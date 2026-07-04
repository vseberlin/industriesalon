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
    if (iss_relations_supports_route_drafts($post_id)) {
        return new WP_Error(
            'iss_relations_route_locked',
            __('Routenstationen werden nur über den Route-Entwurf veröffentlicht.', 'iss-relations'),
            ['status' => 409]
        );
    }

    $params = $request->get_json_params();
    $relations = is_array($params['relations'] ?? null) ? $params['relations'] : [];

    return rest_ensure_response([
        'ok' => true,
        'relations' => iss_relations_save_post_relations($post_id, $relations),
    ]);
}

function iss_relations_rest_can_edit_route_draft(WP_REST_Request $request): bool
{
    $post_id = absint($request->get_param('post_id'));

    return $post_id > 0
        && current_user_can('edit_post', $post_id)
        && iss_relations_supports_route_drafts($post_id);
}

function iss_relations_rest_route_draft_payload(int $post_id): array
{
    $draft = iss_relations_get_route_draft($post_id);
    $canonical = iss_relations_get_stored_post_relations($post_id);

    return [
        'ok' => true,
        'locked' => !$draft,
        'canonical' => $canonical,
        'draft' => $draft,
        'relations' => $draft ? (array) ($draft['relations'] ?? []) : $canonical,
        'trash' => $draft ? (array) ($draft['trash'] ?? []) : [],
        'previewArgs' => iss_relations_get_route_draft_preview_args($post_id),
    ];
}

function iss_relations_rest_get_route_draft(WP_REST_Request $request)
{
    return rest_ensure_response(iss_relations_rest_route_draft_payload(absint($request->get_param('post_id'))));
}

function iss_relations_rest_save_route_draft(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    $params = $request->get_json_params();
    $action = sanitize_key((string) ($params['action'] ?? 'save'));

    if ($action === 'unlock') {
        iss_relations_create_route_draft($post_id);
        return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id));
    }

    if ($action === 'discard') {
        iss_relations_discard_route_draft($post_id);
        return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id));
    }

    if ($action === 'publish') {
        $draft = iss_relations_get_route_draft($post_id);
        if (!$draft) {
            return new WP_Error(
                'iss_relations_route_no_draft',
                __('Es gibt keinen Routen-Entwurf zum Veröffentlichen.', 'iss-relations'),
                ['status' => 409]
            );
        }

        iss_relations_publish_route_draft($post_id);
        return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id));
    }

    $relations = is_array($params['relations'] ?? null) ? $params['relations'] : [];
    $trash = is_array($params['trash'] ?? null) ? $params['trash'] : [];
    $base_hash = sanitize_text_field((string) ($params['baseHash'] ?? ''));
    iss_relations_save_route_draft($post_id, $relations, $trash, $base_hash);

    return rest_ensure_response(iss_relations_rest_route_draft_payload($post_id));
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

    register_rest_route('iss-relations/v1', '/posts/(?P<post_id>\d+)/route-draft', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_relations_rest_get_route_draft',
            'permission_callback' => 'iss_relations_rest_can_edit_route_draft',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_relations_rest_save_route_draft',
            'permission_callback' => 'iss_relations_rest_can_edit_route_draft',
        ],
    ]);
}
add_action('rest_api_init', 'iss_relations_register_relation_rest_routes');
