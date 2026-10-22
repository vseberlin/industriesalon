<?php
/** Isolated digest/lease tests; transport is intercepted before any mail leaves PHP. */
if (!defined('WP_CLI') || !WP_CLI || !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) { exit(1); }
global $wpdb;
$config = json_decode(file_get_contents($args[0]), true);
if (!preg_match('/^' . preg_quote($wpdb->prefix, '/') . 'ledger_test_[a-f0-9]{8}_$/D', $config['prefix'] ?? '')) { WP_CLI::error('Invalid fixture.'); }
$wpdb->prefix = $config['prefix']; $wpdb->options = $config['prefix'] . 'options'; wp_cache_flush();
define('ISS_COMMERCE_STAFF_MAIL', true); define('ISS_COMMERCE_MAIL_FROM', 'fixture@example.invalid');
$checks = 0; $messages = []; $success = true;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
add_filter('pre_wp_mail', static function ($result, array $mail) use (&$messages, &$success, $expect): bool {
    $expect($mail['to'] === 'info@industriesalon.de', 'Only approved staff recipient'); $messages[] = $mail; return $success;
}, 10, 2);
add_filter('pre_http_request', static function () { throw new RuntimeException('Unexpected HTTP.'); });
$dep = ISS_Commerce_Ledger::insert(ISS_Commerce_Ledger::table('departures'), ['departure_key' => hash('sha256', 'digest-fixture'), 'source_post_id' => 99999998, 'source_post_type' => 'fuehrung', 'slot_id' => 'digest-fixture', 'starts_at' => gmdate('Y-m-d H:i:s', time() + 3600), 'title' => 'Isolated digest tour', 'updated_at' => gmdate('Y-m-d H:i:s')]);
ISS_Commerce_Ledger::configure($dep, 30, true, false, 'Isolated real-mode digest fixture', true);
ISS_Commerce_Worker::enqueue_digests(); ISS_Commerce_Worker::enqueue_digests();
$rows = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE departure_id = %d', [ISS_Commerce_Ledger::table('outbox'), $dep]);
$expect(count($rows) === 1, 'Repeated scheduler creates one message identity');
$mail = $rows[0];
$expect((int) strtotime(ISS_Commerce_Ledger::departure($dep)['starts_at'] . ' UTC') - (int) strtotime($mail['due_at'] . ' UTC') === 86400, 'Digest due exactly 24 hours before actual instant');
ISS_Commerce_Worker::dispatch((int) $mail['id']); ISS_Commerce_Worker::dispatch((int) $mail['id']);
$expect(count($messages) === 1 && str_contains($messages[0]['message'], 'Bestätigte Buchungen: 0'), 'Overdue zero-booking digest sent once');
$expect(ISS_Commerce_Ledger::rows('SELECT state FROM %i WHERE id = %d', [ISS_Commerce_Ledger::table('outbox'), $mail['id']])[0]['state'] === 'sent', 'Transport acceptance recorded');
ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('departures'), ['schedule_revision' => 2], $dep);
ISS_Commerce_Worker::enqueue_digests();
$correction = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE departure_id = %d AND schedule_revision = 2', [ISS_Commerce_Ledger::table('outbox'), $dep])[0];
$expect($correction['message_type'] === 'correction', 'Reschedule after delivery creates distinguishable correction');
$success = false;
ISS_Commerce_Worker::dispatch((int) $correction['id']);
$retry = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE id = %d', [ISS_Commerce_Ledger::table('outbox'), $correction['id']])[0];
$expect($retry['state'] === 'retry' && $retry['sent_at'] === null && (int) $retry['attempts'] === 1, 'Transport failure records retry with no false delivery timestamp');
ISS_Commerce_Worker::dispatch((int) $correction['id']);
$expect(count($messages) === 2, 'Retry backoff prevents immediate repeated send');
$success = true;
ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('outbox'), ['next_attempt_at' => gmdate('Y-m-d H:i:s', time() - 1)], (int) $correction['id']);
ISS_Commerce_Worker::dispatch((int) $correction['id']);
$expect(count($messages) === 3 && str_starts_with($messages[2]['subject'], 'Korrektur:'), 'Correction retry succeeds');
ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('departures'), ['schedule_revision' => 3], $dep);
ISS_Commerce_Worker::enqueue_digests();
$next = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE departure_id = %d AND schedule_revision = 3', [ISS_Commerce_Ledger::table('outbox'), $dep])[0];
ISS_Commerce_Ledger::configure($dep, 30, false, false, 'Fixture cancellation', true);
ISS_Commerce_Worker::dispatch((int) $next['id']);
$expect(ISS_Commerce_Ledger::rows('SELECT state FROM %i WHERE id = %d', [ISS_Commerce_Ledger::table('outbox'), $next['id']])[0]['state'] === 'superseded' && count($messages) === 3, 'Canceled departure suppresses unsent summary');
ISS_Commerce_Ledger::configure($dep, 30, true, false, 'Fixture reopen', true);
ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('departures'), ['schedule_revision' => 4, 'starts_at' => gmdate('Y-m-d H:i:s', time() - 60)], $dep);
ISS_Commerce_Worker::enqueue_digests();
$expect(ISS_Commerce_Ledger::rows('SELECT state FROM %i WHERE departure_id = %d AND schedule_revision = 4', [ISS_Commerce_Ledger::table('outbox'), $dep])[0]['state'] === 'missed', 'Missed post-event notices flagged instead of mailed');
ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('outbox'), ['state' => 'sending', 'lease_token' => wp_generate_uuid4(), 'lease_until' => gmdate('Y-m-d H:i:s', time() - 1)], (int) $next['id']);
$expect(ISS_Commerce_Worker::recover_mail_leases() === 1, 'Abandoned mail lease recovered');
$expect(ISS_Commerce_Ledger::rows('SELECT state FROM %i WHERE id = %d', [ISS_Commerce_Ledger::table('outbox'), $next['id']])[0]['state'] === 'uncertain', 'Crash after possible acceptance requires mailbox review');
ISS_Commerce_Worker::dispatch((int) $next['id']);
$expect(count($messages) === 3, 'Uncertain mail is not blindly resent');
WP_CLI::success($checks . ' worker assertions; all mail intercepted.');
