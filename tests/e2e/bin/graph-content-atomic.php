<?php
/** WP-CLI with DISABLE_WP_CRON: temporary graph/queue/options tables; source posts only in process-local caches. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated schemas and intentional SQL failures.
global $wpdb;
$service = iss_graph_get_service();
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled; source fixture caches process-local');
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$tables = [
    'entities' => $service->get_entity_table_name(), 'names' => $service->get_name_table_name(),
    'identifiers' => $service->get_identifier_table_name(), 'relations' => $service->get_relation_table_name(),
    'evidence' => $service->get_evidence_table_name(), 'search' => $service->get_search_table_name(),
    'people' => $service->get_person_facts_table_name(), 'organizations' => $service->get_organization_facts_table_name(),
    'queue' => $service->get_dirty_queue_table_name(),
];
$base = 2147481000;
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 3)) === 0, 'Fixture sources do not exist in WordPress');
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY " . ($table === $wpdb->options ? 'option_id' : 'id'), ARRAY_A);
$snapshot = static fn(): array => array_map($read, array_diff_key($tables, ['queue' => true]));
$original = $snapshot();
$original_queue = $read($tables['queue']);
$original_options = $read($wpdb->options);
$schemas = [];
foreach (array_merge(array_values($tables), [$wpdb->options]) as $table) {
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    if (!is_array($schema) || !str_starts_with($schema[1], 'CREATE TABLE ')) { throw new RuntimeException('Could not read fixture schema'); }
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$post = new WP_Post((object) ['ID' => $base, 'post_type' => 'veranstaltung', 'post_status' => 'publish', 'post_name' => 'new-content', 'post_title' => 'New Content (NEW)', 'post_excerpt' => 'New summary', 'post_content' => '', 'post_author' => 1, 'post_parent' => 0, 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'guid' => 'https://example.test/new-content']);
$venue = new WP_Post((object) ['ID' => $base + 1, 'post_type' => 'register_place', 'post_status' => 'publish', 'post_name' => 'new-venue', 'post_title' => 'New Venue (VEN)', 'post_excerpt' => 'Venue summary', 'post_content' => '', 'post_author' => 1, 'post_parent' => 0, 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'guid' => 'https://example.test/new-venue']);
$source_meta = ['iss_primary_place_id' => [(string) ($base + 1)]];
$venue_meta = ['register_id' => ['content-test-venue'], 'owner' => ['New Venue Works'], 'place_visibility' => ['private']];
$cache_source = static function () use ($post,$venue,$base,&$source_meta,$venue_meta): void {
    wp_cache_set($base, $post, 'posts');
    wp_cache_set($base, $source_meta, 'post_meta');
    wp_cache_set($base + 1, $venue, 'posts');
    wp_cache_set($base + 1, $venue_meta, 'post_meta');
};
$alias = iss_graph_get_alias_backfill_source_system();
$seed = static function () use ($wpdb,$tables,$base,$post,$alias,$cache_source): void {
    foreach ($tables as $table) { if ($wpdb->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Could not reset fixture'); } }
    $time = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
    $insert = static function (string $table, array $row) use ($wpdb,$time): void {
        if ($wpdb->insert($table, $row + $time) !== 1) { throw new RuntimeException('Fixture insert failed: ' . $wpdb->last_error); }
    };
    foreach ([[10,iss_graph_get_entity_kind_for_post_type($post->post_type),'wp_post',(string) $base,'old-content',$base],[20,'place','register_post',(string) ($base + 1),'old-venue',$base + 1],[30,'person','manual_slug','person','person',null],[40,'organization','manual_slug','organization','organization',null],[50,'place','other','unrelated','unrelated',null]] as [$id,$kind,$source,$source_id,$slug,$post_id]) {
        $insert($tables['entities'], ['id' => $id, 'entity_kind' => $kind, 'post_id' => $post_id, 'source_system' => $source, 'source_id' => $source_id, 'canonical_slug' => $slug, 'display_title' => 'Old ' . $slug, 'summary' => 'Original', 'status' => 'draft', 'search_visibility' => 'hidden']);
    }
    foreach ([[1,10,'iss_graph_content_post_title','Old Content'],[2,10,$alias,'Old Alias'],[3,10,'other_source','Manual Historic (HIST)'],[4,20,'register_title','Old Venue'],[5,50,'other_source','Unrelated Place']] as [$id,$entity,$source,$name]) {
        $insert($tables['names'], ['id' => $id, 'entity_id' => $entity, 'source_system' => $source, 'name' => $name, 'normalized_name' => sanitize_title($name), 'source_ref' => 'fixture:' . $id]);
    }
    foreach ([[1,10,'wp_post','old-content'],[2,10,'other_source','manual-id'],[3,20,'register_post','old-venue'],[4,50,'other_source','unrelated']] as [$id,$entity,$source,$value]) {
        $insert($tables['identifiers'], ['id' => $id, 'entity_id' => $entity, 'namespace' => 'catalog', 'value' => $value, 'normalized_value' => $value, 'source_system' => $source]);
    }
    foreach ([[1,10,50,'place','content_native'],[2,10,30,'person','content_native'],[3,10,40,'organization','content_native'],[4,10,30,'person','content_admin'],[5,10,40,'organization','content_admin'],[6,50,40,'organization','other_source']] as [$id,$from,$to,$family,$source]) {
        $insert($tables['relations'], ['id' => $id, 'from_entity_id' => $from, 'to_entity_id' => $to, 'relation_family' => $family, 'source_system' => $source, 'note' => 'Original']);
    }
    $cache_source();
};
$native = static function (array $rows, WP_Post $source) use ($base): array {
    if ((int) $source->ID === $base) {
        $rows['person'] = [['to_entity_id' => 30, 'relation_type' => 'subject', 'is_public' => false]];
        $rows['organization'] = [['to_entity_id' => 40, 'relation_type' => 'organizer', 'is_public' => true]];
    }
    return $rows;
};
$sync = static fn(): ?array => iss_graph_sync_public_content_entity($base);
$with_filter = static function (callable $filter, callable $call) {
    add_filter('query', $filter);
    try { return $call(); } finally { remove_filter('query', $filter); }
};
$fail = static function (callable $call) use ($expect): void {
    $error = null;
    try { $call(); } catch (RuntimeException $caught) { $error = $caught; }
    $expect($error instanceof RuntimeException, 'Failure reaches caller');
};
$created = [];
$error = null;
$old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) {
        if ($wpdb->query($schema) === false) { throw new RuntimeException('Could not create shadow table'); }
        $created[] = $table;
    }
    foreach ($original_options as $option) { if ($wpdb->insert($wpdb->options, $option) !== 1) { throw new RuntimeException('Could not shadow options'); } }
    add_filter('iss_graph_public_content_native_relation_rows', $native, 10, 2);
    $seed();
    $baseline = $snapshot();
    $commands = [];
    $entity = $with_filter(static function (string $sql) use (&$commands): string { $commands[] = $sql; return $sql; }, $sync);
    $expect((int) $entity['id'] === 10 && $entity['display_title'] === $post->post_title && $entity['summary'] === $post->post_excerpt, 'Content identity/title/summary updated');
    $expect((int) $entity['is_public'] === 1 && $entity['search_visibility'] === 'hidden', 'Publication and search visibility contracts retained');
    $expect($service->get_identifier_by_namespace_value('wp_post', (string) $base)['entity_id'] === 10, 'WordPress identity projected');
    $expect($service->get_identifier_by_namespace_value('legacy_slug', 'veranstaltung:new-content')['entity_id'] === 10, 'Legacy slug projected');
    $expect(in_array('NEW', array_column($service->get_names_for_entity(10, ['source_system' => $alias]), 'name'), true), 'Aliases see newly saved title');
    $places = $service->get_relations_for_entity(10, 'place', ['source_system' => 'content_native']);
    $expect(count($places) === 1 && (int) $places[0]['entity_id'] === 20 && $places[0]['relation_type'] === 'venue', 'Native venue replaces previous automatic relation');
    $expect((int) $service->get_entity_by_id(20)['is_public'] === 0, 'Nested register sync preserves private venue visibility');
    $expect($service->find_entity_by_slug('organization', 'new-venue-works') !== null, 'Nested venue owner entity created');
    $current = $snapshot();
    $expect($current['entities'][4] === $baseline['entities'][4], 'Unrelated graph entity retained byte-for-byte');
    $expect(array_values(array_filter($current['relations'], static fn(array $row): bool => $row['source_system'] !== 'content_native' && $row['source_system'] !== 'register_meta')) === array_slice($baseline['relations'], 3), 'Manual and unrelated relations retained');
    $expect(array_values(array_filter($current['identifiers'], static fn(array $row): bool => $row['source_system'] === 'other_source')) === [$baseline['identifiers'][1],$baseline['identifiers'][3]], 'Other-source identifiers retained');
    $expect(count(array_filter($commands, static fn(string $sql): bool => $sql === 'COMMIT')) === 1 && $transaction() === 0, 'Content and nested venue share one outer commit');
    $expect(wp_cache_get($base, 'post_meta') === $source_meta && wp_cache_get($base + 1, 'post_meta') === $venue_meta, 'Source metadata remains unchanged');

    foreach (['state','engine','begin','lookup','entity_update','entity_readback','title_delete','title_insert','identifier_delete','identifier_insert','alias_read','alias_write','venue_meta','venue_post','venue_update','venue_owner_create','place_delete','place_insert','person_insert','organization_insert','final_read','commit','php'] as $stage) {
        $seed();
        $baseline = $snapshot();
        if ($stage === 'venue_meta') { wp_cache_delete($base, 'post_meta'); }
        if ($stage === 'venue_post') { wp_cache_delete($base + 1, 'posts'); }
        $hits = 0;
        $updated = false;
        $finished = false;
        $filter = static function (string $sql) use ($stage,$tables,$base,$alias,&$hits,&$updated,&$finished): string {
            $entity_update = str_starts_with($sql, "UPDATE `{$tables['entities']}`");
            $name_insert = str_starts_with($sql, "INSERT INTO `{$tables['names']}`");
            $relation_insert = str_starts_with($sql, "INSERT INTO `{$tables['relations']}`");
            if ($entity_update && str_contains($sql, "'wp_post'")) { $updated = true; }
            if ($relation_insert && str_contains($sql, "'content_native'") && str_contains($sql, "'organization'")) { $finished = true; }
            $match = match ($stage) {
                'state','engine' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'lookup' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'post_id = ' . $base),
                'entity_update' => $entity_update && str_contains($sql, "'wp_post'"),
                'entity_readback' => $updated && str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'WHERE id = 10'),
                'title_delete' => str_starts_with($sql, "DELETE FROM `{$tables['names']}`") && str_contains($sql, "'iss_graph_content_post_title'"),
                'title_insert' => $name_insert && str_contains($sql, "'iss_graph_content_post_title'"),
                'identifier_delete' => str_starts_with($sql, "DELETE FROM `{$tables['identifiers']}`") && str_contains($sql, "'wp_post'"),
                'identifier_insert' => str_starts_with($sql, "INSERT INTO `{$tables['identifiers']}`") && str_contains($sql, "'wp_post'"),
                'alias_read' => str_contains($sql, "FROM {$tables['names']}") && str_contains($sql, 'entity_id = 10'),
                'alias_write' => $name_insert && str_contains($sql, "'$alias'"),
                'venue_meta' => str_contains($sql, 'meta_value FROM') && str_contains($sql, 'post_id IN (' . $base . ')'),
                'venue_post' => str_contains($sql, 'WHERE ID = ' . ($base + 1) . ' LIMIT 1'),
                'venue_update' => $entity_update && str_contains($sql, "'register_post'"),
                'venue_owner_create' => str_starts_with($sql, "INSERT INTO `{$tables['entities']}`") && str_contains($sql, "'New Venue Works'"),
                'place_delete' => str_starts_with($sql, "DELETE FROM `{$tables['relations']}`") && str_contains($sql, "'content_native'") && str_contains($sql, "'place'"),
                'place_insert' => $relation_insert && str_contains($sql, "'content_native'") && str_contains($sql, "'place'"),
                'person_insert' => $relation_insert && str_contains($sql, "'content_native'") && str_contains($sql, "'person'"),
                'organization_insert','php' => $relation_insert && str_contains($sql, "'content_native'") && str_contains($sql, "'organization'"),
                'final_read' => $finished && str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'WHERE id = 10'),
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected content graph PHP failure'); }
            if ($stage === 'engine') { return str_replace('ENGINE AS engine', "IF(TABLE_NAME = '{$tables['relations']}', 'MyISAM', ENGINE) AS engine", $sql); }
            return 'SELECT iss_graph_content_injected_failure';
        };
        $fail(static fn() => $with_filter($filter, $sync));
        $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage failure rolls back content, native families and nested venue/owner changes");
    }

    // A newly created content entity must disappear too if a later family fails.
    $seed();
    $service->delete_entity(10);
    $baseline = $snapshot();
    $late = static function (string $sql) use ($tables): string {
        return str_starts_with($sql, "INSERT INTO `{$tables['relations']}`") && str_contains($sql, "'content_native'") && str_contains($sql, "'organization'") ? 'SELECT iss_graph_content_late_failure' : $sql;
    };
    $fail(static fn() => $with_filter($late, $sync));
    $expect($snapshot() === $baseline, 'New content entity and nested targets roll back together');
    $entity = $sync();
    $expect((int) $entity['id'] > 0 && (int) $entity['post_id'] === $base, 'New content graph creation succeeds after rollback');

    $seed();
    $baseline = $snapshot();
    foreach ([false,true] as $reject) {
        $wpdb->query('START TRANSACTION');
        $wpdb->update($tables['names'], ['name' => 'Caller pending'], ['id' => 3]);
        $pending = $snapshot();
        if ($reject) { $fail(static fn() => $with_filter($late, $sync)); $expect($snapshot() === $pending, 'Nested failure preserves caller pending work'); }
        else { $sync(); $expect($snapshot() !== $pending, 'Nested content sync applies'); }
        $expect($transaction() === 1, 'Caller retains outer transaction');
        $wpdb->query('ROLLBACK');
        $expect($snapshot() === $baseline, 'Caller rollback undoes complete content and venue graph');
    }
    $wpdb->query('SET autocommit = 0');
    $sync();
    $expect($transaction() === 1, 'Disabled-autocommit caller retains transaction ownership');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    $expect($snapshot() === $baseline, 'Disabled-autocommit rollback restores graph');

    foreach (iss_graph_get_content_relation_post_types() as $type) {
        $post->post_type = $type;
        $post->post_status = 'draft';
        $seed();
        $entity = $sync();
        $expect($entity['entity_kind'] === iss_graph_get_entity_kind_for_post_type($type) && !(int) $entity['is_public'], "$type draft mapping and privacy retained");
        $expect($service->get_identifier_by_namespace_value('legacy_slug', $type . ':new-content')['entity_id'] === 10, "$type identifier contract retained");
    }
    $post->post_type = 'veranstaltung';
    $post->post_status = 'publish';
    foreach ([[], ['iss_primary_place_id' => [(string) ($base + 2)]]] as $empty_meta) {
        $source_meta = $empty_meta;
        $seed();
        $sync();
        $expect($service->get_relations_for_entity(10, 'place', ['source_system' => 'content_native']) === [], 'Absent or missing venue intentionally clears native venue');
        $expect(count($service->get_relations_for_entity(10, 'person', ['source_system' => 'content_admin'])) === 1, 'Venue clearing retains manual relations');
    }
    $source_meta = ['iss_primary_place_id' => [(string) ($base + 1)]];
    foreach (['trash','auto-draft'] as $status) {
        $post->post_status = $status;
        $seed();
        $expect($sync() === null && $service->get_entity_by_id(10) === null, "$status removes graph projection through atomic deletion");
        $expect($service->get_entity_by_id(20) !== null && $service->get_entity_by_id(50) !== null, 'Deletion retains venue and unrelated entities');
        $expect($sync() === null, 'Repeated missing projection removal is harmless');
    }
    $post->post_status = 'trash';
    $seed();
    $baseline = $snapshot();
    $fail(static fn() => $with_filter(static function (string $sql) use ($tables,$base): string {
        return str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'post_id = ' . $base) ? 'SELECT iss_graph_content_delete_lookup_failure' : $sql;
    }, $sync));
    $expect($snapshot() === $baseline, 'Failed removal lookup cannot report a successful no-op');
    $fail(static fn() => $with_filter(static function (string $sql) use ($tables): string {
        return str_starts_with($sql, "DELETE FROM `{$tables['names']}`") ? 'SELECT iss_graph_content_delete_failure' : $sql;
    }, $sync));
    $expect($snapshot() === $baseline, 'Failed projection removal restores preceding relation deletion');

    $post->post_status = 'publish';
    $seed();
    $baseline = $snapshot();
    wp_cache_delete($base, 'posts');
    $fail(static fn() => $with_filter(static function (string $sql) use ($base): string {
        return str_contains($sql, 'WHERE ID = ' . $base . ' LIMIT 1') ? 'SELECT iss_graph_content_source_failure' : $sql;
    }, $sync));
    $expect($snapshot() === $baseline, 'Failed source post read preserves graph');
    $expect($sync() === null && $snapshot() === $baseline, 'Truly missing source keeps no-op behavior');
    $post->post_type = 'page';
    $cache_source();
    $expect($sync() === null && $snapshot() === $baseline, 'Unsupported source type keeps no-op behavior');

    // Failure propagates through the existing worker; only its temporary queue changes.
    $post->post_type = 'veranstaltung';
    $seed();
    $baseline = $snapshot();
    $expect($service->mark_post_dirty($base, 'fixture'), 'Queue source fixture');
    $stats = $with_filter($late, static fn(): array => $service->drain_dirty_queue());
    $expect($stats['failed'] === 1 && $stats['processed'] === 0 && $snapshot() === $baseline, 'Worker retries failed bridge without partial graph writes');
    $queue_row = $read($tables['queue'])[0];
    $expect($queue_row['status'] === 'dirty' && (int) $queue_row['attempts'] === 1, 'Retry retains existing queue contract');
    $expect(has_action('save_post', 'iss_graph_refresh_public_content_entity_on_save') === 35, 'Existing source-save hook retained');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    remove_filter('iss_graph_public_content_native_relation_rows', $native, 10);
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) {
        if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Temporary table cleanup failed'); }
    }
    for ($id = $base; $id <= $base + 3; ++$id) { wp_cache_delete($id, 'posts'); wp_cache_delete($id, 'post_meta'); }
    foreach (['alloptions','notoptions','iss_graph_dirty_queue_lock','iss_graph_dirty_queue_last_run'] as $key) { wp_cache_delete($key, 'options'); }
    $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original && $read($tables['queue']) === $original_queue && $read($wpdb->options) === $original_options, 'Real graph, queue and options unchanged during isolated suite');
$expect(get_post($base) === null && get_post($base + 1) === null, 'No real source posts created');
if ($error !== null) { WP_CLI::error($error->getMessage()); }
WP_CLI::success("Content graph atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
