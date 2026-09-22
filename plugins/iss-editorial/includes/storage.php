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

    if (isset($reference['editorial_set_item_id'])) {
        $sanitized['editorial_set_item_id'] = (string) absint($reference['editorial_set_item_id']);
    }
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
    if (isset($reference['mime'])) {
        $sanitized['mime'] = sanitize_mime_type((string) $reference['mime']);
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
    $page_id = absint($link['page_id'] ?? 0);
    $url = esc_url_raw((string) ($link['url'] ?? ''));
    if ($page_id > 0 && get_post_type($page_id) === 'page' && get_post_status($page_id) === 'publish') {
        $url = (string) get_permalink($page_id);
    } else {
        $page_id = 0;
    }

    if ($label === '' || $url === '') {
        return [];
    }

    $sanitized = [
        'label' => $label,
        'url' => $url,
    ];

    if ($page_id > 0) {
        $sanitized['page_id'] = (string) $page_id;
    }

    return $sanitized;
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

function iss_editorial_sanitize_gateway_item($item, string $profile = ''): array
{
    if (!is_array($item)) {
        return [];
    }

    $label = sanitize_text_field((string) ($item['label'] ?? ''));
    $page_id = absint($item['page_id'] ?? 0);
    $url = esc_url_raw((string) ($item['url'] ?? ''));
    if ($page_id > 0 && get_post_type($page_id) === 'page' && get_post_status($page_id) === 'publish') {
        $url = (string) get_permalink($page_id);
    } else {
        $page_id = 0;
    }

    if ($label === '' || $url === '') {
        return [];
    }

    $sanitized = [
        'label' => $label,
        'text' => ($profile !== '' ? iss_editorial_sanitize_rich_text((string) ($item['text'] ?? ''), $profile) : sanitize_textarea_field((string) ($item['text'] ?? ''))),
        'url' => $url,
        'media_refs' => iss_editorial_sanitize_reference_list($item['media_refs'] ?? []),
    ];

    if ($page_id > 0) {
        $sanitized['page_id'] = (string) $page_id;
    }

    return $sanitized;
}

function iss_editorial_sanitize_gateway_item_list($items, string $profile = ''): array
{
    $sanitized = [];
    foreach ((array) $items as $item) {
        $item = iss_editorial_sanitize_gateway_item($item, $profile);
        if ($item) {
            $sanitized[] = $item;
        }
    }

    return $sanitized;
}

function iss_editorial_sanitize_text_image_item($item, string $profile = ''): array
{
    if (!is_array($item)) {
        return [];
    }

    $label = sanitize_text_field((string) ($item['label'] ?? ''));
    $text = ($profile !== '' ? iss_editorial_sanitize_rich_text((string) ($item['text'] ?? ''), $profile) : sanitize_textarea_field((string) ($item['text'] ?? '')));
    $media_refs = iss_editorial_sanitize_reference_list($item['media_refs'] ?? []);
    if ($label === '' && $text === '' && !$media_refs) {
        return [];
    }

    return [
        'label' => $label,
        'text' => $text,
        'media_refs' => array_slice($media_refs, 0, 1),
    ];
}

function iss_editorial_sanitize_text_image_item_list($items, string $profile = ''): array
{
    $sanitized = [];
    foreach ((array) $items as $item) {
        $item = iss_editorial_sanitize_text_image_item($item, $profile);
        if ($item) {
            $sanitized[] = $item;
        }
    }

    return $sanitized;
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

function iss_editorial_sanitize_gallery_layout($layout): string
{
    $layout = sanitize_key((string) $layout);

    return in_array($layout, ['grid', 'sequence', 'wall', 'viewport'], true) ? $layout : 'grid';
}

function iss_editorial_sanitize_quote_treatment($treatment): string
{
    $treatment = sanitize_key((string) $treatment);

    return in_array($treatment, ['pull', 'source'], true) ? $treatment : 'pull';
}

function iss_editorial_sanitize_section_treatment($treatment): string
{
    $treatment = sanitize_key((string) $treatment);

    return in_array($treatment, ['standard', 'aside'], true) ? $treatment : 'standard';
}

function iss_editorial_sanitize_registered_treatment($treatment, array $format, string $type): string
{
    $section = is_array($format['sections'][$type] ?? null) ? $format['sections'][$type] : [];
    return iss_editorial_resolve_treatment((string) $treatment, $section);
}

function iss_editorial_sanitize_document_rail_feature($feature): array
{
    if (!is_array($feature)) {
        return [];
    }

    $sanitized = [];

    if (array_key_exists('enabled', $feature)) {
        $sanitized['enabled'] = !empty($feature['enabled']);
    }

    $placement = sanitize_key((string) ($feature['placement'] ?? ''));
    if (in_array($placement, ['left', 'right', 'top', 'bottom', 'horizontal'], true)) {
        $sanitized['placement'] = $placement;
    }

    $mode = sanitize_key((string) ($feature['mode'] ?? ''));
    if (in_array($mode, ['anchor-nav', 'section-index', 'contextual'], true)) {
        $sanitized['mode'] = $mode;
    }

    $treatment = sanitize_key((string) ($feature['treatment'] ?? ''));
    if (in_array($treatment, ['quiet', 'card', 'line', 'sticky', 'overlay'], true)) {
        $sanitized['treatment'] = $treatment;
    }

    return $sanitized;
}

function iss_editorial_sanitize_document_features($features): array
{
    if (!is_array($features)) {
        return [];
    }

    $sanitized = [];
    $rail = iss_editorial_sanitize_document_rail_feature($features['rail'] ?? []);
    if ($rail) {
        $sanitized['rail'] = $rail;
    }

    return $sanitized;
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
    $profile = iss_editorial_text_profile($format, $type, 'body');
    if ($profile !== '') {
        return iss_editorial_sanitize_rich_text($body, $profile);
    }
    $format_slug = (string) ($format['slug'] ?? '');
    $safe_rich_text_sections = [
        'fuehrung' => ['bildbuehne', 'intro', 'kapitel', 'leitfrage', 'material', 'schluss'],
        'projekt' => ['kapitel', 'fliesstext', 'schluss'],
    ];

    if (!in_array($type, $safe_rich_text_sections[$format_slug] ?? [], true)) {
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
    $section = iss_editorial_normalize_section_alias($section, $format);
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

    if (iss_editorial_format_supports_section_field($format, $type, 'lead')) {
        $profile = iss_editorial_text_profile($format, $type, 'lead');
        $sanitized['lead'] = $profile !== '' ? iss_editorial_sanitize_rich_text((string) ($section['lead'] ?? ''), $profile) : wp_kses_post((string) ($section['lead'] ?? ''));
    }

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

    if (iss_editorial_format_supports_section_field($format, $type, 'items')) {
        $sanitized['items'] = ($format['sections'][$type]['items_kind'] ?? '') === 'text'
            ? array_values(array_filter(array_map('sanitize_text_field', (array) ($section['items'] ?? []))))
            : (in_array($type, ['text_bild_reihe', 'map_img'], true)
            ? iss_editorial_sanitize_text_image_item_list($section['items'] ?? [], iss_editorial_text_profile($format, $type, 'items'))
            : iss_editorial_sanitize_gateway_item_list($section['items'] ?? [], iss_editorial_text_profile($format, $type, 'items')));
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'slot_key')) {
        $slot_key = sanitize_key((string) ($section['slot_key'] ?? ''));
        if ($slot_key !== '') {
            $sanitized['slot_key'] = $slot_key;
        }
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'treatment')) {
        $treatment = iss_editorial_sanitize_registered_treatment($section['treatment'] ?? '', $format, $type);
        if ($treatment !== '') {
            $sanitized['treatment'] = $treatment;
        }
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'year')) {
        $sanitized['year'] = sanitize_text_field((string) ($section['year'] ?? ''));
    }

    foreach (['start_year', 'end_year'] as $year_field) {
        if (!iss_editorial_format_supports_section_field($format, $type, $year_field)) {
            continue;
        }

        $year = absint($section[$year_field] ?? 0);
        $sanitized[$year_field] = $year >= 1500 && $year <= 2100 ? $year : null;
    }

    if (
        array_key_exists('start_year', $sanitized)
        && array_key_exists('end_year', $sanitized)
        && $sanitized['start_year'] !== null
        && $sanitized['end_year'] !== null
        && $sanitized['end_year'] < $sanitized['start_year']
    ) {
        $sanitized['end_year'] = $sanitized['start_year'];
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'era_key')) {
        $era_key = sanitize_title((string) ($section['era_key'] ?? ''));
        $allowed_eras = ['kaiserzeit', 'weimar', 'ns-zeit', 'nachkriegszeit', 'ddr', 'nach-1990'];
        $sanitized['era_key'] = in_array($era_key, $allowed_eras, true) ? $era_key : '';
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'function_key')) {
        $function_key = sanitize_key((string) ($section['function_key'] ?? ''));
        $allowed_functions = ['industrial', 'commercial', 'culture', 'education', 'community', 'residential', 'mixed', 'vacant', 'infrastructure'];
        $sanitized['function_key'] = in_array($function_key, $allowed_functions, true) ? $function_key : '';
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'is_current')) {
        $sanitized['is_current'] = !empty($section['is_current']);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'source_confidence')) {
        $source_confidence = sanitize_key((string) ($section['source_confidence'] ?? 'unknown'));
        $allowed_confidence = ['unknown', 'oral', 'archive', 'publication', 'url'];
        $sanitized['source_confidence'] = in_array($source_confidence, $allowed_confidence, true) ? $source_confidence : 'unknown';
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'source_summary')) {
        $sanitized['source_summary'] = sanitize_textarea_field((string) ($section['source_summary'] ?? ''));
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'source_refs')) {
        $sanitized['source_refs'] = iss_editorial_sanitize_link_list($section['source_refs'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'media_layout')) {
        $media_layout = sanitize_key((string) ($section['media_layout'] ?? 'inline'));
        if (($format['slug'] ?? '') === 'landing' && $type === 'feature') {
            $sanitized['media_layout'] = in_array($media_layout, ['40-60', '50-50', '60-40'], true) ? $media_layout : '50-50';
        } else {
            $sanitized['media_layout'] = in_array($media_layout, ['inline', 'aside-right'], true) ? $media_layout : 'inline';
        }
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'gallery_layout')) {
        $sanitized['gallery_layout'] = iss_editorial_sanitize_gallery_layout($section['gallery_layout'] ?? 'grid');
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'quote_treatment')) {
        $sanitized['quote_treatment'] = iss_editorial_sanitize_quote_treatment($section['quote_treatment'] ?? 'pull');
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'section_treatment')) {
        $sanitized['section_treatment'] = iss_editorial_sanitize_section_treatment($section['section_treatment'] ?? 'standard');
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

    return (array) apply_filters('iss_editorial_sanitized_section', $sanitized, $section, $format);
}

