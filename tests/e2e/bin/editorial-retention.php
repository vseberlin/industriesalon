<?php
/** wp eval-file: native retention, private drafts and failure safety on disposable records. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/user.php';
require_once ABSPATH . 'wp-admin/includes/revision.php';
global $wpdb;
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$snapshot = static fn(): string => hash('sha256', serialize([
    $wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A),
    $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_key NOT IN ('_edit_lock','_iss_editorial_lock_session') ORDER BY meta_id", ARRAY_A),
]));
$before = $snapshot();
$old_user = get_current_user_id(); $old_post = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$ids = []; $sets = []; $cache_ids = []; $other_user = 0; $fault = null; $override = null;
$old_errors = $wpdb->suppress_errors(true);
$state = static function (int $id): array {
    $post = get_post($id);
    $parent = $post->post_type === 'revision' ? get_post($post->post_parent) : $post;
    $meta = [];
    foreach (wp_post_revision_meta_keys($parent->post_type) as $key) { $meta[$key] = get_post_meta($id, $key); }
    return [$post->post_title, $post->post_excerpt, $post->post_content, $meta];
};
$history = static fn(int $id): array => array_filter(wp_get_post_revisions($id, ['check_enabled' => false]), static fn($post) => !wp_is_post_autosave($post));
$record = static fn(int $id): string => hash('sha256', serialize([
    $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID=%d", $id), ARRAY_A),
    $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id=%d ORDER BY meta_id", $id), ARRAY_A),
]));
try {
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        $fake_id = 1900000700 + count($cache_ids); $cache_ids[] = $fake_id;
        $assert(get_post($fake_id) === null, 'Cache fixture ID is unused');
        $post = new WP_Post((object) ['ID' => $fake_id, 'post_type' => $format['post_types'][0], 'post_name' => $slug === 'landing' ? 'schoneweide' : 'retention-fixture', 'post_status' => 'draft']);
        wp_cache_set($fake_id, $post, 'posts');
        wp_cache_set($fake_id, [], 'post_meta');
        $assert(apply_filters('wp_revisions_to_keep', -1, $post) === -1, "$slug without JSON retains native policy");
        wp_cache_set($fake_id, [iss_editorial_get_document_meta_key($slug) => ['{legacy'], iss_editorial_get_enabled_meta_key($slug) => ['0']], 'post_meta');
        foreach ([-1 => 30, 100 => 30, 30 => 30, 5 => 5, 0 => 0] as $input => $expected) {
            $assert(apply_filters('wp_revisions_to_keep', $input, $post) === $expected, "$slug respects native limit $input, including disabled legacy JSON");
        }
    }
    foreach ($cache_ids as $fake_id) { clean_post_cache($fake_id); }
    $cache_ids = [];

    $id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => 'M5 retention fixture', 'post_author' => $admin->ID], true);
    $assert(is_int($id) && $id > 0, 'Create own project'); $ids[] = $id;
    $sets = array_column(iss_content_editorial_sets_service()->get_links_for_context('projekt', $id), 'set_id');
    $doc = iss_editorial_get_empty_document('projekt');
    $doc['sections'] = [['type' => 'kapitel', 'title' => 'State 0', 'body' => '<p>Retained content</p>']];
    $doc['deleted_sections'] = [['type' => 'retired_kind', 'title' => 'Exact trash', 'extension' => ['keep' => true]]];
    $assert(iss_editorial_save_document($id, 'projekt', $doc, false, true), 'Initialize own JSON');
    $assert(wp_revisions_to_keep(get_post($id)) === 30, 'Runtime default is 30');
    $override = static fn(): int => 7;
    add_filter('wp_projekt_revisions_to_keep', $override);
    $assert(wp_revisions_to_keep(get_post($id)) === 7, 'Native per-post-type override retains precedence');
    remove_filter('wp_projekt_revisions_to_keep', $override); $override = null;
    $other_user = wp_insert_user(['user_login' => 'retention-test-' . wp_generate_uuid4(), 'user_pass' => wp_generate_password(), 'role' => 'editor']);
    $assert(is_int($other_user), 'Create own second author without notification');
    $draft_records = [];
    foreach ([$admin->ID, $other_user] as $author) {
        wp_set_current_user($author);
        $private = $doc; $private['sections'][0]['title'] = 'Private ' . $author;
        $draft = iss_editorial_save_draft($id, 'projekt', $private, true);
        $assert(!is_wp_error($draft), 'Create per-author private autosave');
        $draft_records[$draft['id']] = $record($draft['id']);
    }
    wp_set_current_user($admin->ID);
    $known = [];
    for ($n = 1; $n <= 36; ++$n) {
        $old = $state($id); $known[hash('sha256', serialize($old))] = true;
        $doc['sections'][0]['title'] = 'State ' . $n;
        $assert(iss_editorial_save_document($id, 'projekt', $doc), 'API update succeeds at retention boundary');
        $normal = $history($id);
        $assert(count($normal) <= 30, 'Normal history remains bounded');
        $assert(count(array_filter($normal, static fn($post) => $state($post->ID) === $old)) > 0, 'Immediate prior state remains restorable');
    }
    $assert(count($history($id)) === 30, 'Thirty normal revisions remain in addition to autosaves');
    foreach ($history($id) as $revision) { $assert(isset($known[hash('sha256', serialize($state($revision->ID)))]), 'Every retained title/document snapshot is coherent'); }
    foreach ($draft_records as $draft_id => $hash) { $assert($record($draft_id) === $hash, 'Retention preserves each private autosave exactly'); }
    $old = $state($id); $doc['sections'][0]['title'] = 'Native form at cap';
    $own_draft = iss_editorial_get_draft($id, 'projekt');
    $_POST = wp_slash(['post_ID' => $id, 'iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => ['projekt' => ['document' => iss_editorial_encode_document($doc), 'enabled' => '1', 'base' => iss_editorial_saved_token($id, 'projekt'), 'draft_token' => $own_draft['token']]]]);
    $assert(wp_update_post(['ID' => $id, 'post_title' => 'Native title at cap', 'post_excerpt' => 'Native summary'], true) === $id, 'Native form updates identity and document at the cap');
    $_POST = [];
    $assert(count($history($id)) === 30, 'Native pre/post snapshots share the same normal-revision allowance');
    $assert(count(array_filter($history($id), static fn($post) => $state($post->ID) === $old)) > 0, 'Native form retains its complete pre-update state');
    $current = $state($id);
    $assert(count(array_filter($history($id), static fn($post) => $state($post->ID) === $current)) > 0, 'Native form retains its complete current state');
    $oldest = array_last($history($id)); $expected = $state($oldest->ID);
    $diff = wp_get_revision_ui_diff(get_post($id), false, $oldest);
    $document_diff = array_values(array_filter($diff, static fn(array $field): bool => $field['id'] === 'iss-editorial-document'));
    $assert(count($document_diff) === 1 && str_contains($document_diff[0]['diff'], 'Retained content'), 'Oldest revision comparison renders its document against an empty starting point');
    $assert((int) wp_restore_post_revision($oldest->ID) === $id, 'Oldest retained revision restores');
    $assert($state($id) === $expected, 'Oldest restore retains title, JSON, enabled state, skin and trash');
    $assert(!isset($GLOBALS['iss_editorial_pruned_revision_meta'][$oldest->ID]), 'Restore releases its temporary source snapshot');

    // If the source cannot be read before pruning, core must keep it for the restore.
    $oldest = array_last($history($id)); $expected = $state($oldest->ID); $hits = 0;
    $fault = static function (string $sql) use ($wpdb, $oldest, &$hits): string {
        if (!$hits && str_contains($sql, "FROM $wpdb->postmeta") && str_contains($sql, 'post_id IN (' . $oldest->ID . ')') && str_contains($sql, 'meta_id ASC')) {
            ++$hits; return 'SELECT iss_editorial_retention_read_failure';
        }
        return $sql;
    };
    add_filter('query', $fault);
    $restored = wp_restore_post_revision($oldest->ID);
    remove_filter('query', $fault); $fault = null;
    $assert($hits === 1 && (int) $restored === $id && get_post($oldest->ID) instanceof WP_Post, 'Failed pre-prune read retains its source revision');
    $assert($state($id) === $expected, 'Restore retries the intact source through the existing owner boundary');

    // A failed capture must preserve a full history, including a pre-existing excess.
    $override = static fn(): int => -1;
    add_filter('wp_projekt_revisions_to_keep', $override);
    for ($n = 40; $n < 45; ++$n) { $doc['sections'][0]['title'] = 'Excess ' . $n; $assert(iss_editorial_save_document($id, 'projekt', $doc), 'Seed over-limit own history'); }
    remove_filter('wp_projekt_revisions_to_keep', $override); $override = null;
    $assert(count($history($id)) > 30, 'Fixture models a backlog awaiting next successful save');
    $untouched = $snapshot();
    $assert(iss_editorial_save_document($id, 'projekt', $doc), 'Unchanged canonical save succeeds');
    $assert($snapshot() === $untouched, 'Unchanged save does not purge an existing backlog');
    foreach (['insert', 'meta'] as $failure) {
        $untouched = $snapshot(); $hits = 0;
        $fault = static function (string $sql) use ($wpdb, $id, $failure, &$hits): string {
            $matches = $failure === 'insert'
                ? str_starts_with($sql, "INSERT INTO `$wpdb->posts`") && str_contains($sql, "'revision'") && str_contains($sql, (string) $id)
                : str_starts_with($sql, "INSERT INTO `$wpdb->postmeta`") && str_contains($sql, iss_editorial_get_document_meta_key('projekt'));
            if (!$hits && $matches) { ++$hits; return 'SELECT iss_editorial_retention_injected_failure'; }
            return $sql;
        };
        add_filter('query', $fault);
        $doc['sections'][0]['title'] = 'Must fail ' . $failure;
        $saved = iss_editorial_save_document($id, 'projekt', $doc);
        remove_filter('query', $fault); $fault = null;
        $assert($hits === 1 && !$saved, 'Injected capture ' . $failure . ' fails');
        $assert($snapshot() === $untouched, 'Failed capture preserves canonical data, every older revision and private drafts');
    }
    $doc['sections'][0]['title'] = 'After failure';
    $assert(iss_editorial_save_document($id, 'projekt', $doc) && count($history($id)) === 30, 'Next successful save prunes the backlog through native retention');
    foreach ($draft_records as $draft_id => $hash) {
        // A successful native form save explicitly clears its author's draft; retention does not.
        if ((int) get_post_field('post_author', $draft_id) === $other_user) { $assert($record($draft_id) === $hash, 'Failure and pruning preserve the other author private draft'); }
    }
    $native_id = wp_insert_post(['post_type' => 'post', 'post_status' => 'draft', 'post_title' => 'M5 native fixture'], true);
    $assert(is_int($native_id) && $native_id > 0, 'Create native content without editorial JSON'); $ids[] = $native_id;
    for ($n = 0; $n < 33; ++$n) { wp_update_post(['ID' => $native_id, 'post_title' => 'Native only ' . $n]); }
    $assert(wp_revisions_to_keep(get_post($native_id)) === -1 && count($history($native_id)) > 30, 'Native posts without stored JSON keep the existing unlimited policy');
    WP_CLI::log("PASS: $checks retention checks.");
} finally {
    if ($fault) { remove_filter('query', $fault); }
    if ($override) { remove_filter('wp_projekt_revisions_to_keep', $override); }
    foreach ($cache_ids as $fake_id) { clean_post_cache($fake_id); }
    $_POST = [];
    wp_set_current_user($admin->ID);
    foreach ($ids as $id) { wp_delete_post($id, true); }
    foreach ($sets as $set_id) {
        $service = iss_content_editorial_sets_service();
        if (!$service->delete_set_if_safe((int) $set_id)) { throw new RuntimeException('Own Set cleanup failed'); }
        $wpdb->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    if (is_int($other_user) && $other_user > 0) { wp_delete_user($other_user); }
    $_POST = $old_post; wp_set_current_user($old_user); $wpdb->suppress_errors($old_errors);
    $assert($snapshot() === $before, 'Existing posts, all history and metadata unchanged after cleanup');
    WP_CLI::log('PASS: existing posts/history/metadata unchanged; disposable records removed.');
}
