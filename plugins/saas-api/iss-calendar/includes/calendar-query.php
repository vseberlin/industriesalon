<?php

if (!defined('ABSPATH')) exit;

/**
 * Get calendar items linked to a source post.
 *
 * @param int $post_id
 * @param array $args
 * @return WP_Post[]
 */
function iss_calendar_get_items_for_post($post_id, $args = []) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) return [];

    $defaults = [
        'public_only' => true,
        'future_only' => true,
        'limit' => -1,
    ];

    $args = wp_parse_args($args, $defaults);

    $meta_query = [
        [
            'key' => 'source_post_id',
            'value' => $post_id,
            'compare' => '=',
            'type' => 'NUMERIC',
        ],
    ];

    if (!empty($args['public_only'])) {
        $meta_query[] = [
            'key' => 'is_public',
            'value' => 1,
            'compare' => '=',
            'type' => 'NUMERIC',
        ];
    }

    if (!empty($args['future_only'])) {
        $meta_query[] = [
            'key' => 'event_start',
            'value' => current_time('mysql'),
            'compare' => '>=',
            'type' => 'DATETIME',
        ];
    }

    $limit = isset($args['limit']) ? (int) $args['limit'] : -1;
    if ($limit === 0) {
        $limit = -1;
    }

    $query = new WP_Query([
        'post_type' => ISS_CALENDAR_ITEM_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'no_found_rows' => true,

        'meta_key' => 'event_start',
        'orderby' => 'meta_value',
        'meta_type' => 'DATETIME',
        'order' => 'ASC',

        'meta_query' => $meta_query,
    ]);

    return $query->posts;
}

/**
 * Get next public event for a source post.
 *
 * @param int $post_id
 * @return WP_Post|null
 */
function iss_calendar_get_next_item_for_post($post_id) {
    $items = iss_calendar_get_items_for_post($post_id, [
        'public_only' => true,
        'future_only' => true,
        'limit' => 1,
    ]);

    return !empty($items) ? $items[0] : null;
}

/**
 * Back-compat: original helper (no filters).
 *
 * @param int $post_id
 * @return WP_Post[]
 */
function iss_get_events_for_post($post_id) {
    return iss_calendar_get_items_for_post($post_id, [
        'public_only' => false,
        'future_only' => false,
        'limit' => -1,
    ]);
}
