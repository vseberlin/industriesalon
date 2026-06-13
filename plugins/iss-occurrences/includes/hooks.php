<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_occurrences_post_save_should_skip(int $post_id): bool
{
    if ($post_id <= 0) {
        return true;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return true;
    }
    if (wp_is_post_revision($post_id)) {
        return true;
    }

    return false;
}

add_action('save_post', function (int $post_id, WP_Post $post): void {
    if (iss_occurrences_post_save_should_skip($post_id)) {
        return;
    }

    if (!in_array($post->post_type, iss_occurrences_supported_source_post_types(), true)) {
        return;
    }

    iss_occurrences_sync_source($post_id);
}, 60, 2);

add_action('trashed_post', function (int $post_id): void {
    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, iss_occurrences_supported_source_post_types(), true)) {
        return;
    }

    iss_occurrences_get_service()->delete_source_occurrences($post_id, $post_type);
});

add_action('before_delete_post', function (int $post_id): void {
    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, iss_occurrences_supported_source_post_types(), true)) {
        return;
    }

    iss_occurrences_get_service()->delete_source_occurrences($post_id, $post_type);
});

add_action('set_object_terms', function ($object_id): void {
    $post_id = (int) $object_id;
    $post_type = (string) get_post_type($post_id);
    if (!in_array($post_type, iss_occurrences_supported_source_post_types(), true)) {
        return;
    }

    iss_occurrences_sync_source($post_id);
}, 20, 1);
