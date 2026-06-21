<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_editorial_rest_can_edit_document(WP_REST_Request $request): bool
{
    $post_id = absint($request->get_param('post_id'));
    $format_slug = sanitize_key((string) $request->get_param('format'));

    return $post_id > 0
        && current_user_can('edit_post', $post_id)
        && iss_editorial_post_type_supports_format((string) get_post_type($post_id), $format_slug);
}

function iss_editorial_rest_autosave_document(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    $format_slug = sanitize_key((string) $request->get_param('format'));
    $document = $request->get_param('document');

    if (!iss_editorial_save_document($post_id, $format_slug, $document, true)) {
        return new WP_Error('iss_editorial_autosave_failed', __('Editorial document autosave failed.', 'iss-editorial'), ['status' => 400]);
    }

    return rest_ensure_response([
        'ok' => true,
        'document' => iss_editorial_get_document($post_id, $format_slug, true),
    ]);
}

function iss_editorial_rest_save_document(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    $format_slug = sanitize_key((string) $request->get_param('format'));
    $document = $request->get_param('document');
    $enabled = rest_sanitize_boolean($request->get_param('enabled'));

    if (!iss_editorial_save_document($post_id, $format_slug, $document, false)) {
        return new WP_Error('iss_editorial_save_failed', __('Editorial document save failed.', 'iss-editorial'), ['status' => 400]);
    }

    iss_editorial_set_document_enabled($post_id, $format_slug, $enabled);
    delete_post_meta($post_id, iss_editorial_get_autosave_meta_key($format_slug));

    return rest_ensure_response([
        'ok' => true,
        'enabled' => iss_editorial_document_is_enabled($post_id, $format_slug),
        'document' => iss_editorial_get_document($post_id, $format_slug, false),
    ]);
}

function iss_editorial_register_rest_routes(): void
{
    register_rest_route('iss-editorial/v1', '/document/(?P<post_id>\d+)/(?P<format>[a-z0-9_-]+)/autosave', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_editorial_rest_autosave_document',
            'permission_callback' => 'iss_editorial_rest_can_edit_document',
            'args' => [
                'document' => [
                    'required' => true,
                ],
            ],
        ],
    ]);

    register_rest_route('iss-editorial/v1', '/document/(?P<post_id>\d+)/(?P<format>[a-z0-9_-]+)/save', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_editorial_rest_save_document',
            'permission_callback' => 'iss_editorial_rest_can_edit_document',
            'args' => [
                'document' => [
                    'required' => true,
                ],
                'enabled' => [
                    'required' => false,
                    'default' => false,
                ],
            ],
        ],
    ]);
}
add_action('rest_api_init', 'iss_editorial_register_rest_routes');
