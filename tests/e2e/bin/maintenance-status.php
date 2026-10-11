<?php
/** No cron/network execution: temporary receipts and bounded decay fixtures. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Disposable fixture tables and injected failures.
global $wpdb;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled; cache process-local');
$items = iss_content_editorial_sets_service()->get_items_table_name();
$tables = [$wpdb->options, $items, iss_graph_get_service()->get_dirty_queue_table_name()];
$checksum = static fn($table) => $wpdb->get_row("CHECKSUM TABLE `$table`", ARRAY_N)[1];
$before = array_map($checksum, $tables); $options = $wpdb->get_results("SELECT * FROM {$wpdb->options}", ARRAY_A);
$schemas = [];
foreach ($tables as $table) { $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1); }
$created = []; $error = null; $old_suppress = $wpdb->suppress_errors(true);
$reject = '';
$filter = static function ($sql) use (&$reject) { return $reject !== '' && str_contains($sql, $reject) && preg_match('/^(INSERT|UPDATE|DELETE) /', $sql) ? 'UPDATE iss_missing_maintenance_fixture_table SET x=1' : $sql; };
$failure = static function (callable $run) use ($expect): void { $caught = false; try { $run(); } catch (Throwable $e) { $caught = true; } $expect($caught, 'Owner failure remains visible to scheduler'); };
try {
    foreach ($schemas as $table => $schema) { $expect($wpdb->query($schema) !== false, 'Create temporary schema'); $created[] = $table; }
    foreach ($options as $option) { if ($wpdb->insert($wpdb->options, $option) !== 1) { throw new RuntimeException('Could not shadow options'); } }
    wp_cache_flush(); add_filter('query', $filter);
    $clock = time(); $cron = ['version' => 2];
    foreach (iss_core_maintenance_jobs() as $job => $config) {
        delete_option('iss_maintenance_' . $job);
        $cron[$clock + 60][$config['hook']][md5(serialize([]))] = ['schedule' => 'hourly', 'args' => [], 'interval' => HOUR_IN_SECONDS];
    }
    update_option('cron', $cron); delete_option('iss_graph_dirty_queue_last_run');
    $rows = array_column(iss_core_maintenance_status($clock), null, 'owner');
    $expect($rows['supersaas']['state'] === 'never' && $rows['supersaas']['status'] === 'error', 'Unknown success is visible, not presumed current');
    $expect(iss_core_run_maintenance_job('supersaas', static fn() => ['errors' => 0]) === ['errors' => 0], 'Successful owner result retained');
    $previous = get_option('iss_maintenance_supersaas')['last_success_at'];
    $failure(static fn() => iss_core_run_maintenance_job('supersaas', static fn() => ['errors' => 1, 'error_message' => 'Upstream unavailable']));
    $receipt = get_option('iss_maintenance_supersaas');
    $expect($receipt['status'] === 'failed' && $receipt['last_success_at'] === $previous && $receipt['error'] === 'Upstream unavailable', 'Failure preserves last success and actionable reason');
    $failure(static fn() => iss_core_run_maintenance_job('supersaas', static fn() => new WP_Error('fixture', 'Fixture error')));
    $failure(static fn() => iss_core_run_maintenance_job('fallback', static fn() => ['errors' => ['failed source']]));
    $ran = false; $reject = 'iss_maintenance_set_decay';
    $failure(static function () use (&$ran) { iss_core_run_maintenance_job('set_decay', static function () use (&$ran) { $ran = true; }); });
    $expect(!$ran, 'Job cannot start if receipt persistence fails'); $reject = '';
    foreach (array_keys(iss_core_maintenance_jobs()) as $job) { iss_core_run_maintenance_job($job, static fn() => 0); }
    $rows = iss_core_maintenance_status();
    $expect(!in_array('error', array_column($rows, 'status'), true), 'Successful current jobs, empty queue and scheduled callbacks are healthy');
    $queue = iss_graph_get_service()->get_dirty_queue_table_name();
    $expect($wpdb->insert($queue, ['target_post_id' => 1, 'target_post_type' => 'post', 'status' => 'processing', 'locked_at' => '2000-01-01 00:00:00', 'created_at' => '2000-01-01 00:00:00', 'updated_at' => '2000-01-01 00:00:00']) === 1, 'Create stalled processing fixture');
    $rows = array_column(iss_core_maintenance_status(), null, 'owner');
    $expect($rows['graph_queue']['status'] === 'error' && $rows['graph_queue']['oldest_processing_age_seconds'] > 900, 'Stalled processing is visible even with no dirty rows');
    $wpdb->query("DELETE FROM `$queue`");
    $deny_reads = static fn($sql) => str_starts_with($sql, "SELECT status, COUNT(*) AS count FROM {$queue}") ? 'SELECT * FROM iss_missing_maintenance_fixture_table' : $sql;
    add_filter('query', $deny_reads);
    try {
        $rows = array_column(iss_core_maintenance_status(), null, 'owner');
        $expect($rows['graph_queue']['status'] === 'error' && $rows['graph_queue']['read_error'], 'Queue diagnostic read failure cannot report a healthy empty queue');
    } finally { remove_filter('query', $deny_reads); }
    $rows = array_column(iss_core_maintenance_status(time() + 27 * HOUR_IN_SECONDS), null, 'owner');
    $expect($rows['supersaas']['status'] === 'error' && $rows['fallback']['status'] === 'error' && $rows['wp_cron']['status'] === 'error', 'Overdue jobs and scheduler become visible without executing anything');
    $cron[$clock]['iss_fixture_missing_callback'][md5(serialize([]))] = ['schedule' => false, 'args' => []]; update_option('cron', $cron);
    $rows = array_column(iss_core_maintenance_status(), null, 'owner');
    $expect($rows['wp_cron']['unregistered_hooks'] === ['iss_fixture_missing_callback'], 'Missing callbacks are reported without blindly deleting their events');
    for ($i = 0; $i < 503; ++$i) {
        if ($wpdb->insert($items, ['set_id' => 1, 'source_id' => 'maintenance-' . $i, 'status' => 'pending', 'decay_at' => '2000-01-01 00:00:00', 'retain' => $i === 502 ? 1 : 0, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) !== 1) { throw new RuntimeException('Could not seed decay fixture'); }
    }
    $expect(iss_content_editorial_sets_mark_stale_items() === 500, 'Decay work is bounded to 500 rows');
    $expect(iss_content_editorial_sets_mark_stale_items() === 2, 'Next run completes remaining eligible work');
    $expect((int) $wpdb->get_var("SELECT COUNT(*) FROM `$items` WHERE retain=1 AND status='pending'") === 1, 'Retained source evidence is untouched');
    $reject = $items; $failure('iss_content_editorial_sets_run_decay'); $reject = '';
    $expect(get_option('iss_maintenance_set_decay')['status'] === 'failed', 'SQL failure cannot be logged as successful cleanup');
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('query', $filter);
    foreach (array_reverse($created) as $table) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    wp_cache_flush(); $wpdb->suppress_errors($old_suppress);
}
$expect(array_map($checksum, $tables) === $before, 'Real options, queue and Set records unchanged');
if ($error) { throw $error; }
WP_CLI::success("Maintenance status: $checks checks passed; no real jobs, mail or remote sync executed.");
