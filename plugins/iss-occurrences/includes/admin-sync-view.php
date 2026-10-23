<?php
/** Native admin views over the occurrence owner's existing data and actions. */
if (!defined('ABSPATH')) { exit; }

function iss_occurrences_render_sync_page(): void {
    if (!current_user_can(iss_occurrences_sync_capability())) { wp_die('Keine Berechtigung.', '', ['response' => 403]); }
    $view = iss_occurrences_sync_view();
    echo '<div class="wrap iss-sync"><h1>Termine &amp; Synchronisierung</h1>';
    echo '<p>SuperSaaS liefert Termine. Inhalte und Veröffentlichung werden in WordPress bearbeitet; Plätze und Buchungen verwaltet der WordPress-Buchungsbestand.</p>';
    echo '<nav class="nav-tab-wrapper" aria-label="Terminverwaltung">';
    foreach (iss_occurrences_sync_views() as $key => $label) {
        echo '<a class="nav-tab' . ($key === $view ? ' nav-tab-active' : '') . '" ' . ($key === $view ? 'aria-current="page"' : '') . ' href="' . esc_url(add_query_arg('view', $key, iss_occurrences_sync_admin_url())) . '">' . esc_html($label) . '</a>';
    }
    echo '</nav>';
    $notice_key = 'iss_occurrences_sync_notice_' . get_current_user_id();
    $notice = get_transient($notice_key);
    if (is_array($notice)) {
        delete_transient($notice_key);
        echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>' . esc_html($notice['message']) . '</p></div>';
    }
    $result_key = 'iss_occurrences_sync_result_' . get_current_user_id();
    $result = get_transient($result_key);
    if (is_array($result)) {
        delete_transient($result_key);
        $notice = iss_occurrences_sync_result_notice($result);
        echo '<div class="notice notice-' . esc_attr($notice['type']) . '"><p>' . esc_html($notice['message']) . '</p>';
        if (!empty($result['error_message'])) { echo '<p>' . esc_html($result['error_message']) . '</p>'; }
        echo '</div>';
    }
    if ($view === 'connection') {
        iss_supersaas_render_connection_form();
    } elseif ($view === 'series') {
        iss_occurrences_render_sync_series();
    } elseif ($view === 'review') {
        iss_occurrences_render_sync_review();
    } else { iss_occurrences_render_sync_overview(); }
    echo '</div>';
}

