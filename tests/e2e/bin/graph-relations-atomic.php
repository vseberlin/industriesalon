<?php
/** WP-CLI integration checks using a connection-local copy of the real table schema. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Service-owned table, isolated temporary fixtures and intentional SQL failures.
global $wpdb;
$service = iss_graph_get_service();
$table = $service->get_relation_table_name();
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$read = static fn(): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A);
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Test requires an independent autocommit connection');
$expect($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) === 'InnoDB', 'Real relation table supports transactions');
$original = $read();
$schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
$expect(is_array($schema) && str_starts_with($schema[1], 'CREATE TABLE '), 'Read the real relation schema');
$old_suppression = $wpdb->suppress_errors(true);
$created = false;
$filter = null;
$error = null;
$new_rows = [
    ['to_entity_id' => 20, 'relation_type' => 'subject', 'relation_label' => '<b>New</b>', 'valid_from_year' => 1990, 'confidence' => 70],
    ['to_entity_id' => 30, 'relation_type' => 'partner', 'source_field' => 'partners', 'position' => 4, 'is_public' => false],
];
$replace = static function (array $rows) use ($service): void {
    $service->replace_entity_relations_for_source(10, 'place', 'atomic_test', $rows, 'fixture:replacement');
};
$seed = static function () use ($wpdb, $table, $expect): void {
    $expect($wpdb->query("DELETE FROM `$table`") !== false, 'Reset temporary fixtures');
    foreach ([[1, 10, 'place', 'atomic_test'], [2, 10, 'person', 'atomic_test'], [3, 10, 'place', 'other_source'], [4, 11, 'place', 'atomic_test']] as [$id, $from, $family, $source]) {
        $expect($wpdb->insert($table, [
            'id' => $id, 'from_entity_id' => $from, 'to_entity_id' => 15,
            'relation_family' => $family, 'source_system' => $source,
            'source_ref' => 'fixture:original', 'note' => 'Original relationship',
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]) === 1, 'Seed original and independently owned relationships');
    }
};
$fail = static function (callable $call, string $message) use ($expect): void {
    $caught = null;
    try { $call(); } catch (RuntimeException $exception) { $caught = $exception; }
    $expect($caught instanceof RuntimeException && str_contains($caught->getMessage(), $message), 'Failure reaches caller: ' . $message);
};
try {
    // Temporary-table shadowing isolates committed tests as well as rollbacks.
    // Other connections continue to see the real table; DROP TEMPORARY cannot drop it.
    $temporary_schema = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
    $expect($wpdb->query($temporary_schema) !== false, 'Create connection-local relation fixtures');
    $created = true;
    $seed();
    $baseline = $read();
    $replace($new_rows);
    $rows = $read();
    $expect(count($rows) === 5, 'Successful replacement stores the complete set');
    $expect(array_slice($rows, 0, 3) === array_slice($baseline, 1), 'Other entity, family and source remain byte-identical');
    $replacement = array_slice($rows, 3);
    $expect(array_map('intval', array_column($replacement, 'to_entity_id')) === [20, 30], 'Both new targets persist');
    $expect($replacement[0]['relation_label'] === 'New' && $replacement[0]['source_field'] === 'subject', 'Existing normalization and source-field fallback preserved');
    $expect((int) $replacement[0]['valid_from_year'] === 1990 && (int) $replacement[0]['confidence'] === 70, 'Year and confidence survive replacement');
    $expect((int) $replacement[1]['position'] === 4 && (int) $replacement[1]['is_public'] === 0, 'Ordering and privacy survive replacement');
    $expect($replacement[0]['edge_key'] !== '' && $replacement[0]['source_ref'] === 'fixture:replacement', 'Edge identity and provenance retained');
    $expect($transaction() === 0, 'Successful standalone call finishes its transaction');
    $replace([]);
    $expect($read() === array_slice($baseline, 1), 'Empty input intentionally clears only the requested scope');
    $replace([]);
    $expect($read() === array_slice($baseline, 1), 'Clearing an empty scope succeeds');
    $replace($new_rows);
    $expect(count($read()) === 5, 'Previously empty scope accepts replacement');

    // Fail before mutation, at delete, after one insert and at commit. An invalid
    // SQL statement makes wpdb return false through its real database error path.
    foreach (['state', 'engine', 'begin', 'delete', 'insert_first', 'insert_second', 'commit', 'php'] as $stage) {
        $seed();
        $before = $read();
        $hits = 0;
        $inserts = 0;
        $filter = static function (string $sql) use ($stage, $table, &$hits, &$inserts): string {
            $is_insert = str_starts_with($sql, "INSERT INTO `$table`");
            if ($is_insert) { ++$inserts; }
            $match = match ($stage) {
                'state', 'engine' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'delete' => str_starts_with($sql, "DELETE FROM `$table`"),
                'insert_first' => $is_insert && $inserts === 1,
                'insert_second', 'php' => $is_insert && $inserts === 2,
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected PHP failure'); }
            if ($stage === 'engine') { return "SELECT 'MyISAM' AS engine, 0 AS in_transaction, 1 AS autocommit"; }
            return 'SELECT iss_graph_test_injected_failure';
        };
        add_filter('query', $filter);
        $fail(static fn() => $replace($new_rows), $stage === 'php' ? 'Injected PHP failure' : 'Graph relation replacement');
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1, $stage . ': intended failure occurred');
        $expect($read() === $before, $stage . ': all original rows restored byte-for-byte');
        $expect($transaction() === 0, $stage . ': no transaction left open');
    }

    // wpdb can reject a row before SQL (e.g. a value longer than its column).
    $bad_rows = $new_rows;
    $bad_rows[1]['relation_label'] = str_repeat('x', 192);
    $fail(static fn() => $replace($bad_rows), 'insert a replacement row');
    $expect($read() === $before && $transaction() === 0, 'Pre-query insert rejection also rolls back the earlier insert');

    foreach (['success', 'insert', 'savepoint', 'release'] as $stage) {
        $seed();
        $before = $read();
        $expect($wpdb->query('START TRANSACTION') !== false, 'Caller begins transaction');
        $expect($wpdb->update($table, ['note' => 'Caller-owned pending change'], ['id' => 2]) === 1, 'Caller has prior work to preserve');
        $pending = $read();
        $hits = 0;
        if ($stage !== 'success') {
            $filter = static function (string $sql) use ($stage, $table, &$hits): string {
                $match = match ($stage) {
                    'insert' => str_starts_with($sql, "INSERT INTO `$table`"),
                    'savepoint' => str_starts_with($sql, 'SAVEPOINT iss_graph_write_'),
                    'release' => str_starts_with($sql, 'RELEASE SAVEPOINT iss_graph_write_'),
                };
                if (!$match || $hits > 0) { return $sql; }
                ++$hits;
                return 'SELECT iss_graph_test_injected_failure';
            };
            add_filter('query', $filter);
            $fail(static fn() => $replace($new_rows), 'Graph relation replacement');
            remove_filter('query', $filter);
            $filter = null;
            $expect($hits === 1 && $read() === $pending, $stage . ': nested failure preserves caller work and old relations');
        } else {
            $replace($new_rows);
            $expect(count($read()) === 5, 'Nested success replaces all rows within caller transaction');
        }
        $expect($transaction() === 1, $stage . ': caller still owns its open transaction');
        $expect($wpdb->query('ROLLBACK') !== false, 'Caller rolls back');
        $expect($read() === $before, $stage . ': outer rollback undoes caller and service work');
    }

    // Autocommit disabled also belongs to the caller, before its first write.
    $expect($wpdb->query('SET autocommit = 0') !== false, 'Caller disables autocommit');
    $replace($new_rows);
    $expect($transaction() === 1, 'No early commit with caller-disabled autocommit');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    $expect($read() === $before, 'Caller-disabled autocommit remains reversible');

    $queries = $wpdb->num_queries;
    $service->replace_entity_relations_for_source(0, 'place', 'atomic_test', $new_rows);
    $service->replace_entity_relations_for_source(10, '', 'atomic_test', $new_rows);
    $service->replace_entity_relations_for_source(10, 'place', '', $new_rows);
    $expect($wpdb->num_queries === $queries, 'Invalid scope retains the existing no-op contract');
    $replace([['to_entity_id' => 0], $new_rows[0]]);
    $expect(count($read()) === 4, 'Rows without a target retain the existing skip contract');

    // The projection facade must propagate a failed relation write to its caller.
    $before_facade = $read();
    $fail(static fn() => iss_graph_replace_entity_projection('atomic_test', 10, ['relations' => ['place' => $bad_rows]]), 'insert a replacement row');
    $expect($read() === $before_facade, 'Projection facade failure preserves previous relation set');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter) { remove_filter('query', $filter); }
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    if ($created) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    $wpdb->suppress_errors($old_suppression);
}
$expect($read() === $original, 'Real relationship rows were never changed');
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success($checks . ' graph relation checks passed; temporary fixtures removed and real rows unchanged.');
