<?php
/** Legacy redemption only. Codes and source customer records never enter event logs. */
if (!defined('ABSPATH')) { exit; }

final class ISS_Commerce_Vouchers {
    public static function fingerprint(string $code): string {
        $key = defined('ISS_COMMERCE_VOUCHER_KEY') ? (string) constant('ISS_COMMERCE_VOUCHER_KEY') : '';
        if (strlen($key) < 32) { throw new DomainException('Gutscheine sind noch nicht freigeschaltet.'); }
        $code = strtoupper(trim($code));
        if ($code === '' || strlen($code) > 191) { throw new DomainException('Gutschein konnte nicht geprüft werden.'); }
        return hash_hmac('sha256', $code, $key);
    }

    public static function reserve_value(string $code, int $total, bool $test, string &$deadline): array {
        $rows = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE code_hash = %s FOR UPDATE', [ISS_Commerce_Ledger::table('vouchers'), self::fingerprint($code)]);
        $row = $rows[0] ?? [];
        if (!$row || !(int) $row['enabled'] || (bool) $row['test_mode'] !== $test || $row['currency'] !== 'EUR'
            || $row['issued_at'] > gmdate('Y-m-d H:i:s') || $row['expires_at'] <= gmdate('Y-m-d H:i:s')) { throw new DomainException('Gutschein nicht gültig oder noch nicht abgeglichen. Bitte kontaktieren Sie uns.'); }
        $reserved = ISS_Commerce_Ledger::rows("SELECT amount_cents FROM %i WHERE voucher_id = %d AND state = 'reserved' AND expires_at > UTC_TIMESTAMP() FOR UPDATE", [ISS_Commerce_Ledger::table('redemptions'), $row['id']]);
        $available = (int) $row['balance_cents'] - array_sum(array_map('intval', array_column($reserved, 'amount_cents')));
        if ($available <= 0 || $total <= 0) { throw new DomainException('Gutschein hat kein verfügbares Guthaben.'); }
        $deadline = min($deadline, $row['expires_at']);
        return ['row' => $row, 'credit' => min($total, $available)];
    }

