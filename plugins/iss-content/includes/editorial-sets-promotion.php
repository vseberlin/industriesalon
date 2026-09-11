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
        return 'galerie';
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

/** Prepare approved material in the current author's native draft, never the public document. */
function iss_content_editorial_sets_promote(int $target_id, string $target_type, array $item_ids, array $args = []): array
{
    $failure = static function (string $message): array {
        return ['prepared' => 0, 'promoted' => 0, 'message' => $message];
    };
    $post = get_post($target_id);
    $format = $post && function_exists('iss_editorial_get_format_for_post') ? iss_editorial_get_format_for_post($post) : [];
    if (!$post || $post->post_type !== $target_type || !$format || !current_user_can('edit_post', $target_id)
        || !current_user_can('iss_promote_media')) {
        return $failure(__('Dieses Ziel kann nicht bearbeitet werden.', 'iss-content-model'));
    }
    $slug = $format['slug'];
    $check = iss_editorial_check_edit_version($target_id, $slug, (string) ($args['base'] ?? ''), (string) ($args['draftToken'] ?? ''));
    if (is_wp_error($check)) {
        return $failure($check->get_error_message());
    }
    $draft = iss_editorial_get_draft($target_id, $slug);
    if ($draft && $draft['base'] !== iss_editorial_saved_token($target_id, $slug)) {
        return $failure(__('Bitte zuerst den älteren Entwurf im Editor prüfen.', 'iss-content-model'));
    }
    if (!$draft && !iss_editorial_document_is_enabled($target_id, $slug) && $post->post_status !== 'auto-draft'
        && ($post->post_content !== '' || $post->post_status === 'publish')) {
        return $failure(__('Dieser Inhalt verwendet noch den bisherigen Editor. Bitte dessen Umstellung zuerst prüfen.', 'iss-content-model'));
    }
    $document = $draft ? $draft['document'] : iss_editorial_get_document($target_id, $slug, false);
    $service = iss_content_editorial_sets_service();
    $items = [];
    // Check the complete selection before importing any source files.
    foreach (array_unique(array_map('absint', $item_ids)) as $item_id) {
        $item = $service->get_item($item_id);
        if (!$item || !in_array($item['status'], ['approved', 'promoted'], true)) {
            return $failure(__('Bitte nur freigegebene Materialien auswählen.', 'iss-content-model'));
        }
        $rights = json_decode($item['rights_json'], true);
        if (empty($rights['attribution']) || empty($rights['license']) || (string) ($rights['consent'] ?? '') !== '1') {
            return $failure(__('Bitte zuerst die Rechteangaben im Set vervollständigen.', 'iss-content-model'));
        }
        $items[] = $item;
    }
    if (!$items) {
        return $failure(__('Bitte Material auswählen.', 'iss-content-model'));
    }
    $section_choice = sanitize_key((string) ($args['sectionType'] ?? ''));
    $prepared = [];
    foreach ($items as $item) {
        $reference = iss_content_editorial_sets_reference_from_item($item, $target_id);
        if (!$reference) {
            return $failure(__('Eine ausgewählte Datei konnte nicht übernommen werden. Der Inhalt bleibt unverändert.', 'iss-content-model'));
        }
        $reference['editorial_set_item_id'] = (string) $item['id'];
        $field = iss_content_editorial_sets_reference_field_for_item($item);
        $section_type = $section_choice ?: iss_content_editorial_sets_project_section_type_for_entry(['field' => $field, 'reference' => $reference]);
        $mime = iss_content_editorial_sets_reference_mime($reference);
        if (!isset($format['sections'][$section_type]) || !iss_editorial_format_supports_section_field($format, $section_type, $field)
            || ($section_type === 'material' && ($field === 'object_refs' || str_starts_with($mime, 'image/')))
            || ($section_type === 'galerie' && $field !== 'object_refs' && !str_starts_with($mime, 'image/') && !str_starts_with($mime, 'video/'))) {
            return $failure(__('Material und gewählter Abschnitt passen nicht zusammen. Fotos gehören in die Galerie, Dokumente zu den Dateien.', 'iss-content-model'));
        }
        $index = null;
        foreach ($document['sections'] as $key => $section) {
            if ($section['type'] === $section_type) {
                $index = $key;
                break;
            }
        }
        if ($index === null) {
            $document['sections'][] = ['type' => $section_type, 'title' => $section_type === 'galerie' ? __('Galerie', 'iss-content-model') : __('Dokumente & Downloads', 'iss-content-model'), 'body' => ''];
            $index = array_key_last($document['sections']);
        }
        $document['sections'][$index][$field] = $document['sections'][$index][$field] ?? [];
        if (!iss_content_editorial_sets_reference_exists($document['sections'][$index][$field], $reference)) {
            $document['sections'][$index][$field][] = $reference;
        }
        $prepared[] = ['item' => $item, 'reference' => $reference];
    }
    $result = iss_editorial_save_draft($target_id, $slug, $document, true, $draft ? ['title' => $draft['title'], 'excerpt' => $draft['excerpt']] : []);
    if (is_wp_error($result)) {
        return $failure($result->get_error_message());
    }
    foreach ($prepared as $entry) {
        iss_content_editorial_sets_mark_external_upload_imported($entry['item'], $entry['reference']);
        $service->attach_context((int) $entry['item']['set_id'], $target_type, $target_id, 'source_material');
        $service->record_audit((int) $entry['item']['set_id'], (int) $entry['item']['id'], 'draft_prepared', __('Material im Entwurf bereitgestellt.', 'iss-content-model'), ['target_type' => $target_type, 'target_id' => $target_id]);
    }
    return ['prepared' => count($prepared), 'promoted' => 0, 'editUrl' => get_edit_post_link($target_id, 'raw'), 'message' => __('Im Entwurf bereit. Im Editor wiederherstellen, Vorschau prüfen und speichern.', 'iss-content-model')];
}

