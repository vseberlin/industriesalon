<?php
if (!defined('ABSPATH')) exit;

function iss_timeline_rest_to_bool($value, $default = false) {
    if (is_bool($value)) {
        return $value;
    }

    if (is_string($value)) {
        $value = strtolower(trim($value));
        if (in_array($value, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array($value, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }
    }

    if (is_numeric($value)) {
        return (int) $value === 1;
    }

    return (bool) $default;
}

function iss_timeline_rest_collect_request_data(WP_REST_Request $request) {
    $params = $request->get_json_params();
    if (!is_array($params) || empty($params)) {
        $params = $request->get_params();
    }

    return is_array($params) ? $params : [];
}

function iss_timeline_rest_prepare_query_args($params) {
    $params = is_array($params) ? $params : [];

    $query_args = [];
    if (isset($params['limit'])) {
        $query_args['limit'] = (int) $params['limit'];
    }
    if (isset($params['offset'])) {
        $query_args['offset'] = max(0, (int) $params['offset']);
    }
    if (isset($params['order'])) {
        $query_args['order'] = sanitize_text_field((string) $params['order']);
    }
    if (!empty($params['groups']) && is_array($params['groups'])) {
        $query_args['groups'] = $params['groups'];
    }
    if (!empty($params['filters']) && is_array($params['filters'])) {
        $query_args['filters'] = $params['filters'];
    }

    return $query_args;
}

function iss_timeline_rest_prepare_render_opts($params) {
    $params = is_array($params) ? $params : [];
    $render = isset($params['render']) && is_array($params['render']) ? $params['render'] : [];

    $opts = [
        'renderMode' => (($render['renderMode'] ?? 'timeline') === 'cards') ? 'cards' : 'timeline',
        'yearGrouping' => iss_timeline_rest_to_bool($render['yearGrouping'] ?? true, true),
        'order' => strtoupper(sanitize_text_field((string) ($params['order'] ?? 'ASC'))),
        'showDetailsButton' => iss_timeline_rest_to_bool($render['showDetailsButton'] ?? true, true),
        'showRecommendButton' => iss_timeline_rest_to_bool($render['showRecommendButton'] ?? true, true),
        'showTicketsButton' => iss_timeline_rest_to_bool($render['showTicketsButton'] ?? true, true),
        'detailsButtonUrl' => isset($render['detailsButtonUrl']) ? esc_url_raw((string) $render['detailsButtonUrl']) : '',
        'recommendButtonUrl' => isset($render['recommendButtonUrl']) ? esc_url_raw((string) $render['recommendButtonUrl']) : '',
        'ticketsButtonUrl' => isset($render['ticketsButtonUrl']) ? esc_url_raw((string) $render['ticketsButtonUrl']) : '',
        'detailsButtonText' => isset($render['detailsButtonText']) ? sanitize_text_field((string) $render['detailsButtonText']) : '',
        'recommendButtonText' => isset($render['recommendButtonText']) ? sanitize_text_field((string) $render['recommendButtonText']) : '',
        'ticketsButtonText' => isset($render['ticketsButtonText']) ? sanitize_text_field((string) $render['ticketsButtonText']) : '',
    ];

    if (!in_array($opts['order'], ['ASC', 'DESC'], true)) {
        $opts['order'] = 'ASC';
    }

    return $opts;
}

function iss_timeline_rest_render_collection(WP_REST_Request $request) {
    $params = iss_timeline_rest_collect_request_data($request);
    $query_args = iss_timeline_rest_prepare_query_args($params);
    $render_opts = iss_timeline_rest_prepare_render_opts($params);
    $listing = iss_timeline_get_listing_response($query_args, $render_opts);

    return rest_ensure_response([
        'html' => $listing['html'],
        'count' => (int) $listing['count'],
        'batchCount' => (int) $listing['batchCount'],
        'isEmpty' => (bool) $listing['isEmpty'],
        'offset' => (int) $listing['offset'],
        'nextOffset' => (int) $listing['nextOffset'],
        'hasMore' => (bool) $listing['hasMore'],
        'query' => function_exists('iss_timeline_build_query_args')
            ? iss_timeline_build_query_args($query_args)
            : $query_args,
    ]);
}

add_action('rest_api_init', function () {
    register_rest_route('iss-programm/v1', '/timeline', [
        'methods' => [WP_REST_Server::READABLE, WP_REST_Server::CREATABLE],
        'callback' => 'iss_timeline_rest_render_collection',
        'permission_callback' => '__return_true',
    ]);
});
