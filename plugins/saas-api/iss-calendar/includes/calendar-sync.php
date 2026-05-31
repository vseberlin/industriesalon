<?php

if (!defined('ABSPATH')) exit;

define('ISS_CALENDAR_SYNC_HOOK', 'iss_calendar_cron_sync');
define('ISS_CALENDAR_LAST_SYNC_OPTION', 'iss_calendar_last_sync_at');

add_action(ISS_CALENDAR_SYNC_HOOK, function () {
    iss_calendar_sync_supersaas_to_cpt();
});

function iss_calendar_activate_sync() {
    if (!wp_next_scheduled(ISS_CALENDAR_SYNC_HOOK)) {
        wp_schedule_event(time() + 60, 'hourly', ISS_CALENDAR_SYNC_HOOK);
    }
}

function iss_calendar_deactivate_sync() {
    $ts = wp_next_scheduled(ISS_CALENDAR_SYNC_HOOK);
    if ($ts) {
        wp_unschedule_event($ts, ISS_CALENDAR_SYNC_HOOK);
    }
}

/**
 * Fetch raw "free slots" from SuperSaaS.
 *
 * @return array|WP_Error
 */
function iss_calendar_supersaas_fetch_free_slots($settings = null) {
    if ($settings === null) {
        $settings = function_exists('is_saas_get_settings') ? is_saas_get_settings() : [];
    }

    if (!is_array($settings)) {
        return new WP_Error('iss_calendar_settings', 'Invalid settings.');
    }

    $schedule_id = isset($settings['schedule_id']) ? (string) $settings['schedule_id'] : '';
    $api_key = isset($settings['api_key']) ? (string) $settings['api_key'] : '';
    $account_name = isset($settings['account_name']) ? (string) $settings['account_name'] : '';
    $base_url = isset($settings['base_url']) ? (string) $settings['base_url'] : '';

    if ($schedule_id === '' || $api_key === '' || $account_name === '' || $base_url === '') {
        return new WP_Error('iss_calendar_config', 'Missing SuperSaaS configuration.');
    }

    $future_months = (int) apply_filters('iss_calendar_sync_future_months', 6);
    if ($future_months < 1) {
        $future_months = 1;
    }

    $cache_key = 'iss_calendar_free_' . md5($base_url . '|' . $account_name . '|' . $schedule_id . '|m:' . $future_months);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $base_url = untrailingslashit($base_url);
    $tz = wp_timezone();
    $from_dt = new DateTimeImmutable('now', $tz);
    $to_dt = $from_dt->modify('+' . $future_months . ' months');
    $from = rawurlencode($from_dt->format('Y-m-d H:i:s'));
    $to = rawurlencode($to_dt->format('Y-m-d H:i:s'));
    $url = $base_url . '/api/free/' . rawurlencode($schedule_id) . '.json?from=' . $from . '&to=' . $to;

    $response = wp_remote_get($url, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($account_name . ':' . $api_key),
        ],
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('iss_calendar_fetch', $response->get_error_message());
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('iss_calendar_upstream', 'Upstream request failed with status ' . $code . '.');
    }

    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $slot_items = isset($data['slots']) && is_array($data['slots']) ? $data['slots'] : $data;

    if (!is_array($slot_items)) {
        return new WP_Error('iss_calendar_parse', 'Invalid API response.');
    }

    set_transient($cache_key, $slot_items, 60 * 5);
    return $slot_items;
}

/**
 * Parse a SuperSaaS slot title like: "[TAG] Public Title".
 *
 * @return array{tag:string,title:string,raw_title:string}
 */
function iss_calendar_parse_supersaas_title($raw_title) {
    $raw_title = trim((string) $raw_title);
    $tag = '';
    $title = $raw_title;

    if (preg_match('/^\\s*\\[([^\\]]+)\\]\\s*(.*)$/u', $raw_title, $m)) {
        $tag = strtoupper(trim((string) $m[1]));
        $title = trim((string) $m[2]);
        if ($title === '') {
            $title = $raw_title;
        }
    }

    return [
        'tag' => $tag,
        'title' => $title,
        'raw_title' => $raw_title,
    ];
}

function iss_calendar_clean_supersaas_title($raw_title) {
    $raw_title = trim((string) $raw_title);
    if ($raw_title === '') return '';

    $parsed = iss_calendar_parse_supersaas_title($raw_title);
    $title = isset($parsed['title']) ? (string) $parsed['title'] : '';
    return $title !== '' ? $title : $raw_title;
}

