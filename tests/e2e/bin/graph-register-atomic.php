<?php
/** WP-CLI --exec='define("DISABLE_WP_CRON", true);': graph/options writes use temporary tables; source posts exist only in memory. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Actual schemas copied to temporary fixtures with intentional failures.
global $wpdb;
$service = iss_graph_get_service();
$tables = ['entities' => $service->get_entity_table_name(), 'names' => $service->get_name_table_name(), 'identifiers' => $service->get_identifier_table_name(), 'relations' => $service->get_relation_table_name()];
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY " . ($table === $wpdb->options ? 'option_id' : 'id'), ARRAY_A);
$snapshot = static fn(): array => array_map($read, $tables);
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$expect(!wp_using_ext_object_cache(), 'Fixture post and option caches are local to this PHP process');
$fixture_id = 2147483000;
$expect(get_post($fixture_id) === null, 'Fixture ID has no real post');
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$expect(count($admins) === 1, 'Existing administrator available for permission checks');
$original_user = get_current_user_id();
wp_set_current_user((int) $admins[0]);
$original_post = $_POST;
$original = $snapshot();
$original_options = $read($wpdb->options);
$schemas = [];
foreach (array_merge(array_values($tables), [$wpdb->options]) as $table) {
    $expect($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) === 'InnoDB', 'Fixture table supports rollback');
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    $expect(is_array($schema) && str_starts_with($schema[1], 'CREATE TABLE '), 'Read real schema');
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$post = new WP_Post((object) ['ID' => $fixture_id, 'post_type' => iss_graph_get_register_post_type(), 'post_status' => 'publish', 'post_name' => 'atomic-register-place', 'post_title' => 'New Place (NEW)', 'post_excerpt' => 'Current fixture summary', 'post_content' => '', 'post_author' => (int) $admins[0], 'post_parent' => 0, 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'guid' => 'https://example.test/atomic-register-place']);
$meta = ['register_id' => ['atomic-register-id'], 'owner' => ['Existing Works'], 'developer' => ['New Development Works'], 'operator' => ['Qualified imported claim'], '_iss_register_current_import' => [serialize(['accepted_fields' => ['operator' => 'Qualified imported claim']])]];
$cache_source = static function () use ($post,$fixture_id,$meta): void {
    wp_cache_set($fixture_id, $post, 'posts');
    wp_cache_set($fixture_id, $meta, 'post_meta');
};
$editor = ['names' => [['name' => 'Historic Plant (HIST)', 'name_type' => 'historical', 'valid_from_year' => 1900, 'valid_to_year' => 1950]], 'organizations' => [['name' => 'New Editorial Works', 'relation_type' => 'related', 'is_public' => true]], 'people' => [['name' => 'New Editorial Person', 'relation_type' => 'architect', 'is_public' => false]]];
$alias_source = iss_graph_get_alias_backfill_source_system();
$seed = static function () use ($wpdb,$tables,$fixture_id,$alias_source,$expect,$cache_source): void {
    foreach ($tables as $table) { $expect($wpdb->query("DELETE FROM `$table`") !== false, 'Reset temporary graph table'); }
    $time = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
    foreach ([[10,'place','register_post',(string) $fixture_id,'atomic-register-place','Old Place',$fixture_id],[20,'organization','manual_slug','existing-works','existing-works','Existing Works',null],[30,'place','other','other-place','other-place','Other Place',null]] as [$id,$kind,$source,$source_id,$slug,$title,$post_id]) {
        $expect($wpdb->insert($tables['entities'], $time + ['id' => $id, 'entity_kind' => $kind, 'post_id' => $post_id, 'source_system' => $source, 'source_id' => $source_id, 'canonical_slug' => $slug, 'display_title' => $title, 'summary' => 'Original', 'status' => 'publish', 'search_visibility' => 'hidden']) === 1, 'Seed place, existing target and unrelated entity');
    }
    foreach ([[1,10,'register_title','Old Place'],[2,10,'register_admin','Old manual name'],[3,10,$alias_source,'Old derived alias'],[4,20,'entity_title','Existing Works'],[5,30,'register_admin','Other manual name'],[6,10,'other_source','Preserved historical name']] as [$id,$entity,$source,$name]) {
        $expect($wpdb->insert($tables['names'], $time + ['id' => $id, 'entity_id' => $entity, 'name' => $name, 'normalized_name' => sanitize_title($name), 'source_system' => $source, 'source_ref' => 'original:' . $id]) === 1, 'Seed source-owned name');
    }
    foreach ([[1,10,'register_post','old'],[2,20,'other_source','preserve']] as [$id,$entity,$source,$value]) {
        $expect($wpdb->insert($tables['identifiers'], $time + ['id' => $id, 'entity_id' => $entity, 'namespace' => 'catalog', 'value' => $value, 'normalized_value' => $value, 'source_system' => $source]) === 1, 'Seed source-owned identifier');
    }
    foreach ([[1,10,'organization','register_meta'],[2,10,'organization','register_admin'],[3,10,'person','register_admin'],[4,10,'organization','other_source'],[5,30,'organization','register_meta']] as [$id,$entity,$family,$source]) {
        $expect($wpdb->insert($tables['relations'], $time + ['id' => $id, 'from_entity_id' => $entity, 'to_entity_id' => 20, 'relation_family' => $family, 'source_system' => $source, 'note' => 'Original']) === 1, 'Seed source-owned relation');
    }
    $cache_source();
};
$sync = static fn(?array $rows = null): ?array => iss_graph_sync_register_place_entity($fixture_id, $rows);
$fail = static function (callable $call) use ($expect): void {
    $caught = null;
    try { $call(); } catch (RuntimeException $error) { $caught = $error; }
    $expect($caught instanceof RuntimeException, 'Failure reaches caller');
};
$created = [];
$filter = null;
$error = null;
$old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) {
        $expect($wpdb->query($schema) !== false, 'Create connection-local schema copy');
        $created[] = $table;
    }
    // Preserve real option values in the shadow table so URL, role and cache APIs
    // follow the normal path without any persistent option/transient mutation.
    foreach ($original_options as $row) {
        if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Could not copy option fixture'); }
    }
    $seed();
    $baseline = $snapshot();
    $commands = [];
    $filter = static function (string $sql) use (&$commands): string { $commands[] = $sql; return $sql; };
    add_filter('query', $filter);
    $entity = $sync($editor);
    remove_filter('query', $filter);
    $filter = null;
    $expect((int) $entity['id'] === 10 && $entity['display_title'] === $post->post_title && (int) $entity['is_public'] === 1, 'Automatic place identity/title/visibility update succeeds');
    $expect($service->get_identifier_by_namespace_value('register_id', 'atomic-register-id')['entity_id'] === 10, 'Automatic identifier stored');
    $expect($service->get_names_for_entity(10, ['source_system' => 'register_admin'])[0]['name'] === 'Historic Plant (HIST)', 'Manual name saved with its own source');
    $expect(in_array('HIST', array_column($service->get_names_for_entity(10, ['source_system' => $alias_source]), 'name'), true), 'Alias refresh sees names from this same editor save');
    $auto = $service->get_relations_for_entity(10, 'organization', ['source_system' => 'register_meta']);
    $expect(array_column($auto, 'relation_type') === ['owner','developer'], 'Automatic roles preserve ordering and exclude accepted imported claims');
    $expect($service->find_entity_by_slug('organization', 'qualified-imported-claim') === null, 'Qualified imported statement never becomes an invented organization');
    $people = $service->get_relations_for_entity(10, 'person', ['source_system' => 'register_admin']);
    $expect(count($people) === 1 && $people[0]['name'] === 'New Editorial Person' && !$people[0]['is_public'], 'Manual person and privacy retained');
    $expect((int) $service->get_entity_by_id($people[0]['entity_id'])['is_public'] === 0, 'Created target entity remains hidden');
    $current = $snapshot();
    $expect($current['entities'][2] === $baseline['entities'][2] && array_slice($current['names'], 0, 2) === array_slice($baseline['names'], 4), 'Unrelated entity and names retain byte-identical rows');
    $expect($current['identifiers'][0] === $baseline['identifiers'][1] && array_slice($current['relations'], 0, 2) === array_slice($baseline['relations'], 3), 'Other-source identifiers and relations retained');
    $expect(count(array_filter($commands, static fn(string $sql): bool => $sql === 'COMMIT')) === 1 && $transaction() === 0, 'Automatic and editor work share one standalone commit');

    $seed();
    foreach (['state','engine','begin','place_lookup','place_update','place_readback','title_delete','title_insert','identifier_delete','identifier_insert','target_source','target_identifier','target_slug','target_names','target_search','target_update','target_insert','target_title','target_alias','auto_delete','auto_insert','editor_target','manual_names','manual_organization','manual_person','alias_names_read','alias_entity_read','alias_place_write','commit','php'] as $stage) {
        $hits = 0;
        $place_updated = false;
        $manual_written = false;
        $filter = static function (string $sql) use ($stage,$tables,$fixture_id,$alias_source,&$hits,&$place_updated,&$manual_written): string {
            $is_entity_update = str_starts_with($sql, "UPDATE `{$tables['entities']}`");
            $is_entity_insert = str_starts_with($sql, "INSERT INTO `{$tables['entities']}`");
            $is_name_insert = str_starts_with($sql, "INSERT INTO `{$tables['names']}`");
            $is_name_delete = str_starts_with($sql, "DELETE FROM `{$tables['names']}`");
            $is_relation_insert = str_starts_with($sql, "INSERT INTO `{$tables['relations']}`");
            if ($is_entity_update && str_contains($sql, "'place'")) { $place_updated = true; }
            if ($is_relation_insert && str_contains($sql, "'register_admin'") && str_contains($sql, "'person'")) { $manual_written = true; }
            $match = match ($stage) {
                'state','engine' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'place_lookup' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'post_id = ' . $fixture_id),
                'place_update' => $is_entity_update && str_contains($sql, "'place'"),
                'place_readback' => $place_updated && str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'WHERE id = 10'),
                'title_delete' => $is_name_delete && str_contains($sql, "'register_title'"),
                'title_insert' => $is_name_insert && str_contains($sql, "'register_title'"),
                'identifier_delete' => str_starts_with($sql, "DELETE FROM `{$tables['identifiers']}`"),
                'identifier_insert' => str_starts_with($sql, "INSERT INTO `{$tables['identifiers']}`"),
                'target_source' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, "source_id = 'existing-works'"),
                'target_identifier' => str_contains($sql, "FROM {$tables['identifiers']}") && str_contains($sql, "normalized_value = 'new-development-works'"),
                'target_slug' => str_contains($sql, "canonical_slug = 'new-development-works'"),
                'target_names' => str_contains($sql, "n.normalized_name = 'new-development-works'"),
                'target_search' => str_contains($sql, 'SELECT e.*') && str_contains($sql, 'New Development Works'),
                'target_update' => $is_entity_update && str_contains($sql, "'organization'"),
                'target_insert' => $is_entity_insert && str_contains($sql, "'New Development Works'"),
                'target_title' => $is_name_insert && str_contains($sql, "'entity_title'"),
                'target_alias' => $is_name_delete && str_contains($sql, "'$alias_source'"),
                'auto_delete' => str_starts_with($sql, "DELETE FROM `{$tables['relations']}`") && str_contains($sql, "'register_meta'"),
                'auto_insert' => $is_relation_insert && str_contains($sql, "'register_meta'"),
                'editor_target' => $is_entity_insert && str_contains($sql, "'person'"),
                'manual_names' => $is_name_insert && str_contains($sql, "'register_admin'"),
                'manual_organization' => $is_relation_insert && str_contains($sql, "'register_admin'") && str_contains($sql, "'organization'"),
                'manual_person','php' => $is_relation_insert && str_contains($sql, "'register_admin'") && str_contains($sql, "'person'"),
                'alias_names_read' => $manual_written && str_contains($sql, "FROM {$tables['names']}") && str_contains($sql, 'entity_id = 10'),
                'alias_entity_read' => $manual_written && str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'WHERE id = 10'),
                'alias_place_write' => $manual_written && $is_name_insert && str_contains($sql, "'$alias_source'"),
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected register PHP failure'); }
            if ($stage === 'engine') { return str_replace('ENGINE AS engine', "IF(TABLE_NAME = '{$tables['entities']}', 'MyISAM', ENGINE) AS engine", $sql); }
            return 'SELECT iss_graph_register_test_injected_failure';
        };
        add_filter('query', $filter);
        $fail(static fn() => $sync($editor));
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1 && $snapshot() === $baseline, $stage . ': all graph rows, target creations and aliases rolled back');
        $expect($transaction() === 0, $stage . ': transaction closed');
    }
    $hits = 0;
    $filter = static function (string $sql) use ($tables,&$hits): string {
        if ($hits === 0 && str_starts_with($sql, "INSERT INTO `{$tables['relations']}`") && str_contains($sql, "'register_meta'")) { ++$hits; return 'SELECT iss_graph_register_test_injected_failure'; }
        return $sql;
    };
    add_filter('query', $filter);
    $fail(static fn() => $sync());
    remove_filter('query', $filter);
    $filter = null;
    $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, 'Automatic-only sync restores the place, identifiers, targets and names on late relation failure');
    $wpdb->query('SELECT iss_graph_register_test_injected_failure');
    $punctuation = iss_graph_resolve_entity(['entity_kind' => 'organization', 'label' => '!!!']);
    $expect($punctuation['status'] === 'no_match', 'A skipped normalized-name lookup does not inherit an unrelated earlier SQL error');

    $bad = $editor;
    $bad['names'][0]['name'] = str_repeat('x', 3000);
    $fail(static fn() => $sync($bad));
    $expect($snapshot() === $baseline, 'Pre-SQL length rejection restores automatic writes and newly created targets');

    // Source metadata must not be treated as empty after a read failure.
    wp_cache_delete($fixture_id, 'post_meta');
    $hits = 0;
    $filter = static function (string $sql) use ($wpdb,$fixture_id,&$hits): string {
        if (str_contains($sql, "FROM {$wpdb->postmeta}") && str_contains($sql, (string) $fixture_id)) { ++$hits; return 'SELECT iss_graph_register_test_injected_failure'; }
        return $sql;
    };
    add_filter('query', $filter);
    $fail(static fn() => $sync());
    remove_filter('query', $filter);
    $filter = null;
    $cache_source();
    $expect($hits === 1 && $snapshot() === $baseline, 'Failed source read cannot clear identifiers or roles');

    foreach ([false,true] as $reject) {
        $expect($wpdb->query('START TRANSACTION') !== false, 'Caller begins transaction');
        $expect($wpdb->update($tables['names'], ['name' => 'Caller pending'], ['id' => 6]) === 1, 'Caller writes before bridge');
        $pending = $snapshot();
        if ($reject) { $fail(static fn() => $sync($bad)); $expect($snapshot() === $pending, 'Nested failure keeps caller work'); }
        else { $sync($editor); $expect($snapshot() !== $pending, 'Nested sync applies'); }
        $expect($transaction() === 1, 'Caller keeps transaction ownership');
        $expect($wpdb->query('ROLLBACK') !== false && $snapshot() === $baseline, 'Outer rollback removes complete bridge update');
    }
    $wpdb->query('SET autocommit = 0');
    $sync($editor);
    $expect($transaction() === 1, 'Disabled autocommit retained');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    $expect($snapshot() === $baseline, 'Disabled-autocommit caller can undo bridge');

    // The nullable entity writer must accept a real zero-row update, and report
    // read/insert/update failures without returning an earlier successful row.
    $data = $service->get_entity_by_id(20);
    $affected = null;
    $after_update = false;
    $filter = static function (string $sql) use ($wpdb,$tables,&$affected,&$after_update): string {
        if ($after_update) { $affected = $wpdb->rows_affected; $after_update = false; }
        if (str_starts_with($sql, "UPDATE `{$tables['entities']}`")) { $after_update = true; return preg_replace('/\b\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\b/', '2026-01-01 00:00:00', $sql); }
        return $sql;
    };
    add_filter('query', $filter);
    $unchanged = $service->upsert_entity($data);
    remove_filter('query', $filter);
    $filter = null;
    $expect($unchanged !== null && $affected === 0 && $snapshot() === $baseline, 'Zero-row entity update succeeds');
    foreach (['id','source','slug','insert'] as $stage) {
        $input = ['entity_kind' => 'organization', 'source_system' => 'fixture', 'source_id' => 'new', 'canonical_slug' => 'new-direct', 'display_title' => 'New direct'];
        if ($stage === 'id') { $input['id'] = 20; }
        $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,&$hits): string {
            $match = match ($stage) {
                'id' => str_contains($sql, 'WHERE id = 20'),
                'source' => str_contains($sql, "source_id = 'new'"),
                'slug' => str_contains($sql, "canonical_slug = 'new-direct'"),
                'insert' => str_starts_with($sql, "INSERT INTO `{$tables['entities']}`"),
            };
            if ($match && $hits === 0) { ++$hits; return 'SELECT iss_graph_register_test_injected_failure'; }
            return $sql;
        };
        add_filter('query', $filter);
        $result = $service->upsert_entity($input);
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1 && $result === null && $snapshot() === $baseline, 'Direct entity ' . $stage . ' failure returns null without mutation');
    }

    // Exercise the real save handler, including authorization and cache ordering.
    $_POST = ['iss_graph_register_name_rows' => wp_slash(wp_json_encode($editor['names'])), 'iss_graph_register_organization_rows' => wp_slash(wp_json_encode($editor['organizations'])), 'iss_graph_register_person_rows' => wp_slash(wp_json_encode($editor['people']))];
    wp_set_current_user(0);
    iss_graph_save_register_place_graph($fixture_id, $post);
    $expect($snapshot() === $baseline, 'Unauthorized save performs no graph mutation');
    wp_set_current_user((int) $admins[0]);
    $_POST['iss_graph_register_place_graph_nonce'] = 'invalid';
    iss_graph_save_register_place_graph($fixture_id, $post);
    $expect($service->get_names_for_entity(10, ['source_system' => 'register_admin'])[0]['name'] === 'Old manual name', 'Invalid nonce leaves manual names unchanged');
    $expect(count($service->get_relations_for_entity(10, 'person', ['source_system' => 'register_admin'])) === 1 && $service->get_entity_by_id(10)['display_title'] === $post->post_title, 'Invalid nonce still permits existing automatic-sync behavior');
    $seed();
    $_POST['iss_graph_register_place_graph_nonce'] = wp_create_nonce('iss_graph_save_register_place_graph');
    $cache_version = (int) get_option('iss_register_atlas_cache_version', 1);
    $_POST['iss_graph_register_name_rows'] = wp_slash(wp_json_encode($bad['names']));
    $fail(static fn() => iss_graph_save_register_place_graph($fixture_id, $post));
    $expect($snapshot() === $baseline && (int) get_option('iss_register_atlas_cache_version', 1) === $cache_version, 'Failed authorized save rolls back auto/manual writes and skips final cache invalidation');
    $_POST['iss_graph_register_name_rows'] = wp_slash(wp_json_encode($editor['names']));
    $cache_tx = null;
    $option_filter = static function ($value) use (&$cache_tx,$transaction) { $cache_tx = $transaction(); return $value; };
    add_filter('pre_update_option_iss_register_atlas_cache_version', $option_filter);
    try { iss_graph_save_register_place_graph($fixture_id, $post); }
    finally { remove_filter('pre_update_option_iss_register_atlas_cache_version', $option_filter); }
    $expect($cache_tx === 0 && (int) get_option('iss_register_atlas_cache_version', 1) > $cache_version, 'Standalone editor invalidates cache after graph commit');
    $expect($service->get_names_for_entity(10, ['source_system' => 'register_admin'])[0]['name'] === 'Historic Plant (HIST)', 'Authorized handler saves manual payload');
    $sync(['names' => [], 'organizations' => [], 'people' => []]);
    $expect($service->get_names_for_entity(10, ['source_system' => 'register_admin']) === [] && $service->get_relations_for_entity(10, '', ['source_system' => 'register_admin']) === [], 'Explicit empty editor groups clear together');
    $expect(!in_array('HIST', array_column($service->get_names_for_entity(10, ['source_system' => $alias_source]), 'name'), true), 'Cleared manual name no longer supplies generated alias');
    $expect(count($service->get_relations_for_entity(10, 'organization', ['source_system' => 'register_meta'])) === 2, 'Clearing editor groups preserves automatic source');

    // A failed first-time sync must also remove the newly created place itself.
    foreach ($tables as $table) { $wpdb->query("DELETE FROM `$table`"); }
    $empty = $snapshot();
    $fail(static fn() => $sync($bad));
    $expect($snapshot() === $empty, 'Fresh place and all target creation roll back on later rejection');
    $fresh = $sync($editor);
    $expect($fresh !== null && (int) $fresh['id'] !== 10 && count($read($tables['entities'])) === 5, 'Fresh automatic/editor sync creates one place and four targets');
    $expect(get_post_meta($fixture_id) === $meta, 'Source metadata untouched');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter) { remove_filter('query', $filter); }
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    wp_cache_delete($fixture_id, 'posts');
    wp_cache_delete($fixture_id, 'post_meta');
    wp_cache_delete('alloptions', 'options');
    wp_cache_delete('notoptions', 'options');
    wp_cache_delete('iss_register_atlas_cache_version', 'options');
    $_POST = $original_post;
    wp_set_current_user($original_user);
    $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original && $read($wpdb->options) === $original_options, 'Real graph and option rows remain byte-identical');
$expect(get_post($fixture_id) === null, 'No real source post was created');
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success($checks . ' register graph checks passed; temporary fixtures removed and real rows unchanged.');
