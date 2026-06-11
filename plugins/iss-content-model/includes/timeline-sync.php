<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_timeline_enabled_post_types() {
    return [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
    ];
}

function iss_content_model_sync_ausstellung_permanent_meta($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return;
    }

    $is_permanent = iss_content_model_ausstellung_is_permanent($post_id);

    if ($is_permanent) {
        update_post_meta($post_id, 'iss_is_permanent', '1');
        return;
    }

    delete_post_meta($post_id, 'iss_is_permanent');
}

function iss_content_model_ausstellung_is_permanent($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return false;
    }

    $type_slugs = wp_get_post_terms($post_id, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, ['fields' => 'slugs']);
    return !is_wp_error($type_slugs) && in_array('dauerausstellung', array_map('sanitize_title', (array) $type_slugs), true);
}

function iss_content_model_resync_occurrence_for_post($post_id): void {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_occurrences_sync_source')) {
        return;
    }

    iss_occurrences_sync_source($post_id);
}

add_action('save_post', function ($post_id, $post) {
    if (!$post instanceof WP_Post) {
        return;
    }

    if (!in_array($post->post_type, iss_content_model_timeline_enabled_post_types(), true)) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }

    if ($post->post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        iss_content_model_sync_ausstellung_permanent_meta($post_id);
    }

}, 30, 2);

add_action('set_object_terms', function ($object_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy !== ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY) {
        return;
    }

    iss_content_model_sync_ausstellung_permanent_meta((int) $object_id);
    iss_content_model_resync_occurrence_for_post((int) $object_id);
}, 10, 4);
