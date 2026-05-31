<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('iss_programm_get_items')) {
    function iss_programm_get_items($args = []) {
        $args = is_array($args) ? $args : [];

        if (!function_exists('iss_timeline_get_items_advanced')) {
            return [];
        }

        $groups = isset($args['groups']) && is_array($args['groups']) ? $args['groups'] : [];
        $single_group = isset($args['group']) ? sanitize_title((string) $args['group']) : '';
        if ($single_group !== '') {
            $groups[] = $single_group;
        }

        $mapped = [
            'limit' => isset($args['limit']) ? (int) $args['limit'] : 50,
            'order' => isset($args['order']) ? (string) $args['order'] : 'ASC',
            'groups' => $groups,
            'filters' => isset($args['filters']) && is_array($args['filters']) ? $args['filters'] : [],
        ];

        return iss_timeline_get_items_advanced($mapped);
    }
}

if (!function_exists('iss_programm_get_calendar_item_post_type')) {
    function iss_programm_get_calendar_item_post_type() {
        if (defined('ISS_CALENDAR_ITEM_POST_TYPE')) {
            return (string) ISS_CALENDAR_ITEM_POST_TYPE;
        }

        return 'iss_calendar_item';
    }
}

if (!function_exists('iss_programm_get_source_map')) {
    function iss_programm_get_source_map() {
        if (!function_exists('iss_calendar_get_source_map')) {
            return [];
        }

        $map = iss_calendar_get_source_map();
        return is_array($map) ? $map : [];
    }
}

if (!function_exists('iss_programm_get_source_map_entry')) {
    function iss_programm_get_source_map_entry($tag) {
        $tag = strtoupper(sanitize_text_field((string) $tag));
        if ($tag === '') {
            return null;
        }

        if (!function_exists('iss_calendar_get_source_map_entry')) {
            return null;
        }

        $entry = iss_calendar_get_source_map_entry($tag);
        return is_array($entry) ? $entry : null;
    }
}

if (!function_exists('iss_programm_get_series_map')) {
    function iss_programm_get_series_map() {
        if (!function_exists('iss_calendar_get_series_map')) {
            return [];
        }

        $map = iss_calendar_get_series_map();
        return is_array($map) ? $map : [];
    }
}

if (!function_exists('iss_programm_get_series_map_entry')) {
    function iss_programm_get_series_map_entry($series_key) {
        $series_key = trim((string) $series_key);
        if ($series_key === '') {
            return null;
        }

        if (!function_exists('iss_calendar_get_series_map_entry')) {
            return null;
        }

        $entry = iss_calendar_get_series_map_entry($series_key);
        return is_array($entry) ? $entry : null;
    }
}

if (!function_exists('iss_programm_resolve_tag_for_source_post_id')) {
    function iss_programm_resolve_tag_for_source_post_id($source_post_id) {
        $source_post_id = (int) $source_post_id;
        if ($source_post_id <= 0) {
            return '';
        }

        if (function_exists('iss_calendar_resolve_tag_for_source_post_id')) {
            return (string) iss_calendar_resolve_tag_for_source_post_id($source_post_id);
        }

        $map = iss_programm_get_source_map();
        foreach ($map as $tag => $entry) {
            if (!is_array($entry)) {
                continue;
            }
            if ((int) ($entry['source_post_id'] ?? 0) === $source_post_id) {
                return strtoupper(sanitize_text_field((string) $tag));
            }
        }

        return '';
    }
}

if (!function_exists('iss_programm_get_series_keys_for_source_post_id')) {
    function iss_programm_get_series_keys_for_source_post_id($source_post_id) {
        $source_post_id = (int) $source_post_id;
        if ($source_post_id <= 0) {
            return [];
        }

        if (function_exists('iss_calendar_resolve_series_keys_for_source_post_id')) {
            $keys = iss_calendar_resolve_series_keys_for_source_post_id($source_post_id);
            return is_array($keys) ? $keys : [];
        }

        return [];
    }
}

if (!function_exists('iss_programm_resolve_source_by_series_key')) {
    function iss_programm_resolve_source_by_series_key($series_key) {
        $series_key = trim((string) $series_key);
        if ($series_key === '') {
            return ['source_post_id' => 0, 'source_post_type' => ''];
        }

        if (function_exists('iss_calendar_resolve_source_by_series_key')) {
            $resolved = iss_calendar_resolve_source_by_series_key($series_key);
            if (is_array($resolved)) {
                return [
                    'source_post_id' => (int) ($resolved['source_post_id'] ?? 0),
                    'source_post_type' => sanitize_key((string) ($resolved['source_post_type'] ?? '')),
                ];
            }
        }

        return ['source_post_id' => 0, 'source_post_type' => ''];
    }
}

