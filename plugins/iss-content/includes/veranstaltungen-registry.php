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
    $repair_cafe_fields = [
        ['name' => 'datetime_start', 'type' => 'datetime', 'required' => true],
        ['name' => 'rhythmus', 'type' => 'string', 'required' => false],
    ];
    $report_fields = [
        ['name' => 'published_at', 'type' => 'date', 'required' => true],
        ['name' => 'bezug_termin', 'type' => 'relation', 'target' => 'veranstaltung', 'required' => false],
    ];

    return [
        'schema_version' => 1,
        'shapes' => [
            'moment' => [
                'label' => __('Termin', 'iss-content-model'),
                'expires' => true,
                'primary_surface' => 'timeline',
                'required_facts' => ['datetime_start'],
            ],
            'span' => [
                'label' => __('Programmspanne', 'iss-content-model'),
                'expires' => true,
                'primary_surface' => 'timeline',
                'required_facts' => ['datetime_start', 'datetime_end'],
            ],
            'manual_recurring' => [
                'label' => __('Manuell wiederkehrend', 'iss-content-model'),
                'expires' => false,
                'primary_surface' => 'timeline',
                'required_facts' => ['datetime_start'],
            ],
            'backward' => [
                'label' => __('Rueckblick', 'iss-content-model'),
                'expires' => false,
                'primary_surface' => 'archive',
                'required_facts' => ['published_at'],
            ],
        ],
        'entities' => [
            'event.general' => [
                'label' => __('Termin', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'calendar',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'zitat', 'material', 'galerie'],
                'fields' => $moment_fields,
            ],
            'event.vortrag' => [
                'label' => __('Vortrag', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'mic',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'zitat', 'material', 'galerie'],
                'fields' => $moment_fields,
            ],
            'event.gespraech' => [
                'label' => __('Gespraech', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'chat',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'zitat', 'material', 'galerie'],
                'fields' => $moment_fields,
            ],
            'event.lesung' => [
                'label' => __('Lesung', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'book',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'zitat', 'material', 'galerie'],
                'fields' => $moment_fields,
            ],
            'event.praesentation' => [
                'label' => __('Praesentation', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'presentation',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'zitat', 'material', 'galerie'],
                'fields' => $moment_fields,
            ],
            'event.workshop' => [
                'label' => __('Workshop', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'tools',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'zitat', 'material', 'galerie'],
                'fields' => $moment_fields,
            ],
            'event.konzert' => [
                'label' => __('Konzert', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'note',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'zitat', 'material', 'galerie'],
                'fields' => $moment_fields,
            ],
            'event.school_program' => [
                'label' => __('Schulprogramm', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'moment',
                'icon' => 'school',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'material', 'galerie'],
                'fields' => $moment_fields,
            ],
            'event.festival' => [
                'label' => __('Festival / Programm', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'span',
                'icon' => 'calendar-star',
                'default_skin' => 'programmatisch',
                'allowed_gestures' => ['intro', 'programm', 'kapitel', 'material', 'galerie'],
                'fields' => $span_fields,
            ],
            'event.repair_cafe' => [
                'label' => __('Repair Cafe', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'manual_recurring',
                'icon' => 'wrench',
                'default_skin' => 'typografisch',
                'allowed_gestures' => ['intro', 'kapitel', 'material', 'galerie'],
                'fields' => $repair_cafe_fields,
            ],
            'report.rueckblick' => [
                'label' => __('Rueckblick', 'iss-content-model'),
                'domain' => 'veranstaltung',
                'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
                'shape' => 'backward',
                'icon' => 'archive',
                'default_skin' => 'dokumentarisch',
                'allowed_gestures' => ['intro', 'kapitel', 'leitfrage', 'zitat', 'chronik', 'galerie', 'material'],
                'fields' => $report_fields,
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

function iss_content_model_veranstaltung_entity_options(): array
{
    $options = [
        '' => [
            'label' => __('Noch nicht festgelegt', 'iss-content-model'),
            'description' => __('Bitte einen semantischen Veranstaltungstyp setzen.', 'iss-content-model'),
        ],
    ];

    foreach (iss_content_model_veranstaltung_entities() as $entity_key => $entity) {
        $shape = (string) ($entity['shape'] ?? '');
        $options[(string) $entity_key] = [
            'label' => (string) ($entity['label'] ?? $entity_key),
            'description' => sprintf(
                /* translators: %s: technical event shape. */
                __('Semantischer Typ, Shape: %s.', 'iss-content-model'),
                $shape !== '' ? $shape : '-'
            ),
        ];
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

    return iss_content_model_veranstaltung_entity_key_is_valid($value) ? $value : '';
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
