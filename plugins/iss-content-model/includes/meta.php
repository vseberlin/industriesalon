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
