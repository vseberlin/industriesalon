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
$created = []; $error = null; $stage = 'setup'; $old_suppression = $wpdb->suppress_errors(true); $original_user = wp_get_current_user();
$fixture_root = (string) getenv('ISS_TEST_FILE_ROOT');
$expect($fixture_root !== '' && is_dir($fixture_root) && is_writable($fixture_root), 'Explicit writable checkout fixture mount required');
$root = $fixture_root . '/refactor-files-' . bin2hex(random_bytes(5));
$storage_root = static fn(): string => $root;
add_filter('iss_content_editorial_sets_event_drop_storage_root', $storage_root);
try {
    $expect(mkdir($root, 0700) && mkdir($root . '/incoming') && mkdir($root . '/rejected') && mkdir($root . '/accepted'), 'Fixture files stay inside the explicit fixture mount');
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create fixture'); } $created[] = $table; }
    foreach (['write','audit','integration_audit'] as $stage) {
        foreach (glob($root . '/*/*') ?: [] as $path) { unlink($path); }
        $seed(); $source = $root . '/incoming/source.jpg'; $collision = $root . '/rejected/source.jpg';
        file_put_contents($source, 'Original fixture bytes'); file_put_contents($collision, 'Unrelated collision');
        $hash = hash_file('sha256', $source);
        $id = $service->add_item(['set_id' => 10, 'kind' => 'external_upload', 'source' => 'event-drop', 'source_id' => 'source.jpg', 'provenance' => ['stored_name' => 'source.jpg', 'path' => $source, 'sha256' => $hash, 'storage_state' => 'incoming']]);
        $expect($id > 0, 'Private intake fixture created'); $item = $service->get_item($id); $baseline = $snapshot(); $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,&$hits): string {
            $match = $stage === 'write' ? str_starts_with($sql, "UPDATE `{$tables['items']}`") : (str_starts_with($sql, "INSERT INTO `{$tables['audit']}`") && str_contains($sql, $stage === 'audit' ? "'item_updated'" : "'event_drop_reject'"));
            if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_editorial_file_recovery_failure';
        };
        $call = static fn() => iss_content_editorial_sets_event_drop_moderate_item($id, 'reject');
        if ($stage === 'write') { $expect(!$with_filter($filter, $call), 'First database rejection retains false'); }
        else { $fail(static fn() => $with_filter($filter, $call)); }
        $expect($hits === 1 && $snapshot() === $baseline, 'Both audit rows and item state roll back on failure');
        $moved = iss_content_editorial_sets_event_drop_resolve_item_path($item);
        $expect($moved !== '' && dirname($moved) === $root . '/rejected' && $moved !== $collision && hash_file('sha256', $moved) === $hash && !file_exists($source), 'Failed database save retains moved bytes at a rediscoverable collision path');
        $expect(file_get_contents($collision) === 'Unrelated collision' && count(glob($root . '/*/*')) === 2, 'Unrelated colliding file retained and no extra copies');
        $expect($call() && count($snapshot()['audit']) === count($baseline['audit']) + 2, 'Retry completes item and both audit rows once');
        $updated = $service->get_item($id); $provenance = json_decode($updated['provenance_json'], true);
        $expect($updated['status'] === 'rejected' && $provenance['path'] === $moved && $provenance['storage_state'] === 'rejected', 'Retry records the recovered path and moderation state');
        $expect(iss_content_editorial_sets_event_drop_moderate_item($id, 'restore'), 'Restore succeeds from recovered path');
        $restored = $service->get_item($id); $path = iss_content_editorial_sets_event_drop_resolve_item_path($restored);
        $expect($restored['status'] === 'pending' && dirname($path) === $root . '/incoming' && hash_file('sha256', $path) === $hash, 'Restore retains original bytes and incoming state');
    }
    $stage = 'wrong_identity'; $item = $service->get_item($id); $provenance = json_decode($item['provenance_json'], true); $provenance['sha256'] = str_repeat('0', 64); $item['provenance_json'] = wp_json_encode($provenance);
    $expect(iss_content_editorial_sets_event_drop_resolve_item_path($item) === '' && iss_content_editorial_sets_event_drop_move_item_file($item, 'rejected') === '', 'Known hash mismatch cannot resolve or move unrelated bytes');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    foreach (glob($root . '/*/*') ?: [] as $path) { unlink($path); }
    foreach (glob($root . '/*') ?: [] as $path) { rmdir($path); }
    if (is_dir($root)) { rmdir($root); } remove_filter('iss_content_editorial_sets_event_drop_storage_root', $storage_root);
    $GLOBALS['current_user'] = $original_user; $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original && $outside() === $original_outside, 'Real Sets, items, links, audit, options and queue unchanged; temporary fixtures removed');
if ($error !== null) { WP_CLI::error("After {$checks} checks ($stage): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Editorial Set file recovery: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
