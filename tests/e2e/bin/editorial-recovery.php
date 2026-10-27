<?php
/** wp eval-file: real HTTP recovery, structured failures and route conflicts on disposable records. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
$checks = 0;
$assert = static function ($ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$old_user = get_current_user_id(); $old_cookie = $_COOKIE;
$user = 0; $id = 0; $sets = [];
try {
    $user = wp_insert_user(['user_login' => 'recovery-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'administrator']);
    $assert(is_int($user), 'Create disposable editor');
    wp_set_current_user($user);
    $expires = time() + HOUR_IN_SECONDS;
    $token = WP_Session_Tokens::get_instance($user)->create($expires);
    $logged_in = wp_generate_auth_cookie($user, $expires, 'logged_in', $token);
    $_COOKIE[LOGGED_IN_COOKIE] = $logged_in;
    $secure = wp_parse_url(admin_url(), PHP_URL_SCHEME) === 'https';
    $headers = ['Cookie' => ($secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE) . '=' . wp_generate_auth_cookie($user, $expires, $secure ? 'secure_auth' : 'auth', $token) . '; ' . LOGGED_IN_COOKIE . '=' . $logged_in];
    $id = wp_insert_post(['post_type' => 'fuehrung', 'post_status' => 'draft', 'post_title' => 'Recovery fixture', 'post_author' => $user], true);
    $assert(is_int($id), 'Create disposable tour');
    $sets = array_column(iss_content_editorial_sets_service()->get_links_for_context('fuehrung', $id), 'set_id');
    $doc = iss_editorial_get_empty_document('fuehrung');
    $doc['schema_version'] = 1;
    $doc['sections'] = [['type' => 'kapitel', 'title' => 'Saved', 'body' => '<p>Retain this text</p>']];
    $assert(iss_editorial_save_document($id, 'fuehrung', $doc, false, true), 'Seed document');
    $base = iss_editorial_saved_token($id, 'fuehrung');
    $params = ['action' => 'iss_editorial_save_preview_document', 'nonce' => wp_create_nonce('iss_editorial_preview_document'), 'post_id' => $id, 'format' => 'fuehrung', 'document' => wp_json_encode($doc), 'base' => $base, 'draft_token' => '', 'enabled' => '1', 'title' => 'Private title', 'excerpt' => '', 'intent' => 'autosave'];
    $send = static function (array $body) use ($headers, $assert): array {
        $response = wp_remote_post(admin_url('admin-ajax.php'), ['headers' => $headers, 'body' => $body, 'timeout' => 30]);
        $assert(!is_wp_error($response), 'AJAX transport responds');
        $payload = json_decode(wp_remote_retrieve_body($response), true);
        $assert(is_array($payload), 'AJAX errors and successes are structured JSON');
        return [wp_remote_retrieve_response_code($response), $payload];
    };
    [$status, $first] = $send($params);
    $assert($status === 200 && $first['success'], 'Initial autosave commits');
    $draft_token = $first['data']['draftToken'];
    [$status, $replayed] = $send($params);
    $assert($status === 200 && $replayed['data']['draftToken'] === $draft_token, 'Lost-response retry acknowledges exactly the prior snapshot');
    [$status, $conflict] = $send(array_merge($params, ['title' => 'Competing work']));
    $assert($status === 409 && $conflict['data']['code'] === 'editorial_draft_conflict', 'Different stale input cannot overwrite the private draft');
    [$status, $expired] = $send(array_merge($params, ['nonce' => 'expired']));
    $assert($status === 403 && $expired['data']['code'] === 'editorial_session_expired', 'Expired nonce is an actionable JSON error');
    $bad = $doc; $bad['sections'][0]['body'] = '<div><p>Unsupported old wrapper</p></div>';
    [$status, $invalid] = $send(array_merge($params, ['document' => wp_json_encode($bad), 'draft_token' => $draft_token, 'intent' => 'validate']));
    $assert($status === 422 && $invalid['data']['code'] === 'editorial_lossy_normalization' && $invalid['data']['details']['path'] === 'sections[0].body', 'Validation returns the exact field without modifying recovery');
    [$status, $state] = $send(array_merge($params, ['intent' => 'inspect']));
    $assert($status === 200 && $state['data']['draft']['token'] === $draft_token && $state['data']['canonical']['title'] === 'Recovery fixture', 'Conflict inspection returns distinct saved and private copies');
    $assert(iss_core_acquire_post_write_lock($id), 'Own DB connection acquires the write mutex');
    [$status, $busy] = $send(array_merge($params, ['draft_token' => $draft_token]));
    $assert($status === 409 && $busy['data']['code'] === 'editorial_write_busy', 'Concurrent HTTP writer is rejected before mutation');
    iss_core_release_post_write_locks();
    update_post_meta($id, iss_editorial_get_document_meta_key('fuehrung'), wp_slash(wp_json_encode($bad)));
    $invalid_base = iss_editorial_saved_token($id, 'fuehrung');
    [$status, $discarded] = $send(array_merge($params, ['base' => $invalid_base, 'draft_token' => $draft_token, 'intent' => 'discard']));
    $assert($status === 200 && $discarded['data']['draftToken'] === '', 'Invalid canonical content does not prevent explicit private-draft discard');
    [$status, $repair] = $send(array_merge($params, ['base' => $invalid_base, 'intent' => 'validate']));
    $assert($status === 200 && $repair['data']['valid'], 'Valid repair can be checked without replacing invalid canonical content');
    $assert(get_post_meta($id, iss_editorial_get_document_meta_key('fuehrung'), true) === wp_json_encode($bad), 'Validation is read-only');
    $assert(iss_editorial_save_document($id, 'fuehrung', $doc, false, true), 'Restore fixture document');

    $route = static function (array $body) use ($id, $headers, $assert): array {
        $response = wp_remote_post(rest_url('iss-relations/v1/posts/' . $id . '/route-draft'), ['headers' => array_merge($headers, ['X-WP-Nonce' => wp_create_nonce('wp_rest'), 'Content-Type' => 'application/json']), 'body' => wp_json_encode($body), 'timeout' => 30]);
        $assert(!is_wp_error($response), 'Route transport responds');
        $payload = json_decode(wp_remote_retrieve_body($response), true);
        $assert(is_array($payload), 'Route response is structured');
        return [wp_remote_retrieve_response_code($response), $payload];
    };
    $route_base = iss_relations_route_relation_hash(iss_relations_get_stored_post_relations($id));
    [$status, $unlocked] = $route(['action' => 'unlock', 'baseHash' => $route_base, 'draftToken' => '']);
    $assert($status === 200 && !$unlocked['locked'], 'Route unlock creates the owner draft');
    [$status, $again] = $route(['action' => 'unlock', 'baseHash' => $route_base, 'draftToken' => '']);
    $assert($status === 200 && $again['draftToken'] === $unlocked['draftToken'], 'Lost unlock response can be acknowledged safely');
    [$status, $stale] = $route(['action' => 'save', 'baseHash' => 'stale', 'draftToken' => $unlocked['draftToken'], 'relations' => [], 'trash' => []]);
    $assert($status === 409 && $stale['code'] === 'iss_relations_route_conflict', 'Canonical route conflict is checked');
    [$status, $stale] = $route(['action' => 'discard', 'baseHash' => $route_base, 'draftToken' => 'stale']);
    $assert($status === 409 && $stale['code'] === 'iss_relations_route_draft_conflict', 'Stale route tab cannot discard another draft');
    foreach ([['relations' => 'invalid', 'trash' => []], ['relations' => [], 'trash' => 'invalid'], ['relations' => [['place_id' => 0, 'role' => 'stop']], 'trash' => []]] as $malformed) {
        [$status, $invalid_route] = $route(array_merge(['action' => 'save', 'baseHash' => $route_base, 'draftToken' => $unlocked['draftToken']], $malformed));
        $assert($status === 422 && $invalid_route['code'] === 'iss_relations_route_incomplete', 'Malformed route input fails explicitly before any write or retry acknowledgement');
    }
    [$status, $saved] = $route(['action' => 'save', 'baseHash' => $route_base, 'draftToken' => $unlocked['draftToken'], 'relations' => [], 'trash' => []]);
    $assert($status === 200 && isset($saved['draftToken']), 'Fresh route save succeeds');
    [$status, $published] = $route(['action' => 'publish', 'baseHash' => $route_base, 'draftToken' => $saved['draftToken']]);
    $assert($status === 200 && $published['locked'] && $published['draftToken'] === '', 'Publication verifies canonical storage and draft cleanup');
    WP_CLI::log("PASS: $checks HTTP recovery and route concurrency checks.");
} finally {
    iss_core_release_post_write_locks();
    wp_set_current_user(get_users(['role' => 'administrator', 'number' => 1])[0]->ID);
    if (is_int($id) && $id > 0) { wp_delete_post($id, true); }
    foreach ($sets as $set_id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe((int) $set_id), 'Remove fixture Set');
        $GLOBALS['wpdb']->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    if (is_int($user) && $user > 0) { wp_delete_user($user); }
    $_COOKIE = $old_cookie; wp_set_current_user($old_user);
}
