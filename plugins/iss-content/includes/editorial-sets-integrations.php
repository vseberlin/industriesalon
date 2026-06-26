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

function iss_content_editorial_sets_ensure_project_source_set(int $post_id, ?WP_Post $post = null): int
{
    $post = $post instanceof WP_Post ? $post : get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return 0;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id) || in_array((string) $post->post_status, ['auto-draft', 'trash'], true)) {
        return 0;
    }

    $service = iss_content_editorial_sets_service();
    $links = $service->get_links_for_context(ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, $post_id);
    foreach ($links as $link) {
        if ((string) ($link['link_role'] ?? '') === 'source_material') {
            return iss_content_editorial_sets_normalize_project_source_set_key((int) ($link['set_id'] ?? 0), $post);
        }
    }
    if ($links) {
        $set_id = (int) ($links[0]['set_id'] ?? 0);
        if ($set_id > 0) {
            $service->attach_context($set_id, ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, $post_id, 'source_material');
        }

        return iss_content_editorial_sets_normalize_project_source_set_key($set_id, $post);
    }

    $set_id = $service->create_set([
        'set_key' => iss_content_editorial_sets_project_source_set_key($post),
        'title' => function_exists('iss_content_editorial_sets_context_set_title')
            ? iss_content_editorial_sets_context_set_title($post)
            : sprintf('%s Set', (string) get_the_title($post)),
        'set_role' => 'source_material',
        'status' => 'working',
    ]);

    if ($set_id > 0) {
        $service->attach_context($set_id, ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, $post_id, 'source_material');
    }

    return $set_id;
}

function iss_content_editorial_sets_project_source_set_key(WP_Post $post): string
{
    if ($post->post_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE || (string) $post->post_name === '') {
        return '';
    }

    return iss_content_editorial_sets_service()->normalize_key('project-set-' . $post->post_name);
}

function iss_content_editorial_sets_normalize_project_source_set_key(int $set_id, WP_Post $post): int
{
    if ($set_id <= 0 || $post->post_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return $set_id;
    }

    $service = iss_content_editorial_sets_service();
    $set = $service->get_set($set_id);
    $canonical_key = iss_content_editorial_sets_project_source_set_key($post);
    if (!$set || $canonical_key === '' || (string) ($set['set_key'] ?? '') === $canonical_key) {
        return $set_id;
    }

    $existing = $service->get_set_by_key($canonical_key);
    if (!$existing) {
        $service->update_set_key($set_id, $canonical_key);
    }

    return $set_id;
}

function iss_content_editorial_sets_ensure_project_source_set_on_save(int $post_id, WP_Post $post, bool $update): void
{
    unset($update);
    iss_content_editorial_sets_ensure_project_source_set($post_id, $post);
}
add_action('save_post_' . ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, 'iss_content_editorial_sets_ensure_project_source_set_on_save', 20, 3);

function iss_content_editorial_sets_set_has_live_context(int $set_id, string $deleted_context_type, int $deleted_context_id): bool
{
    $links = iss_content_editorial_sets_service()->get_links_for_set($set_id);
    foreach ($links as $link) {
        $context_type = (string) ($link['context_type'] ?? '');
        $context_id = (int) ($link['context_id'] ?? 0);
        if ($context_type === $deleted_context_type && $context_id === $deleted_context_id) {
            continue;
        }

        $context_post = $context_id > 0 ? get_post($context_id) : null;
        if (!$context_post instanceof WP_Post) {
            return true;
        }
        if (!in_array((string) $context_post->post_status, ['trash', 'auto-draft'], true)) {
            return true;
        }
    }

    return false;
}

function iss_content_editorial_sets_project_item_is_disposable_raw(array $item): bool
{
    if ((string) ($item['kind'] ?? '') !== 'external_upload' || (string) ($item['source'] ?? '') !== 'event-drop') {
        return false;
    }
    if (!empty($item['retain']) || (string) ($item['archive_candidate_status'] ?? '') !== '') {
        return false;
    }

    return in_array((string) ($item['status'] ?? ''), ['pending', 'reviewing', 'approved', 'stale'], true);
}

