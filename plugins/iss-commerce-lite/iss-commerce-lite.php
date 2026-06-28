<?php
/**
 * Plugin Name: ISS Commerce Lite
 * Description: Lightweight booking request and publication order intake for Industriesalon.
 * Version: 0.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This plugin owns the lightweight booking/order request table.

define('ISS_PAYMENTS_LITE_SCHEMA_VERSION', '2026-06-13-requests-v2');
define('ISS_PAYMENTS_LITE_SCHEMA_OPTION', 'iss_payments_lite_schema_version');
define('ISS_PAYMENTS_LITE_SETTINGS_OPTION', 'iss_payments_lite_settings');
define('ISS_PAYMENTS_LITE_PATH', plugin_dir_path(__FILE__));
define('ISS_COMMERCE_LITE_VERSION', '0.3.0');
define('ISS_COMMERCE_LITE_PATH', ISS_PAYMENTS_LITE_PATH);

function iss_payments_lite_requests_table_name(): string {
    global $wpdb;

    return $wpdb->prefix . 'iss_payments_lite_requests';
}

function iss_payments_lite_table_exists(): bool {
    global $wpdb;

    $table = iss_payments_lite_requests_table_name();
    return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
}

function iss_payments_lite_default_settings(): array {
    return [
        'notifications_enabled' => false,
        'notification_email' => sanitize_email((string) get_option('admin_email')),
        'require_nonce' => true,
        'min_submit_seconds' => 3,
        'max_body_bytes' => 8192,
    ];
}

function iss_payments_lite_get_settings(): array {
    $settings = get_option(ISS_PAYMENTS_LITE_SETTINGS_OPTION, []);
    if (!is_array($settings)) {
        $settings = [];
    }

    $settings = array_merge(iss_payments_lite_default_settings(), $settings);
    $settings['notifications_enabled'] = !empty($settings['notifications_enabled']);
    $settings['notification_email'] = sanitize_email((string) $settings['notification_email']);
    $settings['require_nonce'] = !empty($settings['require_nonce']);
    $settings['min_submit_seconds'] = max(0, (int) $settings['min_submit_seconds']);
    $settings['max_body_bytes'] = max(1024, (int) $settings['max_body_bytes']);

    return $settings;
}

function iss_payments_lite_update_settings(array $settings): void {
    $defaults = iss_payments_lite_default_settings();
    $normalized = [
        'notifications_enabled' => !empty($settings['notifications_enabled']),
        'notification_email' => sanitize_email((string) ($settings['notification_email'] ?? $defaults['notification_email'])),
        'require_nonce' => !empty($settings['require_nonce']),
        'min_submit_seconds' => max(0, (int) ($settings['min_submit_seconds'] ?? $defaults['min_submit_seconds'])),
        'max_body_bytes' => max(1024, (int) ($settings['max_body_bytes'] ?? $defaults['max_body_bytes'])),
    ];

    update_option(ISS_PAYMENTS_LITE_SETTINGS_OPTION, $normalized, false);
}

function iss_payments_lite_maybe_install_schema(): void {
    if ((string) get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, '') === ISS_PAYMENTS_LITE_SCHEMA_VERSION) {
        return;
    }

    iss_payments_lite_install_schema();
}

function iss_payments_lite_install_schema(): void {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $table = iss_payments_lite_requests_table_name();
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE {$table} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        request_kind varchar(50) NOT NULL DEFAULT '',
        status varchar(50) NOT NULL DEFAULT 'new',
        source_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
        source_post_type varchar(100) NOT NULL DEFAULT '',
        customer_name varchar(191) NOT NULL DEFAULT '',
        customer_email varchar(191) NOT NULL DEFAULT '',
        item_label varchar(255) NOT NULL DEFAULT '',
        quantity int(11) NOT NULL DEFAULT 0,
        amount_cents int(11) NOT NULL DEFAULT 0,
        payment_method varchar(50) NOT NULL DEFAULT '',
        slot_id varchar(191) NOT NULL DEFAULT '',
        tag varchar(100) NOT NULL DEFAULT '',
        payload longtext NOT NULL,
        duplicate_hash varchar(64) NOT NULL DEFAULT '',
        ip_address varchar(100) NOT NULL DEFAULT '',
        user_agent varchar(255) NOT NULL DEFAULT '',
        notified_at datetime DEFAULT NULL,
        notification_error varchar(255) NOT NULL DEFAULT '',
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY kind_status_created (request_kind, status, created_at),
        KEY source_lookup (source_post_type, source_post_id),
        KEY email_lookup (customer_email),
        KEY duplicate_lookup (duplicate_hash)
    ) {$charset_collate};";

    dbDelta($sql);
    iss_payments_lite_migrate_legacy_option_requests();
    update_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, ISS_PAYMENTS_LITE_SCHEMA_VERSION, false);
}

function iss_payments_lite_migrate_legacy_option_requests(): void {
    $legacy_options = [
        'is_tours_booking_requests' => 'tour_booking',
        'iss_publication_order_requests' => 'publication_order',
    ];

    foreach ($legacy_options as $option_name => $request_kind) {
        $rows = get_option($option_name, []);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_array($row)) {
                    iss_payments_lite_insert_request($request_kind, $row);
                }
            }
        }
        delete_option($option_name);
    }
}

function iss_payments_lite_insert_request(string $request_kind, array $entry): int {
    global $wpdb;

    if (!iss_payments_lite_table_exists()) {
        return 0;
    }

    $request_kind = sanitize_key($request_kind);
    if ($request_kind === '') {
        return 0;
    }

    $source_post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : (int) ($entry['publication_id'] ?? 0);
    $source_post_type = sanitize_key((string) ($entry['source_post_type'] ?? ''));
    if ($source_post_type === '' && $request_kind === 'publication_order') {
        $source_post_type = 'publication';
    }

    $payload = wp_json_encode($entry);
    $now = current_time('mysql');
    $inserted = $wpdb->insert(
        iss_payments_lite_requests_table_name(),
        [
            'request_kind' => $request_kind,
            'status' => sanitize_key((string) ($entry['status'] ?? 'new')),
            'source_post_id' => max(0, $source_post_id),
            'source_post_type' => $source_post_type,
            'customer_name' => sanitize_text_field((string) ($entry['name'] ?? '')),
            'customer_email' => sanitize_email((string) ($entry['email'] ?? '')),
            'item_label' => sanitize_text_field((string) ($entry['title'] ?? '')),
            'quantity' => isset($entry['tickets']) ? (int) $entry['tickets'] : (int) ($entry['quantity'] ?? 0),
            'amount_cents' => (int) ($entry['amount_cents'] ?? 0),
            'payment_method' => sanitize_key((string) ($entry['payment'] ?? '')),
            'slot_id' => sanitize_text_field((string) ($entry['slot_id'] ?? '')),
            'tag' => strtoupper(sanitize_text_field((string) ($entry['tag'] ?? ''))),
            'payload' => is_string($payload) ? $payload : '{}',
            'duplicate_hash' => sanitize_text_field((string) ($entry['duplicate_hash'] ?? '')),
            'ip_address' => sanitize_text_field((string) ($entry['ip'] ?? '')),
            'user_agent' => substr(sanitize_text_field((string) ($entry['ua'] ?? '')), 0, 255),
            'created_at' => sanitize_text_field((string) ($entry['time'] ?? $now)),
            'updated_at' => $now,
        ],
        [
            '%s',
            '%s',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%d',
            '%d',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
            '%s',
        ]
    );

    return $inserted ? (int) $wpdb->insert_id : 0;
}

add_action('init', 'iss_payments_lite_maybe_install_schema', 4);

add_action('wp_enqueue_scripts', function () {
    if (!is_singular('publication')) {
        return;
    }

    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return;
    }

    $sale_enabled = !empty(get_post_meta($post_id, '_iss_publication_sale_enabled', true));
    $price_cents = (int) get_post_meta($post_id, '_iss_publication_price_cents', true);
    if (!$sale_enabled || $price_cents <= 0) {
        return;
    }

    wp_enqueue_script(
        'iss-payments-lite-publications',
        plugin_dir_url(__FILE__) . 'assets/publication-order.js',
        [],
        filemtime(__DIR__ . '/assets/publication-order.js'),
        true
    );

    wp_localize_script('iss-payments-lite-publications', 'ISS_PUBLICATION_ORDER', [
        'orderUrl' => esc_url_raw(rest_url('iss-payments/v1/request')),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
});

add_action('wp_footer', function () {
    if (!wp_script_is('iss-payments-lite-publications', 'enqueued')) {
        return;
    }

    static $rendered = false;
    if ($rendered) {
        return;
    }

    $rendered = true;

    echo '<div class="iss-publication-order-modal" data-shared-publication-order-modal="1" hidden>';
    echo '<div class="iss-publication-order-modal__overlay" data-close="1" tabindex="-1"></div>';
    echo '<div class="iss-publication-order-modal__panel" role="dialog" aria-modal="true" aria-label="' . esc_attr__('Publikation bestellen', 'iss-payments-lite') . '">';
    echo '<button type="button" class="iss-publication-order-modal__close" data-close="1" aria-label="' . esc_attr__('Schließen', 'iss-payments-lite') . '">×</button>';
    echo '<div class="iss-publication-order-modal__content"></div>';
    echo '</div>';
    echo '</div>';
}, 20);

add_action('rest_api_init', function () {
    // Public intentionally: visitors submit tour booking requests without a WordPress account.
    register_rest_route('is-tours/v1', '/book', [
        'methods'  => 'POST',
        'callback' => 'iss_payments_lite_create_tour_booking',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('iss-payments/v1', '/publication-order', [
        'methods'  => 'POST',
        'callback' => 'iss_payments_lite_create_publication_order',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('iss-payments/v1', '/request', [
        'methods'  => 'POST',
        'callback' => 'iss_payments_lite_create_request',
        'permission_callback' => '__return_true',
    ]);
});

function iss_payments_lite_request_ip(): string {
    return isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '';
}

function iss_payments_lite_rate_limit_key(string $scope): string {
    return 'iss_payments_lite_rate_' . md5($scope . '|' . iss_payments_lite_request_ip());
}

function iss_payments_lite_check_rate_limit(string $scope, int $limit = 5, int $window = 10 * MINUTE_IN_SECONDS): ?WP_REST_Response {
    $key = iss_payments_lite_rate_limit_key($scope);
    $count = (int) get_transient($key);
    if ($count >= $limit) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Zu viele Anfragen. Bitte später erneut versuchen.'], 429);
    }

    set_transient($key, $count + 1, $window);
    return null;
}

function iss_payments_lite_public_request_guard(WP_REST_Request $request, string $scope): ?WP_REST_Response {
    $settings = iss_payments_lite_get_settings();
    $body = (string) $request->get_body();
    if (strlen($body) > (int) $settings['max_body_bytes']) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Anfrage ist zu groß.'], 413);
    }

    if (!empty($settings['require_nonce']) && !iss_payments_lite_request_has_valid_nonce($request)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrage.'], 403);
    }

    return null;
}

function iss_payments_lite_request_has_valid_nonce(WP_REST_Request $request): bool {
    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        return false;
    }

    return (bool) wp_verify_nonce($nonce, 'wp_rest');
}

function iss_payments_lite_validate_submit_timing(array $payload): ?WP_REST_Response {
    $settings = iss_payments_lite_get_settings();
    $minimum = (int) $settings['min_submit_seconds'];
    if ($minimum <= 0) {
        return null;
    }

    $loaded_at = isset($payload['loaded_at']) ? (int) $payload['loaded_at'] : 0;
    if ($loaded_at <= 0) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrage.'], 400);
    }

    $elapsed = time() - $loaded_at;
    if ($elapsed < $minimum || $elapsed > 2 * HOUR_IN_SECONDS) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrage.'], 400);
    }

    return null;
}

function iss_payments_lite_reject_honeypot(array $payload): ?WP_REST_Response {
    $honeypot = trim((string) ($payload['website'] ?? $payload['url'] ?? ''));
    if ($honeypot !== '') {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrage.'], 400);
    }

    return null;
}

function iss_payments_lite_supported_payment_methods(): array {
    $methods = ['onsite'];
    $methods = apply_filters('iss_payments_lite_supported_payment_methods', $methods);
    if (!is_array($methods)) {
        $methods = ['onsite'];
    }

    return array_values(array_unique(array_filter(array_map('sanitize_key', $methods))));
}

function iss_payments_lite_payment_method_is_supported(string $payment): bool {
    return in_array(sanitize_key($payment), iss_payments_lite_supported_payment_methods(), true);
}

function iss_payments_lite_duplicate_hash(string $scope, array $parts): string {
    return hash('sha256', $scope . '|' . implode('|', array_map('strval', $parts)));
}

function iss_payments_lite_recent_duplicate_exists(string $request_kind, string $duplicate_hash, int $window = DAY_IN_SECONDS): bool {
    global $wpdb;

    $request_kind = sanitize_key($request_kind);
    $duplicate_hash = sanitize_text_field($duplicate_hash);
    if ($request_kind === '' || $duplicate_hash === '' || !iss_payments_lite_table_exists()) {
        return false;
    }

    $since = wp_date('Y-m-d H:i:s', time() - max(60, $window), wp_timezone());
    $table = iss_payments_lite_requests_table_name();
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE request_kind = %s AND duplicate_hash = %s AND created_at >= %s",
            $request_kind,
            $duplicate_hash,
            $since
        )
    );

    return $count > 0;
}

function iss_payments_lite_check_duplicate(string $scope, array $parts, int $window = 10 * MINUTE_IN_SECONDS): ?WP_REST_Response {
    $fingerprint = iss_payments_lite_duplicate_hash($scope, $parts);
    $key = 'iss_payments_lite_dupe_' . md5($fingerprint);
    if (get_transient($key)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Diese Anfrage wurde bereits gesendet.'], 409);
    }

    set_transient($key, 1, $window);
    return null;
}

function iss_payments_lite_record_notification_result(int $request_id, bool $sent, string $error = ''): void {
    global $wpdb;

    if ($request_id <= 0 || !iss_payments_lite_table_exists()) {
        return;
    }

    $fields = [
        'notification_error' => $sent ? '' : substr(sanitize_text_field($error), 0, 255),
        'updated_at' => current_time('mysql'),
    ];
    $formats = ['%s', '%s'];
    if ($sent) {
        $fields['notified_at'] = current_time('mysql');
        $formats[] = '%s';
    }

    $wpdb->update(
        iss_payments_lite_requests_table_name(),
        $fields,
        ['id' => $request_id],
        $formats,
        ['%d']
    );
}

function iss_payments_lite_send_request_notification(int $request_id, string $request_kind, array $entry): void {
    $settings = iss_payments_lite_get_settings();
    $email = sanitize_email((string) $settings['notification_email']);
    if (empty($settings['notifications_enabled']) || !is_email($email)) {
        return;
    }

    $labels = [
        'publication_order' => __('Neue Publikationsbestellung', 'iss-payments-lite'),
        'event_booking' => __('Neue Veranstaltungsbuchung', 'iss-payments-lite'),
        'tour_booking' => __('Neue Buchungsanfrage', 'iss-payments-lite'),
    ];
    $label = $labels[$request_kind] ?? __('Neue Anfrage', 'iss-payments-lite');
    $subject = sprintf('[%s] %s #%d', wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES), $label, $request_id);
    $lines = [
        $label . ' #' . $request_id,
        '',
        'Name: ' . (string) ($entry['name'] ?? ''),
        'E-Mail: ' . (string) ($entry['email'] ?? ''),
        'Titel: ' . (string) ($entry['title'] ?? ''),
        'Anzahl: ' . (string) ($entry['tickets'] ?? $entry['quantity'] ?? ''),
        'Zahlung: ' . (string) ($entry['payment'] ?? ''),
        'Zeit: ' . (string) ($entry['start'] ?? $entry['time'] ?? ''),
        '',
        admin_url('tools.php?page=iss-payments-lite-requests&request_id=' . $request_id),
    ];

    $sent = wp_mail($email, $subject, implode("\n", $lines));
    iss_payments_lite_record_notification_result($request_id, (bool) $sent, $sent ? '' : 'wp_mail returned false');
}

function iss_payments_lite_public_payload(WP_REST_Request $request, string $scope) {
    $rate_limited = iss_payments_lite_check_rate_limit($scope);
    if ($rate_limited instanceof WP_REST_Response) {
        return $rate_limited;
    }

    $guarded = iss_payments_lite_public_request_guard($request, $scope);
    if ($guarded instanceof WP_REST_Response) {
        return $guarded;
    }

    $payload = json_decode($request->get_body(), true);
    if (!is_array($payload)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Invalid payload'], 400);
    }

    $honeypot = iss_payments_lite_reject_honeypot($payload);
    if ($honeypot instanceof WP_REST_Response) {
        return $honeypot;
    }

    $timing = iss_payments_lite_validate_submit_timing($payload);
    if ($timing instanceof WP_REST_Response) {
        return $timing;
    }

    return $payload;
}

function iss_payments_lite_create_request(WP_REST_Request $request) {
    $payload = iss_payments_lite_public_payload($request, 'commerce-request');
    if ($payload instanceof WP_REST_Response) {
        return $payload;
    }

    return iss_payments_lite_process_request_payload($payload, $request);
}

function iss_payments_lite_create_tour_booking(WP_REST_Request $request) {
    $payload = iss_payments_lite_public_payload($request, 'tour-booking');
    if ($payload instanceof WP_REST_Response) {
        return $payload;
    }

    $payload['request_kind'] = 'tour_booking';
    return iss_payments_lite_process_request_payload($payload, $request);
}

function iss_payments_lite_create_publication_order(WP_REST_Request $request) {
    $payload = iss_payments_lite_public_payload($request, 'publication-order');
    if ($payload instanceof WP_REST_Response) {
        return $payload;
    }

    $payload['request_kind'] = 'publication_order';
    if (!isset($payload['source_post_id']) && isset($payload['publication_id'])) {
        $payload['source_post_id'] = (int) $payload['publication_id'];
    }
    $payload['source_post_type'] = 'publication';

    return iss_payments_lite_process_request_payload($payload, $request);
}

function iss_payments_lite_process_request_payload(array $payload, WP_REST_Request $request): WP_REST_Response {
    $request_kind = sanitize_key((string) ($payload['request_kind'] ?? ''));
    if (!in_array($request_kind, ['tour_booking', 'event_booking', 'publication_order'], true)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrageart.'], 400);
    }

    if ($request_kind === 'publication_order') {
        return iss_payments_lite_process_publication_order($payload, $request);
    }

    return iss_payments_lite_process_booking_request($payload, $request_kind, $request);
}

function iss_payments_lite_process_booking_request(array $payload, string $request_kind, WP_REST_Request $request): WP_REST_Response {
    $name = sanitize_text_field($payload['name'] ?? '');
    $email = sanitize_email($payload['email'] ?? '');
    $tickets = isset($payload['tickets']) ? (int) $payload['tickets'] : 0;
    $slot_id = isset($payload['slot_id']) ? trim((string) $payload['slot_id']) : '';
    $payment = sanitize_text_field($payload['payment'] ?? '');

    $payload_tag = isset($payload['tag']) ? strtoupper(sanitize_text_field((string) $payload['tag'])) : '';
    $start = sanitize_text_field($payload['start'] ?? '');
    $title = sanitize_text_field($payload['title'] ?? '');
    $source_post_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : 0;
    $source_post_type = sanitize_key($payload['source_post_type'] ?? '');
    $price_cents = 0;

    $tag = $payload_tag;
    if ($request_kind === 'event_booking') {
        if ($source_post_id <= 0 || get_post_type($source_post_id) !== 'veranstaltung') {
            return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Veranstaltung.'], 400);
        }
        if (empty(get_post_meta($source_post_id, 'iss_booking_enabled', true))) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Diese Veranstaltung ist derzeit nicht buchbar.'], 400);
        }
        $source_post_type = 'veranstaltung';
        $price_cents = max(0, (int) get_post_meta($source_post_id, 'iss_booking_price_cents', true));
        if ($title === '') {
            $title = get_the_title($source_post_id);
        }
    }

    if ($source_post_id > 0 && $request_kind === 'tour_booking') {
        $resolved = '';
        if (function_exists('iss_occurrences_resolve_tag_for_source_post_id')) {
            $resolved = iss_occurrences_resolve_tag_for_source_post_id($source_post_id);
        }

        if ($tag === '' && $resolved !== '') {
            $tag = $resolved;
        } elseif ($tag !== '' && $resolved !== '' && $tag !== $resolved) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Zuordnung.'], 400);
        }
    }

    $errors = [];
    if ($name === '') {
        $errors[] = 'Name fehlt.';
    }
    if (!is_email($email)) {
        $errors[] = 'Ungültige E-Mail.';
    }
    if ($tickets < 1) {
        $errors[] = 'Bitte mindestens 1 Ticket.';
    }
    if ($slot_id === '') {
        $errors[] = 'Slot fehlt.';
    }
    if (!iss_payments_lite_payment_method_is_supported($payment)) {
        $errors[] = 'Ungültige Zahlungsart.';
    }
    if ($request_kind === 'tour_booking' && $tag === '' && $source_post_id <= 0) {
        $errors[] = 'Keine Zuordnung vorhanden.';
    }

    if ($tag !== '' || $source_post_id > 0) {
        $slots = [];
        if ($tag !== '' && function_exists('is_tours_get_cached_slots_by_tag')) {
            $slots = is_tours_get_cached_slots_by_tag($tag);
        }
        if (empty($slots) && function_exists('is_tours_get_occurrence_slots')) {
            $slots = is_tours_get_occurrence_slots($tag, $source_post_id);
            if ($tag !== '' && !empty($slots) && function_exists('is_tours_set_cached_slots_by_tag') && function_exists('is_tours_set_cached_source_by_tag')) {
                is_tours_set_cached_slots_by_tag($tag, $slots, 60 * 10);
                is_tours_set_cached_source_by_tag($tag, 'occurrences', 60 * 10);
            }
        }

        $found = null;
        foreach ($slots as $slot) {
            if (is_array($slot) && (string) ($slot['id'] ?? '') === (string) $slot_id) {
                $found = $slot;
                break;
            }
        }

        if ($found !== null) {
            if (array_key_exists('available', $found) && $found['available'] !== null && (int) $found['available'] <= 0) {
                $errors[] = 'Slot ist ausgebucht.';
            }
        } else {
            $errors[] = 'Ungültiger Slot.';
        }
    }

    if (!empty($errors)) {
        return new WP_REST_Response(['ok' => false, 'error' => implode(' ', $errors)], 400);
    }

    $duplicate_parts = [
        strtolower($email),
        $slot_id,
        $tag,
        $source_post_id,
    ];
    $duplicate_scope = $request_kind === 'event_booking' ? 'event-booking' : 'tour-booking';
    $duplicate = iss_payments_lite_check_duplicate($duplicate_scope, $duplicate_parts);
    if ($duplicate instanceof WP_REST_Response) {
        return $duplicate;
    }
    $duplicate_hash = iss_payments_lite_duplicate_hash($duplicate_scope, $duplicate_parts);
    if (iss_payments_lite_recent_duplicate_exists($request_kind, $duplicate_hash)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Diese Anfrage wurde bereits gesendet.'], 409);
    }

    $entry = [
        'time' => current_time('mysql'),
        'name' => $name,
        'email' => $email,
        'tickets' => $tickets,
        'slot_id' => $slot_id,
        'payment' => $payment,
        'tag' => $tag,
        'start' => $start,
        'title' => $title,
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'price_cents' => $price_cents,
        'amount_cents' => $price_cents * $tickets,
        'duplicate_hash' => $duplicate_hash,
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
        'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
    ];

    $request_id = iss_payments_lite_insert_request($request_kind, $entry);
    if ($request_id <= 0) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Anfrage konnte nicht gespeichert werden.'], 500);
    }
    $entry['request_id'] = $request_id;
    iss_payments_lite_send_request_notification($request_id, $request_kind, $entry);

    if ($request_kind === 'tour_booking') {
        /**
         * Compatibility hook for existing booking consumers.
         *
         * @param array $entry Sanitized booking entry.
         * @param WP_REST_Request $request Original REST request.
         */
        do_action('is_tours_booking_created', $entry, $request);
    }

    /**
     * New domain-neutral hook for thin booking/payment flows.
     *
     * @param array $entry Sanitized booking entry.
     * @param WP_REST_Request $request Original REST request.
     */
    do_action('iss_payments_lite_booking_created', $entry, $request);

    return new WP_REST_Response(['ok' => true], 200);
}

