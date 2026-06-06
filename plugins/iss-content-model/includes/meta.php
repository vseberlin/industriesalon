<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_get_video_transcript_status_options(): array
{
    return [
        'none' => __('Kein Transkript', 'iss-content-model'),
        'planned' => __('In Vorbereitung', 'iss-content-model'),
        'excerpt' => __('Begleittext / Auszug', 'iss-content-model'),
        'full' => __('Vollständiges Transkript', 'iss-content-model'),
    ];
}

function iss_content_model_normalize_video_transcript_status(string $status): string
{
    $status = sanitize_key($status);
    $options = iss_content_model_get_video_transcript_status_options();

    return array_key_exists($status, $options) ? $status : 'none';
}

function iss_content_model_resolve_video_transcript_status(string $status, bool $has_body_content = false): string
{
    $status = iss_content_model_normalize_video_transcript_status($status);

    if ($status === 'none' && $has_body_content) {
        return 'excerpt';
    }

    return $status;
}

function iss_content_model_get_video_transcript_status_label(string $status, bool $has_body_content = false): string
{
    $status = iss_content_model_resolve_video_transcript_status($status, $has_body_content);
    $options = iss_content_model_get_video_transcript_status_options();

    return $options[$status] ?? $options['none'];
}

function iss_content_model_get_video_transcript_link_label(string $status, bool $has_body_content = false): string
{
    $status = iss_content_model_resolve_video_transcript_status($status, $has_body_content);

    if ($status === 'full') {
        return __('Transkript lesen', 'iss-content-model');
    }

    if ($status === 'excerpt') {
        return __('Zum Begleittext', 'iss-content-model');
    }

    return __('Zum Beitrag', 'iss-content-model');
}

function iss_content_model_get_ausstellung_type_options(): array
{
    return [
        'story' => [
            'label' => __('Story Exhibition', 'iss-content-model'),
            'description' => __('Erzählende Ausstellung mit Kapiteln, Bildern, Quellen und weiterführendem Material.', 'iss-content-model'),
        ],
        'collection' => [
            'label' => __('Collection Exhibition', 'iss-content-model'),
            'description' => __('Kuratierter Bestand aus Objekten oder Archivgruppen, gedacht zum Stöbern und Vergleichen.', 'iss-content-model'),
        ],
        'visual_essay' => [
            'label' => __('Visual Essay', 'iss-content-model'),
            'description' => __('Große Bilder mit knappen Bildtexten und wenig zusätzlicher Erklärung.', 'iss-content-model'),
        ],
        'timeline' => [
            'label' => __('Timeline Exhibition', 'iss-content-model'),
            'description' => __('Chronologische Erzählung mit Daten, Bildern und kurzen Deutungstexten.', 'iss-content-model'),
        ],
        'map' => [
            'label' => __('Map Exhibition', 'iss-content-model'),
            'description' => __('Atlasgeführte Ausstellung, in der Orte die öffentliche Reihenfolge bilden.', 'iss-content-model'),
        ],
    ];
}

function iss_content_model_normalize_ausstellung_type(string $type): string
{
    $type = sanitize_key($type);
    $options = iss_content_model_get_ausstellung_type_options();

    return array_key_exists($type, $options) ? $type : 'story';
}

function iss_content_model_get_ausstellung_source_options(): array
{
    return [
        'manual' => [
            'label' => __('Manueller Inhalt', 'iss-content-model'),
            'description' => __('Der normale Beitragsinhalt bildet den Ausstellungskörper.', 'iss-content-model'),
        ],
        'curated_chapters' => [
            'label' => __('Kuratierte Kapitel', 'iss-content-model'),
            'description' => __('Eine sortierte Auswahl von Archivbeiträgen bildet die Stationen.', 'iss-content-model'),
        ],
        'archive_category' => [
            'label' => __('Archivkategorie als Kapitelpfad', 'iss-content-model'),
            'description' => __('Eine Archivkategorie erzeugt automatisch den Kapitelpfad.', 'iss-content-model'),
        ],
        'archive_browser' => [
            'label' => __('Archivbrowser als Objektbestand', 'iss-content-model'),
            'description' => __('Ein gefilterter Archivbrowser liefert das Material für eine Collection Exhibition.', 'iss-content-model'),
        ],
        'atlas_places' => [
            'label' => __('Atlas-Orte', 'iss-content-model'),
            'description' => __('Verknüpfte Atlas-Orte bilden die Stationen der Ausstellung.', 'iss-content-model'),
        ],
    ];
}