function iss_calendar_generate_tag_from_title($title) {
    $title = trim((string) $title);
    if ($title === '') {
        return 'TOUR_' . substr(md5((string) microtime(true)), 0, 8);
    }

    $slug = sanitize_title($title);
    $slug = strtoupper(str_replace('-', '_', $slug));
    $slug = preg_replace('/[^A-Z0-9_]+/', '', (string) $slug);
    $slug = trim((string) $slug, '_');

    if ($slug === '') {
        $slug = 'TOUR_' . substr(md5($title), 0, 8);
    }

    if (strlen($slug) > 32) {
        $slug = substr($slug, 0, 32);
        $slug = rtrim($slug, '_');
    }

    return $slug;
}

function iss_calendar_extract_tag_from_text($text) {
    $text = trim((string) $text);
    if ($text === '') return '';

    // Hidden marker (if SuperSaaS renders HTML): "Elektropolis Tour <!-- TAG=ELEKTRO -->"
    if (preg_match('/<!--\\s*TAG\\s*[:=]\\s*([A-Z0-9_-]{2,})\\s*-->/i', $text, $m)) {
        return strtoupper(trim((string) $m[1]));
    }

    // Example: "TAG=ELEKTRO" or "tag: elektro"
    if (preg_match('/\\bTAG\\s*[:=]\\s*([A-Z0-9_-]{2,})\\b/i', $text, $m)) {
        return strtoupper(trim((string) $m[1]));
    }

    // Example: "[ELEKTRO] some note"
    if (preg_match('/^\\s*\\[([^\\]]{2,})\\]/u', $text, $m)) {
        return strtoupper(trim((string) $m[1]));
    }

    return '';
}

function iss_calendar_extract_supersaas_slot_tag($slot) {
    if (!is_array($slot)) return '';

    // Preferred: keep public title clean, store tag in description.
    $desc = isset($slot['description']) ? (string) $slot['description'] : '';
    $tag = iss_calendar_extract_tag_from_text($desc);
    if ($tag !== '') return $tag;

    // Back-compat: legacy "[TAG] Title" prefix in title.
    $raw_title = isset($slot['title']) ? (string) $slot['title'] : '';
    $parsed = iss_calendar_parse_supersaas_title($raw_title);
    $tag = isset($parsed['tag']) ? strtoupper((string) $parsed['tag']) : '';
    if ($tag !== '') return $tag;

    // Optional: allow tagging via location only if it matches our tag pattern.
    $loc = isset($slot['location']) ? (string) $slot['location'] : '';
    $tag = iss_calendar_extract_tag_from_text($loc);
    if ($tag !== '') return $tag;

    return '';
}

/**
 * Normalize SuperSaaS free slots for a given (clean) title to the REST "slots" shape.
 *
 * @param string $title
 * @param array|null $settings
 * @return array<int,array<string,mixed>>|WP_Error
 */
function iss_calendar_get_supersaas_slots_for_title($title, $settings = null) {
    $title = trim((string) $title);
    if ($title === '') {
        return [];
    }

    $slot_items = iss_calendar_supersaas_fetch_free_slots($settings);
    if (is_wp_error($slot_items)) {
        return $slot_items;
    }

    $slots = [];

    foreach ($slot_items as $slot) {
        if (!is_array($slot)) continue;

        $raw_title = isset($slot['title']) ? (string) $slot['title'] : '';
        $clean_title = iss_calendar_clean_supersaas_title($raw_title);
        if ($clean_title === '' || $clean_title !== $title) {
            continue;
        }

        $start = $slot['start'] ?? null;
        if (!$start) continue;

        $available = null;
        if (isset($slot['available'])) {
            $available = (int) $slot['available'];
        } elseif (isset($slot['remaining'])) {
            $available = (int) $slot['remaining'];
        } elseif (isset($slot['count'])) {
            $available = (int) $slot['count'];
        }

        $end = $slot['end'] ?? ($slot['finish'] ?? null);
        if ($end === '') $end = null;

        $slots[] = [
            'id' => isset($slot['id']) ? (string) $slot['id'] : '',
            'title' => $clean_title,
            'start' => $start,
            'end' => $end,
            'available' => $available,
            'capacity' => isset($slot['capacity']) ? (int) $slot['capacity'] : null,
            'booking_url' => null,
        ];
    }

    usort($slots, function ($a, $b) {
        return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
    });

    return $slots;
}

/**
 * Normalize SuperSaaS free slots for a specific tag to the REST "slots" shape.
 *
 * @param string $tag
 * @param array|null $settings
 * @return array<int,array<string,mixed>>|WP_Error
 */
