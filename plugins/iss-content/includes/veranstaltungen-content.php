<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_veranstaltung_content_meta_key(): string
{
    return '_iss_content_json';
}

function iss_content_model_veranstaltung_skin_override_meta_key(): string
{
    return '_iss_skin_override';
}

function iss_content_model_veranstaltung_empty_content_document(string $entity_key = ''): array
{
    $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
        ? iss_content_model_sanitize_veranstaltung_entity_key($entity_key)
        : '';

    return [
        'schema_version' => 1,
        'entity_key' => $entity_key,
        'sections' => [],
    ];
}

function iss_content_model_veranstaltung_content_gestures(): array
{
    return [
        'intro' => [
            'label' => __('Intro', 'iss-content-model'),
            'description' => __('Kurz gefasster Einstieg oder Ankuendigungstext.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'media_refs', 'dynamic_refs'],
        ],
        'kapitel' => [
            'label' => __('Kapitel', 'iss-content-model'),
            'description' => __('Thematischer Abschnitt fuer Kontext, Einordnung oder Ablauf.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'media_refs', 'dynamic_refs'],
        ],
        'leitfrage' => [
            'label' => __('Leitfrage', 'iss-content-model'),
            'description' => __('Frage oder These, die den Beitrag rahmt.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body'],
        ],
        'zitat' => [
            'label' => __('Zitat', 'iss-content-model'),
            'description' => __('Zitat mit Zuordnung oder Quellenhinweis.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'quote', 'attribution'],
        ],
        'chronik' => [
            'label' => __('Chronik', 'iss-content-model'),
            'description' => __('Zeitliche Folge oder Rueckblickabschnitt.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'items'],
        ],
        'programm' => [
            'label' => __('Programm', 'iss-content-model'),
            'description' => __('Lineare Programmpunkte fuer Feste und Reihen.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'items'],
        ],
        'material' => [
            'label' => __('Material', 'iss-content-model'),
            'description' => __('Hinweise auf Quellen, Material, Links oder Begleitangebote.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'items', 'media_refs', 'object_refs', 'dynamic_refs'],
        ],
        'galerie' => [
            'label' => __('Galerie', 'iss-content-model'),
            'description' => __('Platzhalter fuer eine spaetere Medien- oder Objektstrecke.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'media_refs', 'object_refs'],
        ],
    ];
}

function iss_content_model_veranstaltung_content_gestures_for_entity(string $entity_key): array
{
    $gestures = iss_content_model_veranstaltung_content_gestures();
    $entity = function_exists('iss_content_model_veranstaltung_entity')
        ? iss_content_model_veranstaltung_entity($entity_key)
        : [];
    $allowed = array_values(array_filter(array_map('sanitize_key', (array) ($entity['allowed_gestures'] ?? []))));

    if (!$allowed) {
        return $gestures;
    }

    return array_intersect_key($gestures, array_fill_keys($allowed, true));
}

function iss_content_model_veranstaltung_content_type_for_entity(string $entity_key, string $preferred_type): string
{
    $gestures = iss_content_model_veranstaltung_content_gestures_for_entity($entity_key);
    $preferred_type = sanitize_key($preferred_type);
    if ($preferred_type !== '' && isset($gestures[$preferred_type])) {
        return $preferred_type;
    }
    if (isset($gestures['kapitel'])) {
        return 'kapitel';
    }
    if (isset($gestures['intro'])) {
        return 'intro';
    }

    $keys = array_keys($gestures);
    return (string) ($keys[0] ?? 'intro');
}

function iss_content_model_sanitize_veranstaltung_content_items($items): array
{
    if (is_string($items)) {
        $items = preg_split('/\R/u', $items) ?: [];
    }

    $sanitized = [];
    foreach ((array) $items as $item) {
        $item = trim(sanitize_text_field((string) $item));
        if ($item !== '') {
            $sanitized[] = $item;
        }
    }

    return $sanitized;
}

function iss_content_model_sanitize_veranstaltung_content_reference($reference): array
{
    if (!is_array($reference)) {
        return [];
    }

    $kind = sanitize_key((string) ($reference['kind'] ?? ''));
    $source = sanitize_key((string) ($reference['source'] ?? ''));
    $id = sanitize_text_field((string) ($reference['id'] ?? ''));
    if ($kind === '' || $source === '' || $id === '') {
        return [];
    }

    $label = sanitize_textarea_field((string) ($reference['label'] ?? ''));
    if ($source === 'iss-archive') {
        $label = iss_content_model_veranstaltung_content_compact_reference_text($label, 140);
    }

    $sanitized = [
        'kind' => $kind,
        'source' => $source,
        'id' => $id,
        'label' => $label,
    ];

    if ($source !== 'wp-media') {
        $sanitized['thumbnail'] = esc_url_raw((string) ($reference['thumbnail'] ?? ''));
    }

    foreach (['set_id', 'member_id', 'width', 'height'] as $field) {
        if (isset($reference[$field])) {
            $sanitized[$field] = (string) absint($reference[$field]);
        }
    }

    foreach (['set_title'] as $field) {
        if (isset($reference[$field])) {
            $sanitized[$field] = sanitize_textarea_field((string) $reference[$field]);
        }
    }

    return $sanitized;
}

function iss_content_model_veranstaltung_content_compact_reference_text(string $value, int $limit): string
{
    $value = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags($value)));
    if ($limit <= 0 || strlen($value) <= $limit) {
        return $value;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim((string) mb_substr($value, 0, max(1, $limit - 1))) . '…';
    }

    return rtrim(substr($value, 0, max(1, $limit - 1))) . '…';
}

function iss_content_model_sanitize_veranstaltung_content_reference_list($references): array
{
    $items = [];
    foreach ((array) $references as $reference) {
        $reference = iss_content_model_sanitize_veranstaltung_content_reference($reference);
        if ($reference) {
            $items[] = $reference;
        }
    }

    return $items;
}

function iss_content_model_sanitize_veranstaltung_content_dynamic_reference($reference): array
{
    if (!is_array($reference)) {
        return [];
    }

    $kind = sanitize_key((string) ($reference['kind'] ?? ''));
    $source = sanitize_key((string) ($reference['source'] ?? ''));
    $key = trim(sanitize_text_field((string) ($reference['key'] ?? '')));
    if ($kind !== 'control_field' || $source !== 'industriesalon-steuerung' || $key === '') {
        return [];
    }
    if (!preg_match('/^[a-z0-9_.-]+$/', $key)) {
        return [];
    }

    $sanitized = [
        'kind' => $kind,
        'source' => $source,
        'key' => $key,
        'label' => sanitize_text_field((string) ($reference['label'] ?? '')),
    ];

    foreach (['tagName', 'linkMode', 'text', 'cssClass', 'hrefSuffix'] as $field) {
        $value = trim(sanitize_text_field((string) ($reference[$field] ?? '')));
        if ($value !== '') {
            $sanitized[$field] = $value;
        }
    }

    return $sanitized;
}

function iss_content_model_sanitize_veranstaltung_content_dynamic_reference_list($references): array
{
    $items = [];
    foreach ((array) $references as $reference) {
        $reference = iss_content_model_sanitize_veranstaltung_content_dynamic_reference($reference);
        if ($reference) {
            $items[] = $reference;
        }
    }

    return $items;
}

function iss_content_model_veranstaltung_content_media_reference(int $attachment_id, string $label = ''): array
{
    $post = $attachment_id > 0 ? get_post($attachment_id) : null;
    if (!$post instanceof WP_Post || $post->post_type !== 'attachment') {
        return [];
    }

    $metadata = wp_get_attachment_metadata($attachment_id);
    $width = is_array($metadata) ? absint($metadata['width'] ?? 0) : 0;
    $height = is_array($metadata) ? absint($metadata['height'] ?? 0) : 0;

    return [
        'kind' => 'media',
        'source' => 'wp-media',
        'id' => (string) $attachment_id,
        'label' => $label !== '' ? $label : (string) get_the_title($post),
        'thumbnail' => '',
        'width' => (string) $width,
        'height' => (string) $height,
    ];
}

function iss_content_model_veranstaltung_content_extract_text_from_block(array $block): string
{
    $html = trim((string) ($block['innerHTML'] ?? ''));
    if ($html === '' && isset($block['innerContent']) && is_array($block['innerContent'])) {
        $html = trim(implode('', array_filter(array_map('strval', $block['innerContent']))));
    }

    return trim(wp_strip_all_tags($html));
}

function iss_content_model_veranstaltung_content_image_caption(array $block): string
{
    foreach (array_filter((array) ($block['innerBlocks'] ?? []), 'is_array') as $inner_block) {
        $text = iss_content_model_veranstaltung_content_extract_text_from_block($inner_block);
        if ($text !== '') {
            return $text;
        }
    }

    $html = trim((string) ($block['innerHTML'] ?? ''));
    if ($html !== '' && preg_match('/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', $html, $matches)) {
        return trim(wp_strip_all_tags((string) $matches[1]));
    }

    return '';
}

function iss_content_model_veranstaltung_content_media_refs_from_block(array $block): array
{
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $block_name = (string) ($block['blockName'] ?? '');
    $ids = [];

    if (isset($attrs['id'])) {
        $ids[] = absint($attrs['id']);
    }
    if (isset($attrs['mediaId'])) {
        $ids[] = absint($attrs['mediaId']);
    }

    $html = trim((string) ($block['innerHTML'] ?? ''));
    if ($html !== '' && preg_match_all('/wp-image-([0-9]+)/', $html, $matches)) {
        foreach ($matches[1] as $match) {
            $ids[] = absint($match);
        }
    }

    if (in_array($block_name, ['core/gallery', 'core/media-text'], true)) {
        foreach (array_filter((array) ($block['innerBlocks'] ?? []), 'is_array') as $inner_block) {
            foreach (iss_content_model_veranstaltung_content_media_refs_from_block($inner_block) as $reference) {
                $refs[$reference['id']] = $reference;
            }
        }
    }

    $caption = iss_content_model_veranstaltung_content_image_caption($block);
    $refs = $refs ?? [];
    foreach (array_values(array_unique(array_filter($ids))) as $attachment_id) {
        $reference = iss_content_model_veranstaltung_content_media_reference((int) $attachment_id, $caption);
        if ($reference) {
            $refs[$reference['id']] = $reference;
        }
    }

    return array_values($refs);
}

function iss_content_model_veranstaltung_content_add_media_refs(array &$section, array $references): void
{
    if (!$references) {
        return;
    }

    $existing = [];
    foreach ((array) ($section['media_refs'] ?? []) as $reference) {
        if (is_array($reference) && !empty($reference['id'])) {
            $existing[(string) $reference['id']] = $reference;
        }
    }

    foreach ($references as $reference) {
        if (is_array($reference) && !empty($reference['id'])) {
            $existing[(string) $reference['id']] = $reference;
        }
    }

    $section['media_refs'] = array_values($existing);
}

function iss_content_model_veranstaltung_content_dynamic_field_label(string $key): string
{
    if ($key === 'address.full' || $key === 'general.address_full') {
        return __('Volle Adresse', 'iss-content-model');
    }

    return $key;
}

function iss_content_model_veranstaltung_content_dynamic_ref_from_block(array $block): array
{
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
    $key = trim(sanitize_text_field((string) ($attrs['key'] ?? '')));
    if ($key === '') {
        return [];
    }

    $reference = [
        'kind' => 'control_field',
        'source' => 'industriesalon-steuerung',
        'key' => $key,
        'label' => trim(sanitize_text_field((string) ($attrs['label'] ?? ''))),
    ];
    if ($reference['label'] === '') {
        $reference['label'] = iss_content_model_veranstaltung_content_dynamic_field_label($key);
    }

    foreach (['tagName', 'linkMode', 'text', 'cssClass', 'hrefSuffix'] as $field) {
        if (isset($attrs[$field])) {
            $reference[$field] = (string) $attrs[$field];
        }
    }

    return iss_content_model_sanitize_veranstaltung_content_dynamic_reference($reference);
}

function iss_content_model_veranstaltung_content_add_dynamic_refs(array &$section, array $references): void
{
    if (!$references) {
        return;
    }

    $existing = [];
    foreach ((array) ($section['dynamic_refs'] ?? []) as $reference) {
        if (is_array($reference) && !empty($reference['key'])) {
            $existing[(string) $reference['key']] = $reference;
        }
    }

    foreach ($references as $reference) {
        if (is_array($reference) && !empty($reference['key'])) {
            $existing[(string) $reference['key']] = $reference;
        }
    }

    $section['dynamic_refs'] = array_values($existing);
}

function iss_content_model_veranstaltung_content_block_class(array $block): string
{
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

    return trim((string) ($attrs['className'] ?? ''));
}

function iss_content_model_veranstaltung_content_block_has_class(array $block, string $class_name): bool
{
    $classes = preg_split('/\s+/', iss_content_model_veranstaltung_content_block_class($block)) ?: [];

    return in_array($class_name, $classes, true);
}

function iss_content_model_veranstaltung_content_block_anchor(array $block): string
{
    $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

    return sanitize_key((string) ($attrs['anchor'] ?? ''));
}

function iss_content_model_veranstaltung_content_append_section_body(array &$section, string $text): void
{
    $text = trim($text);
    if ($text === '') {
        return;
    }

    $current = trim((string) ($section['body'] ?? ''));
    $section['body'] = $current !== '' ? $current . "\n\n" . $text : $text;
}

function iss_content_model_veranstaltung_content_table_rows(array $block): array
{
    $html = trim((string) ($block['innerHTML'] ?? ''));
    if ($html === '') {
        return [];
    }

    if (!preg_match_all('/<tr\b[^>]*>(.*?)<\/tr>/is', $html, $row_matches)) {
        return [];
    }

    $rows = [];
    foreach ($row_matches[1] as $row_html) {
        if (!preg_match_all('/<t[dh]\b[^>]*>(.*?)<\/t[dh]>/is', (string) $row_html, $cell_matches)) {
            continue;
        }

        $cells = [];
        foreach ($cell_matches[1] as $cell_html) {
            $cell = trim(wp_strip_all_tags((string) $cell_html));
            if ($cell !== '') {
                $cells[] = $cell;
            }
        }

        if ($cells) {
            $rows[] = implode(' - ', $cells);
        }
    }

    return $rows;
}

function iss_content_model_veranstaltung_content_material_row_item(array $block): string
{
    $parts = [];
    foreach (array_filter((array) ($block['innerBlocks'] ?? []), 'is_array') as $inner_block) {
        $text = iss_content_model_veranstaltung_content_extract_text_from_block($inner_block);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    if (!$parts) {
        $text = iss_content_model_veranstaltung_content_extract_text_from_block($block);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim(implode(' - ', $parts));
}

function iss_content_model_veranstaltung_content_type_for_format_section(array $block, string $entity_key): string
{
    $anchor = iss_content_model_veranstaltung_content_block_anchor($block);
    if (iss_content_model_veranstaltung_content_block_has_class($block, 'iss-event-materials') || in_array($anchor, ['material', 'materialien'], true)) {
        return iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'material');
    }
    if ($anchor === 'fragen') {
        return iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'leitfrage');
    }
    if ($anchor === 'ablauf') {
        return iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'programm');
    }

    return iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'kapitel');
}

function iss_content_model_veranstaltung_content_collect_format_section_block(array $block, array &$section, array &$unsupported_blocks, int &$media_blocks, string $entity_key): void
{
    $block_name = (string) ($block['blockName'] ?? '');
    $text = iss_content_model_veranstaltung_content_extract_text_from_block($block);
    $inner_blocks = array_filter((array) ($block['innerBlocks'] ?? []), 'is_array');

    if (iss_content_model_veranstaltung_content_block_has_class($block, 'iss-event-format-kicker')) {
        if ($text !== '') {
            $section['kicker'] = $text;
        }
        return;
    }

    if (iss_content_model_veranstaltung_content_block_has_class($block, 'iss-event-material-row')) {
        $item = iss_content_model_veranstaltung_content_material_row_item($block);
        if ($item !== '') {
            $section['items'][] = $item;
        }
        return;
    }

    if ($block_name === 'core/heading') {
        if ($text !== '') {
            if (trim((string) ($section['title'] ?? '')) === '') {
                $section['title'] = $text;
            } else {
                iss_content_model_veranstaltung_content_append_section_body($section, $text);
            }
        }
        return;
    }

    if ($block_name === 'core/table') {
        $rows = iss_content_model_veranstaltung_content_table_rows($block);
        if ($rows) {
            if (in_array((string) ($section['type'] ?? ''), ['material', 'programm'], true)) {
                $section['items'] = array_merge((array) ($section['items'] ?? []), $rows);
            } else {
                iss_content_model_veranstaltung_content_append_section_body($section, implode("\n", $rows));
            }
        } elseif ($text !== '') {
            iss_content_model_veranstaltung_content_append_section_body($section, $text);
        }
        return;
    }

    if ($block_name === 'core/quote') {
        $type = iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'zitat');
        if ($type === 'zitat' && $text !== '') {
            $section['type'] = 'zitat';
            $section['quote'] = $text;
            return;
        }
    }

    if ($block_name === 'industriesalon/field') {
        iss_content_model_veranstaltung_content_add_dynamic_refs(
            $section,
            [iss_content_model_veranstaltung_content_dynamic_ref_from_block($block)]
        );
        return;
    }

    if (in_array($block_name, ['core/image', 'core/gallery', 'core/media-text', 'core/video'], true)) {
        ++$media_blocks;
        iss_content_model_veranstaltung_content_add_media_refs(
            $section,
            iss_content_model_veranstaltung_content_media_refs_from_block($block)
        );
        if ($text !== '') {
            iss_content_model_veranstaltung_content_append_section_body($section, $text);
        }
        return;
    }

    if ($block_name === 'core/embed') {
        ++$media_blocks;
        if ($text !== '') {
            iss_content_model_veranstaltung_content_append_section_body($section, $text);
        }
        return;
    }

    if (in_array($block_name, ['', 'core/paragraph', 'core/list', 'core/freeform'], true)) {
        iss_content_model_veranstaltung_content_append_section_body($section, $text);
        return;
    }

    foreach ($inner_blocks as $inner_block) {
        iss_content_model_veranstaltung_content_collect_format_section_block($inner_block, $section, $unsupported_blocks, $media_blocks, $entity_key);
    }

    if ($text !== '' && !$inner_blocks) {
        iss_content_model_veranstaltung_content_append_section_body($section, $text);
        return;
    }

    if ($block_name !== '' && !$inner_blocks) {
        $unsupported_blocks[$block_name] = true;
    }
}

function iss_content_model_veranstaltung_content_process_format_section(array $block, array &$sections, array &$unsupported_blocks, int &$media_blocks, string $entity_key): void
{
    $section = [
        'type' => iss_content_model_veranstaltung_content_type_for_format_section($block, $entity_key),
    ];

    foreach (array_filter((array) ($block['innerBlocks'] ?? []), 'is_array') as $inner_block) {
        iss_content_model_veranstaltung_content_collect_format_section_block($inner_block, $section, $unsupported_blocks, $media_blocks, $entity_key);
    }

    if (trim((string) ($section['kicker'] ?? '')) === '') {
        $anchor = iss_content_model_veranstaltung_content_block_anchor($block);
        if ($anchor !== '') {
            $section['kicker'] = ucwords(str_replace(['-', '_'], ' ', $anchor));
        }
    }

    $sections[] = $section;
}

function iss_content_model_veranstaltung_content_process_format_sheet(array $block, array &$sections, array &$unsupported_blocks, int &$media_blocks, string $entity_key): void
{
    $inner_blocks = array_filter((array) ($block['innerBlocks'] ?? []), 'is_array');

    foreach ($inner_blocks as $inner_block) {
        if (iss_content_model_veranstaltung_content_block_has_class($inner_block, 'iss-event-format-nav')) {
            continue;
        }

        if (
            iss_content_model_veranstaltung_content_block_has_class($inner_block, 'iss-event-format-chapter')
            || iss_content_model_veranstaltung_content_block_has_class($inner_block, 'iss-event-materials')
        ) {
            iss_content_model_veranstaltung_content_process_format_section($inner_block, $sections, $unsupported_blocks, $media_blocks, $entity_key);
            continue;
        }

        foreach (array_filter((array) ($inner_block['innerBlocks'] ?? []), 'is_array') as $nested_block) {
            if (iss_content_model_veranstaltung_content_block_has_class($nested_block, 'iss-event-format-nav')) {
                continue;
            }

            if (
                iss_content_model_veranstaltung_content_block_has_class($nested_block, 'iss-event-format-chapter')
                || iss_content_model_veranstaltung_content_block_has_class($nested_block, 'iss-event-materials')
            ) {
                iss_content_model_veranstaltung_content_process_format_section($nested_block, $sections, $unsupported_blocks, $media_blocks, $entity_key);
            }
        }
    }
}

function iss_content_model_veranstaltung_content_count_format_sheets(array $blocks): int
{
    $count = 0;
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        if (iss_content_model_veranstaltung_content_block_has_class($block, 'iss-event-format-sheet')) {
            ++$count;
        }

        $count += iss_content_model_veranstaltung_content_count_format_sheets(array_filter((array) ($block['innerBlocks'] ?? []), 'is_array'));
    }

    return $count;
}

function iss_content_model_veranstaltung_content_append_body(array &$sections, string $text, string $entity_key): void
{
    $text = trim($text);
    if ($text === '') {
        return;
    }

    if (!$sections) {
        $sections[] = [
            'type' => iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'intro'),
            'title' => '',
            'body' => '',
        ];
    }

    $last_index = count($sections) - 1;
    $current = trim((string) ($sections[$last_index]['body'] ?? ''));
    $sections[$last_index]['body'] = $current !== '' ? $current . "\n\n" . $text : $text;
}

