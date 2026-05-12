<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_normalize_contract_text($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $text = wp_strip_all_tags((string) $value);
    $text = preg_replace('/\s+/', ' ', $text);

    return is_string($text) ? trim($text) : '';
}

function iss_register_compact_contract_text(string $text, int $limit): string
{
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) > $limit) {
            return rtrim(mb_substr($text, 0, max(1, $limit - 1))) . '…';
        }

        return $text;
    }

    if (strlen($text) > $limit) {
        return rtrim(substr($text, 0, max(1, $limit - 1))) . '…';
    }

    return $text;
}

function iss_register_build_place_summary_text(array $place, int $limit = 280): string
{
    foreach (['current', 'history', 'vornutzung'] as $key) {
        $text = iss_register_normalize_contract_text($place[$key] ?? '');
        if ($text === '') {
            continue;
        }

        return iss_register_compact_contract_text($text, $limit);
    }

    return '';
}

function iss_register_build_place_source_labels(array $place, int $limit = 6): array
{
    $labels = [];
    $seen = [];
    $source_links = isset($place['source_links']) && is_array($place['source_links'])
        ? $place['source_links']
        : [];

    foreach ($source_links as $source_link) {
        $source_link = is_scalar($source_link) ? trim((string) $source_link) : '';
        if ($source_link === '') {
            continue;
        }

        $host = wp_parse_url($source_link, PHP_URL_HOST);
        $label = is_string($host) && $host !== ''
            ? preg_replace('/^www\./i', '', $host)
            : iss_register_normalize_contract_text($source_link);

        if (!is_string($label) || $label === '') {
            continue;
        }

        $key = strtolower($label);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $labels[] = iss_register_compact_contract_text($label, 72);

        if (count($labels) >= $limit) {
            return $labels;
        }
    }

    $source_summary = iss_register_normalize_contract_text($place['sources'] ?? '');
    if ($source_summary === '') {
        return $labels;
    }

    $parts = preg_split('/[|;\n]+/', $source_summary) ?: [];
    foreach ($parts as $part) {
        $label = iss_register_normalize_contract_text($part);
        if ($label === '') {
            continue;
        }

        $label = iss_register_compact_contract_text($label, 72);
        $key = strtolower($label);
        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $labels[] = $label;

        if (count($labels) >= $limit) {
            break;
        }
    }

    return $labels;
}

function iss_register_build_place_current_status_contract(array $place): array
{
    if (function_exists('iss_register_get_normalized_current_status_payload')) {
        $normalized = iss_register_get_normalized_current_status_payload($place);

        return [
            'current_status' => (string) ($normalized['key'] ?? ''),
            'current_status_label' => (string) ($normalized['label'] ?? ''),
            'current_status_source' => (string) ($normalized['source'] ?? ''),
        ];
    }

    return [
        'current_status' => isset($place['normalized_current_status']) && is_scalar($place['normalized_current_status'])
            ? (string) $place['normalized_current_status']
            : (isset($place['current_status']) && is_scalar($place['current_status']) ? (string) $place['current_status'] : ''),
        'current_status_label' => isset($place['normalized_current_status_label']) && is_scalar($place['normalized_current_status_label'])
            ? (string) $place['normalized_current_status_label']
            : '',
        'current_status_source' => isset($place['normalized_current_status_source']) && is_scalar($place['normalized_current_status_source'])
            ? (string) $place['normalized_current_status_source']
            : '',
    ];
}

function iss_register_map_contract_image_group($images): array
{
    if (!is_array($images) || !$images) {
        return [];
    }

    $first = reset($images);
    if (!is_array($first)) {
        return [];
    }

    $url = isset($first['url']) ? esc_url_raw((string) $first['url']) : '';
    if ($url === '') {
        return [];
    }

    return [[
        'url' => $url,
        'caption' => isset($first['caption']) ? sanitize_text_field((string) $first['caption']) : '',
        'source' => isset($first['source']) ? sanitize_text_field((string) $first['source']) : '',
        'year' => isset($first['year']) ? sanitize_text_field((string) $first['year']) : '',
    ]];
}

function iss_register_get_summary_contract_field_names(): array
{
    return [
        'id',
        'post_id',
        'name',
        'owner',
        'operator',
        'developer',
        'tenant',
        'role',
        'area',
        'address',
        'status',
        'current_status',
        'current_use_type',
        'branche',
        'summary',
        'source_labels',
        'available_era_slugs',
        'available_function_keys',
        'primary_historical_era_slug',
        'has_epochs',
        'is_unclear',
        'archive_images',
        'current_images',
        'document_images',
        'lat',
        'lng',
    ];
}

