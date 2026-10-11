<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Existing CLI verification reads owner graph tables.

function iss_graph_wpcli_verify_command(array $args, array $assoc_args): void
{
    global $wpdb;

    $service = iss_graph_get_service();
    $tables = [
        'entity_index' => $service->get_entity_table_name(),
        'entity_names' => $service->get_name_table_name(),
        'entity_identifiers' => $service->get_identifier_table_name(),
        'entity_relations' => $service->get_relation_table_name(),
        'search_index' => $service->get_search_table_name(),
        'person_facts' => $service->get_person_facts_table_name(),
        'organization_facts' => $service->get_organization_facts_table_name(),
        'entity_evidence_refs' => $service->get_evidence_table_name(),
        'editorial_signals' => $service->get_editorial_signal_table_name(),
        'dirty_queue' => $service->get_dirty_queue_table_name(),
    ];

    $failed = false;
    foreach ($tables as $label => $table_name) {
        $exists = $service->table_exists($table_name);
        if ($exists) {
            WP_CLI::log(sprintf('[ok] %s %s', $label, $table_name));
            continue;
        }

        $failed = true;
        WP_CLI::warning(sprintf('%s missing: %s', $label, $table_name));
    }

    $entity_count = $service->table_exists($service->get_entity_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_entity_table_name()}")
        : 0;
    $name_count = $service->table_exists($service->get_name_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_name_table_name()}")
        : 0;
    $relation_count = $service->table_exists($service->get_relation_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_relation_table_name()}")
        : 0;
    $identifier_count = $service->table_exists($service->get_identifier_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_identifier_table_name()}")
        : 0;
    $search_count = $service->table_exists($service->get_search_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_search_table_name()}")
        : 0;
    $person_facts_count = $service->table_exists($service->get_person_facts_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_person_facts_table_name()}")
        : 0;
    $organization_facts_count = $service->table_exists($service->get_organization_facts_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_organization_facts_table_name()}")
        : 0;
    $evidence_count = $service->table_exists($service->get_evidence_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_evidence_table_name()}")
        : 0;
    $editorial_signal_count = $service->table_exists($service->get_editorial_signal_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_editorial_signal_table_name()}")
        : 0;

    WP_CLI::log(sprintf(
        'entities=%d names=%d identifiers=%d relations=%d search=%d person_facts=%d organization_facts=%d evidence_refs=%d editorial_signals=%d',
        $entity_count,
        $name_count,
        $identifier_count,
        $relation_count,
        $search_count,
        $person_facts_count,
        $organization_facts_count,
        $evidence_count,
        $editorial_signal_count
    ));

    if ($failed) {
        WP_CLI::error('ISS graph verification failed.');
    }

    WP_CLI::success('ISS graph verification passed.');
}

function iss_graph_wpcli_table_has_column(string $table_name, string $column): bool
{
    global $wpdb;

    $found = $wpdb->get_var(
        $wpdb->prepare(
            "SHOW COLUMNS FROM {$table_name} LIKE %s",
            $column
        )
    );

    return is_string($found) && $found === $column;
}

