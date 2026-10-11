<?php
/**
 * WP-CLI --exec='define("DISABLE_WP_CRON", true);' eval-file FILE.
 * Disposable tables with real schemas, two connections and deterministic query
 * barriers exercise the queue. Reconciliation sees only nonexistent fixture IDs.
 */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated fixture tables and deliberate SQL failures.
global $wpdb;
$db = $wpdb;
$service = iss_graph_get_service();
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and fixture caches process-local');
$real_prefix = $db->prefix;
$real_options = $db->options;
$real_queue = $service->get_dirty_queue_table_name();
$prefix = $real_prefix . 'iss_test_queue_' . bin2hex(random_bytes(5)) . '_';
$queue = $prefix . 'iss_graph_dirty_queue';
$options = $prefix . 'options';
$lock_option = 'iss_graph_dirty_queue_lock';
$last_option = 'iss_graph_dirty_queue_last_run';
$base = 2147482000;
$expect((int) $db->get_var($db->prepare("SELECT COUNT(*) FROM {$db->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 30)) === 0, 'Fixture post IDs do not exist');
$real_state = static fn(): array => [
    'queue' => $db->get_results("CHECKSUM TABLE `$real_queue`", ARRAY_A),
    'options' => $db->get_results($db->prepare("SELECT * FROM `$real_options` WHERE option_name IN (%s, %s) ORDER BY option_id", $lock_option, $last_option), ARRAY_A),
];
$before = $real_state();
$peer = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST);
$peer->set_prefix($real_prefix);
$expect($db->get_var('SELECT CONNECTION_ID()') !== $peer->get_var('SELECT CONNECTION_ID()'), 'Distinct MariaDB connections');
$peer->prefix = $prefix;
$peer->options = $options;
$peer->query('SET SESSION innodb_lock_wait_timeout = 1');
$as_peer = static function (callable $call) use ($db,$peer) {
    global $wpdb;
    $wpdb = $peer;
    try { return $call(); } finally { $wpdb = $db; }
};
$acquire = new ReflectionMethod($service, 'acquire_dirty_queue_lock');
$release = new ReflectionMethod($service, 'release_dirty_queue_lock');
$guard = new ReflectionMethod($service, 'with_dirty_queue_lock');
$clear_cache = static function () use ($lock_option,$last_option,$base): void {
    foreach ([$lock_option,$last_option,'alloptions','notoptions'] as $key) { wp_cache_delete($key, 'options'); }
    for ($id = $base; $id <= $base + 30; ++$id) { wp_cache_delete($id, 'posts'); }
};
$row = static fn(int $post): ?array => $db->get_row($db->prepare("SELECT * FROM `$queue` WHERE target_post_id = %d", $post), ARRAY_A);
$lock = static fn(): ?string => $db->get_var($db->prepare("SELECT option_value FROM `$options` WHERE option_name = %s", $lock_option));
$reset = static function () use ($db,$queue,$options,$clear_cache): void {
    if ($db->query("DELETE FROM `$queue`") === false || $db->query("DELETE FROM `$options`") === false) { throw new RuntimeException('Fixture reset failed'); }
    $clear_cache();
};
$seed = static function (int $post, array $values = []) use ($db,$queue): void {
    if ($db->insert($queue, $values + ['target_post_id' => $post, 'reason' => 'fixture', 'last_error' => '', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) !== 1) {
        throw new RuntimeException('Could not seed queue fixture');
    }
};
$with_filter = static function (callable $filter, callable $call) {
    add_filter('query', $filter);
    try { return $call(); } finally { remove_filter('query', $filter); }
};
$failure = static function (callable $call) use ($expect): void {
    $error = null;
    try { $call(); } catch (RuntimeException $caught) { $error = $caught; }
    $expect($error instanceof RuntimeException, 'Queue SQL/setup failure is surfaced');
};
$post_read = static fn(string $sql, int $id): bool => str_contains($sql, "FROM {$db->posts} WHERE ID = $id LIMIT 1");
$created = [];
$error = null;
$old_suppression = $db->suppress_errors(true);
$peer->suppress_errors(true);
try {
    foreach ([$queue => $real_queue, $options => $real_options] as $fixture => $original) {
        $expect($db->query("CREATE TABLE `$fixture` LIKE `$original`") !== false, 'Create disposable table from real schema');
        $created[] = $fixture;
    }
    $db->prefix = $prefix;
    $db->options = $options;
    $clear_cache();

    $expect(!$service->mark_post_dirty(0), 'Invalid source ID rejected');
    // The second producer inserts between the first producer's start and write.
    $hits = 0;
    $filter = static function (string $sql) use ($queue,$service,$as_peer,$base,&$hits): string {
        if ($hits === 0 && str_starts_with($sql, "INSERT INTO $queue")) {
            ++$hits;
            if (!$as_peer(static fn(): bool => $service->mark_post_dirty($base, 'peer', 'producer_b'))) { throw new RuntimeException('Peer producer failed'); }
        }
        return $sql;
    };
    $expect($with_filter($filter, static fn(): bool => $service->mark_post_dirty($base, 'main', 'producer_a')), 'Competing producer succeeds');
    $expect($hits === 1 && (int) $db->get_var("SELECT COUNT(*) FROM `$queue`") === 1, 'Competing producers coalesce to one row');
    $expect($row($base)['reason'] === 'main' && $row($base)['source_system'] === 'producer_a', 'Latest source signal retained');
    $original = $row($base);
    $expect($service->mark_post_dirty($base, 'main', 'producer_a'), 'Identical repeated dirty signal remains successful');
    $expect($row($base)['id'] === $original['id'] && $row($base)['created_at'] === $original['created_at'], 'Coalescing retains identity and creation time');
    $original = $row($base);

    // A blocked drain must not select a stale batch before acquiring its lease.
    $owner = $as_peer(static fn(): string => $acquire->invoke($service, 90));
    $selects = 0;
    $filter = static function (string $sql) use ($queue,&$selects): string {
        if (str_starts_with($sql, "SELECT * FROM $queue")) { ++$selects; }
        return $sql;
    };
    $stats = $with_filter($filter, static fn(): array => $service->drain_dirty_queue());
    $expect($stats['locked'] && $selects === 0 && $stats['selected'] === 0, 'Contended drain reads no batch');
    $expect($row($base) === $original && $lock() === $owner, 'Contended drain preserves peer lock and queue');
    $as_peer(static fn() => $release->invoke($service, $owner));

    $seen_lease = false;
    $filter = static function (string $sql) use ($queue,$lock,&$seen_lease): string {
        if (str_starts_with($sql, "SELECT * FROM $queue")) { $seen_lease = $lock() !== null; }
        return $sql;
    };
    $stats = $with_filter($filter, static fn(): array => $service->drain_dirty_queue());
    $expect($seen_lease && $stats['processed'] === 1 && !$stats['lease_lost'], 'Successful drain selects after locking');
    $expect($row($base)['status'] === 'clean' && (int) $row($base)['attempts'] === 1 && $row($base)['locked_at'] === null, 'Successful claim is acknowledged');
    $expect($row($base)['last_processed_at'] !== null && $lock() === null && get_option($last_option) === $stats, 'Completion and run stats persist; lease released');

    // A source edit during both successful and failed reconciliation stays dirty.
    foreach ([false,true] as $throw) {
        $reset();
        $seed($base);
        $hits = 0;
        $changed = null;
        $filter = static function (string $sql) use ($db,$peer,$queue,$base,$post_read,$service,$as_peer,$row,$expect,$throw,&$hits,&$changed): string {
            if ($hits === 0 && $post_read($sql, $base)) {
                ++$hits;
                $expect((int) $db->get_var('SELECT @@SESSION.in_transaction') === 0, 'Reconciliation runs outside a queue transaction');
                $claimed = $row($base);
                $expect($claimed['status'] === 'processing' && (int) $claimed['attempts'] === 1, 'Claim committed before reading source');
                $expect($as_peer(static fn(): bool => $service->mark_post_dirty($base, 'new source edit', 'editor')), 'Concurrent edit is not blocked by queue locks');
                $peer->update($queue, ['updated_at' => $claimed['updated_at']], ['target_post_id' => $base]);
                $changed = $row($base);
                if ($throw) { throw new RuntimeException('Reconciliation failed after edit'); }
            }
            return $sql;
        };
        $stats = $with_filter($filter, static fn(): array => $service->drain_dirty_queue());
        $expect($hits === 1 && $stats['superseded'] === 1 && $stats['processed'] === 0 && $stats['failed'] === 0, 'New dirty work supersedes the old result');
        $expect($row($base) === $changed && $changed['status'] === 'dirty' && (int) $changed['attempts'] === 0, 'Same-second edit and fresh retry budget preserved byte-for-byte');
        wp_cache_delete($base, 'posts');
        $stats = $service->drain_dirty_queue();
        $expect($stats['processed'] === 1 && $row($base)['status'] === 'clean', 'Later run processes preserved edit');
    }

    // Failed attempts, including crashes, are bounded; a real new signal reopens.
    $reset();
    $seed($base);
    for ($attempt = 1; $attempt <= 3; ++$attempt) {
        wp_cache_delete($base, 'posts');
        $filter = static function (string $sql) use ($base,$post_read): string {
            if ($post_read($sql, $base)) { throw new RuntimeException('Fixture reconciliation failure'); }
            return $sql;
        };
        $stats = $with_filter($filter, static fn(): array => $service->drain_dirty_queue(['max_attempts' => 3]));
        $expect($stats['failed'] === 1 && (int) $row($base)['attempts'] === $attempt, 'One retry consumed per selected row per run');
        $expect($row($base)['status'] === ($attempt === 3 ? 'dead' : 'dirty'), 'Retry limit determines dirty/dead state');
    }
    $expect($service->drain_dirty_queue()['selected'] === 0, 'Dead work is not retried automatically');
    $processed_at = $row($base)['last_processed_at'];
    $expect($service->mark_post_dirty($base, 'corrected source'), 'New source signal reopens dead work');
    $expect($row($base)['status'] === 'dirty' && (int) $row($base)['attempts'] === 0 && $row($base)['last_processed_at'] === $processed_at, 'Reopen clears retry/error/claim state without erasing history');
    wp_cache_delete($base, 'posts');
    $expect($service->drain_dirty_queue()['processed'] === 1, 'Corrected source gets a fresh attempt');

    $reset();
    $seed($base, ['status' => 'processing', 'attempts' => 1, 'locked_at' => current_time('mysql', true)]);
    $seed($base + 1, ['status' => 'processing', 'attempts' => 3, 'locked_at' => null]);
    $seed($base + 2, ['status' => 'dirty', 'attempts' => 8]);
    $stats = $service->drain_dirty_queue(['batch_size' => 2]);
    $expect($stats['recovered'] === 2 && $stats['processed'] === 1 && $stats['dead'] === 1, 'New lease recovers abandoned processing and retires exhausted crash attempts');
    $expect((int) $row($base)['attempts'] === 2 && (int) $row($base + 1)['attempts'] === 3, 'Recovery consumes retry only when reconciliation will run');
    $expect($row($base + 2)['status'] === 'dirty', 'Recovery and normal work share the batch limit');
    $stats = $service->drain_dirty_queue();
    $expect($stats['dead'] === 1 && $stats['processed'] === 0 && (int) $row($base + 2)['attempts'] === 8, 'Already exhausted dirty row does not receive another attempt');

    // Expired ownership cannot acknowledge or touch rows claimed by a successor.
    $reset();
    $seed($base);
    $seed($base + 1);
    $successor = null;
    $replacement_row = null;
    $hits = 0;
    $filter = static function (string $sql) use ($peer,$options,$queue,$lock_option,$base,$post_read,$service,$as_peer,$acquire,$row,&$hits,&$successor,&$replacement_row): string {
        if ($hits === 0 && $post_read($sql, $base)) {
            ++$hits;
            $peer->update($options, ['option_value' => maybe_serialize(['expires' => time() - 1])], ['option_name' => $lock_option]);
            $successor = $as_peer(static fn(): string => $acquire->invoke($service, 90));
            $as_peer(static fn(): bool => $service->mark_post_dirty($base, 'replacement source'));
            // Deliberately reuse the same second and attempt count (ABA). The
            // global lease, not timestamps or attempt equality, must fence it.
            $peer->update($queue, ['status' => 'processing', 'attempts' => 1, 'locked_at' => current_time('mysql', true)], ['target_post_id' => $base]);
            $replacement_row = $row($base);
        }
        return $sql;
    };
    $stats = $with_filter($filter, static fn(): array => $service->drain_dirty_queue());
    $expect($hits === 1 && $stats['lease_lost'] && $stats['processed'] === 0, 'Old worker stops after losing lease');
    $expect($row($base) === $replacement_row && $row($base + 1)['status'] === 'dirty', 'Old worker neither acknowledges successor claim nor starts another item');
    $expect($lock() === $successor && get_option($last_option, null) === null, 'Old worker cannot release successor or publish stale run stats');
    $as_peer(static fn() => $release->invoke($service, $successor));
    $expired = maybe_serialize(['token' => wp_generate_uuid4(), 'expires' => time() - 1]);
    $db->insert($options, ['option_name' => $lock_option, 'option_value' => $expired, 'autoload' => 'no']);
    $called = false;
    $owned = $guard->invoke($service, $expired, static function () use (&$called): void { $called = true; });
    $expect(!$owned && !$called, 'Expired lease is rejected even without a successor');

    // Each SQL failure leaves either untouched work or a recoverable processing row.
    foreach (['select','engine','begin','ownership','claim_read','claim_write','claim_commit','finish_write','finish_commit','stats'] as $stage) {
        $reset();
        $seed($base);
        $before_failure = $row($base);
        $hits = 0;
        $commits = 0;
        $filter = static function (string $sql) use ($stage,$queue,$options,&$hits,&$commits): string {
            if ($sql === 'COMMIT') { ++$commits; }
            $match = match ($stage) {
                'select' => str_starts_with($sql, "SELECT * FROM $queue") && str_contains($sql, 'WHERE status IN'),
                'engine' => str_contains($sql, 'ENGINE AS engine'),
                'begin' => $sql === 'START TRANSACTION',
                'ownership' => str_starts_with($sql, "SELECT option_value FROM $options") && str_contains($sql, 'FOR UPDATE'),
                'claim_read' => str_starts_with($sql, "SELECT * FROM $queue") && str_contains($sql, 'FOR UPDATE'),
                'claim_write' => str_starts_with($sql, "UPDATE `$queue`") && str_contains($sql, "SET `status` = 'processing'"),
                'claim_commit' => $sql === 'COMMIT' && $commits === 1,
                'finish_write' => str_starts_with($sql, "UPDATE `$queue`") && str_contains($sql, "SET `status` = 'clean'"),
                'finish_commit' => $sql === 'COMMIT' && $commits === 2,
                'stats' => str_starts_with($sql, "INSERT INTO $options") && str_contains($sql, 'iss_graph_dirty_queue_last_run'),
            };
            if ($hits === 0 && $match) { ++$hits; return 'SELECT iss_graph_queue_processing_injected_failure'; }
            return $sql;
        };
        $failure(static fn() => $with_filter($filter, static fn(): array => $service->drain_dirty_queue()));
        $expect($hits === 1 && $lock() === null && (int) $db->get_var('SELECT @@SESSION.in_transaction') === 0, "$stage failure releases lease and rolls back short transaction");
        if (in_array($stage, ['finish_write','finish_commit'], true)) {
            $expect($row($base)['status'] === 'processing' && (int) $row($base)['attempts'] === 1, 'Failed acknowledgement remains recoverable');
            wp_cache_delete($base, 'posts');
            $expect($service->drain_dirty_queue()['recovered'] === 1 && $row($base)['status'] === 'clean', 'Next run recovers interrupted acknowledgement');
        } elseif ($stage === 'stats') {
            $expect($row($base)['status'] === 'clean' && get_option($last_option, null) === null, 'Stats failure does not undo completed work or publish false success');
        } else {
            $expect($row($base) === $before_failure, "$stage failure retains original queue row");
        }
    }

    $reset();
    $seed($base);
    $seed($base + 1, ['status' => 'processing', 'attempts' => 1]);
    $seed($base + 2, ['attempts' => 3]);
    $snapshot = $db->get_results("SELECT * FROM `$queue` ORDER BY id", ARRAY_A);
    $stats = $service->drain_dirty_queue(['dry_run' => true]);
    $expect($stats['selected'] === 2 && $stats['items'][0]['would_reconcile'] && !$stats['items'][1]['would_reconcile'], 'Dry-run reports pending dirty work and exhausted attempt budget');
    $expect($db->get_results("SELECT * FROM `$queue` ORDER BY id", ARRAY_A) === $snapshot && $lock() === null && get_option($last_option, null) === null, 'Dry run neither claims nor recovers nor records a run');
    $db->query('START TRANSACTION');
    $failure(static fn(): array => $service->drain_dirty_queue());
    $expect((int) $db->get_var('SELECT @@SESSION.in_transaction') === 1 && $lock() === null, 'Reject outer transaction without committing caller work');
    $db->query('ROLLBACK');
    $db->query('SET autocommit = 0');
    $failure(static fn(): array => $service->drain_dirty_queue());
    $db->query('ROLLBACK');
    $db->query('SET autocommit = 1');

    $reset();
    $seed($base);
    $seed($base + 1);
    $hits = 0;
    $filter = static function (string $sql) use ($base,$post_read,&$hits): string {
        if ($hits === 0 && $post_read($sql, $base)) { ++$hits; usleep(1100000); }
        return $sql;
    };
    $stats = $with_filter($filter, static fn(): array => $service->drain_dirty_queue(['max_runtime' => 1]));
    $expect($hits === 1 && $stats['processed'] === 1 && $stats['skipped'] === 1, 'Runtime bound prevents a second claim after slow reconciliation');
    $expect($row($base + 1)['status'] === 'dirty' && (int) $row($base + 1)['attempts'] === 0, 'Unstarted item retains its retry budget');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    $db->query('ROLLBACK');
    $db->query('SET autocommit = 1');
    $wpdb = $db;
    $db->prefix = $real_prefix;
    $db->options = $real_options;
    foreach (array_reverse($created) as $fixture) {
        if ($db->query("DROP TABLE `$fixture`") === false) { $error = new RuntimeException('Fixture cleanup failed: ' . $fixture); }
    }
    $clear_cache();
    $db->suppress_errors($old_suppression);
    $peer->close();
}
if ($error !== null) { WP_CLI::error($error->getMessage()); }
$expect($real_state() === $before, 'Real queue and lock/run options unchanged during isolated suite');
$expect($db->get_col($db->prepare('SHOW TABLES LIKE %s', $db->esc_like($prefix) . '%')) === [], 'No fixture tables remain');
WP_CLI::success("Graph queue processing: {$checks} checks passed; real queue/options unchanged; fixtures removed.");
