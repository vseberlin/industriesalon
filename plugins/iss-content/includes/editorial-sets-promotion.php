<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_editorial_sets_find_event_drop_attachment(string $stored_name, string $sha256 = ''): int
{
    $meta_query = [
        [
            'key' => '_event_drop_stored_name',
            'value' => sanitize_file_name($stored_name),
            'compare' => '=',
        ],
    ];

    if ($sha256 !== '') {
        $meta_query[] = [
            'key' => '_event_drop_sha256',
            'value' => sanitize_text_field($sha256),
            'compare' => '=',
        ];
    }

    $existing = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'fields' => 'ids',
        'numberposts' => 1,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Attachment provenance lookup keeps Event Drop imports idempotent.
        'meta_query' => [
            'relation' => 'OR',
            ...$meta_query,
        ],
    ]);

    return !empty($existing) ? (int) reset($existing) : 0;
}

function iss_content_editorial_sets_external_upload_import_meta(array $item): array
{
    $provenance = json_decode((string) ($item['provenance_json'] ?? ''), true);
    $rights = json_decode((string) ($item['rights_json'] ?? ''), true);
    $provenance = is_array($provenance) ? $provenance : [];
    $rights = is_array($rights) ? $rights : [];

    return [
        'event_slug' => sanitize_title((string) ($provenance['event_slug'] ?? '')),
        'participant_id' => sanitize_text_field((string) ($provenance['participant_id'] ?? '')),
        'original_name' => sanitize_text_field((string) ($item['label'] ?? $provenance['stored_name'] ?? '')),
        'stored_name' => sanitize_file_name((string) ($provenance['stored_name'] ?? $item['source_id'] ?? '')),
        'size_bytes' => sanitize_text_field((string) ($provenance['size_bytes'] ?? '')),
        'sha256' => sanitize_text_field((string) ($provenance['sha256'] ?? '')),
        'uploaded_at' => sanitize_text_field((string) ($provenance['uploaded_at'] ?? '')),
        'attribution' => sanitize_text_field((string) ($rights['attribution'] ?? $provenance['participant_id'] ?? '')),
        'license' => sanitize_text_field((string) ($rights['license'] ?? 'all-rights-reserved')),
        'consent' => (string) ($rights['consent'] ?? '') === '1' ? '1' : '0',
        'uploader_email' => sanitize_email((string) ($rights['uploader_email'] ?? '')),
    ];
}

