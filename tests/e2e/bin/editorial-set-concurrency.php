<?php
/** Two MariaDB connections use uniquely named disposable tables copied from the real Set schemas. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Disposable fixtures and deterministic concurrency barriers.
global $wpdb;
$db = $wpdb; $service = iss_content_editorial_sets_service(); $checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and process-local caches');
$real_prefix = $db->prefix; $prefix = $real_prefix . 'iss_test_sets_' . bin2hex(random_bytes(5)) . '_';
$real_tables = [$service->get_sets_table_name(), $service->get_items_table_name(), $service->get_links_table_name(), $service->get_audit_table_name()];
$real_state = static fn(): array => array_map(static fn(string $table): array => $db->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A), $real_tables);
$before = $real_state();
$peer = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST); $peer->set_prefix($real_prefix); $peer->prefix = $prefix;
$expect($peer->get_var('SELECT CONNECTION_ID()') !== $db->get_var('SELECT CONNECTION_ID()'), 'Independent connections');
$as_peer = static function (callable $call) use ($db,$peer) { global $wpdb; $wpdb = $peer; try { return $call(); } finally { $wpdb = $db; } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Contention or changed ownership reaches caller for retry'); };
$created = []; $error = null; $old_suppression = $db->suppress_errors(true); $peer->suppress_errors(true);
$old_timeout = (int) $db->get_var('SELECT @@SESSION.innodb_lock_wait_timeout'); $db->query('SET SESSION innodb_lock_wait_timeout = 1'); $peer->query('SET SESSION innodb_lock_wait_timeout = 1');
try {
    foreach ($real_tables as $real) {
        $fixture = $prefix . substr($real, strlen($real_prefix));
        $expect($db->query("CREATE TABLE `$fixture` LIKE `$real`") !== false, 'Create isolated schema'); $created[] = $fixture;
    }
    $db->prefix = $prefix;
    $reset = static function () use ($db,$created,$service): array {
        foreach ($created as $table) { if ($db->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Cannot reset fixture'); } }
        $a = $service->create_set(['title' => 'A']); $b = $service->create_set(['title' => 'B']);
        $item = $service->add_item(['set_id' => $a, 'source_id' => 'fixture']); return [$a,$b,$item];
    };
    foreach (['add','update','move','promote','candidate','attach'] as $kind) {
        [$a,$b,$item] = $reset(); $db->query('START TRANSACTION');
        $result = match ($kind) {
            'add' => $service->add_item(['set_id' => $a, 'source_id' => 'new']),
            'update' => $service->update_item($item, ['notes' => 'Pending']),
            'move' => $service->move_items([$item], $b),
            'promote' => $service->mark_promoted($item, 'page', 2147478100),
            'candidate' => $service->mark_archive_candidate($item),
            'attach' => $service->attach_context($a, 'page', 2147478100),
        };
        $expect((bool) $result, 'Writer holds pending complete operation');
        $fail(static fn() => $as_peer(static fn() => $service->delete_set_if_safe($a)));
        $expect($as_peer(static fn() => $service->get_set($a)) !== null, 'Competing deletion leaves parent intact');
        $db->query('COMMIT');
        if (in_array($kind, ['promote','candidate'], true)) { $expect(!$as_peer(static fn() => $service->delete_set_if_safe($a)), 'Committed marker protects Set after competing delete retries'); }
        else { $expect($as_peer(static fn() => $service->delete_set_if_safe($a)), 'Deletion retries after writer commit'); }
        if ($kind === 'move') { $expect((int) $service->get_item($item)['set_id'] === $b, 'Moved item survives deletion of old parent'); }
    }
    [$a,$b,$item] = $reset(); $peer->query('START TRANSACTION');
    $expect($as_peer(static fn() => $service->delete_set_if_safe($a)), 'Peer deletion pending');
    $fail(static fn() => $service->add_item(['set_id' => $a, 'source_id' => 'late']));
    $peer->query('COMMIT');
    $expect($service->add_item(['set_id' => $a, 'source_id' => 'late']) === 0 && $service->attach_context($a, 'page', 99) === 0, 'Completed deletion prevents orphan creation on retry');
    [$a,$b,$item] = $reset(); $hits = 0;
    $filter = static function (string $sql) use ($service,$as_peer,$a,$b,$item,&$hits): string {
        if (!$hits && $sql === "SELECT * FROM {$service->get_sets_table_name()} WHERE id = $a FOR UPDATE") {
            ++$hits;
            if ($as_peer(static fn() => $service->move_items([$item], $b)) !== 1) { throw new RuntimeException('Peer move failed'); }
        }
        return $sql;
    };
    add_filter('query', $filter);
    try { $fail(static fn() => $service->update_item($item, ['notes' => 'Stale owner'])); }
    finally { remove_filter('query', $filter); }
    $row = $service->get_item($item);
    $expect($hits === 1 && (int) $row['set_id'] === $b && $row['notes'] === '', 'Concurrent move retained and stale owner update rejected');
    $expect($service->update_item($item, ['notes' => 'Retry']) && $service->get_item($item)['notes'] === 'Retry', 'Retry locks new parent and succeeds');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $db->query('ROLLBACK'); $peer->query('ROLLBACK'); $db->prefix = $real_prefix;
    foreach (array_reverse($created) as $table) { if ($db->query("DROP TABLE `$table`") === false) { $error = new RuntimeException('Disposable table cleanup failed'); } }
    $db->query('SET SESSION innodb_lock_wait_timeout = ' . $old_timeout); $db->suppress_errors($old_suppression); $peer->close();
}
$expect($real_state() === $before, 'Real Sets, children and audit unchanged');
if ($error) { WP_CLI::error("After $checks checks: " . $error->getMessage()); }
WP_CLI::success("Editorial Set concurrency: $checks checks passed; real rows unchanged; disposable tables removed.");
