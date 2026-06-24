<?php

if (!defined('ABSPATH')) {
    exit;
}

const ISS_PUBLICATIONS_RELATED_PUBLICATIONS_META_KEY = '_iss_publication_related_publications';

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
        '_iss_publication_layout' => [
            'type' => 'string',
            'sanitize' => static function ($value) {
                $value = sanitize_key((string) $value);
                return in_array($value, ['standard', 'longread', 'timeline', 'photoalbum'], true) ? $value : 'standard';
            },
            'default' => 'standard',
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
            'auth_callback'     => static function (...$args) {
                $post_id = isset($args[2]) ? absint($args[2]) : 0;
                return $post_id > 0 ? current_user_can('edit_post', $post_id) : current_user_can('edit_publications');
            },
        ]);
    }

    register_post_meta(ISS_PUBLICATIONS_POST_TYPE, ISS_PUBLICATIONS_RELATED_PUBLICATIONS_META_KEY, [
        'single' => true,
        'type' => 'array',
        'default' => [],
        'show_in_rest' => [
            'schema' => [
                'type' => 'array',
                'items' => [
                    'type' => 'integer',
                ],
            ],
        ],
        'sanitize_callback' => 'iss_publications_sanitize_related_publication_ids',
        'auth_callback' => static function (...$args) {
            $post_id = isset($args[2]) ? absint($args[2]) : 0;
            return $post_id > 0 ? current_user_can('edit_post', $post_id) : current_user_can('edit_publications');
        },
    ]);
});

function iss_publications_get_meta($post_id, $key, $default = '') {
    $value = get_post_meta($post_id, $key, true);
    return ($value === '' || $value === null) ? $default : $value;
}

function iss_publications_sanitize_related_publication_ids($value): array
{
    $ids = is_array($value) ? $value : [];
    $ids = array_values(array_unique(array_filter(array_map('absint', $ids))));

    return array_values(array_filter($ids, static function (int $post_id): bool {
        return $post_id > 0
            && get_post_type($post_id) === ISS_PUBLICATIONS_POST_TYPE
            && get_post_status($post_id) !== false;
    }));
}

function iss_publications_get_related_publication_ids(int $post_id): array
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return [];
    }

    $ids = get_post_meta($post_id, ISS_PUBLICATIONS_RELATED_PUBLICATIONS_META_KEY, true);
    $ids = iss_publications_sanitize_related_publication_ids(is_array($ids) ? $ids : []);

    return array_values(array_filter($ids, static function (int $related_id) use ($post_id): bool {
        return $related_id !== $post_id && get_post_status($related_id) === 'publish';
    }));
}
