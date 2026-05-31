<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('iss_programm_set_sync_notice')) {
    function iss_programm_set_sync_notice($type, $message) {
        $type = sanitize_key((string) $type);
        if (!in_array($type, ['success', 'warning', 'error'], true)) {
            $type = 'success';
        }

        $message = trim((string) $message);
        if ($message === '') {
            return;
        }

        set_transient('iss_programm_sync_notice', [
            'type' => $type,
            'message' => $message,
        ], 60);
    }
}

if (!function_exists('iss_programm_normalize_series_key')) {
    function iss_programm_normalize_series_key($series_key) {
        $series_key = strtolower(trim(sanitize_text_field((string) $series_key)));
        if ($series_key === '') {
            return '';
        }

        $series_key = preg_replace('/[^a-z0-9:_-]+/', '', $series_key);
        $series_key = trim((string) $series_key);
        return $series_key;
    }
}

if (!function_exists('iss_programm_get_fuehrung_ids_for_select')) {
    function iss_programm_get_fuehrung_ids_for_select() {
        return get_posts([
            'post_type' => 'fuehrung',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
        ]);
    }
}

add_action('admin_menu', function () {
    add_management_page(
        'Kalender-Sync',
        'Kalender-Sync',
        'manage_options',
        'iss-calendar-sync',
        'iss_programm_render_sync_page'
    );
});

add_action('admin_post_iss_calendar_sync', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_calendar_sync');

    if (!function_exists('iss_calendar_sync_supersaas_to_cpt')) {
        set_transient('iss_calendar_sync_result', [
            'created' => 0,
            'updated' => 0,
            'errors' => 1,
            'imported_unmapped' => 0,
            'preserved_title' => 0,
            'preserved_description' => 0,
            'error_message' => 'Sync module is unavailable.',
        ], 60);
        wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
        exit;
    }

    $result = iss_calendar_sync_supersaas_to_cpt();
    set_transient('iss_calendar_sync_result', $result, 60);

    wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
    exit;
});

add_action('admin_post_iss_programm_clear_series_mapping', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_programm_sync_mapping_action');

    $series_key = isset($_POST['series_key']) ? iss_programm_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    if ($series_key === '') {
        iss_programm_set_sync_notice('error', 'Zuordnung konnte nicht gelöst werden: ungültige Reihe.');
        wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
        exit;
    }

    $entry = function_exists('iss_programm_get_series_map_entry')
        ? iss_programm_get_series_map_entry($series_key)
        : null;
    $source_post_id = is_array($entry) ? (int) ($entry['source_post_id'] ?? 0) : 0;
    $tag = is_array($entry) ? strtoupper(sanitize_text_field((string) ($entry['tag'] ?? ''))) : '';
    $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
    $tag = trim((string) $tag);

    $cleared = function_exists('iss_programm_clear_series_mapping_for_key')
        ? iss_programm_clear_series_mapping_for_key($series_key)
        : false;

    if ($cleared && $tag !== '' && $source_post_id > 0 && function_exists('iss_programm_get_source_map_entry') && function_exists('iss_programm_clear_mapping_for_tag')) {
        $tag_entry = iss_programm_get_source_map_entry($tag);
        if (is_array($tag_entry) && (int) ($tag_entry['source_post_id'] ?? 0) === $source_post_id) {
            iss_programm_clear_mapping_for_tag($tag);
        }
    }

    if ($cleared) {
        iss_programm_set_sync_notice('success', sprintf('Zuordnung für Reihe %s wurde gelöst.', $series_key));
    } else {
        iss_programm_set_sync_notice('warning', sprintf('Keine Änderung für Reihe %s durchgeführt.', $series_key));
    }

    wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
    exit;
});

