<?php
/**
 * Register a browser-safe large preview without replacing the original TIFF.
 * wp eval-file ops/migrations/2026-10-14-place-image-preview.php dry-run|apply|verify
 * Paired media: ops/uploads/2026-10-14-place-dossier-preview.tar.gz (uploads-relative).
 * Apply can copy the identical unpacked artifact from ../uploads/place-dossier-20261014.
 * Rollback: restore the attachment metadata through wp_update_attachment_metadata()
 * using option iss_backup_20261014_place_image_preview; retain the TIFF unchanged.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$mode = $args[0] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'verify'], true)) { WP_CLI::error('Use dry-run, apply or verify.'); }
$artifact = __DIR__ . '/../uploads/place-dossier-20261014';
$manifest = json_decode((string) file_get_contents($artifact . '/manifest.json'), true);
if (!is_array($manifest)) { WP_CLI::error('Missing preview manifest.'); }
$matches = get_posts([
    'post_type' => 'attachment', 'post_status' => 'inherit', 'posts_per_page' => 2,
    'meta_key' => '_wp_attached_file', 'meta_value' => $manifest['source'], // phpcs:ignore WordPress.DB.SlowDBQuery -- Bounded migration identity lookup.
]);
if (count($matches) !== 1 || get_post_mime_type($matches[0]->ID) !== 'image/tiff') { WP_CLI::error('Expected exactly one source TIFF attachment.'); }
$id = (int) $matches[0]->ID;
$uploads = wp_get_upload_dir();
$source = $uploads['basedir'] . '/' . $manifest['source'];
$preview = $uploads['basedir'] . '/' . $manifest['preview'];
$input = $artifact . '/' . $manifest['preview'];
if (!is_file($source) || hash_file('sha256', $source) !== $manifest['source_sha256']) { WP_CLI::error('Source TIFF differs from the reviewed original.'); }
$old = wp_get_attachment_metadata($id);
$next = is_array($old) ? $old : [];
$next['width'] = $manifest['width'];
$next['height'] = $manifest['height'];
$next['file'] = $manifest['source'];
$next['sizes']['large'] = [
    'file' => wp_basename($manifest['preview']), 'width' => $manifest['width'],
    'height' => $manifest['height'], 'mime-type' => 'image/jpeg', 'filesize' => $manifest['bytes'],
];
$ready_file = is_file($preview) && hash_file('sha256', $preview) === $manifest['preview_sha256'];
if ($ready_file && $old === $next) { WP_CLI::success('Preview and attachment metadata already verified.'); return; }
if ($mode === 'verify') { WP_CLI::error('Preview or attachment metadata is not ready.'); }
if (is_file($preview) && !$ready_file) { WP_CLI::error('Existing preview differs; no overwrite allowed.'); }
if (!$ready_file && (!is_file($input) || hash_file('sha256', $input) !== $manifest['preview_sha256'])) { WP_CLI::error('Missing or changed paired preview artifact.'); }
if ($mode === 'dry-run') { WP_CLI::success('Reviewed TIFF and JPEG match; one large attachment preview will be registered.'); return; }
$backup_key = 'iss_backup_20261014_place_image_preview';
if (!add_option($backup_key, ['attachment_id' => $id, 'metadata' => $old, 'source_sha256' => $manifest['source_sha256']], '', false)) { WP_CLI::error('Targeted backup already exists; inspect before retry.'); }
if (!$ready_file && (!wp_mkdir_p(dirname($preview)) || !copy($input, $preview))) { WP_CLI::error('Could not install the reviewed JPEG.'); }
if (hash_file('sha256', $preview) !== $manifest['preview_sha256']) { WP_CLI::error('Installed preview differs from the artifact.'); }
wp_update_attachment_metadata($id, $next);
if (wp_get_attachment_metadata($id) !== $next || hash_file('sha256', $source) !== $manifest['source_sha256']) { WP_CLI::error('Attachment verification failed; targeted backup retained.'); }
WP_CLI::success('Registered one JPEG large preview; original TIFF and Place content preserved.');
