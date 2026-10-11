<?php

if (!defined('ABSPATH')) exit;

if (!function_exists('iss_programm_month_name_de')) {
    function iss_programm_month_name_de($month) {
        $names = [
            1 => 'Januar',
            2 => 'Februar',
            3 => 'März',
            4 => 'April',
            5 => 'Mai',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'August',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Dezember',
        ];

        $month = (int) $month;
        return $names[$month] ?? '';
    }
}

if (!function_exists('iss_programm_weekday_short_de')) {
    function iss_programm_weekday_short_de($weekday) {
        $names = [
            1 => 'Mo.',
            2 => 'Di.',
            3 => 'Mi.',
            4 => 'Do.',
            5 => 'Fr.',
            6 => 'Sa.',
            7 => 'So.',
        ];

        $weekday = (int) $weekday;
        return $names[$weekday] ?? '';
    }
}

if (!function_exists('iss_programm_format_date_long_de')) {
    function iss_programm_format_date_long_de($timestamp, $timezone = null) {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
        $day = wp_date('j', $timestamp, $timezone);
        $month_name = iss_programm_month_name_de((int) wp_date('n', $timestamp, $timezone));
        $year = wp_date('Y', $timestamp, $timezone);

        return sprintf('%s. %s %s', $day, $month_name, $year);
    }
}

if (!function_exists('iss_programm_format_day_short_de')) {
    function iss_programm_format_day_short_de($timestamp, $timezone = null) {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
        $weekday = iss_programm_weekday_short_de((int) wp_date('N', $timestamp, $timezone));
        $date_part = wp_date('d.m.', $timestamp, $timezone);

        return trim($weekday . ' ' . $date_part);
    }
}

if (!function_exists('iss_programm_format_month_year_de')) {
    function iss_programm_format_month_year_de($timestamp, $timezone = null) {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
        $month_name = iss_programm_month_name_de((int) wp_date('n', $timestamp, $timezone));
        $year = wp_date('Y', $timestamp, $timezone);

        return trim($month_name . ' ' . $year);
    }
}

if (!function_exists('iss_programm_format_datetime_de')) {
    function iss_programm_format_datetime_de($timestamp, $timezone = null) {
        $timestamp = (int) $timestamp;
        if ($timestamp <= 0) {
            return '';
        }

        $timezone = $timezone instanceof DateTimeZone ? $timezone : wp_timezone();
        $date_label = iss_programm_format_date_long_de($timestamp, $timezone);
        $time_label = wp_date('G:i', $timestamp, $timezone);

        return trim($date_label . ', ' . $time_label . ' Uhr');
    }
}

function iss_programm_render_tour_calendar_shell($title, $booking_url, $post_id = 0, $post_type = '', $wrapper_attributes = '', $tag = '') {
    iss_programm_enqueue_calendar_assets();
    $source = iss_programm_booking_context(['source_post_id' => $post_id, 'source_post_type' => $post_type, 'tag' => $tag, 'title' => $title]);
    $slots = iss_occurrences_get_booking_slots(['source_post_id' => $source['id'], 'source_post_type' => $source['type'], 'tag' => $source['tag']]);
    $payload = iss_occurrences_build_booking_slots_payload('occurrences', $slots, $source['id'], $source['type']);
    wp_interactivity_state('iss/booking', ['catalog' => [iss_programm_booking_scope($source) => $payload]]);
    $contact_id = wp_unique_id('iss-booking-contact-');
    $fallback = $booking_url && !iss_programm_tour_url_is_same_page_anchor($booking_url, $source['id']) ? $booking_url : '#' . $contact_id;
    $out = '<div ' . $wrapper_attributes . '><div class="is-tour-calendar__inner"><div class="is-tour-calendar__header">';
    $out .= '<h3 class="is-tour-calendar__heading">' . esc_html($title ?: __('Termine wählen', 'iss-programm')) . '</h3></div>';
    if ($slots) { $out .= iss_programm_render_availability_calendar($source); }
    if ($source['id'] > 0 && ($slots || $payload['inquiry']['allowed'])) {
        $label = $slots ? __('Termin wählen', 'iss-programm') : __('Wunschtermin anfragen', 'iss-programm');
        $attrs = iss_programm_booking_trigger_attributes(['source_post_id' => $source['id'], 'source_post_type' => $source['type'], 'tag' => $source['tag'], 'title' => $source['title']]);
        $out .= '<a class="wp-element-button" href="' . esc_url($fallback) . '"' . iss_timeline_render_attr_list($attrs) . '>' . esc_html($label) . '</a>';
    } else {
        $out .= '<p class="is-tour-calendar__status">' . esc_html__('Aktuell sind keine buchbaren Termine verfügbar.', 'iss-programm') . '</p>';
    }
    if ($slots) {
        $out .= '<details class="is-tour-calendar__dates"><summary>' . esc_html(sprintf(__('Alle Termine (%d)', 'iss-programm'), count($slots))) . '</summary><ul>';
        foreach ($slots as $slot) {
            $start = new DateTimeImmutable($slot['start'], wp_timezone());
            $label = iss_programm_format_datetime_de($start->getTimestamp());
            if (($slot['availability_state'] ?? '') === 'cancelled') { $label .= ' · ' . __('Abgesagt', 'iss-programm'); }
            elseif (($slot['availability_state'] ?? '') === 'sold_out' || ($slot['available'] !== null && $slot['available'] <= 0)) { $label .= ' · ' . __('Ausgebucht', 'iss-programm'); }
            $out .= '<li>' . esc_html($label) . '</li>';
        }
        $out .= '</ul></details>';
    }
    if ($booking_url && !iss_programm_tour_url_is_same_page_anchor($booking_url, $source['id'])) {
        $out .= '<p class="is-tour-calendar__fallback"><a href="' . esc_url($booking_url) . '">' . esc_html__('Direkt buchen', 'iss-programm') . '</a></p>';
    }
    $out .= '<div id="' . esc_attr($contact_id) . '" class="is-tour-calendar__fallback">' . iss_programm_booking_contact() . '</div>';
    return $out . '</div></div>';
}

