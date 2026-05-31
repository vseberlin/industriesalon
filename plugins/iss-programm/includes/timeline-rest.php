<?php
if (!defined('ABSPATH')) exit;

define('ISS_TIMELINE_REST_CACHE_VERSION_OPTION', 'iss_timeline_rest_cache_version');

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

function iss_timeline_rest_get_max_limit() {
    $max_limit = (int) apply_filters('iss_timeline_rest_max_limit', 48);
    if ($max_limit < 1) {
        $max_limit = 48;
    }

    return $max_limit;
}

function iss_timeline_rest_get_cache_ttl() {
    $ttl = (int) apply_filters('iss_timeline_rest_cache_ttl', 5 * MINUTE_IN_SECONDS);
    if ($ttl < 1) {
        $ttl = MINUTE_IN_SECONDS;
    }

    return $ttl;
}

function iss_timeline_rest_normalize_limit($value) {
    $max_limit = iss_timeline_rest_get_max_limit();
    $limit = (int) $value;

    if ($limit <= 0 || $limit > $max_limit) {
        return $max_limit;
    }

    return $limit;
}

function iss_timeline_rest_prepare_query_args($params) {
    $params = is_array($params) ? $params : [];

    $query_args = [
        'limit' => iss_timeline_rest_get_max_limit(),
    ];
    if (isset($params['limit'])) {
        $query_args['limit'] = iss_timeline_rest_normalize_limit($params['limit']);
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
        'groupRecurringTours' => iss_timeline_rest_to_bool($render['groupRecurringTours'] ?? false, false),
        'order' => strtoupper(sanitize_text_field((string) ($params['order'] ?? 'ASC'))),
        'showMeta' => iss_timeline_rest_to_bool($render['showMeta'] ?? true, true),
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

function iss_timeline_rest_array_is_list(array $value) {
    $index = 0;
    foreach (array_keys($value) as $key) {
        if ($key !== $index) {
            return false;
        }
        $index++;
    }

    return true;
}

function iss_timeline_rest_normalize_cache_value($value) {
    if (!is_array($value)) {
        return $value;
    }

    if (iss_timeline_rest_array_is_list($value)) {
        return array_map('iss_timeline_rest_normalize_cache_value', $value);
    }

    ksort($value);

    $normalized = [];
    foreach ($value as $key => $item) {
        $normalized[$key] = iss_timeline_rest_normalize_cache_value($item);
    }

    return $normalized;
}

function iss_timeline_rest_get_cache_version() {
    $version = (int) get_option(ISS_TIMELINE_REST_CACHE_VERSION_OPTION, 1);
    return $version > 0 ? $version : 1;
}

function iss_timeline_rest_build_cache_key($query_args, $render_opts) {
    $payload = [
        'version' => iss_timeline_rest_get_cache_version(),
        'query' => iss_timeline_rest_normalize_cache_value(is_array($query_args) ? $query_args : []),
        'render' => iss_timeline_rest_normalize_cache_value(is_array($render_opts) ? $render_opts : []),
    ];

    return 'iss_timeline_rest_' . md5((string) wp_json_encode($payload));
}

function iss_timeline_rest_bump_cache_version() {
    static $bumped = false;

    if ($bumped) {
        return;
    }

    $bumped = true;
    update_option(ISS_TIMELINE_REST_CACHE_VERSION_OPTION, iss_timeline_rest_get_cache_version() + 1, false);
}

function iss_timeline_rest_maybe_bump_cache_version_for_calendar_item($post_id) {
    if (get_post_type($post_id) !== 'iss_calendar_item') {
        return;
    }

    iss_timeline_rest_bump_cache_version();
}

function iss_timeline_rest_maybe_bump_cache_version_on_post_meta($meta_id, $post_id) {
    iss_timeline_rest_maybe_bump_cache_version_for_calendar_item((int) $post_id);
}

function iss_timeline_rest_render_collection(WP_REST_Request $request) {
    $params = iss_timeline_rest_collect_request_data($request);
    $query_args = iss_timeline_rest_prepare_query_args($params);
    $render_opts = iss_timeline_rest_prepare_render_opts($params);
    $cache_key = iss_timeline_rest_build_cache_key($query_args, $render_opts);
    $listing = get_transient($cache_key);

    if (!is_array($listing)) {
        $listing = iss_timeline_get_listing_response($query_args, $render_opts);
        set_transient($cache_key, $listing, iss_timeline_rest_get_cache_ttl());
    }

    return rest_ensure_response([
        'html' => $listing['html'],
        'count' => (int) $listing['count'],
        'batchCount' => (int) $listing['batchCount'],
        'isEmpty' => (bool) $listing['isEmpty'],
        'offset' => (int) $listing['offset'],
        'nextOffset' => (int) $listing['nextOffset'],
        'hasMore' => (bool) $listing['hasMore'],
    ]);
}

add_action('rest_api_init', function () {
    register_rest_route('iss-programm/v1', '/timeline', [
        'methods' => [WP_REST_Server::READABLE, WP_REST_Server::CREATABLE],
        'callback' => 'iss_timeline_rest_render_collection',
        'permission_callback' => '__return_true',
    ]);
});

add_action('save_post_iss_calendar_item', 'iss_timeline_rest_bump_cache_version');
add_action('before_delete_post', 'iss_timeline_rest_maybe_bump_cache_version_for_calendar_item');
add_action('added_post_meta', 'iss_timeline_rest_maybe_bump_cache_version_on_post_meta', 10, 2);
add_action('updated_post_meta', 'iss_timeline_rest_maybe_bump_cache_version_on_post_meta', 10, 2);
add_action('deleted_post_meta', 'iss_timeline_rest_maybe_bump_cache_version_on_post_meta', 10, 2);
