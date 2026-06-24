<?php
if (!defined('ABSPATH')) exit;

define('ISS_SUPERSAAS_OPTION_GROUP', 'iss_supersaas_options');
define('ISS_SUPERSAAS_SETTINGS_OPTION', 'iss_supersaas_settings');
define('ISS_SUPERSAAS_LEGACY_SETTINGS_OPTION', 'is_saas_settings');
define('ISS_SUPERSAAS_SETTINGS_PAGE', 'iss-supersaas-api');

require_once __DIR__ . '/includes/supersaas-sync.php';

function iss_supersaas_migrate_legacy_settings_option(): void {
    $legacy = get_option(ISS_SUPERSAAS_LEGACY_SETTINGS_OPTION, null);
    if ($legacy === null) {
        return;
    }

    if (get_option(ISS_SUPERSAAS_SETTINGS_OPTION, null) === null) {
        update_option(ISS_SUPERSAAS_SETTINGS_OPTION, is_array($legacy) ? $legacy : [], false);
    }

    delete_option(ISS_SUPERSAAS_LEGACY_SETTINGS_OPTION);
}
add_action('init', 'iss_supersaas_migrate_legacy_settings_option', 3);

function iss_supersaas_get_settings() {
    iss_supersaas_migrate_legacy_settings_option();

    $defaults = [
        'schedule_id'   => '',
        'api_key'       => '',
        'base_url'      => 'https://www.supersaas.de',
        'account_name'  => '',
        'schedule_path' => '',
    ];

    $settings = get_option(ISS_SUPERSAAS_SETTINGS_OPTION, []);
    if (!is_array($settings)) {
        $settings = [];
    }

    return array_merge($defaults, $settings);
}

function iss_supersaas_get_schedule_path($settings = null) {
    if ($settings === null) {
        $settings = iss_supersaas_get_settings();
    }

    if (!empty($settings['schedule_path'])) {
        return $settings['schedule_path'];
    }

    return '';
}

function iss_supersaas_normalize_schedule_path($schedule_path) {
    $schedule_path = trim((string) $schedule_path);
    if ($schedule_path === '') {
        return '';
    }

    return str_replace('%2F', '/', rawurlencode(rawurldecode($schedule_path)));
}

function iss_supersaas_load_admin_settings_api() {
    if (!function_exists('add_settings_section') && defined('ABSPATH')) {
        require_once ABSPATH . 'wp-admin/includes/template.php';
    }
}

