<?php
/** Commerce-owned inventory. All allocations lock departure, request, then voucher. */
if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Owner tables only; identifiers and values use wpdb placeholders.
final class ISS_Commerce_Ledger {
    public static function table(string $role): string {
        global $wpdb;
        if (!in_array($role, ['departures', 'vouchers', 'redemptions', 'events', 'outbox'], true)) { throw new InvalidArgumentException('Unknown commerce table.'); }
        return $wpdb->prefix . 'iss_commerce_' . $role;
    }

    public static function schema(): array {
        global $wpdb;
        $collate = $wpdb->get_charset_collate();
        $definitions = [
            'departures' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                departure_key char(64) NOT NULL,
                source_post_id bigint(20) unsigned NOT NULL,
                source_post_type varchar(100) NOT NULL,
                slot_id varchar(191) NOT NULL,
                starts_at datetime NOT NULL,
                schedule_revision int(11) unsigned NOT NULL DEFAULT 1,
                capacity int(11) unsigned DEFAULT NULL,
                booking_open tinyint(1) NOT NULL DEFAULT 0,
                needs_review tinyint(1) NOT NULL DEFAULT 1,
                test_mode tinyint(1) NOT NULL DEFAULT 1,
                title varchar(255) NOT NULL DEFAULT '',
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY departure_key (departure_key),
                KEY due_departures (starts_at, booking_open),
                KEY source_lookup (source_post_id, source_post_type)",
            'vouchers' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                legacy_key char(64) NOT NULL,
                code_hash char(64) NOT NULL,
                amount_cents int(11) unsigned NOT NULL,
                balance_cents int(11) unsigned NOT NULL,
                currency char(3) NOT NULL DEFAULT 'EUR',
                issued_at datetime NOT NULL,
                expires_at datetime NOT NULL,
                enabled tinyint(1) NOT NULL DEFAULT 0,
                test_mode tinyint(1) NOT NULL DEFAULT 1,
                provenance longtext NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY legacy_key (legacy_key),
                UNIQUE KEY code_hash (code_hash)",
            'redemptions' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                voucher_id bigint(20) unsigned NOT NULL,
                booking_id bigint(20) unsigned NOT NULL,
                amount_cents int(11) unsigned NOT NULL,
                state varchar(32) NOT NULL,
                expires_at datetime NOT NULL,
                operation_key char(64) NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY operation_key (operation_key),
                UNIQUE KEY booking_id (booking_id),
                KEY voucher_state (voucher_id, state, expires_at)",
            'events' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                departure_id bigint(20) unsigned NOT NULL DEFAULT 0,
                booking_id bigint(20) unsigned NOT NULL DEFAULT 0,
                voucher_id bigint(20) unsigned NOT NULL DEFAULT 0,
                transition_key char(64) NOT NULL,
                event_type varchar(64) NOT NULL,
                before_state varchar(64) NOT NULL DEFAULT '',
                after_state varchar(64) NOT NULL DEFAULT '',
                actor varchar(64) NOT NULL,
                reason varchar(255) NOT NULL DEFAULT '',
                created_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY transition_key (transition_key),
                KEY booking_history (booking_id, id),
                KEY departure_history (departure_id, id)",
            'outbox' => "id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                message_key char(64) NOT NULL,
                departure_id bigint(20) unsigned NOT NULL,
                schedule_revision int(11) unsigned NOT NULL,
                message_type varchar(32) NOT NULL,
                recipient varchar(191) NOT NULL,
                due_at datetime NOT NULL,
                state varchar(32) NOT NULL DEFAULT 'pending',
                lease_token char(36) NOT NULL DEFAULT '',
                lease_until datetime DEFAULT NULL,
                attempts int(11) unsigned NOT NULL DEFAULT 0,
                next_attempt_at datetime NOT NULL,
                sent_at datetime DEFAULT NULL,
                transport_reference varchar(191) NOT NULL DEFAULT '',
                last_error varchar(255) NOT NULL DEFAULT '',
                PRIMARY KEY  (id),
                UNIQUE KEY message_key (message_key),
                KEY due_mail (state, next_attempt_at, due_at),
                KEY departure_mail (departure_id, schedule_revision)",
        ];
        $sql = [];
        foreach ($definitions as $role => $columns) { $sql[] = 'CREATE TABLE ' . self::table($role) . " (\n{$columns}\n) ENGINE=InnoDB {$collate};"; }
        return $sql;
    }

