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

if (!function_exists('iss_occurrences_get_veranstaltung_ids_for_select')) {
    function iss_occurrences_get_veranstaltung_ids_for_select() {
        $post_type = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE') ? ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE : 'veranstaltung';

        return get_posts([
            'post_type' => $post_type,
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

if (!function_exists('iss_occurrences_sync_request_args')) {
    function iss_occurrences_sync_request_args(): array {
        return [
            'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
            'schedule_key' => isset($_GET['schedule_key']) ? sanitize_key(wp_unslash($_GET['schedule_key'])) : '',
            'match_state' => isset($_GET['match_state']) ? sanitize_key(wp_unslash($_GET['match_state'])) : 'active',
            'status' => isset($_GET['slot_status']) ? sanitize_key(wp_unslash($_GET['slot_status'])) : '',
            'date_scope' => isset($_GET['date_scope']) ? sanitize_key(wp_unslash($_GET['date_scope'])) : 'future',
            'orderby' => isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'starts_at',
            'order' => isset($_GET['order']) && strtoupper((string) wp_unslash($_GET['order'])) === 'DESC' ? 'DESC' : 'ASC',
            'paged' => isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1,
        ];
    }
}

if (!function_exists('iss_occurrences_sync_sort_url')) {
    function iss_occurrences_sync_sort_url(string $orderby, array $args): string {
        $current_orderby = isset($args['orderby']) ? (string) $args['orderby'] : 'starts_at';
        $current_order = isset($args['order']) ? strtoupper((string) $args['order']) : 'ASC';
        $next_order = ($current_orderby === $orderby && $current_order === 'ASC') ? 'DESC' : 'ASC';

        return esc_url(add_query_arg([
            'page' => 'iss-occurrences-sync',
            's' => $args['search'] ?? '',
            'schedule_key' => $args['schedule_key'] ?? '',
            'match_state' => $args['match_state'] ?? '',
            'slot_status' => $args['status'] ?? '',
            'date_scope' => $args['date_scope'] ?? 'future',
            'orderby' => $orderby,
            'order' => $next_order,
            'paged' => 1,
        ], admin_url(defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php')));
    }
}

if (!function_exists('iss_occurrences_sync_state_label')) {
    function iss_occurrences_sync_state_label(string $value): string {
        $labels = [
            'projected' => 'projiziert',
            'seen' => 'gesehen',
            'skipped' => 'nicht importiert',
            'cancelled' => 'abgesagt',
            'mapped' => 'zugeordnet',
            'unmapped' => 'unzugeordnet',
            'ignored' => 'ignoriert',
            'invalid_source' => 'Quelle ungültig',
        ];

        return $labels[$value] ?? ($value !== '' ? $value : '—');
    }
}

if (!function_exists('iss_occurrences_sync_slot_contains_repair_cafe')) {
    function iss_occurrences_sync_slot_contains_repair_cafe(array $slot_row): bool {
        $text = implode("\n", [
            (string) ($slot_row['raw_title'] ?? ''),
            (string) ($slot_row['clean_title'] ?? ''),
            (string) ($slot_row['description'] ?? ''),
        ]);
        $text = strtolower(remove_accents($text));

        return (bool) preg_match('/repair[\s-]*cafe/', $text);
    }
}

if (!function_exists('iss_occurrences_sync_slot_title')) {
    function iss_occurrences_sync_slot_title(array $slot_row): string {
        $title = trim(wp_strip_all_tags((string) ($slot_row['raw_title'] ?? '')));
        if ($title !== '') {
            return mb_strlen($title) > 120 ? mb_substr($title, 0, 117) . '...' : $title;
        }

        $description = trim(wp_strip_all_tags((string) ($slot_row['description'] ?? '')));
        if ($description !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $description);
            $title = trim((string) ($lines[0] ?? ''));
            if ($title !== '') {
                return mb_strlen($title) > 120 ? mb_substr($title, 0, 117) . '...' : $title;
            }
        }

        return 'SuperSaaS-Termin';
    }
}

if (!function_exists('iss_occurrences_sync_slot_excerpt')) {
    function iss_occurrences_sync_slot_excerpt(array $slot_row): string {
        $description = trim(wp_strip_all_tags((string) ($slot_row['description'] ?? '')));
        if ($description === '') {
            return '';
        }

        return wp_trim_words($description, 45, '...');
    }
}

if (!function_exists('iss_occurrences_sync_slot_landing_target_url')) {
    function iss_occurrences_sync_slot_landing_target_url(array $slot_row): string {
        if (!iss_occurrences_sync_slot_contains_repair_cafe($slot_row)) {
            return '';
        }

        $page = get_page_by_path('repair-cafe');
        if ($page instanceof WP_Post && $page->post_status === 'publish') {
            $url = get_permalink($page);
            return is_string($url) ? esc_url_raw($url) : '';
        }

        return esc_url_raw(home_url('/repair-cafe/'));
    }
}

if (!function_exists('iss_occurrences_sync_slot_semantic_key')) {
    function iss_occurrences_sync_slot_semantic_key(array $slot_row): string {
        return iss_occurrences_sync_slot_contains_repair_cafe($slot_row) ? 'repair-cafe' : '';
    }
}

if (!function_exists('iss_occurrences_sync_event_series_title')) {
    function iss_occurrences_sync_event_series_title(string $series_key, array $summary = []): string {
        $series_key = iss_occurrences_normalize_series_key($series_key);
        if ($series_key === 'event:repair-cafe') {
            return 'Repair-Café';
        }

        $title = trim(wp_strip_all_tags((string) ($summary['clean_title'] ?? '')));
        if ($title !== '') {
            return $title;
        }

        $title = preg_replace('/^event:/', '', $series_key);
        $title = str_replace(['-', '_'], ' ', (string) $title);
        $title = trim($title);

        return $title !== '' ? mb_convert_case($title, MB_CASE_TITLE, 'UTF-8') : 'Veranstaltungsreihe';
    }
}

if (!function_exists('iss_occurrences_sync_event_series_landing_target_url')) {
    function iss_occurrences_sync_event_series_landing_target_url(string $series_key): string {
        return iss_occurrences_normalize_series_key($series_key) === 'event:repair-cafe'
            ? iss_occurrences_sync_slot_landing_target_url([
                'raw_title' => 'Repair-Café',
                'clean_title' => 'Repair-Café',
                'description' => '',
            ])
            : '';
    }
}

if (!function_exists('iss_occurrences_sync_get_event_series_summaries')) {
    function iss_occurrences_sync_get_event_series_summaries(): array {
        if (!function_exists('iss_occurrences_get_service')) {
            return [];
        }

        $service = iss_occurrences_get_service();
        if (!method_exists($service, 'supersaas_slots_table_exists') || !$service->supersaas_slots_table_exists()) {
            return [];
        }

        global $wpdb;

        $table = $service->get_supersaas_slots_table_name();
        $now = current_time('mysql');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT series_key,
                    MIN(NULLIF(clean_title, '')) AS clean_title,
                    MIN(CASE WHEN description <> '' THEN description ELSE NULL END) AS sample_description,
                    COUNT(*) AS total_slots,
                    SUM(CASE WHEN starts_at >= %s THEN 1 ELSE 0 END) AS future_slots,
                    MIN(CASE WHEN starts_at >= %s THEN starts_at ELSE NULL END) AS next_start,
                    SUM(CASE WHEN match_state = 'mapped' THEN 1 ELSE 0 END) AS mapped_slots,
                    SUM(CASE WHEN match_state = 'ignored' THEN 1 ELSE 0 END) AS ignored_slots,
                    MAX(last_seen_at) AS last_seen_at
                FROM {$table}
                WHERE schedule_key = %s
                  AND series_key LIKE %s
                GROUP BY series_key
                ORDER BY series_key ASC",
                $now,
                $now,
                'salonbelegung',
                'event:%'
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
                'series_key' => $series_key,
                'clean_title' => trim((string) ($row['clean_title'] ?? '')),
                'sample_description' => trim((string) ($row['sample_description'] ?? '')),
                'total_slots' => (int) ($row['total_slots'] ?? 0),
                'future_slots' => (int) ($row['future_slots'] ?? 0),
                'next_start' => isset($row['next_start']) ? (string) $row['next_start'] : '',
                'mapped_slots' => (int) ($row['mapped_slots'] ?? 0),
                'ignored_slots' => (int) ($row['ignored_slots'] ?? 0),
                'last_seen_at' => isset($row['last_seen_at']) ? (string) $row['last_seen_at'] : '',
            ];
        }

        uasort($summaries, static function (array $a, array $b): int {
            $a_next = trim((string) ($a['next_start'] ?? ''));
            $b_next = trim((string) ($b['next_start'] ?? ''));
            if ($a_next === '' && $b_next !== '') {
                return 1;
            }
            if ($a_next !== '' && $b_next === '') {
                return -1;
            }
            if ($a_next !== '' && $b_next !== '' && $a_next !== $b_next) {
                return strcmp($a_next, $b_next);
            }

            return strcmp((string) ($a['series_key'] ?? ''), (string) ($b['series_key'] ?? ''));
        });

        return $summaries;
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

add_action('admin_post_iss_occurrences_ignore_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    if ($series_key === '') {
        iss_occurrences_set_sync_notice('error', 'Ignorieren fehlgeschlagen: ungültige Reihe.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $ignored = false;
    if (function_exists('iss_occurrences_get_service') && method_exists(iss_occurrences_get_service(), 'set_series_review_state')) {
        $ignored = iss_occurrences_get_service()->set_series_review_state($series_key, 'ignored');
    }
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_supersaas_sync_occurrences();
    }

    if ($ignored) {
        iss_occurrences_set_sync_notice('success', sprintf('Reihe %s wird ignoriert.', $series_key));
    } else {
        iss_occurrences_set_sync_notice('warning', sprintf('Keine Änderung für Reihe %s durchgeführt.', $series_key));
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_ignore_series_source', [
            'capability' => iss_occurrences_sync_capability(),
            'job_id' => $series_key,
            'result' => $ignored ? 'completed' : 'failed',
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

add_action('admin_post_iss_occurrences_set_event_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $post_id = isset($_POST['source_post_id']) ? absint($_POST['source_post_id']) : 0;
    if ($series_key === '' || strpos($series_key, 'event:') !== 0) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltungsreihe konnte nicht zugeordnet werden: ungültige Reihe.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }
    if ($post_id <= 0) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltungsreihe konnte nicht zugeordnet werden: bitte eine Veranstaltung auswählen.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $post_type = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE') ? ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE : 'veranstaltung';
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== $post_type) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltungsreihe konnte nicht zugeordnet werden: Zielobjekt ist keine Veranstaltung.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $summaries = iss_occurrences_sync_get_event_series_summaries();
    $summary = is_array($summaries[$series_key] ?? null) ? $summaries[$series_key] : [];
    $title = iss_occurrences_sync_event_series_title($series_key, $summary);
    $fallback_url = iss_occurrences_sync_event_series_landing_target_url($series_key);
    if ($fallback_url !== '' && trim((string) get_post_meta($post_id, 'iss_timeline_target_url', true)) === '') {
        update_post_meta($post_id, 'iss_timeline_target_url', $fallback_url);
    }

    $mapped = function_exists('iss_occurrences_remember_series_source')
        ? iss_occurrences_remember_series_source($series_key, $post_id, $post_type, $title, '', $fallback_url)
        : false;
    $service = function_exists('iss_occurrences_get_service') ? iss_occurrences_get_service() : null;
    if ($mapped && is_object($service) && method_exists($service, 'set_supersaas_slots_series_state')) {
        $service->set_supersaas_slots_series_state($series_key, $post_id, $post_type, 'mapped', 'mapped');
    }
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_supersaas_sync_occurrences();
    }

    if ($mapped) {
        iss_occurrences_set_sync_notice('success', sprintf('Veranstaltungsreihe %s wurde auf Veranstaltung #%d gesetzt.', $series_key, $post_id));
    } else {
        iss_occurrences_set_sync_notice('warning', sprintf('Keine Änderung für Veranstaltungsreihe %s durchgeführt.', $series_key));
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_set_event_series_source', [
            'capability' => iss_occurrences_sync_capability(),
            'object_ids' => [$post_id],
            'job_id' => $series_key,
            'result' => $mapped ? 'completed' : 'failed',
        ]);
    }

    wp_safe_redirect(iss_occurrences_sync_admin_url());
    exit;
});

add_action('admin_post_iss_occurrences_clear_event_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    if ($series_key === '' || strpos($series_key, 'event:') !== 0) {
        iss_occurrences_set_sync_notice('error', 'Zuordnung konnte nicht gelöst werden: ungültige Veranstaltungsreihe.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $cleared = function_exists('iss_occurrences_clear_series_source_for_key')
        ? iss_occurrences_clear_series_source_for_key($series_key)
        : false;
    $service = function_exists('iss_occurrences_get_service') ? iss_occurrences_get_service() : null;
    if (is_object($service) && method_exists($service, 'set_supersaas_slots_series_state')) {
        $service->set_supersaas_slots_series_state($series_key, 0, '', 'unmapped', 'unreviewed');
    }
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_supersaas_sync_occurrences();
    }

    if ($cleared) {
        iss_occurrences_set_sync_notice('success', sprintf('Zuordnung für Veranstaltungsreihe %s wurde gelöst.', $series_key));
    } else {
        iss_occurrences_set_sync_notice('warning', sprintf('Keine Änderung für Veranstaltungsreihe %s durchgeführt.', $series_key));
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_clear_event_series_source', [
            'capability' => iss_occurrences_sync_capability(),
            'job_id' => $series_key,
            'result' => $cleared ? 'completed' : 'failed',
        ]);
    }

    wp_safe_redirect(iss_occurrences_sync_admin_url());
    exit;
});