function iss_content_model_normalize_ausstellung_source(string $source): string
{
    $source = sanitize_key($source);
    $options = iss_content_model_get_ausstellung_source_options();

    return array_key_exists($source, $options) ? $source : 'manual';
}

function iss_content_model_parse_slug_list($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/[\s,]+/', (string) $value);
    }

    $slugs = [];
    foreach ((array) $items as $item) {
        $slug = sanitize_title((string) $item);
        if ($slug !== '') {
            $slugs[] = $slug;
        }
    }

    return array_values(array_unique($slugs));
}

function iss_content_model_parse_id_list($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/[\s,]+/', (string) $value);
    }

    $ids = [];
    foreach ((array) $items as $item) {
        $id = absint($item);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function iss_content_model_sanitize_id_list($value): string
{
    return implode(',', iss_content_model_parse_id_list($value));
}

function iss_content_model_sanitize_slug_list($value): string
{
    return implode(',', iss_content_model_parse_slug_list($value));
}

function iss_content_model_sanitize_iso_date($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
    return $date instanceof DateTimeImmutable && $date->format('Y-m-d') === $value ? $value : '';
}

function iss_content_model_format_video_original_date(string $value): string
{
    $value = iss_content_model_sanitize_iso_date($value);
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
    if (!$date instanceof DateTimeImmutable) {
        return '';
    }

    return wp_date(get_option('date_format'), $date->getTimestamp(), wp_timezone());
}

function iss_content_model_get_video_year_label(string $year, string $original_date = ''): string
{
    $year = trim($year);
    if ($year !== '') {
        return $year;
    }

    $original_date = iss_content_model_sanitize_iso_date($original_date);
    if ($original_date === '') {
        return '';
    }

    return substr($original_date, 0, 4);
}

function iss_content_model_get_ausstellung_type(int $post_id): string
{
    return iss_content_model_normalize_ausstellung_type((string) get_post_meta($post_id, 'iss_exhibition_type', true));
}

function iss_content_model_get_ausstellung_source(int $post_id): string
{
    return iss_content_model_normalize_ausstellung_source((string) get_post_meta($post_id, 'iss_exhibition_source', true));
}

function iss_content_model_get_ausstellung_corpus_chapter_ids(int $post_id): array
{
    return iss_content_model_parse_id_list((string) get_post_meta($post_id, 'iss_corpus_chapter_ids', true));
}

function iss_content_model_get_ausstellung_archive_term_slug(int $post_id): string
{
    return sanitize_title((string) get_post_meta($post_id, 'iss_archive_term_slug', true));
}

function iss_content_model_get_ausstellung_archive_browser_config(int $post_id): array
{
    return [
        'default_source' => sanitize_title((string) get_post_meta($post_id, 'iss_archive_browser_default_source', true)),
        'lock_source' => (bool) get_post_meta($post_id, 'iss_archive_browser_lock_source', true),
        'default_field' => sanitize_title((string) get_post_meta($post_id, 'iss_archive_browser_default_field', true)),
        'lock_field' => (bool) get_post_meta($post_id, 'iss_archive_browser_lock_field', true),
        'quick_kicker' => trim((string) get_post_meta($post_id, 'iss_archive_browser_quick_kicker', true)),
        'quick_title' => trim((string) get_post_meta($post_id, 'iss_archive_browser_quick_title', true)),
        'quick_family_slugs' => iss_content_model_parse_slug_list((string) get_post_meta($post_id, 'iss_archive_browser_quick_family_slugs', true)),
        'show_source_cards' => (bool) get_post_meta($post_id, 'iss_archive_browser_show_source_cards', true),
    ];
}

function iss_content_model_meta_definitions() {
    return [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE => [
            'iss_start_datetime' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_end_datetime' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_location' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_timeline_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => true],
            'iss_timeline_item_id' => ['type' => 'integer', 'sanitize' => 'absint', 'default' => 0],
        ],
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE => [
            'iss_start_date' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_end_date' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_is_permanent' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
            'iss_timeline_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => true],
            'iss_timeline_item_id' => ['type' => 'integer', 'sanitize' => 'absint', 'default' => 0],
            'iss_exhibition_type' => ['type' => 'string', 'sanitize' => 'iss_content_model_normalize_ausstellung_type', 'default' => 'story'],
            'iss_exhibition_source' => ['type' => 'string', 'sanitize' => 'iss_content_model_normalize_ausstellung_source', 'default' => 'manual'],
            'iss_archive_term_slug' => ['type' => 'string', 'sanitize' => 'sanitize_title', 'default' => ''],
            'iss_archive_browser_default_source' => ['type' => 'string', 'sanitize' => 'sanitize_title', 'default' => ''],
            'iss_archive_browser_lock_source' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
            'iss_archive_browser_default_field' => ['type' => 'string', 'sanitize' => 'sanitize_title', 'default' => ''],
            'iss_archive_browser_lock_field' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
            'iss_archive_browser_quick_kicker' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_archive_browser_quick_title' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_archive_browser_quick_family_slugs' => ['type' => 'string', 'sanitize' => 'iss_content_model_sanitize_slug_list', 'default' => ''],
            'iss_archive_browser_show_source_cards' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
            'iss_corpus_chapter_ids' => ['type' => 'string', 'sanitize' => 'iss_content_model_sanitize_id_list', 'default' => ''],
        ],
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE => [
            'iss_period_label' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_timeline_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
            'iss_timeline_item_id' => ['type' => 'integer', 'sanitize' => 'absint', 'default' => 0],
        ],
        ISS_CONTENT_MODEL_TEAM_POST_TYPE => [
            'iss_role_label' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_email' => ['type' => 'string', 'sanitize' => 'sanitize_email', 'default' => ''],
            'iss_phone' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
        ],
        ISS_CONTENT_MODEL_VIDEO_POST_TYPE => [
            'iss_video_url' => ['type' => 'string', 'sanitize' => 'esc_url_raw', 'default' => ''],
            'iss_video_source_family' => ['type' => 'string', 'sanitize' => 'sanitize_key', 'default' => 'core'],
            'iss_video_source_label' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_video_source_url' => ['type' => 'string', 'sanitize' => 'esc_url_raw', 'default' => ''],
            'iss_video_year' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_video_original_date' => ['type' => 'string', 'sanitize' => 'iss_content_model_sanitize_iso_date', 'default' => ''],
            'iss_video_duration' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_video_language' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_video_rights' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_video_transcript_status' => ['type' => 'string', 'sanitize' => 'iss_content_model_normalize_video_transcript_status', 'default' => 'none'],
            'iss_video_transcript_source' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_video_featured' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
        ],
    ];
}

