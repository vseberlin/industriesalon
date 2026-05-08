<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_related_objects_for_place(int $place_id, int $limit = 6): array
{
    if ($place_id <= 0 || !function_exists('iss_relations_get_place_term_ids')) {
        return [];
    }

    $term_ids = iss_relations_get_place_term_ids([$place_id]);
    if (!$term_ids || !post_type_exists('archivobjekt')) {
        return [];
    }

    $posts = get_posts([
        'post_type' => 'archivobjekt',
        'post_status' => 'publish',
        'posts_per_page' => max(1, min(12, $limit)),
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
        'tax_query' => [
            [
                'taxonomy' => ISS_RELATIONS_TAXONOMY,
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => 'IN',
            ],
        ],
    ]);

    if (!$posts) {
        return [];
    }

    $items = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $featured_image_url = get_the_post_thumbnail_url($post, 'medium_large');

        $items[] = [
            'post_id' => (int) $post->ID,
            'slug' => (string) $post->post_name,
            'title' => get_the_title($post),
            'excerpt' => (string) get_post_field('post_excerpt', $post->ID),
            'permalink' => (string) get_permalink($post),
            'featured_image_url' => is_string($featured_image_url) ? $featured_image_url : '',
        ];
    }

    return $items;
}

/**
 * Atlas may expose that a place participates in tours, but it must not expose
 * route ordering or stop-specific route presentation fields.
 */
function iss_register_get_place_tour_usage(int $place_id): array
{
    if ($place_id <= 0 || !post_type_exists('fuehrung')) {
        return [];
    }

    $usage_map = get_transient('iss_register_place_tour_usage_map_cache');
    if (!is_array($usage_map)) {
        $usage_map = [];

        $tour_posts = get_posts([
            'post_type' => 'fuehrung',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'suppress_filters' => true,
        ]);

        if ($tour_posts) {
            foreach ($tour_posts as $tour_post) {
                if (!$tour_post instanceof WP_Post) {
                    continue;
                }

                $related_places = get_post_meta((int) $tour_post->ID, 'iss_related_places', true);
                if (!is_array($related_places)) {
                    continue;
                }

                $tour_payload = [
                    'id' => (int) $tour_post->ID,
                    'slug' => (string) $tour_post->post_name,
                    'title' => get_the_title($tour_post),
                    'permalink' => (string) get_permalink($tour_post),
                ];

                foreach ($related_places as $related_place) {
                    if (!is_array($related_place)) {
                        continue;
                    }

                    $related_place_id = (int) ($related_place['place_id'] ?? 0);
                    if ($related_place_id <= 0) {
                        continue;
                    }

                    if (!isset($usage_map[$related_place_id])) {
                        $usage_map[$related_place_id] = [];
                    }

                    $usage_map[$related_place_id][] = $tour_payload;
                }
            }
        }

        set_transient('iss_register_place_tour_usage_map_cache', $usage_map, HOUR_IN_SECONDS);
    }

    return isset($usage_map[$place_id]) && is_array($usage_map[$place_id])
        ? $usage_map[$place_id]
        : [];
}

function iss_register_build_place_detail_contract(array $place): array
{
    $post_id = isset($place['post_id']) ? (int) $place['post_id'] : 0;

    if ($post_id > 0) {
        $place['related_objects'] = iss_register_get_related_objects_for_place($post_id);
    }

    return $place;
}