function iss_content_model_veranstaltung_content_append_dynamic_refs(array &$sections, array $references, string $entity_key): void
{
    if (!$references) {
        return;
    }

    if (!$sections) {
        $sections[] = [
            'type' => iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'intro'),
            'title' => '',
            'body' => '',
        ];
    }

    $last_index = count($sections) - 1;
    iss_content_model_veranstaltung_content_add_dynamic_refs($sections[$last_index], $references);
}

function iss_content_model_veranstaltung_content_process_block(array $block, array &$sections, array &$unsupported_blocks, int &$media_blocks, string $entity_key): void
{
    $block_name = (string) ($block['blockName'] ?? '');
    $text = iss_content_model_veranstaltung_content_extract_text_from_block($block);
    $inner_blocks = array_filter((array) ($block['innerBlocks'] ?? []), 'is_array');

    if (iss_content_model_veranstaltung_content_block_has_class($block, 'iss-event-format-sheet')) {
        iss_content_model_veranstaltung_content_process_format_sheet($block, $sections, $unsupported_blocks, $media_blocks, $entity_key);
        return;
    }

    if (iss_content_model_veranstaltung_content_block_has_class($block, 'iss-event-format-nav')) {
        return;
    }

    if (
        iss_content_model_veranstaltung_content_block_has_class($block, 'iss-event-format-chapter')
        || iss_content_model_veranstaltung_content_block_has_class($block, 'iss-event-materials')
    ) {
        iss_content_model_veranstaltung_content_process_format_section($block, $sections, $unsupported_blocks, $media_blocks, $entity_key);
        return;
    }

    if ($block_name === 'core/heading') {
        $sections[] = [
            'type' => iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'kapitel'),
            'title' => $text !== '' ? $text : __('Ohne Titel', 'iss-content-model'),
            'body' => '',
        ];
        return;
    }

    if ($block_name === 'core/quote') {
        $type = iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'zitat');
        if ($type === 'zitat') {
            $sections[] = [
                'type' => 'zitat',
                'title' => '',
                'quote' => $text,
            ];
            return;
        }
    }

    if ($block_name === 'industriesalon/field') {
        iss_content_model_veranstaltung_content_append_dynamic_refs(
            $sections,
            [iss_content_model_veranstaltung_content_dynamic_ref_from_block($block)],
            $entity_key
        );
        return;
    }

    if (in_array($block_name, ['core/image', 'core/gallery', 'core/media-text', 'core/video'], true)) {
        ++$media_blocks;
        $media_refs = iss_content_model_veranstaltung_content_media_refs_from_block($block);
        if (!$sections) {
            $sections[] = [
                'type' => iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'intro'),
                'title' => '',
                'body' => '',
            ];
        }
        $last_index = count($sections) - 1;
        iss_content_model_veranstaltung_content_add_media_refs($sections[$last_index], $media_refs);
        if ($text !== '') {
            iss_content_model_veranstaltung_content_append_body($sections, $text, $entity_key);
        }
        return;
    }

    if ($block_name === 'core/embed') {
        ++$media_blocks;
        if ($text !== '') {
            iss_content_model_veranstaltung_content_append_body($sections, $text, $entity_key);
        }
        return;
    }

    if (in_array($block_name, ['', 'core/paragraph', 'core/list', 'core/freeform'], true)) {
        iss_content_model_veranstaltung_content_append_body($sections, $text, $entity_key);
        return;
    }

    foreach ($inner_blocks as $inner_block) {
        iss_content_model_veranstaltung_content_process_block($inner_block, $sections, $unsupported_blocks, $media_blocks, $entity_key);
    }

    if ($text !== '' && !$inner_blocks) {
        iss_content_model_veranstaltung_content_append_body($sections, $text, $entity_key);
        return;
    }

    if ($block_name !== '' && !$inner_blocks) {
        $unsupported_blocks[$block_name] = true;
    }
}

