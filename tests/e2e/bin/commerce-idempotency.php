<?php
/** Disposable owner tables, temporary posts/meta, two connections and a mandatory no-mail stub. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Isolated fixtures and deliberate SQL failures.
global $wpdb;
$db = $wpdb; $checks = 0; $sent = 0; $events = [];
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON && !wp_using_ext_object_cache(), 'Cron disabled and caches process-local');
$real_prefix = $db->prefix; $real_options = $db->options; $real_requests = iss_payments_lite_requests_table_name();
$protected = [$real_requests,$db->posts,$db->postmeta,iss_graph_get_service()->get_dirty_queue_table_name()];
$real_state = static fn(): array => ['tables' => array_map(static fn(string $table): array => $db->get_results("CHECKSUM TABLE `$table`", ARRAY_A), $protected), 'options' => $db->get_results("SELECT * FROM `$real_options` ORDER BY option_id", ARRAY_A)];
$before = $real_state(); $prefix = $real_prefix . 'ci_' . bin2hex(random_bytes(4)) . '_'; $options = $prefix . 'options'; $table = $prefix . 'iss_payments_lite_requests';
$base = 2147479200;
$expect((int) $db->get_var($db->prepare("SELECT COUNT(*) FROM {$db->posts} WHERE ID BETWEEN %d AND %d", $base, $base + 3)) === 0, 'Source fixture IDs are unused');
$peer = new wpdb(DB_USER, DB_PASSWORD, DB_NAME, DB_HOST); $peer->set_prefix($real_prefix); $peer->prefix = $prefix; $peer->options = $options; $peer->suppress_errors(true);
$as_peer = static function (callable $call) use ($db,$peer) { global $wpdb; $wpdb = $peer; try { return $call(); } finally { $wpdb = $db; } };
$mail = static function ($value, array $atts) use (&$sent,$expect): bool { $expect($atts['to'] === 'fixture@example.invalid', 'Every intercepted mail has only the fixture recipient'); ++$sent; return true; };
$on_created = static function (string $kind, array $entry) use (&$events): void { $events[] = [$kind,$entry['request_id']]; };
add_filter('pre_wp_mail', $mail, 10, 2); add_action('iss_payments_lite_request_created', $on_created, 10, 2);
$with_filter = static function (callable $filter, callable $call) { add_filter('query', $filter); try { return $call(); } finally { remove_filter('query', $filter); } };
$created = []; $temporary = []; $error = null; $stage = 'setup'; $old_suppression = $db->suppress_errors(true);
$read = static fn(): array => $db->get_results("SELECT * FROM `$table` ORDER BY id", ARRAY_A);
$reset = static function () use ($db,$table,&$sent,&$events): void { if ($db->query("DELETE FROM `$table`") === false) { throw new RuntimeException('Cannot reset requests'); } $sent = 0; $events = []; delete_transient(iss_payments_lite_rate_limit_key('commerce-request')); };
$payload = static fn(string $kind = 'inquiry', string $key = 'fixture-request-0001'): array => ['intent' => $kind, 'source_post_id' => $kind === 'order' ? $base + 1 : $base, 'source_post_type' => $kind === 'order' ? 'publication' : 'veranstaltung', 'name' => 'Fixture visitor', 'email' => 'visitor@example.invalid', 'tickets' => 2, 'quantity' => 2, 'payment' => 'onsite', 'slot_id' => 'fixture-slot', 'start' => '2030-11-01T12:00:00', 'request_key' => $key, 'loaded_at' => time() - 10];
$request = new WP_REST_Request('POST', '/iss-payments/v1/request');
$call = static fn(array $data): WP_REST_Response => iss_payments_lite_process_request_payload($data, $request);
$entry = static fn(array $data): array => ['source_post_id' => $data['source_post_id'], 'source_post_type' => $data['source_post_type'], 'name' => $data['name'], 'email' => $data['email'], 'start' => $data['start'], 'title' => 'Fixture', 'tickets' => 1, 'payment' => 'onsite', 'duplicate_hash' => iss_payments_lite_duplicate_hash('inquiry', [strtolower($data['email']),$data['source_post_id'],$data['start']])];
try {
    foreach ([$db->posts,$db->postmeta] as $original) {
        $schema = $db->get_row("SHOW CREATE TABLE `$original`", ARRAY_N); $expect(is_array($schema), 'Read source fixture schema');
        $expect($db->query(preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $schema[1], 1)) !== false, 'Shadow source data connection-locally'); $temporary[] = $original;
    }
    foreach ([$base => 'veranstaltung', $base + 1 => 'publication'] as $id => $type) {
        $expect($db->insert($db->posts, ['ID' => $id, 'post_type' => $type, 'post_status' => 'publish', 'post_title' => 'Fixture', 'post_name' => 'fixture-' . $id, 'post_content' => 'Fixture content', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '']) === 1, 'Seed public source without save hooks');
    }
    foreach ([$base => ['iss_booking_enabled' => '1','iss_booking_price_cents' => '1000'], $base + 1 => ['_iss_publication_sale_enabled' => '1','_iss_publication_price_cents' => '600']] as $id => $meta) {
        foreach ($meta as $key => $value) { $expect($db->insert($db->postmeta, ['post_id' => $id, 'meta_key' => $key, 'meta_value' => $value]) === 1, 'Seed owner metadata'); }
    }
    $expect($db->query("CREATE TABLE `$options` LIKE `$real_options`") !== false, 'Isolated options/transients'); $created[] = $options; $created[] = $table;
    $db->prefix = $prefix; $db->options = $options; wp_cache_flush();
    iss_payments_lite_install_schema(); update_option('timezone_string', 'Europe/Berlin');
    iss_payments_lite_update_settings(['notifications_enabled' => true, 'notification_email' => 'fixture@example.invalid', 'require_nonce' => true, 'min_submit_seconds' => 3]);
    $occurrences = iss_occurrences_get_service();
    foreach ($occurrences->get_schema_statements() as $schema) { preg_match('/CREATE TABLE ([a-zA-Z0-9_]+)/', $schema, $match); $created[] = $match[1]; }
    $occurrences->install_schema(); $occurrence_table = $occurrences->get_occurrences_table_name();
    $expect($db->insert($occurrence_table, ['source_post_id' => $base, 'source_post_type' => 'veranstaltung', 'kind' => 'event', 'title' => 'Fixture', 'starts_at' => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS), 'external_id' => 'fixture-slot', 'capacity_available' => 10, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00']) === 1, 'Seed future booking availability');
    foreach (['booking','inquiry','order'] as $kind) {
        $stage = $kind . '_retry'; $reset(); $data = $payload($kind);
        $expect($call($data)->get_status() === 200 && count($read()) === 1 && $sent === 1 && count($events) === 1, 'Initial request persists and dispatches once');
        $rows = $read();
        $expect($rows[0]['request_key'] === hash('sha256', $data['request_key']) && strlen($rows[0]['request_fingerprint']) === 64 && $rows[0]['notified_at'] !== null, 'Unique request identity and notification outcome stored');
        $expect($call($data)->get_status() === 200 && $read() === $rows && $sent === 1 && count($events) === 1, 'Lost-response retry returns original success without notification or hook replay');
        $changed = $data; $changed['name'] = 'Another visitor';
        $expect($call($changed)->get_status() === 409 && $read() === $rows && $sent === 1, 'Reusing a key for changed intent conflicts');
        $changed = $data; $changed['request_key'] = 'fixture-request-0002';
        $expect($call($changed)->get_status() === 409 && $read() === $rows, 'Separate keys retain the existing duplicate window');
    }
    $stage = 'availability_change'; $reset(); $data = $payload('booking'); $expect($call($data)->get_status() === 200, 'Booking accepted before availability changes');
    $db->update($occurrence_table, ['capacity_available' => 0], ['source_post_id' => $base]);
    $expect($call($data)->get_status() === 200 && count($read()) === 1 && $sent === 1, 'Committed replay survives later sold-out state');
    $db->update($occurrence_table, ['capacity_available' => 10], ['source_post_id' => $base]);
    foreach (['lookup','state','lock','inside_lookup','duplicate','insert','ignored_insert'] as $stage) {
        $reset(); $data = $payload(); $hits = 0; $lookups = 0;
        $filter = static function (string $sql) use ($stage,$table,&$hits,&$lookups): string {
            $lookup = str_starts_with($sql, "SELECT request_kind, request_fingerprint FROM $table"); if ($lookup) { ++$lookups; }
            $match = match ($stage) { 'lookup' => $lookup, 'inside_lookup' => $lookup && $lookups === 2, 'state' => str_contains($sql, '@@SESSION.in_transaction'), 'lock' => str_starts_with($sql, 'SELECT GET_LOCK('), 'duplicate' => str_starts_with($sql, "SELECT COUNT(*) FROM $table WHERE request_kind"), 'insert','ignored_insert' => str_starts_with($sql, "INSERT INTO `$table`") };
            if (!$match || $hits) { return $sql; } ++$hits;
            return $stage === 'ignored_insert' ? "UPDATE `$table` SET status = status WHERE id = 0" : 'SELECT iss_commerce_injected_failure';
        };
        $expect($with_filter($filter, static fn() => $call($data))->get_status() === 500 && $hits === 1 && !$read() && !$sent && !$events, "$stage does not persist, notify or poison retry");
        $expect($call($data)->get_status() === 200 && count($read()) === 1 && $sent === 1, 'Retry after failure succeeds once');
    }
    $stage = 'release'; $reset(); $data = $payload(); $hits = 0;
    $filter = static function (string $sql) use (&$hits): string {
        if (!$hits && str_starts_with($sql, 'SELECT RELEASE_LOCK(')) { ++$hits; return 'SELECT iss_commerce_release_failure'; }
        return $sql;
    };
    $expect($with_filter($filter, static fn() => $call($data))->get_status() === 200 && $hits === 1 && count($read()) === 1 && $sent === 1, 'Release failure cannot discard dispatch of a committed request');
    $db->query('SELECT RELEASE_ALL_LOCKS()');
    $expect($call($data)->get_status() === 200 && $sent === 1, 'Retry after release failure is still idempotent');
    $stage = 'legacy'; $reset(); $data = $payload(); unset($data['request_key']);
    $expect($call($data)->get_status() === 200 && $read()[0]['request_key'] === null && $call($data)->get_status() === 409 && $sent === 1, 'Legacy clients remain usable with their prior duplicate response');
    $stage = 'guards'; $reset(); $data = $payload(); $invalid = $data; $invalid['request_key'] = ['invalid'];
    $expect($call($invalid)->get_status() === 400 && !$read(), 'Malformed key is rejected');
    $request->set_body((string) wp_json_encode($data));
    $expect(iss_payments_lite_create_request($request)->get_status() === 403 && !$read(), 'Nonce guard still runs');
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $expect(iss_payments_lite_create_request($request)->get_status() === 200 && $sent === 1, 'Guarded public endpoint accepts valid request');
    $expect(iss_payments_lite_create_request($request)->get_status() === 200 && $sent === 1, 'Guarded endpoint preserves replay behavior');
    foreach ([false,true] as $autocommit_off) {
        $stage = 'caller_transaction'; $reset(); $db->query($autocommit_off ? 'SET autocommit = 0' : 'START TRANSACTION');
        $expect($call($payload())->get_status() === 500 && !$read() && !$sent, 'Intake cannot notify or acknowledge an uncommitted caller transaction');
        $db->query('ROLLBACK'); $db->query('SET autocommit = 1');
    }
    $stage = 'two_connection_same_key'; $reset(); $data = $payload(); $hits = 0;
    $filter = static function (string $sql) use ($table,$data,$entry,$request,$as_peer,&$hits): string {
        if (!$hits && str_starts_with($sql, "SELECT request_kind, request_fingerprint FROM $table")) {
            ++$hits; $result = $as_peer(static fn() => iss_payments_lite_store_request('inquiry', $entry($data), $data, $request));
            if ($result->get_status() !== 200) { throw new RuntimeException('Peer request failed'); }
        }
        return $sql;
    };
    $expect($with_filter($filter, static fn() => $call($data))->get_status() === 200 && $hits === 1 && count($read()) === 1 && $sent === 1 && count($events) === 1, 'Competing identical request returns one committed request and one dispatch');
    $stage = 'unique_key_race'; $reset(); $data = $payload(); $other = $data; $other['email'] = 'other@example.invalid'; $hits = 0;
    $filter = static function (string $sql) use ($table,$other,$entry,$request,$as_peer,&$hits): string {
        if (!$hits && str_starts_with($sql, "INSERT INTO `$table`")) {
            ++$hits; $result = $as_peer(static fn() => iss_payments_lite_store_request('inquiry', $entry($other), $other, $request));
            if ($result->get_status() !== 200) { throw new RuntimeException('Peer competing intent failed'); }
        }
        return $sql;
    };
    $expect($with_filter($filter, static fn() => $call($data))->get_status() === 409 && $hits === 1 && count($read()) === 1 && $read()[0]['customer_email'] === $other['email'] && $sent === 1, 'Unique index resolves same-key different-intent race across duplicate locks');
    $stage = 'duplicate_lock'; $reset(); $data = $payload(); $hits = 0; $peer_failed = false;
    $filter = static function (string $sql) use ($table,$data,$entry,$request,$as_peer,&$hits,&$peer_failed): string {
        if (!$hits && str_starts_with($sql, "INSERT INTO `$table`")) {
            ++$hits;
            try { $as_peer(static fn() => iss_payments_lite_store_request('inquiry', $entry($data), $data, $request)); }
            catch (RuntimeException $error) { $peer_failed = true; }
        }
        return $sql;
    };
    $expect($with_filter($filter, static fn() => $call($data))->get_status() === 200 && $peer_failed && $hits === 1 && count($read()) === 1 && $sent === 1, 'Contending duplicate cannot bypass active persistence lock');
    $expect($as_peer(static fn() => iss_payments_lite_store_request('inquiry', $entry($data), $data, $request))->get_status() === 200 && $sent === 1, 'Contending retry sees the committed result without dispatch');
} catch (Throwable $caught) { $error = $caught; }
finally {
    $db->query('ROLLBACK'); $db->query('SET autocommit = 1'); $db->query('SELECT RELEASE_ALL_LOCKS()'); $peer->query('SELECT RELEASE_ALL_LOCKS()');
    $db->prefix = $real_prefix; $db->options = $real_options;
    foreach (array_reverse($temporary) as $name) { if ($db->query("DROP TEMPORARY TABLE `$name`") === false) { $error = new RuntimeException('Temporary fixture cleanup failed'); } }
    foreach (array_reverse($created) as $name) { if ($db->query("DROP TABLE IF EXISTS `$name`") === false) { $error = new RuntimeException('Disposable fixture cleanup failed'); } }
    wp_cache_flush(); $db->suppress_errors($old_suppression); $peer->close(); remove_filter('pre_wp_mail', $mail); remove_action('iss_payments_lite_request_created', $on_created);
}
$expect($real_state() === $before, 'Real requests, posts, metadata, queue and options unchanged');
if ($error) { WP_CLI::error("After $checks checks ($stage): " . $error->getMessage() . "\n" . $error->getTraceAsString()); }
WP_CLI::success("Commerce idempotency: $checks checks passed; real rows unchanged; no mail sent; fixtures removed.");
