<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_editorial_decode_document($value): array
{
    if (is_array($value)) {
        return $value;
    }

    if (!is_string($value) || trim($value) === '') {
        return [];
    }

    $decoded = json_decode($value, true);

    return is_array($decoded) ? $decoded : [];
}

function iss_editorial_sanitize_reference($reference): array
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

    $sanitized = [
        'kind' => $kind,
        'source' => $source,
        'id' => $id,
        'label' => sanitize_textarea_field((string) ($reference['label'] ?? '')),
        'thumbnail' => esc_url_raw((string) ($reference['thumbnail'] ?? '')),
    ];

    if (isset($reference['set_id'])) {
        $sanitized['set_id'] = (string) absint($reference['set_id']);
    }
    if (isset($reference['set_title'])) {
        $sanitized['set_title'] = sanitize_text_field((string) $reference['set_title']);
    }
    if (isset($reference['member_id'])) {
        $sanitized['member_id'] = (string) absint($reference['member_id']);
    }
    if (isset($reference['member_caption'])) {
        $sanitized['member_caption'] = sanitize_textarea_field((string) $reference['member_caption']);
    }
    if (isset($reference['width'])) {
        $sanitized['width'] = (string) absint($reference['width']);
    }
    if (isset($reference['height'])) {
        $sanitized['height'] = (string) absint($reference['height']);
    }

    return $sanitized;
}

function iss_editorial_sanitize_reference_list($references): array
{
    $items = [];
    foreach ((array) $references as $reference) {
        $reference = iss_editorial_sanitize_reference($reference);
        if ($reference) {
            $items[] = $reference;
        }
    }

    return $items;
}

function iss_editorial_sanitize_link($link): array
{
    if (!is_array($link)) {
        return [];
    }

    $label = sanitize_text_field((string) ($link['label'] ?? ''));
    $url = esc_url_raw((string) ($link['url'] ?? ''));
    if ($label === '' || $url === '') {
        return [];
    }

    return [
        'label' => $label,
        'url' => $url,
    ];
}

function iss_editorial_sanitize_link_list($links): array
{
    $items = [];
    foreach ((array) $links as $link) {
        $link = iss_editorial_sanitize_link($link);
        if ($link) {
            $items[] = $link;
        }
    }

    return $items;
}

function iss_editorial_sanitize_fact($fact): array
{
    if (!is_array($fact)) {
        return [];
    }

    $value = sanitize_text_field((string) ($fact['value'] ?? ''));
    $label = sanitize_textarea_field((string) ($fact['label'] ?? ''));
    if ($value === '' && $label === '') {
        return [];
    }

    return [
        'value' => $value,
        'label' => $label,
    ];
}

function iss_editorial_sanitize_fact_list($facts): array
{
    $items = [];
    foreach ((array) $facts as $fact) {
        $fact = iss_editorial_sanitize_fact($fact);
        if ($fact) {
            $items[] = $fact;
        }
    }

    return $items;
}

function iss_editorial_sanitize_rail_options($options): array
{
    $options = is_array($options) ? $options : [];
    $variant = sanitize_key((string) ($options['variant'] ?? 'detailed'));
    if (!in_array($variant, ['detailed', 'compact'], true)) {
        $variant = 'detailed';
    }

    return [
        'show_nav' => !array_key_exists('show_nav', $options) || !empty($options['show_nav']),
        'show_summary' => !array_key_exists('show_summary', $options) || !empty($options['show_summary']),
        'show_related' => !array_key_exists('show_related', $options) || !empty($options['show_related']),
        'variant' => $variant,
    ];
}

function iss_editorial_sanitize_album_source($source): array
{
    if (!is_array($source)) {
        return [];
    }

    $kind = sanitize_key((string) ($source['kind'] ?? ''));
    if (!in_array($kind, ['archive_set', 'editorial_set', 'manual'], true)) {
        return [];
    }

    $source_id = absint($source['set_id'] ?? $source['id'] ?? 0);
    if ($kind !== 'manual' && $source_id <= 0) {
        return [];
    }

    return [
        'kind' => $kind,
        'set_id' => $source_id > 0 ? (string) $source_id : '',
        'set_title' => sanitize_text_field((string) ($source['set_title'] ?? $source['title'] ?? '')),
    ];
}

