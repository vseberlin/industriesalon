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
        'schedules'     => [],
    ];

    $settings = get_option(ISS_SUPERSAAS_SETTINGS_OPTION, []);
    if (!is_array($settings)) {
        $settings = [];
    }

    return array_merge($defaults, $settings);
}

function iss_supersaas_normalize_schedule_key($value): string {
    $value = sanitize_key((string) $value);
    $value = preg_replace('/[^a-z0-9_-]+/', '', $value);
    return trim((string) $value, '_-');
}

function iss_supersaas_get_schedule_configs($settings = null): array {
    if ($settings === null) {
        $settings = iss_supersaas_get_settings();
    }
    if (!is_array($settings)) {
        return [];
    }

    $configs = [];
    $configured = isset($settings['schedules']) && is_array($settings['schedules']) ? $settings['schedules'] : [];
    foreach ($configured as $index => $schedule) {
        if (!is_array($schedule)) {
            continue;
        }

        $schedule_id = isset($schedule['schedule_id']) ? preg_replace('/[^0-9]/', '', (string) $schedule['schedule_id']) : '';
        if ($schedule_id === '') {
            continue;
        }

        $key = isset($schedule['key']) ? iss_supersaas_normalize_schedule_key((string) $schedule['key']) : '';
        if ($key === '') {
            $key = $index === 0 ? 'public' : 'schedule-' . ($index + 1);
        }

        $enabled = array_key_exists('enabled', $schedule) ? (bool) $schedule['enabled'] : true;
        $label = isset($schedule['label']) ? sanitize_text_field((string) $schedule['label']) : '';
        if ($label === '') {
            $label = $key;
        }
        $source = isset($schedule['source']) ? sanitize_key((string) $schedule['source']) : 'free';
        if (!in_array($source, ['free', 'range'], true)) {
            $source = 'free';
        }

        $configs[$key] = [
            'key' => $key,
            'label' => $label,
            'schedule_id' => $schedule_id,
            'schedule_path' => isset($schedule['schedule_path']) ? trim((string) $schedule['schedule_path']) : '',
            'source' => $source,
            'enabled' => $enabled,
        ];
    }

    if (empty($configs) && !empty($settings['schedule_id'])) {
        $schedule_id = preg_replace('/[^0-9]/', '', (string) $settings['schedule_id']);
        if ($schedule_id !== '') {
            $configs['public'] = [
                'key' => 'public',
                'label' => !empty($settings['schedule_path']) ? sanitize_text_field((string) $settings['schedule_path']) : 'public',
                'schedule_id' => $schedule_id,
                'schedule_path' => isset($settings['schedule_path']) ? trim((string) $settings['schedule_path']) : '',
                'source' => 'free',
                'enabled' => true,
            ];
        }
    }

    return array_values(array_filter($configs, static function ($schedule) {
        return is_array($schedule) && !empty($schedule['enabled']) && !empty($schedule['schedule_id']);
    }));
}

