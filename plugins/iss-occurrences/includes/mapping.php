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

function iss_occurrences_get_tag_sources(): array
{
    $sources = [];
    foreach (iss_occurrences_get_series_sources() as $series_key => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $tag = isset($entry['tag']) ? iss_occurrences_normalize_tag($entry['tag']) : '';
        if ($tag === '') {
            continue;
        }

        $entry['series_key'] = (string) $series_key;
        $current_source_id = isset($sources[$tag]['source_post_id']) ? (int) $sources[$tag]['source_post_id'] : 0;
        $next_source_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;
        if (!isset($sources[$tag]) || ($current_source_id <= 0 && $next_source_id > 0)) {
            $sources[$tag] = $entry;
        }
    }

    ksort($sources);
    return $sources;
}

function iss_occurrences_get_tag_source($tag): ?array
{
    $tag = iss_occurrences_normalize_tag($tag);
    if ($tag === '') {
        return null;
    }

    $sources = iss_occurrences_get_tag_sources();
    return isset($sources[$tag]) && is_array($sources[$tag]) ? $sources[$tag] : null;
}

function iss_occurrences_remember_tag_source($tag, $fallback_url, $source_post_id, $source_post_type, $supersaas_title = ''): bool
{
    $tag = iss_occurrences_normalize_tag($tag);
    if ($tag === '') {
        return false;
    }

    $source_post_id = max(0, (int) $source_post_id);
    $source_post_type = sanitize_key((string) $source_post_type);
    $fallback_url = esc_url_raw((string) $fallback_url);
    $supersaas_title = trim((string) $supersaas_title);

    $prev = iss_occurrences_get_tag_source($tag);
    $prev = is_array($prev) ? $prev : [];

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
    if ($supersaas_title === '') {
        $supersaas_title = $tag;
    }

    $series_key = isset($prev['series_key']) ? iss_occurrences_normalize_series_key((string) $prev['series_key']) : '';
    if ($series_key === '') {
        $series_key = iss_occurrences_build_series_key($supersaas_title, 'tour');
    }

    if (!function_exists('iss_occurrences_get_service')) {
        return false;
    }

    $series_id = iss_occurrences_get_service()->upsert_series([
        'series_key' => $series_key,
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'fallback_url' => $fallback_url,
        'supersaas_title' => $supersaas_title,
        'tag' => $tag,
        'origin' => 'supersaas',
    ]);

    return $series_id > 0;
}

function iss_occurrences_get_series_sources(): array
{
    $sources = [];

    if (function_exists('iss_occurrences_get_service')) {
        $service = iss_occurrences_get_service();
        if (method_exists($service, 'get_series_rows')) {
            foreach ($service->get_series_rows() as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $series_key = isset($row['series_key']) ? iss_occurrences_normalize_series_key((string) $row['series_key']) : '';
                if ($series_key === '') {
                    continue;
                }

                $sources[$series_key] = [
                    'series_key' => $series_key,
                    'source_post_id' => isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0,
                    'source_post_type' => isset($row['source_post_type']) ? sanitize_key((string) $row['source_post_type']) : '',
                    'supersaas_title' => isset($row['supersaas_title']) ? trim((string) $row['supersaas_title']) : '',
                    'tag' => isset($row['tag']) ? iss_occurrences_normalize_tag($row['tag']) : '',
                    'fallback_url' => isset($row['fallback_url']) ? esc_url_raw((string) $row['fallback_url']) : '',
                    'review_state' => isset($row['review_state']) ? sanitize_key((string) $row['review_state']) : '',
                    'version' => 1,
                    'last_seen_at' => isset($row['updated_at']) ? (string) $row['updated_at'] : '',
                ];
            }
        }
    }

    ksort($sources);
    return $sources;
}

function iss_occurrences_get_series_source($series_key): ?array
{
    $series_key = iss_occurrences_normalize_series_key($series_key);
    if ($series_key === '') {
        return null;
    }

    $sources = iss_occurrences_get_series_sources();
    return isset($sources[$series_key]) && is_array($sources[$series_key]) ? $sources[$series_key] : null;
}

