<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Archive graph bridge reads plugin-owned graph tables for projection lookups.

function iss_graph_get_archive_object_post_type(): string
{
    return defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? ISS_WF_IMPORT_OBJECT_POST_TYPE : 'archivobjekt';
}

function iss_graph_archive_relation_meta_keys(): array
{
    $keys = ['iss_related_places'];

    foreach ([
        'ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META',
        'ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META',
        'ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_NAME_META',
        'ISS_WF_IMPORT_OBJECT_RELATIONS_META',
    ] as $constant_name) {
        if (defined($constant_name)) {
            $keys[] = constant($constant_name);
        }
    }

    return array_values(array_unique(array_filter(array_map('strval', $keys))));
}

function iss_graph_maybe_backfill_archive_objects(): void
{
    if (!post_type_exists(iss_graph_get_archive_object_post_type())) {
        return;
    }

    $stored = (string) get_option(ISS_GRAPH_ARCHIVE_BACKFILL_OPTION, '');
    if ($stored === ISS_GRAPH_ARCHIVE_BACKFILL_VERSION) {
        return;
    }

    iss_graph_backfill_archive_objects();
    update_option(ISS_GRAPH_ARCHIVE_BACKFILL_OPTION, ISS_GRAPH_ARCHIVE_BACKFILL_VERSION, false);
}

