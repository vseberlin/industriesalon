<?php
/**
 * Create the unpublished Futura review pilot through WordPress/editorial APIs.
 * wp eval-file .../2026-10-15-futura-pilot.php dry-run|apply|verify|rollback --use-include
 * Pair with ops/uploads/2026-10-15-futura-pilot.{tar.gz,json}; extract into uploads first.
 * Private before-image: iss_backup_20261015_futura_pilot (non-autoload option).
 * Never overwrites an existing project. Reapply verifies the installed draft.
 * Guarded rollback trashes the unchanged pilot; its reusable logo and receipt remain.
 * A partial apply retains its receipt for inspection; never delete it and retry blindly.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$mode = $args[0] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'verify', 'rollback'], true)) { WP_CLI::error('Use dry-run, apply, verify or rollback.'); }
$input = json_decode(file_get_contents(__DIR__ . '/2026-10-15-futura-pilot.json'), true);
$manifest = json_decode(file_get_contents(dirname(__DIR__) . '/uploads/2026-10-15-futura-pilot.json'), true);
$backup_key = 'iss_backup_20261015_futura_pilot';
$backup = get_option($backup_key);
$published = static function (): array {
    $result = [];
    foreach (get_posts(['post_type' => 'projekt', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC']) as $post) {
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
$verify = static function () use (&$backup, $published, $core): void {
    $logo_file = is_array($backup) ? get_attached_file((int) ($backup['attachment_id'] ?? 0)) : false;
    if (!is_array($backup) || empty($backup['post_id']) || empty($backup['installed'])
        || $published() !== $backup['published']
        || $core($backup['post_id']) !== $backup['core']
        || iss_editorial_get_document($backup['post_id'], 'projekt') !== $backup['installed']
        || !iss_editorial_document_is_enabled($backup['post_id'], 'projekt')
        || !$logo_file || !is_readable($logo_file) || hash_file('sha256', $logo_file) !== $backup['asset_hash']) {
        WP_CLI::error('Pilot, published baseline or asset differs; inspect the receipt, no overwrite.');
    }
    WP_CLI::success('Draft ' . $backup['post_id'] . ' verified; all published projects preserved.');
};
if ($mode === 'verify') { $verify(); return; }
if ($mode === 'rollback') {
    $verify();
    if (!wp_trash_post($backup['post_id'])) { WP_CLI::error('Could not trash the pilot.'); }
    WP_CLI::success('Pilot moved to Trash. Logo and receipt retained; published projects untouched.');
    return;
}
if (is_array($backup)) { $verify(); return; }
if (get_page_by_path($input['slug'], OBJECT, 'projekt')) { WP_CLI::error('Pilot slug already exists; no overwrite.'); }
$asset = $manifest['files'][0];
$file = wp_upload_dir()['basedir'] . '/' . $asset['path'];
if (!is_readable($file) || hash_file('sha256', $file) !== $asset['sha256']) { WP_CLI::error('Paired logo payload missing or checksum mismatch.'); }
$candidate = iss_editorial_validate_document($input['document'], 'projekt');
if (is_wp_error($candidate)) { WP_CLI::error($candidate->get_error_message()); }
if ($mode === 'dry-run') { WP_CLI::success('Ready: one draft, one supplied logo; no published project changes.'); return; }
$backup = ['published' => $published(), 'post_id' => 0, 'attachment_id' => 0, 'prior_pilot' => null, 'asset_hash' => $asset['sha256']];
if (!add_option($backup_key, $backup, '', false) || get_option($backup_key) !== $backup) { WP_CLI::error('Private before-image could not be verified.'); }
$id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => $input['title'], 'post_name' => $input['slug'], 'post_excerpt' => $input['excerpt']], true);
if (is_wp_error($id)) { WP_CLI::error($id->get_error_message()); }
$backup['post_id'] = $id;
update_option($backup_key, $backup, false);
$attachment_id = wp_insert_attachment(['post_title' => 'Futura – Signet', 'post_mime_type' => 'image/png', 'post_status' => 'inherit', 'post_content' => 'Projektlogo aus der Futura-Übergabe vom 15. Oktober 2026.'], $file, $id, true);
if (is_wp_error($attachment_id)) { WP_CLI::error($attachment_id->get_error_message()); }
$backup['attachment_id'] = $attachment_id;
update_option($backup_key, $backup, false);
require_once ABSPATH . 'wp-admin/includes/image.php';
wp_update_attachment_metadata($attachment_id, wp_generate_attachment_metadata($attachment_id, $file));
update_post_meta($attachment_id, '_wp_attachment_image_alt', 'Futura');
$candidate['features']['identity']['media_refs'] = [iss_editorial_sanitize_reference(['kind' => 'media', 'source' => 'wp-media', 'id' => (string) $attachment_id, 'label' => 'Futura – Signet'])];
$candidate = iss_editorial_validate_document($candidate, 'projekt');
if (is_wp_error($candidate)) { WP_CLI::error($candidate->get_error_message()); }
$backup['installed'] = $candidate;
$backup['core'] = $core($id);
update_option($backup_key, $backup, false);
if (!iss_editorial_save_document($id, 'projekt', $candidate, false, true)) { WP_CLI::error('Editorial write failed; receipt retained.'); }
$verify();
