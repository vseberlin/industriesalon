<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Decay updates plugin-owned Editorial Sets table rows.
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dynamic table name is constrained to the Editorial Sets service.

function iss_content_editorial_sets_mark_stale_items(): int
{
    global $wpdb;

    $service = iss_content_editorial_sets_service();
    $now = current_time('mysql', true);
    $updated = $wpdb->query($wpdb->prepare(
        "UPDATE {$service->get_items_table_name()}
        SET status = 'stale', updated_at = %s
        WHERE decay_at IS NOT NULL
            AND decay_at <= %s
            AND retain = 0
            AND status IN ('pending', 'reviewing', 'approved')",
        $now,
        $now
    ));

    return is_int($updated) ? $updated : 0;
}

function iss_content_editorial_sets_schedule_decay(): void
{
    if (!wp_next_scheduled('iss_content_editorial_sets_decay')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'iss_content_editorial_sets_decay');
    }
}
add_action('init', 'iss_content_editorial_sets_schedule_decay');

function iss_content_editorial_sets_run_decay(): void
{
    iss_content_editorial_sets_mark_stale_items();
}
add_action('iss_content_editorial_sets_decay', 'iss_content_editorial_sets_run_decay');

function iss_content_editorial_sets_event_drop_storage_root(): string
{
    return (string) apply_filters('iss_content_editorial_sets_event_drop_storage_root', '/event-drop-storage');
}

function iss_content_editorial_sets_event_drop_manifest_rows(string $manifest_file): array
{
    if (!is_readable($manifest_file)) {
        return [];
    }

    $handle = fopen($manifest_file, 'r');
    if ($handle === false) {
        return [];
    }

    $header = fgetcsv($handle);
    $keys = is_array($header) ? array_map('sanitize_key', $header) : [];
    $rows = [];

    while (($row = fgetcsv($handle)) !== false) {
        $data = [];
        foreach ($keys as $index => $key) {
            if ($key !== '') {
                $data[$key] = (string) ($row[$index] ?? '');
            }
        }
        $stored_name = (string) ($data['stored_name'] ?? '');
        if ($stored_name !== '') {
            $rows[$stored_name] = $data;
        }
    }

    fclose($handle);

    return $rows;
}

function iss_content_editorial_sets_event_drop_meta_from_filename(string $stored_name, string $path): array
{
    $meta = [
        'event_slug' => '',
        'participant_id' => '',
        'original_name' => $stored_name,
        'stored_name' => $stored_name,
        'extension' => strtolower(pathinfo($stored_name, PATHINFO_EXTENSION)),
        'size_bytes' => is_file($path) ? (string) filesize($path) : '',
        'sha256' => is_file($path) && is_readable($path) ? (string) hash_file('sha256', $path) : '',
        'uploaded_at' => is_file($path) ? gmdate('c', (int) filemtime($path)) : '',
        'attribution' => '',
        'license' => '',
        'consent' => '',
        'uploader_email' => '',
    ];

    if (preg_match('/^(.+)_([^_]+)_([0-9]{8})_([0-9]{6})_([a-f0-9]+)\.[a-z0-9]+$/i', $stored_name, $matches) === 1) {
        $meta['event_slug'] = sanitize_title((string) $matches[1]);
        $meta['participant_id'] = sanitize_text_field((string) $matches[2]);
        $meta['attribution'] = sanitize_text_field((string) $matches[2]);
    }

    return $meta;
}

function iss_content_editorial_sets_event_drop_context_post(string $event_slug): ?WP_Post
{
    $event_slug = sanitize_title($event_slug);
    if ($event_slug === '') {
        return null;
    }

    $post = get_page_by_path($event_slug, OBJECT, ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE);
    if ($post instanceof WP_Post) {
        return $post;
    }

    return null;
}

function iss_content_editorial_sets_event_drop_item_provenance(array $item): array
{
    $provenance = json_decode((string) ($item['provenance_json'] ?? ''), true);
    return is_array($provenance) ? $provenance : [];
}

