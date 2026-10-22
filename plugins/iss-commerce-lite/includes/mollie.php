<?php
/** Mollie test payments belong to the existing commerce request, not the occurrence projection. */
if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- This service owns the commerce table and serializes each payment with a database session lock.

function iss_commerce_mollie_test_key(): string {
    $key = defined('ISS_MOLLIE_API_KEY') ? constant('ISS_MOLLIE_API_KEY') : '';
    return is_string($key) && preg_match('/^test_[A-Za-z0-9]+$/D', $key) === 1 ? $key : '';
}

function iss_commerce_mollie_enabled(): bool {
    return defined('ISS_MOLLIE_ENABLED') && ISS_MOLLIE_ENABLED === true && iss_commerce_mollie_test_key() !== ''
        && (string) get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, '') === ISS_PAYMENTS_LITE_SCHEMA_VERSION;
}

add_filter('iss_payments_lite_supported_payment_methods', static function (array $methods): array {
    if (iss_commerce_mollie_enabled()) { $methods[] = 'mollie'; }
    return $methods;
});

function iss_commerce_payment_row(int $id): array {
    global $wpdb;
    $table = iss_payments_lite_requests_table_name();
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    if ($wpdb->last_error !== '' || !$row || $row['payment_method'] !== 'mollie' || $row['request_kind'] !== 'booking') {
        throw new RuntimeException('Payment booking unavailable.');
    }
    return $row;
}

/** This bearer grants only the minimal payment receipt, never customer details or mutations. */
function iss_commerce_payment_token(array $row): string {
    return hash_hmac('sha256', 'mollie-receipt|' . $row['id'] . '|' . $row['request_key'], wp_salt('auth'));
}

function iss_commerce_mollie_api(string $method, string $path, ?array $body = null, string $idempotency = ''): array {
    // Turning checkout off must not stop callbacks for payments already created.
    $key = iss_commerce_mollie_test_key();
    if ($key === '') { throw new RuntimeException('Mollie test key is unavailable.'); }
    $headers = ['Authorization' => 'Bearer ' . $key, 'Accept' => 'application/json'];
    if ($idempotency !== '') { $headers['Idempotency-Key'] = $idempotency; }
    $args = ['method' => $method, 'headers' => $headers, 'timeout' => 15, 'redirection' => 0];
    if ($body !== null) {
        $args['headers']['Content-Type'] = 'application/json';
        $args['body'] = wp_json_encode($body);
    }
    $response = wp_remote_request('https://api.mollie.com/v2/' . $path, $args);
    // Never log provider bodies, transport arguments, credentials or customer information.
    if (is_wp_error($response) || !in_array(wp_remote_retrieve_response_code($response), [200, 201], true)) {
        throw new RuntimeException('Mollie request could not be confirmed.');
    }
    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (!is_array($data)) { throw new RuntimeException('Invalid Mollie response.'); }
    return $data;
}

function iss_commerce_payment_write(int $id, array $fields): void {
    global $wpdb;
    $fields['updated_at'] = current_time('mysql');
    if ($wpdb->update(iss_payments_lite_requests_table_name(), $fields, ['id' => $id]) === false) {
        throw new RuntimeException('Payment state could not be saved.');
    }
}

function iss_commerce_payment_lock(int $id, callable $operation): array {
    global $wpdb;
    $lock = 'iss_mollie_' . substr(hash('sha256', constant('DB_NAME') . '|' . $wpdb->prefix . '|' . $id), 0, 48);
    if ((int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 2)', $lock)) !== 1) {
        throw new RuntimeException('Payment is busy; retry.');
    }
    try { return $operation(iss_commerce_payment_row($id)); }
    finally { $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock)); }
}

