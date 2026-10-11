<?php
/** Graph schema installation and shared verification run only against uniquely named disposable tables. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Disposable schema fixtures and deliberate SQL failures.
require_once WP_PLUGIN_DIR . '/iss-core/schema.php';
global $wpdb;
$checks = 0; $service = iss_graph_get_service();
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and caches process-local');
$real_prefix = $wpdb->prefix; $real_options = $wpdb->options;
$real_tables = array_map(static function (string $sql): string { preg_match('/CREATE TABLE ([a-zA-Z0-9_]+)/', $sql, $match); return $match[1]; }, $service->get_schema_statements());
$real_state = static fn(): array => ['tables' => array_map(static fn(string $table): array => $wpdb->get_results("CHECKSUM TABLE `$table`", ARRAY_A), $real_tables), 'options' => $wpdb->get_results("SELECT * FROM `$real_options` ORDER BY option_id", ARRAY_A)];
$before = $real_state(); $prefix = $real_prefix . 'sv_' . bin2hex(random_bytes(4)) . '_'; $options = $prefix . 'options';
$created = []; $error = null; $stage = 'setup'; $old_suppression = $wpdb->suppress_errors(true); $original_user = wp_get_current_user();
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Incomplete or unreadable schema is rejected'); };
try {
    $expect($wpdb->query("CREATE TABLE `$options` LIKE `$real_options`") !== false, 'Isolated schema option storage'); $created[] = $options;
    $wpdb->prefix = $prefix; $wpdb->options = $options; wp_cache_flush();
    $schemas = $service->get_schema_statements();
    foreach ($schemas as $sql) { preg_match('/CREATE TABLE ([a-zA-Z0-9_]+)/', $sql, $match); $created[] = $match[1]; }
    $stage = 'fresh_install'; $service->install_schema();
    $expect(get_option(ISS_GRAPH_SCHEMA_OPTION) === ISS_GRAPH_SCHEMA_VERSION, 'Fresh installation records version after verification');
    iss_core_verify_schema($schemas); $expect(true, 'All installed declarations verify');
    $queue = $service->get_dirty_queue_table_name();
    $expect($wpdb->insert($queue, ['target_post_id' => 2147478900, 'reason' => 'Retain fixture', 'last_error' => '', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) === 1, 'Seed data to retain during repeated installation');
    $row = $wpdb->get_results("SELECT * FROM `$queue`", ARRAY_A); $service->install_schema();
    $expect($wpdb->get_results("SELECT * FROM `$queue`", ARRAY_A) === $row, 'Repeated installation preserves rows');
    foreach (['engine','columns','indexes'] as $stage) {
        $hits = 0;
        $filter = static function (string $sql) use ($stage,&$hits): string {
            $match = match ($stage) { 'engine' => str_starts_with($sql, 'SELECT ENGINE FROM information_schema'), 'columns' => str_starts_with($sql, 'SHOW COLUMNS FROM'), 'indexes' => str_starts_with($sql, 'SHOW INDEX FROM') };
            if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_schema_verification_failure';
        };
        $fail(static fn() => $with_filter($filter, static fn() => iss_core_verify_schema($schemas))); $expect($hits === 1, 'Every metadata query failure checked');
    }
    $stage = 'missing_index'; update_option(ISS_GRAPH_SCHEMA_OPTION, 'before', false);
    $expect($wpdb->query("ALTER TABLE `$queue` DROP INDEX queue_order") !== false, 'Create incomplete fixture index state');
    $fail(static fn() => iss_core_verify_schema($schemas));
    $expect(iss_graph_schema_status_rows([])[0]['status'] === 'error', 'Existing diagnostics expose incorrect live indexes');
    $service->maybe_install_schema();
    $expect(iss_graph_schema_status_rows([])[0]['status'] === 'ok', 'Existing diagnostics recognize verified repair');
    $expect(get_option(ISS_GRAPH_SCHEMA_OPTION) === ISS_GRAPH_SCHEMA_VERSION && $wpdb->get_results("SELECT * FROM `$queue`", ARRAY_A) === $row, 'Upgrade repairs missing index and preserves rows');
    $stage = 'failed_create'; $missing = $service->get_name_table_name();
    $wpdb->query("DROP TABLE `$missing`"); update_option(ISS_GRAPH_SCHEMA_OPTION, 'before', false); $hits = 0;
    $filter = static function (string $sql) use ($missing,&$hits): string {
        if (str_starts_with($sql, "CREATE TABLE $missing")) { ++$hits; return 'SELECT iss_schema_create_failure'; } return $sql;
    };
    $fail(static fn() => $with_filter($filter, static fn() => $service->install_schema()));
    $expect($hits === 1 && get_option(ISS_GRAPH_SCHEMA_OPTION) === 'before', 'Failed DDL cannot advance schema version');
    $service->install_schema(); $expect(get_option(ISS_GRAPH_SCHEMA_OPTION) === ISS_GRAPH_SCHEMA_VERSION, 'Retry completes partial installation');
    $stage = 'wrong_index'; update_option(ISS_GRAPH_SCHEMA_OPTION, 'before', false);
    $wpdb->query("ALTER TABLE `$queue` DROP INDEX target_post, ADD INDEX target_post (status)");
    $fail(static fn() => $service->install_schema());
    $expect(get_option(ISS_GRAPH_SCHEMA_OPTION) === 'before', 'Wrong index columns or uniqueness cannot be blessed as current');
    $wpdb->query("ALTER TABLE `$queue` DROP INDEX target_post"); $service->install_schema();
    $expect($wpdb->get_results("SELECT * FROM `$queue`", ARRAY_A) === $row, 'Fixture repair and retry retain data');
    $stage = 'wrong_column'; $wpdb->query("ALTER TABLE `$queue` MODIFY reason varchar(511) NOT NULL DEFAULT ''");
    $fail(static fn() => iss_core_verify_schema($schemas)); $service->install_schema(); iss_core_verify_schema($schemas);
    $expect($wpdb->get_results("SELECT * FROM `$queue`", ARRAY_A) === $row, 'Column shape is checked and installer repairs drift without losing data');
    $stage = 'version_write'; update_option(ISS_GRAPH_SCHEMA_OPTION, 'before', false); $hits = 0;
    $filter = static function (string $sql) use ($options,&$hits): string { if (str_starts_with($sql, "UPDATE `$options`") && str_contains($sql, ISS_GRAPH_SCHEMA_OPTION)) { ++$hits; return 'SELECT iss_schema_version_failure'; } return $sql; };
    $fail(static fn() => $with_filter($filter, static fn() => $service->install_schema()));
    $expect($hits === 1 && get_option(ISS_GRAPH_SCHEMA_OPTION) === 'before', 'Failed version recording reported');
    $stage = 'routing'; $GLOBALS['current_user'] = new WP_User();
    $callbacks = ['iss_graph_maybe_upgrade_schema','iss_occurrences_maybe_upgrade_schema','iss_payments_lite_maybe_upgrade_schema','iss_content_editorial_sets_ensure_runtime_state'];
    foreach (['collection','object','media','archivset','relation','import','assertion'] as $kind) { $callbacks[] = 'iss_wf_import_maybe_install_' . $kind . '_schema'; }
    $queries = [];
    $with_filter(static function (string $sql) use (&$queries): string { $queries[] = $sql; return $sql; }, static function () use ($callbacks): void { foreach ($callbacks as $callback) { $callback(); } });
    $expect(!$queries, 'Anonymous setup callbacks do not query or mutate pending schemas');
    foreach ($callbacks as $callback) { $expect(has_action('init', $callback) === false && has_action('admin_init', $callback) !== false, 'Automatic setup belongs only to privileged admin routing'); }
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->prefix = $real_prefix; $wpdb->options = $real_options; $GLOBALS['current_user'] = $original_user; wp_cache_flush();
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TABLE IF EXISTS `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $before, 'Real graph tables and options unchanged');
if ($error) { WP_CLI::error("After $checks checks ($stage): " . $error->getMessage()); }
WP_CLI::success("Schema installation verification: $checks checks passed; real rows unchanged; disposable tables removed.");
