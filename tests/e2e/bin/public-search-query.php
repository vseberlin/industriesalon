<?php
/** Isolated pagination/visibility checks; temporary tables shadow owner schemas. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Connection-local fixtures and owner query EXPLAIN.
global $wpdb;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and caches are process-local');
$owner = iss_graph_get_service();
$tables = [$wpdb->posts, $wpdb->postmeta, $owner->get_search_table_name(), $owner->get_editorial_signal_table_name()];
$checksum = static fn($table) => $wpdb->get_row("CHECKSUM TABLE `$table`", ARRAY_N)[1];
$before = array_map($checksum, $tables);
$schemas = [];
foreach ($tables as $table) { $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1); }
$template = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_status = 'publish' LIMIT 1", ARRAY_A);
$base = 2147478000;
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 1100)) === 0, 'Fixture identities are unused');
$created = [];
$main = $GLOBALS['wp_the_query'];
$user = get_current_user_id();
$error = null;
try {
    foreach ($schemas as $table => $schema) { $expect($wpdb->query($schema) !== false, 'Create temporary owner schema'); $created[] = $table; }
    $search_table = $owner->get_search_table_name();
    $signal_table = $owner->get_editorial_signal_table_name();
    for ($index = 0; $index < 1005; ++$index) {
        $post = array_merge($template, ['ID' => $base + $index, 'post_title' => 'Fixture needle', 'post_name' => 'fixture-' . $index, 'post_content' => '', 'post_excerpt' => '', 'post_type' => 'post', 'post_status' => 'publish', 'post_password' => '', 'post_date_gmt' => '2026-01-01 00:00:00']);
        if ($index < 3) { $post['post_status'] = ['draft', 'private', 'future'][$index]; }
        if ($index === 3) { $post['post_password'] = 'fixture-protected'; }
        if (in_array($index, [4,5,6], true)) { $post['post_type'] = iss_graph_get_register_post_type(); }
        if ($wpdb->insert($wpdb->posts, $post) !== 1 || $wpdb->insert($search_table, ['target_post_id' => $post['ID'], 'target_post_type' => $post['post_type'], 'search_bucket' => 'story', 'title' => 'Fixture needle', 'excerpt' => '', 'search_text' => 'Fixture needle', 'boost' => 0, 'is_public' => 1, 'updated_at' => '2026-01-01 00:00:00']) !== 1) { throw new RuntimeException('Could not seed search fixture'); }
    }
    $wpdb->insert($wpdb->postmeta, ['post_id' => $base + 4, 'meta_key' => 'place_visibility', 'meta_value' => 'tour_only']);
    $wpdb->insert($wpdb->postmeta, ['post_id' => $base + 5, 'meta_key' => 'place_visibility', 'meta_value' => 'public']);
    $signal = static function (int $offset, string $type, ?string $expires = null) use ($wpdb, $signal_table, $base): void {
        if ($wpdb->insert($signal_table, ['context_post_id' => $base + $offset, 'target_post_id' => $base + $offset, 'surface' => 'search', 'signal_type' => $type, 'status' => 'active', 'expires_at' => $expires, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) !== 1) { throw new RuntimeException('Could not seed editorial signal'); }
    };
    $signal(7, 'suppress'); $signal(8, 'pin'); $signal(9, 'boost'); $signal(10, 'hide', '2000-01-01 00:00:00');
    $page = iss_search_public_page('needle', ['limit' => 3]);
    $expect($page['total'] === 999 && count($page['items']) === 3, 'Total excludes draft/private/future/password/tour-only/suppressed rows before pagination');
    $expect(array_column($page['items'], 'post_id') === [$base + 8, $base + 9, $base + 1004], 'Pin, boost and stable date/ID relevance ordering retained');
    $all = [];
    for ($offset = 0; $offset < 1000; $offset += 100) {
        $part = iss_search_public_page('needle', ['limit' => 100, 'offset' => $offset]);
        $expect($part['total'] === 999 && count($part['items']) <= 100, 'Every page keeps complete totals and bounded rows');
        $all = array_merge($all, array_column($part['items'], 'post_id'));
    }
    $expect(count($all) === 999 && count(array_unique($all)) === 999, 'Pagination covers all visible matches exactly once');
    $expect(in_array($base + 5, $all, true) && in_array($base + 6, $all, true) && in_array($base + 10, $all, true), 'Public/missing place visibility and expired hide remain visible');
    $expect(iss_search_public_page('needle', ['offset' => 1000]) === ['items' => [], 'total' => 999], 'Out-of-range page retains exact total');
    $expect(iss_search_public_page('needle', ['types' => ['nonexistent']]) === ['items' => [], 'total' => 0], 'Unsupported type cannot widen results');
    $expect(iss_search_public_page('') === ['items' => [], 'total' => 0], 'Empty search remains empty');
    $q = new WP_Query(); $GLOBALS['wp_the_query'] = $q;
    $q->query(['s' => 'needle', 'posts_per_page' => 3, 'paged' => 2]);
    $expect(array_column($q->posts, 'ID') === array_slice($all, 3, 3) && $q->found_posts === 999 && $q->max_num_pages === 333, 'WP main search receives the exact requested page and full pagination');
    $expect(!$q->get('post__in') && !$q->get('meta_query') && !$q->get('cache_results'), 'Main query has no large ID list, duplicate visibility filter or stale projection cache');
    $q->query(['s' => 'needle', 'posts_per_page' => 100, 'paged' => 11]);
    $expect(!$q->posts && $q->found_posts === 999 && $q->max_num_pages === 10, 'Empty WP page retains total and maximum page');
    $q->query(['s' => 'needle', 'posts_per_page' => 100, 'paged' => PHP_INT_MAX]);
    $expect(!$q->posts && $q->found_posts === 999, 'Extreme page offset cannot overflow back to the first page');
    $GLOBALS['wp_the_query'] = $main;
    $ordinary = new WP_Query(['post_type' => 'post', 'posts_per_page' => 2]);
    $expect(!$ordinary->get('iss_graph_public_search') && count($ordinary->posts) === 2, 'Ordinary editorial WP_Query remains untouched');
    $wpdb->update($wpdb->posts, ['post_status' => 'publish'], ['ID' => $base]);
    $wpdb->delete($signal_table, ['target_post_id' => $base + 7]);
    $wpdb->delete($wpdb->postmeta, ['post_id' => $base + 4]);
    $expect(iss_search_public_page('needle')['total'] === 1002 && count(iss_search_public_page('needle', ['offset' => 1000])['items']) === 2, 'Fresh visibility/signals and matches beyond the old 1000 cap are reachable without rebuilding');
    $wpdb->update($wpdb->posts, ['post_status' => 'private'], ['ID' => $base + 1004]);
    wp_cache_delete($base + 1004, 'posts');
    $expect(!in_array($base + 1004, array_column(iss_search_public_posts('needle', ['limit' => 100]), 'post_id'), true), 'Stale public index row cannot expose a newly private source');
    $admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) $admin[0]);
    $expect(iss_search_public_page('needle')['total'] === 1001, 'Authenticated public search has the same privacy boundary');
    $expect(iss_search_prepare_result(['post_id' => $base + 3]) === null, 'Direct result preparation also rejects password content');
    $expect(iss_search_public_page("needle' OR 1=1 --")['total'] === 0, 'Prepared token predicates treat SQL-like text literally');
    $unavailable_provider = static fn() => 'meili';
    add_filter('iss_search_provider', $unavailable_provider);
    try { $expect(iss_search_public_page('needle')['total'] === 1001, 'Full search falls back to SQL when the optional provider is unavailable'); }
    finally { remove_filter('iss_search_provider', $unavailable_provider); }
} catch (Throwable $caught) { $error = $caught; }
finally {
    $GLOBALS['wp_the_query'] = $main; wp_set_current_user($user);
    foreach (array_reverse($created) as $table) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    for ($index = 0; $index < 1005; ++$index) { wp_cache_delete($base + $index, 'posts'); wp_cache_delete($base + $index, 'post_meta'); }
}
$expect(array_map($checksum, $tables) === $before, 'All real posts/metadata/search/signals remain unchanged');
if ($error) { throw $error; }
WP_CLI::success("Public SQL search: $checks checks passed; temporary fixtures removed; real rows retained.");
