<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_archivset_rest_namespace(): string
{
    return 'iss-archive/v1';
}

function iss_wf_import_archivset_rest_can_edit_sets(): bool
{
    return current_user_can(ISS_WF_IMPORT_ARCHIVSET_CAPABILITY);
}

function iss_wf_import_archivset_rest_can_list_or_create_sets(WP_REST_Request $request): bool
{
    $context_post_id = iss_wf_import_archivset_rest_get_context_post_id($request);
    if ($context_post_id > 0) {
        return current_user_can('edit_post', $context_post_id);
    }

    return iss_wf_import_archivset_rest_can_edit_sets();
}

function iss_wf_import_archivset_rest_can_edit_set(WP_REST_Request $request): bool
{
    if (iss_wf_import_archivset_rest_can_edit_sets()) {
        return true;
    }

    $context_post_id = iss_wf_import_archivset_rest_get_context_post_id($request);
    $set_id = absint($request->get_param('id') ?: $request->get_param('setId') ?: $request->get_param('set_id'));
    if ($context_post_id <= 0 || $set_id <= 0 || !current_user_can('edit_post', $context_post_id)) {
        return false;
    }

    return iss_wf_import_get_archivset_service()->set_is_attached_to_post($set_id, $context_post_id);
}

function iss_wf_import_archivset_rest_can_edit_content(WP_REST_Request $request): bool
{
    $post_id = absint($request->get_param('post_id'));

    return $post_id > 0 && current_user_can('edit_post', $post_id);
}

function iss_wf_import_archivset_rest_get_context_post_id(WP_REST_Request $request): int
{
    return absint(
        $request->get_param('contextPostId')
        ?: $request->get_param('context_post_id')
        ?: $request->get_param('attachToPostId')
        ?: $request->get_param('attach_to_post_id')
    );
}

function iss_wf_import_archivset_rest_decode_source_refs($value): array
{
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : [];
    }

    return iss_wf_import_sanitize_collection_source_ids(is_array($value) ? $value : []);
}

function iss_wf_import_archivset_rest_prepare_set(array $set, bool $include_members = false): array
{
    $service = iss_wf_import_get_archivset_service();
    $set_id = absint($set['id'] ?? 0);
    $legacy_collection_post_id = absint($set['legacy_collection_post_id'] ?? 0);
    $legacy_post = $legacy_collection_post_id > 0 ? get_post($legacy_collection_post_id) : null;
    $status = sanitize_key((string) ($set['status'] ?? 'active'));
    $status_labels = $service->get_status_labels();

    $item = [
        'id' => $set_id,
        'setKey' => (string) ($set['set_key'] ?? ''),
        'title' => (string) ($set['title'] ?? ''),
        'summary' => (string) ($set['summary'] ?? ''),
        'status' => $status,
        'statusLabel' => (string) ($status_labels[$status] ?? $status),
        'sourceSystem' => (string) ($set['source_system'] ?? ''),
        'sourceId' => (string) ($set['source_id'] ?? ''),
        'sourceUrl' => (string) ($set['source_url'] ?? ''),
        'sourceRefs' => iss_wf_import_archivset_rest_decode_source_refs((string) ($set['source_refs_json'] ?? '')),
        'legacyCollectionPostId' => $legacy_collection_post_id,
        'legacyCollectionTitle' => $legacy_post instanceof WP_Post ? (string) get_the_title($legacy_post) : '',
        'legacyCollectionEditUrl' => $legacy_post instanceof WP_Post ? (string) get_edit_post_link($legacy_post, '') : '',
        'memberCount' => (int) ($set['member_count'] ?? 0),
        'linkCount' => (int) ($set['link_count'] ?? 0),
        'linkPosition' => isset($set['link_position']) ? (int) $set['link_position'] : null,
        'linkRole' => (string) ($set['link_role'] ?? 'archive_material'),
        'editUrl' => add_query_arg(
            ['page' => 'iss-archive-sets', 'set_id' => $set_id],
            admin_url('edit.php?post_type=' . ISS_WF_IMPORT_POST_TYPE)
        ),
        'updatedAt' => (string) ($set['updated_at'] ?? ''),
    ];

    if ($include_members) {
        $item['members'] = array_map(
            'iss_wf_import_archivset_rest_prepare_member',
            $service->get_members($set_id)
        );
    }

    return $item;
}

