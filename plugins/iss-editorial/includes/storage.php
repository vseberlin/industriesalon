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
    ];
    if (isset($reference['thumbnail'])) { $sanitized['thumbnail'] = esc_url_raw((string) $reference['thumbnail']); }

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

    $sanitized = [
        'label' => $label,
        'text' => $text,
        'media_refs' => array_slice($media_refs, 0, 1),
    ];
    foreach (['eyebrow', 'group', 'detail'] as $key) {
        if (isset($item[$key]) && is_string($item[$key])) {
            $sanitized[$key] = $key === 'detail' ? sanitize_textarea_field($item[$key]) : sanitize_text_field($item[$key]);
        }
    }
    return $sanitized;
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

    return in_array($treatment, ['standard', 'aside', 'disclosure'], true) ? $treatment : 'standard';
}

function iss_editorial_sanitize_registered_treatment($treatment, array $format, string $type): string
{
    $section = is_array($format['sections'][$type] ?? null) ? $format['sections'][$type] : [];
    $value = iss_editorial_sanitize_treatment_slug((string) $treatment);
    // Retain unknown choices for diagnosis; only the renderer applies a context fallback.
    // Write validation below still rejects unregistered choices without touching stored data.
    return $value !== '' ? (string) ($section['treatment_aliases'][$value] ?? $value) : (string) ($section['default_treatment'] ?? '');
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

function iss_editorial_sanitize_document_features($features, string $format_slug = ''): array
{
    if (!is_array($features)) {
        return [];
    }

    $sanitized = [];
    $rail = iss_editorial_sanitize_document_rail_feature($features['rail'] ?? []);
    if ($rail) {
        $sanitized['rail'] = $rail;
    }

    return (array) apply_filters('iss_editorial_document_features', $sanitized, $features, $format_slug);
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

/** Use WordPress HTML parsing and protocol rules for both legacy and profiled text. */
function iss_editorial_strip_unsafe_body_hrefs(string $body): string
{
    $html = new WP_HTML_Tag_Processor($body);
    while ($html->next_tag('a')) {
        $href = $html->get_attribute('href');
        if ($href === null) { continue; }
        $value = (string) preg_replace('/[\x00-\x20\x7f]/', '', html_entity_decode((string) $href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($value === '' || strcasecmp(wp_kses_bad_protocol($value, ['http', 'https', 'mailto', 'tel']), $value) !== 0) {
            $html->remove_attribute('href');
        }
    }
    return $html->get_updated_html();
}

function iss_editorial_sanitize_body_html(string $body, array $format, string $type, int $version): string
{
    $profile = iss_editorial_text_profile($format, $type, 'body', $version);
    if ($profile !== '') {
        return iss_editorial_sanitize_rich_text($body, $profile);
    }
    if (($format['sections'][$type]['legacy_body_policy'] ?? 'post') !== 'basic') {
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

function iss_editorial_sanitize_section(array $section, array $format, int $version): array
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
        'body' => iss_editorial_sanitize_body_html((string) ($section['body'] ?? ''), $format, $type, $version),
    ];

    if (iss_editorial_format_supports_section_field($format, $type, 'lead')) {
        $profile = iss_editorial_text_profile($format, $type, 'lead', $version);
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
            : (($format['sections'][$type]['items_kind'] ?? '') === 'text_image'
            ? iss_editorial_sanitize_text_image_item_list($section['items'] ?? [], iss_editorial_text_profile($format, $type, 'items', $version))
            : iss_editorial_sanitize_gateway_item_list($section['items'] ?? [], iss_editorial_text_profile($format, $type, 'items', $version)));
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'slot_key')) {
        $slot_key = sanitize_key((string) ($section['slot_key'] ?? ''));
        if ($slot_key !== '') {
            $sanitized['slot_key'] = $slot_key;
        }
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'treatment')
        && (empty($format['sections'][$type]['legacy_treatment_field']) || !empty($section['treatment']))) {
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
        $allowed_eras = array_column($format['sections'][$type]['vocabularies']['era_key']['choices'] ?? [], 'value');
        $sanitized['era_key'] = in_array($era_key, $allowed_eras, true) ? $era_key : '';
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'function_key')) {
        $function_key = sanitize_key((string) ($section['function_key'] ?? ''));
        $allowed_functions = array_column($format['sections'][$type]['vocabularies']['function_key']['choices'] ?? [], 'value');
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

    if (iss_editorial_format_supports_section_field($format, $type, 'contribution_question')) {
        $sanitized['contribution_question'] = mb_substr(sanitize_text_field((string) ($section['contribution_question'] ?? '')), 0, 240);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'source_refs')) {
        $sanitized['source_refs'] = iss_editorial_sanitize_link_list($section['source_refs'] ?? []);
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'media_layout')) {
        $layout = $format['sections'][$type]['media_layout'];
        $media_layout = sanitize_key((string) ($section['media_layout'] ?? $layout['default']));
        $sanitized['media_layout'] = in_array($media_layout, array_column($layout['choices'], 'value'), true) ? $media_layout : $layout['default'];
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'gallery_layout')
        && (($format['sections'][$type]['legacy_treatment_field'] ?? '') !== 'gallery_layout' || isset($section['gallery_layout']))) {
        $sanitized['gallery_layout'] = iss_editorial_sanitize_gallery_layout($section['gallery_layout'] ?? 'grid');
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'quote_treatment')
        && (($format['sections'][$type]['legacy_treatment_field'] ?? '') !== 'quote_treatment' || isset($section['quote_treatment']))) {
        $sanitized['quote_treatment'] = iss_editorial_sanitize_quote_treatment($section['quote_treatment'] ?? 'pull');
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'image_fit') && isset($section['image_fit'])) {
        $sanitized['image_fit'] = ($section['image_fit'] === 'contain') ? 'contain' : 'cover';
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'surface') && isset($section['surface'])) {
        $surface = sanitize_key((string) $section['surface']);
        $sanitized['surface'] = in_array($surface, ['default', 'soft', 'dark', 'accent'], true) ? $surface : 'default';
    }

    if (iss_editorial_format_supports_section_field($format, $type, 'section_treatment')
        && (($format['sections'][$type]['legacy_treatment_field'] ?? '') !== 'section_treatment' || isset($section['section_treatment']))) {
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

function iss_editorial_sanitize_document($document, string $format_slug): array
{
    $format = iss_editorial_get_format($format_slug);
    if (!$format) {
        return [];
    }

    $document = iss_editorial_strip_document_preview_cache(iss_editorial_decode_document($document));
    $sanitized = iss_editorial_get_empty_document($format_slug);
    $schema_version = absint($document['schema_version'] ?? 1);
    $sanitized['schema_version'] = max(1, $schema_version);
    $sanitized['skin'] = sanitize_key((string) ($document['skin'] ?? $sanitized['skin']));
    $sanitized['variant'] = sanitize_key((string) ($document['variant'] ?? $sanitized['variant']));
    $sanitized['features'] = iss_editorial_sanitize_document_features($document['features'] ?? [], $format_slug);
    $sanitized['sections'] = [];
    $sanitized['deleted_sections'] = [];

    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $section = iss_editorial_sanitize_section($section, $format, $sanitized['schema_version']);
        if ($section) {
            $sanitized['sections'][] = $section;
        }
    }

    foreach ((array) ($document['deleted_sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        // Opaque recovery data: never normalize retired registry fields or render this as content.
        $sanitized['deleted_sections'][] = $section;
    }

    return (array) apply_filters('iss_editorial_sanitized_document', $sanitized, $document, $format);
}

/** First changed input path; defaults may be added, but submitted content must survive. */
function iss_editorial_normalization_loss($input, $output, string $path = ''): string
{
    if ($input === $output || $input === '' || $input === null || $input === []) { return ''; }
    if (is_array($input) && is_array($output)) {
        // Existing album aliases and ordering are representation changes, not discarded content.
        $aliases = str_ends_with($path, '.album_source') ? ['id' => 'set_id', 'title' => 'set_title'] : [];
        if (preg_match('/\.sheets\[\d+\]$/D', $path)) {
            $aliases = ['object_id' => 'source_id', 'attachment_id' => 'source_id'];
        }
        foreach ($aliases as $old => $new) {
            if (array_key_exists($old, $input) && (!array_key_exists($new, $input) || $input[$old] === $input[$new])) {
                $input[$new] = $input[$old];
                unset($input[$old]);
            }
        }
        if (str_ends_with($path, '.sheets') && array_is_list($input)) {
            foreach ($input as $index => &$sheet) {
                if (is_array($sheet) && in_array($sheet['position'] ?? null, [null, '', 0, '0'], true)) { $sheet['position'] = $index + 1; }
            }
            unset($sheet);
            usort($input, static fn($a, $b): int => (int) ($a['position'] ?? 0) <=> (int) ($b['position'] ?? 0));
        }
        if (array_is_list($input) && (!array_is_list($output) || count($input) !== count($output))) { return $path; }
        foreach ($input as $key => $value) {
            $child_path = is_int($key) ? $path . '[' . $key . ']' : ltrim($path . '.' . $key, '.');
            // Restore bookkeeping is intentionally removed when a section becomes active again.
            if (preg_match('/^sections\[\d+\]\.(deleted_at|original_index|restored_from)$/D', $child_path)) { continue; }
            // Native page selections own the URL; their current permalink replaces the cached URL.
            if ($key === 'url' && preg_match('/^sections\[\d+\]\.(links|source_refs|items)\[\d+\]$/D', $path)
                && !empty($input['page_id']) && isset($output['page_id'])
                && (string) $input['page_id'] === (string) $output['page_id']
                && ($output['url'] ?? '') === get_permalink((int) $output['page_id'])) { continue; }
            if ($key === 'page_id' && in_array($value, [0, '0'], true) && !isset($output[$key])) { continue; }
            $loss = iss_editorial_normalization_loss($value, $output[$key] ?? null, $child_path);
            if ($loss !== '') { return $loss; }
        }
        return '';
    }
    // JSON integer/string and boolean 0/1 representations carry the same value.
    if ((is_int($input) && is_string($output) || is_string($input) && is_int($output))
        && (string) $input === (string) $output) { return ''; }
    if (is_bool($output) && in_array($input, [0, 1, '0', '1'], true) && (bool) $input === $output) { return ''; }
    if (is_string($input) && is_string($output)) {
        // Horizontal spacing and CRLF are cosmetic; embedded line breaks and tags are content.
        $spacing = static fn(string $value): string => trim((string) preg_replace('/[ \t]+/', ' ', str_replace("\r\n", "\n", $value)));
        if ($spacing($input) === $spacing($output)) { return ''; }
        if (preg_match('/\.(body|lead|quote|text)$/D', $path)) {
            $void_tags = static fn(string $value): string => (string) preg_replace('/<(br|hr|img)\b([^<>]*?)\s*\/?>/i', '<$1$2>', $value);
            if ($spacing($void_tags($input)) === $spacing($void_tags($output))) { return ''; }
        }
    }
    return $path;
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
    $document = iss_editorial_strip_document_preview_cache($document);
    $comparison = $document;
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
    if (isset($document['features']) && (!is_array($document['features']) || $document['features'] !== iss_editorial_sanitize_document_features($document['features'], $format_slug))) {
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
            if ($list_key === 'deleted_sections') { continue; }
            $section = iss_editorial_normalize_section_alias($section, $format);
            $comparison['sections'][$index] = $section;
            $type = (string) ($section['type'] ?? '');
            if (!isset($format['sections'][$type])) {
                return new WP_Error('editorial_unknown_section', sprintf(__('Abschnitt %d hat einen unbekannten Typ (%s). Er wurde nicht entfernt.', 'iss-editorial'), $index + 1, $type));
            }
            $supports = (array) $format['sections'][$type]['supports'];
            $allowed = array_merge(['type', 'kicker', 'title', 'body', 'anchor', 'deleted_at', 'original_index', 'restored_from'], $supports);
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
            if (($format['sections'][$type]['items_kind'] ?? '') === 'text_image') {
                foreach ((array) ($section['items'] ?? []) as $item) {
                    foreach (['eyebrow', 'group', 'detail'] as $key) {
                        if (isset($item[$key]) && (!is_string($item[$key]) || wp_strip_all_tags($item[$key]) !== $item[$key])) {
                            return new WP_Error('editorial_invalid_field', __('Vorspann, Filtergruppe und Details benötigen einfachen Text.', 'iss-editorial'));
                        }
                    }
                }
            }
            $normalized = iss_editorial_sanitize_section($section, $format, $document['schema_version']);
            foreach (['media_refs', 'object_refs', 'links', 'facts', 'items', 'sheets', 'source_refs', 'dynamic_refs'] as $key) {
                if (!isset($section[$key]) || $section[$key] === []) {
                    continue;
                }
                if (!is_array($section[$key]) || !array_is_list($section[$key]) || count($section[$key]) !== count((array) ($normalized[$key] ?? []))) {
                    return new WP_Error('editorial_incomplete_items', sprintf(__('Abschnitt %1$d: Bitte die Einträge unter „%2$s“ vervollständigen oder entfernen.', 'iss-editorial'), $index + 1, $key));
                }
            }
            if (!empty($section['treatment']) && (($normalized['treatment'] ?? '') !== $section['treatment']
                || !in_array($normalized['treatment'], array_column($format['sections'][$type]['treatments'], 'slug'), true))) {
                return new WP_Error('editorial_invalid_treatment', sprintf(__('Abschnitt %d: Bitte eine verfügbare Darstellung wählen.', 'iss-editorial'), $index + 1));
            }
            foreach (['image_fit', 'surface', 'gallery_layout', 'media_layout', 'quote_treatment', 'section_treatment', 'orientation', 'era_key', 'function_key', 'source_confidence', 'start_year', 'end_year'] as $key) {
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
    $normalized = apply_filters('iss_editorial_validated_document', iss_editorial_sanitize_document($document, $format_slug), $document, $format);
    if (is_wp_error($normalized)) { return $normalized; }
    // Domain owners may declare exact legacy aliases without teaching the engine their slugs.
    $comparison = apply_filters('iss_editorial_normalization_input', $comparison, $format);
    $path = iss_editorial_normalization_loss($comparison, $normalized);
    if ($path !== '') {
        return new WP_Error('editorial_lossy_normalization', sprintf(__('Das Feld „%s“ würde beim Speichern verändert oder gekürzt. Bitte den Inhalt prüfen. Der Entwurf bleibt erhalten.', 'iss-editorial'), $path), ['path' => $path]);
    }
    return $normalized;
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

/** Let a data owner wrap the native metadata write and its required projections. */
function iss_editorial_persist_document(int $post_id, string $format_slug, callable $write): bool
{
    $write = apply_filters('iss_editorial_document_writer', $write, $post_id, $format_slug);
    if (!is_callable($write) || $write() !== true) {
        return false;
    }
    do_action('iss_editorial_document_saved', $post_id, $format_slug, iss_editorial_get_document($post_id, $format_slug));
    return true;
}

/** Called inside the owning writer boundary, including Place transactions. */
function iss_editorial_write_document_meta(int $post_id, array $values): bool
{
    $changed = false;
    foreach ($values as $key => $value) {
        $changed = $changed || get_metadata_raw('post', $post_id, $key, true) !== $value;
    }
    if (!$changed) {
        return true;
    }
    // During save_post, the form has already captured the old title AND old JSON.
    $captured_before_update = doing_action('save_post') && !empty($GLOBALS['iss_editorial_revision_before_update'][$post_id]);
    if (!$captured_before_update && !iss_editorial_capture_revision($post_id)) {
        return false;
    }
    foreach ($values as $key => $value) {
        update_post_meta($post_id, $key, wp_slash($value));
        if (get_metadata_raw('post', $post_id, $key, true) !== $value) {
            return false;
        }
    }
    return true;
}

/** Editors can restore trash, while permanent removal requires the same capability as the UI. */
function iss_editorial_check_trash_removal(int $post_id, string $format_slug, array $document)
{
    if (current_user_can('manage_options')) { return true; }
    $stored = iss_editorial_decode_document(get_metadata_raw('post', $post_id, iss_editorial_get_document_meta_key($format_slug), true));
    $draft = iss_editorial_get_draft($post_id, $format_slug);
    foreach ([$stored, $draft['document'] ?? []] as $before) {
        $remaining = (array) ($document['deleted_sections'] ?? []);
        $active = (array) ($document['sections'] ?? []);
        foreach ((array) ($before['deleted_sections'] ?? []) as $deleted) {
            $retained = array_search($deleted, $remaining, true);
            if ($retained !== false) { unset($remaining[$retained]); continue; }
            $restored = iss_editorial_validate_document(['schema_version' => $document['schema_version'] ?? 1, 'sections' => [$deleted]], $format_slug);
            if (!is_wp_error($restored)) {
                foreach ($active as $index => $section) {
                    // The restore endpoint identifies the source before controls can edit it.
                    // Retain that identity in recovery; canonical normalization removes it.
                    if (is_array($section) && hash_equals(hash('sha256', iss_editorial_encode_document($deleted)), (string) ($section['restored_from'] ?? ''))
                        && ($section['type'] ?? '') === $restored['sections'][0]['type']) {
                        unset($active[$index]);
                        continue 2;
                    }
                    $candidate = iss_editorial_validate_document(['schema_version' => $document['schema_version'] ?? 1, 'sections' => [$section]], $format_slug);
                    if (!is_wp_error($candidate) && $candidate['sections'] === $restored['sections']) {
                        unset($active[$index]);
                        continue 2;
                    }
                }
            }
            return new WP_Error('editorial_trash_permission', __('Gelöschte Abschnitte dürfen nur von Administrator:innen endgültig entfernt werden. Bitte wiederherstellen oder im Papierkorb behalten.', 'iss-editorial'));
        }
    }
    return true;
}

function iss_editorial_save_document(int $post_id, string $format_slug, $document, bool $autosave = false, ?bool $enabled = null): bool
{
    $format = $post_id > 0 ? iss_editorial_get_format_for_post($post_id) : [];
    if ($post_id <= 0 || !$format || (string) ($format['slug'] ?? '') !== sanitize_key($format_slug)) {
        return false;
    }

    $raw_document = iss_editorial_decode_document($document);
    $document = iss_editorial_validate_document($document, $format_slug);
    if (is_wp_error($document) || is_wp_error(iss_editorial_check_trash_removal($post_id, $format_slug, $raw_document))) {
        return false;
    }

    if ($autosave) {
        return !is_wp_error(iss_editorial_save_draft($post_id, $format_slug, $document, iss_editorial_document_is_enabled($post_id, $format_slug)));
    }
    return iss_editorial_persist_document($post_id, sanitize_key($format_slug), static function () use ($post_id, $format_slug, $format, $document, $enabled): bool {
        $values = [iss_editorial_get_document_meta_key($format_slug) => iss_editorial_encode_document($document)];
        $skin_meta_key = iss_editorial_get_skin_meta_key($format_slug);
        if ($skin_meta_key !== '') {
            $values[$skin_meta_key] = sanitize_key((string) ($document['skin'] ?? ''));
        }
        if ($enabled !== null && empty($format['always_enabled'])) {
            $values[iss_editorial_get_enabled_meta_key($format_slug)] = $enabled ? '1' : '0';
        }
        return iss_editorial_write_document_meta($post_id, $values);
    });
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

function iss_editorial_set_document_enabled(int $post_id, string $format_slug, bool $enabled): bool
{
    $format = $post_id > 0 ? iss_editorial_get_format_for_post($post_id) : [];
    if (!$format || $format['slug'] !== sanitize_key($format_slug)) {
        return false;
    }
    if (!empty($format['always_enabled'])) {
        return $enabled;
    }

    return iss_editorial_persist_document($post_id, $format['slug'], static function () use ($post_id, $format, $enabled): bool {
        return iss_editorial_write_document_meta($post_id, [iss_editorial_get_enabled_meta_key($format['slug']) => $enabled ? '1' : '0']);
    });
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

    iss_editorial_prime_document_references($document);
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
