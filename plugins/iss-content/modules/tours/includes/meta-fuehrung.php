<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_fuehrungen_meta_fields() {
    return [
        'duration' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'meeting_point' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'target_group' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'price_note' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'booking_price_cents' => [
            'type' => 'integer',
            'sanitize' => 'absint',
            'default' => 0,
        ],
        'booking_note' => [
            'type' => 'string',
            'sanitize' => 'sanitize_textarea_field',
            'default' => '',
        ],
        'booking_mode' => [
            'type' => 'string',
            'sanitize' => static function ($value) {
                $value = sanitize_key((string) $value);
                $allowed = ['auto', 'calendar', 'on_demand', 'hybrid'];
                return in_array($value, $allowed, true) ? $value : 'auto';
            },
            'default' => 'auto',
        ],
        'allow_on_demand_with_calendar' => [
            'type' => 'boolean',
            'sanitize' => static function ($value) {
                return !empty($value);
            },
            'default' => false,
        ],
        'inquiry_url' => [
            'type' => 'string',
            'sanitize' => static function ($value) {
                return esc_url_raw(trim((string) $value));
            },
            'default' => '',
        ],
        'inquiry_label' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        'inquiry_note' => [
            'type' => 'string',
            'sanitize' => 'sanitize_textarea_field',
            'default' => '',
        ],
        'tour_badge' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
    ];
}

add_action('init', function () {
    foreach (iss_fuehrungen_meta_fields() as $key => $config) {
        register_post_meta(ISS_FUEHRUNGEN_POST_TYPE, $key, [
            'single'            => true,
            'type'              => $config['type'],
            'default'           => $config['default'],
            'show_in_rest'      => true,
            'sanitize_callback' => $config['sanitize'],
            'auth_callback'     => static function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
});

function iss_fuehrungen_get_meta($post_id, $key, $default = '') {
    $value = get_post_meta($post_id, $key, true);
    return ($value === '' || $value === null) ? $default : $value;
}
