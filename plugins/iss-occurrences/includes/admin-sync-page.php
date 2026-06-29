<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('iss_occurrences_set_sync_notice')) {
    function iss_occurrences_set_sync_notice($type, $message) {
        $type = sanitize_key((string) $type);
        if (!in_array($type, ['success', 'warning', 'error'], true)) {
            $type = 'success';
        }

        $message = trim((string) $message);
        if ($message === '') {
            return;
        }

        set_transient('iss_occurrences_sync_notice', [
            'type' => $type,
            'message' => $message,
        ], 60);
    }
}

if (!function_exists('iss_occurrences_sync_capability')) {
    function iss_occurrences_sync_capability() {
        return function_exists('iss_core_capability') ? iss_core_capability('sync') : 'manage_options';
    }
}

if (!function_exists('iss_occurrences_sync_admin_url')) {
    function iss_occurrences_sync_admin_url(): string {
        $parent = defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php';

        return admin_url($parent . '?page=iss-occurrences-sync');
    }
}

if (!function_exists('iss_occurrences_normalize_series_key')) {
    function iss_occurrences_normalize_series_key($series_key) {
        $series_key = strtolower(trim(sanitize_text_field((string) $series_key)));
        if ($series_key === '') {
            return '';
        }

        $series_key = preg_replace('/[^a-z0-9:_-]+/', '', $series_key);
        $series_key = trim((string) $series_key);
        return $series_key;
    }
}

if (!function_exists('iss_occurrences_get_fuehrung_ids_for_select')) {
    function iss_occurrences_get_fuehrung_ids_for_select() {
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

if (!function_exists('iss_occurrences_get_series_occurrence_summaries')) {
    function iss_occurrences_get_series_occurrence_summaries(array $series_keys): array {
        if (!function_exists('iss_occurrences_get_service')) {
            return [];
        }

        $series_keys = array_values(array_unique(array_filter(array_map('iss_occurrences_normalize_series_key', $series_keys))));
        if (empty($series_keys)) {
            return [];
        }

        $service = iss_occurrences_get_service();
        if (!method_exists($service, 'tables_exist') || !$service->tables_exist()) {
            return [];
        }

        global $wpdb;

        $table = $service->get_occurrences_table_name();
        $now = current_time('mysql');
        $placeholders = implode(',', array_fill(0, count($series_keys), '%s'));
        $params = array_merge(
            ['active', 'public', $now, 'active', 'public', $now, 'supersaas'],
            $series_keys
        );
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT series_key,
                    COUNT(*) AS total_rows,
                    SUM(CASE WHEN status = %s AND visibility = %s AND starts_at >= %s THEN 1 ELSE 0 END) AS future_active_rows,
                    MIN(CASE WHEN status = %s AND visibility = %s AND starts_at >= %s THEN starts_at ELSE NULL END) AS next_start
                FROM {$table}
                WHERE origin = %s
                  AND series_key IN ({$placeholders})
                GROUP BY series_key",
                $params
            ),
            ARRAY_A
        );

        $summaries = [];
        foreach ((array) $rows as $row) {
            $series_key = isset($row['series_key']) ? iss_occurrences_normalize_series_key((string) $row['series_key']) : '';
            if ($series_key === '') {
                continue;
            }

            $summaries[$series_key] = [
                'total_rows' => (int) ($row['total_rows'] ?? 0),
                'future_active_rows' => (int) ($row['future_active_rows'] ?? 0),
                'next_start' => isset($row['next_start']) ? (string) $row['next_start'] : '',
            ];
        }

        return $summaries;
    }
}

if (!function_exists('iss_occurrences_format_series_occurrence_summary')) {
    function iss_occurrences_format_series_occurrence_summary(array $summary): string {
        $total = (int) ($summary['total_rows'] ?? 0);
        $future = (int) ($summary['future_active_rows'] ?? 0);
        $next_start = isset($summary['next_start']) ? trim((string) $summary['next_start']) : '';

        if ($total <= 0) {
            return esc_html__('0 Termine', 'iss-occurrences');
        }

        $details = sprintf(_n('%d gesamt', '%d gesamt', $total, 'iss-occurrences'), $total);
        if ($next_start !== '') {
            $details .= ' · ' . sprintf(
                esc_html__('nächster %s', 'iss-occurrences'),
                mysql2date('d.m.Y H:i', $next_start)
            );
        }

        return sprintf(
            '%s<br><code>%s</code>',
            esc_html(sprintf(_n('%d kommender', '%d kommende', $future, 'iss-occurrences'), $future)),
            esc_html($details)
        );
    }
}

add_action('admin_menu', function () {
    add_submenu_page(
        defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : 'tools.php',
        'SuperSaaS-Termin-Sync',
        'SuperSaaS-Termin-Sync',
        iss_occurrences_sync_capability(),
        'iss-occurrences-sync',
        'iss_occurrences_render_sync_page'
    );
});

