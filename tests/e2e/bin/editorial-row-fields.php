<?php
/** wp eval-file: treatment-owned row controls do not change stored item contracts. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$formats = iss_editorial_get_registered_formats();
$declarations = 0;
foreach ($formats as $slug => $format) {
    foreach ($format['sections'] as $type => $section) {
        foreach ($section['treatments'] as $treatment) {
            $expected = $type === 'text_bild_reihe' ? (['text-bild-reihe.editorial' => ['eyebrow'], 'text-bild-reihe.agenda' => ['eyebrow', 'group', 'detail']][$treatment['slug']] ?? []) : [];
            $assert($treatment['item_fields'] === $expected, "$slug.$type.{$treatment['slug']} retains control availability");
            if ($expected) { ++$declarations; }
        }
    }
}
$probe = static function (array $formats): array {
    $section = $formats['projekt']['sections']['text_bild_reihe'];
    $section['treatments'] = ['renamed.agenda' => ['label' => 'Renamed agenda', 'schematic' => 'list', 'item_fields' => ['Detail', 'group', 'detail']]];
    $section['default_treatment'] = 'renamed.agenda';
    $formats['projekt']['sections']['renamed_rows'] = $section;
    $formats['projekt']['sections']['text_bild_reihe']['treatments']['text-bild-reihe.agenda']['item_fields'] = [];
    return $formats;
};
add_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache();
try {
    $format = iss_editorial_get_format('projekt');
    $assert($format['sections']['renamed_rows']['treatments'][0]['item_fields'] === ['detail', 'group'], 'Fields normalize and deduplicate without treatment-name dependency');
    $assert(array_column($format['sections']['text_bild_reihe']['treatments'], 'item_fields', 'slug')['text-bild-reihe.agenda'] === [], 'Historical treatment cannot force hidden controls');
    $assert(iss_editorial_registry_errors() === [], 'Renamed rows and treatment remain valid');
    foreach (['renamed_rows' => 'renamed.agenda', 'text_bild_reihe' => 'text-bild-reihe.agenda'] as $type => $treatment) {
        foreach ([1, 2, 3] as $version) {
            $doc = iss_editorial_get_empty_document('projekt'); $doc['schema_version'] = $version;
            $item = ['label' => 'Retained row', 'text' => 'Retained prose.', 'media_refs' => [], 'eyebrow' => '2026', 'group' => 'Workshop', 'detail' => "First line.\nSecond line."];
            $doc['sections'] = [['type' => $type, 'treatment' => $treatment, 'items' => [$item]]];
            $valid = iss_editorial_validate_document($doc, 'projekt');
            $assert(!is_wp_error($valid) && $valid['sections'][0]['items'][0] === $item, "$type v$version retains all fields independently of control visibility");
        }
    }
    foreach ([['item_fields' => ['unknown']], ['kind' => 'cards'], ['supports' => []]] as $invalid) {
        $bad = static function (array $formats) use ($invalid): array {
            $section = &$formats['projekt']['sections']['renamed_rows'];
            if (isset($invalid['item_fields'])) { $section['treatments']['renamed.agenda']['item_fields'] = $invalid['item_fields']; }
            if (isset($invalid['kind'])) { $section['items_kind'] = $invalid['kind']; }
            if (isset($invalid['supports'])) { $section['supports'] = $invalid['supports']; }
            return $formats;
        };
        add_filter('iss_editorial_formats', $bad, 160); iss_editorial_reset_format_cache();
        try {
            $errors = iss_editorial_registry_errors();
            $assert((bool) array_filter($errors, static fn(string $error): bool => str_contains($error, 'projekt.renamed_rows: treatment renamed.agenda') && str_contains($error, 'item fields')), 'Invalid item-field declarations produce an owner diagnostic');
        } finally { remove_filter('iss_editorial_formats', $bad, 160); iss_editorial_reset_format_cache(); }
    }
} finally { remove_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache(); }
$assert(wp_json_encode(iss_editorial_get_registered_formats()) === wp_json_encode($formats), 'Registry restored');
WP_CLI::success("$checks row-field checks; $declarations owner declarations; no persistent fixtures.");
