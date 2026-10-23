<?php
/** Local previews use ordinary Media Library attachments and the existing featured-image field. */
if (!defined('ABSPATH')) { exit; }

/** Explicit editorial import only. Public rendering never downloads external images. */
function iss_content_model_import_video_thumbnail(int $post_id, string $source_dir = '')
{
    if (get_post_type($post_id) !== ISS_CONTENT_MODEL_VIDEO_POST_TYPE) { return new WP_Error('video_required', 'Video post required.'); }
    $existing = get_post_thumbnail_id($post_id);
    if ($existing) { return $existing; }
    $video_id = iss_content_model_extract_youtube_video_id((string) get_post_meta($post_id, 'iss_video_url', true));
    if (!preg_match('/^[A-Za-z0-9_-]{11}$/D', $video_id)) { return new WP_Error('video_id', 'Valid YouTube video ID required.'); }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $filename = 'youtube-' . $video_id . '.jpg';
    foreach (['maxresdefault', 'hqdefault'] as $size) {
        $source = 'https://i.ytimg.com/vi/' . $video_id . '/' . $size . '.jpg';
        $tmp = wp_tempnam($filename);
        if (!$tmp) { return new WP_Error('thumbnail_tmp', 'Cannot create thumbnail temporary file.'); }
        if ($source_dir !== '') {
            $local = trailingslashit($source_dir) . $filename;
            $written = is_readable($local) && copy($local, $tmp);
        } else {
            $response = wp_safe_remote_get($source, ['timeout' => 15, 'redirection' => 0, 'limit_response_size' => 3 * MB_IN_BYTES]);
            $written = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200
                && file_put_contents($tmp, wp_remote_retrieve_body($response)) !== false;
        }
        $dimensions = $written ? wp_getimagesize($tmp) : false;
        if (!$dimensions || $dimensions[0] < 320 || wp_get_image_mime($tmp) !== 'image/jpeg') {
            wp_delete_file($tmp);
            if ($source_dir !== '') { break; }
            continue;
        }
        $attachment = media_handle_sideload(['name' => $filename, 'tmp_name' => $tmp], $post_id, 'Vorschaubild: ' . get_the_title($post_id));
        if (is_wp_error($attachment)) { wp_delete_file($tmp); return $attachment; }
        update_post_meta($attachment, '_source_url', $source_dir === '' ? $source : get_post_meta($post_id, 'iss_video_url', true));
        if (!set_post_thumbnail($post_id, $attachment) && get_post_thumbnail_id($post_id) !== $attachment) {
            return new WP_Error('thumbnail_assignment', 'Imported attachment could not be assigned: ' . $attachment);
        }
        return $attachment;
    }
    return new WP_Error('thumbnail_unavailable', 'No usable thumbnail available for video ' . $post_id);
}

/** Read-only by default; explicit apply requires an exclusive private before-image. */
function iss_content_model_video_thumbnails_cli(array $assoc_args): void
{
    $posts = get_posts(['post_type' => ISS_CONTENT_MODEL_VIDEO_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'ID', 'order' => 'ASC']);
    $before = [];
    foreach ($posts as $post) {
        if (get_post_thumbnail_id($post->ID)) { continue; }
        $before[] = ['post_id' => $post->ID, 'video_url' => get_post_meta($post->ID, 'iss_video_url', true), 'thumbnail_id' => 0];
    }
    if (!isset($assoc_args['apply'])) { WP_CLI::log((string) wp_json_encode(['missing' => $before], JSON_PRETTY_PRINT)); return; }
    if (!$before) { WP_CLI::success('All published videos already have featured images.'); return; }
    $path = (string) ($assoc_args['before'] ?? '');
    if ($path === '' || file_exists($path)) { WP_CLI::error('Provide a new private --before path.'); }
    $data = (string) wp_json_encode(['site' => home_url(), 'at' => gmdate('c'), 'videos' => $before], JSON_PRETTY_PRINT);
    $mask = umask(0077); $handle = fopen($path, 'x'); umask($mask);
    if (!$handle) { WP_CLI::error('Cannot create before-image.'); }
    $written = fwrite($handle, $data); fclose($handle);
    if ($written !== strlen($data) || file_get_contents($path) !== $data || (fileperms($path) & 0077) !== 0) { WP_CLI::error('Before-image verification failed.'); }
    $failed = 0;
    foreach ($before as $row) {
        $result = iss_content_model_import_video_thumbnail((int) $row['post_id'], (string) ($assoc_args['source-dir'] ?? ''));
        if (is_wp_error($result)) { ++$failed; WP_CLI::warning($result->get_error_message()); }
        else { WP_CLI::log('Video ' . $row['post_id'] . ': local attachment ' . $result); }
    }
    if ($failed) { WP_CLI::error($failed . ' previews unavailable; successful imports retained.'); }
    WP_CLI::success('Local video previews imported; existing featured images preserved.');
}
