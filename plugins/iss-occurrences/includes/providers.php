<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_occurrences_supported_source_post_types(): array
{
    return ['veranstaltung', 'ausstellung', 'projekt'];
}

function iss_occurrences_get_bool_meta(int $post_id, string $key, bool $default): bool
{
    $raw = get_post_meta($post_id, $key, true);
    if ($raw === '' || $raw === null) {
        return $default;
    }

    return !empty($raw);
}

function iss_occurrences_source_is_calendar_enabled(int $post_id): bool
{
    $post_type = (string) get_post_type($post_id);

    if ($post_type === 'veranstaltung' || $post_type === 'ausstellung' || $post_type === 'projekt') {
        return iss_occurrences_get_bool_meta($post_id, 'iss_timeline_enabled', false);
    }

    return false;
}

function iss_occurrences_post_date_fallback(WP_Post $post): string
{
    $date = trim((string) $post->post_date);
    if ($date === '' || $date === '0000-00-00 00:00:00') {
        return current_time('mysql');
    }

    return $date;
}

function iss_occurrences_source_start_end_for_post(WP_Post $post): array
{
    $service = iss_occurrences_get_service();
    $post_id = (int) $post->ID;

    if ($post->post_type === 'veranstaltung') {
        $start_raw = trim((string) get_post_meta($post_id, 'iss_start_datetime', true));
        $end_raw = trim((string) get_post_meta($post_id, 'iss_end_datetime', true));
        $start = $service->normalize_datetime($start_raw);
        $date_source = $start !== '' ? 'explicit' : 'fallback_post_date';

        if ($start === '') {
            $start = $service->normalize_datetime(iss_occurrences_post_date_fallback($post));
        }

        return [
            'starts_at' => $start,
            'ends_at' => $service->normalize_datetime($end_raw, true),
            'date_source' => $date_source,
        ];
    }

    if ($post->post_type === 'ausstellung') {
        $start_raw = trim((string) get_post_meta($post_id, 'iss_start_date', true));
        $end_raw = trim((string) get_post_meta($post_id, 'iss_end_date', true));
        $is_permanent = function_exists('iss_content_model_ausstellung_is_permanent')
            ? iss_content_model_ausstellung_is_permanent($post_id)
            : (bool) get_post_meta($post_id, 'iss_is_permanent', true);
        $start = $service->normalize_datetime($start_raw);
        $date_source = $start !== '' ? 'explicit' : 'fallback_post_date';

        if ($start === '') {
            $start = $service->normalize_datetime(iss_occurrences_post_date_fallback($post));
        }

        if ($end_raw === '' && $is_permanent) {
            $end_raw = '2099-12-31';
        }

        return [
            'starts_at' => $start,
            'ends_at' => $service->normalize_datetime($end_raw, true),
            'date_source' => $date_source,
        ];
    }

    if ($post->post_type === 'projekt') {
        $start_raw = trim((string) get_post_meta($post_id, 'iss_start_date', true));
        $end_raw = trim((string) get_post_meta($post_id, 'iss_end_date', true));
        $start = $service->normalize_datetime($start_raw);
        $date_source = $start !== '' ? 'explicit' : 'fallback_post_date';

        if ($start === '') {
            $start = $service->normalize_datetime(iss_occurrences_post_date_fallback($post));
        }

        return [
            'starts_at' => $start,
            'ends_at' => $service->normalize_datetime($end_raw, true),
            'date_source' => $date_source,
        ];
    }

    return ['starts_at' => '', 'ends_at' => '', 'date_source' => ''];
}

function iss_occurrences_kind_for_source_post_type(string $post_type): string
{
    if ($post_type === 'veranstaltung') {
        return 'event';
    }
    if ($post_type === 'ausstellung') {
        return 'ausstellung';
    }
    if ($post_type === 'projekt') {
        return 'project';
    }

    return '';
}

function iss_occurrences_get_location_data(int $post_id): array
{
    $location_post_id = (int) get_post_meta($post_id, 'iss_primary_place_id', true);
    $location_label = trim((string) get_post_meta($post_id, 'iss_location', true));
    $location_entity_id = $location_post_id > 0
        ? iss_occurrences_get_service()->resolve_graph_entity_id_for_post($location_post_id, true)
        : 0;

    if ($location_label === '' && $location_post_id > 0) {
        $location_label = trim((string) get_the_title($location_post_id));
    }

    return [
        'location_post_id' => $location_post_id,
        'location_entity_id' => $location_entity_id,
        'location_label' => $location_label,
    ];
}