function iss_content_editorial_sets_event_drop_resolve_item_path(array $item): string
{
    if ((string) ($item['kind'] ?? '') !== 'external_upload' || (string) ($item['source'] ?? '') !== 'event-drop') {
        return '';
    }

    $provenance = iss_content_editorial_sets_event_drop_item_provenance($item);
    $stored_name = sanitize_file_name((string) ($provenance['stored_name'] ?? $item['source_id'] ?? ''));
    if ($stored_name === '') {
        return '';
    }

    $root = realpath(rtrim(iss_content_editorial_sets_event_drop_storage_root(), '/'));
    if (!is_string($root) || $root === '') {
        return '';
    }

    $paths = [];
    if (!empty($provenance['path'])) {
        $paths[] = (string) $provenance['path'];
    }
    foreach (['incoming', 'accepted', 'rejected'] as $state) {
        $paths[] = $root . '/' . $state . '/' . $stored_name;
    }

    foreach (array_unique($paths) as $path) {
        $real = realpath($path);
        if (is_string($real) && strpos($real, $root . '/') === 0 && is_file($real) && is_readable($real)) {
            return $real;
        }
    }

    return '';
}

function iss_content_editorial_sets_event_drop_move_item_file(array $item, string $target_state): string
{
    $target_state = sanitize_key($target_state);
    if (!in_array($target_state, ['incoming', 'accepted', 'rejected'], true)) {
        return '';
    }

    $source_path = iss_content_editorial_sets_event_drop_resolve_item_path($item);
    if ($source_path === '') {
        return '';
    }

    $provenance = iss_content_editorial_sets_event_drop_item_provenance($item);
    $stored_name = sanitize_file_name((string) ($provenance['stored_name'] ?? $item['source_id'] ?? basename($source_path)));
    if ($stored_name === '') {
        return '';
    }

    $root = realpath(rtrim(iss_content_editorial_sets_event_drop_storage_root(), '/'));
    if (!is_string($root) || $root === '') {
        return '';
    }

    $target_dir = $root . '/' . $target_state;
    if (!is_dir($target_dir) && !wp_mkdir_p($target_dir)) {
        return '';
    }

    $target_path = $target_dir . '/' . $stored_name;
    if (realpath($source_path) === realpath($target_path)) {
        return $target_path;
    }

    if (is_file($target_path)) {
        $source_hash = is_readable($source_path) ? (string) hash_file('sha256', $source_path) : '';
        $target_hash = is_readable($target_path) ? (string) hash_file('sha256', $target_path) : '';
        if ($source_hash !== '' && $target_hash !== '' && hash_equals($source_hash, $target_hash)) {
            return $target_path;
        }

        $target_path = $target_dir . '/' . wp_unique_filename($target_dir, $stored_name);
    }

    if (!rename($source_path, $target_path)) {
        return '';
    }

    return $target_path;
}

