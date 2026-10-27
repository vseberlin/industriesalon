<?php
/** wp eval-file: remaining editor contracts, cache ownership and write boundaries. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { if (!$ok) { throw new RuntimeException($message); } ++$checks; };
$original_user = get_current_user_id(); $original_post = $_POST;
$admin = get_users(['role' => 'administrator', 'number' => 1])[0]; wp_set_current_user($admin->ID);
$posts = []; $sets = []; $editor = 0;
try {
    $formats = iss_editorial_get_registered_formats();
    $assert(iss_editorial_uses_integrated_route_stations('fuehrung', 'fuehrung'), 'Route owner declares its capability');
    $assert(!iss_editorial_uses_integrated_route_stations('projekt', 'projekt') && !iss_editorial_uses_integrated_route_stations('fuehrung', 'projekt'), 'Other formats and mismatched post types do not acquire route editing');
    $renamed = static function (array $formats): array { $formats['renamed_route'] = $formats['fuehrung']; $formats['fuehrung']['route_stations_callback'] = null; return $formats; };
    add_filter('iss_editorial_formats', $renamed, 150); iss_editorial_reset_format_cache();
    try {
        $assert(iss_editorial_uses_integrated_route_stations('renamed_route', 'fuehrung') && !iss_editorial_uses_integrated_route_stations('fuehrung', 'fuehrung'), 'Route capability follows the declaration, including an explicit override');
    } finally { remove_filter('iss_editorial_formats', $renamed, 150); iss_editorial_reset_format_cache(); }
    $assert(!empty($formats['fuehrung']['sections']['atlas_map']['editor']['route_stations']), 'Tour map exposes route shortcut');
    $assert(!empty($formats['projekt']['sections']['objektfokus']['editor']['reference_captions']), 'Archive captions declared by vocabulary');
    $assert($formats['place']['sections']['epoche']['editor']['hidden_fields'] === ['contribution_question'], 'Stored Place question is retained without an editing control');
    $assert($formats['place']['sections']['dynamic_slot']['editor']['title_context']['preceding_type'] === 'epoche', 'Place context belongs to its owner');
    $assert(!empty($formats['place']['sections']['material']['editor']['media_hide_images']) && empty($formats['projekt']['sections']['material']['editor']['media_hide_images']), 'Distinct download contracts remain distinct');
    foreach (['iss_editorial_text_profile' => 4, 'iss_editorial_sanitize_body_html' => 4, 'iss_editorial_sanitize_section' => 3] as $function => $arity) {
        $assert((new ReflectionFunction($function))->getNumberOfRequiredParameters() === $arity, "$function requires the version explicitly");
    }
    foreach (["<a href='/safe/'>Single</a>", '<a href=/safe/>Bare</a>', '<a href="HTTP://example.invalid/">Upper</a>'] as $html) {
        $assert(iss_editorial_strip_unsafe_body_hrefs($html) === $html, 'Safe link spelling/quoting is retained');
    }
    foreach (['java&#x09;script:bad()', 'javascript:bad()', 'ftp://example.invalid/', 'data:text/html,bad'] as $href) {
        $assert(wp_kses_post(iss_editorial_strip_unsafe_body_hrefs('<a href="' . $href . '">Keep text</a>')) === '<a>Keep text</a>', 'Rejected protocols remove only href');
    }
    $attachment = wp_insert_attachment(['post_title' => 'Audit cache fixture', 'post_mime_type' => 'image/jpeg', 'post_status' => 'inherit']); $posts[] = $attachment;
    update_post_meta($attachment, '_wp_attached_file', 'audit-cache-fixture.jpg');
    update_post_meta($attachment, '_wp_attachment_metadata', ['width' => 640, 'height' => 360]);
    $reference = ['kind' => 'media', 'source' => 'wp-media', 'id' => (string) $attachment, 'label' => 'Author caption', 'thumbnail' => 'https://example.invalid/stale.jpg', 'width' => '7', 'height' => '9', 'mime' => 'image/obsolete'];
    $fresh = iss_editorial_hydrate_reference_preview($reference);
    $assert($fresh['label'] === 'Author caption' && $fresh['width'] === '640' && $fresh['height'] === '360' && $fresh['mime'] === 'image/jpeg' && $fresh['thumbnail'] !== $reference['thumbnail'], 'Transport refreshes derived preview while preserving authored label');
    $doc = iss_editorial_get_empty_document('projekt');
    $doc['sections'] = [['type' => 'kapitel', 'title' => 'Main', 'media_refs' => [$reference]], ['type' => 'text_bild_reihe', 'items' => [['label' => 'Nested', 'media_refs' => [$reference]]]]];
    $deleted = ['type' => 'kapitel', 'title' => 'Restore me', 'body' => '<p>Old text</p>', 'deleted_at' => '2026-10-17T12:00:00Z', 'original_index' => 0];
    $opaque = ['type' => 'retired_kind', 'media_refs' => [$reference], 'extension' => '<iframe>Opaque</iframe>'];
    $doc['deleted_sections'] = [$deleted, $opaque];
    $valid = iss_editorial_validate_document($doc, 'projekt');
    $assert(!is_wp_error($valid), 'Derived cache removal is accepted representation cleanup');
    foreach ([$valid['sections'][0]['media_refs'][0], $valid['sections'][1]['items'][0]['media_refs'][0]] as $ref) {
        $assert(!array_intersect(['thumbnail', 'width', 'height', 'mime'], array_keys($ref)) && $ref['label'] === 'Author caption', 'Only derived fields leave active/nested references');
    }
    $assert($valid['deleted_sections'] === $doc['deleted_sections'], 'Opaque trash retains original preview bytes');
    $identity = ['features' => ['identity' => ['media_refs' => [$reference]]]];
    $assert(!isset(iss_editorial_strip_document_preview_cache($identity)['features']['identity']['media_refs'][0]['thumbnail']), 'Identity preview cache is transport-only too');
    $album = ['sections' => [['sheets' => [['source_kind' => 'wp_media', 'source_id' => (string) $attachment, 'caption' => 'Album caption', 'thumbnail' => 'https://example.invalid/stale.jpg']]]], 'deleted_sections' => [$opaque]];
    $album_transport = iss_editorial_hydrate_document_previews($album);
    $assert($album_transport['sections'][0]['sheets'][0]['thumbnail'] === $fresh['thumbnail'] && $album_transport['sections'][0]['sheets'][0]['caption'] === 'Album caption', 'Album previews refresh through the same owner without changing captions');
    $album_clean = iss_editorial_strip_document_preview_cache($album_transport);
    $assert(!isset($album_clean['sections'][0]['sheets'][0]['thumbnail']) && $album_clean['deleted_sections'] === [$opaque], 'Album cache leaves storage while opaque trash stays exact');
    $id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => 'Audit completion fixture', 'post_author' => $admin->ID]); $posts[] = $id;
    $sets = array_merge($sets, array_column(iss_content_editorial_sets_service()->get_links_for_context('projekt', $id), 'set_id'));
    $assert(iss_editorial_save_document($id, 'projekt', $doc, false, true), 'Save clean canonical reference');
    $assert(iss_editorial_get_document($id, 'projekt') === $valid, 'Canonical save strips caches without changing authored data');
    $draft = iss_editorial_save_draft($id, 'projekt', $doc, true);
    $assert(!is_wp_error($draft) && !isset($draft['document']['sections'][0]['media_refs'][0]['thumbnail']) && $draft['document']['deleted_sections'] === $doc['deleted_sections'], 'Private recovery strips active caches and retains trash');
    wp_delete_post_revision($draft['id']);
    $editor = wp_insert_user(['user_login' => 'audit_editor_' . wp_generate_password(8, false), 'user_pass' => wp_generate_password(32), 'role' => 'editor']);
    $assert(!is_wp_error($editor), 'Disposable ordinary editor created');
    wp_set_current_user($editor);
    $user = wp_get_current_user(); foreach (['edit_posts', 'edit_others_posts', 'edit_published_posts'] as $cap) { $user->add_cap(get_post_type_object('projekt')->cap->$cap); }
    $user->add_cap(get_post_type_object('projekt')->cap->delete_others_posts, false);
    $assert(current_user_can('edit_post', $id) && !current_user_can('delete_post', $id) && !current_user_can('manage_options'), 'Fixture editor can edit but cannot delete this post');
    $purged = $valid; $purged['deleted_sections'] = [];
    $assert(!iss_editorial_save_document($id, 'projekt', $purged) && is_wp_error(iss_editorial_save_draft($id, 'projekt', $purged, true)), 'Canonical and private purge both enforce server permission');
    $restored = $valid; $restored['deleted_sections'] = [$opaque]; $restored['sections'][] = $deleted;
    $assert(!is_wp_error(iss_editorial_save_draft($id, 'projekt', $restored, true)), 'Ordinary editor may restore a valid section');
    $restored['sections'][2]['restored_from'] = hash('sha256', iss_editorial_encode_document($deleted));
    $restored['sections'][2]['title'] = 'Edited immediately after restoration';
    $assert(!is_wp_error(iss_editorial_save_draft($id, 'projekt', $restored, true)) && iss_editorial_save_document($id, 'projekt', $restored), 'Restored identity permits immediate edits before a save');
    $assert(!isset(iss_editorial_get_document($id, 'projekt')['sections'][2]['restored_from']), 'Transient restore identity never becomes canonical content');
    $canonical = get_post_meta($id, iss_editorial_get_document_meta_key('projekt'), true);
    $_POST = ['post_ID' => $id + 100000, 'iss_editorial_nonce' => 'wrong', 'iss_editorial' => ['projekt' => ['document' => '{}']]];
    $data = ['post_type' => 'projekt', 'post_title' => 'Nested title'];
    $assert(iss_editorial_validate_post_update($data, ['ID' => $id]) === $data, 'Nested same-format update bypasses another post submission before nonce validation');
    iss_editorial_save_meta_box($id);
    $assert(get_post_meta($id, iss_editorial_get_document_meta_key('projekt'), true) === $canonical, 'Nested save cannot copy another post document');
    $_POST = [];
    wp_set_current_user($admin->ID);
    $assert(iss_editorial_save_document($id, 'projekt', $purged), 'Administrator may explicitly purge');
    foreach (['iss_editorial_revision_limit', 'iss_editorial_protect_private_recovery', 'iss_editorial_validate_embedded_preview', 'iss_editorial_enqueue_preview_assets'] as $callback) { $assert(is_callable($callback), 'Core hook has a removable named callback'); }
    $assert(function_exists('iss_editorial_cli_project_import_candidate') && str_contains((new ReflectionFunction('iss_editorial_cli_project_import_candidate'))->getFileName(), '/iss-content/'), 'Legacy CLI command now belongs to the content owner');
    WP_CLI::success("$checks audit completion checks.");
} finally {
    $_POST = []; wp_set_current_user($admin->ID);
    foreach (array_reverse($posts) as $post_id) { if (get_post_type($post_id) === 'attachment') { wp_delete_attachment($post_id, true); } else { wp_delete_post($post_id, true); } }
    foreach ($sets as $set_id) { $service = iss_content_editorial_sets_service(); if (!$service->delete_set_if_safe((int) $set_id)) { throw new RuntimeException('Fixture Set cleanup failed'); } $GLOBALS['wpdb']->delete($service->get_audit_table_name(), ['set_id' => $set_id], ['%d']); }
    if (is_int($editor) && $editor > 0) { require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($editor); }
    $_POST = $original_post; wp_set_current_user($original_user);
}
