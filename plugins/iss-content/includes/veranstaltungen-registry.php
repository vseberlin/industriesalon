<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_veranstaltung_registry(): array
{
    $moment_fields = [
        ['name' => 'datetime_start', 'type' => 'datetime', 'required' => true],
        ['name' => 'datetime_end', 'type' => 'datetime', 'required' => false],
        ['name' => 'ort', 'type' => 'string', 'required' => false],
        ['name' => 'referent', 'type' => 'string', 'required' => false],
        ['name' => 'anmeldung', 'type' => 'url', 'required' => false],
    ];
    $span_fields = [
        ['name' => 'datetime_start', 'type' => 'datetime', 'required' => true],
        ['name' => 'datetime_end', 'type' => 'datetime', 'required' => true],
        ['name' => 'ort', 'type' => 'string', 'required' => false],
    ];
    $series_fields = [
        ['name' => 'datetime_start', 'type' => 'datetime', 'required' => true],
        ['name' => 'rhythmus', 'type' => 'string', 'required' => false],
    ];

    return [
        'schema_version' => 1,
        'shapes' => [
            'moment' => [
                'label' => __('Veranstaltung', 'iss-content-model'),
                'expires' => true,
                'primary_surface' => 'timeline',
                'required_facts' => ['datetime_start'],
            ],
            'span' => [
                'label' => __('Programm / Fest', 'iss-content-model'),
                'expires' => true,
                'primary_surface' => 'timeline',
                'required_facts' => ['datetime_start', 'datetime_end'],
            ],
            'manual_recurring' => [
                'label' => __('Serientermin', 'iss-content-model'),
                'expires' => false,
                'primary_surface' => 'timeline',
                'required_facts' => ['datetime_start'],
            ],
        ],
        'entities' => [
            'event.general' => [
                'label' => __('Veranstaltung', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'calendar',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'leitfrage', 'zitat', 'material', 'upload_intake', 'galerie', 'schluss'],
                'fields' => $moment_fields,
            ],
            'event.festival' => [
                'label' => __('Programm / Fest', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'span',
                'icon' => 'calendar-star',
                'default_skin' => 'buehne',
                'allowed_gestures' => ['intro', 'programm', 'kapitel', 'material', 'upload_intake', 'galerie', 'schluss'],
                'fields' => $span_fields,
            ],
            'event.series' => [
                'label' => __('Serientermin', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'manual_recurring',
                'icon' => 'calendar',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'material', 'upload_intake', 'galerie', 'schluss'],
                'fields' => $series_fields,
            ],
        ],
    ];
}

function iss_content_model_veranstaltung_entities(): array
{
    $registry = iss_content_model_veranstaltung_registry();

    return (array) ($registry['entities'] ?? []);
}

function iss_content_model_veranstaltung_entity(string $entity_key): array
{
    $entities = iss_content_model_veranstaltung_entities();

    return is_array($entities[$entity_key] ?? null) ? $entities[$entity_key] : [];
}

function iss_content_model_veranstaltung_entity_label(string $entity_key): string
{
    $entity = iss_content_model_veranstaltung_entity($entity_key);

    return trim((string) ($entity['label'] ?? ''));
}

function iss_content_model_veranstaltung_entity_shape(string $entity_key): string
{
    $entity = iss_content_model_veranstaltung_entity($entity_key);

    return sanitize_key((string) ($entity['shape'] ?? ''));
}

function iss_content_model_veranstaltung_entity_primary_surface(string $entity_key): string
{
    $registry = iss_content_model_veranstaltung_registry();
    $shape = iss_content_model_veranstaltung_entity_shape($entity_key);
    $shape_config = is_array($registry['shapes'][$shape] ?? null) ? $registry['shapes'][$shape] : [];

    return sanitize_key((string) ($shape_config['primary_surface'] ?? ''));
}

function iss_content_model_veranstaltung_entity_default_skin(string $entity_key): string
{
    $entity = iss_content_model_veranstaltung_entity($entity_key);

    return sanitize_key((string) ($entity['default_skin'] ?? ''));
}

function iss_content_model_veranstaltung_entity_icon(string $entity_key): string
{
    $entity = iss_content_model_veranstaltung_entity($entity_key);

    return sanitize_key((string) ($entity['icon'] ?? ''));
}

function iss_content_model_veranstaltung_entity_fields(string $entity_key): array
{
    $entity = iss_content_model_veranstaltung_entity($entity_key);
    $fields = [];

    foreach ((array) ($entity['fields'] ?? []) as $field) {
        if (!is_array($field)) {
            continue;
        }

        $name = sanitize_key((string) ($field['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $fields[$name] = [
            'name' => $name,
            'type' => sanitize_key((string) ($field['type'] ?? '')),
            'required' => !empty($field['required']),
            'target' => sanitize_key((string) ($field['target'] ?? '')),
        ];
    }

    return $fields;
}

function iss_content_model_veranstaltung_entity_field_names(string $entity_key): array
{
    return array_keys(iss_content_model_veranstaltung_entity_fields($entity_key));
}

function iss_content_model_veranstaltung_legacy_entity_key_map(): array
{
    return [
        'event.vortrag' => 'event.general',
        'event.gespraech' => 'event.general',
        'event.lesung' => 'event.general',
        'event.praesentation' => 'event.general',
        'event.workshop' => 'event.general',
        'event.konzert' => 'event.general',
        'event.school_program' => 'event.general',
        'event.repair_cafe' => 'event.series',
    ];
}

function iss_content_model_veranstaltung_entity_storage_keys_for_query(array $entity_keys): array
{
    $entity_keys = array_values(array_filter(array_map('iss_content_model_sanitize_veranstaltung_entity_key', $entity_keys)));
    if (!$entity_keys) {
        return [];
    }

    $storage_keys = $entity_keys;
    $lookup = array_fill_keys($entity_keys, true);
    foreach (iss_content_model_veranstaltung_legacy_entity_key_map() as $legacy_key => $current_key) {
        if (isset($lookup[$current_key])) {
            $storage_keys[] = $legacy_key;
        }
    }

    return array_values(array_unique($storage_keys));
}

function iss_content_model_veranstaltung_semantic_options(): array
{
    return [
        '' => [
            'label' => __('Keine Art gesetzt', 'iss-content-model'),
            'description' => __('Optionaler semantischer Filter fuer Suche und Listen.', 'iss-content-model'),
        ],
        'vortrag' => ['label' => __('Vortrag', 'iss-content-model')],
        'gespraech' => ['label' => __('Gespraech', 'iss-content-model')],
        'lesung' => ['label' => __('Lesung', 'iss-content-model')],
        'praesentation' => ['label' => __('Praesentation', 'iss-content-model')],
        'workshop' => ['label' => __('Workshop', 'iss-content-model')],
        'konzert' => ['label' => __('Konzert', 'iss-content-model')],
        'film' => ['label' => __('Film', 'iss-content-model')],
        'repair-cafe' => ['label' => __('Repair Cafe', 'iss-content-model')],
    ];
}

function iss_content_model_sanitize_veranstaltung_semantic_key($value): string
{
    $value = sanitize_title((string) $value);
    $options = iss_content_model_veranstaltung_semantic_options();

    return isset($options[$value]) && $value !== '' ? $value : '';
}

function iss_content_model_veranstaltung_semantic_label(string $semantic_key): string
{
    $semantic_key = iss_content_model_sanitize_veranstaltung_semantic_key($semantic_key);
    if ($semantic_key === '') {
        return '';
    }

    $options = iss_content_model_veranstaltung_semantic_options();

    return trim((string) ($options[$semantic_key]['label'] ?? ''));
}

function iss_content_model_veranstaltung_semantic_from_legacy_entity_key(string $entity_key): string
{
    $entity_key = sanitize_key(str_replace('.', '-', $entity_key));
    $entity_key = str_replace('-', '.', $entity_key);
    $map = [
        'event.vortrag' => 'vortrag',
        'event.gespraech' => 'gespraech',
        'event.lesung' => 'lesung',
        'event.praesentation' => 'praesentation',
        'event.workshop' => 'workshop',
        'event.konzert' => 'konzert',
        'event.repair_cafe' => 'repair-cafe',
    ];

    return iss_content_model_sanitize_veranstaltung_semantic_key((string) ($map[$entity_key] ?? ''));
}

function iss_content_model_veranstaltung_entity_options(): array
{
    $options = [
        '' => [
            'label' => __('Noch nicht festgelegt', 'iss-content-model'),
            'description' => __('Bitte eine Struktur setzen.', 'iss-content-model'),
        ],
    ];
    $entity_descriptions = [
        'event.general' => __('Einzelne Veranstaltung mit Beginn, Ort und redaktioneller Seite.', 'iss-content-model'),
        'event.festival' => __('Programm oder Fest mit Anfang, Ende und Programminhalt.', 'iss-content-model'),
        'event.series' => __('Nicht ablaufender Serientermin, z.B. woechentliches Repair Cafe.', 'iss-content-model'),
    ];

    foreach (iss_content_model_veranstaltung_entities() as $entity_key => $entity) {
        $options[(string) $entity_key] = [
            'label' => (string) ($entity['label'] ?? $entity_key),
            'description' => (string) ($entity_descriptions[(string) $entity_key] ?? ''),
        ];
    }

    return $options;
}

function iss_content_model_veranstaltung_entity_options_for_editor(string $current_entity_key = ''): array
{
    $current_entity_key = iss_content_model_sanitize_veranstaltung_entity_key($current_entity_key);
    $options = iss_content_model_veranstaltung_entity_options();

    if ($current_entity_key !== 'event.series') {
        unset($options['event.series']);
    }

    return $options;
}

function iss_content_model_veranstaltung_entity_key_is_valid(string $entity_key): bool
{
    return iss_content_model_veranstaltung_entity($entity_key) !== [];
}

function iss_content_model_sanitize_veranstaltung_entity_key($value): string
{
    $value = sanitize_key(str_replace('.', '-', (string) $value));
    $value = str_replace('-', '.', $value);
    if (iss_content_model_veranstaltung_entity_key_is_valid($value)) {
        return $value;
    }

    $legacy_map = iss_content_model_veranstaltung_legacy_entity_key_map();

    return isset($legacy_map[$value]) ? $legacy_map[$value] : '';
}

function iss_content_model_register_veranstaltung_entity_meta(): void
{
    register_post_meta(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, '_iss_entity_key', [
        'single' => true,
        'type' => 'string',
        'default' => '',
        'show_in_rest' => true,
        'sanitize_callback' => 'iss_content_model_sanitize_veranstaltung_entity_key',
        'auth_callback' => static function () {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('init', 'iss_content_model_register_veranstaltung_entity_meta', 25);

function iss_content_model_maybe_migrate_veranstaltung_semantic_terms(): void
{
    $migration_version = '2026-06-28-veranstaltung-semantic-v1';
    if ((string) get_option('iss_content_model_veranstaltung_semantic_migration_version', '') === $migration_version) {
        return;
    }
    if (!taxonomy_exists(ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY)) {
        return;
    }

    $legacy_keys = array_keys(array_filter(iss_content_model_veranstaltung_legacy_entity_key_map(), static function (string $current_key): bool {
        return $current_key !== '';
    }));
    if (!$legacy_keys) {
        update_option('iss_content_model_veranstaltung_semantic_migration_version', $migration_version, false);
        return;
    }

    $posts = function_exists('iss_content_model_veranstaltungen_maintenance_ids')
        ? iss_content_model_veranstaltungen_maintenance_ids([
        'post_status' => 'any',
        'posts_per_page' => -1,
        'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-time compatibility migration from the old semantic entity contract.
            [
                'key' => '_iss_entity_key',
                'value' => $legacy_keys,
                'compare' => 'IN',
            ],
        ],
    ])
        : [];

    foreach ($posts as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            continue;
        }

        $existing = wp_get_post_terms($post_id, ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY, ['fields' => 'slugs']);
        if (is_array($existing) && $existing !== []) {
            continue;
        }

        $semantic_key = iss_content_model_veranstaltung_semantic_from_legacy_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true));
        if ($semantic_key === '') {
            continue;
        }

        wp_set_object_terms($post_id, [$semantic_key], ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY, false);
    }

    update_option('iss_content_model_veranstaltung_semantic_migration_version', $migration_version, false);
}
add_action('init', 'iss_content_model_maybe_migrate_veranstaltung_semantic_terms', 45);

function iss_content_model_validate_veranstaltung_registry(): array
{
    $registry = iss_content_model_veranstaltung_registry();
    $errors = [];
    $shapes = (array) ($registry['shapes'] ?? []);
    $entities = (array) ($registry['entities'] ?? []);
    $allowed_field_types = ['datetime', 'date', 'string', 'text', 'url', 'relation'];

    if ((int) ($registry['schema_version'] ?? 0) < 1) {
        $errors[] = 'Registry schema_version must be >= 1.';
    }

    foreach ($entities as $entity_key => $entity) {
        if (!is_array($entity)) {
            $errors[] = sprintf('Entity %s must be an object.', (string) $entity_key);
            continue;
        }

        $shape = (string) ($entity['shape'] ?? '');
        if ($shape === '' || !isset($shapes[$shape])) {
            $errors[] = sprintf('Entity %s references unknown shape %s.', (string) $entity_key, $shape);
        }

        if (empty($entity['default_skin'])) {
            $errors[] = sprintf('Entity %s has no default_skin.', (string) $entity_key);
        }

        if (sanitize_key((string) ($entity['domain'] ?? '')) !== 'veranstaltung') {
            $errors[] = sprintf('Entity %s must declare domain veranstaltung.', (string) $entity_key);
        }

        if (sanitize_key((string) ($entity['post_type'] ?? '')) !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
            $errors[] = sprintf('Entity %s must declare post_type veranstaltung.', (string) $entity_key);
        }

        if (sanitize_key((string) ($entity['icon'] ?? '')) === '') {
            $errors[] = sprintf('Entity %s has no icon.', (string) $entity_key);
        }

        $fields = iss_content_model_veranstaltung_entity_fields((string) $entity_key);
        if (!$fields) {
            $errors[] = sprintf('Entity %s has no fields.', (string) $entity_key);
        }

        foreach ($fields as $field) {
            $field_name = (string) ($field['name'] ?? '');
            $field_type = (string) ($field['type'] ?? '');
            if ($field_type === '' || !in_array($field_type, $allowed_field_types, true)) {
                $errors[] = sprintf('Entity %s field %s has unsupported type %s.', (string) $entity_key, $field_name, $field_type);
            }
            if ($field_type === 'relation' && (string) ($field['target'] ?? '') !== 'veranstaltung') {
                $errors[] = sprintf('Entity %s relation field %s must target veranstaltung.', (string) $entity_key, $field_name);
            }
        }

        $shape_config = is_array($shapes[$shape] ?? null) ? $shapes[$shape] : [];
        foreach ((array) ($shape_config['required_facts'] ?? []) as $required_fact) {
            $required_fact = sanitize_key((string) $required_fact);
            if ($required_fact !== '' && !isset($fields[$required_fact])) {
                $errors[] = sprintf('Entity %s required fact %s is not declared as a field.', (string) $entity_key, $required_fact);
                continue;
            }
            if ($required_fact !== '' && empty($fields[$required_fact]['required'])) {
                $errors[] = sprintf('Entity %s required fact %s is not marked required.', (string) $entity_key, $required_fact);
            }
        }
    }

    return $errors;
}

function iss_content_model_veranstaltung_required_facts_for_entity(string $entity_key): array
{
    $registry = iss_content_model_veranstaltung_registry();
    $entity = iss_content_model_veranstaltung_entity($entity_key);
    $shape = (string) ($entity['shape'] ?? '');
    $shape_config = is_array($registry['shapes'][$shape] ?? null) ? $registry['shapes'][$shape] : [];

    return array_values(array_filter(array_map('sanitize_key', (array) ($shape_config['required_facts'] ?? []))));
}

function iss_content_model_veranstaltung_required_fact_labels(): array
{
    return [
        'datetime_start' => __('Beginn', 'iss-content-model'),
        'datetime_end' => __('Ende', 'iss-content-model'),
        'published_at' => __('Veroeffentlichung', 'iss-content-model'),
    ];
}

function iss_content_model_veranstaltung_fact_value(WP_Post $post, string $fact): string
{
    $post_id = (int) $post->ID;
    $fact = sanitize_key($fact);

    if (function_exists('iss_content_model_veranstaltung_fact_meta_value')) {
        return iss_content_model_veranstaltung_fact_meta_value($post, $fact);
    }

    if ($fact === 'datetime_start') {
        return trim((string) get_post_meta($post_id, 'iss_start_datetime', true));
    }
    if ($fact === 'datetime_end') {
        return trim((string) get_post_meta($post_id, 'iss_end_datetime', true));
    }
    if ($fact === 'published_at') {
        $published_at = trim((string) $post->post_date);
        return $published_at !== '0000-00-00 00:00:00' ? $published_at : '';
    }

    return '';
}

function iss_content_model_veranstaltung_missing_required_facts(WP_Post $post, string $entity_key): array
{
    $missing = [];
    foreach (iss_content_model_veranstaltung_required_facts_for_entity($entity_key) as $fact) {
        if (iss_content_model_veranstaltung_fact_value($post, $fact) === '') {
            $missing[] = $fact;
        }
    }

    return $missing;
}
