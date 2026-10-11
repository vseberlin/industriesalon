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
$base = 2147479900; $key = iss_graph_get_entity_profile_meta_key();
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
    foreach (['people' => 10, 'organizations' => 20] as $table => $id) {
        if ($wpdb->insert($tables[$table], $time + ['entity_id' => $id, 'summary' => 'Original summary', 'description' => 'Original description', 'source_summary' => 'Original source']) !== 1) { throw new RuntimeException('Cannot seed facts'); }
    }
    $_POST = ['iss_graph_profile_facts_nonce' => wp_create_nonce('iss_graph_profile_facts'), 'iss_graph_profile_facts' => wp_slash([
        'summary' => "<b>Facts O'Neil</b>\nSecond line", 'description' => '<p>New description</p>', 'source_summary' => '<b>New source</b>', 'website' => 'https://example.invalid/profile',
        'person_kind' => '<b>Artist</b>', 'birth_year' => '1900', 'death_year' => '',
        'organization_kind' => '<b>Company</b>', 'organization_status' => '<b>active</b>', 'founded_year' => '-20', 'dissolved_year' => '99999',
    ])];
};
$save = static fn(int $offset = 0) => iss_graph_save_entity_profile_facts($base + $offset, get_post($base + $offset));
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Failure reaches caller'); };
$search_states = [];
$search_hook = static function (array $map) use (&$search_states,$transaction): array { $search_states[] = $transaction(); return $map; };
$created = []; $error = null; $old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create temporary fixture'); } $created[] = $table; }
    foreach ($original_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Cannot shadow options'); } }
    add_filter('iss_graph_public_search_post_type_map', $search_hook);
    foreach (['person' => ['people',10,0], 'organization' => ['organizations',20,1]] as $kind => [$table_key,$id,$offset]) {
        $table = $tables[$table_key]; $call = static fn() => $save($offset);
        foreach ([false,true] as $insert) {
            $prepare = static function () use ($seed,$insert,$wpdb,$table,$id): void { $seed(); if ($insert) { $wpdb->delete($table, ['entity_id' => $id]); } };
            $prepare(); $baseline = $snapshot(); $search_states = []; $call(); $row = $service->get_entity_facts($kind, $id); $after = $snapshot();
            $expect($row['summary'] === "Facts O'Neil\nSecond line" && $row['description'] === 'New description' && $row['source_summary'] === 'New source' && $row['website'] === 'https://example.invalid/profile', "$kind shared facts sanitized and unslashed");
            $expect($kind === 'person' ? $row['person_kind'] === 'Artist' && $row['birth_year'] === 1900 && $row['death_year'] === null : $row['organization_kind'] === 'Company' && $row['organization_status'] === 'active' && $row['founded_year'] === -20 && $row['dissolved_year'] === 32767, "$kind type fields and year normalization retained");
            $expect($insert ? $row['created_at'] === $row['updated_at'] : $row['created_at'] === '2026-01-01 00:00:00', 'Creation timestamp created once and retained on update');
            $expect(array_diff_key($after, [$table_key => true,'search' => true]) === array_diff_key($baseline, [$table_key => true,'search' => true]), 'Other facts, identities, source posts, metadata, names and queue retained');
            $expect($search_states !== [] && array_unique($search_states) === [0] && str_contains($service->get_search_row_by_post($base + $offset)['search_text'], 'New description'), 'Search follows standalone commit and sees saved facts');

            foreach (['binding_read','linked_entity_read','facts_state','facts_engine','entity_lock','facts_read','write','false_success','readback','empty_readback','savepoint','release','commit','php'] as $stage) {
                $prepare(); $baseline = $snapshot(); $search_states = []; $hits = 0;
                $filter = static function (string $sql) use ($stage,$wpdb,$tables,$table,&$hits): string {
                    $write = str_starts_with($sql, "INSERT INTO `$table`") || str_starts_with($sql, "UPDATE `$table`");
                    $readback = str_contains($sql, "FROM {$table}") && str_starts_with(ltrim($sql), 'SELECT *') && !str_contains($sql, 'FOR UPDATE');
                    $match = match ($stage) {
                        'binding_read' => str_contains($sql, "SELECT meta_value FROM {$wpdb->postmeta}"),
                        'linked_entity_read' => str_contains($sql, "FROM {$tables['entities']}") && !str_contains($sql, 'FOR UPDATE'),
                        'facts_state','facts_engine' => str_contains($sql, 'ENGINE AS engine') && str_contains($sql, "'$table'"),
                        'entity_lock' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, 'FOR UPDATE'),
                        'facts_read' => str_contains($sql, "SELECT created_at FROM {$table}"),
                        'write','false_success','php' => $write,
                        'readback','empty_readback' => $readback,
                        'savepoint' => str_starts_with($sql, 'SAVEPOINT '),
                        'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '),
                        'commit' => $sql === 'COMMIT',
                    };
                    if (!$match || $hits > 0) { return $sql; } ++$hits;
                    if ($stage === 'php') { throw new RuntimeException('Injected facts PHP failure'); }
                    if ($stage === 'facts_engine') { return str_replace('ENGINE AS engine', "IF(TABLE_NAME = '$table', 'MyISAM', ENGINE) AS engine", $sql); }
                    if ($stage === 'false_success') { return 'SELECT 1'; }
                    if ($stage === 'empty_readback') { return "SELECT * FROM `$table` WHERE entity_id = 0"; }
                    return 'SELECT iss_graph_profile_facts_injected_failure';
                };
                $fail(static fn() => $with_filter($filter, $call));
                $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$kind $stage restores facts and source state");
                $expect($search_states === [], "$kind $stage skips search");
            }
        }
        // Force the DB API's legitimate zero-row UPDATE result without a wall-clock race.
        $seed(); $hits = 0; $zero_update = null;
        $zero_update = static function (string $sql) use ($wpdb,$table,$id,&$hits,&$zero_update): string {
            if (!str_starts_with($sql, "UPDATE `$table`") || $hits > 0) { return $sql; }
            ++$hits; remove_filter('query', $zero_update);
            try { if ($wpdb->query($sql) === false) { throw new RuntimeException('Cannot prime unchanged update'); } } finally { add_filter('query', $zero_update); }
            return "UPDATE `$table` SET summary = summary WHERE entity_id = $id";
        };
        $with_filter($zero_update, $call);
        $expect($hits === 1 && $service->get_entity_facts($kind, $id)['summary'] === "Facts O'Neil\nSecond line", "$kind verified zero-row update succeeds");

        $seed(); $baseline = $snapshot(); $search_states = [];
        $_POST['iss_graph_profile_facts'][$kind === 'person' ? 'person_kind' : 'organization_kind'] = str_repeat('X', 101);
        $fail($call); $expect($snapshot() === $baseline && $search_states === [], 'Rejected overlong value retains existing facts');
    }

    $late = static fn(string $sql): string => str_contains($sql, "FROM {$tables['people']}") && str_starts_with(ltrim($sql), 'SELECT *') ? 'SELECT iss_graph_profile_facts_readback_failure' : $sql;
    $seed(); $baseline = $snapshot();
    foreach ([false,true] as $reject) {
        $wpdb->query('START TRANSACTION'); $wpdb->update($tables['names'], ['name' => 'Caller pending'], ['entity_id' => 30]); $pending = $snapshot(); $search_states = [];
        if ($reject) { $fail(static fn() => $with_filter($late, $save)); $expect($snapshot() === $pending && $search_states === [], 'Nested failure retains previous caller writes and skips search'); }
        else { $save(); $expect($snapshot() !== $pending && array_unique($search_states) === [1], 'Nested success leaves search in caller transaction'); }
        $expect($transaction() === 1, 'Caller retains transaction ownership');
        $wpdb->query('ROLLBACK'); $expect($snapshot() === $baseline, 'Caller rollback restores facts and search');
    }
    $wpdb->query('SET autocommit = 0'); $save(); $expect($transaction() === 1, 'Disabled-autocommit caller retains ownership');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $expect($snapshot() === $baseline, 'Disabled-autocommit rollback restores rows');

    // Exercise the same service directly, as used by enrichment import, without a profile transaction.
    $seed(); $baseline = $snapshot();
    $direct = static fn() => $service->upsert_entity_facts('person', 10, ['summary' => 'Direct owner write']);
    $fail(static fn() => $with_filter(static fn(string $sql): string => $sql === 'COMMIT' ? 'SELECT iss_graph_facts_commit_failure' : $sql, $direct));
    $expect($snapshot() === $baseline && $transaction() === 0, 'Standalone facts commit failure rolls back');
    $result = $direct(); $expect($result['summary'] === 'Direct owner write' && $result['birth_year'] === null && $transaction() === 0, 'Standalone service returns verified facts with same replacement defaults');
    $baseline = $snapshot();
    foreach ([['person',0],['place',30],['organization',10],['person',999]] as [$kind,$id]) {
        $expect($service->upsert_entity_facts($kind, $id, ['summary' => 'Invalid']) === [] && $snapshot() === $baseline, 'Invalid IDs/kinds and genuinely absent entities remain service no-ops');
    }

    foreach ([null,[]] as $payload) {
        $seed();
        if ($payload === null) { unset($_POST['iss_graph_profile_facts']); } else { $_POST['iss_graph_profile_facts'] = $payload; }
        $save(); $row = $service->get_entity_facts('person', 10);
        $expect($row['summary'] === '' && $row['description'] === '' && $row['person_kind'] === '' && $row['birth_year'] === null, 'Missing and empty fact arrays retain clearing semantics');
    }
    foreach ([0,999,30] as $target) {
        $seed(); $wpdb->update($wpdb->postmeta, ['meta_value' => (string) $target], ['post_id' => $base, 'meta_key' => $key]); $flush_meta(); $baseline = $snapshot(); $search_states = [];
        $save(); $expect($snapshot() === $baseline && $search_states === [], 'Unbound, missing and unsupported profile entities are no-ops');
    }
    $seed(); $previous = $service->get_entity_facts('person', 10); get_post_meta($base, $key, true);
    $wpdb->update($wpdb->postmeta, ['meta_value' => '20'], ['post_id' => $base, 'meta_key' => $key]);
    $wpdb->update($tables['entities'], ['profile_post_id' => null], ['id' => 10]);
    $wpdb->update($tables['entities'], ['profile_post_id' => $base], ['id' => 20]); $save();
    $expect($service->get_entity_facts('person', 10) === $previous && $service->get_entity_facts('organization', 20)['organization_kind'] === 'Company', 'Stored binding wins over stale cache');
    $expect((int) $service->get_search_row_by_post($base)['entity_id'] === 20 && iss_graph_get_profile_linked_entity_id($base) === 20, 'Search sees saved facts and current binding');
    foreach (['draft','private'] as $status) {
        $seed(); $service->upsert_search_row(['target_post_id' => $base, 'title' => 'Old search', 'is_public' => true]);
        $wpdb->update($wpdb->posts, ['post_status' => $status], ['ID' => $base]); $flush_meta(); $save();
        $expect($service->get_entity_facts('person', 10)['person_kind'] === 'Artist' && $service->get_search_row_by_post($base) === null, 'Non-public profiles save facts without public search rows');
    }
    foreach (['unauthorized','missing_nonce','bad_nonce','unsupported','revision','autosave','malformed'] as $guard) {
        $seed(); $post = get_post($base); $baseline = $snapshot(); $search_states = [];
        if ($guard === 'unauthorized') { wp_set_current_user(0); }
        elseif ($guard === 'missing_nonce') { unset($_POST['iss_graph_profile_facts_nonce']); }
        elseif ($guard === 'bad_nonce') { $_POST['iss_graph_profile_facts_nonce'] = 'invalid'; }
        elseif ($guard === 'unsupported') { $post->post_type = 'page'; }
        elseif ($guard === 'malformed') { $_POST['iss_graph_profile_facts'] = 'invalid'; }
        else { $revision = clone $post; $revision->post_type = 'revision'; $revision->post_parent = $base + 1; $revision->post_name = ($base + 1) . ($guard === 'autosave' ? '-autosave-v1' : '-revision-v1'); wp_cache_set($base, $revision, 'posts'); }
        iss_graph_save_entity_profile_facts($base, $post); $expect($snapshot() === $baseline && $search_states === [], "$guard prevents writes/search");
    }
    $seed(); $reject_search = static function (array $map): array { throw new RuntimeException('Injected deferred search failure'); };
    add_filter('iss_graph_public_search_post_type_map', $reject_search, 20);
    try { $fail($save); } finally { remove_filter('iss_graph_public_search_post_type_map', $reject_search, 20); }
    $expect($service->get_entity_facts('person', 10)['person_kind'] === 'Artist' && $transaction() === 0, 'Later search failure cannot undo committed facts');
    $expect(has_action('save_post', 'iss_graph_save_entity_profile_facts') === 55, 'Save hook priority retained');
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('iss_graph_public_search_post_type_map', $search_hook);
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    wp_cache_flush(); $_POST = $original_post; wp_set_current_user($original_user); $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $original && $read($wpdb->options) === $original_options, 'Real graph, facts, queue, profile bindings and options unchanged; no fixture source posts');
if ($error !== null) { WP_CLI::error("After {$checks} checks: " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Profile facts atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
