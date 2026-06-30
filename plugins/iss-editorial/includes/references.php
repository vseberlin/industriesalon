<?php

if (!defined('ABSPATH')) {
    exit;
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
            'post' => $post,
        ];
    }

    return [];
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
    if (empty($reference['thumbnail']) && !empty($resolved['thumbnail'])) {
        $reference['thumbnail'] = (string) $resolved['thumbnail'];
    }
    if (empty($reference['width']) && !empty($resolved['width'])) {
        $reference['width'] = (string) $resolved['width'];
    }
    if (empty($reference['height']) && !empty($resolved['height'])) {
        $reference['height'] = (string) $resolved['height'];
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
    if (!is_array($document['sections'] ?? null)) {
        return $document;
    }

    foreach ($document['sections'] as &$section) {
        if (!is_array($section)) {
            continue;
        }
        if (isset($section['media_refs'])) {
            $section['media_refs'] = iss_editorial_hydrate_reference_preview_list($section['media_refs']);
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
