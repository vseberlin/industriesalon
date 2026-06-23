<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_veranstaltung_content_meta_key(): string
{
    return '_iss_content_json';
}

function iss_content_model_veranstaltung_skin_override_meta_key(): string
{
    return '_iss_skin_override';
}

function iss_content_model_veranstaltung_empty_content_document(string $entity_key = ''): array
{
    $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
        ? iss_content_model_sanitize_veranstaltung_entity_key($entity_key)
        : '';

    return [
        'schema_version' => 1,
        'entity_key' => $entity_key,
        'sections' => [],
    ];
}

function iss_content_model_sanitize_veranstaltung_content_json($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $decoded = json_decode($value, true);
    if (!is_array($decoded)) {
        return '';
    }

    $schema_version = (int) ($decoded['schema_version'] ?? $decoded['schemaVersion'] ?? 0);
    if ($schema_version !== 1) {
        return '';
    }

    $entity_key = '';
    if (isset($decoded['entity_key'])) {
        $entity_key = iss_content_model_sanitize_veranstaltung_entity_key((string) $decoded['entity_key']);
        if ($entity_key === '') {
            return '';
        }
    }

    $sections = [];
    foreach ((array) ($decoded['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $type = sanitize_key((string) ($section['type'] ?? ''));
        if ($type === '') {
            continue;
        }

        $section['type'] = $type;
        $sections[] = $section;
    }

    $normalized = [
        'schema_version' => 1,
        'entity_key' => $entity_key,
        'sections' => $sections,
    ];

    return (string) wp_json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function iss_content_model_veranstaltung_content_document(int $post_id): array
{
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return [];
    }

    $stored = trim((string) get_post_meta($post_id, iss_content_model_veranstaltung_content_meta_key(), true));
    if ($stored === '') {
        return [];
    }

    $sanitized = iss_content_model_sanitize_veranstaltung_content_json($stored);
    if ($sanitized === '') {
        return [];
    }

    $decoded = json_decode($sanitized, true);

    return is_array($decoded) ? $decoded : [];
}

function iss_content_model_register_veranstaltung_content_meta(): void
{
    register_post_meta(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, iss_content_model_veranstaltung_content_meta_key(), [
        'single' => true,
        'type' => 'string',
        'default' => '',
        'show_in_rest' => true,
        'sanitize_callback' => 'iss_content_model_sanitize_veranstaltung_content_json',
        'auth_callback' => static function () {
            return current_user_can('edit_posts');
        },
    ]);

    register_post_meta(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, iss_content_model_veranstaltung_skin_override_meta_key(), [
        'single' => true,
        'type' => 'string',
        'default' => '',
        'show_in_rest' => true,
        'sanitize_callback' => 'sanitize_key',
        'auth_callback' => static function () {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('init', 'iss_content_model_register_veranstaltung_content_meta', 25);
