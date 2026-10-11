<?php
/** Local WP-CLI integration checks; fixture changes roll back, no external calls. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated transactional fixtures in native term/options tables.
global $wpdb, $wp_taxonomies;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$groups = [
    ['iss_content_model_maybe_seed_taxonomy_terms', ISS_CONTENT_MODEL_TAXONOMY_SEED_OPTION, ISS_CONTENT_MODEL_TAXONOMY_SEED_VERSION, array_keys(iss_content_model_get_default_taxonomy_terms()), 'iss-content/iss-content.php'],
    ['iss_content_fallback_seed_categories', ISS_CONTENT_FALLBACK_CATEGORY_SEED_OPTION, ISS_CONTENT_FALLBACK_CATEGORY_SEED_VERSION, ['category'], 'iss-content/iss-content.php'],
    ['iss_wf_import_ensure_museum_digital_terms', ISS_WF_IMPORT_TAXONOMY_SEED_OPTION, ISS_WF_IMPORT_TAXONOMY_SEED_VERSION, [ISS_WF_IMPORT_FIELD_TAXONOMY, ISS_WF_IMPORT_FAMILY_TAXONOMY, ISS_WF_IMPORT_CONTEXT_TAXONOMY, ISS_WF_IMPORT_DECADE_TAXONOMY], 'iss-archive/iss-archive.php'],
];
$tables = [$wpdb->terms, $wpdb->term_taxonomy, $wpdb->term_relationships, $wpdb->termmeta, $wpdb->options];
foreach ($tables as $table) {
    $engine = $wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table));
    $expect($engine === 'InnoDB', $table . ': rollback requires InnoDB');
}
$expect(!wp_using_ext_object_cache(), 'Test cache must be local to this PHP process');
$before = [];
foreach ($tables as $table) { $before[$table] = $wpdb->get_results("SELECT * FROM `$table` ORDER BY 1", ARRAY_A); }
$user_id = get_current_user_id();
$taxonomies_before = $wp_taxonomies;
$deny_http = static fn() => new WP_Error('taxonomy_test_no_network', 'Network is disabled in this test.');
$deny_mail = static fn() => false;
add_filter('pre_http_request', $deny_http);
add_filter('pre_wp_mail', $deny_mail);
$error = null;
$insert_failure = null;
$version_failure = null;
$ajax_filter = static fn() => true;
$expect($wpdb->query('START TRANSACTION') !== false, 'Start isolated fixture transaction');
try {
    foreach ($groups as [$seed, $option, $version, $taxonomies, $plugin]) {
        $expect(has_action('init', $seed) === false, $seed . ': never runs on public init');
        $expect(has_action('activate_' . $plugin, $seed) !== false, $seed . ': installed through activation');
        $expect($wpdb->query('SAVEPOINT taxonomy_seed_fixture') !== false, 'Create fixture savepoint');
        delete_option($option);
        wp_cache_flush();

        // Simulate a fresh term set in this connection only. Real rows remain
        // visible to other requests and are restored by the savepoint rollback.
        foreach ($taxonomies as $taxonomy) {
            $expect($wpdb->delete($wpdb->term_taxonomy, ['taxonomy' => $taxonomy]) !== false, 'Prepare unseeded taxonomy');
        }
        wp_cache_flush();
        $expect($seed() === true, $seed . ': fresh installation succeeds');
        $expect(get_option($option) === $version, 'Version recorded after successful inserts');
        $term_count = (int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->term_taxonomy");
        $queries = $wpdb->num_queries;
        $expect($seed() === true, 'Repeated installation succeeds');
        $expect($wpdb->num_queries === $queries, 'Current version performs no queries with primed option cache');
        $expect((int) $wpdb->get_var("SELECT COUNT(*) FROM $wpdb->term_taxonomy") === $term_count, 'Repeated installation creates no duplicate terms');

        // A failed insert keeps the old marker; retry must fill the remainder.
        update_option($option, 'test-old-version', false);
        $taxonomy = $taxonomies[0];
        $wpdb->delete($wpdb->term_taxonomy, ['taxonomy' => $taxonomy]);
        wp_cache_flush();
        $insert_failure = static fn() => new WP_Error('seed_insert_failed', 'Injected insertion failure');
        add_filter('pre_insert_term', $insert_failure);
        $expect($seed() === false, 'Insertion failure is reported');
        $expect(get_option($option) === 'test-old-version', 'Insertion failure cannot advance version');
        remove_filter('pre_insert_term', $insert_failure);
        $insert_failure = null;
        $expect($seed() === true, 'A later controlled attempt recovers from failed insertion');

        update_option($option, 'test-old-version', false);
        $version_failure = static fn($new, $old) => $old;
        add_filter('pre_update_option_' . $option, $version_failure, 10, 2);
        $expect($seed() === false, 'Failed version persistence is reported');
        $expect(get_option($option) === 'test-old-version', 'Failed version persistence leaves upgrade pending');
        remove_filter('pre_update_option_' . $option, $version_failure);
        $version_failure = null;

        $registration = $wp_taxonomies[$taxonomy];
        unset($wp_taxonomies[$taxonomy]);
        $expect($seed() === false, 'Missing taxonomy leaves migration pending');
        $expect(get_option($option) === 'test-old-version', 'Missing taxonomy cannot advance version');
        $wp_taxonomies[$taxonomy] = $registration;
        $expect($seed() === true, 'Retry after registration succeeds');

        $expect($wpdb->query('ROLLBACK TO SAVEPOINT taxonomy_seed_fixture') !== false, 'Restore real term and option rows');
        wp_cache_flush();
        $terms_before = $wpdb->get_results("SELECT * FROM $wpdb->terms ORDER BY term_id", ARRAY_A);
        update_option($option, 'test-old-version', false);
        $expect($seed() === true, 'Upgrade of an existing installation succeeds');
        $expect($wpdb->get_results("SELECT * FROM $wpdb->terms ORDER BY term_id", ARRAY_A) === $terms_before, 'Upgrade preserves existing IDs, names and slugs');
        $wpdb->query('ROLLBACK TO SAVEPOINT taxonomy_seed_fixture');
        wp_cache_flush();
    }

    foreach ($groups as $group) { update_option($group[1], 'test-old-version', false); }
    wp_set_current_user(0);
    iss_content_model_maybe_upgrade_taxonomy_terms();
    iss_wf_import_maybe_upgrade_taxonomy_terms();
    foreach ($groups as $group) { $expect(get_option($group[1]) === 'test-old-version', 'Anonymous requests cannot run admin upgrades'); }
    $admins = get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 1]);
    $expect(count($admins) === 1, 'Existing local administrator available');
    wp_set_current_user((int) $admins[0]);
    add_filter('wp_doing_ajax', $ajax_filter);
    iss_content_model_maybe_upgrade_taxonomy_terms();
    iss_wf_import_maybe_upgrade_taxonomy_terms();
    foreach ($groups as $group) { $expect(get_option($group[1]) === 'test-old-version', 'AJAX requests cannot trigger setup'); }
    remove_filter('wp_doing_ajax', $ajax_filter);

    $pending = array_merge(iss_content_model_taxonomy_seed_status_rows([]), iss_wf_import_taxonomy_seed_status_rows([]));
    $expect(array_column($pending, 'status') === ['warning', 'warning', 'warning'], 'Existing diagnostics expose pending or failed setup');
    iss_content_model_maybe_upgrade_taxonomy_terms();
    iss_wf_import_maybe_upgrade_taxonomy_terms();
    foreach ($groups as $group) { $expect(get_option($group[1]) === $group[2], 'Privileged admin upgrade completes'); }
    $ready = array_merge(iss_content_model_taxonomy_seed_status_rows([]), iss_wf_import_taxonomy_seed_status_rows([]));
    $expect(array_column($ready, 'status') === ['ok', 'ok', 'ok'], 'Existing diagnostics report completed setup');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($insert_failure) { remove_filter('pre_insert_term', $insert_failure); }
    if ($version_failure) { remove_filter('pre_update_option_' . $option, $version_failure); }
    remove_filter('wp_doing_ajax', $ajax_filter);
    remove_filter('pre_http_request', $deny_http);
    remove_filter('pre_wp_mail', $deny_mail);
    $wpdb->query('ROLLBACK');
    $wp_taxonomies = $taxonomies_before;
    wp_cache_flush();
    wp_set_current_user($user_id);
}
foreach ($before as $table => $rows) {
    $expect($wpdb->get_results("SELECT * FROM `$table` ORDER BY 1", ARRAY_A) === $rows, $table . ': all real rows preserved');
}
if ($error) { WP_CLI::error($error->getMessage()); }
WP_CLI::success($checks . ' taxonomy seed checks passed; all fixture rows rolled back.');
