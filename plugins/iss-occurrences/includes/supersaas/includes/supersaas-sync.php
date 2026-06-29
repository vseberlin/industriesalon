<?php

if (!defined('ABSPATH')) exit;

define('ISS_SUPERSAAS_SYNC_HOOK', 'iss_supersaas_occurrence_sync');
define('ISS_SUPERSAAS_LAST_SYNC_OPTION', 'iss_supersaas_last_sync_at');

add_action(ISS_SUPERSAAS_SYNC_HOOK, function () {
    iss_supersaas_sync_occurrences();
});

function iss_supersaas_activate_sync() {
    if (!wp_next_scheduled(ISS_SUPERSAAS_SYNC_HOOK)) {
        wp_schedule_event(time() + 60, 'hourly', ISS_SUPERSAAS_SYNC_HOOK);
    }
}
add_action('init', 'iss_supersaas_activate_sync', 20);

function iss_supersaas_deactivate_sync() {
    $ts = wp_next_scheduled(ISS_SUPERSAAS_SYNC_HOOK);
    if ($ts) {
        wp_unschedule_event($ts, ISS_SUPERSAAS_SYNC_HOOK);
    }
}

/**
 * Fetch raw "free slots" from SuperSaaS.
 *
 * @return array|WP_Error
 */
function iss_supersaas_fetch_free_slots($settings = null) {
    if ($settings === null) {
        $settings = function_exists('iss_supersaas_get_settings') ? iss_supersaas_get_settings() : [];
    }

    if (!is_array($settings)) {
        return new WP_Error('iss_supersaas_settings', 'Invalid settings.');
    }

    $schedule_id = isset($settings['schedule_id']) ? (string) $settings['schedule_id'] : '';
    $api_key = isset($settings['api_key']) ? (string) $settings['api_key'] : '';
    $account_name = isset($settings['account_name']) ? (string) $settings['account_name'] : '';
    $base_url = isset($settings['base_url']) ? (string) $settings['base_url'] : '';

    if ($schedule_id === '' || $api_key === '' || $account_name === '' || $base_url === '') {
        return new WP_Error('iss_supersaas_config', 'Missing SuperSaaS configuration.');
    }

    $future_months = (int) apply_filters('iss_supersaas_sync_future_months', 6);
    if ($future_months < 1) {
        $future_months = 1;
    }

    $max_results = (int) apply_filters('iss_supersaas_sync_max_results', 200);
    if ($max_results < 10) {
        $max_results = 10;
    }

    $include_full = (bool) apply_filters('iss_supersaas_sync_include_full_slots', true);
    $cache_key = 'iss_supersaas_free_' . md5($base_url . '|' . $account_name . '|' . $schedule_id . '|m:' . $future_months . '|n:' . $max_results . '|full:' . ($include_full ? '1' : '0'));
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $base_url = untrailingslashit($base_url);
    $tz = wp_timezone();
    $from_dt = new DateTimeImmutable('now', $tz);
    $to_dt = $from_dt->modify('+' . $future_months . ' months');
    $from = $from_dt->format('Y-m-d H:i:s');
    $to = $to_dt->format('Y-m-d H:i:s');
    $query_args = [
        'from' => $from,
        'to' => $to,
        'maxresults' => (string) $max_results,
    ];
    if ($include_full) {
        $query_args['full'] = 'true';
    }
    $url = add_query_arg($query_args, $base_url . '/api/free/' . rawurlencode($schedule_id) . '.json');

    $response = wp_remote_get($url, [
        'timeout' => 20,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode($account_name . ':' . $api_key),
        ],
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('iss_supersaas_fetch', $response->get_error_message());
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return new WP_Error('iss_supersaas_upstream', 'Upstream request failed with status ' . $code . '.');
    }

    $body = (string) wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    $slot_items = isset($data['slots']) && is_array($data['slots']) ? $data['slots'] : $data;

    if (!is_array($slot_items)) {
        return new WP_Error('iss_supersaas_parse', 'Invalid API response.');
    }

    set_transient($cache_key, $slot_items, 60 * 5);
    return $slot_items;
}

/**
 * Parse a SuperSaaS slot title like: "[TAG] Public Title".
 *
 * @return array{tag:string,title:string,raw_title:string}
 */