function iss_payments_lite_process_publication_order(array $payload, WP_REST_Request $request): WP_REST_Response {
    $publication_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : (int) ($payload['publication_id'] ?? 0);
    $name = sanitize_text_field($payload['name'] ?? '');
    $email = sanitize_email($payload['email'] ?? '');
    $quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : 0;
    $payment = sanitize_text_field($payload['payment'] ?? '');

    $errors = [];

    if ($publication_id <= 0 || get_post_type($publication_id) !== 'publication') {
        $errors[] = 'Ungültige Publikation.';
    }

    $sale_enabled = !empty(get_post_meta($publication_id, '_iss_publication_sale_enabled', true));
    $price_cents = (int) get_post_meta($publication_id, '_iss_publication_price_cents', true);
    if (!$sale_enabled || $price_cents <= 0) {
        $errors[] = 'Diese Publikation ist derzeit nicht bestellbar.';
    }

    if ($name === '') {
        $errors[] = 'Name fehlt.';
    }

    if (!is_email($email)) {
        $errors[] = 'Ungültige E-Mail.';
    }

    if ($quantity < 1) {
        $errors[] = 'Bitte mindestens 1 Exemplar.';
    }

    if (!iss_payments_lite_payment_method_is_supported($payment)) {
        $errors[] = 'Ungültige Zahlungsart.';
    }

    if (!empty($errors)) {
        return new WP_REST_Response(['ok' => false, 'error' => implode(' ', $errors)], 400);
    }

    $duplicate_parts = [
        strtolower($email),
        $publication_id,
        $quantity,
    ];
    $duplicate = iss_payments_lite_check_duplicate('publication-order', $duplicate_parts);
    if ($duplicate instanceof WP_REST_Response) {
        return $duplicate;
    }
    $duplicate_hash = iss_payments_lite_duplicate_hash('publication-order', $duplicate_parts);
    if (iss_payments_lite_recent_duplicate_exists('publication_order', $duplicate_hash)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Diese Anfrage wurde bereits gesendet.'], 409);
    }

    $entry = [
        'time' => current_time('mysql'),
        'publication_id' => $publication_id,
        'source_post_id' => $publication_id,
        'source_post_type' => 'publication',
        'title' => get_the_title($publication_id),
        'name' => $name,
        'email' => $email,
        'quantity' => $quantity,
        'payment' => $payment,
        'price_cents' => $price_cents,
        'amount_cents' => $price_cents * $quantity,
        'duplicate_hash' => $duplicate_hash,
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
        'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
    ];

    $request_id = iss_payments_lite_insert_request('publication_order', $entry);
    if ($request_id <= 0) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Anfrage konnte nicht gespeichert werden.'], 500);
    }
    $entry['request_id'] = $request_id;
    iss_payments_lite_send_request_notification($request_id, 'publication_order', $entry);

    do_action('iss_payments_lite_publication_order_created', $entry, $request);
    do_action('iss_payments_lite_order_created', 'publication', $entry, $request);

    return new WP_REST_Response(['ok' => true], 200);
}

add_filter('iss_publications_order_button_html', function ($html, $post_id, $context) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return $html;
    }

    $label = isset($context['label']) ? trim((string) $context['label']) : '';
    if ($label === '') {
        $label = 'Publikation bestellen';
    }

    $amount = isset($context['amount']) ? (int) $context['amount'] : 0;
    $title = isset($context['title']) ? (string) $context['title'] : get_the_title($post_id);

    return sprintf(
        '<div class="iss-publication-order-panel__actions"><button type="button" class="iss-publication-order-trigger" data-publication-id="%1$d" data-title="%2$s" data-amount="%3$d" data-label="%4$s">%5$s</button></div>',
        $post_id,
        esc_attr($title),
        $amount,
        esc_attr($label),
        esc_html($label)
    );
}, 10, 3);

require_once ISS_PAYMENTS_LITE_PATH . 'includes/admin.php';
require_once ISS_PAYMENTS_LITE_PATH . 'includes/cli.php';

register_activation_hook(__FILE__, function () {
    iss_payments_lite_install_schema();
});