function iss_occurrences_remember_series_source($series_key, $source_post_id, $source_post_type, $supersaas_title = '', $tag = '', $fallback_url = ''): bool
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
    $review_state = $source_post_id > 0 ? 'mapped' : '';

    if ($supersaas_title === '' && $source_post_id > 0) {
        $source_title = trim((string) get_the_title($source_post_id));
        $source_title = preg_replace('/(?:\s|-)*(tour|fuehrung|führung)$/iu', '', $source_title);
        $supersaas_title = trim((string) $source_title);
    }

    if (!function_exists('iss_occurrences_get_service')) {
        return false;
    }

    $series_id = iss_occurrences_get_service()->upsert_series([
        'series_key' => $series_key,
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'supersaas_title' => $supersaas_title,
        'tag' => $tag,
        'fallback_url' => $fallback_url,
        'review_state' => $review_state,
        'origin' => 'supersaas',
    ]);

    return $series_id > 0;
}

function iss_occurrences_resolve_tag_for_source_post_id($source_post_id): string
{
    $source_post_id = max(0, (int) $source_post_id);
    if ($source_post_id <= 0) {
        return '';
    }

    foreach (iss_occurrences_get_tag_sources() as $tag => $entry) {
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
    if (function_exists('iss_occurrences_get_service')) {
        $service = iss_occurrences_get_service();
        if (method_exists($service, 'get_series_rows')) {
            foreach ($service->get_series_rows() as $row) {
                if (!is_array($row) || (int) ($row['source_post_id'] ?? 0) !== $source_post_id) {
                    continue;
                }

                $series_key = isset($row['series_key']) ? iss_occurrences_normalize_series_key((string) $row['series_key']) : '';
                if ($series_key !== '') {
                    $keys[] = $series_key;
                }
            }
        }
    }

    $keys = array_values(array_unique(array_filter($keys)));
    sort($keys);
    return $keys;
}

function iss_occurrences_resolve_source_by_series_key($series_key): array
{
    $series_key = iss_occurrences_normalize_series_key($series_key);
    if ($series_key === '') {
        return ['source_post_id' => 0, 'source_post_type' => ''];
    }

    if (function_exists('iss_occurrences_get_service')) {
        $service = iss_occurrences_get_service();
        if (method_exists($service, 'get_series_by_key')) {
            $row = $service->get_series_by_key($series_key);
            $source_post_id = is_array($row) ? (int) ($row['source_post_id'] ?? 0) : 0;
            if ($source_post_id > 0 && get_post($source_post_id) instanceof WP_Post) {
                $source_post_type = is_array($row) ? sanitize_key((string) ($row['source_post_type'] ?? '')) : '';
                if ($source_post_type === '') {
                    $source_post_type = sanitize_key((string) get_post_type($source_post_id));
                }

                return [
                    'source_post_id' => $source_post_id,
                    'source_post_type' => $source_post_type,
                ];
            }
        }
    }

    return ['source_post_id' => 0, 'source_post_type' => ''];
}

function iss_occurrences_clear_series_source_for_post($source_post_id): int
{
    $source_post_id = max(0, (int) $source_post_id);
    if ($source_post_id <= 0) {
        return 0;
    }

    if (!function_exists('iss_occurrences_get_service')) {
        return 0;
    }

    return iss_occurrences_get_service()->clear_series_source_for_post($source_post_id);
}

function iss_occurrences_clear_series_source_for_key($series_key): bool
{
    $series_key = iss_occurrences_normalize_series_key($series_key);
    if ($series_key === '') {
        return false;
    }

    if (!function_exists('iss_occurrences_get_service')) {
        return false;
    }

    return iss_occurrences_get_service()->clear_series_source_for_key($series_key);
}

function iss_occurrences_clear_tag_source_for_post($source_post_id): int
{
    $source_post_id = max(0, (int) $source_post_id);
    if ($source_post_id <= 0) {
        return 0;
    }

    if (!function_exists('iss_occurrences_get_service')) {
        return 0;
    }

    return iss_occurrences_get_service()->clear_series_source_for_post($source_post_id);
}

function iss_occurrences_clear_tag_source($tag): bool
{
    $tag = iss_occurrences_normalize_tag($tag);
    if ($tag === '') {
        return false;
    }

    if (!function_exists('iss_occurrences_get_service')) {
        return false;
    }

    return iss_occurrences_get_service()->clear_series_source_for_tag($tag);
}
