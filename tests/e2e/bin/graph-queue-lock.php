<?php
/**
 * Run with WP-CLI --exec='define("DISABLE_WP_CRON", true);' eval-file.
 * Two DB connections share a uniquely named disposable options table. Query
 * barriers force competing acquisitions at the write boundary, without sleeps.
 * The drain caller sees a connection-local temporary queue, never real work.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated fixture schemas and intentional SQL failures.
global $wpdb;
$db = $wpdb;
$service = iss_graph_get_service();
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'Disable cron for isolated checks');
$expect(!wp_using_ext_object_cache(), 'Option fixture caches must remain process-local');
$expect((int) $db->get_var('SELECT @@SESSION.in_transaction') === 0 && (int) $db->get_var('SELECT @@SESSION.autocommit') === 1, 'Independent autocommit connection');
$real_options = $db->options;
$option = 'iss_graph_dirty_queue_lock';
$last_run = 'iss_graph_dirty_queue_last_run';
$fixture = $db->prefix . 'iss_test_queue_lock_' . bin2hex(random_bytes(6));
$queue = $service->get_dirty_queue_table_name();
$real_state = static function () use ($db,$real_options,$option,$last_run,$queue): array {
    return [
        'options' => $db->get_results($db->prepare("SELECT * FROM `$real_options` WHERE option_name IN (%s, %s) ORDER BY option_id", $option, $last_run), ARRAY_A),
        'queue' => $db->get_results("CHECKSUM TABLE `$queue`", ARRAY_A),
    ];
};
$before = $real_state();
$peer = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$peer->set_prefix($db->prefix);
$expect((int) $peer->get_var('SELECT CONNECTION_ID()') !== (int) $db->get_var('SELECT CONNECTION_ID()'), 'Contenders use distinct database connections');
$peer->options = $fixture;
$acquire_method = new ReflectionMethod($service, 'acquire_dirty_queue_lock');
$release_method = new ReflectionMethod($service, 'release_dirty_queue_lock');
$acquire = static function (wpdb $connection, int $ttl = 90) use ($service,$acquire_method): ?string {
    global $wpdb;
    $previous = $wpdb;
    $wpdb = $connection;
    try { return $acquire_method->invoke($service, $ttl); }
    finally { $wpdb = $previous; }
};
$release = static function (wpdb $connection, string $lease) use ($service,$release_method): void {
    global $wpdb;
    $previous = $wpdb;
    $wpdb = $connection;
    try { $release_method->invoke($service, $lease); }
    finally { $wpdb = $previous; }
};
$clear_cache = static function () use ($option,$last_run): void {
    foreach ([$option,$last_run,'notoptions','alloptions'] as $key) { wp_cache_delete($key, 'options'); }
};
$read = static fn(): ?string => $db->get_var($db->prepare("SELECT option_value FROM `$fixture` WHERE option_name = %s", $option));
$reset = static function () use ($db,$fixture,$clear_cache): void {
    if ($db->query("DELETE FROM `$fixture`") === false) { throw new RuntimeException('Fixture reset failed'); }
    $clear_cache();
};
$seed = static function (string $value, string $autoload = 'no') use ($db,$fixture,$option,$reset): void {
    $reset();
    if ($db->insert($fixture, ['option_name' => $option, 'option_value' => $value, 'autoload' => $autoload]) !== 1) {
        throw new RuntimeException('Fixture seed failed');
    }
};
$expired = maybe_serialize(['created' => time() - 120, 'expires' => time() - 1]);
$live = maybe_serialize(['created' => time(), 'expires' => time() + 600]);
$failure = static function (callable $call) use ($expect): void {
    $caught = null;
    try { $call(); } catch (RuntimeException $error) { $caught = $error; }
    $expect($caught instanceof RuntimeException, 'SQL failure reaches caller instead of reporting acquisition/contention/success');
};
$created = false;
$queue_created = false;
$filter = null;
$error = null;
$old_suppression = $db->suppress_errors(true);
$peer->suppress_errors(true);
try {
    $expect($db->query("CREATE TABLE `$fixture` LIKE `$real_options`") !== false, 'Create isolated options table using real unique index and collation');
    $created = true;
    $db->options = $fixture;
    $clear_cache();

    $expect(get_option($option, null) === null, 'Prime missing option cache');
    $owner = $acquire($db, 1);
    $expect(is_string($owner) && $read() === $owner, 'First acquisition persists its lease');
    $payload = maybe_unserialize($owner);
    $expect(is_array($payload) && preg_match('/^[a-f0-9-]{36}$/', $payload['token']) === 1, 'Lease includes a unique ownership token');
    $expect($payload['expires'] - $payload['created'] === 60, 'Minimum TTL retained');
    $expect(get_option($option) === $payload, 'Missing option cache is invalidated');
    $expect($db->get_var($db->prepare("SELECT autoload FROM `$fixture` WHERE option_name = %s", $option)) === 'no', 'Lock is not autoloaded');
    $expect($acquire($peer) === null && $read() === $owner, 'Live owner excludes another connection');
    $expect($acquire($db) === null && $read() === $owner, 'Nested acquisition cannot replace the existing owner');
    $release($db, $owner);
    $expect($read() === null && get_option($option, null) === null, 'Correct owner releases and clears option cache');
    $replacement = $acquire($peer, 120);
    $expect(is_string($replacement) && $replacement !== $owner, 'Reacquisition has a different token, including within the same second');
    $expect(maybe_unserialize($replacement)['expires'] - maybe_unserialize($replacement)['created'] === 120, 'Caller TTL retained');
    $release($db, $owner);
    $expect($read() === $replacement, 'Previously released owner cannot remove a new lease');
    $release($peer, $replacement);

    $seed($live);
    $expect($acquire($peer) === null && $read() === $live, 'Active legacy lease without a token is respected');
    $seed($expired, 'yes');
    $expect(get_option($option) === maybe_unserialize($expired), 'Prime legacy autoload cache');
    $replacement = $acquire($peer);
    $expect(is_string($replacement) && get_option($option) === maybe_unserialize($replacement), 'Expired legacy lease is replaced and autoload cache invalidated');
    $release($db, $expired);
    $expect($read() === $replacement, 'Expired legacy owner cannot release replacement');
    $release($peer, $replacement);
    foreach (['', 'malformed', maybe_serialize(['expires' => time()])] as $stale) {
        $seed($stale);
        $replacement = $acquire($db);
        $expect(is_string($replacement) && $read() === $replacement, 'Malformed or boundary-expired lock is recoverable');
        $release($db, $replacement);
    }

    // Poison local option caches: lock arbitration must still use database state.
    $seed($live);
    wp_cache_set($option, maybe_unserialize($expired), 'options');
    $expect($acquire($db) === null && $read() === $live, 'Stale cached expiry cannot bypass a live database lease');
    $reset();
    wp_cache_set($option, maybe_unserialize($live), 'options');
    $replacement = $acquire($db);
    $expect(is_string($replacement), 'Stale cached live lease cannot block an absent database lock');
    $release($db, $replacement);

    foreach (['absent','expired','malformed'] as $race) {
        if ($race === 'absent') { $reset(); } else { $seed($race === 'expired' ? $expired : 'malformed'); }
        $peer_owner = null;
        $hits = 0;
        $prefix = $race === 'absent' ? "INSERT IGNORE INTO $fixture " : "UPDATE $fixture ";
        $filter = static function (string $sql) use ($prefix,$peer,$acquire,&$peer_owner,&$hits): string {
            if ($hits === 0 && str_starts_with($sql, $prefix)) {
                ++$hits;
                // This peer commits after the first contender's read and before
                // its conditional write: the losing contender must not overwrite it.
                $peer_owner = $acquire($peer);
            }
            return $sql;
        };
        add_filter('query', $filter);
        $loser = $acquire($db);
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1 && is_string($peer_owner), "$race race reaches a committed peer acquisition");
        $expect($loser === null && $read() === $peer_owner, "$race race has exactly one owner");
        $release($peer, $peer_owner);
    }

    // Force a takeover immediately before the old owner's DELETE executes.
    $owner = $acquire($db);
    $peer_owner = null;
    $hits = 0;
    $filter = static function (string $sql) use ($fixture,$seed,$expired,$peer,$acquire,&$peer_owner,&$hits): string {
        if ($hits === 0 && str_starts_with($sql, "DELETE FROM $fixture ")) {
            ++$hits;
            $seed($expired);
            $peer_owner = $acquire($peer);
        }
        return $sql;
    };
    add_filter('query', $filter);
    $release($db, $owner);
    remove_filter('query', $filter);
    $filter = null;
    $expect($hits === 1 && is_string($peer_owner) && $read() === $peer_owner, 'Takeover at release boundary preserves replacement owner');
    $release($db, str_replace('token', 'TOKEN', $peer_owner));
    $expect($read() === $peer_owner, 'Case-insensitive table collation cannot grant ownership');
    $release($db, $peer_owner . ' ');
    $expect($read() === $peer_owner, 'Trailing-space comparison cannot grant ownership');
    $release($peer, $peer_owner);

    foreach (['read','insert','takeover','release'] as $stage) {
        $reset();
        if ($stage === 'takeover') { $seed($expired); }
        $owner = $stage === 'release' ? $acquire($db) : null;
        $before_failure = $read();
        $prefix = match ($stage) {
            'read' => "SELECT option_value FROM $fixture ",
            'insert' => "INSERT IGNORE INTO $fixture ",
            'takeover' => "UPDATE $fixture ",
            'release' => "DELETE FROM $fixture ",
        };
        $hits = 0;
        $filter = static function (string $sql) use ($prefix,&$hits): string {
            if ($hits === 0 && str_starts_with($sql, $prefix)) { ++$hits; return 'SELECT iss_graph_queue_lock_injected_failure'; }
            return $sql;
        };
        add_filter('query', $filter);
        $failure(static function () use ($stage,$db,$acquire,$release,$owner): void {
            if ($stage === 'release') { $release($db, $owner); } else { $acquire($db); }
        });
        remove_filter('query', $filter);
        $filter = null;
        $expect($hits === 1 && $read() === $before_failure, "$stage SQL failure leaves existing lock unchanged");
    }

    $reset();
    $queue_schema = $db->get_row("SHOW CREATE TABLE `$queue`", ARRAY_N);
    $expect(is_array($queue_schema) && str_starts_with($queue_schema[1], 'CREATE TABLE '), 'Read queue schema before shadowing it');
    $expect($db->query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $queue_schema[1], 1)) !== false, 'Shadow queue prevents draining real work');
    $queue_created = true;
    $stats = $service->drain_dirty_queue(['max_runtime' => 1]);
    $expect(!$stats['locked'] && $stats['processed'] === 0 && $stats['selected'] === 0, 'Empty drain completes with the new lease contract');
    $expect($read() === null && get_option($last_run) === $stats, 'Normal drain releases its lease and records stats');
    $expect($db->insert($queue, ['target_post_id' => 2147483000, 'reason' => 'lock-fixture', 'last_error' => '', 'created_at' => current_time('mysql', true), 'updated_at' => current_time('mysql', true)]) === 1, 'Add temporary caller fixture');
    $queue_before = $db->get_results("SELECT * FROM `$queue`", ARRAY_A);
    $owner = $acquire($peer);
    $last_before = get_option($last_run);
    $stats = $service->drain_dirty_queue();
    $expect($stats['locked'] && $stats['processed'] === 0 && $read() === $owner, 'Contended drain returns without releasing peer ownership');
    $expect($db->get_results("SELECT * FROM `$queue`", ARRAY_A) === $queue_before && get_option($last_run) === $last_before, 'Contended drain leaves queue rows and last-run stats unchanged');
    $release($peer, $owner);
    $stats = $service->drain_dirty_queue(['dry_run' => true]);
    $expect($stats['selected'] === 1 && $stats['processed'] === 0 && $read() === null, 'Dry run keeps its read-only contract');
    $expect($db->get_results("SELECT * FROM `$queue`", ARRAY_A) === $queue_before && get_option($last_run) === $last_before, 'Dry run preserves queue and stats');

    // An exception before reconciliation must still release this drain's lease.
    $hits = 0;
    $filter = static function (string $sql) use ($queue,&$hits): string {
        if (str_starts_with($sql, "UPDATE `$queue`")) { ++$hits; throw new RuntimeException('Injected pre-reconcile failure'); }
        return $sql;
    };
    add_filter('query', $filter);
    $failure(static fn(): array => $service->drain_dirty_queue());
    remove_filter('query', $filter);
    $filter = null;
    $expect($hits === 1 && $read() === null, 'Drain finally releases ownership on an exception');
    $expect($db->get_results("SELECT * FROM `$queue`", ARRAY_A) === $queue_before, 'Exception fixture never reaches reconciliation or changes queue');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    if ($filter !== null) { remove_filter('query', $filter); }
    $wpdb = $db;
    $db->options = $real_options;
    if ($queue_created && $db->query("DROP TEMPORARY TABLE `$queue`") === false) { $error = new RuntimeException('Temporary queue cleanup failed'); }
    if ($created && $db->query("DROP TABLE `$fixture`") === false) { $error = new RuntimeException('Options fixture cleanup failed: ' . $fixture); }
    $clear_cache();
    $db->suppress_errors($old_suppression);
    $peer->close();
}
if ($error !== null) { WP_CLI::error($error->getMessage()); }
$expect($real_state() === $before, 'Real queue and lock/last-run options are unchanged');
$expect($db->get_var($db->prepare('SHOW TABLES LIKE %s', $db->esc_like($fixture))) === null, 'No shared fixture table remains');
WP_CLI::success("Graph queue lock: {$checks} checks passed; real queue/options unchanged; fixtures removed.");