function iss_supersaas_parse_title($raw_title) {
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

function iss_supersaas_clean_title($raw_title) {
    $raw_title = trim((string) $raw_title);
    if ($raw_title === '') return '';

    $parsed = iss_supersaas_parse_title($raw_title);
    $title = isset($parsed['title']) ? (string) $parsed['title'] : '';
    return $title !== '' ? $title : $raw_title;
}

function iss_supersaas_generate_tag_from_title($title) {
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

function iss_supersaas_extract_tag_from_text($text) {
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

function iss_supersaas_extract_slot_tag($slot) {
    if (!is_array($slot)) return '';

    // Preferred: keep public title clean, store tag in description.
    $desc = isset($slot['description']) ? (string) $slot['description'] : '';
    $tag = iss_supersaas_extract_tag_from_text($desc);
    if ($tag !== '') return $tag;

    // Older SuperSaaS entries may carry a "[TAG] Title" prefix.
    $raw_title = isset($slot['title']) ? (string) $slot['title'] : '';
    $parsed = iss_supersaas_parse_title($raw_title);
    $tag = isset($parsed['tag']) ? strtoupper((string) $parsed['tag']) : '';
    if ($tag !== '') return $tag;

    // Optional: allow tagging via location only if it matches our tag pattern.
    $loc = isset($slot['location']) ? (string) $slot['location'] : '';
    $tag = iss_supersaas_extract_tag_from_text($loc);
    if ($tag !== '') return $tag;

    return '';
}

function iss_supersaas_slot_is_cancelled($slot, string $clean_title = ''): bool {
    if (!is_array($slot)) {
        return false;
    }

    $texts = [
        $clean_title,
        isset($slot['title']) ? (string) $slot['title'] : '',
    ];
    $description = isset($slot['description']) ? trim((string) $slot['description']) : '';
    if ($description !== '') {
        $texts[] = strtok($description, "\r\n") ?: $description;
    }

    foreach ($texts as $text) {
        $text = trim((string) $text);
        if ($text === '') {
            continue;
        }

        if (preg_match('/^(ausfall|abgesagt|storniert|cancelled|canceled)(\\b|\\s|:|-)/iu', $text)) {
            return true;
        }
    }

    return false;
}

/**
 * Normalize SuperSaaS free slots for a given (clean) title to the REST "slots" shape.
 *
 * @param string $title
 * @param array|null $settings
 * @return array<int,array<string,mixed>>|WP_Error
 */
function iss_supersaas_get_slots_for_title($title, $settings = null) {
    $title = trim((string) $title);
    if ($title === '') {
        return [];
    }

    $slot_items = iss_supersaas_fetch_free_slots($settings);
    if (is_wp_error($slot_items)) {
        return $slot_items;
    }

    $slots = [];

    foreach ($slot_items as $slot) {
        if (!is_array($slot)) continue;

        $raw_title = isset($slot['title']) ? (string) $slot['title'] : '';
        $clean_title = iss_supersaas_clean_title($raw_title);
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
function iss_supersaas_get_slots_for_tag($tag, $settings = null) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') {
        return [];
    }

    $slot_items = iss_supersaas_fetch_free_slots($settings);
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

        $slot_tag = iss_supersaas_extract_slot_tag($slot);
        if ($slot_tag === '' || $slot_tag !== $tag) {
            continue;
        }

        $parsed = iss_supersaas_parse_title($raw_title);
        $clean_title = !empty($parsed['title']) ? (string) $parsed['title'] : $raw_title;

        $start = $slot['start'] ?? null;
        if (!$start) {
            continue;
        }

        if (function_exists('iss_supersaas_build_slot_response')) {
            $built = iss_supersaas_build_slot_response($slot, $clean_title, $start);
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

function iss_supersaas_source_entry_title_candidates($entry) {
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
        $clean = function_exists('iss_supersaas_clean_title')
            ? iss_supersaas_clean_title($candidate)
            : $candidate;
        $clean = trim((string) $clean);
        if ($clean !== '') {
            $cleaned[] = $clean;
        }
    }

    return array_values(array_unique($cleaned));
}

function iss_supersaas_normalize_match_text($value): string {
    $value = html_entity_decode(wp_strip_all_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $value = function_exists('remove_accents') ? remove_accents($value) : $value;
    $value = strtolower((string) $value);
    $value = preg_replace('/[^a-z0-9]+/', ' ', $value);
    return trim((string) $value);
}

function iss_supersaas_match_tokens($value): array {
    $normalized = iss_supersaas_normalize_match_text($value);
    if ($normalized === '') {
        return [];
    }

    $stopwords = [
        'aber' => true,
        'alle' => true,
        'auch' => true,
        'danach' => true,
        'dass' => true,
        'denn' => true,
        'fuer' => true,
        'kann' => true,
        'kaffee' => true,
        'kekse' => true,
        'mit' => true,
        'oder' => true,
        'salon' => true,
        'und' => true,
        'uhr' => true,
    ];

    $tokens = [];
    foreach (preg_split('/\s+/', $normalized) as $token) {
        $token = trim((string) $token);
        if ($token === '' || strlen($token) < 4 || isset($stopwords[$token])) {
            continue;
        }
        $tokens[$token] = true;
    }

    return array_keys($tokens);
}

function iss_supersaas_match_mapped_series_from_slot_text(array $slot, array $series_sources): array {
    if (empty($series_sources)) {
        return [];
    }

    $description = isset($slot['description']) ? (string) $slot['description'] : '';
    $location = isset($slot['location']) ? (string) $slot['location'] : '';
    $raw_title = isset($slot['title']) ? (string) $slot['title'] : '';
    $slot_text = trim($raw_title . "\n" . $description . "\n" . $location);
    $slot_tokens = iss_supersaas_match_tokens($slot_text);
    if (empty($slot_tokens)) {
        return [];
    }

    $slot_token_lookup = array_fill_keys($slot_tokens, true);
    $best = [];
    $best_score = 0;
    $ambiguous = false;

    foreach ($series_sources as $series_key => $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $source_post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;
        $source_post_type = isset($entry['source_post_type']) ? sanitize_key((string) $entry['source_post_type']) : '';
        if ($source_post_id <= 0 || $source_post_type !== 'fuehrung') {
            continue;
        }

        $candidate_tokens = [];
        foreach (iss_supersaas_source_entry_title_candidates($entry) as $candidate) {
            foreach (iss_supersaas_match_tokens($candidate) as $token) {
                $candidate_tokens[$token] = true;
            }
        }
        if (empty($candidate_tokens)) {
            continue;
        }

        $common = array_intersect_key($candidate_tokens, $slot_token_lookup);
        $common_count = count($common);
        if ($common_count <= 0) {
            continue;
        }

        $candidate_count = count($candidate_tokens);
        $score = ($common_count * 10) + (int) floor(($common_count / max(1, $candidate_count)) * 10);
        if ($common_count < 2 && $score < 18) {
            continue;
        }

        if ($score > $best_score) {
            $best_score = $score;
            $best = $entry;
            $best['series_key'] = (string) $series_key;
            $ambiguous = false;
        } elseif ($score === $best_score && $best_score > 0) {
            $best_source = isset($best['source_post_id']) ? (int) $best['source_post_id'] : 0;
            if ($best_source !== $source_post_id) {
                $ambiguous = true;
            }
        }
    }

    if ($ambiguous || $best_score <= 0) {
        return [];
    }

    return $best;
}

function iss_supersaas_match_fuehrung_by_title_candidates($candidates) {
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

function iss_supersaas_try_resolve_source_post_from_source_entry($entry) {
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

    $candidates = iss_supersaas_source_entry_title_candidates($entry);
    return iss_supersaas_match_fuehrung_by_title_candidates($candidates);
}

function iss_supersaas_find_linked_source_by_series_key($series_key) {
    $series_key = trim((string) $series_key);
    if ($series_key === '') {
        return ['source_post_id' => 0, 'source_post_type' => ''];
    }

    if (function_exists('iss_occurrences_resolve_source_by_series_key')) {
        $resolved = iss_occurrences_resolve_source_by_series_key($series_key);
        if (is_array($resolved) && !empty($resolved['source_post_id'])) {
            return [
                'source_post_id' => (int) ($resolved['source_post_id'] ?? 0),
                'source_post_type' => sanitize_key((string) ($resolved['source_post_type'] ?? '')),
            ];
        }
    }

    return ['source_post_id' => 0, 'source_post_type' => ''];
}

function iss_supersaas_reconcile_exact_title_series_sources() {
    if (!function_exists('iss_occurrences_get_service')
        || !function_exists('iss_occurrences_remember_series_source')
        || !function_exists('iss_supersaas_match_fuehrung_by_title_candidates')
    ) {
        return 0;
    }

    global $wpdb;

    $service = iss_occurrences_get_service();
    $service->maybe_install_schema();
    if (!$service->tables_exist()) {
        return 0;
    }

    $series_table = $service->get_series_table_name();
    $occurrences_table = $service->get_occurrences_table_name();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT series_key, supersaas_title, tag, fallback_url, source_post_id
            FROM {$series_table}
            WHERE origin = %s
              AND supersaas_title <> ''
              AND tag = ''",
            'supersaas'
        ),
        ARRAY_A
    );

    $updated = 0;
    foreach ((array) $rows as $row) {
        $series_key = trim((string) ($row['series_key'] ?? ''));
        $title = trim((string) ($row['supersaas_title'] ?? ''));
        if ($series_key === '' || $title === '') {
            continue;
        }

        $matched_post_id = iss_supersaas_match_fuehrung_by_title_candidates([$title]);
        if ($matched_post_id <= 0 || (int) ($row['source_post_id'] ?? 0) === $matched_post_id) {
            continue;
        }

        $matched_post = get_post($matched_post_id);
        if (!$matched_post instanceof WP_Post || $matched_post->post_type !== 'fuehrung' || $matched_post->post_status !== 'publish') {
            continue;
        }

        $fallback_url = isset($row['fallback_url']) ? esc_url_raw((string) $row['fallback_url']) : '';
        if (!iss_occurrences_remember_series_source($series_key, $matched_post_id, 'fuehrung', $title, '', $fallback_url)) {
            continue;
        }

        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$occurrences_table}
                SET source_post_id = %d, source_post_type = %s, updated_at = %s
                WHERE origin = %s
                  AND series_key = %s
                  AND source_post_id <> %d",
                $matched_post_id,
                'fuehrung',
                current_time('mysql'),
                'supersaas',
                $series_key,
                $matched_post_id
            )
        );
        $updated++;
    }

    if ($updated > 0) {
        do_action('iss_occurrences_changed', ['origin' => 'supersaas', 'reconciled_series' => $updated]);
    }

    return $updated;
}

function iss_supersaas_clear_non_fuehrung_series_sources() {
    if (!function_exists('iss_occurrences_get_service')
        || !function_exists('iss_occurrences_clear_series_source_for_key')
    ) {
        return 0;
    }

    global $wpdb;

    $service = iss_occurrences_get_service();
    $service->maybe_install_schema();
    if (!$service->tables_exist()) {
        return 0;
    }

    $series_table = $service->get_series_table_name();
    $occurrences_table = $service->get_occurrences_table_name();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT s.series_key, COUNT(o.id) AS occurrence_rows
            FROM {$series_table} s
            LEFT JOIN {$wpdb->posts} p ON p.ID = s.source_post_id
            LEFT JOIN {$occurrences_table} o ON o.origin = s.origin AND o.series_key = s.series_key
            WHERE s.origin = %s
              AND s.source_post_id > 0
              AND (
                  p.ID IS NULL
                  OR p.post_type <> %s
                  OR (s.source_post_type <> '' AND s.source_post_type <> %s)
              )
            GROUP BY s.series_key
            HAVING occurrence_rows = 0",
            'supersaas',
            'fuehrung',
            'fuehrung'
        ),
        ARRAY_A
    );

    $cleared = 0;
    foreach ((array) $rows as $row) {
        $series_key = trim((string) ($row['series_key'] ?? ''));
        if ($series_key === '') {
            continue;
        }

        if (iss_occurrences_clear_series_source_for_key($series_key)) {
            $cleared++;
        }
    }

    if ($cleared > 0) {
        do_action('iss_occurrences_changed', ['origin' => 'supersaas', 'cleared_non_fuehrung_series' => $cleared]);
    }

    return $cleared;
}

