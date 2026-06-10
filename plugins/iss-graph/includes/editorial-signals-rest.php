<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_rest_get_editorial_signal_context_post_id(WP_REST_Request $request): int
{
    return absint($request->get_param('contextPostId') ?: $request->get_param('context_post_id'));
}

function iss_graph_rest_get_editorial_signal_target_post_id(WP_REST_Request $request): int
{
    return absint($request->get_param('targetPostId') ?: $request->get_param('target_post_id'));
}

function iss_graph_rest_editorial_signals_can_edit(WP_REST_Request $request): bool
{
    $context_post_id = iss_graph_rest_get_editorial_signal_context_post_id($request);

    return $context_post_id > 0 && iss_graph_current_user_can_edit_editorial_signals($context_post_id);
}

function iss_graph_rest_prepare_editorial_signal(array $signal): array
{
    $target_post_id = absint($signal['target_post_id'] ?? 0);
    $target = get_post($target_post_id);

    return [
        'id' => (int) ($signal['id'] ?? 0),
        'contextEntityId' => !empty($signal['context_entity_id']) ? (int) $signal['context_entity_id'] : null,
        'contextPostId' => (int) ($signal['context_post_id'] ?? 0),
        'targetEntityId' => !empty($signal['target_entity_id']) ? (int) $signal['target_entity_id'] : null,
        'targetPostId' => $target_post_id,
        'surface' => (string) ($signal['surface'] ?? 'related'),
        'signal' => (string) ($signal['signal'] ?? ''),
        'reason' => (string) ($signal['reason'] ?? ''),
        'expiresAt' => isset($signal['expires_at']) && $signal['expires_at'] !== null ? substr((string) $signal['expires_at'], 0, 10) : '',
        'authorUserId' => !empty($signal['author_user_id']) ? (int) $signal['author_user_id'] : null,
        'status' => (string) ($signal['status'] ?? 'active'),
        'target' => [
            'id' => $target_post_id,
            'title' => $target instanceof WP_Post ? (string) get_the_title($target) : '',
            'postType' => $target instanceof WP_Post ? (string) $target->post_type : '',
            'permalink' => $target instanceof WP_Post ? (string) get_permalink($target) : '',
        ],
    ];
}

function iss_graph_rest_editorial_signal_list_response(int $context_post_id, string $surface): WP_REST_Response
{
    $signals = iss_graph_get_active_editorial_signals_for_post($context_post_id, $surface);
    $items = array_map('iss_graph_rest_prepare_editorial_signal', $signals);

    return new WP_REST_Response([
        'items' => array_values($items),
        'count' => count($items),
    ]);
}

function iss_graph_rest_list_editorial_signals(WP_REST_Request $request): WP_REST_Response
{
    $context_post_id = iss_graph_rest_get_editorial_signal_context_post_id($request);
    $surface = iss_graph_get_service()->normalize_editorial_signal_surface((string) ($request->get_param('surface') ?: 'related'));

    return iss_graph_rest_editorial_signal_list_response($context_post_id, $surface);
}

function iss_graph_rest_save_editorial_signal(WP_REST_Request $request)
{
    $context_post_id = iss_graph_rest_get_editorial_signal_context_post_id($request);
    $target_post_id = iss_graph_rest_get_editorial_signal_target_post_id($request);
    $signal = iss_graph_get_service()->normalize_editorial_signal_type((string) $request->get_param('signal'));

    if ($context_post_id <= 0 || $target_post_id <= 0 || $signal === '') {
        return new WP_Error(
            'iss_graph_invalid_editorial_signal',
            __('Provide a valid context post, target post, and editorial signal.', 'iss-graph'),
            ['status' => 400]
        );
    }

    if (!iss_graph_editorial_signal_target_is_allowed($context_post_id, $target_post_id)) {
        return new WP_Error(
            'iss_graph_invalid_editorial_signal_target',
            __('The selected target is not available for this editorial signal context.', 'iss-graph'),
            ['status' => 400]
        );
    }

    $row = iss_graph_upsert_editorial_signal_for_post($context_post_id, $target_post_id, $signal, [
        'surface' => (string) ($request->get_param('surface') ?: 'related'),
        'reason' => (string) ($request->get_param('reason') ?: ''),
        'expires_at' => $request->get_param('expiresAt') ?: $request->get_param('expires_at'),
        'author_user_id' => get_current_user_id(),
        'status' => 'active',
    ]);

    if (!$row) {
        return new WP_Error(
            'iss_graph_editorial_signal_save_failed',
            __('The editorial signal could not be saved.', 'iss-graph'),
            ['status' => 400]
        );
    }

    return rest_ensure_response([
        'item' => iss_graph_rest_prepare_editorial_signal($row),
    ]);
}

function iss_graph_rest_remove_editorial_signal(WP_REST_Request $request): WP_REST_Response
{
    $context_post_id = iss_graph_rest_get_editorial_signal_context_post_id($request);
    $target_post_id = iss_graph_rest_get_editorial_signal_target_post_id($request);
    $surface = iss_graph_get_service()->normalize_editorial_signal_surface((string) ($request->get_param('surface') ?: 'related'));

    $removed = iss_graph_remove_editorial_signal_for_post($context_post_id, $target_post_id, $surface);

    return new WP_REST_Response([
        'removed' => $removed,
    ]);
}

function iss_graph_register_editorial_signal_rest_routes(): void
{
    register_rest_route('iss-graph/v1', '/editorial-signals', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_graph_rest_list_editorial_signals',
            'permission_callback' => 'iss_graph_rest_editorial_signals_can_edit',
            'args' => [
                'contextPostId' => [
                    'type' => 'integer',
                    'required' => true,
                ],
                'surface' => [
                    'type' => 'string',
                    'required' => false,
                ],
            ],
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_graph_rest_save_editorial_signal',
            'permission_callback' => 'iss_graph_rest_editorial_signals_can_edit',
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'iss_graph_rest_remove_editorial_signal',
            'permission_callback' => 'iss_graph_rest_editorial_signals_can_edit',
        ],
    ]);
}
add_action('rest_api_init', 'iss_graph_register_editorial_signal_rest_routes');
