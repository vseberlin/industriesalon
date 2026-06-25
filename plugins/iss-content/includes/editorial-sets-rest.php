<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_editorial_sets_rest_namespace(): string
{
    return 'iss-content/v1';
}

function iss_content_editorial_sets_can_manage(): bool
{
    return current_user_can(ISS_CONTENT_EDITORIAL_SETS_CAPABILITY);
}

function iss_content_editorial_sets_current_user_can_any(array $capabilities): bool
{
    foreach ($capabilities as $capability) {
        if (current_user_can(sanitize_key((string) $capability))) {
            return true;
        }
    }

    return false;
}

function iss_content_editorial_sets_can_access(): bool
{
    return iss_content_editorial_sets_current_user_can_any([
        'iss_create_sets',
        'iss_edit_sets',
        'iss_review_sets',
        'iss_promote_media',
        'iss_delete_sets',
        'iss_cleanup_sets',
    ]);
}

function iss_content_editorial_sets_request_context_id(WP_REST_Request $request): int
{
    return absint(
        $request->get_param('contextId')
        ?: $request->get_param('context_id')
        ?: $request->get_param('targetId')
        ?: $request->get_param('target_id')
    );
}

function iss_content_editorial_sets_can_use_context(WP_REST_Request $request, string $capability = ''): bool
{
    $capability = sanitize_key($capability);
    if ($capability !== '' && !current_user_can($capability)) {
        return false;
    }

    if ($capability === '' && !iss_content_editorial_sets_can_access()) {
        return false;
    }

    $context_id = iss_content_editorial_sets_request_context_id($request);
    if ($context_id <= 0) {
        return true;
    }

    return $context_id > 0 && current_user_can('edit_post', $context_id);
}

function iss_content_editorial_sets_can_create(WP_REST_Request $request): bool
{
    return iss_content_editorial_sets_can_use_context($request, 'iss_create_sets');
}

function iss_content_editorial_sets_can_edit(WP_REST_Request $request): bool
{
    return iss_content_editorial_sets_can_use_context($request, 'iss_edit_sets');
}

function iss_content_editorial_sets_can_review(WP_REST_Request $request): bool
{
    return iss_content_editorial_sets_can_use_context($request, 'iss_review_sets');
}

function iss_content_editorial_sets_can_promote(WP_REST_Request $request): bool
{
    return iss_content_editorial_sets_can_use_context($request, 'iss_promote_media');
}

function iss_content_editorial_sets_can_batch(WP_REST_Request $request): bool
{
    $action = sanitize_key((string) $request->get_param('action'));
    if ($action === 'move') {
        return iss_content_editorial_sets_can_edit($request);
    }
    if ($action === 'archive_candidate') {
        return iss_content_editorial_sets_can_review($request);
    }

    return iss_content_editorial_sets_can_review($request);
}

function iss_content_editorial_sets_rest_list_sets(WP_REST_Request $request): WP_REST_Response
{
    if (function_exists('iss_content_editorial_sets_sync_event_drop_incoming')) {
        iss_content_editorial_sets_sync_event_drop_incoming();
    }

    $service = iss_content_editorial_sets_service();
    $result = $service->search_sets([
        'search' => (string) $request->get_param('search'),
        'status' => (string) $request->get_param('status'),
        'set_role' => (string) ($request->get_param('setRole') ?: $request->get_param('set_role')),
        'context_type' => (string) ($request->get_param('contextType') ?: $request->get_param('context_type')),
        'context_id' => absint($request->get_param('contextId') ?: $request->get_param('context_id')),
        'page' => (int) $request->get_param('page'),
        'per_page' => (int) ($request->get_param('perPage') ?: $request->get_param('per_page') ?: 30),
    ]);

    return rest_ensure_response([
        'items' => array_map([$service, 'prepare_set'], (array) ($result['items'] ?? [])),
        'page' => (int) ($result['page'] ?? 1),
        'perPage' => (int) ($result['per_page'] ?? 30),
    ]);
}

