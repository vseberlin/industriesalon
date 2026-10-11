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
$base = 2147479600; $key = 'iss_video_transcript_status';
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 2)) === 0, 'Fixture posts absent');
// Read only graph and transcript status source state from real tables, avoiding whole-DB equality claims during real editor activity.
$real_state = static fn(): array => [
    'graph' => array_map($read, array_diff_key($tables, ['posts' => true, 'postmeta' => true])),
    'transcript_status' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE meta_key = %s ORDER BY meta_id", $key), ARRAY_A),
    'fixture_posts' => $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d ORDER BY ID", $base, $base + 2), ARRAY_A),
];
$original = $real_state(); $original_options = $read($wpdb->options);
$schemas = [];
foreach (array_merge(array_values($tables), [$wpdb->options]) as $table) {
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    if (!is_array($schema) || !str_starts_with($schema[1], 'CREATE TABLE ')) { throw new RuntimeException('Cannot read fixture schema'); }
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$clear_source = static function () use ($base): void { for ($id = $base; $id <= $base + 2; ++$id) { wp_cache_delete($id, 'posts'); wp_cache_delete($id, 'post_meta'); } };
$source = iss_graph_video_transcript_source_system(); $review = iss_graph_video_transcript_review_source_system();
$seed = static function (bool $new = false) use ($wpdb,$tables,$base,$key,$clear_source,$source,$review,$service): void {
    foreach ($tables as $table) { if ($wpdb->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Cannot reset fixture'); } }
    $clear_source();
    $time = ['created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'];
    $content = '<!-- wp:paragraph --><p>[01:02] Peter Müller at River Works with Example Trust and Hidden Profile.</p><!-- /wp:paragraph -->'
        . '<!-- wp:paragraph --><p>[01:03:04] Peter Müller meets Accepted Person, Rejected Person and Pending Person. RFT matters.</p><!-- /wp:paragraph -->'
        . '<!-- wp:paragraph --><p>Hidden Person, Hidden Place, Shared Firm, Generated Alias and XY are mentioned.</p><!-- /wp:paragraph -->';
    for ($n = 0; $n <= 2; ++$n) {
        $id = $base + $n;
        if ($wpdb->insert($wpdb->posts, ['ID' => $id, 'post_author' => 1, 'post_type' => $n === 1 ? 'entity_profile' : iss_graph_get_video_post_type(), 'post_status' => 'publish', 'post_title' => 'Video fixture (VID)', 'post_name' => 'transcript-fixture-' . $n, 'post_content' => $content, 'post_excerpt' => 'Video excerpt', 'post_date' => '2026-01-01 00:00:00', 'post_date_gmt' => '2026-01-01 00:00:00', 'post_modified' => '2026-01-01 00:00:00', 'post_modified_gmt' => '2026-01-01 00:00:00', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '']) !== 1) { throw new RuntimeException('Cannot seed post'); }
    }
    if ($wpdb->insert($wpdb->postmeta, ['post_id' => $base, 'meta_key' => $key, 'meta_value' => 'full']) !== 1) { throw new RuntimeException('Cannot seed transcript status'); }
    foreach ([[10,'video','Old Video',1,0],[20,'person','Peter Müller',1,0],[21,'person','Hidden Profile',0,$base + 1],[22,'person','Hidden Person',0,0],[30,'place','River Works',1,0],[31,'place','Hidden Place',0,0],[40,'organization','Example Trust',0,0],[41,'organization','Shared Firm',1,0],[42,'organization','Shared Firm',0,0],[43,'organization','Generated Alias',1,0],[44,'organization','XY',1,0],[45,'organization','RFT',1,0],[50,'person','Accepted Person',1,0],[51,'person','Rejected Person',1,0],[52,'person','Pending Person',1,0],[99,'video','Other Video',1,0]] as [$id,$kind,$title,$public,$profile]) {
        if ($wpdb->insert($tables['entities'], $time + ['id' => $id, 'entity_kind' => $kind, 'post_id' => $id === 10 ? $base : ($id === 99 ? $base + 2 : null), 'profile_post_id' => $profile ?: null, 'has_profile' => (int) ($profile > 0), 'source_system' => 'fixture', 'source_id' => (string) $id, 'canonical_slug' => 'transcript-' . $id, 'display_title' => $title, 'status' => 'publish', 'is_public' => $public, 'search_visibility' => 'hidden']) !== 1) { throw new RuntimeException('Cannot seed entity'); }
        if ($wpdb->insert($tables['names'], $time + ['entity_id' => $id, 'name' => $title, 'normalized_name' => sanitize_title($title), 'name_type' => $id === 45 ? 'abbreviation' : 'primary', 'source_system' => $id === 43 ? 'entity_alias_backfill' : 'fixture']) !== 1) { throw new RuntimeException('Cannot seed name'); }
    }
    foreach ([[10,20,$source,'pending'],[10,30,$source,'pending'],[10,50,$review,'accepted'],[10,51,$review,'rejected'],[10,52,$review,'pending'],[10,40,'curator_note','accepted'],[99,20,$source,'pending']] as [$id,$target,$owner,$status]) {
        if ($wpdb->insert($tables['evidence'], $time + ['entity_id' => $id, 'target_kind' => 'entity', 'target_id' => $target, 'source_system' => $owner, 'source_ref' => 'old:' . $id, 'label' => 'Keep unless automatic target', 'note' => 'Original evidence', 'status' => $status]) !== 1) { throw new RuntimeException('Cannot seed evidence'); }
    }
    if ($wpdb->insert($tables['relations'], $time + ['from_entity_id' => 10, 'to_entity_id' => 20, 'relation_family' => 'person', 'source_system' => 'content_admin', 'is_public' => 0]) !== 1) { throw new RuntimeException('Cannot seed manual relation'); }
    if ($new) { $service->delete_entity(10); }
};
$sync = static fn(): array => iss_graph_sync_video_transcript_mentions($base);
$evidence = static fn(int $id = 10): array => $service->get_evidence_refs_for_entity($id, ['source_system' => $source]);
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$fail = static function (callable $call) use ($expect): void { $error = null; try { $call(); } catch (RuntimeException $caught) { $error = $caught; } $expect($error !== null, 'Failure reaches caller'); };
$render_states = []; $render_failure = '';
$render_hook = static function (string $html) use ($wpdb,$transaction,&$render_states,&$render_failure): string {
    $render_states[] = $transaction();
    if ($render_failure === 'php') { throw new RuntimeException('Injected rendering failure'); }
    if ($render_failure === 'sql') { $wpdb->get_var('SELECT iss_graph_transcript_render_failure'); }
    return $html;
};
$native = static function (array $rows, WP_Post $post) use ($base): array {
    if ((int) $post->ID === $base) { $rows['person'] = [['to_entity_id' => 20, 'relation_type' => 'subject', 'is_public' => false]]; }
    return $rows;
};
$created = []; $error = null; $old_suppression = $wpdb->suppress_errors(true);
try {
    foreach ($schemas as $table => $schema) { if ($wpdb->query($schema) === false) { throw new RuntimeException('Cannot create temporary fixture'); } $created[] = $table; }
    foreach ($original_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Cannot shadow options'); } }
    add_filter('render_block', $render_hook);
    add_filter('iss_graph_public_content_native_relation_rows', $native, 10, 2);
    $seed(); $baseline = $snapshot(); $result = $sync(); $rows = $evidence(); $after = $snapshot();
    $expect($result === ['synced' => true, 'entity_id' => 10, 'mentions' => 6, 'segments' => 3, 'status' => 'full'], 'Result shape and transcript counts retained');
    $expect(array_column($rows, 'target_id') === [21,20,40,30,52,45], 'Candidate ordering, privacy, ambiguity, source and short-name exclusions retained');
    $expect(array_unique(array_column($rows, 'status')) === ['pending'] && array_unique(array_column($rows, 'source_ref')) === ['post:' . $base], 'Automatic evidence remains pending with source provenance');
    $peter = json_decode($rows[1]['note'], true);
    $expect($peter['matched_names'] === ['Peter Müller'] && array_column($peter['segments'], 'seconds') === [62,3784] && array_column($peter['segments'], 'anchor') === ['t-01-02','t-01-03-04'], 'Matched names, duplicate aggregation and timecodes retained');
    $expect(str_ends_with($rows[0]['source_url'], '#t-01-02') && $rows[0]['confidence'] === 85 && $rows[5]['confidence'] === 70, 'Source anchors and full-transcript confidence retained');
    $unowned = static fn(array $rows): array => array_values(array_filter($rows, static fn(array $row): bool => (int) $row['entity_id'] !== 10 || $row['source_system'] !== $source));
    $expect($unowned($after['evidence']) === $unowned($baseline['evidence']), 'Accepted/rejected/pending reviews, curator evidence and other videos retained');
    $expect(array_diff_key($after, ['evidence' => true]) === array_diff_key($baseline, ['evidence' => true]), 'Existing source, identities, facts, relations, search and queue remain unchanged');
    $expect($render_states !== [] && array_unique($render_states) === [0] && $transaction() === 0, 'Standalone rendering precedes graph transaction');
    $sync(); $expect(array_column($evidence(), 'target_id') === array_column($rows, 'target_id'), 'Repeated sync does not accumulate evidence');

    foreach ([false,true] as $new) {
        foreach (['state','engine','begin','lookup','candidate_read','review_read','evidence_engine','delete','insert','second_insert','commit','php'] as $stage) {
            $seed($new); $baseline = $snapshot(); $hits = 0; $seen = 0;
            $filter = static function (string $sql) use ($stage,$tables,$source,$review,&$hits,&$seen): string {
                $insert = str_starts_with($sql, "INSERT INTO `{$tables['evidence']}`") && str_contains($sql, "'$source'");
                $match = match ($stage) {
                    'state','engine' => str_contains($sql, 'ENGINE AS engine'),
                    'begin' => $sql === 'START TRANSACTION',
                    'lookup' => str_contains($sql, "FROM {$tables['entities']}") && str_contains($sql, "entity_kind = 'video'"),
                    'candidate_read' => str_contains($sql, "FROM {$tables['names']} n"),
                    'review_read' => str_contains($sql, "FROM {$tables['evidence']}") && str_contains($sql, "source_system = '$review'"),
                    'evidence_engine' => str_contains($sql, 'ENGINE AS engine') && str_contains($sql, "'{$tables['evidence']}'"),
                    'delete' => str_starts_with($sql, "DELETE FROM `{$tables['evidence']}`") && str_contains($sql, "'$source'"),
                    'insert','second_insert','php' => $insert,
                    'commit' => $sql === 'COMMIT',
                };
                if (!$match || $hits > 0) { return $sql; } ++$seen;
                if ($stage === 'second_insert' && $seen < 2) { return $sql; } ++$hits;
                if ($stage === 'php') { throw new RuntimeException('Injected transcript PHP failure'); }
                if ($stage === 'engine' || $stage === 'evidence_engine') { return str_replace('ENGINE AS engine', "'MyISAM' AS engine", $sql); }
                return 'SELECT iss_graph_video_transcript_injected_failure';
            };
            $fail(static fn() => $with_filter($filter, $sync));
            $expect($hits === 1 && $snapshot() === $baseline && $transaction() === 0, "$stage restores evidence and required new video graph");
        }
    }
    foreach (['entity','title','identifiers','aliases','relations','savepoint','release'] as $stage) {
        $seed(true); $baseline = $snapshot(); $hits = 0;
        $filter = static function (string $sql) use ($stage,$tables,&$hits): string {
            $match = match ($stage) {
                'entity' => str_starts_with($sql, "INSERT INTO `{$tables['entities']}`"),
                'title' => str_starts_with($sql, "INSERT INTO `{$tables['names']}`") && str_contains($sql, "'iss_graph_content_post_title'"),
                'identifiers' => str_starts_with($sql, "INSERT INTO `{$tables['identifiers']}`"),
                'aliases' => str_starts_with($sql, "INSERT INTO `{$tables['names']}`") && str_contains($sql, "'entity_alias_backfill'"),
                'relations' => str_starts_with($sql, "INSERT INTO `{$tables['relations']}`"),
                'savepoint' => str_starts_with($sql, 'SAVEPOINT '),
                'release' => str_starts_with($sql, 'RELEASE SAVEPOINT '),
            };
            if (!$match || $hits > 0) { return $sql; } ++$hits; return 'SELECT iss_graph_video_creation_failure';
        };
        $fail(static fn() => $with_filter($filter, $sync));
        $expect($hits === 1 && $snapshot() === $baseline, "$stage creation failure preserves complete baseline");
    }
    $seed(true); $baseline = $snapshot(); $commands = [];
    $result = $with_filter(static function (string $sql) use (&$commands): string { $commands[] = $sql; return $sql; }, $sync);
    $id = $result['entity_id']; $entity = $service->get_entity_by_id($id);
    $expect($id > 0 && $entity['display_title'] === 'Video fixture (VID)' && (int) $entity['post_id'] === $base, 'Missing video created through existing content owner');
    $expect((int) $service->get_identifier_by_namespace_value('wp_post', (string) $base)['entity_id'] === $id && in_array('VID', array_column($service->get_names_for_entity($id), 'name'), true), 'New video identifiers and title aliases included');
    $expect(count($service->get_relations_for_entity($id, 'person', ['source_system' => 'content_native'])) === 1 && count($evidence($id)) === 8, 'Native relations and transcript evidence finish together');
    $expect(count(array_filter($commands, static fn(string $sql): bool => $sql === 'COMMIT')) === 1, 'Graph creation and evidence share one standalone commit');
    $expect($read($tables['posts']) === $baseline['posts'] && $read($tables['postmeta']) === $baseline['postmeta'] && $read($tables['search']) === $baseline['search'], 'Source posts/meta and search retain separate ownership');

    $late = static fn(string $sql): string => str_starts_with($sql, "INSERT INTO `{$tables['evidence']}`") ? 'SELECT iss_graph_video_late_failure' : $sql;
    $seed(true); $baseline = $snapshot();
    foreach ([false,true] as $reject) {
        $wpdb->query('START TRANSACTION'); $wpdb->update($tables['names'], ['name' => 'Caller pending'], ['entity_id' => 99]); $pending = $snapshot();
        if ($reject) { $fail(static fn() => $with_filter($late, $sync)); $expect($snapshot() === $pending, 'Nested failure retains earlier caller writes'); }
        else { $result = $sync(); $expect($result['synced'] && $snapshot() !== $pending, 'Nested synchronization succeeds'); }
        $expect($transaction() === 1, 'Caller retains transaction ownership');
        $wpdb->query('ROLLBACK'); $expect($snapshot() === $baseline, 'Caller rollback restores graph and evidence');
    }
    $wpdb->query('SET autocommit = 0'); $sync(); $expect($transaction() === 1, 'Disabled-autocommit caller retains ownership');
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1'); $expect($snapshot() === $baseline, 'Disabled-autocommit rollback restores rows');

    foreach (['php','sql'] as $failure) {
        $seed(); $baseline = $snapshot(); $render_failure = $failure; $fail($sync); $render_failure = '';
        $expect($snapshot() === $baseline && $transaction() === 0, 'Rendering failure preserves evidence before graph writes');
    }
    $seed(); $baseline = $snapshot();
    $source_error = static fn(string $sql): string => str_contains($sql, 'WHERE ID = ' . $base . ' LIMIT 1') ? 'SELECT iss_graph_transcript_source_failure' : $sql;
    $fail(static fn() => $with_filter($source_error, $sync));
    $expect($snapshot() === $baseline, 'Failed source post read cannot report successful no-op');
    $clear_source(); $fail(static fn() => $with_filter($source_error, static fn() => iss_graph_video_transcript_extract_segments($base)));
    $expect($snapshot() === $baseline, 'Direct content extraction checks source read failure');
    $clear_source(); $fail(static fn() => $with_filter(static fn(string $sql): string => str_contains($sql, "meta_value FROM {$wpdb->postmeta}") ? 'SELECT iss_graph_transcript_meta_failure' : $sql, $sync));
    $expect($snapshot() === $baseline, 'Failed status read cannot clear automatic evidence');
    $clear_source(); $fail(static fn() => $with_filter($source_error, static fn() => iss_graph_video_transcript_build_evidence_rows($base, [], 'full')));
    $expect($snapshot() === $baseline, 'Evidence source URL checks post read failure');

    foreach (['planned','none','invalid','', 'excerpt'] as $status) {
        $seed(); $wpdb->update($wpdb->postmeta, ['meta_value' => $status], ['post_id' => $base, 'meta_key' => $key]); $clear_source(); $baseline = $snapshot(); $result = $sync();
        $expected_status = in_array($status, ['none','invalid',''], true) ? 'excerpt' : $status;
        $expect($result['status'] === $expected_status && $result['mentions'] === ($expected_status === 'excerpt' ? 6 : 0), "$status transcript status and intentional clearing retained");
        $expect($unowned($read($tables['evidence'])) === $unowned($baseline['evidence']), 'Status changes preserve review decisions and other evidence');
        if ($expected_status === 'excerpt') { $expect($evidence()[0]['confidence'] === 80, 'Excerpt confidence retained'); }
    }
    $seed(); $wpdb->update($wpdb->posts, ['post_content' => ''], ['ID' => $base]); $clear_source(); $baseline = $snapshot();
    $expect($sync()['mentions'] === 0 && $unowned($read($tables['evidence'])) === $unowned($baseline['evidence']), 'Empty transcript clears only its automatic evidence');
    $seed(); $wpdb->update($wpdb->posts, ['post_content' => "[00:07] Peter Müller\n\nRiver Works"], ['ID' => $base]); $clear_source();
    $expect($sync()['segments'] === 2 && count($evidence()) === 2, 'Plain-text paragraph fallback retained');
    $seed(); $empty_candidates = static fn(): array => [];
    add_filter('iss_graph_video_transcript_name_types', $empty_candidates);
    try { $expect($sync()['mentions'] === 0, 'Intentional empty candidate filter clears automatic evidence'); } finally { remove_filter('iss_graph_video_transcript_name_types', $empty_candidates); }
    $seed(); $limit = static fn(): int => 1;
    add_filter('iss_graph_video_transcript_max_mentions_per_entity', $limit);
    try { $sync(); $peter = json_decode($evidence()[1]['note'], true); $expect(count($peter['segments']) === 1, 'Mention-per-entity cap retained'); } finally { remove_filter('iss_graph_video_transcript_max_mentions_per_entity', $limit); }
    add_filter('iss_graph_video_transcript_max_entities_per_video', $limit);
    try { $expect($sync()['mentions'] === 1, 'Entity-per-video cap retained'); } finally { remove_filter('iss_graph_video_transcript_max_entities_per_video', $limit); }

    foreach (['draft','private'] as $status) {
        $seed(true); $wpdb->update($wpdb->posts, ['post_status' => $status], ['ID' => $base]); $clear_source(); $result = $sync(); $entity = $service->get_entity_by_id($result['entity_id']);
        $expect($result['synced'] && !(int) $entity['is_public'] && $entity['search_visibility'] === 'hidden' && $read($tables['search']) === [], 'Private/draft evidence remains internal without public search writes');
    }
    foreach (['trash','auto-draft'] as $status) {
        $seed(true); $wpdb->update($wpdb->posts, ['post_status' => $status], ['ID' => $base]); $clear_source(); $baseline = $snapshot();
        $expect($sync() === ['synced' => false,'entity_id' => 0,'mentions' => 0] && $snapshot() === $baseline, "$status missing video stays a no-op");
    }
    $seed(); $baseline = $snapshot();
    $expect(!iss_graph_sync_video_transcript_mentions($base + 1)['synced'] && !iss_graph_sync_video_transcript_mentions($base + 3)['synced'] && $snapshot() === $baseline, 'Unsupported and absent posts are harmless no-ops');
    $post = get_post($base); $revision = clone $post; $revision->post_type = 'revision'; $revision->post_parent = $base + 2; wp_cache_set($base, $revision, 'posts');
    iss_graph_sync_video_transcript_mentions_on_save($base, $post); $expect($snapshot() === $baseline, 'Save hook skips revisions');
    $clear_source(); get_post($base); get_post_meta($base, $key, true); $wpdb->get_var('SELECT iss_graph_previous_unrelated_error');
    $expect($sync()['synced'], 'Cached source reads do not mistake an earlier SQL error for their failure');
    $expect(has_action('save_post', 'iss_graph_sync_video_transcript_mentions_on_save') === 45 && has_action('save_post', 'iss_graph_save_video_transcript_review') === 58, 'Automatic and review hook priorities retained');
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('render_block', $render_hook);
    remove_filter('iss_graph_public_content_native_relation_rows', $native, 10);
    $wpdb->query('ROLLBACK'); $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { if ($wpdb->query("DROP TEMPORARY TABLE `$table`") === false) { $error = new RuntimeException('Fixture cleanup failed'); } }
    wp_cache_flush(); $wpdb->suppress_errors($old_suppression);
}
$expect($real_state() === $original && $read($wpdb->options) === $original_options, 'Real graph, transcript status, queue and options unchanged; no fixture source posts');
if ($error !== null) { WP_CLI::error("After {$checks} checks: " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Video transcript atomicity: {$checks} checks passed; real rows unchanged; temporary fixtures removed.");