function iss_content_editorial_sets_import_external_upload_item(array $item, int $target_id): int
{
    if ((string) ($item['kind'] ?? '') !== 'external_upload' || (string) ($item['source'] ?? '') !== 'event-drop') {
        return 0;
    }

    $meta = iss_content_editorial_sets_external_upload_import_meta($item);
    $stored_name = (string) ($meta['stored_name'] ?? '');
    if ($stored_name === '') {
        return 0;
    }

    $existing = iss_content_editorial_sets_find_event_drop_attachment($stored_name, (string) ($meta['sha256'] ?? ''));
    if ($existing > 0) {
        return $existing;
    }

    $source_file = function_exists('iss_content_editorial_sets_resolve_external_upload_path')
        ? iss_content_editorial_sets_resolve_external_upload_path($item)
        : '';
    if ($source_file === '' || !is_file($source_file) || !is_readable($source_file)) {
        return 0;
    }

    $file_type = wp_check_filetype_and_ext($source_file, $stored_name);
    if (!is_array($file_type) || empty($file_type['type'])) {
        return 0;
    }

    $upload_dir = wp_upload_dir();
    if (!empty($upload_dir['error'])) {
        return 0;
    }

    $destination_dir = trailingslashit((string) $upload_dir['basedir']) . 'event-drop-storage/accepted';
    if (!wp_mkdir_p($destination_dir)) {
        return 0;
    }

    $destination_path = rtrim($destination_dir, '/\\') . '/' . $stored_name;
    if (is_file($destination_path)) {
        $source_hash = is_readable($source_file) ? (string) hash_file('sha256', $source_file) : '';
        $destination_hash = is_readable($destination_path) ? (string) hash_file('sha256', $destination_path) : '';
        if ($source_hash === '' || $destination_hash === '' || !hash_equals($source_hash, $destination_hash)) {
            $destination_path = rtrim($destination_dir, '/\\') . '/' . wp_unique_filename($destination_dir, $stored_name);
        }
    }

    if (!is_file($destination_path) && !copy($source_file, $destination_path)) {
        return 0;
    }

    $title = (string) ($meta['attribution'] ?? '');
    if ($title === '') {
        $title = pathinfo($stored_name, PATHINFO_FILENAME);
    }

    $attachment_id = wp_insert_attachment(
        [
            'post_mime_type' => (string) $file_type['type'],
            'post_title' => sanitize_text_field($title),
            'post_status' => 'inherit',
            'post_parent' => $target_id,
            'post_content' => '',
            'post_excerpt' => sanitize_text_field((string) ($meta['attribution'] ?? '')),
        ],
        $destination_path,
        $target_id
    );

    if ((int) $attachment_id <= 0) {
        return 0;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $metadata = wp_generate_attachment_metadata((int) $attachment_id, $destination_path);
    if (is_array($metadata) && $metadata !== []) {
        wp_update_attachment_metadata((int) $attachment_id, $metadata);
    }

    update_post_meta((int) $attachment_id, '_event_drop_stored_name', $stored_name);
    update_post_meta((int) $attachment_id, '_event_drop_event_ref', (string) ($meta['event_slug'] ?? ''));
    update_post_meta((int) $attachment_id, '_event_drop_original_name', (string) ($meta['original_name'] ?? ''));
    update_post_meta((int) $attachment_id, '_event_drop_size_bytes', (string) ($meta['size_bytes'] ?? ''));
    update_post_meta((int) $attachment_id, '_event_drop_sha256', (string) ($meta['sha256'] ?? ''));
    update_post_meta((int) $attachment_id, '_event_drop_uploader', (string) ($meta['participant_id'] ?? ''));
    update_post_meta((int) $attachment_id, '_event_drop_uploader_email', (string) ($meta['uploader_email'] ?? ''));
    update_post_meta((int) $attachment_id, '_event_drop_attribution', (string) ($meta['attribution'] ?? ''));
    update_post_meta((int) $attachment_id, '_event_drop_license', (string) (($meta['license'] ?? '') ?: 'all-rights-reserved'));
    update_post_meta((int) $attachment_id, '_event_drop_consent', (string) ($meta['consent'] ?? '0'));
    update_post_meta((int) $attachment_id, '_event_drop_uploaded_at', (string) ($meta['uploaded_at'] ?? ''));
    update_post_meta((int) $attachment_id, '_event_drop_source_path', $source_file);
    update_post_meta((int) $attachment_id, '_event_drop_publish_path', $destination_path);
    update_post_meta((int) $attachment_id, '_event_drop_mime_type', (string) $file_type['type']);
    update_post_meta((int) $attachment_id, '_event_drop_synced_at', gmdate('c'));

    if (get_post_type($target_id) === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE && (string) ($meta['event_slug'] ?? '') !== '' && function_exists('iss_content_editorial_sets_event_drop_set_for_attachment')) {
        iss_content_editorial_sets_event_drop_set_for_attachment((int) $attachment_id);
    }

    return (int) $attachment_id;
}

function iss_content_editorial_sets_reference_from_item(array $item, int $target_id = 0): array
{
    $kind = (string) ($item['kind'] ?? '');
    $source = (string) ($item['source'] ?? '');
    $id = (string) ($item['source_id'] ?? '');
    $label = sanitize_textarea_field((string) ($item['label'] ?? ''));

    if ($kind === 'wp_media' && $source === 'wp-media') {
        $attachment_id = absint($id);
        if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
            return [];
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        return [
            'kind' => 'media',
            'source' => 'wp-media',
            'id' => (string) $attachment_id,
            'label' => $label !== '' ? $label : (string) get_the_title($attachment_id),
            'width' => is_array($metadata) ? (string) absint($metadata['width'] ?? 0) : '',
            'height' => is_array($metadata) ? (string) absint($metadata['height'] ?? 0) : '',
        ];
    }

    if ($kind === 'external_upload' && $source === 'event-drop' && $target_id > 0) {
        $attachment_id = iss_content_editorial_sets_import_external_upload_item($item, $target_id);
        if ($attachment_id <= 0) {
            return [];
        }

        return iss_content_editorial_sets_reference_from_item([
            'kind' => 'wp_media',
            'source' => 'wp-media',
            'source_id' => (string) $attachment_id,
            'label' => $label,
        ]);
    }

    if ($kind === 'archive_object' && $source === 'iss-archive') {
        $post_id = absint($id);
        if ($post_id <= 0 || get_post($post_id) === null) {
            return [];
        }

        return [
            'kind' => 'archive_object',
            'source' => 'iss-archive',
            'id' => (string) $post_id,
            'label' => $label !== '' ? $label : (string) get_the_title($post_id),
            'thumbnail' => (string) get_the_post_thumbnail_url($post_id, 'medium'),
        ];
    }

    return [];
}

function iss_content_editorial_sets_reference_field_for_item(array $item): string
{
    return (string) ($item['kind'] ?? '') === 'archive_object' ? 'object_refs' : 'media_refs';
}

function iss_content_editorial_sets_reference_mime(array $reference): string
{
    if ((string) ($reference['kind'] ?? '') === 'archive_object') {
        return 'archive/object';
    }

    if ((string) ($reference['source'] ?? '') !== 'wp-media') {
        return '';
    }

    $attachment_id = absint($reference['id'] ?? 0);
    return $attachment_id > 0 ? (string) get_post_mime_type($attachment_id) : '';
}

function iss_content_editorial_sets_project_section_type_for_entry(array $entry): string
{
    if ((string) ($entry['field'] ?? '') === 'object_refs') {
        return 'material';
    }

    $mime = iss_content_editorial_sets_reference_mime((array) ($entry['reference'] ?? []));
    if (strpos($mime, 'image/') === 0 || strpos($mime, 'video/') === 0) {
        return 'galerie';
    }

    return 'material';
}

function iss_content_editorial_sets_mark_external_upload_imported(array $item, array $reference): bool
{
    if ((string) ($item['kind'] ?? '') !== 'external_upload' || (string) ($item['source'] ?? '') !== 'event-drop') {
        return false;
    }

    $attachment_id = absint($reference['id'] ?? 0);
    if ($attachment_id <= 0 || get_post_type($attachment_id) !== 'attachment') {
        return false;
    }

    $provenance = json_decode((string) ($item['provenance_json'] ?? ''), true);
    $provenance = is_array($provenance) ? $provenance : [];
    $provenance['storage_state'] = 'imported';
    $provenance['imported_attachment_id'] = (string) $attachment_id;
    $provenance['imported_at'] = gmdate('c');
    $provenance['publish_path'] = (string) get_post_meta($attachment_id, '_event_drop_publish_path', true);

    return iss_content_editorial_sets_service()->update_item((int) ($item['id'] ?? 0), [
        'status' => 'promoted',
        'provenance' => $provenance,
    ]);
}

function iss_content_editorial_sets_reference_exists(array $references, array $reference): bool
{
    $source = (string) ($reference['source'] ?? '');
    $id = (string) ($reference['id'] ?? '');
    if ($source === '' || $id === '') {
        return false;
    }

    foreach ($references as $existing) {
        if (!is_array($existing)) {
            continue;
        }
        if ((string) ($existing['source'] ?? '') === $source && (string) ($existing['id'] ?? '') === $id) {
            return true;
        }
    }

    return false;
}

function iss_content_editorial_sets_collect_approved_items(array $item_ids, int $target_id = 0): array
{
    $service = iss_content_editorial_sets_service();
    $items = [];

    foreach ($item_ids as $item_id) {
        $item = $service->get_item(absint($item_id));
        if (!$item || (string) ($item['status'] ?? '') !== 'approved') {
            continue;
        }
        $reference = iss_content_editorial_sets_reference_from_item($item, $target_id);
        if (!$reference) {
            continue;
        }
        $items[] = [
            'item' => $item,
            'reference' => $reference,
            'field' => iss_content_editorial_sets_reference_field_for_item($item),
        ];
    }

    return $items;
}

function iss_content_editorial_sets_promote_to_veranstaltung(int $post_id, array $item_ids, string $section_title = ''): array
{
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return ['promoted' => 0, 'message' => __('Target is not a Veranstaltung.', 'iss-content-model')];
    }

    $items = iss_content_editorial_sets_collect_approved_items($item_ids, $post_id);
    if (!$items) {
        return ['promoted' => 0, 'message' => __('No approved promotable items selected.', 'iss-content-model')];
    }

    $document = function_exists('iss_content_model_veranstaltung_content_document')
        ? iss_content_model_veranstaltung_content_document($post_id)
        : [];
    if (!$document) {
        $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
            ? iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true))
            : '';
        $document = iss_content_model_veranstaltung_empty_content_document($entity_key);
    }

    if (!isset($document['sections']) || !is_array($document['sections'])) {
        $document['sections'] = [];
    }

    $section_index = null;
    foreach ($document['sections'] as $index => $section) {
        if (is_array($section) && (string) ($section['type'] ?? '') === 'galerie') {
            $section_index = $index;
            break;
        }
    }

    if ($section_index === null) {
        $document['sections'][] = [
            'type' => 'galerie',
            'title' => $section_title !== '' ? sanitize_text_field($section_title) : __('Galerie', 'iss-content-model'),
            'media_refs' => [],
            'object_refs' => [],
        ];
        $section_index = count($document['sections']) - 1;
    }

    $service = iss_content_editorial_sets_service();
    $promoted = 0;
    foreach ($items as $entry) {
        $field = (string) $entry['field'];
        if (!isset($document['sections'][$section_index][$field]) || !is_array($document['sections'][$section_index][$field])) {
            $document['sections'][$section_index][$field] = [];
        }
        if (!iss_content_editorial_sets_reference_exists($document['sections'][$section_index][$field], $entry['reference'])) {
            $document['sections'][$section_index][$field][] = $entry['reference'];
        }
        if ($service->mark_promoted((int) $entry['item']['id'], 'veranstaltung', $post_id)) {
            iss_content_editorial_sets_mark_external_upload_imported($entry['item'], $entry['reference']);
            $promoted++;
        }
    }

    $encoded = iss_content_model_sanitize_veranstaltung_content_json((string) wp_json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    if ($encoded === '') {
        return ['promoted' => 0, 'message' => __('Promotion produced an invalid Veranstaltung document.', 'iss-content-model')];
    }

    update_post_meta($post_id, iss_content_model_veranstaltung_content_meta_key(), wp_slash($encoded));

    return ['promoted' => $promoted, 'message' => __('Items promoted to Veranstaltung gallery.', 'iss-content-model')];
}

