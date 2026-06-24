<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_meta_auth_callback(...$args): bool
{
    $post_id = isset($args[2]) ? absint($args[2]) : 0;
    if ($post_id > 0) {
        return current_user_can('edit_post', $post_id);
    }

    return current_user_can('edit_archive_items');
}

function iss_wf_import_register_string_meta(string $post_type, string $meta_key, bool $show_in_rest = true): void
{
    register_post_meta($post_type, $meta_key, [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => $show_in_rest,
        'sanitize_callback' => 'sanitize_text_field',
        'auth_callback' => 'iss_wf_import_meta_auth_callback',
    ]);
}

function iss_wf_import_register_url_meta(string $post_type, string $meta_key, bool $show_in_rest = true): void
{
    register_post_meta($post_type, $meta_key, [
        'single' => true,
        'type' => 'string',
        'show_in_rest' => $show_in_rest,
        'sanitize_callback' => 'esc_url_raw',
        'auth_callback' => 'iss_wf_import_meta_auth_callback',
    ]);
}

function iss_wf_import_register_integer_meta(string $post_type, string $meta_key, bool $show_in_rest = true): void
{
    register_post_meta($post_type, $meta_key, [
        'single' => true,
        'type' => 'integer',
        'show_in_rest' => $show_in_rest,
        'sanitize_callback' => 'absint',
        'auth_callback' => 'iss_wf_import_meta_auth_callback',
    ]);
}

function iss_wf_import_register_array_meta(string $post_type, string $meta_key, string $sanitize_callback, bool $show_in_rest = false): void
{
    register_post_meta($post_type, $meta_key, [
        'single' => true,
        'type' => 'array',
        'default' => [],
        'show_in_rest' => $show_in_rest,
        'sanitize_callback' => $sanitize_callback,
        'auth_callback' => 'iss_wf_import_meta_auth_callback',
    ]);
}

function iss_wf_import_sanitize_string_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        $item = sanitize_text_field((string) $item);
        if ($item !== '') {
            $clean[] = $item;
        }
    }

    return array_values(array_unique($clean));
}

function iss_wf_import_sanitize_id_list($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        $item = absint($item);
        if ($item > 0) {
            $clean[] = $item;
        }
    }

    return array_values(array_unique($clean));
}

function iss_wf_import_sanitize_collection_item(array $item): ?array
{
    $object_id = absint($item['object_id'] ?? 0);
    $source_object_id = sanitize_text_field((string) ($item['source_object_id'] ?? ''));
    $source_url = esc_url_raw((string) ($item['source_url'] ?? ''));

    if ($object_id <= 0 && $source_object_id === '' && $source_url === '') {
        return null;
    }

    return [
        'object_id' => $object_id,
        'position' => max(0, absint($item['position'] ?? 0)),
        'caption_override' => sanitize_textarea_field((string) ($item['caption_override'] ?? '')),
        'page_label' => sanitize_text_field((string) ($item['page_label'] ?? '')),
        'title' => sanitize_text_field((string) ($item['title'] ?? '')),
        'source_object_id' => $source_object_id,
        'source_url' => $source_url,
    ];
}

function iss_wf_import_sanitize_collection_items($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sanitized = iss_wf_import_sanitize_collection_item($item);
        if ($sanitized) {
            $clean[] = $sanitized;
        }
    }

    usort($clean, static function (array $a, array $b): int {
        if ($a['position'] === $b['position']) {
            return strcmp((string) $a['source_object_id'], (string) $b['source_object_id']);
        }

        return $a['position'] <=> $b['position'];
    });

    return array_values($clean);
}

function iss_wf_import_sanitize_collection_child(array $item): ?array
{
    $collection_id = absint($item['collection_id'] ?? 0);
    $source_external_id = sanitize_text_field((string) ($item['source_external_id'] ?? ''));
    $source_url = esc_url_raw((string) ($item['source_url'] ?? ''));

    if ($collection_id <= 0 && $source_external_id === '' && $source_url === '') {
        return null;
    }

    return [
        'collection_id' => $collection_id,
        'position' => max(0, absint($item['position'] ?? 0)),
        'title' => sanitize_text_field((string) ($item['title'] ?? '')),
        'slug' => sanitize_title((string) ($item['slug'] ?? '')),
        'source_external_id' => $source_external_id,
        'source_url' => $source_url,
    ];
}

function iss_wf_import_sanitize_collection_children($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sanitized = iss_wf_import_sanitize_collection_child($item);
        if ($sanitized) {
            $clean[] = $sanitized;
        }
    }

    usort($clean, static function (array $a, array $b): int {
        if ($a['position'] === $b['position']) {
            return strcmp((string) $a['title'], (string) $b['title']);
        }

        return $a['position'] <=> $b['position'];
    });

    return array_values($clean);
}