add_action('admin_post_iss_occurrences_set_event_series_review_state', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $review_state = isset($_POST['review_state']) ? sanitize_key(wp_unslash($_POST['review_state'])) : '';
    if ($series_key === '' || strpos($series_key, 'event:') !== 0 || !in_array($review_state, ['ignored', 'unreviewed'], true)) {
        iss_occurrences_set_sync_notice('error', 'Status der Veranstaltungsreihe konnte nicht geändert werden.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $summaries = iss_occurrences_sync_get_event_series_summaries();
    $summary = is_array($summaries[$series_key] ?? null) ? $summaries[$series_key] : [];
    $title = iss_occurrences_sync_event_series_title($series_key, $summary);
    $service = function_exists('iss_occurrences_get_service') ? iss_occurrences_get_service() : null;
    if (is_object($service) && method_exists($service, 'upsert_series')) {
        $service->upsert_series([
            'series_key' => $series_key,
            'source_post_id' => 0,
            'source_post_type' => '',
            'supersaas_title' => $title,
            'fallback_url' => iss_occurrences_sync_event_series_landing_target_url($series_key),
            'review_state' => $review_state,
            'origin' => 'supersaas',
        ]);
    }

    $updated = is_object($service) && method_exists($service, 'set_series_review_state')
        ? $service->set_series_review_state($series_key, $review_state)
        : false;
    if (is_object($service) && method_exists($service, 'set_supersaas_slots_series_state')) {
        if ($review_state === 'ignored') {
            $service->set_supersaas_slots_series_state($series_key, 0, '', 'ignored', 'ignored');
        } else {
            $service->set_supersaas_slots_series_state($series_key, 0, '', 'unmapped', 'unreviewed');
        }
    }
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_supersaas_sync_occurrences();
    }

    if ($updated) {
        $label = $review_state === 'ignored' ? 'ignoriert' : 'wieder aktiviert';
        iss_occurrences_set_sync_notice('success', sprintf('Veranstaltungsreihe %s wurde %s.', $series_key, $label));
    } else {
        iss_occurrences_set_sync_notice('warning', sprintf('Keine Änderung für Veranstaltungsreihe %s durchgeführt.', $series_key));
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_set_event_series_review_state', [
            'capability' => iss_occurrences_sync_capability(),
            'job_id' => $series_key,
            'result' => $updated ? 'completed' : 'failed',
        ]);
    }

    wp_safe_redirect(iss_occurrences_sync_admin_url());
    exit;
});

