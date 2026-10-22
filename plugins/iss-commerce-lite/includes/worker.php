<?php
/** Bounded owner jobs and durable staff mail; no customer mail is enabled here. */
if (!defined('ABSPATH')) { exit; }

final class ISS_Commerce_Worker {
    public static function enqueue_digests(): void {
        foreach (ISS_Commerce_Ledger::rows('SELECT id FROM %i WHERE starts_at > UTC_TIMESTAMP() OR booking_open = 1', [ISS_Commerce_Ledger::table('departures')]) as $candidate) {
            ISS_Commerce_Ledger::atomic(static function () use ($candidate): void {
                $departure = ISS_Commerce_Ledger::departure((int) $candidate['id'], true);
                $id = (int) $departure['id']; $outbox = ISS_Commerce_Ledger::table('outbox');
                if (!(int) $departure['booking_open'] || (int) $departure['needs_review'] || (int) $departure['test_mode']) {
                    ISS_Commerce_Ledger::execute("UPDATE %i SET state = 'superseded' WHERE departure_id = %d AND state IN ('pending','retry')", [$outbox, $id]); return;
                }
                $revision = (int) $departure['schedule_revision'];
                $key = hash('sha256', 'digest:' . $id . ':' . $revision);
                if (ISS_Commerce_Ledger::rows('SELECT id FROM %i WHERE message_key = %s', [$outbox, $key])) { return; }
                $prior = ISS_Commerce_Ledger::rows("SELECT id FROM %i WHERE departure_id = %d AND state = 'sent' LIMIT 1", [$outbox, $id]);
                $due = gmdate('Y-m-d H:i:s', (int) strtotime($departure['starts_at'] . ' UTC') - DAY_IN_SECONDS);
                ISS_Commerce_Ledger::insert($outbox, ['message_key' => $key, 'departure_id' => $id, 'schedule_revision' => $revision,
                    'message_type' => $prior ? 'correction' : 'digest', 'recipient' => 'info@industriesalon.de', 'due_at' => $due,
                    'next_attempt_at' => $due, 'state' => $departure['starts_at'] <= gmdate('Y-m-d H:i:s') ? 'missed' : 'pending']);
            });
        }
    }

    public static function digest(array $departure): string {
        $rows = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE departure_id = %d AND test_mode = 0 ORDER BY id', [iss_payments_lite_requests_table_name(), $departure['id']]);
        $totals = ['bookings' => 0, 'people' => 0, 'regular' => 0, 'reduced' => 0, 'paid_cents' => 0, 'voucher_cents' => 0, 'onsite_cents' => 0, 'holds' => 0, 'exceptions' => 0];
        foreach ($rows as $row) {
            if (in_array($row['booking_state'], ['payment_exception', 'legacy_review'], true)) { ++$totals['exceptions']; }
            if ($row['booking_state'] === 'pending' && $row['hold_expires_at'] > gmdate('Y-m-d H:i:s')) { $totals['holds'] += (int) $row['quantity']; }
            if ($row['booking_state'] !== 'confirmed') { continue; }
            $entry = json_decode($row['payload'], true) ?: [];
            ++$totals['bookings']; $totals['people'] += (int) $row['quantity'];
            $totals['reduced'] += (int) ($entry['reduced_tickets'] ?? 0); $totals['regular'] += (int) ($entry['regular_tickets'] ?? $row['quantity']);
            $totals['voucher_cents'] += (int) $row['voucher_cents'];
            if ($row['payment_method'] === 'onsite') { $totals['onsite_cents'] += (int) $row['amount_cents'] - (int) $row['voucher_cents']; }
            elseif ($row['payment_status'] === 'paid') { $totals['paid_cents'] += (int) $row['provider_amount_cents']; }
            elseif ((int) $row['provider_amount_cents'] > 0) { ++$totals['exceptions']; }
        }
        return implode("\n", [
            'Führung: ' . $departure['title'], 'Beginn: ' . wp_date('d.m.Y H:i T', (int) strtotime($departure['starts_at'] . ' UTC'), new DateTimeZone('Europe/Berlin')),
            'Bestätigte Buchungen: ' . $totals['bookings'], 'Teilnehmende: ' . $totals['people'], 'Regulär: ' . $totals['regular'], 'Ermäßigt: ' . $totals['reduced'],
            'Online bezahlt: ' . number_format($totals['paid_cents'] / 100, 2, ',', '.') . ' EUR',
            'Gutscheine: ' . number_format($totals['voucher_cents'] / 100, 2, ',', '.') . ' EUR',
            'Vor Ort zu zahlen: ' . number_format($totals['onsite_cents'] / 100, 2, ',', '.') . ' EUR',
            'Noch reservierte Plätze (nicht bestätigt): ' . $totals['holds'], 'Zahlungsfälle zur Prüfung: ' . $totals['exceptions'],
            admin_url('tools.php?page=iss-payments-lite-requests&departure_id=' . (int) $departure['id']),
        ]);
    }