/** Record actual uses only after the editorial engine has saved the canonical document. */
/** Recheck approval at save/preview time: a reviewer may withdraw it after draft preparation. */
function iss_content_editorial_sets_reference_is_approved(array $reference): bool
{
    $item = iss_content_editorial_sets_service()->get_item(absint($reference['editorial_set_item_id'] ?? 0));
    if (!$item || !in_array($item['status'], ['approved', 'promoted'], true)) {
        return false;
    }
    $rights = json_decode($item['rights_json'], true);
    if (empty($rights['attribution']) || empty($rights['license']) || (string) ($rights['consent'] ?? '') !== '1') {
        return false;
    }
    $source_id = $item['kind'] === 'external_upload'
        ? (json_decode($item['provenance_json'], true)['imported_attachment_id'] ?? 0) : $item['source_id'];
    $source = $item['kind'] === 'external_upload' ? 'wp-media' : $item['source'];
    return (string) $source_id === (string) ($reference['id'] ?? '') && $source === ($reference['source'] ?? '');
}

add_filter('iss_editorial_validated_document', static function ($validated) {
    if (is_wp_error($validated)) {
        return $validated;
    }
    $valid = true;
    $visit = static function (array $node) use (&$visit, &$valid): void {
        if (!empty($node['editorial_set_item_id']) && !iss_content_editorial_sets_reference_is_approved($node)) {
            $valid = false;
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $visit($value);
            }
        }
    };
    $visit((array) ($validated['sections'] ?? []));
    return $valid ? $validated : new WP_Error('iss_content_set_approval_changed', __('Ein Material wurde nicht freigegeben oder seine Freigabe wurde zurückgezogen. Bitte im Set prüfen oder aus dem Inhalt entfernen.', 'iss-content-model'));
}, 20);

function iss_content_editorial_sets_record_document_uses(int $post_id, string $format, array $document): void
{
    unset($format);
    $service = iss_content_editorial_sets_service();
    $visit = static function (array $node) use (&$visit, $service, $post_id): void {
        $item_id = absint($node['editorial_set_item_id'] ?? 0);
        if ($item_id && iss_content_editorial_sets_reference_is_approved($node)) {
            $service->mark_promoted($item_id, (string) get_post_type($post_id), $post_id);
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $visit($value);
            }
        }
    };
    $visit((array) ($document['sections'] ?? []));
}
add_action('iss_editorial_document_saved', 'iss_content_editorial_sets_record_document_uses', 30, 3);