    public static function ready(): bool {
        return (string) get_option(ISS_PAYMENTS_LITE_SCHEMA_OPTION, '') === ISS_PAYMENTS_LITE_SCHEMA_VERSION;
    }

    public static function intake_enabled(): bool {
        return !defined('ISS_COMMERCE_BOOKINGS_ENABLED') || constant('ISS_COMMERCE_BOOKINGS_ENABLED') !== false;
    }

    public static function rows(string $sql, array $args = []): array {
        global $wpdb;
        $rows = $wpdb->get_results($args ? $wpdb->prepare($sql, ...$args) : $sql, ARRAY_A);
        if ($wpdb->last_error !== '' || !is_array($rows)) { throw new RuntimeException('Commerce read failed.'); }
        return $rows;
    }

    public static function execute(string $sql, array $args = []): int {
        global $wpdb;
        $result = $wpdb->query($args ? $wpdb->prepare($sql, ...$args) : $sql);
        if ($result === false) { throw new RuntimeException('Commerce write failed.'); }
        return (int) $result;
    }

    public static function insert(string $table, array $fields): int {
        global $wpdb;
        if ($wpdb->insert($table, $fields) !== 1) { throw new RuntimeException('Commerce insert failed.'); }
        return (int) $wpdb->insert_id;
    }

    public static function update(string $table, array $fields, int $id): void {
        global $wpdb;
        if ($wpdb->update($table, $fields, ['id' => $id]) === false) { throw new RuntimeException('Commerce update failed.'); }
    }

    /** No HTTP, mail or hooks inside this boundary; retry only server-declared deadlocks. */
    public static function atomic(callable $operation) {
        global $wpdb;
        $state = self::rows('SELECT @@SESSION.in_transaction AS active, @@SESSION.autocommit AS autocommit')[0];
        if ((int) $state['active'] !== 0 || (int) $state['autocommit'] !== 1) { throw new RuntimeException('Commerce requires an independent transaction.'); }
        foreach (array_merge([iss_payments_lite_requests_table_name()], array_map([self::class, 'table'], ['departures', 'vouchers', 'redemptions', 'events', 'outbox'])) as $table) {
            $engine = self::rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', [$table]);
            if (strtolower($engine[0]['ENGINE'] ?? '') !== 'innodb') { throw new RuntimeException('Commerce requires verified InnoDB tables.'); }
        }
        for ($attempt = 0; $attempt < 3; ++$attempt) {
            self::execute('START TRANSACTION');
            try {
                $result = $operation();
                self::execute('COMMIT');
                return $result;
            } catch (Throwable $error) {
                $deadlock = str_contains($wpdb->last_error, 'Deadlock found');
                self::execute('ROLLBACK');
                if (!$deadlock || $attempt === 2) { throw $error; }
                usleep(20000 * ($attempt + 1));
            }
        }
        throw new RuntimeException('Commerce transaction could not complete.');
    }

    public static function departure(int $id, bool $lock = false): array {
        $rows = self::rows('SELECT * FROM %i WHERE id = %d' . ($lock ? ' FOR UPDATE' : ''), [self::table('departures'), $id]);
        if (!$rows) { throw new DomainException('Termin nicht verfügbar.'); }
        return $rows[0];
    }

    public static function booking(int $id, bool $lock = false): array {
        $rows = self::rows('SELECT * FROM %i WHERE id = %d' . ($lock ? ' FOR UPDATE' : ''), [iss_payments_lite_requests_table_name(), $id]);
        if (!$rows) { throw new DomainException('Buchung nicht verfügbar.'); }
        return $rows[0];
    }

