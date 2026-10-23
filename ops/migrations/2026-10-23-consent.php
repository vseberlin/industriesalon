<?php
/** wp eval-file FILE check|apply|verify /PRIVATE/new-before.json --use-include
 * Exact target-local settings only. Never sends mail, changes subscribers or imports accounts.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$mode = $args[0] ?? 'check';
$path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true)) { WP_CLI::error('Unknown mode.'); }
if (!function_exists('iss_newsletter_privacy_defaults')) { WP_CLI::error('Deploy the matching newsletter adapter first.'); }
$page = get_page_by_path('verein');
if (!$page || $page->post_status !== 'publish') { WP_CLI::error('Published Verein page required.'); }
foreach (['page-verein' => 'wp_template', 'footer' => 'wp_template_part'] as $slug => $type) {
    $template = get_block_template(get_stylesheet() . '//' . $slug, $type);
    if (!$template || $template->source !== 'theme') { WP_CLI::error('Review and preserve DB template override before applying: ' . $slug); }
}
$pending = static function () use ($page): array {
    $result = [];
    if (!in_array('iss-consent/iss-consent.php', (array) get_option('active_plugins'), true)) { $result[] = 'activate consent'; }
    foreach (iss_newsletter_privacy_checks() as $row) { if ($row['status'] !== 'ok') { $result[] = $row['name']; } }
    if ((int) get_option('wp_page_for_privacy_policy') !== (int) $page->ID) { $result[] = 'WordPress privacy page'; }
    return $result;
};
$items = $pending();
if ($mode === 'check') { WP_CLI::log((string) wp_json_encode(['pending' => $items, 'uploads_required' => false])); return; }
if ($mode === 'verify') {
    if ($items) { WP_CLI::error('Pending: ' . implode(', ', $items)); }
    WP_CLI::success('Consent settings applied. Browser acceptance is a separate gate.'); return;
}
if (!$items) { WP_CLI::success('Already applied.'); return; }
if (!$path || file_exists($path)) { WP_CLI::error('New private before-image path required.'); }
global $wpdb;
$names = array_merge(['active_plugins', 'wp_page_for_privacy_policy'], array_keys(iss_newsletter_privacy_defaults()));
$placeholders = implode(', ', array_fill(0, count($names), '%s'));
// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Exact option before-image; identifiers are WordPress owned, all values prepared.
$rows = $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE option_name IN ($placeholders) ORDER BY option_name", $names), ARRAY_A);
if ($wpdb->last_error !== '') { WP_CLI::error('Could not read settings before-image.'); }
$data = (string) wp_json_encode(['site' => home_url(), 'at' => gmdate('c'), 'option_names' => $names, 'options' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$mask = umask(0077);
$file = fopen($path, 'x');
umask($mask);
if (!$file) { WP_CLI::error('Cannot create before-image.'); }
$written = fwrite($file, $data); fclose($file);
if ($written !== strlen($data) || file_get_contents($path) !== $data || (fileperms($path) & 0077) !== 0) { WP_CLI::error('Before-image verification failed.'); }
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$activation = activate_plugin('iss-consent/iss-consent.php', '', false, true);
if (is_wp_error($activation)) { WP_CLI::error($activation->get_error_message()); }
iss_newsletter_apply_privacy_defaults();
update_option('wp_page_for_privacy_policy', (int) $page->ID);
if ($pending()) { WP_CLI::error('Settings incomplete; preserve before-image and inspect.'); }
WP_CLI::success('Consent enabled; newsletter settings and WordPress privacy page verified. No mail sent.');
