<?php
/**
 * wp eval-file FILE check|apply|verify /PRIVATE/new-before-image.json --use-include
 * Pause the host runner before apply; traffic cron must already be disabled.
 * Removes only four confirmed inactive-owner hooks, never plugin data/orders.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$mode = $args[0] ?? 'check'; $path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true)) { WP_CLI::error('Unknown mode.'); }
$owners = [
    'jetpack_clean_nonces' => ['jetpack/'],
    'jetpack_v2_heartbeat' => ['jetpack/'],
    'wc_admin_unsnooze_admin_notes' => ['woocommerce/'],
    'acf_update_site_health_data' => ['advanced-custom-fields/', 'advanced-custom-fields-pro/'],
];
$active = array_merge((array) get_option('active_plugins', []), array_keys((array) get_site_option('active_sitewide_plugins', [])));
$pending = [];
foreach ($owners as $hook => $prefixes) {
    foreach ($active as $plugin) {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($plugin, $prefix)) { WP_CLI::error('Owner still active for ' . $hook); }
        }
    }
    if (has_action($hook)) { WP_CLI::error('Callback still registered for ' . $hook); }
    foreach ((array) _get_cron_array() as $events) {
        if (isset($events[$hook])) { $pending[] = $hook; break; }
    }
}
if ($mode === 'check') { WP_CLI::log((string) wp_json_encode(['pending' => $pending])); return; }
if ($mode === 'verify') {
    if ($pending) { WP_CLI::error('Stale owner events remain.'); }
    WP_CLI::success('Allowlisted retired-owner hooks absent.'); return;
}
if (!$pending) { WP_CLI::success('Already applied; no changes.'); return; }
if (!defined('DISABLE_WP_CRON') || !DISABLE_WP_CRON) { WP_CLI::error('Disable traffic cron and pause host scheduling before applying.'); }
if (!$path || file_exists($path)) { WP_CLI::error('A new private before-image path is required.'); }
$receipt = (string) wp_json_encode(['site' => home_url(), 'hooks' => $pending, 'cron' => get_option('cron')], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$mask = umask(0077); $file = fopen($path, 'x'); umask($mask);
if (!$file) { WP_CLI::error('Could not create before-image.'); }
$written = fwrite($file, $receipt); fclose($file);
if ($written !== strlen($receipt) || file_get_contents($path) !== $receipt || (fileperms($path) & 0077) !== 0) { WP_CLI::error('Before-image verification failed.'); }
foreach ($pending as $hook) {
    $result = wp_unschedule_hook($hook, true);
    if (is_wp_error($result) || $result === false) { WP_CLI::error('Could not unschedule ' . $hook . '; retain before-image.'); }
    foreach ((array) _get_cron_array() as $hooks) {
        if (isset($hooks[$hook])) { WP_CLI::error('Events remain for ' . $hook); }
    }
}
WP_CLI::success('Allowlisted hooks removed; retain private before-image. Restore only reviewed events through WP scheduling APIs if rolling back.');
