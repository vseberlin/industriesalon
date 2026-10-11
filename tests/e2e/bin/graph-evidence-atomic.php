<?php
/** WP-CLI --exec='define("DISABLE_WP_CRON", true);': committed writes use temporary schema copies only. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Service-owned temporary fixtures and intentional database failures.
global $wpdb;
$service = iss_graph_get_service();
$table = $service->get_evidence_table_name();
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
    $expect($wpdb->query("DELETE FROM `$table`") !== false, 'Reset temporary evidence');
    foreach ([[1,10,'atomic_evidence'], [2,10,'atomic_evidence'], [3,10,'other_source'], [4,20,'atomic_evidence'], [5,10,'prior_source'], [6,999,'atomic_evidence']] as [$id,$entity,$source]) {
        $expect($wpdb->insert($table, [
            'id' => $id, 'entity_id' => $entity, 'target_kind' => 'entity', 'target_id' => 20,
            'source_system' => $source, 'source_ref' => 'original:' . $id, 'label' => 'Original', 'note' => 'Original note',
            'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
        ]) === 1, 'Seed target, other source, foreign entity and missing-entity fixture');
    }
};
$first = ['target_kind' => 'Entity!', 'target_id' => 20, 'label' => '<b>Transcript mention</b>', 'source_url' => 'https://example.test/video#t=42', 'note' => "<b>First</b>\nSecond", 'confidence' => 140, 'status' => 'review', 'source_system' => 'ignored', 'source_ref' => 'ignored'];
$second = ['label' => 'Second source', 'confidence' => -10, 'status' => 'invalid'];
$shared = ['id' => 5, 'target_id' => 20, 'label' => 'Updated same entity'];
$foreign = ['id' => 4, 'label' => 'Must not reassign'];
$replace = static function (array $rows) use ($service): void {
    $service->replace_entity_evidence_refs_for_source(10, 'atomic_evidence', $rows, 'fixture:new');
};
$fail = static function (callable $call, string $message = 'Graph evidence replacement') use ($expect): void {
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
        $expect($wpdb->insert($entities, ['id' => $id, 'entity_kind' => 'person', 'canonical_slug' => 'fixture-' . $id, 'source_system' => 'atomic_evidence', 'source_id' => (string) $id, 'display_title' => 'Fixture', 'summary' => '', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) === 1, 'Seed real-shaped entity prerequisite');
    }
    $seed();
    $baseline = $read($table);
    $preserved = array_slice($baseline, 2);
    $replace([$first, 'ignored non-array row', $second]);
    $rows = $read($table);
    $expect(count($rows) === 6 && array_slice($rows, 0, 4) === $preserved, 'Successful replacement preserves unrelated sources/entities');
    $new = array_slice($rows, 4);
    $expect($new[0]['target_kind'] === 'entity' && (int) $new[0]['target_id'] === 20, 'Target normalization retained');
    $expect($new[0]['label'] === 'Transcript mention' && $new[0]['source_url'] === 'https://example.test/video#t=42' && $new[0]['note'] === "First\nSecond", 'Label, URL and multiline note retained');
    $expect((int) $new[0]['confidence'] === 100 && $new[0]['status'] === 'review', 'Confidence ceiling and review status retained');
    $expect($new[0]['source_system'] === 'atomic_evidence' && $new[0]['source_ref'] === 'fixture:new', 'Caller controls provenance');
    $expect($new[1]['target_kind'] === 'entity' && $new[1]['target_id'] === null && (int) $new[1]['confidence'] === 0 && $new[1]['status'] === 'accepted', 'Nullable target, confidence floor and default status retained');
    $expect(count($service->get_evidence_refs_for_entity(10, ['source_system' => 'atomic_evidence', 'status' => 'review', 'target_kind' => 'entity'])) === 1, 'Filtered evidence reader sees replacement');
    $expect($transaction() === 0, 'Standalone replacement commits');
    $replace([]);
    $expect($read($table) === $preserved, 'Empty input intentionally clears only this source');
    $replace([]);
    $expect($read($table) === $preserved, 'Empty-source clearing is repeatable');
    $queries = $wpdb->num_queries;
    $service->replace_entity_evidence_refs_for_source(0, 'atomic_evidence', [$first]);
    $service->replace_entity_evidence_refs_for_source(10, '', [$first]);
    $expect($wpdb->num_queries === $queries, 'Invalid scope remains a no-op');

    $seed();
    $replace([$shared, ['id' => 1, 'label' => 'Recreated deleted row'], ['id' => 999999, 'label' => 'Stale ID becomes new row'], $first, $first, []]);
    $moved = $service->get_evidence_ref_by_id(5);
    $expect($moved['entity_id'] === 10 && $moved['source_system'] === 'atomic_evidence' && $moved['source_ref'] === 'fixture:new', 'Existing same-entity source reassignment retained');
    $expect($moved['created_at'] === '2026-01-01 00:00:00', 'Upsert preserves existing evidence identity and creation time');
    $current = $service->get_evidence_refs_for_entity(10, ['source_system' => 'atomic_evidence']);
    $expect(count($current) === 6 && count($read($table)) === 9, 'No-ID duplicates remain independent rows and empty arrays retain default-row behavior');
    $expect($service->get_evidence_ref_by_id(1) === null && $service->get_evidence_ref_by_id(999999) === null, 'Deleted and stale IDs are replaced with generated identities');
    $expect($current[5]['target_id'] === null && $current[5]['label'] === '', 'Default evidence row retains nullable target and empty label');
    $expect($service->get_evidence_ref_by_id(4)['entity_id'] === 20, 'Foreign entity keeps evidence ownership');

    // Fix timestamps to make repeated identical direct upserts update zero rows.
    $affected = [];
    $after_update = false;
    $filter = static function (string $sql) use ($wpdb,$table,&$affected,&$after_update): string {
        if ($after_update) { $affected[] = $wpdb->rows_affected; $after_update = false; }
        if (str_starts_with($sql, "UPDATE `$table`")) {
            $after_update = true;
            return preg_replace('/\b\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\b/', '2026-01-01 00:00:00', $sql);
        }
        return $sql;
    };
    add_filter('query', $filter);
    $direct = $shared + ['source_system' => 'review_source', 'status' => 'rejected'];
    $one = $service->upsert_entity_evidence_ref(10, $direct);
    $two = $service->upsert_entity_evidence_ref(10, $direct);
    remove_filter('query', $filter);
    $filter = null;
    $expect($one !== null && $two === $one && $two['status'] === 'rejected' && end($affected) === 0, 'Direct review-style upsert accepts a successful zero-row update');
    $expect($service->upsert_entity_evidence_ref(10, $foreign) === null, 'Direct upsert rejects foreign ownership');

    $seed();
    foreach (['delete','first_insert','second_insert','update','lookup','entity_lookup','update_readback','insert_readback','commit','php'] as $stage) {
        $hits = 0;
        $inserts = 0;
        $updates = 0;
        $filter = static function (string $sql) use ($stage,$table,$entities,&$hits,&$inserts,&$updates): string {
            $is_insert = str_starts_with($sql, "INSERT INTO `$table`");
            $is_update = str_starts_with($sql, "UPDATE `$table`");
            $is_lookup = str_contains($sql, "FROM $table") && str_contains($sql, 'WHERE id =');
            if ($is_insert) { ++$inserts; }
            if ($is_update) { ++$updates; }
            $match = match ($stage) {
                'delete' => str_starts_with($sql, "DELETE FROM `$table`"),
                'first_insert' => $is_insert && $inserts === 1,
                'second_insert','php' => $is_insert && $inserts === 2,
                'update' => $is_update,
                'lookup' => $is_lookup && $updates === 0,
                'entity_lookup' => str_contains($sql, "FROM $entities") && str_contains($sql, 'WHERE id ='),
                'update_readback' => $is_lookup && $updates > 0,
                'insert_readback' => $is_lookup && $inserts > 0,
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected PHP evidence failure'); }
            return 'SELECT iss_graph_evidence_test_injected_failure';
        };
        add_filter('query', $filter);
        $fail(static fn() => $replace([$shared,$first,$second]), $stage === 'php' ? 'Injected PHP evidence failure' : 'Graph evidence replacement');
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1, $stage . ': expected failure occurred');
        $expect($read($table) === $baseline && $transaction() === 0, $stage . ': old rows, IDs and source assignments all restored');
    }
    foreach ([$foreign, ['label' => str_repeat('x', 192)]] as $bad_row) {
        $fail(static fn() => $replace([$shared,$first,$bad_row]));
        $expect($read($table) === $baseline, 'Rejected row restores deleted evidence, preceding insert and source reassignment');
    }
    $fail(static fn() => $service->replace_entity_evidence_refs_for_source(999, 'atomic_evidence', [$first]));
    $expect($read($table) === $baseline, 'Missing entity cannot silently clear existing orphan evidence');

    foreach ([false,true] as $reject) {
        $expect($wpdb->query('START TRANSACTION') !== false, 'Caller starts transaction');
        $expect($wpdb->update($table, ['label' => 'Caller pending edit'], ['id' => 3]) === 1, 'Caller has preceding work');
        $pending = $read($table);
        if ($reject) {
            $fail(static fn() => $replace([$shared,$first,$foreign]));
            $expect($read($table) === $pending, 'Nested failure preserves caller edit and all old evidence');
        } else {
            $replace([$shared,$first]);
            $expect($service->get_evidence_ref_by_id(5)['source_system'] === 'atomic_evidence', 'Nested success applies existing source reassignment');
        }
        $expect($transaction() === 1, 'Caller still owns the transaction');
        $expect($wpdb->query('ROLLBACK') !== false && $read($table) === $baseline, 'Outer rollback restores all evidence and caller work');
    }
    $expect($wpdb->query('SET autocommit = 0') !== false, 'Caller disables autocommit');
    $replace([$shared,$first]);
    $expect($transaction() === 1, 'Caller-disabled autocommit retained');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    $expect($read($table) === $baseline, 'Disabled-autocommit caller can undo replacement');
    $fail(static fn() => iss_graph_replace_entity_projection('atomic_evidence', 10, ['evidence_refs' => [$shared,$foreign]]));
    $expect($read($table) === $baseline, 'Projection facade propagates rejected evidence without data loss');
    iss_graph_replace_entity_projection('atomic_evidence', 10, ['evidence' => [$first,$second]]);
    $expect(count($read($table)) === 6, 'Projection facade retains the evidence alias');
    iss_graph_replace_entity_projection('atomic_evidence', 10, ['evidence_refs' => [], 'evidence' => [$first]]);
    $expect($read($table) === $preserved, 'Explicit evidence_refs takes precedence over alias and can clear source');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter) { remove_filter('query', $filter); }
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $name) { $wpdb->query("DROP TEMPORARY TABLE `$name`"); }
    $wpdb->suppress_errors($old_suppression);
}
foreach ($original as $name => $rows) { $expect($read($name) === $rows, 'Real evidence/entity rows remain byte-identical'); }
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success($checks . ' graph evidence checks passed; temporary fixtures removed and real rows unchanged.');
