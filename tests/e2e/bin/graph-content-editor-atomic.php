<?php
/** WP-CLI with DISABLE_WP_CRON: shadow tables and process-local source posts; no real editorial writes. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated real schemas and deliberate SQL failures.
global $wpdb;
$service = iss_graph_get_service();
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled; source caches process-local');
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$tables = [
    'entities' => $service->get_entity_table_name(), 'names' => $service->get_name_table_name(),
    'identifiers' => $service->get_identifier_table_name(), 'relations' => $service->get_relation_table_name(),
    'evidence' => $service->get_evidence_table_name(), 'search' => $service->get_search_table_name(),
    'people' => $service->get_person_facts_table_name(), 'organizations' => $service->get_organization_facts_table_name(),
    'queue' => $service->get_dirty_queue_table_name(),
    'terms' => $wpdb->terms, 'term_taxonomy' => $wpdb->term_taxonomy,
    'term_relationships' => $wpdb->term_relationships, 'postmeta' => $wpdb->postmeta,
];
$order = [$wpdb->terms => 'term_id', $wpdb->term_taxonomy => 'term_taxonomy_id', $wpdb->term_relationships => 'object_id, term_taxonomy_id', $wpdb->postmeta => 'meta_id', $wpdb->options => 'option_id'];
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY " . ($order[$table] ?? 'id'), ARRAY_A);
$snapshot = static fn(): array => array_map($read, $tables);
$base = 2147480800;
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 3)) === 0, 'Source fixture IDs absent');
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$expect(count($admins) === 1, 'Existing administrator for authorization checks');
$original_user = get_current_user_id();
wp_set_current_user((int) $admins[0]);
$original_post = $_POST;
$original = $snapshot();
$original_options = $read($wpdb->options);
$schemas = [];
foreach (array_merge(array_values($tables), [$wpdb->options]) as $table) {
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    if (!is_array($schema) || !str_starts_with($schema[1], 'CREATE TABLE ')) { throw new RuntimeException('Could not read fixture schema'); }
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$post = new WP_Post((object) ['ID' => $base, 'post_type' => 'post', 'post_status' => 'publish', 'post_name' => 'editor-content', 'post_title' => 'Editor Content (EDIT)', 'post_excerpt' => 'Editor summary', 'post_content' => '', 'post_author' => (int) $admins[0], 'post_parent' => 0, 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'guid' => 'https://example.test/editor-content']);
$venue = new WP_Post((object) ['ID' => $base + 1, 'post_type' => 'register_place', 'post_status' => 'publish', 'post_name' => 'editor-venue', 'post_title' => 'Editor Venue', 'post_excerpt' => '', 'post_content' => '', 'post_author' => (int) $admins[0], 'post_parent' => 0]);
$source_meta = [];
$venue_meta = ['register_id' => ['editor-venue'], 'owner' => ['Editor Venue Owner'], 'place_visibility' => ['private']];
$cache_source = static function () use ($post,$venue,$base,&$source_meta,$venue_meta): void {
    wp_cache_set($base, $post, 'posts'); wp_cache_set($base, $source_meta, 'post_meta');
    wp_cache_set($base + 1, $venue, 'posts'); wp_cache_set($base + 1, $venue_meta, 'post_meta');
};
$payload = [
    'person' => [['entity_id' => 31, 'relation_type' => 'author', 'valid_from_year' => 1900, 'valid_to_year' => 1950, 'is_public' => false]],
    'organization' => [['entity_id' => 41, 'relation_type' => 'publisher', 'is_public' => true]],
];
$set_payload = static function (array $rows) use ($admins): void {
    wp_set_current_user((int) $admins[0]);
    $_POST = ['iss_graph_content_relations_nonce' => wp_create_nonce('iss_graph_save_content_relations')];
    foreach ($rows as $kind => $values) { $_POST['iss_graph_content_' . $kind . '_rows'] = wp_slash(wp_json_encode($values)); }
};
$seed = static function () use ($wpdb,$tables,$base,$post,$cache_source,$set_payload,$payload): void {
    foreach ($tables as $table) { if ($wpdb->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Could not reset fixture'); } }
    $time = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
    $insert = static function (string $table, array $row) use ($wpdb,$time): void {
        if ($wpdb->insert($table, $row + $time) !== 1) { throw new RuntimeException('Fixture insert failed: ' . $wpdb->last_error); }
    };
    foreach ([[10,iss_graph_get_entity_kind_for_post_type($post->post_type),$base,null],[20,'place',null,null],[30,'person',null,$base + 2],[31,'person',null,$base + 2],[40,'organization',null,$base + 3],[41,'organization',null,$base + 3],[50,'person',null,null]] as [$id,$kind,$post_id,$profile]) {
        $insert($tables['entities'], ['id' => $id, 'entity_kind' => $kind, 'post_id' => $post_id, 'profile_post_id' => $profile, 'source_system' => $post_id ? 'wp_post' : 'fixture', 'source_id' => $post_id ? (string) $post_id : (string) $id, 'canonical_slug' => 'editor-fixture-' . $id, 'display_title' => 'Target ' . $id, 'status' => 'publish', 'is_public' => 1]);
    }
    $insert($tables['names'], ['id' => 1, 'entity_id' => 10, 'name' => 'Unrelated historic name', 'normalized_name' => 'unrelated-historic-name', 'source_system' => 'other_source']);
    foreach ([[1,10,30,'person','content_admin'],[2,10,40,'organization','content_admin'],[3,10,20,'place','content_admin'],[4,10,30,'person','content_native'],[5,20,40,'organization','other_source']] as [$id,$from,$to,$family,$source]) {
        $insert($tables['relations'], ['id' => $id, 'from_entity_id' => $from, 'to_entity_id' => $to, 'relation_family' => $family, 'source_system' => $source, 'note' => 'Original']);
    }
    $cache_source(); $set_payload($payload);
};
$save = static fn() => iss_graph_save_public_content_relations($base, $post);
$manual = static fn(int $entity = 10): array => $service->get_relations_for_entity($entity, '', ['source_system' => 'content_admin']);
$with_filter = static function (callable $filter, callable $call) {
    add_filter('query', $filter);
    try { return $call(); } finally { remove_filter('query', $filter); }
};
$fail = static function (callable $call) use ($expect): void {
    $error = null;
    try { $call(); } catch (RuntimeException $caught) { $error = $caught; }
    $expect($error instanceof RuntimeException, 'Failure reaches caller');
};
$after_graph = [];
$after_search = [];
$stop_derived = false;
$terms_hook = static function ($object_id) use ($base,&$after_graph,$snapshot,$transaction,&$stop_derived): void {
    if ((int) $object_id !== $base) { return; }
    $after_graph[] = ['rows' => $snapshot(), 'transaction' => $transaction()];
    if ($stop_derived) { throw new RuntimeException('Injected derived-model failure'); }
};
$search_hook = static function (array $map) use (&$after_search,$transaction): array { $after_search[] = $transaction(); return $map; };
$created = [];
$error = null;
$old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) {
        if ($wpdb->query($schema) === false) { throw new RuntimeException('Could not create shadow table'); }
        $created[] = $table;
    }
    foreach ($original_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Could not shadow options'); } }
    add_action('set_object_terms', $terms_hook);
    add_filter('iss_graph_public_search_post_type_map', $search_hook);
    $seed();
    $baseline = $snapshot();
    $save();
    $rows = $manual();
    $people = array_values(array_filter($rows, static fn(array $row): bool => $row['relation_family'] === 'person'));
    $organizations = array_values(array_filter($rows, static fn(array $row): bool => $row['relation_family'] === 'organization'));
    $expect(count($people) === 1 && $people[0]['entity_id'] === 31 && $people[0]['relation_type'] === 'author' && !$people[0]['is_public'], 'Manual person and private relation saved');
    $expect($people[0]['valid_from_year'] === 1900 && $people[0]['valid_to_year'] === 1950 && $people[0]['source_ref'] === 'post:' . $base, 'Dates and source reference retained');
    $expect(count($organizations) === 1 && $organizations[0]['entity_id'] === 41 && $organizations[0]['relation_type'] === 'publisher' && $organizations[0]['is_public'], 'Manual organization saved');
    $expect($people[0]['source_field'] === 'iss_graph_content_person_rows' && $organizations[0]['source_field'] === 'iss_graph_content_organization_rows', 'Source field ownership retained');
    $expect(count($after_graph) === 1 && $after_graph[0]['transaction'] === 0 && $after_search !== [] && array_unique($after_search) === [0], 'Read-model and search callbacks run after standalone graph commit');
    $expect($after_graph[0]['rows']['entities'] === $baseline['entities'] && $after_graph[0]['rows']['names'] === $baseline['names'], 'Existing source entity not recreated or rewritten within manual boundary');
    $expect(array_values(array_filter($after_graph[0]['rows']['relations'], static fn(array $row): bool => !in_array((int) $row['id'], [1,2], true) && $row['source_system'] !== 'content_admin')) === array_slice($baseline['relations'], 3), 'Automatic and unrelated relations unchanged within manual boundary');
    $expect(count($rows) === 3, 'Unrelated manual place family retained');
    $search = $read($tables['search']);
    $expect(count($search) === 1 && str_contains($search[0]['search_text'], 'Target 41') && !str_contains($search[0]['search_text'], 'Target 31'), 'Derived public search includes public organization, excludes private person');

    foreach (['state','engine','begin','source_read','person_read','organization_read','person_delete','person_insert','organization_delete','organization_insert','commit','php'] as $stage) {
        $seed(); $baseline = $snapshot(); $after_graph = []; $after_search = []; $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,$base,&$hits): string {
            $read_entity = str_contains($sql, "FROM {$tables['entities']}");
            $insert = str_starts_with($sql, "INSERT INTO `{$tables['relations']}`") && str_contains($sql, "'content_admin'");
            $delete = str_starts_with($sql, "DELETE FROM `{$tables['relations']}`") && str_contains($sql, "'content_admin'");
            $match = match ($stage) {
                'state','engine' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'source_read' => $read_entity && str_contains($sql, 'post_id = ' . $base),
                'person_read' => $read_entity && str_contains($sql, 'WHERE id = 31'),
                'organization_read' => $read_entity && str_contains($sql, 'WHERE id = 41'),
                'person_delete' => $delete && str_contains($sql, "'person'"),
                'person_insert' => $insert && str_contains($sql, "'person'"),
                'organization_delete' => $delete && str_contains($sql, "'organization'"),
                'organization_insert','php' => $insert && str_contains($sql, "'organization'"),
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected editor PHP failure'); }
            if ($stage === 'engine') { return str_replace('ENGINE AS engine', "IF(TABLE_NAME = '{$tables['relations']}', 'MyISAM', ENGINE) AS engine", $sql); }
            return 'SELECT iss_graph_content_editor_injected_failure';
        };
        $fail(static fn() => $with_filter($filter, $save));
        $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage restores both groups and closes transaction");
        $expect($after_graph === [] && $after_search === [], "$stage skips derived writes after failure");
    }
    $late = static fn(string $sql): string => str_starts_with($sql, "INSERT INTO `{$tables['relations']}`") && str_contains($sql, "'content_admin'") && str_contains($sql, "'organization'") ? 'SELECT iss_graph_content_editor_late_failure' : $sql;
    $seed(); $baseline = $snapshot();
    foreach ([false,true] as $reject) {
        $wpdb->query('START TRANSACTION');
        $wpdb->update($tables['names'], ['name' => 'Caller pending'], ['id' => 1]);
        $pending = $snapshot();
        if ($reject) { $fail(static fn() => $with_filter($late, $save)); $expect($snapshot() === $pending, 'Nested failure retains caller pending work'); }
        else { $save(); $expect($snapshot() !== $pending, 'Nested success applies editor save'); }
        $expect($transaction() === 1, 'Caller retains transaction ownership');
        $wpdb->query('ROLLBACK');
        $expect($snapshot() === $baseline, 'Caller rollback restores graph and derived tables');
    }
    $wpdb->query('SET autocommit = 0'); $save();
    $expect($transaction() === 1, 'Disabled-autocommit caller keeps ownership');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    $expect($snapshot() === $baseline, 'Disabled-autocommit rollback restores rows');

    // A missing source requires automatic content and nested venue creation inside this boundary.
    $post->post_type = 'veranstaltung'; $source_meta = ['iss_primary_place_id' => [(string) ($base + 1)]];
    $seed(); $service->delete_entity(10); $baseline = $snapshot();
    $fail(static fn() => $with_filter($late, $save));
    $expect($snapshot() === $baseline, 'Late manual failure removes newly created source, venue, owner and automatic graph writes');
    $save();
    $fresh = $service->find_entity_by_post(iss_graph_get_entity_kind_for_post_type('veranstaltung'), $base);
    $expect($fresh !== null && count($manual((int) $fresh['id'])) === 2, 'Missing source creation and both manual groups succeed');
    $expect($service->find_entity_by_post('place', $base + 1) !== null && $service->find_entity_by_slug('organization', 'editor-venue-owner') !== null, 'Nested venue and owner creation retained on success');
    $expect(wp_cache_get($base, 'post_meta') === $source_meta, 'Source metadata retained');
    $post->post_type = 'post'; $source_meta = [];
    $seed(); $service->delete_entity(10); $baseline = $snapshot();
    // An accepted editor payload cannot silently succeed when source creation is required but unavailable.
    $missing_source = static function (string $sql) use ($tables,$base): string {
        // Remove the cached source after the permission gate, at the entity lookup.
        if (str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'post_id = ' . $base)) { wp_cache_delete($base, 'posts'); }
        return $sql;
    };
    $fail(static fn() => $with_filter($missing_source, $save));
    $expect($snapshot() === $baseline, 'Missing required source aborts without partial manual save');

    foreach (iss_graph_get_content_relation_post_types() as $type) {
        $post->post_type = $type; $post->post_status = 'draft'; $seed(); $save();
        $expect(count($manual()) === 3 && (int) $service->get_entity_by_id(10)['is_public'] === 0, "$type keeps manual groups and draft visibility");
    }
    $post->post_type = 'post'; $post->post_status = 'publish';
    foreach (['missing','invalid','non_string'] as $mode) {
        $seed();
        if ($mode === 'missing') { unset($_POST['iss_graph_content_person_rows'], $_POST['iss_graph_content_organization_rows']); }
        else { $_POST['iss_graph_content_person_rows'] = $_POST['iss_graph_content_organization_rows'] = $mode === 'invalid' ? 'not json' : []; }
        $save();
        $expect(count($manual()) === 1 && $manual()[0]['relation_family'] === 'place', "$mode payload retains existing empty-clearing contract");
    }
    $seed(); $set_payload(['person' => [null, ['entity_id' => 999], ['entity_id' => 41], ['entity_id' => 50], ['entity_id' => 31, 'relation_type' => 'invalid', 'is_public' => true]], 'organization' => []]); $save();
    $people = $service->get_relations_for_entity(10, 'person', ['source_system' => 'content_admin']);
    $expect(count($people) === 1 && $people[0]['relation_type'] === 'related' && $people[0]['position'] === 4, 'Invalid/missing/wrong-kind/profileless targets skipped; type fallback and original position retained');
    $wpdb->query('SELECT iss_graph_content_editor_stale_failure');
    $expect(iss_graph_sanitize_content_relation_rows([['entity_id' => 0]], 'person') === [], 'Skipped zero target does not inherit stale DB error');

    foreach (['unauthorized','missing_nonce','bad_nonce','unsupported','revision','autosave_revision'] as $guard) {
        $seed(); $baseline = $snapshot(); $after_graph = []; $after_search = [];
        if ($guard === 'unauthorized') { wp_set_current_user(0); }
        elseif ($guard === 'missing_nonce') { unset($_POST['iss_graph_content_relations_nonce']); }
        elseif ($guard === 'bad_nonce') { $_POST['iss_graph_content_relations_nonce'] = 'invalid'; }
        elseif ($guard === 'unsupported') { $post->post_type = 'page'; }
        else { $revision = clone $post; $revision->post_type = 'revision'; $revision->post_parent = $base + 1; $revision->post_name = $guard === 'autosave_revision' ? ($base + 1) . '-autosave-v1' : ($base + 1) . '-revision-v1'; wp_cache_set($base, $revision, 'posts'); }
        $save();
        $expect($snapshot() === $baseline && $after_graph === [] && $after_search === [], "$guard cannot save graph or derived projections");
        $post->post_type = 'post';
    }
    $seed(); $baseline = $snapshot(); $stop_derived = true;
    $fail($save); $stop_derived = false;
    $people = $service->get_relations_for_entity(10, 'person', ['source_system' => 'content_admin']);
    $organizations = $service->get_relations_for_entity(10, 'organization', ['source_system' => 'content_admin']);
    $expect($people[0]['entity_id'] === 31 && $organizations[0]['entity_id'] === 41 && $transaction() === 0, 'Later derived failure does not falsely roll back committed manual groups');
    foreach (['trash','auto-draft'] as $status) {
        $post->post_status = $status; $seed(); $service->delete_entity(10); $save();
        $expect($service->find_entity_by_post(iss_graph_get_entity_kind_for_post_type('post'), $base) === null, "$status retains intentional no-create behavior");
    }
    $post->post_status = 'publish'; $seed(); $baseline = $snapshot();
    define('DOING_AUTOSAVE', true); $save();
    $expect($snapshot() === $baseline, 'DOING_AUTOSAVE guard retained');
    $expect(has_action('save_post', 'iss_graph_save_public_content_relations') === 52, 'Existing save priority retained');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    remove_action('set_object_terms', $terms_hook);
    remove_filter('iss_graph_public_search_post_type_map', $search_hook);
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Temporary table cleanup failed'); } }
    wp_cache_flush();
    $_POST = $original_post; wp_set_current_user($original_user);
    $wpdb->suppress_errors($old_suppression);
}
$expect($snapshot() === $original && $read($wpdb->options) === $original_options, 'Real graph, queue, terms, postmeta and options unchanged during suite');
$expect(get_post($base) === null && get_post($base + 1) === null, 'No real source or venue post created');
if ($error !== null) { WP_CLI::error("After {$checks} checks: " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Content editor graph atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