add_action('admin_post_iss_occurrences_create_veranstaltung_from_slot', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_slot_action');

    $slot_row_id = isset($_POST['slot_row_id']) ? absint($_POST['slot_row_id']) : 0;
    $service = function_exists('iss_occurrences_get_service') ? iss_occurrences_get_service() : null;
    if ($slot_row_id <= 0 || !is_object($service) || !method_exists($service, 'get_supersaas_slot')) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung konnte nicht angelegt werden: Slot nicht gefunden.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $slot_row = $service->get_supersaas_slot($slot_row_id);
    if (empty($slot_row)) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung konnte nicht angelegt werden: Slot nicht gefunden.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $schedule_key = isset($slot_row['schedule_key']) ? sanitize_key((string) $slot_row['schedule_key']) : '';
    if ($schedule_key !== 'salonbelegung') {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung konnte nicht angelegt werden: nur Salonbelegung-Slots sind zulaessig.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $existing_source_id = isset($slot_row['source_post_id']) ? (int) $slot_row['source_post_id'] : 0;
    if ($existing_source_id > 0 && get_post($existing_source_id) instanceof WP_Post) {
        iss_occurrences_set_sync_notice('warning', sprintf('Dieser Slot ist bereits mit #%d verknuepft.', $existing_source_id));
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $starts_at = isset($slot_row['starts_at']) ? trim((string) $slot_row['starts_at']) : '';
    if ($starts_at === '') {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung konnte nicht angelegt werden: Startdatum fehlt.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $post_type = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE') ? ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE : 'veranstaltung';
    $title = iss_occurrences_sync_slot_title($slot_row);
    $excerpt = iss_occurrences_sync_slot_excerpt($slot_row);
    $landing_target_url = iss_occurrences_sync_slot_landing_target_url($slot_row);
    $semantic_key = iss_occurrences_sync_slot_semantic_key($slot_row);
    $entity_key = $semantic_key === 'repair-cafe' ? 'event.series' : 'event.general';

    $post_id = wp_insert_post([
        'post_type' => $post_type,
        'post_status' => 'draft',
        'post_title' => $title,
        'post_excerpt' => $excerpt,
        'post_content' => '',
    ], true);

    if (is_wp_error($post_id) || (int) $post_id <= 0) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung konnte nicht angelegt werden.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $post_id = (int) $post_id;
    update_post_meta($post_id, '_iss_entity_key', $entity_key);
    update_post_meta($post_id, 'iss_start_datetime', $starts_at);
    update_post_meta($post_id, 'iss_programme_enabled', 0);

    $ends_at = isset($slot_row['ends_at']) ? trim((string) $slot_row['ends_at']) : '';
    if ($ends_at !== '' && $ends_at !== '0000-00-00 00:00:00') {
        update_post_meta($post_id, 'iss_end_datetime', $ends_at);
    }

    $location = isset($slot_row['location_label']) ? trim((string) $slot_row['location_label']) : '';
    if ($location !== '') {
        update_post_meta($post_id, 'iss_location', sanitize_text_field($location));
    }

    if ($landing_target_url !== '') {
        update_post_meta($post_id, 'iss_timeline_target_url', $landing_target_url);
    }

    update_post_meta($post_id, '_iss_supersaas_slot_row_id', $slot_row_id);
    update_post_meta($post_id, '_iss_supersaas_external_id', sanitize_text_field((string) ($slot_row['external_id'] ?? '')));
    update_post_meta($post_id, '_iss_supersaas_slot_id', sanitize_text_field((string) ($slot_row['slot_id'] ?? '')));
    update_post_meta($post_id, '_iss_supersaas_schedule_key', $schedule_key);
    update_post_meta($post_id, '_iss_supersaas_description', sanitize_textarea_field((string) ($slot_row['description'] ?? '')));

    if ($semantic_key !== '') {
        $taxonomy = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY') ? ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY : 'veranstaltung_art';
        if (taxonomy_exists($taxonomy)) {
            if (!term_exists($semantic_key, $taxonomy)) {
                $label = function_exists('iss_content_model_veranstaltung_semantic_label')
                    ? iss_content_model_veranstaltung_semantic_label($semantic_key)
                    : $semantic_key;
                if ($label !== '') {
                    wp_insert_term($label, $taxonomy, ['slug' => $semantic_key]);
                }
            }
            wp_set_object_terms($post_id, [$semantic_key], $taxonomy, false);
        }
    }

    if (method_exists($service, 'set_supersaas_slot_source')) {
        $service->set_supersaas_slot_source($slot_row_id, $post_id, $post_type, 'mapped');
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_create_veranstaltung_from_supersaas_slot', [
            'capability' => iss_occurrences_sync_capability(),
            'object_ids' => [$post_id],
            'job_id' => (string) ($slot_row['external_id'] ?? $slot_row_id),
            'result' => 'completed',
        ]);
    }

    iss_occurrences_set_sync_notice('success', sprintf('Veranstaltung #%d wurde als Entwurf angelegt. Programm bleibt bis zur redaktionellen Freigabe aus.', $post_id));
    wp_safe_redirect(iss_occurrences_sync_admin_url());
    exit;
});

