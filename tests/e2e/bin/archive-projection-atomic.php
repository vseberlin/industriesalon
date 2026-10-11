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
$base = 2147478500;
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
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): Throwable { $error = null; try { $call(); } catch (Throwable $caught) { $error = $caught; } $expect($error !== null, 'Failure reaches caller'); return $error; };
$prepare = static function (bool $cold = false) use ($seed,$wpdb,$base,$archive_relations,$clear): void {
    $seed($cold);
    foreach ([ISS_WF_IMPORT_OBJECT_TAGS_META => 'Tag', ISS_WF_IMPORT_OBJECT_COLLECTIONS_META => 'Collection', ISS_WF_IMPORT_OBJECT_SERIES_META => 'Series'] as $key => $name) {
        $wpdb->insert($wpdb->postmeta, ['post_id' => $base, 'meta_key' => $key, 'meta_value' => maybe_serialize([['source_id' => '200', 'name' => $name]])]);
    }
    $clear(); if (!$cold) { $archive_relations->sync_object_relations($base); } $clear();
};
$change = static function () use ($wpdb,$base,$objects,$archive_relations): void {
    $objects->get_projection_for_post($base); $archive_relations->get_projection_for_post($base);
    $wpdb->update($wpdb->posts, ['post_title' => 'Changed archive title'], ['ID' => $base]);
    $wpdb->update($wpdb->postmeta, ['meta_value' => maybe_serialize([['source_id' => '101','name' => 'Changed Person','type' => 'creator','note' => 'Changed provenance']])], ['post_id' => $base, 'meta_key' => ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META]);
    wp_cache_delete($base, 'post_meta'); // WordPress owns canonical source metadata caching.
};
$relations_sync = static fn() => $archive_relations->sync_object_relations($base);
$object_sync = static fn() => $objects->sync_object_post($base);
$created = []; $error = null; $stage = 'success'; $old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create fixture'); } $created[] = $table; }
    foreach ($original_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Cannot shadow options'); } }
    foreach ([false,true] as $cold) {
        $prepare($cold); $baseline = $snapshot(); $sqls = [];
        $with_filter(static function (string $sql) use (&$sqls): string { $sqls[] = $sql; return $sql; }, $relations_sync);
        $projection = $archive_relations->get_projection_for_post($base);
        foreach (['tags' => 1,'collections' => 1,'series' => 1,'places' => 1,'people' => 2,'events' => 1,'related_objects' => 1,'local_places' => 1] as $key => $count) { $expect(count($projection[$key]) === $count, "Complete $key family saved"); }
        $rows = $snapshot()['archive_relations'];
        $expect(array_map('intval', array_column($rows, 'position')) === range(0, 8), 'Cross-family order preserved');
        $expect($projection['people'][0]['note'] === 'Person provenance' && $projection['local_places'][0]['weight'] == 7, 'Provenance and local place weight retained');
        $expect(count(array_filter($sqls, static fn(string $sql): bool => $sql === 'COMMIT')) === 1, 'Required object creation and replacement share one commit');
        $expect(array_diff_key($snapshot(), ['objects' => true,'archive_relations' => true]) === array_diff_key($baseline, ['objects' => true,'archive_relations' => true]), 'Graph and canonical sources unchanged');
    }
    foreach ([false,true] as $cold) {
        foreach (['state','engine','begin','lock','metadata','object_read','delete','tag','collection','series','place','person','event','object','local_place','local_title','readback','commit','php'] as $stage) {
            $prepare($cold); $change(); $baseline = $snapshot(); $hits = 0; $last_insert = false;
            $filter = static function (string $sql) use ($stage,$tables,$wpdb,$base,&$hits,&$last_insert): string {
                $flat = preg_replace('/\s+/', ' ', $sql);
                $insert = str_starts_with($sql, "INSERT INTO `{$tables['archive_relations']}`");
                $match = match ($stage) {
                    'state','engine' => str_contains($sql, 'ENGINE AS engine'), 'begin' => $sql === 'START TRANSACTION',
                    'lock' => str_contains($sql, "WHERE ID = $base FOR UPDATE"),
                    'metadata' => str_contains($sql, "meta_value FROM {$wpdb->postmeta}"),
                    'object_read' => str_contains($sql, "FROM {$tables['objects']}"),
                    'delete' => str_starts_with($sql, "DELETE FROM `{$tables['archive_relations']}`"),
                    'local_title' => str_contains($sql, 'WHERE ID = ' . ($base + 1) . ' LIMIT 1'),
                    'readback' => $last_insert && str_contains($flat, "FROM {$tables['archive_relations']}"),
                    'commit' => $sql === 'COMMIT',
                    default => $insert && str_contains($sql, "'" . ($stage === 'php' ? 'local_place' : $stage) . "'"),
                };
                $last_insert = $last_insert || ($insert && str_contains($sql, "'local_place'"));
                if (!$match || $hits) { return $sql; } ++$hits;
                if ($stage === 'php') { throw new Error('Injected late archive replacement throwable'); }
                if ($stage === 'engine') { return str_replace('ENGINE AS engine', "'MyISAM' AS engine", $sql); }
                return 'SELECT iss_archive_projection_injected_failure';
            };
            $fail(static fn() => $with_filter($filter, $relations_sync));
            $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage restores full warm/cold projection boundary");
            $cached = $objects->get_projection_for_post($base);
            $expect($cold ? $cached === null : $cached['title'] === 'Archive Object (ARC)', 'Object cache reflects rollback');
            if (!$cold) { $expect($archive_relations->get_projection_for_post($base)['people'][0]['name'] === 'Archive Person', 'Relation cache reflects complete old projection'); }
            $relations_sync(); $fresh = $archive_relations->get_projection_for_post($base);
            $expect($fresh['people'][0]['name'] === 'Changed Person' && count($fresh['people']) === 1 && count($snapshot()['archive_relations']) === 8, 'Retry replaces all families with current source');
        }
    }
    foreach ([false,true] as $cold) {
        foreach (array_merge(['state','engine','begin','lock','metadata','read','write','readback','commit'], $cold ? [] : ['created_at','ignored_update']) as $stage) {
            $prepare($cold); $change();
            if ($stage === 'created_at') { $wpdb->update($tables['objects'], ['created_at' => '0000-00-00 00:00:00'], ['object_post_id' => $base]); }
            $baseline = $snapshot(); $hits = 0; $written = false;
            $filter = static function (string $sql) use ($stage,$tables,$wpdb,$base,&$hits,&$written): string {
                $write = str_starts_with($sql, "UPDATE `{$tables['objects']}`") || str_starts_with($sql, "INSERT INTO `{$tables['objects']}`");
                $read = str_contains($sql, "FROM {$tables['objects']}");
                $match = match ($stage) {
                    'state','engine' => str_contains($sql, 'ENGINE AS engine'), 'begin' => $sql === 'START TRANSACTION',
                    'lock' => str_contains($sql, "WHERE ID = $base FOR UPDATE"), 'metadata' => str_contains($sql, "meta_value FROM {$wpdb->postmeta}"),
                    'read' => $read, 'write','ignored_update' => $write, 'readback' => $written && $read,
                    'created_at' => str_starts_with($sql, "UPDATE `{$tables['objects']}` SET `created_at`"), 'commit' => $sql === 'COMMIT',
                };
                $written = $written || $write;
                if (!$match || $hits) { return $sql; } ++$hits;
                if ($stage === 'engine') { return str_replace('ENGINE AS engine', "'MyISAM' AS engine", $sql); }
                if ($stage === 'ignored_update') { return "UPDATE {$tables['objects']} SET title = title WHERE object_post_id = $base"; }
                return 'SELECT iss_archive_object_injected_failure';
            };
            $fail(static fn() => $with_filter($filter, $object_sync));
            $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage restores complete object save");
            $object_sync(); $expect($objects->get_projection_for_post($base)['title'] === 'Changed archive title', 'Object retry succeeds and refreshes cached projection');
            if (!$cold) { $expect($archive_relations->get_projection_for_post($base)['object']['title'] === 'Changed archive title', 'Object save invalidates embedded relation object cache'); }
        }
    }
    $stage = 'ignored_delete'; $prepare(); $change(); $baseline = $snapshot(); $hits = 0;
    $filter = static function (string $sql) use ($tables,&$hits): string { if (str_starts_with($sql, "DELETE FROM `{$tables['archive_relations']}`")) { ++$hits; return "DELETE FROM {$tables['archive_relations']} WHERE object_id = 0"; } return $sql; };
    $fail(static fn() => $with_filter($filter, $relations_sync)); $expect($hits === 1 && $snapshot() === $baseline, 'Ignored delete cannot retain old plus new rows');

    $stage = 'nested';
    foreach ([false,true] as $cold) {
        foreach ([false,true] as $autocommit_off) {
            foreach (['success','late','savepoint','release'] as $stage) {
                $prepare($cold); $change(); $baseline = $snapshot();
                $wpdb->query($autocommit_off ? 'SET autocommit = 0' : 'START TRANSACTION');
                $wpdb->update($wpdb->posts, ['post_title' => 'Caller pending'], ['ID' => $base + 2]); $pending = $snapshot(); $hits = 0;
                $filter = static function (string $sql) use ($stage,$tables,&$hits): string {
                    $match = match ($stage) { 'late' => str_starts_with($sql, "INSERT INTO `{$tables['archive_relations']}`") && str_contains($sql, "'local_place'"), 'savepoint' => str_starts_with($sql, 'SAVEPOINT '), 'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '), default => false };
                    if (!$match || $hits) { return $sql; } ++$hits; return 'SELECT iss_archive_nested_failure';
                };
                if ($stage === 'success') {
                    $relations_sync(); $object_sync();
                    $expect($objects->get_projection_for_post($base)['title'] === 'Changed archive title' && $archive_relations->get_projection_for_post($base)['people'][0]['name'] === 'Changed Person', 'Pending owner projections readable without stale caches');
                } else {
                    $fail(static fn() => $with_filter($filter, $relations_sync));
                    $expect($hits === 1 && $snapshot() === $pending, 'Nested failure preserves earlier caller work');
                }
                $expect($transaction() === 1, 'Caller retains final transaction decision');
                $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
                $expect($snapshot() === $baseline, 'Caller rollback restores projections and earlier caller writes');
                $object = $objects->get_projection_for_post($base); $relation = $archive_relations->get_projection_for_post($base);
                $expect($cold ? $object === null && $relation === null : $object['title'] === 'Archive Object (ARC)' && $relation['people'][0]['name'] === 'Archive Person', 'Caller rollback leaves no cached pending object or relation');
            }
        }
    }
    $stage = 'external_transaction_cache'; $prepare();
    $objects->get_projection_for_post($base); $archive_relations->get_projection_for_post($base);
    $wpdb->query('START TRANSACTION');
    $wpdb->update($tables['objects'], ['title' => 'External pending'], ['object_post_id' => $base]);
    $wpdb->update($tables['archive_relations'], ['target_name' => 'External person'], ['relation_family' => 'person']);
    $expect($objects->get_projection_for_post($base)['title'] === 'External pending' && $archive_relations->get_projection_for_post($base)['people'][0]['name'] === 'External person', 'Previously warm caches bypassed inside an external transaction');
    $wpdb->query('ROLLBACK');
    $expect($objects->get_projection_for_post($base)['title'] === 'Archive Object (ARC)' && $archive_relations->get_projection_for_post($base)['people'][0]['name'] === 'Archive Person', 'External rollback evicts transaction observations');
    $sqls = [];
    $with_filter(static function (string $sql) use (&$sqls): string { $sqls[] = $sql; return $sql; }, static function () use ($objects,$archive_relations,$base): void { $objects->get_projection_for_post($base); $archive_relations->get_projection_for_post($base); });
    $expect(count($sqls) === 2 && count(array_filter($sqls, static fn(string $sql): bool => str_contains($sql, '@@SESSION.in_transaction'))) === 2, 'Autocommit getters reuse cached projection rows after checking transaction state');
    $state_failure = static fn(string $sql): string => str_contains($sql, '@@SESSION.in_transaction') ? 'SELECT iss_archive_cache_state_failure' : $sql;
    $fail(static fn() => $with_filter($state_failure, static fn() => $objects->get_projection_for_post($base)));
    $fail(static fn() => $with_filter($state_failure, static fn() => $archive_relations->get_projection_for_post($base)));

    $stage = 'lazy_metadata_retry'; $prepare(); $wpdb->query("DELETE FROM {$tables['archive_relations']}"); $clear();
    $filter = static fn(string $sql): string => str_contains($sql, "meta_value FROM {$wpdb->postmeta}") ? 'SELECT iss_archive_payload_failure' : $sql;
    $fail(static fn() => $with_filter($filter, static fn() => $archive_relations->get_projection_for_post($base)));
    $expect(count($archive_relations->get_projection_for_post($base)['people']) === 2 && count($snapshot()['archive_relations']) === 9, 'Lazy payload read retries after failed WordPress metadata cache priming');
    $stage = 'idle_autocommit_off'; $prepare(true); $baseline = $snapshot(); $wpdb->query('SET autocommit = 0');
    $expect($transaction() === 0, 'Autocommit disabled without a prior transaction'); $relations_sync();
    $expect($transaction() === 1 && $archive_relations->get_projection_for_post($base) !== null, 'Idle autocommit-off caller retains pending projection');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    $expect($snapshot() === $baseline && $objects->get_projection_for_post($base) === null && $archive_relations->get_projection_for_post($base) === null, 'Idle autocommit-off rollback restores cold projection and caches');
    $stage = 'unrelated_projection'; $prepare(); $other = $snapshot()['objects'][0]; unset($other['id']); $other['object_post_id'] = $base + 2; $other['object_key'] = 'unrelated-object';
    $wpdb->insert($tables['objects'], $other); $other_id = (int) $wpdb->insert_id;
    $other_relation = $snapshot()['archive_relations'][0]; unset($other_relation['id']); $other_relation['object_id'] = $other_id; $wpdb->insert($tables['archive_relations'], $other_relation);
    $unrelated = static fn(): array => ['object' => $wpdb->get_row("SELECT * FROM {$tables['objects']} WHERE id = $other_id", ARRAY_A), 'relations' => $wpdb->get_results("SELECT * FROM {$tables['archive_relations']} WHERE object_id = $other_id ORDER BY id", ARRAY_A)];
    $untouched = $unrelated(); $change(); $object_sync(); $relations_sync(); $expect($unrelated() === $untouched, 'Object and relation saves retain unrelated projections');

    $stage = 'zero_row_and_created_at'; $prepare(); $baseline = $snapshot();
    $same_writer = new class extends ISS_WF_Import_Object_Service {
        public function save_same(int $post_id, string $timestamp): void { $this->synchronize_projection($post_id, function (WP_Post $post) use ($post_id,$timestamp): void { $this->upsert_object_row($this->build_snapshot($post_id, $post), $timestamp); }); }
    };
    $same_writer->save_same($base, $baseline['objects'][0]['updated_at']);
    $expect($snapshot() === $baseline, 'Unchanged zero-row object update succeeds');
    $wpdb->update($tables['objects'], ['created_at' => '0000-00-00 00:00:00'], ['object_post_id' => $base]); $object_sync();
    $expect($objects->get_projection_for_post($base)['created_at'] !== '0000-00-00 00:00:00', 'Missing creation timestamp repaired in same boundary');
    $stage = 'empty';
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id = $base"); wp_cache_delete($base, 'post_meta'); $relations_sync();
    $expect($snapshot()['archive_relations'] === [], 'Empty source clears prior complete replacement'); $relations_sync(); $expect($snapshot()['archive_relations'] === [], 'Repeated empty replacement permits zero deleted rows');

    $stage = 'fresh_locked_post'; $prepare(); get_post($base); $wpdb->update($wpdb->posts, ['post_title' => 'Locked fresh title'], ['ID' => $base]); $object_sync();
    $expect($objects->get_projection_for_post($base)['title'] === 'Locked fresh title', 'Locked database post overrides a stale WordPress post cache');
    foreach (['missing','wrong_type','trash','auto-draft'] as $stage) {
        $prepare();
        if ($stage === 'missing') { $wpdb->delete($wpdb->posts, ['ID' => $base]); }
        else { $wpdb->update($wpdb->posts, $stage === 'wrong_type' ? ['post_type' => 'post'] : ['post_status' => $stage], ['ID' => $base]); }
        $baseline = $snapshot(); $object_sync(); $relations_sync(); $expect($snapshot() === $baseline, 'Unsupported source stays a no-op');
    }
    $stage = 'rollback_failure'; $prepare(); $baseline = $snapshot(); $hits = 0;
    $filter = static function (string $sql) use ($tables,&$hits): string { if ($sql === 'ROLLBACK' || (str_starts_with($sql, "INSERT INTO `{$tables['archive_relations']}`") && str_contains($sql, "'local_place'"))) { ++$hits; return 'SELECT iss_archive_rollback_failure'; } return $sql; };
    $caught = $fail(static fn() => $with_filter($filter, $relations_sync));
    $expect($hits === 2 && str_contains($caught->getMessage(), 'rollback could not be confirmed') && $caught->getPrevious() !== null, 'Unconfirmed rollback reported with original cause');
    $wpdb->query('ROLLBACK'); $expect($snapshot() === $baseline, 'Explicit recovery restores fixture after injected rollback failure');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    $clear(); $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $original && $read($wpdb->options) === $original_options, 'Real graph/archive projections, queue and options unchanged; fixture posts/meta absent');
if ($error !== null) { WP_CLI::error("After {$checks} checks ($stage): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Archive projection atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