/**
 * Dynamic block renderer: iss/tour-dates.
 *
 * Renders upcoming occurrence rows linked to the current post.
 *
 * @param array<string,mixed> $attributes
 * @param string $content
 * @return string
 */
function iss_programm_render_tour_dates($attributes = [], $content = '') {
    $attributes = is_array($attributes) ? $attributes : [];

    $limit = isset($attributes['limit']) ? (int) $attributes['limit'] : 12;
    if ($limit <= 0) {
        $limit = 12;
    }

    $title = isset($attributes['title']) ? (string) $attributes['title'] : 'Termine';
    $title = trim($title);
    if ($title === '') {
        $title = 'Termine';
    }
    $hide_when_empty = !empty($attributes['hideWhenEmpty']);

    if (!function_exists('iss_programm_get_item_dates')) {
        return '';
    }

    $post_id = (int) get_the_ID();
    if ($post_id <= 0) {
        return '';
    }
    $post_type = (string) get_post_type($post_id);

    $items = array_slice(iss_programm_get_item_dates($post_id), 0, $limit);

    $attrs = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-tour-dates'])
        : 'class="wp-block-iss-tour-dates"';

    if (empty($items)) {
        if ($hide_when_empty) {
            return '';
        }

        $out = '<div ' . $attrs . '>';
        $out .= '<h3 class="iss-tour-dates__title">' . esc_html($title) . '</h3>';
        $out .= '<p>' . esc_html__('Aktuell sind keine Termine verfügbar.', 'iss-programm') . '</p>';
        $out .= '</div>';
        return $out;
    }

    $out = '<div ' . $attrs . '>';
    $out .= '<h3 class="iss-tour-dates__title">' . esc_html($title) . '</h3>';
    $out .= '<ul class="iss-tour-dates">';
    $has_slot_trigger = false;

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        $data = $item;

        $date_label = isset($data['date_label']) ? trim((string) $data['date_label']) : '';
        $time_label = isset($data['time_label']) ? trim((string) $data['time_label']) : '';
        $fallback_label = isset($data['datetime_label']) ? trim((string) $data['datetime_label']) : '';
        if ($fallback_label === '') {
            $fallback_label = isset($data['title']) ? trim((string) $data['title']) : '';
        }

        $label_html = '';
        if ($date_label !== '') {
            $label_html .= '<span class="iss-tour-dates__date">' . esc_html($date_label) . '</span>';
        }
        if ($time_label !== '') {
            $label_html .= '<span class="iss-tour-dates__time">' . esc_html($time_label) . '</span>';
        }
        if ($label_html === '') {
            $label_html = '<span class="iss-tour-dates__date">' . esc_html($fallback_label) . '</span>';
        }

        $booking_url = isset($data['booking_url']) ? (string) $data['booking_url'] : '';
        $is_sold_out = isset($data['availability']) && (string) $data['availability'] === 'sold_out';
        $slot_id = trim((string) ($data['slot_id'] ?? ''));
        $slot_start = isset($data['start_raw']) ? trim((string) $data['start_raw']) : '';
        $slot_title = isset($data['title']) ? trim((string) $data['title']) : '';

        $out .= '<li class="iss-tour-dates__item">';
        $content_url = isset($data['content_url']) ? trim((string) $data['content_url']) : '';
        if ($content_url !== '') {
            $out .= '<a class="iss-tour-dates__label" href="' . esc_url($content_url) . '">' . $label_html . '</a>';
        } else {
            $out .= '<span class="iss-tour-dates__label">' . $label_html . '</span>';
        }

        if ($booking_url !== '' && $is_sold_out) {
            $out .= ' <span class="iss-tour-dates__link iss-tour-dates__link--disabled" aria-disabled="true">';
            $out .= esc_html__('Ausgebucht', 'iss-programm');
            $out .= '</span>';
        } elseif ($booking_url !== '') {
            $link_classes = 'iss-tour-dates__link';
            $link_attrs = '';
            if (($data['cta_mode'] ?? '') !== 'external' && $slot_id !== '' && $slot_start !== '') {
                $link_attrs = iss_timeline_render_attr_list(iss_programm_booking_trigger_attributes([
                    'source_post_id' => $post_id, 'source_post_type' => $post_type, 'slot_id' => $slot_id, 'title' => $slot_title,
                ]));
                $has_slot_trigger = true;
            }

            $out .= ' <a class="' . esc_attr($link_classes) . '" href="' . esc_url($booking_url) . '"' . $link_attrs . '>';
            $out .= esc_html__('Buchen', 'iss-programm');
            $out .= '</a>';
        }

        $out .= '</li>';
    }

    if ($has_slot_trigger && function_exists('iss_programm_enqueue_calendar_assets')) {
        iss_programm_enqueue_calendar_assets();
    }

    $out .= '</ul></div>';
    return $out;
}