function iss_content_model_register_meta() {
    foreach (iss_content_model_meta_definitions() as $post_type => $fields) {
        foreach ($fields as $key => $config) {
            register_post_meta($post_type, $key, [
                'type' => $config['type'] === 'boolean' ? 'boolean' : ($config['type'] === 'integer' ? 'integer' : 'string'),
                'single' => true,
                'show_in_rest' => true,
                'default' => $config['default'],
                'sanitize_callback' => 'iss_content_model_sanitize_meta_value',
                'auth_callback' => static function () {
                    return current_user_can('edit_posts');
                },
            ]);
        }
    }
}
add_action('init', 'iss_content_model_register_meta', 20);

function iss_content_model_sanitize_meta_value($value, $meta_key, $meta_type) {
    foreach (iss_content_model_meta_definitions() as $post_type => $fields) {
        if (!isset($fields[$meta_key])) {
            continue;
        }

        $config = $fields[$meta_key];
        $sanitizer = $config['sanitize'];
        $sanitized = is_callable($sanitizer) ? call_user_func($sanitizer, $value) : sanitize_text_field((string) $value);

        if ($config['type'] === 'boolean') {
            return (bool) $sanitized;
        }

        if ($config['type'] === 'integer') {
            return (int) $sanitized;
        }

        return (string) $sanitized;
    }

    return sanitize_text_field((string) $value);
}
