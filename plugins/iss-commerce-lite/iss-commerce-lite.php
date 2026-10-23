<?php
/**
 * Plugin Name: ISS Commerce Lite
 * Description: Lightweight booking request and publication order intake for Industriesalon.
 * Version: 0.5.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This plugin owns the lightweight booking/order request table.

define('ISS_PAYMENTS_LITE_SCHEMA_VERSION', '2026-10-22-requests-v5-ledger');
define('ISS_PAYMENTS_LITE_SCHEMA_OPTION', 'iss_payments_lite_schema_version');
define('ISS_PAYMENTS_LITE_SETTINGS_OPTION', 'iss_payments_lite_settings');
define('ISS_PAYMENTS_LITE_PATH', plugin_dir_path(__FILE__));
define('ISS_COMMERCE_LITE_VERSION', '0.5.1');
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

function iss_payments_lite_install_schema(bool $allow_existing_upgrade = false): void {
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    require_once dirname(__DIR__) . '/iss-core/schema.php';

    global $wpdb;
    $columns = $wpdb->get_col($wpdb->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', iss_payments_lite_requests_table_name()));
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Commerce schema could not be inspected before upgrade.');
    }
    if ($columns && array_diff(['request_key', 'request_fingerprint', 'provider_payment_id', 'payment_status', 'payment_checkout_url', 'payment_payload', 'payment_started_at', 'departure_id', 'booking_state', 'payment_checked_at'], $columns) && !$allow_existing_upgrade) {
        throw new RuntimeException('Commerce upgrade requires the scoped backup in ops/migrations/2026-10-22-commerce-ledger.php.');
    }
    $statements = iss_payments_lite_schema_statements();
    foreach ($statements as $statement) {
        dbDelta($statement);
    }
    iss_core_verify_schema($statements);
    update_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, ISS_PAYMENTS_LITE_SCHEMA_VERSION, false);
    if ((string) get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, '') !== ISS_PAYMENTS_LITE_SCHEMA_VERSION) {
        throw new RuntimeException('Commerce schema version could not be recorded.');
    }
}

function iss_payments_lite_schema_statements(): array {
    global $wpdb;

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
        provider_payment_id varchar(64) DEFAULT NULL,
        payment_status varchar(32) NOT NULL DEFAULT '',
        payment_checkout_url varchar(512) NOT NULL DEFAULT '',
        payment_payload longtext DEFAULT NULL,
        payment_started_at datetime DEFAULT NULL,
        booking_reference varchar(32) DEFAULT NULL,
        departure_id bigint(20) unsigned NOT NULL DEFAULT 0,
        booking_state varchar(32) NOT NULL DEFAULT 'legacy',
        hold_expires_at datetime DEFAULT NULL,
        confirmed_at datetime DEFAULT NULL,
        cancelled_at datetime DEFAULT NULL,
        voucher_cents int(11) unsigned NOT NULL DEFAULT 0,
        provider_amount_cents int(11) unsigned NOT NULL DEFAULT 0,
        currency char(3) NOT NULL DEFAULT 'EUR',
        test_mode tinyint(1) NOT NULL DEFAULT 0,
        payment_checked_at datetime DEFAULT NULL,
        slot_id varchar(191) NOT NULL DEFAULT '',
        tag varchar(100) NOT NULL DEFAULT '',
        payload longtext NOT NULL,
        duplicate_hash varchar(64) NOT NULL DEFAULT '',
        request_key char(64) DEFAULT NULL,
        request_fingerprint char(64) NOT NULL DEFAULT '',
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
        KEY duplicate_lookup (duplicate_hash),
        UNIQUE KEY request_key (request_key),
        UNIQUE KEY provider_payment_id (provider_payment_id),
        UNIQUE KEY booking_reference (booking_reference),
        KEY departure_allocation (departure_id, booking_state, hold_expires_at),
        KEY reconcile_payments (payment_checked_at, payment_status)
    ) ENGINE=InnoDB {$charset_collate};";

    return array_merge([$sql], ISS_Commerce_Ledger::schema());
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
    if ($source_post_type === '' && $request_kind === 'order') {
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
            'request_key' => !empty($entry['request_key']) ? (string) $entry['request_key'] : null,
            'request_fingerprint' => (string) ($entry['request_fingerprint'] ?? ''),
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
            '%s',
            '%s',
        ]
    );

    return $inserted === 1 ? (int) $wpdb->insert_id : 0;
}

function iss_payments_lite_maybe_upgrade_schema(): void {
    require_once dirname(__DIR__) . '/iss-core/schema.php';
    iss_core_admin_schema_upgrade(static function (): void {
        iss_payments_lite_maybe_install_schema();
    });
}
add_action('admin_init', 'iss_payments_lite_maybe_upgrade_schema', 4);

add_action('rest_api_init', function () {
    // Public intentionally: visitors submit booking, inquiry, and order requests.
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
    if ($request_kind === '' || $duplicate_hash === '') {
        return false;
    }

    $since = wp_date('Y-m-d H:i:s', time() - max(60, $window), wp_timezone());
    $table = iss_payments_lite_requests_table_name();
    $count = (int) $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE request_kind = %s AND duplicate_hash = %s AND created_at >= %s AND payment_status NOT IN ('failed', 'canceled', 'expired')",
            $request_kind,
            $duplicate_hash,
            $since
        )
    );

    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Commerce duplicate lookup failed.');
    }
    return $count > 0;
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
        'booking' => __('Neue Buchungsanfrage', 'iss-payments-lite'),
        'inquiry' => __('Neue Terminanfrage', 'iss-payments-lite'),
        'order' => __('Neue Bestellung', 'iss-payments-lite'),
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
    if ($request_kind === 'booking') {
        array_splice($lines, 4, 0, [
            'Regulär: ' . (string) ($entry['regular_tickets'] ?? $entry['tickets'] ?? 0),
            'Ermäßigt: ' . (string) ($entry['reduced_tickets'] ?? 0),
            'Gesamtpreis: ' . number_format_i18n((int) ($entry['amount_cents'] ?? 0) / 100, 2) . ' €',
        ]);
    }

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

/** A reduced count is a subset of the total seat count, including on retries. */
function iss_payments_lite_booking_ticket_counts(array $payload): array {
    $counts = [];
    foreach (['tickets', 'reduced_tickets'] as $key) {
        $value = array_key_exists($key, $payload) ? $payload[$key] : 0;
        if ((!is_int($value) && !is_string($value)) || !preg_match('/^(0|[1-9][0-9]*)$/D', (string) $value)
            || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 2147483647]]) === false) {
            throw new InvalidArgumentException('Bitte eine ganze, nicht negative Ticketanzahl angeben.');
        }
        $counts[$key] = (int) $value;
    }
    if ($counts['tickets'] < 1 || $counts['reduced_tickets'] > $counts['tickets']) {
        throw new InvalidArgumentException('Bitte mindestens 1 Ticket wählen; ermäßigte Tickets sind Teil der Gesamtanzahl.');
    }
    return $counts;
}

