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

function iss_programm_calendar_item_display_title($item_id) {
    $item_id = (int) $item_id;
    if ($item_id <= 0) {
        return '';
    }

    $source_post_id = (int) get_post_meta($item_id, 'source_post_id', true);
    if ($source_post_id > 0) {
        $linked_title = trim((string) get_the_title($source_post_id));
        if ($linked_title !== '') {
            return $linked_title;
        }
    }

    return (string) get_the_title($item_id);
}

function iss_programm_calendar_item_content_url($item_id) {
    $item_id = (int) $item_id;
    if ($item_id <= 0) {
        return '';
    }

    if (function_exists('iss_calendar_get_item_source_permalink')) {
        $source_link = (string) iss_calendar_get_item_source_permalink($item_id);
        if ($source_link !== '') {
            return $source_link;
        }
    }

    $source_post_id = (int) get_post_meta($item_id, 'source_post_id', true);
    if ($source_post_id <= 0) {
        return '';
    }

    $source_link = get_permalink($source_post_id);
    if (!is_string($source_link) || trim($source_link) === '') {
        return '';
    }

    return $source_link;
}

/**
 * Normalize one event into readable frontend data.
 *
 * @param int $item_id
 * @return array<string,mixed>
 */
function iss_calendar_prepare_item($item_id) {
    $item_id = (int) $item_id;
    if ($item_id <= 0) {
        return [];
    }

    $start = (string) get_post_meta($item_id, 'event_start', true);
    $end = (string) get_post_meta($item_id, 'event_end', true);

    $start_ts = null;
    $end_ts = null;

    try {
        if ($start !== '') {
            $start_dt = new DateTimeImmutable($start, wp_timezone());
            $start_ts = $start_dt->getTimestamp();
        }
    } catch (Throwable $e) {
        $start_ts = null;
    }

    try {
        if ($end !== '') {
            $end_dt = new DateTimeImmutable($end, wp_timezone());
            $end_ts = $end_dt->getTimestamp();
        }
    } catch (Throwable $e) {
        $end_ts = null;
    }

    $availability = (string) get_post_meta($item_id, 'availability_state', true);
    $available_raw = get_post_meta($item_id, 'capacity_available', true);
    $available = ($available_raw === '' || $available_raw === null) ? null : (int) $available_raw;
    $booking_url = (string) get_post_meta($item_id, 'booking_url', true);
    $note = (string) get_post_meta($item_id, 'public_note', true);

    return [
        'id' => $item_id,
        'title' => iss_programm_calendar_item_display_title($item_id),
        'start_raw' => $start,
        'end_raw' => $end,
        'date_label' => $start_ts ? iss_programm_format_date_long_de($start_ts) : '',
        'time_label' => $start_ts ? wp_date('G:i', $start_ts) . ' Uhr' : '',
        'datetime_label' => $start_ts ? iss_programm_format_datetime_de($start_ts) : '',
        'availability' => $availability,
        'available' => $available,
        'booking_url' => $booking_url,
        'content_url' => iss_programm_calendar_item_content_url($item_id),
        'note' => $note,
        'start_ts' => $start_ts,
        'end_ts' => $end_ts,
    ];
}

