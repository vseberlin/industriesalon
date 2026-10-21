<?php
/** wp eval-file project-uat.php USER_LOGIN; creates and removes only its own fixtures. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/post.php';

$tester = get_user_by('login', $args[0] ?? '');
if (!$tester || $tester->roles !== ['iss_project_uat']) { WP_CLI::error('Supply a project UAT account.'); }
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
$original_user = get_current_user_id();
$original_post = $_POST;
$fixtures = [];
$archive_set = 0;
$checks = 0;
$assert = static function ($condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
};
$request = static function (string $method, string $route, array $params = []): WP_REST_Response {
    $request = new WP_REST_Request($method, $route);
    if (in_array($method, ['GET', 'HEAD'], true)) {
        $request->set_query_params($params);
    } else {
        $request->set_body_params($params);
    }
    return rest_do_request($request);
};
$snapshot = static function (): string {
    global $wpdb;
    return hash('sha256', serialize($wpdb->get_results("SELECT p.ID,p.post_title,p.post_content,p.post_excerpt,p.post_status,m.meta_key,m.meta_value FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key NOT IN ('_edit_lock', '_iss_editorial_lock_session') WHERE p.post_type <> 'revision' ORDER BY p.ID,m.meta_id", ARRAY_A)));
};
$before = $snapshot();
$die_handler = static function () {
    return static function ($message): void { throw new RuntimeException(wp_strip_all_tags((string) $message)); };
};
add_filter('wp_die_handler', $die_handler, PHP_INT_MAX);
try {
    wp_set_current_user($admin->ID);
    foreach (['draft', 'publish', 'private'] as $status) {
        $id = wp_insert_post(['post_type' => 'projekt', 'post_status' => $status, 'post_title' => 'Temporary project UAT fixture', 'post_password' => $status === 'publish' ? wp_generate_password() : '', 'post_author' => $admin->ID], true);
        $assert(!is_wp_error($id), 'Create project fixture');
        $fixtures[$status] = $id;
    }
    $other = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Temporary access-denial fixture'], true);
    $assert(!is_wp_error($other), 'Create other-content fixture');
    $fixtures['page'] = $other;
    $archive_set = iss_wf_import_get_archivset_service()->create_set(['title' => 'Temporary UAT archive picker fixture']);
    $assert($archive_set > 0, 'Create unlinked archive selection fixture');
    wp_set_current_user($tester->ID);
    foreach (['create_projekte', 'edit_projekte', 'edit_others_projekte', 'edit_published_projekte', 'upload_files', 'iss_read_sets'] as $cap) {
        $assert(current_user_can($cap), 'Required capability: ' . $cap);
    }
    foreach (['publish_projekte', 'delete_projekte', 'edit_posts', 'edit_pages', 'manage_options', 'list_users', 'activate_plugins', 'iss_access_operations', 'iss_create_sets', 'iss_edit_sets', 'iss_review_sets', 'iss_promote_media', 'iss_delete_sets', 'iss_cleanup_sets', 'iss_manage_archive', 'iss_import_archive'] as $cap) {
        $assert(!current_user_can($cap), 'Restricted capability: ' . $cap);
    }
    $assert($request('GET', '/iss-content/v1/editorial-sets', ['perPage' => 1])->get_status() === 200, 'Browse existing private Sets');
    $assert($request('GET', '/iss-content/v1/editorial-set-items', ['perPage' => 1])->get_status() === 200, 'Browse existing Set media');
    $assert($request('GET', '/iss-content/v1/editorial-sets', ['contextId' => $other])->get_status() === 403, 'Cannot browse with an uneditable context');
    $assert($request('POST', '/iss-content/v1/editorial-sets', ['title' => 'Must not be created'])->get_status() === 403, 'Set creation denied');
    $assert($request('POST', '/iss-content/v1/editorial-sets/0', ['title' => 'Must not change'])->get_status() === 403, 'Set editing denied');
    $archive_context = ['contextPostId' => $fixtures['draft']];
    $assert($request('GET', '/iss-archive/v1/sets/' . $archive_set, $archive_context)->get_status() === 200, 'Read an archive selection before attaching it');
    $assert($request('GET', '/iss-archive/v1/sets/' . $archive_set, ['contextPostId' => $other])->get_status() === 403, 'Archive read requires an editable context');
    $assert($request('GET', '/iss-archive/v1/sets/' . $archive_set)->get_status() === 403, 'No standalone archive management access');
    $assert($request('POST', '/iss-archive/v1/sets/' . $archive_set, $archive_context)->get_status() === 403, 'Archive selection metadata editing denied');
    $assert($request('DELETE', '/iss-archive/v1/sets/' . $archive_set, $archive_context)->get_status() === 403, 'Archive selection deletion denied');
    $assert($request('POST', '/iss-archive/v1/sets/' . $archive_set . '/members', $archive_context)->get_status() === 403, 'Browsing an unlinked selection does not grant member edits');
    $assert($request('POST', '/iss-archive/v1/sets', $archive_context + ['title' => 'Must not be created'])->get_status() === 403, 'Archive selection creation denied');
    $assert($request('POST', '/iss-archive/v1/content/' . $fixtures['draft'] . '/sets', ['setId' => $archive_set])->get_status() === 403, 'Archive selection linking denied');
    wp_set_current_user($admin->ID);
    $assert(iss_wf_import_get_archivset_service()->attach_set($archive_set, $fixtures['draft']), 'Attach own archive fixture');
    wp_set_current_user($tester->ID);
    $assert($request('GET', '/iss-archive/v1/sets/' . $archive_set, $archive_context)->get_status() === 200, 'Read attached archive selection');
    $assert($request('POST', '/iss-archive/v1/sets/' . $archive_set . '/members', $archive_context)->get_status() === 403, 'Attached archive membership editing denied');
    $assert($request('DELETE', '/iss-archive/v1/content/' . $fixtures['draft'] . '/sets/' . $archive_set)->get_status() === 403, 'Archive selection unlinking denied');
    wp_set_current_user(0);
    $assert($request('GET', '/iss-content/v1/editorial-sets')->get_status() === 401, 'Anonymous private Set access denied');
    $assert($request('GET', '/iss-archive/v1/sets/' . $archive_set, $archive_context)->get_status() === 401, 'Anonymous archive selection access denied');
    wp_set_current_user($tester->ID);
    foreach (get_post_types(['show_ui' => true], 'objects') as $type) {
        if ($type->name !== 'projekt') {
            $assert(!current_user_can($type->cap->edit_posts), 'Cannot manage ' . $type->name);
        }
    }
    $assert(current_user_can('edit_post', $fixtures['draft']), 'Edit another author project draft');
    $assert(current_user_can('edit_post', $fixtures['publish']), 'Edit published project');
    $assert(!current_user_can('edit_post', $fixtures['private']), 'Private projects remain restricted');
    $assert(!current_user_can('edit_post', $other), 'Cannot edit page');
    $created = $request('POST', '/wp/v2/projekt', ['title' => 'Temporary project creation fixture', 'status' => 'draft']);
    $assert($created->get_status() === 201, 'REST project creation: ' . wp_json_encode($created->get_data()));
    $fixtures['created'] = $created->get_data()['id'];
    $assert($request('POST', '/wp/v2/projekt/' . $fixtures['created'], ['title' => 'Saved project draft'])->get_status() === 200, 'REST project update');
    $assert($request('POST', '/wp/v2/projekt/' . $fixtures['created'], ['status' => 'publish'])->get_status() === 403, 'REST publication denied');
    $assert($request('DELETE', '/wp/v2/projekt/' . $fixtures['created'])->get_status() === 403, 'REST deletion denied');
    foreach (['posts', 'pages', 'veranstaltung', 'ausstellung'] as $route) {
        $assert($request('POST', '/wp/v2/' . $route, ['title' => 'Must not be created'])->get_status() === 403, 'REST creation denied: ' . $route);
    }
    $assert($request('POST', '/wp/v2/pages/' . $other, ['title' => 'Must not change'])->get_status() === 403, 'REST page edit denied');

    $document = iss_editorial_get_empty_document('projekt');
    $document['sections'] = [['type' => 'kapitel', 'title' => 'UAT chapter', 'body' => '<p>Rich editor test.</p>']];
    foreach (['draft', 'publish', 'created'] as $key) {
        $id = $fixtures[$key];
        $autosave = iss_editorial_save_draft($id, 'projekt', $document, true);
        $assert(!is_wp_error($autosave), 'JSON autosave: ' . $key);
        $status = get_post_status($id);
        $_POST = wp_slash(['post_ID' => $id, 'post_type' => 'projekt', 'post_title' => 'UAT saved rich editor', 'post_status' => $status, 'original_post_status' => $status, 'iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => ['projekt' => ['enabled' => '1', 'base' => iss_editorial_saved_token($id, 'projekt'), 'draft_token' => $autosave['token'], 'document' => wp_json_encode($document)]]]);
        $saved = edit_post($_POST);
        $assert($saved === $id, 'Native project save: ' . $key);
        $assert(iss_editorial_get_document($id, 'projekt')['sections'][0]['title'] === 'UAT chapter', 'JSON persists on reload: ' . $key);
        $assert(get_post_status($id) === $status, 'Status preserved on edit: ' . $key);
    }
    $assert(is_wp_error(iss_editorial_save_draft($other, 'landing', $document, true)), 'JSON autosave denied for another content type');
    $assert(apply_filters('login_redirect', admin_url(), '', $tester) === admin_url('edit.php?post_type=projekt'), 'Login goes directly to Projects');
    foreach (['administrator', 'editor', 'author', 'contributor'] as $role) {
        $assert(get_role($role)->has_cap('create_projekte'), 'Existing role keeps creation rights: ' . $role);
    }
    $assert(!get_role('contributor')->has_cap('publish_projekte'), 'Contributor is not elevated');
    $assert(iss_core_apply_capability_migration(true)['changes'] === [], 'Capability migration is idempotent');
} finally {
    remove_filter('wp_die_handler', $die_handler, PHP_INT_MAX);
    $_POST = [];
    wp_set_current_user($admin->ID);
    if ($archive_set) {
        $assert(iss_wf_import_get_archivset_service()->delete_set($archive_set), 'Remove own archive selection fixture');
    }
    foreach ($fixtures as $id) {
        $sets = iss_content_editorial_sets_service()->get_links_for_context(get_post_type($id), $id);
        wp_delete_post($id, true);
        foreach ($sets as $link) {
            $assert(iss_content_editorial_sets_service()->delete_set_if_safe((int) $link['set_id']), 'Remove own empty fixture Set');
        }
    }
    $_POST = $original_post;
    wp_set_current_user($original_user);
    $assert($before === $snapshot(), 'Existing content and metadata remain unchanged');
}
WP_CLI::success("Project UAT: $checks checks passed; temporary fixtures removed.");
