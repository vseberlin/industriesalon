<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_search_rest_get_route_args(): array
{
    return [
        'q' => [
            'type' => 'string',
            'required' => false,
        ],
        'search' => [
            'type' => 'string',
            'required' => false,
        ],
        'type' => [
            'type' => 'string',
            'required' => false,
        ],
        'types' => [
            'type' => 'string',
            'required' => false,
        ],
        'limit' => [
            'type' => 'integer',
            'required' => false,
            'validate_callback' => static function ($value): bool {
                return $value === null || ((int) $value >= 1 && (int) $value <= 100);
            },
        ],
    ];
}

function iss_search_rest_search(WP_REST_Request $request)
{
    $query = iss_graph_normalize_public_search_query((string) ($request->get_param('q') ?: $request->get_param('search') ?: ''));
    $types = iss_search_normalize_public_search_types($request->get_param('type') ?: $request->get_param('types') ?: []);
    $limit = iss_search_normalize_limit($request->get_param('limit'), 24, 100);

    $hits = iss_search_public_posts($query, [
        'types' => $types,
        'limit' => $limit,
    ]);

    if (is_wp_error($hits)) {
        return $hits;
    }

    $items = [];
    foreach (is_array($hits) ? $hits : [] as $hit) {
        $item = is_array($hit) ? iss_search_prepare_result($hit) : null;
        if ($item) {
            $items[] = $item;
        }
    }

    return rest_ensure_response([
        'query' => $query,
        'provider' => iss_search_get_provider_name(),
        'types' => $types,
        'limit' => $limit,
        'count' => count($items),
        'items' => $items,
        'available_types' => array_values(iss_search_get_public_type_options()),
    ]);
}

function iss_search_register_rest_routes(): void
{
    register_rest_route('iss-search/v1', '/search', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_search_rest_search',
        'permission_callback' => '__return_true',
        'args' => iss_search_rest_get_route_args(),
    ]);
}

add_action('rest_api_init', 'iss_search_register_rest_routes');
