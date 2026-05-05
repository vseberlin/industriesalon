<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_md_base_url(): string
{
    return untrailingslashit((string) apply_filters('iss_wf_import_md_base_url', 'https://berlin.museum-digital.de'));
}

function iss_wf_import_md_asset_base_url(): string
{
    return untrailingslashit((string) apply_filters('iss_wf_import_md_asset_base_url', 'https://asset.museum-digital.org/berlin'));
}

function iss_wf_import_md_object_url(int $object_id): string
{
    return iss_wf_import_md_base_url() . '/object/' . $object_id;
}

function iss_wf_import_md_collection_url(int $collection_id): string
{
    return iss_wf_import_md_base_url() . '/collection/' . $collection_id;
}

function iss_wf_import_md_json_request(string $path, array $query = [])
{
    $url = add_query_arg($query, iss_wf_import_md_base_url() . '/' . ltrim($path, '/'));
    $attempts = 4;
    $retryable_codes = [429, 500, 502, 503, 504];
    $response = null;

    for ($attempt = 1; $attempt <= $attempts; $attempt++) {
        $response = wp_remote_get($url, iss_wf_import_http_args());

        if (!is_wp_error($response)) {
            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code >= 200 && $code < 300) {
                break;
            }

            if (!in_array($code, $retryable_codes, true) || $attempt === $attempts) {
                return new WP_Error(
                    'iss_wf_import_md_http_error',
                    sprintf('museum-digital request failed with status %d for %s', $code, $url)
                );
            }
        } elseif ($attempt === $attempts) {
            return $response;
        }

        sleep($attempt);
    }

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data)) {
        return new WP_Error(
            'iss_wf_import_md_invalid_json',
            sprintf('museum-digital returned invalid JSON for %s', $url)
        );
    }

    return $data;
}

function iss_wf_import_fetch_md_object(int $object_id)
{
    if ($object_id <= 0) {
        return new WP_Error('iss_wf_import_md_missing_object_id', 'Missing museum-digital object id.');
    }

    return iss_wf_import_md_json_request('json/object/' . $object_id);
}

function iss_wf_import_fetch_md_collection(int $collection_id)
{
    if ($collection_id <= 0) {
        return new WP_Error('iss_wf_import_md_missing_collection_id', 'Missing museum-digital collection id.');
    }

    return iss_wf_import_md_json_request('json/collection/' . $collection_id);
}

function iss_wf_import_fetch_md_collection_objects(int $collection_id)
{
    if ($collection_id <= 0) {
        return new WP_Error('iss_wf_import_md_missing_collection_id', 'Missing museum-digital collection id.');
    }

    return iss_wf_import_md_json_request('json/collection/' . $collection_id, [
        'mod' => 'objects',
    ]);
}

function iss_wf_import_extract_md_object_id_from_url(string $url): int
{
    $url = trim($url);
    if ($url === '') {
        return 0;
    }

    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if (preg_match('~(?:^|/)object/(\d+)(?:/|$)~', $path, $matches)) {
        return absint($matches[1]);
    }

    parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

    if (!empty($query['oges'])) {
        return absint($query['oges']);
    }

    if (!empty($query['object_id'])) {
        return absint($query['object_id']);
    }

    return 0;
}

function iss_wf_import_extract_md_collection_id_from_url(string $url): int
{
    $url = trim($url);
    if ($url === '') {
        return 0;
    }

    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if (preg_match('~(?:^|/)collection/(\d+)(?:/|$)~', $path, $matches)) {
        return absint($matches[1]);
    }

    parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $query);

    foreach (['suinsa', 'collection_id', 'ges'] as $key) {
        if (!empty($query[$key])) {
            return absint($query[$key]);
        }
    }

    return 0;
}

function iss_wf_import_md_text_to_html(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    return wpautop(esc_html($text));
}

function iss_wf_import_md_excerpt_from_text(string $text, int $limit = 40): string
{
    $plain = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags($text)));

    if ($plain === '') {
        return '';
    }

    return wp_trim_words($plain, max(10, $limit));
}

