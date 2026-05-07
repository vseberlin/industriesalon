<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_meta_definitions() {
    return [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE => [
            'iss_start_datetime' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_end_datetime' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_location' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_timeline_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => true],
            'iss_timeline_item_id' => ['type' => 'integer', 'sanitize' => 'absint', 'default' => 0],
        ],
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE => [
            'iss_start_date' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_end_date' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_is_permanent' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
            'iss_timeline_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => true],
            'iss_timeline_item_id' => ['type' => 'integer', 'sanitize' => 'absint', 'default' => 0],
            'iss_corpus_chapter_ids' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_companion_publication_id' => ['type' => 'integer', 'sanitize' => 'absint', 'default' => 0],
        ],
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE => [
            'iss_period_label' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_timeline_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
            'iss_timeline_item_id' => ['type' => 'integer', 'sanitize' => 'absint', 'default' => 0],
        ],
        ISS_CONTENT_MODEL_TEAM_POST_TYPE => [
            'iss_role_label' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_email' => ['type' => 'string', 'sanitize' => 'sanitize_email', 'default' => ''],
            'iss_phone' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
        ],
    ];
}

function iss_content_model_register_meta() {
    foreach (iss_content_model_meta_definitions() as $post_type => $fields) {
        foreach ($fields as $key => $config) {
            register_post_meta($post_type, $key, [
                'type' => $config['type'] === 'boolean' ? 'boolean' : ($config['type'] === 'integer' ? 'integer' : 'string'),
                'single' => true,
                'show_in_rest' => true,
                'default' => $config['default'],
                'sanitize_callback' => 'iss_content_model_sanitize_meta_value',
                'auth_callback' => static function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }
}
add_action('init', 'iss_content_model_register_meta', 20);

function iss_content_model_sanitize_meta_value($value, $meta_key, $meta_type) {
    foreach (iss_content_model_meta_definitions() as $post_type => $fields) {
        if (!isset($fields[$meta_key])) {
            continue;
        }

        $config = $fields[$meta_key];
        $sanitizer = $config['sanitize'];
        $sanitized = is_callable($sanitizer) ? call_user_func($sanitizer, $value) : sanitize_text_field((string) $value);

        if ($config['type'] === 'boolean') {
            return (bool) $sanitized;
        }

        if ($config['type'] === 'integer') {
            return (int) $sanitized;
        }

        return (string) $sanitized;
    }

    return sanitize_text_field((string) $value);
}
