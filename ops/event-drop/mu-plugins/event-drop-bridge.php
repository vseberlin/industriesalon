<?php
/**
 * Event Drop Bridge.
 *
 * Intake is the source of record under /event-drop-storage.
 * This bridge imports approved files into WordPress attachments and
 * exposes helper functions for event templates.
 */

if (!defined('ABSPATH')) {
    exit;
}

const EVENT_DROP_BRIDGE_HOOK = 'event_drop_bridge_sync';
const EVENT_DROP_BRIDGE_INTERVAL = 'event_drop_bridge_5min';
const EVENT_DROP_BRIDGE_ACCEPTED_DIR = '/event-drop-storage/accepted';
const EVENT_DROP_BRIDGE_MANIFEST_FILE = '/event-drop-storage/manifests/upload-manifest.csv';
const EVENT_DROP_BRIDGE_SYNC_LOG_OPTION = 'event_drop_bridge_last_sync';
const EVENT_DROP_BRIDGE_PUBLISH_SUBDIR = 'event-drop-storage/accepted';

add_filter(
    'cron_schedules',
    static function (array $schedules) : array {
        if (!isset($schedules[EVENT_DROP_BRIDGE_INTERVAL])) {
            $schedules[EVENT_DROP_BRIDGE_INTERVAL] = [
                'interval' => 300,
                'display' => __('Every 5 minutes', 'event-drop-bridge'),
            ];
        }
        return $schedules;
    }
);

add_action(
    'init',
    static function () : void {
        if (!wp_next_scheduled(EVENT_DROP_BRIDGE_HOOK)) {
            wp_schedule_event(time() + 60, EVENT_DROP_BRIDGE_INTERVAL, EVENT_DROP_BRIDGE_HOOK);
        }
    }
);

add_action(EVENT_DROP_BRIDGE_HOOK, 'event_drop_bridge_sync_from_intake');

/**
 * Parse intake manifest rows from /event-drop-storage/manifests/upload-manifest.csv.
 */
function event_drop_bridge_get_manifest_rows(): array
{
    if (!is_file(EVENT_DROP_BRIDGE_MANIFEST_FILE)) {
        return [];
    }

    $rows = [];
    $fp = fopen(EVENT_DROP_BRIDGE_MANIFEST_FILE, 'r');
    if ($fp === false) {
        return [];
    }

    while (($row = fgetcsv($fp)) !== false) {
        if (!isset($row[3]) || $row[3] === '') {
            continue;
        }

        $storedName = (string)$row[3];
        $rows[$storedName] = [
            'event_slug' => (string)($row[0] ?? ''),
            'participant_id' => (string)($row[1] ?? ''),
            'original_name' => (string)($row[2] ?? ''),
            'stored_name' => $storedName,
            'extension' => (string)($row[4] ?? ''),
            'size_bytes' => (string)($row[5] ?? ''),
            'sha256' => (string)($row[6] ?? ''),
            'remote_ip' => (string)($row[7] ?? ''),
            'user_agent' => (string)($row[8] ?? ''),
            'token_suffix' => (string)($row[9] ?? ''),
            'uploaded_at' => (string)($row[10] ?? ''),
            'attribution' => (string)($row[11] ?? ''),
            'license' => (string)($row[12] ?? ''),
            'consent' => (string)($row[13] ?? ''),
            'uploader_email' => (string)($row[14] ?? ''),
        ];
    }

    fclose($fp);
    return $rows;
}

