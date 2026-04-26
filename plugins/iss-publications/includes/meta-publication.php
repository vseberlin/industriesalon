<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_meta_fields() {
    return [
        '_iss_publication_subtitle' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_author' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_editor' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_year' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_pages' => [
            'type' => 'integer',
            'sanitize' => 'absint',
            'default' => 0,
        ],
        '_iss_publication_format' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_language' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_isbn' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_publisher' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_sale_enabled' => [
            'type' => 'boolean',
            'sanitize' => static function ($value) {
                return !empty($value);
            },
            'default' => false,
        ],
        '_iss_publication_price_cents' => [
            'type' => 'integer',
            'sanitize' => 'absint',
            'default' => 0,
        ],
        '_iss_publication_cta_label' => [
            'type' => 'string',
            'sanitize' => 'sanitize_text_field',
            'default' => '',
        ],
        '_iss_publication_gateway_description' => [
            'type' => 'string',
            'sanitize' => 'sanitize_textarea_field',
            'default' => '',
        ],
        '_iss_publication_featured' => [
            'type' => 'boolean',
            'sanitize' => static function ($value) {
                return !empty($value);
            },
            'default' => false,
        ],
    ];
}

add_action('init', function () {
    foreach (iss_publications_meta_fields() as $key => $config) {
        register_post_meta(ISS_PUBLICATIONS_POST_TYPE, $key, [
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

function iss_publications_get_meta($post_id, $key, $default = '') {
    $value = get_post_meta($post_id, $key, true);
    return ($value === '' || $value === null) ? $default : $value;
}