function iss_editorial_normalize_section_alias(array $section, array $format): array
{
    $type = sanitize_key((string) ($section['type'] ?? ''));
    $sections = is_array($format['sections'] ?? null) ? $format['sections'] : [];
    foreach ($sections as $target => $definition) {
        if (isset($definition['legacy_types'][$type])) {
            $section['type'] = $target;
            foreach ($definition['legacy_types'][$type] as $field => $value) {
                if (empty($section[$field])) { $section[$field] = $value; }
            }
            break;
        }
    }
    return $section;
}

function iss_editorial_sanitize_deleted_section(array $section, array $format): array
{
    $sanitized = iss_editorial_sanitize_section($section, $format);
    if (!$sanitized) {
        return [];
    }

    $deleted_at = sanitize_text_field((string) ($section['deleted_at'] ?? ''));
    if ($deleted_at !== '') {
        $sanitized['deleted_at'] = $deleted_at;
    }

    if (isset($section['original_index'])) {
        $sanitized['original_index'] = absint($section['original_index']);
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
    $format['document_version'] = $sanitized['schema_version'];
    $sanitized['skin'] = sanitize_key((string) ($document['skin'] ?? $sanitized['skin']));
    $sanitized['variant'] = sanitize_key((string) ($document['variant'] ?? $sanitized['variant']));
    $sanitized['features'] = iss_editorial_sanitize_document_features($document['features'] ?? []);
    $sanitized['sections'] = [];
    $sanitized['deleted_sections'] = [];

    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $section = iss_editorial_sanitize_section($section, $format);
        if ($section) {
            $sanitized['sections'][] = $section;
        }
    }

    foreach ((array) ($document['deleted_sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $section = iss_editorial_sanitize_deleted_section($section, $format);
        if ($section) {
            $sanitized['deleted_sections'][] = $section;
        }
    }

    return (array) apply_filters('iss_editorial_sanitized_document', $sanitized, $document, $format);
}

/**
 * Validate before normalization can discard an author's content.
 *
 * @return array|WP_Error
 */
function iss_editorial_validate_document($value, string $format_slug)
{
    $format = iss_editorial_get_format($format_slug);
    $document = is_string($value) ? json_decode($value, true) : $value;
    if (!$format || !is_array($document) || (is_string($value) && json_last_error() !== JSON_ERROR_NONE)) {
        return new WP_Error('editorial_invalid_json', __('Der Inhalt konnte nicht gelesen werden. Die gespeicherte Fassung bleibt erhalten.', 'iss-editorial'));
    }
    if (!iss_editorial_supports_version($format, $document['schema_version'] ?? null) || !isset($document['sections']) || !is_array($document['sections']) || !array_is_list($document['sections'])) {
        return new WP_Error('editorial_invalid_schema', __('Dieses Dokumentformat wird nicht unterstützt. Die gespeicherte Fassung bleibt erhalten.', 'iss-editorial'));
    }
    $format['document_version'] = $document['schema_version'];
    $allowed_document_fields = (array) apply_filters('iss_editorial_document_fields', ['schema_version', 'skin', 'variant', 'features', 'sections', 'deleted_sections'], $format);
    foreach ($document as $key => $value) {
        if (!in_array($key, $allowed_document_fields, true) && $value !== '' && $value !== [] && $value !== null) {
            return new WP_Error('editorial_unknown_field', sprintf(__('Ein unbekanntes Dokumentfeld muss geprüft werden: %s.', 'iss-editorial'), $key));
        }
    }
    if (isset($document['skin']) && !in_array($document['skin'], array_column(iss_editorial_get_format_skins($format_slug), 'slug'), true)) {
        return new WP_Error('editorial_invalid_skin', __('Bitte eine verfügbare Darstellung wählen.', 'iss-editorial'));
    }
    foreach (['variant', 'entity_key'] as $key) {
        if (isset($document[$key]) && !is_string($document[$key])) {
            return new WP_Error('editorial_invalid_field', sprintf(__('Bitte das Dokumentfeld „%s“ prüfen.', 'iss-editorial'), $key));
        }
    }
    if (isset($document['features']) && (!is_array($document['features']) || $document['features'] !== iss_editorial_sanitize_document_features($document['features']))) {
        return new WP_Error('editorial_invalid_features', __('Bitte die Dokumentdarstellung prüfen.', 'iss-editorial'));
    }
    foreach (['sections', 'deleted_sections'] as $list_key) {
        $list = $document[$list_key] ?? [];
        if (!is_array($list) || !array_is_list($list)) {
            return new WP_Error('editorial_invalid_sections', __('Die Abschnittsliste konnte nicht gelesen werden.', 'iss-editorial'));
        }
        foreach ($list as $index => $section) {
            if (!is_array($section)) {
                return new WP_Error('editorial_invalid_section', sprintf(__('Abschnitt %d konnte nicht gelesen werden.', 'iss-editorial'), $index + 1));
            }
            if (!isset($section['type']) || !is_string($section['type'])) {
                return new WP_Error('editorial_invalid_section', sprintf(__('Abschnitt %d hat keinen gültigen Typ.', 'iss-editorial'), $index + 1));
            }
            $section = iss_editorial_normalize_section_alias($section, $format);
            $type = (string) ($section['type'] ?? '');
            if (!isset($format['sections'][$type])) {
                return new WP_Error('editorial_unknown_section', sprintf(__('Abschnitt %d hat einen unbekannten Typ (%s). Er wurde nicht entfernt.', 'iss-editorial'), $index + 1, $type));
            }
            $supports = (array) $format['sections'][$type]['supports'];
            $allowed = array_merge(['type', 'kicker', 'title', 'body', 'anchor', 'deleted_at', 'original_index'], $supports);
            if (in_array('quote', $supports, true)) {
                $allowed[] = 'attribution';
            }
            foreach ($section as $key => $value) {
                if (!in_array($key, $allowed, true) && $value !== '' && $value !== [] && $value !== null) {
                    return new WP_Error('editorial_unsupported_field', sprintf(__('Abschnitt %1$d: Das Feld „%2$s“ wird hier nicht unterstützt. Der Inhalt bleibt erhalten.', 'iss-editorial'), $index + 1, $key));
                }
            }
            $collections = ['media_refs', 'object_refs', 'links', 'facts', 'items', 'sheets', 'source_refs', 'dynamic_refs', 'rail_options', 'album_source'];
            foreach (array_diff($allowed, $collections) as $key) {
                if (isset($section[$key]) && !is_scalar($section[$key])) {
                    return new WP_Error('editorial_invalid_field', sprintf(__('Abschnitt %1$d: Bitte „%2$s“ prüfen.', 'iss-editorial'), $index + 1, $key));
                }
            }
            foreach ($collections as $key) {
                if (isset($section[$key]) && !is_array($section[$key])) {
                    return new WP_Error('editorial_invalid_field', sprintf(__('Abschnitt %1$d: Bitte „%2$s“ prüfen.', 'iss-editorial'), $index + 1, $key));
                }
            }
            foreach ((array) $format['sections'][$type]['rich_text'] as $field => $profile) {
                if ($document['schema_version'] < 2) { continue; }
                $values = $field === 'items' ? array_column((array) ($section['items'] ?? []), 'text') : [$section[$field] ?? ''];
                foreach ($values as $text) {
                    if (!is_string($text) || ($document['schema_version'] < 3 && preg_match('/class="[^"]*iss-(ink|mark)-preset-/', $text)) || !iss_editorial_rich_text_is_supported($text, $profile)) {
                        return new WP_Error('editorial_unsupported_markup', sprintf(__('Abschnitt %d: Die Textformatierung muss geprüft werden. Der Entwurf bleibt erhalten.', 'iss-editorial'), $index + 1));
                    }
                }
            }
            $normalized = iss_editorial_sanitize_section($section, $format);
            foreach (['media_refs', 'object_refs', 'links', 'facts', 'items', 'sheets', 'source_refs', 'dynamic_refs'] as $key) {
                if (!isset($section[$key]) || $section[$key] === []) {
                    continue;
                }
                if (!is_array($section[$key]) || !array_is_list($section[$key]) || count($section[$key]) !== count((array) ($normalized[$key] ?? []))) {
                    return new WP_Error('editorial_incomplete_items', sprintf(__('Abschnitt %1$d: Bitte die Einträge unter „%2$s“ vervollständigen oder entfernen.', 'iss-editorial'), $index + 1, $key));
                }
            }
            if (!empty($section['treatment']) && ($normalized['treatment'] ?? '') !== $section['treatment']) {
                return new WP_Error('editorial_invalid_treatment', sprintf(__('Abschnitt %d: Bitte eine verfügbare Darstellung wählen.', 'iss-editorial'), $index + 1));
            }
            foreach (['gallery_layout', 'media_layout', 'quote_treatment', 'section_treatment', 'orientation', 'era_key', 'function_key', 'source_confidence', 'start_year', 'end_year'] as $key) {
                if (isset($section[$key]) && $section[$key] !== '' && (string) $section[$key] !== (string) ($normalized[$key] ?? '')) {
                    return new WP_Error('editorial_invalid_field', sprintf(__('Abschnitt %1$d: Bitte „%2$s“ prüfen.', 'iss-editorial'), $index + 1, $key));
                }
            }
            $slots = (array) ($format['sections'][$type]['slots'] ?? []);
            if ($slots && !isset($slots[$section['slot_key'] ?? ''])) {
                return new WP_Error('editorial_invalid_slot', sprintf(__('Abschnitt %d: Bitte die automatischen Inhalte auswählen.', 'iss-editorial'), $index + 1));
            }
            if ($slots && !empty($section['treatment']) && $section['treatment'] !== $slots[$section['slot_key']]['treatment']) {
                return new WP_Error('editorial_invalid_slot', sprintf(__('Abschnitt %d: Automatischer Inhalt und Darstellung passen nicht zusammen.', 'iss-editorial'), $index + 1));
            }
            if (!empty($section['start_year']) && !empty($section['end_year']) && (int) $section['end_year'] < (int) $section['start_year']) {
                return new WP_Error('editorial_invalid_dates', sprintf(__('Abschnitt %d: Das Ende liegt vor dem Beginn.', 'iss-editorial'), $index + 1));
            }
        }
    }
    return apply_filters('iss_editorial_validated_document', iss_editorial_sanitize_document($document, $format_slug), $document, $format);
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

    if ($prefer_autosave && current_user_can('edit_post', $post_id)) {
        $context = iss_editorial_embedded_preview();
        if ($context && $context['postId'] === $post_id && $context['format'] === $format_slug) { return $context['document']; }
        $draft = iss_editorial_get_draft($post_id, $format_slug);
        if ($draft) {
            return iss_editorial_sanitize_document($draft['document'], $format_slug);
        }
    }

    $stored = get_post_meta($post_id, iss_editorial_get_document_meta_key($format_slug), true);
    if (is_string($stored) && trim($stored) !== '') {
        return iss_editorial_sanitize_document($stored, $format_slug);
    }

    $document = iss_editorial_get_empty_document($format_slug);
    $skin_meta_key = iss_editorial_get_skin_meta_key($format_slug);
    if ($skin_meta_key !== '') {
        $skin = sanitize_key((string) get_post_meta($post_id, $skin_meta_key, true));
        if ($skin !== '') {
            $document['skin'] = $skin;
        }
    }

    return $document;
}

function iss_editorial_save_document(int $post_id, string $format_slug, $document, bool $autosave = false): bool
{
    $format = $post_id > 0 ? iss_editorial_get_format_for_post($post_id) : [];
    if ($post_id <= 0 || !$format || (string) ($format['slug'] ?? '') !== sanitize_key($format_slug)) {
        return false;
    }

    $document = iss_editorial_validate_document($document, $format_slug);
    if (is_wp_error($document)) {
        return false;
    }

    if ($autosave) {
        return !is_wp_error(iss_editorial_save_draft($post_id, $format_slug, $document, iss_editorial_document_is_enabled($post_id, $format_slug)));
    }
    $meta_key = iss_editorial_get_document_meta_key($format_slug);
    $encoded = iss_editorial_encode_document($document);
    update_post_meta($post_id, $meta_key, wp_slash($encoded));
    if (get_metadata_raw('post', $post_id, $meta_key, true) !== $encoded) {
        return false;
    }
    if (!$autosave) {
        $skin_meta_key = iss_editorial_get_skin_meta_key($format_slug);
        if ($skin_meta_key !== '') {
            update_post_meta($post_id, $skin_meta_key, sanitize_key((string) ($document['skin'] ?? '')));
        }
        do_action('iss_editorial_document_saved', $post_id, sanitize_key($format_slug), $document);
    }

    return true;
}

function iss_editorial_document_is_enabled(int $post_id, string $format_slug): bool
{
    if (!empty(iss_editorial_get_format($format_slug)['always_enabled'])) {
        return $post_id > 0;
    }
    if (iss_editorial_should_prefer_preview_autosave($post_id, $format_slug)) {
        $context = iss_editorial_embedded_preview();
        if ($context && $context['postId'] === $post_id && $context['format'] === $format_slug) { return $context['enabled']; }
        $draft = iss_editorial_get_draft($post_id, $format_slug);
        if ($draft) {
            return $draft['enabled'];
        }
    }
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
    $prefer_autosave = $prefer_autosave || iss_editorial_should_prefer_preview_autosave($post_id, $format_slug);
    static $preview_models = [];
    $context = $prefer_autosave ? iss_editorial_embedded_preview() : [];
    $cache_key = $context && $context['postId'] === $post_id && $context['format'] === $format_slug
        ? get_current_user_id() . ':' . $post_id . ':' . $format_slug . ':' . $context['token'] : '';
    if ($cache_key !== '' && isset($preview_models[$cache_key])) { return $preview_models[$cache_key]; }
    $document = iss_editorial_get_document($post_id, $format_slug, $prefer_autosave);
    if (!$document) {
        return [];
    }

    foreach ($document['sections'] as $index => $section) {
        if ($cache_key !== '') { $document['sections'][$index]['_editorial_index'] = $index; }
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

    if ($cache_key !== '') { $preview_models[$cache_key] = $document; }
    return $document;
}
