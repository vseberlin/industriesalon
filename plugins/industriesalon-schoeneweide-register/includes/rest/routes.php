<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_places_route_args(): array
{
    return [
        'search' => [
            'type' => 'string',
            'required' => false,
        ],
        'area' => [
            'type' => 'string',
            'required' => false,
        ],
        'status' => [
            'type' => 'string',
            'required' => false,
        ],
        'role' => [
            'type' => 'string',
            'required' => false,
        ],
        'current_status' => [
            'type' => 'string',
            'required' => false,
        ],
        'current_use_type' => [
            'type' => 'string',
            'required' => false,
        ],
        'tag' => [
            'type' => 'string',
            'required' => false,
        ],
        'unclear' => [
            'required' => false,
        ],
        'orderby' => [
            'type' => 'string',
            'required' => false,
            'validate_callback' => static function ($value): bool {
                return $value === null || $value === '' || iss_register_place_query_allows_orderby($value);
            },
        ],
        'order' => [
            'type' => 'string',
            'required' => false,
            'validate_callback' => static function ($value): bool {
                return $value === null || $value === '' || iss_register_place_query_allows_order($value);
            },
        ],
        'fields' => [
            'type' => 'string',
            'required' => false,
            'validate_callback' => static function ($value): bool {
                return $value === null || $value === '' || iss_register_validate_summary_contract_fields_request($value);
            },
            'description' => 'Comma-separated subset of summary fields.',
        ],
    ];
}

function iss_register_rest_get_places(WP_REST_Request $request): WP_REST_Response
{
    $query_args = iss_register_get_place_query_args_from_request($request);
    $fields = iss_register_parse_summary_contract_fields($request->get_param('fields'));

    return rest_ensure_response(
        iss_register_get_summary_places_contracts($query_args, $fields)
    );
}

function iss_register_rest_export_places(WP_REST_Request $request): WP_REST_Response
{
    $places = iss_register_get_export_places_contracts(
        iss_register_get_place_query_args_from_request($request)
    );

    $response = rest_ensure_response([
        'generatedAt' => gmdate('c'),
        'total' => count($places),
        'places' => array_values($places),
    ]);

    $response->header('Content-Disposition', 'attachment; filename="schoeneweide-register-export.json"');

    return $response;
}

function iss_register_rest_get_atlas_places(): WP_REST_Response
{
    return rest_ensure_response(iss_register_get_atlas_places_data());
}

function iss_register_rest_get_atlas_context(WP_REST_Request $request): WP_REST_Response
{
    $era_slug = sanitize_title((string) $request->get_param('era'));
    $context = iss_register_get_atlas_context_data();

    if ($era_slug !== '') {
        $context['stories'] = array_values(array_filter((array) ($context['stories'] ?? []), static function (array $story) use ($era_slug): bool {
            return in_array($era_slug, (array) ($story['era_slugs'] ?? []), true);
        }));
    }

    return rest_ensure_response($context);
}

function iss_register_rest_get_place(WP_REST_Request $request)
{
    $target_id = trim((string) $request['id']);
    if ($target_id === '') {
        return new WP_Error('iss_register_missing_id', 'Missing place id.', ['status' => 400]);
    }

    $place = iss_register_get_detail_place_contract($target_id);
    if (is_array($place)) {
        return rest_ensure_response($place);
    }

    return new WP_Error('iss_register_not_found', 'Place not found.', ['status' => 404]);
}

function iss_register_rest_get_meta(): WP_REST_Response
{
    return rest_ensure_response(iss_register_get_meta_contract());
}

function iss_register_register_rest_routes(): void
{
    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/places', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_places',
        'permission_callback' => '__return_true',
        'args' => iss_register_get_places_route_args(),
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/export', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_export_places',
        'permission_callback' => '__return_true',
        'args' => iss_register_get_places_route_args(),
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/atlas', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_atlas_places',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/atlas-context', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_atlas_context',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/places/(?P<id>[^/]+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_place',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/meta', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_meta',
        'permission_callback' => '__return_true',
    ]);
}

add_action('rest_api_init', 'iss_register_register_rest_routes');