    public static function record(array $voucher, int $booking, bool $pending, string $deadline): void {
        $row = $voucher['row']; $credit = $voucher['credit'];
        ISS_Commerce_Ledger::insert(ISS_Commerce_Ledger::table('redemptions'), ['voucher_id' => $row['id'], 'booking_id' => $booking, 'amount_cents' => $credit, 'state' => $pending ? 'reserved' : 'committed', 'expires_at' => $deadline, 'operation_key' => hash('sha256', 'redemption:' . $booking)]);
        if (!$pending) { ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('vouchers'), ['balance_cents' => (int) $row['balance_cents'] - $credit], (int) $row['id']); }
    }

    /** Called after departure and booking locks; voucher lock precedes redemption lock. */
    public static function transition(array $booking, string $action): bool {
        $rows = ISS_Commerce_Ledger::rows('SELECT voucher_id FROM %i WHERE booking_id = %d', [ISS_Commerce_Ledger::table('redemptions'), $booking['id']]);
        if (!$rows) { return true; }
        $vouchers = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE id = %d FOR UPDATE', [ISS_Commerce_Ledger::table('vouchers'), $rows[0]['voucher_id']]);
        if (!$vouchers) { throw new RuntimeException('Missing redemption voucher.'); }
        $voucher = $vouchers[0];
        $redemption = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE booking_id = %d FOR UPDATE', [ISS_Commerce_Ledger::table('redemptions'), $booking['id']])[0];
        $state = $redemption['state'];
        if ($action === 'commit') {
            if ($state === 'committed') { return true; }
            if ($state === 'reversed' || !(int) $voucher['enabled'] || $voucher['expires_at'] <= gmdate('Y-m-d H:i:s')) { return false; }
            $other = ISS_Commerce_Ledger::rows("SELECT amount_cents FROM %i WHERE voucher_id = %d AND booking_id <> %d AND state = 'reserved' AND expires_at > UTC_TIMESTAMP() FOR UPDATE", [ISS_Commerce_Ledger::table('redemptions'), $voucher['id'], $booking['id']]);
            $available = (int) $voucher['balance_cents'] - array_sum(array_map('intval', array_column($other, 'amount_cents')));
            if ($available < (int) $redemption['amount_cents']) { return false; }
            ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('vouchers'), ['balance_cents' => (int) $voucher['balance_cents'] - (int) $redemption['amount_cents']], (int) $voucher['id']);
            $next = 'committed';
        } elseif ($action === 'reverse') {
            if ($state === 'reversed') { return true; }
            if ($state !== 'committed' || $booking['booking_state'] !== 'cancelled') { throw new DomainException('Nur stornierte Buchungen mit eingelöstem Guthaben können rückgebucht werden.'); }
            ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('vouchers'), ['balance_cents' => (int) $voucher['balance_cents'] + (int) $redemption['amount_cents']], (int) $voucher['id']);
            $next = 'reversed';
        } else {
            if ($state !== 'reserved') { return true; }
            $next = 'released';
        }
        ISS_Commerce_Ledger::update(ISS_Commerce_Ledger::table('redemptions'), ['state' => $next], (int) $redemption['id']);
        ISS_Commerce_Ledger::event((int) $booking['departure_id'], (int) $booking['id'], 'voucher_' . $next, $state, $next, '', '', (int) $voucher['id']);
        return true;
    }

    /** Input is a private reconciled manifest, never a database import. Replays cannot reset balances. */
    public static function import(array $records, bool $apply, bool $test): array {
        $result = ['new' => 0, 'existing' => 0, 'review' => 0];
        $normalized = []; $seen = [];
        foreach ($records as $record) {
            if (!is_array($record) || empty($record['legacy_site']) || empty($record['legacy_id']) || empty($record['source_sha256'])
                || !preg_match('/^[a-f0-9]{64}$/D', (string) $record['source_sha256']) || !is_int($record['amount_cents'] ?? null) || !is_int($record['balance_cents'] ?? null)
                || $record['amount_cents'] < 0 || $record['amount_cents'] > 2147483647 || $record['balance_cents'] < 0 || $record['balance_cents'] > $record['amount_cents']) { throw new DomainException('Invalid private voucher manifest.'); }
            $key = hash('sha256', $record['legacy_site'] . '|' . $record['legacy_id']);
            $hash = self::fingerprint((string) ($record['code'] ?? ''));
            if (isset($seen[$key]) || isset($seen[$hash])) { throw new DomainException('Duplicate identity in voucher manifest.'); }
            $seen[$key] = true; $seen[$hash] = true;
            $verified = !empty($record['reconciled']) && !empty($record['single_authority']) && !empty($record['terms_verified']) && !empty($record['issued_at']) && !empty($record['expires_at']);
            $issued = !empty($record['issued_at']) ? ISS_Commerce_Ledger::utc($record['issued_at']) : '1970-01-01 00:00:00';
            $expires = !empty($record['expires_at']) ? ISS_Commerce_Ledger::utc($record['expires_at']) : '1970-01-01 00:00:00';
            if (!$verified) { ++$result['review']; }
            $normalized[] = ['legacy_key' => $key, 'code_hash' => $hash, 'amount_cents' => $record['amount_cents'], 'balance_cents' => $record['balance_cents'],
                'currency' => (string) ($record['currency'] ?? 'EUR'), 'issued_at' => $issued, 'expires_at' => $expires,
                'enabled' => (int) ($verified && !empty($record['enabled']) && $expires > $issued), 'test_mode' => (int) $test,
                'provenance' => (string) wp_json_encode(['source_sha256' => $record['source_sha256'], 'legacy_key' => $key, 'reconciled' => $verified, 'source_balance_cents' => $record['balance_cents']])];
        }
        ISS_Commerce_Ledger::atomic(static function () use ($normalized, $apply, &$result): void {
            foreach ($normalized as $record) {
                $rows = ISS_Commerce_Ledger::rows('SELECT * FROM %i WHERE legacy_key = %s OR code_hash = %s FOR UPDATE', [ISS_Commerce_Ledger::table('vouchers'), $record['legacy_key'], $record['code_hash']]);
                if ($rows) {
                    $old = $rows[0];
                    if (count($rows) !== 1 || $old['legacy_key'] !== $record['legacy_key'] || $old['code_hash'] !== $record['code_hash'] || $old['provenance'] !== $record['provenance'] || (int) $old['test_mode'] !== $record['test_mode']) { throw new DomainException('Existing voucher conflicts with manifest; reconcile explicitly.'); }
                    ++$result['existing'];
                } else {
                    ++$result['new'];
                    if ($apply) {
                        $id = ISS_Commerce_Ledger::insert(ISS_Commerce_Ledger::table('vouchers'), $record);
                        ISS_Commerce_Ledger::event(0, 0, 'voucher_import', '', $record['enabled'] ? 'enabled' : 'review', '', 'voucher-import:' . $record['legacy_key'], $id);
                    }
                }
            }
        });
        return $result;
    }
}