add_action('admin_post_iss_occurrences_sync', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync');

    if (!function_exists('iss_supersaas_sync_occurrences')) {
        set_transient('iss_occurrences_sync_result', [
            'created' => 0,
            'updated' => 0,
            'errors' => 1,
            'imported_unmapped' => 0,
            'skipped_unlinked' => 0,
            'inactivated' => 0,
            'purged_inactive' => 0,
            'past_reactivated' => 0,
            'metadata_backfilled' => 0,
            'source_reconciled' => 0,
            'source_cleared' => 0,
            'series_pruned' => 0,
            'error_message' => 'SuperSaaS sync module is unavailable.',
        ], 60);
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $result = iss_supersaas_sync_occurrences();
    set_transient('iss_occurrences_sync_result', $result, 60);
    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_sync', [
            'capability' => iss_occurrences_sync_capability(),
            'result' => ((int) ($result['errors'] ?? 0)) > 0 ? 'failed' : 'completed',
            'created' => (int) ($result['created'] ?? 0),
            'updated' => (int) ($result['updated'] ?? 0),
        ]);
    }

    wp_safe_redirect(iss_occurrences_sync_admin_url());
    exit;
});

add_action('admin_post_iss_occurrences_clear_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    if ($series_key === '') {
        iss_occurrences_set_sync_notice('error', 'Zuordnung konnte nicht gelöst werden: ungültige Reihe.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $cleared = function_exists('iss_occurrences_clear_series_source_for_key')
        ? iss_occurrences_clear_series_source_for_key($series_key)
        : false;

    if ($cleared) {
        iss_occurrences_set_sync_notice('success', sprintf('Zuordnung für Reihe %s wurde gelöst.', $series_key));
    } else {
        iss_occurrences_set_sync_notice('warning', sprintf('Keine Änderung für Reihe %s durchgeführt.', $series_key));
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_clear_series_source', [
            'capability' => iss_occurrences_sync_capability(),
            'job_id' => $series_key,
            'result' => $cleared ? 'completed' : 'failed',
        ]);
    }

    wp_safe_redirect(iss_occurrences_sync_admin_url());
    exit;
});

add_action('admin_post_iss_occurrences_set_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $post_id = isset($_POST['source_post_id']) ? (int) $_POST['source_post_id'] : 0;

    if ($series_key === '') {
        iss_occurrences_set_sync_notice('error', 'Neu-Zuordnung fehlgeschlagen: ungültige Reihe.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    if ($post_id <= 0) {
        iss_occurrences_set_sync_notice('error', 'Neu-Zuordnung fehlgeschlagen: bitte eine Führung auswählen.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post) || $post->post_type !== 'fuehrung') {
        iss_occurrences_set_sync_notice('error', 'Neu-Zuordnung fehlgeschlagen: Zielobjekt ist keine Führung.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $entry = function_exists('iss_occurrences_get_series_source')
        ? iss_occurrences_get_series_source($series_key)
        : null;
    if (!is_array($entry)) {
        iss_occurrences_set_sync_notice('error', sprintf('Neu-Zuordnung fehlgeschlagen: Reihe %s wurde nicht gefunden.', $series_key));
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    if (function_exists('iss_occurrences_clear_series_source_for_post')) {
        iss_occurrences_clear_series_source_for_post($post_id);
    }

    $title = isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '';
    $tag = isset($entry['tag']) ? strtoupper(sanitize_text_field((string) $entry['tag'])) : '';
    $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
    $tag = trim((string) $tag);
    $fallback_url = isset($entry['fallback_url']) ? esc_url_raw((string) $entry['fallback_url']) : '';

    if (function_exists('iss_occurrences_remember_series_source')) {
        iss_occurrences_remember_series_source($series_key, $post_id, 'fuehrung', $title, $tag, $fallback_url);
    }
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_supersaas_sync_occurrences();
    }

    iss_occurrences_set_sync_notice('success', sprintf('Reihe %s wurde auf Führung #%d gesetzt.', $series_key, $post_id));

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_set_series_source', [
            'capability' => iss_occurrences_sync_capability(),
            'object_ids' => [$post_id],
            'job_id' => $series_key,
            'result' => 'completed',
        ]);
    }

    wp_safe_redirect(iss_occurrences_sync_admin_url());
    exit;
});