function iss_graph_wpcli_relation_audit_stats(int $limit = 25): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $relation_table = $service->get_relation_table_name();
    $entity_table = $service->get_entity_table_name();
    $limit = max(1, min(500, $limit));

    if (!$service->table_exists($relation_table)) {
        return [
            'exists' => false,
            'missing_columns' => [],
            'counts' => [],
            'samples' => [],
        ];
    }

    $required_columns = ['edge_key', 'source_field', 'relation_status', 'confidence'];
    $missing_columns = [];
    foreach ($required_columns as $column) {
        if (!iss_graph_wpcli_table_has_column($relation_table, $column)) {
            $missing_columns[] = $column;
        }
    }

    if ($missing_columns) {
        return [
            'exists' => true,
            'missing_columns' => $missing_columns,
            'counts' => [],
            'samples' => [],
        ];
    }

    $counts = [
        'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$relation_table}"),
        'missing_edge_key' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$relation_table} WHERE edge_key = ''"),
        'missing_provenance' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$relation_table} WHERE source_system = '' OR source_ref = '' OR source_field = ''"),
        'invalid_status' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$relation_table} WHERE relation_status NOT IN ('derived', 'suggested', 'accepted', 'manual-pinned', 'suppressed', 'deprecated', 'rejected')"),
        'orphan_edges' => (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$relation_table} r
            LEFT JOIN {$entity_table} fe ON fe.id = r.from_entity_id
            LEFT JOIN {$entity_table} te ON te.id = r.to_entity_id
            WHERE fe.id IS NULL OR te.id IS NULL"
        ),
        'duplicate_edge_keys' => (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM (
                SELECT edge_key
                FROM {$relation_table}
                WHERE edge_key <> ''
                  AND relation_status NOT IN ('deprecated', 'rejected')
                GROUP BY edge_key
                HAVING COUNT(*) > 1
            ) duplicates"
        ),
    ];

    $samples = [
        'duplicate_edge_keys' => $wpdb->get_results(
            $wpdb->prepare(
                "SELECT edge_key, COUNT(*) AS count
                FROM {$relation_table}
                WHERE edge_key <> ''
                  AND relation_status NOT IN ('deprecated', 'rejected')
                GROUP BY edge_key
                HAVING COUNT(*) > 1
                ORDER BY count DESC, edge_key ASC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        ),
        'missing_provenance' => $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, from_entity_id, to_entity_id, relation_family, relation_type, source_system, source_ref, source_field
                FROM {$relation_table}
                WHERE source_system = '' OR source_ref = '' OR source_field = ''
                ORDER BY id ASC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        ),
        'orphan_edges' => $wpdb->get_results(
            $wpdb->prepare(
                "SELECT r.id, r.from_entity_id, r.to_entity_id, r.relation_family, r.relation_type
                FROM {$relation_table} r
                LEFT JOIN {$entity_table} fe ON fe.id = r.from_entity_id
                LEFT JOIN {$entity_table} te ON te.id = r.to_entity_id
                WHERE fe.id IS NULL OR te.id IS NULL
                ORDER BY r.id ASC
                LIMIT %d",
                $limit
            ),
            ARRAY_A
        ),
    ];

    return [
        'exists' => true,
        'missing_columns' => [],
        'counts' => $counts,
        'samples' => array_map(static function ($rows): array {
            return is_array($rows) ? array_values($rows) : [];
        }, $samples),
    ];
}

