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
            'label' => __('Die Kernfrage oder These', 'iss-content-model'),
            'description' => __('Gibt den roten Faden vor und führt den Besucher durch das Thema.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body'],
        ],
        'zitat' => [
            'label' => __('Ein prägnantes Zitat zum Inhalt.', 'iss-content-model'),
            'description' => __('Mit Angabe der Person oder der historischen Quelle.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'quote', 'attribution'],
        ],
        'programm' => [
            'label' => __('Programm', 'iss-content-model'),
            'description' => __('Lineare Programmpunkte fuer Feste und Reihen.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body'],
        ],
        'material' => [
            'label' => __('Begleitende Dateien', 'iss-content-model'),
            'description' => __('Downloads, Quellen und ergänzende Verweise zum Inhalt.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'media_refs', 'object_refs', 'links', 'dynamic_refs', 'items'],
            'items_kind' => 'text',
        ],
        'upload_intake' => [
            'label' => __('Öffentlicher Mitmach-Aufruf für Gäste', 'iss-content-model'),
            'description' => __('Schickt hochgeladene Besucherfotos direkt in die Warteschlange zur Freigabe.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body'],
        ],
        'galerie' => [
            'label' => __('Bildergalerie', 'iss-content-model'),
            'description' => __('Freigegebene Gästefotos und Medienbilder. Flexibel dargestellt als Raster, Bilderwand, Reihe oder Fokus-Ansicht.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body', 'media_refs', 'object_refs'],
        ],
        'schluss' => [
            'label' => __('Schluss', 'iss-content-model'),
            'description' => __('Abschluss, Einladung oder naechster Schritt.', 'iss-content-model'),
            'supports' => ['kicker', 'title', 'body'],
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

function iss_content_model_veranstaltung_body_href_is_safe(string $href): bool
{
    $href = trim($href);
    if ($href === '') {
        return false;
    }

    if (preg_match('/^(#|\/|\?|\.\.?\/)/', $href) === 1) {
        return true;
    }

    if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $href) === 1) {
        return preg_match('/^(https?:|mailto:|tel:)/i', $href) === 1;
    }

    return true;
}

function iss_content_model_strip_unsafe_veranstaltung_body_hrefs(string $body): string
{
    return (string) preg_replace_callback(
        '/<a\b([^>]*)\bhref=(["\'])(.*?)\2([^>]*)>/i',
        static function (array $matches): string {
            $href = html_entity_decode((string) $matches[3], ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
            if (iss_content_model_veranstaltung_body_href_is_safe($href)) {
                return $matches[0];
            }

            return '<a' . $matches[1] . $matches[4] . '>';
        },
        $body
    );
}

function iss_content_model_sanitize_veranstaltung_body_html(string $body): string
{
    $allowed_html = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'em' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => [
            'href' => true,
        ],
    ];

    $body = iss_content_model_strip_unsafe_veranstaltung_body_hrefs($body);

    return trim(wp_kses($body, $allowed_html));
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
    if (function_exists('iss_editorial_validate_document') && iss_editorial_get_format('veranstaltung')) {
        $validated = iss_editorial_validate_document($decoded, 'veranstaltung');
        return is_wp_error($validated) ? '' : iss_editorial_encode_document($validated);
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

        $gesture = $allowed_lookup[$type] ? iss_content_model_veranstaltung_content_gestures()[$type] ?? [] : [];
        $supports = (array) ($gesture['supports'] ?? []);

        foreach (['kicker', 'title', 'attribution'] as $field) {
            $value = trim(sanitize_text_field((string) ($section[$field] ?? '')));
            if ($value !== '') {
                $normalized_section[$field] = $value;
            }
        }

        $body = iss_content_model_sanitize_veranstaltung_body_html((string) ($section['body'] ?? ''));
        if ($body !== '') {
            $normalized_section['body'] = $body;
        }

        $quote = trim(sanitize_textarea_field((string) ($section['quote'] ?? '')));
        if ($quote !== '') {
            $normalized_section['quote'] = $quote;
        }

        if (in_array('items', $supports, true)) {
            $items = iss_content_model_sanitize_veranstaltung_content_items($section['items'] ?? []);
            if ($items) {
                $normalized_section['items'] = $items;
            }
        }

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
    if (function_exists('iss_editorial_should_prefer_preview_autosave') && iss_editorial_should_prefer_preview_autosave($post_id, 'veranstaltung')) {
        return iss_editorial_get_document($post_id, 'veranstaltung', true);
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

/** The shared editor writes the existing event document; no content migration is required. */
function iss_content_model_veranstaltung_dynamic_previews(array $document): array
{
    $previews = [];
    $steuerung = class_exists('Industriesalon_Steuerung') ? Industriesalon_Steuerung::instance() : null;
    foreach ((array) ($document['sections'] ?? []) as $section) {
        foreach ((array) ($section['dynamic_refs'] ?? []) as $reference) {
            if (($reference['source'] ?? '') !== 'industriesalon-steuerung' || ($reference['kind'] ?? '') !== 'control_field' || empty($reference['key'])) {
                continue;
            }
            $key = (string) $reference['key'];
            $previews[$key] = ['label' => (string) ($reference['label'] ?? ''), 'value' => $steuerung ? (string) $steuerung->get_field_value($key, '') : ''];
        }
    }
    return $previews;
}

add_filter('iss_editorial_formats', static function (array $formats): array {
    $formats['veranstaltung'] = [
        'label' => __('Veranstaltung', 'iss-content-model'),
        'post_types' => [ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE],
        'storage_meta_key' => iss_content_model_veranstaltung_content_meta_key(),
        'always_enabled' => true,
        'default_skin' => 'typografisch',
        'sections' => iss_content_model_veranstaltung_content_gestures(),
    ];
    return $formats;
});

add_filter('iss_editorial_document_fields', static function (array $fields, array $format): array {
    if ($format['slug'] === 'veranstaltung') {
        $fields[] = 'entity_key';
    }
    return $fields;
}, 10, 2);

add_filter('iss_editorial_draft_meta', static function (array $meta, array $document, array $format): array {
    if ($format['slug'] === 'veranstaltung') {
        $meta['_iss_entity_key'] = iss_content_model_sanitize_veranstaltung_entity_key($document['entity_key'] ?? '');
    }
    return $meta;
}, 10, 3);

add_filter('get_post_metadata', static function ($value, int $post_id, string $key, bool $single) {
    if ($key === '_iss_entity_key' && function_exists('iss_editorial_should_prefer_preview_autosave') && iss_editorial_should_prefer_preview_autosave($post_id, 'veranstaltung')) {
        $draft = iss_editorial_get_draft($post_id, 'veranstaltung');
        if (!empty($draft['document']['entity_key'])) {
            return $single ? $draft['document']['entity_key'] : [$draft['document']['entity_key']];
        }
    }
    return $value;
}, 9, 4);

add_filter('iss_editorial_admin_settings', static function (array $settings, WP_Post $post, array $format): array {
    if ($format['slug'] === 'veranstaltung') {
        $settings['skins'] = [];
        $settings['dynamicPreviews'] = iss_content_model_veranstaltung_dynamic_previews($settings['document']);
        $settings['documentBindings'] = ['entity_key' => '[name="iss_content_model[_iss_entity_key]"]'];
        $settings['sectionContexts'] = ['entity_key' => []];
        foreach (array_keys(iss_content_model_veranstaltung_entities()) as $key) {
            $settings['sectionContexts']['entity_key'][$key] = array_keys(iss_content_model_veranstaltung_content_gestures_for_entity($key));
        }
    }
    return $settings;
}, 10, 3);

add_filter('iss_editorial_editor_document', static function (array $document, int $post_id, string $format): array {
    if ($format === 'veranstaltung') {
        $document['entity_key'] = get_post_meta($post_id, '_iss_entity_key', true) ?: ($document['entity_key'] ?? 'event.general');
    }
    return $document;
}, 10, 3);

add_filter('iss_editorial_sanitized_section', static function (array $sanitized, array $section, array $format): array {
    if ($format['slug'] === 'veranstaltung' && in_array('dynamic_refs', $format['sections'][$sanitized['type']]['supports'], true)) {
        $sanitized['dynamic_refs'] = iss_content_model_sanitize_veranstaltung_content_dynamic_reference_list($section['dynamic_refs'] ?? []);
    }
    return $sanitized;
}, 10, 3);

add_filter('iss_editorial_sanitized_document', static function (array $sanitized, array $document, array $format): array {
    if ($format['slug'] === 'veranstaltung') {
        $sanitized['entity_key'] = iss_content_model_sanitize_veranstaltung_entity_key((string) ($document['entity_key'] ?? 'event.general'));
    }
    return $sanitized;
}, 10, 3);

add_filter('iss_editorial_validated_document', static function ($validated, array $document, array $format) {
    if (is_wp_error($validated) || $format['slug'] !== 'veranstaltung') {
        return $validated;
    }
    $entity = $validated['entity_key'];
    if ($entity === '') {
        return new WP_Error('editorial_event_type', __('Bitte die Veranstaltungsstruktur auswählen.', 'iss-content-model'));
    }
    $allowed = iss_content_model_veranstaltung_content_gestures_for_entity($entity);
    foreach ($validated['sections'] as $index => $section) {
        if (!isset($allowed[$section['type']])) {
            return new WP_Error('editorial_event_section', sprintf(__('Abschnitt %d passt nicht zur gewählten Veranstaltungsstruktur. Bitte den Abschnitt oder die Struktur prüfen.', 'iss-content-model'), $index + 1));
        }
    }
    return $validated;
}, 10, 3);

add_filter('rest_pre_insert_veranstaltung', static function ($prepared, WP_REST_Request $request) {
    $meta = $request->get_param('meta');
    if (is_array($meta) && isset($meta['_iss_content_json']) && function_exists('iss_editorial_validate_document')) {
        $result = iss_editorial_validate_document($meta['_iss_content_json'], 'veranstaltung');
        if (is_wp_error($result)) {
            return $result;
        }
    }
    return $prepared;
}, 10, 2);
