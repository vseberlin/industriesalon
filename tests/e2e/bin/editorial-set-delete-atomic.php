<?php
/** WP-CLI with cron disabled: all Set writes use connection-local real-schema temporary tables. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated fixtures and intentional SQL failures.
global $wpdb;
$service = iss_content_editorial_sets_service(); $checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and process-local caches');
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$tables = ['sets' => $service->get_sets_table_name(), 'items' => $service->get_items_table_name(), 'links' => $service->get_links_table_name(), 'audit' => $service->get_audit_table_name()];
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A);
$snapshot = static fn(): array => array_map($read, $tables);
$original = $snapshot();
$outside = static fn(): array => ['options' => $wpdb->get_results("SELECT * FROM {$wpdb->options} ORDER BY option_id", ARRAY_A), 'queue' => $read(iss_graph_get_service()->get_dirty_queue_table_name())];
$original_outside = $outside(); $schemas = [];
foreach ($tables as $table) {
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    if (!is_array($schema) || !str_starts_with($schema[1], 'CREATE TABLE ')) { throw new RuntimeException('Cannot read fixture schema'); }
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$seed = static function (int $count = 3, bool $links = true) use ($wpdb,$tables): void {
    foreach ($tables as $table) { if ($wpdb->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Cannot reset fixtures'); } }
    $time = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
    foreach ([10,20] as $id) {
        if ($wpdb->insert($tables['sets'], $time + ['id' => $id, 'set_key' => 'set-delete-' . $id, 'title' => 'Private Set ' . $id, 'summary' => 'Fixture', 'created_by' => 1, 'updated_by' => 1]) !== 1) { throw new RuntimeException('Cannot seed Set'); }
        for ($n = 0; $n < ($id === 10 ? $count : 1); ++$n) {
            if ($wpdb->insert($tables['items'], $time + ['set_id' => $id, 'kind' => 'wp_media', 'source' => 'wp-media', 'source_id' => 'fixture-' . $n, 'status' => 'pending', 'label' => 'Private item', 'provenance_json' => '{"source":"fixture"}', 'rights_json' => '{}', 'notes' => 'Preserve on failure', 'retain_reason' => '', 'position' => $n]) !== 1) { throw new RuntimeException('Cannot seed item'); }
        }
        if ($links || $id === 20) {
            foreach ([1,2] as $context) {
                if ($wpdb->insert($tables['links'], $time + ['set_id' => $id, 'context_type' => 'page', 'context_id' => $context, 'link_role' => 'source_material', 'position' => $context]) !== 1) { throw new RuntimeException('Cannot seed link'); }
            }
        }
        if ($wpdb->insert($tables['audit'], ['set_id' => $id, 'item_id' => 0, 'action' => 'set_created', 'message' => 'Retained history', 'payload_json' => '{}', 'created_at' => $time['created_at']]) !== 1) { throw new RuntimeException('Cannot seed audit'); }
    }
};
$delete = static fn(): bool => $service->delete_set_if_safe(10);
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): Throwable { $error = null; try { $call(); } catch (Throwable $caught) { $error = $caught; } $expect($error !== null, 'Database failure reaches caller'); return $error; };
$expected_delete = static function (array $before): array {
    foreach (['sets','items','links'] as $table) { $before[$table] = array_values(array_filter($before[$table], static fn(array $row): bool => (int) $row[$table === 'sets' ? 'id' : 'set_id'] !== 10)); }
    return $before;
};
$created = []; $error = null; $stage = 'success'; $old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create fixture'); } $created[] = $table; }
    foreach ([[3,true],[0,true],[3,false],[0,false],[125,true]] as [$count,$links]) {
        $seed($count,$links); $baseline = $snapshot(); $sqls = [];
        $expect($with_filter(static function (string $sql) use (&$sqls): string { $sqls[] = $sql; return $sql; }, $delete), 'Eligible Set deletion succeeds');
        $expect($snapshot() === $expected_delete($baseline), 'Only target Set, items and links removed; all audit and unrelated rows retained');
        $expect($transaction() === 0 && count(array_filter($sqls, static fn(string $sql): bool => $sql === 'COMMIT')) === 1, 'One standalone commit');
        $locks = array_values(array_filter($sqls, static fn(string $sql): bool => str_contains($sql, 'FOR UPDATE')));
        $expect(count($locks) === 3 && str_contains($locks[0], $tables['sets']) && str_contains($locks[1], $tables['items']) && str_contains($locks[2], $tables['links']), 'Locks Set, all items and context links before deletion');
        $expect(!$delete() && $snapshot() === $expected_delete($baseline), 'Repeated missing Set deletion stays false without changing audit history');
    }
    foreach ([['status' => 'promoted'], ['status' => 'PROMOTED'], ['archive_candidate_status' => 'candidate'], ['archive_candidate_status' => 'accepted']] as $protection) {
        $stage = 'protected'; $seed(125); $last = (int) $wpdb->get_var("SELECT MAX(id) FROM {$tables['items']} WHERE set_id = 10");
        $wpdb->update($tables['items'], $protection, ['id' => $last]); $baseline = $snapshot();
        $expect(!$delete() && $snapshot() === $baseline && $transaction() === 0, 'SQL protection semantics retained beyond first UI page');
    }
    $stage = 'invalid_missing'; $seed(); $baseline = $snapshot(); $queries = $wpdb->num_queries;
    $expect(!$service->delete_set_if_safe(0) && !$service->delete_set_if_safe(-10) && $wpdb->num_queries === $queries, 'Invalid IDs skip database work');
    $expect(!$service->delete_set_if_safe(999999) && $snapshot() === $baseline, 'Missing positive Set leaves all rows unchanged');

    foreach (['state','missing_engine_row','sets_engine','items_engine','links_engine','missing_state','begin','set_read','item_read','link_read','items_delete','links_delete','sets_delete','ignored_items','ignored_links','ignored_set','partial_items','partial_links','commit','php'] as $stage) {
        $seed(); $baseline = $snapshot(); $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,&$hits): string {
            $flat = preg_replace('/\s+/', ' ', $sql);
            $match = match ($stage) {
                'state','missing_engine_row','sets_engine','items_engine','links_engine','missing_state' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'set_read' => str_contains($flat, "SELECT id FROM {$tables['sets']} WHERE id = 10 FOR UPDATE"),
                'item_read' => str_contains($sql, 'AS is_protected'),
                'link_read' => str_contains($flat, "SELECT id FROM {$tables['links']} WHERE set_id = 10 ORDER BY id ASC FOR UPDATE"),
                'items_delete','ignored_items','partial_items' => str_starts_with($sql, "DELETE FROM `{$tables['items']}`"),
                'links_delete','ignored_links','partial_links','php' => str_starts_with($sql, "DELETE FROM `{$tables['links']}`"),
                'sets_delete','ignored_set' => str_starts_with($sql, "DELETE FROM `{$tables['sets']}`"),
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits) { return $sql; } ++$hits;
            if ($stage === 'php') { throw new Error('Injected failure after item deletion'); }
            if (str_ends_with($stage, '_engine')) { $table = $tables[substr($stage, 0, -7)]; return str_replace('ENGINE AS engine', "CASE WHEN TABLE_NAME = '$table' THEN 'MyISAM' ELSE ENGINE END AS engine", $sql); }
            if ($stage === 'missing_engine_row') { return $sql . ' LIMIT 2'; }
            if ($stage === 'missing_state') { return str_replace('@@SESSION.autocommit AS autocommit', 'NULL AS autocommit', $sql); }
            if (str_starts_with($stage, 'ignored_')) { return str_replace('= 10', '= 0', $sql); }
            if (str_starts_with($stage, 'partial_')) { return $sql . ' LIMIT 1'; }
            return 'SELECT iss_editorial_set_delete_injected_failure';
        };
        $fail(static fn() => $with_filter($filter, $delete));
        $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage restores all target and unrelated rows");
        $expect($service->get_set(10) !== null && count($service->list_items(['set_id' => 10])['items']) === 3 && count($service->get_links_for_set(10)) === 2, 'Owner readers see complete restored state');
        $expect($delete() && $snapshot() === $expected_delete($baseline), 'Retry completes only the intended deletion');
    }
    foreach ([false,true] as $autocommit_off) {
        foreach (['success','protected','missing','late','savepoint','release'] as $stage) {
            $seed();
            if ($stage === 'protected') { $wpdb->query("UPDATE {$tables['items']} SET status = 'promoted' WHERE set_id = 10"); }
            if ($stage === 'missing') { $wpdb->delete($tables['sets'], ['id' => 10], ['%d']); }
            $baseline = $snapshot(); $wpdb->query($autocommit_off ? 'SET autocommit = 0' : 'START TRANSACTION');
            $wpdb->update($tables['sets'], ['summary' => 'Caller pending'], ['id' => 20]); $pending = $snapshot(); $hits = 0; $sqls = [];
            $filter = static function (string $sql) use ($stage,$tables,&$hits,&$sqls): string {
                $sqls[] = $sql;
                $match = match ($stage) { 'late' => str_starts_with($sql, "DELETE FROM `{$tables['sets']}`"), 'savepoint' => str_starts_with($sql, 'SAVEPOINT '), 'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '), default => false };
                if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_editorial_set_nested_failure';
            };
            if (in_array($stage, ['success','protected','missing'], true)) {
                $expect($with_filter($filter, $delete) === ($stage === 'success'), 'Nested business result retained');
                $expect($snapshot() === ($stage === 'success' ? $expected_delete($pending) : $pending), 'Nested call retains earlier caller work and scope');
            } else {
                $fail(static fn() => $with_filter($filter, $delete)); $expect($hits === 1 && $snapshot() === $pending, 'Nested failure restores own changes only');
            }
            $expect($transaction() === 1 && !in_array('COMMIT', $sqls, true) && !in_array('ROLLBACK', $sqls, true), 'Caller owns final transaction decision');
            $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
            $expect($snapshot() === $baseline && $service->get_set(20)['summary'] === 'Fixture', 'Caller rollback restores complete before-image');
        }
    }
    $stage = 'idle_autocommit_off'; $seed(); $baseline = $snapshot(); $wpdb->query('SET autocommit = 0');
    $expect($transaction() === 0 && $delete() && $transaction() === 1, 'Idle autocommit-off caller retains pending deletion');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $expect($snapshot() === $baseline, 'Idle autocommit-off rollback restores rows');
    $stage = 'caller_commit'; $seed(); $baseline = $snapshot(); $wpdb->query('START TRANSACTION'); $expect($delete(), 'Caller may choose to commit deletion'); $wpdb->query('COMMIT');
    $expect($snapshot() === $expected_delete($baseline), 'Caller commit retains audit and unrelated rows');

    foreach (['rollback','rollback_savepoint','rollback_release'] as $stage) {
        $seed(); $baseline = $snapshot(); $nested = $stage !== 'rollback'; if ($nested) { $wpdb->query('START TRANSACTION'); }
        $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,&$hits): string {
            $late = str_starts_with($sql, "DELETE FROM `{$tables['sets']}`");
            $cleanup = match ($stage) { 'rollback' => $sql === 'ROLLBACK', 'rollback_savepoint' => str_starts_with($sql, 'ROLLBACK TO SAVEPOINT '), 'rollback_release' => str_starts_with($sql, 'RELEASE SAVEPOINT ') };
            if (!$late && !$cleanup) { return $sql; } ++$hits; return 'SELECT iss_editorial_set_rollback_failure';
        };
        $caught = $fail(static fn() => $with_filter($filter, $delete));
        $expect($hits === 2 && $caught->getPrevious() !== null && str_contains($caught->getMessage(), $stage === 'rollback_release' ? 'release its savepoint' : 'rollback could not be confirmed'), 'Unconfirmed cleanup reported with original failure');
        $wpdb->query('ROLLBACK'); $expect($snapshot() === $baseline, 'Explicit recovery restores fixture after injected rollback failure');
    }
    $stage = 'owner_apis'; $seed(); $baseline = $snapshot();
    $set_id = $service->create_set(['set_key' => 'owner-api-set', 'title' => 'Owner API Set']);
    $item = $service->add_item(['set_id' => $set_id, 'source_id' => 'owner-fixture', 'label' => 'Private source']);
    $link = $service->attach_context($set_id, 'page', 123456, 'source_material');
    $expect($set_id > 0 && $item > 0 && $link > 0, 'Existing owner APIs create isolated Set, item and link');
    $expect($service->mark_promoted($item, 'page', 123456), 'Existing owner promotion API succeeds');
    $pending = $snapshot(); $expect(!$service->delete_set_if_safe($set_id) && $snapshot() === $pending, 'Promoted owner item remains protected');
    $wpdb->update($tables['items'], ['status' => 'pending', 'archive_candidate_status' => 'candidate'], ['id' => $item]);
    $pending = $snapshot(); $expect(!$service->delete_set_if_safe($set_id) && $snapshot() === $pending, 'Candidate owner item remains protected');
    $wpdb->update($tables['items'], ['archive_candidate_status' => ''], ['id' => $item]);
    $history = $read($tables['audit']); $expect($service->delete_set_if_safe($set_id), 'Eligible owner fixture can be removed');
    $expect(array_diff_key($snapshot(), ['audit' => true]) === array_diff_key($baseline, ['audit' => true]) && $read($tables['audit']) === $history, 'Owner API fixture deletion retains history and pre-existing Sets');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original && $outside() === $original_outside, 'Real Sets, items, links, audit, options and queue unchanged; temporary fixtures removed');
if ($error !== null) { WP_CLI::error("After {$checks} checks ($stage): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Editorial Set deletion atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
