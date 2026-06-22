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
        'body' => wp_kses_post((string) ($section['body'] ?? '')),
    ];

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