if (!function_exists('iss_programm_remember_source_mapping')) {
    function iss_programm_remember_source_mapping($tag, $fallback_url, $source_post_id, $source_post_type) {
        if (!function_exists('iss_calendar_remember_source_mapping')) {
            return;
        }

        iss_calendar_remember_source_mapping($tag, $fallback_url, $source_post_id, $source_post_type);
    }
}

if (!function_exists('iss_programm_remember_series_mapping')) {
    function iss_programm_remember_series_mapping($series_key, $source_post_id, $source_post_type, $supersaas_title = '', $tag = '', $fallback_url = '') {
        if (!function_exists('iss_calendar_remember_series_mapping')) {
            return false;
        }

        return (bool) iss_calendar_remember_series_mapping($series_key, $source_post_id, $source_post_type, $supersaas_title, $tag, $fallback_url);
    }
}

if (!function_exists('iss_programm_clear_mapping_for_post')) {
    function iss_programm_clear_mapping_for_post($source_post_id) {
        $source_post_id = (int) $source_post_id;
        if ($source_post_id <= 0) {
            return 0;
        }

        $map = iss_programm_get_source_map();
        if (empty($map)) {
            return 0;
        }

        $changed = 0;
        foreach ($map as $tag => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if ((int) ($entry['source_post_id'] ?? 0) !== $source_post_id) {
                continue;
            }

            $entry['source_post_id'] = 0;
            $entry['source_post_type'] = '';
            $entry['last_seen_at'] = current_time('mysql');
            $map[$tag] = $entry;
            $changed++;
        }

        if ($changed <= 0) {
            return 0;
        }

        $option_name = defined('ISS_CALENDAR_SOURCE_MAP_OPTION')
            ? ISS_CALENDAR_SOURCE_MAP_OPTION
            : 'iss_calendar_source_map';
        update_option($option_name, $map, false);

        return $changed;
    }
}

if (!function_exists('iss_programm_clear_series_mapping_for_post')) {
    function iss_programm_clear_series_mapping_for_post($source_post_id) {
        $source_post_id = (int) $source_post_id;
        if ($source_post_id <= 0) {
            return 0;
        }

        if (!function_exists('iss_calendar_clear_series_mapping_for_post')) {
            return 0;
        }

        return (int) iss_calendar_clear_series_mapping_for_post($source_post_id);
    }
}

if (!function_exists('iss_programm_clear_mapping_for_tag')) {
    function iss_programm_clear_mapping_for_tag($tag) {
        $tag = strtoupper(sanitize_text_field((string) $tag));
        if ($tag === '') {
            return false;
        }

        $map = iss_programm_get_source_map();
        if (!isset($map[$tag]) || !is_array($map[$tag])) {
            return false;
        }

        $entry = $map[$tag];
        $entry['source_post_id'] = 0;
        $entry['source_post_type'] = '';
        $entry['last_seen_at'] = current_time('mysql');
        $map[$tag] = $entry;

        $option_name = defined('ISS_CALENDAR_SOURCE_MAP_OPTION')
            ? ISS_CALENDAR_SOURCE_MAP_OPTION
            : 'iss_calendar_source_map';

        return (bool) update_option($option_name, $map, false);
    }
}

if (!function_exists('iss_programm_clear_series_mapping_for_key')) {
    function iss_programm_clear_series_mapping_for_key($series_key) {
        $series_key = trim((string) $series_key);
        if ($series_key === '') {
            return false;
        }

        if (!function_exists('iss_calendar_clear_series_mapping_for_key')) {
            return false;
        }

        return (bool) iss_calendar_clear_series_mapping_for_key($series_key);
    }
}

if (!function_exists('iss_programm_build_series_key')) {
    function iss_programm_build_series_key($title, $item_type = '') {
        if (function_exists('iss_calendar_build_series_key')) {
            return (string) iss_calendar_build_series_key($title, $item_type);
        }

        $slug = sanitize_title((string) $title);
        $item_type = sanitize_key((string) $item_type);
        if ($item_type !== '') {
            return $item_type . ':' . $slug;
        }

        return $slug;
    }
}

