<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_ausstellung_is_permanent($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return false;
    }

    $type_slugs = wp_get_post_terms($post_id, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, ['fields' => 'slugs']);
    return !is_wp_error($type_slugs) && in_array('dauerausstellung', array_map('sanitize_title', (array) $type_slugs), true);
}

function iss_content_model_cleanup_retired_ausstellung_meta(): void {
    $cleanup_version = '2026-06-13-ausstellung-type-v1';
    if ((string) get_option('iss_content_model_ausstellung_type_cleanup', '') === $cleanup_version) {
        return;
    }

    delete_post_meta_by_key('iss_is_permanent');
    update_option('iss_content_model_ausstellung_type_cleanup', $cleanup_version, false);
}

add_action('init', 'iss_content_model_cleanup_retired_ausstellung_meta', 20);