function iss_register_get_summary_contract_field_lookup(): array
{
    return array_fill_keys(iss_register_get_summary_contract_field_names(), true);
}

function iss_register_parse_summary_contract_fields_request($value): array
{
    if (is_array($value)) {
        $value = implode(',', array_map('strval', $value));
    }

    if (!is_scalar($value)) {
        return [
            'requested' => false,
            'fields' => [],
            'invalid' => [],
        ];
    }

    $raw_value = trim((string) $value);
    if ($raw_value === '') {
        return [
            'requested' => false,
            'fields' => [],
            'invalid' => [],
        ];
    }

    $allowed = iss_register_get_summary_contract_field_lookup();
    $fields = [];
    $invalid = [];

    foreach (explode(',', $raw_value) as $field) {
        $field = sanitize_key($field);
        if ($field === '') {
            continue;
        }

        if (!isset($allowed[$field])) {
            $invalid[$field] = true;
            continue;
        }

        $fields[$field] = true;
    }

    return [
        'requested' => true,
        'fields' => array_keys($fields),
        'invalid' => array_keys($invalid),
    ];
}

function iss_register_parse_summary_contract_fields($value): array
{
    $parsed = iss_register_parse_summary_contract_fields_request($value);

    return $parsed['fields'];
}

function iss_register_select_contract_fields(array $contract, array $fields): array
{
    if (!$fields) {
        return $contract;
    }

    $selected = [];

    foreach ($fields as $field) {
        if (array_key_exists($field, $contract)) {
            $selected[$field] = $contract[$field];
        }
    }

    return $selected;
}

function iss_register_build_place_summary_contract(array $place, array $fields = []): array
{
    $current_status = iss_register_build_place_current_status_contract($place);

    $contract = [
        'id' => isset($place['id']) ? (string) $place['id'] : '',
        'post_id' => isset($place['post_id']) ? (int) $place['post_id'] : 0,
        'name' => isset($place['name']) ? (string) $place['name'] : '',
        'owner' => isset($place['owner']) ? (string) $place['owner'] : '',
        'operator' => isset($place['operator']) ? (string) $place['operator'] : '',
        'developer' => isset($place['developer']) ? (string) $place['developer'] : '',
        'tenant' => isset($place['tenant']) ? (string) $place['tenant'] : '',
        'role' => isset($place['role']) ? (string) $place['role'] : '',
        'area' => isset($place['area']) ? (string) $place['area'] : '',
        'address' => isset($place['address']) ? (string) $place['address'] : '',
        'status' => isset($place['status']) ? (string) $place['status'] : '',
        'current_status' => $current_status['current_status'],
        'current_use_type' => isset($place['current_use_type']) ? (string) $place['current_use_type'] : '',
        'branche' => isset($place['branche']) ? (string) $place['branche'] : '',
        'summary' => iss_register_build_place_summary_text($place),
        'source_labels' => iss_register_build_place_source_labels($place),
        'available_era_slugs' => isset($place['available_era_slugs']) && is_array($place['available_era_slugs']) ? array_values($place['available_era_slugs']) : [],
        'available_function_keys' => isset($place['available_function_keys']) && is_array($place['available_function_keys']) ? array_values($place['available_function_keys']) : [],
        'primary_historical_era_slug' => isset($place['primary_historical_era_slug']) ? (string) $place['primary_historical_era_slug'] : '',
        'has_epochs' => !empty($place['has_epochs']),
        'is_unclear' => !empty($place['is_unclear']),
        'archive_images' => iss_register_map_contract_image_group($place['archive_images'] ?? []),
        'current_images' => iss_register_map_contract_image_group($place['current_images'] ?? []),
        'document_images' => iss_register_map_contract_image_group($place['document_images'] ?? []),
        'lat' => isset($place['lat']) ? (string) $place['lat'] : '',
        'lng' => isset($place['lng']) ? (string) $place['lng'] : '',
    ];

    return iss_register_select_contract_fields($contract, $fields);
}

function iss_register_get_detail_contract_field_names(): array
{
    return [
        'id',
        'post_id',
        'slug',
        'name',
        'excerpt',
        'permalink',
        'featured_image_url',
        'owner',
        'operator',
        'developer',
        'tenant',
        'role',
        'area',
        'address',
        'status',
        'current_status',
        'current_status_label',
        'current_status_source',
        'current_use_type',
        'size',
        'investment',
        'jobs',
        'branche',
        'website',
        'questions',
        'is_unclear',
        'archive_images',
        'current_images',
        'document_images',
        'lat',
        'lng',
        'vornutzung',
        'history',
        'current',
        'sources',
        'source_links',
        'available_era_slugs',
        'available_function_keys',
        'primary_historical_era_slug',
        'has_epochs',
        'historical_phase_labels',
        'epochs',
        'related_objects',
    ];
}