/** The fingerprint describes customer intent, excluding mutable prices, titles and transport metadata. */
function iss_payments_lite_request_identity(string $kind, array $payload): array {
    $key = $payload['request_key'] ?? '';
    if (!is_string($key) || ($key !== '' && !preg_match('/^[a-zA-Z0-9_-]{16,128}$/D', $key))) {
        throw new InvalidArgumentException('Invalid request key.');
    }
    $intent = [
        'kind' => $kind,
        'source_post_id' => (int) ($payload['source_post_id'] ?? $payload['publication_id'] ?? 0),
        'source_post_type' => $kind === 'order' ? 'publication' : sanitize_key($payload['source_post_type'] ?? ''),
        'name' => sanitize_text_field($payload['name'] ?? ''),
        'email' => strtolower(sanitize_email($payload['email'] ?? '')),
        'quantity' => $kind === 'booking' ? (int) ($payload['tickets'] ?? 0) : ($kind === 'order' ? (int) ($payload['quantity'] ?? 0) : 1),
        'payment' => $kind === 'inquiry' ? 'onsite' : sanitize_key($payload['payment'] ?? ''),
        'slot_id' => $kind === 'booking' ? trim(sanitize_text_field($payload['slot_id'] ?? '')) : '',
        'start' => $kind === 'inquiry' ? sanitize_text_field($payload['start'] ?? '') : '',
    ];
    if ($kind === 'booking') {
        if (!empty($payload['voucher_code'])) { $intent['voucher'] = ISS_Commerce_Vouchers::fingerprint((string) $payload['voucher_code']); }
        $counts = iss_payments_lite_booking_ticket_counts($payload);
        // Preserve fingerprints of already committed regular-only requests.
        if ($counts['reduced_tickets'] > 0) {
            $intent['reduced_tickets'] = $counts['reduced_tickets'];
        }
    }
    return ['key' => $key !== '' ? hash('sha256', $key) : null, 'fingerprint' => hash('sha256', (string) wp_json_encode($intent)), 'kind' => $kind];
}