function iss_content_editorial_sets_quarantine_project_raw_intake(int $post_id): void
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return;
    }

    $service = iss_content_editorial_sets_service();
    $links = $service->get_links_for_context(ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, $post_id);
    foreach ($links as $link) {
        $set_id = (int) ($link['set_id'] ?? 0);
        if ($set_id <= 0 || iss_content_editorial_sets_set_has_live_context($set_id, ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, $post_id)) {
            continue;
        }

        $items = $service->list_items(['set_id' => $set_id, 'per_page' => 120]);
        $quarantined = 0;
        foreach ((array) ($items['items'] ?? []) as $item) {
            if (!iss_content_editorial_sets_project_item_is_disposable_raw($item)) {
                continue;
            }
            if (iss_content_editorial_sets_event_drop_moderate_item((int) ($item['id'] ?? 0), 'reject')) {
                $quarantined++;
            }
        }

        $set = $service->get_set($set_id);
        if ($set) {
            $service->update_set($set_id, [
                'title' => (string) ($set['title'] ?? ''),
                'summary' => (string) ($set['summary'] ?? ''),
                'set_role' => (string) ($set['set_role'] ?? 'source_material'),
                'status' => 'stale',
            ]);
            $service->record_audit($set_id, 0, 'project_deleted_raw_intake_quarantined', __('Project raw intake quarantined after project deletion.', 'iss-content-model'), [
                'context_type' => ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
                'context_id' => $post_id,
                'quarantined' => $quarantined,
            ]);
        }
    }
}
add_action('trashed_post', 'iss_content_editorial_sets_quarantine_project_raw_intake', 20);
add_action('before_delete_post', 'iss_content_editorial_sets_quarantine_project_raw_intake', 20);

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

