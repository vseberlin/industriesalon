<?php
/** Run with wp eval-file. Exercises real edit, logout and heartbeat requests on disposable fixtures. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/user.php';

$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
};
$original_user = get_current_user_id();
$original_cookie = $_COOKIE;
$users = [];
$id = 0;
$sets = [];
$session = static function (int $user): array {
    $expires = time() + HOUR_IN_SECONDS;
    $token = WP_Session_Tokens::get_instance($user)->create($expires);
    $logged_in = wp_generate_auth_cookie($user, $expires, 'logged_in', $token);
    $secure = wp_parse_url(admin_url(), PHP_URL_SCHEME) === 'https';
    $auth_cookie = ($secure ? SECURE_AUTH_COOKIE : AUTH_COOKIE) . '=' . wp_generate_auth_cookie($user, $expires, $secure ? 'secure_auth' : 'auth', $token);
    return ['token' => $token, 'logged_in' => $logged_in, 'headers' => ['Cookie' => $auth_cookie . '; ' . LOGGED_IN_COOKIE . '=' . $logged_in]];
};
$activate = static function (int $user, array $session): void {
    wp_set_current_user($user);
    $_COOKIE[LOGGED_IN_COOKIE] = $session['logged_in'];
};
$read_settings = static function (int $id, array $session) use ($assert): array {
    $response = wp_remote_get(admin_url('post.php?post=' . $id . '&action=edit'), ['headers' => $session['headers'], 'timeout' => 30]);
    $assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200, 'Native edit screen responds');
    preg_match('/var issEditorialAdmin = (.*?);\s*\n/', wp_remote_retrieve_body($response), $matches);
    $settings = json_decode($matches[1] ?? '', true);
    $assert(is_array($settings), 'Shared canvas config is present');
    return $settings;
};
$logout = static function (int $user, array $session) use ($activate, $assert): void {
    $activate($user, $session);
    $response = wp_remote_get(html_entity_decode(wp_logout_url()), ['headers' => $session['headers'], 'timeout' => 30, 'redirection' => 0]);
    $assert(!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 302, 'Native logout succeeds');
    clean_user_cache($user);
    $assert(!WP_Session_Tokens::get_instance($user)->verify($session['token']), 'Logout destroys only this test session');
};

try {
    foreach (['administrator', 'iss_project_uat'] as $role) {
        $user = wp_insert_user(['user_login' => 'editorial-lock-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => $role]);
        $assert(!is_wp_error($user), 'Create fixture account');
        $users[] = $user;
    }
    [$first, $second] = $users;
    wp_set_current_user($first);
    $id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => 'Editorial lock fixture', 'post_author' => $first], true);
    $assert(!is_wp_error($id), 'Create fixture project');
    $sets = array_column(iss_content_editorial_sets_service()->get_links_for_context('projekt', $id), 'set_id');
    $doc = iss_editorial_get_empty_document('projekt');
    $doc['sections'] = [['type' => 'kapitel', 'title' => 'Saved chapter', 'body' => '<p>Saved text</p>']];
    $assert(iss_editorial_save_document($id, 'projekt', $doc), 'Save fixture content');
    iss_editorial_set_document_enabled($id, 'projekt', true);
    $canonical = iss_editorial_saved_token($id, 'projekt');
    $draft_doc = iss_editorial_get_document($id, 'projekt');
    $draft_doc['sections'][0]['title'] = 'First editor private draft';
    $draft = iss_editorial_save_draft($id, 'projekt', $draft_doc, true);
    $assert(!is_wp_error($draft), 'Private recovery exists before switching');
    $a = $session($first);
    $b = $session($second);
    $activate($first, $a);
    $a_nonce = wp_create_nonce('heartbeat-nonce');
    $a_settings = $read_settings($id, $a);
    $assert(absint($a_settings['userId'] ?? 0) === $first, 'Editor identity is localized');
    $assert(!empty($a_settings['recovery']), 'Private recovery is localized');
    $activate($second, $b);
    $read_settings($id, $b);
    clean_post_cache($id);
    $assert(wp_check_post_lock($id) === $first, 'A second signed-in editor cannot steal the native lock');
    $blocked = iss_editorial_check_edit_version($id, 'projekt', $canonical);
    $assert(is_wp_error($blocked) && $blocked->get_error_code() === 'editorial_locked', 'Saving remains blocked during genuine concurrent editing');

    $logout($first, $a);
    $activate($second, $b);
    $b_settings = $read_settings($id, $b);
    clean_post_cache($id);
    $assert(wp_check_post_lock($id) === false && str_ends_with(get_post_meta($id, '_edit_lock', true), ':' . $second), 'Switching after logout immediately acquires the native lock');
    $assert(empty($b_settings['recovery']), 'Switching never imports another author recovery');
    $lock = get_post_meta($id, '_edit_lock', true);
    $heartbeat = static function (array $session, int $editor, string $nonce) use ($id): array {
        $response = wp_remote_post(admin_url('admin-ajax.php'), ['headers' => $session['headers'], 'timeout' => 30, 'body' => [
            'action' => 'heartbeat', '_nonce' => $nonce, 'screen_id' => 'projekt',
            'data' => ['iss-editorial-user' => $editor, 'wp-refresh-post-lock' => ['post_id' => $id]],
        ]]);
        return json_decode(wp_remote_retrieve_body($response), true) ?? [];
    };
    $changed = $heartbeat($b, $first, $a_nonce);
    clean_post_cache($id);
    $assert(!empty($changed['iss-editorial-session-changed']) && !isset($changed['wp-refresh-post-lock']), 'An old tab detects account switching before native lock renewal');
    $assert(get_post_meta($id, '_edit_lock', true) === $lock, 'Old-tab heartbeat leaves the current lock unchanged');
    $expired = $heartbeat($b, $second, $a_nonce);
    $assert(!empty($expired['iss-editorial-session-changed']), 'An expired session also requires fresh page nonces');
    $tick = $heartbeat($b, $second, wp_create_nonce('heartbeat-nonce'));
    $assert(!empty($tick['wp-refresh-post-lock']['new_lock']), 'Current-account heartbeat keeps native lock renewal');

    // Logging out one browser must not release an editor still signed in elsewhere.
    $b_other = $session($second);
    $logout($second, $b);
    $a = $session($first);
    $activate($first, $a);
    $read_settings($id, $a);
    clean_post_cache($id);
    $assert(wp_check_post_lock($id) === $second, 'Another valid session keeps its editing lock');
    $logout($second, $b_other);
    $activate($first, $a);
    $read_settings($id, $a);
    clean_post_cache($id);
    $assert(wp_check_post_lock($id) === false, 'Switching back after the final logout opens editing immediately');
    $assert(iss_editorial_get_draft($id, 'projekt')['token'] === $draft['token'], 'Private recovery survives all account switches');
    $assert(iss_editorial_saved_token($id, 'projekt') === $canonical, 'Switching and heartbeat never change canonical content');
    WP_CLI::log("PASS: $checks account-switch, concurrency and recovery checks.");
} finally {
    wp_set_current_user(get_users(['role' => 'administrator', 'number' => 1])[0]->ID);
    if (is_int($id) && $id > 0) { wp_delete_post($id, true); }
    foreach ($sets as $set_id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe((int) $set_id), 'Remove fixture Set');
        $GLOBALS['wpdb']->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    foreach ($users as $user) { wp_delete_user($user); }
    $_COOKIE = $original_cookie;
    wp_set_current_user($original_user);
}