function iss_wf_import_archivset_rest_prepare_member(array $member): array
{
    $object_post_id = absint($member['object_post_id'] ?? 0);
    $object = $object_post_id > 0 ? get_post($object_post_id) : null;
    $legacy_collection_post_id = absint($member['legacy_collection_post_id'] ?? 0);
    $legacy_collection = $legacy_collection_post_id > 0 ? get_post($legacy_collection_post_id) : null;
    $related_set_id = absint($member['related_set_id'] ?? 0);
    $display_title = (string) ($member['display_title'] ?? '');
    $member_title = (string) ($member['member_title'] ?? '');

    return [
        'id' => (int) ($member['id'] ?? 0),
        'setId' => (int) ($member['set_id'] ?? 0),
        'memberKind' => (string) ($member['member_kind'] ?? 'object'),
        'objectPostId' => $object_post_id,
        'relatedSetId' => $related_set_id,
        'legacyCollectionPostId' => $legacy_collection_post_id,
        'sourceObjectId' => (string) ($member['member_source_id'] ?? ''),
        'sourceUrl' => (string) ($member['member_source_url'] ?? ''),
        'memberTitle' => $member_title,
        'objectTitle' => $object instanceof WP_Post ? (string) get_the_title($object) : '',
        'legacyCollectionTitle' => $legacy_collection instanceof WP_Post ? (string) get_the_title($legacy_collection) : '',
        'title' => $display_title !== '' ? $display_title : $member_title,
        'displayTitle' => $display_title,
        'caption' => (string) ($member['caption'] ?? ''),
        'pageLabel' => (string) ($member['page_label'] ?? ''),
        'position' => (int) ($member['position'] ?? 0),
        'editUrl' => $object instanceof WP_Post ? (string) get_edit_post_link($object, '') : '',
        'permalink' => $object instanceof WP_Post ? (string) get_permalink($object) : '',
        'thumbnail' => $object instanceof WP_Post ? (string) get_the_post_thumbnail_url($object, 'thumbnail') : '',
    ];
}

function iss_wf_import_archivset_rest_prepare_object(WP_Post $post): array
{
    $object_id = (int) $post->ID;
    $projection = iss_wf_import_get_object_service()->ensure_projection_for_post($object_id);

    return [
        'id' => $object_id,
        'title' => (string) get_the_title($post),
        'excerpt' => wp_strip_all_tags((string) get_the_excerpt($post)),
        'editUrl' => (string) get_edit_post_link($post, ''),
        'permalink' => (string) get_permalink($post),
        'thumbnail' => (string) get_the_post_thumbnail_url($post, 'thumbnail'),
        'sourceSystem' => is_array($projection) ? (string) ($projection['source_system'] ?? '') : '',
        'sourceId' => is_array($projection) ? (string) ($projection['source_id'] ?? '') : '',
        'sourceUrl' => is_array($projection) ? (string) ($projection['source_url'] ?? '') : '',
        'inventoryNumber' => is_array($projection) ? (string) ($projection['inventory_number'] ?? '') : '',
        'objectTypeLabel' => is_array($projection) ? (string) ($projection['object_type_label'] ?? '') : '',
        'yearLabel' => is_array($projection) ? (string) ($projection['year_label'] ?? '') : '',
    ];
}