if (!function_exists('iss_programm_relink_series_to_post')) {
    function iss_programm_relink_series_to_post($post_id, $series_keys, $source_post_type = '') {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || !is_array($series_keys)) {
            return 0;
        }

        $series_keys = array_values(array_unique(array_filter(array_map(static function ($value) {
            return trim((string) $value);
        }, $series_keys))));
        if (empty($series_keys)) {
            return 0;
        }

        $calendar_post_type = iss_programm_get_calendar_item_post_type();
        if ($calendar_post_type === '' || !post_type_exists($calendar_post_type)) {
            return 0;
        }

        $query = new WP_Query([
            'post_type' => $calendar_post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'source_post_id',
                    'value' => $post_id,
                    'compare' => '!=',
                    'type' => 'NUMERIC',
                ],
                [
                    'key' => 'series_key',
                    'value' => $series_keys,
                    'compare' => 'IN',
                ],
            ],
        ]);

        if (empty($query->posts)) {
            return 0;
        }

        $updated = 0;
        foreach ($query->posts as $calendar_item_id) {
            $calendar_item_id = (int) $calendar_item_id;
            if ($calendar_item_id <= 0) {
                continue;
            }

            update_post_meta($calendar_item_id, 'source_post_id', $post_id);
            update_post_meta($calendar_item_id, 'source_post_type', sanitize_key((string) $source_post_type));
            $updated++;
        }

        return $updated;
    }
}

if (!function_exists('iss_programm_get_upcoming_events')) {
    function iss_programm_get_upcoming_events($post_id, $limit = 12) {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || !function_exists('iss_calendar_get_items_for_post')) {
            return [];
        }

        return iss_calendar_get_items_for_post($post_id, [
            'public_only' => true,
            'future_only' => true,
            'limit' => max(1, (int) $limit),
        ]);
    }
}

if (!function_exists('iss_programm_get_next_event')) {
    function iss_programm_get_next_event($post_id) {
        if (function_exists('iss_calendar_get_next_item_for_post')) {
            return iss_calendar_get_next_item_for_post((int) $post_id);
        }

        $items = iss_programm_get_upcoming_events((int) $post_id, 1);
        return !empty($items) ? $items[0] : null;
    }
}

if (!function_exists('iss_programm_has_linked_future_events')) {
    function iss_programm_has_linked_future_events($post_id) {
        $next = iss_programm_get_next_event((int) $post_id);
        return ($next instanceof WP_Post);
    }
}

if (!function_exists('iss_programm_get_item_dates')) {
    function iss_programm_get_item_dates($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0 || !function_exists('iss_calendar_get_items_for_post')) {
            return [];
        }

        $items = iss_calendar_get_items_for_post($post_id, [
            'public_only' => true,
            'future_only' => true,
            'limit' => -1,
        ]);

        $rows = [];
        foreach ($items as $item) {
            if (!($item instanceof WP_Post)) {
                continue;
            }

            if (function_exists('iss_calendar_prepare_item')) {
                $rows[] = iss_calendar_prepare_item((int) $item->ID);
                continue;
            }

            $rows[] = [
                'id' => (int) $item->ID,
                'title' => get_the_title($item->ID),
                'start_raw' => (string) get_post_meta($item->ID, 'event_start', true),
                'end_raw' => (string) get_post_meta($item->ID, 'event_end', true),
            ];
        }

        return $rows;
    }
}

if (!function_exists('iss_programm_render_card')) {
    function iss_programm_render_card($post_id, $context = 'default') {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return '';
        }

        $post = get_post($post_id);
        if (!($post instanceof WP_Post) || $post->post_status !== 'publish') {
            return '';
        }

        $title = get_the_title($post_id);
        $permalink = get_permalink($post_id);
        $excerpt = has_excerpt($post_id)
            ? (string) get_the_excerpt($post_id)
            : wp_trim_words(wp_strip_all_tags((string) $post->post_content), 28);

        return '<article class="iss-card iss-card--flat iss-program-cards__card iss-program-cards__card--' . esc_attr(sanitize_html_class((string) $context)) . '">'
            . '<div class="iss-card__body">'
            . '<h3 class="iss-card__title"><a href="' . esc_url((string) $permalink) . '">' . esc_html((string) $title) . '</a></h3>'
            . ($excerpt !== '' ? '<p class="iss-card__text">' . esc_html($excerpt) . '</p>' : '')
            . '</div></article>';
    }
}

if (!function_exists('iss_programm_render_calendar')) {
    function iss_programm_render_calendar($args = []) {
        $args = is_array($args) ? $args : [];
        if (!function_exists('iss_render_tour_calendar')) {
            return '';
        }

        return iss_render_tour_calendar([
            'tag' => isset($args['tag']) ? (string) $args['tag'] : '',
            'title' => isset($args['title']) ? (string) $args['title'] : 'Termine wählen',
            'fallbackUrl' => isset($args['fallbackUrl']) ? (string) $args['fallbackUrl'] : '',
        ], '');
    }
}

if (!function_exists('iss_programm_render_timeline')) {
    function iss_programm_render_timeline($args = []) {
        $args = is_array($args) ? $args : [];
        if (!function_exists('iss_timeline_render_query_block')) {
            return '';
        }

        return iss_timeline_render_query_block($args, '');
    }
}