function iss_wf_import_sanitize_collection_source_id(array $item): ?array
{
    $source_kind = sanitize_key((string) ($item['source_kind'] ?? ''));
    $source_id = sanitize_text_field((string) ($item['source_id'] ?? ''));
    $source_url = esc_url_raw((string) ($item['source_url'] ?? ''));

    if ($source_kind === '' && $source_id === '' && $source_url === '') {
        return null;
    }

    return [
        'source_kind' => $source_kind,
        'source_id' => $source_id,
        'source_url' => $source_url,
        'label' => sanitize_text_field((string) ($item['label'] ?? '')),
    ];
}

function iss_wf_import_sanitize_collection_source_ids($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sanitized = iss_wf_import_sanitize_collection_source_id($item);
        if ($sanitized) {
            $clean[] = $sanitized;
        }
    }

    return array_values($clean);
}

function iss_wf_import_sanitize_archive_image_source(array $item): ?array
{
    $source_url = esc_url_raw((string) ($item['source_url'] ?? ''));
    $preview_url = esc_url_raw((string) ($item['preview_url'] ?? ''));

    if ($source_url === '' && $preview_url === '') {
        return null;
    }

    return [
        'source_id' => absint($item['source_id'] ?? 0),
        'source_url' => $source_url,
        'preview_url' => $preview_url,
        'attachment_id' => absint($item['attachment_id'] ?? 0),
        'preview_attachment_id' => absint($item['preview_attachment_id'] ?? 0),
        'filename' => sanitize_text_field((string) ($item['filename'] ?? '')),
        'label' => sanitize_text_field((string) ($item['label'] ?? '')),
        'owner' => sanitize_text_field((string) ($item['owner'] ?? '')),
        'creator' => sanitize_text_field((string) ($item['creator'] ?? '')),
        'rights' => sanitize_text_field((string) ($item['rights'] ?? '')),
        'type' => sanitize_key((string) ($item['type'] ?? '')),
        'is_main' => !empty($item['is_main']),
    ];
}

function iss_wf_import_sanitize_archive_image_sources($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sanitized = iss_wf_import_sanitize_archive_image_source($item);
        if ($sanitized) {
            $clean[] = $sanitized;
        }
    }

    return array_values($clean);
}

function iss_wf_import_sanitize_archive_named_ref(array $item): ?array
{
    $id = absint($item['id'] ?? ($item['source_id'] ?? 0));
    $name = sanitize_text_field((string) ($item['name'] ?? ''));
    $source_id = sanitize_text_field((string) ($item['source_id'] ?? ''));

    if ($id <= 0 && $name === '' && $source_id === '') {
        return null;
    }

    return [
        'id' => $id,
        'source_id' => $source_id,
        'name' => $name,
        'type' => sanitize_key((string) ($item['type'] ?? '')),
        'note' => sanitize_textarea_field((string) ($item['note'] ?? '')),
        'source_url' => esc_url_raw((string) ($item['source_url'] ?? '')),
    ];
}

function iss_wf_import_sanitize_archive_named_refs($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sanitized = iss_wf_import_sanitize_archive_named_ref($item);
        if ($sanitized) {
            $clean[] = $sanitized;
        }
    }

    return array_values($clean);
}

function iss_wf_import_sanitize_archive_event(array $item): ?array
{
    $event_id = absint($item['event_id'] ?? 0);
    $event_type = absint($item['event_type'] ?? 0);
    $event_type_name = sanitize_text_field((string) ($item['event_type_name'] ?? ''));

    if ($event_id <= 0 && $event_type <= 0 && $event_type_name === '') {
        return null;
    }

    return [
        'event_id' => $event_id,
        'event_type' => $event_type,
        'event_type_name' => $event_type_name,
        'note' => sanitize_textarea_field((string) ($item['note'] ?? '')),
        'time_label' => sanitize_text_field((string) ($item['time_label'] ?? '')),
        'time_start' => sanitize_text_field((string) ($item['time_start'] ?? '')),
        'time_end' => sanitize_text_field((string) ($item['time_end'] ?? '')),
        'people_id' => absint($item['people_id'] ?? 0),
        'people_name' => sanitize_text_field((string) ($item['people_name'] ?? '')),
        'place_id' => absint($item['place_id'] ?? 0),
        'place_name' => sanitize_text_field((string) ($item['place_name'] ?? '')),
        'place_latitude' => isset($item['place_latitude']) ? (float) $item['place_latitude'] : 0.0,
        'place_longitude' => isset($item['place_longitude']) ? (float) $item['place_longitude'] : 0.0,
    ];
}

function iss_wf_import_sanitize_archive_events($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sanitized = iss_wf_import_sanitize_archive_event($item);
        if ($sanitized) {
            $clean[] = $sanitized;
        }
    }

    return array_values($clean);
}