function iss_occurrences_render_sync_overview(): void {
    $service = iss_occurrences_get_service();
    $settings = iss_supersaas_get_settings();
    $schedules = iss_supersaas_get_schedule_configs($settings);
    $configured = !empty($settings['api_key']) && !empty($settings['account_name']) && !empty($settings['base_url']) && !empty($schedules);
    $receipt = get_option('iss_maintenance_supersaas', []);
    $last_success = (int) ($receipt['last_success_at'] ?? 0);
    $next = wp_next_scheduled(ISS_SUPERSAAS_SYNC_HOOK);
    $state = (string) ($receipt['status'] ?? 'never');
    $healthy = $state === 'ok' && $last_success > time() - 2 * HOUR_IN_SECONDS && $next;
    $running = $state === 'running' && (int) ($receipt['started_at'] ?? 0) > time() - 15 * MINUTE_IN_SECONDS;
    $state_label = ['ok' => 'Letzter Abgleich erfolgreich', 'running' => 'Abgleich läuft', 'failed' => 'Letzter Abgleich fehlgeschlagen', 'never' => 'Noch kein überwachter Abgleich'][$state] ?? 'Status unbekannt';
    echo '<h2>Synchronisierung</h2><dl class="iss-sync-status">';
    echo '<dt>Verbindung</dt><dd>' . esc_html($configured ? 'Eingerichtet · ' . count($schedules) . ' aktive Kalender' : 'Einrichtung unvollständig') . '</dd>';
    echo '<dt>Ergebnis</dt><dd>' . esc_html($state_label) . '</dd>';
    echo '<dt>Letzter erfolgreicher Lauf</dt><dd>' . esc_html($last_success ? wp_date('d.m.Y H:i T', $last_success) : 'Noch nicht erfasst') . '</dd>';
    echo '<dt>Nächster geplanter Lauf</dt><dd>' . esc_html($next ? wp_date('d.m.Y H:i T', $next) : 'Nicht geplant') . '</dd></dl>';
    if ($configured && !$healthy && $state !== 'running') {
        echo '<div class="notice notice-warning inline"><p>Der automatische Abgleich ist nicht als aktuell bestätigt. Bitte Verbindung und geplanten Lauf prüfen.</p></div>';
    }
    if (!empty($receipt['error'])) { echo '<p class="description">' . esc_html($receipt['error']) . '</p>'; }
    iss_occurrences_sync_form_start('iss_occurrences_sync', 'iss_occurrences_sync');
    echo '<p>Übernimmt Termine aus allen aktiven Kalendern und aktualisiert ihre Anzeige auf der Website. Fehlende zukünftige Quelltermine werden aus der öffentlichen Terminanzeige entfernt. Buchungen werden hier nicht bearbeitet.</p>';
    submit_button('Termine jetzt abgleichen', 'primary', 'submit', false, $configured && !$running ? [] : ['disabled' => 'disabled']);
    echo '<p role="status" data-iss-sync-progress></p></form><p>Änderungen im Quellkalender können bis zu fünf Minuten verzögert erscheinen.</p><p><a href="' . esc_url(add_query_arg('view', 'connection', iss_occurrences_sync_admin_url())) . '">Verbindung und Kalender prüfen</a></p>';
    echo '<h2>Termine bearbeiten</h2><div class="iss-sync-overview">';
    foreach (['attention' => 'Zu prüfen', 'mapped' => 'Zugeordnet', 'ignored' => 'Ignoriert', 'all' => 'Alle kommenden Termine'] as $state => $label) {
        $count = $service->count_supersaas_slots(['match_state' => $state, 'date_scope' => 'future']);
        $url = add_query_arg(['view' => 'review', 'match_state' => $state], iss_occurrences_sync_admin_url());
        echo '<p><a href="' . esc_url($url) . '"><strong>' . esc_html((string) $count) . '</strong> ' . esc_html($label) . '</a></p>';
    }
    echo '</div><p>„Zu prüfen“ enthält noch nicht zugeordnete Termine und ungültige Verknüpfungen. Eine Zuordnung allein veröffentlicht keinen redaktionellen Entwurf.</p>';
    if (function_exists('iss_payments_lite_admin_capability') && current_user_can(iss_payments_lite_admin_capability())) {
        $parent = defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php';
        echo '<p><a class="button" href="' . esc_url(admin_url($parent . '?page=iss-payments-lite-requests&view=departures')) . '">Plätze und Buchungen verwalten</a></p>';
    }
    echo '<details><summary>Technische Angaben</summary><p>Automatischer Abgleich: stündlich. Abrufe können bis zu fünf Minuten zwischengespeichert sein. Zeitangaben: ' . esc_html(wp_timezone_string()) . '.</p><p>Letzter abgeschlossener Import: ' . esc_html((string) get_option(ISS_SUPERSAAS_LAST_SYNC_OPTION, 'noch keiner')) . '</p></details>';
}

