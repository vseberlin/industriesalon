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
$base = 2147480500; $key = iss_graph_get_entity_profile_meta_key();
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
$seed = static function () use ($wpdb,$tables,$base,$key,$admins,$flush_meta,$service): void {
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
    foreach ([[10,'person',$base],[20,'organization',$base + 1],[30,'person',$base + 2],[40,'place',0]] as [$id,$kind,$profile]) {
        if ($wpdb->insert($tables['entities'], $time + ['id' => $id, 'entity_kind' => $kind, 'profile_post_id' => $profile ?: null, 'source_system' => 'fixture', 'source_id' => (string) $id, 'canonical_slug' => 'binding-' . $id, 'display_title' => 'Identity ' . $id, 'has_profile' => (int) ($profile > 0), 'is_public' => 1, 'status' => 'publish', 'search_visibility' => 'public']) !== 1) { throw new RuntimeException('Cannot seed entity'); }
        if ($wpdb->insert($tables['names'], $time + ['entity_id' => $id, 'name' => 'Identity ' . $id . ' (ALIAS' . $id . ')', 'normalized_name' => 'identity-' . $id, 'source_system' => 'entity_title']) !== 1) { throw new RuntimeException('Cannot seed name'); }
        if ($profile) { iss_graph_sync_wp_post_identifiers($id, get_post($profile), 'entity_profile'); }
    }
    if ($service->upsert_entity_identifier(20, ['namespace' => 'catalog', 'value' => 'Keep external identity', 'source_system' => 'other_source']) === null) { throw new RuntimeException('Cannot seed other-source identifier'); }
    wp_set_current_user((int) $admins[0]);
    $_POST = ['iss_graph_profile_link_nonce' => wp_create_nonce('iss_graph_profile_link'), 'iss_graph_profile_entity_id' => '20'];
};
$save = static fn() => iss_graph_save_entity_profile_link($base, get_post($base));
$sync = static fn() => iss_graph_sync_profile_binding_for_post($base);
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Failure reaches caller'); };
$cache_states = []; $search_states = []; $meta_states = []; $reject_meta_hook = false;
$cache_hook = static function ($value) use (&$cache_states,$transaction) { $cache_states[] = $transaction(); return $value; };
$search_hook = static function (array $map) use (&$search_states,$transaction): array { $search_states[] = $transaction(); return $map; };
$meta_hook = static function ($meta_id, int $post_id, string $meta_key) use ($key,$base,&$meta_states,&$reject_meta_hook,$transaction): void {
    if ($meta_key !== $key || $post_id < $base || $post_id > $base + 2) { return; }
    $meta_states[] = ['post' => $post_id, 'transaction' => $transaction(), 'value' => get_post_meta($post_id, $key, true)];
    if ($reject_meta_hook) { throw new RuntimeException('Injected metadata callback failure'); }
};
$created = []; $error = null; $old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create temporary fixture'); } $created[] = $table; }
    foreach ($original_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Cannot shadow options'); } }
    add_filter('pre_update_option_iss_register_atlas_cache_version', $cache_hook);
    add_filter('iss_graph_public_search_post_type_map', $search_hook);
    foreach (['added_post_meta','updated_post_meta','deleted_post_meta'] as $hook) { add_action($hook, $meta_hook, 90, 3); }
    $seed(); $baseline = $snapshot(); $save();
    $expect(iss_graph_get_profile_linked_entity_id($base) === 20 && iss_graph_get_profile_linked_entity_id($base + 1) === 0 && iss_graph_get_profile_linked_entity_id($base + 2) === 30, 'Profile move and displacement keep unrelated binding');
    $old = $service->get_entity_by_id(10); $new = $service->get_entity_by_id(20);
    $expect(!(int) $old['has_profile'] && !(int) $old['is_public'] && !(int) $old['profile_post_id'] && $old['search_visibility'] === 'hidden', 'Previous entity detached and hidden');
    $expect((int) $new['profile_post_id'] === $base && (int) $new['has_profile'] === 1 && (int) $new['is_public'] === 1, 'Selected entity bound to published profile');
    $expect($service->get_identifiers_for_entity(10, ['source_system' => 'entity_profile']) === [], 'Old profile identifiers removed before reassignment');
    $expect($service->get_identifier_by_namespace_value('wp_post', (string) $base)['entity_id'] === 20 && $service->get_identifier_by_namespace_value('wp_post', (string) ($base + 1)) === null, 'Identifiers move to selected entity and displaced profile identity is removed');
    $expect($service->get_identifier_by_namespace_value('catalog', 'Keep external identity')['entity_id'] === 20, 'Other-source identifiers retained');
    $expect(in_array('ALIAS20', array_column($service->get_names_for_entity(20, ['source_system' => iss_graph_get_alias_backfill_source_system()]), 'name'), true), 'Aliases synchronized');
    $expect($service->get_entity_by_id(30) === $baseline['entities'][2] && get_post_meta($base, 'unrelated_meta', true) === 'Keep 0', 'Unrelated entity and metadata retained');
    $expect($cache_states === [0] && $search_states !== [] && array_unique($search_states) === [0], 'Cache and search updates follow standalone commit');
    $expect(count($meta_states) === 2 && array_unique(array_column($meta_states, 'transaction')) === [1], 'Native metadata hooks retained within binding transaction');
    $expect((int) $service->get_search_row_by_post($base)['entity_id'] === 20 && $service->get_search_row_by_post($base + 1)['entity_id'] === null, 'Search refresh sees completed current and displaced bindings');
    $save(); $expect(iss_graph_get_profile_linked_entity_id($base) === 20, 'Repeat identical save accepts unchanged metadata');

    foreach (['state','engine','begin','source_lock','source_meta','identity_read','entity_reads','meta_update','meta_readback','previous_read','previous_update','previous_identifiers','displaced_meta','displaced_delete','new_update','identifiers_insert','aliases','commit','php'] as $stage) {
        $seed(); $baseline = $snapshot(); $cache_states = []; $search_states = []; $hits = 0; $metadata_written = false;
        $filter = static function (string $sql) use ($stage,$wpdb,$tables,$base,&$hits,&$metadata_written): string {
            $update_entity = str_starts_with($sql, "UPDATE `{$tables['entities']}`");
            if (str_starts_with($sql, "UPDATE `{$wpdb->postmeta}`")) { $metadata_written = true; }
            $match = match ($stage) {
                'state','engine' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'source_lock' => str_contains($sql, "FROM {$wpdb->posts}") && str_contains($sql, 'FOR UPDATE'),
                'source_meta' => str_contains($sql, "SELECT meta_value FROM {$wpdb->postmeta}") && str_contains($sql, 'post_id = ' . $base),
                'identity_read' => str_contains($sql, "FROM {$tables['identifiers']}") && str_contains($sql, "namespace = 'wp_post'"),
                'entity_reads' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'ORDER BY id FOR UPDATE'),
                'meta_update' => str_starts_with($sql, "UPDATE `{$wpdb->postmeta}`"),
                'meta_readback' => $metadata_written && str_contains($sql, "SELECT meta_value FROM {$wpdb->postmeta}") && str_contains($sql, 'post_id = ' . $base),
                'previous_read' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'WHERE id = 10'),
                'previous_update' => $update_entity && str_contains($sql, '`id` = 10'),
                'previous_identifiers' => str_starts_with($sql, "DELETE FROM `{$tables['identifiers']}`") && str_contains($sql, '`entity_id` = 10'),
                'displaced_meta' => str_contains($sql, "SELECT meta_value FROM {$wpdb->postmeta}") && str_contains($sql, 'post_id = ' . ($base + 1)),
                'displaced_delete' => str_starts_with($sql, "DELETE FROM {$wpdb->postmeta}"),
                'new_update' => $update_entity && str_contains($sql, '`id` = 20'),
                'identifiers_insert' => str_starts_with($sql, "INSERT INTO `{$tables['identifiers']}`"),
                'aliases','php' => str_starts_with($sql, "INSERT INTO `{$tables['names']}`"),
                'commit' => $sql === 'COMMIT',
            };
            if (!$match || $hits > 0) { return $sql; } ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected binding PHP failure'); }
            if ($stage === 'engine') { return str_replace('ENGINE AS engine', "IF(TABLE_NAME = '{$wpdb->postmeta}', 'MyISAM', ENGINE) AS engine", $sql); }
            return 'SELECT iss_graph_profile_binding_injected_failure';
        };
        $fail(static fn() => $with_filter($filter, $save));
        $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage rolls back graph and both profile metadata rows");
        $expect($cache_states === [] && $search_states === [], "$stage skips cache/search refresh on failure");
        $expect(iss_graph_get_profile_linked_entity_id($base) === 10 && iss_graph_get_profile_linked_entity_id($base + 1) === 20 && !wp_suspend_cache_addition(), "$stage metadata cache and cache policy restored");
        $expect(has_action('updated_post_meta', 'iss_graph_sync_public_search_on_meta_change') === 30, "$stage restores deferred metadata search hook");
    }
    $seed(); $baseline = $snapshot(); $reject_meta_hook = true; $fail($save); $reject_meta_hook = false;
    $expect($snapshot() === $baseline && iss_graph_get_profile_linked_entity_id($base) === 10, 'Throwing metadata callback restores graph, metadata and cache');
    foreach ([false,true] as $pretend) {
        $seed(); $baseline = $snapshot();
        $reject = static fn($value, $post_id, $meta_key) => $post_id === $base && $meta_key === $key ? $pretend : $value;
        add_filter('update_post_metadata', $reject, 10, 3);
        try { $fail($save); } finally { remove_filter('update_post_metadata', $reject, 10); }
        $expect($snapshot() === $baseline, 'Rejected or falsely successful metadata API result cannot partially bind');
    }
    $seed(); $wpdb->delete($wpdb->postmeta, ['post_id' => $base, 'meta_key' => $key]); $flush_meta(); $baseline = $snapshot();
    $fail(static fn() => $with_filter(static fn(string $sql): string => str_starts_with($sql, "INSERT INTO `{$wpdb->postmeta}`") ? 'SELECT iss_graph_profile_binding_insert_failure' : $sql, $save));
    $expect($snapshot() === $baseline, 'First metadata insertion failure retains old reverse binding');
    $save(); $expect(iss_graph_get_profile_linked_entity_id($base) === 20 && !(int) $service->get_entity_by_id(10)['profile_post_id'], 'Missing metadata insertion and reverse-link discovery succeed');

    $late = static fn(string $sql): string => str_starts_with($sql, "INSERT INTO `{$tables['names']}`") ? 'SELECT iss_graph_profile_binding_late_failure' : $sql;
    $seed(); $baseline = $snapshot();
    foreach ([false,true] as $reject) {
        $wpdb->query('START TRANSACTION'); $wpdb->update($tables['names'], ['name' => 'Caller pending'], ['entity_id' => 30]); $pending = $snapshot();
        if ($reject) { $fail(static fn() => $with_filter($late, $save)); $expect($snapshot() === $pending, 'Nested failure retains earlier caller writes'); }
        else { $save(); $expect($snapshot() !== $pending, 'Nested binding save applies'); }
        $expect($transaction() === 1, 'Caller retains transaction ownership');
        $wpdb->query('ROLLBACK'); $flush_meta(); wp_cache_delete('iss_register_atlas_cache_version', 'options');
        $expect($snapshot() === $baseline && iss_graph_get_profile_linked_entity_id($base) === 10, 'Caller rollback and metadata cache cleanup restore binding');
    }
    $wpdb->query('SET autocommit = 0'); $save(); $expect($transaction() === 1, 'Disabled-autocommit caller retains ownership');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $flush_meta(); wp_cache_delete('iss_register_atlas_cache_version', 'options');
    $expect($snapshot() === $baseline, 'Disabled-autocommit rollback restores rows');

    foreach ([0,999,40] as $target) {
        $seed(); $_POST['iss_graph_profile_entity_id'] = (string) $target; $save();
        $expect(iss_graph_get_profile_linked_entity_id($base) === 0 && !(int) $service->get_entity_by_id(10)['profile_post_id'], "$target unlinks without creating an invalid binding");
        $expect($service->get_identifiers_for_entity(10, ['source_system' => 'entity_profile']) === [], 'Unlink clears only profile identifiers');
    }
    foreach (['publish','draft','private','trash','auto-draft'] as $status) {
        $seed(); $wpdb->update($wpdb->posts, ['post_status' => $status], ['ID' => $base]); $flush_meta(); $sync(); $entity = $service->get_entity_by_id(10);
        $expect((bool) $entity['is_public'] === ($status === 'publish') && (bool) $entity['has_profile'] === !in_array($status, ['trash','auto-draft'], true) && (int) $entity['profile_post_id'] === $base, "$status status mapping retained");
    }
    // Metadata already saved by another entrypoint is the source; discover old reverse links without a supplied previous ID.
    $seed(); $wpdb->update($wpdb->postmeta, ['meta_value' => '20'], ['post_id' => $base, 'meta_key' => $key]); $flush_meta(); $baseline = $snapshot();
    $fail(static fn() => $with_filter($late, $sync));
    $expect($snapshot() === $baseline && iss_graph_get_profile_linked_entity_id($base) === 20, 'Failure preserves source metadata committed before the sync call');
    $sync();
    $expect(!(int) $service->get_entity_by_id(10)['profile_post_id'] && (int) $service->get_entity_by_id(20)['profile_post_id'] === $base, 'Automatic sync repairs a moved source without previous-ID hint');
    $seed(); $wpdb->update($wpdb->postmeta, ['meta_value' => '30'], ['post_id' => $base + 1, 'meta_key' => $key]); $flush_meta(); $save();
    $expect(iss_graph_get_profile_linked_entity_id($base + 1) === 30, 'Stale displaced reverse pointer preserves newer metadata choice');
    $seed(); $baseline = $snapshot(); iss_graph_clear_profile_binding_for_entity_id(20, $base);
    $expect($snapshot() === $baseline, 'Stale previous binding hint cannot clear another profile');
    iss_graph_clear_profile_binding_for_entity_id(10, $base);
    $expect(!(int) $service->get_entity_by_id(10)['profile_post_id'] && $service->get_identifiers_for_entity(10, ['source_system' => 'entity_profile']) === [], 'Standalone deletion helper clears entity and profile identifiers together');

    foreach (['unauthorized','missing_nonce','bad_nonce','unsupported','revision'] as $guard) {
        $seed(); $baseline = $snapshot(); $cache_states = []; $search_states = [];
        if ($guard === 'unauthorized') { wp_set_current_user(0); }
        elseif ($guard === 'missing_nonce') { unset($_POST['iss_graph_profile_link_nonce']); }
        elseif ($guard === 'bad_nonce') { $_POST['iss_graph_profile_link_nonce'] = 'invalid'; }
        elseif ($guard === 'unsupported') { $wpdb->update($wpdb->posts, ['post_type' => 'page'], ['ID' => $base]); $flush_meta(); $baseline = $snapshot(); }
        else { $revision = get_post($base); $revision->post_type = 'revision'; $revision->post_parent = $base + 1; wp_cache_set($base, $revision, 'posts'); }
        $save(); $expect($snapshot() === $baseline && $cache_states === [] && $search_states === [], "$guard prevents binding writes");
    }
    $seed(); $cache_states = []; $search_states = [];
    $reject_search = static function (array $map): array { throw new RuntimeException('Injected deferred search failure'); };
    add_filter('iss_graph_public_search_post_type_map', $reject_search, 20);
    try { $fail($save); } finally { remove_filter('iss_graph_public_search_post_type_map', $reject_search, 20); }
    $expect(iss_graph_get_profile_linked_entity_id($base) === 20 && !(int) $service->get_entity_by_id(10)['profile_post_id'] && $cache_states === [0], 'Deferred search failure leaves consistent committed binding and invalidated cache');
    $expect(has_action('save_post', 'iss_graph_save_entity_profile_link') === 40 && has_action('save_post', 'iss_graph_refresh_entity_profile_binding') === 65, 'Save hook priorities retained');
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('pre_update_option_iss_register_atlas_cache_version', $cache_hook);
    remove_filter('iss_graph_public_search_post_type_map', $search_hook);
    foreach (['added_post_meta','updated_post_meta','deleted_post_meta'] as $hook) { remove_action($hook, $meta_hook, 90); }
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    wp_cache_flush(); $_POST = $original_post; wp_set_current_user($original_user); $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $original && $read($wpdb->options) === $original_options, 'Real graph, queue, profile bindings and options unchanged; no fixture source posts');
if ($error !== null) { WP_CLI::error("After {$checks} checks: " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Profile binding atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
