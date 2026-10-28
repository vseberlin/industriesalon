<?php

if (!defined('ABSPATH')) {
    exit;
}
/** Invalidate request-local definitions after changing registry filters at runtime. */
function iss_editorial_reset_format_cache(): void
{
    unset($GLOBALS['iss_editorial_format_cache']);
}

function iss_editorial_get_registered_formats(): array
{
    // init counts as fired while its callbacks still register post types and formats.
    $cacheable = did_action('init') && !doing_action('init');
    $context = [get_current_blog_id(), get_locale()];
    $cache = $GLOBALS['iss_editorial_format_cache'] ?? null;
    if ($cacheable && $cache !== null && $cache['context'] === $context) {
        return $cache['formats'];
    }

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
            $registered_treatments = (array) ($section['treatments'] ?? []);
            foreach ((array) ($section['slots'] ?? []) as $slot) {
                $registered_treatments[$slot['treatment']] = $registered_treatments[$slot['treatment']] ?? $slot['label'];
            }
            foreach ($registered_treatments as $treatment_slug => $treatment) {
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
                    'schematic' => is_array($treatment) ? sanitize_key($treatment['schematic'] ?? '') : '',
                    'hint' => is_array($treatment) ? sanitize_text_field($treatment['hint'] ?? '') : '',
                    'role' => is_array($treatment) ? sanitize_key($treatment['role'] ?? '') : '',
                    'min_version' => is_array($treatment) ? absint($treatment['min_version'] ?? 1) : 1,
                    'item_fields' => is_array($treatment) ? array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($treatment['item_fields'] ?? []))))) : [],
                    'legacy_value' => is_array($treatment) ? sanitize_key((string) ($treatment['legacy_value'] ?? '')) : '',
                ];
            }

            $media_layout = (array) ($section['media_layout'] ?? []);
            $media_layout_choices = [];
            foreach ((array) ($media_layout['choices'] ?? []) as $value => $label) {
                $value = sanitize_key((string) $value);
                if ($value === '') { continue; }
                $media_layout_choices[$value] = ['value' => $value, 'label' => sanitize_text_field((string) $label)];
            }

            $vocabularies = [];
            foreach ((array) ($section['vocabularies'] ?? []) as $field => $vocabulary) {
                $choices = [];
                foreach ((array) ($vocabulary['choices'] ?? []) as $value => $label) {
                    $value = sanitize_key((string) $value);
                    if ($value === '') { continue; }
                    $choices[$value] = ['value' => $value, 'label' => sanitize_text_field((string) $label)];
                }
                $vocabularies[sanitize_key((string) $field)] = [
                    'label' => sanitize_text_field((string) ($vocabulary['label'] ?? '')),
                    'choices' => array_values($choices),
                ];
            }

            $sections[$type] = [
                'type' => $type,
                'label' => sanitize_text_field((string) ($section['label'] ?? $type)),
                'icon' => sanitize_key($section['icon'] ?? 'editor-paragraph'),
                'tone' => in_array($section['tone'] ?? '', ['text', 'media', 'navigation', 'automatic'], true) ? $section['tone'] : 'text',
                'group' => sanitize_text_field($section['group'] ?? 'Text'),
                'description' => sanitize_text_field((string) ($section['description'] ?? '')),
                'help' => array_values(array_filter(array_map('sanitize_text_field', (array) ($section['help'] ?? [])))),
                'supports' => array_values(array_filter(array_map('sanitize_key', (array) ($section['supports'] ?? [])))),
                'treatments' => array_values($treatments),
                'ui_hidden' => !empty($section['ui_hidden']),
                // An insertion limit, not a reason to discard previously saved sections.
                'max_instances' => absint($section['max_instances'] ?? 0),
                'slots' => (array) ($section['slots'] ?? []),
                'rich_text' => array_intersect((array) ($section['rich_text'] ?? []), ['block', 'inline', 'inline-card']),
                'legacy_body_policy' => sanitize_key((string) ($section['legacy_body_policy'] ?? 'post')),
                'items_kind' => in_array($section['items_kind'] ?? '', ['text', 'cards', 'text_image'], true) ? $section['items_kind'] : 'cards',
                'items_label' => sanitize_text_field((string) ($section['items_label'] ?? __('Ziele', 'iss-editorial'))),
                'vocabularies' => $vocabularies,
                'media_layout' => [
                    'choices' => array_values($media_layout_choices),
                    'default' => sanitize_key((string) ($media_layout['default'] ?? '')),
                    'treatments' => array_values(array_unique(array_filter(array_map('iss_editorial_sanitize_treatment_slug', (array) ($media_layout['treatments'] ?? []))))),
                ],
                'editor' => (array) ($section['editor'] ?? []),
                'family' => sanitize_key($section['family'] ?? $type),
                'portable' => !empty($section['portable']),
                'renderer' => sanitize_key($section['renderer'] ?? ''),
                'schematic' => sanitize_key($section['schematic'] ?? 'text'),
                'default_treatment' => iss_editorial_sanitize_treatment_slug($section['default_treatment'] ?? (array_key_first($treatments) ?? '')),
                'treatment_aliases' => (array) ($section['treatment_aliases'] ?? []),
                'legacy_treatment_field' => sanitize_key((string) ($section['legacy_treatment_field'] ?? '')),
                'treatment_contexts' => (array) ($section['treatment_contexts'] ?? []),
                'legacy_types' => (array) ($section['legacy_types'] ?? []),
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
            'route_stations_callback' => is_callable($format['route_stations_callback'] ?? null) ? $format['route_stations_callback'] : null,
            'opening_skins' => array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($format['opening_skins'] ?? []))))),
            'opening_eligibility_callback' => is_callable($format['opening_eligibility_callback'] ?? null) ? $format['opening_eligibility_callback'] : null,
            'skin_meta_key' => sanitize_key((string) ($format['skin_meta_key'] ?? '')),
            'storage_meta_key' => sanitize_key((string) ($format['storage_meta_key'] ?? '')),
            'version_meta_keys' => array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($format['version_meta_keys'] ?? []))))),
            'supported_versions' => array_values(array_unique(array_map('absint', (array) ($format['supported_versions'] ?? [1])))),
            'always_enabled' => !empty($format['always_enabled']),
            'default_version' => absint($format['default_version'] ?? 1),
            'editor' => array_merge(['workspace' => false, 'preview' => false, 'canvas' => false], (array) ($format['editor'] ?? [])),
            'starters' => (array) ($format['starters'] ?? []),
            'skins' => (array) ($format['skins'] ?? []),
            'renderer' => sanitize_key($format['renderer'] ?? ''),
        ];
    }

    if ($cacheable) {
        $GLOBALS['iss_editorial_format_cache'] = ['context' => $context, 'formats' => $normalized];
    }

    return $normalized;
}

