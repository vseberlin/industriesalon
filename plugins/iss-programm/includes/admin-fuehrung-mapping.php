<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_programm_get_mapping_series_options() {
    $options = [];
    $map = function_exists('iss_programm_get_series_map') ? iss_programm_get_series_map() : [];

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

function iss_programm_collect_series_keys_for_post($post_id, $tag = '') {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !function_exists('iss_programm_build_series_key')) {
        return [];
    }

    $series_keys = [];
    $series_seed_titles = [];
    $title = trim((string) get_the_title($post_id));
    if ($title !== '') {
        $series_seed_titles[] = $title;
        $series_seed_titles[] = preg_replace('/(?:\\s|-)*(tour|fuehrung|führung)$/iu', '', $title);
    }

    $post_slug = (string) get_post_field('post_name', $post_id);
    if ($post_slug !== '') {
        $slug_title = trim(str_replace(['-', '_'], ' ', $post_slug));
        if ($slug_title !== '') {
            $series_seed_titles[] = $slug_title;
        }

        $slug_reduced = preg_replace('/(?:-|_)?(tour|fuehrung|fuehrungen)$/iu', '', $post_slug);
        $slug_reduced = trim(str_replace(['-', '_'], ' ', (string) $slug_reduced));
        if ($slug_reduced !== '') {
            $series_seed_titles[] = $slug_reduced;
        }
    }

    foreach ($series_seed_titles as $seed_title) {
        $seed_title = trim((string) $seed_title);
        if ($seed_title === '') {
            continue;
        }

        $series_keys[] = iss_programm_build_series_key($seed_title, 'tour');
    }

    $tag = strtoupper(sanitize_text_field((string) $tag));
    if ($tag !== '' && function_exists('iss_programm_get_source_map_entry')) {
        $entry = iss_programm_get_source_map_entry($tag);
        if (is_array($entry) && !empty($entry['supersaas_title'])) {
            $mapped_title = trim((string) $entry['supersaas_title']);
            if ($mapped_title !== '') {
                $series_keys[] = iss_programm_build_series_key($mapped_title, 'tour');
            }
        }
    }

    $series_keys = array_values(array_unique(array_filter(array_map(static function ($value) {
        return trim((string) $value);
    }, $series_keys))));

    return $series_keys;
}

function iss_programm_get_current_series_key_for_post($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $keys = function_exists('iss_programm_get_series_keys_for_source_post_id')
        ? iss_programm_get_series_keys_for_source_post_id($post_id)
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
        __('Kalender-Zuordnung', 'iss-programm'),
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
    echo '<option value="">' . esc_html__('— Keine Zuordnung —', 'iss-programm') . '</option>';
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
        . esc_html__('Die Zuordnung erfolgt primär über Serien-Schlüssel aus importierten Kalenderdaten.', 'iss-programm')
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

    if (function_exists('iss_programm_clear_series_mapping_for_post')) {
        iss_programm_clear_series_mapping_for_post($post_id);
    }
    if (function_exists('iss_programm_clear_mapping_for_post')) {
        iss_programm_clear_mapping_for_post($post_id);
    }

    if ($series_key === '') {
        return;
    }

    $entry = function_exists('iss_programm_get_series_map_entry')
        ? iss_programm_get_series_map_entry($series_key)
        : null;
    if (!is_array($entry)) {
        return;
    }

    $title = isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '';
    $tag = isset($entry['tag']) ? strtoupper(sanitize_text_field((string) $entry['tag'])) : '';
    $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
    $tag = trim((string) $tag);
    $fallback_url = isset($entry['fallback_url']) ? esc_url_raw((string) $entry['fallback_url']) : '';

    if (function_exists('iss_programm_remember_series_mapping')) {
        iss_programm_remember_series_mapping($series_key, $post_id, 'fuehrung', $title, $tag, $fallback_url);
    }

    if ($tag !== '' && function_exists('iss_programm_remember_source_mapping')) {
        iss_programm_remember_source_mapping($tag, $fallback_url, $post_id, 'fuehrung');
    }

    if (function_exists('iss_programm_relink_series_to_post')) {
        iss_programm_relink_series_to_post($post_id, [$series_key], 'fuehrung');
    }
}, 20);
