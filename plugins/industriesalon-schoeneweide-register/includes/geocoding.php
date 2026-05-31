<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_coordinate_meta_keys(): array
{
    return ['lat', 'lng', 'coordinates_accuracy'];
}

function iss_register_address_supports_berlin_suffix(string $address): bool
{
    return (bool) preg_match('/\b(?:berlin|12\d{3})\b/iu', $address);
}

function iss_register_normalize_geocode_query(string $address): string
{
    $address = html_entity_decode($address, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $address = str_replace(["\r", "\n"], ' ', $address);
    $address = preg_replace('/\s*\([^)]*\)/u', '', $address);
    $address = str_replace([' / ', ' /', '/ '], ', ', $address);
    $address = str_replace(['–', '—'], '-', $address);
    $address = preg_replace('/\bstr\./iu', 'straße', $address);
    $address = preg_replace('/\bstrasse\b/iu', 'straße', $address);

    $segments = preg_split('/\s*,\s*/u', $address);
    if (!is_array($segments)) {
        $segments = [$address];
    }

    $segments = array_values(array_filter(array_map(static function ($segment): string {
        $segment = trim((string) $segment);
        if ($segment === '') {
            return '';
        }

        if (preg_match('/^(?:g\d+(?:\/g\d+)?|haus\s+[a-z0-9-]+|gebäude\s+[a-z0-9]+|[0-9]+\.\s*og|industriesalon|sucht\s+neuen\s+standort|standort\s+offen)$/iu', $segment)) {
            return '';
        }

        $segment = preg_replace('/^ehem\.\s*/iu', '', $segment);

        return $segment;
    }, $segments)));

    $address = implode(', ', $segments);
    $address = trim((string) preg_replace('/\s+/u', ' ', $address));

    if ($address !== '' && !iss_register_address_supports_berlin_suffix($address)) {
        $address .= ', Berlin';
    }

    if ($address !== '' && stripos($address, 'Deutschland') === false) {
        $address .= ', Deutschland';
    }

    return trim($address, ", \t\n\r\0\x0B");
}

function iss_register_geocode_accuracy_label(array $result): string
{
    $addresstype = sanitize_key((string) ($result['addresstype'] ?? ''));
    $category = sanitize_key((string) ($result['category'] ?? ''));

    if (in_array($addresstype, ['amenity', 'shop', 'office', 'tourism', 'industrial', 'commercial', 'building'], true)) {
        return 'nominatim (site)';
    }

    if (in_array($addresstype, ['road', 'residential'], true) || $category === 'highway') {
        return 'nominatim (road)';
    }

    if (in_array($addresstype, ['suburb', 'quarter', 'neighbourhood', 'city_district', 'district'], true)) {
        return 'nominatim (district)';
    }

    if ($addresstype !== '') {
        return 'nominatim (' . $addresstype . ')';
    }

    if ($category !== '') {
        return 'nominatim (' . $category . ')';
    }

    return 'nominatim (approx)';
}

function iss_register_is_valid_berlin_geocode(float $lat, float $lng): bool
{
    return $lat >= 52.35
        && $lat <= 52.60
        && $lng >= 13.25
        && $lng <= 13.70;
}

function iss_register_geocode_address(string $address)
{
    $query = iss_register_normalize_geocode_query($address);
    if ($query === '') {
        return new WP_Error('iss_register_geocode_empty_query', 'Adresse leer oder nicht geocodierbar.');
    }

    $cache_key = 'iss_register_geo_' . md5($query);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        $cached['cached'] = true;
        return $cached;
    }

    $request_url = add_query_arg([
        'format' => 'jsonv2',
        'limit' => 1,
        'addressdetails' => 1,
        'countrycodes' => 'de',
        'q' => $query,
    ], 'https://nominatim.openstreetmap.org/search');

    $response = wp_remote_get($request_url, [
        'timeout' => 20,
        'redirection' => 3,
        'headers' => [
            'User-Agent' => 'IndustriesalonRegisterBot/1.0 (main-site geocoding)',
            'Accept-Language' => 'de',
        ],
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if ($status_code !== 200) {
        return new WP_Error('iss_register_geocode_http_error', 'Geocoder antwortete mit HTTP ' . $status_code . '.');
    }

    $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($decoded) || !$decoded) {
        return new WP_Error('iss_register_geocode_no_match', 'Kein Geocode-Treffer gefunden.');
    }

    $result = $decoded[0];
    $lat = isset($result['lat']) ? (float) $result['lat'] : null;
    $lng = isset($result['lon']) ? (float) $result['lon'] : null;

    if (!is_numeric($lat) || !is_numeric($lng)) {
        return new WP_Error('iss_register_geocode_invalid_match', 'Geocode-Treffer ohne verwertbare Koordinaten.');
    }

    if (!iss_register_is_valid_berlin_geocode((float) $lat, (float) $lng)) {
        return new WP_Error('iss_register_geocode_outside_scope', 'Geocode-Treffer liegt außerhalb des Berliner Zielbereichs.');
    }

    $normalized = [
        'lat' => (float) $lat,
        'lng' => (float) $lng,
        'accuracy' => iss_register_geocode_accuracy_label($result),
        'display_name' => (string) ($result['display_name'] ?? ''),
        'query' => $query,
        'cached' => false,
    ];

    set_transient($cache_key, $normalized, MONTH_IN_SECONDS);

    return $normalized;
}

function iss_register_get_coordinate_coverage(): array
{
    $posts = get_posts([
        'post_type' => ISS_REGISTER_POST_TYPE,
        'post_status' => ['publish', 'draft', 'private', 'pending'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);

    $stats = [
        'total' => count($posts),
        'with_address' => 0,
        'with_coordinates' => 0,
        'missing_coordinates' => 0,
    ];

    foreach ($posts as $post_id) {
        $address = trim((string) get_post_meta((int) $post_id, 'address', true));
        $lat = get_post_meta((int) $post_id, 'lat', true);
        $lng = get_post_meta((int) $post_id, 'lng', true);

        if ($address !== '') {
            $stats['with_address']++;
        }

        if ($lat !== '' && $lng !== '') {
            $stats['with_coordinates']++;
        } elseif ($address !== '') {
            $stats['missing_coordinates']++;
        }
    }

    return $stats;
}

function iss_register_get_places_for_coordinate_backfill(int $limit = 20, bool $force = false): array
{
    $posts = get_posts([
        'post_type' => ISS_REGISTER_POST_TYPE,
        'post_status' => ['publish', 'draft', 'private', 'pending'],
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    $items = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $address = trim((string) get_post_meta($post->ID, 'address', true));
        if ($address === '') {
            continue;
        }

        $lat = get_post_meta($post->ID, 'lat', true);
        $lng = get_post_meta($post->ID, 'lng', true);

        if (!$force && $lat !== '' && $lng !== '') {
            continue;
        }

        $items[] = [
            'post_id' => (int) $post->ID,
            'title' => (string) $post->post_title,
            'address' => $address,
        ];
    }

    if ($limit > 0) {
        $items = array_slice($items, 0, $limit);
    }

    return $items;
}

function iss_register_run_coordinate_backfill(array $options = []): array
{
    $dry_run = !empty($options['dry_run']);
    $force = !empty($options['force']);
    $limit = max(1, min(200, (int) ($options['limit'] ?? 20)));
    $sleep_microseconds = max(0, (int) ($options['sleep_microseconds'] ?? 1100000));
    $items = iss_register_get_places_for_coordinate_backfill($limit, $force);

    $result = [
        'dry_run' => $dry_run,
        'stats' => [
            'selected' => count($items),
            'updated' => 0,
            'would_update' => 0,
            'no_match' => 0,
            'errors' => 0,
        ],
        'messages' => [],
    ];

    if (!$items) {
        return $result;
    }

    if (function_exists('set_time_limit')) {
        @set_time_limit(0);
    }

    foreach ($items as $index => $item) {
        $geo_result = iss_register_geocode_address((string) $item['address']);
        if (is_wp_error($geo_result)) {
            $error_code = $geo_result->get_error_code();
            if ($error_code === 'iss_register_geocode_no_match') {
                $result['stats']['no_match']++;
            } else {
                $result['stats']['errors']++;
            }

            $result['messages'][] = $item['title'] . ': ' . $geo_result->get_error_message();
            continue;
        }

        $message = sprintf(
            '%s: %s, %s (%s)',
            $item['title'],
            (string) $geo_result['lat'],
            (string) $geo_result['lng'],
            (string) $geo_result['accuracy']
        );

        if ($dry_run) {
            $result['stats']['would_update']++;
            $result['messages'][] = '[Dry run] ' . $message;
        } else {
            update_post_meta($item['post_id'], 'lat', (float) $geo_result['lat']);
            update_post_meta($item['post_id'], 'lng', (float) $geo_result['lng']);
            update_post_meta($item['post_id'], 'coordinates_accuracy', (string) $geo_result['accuracy']);
            $result['stats']['updated']++;
            $result['messages'][] = $message;
        }

        if (!$dry_run && empty($geo_result['cached']) && $sleep_microseconds > 0 && $index < count($items) - 1) {
            usleep($sleep_microseconds);
        }
    }

    if (!$dry_run && $result['stats']['updated'] > 0 && function_exists('iss_register_clear_places_cache')) {
        iss_register_clear_places_cache();
    }

    return $result;
}