add_action('admin_post_iss_programm_set_series_mapping', function () {
    if (!current_user_can('manage_options')) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_programm_sync_mapping_action');

    $series_key = isset($_POST['series_key']) ? iss_programm_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $post_id = isset($_POST['source_post_id']) ? (int) $_POST['source_post_id'] : 0;

    if ($series_key === '') {
        iss_programm_set_sync_notice('error', 'Neu-Zuordnung fehlgeschlagen: ungültige Reihe.');
        wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
        exit;
    }

    if ($post_id <= 0) {
        iss_programm_set_sync_notice('error', 'Neu-Zuordnung fehlgeschlagen: bitte eine Führung auswählen.');
        wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
        exit;
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post) || $post->post_type !== 'fuehrung') {
        iss_programm_set_sync_notice('error', 'Neu-Zuordnung fehlgeschlagen: Zielobjekt ist keine Führung.');
        wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
        exit;
    }

    $entry = function_exists('iss_programm_get_series_map_entry')
        ? iss_programm_get_series_map_entry($series_key)
        : null;
    if (!is_array($entry)) {
        iss_programm_set_sync_notice('error', sprintf('Neu-Zuordnung fehlgeschlagen: Reihe %s wurde nicht gefunden.', $series_key));
        wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
        exit;
    }

    if (function_exists('iss_programm_clear_series_mapping_for_post')) {
        iss_programm_clear_series_mapping_for_post($post_id);
    }
    if (function_exists('iss_programm_clear_mapping_for_post')) {
        iss_programm_clear_mapping_for_post($post_id);
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

    iss_programm_set_sync_notice('success', sprintf('Reihe %s wurde auf Führung #%d gesetzt.', $series_key, $post_id));

    wp_safe_redirect(admin_url('tools.php?page=iss-calendar-sync'));
    exit;
});

