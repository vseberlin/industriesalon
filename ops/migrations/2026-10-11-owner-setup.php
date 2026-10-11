<?php
/**
 * wp eval-file FILE check|apply|verify /PRIVATE/new-before-image.json --use-include
 * Deploy matching ISS Core/Newsletter first. No remote calls or mail.
 * Backup covers exact options incl. roles, aliases and newsletter defaults.
 * Rollback restores only these rows after reviewing subsequent admin edits.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Scoped setup before-image.
$mode = $args[0] ?? 'check'; $path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true)) { WP_CLI::error('Unknown mode.'); }
if (!defined('ISS_NEWSLETTER_DEFAULTS_VERSION') || !function_exists('iss_core_apply_capability_migration')) { WP_CLI::error('Matching setup owners required.'); }
$pending = static function (): array {
    $items = [];
    if (get_option(ISS_CORE_CAPS_OPTION) !== ISS_CORE_CAPS_VERSION || iss_core_capability_diagnostic_report()['missing_role_caps']) { $items[] = 'capabilities'; }
    if (iss_newsletter_dependency_active() && get_option('iss_newsletter_defaults_version') !== ISS_NEWSLETTER_DEFAULTS_VERSION) { $items[] = 'newsletter_defaults'; }
    $active = (array) get_option('active_plugins', []);
    foreach (['iss-content-model/iss-content-model.php', 'iss-payments-lite/iss-payments-lite.php', 'iss-wf-import/iss-wf-import.php', 'iss-fuehrungen/iss-fuehrungen.php', 'iss-programm/iss-programm.php', 'saas-api/saas-api.php'] as $legacy) {
        if (in_array($legacy, $active, true)) { $items[] = 'domain_basenames'; break; }
    }
    if (in_array('iss-content/iss-content.php', $active, true) && !in_array('iss-editorial/iss-editorial.php', $active, true)) { $items[] = 'editorial_owner'; }
    return $items;
};
$items = $pending();
if ($mode === 'check') { WP_CLI::log((string) wp_json_encode(['pending' => $items])); return; }
if ($mode === 'verify') {
    if ($items) { WP_CLI::error('Setup pending: ' . implode(', ', $items)); }
    WP_CLI::success('Owner setup verified.'); return;
}
if (!$items) { WP_CLI::success('Already applied; no changes.'); return; }
if (!$path || file_exists($path)) { WP_CLI::error('A new private before-image path is required.'); }
global $wpdb;
$names = [wp_roles()->role_key, ISS_CORE_CAPS_OPTION, 'active_plugins', 'newsletter_antispam', 'newsletter_form', 'iss_newsletter_defaults_version'];
$placeholders = implode(', ', array_fill(0, count($names), '%s'));
$before = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ($placeholders) ORDER BY option_name", $names), ARRAY_A);
if ($wpdb->last_error !== '') { WP_CLI::error('Could not read setup before-image.'); }
$receipt = (string) wp_json_encode(['site' => home_url(), 'option_names' => $names, 'options' => $before, 'pending' => $items], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$old_mask = umask(0077);
$file = fopen($path, 'x'); umask($old_mask);
if (!$file) { WP_CLI::error('Could not create private before-image.'); }
$written = fwrite($file, $receipt); fclose($file);
if ($written !== strlen($receipt) || file_get_contents($path) !== $receipt || (fileperms($path) & 0077) !== 0) { WP_CLI::error('Before-image verification failed.'); }
iss_core_migrate_domain_plugin_basenames();
if (in_array('capabilities', $items, true)) { iss_core_apply_capability_migration(); }
iss_newsletter_configure_defaults();
if ($pending()) { WP_CLI::error('Setup incomplete; preserve the before-image and fix the reported owner.'); }
WP_CLI::success('Owner setup applied and verified; retain the private before-image.');