function iss_supersaas_load_admin_menu_api() {
    if (!function_exists('add_options_page') && defined('ABSPATH')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
}

add_action('admin_notices', function () {
    $settings = iss_supersaas_get_settings();
    if (empty($settings['schedule_id']) || empty($settings['api_key'])) {
        $settings_url = admin_url((defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'options-general.php') . '?page=' . ISS_SUPERSAAS_SETTINGS_PAGE);
        echo '<div class="notice notice-warning is-dismissible"><p>'
            . '<strong>SuperSaaS API:</strong> '
            . 'API-Zugangsdaten fehlen. Das Buchungssystem ist nicht aktiv. '
            . '<a href="' . esc_url($settings_url) . '">Jetzt einrichten →</a>'
            . '</p></div>';
    }
});

function iss_supersaas_register_settings() {
    iss_supersaas_load_admin_settings_api();

    register_setting(
        ISS_SUPERSAAS_OPTION_GROUP,
        ISS_SUPERSAAS_SETTINGS_OPTION,
        [
            'sanitize_callback' => 'iss_supersaas_sanitize_settings',
            'default' => [],
        ]
    );

    add_settings_section(
        'iss_supersaas_main',
        'SuperSaaS Configuration',
        '__return_false',
        ISS_SUPERSAAS_OPTION_GROUP
    );

    add_settings_field('schedule_id', 'Schedule ID', 'iss_supersaas_field_schedule_id', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
    add_settings_field('api_key', 'API Key', 'iss_supersaas_field_api_key', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
    add_settings_field('base_url', 'API Base URL', 'iss_supersaas_field_base_url', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
    add_settings_field('account_name', 'Account Name', 'iss_supersaas_field_account_name', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
    add_settings_field('schedule_path', 'Schedule Path', 'iss_supersaas_field_schedule_path', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
}
add_action('admin_init', 'iss_supersaas_register_settings');
add_filter('option_page_capability_' . ISS_SUPERSAAS_OPTION_GROUP, function () {
    return function_exists('iss_core_capability') ? iss_core_capability('sync') : 'manage_options';
});

function iss_supersaas_sanitize_settings($input) {
    $out = [];
    $out['schedule_id']   = isset($input['schedule_id']) ? preg_replace('/[^0-9]/', '', $input['schedule_id']) : '';
    $out['api_key']       = isset($input['api_key']) ? sanitize_text_field($input['api_key']) : '';
    $out['base_url']      = isset($input['base_url']) ? esc_url_raw(trim($input['base_url'])) : '';
    $out['account_name']  = isset($input['account_name']) ? sanitize_text_field($input['account_name']) : '';
    $out['schedule_path'] = isset($input['schedule_path']) ? trim((string) $input['schedule_path']) : '';
    return $out;
}

function iss_supersaas_add_admin_menu() {
    iss_supersaas_load_admin_menu_api();

    $capability = function_exists('iss_core_capability') ? iss_core_capability('sync') : 'manage_options';

    add_submenu_page(
        defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : 'options-general.php',
        'SuperSaaS API',
        'SuperSaaS API',
        $capability,
        ISS_SUPERSAAS_SETTINGS_PAGE,
        'iss_supersaas_render_settings_page'
    );
}
add_action('admin_menu', 'iss_supersaas_add_admin_menu');

function iss_supersaas_render_settings_page() {
    iss_supersaas_load_admin_settings_api();
    iss_supersaas_load_admin_menu_api();
    if (function_exists('iss_require_cap')) {
        iss_require_cap(function_exists('iss_core_capability') ? iss_core_capability('sync') : 'manage_options');
    }

    ?>
    <div class="wrap">
        <h1>SuperSaaS API</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields(ISS_SUPERSAAS_OPTION_GROUP);
            do_settings_sections(ISS_SUPERSAAS_OPTION_GROUP);
            submit_button();
            ?>
        </form>
        <p><strong>Frontend:</strong> Tour calendars are rendered by the <code>iss/tour-calendar</code> and <code>iss/tour-dates</code> blocks in <code>iss-frontend</code>.</p>
    </div>
    <?php
}

function iss_supersaas_build_slot_response($slot, $title, $start) {
    $available = null;
    if (isset($slot['available'])) {
        $available = (int) $slot['available'];
    } elseif (isset($slot['remaining'])) {
        $available = (int) $slot['remaining'];
    } elseif (isset($slot['count'])) {
        $available = (int) $slot['count'];
    }

    return [
        'id'        => isset($slot['id']) ? (string) $slot['id'] : '',
        'title'     => $title,
        'start'     => $start,
        'end'       => $slot['end'] ?? ($slot['finish'] ?? null),
        'capacity'  => isset($slot['capacity']) ? (int) $slot['capacity'] : null,
        'available' => $available,
        'booking_url' => null,
    ];
}

function iss_supersaas_field_schedule_id() {
    $settings = iss_supersaas_get_settings();
    printf(
        '<input type="text" name="%s[schedule_id]" value="%s" class="regular-text" />',
        esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
        esc_attr($settings['schedule_id'])
    );
}

function iss_supersaas_field_api_key() {
    $settings = iss_supersaas_get_settings();
    printf(
        '<input type="password" name="%s[api_key]" value="%s" class="regular-text" autocomplete="new-password" />',
        esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
        esc_attr($settings['api_key'])
    );
}

function iss_supersaas_field_base_url() {
    $settings = iss_supersaas_get_settings();
    printf(
        '<input type="text" name="%s[base_url]" value="%s" class="regular-text" />',
        esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
        esc_attr($settings['base_url'])
    );
    echo '<p class="description">Example: https://www.supersaas.de</p>';
}

function iss_supersaas_field_account_name() {
    $settings = iss_supersaas_get_settings();
    printf(
        '<input type="text" name="%s[account_name]" value="%s" class="regular-text" />',
        esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
        esc_attr($settings['account_name'])
    );
    echo '<p class="description">Used for booking links.</p>';
}

function iss_supersaas_field_schedule_path() {
    $settings = iss_supersaas_get_settings();
    printf(
        '<input type="text" name="%s[schedule_path]" value="%s" class="regular-text" />',
        esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
        esc_attr($settings['schedule_path'])
    );
    echo '<p class="description">Required for booking links. Example: Fuehrungen_%28oeffentlich%29</p>';
}

function is_tours_register_public_slot_routes() {
    // Public intentionally: frontend tour calendar reads slot availability without authentication.
    $route_args = [
        'methods'  => 'GET',
        'callback' => 'is_tours_get_slots',
        'permission_callback' => '__return_true',
    ];

    register_rest_route('iss/v1', '/tour-slots', $route_args);
}
add_action('rest_api_init', 'is_tours_register_public_slot_routes');

function is_tours_public_rate_limit_response(string $scope, int $limit = 60, int $window = 10 * MINUTE_IN_SECONDS): ?WP_REST_Response {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '';
    $key = 'is_tours_rate_' . md5($scope . '|' . $ip);
    $count = (int) get_transient($key);
    if ($count >= $limit) {
        $res = new WP_REST_Response(['source' => 'rate_limited', 'slots' => []], 429);
        $res->header('Cache-Control', 'no-store');
        return $res;
    }

    set_transient($key, $count + 1, $window);
    return null;
}

function is_tours_get_slots(WP_REST_Request $request) {
    $rate_limited = is_tours_public_rate_limit_response('tour-slots');
    if ($rate_limited instanceof WP_REST_Response) {
        return $rate_limited;
    }

    $tag = strtoupper(sanitize_text_field($request->get_param('tag')));
    $source_post_id = (int) $request->get_param('post_id');

    if (!$tag && $source_post_id > 0) {
        if (function_exists('iss_occurrences_resolve_tag_for_source_post_id')) {
            $tag = iss_occurrences_resolve_tag_for_source_post_id($source_post_id);
        }
    }

    if (!$tag && $source_post_id <= 0) {
        // Return an explicit no-mapping response; public slots require a tag or source post.
        $res = new WP_REST_Response(['source' => 'nomap', 'slots' => []], 200);
        $res->header('X-IS-Tours-Source', 'nomap');
        $res->header('X-IS-Tours-Error', 'missing-tag');
        $res->header('Cache-Control', 'no-store');
        return $res;
    }

    if ($tag !== '' && $source_post_id <= 0) {
        $cache_key = 'is_tours_slots_' . md5($tag);
        $cached = get_transient($cache_key);
    } else {
        $cached = false;
    }

    if ($cached !== false) {
        $cached_at = is_tours_get_cached_at_by_tag($tag);
        $source = is_tours_get_cached_source_by_tag($tag);
        if ($source === '' || $source === 'cache') {
            $source = 'occurrences';
            is_tours_set_cached_source_by_tag($tag, $source, 60 * 10);
        }
        $payload = ['source' => $source, 'slots' => is_array($cached) ? $cached : []];
        $etag = is_tours_build_etag($payload);
        $maybe = is_tours_maybe_304($request, $etag, $cached_at, 60);
        if ($maybe) {
            $maybe->header('X-IS-Tours-Source', 'cache');
            return $maybe;
        }

        $res = new WP_REST_Response($payload, 200);
        $res->header('X-IS-Tours-Source', 'cache');
        if ($etag !== '') $res->header('ETag', $etag);
        $res->header('Cache-Control', 'public, max-age=60');
        if ($cached_at > 0) {
            $res->header('Last-Modified', gmdate('D, d M Y H:i:s', $cached_at) . ' GMT');
        }
        return $res;
	    }

    if (!function_exists('iss_occurrences_query')) {
        return new WP_REST_Response([
            'error' => 'Occurrence module missing',
        ], 500);
    }

    $slots = is_tours_get_occurrence_slots($tag, $source_post_id);
    $source = 'occurrences';

    if (!empty($slots)) {
        if ($tag !== '') {
            $ttl = 60 * 10;
            is_tours_set_cached_slots_by_tag($tag, $slots, $ttl);
            is_tours_set_cached_source_by_tag($tag, $source, $ttl);
        }

        $payload = ['source' => $source, 'slots' => $slots];
        $etag = is_tours_build_etag($payload);
        $cached_at = $tag !== '' ? is_tours_get_cached_at_by_tag($tag) : 0;
        $max_age = 60;
        $maybe = is_tours_maybe_304($request, $etag, $cached_at, $max_age);
        if ($maybe) {
            $maybe->header('X-IS-Tours-Source', $source);
            return $maybe;
        }

        $res = new WP_REST_Response($payload, 200);
        $res->header('X-IS-Tours-Source', $source);
        if ($etag !== '') $res->header('ETag', $etag);
        $res->header('Cache-Control', 'public, max-age=' . $max_age);
        if ($cached_at > 0) {
            $res->header('Last-Modified', gmdate('D, d M Y H:i:s', $cached_at) . ' GMT');
        }
        return $res;
    }

    $res = new WP_REST_Response(['source' => $source, 'slots' => []], 200);
    $res->header('X-IS-Tours-Source', $source);
    $res->header('Cache-Control', 'no-store');
    return $res;
}

/**
 * Return cached slots for a given tag, or an empty array when not cached.
 */
function is_tours_get_cached_slots_by_tag($tag) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') {
        return [];
    }

    $cache_key = 'is_tours_slots_' . md5($tag);
    $cached = get_transient($cache_key);
    if ($cached === false || !is_array($cached)) {
        return [];
    }

    return $cached;
}

function is_tours_get_cached_source_by_tag($tag) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') {
        return '';
    }

    $cache_key = 'is_tours_slots_src_' . md5($tag);
    $src = get_transient($cache_key);
    return $src ? (string) $src : '';
}

/**
 * Return when the tag cache was last written (unix timestamp), or 0.
 */
function is_tours_get_cached_at_by_tag($tag) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') {
        return 0;
    }

    $cache_key = 'is_tours_slots_ts_' . md5($tag);
    $ts = get_transient($cache_key);
    return $ts ? (int) $ts : 0;
}

