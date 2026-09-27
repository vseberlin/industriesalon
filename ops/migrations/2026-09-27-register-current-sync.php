<?php
/**
 * Bounded current-fact / modern-milestone import. No history, media or post writes.
 *
 * wp eval-file FILE dry-run --use-include
 * wp eval-file FILE apply --use-include
 * wp eval-file FILE verify --use-include
 * Reinbeckhallen follow-up: append "reinbeckhallen" after the mode.
 * This uses separate register_reinbeck_* backup tables and the same safeguards.
 *
 * Deploy the matching register/theme code first; no uploads are required.
 * Apply creates prefix_iss_backup_20260927_register_{meta,states,epochs} before
 * its transaction. Refuse existing backups unless the full receipt is already
 * applied. Rollback: restore only target meta/current states from those backups,
 * remove only the six manifest milestone keys, then clear the register cache.
 * Keep additive milestone columns when rolling back data. Never restore the
 * entire database over later editorial changes. A full host backup also exists
 * for the first local application; see the handoff.
 */

if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit(1);
}

// phpcs:disable WordPress.Security.EscapeOutput -- CLI-only exception messages, not HTML.
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Bounded migration; table identifiers are WordPress/service-owned and values are prepared.

function iss_register_sync_protected_hashes(array $targets): array
{
    global $wpdb;

    $meta = [];
    foreach ($wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id", ARRAY_A) as $row) {
        $key = $row['meta_key'];
        if ($key === '_edit_lock' || (isset($targets[(int) $row['post_id']]) && in_array($key, $targets[(int) $row['post_id']], true))) {
            continue;
        }
        $meta[] = $row;
    }
    $epoch_table = iss_register_get_epoch_service()->get_table_name();
    $state_table = iss_register_get_place_state_service()->get_table_name();
    $sets = [
        'posts' => $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A),
        'protected_meta' => $meta,
        'historical_epochs' => $wpdb->get_results("SELECT * FROM {$epoch_table} WHERE milestone_key IS NULL ORDER BY id", ARRAY_A),
        'historical_states' => $wpdb->get_results("SELECT * FROM {$state_table} WHERE state_kind = 'historical' ORDER BY id", ARRAY_A),
        'term_relationships' => $wpdb->get_results("SELECT * FROM {$wpdb->term_relationships} ORDER BY object_id,term_taxonomy_id", ARRAY_A),
    ];
    $hashes = [];
    foreach ($sets as $key => $rows) {
        $hashes[$key] = ['count' => count($rows), 'sha256' => hash('sha256', (string) wp_json_encode($rows))];
    }
    return $hashes;
}

