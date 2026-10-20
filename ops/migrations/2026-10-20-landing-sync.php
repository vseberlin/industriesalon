<?php
/**
 * Sync the three reviewed saved landing documents through the editorial owner.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 * Input contains before/after documents and attachment fingerprints; keep it private.
 * Staging only. Reject intervening edits; preserve private drafts and post fields.
 * Receipt: iss_backup_20261020_landing_sync, non-autoload option.
 * Rollback: after checking current after_hashes, save receipt.before documents
 * through iss_editorial_save_document(); retain the receipt and native revisions.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Provide mode and private input.');
$expect(home_url() === 'https://staging.industriesalon.info' && current_user_can('manage_options'), 'Staging administrator required.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$expect(array_keys($input['records']) === ['fuehrungen', 'schoneweide', 'about'], 'Unexpected landing scope.');
$hash = static fn($value): string => hash('sha256', wp_json_encode($value));
$private_hash = static function (int $id) use ($hash): string {
    $meta = get_post_meta($id);
    unset($meta[iss_editorial_get_document_meta_key('landing')], $meta[iss_editorial_get_skin_meta_key('landing')]);
    $autosaves = [];
    foreach (wp_get_post_revisions($id, ['posts_per_page' => -1]) as $revision) {
        if (wp_is_post_autosave($revision->ID)) { $autosaves[$revision->ID] = [$revision->to_array(), get_post_meta($revision->ID)]; }
    }
    return $hash([get_post($id)->to_array(), $meta, $autosaves]);
};
$key = 'iss_backup_20261020_landing_sync';
$receipt = get_option($key);
$verify = static function () use (&$receipt, $expect, $hash, $private_hash): void {
    $expect(is_array($receipt) && !empty($receipt['complete']), 'Missing or partial receipt; inspect before retrying.');
    foreach ($receipt['ids'] as $slug => $id) {
        $expect($hash(iss_editorial_get_document($id, 'landing', false)) === $receipt['after_hashes'][$slug], 'Saved document changed: ' . $slug);
        $expect($private_hash($id) === $receipt['private_hashes'][$slug], 'Private draft or unrelated state changed: ' . $slug);
    }
    echo "Verified three saved landing documents; private drafts and post fields preserved.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
foreach ($input['media'] as $id => $entry) {
    $file = get_attached_file((int) $id);
    $expect(get_post_type((int) $id) === 'attachment'
        && get_post_meta((int) $id, '_wp_attached_file', true) === $entry['file']
        && is_file($file) && hash_file('sha256', $file) === $entry['sha256'], 'Attachment identity/file differs: ' . $id);
}
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'ids' => [], 'before' => [], 'private_hashes' => [], 'after_hashes' => []];
foreach ($input['records'] as $slug => $record) {
    $post = get_page_by_path($slug);
    $expect($post instanceof WP_Post && $post->post_status === 'publish', 'Missing published landing: ' . $slug);
    $before = iss_editorial_get_document($post->ID, 'landing', false);
    $expect($before === $record['before'], 'Target document changed: ' . $slug);
    $after = iss_editorial_validate_document($record['after'], 'landing');
    $expect(!is_wp_error($after) && $after === $record['after'], 'Invalid or noncanonical input: ' . $slug);
    $receipt['ids'][$slug] = $post->ID;
    $receipt['before'][$slug] = $before;
    $receipt['private_hashes'][$slug] = $private_hash($post->ID);
    $receipt['after_hashes'][$slug] = $hash($after);
}
if ($mode === 'dry-run') { echo "Ready: three saved landing documents; all attachment identities and files match.\n"; return; }
$expect(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image not saved.');
foreach ($receipt['ids'] as $slug => $id) {
    $expect(iss_editorial_save_document($id, 'landing', $input['records'][$slug]['after'], false), 'Document save failed: ' . $slug);
}
$receipt['complete'] = true;
update_option($key, $receipt, false);
$expect(get_option($key) === $receipt, 'Receipt not saved.');
$verify();