function iss_calendar_get_supersaas_slots_for_tag($tag, $settings = null) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') {
        return [];
    }

    $slot_items = iss_calendar_supersaas_fetch_free_slots($settings);
    if (is_wp_error($slot_items)) {
        return $slot_items;
    }

    $slots = [];

    foreach ($slot_items as $slot) {
        if (!is_array($slot)) {
            continue;
        }

        $raw_title = isset($slot['title']) ? trim((string) $slot['title']) : '';
        if ($raw_title === '') {
            continue;
        }

        $slot_tag = iss_calendar_extract_supersaas_slot_tag($slot);
        if ($slot_tag === '' || $slot_tag !== $tag) {
            continue;
        }

        $parsed = iss_calendar_parse_supersaas_title($raw_title);
        $clean_title = !empty($parsed['title']) ? (string) $parsed['title'] : $raw_title;

        $start = $slot['start'] ?? null;
        if (!$start) {
            continue;
        }

        if (function_exists('is_saas_build_slot_response')) {
            $built = is_saas_build_slot_response($slot, $clean_title, $start);
            if (is_array($built)) {
                $built['id'] = isset($built['id']) ? (string) $built['id'] : '';
                $built['booking_url'] = null;
                $slots[] = $built;
            }
            continue;
        }

        // Fallback normalizer if plugin function isn't available for some reason.
        $available = null;
        if (isset($slot['available'])) {
            $available = (int) $slot['available'];
        } elseif (isset($slot['remaining'])) {
            $available = (int) $slot['remaining'];
        } elseif (isset($slot['count'])) {
            $available = (int) $slot['count'];
        }

        $slots[] = [
            'id' => isset($slot['id']) ? (string) $slot['id'] : '',
            'title' => $clean_title,
            'start' => $start,
            'end' => $slot['end'] ?? ($slot['finish'] ?? null),
            'capacity' => isset($slot['capacity']) ? (int) $slot['capacity'] : null,
            'available' => $available,
            'booking_url' => null,
        ];
    }

    usort($slots, function ($a, $b) {
        return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
    });

    return $slots;
}

/**
 * Get normalized slots with automatic CPT fallback when SaaS is unavailable.
 *
 * @param string $tag
 * @param array|null $settings
 * @return array{slots:array<int,array<string,mixed>>,source:string,error:WP_Error|null}
 */
function iss_calendar_get_slots_with_fallback($tag, $settings = null) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') {
        return ['slots' => [], 'source' => 'empty', 'error' => null];
    }

    $slots = iss_calendar_get_supersaas_slots_for_tag($tag, $settings);
    if (!is_wp_error($slots) && empty($slots) && function_exists('iss_calendar_get_source_map_entry')) {
        $entry = iss_calendar_get_source_map_entry($tag);
        if (is_array($entry) && !empty($entry['supersaas_title'])) {
            $slots = iss_calendar_get_supersaas_slots_for_title((string) $entry['supersaas_title'], $settings);
        }
    }
    if (!is_wp_error($slots) && !empty($slots)) {
        return ['slots' => $slots, 'source' => 'saas', 'error' => null];
    }

    $fallback = iss_calendar_get_slots_fallback_for_tag($tag);
    if (!empty($fallback)) {
        return [
            'slots' => $fallback,
            'source' => 'cpt',
            'error' => is_wp_error($slots) ? $slots : null,
        ];
    }

    return [
        'slots' => [],
        'source' => is_wp_error($slots) ? 'error' : 'empty',
        'error' => is_wp_error($slots) ? $slots : null,
    ];
}

/**
 * REST fallback: serve future slots from CPT when SuperSaaS is down.
 *
 * @return array<int,array<string,mixed>>
 */