add_action('admin_post_iss_occurrences_set_event_slot_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_slot_action');

    $slot_row_id = isset($_POST['slot_row_id']) ? absint($_POST['slot_row_id']) : 0;
    $post_id = isset($_POST['source_post_id']) ? absint($_POST['source_post_id']) : 0;
    $service = function_exists('iss_occurrences_get_service') ? iss_occurrences_get_service() : null;
    if ($slot_row_id <= 0 || $post_id <= 0 || !is_object($service) || !method_exists($service, 'get_supersaas_slot')) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung-Zuordnung fehlgeschlagen: Slot oder Ziel fehlt.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $slot_row = $service->get_supersaas_slot($slot_row_id);
    $schedule_key = isset($slot_row['schedule_key']) ? sanitize_key((string) $slot_row['schedule_key']) : '';
    if (empty($slot_row) || $schedule_key !== 'salonbelegung') {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung-Zuordnung fehlgeschlagen: nur Salonbelegung-Slots sind zulaessig.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $post = get_post($post_id);
    $post_type = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE') ? ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE : 'veranstaltung';
    if (!$post instanceof WP_Post || $post->post_type !== $post_type) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung-Zuordnung fehlgeschlagen: Zielobjekt ist keine Veranstaltung.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $landing_target_url = iss_occurrences_sync_slot_landing_target_url($slot_row);
    if ($landing_target_url !== '' && trim((string) get_post_meta($post_id, 'iss_timeline_target_url', true)) === '') {
        update_post_meta($post_id, 'iss_timeline_target_url', $landing_target_url);
    }

    update_post_meta($post_id, '_iss_supersaas_slot_row_id', $slot_row_id);
    update_post_meta($post_id, '_iss_supersaas_external_id', sanitize_text_field((string) ($slot_row['external_id'] ?? '')));
    update_post_meta($post_id, '_iss_supersaas_slot_id', sanitize_text_field((string) ($slot_row['slot_id'] ?? '')));
    update_post_meta($post_id, '_iss_supersaas_schedule_key', $schedule_key);

    $mapped = method_exists($service, 'set_supersaas_slot_source')
        ? $service->set_supersaas_slot_source($slot_row_id, $post_id, $post_type, 'mapped')
        : false;
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_supersaas_sync_occurrences();
    }

    if ($mapped) {
        iss_occurrences_set_sync_notice('success', sprintf('Salonbelegung-Slot wurde auf Veranstaltung #%d gesetzt.', $post_id));
    } else {
        iss_occurrences_set_sync_notice('warning', 'Veranstaltung-Zuordnung wurde nicht geaendert.');
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_set_event_slot_source', [
            'capability' => iss_occurrences_sync_capability(),
            'object_ids' => [$post_id],
            'job_id' => (string) ($slot_row['external_id'] ?? $slot_row_id),
            'result' => $mapped ? 'completed' : 'failed',
        ]);
    }

    wp_safe_redirect(iss_occurrences_sync_admin_url());
    exit;
});