function iss_wf_import_md_collection_image_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    if (wp_http_validate_url($value)) {
        return esc_url_raw($value);
    }

    return esc_url_raw(iss_wf_import_md_asset_base_url() . '/' . ltrim($value, '/'));
}

function iss_wf_import_md_image_source_url(array $image): string
{
    $source = trim((string) ($image['filename_loc'] ?? ''));
    if ($source === '') {
        return '';
    }

    if (wp_http_validate_url($source)) {
        return esc_url_raw($source);
    }

    if (str_starts_with($source, 'data/')) {
        return esc_url_raw('https://asset.museum-digital.org/' . ltrim($source, '/'));
    }

    $folder = trim((string) ($image['folder'] ?? ''), '/');
    if ($folder !== '') {
        return esc_url_raw(iss_wf_import_md_asset_base_url() . '/' . $folder . '/' . ltrim($source, '/'));
    }

    return esc_url_raw(iss_wf_import_md_asset_base_url() . '/' . ltrim($source, '/'));
}

function iss_wf_import_md_image_preview_url(array $image): string
{
    $preview = trim((string) ($image['preview'] ?? ''));
    if ($preview === '') {
        return '';
    }

    if (wp_http_validate_url($preview)) {
        return esc_url_raw($preview);
    }

    if (str_starts_with($preview, 'data/')) {
        return esc_url_raw('https://asset.museum-digital.org/' . ltrim($preview, '/'));
    }

    $folder = trim((string) ($image['folder'] ?? ''), '/');
    if ($folder !== '') {
        return esc_url_raw(iss_wf_import_md_asset_base_url() . '/' . $folder . '/' . ltrim($preview, '/'));
    }

    return esc_url_raw(iss_wf_import_md_asset_base_url() . '/' . ltrim($preview, '/'));
}

function iss_wf_import_md_normalize_images(array $images): array
{
    $normalized = [];

    foreach ($images as $image) {
        if (!is_array($image)) {
            continue;
        }

        $source_url = iss_wf_import_md_image_source_url($image);
        $preview_url = iss_wf_import_md_image_preview_url($image);

        if ($source_url === '' && $preview_url === '') {
            continue;
        }

        $normalized[] = [
            'source_id' => absint($image['quell_id'] ?? 0),
            'source_url' => $source_url,
            'preview_url' => $preview_url,
            'attachment_id' => 0,
            'preview_attachment_id' => 0,
            'filename' => sanitize_text_field((string) wp_basename((string) wp_parse_url($source_url !== '' ? $source_url : $preview_url, PHP_URL_PATH))),
            'label' => sanitize_text_field((string) ($image['name'] ?? '')),
            'owner' => sanitize_text_field((string) ($image['owner'] ?? '')),
            'creator' => sanitize_text_field((string) ($image['creator'] ?? '')),
            'rights' => sanitize_text_field((string) ($image['rights'] ?? '')),
            'type' => sanitize_key((string) ($image['type'] ?? '')),
            'is_main' => !empty($image['is_main']) && strtolower((string) $image['is_main']) === 'j',
        ];
    }

    return $normalized;
}

function iss_wf_import_md_normalize_tags(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = absint($item['tag_id'] ?? 0);
        $name = sanitize_text_field((string) ($item['tag_name'] ?? ''));

        if ($id <= 0 && $name === '') {
            continue;
        }

        $normalized[] = [
            'id' => $id,
            'source_id' => $id > 0 ? (string) $id : '',
            'name' => $name,
            'type' => sanitize_key((string) ($item['relation_type'] ?? '')),
            'note' => sanitize_textarea_field((string) ($item['tag_note'] ?? '')),
            'source_url' => '',
        ];
    }

    return $normalized;
}

