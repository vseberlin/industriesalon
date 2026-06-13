<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/providers/provider-interface.php';
require_once __DIR__ . '/providers/abstract-editorial-provider.php';
require_once __DIR__ . '/providers/veranstaltung-provider.php';
require_once __DIR__ . '/providers/ausstellung-provider.php';
require_once __DIR__ . '/providers/projekt-provider.php';
require_once __DIR__ . '/providers/fuehrung-provider.php';

/**
 * Editorial sources are WordPress posts that opt into occurrence projection.
 *
 * Führung occurrences are deliberately not listed here: their occurrence rows
 * are external SuperSaaS slot projections linked back to `fuehrung` posts.
 */
function iss_occurrences_supported_editorial_source_post_types(): array
{
    return array_keys(iss_occurrences_get_editorial_source_providers());
}

function iss_occurrences_supported_source_post_types(): array
{
    return iss_occurrences_supported_editorial_source_post_types();
}

function iss_occurrences_get_editorial_source_providers(): array
{
    static $providers = null;

    if ($providers === null) {
        $providers = [
            'veranstaltung' => new ISS_Occurrences_VeranstaltungProvider(),
            'ausstellung' => new ISS_Occurrences_AusstellungProvider(),
            'projekt' => new ISS_Occurrences_ProjektProvider(),
        ];
    }

    return $providers;
}

function iss_occurrences_get_editorial_source_provider(string $post_type): ?ISS_Occurrences_Source_Provider
{
    $post_type = sanitize_key($post_type);
    $providers = iss_occurrences_get_editorial_source_providers();

    return isset($providers[$post_type]) && $providers[$post_type] instanceof ISS_Occurrences_Source_Provider
        ? $providers[$post_type]
        : null;
}

function iss_occurrences_get_fuehrung_provider(): ISS_Occurrences_FuehrungProvider
{
    static $provider = null;

    if (!$provider instanceof ISS_Occurrences_FuehrungProvider) {
        $provider = new ISS_Occurrences_FuehrungProvider();
    }

    return $provider;
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
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return false;
    }

    $provider = iss_occurrences_get_editorial_source_provider((string) $post->post_type);
    return $provider instanceof ISS_Occurrences_Source_Provider
        ? $provider->is_calendar_enabled($post)
        : false;
}

function iss_occurrences_ausstellung_is_availability_type(int $post_id): bool
{
    $provider = iss_occurrences_get_editorial_source_provider('ausstellung');
    return $provider instanceof ISS_Occurrences_AusstellungProvider
        ? $provider->is_availability_type($post_id)
        : false;
}

function iss_occurrences_ausstellung_has_type(int $post_id, array $target_slugs): bool
{
    $provider = iss_occurrences_get_editorial_source_provider('ausstellung');
    return $provider instanceof ISS_Occurrences_AusstellungProvider
        ? $provider->has_type($post_id, $target_slugs)
        : false;
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
    $provider = iss_occurrences_get_editorial_source_provider((string) $post->post_type);
    if (!$provider instanceof ISS_Occurrences_Source_Provider) {
        return ['starts_at' => '', 'ends_at' => '', 'is_open_ended' => false, 'date_source' => ''];
    }

    return $provider->get_start_end_for_post($post);
}

function iss_occurrences_kind_for_source_post_type(string $post_type): string
{
    $provider = iss_occurrences_get_editorial_source_provider($post_type);
    if ($provider instanceof ISS_Occurrences_Source_Provider) {
        return $provider->get_kind();
    }

    $fuehrung_provider = iss_occurrences_get_fuehrung_provider();
    if (sanitize_key($post_type) === $fuehrung_provider->get_post_type()) {
        return $fuehrung_provider->get_kind();
    }

    return '';
}

function iss_occurrences_get_location_data(int $post_id): array
{
    $location_post_id = (int) get_post_meta($post_id, 'iss_primary_place_id', true);
    $location_label = trim((string) get_post_meta($post_id, 'iss_location', true));

    if ($location_label === '' && $location_post_id > 0) {
        $location_label = trim((string) get_the_title($location_post_id));
    }

    return [
        'location_post_id' => $location_post_id,
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

    $provider = iss_occurrences_get_editorial_source_provider((string) $post->post_type);
    if (!$provider instanceof ISS_Occurrences_Source_Provider) {
        return [];
    }

    return $provider->get_occurrences_for_post($post);
}

function iss_occurrences_sync_source(int $post_id): int
{
    $post_id = max(0, $post_id);
    if ($post_id <= 0) {
        return 0;
    }

    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, iss_occurrences_supported_editorial_source_post_types(), true)) {
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
        'post_type' => iss_occurrences_supported_editorial_source_post_types(),
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
    return iss_occurrences_get_fuehrung_provider()->sync_slot($slot);
}

function iss_occurrences_sync_all(): array
{
    $sources = iss_occurrences_sync_all_sources();
    $supersaas = function_exists('iss_supersaas_sync_occurrences') ? iss_supersaas_sync_occurrences() : [];

    return [
        'sources' => $sources,
        'supersaas' => $supersaas,
    ];
}