/**
 * Dynamic block renderer: iss/tour-calendar.
 *
 * Renders the interactive Fuehrung booking calendar mount from occurrence rows.
 *
 * @param array<string,mixed> $attributes
 * @param string $content
 * @return string
 */
function iss_programm_render_tour_calendar($attributes = [], $content = '') {
    $attributes = is_array($attributes) ? $attributes : [];

    if (function_exists('iss_programm_enqueue_calendar_assets')) {
        iss_programm_enqueue_calendar_assets();
    }

    $title = isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : 'Termine wählen';
    $booking_url = isset($attributes['bookingUrl']) ? esc_url_raw((string) $attributes['bookingUrl']) : '';

    $post_id = (int) get_the_ID();
    $post_type = $post_id ? get_post_type($post_id) : '';

    $tag = isset($attributes['tag']) ? strtoupper(sanitize_text_field((string) $attributes['tag'])) : '';
    if ($tag === '' && $post_id && function_exists('iss_occurrences_resolve_tag_for_source_post_id')) {
        $tag = iss_occurrences_resolve_tag_for_source_post_id($post_id);
        if ($tag !== '' && $booking_url === '' && function_exists('iss_occurrences_get_tag_source')) {
            $entry = iss_occurrences_get_tag_source($tag);
            if (is_array($entry) && !empty($entry['fallback_url'])) {
                $booking_url = esc_url_raw((string) $entry['fallback_url']);
            }
        }
    }

    if ($tag === '' && $post_id <= 0) {
        // Can't render an interactive calendar without a tag or source post.
        $attrs = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes([
                'class' => 'is-tour-calendar wp-block-group alignwide has-global-padding is-layout-constrained',
            ])
            : 'class="is-tour-calendar wp-block-group alignwide has-global-padding is-layout-constrained"';

        $msg = esc_html__('Kalender ist nicht konfiguriert (Tag fehlt).', 'iss-programm');
        return '<div ' . $attrs . '><p class="is-tour-calendar__status has-small-font-size">' . $msg . '</p></div>';
    }

    // Source mappings are maintained by occurrence sync/editorial owners, never a public render.
    $attrs = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'is-tour-calendar wp-block-group alignwide has-global-padding is-layout-constrained',
        ])
        : 'class="is-tour-calendar wp-block-group alignwide has-global-padding is-layout-constrained"';

    return iss_programm_render_tour_calendar_shell($title, $booking_url, $post_id, (string) $post_type, $attrs, $tag);
}

/**
 * Decide whether a booking URL should be presented as a same-page list anchor.
 *
 * @param string $booking_url
 * @param int $post_id
 * @return bool
 */
function iss_programm_tour_url_is_same_page_anchor($booking_url, $post_id) {
    $booking_url = trim((string) $booking_url);
    if ($booking_url === '') return false;

    if (str_starts_with($booking_url, '#')) return true;

    $post_id = (int) $post_id;
    if ($post_id <= 0) return false;

    $parsed = wp_parse_url($booking_url);
    if (empty($parsed['fragment'])) return false;

    $permalink = get_permalink($post_id);
    if (!$permalink) return false;

    $p1 = wp_parse_url($permalink);
    if (!is_array($p1) || !is_array($parsed)) return false;

    $host1 = isset($p1['host']) ? (string) $p1['host'] : '';
    $host2 = isset($parsed['host']) ? (string) $parsed['host'] : '';
    $path1 = isset($p1['path']) ? (string) $p1['path'] : '';
    $path2 = isset($parsed['path']) ? (string) $parsed['path'] : '';

    if ($host2 !== '' && $host1 !== '' && strcasecmp($host1, $host2) !== 0) return false;
    if ($path2 !== '' && $path1 !== '' && untrailingslashit($path1) !== untrailingslashit($path2)) return false;

    return true;
}