/**
 * Store normalized slots into the shared tag cache.
 *
 * @param string $tag
 * @param array $slots
 * @param int $ttl_seconds
 * @return void
 */
function is_tours_set_cached_slots_by_tag($tag, $slots, $ttl_seconds) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') return;

    if (!is_array($slots)) {
        $slots = [];
    }

    $ttl_seconds = (int) $ttl_seconds;
    if ($ttl_seconds <= 0) {
        $ttl_seconds = 60 * 10;
    }

    $cache_key = 'is_tours_slots_' . md5($tag);
    set_transient($cache_key, $slots, $ttl_seconds);

    $ts_key = 'is_tours_slots_ts_' . md5($tag);
    set_transient($ts_key, (int) current_time('timestamp'), $ttl_seconds);
}

function is_tours_set_cached_source_by_tag($tag, $source, $ttl_seconds) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag === '') return;

    $source = sanitize_key((string) $source);
    if ($source === '') return;

    $ttl_seconds = (int) $ttl_seconds;
    if ($ttl_seconds <= 0) {
        $ttl_seconds = 60 * 10;
    }

    $cache_key = 'is_tours_slots_src_' . md5($tag);
    set_transient($cache_key, $source, $ttl_seconds);
}

function is_tours_build_etag($data) {
    try {
        $json = wp_json_encode($data);
        if (!is_string($json)) return '';
        return '"' . md5($json) . '"';
    } catch (Throwable $e) {
        return '';
    }
}