function iss_occurrences_render_sync_review(): void {
    $service = iss_occurrences_get_service();
    if (!$service->supersaas_slots_table_exists()) { echo '<p>Die Termindatenbank ist noch nicht eingerichtet. Bitte die Systemverwaltung prüfen.</p>'; return; }
    $args = iss_occurrences_sync_request_args();
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only filters.
    $args['match_state'] = isset($_GET['match_state']) ? sanitize_key((string) $_GET['match_state']) : 'attention';
    $args['series_key'] = iss_occurrences_normalize_series_key(wp_unslash((string) ($_GET['series_key'] ?? '')));
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $states = ['attention' => 'Zu prüfen', 'all' => 'Alle', 'mapped' => 'Zugeordnet', 'unmapped' => 'Ohne Zuordnung', 'invalid_source' => 'Ungültige Zuordnung', 'ignored' => 'Ignoriert', 'cancelled' => 'Abgesagt'];
    if (!isset($states[$args['match_state']])) { $args['match_state'] = 'attention'; }
    if (!in_array($args['date_scope'], ['future', 'past', 'all'], true)) { $args['date_scope'] = 'future'; }
    $query = $args;
    $query['limit'] = 25;
    $total = $service->count_supersaas_slots($query);
    $pages = max(1, (int) ceil($total / 25));
    $args['paged'] = min($args['paged'], $pages);
    $query['offset'] = ($args['paged'] - 1) * 25;
    $rows = $service->query_supersaas_slots($query);
    echo '<h2>Termine prüfen</h2><form class="iss-sync-filters" method="get" action="' . esc_url(admin_url(defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? 'admin.php' : 'tools.php')) . '"><input type="hidden" name="page" value="iss-occurrences-sync"><input type="hidden" name="view" value="review">';
    echo '<label>Suche<input type="search" name="s" value="' . esc_attr($args['search']) . '" placeholder="Titel oder Terminreihe"></label>';
    echo '<label>Kalender<select name="schedule_key"><option value="">Alle Kalender</option>';
    foreach ($service->get_supersaas_slot_schedule_keys() as $schedule) {
        echo '<option value="' . esc_attr($schedule['schedule_key']) . '" ' . selected($args['schedule_key'], $schedule['schedule_key'], false) . '>' . esc_html($schedule['schedule_label'] === 'public' ? 'Öffentliche Führungen' : ($schedule['schedule_label'] ?: $schedule['schedule_key'])) . '</option>';
    }
    echo '</select></label><label>Zuordnung<select name="match_state">';
    foreach ($states as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected($args['match_state'], $key, false) . '>' . esc_html($label) . '</option>'; }
    echo '</select></label><label>Zeitraum<select name="date_scope">';
    foreach (['future' => 'Kommend', 'past' => 'Vergangen', 'all' => 'Alle'] as $key => $label) { echo '<option value="' . esc_attr($key) . '" ' . selected($args['date_scope'], $key, false) . '>' . esc_html($label) . '</option>'; }
    echo '</select></label>';
    if ($args['series_key'] !== '') { echo '<input type="hidden" name="series_key" value="' . esc_attr($args['series_key']) . '"><span>Reihe: ' . esc_html($args['series_key']) . '</span>'; }
    submit_button('Filtern', 'secondary', 'submit', false);
    echo '<a href="' . esc_url(add_query_arg(['view' => 'review', 'match_state' => 'all'], iss_occurrences_sync_admin_url())) . '">Filter zurücksetzen</a></form>';
    echo '<p>' . esc_html(sprintf('%d Termine gefunden · Seite %d von %d', $total, $args['paged'], $pages)) . '</p>';
    if (!$rows) {
        $any = $service->count_supersaas_slots(['match_state' => 'all', 'date_scope' => 'all']);
        echo '<p>' . esc_html($any ? 'Keine Termine für diese Auswahl. Bitte Filter ändern oder zurücksetzen.' : 'Noch keine Termine importiert. Bitte die Verbindung einrichten und unter „Übersicht“ abgleichen.') . '</p>';
        return;
    }
    foreach ($rows as $row) {
        $source = (int) $row['source_post_id'];
        $series = (string) $row['series_key'];
        $is_event = $row['schedule_key'] === 'salonbelegung';
        echo '<article class="iss-sync-item"><h3>' . esc_html(mysql2date('d.m.Y H:i', $row['starts_at']) . ' · ' . ($row['raw_title'] ?: $row['clean_title'] ?: 'Termin ohne Titel')) . '</h3>';
        echo '<p>' . esc_html($row['schedule_label'] === 'public' ? 'Öffentliche Führungen' : ($row['schedule_label'] ?: $row['schedule_key'])) . ' · ' . esc_html(iss_occurrences_sync_state_label($row['match_state'])) . ' · ' . esc_html(iss_occurrences_sync_state_label($row['status'])) . '</p>';
        iss_occurrences_render_sync_source($source);
        echo '<details><summary>Quelldetails</summary><p>' . esc_html($row['description']) . '</p><p>Ende: ' . esc_html($row['ends_at'] ? mysql2date('d.m.Y H:i', $row['ends_at']) : 'nicht angegeben') . '</p><p>Reihe: <code>' . esc_html($series) . '</code> · Termin-ID: <code>' . esc_html($row['slot_id']) . '</code> · Zuletzt abgerufen: ' . esc_html($row['last_seen_at']) . '</p></details>';
        if (!$is_event) {
            if (strpos($series, 'tour:') === 0) {
                echo '<p>Dieser Termin gehört zu einer Führungsreihe. Änderungen der Zuordnung gelten für die gesamte Reihe.</p><a class="button" href="' . esc_url(add_query_arg(['view' => 'series', 'kind' => 'tours', 'series_key' => $series], iss_occurrences_sync_admin_url())) . '">Terminreihe bearbeiten</a>';
            } else { echo '<p>Keine gültige Führungsreihe erkannt. Bitte den Titel im Quellkalender prüfen.</p>'; }
        } else {
            echo '<details><summary>Diesen Termin bearbeiten</summary><p>Gilt nur für diesen Termin. Redaktionelle Freigabe und Buchungsbestand bleiben getrennte Schritte.</p>';
            iss_occurrences_sync_form_start('iss_occurrences_set_event_slot_source', 'iss_occurrences_sync_slot_action', ['slot_row_id' => $row['id'], 'expected_source' => $source]);
            iss_occurrences_sync_source_select('veranstaltung', $source, 'Veranstaltung');
            iss_occurrences_sync_change_confirmation($source);
            submit_button('Diesen Termin zuordnen', 'secondary', 'submit', false); echo '</form>';
            $ignored = $row['match_state'] === 'ignored';
            iss_occurrences_sync_form_start('iss_occurrences_ignore_event_slot', 'iss_occurrences_sync_slot_action', ['slot_row_id' => $row['id'], 'expected_source' => $source, 'review_state' => $ignored ? 'unreviewed' : 'ignored']);
            echo '<p>' . esc_html($ignored ? 'Bringt diesen Termin zurück in die Prüfung.' : 'Entfernt die Zuordnung dieses Termins und nimmt ihn aus der öffentlichen Terminanzeige.') . '</p>';
            iss_occurrences_sync_change_confirmation($source);
            submit_button($ignored ? 'Diesen Termin wieder prüfen' : 'Diesen Termin ignorieren', 'secondary', 'submit', false); echo '</form>';
            if (!$source && !$ignored && !iss_occurrences_sync_slot_contains_repair_cafe($row)) {
                $type = get_post_type_object('veranstaltung');
                if ($type && current_user_can($type->cap->create_posts)) {
                    iss_occurrences_sync_form_start('iss_occurrences_create_veranstaltung_from_slot', 'iss_occurrences_sync_slot_action', ['slot_row_id' => $row['id'], 'expected_source' => 0]);
                    echo '<p>Nur wenn noch keine passende Veranstaltung existiert: erstellt einen Entwurf, der vor Veröffentlichung redaktionell geprüft werden muss.</p>';
                    submit_button('Veranstaltungsentwurf anlegen', 'secondary', 'submit', false); echo '</form>';
                }
            }
            echo '</details>';
            if (strpos($series, 'event:') === 0) { echo '<p><a href="' . esc_url(add_query_arg(['view' => 'series', 'kind' => 'events', 'series_key' => $series], iss_occurrences_sync_admin_url())) . '">Gesamte Veranstaltungsreihe bearbeiten</a></p>'; }
        }
        echo '</article>';
    }
    if ($pages > 1) {
        $links = ['view' => 'review', 's' => $args['search'], 'schedule_key' => $args['schedule_key'], 'match_state' => $args['match_state'], 'date_scope' => $args['date_scope'], 'series_key' => $args['series_key'], 'paged' => '%#%'];
        echo '<nav class="tablenav-pages" aria-label="Ergebnisseiten">' . wp_kses_post(paginate_links(['base' => add_query_arg($links, iss_occurrences_sync_admin_url()), 'format' => '', 'current' => $args['paged'], 'total' => $pages])) . '</nav>';
    }
}

