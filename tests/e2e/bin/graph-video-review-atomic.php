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
$tables = ['entities' => $service->get_entity_table_name(), 'names' => $service->get_name_table_name(), 'identifiers' => $service->get_identifier_table_name(), 'relations' => $service->get_relation_table_name(), 'evidence' => $service->get_evidence_table_name(), 'search' => $service->get_search_table_name(), 'people' => $service->get_person_facts_table_name(), 'organizations' => $service->get_organization_facts_table_name(), 'queue' => $service->get_dirty_queue_table_name(), 'posts' => $wpdb->posts, 'postmeta' => $wpdb->postmeta, 'terms' => $wpdb->terms, 'taxonomy' => $wpdb->term_taxonomy, 'termmeta' => $wpdb->termmeta, 'termrels' => $wpdb->term_relationships];
$order = [$wpdb->posts => 'ID', $wpdb->postmeta => 'meta_id', $wpdb->options => 'option_id', $wpdb->terms => 'term_id', $wpdb->term_taxonomy => 'term_taxonomy_id', $wpdb->termmeta => 'meta_id', $wpdb->term_relationships => 'object_id, term_taxonomy_id'];
$read = static fn(string $table): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY " . ($order[$table] ?? 'id'), ARRAY_A);
$snapshot = static fn(): array => array_map($read, $tables);
$base = 2147479300; $key = 'iss_video_transcript_status';
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 4)) === 0, 'Fixture posts absent');
// Read only graph and transcript status source state from real tables, avoiding whole-DB equality claims during real editor activity.
$real_state = static fn(): array => [
    'graph' => array_map($read, array_diff_key($tables, ['posts' => true, 'postmeta' => true])),
    'relation_and_status_meta' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE meta_key IN (%s, %s) ORDER BY meta_id", $key, ISS_RELATIONS_META_KEY), ARRAY_A),
    'fixture_posts' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d ORDER BY ID", $base, $base + 4), ARRAY_A),
];
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$expect(count($admins) === 1, 'Administrator exists for authorization tests');
$original_user = get_current_user_id(); $original_post = $_POST; wp_set_current_user((int) $admins[0]);
$original = $real_state(); $original_options = $read($wpdb->options);
$schemas = [];
foreach (array_merge(array_values($tables), [$wpdb->options]) as $table) {
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    if (!is_array($schema) || !str_starts_with($schema[1], 'CREATE TABLE ')) { throw new RuntimeException('Cannot read fixture schema'); }
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$clear_source = static function () use ($base): void { for ($id = $base; $id <= $base + 4; ++$id) { wp_cache_delete($id, 'posts'); wp_cache_delete($id, 'post_meta'); } };
$source = iss_graph_video_transcript_source_system(); $review = iss_graph_video_transcript_review_source_system();
$seed = static function (bool $new = false) use ($wpdb,$tables,$base,$key,$clear_source,$source,$review,$service): void {
    foreach ($tables as $table) { if ($wpdb->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Cannot reset fixture'); } }
    $clear_source(); wp_cache_flush();
    $time = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
    $content = '<!-- wp:paragraph --><p>[01:02] Peter Müller at River Works with Example Trust and Hidden Profile.</p><!-- /wp:paragraph -->'
        . '<!-- wp:paragraph --><p>[01:03:04] Peter Müller meets Accepted Person, Rejected Person and Pending Person. RFT matters.</p><!-- /wp:paragraph -->'
        . '<!-- wp:paragraph --><p>Hidden Person, Hidden Place, Shared Firm, Generated Alias and XY are mentioned.</p><!-- /wp:paragraph -->';
    for ($n = 0; $n <= 4; ++$n) {
        $id = $base + $n;
        if ($wpdb->insert($wpdb->posts, ['ID' => $id, 'post_author' => 1, 'post_type' => $n >= 3 ? 'register_place' : ($n === 1 ? 'entity_profile' : iss_graph_get_video_post_type()), 'post_status' => 'publish', 'post_title' => 'Video fixture (VID)', 'post_name' => 'transcript-fixture-' . $n, 'post_content' => $content, 'post_excerpt' => 'Video excerpt', 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_modified' => '2026-01-01 00:00:00', 'post_modified_gmt' => '2026-01-01 00:00:00', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '']) !== 1) { throw new RuntimeException('Cannot seed post'); }
    }
    if ($wpdb->insert($wpdb->postmeta, ['post_id' => $base, 'meta_key' => $key, 'meta_value' => 'full']) !== 1) { throw new RuntimeException('Cannot seed transcript status'); }
    foreach ([[10,'video','Old Video',1,0],[20,'person','Peter Müller',1,0],[21,'person','Hidden Profile',0,$base + 1],[22,'person','Hidden Person',0,0],[30,'place','River Works',1,0],[31,'place','Hidden Place',0,0],[40,'organization','Example Trust',0,0],[41,'organization','Shared Firm',1,0],[42,'organization','Shared Firm',0,0],[43,'organization','Generated Alias',1,0],[44,'organization','XY',1,0],[45,'organization','RFT',1,0],[50,'person','Accepted Person',1,0],[51,'person','Rejected Person',1,0],[52,'person','Pending Person',1,0],[99,'video','Other Video',1,0]] as [$id,$kind,$title,$public,$profile]) {
        if ($wpdb->insert($tables['entities'], $time + ['id' => $id, 'entity_kind' => $kind, 'post_id' => $id === 10 ? $base : ($id === 99 ? $base + 2 : ($id === 30 ? $base + 3 : ($id === 31 ? $base + 4 : null))), 'profile_post_id' => $profile ?: null, 'has_profile' => (int) ($profile > 0), 'source_system' => 'fixture', 'source_id' => (string) $id, 'canonical_slug' => 'transcript-' . $id, 'display_title' => $title, 'status' => 'publish', 'is_public' => $public, 'search_visibility' => 'hidden']) !== 1) { throw new RuntimeException('Cannot seed entity'); }
        if ($wpdb->insert($tables['names'], $time + ['entity_id' => $id, 'name' => $title, 'normalized_name' => sanitize_title($title), 'name_type' => $id === 45 ? 'abbreviation' : 'primary', 'source_system' => $id === 43 ? 'entity_alias_backfill' : 'fixture']) !== 1) { throw new RuntimeException('Cannot seed name'); }
    }
    foreach ([[10,20,$source,'pending'],[10,21,$source,'pending'],[10,30,$source,'pending'],[10,40,$source,'pending'],[10,52,$source,'pending'],[10,45,$source,'pending'],[10,50,$review,'accepted'],[10,51,$review,'rejected'],[10,52,$review,'pending'],[10,40,'curator_note','accepted'],[99,20,$source,'pending']] as [$id,$target,$owner,$status]) {
        if ($wpdb->insert($tables['evidence'], $time + ['entity_id' => $id, 'target_kind' => 'entity', 'target_id' => $target, 'source_system' => $owner, 'source_ref' => 'old:' . $id, 'label' => 'Keep unless automatic target', 'note' => 'Original evidence', 'status' => $status]) !== 1) { throw new RuntimeException('Cannot seed evidence'); }
    }
    if ($wpdb->insert($tables['relations'], $time + ['from_entity_id' => 10, 'to_entity_id' => 20, 'relation_family' => 'person', 'source_system' => 'content_admin', 'is_public' => 0]) !== 1) { throw new RuntimeException('Cannot seed manual relation'); }
    $stored = iss_relations_normalize_relations([['target' => ['entity_id' => 31, 'entity_kind' => 'place', 'post_id' => $base + 4], 'role' => 'venue', 'weight' => 7, 'label' => 'Keep \\ old place']], $base);
    if ($wpdb->insert($wpdb->postmeta, ['post_id' => $base, 'meta_key' => ISS_RELATIONS_META_KEY, 'meta_value' => maybe_serialize($stored)]) !== 1) { throw new RuntimeException('Cannot seed existing place relation'); }
    if ($new) { $service->delete_entity(10); }
};

$id_for = static fn(int $target, int $entity = 10, string $owner = 'video_transcript'): int => (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$tables['evidence']} WHERE entity_id = %d AND target_id = %d AND source_system = %s", $entity, $target, $owner));
$prepare = static function (array $accept = [21,20,40,30], array $dismiss = [52,45]) use ($admins,$id_for): void {
    wp_set_current_user((int) $admins[0]);
    $_POST = ['iss_graph_video_transcript_review_nonce' => wp_create_nonce('iss_graph_save_video_transcript_review'), 'iss_graph_video_transcript_accept' => array_map($id_for, $accept), 'iss_graph_video_transcript_dismiss' => array_map($id_for, $dismiss)];
};
$save = static fn() => iss_graph_save_video_transcript_review($base, get_post($base));
$decisions = static fn(): array => $service->get_evidence_refs_for_entity(10, ['source_system' => $review]);
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Failure reaches caller'); };
$render_states = []; $search_states = []; $term_states = []; $meta_states = []; $render_failure = false; $meta_failure = false;
$render_hook = static function (string $html) use ($transaction,&$render_states,&$render_failure): string { $render_states[] = $transaction(); if ($render_failure) { throw new RuntimeException('Injected renderer failure'); } return $html; };
$search_hook = static function (array $map) use (&$search_states,$transaction): array { $search_states[] = $transaction(); return $map; };
$term_hook = static function () use (&$term_states,$transaction): void { $term_states[] = $transaction(); };
$meta_hook = static function ($id, int $post, string $key) use ($base,$transaction,&$meta_states,&$meta_failure): void {
    if ($post !== $base || $key !== ISS_RELATIONS_META_KEY) { return; }
    $meta_states[] = $transaction(); get_post_meta($base, ISS_RELATIONS_META_KEY, true);
    if ($meta_failure) { throw new RuntimeException('Injected native metadata callback failure'); }
};
$hooks = static function (): array {
    $result = [];
    foreach (['added_post_meta','updated_post_meta','deleted_post_meta'] as $hook) {
        foreach (['iss_relations_sync_post_terms_on_meta_change','iss_graph_sync_public_search_on_meta_change'] as $callback) { $result[] = has_action($hook, $callback); }
    }
    return $result;
};
$reset_states = static function () use (&$render_states,&$search_states,&$term_states,&$meta_states): void { $render_states = []; $search_states = []; $term_states = []; $meta_states = []; };
$initial_hooks = $hooks(); $created = []; $error = null; $old_suppression = $wpdb->suppress_errors(true); $initial_cache = wp_suspend_cache_addition();
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create temporary fixture'); } $created[] = $table; }
    foreach ($original_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Cannot shadow options'); } }
    add_filter('render_block', $render_hook);
    add_filter('iss_graph_public_search_post_type_map', $search_hook);
    add_action('set_object_terms', $term_hook);
    foreach (['added_post_meta','updated_post_meta'] as $hook) { add_action($hook, $meta_hook, 90, 3); }
    $seed(); $prepare(); $baseline = $snapshot(); $old_places = iss_relations_get_stored_post_relations($base); $reset_states(); $save();
    $rows = $decisions(); $by_target = array_column($rows, null, 'target_id');
    foreach ([21,20,40,30] as $target) { $expect($by_target[$target]['status'] === 'accepted', 'Promotion records accepted decision'); }
    foreach ([52,45] as $target) { $expect($by_target[$target]['status'] === 'rejected', 'Dismissal records rejected decision'); }
    $expect($by_target[50]['status'] === 'accepted' && $by_target[51]['status'] === 'rejected', 'Other existing decisions retained');
    $expect(count($rows) === 8 && (int) $by_target[52]['id'] === (int) array_values(array_filter($baseline['evidence'], static fn(array $r): bool => $r['source_system'] === 'video_transcript_review' && (int) $r['target_id'] === 52))[0]['id'], 'Existing decision updated in place');
    $person = $service->get_relations_for_entity(10, 'person', ['source_system' => 'content_admin']);
    $expect(count($person) === 2 && count(array_filter($person, static fn(array $r): bool => (int) $r['entity_id'] === 20 && !(int) $r['is_public'])) === 1, 'Existing person relation and privacy preserved without duplication');
    $expect(count($service->get_relations_for_entity(10, 'organization', ['source_system' => 'content_admin'])) === 1, 'Organization promoted through existing graph owner');
    $places = iss_relations_get_stored_post_relations($base);
    $expect(count($places) === 2 && $places[0] === $old_places[0] && $places[1]['role'] === 'subject' && $places[1]['label'] === 'Transkript', 'Place source preserves old relation, backslash, role and review label');
    $expect(count($service->get_relations_for_entity(10, 'place', ['source_system' => 'iss_relations_meta'])) === 2 && count(wp_get_object_terms($base, ISS_RELATIONS_TAXONOMY)) === 2, 'Existing owners refresh graph and taxonomy projections');
    $expect($service->get_evidence_refs_for_entity(10, ['source_system' => $source]) === [], 'Automatic refresh excludes all reviewed transcript targets');
    $note = json_decode($by_target[21]['note'], true); $expect($note['decision_type'] === 'video_transcript_review' && $note['target_entity_kind'] === 'person' && $by_target[21]['source_ref'] === 'entity:10', 'Decision provenance retained');
    $expect($render_states !== [] && array_unique($render_states) === [0] && $meta_states === [1], 'Rendering precedes write locks; native metadata callbacks run within transaction');
    $expect($search_states !== [] && array_unique($search_states) === [0] && $term_states === [0], 'Derived search and taxonomy run after standalone commit');
    $expect($hooks() === $initial_hooks && wp_suspend_cache_addition() === $initial_cache, 'Hook and cache state restored after success');
    $after = $snapshot(); $save(); $expect($snapshot() === $after, 'Stale submitted evidence IDs cannot repeat promotion');

    foreach (['state','engine','evidence_engine','begin','lock','lookup','pending','target','relations_read','relation_delete','relation_insert','review_read','decision_insert','decision_update','decision_readback','place_read','place_entity','source_meta','meta_read','meta_update','meta_readback','candidates','refresh_delete','refresh_insert','savepoint','release','commit','php'] as $stage) {
        $seed(); $prepare();
        // Keep an unreviewed mention for the late automatic insert failure.
        if ($stage === 'refresh_insert') { $_POST['iss_graph_video_transcript_dismiss'] = [$id_for(52)]; }
        $baseline = $snapshot(); $hits = 0; $wrote_meta = false; $wrote_decision = false; $reset_states();
        $filter = static function (string $sql) use ($stage,$tables,$wpdb,$base,$source,$review,&$hits,&$wrote_meta,&$wrote_decision): string {
            $original_sql = $sql; $sql = preg_replace('/\s+/', ' ', $sql);
            $meta_write = str_starts_with($sql, "UPDATE `{$wpdb->postmeta}`");
            $decision_write = (str_starts_with($sql, "INSERT INTO `{$tables['evidence']}`") || str_starts_with($sql, "UPDATE `{$tables['evidence']}`")) && str_contains($sql, "'$review'");
            $match = match ($stage) {
                'state','engine' => str_contains($sql, 'ENGINE AS engine'),
                'evidence_engine' => str_contains($sql, 'ENGINE AS engine') && str_contains($sql, "'{$tables['evidence']}'"),
                'begin' => $sql === 'START TRANSACTION',
                'lock' => str_contains($sql, "FROM {$wpdb->posts} WHERE ID = $base FOR UPDATE"),
                'lookup' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, "entity_kind = 'video'"),
                'pending' => str_contains($sql, "FROM {$tables['evidence']} WHERE id = "),
                'target' => str_contains($sql, "FROM {$tables['entities']} WHERE id = 21"),
                'relations_read' => str_contains($sql, "FROM {$tables['relations']} r"),
                'relation_delete' => str_starts_with($sql, "DELETE FROM `{$tables['relations']}`"),
                'relation_insert' => str_starts_with($sql, "INSERT INTO `{$tables['relations']}`"),
                'review_read' => str_contains($sql, "FROM {$tables['evidence']}") && str_contains($sql, "source_system = '$review'"),
                'decision_insert' => $decision_write && str_starts_with($sql, 'INSERT'),
                'decision_update' => $decision_write && str_starts_with($sql, 'UPDATE'),
                'decision_readback' => $wrote_decision && str_contains($sql, "FROM {$tables['evidence']} WHERE id = "),
                'place_read' => str_contains($sql, 'WHERE ID = ' . ($base + 3) . ' LIMIT 1'),
                'place_entity' => str_contains($sql, "FROM {$tables['entities']} WHERE id = 31"),
                'source_meta' => $wrote_decision && str_contains($sql, "post_id, meta_key, meta_value FROM {$wpdb->postmeta}"),
                'meta_read' => str_contains($sql, "SELECT meta_value FROM {$wpdb->postmeta}"),
                'meta_update' => $meta_write,
                'meta_readback' => $wrote_meta && str_contains($sql, "SELECT meta_value FROM {$wpdb->postmeta}"),
                'candidates' => str_contains($sql, "FROM {$tables['names']} n"),
                'refresh_delete' => str_starts_with($sql, "DELETE FROM `{$tables['evidence']}`") && str_contains($sql, "'$source'"),
                'refresh_insert' => str_starts_with($sql, "INSERT INTO `{$tables['evidence']}`") && str_contains($sql, "'$source'"),
                'savepoint' => str_starts_with($sql, 'SAVEPOINT '),
                'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '),
                'commit' => $sql === 'COMMIT',
                'php' => $decision_write && str_starts_with($sql, 'UPDATE'),
            };
            $wrote_meta = $wrote_meta || $meta_write; $wrote_decision = $wrote_decision || $decision_write;
            if (!$match || $hits > 0) { return $original_sql; } ++$hits;
            if ($stage === 'php') { throw new RuntimeException('Injected late PHP failure'); }
            if (in_array($stage, ['engine','evidence_engine'], true)) { return str_replace('ENGINE AS engine', "'MyISAM' AS engine", $sql); }
            return 'SELECT iss_graph_review_injected_failure';
        };
        $fail(static fn() => $with_filter($filter, $save));
        $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage restores whole review batch");
        $expect($search_states === [] && $term_states === [] && $hooks() === $initial_hooks && wp_suspend_cache_addition() === $initial_cache, "$stage skips derived work and restores hooks/cache state");
        $expect(get_post_meta($base, ISS_RELATIONS_META_KEY, true) === $old_places, "$stage leaves no rolled-back metadata in cache");
    }
    foreach ([false,true] as $pretend_success) {
        $seed(); $prepare(); $baseline = $snapshot(); $hits = 0;
        $filter = static function ($check, int $post, string $key) use ($base,$pretend_success,&$hits) { if ($post === $base && $key === ISS_RELATIONS_META_KEY) { ++$hits; return $pretend_success; } return $check; };
        add_filter('update_post_metadata', $filter, 10, 3);
        try { $fail($save); } finally { remove_filter('update_post_metadata', $filter, 10); }
        $expect($hits === 1 && $snapshot() === $baseline, 'Metadata API false or false success aborts whole batch');
    }
    $seed(); $prepare(); $baseline = $snapshot(); $meta_failure = true;
    try { $fail($save); } finally { $meta_failure = false; }
    $expect($snapshot() === $baseline && $hooks() === $initial_hooks && !wp_suspend_cache_addition(), 'Throwing metadata callback rolls back and restores state');
    $seed(); $prepare(); $baseline = $snapshot(); $render_failure = true;
    try { $fail($save); } finally { $render_failure = false; }
    $expect($snapshot() === $baseline && $hooks() === $initial_hooks, 'Renderer failure precedes all review writes');

    foreach (['unauthorized','missing_nonce','bad_nonce','no_selection','wrong_owner','nonpending','missing_id','revision','autosave_revision','unsupported'] as $guard) {
        $seed(); $prepare(); $baseline = $snapshot(); $post = get_post($base);
        if ($guard === 'unauthorized') { wp_set_current_user(0); }
        elseif ($guard === 'missing_nonce') { unset($_POST['iss_graph_video_transcript_review_nonce']); }
        elseif ($guard === 'bad_nonce') { $_POST['iss_graph_video_transcript_review_nonce'] = 'invalid'; }
        elseif (in_array($guard, ['no_selection','wrong_owner','nonpending','missing_id'], true)) {
            $_POST['iss_graph_video_transcript_accept'] = match ($guard) { 'no_selection' => [], 'wrong_owner' => [$id_for(20, 99)], 'nonpending' => [$id_for(50, 10, $review)], 'missing_id' => [2147479000] };
            $_POST['iss_graph_video_transcript_dismiss'] = [];
        } elseif ($guard === 'unsupported') { $post->post_type = 'post'; }
        else { $revision = clone $post; $revision->post_type = 'revision'; $revision->post_parent = $base + 2; $revision->post_name = $guard === 'autosave_revision' ? ($base + 2) . '-autosave-v1' : 'revision'; wp_cache_set($base, $revision, 'posts'); }
        iss_graph_save_video_transcript_review($base, $post); $expect($snapshot() === $baseline, "$guard is a harmless no-op");
    }
    $seed(); $prepare([21], [21]); $save(); $by_target = array_column($decisions(), null, 'target_id'); $expect($by_target[21]['status'] === 'accepted', 'Accept wins when evidence appears in both fields');
    $seed(); $prepare([30], []); $wpdb->delete($wpdb->postmeta, ['post_id' => $base, 'meta_key' => ISS_RELATIONS_META_KEY]); $clear_source(); $save();
    $expect(count(iss_relations_get_stored_post_relations($base)) === 1, 'First place metadata is inserted through Metadata API');
    $seed(); $prepare([30], []); $wpdb->update($wpdb->posts, ['post_status' => 'trash'], ['ID' => $base + 3]); $clear_source(); $baseline = $snapshot(); $save();
    $expect($snapshot() === $baseline, 'Unusable place cannot receive a successful accepted decision');

    $seed(); $prepare([21,40,30], [52]); $baseline = $snapshot();
    $late = static fn(string $sql): string => str_starts_with($sql, "UPDATE `{$tables['evidence']}`") ? 'SELECT iss_graph_review_late_failure' : $sql;
    foreach ([false,true] as $reject) {
        $wpdb->query('START TRANSACTION'); $wpdb->update($tables['names'], ['name' => 'Caller pending'], ['entity_id' => 99]); $pending = $snapshot();
        if ($reject) { $fail(static fn() => $with_filter($late, $save)); $expect($snapshot() === $pending, 'Nested failure retains caller writes'); }
        else { $save(); $expect($snapshot() !== $pending, 'Nested review succeeds'); }
        $expect($transaction() === 1, 'Caller retains final transaction ownership');
        $wpdb->query('ROLLBACK'); $clear_source(); $expect($snapshot() === $baseline, 'Caller rollback restores review');
    }
    $wpdb->query('SET autocommit = 0'); $save(); $expect($transaction() === 1, 'Disabled-autocommit caller retains ownership');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $clear_source(); $expect($snapshot() === $baseline, 'Disabled-autocommit rollback restores review');

    $seed(); $prepare([21], []); wp_suspend_cache_addition(true); $save(); $expect(wp_suspend_cache_addition(), 'Existing cache suspension retained'); wp_suspend_cache_addition(false);
    $seed(); $prepare([], [52]); $save(); $before = $decisions();
    $pending = $service->get_evidence_ref_by_id($id_for(20)); iss_graph_video_transcript_upsert_review_decision(10, 52, 'rejected', $pending); $after = $decisions();
    $expect(count($after) === count($before), 'Direct repeated decision update does not duplicate rows');

    $seed(); $prepare([], [52]); $baseline = $snapshot(); $hits = 0;
    $ignore_update = static function (string $sql) use ($tables,&$hits): string { if (str_starts_with($sql, "UPDATE `{$tables['evidence']}`")) { ++$hits; return "UPDATE `{$tables['evidence']}` SET status = status WHERE id = 0"; } return $sql; };
    $fail(static fn() => $with_filter($ignore_update, $save)); $expect($hits === 1 && $snapshot() === $baseline, 'Wrong stored decision cannot masquerade as a valid zero-row update');
    $seed(); $prepare([30], []); $wpdb->delete($wpdb->postmeta, ['post_id' => $base, 'meta_key' => ISS_RELATIONS_META_KEY]); $clear_source(); $baseline = $snapshot(); $hits = 0;
    $bad_insert = static function (string $sql) use ($wpdb,&$hits): string { if (str_starts_with($sql, "INSERT INTO `{$wpdb->postmeta}`")) { ++$hits; return 'SELECT iss_graph_review_insert_failure'; } return $sql; };
    $fail(static fn() => $with_filter($bad_insert, $save)); $expect($hits === 1 && $snapshot() === $baseline, 'Failed first place metadata insert cannot accept the evidence');
    $seed(); $prepare([30], []); $save(); $after = $snapshot();
    $same = iss_relations_get_stored_post_relations($base); iss_relations_update_post_relations($base, $same);
    $expect($snapshot() === $after, 'Unchanged normalized metadata is a successful no-op');
    iss_relations_update_post_relations($base, []); $expect(!metadata_exists('post', $base, ISS_RELATIONS_META_KEY) && iss_relations_get_stored_post_relations($base) === [], 'Shared owner still supports explicit clearing');
    $after = $snapshot(); iss_relations_update_post_relations($base, []); $expect($snapshot() === $after, 'Repeated clearing is a successful no-op');
    $seed(); $prepare(); $before_meta = get_post_meta($base, ISS_RELATIONS_META_KEY, true);
    $wpdb->insert($wpdb->postmeta, ['post_id' => $base, 'meta_key' => ISS_RELATIONS_META_KEY, 'meta_value' => maybe_serialize($before_meta)]); $clear_source(); $save();
    $all_meta = get_post_meta($base, ISS_RELATIONS_META_KEY, false);
    $expect(count($all_meta) === 2 && $all_meta[0] === $all_meta[1] && count($all_meta[0]) === 2, 'Duplicate canonical metadata rows are consistently updated');
    $seed(); $prepare([], [52]); $post = get_post($base); get_post_meta($base, 'iss_video_transcript_status', true); $wpdb->get_var('SELECT iss_graph_review_previous_unrelated_error');
    iss_graph_save_video_transcript_review($base, $post); $expect(array_column($decisions(), 'status', 'target_id')[52] === 'rejected', 'Cached reads ignore an earlier unrelated SQL error');
    $seed(); $prepare([21], []); remove_action('updated_post_meta', 'iss_relations_sync_post_terms_on_meta_change', 10); $save();
    $expect(has_action('updated_post_meta', 'iss_relations_sync_post_terms_on_meta_change') === false, 'Previously absent metadata callback stays absent'); add_action('updated_post_meta', 'iss_relations_sync_post_terms_on_meta_change', 10, 3);
    $seed(); $prepare([21], []); $reject_search = static function (array $map): array { throw new RuntimeException('Injected derived search failure'); };
    add_filter('iss_graph_public_search_post_type_map', $reject_search, 100);
    try { $fail($save); } finally { remove_filter('iss_graph_public_search_post_type_map', $reject_search, 100); }
    $expect(array_column($decisions(), 'status', 'target_id')[21] === 'accepted' && $transaction() === 0, 'Later search failure cannot undo standalone review commit');

    $expect(has_action('save_post', 'iss_graph_save_video_transcript_review') === 58, 'Save hook priority retained');
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('render_block', $render_hook); remove_filter('iss_graph_public_search_post_type_map', $search_hook); remove_action('set_object_terms', $term_hook);
    foreach (['added_post_meta','updated_post_meta'] as $hook) { remove_action($hook, $meta_hook, 90); }
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); wp_suspend_cache_addition($initial_cache);
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    $_POST = $original_post; wp_set_current_user($original_user); wp_cache_flush(); $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $original && $read($wpdb->options) === $original_options, 'Real graph, taxonomy, relation/transcript metadata, queue and options unchanged; fixture posts absent');
if ($error !== null) { WP_CLI::error("After {$checks} checks (stage " . ($stage ?? "success") . "): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Video transcript review atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
