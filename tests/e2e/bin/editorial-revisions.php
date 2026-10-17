<?php
/** Run with wp eval-file. Only disposable fixtures are written; all are removed. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Read-only preservation proof and targeted fixture faults.
global $wpdb;
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$snapshot = static fn(): string => hash('sha256', serialize($wpdb->get_results("SELECT p.ID,p.post_title,p.post_content,p.post_excerpt,m.meta_key,m.meta_value FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id=p.ID AND m.meta_key NOT IN ('_edit_lock','_iss_editorial_lock_session') WHERE p.post_type <> 'revision' ORDER BY p.ID,m.meta_id", ARRAY_A)));
$before = $snapshot();
$old_user = get_current_user_id();
$old_post = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$id = 0;
$sets = [];
$fault = null;
$old_suppress = $wpdb->suppress_errors(true);
$die = static fn() => static function ($message): void { throw new RuntimeException('Rejected: ' . wp_strip_all_tags((string) $message)); };
add_filter('wp_die_handler', $die, PHP_INT_MAX);
$history = static fn(int $post_id): array => wp_get_post_revisions($post_id, ['check_enabled' => false]);
$state = static function (int $post_id): array {
    $post = get_post($post_id);
    $parent = $post->post_type === 'revision' ? get_post($post->post_parent) : $post;
    $meta = [];
    foreach (wp_post_revision_meta_keys($parent->post_type) as $key) {
        $meta[$key] = get_post_meta($post_id, $key);
    }
    return ['title' => $post->post_title, 'excerpt' => $post->post_excerpt, 'content' => $post->post_content, 'meta' => $meta];
};
$find_revision = static function (int $post_id, array $expected) use ($history, $state): int {
    foreach ($history($post_id) as $revision) {
        if (!wp_is_post_autosave($revision->ID) && $state($revision->ID) === $expected) { return $revision->ID; }
    }
    return 0;
};

try {
    $id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => 'Revision fixture ' . wp_generate_uuid4(), 'post_excerpt' => 'Original summary', 'post_content' => '<!-- wp:paragraph --><p>Native import source.</p><!-- /wp:paragraph -->'], true);
    $assert(!is_wp_error($id) && $id > 0, 'Create fixture');
    foreach (iss_content_editorial_sets_service()->get_links_for_context('projekt', $id) as $link) { $sets[] = (int) $link['set_id']; }
    $doc = iss_editorial_get_empty_document('projekt');
    $doc['sections'] = [
        ['type' => 'kapitel', 'title' => 'Original JSON', 'body' => '<p>Über "quotes" and C:\\source</p>', 'treatment' => 'kapitel.disclosure', 'section_treatment' => 'standard'],
        ['type' => 'zitat', 'quote' => 'Retained voice', 'attribution' => 'Retained source', 'treatment' => 'zitat.source', 'quote_treatment' => 'pull', 'orientation' => 'media-right'],
        ['type' => 'galerie', 'title' => 'Retained sequence', 'treatment' => 'galerie.sequence', 'gallery_layout' => 'wall'],
    ];
    $assert(iss_editorial_save_document($id, 'projekt', $doc, false, true), 'Initialize document and enablement');
    $original = $state($id);
    $next = $doc;
    $next['sections'][0]['title'] = 'API replacement';
    $assert(iss_editorial_save_document($id, 'projekt', $next, false, false), 'API replaces document and enablement');
    $revision = $find_revision($id, $original);
    $assert($revision > 0, 'Direct API replacement captures the complete original state');
    $count = count($history($id));
    $assert(iss_editorial_save_document($id, 'projekt', $next, false, false), 'Unchanged API save succeeds');
    $assert(count($history($id)) === $count, 'Unchanged API save creates no revision');
    $assert(!iss_editorial_save_document($id, 'projekt', '{broken'), 'Invalid document rejected');
    $assert(count($history($id)) === $count, 'Invalid API save creates no revision');
    $assert((int) wp_restore_post_revision($revision) === $id && $state($id) === $original, 'Native restore recovers title, excerpt, JSON, skin and enabled state');

    // Enablement must preserve raw stored JSON rather than revalidate/normalize it.
    $enabled = $state($id);
    $assert(iss_editorial_set_document_enabled($id, 'projekt', false) === true, 'Disable reports success');
    $assert($find_revision($id, $enabled) > 0, 'Enablement-only change has a restorable previous state');
    $disabled = $state($id);
    $count = count($history($id));
    $assert(iss_editorial_set_document_enabled($id, 'projekt', false) === true && count($history($id)) === $count, 'Unchanged enablement creates no revision');
    $assert(iss_editorial_set_document_enabled($id, 'projekt', true) === true, 'Re-enable succeeds');
    $assert($find_revision($id, $disabled) > 0, 'Disabled state is recoverable too');
    $assert(iss_editorial_set_document_enabled($id, 'veranstaltung', false) === false, 'Wrong format cannot write enablement metadata');

    // Exercise the actual CLI import handler, including its --force and enabled-off behavior.
    $before_import = $state($id);
    ob_start();
    iss_editorial_cli_project_import_candidate([], ['post' => (string) $id, 'force' => true]);
    ob_end_clean();
    $assert(!iss_editorial_document_is_enabled($id, 'projekt'), 'Forced CLI import disables its candidate');
    $assert($state($id) !== $before_import, 'Forced CLI import replaces authored JSON');
    $revision = $find_revision($id, $before_import);
    $assert($revision > 0 && (int) wp_restore_post_revision($revision) === $id && $state($id) === $before_import, 'CLI replacement is fully reversible');

    // A form updates native identity before save_post; its revision must not mix versions.
    $before_form = $state($id);
    $old_revisions = array_keys($history($id));
    $_POST = wp_slash(['post_ID' => $id, 'iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => ['projekt' => ['document' => iss_editorial_encode_document($next), 'enabled' => '1', 'base' => iss_editorial_saved_token($id, 'projekt'), 'draft_token' => '']]]);
    $assert(wp_update_post(['ID' => $id, 'post_title' => 'New form title', 'post_excerpt' => 'New form summary'], true) === $id, 'Native form save succeeds');
    $_POST = [];
    $after_form = $state($id);
    $assert($find_revision($id, $before_form) > 0 && $find_revision($id, $after_form) > 0, 'Form retains complete before and after states');
    foreach (array_diff(array_keys($history($id)), $old_revisions) as $revision_id) {
        $actual = $state($revision_id);
        $assert($actual === $before_form || $actual === $after_form, 'No form revision mixes old JSON with a new title/summary');
    }
    $assert(iss_editorial_save_document($id, 'projekt', $doc, false, true), 'Direct API write after a form save succeeds');
    $assert($find_revision($id, $after_form) > 0, 'Form capture cannot suppress a subsequent API revision');
    $count = count($history($id));
    $canonical = $state($id);
    $assert(iss_editorial_save_document($id, 'projekt', $next, true), 'Autosave uses private recovery');
    $draft = iss_editorial_get_draft($id, 'projekt');
    $assert($draft !== [] && count($history($id)) === $count + 1 && $state($id) === $canonical, 'Autosave creates only its recovery revision and leaves canonical state unchanged');
    wp_delete_post_revision($draft['id']);

    // Enablement is independent of whether a legacy document passes today's validator.
    $key = iss_editorial_get_document_meta_key('projekt');
    $raw = " {\n\"schema_version\":999,\"unknown\":\"Preserve exactly\"\n} ";
    update_post_meta($id, $key, wp_slash($raw));
    $assert(iss_editorial_set_document_enabled($id, 'projekt', false) && get_metadata_raw('post', $id, $key, true) === $raw, 'Disable preserves unsupported raw JSON byte-for-byte');
    $assert(iss_editorial_save_document($id, 'projekt', $next, false, true), 'Restore a valid fixture document');

    // Both failed revision insertion and incomplete metadata copying must stop replacement.
    foreach (['insert', 'meta', 'enabled', 'form'] as $failure) {
        foreach ($history($id) as $revision) { wp_delete_post_revision($revision->ID); }
        $unchanged = $state($id);
        $hits = 0;
        $fault = static function (string $sql) use ($wpdb, $id, $failure, &$hits): string {
            $matches = $failure !== 'meta'
                ? str_starts_with($sql, "INSERT INTO `$wpdb->posts`") && str_contains($sql, "'revision'") && str_contains($sql, (string) $id)
                : str_starts_with($sql, "INSERT INTO `$wpdb->postmeta`") && str_contains($sql, iss_editorial_get_document_meta_key('projekt'));
            if ($hits === 0 && $matches) { ++$hits; return 'SELECT iss_editorial_revision_injected_failure'; }
            return $sql;
        };
        add_filter('query', $fault);
        if ($failure === 'form') {
            $_POST = wp_slash(['post_ID' => $id, 'iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => ['projekt' => ['document' => iss_editorial_encode_document($doc), 'enabled' => '1', 'base' => iss_editorial_saved_token($id, 'projekt'), 'draft_token' => '']]]);
            $saved = true;
            try { wp_update_post(['ID' => $id, 'post_title' => 'Must not be written'], true); }
            catch (RuntimeException $error) { $saved = !str_starts_with($error->getMessage(), 'Rejected:'); }
            $_POST = [];
        } else {
            $saved = $failure === 'enabled'
                ? iss_editorial_set_document_enabled($id, 'projekt', false)
                : iss_editorial_save_document($id, 'projekt', $doc, false, false);
        }
        remove_filter('query', $fault);
        $fault = null;
        $assert($hits === 1 && !$saved, 'Failed revision ' . $failure . ' reaches caller');
        $assert($state($id) === $unchanged, 'Failed revision ' . $failure . ' preserves canonical state');
        $assert($history($id) === [], 'Incomplete revision is removed');
    }
} finally {
    if ($fault) { remove_filter('query', $fault); }
    $_POST = [];
    remove_filter('wp_die_handler', $die, PHP_INT_MAX);
    if (is_int($id) && $id > 0) { wp_delete_post($id, true); }
    foreach (array_unique($sets) as $set_id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe($set_id), 'Remove own empty fixture Set');
        $wpdb->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    $_POST = $old_post;
    wp_set_current_user($old_user);
    $wpdb->suppress_errors($old_suppress);
    $assert($snapshot() === $before, 'Existing content and metadata unchanged; fixtures removed');
}
WP_CLI::success("Editorial revision writers: $checks checks passed.");
