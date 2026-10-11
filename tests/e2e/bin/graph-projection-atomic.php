<?php
/** WP-CLI --exec='define("DISABLE_WP_CRON", true);': all committed fixtures use temporary schema copies. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Service-owned temporary fixtures and intentional query failures.
global $wpdb;
$service = iss_graph_get_service();
$tables = [
    'names' => $service->get_name_table_name(),
    'identifiers' => $service->get_identifier_table_name(),
    'evidence' => $service->get_evidence_table_name(),
    'relations' => $service->get_relation_table_name(),
    'entities' => $service->get_entity_table_name(),
];
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A);
$snapshot = static function () use ($tables,$read): array {
    return array_map($read, $tables);
};
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$original = $snapshot();
$schemas = [];
foreach ($tables as $table) {
    $expect($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) === 'InnoDB', 'Real table supports rollback');
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    $expect(is_array($schema) && str_starts_with($schema[1], 'CREATE TABLE '), 'Read actual schema');
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$seed = static function () use ($wpdb,$tables,$expect): void {
    foreach ($tables as $kind => $table) {
        if ($kind === 'entities') { continue; }
        $expect($wpdb->query("DELETE FROM `$table`") !== false, 'Reset temporary ' . $kind);
        foreach ([[1,10,'atomic_projection'],[2,10,'atomic_projection'],[3,10,'other_source'],[4,20,'atomic_projection'],[5,10,'prior_source']] as [$id,$entity,$source]) {
            $row = ['id' => $id, 'source_system' => $source, 'source_ref' => 'original:' . $id, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
            $row += match ($kind) {
                'names' => ['entity_id' => $entity, 'name' => 'Original ' . $id, 'normalized_name' => 'original-' . $id],
                'identifiers' => ['entity_id' => $entity, 'namespace' => 'catalog', 'value' => 'old-' . $id, 'normalized_value' => 'old-' . $id],
                'evidence' => ['entity_id' => $entity, 'target_kind' => 'entity', 'target_id' => 20, 'label' => 'Original', 'note' => 'Original note'],
                'relations' => ['from_entity_id' => $entity, 'to_entity_id' => 20, 'relation_family' => $id === 2 ? 'person' : ($id === 5 ? 'unmentioned' : 'place'), 'note' => 'Original relation'],
            };
            if ($kind === 'relations' && $id === 5) { $row['source_system'] = 'atomic_projection'; }
            $expect($wpdb->insert($table, $row) === 1, 'Seed ' . $kind . ' fixture ' . $id);
        }
    }
};
$payload = [
    'source_ref' => '<b>fixture:new</b>',
    'names' => [['name' => 'New name', 'is_primary' => true], ['name' => 'Historical', 'valid_from_year' => 1900]],
    'identifiers' => [['namespace' => 'catalog', 'value' => 'old-5'], ['namespace' => 'catalog', 'value' => 'new']],
    'evidence_refs' => [['id' => 5, 'label' => 'Moved evidence'], ['label' => 'New evidence']],
    'relations' => [
        'place' => [['to_entity_id' => 20, 'relation_type' => 'subject'], ['to_entity_id' => 30, 'is_public' => false]],
        'person' => [['to_entity_id' => 30, 'relation_type' => 'partner']],
    ],
];
$replace = static fn(array $data) => iss_graph_replace_entity_projection('atomic_projection', 10, $data);
$fail = static function (callable $call, string $message = 'Graph') use ($expect): void {
    $caught = null;
    try { $call(); } catch (RuntimeException $exception) { $caught = $exception; }
    $expect($caught instanceof RuntimeException && str_contains($caught->getMessage(), $message), 'Failure propagates: ' . $message);
};
$created = [];
$filter = null;
$error = null;
$old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) {
        $expect($wpdb->query($schema) !== false, 'Create isolated schema copy');
        $created[] = $table;
    }
    foreach ([10,20,30] as $id) {
        $expect($wpdb->insert($tables['entities'], ['id' => $id, 'entity_kind' => 'person', 'canonical_slug' => 'fixture-' . $id, 'source_system' => 'atomic_projection', 'source_id' => (string) $id, 'display_title' => 'Fixture', 'summary' => '', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) === 1, 'Seed entity prerequisite');
    }
    $seed();
    $baseline = $snapshot();
    $commands = [];
    $filter = static function (string $sql) use (&$commands): string { $commands[] = $sql; return $sql; };
    add_filter('query', $filter);
    $replace($payload);
    remove_filter('query', $filter);
    $filter = null;
    $current = $snapshot();
    $expect(array_map('count', $current) === ['names' => 5, 'identifiers' => 4, 'evidence' => 4, 'relations' => 6, 'entities' => 3], 'All supplied parts and relation families replaced');
    foreach (['names','identifiers','evidence','relations'] as $kind) {
        $keep = $kind === 'names' || $kind === 'relations' ? 3 : 2;
        $expect(array_slice($current[$kind], 0, $keep) === array_slice($baseline[$kind], 2, $keep), $kind . ': unrelated sources, owners and omitted families unchanged');
        $source_rows = array_filter($current[$kind], static fn(array $row): bool => $row['source_ref'] === 'fixture:new');
        $expect(count($source_rows) === ($kind === 'relations' ? 3 : 2), $kind . ': shared source reference sanitized and stored');
    }
    $expect($service->get_identifier_by_id(5)['source_system'] === 'atomic_projection' && $service->get_evidence_ref_by_id(5)['source_system'] === 'atomic_projection', 'Both same-entity source reassignments succeed');
    $expect($current['entities'] === $baseline['entities'], 'Entity row itself is outside projection writes');
    $expect(count(array_filter($commands, static fn(string $sql): bool => $sql === 'COMMIT')) === 1 && count(array_filter($commands, static fn(string $sql): bool => $sql === 'START TRANSACTION')) === 1, 'Only the outer projection owns a commit');
    $expect(count(array_filter($commands, static fn(string $sql): bool => str_starts_with($sql, 'SAVEPOINT '))) === 5 && $transaction() === 0, 'All five component writes nest inside the outer transaction');

    $seed();
    // Force failure in each component, including the final family after earlier
    // component savepoints have been released. Every table must return to baseline.
    foreach (['state','engine','missing_table','begin','names_delete','names_insert','identifiers_delete','identifiers_update','identifiers_insert','evidence_delete','evidence_update','evidence_insert','place_delete','place_insert','person_delete','person_insert','last_savepoint','last_release','commit','php'] as $stage) {
        $hits = 0;
        $savepoints = 0;
        $releases = 0;
        $writes = 0;
        $filter = static function (string $sql) use ($stage,$tables,&$hits,&$savepoints,&$releases,&$writes): string {
            if (preg_match('/^(INSERT|UPDATE|DELETE) /', $sql)) { ++$writes; }
            if (str_starts_with($sql, 'SAVEPOINT ')) { ++$savepoints; }
            if (str_starts_with($sql, 'RELEASE SAVEPOINT ')) { ++$releases; }
            $parts = explode('_', $stage);
            $kind = $parts[0];
            $family = in_array($kind, ['place','person'], true) ? $kind : '';
            $table = $tables[$family !== '' ? 'relations' : $kind] ?? '';
            $verb = strtoupper($parts[1] ?? '');
            $write_match = $table !== '' && in_array($verb, ['DELETE','INSERT','UPDATE'], true)
                && str_starts_with($sql, $verb . ($verb === 'DELETE' ? ' FROM' : ($verb === 'INSERT' ? ' INTO' : '')) . " `$table`")
                && ($family === '' || str_contains($sql, "'$family'"));
            $match = match ($stage) {
                'state','engine','missing_table' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'last_savepoint' => str_starts_with($sql, 'SAVEPOINT ') && $savepoints === 5,
                'last_release' => str_starts_with($sql, 'RELEASE SAVEPOINT ') && $releases === 5,
                'commit' => $sql === 'COMMIT',
                'php' => str_starts_with($sql, "INSERT INTO `{$tables['relations']}`") && str_contains($sql, "'person'"),
                default => $write_match,
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected projection PHP failure'); }
            if ($stage === 'engine') { return str_replace('ENGINE AS engine', "IF(TABLE_NAME = '{$tables['relations']}', 'MyISAM', ENGINE) AS engine", $sql); }
            if ($stage === 'missing_table') { return $sql . " AND TABLE_NAME <> '{$tables['relations']}'"; }
            return 'SELECT iss_graph_projection_test_injected_failure';
        };
        add_filter('query', $filter);
        $fail(static fn() => $replace($payload), $stage === 'php' ? 'Injected projection PHP failure' : 'Graph');
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1 && $snapshot() === $baseline, $stage . ': all rows, IDs and source reassignments restored');
        $expect($transaction() === 0, $stage . ': no transaction left open');
        if (in_array($stage, ['state','engine','missing_table','begin'], true)) { $expect($writes === 0, $stage . ': rejected before any write'); }
    }
    foreach (['identifier_conflict','evidence_conflict','late_length','colliding_family'] as $stage) {
        $bad = $payload;
        if ($stage === 'identifier_conflict') { $bad['identifiers'][] = ['namespace' => 'catalog', 'value' => 'old-4']; }
        if ($stage === 'evidence_conflict') { $bad['evidence_refs'][] = ['id' => 4]; }
        if ($stage === 'late_length') { $bad['relations']['person'][0]['relation_label'] = str_repeat('x', 192); }
        if ($stage === 'colliding_family') { $bad['relations'] = ['Place!' => [['to_entity_id' => 20, 'relation_label' => str_repeat('x', 192)]], 'place' => [['to_entity_id' => 30]]]; }
        $fail(static fn() => $replace($bad));
        $expect($snapshot() === $baseline, $stage . ': component rejection restores all preceding writes');
    }

    foreach (['success','rejection','outer_release'] as $stage) {
        $expect($wpdb->query('START TRANSACTION') !== false, 'Caller begins transaction');
        $expect($wpdb->update($tables['names'], ['name' => 'Caller pending edit'], ['id' => 3]) === 1, 'Caller creates preceding work');
        $pending = $snapshot();
        if ($stage === 'success') {
            $replace($payload);
            $expect($snapshot() !== $pending, 'Nested success applies projection');
        } elseif ($stage === 'rejection') {
            $bad = $payload;
            $bad['relations']['person'][0]['relation_label'] = str_repeat('x', 192);
            $fail(static fn() => $replace($bad));
            $expect($snapshot() === $pending, 'Nested component rejection preserves preceding caller work');
        } else {
            $outer = '';
            $hits = 0;
            $filter = static function (string $sql) use (&$outer,&$hits): string {
                if ($outer === '' && str_starts_with($sql, 'SAVEPOINT ')) { $outer = substr($sql, strlen('SAVEPOINT ')); }
                if ($outer !== '' && $sql === 'RELEASE SAVEPOINT ' . $outer && $hits === 0) { ++$hits; return 'SELECT iss_graph_projection_test_injected_failure'; }
                return $sql;
            };
            add_filter('query', $filter);
            $fail(static fn() => $replace($payload), 'Graph entity projection replacement');
            remove_filter('query', $filter);
            $filter = null;
            $expect($hits === 1 && $snapshot() === $pending, 'Outer savepoint release failure preserves caller work and restores projection');
        }
        $expect($transaction() === 1, 'Caller retains transaction ownership');
        $expect($wpdb->query('ROLLBACK') !== false && $snapshot() === $baseline, 'Caller can undo all work');
    }
    $expect($wpdb->query('SET autocommit = 0') !== false, 'Disable caller autocommit');
    $service->replace_entity_projection(10, 'atomic_projection', $payload);
    $expect($transaction() === 1, 'Direct service method retains caller-disabled autocommit');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    $expect($snapshot() === $baseline, 'Disabled-autocommit caller can undo all parts');

    $queries = $wpdb->num_queries;
    foreach ([[], ['names' => null, 'identifiers' => 'skip', 'evidence_refs' => 'skip', 'evidence' => [[]], 'relations' => []], ['relations' => ['' => [], 'skip' => 'invalid']], ['relations' => ['invalid', ['relation_family' => '']]]] as $noop) { $replace($noop); }
    iss_graph_replace_entity_projection('', 10, $payload);
    iss_graph_replace_entity_projection('atomic_projection', 0, $payload);
    $expect($wpdb->num_queries === $queries && $snapshot() === $baseline, 'No-op and malformed omitted components perform no database work');
    $commands = [];
    $filter = static function (string $sql) use (&$commands): string { $commands[] = $sql; return $sql; };
    add_filter('query', $filter);
    $replace(['names' => []]);
    remove_filter('query', $filter);
    $filter = null;
    $expected = $baseline;
    $expected['names'] = array_slice($baseline['names'], 2);
    $expect($snapshot() === $expected, 'Explicit empty names clear only names; omitted parts retain rows');
    $guards = array_filter($commands, static fn(string $sql): bool => str_contains($sql, 'ENGINE AS engine'));
    $expect(count($guards) === 2 && !str_contains(implode('\n', $guards), $tables['relations']) && !str_contains(implode('\n', $guards), $tables['identifiers']) && !str_contains(implode('\n', $guards), $tables['evidence']), 'Partial payload validates only participating tables');
    $replace(['names' => [], 'identifiers' => [], 'evidence_refs' => [], 'evidence' => [['label' => 'Ignored alias']], 'relations' => ['place' => [], 'person' => []]]);
    $expected = array_map(static fn(array $rows): array => array_slice($rows, 2), $baseline);
    $expected['entities'] = $baseline['entities'];
    $expect($snapshot() === $expected, 'Explicit empty parts and families clear together, with evidence_refs precedence');

    $seed();
    $list = ['evidence' => [['label' => 'Alias evidence']], 'relations' => [
        ['relation_family' => 'Place!', 'to_entity_id' => 20], 'ignored', ['to_entity_id' => 30],
        ['relation_family' => 'place', 'to_entity_id' => 30], ['relation_family' => 'person', 'to_entity_id' => 20],
    ]];
    iss_graph_replace_entity_projection('Atomic_Projection!', -10, $list);
    $expect(count($service->get_relations_for_entity(10, 'place', ['source_system' => 'atomic_projection'])) === 2, 'Flat list groups sanitized families and skips malformed rows');
    $expect($service->get_evidence_refs_for_entity(10, ['source_system' => 'atomic_projection'])[0]['label'] === 'Alias evidence', 'Evidence alias remains supported');
    $expect($service->get_evidence_refs_for_entity(10, ['source_system' => 'atomic_projection'])[0]['source_ref'] === 'entity:10', 'Facade retains ID/source normalization and default source reference');
    $replace(['relations' => ['Place!' => [['to_entity_id' => 20]], 'place' => [['to_entity_id' => 30]]]]);
    $place = $service->get_relations_for_entity(10, 'place', ['source_system' => 'atomic_projection']);
    $expect(count($place) === 1 && $place[0]['entity_id'] === 30, 'Distinct map keys sanitizing to the same family retain sequential last replacement');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter) { remove_filter('query', $filter); }
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original, 'All five real tables remain byte-identical');
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success($checks . ' graph projection checks passed; temporary fixtures removed and real rows unchanged.');