function iss_supersaas_prune_empty_unlinked_series(): int {
    if (!function_exists('iss_occurrences_get_service')) {
        return 0;
    }

    global $wpdb;

    $service = iss_occurrences_get_service();
    if (!method_exists($service, 'tables_exist') || !$service->tables_exist()) {
        return 0;
    }

    $series_table = $service->get_series_table_name();
    $occurrences_table = $service->get_occurrences_table_name();
    $deleted = (int) $wpdb->query(
        $wpdb->prepare(
            "DELETE s
            FROM {$series_table} s
            LEFT JOIN {$occurrences_table} o ON o.origin = s.origin AND o.series_key = s.series_key
            WHERE s.origin = %s
              AND o.id IS NULL
              AND (
                  (
                      s.source_post_id = 0
                      AND (
                          s.series_key IN ('tour:', 'tour')
                          OR s.supersaas_title = ''
                      )
                  )
                  OR LOWER(s.supersaas_title) REGEXP '^[[:space:]]*(ausfall|abgesagt|storniert|cancelled|canceled)([[:space:]]|:|-|$)'
              )",
            'supersaas'
        )
    );

    if ($deleted > 0) {
        do_action('iss_occurrences_changed', ['origin' => 'supersaas', 'pruned_empty_series' => $deleted]);
    }

    return $deleted;
}

