<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_rest_can_edit_post_relations(WP_REST_Request $request): bool
{
    $post_id = absint($request->get_param('post_id'));

    return $post_id > 0
        && current_user_can('edit_post', $post_id)
        && iss_relations_is_supported_post_type((string) get_post_type($post_id));
}

function iss_relations_rest_get_post_relations(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    $stored = get_post_meta($post_id, ISS_RELATIONS_META_KEY, true);

    return rest_ensure_response([
        'ok' => true,
        'relations' => iss_relations_normalize_relations(is_array($stored) ? $stored : [], $post_id),
    ]);
}

function iss_relations_rest_save_post_relations(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    $params = $request->get_json_params();
    $relations = is_array($params['relations'] ?? null) ? $params['relations'] : [];

    return rest_ensure_response([
        'ok' => true,
        'relations' => iss_relations_save_post_relations($post_id, $relations),
    ]);
}

function iss_relations_register_relation_rest_routes(): void
{
    register_rest_route('iss-relations/v1', '/posts/(?P<post_id>\d+)/places', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_relations_rest_get_post_relations',
            'permission_callback' => 'iss_relations_rest_can_edit_post_relations',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_relations_rest_save_post_relations',
            'permission_callback' => 'iss_relations_rest_can_edit_post_relations',
            'args' => [
                'relations' => [
                    'required' => true,
                    'type' => 'array',
                ],
            ],
        ],
    ]);
}
add_action('rest_api_init', 'iss_relations_register_relation_rest_routes');