/** Replaying a committed request does not depend on later availability or send another notification. */
function iss_payments_lite_replay_request(array $identity): ?WP_REST_Response {
    if ($identity['key'] === null) {
        return null;
    }
    global $wpdb;
    $table = iss_payments_lite_requests_table_name();
    $row = $wpdb->get_row($wpdb->prepare("SELECT request_kind, request_fingerprint, id, payment_method FROM {$table} WHERE request_key = %s", $identity['key']), ARRAY_A);
    if ($wpdb->last_error !== '') {
        throw new RuntimeException('Commerce retry lookup failed.');
    }
    if (!$row) {
        return null;
    }
    return $row['request_kind'] === $identity['kind'] && hash_equals($row['request_fingerprint'], $identity['fingerprint'])
        ? iss_commerce_request_response((int) $row['id'], $row['payment_method'])
        : new WP_REST_Response(['ok' => false, 'error' => 'Diese Anfrage wurde bereits mit anderen Angaben gesendet. Bitte das Formular erneut öffnen.'], 409);
}

function iss_payments_lite_process_request_payload(array $payload, WP_REST_Request $request): WP_REST_Response {
    $intent = sanitize_key((string) ($payload['intent'] ?? ''));
    if (!in_array($intent, ['booking', 'inquiry', 'order'], true)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrageart.'], 400);
    }
    if ((string) get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, '') !== ISS_PAYMENTS_LITE_SCHEMA_VERSION) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Anfragen sind vorübergehend nicht verfügbar. Bitte später erneut versuchen.'], 503);
    }
    if (($payload['payment'] ?? '') === 'mollie' && ($intent !== 'booking' || empty($payload['request_key']))) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Online-Zahlung benötigt eine Buchung mit Anfragekennung.'], 400);
    }
    try {
        $identity = iss_payments_lite_request_identity($intent, $payload);
        $replay = iss_payments_lite_replay_request($identity);
        if ($replay) {
            return $replay;
        }
        if ($intent === 'order') {
            return iss_payments_lite_process_order_request($payload, $request);
        }
        if ($intent === 'inquiry') {
            return iss_payments_lite_process_inquiry_request($payload, $request);
        }
        return iss_payments_lite_process_slot_booking_request($payload, $request);
    } catch (DomainException $error) {
        return new WP_REST_Response(['ok' => false, 'error' => $error->getMessage()], 409);
    } catch (InvalidArgumentException $error) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfragekennung.'], 400);
    } catch (RuntimeException $error) {
        if (function_exists('iss_core_debug_log')) {
            iss_core_debug_log('Commerce request persistence failed.', ['error' => $error->getMessage()]);
        }
        return new WP_REST_Response(['ok' => false, 'error' => 'Anfrage konnte nicht gespeichert werden. Bitte erneut versuchen.'], 500);
    }
}

