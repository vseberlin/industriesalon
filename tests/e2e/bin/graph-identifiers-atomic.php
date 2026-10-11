<?php
/** WP-CLI --exec='define("DISABLE_WP_CRON", true);': committed writes use temporary schema copies only. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Service-owned temporary fixtures and intentional database failures.
global $wpdb;
$service = iss_graph_get_service();
$table = $service->get_identifier_table_name();
$entities = $service->get_entity_table_name();
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$read = static fn(string $name): array => $wpdb->get_results("SELECT * FROM `$name` ORDER BY id", ARRAY_A);
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$original = [];
$schemas = [];
foreach ([$table, $entities] as $name) {
    $expect($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $name)) === 'InnoDB', 'Real table supports rollback');
    $original[$name] = $read($name);
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$name`", ARRAY_N);
    $expect(is_array($schema) && str_starts_with($schema[1], 'CREATE TABLE '), 'Read real schema');
    $schemas[$name] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$seed = static function () use ($wpdb, $table, $expect): void {
    $expect($wpdb->query("DELETE FROM `$table`") !== false, 'Reset temporary identifiers');
    foreach ([[1,10,'atomic_ids','old-one'], [2,10,'atomic_ids','old-two'], [3,10,'other_source','keep'], [4,20,'atomic_ids','foreign'], [5,10,'prior_source','shared'], [6,999,'atomic_ids','orphan']] as [$id,$entity,$source,$value]) {
        $expect($wpdb->insert($table, [
            'id' => $id, 'entity_id' => $entity, 'namespace' => 'catalog', 'value' => $value,
            'normalized_value' => $value, 'source_system' => $source, 'source_ref' => 'original:' . $id,
            'label' => 'Original', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]) === 1, 'Seed target, other source, foreign entity and missing-entity fixture');
    }
};
$first = ['namespace' => 'Catalog!', 'value' => ' <b>Alpha</b> ', 'label' => '<b>Primary ID</b>', 'url' => 'https://example.test/id/alpha', 'trust_level' => 'trusted_review', 'confidence' => 140, 'is_primary' => true, 'status' => 'review', 'source_system' => 'ignored', 'source_ref' => 'ignored'];
$second = ['namespace' => 'catalog', 'value' => 'Beta'];
$shared = ['namespace' => 'catalog', 'value' => 'shared', 'label' => 'Updated same entity'];
$foreign = ['namespace' => 'catalog', 'value' => 'FOREIGN'];
$replace = static function (array $rows) use ($service): void {
    $service->replace_entity_identifiers_for_source(10, 'atomic_ids', $rows, 'fixture:new');
};
$fail = static function (callable $call, string $message = 'Graph identifier replacement') use ($expect): void {
    $caught = null;
    try { $call(); } catch (RuntimeException $exception) { $caught = $exception; }
    $expect($caught instanceof RuntimeException && str_contains($caught->getMessage(), $message), 'Failure reaches caller: ' . $message);
};
$created = [];
$filter = null;
$error = null;
$old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $name => $schema) {
        $expect($wpdb->query($schema) !== false, 'Create isolated schema copy');
        $created[] = $name;
    }
    foreach ([10,20] as $id) {
        $expect($wpdb->insert($entities, ['id' => $id, 'entity_kind' => 'person', 'canonical_slug' => 'fixture-' . $id, 'source_system' => 'atomic_ids', 'source_id' => (string) $id, 'display_title' => 'Fixture', 'summary' => '', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) === 1, 'Seed real-shaped entity prerequisite');
    }
    $seed();
    $baseline = $read($table);
    $preserved = array_slice($baseline, 2);
    $replace([$first, 'ignored non-array row', $second]);
    $rows = $read($table);
    $expect(count($rows) === 6 && array_slice($rows, 0, 4) === $preserved, 'Successful replacement preserves unrelated sources/entities');
    $new = array_slice($rows, 4);
    $expect($new[0]['namespace'] === 'catalog' && $new[0]['value'] === 'Alpha' && $new[0]['normalized_value'] === 'alpha', 'Namespace/value normalization retained');
    $expect($new[0]['label'] === 'Primary ID' && $new[0]['url'] === 'https://example.test/id/alpha', 'Label and URL retained');
    $expect($new[0]['trust_level'] === 'trusted_review' && (int) $new[0]['confidence'] === 100 && (int) $new[0]['is_primary'] === 1 && $new[0]['status'] === 'review', 'Trust, confidence, primary and review contracts retained');
    $expect($new[0]['source_system'] === 'atomic_ids' && $new[0]['source_ref'] === 'fixture:new', 'Caller controls provenance');
    $expect($new[1]['trust_level'] === 'suggest_only' && $new[1]['status'] === 'accepted', 'Default trust and status retained');
    $expect($service->get_identifier_by_namespace_value('catalog', 'ALPHA')['id'] === (int) $new[0]['id'], 'Canonical lookup remains case-insensitive');
    $expect($transaction() === 0, 'Standalone replacement commits');
    $replace([]);
    $expect($read($table) === $preserved, 'Empty input intentionally clears only this source');
    $replace([]);
    $expect($read($table) === $preserved, 'Empty-source clearing is repeatable');
    $queries = $wpdb->num_queries;
    $service->replace_entity_identifiers_for_source(0, 'atomic_ids', [$first]);
    $service->replace_entity_identifiers_for_source(10, '', [$first]);
    $expect($wpdb->num_queries === $queries, 'Invalid scope remains a no-op');

    $seed();
    $replace([$shared, $first, ['namespace' => 'catalog', 'value' => 'ALPHA', 'label' => 'Last duplicate wins']]);
    $moved = $service->get_identifier_by_id(5);
    $expect($moved['entity_id'] === 10 && $moved['source_system'] === 'atomic_ids' && $moved['source_ref'] === 'fixture:new', 'Existing same-entity source reassignment retained');
    $expect($moved['created_at'] === '2026-01-01 00:00:00', 'Upsert preserves existing identifier identity and creation time');
    $alpha = $service->get_identifier_by_namespace_value('catalog', 'alpha');
    $expect(count($read($table)) === 5 && $alpha['label'] === 'Last duplicate wins', 'Duplicate normalized input updates one row, retaining last-row behavior');
    $expect($service->get_identifier_by_id(4)['entity_id'] === 20, 'Foreign entity keeps identifier ownership');

    // Fix fixture timestamps so the repeated identical upsert updates zero rows,
    // independent of whether these calls cross a clock-second boundary.
    $updates = [];
    $filter = static function (string $sql) use ($table, &$updates): string {
        if (str_starts_with($sql, "UPDATE `$table`")) { $updates[] = $sql; }
        if (str_starts_with($sql, "UPDATE `$table`") || str_starts_with($sql, "INSERT INTO `$table`")) {
            return preg_replace('/\b\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\b/', '2026-01-01 00:00:00', $sql);
        }
        return $sql;
    };
    add_filter('query', $filter);
    $replace([$second, $second]);
    remove_filter('query', $filter);
    $filter = null;
    $expect(count($updates) === 1 && count($service->get_identifiers_for_entity(10, ['source_system' => 'atomic_ids'])) === 1, 'Repeated identical input accepts an unchanged upsert');

    $seed();
    foreach (['delete','first_insert','second_insert','update','lookup','readback','commit','php'] as $stage) {
        $hits = 0;
        $inserts = 0;
        $filter = static function (string $sql) use ($stage,$table,&$hits,&$inserts): string {
            $is_insert = str_starts_with($sql, "INSERT INTO `$table`");
            if ($is_insert) { ++$inserts; }
            $match = match ($stage) {
                'delete' => str_starts_with($sql, "DELETE FROM `$table`"),
                'first_insert' => $is_insert && $inserts === 1,
                'second_insert','php' => $is_insert && $inserts === 2,
                'update' => str_starts_with($sql, "UPDATE `$table`"),
                'lookup' => str_contains($sql, "FROM $table") && str_contains($sql, 'WHERE namespace'),
                'readback' => str_contains($sql, "FROM $table") && str_contains($sql, 'WHERE id ='),
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected PHP identifier failure'); }
            return 'SELECT iss_graph_identifiers_test_injected_failure';
        };
        add_filter('query', $filter);
        $fail(static fn() => $replace([$shared,$first,$second]), $stage === 'php' ? 'Injected PHP identifier failure' : 'Graph identifier replacement');
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1, $stage . ': expected failure occurred');
        $expect($read($table) === $baseline && $transaction() === 0, $stage . ': old rows, IDs and source assignments all restored');
    }
    foreach ([[], ['namespace' => 'catalog', 'value' => ''], ['namespace' => '', 'value' => 'x'], ['namespace' => 'catalog', 'value' => []], $foreign, ['namespace' => 'catalog', 'value' => 'too-long-label', 'label' => str_repeat('x', 192)]] as $bad_row) {
        $fail(static fn() => $replace([$shared,$first,$bad_row]));
        $expect($read($table) === $baseline, 'Rejected row restores deleted identifiers, earlier insert and same-entity source change');
    }
    $fail(static fn() => $service->replace_entity_identifiers_for_source(999, 'atomic_ids', [$first]));
    $expect($read($table) === $baseline, 'Missing entity cannot silently clear existing orphan identifiers');

    // Simulate a competing claim appearing after the lookup: the real UNIQUE
    // constraint must still reject the insert and the whole batch must roll back.
    $hits = 0;
    $filter = static function (string $sql) use ($table,&$hits): string {
        if (str_contains($sql, "FROM $table") && str_contains($sql, "normalized_value = 'foreign'")) {
            ++$hits;
            return str_replace('WHERE namespace', 'WHERE 1 = 0 AND namespace', $sql);
        }
        return $sql;
    };
    add_filter('query', $filter);
    $fail(static fn() => $replace([$first,$foreign]));
    remove_filter('query', $filter);
    $filter = null;
    $expect($hits === 1 && $read($table) === $baseline, 'Database uniqueness failure preserves both owners');

    foreach ([false,true] as $reject) {
        $expect($wpdb->query('START TRANSACTION') !== false, 'Caller starts transaction');
        $expect($wpdb->update($table, ['label' => 'Caller pending edit'], ['id' => 3]) === 1, 'Caller has preceding work');
        $pending = $read($table);
        if ($reject) {
            $fail(static fn() => $replace([$shared,$first,$foreign]));
            $expect($read($table) === $pending, 'Nested failure preserves caller edit and all old identifiers');
        } else {
            $replace([$shared,$first]);
            $expect($service->get_identifier_by_id(5)['source_system'] === 'atomic_ids', 'Nested success applies existing source reassignment');
        }
        $expect($transaction() === 1, 'Caller still owns the transaction');
        $expect($wpdb->query('ROLLBACK') !== false && $read($table) === $baseline, 'Outer rollback restores all identifiers and caller work');
    }
    $expect($wpdb->query('SET autocommit = 0') !== false, 'Caller disables autocommit');
    $replace([$shared,$first]);
    $expect($transaction() === 1, 'Caller-disabled autocommit retained');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    $expect($read($table) === $baseline, 'Disabled-autocommit caller can undo replacement');
    $fail(static fn() => iss_graph_replace_entity_projection('atomic_ids', 10, ['identifiers' => [$shared,$foreign]]));
    $expect($read($table) === $baseline, 'Projection facade propagates rejected identity without data loss');
    iss_graph_replace_entity_projection('atomic_ids', 10, ['identifiers' => [$first,$second]]);
    $expect(count($read($table)) === 6, 'Projection facade completes a valid replacement');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter) { remove_filter('query', $filter); }
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $name) { $wpdb->query("DROP TEMPORARY TABLE `$name`"); }
    $wpdb->suppress_errors($old_suppression);
}
foreach ($original as $name => $rows) { $expect($read($name) === $rows, 'Real identifier/entity rows remain byte-identical'); }
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success($checks . ' graph identifier checks passed; temporary fixtures removed and real rows unchanged.');