function iss_occurrences_get_occurrences_for_post(int $post_id): array
{
    $post_id = max(0, $post_id);
    if ($post_id <= 0) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
        return [];
    }

    if (!in_array($post->post_type, iss_occurrences_supported_source_post_types(), true)) {
        return [];
    }

    if (!iss_occurrences_source_is_calendar_enabled($post_id)) {
        return [];
    }

    $dates = iss_occurrences_source_start_end_for_post($post);
    if (empty($dates['starts_at'])) {
        return [];
    }

    $kind = iss_occurrences_kind_for_source_post_type($post->post_type);
    if ($kind === '') {
        return [];
    }

    $location = iss_occurrences_get_location_data($post_id);

    return [[
        'entity_id' => iss_occurrences_get_service()->resolve_graph_entity_id_for_post($post_id, true),
        'source_post_id' => $post_id,
        'source_post_type' => $post->post_type,
        'kind' => $kind,
        'title' => get_the_title($post_id),
        'starts_at' => $dates['starts_at'],
        'ends_at' => $dates['ends_at'],
        'date_source' => $dates['date_source'],
        'status' => 'active',
        'visibility' => 'public',
        'origin' => 'wp',
        'external_id' => 'wp:' . $post->post_type . ':' . $post_id,
        'tag' => '',
        'source_calendar' => '',
        'series_key' => '',
        'booking_url' => '',
        'location_post_id' => (int) $location['location_post_id'],
        'location_entity_id' => (int) $location['location_entity_id'],
        'location_label' => (string) $location['location_label'],
    ]];
}

function iss_occurrences_sync_source(int $post_id): int
{
    $post_id = max(0, $post_id);
    if ($post_id <= 0) {
        return 0;
    }

    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, iss_occurrences_supported_source_post_types(), true)) {
        return 0;
    }

    $service = iss_occurrences_get_service();
    $service->delete_source_occurrences($post_id, $post_type, 'wp');

    $count = 0;
    foreach (iss_occurrences_get_occurrences_for_post($post_id) as $row) {
        if ($service->upsert_occurrence($row) > 0) {
            $count++;
        }
    }

    return $count;
}

function iss_occurrences_sync_all_sources(): int
{
    $ids = get_posts([
        'post_type' => iss_occurrences_supported_source_post_types(),
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);

    $count = 0;
    foreach ((array) $ids as $post_id) {
        $count += iss_occurrences_sync_source((int) $post_id);
    }

    return $count;
}

function iss_occurrences_sync_supersaas(array $slot): int
{
    if (empty($slot['external_id']) && !empty($slot['id'])) {
        $slot['external_id'] = $slot['id'];
    }

    $source_post_id = isset($slot['source_post_id']) ? (int) $slot['source_post_id'] : 0;
    $source_post_type = isset($slot['source_post_type']) ? sanitize_key((string) $slot['source_post_type']) : '';
    $external_id = isset($slot['external_id']) ? sanitize_text_field((string) $slot['external_id']) : '';
    $origin = isset($slot['origin']) ? sanitize_key((string) $slot['origin']) : 'supersaas';

    if ($external_id === '' || $source_post_id <= 0 || $source_post_type !== 'fuehrung') {
        if ($external_id !== '') {
            iss_occurrences_get_service()->delete_occurrence_by_external($origin, $external_id);
        }
        return 0;
    }

    $source = get_post($source_post_id);
    if (!$source instanceof WP_Post || $source->post_status !== 'publish' || $source->post_type !== 'fuehrung') {
        iss_occurrences_get_service()->delete_occurrence_by_external($origin, $external_id);
        return 0;
    }

    $service = iss_occurrences_get_service();
    $start = $service->normalize_datetime((string) ($slot['start'] ?? $slot['starts_at'] ?? ''));
    if ($start === '') {
        iss_occurrences_get_service()->delete_occurrence_by_external($origin, $external_id);
        return 0;
    }

    return $service->upsert_occurrence([
        'entity_id' => $service->resolve_graph_entity_id_for_post($source_post_id, true),
        'source_post_id' => $source_post_id,
        'source_post_type' => 'fuehrung',
        'kind' => 'tour',
        'title' => isset($slot['title']) ? sanitize_text_field((string) $slot['title']) : get_the_title($source_post_id),
        'starts_at' => $start,
        'ends_at' => $service->normalize_datetime((string) ($slot['end'] ?? $slot['ends_at'] ?? ''), true),
        'date_source' => 'supersaas',
        'status' => 'active',
        'visibility' => 'public',
        'origin' => $origin,
        'source_calendar' => isset($slot['source_calendar']) ? sanitize_text_field((string) $slot['source_calendar']) : '',
        'external_id' => $external_id,
        'tag' => isset($slot['tag']) ? strtoupper(sanitize_text_field((string) $slot['tag'])) : '',
        'series_key' => isset($slot['series_key']) ? sanitize_text_field((string) $slot['series_key']) : '',
        'booking_url' => isset($slot['booking_url']) ? esc_url_raw((string) $slot['booking_url']) : '',
        'location_label' => isset($slot['location']) ? sanitize_text_field((string) $slot['location']) : '',
        'availability_state' => isset($slot['availability_state']) ? sanitize_key((string) $slot['availability_state']) : '',
        'capacity_total' => array_key_exists('capacity', $slot) && $slot['capacity'] !== null ? (int) $slot['capacity'] : null,
        'capacity_available' => array_key_exists('available', $slot) && $slot['available'] !== null ? (int) $slot['available'] : null,
    ]) > 0 ? 1 : 0;
}

function iss_occurrences_sync_all(): array
{
    $sources = iss_occurrences_sync_all_sources();
    $supersaas = function_exists('iss_supersaas_sync_occurrences') ? iss_supersaas_sync_occurrences() : [];

    return [
        'sources' => $sources,
        'supersaas' => $supersaas,
        'graph_backfilled' => iss_occurrences_get_service()->backfill_graph_entities(),
    ];
}