function iss_content_editorial_sets_event_drop_moderate_item(int $item_id, string $action): bool
{
    $service = iss_content_editorial_sets_service();
    $item = $service->get_item($item_id);
    if (!$item) {
        return false;
    }

    if ((string) ($item['kind'] ?? '') !== 'external_upload' || (string) ($item['source'] ?? '') !== 'event-drop') {
        $status_map = [
            'approve' => 'approved',
            'review' => 'reviewing',
            'retain' => 'retained',
            'stale' => 'stale',
            'restore' => 'pending',
            'reject' => 'rejected',
        ];
        return isset($status_map[$action]) && $service->update_item($item_id, ['status' => $status_map[$action]]);
    }

    $provenance = iss_content_editorial_sets_event_drop_item_provenance($item);
    $now = current_time('mysql', true);
    $now_iso = gmdate('c');
    $args = ['provenance' => $provenance];

    if ($action === 'reject') {
        $path = iss_content_editorial_sets_event_drop_move_item_file($item, 'rejected');
        if ($path === '') {
            return false;
        }
        $args['status'] = 'rejected';
        $args['decay_at'] = gmdate('Y-m-d H:i:s', time() + 14 * DAY_IN_SECONDS);
        $args['provenance']['storage_state'] = 'rejected';
        $args['provenance']['path'] = $path;
        $args['provenance']['rejected_at'] = $now_iso;
    } elseif ($action === 'restore') {
        $path = iss_content_editorial_sets_event_drop_move_item_file($item, 'incoming');
        if ($path === '') {
            return false;
        }
        $args['status'] = 'pending';
        $args['decay_at'] = '';
        $args['provenance']['storage_state'] = 'incoming';
        $args['provenance']['path'] = $path;
        $args['provenance']['restored_at'] = $now_iso;
    } elseif ($action === 'retain') {
        $args['status'] = 'retained';
        $args['retain'] = true;
        $args['retain_reason'] = __('Manual retention in intake workbench.', 'iss-content-model');
        $args['provenance']['retained_at'] = $now_iso;
    } elseif ($action === 'stale') {
        $args['status'] = 'stale';
        $args['decay_at'] = gmdate('Y-m-d H:i:s', time() + 14 * DAY_IN_SECONDS);
        $args['provenance']['stale_at'] = $now_iso;
    } elseif ($action === 'approve') {
        $args['status'] = 'approved';
        $args['decay_at'] = '';
        $args['provenance']['approved_at'] = $now_iso;
        $args['provenance']['storage_state'] = (string) ($args['provenance']['storage_state'] ?? 'incoming');
    } elseif ($action === 'review') {
        $args['status'] = 'reviewing';
        $args['provenance']['reviewed_at'] = $now_iso;
    } else {
        return false;
    }

    $ok = $service->update_item($item_id, $args);
    if ($ok) {
        $service->record_audit((int) ($item['set_id'] ?? 0), $item_id, 'event_drop_' . sanitize_key($action), __('Event Drop storage state updated.', 'iss-content-model'), [
            'action' => $action,
            'at' => $now,
            'storage_state' => (string) ($args['provenance']['storage_state'] ?? $provenance['storage_state'] ?? ''),
        ]);
    }

    return $ok;
}

function iss_content_editorial_sets_event_drop_moderate_items(array $item_ids, string $action): int
{
    $updated = 0;
    foreach ($item_ids as $item_id) {
        if (iss_content_editorial_sets_event_drop_moderate_item(absint($item_id), $action)) {
            $updated++;
        }
    }

    return $updated;
}

function iss_content_editorial_sets_sync_event_drop_incoming(): int
{
    $root = rtrim(iss_content_editorial_sets_event_drop_storage_root(), '/');
    $incoming_dir = $root . '/incoming';
    if (!is_dir($incoming_dir) || !is_readable($incoming_dir)) {
        return 0;
    }

    $manifest_rows = iss_content_editorial_sets_event_drop_manifest_rows($root . '/manifests/upload-manifest.csv');
    $service = iss_content_editorial_sets_service();
    $created = 0;

    foreach (glob($incoming_dir . '/*') ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }

        $stored_name = basename($path);
        $meta = $manifest_rows[$stored_name] ?? iss_content_editorial_sets_event_drop_meta_from_filename($stored_name, $path);
        $event_slug = sanitize_title((string) ($meta['event_slug'] ?? ''));
        $event = iss_content_editorial_sets_event_drop_context_post($event_slug);
        $set_key = $service->normalize_key('event-drop-' . ($event_slug !== '' ? $event_slug : 'uncategorized'));
        $set = $service->get_set_by_key($set_key);

        if (!$set) {
            $set_id = $service->create_set([
                'set_key' => $set_key,
                'title' => $event instanceof WP_Post
                    ? sprintf(__('Event Drop: %s', 'iss-content-model'), get_the_title($event))
                    : __('Event Drop intake', 'iss-content-model'),
                'set_role' => 'intake',
                'status' => 'working',
            ]);
            $set = $set_id > 0 ? $service->get_set($set_id) : null;
        }

        $set_id = is_array($set) ? (int) $set['id'] : 0;
        if ($set_id <= 0) {
            continue;
        }

        if ($event instanceof WP_Post) {
            $service->attach_context($set_id, ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, (int) $event->ID, 'gallery_candidate');
        }

        $item_id = $service->add_item([
            'set_id' => $set_id,
            'kind' => 'external_upload',
            'source' => 'event-drop',
            'source_id' => $stored_name,
            'status' => 'pending',
            'label' => (string) ($meta['original_name'] ?? $stored_name),
            'origin' => 'event_drop',
            'provenance' => [
                'storage_state' => 'incoming',
                'stored_name' => $stored_name,
                'path' => $path,
                'event_slug' => $event_slug,
                'participant_id' => (string) ($meta['participant_id'] ?? ''),
                'size_bytes' => (string) ($meta['size_bytes'] ?? ''),
                'sha256' => (string) ($meta['sha256'] ?? ''),
                'uploaded_at' => (string) ($meta['uploaded_at'] ?? ''),
            ],
            'rights' => [
                'attribution' => (string) ($meta['attribution'] ?? ''),
                'license' => (string) ($meta['license'] ?? ''),
                'consent' => (string) ($meta['consent'] ?? ''),
                'uploader_email' => (string) ($meta['uploader_email'] ?? ''),
            ],
        ]);

        if ($item_id > 0) {
            $created++;
        }
    }

    return $created;
}

