<?php
/**
 * Create a native KiezKarte review draft with the existing editorial gestures.
 * wp eval-file .../2026-10-15-kiezkarte.php dry-run|apply|verify|rollback --use-include
 * Deploy matching code and extract the paired ops/uploads archive after checksum verification.
 * Private before-image: iss_backup_20261015_kiezkarte (non-autoload option).
 * Existing project fingerprints, installed draft and media hashes are checked on reapply.
 * Rollback trashes only the unchanged draft. Assets/receipt remain; no destructive cleanup.
 * Partial failures retain the receipt for inspection. Never remove the receipt and retry blindly.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$mode = $args[0] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'verify', 'rollback'], true)) { WP_CLI::error('Use dry-run, apply, verify or rollback.'); }
$input = json_decode(file_get_contents(__DIR__ . '/2026-10-15-kiezkarte.json'), true);
$manifest = json_decode(file_get_contents(dirname(__DIR__) . '/uploads/2026-10-15-kiezkarte.json'), true);
$backup_key = 'iss_backup_20261015_kiezkarte';
$backup = get_option($backup_key);
$existing_projects = static function (int $exclude = 0): array {
    $result = [];
    foreach (get_posts(['post_type' => 'projekt', 'post_status' => ['publish', 'draft', 'private', 'pending', 'future'], 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC']) as $post) {
        if ($post->ID === $exclude) { continue; }
        $meta = get_post_meta($post->ID);
        unset($meta['_edit_lock']);
        $result[$post->ID] = hash('sha256', wp_json_encode([$post->to_array(), $meta]));
    }
    return $result;
};
$core = static function (int $id): array {
    $post = get_post($id);
    return $post ? array_intersect_key($post->to_array(), array_flip(['post_title', 'post_excerpt', 'post_content', 'post_status', 'post_name'])) : [];
};
$verify = static function () use (&$backup, $existing_projects, $core): void {
    if (!is_array($backup) || empty($backup['post_id']) || empty($backup['installed'])
        || $existing_projects($backup['post_id']) !== $backup['existing_projects'] || $core($backup['post_id']) !== $backup['core']
        || iss_editorial_get_document($backup['post_id'], 'projekt') !== $backup['installed']
        || !iss_editorial_document_is_enabled($backup['post_id'], 'projekt')
        || get_post_thumbnail_id($backup['post_id']) !== $backup['featured_image']) {
        WP_CLI::error('Draft or existing project baseline differs; inspect the receipt, no overwrite.');
    }
    foreach ($backup['assets'] as $asset) {
        $file = get_attached_file($asset['id']);
        if (!$file || !is_readable($file) || hash_file('sha256', $file) !== $asset['sha256']) { WP_CLI::error('Media changed or missing.'); }
    }
    WP_CLI::success('Draft ' . $backup['post_id'] . ' verified; ' . count($backup['existing_projects']) . ' existing projects preserved.');
};
if ($mode === 'verify') { $verify(); return; }
if ($mode === 'rollback') {
    $verify();
    if (!wp_trash_post($backup['post_id'])) { WP_CLI::error('Could not trash the draft.'); }
    WP_CLI::success('Draft moved to Trash. Assets and receipt retained.');
    return;
}
if (is_array($backup)) { $verify(); return; }
if (get_page_by_path($input['slug'], OBJECT, 'projekt')) { WP_CLI::error('Draft slug already exists; no overwrite.'); }
$uploads = wp_upload_dir();
foreach ($manifest['files'] as $asset) {
    $file = $uploads['basedir'] . '/' . $asset['path'];
    if (!is_readable($file) || hash_file('sha256', $file) !== $asset['sha256'] || !wp_getimagesize($file)) { WP_CLI::error('Paired asset missing, unreadable or checksum mismatch.'); }
}
// Check the shared feature contract before registering its new attachment.
$preflight = $input['document'];
$preflight['sections'][0]['treatment'] = 'feature.media-text';
$preflight = iss_editorial_validate_document($preflight, 'projekt');
if (is_wp_error($preflight)) { WP_CLI::error($preflight->get_error_message()); }
if ($mode === 'dry-run') { WP_CLI::success('Ready: one draft, 20 partner cards, 21 verified media files. Opening image validation follows attachment registration.'); return; }
$backup = ['existing_projects' => $existing_projects(), 'post_id' => 0, 'assets' => []];
if (!add_option($backup_key, $backup, '', false) || get_option($backup_key) !== $backup) { WP_CLI::error('Private before-image could not be verified.'); }
$id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => $input['title'], 'post_name' => $input['slug'], 'post_excerpt' => $input['excerpt']], true);
if (is_wp_error($id)) { WP_CLI::error($id->get_error_message()); }
$backup['post_id'] = $id;
update_option($backup_key, $backup, false);
require_once ABSPATH . 'wp-admin/includes/image.php';
$candidate = $input['document'];
foreach ($manifest['files'] as $asset) {
    $file = $uploads['basedir'] . '/' . $asset['path'];
    $existing = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 2, 'fields' => 'ids', 'meta_key' => '_wp_attached_file', 'meta_value' => $asset['path']]);
    if (count($existing) > 1) { WP_CLI::error('Ambiguous attachment path; receipt retained.'); }
    $attachment_id = (int) ($existing[0] ?? 0);
    if (!$attachment_id) {
        $attachment_id = wp_insert_attachment(['post_title' => $asset['title'], 'post_excerpt' => $asset['caption'], 'post_mime_type' => $asset['mime'], 'post_status' => 'inherit', 'post_content' => 'Projektmaterial aus ' . $manifest['source_archive'] . ' / ' . $asset['source']], $file, $id, true);
        if (is_wp_error($attachment_id)) { WP_CLI::error($attachment_id->get_error_message()); }
        wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file));
        update_post_meta($attachment_id, '_wp_attachment_image_alt', $asset['alt']);
    }
    $backup['assets'][$asset['key']] = ['id' => $attachment_id, 'sha256' => $asset['sha256']];
    update_option($backup_key, $backup, false);
    WP_CLI::log('Media ready: ' . $asset['key']);
}
foreach ($input['media_map'] as $mapping) {
    $attachment_id = (int) $backup['assets'][$mapping['key']]['id'];
    $reference = iss_editorial_sanitize_reference(['kind' => 'media', 'source' => 'wp-media', 'id' => (string) $attachment_id, 'label' => get_the_title($attachment_id)]);
    if (isset($mapping['item'])) { $candidate['sections'][$mapping['section']]['items'][$mapping['item']]['media_refs'] = [$reference]; }
    else { $candidate['sections'][$mapping['section']]['media_refs'] = [$reference]; }
}
$backup['featured_image'] = (int) $backup['assets'][$input['featured_image']]['id'];
if (!set_post_thumbnail($id, $backup['featured_image'])) { WP_CLI::error('Featured image write failed; receipt retained.'); }
$candidate = iss_editorial_validate_document($candidate, 'projekt');
if (is_wp_error($candidate)) { WP_CLI::error($candidate->get_error_message()); }
$backup['installed'] = $candidate;
$backup['core'] = $core($id);
update_option($backup_key, $backup, false);
if (!iss_editorial_save_document($id, 'projekt', $candidate, false, true)) { WP_CLI::error('Editorial write failed; receipt retained.'); }
$verify();