function iss_calendar_get_slots_fallback_for_tag($tag) {
    $entry = function_exists('iss_calendar_get_source_map_entry') ? iss_calendar_get_source_map_entry($tag) : null;
    if (!is_array($entry)) return [];

    $source_post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;
    if ($source_post_id <= 0) return [];

    $now = current_time('Y-m-d H:i:s');

    $q = new WP_Query([
        'post_type' => ISS_CALENDAR_ITEM_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 250,
        'orderby' => 'meta_value',
        'meta_type' => 'DATETIME',
        'order' => 'ASC',
        'meta_key' => 'event_start',
        'meta_query' => [
            [
                'key' => 'source_post_id',
                'value' => $source_post_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
            [
                'key' => 'event_start',
                'value' => $now,
                'compare' => '>=',
                'type' => 'DATETIME',
            ],
            [
                'key' => 'is_public',
                'value' => 1,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    $out = [];
    foreach ($q->posts as $post) {
        $post_id = (int) $post->ID;

        $external_id = (string) get_post_meta($post_id, 'external_id', true);
        $start = (string) get_post_meta($post_id, 'event_start', true);
        if ($external_id === '' || $start === '') continue;

        $cap_raw = get_post_meta($post_id, 'capacity_total', true);
        $avail_raw = get_post_meta($post_id, 'capacity_available', true);

        $capacity = ($cap_raw === '' || $cap_raw === null) ? null : (int) $cap_raw;
        $available = ($avail_raw === '' || $avail_raw === null) ? null : (int) $avail_raw;
        $booking_url = get_post_meta($post_id, 'booking_url', true);
        $booking_url = $booking_url ? (string) $booking_url : null;

        $end = (string) get_post_meta($post_id, 'event_end', true);
        $end = $end !== '' ? $end : null;

        $out[] = [
            'id' => (string) $external_id,
            'title' => function_exists('is_saas_resolve_calendar_item_title')
                ? is_saas_resolve_calendar_item_title($post_id)
                : (string) get_the_title($post_id),
            'start' => $start,
            'end' => $end,
            'capacity' => $capacity,
            'available' => $available,
            'booking_url' => $booking_url,
            'content_url' => function_exists('is_saas_resolve_calendar_item_content_url')
                ? is_saas_resolve_calendar_item_content_url($post_id)
                : null,
        ];
    }

    return $out;
}

function iss_calendar_map_entry_title_candidates($entry) {
    if (!is_array($entry)) {
        return [];
    }

    $candidates = [];

    $mapped_title = isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '';
    if ($mapped_title !== '') {
        $candidates[] = $mapped_title;
    }

    $source_post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;
    if ($source_post_id > 0) {
        $source_title = trim((string) get_the_title($source_post_id));
        if ($source_title !== '') {
            $candidates[] = $source_title;
            $candidates[] = preg_replace('/(?:\\s|-)*(tour|fuehrung|führung)$/iu', '', $source_title);
        }
    }

    $cleaned = [];
    foreach ($candidates as $candidate) {
        $candidate = trim((string) $candidate);
        if ($candidate === '') {
            continue;
        }
        $clean = function_exists('iss_calendar_clean_supersaas_title')
            ? iss_calendar_clean_supersaas_title($candidate)
            : $candidate;
        $clean = trim((string) $clean);
        if ($clean !== '') {
            $cleaned[] = $clean;
        }
    }

    return array_values(array_unique($cleaned));
}

function iss_calendar_match_fuehrung_by_title_candidates($candidates) {
    if (!is_array($candidates) || empty($candidates)) {
        return 0;
    }

    $normalize = static function ($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/(?:\\s|-)*(tour|fuehrung|führung)$/iu', '', $value);
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return sanitize_title($value);
    };

    $candidate_keys = [];
    foreach ($candidates as $candidate) {
        $key = $normalize($candidate);
        if ($key !== '') {
            $candidate_keys[$key] = true;
        }
    }
    if (empty($candidate_keys)) {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'fuehrung',
        'post_status' => ['publish', 'draft', 'private', 'pending'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'title',
        'order' => 'ASC',
        'no_found_rows' => true,
    ]);

    $matches = [];
    foreach ($posts as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            continue;
        }

        $title_key = $normalize(get_the_title($post_id));
        if ($title_key !== '' && isset($candidate_keys[$title_key])) {
            $matches[] = $post_id;
            continue;
        }

        $slug = (string) get_post_field('post_name', $post_id);
        $slug_key = $normalize(str_replace(['-', '_'], ' ', $slug));
        if ($slug_key !== '' && isset($candidate_keys[$slug_key])) {
            $matches[] = $post_id;
        }
    }

    $matches = array_values(array_unique(array_filter(array_map('intval', $matches))));
    if (count($matches) !== 1) {
        return 0;
    }

    return (int) $matches[0];
}

function iss_calendar_try_resolve_source_post_from_map_entry($entry) {
    if (!is_array($entry)) {
        return 0;
    }

    $source_post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;
    if ($source_post_id > 0 && get_post($source_post_id) instanceof WP_Post) {
        return $source_post_id;
    }

    $fallback_url = isset($entry['fallback_url']) ? trim((string) $entry['fallback_url']) : '';
    if ($fallback_url !== '' && strpos($fallback_url, '#') !== 0) {
        $resolved = (int) url_to_postid($fallback_url);
        if ($resolved > 0 && get_post_type($resolved) === 'fuehrung') {
            return $resolved;
        }
    }

    $candidates = iss_calendar_map_entry_title_candidates($entry);
    return iss_calendar_match_fuehrung_by_title_candidates($candidates);
}

function iss_calendar_find_linked_source_by_series_key($series_key) {
    $series_key = trim((string) $series_key);
    if ($series_key === '') {
        return ['source_post_id' => 0, 'source_post_type' => ''];
    }

    if (function_exists('iss_calendar_resolve_source_by_series_key')) {
        $resolved = iss_calendar_resolve_source_by_series_key($series_key);
        if (is_array($resolved) && !empty($resolved['source_post_id'])) {
            return [
                'source_post_id' => (int) ($resolved['source_post_id'] ?? 0),
                'source_post_type' => sanitize_key((string) ($resolved['source_post_type'] ?? '')),
            ];
        }
    }

    static $cache = [];
    if (array_key_exists($series_key, $cache)) {
        return $cache[$series_key];
    }

    $q = new WP_Query([
        'post_type' => ISS_CALENDAR_ITEM_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => 'series_key',
                'value' => $series_key,
                'compare' => '=',
            ],
            [
                'key' => 'source_post_id',
                'value' => 0,
                'compare' => '>',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    if (empty($q->posts)) {
        $cache[$series_key] = ['source_post_id' => 0, 'source_post_type' => ''];
        return $cache[$series_key];
    }

    $item_id = (int) $q->posts[0];
    $cache[$series_key] = [
        'source_post_id' => (int) get_post_meta($item_id, 'source_post_id', true),
        'source_post_type' => sanitize_key((string) get_post_meta($item_id, 'source_post_type', true)),
    ];

    return $cache[$series_key];
}

/**
 * Sync SuperSaaS slots into the local CPT for fallback and internal use.
 *
 * @return array{created:int,updated:int,errors:int,imported_unmapped:int,preserved_title:int,preserved_description:int,error_message:string}
 */
function iss_calendar_sync_supersaas_to_cpt() {
    $settings = function_exists('is_saas_get_settings') ? is_saas_get_settings() : [];
    $slot_items = iss_calendar_supersaas_fetch_free_slots($settings);
    if (is_wp_error($slot_items)) {
        return [
            'created' => 0,
            'updated' => 0,
            'errors' => 1,
            'imported_unmapped' => 0,
            'preserved_title' => 0,
            'preserved_description' => 0,
            'error_message' => (string) $slot_items->get_error_message(),
        ];
    }

    $source_calendar = function_exists('is_saas_get_schedule_path')
        ? (is_saas_get_schedule_path($settings) ?: (string) ($settings['schedule_id'] ?? ''))
        : (string) ($settings['schedule_id'] ?? '');
    $source_calendar = sanitize_text_field((string) $source_calendar);

    $schedule_url = iss_calendar_build_schedule_url($settings);
    $map = function_exists('iss_calendar_get_source_map') ? iss_calendar_get_source_map() : [];
    $series_map = function_exists('iss_calendar_get_series_map') ? iss_calendar_get_series_map() : [];
    $mapped_tags = array_keys($map);
    $mapped_tags = array_filter(array_map(function ($t) { return strtoupper(sanitize_text_field((string) $t)); }, $mapped_tags));

    $title_index = [];
    foreach ($map as $map_tag => $entry) {
        if (!is_array($entry)) continue;
        $map_tag_norm = strtoupper(sanitize_text_field((string) $map_tag));
        $candidates = iss_calendar_map_entry_title_candidates($entry);
        foreach ($candidates as $candidate) {
            $title_index[$candidate] = $map_tag_norm;
        }
    }

    $now = current_time('mysql');
    $created = 0;
    $updated = 0;
    $errors = 0;
    $imported_unmapped = 0;
    $preserved_title = 0;
    $preserved_description = 0;
    $slots_by_tag = [];

    foreach ($slot_items as $slot) {
        if (!is_array($slot)) continue;

        $external_id = isset($slot['id']) ? trim((string) $slot['id']) : '';
        if ($external_id === '') continue;

        $raw_title = isset($slot['title']) ? (string) $slot['title'] : '';
        $parsed = iss_calendar_parse_supersaas_title($raw_title);
        $tag = iss_calendar_extract_supersaas_slot_tag($slot);
        if ($tag === '' && !empty($title_index)) {
            $ct = iss_calendar_clean_supersaas_title($raw_title);
            if ($ct !== '' && isset($title_index[$ct])) {
                $tag = $title_index[$ct];
            }
        }

        $is_mapped_tag = ($tag !== '' && in_array($tag, $mapped_tags, true));

        $clean_title = isset($parsed['title']) ? (string) $parsed['title'] : '';
        if ($clean_title === '') {
            $clean_title = trim((string) $raw_title);
        }
        $series_key = function_exists('iss_calendar_build_series_key')
            ? iss_calendar_build_series_key($clean_title, 'tour')
            : '';

        $start = isset($slot['start']) ? trim((string) $slot['start']) : '';
        if ($start === '') continue;

        $end = isset($slot['end']) ? trim((string) $slot['end']) : (isset($slot['finish']) ? trim((string) $slot['finish']) : '');
        if ($end === '') {
            $end = null;
        }

        $capacity_total = isset($slot['capacity']) ? (int) $slot['capacity'] : null;
        $available = null;
        if (isset($slot['available'])) {
            $available = (int) $slot['available'];
        } elseif (isset($slot['remaining'])) {
            $available = (int) $slot['remaining'];
        } elseif (isset($slot['count'])) {
            $available = (int) $slot['count'];
        }

        $availability_state = 'inquiry';
        if ($available !== null) {
            $availability_state = $available > 0 ? 'available' : 'sold_out';
        }

        if (!$is_mapped_tag) {
            $imported_unmapped++;
        }

        $map_entry = ($is_mapped_tag && isset($map[$tag]) && is_array($map[$tag])) ? $map[$tag] : [];
        $source_post_id = isset($map_entry['source_post_id']) ? (int) $map_entry['source_post_id'] : 0;
        $source_post_type = isset($map_entry['source_post_type']) ? sanitize_key((string) $map_entry['source_post_type']) : '';
        $fallback_url = isset($map_entry['fallback_url']) ? esc_url_raw((string) $map_entry['fallback_url']) : '';

        $series_entry = ($series_key !== '' && isset($series_map[$series_key]) && is_array($series_map[$series_key]))
            ? $series_map[$series_key]
            : [];
        if ($source_post_id <= 0 && !empty($series_entry['source_post_id'])) {
            $resolved_series_post_id = (int) $series_entry['source_post_id'];
            if ($resolved_series_post_id > 0 && get_post($resolved_series_post_id) instanceof WP_Post) {
                $source_post_id = $resolved_series_post_id;
                $source_post_type = sanitize_key((string) ($series_entry['source_post_type'] ?? get_post_type($resolved_series_post_id)));
            }
        }
        if ($fallback_url === '' && !empty($series_entry['fallback_url'])) {
            $fallback_url = esc_url_raw((string) $series_entry['fallback_url']);
        }

        if ($source_post_id <= 0 && $is_mapped_tag) {
            $resolved_from_map = iss_calendar_try_resolve_source_post_from_map_entry($map_entry);
            if ($resolved_from_map > 0) {
                $source_post_id = $resolved_from_map;
                $source_post_type = sanitize_key((string) get_post_type($source_post_id));
            }
        }

        $post_id = iss_calendar_find_item_post_id($external_id, $source_calendar);
        if ($source_post_id <= 0 && $post_id) {
            $existing_source_post_id = (int) get_post_meta($post_id, 'source_post_id', true);
            $existing_source_post_type = sanitize_key((string) get_post_meta($post_id, 'source_post_type', true));
            if ($existing_source_post_id > 0) {
                $source_post_id = $existing_source_post_id;
                $source_post_type = $existing_source_post_type;
            }
        }

        if ($source_post_id <= 0 && $series_key !== '') {
            $inferred = iss_calendar_find_linked_source_by_series_key($series_key);
            if (!empty($inferred['source_post_id'])) {
                $source_post_id = (int) $inferred['source_post_id'];
                $source_post_type = sanitize_key((string) ($inferred['source_post_type'] ?? ''));
            }
        }

        if ($source_post_id > 0 && $source_post_type === '') {
            $source_post_type = sanitize_key((string) get_post_type($source_post_id));
        }

        if ($source_post_id > 0 && $is_mapped_tag && function_exists('iss_calendar_remember_source_mapping')) {
            iss_calendar_remember_source_mapping($tag, $fallback_url, $source_post_id, $source_post_type);

            $map[$tag] = isset($map[$tag]) && is_array($map[$tag]) ? $map[$tag] : [];
            $map[$tag]['source_post_id'] = $source_post_id;
            $map[$tag]['source_post_type'] = $source_post_type;
            if (!isset($map[$tag]['fallback_url'])) {
                $map[$tag]['fallback_url'] = $fallback_url;
            }
        }

        if ($series_key !== '' && function_exists('iss_calendar_remember_series_mapping')) {
            iss_calendar_remember_series_mapping($series_key, $source_post_id, $source_post_type, $clean_title, $tag, $fallback_url);

            $series_map[$series_key] = isset($series_map[$series_key]) && is_array($series_map[$series_key]) ? $series_map[$series_key] : [];
            if (!isset($series_map[$series_key]['source_post_id']) || (int) $series_map[$series_key]['source_post_id'] <= 0) {
                $series_map[$series_key]['source_post_id'] = $source_post_id;
            } elseif ($source_post_id > 0) {
                $series_map[$series_key]['source_post_id'] = $source_post_id;
            }
            if (!isset($series_map[$series_key]['source_post_type']) || trim((string) $series_map[$series_key]['source_post_type']) === '') {
                $series_map[$series_key]['source_post_type'] = $source_post_type;
            } elseif ($source_post_type !== '') {
                $series_map[$series_key]['source_post_type'] = $source_post_type;
            }
            if (trim((string) ($series_map[$series_key]['supersaas_title'] ?? '')) === '' && $clean_title !== '') {
                $series_map[$series_key]['supersaas_title'] = $clean_title;
            }
            if (trim((string) ($series_map[$series_key]['tag'] ?? '')) === '' && $tag !== '') {
                $series_map[$series_key]['tag'] = $tag;
            }
            if (trim((string) ($series_map[$series_key]['fallback_url'] ?? '')) === '' && $fallback_url !== '') {
                $series_map[$series_key]['fallback_url'] = $fallback_url;
            }
            $series_map[$series_key]['version'] = defined('ISS_CALENDAR_SERIES_MAP_VERSION')
                ? (int) ISS_CALENDAR_SERIES_MAP_VERSION
                : 1;
            $series_map[$series_key]['last_seen_at'] = $now;
        }

        $booking_url = $fallback_url ?: $schedule_url;
        $incoming_title = $clean_title !== '' ? wp_strip_all_tags($clean_title) : 'Calendar Item';
        $incoming_description = '';
        if (!empty($slot['description'])) {
            $incoming_description = sanitize_textarea_field((string) $slot['description']);
        } elseif (!empty($slot['details'])) {
            $incoming_description = sanitize_textarea_field((string) $slot['details']);
        } elseif (!empty($slot['comment'])) {
            $incoming_description = sanitize_textarea_field((string) $slot['comment']);
        } elseif (!empty($slot['note'])) {
            $incoming_description = sanitize_textarea_field((string) $slot['note']);
        } elseif (!empty($slot['notes'])) {
            $incoming_description = sanitize_textarea_field((string) $slot['notes']);
        }

        $postarr = [
            'post_type' => ISS_CALENDAR_ITEM_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $incoming_title,
            'post_content' => $incoming_description,
        ];

        if ($post_id) {
            $existing = get_post($post_id);
            if (!($existing instanceof WP_Post)) {
                $errors++;
                continue;
            }

            $post_update = ['ID' => $post_id];
            $needs_post_update = false;
            $existing_title = trim((string) $existing->post_title);
            if ($existing_title === '' && $incoming_title !== '') {
                $post_update['post_title'] = $incoming_title;
                $needs_post_update = true;
            } else {
                $preserved_title++;
            }

            $existing_content = trim((string) $existing->post_content);
            if ($existing_content === '' && $incoming_description !== '') {
                $post_update['post_content'] = $incoming_description;
                $needs_post_update = true;
            } elseif ($existing_content !== '') {
                $preserved_description++;
            }

            if ($needs_post_update) {
                $res = wp_update_post($post_update, true);
                if (is_wp_error($res)) {
                    $errors++;
                    continue;
                }
            }

            $updated++;
        } else {
            $res = wp_insert_post($postarr, true);
            if (is_wp_error($res) || !$res) {
                $errors++;
                continue;
            }
            $post_id = (int) $res;
            $created++;
        }

        update_post_meta($post_id, 'event_start', $start);
        update_post_meta($post_id, 'event_end', $end);
        update_post_meta($post_id, 'item_type', 'tour');
        update_post_meta($post_id, 'supersaas_title', $clean_title);
        if ($series_key !== '') {
            update_post_meta($post_id, 'series_key', $series_key);
        }
        update_post_meta($post_id, 'source_system', 'supersaas');
        update_post_meta($post_id, 'source_calendar', $source_calendar);
        update_post_meta($post_id, 'external_id', $external_id);
        update_post_meta($post_id, 'source_post_id', $source_post_id);
        update_post_meta($post_id, 'source_post_type', $source_post_type);
        update_post_meta($post_id, 'booking_url', $booking_url);
        if (!empty($slot['location'])) {
            update_post_meta($post_id, 'location', sanitize_text_field((string) $slot['location']));
        }
        update_post_meta($post_id, 'availability_state', $availability_state);
        if ($capacity_total !== null) update_post_meta($post_id, 'capacity_total', (int) $capacity_total);
        if ($available !== null) update_post_meta($post_id, 'capacity_available', (int) $available);
        update_post_meta($post_id, 'is_public', 1);
        update_post_meta($post_id, 'sync_status', 'ok');
        update_post_meta($post_id, 'last_synced_at', $now);
        update_post_meta($post_id, 'last_seen_at', $now);
        update_post_meta($post_id, 'origin_mode', 'supersaas');
        update_post_meta($post_id, 'sort_date', $start);

        // Prime the REST/tag cache from the same normalized shape as the REST endpoint.
        if ($tag !== '' && !isset($slots_by_tag[$tag])) {
            $slots_by_tag[$tag] = [];
        }
        if ($tag !== '') {
            $slots_by_tag[$tag][] = [
                'id' => (string) $external_id,
                'title' => $clean_title !== '' ? $clean_title : $raw_title,
                'start' => $start,
                'end' => $end,
                'capacity' => $capacity_total !== null ? (int) $capacity_total : null,
                'available' => $available,
                'booking_url' => $booking_url ? (string) $booking_url : null,
            ];
        }
    }

    // Keep the REST endpoint cache and the CPT in sync.
    foreach ($slots_by_tag as $tag => $slots) {
        if (!is_array($slots)) continue;

        usort($slots, function ($a, $b) {
            return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
        });

        if (function_exists('is_tours_set_cached_slots_by_tag')) {
            is_tours_set_cached_slots_by_tag($tag, $slots, 60 * 60 * 6);
            if (function_exists('is_tours_set_cached_source_by_tag')) {
                is_tours_set_cached_source_by_tag($tag, 'cpt', 60 * 60 * 6);
            }
        } else {
            set_transient('is_tours_slots_' . md5($tag), $slots, 60 * 60 * 6);
            set_transient('is_tours_slots_src_' . md5($tag), 'cpt', 60 * 60 * 6);
        }
    }

    update_option(ISS_CALENDAR_LAST_SYNC_OPTION, $now, false);

    return [
        'created' => $created,
        'updated' => $updated,
        'errors' => $errors,
        'imported_unmapped' => $imported_unmapped,
        'preserved_title' => $preserved_title,
        'preserved_description' => $preserved_description,
        'error_message' => '',
    ];
}

function iss_calendar_build_schedule_url($settings) {
    if (!is_array($settings)) return '';

    $base_url = isset($settings['base_url']) ? untrailingslashit((string) $settings['base_url']) : '';
    $account_name = isset($settings['account_name']) ? (string) $settings['account_name'] : '';
    $schedule_path = function_exists('is_saas_get_schedule_path') ? is_saas_get_schedule_path($settings) : '';
    $schedule_path = function_exists('is_saas_normalize_schedule_path') ? is_saas_normalize_schedule_path($schedule_path) : '';

    if ($base_url === '' || $account_name === '' || $schedule_path === '') {
        return '';
    }

    return $base_url . '/schedule/' . rawurlencode($account_name) . '/' . ltrim($schedule_path, '/');
}

function iss_calendar_find_item_post_id($external_id, $source_calendar) {
    global $wpdb;

    $external_id = (string) $external_id;
    $source_calendar = (string) $source_calendar;
    if ($external_id === '' || $source_calendar === '') return 0;

    $sql = "
        SELECT p.ID
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} m1
            ON m1.post_id = p.ID AND m1.meta_key = 'external_id' AND m1.meta_value = %s
        INNER JOIN {$wpdb->postmeta} m2
            ON m2.post_id = p.ID AND m2.meta_key = 'source_calendar' AND m2.meta_value = %s
        WHERE p.post_type = %s
        LIMIT 1
    ";

    $found = $wpdb->get_var($wpdb->prepare($sql, $external_id, $source_calendar, ISS_CALENDAR_ITEM_POST_TYPE));
    return $found ? (int) $found : 0;
}
