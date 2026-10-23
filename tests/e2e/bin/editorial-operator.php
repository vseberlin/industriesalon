<?php
/** wp eval-file FILE --use-include; isolated records, no mail, always cleaned up. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/user.php';
$checks = 0;
$fixtures = [];
$user_id = 0;
$mail_attempts = 0;
add_filter('pre_wp_mail', static function () use (&$mail_attempts) { ++$mail_attempts; return false; });
$assert = static function ($ok, string $label) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($label); }
    ++$checks;
};
$request = static function (string $method, string $route, array $params = []): WP_REST_Response {
    $request = new WP_REST_Request($method, $route);
    $request->set_body_params($params);
    return rest_do_request($request);
};
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
try {
    $user_id = wp_insert_user(['user_login' => 'operator-check-' . wp_generate_password(10, false),
        'user_pass' => wp_generate_password(40), 'role' => 'iss_editorial_operator']);
    $assert(!is_wp_error($user_id), 'Create isolated role fixture');
    wp_set_current_user($user_id);
    foreach (iss_core_editorial_operator_grants() as $cap) { $assert(current_user_can($cap), 'Grant ' . $cap); }
    foreach (['manage_options', 'edit_posts', 'edit_users', 'list_users', 'activate_plugins',
        'install_plugins', 'edit_theme_options', 'iss_send_newsletter', 'iss_delete_sets', 'iss_cleanup_data'] as $cap) {
        $assert(!current_user_can($cap), 'Deny ' . $cap);
    }
    foreach (iss_core_editorial_operator_post_types() as $type) {
        $object = get_post_type_object($type);
        $assert($object && current_user_can($object->cap->create_posts), 'Create ' . $type);
        $assert(!current_user_can($object->cap->publish_posts), 'No publication ' . $type);
        $assert(!current_user_can($object->cap->delete_posts), 'No deletion ' . $type);
        wp_set_current_user($admin->ID);
        $id = wp_insert_post(['post_type' => $type, 'post_title' => 'Temporary operator fixture ' . wp_generate_password(8, false),
            'post_password' => wp_generate_password(), 'post_status' => $type === 'attachment' ? 'inherit' : 'publish', 'post_author' => $admin->ID], true);
        $assert(!is_wp_error($id), 'Insert fixture ' . $type);
        $fixtures[] = $id;
        wp_set_current_user($user_id);
        $assert(current_user_can('edit_post', $id), 'Edit another author ' . $type);
        $assert(!current_user_can('delete_post', $id), 'Deny object deletion ' . $type);
        $base = '/wp/v2/' . ($object->rest_base ?: $type);
        if (!$object->show_in_rest) { continue; }
        $response = $request('POST', $base . '/' . $id, ['title' => 'Edited temporary operator fixture']);
        $assert($response->get_status() === 200, 'REST edit ' . $type . ': ' . wp_json_encode($response->get_data()));
        if ($type === 'attachment') { continue; }
        $response = $request('POST', $base, ['title' => 'New temporary operator draft', 'status' => 'draft']);
        $assert($response->get_status() === 201, 'REST draft ' . $type . ': ' . wp_json_encode($response->get_data()));
        $created = $response->get_data()['id'];
        $fixtures[] = $created;
        $assert($request('POST', $base . '/' . $created, ['status' => 'publish'])->get_status() === 403, 'REST publish denied ' . $type);
        $assert($request('DELETE', $base . '/' . $created)->get_status() === 403, 'REST delete denied ' . $type);
        foreach (get_registered_meta_keys('post', $type) as $key => $meta) {
            if (!empty($meta['show_in_rest']) && !empty($meta['auth_callback'])) {
                $assert(current_user_can('edit_post_meta', $created, $key), 'Metadata access ' . $type . '/' . $key);
            }
        }
    }
    foreach (['posts', 'video', 'blocks', 'templates', 'navigation'] as $route) {
        $assert($request('POST', '/wp/v2/' . $route, ['title' => 'Must not exist', 'status' => 'draft', 'slug' => 'operator-denied'])->get_status() === 403, 'Unlisted REST denied ' . $route);
    }
    foreach (['save', 'preview', 'create'] as $act) {
        $assert(iss_newsletter_editorial_request_allowed(['page' => 'newsletter_emails_composer', 'act' => $act]), 'Newsletter draft ' . $act);
    }
    foreach (['send', 'schedule', 'test', 'continue', 'delete', 'delete_selected'] as $act) {
        $assert(!iss_newsletter_editorial_request_allowed(['page' => 'newsletter_emails_edit', 'act' => $act]), 'Newsletter denied ' . $act);
    }
    foreach (['tnpc_test', 'tnpc_test_raw_html', 'tnp', 'tnpc_regenerate_email'] as $action) {
        $assert(!iss_newsletter_editorial_request_allowed(['action' => $action]), 'Newsletter AJAX denied ' . $action);
    }
    $assert($mail_attempts === 0, 'No mail attempted');
} finally {
    wp_set_current_user($admin->ID);
    foreach (array_reverse($fixtures) as $id) {
        $sets = iss_content_editorial_sets_service()->get_links_for_context(get_post_type($id), $id);
        wp_delete_post($id, true);
        foreach ($sets as $link) { $assert(iss_content_editorial_sets_service()->delete_set_if_safe((int) $link['set_id']), 'Remove own empty Set'); }
    }
    if (is_int($user_id) && $user_id > 0) { wp_delete_user($user_id); }
}
WP_CLI::success($checks . ' editorial operator checks passed; fixtures removed; mail attempts=' . $mail_attempts);