function iss_content_editorial_sets_promote_to_editorial_document(int $post_id, string $format_slug, array $item_ids, string $section_type = ''): array
{
    if (!function_exists('iss_editorial_get_format') || !function_exists('iss_editorial_save_document')) {
        return ['promoted' => 0, 'message' => __('Editorial document engine is unavailable.', 'iss-content-model')];
    }

    $format_slug = sanitize_key($format_slug);
    $format = iss_editorial_get_format($format_slug);
    if (!$format || !iss_editorial_post_type_supports_format((string) get_post_type($post_id), $format_slug)) {
        return ['promoted' => 0, 'message' => __('Target does not support this editorial format.', 'iss-content-model')];
    }

    $items = iss_content_editorial_sets_collect_approved_items($item_ids, $post_id);
    if (!$items) {
        return ['promoted' => 0, 'message' => __('No approved promotable items selected.', 'iss-content-model')];
    }

    $section_type = sanitize_key($section_type);
    if ($section_type === '' || !isset($format['sections'][$section_type])) {
        $preferred_sections = get_post_type($post_id) === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE
            ? ['galerie', 'material', 'image_wall', 'kapitel']
            : ['galerie', 'bildstrecke', 'kapitel'];
        foreach ($preferred_sections as $candidate) {
            if (isset($format['sections'][$candidate])) {
                $section_type = $candidate;
                break;
            }
        }
    }
    if ($section_type === '') {
        $section_type = (string) array_key_first((array) $format['sections']);
    }

    $section = [
        'type' => $section_type,
        'title' => __('Aus Set promoviert', 'iss-content-model'),
        'body' => '',
        'media_refs' => [],
        'object_refs' => [],
    ];
    $service = iss_content_editorial_sets_service();
    $promoted = 0;
    foreach ($items as $entry) {
        $field = (string) $entry['field'];
        if (isset($section[$field])) {
            $section[$field][] = $entry['reference'];
        }
        if ($service->mark_promoted((int) $entry['item']['id'], (string) get_post_type($post_id), $post_id)) {
            iss_content_editorial_sets_mark_external_upload_imported($entry['item'], $entry['reference']);
            $promoted++;
        }
    }

    $document = iss_editorial_get_document($post_id, $format_slug, false);
    if (!isset($document['sections']) || !is_array($document['sections'])) {
        $document = iss_editorial_get_empty_document($format_slug);
    }
    $document['sections'][] = $section;

    if (!iss_editorial_save_document($post_id, $format_slug, $document, false)) {
        return ['promoted' => 0, 'message' => __('Editorial document save failed.', 'iss-content-model')];
    }

    return ['promoted' => $promoted, 'message' => __('Items promoted to editorial document.', 'iss-content-model')];
}

