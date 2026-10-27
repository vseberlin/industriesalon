<?php
/** wp eval-file: opaque trash, strict restores, revisions and read-only CLI batches. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
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
$old_user = get_current_user_id();
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$id = 0; $sets = []; $tables = []; $guard = null; $editor_filter = null;
$trash = [
    ['type' => 'retired_kind', 'title' => 'Retained "title"', 'body' => '<old-tag data-key="x">Exact recovery</old-tag>', 'custom' => ['enabled' => false, 'nullable' => null, 'list' => [1, 'two']], 'deleted_at' => '2020-01-01', 'original_index' => 2],
    ['type' => 'dynamic_slot', 'slot_key' => 'retired_slot', 'treatment' => 'retired.treatment', 'media_refs' => ['old_shape' => ['must' => 'survive']]],
    ['type' => 'kapitel', 'title' => 'Known type with unknown fields', 'retired_field' => ['nested' => 'Keep this']],
];
try {
    foreach (['event.gespraech' => 'typografisch', 'event.festival' => 'buehne'] as $entity => $skin) {
        $raw_event = ['schema_version' => 1, 'entity_key' => $entity, 'sections' => []];
        $editor_event = apply_filters('iss_editorial_editor_document', $raw_event, 0, 'veranstaltung');
        $assert($editor_event['skin'] === $skin && $editor_event['entity_key'] === $entity, 'Editor derives a valid skin through the existing legacy entity mapping without rewriting the entity');
        $assert(!is_wp_error(iss_editorial_validate_document($editor_event, 'veranstaltung')), 'Legacy and current event editor copies remain saveable');
    }
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        $doc = iss_editorial_get_empty_document($slug);
        $doc['deleted_sections'] = $trash;
        $valid = iss_editorial_validate_document($doc, $slug);
        $assert(!is_wp_error($valid), "$slug: retired trash does not freeze a valid document");
        $assert($valid['deleted_sections'] === $trash, "$slug: all trash fields and values retained");
        $assert(iss_editorial_hydrate_document_previews($doc)['deleted_sections'] === $trash, "$slug: editor hydration does not reinterpret trash");
        foreach ($trash as $section) {
            $restored = $doc; $restored['sections'][] = $section;
            $assert(is_wp_error(iss_editorial_validate_document($restored, $slug)), "$slug: invalid restored active section remains blocked");
        }
        foreach (['invalid', [false], [['body' => 'Missing type']], [['type' => ['invalid']]]] as $bad_trash) {
            $bad = $doc; $bad['deleted_sections'] = $bad_trash;
            $assert(is_wp_error(iss_editorial_validate_document($bad, $slug)), "$slug: malformed trash envelope fails closed");
        }
    }

    $id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => 'M8 validation fixture ' . wp_generate_uuid4()], true);
    $assert(is_int($id) && $id > 0, 'Create own fixture');
    $sets = array_column(iss_content_editorial_sets_service()->get_links_for_context('projekt', $id), 'set_id');
    $doc = iss_editorial_get_empty_document('projekt');
    $doc['sections'] = [['type' => 'kapitel', 'title' => 'Active', 'body' => '<p>Safe active content</p>']];
    $doc['deleted_sections'] = $trash;
    $assert(iss_editorial_save_document($id, 'projekt', $doc, false, true), 'Save valid active content with opaque trash');
    $key = iss_editorial_get_document_meta_key('projekt');
    $raw = get_post_meta($id, $key, true);
    $assert(iss_editorial_get_document($id, 'projekt')['deleted_sections'] === $trash, 'Canonical read retains opaque trash');
    $assert(iss_editorial_get_read_model($id, 'projekt')['deleted_sections'] === $trash, 'Read model retains opaque trash without resolving it');
    $assert(iss_editorial_get_editor_document($id, 'projekt')['deleted_sections'] === $trash, 'Editor upgrade leaves trash intact');
    $draft = iss_editorial_save_draft($id, 'projekt', $doc, true);
    $assert(!is_wp_error($draft) && $draft['document']['deleted_sections'] === $trash, 'Autosave/recovery preserves exact opaque values');
    $assert(get_post_meta($id, $key, true) === $raw, 'Autosave leaves canonical JSON unchanged');
    $before_invalid = $snapshot();
    $restored = $doc; $restored['sections'][] = $trash[0];
    $assert(!iss_editorial_save_document($id, 'projekt', $restored), 'Unregistered active restore cannot be saved');
    $assert($snapshot() === $before_invalid, 'Rejected restore changes no content, autosave or revision');
    $changed = $doc; $changed['sections'][0]['title'] = 'Later edit';
    $assert(iss_editorial_save_document($id, 'projekt', $changed), 'Unrelated active edit can save');
    $revision = 0;
    foreach (wp_get_post_revisions($id) as $post) {
        if (!wp_is_post_autosave($post->ID) && get_metadata_raw('post', $post->ID, $key, true) === $raw) { $revision = $post->ID; break; }
    }
    $assert($revision > 0 && (int) wp_restore_post_revision($revision) === $id, 'Restore a native revision containing retired trash');
    $assert(get_post_meta($id, $key, true) === $raw, 'Native revision restores exact JSON including opaque trash');
    $known = $doc['sections'][0]; $known['title'] = 'Restorable';
    $restored = $doc; $restored['deleted_sections'][] = $known;
    $restored['sections'][] = array_pop($restored['deleted_sections']);
    $assert(iss_editorial_save_document($id, 'projekt', $restored), 'Known valid section restores normally');

    wp_delete_post($id, true); $id = 0;
    foreach ($sets as $set_id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe((int) $set_id), 'Remove own empty Set');
        $wpdb->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    $sets = [];
    $assert($snapshot() === $before, 'Existing posts, revisions and metadata unchanged after owner tests');

    // A connection-local database view tests keyset pagination and failures without real invalid content.
    $seed = $wpdb->get_row("SELECT * FROM {$wpdb->posts} WHERE post_type='projekt' LIMIT 1", ARRAY_A);
    foreach ([$wpdb->posts, $wpdb->postmeta] as $table) {
        $schema = preg_replace('/^CREATE TABLE /', 'CREATE TEMPORARY TABLE ', $wpdb->get_row("SHOW CREATE TABLE `$table`", ARRAY_N)[1], 1);
        $assert($wpdb->query($schema) !== false, 'Create temporary audit fixture table'); $tables[] = $table;
    }
    $insert = static function (int $id, string $status, string $raw, string $type = 'projekt') use ($wpdb, $seed, $key, $assert): void {
        $assert($wpdb->insert($wpdb->posts, array_merge($seed, ['ID' => $id, 'post_type' => $type, 'post_status' => $status, 'post_parent' => 0])) !== false, 'Insert temporary post');
        $assert($wpdb->insert($wpdb->postmeta, ['post_id' => $id, 'meta_key' => $key, 'meta_value' => $raw]) !== false, 'Insert temporary raw document');
    };
    for ($n = 0; $n < 105; ++$n) { $insert(1900000500 + $n, 'draft', iss_editorial_encode_document($doc)); }
    $insert(1900000605, 'trash', '{broken');
    $insert(1900000606, 'private', iss_editorial_encode_document(['schema_version' => 999, 'sections' => []]));
    $insert(1900000607, 'publish', iss_editorial_encode_document(['schema_version' => 3, 'sections' => [$trash[0]]]));
    $insert(1900000608, 'auto-draft', '');
    $insert(1900000609, 'inherit', '{broken', 'revision');
    foreach ([1900000610 => iss_editorial_encode_document($doc), 1900000611 => '{broken'] as $draft_id => $raw) {
        $insert($draft_id, 'inherit', $raw, 'revision');
        $wpdb->update($wpdb->posts, ['post_parent' => 1900000500, 'post_name' => '1900000500-autosave-v1', 'post_author' => $draft_id], ['ID' => $draft_id]);
    }
    $guard = static function (string $sql): string {
        if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP|TRUNCATE)\b/i', $sql)) { throw new RuntimeException('Read-only CLI attempted a write'); }
        return $sql;
    };
    add_filter('query', $guard);
    $audit_before = $snapshot();
    $result = WP_CLI::runcommand('iss-editorial validate-all --format=json', ['launch' => false, 'return' => 'all', 'exit_error' => false]);
    $report = json_decode($result->stdout, true);
    $assert($result->return_code === 1, 'Invalid documents make the CLI gate fail');
    $assert($report['checked'] === 109 && $report['invalid'] === 4, 'CLI crosses a 100-row boundary and excludes revisions');
    $assert(array_column($report['errors'], 'post_id') === [1900000605, 1900000606, 1900000607, 1900000608], 'CLI identifies trash/private/published/auto-draft documents, including disabled JSON');
    $assert(array_column($report['errors'], 'code') === ['editorial_invalid_json', 'editorial_invalid_schema', 'editorial_unknown_section', 'editorial_invalid_json'], 'CLI validates raw JSON before lossy normalization');
    $extended = WP_CLI::runcommand('iss-editorial validate-all --include-drafts --editor-view --format=json', ['launch' => false, 'return' => 'all', 'exit_error' => false]);
    $extended_report = json_decode($extended->stdout, true);
    $assert($extended->return_code === 1 && $extended_report['invalid'] === 5, 'Extended gate includes invalid private recovery');
    $assert($extended_report['drafts']['checked'] === 2 && $extended_report['drafts']['invalid'] === 1 && $extended_report['drafts']['missing_base'] === 2, 'Extended gate counts private drafts without treating ordinary revisions as drafts');
    $editor_filter = static function (array $document, int $post_id): array {
        if ($post_id === 1900000501) { $document['unexpected_upgrade_field'] = 'Detect editor-only incompatibility'; }
        return $document;
    };
    add_filter('iss_editorial_editor_document', $editor_filter, 99, 2);
    $extended = WP_CLI::runcommand('iss-editorial validate-all --include-drafts --editor-view --format=json', ['launch' => false, 'return' => 'all', 'exit_error' => false]);
    $extended_report = json_decode($extended->stdout, true);
    $assert($extended->return_code === 1 && $extended_report['invalid'] === 6 && in_array(1900000501, array_column($extended_report['errors'], 'post_id'), true), 'Editor-copy hydration is validated as a separate compatibility boundary');
    remove_filter('iss_editorial_editor_document', $editor_filter, 99); $editor_filter = null;
    $table = WP_CLI::runcommand('iss-editorial validate-all', ['launch' => false, 'return' => 'all', 'exit_error' => false]);
    $assert($table->return_code === 1 && str_contains($table->stdout, '1900000605') && str_contains($table->stdout, 'editorial_invalid_json'), 'Default table output identifies invalid documents and fails the gate');
    $assert($snapshot() === $audit_before, 'Failed audit has no database side effects');
    remove_filter('query', $guard);
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID BETWEEN 1900000605 AND 1900000608");
    add_filter('query', $guard);
    $result = WP_CLI::runcommand('iss-editorial validate-all --format=json', ['launch' => false, 'return' => 'all', 'exit_error' => false]);
    $report = json_decode($result->stdout, true);
    $assert($result->return_code === 0 && $report['checked'] === 105 && $report['invalid'] === 0, 'Valid canonical documents with retired trash pass the gate');
    WP_CLI::log("PASS: $checks validation/trash/CLI checks.");
} finally {
    if ($editor_filter) { remove_filter('iss_editorial_editor_document', $editor_filter, 99); }
    if ($guard) { remove_filter('query', $guard); }
    foreach (array_reverse($tables) as $table) { $wpdb->query("DROP TEMPORARY TABLE IF EXISTS `$table`"); }
    foreach (range(1900000500, 1900000611) as $fixture_id) { clean_post_cache($fixture_id); }
    if ($id) { wp_delete_post($id, true); }
    foreach ($sets as $set_id) {
        $service = iss_content_editorial_sets_service();
        $service->delete_set_if_safe((int) $set_id);
        $wpdb->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    wp_set_current_user($old_user);
    $assert($snapshot() === $before, 'All existing posts/revisions/metadata preserved after cleanup');
    WP_CLI::log('PASS: fixtures removed; existing content and private drafts unchanged.');
}
