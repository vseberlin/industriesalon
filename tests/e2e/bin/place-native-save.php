<?php
/** wp eval-file: native Place saves prepare excerpts without nested writes; own fixtures only. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$old_user = get_current_user_id(); $old_post = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$fixtures = []; $sets = []; $target = 0; $writes = 0; $notifications = 0;
$count = static function (array $data, array $postarr) use (&$target, &$writes): array {
    if ($target > 0 && (int) ($postarr['ID'] ?? 0) === $target && $data['post_type'] !== 'revision') { ++$writes; }
    return $data;
};
$saved = static function (int $id) use (&$target, &$notifications): void { if ($id === $target) { ++$notifications; } };
$die = static fn() => static function ($message): void { throw new RuntimeException('Rejected: ' . wp_strip_all_tags((string) $message)); };
add_filter('wp_insert_post_data', $count, 1, 2);
add_action('iss_editorial_document_saved', $saved);
add_filter('wp_die_handler', $die, PHP_INT_MAX);
$state = static function (int $id): array {
    $post = get_post($id); $parent = $post->post_type === 'revision' ? get_post($post->post_parent) : $post;
    $meta = [];
    foreach (wp_post_revision_meta_keys($parent->post_type) as $key) { $meta[$key] = get_post_meta($id, $key); }
    return ['title' => $post->post_title, 'excerpt' => $post->post_excerpt, 'content' => $post->post_content, 'meta' => $meta];
};
$snapshot = static function (int $id): string {
    $rows = [];
    foreach (array_merge([$id], array_keys(wp_get_post_revisions($id, ['check_enabled' => false]))) as $row_id) {
        $rows[$row_id] = [get_post($row_id)->to_array(), get_post_meta($row_id)];
    }
    return serialize([$rows, iss_register_get_epoch_service()->get_epochs_for_place($id), iss_register_get_place_state_service()->get_states_for_place($id)]);
};
$submit = static function (int $id, array $doc, string $excerpt, array $overrides = [], string $nonce = '') {
    $draft = iss_editorial_get_draft($id, 'place');
    $_POST = wp_slash(['post_ID' => $id, 'iss_editorial_nonce' => $nonce ?: wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => ['place' => array_replace([
        'document' => iss_editorial_encode_document($doc), 'base' => iss_editorial_saved_token($id, 'place'), 'draft_token' => $draft['token'] ?? '', 'enabled' => '1',
    ], $overrides)]]);
    $stack = $GLOBALS['wp_current_filter'];
    try { return wp_update_post(wp_slash(['ID' => $id, 'post_title' => 'Saved native Place fixture', 'post_excerpt' => $excerpt]), true); }
    finally { $_POST = []; $GLOBALS['wp_current_filter'] = $stack; }
};
$baseline = in_array('expect-conflict', $args ?? [], true);
try {
    foreach (['intro', 'gegenwart', 'manual', 'empty', 'clear'] as $case) {
        $_POST = []; $target = 0;
        $id = wp_insert_post(['post_type' => 'register_place', 'post_status' => 'draft', 'post_title' => 'Native Place fixture ' . $case, 'post_excerpt' => $case === 'clear' ? 'Previous manual summary' : '', 'post_author' => $admin->ID], true);
        $assert(!is_wp_error($id) && $id > 0, 'Create own Place'); $fixtures[] = $id;
        foreach (iss_content_editorial_sets_service()->get_links_for_context('register_place', $id) as $link) { $sets[] = (int) $link['set_id']; }
        $doc = iss_editorial_get_empty_document('place');
        $doc['sections'] = [['type' => 'epoche', 'title' => 'Original phase', 'body' => '<p>Preserved history.</p>', 'era_key' => 'weimar', 'function_key' => 'industrial', 'start_year' => 1920]];
        if ($case !== 'empty') { array_unshift($doc['sections'], ['type' => $case === 'gegenwart' ? 'gegenwart' : 'intro', 'body' => '<p>Quoted "summary" with C:\\source and ü.</p>']); }
        $doc['deleted_sections'] = [['type' => 'retired_kind', 'extension' => ['keep' => 'Exact']]];
        $assert(iss_editorial_save_document($id, 'place', $doc, false, true), 'Initialize canonical Place and projection');
        $doc = iss_editorial_get_document($id, 'place'); $original = $state($id);
        $candidate = iss_register_get_public_excerpt_candidate($id);
        $next = $doc; $epoch = count($next['sections']) - 1;
        $next['sections'][$epoch]['title'] = 'Edited phase'; $next['sections'][$epoch]['function_key'] = 'commercial';
        $draft = iss_editorial_save_draft($id, 'place', $next, true, ['title' => 'Saved native Place fixture', 'excerpt' => '']);
        $assert(!is_wp_error($draft), 'Keep the submitted changes in private recovery');
        $old_revisions = array_keys(wp_get_post_revisions($id, ['check_enabled' => false]));
        $target = $id; $writes = 0; $notifications = 0; $error = '';
        $excerpt = $case === 'manual' ? 'Author chosen "summary" C:\\path' : '';
        try { $result = $submit($id, $next, $excerpt); }
        catch (RuntimeException $caught) { $error = $caught->getMessage(); }
        if ($baseline) {
            $assert(str_contains($error, 'Inzwischen wurde eine neue Fassung gespeichert') && $writes === 2, 'Reproduced nested-save edit-version conflict');
            $assert(iss_editorial_get_document($id, 'place') === $doc && iss_editorial_get_draft($id, 'place')['document'] === $next, 'Conflict leaves canonical JSON unchanged and retains recovery');
            WP_CLI::log('BASELINE: empty excerpt causes two parent writes/preflights; nested update rejects the original token.');
            break;
        }
        $assert($error === '' && $result === $id, 'Native blank/manual excerpt save succeeds: ' . $case . ' ' . $error);
        $assert($writes === 1 && $notifications === 1, 'Exactly one parent write and one document save');
        $assert(get_post($id)->post_excerpt === ($excerpt !== '' ? $excerpt : $candidate), 'Existing source/manual excerpt and escaping preserved');
        $assert(iss_editorial_get_document($id, 'place') === $next && iss_editorial_get_draft($id, 'place') === [], 'Native save retains the full document and clears recovery');
        $epochs = iss_register_get_epoch_service()->get_epochs_for_place($id);
        $assert(count($epochs) === 1 && $epochs[0]['phase_name'] === 'Edited phase' && $epochs[0]['function_key'] === 'commercial', 'Canonical save projects the submitted epoch');
        $after = $state($id); $seen_before = false; $seen_after = false;
        foreach (wp_get_post_revisions($id, ['check_enabled' => false]) as $revision) {
            if (wp_is_post_autosave($revision->ID)) { continue; }
            $actual = $state($revision->ID);
            $seen_before = $seen_before || $actual === $original; $seen_after = $seen_after || $actual === $after;
            if (!in_array($revision->ID, $old_revisions, true)) { $assert($actual === $original || $actual === $after, 'No revision mixes old JSON with new identity/excerpt'); }
        }
        $assert($seen_before && $seen_after, 'Both paired before/after snapshots exist');

        if ($case !== 'intro') { continue; }
        $draft = iss_editorial_save_draft($id, 'place', $doc, true);
        $stale_base = iss_editorial_saved_token($id, 'place');
        wp_update_post(['ID' => $id, 'post_title' => 'Concurrent winning title']);
        foreach (['stale-base', 'stale-recovery', 'invalid-document', 'invalid-nonce', 'permission'] as $rejection) {
            $overrides = []; $nonce = ''; $input = $doc;
            if ($rejection === 'stale-base') { $overrides['base'] = $stale_base; }
            if ($rejection === 'stale-recovery') { $overrides['draft_token'] = 'obsolete-draft'; }
            if ($rejection === 'invalid-document') { $input['sections'][$epoch]['era_key'] = ''; }
            if ($rejection === 'invalid-nonce') { $nonce = 'invalid-nonce'; }
            if ($rejection === 'permission') { wp_set_current_user(0); }
            $before_rejection = $snapshot($id); $rejected = false;
            try { $submit($id, $input, '', $overrides, $nonce); }
            catch (RuntimeException $caught) { $rejected = str_starts_with($caught->getMessage(), 'Rejected:'); }
            wp_set_current_user($admin->ID);
            $assert($rejected && $snapshot($id) === $before_rejection, "$rejection stops before parent, history, recovery or projections change");
        }
        $assert(iss_editorial_get_draft($id, 'place')['document'] === $doc, 'All rejected submissions preserve private recovery');
    }
    if (!$baseline) {
        $_POST = []; $target = 0;
        $id = wp_insert_post(['post_type' => 'register_place', 'post_status' => 'draft', 'post_title' => 'Legacy Place excerpt fixture'], true);
        $assert(!is_wp_error($id), 'Create own legacy Place'); $fixtures[] = $id;
        foreach (iss_content_editorial_sets_service()->get_links_for_context('register_place', $id) as $link) { $sets[] = (int) $link['set_id']; }
        wp_update_post(['ID' => $id, 'post_content' => '<p>Legacy current source.</p>']);
        $assert(get_post($id)->post_excerpt === 'Legacy current source.', 'Legacy source fill retains its existing owner path');
        wp_update_post(['ID' => $id, 'post_excerpt' => 'Manual legacy summary']);
        $assert(get_post($id)->post_excerpt === 'Manual legacy summary', 'Legacy manual excerpt remains authoritative');
    }
} finally {
    $_POST = []; wp_set_current_user($admin->ID);
    remove_filter('wp_insert_post_data', $count, 1); remove_action('iss_editorial_document_saved', $saved);
    remove_filter('wp_die_handler', $die, PHP_INT_MAX);
    foreach ($fixtures as $id) {
        $owned = array_merge([$id], array_keys(wp_get_post_revisions($id, ['check_enabled' => false])));
        $assert((bool) wp_delete_post($id, true), 'Remove own Place');
        foreach ($owned as $owned_id) { $assert(!get_post($owned_id) && !get_post_meta($owned_id), 'Own post/history metadata removed'); }
        $assert(!iss_register_get_epoch_service()->get_epochs_for_place($id) && !iss_register_get_place_state_service()->get_states_for_place($id), 'Own projections removed');
    }
    foreach (array_unique($sets) as $set_id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe($set_id), 'Remove own empty Set');
        $GLOBALS['wpdb']->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    $_POST = $old_post; wp_set_current_user($old_user);
}
WP_CLI::success("Place native save: $checks checks; fixtures removed.");
