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
            'iss_primary_place_id' => ['type' => 'integer', 'sanitize' => 'absint', 'default' => 0],
            'iss_location' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_programme_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
        ],
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE => [
            'iss_start_date' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_end_date' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_public_overview_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
            'iss_programme_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
        ],
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE => [
            'iss_start_date' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_end_date' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_period_label' => ['type' => 'string', 'sanitize' => 'sanitize_text_field', 'default' => ''],
            'iss_programme_enabled' => ['type' => 'boolean', 'sanitize' => 'rest_sanitize_boolean', 'default' => false],
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

function iss_content_model_migrate_programme_visibility_meta(): void
{
    $version = '20260613-programme-visibility-v2';
    $previous_version = (string) get_option('iss_content_model_programme_visibility_meta_version', '');
    if ($previous_version === $version) {
        return;
    }

    global $wpdb;

    $old_key = 'iss_timeline_enabled';
    $programme_key = 'iss_programme_enabled';
    $overview_key = 'iss_public_overview_enabled';
    $editorial_programme_post_types = [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
    ];

    // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- One-time meta-key rename for plugin-owned editorial visibility flags.
    foreach ($editorial_programme_post_types as $post_type) {
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
            SELECT old_meta.post_id, %s, old_meta.meta_value
            FROM {$wpdb->postmeta} old_meta
            INNER JOIN {$wpdb->posts} p ON p.ID = old_meta.post_id
            LEFT JOIN {$wpdb->postmeta} existing_meta
                ON existing_meta.post_id = old_meta.post_id
                AND existing_meta.meta_key = %s
            WHERE old_meta.meta_key = %s
              AND p.post_type = %s
              AND existing_meta.meta_id IS NULL",
            $programme_key,
            $programme_key,
            $old_key,
            $post_type
        ));
    }

    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
        SELECT old_meta.post_id, %s, old_meta.meta_value
        FROM {$wpdb->postmeta} old_meta
        INNER JOIN {$wpdb->posts} p ON p.ID = old_meta.post_id
        LEFT JOIN {$wpdb->postmeta} existing_meta
            ON existing_meta.post_id = old_meta.post_id
            AND existing_meta.meta_key = %s
        WHERE old_meta.meta_key = %s
          AND p.post_type = %s
          AND existing_meta.meta_id IS NULL
          AND NOT EXISTS (
            SELECT 1
            FROM {$wpdb->term_relationships} tr
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE tr.object_id = old_meta.post_id
              AND tt.taxonomy = %s
              AND t.slug IN (%s, %s)
          )",
        $programme_key,
        $programme_key,
        $old_key,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY,
        'dauerausstellung',
        'digitaleausstellungen'
    ));

    if ($previous_version === '20260613-programme-visibility-v1') {
        $wpdb->query($wpdb->prepare(
            "DELETE programme_meta
            FROM {$wpdb->postmeta} programme_meta
            INNER JOIN {$wpdb->posts} p ON p.ID = programme_meta.post_id
            INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE programme_meta.meta_key = %s
              AND p.post_type = %s
              AND tt.taxonomy = %s
              AND t.slug IN (%s, %s)",
            $programme_key,
            ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
            ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY,
            'dauerausstellung',
            'digitaleausstellungen'
        ));
    }

    $wpdb->query($wpdb->prepare(
        "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
        SELECT old_meta.post_id, %s, old_meta.meta_value
        FROM {$wpdb->postmeta} old_meta
        INNER JOIN {$wpdb->posts} p ON p.ID = old_meta.post_id
        LEFT JOIN {$wpdb->postmeta} existing_meta
            ON existing_meta.post_id = old_meta.post_id
            AND existing_meta.meta_key = %s
        WHERE old_meta.meta_key = %s
          AND p.post_type = %s
          AND existing_meta.meta_id IS NULL",
        $overview_key,
        $overview_key,
        $old_key,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE
    ));

    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
        $old_key
    ));
    // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

    update_option('iss_content_model_programme_visibility_meta_version', $version, false);
}
add_action('init', 'iss_content_model_migrate_programme_visibility_meta', 30);

function iss_content_model_migrate_veranstaltung_primary_place_meta(): void
{
    $version = '20260628-primary-place-meta-v1';
    if ((string) get_option('iss_content_model_veranstaltung_primary_place_meta_version', '') === $version) {
        return;
    }

    if (!post_type_exists(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) || !function_exists('iss_relations_get_post_relations')) {
        return;
    }

    $post_ids = get_posts([
        'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    foreach ($post_ids as $post_id) {
        $post_id = (int) $post_id;
        if (absint(get_post_meta($post_id, 'iss_primary_place_id', true)) > 0) {
            continue;
        }

        $place_id = function_exists('iss_content_model_get_veranstaltung_primary_place_id')
            ? iss_content_model_get_veranstaltung_primary_place_id($post_id)
            : 0;

        if ($place_id > 0) {
            update_post_meta($post_id, 'iss_primary_place_id', $place_id);
        }
    }

    update_option('iss_content_model_veranstaltung_primary_place_meta_version', $version, false);
}
add_action('init', 'iss_content_model_migrate_veranstaltung_primary_place_meta', 40);

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
