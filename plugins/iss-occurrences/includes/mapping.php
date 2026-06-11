<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_occurrences_normalize_tag($tag): string
{
    $tag = strtoupper(sanitize_text_field((string) $tag));
    $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
    return trim((string) $tag);
}

function iss_occurrences_normalize_series_key($series_key): string
{
    $series_key = strtolower(trim(sanitize_text_field((string) $series_key)));
    if ($series_key === '') {
        return '';
    }

    $series_key = preg_replace('/[^a-z0-9:_-]+/', '', $series_key);
    return trim((string) $series_key);
}

function iss_occurrences_build_series_key($title, $kind = ''): string
{
    $slug = sanitize_title((string) $title);
    $kind = sanitize_key((string) $kind);
    return $kind !== '' ? $kind . ':' . $slug : $slug;
}

function iss_occurrences_get_source_map(): array
{
    $map = get_option(ISS_OCCURRENCES_SOURCE_MAP_OPTION, []);
    if (!is_array($map)) {
        return [];
    }

    $normalized = [];
    foreach ($map as $tag => $entry) {
        $tag = iss_occurrences_normalize_tag($tag);
        if ($tag === '' || !is_array($entry)) {
            continue;
        }

        $normalized[$tag] = [
            'source_post_id' => isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0,
            'source_post_type' => isset($entry['source_post_type']) ? sanitize_key((string) $entry['source_post_type']) : '',
            'fallback_url' => isset($entry['fallback_url']) ? esc_url_raw((string) $entry['fallback_url']) : '',
            'supersaas_title' => isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '',
            'version' => isset($entry['version']) ? (int) $entry['version'] : 1,
            'last_seen_at' => isset($entry['last_seen_at']) ? (string) $entry['last_seen_at'] : '',
        ];
    }

    ksort($normalized);
    return $normalized;
}

function iss_occurrences_get_source_map_entry($tag): ?array
{
    $tag = iss_occurrences_normalize_tag($tag);
    if ($tag === '') {
        return null;
    }

    $map = iss_occurrences_get_source_map();
    return isset($map[$tag]) && is_array($map[$tag]) ? $map[$tag] : null;
}

function iss_occurrences_remember_source_mapping($tag, $fallback_url, $source_post_id, $source_post_type, $supersaas_title = ''): void
{
    $tag = iss_occurrences_normalize_tag($tag);
    if ($tag === '') {
        return;
    }

    $source_post_id = max(0, (int) $source_post_id);
    $source_post_type = sanitize_key((string) $source_post_type);
    $fallback_url = esc_url_raw((string) $fallback_url);
    $supersaas_title = trim((string) $supersaas_title);

    $map = iss_occurrences_get_source_map();
    $prev = isset($map[$tag]) && is_array($map[$tag]) ? $map[$tag] : [];

    if ($source_post_id <= 0 && !empty($prev['source_post_id'])) {
        $source_post_id = (int) $prev['source_post_id'];
    }
    if ($source_post_type === '' && !empty($prev['source_post_type'])) {
        $source_post_type = sanitize_key((string) $prev['source_post_type']);
    }
    if ($fallback_url === '' && !empty($prev['fallback_url'])) {
        $fallback_url = esc_url_raw((string) $prev['fallback_url']);
    }
    if ($supersaas_title === '' && !empty($prev['supersaas_title'])) {
        $supersaas_title = trim((string) $prev['supersaas_title']);
    }
    if ($supersaas_title === '' && $source_post_id > 0) {
        $source_title = trim((string) get_the_title($source_post_id));
        $source_title = preg_replace('/(?:\s|-)*(tour|fuehrung|führung)$/iu', '', $source_title);
        $supersaas_title = trim((string) $source_title);
    }

    $next = [
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'fallback_url' => $fallback_url,
        'supersaas_title' => $supersaas_title,
        'version' => 1,
        'last_seen_at' => current_time('mysql'),
    ];

    if ($prev === $next) {
        return;
    }

    $map[$tag] = $next;
    update_option(ISS_OCCURRENCES_SOURCE_MAP_OPTION, $map, false);
}

