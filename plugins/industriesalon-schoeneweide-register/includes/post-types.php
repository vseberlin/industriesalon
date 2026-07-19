<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_query_includes_place_post_type($post_type): bool
{
    if ($post_type === null || $post_type === '' || $post_type === 'any') {
        return true;
    }

    if (is_string($post_type)) {
        return sanitize_key($post_type) === ISS_REGISTER_POST_TYPE;
    }

    if (is_array($post_type)) {
        return in_array(ISS_REGISTER_POST_TYPE, array_map('sanitize_key', $post_type), true);
    }

    return false;
}

function iss_register_exclude_tour_only_places_from_search(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    if (!iss_register_query_includes_place_post_type($query->get('post_type'))) {
        return;
    }

    $meta_query = $query->get('meta_query');
    if (!is_array($meta_query)) {
        $meta_query = [];
    }

    $meta_query[] = iss_register_build_public_place_visibility_meta_query_clause();
    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'iss_register_exclude_tour_only_places_from_search', 20);

function iss_register_exclude_tour_only_places_from_sitemaps(array $args, string $post_type): array
{
    if ($post_type !== ISS_REGISTER_POST_TYPE) {
        return $args;
    }

    $meta_query = isset($args['meta_query']) && is_array($args['meta_query'])
        ? $args['meta_query']
        : [];

    $meta_query[] = iss_register_build_public_place_visibility_meta_query_clause();
    // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemap visibility uses the same public place meta contract as frontend search.
    $args['meta_query'] = $meta_query;

    return $args;
}
add_filter('wp_sitemaps_posts_query_args', 'iss_register_exclude_tour_only_places_from_sitemaps', 10, 2);
