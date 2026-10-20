<?php
/** Current-source visibility and uncached rendering using connection-local tables. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated owner fixtures.
global $wpdb;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and caches process-local');
$owner = iss_occurrences_get_service();
$table = $owner->get_occurrences_table_name();
$tables = [$wpdb->posts, $wpdb->postmeta, $table, $owner->get_series_table_name(), $owner->get_supersaas_slots_table_name(), $wpdb->options];
$checksum = static fn($name) => $wpdb->get_row("CHECKSUM TABLE `$name`", ARRAY_N)[1];
$before = array_map($checksum, $tables);
$schemas = [];
foreach ($tables as $name) { $schemas[$name] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$name`", ARRAY_N)[1], 1); }
$template = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_status = 'publish' LIMIT 1", ARRAY_A);
$base = 2147477000;
$expect((int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 20)) === 0, 'Fixture identities unused');
$created = []; $error = null; $user = get_current_user_id();
$now = '2026-10-25 23:59:59';
$clock = static function () use (&$now): string { return $now; };
$http = null;
add_filter('iss_occurrences_query_now', $clock, 99);
try {
    foreach ($schemas as $name => $schema) { $expect($wpdb->query($schema) !== false, 'Create temporary schema'); $created[] = $name; }
    for ($index = 0; $index < 9; ++$index) {
        $post = array_merge($template, ['ID' => $base + $index, 'post_type' => 'veranstaltung', 'post_status' => 'publish', 'post_password' => '', 'post_title' => 'Occurrence fixture ' . $index, 'post_name' => 'occurrence-fixture-' . $index, 'post_content' => '', 'post_excerpt' => '']);
        if ($index < 3) { $post['post_status'] = ['private', 'draft', 'future'][$index]; }
        if ($index === 3) { $post['post_password'] = 'protected'; }
        if ($index === 4) { $post['post_type'] = 'post'; }
        if ($wpdb->insert($wpdb->posts, $post) !== 1) { throw new RuntimeException('Post fixture failed'); }
        if ($index === 5) { continue; }
        if ($wpdb->insert($table, ['source_post_id' => $index === 8 ? 0 : $base + $index, 'source_post_type' => $index === 8 ? '' : 'veranstaltung', 'kind' => 'event', 'title' => 'External fixture', 'starts_at' => '2026-10-25 12:00:00', 'status' => 'active', 'visibility' => 'public', 'origin' => 'wp', 'external_id' => (string) $index, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) !== 1) { throw new RuntimeException('Occurrence fixture failed'); }
    }
    // A removed source must not surface through its retained projection either.
    $wpdb->delete($wpdb->posts, ['ID' => $base + 6]);
    $filters = ['origin' => 'wp', 'limit' => 1, 'order' => 'ASC'];
    $first = $owner->query($filters);
    $expect(count($first) === 1 && $first[0]['source_post_id'] === $base + 7, 'Visibility excludes private/draft/future/password/mismatched/missing sources before limit');
    $expect($owner->query(array_merge($filters, ['offset' => 1]))[0]['source_post_id'] === 0, 'Public external occurrence remains visible on next page');
    $expect(count($owner->query(array_merge($filters, ['limit' => 20, 'group_recurring' => true]))) === 2, 'Grouped pagination shares current privacy boundary');
    $expect((int) (wp_cache_get($base + 7, 'posts')->ID ?? 0) === $base + 7 && is_array(wp_cache_get($base + 7, 'post_meta')), 'Source post and metadata primed in core caches');
    $request = new WP_REST_Request('GET', '/iss/v1/timeline');
    $request->set_param('filters', ['source_post_ids' => [$base + 7], 'time_mode' => 'all']);
    $initial = iss_timeline_rest_render_collection($request);
    $expect($initial->get_data()['count'] === 1 && $initial->get_headers()['Cache-Control'] === 'no-store', 'Public response renders current data with no-store');
    $wpdb->update($wpdb->posts, ['post_status' => 'private'], ['ID' => $base + 7]); wp_cache_delete($base + 7, 'posts');
    $expect(iss_timeline_rest_render_collection($request)->get_data()['count'] === 0, 'Same REST request cannot reuse HTML after source becomes private');
    $admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']); wp_set_current_user((int) $admin[0]);
    $expect(iss_timeline_rest_render_collection($request)->get_data()['count'] === 0, 'Authenticated public route keeps identical privacy boundary');
    $wpdb->update($wpdb->posts, ['post_status' => 'publish'], ['ID' => $base + 7]); wp_cache_delete($base + 7, 'posts');
    $request->set_param('filters', ['source_post_ids' => [$base + 7], 'time_mode' => 'upcoming']);
    $expect(iss_timeline_rest_render_collection($request)->get_data()['count'] === 1, 'Unknown-end event retained through Berlin DST date');
    $now = '2026-10-26 00:00:00';
    $expect(iss_timeline_rest_render_collection($request)->get_data()['count'] === 0, 'Same request rotates at next Berlin midnight');
    $now = '2026-10-25 12:00:00';
    $expect(iss_timeline_rest_render_collection($request)->get_data()['count'] === 1, 'Earlier authorized clock result cannot be poisoned by later render');
    // Run the real sync using isolated owner tables and a mandatory HTTP stub.
    $tour_id = $base + 10;
    $post = array_merge($template, ['ID' => $tour_id, 'post_type' => 'fuehrung', 'post_status' => 'publish', 'post_password' => '', 'post_title' => 'Fixture Tour', 'post_name' => 'fixture-tour', 'post_content' => '', 'post_excerpt' => '']);
    $expect($wpdb->insert($wpdb->posts, $post) === 1, 'Seed public tour');
    wp_cache_flush();
    update_option('timezone_string', 'Europe/Berlin');
    update_option(ISS_SUPERSAAS_SETTINGS_OPTION, ['account_name' => 'fixture', 'api_key' => 'fixture', 'base_url' => 'https://example.invalid', 'schedule_id' => '123', 'schedule_path' => 'public']);
    $owner->upsert_series(['series_key' => 'tour:fixture-tour', 'source_post_id' => $tour_id, 'source_post_type' => 'fuehrung', 'supersaas_title' => 'Fixture Tour', 'review_state' => 'mapped']);
    $owner->upsert_series(['series_key' => 'tour:fixture-tour-individuell', 'supersaas_title' => 'Fixture Tour individuell', 'review_state' => 'ignored']);
    $start = gmdate('Y-m-d', time() + DAY_IN_SECONDS) . ' 14:00:00';
    $end = gmdate('Y-m-d', time() + DAY_IN_SECONDS) . ' 15:30:00';
    $http = static function ($pre, $args, $url) use ($expect, $start, $end): array {
        $expect(str_starts_with($url, 'https://example.invalid/api/free/123.json'), 'All upstream calls intercepted at fixture endpoint');
        return ['headers' => [], 'body' => wp_json_encode(['slots' => [
            ['id' => 'special', 'title' => 'Fixture Tour Spezial', 'start' => $start, 'finish' => $end, 'capacity' => 10, 'count' => 4],
            ['id' => 'individual', 'title' => 'Fixture Tour individuell', 'start' => $start, 'finish' => $end, 'capacity' => 10, 'count' => 7],
        ]]), 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => []];
    };
    add_filter('pre_http_request', $http, PHP_INT_MAX, 3);
    for ($repeat = 0; $repeat < 2; ++$repeat) {
        $result = iss_supersaas_sync_occurrences();
        $expect($result['errors'] === 0, 'Isolated sync succeeds');
        $special = $owner->get_occurrence_by_external('supersaas', 'public:special');
        $expect($special && (int) $special['source_post_id'] === $tour_id && $special['title'] === 'Fixture Tour Spezial', 'Series matching preserves appointment title');
        $display = $owner->format_timeline_row($special);
        $expect($display['title'] === 'Fixture Tour Spezial' && $display['time_label'] === '14:00 – 15:30 Uhr', 'Public special label and actual duration survive formatting');
        $expect(!$owner->get_occurrence_by_external('supersaas', 'public:individual')
            && $owner->get_series_by_key('tour:fixture-tour-individuell')['review_state'] === 'ignored', 'Ignored individual series cannot rematch to regular public tour');
    }
} catch (Throwable $caught) { $error = $caught; }
finally {
    if ($http) { remove_filter('pre_http_request', $http, PHP_INT_MAX); }
    remove_filter('iss_occurrences_query_now', $clock, 99); wp_set_current_user($user);
    foreach (array_reverse($created) as $name) { $wpdb->query("DROP TEMPORARY TABLE `$name`"); }
    for ($index = 0; $index < 9; ++$index) { wp_cache_delete($base + $index, 'posts'); wp_cache_delete($base + $index, 'post_meta'); }
}
$expect(array_map($checksum, $tables) === $before, 'Real posts, metadata and occurrences unchanged');
if ($error) { throw $error; }
WP_CLI::success("Occurrence public query: $checks checks passed; real data retained.");