add_action('admin_post_iss_occurrences_ignore_event_slot', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_slot_action');

    $slot_row_id = isset($_POST['slot_row_id']) ? absint($_POST['slot_row_id']) : 0;
    $service = function_exists('iss_occurrences_get_service') ? iss_occurrences_get_service() : null;
    if ($slot_row_id <= 0 || !is_object($service) || !method_exists($service, 'get_supersaas_slot')) {
        iss_occurrences_set_sync_notice('error', 'Ignorieren fehlgeschlagen: Slot nicht gefunden.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $slot_row = $service->get_supersaas_slot($slot_row_id);
    $schedule_key = isset($slot_row['schedule_key']) ? sanitize_key((string) $slot_row['schedule_key']) : '';
    if (empty($slot_row) || $schedule_key !== 'salonbelegung') {
        iss_occurrences_set_sync_notice('error', 'Ignorieren fehlgeschlagen: nur Salonbelegung-Slots sind zulaessig.');
        wp_safe_redirect(iss_occurrences_sync_admin_url());
        exit;
    }

    $ignored = method_exists($service, 'set_supersaas_slot_review_state')
        ? $service->set_supersaas_slot_review_state($slot_row_id, 'ignored')
        : false;
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_supersaas_sync_occurrences();
    }

    if ($ignored) {
        iss_occurrences_set_sync_notice('success', 'Salonbelegung-Slot wird ignoriert und in der aktiven Ansicht ausgeblendet.');
    } else {
        iss_occurrences_set_sync_notice('warning', 'Ignorieren hat keine Aenderung erzeugt.');
    }

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_ignore_event_slot', [
            'capability' => iss_occurrences_sync_capability(),
            'job_id' => (string) ($slot_row['external_id'] ?? $slot_row_id),
            'result' => $ignored ? 'completed' : 'failed',
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
    $event_series_summaries = iss_occurrences_sync_get_event_series_summaries();
    $fuehrungen = iss_occurrences_get_fuehrung_ids_for_select();
    $veranstaltungen = iss_occurrences_get_veranstaltung_ids_for_select();
    $series_occurrences = iss_occurrences_get_series_occurrence_summaries(array_keys($series_sources));
    $event_series_occurrences = iss_occurrences_get_series_occurrence_summaries(array_keys($event_series_summaries));

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

    echo '<h2>Veranstaltungs-Terminreihen</h2>';
    if (empty($event_series_summaries)) {
        echo '<p>Noch keine Veranstaltungsreihen aus Salonbelegung erkannt.</p>';
    } else {
        $event_allowed_html = [
            'a' => ['href' => true],
            'br' => [],
            'button' => ['class' => true, 'type' => true],
            'code' => [],
            'form' => ['action' => true, 'method' => true, 'style' => true],
            'input' => ['name' => true, 'type' => true, 'value' => true],
            'option' => ['selected' => true, 'value' => true],
            'select' => ['name' => true],
            'span' => ['class' => true],
            'strong' => [],
        ];

        echo '<table class="widefat striped"><thead><tr><th>Reihe</th><th>Schlüssel</th><th>Slots</th><th>Timeline</th><th>Quelle</th><th>Zuletzt gesehen</th><th>Aktionen</th></tr></thead><tbody>';
        foreach ($event_series_summaries as $series_key => $summary) {
            $series_key = iss_occurrences_normalize_series_key((string) $series_key);
            if ($series_key === '') {
                continue;
            }

            $entry = is_array($series_sources[$series_key] ?? null) ? $series_sources[$series_key] : [];
            $source_post_id = isset($entry['source_post_id']) ? (int) $entry['source_post_id'] : 0;
            $source_post_type = isset($entry['source_post_type']) ? sanitize_key((string) $entry['source_post_type']) : '';
            $review_state = isset($entry['review_state']) ? sanitize_key((string) $entry['review_state']) : '';
            $title = iss_occurrences_sync_event_series_title($series_key, $summary);
            $next_start = isset($summary['next_start']) ? trim((string) $summary['next_start']) : '';
            $slot_label = sprintf(
                '%d gesamt<br><code>%d kommend · %d ignoriert</code>',
                (int) ($summary['total_slots'] ?? 0),
                (int) ($summary['future_slots'] ?? 0),
                (int) ($summary['ignored_slots'] ?? 0)
            );
            if ($next_start !== '') {
                $slot_label .= '<br><code>nächster ' . esc_html(mysql2date('d.m.Y H:i', $next_start)) . '</code>';
            }

            $timeline_label = iss_occurrences_format_series_occurrence_summary($event_series_occurrences[$series_key] ?? []);
            $source_label = '—';
            if ($review_state === 'ignored') {
                $source_label = '<span class="description">ignoriert</span>';
            } elseif ($source_post_id > 0) {
                $source_label = '#' . $source_post_id . ' ' . (get_the_title($source_post_id) ?: '(ohne Titel)');
                $source_edit = get_edit_post_link($source_post_id);
                if ($source_edit) {
                    $source_label = sprintf('<a href="%s">%s</a>', esc_url($source_edit), esc_html($source_label));
                } else {
                    $source_label = esc_html($source_label);
                }
                if ($source_post_type !== '') {
                    $source_label .= '<br><code>' . esc_html($source_post_type) . '</code>';
                }
            }

            $actions = '';
            if ($review_state !== 'ignored') {
                $assign_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">'
                    . '<input type="hidden" name="action" value="iss_occurrences_set_event_series_source" />'
                    . '<input type="hidden" name="series_key" value="' . esc_attr($series_key) . '" />';
                ob_start();
                wp_nonce_field('iss_occurrences_sync_series_source_action');
                $assign_form .= (string) ob_get_clean();
                $assign_form .= '<select name="source_post_id"><option value="">' . esc_html__('Veranstaltung wählen', 'iss-occurrences') . '</option>';
                foreach ($veranstaltungen as $veranstaltung_id) {
                    $veranstaltung_id = (int) $veranstaltung_id;
                    if ($veranstaltung_id <= 0) {
                        continue;
                    }
                    $assign_form .= sprintf(
                        '<option value="%d" %s>#%d %s</option>',
                        $veranstaltung_id,
                        selected($source_post_id, $veranstaltung_id, false),
                        $veranstaltung_id,
                        esc_html(get_the_title($veranstaltung_id) ?: '(ohne Titel)')
                    );
                }
                $assign_form .= '</select><button type="submit" class="button button-secondary">'
                    . esc_html($source_post_id > 0 ? 'Neu zuordnen' : 'Zuordnen')
                    . '</button></form>';
                $actions .= wp_kses($assign_form, $event_allowed_html);

                if ($source_post_id > 0) {
                    $clear_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:6px;">'
                        . '<input type="hidden" name="action" value="iss_occurrences_clear_event_series_source" />'
                        . '<input type="hidden" name="series_key" value="' . esc_attr($series_key) . '" />';
                    ob_start();
                    wp_nonce_field('iss_occurrences_sync_series_source_action');
                    $clear_form .= (string) ob_get_clean();
                    $clear_form .= '<button type="submit" class="button">Zuordnung lösen</button></form>';
                    $actions .= wp_kses($clear_form, $event_allowed_html);
                }

                $ignore_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:6px;">'
                    . '<input type="hidden" name="action" value="iss_occurrences_set_event_series_review_state" />'
                    . '<input type="hidden" name="series_key" value="' . esc_attr($series_key) . '" />'
                    . '<input type="hidden" name="review_state" value="ignored" />';
                ob_start();
                wp_nonce_field('iss_occurrences_sync_series_source_action');
                $ignore_form .= (string) ob_get_clean();
                $ignore_form .= '<button type="submit" class="button">Ignorieren</button></form>';
                $actions .= wp_kses($ignore_form, $event_allowed_html);
            } else {
                $activate_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                    . '<input type="hidden" name="action" value="iss_occurrences_set_event_series_review_state" />'
                    . '<input type="hidden" name="series_key" value="' . esc_attr($series_key) . '" />'
                    . '<input type="hidden" name="review_state" value="unreviewed" />';
                ob_start();
                wp_nonce_field('iss_occurrences_sync_series_source_action');
                $activate_form .= (string) ob_get_clean();
                $activate_form .= '<button type="submit" class="button button-secondary">Wieder aktivieren</button></form>';
                $actions .= wp_kses($activate_form, $event_allowed_html);
            }

            printf(
                '<tr><td><strong>%s</strong></td><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($title),
                esc_html($series_key),
                wp_kses($slot_label, $event_allowed_html),
                wp_kses($timeline_label, $event_allowed_html),
                wp_kses($source_label, $event_allowed_html),
                esc_html(!empty($summary['last_seen_at']) ? mysql2date('d.m.Y H:i', (string) $summary['last_seen_at']) : '—'),
                $actions !== '' ? wp_kses($actions, $event_allowed_html) : '—'
            );
        }
        echo '</tbody></table>';
    }

    echo '<h2>SuperSaaS-Slots</h2>';
    if (function_exists('iss_occurrences_get_service') && method_exists(iss_occurrences_get_service(), 'query_supersaas_slots')) {
        $service = iss_occurrences_get_service();
        $slot_request_args = iss_occurrences_sync_request_args();
        $per_page = 25;
        $slot_query_args = [
            'search' => $slot_request_args['search'],
            'schedule_key' => $slot_request_args['schedule_key'],
            'match_state' => $slot_request_args['match_state'],
            'status' => $slot_request_args['status'],
            'date_scope' => $slot_request_args['date_scope'],
            'orderby' => $slot_request_args['orderby'],
            'order' => $slot_request_args['order'],
            'limit' => $per_page,
            'offset' => ($slot_request_args['paged'] - 1) * $per_page,
        ];
        $slot_rows = $service->query_supersaas_slots($slot_query_args);
        $slot_total = $service->count_supersaas_slots($slot_query_args);
        $schedule_options = method_exists($service, 'get_supersaas_slot_schedule_keys') ? $service->get_supersaas_slot_schedule_keys() : [];

        echo '<form method="get" action="' . esc_url(admin_url(defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php')) . '" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin:12px 0;">';
        echo '<input type="hidden" name="page" value="iss-occurrences-sync" />';
        echo '<label>Suche<br><input type="search" name="s" value="' . esc_attr($slot_request_args['search']) . '" placeholder="Titel, Slot-ID, Reihe" /></label>';
        echo '<label>Schedule<br><select name="schedule_key"><option value="">Alle</option>';
        foreach ($schedule_options as $schedule_option) {
            $option_key = isset($schedule_option['schedule_key']) ? sanitize_key((string) $schedule_option['schedule_key']) : '';
            if ($option_key === '') {
                continue;
            }
            $option_label = isset($schedule_option['schedule_label']) && trim((string) $schedule_option['schedule_label']) !== ''
                ? (string) $schedule_option['schedule_label']
                : $option_key;
            echo '<option value="' . esc_attr($option_key) . '" ' . selected($slot_request_args['schedule_key'], $option_key, false) . '>' . esc_html($option_label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Status<br><select name="slot_status">';
        foreach (['' => 'Alle', 'projected' => 'projiziert', 'skipped' => 'nicht importiert', 'cancelled' => 'abgesagt'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($slot_request_args['status'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Mapping<br><select name="match_state">';
        foreach (['active' => 'Aktive', 'all' => 'Alle inkl. ignorierte', 'mapped' => 'zugeordnet', 'unmapped' => 'unzugeordnet', 'ignored' => 'ignoriert', 'cancelled' => 'abgesagt', 'invalid_source' => 'Quelle ungültig'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($slot_request_args['match_state'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        echo '<label>Zeitraum<br><select name="date_scope">';
        foreach (['future' => 'Kommend', 'past' => 'Vergangen', 'all' => 'Alle'] as $value => $label) {
            echo '<option value="' . esc_attr($value) . '" ' . selected($slot_request_args['date_scope'], $value, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select></label>';
        submit_button('Filtern', 'secondary', '', false);
        echo '</form>';

        if (empty($slot_rows)) {
            echo '<p>Noch keine SuperSaaS-Slots in der Staging-Tabelle. Bitte zuerst synchronisieren.</p>';
        } else {
            $slot_allowed_html = [
                'a' => ['href' => true],
                'br' => [],
                'button' => ['class' => true, 'type' => true],
                'code' => [],
                'details' => [],
                'div' => ['class' => true],
                'form' => ['action' => true, 'method' => true, 'style' => true],
                'input' => ['name' => true, 'type' => true, 'value' => true],
                'option' => ['selected' => true, 'value' => true],
                'select' => ['name' => true],
                'span' => ['class' => true],
                'strong' => [],
                'summary' => [],
            ];

            echo '<table class="widefat striped"><thead><tr>';
            echo '<th><a href="' . iss_occurrences_sync_sort_url('starts_at', $slot_request_args) . '">Datum</a></th>';
            echo '<th><a href="' . iss_occurrences_sync_sort_url('schedule_key', $slot_request_args) . '">Schedule</a></th>';
            echo '<th>SuperSaaS-Inhalt</th>';
            echo '<th><a href="' . iss_occurrences_sync_sort_url('clean_title', $slot_request_args) . '">Reihe</a></th>';
            echo '<th>Quelle</th>';
            echo '<th><a href="' . iss_occurrences_sync_sort_url('match_state', $slot_request_args) . '">Status</a></th>';
            echo '<th>Verfügbarkeit</th>';
            echo '<th><a href="' . iss_occurrences_sync_sort_url('last_seen_at', $slot_request_args) . '">Gesehen</a></th>';
            echo '<th>Aktionen</th>';
            echo '</tr></thead><tbody>';

            foreach ($slot_rows as $slot_row) {
                $schedule_key = isset($slot_row['schedule_key']) ? sanitize_key((string) $slot_row['schedule_key']) : '';
                $is_salonbelegung = $schedule_key === 'salonbelegung';
                $series_key = isset($slot_row['series_key']) ? iss_occurrences_normalize_series_key((string) $slot_row['series_key']) : '';
                $source_post_id = isset($slot_row['source_post_id']) ? (int) $slot_row['source_post_id'] : 0;
                $source_post_title = isset($slot_row['source_post_title']) ? trim((string) $slot_row['source_post_title']) : '';
                $source_label = '—';
                if ($source_post_id > 0) {
                    $source_label = '#' . $source_post_id . ' ' . ($source_post_title !== '' ? $source_post_title : get_the_title($source_post_id));
                    $source_edit = get_edit_post_link($source_post_id);
                    if ($source_edit) {
                        $source_label = sprintf('<a href="%s">%s</a>', esc_url($source_edit), esc_html($source_label));
                    } else {
                        $source_label = esc_html($source_label);
                    }
                }

                $raw_title = trim((string) ($slot_row['raw_title'] ?? ''));
                $description = trim((string) ($slot_row['description'] ?? ''));
                $clean_title = trim((string) ($slot_row['clean_title'] ?? ''));
                $slot_id = trim((string) ($slot_row['slot_id'] ?? ''));
                $slot_title = $raw_title !== ''
                    ? '<strong>' . esc_html($raw_title) . '</strong>'
                    : '<span class="description">ohne Titel/Name</span>';
                $slot_title .= '<br><code>' . esc_html($slot_id) . '</code>';
                if ($description !== '') {
                    $slot_title .= '<details><summary>Beschreibung</summary><div class="description">'
                        . nl2br(esc_html($description))
                        . '</div></details>';
                }
                $series_label = $clean_title !== '' ? esc_html($clean_title) : '—';
                if ($series_key !== '') {
                    $series_label .= '<br><code>' . esc_html($series_key) . '</code>';
                }

                $actions = '—';
                if ($series_key !== '' && !$is_salonbelegung) {
                    $assign_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">'
                        . '<input type="hidden" name="action" value="iss_occurrences_set_series_source" />'
                        . '<input type="hidden" name="series_key" value="' . esc_attr($series_key) . '" />';
                    ob_start();
                    wp_nonce_field('iss_occurrences_sync_series_source_action');
                    $assign_form .= (string) ob_get_clean();
                    $assign_form .= '<select name="source_post_id"><option value="">' . esc_html__('Führung wählen', 'iss-occurrences') . '</option>';
                    foreach ($fuehrungen as $fuehrung_id) {
                        $fuehrung_id = (int) $fuehrung_id;
                        if ($fuehrung_id <= 0) {
                            continue;
                        }
                        $assign_form .= sprintf(
                            '<option value="%d" %s>#%d %s</option>',
                            $fuehrung_id,
                            selected($source_post_id, $fuehrung_id, false),
                            $fuehrung_id,
                            esc_html(get_the_title($fuehrung_id) ?: '(ohne Titel)')
                        );
                    }
                    $assign_form .= '</select><button type="submit" class="button button-secondary">Zuordnen</button></form>';

                    $ignore_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:6px;">'
                        . '<input type="hidden" name="action" value="iss_occurrences_ignore_series_source" />'
                        . '<input type="hidden" name="series_key" value="' . esc_attr($series_key) . '" />';
                    ob_start();
                    wp_nonce_field('iss_occurrences_sync_series_source_action');
                    $ignore_form .= (string) ob_get_clean();
                    $ignore_form .= '<button type="submit" class="button">Ignorieren</button></form>';
                    $actions = wp_kses($assign_form, $slot_allowed_html) . wp_kses($ignore_form, $slot_allowed_html);
                }
                if ($is_salonbelegung && $source_post_id <= 0) {
                    $is_repair_cafe_slot = iss_occurrences_sync_slot_contains_repair_cafe($slot_row);
                    $event_assign_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">'
                        . '<input type="hidden" name="action" value="iss_occurrences_set_event_slot_source" />'
                        . '<input type="hidden" name="slot_row_id" value="' . esc_attr((string) ($slot_row['id'] ?? '')) . '" />';
                    ob_start();
                    wp_nonce_field('iss_occurrences_sync_slot_action');
                    $event_assign_form .= (string) ob_get_clean();
                    $event_assign_form .= '<select name="source_post_id"><option value="">' . esc_html__('Veranstaltung wählen', 'iss-occurrences') . '</option>';
                    foreach ($veranstaltungen as $veranstaltung_id) {
                        $veranstaltung_id = (int) $veranstaltung_id;
                        if ($veranstaltung_id <= 0) {
                            continue;
                        }
                        $event_assign_form .= sprintf(
                            '<option value="%d" %s>#%d %s</option>',
                            $veranstaltung_id,
                            selected($source_post_id, $veranstaltung_id, false),
                            $veranstaltung_id,
                            esc_html(get_the_title($veranstaltung_id) ?: '(ohne Titel)')
                        );
                    }
                    $event_assign_form .= '</select><button type="submit" class="button button-secondary">Zuordnen</button></form>';

                    $create_form = '';
                    if (!$is_repair_cafe_slot) {
                        $create_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
                            . '<input type="hidden" name="action" value="iss_occurrences_create_veranstaltung_from_slot" />'
                            . '<input type="hidden" name="slot_row_id" value="' . esc_attr((string) ($slot_row['id'] ?? '')) . '" />';
                        ob_start();
                        wp_nonce_field('iss_occurrences_sync_slot_action');
                        $create_form .= (string) ob_get_clean();
                        $create_form .= '<button type="submit" class="button" style="margin-top:6px;">Veranstaltung anlegen</button></form>';
                    }
                    $actions = $actions === '—'
                        ? wp_kses($event_assign_form, $slot_allowed_html) . wp_kses($create_form, $slot_allowed_html)
                        : $actions . wp_kses($event_assign_form, $slot_allowed_html) . wp_kses($create_form, $slot_allowed_html);
                } elseif ($is_salonbelegung) {
                    $event_assign_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;">'
                        . '<input type="hidden" name="action" value="iss_occurrences_set_event_slot_source" />'
                        . '<input type="hidden" name="slot_row_id" value="' . esc_attr((string) ($slot_row['id'] ?? '')) . '" />';
                    ob_start();
                    wp_nonce_field('iss_occurrences_sync_slot_action');
                    $event_assign_form .= (string) ob_get_clean();
                    $event_assign_form .= '<select name="source_post_id"><option value="">' . esc_html__('Veranstaltung wählen', 'iss-occurrences') . '</option>';
                    foreach ($veranstaltungen as $veranstaltung_id) {
                        $veranstaltung_id = (int) $veranstaltung_id;
                        if ($veranstaltung_id <= 0) {
                            continue;
                        }
                        $event_assign_form .= sprintf(
                            '<option value="%d" %s>#%d %s</option>',
                            $veranstaltung_id,
                            selected($source_post_id, $veranstaltung_id, false),
                            $veranstaltung_id,
                            esc_html(get_the_title($veranstaltung_id) ?: '(ohne Titel)')
                        );
                    }
                    $event_assign_form .= '</select><button type="submit" class="button button-secondary">Neu zuordnen</button></form>';
                    $actions = $actions === '—'
                        ? wp_kses($event_assign_form, $slot_allowed_html)
                        : $actions . wp_kses($event_assign_form, $slot_allowed_html);
                }
                if ($is_salonbelegung && (string) ($slot_row['match_state'] ?? '') !== 'ignored') {
                    $ignore_form = '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:6px;">'
                        . '<input type="hidden" name="action" value="iss_occurrences_ignore_event_slot" />'
                        . '<input type="hidden" name="slot_row_id" value="' . esc_attr((string) ($slot_row['id'] ?? '')) . '" />';
                    ob_start();
                    wp_nonce_field('iss_occurrences_sync_slot_action');
                    $ignore_form .= (string) ob_get_clean();
                    $ignore_form .= '<button type="submit" class="button">Ignorieren</button></form>';
                    $actions = $actions === '—'
                        ? wp_kses($ignore_form, $slot_allowed_html)
                        : $actions . wp_kses($ignore_form, $slot_allowed_html);
                }

                $date_label = isset($slot_row['starts_at']) ? mysql2date('d.m.Y H:i', (string) $slot_row['starts_at']) : '';
                $date_label .= !empty($slot_row['ends_at']) ? '<br><code>bis ' . esc_html(mysql2date('d.m.Y H:i', (string) $slot_row['ends_at'])) . '</code>' : '';
                $availability = trim((string) ($slot_row['availability_state'] ?? ''));
                $capacity = ((int) ($slot_row['capacity_available'] ?? -1) >= 0 || (int) ($slot_row['capacity_total'] ?? -1) >= 0)
                    ? sprintf('%d / %d', (int) ($slot_row['capacity_available'] ?? -1), (int) ($slot_row['capacity_total'] ?? -1))
                    : '—';

                printf(
                    '<tr><td>%s</td><td>%s<br><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td><td>%s<br><code>%s</code></td><td>%s<br><code>%s</code></td><td>%s</td><td>%s</td></tr>',
                    wp_kses($date_label, $slot_allowed_html),
                    esc_html((string) ($slot_row['schedule_label'] ?? $slot_row['schedule_key'] ?? '')),
                    esc_html((string) ($slot_row['schedule_key'] ?? '')),
                    wp_kses($slot_title, $slot_allowed_html),
                    wp_kses($series_label, $slot_allowed_html),
                    wp_kses($source_label, $slot_allowed_html),
                    esc_html(iss_occurrences_sync_state_label((string) ($slot_row['status'] ?? ''))),
                    esc_html(iss_occurrences_sync_state_label((string) ($slot_row['match_state'] ?? ''))),
                    esc_html($availability !== '' ? $availability : '—'),
                    esc_html($capacity),
                    esc_html(isset($slot_row['last_seen_at']) ? mysql2date('d.m.Y H:i', (string) $slot_row['last_seen_at']) : ''),
                    wp_kses($actions, $slot_allowed_html)
                );
            }
            echo '</tbody></table>';

            $total_pages = max(1, (int) ceil($slot_total / $per_page));
            if ($total_pages > 1) {
                echo '<div class="tablenav"><div class="tablenav-pages">';
                echo wp_kses_post(paginate_links([
                    'base' => add_query_arg([
                        'page' => 'iss-occurrences-sync',
                        's' => $slot_request_args['search'],
                        'schedule_key' => $slot_request_args['schedule_key'],
                        'match_state' => $slot_request_args['match_state'],
                        'slot_status' => $slot_request_args['status'],
                        'date_scope' => $slot_request_args['date_scope'],
                        'orderby' => $slot_request_args['orderby'],
                        'order' => $slot_request_args['order'],
                        'paged' => '%#%',
                    ], admin_url(defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php')),
                    'format' => '',
                    'current' => $slot_request_args['paged'],
                    'total' => $total_pages,
                ]));
                echo '</div></div>';
            }
        }
    } else {
        echo '<p>Die SuperSaaS-Staging-Tabelle ist noch nicht verfügbar.</p>';
    }

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
