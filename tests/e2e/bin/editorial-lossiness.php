<?php
/** wp eval-file: loss rejection, intentional conversions and recoverable native saves. */
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
$before = $snapshot(); $old_user = get_current_user_id(); $old_post = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$id = 0; $sets = []; $fault = null; $fields = null;
$die = static fn() => static function ($message): void { throw new RuntimeException(wp_strip_all_tags((string) $message)); };
add_filter('wp_die_handler', $die, PHP_INT_MAX);
$reject = static function (array $doc, string $slug, string $path) use ($assert): void {
    $result = iss_editorial_validate_document($doc, $slug);
    $assert(is_wp_error($result) && $result->get_error_code() === 'editorial_lossy_normalization', "$slug rejects loss at $path: " . (is_wp_error($result) ? $result->get_error_message() : 'accepted'));
    $assert($result->get_error_data()['path'] === $path && str_contains($result->get_error_message(), $path), 'Loss error identifies the exact input path');
};
$accept = static function (array $doc, string $slug) use ($assert): array {
    $result = iss_editorial_validate_document($doc, $slug);
    $assert(!is_wp_error($result), "$slug accepts intentional normalization: " . (is_wp_error($result) ? $result->get_error_message() : ''));
    return $result;
};
try {
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        foreach ($format['supported_versions'] as $version) {
            $doc = iss_editorial_get_empty_document($slug); $doc['schema_version'] = $version;
            $doc['sections'] = [['type' => isset($format['sections']['fliesstext']) ? 'fliesstext' : 'intro', 'title' => 'Kept title', 'body' => '<p>Kept prose<br />and a second line.</p>']];
            $accept($doc, $slug);
            foreach (['title' => '<b>Unapproved formatting</b>', 'kicker' => "First line\nSecond line", 'anchor' => 'Changed Anchor'] as $field => $value) {
                $bad = $doc; $bad['sections'][0][$field] = $value;
                $reject($bad, $slug, 'sections[0].' . $field);
            }
            $doc['sections'][0]['title'] = "  Kept\t  title  ";
            $valid = $accept($doc, $slug);
            $assert($valid['sections'][0]['title'] === 'Kept title', 'Horizontal whitespace is intentionally normalized');
            $doc['deleted_sections'] = [['type' => 'retired', 'title' => "<old>Exact\nrecovery</old>", 'custom' => ['items' => [false, null, ['nested' => 'Keep']]]]];
            $assert($accept($doc, $slug)['deleted_sections'] === $doc['deleted_sections'], 'Opaque trash is never rewritten by loss checks');
        }
    }
    $doc = iss_editorial_get_empty_document('projekt');
    $ref = ['kind' => 'media', 'source' => 'wp-media', 'id' => 123];
    $doc['sections'] = [['type' => 'text_bild_reihe', 'items' => [['label' => 'Row', 'text' => 'Text', 'media_refs' => [$ref, $ref]]]]];
    $reject($doc, 'projekt', 'sections[0].items[0].media_refs');
    array_pop($doc['sections'][0]['items'][0]['media_refs']);
    $assert($accept($doc, 'projekt')['sections'][0]['items'][0]['media_refs'][0]['id'] === '123', 'Integer reference IDs convert without value loss');
    foreach (['label' => "Line\nTwo", 'unregistered' => 'Keep nested content'] as $field => $value) {
        $bad = $doc; $bad['sections'][0]['items'][0][$field] = $value;
        $reject($bad, 'projekt', 'sections[0].items[0].' . $field);
    }
    $bad = $doc; $bad['sections'][0]['items'][0]['media_refs'][0]['unregistered'] = ['nested' => 'Keep reference data'];
    $reject($bad, 'projekt', 'sections[0].items[0].media_refs[0].unregistered');
    $bad = $doc; $bad['sections'][0]['items'][0]['media_refs'][0]['id'] = '<b>123</b>';
    $reject($bad, 'projekt', 'sections[0].items[0].media_refs[0].id');
    $bad = $doc; $bad['sections'] = [['type' => 'facts', 'facts' => [['value' => '<b>42</b>', 'label' => 'Meaning']]]];
    $reject($bad, 'projekt', 'sections[0].facts[0].value');
    $bad = $doc; $bad['sections'] = [['type' => 'fliesstext', 'body' => '<p>Text<script>alert(1)</script></p>']];
    foreach ([1, 2, 3] as $version) {
        $bad['schema_version'] = $version;
        $assert(is_wp_error(iss_editorial_validate_document($bad, 'projekt')), 'Unsafe markup remains rejected for version ' . $version);
        $assert(!str_contains(iss_editorial_sanitize_document($bad, 'projekt')['sections'][0]['body'], '<script'), 'Read-side security sanitization remains active');
    }
    $place = iss_editorial_get_empty_document('place');
    $place['sections'] = [['type' => 'epoche', 'title' => 'Epoch', 'era_key' => 'ddr', 'function_key' => 'industrial', 'contribution_question' => str_repeat('ü', 241)]];
    $reject($place, 'place', 'sections[0].contribution_question');
    $place['sections'][0]['contribution_question'] = str_repeat('ü', 240);
    $place['sections'][0]['start_year'] = '1950'; $place['sections'][0]['is_current'] = '1';
    $valid = $accept($place, 'place');
    $assert(mb_strlen($valid['sections'][0]['contribution_question']) === 240 && $valid['sections'][0]['start_year'] === 1950 && $valid['sections'][0]['is_current'] === true, 'Boundary-length question, numeric year and boolean values survive');
    $place['sections'][0]['is_current'] = 'false';
    $reject($place, 'place', 'sections[0].is_current');
    $event = iss_editorial_get_empty_document('veranstaltung');
    foreach (iss_content_model_veranstaltung_legacy_entity_key_map() as $alias => $current) {
        $event['entity_key'] = $alias;
        $assert($accept($event, 'veranstaltung')['entity_key'] === $current, 'Registered event alias maps through its owner');
    }
    $event['hero_title'] = '<b>Opening</b>';
    $reject($event, 'veranstaltung', 'hero_title');
    $alias = iss_editorial_get_empty_document('projekt'); $alias['sections'] = [['type' => 'image_wall', 'title' => 'Gallery']];
    $assert($accept($alias, 'projekt')['sections'][0]['type'] === 'galerie', 'Registered section alias remains intentional');
    $page = get_posts(['post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1])[0];
    $links = $doc; $links['sections'] = [['type' => 'material', 'links' => [['label' => 'Page', 'page_id' => $page->ID, 'url' => 'https://old.example/path']]]];
    $assert($accept($links, 'projekt')['sections'][0]['links'][0]['url'] === get_permalink($page), 'Native page selection refreshes its cached permalink');
    $links['sections'][0]['links'][0]['page_id'] = 0;
    $accept($links, 'projekt');
    $album = iss_editorial_get_empty_document('publication');
    $album['sections'] = [['type' => 'photoalbum', 'album_source' => ['kind' => 'archive_set', 'id' => '123', 'title' => 'Album'], 'sheets' => [
        ['source_kind' => 'wp_media', 'attachment_id' => '124', 'label' => 'Second', 'position' => '2', 'visible' => '1'],
        ['source_kind' => 'wp_media', 'source_id' => '123', 'label' => 'First', 'position' => 1],
    ]]];
    $valid = $accept($album, 'publication');
    $assert(array_column($valid['sections'][0]['sheets'], 'label') === ['First', 'Second'] && $valid['sections'][0]['album_source']['set_id'] === '123', 'Album aliases and declared positions retain the same content');
    $album['sections'][0]['sheets'][0]['label'] = '<b>Second</b>';
    $reject($album, 'publication', 'sections[0].sheets[1].label');
    // Future owner fields are protected even when nobody extends a field-name allowlist.
    $fault = static function (array $normal): array { $normal['sections'][0]['title'] = 'Shortened'; return $normal; };
    add_filter('iss_editorial_sanitized_document', $fault);
    $forward = $doc; $forward['sections'][0]['title'] = 'Must survive';
    $reject($forward, 'projekt', 'sections[0].title');
    remove_filter('iss_editorial_sanitized_document', $fault); $fault = null;
    $fields = static fn(array $allowed): array => array_merge($allowed, ['future_field']);
    add_filter('iss_editorial_document_fields', $fields);
    $forward = $doc; $forward['future_field'] = ['nested' => ['Must survive']];
    $reject($forward, 'projekt', 'future_field');
    remove_filter('iss_editorial_document_fields', $fields); $fields = null;

    $id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => 'H3 disposable lossiness check', 'post_author' => $admin->ID], true);
    $assert(is_int($id) && $id > 0, 'Create own draft project');
    $sets = array_column(iss_content_editorial_sets_service()->get_links_for_context('projekt', $id), 'set_id');
    $doc = iss_editorial_get_empty_document('projekt');
    $doc['sections'] = [['type' => 'kapitel', 'title' => 'Saved chapter', 'body' => '<p>Saved prose.</p>']];
    $assert(iss_editorial_save_document($id, 'projekt', $doc, false, true), 'Initialize valid own canonical document');
    $base = iss_editorial_saved_token($id, 'projekt');
    $bad = $doc; $bad['sections'][0]['title'] = '<b>Keep my exact draft</b>';
    $draft = iss_editorial_save_draft($id, 'projekt', $bad, true);
    $assert(!is_wp_error($draft) && $draft['document'] === $bad, 'Private recovery keeps rejected input exactly');
    $unchanged = $snapshot();
    $assert(!iss_editorial_save_document($id, 'projekt', $bad), 'Direct API refuses a lossy canonical write');
    $assert($snapshot() === $unchanged, 'Rejected API write creates no revision or metadata changes');
    $_POST = wp_slash(['post_ID' => $id, 'iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => ['projekt' => ['document' => iss_editorial_encode_document($bad), 'enabled' => '1', 'base' => $base, 'draft_token' => $draft['token']]]]);
    $rejected = '';
    try { wp_update_post(['ID' => $id, 'post_title' => 'Must not replace title', 'post_excerpt' => 'Must not replace summary']); }
    catch (RuntimeException $error) { $rejected = $error->getMessage(); }
    $assert(str_contains($rejected, 'sections[0].title'), 'Native save reports the affected field path');
    $assert($snapshot() === $unchanged && iss_editorial_get_draft($id, 'projekt')['document'] === $bad, 'Native rejection preserves title, document, all history and private recovery');
    $good = $bad; $good['sections'][0]['title'] = 'Keep my corrected draft';
    $_POST = wp_slash(['post_ID' => $id, 'iss_editorial_nonce' => wp_create_nonce('iss_editorial_save_document'), 'iss_editorial' => ['projekt' => ['document' => iss_editorial_encode_document($good), 'enabled' => '1', 'base' => $base, 'draft_token' => $draft['token']]]]);
    $assert(wp_update_post(['ID' => $id, 'post_title' => 'Corrected H3 fixture'], true) === $id, 'Native retry saves corrected content');
    $assert(iss_editorial_get_document($id, 'projekt')['sections'][0]['title'] === 'Keep my corrected draft' && iss_editorial_get_draft($id, 'projekt') === [], 'Corrected native save clears recovery through the existing lifecycle');
    WP_CLI::log("PASS: $checks lossiness checks.");
} finally {
    if ($fault) { remove_filter('iss_editorial_sanitized_document', $fault); }
    if ($fields) { remove_filter('iss_editorial_document_fields', $fields); }
    remove_filter('wp_die_handler', $die, PHP_INT_MAX);
    $_POST = []; wp_set_current_user($admin->ID);
    if ($id) { wp_delete_post($id, true); }
    foreach ($sets as $set_id) {
        $service = iss_content_editorial_sets_service();
        if (!$service->delete_set_if_safe((int) $set_id)) { throw new RuntimeException('Own Set cleanup failed'); }
        $wpdb->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']);
    }
    $_POST = $old_post; wp_set_current_user($old_user);
    $assert($snapshot() === $before, 'Existing posts, revision history and metadata unchanged after cleanup');
    WP_CLI::log('PASS: existing content/history unchanged; disposable records removed.');
}