    /** Locking read avoids repeatable-read snapshots older than the departure lock. */
    public static function allocated(int $id, bool $lock = false, int $exclude = 0): int {
        $rows = self::rows("SELECT id, quantity FROM %i WHERE departure_id = %d AND id <> %d AND (booking_state IN ('confirmed','legacy_review') OR (booking_state = 'pending' AND hold_expires_at > UTC_TIMESTAMP()))" . ($lock ? ' FOR UPDATE' : ''), [iss_payments_lite_requests_table_name(), $id, $exclude]);
        return array_sum(array_map('intval', array_column($rows, 'quantity')));
    }

    public static function event(int $departure, int $booking, string $type, string $before, string $after, string $reason = '', string $key = '', int $voucher = 0): void {
        self::insert(self::table('events'), ['departure_id' => $departure, 'booking_id' => $booking, 'voucher_id' => $voucher,
            'transition_key' => hash('sha256', $key ?: wp_generate_uuid4()), 'event_type' => $type,
            'before_state' => $before, 'after_state' => $after, 'reason' => substr(sanitize_text_field($reason), 0, 255),
            'actor' => get_current_user_id() ? 'user:' . get_current_user_id() : 'system', 'created_at' => gmdate('Y-m-d H:i:s')]);
    }

    public static function utc(string $date): string {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}:\d{2}/', $date)) { throw new DomainException('Ungültiger Termin.'); }
        $value = date_create_immutable($date, new DateTimeZone('Europe/Berlin'));
        $errors = DateTimeImmutable::getLastErrors();
        if (!$value || ($errors && ($errors['warning_count'] || $errors['error_count']))) { throw new DomainException('Ungültiger Termin.'); }
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    /** Schedule identity survives deletion/recreation of occurrence projection rows. */
    public static function schedule_identity(array $raw): string {
        return hash('sha256', implode('|', [$raw['source_post_type'] ?? '', $raw['source_post_id'] ?? 0, $raw['origin'] ?? '', $raw['source_calendar'] ?? '', $raw['external_id'] ?? '']));
    }

    public static function availability(array $item, array $raw): array {
        if (!in_array($item['source_post_type'] ?? '', ['fuehrung', 'veranstaltung'], true) || ($item['booking_url'] ?? '') === '') { return $item; }
        $item['departure_key'] = self::schedule_identity($raw);
        $item['schedule_cancelled'] = ($raw['availability_state'] ?? '') === 'cancelled' || ($item['availability_state'] ?? '') === 'cancelled';
        if (!self::ready()) { return $item; }
        try {
            $rows = self::rows('SELECT * FROM %i WHERE departure_key = %s', [self::table('departures'), $item['departure_key']]);
            $departure = $rows[0] ?? null;
            $allocated = $departure ? self::allocated((int) $departure['id']) : 0;
        } catch (RuntimeException $error) {
            // A commerce outage closes intake while ordinary editorial pages remain readable.
            $departure = null; $allocated = 0;
        }
        $open = self::intake_enabled() && $departure && (int) $departure['booking_open'] && !(int) $departure['needs_review'] && $departure['capacity'] !== null
            && $departure['starts_at'] === self::utc($item['start_raw']) && !$item['schedule_cancelled'];
        $available = $open ? max(0, (int) $departure['capacity'] - $allocated) : 0;
        $item['capacity'] = $departure && $departure['capacity'] !== null ? (int) $departure['capacity'] : null;
        $item['available'] = $available;
        $item['availability_state'] = $item['schedule_cancelled'] ? 'cancelled' : (!$open ? 'unavailable' : ($available ? 'available' : 'sold_out'));
        $item['availability'] = $item['availability_state'];
        $item['departure_id'] = $departure ? (int) $departure['id'] : 0;
        $item['test_mode'] = $departure ? (bool) $departure['test_mode'] : true;
        return $item;
    }

