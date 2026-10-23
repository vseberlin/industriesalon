<?php
/** Shared controls for the existing SuperSaaS admin and editor surfaces. */
if (!defined('ABSPATH')) { exit; }

function iss_occurrences_sync_views(): array {
    return ['overview' => 'Übersicht', 'review' => 'Zu prüfen', 'series' => 'Terminreihen', 'connection' => 'Verbindung'];
}

function iss_occurrences_sync_view(): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
    $view = sanitize_key(wp_unslash((string) ($_GET['view'] ?? 'overview')));
    return isset(iss_occurrences_sync_views()[$view]) ? $view : 'overview';
}

function iss_occurrences_sync_return_url(): string {
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Called after each action's nonce check; allowlisted local navigation only.
    $referer = wp_unslash((string) ($_POST['_wp_http_referer'] ?? ''));
    $args = [];
    wp_parse_str((string) wp_parse_url($referer, PHP_URL_QUERY), $args);
    if (($args['page'] ?? '') !== 'iss-occurrences-sync') { return iss_occurrences_sync_admin_url(); }
    $allowed = ['view', 's', 'schedule_key', 'match_state', 'slot_status', 'date_scope', 'orderby', 'order', 'paged', 'kind', 'series_key'];
    $args = array_intersect_key($args, array_flip($allowed));
    foreach ($args as $key => $value) { $args[$key] = is_scalar($value) ? sanitize_text_field((string) $value) : ''; }
    return add_query_arg($args, iss_occurrences_sync_admin_url());
}

/** Record the manual run through the same monitor used by the hourly job. */
function iss_occurrences_run_admin_sync(): array {
    $result = [];
    try {
        $run = static function () use (&$result): array {
            $result = iss_supersaas_sync_occurrences();
            return $result;
        };
        if (function_exists('iss_core_run_maintenance_job')) {
            iss_core_run_maintenance_job('supersaas', $run);
        } else { $run(); }
    } catch (Throwable $error) {
        $result['errors'] = max(1, (int) ($result['errors'] ?? 0));
        $result['error_message'] = $error->getMessage();
    }
    set_transient('iss_occurrences_sync_result_' . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS);
    return $result;
}

function iss_occurrences_sync_result_notice(array $result): array {
    $failed = !empty($result['errors']) || !empty($result['error_message']);
    return [
        'type' => $failed ? 'error' : 'success',
        'message' => $failed
            ? 'Terminabgleich nicht vollständig. Gespeicherte Zuordnungen bleiben erhalten; die Website kann noch den vorherigen Stand zeigen. Verbindung prüfen und den Abgleich erneut starten.'
            : sprintf('Terminabgleich erfolgreich: %d neue, %d aktualisierte und %d nicht importierte Termine.', (int) ($result['created'] ?? 0), (int) ($result['updated'] ?? 0), (int) ($result['skipped_unlinked'] ?? 0)),
    ];
}

/** Never let a tour assignment silently detach another series. */
function iss_occurrences_validate_tour_assignment(string $series_key, int $post_id) {
    $entry = iss_occurrences_get_series_source($series_key);
    if (strpos($series_key, 'tour:') !== 0 || !is_array($entry)) {
        return new WP_Error('invalid_series', 'Bitte eine vorhandene Führungsreihe wählen. Veranstaltungsreihen können keiner Führung zugeordnet werden.');
    }
    if (get_post_type($post_id) !== 'fuehrung' || !current_user_can('edit_post', $post_id)) {
        return new WP_Error('invalid_target', 'Die gewählte Führung kann nicht bearbeitet werden.');
    }
    $other = array_diff(iss_occurrences_resolve_series_keys_for_source_post_id($post_id), [$series_key]);
    if ($other && (int) ($entry['source_post_id'] ?? 0) !== $post_id) {
        return new WP_Error('already_linked', 'Diese Führung ist bereits mit einer anderen Terminreihe verbunden. Bitte deren Zuordnung unter „Terminreihen“ zuerst prüfen und lösen.');
    }
    return $entry;
}

/** Review controls submit their observed source so stale pages cannot overwrite new work. */
function iss_occurrences_check_assignment_change(int $current_source): bool {
    // phpcs:disable WordPress.Security.NonceVerification.Missing -- Action handlers verify the nonce before this guard.
    $expected = isset($_POST['expected_source']) ? (int) $_POST['expected_source'] : -1;
    $confirmed = !empty($_POST['confirm_change']);
    // phpcs:enable WordPress.Security.NonceVerification.Missing
    if ($expected !== $current_source) {
        iss_occurrences_set_sync_notice('error', 'Die Zuordnung hat sich inzwischen geändert. Bitte den aktuellen Stand prüfen und erneut speichern.');
        return false;
    }
    if ($current_source > 0 && !$confirmed) {
        iss_occurrences_set_sync_notice('error', 'Bitte die Änderung der bestehenden Zuordnung bestätigen.');
        return false;
    }
    return true;
}

function iss_occurrences_sync_form_start(string $action, string $nonce, array $fields = []): void {
    echo '<form class="iss-sync-form" ' . ($action === 'iss_occurrences_sync' ? 'data-iss-sync-run ' : '') . 'method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field($nonce);
    foreach (array_merge(['action' => $action], $fields) as $name => $value) {
        echo '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '">';
    }
}

function iss_occurrences_sync_post_status_label(int $post_id): string {
    $status = get_post_status($post_id);
    $labels = ['publish' => 'Veröffentlicht', 'draft' => 'Entwurf', 'pending' => 'Freigabe ausstehend', 'private' => 'Privat', 'future' => 'Geplant', 'trash' => 'Papierkorb'];
    return $labels[$status] ?? 'Inhalt nicht mehr vorhanden';
}

function iss_occurrences_sync_source_select(string $type, int $selected_id, string $label): void {
    $ids = $type === 'fuehrung' ? iss_occurrences_get_fuehrung_ids_for_select() : iss_occurrences_get_veranstaltung_ids_for_select();
    echo '<label>' . esc_html($label) . '<select name="source_post_id" required><option value="">Bitte wählen</option>';
    foreach ($ids as $id) {
        if (!current_user_can('edit_post', $id)) { continue; }
        $text = get_the_title($id) ?: '(ohne Titel)';
        $text .= ' · ' . iss_occurrences_sync_post_status_label((int) $id) . ' · #' . $id;
        echo '<option value="' . esc_attr((string) $id) . '" ' . selected($id, $selected_id, false) . '>' . esc_html($text) . '</option>';
    }
    echo '</select></label>';
}

function iss_occurrences_sync_change_confirmation(int $source): void {
    if ($source > 0) {
        echo '<label><input type="checkbox" name="confirm_change" value="1" required> Bestehende Zuordnung ändern: ' . esc_html(get_the_title($source) ?: ('#' . $source)) . '</label>';
    }
}

add_action('admin_enqueue_scripts', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Asset selection only.
    if (in_array(sanitize_key((string) ($_GET['page'] ?? '')), ['iss-occurrences-sync', ISS_SUPERSAAS_SETTINGS_PAGE], true)) {
        wp_enqueue_script('iss-occurrences-admin', plugins_url('../assets/admin-sync.js', __FILE__), [], ISS_OCCURRENCES_VERSION, true);
        wp_enqueue_style('iss-occurrences-admin', plugins_url('../assets/admin-sync.css', __FILE__), [], ISS_OCCURRENCES_VERSION);
    }
});