/** Resolve declared aliases/defaults; validation separately rejects unknown input. */
function iss_editorial_resolve_treatment(string $value, array $section): string
{
    $value = iss_editorial_sanitize_treatment_slug($value);
    $value = (string) ($section['treatment_aliases'][$value] ?? $value);
    $allowed = array_column((array) ($section['treatments'] ?? []), 'slug');
    return in_array($value, $allowed, true) ? $value : (string) ($section['default_treatment'] ?? '');
}

/** Context limits new choices; retained authored treatments remain readable. */
function iss_editorial_treatment_choices(array $definition, string $skin = ''): array
{
    $choices = (array) ($definition['treatments'] ?? []);
    $allowed = $definition['treatment_contexts'][$skin]['allowed'] ?? null;
    return is_array($allowed) ? array_values(array_filter($choices, static fn(array $choice): bool => in_array($choice['slug'], $allowed, true))) : $choices;
}

/** Resolve presentation without adding defaults or replacing choices in stored JSON. */
function iss_editorial_resolve_section_treatment(array $section, array $definition, string $skin = ''): string
{
    $context = (array) ($definition['treatment_contexts'][$skin] ?? []);
    $default = (string) ($context['default'] ?? $definition['default_treatment'] ?? '');
    $choices = array_column((array) ($definition['treatments'] ?? []), null, 'slug');
    $explicit = (string) ($section['treatment'] ?? '');
    if ($explicit !== '') {
        $explicit = (string) ($definition['treatment_aliases'][$explicit] ?? $explicit);
        return isset($choices[$explicit]) ? $explicit : $default;
    }
    $field = (string) ($definition['legacy_treatment_field'] ?? '');
    if ($field !== '' && isset($section[$field])) {
        $legacy = (string) $section[$field];
        if (isset($context['legacy'][$legacy])) { return (string) $context['legacy'][$legacy]; }
        foreach ($choices as $choice) {
            if (($choice['legacy_value'] ?? '') === $legacy) { return $choice['slug']; }
        }
    }
    return $default;
}