    public static function sync_schedule(): array {
        $items = iss_occurrences_query(['time_mode' => 'upcoming', 'limit' => -1, 'order' => 'ASC', 'post_types' => ['fuehrung', 'veranstaltung']]);
        $seen = []; $counts = ['created' => 0, 'review' => 0];
        foreach ($items as $item) {
            if (empty($item['departure_key']) || empty($item['slot_id'])) { continue; }
            $key = $item['departure_key']; $seen[$key] = true;
            self::atomic(static function () use ($item, $key, &$counts): void {
                $rows = self::rows('SELECT * FROM %i WHERE departure_key = %s FOR UPDATE', [self::table('departures'), $key]);
                $start = self::utc($item['start_raw']);
                if (!$rows) {
                    $id = self::insert(self::table('departures'), ['departure_key' => $key, 'source_post_id' => $item['source_post_id'], 'source_post_type' => $item['source_post_type'], 'slot_id' => $item['slot_id'], 'starts_at' => $start, 'title' => $item['title'], 'updated_at' => gmdate('Y-m-d H:i:s')]);
                    self::event($id, 0, 'schedule_created', '', 'closed'); ++$counts['created'];
                } else {
                    $row = $rows[0];
                    if ($row['starts_at'] !== $start || (!empty($item['schedule_cancelled']) && !(int) $row['needs_review'])) {
                        self::update(self::table('departures'), ['starts_at' => $start, 'booking_open' => 0, 'needs_review' => 1, 'schedule_revision' => (int) $row['schedule_revision'] + 1, 'updated_at' => gmdate('Y-m-d H:i:s')], (int) $row['id']);
                        self::event((int) $row['id'], 0, 'schedule_changed', $row['starts_at'], $start, 'Staff review required'); ++$counts['review'];
                        self::execute("UPDATE %i SET state = 'superseded' WHERE departure_id = %d AND state IN ('pending','retry')", [self::table('outbox'), $row['id']]);
                    }
                }
            });
        }
        foreach (self::rows('SELECT * FROM %i WHERE starts_at > UTC_TIMESTAMP() AND booking_open = 1', [self::table('departures')]) as $row) {
            if (!isset($seen[$row['departure_key']])) { self::configure((int) $row['id'], (int) $row['capacity'], false, (bool) $row['test_mode'], 'Schedule missing', false); ++$counts['review']; }
        }
        return $counts;
    }

    public static function configure(int $id, int $capacity, bool $open, bool $test, string $reason, bool $reconciled): void {
        if ($capacity < 0 || $capacity > 100000 || trim($reason) === '' || ($open && !$reconciled)) { throw new DomainException('Kapazität, Begründung und Abgleich vorhandener Buchungen sind erforderlich.'); }
        self::atomic(static function () use ($id, $capacity, $open, $test, $reason, $reconciled): void {
            $row = self::departure($id, true);
            if ($capacity < self::allocated($id, true)) { throw new DomainException('Kapazität liegt unter den reservierten Plätzen.'); }
            $existing = self::rows('SELECT id FROM %i WHERE departure_id = %d LIMIT 1 FOR UPDATE', [iss_payments_lite_requests_table_name(), $id]);
            if ($existing && (int) $row['test_mode'] !== (int) $test) { throw new DomainException('Testmodus eines belegten Termins kann nicht geändert werden.'); }
            self::update(self::table('departures'), ['capacity' => $capacity, 'booking_open' => (int) $open, 'test_mode' => (int) $test, 'needs_review' => (int) !$reconciled, 'updated_at' => gmdate('Y-m-d H:i:s')], $id);
            self::event($id, 0, 'capacity_configured', (string) $row['capacity'] . ':' . $row['booking_open'], $capacity . ':' . (int) $open, $reason);
        });
    }

