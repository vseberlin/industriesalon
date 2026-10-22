<?php
/** Test the deployed migration against isolated historical tables and private receipts. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Disposable exact-owner fixtures and deliberate DDL failure.
global $wpdb;
$db = $wpdb; $checks = 0; $created = []; $paths = []; $error = null;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$root = getenv('ISS_TEST_FILE_ROOT');
$expect(is_string($root) && is_dir($root) && is_writable($root), 'Explicit private fixture root');
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'No cron or shared cache');
$real_prefix = $db->prefix; $real_options = $db->options; $real_table = iss_payments_lite_requests_table_name();
$real_state = static fn(): array => [$db->get_results("CHECKSUM TABLE `$real_table`", ARRAY_A), $db->get_results("SELECT * FROM `$real_options` ORDER BY option_id", ARRAY_A)];
$before_real = $real_state(); $prefix = $real_prefix . 'cm_' . bin2hex(random_bytes(4)) . '_'; $table = $prefix . 'iss_payments_lite_requests'; $options = $prefix . 'options';
$run = static function (array $args): void { include ABSPATH . 'ops/migrations/2026-10-22-commerce-ledger.php'; };
$old_suppression = $db->suppress_errors(true);
try {
    $expect($db->query("CREATE TABLE `$options` LIKE `$real_options`") !== false, 'Isolated options'); $created[] = $options; $created[] = $table;
    $db->prefix = $prefix; $db->options = $options; wp_cache_flush();
    foreach (array_slice(iss_payments_lite_schema_statements(), 1) as $statement) { preg_match('/CREATE TABLE (\w+)/', $statement, $m); $created[] = $m[1]; }
    $sql = iss_payments_lite_schema_statements()[0];
    $sql = preg_replace('/^\s*(?:request_key char|request_fingerprint char|provider_payment_id varchar|payment_status varchar|payment_checkout_url varchar|payment_payload longtext|payment_started_at datetime|UNIQUE KEY request_key|UNIQUE KEY provider_payment_id|booking_reference varchar|departure_id bigint|booking_state varchar|hold_expires_at datetime|confirmed_at datetime|cancelled_at datetime|voucher_cents int|provider_amount_cents int|currency char|test_mode tinyint|payment_checked_at datetime|UNIQUE KEY booking_reference|KEY departure_allocation|KEY reconcile_payments).*\n/m', '', $sql);
    $sql = preg_replace('/,\s*\)/', "\n    )", $sql);
    $expect($db->query($sql) !== false, 'Install historical v2 fixture');
    update_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, '2026-06-13-requests-v2', false);
    $expect($db->insert($table, ['request_kind' => 'inquiry', 'customer_name' => 'Historical fixture', 'customer_email' => 'fixture@example.invalid', 'payload' => '{}', 'created_at' => '2026-01-01 10:00:00', 'updated_at' => '2026-01-01 10:00:00']) === 1, 'Seed historical request');
    $before = $db->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A);
    $refused = false;
    try { iss_payments_lite_install_schema(); } catch (RuntimeException $caught) { $refused = str_contains($caught->getMessage(), 'scoped backup'); }
    $expect($refused, 'Ordinary upgrade refuses unbacked schema change');
    $paths[] = $root . '/' . $prefix . 'before-failure.json'; $hits = 0;
    $filter = static function (string $query) use ($table,&$hits): string {
        if (str_contains($query, "ALTER TABLE $table ADD UNIQUE KEY `request_key`")) { ++$hits; return 'SELECT iss_migration_injected_ddl_failure'; }
        return $query;
    };
    add_filter('query', $filter); $failed = false;
    try { $run(['apply', $paths[0]]); } catch (RuntimeException $caught) { $failed = true; } finally { remove_filter('query', $filter); }
    $expect($failed && $hits === 1, 'Failed unique index is detected');
    $expect(get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION) === '2026-06-13-requests-v2', 'Failed migration does not advance version');
    $receipt = json_decode((string) file_get_contents($paths[0]), true);
    $expect(is_array($receipt) && $receipt['tables'][$table]['rows'] === $before && str_contains(wp_json_encode($receipt['tables'][$table]['schema']), 'CREATE TABLE'), 'Before-image contains exact old data and schema');
    $expect((fileperms($paths[0]) & 0777) === 0600, 'Receipt is private');
    $paths[] = $root . '/' . $prefix . 'before-retry.json'; $run(['apply', $paths[1]]); $run(['verify']);
    $after = $db->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A);
    $expect(count($after) === 1 && array_intersect_key($after[0], $before[0]) === $before[0], 'Successful retry preserves historical values');
    $expect($after[0]['request_key'] === null && $after[0]['request_fingerprint'] === '', 'Historical requests retain nullable keys');
    $expect(get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION) === ISS_PAYMENTS_LITE_SCHEMA_VERSION, 'Version advances after structural verification');
    $run(['apply', $paths[1]]);
    $expect($db->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A) === $after, 'Repeat application is a no-op');
    iss_payments_lite_install_schema();
    $expect($db->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A) === $after, 'Normal owner installation is repeatable after migration');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $db->prefix = $real_prefix; $db->options = $real_options;
    foreach (array_reverse($created) as $name) { if ($db->query("DROP TABLE IF EXISTS `$name`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    foreach ($paths as $path) { if (file_exists($path) && !unlink($path)) { $error = new RuntimeException('Receipt fixture cleanup failed'); } }
    wp_cache_flush(); $db->suppress_errors($old_suppression);
}
$expect($real_state() === $before_real, 'Real requests and options unchanged');
if ($error) { WP_CLI::error("After $checks checks: " . $error->getMessage()); }
WP_CLI::success("Commerce migration: $checks checks passed; isolated fixtures removed.");
