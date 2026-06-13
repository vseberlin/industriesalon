<?php
/**
 * Plugin Name: ISS Payments Lite
 * Description: Thin booking and payment entry layer for Industriesalon domain plugins.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This plugin owns the lightweight booking/order request table.

define('ISS_PAYMENTS_LITE_SCHEMA_VERSION', '2026-06-13-requests-v1');
define('ISS_PAYMENTS_LITE_SCHEMA_OPTION', 'iss_payments_lite_schema_version');

function iss_payments_lite_requests_table_name(): string {
    global $wpdb;

    return $wpdb->prefix . 'iss_payments_lite_requests';
}

function iss_payments_lite_table_exists(): bool {
    global $wpdb;

    $table = iss_payments_lite_requests_table_name();
    return (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
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

register_activation_hook(__FILE__, 'iss_payments_lite_install_schema');
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
        'orderUrl' => esc_url_raw(rest_url('iss-payments/v1/publication-order')),
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

function iss_payments_lite_request_has_valid_nonce(WP_REST_Request $request): bool {
    $nonce = (string) $request->get_header('X-WP-Nonce');
    if ($nonce === '') {
        return false;
    }

    return (bool) wp_verify_nonce($nonce, 'wp_rest');
}

function iss_payments_lite_reject_honeypot(array $payload): ?WP_REST_Response {
    $honeypot = trim((string) ($payload['website'] ?? $payload['url'] ?? ''));
    if ($honeypot !== '') {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrage.'], 400);
    }

    return null;
}

function iss_payments_lite_duplicate_hash(string $scope, array $parts): string {
    return hash('sha256', $scope . '|' . implode('|', array_map('strval', $parts)));
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

function iss_payments_lite_create_tour_booking(WP_REST_Request $request) {
    $rate_limited = iss_payments_lite_check_rate_limit('tour-booking');
    if ($rate_limited instanceof WP_REST_Response) {
        return $rate_limited;
    }

    $payload = json_decode($request->get_body(), true);
    if (!is_array($payload)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Invalid payload'], 400);
    }

    if ((string) $request->get_header('X-WP-Nonce') !== '' && !iss_payments_lite_request_has_valid_nonce($request)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrage.'], 403);
    }

    $honeypot = iss_payments_lite_reject_honeypot($payload);
    if ($honeypot instanceof WP_REST_Response) {
        return $honeypot;
    }

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

    $tag = $payload_tag;
    if ($source_post_id > 0) {
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
    if (!in_array($payment, ['onsite', 'mollie'], true)) {
        $errors[] = 'Ungültige Zahlungsart.';
    }
    if ($tag === '' && $source_post_id <= 0) {
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
    $duplicate = iss_payments_lite_check_duplicate('tour-booking', $duplicate_parts);
    if ($duplicate instanceof WP_REST_Response) {
        return $duplicate;
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
        'duplicate_hash' => iss_payments_lite_duplicate_hash('tour-booking', $duplicate_parts),
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
        'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
    ];

    $request_id = iss_payments_lite_insert_request('tour_booking', $entry);
    if ($request_id <= 0) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Anfrage konnte nicht gespeichert werden.'], 500);
    }
    $entry['request_id'] = $request_id;

    /**
     * Compatibility hook for existing booking consumers.
     *
     * @param array $entry Sanitized booking entry.
     * @param WP_REST_Request $request Original REST request.
     */
    do_action('is_tours_booking_created', $entry, $request);

    /**
     * New domain-neutral hook for thin booking/payment flows.
     *
     * @param array $entry Sanitized booking entry.
     * @param WP_REST_Request $request Original REST request.
     */
    do_action('iss_payments_lite_booking_created', $entry, $request);

    return new WP_REST_Response(['ok' => true], 200);
}

function iss_payments_lite_create_publication_order(WP_REST_Request $request) {
    $rate_limited = iss_payments_lite_check_rate_limit('publication-order');
    if ($rate_limited instanceof WP_REST_Response) {
        return $rate_limited;
    }

    $payload = json_decode($request->get_body(), true);
    if (!is_array($payload)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Invalid payload'], 400);
    }

    if ((string) $request->get_header('X-WP-Nonce') !== '' && !iss_payments_lite_request_has_valid_nonce($request)) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Ungültige Anfrage.'], 403);
    }

    $honeypot = iss_payments_lite_reject_honeypot($payload);
    if ($honeypot instanceof WP_REST_Response) {
        return $honeypot;
    }

    $publication_id = isset($payload['publication_id']) ? (int) $payload['publication_id'] : 0;
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

    if (!in_array($payment, ['onsite', 'mollie'], true)) {
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

    $entry = [
        'time' => current_time('mysql'),
        'publication_id' => $publication_id,
        'title' => get_the_title($publication_id),
        'name' => $name,
        'email' => $email,
        'quantity' => $quantity,
        'payment' => $payment,
        'price_cents' => $price_cents,
        'amount_cents' => $price_cents * $quantity,
        'duplicate_hash' => iss_payments_lite_duplicate_hash('publication-order', $duplicate_parts),
        'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field((string) $_SERVER['REMOTE_ADDR']) : '',
        'ua' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
    ];

    $request_id = iss_payments_lite_insert_request('publication_order', $entry);
    if ($request_id <= 0) {
        return new WP_REST_Response(['ok' => false, 'error' => 'Anfrage konnte nicht gespeichert werden.'], 500);
    }
    $entry['request_id'] = $request_id;

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