function iss_register_current_sync_run(string $mode, string $batch = 'current'): array
{
    global $wpdb;

    if (!in_array($batch, ['current', 'reinbeckhallen'], true)) {
        throw new RuntimeException('Unknown import batch.');
    }
    $path = __DIR__ . ($batch === 'current' ? '/2026-09-27-register-updates.json' : '/2026-09-27-register-reinbeckhallen.json');
    $backup_stem = $wpdb->prefix . 'iss_backup_20260927_register_' . ($batch === 'current' ? '' : 'reinbeck_');
    $source_path = __DIR__ . '/2026-09-27-register-source.json';
    $manifest = json_decode((string) file_get_contents($path), true);
    $source = json_decode((string) file_get_contents($source_path), true);
    if (!is_array($manifest) || !is_array($source) || hash_file('sha256', $source_path) !== $manifest['source_file_sha256']) {
        throw new RuntimeException('Source artifact checksum mismatch.');
    }
    if ($source['source_revision'] !== $manifest['source_revision'] || $source['source_namespace'] !== $manifest['source_namespace']) {
        throw new RuntimeException('Source identity mismatch.');
    }
    $source_rows = array_column($source['records'], null, 'source_id');
    $allowed = ['owner', 'operator', 'developer', 'tenant', 'current_use', 'current_status', 'size', 'investment', 'legacy_kaufpreis', 'legacy_website'];
    $service = iss_register_get_epoch_service();
    $targets = [];
    $plans = [];
    $changes = 0;
    $event_count = 0;
    $provenance_key = '_iss_register_current_import';
    foreach ($manifest['records'] as $record) {
        $source_id = (string) $record['source_id'];
        if (($source_rows[$source_id]['content_sha256'] ?? '') !== $record['source_record_sha256']) {
            throw new RuntimeException('Source record mismatch: ' . $source_id);
        }
        $identity = iss_register_resolve_supplier_place($manifest['source_namespace'], $source_id);
        if (is_wp_error($identity)) {
            throw new RuntimeException($identity->get_error_message());
        }
        if (is_array($identity['allowed_fields']) && array_diff(array_keys($record['fields']), $identity['allowed_fields'])) {
            throw new RuntimeException('Component data cannot overwrite whole-site facts.');
        }
        if (is_array($identity['allowed_event_types']) && array_diff(array_column($record['milestones'], 'event_type'), $identity['allowed_event_types'])) {
            throw new RuntimeException('Component event is outside its accepted scope.');
        }
        $post_id = $identity['post_id'];
        $public_text = iss_register_validate_public_import_text($post_id, [$record['fields'], $record['milestones']]);
        if (is_wp_error($public_text)) {
            throw new RuntimeException($public_text->get_error_message());
        }
        $post = get_post($post_id);
        if ($post->post_name !== $record['expected_slug'] || $post->post_status !== 'publish') {
            throw new RuntimeException('Place identity/publication differs: ' . $source_id);
        }
        if (iss_editorial_document_is_enabled($post_id, 'place')) {
            throw new RuntimeException('This batch must not mutate an enabled Place JSON document.');
        }
        $proof = [
            'source_namespace' => $manifest['source_namespace'],
            'source_id' => $source_id,
            'source_revision' => $manifest['source_revision'],
            'source_url' => $manifest['source_url'],
            'source_record_sha256' => $record['source_record_sha256'],
            'observed_at' => $manifest['observed_at'],
            'accepted_fields' => $record['fields'],
            'milestone_keys' => array_column($record['milestones'], 'key'),
        ];
        $prior = get_post_meta($post_id, $provenance_key, true);
        if ($identity['scope'] !== 'place') {
            $proof['canonical_source_id'] = $identity['canonical_source_id'];
            $proof['source_ids'] = $identity['source_ids'];
            $proof['scope'] = $identity['scope'];
        }
        $applied = $prior === $proof;
        if ($prior !== '' && !$applied) {
            throw new RuntimeException('Different prior import: explicit reconciliation required.');
        }
        foreach ($record['fields'] as $key => $value) {
            if (!in_array($key, $allowed, true) || !is_string($value) || iss_register_sanitize_meta_value($key, $value) !== $value) {
                throw new RuntimeException('Disallowed or noncanonical current field: ' . $key);
            }
            $actual = get_post_meta($post_id, $key, false);
            $expected = $applied ? [$value] : $record['expected_meta'][$key];
            if ($actual !== $expected) {
                throw new RuntimeException('Precondition differs for #' . $source_id . ' / ' . $key);
            }
        }
        $existing = array_column($service->get_milestones_for_place($post_id), null, 'key');
        foreach ($record['milestones'] as $event) {
            $valid = $service->validate_milestone($event);
            if (is_wp_error($valid)) {
                throw new RuntimeException($valid->get_error_message());
            }
            if (isset($existing[$event['key']]) && $existing[$event['key']] !== $event) {
                throw new RuntimeException('Milestone identity conflict.');
            }
            if ($applied && !isset($existing[$event['key']])) {
                throw new RuntimeException('Applied milestone is missing.');
            }
        }
        if (isset($targets[$post_id])) {
            throw new RuntimeException('Duplicate target in manifest.');
        }
        $targets[$post_id] = array_merge(array_keys($record['fields']), [$provenance_key]);
        $plans[] = compact('post_id', 'source_id', 'record', 'proof', 'applied');
        if (!$applied) {
            $changes += count($record['fields']);
            $event_count += count($record['milestones']);
        }
    }
    $before = iss_register_sync_protected_hashes($targets);
    $result = ['mode' => $mode, 'source_revision' => $manifest['source_revision'], 'places' => count($plans), 'pending_field_writes' => $changes, 'pending_milestones' => $event_count, 'protected' => $before];
    if ($mode === 'verify' && $changes !== 0) {
        throw new RuntimeException('Import has not been fully applied.');
    }
    if ($mode !== 'apply' || $changes === 0) {
        return $result;
    }

    $id_list = implode(',', array_map('intval', array_keys($targets)));
    $backup_tables = [
        'meta' => "SELECT * FROM {$wpdb->postmeta} WHERE post_id IN ({$id_list})",
        'states' => "SELECT * FROM " . iss_register_get_place_state_service()->get_table_name() . " WHERE place_post_id IN ({$id_list})",
        'epochs' => "SELECT * FROM " . $service->get_table_name() . " WHERE place_post_id IN ({$id_list})",
    ];
    foreach ($backup_tables as $suffix => $select) {
        $table = $backup_stem . $suffix;
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table) {
            throw new RuntimeException('Backup exists; refuse to replay partial import: ' . $table);
        }
    }
    foreach ($backup_tables as $suffix => $select) {
        $table = $backup_stem . $suffix;
        if ($wpdb->query("CREATE TABLE {$table} AS {$select}") === false || (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}") === 0) {
            throw new RuntimeException('Targeted backup failed: ' . $table);
        }
    }
    $wpdb->query('START TRANSACTION');
    try {
        foreach ($plans as $plan) {
            if ($plan['applied']) {
                continue;
            }
            $post_id = $plan['post_id'];
            foreach ($plan['record']['fields'] as $key => $value) {
                update_post_meta($post_id, $key, wp_slash($value));
                if (get_post_meta($post_id, $key, false) !== [$value]) {
                    throw new RuntimeException('Current field write failed: ' . $key);
                }
            }
            foreach ($plan['record']['milestones'] as $event) {
                $saved = $service->append_milestone($post_id, $event);
                if (is_wp_error($saved)) {
                    throw new RuntimeException($saved->get_error_message());
                }
            }
            update_post_meta($post_id, $provenance_key, wp_slash($plan['proof']));
            if (get_post_meta($post_id, $provenance_key, true) !== $plan['proof']) {
                throw new RuntimeException('Import provenance did not round-trip.');
            }
            $synced = iss_register_get_place_state_service()->sync_current_state_for_post($post_id);
            if (is_wp_error($synced)) {
                throw new RuntimeException($synced->get_error_message());
            }
        }
        $after = iss_register_sync_protected_hashes($targets);
        if ($before !== $after) {
            throw new RuntimeException('Protected content changed; rolling back.');
        }
        if ($wpdb->query('COMMIT') === false) {
            throw new RuntimeException('Commit failed.');
        }
        iss_register_clear_places_cache();
        $result['written_fields'] = $changes;
        $result['appended_milestones'] = $event_count;
        $result['protected_unchanged'] = true;
        $result['local_ids'] = array_column($plans, 'post_id', 'source_id');
        return $result;
    } catch (Throwable $error) {
        $wpdb->query('ROLLBACK');
        foreach (array_keys($targets) as $post_id) {
            clean_post_cache($post_id);
        }
        iss_register_clear_places_cache();
        throw $error;
    }
}

if (defined('ISS_REGISTER_SYNC_LIBRARY_ONLY') && ISS_REGISTER_SYNC_LIBRARY_ONLY) {
    return;
}

$mode = (string) ($args[0] ?? 'dry-run');
if (!in_array($mode, ['dry-run', 'apply', 'verify'], true)) {
    WP_CLI::error('Use dry-run, apply or verify.');
}
try {
    WP_CLI::line((string) wp_json_encode(iss_register_current_sync_run($mode, (string) ($args[1] ?? 'current')), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
} catch (Throwable $error) {
    WP_CLI::error($error->getMessage());
}