function iss_content_editorial_sets_rest_create_set(WP_REST_Request $request)
{
    $service = iss_content_editorial_sets_service();
    $set_id = $service->create_set([
        'title' => (string) $request->get_param('title'),
        'summary' => (string) $request->get_param('summary'),
        'set_role' => (string) ($request->get_param('setRole') ?: $request->get_param('set_role') ?: 'intake'),
        'status' => (string) ($request->get_param('status') ?: 'working'),
    ]);

    if ($set_id <= 0) {
        return new WP_Error('iss_content_set_create_failed', __('Set could not be created.', 'iss-content-model'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('set_create', [
            'capability' => 'iss_create_sets',
            'object_ids' => [$set_id],
            'result' => 'completed',
        ]);
    }

    $context_type = (string) ($request->get_param('contextType') ?: $request->get_param('context_type'));
    $context_id = absint($request->get_param('contextId') ?: $request->get_param('context_id'));
    if ($context_type !== '' && $context_id > 0) {
        $service->attach_context($set_id, $context_type, $context_id, (string) ($request->get_param('linkRole') ?: 'source_material'));
    }

    $set = $service->get_set($set_id);

    return rest_ensure_response(['item' => $set ? $service->prepare_set($set) : null]);
}

function iss_content_editorial_sets_rest_get_set(WP_REST_Request $request)
{
    $service = iss_content_editorial_sets_service();
    $set = $service->get_set(absint($request->get_param('id')));
    if (!$set) {
        return new WP_Error('iss_content_set_not_found', __('Set not found.', 'iss-content-model'), ['status' => 404]);
    }

    return rest_ensure_response(['item' => $service->prepare_set($set)]);
}

function iss_content_editorial_sets_rest_update_set(WP_REST_Request $request)
{
    $service = iss_content_editorial_sets_service();
    $set_id = absint($request->get_param('id'));
    $ok = $service->update_set($set_id, [
        'title' => (string) $request->get_param('title'),
        'summary' => (string) $request->get_param('summary'),
        'set_role' => (string) ($request->get_param('setRole') ?: $request->get_param('set_role') ?: 'intake'),
        'status' => (string) ($request->get_param('status') ?: 'working'),
    ]);

    if (!$ok) {
        return new WP_Error('iss_content_set_update_failed', __('Set could not be updated.', 'iss-content-model'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('set_update', [
            'capability' => 'iss_edit_sets',
            'object_ids' => [$set_id],
            'result' => 'completed',
        ]);
    }

    return iss_content_editorial_sets_rest_get_set($request);
}

function iss_content_editorial_sets_rest_list_items(WP_REST_Request $request): WP_REST_Response
{
    if (function_exists('iss_content_editorial_sets_sync_event_drop_incoming')) {
        iss_content_editorial_sets_sync_event_drop_incoming();
    }

    $service = iss_content_editorial_sets_service();
    $result = $service->list_items([
        'set_id' => $request->get_param('setId') !== null ? absint($request->get_param('setId')) : null,
        'status' => (string) $request->get_param('status'),
        'stale' => rest_sanitize_boolean($request->get_param('stale')),
        'page' => (int) $request->get_param('page'),
        'per_page' => (int) ($request->get_param('perPage') ?: $request->get_param('per_page') ?: 60),
    ]);

    return rest_ensure_response([
        'items' => array_map([$service, 'prepare_item'], (array) ($result['items'] ?? [])),
        'page' => (int) ($result['page'] ?? 1),
        'perPage' => (int) ($result['per_page'] ?? 60),
    ]);
}

function iss_content_editorial_sets_rest_add_item(WP_REST_Request $request)
{
    $service = iss_content_editorial_sets_service();
    $item_id = $service->add_item([
        'set_id' => absint($request->get_param('setId') ?: $request->get_param('set_id')),
        'kind' => (string) ($request->get_param('kind') ?: 'wp_media'),
        'source' => (string) ($request->get_param('source') ?: 'wp-media'),
        'source_id' => (string) ($request->get_param('sourceId') ?: $request->get_param('source_id') ?: $request->get_param('id')),
        'status' => (string) ($request->get_param('status') ?: 'pending'),
        'label' => (string) $request->get_param('label'),
        'origin' => (string) ($request->get_param('origin') ?: 'manual_upload'),
        'provenance' => $request->get_param('provenance'),
        'rights' => $request->get_param('rights'),
        'notes' => (string) $request->get_param('notes'),
        'decay_at' => (string) ($request->get_param('decayAt') ?: $request->get_param('decay_at')),
    ]);

    if ($item_id <= 0) {
        return new WP_Error('iss_content_set_item_add_failed', __('Item could not be added.', 'iss-content-model'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('set_item_add', [
            'capability' => 'iss_create_sets',
            'object_ids' => [$item_id],
            'result' => 'completed',
        ]);
    }

    $item = $service->get_item($item_id);
    return rest_ensure_response(['item' => $item ? $service->prepare_item($item) : null]);
}

function iss_content_editorial_sets_rest_upload_files(WP_REST_Request $request)
{
    $service = iss_content_editorial_sets_service();
    $set_id = absint($request->get_param('setId') ?: $request->get_param('set_id'));
    $set = $service->get_set($set_id);
    if (!$set) {
        return new WP_Error('iss_content_set_missing', __('Select a Set before uploading.', 'iss-content-model'), ['status' => 400]);
    }

    $root = function_exists('iss_content_editorial_sets_event_drop_storage_root')
        ? iss_content_editorial_sets_event_drop_storage_root()
        : '/event-drop-storage';
    $incoming_dir = rtrim($root, '/\\') . '/incoming';
    if (!wp_mkdir_p($incoming_dir) || !is_dir($incoming_dir) || !is_writable($incoming_dir)) {
        return new WP_Error('iss_content_set_upload_storage_unavailable', __('Raw upload storage is unavailable.', 'iss-content-model'), ['status' => 500]);
    }

    $files = iss_content_editorial_sets_rest_uploaded_files($request);
    if (!$files) {
        return new WP_Error('iss_content_set_upload_missing', __('No files were uploaded.', 'iss-content-model'), ['status' => 400]);
    }

    $created = [];
    foreach ($files as $file) {
        $item = iss_content_editorial_sets_store_raw_upload_file($file, $set_id, $request);
        if (is_wp_error($item)) {
            return $item;
        }
        if (is_array($item)) {
            $created[] = $item;
        }
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('set_raw_upload', [
            'capability' => 'iss_create_sets',
            'object_ids' => array_map(static function (array $item): int {
                return (int) ($item['id'] ?? 0);
            }, $created),
            'result' => 'completed',
        ]);
    }

    return rest_ensure_response(['items' => array_map([$service, 'prepare_item'], $created)]);
}

function iss_content_editorial_sets_rest_uploaded_files(WP_REST_Request $request): array
{
    $params = $request->get_file_params();
    $input = $params['files'] ?? $params['file'] ?? [];
    if (!$input) {
        return [];
    }

    if (isset($input['name']) && is_array($input['name'])) {
        $files = [];
        foreach ($input['name'] as $index => $name) {
            $files[] = [
                'name' => (string) $name,
                'type' => (string) ($input['type'][$index] ?? ''),
                'tmp_name' => (string) ($input['tmp_name'][$index] ?? ''),
                'error' => (int) ($input['error'][$index] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int) ($input['size'][$index] ?? 0),
            ];
        }

        return $files;
    }

    return isset($input['name']) ? [$input] : [];
}

function iss_content_editorial_sets_store_raw_upload_file(array $file, int $set_id, WP_REST_Request $request)
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error !== UPLOAD_ERR_OK) {
        return new WP_Error('iss_content_set_upload_failed', __('File upload failed.', 'iss-content-model'), ['status' => 400]);
    }

    $tmp_name = (string) ($file['tmp_name'] ?? '');
    $original_name = sanitize_file_name((string) ($file['name'] ?? ''));
    if ($tmp_name === '' || !is_readable($tmp_name) || $original_name === '') {
        return new WP_Error('iss_content_set_upload_invalid', __('Uploaded file is invalid.', 'iss-content-model'), ['status' => 400]);
    }

    $file_type = wp_check_filetype_and_ext($tmp_name, $original_name);
    if (!is_array($file_type) || empty($file_type['type'])) {
        return new WP_Error('iss_content_set_upload_type_invalid', __('Uploaded file type is not allowed.', 'iss-content-model'), ['status' => 400]);
    }

    $root = function_exists('iss_content_editorial_sets_event_drop_storage_root')
        ? iss_content_editorial_sets_event_drop_storage_root()
        : '/event-drop-storage';
    $incoming_dir = rtrim($root, '/\\') . '/incoming';
    $stored_name = wp_unique_filename(
        $incoming_dir,
        sanitize_file_name($set_id . '-' . gmdate('Ymd-His') . '-' . $original_name)
    );
    $target_path = rtrim($incoming_dir, '/\\') . '/' . $stored_name;

    if (!move_uploaded_file($tmp_name, $target_path) && !rename($tmp_name, $target_path)) {
        return new WP_Error('iss_content_set_upload_store_failed', __('Uploaded file could not be stored.', 'iss-content-model'), ['status' => 500]);
    }

    $context_type = sanitize_key((string) ($request->get_param('contextType') ?: $request->get_param('context_type')));
    $context_id = absint($request->get_param('contextId') ?: $request->get_param('context_id'));
    $sha256 = is_readable($target_path) ? (string) hash_file('sha256', $target_path) : '';
    $now = gmdate('c');

    $service = iss_content_editorial_sets_service();
    $item_id = $service->add_item([
        'set_id' => $set_id,
        'kind' => 'external_upload',
        'source' => 'event-drop',
        'source_id' => $stored_name,
        'status' => 'pending',
        'label' => $original_name,
        'origin' => 'workbench_upload',
        'provenance' => [
            'storage_state' => 'incoming',
            'stored_name' => $stored_name,
            'original_name' => $original_name,
            'path' => $target_path,
            'context_type' => $context_type,
            'context_id' => $context_id > 0 ? (string) $context_id : '',
            'set_id' => (string) $set_id,
            'size_bytes' => (string) filesize($target_path),
            'sha256' => $sha256,
            'mime_type' => (string) $file_type['type'],
            'uploaded_at' => $now,
        ],
        'rights' => [
            'license' => 'all-rights-reserved',
            'consent' => '',
            'uploader_id' => (string) get_current_user_id(),
        ],
    ]);

    if ($item_id <= 0) {
        return new WP_Error('iss_content_set_item_add_failed', __('Item could not be added.', 'iss-content-model'), ['status' => 400]);
    }

    $item = $service->get_item($item_id);
    return $item ?: [];
}

function iss_content_editorial_sets_rest_update_item(WP_REST_Request $request)
{
    $service = iss_content_editorial_sets_service();
    $item_id = absint($request->get_param('id'));
    $ok = $service->update_item($item_id, [
        'status' => (string) $request->get_param('status'),
        'label' => (string) $request->get_param('label'),
        'notes' => (string) $request->get_param('notes'),
        'rights' => $request->get_param('rights'),
        'provenance' => $request->get_param('provenance'),
        'decay_at' => (string) ($request->get_param('decayAt') ?: $request->get_param('decay_at')),
        'retain' => rest_sanitize_boolean($request->get_param('retain')),
        'retain_reason' => (string) ($request->get_param('retainReason') ?: $request->get_param('retain_reason')),
    ]);

    if (!$ok) {
        return new WP_Error('iss_content_set_item_update_failed', __('Item could not be updated.', 'iss-content-model'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('set_item_update', [
            'capability' => 'iss_review_sets',
            'object_ids' => [$item_id],
            'result' => 'completed',
        ]);
    }

    $item = $service->get_item($item_id);
    return rest_ensure_response(['item' => $item ? $service->prepare_item($item) : null]);
}

function iss_content_editorial_sets_rest_batch(WP_REST_Request $request)
{
    $service = iss_content_editorial_sets_service();
    $action = sanitize_key((string) $request->get_param('action'));
    $item_ids = array_values(array_filter(array_map('absint', (array) $request->get_param('itemIds'))));

    if (!$item_ids) {
        return new WP_Error('iss_content_set_items_missing', __('No items selected.', 'iss-content-model'), ['status' => 400]);
    }

    if (in_array($action, ['approve', 'reject', 'review', 'retain', 'stale', 'restore'], true)) {
        $status_map = [
            'approve' => 'approved',
            'reject' => 'rejected',
            'review' => 'reviewing',
            'retain' => 'retained',
            'stale' => 'stale',
            'restore' => 'pending',
        ];
        $updated = function_exists('iss_content_editorial_sets_event_drop_moderate_items')
            ? iss_content_editorial_sets_event_drop_moderate_items($item_ids, $action)
            : $service->batch_update_status($item_ids, $status_map[$action]);
        if (function_exists('iss_core_audit_log')) {
            iss_core_audit_log('set_items_' . $action, [
                'capability' => 'iss_review_sets',
                'object_ids' => $item_ids,
                'result' => 'completed',
                'updated' => $updated,
            ]);
        }
        return rest_ensure_response(['updated' => $updated]);
    }

    if ($action === 'move') {
        $moved = $service->move_items($item_ids, absint($request->get_param('setId') ?: $request->get_param('set_id')));
        if (function_exists('iss_core_audit_log')) {
            iss_core_audit_log('set_items_move', [
                'capability' => 'iss_edit_sets',
                'object_ids' => $item_ids,
                'result' => 'completed',
                'updated' => $moved,
            ]);
        }
        return rest_ensure_response(['updated' => $moved]);
    }

    if ($action === 'archive_candidate') {
        $updated = 0;
        foreach ($item_ids as $item_id) {
            if ($service->mark_archive_candidate($item_id, (array) $request->get_param('metadata'))) {
                $updated++;
            }
        }
        if (function_exists('iss_core_audit_log')) {
            iss_core_audit_log('set_items_archive_candidate', [
                'capability' => 'iss_review_sets',
                'object_ids' => $item_ids,
                'result' => 'completed',
                'updated' => $updated,
            ]);
        }
        return rest_ensure_response(['updated' => $updated]);
    }

    return new WP_Error('iss_content_set_batch_action_unknown', __('Unknown batch action.', 'iss-content-model'), ['status' => 400]);
}

function iss_content_editorial_sets_rest_attach(WP_REST_Request $request)
{
    $service = iss_content_editorial_sets_service();
    $link_id = $service->attach_context(
        absint($request->get_param('id')),
        (string) ($request->get_param('contextType') ?: $request->get_param('context_type')),
        absint($request->get_param('contextId') ?: $request->get_param('context_id')),
        (string) ($request->get_param('linkRole') ?: $request->get_param('link_role') ?: 'source_material')
    );

    if ($link_id <= 0) {
        return new WP_Error('iss_content_set_attach_failed', __('Set could not be attached.', 'iss-content-model'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('set_attach_context', [
            'capability' => 'iss_edit_sets',
            'object_ids' => [absint($request->get_param('id')), absint($request->get_param('contextId') ?: $request->get_param('context_id'))],
            'result' => 'completed',
        ]);
    }

    return rest_ensure_response(['id' => $link_id]);
}

function iss_content_editorial_sets_rest_promote(WP_REST_Request $request)
{
    $item_ids = array_values(array_filter(array_map('absint', (array) $request->get_param('itemIds'))));
    $result = iss_content_editorial_sets_promote(
        absint($request->get_param('targetId') ?: $request->get_param('target_id')),
        (string) ($request->get_param('targetType') ?: $request->get_param('target_type')),
        $item_ids,
        [
            'sectionTitle' => (string) $request->get_param('sectionTitle'),
            'sectionType' => (string) $request->get_param('sectionType'),
        ]
    );

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('media_promotion', [
            'capability' => 'iss_promote_media',
            'object_ids' => array_merge([absint($request->get_param('targetId') ?: $request->get_param('target_id'))], $item_ids),
            'result' => 'completed',
        ]);
    }

    return rest_ensure_response($result);
}

function iss_content_editorial_sets_register_rest_routes(): void
{
    $namespace = iss_content_editorial_sets_rest_namespace();

    register_rest_route($namespace, '/editorial-sets', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_content_editorial_sets_rest_list_sets',
            'permission_callback' => 'iss_content_editorial_sets_can_use_context',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_content_editorial_sets_rest_create_set',
            'permission_callback' => 'iss_content_editorial_sets_can_create',
        ],
    ]);

    register_rest_route($namespace, '/editorial-sets/(?P<id>\d+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_content_editorial_sets_rest_get_set',
            'permission_callback' => 'iss_content_editorial_sets_can_use_context',
        ],
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'iss_content_editorial_sets_rest_update_set',
            'permission_callback' => 'iss_content_editorial_sets_can_edit',
        ],
    ]);

    register_rest_route($namespace, '/editorial-set-items', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_content_editorial_sets_rest_list_items',
            'permission_callback' => 'iss_content_editorial_sets_can_use_context',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_content_editorial_sets_rest_add_item',
            'permission_callback' => 'iss_content_editorial_sets_can_create',
        ],
    ]);

    register_rest_route($namespace, '/editorial-set-items/upload', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_content_editorial_sets_rest_upload_files',
            'permission_callback' => 'iss_content_editorial_sets_can_create',
        ],
    ]);

    register_rest_route($namespace, '/editorial-set-items/(?P<id>\d+)', [
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'iss_content_editorial_sets_rest_update_item',
            'permission_callback' => 'iss_content_editorial_sets_can_review',
        ],
    ]);

    register_rest_route($namespace, '/editorial-set-items/batch', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_content_editorial_sets_rest_batch',
            'permission_callback' => 'iss_content_editorial_sets_can_batch',
        ],
    ]);

    register_rest_route($namespace, '/editorial-sets/(?P<id>\d+)/attach', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_content_editorial_sets_rest_attach',
            'permission_callback' => 'iss_content_editorial_sets_can_edit',
        ],
    ]);

    register_rest_route($namespace, '/editorial-set-items/promote', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_content_editorial_sets_rest_promote',
            'permission_callback' => 'iss_content_editorial_sets_can_promote',
        ],
    ]);
}
add_action('rest_api_init', 'iss_content_editorial_sets_register_rest_routes');
