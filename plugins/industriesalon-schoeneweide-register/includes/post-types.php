<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_register_post_types(): void
{
    $labels = [
        'name' => 'Schöneweide Register',
        'singular_name' => 'Ort / Standort',
        'menu_name' => 'Schöneweide Register',
        'name_admin_bar' => 'Ort / Standort',
        'add_new' => 'Neu hinzufügen',
        'add_new_item' => 'Ort / Standort hinzufügen',
        'edit_item' => 'Ort / Standort bearbeiten',
        'new_item' => 'Neuer Ort / Standort',
        'view_item' => 'Ort / Standort ansehen',
        'search_items' => 'Orte / Standorte durchsuchen',
        'not_found' => 'Keine Orte / Standorte gefunden',
        'not_found_in_trash' => 'Keine Orte / Standorte im Papierkorb gefunden',
        'all_items' => 'Alle Orte / Standorte',
    ];

    register_post_type(ISS_REGISTER_POST_TYPE, [
        'labels' => $labels,
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-location-alt',
        'has_archive' => false,
        'rewrite' => [
            'slug' => 'schoeneweide/orte',
            'with_front' => false,
            'feeds' => false,
            'pages' => false,
        ],
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions'],
    ]);
}

add_action('init', 'iss_register_register_post_types');

// Places need Gutenberg for editable prose/media while the global structured-CPT policy stays Classic-first.
function iss_register_use_block_editor_for_place_post_type(bool $use_block_editor, string $post_type): bool
{
    if ($post_type === ISS_REGISTER_POST_TYPE) {
        return true;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'iss_register_use_block_editor_for_place_post_type', 1000, 2);

function iss_register_use_block_editor_for_place_post(bool $use_block_editor, $post): bool
{
    if (is_numeric($post)) {
        $post = get_post((int) $post);
    }

    if ($post instanceof WP_Post && $post->post_type === ISS_REGISTER_POST_TYPE) {
        return true;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post', 'iss_register_use_block_editor_for_place_post', 1000, 2);

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
    $args['meta_query'] = $meta_query;

    return $args;
}
add_filter('wp_sitemaps_posts_query_args', 'iss_register_exclude_tour_only_places_from_sitemaps', 10, 2);
