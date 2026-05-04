<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_public_excerpt_candidate(int $post_id): string
{
    $sources = [
        (string) get_post_meta($post_id, 'history_short', true),
        (string) get_post_meta($post_id, 'current_use', true),
        (string) get_post_field('post_content', $post_id),
    ];

    foreach ($sources as $source) {
        $source = trim(wp_strip_all_tags($source));
        if ($source === '') {
            continue;
        }

        return wp_trim_words($source, 32, '');
    }

    return '';
}

function iss_register_get_public_image_candidate_id(int $post_id): int
{
    $featured_candidate = 0;
    $fallback_candidate = 0;

    foreach (iss_register_image_group_fields() as $field) {
        $images = get_post_meta($post_id, $field, true);
        if (!is_array($images)) {
            continue;
        }

        foreach ($images as $image) {
            if (!is_array($image) || ($image['visibility'] ?? '') !== 'public') {
                continue;
            }

            $media_id = absint($image['media_id'] ?? 0);
            if ($media_id <= 0) {
                continue;
            }

            if ($featured_candidate === 0 && !empty($image['is_featured'])) {
                $featured_candidate = $media_id;
            }

            if ($fallback_candidate === 0) {
                $fallback_candidate = $media_id;
            }
        }
    }

    return $featured_candidate > 0 ? $featured_candidate : $fallback_candidate;
}

function iss_register_sync_public_fields_for_post(int $post_id): void
{
    static $syncing = [];

    if ($post_id <= 0 || isset($syncing[$post_id])) {
        return;
    }

    if (wp_is_post_revision($post_id) || get_post_type($post_id) !== ISS_REGISTER_POST_TYPE) {
        return;
    }

    $post_update = ['ID' => $post_id];
    $needs_post_update = false;

    if (trim((string) get_post_field('post_excerpt', $post_id)) === '') {
        $excerpt_candidate = iss_register_get_public_excerpt_candidate($post_id);
        if ($excerpt_candidate !== '') {
            $post_update['post_excerpt'] = $excerpt_candidate;
            $needs_post_update = true;
        }
    }

    if ($needs_post_update) {
        $syncing[$post_id] = true;
        wp_update_post(wp_slash($post_update));
        unset($syncing[$post_id]);
    }

    if (!has_post_thumbnail($post_id)) {
        $thumbnail_candidate = iss_register_get_public_image_candidate_id($post_id);
        if ($thumbnail_candidate > 0) {
            set_post_thumbnail($post_id, $thumbnail_candidate);
        }
    }
}

function iss_register_sync_public_fields_on_save(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    iss_register_sync_public_fields_for_post($post_id);
}

add_action('save_post_' . ISS_REGISTER_POST_TYPE, 'iss_register_sync_public_fields_on_save', 30);