    public static function mail_enabled(): bool {
        return defined('ISS_COMMERCE_STAFF_MAIL') && constant('ISS_COMMERCE_STAFF_MAIL') === true;
    }

    public static function dispatch(int $id): void {
        $candidate = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE id = %d', [ISS_Commerce_Ledger::table('outbox'), $id])[0] ?? null;
        if (!$candidate || !self::mail_enabled()) { return; }
        $message = ISS_Commerce_Ledger::atomic(static function () use ($candidate, $id): array {
            $departure = ISS_Commerce_Ledger::departure((int) $candidate['departure_id'], true);
            $row = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE id = %d FOR UPDATE', [ISS_Commerce_Ledger::table('outbox'), $id])[0];
            if (!in_array($row['state'], ['pending', 'retry'], true) || $row['due_at'] > gmdate('Y-m-d H:i:s') || $row['next_attempt_at'] > gmdate('Y-m-d H:i:s')) { return []; }
            $state = '';
            if ((int) $departure['test_mode'] || !(int) $departure['booking_open'] || (int) $departure['needs_review'] || $row['schedule_revision'] !== $departure['schedule_revision']) { $state = 'superseded'; }
            elseif ($departure['starts_at'] <= gmdate('Y-m-d H:i:s')) { $state = 'missed'; }
            if ($state) { ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('outbox'), ['state' => $state], $id); return []; }
            $row['lease_token'] = wp_generate_uuid4();
            ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('outbox'), ['state' => 'sending', 'lease_token' => $row['lease_token'], 'lease_until' => gmdate('Y-m-d H:i:s', time() + 120), 'attempts' => (int) $row['attempts'] + 1], $id);
            return ['row' => $row, 'body' => self::digest($departure), 'title' => $departure['title']];
        });
        if (!$message) { return; }
        $row = $message['row'];
        $reference = '<iss-' . $row['message_key'] . '@industriesalon.de>';
        $configure = static function ($mailer) use ($reference): void {
            $mailer->MessageID = $reference;
            $mailer->Timeout = 15;
            if (defined('ISS_COMMERCE_SMTP_HOST')) {
                $mailer->isSMTP(); $mailer->Host = (string) constant('ISS_COMMERCE_SMTP_HOST');
                $mailer->Port = defined('ISS_COMMERCE_SMTP_PORT') ? (int) constant('ISS_COMMERCE_SMTP_PORT') : 587;
                $mailer->SMTPAuth = defined('ISS_COMMERCE_SMTP_USER') && (string) constant('ISS_COMMERCE_SMTP_USER') !== '';
                $mailer->Username = $mailer->SMTPAuth ? (string) constant('ISS_COMMERCE_SMTP_USER') : '';
                $mailer->Password = defined('ISS_COMMERCE_SMTP_PASSWORD') ? (string) constant('ISS_COMMERCE_SMTP_PASSWORD') : '';
                $mailer->SMTPSecure = defined('ISS_COMMERCE_SMTP_SECURITY') ? (string) constant('ISS_COMMERCE_SMTP_SECURITY') : 'tls';
                $mailer->SMTPDebug = 0;
            }
        };
        $from = defined('ISS_COMMERCE_MAIL_FROM') ? sanitize_email((string) constant('ISS_COMMERCE_MAIL_FROM')) : '';
        $sent = false;
        add_action('phpmailer_init', $configure, 100);
        try {
            if (!$from) { throw new RuntimeException('Staff mail sender not configured.'); }
            $sent = wp_mail('info@industriesalon.de', ($row['message_type'] === 'correction' ? 'Korrektur: ' : '') . 'Führungsübersicht: ' . $message['title'], $message['body'], ['From: Industriesalon <' . $from . '>']);
        } catch (Throwable $error) { $sent = false; }
        finally { remove_action('phpmailer_init', $configure, 100); }
        $state = $sent ? 'sent' : ((int) $row['attempts'] >= 4 ? 'failed' : 'retry');
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lease-owned completion; no cached reads.
        $changed = $wpdb->update(ISS_Commerce_Ledger::table('outbox'), [
            'state' => $state, 'sent_at' => $sent ? gmdate('Y-m-d H:i:s') : null, 'lease_token' => '', 'lease_until' => null,
            'transport_reference' => $reference, 'last_error' => $sent ? '' : 'Mail transport did not confirm acceptance; inspect delivery before retry.',
            'next_attempt_at' => gmdate('Y-m-d H:i:s', time() + min(3600, 60 * (2 ** (int) $row['attempts']))),
        ], ['id' => $id, 'state' => 'sending', 'lease_token' => $row['lease_token']]);
        if ($changed !== 1) { throw new RuntimeException('Mail delivery outcome uncertain; inspect lease and mailbox.'); }
    }

    public static function recover_mail_leases(): int {
        return ISS_Commerce_Ledger::execute("UPDATE %i SET state = 'uncertain', last_error = 'Worker lease expired; verify mailbox before explicit retry.' WHERE state = 'sending' AND lease_until <= UTC_TIMESTAMP()", [ISS_Commerce_Ledger::table('outbox')]);
    }

    public static function run(int $batch = 20, int $seconds = 40): array {
        global $wpdb;
        $lock = 'iss_commerce_worker_' . substr(hash('sha256', constant('DB_NAME') . '|' . $wpdb->prefix), 0, 40);
        $got = ISS_Commerce_Ledger::rows('SELECT GET_LOCK(%s, 0) AS acquired', [$lock]);
        if ((int) $got[0]['acquired'] !== 1) { return ['busy' => true]; }
        $end = microtime(true) + min(45, max(1, $seconds)); $batch = min(50, max(1, $batch)); $counts = ['expired' => 0, 'payments' => 0, 'mail' => 0, 'errors' => 0];
        try {
            ISS_Commerce_Ledger::sync_schedule();
            foreach (ISS_Commerce_Ledger::rows("SELECT id FROM %i WHERE booking_state = 'pending' AND hold_expires_at <= UTC_TIMESTAMP() ORDER BY hold_expires_at LIMIT %d", [iss_payments_lite_requests_table_name(), $batch]) as $row) { ISS_Commerce_Ledger::expire((int) $row['id']); ++$counts['expired']; }
            $payments = ISS_Commerce_Ledger::rows("SELECT id FROM %i WHERE payment_method = 'mollie' AND (provider_payment_id IS NOT NULL OR payment_started_at > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 45 MINUTE)) AND payment_status NOT IN ('refunded','charged_back','failed','canceled','expired') ORDER BY payment_checked_at, id LIMIT %d", [iss_payments_lite_requests_table_name(), $batch]);
            foreach ($payments as $row) {
                if (microtime(true) >= $end) { break; }
                try { iss_commerce_mollie_payment((int) $row['id'], true); ++$counts['payments']; }
                catch (RuntimeException $error) { ++$counts['errors']; }
                ISS_Commerce_Ledger::update(iss_payments_lite_requests_table_name(), ['payment_checked_at' => gmdate('Y-m-d H:i:s')], (int) $row['id']);
            }
            self::enqueue_digests();
            self::recover_mail_leases();
            foreach (ISS_Commerce_Ledger::rows("SELECT id FROM %i WHERE state IN ('pending','retry') AND due_at <= UTC_TIMESTAMP() AND next_attempt_at <= UTC_TIMESTAMP() ORDER BY due_at LIMIT %d", [ISS_Commerce_Ledger::table('outbox'), $batch]) as $row) {
                if (microtime(true) >= $end) { break; }
                self::dispatch((int) $row['id']); ++$counts['mail'];
            }
            update_option('iss_commerce_worker_receipt', ['finished_at' => time(), 'ok' => $counts['errors'] === 0, 'counts' => $counts], false);
            return $counts;
        } catch (Throwable $error) {
            update_option('iss_commerce_worker_receipt', ['finished_at' => time(), 'ok' => false, 'counts' => $counts, 'error' => 'Commerce job failed; inspect owner schema and provider connectivity.'], false);
            throw $error;
        } finally { ISS_Commerce_Ledger::rows('SELECT RELEASE_LOCK(%s)', [$lock]); }
    }
}