function iss_content_editorial_sets_event_drop_target_context(string $target_key): array
{
    $target_key = sanitize_title($target_key);
    if ($target_key === '') {
        return [];
    }

    $project_prefix = 'projekt__';
    if (strpos($target_key, $project_prefix) === 0) {
        $project_slug = sanitize_title(substr($target_key, strlen($project_prefix)));
        $project = $project_slug !== '' ? get_page_by_path($project_slug, OBJECT, ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) : null;
        if ($project instanceof WP_Post) {
            $set_id = iss_content_editorial_sets_ensure_project_source_set((int) $project->ID, $project);

            return [
                'post' => $project,
                'context_type' => ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
                'context_id' => (int) $project->ID,
                'set_id' => $set_id,
                'set_key' => iss_content_editorial_sets_project_source_set_key($project),
                'set_title' => function_exists('iss_content_editorial_sets_context_set_title')
                    ? iss_content_editorial_sets_context_set_title($project)
                    : sprintf('%s Set', (string) get_the_title($project)),
                'set_role' => 'source_material',
                'link_role' => 'source_material',
            ];
        }
    }

    $event = iss_content_editorial_sets_event_drop_context_post($target_key);
    if ($event instanceof WP_Post) {
        return [
            'post' => $event,
            'context_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
            'context_id' => (int) $event->ID,
            'set_id' => 0,
            'set_key' => 'event-drop-' . $target_key,
            'set_title' => sprintf(__('Event Drop: %s', 'iss-content-model'), get_the_title($event)),
            'set_role' => 'intake',
            'link_role' => 'gallery_candidate',
        ];
    }

    return [
        'post' => null,
        'context_type' => '',
        'context_id' => 0,
        'set_id' => 0,
        'set_key' => 'event-drop-' . $target_key,
        'set_title' => __('Event Drop intake', 'iss-content-model'),
        'set_role' => 'intake',
        'link_role' => '',
    ];
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
        $target_key = sanitize_title((string) ($meta['event_slug'] ?? ''));
        $target = iss_content_editorial_sets_event_drop_target_context($target_key);
        $set_id = (int) ($target['set_id'] ?? 0);
        $set_key = $service->normalize_key((string) (($target['set_key'] ?? '') ?: 'event-drop-uncategorized'));
        $set = $set_id > 0 ? $service->get_set($set_id) : $service->get_set_by_key($set_key);

        if (!$set) {
            $set_id = $service->create_set([
                'set_key' => $set_key,
                'title' => (string) ($target['set_title'] ?? __('Event Drop intake', 'iss-content-model')),
                'set_role' => (string) (($target['set_role'] ?? '') ?: 'intake'),
                'status' => 'working',
            ]);
            $set = $set_id > 0 ? $service->get_set($set_id) : null;
        }

        $set_id = is_array($set) ? (int) $set['id'] : 0;
        if ($set_id <= 0) {
            continue;
        }

        $context_type = (string) ($target['context_type'] ?? '');
        $context_id = (int) ($target['context_id'] ?? 0);
        $link_role = (string) ($target['link_role'] ?? '');
        if ($context_type !== '' && $context_id > 0 && $link_role !== '') {
            $service->attach_context($set_id, $context_type, $context_id, $link_role);
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
                'event_slug' => $target_key,
                'target_type' => $context_type,
                'target_id' => $context_id > 0 ? (string) $context_id : '',
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
    $parent = $event_id > 0 ? get_post($event_id) : null;
    $service = iss_content_editorial_sets_service();
    $target_key = sanitize_title((string) get_post_meta($attachment_id, '_event_drop_event_ref', true));
    if ($target_key === '' && $parent instanceof WP_Post) {
        if ($parent->post_type === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
            $target_key = 'projekt__' . $parent->post_name;
        } elseif ($parent->post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
            $target_key = $parent->post_name;
        }
    }

    $target = iss_content_editorial_sets_event_drop_target_context($target_key);
    $set_id = (int) ($target['set_id'] ?? 0);
    $set_key = $service->normalize_key((string) (($target['set_key'] ?? '') ?: 'event-drop-uncategorized'));
    $set = $set_id > 0 ? $service->get_set($set_id) : $service->get_set_by_key($set_key);
    if (!$set) {
        $set_id = $service->create_set([
            'set_key' => $set_key,
            'title' => (string) ($target['set_title'] ?? __('Event Drop intake', 'iss-content-model')),
            'set_role' => (string) (($target['set_role'] ?? '') ?: 'intake'),
            'status' => 'working',
        ]);
        $set = $set_id > 0 ? $service->get_set($set_id) : null;
    }

    $set_id = is_array($set) ? (int) $set['id'] : 0;
    if ($set_id <= 0) {
        return 0;
    }

    $context_type = (string) ($target['context_type'] ?? '');
    $context_id = (int) ($target['context_id'] ?? 0);
    $link_role = (string) ($target['link_role'] ?? '');
    if ($context_type !== '' && $context_id > 0 && $link_role !== '') {
        $service->attach_context($set_id, $context_type, $context_id, $link_role);
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
            'event_slug' => $target_key,
            'target_type' => $context_type,
            'target_id' => $context_id > 0 ? (string) $context_id : '',
        ],
        'rights' => [
            'attribution' => (string) get_post_meta($attachment_id, '_event_drop_attribution', true),
            'license' => (string) get_post_meta($attachment_id, '_event_drop_license', true),
            'consent' => (string) get_post_meta($attachment_id, '_event_drop_consent', true),
        ],
    ]);

    return $item_id;
}

