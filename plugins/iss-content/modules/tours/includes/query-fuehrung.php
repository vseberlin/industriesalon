<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_fuehrung_get_upcoming_events($post_id, $limit = 12) {
    if (!function_exists('iss_programm_get_upcoming_events')) {
        return [];
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    return iss_programm_get_upcoming_events($post_id, (int) $limit);
}

function iss_fuehrung_get_next_event($post_id) {
    if (function_exists('iss_programm_get_next_event')) {
        return iss_programm_get_next_event((int) $post_id);
    }

    $events = iss_fuehrung_get_upcoming_events($post_id, 1);
    return !empty($events) ? $events[0] : null;
}

function iss_fuehrung_is_calendar_event($event) {
    return ($event instanceof WP_Post) || (is_array($event) && !empty($event));
}

function iss_fuehrung_get_event_start_label($event) {
    if (is_array($event)) {
        $label = trim((string) ($event['date_label'] ?? ''));
        $time = trim((string) ($event['time_label'] ?? ''));
        if ($label !== '') {
            return $time !== '' ? $label . ', ' . $time : $label;
        }
        $start = trim((string) ($event['date_raw'] ?? $event['starts_at'] ?? ''));
    } else {
        $event_id = ($event instanceof WP_Post) ? (int) $event->ID : (int) $event;
        $start = get_post_meta($event_id, 'event_start', true);
    }

    if (!$start) {
        return '';
    }

    try {
        $datetime = new DateTimeImmutable((string) $start, wp_timezone());
        $timestamp = $datetime->getTimestamp();
    } catch (Throwable $e) {
        $timestamp = 0;
    }

    if (!$timestamp) {
        return (string) $start;
    }

    if (function_exists('iss_programm_format_datetime_de')) {
        return iss_programm_format_datetime_de($timestamp, wp_timezone());
    }

    return wp_date('d.m.Y, H:i', $timestamp, wp_timezone());
}

function iss_fuehrung_get_event_booking_url($event, $post_id = 0) {
    if (is_array($event)) {
        $url = trim((string) ($event['booking_url'] ?? ''));
    } else {
        $event_id = ($event instanceof WP_Post) ? (int) $event->ID : (int) $event;
        $url = (string) get_post_meta($event_id, 'booking_url', true);
    }

    if ($url !== '') {
        return $url;
    }

    if ($post_id > 0) {
        $url = (string) get_post_meta($post_id, 'fallback_url', true);
        if ($url !== '') {
            return $url;
        }
    }

    return '';
}

function iss_fuehrung_get_card_meta($post_id) {
    $parts = [];

    foreach (['duration', 'meeting_point', 'target_group'] as $key) {
        $value = trim((string) get_post_meta($post_id, $key, true));
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    return $parts;
}

function iss_fuehrung_get_related($post_id, $limit = 3) {
    $terms = wp_get_post_terms($post_id, 'fuehrung_typ', ['fields' => 'ids']);

    $tax_query = [];
    if (!is_wp_error($terms) && !empty($terms)) {
        $tax_query[] = [
            'taxonomy' => 'fuehrung_typ',
            'field'    => 'term_id',
            'terms'    => $terms,
        ];
    }

    return get_posts([
        'post_type'      => ISS_FUEHRUNGEN_POST_TYPE,
        'posts_per_page' => max(1, (int) $limit),
        'post__not_in'   => [$post_id],
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Related tour cards intentionally match shared tour taxonomy terms.
        'tax_query'      => $tax_query,
    ]);
}

function iss_fuehrung_get_booking_mode($post_id) {
    $mode = sanitize_key((string) get_post_meta($post_id, 'booking_mode', true));
    $allowed = ['auto', 'calendar', 'on_demand', 'hybrid'];

    if (!in_array($mode, $allowed, true)) {
        return 'auto';
    }

    return $mode;
}

function iss_fuehrung_get_inquiry_data($post_id) {
    $url = trim((string) get_post_meta($post_id, 'inquiry_url', true));
    $label = trim((string) get_post_meta($post_id, 'inquiry_label', true));
    $note = trim((string) get_post_meta($post_id, 'inquiry_note', true));

    if ($label === '') {
        $label = __('Anfrage senden', 'iss-fuehrungen');
    }

    return [
        'url' => $url,
        'label' => $label,
        'note' => $note,
    ];
}

function iss_fuehrung_get_effective_booking_mode($post_id) {
    $mode = iss_fuehrung_get_booking_mode($post_id);
    if ($mode !== 'auto') {
        return $mode;
    }

    $next_event = iss_fuehrung_get_next_event($post_id);
    $has_calendar = iss_fuehrung_is_calendar_event($next_event);

    $inquiry = iss_fuehrung_get_inquiry_data($post_id);
    $has_on_demand = ($inquiry['url'] !== '' || $inquiry['note'] !== '');
    $allow_hybrid = !empty(get_post_meta($post_id, 'allow_on_demand_with_calendar', true));

    if ($has_calendar && $has_on_demand && $allow_hybrid) {
        return 'hybrid';
    }

    if ($has_calendar) {
        return 'calendar';
    }

    if ($has_on_demand) {
        return 'on_demand';
    }

    return 'calendar';
}

function iss_fuehrung_get_catalog_posts(array $args = []) {
    $defaults = [
        'post_type'              => ISS_FUEHRUNGEN_POST_TYPE,
        'post_status'            => 'publish',
        'posts_per_page'         => -1,
        'orderby'                => [
            'menu_order' => 'ASC',
            'title'      => 'ASC',
        ],
        'order'                  => 'ASC',
        'suppress_filters'       => false,
        'ignore_sticky_posts'    => true,
        'no_found_rows'          => true,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => false,
    ];

    return get_posts(wp_parse_args($args, $defaults));
}
