<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_occurrences_get_series_source_options() {
    $options = [];
    $series_sources = function_exists('iss_occurrences_get_series_sources') ? iss_occurrences_get_series_sources() : [];

    if (!is_array($series_sources) || empty($series_sources)) {
        return $options;
    }

    foreach ($series_sources as $series_key => $entry) {
        $series_key = trim((string) $series_key);
        if (strpos($series_key, 'tour:') !== 0 || !is_array($entry)) {
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
            $label .= ' — ' . __('nicht zugeordnet', 'iss-occurrences');
        }

        $options[$series_key] = $label;
    }

    natcasesort($options);
    return $options;
}

function iss_occurrences_get_current_series_key_for_post($post_id) {
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
        'iss-occurrences-calendar-mapping',
        __('Termine aus SuperSaaS', 'iss-occurrences'),
        'iss_occurrences_render_fuehrung_mapping_metabox',
        'fuehrung',
        'side',
        'high'
    );
}, 20, 2);

function iss_occurrences_render_fuehrung_mapping_metabox($post) {
    if (!($post instanceof WP_Post)) {
        return;
    }

    $post_id = (int) $post->ID;
    $current_series_key = iss_occurrences_get_current_series_key_for_post($post_id);
    $options = iss_occurrences_get_series_source_options();

    $linked = iss_occurrences_resolve_series_keys_for_source_post_id($post_id);
    if (count($linked) > 1) {
        echo '<p>Diese Führung ist mit mehreren Terminreihen verbunden:</p><ul>';
        foreach ($linked as $key) { echo '<li>' . esc_html($options[$key] ?? $key) . '</li>'; }
        echo '</ul><p>Bitte jede Reihe einzeln in der Terminverwaltung prüfen. Das Speichern dieses Beitrags verändert diese Zuordnungen nicht.</p>';
        if (current_user_can(iss_occurrences_sync_capability())) {
            echo '<p><a href="' . esc_url(add_query_arg(['view' => 'series', 'kind' => 'tours'], iss_occurrences_sync_admin_url())) . '">Terminreihen bearbeiten</a></p>';
        }
        return;
    }

    wp_nonce_field('iss_occurrences_save_fuehrung_series_source', 'iss_occurrences_series_source_nonce');

    echo '<p class="description">'
        . esc_html__('SuperSaaS liefert die Termine dieser Führung. Plätze und Buchungen werden in WordPress verwaltet.', 'iss-occurrences')
        . '</p>';

    echo '<p><label for="iss_occurrences_series_key"><strong>' . esc_html__('Terminreihe', 'iss-occurrences') . '</strong></label></p>';
    echo '<select class="widefat" id="iss_occurrences_series_key" name="iss_occurrences_series_key">';
    echo '<option value="">' . esc_html__('— Keine Terminreihe —', 'iss-occurrences') . '</option>';
    foreach ($options as $series_key => $label) {
        printf(
            '<option value="%s" %s>%s</option>',
            esc_attr($series_key),
            selected($current_series_key, $series_key, false),
            esc_html((string) $label)
        );
    }
    echo '</select>';
    echo '<input type="hidden" name="iss_occurrences_previous_series_key" value="' . esc_attr($current_series_key) . '">';
    echo '<p><label><input type="checkbox" name="iss_occurrences_confirm_change" value="1"> Änderung einer bestehenden Zuordnung bestätigen</label></p>';
    if (current_user_can(iss_occurrences_sync_capability())) {
        echo '<p><a href="' . esc_url(add_query_arg(['view' => 'series', 'kind' => 'tours'], iss_occurrences_sync_admin_url())) . '">Terminreihen und Abgleich prüfen</a></p>';
    }

    echo '<p class="description">'
        . esc_html__('Die Zuordnung gilt für die gesamte Terminreihe. Änderungen werden mit dem Beitrag gespeichert und anschließend abgeglichen.', 'iss-occurrences')
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

    if (!isset($_POST['iss_occurrences_series_source_nonce']) || !wp_verify_nonce((string) $_POST['iss_occurrences_series_source_nonce'], 'iss_occurrences_save_fuehrung_series_source')) {
        return;
    }

    if (!array_key_exists('iss_occurrences_series_key', $_POST)) {
        return;
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return;
    }

    if (count(iss_occurrences_resolve_series_keys_for_source_post_id($post_id)) > 1) {
        iss_occurrences_set_sync_notice('error', 'Diese Führung hat mehrere Terminreihen. Bitte die Zuordnungen einzeln in der Terminverwaltung prüfen.');
        return;
    }

    $series_key = strtolower(sanitize_text_field((string) wp_unslash($_POST['iss_occurrences_series_key'])));
    $series_key = preg_replace('/[^a-z0-9:_-]+/', '', $series_key);
    $series_key = trim((string) $series_key);

    $current = iss_occurrences_get_current_series_key_for_post($post_id);
    $previous = sanitize_text_field(wp_unslash((string) ($_POST['iss_occurrences_previous_series_key'] ?? '')));
    if ($current !== $previous) {
        iss_occurrences_set_sync_notice('error', 'Terminzuordnung inzwischen geändert. Bitte erneut prüfen.');
        return;
    }
    if ($current === $series_key) { return; }
    $confirmed = !empty($_POST['iss_occurrences_confirm_change']);
    if ($current !== '' && !$confirmed) {
        iss_occurrences_set_sync_notice('error', 'Terminzuordnung nicht geändert: Bitte die Änderung der bestehenden Reihe bestätigen.');
        return;
    }
    if ($series_key === '') {
        if ($current !== '') {
            if (!iss_occurrences_clear_series_source_for_key($current)) {
                iss_occurrences_set_sync_notice('error', 'Terminzuordnung konnte nicht gelöst werden. Bitte erneut prüfen.');
                return;
            }
            iss_occurrences_get_service()->set_supersaas_slots_series_state($current, 0, '', 'unmapped', 'unreviewed');
            iss_occurrences_run_admin_sync();
        }
        return;
    }
    // Validate the selection before touching the current mapping.
    $entry = iss_occurrences_get_series_source($series_key);
    if (strpos($series_key, 'tour:') !== 0 || !is_array($entry)) {
        iss_occurrences_set_sync_notice('error', 'Terminzuordnung nicht geändert: Ungültige Führungsreihe.');
        return;
    }
    if ((int) ($entry['source_post_id'] ?? 0) > 0 && (int) $entry['source_post_id'] !== $post_id) {
        iss_occurrences_set_sync_notice('error', 'Diese Reihe gehört bereits zu einer anderen Führung. Bitte die Zuordnung in der Terminverwaltung prüfen.');
        return;
    }
    if ($current !== '') {
        iss_occurrences_set_sync_notice('error', 'Bitte die bisherige Reihe zuerst mit „Keine Terminreihe“ lösen und speichern. Danach die neue Reihe zuordnen.');
        return;
    }
    $title = isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '';
    $tag = isset($entry['tag']) ? strtoupper(sanitize_text_field((string) $entry['tag'])) : '';
    $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
    $tag = trim((string) $tag);
    $fallback_url = isset($entry['fallback_url']) ? esc_url_raw((string) $entry['fallback_url']) : '';

    if (function_exists('iss_occurrences_remember_series_source')) {
        if (!iss_occurrences_remember_series_source($series_key, $post_id, 'fuehrung', $title, $tag, $fallback_url)) {
            iss_occurrences_set_sync_notice('error', 'Terminzuordnung konnte nicht gespeichert werden.');
            return;
        }
        iss_occurrences_get_service()->set_supersaas_slots_series_state($series_key, $post_id, 'fuehrung', 'mapped', 'mapped');
    }

    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_occurrences_run_admin_sync();
    }
}, 20);

add_action('admin_notices', static function (): void {
    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'fuehrung') { return; }
    $key = 'iss_occurrences_sync_notice_' . get_current_user_id();
    $notice = get_transient($key);
    if (is_array($notice)) {
        delete_transient($key);
        echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>' . esc_html($notice['message']) . '</p></div>';
    }
    $key = 'iss_occurrences_sync_result_' . get_current_user_id();
    $result = get_transient($key);
    if (is_array($result)) {
        delete_transient($key);
        $notice = iss_occurrences_sync_result_notice($result);
        echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>' . esc_html($notice['message']) . '</p></div>';
    }
});
