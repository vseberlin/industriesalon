<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_clear_places_cache(): void
{
    global $wpdb;

    delete_transient('iss_register_places_cache');
    delete_transient('iss_register_atlas_places_cache');
    delete_transient('iss_register_place_tour_usage_map_cache');

    $like_timeout = $wpdb->esc_like('_transient_timeout_iss_register_atlas_places_cache:') . '%';
    $like_value = $wpdb->esc_like('_transient_iss_register_atlas_places_cache:') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like_timeout, $like_value));
}

function iss_register_should_clear_places_cache_for_post_type($post_type): bool
{
    $post_type = sanitize_key((string) $post_type);

    return in_array($post_type, [
        ISS_REGISTER_POST_TYPE,
        'fuehrung',
    ], true);
}

function iss_register_maybe_clear_places_cache_on_relations_meta($meta_id, int $post_id, string $meta_key): void
{
    if (!defined('ISS_RELATIONS_META_KEY') || $meta_key !== ISS_RELATIONS_META_KEY) {
        return;
    }

    if (!iss_register_should_clear_places_cache_for_post_type(get_post_type($post_id))) {
        return;
    }

    iss_register_clear_places_cache();
}

function iss_register_maybe_clear_places_cache_on_deleted_post(int $post_id): void
{
    if (!iss_register_should_clear_places_cache_for_post_type(get_post_type($post_id))) {
        return;
    }

    iss_register_clear_places_cache();
}

add_action('added_post_meta', 'iss_register_maybe_clear_places_cache_on_relations_meta', 10, 3);
add_action('updated_post_meta', 'iss_register_maybe_clear_places_cache_on_relations_meta', 10, 3);
add_action('deleted_post_meta', 'iss_register_maybe_clear_places_cache_on_relations_meta', 10, 3);
add_action('save_post_' . ISS_REGISTER_POST_TYPE, 'iss_register_clear_places_cache');
add_action('save_post_fuehrung', 'iss_register_clear_places_cache');
add_action('before_delete_post', 'iss_register_maybe_clear_places_cache_on_deleted_post');
