<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_programm_occurrences_ready(): bool
{
    if (!function_exists('iss_occurrences_query')) {
        return false;
    }

    return !function_exists('iss_occurrences_public_query_ready') || iss_occurrences_public_query_ready();
}

if (!function_exists('iss_programm_get_upcoming_events')) {
    function iss_programm_get_upcoming_events($post_id, $limit = 12) {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || !iss_programm_occurrences_ready()) {
            return [];
        }

        return iss_occurrences_query([
            'limit' => max(1, (int) $limit),
            'order' => 'ASC',
            'time_mode' => 'upcoming',
            'source_post_ids' => [$post_id],
        ]);
    }
}

if (!function_exists('iss_programm_get_next_event')) {
    function iss_programm_get_next_event($post_id) {
        $items = iss_programm_get_upcoming_events((int) $post_id, 1);
        return !empty($items) ? $items[0] : null;
    }
}

if (!function_exists('iss_programm_has_linked_future_events')) {
    function iss_programm_has_linked_future_events($post_id) {
        $next = iss_programm_get_next_event((int) $post_id);
        return is_array($next) && !empty($next);
    }
}

if (!function_exists('iss_programm_get_item_dates')) {
    function iss_programm_get_item_dates($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || !iss_programm_occurrences_ready()) {
            return [];
        }

        return iss_occurrences_query([
            'limit' => -1,
            'order' => 'ASC',
            'time_mode' => 'upcoming',
            'source_post_ids' => [$post_id],
        ]);
    }
}

/** Partition public occurrences using their display lifetime, never inventing stored end times. */
function iss_programm_sections(array $items, string $now): array
{
    $sections = ['dates' => [], 'running' => [], 'exhibitions' => [], 'later_exhibitions' => []];
    usort($items, static function ($a, $b) {
        return strcmp((string) ($a['start_raw'] ?? ''), (string) ($b['start_raw'] ?? ''))
            ?: ((int) ($a['id'] ?? 0) <=> (int) ($b['id'] ?? 0));
    });
    $seen = [];
    foreach ($items as $row) {
        $start = (string) ($row['start_raw'] ?? '');
        $end = (string) ($row['end_raw'] ?? '');
        $is_exhibition = ($row['source_post_type'] ?? '') === 'ausstellung';
        $open = !empty($row['is_open_ended']);
        $display_end = $end !== '' ? $end : substr($start, 0, 10) . ' 23:59:59';
        if ($start === '' || (!$open && $display_end < $now)) {
            continue;
        }
        $series = (string) ($row['series_key'] ?? '');
        if ($series !== '' && isset($seen[$series])) {
            continue;
        }
        if ($series !== '') {
            $seen[$series] = true;
        }
        if ($is_exhibition) {
            $key = $start <= $now ? 'exhibitions' : 'later_exhibitions';
        } elseif ($start <= $now && ($open || ($end !== '' && substr($start, 0, 10) !== substr($end, 0, 10)))) {
            $key = 'running';
        } else {
            $key = 'dates';
        }
        $sections[$key][] = $row;
    }
    return $sections;
}

/** Consume the existing graph promotion signal, including its UTC expiry. */
function iss_programm_focus_until(int $post_id): string
{
    $signal = function_exists('iss_graph_get_related_promotion_signal') ? iss_graph_get_related_promotion_signal($post_id, false) : null;
    return $signal && ($signal['status'] ?? '') === 'active' && !empty($signal['expires_at'])
        ? get_date_from_gmt($signal['expires_at']) : '';
}

/** Remove exactly one eligible feature from its section. All later dates stay available. */
function iss_programm_take_feature(array &$sections, string $now): ?array
{
    $eligible = array_filter($sections['dates'], static function ($row) {
        return ($row['availability_state'] ?? '') !== 'cancelled';
    });
    $key = key($eligible);
    $nearest = $key;
    $promoted = false;
    foreach ($eligible as $index => $row) {
        $until = (string) ($row['focus_until'] ?? '');
        if ($until !== '' && $until >= $now) {
            $key = $index;
            $promoted = true;
            break;
        }
    }
    $section = 'dates';
    $label = $key !== $nearest ? 'focus' : 'next';
    $horizon = (new DateTimeImmutable($now, wp_timezone()))->modify('+6 weeks')->format('Y-m-d H:i:s');
    if (($key === null || (!$promoted && $eligible[$key]['start_raw'] > $horizon)) && $sections['exhibitions']) {
        $section = 'exhibitions';
        $key = array_key_first($sections[$section]);
        $label = 'current';
    }
    if ($key === null) {
        return null;
    }
    $row = $sections[$section][$key];
    unset($sections[$section][$key]);
    $row['feature_label'] = $label;
    return $row;
}

/** Only authenticated editors can change the display clock; no source dates are written. */
function iss_programm_preview_datetime(): string
{
    if (!is_page('veranstaltungen') || !current_user_can('edit_others_posts')) {
        return '';
    }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'iss_programme_preview')) {
        return '';
    }
    $value = isset($_GET['programme_at']) ? sanitize_text_field(wp_unslash($_GET['programme_at'])) : '';
    $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value, wp_timezone());
    return $date && $date->format('Y-m-d\TH:i') === $value ? $date->format('Y-m-d H:i:s') : '';
}

add_filter('iss_occurrences_query_now', static function (string $now): string {
    return iss_programm_preview_datetime() ?: $now;
});

// Dates are evaluated on each page request; shared full-page caches must not freeze them.
add_action('template_redirect', static function (): void {
    if (is_page(['veranstaltungen', 'kalender'])) {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        nocache_headers();
    }
});
