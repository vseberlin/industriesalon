<?php
/**
 * wp eval-file FILE check|apply|verify /PRIVATE/new-before-image.json --use-include
 * Deploy matching commerce code first; pending public intake returns 503 until apply.
 * Apply verifies a private, target-local before-image before the owner adds nullable
 * request keys, fingerprints and their unique index. Historical rows remain intact.
 * Rollback: restore previous code, retain additive columns/index and all request rows.
 * Restore only the schema option through the Options API if needed for that code.
 * Never overwrite later requests from this snapshot. No uploads artifact is needed.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Read-only, exact-owner before-image and post-migration verification.
$mode = $args[0] ?? 'check'; $path = $args[1] ?? '';
if (!in_array($mode, ['check','apply','verify'], true)) { WP_CLI::error('Unknown mode.'); }
if (!function_exists('iss_payments_lite_schema_statements') || ISS_PAYMENTS_LITE_SCHEMA_VERSION !== '2026-10-11-requests-v3-idempotency') { WP_CLI::error('Deploy the matching commerce code first.'); }
require_once WP_PLUGIN_DIR . '/iss-core/schema.php';
global $wpdb;
$table = iss_payments_lite_requests_table_name(); $option = ISS_PAYMENTS_LITE_SCHEMA_OPTION;
$structural_error = '';
try { iss_core_verify_schema(iss_payments_lite_schema_statements()); } catch (RuntimeException $error) { $structural_error = $error->getMessage(); }
$pending = $structural_error !== '' || (string) get_option($option, '') !== ISS_PAYMENTS_LITE_SCHEMA_VERSION;
if ($mode === 'check') { WP_CLI::log((string) wp_json_encode(['pending' => $pending, 'stored_version' => get_option($option, ''), 'target_version' => ISS_PAYMENTS_LITE_SCHEMA_VERSION, 'structure_error' => $structural_error])); return; }
if ($mode === 'verify') {
    if ($pending) { WP_CLI::error('Commerce migration remains incomplete: ' . $structural_error); }
    WP_CLI::success('Commerce request key, unique index and schema version verified.'); return;
}
if (!$pending) { WP_CLI::success('Already applied; no changes.'); return; }
if ($path === '' || file_exists($path)) { WP_CLI::error('A new private before-image path is required.'); }
$schema = $wpdb->get_row($wpdb->prepare('SHOW CREATE TABLE %i', $table), ARRAY_N);
if ($wpdb->last_error !== '' || !is_array($schema)) { WP_CLI::error('Cannot read the existing request table schema. Use plugin activation for a fresh installation.'); }
$rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id', $table), ARRAY_A);
if ($wpdb->last_error !== '' || !is_array($rows)) { WP_CLI::error('Cannot read the request before-image.'); }
$option_rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name = %s", $option), ARRAY_A);
if ($wpdb->last_error !== '' || !is_array($option_rows)) { WP_CLI::error('Cannot read the schema option before-image.'); }
$before = ['migration' => '2026-10-11-commerce-idempotency', 'site' => home_url(), 'table' => $table, 'schema_sql' => $schema[1], 'rows' => $rows, 'option_rows' => $option_rows];
$json = wp_json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (!is_string($json)) { WP_CLI::error('Cannot encode the before-image.'); }
$file = fopen($path, 'x');
if (!$file || !chmod($path, 0600)) { WP_CLI::error('Cannot create a private before-image.'); }
$written = fwrite($file, $json); fclose($file);
if ($written !== strlen($json) || file_get_contents($path) !== $json) { WP_CLI::error('Before-image verification failed; no schema change made.'); }
iss_payments_lite_install_schema(true);
iss_core_verify_schema(iss_payments_lite_schema_statements());
$after_rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i ORDER BY id', $table), ARRAY_A);
if ($wpdb->last_error !== '' || !is_array($after_rows)) { WP_CLI::error('Cannot verify request retention; preserve the before-image and inspect.'); }
$after = array_column($after_rows, null, 'id');
foreach ($rows as $row) {
    $stored = $after[$row['id']] ?? null;
    if (!$stored || array_intersect_key($stored, $row) !== $row) { WP_CLI::error('An existing request changed; preserve the before-image and inspect concurrent edits before proceeding.'); }
}
WP_CLI::success(sprintf('Commerce upgrade verified; retained %d existing requests. Before-image SHA-256: %s', count($rows), hash_file('sha256', $path)));