    public static function apply_payment(array $original, array $fields): array {
        if (empty($original['departure_id'])) {
            if (array_intersect_key($original, $fields) !== $fields) { iss_commerce_payment_write((int) $original['id'], $fields); }
            return array_merge($original, $fields);
        }
        return self::atomic(static function () use ($original, $fields): array {
            $departure = self::departure((int) $original['departure_id'], true);
            $row = self::booking((int) $original['id'], true);
            if (!empty($row['provider_payment_id']) && $row['provider_payment_id'] !== $fields['provider_payment_id']) { throw new RuntimeException('Payment binding conflict.'); }
            $status = $fields['payment_status']; $before = $row['booking_state'];
            // Delayed provider snapshots must not undo a settled payment.
            $settled = ['paid', 'partially_refunded', 'refunded', 'charged_back'];
            if (in_array($row['payment_status'], $settled, true) && !in_array($status, $settled, true)) { return $row; }
            if (in_array($row['payment_status'], ['refunded','charged_back'], true) && $status === 'paid') { return $row; }
            if (in_array($row['payment_status'], ['failed','canceled','expired'], true) && in_array($status, ['open','pending','authorized'], true)) { return $row; }
            $next = $before;
            if ($status === 'paid' && !in_array($before, ['confirmed', 'payment_exception'], true)) {
                $can_allocate = $before !== 'cancelled' && (int) $departure['booking_open'] && !(int) $departure['needs_review']
                    && $departure['starts_at'] > gmdate('Y-m-d H:i:s')
                    && (int) $row['quantity'] + self::allocated((int) $departure['id'], true, (int) $row['id']) <= (int) $departure['capacity'];
                if ($can_allocate && ISS_Commerce_Vouchers::transition($row, 'commit')) {
                    $next = 'confirmed'; $fields['confirmed_at'] = gmdate('Y-m-d H:i:s');
                } else {
                    $next = 'payment_exception'; ISS_Commerce_Vouchers::transition($row, 'release');
                }
            } elseif (in_array($status, ['failed', 'canceled', 'expired'], true) && $before === 'pending') {
                $next = 'expired'; ISS_Commerce_Vouchers::transition($row, 'release');
            }
            if ($next !== $before) {
                $fields['booking_state'] = $next; $fields['hold_expires_at'] = null;
                self::event((int) $departure['id'], (int) $row['id'], 'payment_allocation', $before, $next);
            }
            if ($row['payment_status'] !== $status) { self::event((int) $departure['id'], (int) $row['id'], 'payment_status', $row['payment_status'], $status); }
            if (array_intersect_key($row, $fields) !== $fields) { self::update(iss_payments_lite_requests_table_name(), $fields + ['updated_at' => current_time('mysql')], (int) $row['id']); }
            return array_merge($row, $fields);
        });
    }

    /** Legacy snapshot reservations retain seats pending staff reconciliation, without implying payment settlement. */
    public static function import_reservations(array $records, bool $apply): array {
        $result = ['new' => 0, 'existing' => 0, 'seats' => 0];
        foreach ($records as $record) {
            if (empty($record['legacy_identity']) || empty($record['departure_key']) || !is_int($record['quantity'] ?? null) || $record['quantity'] < 1
                || !is_int($record['amount_cents'] ?? null) || $record['amount_cents'] < 0 || empty($record['source_sha256'])) { throw new DomainException('Invalid legacy reservation manifest.'); }
            $key = hash('sha256', 'legacy-reservation:' . $record['legacy_identity']);
            self::atomic(static function () use ($record, $key, $apply, &$result): void {
                $departures = self::rows('SELECT * FROM %i WHERE departure_key = %s FOR UPDATE', [self::table('departures'), $record['departure_key']]);
                if (!$departures) { throw new DomainException('Legacy departure mapping not found.'); }
                $departure = $departures[0];
                $existing = self::rows('SELECT * FROM %i WHERE request_key = %s FOR UPDATE', [iss_payments_lite_requests_table_name(), $key]);
                $fingerprint = hash('sha256', (string) wp_json_encode($record));
                if ($existing) {
                    if (!hash_equals($existing[0]['request_fingerprint'], $fingerprint)) { throw new DomainException('Legacy reservation changed; reconcile explicitly.'); }
                    ++$result['existing']; return;
                }
                if ((int) $departure['booking_open']) { throw new DomainException('Close the departure before importing reservations.'); }
                ++$result['new']; $result['seats'] += $record['quantity'];
                if (!$apply) { return; }
                $entry = ['source_post_id' => $departure['source_post_id'], 'source_post_type' => $departure['source_post_type'], 'title' => $departure['title'], 'slot_id' => $departure['slot_id'],
                    'tickets' => $record['quantity'], 'regular_tickets' => $record['regular_tickets'] ?? $record['quantity'], 'reduced_tickets' => $record['reduced_tickets'] ?? 0,
                    'amount_cents' => $record['amount_cents'], 'payment' => 'legacy', 'name' => $record['name'] ?? '', 'email' => $record['email'] ?? '',
                    'legacy_identity' => $record['legacy_identity'], 'source_sha256' => $record['source_sha256'], 'legacy_status' => $record['legacy_status'] ?? '', 'request_key' => $key, 'request_fingerprint' => $fingerprint];
                $id = iss_payments_lite_insert_request('booking', $entry);
                if (!$id) { throw new RuntimeException('Legacy reservation could not be preserved.'); }
                self::update(iss_payments_lite_requests_table_name(), ['departure_id' => $departure['id'], 'booking_reference' => 'ISS-' . strtoupper(bin2hex(random_bytes(8))), 'booking_state' => 'legacy_review', 'test_mode' => $departure['test_mode']], $id);
                self::update(self::table('departures'), ['needs_review' => 1], (int) $departure['id']);
                self::event((int) $departure['id'], $id, 'legacy_reservation', '', 'legacy_review', 'Private snapshot; payment and acceptance require reconciliation', $key);
            });
        }
        return $result;
    }

