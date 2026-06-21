<?php

if (!defined('ABSPATH')) {
    exit;
}
function iss_editorial_get_registered_formats(): array
{
    $formats = (array) apply_filters('iss_editorial_formats', []);
    $normalized = [];

    foreach ($formats as $slug => $format) {
        if (!is_array($format)) {
            continue;
        }

        $slug = sanitize_key(is_string($slug) ? $slug : (string) ($format['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }

        $sections = [];
        foreach ((array) ($format['sections'] ?? []) as $type => $section) {
            if (!is_array($section)) {
                continue;
            }

            $type = sanitize_key(is_string($type) ? $type : (string) ($section['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $sections[$type] = [
                'type' => $type,
                'label' => sanitize_text_field((string) ($section['label'] ?? $type)),
                'description' => sanitize_text_field((string) ($section['description'] ?? '')),
                'supports' => array_values(array_filter(array_map('sanitize_key', (array) ($section['supports'] ?? [])))),
            ];
        }

        $post_types = array_values(array_filter(array_map('sanitize_key', (array) ($format['post_types'] ?? [])), 'post_type_exists'));
        if (!$post_types || !$sections) {
            continue;
        }

        $normalized[$slug] = [
            'slug' => $slug,
            'label' => sanitize_text_field((string) ($format['label'] ?? $slug)),
            'base' => sanitize_key((string) ($format['base'] ?? 'ordered')),
            'post_types' => $post_types,
            'sections' => $sections,
            'default_skin' => sanitize_key((string) ($format['default_skin'] ?? 'standard')),
            'default_variant' => sanitize_key((string) ($format['default_variant'] ?? 'standard')),
        ];
    }

    return $normalized;
}

function iss_editorial_get_format(string $format_slug): array
{
    $format_slug = sanitize_key($format_slug);
    $formats = iss_editorial_get_registered_formats();

    return $formats[$format_slug] ?? [];
}

function iss_editorial_get_format_for_post_type(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    foreach (iss_editorial_get_registered_formats() as $format) {
        if (in_array($post_type, (array) $format['post_types'], true)) {
            return $format;
        }
    }

    return [];
}

function iss_editorial_post_type_supports_format(string $post_type, string $format_slug): bool
{
    $format = iss_editorial_get_format($format_slug);

    return $format && in_array(sanitize_key($post_type), (array) $format['post_types'], true);
}

function iss_editorial_get_document_meta_key(string $format_slug): string
{
    return '_iss_editorial_' . sanitize_key($format_slug);
}

function iss_editorial_get_autosave_meta_key(string $format_slug): string
{
    return iss_editorial_get_document_meta_key($format_slug) . '_autosave';
}

function iss_editorial_get_enabled_meta_key(string $format_slug): string
{
    return '_iss_editorial_enabled_' . sanitize_key($format_slug);
}

function iss_editorial_get_empty_document(string $format_slug): array
{
    $format = iss_editorial_get_format($format_slug);

    return [
        'schema_version' => 1,
        'skin' => (string) ($format['default_skin'] ?? 'standard'),
        'variant' => (string) ($format['default_variant'] ?? 'standard'),
        'sections' => [],
    ];
}

function iss_editorial_format_supports_section_field(array $format, string $section_type, string $field): bool
{
    $section_type = sanitize_key($section_type);
    $field = sanitize_key($field);
    $section = is_array($format['sections'][$section_type] ?? null) ? $format['sections'][$section_type] : [];

    return in_array($field, (array) ($section['supports'] ?? []), true);
}
