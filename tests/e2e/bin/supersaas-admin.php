<?php
/** Admin workflow regression checks using connection-local tables and intercepted HTTP. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Disposable temporary tables only.
global $wpdb;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled, process-local cache');
$expect(current_user_can(iss_occurrences_sync_capability()), 'Run with authorized --user');
$owner = iss_occurrences_get_service();
$tables = [$wpdb->posts, $wpdb->postmeta, $wpdb->options, $owner->get_series_table_name(), $owner->get_occurrences_table_name(), $owner->get_supersaas_slots_table_name()];
$protected = array_merge($tables, [iss_payments_lite_requests_table_name(), ISS_Commerce_Ledger::table('departures')]);
$checksum = static fn($table) => $wpdb->get_row("CHECKSUM TABLE `$table`", ARRAY_N)[1];
$before = array_map($checksum, $protected);
$options = $wpdb->get_results("SELECT * FROM {$wpdb->options}", ARRAY_A);
$schemas = [];
foreach ($tables as $table) { $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1); }
$created = []; $error = null; $base = 2147476100; $old_user = get_current_user_id();
$http_fail = false; $http_calls = 0; $reject = '';
$start = wp_date('Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS);
$http = static function () use (&$http_fail, &$http_calls, $start) {
    ++$http_calls;
    if ($http_fail) { return new WP_Error('fixture', 'Test: Kalender nicht erreichbar'); }
    return ['response' => ['code' => 200], 'headers' => [], 'body' => wp_json_encode(['slots' => [['id' => 'fixture-slot', 'name' => 'Fixture title', 'title' => 'Fixture title', 'start' => $start, 'finish' => wp_date('Y-m-d H:i:s', time() + 2 * DAY_IN_SECONDS + HOUR_IN_SECONDS)]]])];
};
$clear_cache = static function () use ($wpdb): void {
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient%iss_supersaas_%'"); wp_cache_flush();
};
$redirect = static function ($url) { throw new RuntimeException('redirect:' . $url); };
$upload = static function ($dir) { $dir['basedir'] = sys_get_temp_dir() . '/iss-supersaas-admin-test'; return $dir; };
$reject_query = static function ($sql) use (&$reject) { return $reject !== '' && str_contains($sql, $reject) && str_starts_with($sql, 'INSERT INTO') ? 'UPDATE iss_missing_admin_test_table SET x=1' : $sql; };
$old_errors = $wpdb->suppress_errors(true);
$invoke = static function (string $action, array $fields, string $nonce = 'iss_occurrences_sync_series_source_action') use ($redirect): string {
    $_POST = array_merge($fields, ['_wpnonce' => wp_create_nonce($nonce), '_wp_http_referer' => admin_url('admin.php?page=iss-occurrences-sync&view=series&kind=tours&series_key=tour:fixture-title')]);
    $_REQUEST = $_POST;
    add_filter('wp_redirect', $redirect, 1);
    try { do_action('admin_post_' . $action); } catch (RuntimeException $error) {
        if (!str_starts_with($error->getMessage(), 'redirect:')) { throw $error; }
        return substr($error->getMessage(), 9);
    } finally { remove_filter('wp_redirect', $redirect, 1); }
    throw new RuntimeException('Action did not redirect');
};
try {
    foreach ($schemas as $table => $schema) { $expect($wpdb->query($schema) !== false, 'Create temporary shadow'); $created[] = $table; }
    foreach ($options as $option) { if ($wpdb->insert($wpdb->options, $option) !== 1) { throw new RuntimeException('Shadow options'); } }
    wp_cache_flush(); add_filter('pre_http_request', $http); add_filter('upload_dir', $upload); add_filter('query', $reject_query);
    foreach ([$base => ['fuehrung', 'Fixture title'], $base + 1 => ['fuehrung', 'Staff selected tour'], $base + 2 => ['veranstaltung', 'Event fixture']] as $id => [$type, $title]) {
        $expect($wpdb->insert($wpdb->posts, ['ID' => $id, 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title, 'post_name' => 'fixture-' . $id, 'post_content' => '', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '']) === 1, 'Seed source');
    }
    $settings = ['api_key' => 'fixture-secret-not-for-render', 'base_url' => 'https://fixture.invalid', 'account_name' => 'fixture', 'schedule_id' => '', 'schedule_path' => '', 'schedules' => [['enabled' => 1, 'key' => 'public', 'label' => 'Führungen', 'schedule_id' => '123', 'schedule_path' => 'fixture', 'source' => 'free']]];
    update_option(ISS_SUPERSAAS_SETTINGS_OPTION, $settings);
    $settings = iss_supersaas_get_settings();
    $invalid = $settings; $invalid['schedules'][0]['schedule_id'] = '12x';
    $expect(iss_supersaas_sanitize_settings($invalid) === $settings, 'Invalid ID preserves complete configuration');
    $invalid = $settings; $invalid['schedules'][] = $settings['schedules'][0];
    $expect(iss_supersaas_sanitize_settings($invalid) === $settings, 'Duplicate calendar key rejected');
    $invalid = $settings; $invalid['schedules'][0]['key'] = 'renamed';
    $expect(iss_supersaas_sanitize_settings($invalid) === $settings, 'Existing stable key cannot be renamed');
    $valid = $settings; $valid['api_key'] = ''; $valid['schedules'][0]['enabled'] = 0;
    $normalized = iss_supersaas_sanitize_settings($valid);
    $expect($normalized['api_key'] === $settings['api_key'] && iss_supersaas_get_schedule_configs($normalized) === [], 'Blank key preserved and disabling all calendars has no legacy fallback');
    ob_start(); iss_supersaas_field_api_key(); $key_html = ob_get_clean();
    $expect(!str_contains($key_html, $settings['api_key']), 'Saved secret never rendered');
    $GLOBALS['wp_settings_errors'] = [];
    $key = 'tour:fixture-title';
    $expect(iss_occurrences_remember_series_source($key, $base + 1, 'fuehrung', 'Fixture title'), 'Seed manual mapping contrary to exact-title match');
    $expect(!is_wp_error(iss_occurrences_validate_tour_assignment($key, $base + 1)), 'Current mapping valid');
    $expect(iss_occurrences_remember_series_source('event:fixture', $base + 2, 'veranstaltung', 'Event fixture'), 'Seed event series');
    $expect(is_wp_error(iss_occurrences_validate_tour_assignment('event:fixture', $base)), 'Event series cannot be assigned to a tour');
    $expect(!isset(iss_occurrences_get_series_source_options()['event:fixture']), 'Tour editor offers only tour series');
    $expect(iss_occurrences_remember_series_source('tour:other', $base, 'fuehrung', 'Another series'), 'Seed existing target assignment');
    $expect(is_wp_error(iss_occurrences_validate_tour_assignment($key, $base)), 'Another target assignment is not silently removed');
    iss_occurrences_remember_series_source('tour:existing-second', $base + 1, 'fuehrung', 'Existing additional series');
    $expect(!is_wp_error(iss_occurrences_validate_tour_assignment($key, $base + 1)), 'Existing multiple mappings allow retaining the current assignment');
    ob_start(); iss_occurrences_render_fuehrung_mapping_metabox(get_post($base + 1)); $editor_html = ob_get_clean();
    $expect(str_contains($editor_html, 'mehreren Terminreihen') && !str_contains($editor_html, 'name="iss_occurrences_series_key"'), 'Editor cannot collapse existing multiple mappings on ordinary save');
    $_POST = ['iss_occurrences_series_source_nonce' => wp_create_nonce('iss_occurrences_save_fuehrung_series_source'), 'iss_occurrences_series_key' => '', 'iss_occurrences_previous_series_key' => iss_occurrences_get_current_series_key_for_post($base + 1), 'iss_occurrences_confirm_change' => 1];
    do_action('save_post_fuehrung', $base + 1);
    $expect(count(iss_occurrences_resolve_series_keys_for_source_post_id($base + 1)) === 2 && $http_calls === 0, 'Old editor form cannot unlink a newly multi-series tour or trigger sync');
    $wpdb->delete($owner->get_series_table_name(), ['series_key' => 'tour:existing-second']);

    $_POST = ['expected_source' => $base, 'confirm_change' => 1];
    $expect(!iss_occurrences_check_assignment_change($base + 1), 'Stale source rejected');
    $_POST = ['expected_source' => $base + 1];
    $expect(!iss_occurrences_check_assignment_change($base + 1), 'Replacement without acknowledgement rejected');
    $expect(iss_occurrences_sync_result_notice(['errors' => 1])['type'] === 'error', 'Error count cannot render green success');
    $expect(iss_occurrences_sync_result_notice(['errors' => 0, 'error_message' => 'failed'])['type'] === 'error', 'Error message cannot render green success');
    $expect(iss_occurrences_sync_result_notice(['errors' => 0])['type'] === 'success', 'Successful result stays green');
    $result = iss_occurrences_run_admin_sync();
    $expect(empty($result['errors']), 'Fixture sync succeeds');
    $expect((int) $owner->get_series_by_key($key)['source_post_id'] === $base + 1, 'Exact title reconciliation preserves reviewed assignment');
    $slot = $owner->get_supersaas_slot_by_external('public:fixture-slot');
    $expect((int) $slot['source_post_id'] === $base + 1, 'Imported slot uses staff-selected tour over exact title');
    $expect(get_option('iss_maintenance_supersaas')['status'] === 'ok', 'Manual sync updates existing maintenance receipt');
    $url = $invoke('iss_occurrences_clear_series_source', ['series_key' => $key, 'expected_source' => $base + 1, 'confirm_change' => 1]);
    $expect(str_contains($url, 'view=series') && str_contains($url, 'kind=tours'), 'Action preserves navigation context');
    $expect((int) $owner->get_series_by_key($key)['source_post_id'] === 0 && $owner->get_series_by_key($key)['review_state'] === 'unreviewed', 'Explicit unlink survives full sync');
    $expect((int) $owner->get_supersaas_slot_by_external('public:fixture-slot')['source_post_id'] === 0, 'Unlinked slot is not reattached by old projection or title');
    $invoke('iss_occurrences_ignore_series_source', ['series_key' => $key, 'expected_source' => 0]);
    $result = iss_occurrences_run_admin_sync();
    $expect($owner->get_series_by_key($key)['review_state'] === 'ignored' && $owner->get_supersaas_slot_by_external('public:fixture-slot')['match_state'] === 'ignored', 'Ignore persists through repeated sync');
    $invoke('iss_occurrences_ignore_series_source', ['series_key' => $key, 'expected_source' => 0, 'review_state' => 'unreviewed']);
    $expect($owner->get_supersaas_slot_by_external('public:fixture-slot')['match_state'] === 'unmapped', 'Restore returns ignored series to review');
    $last_success = get_option('iss_maintenance_supersaas')['last_success_at'];
    $http_fail = true; $clear_cache();
    $invoke('iss_occurrences_set_series_source', ['series_key' => $key, 'source_post_id' => $base + 1, 'expected_source' => 0]);
    $expect((int) $owner->get_series_by_key($key)['source_post_id'] === $base + 1, 'Mapping survives upstream failure');
    $receipt = get_option('iss_maintenance_supersaas');
    $expect($receipt['status'] === 'failed' && $receipt['last_success_at'] === $last_success, 'Failed manual run preserves last success');
    $result = get_transient('iss_occurrences_sync_result_' . $old_user);
    $expect(!empty($result['errors']), 'Row action preserves sync failure for feedback');
    $mapping_before = $owner->get_series_by_key($key);
    $invoke('iss_occurrences_set_series_source', ['series_key' => $key, 'source_post_id' => $base, 'expected_source' => $base + 1, 'confirm_change' => 1]);
    $expect($owner->get_series_by_key($key) === $mapping_before && (int) $owner->get_series_by_key('tour:other')['source_post_id'] === $base, 'Rejected conflicting assignment preserves both mappings');
    $invoke('iss_occurrences_clear_series_source', ['series_key' => $key, 'expected_source' => 0, 'confirm_change' => 1]);
    $expect($owner->get_series_by_key($key) === $mapping_before, 'Stale action leaves current series unchanged');
    $event_id = $owner->upsert_supersaas_slot(['schedule_key' => 'salonbelegung', 'schedule_label' => 'Veranstaltungen', 'slot_id' => 'event-fixture', 'external_id' => 'salonbelegung:event-fixture', 'series_key' => 'event:fixture', 'raw_title' => 'Event fixture', 'starts_at' => $start, 'status' => 'skipped', 'match_state' => 'unmapped']);
    $expect($event_id > 0, 'Seed unassigned event slot');
    $invoke('iss_occurrences_set_event_slot_source', ['slot_row_id' => $event_id, 'source_post_id' => $base + 2, 'expected_source' => 0], 'iss_occurrences_sync_slot_action');
    $expect((int) $owner->get_supersaas_slot($event_id)['source_post_id'] === $base + 2, 'Single event assignment uses event owner');
    $invoke('iss_occurrences_ignore_event_slot', ['slot_row_id' => $event_id, 'expected_source' => $base + 2, 'confirm_change' => 1], 'iss_occurrences_sync_slot_action');
    $expect($owner->get_supersaas_slot($event_id)['match_state'] === 'ignored', 'Ignore event slot clears assignment');
    $invoke('iss_occurrences_ignore_event_slot', ['slot_row_id' => $event_id, 'expected_source' => 0, 'review_state' => 'unreviewed'], 'iss_occurrences_sync_slot_action');
    $expect($owner->get_supersaas_slot($event_id)['match_state'] === 'unmapped', 'Restore event slot returns it to review');
    $_POST = ['_wp_http_referer' => 'https://outside.invalid/?page=iss-occurrences-sync&view=review&paged=2&redirect_to=https://outside.invalid/'];
    $return = iss_occurrences_sync_return_url();
    $expect(str_starts_with($return, iss_occurrences_sync_admin_url()) && !str_contains($return, 'outside.invalid') && str_contains($return, 'paged=2'), 'Return URL preserves filters but cannot redirect externally');
    $expect(get_transient('iss_occurrences_sync_result_0') === false, 'Results isolated to the acting user');

    $reject = $owner->get_series_table_name();
    $expect(!iss_occurrences_remember_series_source($key, $base, 'fuehrung', 'Fixture title'), 'Failed SQL upsert cannot report success via existing row'); $reject = '';
    $_GET = ['view' => 'series', 'kind' => 'tours']; ob_start(); iss_occurrences_render_sync_series(); $html = ob_get_clean();
    $expect(!str_contains($html, 'Event fixture') && str_contains($html, 'Fixture title'), 'Tour view excludes event series');
    $_GET = ['view' => 'series', 'kind' => 'events']; ob_start(); iss_occurrences_render_sync_series(); $html = ob_get_clean();
    $expect(str_contains($html, 'Event fixture') && !str_contains($html, 'name="action" value="iss_occurrences_set_series_source"'), 'Event view exposes event controls only');
    $_GET = ['view' => 'review', 's' => 'no-such-fixture']; ob_start(); iss_occurrences_render_sync_review(); $html = ob_get_clean();
    $expect(str_contains($html, 'Keine Termine für diese Auswahl') && !str_contains($html, 'Noch keine Termine importiert'), 'Filtered empty state does not suggest missing data');
    $slot = $owner->get_supersaas_slot_by_external('public:fixture-slot');
    $wpdb->update($owner->get_supersaas_slots_table_name(), ['match_state' => 'invalid_source'], ['id' => $slot['id']]);
    $expect($owner->count_supersaas_slots(['match_state' => 'attention', 'date_scope' => 'future']) === 2, 'Review queue includes invalid targets');
    $expect($owner->count_supersaas_slots(['series_key' => 'tour:missing']) === 0, 'Series drilldown uses exact owner filter');
    $_GET = ['page' => ISS_SUPERSAAS_SETTINGS_PAGE];
    add_filter('wp_redirect', $redirect, 1);
    try { iss_supersaas_redirect_legacy_settings_page(); } catch (RuntimeException $redirected) {
        $expect($redirected->getMessage() === 'redirect:' . add_query_arg('view', 'connection', iss_occurrences_sync_admin_url()), 'Legacy settings bookmark redirects to the canonical connection view');
    } finally { remove_filter('wp_redirect', $redirect, 1); }
    iss_supersaas_register_settings();
    // Render each tab and validate control labels without logging any records or tokens.
    foreach (array_keys(iss_occurrences_sync_views()) as $view) {
        $_GET = ['view' => $view]; $_POST = []; $_SERVER['REQUEST_URI'] = '/wp-admin/admin.php?page=iss-occurrences-sync&view=' . $view;
        ob_start(); iss_occurrences_render_sync_page(); $html = ob_get_clean();
        $expect(str_contains($html, 'Termine &amp; Synchronisierung'), 'Render view ' . $view);
        $expect(!str_contains($html, $settings['api_key']), 'No saved secret in view ' . $view);
        $dom = new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">' . $html); $xp = new DOMXPath($dom);
        foreach ($xp->query('//select | //input[not(@type="hidden") and not(@type="submit")]') as $control) {
            $id = $control->getAttribute('id');
            $label = $xp->query('ancestor::label', $control)->length || $control->hasAttribute('aria-label') || ($id !== '' && $xp->query('//label[@for="' . $id . '"]')->length);
            $expect((bool) $label, 'Control has label in ' . $view);
        }
    }
    $expect($http_calls > 0, 'Sync exercised intercepted upstream requests');
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('pre_http_request', $http); remove_filter('upload_dir', $upload); remove_filter('query', $reject_query);
    foreach (array_reverse($created) as $table) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    $_POST = []; $_GET = []; $_REQUEST = []; wp_cache_flush(); $wpdb->suppress_errors($old_errors); wp_set_current_user($old_user);
}
$after = array_map($checksum, $protected);
if ($error) { WP_CLI::warning('First failure: ' . $error->getMessage()); }
foreach ($protected as $index => $name) { if ($before[$index] !== $after[$index]) { WP_CLI::warning('Concurrent or unexpected table change: ' . $name); } }
$expect($after === $before, 'Real source, configuration, mappings, slots, bookings and inventory unchanged');
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success("SuperSaaS admin: $checks checks passed; no remote requests, real sync or booking mutations.");