function iss_content_model_veranstaltung_content_candidate_for_post(WP_Post $post): array
{
    $post_id = (int) $post->ID;
    $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
        ? iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true))
        : '';

    $blocks = parse_blocks((string) $post->post_content);
    $sections = [];
    $unsupported_blocks = [];
    $media_blocks = 0;
    $format_sheet_blocks = iss_content_model_veranstaltung_content_count_format_sheets($blocks);

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        iss_content_model_veranstaltung_content_process_block($block, $sections, $unsupported_blocks, $media_blocks, $entity_key);
    }

    $sections = array_values(array_filter($sections, static function (array $section): bool {
        return trim((string) ($section['title'] ?? '')) !== ''
            || trim((string) ($section['body'] ?? '')) !== ''
            || trim((string) ($section['quote'] ?? '')) !== ''
            || !empty($section['items'])
            || !empty($section['media_refs'])
            || !empty($section['object_refs'])
            || !empty($section['dynamic_refs']);
    }));

    if (!$sections && has_excerpt($post)) {
        $sections[] = [
            'type' => iss_content_model_veranstaltung_content_type_for_entity($entity_key, 'intro'),
            'title' => '',
            'body' => trim((string) get_the_excerpt($post)),
        ];
    }

    $document = [
        'schema_version' => 1,
        'entity_key' => $entity_key,
        'sections' => $sections,
    ];
    $sanitized = iss_content_model_sanitize_veranstaltung_content_json((string) wp_json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $decoded = $sanitized !== '' ? json_decode($sanitized, true) : [];

    return [
        'document' => is_array($decoded) ? $decoded : iss_content_model_veranstaltung_empty_content_document($entity_key),
        'entity_key' => $entity_key,
        'legacy_blocks' => count($blocks),
        'legacy_chars' => strlen(wp_strip_all_tags((string) $post->post_content)),
        'format_sheet_blocks' => $format_sheet_blocks,
        'media_blocks' => $media_blocks,
        'unsupported_blocks' => array_keys($unsupported_blocks),
    ];
}

