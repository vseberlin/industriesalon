<?php
/** Connection-local projections; exercise ordinary callbacks and durable retries. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated fixtures and failure injection.
global $wpdb;
$service = iss_graph_get_service(); $checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and caches local');
$tables = [$service->get_entity_table_name(), $service->get_name_table_name(), $service->get_identifier_table_name(), $service->get_relation_table_name(), $service->get_evidence_table_name(), $service->get_search_table_name(), $service->get_person_facts_table_name(), $service->get_organization_facts_table_name(), $service->get_dirty_queue_table_name(), $service->get_editorial_signal_table_name(), $wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->termmeta, $wpdb->posts, $wpdb->postmeta, $wpdb->options];
$checksum = static fn($table) => $wpdb->get_row("CHECKSUM TABLE `$table`", ARRAY_N)[1];
$before = array_map($checksum, $tables);
$options = $wpdb->get_results("SELECT * FROM {$wpdb->options}", ARRAY_A);
$template = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' LIMIT 1", ARRAY_A);
$schemas = [];
foreach ($tables as $table) { $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1); }
$base = 2147476000;
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 3)) === 0, 'Fixture IDs unused');
$queue = $service->get_dirty_queue_table_name(); $search = $service->get_search_table_name();
$row = static fn($id) => $wpdb->get_row($wpdb->prepare("SELECT * FROM `$queue` WHERE target_post_id = %d", $id), ARRAY_A);
$search_writes = 0; $term_writes = 0; $fail_search = false;
$watch = static function ($sql) use ($search, &$search_writes, &$fail_search) {
    if (preg_match('/^(INSERT INTO|UPDATE) `?' . preg_quote($search, '/') . '`? /', $sql)) {
        ++$search_writes;
        if ($fail_search) { return 'INSERT INTO iss_deliberately_missing_projection_fixture_table VALUES (1)'; }
    }
    return $sql;
};
$terms = static function ($id) use ($base, &$term_writes) { if ((int) $id === $base) { ++$term_writes; } };
$created = []; $error = null; $old_suppress = $wpdb->suppress_errors(true);
$pending = $GLOBALS['iss_graph_request_projection_ids'] ?? [];
try {
    foreach ($schemas as $table => $schema) { $expect($wpdb->query($schema) !== false, 'Create temporary schema'); $created[] = $table; }
    foreach ($options as $option) { if ($wpdb->insert($wpdb->options, $option) !== 1) { throw new RuntimeException('Could not shadow options'); } }
    $wpdb->delete($wpdb->options, ['option_name' => 'iss_graph_dirty_queue_lock']);
    foreach (['alloptions', 'notoptions', 'iss_graph_dirty_queue_lock'] as $key) { wp_cache_delete($key, 'options'); }
    $post = array_merge($template, ['ID' => $base, 'post_title' => 'Coalesced title', 'post_name' => 'coalesced-fixture', 'post_content' => '', 'post_excerpt' => '', 'post_password' => '']);
    $expect($wpdb->insert($wpdb->posts, $post) === 1, 'Create fixture post');
    $service->mark_post_dirty($base + 1, 'unrelated', 'fixture');
    add_filter('query', $watch); add_action('set_object_terms', $terms);
    iss_graph_mark_related_post_dirty_on_save($base, get_post($base));
    iss_graph_sync_public_search_on_save($base, get_post($base));
    iss_graph_refresh_public_content_entity_on_save($base, get_post($base));
    iss_relations_sync_supported_post_on_save($base, get_post($base));
    for ($i = 0; $i < 5; ++$i) {
        iss_relations_sync_post_terms_on_meta_change(1, $base, ISS_RELATIONS_META_KEY);
        iss_graph_sync_public_search_on_meta_change(1, $base, iss_graph_get_search_index_meta_keys_for_post_type('post')[0]);
    }
    $expect($search_writes === 0 && $term_writes === 0 && $row($base)['status'] === 'dirty', 'Repeated save/meta callbacks defer once with durable work already recorded');
    $expect($service->drain_dirty_queue(['post_ids' => []])['selected'] === 0, 'Empty scope cannot drain unrelated work');
    $expect($service->drain_dirty_queue(['post_ids' => 'invalid'])['selected'] === 0, 'Invalid scope fails closed');
    iss_graph_flush_request_projections();
    $expect($search_writes === 1 && $term_writes === 1, 'One search and one term projection after all save callbacks');
    $expect($row($base)['status'] === 'clean' && $row($base + 1)['status'] === 'dirty', 'Only the request source is acknowledged; historical work untouched');
    $expect($service->get_search_row_by_post($base)['title'] === 'Coalesced title', 'Final canonical source indexed');
    iss_graph_flush_request_projections();
    $expect($search_writes === 1, 'Repeated flush does not repeat completed projection');
    iss_graph_sync_public_search_on_save($base, get_post($base)); $fail_search = true;
    iss_graph_flush_request_projections();
    $expect($row($base)['status'] === 'dirty' && (int) $row($base)['attempts'] === 1 && str_contains($row($base)['last_error'], 'search projection'), 'Write failure remains retryable with actionable queue error');
    $fail_search = false;
    $retry = $service->drain_dirty_queue(['post_ids' => [$base]]);
    $expect($retry['processed'] === 1 && $row($base)['status'] === 'clean' && $row($base + 1)['status'] === 'dirty', 'Existing maintenance drain repairs the failed source only');
    $lock_method = new ReflectionMethod($service, 'acquire_dirty_queue_lock');
    $release = new ReflectionMethod($service, 'release_dirty_queue_lock');
    $token = $lock_method->invoke($service, 60);
    iss_graph_sync_public_search_on_save($base, get_post($base));
    iss_graph_flush_request_projections();
    $expect($row($base)['status'] === 'dirty', 'Busy worker leaves request work durable');
    $release->invoke($service, $token);
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('query', $watch); remove_action('set_object_terms', $terms);
    $GLOBALS['iss_graph_request_projection_ids'] = $pending;
    foreach (array_reverse($created) as $table) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    $wpdb->suppress_errors($old_suppress);
    wp_cache_flush();
}
$expect(array_map($checksum, $tables) === $before, 'All real source, projection, taxonomy and option rows unchanged');
if ($error) { throw $error; }
WP_CLI::success("Projection coalescing: $checks checks passed; real state retained.");
