<?php
/**
 * Run after register-current-sync: retire obsolete automatically derived roles.
 * wp eval-file FILE dry-run|apply|verify --use-include
 * Does not infer organization identities from qualified supplier statements.
 * Rollback: restore only changed relation rows from the dedicated backup table.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit(1);
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Bounded, prepared migration over graph-owned tables.
// phpcs:disable WordPress.Security.EscapeOutput -- CLI output only.
global $wpdb;
$mode = (string) ($args[0] ?? 'dry-run');
try {
    if (!in_array($mode, ['dry-run', 'apply', 'verify'], true)) {
        throw new RuntimeException('Use dry-run, apply or verify.');
    }
    $manifest = json_decode((string) file_get_contents(__DIR__ . '/2026-09-27-register-updates.json'), true);
    $service = iss_graph_get_service();
    $table = $service->get_relation_table_name();
    $before = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id", ARRAY_A);
    $pending = [];
    foreach ($manifest['records'] as $record) {
        $post = get_page_by_path($record['expected_slug'], OBJECT, ISS_REGISTER_POST_TYPE);
        $proof = $post ? get_post_meta($post->ID, '_iss_register_current_import', true) : null;
        if (!$post || !is_array($proof) || $proof['source_revision'] !== $manifest['source_revision'] || $proof['accepted_fields'] !== $record['fields']) {
            throw new RuntimeException('Apply the current-fact import first.');
        }
        $entity = $service->find_entity_by_post('place', (int) $post->ID);
        foreach ($before as $row) {
            $role = $row['relation_type'];
            if (!$entity || (int) $row['from_entity_id'] !== (int) $entity['id']
                || $row['source_system'] !== 'register_meta' || $row['source_ref'] !== 'post:' . $post->ID
                || $row['relation_family'] !== 'organization' || $row['relation_status'] !== 'derived'
                || $row['valid_from_year'] !== null || $row['valid_to_year'] !== null
                || !in_array($role, ['owner', 'operator', 'developer', 'tenant'], true)
                || !array_key_exists($role, $record['fields'])) {
                continue;
            }
            if (get_post_meta($post->ID, $role, true) !== $record['fields'][$role]) {
                throw new RuntimeException('Current role changed after import.');
            }
            $pending[(int) $row['id']] = $row;
        }
    }
    $result = ['mode' => $mode, 'pending_obsolete_relations' => count($pending), 'relation_ids' => array_keys($pending)];
    if ($mode === 'verify' && $pending) {
        throw new RuntimeException('Obsolete derived current relations remain.');
    }
    if ($mode === 'apply' && $pending) {
        $backup = $wpdb->prefix . 'iss_backup_20260927_register_relations';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($backup))) === $backup) {
            throw new RuntimeException('Backup already exists; inspect before any partial replay.');
        }
        $ids = implode(',', array_keys($pending));
        if ($wpdb->query("CREATE TABLE {$backup} AS SELECT * FROM {$table} WHERE id IN ({$ids})") === false
            || (int) $wpdb->get_var("SELECT COUNT(*) FROM {$backup}") !== count($pending)) {
            throw new RuntimeException('Relation backup failed.');
        }
        $wpdb->query('START TRANSACTION');
        foreach ($pending as $id => $row) {
            if ($wpdb->update($table, ['relation_status' => 'deprecated', 'is_public' => 0], ['id' => $id, 'relation_status' => 'derived']) !== 1) {
                throw new RuntimeException('Relation update failed.');
            }
        }
        $after = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id", ARRAY_A);
        $expected = $before;
        foreach ($expected as &$row) {
            if (isset($pending[(int) $row['id']])) {
                $row['relation_status'] = 'deprecated';
                $row['is_public'] = '0';
            }
        }
        unset($row);
        if ($after !== $expected || $wpdb->query('COMMIT') === false) {
            throw new RuntimeException('Relation preservation verification failed.');
        }
        iss_register_clear_places_cache();
        $result['retired_relations'] = count($pending);
        $result['all_other_relations_unchanged'] = true;
    }
    WP_CLI::line((string) wp_json_encode($result, JSON_PRETTY_PRINT));
} catch (Throwable $error) {
    $wpdb->query('ROLLBACK');
    WP_CLI::error($error->getMessage());
}