function iss_graph_wpcli_relation_audit_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(500, absint($assoc_args['limit'] ?? 25)));
    $format = sanitize_key((string) ($assoc_args['format'] ?? 'table'));
    $audit = iss_graph_wpcli_relation_audit_stats($limit);

    if ($format === 'json') {
        WP_CLI::log(wp_json_encode($audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        WP_CLI::success('ISS graph relation audit completed. No changes made.');
        return;
    }

    if (empty($audit['exists'])) {
        WP_CLI::warning('Relation table is missing.');
        WP_CLI::success('ISS graph relation audit completed. No changes made.');
        return;
    }

    if (!empty($audit['missing_columns'])) {
        WP_CLI::warning('Relation table is missing autonomy columns: ' . implode(', ', (array) $audit['missing_columns']));
        WP_CLI::success('ISS graph relation audit completed. No changes made.');
        return;
    }

    foreach ((array) ($audit['counts'] ?? []) as $key => $count) {
        WP_CLI::log(sprintf('%s=%d', $key, (int) $count));
    }

    foreach ((array) ($audit['samples'] ?? []) as $label => $rows) {
        if (!$rows) {
            continue;
        }
        WP_CLI::log(sprintf('[sample] %s', $label));
        foreach ($rows as $row) {
            WP_CLI::log('  ' . wp_json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
    }

    WP_CLI::success('ISS graph relation audit completed. No changes made.');
}

function iss_graph_wpcli_relation_backfill_command(array $args, array $assoc_args): void
{
    $batch_size = max(1, min(5000, absint($assoc_args['batch-size'] ?? 500)));
    $dry_run = isset($assoc_args['dry-run']);
    $all = isset($assoc_args['all']);
    $total = [
        'dry_run' => $dry_run,
        'batch_size' => $batch_size,
        'batches' => 0,
        'selected' => 0,
        'updated' => 0,
        'samples' => [],
    ];

    do {
        $stats = iss_graph_backfill_relation_autonomy_columns([
            'batch_size' => $batch_size,
            'dry_run' => $dry_run,
        ]);
        $total['batches']++;
        $total['selected'] += (int) ($stats['selected'] ?? 0);
        $total['updated'] += (int) ($stats['updated'] ?? 0);
        if (!$total['samples']) {
            $total['samples'] = array_slice((array) ($stats['items'] ?? []), 0, 10);
        }

        if ($dry_run || empty($stats['selected']) || !$all) {
            break;
        }
    } while ($total['batches'] < 100);

    WP_CLI::log(wp_json_encode($total, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    WP_CLI::success($dry_run ? 'ISS graph relation autonomy backfill dry-run completed. No changes made.' : 'ISS graph relation autonomy backfill completed.');
}

function iss_graph_wpcli_relation_dedupe_command(array $args, array $assoc_args): void
{
    global $wpdb;

    $service = iss_graph_get_service();
    $table = $service->get_relation_table_name();
    $limit = max(1, min(500, absint($assoc_args['limit'] ?? 100)));
    $dry_run = isset($assoc_args['dry-run']);
    if (!$service->table_exists($table)) {
        WP_CLI::error('Relation table is missing.');
    }

    $groups = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT edge_key, GROUP_CONCAT(id ORDER BY id ASC) AS ids, COUNT(*) AS count
            FROM {$table}
            WHERE edge_key <> ''
              AND relation_status NOT IN ('deprecated', 'rejected')
            GROUP BY edge_key
            HAVING COUNT(*) > 1
            ORDER BY edge_key ASC
            LIMIT %d",
            $limit
        ),
        ARRAY_A
    );
    $groups = is_array($groups) ? $groups : [];
    $deprecated = 0;
    $preview = [];

    foreach ($groups as $group) {
        $ids = array_values(array_filter(array_map('absint', explode(',', (string) ($group['ids'] ?? '')))));
        if (count($ids) < 2) {
            continue;
        }

        $keep_id = array_shift($ids);
        $preview[] = [
            'edge_key' => (string) ($group['edge_key'] ?? ''),
            'keep_id' => $keep_id,
            'deprecated_ids' => $ids,
        ];

        if ($dry_run) {
            continue;
        }

        foreach ($ids as $id) {
            $updated = $wpdb->update(
                $table,
                [
                    'relation_status' => 'deprecated',
                    'is_public' => 0,
                    'updated_at' => current_time('mysql', true),
                ],
                ['id' => $id],
                ['%s', '%d', '%s'],
                ['%d']
            );
            if ($updated !== false) {
                $deprecated++;
            }
        }
    }

    WP_CLI::log(wp_json_encode([
        'dry_run' => $dry_run,
        'groups' => count($groups),
        'deprecated' => $deprecated,
        'preview' => array_slice($preview, 0, 20),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    WP_CLI::success($dry_run ? 'ISS graph relation dedupe dry-run completed. No changes made.' : 'ISS graph relation dedupe completed.');
}

function iss_graph_wpcli_check_relation_integrity(int $limit): array
{
    $audit = iss_graph_wpcli_relation_audit_stats($limit);
    $errors = [];
    $counts = (array) ($audit['counts'] ?? []);

    if (empty($audit['exists'])) {
        return [
            'checked' => 0,
            'errors' => ['Relation table is missing.'],
        ];
    }

    if (!empty($audit['missing_columns'])) {
        return [
            'checked' => 0,
            'errors' => ['Relation table is missing autonomy columns: ' . implode(', ', (array) $audit['missing_columns'])],
        ];
    }

    foreach ([
        'missing_edge_key' => 'relation rows missing edge keys',
        'missing_provenance' => 'relation rows missing provenance',
        'invalid_status' => 'relation rows with invalid statuses',
        'orphan_edges' => 'orphan relation edges',
        'duplicate_edge_keys' => 'duplicate relation edge keys',
    ] as $key => $label) {
        $count = (int) ($counts[$key] ?? 0);
        if ($count > 0) {
            $errors[] = sprintf('%d %s.', $count, $label);
        }
    }

    return [
        'checked' => (int) ($counts['total'] ?? 0),
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_harvest_critical_stats(): array
{
    global $wpdb;

    $stats = [];
    if (post_type_exists('veranstaltung')) {
        $stats['veranstaltung.iss_primary_place_id'] = [
            'empty_count' => (int) $wpdb->get_var(
                "SELECT COUNT(*)
                FROM {$wpdb->posts} p
                LEFT JOIN {$wpdb->postmeta} pm
                    ON pm.post_id = p.ID
                   AND pm.meta_key = 'iss_primary_place_id'
                WHERE p.post_type = 'veranstaltung'
                  AND p.post_status NOT IN ('auto-draft', 'trash')
                  AND (pm.meta_value IS NULL OR pm.meta_value = '' OR pm.meta_value = '0')"
            ),
        ];
    }

    return $stats;
}

function iss_graph_wpcli_autonomy_health_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(100, absint($assoc_args['limit'] ?? 10)));
    $format = sanitize_key((string) ($assoc_args['format'] ?? 'table'));
    $health = [
        'schema_version' => (string) get_option(ISS_GRAPH_SCHEMA_OPTION, ''),
        'expected_schema_version' => ISS_GRAPH_SCHEMA_VERSION,
        'dirty_queue' => function_exists('iss_graph_get_dirty_queue_stats') ? iss_graph_get_dirty_queue_stats() : [],
        'relation_audit' => iss_graph_wpcli_relation_audit_stats($limit),
        'harvest_critical' => iss_graph_wpcli_harvest_critical_stats(),
    ];

    if ($format === 'json') {
        WP_CLI::log(wp_json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        WP_CLI::success('ISS graph autonomy health completed. No changes made.');
        return;
    }

    WP_CLI::log(sprintf('schema=%s expected=%s', $health['schema_version'], $health['expected_schema_version']));
    foreach ((array) ($health['dirty_queue'] ?? []) as $key => $value) {
        if (is_array($value)) {
            WP_CLI::log(sprintf('dirty_queue.%s=%s', $key, wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
            continue;
        }
        WP_CLI::log(sprintf('dirty_queue.%s=%s', $key, (string) $value));
    }
    foreach ((array) ($health['relation_audit']['counts'] ?? []) as $key => $count) {
        WP_CLI::log(sprintf('relation_audit.%s=%d', $key, (int) $count));
    }
    foreach ((array) ($health['harvest_critical'] ?? []) as $key => $value) {
        WP_CLI::log(sprintf('harvest_critical.%s=%s', $key, wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
    }

    WP_CLI::success('ISS graph autonomy health completed. No changes made.');
}

function iss_graph_wpcli_find_fixture_post(string $post_type): array
{
    if (!post_type_exists($post_type)) {
        return [
            'post_type' => $post_type,
            'exists' => false,
            'reason' => 'post_type_missing',
        ];
    }

    $ids = get_posts([
        'post_type' => $post_type,
        'post_status' => ['publish', 'draft', 'pending', 'private'],
        'numberposts' => 1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);
    $post_id = isset($ids[0]) ? absint($ids[0]) : 0;

    return [
        'post_type' => $post_type,
        'exists' => $post_id > 0,
        'post_id' => $post_id,
        'title' => $post_id > 0 ? get_the_title($post_id) : '',
        'reason' => $post_id > 0 ? '' : 'post_missing',
    ];
}

function iss_graph_wpcli_find_fixture_entity(array $kinds): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $table = $service->get_entity_table_name();
    if (!$service->table_exists($table)) {
        return [
            'entity_kinds' => $kinds,
            'exists' => false,
            'reason' => 'entity_table_missing',
        ];
    }

    $kinds = array_values(array_filter(array_map('sanitize_key', $kinds)));
    if (!$kinds) {
        return [
            'entity_kinds' => [],
            'exists' => false,
            'reason' => 'entity_kind_missing',
        ];
    }

    $placeholders = implode(', ', array_fill(0, count($kinds), '%s'));
    // phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholder count is built from sanitized entity-kind values and passed with argument unpacking.
    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id, entity_kind, post_id, profile_post_id, display_title
            FROM {$table}
            WHERE entity_kind IN ({$placeholders})
            ORDER BY is_public DESC, id ASC
            LIMIT 1",
            ...$kinds
        ),
        ARRAY_A
    );
    // phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare

    return [
        'entity_kinds' => $kinds,
        'exists' => is_array($row),
        'entity_id' => is_array($row) ? (int) ($row['id'] ?? 0) : 0,
        'entity_kind' => is_array($row) ? (string) ($row['entity_kind'] ?? '') : '',
        'post_id' => is_array($row) ? (int) ($row['post_id'] ?? 0) : 0,
        'profile_post_id' => is_array($row) ? (int) ($row['profile_post_id'] ?? 0) : 0,
        'title' => is_array($row) ? (string) ($row['display_title'] ?? '') : '',
        'reason' => is_array($row) ? '' : 'entity_missing',
    ];
}

function iss_graph_wpcli_autonomy_fixtures_command(array $args, array $assoc_args): void
{
    $format = sanitize_key((string) ($assoc_args['format'] ?? 'table'));
    $strict = isset($assoc_args['strict']);
    $archive_post_type = function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt';
    $place_post_type = function_exists('iss_graph_get_register_post_type') ? iss_graph_get_register_post_type() : 'register_place';
    $fixtures = [
        'event' => iss_graph_wpcli_find_fixture_post('veranstaltung'),
        'exhibition' => iss_graph_wpcli_find_fixture_post('ausstellung'),
        'publication' => iss_graph_wpcli_find_fixture_post('publication'),
        'archive_object' => iss_graph_wpcli_find_fixture_post($archive_post_type),
        'place' => iss_graph_wpcli_find_fixture_post($place_post_type),
        'person_or_organization' => iss_graph_wpcli_find_fixture_entity(['person', 'organization']),
    ];

    if ($format === 'json') {
        WP_CLI::log(wp_json_encode($fixtures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    } else {
        foreach ($fixtures as $label => $fixture) {
            WP_CLI::log(sprintf(
                '%s exists=%s id=%d title="%s" reason=%s',
                $label,
                !empty($fixture['exists']) ? 'yes' : 'no',
                (int) ($fixture['post_id'] ?? ($fixture['entity_id'] ?? 0)),
                (string) ($fixture['title'] ?? ''),
                (string) ($fixture['reason'] ?? '')
            ));
        }
    }

    $missing = array_keys(array_filter($fixtures, static function (array $fixture): bool {
        return empty($fixture['exists']);
    }));
    if ($strict && $missing) {
        WP_CLI::error('Missing autonomy fixtures: ' . implode(', ', $missing));
    }

    WP_CLI::success('ISS graph autonomy fixture check completed. No changes made.');
}

function iss_graph_wpcli_reconcile_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);
    $dry_run = isset($assoc_args['dry-run']);

    if ($post_id > 0) {
        if ($dry_run) {
            WP_CLI::log(wp_json_encode([
                'dry_run' => true,
                'post_id' => $post_id,
                'would_reconcile' => true,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            WP_CLI::success(sprintf('ISS graph reconcile dry-run completed for post %d. No changes made.', $post_id));
            return;
        }

        $result = iss_graph_reconcile_post($post_id);
        if (sanitize_key((string) ($result['status'] ?? '')) !== 'clean') {
            WP_CLI::error(sprintf('Post %d reconcile failed: %s', $post_id, (string) ($result['message'] ?? 'unknown')));
        }

        WP_CLI::success(sprintf('Reconciled post %d: %s', $post_id, (string) ($result['message'] ?? 'ok')));
        return;
    }

    $stats = iss_graph_drain_dirty_queue([
        'batch_size' => absint($assoc_args['batch-size'] ?? 50),
        'max_runtime' => absint($assoc_args['max-runtime'] ?? 30),
        'max_attempts' => absint($assoc_args['max-attempts'] ?? 3),
        'dry_run' => $dry_run,
    ]);

    WP_CLI::log(wp_json_encode($stats, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    if (!empty($stats['locked'])) {
        WP_CLI::warning('Dirty queue is locked by another reconcile run.');
    }
    if (!empty($stats['lease_lost'])) {
        WP_CLI::warning('Dirty queue processing stopped because this run no longer owns its lease.');
    }

    WP_CLI::success($dry_run ? 'ISS graph reconcile dry-run completed. No changes made.' : 'ISS graph dirty queue reconcile completed.');
}

function iss_graph_wpcli_get_editorial_signal_rows(string $status = ''): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $table = $service->get_editorial_signal_table_name();
    if (!$service->table_exists($table)) {
        return [];
    }

    $status = sanitize_key($status);
    if ($status !== '') {
        $rows = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE status = %s ORDER BY id ASC", $status),
            ARRAY_A
        );
    } else {
        $rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id ASC", ARRAY_A);
    }

    return is_array($rows) ? array_values($rows) : [];
}

function iss_graph_wpcli_signals_export_command(array $args, array $assoc_args): void
{
    $status = sanitize_key((string) ($assoc_args['status'] ?? ''));
    $file = isset($assoc_args['file']) ? (string) $assoc_args['file'] : '';
    $payload = [
        'exported_at' => current_time('mysql', true),
        'schema_version' => ISS_GRAPH_SCHEMA_VERSION,
        'status_filter' => $status,
        'signals' => iss_graph_wpcli_get_editorial_signal_rows($status),
    ];
    $json = wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (!is_string($json)) {
        WP_CLI::error('Could not encode editorial signals export.');
    }

    if ($file !== '') {
        $written = file_put_contents($file, $json . PHP_EOL); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- WP-CLI export writes an operator-selected local artifact.
        if ($written === false) {
            WP_CLI::error(sprintf('Could not write editorial signals export to %s.', $file));
        }
        WP_CLI::success(sprintf('Exported %d editorial signals to %s.', count($payload['signals']), $file));
        return;
    }

    WP_CLI::log($json);
    WP_CLI::success(sprintf('Exported %d editorial signals. No changes made.', count($payload['signals'])));
}

function iss_graph_wpcli_signals_import_command(array $args, array $assoc_args): void
{
    $file = isset($assoc_args['file']) ? (string) $assoc_args['file'] : '';
    $dry_run = isset($assoc_args['dry-run']);
    if ($file === '') {
        WP_CLI::error('Provide --file=/path/to/editorial-signals.json.');
    }

    if (!is_readable($file)) {
        WP_CLI::error(sprintf('Could not read editorial signals import file: %s.', $file));
    }

    $raw = file_get_contents($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- WP-CLI import reads an operator-selected local artifact.
    if (!is_string($raw) || $raw === '') {
        WP_CLI::error(sprintf('Could not read editorial signals import file: %s.', $file));
    }

    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        WP_CLI::error(sprintf('Invalid editorial signals JSON: %s.', $file));
    }

    $signals = isset($payload['signals']) && is_array($payload['signals']) ? $payload['signals'] : [];
    $imported = 0;
    $skipped = 0;
    foreach ($signals as $row) {
        if (!is_array($row)) {
            $skipped++;
            continue;
        }

        $context_post_id = absint($row['context_post_id'] ?? 0);
        $target_post_id = absint($row['target_post_id'] ?? 0);
        $surface = iss_graph_get_service()->normalize_editorial_signal_surface((string) ($row['surface'] ?? 'related'));
        $signal = iss_graph_get_service()->normalize_editorial_signal_type((string) ($row['signal_type'] ?? ($row['signal'] ?? '')));
        if ($context_post_id <= 0 || $target_post_id <= 0 || $signal === '') {
            $skipped++;
            continue;
        }

        if ($dry_run) {
            WP_CLI::log(sprintf('[dry-run] would import %s signal %s context=%d target=%d status=%s', $surface, $signal, $context_post_id, $target_post_id, sanitize_key((string) ($row['status'] ?? 'active'))));
            $imported++;
            continue;
        }

        $saved = iss_graph_upsert_editorial_signal_for_post($context_post_id, $target_post_id, $signal, [
            'surface' => $surface,
            'reason' => (string) ($row['reason'] ?? ''),
            'expires_at' => (string) ($row['expires_at'] ?? ''),
            'author_user_id' => absint($row['author_user_id'] ?? 0),
            'status' => sanitize_key((string) ($row['status'] ?? 'active')),
            'require_metadata' => false,
        ]);

        if ($saved) {
            $imported++;
        } else {
            $skipped++;
        }
    }

    WP_CLI::success(sprintf(
        $dry_run ? 'Editorial signals import dry-run completed: would_import=%d skipped=%d. No changes made.' : 'Imported editorial signals: imported=%d skipped=%d.',
        $imported,
        $skipped
    ));
}
