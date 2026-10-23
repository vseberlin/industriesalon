<?php
/** Extend the existing staff request screen; native forms, capabilities and nonces. */
if (!defined('ABSPATH')) { exit; }

add_action('admin_post_iss_commerce_ledger', static function (): void {
    if (!current_user_can(iss_payments_lite_admin_capability())) { wp_die('Keine Berechtigung.', '', ['response' => 403]); }
    check_admin_referer('iss_commerce_ledger');
    $action = sanitize_key(wp_unslash((string) ($_POST['ledger_action'] ?? '')));
    $id = absint($_POST['ledger_id'] ?? 0);
    $reason = sanitize_text_field(wp_unslash((string) ($_POST['reason'] ?? '')));
    try {
        if ($action === 'sync') { ISS_Commerce_Ledger::sync_schedule(); }
        elseif ($action === 'create_booking') {
            $departure = ISS_Commerce_Ledger::departure($id);
            $payload = ['intent' => 'booking', 'source_post_id' => (int) $departure['source_post_id'], 'source_post_type' => $departure['source_post_type'], 'slot_id' => $departure['slot_id'],
                'name' => sanitize_text_field(wp_unslash((string) ($_POST['customer_name'] ?? ''))), 'email' => sanitize_email(wp_unslash((string) ($_POST['customer_email'] ?? ''))),
                'tickets' => absint($_POST['tickets'] ?? 0), 'reduced_tickets' => absint($_POST['reduced_tickets'] ?? 0), 'payment' => 'onsite',
                'request_key' => sanitize_text_field(wp_unslash((string) ($_POST['request_key'] ?? '')))];
            $response = iss_payments_lite_process_request_payload($payload, new WP_REST_Request('POST'));
            if ($response->get_status() !== 200) { throw new DomainException((string) ($response->get_data()['error'] ?? 'Buchung abgelehnt.')); }
        }
        elseif ($action === 'capacity') { ISS_Commerce_Ledger::configure($id, absint($_POST['capacity'] ?? 0), !empty($_POST['booking_open']), !empty($_POST['test_mode']), $reason, !empty($_POST['reconciled'])); }
        elseif (in_array($action, ['confirmed', 'cancelled', 'reverse_voucher'], true)) { ISS_Commerce_Ledger::staff_action($id, $action, $reason); }
        elseif ($action === 'mail_retry') {
            if ($reason === '') { throw new DomainException('Zustellung prüfen und Begründung angeben.'); }
            ISS_Commerce_Ledger::execute("UPDATE %i SET state = 'retry', next_attempt_at = UTC_TIMESTAMP(), last_error = 'Staff reviewed mailbox and requested retry' WHERE id = %d AND state IN ('failed','uncertain')", [ISS_Commerce_Ledger::table('outbox'), $id]);
        } else { throw new DomainException('Unbekannte Aktion.'); }
    } catch (Throwable $error) { wp_die(esc_html($error->getMessage()), '', ['response' => 409, 'back_link' => true]); }
    $view = in_array($action, ['confirmed', 'cancelled', 'reverse_voucher', 'create_booking'], true) ? 'bookings' : ($action === 'mail_retry' ? 'delivery' : 'departures');
    wp_safe_redirect(iss_commerce_admin_return_url($view)); exit;
});

