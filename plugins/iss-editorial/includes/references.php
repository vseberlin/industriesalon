<?php

if (!defined('ABSPATH')) {
    exit;
}

/** Prime native caches for references consumed by a document's render or editor path. */
function iss_editorial_prime_document_references(array $document, bool $editor_previews = false): void
{
    $post_ids = [];
    $object_ids = [];
    $collect = static function ($references) use (&$post_ids, &$object_ids): void {
        foreach ((array) $references as $reference) {
            $reference = iss_editorial_sanitize_reference($reference);
            $id = absint($reference['id'] ?? 0);
            $media = ($reference['source'] ?? '') === 'wp-media' && ($reference['kind'] ?? '') === 'media';
            $object = ($reference['source'] ?? '') === 'iss-archive' && ($reference['kind'] ?? '') === 'archive_object';
            if ($id > 0 && ($media || $object)) {
                $post_ids[$id] = $id;
                if ($object) { $object_ids[$id] = $id; }
            }
        }
    };
    $collect($document['features']['identity']['media_refs'] ?? []);
    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (!is_array($section)) { continue; }
        $collect($section['media_refs'] ?? []);
        $collect($section['object_refs'] ?? []);
        foreach ((array) ($section['items'] ?? []) as $item) {
            if (is_array($item)) { $collect($item['media_refs'] ?? []); }
        }
        foreach ((array) ($section['sheets'] ?? []) as $sheet) {
            if (is_array($sheet)) { $collect([iss_editorial_album_sheet_reference($sheet)]); }
        }
    }
    if (!$post_ids) { return; }

    _prime_post_caches(array_values($post_ids), false, true);
    $thumbnail_ids = [];
    foreach ($object_ids as $id) {
        $post = get_post($id);
        if (!$post instanceof WP_Post || $post->post_status === 'trash') { continue; }
        $thumbnail_id = (int) get_post_thumbnail_id($post);
        if ($thumbnail_id > 0) { $thumbnail_ids[$thumbnail_id] = $thumbnail_id; }
    }
    if ($thumbnail_ids) { _prime_post_caches(array_values($thumbnail_ids), false, true); }
}

/** Album source identity uses the same media/archive resolver as ordinary references. */
function iss_editorial_album_sheet_reference(array $sheet): array
{
    $kind = $sheet['source_kind'] ?? '';
    return [
        'kind' => $kind === 'wp_media' ? 'media' : $kind,
        'source' => $kind === 'wp_media' ? 'wp-media' : ($kind === 'archive_object' ? 'iss-archive' : ''),
        'id' => (string) ($sheet['source_id'] ?? $sheet['object_id'] ?? $sheet['attachment_id'] ?? ''),
    ];
}

function iss_editorial_resolve_reference(array $reference): array
{
    $reference = iss_editorial_sanitize_reference($reference);
    if (!$reference) {
        return [];
    }

    if ($reference['source'] === 'iss-archive' && $reference['kind'] === 'archive_object') {
        $post_id = absint($reference['id']);
        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post instanceof WP_Post || $post->post_status === 'trash') {
            return [];
        }

        return [
            'kind' => 'archive_object',
            'source' => 'iss-archive',
            'id' => (string) $post_id,
            'title' => (string) get_the_title($post),
            'url' => (string) get_permalink($post),
            'thumbnail' => (string) get_the_post_thumbnail_url($post, 'medium'),
            'post' => $post,
        ];
    }

    if ($reference['source'] === 'wp-media' && $reference['kind'] === 'media') {
        $attachment_id = absint($reference['id']);
        $post = $attachment_id > 0 ? get_post($attachment_id) : null;
        if (!$post instanceof WP_Post || $post->post_type !== 'attachment') {
            return [];
        }
        $metadata = wp_get_attachment_metadata($attachment_id);
        $width = is_array($metadata) ? absint($metadata['width'] ?? 0) : 0;
        $height = is_array($metadata) ? absint($metadata['height'] ?? 0) : 0;

        return [
            'kind' => 'media',
            'source' => 'wp-media',
            'id' => (string) $attachment_id,
            'title' => (string) get_the_title($post),
            'url' => (string) wp_get_attachment_url($attachment_id),
            'thumbnail' => (string) wp_get_attachment_image_url($attachment_id, 'medium'),
            'width' => (string) $width,
            'height' => (string) $height,
            'mime' => (string) get_post_mime_type($attachment_id),
            'post' => $post,
        ];
    }

    return [];
}

