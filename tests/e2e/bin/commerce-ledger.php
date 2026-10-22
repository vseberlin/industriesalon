<?php
/** Real MariaDB fixtures. Run setup, ten worker processes, verify, cleanup. No mail or provider HTTP. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Disposable fixture schemas and deliberate failure injection.
global $wpdb;
$mode = $args[0] ?? ''; $path = $args[1] ?? '';
if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON || wp_using_ext_object_cache() || !$path) { WP_CLI::error('Cron-off, process-local cache and private fixture receipt required.'); }
add_filter('pre_wp_mail', static function () { throw new RuntimeException('Unexpected real mail.'); });
add_filter('pre_http_request', static function () { throw new RuntimeException('Unexpected real HTTP.'); });
if (!defined('ISS_COMMERCE_VOUCHER_KEY')) { define('ISS_COMMERCE_VOUCHER_KEY', str_repeat('fixture-key-', 4)); }
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$real_prefix = $wpdb->prefix; $real_options = $wpdb->options;
$protected = [iss_payments_lite_requests_table_name(), $wpdb->posts, $wpdb->postmeta];
$snapshot = static fn(): array => array_map(static fn(string $table): array => ISS_Commerce_Ledger::rows('CHECKSUM TABLE %i', [$table]), $protected);
if ($mode === 'setup') {
    if (file_exists($path)) { WP_CLI::error('Use a new fixture receipt path.'); }
    $config = ['prefix' => $real_prefix . 'ledger_test_' . bin2hex(random_bytes(4)) . '_', 'before' => $snapshot(), 'tables' => []];
} else { $config = json_decode(file_get_contents($path), true); }
if (!is_array($config) || !preg_match('/^' . preg_quote($real_prefix, '/') . 'ledger_test_[a-f0-9]{8}_$/D', $config['prefix'] ?? '')) { WP_CLI::error('Invalid fixture ownership.'); }
$wpdb->prefix = $config['prefix']; $wpdb->options = $config['prefix'] . 'options'; wp_cache_flush();
$table = iss_payments_lite_requests_table_name();
$entry = static fn(int $i = 1, int $seats = 1): array => ['departure_id' => 1, 'source_post_id' => 99999999, 'source_post_type' => 'fuehrung', 'slot_id' => 'fixture', 'start' => gmdate('c', time() + 3 * DAY_IN_SECONDS),
    'tickets' => $seats, 'regular_tickets' => $seats, 'reduced_tickets' => 0, 'amount_cents' => $seats * 1200, 'payment' => 'mollie', 'title' => 'Isolated tour', 'name' => 'Fixture', 'email' => 'fixture' . $i . '@example.invalid', 'ip' => '192.0.2.' . $i];
$identity = static fn(string $key): array => ['key' => hash('sha256', $key), 'fingerprint' => hash('sha256', 'intent:' . $key), 'kind' => 'booking'];
if ($mode === 'setup') {
    ISS_Commerce_Ledger::execute('CREATE TABLE %i LIKE %i', [$wpdb->options, $real_options]);
    $config['tables'][] = $wpdb->options;
    foreach (iss_payments_lite_schema_statements() as $sql) { preg_match('/CREATE TABLE (\w+)/', $sql, $m); $config['tables'][] = $m[1]; }
    file_put_contents($path, wp_json_encode($config)); chmod($path, 0600);
    iss_payments_lite_install_schema();
    update_option('timezone_string', 'Europe/Berlin'); update_option('blog_charset', 'UTF-8');
    ISS_Commerce_Ledger::insert(ISS_Commerce_Ledger::table('departures'), ['departure_key' => hash('sha256', 'fixture'), 'source_post_id' => 99999999, 'source_post_type' => 'fuehrung', 'slot_id' => 'fixture', 'starts_at' => gmdate('Y-m-d H:i:s', time() + 3 * DAY_IN_SECONDS), 'title' => 'Isolated tour', 'updated_at' => gmdate('Y-m-d H:i:s')]);
    ISS_Commerce_Ledger::configure(1, 3, true, true, 'Isolated concurrency fixture', true);
    $config['start'] = ISS_Commerce_Ledger::departure(1)['starts_at'];
    file_put_contents($path, wp_json_encode($config));
    WP_CLI::success('Fixture created.'); return;
}
if ($mode === 'voucher-seed') {
    ISS_Commerce_Vouchers::import([['legacy_site' => 'fixture', 'legacy_id' => 'race', 'code' => 'CONCURRENT-VOUCHER', 'source_sha256' => str_repeat('b', 64), 'amount_cents' => 1200, 'balance_cents' => 1200, 'issued_at' => '2026-01-01T00:00:00+01:00', 'expires_at' => '2029-12-31T23:59:59+01:00', 'enabled' => true, 'reconciled' => true, 'terms_verified' => true, 'single_authority' => true]], true, true);
    WP_CLI::success('Voucher race seeded.'); return;
}
if ($mode === 'voucher-worker') {
    $i = (int) ($args[2] ?? 0); $data = $entry($i + 30); $data['start'] = $config['start'] . ' UTC';
    try { $result = ISS_Commerce_Ledger::admit($data, $identity('voucher-parallel-' . $i), 'CONCURRENT-VOUCHER'); WP_CLI::log('voucher-admitted:' . $i . ':' . $result['id']); }
    catch (DomainException $error) { WP_CLI::log('voucher-rejected:' . $i); }
    return;
}
if ($mode === 'voucher-verify') {
    $v = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE code_hash = %s', [ISS_Commerce_Ledger::table('vouchers'), ISS_Commerce_Vouchers::fingerprint('CONCURRENT-VOUCHER')])[0];
    $r = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE voucher_id = %d', [ISS_Commerce_Ledger::table('redemptions'), $v['id']]);
    $expect((int) $v['balance_cents'] === 0 && count($r) === 1 && $r[0]['state'] === 'committed', 'Concurrent voucher use allocates value exactly once');
    WP_CLI::success('Concurrent voucher conservation verified.'); return;
}
if ($mode === 'worker') {
    $i = (int) ($args[2] ?? 0); $data = $entry($i); $data['start'] = $config['start'] . ' UTC';
    try { $result = ISS_Commerce_Ledger::admit($data, $identity('parallel-' . $i)); WP_CLI::log('admitted:' . $i . ':' . $result['id']); }
    catch (DomainException $error) { WP_CLI::log('rejected:' . $i); }
    return;
}
if ($mode === 'cleanup') {
    foreach (array_reverse($config['tables']) as $name) { if (!str_starts_with($name, $config['prefix'])) { throw new RuntimeException('Foreign cleanup table.'); } ISS_Commerce_Ledger::execute('DROP TABLE IF EXISTS %i', [$name]); }
    $wpdb->prefix = $real_prefix; $wpdb->options = $real_options; wp_cache_flush();
    $expect($snapshot() === $config['before'], 'Real requests, posts and metadata unchanged');
    WP_CLI::success('Fixtures removed; protected data unchanged.'); return;
}
if ($mode !== 'verify') { WP_CLI::error('Unknown mode.'); }
$expect(count(ISS_Commerce_Ledger::rows('SELECT * FROM %i', [$table])) === 3 && ISS_Commerce_Ledger::allocated(1) === 3, 'Ten simultaneous customers admitted exactly three');
$expect(count(ISS_Commerce_Ledger::rows("SELECT * FROM %i WHERE event_type = 'booking_admitted'", [ISS_Commerce_Ledger::table('events')])) === 3, 'Exactly one admission event per accepted booking');
$row = ISS_Commerce_Ledger::rows('SELECT * FROM %i ORDER BY id LIMIT 1', [$table])[0];
$id = (int) $row['id']; $data = json_decode($row['payload'], true);
$replay = ISS_Commerce_Ledger::admit($data, ['key' => $row['request_key'], 'fingerprint' => $row['request_fingerprint'], 'kind' => 'booking']);
$expect(!$replay['created'] && ISS_Commerce_Ledger::booking($id) === $row, 'Retry does not extend hold or change row');
try { ISS_Commerce_Ledger::configure(1, 2, true, true, 'Invalid reduction', true); $expect(false, 'Capacity reduction must fail'); } catch (DomainException $error) { ++$checks; }
ISS_Commerce_Ledger::update($table, ['hold_expires_at' => gmdate('Y-m-d H:i:s', time() - 1)], $id);
$expect(ISS_Commerce_Ledger::allocated(1) === 2, 'Expiry releases seats without cron');
$many = $entry(20, 2); $many['start'] = $config['start'] . ' UTC';
try { ISS_Commerce_Ledger::admit($many, $identity('too-many-seats')); $expect(false, 'Two seats cannot fit one remaining place'); } catch (DomainException $error) { ++$checks; }
$replacement = $entry(11); $replacement['start'] = $config['start'] . ' UTC';
$new = ISS_Commerce_Ledger::admit($replacement, $identity('replacement'));
$paid = ISS_Commerce_Ledger::apply_payment(ISS_Commerce_Ledger::booking($id), ['provider_payment_id' => 'tr_fixture111', 'payment_status' => 'paid', 'payment_checkout_url' => '']);
$expect($paid['booking_state'] === 'payment_exception' && ISS_Commerce_Ledger::allocated(1) === 3, 'Late payment after reassignment does not take another seat');
$before_events = ISS_Commerce_Ledger::rows('SELECT * FROM %i', [ISS_Commerce_Ledger::table('events')]);
ISS_Commerce_Ledger::apply_payment($paid, ['provider_payment_id' => 'tr_fixture111', 'payment_status' => 'paid', 'payment_checkout_url' => '']);
$expect($before_events === ISS_Commerce_Ledger::rows('SELECT * FROM %i', [ISS_Commerce_Ledger::table('events')]), 'Duplicate callback has no repeated effects');
$stale = ISS_Commerce_Ledger::apply_payment($paid, ['provider_payment_id' => 'tr_fixture111', 'payment_status' => 'open', 'payment_checkout_url' => '']);
$expect($stale['payment_status'] === 'paid', 'Out-of-order open cannot undo paid');
ISS_Commerce_Ledger::staff_action((int) $new['id'], 'cancelled', 'Fixture cancellation');
$expect(ISS_Commerce_Ledger::allocated(1) === 2, 'Staff cancellation frees seats');
ISS_Commerce_Ledger::staff_action($id, 'confirmed', 'Resolved late payment with available seat');
$expect(ISS_Commerce_Ledger::allocated(1) === 3, 'Explicit staff resolution uses admission lock');
ISS_Commerce_Ledger::configure(1, 30, true, true, 'Capacity test', true);
$onsite = $entry(12, 2); $onsite['payment'] = 'onsite'; $onsite['start'] = $config['start'] . ' UTC';
$accepted = ISS_Commerce_Ledger::admit($onsite, $identity('onsite'));
$expect(ISS_Commerce_Ledger::booking($accepted['id'])['booking_state'] === 'confirmed' && ISS_Commerce_Ledger::booking($accepted['id'])['hold_expires_at'] === null, 'Pay-on-arrival is confirmed, never an expiring hold');
$manifest = [['legacy_site' => 'fixture', 'legacy_id' => '1', 'code' => 'TEST-VOUCHER', 'source_sha256' => str_repeat('a', 64), 'amount_cents' => 3000, 'balance_cents' => 3000, 'issued_at' => '2026-01-01T00:00:00+01:00', 'expires_at' => '2029-12-31T23:59:59+01:00', 'enabled' => true, 'reconciled' => true, 'terms_verified' => true, 'single_authority' => true]];
$expect(ISS_Commerce_Vouchers::import($manifest, false, true)['new'] === 1 && !ISS_Commerce_Ledger::rows('SELECT * FROM %i', [ISS_Commerce_Ledger::table('vouchers')]), 'Voucher dry-run writes nothing');
ISS_Commerce_Vouchers::import($manifest, true, true);
$full = $entry(13); $full['start'] = $config['start'] . ' UTC';
$covered = ISS_Commerce_Ledger::admit($full, $identity('covered'), 'test-voucher');
$cover = ISS_Commerce_Ledger::booking($covered['id']);
$expect($cover['booking_state'] === 'confirmed' && (int) $cover['provider_amount_cents'] === 0 && (int) $cover['voucher_cents'] === 1200, 'Full voucher confirms without provider');
$partial = $entry(14, 2); $partial['start'] = $config['start'] . ' UTC';
$partial_id = ISS_Commerce_Ledger::admit($partial, $identity('partial'), 'TEST-VOUCHER')['id'];
$part = ISS_Commerce_Ledger::booking($partial_id);
$expect($part['booking_state'] === 'pending' && (int) $part['provider_amount_cents'] === 600, 'Partial voucher freezes only remainder for Mollie');
try { ISS_Commerce_Ledger::admit($full, $identity('double-spend'), 'TEST-VOUCHER'); $expect(false, 'Reserved voucher cannot spend twice'); } catch (DomainException $error) { ++$checks; }
ISS_Commerce_Ledger::apply_payment($part, ['provider_payment_id' => 'tr_partial111', 'payment_status' => 'failed', 'payment_checkout_url' => '']);
$expect(ISS_Commerce_Ledger::rows('SELECT state FROM %i WHERE booking_id = %d', [ISS_Commerce_Ledger::table('redemptions'), $partial_id])[0]['state'] === 'released', 'Provider failure releases voucher reservation');
$repeat = ISS_Commerce_Vouchers::import($manifest, true, true);
$expect($repeat['existing'] === 1 && (int) ISS_Commerce_Ledger::rows('SELECT balance_cents FROM %i', [ISS_Commerce_Ledger::table('vouchers')])[0]['balance_cents'] === 1800, 'Import replay never restores spent balance');
ISS_Commerce_Ledger::staff_action((int) $covered['id'], 'cancelled', 'Fixture cancellation');
ISS_Commerce_Ledger::staff_action((int) $covered['id'], 'reverse_voucher', 'Explicit reversal');
ISS_Commerce_Ledger::staff_action((int) $covered['id'], 'reverse_voucher', 'Duplicate reversal');
$expect((int) ISS_Commerce_Ledger::rows('SELECT balance_cents FROM %i', [ISS_Commerce_Ledger::table('vouchers')])[0]['balance_cents'] === 3000, 'Voucher reversal is explicit and idempotent');
$failure = static fn(string $sql): string => str_starts_with($sql, 'INSERT INTO `' . ISS_Commerce_Ledger::table('events') . '`') ? 'INSERT INTO missing_commerce_fixture VALUES (1)' : $sql;
$before = ISS_Commerce_Ledger::rows('SELECT * FROM %i', [$table]);
$wpdb->suppress_errors(true); add_filter('query', $failure);
try { ISS_Commerce_Ledger::admit($full, $identity('audit-failure'), 'TEST-VOUCHER'); $expect(false, 'Audit failure must abort'); } catch (RuntimeException $error) { ++$checks; } finally { remove_filter('query', $failure); }
$expect(ISS_Commerce_Ledger::rows('SELECT * FROM %i', [$table]) === $before, 'Event write failure rolls back booking');
$expect((int) ISS_Commerce_Ledger::rows('SELECT balance_cents FROM %i', [ISS_Commerce_Ledger::table('vouchers')])[0]['balance_cents'] === 3000, 'Event failure rolls back voucher debit too');
$expect(ISS_Commerce_Ledger::utc('2026-10-25T10:00:00+01:00') === '2026-10-25 09:00:00', 'Berlin daylight-saving schedule persists correct UTC instant');
ISS_Commerce_Worker::enqueue_digests();
$expect(!ISS_Commerce_Ledger::rows('SELECT * FROM %i', [ISS_Commerce_Ledger::table('outbox')]), 'Synthetic departures produce no staff digest');
WP_CLI::success($checks . ' ledger assertions passed, including real concurrent admission.');