function event_drop_bridge_resolve_event_id(string $eventRef): int
{
    $eventRef = sanitize_title($eventRef);
    if ($eventRef === '') {
        return 0;
    }

    $event = get_page_by_path($eventRef, OBJECT, 'veranstaltung');
    if ($event instanceof WP_Post) {
        return (int)$event->ID;
    }

    $query = new WP_Query([
        'post_type' => 'veranstaltung',
        'name' => $eventRef,
        'post_status' => 'publish',
        'fields' => 'ids',
        'posts_per_page' => 1,
        'no_found_rows' => true,
    ]);

    if (!empty($query->posts)) {
        return (int)$query->posts[0];
    }

    if (strlen($eventRef) <= 40) {
        global $wpdb;
        if ($wpdb instanceof wpdb) {
            $found = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'veranstaltung' AND post_status = 'publish' AND post_name LIKE %s ORDER BY ID DESC LIMIT 1",
                    $wpdb->esc_like($eventRef . '-') . '%'
                )
            );
            if ($found > 0) {
                return $found;
            }
        }
    }

    return 0;
}

function event_drop_bridge_find_existing_imported_attachment(string $storedName, string $sha256): int
{
    $metaQuery = [
        [
            'key' => '_event_drop_stored_name',
            'value' => $storedName,
            'compare' => '=',
        ],
    ];

    if ($sha256 !== '') {
        $metaQuery[] = [
            'key' => '_event_drop_sha256',
            'value' => $sha256,
            'compare' => '=',
        ];
    }

    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => ['image', 'video'],
        'fields' => 'ids',
        'numberposts' => 1,
        'meta_query' => [
            'relation' => 'OR',
            ...$metaQuery,
        ],
    ]);

    return !empty($existing) ? (int)reset($existing) : 0;
}

function event_drop_bridge_prepare_public_copy(string $sourceFile, string $storedName): string|WP_Error
{
    if (!is_file($sourceFile) || !is_readable($sourceFile)) {
        return new WP_Error('event_drop_bridge_source_missing', 'Source file is not available for publishing copy.');
    }

    $uploadDir = wp_upload_dir();
    if (!empty($uploadDir['error'])) {
        return new WP_Error('event_drop_bridge_upload_dir', (string)$uploadDir['error']);
    }

    $destinationDir = trailingslashit((string)$uploadDir['basedir']) . EVENT_DROP_BRIDGE_PUBLISH_SUBDIR;
    if (!wp_mkdir_p($destinationDir)) {
        return new WP_Error('event_drop_bridge_publish_dir', 'Could not create publishing directory.');
    }

    $destinationPath = rtrim($destinationDir, '/\\') . '/' . $storedName;
    if (is_file($destinationPath)) {
        if (!is_readable($destinationPath) || !hash_equals((string)hash_file('sha256', $sourceFile), (string)hash_file('sha256', $destinationPath))) {
            if (!unlink($destinationPath)) {
                return new WP_Error('event_drop_bridge_publish_conflict', 'Publishing destination exists but cannot be replaced.');
            }
        }
    }

    if (!copy($sourceFile, $destinationPath)) {
        return new WP_Error('event_drop_bridge_publish_copy', 'Unable to create publishable attachment copy.');
    }

    return $destinationPath;
}

function event_drop_bridge_is_media_file(string $path): bool
{
    if (!is_file($path) || !is_readable($path)) {
        return false;
    }

    $mime = (string)mime_content_type($path);
    return str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/');
}

function event_drop_bridge_is_derivative_name(string $storedName): bool
{
    if (!is_string($storedName) || $storedName === '') {
        return false;
    }

    $name = pathinfo($storedName, PATHINFO_FILENAME);
    return (bool)preg_match('/-(\d+x\d+)(-\d+x\d+)?$/', $name)
        || (bool)preg_match('/-scaled$/', $name)
        || (bool)preg_match('/-(?:thumbnail|crop|medium|large)$/i', $name);
}