function iss_wf_import_archivset_rest_list_sets(WP_REST_Request $request): WP_REST_Response
{
    $result = iss_wf_import_get_archivset_service()->search_sets([
        'search' => (string) $request->get_param('search'),
        'status' => (string) $request->get_param('status'),
        'page' => (int) $request->get_param('page'),
        'per_page' => (int) ($request->get_param('perPage') ?: $request->get_param('per_page') ?: 20),
    ]);

    return rest_ensure_response([
        'items' => array_map('iss_wf_import_archivset_rest_prepare_set', (array) ($result['items'] ?? [])),
        'total' => (int) ($result['total'] ?? 0),
        'page' => (int) ($result['page'] ?? 1),
        'perPage' => (int) ($result['per_page'] ?? 20),
    ]);
}

function iss_wf_import_archivset_rest_create_set(WP_REST_Request $request)
{
    $context_post_id = iss_wf_import_archivset_rest_get_context_post_id($request);
    $attach = rest_sanitize_boolean($request->get_param('attachToContext') ?: $request->get_param('attach_to_context'));
    if ($attach && ($context_post_id <= 0 || !current_user_can('edit_post', $context_post_id))) {
        return new WP_Error(
            'iss_archive_set_link_forbidden',
            __('You cannot attach Archivsets to this content item.', 'iss-wf-import'),
            ['status' => 403]
        );
    }

    if (!$attach && $context_post_id > 0 && !iss_wf_import_archivset_rest_can_edit_sets()) {
        return new WP_Error(
            'iss_archive_set_global_create_forbidden',
            __('You cannot create standalone Archivsets.', 'iss-wf-import'),
            ['status' => 403]
        );
    }

    $args = [
        'title' => (string) $request->get_param('title'),
        'summary' => (string) $request->get_param('summary'),
        'status' => (string) ($request->get_param('status') ?: 'active'),
        'source_system' => (string) ($request->get_param('sourceSystem') ?: $request->get_param('source_system')),
        'source_id' => (string) ($request->get_param('sourceId') ?: $request->get_param('source_id')),
        'source_url' => (string) ($request->get_param('sourceUrl') ?: $request->get_param('source_url')),
        'source_refs' => iss_wf_import_archivset_rest_decode_source_refs($request->get_param('sourceRefs') ?: $request->get_param('source_refs')),
    ];

    if ($context_post_id > 0) {
        $context_post = get_post($context_post_id);
        if ($context_post instanceof WP_Post) {
            if (trim($args['title']) === '') {
                $args['title'] = sprintf(__('Archive Picker: %s', 'iss-wf-import'), get_the_title($context_post));
            }

            if ($args['source_system'] === '') {
                $args['source_system'] = 'wp-post';
            }

            if ($args['source_id'] === '') {
                $args['source_id'] = (string) $context_post_id;
            }

            if ($args['source_url'] === '') {
                $args['source_url'] = (string) get_permalink($context_post);
            }
        }
    }

    $set_id = iss_wf_import_get_archivset_service()->create_set($args);
    if ($set_id <= 0) {
        return new WP_Error(
            'iss_archive_set_create_failed',
            __('Archivset could not be created.', 'iss-wf-import'),
            ['status' => 400]
        );
    }

    if ($attach && $context_post_id > 0) {
        iss_wf_import_get_archivset_service()->attach_set($set_id, $context_post_id);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('archive_set_create', [
            'capability' => ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
            'object_ids' => [$set_id, $context_post_id],
            'result' => 'completed',
        ]);
    }

    $set = iss_wf_import_get_archivset_service()->get_set($set_id);

    return rest_ensure_response([
        'item' => is_array($set) ? iss_wf_import_archivset_rest_prepare_set($set, true) : null,
    ]);
}

function iss_wf_import_archivset_rest_get_set(WP_REST_Request $request)
{
    $set = iss_wf_import_get_archivset_service()->get_set(absint($request->get_param('id')));
    if (!$set) {
        return new WP_Error('iss_archive_set_not_found', __('Archivset not found.', 'iss-wf-import'), ['status' => 404]);
    }

    return rest_ensure_response(['item' => iss_wf_import_archivset_rest_prepare_set($set, true)]);
}

