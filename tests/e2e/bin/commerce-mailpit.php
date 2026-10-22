<?php
/** Explicit local SMTP capture, never an external mail service. Uses the isolated ledger receipt. */
if (!defined('WP_CLI') || !WP_CLI || !defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON || getenv('ISS_TEST_MAILPIT') !== 'mailpit') { exit(1); }
global $wpdb;
$config = json_decode(file_get_contents($args[0]), true);
if (!preg_match('/^' . preg_quote($wpdb->prefix, '/') . 'ledger_test_[a-f0-9]{8}_$/D', $config['prefix'] ?? '')) { WP_CLI::error('Invalid isolated ledger receipt.'); }
$wpdb->prefix = $config['prefix']; $wpdb->options = $config['prefix'] . 'options'; wp_cache_flush(); update_option('blog_charset', 'UTF-8');
define('ISS_COMMERCE_STAFF_MAIL', true); define('ISS_COMMERCE_MAIL_FROM', 'fixture@example.invalid');
define('ISS_COMMERCE_SMTP_HOST', 'mailpit'); define('ISS_COMMERCE_SMTP_PORT', 1025); define('ISS_COMMERCE_SMTP_SECURITY', '');
add_action('phpmailer_init', static function ($mailer): void {
    if ($mailer->Host !== 'mailpit' || $mailer->Port !== 1025 || $mailer->Mailer !== 'smtp') { throw new RuntimeException('Only local Mailpit transport allowed.'); }
}, 101);
add_filter('pre_wp_mail', static function ($value, array $mail) {
    if ($mail['to'] !== 'info@industriesalon.de') { throw new RuntimeException('Unexpected recipient.'); }
    return $value;
}, 10, 2);
$id = ISS_Commerce_Ledger::insert(ISS_Commerce_Ledger::table('departures'), ['departure_key' => hash('sha256', wp_generate_uuid4()), 'source_post_id' => 99999997, 'source_post_type' => 'fuehrung', 'slot_id' => 'mailpit-fixture', 'starts_at' => gmdate('Y-m-d H:i:s', time() + 7200), 'title' => 'ISS Upgrade Mailpit 20261022', 'updated_at' => gmdate('Y-m-d H:i:s')]);
ISS_Commerce_Ledger::configure($id, 30, true, false, 'Isolated Mailpit acceptance', true);
ISS_Commerce_Worker::enqueue_digests();
$mail = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE departure_id = %d', [ISS_Commerce_Ledger::table('outbox'), $id])[0];
ISS_Commerce_Worker::dispatch((int) $mail['id']);
$after = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE id = %d', [ISS_Commerce_Ledger::table('outbox'), $mail['id']])[0];
if ($after['state'] !== 'sent') { WP_CLI::error('Mailpit SMTP acceptance failed.'); }
WP_CLI::success('Digest accepted by local Mailpit. Verify captured message through its API.');
