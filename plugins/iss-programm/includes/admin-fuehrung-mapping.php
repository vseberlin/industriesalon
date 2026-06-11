<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_programm_get_mapping_series_options() {
    $options = [];
    $map = function_exists('iss_occurrences_get_series_map') ? iss_occurrences_get_series_map() : [];

    if (!is_array($map) || empty($map)) {
        return $options;
    }

    foreach ($map as $series_key => $entry) {
        $series_key = trim((string) $series_key);
        if ($series_key === '' || !is_array($entry)) {
            continue;
        }

        $title = isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '';
        $tag = isset($entry['tag']) ? strtoupper(sanitize_text_field((string) $entry['tag'])) : '';
        $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
        $tag = trim((string) $tag);
        $source_post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;

        $label = $title !== '' ? $title : $series_key;
        if ($tag !== '') {
            $label .= ' [' . $tag . ']';
        }
        if ($source_post_id > 0) {
            $label .= ' — #' . $source_post_id . ' ' . get_the_title($source_post_id);
        } else {
            $label .= ' — ' . __('nicht zugeordnet', 'iss-programm');
        }

        $options[$series_key] = $label;
    }

    natcasesort($options);
    return $options;
}

function iss_programm_get_current_series_key_for_post($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $keys = function_exists('iss_occurrences_resolve_series_keys_for_source_post_id')
        ? iss_occurrences_resolve_series_keys_for_source_post_id($post_id)
        : [];

    if (is_array($keys) && !empty($keys)) {
        $keys = array_values(array_filter(array_map('trim', $keys)));
        sort($keys);
        return isset($keys[0]) ? (string) $keys[0] : '';
    }

    return '';
}

add_action('add_meta_boxes', function ($post_type, $post) {
    if ($post_type !== 'fuehrung') {
        return;
    }

    add_meta_box(
        'iss-programm-calendar-mapping',
        __('SuperSaaS-Reihe', 'iss-programm'),
        'iss_programm_render_fuehrung_mapping_metabox',
        'fuehrung',
        'side',
        'high'
    );
}, 20, 2);

function iss_programm_render_fuehrung_mapping_metabox($post) {
    if (!($post instanceof WP_Post)) {
        return;
    }

    $post_id = (int) $post->ID;
    $current_series_key = iss_programm_get_current_series_key_for_post($post_id);
    $options = iss_programm_get_mapping_series_options();

    wp_nonce_field('iss_programm_save_fuehrung_mapping', 'iss_programm_mapping_nonce');

    echo '<p><label for="iss_programm_series_key"><strong>' . esc_html__('Terminreihe', 'iss-programm') . '</strong></label></p>';
    echo '<select class="widefat" id="iss_programm_series_key" name="iss_programm_series_key">';
    echo '<option value="">' . esc_html__('— Keine Terminreihe —', 'iss-programm') . '</option>';
    foreach ($options as $series_key => $label) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($series_key),
            selected($current_series_key, $series_key, false),
            esc_html((string) $label)
        );
    }
    echo '</select>';

    echo '<p class="description" style="margin-top:8px;">'
        . esc_html__('Die Führung wird über eine SuperSaaS-Terminreihe mit der öffentlichen Occurrence-Projektion verbunden.', 'iss-programm')
        . '</p>';
}

add_action('save_post_fuehrung', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (!isset($_POST['iss_programm_mapping_nonce']) || !wp_verify_nonce((string) $_POST['iss_programm_mapping_nonce'], 'iss_programm_save_fuehrung_mapping')) {
        return;
    }

    if (!array_key_exists('iss_programm_series_key', $_POST)) {
        return;
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    $series_key = strtolower(sanitize_text_field((string) wp_unslash($_POST['iss_programm_series_key'])));
    $series_key = preg_replace('/[^a-z0-9:_-]+/', '', $series_key);
    $series_key = trim((string) $series_key);

    if (function_exists('iss_occurrences_clear_series_mapping_for_post')) {
        iss_occurrences_clear_series_mapping_for_post($post_id);
    }
    if (function_exists('iss_occurrences_clear_source_mapping_for_post')) {
        iss_occurrences_clear_source_mapping_for_post($post_id);
    }

    if ($series_key === '') {
        return;
    }

    $entry = function_exists('iss_occurrences_get_series_map_entry')
        ? iss_occurrences_get_series_map_entry($series_key)
        : null;
    if (!is_array($entry)) {
        return;
    }

    $title = isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '';
    $tag = isset($entry['tag']) ? strtoupper(sanitize_text_field((string) $entry['tag'])) : '';
    $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
    $tag = trim((string) $tag);
    $fallback_url = isset($entry['fallback_url']) ? esc_url_raw((string) $entry['fallback_url']) : '';

    if (function_exists('iss_occurrences_remember_series_mapping')) {
        iss_occurrences_remember_series_mapping($series_key, $post_id, 'fuehrung', $title, $tag, $fallback_url);
    }

    if ($tag !== '' && function_exists('iss_occurrences_remember_source_mapping')) {
        iss_occurrences_remember_source_mapping($tag, $fallback_url, $post_id, 'fuehrung');
    }

    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_supersaas_sync_occurrences();
    }
}, 20);
