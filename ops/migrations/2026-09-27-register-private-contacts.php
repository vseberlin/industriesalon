<?php
/**
 * wp eval-file FILE dry-run|apply|verify /PRIVATE/payload.json --use-include
 * Keep the reviewed payload outside the web root and Git; transfer privately.
 * Backup: prefix_iss_backup_20260927_register_contacts (target metadata only).
 * Rollback only research_note and _iss_register_contacts for receipt targets
 * using before-images. Do not expose restored contact-bearing notes publicly.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit(1);
}
define('ISS_REGISTER_SYNC_LIBRARY_ONLY', true);
require_once __DIR__ . '/2026-09-27-register-current-sync.php';
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Bounded migration with prepared values and WordPress-owned tables.
// phpcs:disable WordPress.Security.EscapeOutput -- CLI output contains counts, not contact data.
global $wpdb;
$mode = (string) ($args[0] ?? 'dry-run');
try {
    $path = realpath((string) ($args[1] ?? ''));
    if (!in_array($mode, ['dry-run', 'apply', 'verify'], true) || !$path || str_starts_with($path, ABSPATH)) {
        throw new RuntimeException('Use dry-run, apply or verify with a private payload outside the web root.');
    }
    $payload = json_decode((string) file_get_contents($path), true);
    if (!is_array($payload) || empty($payload['records'])) {
        throw new RuntimeException('Missing reviewed contact payload.');
    }
    $targets = [];
    $pending = [];
    foreach ($payload['records'] as $record) {
        $post = get_page_by_path($record['slug'], OBJECT, ISS_REGISTER_POST_TYPE);
        if (!$post || (string) get_post_meta($post->ID, 'register_id', true) !== $record['source_id']) {
            throw new RuntimeException('Place identity mismatch.');
        }
        $id = (int) $post->ID;
        if (isset($targets[$id]) || $record['contacts'] !== iss_register_sanitize_contacts($record['contacts']) || !$record['contacts']) {
            throw new RuntimeException('Invalid contact payload or duplicate Place.');
        }
        $targets[$id] = ['research_note', '_iss_register_contacts'];
        $note = get_post_meta($id, 'research_note', true);
        $contacts = get_post_meta($id, '_iss_register_contacts', true);
        if ($note === $record['after'] && $contacts === $record['contacts']) {
            continue;
        }
        if ($note !== $record['before'] || $contacts !== '') {
            throw new RuntimeException('Existing notes or contacts differ; reconcile without overwriting.');
        }
        $pending[$id] = $record;
    }
    $before = iss_register_sync_protected_hashes($targets);
    $result = ['mode' => $mode, 'places' => count($targets), 'pending_places' => count($pending), 'protected' => $before];
    if ($mode === 'verify' && $pending) {
        throw new RuntimeException('Contact separation has not been applied.');
    }
    if ($mode === 'apply' && $pending) {
        $backup = $wpdb->prefix . 'iss_backup_20260927_register_contacts';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($backup))) === $backup) {
            throw new RuntimeException('Backup exists; inspect before partial replay.');
        }
        $ids = implode(',', array_keys($pending));
        if ($wpdb->query("CREATE TABLE {$backup} AS SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ({$ids})") === false
            || (int) $wpdb->get_var("SELECT COUNT(*) FROM {$backup}") === 0) {
            throw new RuntimeException('Contact backup failed.');
        }
        $wpdb->query('START TRANSACTION');
        foreach ($pending as $id => $record) {
            update_post_meta($id, 'research_note', wp_slash($record['after']));
            update_post_meta($id, '_iss_register_contacts', wp_slash($record['contacts']));
            if (get_post_meta($id, 'research_note', true) !== $record['after'] || get_post_meta($id, '_iss_register_contacts', true) !== $record['contacts']) {
                throw new RuntimeException('Contact write did not round-trip.');
            }
        }
        if ($before !== iss_register_sync_protected_hashes($targets)) {
            throw new RuntimeException('Protected content changed.');
        }
        if ($wpdb->query('COMMIT') === false) {
            throw new RuntimeException('Commit failed.');
        }
        iss_register_clear_places_cache();
        $result['updated_places'] = count($pending);
        $result['protected_unchanged'] = true;
    }
    WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT));
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK');
    foreach (array_keys($targets ?? []) as $id) {
        clean_post_cache($id);
    }
    WP_CLI::error($error->getMessage());
}
