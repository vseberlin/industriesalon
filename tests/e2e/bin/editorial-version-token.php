<?php
/** wp eval-file: content-scoped edit versions on disposable records; expect-conflict reproduces M7. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/post.php';
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$old_user = get_current_user_id(); $old_post = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$fixtures = []; $sets = []; $registered = []; $other_user = 0; $baseline = in_array('expect-conflict', $args ?? [], true);
$die = static fn() => static function ($message): void { throw new RuntimeException('Rejected: ' . wp_strip_all_tags((string) $message)); };
add_filter('wp_die_handler', $die, PHP_INT_MAX);
$submit = static function (int $id, string $slug, array $document, string $base, string $draft_token, array $owner = [], ?int $native_order = null): int {
    $_POST = wp_slash(['post_ID' => $id, 'iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => [$slug => [
        'document' => iss_editorial_encode_document($document), 'enabled' => '1', 'base' => $base, 'draft_token' => $draft_token,
    ]]]);
    if ($owner) {
        $_POST['iss_content_model_meta_nonce'] = wp_create_nonce('iss_content_model_save_meta');
        $_POST['iss_content_model'] = $owner;
    }
    if ($native_order !== null) { $_POST['menu_order'] = (string) $native_order; }
    $stack = $GLOBALS['wp_current_filter'];
    try { return wp_update_post(array_merge(['ID' => $id, 'post_title' => 'Saved token fixture'], $native_order !== null ? ['menu_order' => $native_order] : []), true); }
    finally { $_POST = []; $GLOBALS['wp_current_filter'] = $stack; }
};
$snapshot = static function (int $id): string {
    $rows = [];
    foreach (array_merge([$id], array_keys(wp_get_post_revisions($id, ['check_enabled' => false]))) as $row_id) {
        $rows[$row_id] = [get_post($row_id)->to_array(), get_post_meta($row_id)];
    }
    return serialize($rows);
};
try {
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        $_POST = [];
        $type = $format['post_types'][0];
        $id = wp_insert_post(['post_type' => $type, 'post_status' => 'draft', 'post_title' => 'Token fixture ' . $slug, 'post_name' => $slug === 'landing' ? 'about' : 'token-fixture-' . $slug, 'post_excerpt' => 'Preserved summary', 'post_author' => $admin->ID], true);
        $assert(is_int($id) && $id > 0, 'Create own ' . $slug); $fixtures[] = $id;
        foreach (iss_content_editorial_sets_service()->get_links_for_context($type, $id) as $link) { $sets[] = (int) $link['set_id']; }
        $assert((iss_editorial_get_format_for_post($id)['slug'] ?? '') === $slug, 'Correct format eligibility: ' . $slug);
        $document = iss_editorial_get_empty_document($slug);
        $document['deleted_sections'] = [['type' => 'retired_token_fixture', 'opaque' => ['keep' => 'Exact']]];
        if ($slug === 'veranstaltung') { $document['entity_key'] = 'event.general'; }
        $assert(iss_editorial_save_document($id, $slug, $document, false, true), 'Seed canonical ' . $slug);
        $document = iss_editorial_get_document($id, $slug);
        $base = iss_editorial_saved_token($id, $slug);
        $next = $document; $next['deleted_sections'][0]['opaque']['keep'] = 'Private changed value';
        $draft = iss_editorial_save_draft($id, $slug, $next, true);
        $assert(!is_wp_error($draft), 'Seed private recovery');
        $key = '_iss_token_unrelated_fixture';
        register_post_meta($type, $key, ['type' => 'string', 'single' => true, 'revisions_enabled' => true]); $registered[$type] = $key;
        update_post_meta($id, $key, 'Background metadata');
        $meta_base = iss_editorial_saved_token($id, $slug);
        $modified_before = get_post($id)->post_modified_gmt;
        // Deterministic different timestamp; still run the normal post update and hooks.
        $clock = static function (array $data, array $postarr) use ($id): array {
            if ((int) ($postarr['ID'] ?? 0) === $id) { $data['post_modified_gmt'] = '2001-01-01 00:00:00'; }
            return $data;
        };
        add_filter('wp_insert_post_data', $clock, 98, 2);
        try { wp_update_post(['ID' => $id, 'menu_order' => 37]); }
        finally { remove_filter('wp_insert_post_data', $clock, 98); }
        $assert(get_post($id)->post_modified_gmt !== $modified_before && (int) get_post($id)->menu_order === 37, 'Unrelated post update really changes order and timestamp');
        if ($baseline) {
            $assert($meta_base !== $base && iss_editorial_saved_token($id, $slug) !== $meta_base, 'Reproduced both unrelated revision-meta and timestamp invalidation');
            $error = iss_editorial_check_edit_version($id, $slug, $base, $draft['token']);
            $assert(is_wp_error($error) && $error->get_error_code() === 'editorial_conflict', 'Reproduced false conflict with unchanged editor content');
            $assert(iss_editorial_get_draft($id, $slug) === $draft, 'False conflict retains private recovery');
            break;
        }
        $assert($meta_base === $base && iss_editorial_saved_token($id, $slug) === $base, 'Unrelated revision meta, order and timestamp do not change the token: ' . $slug);
        $assert(iss_editorial_check_edit_version($id, $slug, $base, $draft['token']) === true, 'Unrelated writes allow the existing editor and recovery');
        $assert(iss_editorial_get_draft($id, $slug) === $draft, 'Unrelated writes do not rewrite recovery');
        $owner = $slug === 'projekt' ? ['menu_order' => '0', 'menu_order_base' => '0'] : [];
        $assert($submit($id, $slug, $next, $base, $draft['token'], $owner) === $id, 'Native save after unrelated writes succeeds');
        $assert((int) get_post($id)->menu_order === 37 && get_post_meta($id, $key, true) === 'Background metadata', 'Native document save retains unrelated values');
        $assert(iss_editorial_get_document($id, $slug) === $next && iss_editorial_get_draft($id, $slug) === [], 'Native save persists exact content and clears recovery');
        $copied = false;
        foreach (wp_get_post_revisions($id) as $revision) {
            $copied = $copied || get_post_meta($revision->ID, $key, true) === 'Background metadata';
        }
        $assert($copied, 'Unrelated metadata stays revisioned even though it is not a token input');

        if ($slug === 'projekt') {
            $base = iss_editorial_saved_token($id, $slug);
            $draft = iss_editorial_save_draft($id, $slug, $document, true);
            $before = $snapshot($id); $rejected = false;
            try { $submit($id, $slug, $document, $base, $draft['token'], ['menu_order' => '19', 'menu_order_base' => '0']); }
            catch (RuntimeException $caught) { $rejected = str_contains($caught->getMessage(), 'Projektreihenfolge'); }
            $assert($rejected && $snapshot($id) === $before, 'Competing intentional order edit rejects before parent, revisions or recovery change');
            $assert($submit($id, $slug, $next, $base, $draft['token'], ['menu_order' => '37', 'menu_order_base' => '0']) === $id, 'Convergent order edits are accepted');
            $base = iss_editorial_saved_token($id, $slug);
            $assert($submit($id, $slug, $next, $base, '', ['menu_order' => '19', 'menu_order_base' => '37']) === $id && (int) get_post($id)->menu_order === 19, 'Fresh intentional order edit remains supported');
            $other_user = wp_insert_user(['user_login' => 'token-order-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'iss_project_uat']);
            $assert(is_int($other_user), 'Create disposable restricted editor');
            wp_set_current_user($other_user);
            $assert(current_user_can('edit_post', $id) && !current_user_can('manage_options'), 'Exercise the existing non-admin native order contract');
            $base = iss_editorial_saved_token($id, $slug);
            $assert($submit($id, $slug, $next, $base, '', ['menu_order_base' => '0'], 0) === $id && (int) get_post($id)->menu_order === 19, 'Untouched native order preserves the later list value for non-admins');
            $assert($submit($id, $slug, $next, $base, '', ['menu_order_base' => '19'], 7) === $id && (int) get_post($id)->menu_order === 7, 'Fresh native order edit keeps its existing capability');
            $before = $snapshot($id); $rejected = false;
            try { $submit($id, $slug, $next, $base, '', ['menu_order_base' => '19'], 3); }
            catch (RuntimeException $caught) { $rejected = str_contains($caught->getMessage(), 'Projektreihenfolge'); }
            $assert($rejected && $snapshot($id) === $before, 'Competing native order edit rejects without parent/history changes');
            wp_set_current_user($admin->ID);
        }

        $changes = [
            'title' => static fn() => wp_update_post(['ID' => $id, 'post_title' => 'Concurrent title']),
            'excerpt' => static fn() => wp_update_post(['ID' => $id, 'post_excerpt' => 'Concurrent summary']),
            'document' => static fn() => iss_editorial_save_document($id, $slug, $document),
        ];
        if (!$format['always_enabled']) { $changes['enabled'] = static fn() => iss_editorial_set_document_enabled($id, $slug, false); }
        if (iss_editorial_get_skin_meta_key($slug) !== '') {
            $changes['skin'] = static fn() => update_post_meta($id, iss_editorial_get_skin_meta_key($slug), 'token-fixture-skin');
        }
        if ($slug === 'veranstaltung') { $changes['structure'] = static fn() => update_post_meta($id, '_iss_entity_key', 'event.festival'); }
        foreach ($changes as $label => $change) {
            $base = iss_editorial_saved_token($id, $slug);
            $draft = iss_editorial_save_draft($id, $slug, $next, true);
            $change();
            $error = iss_editorial_check_edit_version($id, $slug, $base, $draft['token']);
            $assert(is_wp_error($error) && $error->get_error_code() === 'editorial_conflict', "$slug: concurrent $label rejects stale editor");
            $before = $snapshot($id); $rejected = false;
            try { $submit($id, $slug, $next, $base, $draft['token']); }
            catch (RuntimeException $caught) { $rejected = str_starts_with($caught->getMessage(), 'Rejected:'); }
            $assert($rejected && $snapshot($id) === $before, "$slug: rejected $label save preserves parent, history and recovery");
        }
        $base = iss_editorial_saved_token($id, $slug);
        $first = iss_editorial_save_draft($id, $slug, $document, true);
        $second = iss_editorial_save_draft($id, $slug, $next, true);
        $error = iss_editorial_check_edit_version($id, $slug, $base, $first['token']);
        $assert(is_wp_error($error) && $error->get_error_code() === 'editorial_draft_conflict', 'Competing recovery token still rejects stale tab');
        $assert(iss_editorial_check_edit_version($id, $slug, $base, $second['token']) === true, 'Current recovery token remains accepted');
        // Old deployment tokens remain recoverable data, but cannot authorize a write.
        update_metadata('post', $second['id'], '_iss_editorial_draft_base', 'old-deployment-token');
        $legacy = iss_editorial_get_draft($id, $slug);
        $assert($legacy['document'] === $next && $legacy['base'] === 'old-deployment-token', 'Existing recovery remains readable without migration');
        $assert(iss_editorial_check_edit_version($id, $slug, $base, $legacy['token']) === true, 'Reopened editor can continue an existing recovery');
        $error = iss_editorial_check_edit_version($id, $slug, 'old-deployment-token', $legacy['token']);
        $assert(is_wp_error($error) && $error->get_error_code() === 'editorial_conflict', 'Old open tabs fail closed until reload');
        unregister_post_meta($type, $key); unset($registered[$type]);
    }
} finally {
    $_POST = []; wp_set_current_user($admin->ID); remove_filter('wp_die_handler', $die, PHP_INT_MAX);
    foreach ($fixtures as $id) {
        $owned = array_merge([$id], array_keys(wp_get_post_revisions($id, ['check_enabled' => false])));
        $assert((bool) wp_delete_post($id, true), 'Remove own post');
        foreach ($owned as $owned_id) { $assert(!get_post($owned_id) && !get_post_meta($owned_id), 'Own history and metadata removed'); }
    }
    foreach ($registered as $type => $key) { unregister_post_meta($type, $key); }
    if (is_int($other_user) && $other_user > 0) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        $assert(wp_delete_user($other_user), 'Remove disposable restricted editor');
    }
    foreach (array_unique($sets) as $set_id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe($set_id), 'Remove own empty Set');
        $GLOBALS['wpdb']->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    $_POST = $old_post; wp_set_current_user($old_user);
}
WP_CLI::success("Editorial version token: $checks checks; fixtures removed.");
