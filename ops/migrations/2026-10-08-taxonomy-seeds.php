<?php
/**
 * Controlled seed upgrade. Deploy matching iss-content and iss-archive first.
 * wp eval-file FILE check|apply|verify /PRIVATE/new-receipt.json --use-include
 * Apply creates and verifies a scoped before-image before calling owner APIs.
 * No media or content transfer. Rollback: restore only the three option rows
 * from the receipt; if terms were added, review their usage and later edits
 * before removing any. Never roll back the whole database over editorial work.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Read-only before-image of the exact migration-owned options.
$mode = $args[0] ?? 'check';
$path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true)) { WP_CLI::error('Unknown mode.'); }
if (!defined('ISS_CONTENT_MODEL_TAXONOMY_SEED_VERSION') || !defined('ISS_WF_IMPORT_TAXONOMY_SEED_VERSION')) {
    WP_CLI::error('Deploy the matching content and archive plugins first.');
}
$seeds = [
    [ISS_CONTENT_MODEL_TAXONOMY_SEED_OPTION, ISS_CONTENT_MODEL_TAXONOMY_SEED_VERSION, 'iss_content_model_maybe_seed_taxonomy_terms'],
    [ISS_CONTENT_FALLBACK_CATEGORY_SEED_OPTION, ISS_CONTENT_FALLBACK_CATEGORY_SEED_VERSION, 'iss_content_fallback_seed_categories'],
    [ISS_WF_IMPORT_TAXONOMY_SEED_OPTION, ISS_WF_IMPORT_TAXONOMY_SEED_VERSION, 'iss_wf_import_ensure_museum_digital_terms'],
];
$pending = [];
foreach ($seeds as [$option, $version, $callback]) {
    if ((string) get_option($option, '') !== $version) { $pending[] = $option; }
}
if ($mode === 'check') {
    WP_CLI::log((string) wp_json_encode(['pending' => $pending], JSON_PRETTY_PRINT));
    return;
}
if ($mode === 'verify') {
    if ($pending) { WP_CLI::error('Incomplete taxonomy seeds: ' . implode(', ', $pending)); }
    WP_CLI::success('All three taxonomy seed versions verified.');
    return;
}
if (!$pending) { WP_CLI::success('Already applied; no changes.'); return; }
if (!$path || file_exists($path)) { WP_CLI::error('A new private before-image path is required.'); }
global $wpdb;
$options = array_column($seeds, 0);
$before = [
    'site' => home_url(),
    'options' => $wpdb->get_results($wpdb->prepare("SELECT option_name, option_value, autoload FROM $wpdb->options WHERE option_name IN (%s, %s, %s) ORDER BY option_name", $options[0], $options[1], $options[2]), ARRAY_A),
    'option_names' => $options,
    'term_ids' => $wpdb->get_col("SELECT term_id FROM $wpdb->terms ORDER BY term_id"),
    'pending' => $pending,
];
if (!is_array($before['options']) || !is_array($before['term_ids']) || $wpdb->last_error !== '') {
    WP_CLI::error('Cannot read the migration before-image.');
}
$receipt = wp_json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$file = fopen($path, 'x');
if (!$file) { WP_CLI::error('Cannot create before-image.'); }
$written = fwrite($file, (string) $receipt);
fclose($file);
if ($written !== strlen((string) $receipt) || file_get_contents($path) !== $receipt) { WP_CLI::error('Before-image verification failed.'); }
foreach ($seeds as [$option, $version, $callback]) {
    if (!$callback() || (string) get_option($option, '') !== $version) {
        WP_CLI::error('Seed incomplete: ' . $option . '. Retain receipt and retry after fixing the failure.');
    }
}
WP_CLI::success('Taxonomy seed upgrade verified; before-image retained at ' . $path);