/** Serialize the existing duplicate window, then use the unique request key to settle retry races. */
function iss_payments_lite_store_request(string $kind, array $entry, array $payload, WP_REST_Request $request): WP_REST_Response {
    global $wpdb;
    $identity = iss_payments_lite_request_identity($kind, $payload);
    if ($kind === 'booking') {
        $result = ISS_Commerce_Ledger::admit($entry, $identity, (string) ($payload['voucher_code'] ?? ''));
        if ($result['created']) {
            $entry['request_id'] = $result['id'];
            iss_payments_lite_send_request_notification($result['id'], $kind, $entry);
            do_action('iss_payments_lite_booking_created', $entry, $request);
            do_action('iss_payments_lite_request_created', $kind, $entry, $request);
        }
        return iss_commerce_request_response($result['id'], (string) $entry['payment']);
    }
    $state = $wpdb->get_row('SELECT @@SESSION.in_transaction AS in_transaction, @@SESSION.autocommit AS autocommit, DATABASE() AS database_name', ARRAY_A);
    if ($wpdb->last_error !== '' || !$state || !isset($state['in_transaction'], $state['autocommit'], $state['database_name']) || (int) $state['in_transaction'] !== 0 || (int) $state['autocommit'] !== 1) {
        throw new RuntimeException('Commerce intake requires an independent committed write.');
    }
    $lock = 'iss_request_' . substr(hash('sha256', $state['database_name'] . '|' . $wpdb->prefix . '|' . $entry['duplicate_hash']), 0, 48);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock)) !== 1) {
        throw new RuntimeException('Commerce request is busy; retry.');
    }
    try {
        $replay = iss_payments_lite_replay_request($identity);
        if ($replay) {
            return $replay;
        }
        if (iss_payments_lite_recent_duplicate_exists($kind, $entry['duplicate_hash'])) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Diese Anfrage wurde bereits gesendet.'], 409);
        }
        $entry['request_key'] = $identity['key'];
        $entry['request_fingerprint'] = $identity['fingerprint'];
        $request_id = iss_payments_lite_insert_request($kind, $entry);
        if ($request_id <= 0) {
            // A different intent may have raced with this request key under another duplicate lock.
            $replay = iss_payments_lite_replay_request($identity);
            if ($replay) {
                return $replay;
            }
            throw new RuntimeException('Commerce request insert failed.');
        }
    } finally {
        if ((int) $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)) !== 1 && function_exists('iss_core_debug_log')) {
            // The insert is already committed. Do not skip its dispatch or replace a
            // persistence error; connection teardown also releases this session lock.
            iss_core_debug_log('Commerce request lock release could not be confirmed.');
        }
    }
    $entry['request_id'] = $request_id;
    iss_payments_lite_send_request_notification($request_id, $kind, $entry);
    if ($kind === 'order') {
        do_action('iss_payments_lite_order_created', 'publication', $entry, $request);
    } else {
        do_action('iss_payments_lite_' . $kind . '_created', $entry, $request);
    }
    do_action('iss_payments_lite_request_created', $kind, $entry, $request);
    return iss_commerce_request_response($request_id, (string) ($entry['payment'] ?? ''));
}