function iss_occurrences_get_series_map(): array
{
    $map = get_option(ISS_OCCURRENCES_SERIES_MAP_OPTION, []);
    if (!is_array($map)) {
        return [];
    }

    $normalized = [];
    foreach ($map as $series_key => $entry) {
        $series_key = iss_occurrences_normalize_series_key($series_key);
        if ($series_key === '') {
            continue;
        }

        $entry = is_array($entry) ? $entry : [];
        $normalized[$series_key] = [
            'source_post_id' => isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0,
            'source_post_type' => isset($entry['source_post_type']) ? sanitize_key((string) $entry['source_post_type']) : '',
            'supersaas_title' => isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '',
            'tag' => isset($entry['tag']) ? iss_occurrences_normalize_tag($entry['tag']) : '',
            'fallback_url' => isset($entry['fallback_url']) ? esc_url_raw((string) $entry['fallback_url']) : '',
            'version' => isset($entry['version']) ? (int) $entry['version'] : 1,
            'last_seen_at' => isset($entry['last_seen_at']) ? (string) $entry['last_seen_at'] : '',
        ];
    }

    ksort($normalized);
    return $normalized;
}

function iss_occurrences_get_series_map_entry($series_key): ?array
{
    $series_key = iss_occurrences_normalize_series_key($series_key);
    if ($series_key === '') {
        return null;
    }

    $map = iss_occurrences_get_series_map();
    return isset($map[$series_key]) && is_array($map[$series_key]) ? $map[$series_key] : null;
}

function iss_occurrences_remember_series_mapping($series_key, $source_post_id, $source_post_type, $supersaas_title = '', $tag = '', $fallback_url = ''): bool
{
    $series_key = iss_occurrences_normalize_series_key($series_key);
    if ($series_key === '') {
        return false;
    }

    $source_post_id = max(0, (int) $source_post_id);
    $source_post_type = sanitize_key((string) $source_post_type);
    $supersaas_title = trim((string) $supersaas_title);
    $tag = iss_occurrences_normalize_tag($tag);
    $fallback_url = esc_url_raw((string) $fallback_url);

    $map = iss_occurrences_get_series_map();
    $prev = isset($map[$series_key]) && is_array($map[$series_key]) ? $map[$series_key] : [];

    if ($source_post_id <= 0 && !empty($prev['source_post_id'])) {
        $source_post_id = (int) $prev['source_post_id'];
    }
    if ($source_post_type === '' && !empty($prev['source_post_type'])) {
        $source_post_type = sanitize_key((string) $prev['source_post_type']);
    }
    if ($supersaas_title === '' && !empty($prev['supersaas_title'])) {
        $supersaas_title = trim((string) $prev['supersaas_title']);
    }
    if ($tag === '' && !empty($prev['tag'])) {
        $tag = iss_occurrences_normalize_tag($prev['tag']);
    }
    if ($fallback_url === '' && !empty($prev['fallback_url'])) {
        $fallback_url = esc_url_raw((string) $prev['fallback_url']);
    }
    if ($supersaas_title === '' && $source_post_id > 0) {
        $source_title = trim((string) get_the_title($source_post_id));
        $source_title = preg_replace('/(?:\s|-)*(tour|fuehrung|führung)$/iu', '', $source_title);
        $supersaas_title = trim((string) $source_title);
    }

    $next = [
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'supersaas_title' => $supersaas_title,
        'tag' => $tag,
        'fallback_url' => $fallback_url,
        'version' => 1,
        'last_seen_at' => current_time('mysql'),
    ];

    if ($prev === $next) {
        return false;
    }

    $map[$series_key] = $next;
    update_option(ISS_OCCURRENCES_SERIES_MAP_OPTION, $map, false);
    return true;
}

function iss_occurrences_resolve_tag_for_source_post_id($source_post_id): string
{
    $source_post_id = max(0, (int) $source_post_id);
    if ($source_post_id <= 0) {
        return '';
    }

    foreach (iss_occurrences_get_source_map() as $tag => $entry) {
        if ((int) ($entry['source_post_id'] ?? 0) === $source_post_id) {
            return iss_occurrences_normalize_tag($tag);
        }
    }

    return '';
}

