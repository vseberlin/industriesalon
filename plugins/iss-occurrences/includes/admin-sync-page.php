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

        set_transient('iss_occurrences_sync_notice_' . get_current_user_id(), [
            'type' => $type,
            'message' => $message,
        ], 10 * MINUTE_IN_SECONDS);
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

if (!function_exists('iss_occurrences_sync_request_args')) {
    function iss_occurrences_sync_request_args(): array {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only list filters.
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

// phpcs:enable WordPress.Security.NonceVerification.Recommended

if (!function_exists('iss_occurrences_sync_state_label')) {
    function iss_occurrences_sync_state_label(string $value): string {
        $labels = [
            'projected' => 'In Terminanzeige übernommen',
            'seen' => 'Abgerufen',
            'skipped' => 'Nicht in öffentlicher Terminanzeige',
            'cancelled' => 'abgesagt',
            'mapped' => 'zugeordnet',
            'unmapped' => 'Ohne Zuordnung',
            'ignored' => 'ignoriert',
            'invalid_source' => 'Zuordnung ungültig',
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
        return iss_occurrences_sync_slot_contains_repair_cafe($slot_row)
            ? iss_occurrences_event_series_landing_target_url('event:repair-cafe')
            : '';
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

add_action('admin_menu', function () {
    add_submenu_page(
        defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : 'tools.php',
        'Termine & Synchronisierung',
        'Termine & Synchronisierung',
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
        set_transient('iss_occurrences_sync_result_' . get_current_user_id(), [
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
        ], 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $result = iss_occurrences_run_admin_sync();
    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('occurrences_sync', [
            'capability' => iss_occurrences_sync_capability(),
            'result' => ((int) ($result['errors'] ?? 0)) > 0 ? 'failed' : 'completed',
            'created' => (int) ($result['created'] ?? 0),
            'updated' => (int) ($result['updated'] ?? 0),
        ]);
    }

    wp_safe_redirect(iss_occurrences_sync_return_url());
    exit;
});

add_action('admin_post_iss_occurrences_clear_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $entry = iss_occurrences_get_series_source($series_key);
    if (strpos($series_key, 'tour:') !== 0 || !is_array($entry) || !iss_occurrences_check_assignment_change((int) ($entry['source_post_id'] ?? 0))) {
        iss_occurrences_set_sync_notice('error', 'Terminreihe oder Bestätigung ungültig. Bitte den aktuellen Stand prüfen.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    if ($series_key === '') {
        iss_occurrences_set_sync_notice('error', 'Zuordnung konnte nicht gelöst werden: ungültige Reihe.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $cleared = function_exists('iss_occurrences_clear_series_source_for_key')
        ? iss_occurrences_clear_series_source_for_key($series_key)
        : false;

    if ($cleared) {
        iss_occurrences_get_service()->set_supersaas_slots_series_state($series_key, 0, '', 'unmapped', 'unreviewed');
        iss_occurrences_run_admin_sync();
    }
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

    wp_safe_redirect(iss_occurrences_sync_return_url());
    exit;
});

add_action('admin_post_iss_occurrences_ignore_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $entry = iss_occurrences_get_series_source($series_key);
    if (strpos($series_key, 'tour:') !== 0 || !is_array($entry) || !iss_occurrences_check_assignment_change((int) ($entry['source_post_id'] ?? 0))) {
        iss_occurrences_set_sync_notice('error', 'Terminreihe oder Bestätigung ungültig. Bitte den aktuellen Stand prüfen.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    if ($series_key === '') {
        iss_occurrences_set_sync_notice('error', 'Ignorieren fehlgeschlagen: ungültige Reihe.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $ignored = false;
    if (function_exists('iss_occurrences_get_service') && method_exists(iss_occurrences_get_service(), 'set_series_review_state')) {
        $review_state = isset($_POST['review_state']) && $_POST['review_state'] === 'unreviewed' ? 'unreviewed' : 'ignored';
        $ignored = iss_occurrences_get_service()->set_series_review_state($series_key, $review_state);
        if ($ignored) { iss_occurrences_get_service()->set_supersaas_slots_series_state($series_key, 0, '', $review_state === 'ignored' ? 'ignored' : 'unmapped', $review_state); }
    }
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_occurrences_run_admin_sync();
    }

    if ($ignored) {
        iss_occurrences_set_sync_notice('success', sprintf('Reihe %s: %s.', $series_key, ($review_state ?? '') === 'unreviewed' ? 'wieder zur Prüfung vorgemerkt' : 'ignoriert'));
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

    wp_safe_redirect(iss_occurrences_sync_return_url());
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
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    if ($post_id <= 0) {
        iss_occurrences_set_sync_notice('error', 'Neu-Zuordnung fehlgeschlagen: bitte eine Führung auswählen.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $post = get_post($post_id);
    if (!($post instanceof WP_Post) || $post->post_type !== 'fuehrung') {
        iss_occurrences_set_sync_notice('error', 'Neu-Zuordnung fehlgeschlagen: Zielobjekt ist keine Führung.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $entry = function_exists('iss_occurrences_get_series_source')
        ? iss_occurrences_get_series_source($series_key)
        : null;
    if (!is_array($entry)) {
        iss_occurrences_set_sync_notice('error', sprintf('Neu-Zuordnung fehlgeschlagen: Reihe %s wurde nicht gefunden.', $series_key));
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $validated = iss_occurrences_validate_tour_assignment($series_key, $post_id);
    if (is_wp_error($validated)) {
        iss_occurrences_set_sync_notice('error', $validated->get_error_message());
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    if (!iss_occurrences_check_assignment_change((int) ($entry['source_post_id'] ?? 0))) {
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $title = isset($entry['supersaas_title']) ? trim((string) $entry['supersaas_title']) : '';
    $tag = isset($entry['tag']) ? strtoupper(sanitize_text_field((string) $entry['tag'])) : '';
    $tag = preg_replace('/[^A-Z0-9_-]+/', '', $tag);
    $tag = trim((string) $tag);
    $fallback_url = isset($entry['fallback_url']) ? esc_url_raw((string) $entry['fallback_url']) : '';

    $mapped = iss_occurrences_remember_series_source($series_key, $post_id, 'fuehrung', $title, $tag, $fallback_url);
    if (!$mapped) {
        iss_occurrences_set_sync_notice('error', 'Zuordnung konnte nicht gespeichert werden. Bitte erneut versuchen.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    iss_occurrences_get_service()->set_supersaas_slots_series_state($series_key, $post_id, 'fuehrung', 'mapped', 'mapped');
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_occurrences_run_admin_sync();
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

    wp_safe_redirect(iss_occurrences_sync_return_url());
    exit;
});

add_action('admin_post_iss_occurrences_set_event_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $entry = iss_occurrences_get_series_source($series_key);
    if (strpos($series_key, 'event:') !== 0 || (!is_array($entry) && !isset(iss_occurrences_get_service()->get_event_series_summaries()[$series_key])) || !iss_occurrences_check_assignment_change((int) ($entry['source_post_id'] ?? 0))) {
        iss_occurrences_set_sync_notice('error', 'Terminreihe oder Bestätigung ungültig. Bitte den aktuellen Stand prüfen.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    $post_id = isset($_POST['source_post_id']) ? absint($_POST['source_post_id']) : 0;
    if ($series_key === '' || strpos($series_key, 'event:') !== 0) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltungsreihe konnte nicht zugeordnet werden: ungültige Reihe.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    if ($post_id <= 0) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltungsreihe konnte nicht zugeordnet werden: bitte eine Veranstaltung auswählen.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $post_type = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE') ? ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE : 'veranstaltung';
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== $post_type || !current_user_can('edit_post', $post_id)) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltungsreihe konnte nicht zugeordnet werden: Zielobjekt ist keine Veranstaltung.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $summaries = iss_occurrences_get_service()->get_event_series_summaries();
    $summary = is_array($summaries[$series_key] ?? null) ? $summaries[$series_key] : [];
    $title = iss_occurrences_sync_event_series_title($series_key, $summary);
    $fallback_url = iss_occurrences_event_series_landing_target_url($series_key);
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
        iss_occurrences_run_admin_sync();
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

    wp_safe_redirect(iss_occurrences_sync_return_url());
    exit;
});

add_action('admin_post_iss_occurrences_clear_event_series_source', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $entry = iss_occurrences_get_series_source($series_key);
    if (strpos($series_key, 'event:') !== 0 || (!is_array($entry) && !isset(iss_occurrences_get_service()->get_event_series_summaries()[$series_key])) || !iss_occurrences_check_assignment_change((int) ($entry['source_post_id'] ?? 0))) {
        iss_occurrences_set_sync_notice('error', 'Terminreihe oder Bestätigung ungültig. Bitte den aktuellen Stand prüfen.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    if ($series_key === '' || strpos($series_key, 'event:') !== 0) {
        iss_occurrences_set_sync_notice('error', 'Zuordnung konnte nicht gelöst werden: ungültige Veranstaltungsreihe.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
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
        iss_occurrences_run_admin_sync();
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

    wp_safe_redirect(iss_occurrences_sync_return_url());
    exit;
});

add_action('admin_post_iss_occurrences_set_event_series_review_state', function () {
    if (!current_user_can(iss_occurrences_sync_capability())) {
        wp_die('Not allowed.');
    }

    check_admin_referer('iss_occurrences_sync_series_source_action');

    $series_key = isset($_POST['series_key']) ? iss_occurrences_normalize_series_key(wp_unslash($_POST['series_key'])) : '';
    $entry = iss_occurrences_get_series_source($series_key);
    if (strpos($series_key, 'event:') !== 0 || (!is_array($entry) && !isset(iss_occurrences_get_service()->get_event_series_summaries()[$series_key])) || !iss_occurrences_check_assignment_change((int) ($entry['source_post_id'] ?? 0))) {
        iss_occurrences_set_sync_notice('error', 'Terminreihe oder Bestätigung ungültig. Bitte den aktuellen Stand prüfen.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    $review_state = isset($_POST['review_state']) ? sanitize_key(wp_unslash($_POST['review_state'])) : '';
    if ($series_key === '' || strpos($series_key, 'event:') !== 0 || !in_array($review_state, ['ignored', 'unreviewed'], true)) {
        iss_occurrences_set_sync_notice('error', 'Status der Veranstaltungsreihe konnte nicht geändert werden.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $summaries = iss_occurrences_get_service()->get_event_series_summaries();
    $summary = is_array($summaries[$series_key] ?? null) ? $summaries[$series_key] : [];
    $title = iss_occurrences_sync_event_series_title($series_key, $summary);
    $service = function_exists('iss_occurrences_get_service') ? iss_occurrences_get_service() : null;
    if (is_object($service) && method_exists($service, 'upsert_series')) {
        $service->upsert_series([
            'series_key' => $series_key,
            'source_post_id' => 0,
            'source_post_type' => '',
            'supersaas_title' => $title,
            'fallback_url' => iss_occurrences_event_series_landing_target_url($series_key),
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
        iss_occurrences_run_admin_sync();
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

    wp_safe_redirect(iss_occurrences_sync_return_url());
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
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $slot_row = $service->get_supersaas_slot($slot_row_id);
    if (!iss_occurrences_check_assignment_change((int) ($slot_row['source_post_id'] ?? 0))) {
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    if (empty($slot_row)) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung konnte nicht angelegt werden: Slot nicht gefunden.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $schedule_key = isset($slot_row['schedule_key']) ? sanitize_key((string) $slot_row['schedule_key']) : '';
    if ($schedule_key !== 'salonbelegung') {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung konnte nicht angelegt werden: nur Salonbelegung-Slots sind zulaessig.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $existing_source_id = isset($slot_row['source_post_id']) ? (int) $slot_row['source_post_id'] : 0;
    if ($existing_source_id > 0 && get_post($existing_source_id) instanceof WP_Post) {
        iss_occurrences_set_sync_notice('warning', sprintf('Dieser Slot ist bereits mit #%d verknuepft.', $existing_source_id));
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $starts_at = isset($slot_row['starts_at']) ? trim((string) $slot_row['starts_at']) : '';
    if ($starts_at === '') {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung konnte nicht angelegt werden: Startdatum fehlt.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $post_type = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE') ? ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE : 'veranstaltung';
    $type_object = get_post_type_object($post_type);
    if (!$type_object || !current_user_can($type_object->cap->create_posts)) {
        wp_die('Keine Berechtigung zum Anlegen einer Veranstaltung.', '', ['response' => 403]);
    }
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
        wp_safe_redirect(iss_occurrences_sync_return_url());
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
    wp_safe_redirect(iss_occurrences_sync_return_url());
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
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $slot_row = $service->get_supersaas_slot($slot_row_id);
    if (!iss_occurrences_check_assignment_change((int) ($slot_row['source_post_id'] ?? 0))) {
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    $schedule_key = isset($slot_row['schedule_key']) ? sanitize_key((string) $slot_row['schedule_key']) : '';
    if (empty($slot_row) || $schedule_key !== 'salonbelegung') {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung-Zuordnung fehlgeschlagen: nur Salonbelegung-Slots sind zulaessig.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $post = get_post($post_id);
    $post_type = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE') ? ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE : 'veranstaltung';
    if (!$post instanceof WP_Post || $post->post_type !== $post_type || !current_user_can('edit_post', $post_id)) {
        iss_occurrences_set_sync_notice('error', 'Veranstaltung-Zuordnung fehlgeschlagen: Zielobjekt ist keine Veranstaltung.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
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
        iss_occurrences_run_admin_sync();
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

    wp_safe_redirect(iss_occurrences_sync_return_url());
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
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $slot_row = $service->get_supersaas_slot($slot_row_id);
    if (!iss_occurrences_check_assignment_change((int) ($slot_row['source_post_id'] ?? 0))) {
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }
    $schedule_key = isset($slot_row['schedule_key']) ? sanitize_key((string) $slot_row['schedule_key']) : '';
    if (empty($slot_row) || $schedule_key !== 'salonbelegung') {
        iss_occurrences_set_sync_notice('error', 'Ignorieren fehlgeschlagen: nur Salonbelegung-Slots sind zulaessig.');
        wp_safe_redirect(iss_occurrences_sync_return_url());
        exit;
    }

    $review_state = isset($_POST['review_state']) && $_POST['review_state'] === 'unreviewed' ? 'unreviewed' : 'ignored';
    $ignored = method_exists($service, 'set_supersaas_slot_review_state')
        ? $service->set_supersaas_slot_review_state($slot_row_id, $review_state)
        : false;
    if (function_exists('iss_supersaas_sync_occurrences')) {
        iss_occurrences_run_admin_sync();
    }

    if ($ignored) {
        iss_occurrences_set_sync_notice('success', $review_state === 'unreviewed' ? 'Termin wieder zur Prüfung vorgemerkt.' : 'Termin ignoriert und aus der aktiven Ansicht genommen.');
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

    wp_safe_redirect(iss_occurrences_sync_return_url());
    exit;
});

require_once __DIR__ . '/admin-sync-view.php';
