<?php
/** Run via WP-CLI with --exec='define("DISABLE_WP_CRON", true);'. Only temporary fixtures are written. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Service-owned schema, temporary fixtures and deliberate query failures.
global $wpdb;
$service = iss_graph_get_service();
$table = $service->get_name_table_name();
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$read = static fn(): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A);
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$expect($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) === 'InnoDB', 'Real names table supports rollback');
$original = $read();
$schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
$expect(is_array($schema) && str_starts_with($schema[1], 'CREATE TABLE '), 'Read real names schema');
$seed = static function () use ($wpdb, $table, $expect): void {
    $expect($wpdb->query("DELETE FROM `$table`") !== false, 'Reset temporary fixtures');
    foreach ([[1, 10, 'atomic_names', 'old:one'], [2, 10, 'atomic_names', 'old:two'], [3, 10, 'other_source', 'preserve'], [4, 20, 'atomic_names', 'preserve']] as [$id, $entity, $source, $ref]) {
        $expect($wpdb->insert($table, [
            'id' => $id, 'entity_id' => $entity, 'name' => 'Original ' . $id,
            'normalized_name' => 'original-' . $id, 'name_type' => 'historical',
            'language' => 'de', 'valid_from_year' => 1900, 'valid_to_year' => 1950,
            'source_system' => $source, 'source_ref' => $ref,
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]) === 1, 'Seed target names, other source and other entity');
    }
};
$names = [
    ['name' => ' <b>Old Factory</b> ', 'name_type' => 'Primary!', 'language' => 'DE', 'is_primary' => true],
    ['name' => '   '],
    ['name' => 'Ältere Fabrik', 'name_type' => 'historical', 'valid_from_year' => 1850, 'valid_to_year' => 1900, 'position' => 8],
    ['name' => 'Factory', 'name_type' => 'alternative'],
];
$replace = static function (array $rows) use ($service): void {
    $service->replace_entity_names_for_source(10, 'atomic_names', $rows, 'fixture:new');
};
$fail = static function (callable $call, string $message = 'Graph name replacement') use ($expect): void {
    $caught = null;
    try { $call(); } catch (RuntimeException $exception) { $caught = $exception; }
    $expect($caught instanceof RuntimeException && str_contains($caught->getMessage(), $message), 'Failure reaches caller: ' . $message);
};
$created = false;
$filter = null;
$normalization_filter = null;
$error = null;
$old_suppression = $wpdb->suppress_errors(true);
try {
    $expect($wpdb->query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1)) !== false, 'Create connection-local schema copy');
    $created = true;
    $seed();
    $baseline = $read();
    $preserved = array_slice($baseline, 2);
    $replace($names);
    $rows = $read();
    $expect(count($rows) === 5 && array_slice($rows, 0, 2) === $preserved, 'Replace both old source references while preserving other sources/entities');
    $new = array_slice($rows, 2);
    $expect(array_column($new, 'name') === ['Old Factory', 'Ältere Fabrik', 'Factory'], 'Text sanitation, Unicode and blank-name skipping retained');
    $expect($new[0]['normalized_name'] === 'old-factory' && $new[0]['name_type'] === 'primary' && $new[0]['language'] === 'de', 'Canonical lookup, type and language normalization retained');
    $expect((int) $new[0]['is_primary'] === 1 && (int) $new[1]['is_primary'] === 0, 'Primary-name flags retained');
    $expect($new[0]['valid_from_year'] === null && (int) $new[1]['valid_from_year'] === 1850 && (int) $new[1]['valid_to_year'] === 1900, 'Nullable and historical years retained');
    $expect(array_map('intval', array_column($new, 'position')) === [0, 8, 3], 'Explicit positions and original input indices retained');
    $expect(array_unique(array_column($new, 'source_ref')) === ['fixture:new'], 'Replacement provenance recorded');
    $expect(array_column($service->get_names_for_entity(10, ['source_system' => 'atomic_names']), 'name') === ['Old Factory', 'Factory', 'Ältere Fabrik'], 'Existing name reader retains primary/position ordering');
    $expect($transaction() === 0, 'Successful standalone call commits');
    $replace([]);
    $expect($read() === $preserved, 'Empty input clears only the requested source');
    $replace([]);
    $expect($read() === $preserved, 'Repeated empty clearing succeeds');
    $replace($names);
    $expect(count($read()) === 5, 'Empty scope can be populated');
    $replace([['name' => ' '], []]);
    $expect($read() === $preserved, 'All-blank input retains the existing clearing behavior');
    $queries = $wpdb->num_queries;
    $service->replace_entity_names_for_source(0, 'atomic_names', $names);
    $service->replace_entity_names_for_source(10, '', $names);
    $expect($wpdb->num_queries === $queries, 'Invalid scope remains a no-op');

    $seed();
    foreach (['delete', 'first_insert', 'second_insert', 'commit', 'php'] as $stage) {
        $hits = 0;
        $inserts = 0;
        $filter = static function (string $sql) use ($stage, $table, &$hits, &$inserts): string {
            $is_insert = str_starts_with($sql, "INSERT INTO `$table`");
            if ($is_insert) { ++$inserts; }
            $match = match ($stage) {
                'delete' => str_starts_with($sql, "DELETE FROM `$table`"),
                'first_insert' => $is_insert && $inserts === 1,
                'second_insert', 'php' => $is_insert && $inserts === 2,
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected PHP name failure'); }
            return 'SELECT iss_graph_names_test_injected_failure';
        };
        add_filter('query', $filter);
        $fail(static fn() => $replace($names), $stage === 'php' ? 'Injected PHP name failure' : 'Graph name replacement');
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1, $stage . ': expected failure exercised');
        $expect($read() === $baseline && $transaction() === 0, $stage . ': original IDs and rows restored without an open transaction');
    }
    $bad_names = [$names[0], ['name' => str_repeat('x', 256)]];
    $fail(static fn() => $replace($bad_names), 'insert a replacement row');
    $expect($read() === $baseline && $transaction() === 0, 'wpdb length rejection restores the previous set and earlier insert');

    $normalization_filter = static function (string $value): string {
        if ($value === 'Normalization failure') { throw new RuntimeException('Injected name normalization failure'); }
        return $value;
    };
    add_filter('sanitize_text_field', $normalization_filter);
    $fail(static fn() => $replace([$names[0], ['name' => 'Normalization failure']]), 'Injected name normalization failure');
    remove_filter('sanitize_text_field', $normalization_filter);
    $normalization_filter = null;
    $expect($read() === $baseline && $transaction() === 0, 'Preparation failure leaves the original names untouched');

    foreach ([false, true] as $fail_insert) {
        $expect($wpdb->query('START TRANSACTION') !== false, 'Caller starts transaction');
        $expect($wpdb->update($table, ['name' => 'Caller pending edit'], ['id' => 3]) === 1, 'Caller has preceding work');
        $pending = $read();
        if ($fail_insert) {
            $fail(static fn() => $replace($bad_names), 'insert a replacement row');
            $expect($read() === $pending, 'Nested failure preserves caller work and original names');
        } else {
            $replace($names);
            $expect(count($read()) === 5, 'Nested success replaces the full set');
        }
        $expect($transaction() === 1, 'Caller retains transaction ownership');
        $expect($wpdb->query('ROLLBACK') !== false, 'Caller can roll back');
        $expect($read() === $baseline, 'Outer rollback undoes both caller and name writes');
    }
    $expect($wpdb->query('SET autocommit = 0') !== false, 'Caller disables autocommit');
    $replace($names);
    $expect($transaction() === 1, 'Caller-disabled autocommit is respected');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    $expect($read() === $baseline, 'Disabled-autocommit caller can undo replacement');

    $fail(static fn() => iss_graph_replace_entity_projection('atomic_names', 10, ['names' => $bad_names]), 'insert a replacement row');
    $expect($read() === $baseline, 'Projection facade propagates failure with original names intact');
    iss_graph_replace_entity_projection('atomic_names', 10, ['names' => $names]);
    $expect(count($read()) === 5, 'Projection facade accepts successful name replacement');
    $replace($names);
    $expect(count($read()) === 5, 'Repeated replacement does not accumulate names');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter) { remove_filter('query', $filter); }
    if ($normalization_filter) { remove_filter('sanitize_text_field', $normalization_filter); }
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    if ($created) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    $wpdb->suppress_errors($old_suppression);
}
$expect($read() === $original, 'Real name rows remain byte-identical');
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success($checks . ' graph name checks passed; temporary fixtures removed and real rows unchanged.');
