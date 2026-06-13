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
    $title = trim((string) $title);
    if ($title === '') {
        $title = __('Termine wählen', 'iss-programm');
    }

    $post_id = (int) $post_id;
    $post_type = sanitize_key((string) $post_type);
    $tag = strtoupper(sanitize_text_field((string) $tag));
    $slot_select_id = 'is-tour-slot-';

    if ($tag !== '') {
        $slot_select_id .= sanitize_title($tag);
    } elseif ($post_id > 0) {
        $slot_select_id .= 'post-' . $post_id;
    } else {
        $slot_select_id .= 'calendar';
    }

    $slot_select_id = sanitize_html_class($slot_select_id) . '-time';

    $booking_url = esc_url_raw((string) $booking_url);

    $booking_link_label = iss_programm_tour_url_is_same_page_anchor($booking_url, $post_id)
        ? esc_html__('Alle Termine anzeigen', 'iss-programm')
        : esc_html__('Direkt buchen', 'iss-programm');

    $booking_link_html = '';
    if ($booking_url) {
        $booking_link_html = '<p class="is-tour-calendar__fallback has-small-font-size">'
            . '<a class="is-tour-calendar__fallback-link" href="' . esc_url($booking_url) . '">' . $booking_link_label . '</a>'
            . '</p>';
    }

    $noscript = '<noscript><p class="is-tour-calendar__status has-small-font-size">'
        . esc_html__('Bitte JavaScript aktivieren, um den Kalender zu nutzen.', 'iss-programm')
        . '</p>'
        . $booking_link_html
        . '</noscript>';

    return sprintf(
        '<div %1$s data-tag="%2$s" data-booking-url="%3$s" data-title="%4$s" data-source-post-id="%5$s" data-source-post-type="%6$s">'
        . '<div class="is-tour-calendar__inner wp-block-group is-layout-constrained">'
        . '<div class="is-tour-calendar__header wp-block-group is-layout-constrained">'
        . '<p class="is-tour-calendar__eyebrow has-small-font-size">%7$s</p>'
        . '<h3 class="is-tour-calendar__heading wp-block-heading">%8$s</h3>'
        . '<p class="is-tour-calendar__status has-small-font-size">%9$s</p>'
        . '%10$s'
        . '</div>'
        . '<div class="is-tour-calendar__layout">'
        . '<div class="is-tour-calendar__calendar">'
        . '<input type="text" class="is-tour-calendar__date-input" aria-label="%11$s" />'
        . '<div class="is-tour-calendar__slots-panel">'
        . '<p class="is-tour-calendar__selected-date has-small-font-size">%12$s</p>'
        . '<div class="is-tour-calendar__appointments">'
        . '<p class="is-tour-calendar__appointments-title">'
        . '<span class="is-tour-calendar__appointments-title-label">%13$s</span>'
        . '<span class="is-tour-calendar__appointments-title-date"></span>'
        . '</p>'
        . '<div class="is-tour-calendar__appointments-divider" aria-hidden="true"></div>'
        . '<div class="is-tour-calendar__appointments-list"></div>'
        . '</div>'
        . '<div class="is-tour-calendar__slot-select-wrap">'
        . '<label class="is-tour-calendar__slot-label" for="%14$s">%15$s</label>'
        . '<select id="%14$s" class="is-tour-calendar__slot-select" disabled>'
        . '<option value="">%16$s</option>'
        . '</select>'
        . '</div>'
        . '<div class="is-tour-calendar__booking"></div>'
        . '</div>'
        . '</div>'
        . '</div>'
        . '%17$s'
        . '</div>'
        . '</div>',
        $wrapper_attributes,
        esc_attr($tag),
        esc_url($booking_url),
        esc_attr($title),
        esc_attr($post_id > 0 ? (string) $post_id : ''),
        esc_attr($post_type),
        esc_html__('Kalender', 'iss-programm'),
        esc_html($title),
        esc_html__('Termine werden geladen …', 'iss-programm'),
        $booking_link_html,
        esc_attr__('Datum auswählen', 'iss-programm'),
        esc_html__('Bitte wählen Sie einen Tag.', 'iss-programm'),
        esc_html__('Verfügbare Termine am', 'iss-programm'),
        esc_attr($slot_select_id),
        esc_html__('Uhrzeit', 'iss-programm'),
        esc_html__('Bitte zuerst ein Datum wählen', 'iss-programm'),
        $noscript
    );
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

        if ($booking_url !== '') {
            $link_classes = 'iss-tour-dates__link';
            $link_attrs = '';
            if ($slot_id !== '' && $slot_start !== '') {
                $link_classes .= ' js-is-tour-slot-trigger';
                $link_attrs .= ' data-slot-id="' . esc_attr($slot_id) . '"';
                $link_attrs .= ' data-start="' . esc_attr($slot_start) . '"';
                $link_attrs .= ' data-title="' . esc_attr($slot_title) . '"';
                $link_attrs .= ' data-source-post-id="' . esc_attr((string) $post_id) . '"';
                $link_attrs .= ' data-source-post-type="' . esc_attr($post_type) . '"';
            }

            $out .= ' <a class="' . esc_attr($link_classes) . '" href="' . esc_url($booking_url) . '"' . $link_attrs . '>';
            $out .= $is_sold_out ? esc_html__('Ausgebucht', 'iss-programm') : esc_html__('Buchen', 'iss-programm');
            $out .= '</a>';
        }

        $out .= '</li>';
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

    if ($tag !== '' && function_exists('iss_occurrences_remember_tag_source')) {
        iss_occurrences_remember_tag_source($tag, $booking_url, $post_id, $post_type);
    }

    // Render only a lightweight mount node; front-end JS builds the UI.
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