function iss_supersaas_get_schedule_path($settings = null) {
    if ($settings === null) {
        $settings = iss_supersaas_get_settings();
    }

    $schedules = iss_supersaas_get_schedule_configs($settings);
    if (!empty($schedules[0]['schedule_path'])) {
        return (string) $schedules[0]['schedule_path'];
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
    if (empty($settings['api_key']) || empty(iss_supersaas_get_schedule_configs($settings))) {
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

    add_settings_field('api_key', 'API Key', 'iss_supersaas_field_api_key', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
    add_settings_field('base_url', 'API Base URL', 'iss_supersaas_field_base_url', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
    add_settings_field('account_name', 'Account Name', 'iss_supersaas_field_account_name', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
    add_settings_field('schedules', 'Schedules', 'iss_supersaas_field_schedules', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
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
    $out['schedules'] = [];
    $schedules = isset($input['schedules']) && is_array($input['schedules']) ? $input['schedules'] : [];
    foreach ($schedules as $index => $schedule) {
        if (!is_array($schedule)) {
            continue;
        }
        $schedule_id = isset($schedule['schedule_id']) ? preg_replace('/[^0-9]/', '', (string) $schedule['schedule_id']) : '';
        if ($schedule_id === '') {
            continue;
        }
        $key = isset($schedule['key']) ? iss_supersaas_normalize_schedule_key((string) $schedule['key']) : '';
        if ($key === '') {
            $key = $index === 0 ? 'public' : 'schedule-' . ($index + 1);
        }
        $out['schedules'][] = [
            'enabled' => !empty($schedule['enabled']) ? 1 : 0,
            'key' => $key,
            'label' => isset($schedule['label']) ? sanitize_text_field((string) $schedule['label']) : $key,
            'schedule_id' => $schedule_id,
            'schedule_path' => isset($schedule['schedule_path']) ? trim((string) $schedule['schedule_path']) : '',
            'source' => isset($schedule['source']) && in_array(sanitize_key((string) $schedule['source']), ['free', 'range'], true)
                ? sanitize_key((string) $schedule['source'])
                : 'free',
        ];
    }
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

function iss_supersaas_field_schedules() {
    $settings = iss_supersaas_get_settings();
    $schedules = isset($settings['schedules']) && is_array($settings['schedules']) && !empty($settings['schedules'])
        ? $settings['schedules']
        : iss_supersaas_get_schedule_configs($settings);

    if (empty($schedules)) {
        $schedules = [[
            'enabled' => 1,
            'key' => 'public',
            'label' => 'public',
            'schedule_id' => isset($settings['schedule_id']) ? (string) $settings['schedule_id'] : '',
            'schedule_path' => isset($settings['schedule_path']) ? (string) $settings['schedule_path'] : '',
            'source' => 'free',
        ]];
    }
    while (count($schedules) < 2) {
        $schedules[] = [
            'enabled' => 0,
            'key' => '',
            'label' => '',
            'schedule_id' => '',
            'schedule_path' => '',
            'source' => 'free',
        ];
    }

    echo '<table class="widefat striped" style="max-width:1080px"><thead><tr><th>Aktiv</th><th>Key</th><th>Label</th><th>Schedule ID</th><th>Schedule Path</th><th>Quelle</th></tr></thead><tbody>';
    foreach ($schedules as $index => $schedule) {
        $schedule = is_array($schedule) ? $schedule : [];
        echo '<tr>';
        echo '<td><input type="hidden" name="' . esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION) . '[schedules][' . (int) $index . '][enabled]" value="0" />';
        echo '<input type="checkbox" name="' . esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION) . '[schedules][' . (int) $index . '][enabled]" value="1" ' . checked(!empty($schedule['enabled']), true, false) . ' /></td>';
        printf(
            '<td><input type="text" name="%1$s[schedules][%2$d][key]" value="%3$s" class="regular-text" placeholder="public" /></td>',
            esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
            (int) $index,
            esc_attr((string) ($schedule['key'] ?? ''))
        );
        printf(
            '<td><input type="text" name="%1$s[schedules][%2$d][label]" value="%3$s" class="regular-text" /></td>',
            esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
            (int) $index,
            esc_attr((string) ($schedule['label'] ?? ''))
        );
        printf(
            '<td><input type="text" name="%1$s[schedules][%2$d][schedule_id]" value="%3$s" class="regular-text" /></td>',
            esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
            (int) $index,
            esc_attr((string) ($schedule['schedule_id'] ?? ''))
        );
        printf(
            '<td><input type="text" name="%1$s[schedules][%2$d][schedule_path]" value="%3$s" class="regular-text" placeholder="public" /></td>',
            esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION),
            (int) $index,
            esc_attr((string) ($schedule['schedule_path'] ?? ''))
        );
        $source = isset($schedule['source']) ? sanitize_key((string) $schedule['source']) : 'free';
        echo '<td><select name="' . esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION) . '[schedules][' . (int) $index . '][source]">';
        foreach (['free' => 'Freie Slots', 'range' => 'Termine'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($source, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '<p class="description">Jeder aktive Schedule wird in die SuperSaaS-Staging-Tabelle gezogen. Der Key wird für eindeutige Slot-IDs verwendet.</p>';
}

function iss_occurrences_register_public_booking_slot_routes() {
    // Public intentionally: frontend calendars read slot availability without authentication.
    $route_args = [
        'methods'  => 'GET',
        'callback' => 'iss_occurrences_get_booking_slots_rest',
        'permission_callback' => '__return_true',
    ];

    register_rest_route('iss/v1', '/booking-slots', $route_args);
}
add_action('rest_api_init', 'iss_occurrences_register_public_booking_slot_routes');

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

function iss_occurrences_get_booking_slots_rest(WP_REST_Request $request) {
    $rate_limited = is_tours_public_rate_limit_response('booking-slots');
    if ($rate_limited instanceof WP_REST_Response) { return $rate_limited; }

    $tag = strtoupper(sanitize_text_field((string) $request->get_param('tag')));
    $source_post_id = (int) ($request->get_param('source_post_id') ?: $request->get_param('post_id'));
    $source_post_type = sanitize_key((string) $request->get_param('source_post_type'));
    $item_type = sanitize_key((string) $request->get_param('item_type'));
    if ($source_post_id <= 0 && $tag !== '') {
        $mapping = iss_occurrences_get_tag_source($tag);
        $source_post_id = (int) ($mapping['source_post_id'] ?? 0);
        $source_post_type = (string) ($mapping['source_post_type'] ?? '');
    }
    $slots = iss_occurrences_get_booking_slots([
        'tag' => $tag, 'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type, 'item_type' => $item_type,
    ]);
    $payload = iss_occurrences_build_booking_slots_payload('occurrences', $slots, $source_post_id, $source_post_type);
    // Read the projection on every request: tag-only caches could outlive source
    // visibility changes or hold a different source's partial availability.
    $response = new WP_REST_Response($payload, 200);
    $response->header('X-IS-Tours-Source', 'occurrences');
    $response->header('Cache-Control', 'no-store');
    return $response;
}

function iss_occurrences_build_booking_slots_payload(string $source, array $slots, int $source_post_id = 0, string $source_post_type = ''): array {
    $source_post_type = sanitize_key($source_post_type);
    if ($source_post_type === '' && $source_post_id > 0) {
        $source_post_type = sanitize_key((string) get_post_type($source_post_id));
    }

    $inquiry_allowed = iss_occurrences_booking_inquiry_allowed($source_post_id, $source_post_type);

    return [
        'source' => sanitize_key($source),
        'mode' => !empty($slots) ? 'slots' : ($inquiry_allowed ? 'inquiry' : 'unavailable'),
        'slots' => array_values($slots),
        'inquiry' => [
            'allowed' => $inquiry_allowed,
            'mode' => $inquiry_allowed ? 'preferred_date' : '',
        ],
    ];
}

function iss_occurrences_booking_inquiry_allowed(int $source_post_id, string $source_post_type): bool {
    $source_post_type = sanitize_key($source_post_type);
    if ($source_post_id <= 0 || $source_post_type === '') {
        return false;
    }

    if (get_post_type($source_post_id) !== $source_post_type || !is_post_publicly_viewable($source_post_id) || get_post_field('post_password', $source_post_id) !== '') {
        return false;
    }

    if ($source_post_type === 'veranstaltung') {
        return !empty(get_post_meta($source_post_id, 'iss_booking_enabled', true));
    }

    if ($source_post_type === 'fuehrung') {
        return function_exists('iss_fuehrung_get_effective_booking_mode')
            && in_array(iss_fuehrung_get_effective_booking_mode($source_post_id), ['on_demand', 'hybrid'], true);
    }

    return $source_post_type === 'ausstellung';
}

/**
 * Return normalized bookable occurrence rows for booking UI validation.
 *
 * @param array<string,mixed> $args
 * @return array<int,array<string,mixed>>
 */
function iss_occurrences_get_booking_slots(array $args = []): array {
    if (!function_exists('iss_occurrences_query')) {
        return [];
    }

    $tag = isset($args['tag']) ? strtoupper(sanitize_text_field((string) $args['tag'])) : '';
    $source_post_id = isset($args['source_post_id']) ? (int) $args['source_post_id'] : 0;
    $source_post_type = isset($args['source_post_type']) ? sanitize_key((string) $args['source_post_type']) : '';
    $item_type = isset($args['item_type']) ? sanitize_key((string) $args['item_type']) : '';
    $horizon_months = isset($args['horizon_months']) ? (int) $args['horizon_months'] : 12;
    $horizon_months = min(24, max(1, $horizon_months));

    if ($source_post_type !== '' && function_exists('iss_occurrences_kind_for_source_post_type')) {
        $item_type = iss_occurrences_kind_for_source_post_type($source_post_type);
    }
    if ($item_type === '' && $tag !== '') {
        $item_type = 'tour';
    }
    if ($source_post_type === '' && $item_type === 'tour') {
        $source_post_type = 'fuehrung';
    }

    $query = [
        // Complete availability inside the explicit source/date horizon; no silent row cap.
        'limit' => -1,
        'order' => 'ASC',
        'time_mode' => 'upcoming',
    ];
    if ($item_type !== '') {
        $query['item_type'] = $item_type;
    }
    if ($source_post_type !== '') {
        $query['post_types'] = [$source_post_type];
    }
    if (!empty($args['origin'])) {
        $query['origin'] = sanitize_key((string) $args['origin']);
    }
    if ($source_post_id > 0) {
        $query['source_post_ids'] = [$source_post_id];
    } elseif ($tag !== '') {
        $query['tag'] = $tag;
        if (empty($query['origin'])) {
            $query['origin'] = 'supersaas';
        }
    } else {
        return [];
    }

    $horizon_filter = static function () use ($horizon_months) {
        return $horizon_months;
    };
    add_filter('iss_occurrences_future_horizon_months', $horizon_filter);
    try {
        $items = iss_occurrences_query($query);
    } finally {
        remove_filter('iss_occurrences_future_horizon_months', $horizon_filter);
    }
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
        $slot_source_post_id = isset($item['source_post_id']) ? (int) $item['source_post_id'] : 0;
        $slot_source_post_type = isset($item['source_post_type']) ? sanitize_key((string) $item['source_post_type']) : '';
        if ($slot_source_post_id <= 0 || get_post_type($slot_source_post_id) !== $slot_source_post_type || !is_post_publicly_viewable($slot_source_post_id) || get_post_field('post_password', $slot_source_post_id) !== '') {
            continue;
        }
        $slot = [
            'id' => $external_id,
            'title' => trim((string) ($item['title'] ?? '')),
            'start' => $start,
            'end' => !empty($item['end_raw']) ? (string) $item['end_raw'] : null,
            'availability_state' => (string) ($item['availability_state'] ?? ''),
            'capacity' => array_key_exists('capacity', $item) ? $item['capacity'] : null,
            'available' => array_key_exists('available', $item) ? $item['available'] : null,
            'booking_url' => $booking_url !== '' ? $booking_url : null,
            'content_url' => !empty($item['content_url']) ? (string) $item['content_url'] : null,
            'source_post_id' => $slot_source_post_id,
            'source_post_type' => $slot_source_post_type,
        ];

        if ($slot_source_post_id > 0 && in_array($slot_source_post_type, ['fuehrung', 'veranstaltung'], true)) {
            $price_meta_key = $slot_source_post_type === 'fuehrung' ? 'booking_price_cents' : 'iss_booking_price_cents';
            $description_meta_key = $slot_source_post_type === 'fuehrung' ? 'price_note' : 'iss_booking_gateway_description';
            $price_cents = max(0, (int) get_post_meta($slot_source_post_id, $price_meta_key, true));
            $cta_label = $slot_source_post_type === 'veranstaltung'
                ? trim((string) get_post_meta($slot_source_post_id, 'iss_booking_cta_label', true))
                : '';
            $description = trim((string) get_post_meta($slot_source_post_id, $description_meta_key, true));

            if ($price_cents > 0) {
                $slot['amount'] = $price_cents;
            }
            $reduced_price_cents = $slot_source_post_type === 'fuehrung'
                ? max(0, (int) get_post_meta($slot_source_post_id, 'booking_reduced_price_cents', true)) : 0;
            if ($reduced_price_cents > 0 && $reduced_price_cents < $price_cents) {
                $slot['reduced_amount'] = $reduced_price_cents;
            }
            if ($cta_label !== '') {
                $slot['label'] = $cta_label;
            }
            if ($description !== '') {
                $slot['description'] = $description;
            }
        }

        $slots[] = $slot;
    }

    return $slots;
}

function is_tours_get_occurrence_slots($tag, $source_post_id = 0) {
    return iss_occurrences_get_booking_slots([
        'tag' => $tag,
        'source_post_id' => (int) $source_post_id,
        'source_post_type' => 'fuehrung',
        'item_type' => 'tour',
        'origin' => 'supersaas',
    ]);
}
