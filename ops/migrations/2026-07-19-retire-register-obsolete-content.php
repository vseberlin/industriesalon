<?php
/**
 * Remove obsolete TouchTable snapshots and empty register CPTs.
 *
 * The migration refuses deletion if a target contains reviewed/linked source
 * decisions or any feedback/story rows. A target DB backup is still required.
 *
 * Usage:
 *   wp eval-file ops/migrations/2026-07-19-retire-register-obsolete-content.php --allow-root
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run through WP-CLI.\n");
    exit(1);
}

global $wpdb;
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders -- Reviewed one-time retirement migration with target backup tables and bounded IDs.

$source_type = 'register_source_item';
$empty_types = ['register_feedback', 'atlas_story'];
$source_ids = array_values(array_map('absint', (array) $wpdb->get_col(
    $wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_type = %s ORDER BY ID", $source_type)
)));
$status_rows = $wpdb->get_results(
    $wpdb->prepare(
        "SELECT COALESCE(pm.meta_value, 'new') AS status, COUNT(*) AS total
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm
            ON pm.post_id = p.ID AND pm.meta_key = %s
        WHERE p.post_type = %s
        GROUP BY COALESCE(pm.meta_value, 'new')
        ORDER BY status",
        '_iss_register_source_review_status',
        $source_type
    ),
    ARRAY_A
);

$unexpected_statuses = array_values(array_filter($status_rows, static function (array $row): bool {
    return !in_array((string) ($row['status'] ?? ''), ['new', 'ignored'], true);
}));
if ($unexpected_statuses) {
    WP_CLI::error('Refusing retirement: target contains reviewed or linked TouchTable decisions.');
}

foreach ($empty_types as $post_type) {
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
        $post_type
    ));
    if ($count !== 0) {
        WP_CLI::error(sprintf('Refusing retirement: target contains %d %s rows.', $count, $post_type));
    }
}

$posts_backup = $wpdb->prefix . 'iss_backup_20260719_register_obsolete_posts';
$meta_backup = $wpdb->prefix . 'iss_backup_20260719_register_obsolete_postmeta';
$wpdb->query("CREATE TABLE IF NOT EXISTS {$posts_backup} LIKE {$wpdb->posts}");
$wpdb->query("CREATE TABLE IF NOT EXISTS {$meta_backup} LIKE {$wpdb->postmeta}");

if ($source_ids) {
    $placeholders = implode(', ', array_fill(0, count($source_ids), '%d'));
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$posts_backup}
        SELECT * FROM {$wpdb->posts} WHERE ID IN ({$placeholders})",
        $source_ids
    ));
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$meta_backup}
        SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ({$placeholders})",
        $source_ids
    ));

    $wpdb->query('START TRANSACTION');
    $deleted_meta = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$placeholders})",
        $source_ids
    ));
    $deleted_terms = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$placeholders})",
        $source_ids
    ));
    $deleted_posts = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->posts} WHERE ID IN ({$placeholders})",
        $source_ids
    ));

    if ($deleted_meta === false || $deleted_terms === false || $deleted_posts !== count($source_ids)) {
        $wpdb->query('ROLLBACK');
        WP_CLI::error('Obsolete register row deletion failed; transaction rolled back.');
    }
    $wpdb->query('COMMIT');
}

$remaining = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
    $source_type
));
if ($remaining !== 0) {
    WP_CLI::error(sprintf('Retirement verification failed: %d source rows remain.', $remaining));
}

WP_CLI::log('TouchTable inventory before deletion: ' . wp_json_encode($status_rows, JSON_UNESCAPED_SLASHES));
WP_CLI::success(sprintf(
    'Retired %d obsolete TouchTable source rows; backups: %s, %s.',
    count($source_ids),
    $posts_backup,
    $meta_backup
));
// phpcs:enable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL, WordPress.DB.PreparedSQLPlaceholders
