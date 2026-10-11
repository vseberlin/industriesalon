<?php
/** WP-CLI with cron disabled: all source, archive and graph fixture writes use temporary tables. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Real-schema isolated fixtures and intentional SQL failures.
global $wpdb;
$service = iss_graph_get_service(); $objects = iss_wf_import_get_object_service(); $archive_relations = iss_wf_import_get_relation_service(); $checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled; fixture caches process-local');
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$tables = ['entities' => $service->get_entity_table_name(), 'names' => $service->get_name_table_name(), 'identifiers' => $service->get_identifier_table_name(), 'relations' => $service->get_relation_table_name(), 'evidence' => $service->get_evidence_table_name(), 'search' => $service->get_search_table_name(), 'people' => $service->get_person_facts_table_name(), 'organizations' => $service->get_organization_facts_table_name(), 'queue' => $service->get_dirty_queue_table_name(), 'objects' => $objects->get_object_table_name(), 'archive_relations' => $archive_relations->get_relation_table_name(), 'posts' => $wpdb->posts, 'postmeta' => $wpdb->postmeta];
$order = [$wpdb->posts => 'ID', $wpdb->postmeta => 'meta_id', $wpdb->options => 'option_id'];
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY " . ($order[$table] ?? 'id'), ARRAY_A);
$snapshot = static fn(): array => array_map($read, $tables);
$base = 2147478800;
$fixture_sources = static fn(): array => ['posts' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d ORDER BY ID", $base, $base + 3), ARRAY_A), 'meta' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id BETWEEN %d AND %d ORDER BY meta_id", $base, $base + 3), ARRAY_A)];
$expect($fixture_sources() === ['posts' => [], 'meta' => []], 'Fixture source IDs absent');
$real_state = static fn(): array => array_map($read, array_diff_key($tables, ['posts' => true, 'postmeta' => true])) + $fixture_sources();
$original = $real_state(); $original_options = $read($wpdb->options); $schemas = [];
foreach (array_merge(array_values($tables), [$wpdb->options]) as $table) {
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    if (!is_array($schema) || !str_starts_with($schema[1], 'CREATE TABLE ')) { throw new RuntimeException('Cannot read fixture schema'); }
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$clear = static function () use ($objects,$archive_relations): void {
    wp_cache_flush();
    foreach ([$objects,$archive_relations] as $owner) { (new ReflectionProperty($owner, 'projection_cache'))->setValue($owner, []); }
};
$seed = static function (bool $cold = false, bool $new = false) use ($wpdb,$tables,$base,$objects,$archive_relations,$clear,$service): void {
    foreach ($tables as $table) { if ($wpdb->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Cannot reset fixtures'); } }
    $clear();
    for ($n = 0; $n <= 2; ++$n) {
        if ($wpdb->insert($wpdb->posts, ['ID' => $base + $n, 'post_author' => 1, 'post_type' => ['archivobjekt','register_place','post'][$n], 'post_status' => 'publish', 'post_title' => ['Archive Object (ARC)','Local Works (LOC)','Other Post'][$n], 'post_name' => 'archive-fixture-' . $n, 'post_excerpt' => 'Archive summary', 'post_content' => 'Preserved editorial content', 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_modified' => '2026-01-01 00:00:00', 'post_modified_gmt' => '2026-01-01 00:00:00', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '']) !== 1) { throw new RuntimeException('Cannot seed post'); }
    }
    $meta = [ISS_WF_IMPORT_SOURCE_KIND_META => 'museum_fixture', ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META => 'object-1', ISS_WF_IMPORT_OBJECT_YEAR_META => '1920–1935', ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_ID_META => 55, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_NAME_META => 'Archive Museum', ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META => [['source_id' => '101','name' => 'Archive Person','type' => 'creator','note' => 'Person provenance'], ['source_id' => '102','name' => 'Werk für Technik','type' => 'person','note' => 'Organization label']], ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META => [['source_id' => '103','name' => 'Remote Place','type' => 'depicted','note' => 'Place provenance']], 'iss_related_places' => [['place_id' => $base + 1, 'role' => 'subject', 'weight' => 7, 'label' => 'Local relation']], ISS_WF_IMPORT_OBJECT_EVENTS_META => [['event_id' => 1,'event_type_name' => 'Production','note' => 'Event']], ISS_WF_IMPORT_OBJECT_RELATIONS_META => [['source_object_id' => 'other-source','relation_type' => 'related']]];
    foreach ($meta as $key => $value) { if ($wpdb->insert($wpdb->postmeta, ['post_id' => $base,'meta_key' => $key,'meta_value' => maybe_serialize($value)]) !== 1) { throw new RuntimeException('Cannot seed source metadata'); } }
    foreach (['register_id' => 'archive-local-place','owner' => 'Local Place Owner','place_visibility' => 'private'] as $key => $value) { $wpdb->insert($wpdb->postmeta, ['post_id' => $base + 1,'meta_key' => $key,'meta_value' => $value]); }
    if (!$cold) { $objects->sync_object_post($base); $archive_relations->sync_object_relations($base); }
    $time = ['created_at' => '2026-01-01 00:00:00','updated_at' => '2026-01-01 00:00:00'];
    foreach ([[10,'archive_object','archive_object','museum_fixture:object-1',$base],[30,'person','fixture','30',null],[40,'organization','fixture','40',null],[50,'place','fixture','50',null],[99,'organization','fixture','99',null]] as [$id,$kind,$owner,$source_id,$post_id]) {
        if ($wpdb->insert($tables['entities'], $time + ['id' => $id,'entity_kind' => $kind,'post_id' => $post_id,'source_system' => $owner,'source_id' => $source_id,'canonical_slug' => 'old-' . $id,'display_title' => 'Old Entity ' . $id,'summary' => 'Original','status' => 'draft','is_public' => 0,'search_visibility' => 'hidden']) !== 1) { throw new RuntimeException('Cannot seed entity'); }
    }
    foreach ([[10,'archive_object_title','Old title'],[10,'entity_alias_backfill','OLD'],[10,'manual','Keep name'],[99,'manual','Keep unrelated']] as [$id,$owner,$name]) { $wpdb->insert($tables['names'], $time + ['entity_id' => $id,'source_system' => $owner,'name' => $name,'normalized_name' => sanitize_title($name)]); }
    foreach ([[10,'archive_object','old-object'],[10,'manual','keep-id'],[99,'manual','keep-unrelated']] as [$id,$owner,$value]) { $wpdb->insert($tables['identifiers'], $time + ['entity_id' => $id,'source_system' => $owner,'namespace' => 'catalog','value' => $value,'normalized_value' => $value]); }
    foreach ([[10,30,'person','archive_projection'],[10,40,'organization','archive_projection'],[10,50,'place','archive_projection'],[10,30,'person','manual'],[99,50,'place','manual']] as [$from,$to,$family,$owner]) { $wpdb->insert($tables['relations'], $time + ['from_entity_id' => $from,'to_entity_id' => $to,'relation_family' => $family,'source_system' => $owner,'note' => 'Keep unless owned']); }
    if ($new) { $service->delete_entity(10); }
    $clear();
};
$sync = static fn(): ?array => iss_graph_sync_archive_object_entity($base);
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Failure reaches caller'); };
$graph_only = static fn(array $rows): array => array_diff_key($rows, ['objects' => true,'archive_relations' => true]);
$created = []; $error = null; $stage = 'success'; $old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create fixture'); } $created[] = $table; }
    foreach ($original_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Cannot shadow options'); } }
    $seed(); $baseline = $snapshot(); $sqls = [];
    $entity = $with_filter(static function (string $sql) use (&$sqls): string { $sqls[] = $sql; return $sql; }, $sync);
    $expect((int) $entity['id'] === 10 && $entity['display_title'] === 'Archive Object (ARC)' && $entity['summary'] === 'Archive summary' && $entity['is_public'] == 1 && $entity['search_visibility'] === 'hidden', 'Object identity, title, summary and public/search semantics retained');
    $expect((int) $entity['sort_start'] === 1920 && (int) $entity['sort_end'] === 1935, 'Projection date bounds retained');
    $expect(in_array('ARC', array_column($service->get_names_for_entity(10), 'name'), true) && in_array('Keep name', array_column($service->get_names_for_entity(10), 'name'), true), 'Title aliases refreshed; other names retained');
    foreach (['wp_post' => (string) $base,'archive_object_id' => 'museum_fixture:object-1','archive_source_id' => 'object-1','catalog' => 'keep-id'] as $namespace => $value) { $expect((int) $service->get_identifier_by_namespace_value($namespace, $value)['entity_id'] === 10, 'WordPress, archive and manual identifiers retained'); }
    foreach (['organization' => 2,'person' => 1,'place' => 2] as $family => $count) { $expect(count($service->get_relations_for_entity(10, $family, ['source_system' => 'archive_projection'])) === $count, 'All projection relation families synchronized'); }
    $people = $service->get_relations_for_entity(10, 'person', ['source_system' => 'archive_projection']); $expect($people[0]['relation_type'] === 'creator' && $people[0]['note'] === 'Person provenance', 'People relation provenance retained');
    $organizations = $service->get_relations_for_entity(10, 'organization', ['source_system' => 'archive_projection']); $expect(in_array('mentioned', array_column($organizations, 'relation_type'), true) && in_array('institution', array_column($organizations, 'relation_type'), true), 'Organization-looking person labels remain organizations');
    $local = $service->find_entity_by_post('place', $base + 1); $expect($local && !(int) $local['is_public'] && count($service->get_relations_for_entity((int) $local['id'], 'organization', ['source_system' => 'register_meta'])) === 1, 'Nested local register place and owner created with privacy preserved');
    $expect(count(array_filter($sqls, static fn(string $sql): bool => $sql === 'COMMIT')) === 2, 'Source preparation and complete nested graph writes each use one commit');
    $expect($snapshot()['objects'] === $baseline['objects'] && $snapshot()['archive_relations'] === $baseline['archive_relations'] && $snapshot()['posts'] === $baseline['posts'] && $snapshot()['postmeta'] === $baseline['postmeta'], 'Prepared source projections and editorial source stay unchanged');
    $unowned = static fn(array $rows): array => array_values(array_filter($rows, static fn(array $row): bool => $row['source_system'] === 'manual'));
    $expect($unowned($snapshot()['relations']) === $unowned($baseline['relations']) && $snapshot()['search'] === [] && $snapshot()['queue'] === [], 'Manual relations, search and queue remain separate');
    $count = count($snapshot()['entities']); $sync(); $expect(count($snapshot()['entities']) === $count, 'Repeated synchronization reuses named identities');

    foreach ([false,true] as $new) {
        foreach (['state','engine','begin','entity','title','identifiers','aliases','named_read','named_insert','named_title','named_identifier','local_lookup','local_post','local_metadata','local_entity','local_title','local_identifier','local_owner','organization','person','place','readback','savepoint','release','commit','php'] as $stage) {
            $seed(false, $new); $baseline = $snapshot(); $hits = 0; $place_written = false; $graph_boundary = false;
            $filter = static function (string $sql) use ($stage,$tables,$wpdb,$base,&$hits,&$place_written,&$graph_boundary): string {
                $original_sql = $sql; $sql = preg_replace('/\s+/', ' ', $sql);
                $graph_boundary = $graph_boundary || (str_contains($sql, 'ENGINE AS engine') && str_contains($sql, $tables['entities']));
                $relation = str_starts_with($sql, "INSERT INTO `{$tables['relations']}`") && str_contains($sql, "'archive_projection'");
                $entity_write = (str_starts_with($sql, "INSERT INTO `{$tables['entities']}`") || str_starts_with($sql, "UPDATE `{$tables['entities']}`"));
                $match = match ($stage) {
                    'state','engine' => $graph_boundary && str_contains($sql, 'ENGINE AS engine'), 'begin' => $graph_boundary && $sql === 'START TRANSACTION',
                    'entity' => $entity_write && str_contains($sql, "'archive_object'"),
                    'title' => str_starts_with($sql, "INSERT INTO `{$tables['names']}`") && str_contains($sql, "'archive_object_title'"),
                    'identifiers' => str_starts_with($sql, "INSERT INTO `{$tables['identifiers']}`") && str_contains($sql, "'archive_object'"),
                    'aliases' => str_starts_with($sql, "INSERT INTO `{$tables['names']}`") && str_contains($sql, "'entity_alias_backfill'"),
                    'named_read' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, "source_system = 'archive_person'"),
                    'named_insert' => $entity_write && str_contains($sql, "'archive_person'"),
                    'named_title' => str_starts_with($sql, "INSERT INTO `{$tables['names']}`") && str_contains($sql, "'archive_title'"),
                    'named_identifier' => str_starts_with($sql, "INSERT INTO `{$tables['identifiers']}`") && str_contains($sql, "'archive_person'"),
                    'local_lookup' => str_contains($sql, "entity_kind = 'place'") && str_contains($sql, 'post_id = ' . ($base + 1)),
                    'local_post' => str_contains($sql, 'WHERE ID = ' . ($base + 1) . ' LIMIT 1'),
                    'local_metadata' => str_contains($sql, "meta_value FROM {$wpdb->postmeta}") && str_contains($sql, (string) ($base + 1)),
                    'local_entity' => $entity_write && str_contains($sql, "'register_post'"),
                    'local_title' => str_starts_with($sql, "INSERT INTO `{$tables['names']}`") && str_contains($sql, "'register_title'"),
                    'local_identifier' => str_starts_with($sql, "INSERT INTO `{$tables['identifiers']}`") && str_contains($sql, "'register_post'"),
                    'local_owner' => $entity_write && str_contains($sql, "'Local Place Owner'"),
                    'organization','person','place','php' => $relation && str_contains($sql, "'" . ($stage === 'php' ? 'place' : $stage) . "'"),
                    'readback' => $place_written && str_contains($sql, "FROM {$tables['entities']} WHERE id = "),
                    'savepoint' => str_starts_with($sql, 'SAVEPOINT '), 'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '), 'commit' => $graph_boundary && $sql === 'COMMIT',
                };
                $place_written = $place_written || ($relation && str_contains($sql, "'place'"));
                if (!$match || $hits > 0) { return $original_sql; } ++$hits;
                if ($stage === 'php') { throw new RuntimeException('Injected late archive graph failure'); }
                if ($stage === 'engine') { return str_replace('ENGINE AS engine', "'MyISAM' AS engine", $original_sql); }
                return 'SELECT iss_graph_archive_injected_failure';
            };
            $fail(static fn() => $with_filter($filter, $sync));
            $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage restores object and all nested graph writes");
        }
    }
    foreach (['post','object_read','relations_read'] as $stage) {
        $seed(); $baseline = $snapshot(); $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,$base,&$hits): string {
            $match = match ($stage) { 'post' => str_contains($sql, "WHERE ID = $base LIMIT 1"), 'object_read' => str_contains($sql, "FROM {$tables['objects']}"), 'relations_read' => str_contains($sql, "FROM {$tables['archive_relations']}") };
            if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_graph_archive_source_failure';
        };
        $fail(static fn() => $with_filter($filter, $sync)); $expect($hits === 1 && $snapshot() === $baseline, 'Failed projection read does not trigger repair or graph clearing');
        $expect($sync() !== null, 'Failed source reads are not cached as missing');
    }
    $seed(true, true); $baseline = $snapshot(); $source_write_states = [];
    $filter = static function (string $sql) use ($tables,$transaction,&$source_write_states): string { if (str_starts_with($sql, "INSERT INTO `{$tables['objects']}`") || str_starts_with($sql, "INSERT INTO `{$tables['archive_relations']}`")) { $source_write_states[] = $transaction(); } return $sql; };
    $entity = $with_filter($filter, $sync); $expect($entity && $source_write_states !== [] && array_unique($source_write_states) === [1], 'Cold source preparation is transactional before the standalone graph transaction');
    $expect($snapshot()['posts'] === $baseline['posts'] && $snapshot()['postmeta'] === $baseline['postmeta'], 'Cold preparation preserves canonical editorial inputs');
    $seed(true); $baseline = $snapshot();
    $late = static fn(string $sql): string => str_starts_with($sql, "INSERT INTO `{$tables['relations']}`") && str_contains($sql, "'archive_projection'") ? 'SELECT iss_graph_archive_late_failure' : $sql;
    $fail(static fn() => $with_filter($late, $sync)); $expect($graph_only($snapshot()) === $graph_only($baseline) && $snapshot()['objects'] !== [] && $snapshot()['archive_relations'] !== [], 'Cold source preparation survives later graph rollback as a separate owner boundary');

    foreach (['metadata','object_insert','relation_delete','named_ref','event','object','local_place'] as $stage) {
        $seed(true); $baseline = $snapshot(); $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,$wpdb,&$hits): string {
            $match = match ($stage) {
                'metadata' => str_contains($sql, "meta_value FROM {$wpdb->postmeta}"),
                'object_insert' => str_starts_with($sql, "INSERT INTO `{$tables['objects']}`"),
                'relation_delete' => str_starts_with($sql, "DELETE FROM `{$tables['archive_relations']}`"),
                default => str_starts_with($sql, "INSERT INTO `{$tables['archive_relations']}`") && str_contains($sql, "'" . ['named_ref' => 'source_named_ref','event' => 'source_event','object' => 'source_object','local_place' => 'local_place'][$stage] . "'"),
            };
            if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_graph_archive_prepare_failure';
        };
        $fail(static fn() => $with_filter($filter, $sync)); $expect($hits === 1 && $snapshot() === $baseline, 'Preparation failure restores paired source projections before graph writes');
    }

    $seed(); $baseline = $snapshot();
    foreach ([false,true] as $reject) {
        $wpdb->query('START TRANSACTION'); $wpdb->update($tables['names'], ['name' => 'Caller pending'], ['entity_id' => 99]); $pending = $snapshot();
        if ($reject) { $fail(static fn() => $with_filter($late, $sync)); $expect($snapshot() === $pending, 'Nested failure preserves caller writes'); }
        else { $expect($sync() !== null, 'Nested archive graph save succeeds'); }
        $expect($transaction() === 1, 'Caller retains transaction ownership'); $wpdb->query('ROLLBACK'); $expect($snapshot() === $baseline, 'Caller rollback restores all graph data');
    }
    $wpdb->query('SET autocommit = 0'); $sync(); $expect($transaction() === 1, 'Disabled-autocommit caller retains ownership'); $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $expect($snapshot() === $baseline, 'Disabled-autocommit rollback restores graph');
    foreach (['draft','private','trash','auto-draft'] as $status) {
        $seed(); $wpdb->update($wpdb->posts, ['post_status' => $status], ['ID' => $base]); $clear(); $entity = $sync();
        $expect($entity && $entity['status'] === $status && !(int) $entity['is_public'] && $entity['search_visibility'] === 'hidden', 'Existing projection status/privacy semantics retained');
        if (in_array($status, ['trash','auto-draft'], true)) { $seed(true); $wpdb->update($wpdb->posts, ['post_status' => $status], ['ID' => $base]); $clear(); $baseline = $snapshot(); $expect($sync() === null && $snapshot() === $baseline, 'Missing trash/auto-draft projection remains a no-op'); }
    }
    $seed(); $baseline = $snapshot(); $expect(iss_graph_sync_archive_object_entity($base + 2) === null && iss_graph_sync_archive_object_entity($base + 3) === null && $snapshot() === $baseline, 'Unsupported and absent sources remain no-ops');
    $post = get_post($base); $revision = clone $post; $revision->post_type = 'revision'; $revision->post_parent = $base + 2; wp_cache_set($base, $revision, 'posts'); iss_graph_sync_archive_object_on_save($base, $post); $expect($snapshot() === $baseline, 'Save hook skips revisions');
    $clear(); $objects->get_projection_for_post($base); $archive_relations->get_projection_for_post($base); get_post($base); $wpdb->get_var('SELECT iss_graph_archive_previous_error'); $expect($sync() !== null, 'Cached source projections ignore unrelated earlier SQL errors');

    $stage = 'placeholder_identity'; $seed(); $sync();
    $museum = $service->find_entity_by_source('organization', 'archive_institution', 'institution:55');
    $wpdb->update($tables['objects'], ['md_institution_id' => 0], ['object_post_id' => $base]); $clear(); $count = count($snapshot()['entities']); $sync();
    $expect(count($snapshot()['entities']) === $count && (int) $service->get_identifier_by_namespace_value('archive_institution', 'institution:55')['entity_id'] === (int) $museum['id'], 'Placeholder institution reuses unique strong identity without rewriting its source identifier');
    foreach (['placeholder_lookup','placeholder_entity'] as $stage) {
        $clear(); $baseline = $snapshot(); $hits = 0; $matched = false;
        $filter = static function (string $sql) use ($stage,$tables,$museum,&$hits,&$matched): string {
            $compact = preg_replace('/\s+/', ' ', $sql);
            $lookup = str_contains($compact, 'archive-museum') && str_contains($compact, "FROM {$tables['names']}");
            $match = $stage === 'placeholder_lookup' ? $lookup : ($matched && str_contains($compact, "FROM {$tables['entities']} WHERE id = " . $museum['id'] . ' LIMIT 1'));
            $matched = $matched || $lookup;
            if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_graph_archive_placeholder_failure';
        };
        $fail(static fn() => $with_filter($filter, $sync)); $expect($hits === 1 && $snapshot() === $baseline, 'Failed placeholder lookup or final entity read cannot create duplicates or partially replace graph');
    }
    $stage = 'empty_projection'; $seed();
    $wpdb->query("DELETE FROM {$tables['archive_relations']}"); $wpdb->delete($wpdb->postmeta, ['post_id' => $base]); $wpdb->update($tables['objects'], ['institution_name' => ''], ['object_post_id' => $base]); $clear(); $sync();
    $expect($service->get_relations_for_entity(10, '', ['source_system' => 'archive_projection']) === [] && count($service->get_relations_for_entity(10, 'person', ['source_system' => 'manual'])) === 1, 'Intentionally empty projections clear only owned families');
    $stage = 'source_fallbacks'; $seed(); $wpdb->update($tables['objects'], ['object_key' => '', 'source_id' => ''], ['object_post_id' => $base]); $wpdb->update($wpdb->posts, ['post_name' => ''], ['ID' => $base]); $clear(); $entity = $sync();
    $expect($entity['source_id'] === 'wp-id:' . $base && $entity['canonical_slug'] === 'archive-object-' . $base && $service->get_identifier_by_namespace_value('archive_source_id', 'object-1') === null, 'Source key, slug fallback and empty external identifier semantics retained');
    $stage = 'invalid_local_place'; $seed(); $wpdb->update($tables['archive_relations'], ['target_post_id' => $base + 2], ['relation_family' => 'local_place']); $clear(); $sync();
    $expect(count($service->get_relations_for_entity(10, 'place', ['source_system' => 'archive_projection'])) === 1 && $service->find_entity_by_post('place', $base + 2) === null, 'Wrong-type local target is skipped without a spurious place');
    $stage = 'named_privacy'; $seed(); $sync();
    foreach ($snapshot()['entities'] as $row) {
        if (in_array($row['source_system'], ['archive_person','archive_person_organization_label','archive_institution','archive_place'], true)) { $expect(!(int) $row['is_public'] && $row['search_visibility'] === 'hidden', 'Named archive targets remain hidden by default'); }
    }
    $stage = 'metadata_hook'; $seed(); $baseline = $snapshot(); iss_graph_sync_archive_object_on_relevant_meta_change(1, $base, 'unrelated_meta'); $expect($snapshot() === $baseline, 'Unrelated metadata leaves graph unchanged');
    iss_graph_sync_archive_object_on_relevant_meta_change(1, $base, ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META); $expect($snapshot() !== $baseline, 'Relevant metadata hook invokes checked synchronization');
    foreach (['object_update','object_created_at','object_source_read','relation_source_read','relation_payload_read'] as $stage) {
        $seed(); if ($stage === 'object_created_at') { $wpdb->update($tables['objects'], ['created_at' => '0000-00-00 00:00:00'], ['object_post_id' => $base]); }
        if ($stage === 'relation_payload_read') { $wpdb->query("DELETE FROM {$tables['archive_relations']}"); }
        $clear(); $baseline = $snapshot(); $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,$wpdb,$base,&$hits): string {
            $match = match ($stage) {
                'object_update' => str_starts_with($sql, "UPDATE `{$tables['objects']}`"),
                'object_created_at' => str_starts_with($sql, "UPDATE `{$tables['objects']}` SET `created_at`"),
                'object_source_read','relation_source_read' => str_contains($sql, "WHERE ID = $base FOR UPDATE"),
                'relation_payload_read' => str_contains($sql, "meta_value FROM {$wpdb->postmeta}"),
            };
            if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_graph_archive_owner_failure';
        };
        $call = match ($stage) { 'relation_source_read' => static fn() => $archive_relations->sync_object_relations($base), 'relation_payload_read' => static fn() => $archive_relations->get_projection_for_post($base), default => static fn() => $objects->sync_object_post($base) };
        $fail(static fn() => $with_filter($filter, $call)); $expect($hits === 1 && $graph_only($snapshot()) === $graph_only($baseline), 'Projection owner source/update errors are explicit before graph work');
    }
    $expect(has_action('save_post', 'iss_graph_sync_archive_object_on_save') === 40 && has_action('updated_post_meta', 'iss_graph_sync_archive_object_on_relevant_meta_change') === 20, 'Hook priorities retained');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    $clear(); $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $original && $read($wpdb->options) === $original_options, 'Real graph/archive projections, queue and options unchanged; fixture posts/meta absent');
if ($error !== null) { WP_CLI::error("After {$checks} checks ($stage): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Archive graph atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
