<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_register_meta(): void
{
    $schema = [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'target' => [
                    'type' => 'object',
                    'properties' => [
                        'entity_id' => [
                            'type' => 'integer',
                        ],
                        'entity_kind' => [
                            'type' => 'string',
                        ],
                        'post_id' => [
                            'type' => 'integer',
                        ],
                    ],
                    'required' => ['entity_id', 'entity_kind', 'post_id'],
                    'additionalProperties' => false,
                ],
                'relation_family' => [
                    'type' => 'string',
                ],
                'relation_type' => [
                    'type' => 'string',
                ],
                'relation_role' => [
                    'type' => 'string',
                ],
                'place_id' => [
                    'type' => 'integer',
                ],
                'role' => [
                    'type' => 'string',
                ],
                'weight' => [
                    'type' => 'integer',
                ],
                'label' => [
                    'type' => 'string',
                ],
                'route_title' => [
                    'type' => 'string',
                ],
                'route_teaser' => [
                    'type' => 'string',
                ],
                'station_object_id' => [
                    'type' => 'integer',
                ],
                'station_story_id' => [
                    'type' => 'integer',
                ],
            ],
            'additionalProperties' => false,
        ],
    ];

    foreach (iss_relations_get_supported_post_types() as $post_type) {
        register_post_meta($post_type, ISS_RELATIONS_META_KEY, [
            'single' => true,
            'type' => 'array',
            'default' => [],
            'show_in_rest' => [
                'schema' => $schema,
            ],
            'sanitize_callback' => 'iss_relations_sanitize_meta_value',
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
}
add_action('init', 'iss_relations_register_meta', 20);

function iss_relations_sanitize_meta_value($value): array
{
    return iss_relations_normalize_relations(is_array($value) ? $value : []);
}
