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
$created = []; $error = null; $stage = 'success'; $old_suppression = $wpdb->suppress_errors(true);
$original_user = wp_get_current_user(); $fixture_user = new WP_User(); $fixture_user->ID = 2147478400; $GLOBALS['current_user'] = $fixture_user;
$ids = []; $events = [];
$on_candidate = static function (array $item, array $metadata) use (&$events,$transaction): void { $events[] = ['item' => $item, 'metadata' => $metadata, 'transaction' => $transaction()]; };
add_action('iss_content_editorial_set_archive_candidate', $on_candidate, 10, 2);
$reset = static function () use ($seed,$wpdb,$tables,&$ids,&$events): void {
    $seed(); $events = []; $ids = array_map('intval', $wpdb->get_col("SELECT id FROM {$tables['items']} WHERE set_id = 10 ORDER BY id"));
    $wpdb->update($tables['items'], ['source_id' => 'different-source'], ['set_id' => 20]);
};
$rights = ['attribution' => 'Fixture author', 'license' => 'Test license', 'consent' => '1'];
$args = ['set_id' => 10, 'source_id' => 'New source', 'label' => '<b>New item</b>', 'provenance' => ['path' => 'C:\\source'], 'rights' => $rights, 'retain' => 1, 'retain_reason' => 'Keep', 'decay_at' => '2026-12-01 12:00:00'];
$calls = [
    'add' => static fn() => $service->add_item($args),
    'attach' => static fn() => $service->attach_context(10, 'page', 99, 'SOURCE_material'),
];
// Closures capture the IDs by reference because each fixture reset allocates new rows.
$calls['update'] = static function () use ($service,&$ids,$rights) { return $service->update_item($ids[0], ['status' => 'approved', 'rights' => $rights, 'notes' => 'Reviewed']); };
$calls['move'] = static function () use ($service,&$ids) { return $service->move_items([$ids[0]], 20); };
$calls['promote'] = static function () use ($service,&$ids) { return $service->mark_promoted($ids[0], 'PAGE', 99); };
$calls['candidate'] = static function () use ($service,&$ids) { return $service->mark_archive_candidate($ids[0], ['note' => 'Candidate \\ path']); };
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create fixture'); } $created[] = $table; }
    foreach ($calls as $kind => $call) {
        $stage = $kind . '_success'; $reset(); $baseline = $snapshot(); $sqls = [];
        $result = $with_filter(static function (string $sql) use (&$sqls): string { $sqls[] = $sql; return $sql; }, $call);
        $after = $snapshot(); $audit = end($after['audit']);
        $expect((bool) $result && count($after['audit']) === count($baseline['audit']) + 1 && (int) $audit['actor_id'] === $fixture_user->ID, "$kind stores one required audit with actor");
        $expect($transaction() === 0 && count(array_filter($sqls, static fn(string $sql): bool => $sql === 'COMMIT')) === 1, 'Single standalone transaction');
        $parent_lock = array_search("SELECT * FROM {$tables['sets']} WHERE id = 10 FOR UPDATE", $sqls, true);
        $writes = array_keys(array_filter($sqls, static fn(string $sql): bool => str_starts_with($sql, 'INSERT INTO') || str_starts_with($sql, 'UPDATE `')));
        $expect($parent_lock !== false && $parent_lock < $writes[0], 'Parent locked before any child write');
        if (in_array($kind, ['add','attach'], true)) {
            $expect($call() === $result && $snapshot() === $after, 'Duplicate attachment or item returns existing ID without timestamp or audit');
        }
        if ($kind === 'add') {
            $row = $service->get_item($result);
            $expect($row['label'] === 'New item' && json_decode($row['provenance_json'], true) === $args['provenance'] && $row['decay_at'] === $args['decay_at'] && (int) $row['position'] === 3, 'Existing normalization, provenance, date and next-position contracts');
        }
        if ($kind === 'candidate') {
            $expect(count($events) === 1 && $events[0]['transaction'] === 0 && $events[0]['item']['archive_candidate_status'] === '' && $service->get_item($ids[0])['archive_candidate_status'] === 'candidate', 'Candidate hook receives prior item only after successful standalone commit');
        }
        if (in_array($kind, ['promote','candidate'], true)) { $expect(!$service->delete_set_if_safe(10), 'Protected child still prevents Set deletion'); }
        $stages = ['parent','first_write','ignored_write','audit','ignored_audit','commit'];
        if (in_array($kind, ['add','attach','update','move'], true)) { $stages = array_merge($stages, ['touch','touch_verify']); }
        if (in_array($kind, ['add','attach','move'], true)) { $stages[] = 'position'; }
        if (in_array($kind, ['add','attach'], true)) { $stages[] = 'existing'; }
        else { $stages = array_merge($stages, ['initial_read','item_lock','ownership_change','verify']); }
        foreach ($stages as $stage) {
            $reset(); $baseline = $snapshot(); $hits = 0;
            $filter = static function (string $sql) use ($stage,$kind,$tables,&$hits): string {
                $write_table = $kind === 'attach' ? $tables['links'] : $tables['items'];
                $match = match ($stage) {
                    'parent' => $sql === "SELECT * FROM {$tables['sets']} WHERE id = 10 FOR UPDATE",
                    'first_write','ignored_write' => str_starts_with($sql, (in_array($kind, ['add','attach'], true) ? 'INSERT INTO `' : 'UPDATE `') . $write_table . '`'),
                    'audit','ignored_audit' => str_starts_with($sql, "INSERT INTO `{$tables['audit']}`"),
                    'commit' => $sql === 'COMMIT',
                    'touch' => str_starts_with($sql, "UPDATE `{$tables['sets']}`"),
                    'touch_verify' => $sql === "SELECT * FROM {$tables['sets']} WHERE id = 10",
                    'position' => str_starts_with($sql, 'SELECT COALESCE(MAX(position)'),
                    'existing' => str_contains($sql, "SELECT * FROM $write_table\n"),
                    'initial_read' => str_contains($sql, "FROM {$tables['items']} WHERE id IN (") && !str_ends_with($sql, 'FOR UPDATE'),
                    'item_lock','ownership_change' => str_contains($sql, "FROM {$tables['items']} WHERE id IN (") && str_ends_with($sql, 'FOR UPDATE'),
                    'verify' => str_starts_with($sql, "SELECT * FROM {$tables['items']} WHERE id = "),
                };
                if (!$match || $hits) { return $sql; } ++$hits;
                if ($stage === 'ownership_change') { return str_replace('SELECT *', 'SELECT *, 20 AS set_id', $sql); }
                if (str_starts_with($stage, 'ignored_')) { return "UPDATE {$tables['sets']} SET title = title WHERE id = 0"; }
                return 'SELECT iss_editorial_child_injected_failure';
            };
            if ($stage === 'first_write' || ($stage === 'ignored_write' && in_array($kind, ['add','attach'], true))) { $expect(!$with_filter($filter, $call), 'Rejected first write retains zero/false'); }
            else { $fail(static fn() => $with_filter($filter, $call)); }
            $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0 && !$events, "$kind $stage restores complete state and emits no hook");
            $expect((bool) $call() && count($snapshot()['audit']) === count($baseline['audit']) + 1, 'Retry records exactly one successful operation');
        }
        foreach ([false,true] as $autocommit_off) {
            foreach (['success','audit','release'] as $stage) {
                $reset(); $baseline = $snapshot(); $wpdb->query($autocommit_off ? 'SET autocommit = 0' : 'START TRANSACTION');
                $wpdb->update($tables['sets'], ['summary' => 'Caller pending'], ['id' => 20]); $pending = $snapshot(); $hits = 0; $sqls = [];
                $filter = static function (string $sql) use ($stage,$tables,&$hits,&$sqls): string {
                    $sqls[] = $sql; $match = $stage === 'audit' ? str_starts_with($sql, "INSERT INTO `{$tables['audit']}`") : ($stage === 'release' && str_starts_with($sql, 'RELEASE SAVEPOINT '));
                    if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_editorial_child_nested_failure';
                };
                if ($stage === 'success') { $expect((bool) $with_filter($filter, $call) && count($snapshot()['audit']) === count($pending['audit']) + 1, 'Nested complete operation'); }
                else { $fail(static fn() => $with_filter($filter, $call)); $expect($hits === 1 && $snapshot() === $pending && !$events, 'Nested failure retains caller writes and emits no hook'); }
                $expect($transaction() === 1 && !in_array('COMMIT', $sqls, true) && !in_array('ROLLBACK', $sqls, true) && $service->get_set(20)['summary'] === 'Caller pending', 'No implicit caller commit');
                $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $expect($snapshot() === $baseline, 'Caller rollback restores complete state');
            }
        }
    }
    $stage = 'invalid_and_unassigned'; $reset(); $baseline = $snapshot();
    $expect($service->add_item(['set_id' => 99, 'source_id' => 'orphan']) === 0 && $service->attach_context(99, 'page', 1) === 0 && $service->move_items($ids, 99) === 0 && $service->move_items($ids, -1) === 0 && $snapshot() === $baseline, 'Missing or invalid parent cannot gain orphan rows');
    $expect(!$service->update_item($ids[0], ['status' => 'approved']) && !$service->update_item(0, []) && !$service->mark_promoted(999999, 'page', 99) && !$service->mark_archive_candidate(999999) && $snapshot() === $baseline, 'Rights gate and missing item contracts');
    $id = $service->add_item(['source_id' => 'unassigned']);
    $expect($id > 0 && (int) $service->get_item($id)['set_id'] === 0 && $service->update_item($id, ['notes' => 'Unassigned review']), 'Set zero remains valid unassigned intake');
    $expect($service->move_items([$id], 10) === 1 && $service->move_items([$id], 0) === 1 && (int) $service->get_item($id)['set_id'] === 0, 'Moves into and out of unassigned intake');
    $stage = 'batch_late_failure';
    foreach (['move','status'] as $kind) {
        $reset(); $baseline = $snapshot(); $hits = 0;
        $call = static function () use ($kind,$service,&$ids) { return $kind === 'move' ? $service->move_items($ids, 20) : $service->batch_update_status($ids, 'reviewing'); };
        $filter = static function (string $sql) use ($tables,&$hits): string {
            if (str_starts_with($sql, "INSERT INTO `{$tables['audit']}`") && ++$hits === 2) { return 'SELECT iss_editorial_batch_late_failure'; } return $sql;
        };
        $fail(static fn() => $with_filter($filter, $call));
        $expect($hits === 2 && $snapshot() === $baseline, 'Late failure rolls back earlier batch items, timestamps and audits');
        $expect($call() === 3 && count($snapshot()['audit']) === count($baseline['audit']) + 3, 'Batch retry handles all three items once');
    }
    $stage = 'move_duplicates'; $reset(); $baseline = $snapshot();
    $wpdb->update($tables['items'], ['source_id' => 'fixture-1'], ['set_id' => 20]);
    $expect($service->move_items([$ids[0], $ids[1], 999999, $ids[2]], 20) === 2 && (int) $service->get_item($ids[1])['set_id'] === 10, 'Move retains duplicate-source rejection and missing-item skipping');
    $reset(); $expect($service->move_items([$ids[0], $ids[0]], 20) === 2, 'Repeated input retains count and repositioning');
    $audits = $wpdb->get_col("SELECT payload_json FROM {$tables['audit']} WHERE action='item_moved' ORDER BY id");
    $expect(json_decode($audits[0], true)['from_set_id'] === 10 && json_decode($audits[1], true)['from_set_id'] === 20, 'Repeated-item audit uses its updated parent');
    $stage = 'promotion_history_read'; $reset(); $service->mark_promoted($ids[0], 'page', 99); $baseline = $snapshot();
    $filter = static fn(string $sql): string => str_starts_with($sql, "SELECT payload_json FROM {$tables['audit']}") ? 'SELECT iss_editorial_history_failure' : $sql;
    $fail(static fn() => $with_filter($filter, static fn() => $service->mark_promoted($ids[0], 'page', 100)));
    $expect($snapshot() === $baseline, 'Failed promotion history read cannot silently duplicate or replace promotion');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    remove_action('iss_content_editorial_set_archive_candidate', $on_candidate);
    $GLOBALS['current_user'] = $original_user; $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original && $outside() === $original_outside, 'Real Sets, items, links, audit, options and queue unchanged; temporary fixtures removed');
if ($error !== null) { WP_CLI::error("After {$checks} checks ($stage): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Editorial Set child writes atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