/** Derived previews travel to the editor, never into canonical JSON or recovery. */
function iss_editorial_strip_document_preview_cache(array $document): array
{
    $strip = static function (&$references): void {
        if (!is_array($references)) { return; }
        foreach ($references as &$reference) {
            if (!is_array($reference)) { continue; }
            if ((($reference['source'] ?? '') === 'wp-media' && ($reference['kind'] ?? '') === 'media')
                || (($reference['source'] ?? '') === 'iss-archive' && ($reference['kind'] ?? '') === 'archive_object')) {
                unset($reference['thumbnail'], $reference['width'], $reference['height'], $reference['mime']);
            }
        }
        unset($reference);
    };
    if (isset($document['features']['identity']['media_refs'])) { $strip($document['features']['identity']['media_refs']); }
    if (!is_array($document['sections'] ?? null)) { return $document; }
    foreach ($document['sections'] as &$section) {
        if (!is_array($section)) { continue; }
        foreach (['media_refs', 'object_refs'] as $key) {
            if (isset($section[$key])) { $strip($section[$key]); }
        }
        if (is_array($section['sheets'] ?? null)) {
            foreach ($section['sheets'] as &$sheet) {
                if (is_array($sheet) && in_array($sheet['source_kind'] ?? '', ['wp_media', 'archive_object'], true)) { unset($sheet['thumbnail']); }
            }
            unset($sheet);
        }
        if (!is_array($section['items'] ?? null)) { continue; }
        foreach ($section['items'] as &$item) {
            if (is_array($item) && isset($item['media_refs'])) { $strip($item['media_refs']); }
        }
        unset($item);
    }
    unset($section);
    // Retired sections remain opaque recovery records until explicitly restored.
    return $document;
}

function iss_editorial_hydrate_reference_preview(array $reference): array
{
    if (!$reference) {
        return [];
    }

    $resolved = iss_editorial_resolve_reference($reference);
    if (!$resolved) {
        return $reference;
    }

    if (empty($reference['label']) && !empty($resolved['title'])) {
        $reference['label'] = (string) $resolved['title'];
    }
    foreach (['thumbnail', 'width', 'height', 'mime'] as $key) {
        if (array_key_exists($key, $resolved)) { $reference[$key] = (string) $resolved[$key]; }
    }

    return $reference;
}

function iss_editorial_hydrate_reference_preview_list($references): array
{
    $hydrated = [];
    foreach ((array) $references as $reference) {
        if (!is_array($reference)) {
            continue;
        }
        $reference = iss_editorial_hydrate_reference_preview($reference);
        if ($reference) {
            $hydrated[] = $reference;
        }
    }

    return $hydrated;
}

function iss_editorial_hydrate_document_previews(array $document): array
{
    iss_editorial_prime_document_references($document, true);
    if (isset($document['features']['identity']['media_refs'])) {
        $document['features']['identity']['media_refs'] = iss_editorial_hydrate_reference_preview_list($document['features']['identity']['media_refs']);
    }
    if (!is_array($document['sections'] ?? null)) {
        return $document;
    }

    // Deleted sections are opaque until an explicitly validated restore.
    foreach ($document['sections'] as &$section) {
        if (!is_array($section)) {
            continue;
        }
        if (isset($section['media_refs'])) {
            $section['media_refs'] = iss_editorial_hydrate_reference_preview_list($section['media_refs']);
        }
        if (isset($section['object_refs'])) {
            $section['object_refs'] = iss_editorial_hydrate_reference_preview_list($section['object_refs']);
        }
        if (is_array($section['sheets'] ?? null)) {
            foreach ($section['sheets'] as &$sheet) {
                if (!is_array($sheet)) { continue; }
                $resolved = iss_editorial_resolve_reference(iss_editorial_album_sheet_reference($sheet));
                if ($resolved) { $sheet['thumbnail'] = (string) ($resolved['thumbnail'] ?? ''); }
            }
            unset($sheet);
        }
        if (!is_array($section['items'] ?? null)) {
            continue;
        }
        foreach ($section['items'] as &$item) {
            if (!is_array($item) || !isset($item['media_refs'])) {
                continue;
            }
            $item['media_refs'] = iss_editorial_hydrate_reference_preview_list($item['media_refs']);
        }
        unset($item);
    }
    unset($section);

    return $document;
}