    public static function expire(int $id): void {
        $original = self::booking($id);
        if (empty($original['departure_id'])) { return; }
        self::atomic(static function () use ($id, $original): void {
            self::departure((int) $original['departure_id'], true);
            $row = self::booking($id, true);
            if ($row['booking_state'] !== 'pending' || $row['hold_expires_at'] > gmdate('Y-m-d H:i:s')) { return; }
            ISS_Commerce_Vouchers::transition($row, 'release');
            self::update(iss_payments_lite_requests_table_name(), ['booking_state' => 'expired', 'hold_expires_at' => null], $id);
            self::event((int) $row['departure_id'], $id, 'hold_expired', 'pending', 'expired');
        });
    }

    public static function staff_action(int $id, string $action, string $reason): void {
        if (trim($reason) === '') { throw new DomainException('Begründung erforderlich.'); }
        $original = self::booking($id);
        if (empty($original['departure_id'])) { throw new DomainException('Historische Anfrage zuerst einem Termin zuordnen.'); }
        self::atomic(static function () use ($id, $action, $reason, $original): void {
            $departure = self::departure((int) $original['departure_id'], true); $row = self::booking($id, true);
            if ($action === 'reverse_voucher') { ISS_Commerce_Vouchers::transition($row, 'reverse'); return; }
            if (!in_array($action, ['cancelled', 'confirmed'], true)) { throw new DomainException('Unbekannte Buchungsaktion.'); }
            if ($row['booking_state'] === $action) { return; }
            if ($action === 'confirmed') {
                if (!(int) $departure['booking_open'] || (int) $departure['needs_review'] || $departure['starts_at'] <= gmdate('Y-m-d H:i:s')
                    || ($row['payment_method'] === 'mollie' && (int) $row['provider_amount_cents'] > 0 && $row['payment_status'] !== 'paid')
                    || (int) $row['quantity'] + self::allocated((int) $departure['id'], true, $id) > (int) $departure['capacity']
                    || !ISS_Commerce_Vouchers::transition($row, 'commit')) { throw new DomainException('Buchung kann nicht bestätigt werden: Plätze oder Zahlung prüfen.'); }
            } else { ISS_Commerce_Vouchers::transition($row, 'release'); }
            self::update(iss_payments_lite_requests_table_name(), ['booking_state' => $action, 'status' => $action, 'hold_expires_at' => null,
                $action === 'confirmed' ? 'confirmed_at' : 'cancelled_at' => gmdate('Y-m-d H:i:s')], $id);
            self::event((int) $row['departure_id'], $id, 'staff_booking', $row['booking_state'], $action, $reason);
        });
    }