function iss_occurrences_resolve_series_keys_for_source_post_id($source_post_id): array
{
    $source_post_id = max(0, (int) $source_post_id);
    if ($source_post_id <= 0) {
        return [];
    }

    $keys = [];
    foreach (iss_occurrences_get_series_map() as $series_key => $entry) {
        if ((int) ($entry['source_post_id'] ?? 0) === $source_post_id) {
            $keys[] = $series_key;
        }
    }

    $keys = array_values(array_unique(array_filter($keys)));
    sort($keys);
    return $keys;
}

function iss_occurrences_resolve_source_by_series_key($series_key): array
{
    $entry = iss_occurrences_get_series_map_entry($series_key);
    if (!is_array($entry)) {
        return ['source_post_id' => 0, 'source_post_type' => ''];
    }

    $source_post_id = (int) ($entry['source_post_id'] ?? 0);
    $source_post_type = sanitize_key((string) ($entry['source_post_type'] ?? ''));
    if ($source_post_id <= 0 || !(get_post($source_post_id) instanceof WP_Post)) {
        return ['source_post_id' => 0, 'source_post_type' => ''];
    }

    if ($source_post_type === '') {
        $source_post_type = sanitize_key((string) get_post_type($source_post_id));
    }

    return [
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
    ];
}

function iss_occurrences_clear_series_mapping_for_post($source_post_id): int
{
    $source_post_id = max(0, (int) $source_post_id);
    if ($source_post_id <= 0) {
        return 0;
    }

    $map = iss_occurrences_get_series_map();
    $changed = 0;
    foreach ($map as $series_key => $entry) {
        if ((int) ($entry['source_post_id'] ?? 0) !== $source_post_id) {
            continue;
        }

        $entry['source_post_id'] = 0;
        $entry['source_post_type'] = '';
        $entry['last_seen_at'] = current_time('mysql');
        $map[$series_key] = $entry;
        $changed++;
    }

    if ($changed > 0) {
        update_option(ISS_OCCURRENCES_SERIES_MAP_OPTION, $map, false);
    }

    return $changed;
}

function iss_occurrences_clear_series_mapping_for_key($series_key): bool
{
    $series_key = iss_occurrences_normalize_series_key($series_key);
    if ($series_key === '') {
        return false;
    }

    $map = iss_occurrences_get_series_map();
    if (!isset($map[$series_key]) || !is_array($map[$series_key])) {
        return false;
    }

    $entry = $map[$series_key];
    $entry['source_post_id'] = 0;
    $entry['source_post_type'] = '';
    $entry['last_seen_at'] = current_time('mysql');
    $map[$series_key] = $entry;

    return (bool) update_option(ISS_OCCURRENCES_SERIES_MAP_OPTION, $map, false);
}

function iss_occurrences_clear_source_mapping_for_post($source_post_id): int
{
    $source_post_id = max(0, (int) $source_post_id);
    if ($source_post_id <= 0) {
        return 0;
    }

    $map = iss_occurrences_get_source_map();
    $changed = 0;
    foreach ($map as $tag => $entry) {
        if ((int) ($entry['source_post_id'] ?? 0) !== $source_post_id) {
            continue;
        }

        $entry['source_post_id'] = 0;
        $entry['source_post_type'] = '';
        $entry['last_seen_at'] = current_time('mysql');
        $map[$tag] = $entry;
        $changed++;
    }

    if ($changed > 0) {
        update_option(ISS_OCCURRENCES_SOURCE_MAP_OPTION, $map, false);
    }

    return $changed;
}

function iss_occurrences_clear_source_mapping_for_tag($tag): bool
{
    $tag = iss_occurrences_normalize_tag($tag);
    if ($tag === '') {
        return false;
    }

    $map = iss_occurrences_get_source_map();
    if (!isset($map[$tag]) || !is_array($map[$tag])) {
        return false;
    }

    $entry = $map[$tag];
    $entry['source_post_id'] = 0;
    $entry['source_post_type'] = '';
    $entry['last_seen_at'] = current_time('mysql');
    $map[$tag] = $entry;

    return (bool) update_option(ISS_OCCURRENCES_SOURCE_MAP_OPTION, $map, false);
}
