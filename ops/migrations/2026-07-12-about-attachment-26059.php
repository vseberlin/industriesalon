<?php
/**
 * Restore the image attachment required by the About landing migration.
 *
 * Extract the paired uploads artifact before running:
 * wp eval-file ops/migrations/2026-07-12-about-attachment-26059.php --allow-root
 *
 * Rollback: wp delete 26059 --force --allow-root
 */

if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit(1);
}

$attachment_id = 26059;
$parent_id = 13123;
$relative_file = '2026/06/2024-04-06_Fuehrung_Wilhelminenhofstrasse_Industriesalon.webp';
$uploads = wp_upload_dir();
$absolute_file = trailingslashit($uploads['basedir']) . $relative_file;

if (get_post_type($parent_id) !== 'page') {
    WP_CLI::error('Expected parent page 13123.');
}

if (!is_file($absolute_file)) {
    WP_CLI::error('Missing paired upload file: ' . $absolute_file);
}

$existing_type = get_post_type($attachment_id);
if ($existing_type && $existing_type !== 'attachment') {
    WP_CLI::error('Post ID 26059 is already used by ' . $existing_type . '.');
}

if (!$existing_type) {
    $inserted_id = wp_insert_attachment([
        'import_id' => $attachment_id,
        'post_author' => 1,
        'post_date' => '2026-06-04 16:57:56',
        'post_date_gmt' => '2026-06-04 14:57:56',
        'post_title' => '2024-04-06_Fuehrung_Wilhelminenhofstrasse_Industriesalon',
        'post_status' => 'inherit',
        'comment_status' => 'open',
        'ping_status' => 'closed',
        'post_name' => '2024-04-06_fuehrung_wilhelminenhofstrasse_industriesalon',
        'post_parent' => $parent_id,
        'post_mime_type' => 'image/webp',
        'guid' => trailingslashit($uploads['baseurl']) . $relative_file,
    ], $absolute_file, $parent_id, true);

    if (is_wp_error($inserted_id)) {
        WP_CLI::error($inserted_id->get_error_message());
    }

    if ((int) $inserted_id !== $attachment_id) {
        WP_CLI::error('Expected attachment ID 26059, received ' . (int) $inserted_id . '.');
    }
}

$metadata = [
    'width' => 1270,
    'height' => 847,
    'file' => $relative_file,
    'filesize' => 438266,
    'sizes' => [
        'medium' => [
            'file' => '2024-04-06_Fuehrung_Wilhelminenhofstrasse_Industriesalon-300x200.webp',
            'width' => 300,
            'height' => 200,
            'mime-type' => 'image/webp',
            'filesize' => 99312,
        ],
        'large' => [
            'file' => '2024-04-06_Fuehrung_Wilhelminenhofstrasse_Industriesalon-1024x683.webp',
            'width' => 1024,
            'height' => 683,
            'mime-type' => 'image/webp',
            'filesize' => 265216,
        ],
        'thumbnail' => [
            'file' => '2024-04-06_Fuehrung_Wilhelminenhofstrasse_Industriesalon-150x150.webp',
            'width' => 150,
            'height' => 150,
            'mime-type' => 'image/webp',
            'filesize' => 87830,
        ],
        'medium_large' => [
            'file' => '2024-04-06_Fuehrung_Wilhelminenhofstrasse_Industriesalon-768x512.webp',
            'width' => 768,
            'height' => 512,
            'mime-type' => 'image/webp',
            'filesize' => 189854,
        ],
    ],
    'image_meta' => [
        'aperture' => '0',
        'credit' => '',
        'camera' => '',
        'caption' => '',
        'created_timestamp' => '0',
        'copyright' => '',
        'focal_length' => '0',
        'iso' => '0',
        'shutter_speed' => '0',
        'title' => '',
        'orientation' => '0',
        'keywords' => [],
        'alt' => '',
    ],
];

update_attached_file($attachment_id, $absolute_file);
wp_update_attachment_metadata($attachment_id, $metadata);

if (
    get_post_type($attachment_id) !== 'attachment'
    || get_post_meta($attachment_id, '_wp_attached_file', true) !== $relative_file
    || !wp_attachment_is_image($attachment_id)
    || !is_file(get_attached_file($attachment_id))
) {
    WP_CLI::error('Attachment 26059 verification failed.');
}

WP_CLI::success('Restored About image attachment 26059 with its paired upload family.');