function iss_commerce_render_ledger(string $view = 'departures'): void {
    if (!ISS_Commerce_Ledger::ready() || !current_user_can(iss_payments_lite_admin_capability())) { return; }
    $page = max(1, (int) iss_payments_lite_admin_get_param('paged'));
    $departures = $view === 'departures' ? ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE starts_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY) ORDER BY starts_at LIMIT 50 OFFSET %d', [ISS_Commerce_Ledger::table('departures'), ($page - 1) * 50]) : [];
    if ($view === 'bookings') {
        $departures = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE starts_at > UTC_TIMESTAMP() AND booking_open = 1 ORDER BY starts_at', [ISS_Commerce_Ledger::table('departures')]);
    }
    if ($view === 'departures') {
    if (function_exists('iss_occurrences_sync_admin_url') && current_user_can(iss_occurrences_sync_capability())) {
        echo '<p><a href="' . esc_url(iss_occurrences_sync_admin_url()) . '">Quelltermine und SuperSaaS-Abgleich</a></p>';
    }
    echo '<h2>' . esc_html__('Termine und Buchungsbestand', 'iss-payments-lite') . '</h2><p>' . esc_html__('Kapazität: 30 Personen je Termin. Vor dem Öffnen vorhandene Reservierungen abgleichen. Testtermine erscheinen nicht in der Mitarbeiterübersicht.', 'iss-payments-lite') . '</p>';
    echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
    wp_nonce_field('iss_commerce_ledger');
    echo '<input type="hidden" name="action" value="iss_commerce_ledger"><input type="hidden" name="ledger_action" value="sync">';
    submit_button('Buchungsbestand aus WordPress-Terminen aktualisieren', 'secondary', 'submit', false); echo '</form>';
    foreach ($departures as $row) {
        $allocated = ISS_Commerce_Ledger::allocated((int) $row['id']);
        echo '<details><summary>' . esc_html('#' . $row['id'] . ' · ' . $row['title'] . ' · ' . wp_date('d.m.Y H:i T', (int) strtotime($row['starts_at'] . ' UTC'), new DateTimeZone('Europe/Berlin')) . ' · ' . $allocated . '/' . ($row['capacity'] ?? '–') . ' · ' . ((int) $row['booking_open'] ? 'offen' : 'geschlossen') . ((int) $row['test_mode'] ? ' · TESTTERMIN' : '') . ((int) $row['needs_review'] ? ' · Prüfung erforderlich' : '')) . '</summary>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('iss_commerce_ledger');
        echo '<input type="hidden" name="action" value="iss_commerce_ledger"><input type="hidden" name="ledger_action" value="capacity"><input type="hidden" name="ledger_id" value="' . esc_attr($row['id']) . '">';
        echo '<p><label>Kapazität <input type="number" min="0" max="100000" name="capacity" value="' . esc_attr($row['capacity'] ?? '30') . '" required></label> <label><input type="checkbox" name="booking_open" value="1" ' . checked($row['booking_open'], 1, false) . '> Buchung öffnen</label> <label><input type="checkbox" name="test_mode" value="1" ' . checked($row['test_mode'], 1, false) . '> Testtermin</label></p>';
        echo '<p><label><input type="checkbox" name="reconciled" value="1" required> Bestehende Reservierungen und Terminänderungen geprüft</label></p><p><label>Begründung <input name="reason" required maxlength="255"></label></p>';
        submit_button('Termin speichern', 'secondary', 'submit', false); echo '</form></details>';
    }
    $count = ISS_Commerce_Ledger::rows('SELECT COUNT(*) AS total FROM %i WHERE starts_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)', [ISS_Commerce_Ledger::table('departures')]);
    iss_commerce_admin_pagination((int) $count[0]['total'], $page, $view);
    }
    if ($view === 'bookings') {
    echo '<h2>Buchungen</h2><p>Bestätigen, Stornieren und Gutscheinrückbuchungen werden direkt an der betreffenden Buchung ausgeführt.</p>';
    echo '<h3>Vor-Ort-Buchung erfassen</h3><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'; wp_nonce_field('iss_commerce_ledger');
    echo '<input type="hidden" name="action" value="iss_commerce_ledger"><input type="hidden" name="ledger_action" value="create_booking"><input type="hidden" name="request_key" value="' . esc_attr(wp_generate_uuid4()) . '"><p><label>Termin <select name="ledger_id" required><option value="">Termin wählen</option>';
    foreach ($departures as $row) { if ((int) $row['booking_open']) { echo '<option value="' . esc_attr($row['id']) . '">' . esc_html('#' . $row['id'] . ' ' . $row['title'] . ((int) $row['test_mode'] ? ' · TEST' : '') . ' ' . wp_date('d.m.Y H:i', (int) strtotime($row['starts_at'] . ' UTC'), new DateTimeZone('Europe/Berlin'))) . '</option>'; } }
    echo '</select></label></p><p><label>Name <input name="customer_name" required></label> <label>E-Mail <input name="customer_email" type="email" required></label></p><p><label>Plätze gesamt <input name="tickets" type="number" min="1" value="1" required></label> <label>Davon ermäßigt <input name="reduced_tickets" type="number" min="0" value="0"></label></p>';
    submit_button('Verbindlich buchen · Zahlung vor Ort', 'secondary', 'submit', false, $departures ? [] : ['disabled' => 'disabled']); echo '</form>';
    $bookings = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE departure_id > 0 ORDER BY id DESC LIMIT 50 OFFSET %d', [iss_payments_lite_requests_table_name(), ($page - 1) * 50]);
    echo '<div class="iss-commerce-table" role="region" aria-label="Buchungsliste" tabindex="0"><table class="widefat striped"><thead><tr><th>Referenz / Termin</th><th>Tickets / Betrag</th><th>Buchung / Zahlung</th><th>Reserviert bis</th><th>Verlauf</th></tr></thead><tbody>';
    foreach ($bookings as $row) {
        $entry = json_decode($row['payload'], true) ?: [];
        echo '<tr><td>' . esc_html('#' . $row['id'] . ' ' . $row['booking_reference'] . ' / ' . $row['departure_id'] . ((int) $row['test_mode'] ? ' TEST' : '')) . '</td><td>' . esc_html($row['quantity'] . ' (' . ($entry['regular_tickets'] ?? $row['quantity']) . ' regulär / ' . ($entry['reduced_tickets'] ?? 0) . ' ermäßigt) · ' . number_format_i18n((int) $row['amount_cents'] / 100, 2) . ' EUR · Gutschein ' . number_format_i18n((int) $row['voucher_cents'] / 100, 2)) . '</td><td>' . esc_html($row['booking_state'] . ' / ' . ($row['payment_status'] ?: $row['payment_method'])) . '</td><td>' . esc_html($row['hold_expires_at'] ? wp_date('d.m.Y H:i T', (int) strtotime($row['hold_expires_at'] . ' UTC'), new DateTimeZone('Europe/Berlin')) : '–') . '</td><td><details><summary>Ereignisse</summary>';
        foreach (ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE booking_id = %d ORDER BY id', [ISS_Commerce_Ledger::table('events'), $row['id']]) as $event) { echo '<p>' . esc_html($event['created_at'] . ' UTC · ' . $event['event_type'] . ' · ' . $event['before_state'] . ' → ' . $event['after_state'] . ' · ' . $event['actor'] . ' · ' . $event['reason']) . '</p>'; }
        echo '</details><details><summary>Diese Buchung bearbeiten</summary>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('iss_commerce_ledger');
        echo '<input type="hidden" name="action" value="iss_commerce_ledger"><input type="hidden" name="ledger_id" value="' . esc_attr($row['id']) . '"><p><label>Aktion für ' . esc_html($row['booking_reference']) . '<select name="ledger_action" required><option value="">Bitte wählen</option><option value="cancelled">Stornieren</option><option value="confirmed">Bestätigen / Zahlungsfall auflösen</option><option value="reverse_voucher">Gutschein nach Storno rückbuchen</option></select></label></p><p><label>Begründung <input name="reason" required maxlength="255"></label></p>';
        submit_button('Diese Buchung aktualisieren', 'secondary', 'submit', false);
        echo '</form></details></td></tr>';
    }
    echo '</tbody></table></div>';
    $count = ISS_Commerce_Ledger::rows('SELECT COUNT(*) AS total FROM %i WHERE departure_id > 0', [iss_payments_lite_requests_table_name()]);
    iss_commerce_admin_pagination((int) $count[0]['total'], $page, $view);
    }
    if ($view !== 'delivery') { return; }
    echo '<h2>Versand und Hintergrundlauf</h2>';
    $receipt = get_option('iss_commerce_worker_receipt', []);
    echo '<p>' . esc_html('Letzter Lauf: ' . (!empty($receipt['finished_at']) ? wp_date('d.m.Y H:i:s', $receipt['finished_at']) : 'noch nicht eingerichtet') . ' · Mitarbeitermail: ' . (ISS_Commerce_Worker::mail_enabled() ? 'aktiv' : 'inaktiv')) . '</p>';
    foreach (ISS_Commerce_Ledger::rows('SELECT * FROM %i ORDER BY id DESC LIMIT 30', [ISS_Commerce_Ledger::table('outbox')]) as $mail) {
        echo '<p>' . esc_html('#' . $mail['id'] . ' · Termin ' . $mail['departure_id'] . ' · ' . $mail['message_type'] . ' · ' . $mail['state'] . ' · Versuche: ' . $mail['attempts']) . '</p>';
        if (!in_array($mail['state'], ['failed', 'uncertain'], true)) { continue; }
        echo '<details><summary>Diese Nachricht erneut versuchen</summary><form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('iss_commerce_ledger');
        echo '<input type="hidden" name="action" value="iss_commerce_ledger"><input type="hidden" name="ledger_action" value="mail_retry"><input type="hidden" name="ledger_id" value="' . esc_attr($mail['id']) . '"><p><label>Ergebnis der Postfachprüfung <input name="reason" required></label></p><p>Bei unklarem Versand zuerst das Postfach prüfen. Ein erneuter Versuch kann eine zweite Zustellung verursachen.</p>';
        submit_button('Diese Nachricht nach Postfachprüfung erneut versuchen', 'secondary', 'submit', false);
        echo '</form></details>';
    }
}

function iss_commerce_admin_pagination(int $total, int $page, string $view): void {
    echo '<p>' . esc_html(sprintf('%d Einträge · Seite %d von %d', $total, $page, max(1, (int) ceil($total / 50)))) . '</p>';
    echo wp_kses_post(paginate_links(['base' => add_query_arg('paged', '%#%', iss_commerce_admin_url(['view' => $view])), 'format' => '', 'current' => $page, 'total' => max(1, (int) ceil($total / 50))]));
}