function iss_wf_import_md_normalize_collections(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = absint($item['collection_id'] ?? 0);
        $name = sanitize_text_field((string) ($item['collection_name'] ?? ''));

        if ($id <= 0 && $name === '') {
            continue;
        }

        $normalized[] = [
            'id' => $id,
            'source_id' => $id > 0 ? (string) $id : '',
            'name' => $name,
            'type' => 'collection',
            'note' => '',
            'source_url' => $id > 0 ? iss_wf_import_md_collection_url($id) : '',
        ];
    }

    return $normalized;
}

function iss_wf_import_md_normalize_series(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $id = absint($item['series_id'] ?? 0);
        $name = sanitize_text_field((string) ($item['series_name'] ?? ''));

        if ($id <= 0 && $name === '') {
            continue;
        }

        $normalized[] = [
            'id' => $id,
            'source_id' => $id > 0 ? (string) $id : '',
            'name' => $name,
            'type' => 'series',
            'note' => '',
            'source_url' => '',
        ];
    }

    return $normalized;
}

function iss_wf_import_md_normalize_place_relations(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $place = is_array($item['place'] ?? null) ? $item['place'] : [];
        $place_id = absint($place['place_id'] ?? 0);
        $place_name = sanitize_text_field((string) ($place['place_name'] ?? ''));

        if ($place_id <= 0 && $place_name === '') {
            continue;
        }

        $normalized[] = [
            'id' => $place_id,
            'source_id' => $place_id > 0 ? (string) $place_id : '',
            'name' => $place_name,
            'type' => sanitize_key((string) ($item['event_type_name'] ?? ('event_' . absint($item['event_type'] ?? 0)))),
            'note' => sanitize_textarea_field((string) ($item['event_note'] ?? '')),
            'source_url' => '',
        ];
    }

    return $normalized;
}

function iss_wf_import_md_normalize_people_relations(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $people = is_array($item['people'] ?? null) ? $item['people'] : [];
        $people_id = absint($people['people_id'] ?? 0);
        $people_name = sanitize_text_field((string) ($people['displayname'] ?? ($people['people_name'] ?? '')));

        if ($people_id <= 0 && $people_name === '') {
            continue;
        }

        $normalized[] = [
            'id' => $people_id,
            'source_id' => $people_id > 0 ? (string) $people_id : '',
            'name' => $people_name,
            'type' => sanitize_key((string) ($item['event_type_name'] ?? ('event_' . absint($item['event_type'] ?? 0)))),
            'note' => sanitize_textarea_field((string) ($item['event_note'] ?? '')),
            'source_url' => '',
        ];
    }

    return $normalized;
}

function iss_wf_import_md_normalize_events(array $items): array
{
    $normalized = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $place = is_array($item['place'] ?? null) ? $item['place'] : [];
        $people = is_array($item['people'] ?? null) ? $item['people'] : [];
        $time = is_array($item['time'] ?? null) ? $item['time'] : [];

        $event_id = absint($item['event_id'] ?? 0);
        $event_type = absint($item['event_type'] ?? 0);
        $event_type_name = sanitize_text_field((string) ($item['event_type_name'] ?? ''));

        if ($event_id <= 0 && $event_type <= 0 && $event_type_name === '') {
            continue;
        }

        $normalized[] = [
            'event_id' => $event_id,
            'event_type' => $event_type,
            'event_type_name' => $event_type_name,
            'note' => sanitize_textarea_field((string) ($item['event_note'] ?? '')),
            'time_label' => sanitize_text_field((string) ($time['time_name'] ?? '')),
            'time_start' => sanitize_text_field((string) ($time['time_start'] ?? '')),
            'time_end' => sanitize_text_field((string) ($time['time_end'] ?? '')),
            'people_id' => absint($people['people_id'] ?? 0),
            'people_name' => sanitize_text_field((string) ($people['displayname'] ?? ($people['people_name'] ?? ''))),
            'place_id' => absint($place['place_id'] ?? 0),
            'place_name' => sanitize_text_field((string) ($place['place_name'] ?? '')),
            'place_latitude' => isset($place['place_latitude']) ? (float) $place['place_latitude'] : 0.0,
            'place_longitude' => isset($place['place_longitude']) ? (float) $place['place_longitude'] : 0.0,
        ];
    }

    return $normalized;
}

