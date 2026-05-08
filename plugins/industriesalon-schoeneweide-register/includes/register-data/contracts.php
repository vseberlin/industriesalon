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
        'branche',
        'summary',
        'source_labels',
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
        'branche' => isset($place['branche']) ? (string) $place['branche'] : '',
        'summary' => iss_register_build_place_summary_text($place),
        'source_labels' => iss_register_build_place_source_labels($place),
        'is_unclear' => !empty($place['is_unclear']),
        'archive_images' => iss_register_map_contract_image_group($place['archive_images'] ?? []),
        'current_images' => iss_register_map_contract_image_group($place['current_images'] ?? []),
        'document_images' => iss_register_map_contract_image_group($place['document_images'] ?? []),
        'lat' => isset($place['lat']) ? (string) $place['lat'] : '',
        'lng' => isset($place['lng']) ? (string) $place['lng'] : '',
    ];

    return iss_register_select_contract_fields($contract, $fields);
}

function iss_register_build_summary_place_contracts(array $places, array $fields = []): array
{
    return array_values(array_map(static function (array $place) use ($fields): array {
        return iss_register_build_place_summary_contract($place, $fields);
    }, $places));
}
