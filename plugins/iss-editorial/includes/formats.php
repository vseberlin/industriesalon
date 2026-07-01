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

            $treatments = [];
            foreach ((array) ($section['treatments'] ?? []) as $treatment_slug => $treatment) {
                if (is_array($treatment)) {
                    $treatment_slug = iss_editorial_sanitize_treatment_slug(is_string($treatment_slug) ? $treatment_slug : (string) ($treatment['slug'] ?? ''));
                    $treatment_label = sanitize_text_field((string) ($treatment['label'] ?? $treatment_slug));
                } else {
                    $treatment_slug = iss_editorial_sanitize_treatment_slug(is_string($treatment_slug) ? $treatment_slug : (string) $treatment);
                    $treatment_label = sanitize_text_field((string) $treatment);
                }

                if ($treatment_slug === '') {
                    continue;
                }

                $treatments[$treatment_slug] = [
                    'slug' => $treatment_slug,
                    'label' => $treatment_label !== '' ? $treatment_label : $treatment_slug,
                ];
            }

            $sections[$type] = [
                'type' => $type,
                'label' => sanitize_text_field((string) ($section['label'] ?? $type)),
                'description' => sanitize_text_field((string) ($section['description'] ?? '')),
                'supports' => array_values(array_filter(array_map('sanitize_key', (array) ($section['supports'] ?? [])))),
                'treatments' => array_values($treatments),
                'ui_hidden' => !empty($section['ui_hidden']),
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
            'post_eligibility_callback' => is_callable($format['post_eligibility_callback'] ?? null) ? $format['post_eligibility_callback'] : null,
            'skin_meta_key' => sanitize_key((string) ($format['skin_meta_key'] ?? '')),
        ];
    }

    return $normalized;
}

function iss_editorial_sanitize_treatment_slug(string $slug): string
{
    $slug = strtolower($slug);

    return (string) preg_replace('/[^a-z0-9_.-]/', '', $slug);
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
        if (is_callable($format['post_eligibility_callback'] ?? null)) {
            continue;
        }
        if (in_array($post_type, (array) $format['post_types'], true)) {
            return $format;
        }
    }

    return [];
}

function iss_editorial_format_is_post_eligible(array $format, WP_Post $post): bool
{
    if (!in_array((string) $post->post_type, (array) ($format['post_types'] ?? []), true)) {
        return false;
    }

    $callback = $format['post_eligibility_callback'] ?? null;
    if (!is_callable($callback)) {
        return true;
    }

    return (bool) call_user_func($callback, $post, $format);
}

function iss_editorial_get_format_for_post($post): array
{
    if (is_numeric($post)) {
        $post = get_post((int) $post);
    }

    if (!$post instanceof WP_Post) {
        return [];
    }

    foreach (iss_editorial_get_registered_formats() as $format) {
        if (iss_editorial_format_is_post_eligible($format, $post)) {
            return $format;
        }
    }

    return [];
}

function iss_editorial_normalize_format_skins(array $skins, array $format = []): array
{
    $normalized = [];

    foreach ($skins as $slug => $skin) {
        if (is_array($skin)) {
            $slug = sanitize_key(is_string($slug) ? $slug : (string) ($skin['slug'] ?? ''));
            $label = sanitize_text_field((string) ($skin['label'] ?? $slug));
        } else {
            $slug = sanitize_key(is_string($slug) ? $slug : (string) $skin);
            $label = sanitize_text_field((string) $skin);
        }

        if ($slug === '') {
            continue;
        }

        $normalized[$slug] = [
            'slug' => $slug,
            'label' => $label !== '' ? $label : $slug,
        ];
    }

    $default_skin = sanitize_key((string) ($format['default_skin'] ?? 'standard'));
    if ($default_skin !== '' && !isset($normalized[$default_skin])) {
        $normalized[$default_skin] = [
            'slug' => $default_skin,
            'label' => ucfirst(str_replace('-', ' ', $default_skin)),
        ];
    }

    if (!isset($normalized['standard'])) {
        $normalized = array_merge(
            [
                'standard' => [
                    'slug' => 'standard',
                    'label' => __('Standard', 'iss-editorial'),
                ],
            ],
            $normalized
        );
    }

    return array_values($normalized);
}

function iss_editorial_get_format_skins(string $format_slug): array
{
    $format = iss_editorial_get_format($format_slug);
    if (!$format) {
        return [];
    }

    $skins = iss_editorial_normalize_format_skins((array) ($format['skins'] ?? []), $format);

    /**
     * Allows the presentation owner to expose the allowed assignment skins for a format.
     *
     * The editor stores the chosen skin slug in the document; renderers still own
     * all layout and visual interpretation for that slug.
     */
    $skins = apply_filters('iss_editorial_format_skins', $skins, $format_slug, $format);

    return iss_editorial_normalize_format_skins((array) $skins, $format);
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

function iss_editorial_get_preview_nonce_action(int $post_id, string $format_slug): string
{
    return 'iss_editorial_preview_' . (string) $post_id . '_' . sanitize_key($format_slug);
}

function iss_editorial_add_preview_args(string $url, int $post_id, string $format_slug): string
{
    if ($url === '' || $post_id <= 0 || $format_slug === '') {
        return $url;
    }

    $format_slug = sanitize_key($format_slug);

    return add_query_arg(
        [
            'iss_editorial_preview' => '1',
            'iss_editorial_format' => $format_slug,
            'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($post_id, $format_slug)),
        ],
        $url
    );
}

function iss_editorial_should_prefer_preview_autosave(int $post_id, string $format_slug): bool
{
    if ($post_id <= 0 || $format_slug === '' || !current_user_can('edit_post', $post_id)) {
        return false;
    }

    if (is_preview()) {
        return true;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified below; read-only preview routing.
    if ((string) ($_GET['iss_editorial_preview'] ?? '') !== '1') {
        return false;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified below; read-only preview routing.
    if (sanitize_key((string) ($_GET['iss_editorial_format'] ?? '')) !== sanitize_key($format_slug)) {
        return false;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is the nonce value being verified.
    $nonce = isset($_GET['iss_editorial_preview_nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['iss_editorial_preview_nonce'])) : '';

    return $nonce !== '' && wp_verify_nonce($nonce, iss_editorial_get_preview_nonce_action($post_id, $format_slug));
}

function iss_editorial_get_enabled_meta_key(string $format_slug): string
{
    return '_iss_editorial_enabled_' . sanitize_key($format_slug);
}

function iss_editorial_get_skin_meta_key(string $format_slug): string
{
    $format = iss_editorial_get_format($format_slug);
    $meta_key = sanitize_key((string) ($format['skin_meta_key'] ?? ''));

    return $meta_key !== '' ? $meta_key : '';
}

function iss_editorial_get_empty_document(string $format_slug): array
{
    $format = iss_editorial_get_format($format_slug);

    return [
        'schema_version' => 1,
        'skin' => (string) ($format['default_skin'] ?? 'standard'),
        'variant' => (string) ($format['default_variant'] ?? 'standard'),
        'features' => [],
        'sections' => [],
        'deleted_sections' => [],
    ];
}

function iss_editorial_format_supports_section_field(array $format, string $section_type, string $field): bool
{
    $section_type = sanitize_key($section_type);
    $field = sanitize_key($field);
    $section = is_array($format['sections'][$section_type] ?? null) ? $format['sections'][$section_type] : [];

    return in_array($field, (array) ($section['supports'] ?? []), true);
}
