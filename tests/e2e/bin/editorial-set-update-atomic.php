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
$args = ['title' => ' <b>Updated Set</b> ', 'summary' => "Line one\nLine two \\ path", 'set_role' => 'REVIEW', 'status' => 'APPROVED'];
$calls = ['fields' => static fn(): bool => $service->update_set(10, $args), 'key' => static fn(): bool => $service->update_set_key(10, 'Updated KEY')];
$created = []; $error = null; $stage = 'success'; $old_suppression = $wpdb->suppress_errors(true);
$original_user = wp_get_current_user(); $fixture_user = new WP_User(); $fixture_user->ID = 2147478400; $GLOBALS['current_user'] = $fixture_user;
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create fixture'); } $created[] = $table; }
    foreach ($calls as $kind => $call) {
        $seed(); $baseline = $snapshot(); $sqls = [];
        $expect($with_filter(static function (string $sql) use (&$sqls): string { $sqls[] = $sql; return $sql; }, $call), 'Update returns true');
        $row = $service->get_set(10); $after = $snapshot(); $audit = end($after['audit']); $payload = json_decode($audit['payload_json'], true);
        $expect((int) $row['updated_by'] === $fixture_user->ID && $row['created_at'] === $baseline['sets'][0]['created_at'] && (int) $row['created_by'] === 1, 'Update attribution changes and creation attribution stays');
        $expect($audit['action'] === ($kind === 'fields' ? 'set_updated' : 'set_key_updated') && (int) $audit['set_id'] === 10 && (int) $audit['actor_id'] === $fixture_user->ID && count($after['audit']) === count($baseline['audit']) + 1, 'Exactly one required audit with actor');
        if ($kind === 'fields') {
            $expect($row['title'] === 'Updated Set' && $row['summary'] === $args['summary'] && $row['set_role'] === 'review' && $row['status'] === 'approved', 'Sanitation and backslashes retained');
            $expect(!array_diff_assoc($payload, $row), 'Audit describes stored values');
        } else {
            $expect($row['set_key'] === 'updated-key' && $payload === ['from' => 'set-delete-10', 'to' => 'updated-key'], 'Normalized key and exact change audit');
            $expect($service->update_set_key(10, 'Updated KEY') && $snapshot() === $after, 'Unchanged key succeeds without extra audit');
        }
        $expect($after['items'] === $baseline['items'] && $after['links'] === $baseline['links'] && $after['sets'][1] === $baseline['sets'][1], 'Children and unrelated Set retained');
        $lock = array_search("SELECT * FROM {$tables['sets']} WHERE id = 10 FOR UPDATE", $sqls, true);
        $writes = array_keys(array_filter($sqls, static fn(string $sql): bool => str_starts_with($sql, "UPDATE `{$tables['sets']}`")));
        $expect($lock !== false && $lock < $writes[0] && count(array_filter($sqls, static fn(string $sql): bool => $sql === 'COMMIT')) === 1 && $transaction() === 0, 'Fresh Set locked before update and one standalone commit');
        $seed(); $baseline = $snapshot();
        $expect(!$service->update_set(-1, $args) && !$service->update_set(99, $args) && !$service->update_set_key(99, 'missing') && !$service->update_set_key(10, '***') && !$service->update_set_key(10, 'set-delete-20') && $snapshot() === $baseline, 'Missing Set, empty key and conflicting key return false unchanged');
        foreach (['state','sets_engine','audit_engine','begin','set_read','key_read','write','ignored_write','verify','audit','ignored_audit','commit','php'] as $stage) {
            if ($stage === 'key_read' && $kind !== 'key') { continue; }
            $seed(); $baseline = $snapshot(); $hits = 0;
            $filter = static function (string $sql) use ($stage,$tables,&$hits): string {
                $match = match ($stage) {
                    'state','sets_engine','audit_engine' => str_contains($sql, 'ENGINE AS engine'),
                    'begin' => $sql === 'START TRANSACTION',
                    'set_read' => $sql === "SELECT * FROM {$tables['sets']} WHERE id = 10 FOR UPDATE",
                    'key_read' => str_contains($sql, "FROM {$tables['sets']} WHERE set_key = "),
                    'write','ignored_write' => str_starts_with($sql, "UPDATE `{$tables['sets']}`"),
                    'verify' => $sql === "SELECT * FROM {$tables['sets']} WHERE id = 10",
                    'audit','ignored_audit','php' => str_starts_with($sql, "INSERT INTO `{$tables['audit']}`"),
                    'commit' => $sql === 'COMMIT',
                };
                if (!$match || $hits) { return $sql; } ++$hits;
                if ($stage === 'php') { throw new Error('Injected throwable after update'); }
                if (str_ends_with($stage, '_engine')) { $table = $tables[substr($stage, 0, -7)]; return str_replace('ENGINE AS engine', "CASE WHEN TABLE_NAME = '$table' THEN 'MyISAM' ELSE ENGINE END AS engine", $sql); }
                if (str_starts_with($stage, 'ignored_')) { return "UPDATE {$tables['sets']} SET title = title WHERE id = 0"; }
                return 'SELECT iss_editorial_set_update_injected_failure';
            };
            if ($stage === 'write') { $expect(!$with_filter($filter, $call), 'First update rejection preserves false'); }
            else { $fail(static fn() => $with_filter($filter, $call)); }
            $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$kind $stage retains all rows");
            $expect($call() && count($snapshot()['audit']) === count($baseline['audit']) + 1, 'Retry succeeds with exactly one audit');
        }
        foreach ([false,true] as $autocommit_off) {
            foreach (['success','write','audit','savepoint','release'] as $stage) {
                $seed(); $baseline = $snapshot(); $wpdb->query($autocommit_off ? 'SET autocommit = 0' : 'START TRANSACTION');
                $wpdb->update($tables['sets'], ['summary' => 'Caller pending'], ['id' => 20]); $pending = $snapshot(); $hits = 0; $sqls = [];
                $filter = static function (string $sql) use ($stage,$tables,&$hits,&$sqls): string {
                    $sqls[] = $sql;
                    $match = match ($stage) { 'write' => str_starts_with($sql, "UPDATE `{$tables['sets']}`"), 'audit' => str_starts_with($sql, "INSERT INTO `{$tables['audit']}`"), 'savepoint' => str_starts_with($sql, 'SAVEPOINT '), 'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '), default => false };
                    if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_editorial_set_update_nested_failure';
                };
                if ($stage === 'success') { $expect($with_filter($filter, $call) && count($snapshot()['audit']) === count($pending['audit']) + 1, 'Nested update records complete pair'); }
                elseif ($stage === 'write') { $expect(!$with_filter($filter, $call) && $snapshot() === $pending, 'Rejected nested update retains false and preceding writes'); }
                else { $fail(static fn() => $with_filter($filter, $call)); $expect($hits === 1 && $snapshot() === $pending, 'Late failure preserves preceding caller writes'); }
                $expect($transaction() === 1 && !in_array('COMMIT', $sqls, true) && !in_array('ROLLBACK', $sqls, true) && $service->get_set(20)['summary'] === 'Caller pending', 'Caller retains final decision');
                $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $expect($snapshot() === $baseline, 'Caller rollback restores all rows');
            }
        }
    }
    $stage = 'defaults_and_zero_update'; $seed();
    $expect($service->update_set(10, ['title' => ' ', 'set_role' => 'bad', 'status' => 'bad']), 'Default update succeeds'); $row = $service->get_set(10);
    $expect($row['title'] === 'Private Set 10' && $row['summary'] === '' && $row['set_role'] === 'intake' && $row['status'] === 'working', 'Empty title preserves previous title and field defaults retained');
    $baseline = $snapshot(); $hits = 0;
    $filter = static function (string $sql) use ($tables,&$hits): string {
        if (str_starts_with($sql, "UPDATE `{$tables['sets']}`")) { ++$hits; return "UPDATE {$tables['sets']} SET title = title WHERE id = 10"; }
        if ($sql === "SELECT * FROM {$tables['sets']} WHERE id = 10") { return str_replace('SELECT *', 'SELECT *, UTC_TIMESTAMP() AS updated_at', $sql); }
        return $sql;
    };
    $expect($with_filter($filter, static fn(): bool => $service->update_set(10, [])) && $hits === 1 && count($snapshot()['audit']) === count($baseline['audit']) + 1, 'Valid zero-row unchanged update still records its audit');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    $GLOBALS['current_user'] = $original_user; $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original && $outside() === $original_outside, 'Real Sets, items, links, audit, options and queue unchanged; temporary fixtures removed');
if ($error !== null) { WP_CLI::error("After {$checks} checks ($stage): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Editorial Set update atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
