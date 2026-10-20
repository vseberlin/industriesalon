<?php
/**
 * Enable the confirmed public Elektropolis reduced rate through registered meta.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 * Run after tour-facts. Keep the input and receipt private; no schema/uploads.
 * Rollback: check later edits, then restore/delete only the recorded meta key
 * through update_post_meta/delete_post_meta according to before.exists/value.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Provide mode and private input.');
$expect(current_user_can('manage_options'), 'Administrator required.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$expect(($input['home_url'] ?? '') === home_url() && ($input['slug'] ?? '') === 'elektropolis-tour', 'Input identity differs.');
$post = get_page_by_path($input['slug'], OBJECT, 'fuehrung');
$expect($post instanceof WP_Post && $post->post_status === 'publish' && $post->post_title === $input['title'], 'Public tour identity differs.');
$id = (int) $post->ID;
$meta_key = 'booking_reduced_price_cents';
$registered = get_registered_meta_keys('post', 'fuehrung');
$expect(isset($registered[$meta_key]) && $registered[$meta_key]['type'] === 'integer', 'Deploy the registered price field first.');
$expect(get_post_meta($id, 'booking_mode', true) === 'calendar'
    && (int) get_post_meta($id, 'booking_price_cents', true) === $input['regular_price_cents'], 'Apply and verify the tour facts first.');
$expect(is_int($input['reduced_price_cents']) && $input['reduced_price_cents'] > 0
    && $input['reduced_price_cents'] < $input['regular_price_cents'], 'Invalid reduced rate.');
$receipt_key = 'iss_backup_20261020_reduced_tickets';
$receipt = get_option($receipt_key);
$verify = static function () use (&$receipt, $expect, $id, $meta_key, $input): void {
    $expect(is_array($receipt) && !empty($receipt['complete']) && $receipt['post_id'] === $id, 'Missing or partial receipt; inspect before retrying.');
    $expect(metadata_exists('post', $id, $meta_key)
        && (int) get_post_meta($id, $meta_key, true) === $input['reduced_price_cents']
        && $receipt['after'] === $input['reduced_price_cents'], 'Reduced rate differs.');
    echo "Verified public tour reduced rate; group offer unchanged.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$before = ['exists' => metadata_exists('post', $id, $meta_key), 'value' => get_post_meta($id, $meta_key, true)];
$expect($before === $input['before'], 'Price field changed since inspection.');
if ($mode === 'dry-run') { echo "Ready: one registered price field; no schema or upload changes.\n"; return; }
$receipt = ['complete' => false, 'post_id' => $id, 'field' => $meta_key, 'before' => $before,
    'before_post' => $post->to_array(), 'before_meta' => get_post_meta($id),
    'after' => $input['reduced_price_cents'], 'source_note' => $input['source_note'], 'input_sha256' => hash_file('sha256', $path)];
$expect(add_option($receipt_key, $receipt, '', false) && get_option($receipt_key) === $receipt, 'Before-image not saved.');
update_post_meta($id, $meta_key, $input['reduced_price_cents']);
$expect((int) get_post_meta($id, $meta_key, true) === $input['reduced_price_cents'], 'Price write failed.');
$receipt['complete'] = true;
update_option($receipt_key, $receipt, false);
$expect(get_option($receipt_key) === $receipt, 'Receipt write failed.');
$verify();