/**
 * Sync SuperSaaS slots into the occurrence projection.
 *
 * @return array{created:int,updated:int,errors:int,imported_unmapped:int,skipped_unlinked:int,inactivated:int,purged_inactive:int,past_reactivated:int,metadata_backfilled:int,source_reconciled:int,source_cleared:int,series_pruned:int,error_message:string}
 */
function iss_supersaas_sync_occurrences() {
    if (!function_exists('iss_occurrences_get_service')) {
        return [
            'created' => 0,
            'updated' => 0,
            'errors' => 1,
            'imported_unmapped' => 0,
            'skipped_unlinked' => 0,
            'inactivated' => 0,
            'purged_inactive' => 0,
            'past_reactivated' => 0,
            'metadata_backfilled' => 0,
            'source_reconciled' => 0,
            'source_cleared' => 0,
            'series_pruned' => 0,
            'error_message' => 'ISS Occurrences is unavailable.',
        ];
    }

    $service = iss_occurrences_get_service();
    $service->maybe_install_schema();

    $settings = function_exists('iss_supersaas_get_settings') ? iss_supersaas_get_settings() : [];
    $slot_items = iss_supersaas_fetch_free_slots($settings);
    if (is_wp_error($slot_items)) {
        return [
            'created' => 0,
            'updated' => 0,
            'errors' => 1,
            'imported_unmapped' => 0,
            'skipped_unlinked' => 0,
            'inactivated' => 0,
            'purged_inactive' => 0,
            'past_reactivated' => 0,
            'metadata_backfilled' => 0,
            'source_reconciled' => 0,
            'source_cleared' => 0,
            'series_pruned' => 0,
            'error_message' => (string) $slot_items->get_error_message(),
        ];
    }

    $source_calendar = function_exists('iss_supersaas_get_schedule_path')
        ? (iss_supersaas_get_schedule_path($settings) ?: (string) ($settings['schedule_id'] ?? ''))
        : (string) ($settings['schedule_id'] ?? '');
    $source_calendar = sanitize_text_field((string) $source_calendar);
    $metadata_backfilled = method_exists($service, 'backfill_supersaas_metadata')
        ? $service->backfill_supersaas_metadata($source_calendar)
        : 0;
    $source_reconciled = iss_supersaas_reconcile_exact_title_series_sources();
    $source_cleared = iss_supersaas_clear_non_fuehrung_series_sources();
    $series_pruned = iss_supersaas_prune_empty_unlinked_series();

    $schedule_url = iss_supersaas_build_schedule_url($settings);
    $tag_sources = function_exists('iss_occurrences_get_tag_sources') ? iss_occurrences_get_tag_sources() : [];
    $series_sources = function_exists('iss_occurrences_get_series_sources') ? iss_occurrences_get_series_sources() : [];
    $known_tags = array_keys($tag_sources);
    $known_tags = array_filter(array_map(function ($t) { return strtoupper(sanitize_text_field((string) $t)); }, $known_tags));

    $title_index = [];
    foreach ($tag_sources as $source_tag => $entry) {
        if (!is_array($entry)) continue;
        $source_tag_norm = strtoupper(sanitize_text_field((string) $source_tag));
        $candidates = iss_supersaas_source_entry_title_candidates($entry);
        foreach ($candidates as $candidate) {
            $title_index[$candidate] = $source_tag_norm;
        }
    }

    $now = current_time('mysql');
    $created = 0;
    $updated = 0;
    $errors = 0;
    $imported_unmapped = 0;
    $skipped_unlinked = 0;
    $seen_external_ids = [];
    $slots_by_tag = [];

    foreach ($slot_items as $slot) {
        if (!is_array($slot)) continue;

        $external_id = isset($slot['id']) ? trim((string) $slot['id']) : '';
        if ($external_id === '') continue;
        $seen_external_ids[] = $external_id;

        $raw_title = isset($slot['title']) ? (string) $slot['title'] : '';
        $parsed = iss_supersaas_parse_title($raw_title);
        $tag = iss_supersaas_extract_slot_tag($slot);
        if ($tag === '' && !empty($title_index)) {
            $ct = iss_supersaas_clean_title($raw_title);
            if ($ct !== '' && isset($title_index[$ct])) {
                $tag = $title_index[$ct];
            }
        }

        $is_known_tag = ($tag !== '' && in_array($tag, $known_tags, true));

        $clean_title = isset($parsed['title']) ? trim((string) $parsed['title']) : '';
        if ($clean_title === '') {
            $clean_title = trim((string) $raw_title);
        }
        $series_key = ($clean_title !== '' && function_exists('iss_occurrences_build_series_key'))
            ? iss_occurrences_build_series_key($clean_title, 'tour')
            : '';
        $series_entry = ($series_key !== '' && isset($series_sources[$series_key]) && is_array($series_sources[$series_key]))
            ? $series_sources[$series_key]
            : [];
        if ($clean_title === '' || empty($series_entry['source_post_id'])) {
            $matched_series = iss_supersaas_match_mapped_series_from_slot_text($slot, $series_sources);
            if (!empty($matched_series)) {
                $matched_series_key = isset($matched_series['series_key'])
                    ? (string) $matched_series['series_key']
                    : (string) array_search($matched_series, $series_sources, true);
                if ($matched_series_key !== '') {
                    $series_key = $matched_series_key;
                }
                $series_entry = $matched_series;
                $mapped_title = isset($matched_series['supersaas_title']) ? trim((string) $matched_series['supersaas_title']) : '';
                if ($mapped_title === '' && !empty($matched_series['source_post_id'])) {
                    $mapped_title = trim((string) get_the_title((int) $matched_series['source_post_id']));
                }
                if ($mapped_title !== '') {
                    $clean_title = $mapped_title;
                }
                if ($tag === '' && !empty($matched_series['tag'])) {
                    $tag = strtoupper(sanitize_text_field((string) $matched_series['tag']));
                }
            }
        }
        $is_known_tag = ($tag !== '' && in_array($tag, $known_tags, true));
        if (iss_supersaas_slot_is_cancelled($slot, $clean_title)) {
            $service->delete_occurrence_by_external('supersaas', $external_id);
            $skipped_unlinked++;
            continue;
        }

        $exact_title_source_post_id = function_exists('iss_supersaas_match_fuehrung_by_title_candidates')
            ? iss_supersaas_match_fuehrung_by_title_candidates([$clean_title])
            : 0;
        if ($exact_title_source_post_id > 0) {
            $exact_title_source = get_post($exact_title_source_post_id);
            if (!$exact_title_source instanceof WP_Post
                || $exact_title_source->post_type !== 'fuehrung'
                || $exact_title_source->post_status !== 'publish'
            ) {
                $exact_title_source_post_id = 0;
            }
        }

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

        if (!$is_known_tag) {
            $imported_unmapped++;
        }

        $tag_source = ($is_known_tag && isset($tag_sources[$tag]) && is_array($tag_sources[$tag])) ? $tag_sources[$tag] : [];
        $source_post_id = isset($tag_source['source_post_id']) ? (int) $tag_source['source_post_id'] : 0;
        $source_post_type = isset($tag_source['source_post_type']) ? sanitize_key((string) $tag_source['source_post_type']) : '';
        $fallback_url = isset($tag_source['fallback_url']) ? esc_url_raw((string) $tag_source['fallback_url']) : '';

        if (empty($series_entry) && $series_key !== '' && isset($series_sources[$series_key]) && is_array($series_sources[$series_key])) {
            $series_entry = $series_sources[$series_key];
        }
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

        if ($source_post_id <= 0 && $is_known_tag) {
            $resolved_from_source = iss_supersaas_try_resolve_source_post_from_source_entry($tag_source);
            if ($resolved_from_source > 0) {
                $source_post_id = $resolved_from_source;
                $source_post_type = sanitize_key((string) get_post_type($source_post_id));
            }
        }

        $existing = $service->get_occurrence_by_external('supersaas', $external_id);
        if ($source_post_id <= 0 && !empty($existing)) {
            $existing_source_post_id = (int) ($existing['source_post_id'] ?? 0);
            $existing_source_post_type = sanitize_key((string) ($existing['source_post_type'] ?? ''));
            if ($existing_source_post_id > 0) {
                $source_post_id = $existing_source_post_id;
                $source_post_type = $existing_source_post_type;
            }
        }

        if ($source_post_id <= 0 && $series_key !== '') {
            $inferred = iss_supersaas_find_linked_source_by_series_key($series_key);
            if (!empty($inferred['source_post_id'])) {
                $source_post_id = (int) $inferred['source_post_id'];
                $source_post_type = sanitize_key((string) ($inferred['source_post_type'] ?? ''));
            }
        }

        if ($exact_title_source_post_id > 0 && (!$is_known_tag || $source_post_id <= 0) && $source_post_id !== $exact_title_source_post_id) {
            $source_post_id = $exact_title_source_post_id;
            $source_post_type = 'fuehrung';
        }

        if ($source_post_id > 0 && $source_post_type === '') {
            $source_post_type = sanitize_key((string) get_post_type($source_post_id));
        }

        if ($source_post_id > 0 && $is_known_tag && function_exists('iss_occurrences_remember_tag_source')) {
            iss_occurrences_remember_tag_source($tag, $fallback_url, $source_post_id, $source_post_type, $clean_title);

            $tag_sources[$tag] = isset($tag_sources[$tag]) && is_array($tag_sources[$tag]) ? $tag_sources[$tag] : [];
            $tag_sources[$tag]['source_post_id'] = $source_post_id;
            $tag_sources[$tag]['source_post_type'] = $source_post_type;
            if (!isset($tag_sources[$tag]['fallback_url'])) {
                $tag_sources[$tag]['fallback_url'] = $fallback_url;
            }
        }

        if ($series_key !== '' && function_exists('iss_occurrences_remember_series_source')) {
            iss_occurrences_remember_series_source($series_key, $source_post_id, $source_post_type, $clean_title, $tag, $fallback_url);

            $series_sources[$series_key] = isset($series_sources[$series_key]) && is_array($series_sources[$series_key]) ? $series_sources[$series_key] : [];
            if (!isset($series_sources[$series_key]['source_post_id']) || (int) $series_sources[$series_key]['source_post_id'] <= 0) {
                $series_sources[$series_key]['source_post_id'] = $source_post_id;
            } elseif ($source_post_id > 0) {
                $series_sources[$series_key]['source_post_id'] = $source_post_id;
            }
            if (!isset($series_sources[$series_key]['source_post_type']) || trim((string) $series_sources[$series_key]['source_post_type']) === '') {
                $series_sources[$series_key]['source_post_type'] = $source_post_type;
            } elseif ($source_post_type !== '') {
                $series_sources[$series_key]['source_post_type'] = $source_post_type;
            }
            if (trim((string) ($series_sources[$series_key]['supersaas_title'] ?? '')) === '' && $clean_title !== '') {
                $series_sources[$series_key]['supersaas_title'] = $clean_title;
            }
            if (trim((string) ($series_sources[$series_key]['tag'] ?? '')) === '' && $tag !== '') {
                $series_sources[$series_key]['tag'] = $tag;
            }
            if (trim((string) ($series_sources[$series_key]['fallback_url'] ?? '')) === '' && $fallback_url !== '') {
                $series_sources[$series_key]['fallback_url'] = $fallback_url;
            }
            $series_sources[$series_key]['version'] = 1;
            $series_sources[$series_key]['last_seen_at'] = $now;
        }

        $booking_url = $fallback_url ?: $schedule_url;

        $source = $source_post_id > 0 ? get_post($source_post_id) : null;
        if (!$source instanceof WP_Post || $source->post_status !== 'publish' || $source->post_type !== 'fuehrung') {
            $service->delete_occurrence_by_external('supersaas', $external_id);
            $skipped_unlinked++;
            continue;
        }

        $occurrence_id = $service->upsert_occurrence([
            'source_post_id' => $source_post_id,
            'source_post_type' => 'fuehrung',
            'kind' => 'tour',
            'title' => $clean_title !== '' ? wp_strip_all_tags($clean_title) : trim((string) $raw_title),
            'starts_at' => $start,
            'ends_at' => $end,
            'date_source' => 'supersaas',
            'status' => 'active',
            'visibility' => 'public',
            'origin' => 'supersaas',
            'source_calendar' => $source_calendar,
            'external_id' => $external_id,
            'tag' => $tag,
            'series_key' => $series_key,
            'booking_url' => $booking_url,
            'location_post_id' => 0,
            'location_label' => isset($slot['location']) ? sanitize_text_field((string) $slot['location']) : '',
            'availability_state' => $availability_state,
            'capacity_total' => $capacity_total,
            'capacity_available' => $available,
        ]);

        if ($occurrence_id <= 0) {
            $errors++;
            continue;
        }

        if (empty($existing)) {
            $created++;
        } else {
            $updated++;
        }

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
                'content_url' => get_permalink($source_post_id) ?: null,
            ];
        }
    }

    $inactivated = $service->mark_missing_origin_future_inactive('supersaas', $source_calendar, $seen_external_ids);
    $purged_inactive = 0;
    if ((bool) apply_filters('iss_supersaas_sync_purge_missing_future_rows', true)
        && method_exists($service, 'delete_inactive_origin_future')
    ) {
        $purged_inactive = $service->delete_inactive_origin_future('supersaas', $source_calendar);
    }
    $series_pruned += iss_supersaas_prune_empty_unlinked_series();
    $past_reactivated = method_exists($service, 'mark_origin_past_active')
        ? $service->mark_origin_past_active('supersaas', $source_calendar)
        : 0;

    // Keep the REST endpoint cache and the occurrence table in sync.
    foreach ($slots_by_tag as $tag => $slots) {
        if (!is_array($slots)) continue;

        usort($slots, function ($a, $b) {
            return strcmp((string) ($a['start'] ?? ''), (string) ($b['start'] ?? ''));
        });

        if (function_exists('is_tours_set_cached_slots_by_tag')) {
            is_tours_set_cached_slots_by_tag($tag, $slots, 60 * 60 * 6);
            if (function_exists('is_tours_set_cached_source_by_tag')) {
                is_tours_set_cached_source_by_tag($tag, 'occurrences', 60 * 60 * 6);
            }
        } else {
            set_transient('is_tours_slots_' . md5($tag), $slots, 60 * 60 * 6);
            set_transient('is_tours_slots_src_' . md5($tag), 'occurrences', 60 * 60 * 6);
        }
    }

    update_option(ISS_SUPERSAAS_LAST_SYNC_OPTION, $now, false);

    return [
        'created' => $created,
        'updated' => $updated,
        'errors' => $errors,
        'imported_unmapped' => $imported_unmapped,
        'skipped_unlinked' => $skipped_unlinked,
        'inactivated' => $inactivated,
        'purged_inactive' => $purged_inactive,
        'past_reactivated' => $past_reactivated,
        'metadata_backfilled' => $metadata_backfilled,
        'source_reconciled' => $source_reconciled,
        'source_cleared' => $source_cleared,
        'series_pruned' => $series_pruned,
        'error_message' => '',
    ];
}

function iss_supersaas_build_schedule_url($settings) {
    if (!is_array($settings)) return '';

    $base_url = isset($settings['base_url']) ? untrailingslashit((string) $settings['base_url']) : '';
    $account_name = isset($settings['account_name']) ? (string) $settings['account_name'] : '';
    $schedule_path = function_exists('iss_supersaas_get_schedule_path') ? iss_supersaas_get_schedule_path($settings) : '';
    $schedule_path = function_exists('iss_supersaas_normalize_schedule_path') ? iss_supersaas_normalize_schedule_path($schedule_path) : '';

    if ($base_url === '' || $account_name === '' || $schedule_path === '') {
        return '';
    }

    return $base_url . '/schedule/' . rawurlencode($account_name) . '/' . ltrim($schedule_path, '/');
}
