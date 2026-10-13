<?php
/** Run with WP-CLI and DISABLE_WP_CRON: every write targets connection-local schema copies. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Temporary fixtures and deliberate SQL faults.
global $wpdb;
$epochs = iss_register_get_epoch_service();
$states = iss_register_get_place_state_service();
$tables = [$wpdb->posts => 'ID', $wpdb->postmeta => 'meta_id', $epochs->get_table_name() => 'id', $states->get_table_name() => 'id'];
$read = static fn(string $table, string $order): array => $wpdb->get_results("SELECT * FROM `$table` ORDER BY `$order`", ARRAY_A);
$snapshot = static function () use ($tables, $read): array {
    $result = [];
    foreach ($tables as $table => $order) { $result[$table] = $read($table, $order); }
    return $result;
};
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$tx = static fn(): int => (int) $wpdb->get_var('SELECT @@SESSION.in_transaction');
$expect($tx() === 0 && (int) $wpdb->get_var('SELECT @@SESSION.autocommit') === 1 && !wp_using_ext_object_cache(), 'Independent connection and process-local caches');
$id = 2147483000;
$revision_id = $id + 1;
$draft_id = $id + 2;
$expect(get_post($id) === null && get_post($revision_id) === null && get_post($draft_id) === null, 'Fixture IDs do not exist');
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$expect(count($admins) === 1, 'Administrator available');
$old_user = get_current_user_id();
wp_set_current_user((int) $admins[0]);
$old_post = $_POST;
$real_hash = hash('sha256', serialize($snapshot()));
$real_options = $read($wpdb->options, 'option_id');
$schemas = [];
foreach (array_merge(array_keys($tables), [$wpdb->options]) as $table) {
    $expect($wpdb->get_var($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table)) === 'InnoDB', 'Real schema supports rollback');
    $schema = $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N);
    $expect(is_array($schema) && str_starts_with($schema[1], 'CREATE TABLE '), 'Read schema');
    $schemas[$table] = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1);
}
$doc = iss_editorial_get_empty_document('place');
$doc['sections'] = [['type' => 'epoche', 'title' => 'Original phase', 'body' => '<p>Original history</p>', 'era_key' => 'nach-1990', 'function_key' => 'culture', 'start_year' => 2000, 'is_current' => true]];
$next = $doc;
$next['sections'][0]['title'] = 'Changed phase';
$next['sections'][0]['body'] = '<p>Changed history</p>';
$key = iss_editorial_get_document_meta_key('place');
$enabled_key = iss_editorial_get_enabled_meta_key('place');
$skin_key = iss_editorial_get_skin_meta_key('place');
$created = [];
$filter = null;
$error = null;
$old_suppression = $wpdb->suppress_errors(true);
$die = static fn() => static function ($message): void { throw new RuntimeException('Rejected: ' . $message); };
add_filter('wp_die_handler', $die);
$notifications = 0;
$notified = static function () use (&$notifications): void { ++$notifications; };
add_action('iss_editorial_document_saved', $notified);
try {
    foreach ($schemas as $table => $schema) {
        $expect($wpdb->query($schema) !== false, 'Create temporary table');
        $created[] = $table;
    }
    foreach ($real_options as $row) { if ($wpdb->insert($wpdb->options, $row) !== 1) { throw new RuntimeException('Copy option fixture'); } }
    foreach ([$id => 'register_place', $revision_id => 'revision', $draft_id => 'revision'] as $post_id => $type) {
        $expect($wpdb->insert($wpdb->posts, ['ID' => $post_id, 'post_type' => $type, 'post_status' => $type === 'revision' ? 'inherit' : 'publish', 'post_parent' => $type === 'revision' ? $id : 0, 'post_author' => $admins[0], 'post_title' => 'Atomic Place fixture', 'post_name' => $post_id === $draft_id ? $id . '-autosave-v1' : 'atomic-place-' . $post_id, 'post_date' => '2026-01-01 00:00:00', 'post_modified' => '2026-01-01 00:00:00']) === 1, 'Seed shadow post');
        clean_post_cache($post_id);
    }
    $expect(iss_editorial_save_document($id, 'place', $doc, false, true), 'First activation writes JSON and projections together');
    $expect(count($epochs->get_epochs_for_place($id)) === 1 && count($states->get_states_for_place($id)) === 2, 'Published Place has one epoch and historical/current states');
    $expect(iss_editorial_save_document($id, 'place', $doc, false, true), 'Unchanged metadata is a successful save');
    foreach ([$revision_id, $draft_id] as $copy_id) {
        add_metadata('post', $copy_id, $key, wp_slash(iss_editorial_encode_document(iss_editorial_validate_document($next, 'place'))));
        add_metadata('post', $copy_id, $enabled_key, '1');
        add_metadata('post', $copy_id, $skin_key, (string) ($next['skin'] ?? ''));
    }
    add_metadata('post', $draft_id, '_iss_editorial_draft_base', 'recovery-base');
    // Seed a milestone through its owning API and preserve it during every replacement.
    $milestone = ['key' => 'fixture-milestone', 'title' => 'Preserved milestone', 'summary' => 'Fixture event', 'event_type' => 'opening', 'source_url' => 'https://example.com/fixture', 'source_revision' => str_repeat('a', 40), 'source_summary' => 'Fixture source', 'observed_at' => '2026-01-01', 'date_precision' => 'year', 'event_date' => '2020'];
    $expect(!is_wp_error($epochs->append_milestone($id, $milestone)), 'Seed independent chronology milestone');
    $baseline = $snapshot();
    $baseline_doc = get_metadata_raw('post', $id, $key, true);
    $baseline_draft = iss_editorial_get_draft($id, 'place');
    $expect($baseline_draft['id'] === $draft_id, 'Recovery fixture is readable');
    $reset = static function () use ($wpdb, $tables, $baseline, $id, $revision_id, $draft_id): void {
        foreach ($tables as $table => $order) {
            $wpdb->query("DELETE FROM `$table`");
            foreach ($baseline[$table] as $row) { if ($wpdb->insert($table, $row) !== 1) { throw new RuntimeException('Reset fixture'); } }
        }
        foreach ([$id, $revision_id, $draft_id] as $post_id) { clean_post_cache($post_id); }
    };
    $fault = static function (callable $match, callable $call, string $label, bool $php = false, bool $non_transactional = false) use (&$filter, $expect, $wpdb, $snapshot, $tx, &$notifications, $id, $key, $baseline_doc, $baseline_draft): void {
        $before = $snapshot();
        $sent = $notifications;
        $hits = 0;
        $filter = static function (string $sql) use ($match, &$hits, $php, $non_transactional): string {
            if ($hits > 0 || !$match($sql)) { return $sql; }
            ++$hits;
            if ($php) { throw new RuntimeException('Injected PHP failure'); }
            if ($non_transactional) { return str_replace('ENGINE AS engine', "'MyISAM' AS engine", $sql); }
            return 'SELECT iss_place_test_injected_failure';
        };
        add_filter('query', $filter);
        $result = $call();
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1, $label . ': fault reached');
        $expect($result === false || is_wp_error($result), $label . ': failure reaches caller');
        $expect($snapshot() === $before, $label . ': all metadata, epochs, milestones and states rolled back');
        $expect($notifications === $sent, $label . ': no successful-save notification');
        $expect(get_metadata_raw('post', $id, $key, true) === $baseline_doc && iss_editorial_get_draft($id, 'place') === $baseline_draft, $label . ': caches and recovery preserved');
    };
    $save = static fn() => iss_editorial_save_document($id, 'place', $next, false, true);
    $et = $epochs->get_table_name();
    $st = $states->get_table_name();
    $matches = [
        'transaction state' => static fn($sql) => str_contains($sql, 'information_schema.TABLES'),
        'begin' => static fn($sql) => $sql === 'START TRANSACTION',
        'source lock' => static fn($sql) => str_contains($sql, 'FOR UPDATE'),
        'source metadata read' => static fn($sql) => str_contains($sql, "FROM $wpdb->postmeta") && str_contains($sql, 'meta_id ASC'),
        'canonical JSON' => static fn($sql) => str_starts_with($sql, "UPDATE `$wpdb->postmeta`") && str_contains($sql, $key),
        'epoch read' => static fn($sql) => str_contains($sql, "FROM $et") && str_contains($sql, 'milestone_key IS NULL'),
        'epoch insert' => static fn($sql) => str_starts_with($sql, "INSERT INTO `$et`"),
        'epoch delete' => static fn($sql) => str_starts_with($sql, "DELETE FROM `$et`"),
        'latest snapshot' => static fn($sql) => str_starts_with($sql, "UPDATE `$wpdb->postmeta`") && str_contains($sql, "'" . ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY . "'"),
        'snapshot history' => static fn($sql) => str_starts_with($sql, "UPDATE `$wpdb->postmeta`") && str_contains($sql, "'" . ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY . "'"),
        'state delete' => static fn($sql) => str_starts_with($sql, "DELETE FROM `$st`"),
        'state insert' => static fn($sql) => str_starts_with($sql, "INSERT INTO `$st`"),
        'current state insert' => static fn($sql) => str_starts_with($sql, "INSERT INTO `$st`") && str_contains($sql, "'current'"),
        'commit' => static fn($sql) => $sql === 'COMMIT',
        'nested release' => static fn($sql) => str_starts_with($sql, 'RELEASE SAVEPOINT'),
    ];
    foreach ($matches as $label => $match) { $fault($match, $save, $label); $expect($tx() === 0, $label . ': no open transaction'); }
    $fault($matches['transaction state'], $save, 'Nontransactional schema rejected', false, true);
    $epoch_reads = 0;
    $fault(static function ($sql) use ($et, &$epoch_reads) { return str_contains($sql, "FROM $et") && str_contains($sql, 'milestone_key IS NULL') && ++$epoch_reads === 2; }, $save, 'Epoch readback');
    $fault($matches['current state insert'], $save, 'Late PHP exception', true);
    $fault(static fn($sql) => str_starts_with($sql, "UPDATE `$et`"), static function () use ($epochs, $id) { $rows = $epochs->get_epochs_for_place($id); $rows[0]['phase_name'] = 'Changed directly'; return $epochs->save_epochs_for_place($id, $rows); }, 'Legacy epoch update');
    $fault($matches['current state insert'], static fn() => $states->sync_place_state_for_post($id), 'Standalone state replacement');
    // Native handler must surface failure before deleting the existing recovery draft.
    $_POST = wp_slash(['iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => ['place' => ['document' => iss_editorial_encode_document($next), 'enabled' => '1']]]);
    $native = static function () use ($id, $expect) {
        try { iss_editorial_save_meta_box($id); }
        catch (RuntimeException $error) { $expect(str_starts_with($error->getMessage(), 'Rejected:'), 'Native save reports an error'); return false; }
        return true;
    };
    $fault($matches['current state insert'], $native, 'Native error and recovery');
    $_POST = [];
    // Exercise the real revision metadata hook, including the native copy callback.
    $restore = static function () use ($id, $revision_id, $expect) {
        try { do_action('wp_restore_post_revision', $id, $revision_id); }
        catch (RuntimeException $error) { $expect(str_starts_with($error->getMessage(), 'Rejected:'), 'Revision restore reports an error'); return false; }
        return true;
    };
    $fault($matches['current state insert'], $restore, 'Revision projection');
    $fault(static fn($sql) => str_starts_with($sql, "INSERT INTO `$wpdb->postmeta`") && str_contains($sql, $key), $restore, 'Revision metadata copy');
    $fault(static fn($sql) => str_contains($sql, "FROM $wpdb->postmeta") && str_contains($sql, (string) $revision_id) && str_contains($sql, 'meta_id ASC'), $restore, 'Revision metadata read');
    $fault(static fn($sql) => str_starts_with($sql, "DELETE FROM $wpdb->postmeta"), $restore, 'Revision metadata delete');
    $expect($restore(), 'Revision retry succeeds');
    $expect($epochs->get_epochs_for_place($id)[0]['phase_name'] === 'Changed phase' && iss_editorial_get_document($id, 'place')['sections'][0]['title'] === 'Changed phase', 'Restored JSON and epoch agree');
    $reset();
    foreach ([false, true] as $reject) {
        $wpdb->query('START TRANSACTION');
        $wpdb->update($wpdb->posts, ['post_title' => 'Caller pending'], ['ID' => $id]);
        if ($reject) { $fault($matches['current state insert'], $save, 'Caller transaction failure'); }
        else { $expect($save(), 'Save inside caller transaction succeeds'); }
        $expect($tx() === 1, 'Caller keeps transaction ownership');
        $wpdb->query('ROLLBACK');
        clean_post_cache($id);
        $expect($snapshot() === $baseline, 'Caller rollback restores the entire boundary');
    }
    $wpdb->query('SET autocommit = 0');
    $expect($save() && $tx() === 1, 'Disabled autocommit retains caller ownership');
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    clean_post_cache($id);
    $expect($snapshot() === $baseline, 'Autocommit-off rollback preserves baseline');
    // First activation and skin writes participate too.
    update_post_meta($id, $enabled_key, '0');
    update_post_meta($id, $skin_key, 'previous-skin');
    $fault(static fn($sql) => str_starts_with($sql, "UPDATE `$wpdb->postmeta`") && str_contains($sql, $skin_key), $save, 'Skin metadata');
    $fault(static fn($sql) => str_starts_with($sql, "UPDATE `$wpdb->postmeta`") && str_contains($sql, $enabled_key), $save, 'Activation metadata');
    $fault($matches['current state insert'], $save, 'First activation projection');
    $expect(!iss_editorial_document_is_enabled($id, 'place'), 'Failed activation remains disabled');
    $expect($save() && iss_editorial_document_is_enabled($id, 'place'), 'Activation retry succeeds');
    $expect($epochs->get_milestones_for_place($id) === [$milestone], 'Successful replacement preserves milestone');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter) { remove_filter('query', $filter); }
    $wpdb->query('ROLLBACK');
    $wpdb->query('SET autocommit = 1');
    foreach (array_reverse($created) as $table) { $wpdb->query("DROP TEMPORARY TABLE IF EXISTS `$table`"); }
    foreach ([$id, $revision_id, $draft_id] as $post_id) { clean_post_cache($post_id); }
    $wpdb->suppress_errors($old_suppression);
    remove_filter('wp_die_handler', $die);
    remove_action('iss_editorial_document_saved', $notified);
    $_POST = $old_post;
    wp_set_current_user($old_user);
}
$expect(hash('sha256', serialize($snapshot())) === $real_hash, 'Real posts, metadata, epochs and states unchanged');
$expect($read($wpdb->options, 'option_id') === $real_options, 'Real options and transients unchanged');
if ($error) { throw $error; }
WP_CLI::success('Place editorial atomic: ' . $checks . ' checks passed; temporary fixtures removed and real tables unchanged.');