    public static function admit(array $entry, array $identity, string $voucher_code = ''): array {
        if (!self::intake_enabled()) { throw new DomainException('Neue Buchungen sind vorübergehend geschlossen. Bestehende Buchungen bleiben erhalten.'); }
        if (!$identity['key']) { throw new DomainException('Bitte das Buchungsformular neu öffnen.'); }
        return self::atomic(static function () use ($entry, $identity, $voucher_code): array {
            $departure = self::departure((int) $entry['departure_id'], true);
            $existing = self::rows('SELECT * FROM %i WHERE request_key = %s FOR UPDATE', [iss_payments_lite_requests_table_name(), $identity['key']]);
            if ($existing) {
                if (!hash_equals($existing[0]['request_fingerprint'], $identity['fingerprint'])) { throw new DomainException('Anfragekennung wurde mit anderen Angaben verwendet.'); }
                return ['id' => (int) $existing[0]['id'], 'created' => false];
            }
            if (!(int) $departure['booking_open'] || (int) $departure['needs_review'] || $departure['capacity'] === null || $departure['starts_at'] <= gmdate('Y-m-d H:i:s')
                || $departure['starts_at'] !== self::utc($entry['start'])) { throw new DomainException('Dieser Termin ist derzeit nicht buchbar.'); }
            if ((int) $entry['tickets'] < 1 || (int) $entry['tickets'] + self::allocated((int) $departure['id'], true) > (int) $departure['capacity']) { throw new DomainException('Nicht genügend Plätze verfügbar. Bitte einen anderen Termin wählen.'); }
            if ($entry['payment'] === 'mollie' && !(int) $departure['test_mode']) { throw new DomainException('Online-Zahlungen sind noch im Testbetrieb.'); }
            if (!empty($entry['duplicate_hash']) && iss_payments_lite_recent_duplicate_exists('booking', $entry['duplicate_hash'])) { throw new DomainException('Diese Anfrage wurde bereits gesendet.'); }
            $holds = self::rows("SELECT id FROM %i WHERE departure_id = %d AND booking_state = 'pending' AND hold_expires_at > UTC_TIMESTAMP() AND (customer_email = %s OR (ip_address <> '' AND ip_address = %s)) FOR UPDATE", [iss_payments_lite_requests_table_name(), $departure['id'], $entry['email'], $entry['ip'] ?? '']);
            if (count($holds) >= 2) { throw new DomainException('Bitte zunächst Ihre bestehende Buchung abschließen.'); }
            $deadline = gmdate('Y-m-d H:i:s', min(time() + 15 * MINUTE_IN_SECONDS, (int) strtotime($departure['starts_at'] . ' UTC')));
            $voucher = $voucher_code !== '' ? ISS_Commerce_Vouchers::reserve_value($voucher_code, (int) $entry['amount_cents'], (bool) $departure['test_mode'], $deadline) : null;
            $credit = $voucher ? $voucher['credit'] : 0;
            $remainder = (int) $entry['amount_cents'] - $credit;
            $pending = $entry['payment'] === 'mollie' && $remainder > 0;
            $entry['request_key'] = $identity['key']; $entry['request_fingerprint'] = $identity['fingerprint'];
            $id = iss_payments_lite_insert_request('booking', $entry);
            if (!$id) { throw new RuntimeException('Booking insert failed.'); }
            self::update(iss_payments_lite_requests_table_name(), ['departure_id' => $departure['id'], 'booking_reference' => 'ISS-' . strtoupper(bin2hex(random_bytes(8))), 'booking_state' => $pending ? 'pending' : 'confirmed',
                'hold_expires_at' => $pending ? $deadline : null, 'confirmed_at' => $pending ? null : gmdate('Y-m-d H:i:s'), 'voucher_cents' => $credit,
                'provider_amount_cents' => $entry['payment'] === 'mollie' ? $remainder : 0, 'test_mode' => $departure['test_mode']], $id);
            if ($voucher) { ISS_Commerce_Vouchers::record($voucher, $id, $pending, $deadline); }
            self::event((int) $departure['id'], $id, 'booking_admitted', '', $pending ? 'pending' : 'confirmed', '', 'admit:' . $identity['key']);
            return ['id' => $id, 'created' => true];
        });
    }
}