function iss_calendar_render_shell($title, $fallback_url, $post_id = 0, $post_type = '', $wrapper_attributes = '', $tag = '') {
    $title = trim((string) $title);
    if ($title === '') {
        $title = __('Termine wählen', 'iss-calendar');
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

    $fallback_label = iss_calendar_is_fallback_anchor($fallback_url, $post_id)
        ? esc_html__('Alle Termine anzeigen', 'iss-calendar')
        : esc_html__('Direkt buchen', 'iss-calendar');

    $fallback_html = '';
    if ($fallback_url) {
        $fallback_html = '<p class="is-tour-calendar__fallback has-small-font-size">'
            . '<a class="is-tour-calendar__fallback-link" href="' . esc_url($fallback_url) . '">' . $fallback_label . '</a>'
            . '</p>';
    }

    $noscript = '<noscript><p class="is-tour-calendar__status has-small-font-size">'
        . esc_html__('Bitte JavaScript aktivieren, um den Kalender zu nutzen.', 'iss-calendar')
        . '</p>'
        . $fallback_html
        . '</noscript>';

    return sprintf(
        '<div %1$s data-tag="%2$s" data-fallback="%3$s" data-title="%4$s" data-source-post-id="%5$s" data-source-post-type="%6$s">'
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
        esc_url($fallback_url),
        esc_attr($title),
        esc_attr($post_id > 0 ? (string) $post_id : ''),
        esc_attr($post_type),
        esc_html__('Kalender', 'iss-calendar'),
        esc_html($title),
        esc_html__('Termine werden geladen …', 'iss-calendar'),
        $fallback_html,
        esc_attr__('Datum auswählen', 'iss-calendar'),
        esc_html__('Bitte wählen Sie einen Tag.', 'iss-calendar'),
        esc_html__('Verfügbare Termine am', 'iss-calendar'),
        esc_attr($slot_select_id),
        esc_html__('Uhrzeit', 'iss-calendar'),
        esc_html__('Bitte zuerst ein Datum wählen', 'iss-calendar'),
        $noscript
    );
}

/**
 * Dynamic block renderer: iss/tour-dates.
 *
 * Renders upcoming `iss_calendar_item` entries linked to the current post
 * (via `source_post_id`), or a specific post id when passed via attributes.
 *
 * @param array<string,mixed> $attributes
 * @param string $content
 * @return string
 */
function iss_calendar_render_dates($attributes = [], $content = '') {
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

    if (!function_exists('iss_calendar_get_items_for_post')) {
        return '';
    }

    $post_id = (int) get_the_ID();
    if ($post_id <= 0) {
        return '';
    }
    $post_type = (string) get_post_type($post_id);

    $items = iss_calendar_get_items_for_post($post_id, [
        'public_only' => true,
        'future_only' => true,
        'limit' => $limit,
    ]);

    $attrs = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-tour-dates'])
        : 'class="wp-block-iss-tour-dates"';

    if (empty($items)) {
        $out = '<div ' . $attrs . '>';
        $out .= '<h3 class="iss-tour-dates__title">' . esc_html($title) . '</h3>';
        $out .= '<p>' . esc_html__('Aktuell sind keine Termine verfügbar.', 'iss-calendar') . '</p>';
        $out .= '</div>';
        return $out;
    }

    $out = '<div ' . $attrs . '>';
    $out .= '<h3 class="iss-tour-dates__title">' . esc_html($title) . '</h3>';
    $out .= '<ul class="iss-tour-dates">';

    foreach ($items as $item) {
        if (!($item instanceof WP_Post)) {
            continue;
        }

        $data = iss_calendar_prepare_item($item->ID);
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
        $slot_id = trim((string) get_post_meta($item->ID, 'external_id', true));
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
            $out .= $is_sold_out ? esc_html__('Ausgebucht', 'iss-calendar') : esc_html__('Buchen', 'iss-calendar');
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
 * Uses the existing shortcode markup so the front-end JS can attach reliably.
 *
 * @param array<string,mixed> $attributes
 * @param string $content
 * @return string
 */
function iss_render_tour_calendar($attributes = [], $content = '') {
    $attributes = is_array($attributes) ? $attributes : [];

    if (function_exists('iss_programm_enqueue_calendar_assets')) {
        iss_programm_enqueue_calendar_assets();
    }

    $title = isset($attributes['title']) ? sanitize_text_field((string) $attributes['title']) : 'Termine wählen';
    $fallback_url = isset($attributes['fallbackUrl']) ? esc_url_raw((string) $attributes['fallbackUrl']) : '';

    $post_id = (int) get_the_ID();
    $post_type = $post_id ? get_post_type($post_id) : '';

    $tag = isset($attributes['tag']) ? strtoupper(sanitize_text_field((string) $attributes['tag'])) : '';
    if ($tag === '' && $post_id) {
        $tag = strtoupper(sanitize_text_field((string) get_post_meta($post_id, 'calendar_tag', true)));
    }
    if ($tag === '' && $post_id && function_exists('iss_calendar_resolve_tag_for_source_post_id')) {
        $tag = iss_calendar_resolve_tag_for_source_post_id($post_id);
        if ($tag !== '' && $fallback_url === '' && function_exists('iss_calendar_get_source_map_entry')) {
            $entry = iss_calendar_get_source_map_entry($tag);
            if (is_array($entry) && !empty($entry['fallback_url'])) {
                $fallback_url = esc_url_raw((string) $entry['fallback_url']);
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

        $msg = esc_html__('Kalender ist nicht konfiguriert (Tag fehlt).', 'iss-calendar');
        $link_text = iss_calendar_is_fallback_anchor($fallback_url, $post_id)
            ? esc_html__('Alle Termine anzeigen', 'iss-calendar')
            : esc_html__('Direkt buchen', 'iss-calendar');
        $link = $fallback_url ? ' <a href="' . esc_url($fallback_url) . '">' . $link_text . '</a>' : '';
        return '<div ' . $attrs . '><p class="is-tour-calendar__status has-small-font-size">' . $msg . $link . '</p></div>';
    }

    if ($tag !== '' && function_exists('iss_calendar_remember_source_mapping')) {
        iss_calendar_remember_source_mapping($tag, $fallback_url, $post_id, $post_type);
    }

    // Render only a lightweight mount node; front-end JS builds the UI.
    $attrs = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'is-tour-calendar wp-block-group alignwide has-global-padding is-layout-constrained',
        ])
        : 'class="is-tour-calendar wp-block-group alignwide has-global-padding is-layout-constrained"';

    return iss_calendar_render_shell($title, $fallback_url, $post_id, (string) $post_type, $attrs, $tag);
}

/**
 * Decide whether a fallback URL should be presented as a same-page list anchor.
 *
 * @param string $fallback_url
 * @param int $post_id
 * @return bool
 */
function iss_calendar_is_fallback_anchor($fallback_url, $post_id) {
    $fallback_url = trim((string) $fallback_url);
    if ($fallback_url === '') return false;

    if (str_starts_with($fallback_url, '#')) return true;

    $post_id = (int) $post_id;
    if ($post_id <= 0) return false;

    $parsed = wp_parse_url($fallback_url);
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