/**
 * Apply HTTP cache headers + conditional GET handling.
 *
 * @return WP_REST_Response|null Return a 304 response to short-circuit, or null to continue.
 */
function is_tours_maybe_304(WP_REST_Request $request, $etag, $last_modified_ts, $max_age) {
    $etag = (string) $etag;
    $last_modified_ts = (int) $last_modified_ts;
    $max_age = (int) $max_age;
    if ($max_age <= 0) {
        $max_age = 60;
    }

    if ($etag !== '') {
        $if_none_match = (string) $request->get_header('if-none-match');
        if ($if_none_match !== '' && trim($if_none_match) === $etag) {
            $res = new WP_REST_Response(null, 304);
            $res->header('ETag', $etag);
            $res->header('Cache-Control', 'public, max-age=' . $max_age);
            if ($last_modified_ts > 0) {
                $res->header('Last-Modified', gmdate('D, d M Y H:i:s', $last_modified_ts) . ' GMT');
            }
            return $res;
        }
    }

    if ($last_modified_ts > 0) {
        $if_modified_since = (string) $request->get_header('if-modified-since');
        if ($if_modified_since !== '') {
            $since = strtotime($if_modified_since);
            if ($since && $since >= $last_modified_ts) {
                $res = new WP_REST_Response(null, 304);
                if ($etag !== '') {
                    $res->header('ETag', $etag);
                }
                $res->header('Cache-Control', 'public, max-age=' . $max_age);
                $res->header('Last-Modified', gmdate('D, d M Y H:i:s', $last_modified_ts) . ' GMT');
                return $res;
            }
        }
    }

    return null;
}

