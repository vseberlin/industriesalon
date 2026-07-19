<?php
/**
 * Enable the shared Place editorial document for Kino Spreehöfe (post 12899).
 *
 * Run after deploying code and after
 * ops/migrations/2026-07-19-kino-spreehoefe-place-dossier.php.
 *
 * Usage:
 *   wp eval-file ops/migrations/2026-07-19-kino-spreehoefe-place-editorial.php --allow-root
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run through WP-CLI.\n");
    exit(1);
}

$post_id = 12899;
$post = get_post($post_id);
$required_functions = [
    'iss_content_model_build_place_editorial_candidate',
    'iss_editorial_save_document',
    'iss_editorial_set_document_enabled',
    'iss_editorial_get_document',
    'iss_editorial_document_is_enabled',
    'iss_register_sync_editorial_place_projection',
    'iss_register_get_epoch_service',
];

foreach ($required_functions as $required_function) {
    if (!function_exists($required_function)) {
        WP_CLI::error(sprintf('Required function is unavailable: %s', $required_function));
    }
}

if (!$post instanceof WP_Post || $post->post_type !== 'register_place') {
    WP_CLI::error('Expected register_place post 12899 was not found.');
}

$epochs_before = iss_register_get_epoch_service()->get_epochs_for_place($post_id);
if (count($epochs_before) !== 6) {
    WP_CLI::error(sprintf('Expected six reviewed legacy epochs before cutover; found %d.', count($epochs_before)));
}

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Reviewed one-time migration with owner-controlled table names and placeholders.
$backup_table = $wpdb->prefix . 'iss_backup_20260719_kino_place_editorial_meta';
$wpdb->query("CREATE TABLE IF NOT EXISTS {$backup_table} LIKE {$wpdb->postmeta}");

$meta_keys = [
    '_iss_editorial_place',
    '_iss_editorial_enabled_place',
    '_iss_editorial_place_autosave',
];
$meta_placeholders = implode(', ', array_fill(0, count($meta_keys), '%s'));
$wpdb->query(
    $wpdb->prepare(
        "INSERT IGNORE INTO {$backup_table}
        SELECT * FROM {$wpdb->postmeta}
        WHERE post_id = %d AND meta_key IN ({$meta_placeholders})",
        array_merge([$post_id], $meta_keys)
    )
);

$candidate = iss_content_model_build_place_editorial_candidate($post);
$candidate_epochs = array_values(array_filter(
    (array) ($candidate['sections'] ?? []),
    static function (array $section): bool {
        return ($section['type'] ?? '') === 'epoche';
    }
));

if (count($candidate_epochs) !== 6) {
    WP_CLI::error(sprintf('Candidate must contain six epoch sections; found %d.', count($candidate_epochs)));
}

if (!iss_editorial_save_document($post_id, 'place', $candidate, false)) {
    WP_CLI::error('Failed to save the Place editorial document.');
}

iss_editorial_set_document_enabled($post_id, 'place', true);
$projection = iss_register_sync_editorial_place_projection($post_id, 'place', $candidate);
if (is_wp_error($projection)) {
    WP_CLI::error($projection->get_error_message());
}

$saved = iss_editorial_get_document($post_id, 'place', false);
$saved_epochs = array_values(array_filter(
    (array) ($saved['sections'] ?? []),
    static function (array $section): bool {
        return ($section['type'] ?? '') === 'epoche';
    }
));
$projected_epochs = iss_register_get_epoch_service()->get_epochs_for_place($post_id);

if (
    !iss_editorial_document_is_enabled($post_id, 'place')
    || count($saved_epochs) !== 6
    || count($projected_epochs) !== 6
) {
    WP_CLI::error('Place editorial cutover verification failed.');
}

WP_CLI::success(sprintf(
    'Kino Spreehöfe Place document enabled: %d sections, %d epoch projections; backup table %s.',
    count((array) ($saved['sections'] ?? [])),
    count($projected_epochs),
    $backup_table
));
// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