/** A transient renderer view; legacy helpers consume the same effective choice. */
function iss_editorial_section_presentation(array $section, array $definition, string $skin): array
{
    $section['_resolved_treatment'] = iss_editorial_resolve_section_treatment($section, $definition, $skin);
    $field = (string) ($definition['legacy_treatment_field'] ?? '');
    foreach ((array) ($definition['treatments'] ?? []) as $choice) {
        if ($field !== '' && $choice['slug'] === $section['_resolved_treatment'] && ($choice['legacy_value'] ?? '') !== '') {
            $section[$field] = $choice['legacy_value'];
            break;
        }
    }
    return $section;
}

/** Read-only diagnostics for the single effective contract supplied by its owners. */
function iss_editorial_registry_errors(): array
{
    $errors = [];
    $raw = (array) apply_filters('iss_editorial_formats', []);
    foreach (iss_editorial_get_registered_formats() as $slug => $format) {
        if (!in_array($format['default_version'], $format['supported_versions'], true)) {
            $errors[] = "$slug: unsupported default document version";
        }
        if (!in_array($format['default_skin'], array_column(iss_editorial_get_format_skins($slug), 'slug'), true)) {
            $errors[] = "$slug: missing default skin";
        }
        if (array_diff($format['opening_skins'], array_column(iss_editorial_get_format_skins($slug), 'slug'))) {
            $errors[] = "$slug: unknown opening skin";
        }
        if (isset($raw[$slug]['opening_eligibility_callback']) && !is_callable($raw[$slug]['opening_eligibility_callback'])) {
            $errors[] = "$slug: invalid opening eligibility callback";
        }
        $aliases = [];
        foreach ($format['sections'] as $type => $section) {
            if (!in_array($section['legacy_body_policy'], ['post', 'basic'], true)) {
                $errors[] = "$slug.$type: unknown legacy body policy";
            }
            if (in_array('items', $section['supports'], true) && isset($raw[$slug]['sections'][$type]['items_kind'])
                && !in_array($raw[$slug]['sections'][$type]['items_kind'], ['text', 'cards', 'text_image'], true)) {
                $errors[] = "$slug.$type: unknown item kind";
            }
            if ($section['portable'] && $section['renderer'] === '') {
                $errors[] = "$slug.$type: portable gesture has no renderer";
            }
            foreach (['label', 'description', 'family', 'icon', 'tone', 'group', 'schematic'] as $field) {
                if (empty($raw[$slug]['sections'][$type][$field])) { $errors[] = "$slug.$type: missing $field"; }
            }
            $treatments = array_column($section['treatments'], 'slug');
            $legacy_field = $section['legacy_treatment_field'];
            if ($legacy_field !== '' && (!in_array($legacy_field, $section['supports'], true) || !in_array('treatment', $section['supports'], true))) {
                $errors[] = "$slug.$type: legacy treatment field must be supported alongside treatment";
            }
            foreach ($section['treatment_contexts'] as $skin => $context) {
                $allowed = (array) ($context['allowed'] ?? $treatments);
                if (!isset($format['skins'][$skin]) || !$allowed || array_diff($allowed, $treatments)
                    || !in_array($context['default'] ?? $section['default_treatment'], $allowed, true)
                    || array_diff((array) ($context['legacy'] ?? []), $allowed)) {
                    $errors[] = "$slug.$type: invalid treatment context $skin";
                }
            }
            foreach (['era_key', 'function_key'] as $field) {
                if (!in_array($field, $section['supports'], true)) { continue; }
                $vocabulary = $section['vocabularies'][$field] ?? [];
                if (empty($vocabulary['label']) || empty($vocabulary['choices'])) {
                    $errors[] = "$slug.$type: missing $field vocabulary label or choices";
                } elseif (in_array('', array_column($vocabulary['choices'], 'label'), true)) {
                    $errors[] = "$slug.$type: $field vocabulary choice lacks a label";
                }
            }
            if (in_array('media_layout', $section['supports'], true)) {
                $layout = $section['media_layout'];
                if (!$layout['choices'] || !in_array($layout['default'], array_column($layout['choices'], 'value'), true)) {
                    $errors[] = "$slug.$type: missing media layout choices or invalid default";
                }
                if (array_diff($layout['treatments'], $treatments)) {
                    $errors[] = "$slug.$type: unknown media layout treatment";
                }
                if (in_array('', array_column($layout['choices'], 'label'), true)) {
                    $errors[] = "$slug.$type: media layout choice lacks a label";
                }
            }
            if ($treatments && !in_array($section['default_treatment'], $treatments, true)) {
                $errors[] = "$slug.$type: unknown default treatment";
            }
            foreach ($section['treatments'] as $treatment) {
                if (array_diff($treatment['item_fields'], ['eyebrow', 'group', 'detail'])) {
                    $errors[] = "$slug.$type: treatment {$treatment['slug']} has unknown item fields";
                }
                if ($treatment['item_fields'] && ($section['items_kind'] !== 'text_image' || !in_array('items', $section['supports'], true))) {
                    $errors[] = "$slug.$type: treatment {$treatment['slug']} item fields require text-image items";
                }
                if ($treatment['label'] === '' || $treatment['schematic'] === '') {
                    $errors[] = "$slug.$type: treatment {$treatment['slug']} lacks presentation metadata";
                }
            }
            foreach ($section['treatment_aliases'] as $alias => $target) {
                if (in_array($alias, $treatments, true) || !in_array($target, $treatments, true)) {
                    $errors[] = "$slug.$type: invalid treatment alias $alias";
                }
            }
            foreach ($section['legacy_types'] as $alias => $defaults) {
                if (isset($format['sections'][$alias]) || isset($aliases[$alias]) || !is_array($defaults)) {
                    $errors[] = "$slug: ambiguous section alias $alias";
                }
                $aliases[$alias] = $type;
            }
        }
        foreach ($format['starters'] as $starter) {
            foreach ($starter['sections'] ?? [] as $section) {
                if (!isset($format['sections'][$section['type'] ?? ''])) { $errors[] = "$slug: unknown starter section"; }
            }
        }
    }
    return $errors;
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

/** Resolve the editor capability per post; cached definitions never freeze eligibility. */
function iss_editorial_get_opening_skins(array $format, WP_Post $post): array
{
    $skins = (array) ($format['opening_skins'] ?? []);
    $callback = $format['opening_eligibility_callback'] ?? null;
    if ($skins && is_callable($callback) && !call_user_func($callback, $post, $format)) {
        return [];
    }

    return $skins;
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
            'features' => is_array($skin) ? (array) ($skin['features'] ?? []) : [],
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

function iss_editorial_get_document_meta_key(string $format_slug): string
{
    $format = iss_editorial_get_format($format_slug);
    return (string) ($format['storage_meta_key'] ?? '') ?: '_iss_editorial_' . sanitize_key($format_slug);
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

    if (is_preview() && get_queried_object_id() === $post_id) {
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
        'schema_version' => (int) ($format['default_version'] ?? 1),
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
