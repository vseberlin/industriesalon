<?php
if (!defined('ABSPATH')) exit;

/**
 * Timeline uses the calendar CPT as its storage layer.
 * Calendar owns the CPT definition; timeline only registers extra meta + taxonomy.
 */
function iss_timeline_get_post_type() {
    return defined('ISS_CALENDAR_ITEM_POST_TYPE') ? ISS_CALENDAR_ITEM_POST_TYPE : 'iss_calendar_item';
}

add_action('init', function () {
    $post_type = iss_timeline_get_post_type();
    if (!$post_type || !post_type_exists($post_type)) {
        // Calendar CPT not registered (yet).
        return;
    }

    // Optional grouping taxonomy for timeline presentation.
    register_taxonomy('iss_timeline_group', [$post_type], [
        'label' => 'Timeline Groups',
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
    ]);

    iss_timeline_register_meta($post_type);
}, 20);

function iss_timeline_register_meta($post_type) {
    $fields = [
        'public_title' => ['type' => 'string'],
        'public_summary' => ['type' => 'string'],
        // Reuse item_type from calendar CPT; but allow editing here too.
        'cta_mode' => ['type' => 'string'],   // auto|details|booking|external
        'cta_url' => ['type' => 'string'],
        'cta_label' => ['type' => 'string'],
        'is_visible' => ['type' => 'integer'], // timeline-only visibility toggle
    ];

    foreach ($fields as $key => $cfg) {
        register_post_meta($post_type, $key, [
            'type' => $cfg['type'],
            'single' => true,
            'show_in_rest' => true,
            'sanitize_callback' => 'iss_timeline_sanitize_meta_value',
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
}

function iss_timeline_sanitize_meta_value($value, $meta_key, $meta_type) {
    if (is_array($value) || is_object($value)) return null;

    if ($meta_key === 'is_visible') {
        return (int) $value;
    }

    if ($meta_key === 'cta_url') {
        return esc_url_raw((string) $value);
    }

    return sanitize_text_field((string) $value);
}