function iss_programm_render_sync_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $result = get_transient('iss_calendar_sync_result');
    if ($result !== false) {
        delete_transient('iss_calendar_sync_result');
    }
    $notice = get_transient('iss_programm_sync_notice');
    if ($notice !== false) {
        delete_transient('iss_programm_sync_notice');
    }

    $series_map = function_exists('iss_programm_get_series_map') ? iss_programm_get_series_map() : [];
    $fuehrungen = iss_programm_get_fuehrung_ids_for_select();

    echo '<div class="wrap">';
    echo '<h1>Kalender-Sync</h1>';

    if (is_array($result)) {
        $created = (int) ($result['created'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $errors = (int) ($result['errors'] ?? 0);
        $imported_unmapped = (int) ($result['imported_unmapped'] ?? 0);
        $preserved_title = (int) ($result['preserved_title'] ?? 0);
        $preserved_description = (int) ($result['preserved_description'] ?? 0);
        $error_message = isset($result['error_message']) ? trim((string) $result['error_message']) : '';

        printf(
            '<div class="notice notice-success"><p>Sync abgeschlossen. Neu: %d, Aktualisiert: %d, Fehler: %d, Importiert (ohne Zuordnung): %d, Titel beibehalten: %d, Beschreibung beibehalten: %d.</p></div>',
            $created,
            $updated,
            $errors,
            $imported_unmapped,
            $preserved_title,
            $preserved_description
        );
        if ($error_message !== '') {
            echo '<div class="notice notice-error"><p>' . esc_html($error_message) . '</p></div>';
        }
    }

    if (is_array($notice)) {
        $notice_type = isset($notice['type']) ? sanitize_key((string) $notice['type']) : 'success';
        if (!in_array($notice_type, ['success', 'warning', 'error'], true)) {
            $notice_type = 'success';
        }
        $message = isset($notice['message']) ? trim((string) $notice['message']) : '';
        if ($message !== '') {
            echo '<div class="notice notice-' . esc_attr($notice_type) . '"><p>' . esc_html($message) . '</p></div>';
        }
    }

    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    echo '<input type="hidden" name="action" value="iss_calendar_sync" />';
    wp_nonce_field('iss_calendar_sync');
    submit_button('Jetzt synchronisieren');
    echo '</form>';

    echo '<h2>Reihen-Zuordnungen</h2>';
    if (empty($series_map)) {
        echo '<p>Noch keine Reihen erkannt. Bitte zuerst synchronisieren.</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Reihe</th><th>Schlüssel</th><th>Tag</th><th>Quelle</th><th>Fallback-URL</th><th>Zuletzt gesehen</th><th>Aktionen</th></tr></thead><tbody>';
        foreach ($series_map as $series_key => $entry) {
            if (!is_array($entry)) {
                continue;
            }

            $title = isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '';
            if ($title === '') {
                $title = $series_key;
            }
            $tag = isset($entry['tag']) ? strtoupper(sanitize_text_field((string) $entry['tag'])) : '';
            $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
            $tag = trim((string) $tag);
            $post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;
            $post_type = isset($entry['source_post_type']) ? sanitize_key((string) $entry['source_post_type']) : '';
            $fallback_url = isset($entry['fallback_url']) ? (string) $entry['fallback_url'] : '';
            $last_seen_at = isset($entry['last_seen_at']) ? (string) $entry['last_seen_at'] : '';

            $post_label = $post_id ? ('#' . $post_id . ' ' . get_the_title($post_id)) : '—';
            $post_edit = $post_id ? get_edit_post_link($post_id) : '';
            if ($post_edit) {
                $post_label = sprintf('<a href="%s">%s</a>', esc_url($post_edit), esc_html($post_label));
            } else {
                $post_label = esc_html($post_label);
            }
            if ($post_type !== '') {
                $post_label .= '<br><code>' . esc_html($post_type) . '</code>';
            }

            $assign_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:6px;align-items:center;">'
                . '<input type="hidden" name="action" value="iss_programm_set_series_mapping" />'
                . '<input type="hidden" name="series_key" value="' . esc_attr((string) $series_key) . '" />';
            ob_start();
            wp_nonce_field('iss_programm_sync_mapping_action');
            $assign_form .= (string) ob_get_clean();
            $assign_form .= '<select name="source_post_id">';
            $assign_form .= '<option value="">' . esc_html__('Führung wählen', 'iss-programm') . '</option>';
            foreach ($fuehrungen as $fuehrung_id) {
                $fuehrung_id = (int) $fuehrung_id;
                if ($fuehrung_id <= 0) {
                    continue;
                }
                $fuehrung_title = trim((string) get_the_title($fuehrung_id));
                if ($fuehrung_title === '') {
                    $fuehrung_title = '(ohne Titel)';
                }
                $assign_form .= sprintf(
                    '<option value="%d" %s>#%d %s</option>',
                    $fuehrung_id,
                    selected($post_id, $fuehrung_id, false),
                    $fuehrung_id,
                    esc_html($fuehrung_title)
                );
            }
            $assign_form .= '</select>';
            $assign_form .= '<button type="submit" class="button button-secondary">Neu zuordnen</button>';
            $assign_form .= '</form>';

            $clear_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:6px;">'
                . '<input type="hidden" name="action" value="iss_programm_clear_series_mapping" />'
                . '<input type="hidden" name="series_key" value="' . esc_attr((string) $series_key) . '" />';
            ob_start();
            wp_nonce_field('iss_programm_sync_mapping_action');
            $clear_form .= (string) ob_get_clean();
            $clear_form .= '<button type="submit" class="button">Zuordnung lösen</button>';
            $clear_form .= '</form>';

            printf(
                '<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s%s</td></tr>',
                esc_html($title),
                esc_html((string) $series_key),
                $tag !== '' ? esc_html($tag) : '—',
                $post_label,
                $fallback_url ? ('<a href="' . esc_url($fallback_url) . '" target="_blank" rel="noopener">link</a>') : '—',
                esc_html($last_seen_at),
                $assign_form,
                $clear_form
            );
        }
        echo '</tbody></table>';
    }

    echo '</div>';
}