/**
 * Return the next available slot from cache for a tag (or null).
 */
function is_tours_get_next_slot($tag) {
    $slots = is_tours_get_cached_slots_by_tag($tag);

    foreach ($slots as $slot) {
        if (!is_array($slot)) {
            continue;
        }

        if (!array_key_exists('available', $slot) || $slot['available'] === null || (int) $slot['available'] > 0) {
            return $slot;
        }
    }

    return null;
}

function is_tours_get_occurrence_slots($tag, $source_post_id = 0) {
    $tag = strtoupper(sanitize_text_field((string) $tag));
    $source_post_id = (int) $source_post_id;

    if (!function_exists('iss_occurrences_query')) {
        return [];
    }

    $query = [
        'limit' => 250,
        'order' => 'ASC',
        'time_mode' => 'upcoming',
        'item_type' => 'tour',
        'origin' => 'supersaas',
    ];
    if ($source_post_id > 0) {
        $query['source_post_ids'] = [$source_post_id];
    } elseif ($tag !== '') {
        $query['tag'] = $tag;
    } else {
        return [];
    }

    $items = iss_occurrences_query($query);
    $slots = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $external_id = trim((string) ($item['slot_id'] ?? ''));
        $start = trim((string) ($item['slot_start'] ?? $item['start_raw'] ?? ''));
        if ($external_id === '' || $start === '') {
            continue;
        }

        $booking_url = trim((string) ($item['booking_url'] ?? ''));
        $slots[] = [
            'id' => $external_id,
            'title' => trim((string) ($item['title'] ?? '')),
            'start' => $start,
            'end' => !empty($item['end_raw']) ? (string) $item['end_raw'] : null,
            'capacity' => array_key_exists('capacity', $item) ? $item['capacity'] : null,
            'available' => array_key_exists('available', $item) ? $item['available'] : null,
            'booking_url' => $booking_url !== '' ? $booking_url : null,
            'content_url' => !empty($item['content_url']) ? (string) $item['content_url'] : null,
        ];
    }

    return $slots;
}