function iss_wf_import_sanitize_archive_relation_item(array $item): ?array
{
    $object_id = absint($item['object_id'] ?? 0);
    $source_object_id = sanitize_text_field((string) ($item['source_object_id'] ?? ''));

    if ($object_id <= 0 && $source_object_id === '') {
        return null;
    }

    return [
        'object_id' => $object_id,
        'source_object_id' => $source_object_id,
        'relation_type' => sanitize_key((string) ($item['relation_type'] ?? 'related')),
        'context' => sanitize_text_field((string) ($item['context'] ?? '')),
        'weight' => max(0, min(1000, absint($item['weight'] ?? 0))),
        'label' => sanitize_text_field((string) ($item['label'] ?? '')),
    ];
}

function iss_wf_import_sanitize_archive_relations($value): array
{
    if (!is_array($value)) {
        return [];
    }

    $clean = [];
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }

        $sanitized = iss_wf_import_sanitize_archive_relation_item($item);
        if ($sanitized) {
            $clean[] = $sanitized;
        }
    }

    return array_values($clean);
}

function iss_wf_import_register_post_meta(): void
{
    iss_wf_import_register_integer_meta('attachment', ISS_WF_IMPORT_ATTACHMENT_OWNER_OBJECT_META, false);
    iss_wf_import_register_string_meta('attachment', ISS_WF_IMPORT_ATTACHMENT_ARCHIVE_OWNED_META, false);
    iss_wf_import_register_url_meta('attachment', ISS_WF_IMPORT_ATTACHMENT_SOURCE_URL_META, false);

    $archive_post_types = [
        ISS_WF_IMPORT_POST_TYPE,
        ISS_WF_IMPORT_COLLECTION_POST_TYPE,
        ISS_WF_IMPORT_OBJECT_POST_TYPE,
    ];

    foreach ($archive_post_types as $post_type) {
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_SOURCE_SITE_META);
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_SOURCE_KIND_META);
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META);
        iss_wf_import_register_url_meta($post_type, ISS_WF_IMPORT_SOURCE_URL_META);
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_SOURCE_SLUG_META);
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_SOURCE_DATE_GMT_META);
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META);
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_SOURCE_AUTHOR_META);

        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_HASH_META, false);
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_LAST_SYNCED_META, false);
    }

    iss_wf_import_register_integer_meta(ISS_WF_IMPORT_POST_TYPE, ISS_WF_IMPORT_REMOTE_POST_ID_META);

    foreach (iss_wf_import_get_suggestion_post_types() as $post_type) {
        iss_wf_import_register_array_meta($post_type, ISS_WF_IMPORT_PLACE_SUGGESTIONS_META, 'iss_wf_import_sanitize_place_suggestions');
        iss_wf_import_register_string_meta($post_type, ISS_WF_IMPORT_PLACE_SUGGESTED_AT_META, false);
    }

    iss_wf_import_register_array_meta(ISS_WF_IMPORT_COLLECTION_POST_TYPE, ISS_WF_IMPORT_COLLECTION_ITEMS_META, 'iss_wf_import_sanitize_collection_items');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_COLLECTION_POST_TYPE, ISS_WF_IMPORT_COLLECTION_CHILDREN_META, 'iss_wf_import_sanitize_collection_children');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_COLLECTION_POST_TYPE, ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META, 'iss_wf_import_sanitize_collection_source_ids');

    iss_wf_import_register_integer_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_PRIMARY_ATTACHMENT_META);
    iss_wf_import_register_integer_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_PREVIEW_ATTACHMENT_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_TYPE_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_INVENTORY_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_RIGHTS_HOLDER_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_RIGHTS_STATUS_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_CREATOR_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MATERIAL_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_DIMENSIONS_META);
    iss_wf_import_register_url_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_JSON_URL_META);
    iss_wf_import_register_integer_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_ID_META);
    iss_wf_import_register_url_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_MANIFEST_URL_META);
    iss_wf_import_register_url_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_IMAGE_URL_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_IMAGE_RIGHTS_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_IMAGE_OWNER_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_METADATA_RIGHTS_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_METADATA_HOLDER_META);
    iss_wf_import_register_integer_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_ID_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_NAME_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_YEAR_META);
    iss_wf_import_register_string_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_DECADE_META);
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, 'iss_wf_import_sanitize_archive_image_sources');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_TAGS_META, 'iss_wf_import_sanitize_archive_named_refs');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_COLLECTIONS_META, 'iss_wf_import_sanitize_archive_named_refs');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_SERIES_META, 'iss_wf_import_sanitize_archive_named_refs');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_EVENTS_META, 'iss_wf_import_sanitize_archive_events');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META, 'iss_wf_import_sanitize_archive_named_refs');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META, 'iss_wf_import_sanitize_archive_named_refs');
    iss_wf_import_register_array_meta(ISS_WF_IMPORT_OBJECT_POST_TYPE, ISS_WF_IMPORT_OBJECT_RELATIONS_META, 'iss_wf_import_sanitize_archive_relations');
}
add_action('init', 'iss_wf_import_register_post_meta', 20);