function iss_wf_import_md_object_hash(array $payload): string
{
    return md5(wp_json_encode([
        'object_id' => (int) ($payload['object_id'] ?? 0),
        'object_last_updated' => (string) ($payload['object_last_updated'] ?? ''),
        'object_name' => (string) ($payload['object_name'] ?? ''),
        'object_description' => (string) ($payload['object_description'] ?? ''),
        'object_images' => (array) ($payload['object_images'] ?? []),
        'object_collection' => (array) ($payload['object_collection'] ?? []),
        'object_events' => (array) ($payload['object_events'] ?? []),
        'object_relation_places' => (array) ($payload['object_relation_places'] ?? []),
        'object_relation_people' => (array) ($payload['object_relation_people'] ?? []),
        'object_tags' => (array) ($payload['object_tags'] ?? []),
        'object_series' => (array) ($payload['object_series'] ?? []),
        'licence' => (array) ($payload['licence'] ?? []),
    ]));
}

function iss_wf_import_md_collection_hash(array $meta_payload, array $objects_payload): string
{
    $object_ids = [];
    foreach ((array) ($objects_payload['objekte'] ?? []) as $item) {
        if (!is_array($item)) {
            continue;
        }

        $object_ids[] = absint($item['objekt_id'] ?? 0);
    }

    return md5(wp_json_encode([
        'collection_id' => (int) ($meta_payload['collection_id'] ?? 0),
        'collection_last_updated' => (string) ($meta_payload['collection_last_updated'] ?? ''),
        'collection_name' => (string) ($meta_payload['collection_name'] ?? ''),
        'collection_description' => (string) ($meta_payload['collection_description'] ?? ''),
        'collection_image' => (string) ($meta_payload['collection_image'] ?? ''),
        'subcollections' => (array) ($meta_payload['collection_subcollections'] ?? []),
        'object_ids' => $object_ids,
    ]));
}

function iss_wf_import_sync_collection_sequence_relations(array $object_ids, string $context, string $label = ''): void
{
    $object_ids = array_values(array_filter(array_map('absint', $object_ids)));
    $context = sanitize_text_field($context);
    $label = sanitize_text_field($label);

    if (!$object_ids || $context === '') {
        return;
    }

    foreach ($object_ids as $index => $post_id) {
        $existing = get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_RELATIONS_META, true);
        $relations = is_array($existing) ? $existing : [];
        $clean = [];

        foreach ($relations as $relation) {
            if (!is_array($relation)) {
                continue;
            }

            $relation_type = sanitize_key((string) ($relation['relation_type'] ?? ''));
            $relation_context = sanitize_text_field((string) ($relation['context'] ?? ''));

            if ($relation_context === $context && in_array($relation_type, ['sequence_prev', 'sequence_next'], true)) {
                continue;
            }

            $clean[] = $relation;
        }

        if (!empty($object_ids[$index - 1])) {
            $clean[] = [
                'object_id' => (int) $object_ids[$index - 1],
                'source_object_id' => '',
                'relation_type' => 'sequence_prev',
                'context' => $context,
                'weight' => 1000 - $index,
                'label' => $label,
            ];
        }

        if (!empty($object_ids[$index + 1])) {
            $clean[] = [
                'object_id' => (int) $object_ids[$index + 1],
                'source_object_id' => '',
                'relation_type' => 'sequence_next',
                'context' => $context,
                'weight' => 1000 - $index,
                'label' => $label,
            ];
        }

        update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_RELATIONS_META, array_values($clean));
    }
}