function iss_graph_backfill_archive_objects(): int
{
    $post_type = iss_graph_get_archive_object_post_type();
    if (!post_type_exists($post_type)) {
        return 0;
    }

    $post_ids = get_posts([
        'post_type' => $post_type,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    $count = 0;

    foreach ($post_ids as $post_id) {
        if (iss_graph_sync_archive_object_entity((int) $post_id)) {
            $count++;
        }
    }

    return $count;
}

function iss_graph_get_archive_object_projection(int $post_id): ?array
{
    if (function_exists('iss_wf_import_get_object_service')) {
        $projection = iss_wf_import_get_object_service()->ensure_projection_for_post($post_id);

        return is_array($projection) ? $projection : null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== iss_graph_get_archive_object_post_type()) {
        return null;
    }

    return [
        'object_post_id' => $post_id,
        'object_key' => 'wp-id:' . $post_id,
        'title' => get_the_title($post),
        'summary' => (string) get_post_field('post_excerpt', $post_id),
        'description' => (string) get_post_field('post_content', $post_id),
        'source_system' => '',
        'source_id' => '',
        'institution_name' => '',
        'md_institution_id' => 0,
        'sort_year_start' => 0,
        'sort_year_end' => 0,
    ];
}

function iss_graph_get_archive_object_relation_projection(int $post_id): array
{
    if (!function_exists('iss_wf_import_get_relation_service')) {
        return [];
    }

    $projection = iss_wf_import_get_relation_service()->get_projection_for_post($post_id);

    return is_array($projection) ? $projection : [];
}

function iss_graph_build_archive_entity_source_id(string $prefix, $value, string $fallback): string
{
    $value = is_scalar($value) ? trim((string) $value) : '';
    if ($value !== '') {
        return $prefix . ':' . sanitize_title($value);
    }

    return $prefix . ':' . sanitize_title($fallback);
}

function iss_graph_ensure_archive_named_entity(
    string $entity_kind,
    string $source_system,
    string $source_id,
    string $display_title,
    array $extra_entity_data = []
): ?array {
    $service = iss_graph_get_service();
    $display_title = sanitize_text_field($display_title);
    if ($display_title === '') {
        return null;
    }

    $source_system = sanitize_key($source_system);
    $source_id = sanitize_text_field($source_id);
    if ($source_system === '' || $source_id === '') {
        return null;
    }

    $entity = iss_graph_resolve_or_create_named_entity($entity_kind, $display_title, array_merge([
        'entity_kind' => $entity_kind,
        'source_system' => $source_system,
        'source_id' => $source_id,
        'canonical_slug' => $source_system . '-' . $source_id,
        'display_title' => $display_title,
        'summary' => '',
        'status' => 'publish',
        'is_public' => false,
        'search_visibility' => 'hidden',
    ], $extra_entity_data), [
        'source_ref' => $source_system . ':' . $source_id,
    ]);

    if (!$entity) {
        return null;
    }

    $service->replace_entity_names_for_source((int) $entity['id'], 'archive_title', [[
        'name' => $display_title,
        'name_type' => 'source_label',
        'is_primary' => false,
        'position' => 0,
    ]], 'entity:' . (int) $entity['id']);

    $service->replace_entity_identifiers_for_source((int) $entity['id'], $source_system, [[
        'namespace' => $source_system,
        'value' => $source_id,
        'label' => 'Archive source ID',
        'trust_level' => 'trusted_review',
        'confidence' => 80,
        'is_primary' => true,
        'status' => 'accepted',
    ]], 'entity:' . (int) $entity['id']);

    return $service->get_entity_by_id((int) $entity['id']);
}

function iss_graph_build_archive_object_relation_rows(int $object_post_id): array
{
    $projection = iss_graph_get_archive_object_relation_projection($object_post_id);

    return [
        'organization_rows' => iss_graph_build_archive_object_organization_rows($object_post_id),
        'person_rows' => iss_graph_build_archive_object_person_rows($projection),
        'place_rows' => iss_graph_build_archive_object_place_rows($projection),
    ];
}

function iss_graph_build_archive_object_organization_rows(int $object_post_id): array
{
    $projection = iss_graph_get_archive_object_projection($object_post_id);
    if (!$projection) {
        return [];
    }

    $institution_name = trim((string) ($projection['institution_name'] ?? ''));
    if ($institution_name === '') {
        return [];
    }

    $source_id = iss_graph_build_archive_entity_source_id(
        'institution',
        (string) ($projection['md_institution_id'] ?? ''),
        $institution_name
    );

    $organization = iss_graph_ensure_archive_named_entity(
        'organization',
        'archive_institution',
        $source_id,
        $institution_name
    );

    if (!$organization) {
        return [];
    }

    return [[
        'to_entity_id' => (int) ($organization['id'] ?? 0),
        'relation_type' => 'institution',
        'relation_label' => 'Institution',
        'position' => 0,
        'is_public' => true,
    ]];
}

function iss_graph_build_archive_object_person_rows(array $projection): array
{
    $rows = [];
    $position = 0;

    foreach ((array) ($projection['people'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = sanitize_text_field((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $source_id = iss_graph_build_archive_entity_source_id(
            'person',
            (string) ($item['source_id'] ?? ''),
            $name
        );

        $person = iss_graph_ensure_archive_named_entity(
            'person',
            'archive_person',
            $source_id,
            $name
        );

        if (!$person) {
            continue;
        }

        $rows[] = [
            'to_entity_id' => (int) ($person['id'] ?? 0),
            'relation_type' => sanitize_key((string) ($item['type'] ?? 'related')) ?: 'related',
            'relation_label' => sanitize_text_field((string) ($item['type'] ?? 'Person')),
            'note' => sanitize_textarea_field((string) ($item['note'] ?? '')),
            'position' => $position++,
            'is_public' => true,
        ];
    }

    return $rows;
}

function iss_graph_build_archive_object_place_rows(array $projection): array
{
    $rows = [];
    $position = 0;

    foreach ((array) ($projection['local_places'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $place_post_id = absint($item['place_id'] ?? 0);
        if ($place_post_id <= 0) {
            continue;
        }

        $place_entity = iss_graph_get_service()->find_entity_by_post('place', $place_post_id);
        if (!$place_entity && function_exists('iss_graph_sync_register_place_entity')) {
            $place_entity = iss_graph_sync_register_place_entity($place_post_id);
        }

        if (!$place_entity) {
            continue;
        }

        $rows[] = [
            'to_entity_id' => (int) ($place_entity['id'] ?? 0),
            'relation_type' => sanitize_key((string) ($item['role'] ?? 'related')) ?: 'related',
            'relation_label' => sanitize_text_field((string) ($item['label'] ?? '')),
            'weight' => (int) ($item['weight'] ?? 0),
            'position' => $position++,
            'is_public' => true,
        ];
    }

    foreach ((array) ($projection['places'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $name = sanitize_text_field((string) ($item['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $source_id = iss_graph_build_archive_entity_source_id(
            'place',
            (string) ($item['source_id'] ?? ''),
            $name
        );

        $place = iss_graph_ensure_archive_named_entity(
            'place',
            'archive_place',
            $source_id,
            $name
        );

        if (!$place) {
            continue;
        }

        $rows[] = [
            'to_entity_id' => (int) ($place['id'] ?? 0),
            'relation_type' => sanitize_key((string) ($item['type'] ?? 'related')) ?: 'related',
            'relation_label' => sanitize_text_field((string) ($item['type'] ?? 'Ort')),
            'note' => sanitize_textarea_field((string) ($item['note'] ?? '')),
            'position' => $position++,
            'is_public' => true,
        ];
    }

    return $rows;
}

function iss_graph_sync_archive_object_entity(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== iss_graph_get_archive_object_post_type()) {
        return null;
    }

    $projection = iss_graph_get_archive_object_projection($post_id);
    if (!$projection) {
        return null;
    }

    $service = iss_graph_get_service();
    $object_key = trim((string) ($projection['object_key'] ?? ''));
    if ($object_key === '') {
        $object_key = 'wp-id:' . $post_id;
    }

    $entity = $service->upsert_entity([
        'entity_kind' => 'archive_object',
        'post_id' => $post_id,
        'source_system' => 'archive_object',
        'source_id' => $object_key,
        'canonical_slug' => trim((string) $post->post_name) !== '' ? (string) $post->post_name : ('archive-object-' . $post_id),
        'display_title' => sanitize_text_field((string) ($projection['title'] ?? get_the_title($post))),
        'summary' => sanitize_textarea_field((string) ($projection['summary'] ?? get_post_field('post_excerpt', $post_id))),
        'status' => sanitize_key((string) $post->post_status),
        'is_public' => $post->post_status === 'publish',
        'search_visibility' => 'hidden',
        'sort_start' => (int) ($projection['sort_year_start'] ?? 0),
        'sort_end' => (int) ($projection['sort_year_end'] ?? 0),
    ]);

    if (!$entity) {
        return null;
    }

    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return null;
    }

    $service->replace_entity_names_for_source($entity_id, 'archive_object_title', [[
        'name' => sanitize_text_field((string) ($projection['title'] ?? get_the_title($post))),
        'name_type' => 'primary',
        'is_primary' => true,
        'position' => 0,
    ]], 'post:' . $post_id);

    $identifier_rows = [[
        'namespace' => 'archive_object_id',
        'value' => $object_key,
        'label' => 'Archive object ID',
        'trust_level' => 'trusted_auto_link',
        'confidence' => 100,
        'is_primary' => true,
        'status' => 'accepted',
    ]];
    $source_id = trim((string) ($projection['source_id'] ?? ''));
    if ($source_id !== '') {
        $identifier_rows[] = [
            'namespace' => 'archive_source_id',
            'value' => $source_id,
            'label' => 'Archive source ID',
            'trust_level' => 'trusted_review',
            'confidence' => 80,
            'status' => 'accepted',
        ];
    }
    iss_graph_sync_wp_post_identifiers($entity_id, $post, 'archive_object', $identifier_rows);

    if (function_exists('iss_graph_sync_entity_alias_backfill')) {
        iss_graph_sync_entity_alias_backfill($entity_id);
    }

    $relation_rows = iss_graph_build_archive_object_relation_rows($post_id);
    $service->replace_entity_relations_for_source(
        $entity_id,
        'organization',
        'archive_projection',
        (array) ($relation_rows['organization_rows'] ?? []),
        'post:' . $post_id
    );
    $service->replace_entity_relations_for_source(
        $entity_id,
        'person',
        'archive_projection',
        (array) ($relation_rows['person_rows'] ?? []),
        'post:' . $post_id
    );
    $service->replace_entity_relations_for_source(
        $entity_id,
        'place',
        'archive_projection',
        (array) ($relation_rows['place_rows'] ?? []),
        'post:' . $post_id
    );

    return $service->get_entity_by_id($entity_id);
}

function iss_graph_get_archive_objects_for_place(int $place_post_id, int $limit = 6): array
{
    $place_post_id = absint($place_post_id);
    if ($place_post_id <= 0) {
        return [];
    }

    $place_entity = iss_graph_get_service()->find_entity_by_post('place', $place_post_id);
    if (!$place_entity && function_exists('iss_graph_sync_register_place_entity')) {
        $place_entity = iss_graph_sync_register_place_entity($place_post_id);
    }

    $place_entity_id = (int) ($place_entity['id'] ?? 0);
    if ($place_entity_id <= 0) {
        return [];
    }

    global $wpdb;

    $limit = max(1, min(12, $limit));
    $relation_table = iss_graph_get_service()->get_relation_table_name();
    $entity_table = iss_graph_get_service()->get_entity_table_name();

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT
            e.post_id
        FROM {$relation_table} r
        INNER JOIN {$entity_table} e
            ON e.id = r.from_entity_id
        INNER JOIN {$wpdb->posts} p
            ON p.ID = e.post_id
        WHERE r.relation_family = %s
          AND r.to_entity_id = %d
          AND e.entity_kind = %s
          AND e.post_id > 0
          AND p.post_status = 'publish'
          AND p.post_type = %s
        ORDER BY r.weight DESC, p.post_date DESC, e.post_id DESC
        LIMIT %d",
        'place',
        $place_entity_id,
        'archive_object',
        iss_graph_get_archive_object_post_type(),
        $limit
    ), ARRAY_A);

    if (!is_array($rows) || !$rows) {
        return [];
    }

    $items = [];

    foreach ($rows as $row) {
        $post_id = absint($row['post_id'] ?? 0);
        if ($post_id <= 0) {
            continue;
        }

        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_status !== 'publish' || $post->post_type !== iss_graph_get_archive_object_post_type()) {
            continue;
        }

        $featured_image_url = get_the_post_thumbnail_url($post, 'medium_large');

        $items[] = [
            'post_id' => $post_id,
            'slug' => (string) $post->post_name,
            'title' => get_the_title($post),
            'excerpt' => (string) get_post_field('post_excerpt', $post_id),
            'permalink' => (string) get_permalink($post),
            'featured_image_url' => is_string($featured_image_url) ? $featured_image_url : '',
        ];
    }

    return $items;
}

function iss_graph_sync_archive_object_on_save(int $post_id, WP_Post $post): void
{
    if ($post->post_type !== iss_graph_get_archive_object_post_type()) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    iss_graph_sync_archive_object_entity($post_id);
}

function iss_graph_delete_archive_object_on_before_delete(int $post_id): void
{
    if (get_post_type($post_id) !== iss_graph_get_archive_object_post_type()) {
        return;
    }

    iss_graph_get_service()->delete_entity_by_post('archive_object', $post_id);
}

function iss_graph_sync_archive_object_on_relevant_meta_change($meta_id, int $post_id, string $meta_key): void
{
    if (get_post_type($post_id) !== iss_graph_get_archive_object_post_type()) {
        return;
    }

    if (!in_array($meta_key, iss_graph_archive_relation_meta_keys(), true)) {
        return;
    }

    iss_graph_sync_archive_object_entity($post_id);
}

add_action('save_post', 'iss_graph_sync_archive_object_on_save', 40, 2);
add_action('before_delete_post', 'iss_graph_delete_archive_object_on_before_delete', 5);
add_action('added_post_meta', 'iss_graph_sync_archive_object_on_relevant_meta_change', 20, 3);
add_action('updated_post_meta', 'iss_graph_sync_archive_object_on_relevant_meta_change', 20, 3);
add_action('deleted_post_meta', 'iss_graph_sync_archive_object_on_relevant_meta_change', 20, 3);
