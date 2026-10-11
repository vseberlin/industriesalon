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
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): Throwable { $error = null; try { $call(); } catch (Throwable $caught) { $error = $caught; } $expect($error !== null, 'Failure reaches caller without a successful ID'); return $error; };
$args = ['set_key' => 'Created KEY', 'title' => ' <b>Created Set</b> ', 'summary' => "Line one\nLine two \\ path", 'set_role' => 'REVIEW', 'status' => 'APPROVED'];
$create = static fn(): int => $service->create_set($args);
$created = []; $error = null; $stage = 'success'; $old_suppression = $wpdb->suppress_errors(true);
$original_user = wp_get_current_user(); $fixture_user = new WP_User(); $fixture_user->ID = 2147478400; $GLOBALS['current_user'] = $fixture_user;
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create fixture'); } $created[] = $table; }
    $seed(); $baseline = $snapshot(); $sqls = [];
    $id = $with_filter(static function (string $sql) use (&$sqls): string { $sqls[] = $sql; return $sql; }, $create);
    $row = $service->get_set($id); $history = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$tables['audit']} WHERE set_id = %d", $id), ARRAY_A);
    $expect($id > 0 && $row['set_key'] === 'created-key' && $row['title'] === 'Created Set', 'Positive created ID and existing key/title normalization retained');
    $expect($row['summary'] === $args['summary'] && $row['set_role'] === 'review' && $row['status'] === 'approved', 'Summary, backslashes and role/status normalization retained');
    $expect((int) $row['created_by'] === $fixture_user->ID && (int) $row['updated_by'] === $fixture_user->ID && $row['created_at'] === $row['updated_at'], 'Actor and creation/update timestamps retained');
    $expect(count($history) === 1 && $history[0]['action'] === 'set_created' && (int) $history[0]['item_id'] === 0 && (int) $history[0]['actor_id'] === $fixture_user->ID, 'One audit row belongs to the created Set and actor');
    $payload = json_decode($history[0]['payload_json'], true);
    $expect($payload == array_intersect_key($row, $payload) && $payload['summary'] === $args['summary'], 'Audit payload describes the stored Set without lost escapes');
    $expect(count(array_filter($sqls, static fn(string $sql): bool => $sql === 'COMMIT')) === 1 && $transaction() === 0, 'Set and required audit share one standalone commit');
    $expect($snapshot()['items'] === $baseline['items'] && $snapshot()['links'] === $baseline['links'] && array_slice($snapshot()['sets'], 0, 2) === $baseline['sets'] && array_slice($snapshot()['audit'], 0, 2) === $baseline['audit'], 'Unrelated Sets, children and earlier audit retained');
    $after = $snapshot(); $expect($create() === 0 && $snapshot() === $after && $transaction() === 0, 'Duplicate explicit key returns zero without a second audit');

    $stage = 'defaults_and_generated_keys'; $seed();
    $first = $service->create_set(['title' => 'Generated title']); $second = $service->create_set(['title' => 'Generated title']); $third = $service->create_set(['title' => 'Generated title', 'set_key' => '***']);
    $expect(array_map(static fn(int $id): string => $service->get_set($id)['set_key'], [$first,$second,$third]) === ['generated-title','generated-title-2','generated-title-3'], 'Generated keys retain collision suffixes and invalid explicit key fallback');
    $empty = $service->create_set(['set_role' => 'unknown', 'status' => 'unknown']); $row = $service->get_set($empty);
    $expect($row['title'] === __('Untitled set', 'iss-content-model') && preg_match('/^set-\d{8}-\d{6}(?:-\d+)?$/', $row['set_key']) === 1 && $row['set_role'] === 'intake' && $row['status'] === 'working', 'Empty title and invalid role/status defaults retained');
    $baseline = $snapshot(); $expect($service->create_set(['set_key' => str_repeat('x', 192)]) === 0 && $snapshot() === $baseline, 'Rejected oversized key returns zero without audit or partial Set');

    foreach (['state','sets_engine','audit_engine','missing_engine_row','missing_state','begin','key_read','later_key_read','set_insert','ignored_set_insert','audit_insert','ignored_audit_insert','commit','php'] as $stage) {
        $seed();
        if ($stage === 'later_key_read') { $service->create_set(['set_key' => 'created-set']); }
        $baseline = $snapshot(); $hits = 0; $seen_reads = 0;
        $call = in_array($stage, ['key_read','later_key_read'], true) ? static fn(): int => $service->create_set(['title' => 'Created Set']) : $create;
        $filter = static function (string $sql) use ($stage,$tables,&$hits,&$seen_reads): string {
            $key_read = str_contains($sql, "FROM {$tables['sets']} WHERE set_key = ");
            if ($key_read) { ++$seen_reads; }
            $match = match ($stage) {
                'state','sets_engine','audit_engine','missing_engine_row','missing_state' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'key_read' => $key_read, 'later_key_read' => $key_read && $seen_reads === 2,
                'set_insert','ignored_set_insert' => str_starts_with($sql, "INSERT INTO `{$tables['sets']}`"),
                'audit_insert','ignored_audit_insert','php' => str_starts_with($sql, "INSERT INTO `{$tables['audit']}`"),
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits) { return $sql; } ++$hits;
            if ($stage === 'php') { throw new Error('Injected audit throwable after Set insert'); }
            if (str_ends_with($stage, '_engine')) { $table = $tables[substr($stage, 0, -7)]; return str_replace('ENGINE AS engine', "CASE WHEN TABLE_NAME = '$table' THEN 'MyISAM' ELSE ENGINE END AS engine", $sql); }
            if ($stage === 'missing_engine_row') { return $sql . ' LIMIT 1'; }
            if ($stage === 'missing_state') { return str_replace('@@SESSION.autocommit AS autocommit', 'NULL AS autocommit', $sql); }
            if (str_starts_with($stage, 'ignored_')) { return "UPDATE {$tables['sets']} SET title = title WHERE id = 0"; }
            return 'SELECT iss_editorial_set_create_injected_failure';
        };
        if (in_array($stage, ['set_insert','ignored_set_insert'], true)) { $expect($with_filter($filter, $call) === 0, 'First insert rejection retains zero result'); }
        else { $fail(static fn() => $with_filter($filter, $call)); }
        $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage leaves no partial Set or audit");
        $retry = $call(); $expect($retry > 0 && count($snapshot()['sets']) === count($baseline['sets']) + 1 && count($snapshot()['audit']) === count($baseline['audit']) + 1, 'Retry creates exactly one Set and one audit');
        $expect($snapshot()['items'] === $baseline['items'] && $snapshot()['links'] === $baseline['links'], 'Retry never changes existing child ownership');
    }
    foreach ([false,true] as $autocommit_off) {
        foreach (['success','duplicate','set_insert','audit_insert','savepoint','release'] as $stage) {
            $seed(); if ($stage === 'duplicate') { $create(); } $baseline = $snapshot();
            $wpdb->query($autocommit_off ? 'SET autocommit = 0' : 'START TRANSACTION');
            $wpdb->update($tables['sets'], ['summary' => 'Caller pending'], ['id' => 20]); $pending = $snapshot(); $hits = 0; $sqls = [];
            $filter = static function (string $sql) use ($stage,$tables,&$hits,&$sqls): string {
                $sqls[] = $sql;
                $match = match ($stage) { 'set_insert' => str_starts_with($sql, "INSERT INTO `{$tables['sets']}`"), 'audit_insert' => str_starts_with($sql, "INSERT INTO `{$tables['audit']}`"), 'savepoint' => str_starts_with($sql, 'SAVEPOINT '), 'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '), default => false };
                if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_editorial_set_create_nested_failure';
            };
            if (in_array($stage, ['success','duplicate','set_insert'], true)) {
                $id = $with_filter($filter, $create); $expect($stage === 'success' ? $id > 0 : $id === 0, 'Nested creation preserves success/rejection return contract');
                $expect($stage === 'success' ? count($snapshot()['sets']) === count($pending['sets']) + 1 && count($snapshot()['audit']) === count($pending['audit']) + 1 : $snapshot() === $pending, 'Nested result retains complete pair or unchanged pending state');
            } else {
                $fail(static fn() => $with_filter($filter, $create)); $expect($hits === 1 && $snapshot() === $pending, 'Nested creation failure preserves preceding caller writes');
            }
            $expect($transaction() === 1 && !in_array('COMMIT', $sqls, true) && !in_array('ROLLBACK', $sqls, true) && $service->get_set(20)['summary'] === 'Caller pending', 'Caller retains final decision and earlier work');
            $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $expect($snapshot() === $baseline, 'Caller rollback removes pending pair and restores earlier work');
        }
    }
    $stage = 'idle_autocommit_off'; $seed(); $baseline = $snapshot(); $wpdb->query('SET autocommit = 0');
    $expect($transaction() === 0 && $create() > 0 && $transaction() === 1, 'Idle autocommit-off caller retains pending pair');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $expect($snapshot() === $baseline, 'Idle caller rollback removes both rows');
    $stage = 'caller_commit'; $seed(); $baseline = $snapshot(); $wpdb->query('START TRANSACTION'); $id = $create(); $wpdb->query('COMMIT');
    $expect($id > 0 && count($snapshot()['sets']) === count($baseline['sets']) + 1 && count($snapshot()['audit']) === count($baseline['audit']) + 1, 'Caller commit persists complete Set and audit pair');
    foreach (['rollback','rollback_savepoint','rollback_release'] as $stage) {
        $seed(); $baseline = $snapshot(); if ($stage !== 'rollback') { $wpdb->query('START TRANSACTION'); } $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,&$hits): string {
            $late = str_starts_with($sql, "INSERT INTO `{$tables['audit']}`");
            $cleanup = match ($stage) { 'rollback' => $sql === 'ROLLBACK', 'rollback_savepoint' => str_starts_with($sql, 'ROLLBACK TO SAVEPOINT '), 'rollback_release' => str_starts_with($sql, 'RELEASE SAVEPOINT ') };
            if (!$late && !$cleanup) { return $sql; } ++$hits; return 'SELECT iss_editorial_set_create_cleanup_failure';
        };
        $caught = $fail(static fn() => $with_filter($filter, $create));
        $expect($hits === 2 && $caught->getPrevious() !== null && str_contains($caught->getMessage(), $stage === 'rollback_release' ? 'release its savepoint' : 'rollback could not be confirmed'), 'Cleanup failure reports original cause');
        $wpdb->query('ROLLBACK'); $expect($snapshot() === $baseline, 'Explicit fixture recovery restores data after injected cleanup failure');
    }
    $stage = 'audit_writer'; $seed(); $baseline = $snapshot();
    $expect($service->record_audit(10, 0, 'manual_note', 'Test note', ['path' => 'C:\\source']) === true, 'Existing audit writer reports successful insert');
    $history = $snapshot(); $filter = static fn(string $sql): string => str_starts_with($sql, "INSERT INTO `{$tables['audit']}`") ? 'SELECT iss_editorial_audit_failure' : $sql;
    $expect($with_filter($filter, static fn(): bool => $service->record_audit(10, 0, 'manual_note', 'Rejected')) === false && $snapshot() === $history, 'Audit writer reports failure without changing other callers to exceptions');
    $stage = 'rest_insert_rejection'; $seed(); $baseline = $snapshot(); $request = new WP_REST_Request('POST', '/iss-content/v1/sets'); $request->set_param('title', 'REST fixture');
    $filter = static fn(string $sql): string => str_starts_with($sql, "INSERT INTO `{$tables['sets']}`") ? 'SELECT iss_editorial_rest_insert_failure' : $sql;
    $response = $with_filter($filter, static fn() => iss_content_editorial_sets_rest_create_set($request));
    $expect(is_wp_error($response) && $response->get_error_code() === 'iss_content_set_create_failed' && $snapshot() === $baseline, 'Existing REST callback retains first-insert failure response');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    $GLOBALS['current_user'] = $original_user; $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original && $outside() === $original_outside, 'Real Sets, items, links, audit, options and queue unchanged; temporary fixtures removed');
if ($error !== null) { WP_CLI::error("After {$checks} checks ($stage): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Editorial Set creation atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