function iss_wf_import_archivset_rest_update_set(WP_REST_Request $request)
{
    $set_id = absint($request->get_param('id'));
    $updated = iss_wf_import_get_archivset_service()->update_set($set_id, [
        'title' => (string) $request->get_param('title'),
        'summary' => (string) $request->get_param('summary'),
        'status' => (string) $request->get_param('status'),
        'source_system' => (string) ($request->get_param('sourceSystem') ?: $request->get_param('source_system')),
        'source_id' => (string) ($request->get_param('sourceId') ?: $request->get_param('source_id')),
        'source_url' => (string) ($request->get_param('sourceUrl') ?: $request->get_param('source_url')),
        'source_refs' => iss_wf_import_archivset_rest_decode_source_refs($request->get_param('sourceRefs') ?: $request->get_param('source_refs')),
        'legacy_collection_post_id' => absint($request->get_param('legacyCollectionPostId') ?: $request->get_param('legacy_collection_post_id')),
    ]);

    if (!$updated) {
        return new WP_Error('iss_archive_set_update_failed', __('Archivset could not be updated.', 'iss-wf-import'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('archive_set_update', [
            'capability' => ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
            'object_ids' => [$set_id],
            'result' => 'completed',
        ]);
    }

    $set = iss_wf_import_get_archivset_service()->get_set($set_id);

    return rest_ensure_response([
        'item' => is_array($set) ? iss_wf_import_archivset_rest_prepare_set($set, true) : null,
    ]);
}

function iss_wf_import_archivset_rest_delete_set(WP_REST_Request $request)
{
    $set_id = absint($request->get_param('id'));
    $deleted = iss_wf_import_get_archivset_service()->delete_set($set_id);

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('archive_set_delete', [
            'capability' => ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
            'object_ids' => [$set_id],
            'result' => $deleted ? 'completed' : 'failed',
        ]);
    }

    return rest_ensure_response(['deleted' => $deleted]);
}

function iss_wf_import_archivset_rest_add_member(WP_REST_Request $request)
{
    $set_id = absint($request->get_param('id'));
    $member_id = iss_wf_import_get_archivset_service()->add_member($set_id, [
        'object_post_id' => absint($request->get_param('objectPostId') ?: $request->get_param('object_post_id')),
        'display_title' => (string) ($request->get_param('displayTitle') ?: $request->get_param('display_title')),
        'caption' => (string) $request->get_param('caption'),
        'page_label' => (string) ($request->get_param('pageLabel') ?: $request->get_param('page_label')),
    ]);

    if ($member_id <= 0) {
        return new WP_Error('iss_archive_set_member_failed', __('Archive object could not be added.', 'iss-wf-import'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('archive_set_member_add', [
            'capability' => ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
            'object_ids' => [$set_id, $member_id],
            'result' => 'completed',
        ]);
    }

    $set = iss_wf_import_get_archivset_service()->get_set($set_id);

    return rest_ensure_response([
        'item' => is_array($set) ? iss_wf_import_archivset_rest_prepare_set($set, true) : null,
    ]);
}

function iss_wf_import_archivset_rest_update_member(WP_REST_Request $request)
{
    $set_id = absint($request->get_param('id'));
    $member_id = absint($request->get_param('member_id'));
    $updated = iss_wf_import_get_archivset_service()->update_member($set_id, $member_id, [
        'display_title' => (string) ($request->get_param('displayTitle') ?: $request->get_param('display_title')),
        'caption' => (string) $request->get_param('caption'),
        'page_label' => (string) ($request->get_param('pageLabel') ?: $request->get_param('page_label')),
        'source_url' => (string) ($request->get_param('sourceUrl') ?: $request->get_param('source_url')),
        'position' => (int) $request->get_param('position'),
    ]);

    if (!$updated) {
        return new WP_Error('iss_archive_set_member_update_failed', __('Archivset member could not be updated.', 'iss-wf-import'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('archive_set_member_update', [
            'capability' => ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
            'object_ids' => [$set_id, $member_id],
            'result' => 'completed',
        ]);
    }

    $set = iss_wf_import_get_archivset_service()->get_set($set_id);

    return rest_ensure_response([
        'item' => is_array($set) ? iss_wf_import_archivset_rest_prepare_set($set, true) : null,
    ]);
}

function iss_wf_import_archivset_rest_remove_member(WP_REST_Request $request): WP_REST_Response
{
    $set_id = absint($request->get_param('id'));
    $member_id = absint($request->get_param('member_id'));
    $removed = iss_wf_import_get_archivset_service()->remove_member(
        $set_id,
        $member_id
    );

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('archive_set_member_remove', [
            'capability' => ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
            'object_ids' => [$set_id, $member_id],
            'result' => $removed ? 'completed' : 'failed',
        ]);
    }

    return rest_ensure_response(['removed' => $removed]);
}

function iss_wf_import_archivset_rest_reorder_members(WP_REST_Request $request)
{
    $members = $request->get_param('members');
    $updated = iss_wf_import_get_archivset_service()->reorder_members(
        absint($request->get_param('id')),
        is_array($members) ? $members : []
    );

    if (!$updated) {
        return new WP_Error('iss_archive_set_member_order_failed', __('Archivset members could not be reordered.', 'iss-wf-import'), ['status' => 400]);
    }

    $set = iss_wf_import_get_archivset_service()->get_set(absint($request->get_param('id')));

    return rest_ensure_response([
        'item' => is_array($set) ? iss_wf_import_archivset_rest_prepare_set($set, true) : null,
    ]);
}

function iss_wf_import_archivset_rest_get_content_sets(WP_REST_Request $request): WP_REST_Response
{
    $sets = iss_wf_import_get_archivset_service()->get_sets_for_post(absint($request->get_param('post_id')));

    return rest_ensure_response([
        'items' => array_map('iss_wf_import_archivset_rest_prepare_set', $sets),
        'count' => count($sets),
    ]);
}

function iss_wf_import_archivset_rest_attach_set(WP_REST_Request $request)
{
    $post_id = absint($request->get_param('post_id'));
    $set_id = absint($request->get_param('setId') ?: $request->get_param('set_id'));
    $attached = iss_wf_import_get_archivset_service()->attach_set($set_id, $post_id);

    if (!$attached) {
        return new WP_Error('iss_archive_set_attach_failed', __('Archivset could not be attached.', 'iss-wf-import'), ['status' => 400]);
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('archive_set_attach_content', [
            'capability' => ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
            'object_ids' => [$set_id, $post_id],
            'result' => 'completed',
        ]);
    }

    return iss_wf_import_archivset_rest_get_content_sets($request);
}

function iss_wf_import_archivset_rest_detach_set(WP_REST_Request $request): WP_REST_Response
{
    $set_id = absint($request->get_param('set_id'));
    $post_id = absint($request->get_param('post_id'));
    $detached = iss_wf_import_get_archivset_service()->detach_set(
        $set_id,
        $post_id
    );

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('archive_set_detach_content', [
            'capability' => ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
            'object_ids' => [$set_id, $post_id],
            'result' => $detached ? 'completed' : 'failed',
        ]);
    }

    return rest_ensure_response(['detached' => $detached]);
}

function iss_wf_import_archivset_rest_object_picker(WP_REST_Request $request): WP_REST_Response
{
    $browser = iss_wf_import_get_archive_browser_service();
    $state = [
        'search' => (string) $request->get_param('search'),
        'source' => (string) $request->get_param('source'),
        'field' => (string) $request->get_param('field'),
        'family' => (string) $request->get_param('family'),
        'context' => (string) $request->get_param('context'),
        'decade' => (string) $request->get_param('decade'),
        'page' => (int) ($request->get_param('page') ?: 1),
    ];
    $per_page = max(1, min(50, (int) ($request->get_param('perPage') ?: $request->get_param('per_page') ?: 12)));
    $query = $browser->get_query($state, $per_page);
    $posts = array_values(array_filter((array) $query->posts, static function ($post): bool {
        return $post instanceof WP_Post;
    }));

    $facets = [
        'source' => iss_wf_import_archivset_rest_prepare_terms($browser->get_term_options(ISS_WF_IMPORT_SOURCE_TAXONOMY)),
        'field' => iss_wf_import_archivset_rest_prepare_terms($browser->get_term_options(ISS_WF_IMPORT_FIELD_TAXONOMY)),
        'family' => iss_wf_import_archivset_rest_prepare_terms($browser->get_term_options(ISS_WF_IMPORT_FAMILY_TAXONOMY)),
        'context' => iss_wf_import_archivset_rest_prepare_terms($browser->get_term_options(ISS_WF_IMPORT_CONTEXT_TAXONOMY)),
        'decade' => iss_wf_import_archivset_rest_prepare_terms($browser->get_term_options(ISS_WF_IMPORT_DECADE_TAXONOMY)),
    ];

    return rest_ensure_response([
        'items' => array_map('iss_wf_import_archivset_rest_prepare_object', $posts),
        'total' => (int) $query->found_posts,
        'page' => max(1, (int) $state['page']),
        'perPage' => $per_page,
        'totalPages' => max(1, (int) $query->max_num_pages),
        'facets' => $facets,
    ]);
}

function iss_wf_import_archivset_rest_prepare_terms(array $terms): array
{
    $items = [];
    foreach ($terms as $term) {
        if (!is_object($term)) {
            continue;
        }

        $items[] = [
            'slug' => (string) ($term->slug ?? ''),
            'name' => (string) ($term->name ?? ''),
            'count' => (int) ($term->count ?? 0),
        ];
    }

    return $items;
}

function iss_wf_import_archivset_register_rest_routes(): void
{
    $namespace = iss_wf_import_archivset_rest_namespace();

    register_rest_route($namespace, '/sets', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_wf_import_archivset_rest_list_sets',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_list_or_create_sets',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_wf_import_archivset_rest_create_set',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_list_or_create_sets',
        ],
    ]);

    register_rest_route($namespace, '/sets/(?P<id>\d+)', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_wf_import_archivset_rest_get_set',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_set',
        ],
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'iss_wf_import_archivset_rest_update_set',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_sets',
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'iss_wf_import_archivset_rest_delete_set',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_sets',
        ],
    ]);

    register_rest_route($namespace, '/sets/(?P<id>\d+)/members', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_wf_import_archivset_rest_add_member',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_set',
        ],
    ]);

    register_rest_route($namespace, '/sets/(?P<id>\d+)/members/reorder', [
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_wf_import_archivset_rest_reorder_members',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_set',
        ],
    ]);

    register_rest_route($namespace, '/sets/(?P<id>\d+)/members/(?P<member_id>\d+)', [
        [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => 'iss_wf_import_archivset_rest_update_member',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_set',
        ],
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'iss_wf_import_archivset_rest_remove_member',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_set',
        ],
    ]);

    register_rest_route($namespace, '/content/(?P<post_id>\d+)/sets', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_wf_import_archivset_rest_get_content_sets',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_content',
        ],
        [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => 'iss_wf_import_archivset_rest_attach_set',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_content',
        ],
    ]);

    register_rest_route($namespace, '/content/(?P<post_id>\d+)/sets/(?P<set_id>\d+)', [
        [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => 'iss_wf_import_archivset_rest_detach_set',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_edit_content',
        ],
    ]);

    register_rest_route($namespace, '/object-picker', [
        [
            'methods' => WP_REST_Server::READABLE,
            'callback' => 'iss_wf_import_archivset_rest_object_picker',
            'permission_callback' => 'iss_wf_import_archivset_rest_can_list_or_create_sets',
        ],
    ]);
}
add_action('rest_api_init', 'iss_wf_import_archivset_register_rest_routes');