function iss_occurrences_render_sync_page() {
    if (function_exists('iss_require_cap')) {
        iss_require_cap(iss_occurrences_sync_capability());
    } elseif (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.', 403);
    }

    $result = get_transient('iss_occurrences_sync_result');
    if ($result !== false) {
        delete_transient('iss_occurrences_sync_result');
    }
    $notice = get_transient('iss_occurrences_sync_notice');
    if ($notice !== false) {
        delete_transient('iss_occurrences_sync_notice');
    }

    $series_sources = function_exists('iss_occurrences_get_series_sources') ? iss_occurrences_get_series_sources() : [];
    $fuehrungen = iss_occurrences_get_fuehrung_ids_for_select();
    $series_occurrences = iss_occurrences_get_series_occurrence_summaries(array_keys($series_sources));

    echo '<div class="wrap">';
    echo '<h1>SuperSaaS-Termin-Sync</h1>';

    if (is_array($result)) {
        $created = (int) ($result['created'] ?? 0);
        $updated = (int) ($result['updated'] ?? 0);
        $errors = (int) ($result['errors'] ?? 0);
        $imported_unmapped = (int) ($result['imported_unmapped'] ?? 0);
        $skipped_unlinked = (int) ($result['skipped_unlinked'] ?? 0);
        $inactivated = (int) ($result['inactivated'] ?? 0);
        $purged_inactive = (int) ($result['purged_inactive'] ?? 0);
        $past_reactivated = (int) ($result['past_reactivated'] ?? 0);
        $metadata_backfilled = (int) ($result['metadata_backfilled'] ?? 0);
        $source_reconciled = (int) ($result['source_reconciled'] ?? 0);
        $source_cleared = (int) ($result['source_cleared'] ?? 0);
        $series_pruned = (int) ($result['series_pruned'] ?? 0);
        $error_message = isset($result['error_message']) ? trim((string) $result['error_message']) : '';

        $sync_message = sprintf(
            'SuperSaaS-Sync abgeschlossen. Neu: %d, Aktualisiert: %d, Fehler: %d, Slots ohne bekannten Tag: %d, Nicht importiert: %d, Inaktiviert: %d, Bereinigt: %d, Vergangene reaktiviert: %d, Metadaten ergänzt: %d, Quellen korrigiert: %d, Quellen geleert: %d, Leere Reihen entfernt: %d.',
            $created,
            $updated,
            $errors,
            $imported_unmapped,
            $skipped_unlinked,
            $inactivated,
            $purged_inactive,
            $past_reactivated,
            $metadata_backfilled,
            $source_reconciled,
            $source_cleared,
            $series_pruned
        );
        echo '<div class="notice notice-success"><p>' . esc_html($sync_message) . '</p></div>';
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
    echo '<input type="hidden" name="action" value="iss_occurrences_sync" />';
    wp_nonce_field('iss_occurrences_sync');
    submit_button('Jetzt synchronisieren');
    echo '</form>';

    echo '<h2>Führungs-Terminreihen</h2>';
    if (empty($series_sources)) {
        echo '<p>Noch keine Reihen erkannt. Bitte zuerst synchronisieren.</p>';
    } else {
        echo '<table class="widefat striped"><thead><tr><th>Reihe</th><th>Schlüssel</th><th>Tag</th><th>Quelle</th><th>Termine</th><th>Buchungslink</th><th>Zuletzt gesehen</th><th>Aktionen</th></tr></thead><tbody>';
        $allowed_admin_html = [
            'a' => [
                'href' => true,
                'rel' => true,
                'target' => true,
            ],
            'br' => [],
            'button' => [
                'class' => true,
                'type' => true,
            ],
            'code' => [],
            'form' => [
                'action' => true,
                'method' => true,
                'style' => true,
            ],
            'input' => [
                'id' => true,
                'name' => true,
                'type' => true,
                'value' => true,
            ],
            'option' => [
                'selected' => true,
                'value' => true,
            ],
            'select' => [
                'name' => true,
            ],
        ];

        foreach ($series_sources as $series_key => $entry) {
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
            $occurrence_label = iss_occurrences_format_series_occurrence_summary($series_occurrences[$series_key] ?? []);

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

            $fallback_label = $fallback_url
                ? sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($fallback_url), esc_html__('link', 'iss-occurrences'))
                : esc_html('—');

            $assign_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:6px;align-items:center;">'
                . '<input type="hidden" name="action" value="iss_occurrences_set_series_source" />'
                . '<input type="hidden" name="series_key" value="' . esc_attr((string) $series_key) . '" />';
            ob_start();
            wp_nonce_field('iss_occurrences_sync_series_source_action');
            $assign_form .= (string) ob_get_clean();
            $assign_form .= '<select name="source_post_id">';
            $assign_form .= '<option value="">' . esc_html__('Führung wählen', 'iss-occurrences') . '</option>';
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
                . '<input type="hidden" name="action" value="iss_occurrences_clear_series_source" />'
                . '<input type="hidden" name="series_key" value="' . esc_attr((string) $series_key) . '" />';
            ob_start();
            wp_nonce_field('iss_occurrences_sync_series_source_action');
            $clear_form .= (string) ob_get_clean();
            $clear_form .= '<button type="submit" class="button">Zuordnung lösen</button>';
            $clear_form .= '</form>';

            printf(
                '<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s%s</td></tr>',
                esc_html($title),
                esc_html((string) $series_key),
                $tag !== '' ? esc_html($tag) : '—',
                wp_kses($post_label, $allowed_admin_html),
                wp_kses($occurrence_label, $allowed_admin_html),
                wp_kses($fallback_label, $allowed_admin_html),
                esc_html($last_seen_at),
                wp_kses($assign_form, $allowed_admin_html),
                wp_kses($clear_form, $allowed_admin_html)
            );
        }
        echo '</tbody></table>';
    }

    echo '</div>';
}