function iss_wf_import_upsert_md_object(int $object_id, array $options = []): array
{
    $payload = isset($options['payload']) && is_array($options['payload'])
        ? $options['payload']
        : iss_wf_import_fetch_md_object($object_id);

    if (is_wp_error($payload)) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => $payload->get_error_message(),
            'object_id' => $object_id,
        ];
    }

    $object_id = absint($payload['object_id'] ?? $object_id);
    if ($object_id <= 0) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => 'museum-digital payload has no object id.',
            'object_id' => 0,
        ];
    }

    $existing_post_id = iss_wf_import_find_post_by_source_identity(
        ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'museum_digital_object',
        (string) $object_id
    );
    $force = !empty($options['force']);
    $media = in_array((string) ($options['media'] ?? 'all'), ['all', 'featured', 'none'], true)
        ? (string) $options['media']
        : 'all';

    $hash = iss_wf_import_md_object_hash($payload);

    $images = iss_wf_import_md_normalize_images((array) ($payload['object_images'] ?? []));
    $title = trim((string) ($payload['object_name'] ?? ''));
    if ($title === '') {
        $title = 'Archivobjekt ' . $object_id;
    }

    $description = trim((string) ($payload['object_description'] ?? ''));
    $content = iss_wf_import_md_text_to_html($description);
    $excerpt = iss_wf_import_md_excerpt_from_text($description);
    $institution = is_array($payload['object_institution'] ?? null) ? $payload['object_institution'] : [];
    $licence = is_array($payload['licence'] ?? null) ? $payload['licence'] : [];

    $meta_input = [
        ISS_WF_IMPORT_SOURCE_SITE_META => 'museum-digital',
        ISS_WF_IMPORT_SOURCE_KIND_META => 'museum_digital_object',
        ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META => (string) $object_id,
        ISS_WF_IMPORT_SOURCE_URL_META => iss_wf_import_md_object_url($object_id),
        ISS_WF_IMPORT_SOURCE_SLUG_META => 'object-' . $object_id,
        ISS_WF_IMPORT_SOURCE_DATE_GMT_META => '',
        ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META => sanitize_text_field((string) ($payload['object_last_updated'] ?? '')),
        ISS_WF_IMPORT_SOURCE_AUTHOR_META => sanitize_text_field((string) ($institution['institution_name'] ?? '')),
        ISS_WF_IMPORT_HASH_META => $hash,
        ISS_WF_IMPORT_LAST_SYNCED_META => current_time('mysql', true),
        ISS_WF_IMPORT_OBJECT_TYPE_META => sanitize_text_field((string) ($payload['object_type'] ?? '')),
        ISS_WF_IMPORT_OBJECT_INVENTORY_META => sanitize_text_field((string) ($payload['object_inventory_number'] ?? '')),
        ISS_WF_IMPORT_OBJECT_RIGHTS_HOLDER_META => sanitize_text_field((string) ($licence['metadata_rights_holder'] ?? '')),
        ISS_WF_IMPORT_OBJECT_RIGHTS_STATUS_META => sanitize_text_field((string) ($licence['metadata_rights_status'] ?? '')),
        ISS_WF_IMPORT_OBJECT_CREATOR_META => sanitize_text_field((string) ($images[0]['creator'] ?? '')),
        ISS_WF_IMPORT_OBJECT_MATERIAL_META => sanitize_text_field((string) ($payload['object_material_technique'] ?? '')),
        ISS_WF_IMPORT_OBJECT_DIMENSIONS_META => sanitize_text_field((string) ($payload['object_dimensions'] ?? '')),
        ISS_WF_IMPORT_OBJECT_JSON_URL_META => iss_wf_import_md_base_url() . '/json/object/' . $object_id,
        ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META => $images,
        ISS_WF_IMPORT_OBJECT_TAGS_META => iss_wf_import_md_normalize_tags((array) ($payload['object_tags'] ?? [])),
        ISS_WF_IMPORT_OBJECT_COLLECTIONS_META => iss_wf_import_md_normalize_collections((array) ($payload['object_collection'] ?? [])),
        ISS_WF_IMPORT_OBJECT_SERIES_META => iss_wf_import_md_normalize_series((array) ($payload['object_series'] ?? [])),
        ISS_WF_IMPORT_OBJECT_EVENTS_META => iss_wf_import_md_normalize_events((array) ($payload['object_events'] ?? [])),
        ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META => iss_wf_import_md_normalize_place_relations((array) ($payload['object_relation_places'] ?? [])),
        ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META => iss_wf_import_md_normalize_people_relations((array) ($payload['object_relation_people'] ?? [])),
    ];

    if ($existing_post_id > 0 && !$force) {
        $existing_hash = (string) get_post_meta($existing_post_id, ISS_WF_IMPORT_HASH_META, true);
        if ($existing_hash === $hash) {
            update_post_meta($existing_post_id, ISS_WF_IMPORT_LAST_SYNCED_META, current_time('mysql', true));
            iss_wf_import_assign_source_term($existing_post_id, 'museum-digital', 'museum-digital');

            return [
                'status' => 'skipped',
                'post_id' => $existing_post_id,
                'message' => 'Unchanged.',
                'object_id' => $object_id,
            ];
        }
    }

    $postarr = [
        'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => sanitize_title($title),
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'meta_input' => $meta_input,
    ];

    if ($existing_post_id > 0) {
        $postarr['ID'] = $existing_post_id;
        $post_id = wp_update_post($postarr, true);
        $status = 'updated';
    } else {
        $post_id = wp_insert_post($postarr, true);
        $status = 'imported';
    }

    if (is_wp_error($post_id)) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => $post_id->get_error_message(),
            'object_id' => $object_id,
        ];
    }

    $post_id = (int) $post_id;
    iss_wf_import_assign_source_term($post_id, 'museum-digital', 'museum-digital');

    $primary_attachment_id = 0;
    $preview_attachment_id = 0;
    $thumbnail_attachment_id = 0;

    if ($media !== 'none' && $images) {
        foreach ($images as $index => $image) {
            $image_title = $image['label'] !== '' ? $image['label'] : $title;
            $source_attachment_id = 0;
            $preview_image_attachment_id = 0;

            if ($image['source_url'] !== '') {
                $source_attachment_id = iss_wf_import_get_or_create_attachment($image['source_url'], $post_id, $image_title);
            }

            if (($media === 'all' || $source_attachment_id <= 0) && $image['preview_url'] !== '') {
                $preview_image_attachment_id = iss_wf_import_get_or_create_attachment($image['preview_url'], $post_id, $image_title . ' Vorschau');
            }

            $images[$index]['attachment_id'] = $source_attachment_id > 0 ? $source_attachment_id : $preview_image_attachment_id;
            $images[$index]['preview_attachment_id'] = $preview_image_attachment_id;

            if ($primary_attachment_id <= 0 && $source_attachment_id > 0) {
                $primary_attachment_id = $source_attachment_id;
            } elseif ($primary_attachment_id <= 0 && $preview_image_attachment_id > 0) {
                $primary_attachment_id = $preview_image_attachment_id;
            }

            if ($preview_attachment_id <= 0 && $preview_image_attachment_id > 0) {
                $preview_attachment_id = $preview_image_attachment_id;
            }

            if ($thumbnail_attachment_id <= 0) {
                if ($preview_image_attachment_id > 0 && wp_attachment_is_image($preview_image_attachment_id)) {
                    $thumbnail_attachment_id = $preview_image_attachment_id;
                } elseif ($source_attachment_id > 0 && wp_attachment_is_image($source_attachment_id)) {
                    $thumbnail_attachment_id = $source_attachment_id;
                }
            }
        }

        update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, $images);
    }

    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_PRIMARY_ATTACHMENT_META, $primary_attachment_id);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_PREVIEW_ATTACHMENT_META, $preview_attachment_id);

    if ($thumbnail_attachment_id > 0) {
        set_post_thumbnail($post_id, $thumbnail_attachment_id);
    } else {
        delete_post_thumbnail($post_id);
    }

    return [
        'status' => $status,
        'post_id' => $post_id,
        'message' => '',
        'object_id' => $object_id,
    ];
}