function event_drop_bridge_import_file(
    string $sourceFile,
    int $eventId,
    string $storedName,
    array $meta
): int|WP_Error {
    $publishFile = event_drop_bridge_prepare_public_copy($sourceFile, $storedName);
    if (is_wp_error($publishFile)) {
        return $publishFile;
    }

    if (!is_file((string)$publishFile) || !is_readable((string)$publishFile)) {
        return new WP_Error('event_drop_bridge_publish_file_missing', 'Publish copy could not be prepared.');
    }

    $fileType = wp_check_filetype_and_ext((string)$publishFile, $storedName);
    if (!is_array($fileType) || empty($fileType['type'])) {
        $fileType = ['type' => (string)mime_content_type((string)$publishFile)];
    }

    $title = trim((string)($meta['attribution'] ?? ''));
    if ($title === '') {
        $title = pathinfo($storedName, PATHINFO_FILENAME);
    }

    $attachmentId = wp_insert_attachment(
        [
            'post_mime_type' => $fileType['type'],
            'post_title' => sanitize_text_field($title),
            'post_status' => 'inherit',
            'post_parent' => $eventId,
            'post_content' => '',
            'post_excerpt' => sanitize_text_field((string)($meta['attribution'] ?? '')),
        ],
        (string)$publishFile,
        $eventId
    );

    if (is_wp_error($attachmentId)) {
        return $attachmentId;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata((int)$attachmentId, (string)$publishFile);
    if (is_array($metadata) && $metadata !== []) {
        wp_update_attachment_metadata((int)$attachmentId, $metadata);
    }

    $mime = (string)($meta['mime'] ?? '') ?: (string)$fileType['type'];

    update_post_meta((int)$attachmentId, '_event_drop_stored_name', $storedName);
    update_post_meta((int)$attachmentId, '_event_drop_event_ref', (string)($meta['event_slug'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_original_name', (string)($meta['original_name'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_size_bytes', (string)($meta['size_bytes'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_sha256', (string)($meta['sha256'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_remote_ip', (string)($meta['remote_ip'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_user_agent', (string)($meta['user_agent'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_uploader', (string)($meta['participant_id'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_uploader_email', (string)($meta['uploader_email'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_attribution', (string)($meta['attribution'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_license', (string)($meta['license'] ?: 'all-rights-reserved'));
    update_post_meta((int)$attachmentId, '_event_drop_consent', (string)($meta['consent'] === '1' ? '1' : '0'));
    update_post_meta((int)$attachmentId, '_event_drop_uploaded_at', (string)($meta['uploaded_at'] ?? ''));
    update_post_meta((int)$attachmentId, '_event_drop_source_path', $sourceFile);
    update_post_meta((int)$attachmentId, '_event_drop_publish_path', $publishFile);
    update_post_meta((int)$attachmentId, '_event_drop_mime_type', $mime);
    update_post_meta((int)$attachmentId, '_event_drop_synced_at', gmdate('c'));

    return (int)$attachmentId;
}

function event_drop_bridge_parse_error_message($result): string
{
    return is_wp_error($result) ? $result->get_error_message() : '';
}

function event_drop_bridge_sync_from_intake(): array
{
    $result = [
        'found' => 0,
        'imported' => 0,
        'skipped' => 0,
        'missing_event' => 0,
        'failed' => 0,
        'errors' => [],
    ];

    if (!is_dir(EVENT_DROP_BRIDGE_ACCEPTED_DIR)) {
        $result['errors'][] = 'accepted directory not found.';
        update_option(EVENT_DROP_BRIDGE_SYNC_LOG_OPTION, ['at' => gmdate('c'), 'result' => $result], false);
        return $result;
    }

    $manifest = event_drop_bridge_get_manifest_rows();

    foreach (glob(EVENT_DROP_BRIDGE_ACCEPTED_DIR . '/*') as $path) {
        if (!is_file($path)) {
            continue;
        }
        $result['found']++;

        $storedName = basename($path);
        $meta = $manifest[$storedName] ?? [];
        if (!event_drop_bridge_is_media_file($path)) {
            $result['skipped']++;
            continue;
        }
        if ($meta === []) {
            if (event_drop_bridge_is_derivative_name($storedName)) {
                $result['skipped']++;
                continue;
            }
            $result['missing_event']++;
            $result['errors'][] = 'No manifest entry for intake item: ' . $storedName;
            continue;
        }

        $eventRef = (string)($meta['event_slug'] ?? '');
        if ($eventRef === '' && preg_match('/^([a-z0-9\\.-]+)_/i', $storedName, $match) === 1) {
            $eventRef = $match[1];
        }

        $eventId = event_drop_bridge_resolve_event_id($eventRef);
        if ($eventId <= 0) {
            $result['missing_event']++;
            $result['errors'][] = 'No event found for intake item: ' . $storedName . ' (ref: ' . $eventRef . ')';
            continue;
        }

        $existingAttachmentId = event_drop_bridge_find_existing_imported_attachment(
            $storedName,
            (string)($meta['sha256'] ?? '')
        );
        if ($existingAttachmentId > 0) {
            $result['skipped']++;
            continue;
        }

        $syncResult = event_drop_bridge_import_file($path, $eventId, $storedName, $meta);
        if (is_wp_error($syncResult)) {
            $result['failed']++;
            $result['errors'][] = $storedName . ': ' . event_drop_bridge_parse_error_message($syncResult);
            continue;
        }

        $result['imported']++;
    }

    update_option(EVENT_DROP_BRIDGE_SYNC_LOG_OPTION, ['at' => gmdate('c'), 'result' => $result], false);
    return $result;
}

function event_drop_bridge_get_event_media($event, array $args = []): array
{
    $eventId = 0;

    if (is_object($event) && $event instanceof WP_Post) {
        $eventId = (int)$event->ID;
    } elseif (is_int($event)) {
        $eventId = (int)$event;
    } elseif (is_numeric($event)) {
        $eventId = (int)$event;
    } elseif (is_string($event)) {
        $eventId = event_drop_bridge_resolve_event_id((string)$event);
    } else {
        $eventId = 0;
    }

    if ($eventId <= 0 && is_singular('veranstaltung')) {
        $eventId = (int)get_queried_object_id();
    }

    if ($eventId <= 0) {
        return [];
    }

    $type = sanitize_key((string)($args['type'] ?? 'all'));
    $limit = (int)($args['limit'] ?? -1);
    if ($limit < -1) {
        $limit = -1;
    }

    $queryArgs = [
        'post_parent' => $eventId,
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => ['image', 'video'],
        'posts_per_page' => $limit,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => '_event_drop_stored_name',
                'compare' => 'EXISTS',
            ],
            [
                'relation' => 'OR',
                [
                    'key' => '_event_drop_gallery_hidden',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key' => '_event_drop_gallery_hidden',
                    'value' => '1',
                    'compare' => '!=',
                ],
            ],
        ],
    ];

        if ($type === 'image') {
            $queryArgs['post_mime_type'] = 'image';
        } elseif ($type === 'video') {
            $queryArgs['post_mime_type'] = 'video';
        }

    $media = get_posts($queryArgs);
    if (empty($media)) {
        return [];
    }

    usort($media, static function ($a, $b): int {
        $aId = (int)$a;
        $bId = (int)$b;
        $aOrder = (int)get_post_meta($aId, '_event_drop_gallery_order', true);
        $bOrder = (int)get_post_meta($bId, '_event_drop_gallery_order', true);
        if ($aOrder === $bOrder) {
            return $aId <=> $bId;
        }
        return $aOrder <=> $bOrder;
    });

    $items = [];
    foreach ($media as $id) {
        $id = (int)$id;
        $sourceName = (string)get_post_meta($id, '_event_drop_stored_name', true);
        if (event_drop_bridge_is_derivative_name($sourceName)) {
            continue;
        }
        $mime = (string)get_post_mime_type($id);
        $items[] = [
            'id' => $id,
            'post_id' => $id,
            'mime' => $mime,
            'attribution' => (string)get_post_meta($id, '_event_drop_attribution', true),
            'license' => (string)get_post_meta($id, '_event_drop_license', true),
            'source_name' => (string)get_post_meta($id, '_event_drop_stored_name', true),
            'synced_at' => (string)get_post_meta($id, '_event_drop_synced_at', true),
            'gallery_order' => (int)get_post_meta($id, '_event_drop_gallery_order', true),
            'hidden' => (string)get_post_meta($id, '_event_drop_gallery_hidden', true) === '1',
        ];
    }

    return $items;
}

function event_drop_bridge_render_event_gallery($event, array $args = []): string
{
    $items = event_drop_bridge_get_event_media($event, $args);
    if (empty($items)) {
        return '';
    }

    $size = sanitize_key((string)($args['size'] ?? 'large'));
    if ($size === '') {
        $size = 'large';
    }

    static $stylesRendered = false;
    $html = '';
    if (!$stylesRendered) {
        $html .= '<style>
        .event-drop-bridge-gallery {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            overflow-y: hidden;
            scrollbar-width: thin;
            padding: 0.25rem 0 0.75rem 0;
            scroll-snap-type: x mandatory;
        }
        .event-drop-bridge-gallery::-webkit-scrollbar {
            height: 0.65rem;
        }
        .event-drop-bridge-gallery::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.35);
            border-radius: 999px;
        }
        .event-drop-bridge-item {
            flex: 0 0 14rem;
            width: 14rem;
            margin: 0;
            scroll-snap-align: start;
            background: #fff;
            border-radius: 0.65rem;
            border: 1px solid rgba(0, 0, 0, 0.08);
            padding: 0.45rem;
            display: grid;
            gap: 0.35rem;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.04);
        }
        .event-drop-bridge-card-link {
            display: block;
            text-decoration: none;
            color: inherit;
        }
        .event-drop-bridge-media-wrap {
            width: 100%;
            aspect-ratio: 5 / 3;
            overflow: hidden;
            border-radius: 0.5rem;
            background: #f0f0f0;
        }
        .event-drop-bridge-media-wrap img,
        .event-drop-bridge-media-wrap video {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            display: block;
        }
        .event-drop-bridge-media-caption {
            font-size: 0.85rem;
            color: #444;
            line-height: 1.25;
            min-height: 2.4em;
        }
        </style>';
        $stylesRendered = true;
    }

    $html .= '<section class="wp-block-group iss-event-asset-gallery section section--plain"><div class="wp-block-group iss-container">';
    $html .= '<h2 class="wp-block-heading">Mediengalerie</h2>';
    $html .= '<div class="event-drop-bridge-gallery">';
    foreach ($items as $item) {
        $attachmentId = (int)$item['id'];
        $mime = (string)$item['mime'];
        $attribution = (string)$item['attribution'];
        $license = (string)$item['license'];
        $fullUrl = (string)wp_get_attachment_url($attachmentId);

        $html .= '<figure class="event-drop-bridge-item">';
        if (str_starts_with($mime, 'image/')) {
            $src = wp_get_attachment_image_src($attachmentId, $size);
            if (is_array($src)) {
                $width = (int)($src[1] ?? 0);
                $height = (int)($src[2] ?? 0);
                $html .= '<a class="event-drop-bridge-card-link" href="' . esc_url($fullUrl) . '" target="_blank" rel="noopener noreferrer" title="Open full image">';
                $html .= '<span class="event-drop-bridge-media-wrap">';
                $html .= '<img src="' . esc_url((string)$src[0]) . '"';
                if ($width > 0) {
                    $html .= ' width="' . $width . '"';
                }
                if ($height > 0) {
                    $html .= ' height="' . $height . '"';
                }
                $html .= ' alt="' . esc_attr($attribution) . '" loading="lazy">';
                $html .= '</span></a>';
            } elseif ($fullUrl !== '') {
                $html .= '<a class="event-drop-bridge-card-link" href="' . esc_url($fullUrl) . '" target="_blank" rel="noopener noreferrer" title="Open full image">';
                $html .= '<span class="event-drop-bridge-media-wrap">';
                $html .= '<img src="' . esc_url($fullUrl) . '" alt="' . esc_attr($attribution) . '" loading="lazy">';
                $html .= '</span></a>';
            }
        } elseif (str_starts_with($mime, 'video/')) {
            if ($fullUrl !== '') {
                $html .= '<a class="event-drop-bridge-card-link" href="' . esc_url($fullUrl) . '" target="_blank" rel="noopener noreferrer" title="Open video">';
                $html .= '<span class="event-drop-bridge-media-wrap">';
                $html .= '<video controls preload="metadata" class="event-drop-bridge-video">';
                $html .= '<source src="' . esc_url($fullUrl) . '" type="' . esc_attr($mime) . '">';
                $html .= '</video>';
                $html .= '</span></a>';
            }
        }
        $caption = $attribution !== '' ? trim($attribution . ($license !== '' ? ' (' . $license . ')' : '')) : $license;
        if ($caption !== '') {
            $html .= '<figcaption class="event-drop-bridge-media-caption">' . esc_html($caption) . '</figcaption>';
        }
        $html .= '</figure>';
    }

    $html .= '</div></div></section>';
    return $html;
}

function event_drop_bridge_gallery_shortcode($atts)
{
    $atts = shortcode_atts(
        [
            'event_id' => '',
            'event_slug' => '',
            'type' => 'all',
            'limit' => '24',
            'size' => 'large',
        ],
        (array)$atts,
        'event_drop_gallery'
    );

    $eventId = 0;
    if (!empty($atts['event_id'])) {
        $eventId = (int)$atts['event_id'];
    } elseif (!empty($atts['event_slug'])) {
        $eventId = event_drop_bridge_resolve_event_id((string)$atts['event_slug']);
    }

    if ($eventId <= 0) {
        $eventId = is_singular('veranstaltung') ? (int)get_queried_object_id() : 0;
    }

    if ($eventId <= 0 && !empty($atts['event_slug'])) {
        $eventId = event_drop_bridge_resolve_event_id((string)$atts['event_slug']);
    }

    if ($eventId <= 0) {
        return '';
    }

    $html = event_drop_bridge_render_event_gallery(
        $eventId,
        [
            'type' => (string)$atts['type'],
            'limit' => (int)$atts['limit'],
            'size' => (string)$atts['size'],
        ]
    );

    return $html === '' ? '' : $html;
}

add_shortcode('event_drop_gallery', 'event_drop_bridge_gallery_shortcode');


add_action('add_meta_boxes', 'event_drop_bridge_register_event_media_box');
function event_drop_bridge_register_event_media_box(): void
{
    add_meta_box(
        'event-drop-bridge-media',
        __('Event media gallery', 'event-drop-bridge'),
        'event_drop_bridge_render_event_media_box',
        'veranstaltung',
        'normal',
        'default'
    );
}

function event_drop_bridge_admin_license_options(): array
{
    return [
        'all-rights-reserved' => 'All rights reserved',
        'cc-by-4.0' => 'CC BY 4.0',
        'cc-by-sa-4.0' => 'CC BY-SA 4.0',
        'cc-by-nc-4.0' => 'CC BY-NC 4.0',
        'cc0-1.0' => 'CC0 1.0',
    ];
}

function event_drop_bridge_get_event_attachment_ids_for_admin(int $eventId): array
{
    if ($eventId <= 0) {
        return [];
    }

    $ids = get_posts([
        'post_parent' => $eventId,
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'post_mime_type' => ['image', 'video'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => [
            [
                'key' => '_event_drop_stored_name',
                'compare' => 'EXISTS',
            ],
        ],
    ]);

    return array_map('intval', $ids);
}

function event_drop_bridge_render_event_media_box(WP_Post $post): void
{
    if (!current_user_can('edit_post', (int)$post->ID)) {
        return;
    }

    wp_nonce_field('event_drop_bridge_save_event_media', 'event_drop_bridge_media_nonce');

    $syncUrl = wp_nonce_url(
        add_query_arg([
            'event_drop_bridge_sync_now' => '1',
            'post' => (int)$post->ID,
        ], admin_url('post.php')),
        'event_drop_bridge_sync_now_' . (int)$post->ID
    );

    $lastSync = get_option(EVENT_DROP_BRIDGE_SYNC_LOG_OPTION);
    echo '<p><a class="button" href="' . esc_url($syncUrl) . '">' . esc_html__('Sync accepted uploads now', 'event-drop-bridge') . '</a></p>';
    if (is_array($lastSync) && isset($lastSync['at'])) {
        echo '<p style="color:#646970;margin-top:-4px;">Last sync: ' . esc_html((string)$lastSync['at']) . '</p>';
    }

    $ids = event_drop_bridge_get_event_attachment_ids_for_admin((int)$post->ID);
    if ($ids === []) {
        echo '<p>No imported event media attached to this Veranstaltung yet.</p>';
        return;
    }

    echo '<style>
        .event-drop-admin-media { display: grid; gap: 12px; }
        .event-drop-admin-item { display: grid; grid-template-columns: 112px 1fr; gap: 12px; padding: 12px; border: 1px solid #dcdcde; background: #fff; }
        .event-drop-admin-thumb img, .event-drop-admin-thumb video { width: 112px; height: 78px; object-fit: cover; background: #f0f0f1; display: block; }
        .event-drop-admin-fields { display: grid; grid-template-columns: 90px minmax(120px, 1fr) minmax(140px, 1fr) 90px 90px; gap: 8px; align-items: end; }
        .event-drop-admin-fields label { display: grid; gap: 4px; font-weight: 600; }
        .event-drop-admin-fields input[type="text"], .event-drop-admin-fields input[type="number"], .event-drop-admin-fields select { width: 100%; }
        .event-drop-admin-meta { grid-column: 1 / -1; color: #646970; font-size: 12px; }
        @media (max-width: 900px) { .event-drop-admin-item { grid-template-columns: 1fr; } .event-drop-admin-fields { grid-template-columns: 1fr; } }
    </style>';

    echo '<div class="event-drop-admin-media">';
    foreach ($ids as $id) {
        $mime = (string)get_post_mime_type($id);
        $thumb = '';
        if (str_starts_with($mime, 'image/')) {
            $thumb = (string)wp_get_attachment_image($id, 'thumbnail');
        } elseif (str_starts_with($mime, 'video/')) {
            $url = (string)wp_get_attachment_url($id);
            $thumb = '<video muted preload="metadata"><source src="' . esc_url($url) . '" type="' . esc_attr($mime) . '"></video>';
        }

        $attribution = (string)get_post_meta($id, '_event_drop_attribution', true);
        $license = (string)get_post_meta($id, '_event_drop_license', true);
        $order = (int)get_post_meta($id, '_event_drop_gallery_order', true);
        $hidden = (string)get_post_meta($id, '_event_drop_gallery_hidden', true) === '1';
        $sourceName = (string)get_post_meta($id, '_event_drop_stored_name', true);
        $originalName = (string)get_post_meta($id, '_event_drop_original_name', true);
        $editUrl = get_edit_post_link($id);

        echo '<div class="event-drop-admin-item">';
        echo '<div class="event-drop-admin-thumb">' . $thumb . '</div>';
        echo '<div class="event-drop-admin-fields">';
        echo '<input type="hidden" name="event_drop_media_ids[]" value="' . esc_attr((string)$id) . '">';
        echo '<label>Order<input type="number" name="event_drop_media[' . esc_attr((string)$id) . '][order]" value="' . esc_attr((string)$order) . '" step="1"></label>';
        echo '<label>Credit<input type="text" name="event_drop_media[' . esc_attr((string)$id) . '][attribution]" value="' . esc_attr($attribution) . '"></label>';
        echo '<label>License<select name="event_drop_media[' . esc_attr((string)$id) . '][license]">';
        foreach (event_drop_bridge_admin_license_options() as $value => $label) {
            echo '<option value="' . esc_attr($value) . '"' . selected($license, $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Hidden<input type="checkbox" name="event_drop_media[' . esc_attr((string)$id) . '][hidden]" value="1"' . checked($hidden, true, false) . '></label>';
        echo '<p><a class="button button-small" href="' . esc_url((string)$editUrl) . '">Media</a></p>';
        echo '<div class="event-drop-admin-meta">' . esc_html($originalName !== '' ? $originalName : $sourceName) . ' · attachment #' . esc_html((string)$id) . '</div>';
        echo '</div></div>';
    }
    echo '</div>';
}

add_action('save_post_veranstaltung', 'event_drop_bridge_save_event_media_box', 10, 2);
function event_drop_bridge_save_event_media_box(int $postId, WP_Post $post): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $postId)) {
        return;
    }
    if (!isset($_POST['event_drop_bridge_media_nonce']) || !wp_verify_nonce((string)$_POST['event_drop_bridge_media_nonce'], 'event_drop_bridge_save_event_media')) {
        return;
    }

    $ids = isset($_POST['event_drop_media_ids']) && is_array($_POST['event_drop_media_ids']) ? array_map('intval', $_POST['event_drop_media_ids']) : [];
    $payload = isset($_POST['event_drop_media']) && is_array($_POST['event_drop_media']) ? $_POST['event_drop_media'] : [];
    $allowedLicenses = array_keys(event_drop_bridge_admin_license_options());

    foreach ($ids as $id) {
        if ($id <= 0 || (int)get_post_field('post_parent', $id) !== $postId) {
            continue;
        }
        if ((string)get_post_meta($id, '_event_drop_stored_name', true) === '') {
            continue;
        }

        $row = isset($payload[$id]) && is_array($payload[$id]) ? $payload[$id] : [];
        $order = isset($row['order']) ? (int)$row['order'] : 0;
        $attribution = sanitize_text_field((string)($row['attribution'] ?? ''));
        $license = sanitize_key((string)($row['license'] ?? 'all-rights-reserved'));
        if (!in_array($license, $allowedLicenses, true)) {
            $license = 'all-rights-reserved';
        }
        $hidden = isset($row['hidden']) && (string)$row['hidden'] === '1' ? '1' : '0';

        update_post_meta($id, '_event_drop_gallery_order', (string)$order);
        update_post_meta($id, '_event_drop_attribution', $attribution);
        update_post_meta($id, '_event_drop_license', $license);
        update_post_meta($id, '_event_drop_gallery_hidden', $hidden);

        wp_update_post([
            'ID' => $id,
            'post_title' => $attribution !== '' ? $attribution : get_the_title($id),
            'post_excerpt' => $attribution,
            'menu_order' => $order,
        ]);
    }
}

add_action('admin_init', 'event_drop_bridge_handle_sync_now');
function event_drop_bridge_handle_sync_now(): void
{
    if (!is_admin() || !isset($_GET['event_drop_bridge_sync_now'], $_GET['post'])) {
        return;
    }

    $postId = (int)$_GET['post'];
    if ($postId <= 0 || get_post_type($postId) !== 'veranstaltung') {
        return;
    }
    if (!current_user_can('edit_post', $postId)) {
        return;
    }
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce((string)$_GET['_wpnonce'], 'event_drop_bridge_sync_now_' . $postId)) {
        return;
    }

    event_drop_bridge_sync_from_intake();
    wp_safe_redirect(add_query_arg('event_drop_synced', '1', get_edit_post_link($postId, '')));
    exit;
}

add_action('admin_notices', 'event_drop_bridge_sync_now_notice');
function event_drop_bridge_sync_now_notice(): void
{
    if (!isset($_GET['event_drop_synced'])) {
        return;
    }
    echo '<div class="notice notice-success is-dismissible"><p>Event media sync completed.</p></div>';
}