function iss_payments_lite_process_slot_booking_request(array $payload, WP_REST_Request $request): WP_REST_Response {
    $name = sanitize_text_field($payload['name'] ?? '');
    $email = sanitize_email($payload['email'] ?? '');
    $counts = iss_payments_lite_booking_ticket_counts($payload);
    $tickets = $counts['tickets'];
    $reduced_tickets = $counts['reduced_tickets'];
    $regular_tickets = $tickets - $reduced_tickets;
    $slot_id = isset($payload['slot_id']) ? trim((string) $payload['slot_id']) : '';
    $payment = sanitize_text_field($payload['payment'] ?? '');

    $payload_tag = isset($payload['tag']) ? strtoupper(sanitize_text_field((string) $payload['tag'])) : '';
    $start = sanitize_text_field($payload['start'] ?? '');
    $title = sanitize_text_field($payload['title'] ?? '');
    $source_post_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : 0;
    $source_post_type = sanitize_key($payload['source_post_type'] ?? '');
    $price_cents = 0;
    $reduced_price_cents = 0;

    $tag = $payload_tag;
    if ($source_post_id <= 0 || !in_array($source_post_type, ['fuehrung', 'veranstaltung'], true) || get_post_type($source_post_id) !== $source_post_type || !is_post_publicly_viewable($source_post_id) || get_post_field('post_password', $source_post_id) !== '') {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Quelle.'], 400);
    }

    if ($source_post_type === 'veranstaltung') {
        if (empty(get_post_meta($source_post_id, 'iss_booking_enabled', true))) {
            return new WP_REST_Response(['ok' => false, 'error' => 'Diese Veranstaltung ist derzeit nicht buchbar.'], 400);
        }
        $price_cents = max(0, (int) get_post_meta($source_post_id, 'iss_booking_price_cents', true));
    } elseif ($source_post_type === 'fuehrung') {
        $price_cents = max(0, (int) get_post_meta($source_post_id, 'booking_price_cents', true));
        $reduced_price_cents = max(0, (int) get_post_meta($source_post_id, 'booking_reduced_price_cents', true));
    }
    if ($reduced_tickets > 0 && ($reduced_price_cents <= 0 || $reduced_price_cents >= $price_cents)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Für dieses Angebot sind keine ermäßigten Tickets verfügbar. Bitte die Seite neu laden.'], 400);
    }
    if ($title === '') {
        $title = get_the_title($source_post_id);
    }

    if ($source_post_type === 'fuehrung') {
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
    if ($payment === 'mollie' && (!iss_commerce_mollie_enabled() || $price_cents * $regular_tickets + $reduced_price_cents * $reduced_tickets < 1)) {
        $errors[] = 'Online-Zahlung ist für dieses Angebot nicht verfügbar.';
    }
    if ($price_cents * $regular_tickets + $reduced_price_cents * $reduced_tickets > 2147483647) {
        $errors[] = 'Der Gesamtbetrag ist zu hoch.';
    }

    if ($source_post_id > 0) {
        $slots = [];
        if (function_exists('iss_occurrences_get_booking_slots')) {
            $slots = iss_occurrences_get_booking_slots([
                'tag' => $tag,
                'source_post_id' => $source_post_id,
                'source_post_type' => $source_post_type,
            ]);
        }

        $found = null;
        foreach ($slots as $slot) {
            if (is_array($slot) && (string) ($slot['id'] ?? '') === (string) $slot_id) {
                $found = $slot;
                break;
            }
        }

        if ($found !== null) {
            if (in_array($found['availability_state'] ?? '', ['cancelled', 'unavailable', 'sold_out'], true)) {
                $errors[] = 'Dieser Termin ist nicht buchbar.';
            }
            if (array_key_exists('available', $found) && $found['available'] !== null && $tickets > (int) $found['available']) {
                $errors[] = 'Für diese Anzahl sind nicht genügend Plätze verfügbar.';
            }
            $start = (string) $found['start'];
            $title = trim((string) ($found['title'] ?? '')) ?: get_the_title($source_post_id);
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
        $source_post_id,
    ];
    $duplicate_hash = iss_payments_lite_duplicate_hash('booking', $duplicate_parts);

    $entry = [
        'time' => current_time('mysql'),
        'name' => $name,
        'email' => $email,
        'tickets' => $tickets,
        'regular_tickets' => $regular_tickets,
        'reduced_tickets' => $reduced_tickets,
        'slot_id' => $slot_id,
        'departure_id' => (int) ($found['departure_id'] ?? 0),
        'payment' => $payment,
        'tag' => $tag,
        'start' => $start,
        'title' => $title,
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'price_cents' => $price_cents,
        'reduced_price_cents' => $reduced_tickets > 0 ? $reduced_price_cents : 0,
        'amount_cents' => $price_cents * $regular_tickets + $reduced_price_cents * $reduced_tickets,
        'duplicate_hash' => $duplicate_hash,
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
        'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
    ];

    return iss_payments_lite_store_request('booking', $entry, $payload, $request);
}

function iss_payments_lite_process_inquiry_request(array $payload, WP_REST_Request $request): WP_REST_Response {
    $source_post_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : 0;
    $source_post_type = sanitize_key($payload['source_post_type'] ?? '');
    $name = sanitize_text_field($payload['name'] ?? '');
    $email = sanitize_email($payload['email'] ?? '');
    $start = sanitize_text_field($payload['start'] ?? '');
    $title = sanitize_text_field($payload['title'] ?? '');

    $errors = [];
    if (!function_exists('iss_occurrences_booking_inquiry_allowed') || !iss_occurrences_booking_inquiry_allowed($source_post_id, $source_post_type)) {
        $errors[] = 'Ungültige Quelle.';
    }
    if ($name === '') {
        $errors[] = 'Name fehlt.';
    }
    if (!is_email($email)) {
        $errors[] = 'Ungültige E-Mail.';
    }
    $format = strlen($start) === 10 ? 'Y-m-d' : 'Y-m-d\\TH:i:s';
    $preferred = DateTimeImmutable::createFromFormat('!' . $format, $start, wp_timezone());
    $now = current_datetime();
    if (!$preferred || $preferred->format($format) !== $start || (strlen($start) === 10 ? $preferred->format('Y-m-d') < $now->format('Y-m-d') : $preferred < $now)) {
        $errors[] = 'Bitte einen gültigen zukünftigen Wunschtermin angeben.';
    }

    if (!empty($errors)) {
        return new WP_REST_Response(['ok' => false, 'error' => implode(' ', $errors)], 400);
    }

    if ($title === '') {
        $title = get_the_title($source_post_id);
    }

    $duplicate_parts = [
        strtolower($email),
        $source_post_id,
        $start,
    ];
    $duplicate_hash = iss_payments_lite_duplicate_hash('inquiry', $duplicate_parts);

    $entry = [
        'time' => current_time('mysql'),
        'name' => $name,
        'email' => $email,
        'tickets' => 1,
        'payment' => 'onsite',
        'start' => $start,
        'title' => $title,
        'source_post_id' => $source_post_id,
        'source_post_type' => $source_post_type,
        'duplicate_hash' => $duplicate_hash,
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
        'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
    ];

    return iss_payments_lite_store_request('inquiry', $entry, $payload, $request);
}

function iss_payments_lite_process_order_request(array $payload, WP_REST_Request $request): WP_REST_Response {
    $publication_id = isset($payload['source_post_id']) ? (int) $payload['source_post_id'] : (int) ($payload['publication_id'] ?? 0);
    $name = sanitize_text_field($payload['name'] ?? '');
    $email = sanitize_email($payload['email'] ?? '');
    $quantity = isset($payload['quantity']) ? (int) $payload['quantity'] : 0;
    $payment = sanitize_text_field($payload['payment'] ?? '');

    $errors = [];

    if ($publication_id <= 0 || get_post_type($publication_id) !== 'publication' || !is_post_publicly_viewable($publication_id) || get_post_field('post_password', $publication_id) !== '') {
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
    $duplicate_hash = iss_payments_lite_duplicate_hash('order', $duplicate_parts);

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

    return iss_payments_lite_store_request('order', $entry, $payload, $request);
}

require_once ISS_PAYMENTS_LITE_PATH . 'includes/ledger.php';
require_once ISS_PAYMENTS_LITE_PATH . 'includes/vouchers.php';
require_once ISS_PAYMENTS_LITE_PATH . 'includes/worker.php';
add_filter('iss_occurrences_public_item', [ISS_Commerce_Ledger::class, 'availability'], 10, 2);
require_once ISS_PAYMENTS_LITE_PATH . 'includes/mollie.php';
require_once ISS_PAYMENTS_LITE_PATH . 'includes/admin.php';
require_once ISS_PAYMENTS_LITE_PATH . 'includes/ledger-admin.php';
require_once ISS_PAYMENTS_LITE_PATH . 'includes/cli.php';

register_activation_hook(__FILE__, function () {
    iss_payments_lite_install_schema();
});