function iss_commerce_mollie_checkout_url(string $url): string {
    $parts = wp_parse_url($url);
    if (!$parts || ($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', ['www.mollie.com', 'mollie.com', 'payment.mollie.com'], true)
        || isset($parts['user']) || isset($parts['pass']) || isset($parts['port']) || strlen($url) > 512) {
        throw new RuntimeException('Invalid Mollie checkout URL.');
    }
    return $url;
}

/** Only authenticated provider reads establish payment truth. Caller holds the booking lock. */
function iss_commerce_mollie_accept(array $row, array $payment): array {
    $id = (string) ($payment['id'] ?? '');
    $expected = number_format((int) $row['amount_cents'] / 100, 2, '.', '');
    if (!preg_match('/^tr_[A-Za-z0-9]{5,60}$/D', $id)
        || (!empty($row['provider_payment_id']) && $row['provider_payment_id'] !== $id)
        || ($payment['mode'] ?? '') !== 'test' || ($payment['amount']['currency'] ?? '') !== 'EUR'
        || ($payment['amount']['value'] ?? '') !== $expected
        || (string) ($payment['metadata']['booking_id'] ?? '') !== (string) $row['id']
        || !hash_equals(iss_commerce_payment_token($row), (string) ($payment['metadata']['reference'] ?? ''))) {
        throw new RuntimeException('Payment does not match the booking.');
    }
    $status = (string) ($payment['status'] ?? '');
    if (!in_array($status, ['open', 'pending', 'authorized', 'paid', 'failed', 'canceled', 'expired'], true)) {
        throw new RuntimeException('Unrecognized payment status.');
    }
    if ((float) ($payment['amountChargedBack']['value'] ?? 0) > 0) { $status = 'charged_back'; }
    elseif ((float) ($payment['amountRefunded']['value'] ?? 0) > 0) {
        $status = (string) $payment['amountRefunded']['value'] === $expected ? 'refunded' : 'partially_refunded';
    }
    $checkout = '';
    if (in_array($status, ['open', 'pending'], true) && !empty($payment['_links']['checkout']['href'])) {
        $checkout = iss_commerce_mollie_checkout_url((string) $payment['_links']['checkout']['href']);
    }
    $fields = ['provider_payment_id' => $id, 'payment_status' => $status, 'payment_checkout_url' => $checkout];
    // Exact duplicates do not rewrite timestamps or dispatch actions/notifications.
    if (array_intersect_key($row, $fields) !== $fields) { iss_commerce_payment_write((int) $row['id'], $fields); }
    return array_merge($row, $fields);
}

function iss_commerce_mollie_payment(int $id, bool $create): array {
    return iss_commerce_payment_lock($id, static function (array $row) use ($create): array {
        if (!empty($row['provider_payment_id'])) {
            return iss_commerce_mollie_accept($row, iss_commerce_mollie_api('GET', 'payments/' . rawurlencode($row['provider_payment_id'])));
        }
        if (!$create) { return $row; }
        if (!iss_commerce_mollie_enabled()) { throw new RuntimeException('New Mollie payments are disabled.'); }
        if (empty($row['payment_started_at'])) {
            $token = iss_commerce_payment_token($row);
            $body = [
                'amount' => ['currency' => 'EUR', 'value' => number_format((int) $row['amount_cents'] / 100, 2, '.', '')],
                'description' => 'Industriesalon Testbuchung #' . $row['id'],
                'redirectUrl' => home_url('/?iss_payment_return=1') . '#iss-payment=' . $row['id'] . '.' . $token,
                'webhookUrl' => rest_url('iss-payments/v1/mollie/webhook'),
                'locale' => 'de_DE',
                // These hosted methods require no customer address or email in the API payload.
                'method' => ['creditcard', 'paypal'],
                'metadata' => ['booking_id' => (string) $row['id'], 'reference' => $token],
            ];
            $fields = ['payment_started_at' => gmdate('Y-m-d H:i:s'), 'payment_status' => 'creating', 'payment_payload' => wp_json_encode($body)];
            iss_commerce_payment_write((int) $row['id'], $fields);
            $row = array_merge($row, $fields);
        }
        // Mollie retains idempotent responses for one hour. Never create a second
        // payment after an ambiguous response has passed that retention window.
        if (time() - (int) strtotime($row['payment_started_at'] . ' UTC') > 45 * MINUTE_IN_SECONDS) {
            throw new RuntimeException('Unresolved payment requires staff reconciliation.');
        }
        $body = json_decode((string) $row['payment_payload'], true);
        if (!is_array($body)) { throw new RuntimeException('Payment creation payload is missing.'); }
        $payment = iss_commerce_mollie_api('POST', 'payments', $body, 'iss-' . iss_commerce_payment_token($row));
        // Persist the binding, then read fresh state: an idempotent POST may return
        // an old open snapshot after a payment has already completed.
        $row = iss_commerce_mollie_accept($row, $payment);
        return iss_commerce_mollie_accept($row, iss_commerce_mollie_api('GET', 'payments/' . rawurlencode($row['provider_payment_id'])));
    });
}

function iss_commerce_payment_receipt(array $row): array {
    return [
        'ok' => true, 'booking_id' => (int) $row['id'], 'payment_status' => $row['payment_status'], 'test_mode' => true,
        'amount_cents' => (int) $row['amount_cents'], 'title' => $row['item_label'],
        'checkout_url' => $row['payment_checkout_url'], 'source_url' => get_permalink((int) $row['source_post_id']) ?: home_url('/'),
        'return_url' => home_url('/?iss_payment_return=1') . '#iss-payment=' . $row['id'] . '.' . iss_commerce_payment_token($row),
    ];
}

function iss_commerce_request_response(int $id, string $method): WP_REST_Response {
    if ($method !== 'mollie') { return new WP_REST_Response(['ok' => true], 200); }
    try { $response = new WP_REST_Response(iss_commerce_payment_receipt(iss_commerce_mollie_payment($id, true)), 200); }
    catch (RuntimeException $error) {
        $response = new WP_REST_Response(['ok' => false, 'error' => 'Die Zahlungsanfrage konnte noch nicht bestätigt werden. Bitte mit unveränderten Angaben erneut versuchen. Bei längerem Ausfall kontaktieren Sie uns; bitte keine zweite Buchung anlegen.'], 503);
    }
    $response->header('Cache-Control', 'no-store');
    return $response;
}

function iss_commerce_mollie_webhook(WP_REST_Request $request): WP_REST_Response {
    $id = $request->get_param('id');
    if (strlen($request->get_body()) > 1024 || !is_string($id) || !preg_match('/^tr_[A-Za-z0-9]{5,60}$/D', $id)) {
        return new WP_REST_Response(['ok' => false], 400);
    }
    global $wpdb;
    $table = iss_payments_lite_requests_table_name();
    $booking_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE provider_payment_id = %s", $id));
    // A provider callback can precede saving the create response. Ask Mollie to
    // retry rather than acknowledging a payment that we have not bound yet.
    if (!$booking_id || $wpdb->last_error !== '') { return new WP_REST_Response(['ok' => false], 503); }
    try { iss_commerce_mollie_payment($booking_id, false); }
    catch (RuntimeException $error) { return new WP_REST_Response(['ok' => false], 503); }
    return new WP_REST_Response(['ok' => true], 200);
}

function iss_commerce_payment_status(WP_REST_Request $request): WP_REST_Response {
    try {
        if (strlen($request->get_body()) > 1024) { throw new RuntimeException('Invalid receipt.'); }
        $id = $request->get_param('booking_id'); $token = $request->get_param('token');
        if (!is_scalar($id) || !ctype_digit((string) $id) || !is_string($token) || !preg_match('/^[a-f0-9]{64}$/D', $token)) {
            throw new RuntimeException('Invalid receipt.');
        }
        $row = iss_commerce_payment_row((int) $id);
        if (!hash_equals(iss_commerce_payment_token($row), $token)) { throw new RuntimeException('Invalid receipt.'); }
    } catch (RuntimeException $error) { return new WP_REST_Response(['ok' => false, 'error' => 'Ungültiger Zahlungsbeleg.'], 404, ['Cache-Control' => 'no-store']); }
    try { $data = iss_commerce_payment_receipt(iss_commerce_mollie_payment((int) $id, false)); }
    catch (RuntimeException $error) { return new WP_REST_Response(['ok' => false, 'error' => 'Zahlungsstatus derzeit nicht prüfbar. Bitte später erneut prüfen.'], 503, ['Cache-Control' => 'no-store']); }
    return new WP_REST_Response($data, 200, ['Cache-Control' => 'no-store']);
}

add_action('rest_api_init', static function (): void {
    // Public intentionally: Mollie authenticates through a server-side payment
    // read; the minimal receipt uses an unguessable bearer, not a visitor nonce.
    register_rest_route('iss-payments/v1', '/mollie/webhook', ['methods' => 'POST', 'callback' => 'iss_commerce_mollie_webhook', 'permission_callback' => '__return_true']);
    register_rest_route('iss-payments/v1', '/payment-status', ['methods' => 'POST', 'callback' => 'iss_commerce_payment_status', 'permission_callback' => '__return_true']);
});
