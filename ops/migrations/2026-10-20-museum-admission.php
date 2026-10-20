<?php
/**
 * Record confirmed museum admission through the existing settings owner.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 * Run after exhibition-visit-info. Private input: home_url, before, note, source_note.
 * Rollback after checking later edits: update_option(key, before), or delete_option
 * if before was null. Keep the private before-image; no schema or uploads.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Mode and private input required.');
$expect(current_user_can('manage_options'), 'Administrator required.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$expect(($input['home_url'] ?? '') === home_url() && !empty($input['source_note'])
    && isset($input['note']) && trim($input['note']) !== '' && array_key_exists('before', $input), 'Input identity or confirmation missing.');
$owner = Industriesalon_Steuerung::instance();
$key = $owner->get_option_keys()['prices'];
$after = $owner->sanitize_prices(array_replace((array) $input['before'], ['note' => $input['note']]));
$receipt_key = 'iss_backup_20261020_museum_admission';
$receipt = get_option($receipt_key);
$verify = static function () use (&$receipt, $expect, $path, $key, $after): void {
    $expect(is_array($receipt) && !empty($receipt['complete'])
        && $receipt['input_sha256'] === hash_file('sha256', $path), 'Missing or incomplete receipt; inspect before retrying.');
    $expect(get_option($key, null) === $after && $receipt['after'] === $after, 'Confirmed admission differs.');
    echo "Verified central museum admission; exhibition and overview share this setting.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$previous = get_option('iss_backup_20261020_exhibition_visit_info');
$expect(is_array($previous) && !empty($previous['complete']), 'Apply the exhibition visitor migration first.');
$expect(get_option($key, null) === $input['before'], 'Prices changed since inspection.');
if ($mode === 'dry-run') { echo "Ready: one central admission note; no per-exhibition copies.\n"; return; }
$receipt = ['complete' => false, 'option' => $key, 'before' => $input['before'], 'after' => $after,
    'source_note' => $input['source_note'], 'input_sha256' => hash_file('sha256', $path)];
$expect(add_option($receipt_key, $receipt, '', false) && get_option($receipt_key) === $receipt, 'Before-image not saved.');
update_option($key, $after, false);
$expect(get_option($key, null) === $after, 'Admission write failed.');
$receipt['complete'] = true;
update_option($receipt_key, $receipt, false);
$expect(get_option($receipt_key) === $receipt, 'Receipt write failed.');
$verify();