function iss_content_editorial_sets_find_or_append_section(array &$document, string $section_type, string $title): int
{
    if (!isset($document['sections']) || !is_array($document['sections'])) {
        $document['sections'] = [];
    }

    foreach ($document['sections'] as $index => $section) {
        if (is_array($section) && (string) ($section['type'] ?? '') === $section_type) {
            return (int) $index;
        }
    }

    $document['sections'][] = [
        'type' => $section_type,
        'title' => $title,
        'body' => '',
        'media_refs' => [],
        'object_refs' => [],
        'links' => [],
    ];

    return count($document['sections']) - 1;
}

function iss_content_editorial_sets_promote_to_project(int $post_id, string $format_slug, array $item_ids): array
{
    if (!function_exists('iss_editorial_get_format') || !function_exists('iss_editorial_save_document')) {
        return ['promoted' => 0, 'message' => __('Editorial document engine is unavailable.', 'iss-content-model')];
    }

    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return ['promoted' => 0, 'message' => __('Target is not a project.', 'iss-content-model')];
    }

    $format_slug = sanitize_key($format_slug);
    $format = iss_editorial_get_format($format_slug);
    if (!$format || !isset($format['sections']['galerie'], $format['sections']['material'])) {
        return iss_content_editorial_sets_promote_to_editorial_document($post_id, $format_slug, $item_ids);
    }

    $items = iss_content_editorial_sets_collect_approved_items($item_ids, $post_id);
    if (!$items) {
        return ['promoted' => 0, 'message' => __('No approved promotable items selected.', 'iss-content-model')];
    }

    $document = iss_editorial_get_document($post_id, $format_slug, false);
    if (!isset($document['sections']) || !is_array($document['sections'])) {
        $document = iss_editorial_get_empty_document($format_slug);
    }

    $section_titles = [
        'galerie' => __('Galerie', 'iss-content-model'),
        'material' => __('Material', 'iss-content-model'),
    ];
    $pending_marks = [];

    foreach ($items as $entry) {
        $target_section = iss_content_editorial_sets_project_section_type_for_entry($entry);
        $section_index = iss_content_editorial_sets_find_or_append_section(
            $document,
            $target_section,
            (string) ($section_titles[$target_section] ?? __('Aus Set promoviert', 'iss-content-model'))
        );
        $field = (string) $entry['field'];
        if (!isset($document['sections'][$section_index][$field]) || !is_array($document['sections'][$section_index][$field])) {
            $document['sections'][$section_index][$field] = [];
        }
        if (!iss_content_editorial_sets_reference_exists($document['sections'][$section_index][$field], $entry['reference'])) {
            $document['sections'][$section_index][$field][] = $entry['reference'];
        }
        $pending_marks[] = $entry;
    }

    if (!iss_editorial_save_document($post_id, $format_slug, $document, false)) {
        return ['promoted' => 0, 'message' => __('Editorial document save failed.', 'iss-content-model')];
    }

    $service = iss_content_editorial_sets_service();
    $promoted = 0;
    foreach ($pending_marks as $entry) {
        if ($service->mark_promoted((int) $entry['item']['id'], ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, $post_id)) {
            iss_content_editorial_sets_mark_external_upload_imported($entry['item'], $entry['reference']);
            $promoted++;
        }
    }

    return [
        'promoted' => $promoted,
        'message' => __('Project items promoted to gallery and material sections.', 'iss-content-model'),
    ];
}

