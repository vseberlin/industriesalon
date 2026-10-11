<?php
/** Every owner installs only into disposable tables/options; no real data or uploads are changed. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Disposable schema fixtures and intentional SQL failures.
require_once WP_PLUGIN_DIR . '/iss-core/schema.php';
global $wpdb;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and process-local caches');
$owners = [
    'occurrences' => [iss_occurrences_get_service(), ISS_OCCURRENCES_SCHEMA_OPTION, ISS_OCCURRENCES_SCHEMA_VERSION],
    'sets' => [iss_content_editorial_sets_service(), ISS_CONTENT_EDITORIAL_SETS_SCHEMA_OPTION, ISS_CONTENT_EDITORIAL_SETS_SCHEMA_VERSION],
    'commerce' => [null, ISS_PAYMENTS_LITE_SCHEMA_OPTION, ISS_PAYMENTS_LITE_SCHEMA_VERSION],
];
foreach (['collection','object','media','archivset','relation','import','assertion'] as $kind) {
    $factory = 'iss_wf_import_get_' . $kind . '_service';
    $owners[$kind] = [$factory(), constant('ISS_WF_IMPORT_' . strtoupper($kind) . '_SCHEMA_OPTION'), constant('ISS_WF_IMPORT_' . strtoupper($kind) . '_SCHEMA_VERSION')];
}
$definitions = static fn($owner): array => $owner ? $owner->get_schema_statements() : iss_payments_lite_schema_statements();
$install = static function ($owner): void { if ($owner) { $owner->install_schema(); } else { iss_payments_lite_install_schema(); } };
$table_names = static fn(array $schemas): array => array_map(static function (string $sql): string { preg_match('/CREATE TABLE ([a-zA-Z0-9_]+)/', $sql, $m); return $m[1]; }, $schemas);
$real_prefix = $wpdb->prefix; $real_options = $wpdb->options; $real_tables = [];
foreach ($owners as [$owner]) { $real_tables = array_merge($real_tables, $table_names($definitions($owner))); }
$real_state = static fn(): array => ['tables' => array_map(static fn(string $table): array => $wpdb->get_results("CHECKSUM TABLE `$table`", ARRAY_A), $real_tables), 'options' => $wpdb->get_results("SELECT * FROM `$real_options` ORDER BY option_id", ARRAY_A)];
$before = $real_state(); $prefix = $real_prefix . 'ov_' . bin2hex(random_bytes(4)) . '_'; $options = $prefix . 'options';
$created = []; $error = null; $stage = 'setup'; $old_suppression = $wpdb->suppress_errors(true);
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Incomplete installation is rejected'); };
try {
    $expect($wpdb->query("CREATE TABLE `$options` LIKE `$real_options`") !== false, 'Isolated option storage'); $created[] = $options;
    $wpdb->prefix = $prefix; $wpdb->options = $options; wp_cache_flush();
    foreach ($owners as $kind => [$owner,$option,$version]) {
        $stage = $kind . '_fresh'; $schemas = $definitions($owner); $tables = $table_names($schemas); $created = array_merge($created, $tables);
        $install($owner); iss_core_verify_schema($schemas);
        $expect(get_option($option) === $version, "$kind fresh install verified and versioned");
        $table = $tables[0]; $data = [];
        foreach ($wpdb->get_results("SHOW COLUMNS FROM `$table`", ARRAY_A) as $column) {
            if ($column['Default'] !== null || $column['Null'] === 'YES' || str_contains($column['Extra'], 'auto_increment')) { continue; }
            $type = $column['Type'];
            $data[$column['Field']] = str_starts_with($type, 'datetime') ? '2026-01-01 00:00:00' : (str_contains($type, 'int') ? 0 : 'Fixture');
        }
        $expect($wpdb->insert($table, $data) === 1, 'Seed required owner fields');
        $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A); $install($owner);
        $expect($wpdb->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A) === $rows, "$kind repeated install retains rows");
        $stage = $kind . '_missing_index'; $indexes = $wpdb->get_results("SHOW INDEX FROM `$table`", ARRAY_A); $index = array_values(array_filter($indexes, static fn(array $row): bool => $row['Key_name'] !== 'PRIMARY'))[0]['Key_name'];
        $expect($wpdb->query("ALTER TABLE `$table` DROP INDEX `$index`") !== false, 'Remove one fixture index'); update_option($option, 'before', false);
        $fail(static fn() => iss_core_verify_schema($schemas)); $install($owner); iss_core_verify_schema($schemas);
        $expect(get_option($option) === $version && $wpdb->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A) === $rows, "$kind upgrade restores index and preserves rows");
        $stage = $kind . '_failed_create'; $missing = end($tables); $wpdb->query("DROP TABLE `$missing`"); update_option($option, 'before', false); $hits = 0;
        $filter = static function (string $sql) use ($missing,&$hits): string { if (str_starts_with($sql, "CREATE TABLE $missing")) { ++$hits; return 'SELECT iss_owner_schema_create_failure'; } return $sql; };
        $fail(static fn() => $with_filter($filter, static fn() => $install($owner)));
        $expect($hits === 1 && get_option($option) === 'before', "$kind failed DDL cannot advance version");
        $install($owner); $expect(get_option($option) === $version, "$kind partial installation can retry");
        $stage = $kind . '_version_write'; update_option($option, 'before', false); $hits = 0;
        $filter = static function (string $sql) use ($options,$option,&$hits): string { if (str_starts_with($sql, "UPDATE `$options`") && str_contains($sql, $option)) { ++$hits; return 'SELECT iss_owner_schema_version_failure'; } return $sql; };
        $fail(static fn() => $with_filter($filter, static fn() => $install($owner)));
        $expect($hits === 1 && get_option($option) === 'before', "$kind failed version write reported"); $install($owner);
    }
    $service = iss_occurrences_get_service(); $table = $service->get_occurrences_table_name();
    foreach (['index_read','index_drop','column_read','column_drop','sentinel','sentinel_verify','option_delete','option_verify'] as $stage) {
        $service->install_schema();
        $expect($wpdb->query("ALTER TABLE `$table` ADD entity_id bigint unsigned NOT NULL DEFAULT 0, ADD location_entity_id bigint unsigned NOT NULL DEFAULT 0, ADD INDEX entity_date (entity_id, starts_at), ADD INDEX location_entity_date (location_entity_id, starts_at)") !== false, 'Seed retired graph fields');
        $expect($wpdb->query("UPDATE `$table` SET is_open_ended = 0, ends_at = '2100-01-01 00:00:00'") === 1, 'Seed retired sentinel');
        update_option('iss_calendar_source_map', ['legacy' => 'fixture'], false); update_option(ISS_OCCURRENCES_SCHEMA_OPTION, 'before', false); $hits = 0;
        $filter = static function (string $sql) use ($stage,$table,$options,&$hits): string {
            $match = match ($stage) {
                'index_read' => str_contains($sql, 'SHOW INDEX FROM') && str_contains($sql, "Key_name = 'entity_date'"),
                'index_drop' => str_contains($sql, "ALTER TABLE $table DROP INDEX entity_date"),
                'column_read' => str_contains($sql, 'SHOW COLUMNS FROM') && str_contains($sql, 'LIKE'),
                'column_drop' => str_contains($sql, "ALTER TABLE $table DROP COLUMN entity_id"),
                'sentinel' => str_starts_with($sql, "UPDATE $table SET is_open_ended"),
                'sentinel_verify' => str_starts_with($sql, "SELECT COUNT(*) FROM $table WHERE ends_at"),
                'option_delete' => str_starts_with($sql, "DELETE FROM `$options`") && str_contains($sql, 'iss_calendar_source_map'),
                'option_verify' => str_starts_with($sql, "SELECT option_id FROM $options") && str_contains($sql, 'iss_calendar_source_map'),
            };
            if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_occurrence_migration_failure';
        };
        $fail(static fn() => $with_filter($filter, static fn() => $service->install_schema()));
        $expect($hits === 1 && get_option(ISS_OCCURRENCES_SCHEMA_OPTION) === 'before', "$stage cannot record incomplete migration as current");
        $service->install_schema(); $row = $wpdb->get_row("SELECT * FROM `$table`", ARRAY_A);
        $expect((int) $row['is_open_ended'] === 1 && $row['ends_at'] === null && !array_key_exists('entity_id', $row) && !array_key_exists('location_entity_id', $row) && get_option('iss_calendar_source_map', null) === null, 'Retry finishes legacy migration without replaying imports');
    }
    $stage = 'diagnostics'; $reports = iss_core_schema_status_rows([]);
    $reports = array_values(array_filter($reports, static fn(array $row): bool => $row['name'] !== 'iss-graph structure'));
    $expect(count($reports) === count($owners) && !array_filter($reports, static fn(array $row): bool => $row['status'] !== 'ok'), 'Existing diagnostics verify every available owner');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->prefix = $real_prefix; $wpdb->options = $real_options; wp_cache_flush();
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TABLE IF EXISTS `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $before, 'Real owner tables and options unchanged');
if ($error) { WP_CLI::error("After $checks checks ($stage): " . $error->getMessage()); }
WP_CLI::success("Owner schema installation: $checks checks passed; real rows unchanged; disposable tables removed.");
