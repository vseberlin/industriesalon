<?php
/** wp eval-file: item shape follows the registry, including renamed gestures; no records written. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$accept = static function (array $document) use ($assert): array {
    $result = iss_editorial_validate_document($document, 'projekt');
    $assert(!is_wp_error($result), is_wp_error($result) ? $result->get_error_message() : 'Valid item shape');
    return $result;
};
$rows = 0; $cards = 0; $notes = 0;
foreach (iss_editorial_get_registered_formats() as $slug => $format) {
    foreach ($format['sections'] as $type => $section) {
        if (!in_array('items', $section['supports'], true)) { continue; }
        if (in_array($type, ['text_bild_reihe', 'map_img'], true)) {
            $assert($section['items_kind'] === 'text_image', "$slug.$type declares rows");
            $assert($section['items_label'] === ($type === 'text_bild_reihe' ? 'Bild-Text-Paare' : 'Ziele'), 'Existing row panel labels remain');
            ++$rows;
        } elseif ($slug === 'veranstaltung' && $type === 'material') {
            $assert($section['items_kind'] === 'text', 'Event notes retain their owner contract');
            ++$notes;
        } else {
            $assert($section['items_kind'] === 'cards', "$slug.$type retains cards");
            ++$cards;
        }
    }
}
$assert([$rows, $cards, $notes] === [9, 8, 1], 'All 18 current item definitions are covered');
$probe = static function (array $formats): array {
    $row = $formats['projekt']['sections']['text_bild_reihe'];
    $card = $formats['projekt']['sections']['gateway'];
    $text = $formats['veranstaltung']['sections']['material'];
    $formats['projekt']['sections']['renamed_row'] = $row;
    $formats['projekt']['sections']['gateway'] = $row;
    $formats['projekt']['sections']['renamed_card'] = $card;
    $formats['projekt']['sections']['text_bild_reihe'] = $card;
    $formats['projekt']['sections']['map_img'] = $card;
    $formats['projekt']['sections']['renamed_notes'] = $text;
    $formats['projekt']['sections']['renamed_row']['items_label'] = '<b>Custom rows</b>';
    return $formats;
};
$invalid = static function (array $formats): array {
    $formats['projekt']['sections']['renamed_row']['items_kind'] = 'unregistered';
    return $formats;
};
add_filter('iss_editorial_formats', $probe, 150);
iss_editorial_reset_format_cache();
try {
    $assert(iss_editorial_registry_errors() === [], 'Renamed definitions need no new engine branches');
    $assert(iss_editorial_get_format('projekt')['sections']['renamed_row']['items_label'] === 'Custom rows', 'Panel label is normalized plain text');
    $ref = ['kind' => 'media', 'source' => 'wp-media', 'id' => '123', 'label' => ''];
    foreach ([1, 2, 3] as $version) {
        $document = iss_editorial_get_empty_document('projekt');
        $document['schema_version'] = $version;
        foreach (['renamed_row', 'gateway'] as $type) {
            $item = ['label' => 'Row without link', 'text' => $version === 1 ? 'Plain text' : 'Text with <em>emphasis</em>', 'media_refs' => [$ref], 'eyebrow' => '2026', 'group' => 'Events', 'detail' => "First line\nSecond line"];
            $document['sections'] = [['type' => $type, 'items' => [$item]]];
            $valid = $accept($document);
            $assert($valid['sections'][0]['items'] === [$item], "$type preserves row data in version $version");
            foreach (['eyebrow', 'group', 'detail'] as $key) {
                $bad = $document; $bad['sections'][0]['items'][0][$key] = '<b>Unsupported markup</b>';
                $result = iss_editorial_validate_document($bad, 'projekt');
                $assert(is_wp_error($result) && $result->get_error_code() === 'editorial_invalid_field', "$type validates plain $key");
            }
            $bad = $document; $bad['sections'][0]['items'][0]['media_refs'][] = $ref;
            $result = iss_editorial_validate_document($bad, 'projekt');
            $assert(is_wp_error($result) && $result->get_error_data()['path'] === 'sections[0].items[0].media_refs', 'Rows reject loss of a second image');
        }
        foreach (['renamed_card', 'text_bild_reihe', 'map_img'] as $type) {
            $item = ['label' => 'Linked card', 'text' => $version === 1 ? 'Plain card' : 'Card with <em>emphasis</em>', 'url' => 'https://example.invalid/destination/', 'media_refs' => [$ref, $ref]];
            $document['sections'] = [['type' => $type, 'items' => [$item]]];
            $valid = $accept($document);
            $assert($valid['sections'][0]['items'] === [$item], "$type preserves card links and media in version $version");
            unset($document['sections'][0]['items'][0]['url']);
            $result = iss_editorial_validate_document($document, 'projekt');
            $assert(is_wp_error($result) && $result->get_error_code() === 'editorial_incomplete_items', 'Cards still require a destination');
        }
        $document['sections'] = [['type' => 'renamed_notes', 'items' => ['First note', 'Second note']]];
        $assert($accept($document)['sections'][0]['items'] === ['First note', 'Second note'], 'Renamed text list retains strings');
    }
    add_filter('iss_editorial_formats', $invalid, 160);
    iss_editorial_reset_format_cache();
    $assert(in_array('projekt.renamed_row: unknown item kind', iss_editorial_registry_errors(), true), 'Registry diagnostics reject an unsupported item kind');
    $assert(iss_editorial_get_format('projekt')['sections']['renamed_row']['items_kind'] === 'cards', 'Unrecognized declaration keeps the compatibility fallback');
} finally {
    remove_filter('iss_editorial_formats', $invalid, 160);
    remove_filter('iss_editorial_formats', $probe, 150);
    iss_editorial_reset_format_cache();
}
$assert(iss_editorial_registry_errors() === [], 'Original registry restored');
WP_CLI::success("$checks item contract checks; no persistent fixtures.");