function iss_content_editorial_sets_event_drop_set_for_attachment(int $attachment_id): int
{
    if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
        return 0;
    }

    $stored_name = (string) get_post_meta($attachment_id, '_event_drop_stored_name', true);
    if ($stored_name === '') {
        return 0;
    }

    $event_id = (int) wp_get_post_parent_id($attachment_id);
    $event = $event_id > 0 ? get_post($event_id) : null;
    $service = iss_content_editorial_sets_service();
    $event_key = $event instanceof WP_Post ? $event->post_name : sanitize_title((string) get_post_meta($attachment_id, '_event_drop_event_ref', true));
    $set_key = $service->normalize_key('event-drop-' . ($event_key !== '' ? $event_key : 'uncategorized'));
    $set = $service->get_set_by_key($set_key);
    if (!$set) {
        $set_id = $service->create_set([
            'set_key' => $set_key,
            'title' => $event instanceof WP_Post
                ? sprintf(__('Event Drop: %s', 'iss-content-model'), get_the_title($event))
                : __('Event Drop intake', 'iss-content-model'),
            'set_role' => 'intake',
            'status' => 'working',
        ]);
        $set = $set_id > 0 ? $service->get_set($set_id) : null;
    }

    $set_id = is_array($set) ? (int) $set['id'] : 0;
    if ($set_id <= 0) {
        return 0;
    }

    if ($event instanceof WP_Post && $event->post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $service->attach_context($set_id, ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, (int) $event->ID, 'gallery_candidate');
    }

    $item_id = $service->add_item([
        'set_id' => $set_id,
        'kind' => 'wp_media',
        'source' => 'wp-media',
        'source_id' => (string) $attachment_id,
        'status' => 'pending',
        'label' => (string) get_the_title($attachment_id),
        'origin' => 'event_drop',
        'provenance' => [
            'stored_name' => $stored_name,
            'original_name' => (string) get_post_meta($attachment_id, '_event_drop_original_name', true),
            'sha256' => (string) get_post_meta($attachment_id, '_event_drop_sha256', true),
            'uploaded_at' => (string) get_post_meta($attachment_id, '_event_drop_uploaded_at', true),
            'uploader' => (string) get_post_meta($attachment_id, '_event_drop_uploader', true),
        ],
        'rights' => [
            'attribution' => (string) get_post_meta($attachment_id, '_event_drop_attribution', true),
            'license' => (string) get_post_meta($attachment_id, '_event_drop_license', true),
            'consent' => (string) get_post_meta($attachment_id, '_event_drop_consent', true),
        ],
    ]);

    return $item_id;
}

function iss_content_editorial_sets_watch_event_drop_meta($meta_id, int $object_id, string $meta_key): void
{
    if ($meta_key !== '_event_drop_stored_name') {
        return;
    }

    iss_content_editorial_sets_event_drop_set_for_attachment($object_id);
}
add_action('added_post_meta', 'iss_content_editorial_sets_watch_event_drop_meta', 10, 3);
add_action('updated_post_meta', 'iss_content_editorial_sets_watch_event_drop_meta', 10, 3);
