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