function iss_register_build_place_detail_contract_data(array $place): array
{
    $current_status = iss_register_build_place_current_status_contract($place);

    return [
        'id' => isset($place['id']) ? (string) $place['id'] : '',
        'post_id' => isset($place['post_id']) ? (int) $place['post_id'] : 0,
        'slug' => isset($place['slug']) ? (string) $place['slug'] : '',
        'name' => isset($place['name']) ? (string) $place['name'] : '',
        'excerpt' => isset($place['excerpt']) ? (string) $place['excerpt'] : '',
        'permalink' => isset($place['permalink']) ? (string) $place['permalink'] : '',
        'featured_image_url' => isset($place['featured_image_url']) ? (string) $place['featured_image_url'] : '',
        'owner' => isset($place['owner']) ? (string) $place['owner'] : '',
        'operator' => isset($place['operator']) ? (string) $place['operator'] : '',
        'developer' => isset($place['developer']) ? (string) $place['developer'] : '',
        'tenant' => isset($place['tenant']) ? (string) $place['tenant'] : '',
        'role' => isset($place['role']) ? (string) $place['role'] : '',
        'area' => isset($place['area']) ? (string) $place['area'] : '',
        'address' => isset($place['address']) ? (string) $place['address'] : '',
        'status' => isset($place['status']) ? (string) $place['status'] : '',
        'current_status' => $current_status['current_status'],
        'current_status_label' => $current_status['current_status_label'],
        'current_status_source' => $current_status['current_status_source'],
        'current_use_type' => isset($place['current_use_type']) ? (string) $place['current_use_type'] : '',
        'size' => isset($place['size']) ? (string) $place['size'] : '',
        'investment' => isset($place['investment']) ? (string) $place['investment'] : '',
        'jobs' => isset($place['jobs']) ? (string) $place['jobs'] : '',
        'branche' => isset($place['branche']) ? (string) $place['branche'] : '',
        'website' => isset($place['website']) ? (string) $place['website'] : '',
        'questions' => isset($place['questions']) && is_array($place['questions']) ? array_values($place['questions']) : [],
        'is_unclear' => !empty($place['is_unclear']),
        'archive_images' => isset($place['archive_images']) && is_array($place['archive_images']) ? array_values($place['archive_images']) : [],
        'current_images' => isset($place['current_images']) && is_array($place['current_images']) ? array_values($place['current_images']) : [],
        'document_images' => isset($place['document_images']) && is_array($place['document_images']) ? array_values($place['document_images']) : [],
        'lat' => isset($place['lat']) ? (string) $place['lat'] : '',
        'lng' => isset($place['lng']) ? (string) $place['lng'] : '',
        'vornutzung' => isset($place['vornutzung']) ? (string) $place['vornutzung'] : '',
        'history' => isset($place['history']) ? (string) $place['history'] : '',
        'current' => isset($place['current']) ? (string) $place['current'] : '',
        'sources' => isset($place['sources']) ? (string) $place['sources'] : '',
        'source_links' => isset($place['source_links']) && is_array($place['source_links']) ? array_values($place['source_links']) : [],
        'available_era_slugs' => isset($place['available_era_slugs']) && is_array($place['available_era_slugs']) ? array_values($place['available_era_slugs']) : [],
        'available_function_keys' => isset($place['available_function_keys']) && is_array($place['available_function_keys']) ? array_values($place['available_function_keys']) : [],
        'primary_historical_era_slug' => isset($place['primary_historical_era_slug']) ? (string) $place['primary_historical_era_slug'] : '',
        'has_epochs' => !empty($place['has_epochs']),
        'historical_phase_labels' => isset($place['historical_phase_labels']) && is_array($place['historical_phase_labels']) ? array_values($place['historical_phase_labels']) : [],
        'epochs' => isset($place['epochs']) && is_array($place['epochs']) ? array_values($place['epochs']) : [],
        'related_objects' => isset($place['related_objects']) && is_array($place['related_objects']) ? array_values($place['related_objects']) : [],
    ];
}

function iss_register_build_summary_place_contracts(array $places, array $fields = []): array
{
    return array_values(array_map(static function (array $place) use ($fields): array {
        return iss_register_build_place_summary_contract($place, $fields);
    }, $places));
}