function iss_occurrences_render_sync_source(int $source): void {
    if (!$source) { echo '<p><strong>Noch nicht zugeordnet</strong></p>'; return; }
    $edit = get_edit_post_link($source);
    $title = get_the_title($source) ?: ('#' . $source);
    echo '<p>Verknüpft mit: ';
    if ($edit) { echo '<a href="' . esc_url($edit) . '">' . esc_html($title) . '</a>'; } else { echo esc_html($title); }
    echo ' · ' . esc_html(iss_occurrences_sync_post_status_label($source)) . '</p>';
}

function iss_occurrences_render_sync_series(): void {
    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only navigation.
    $kind = ($_GET['kind'] ?? '') === 'events' ? 'events' : 'tours';
    $selected = iss_occurrences_normalize_series_key(wp_unslash((string) ($_GET['series_key'] ?? '')));
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
    $event = $kind === 'events';
    $prefix = $event ? 'event:' : 'tour:';
    $sources = iss_occurrences_get_series_sources();
    $summaries = $event ? iss_occurrences_get_service()->get_event_series_summaries() : [];
    foreach ($summaries as $key => $summary) { $sources[$key] = $sources[$key] ?? ['supersaas_title' => iss_occurrences_sync_event_series_title($key, $summary)]; }
    $sources = array_filter($sources, static fn($key) => strpos($key, $prefix) === 0 && ($selected === '' || $key === $selected), ARRAY_FILTER_USE_KEY);
    $occurrences = iss_occurrences_get_service()->get_series_occurrence_summaries(array_keys($sources));
    echo '<h2>Terminreihen</h2><p>';
    foreach (['tours' => 'Führungen', 'events' => 'Veranstaltungen'] as $key => $label) {
        echo '<a class="button" ' . ($key === $kind ? 'aria-current="page"' : '') . ' href="' . esc_url(add_query_arg(['view' => 'series', 'kind' => $key], iss_occurrences_sync_admin_url())) . '">' . esc_html($label) . '</a> ';
    }
    echo '</p><p>Änderungen hier gelten für alle Termine der gewählten Reihe. Einzelne Veranstaltungstermine können unter „Zu prüfen“ getrennt bearbeitet werden.</p>';
    if (!$sources) { echo '<p>Keine passenden Terminreihen vorhanden.</p>'; }
    foreach ($sources as $key => $entry) {
        $source = (int) ($entry['source_post_id'] ?? 0);
        $ignored = ($entry['review_state'] ?? '') === 'ignored';
        $total = iss_occurrences_get_service()->count_supersaas_slots(['series_key' => $key, 'match_state' => 'all']);
        $upcoming = iss_occurrences_get_service()->count_supersaas_slots(['series_key' => $key, 'match_state' => 'all', 'date_scope' => 'future']);
        echo '<article class="iss-sync-item"><h3>' . esc_html($entry['supersaas_title'] ?: $key) . '</h3><p>' . esc_html(sprintf('%d importierte Termine · %d kommend · %d kommende öffentliche Termine', $total, $upcoming, (int) ($occurrences[$key]['future_active_rows'] ?? 0))) . '</p>';
        if ($ignored) { echo '<p><strong>Reihe ignoriert</strong></p>'; }
        iss_occurrences_render_sync_source($source);
        echo '<p><a href="' . esc_url(add_query_arg(['view' => 'review', 'series_key' => $key, 'match_state' => 'all', 'date_scope' => 'all'], iss_occurrences_sync_admin_url())) . '">Betroffene Termine ansehen</a></p>';
        echo '<details><summary>Gesamte Reihe bearbeiten</summary>';
        if (!$ignored) {
            iss_occurrences_sync_form_start($event ? 'iss_occurrences_set_event_series_source' : 'iss_occurrences_set_series_source', 'iss_occurrences_sync_series_source_action', ['series_key' => $key, 'expected_source' => $source]);
            iss_occurrences_sync_source_select($event ? 'veranstaltung' : 'fuehrung', $source, $event ? 'Veranstaltung für diese Reihe' : 'Führung für diese Reihe');
            iss_occurrences_sync_change_confirmation($source);
            if (!$event) { echo '<p>Eine Führung mit einer anderen bestehenden Terminreihe muss dort zuerst gelöst werden.</p>'; }
            submit_button('Gesamte Reihe zuordnen', 'secondary', 'submit', false); echo '</form>';
            if ($source) {
                iss_occurrences_sync_form_start($event ? 'iss_occurrences_clear_event_series_source' : 'iss_occurrences_clear_series_source', 'iss_occurrences_sync_series_source_action', ['series_key' => $key, 'expected_source' => $source]);
                echo '<p>Löst die Verknüpfung dieser gesamten Reihe. Inhalte werden nicht gelöscht.</p>';
                iss_occurrences_sync_change_confirmation($source);
                submit_button('Zuordnung der Reihe lösen', 'secondary', 'submit', false); echo '</form>';
            }
        }
        iss_occurrences_sync_form_start($event ? 'iss_occurrences_set_event_series_review_state' : 'iss_occurrences_ignore_series_source', 'iss_occurrences_sync_series_source_action', ['series_key' => $key, 'expected_source' => $source, 'review_state' => $ignored ? 'unreviewed' : 'ignored']);
        echo '<p>' . esc_html($ignored ? 'Bringt die gesamte Reihe zurück in die Prüfung.' : 'Ignorieren entfernt die Zuordnung und nimmt die Termine dieser Reihe aus der öffentlichen Terminanzeige.') . '</p>';
        iss_occurrences_sync_change_confirmation($source);
        submit_button($ignored ? 'Gesamte Reihe wieder prüfen' : 'Gesamte Reihe ignorieren', 'secondary', 'submit', false); echo '</form></details>';
        echo '<details><summary>Technische Angaben</summary><p>Reihenschlüssel: <code>' . esc_html($key) . '</code></p></details></article>';
    }
}