function iss_editorial_sanitize_album_sheet($sheet): array
{
    if (!is_array($sheet)) {
        return [];
    }

    $source_kind = sanitize_key((string) ($sheet['source_kind'] ?? ''));
    if (!in_array($source_kind, ['archive_object', 'wp_media'], true)) {
        return [];
    }

    $source_id = absint($sheet['source_id'] ?? $sheet['object_id'] ?? $sheet['attachment_id'] ?? 0);
    if ($source_id <= 0) {
        return [];
    }

    $sanitized = [
        'source_kind' => $source_kind,
        'source_id' => (string) $source_id,
        'visible' => !array_key_exists('visible', $sheet) || !empty($sheet['visible']),
        'label' => sanitize_text_field((string) ($sheet['label'] ?? '')),
        'nav_title' => sanitize_text_field((string) ($sheet['nav_title'] ?? '')),
        'caption' => sanitize_textarea_field((string) ($sheet['caption'] ?? '')),
        'caption_override' => sanitize_textarea_field((string) ($sheet['caption_override'] ?? '')),
        'thumbnail' => esc_url_raw((string) ($sheet['thumbnail'] ?? '')),
        'position' => absint($sheet['position'] ?? 0),
    ];

    foreach (['source_set_id', 'source_item_id', 'member_id'] as $key) {
        if (isset($sheet[$key])) {
            $sanitized[$key] = (string) absint($sheet[$key]);
        }
    }

    return $sanitized;
}

function iss_editorial_sanitize_album_sheet_list($sheets): array
{
    $items = [];
    foreach ((array) $sheets as $index => $sheet) {
        $sheet = iss_editorial_sanitize_album_sheet($sheet);
        if (!$sheet) {
            continue;
        }
        if ((int) ($sheet['position'] ?? 0) <= 0) {
            $sheet['position'] = count($items) + 1;
        }
        $items[] = $sheet;
    }

    usort($items, static function (array $a, array $b): int {
        return (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0);
    });

    return array_values($items);
}

function iss_editorial_body_href_is_safe(string $href): bool
{
    $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8'));
    if ($href === '') {
        return false;
    }
    if (preg_match('/^(#|\/|\?|\.{1,2}\/)/', $href)) {
        return true;
    }
    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href)) {
        return preg_match('/^(https?:|mailto:|tel:)/i', $href) === 1;
    }

    return true;
}