function iss_content_model_sanitize_veranstaltung_content_json($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return '';
    }

    $schema_version = (int) ($decoded['schema_version'] ?? $decoded['schemaVersion'] ?? 0);
    if ($schema_version !== 1) {
        return '';
    }

    $entity_key = '';
    if (isset($decoded['entity_key'])) {
        $entity_key = iss_content_model_sanitize_veranstaltung_entity_key((string) $decoded['entity_key']);
        if ($entity_key === '') {
            return '';
        }
    }

    $allowed_gestures = array_keys(iss_content_model_veranstaltung_content_gestures_for_entity($entity_key));
    if (!$allowed_gestures) {
        $allowed_gestures = array_keys(iss_content_model_veranstaltung_content_gestures());
    }
    $allowed_lookup = array_fill_keys($allowed_gestures, true);
    $sections = [];
    foreach ((array) ($decoded['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $type = sanitize_key((string) ($section['type'] ?? ''));
        if ($type === '' || !isset($allowed_lookup[$type])) {
            continue;
        }

        $normalized_section = [
            'type' => $type,
        ];

        foreach (['kicker', 'title', 'attribution'] as $field) {
            $value = trim(sanitize_text_field((string) ($section[$field] ?? '')));
            if ($value !== '') {
                $normalized_section[$field] = $value;
            }
        }

        foreach (['body', 'quote'] as $field) {
            $value = trim(sanitize_textarea_field((string) ($section[$field] ?? '')));
            if ($value !== '') {
                $normalized_section[$field] = $value;
            }
        }

        $items = iss_content_model_sanitize_veranstaltung_content_items($section['items'] ?? []);
        if ($items) {
            $normalized_section['items'] = $items;
        }

        $gesture = $allowed_lookup[$type] ? iss_content_model_veranstaltung_content_gestures()[$type] ?? [] : [];
        $supports = (array) ($gesture['supports'] ?? []);
        if (in_array('media_refs', $supports, true)) {
            $media_refs = iss_content_model_sanitize_veranstaltung_content_reference_list($section['media_refs'] ?? []);
            if ($media_refs) {
                $normalized_section['media_refs'] = $media_refs;
            }
        }
        if (in_array('object_refs', $supports, true)) {
            $object_refs = iss_content_model_sanitize_veranstaltung_content_reference_list($section['object_refs'] ?? []);
            if ($object_refs) {
                $normalized_section['object_refs'] = $object_refs;
            }
        }
        if (in_array('dynamic_refs', $supports, true)) {
            $dynamic_refs = iss_content_model_sanitize_veranstaltung_content_dynamic_reference_list($section['dynamic_refs'] ?? []);
            if ($dynamic_refs) {
                $normalized_section['dynamic_refs'] = $dynamic_refs;
            }
        }

        $sections[] = $normalized_section;
    }

    $normalized = [
        'schema_version' => 1,
        'entity_key' => $entity_key,
        'sections' => $sections,
    ];

    return (string) wp_json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function iss_content_model_veranstaltung_content_document(int $post_id): array
{
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return [];
    }

    $stored = trim((string) get_post_meta($post_id, iss_content_model_veranstaltung_content_meta_key(), true));
    if ($stored === '') {
        return [];
    }

    $sanitized = iss_content_model_sanitize_veranstaltung_content_json($stored);
    if ($sanitized === '') {
        return [];
    }

    $decoded = json_decode($sanitized, true);

    return is_array($decoded) ? $decoded : [];
}

function iss_content_model_register_veranstaltung_content_meta(): void
{
    register_post_meta(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, iss_content_model_veranstaltung_content_meta_key(), [
        'single' => true,
        'type' => 'string',
        'default' => '',
        'show_in_rest' => true,
        'sanitize_callback' => 'iss_content_model_sanitize_veranstaltung_content_json',
        'auth_callback' => static function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_post_meta(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, iss_content_model_veranstaltung_skin_override_meta_key(), [
        'single' => true,
        'type' => 'string',
        'default' => '',
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_key',
        'auth_callback' => static function () {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('init', 'iss_content_model_register_veranstaltung_content_meta', 25);
