<?php
/** Additive owner migration; private before-image, no content/account/uploads transfer. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$mode = $args[0] ?? 'check'; $path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true) || ISS_PAYMENTS_LITE_SCHEMA_VERSION !== '2026-10-22-requests-v5-ledger') { WP_CLI::error('Deploy matching ledger code; use check|apply|verify.'); }
require_once WP_PLUGIN_DIR . '/iss-core/schema.php';
$error = '';
try { iss_core_verify_schema(iss_payments_lite_schema_statements()); } catch (RuntimeException $caught) { $error = $caught->getMessage(); }
$pending = $error !== '' || get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION) !== ISS_PAYMENTS_LITE_SCHEMA_VERSION;
if ($mode === 'check') { WP_CLI::log((string) wp_json_encode(['pending' => $pending, 'structure_error' => $error])); return; }
if ($mode === 'verify') { if ($pending) { WP_CLI::error('Ledger schema remains incomplete.'); } WP_CLI::success('Ledger schema verified.'); return; }
if (!$pending) { WP_CLI::success('Already applied.'); return; }
if (!$path || file_exists($path)) { WP_CLI::error('A new private before-image path is required.'); }
$before = ['site' => home_url(), 'schema_version' => get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION), 'tables' => []];
$tables = array_merge([iss_payments_lite_requests_table_name()], array_map([ISS_Commerce_Ledger::class, 'table'], ['departures', 'vouchers', 'redemptions', 'events', 'outbox']));
foreach ($tables as $table) {
    if (!ISS_Commerce_Ledger::rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', [$table])) { continue; }
    $before['tables'][$table] = ['schema' => ISS_Commerce_Ledger::rows('SHOW CREATE TABLE %i', [$table]), 'rows' => ISS_Commerce_Ledger::rows('SELECT * FROM %i ORDER BY id', [$table])];
}
$json = wp_json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) { WP_CLI::error('Cannot encode private before-image.'); }
$file = fopen($path, 'x');
if (!$file || !chmod($path, 0600)) { WP_CLI::error('Cannot create private before-image.'); }
$written = fwrite($file, $json); fclose($file);
if ($written !== strlen($json) || file_get_contents($path) !== $json) { WP_CLI::error('Before-image verification failed.'); }
iss_payments_lite_install_schema(true);
foreach ($before['tables'] as $table => $snapshot) {
    foreach ($snapshot['rows'] as $row) {
        $current = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE id = %d', [$table, $row['id']])[0] ?? [];
        if (array_intersect_key($current, $row) !== $row) { WP_CLI::error('Historical row changed; inspect concurrent edits and preserve before-image.'); }
    }
}
WP_CLI::success('Ledger installed; historical rows retained. Before-image SHA-256: ' . hash_file('sha256', $path));