function iss_editorial_strip_unsafe_body_hrefs(string $body): string
{
    return (string) preg_replace_callback('/<a\b[^>]*>/i', static function (array $matches): string {
        $tag = $matches[0];
        if (!preg_match('/\s+href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $tag, $href_matches)) {
            return $tag;
        }

        $href = (string) ($href_matches[2] ?? $href_matches[3] ?? $href_matches[4] ?? '');
        if (iss_editorial_body_href_is_safe($href)) {
            return $tag;
        }

        return (string) preg_replace('/\s+href\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', '', $tag, 1);
    }, $body);
}

function iss_editorial_sanitize_body_html(string $body, array $format, string $type): string
{
    if ((string) ($format['slug'] ?? '') !== 'projekt' || !in_array($type, ['kapitel', 'fliesstext', 'schluss'], true)) {
        return wp_kses_post($body);
    }

    $body = iss_editorial_strip_unsafe_body_hrefs($body);

    return wp_kses($body, [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'a' => [
            'href' => true,
        ],
        'ul' => [],
        'ol' => [],
        'li' => [],
    ]);
}

function iss_editorial_sanitize_section(array $section, array $format): array
{
    $type = sanitize_key((string) ($section['type'] ?? ''));
    if ($type === '' || !isset($format['sections'][$type])) {
        return [];
    }

    $sanitized = [
        'type' => $type,
        'kicker' => sanitize_text_field((string) ($section['kicker'] ?? '')),
        'title' => sanitize_text_field((string) ($section['title'] ?? '')),
        'body' => iss_editorial_sanitize_body_html((string) ($section['body'] ?? ''), $format, $type),
    ];

    if (isset($section['anchor'])) {
        $anchor = sanitize_title((string) $section['anchor']);
        if ($anchor !== '') {
            $sanitized['anchor'] = $anchor;
        }
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'quote')) {
        $sanitized['quote'] = wp_kses_post((string) ($section['quote'] ?? ''));
        $sanitized['attribution'] = sanitize_text_field((string) ($section['attribution'] ?? ''));
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'object_refs')) {
        $sanitized['object_refs'] = iss_editorial_sanitize_reference_list($section['object_refs'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'media_refs')) {
        $sanitized['media_refs'] = iss_editorial_sanitize_reference_list($section['media_refs'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'links')) {
        $sanitized['links'] = iss_editorial_sanitize_link_list($section['links'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'facts')) {
        $sanitized['facts'] = iss_editorial_sanitize_fact_list($section['facts'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'year')) {
        $sanitized['year'] = sanitize_text_field((string) ($section['year'] ?? ''));
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'media_layout')) {
        $media_layout = sanitize_key((string) ($section['media_layout'] ?? 'inline'));
        $sanitized['media_layout'] = in_array($media_layout, ['inline', 'aside-right'], true) ? $media_layout : 'inline';
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'rail_options')) {
        $sanitized['rail_options'] = iss_editorial_sanitize_rail_options($section['rail_options'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'album_source')) {
        $sanitized['album_source'] = iss_editorial_sanitize_album_source($section['album_source'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'sheets')) {
        $sanitized['sheets'] = iss_editorial_sanitize_album_sheet_list($section['sheets'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'orientation')) {
        $orientation = sanitize_key((string) ($section['orientation'] ?? ''));
        $sanitized['orientation'] = in_array($orientation, ['media-left', 'media-right'], true) ? $orientation : 'media-left';
    }

    return $sanitized;
}

function iss_editorial_sanitize_document($document, string $format_slug): array
{
    $format = iss_editorial_get_format($format_slug);
    if (!$format) {
        return [];
    }

    $document = iss_editorial_decode_document($document);
    $sanitized = iss_editorial_get_empty_document($format_slug);
    $schema_version = absint($document['schema_version'] ?? 1);
    $sanitized['schema_version'] = max(1, $schema_version);
    $sanitized['skin'] = sanitize_key((string) ($document['skin'] ?? $sanitized['skin']));
    $sanitized['variant'] = sanitize_key((string) ($document['variant'] ?? $sanitized['variant']));
    $sanitized['sections'] = [];

    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $section = iss_editorial_sanitize_section($section, $format);
        if ($section) {
            $sanitized['sections'][] = $section;
        }
    }

    return $sanitized;
}

function iss_editorial_encode_document(array $document): string
{
    $encoded = wp_json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return is_string($encoded) ? $encoded : '';
}

function iss_editorial_get_document(int $post_id, string $format_slug, bool $prefer_autosave = false): array
{
    if ($post_id <= 0) {
        return [];
    }

    if ($prefer_autosave) {
        $autosave = get_post_meta($post_id, iss_editorial_get_autosave_meta_key($format_slug), true);
        if (is_string($autosave) && trim($autosave) !== '') {
            return iss_editorial_sanitize_document($autosave, $format_slug);
        }
    }

    $stored = get_post_meta($post_id, iss_editorial_get_document_meta_key($format_slug), true);
    if (is_string($stored) && trim($stored) !== '') {
        return iss_editorial_sanitize_document($stored, $format_slug);
    }

    return iss_editorial_get_empty_document($format_slug);
}

function iss_editorial_save_document(int $post_id, string $format_slug, $document, bool $autosave = false): bool
{
    if ($post_id <= 0 || !iss_editorial_post_type_supports_format((string) get_post_type($post_id), $format_slug)) {
        return false;
    }

    $document = iss_editorial_sanitize_document($document, $format_slug);
    if (!$document) {
        return false;
    }

    $meta_key = $autosave ? iss_editorial_get_autosave_meta_key($format_slug) : iss_editorial_get_document_meta_key($format_slug);
    update_post_meta($post_id, $meta_key, wp_slash(iss_editorial_encode_document($document)));

    return true;
}

function iss_editorial_document_is_enabled(int $post_id, string $format_slug): bool
{
    return $post_id > 0 && get_post_meta($post_id, iss_editorial_get_enabled_meta_key($format_slug), true) === '1';
}

function iss_editorial_set_document_enabled(int $post_id, string $format_slug, bool $enabled): void
{
    if ($post_id <= 0) {
        return;
    }

    update_post_meta($post_id, iss_editorial_get_enabled_meta_key($format_slug), $enabled ? '1' : '0');
}

function iss_editorial_get_read_model(int $post_id, string $format_slug, bool $prefer_autosave = false): array
{
    $document = iss_editorial_get_document($post_id, $format_slug, $prefer_autosave);
    if (!$document) {
        return [];
    }

    foreach ($document['sections'] as $index => $section) {
        foreach (['object_refs', 'media_refs'] as $field) {
            if (empty($section[$field]) || !is_array($section[$field])) {
                continue;
            }

            $resolved = [];
            foreach ($section[$field] as $reference) {
                $resolved[] = [
                    'reference' => $reference,
                    'resolved' => iss_editorial_resolve_reference($reference),
                ];
            }
            $document['sections'][$index][$field . '_resolved'] = $resolved;
        }
    }

    return $document;
}