function iss_content_editorial_sets_normalize_project_duplicate_sets(int $project_id = 0): array
{
    $service = iss_content_editorial_sets_service();
    $query_args = [
        'post_type' => ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        'post_status' => 'any',
        'numberposts' => -1,
    ];
    if ($project_id > 0) {
        $query_args['include'] = [$project_id];
    }

    $result = [
        'projects' => 0,
        'renamed' => 0,
        'merged_sets' => 0,
        'moved_items' => 0,
        'skipped_sets' => 0,
    ];

    foreach (get_posts($query_args) as $project) {
        if (!$project instanceof WP_Post) {
            continue;
        }

        $duplicate_keys = array_unique(array_filter([
            $service->normalize_key('event-drop-' . $project->post_name),
            $service->normalize_key('event-drop-projekt__' . $project->post_name),
        ]));
        $links = $service->get_links_for_context(ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, (int) $project->ID);
        $has_project_duplicate = false;
        foreach ($duplicate_keys as $duplicate_key) {
            $duplicate = $service->get_set_by_key($duplicate_key);
            $duplicate_set_id = is_array($duplicate) ? (int) ($duplicate['id'] ?? 0) : 0;
            if ($duplicate_set_id <= 0) {
                continue;
            }

            $has_foreign_link = false;
            foreach ($service->get_links_for_set($duplicate_set_id) as $duplicate_link) {
                $context_type = (string) ($duplicate_link['context_type'] ?? '');
                $context_id = (int) ($duplicate_link['context_id'] ?? 0);
                if ($context_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE || $context_id !== (int) $project->ID) {
                    $has_foreign_link = true;
                    break;
                }
            }
            if (!$has_foreign_link) {
                $has_project_duplicate = true;
                break;
            }
        }
        if (!$has_project_duplicate && $links === []) {
            continue;
        }

        $before_set_key = '';
        foreach ($links as $link) {
            if ((string) ($link['link_role'] ?? '') === 'source_material') {
                $before_set_key = (string) ($link['set_key'] ?? '');
                break;
            }
        }

        $target_set_id = iss_content_editorial_sets_ensure_project_source_set((int) $project->ID, $project);
        if ($target_set_id <= 0) {
            continue;
        }

        $result['projects']++;
        $target_set = $service->get_set($target_set_id);
        if (
            $target_set
            && $before_set_key !== ''
            && $before_set_key !== (string) ($target_set['set_key'] ?? '')
            && (string) ($target_set['set_key'] ?? '') === iss_content_editorial_sets_project_source_set_key($project)
        ) {
            $result['renamed']++;
        }

        foreach ($duplicate_keys as $duplicate_key) {
            $duplicate = $service->get_set_by_key($duplicate_key);
            $duplicate_set_id = is_array($duplicate) ? (int) ($duplicate['id'] ?? 0) : 0;
            if ($duplicate_set_id <= 0 || $duplicate_set_id === $target_set_id) {
                continue;
            }

            $has_foreign_link = false;
            foreach ($service->get_links_for_set($duplicate_set_id) as $link) {
                $context_type = (string) ($link['context_type'] ?? '');
                $context_id = (int) ($link['context_id'] ?? 0);
                if ($context_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE || $context_id !== (int) $project->ID) {
                    $has_foreign_link = true;
                    break;
                }
            }

            if ($has_foreign_link) {
                $result['skipped_sets']++;
                continue;
            }

            $items = $service->list_items(['set_id' => $duplicate_set_id, 'per_page' => 120]);
            $item_ids = [];
            foreach ((array) ($items['items'] ?? []) as $item) {
                if ((string) ($item['status'] ?? '') === 'promoted' || (string) ($item['archive_candidate_status'] ?? '') !== '') {
                    continue;
                }
                $item_ids[] = (int) ($item['id'] ?? 0);
            }

            $moved = $item_ids ? $service->move_items($item_ids, $target_set_id) : 0;
            $result['moved_items'] += $moved;
            $service->record_audit($target_set_id, 0, 'project_duplicate_set_merged', __('Project duplicate Event Drop set merged.', 'iss-content-model'), [
                'project_id' => (int) $project->ID,
                'duplicate_set_id' => $duplicate_set_id,
                'duplicate_set_key' => $duplicate_key,
                'moved_items' => $moved,
            ]);

            if ($service->delete_set_if_safe($duplicate_set_id)) {
                $result['merged_sets']++;
            } else {
                $result['skipped_sets']++;
            }
        }
    }

    return $result;
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
