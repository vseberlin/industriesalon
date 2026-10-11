<?php
/** Privileged/versioned setup with temporary options; no real settings changed. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Temporary fixture schema and injected failures.
global $wpdb;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron and external cache disabled');
$expect(has_action('plugins_loaded', 'iss_core_maybe_run_capability_migration') === false && has_action('newsletter_loaded', 'iss_newsletter_configure_defaults') === false, 'Public bootstrap has no setup hooks');
$table = $wpdb->options; $rows = $wpdb->get_results("SELECT * FROM `$table` ORDER BY option_id", ARRAY_A);
$schema = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1);
$user = get_current_user_id(); $roles = clone wp_roles(); $created = false; $error = null; $old_suppress = $wpdb->suppress_errors(true);
$write_count = 0; $reject_option = '';
$no_audit_file = static fn($upload) => array_merge($upload, ['error' => 'Fixture does not write upload audit files']);
add_filter('upload_dir', $no_audit_file);
$watch = static function ($sql) use (&$write_count, &$reject_option) {
    if (preg_match('/^(INSERT|UPDATE|DELETE|REPLACE) /', $sql)) {
        ++$write_count;
        if ($reject_option !== '' && str_contains($sql, $reject_option)) { return 'UPDATE iss_missing_setup_fixture_table SET x=1'; }
    }
    return $sql;
};
$expect_failure = static function (callable $run) use ($expect): void { $failed = false; try { $run(); } catch (RuntimeException $e) { $failed = true; } $expect($failed, 'Persistence failure reaches setup caller'); };
try {
    $expect($wpdb->query($schema) !== false, 'Create temporary options'); $created = true;
    foreach ($rows as $row) { if ($wpdb->insert($table, $row) !== 1) { throw new RuntimeException('Shadow option failed'); } }
    wp_cache_flush(); add_filter('query', $watch);
    delete_option(ISS_CORE_CAPS_OPTION); wp_set_current_user(0); $write_count = 0;
    iss_core_maybe_run_capability_migration();
    $expect($write_count === 0 && get_option(ISS_CORE_CAPS_OPTION) === false, 'Anonymous visitor cannot repair or mark capability migration');
    $active = get_option('active_plugins');
    update_option('active_plugins', ['iss-content-model/iss-content-model.php']); $write_count = 0;
    iss_core_migrate_domain_plugin_basenames(false);
    $expect($write_count === 0 && get_option('active_plugins') === ['iss-content-model/iss-content-model.php'], 'Legacy bootstrap loads compatibility owners without persisting active plugins');
    $reject_option = 'active_plugins'; $expect_failure(static fn() => iss_core_migrate_domain_plugin_basenames()); $reject_option = '';
    iss_core_migrate_domain_plugin_basenames();
    $expect(in_array('iss-content/iss-content.php', get_option('active_plugins'), true) && !in_array('iss-content-model/iss-content-model.php', get_option('active_plugins'), true), 'Controlled basename migration persists replacements');
    update_option('active_plugins', $active);
    $role_key = wp_roles()->role_key;
    $stored = get_option($role_key); unset($stored['administrator']['capabilities']['iss_access_operations']); update_option($role_key, $stored); wp_roles()->for_site();
    $reject_option = $role_key; $expect_failure(static fn() => iss_core_apply_capability_migration()); $reject_option = '';
    $expect(get_option(ISS_CORE_CAPS_OPTION) === false, 'Failed stored role write cannot advance capability version');
    wp_roles()->for_site(); iss_core_apply_capability_migration();
    $expect(get_option(ISS_CORE_CAPS_OPTION) === ISS_CORE_CAPS_VERSION && get_option($role_key)['administrator']['capabilities']['iss_access_operations'], 'Retry verifies persisted role capabilities');
    $expect(iss_newsletter_dependency_active(), 'Newsletter dependency present in fixture');
    delete_option('iss_newsletter_defaults_version'); update_option('newsletter_form', ['fixture_extra' => 'retained']);
    $reject_option = 'newsletter_form'; $expect_failure(static fn() => iss_newsletter_configure_defaults()); $reject_option = '';
    $expect(get_option('iss_newsletter_defaults_version') === false, 'Incomplete newsletter defaults do not advance version');
    iss_newsletter_configure_defaults();
    $expect(get_option('iss_newsletter_defaults_version') === ISS_NEWSLETTER_DEFAULTS_VERSION && get_option('newsletter_form')['fixture_extra'] === 'retained', 'Setup retry completes and preserves unrelated options');
    $write_count = 0; iss_newsletter_configure_defaults();
    $expect($write_count === 0, 'Completed newsletter setup is write-free');
} catch (Throwable $caught) { $error = $caught; }
finally {
    remove_filter('query', $watch); remove_filter('upload_dir', $no_audit_file);
    if ($created) { $wpdb->query("DROP TEMPORARY TABLE `$table`"); }
    $wpdb->suppress_errors($old_suppress); wp_cache_flush(); $GLOBALS['wp_roles'] = $roles; wp_set_current_user($user);
}
$expect($wpdb->get_results("SELECT * FROM `$table` ORDER BY option_id", ARRAY_A) === $rows, 'All real options and roles unchanged');
if ($error) { throw $error; }
WP_CLI::success("Owner setup gates: $checks checks passed; real settings retained.");
