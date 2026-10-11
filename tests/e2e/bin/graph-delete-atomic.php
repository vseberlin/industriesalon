<?php
/** Run with WP-CLI --exec='define("DISABLE_WP_CRON", true);'. Only temporary tables are mutated. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated copies of service-owned schemas and deliberate query failures.
global $wpdb;
$service = iss_graph_get_service();
$tables = [
    'relations' => $service->get_relation_table_name(),
    'names' => $service->get_name_table_name(),
    'identifiers' => $service->get_identifier_table_name(),
    'evidence' => $service->get_evidence_table_name(),
    'search' => $service->get_search_table_name(),
    'person' => $service->get_person_facts_table_name(),
    'organization' => $service->get_organization_facts_table_name(),
    'entities' => $service->get_entity_table_name(),
];
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Test owns an independent autocommit connection');
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A);
$snapshot = static fn(): array => array_map($read, $tables);
$fingerprint = static fn(string $table): string => hash('sha256', (string) wp_json_encode($read($table)));
$original = array_map($fingerprint, $tables);
$schemas = [];
foreach ($tables as $key => $table) {
    $expect($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) === 'InnoDB', $key . ': real table is transactional');
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    $expect(is_array($schema) && str_starts_with($schema[1], 'CREATE TABLE '), $key . ': read real schema');
    $schemas[$key] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$seed = static function () use ($wpdb, $tables, $expect): void {
    foreach ($tables as $key => $table) {
        $expect($wpdb->query("DELETE FROM `$table`") !== false, $key . ': reset temporary fixture');
        foreach ([10, 20] as $id) {
            $row = ['id' => $id, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
            if ($key === 'entities') {
                $row += ['entity_kind' => 'person', 'post_id' => 100000 + $id, 'source_system' => 'atomic_delete', 'source_id' => (string) $id, 'canonical_slug' => 'fixture-' . $id, 'display_title' => 'Fixture', 'summary' => 'Preserve unless targeted'];
            } elseif ($key === 'relations') {
                $row += ['from_entity_id' => $id, 'to_entity_id' => $id, 'relation_family' => 'person', 'note' => 'Self edge'];
            } else {
                $row['entity_id'] = $id;
                $row += match ($key) {
                    'names' => ['name' => 'Fixture ' . $id],
                    'identifiers' => ['namespace' => 'atomic_delete', 'value' => (string) $id, 'normalized_value' => (string) $id],
                    'evidence' => ['note' => 'Evidence ' . $id],
                    'search' => ['target_post_id' => 100000 + $id, 'target_post_type' => 'person', 'title' => 'Fixture', 'excerpt' => 'Excerpt', 'search_text' => 'Indexed text'],
                    'person', 'organization' => ['summary' => 'Summary', 'description' => 'Description', 'source_summary' => 'Source'],
                };
            }
            $expect($wpdb->insert($table, $row) === 1, $key . ': seed target and unrelated entity');
        }
    }
    foreach ([[30, 10, 20], [40, 20, 10]] as [$id, $from, $to]) {
        $expect($wpdb->insert($tables['relations'], ['id' => $id, 'from_entity_id' => $from, 'to_entity_id' => $to, 'relation_family' => 'person', 'note' => 'Cross-entity edge', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) === 1, 'Seed incoming and outgoing edges');
    }
};
$fail = static function (callable $call, string $message = 'Graph entity deletion') use ($expect): void {
    $caught = null;
    try { $call(); } catch (RuntimeException $exception) { $caught = $exception; }
    $expect($caught instanceof RuntimeException && str_contains($caught->getMessage(), $message), 'Caller receives failure: ' . $message);
};
$created = [];
$filter = null;
$error = null;
$old_suppression = $wpdb->suppress_errors(true);
try {
    // Same-name temporary tables are visible only in this connection. Real rows
    // remain untouched even when testing successful COMMIT or failed writes.
    foreach ($schemas as $key => $schema) {
        $expect($wpdb->query($schema) !== false, $key . ': create isolated schema copy');
        $created[] = $tables[$key];
    }
    $seed();
    $baseline = $snapshot();
    $remaining = [];
    foreach ($baseline as $key => $rows) {
        $remaining[$key] = array_values(array_filter($rows, static function (array $row) use ($key): bool {
            if ($key === 'relations') { return (int) $row['from_entity_id'] !== 10 && (int) $row['to_entity_id'] !== 10; }
            return (int) $row[$key === 'entities' ? 'id' : 'entity_id'] !== 10;
        }));
    }
    $service->delete_entity(10);
    $expect($snapshot() === $remaining, 'Successful delete removes all target dependents, including incoming/outgoing/self edges, and preserves unrelated rows');
    $expect($transaction() === 0, 'Standalone success commits');
    $service->delete_entity(10);
    $service->delete_entity(9999);
    $expect($snapshot() === $remaining, 'Repeated and absent-entity deletion are harmless');
    $queries = $wpdb->num_queries;
    $service->delete_entity(0);
    $service->delete_entity(-1);
    $expect($wpdb->num_queries === $queries, 'Invalid IDs keep the no-op contract');

    $seed();
    foreach (range(1, 9) as $failed_delete) {
        $deletes = 0;
        $filter = static function (string $sql) use (&$deletes, $failed_delete): string {
            if (str_starts_with($sql, 'DELETE FROM ') && ++$deletes === $failed_delete) {
                return 'SELECT iss_graph_delete_test_injected_failure';
            }
            return $sql;
        };
        add_filter('query', $filter);
        $fail(static fn() => $service->delete_entity(10));
        remove_filter('query', $filter);
        $filter = null;
        $expect($deletes === $failed_delete, 'Stop after failed delete ' . $failed_delete);
        $expect($snapshot() === $baseline && $transaction() === 0, 'Failed delete ' . $failed_delete . ' restores all eight tables');
    }

    foreach (['state', 'missing_table', 'engine', 'begin', 'commit', 'php'] as $stage) {
        $hits = 0;
        $filter = static function (string $sql) use ($stage, $tables, &$hits): string {
            $match = match ($stage) {
                'state', 'missing_table', 'engine' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'commit' => $sql === 'COMMIT',
                'php' => str_starts_with($sql, 'DELETE FROM `' . $tables['entities'] . '`'),
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected PHP deletion failure'); }
            if ($stage === 'missing_table') { return $sql . ' AND TABLE_NAME <> \'' . $tables['entities'] . '\''; }
            if ($stage === 'engine') { return str_replace('ENGINE AS engine', "IF(TABLE_NAME = '" . $tables['entities'] . "', 'MyISAM', ENGINE) AS engine", $sql); }
            return 'SELECT iss_graph_delete_test_injected_failure';
        };
        add_filter('query', $filter);
        $fail(static fn() => $service->delete_entity(10), $stage === 'php' ? 'Injected PHP deletion failure' : 'Graph entity deletion');
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1, $stage . ': failure path exercised');
        $expect($snapshot() === $baseline && $transaction() === 0, $stage . ': all rows retained and transaction closed');
    }

    foreach (['success', 'delete', 'savepoint', 'release'] as $stage) {
        $expect($wpdb->query('START TRANSACTION') !== false, 'Caller starts transaction');
        $expect($wpdb->update($tables['names'], ['name' => 'Caller pending edit'], ['entity_id' => 20]) === 1, 'Caller has preceding work');
        $pending = $snapshot();
        $hits = 0;
        if ($stage === 'success') {
            $service->delete_entity(10);
            $expect(count($snapshot()['entities']) === 1, 'Nested deletion completes');
        } else {
            $filter = static function (string $sql) use ($stage, $tables, &$hits): string {
                $match = match ($stage) {
                    'delete' => str_starts_with($sql, 'DELETE FROM `' . $tables['entities'] . '`'),
                    'savepoint' => str_starts_with($sql, 'SAVEPOINT iss_graph_write_'),
                    'release' => str_starts_with($sql, 'RELEASE SAVEPOINT iss_graph_write_'),
                };
                if (!$match || $hits > 0) { return $sql; }
                ++$hits;
                return 'SELECT iss_graph_delete_test_injected_failure';
            };
            add_filter('query', $filter);
            $fail(static fn() => $service->delete_entity(10));
            remove_filter('query', $filter);
            $filter = null;
            $expect($hits === 1 && $snapshot() === $pending, $stage . ': nested failure preserves caller work');
        }
        $expect($transaction() === 1, $stage . ': caller transaction still open');
        $expect($wpdb->query('ROLLBACK') !== false, 'Caller rolls back');
        $expect($snapshot() === $baseline, $stage . ': caller can undo all changes');
    }
    $expect($wpdb->query('SET autocommit = 0') !== false, 'Caller disables autocommit');
    $service->delete_entity(10);
    $expect($transaction() === 1, 'Disabled autocommit retains caller ownership');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    $expect($snapshot() === $baseline, 'Disabled-autocommit caller can undo deletion');

    // The existing post-based facade must neither swallow errors nor delete a
    // WordPress source post. Only its graph projection exists in these fixtures.
    $filter = static fn(string $sql): string => str_starts_with($sql, 'DELETE FROM `' . $tables['entities'] . '`') ? 'SELECT iss_graph_delete_test_injected_failure' : $sql;
    add_filter('query', $filter);
    $fail(static fn() => $service->delete_entity_by_post('person', 100010));
    remove_filter('query', $filter);
    $filter = null;
    $expect($snapshot() === $baseline, 'Post-based facade propagates failure with graph rows restored');
    $service->delete_entity_by_post('person', 100010);
    $expect($snapshot() === $remaining, 'Post-based facade deletes the matching projection');
    $service->delete_entity_by_post('person', 100010);
    $expect($snapshot() === $remaining, 'Missing post mapping is harmless');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter) { remove_filter('query', $filter); }
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    $wpdb->suppress_errors($old_suppression);
}
$expect(array_map($fingerprint, $tables) === $original, 'All eight real graph tables remain byte-identical');
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success($checks . ' graph deletion checks passed; temporary fixtures removed and real rows unchanged.');
