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

add_action('admin_notices', function () {
    if (!current_user_can(function_exists('iss_core_capability') ? iss_core_capability('sync') : 'manage_options')) { return; }
    $settings = iss_supersaas_get_settings();
    if (empty($settings['api_key']) || empty(iss_supersaas_get_schedule_configs($settings))) {
        $settings_url = add_query_arg('view', 'connection', iss_occurrences_sync_admin_url());
        echo '<div class="notice notice-warning is-dismissible"><p>'
            . '<strong>SuperSaaS API:</strong> '
            . 'Terminabgleich nicht eingerichtet. Bitte Zugangsdaten und aktive Kalender prüfen. '
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
        'SuperSaaS-Verbindung',
        '__return_false',
        ISS_SUPERSAAS_OPTION_GROUP
    );

    add_settings_field('api_key', 'API-Schlüssel', 'iss_supersaas_field_api_key', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main', ['label_for' => 'iss-supersaas-api_key']);
    add_settings_field('base_url', 'Server-Adresse', 'iss_supersaas_field_base_url', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main', ['label_for' => 'iss-supersaas-base_url']);
    add_settings_field('account_name', 'Kontoname', 'iss_supersaas_field_account_name', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main', ['label_for' => 'iss-supersaas-account_name']);
    add_settings_field('schedules', 'Kalender', 'iss_supersaas_field_schedules', ISS_SUPERSAAS_OPTION_GROUP, 'iss_supersaas_main');
}
add_action('admin_init', 'iss_supersaas_register_settings');
add_filter('option_page_capability_' . ISS_SUPERSAAS_OPTION_GROUP, function () {
    return function_exists('iss_core_capability') ? iss_core_capability('sync') : 'manage_options';
});

function iss_supersaas_sanitize_settings($input) {
    $old = iss_supersaas_get_settings();
    $input = is_array($input) ? $input : [];
    $out = $old;
    $errors = [];
    $out['api_key'] = trim((string) ($input['api_key'] ?? '')) !== '' ? sanitize_text_field($input['api_key']) : $old['api_key'];
    $out['base_url'] = esc_url_raw(trim((string) ($input['base_url'] ?? '')));
    if (!in_array(wp_parse_url($out['base_url'], PHP_URL_SCHEME), ['https', 'http'], true) || !wp_parse_url($out['base_url'], PHP_URL_HOST)) {
        $errors[] = 'Server-Adresse: Bitte eine vollständige HTTP- oder HTTPS-Adresse angeben.';
    }
    $out['account_name'] = sanitize_text_field((string) ($input['account_name'] ?? ''));
    $out['schedules'] = [];
    $keys = [];
    foreach ((array) ($input['schedules'] ?? []) as $index => $schedule) {
        if (!is_array($schedule)) { continue; }
        $raw_key = trim((string) ($schedule['key'] ?? ''));
        $id = trim((string) ($schedule['schedule_id'] ?? ''));
        $label = sanitize_text_field((string) ($schedule['label'] ?? ''));
        $path = trim((string) ($schedule['schedule_path'] ?? ''));
        if ($raw_key === '' && $id === '' && $label === '' && $path === '' && empty($schedule['enabled'])) { continue; }
        $row = 'Kalender ' . ((int) $index + 1) . ': ';
        $key = iss_supersaas_normalize_schedule_key($raw_key);
        if ($key === '' || $key !== $raw_key || isset($keys[$key])) { $errors[] = $row . 'Der Schlüssel muss eindeutig sein und nur Kleinbuchstaben, Zahlen, Bindestriche oder Unterstriche enthalten.'; }
        $existing = $old['schedules'][$index] ?? null;
        if (is_array($existing) && !empty($existing['key']) && $key !== $existing['key']) { $errors[] = $row . 'Der Schlüssel eines bestehenden Kalenders darf nicht geändert werden.'; }
        if (!ctype_digit($id) || (int) $id <= 0) { $errors[] = $row . 'Kalender-ID muss eine positive ganze Zahl sein. Die bisherige Konfiguration bleibt erhalten.'; }
        $source = sanitize_key((string) ($schedule['source'] ?? ''));
        if (!in_array($source, ['free', 'range'], true)) { $errors[] = $row . 'Bitte eine gültige Abrufart wählen.'; }
        if (($key === 'salonbelegung') !== ($source === 'range')) { $errors[] = $row . 'Der Veranstaltungskalender benötigt den Schlüssel salonbelegung und die Abrufart Veranstaltungstermine.'; }
        $keys[$key] = true;
        $out['schedules'][] = ['enabled' => !empty($schedule['enabled']) ? 1 : 0, 'key' => $key, 'label' => $label ?: $key, 'schedule_id' => $id, 'schedule_path' => $path, 'source' => $source];
    }
    $active = array_filter($out['schedules'], static fn($schedule) => !empty($schedule['enabled']));
    if ($active && ($out['account_name'] === '' || $out['api_key'] === '')) { $errors[] = 'Aktive Kalender benötigen Kontoname und API-Schlüssel.'; }
    if ($errors) {
        foreach ($errors as $index => $message) { add_settings_error(ISS_SUPERSAAS_SETTINGS_OPTION, 'calendar-' . $index, $message); }
        return $old;
    }
    // Array configuration replaces the legacy fallback, including when all calendars are disabled.
    $out['schedule_id'] = '';
    $out['schedule_path'] = '';
    return $out;
}

/** Redirect legacy bookmarks before core checks the removed submenu's access. */
function iss_supersaas_redirect_legacy_settings_page(): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
    if (sanitize_key((string) ($_GET['page'] ?? '')) !== ISS_SUPERSAAS_SETTINGS_PAGE) { return; }
    if (!current_user_can(iss_occurrences_sync_capability())) { wp_die('Keine Berechtigung.', '', ['response' => 403]); }
    wp_safe_redirect(add_query_arg('view', 'connection', iss_occurrences_sync_admin_url()));
    exit;
}
add_action('admin_menu', 'iss_supersaas_redirect_legacy_settings_page', 9);

function iss_supersaas_render_connection_form(): void {
    iss_supersaas_load_admin_settings_api();
    settings_errors();
    echo '<h2>Verbindung und Kalender</h2><p>Diese Einstellungen bestimmen, aus welchen Kalendern Termine gelesen werden. Speichern startet keinen Import. Anschließend unter „Übersicht“ den Terminabgleich ausführen und das Ergebnis prüfen.</p>';
    echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
    settings_fields(ISS_SUPERSAAS_OPTION_GROUP);
    do_settings_sections(ISS_SUPERSAAS_OPTION_GROUP);
    submit_button('Verbindung speichern');
    echo '</form>';
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

function iss_supersaas_field_api_key() {
    $settings = iss_supersaas_get_settings();
    echo '<input id="iss-supersaas-api_key" type="password" name="' . esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION) . '[api_key]" value="" class="regular-text" autocomplete="new-password">';
    echo '<p class="description">' . esc_html($settings['api_key'] !== '' ? 'Schlüssel gespeichert. Leer lassen, um ihn beizubehalten; einen neuen Schlüssel nur zum Ersetzen eingeben.' : 'Noch kein Schlüssel gespeichert. API-Schlüssel aus dem SuperSaaS-Konto eingeben.') . '</p>';
}

function iss_supersaas_field_base_url() {
    $settings = iss_supersaas_get_settings();
    echo '<input id="iss-supersaas-base_url" type="url" name="' . esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION) . '[base_url]" value="' . esc_attr($settings['base_url']) . '" class="regular-text" required><p class="description">Zum Beispiel https://www.supersaas.de</p>';
}

function iss_supersaas_field_account_name() {
    $settings = iss_supersaas_get_settings();
    echo '<input id="iss-supersaas-account_name" type="text" name="' . esc_attr(ISS_SUPERSAAS_SETTINGS_OPTION) . '[account_name]" value="' . esc_attr($settings['account_name']) . '" class="regular-text"><p class="description">SuperSaaS-Kontoname für Verweise auf den Quellkalender. Buchungen werden in WordPress verwaltet.</p>';
}

function iss_supersaas_field_schedules() {
    $settings = iss_supersaas_get_settings();
    $schedules = !empty($settings['schedules']) ? $settings['schedules'] : iss_supersaas_get_schedule_configs($settings);
    $schedules[] = ['enabled' => 0, 'key' => '', 'label' => '', 'schedule_id' => '', 'schedule_path' => '', 'source' => 'free'];
    foreach ($schedules as $index => $schedule) {
        $name = ISS_SUPERSAAS_SETTINGS_OPTION . '[schedules][' . (int) $index . ']';
        $existing = !empty($schedule['key']);
        echo '<fieldset class="iss-sync-calendar"><legend><strong>' . esc_html($existing ? ($schedule['label'] ?: $schedule['key']) : 'Weiteren Kalender hinzufügen (optional)') . '</strong></legend>';
        echo '<label><input type="checkbox" name="' . esc_attr($name . '[enabled]') . '" value="1" ' . checked(!empty($schedule['enabled']), true, false) . '> Kalender abrufen</label>';
        foreach (['label' => 'Anzeigename', 'schedule_id' => 'Kalender-ID', 'key' => 'Stabiler Schlüssel', 'schedule_path' => 'Kalenderpfad bei SuperSaaS'] as $key => $label) {
            echo '<label>' . esc_html($label) . '<input type="text" class="regular-text" name="' . esc_attr($name . '[' . $key . ']') . '" value="' . esc_attr((string) ($schedule[$key] ?? '')) . '" ' . ($key === 'key' && $existing ? 'readonly' : '') . '></label>';
        }
        echo '<label>Abrufart<select name="' . esc_attr($name . '[source]') . '">';
        foreach (['free' => 'Führungstermine (Slots)', 'range' => 'Veranstaltungstermine (Kalenderbereich)'] as $key => $label) {
            echo '<option value="' . esc_attr($key) . '" ' . selected($schedule['source'] ?? 'free', $key, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label><p class="description">Die Kalender-ID ist die numerische ID bei SuperSaaS. Der Schlüssel hält importierte Termine und Zuordnungen stabil; bestehende Schlüssel bleiben unverändert. „salonbelegung“ ist der Veranstaltungskalender. Deaktivieren stoppt weitere Abrufe und entfernt keine bereits importierten Termine.</p></fieldset>';
    }
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
            'departure_id' => (int) ($item['departure_id'] ?? 0),
            'test_mode' => !empty($item['test_mode']),
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
