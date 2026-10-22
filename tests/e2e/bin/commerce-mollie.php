<?php
/** Included by commerce-idempotency.php inside its disposable database/no-mail harness. */
if (!defined('WP_CLI') || !WP_CLI || !isset($reset, $call, $expect, $read)) { exit(1); }
if (!defined('ISS_MOLLIE_ENABLED')) { define('ISS_MOLLIE_ENABLED', true); }
if (!defined('ISS_MOLLIE_API_KEY')) { define('ISS_MOLLIE_API_KEY', 'test_fixture'); }
$stage = 'mollie'; $remote_payments = []; $remote_keys = []; $http_calls = []; $transport_failure = ''; $mismatch = [];
$http = static function ($pre, array $args, string $url) use (&$remote_payments, &$remote_keys, &$http_calls, &$transport_failure, &$mismatch) {
    if (!str_starts_with($url, 'https://api.mollie.com/v2/payments')) { return new WP_Error('test_network_blocked', 'No network in fixtures.'); }
    $http_calls[] = [$args['method'], $url];
    if ($args['method'] === 'POST') {
        $key = $args['headers']['Idempotency-Key'];
        if (!isset($remote_keys[$key])) {
            $id = 'tr_fixture' . (count($remote_payments) + 1);
            $remote_keys[$key] = $id;
            $remote_payments[$id] = array_merge(json_decode($args['body'], true), ['id' => $id, 'mode' => 'test', 'status' => 'open', '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/test/' . $id]]]);
        }
        $data = $remote_payments[$remote_keys[$key]];
    } else { $data = $remote_payments[basename($url)] ?? []; }
    if ($transport_failure !== '') { return new WP_Error('test_timeout', 'Ambiguous provider timeout.'); }
    $data = array_replace_recursive($data, $mismatch);
    return ['headers' => [], 'body' => wp_json_encode($data), 'response' => ['code' => $args['method'] === 'POST' ? 201 : 200, 'message' => 'OK']];
};
add_filter('pre_http_request', $http, 10, 3);
try {
    $reset(); $data = array_replace($payload('booking'), ['payment' => 'mollie', 'amount_cents' => 1]);
    $response = $call($data); $result = $response->get_data();
    $expect($response->get_status() === 200 && $result['amount_cents'] === 2000 && $result['payment_status'] === 'open', 'Mollie uses the persisted owner price, not client amount');
    $row = $read()[0]; $id = $row['provider_payment_id'];
    $expect(count($remote_payments) === 1 && $sent === 1 && count($events) === 1, 'One booking and one remote payment created');
    $expect(str_contains($remote_payments[$id]['webhookUrl'], '/iss-payments/v1/mollie/webhook') && str_contains($remote_payments[$id]['redirectUrl'], '#iss-payment='), 'Webhook and fragment-only receipt configured');
    $expect(!str_contains(wp_json_encode($remote_payments), $data['email']) && !str_contains(wp_json_encode($remote_payments), $data['name']), 'Provider receives no booking customer name or email');
    $expect($call($data)->get_status() === 200 && count($remote_payments) === 1 && $sent === 1 && count($events) === 1, 'Retry creates no duplicate payment or notification');
    $expect($call(array_replace($data, ['tickets' => 3]))->get_status() === 409, 'Changed intent cannot reuse a payment identity');
    $webhook = new WP_REST_Request('POST', '/iss-payments/v1/mollie/webhook'); $webhook->set_param('id', $id); $webhook->set_param('status', 'paid');
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 200 && $read()[0]['payment_status'] === 'open', 'Forged paid webhook field is ignored');
    foreach ([['amount' => ['value' => '0.01']], ['amount' => ['currency' => 'USD']], ['mode' => 'live'], ['metadata' => ['booking_id' => '999']], ['metadata' => ['reference' => 'wrong']], ['id' => 'tr_wrongbinding']] as $wrong) {
        $mismatch = $wrong;
        $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 503 && $read()[0]['payment_status'] === 'open', 'Wrong amount, currency, mode or binding cannot settle a booking');
    }
    $mismatch = []; $remote_payments[$id]['status'] = 'paid';
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 200 && $read()[0]['payment_status'] === 'paid', 'Authenticated provider paid state is saved');
    $after = $read();
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 200 && $read() === $after && $sent === 1 && count($events) === 1, 'Duplicate webhook is a no-op with no repeat dispatch');
    $expect($read()[0]['status'] === 'new', 'Payment never implies a SuperSaaS reservation or staff confirmation');
    $receipt_request = new WP_REST_Request('POST', '/iss-payments/v1/payment-status');
    $receipt_request->set_param('booking_id', $row['id']); $receipt_request->set_param('token', 'wrong');
    $before_calls = count($http_calls);
    $expect(iss_commerce_payment_status($receipt_request)->get_status() === 404 && count($http_calls) === $before_calls, 'Invalid bearer cannot read or trigger provider lookups');
    $receipt_request->set_param('token', iss_commerce_payment_token($row));
    $receipt_response = iss_commerce_payment_status($receipt_request);
    $expect($receipt_response->get_status() === 200 && $receipt_response->get_data()['payment_status'] === 'paid' && $receipt_response->get_headers()['Cache-Control'] === 'no-store', 'Receipt is verified and uncached');
    $expect(!str_contains(wp_json_encode($receipt_response->get_data()), $data['email']) && !str_contains(wp_json_encode($receipt_response->get_data()), $data['name']), 'Public receipt excludes customer details');
    $transport_failure = 'read';
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 503 && $read() === $after, 'Provider outage requests redelivery and preserves known state');
    $expect(iss_commerce_payment_status($receipt_request)->get_status() === 503, 'Return page never substitutes stale success when verification fails');
    $transport_failure = '';
    foreach (['failed', 'canceled', 'expired', 'pending', 'authorized'] as $status) {
        $remote_payments[$id]['status'] = $status;
        $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 200 && $read()[0]['payment_status'] === $status, 'Provider terminal/pending states are represented without success');
    }
    $remote_payments[$id]['status'] = 'paid'; $remote_payments[$id]['amountRefunded'] = ['value' => '10.00'];
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 200 && $read()[0]['payment_status'] === 'partially_refunded', 'Partial refund remains visible');
    $remote_payments[$id]['amountRefunded']['value'] = '20.00';
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 200 && $read()[0]['payment_status'] === 'refunded', 'Full refund is not presented as current paid status');
    $remote_payments[$id]['amountChargedBack'] = ['value' => '20.00'];
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 200 && $read()[0]['payment_status'] === 'charged_back', 'Chargeback is visible');
    $webhook->set_param('id', ['bad']);
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 400, 'Malformed webhook rejected');
    $webhook->set_param('id', 'tr_unknown123');
    $expect(iss_commerce_mollie_webhook($webhook)->get_status() === 503, 'Callback before binding requests retry');
    $reset(); $data['request_key'] = 'fixture-timeout-0001'; $transport_failure = 'create';
    $expect($call($data)->get_status() === 503 && count($read()) === 1 && $read()[0]['payment_status'] === 'creating', 'Ambiguous create retains a recoverable booking');
    $payment_count = count($remote_payments); $transport_failure = '';
    $expect($call($data)->get_status() === 200 && count($remote_payments) === $payment_count && $sent === 1, 'Ambiguous create recovers the same payment with frozen payload and key');
    $reset(); $data['request_key'] = 'fixture-bind-write-0001'; $binding_failures = 0;
    $binding_filter = static function (string $sql) use ($table, &$binding_failures): string {
        if (!$binding_failures && str_starts_with($sql, "UPDATE `$table`") && str_contains($sql, '`provider_payment_id`')) {
            ++$binding_failures; return 'SELECT iss_fixture_binding_write_failure';
        }
        return $sql;
    };
    $expect($with_filter($binding_filter, static fn() => $call($data))->get_status() === 503 && $binding_failures === 1, 'Failed binding write cannot report success');
    $payment_count = count($remote_payments);
    $expect($call($data)->get_status() === 200 && count($remote_payments) === $payment_count && $sent === 1, 'Binding write failure recovers without a second payment');
    $reset(); $data['request_key'] = 'fixture-expired-0001'; $transport_failure = 'create';
    $expect($call($data)->get_status() === 503, 'Seed unresolved provider response'); $transport_failure = '';
    $db->update($table, ['payment_started_at' => gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS)], ['id' => $read()[0]['id']]);
    $before_calls = count($http_calls);
    $expect($call($data)->get_status() === 503 && count($http_calls) === $before_calls, 'Expired idempotency window cannot create another payment');
    $reset(); $data['request_key'] = 'fixture-payment-lock-0001'; $expect($call($data)->get_status() === 200, 'Seed concurrency fixture');
    $id = (int) $read()[0]['id']; $blocked = false;
    iss_commerce_payment_lock($id, static function (array $locked) use ($as_peer, $id, &$blocked): array {
        try { $as_peer(static fn() => iss_commerce_mollie_payment($id, false)); } catch (RuntimeException $error) { $blocked = true; }
        return $locked;
    });
    $expect($blocked, 'Two connections cannot reorder provider reads/writes for one booking');
    $expect($call(array_replace($data, ['intent' => 'order']))->get_status() === 400, 'Mollie cannot silently enable publication checkout');
    $no_key = $data; unset($no_key['request_key']);
    $expect($call($no_key)->get_status() === 400, 'Online booking requires a durable client identity');
    $reset();
} finally { remove_filter('pre_http_request', $http); }
