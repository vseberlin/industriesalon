<?php
/** WP-CLI with cron disabled: all fixture posts, metadata, graph, search and options use temporary tables. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated schemas, row snapshots and intentional SQL failures.
global $wpdb;
$service = iss_graph_get_service(); $checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled; cache fixtures process-local');
$transaction = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($transaction() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$tables = ['entities' => $service->get_entity_table_name(), 'names' => $service->get_name_table_name(), 'identifiers' => $service->get_identifier_table_name(), 'relations' => $service->get_relation_table_name(), 'evidence' => $service->get_evidence_table_name(), 'search' => $service->get_search_table_name(), 'people' => $service->get_person_facts_table_name(), 'organizations' => $service->get_organization_facts_table_name(), 'queue' => $service->get_dirty_queue_table_name(), 'posts' => $wpdb->posts, 'postmeta' => $wpdb->postmeta];
$order = [$wpdb->posts => 'ID', $wpdb->postmeta => 'meta_id', $wpdb->options => 'option_id'];
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY " . ($order[$table] ?? 'id'), ARRAY_A);
$snapshot = static fn(): array => array_map($read, $tables);
$base = 2147480200; $key = iss_graph_get_entity_profile_meta_key();
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 2)) === 0, 'Fixture posts absent');
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$expect(count($admins) === 1, 'Existing administrator available');
$original_user = get_current_user_id(); $original_post = $_POST;
wp_set_current_user((int) $admins[0]);
// Read only graph and profile-binding source state from real tables, avoiding whole-DB equality claims during real editor activity.
$real_state = static fn(): array => [
    'graph' => array_map($read, array_diff_key($tables, ['posts' => true, 'postmeta' => true])),
    'bindings' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id", $key), ARRAY_A),
    'fixture_posts' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d ORDER BY ID", $base, $base + 2), ARRAY_A),
];
$original = $real_state(); $original_options = $read($wpdb->options);
$schemas = [];
foreach (array_merge(array_values($tables), [$wpdb->options]) as $table) {
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    if (!is_array($schema) || !str_starts_with($schema[1], 'CREATE TABLE ')) { throw new RuntimeException('Cannot read fixture schema'); }
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$flush_meta = static function () use ($base): void { for ($id = $base; $id <= $base + 2; ++$id) { wp_cache_delete($id, 'post_meta'); wp_cache_delete($id, 'posts'); } };
$manual = iss_graph_get_profile_alias_source_system(); $generated = iss_graph_get_alias_backfill_source_system();
$seed = static function () use ($wpdb,$tables,$base,$key,$admins,$flush_meta,$manual,$generated): void {
    foreach ($tables as $table) { if ($wpdb->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Cannot reset fixture'); } }
    $flush_meta();
    $time = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
    for ($n = 0; $n <= 2; ++$n) {
        $id = $base + $n;
        if ($wpdb->insert($wpdb->posts, ['ID' => $id, 'post_author' => (int) $admins[0], 'post_type' => iss_graph_get_entity_profile_post_type(), 'post_status' => 'publish', 'post_title' => 'Profile ' . $n, 'post_name' => 'profile-' . $n, 'post_content' => '', 'post_excerpt' => 'Profile excerpt', 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_modified' => '2026-01-01 00:00:00', 'post_modified_gmt' => '2026-01-01 00:00:00', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '']) !== 1) { throw new RuntimeException('Cannot seed post'); }
        foreach ([$key => (string) (($n + 1) * 10), 'unrelated_meta' => 'Keep ' . $n] as $meta_key => $value) {
            if ($wpdb->insert($wpdb->postmeta, ['post_id' => $id, 'meta_key' => $meta_key, 'meta_value' => $value]) !== 1) { throw new RuntimeException('Cannot seed metadata'); }
        }
    }
    foreach ([[10,'person',$base],[20,'organization',$base + 1],[30,'place',$base + 2]] as [$id,$kind,$profile]) {
        if ($wpdb->insert($tables['entities'], $time + ['id' => $id, 'entity_kind' => $kind, 'profile_post_id' => $profile, 'source_system' => 'fixture', 'source_id' => (string) $id, 'canonical_slug' => 'aliases-' . $id, 'display_title' => 'Identity ' . $id, 'has_profile' => 1, 'is_public' => 1, 'status' => 'publish', 'search_visibility' => 'public']) !== 1) { throw new RuntimeException('Cannot seed entity'); }
        foreach (['entity_title' => 'Identity ' . $id, $manual => 'Old manual ' . $id, $generated => 'Old derived ' . $id] as $source => $name) {
            if ($wpdb->insert($tables['names'], $time + ['entity_id' => $id, 'name' => $name, 'normalized_name' => sanitize_title($name), 'name_type' => 'alternative', 'source_system' => $source, 'source_ref' => 'fixture:' . $id]) !== 1) { throw new RuntimeException('Cannot seed name'); }
        }
    }
    wp_set_current_user((int) $admins[0]);
    $_POST = ['iss_graph_profile_aliases_nonce' => wp_create_nonce('iss_graph_profile_aliases'), 'iss_graph_profile_name_rows' => wp_slash(wp_json_encode([
        ['name' => '<b>Müller Gesellschaft (MG)</b>', 'name_type' => 'official', 'valid_from_year' => '1900', 'valid_to_year' => '1989'],
        ['name' => "Agnes O'Neil", 'name_type' => 'unsupported'],
        ['name' => '   '],
    ]))];
};
$save = static fn() => iss_graph_save_entity_profile_aliases($base, get_post($base));
$names = static fn(int $id, string $source): array => $service->get_names_for_entity($id, ['source_system' => $source]);
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Failure reaches caller'); };
$search_states = [];
$search_hook = static function (array $map) use (&$search_states,$transaction): array { $search_states[] = $transaction(); return $map; };
$created = []; $error = null; $old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create temporary fixture'); } $created[] = $table; }
    foreach ($original_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Cannot shadow options'); } }
    add_filter('iss_graph_public_search_post_type_map', $search_hook);
    $seed(); $baseline = $snapshot(); $save(); $after = $snapshot();
    $rows = $names(10, $manual); $aliases = $names(10, $generated);
    $expect(array_column($rows, 'name') === ['Müller Gesellschaft (MG)', "Agnes O'Neil"], 'Manual names sanitized, unslashed and ordered');
    $expect(array_column($rows, 'name_type') === ['official','alternative'] && array_column($rows, 'position') === [0,1], 'Name type allowlist and positions retained');
    $expect((int) $rows[0]['valid_from_year'] === 1900 && (int) $rows[0]['valid_to_year'] === 1989 && $rows[1]['valid_from_year'] === null, 'Name validity years retained');
    $expect(array_unique(array_column($rows, 'source_ref')) === ['profile:' . $base] && array_unique(array_column($aliases, 'source_ref')) === ['entity:10'], 'Manual and generated source labels retained');
    $expect(in_array('MG', array_column($aliases, 'name'), true) && !in_array('Old derived 10', array_column($aliases, 'name'), true), 'Aliases derived from new manual names in same save');
    $unowned = static fn(array $rows): array => array_values(array_filter($rows, static fn(array $row): bool => (int) $row['entity_id'] !== 10 || !in_array($row['source_system'], [$manual,$generated], true)));
    $expect($unowned($after['names']) === $unowned($baseline['names']), 'Other entities and source-owned names retained');
    $expect(array_diff_key($after, ['names' => true,'search' => true]) === array_diff_key($baseline, ['names' => true,'search' => true]), 'Bindings, entities, facts, identifiers, source posts, relations and queue retained');
    $expect($search_states !== [] && array_unique($search_states) === [0] && $transaction() === 0, 'Search follows standalone commit');
    $expect(str_contains($service->get_search_row_by_post($base)['search_text'], "Agnes O'Neil"), 'Search observes saved names');
    $save(); $expect(array_column($names(10, $manual), 'name') === array_column($rows, 'name'), 'Repeated unchanged save succeeds');

    foreach (['state','engine','begin','source_lock','binding_read','entity_read','manual_delete','manual_insert','manual_second_insert','alias_entity_read','alias_names_read','alias_delete','alias_insert','alias_second_insert','savepoint','release','commit','php'] as $stage) {
        $seed(); $baseline = $snapshot(); $search_states = []; $hits = 0; $seen = 0;
        $filter = static function (string $sql) use ($stage,$wpdb,$tables,$manual,$generated,&$hits,&$seen): string {
            $insert = str_starts_with($sql, "INSERT INTO `{$tables['names']}`");
            $delete = str_starts_with($sql, "DELETE FROM `{$tables['names']}`");
            $match = match ($stage) {
                'state','engine' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'source_lock' => str_contains($sql, "FROM {$wpdb->posts}") && str_contains($sql, 'FOR UPDATE'),
                'binding_read' => str_contains($sql, "SELECT meta_value FROM {$wpdb->postmeta}"),
                'entity_read' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'FOR UPDATE'),
                'manual_delete' => $delete && str_contains($sql, "'$manual'"),
                'manual_insert','manual_second_insert' => $insert && str_contains($sql, "'$manual'"),
                'alias_entity_read' => str_contains($sql, "FROM {$tables['entities']}") && !str_contains($sql, 'FOR UPDATE'),
                'alias_names_read' => str_starts_with(ltrim($sql), 'SELECT') && str_contains($sql, "FROM {$tables['names']}"),
                'alias_delete' => $delete && str_contains($sql, "'$generated'"),
                'alias_insert','alias_second_insert','php' => $insert && str_contains($sql, "'$generated'"),
                'savepoint' => str_starts_with($sql, 'SAVEPOINT '),
                'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '),
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; }
            ++$seen;
            if (str_contains($stage, 'second') && $seen < 2) { return $sql; }
            ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected alias PHP failure'); }
            if ($stage === 'engine') { return str_replace('ENGINE AS engine', "IF(TABLE_NAME = '{$wpdb->postmeta}', 'MyISAM', ENGINE) AS engine", $sql); }
            return 'SELECT iss_graph_profile_aliases_injected_failure';
        };
        $fail(static fn() => $with_filter($filter, $save));
        $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage restores both name sets and source state");
        $expect($search_states === [], "$stage skips search on failure");
    }

    $late = static fn(string $sql): string => str_starts_with($sql, "INSERT INTO `{$tables['names']}`") && str_contains($sql, "'$generated'") ? 'SELECT iss_graph_profile_aliases_late_failure' : $sql;
    $seed(); $baseline = $snapshot();
    foreach ([false,true] as $reject) {
        $wpdb->query('START TRANSACTION'); $wpdb->update($tables['names'], ['name' => 'Caller pending'], ['entity_id' => 30]); $pending = $snapshot(); $search_states = [];
        if ($reject) { $fail(static fn() => $with_filter($late, $save)); $expect($snapshot() === $pending && $search_states === [], 'Nested failure retains previous caller writes and skips search'); }
        else { $save(); $expect($snapshot() !== $pending && array_unique($search_states) === [1], 'Nested success retains outer transaction for search'); }
        $expect($transaction() === 1, 'Caller retains transaction ownership');
        $wpdb->query('ROLLBACK');
        $expect($snapshot() === $baseline, 'Caller rollback restores names and search');
    }
    $wpdb->query('SET autocommit = 0'); $save(); $expect($transaction() === 1, 'Disabled-autocommit caller retains ownership');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    $expect($snapshot() === $baseline, 'Disabled-autocommit rollback restores rows');

    foreach ([null,'[]','malformed',[]] as $payload) {
        $seed(); $baseline = $snapshot();
        if ($payload === null) { unset($_POST['iss_graph_profile_name_rows']); } else { $_POST['iss_graph_profile_name_rows'] = $payload; }
        $save();
        $expect($names(10, $manual) === [] && $names(10, $generated) === [], 'Missing, empty and malformed rows retain existing clear semantics');
        $expect($unowned($read($tables['names'])) === $unowned($baseline['names']), 'Clearing preserves other-source names');
    }
    foreach ([0,999,30] as $target) {
        $seed(); $wpdb->update($wpdb->postmeta, ['meta_value' => (string) $target], ['post_id' => $base, 'meta_key' => $key]); $flush_meta(); $baseline = $snapshot(); $search_states = [];
        $save(); $expect($snapshot() === $baseline && $search_states === [], 'Unbound, missing and unsupported entities are harmless no-ops');
    }
    // Prime a stale metadata cache, then move the stored binding without touching it.
    $seed(); get_post_meta($base, $key, true);
    $wpdb->update($wpdb->postmeta, ['meta_value' => '20'], ['post_id' => $base, 'meta_key' => $key]);
    $wpdb->update($tables['entities'], ['profile_post_id' => null], ['id' => 10]);
    $wpdb->update($tables['entities'], ['profile_post_id' => $base], ['id' => 20]);
    $previous = $names(10, $manual); $save();
    $expect($names(10, $manual) === $previous && array_column($names(20, $manual), 'name') === ['Müller Gesellschaft (MG)', "Agnes O'Neil"], 'Stored binding wins over stale cache and organization names save');
    $expect(in_array('MG', array_column($names(20, $generated), 'name'), true) && (int) $service->get_search_row_by_post($base)['entity_id'] === 20 && iss_graph_get_profile_linked_entity_id($base) === 20, 'Aliases and search see the same current binding');

    foreach (['draft','private'] as $status) {
        $seed(); $service->upsert_search_row(['target_post_id' => $base, 'title' => 'Old search', 'is_public' => true]);
        $wpdb->update($wpdb->posts, ['post_status' => $status], ['ID' => $base]); $flush_meta(); $save();
        $expect(count($names(10, $manual)) === 2 && $service->get_search_row_by_post($base) === null, 'Non-public profiles save names without public search rows');
    }
    foreach (['unauthorized','missing_nonce','bad_nonce','unsupported','revision','autosave'] as $guard) {
        $seed(); $post = get_post($base); $baseline = $snapshot(); $search_states = [];
        if ($guard === 'unauthorized') { wp_set_current_user(0); }
        elseif ($guard === 'missing_nonce') { unset($_POST['iss_graph_profile_aliases_nonce']); }
        elseif ($guard === 'bad_nonce') { $_POST['iss_graph_profile_aliases_nonce'] = 'invalid'; }
        elseif ($guard === 'unsupported') { $post->post_type = 'page'; }
        else { $revision = clone $post; $revision->post_type = 'revision'; $revision->post_parent = $base + 1; $revision->post_name = ($base + 1) . ($guard === 'autosave' ? '-autosave-v1' : '-revision-v1'); wp_cache_set($base, $revision, 'posts'); }
        iss_graph_save_entity_profile_aliases($base, $post);
        $expect($snapshot() === $baseline && $search_states === [], "$guard prevents writes/search");
    }
    $seed(); $search_states = [];
    $reject_search = static function (array $map): array { throw new RuntimeException('Injected deferred search failure'); };
    add_filter('iss_graph_public_search_post_type_map', $reject_search, 20);
    try { $fail($save); } finally { remove_filter('iss_graph_public_search_post_type_map', $reject_search, 20); }
    $expect(count($names(10, $manual)) === 2 && in_array('MG', array_column($names(10, $generated), 'name'), true) && $transaction() === 0, 'Later search failure cannot undo committed consistent names');
    $expect(has_action('save_post', 'iss_graph_save_entity_profile_aliases') === 58, 'Save hook priority retained');
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('iss_graph_public_search_post_type_map', $search_hook);
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    wp_cache_flush(); $_POST = $original_post; wp_set_current_user($original_user); $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $original && $read($wpdb->options) === $original_options, 'Real graph, queue, profile bindings and options unchanged; no fixture source posts');
if ($error !== null) { WP_CLI::error("After {$checks} checks: " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Profile alias atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