function iss_content_editorial_sets_promote(int $target_id, string $target_type, array $item_ids, array $args = []): array
{
    $target_type = sanitize_key($target_type);
    $target_id = absint($target_id);
    if ($target_id <= 0) {
        return ['promoted' => 0, 'message' => __('Missing target.', 'iss-content-model')];
    }

    $custom = apply_filters('iss_content_editorial_set_promote_target', null, $target_id, $target_type, $item_ids, $args);
    if (is_array($custom)) {
        return $custom;
    }

    if ($target_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return iss_content_editorial_sets_promote_to_veranstaltung($target_id, $item_ids, (string) ($args['sectionTitle'] ?? ''));
    }

    if (function_exists('iss_editorial_get_format_for_post_type')) {
        $format = iss_editorial_get_format_for_post_type($target_type);
        if ($format) {
            if ($target_type === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE && (string) ($args['sectionType'] ?? '') === '') {
                return iss_content_editorial_sets_promote_to_project($target_id, (string) $format['slug'], $item_ids);
            }

            return iss_content_editorial_sets_promote_to_editorial_document(
                $target_id,
                (string) $format['slug'],
                $item_ids,
                (string) ($args['sectionType'] ?? '')
            );
        }
    }

    return ['promoted' => 0, 'message' => __('No promotion path exists for this target type.', 'iss-content-model')];
}