function iss_wf_import_upsert_md_collection(int $collection_id, array $options = []): array
{
    $meta_payload = isset($options['meta_payload']) && is_array($options['meta_payload'])
        ? $options['meta_payload']
        : iss_wf_import_fetch_md_collection($collection_id);

    if (is_wp_error($meta_payload)) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => $meta_payload->get_error_message(),
            'collection_id' => $collection_id,
            'object_stats' => [],
        ];
    }

    $objects_payload = isset($options['objects_payload']) && is_array($options['objects_payload'])
        ? $options['objects_payload']
        : iss_wf_import_fetch_md_collection_objects($collection_id);

    if (is_wp_error($objects_payload)) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => $objects_payload->get_error_message(),
            'collection_id' => $collection_id,
            'object_stats' => [],
        ];
    }

    $collection_id = absint($meta_payload['collection_id'] ?? $collection_id);
    if ($collection_id <= 0) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => 'museum-digital payload has no collection id.',
            'collection_id' => 0,
            'object_stats' => [],
        ];
    }

    $existing_post_id = iss_wf_import_find_post_by_source_identity(
        ISS_WF_IMPORT_COLLECTION_POST_TYPE,
        'museum_digital_collection',
        (string) $collection_id
    );
    $force = !empty($options['force']);
    $limit = max(0, absint($options['limit'] ?? 0));
    $media = in_array((string) ($options['media'] ?? 'all'), ['all', 'featured', 'none'], true)
        ? (string) $options['media']
        : 'all';
    $skip_objects = !empty($options['skip_objects']);

    $hash = iss_wf_import_md_collection_hash($meta_payload, $objects_payload);
    $title = trim((string) ($meta_payload['collection_name'] ?? ''));
    if ($title === '') {
        $title = 'Archivsammlung ' . $collection_id;
    }

    $description = trim((string) ($meta_payload['collection_description'] ?? ''));
    $institution = is_array($meta_payload['collection_institution'] ?? null) ? $meta_payload['collection_institution'] : [];

    $collection_children = [];
    foreach ((array) ($meta_payload['collection_subcollections'] ?? []) as $index => $item) {
        if (!is_array($item)) {
            continue;
        }

        $collection_children[] = [
            'collection_id' => 0,
            'position' => $index + 1,
            'title' => sanitize_text_field((string) ($item['collection_name'] ?? '')),
            'slug' => sanitize_title((string) ($item['collection_name'] ?? '')),
            'source_external_id' => sanitize_text_field((string) ($item['collection_id'] ?? '')),
            'source_url' => !empty($item['collection_id']) ? iss_wf_import_md_collection_url(absint($item['collection_id'])) : '',
        ];
    }

    if ($existing_post_id > 0 && !$force) {
        $existing_hash = (string) get_post_meta($existing_post_id, ISS_WF_IMPORT_HASH_META, true);
        if ($existing_hash === $hash) {
            update_post_meta($existing_post_id, ISS_WF_IMPORT_LAST_SYNCED_META, current_time('mysql', true));
            iss_wf_import_assign_source_term($existing_post_id, 'museum-digital', 'museum-digital');

            return [
                'status' => 'skipped',
                'post_id' => $existing_post_id,
                'message' => 'Unchanged.',
                'collection_id' => $collection_id,
                'object_stats' => [],
            ];
        }
    }

    $postarr = [
        'post_type' => ISS_WF_IMPORT_COLLECTION_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => sanitize_title($title),
        'post_content' => iss_wf_import_md_text_to_html($description),
        'post_excerpt' => iss_wf_import_md_excerpt_from_text($description),
        'meta_input' => [
            ISS_WF_IMPORT_SOURCE_SITE_META => 'museum-digital',
            ISS_WF_IMPORT_SOURCE_KIND_META => 'museum_digital_collection',
            ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META => (string) $collection_id,
            ISS_WF_IMPORT_SOURCE_URL_META => iss_wf_import_md_collection_url($collection_id),
            ISS_WF_IMPORT_SOURCE_SLUG_META => 'collection-' . $collection_id,
            ISS_WF_IMPORT_SOURCE_DATE_GMT_META => '',
            ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META => sanitize_text_field((string) ($meta_payload['collection_last_updated'] ?? '')),
            ISS_WF_IMPORT_SOURCE_AUTHOR_META => sanitize_text_field((string) ($institution['institution_name'] ?? '')),
            ISS_WF_IMPORT_COLLECTION_CHILDREN_META => $collection_children,
            ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META => [[
                'source_kind' => 'museum_digital_collection',
                'source_id' => (string) $collection_id,
                'source_url' => iss_wf_import_md_collection_url($collection_id),
                'label' => $title,
            ]],
            ISS_WF_IMPORT_HASH_META => $hash,
            ISS_WF_IMPORT_LAST_SYNCED_META => current_time('mysql', true),
        ],
    ];

    if ($existing_post_id > 0) {
        $postarr['ID'] = $existing_post_id;
        $post_id = wp_update_post($postarr, true);
        $status = 'updated';
    } else {
        $post_id = wp_insert_post($postarr, true);
        $status = 'imported';
    }

    if (is_wp_error($post_id)) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => $post_id->get_error_message(),
            'collection_id' => $collection_id,
            'object_stats' => [],
        ];
    }

    $post_id = (int) $post_id;
    iss_wf_import_assign_source_term($post_id, 'museum-digital', 'museum-digital');

    if ($media !== 'none') {
        $collection_image_url = iss_wf_import_md_collection_image_url((string) ($meta_payload['collection_image'] ?? ''));
        if ($collection_image_url !== '') {
            $attachment_id = iss_wf_import_get_or_create_attachment($collection_image_url, $post_id, $title);
            if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
                set_post_thumbnail($post_id, $attachment_id);
            }
        }
    }

    $object_stats = [
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'processed' => 0,
        'object_ids' => [],
        'errors' => [],
    ];

    $collection_items = [];

    if (!$skip_objects) {
        foreach ((array) ($objects_payload['objekte'] ?? []) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            $remote_object_id = absint($item['objekt_id'] ?? 0);
            if ($remote_object_id <= 0) {
                continue;
            }

            $result = iss_wf_import_upsert_md_object($remote_object_id, [
                'force' => $force,
                'media' => $media,
            ]);

            if (isset($object_stats[$result['status']])) {
                $object_stats[$result['status']]++;
            }

            $object_stats['processed']++;

            if (!empty($result['post_id'])) {
                $object_stats['object_ids'][] = (int) $result['post_id'];
            }

            if (!empty($result['message']) && $result['status'] === 'failed') {
                $object_stats['errors'][] = sprintf('Object %d: %s', $remote_object_id, $result['message']);
            }

            $collection_items[] = [
                'object_id' => (int) ($result['post_id'] ?? 0),
                'position' => $index + 1,
                'caption_override' => '',
                'page_label' => '',
                'title' => sanitize_text_field((string) ($item['objekt_name'] ?? '')),
                'source_object_id' => (string) $remote_object_id,
                'source_url' => iss_wf_import_md_object_url($remote_object_id),
            ];

            if ($limit > 0 && $object_stats['processed'] >= $limit) {
                break;
            }
        }
    }

    update_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_ITEMS_META, $collection_items);
    iss_wf_import_sync_collection_sequence_relations(
        array_values(array_filter(array_map(static function (array $item): int {
            return absint($item['object_id'] ?? 0);
        }, $collection_items))),
        'collection:md:' . $collection_id,
        $title
    );

    return [
        'status' => $status,
        'post_id' => $post_id,
        'message' => '',
        'collection_id' => $collection_id,
        'object_stats' => $object_stats,
    ];
}
