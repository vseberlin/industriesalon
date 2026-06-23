<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_veranstaltung_fact_meta_keys(): array
{
    return [
        'datetime_start' => '_iss_datetime_start',
        'datetime_end' => '_iss_datetime_end',
        'published_at' => '_iss_published_at',
    ];
}

function iss_content_model_sanitize_mysql_datetime($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value, wp_timezone());
    } catch (Throwable $e) {
        return '';
    }

    return $dt->format('Y-m-d H:i:s');
}

function iss_content_model_register_veranstaltung_fact_meta(): void
{
    foreach (iss_content_model_veranstaltung_fact_meta_keys() as $meta_key) {
        register_post_meta(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, $meta_key, [
            'single' => true,
            'type' => 'string',
            'default' => '',
            'show_in_rest' => true,
            'sanitize_callback' => 'iss_content_model_sanitize_mysql_datetime',
            'auth_callback' => static function () {
                return current_user_can('edit_posts');
            },
        ]);
    }
}
add_action('init', 'iss_content_model_register_veranstaltung_fact_meta', 25);

function iss_content_model_veranstaltung_resolve_normalized_facts(WP_Post $post): array
{
    $post_id = (int) $post->ID;
    $published_at = trim((string) $post->post_date);
    if ($published_at === '0000-00-00 00:00:00') {
        $published_at = '';
    }

    return [
        'datetime_start' => iss_content_model_sanitize_mysql_datetime(get_post_meta($post_id, 'iss_start_datetime', true)),
        'datetime_end' => iss_content_model_sanitize_mysql_datetime(get_post_meta($post_id, 'iss_end_datetime', true)),
        'published_at' => iss_content_model_sanitize_mysql_datetime($published_at),
    ];
}

function iss_content_model_sync_veranstaltung_normalized_facts(int $post_id): void
{
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return;
    }
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return;
    }

    $facts = iss_content_model_veranstaltung_resolve_normalized_facts($post);
    $meta_keys = iss_content_model_veranstaltung_fact_meta_keys();

    foreach ($meta_keys as $fact => $meta_key) {
        $value = trim((string) ($facts[$fact] ?? ''));
        if ($value === '') {
            delete_post_meta($post_id, $meta_key);
            continue;
        }

        update_post_meta($post_id, $meta_key, $value);
    }
}
add_action('save_post_' . ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, 'iss_content_model_sync_veranstaltung_normalized_facts', 35);

function iss_content_model_veranstaltung_fact_meta_value(WP_Post $post, string $fact): string
{
    $fact = sanitize_key($fact);
    $meta_keys = iss_content_model_veranstaltung_fact_meta_keys();
    $meta_key = (string) ($meta_keys[$fact] ?? '');

    if ($meta_key !== '') {
        $value = trim((string) get_post_meta((int) $post->ID, $meta_key, true));
        if ($value !== '') {
            return $value;
        }
    }

    $facts = iss_content_model_veranstaltung_resolve_normalized_facts($post);

    return trim((string) ($facts[$fact] ?? ''));
}
