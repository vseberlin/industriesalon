<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_veranstaltung_content_meta_key(): string
{
    return '_iss_content_json';
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

function iss_content_model_veranstaltung_content_gestures(): array
{
    return [
        'intro' => [
            'label' => __('Intro', 'iss-content-model'),
            'description' => __('Kurz gefasster Einstieg oder Ankuendigungstext.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'media_refs', 'dynamic_refs'],
        ],
        'kapitel' => [
            'label' => __('Kapitel', 'iss-content-model'),
            'description' => __('Thematischer Abschnitt fuer Kontext, Einordnung oder Ablauf.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'media_refs', 'dynamic_refs'],
        ],
        'leitfrage' => [
            'label' => __('Leitfrage', 'iss-content-model'),
            'description' => __('Frage oder These, die den Beitrag rahmt.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body'],
        ],
        'zitat' => [
            'label' => __('Zitat', 'iss-content-model'),
            'description' => __('Zitat mit Zuordnung oder Quellenhinweis.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'quote', 'attribution'],
        ],
        'chronik' => [
            'label' => __('Chronik', 'iss-content-model'),
            'description' => __('Zeitliche Folge oder Rueckblickabschnitt.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'items'],
        ],
        'programm' => [
            'label' => __('Programm', 'iss-content-model'),
            'description' => __('Lineare Programmpunkte fuer Feste und Reihen.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'items'],
        ],
        'material' => [
            'label' => __('Material', 'iss-content-model'),
            'description' => __('Hinweise auf Quellen, Downloads, Links oder Begleitangebote.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'items', 'object_refs', 'dynamic_refs'],
        ],
        'upload_intake' => [
            'label' => __('Upload-Aufruf', 'iss-content-model'),
            'description' => __('Oeffentlicher Aufruf zum Hochladen von Material in den moderierten Intake.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'items'],
        ],
        'galerie' => [
            'label' => __('Galerie', 'iss-content-model'),
            'description' => __('Bildstrecke aus Medienbibliothek oder spaeterem Upload-Intake.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'media_refs', 'object_refs'],
        ],
    ];
}

function iss_content_model_veranstaltung_content_gestures_for_entity(string $entity_key): array
{
    $gestures = iss_content_model_veranstaltung_content_gestures();
    $entity = function_exists('iss_content_model_veranstaltung_entity')
        ? iss_content_model_veranstaltung_entity($entity_key)
        : [];
    $allowed = array_values(array_filter(array_map('sanitize_key', (array) ($entity['allowed_gestures'] ?? []))));

    if (!$allowed) {
        return $gestures;
    }

    return array_intersect_key($gestures, array_fill_keys($allowed, true));
}

function iss_content_model_veranstaltung_content_type_for_entity(string $entity_key, string $preferred_type): string
{
    $gestures = iss_content_model_veranstaltung_content_gestures_for_entity($entity_key);
    $preferred_type = sanitize_key($preferred_type);
    if ($preferred_type !== '' && isset($gestures[$preferred_type])) {
        return $preferred_type;
    }
    if (isset($gestures['kapitel'])) {
        return 'kapitel';
    }
    if (isset($gestures['intro'])) {
        return 'intro';
    }

    $keys = array_keys($gestures);
    return (string) ($keys[0] ?? 'intro');
}

function iss_content_model_sanitize_veranstaltung_content_items($items): array
{
    if (is_string($items)) {
        $items = preg_split('/\R/u', $items) ?: [];
    }

    $sanitized = [];
    foreach ((array) $items as $item) {
        $item = trim(sanitize_text_field((string) $item));
        if ($item !== '') {
            $sanitized[] = $item;
        }
    }

    return $sanitized;
}

function iss_content_model_sanitize_veranstaltung_content_reference($reference): array
{
    if (!is_array($reference)) {
        return [];
    }

    $kind = sanitize_key((string) ($reference['kind'] ?? ''));
    $source = sanitize_key((string) ($reference['source'] ?? ''));
    $id = sanitize_text_field((string) ($reference['id'] ?? ''));
    if ($kind === '' || $source === '' || $id === '') {
        return [];
    }

    $label = sanitize_textarea_field((string) ($reference['label'] ?? ''));
    if ($source === 'iss-archive') {
        $label = iss_content_model_veranstaltung_content_compact_reference_text($label, 140);
    }

    $sanitized = [
        'kind' => $kind,
        'source' => $source,
        'id' => $id,
        'label' => $label,
    ];

    if ($source !== 'wp-media') {
        $sanitized['thumbnail'] = esc_url_raw((string) ($reference['thumbnail'] ?? ''));
    }

    foreach (['set_id', 'member_id', 'width', 'height'] as $field) {
        if (isset($reference[$field])) {
            $sanitized[$field] = (string) absint($reference[$field]);
        }
    }

    foreach (['set_title'] as $field) {
        if (isset($reference[$field])) {
            $sanitized[$field] = sanitize_textarea_field((string) $reference[$field]);
        }
    }

    return $sanitized;
}

function iss_content_model_veranstaltung_content_compact_reference_text(string $value, int $limit): string
{
    $value = trim((string) preg_replace('/\s+/u', ' ', wp_strip_all_tags($value)));
    if ($limit <= 0 || strlen($value) <= $limit) {
        return $value;
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return rtrim((string) mb_substr($value, 0, max(1, $limit - 1))) . '…';
    }

    return rtrim(substr($value, 0, max(1, $limit - 1))) . '…';
}

function iss_content_model_sanitize_veranstaltung_content_reference_list($references): array
{
    $items = [];
    foreach ((array) $references as $reference) {
        $reference = iss_content_model_sanitize_veranstaltung_content_reference($reference);
        if ($reference) {
            $items[] = $reference;
        }
    }

    return $items;
}

function iss_content_model_sanitize_veranstaltung_content_dynamic_reference($reference): array
{
    if (!is_array($reference)) {
        return [];
    }

    $kind = sanitize_key((string) ($reference['kind'] ?? ''));
    $source = sanitize_key((string) ($reference['source'] ?? ''));
    $key = trim(sanitize_text_field((string) ($reference['key'] ?? '')));
    if ($kind !== 'control_field' || $source !== 'industriesalon-steuerung' || $key === '') {
        return [];
    }
    if (!preg_match('/^[a-z0-9_.-]+$/', $key)) {
        return [];
    }

    $sanitized = [
        'kind' => $kind,
        'source' => $source,
        'key' => $key,
        'label' => sanitize_text_field((string) ($reference['label'] ?? '')),
    ];

    foreach (['tagName', 'linkMode', 'text', 'cssClass', 'hrefSuffix'] as $field) {
        $value = trim(sanitize_text_field((string) ($reference[$field] ?? '')));
        if ($value !== '') {
            $sanitized[$field] = $value;
        }
    }

    return $sanitized;
}

function iss_content_model_sanitize_veranstaltung_content_dynamic_reference_list($references): array
{
    $items = [];
    foreach ((array) $references as $reference) {
        $reference = iss_content_model_sanitize_veranstaltung_content_dynamic_reference($reference);
        if ($reference) {
            $items[] = $reference;
        }
    }

    return $items;
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

    $allowed_gestures = array_keys(iss_content_model_veranstaltung_content_gestures_for_entity($entity_key));
    if (!$allowed_gestures) {
        $allowed_gestures = array_keys(iss_content_model_veranstaltung_content_gestures());
    }
    $allowed_lookup = array_fill_keys($allowed_gestures, true);
    $sections = [];
    foreach ((array) ($decoded['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $type = sanitize_key((string) ($section['type'] ?? ''));
        if ($type === '' || !isset($allowed_lookup[$type])) {
            continue;
        }

        $normalized_section = [
            'type' => $type,
        ];

        foreach (['kicker', 'title', 'attribution'] as $field) {
            $value = trim(sanitize_text_field((string) ($section[$field] ?? '')));
            if ($value !== '') {
                $normalized_section[$field] = $value;
            }
        }

        foreach (['body', 'quote'] as $field) {
            $value = trim(sanitize_textarea_field((string) ($section[$field] ?? '')));
            if ($value !== '') {
                $normalized_section[$field] = $value;
            }
        }

        $items = iss_content_model_sanitize_veranstaltung_content_items($section['items'] ?? []);
        if ($items) {
            $normalized_section['items'] = $items;
        }

        $gesture = $allowed_lookup[$type] ? iss_content_model_veranstaltung_content_gestures()[$type] ?? [] : [];
        $supports = (array) ($gesture['supports'] ?? []);
        if (in_array('media_refs', $supports, true)) {
            $media_refs = iss_content_model_sanitize_veranstaltung_content_reference_list($section['media_refs'] ?? []);
            if ($media_refs) {
                $normalized_section['media_refs'] = $media_refs;
            }
        }
        if (in_array('object_refs', $supports, true)) {
            $object_refs = iss_content_model_sanitize_veranstaltung_content_reference_list($section['object_refs'] ?? []);
            if ($object_refs) {
                $normalized_section['object_refs'] = $object_refs;
            }
        }
        if (in_array('dynamic_refs', $supports, true)) {
            $dynamic_refs = iss_content_model_sanitize_veranstaltung_content_dynamic_reference_list($section['dynamic_refs'] ?? []);
            if ($dynamic_refs) {
                $normalized_section['dynamic_refs'] = $dynamic_refs;
            }
        }

        $sections[] = $normalized_section;
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
}
add_action('init', 'iss_content_model_register_veranstaltung_content_meta', 25);
