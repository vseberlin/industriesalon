<?php
/** Two place website fields: retain private before-images, use the current home URL.
 * Restore the receipt's old values with update_post_meta only after checking for
 * subsequent edits. No source-provenance, projection, schema or upload changes.
 */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options')) { throw new RuntimeException('Administrator CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(in_array($mode, ['dry-run', 'apply', 'verify'], true), 'Invalid mode.');
$key = 'iss_backup_20261020_local_websites';
$receipt = get_option($key);
$verify = static function () use (&$receipt, $expect): void {
    $expect(is_array($receipt) && !empty($receipt['complete']), 'Missing receipt.');
    foreach ($receipt['places'] as $place) {
        $expect(get_post_meta($place['id'], 'legacy_website', true) === $place['after'], 'Place website differs.');
        $entity = iss_register_get_place_entity_by_post_id($place['id']);
        $expect(($entity['website'] ?? '') === $place['after'], 'Public place cache differs.');
    }
    echo "Verified two internal place website links.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$receipt = ['complete' => false, 'places' => []];
foreach (['industriesalon-schoneweide-reinbeckstrasse', 'electropolis-berlin-industriemuseum-projekt'] as $slug) {
    $post = get_page_by_path($slug, OBJECT, 'register_place');
    $expect($post instanceof WP_Post && $post->post_status === 'publish', 'Missing published place.');
    $before = get_post_meta($post->ID, 'legacy_website', true);
    $expect(in_array($before, ['https://www.industriesalon.de', 'https://www.industriesalon.de/'], true), 'Website changed; review before applying.');
    $receipt['places'][] = ['id' => $post->ID, 'before' => $before, 'after' => iss_register_sanitize_meta_value('legacy_website', home_url('/'))];
}
if ($mode === 'dry-run') { echo "Ready: two place website fields.\n"; return; }
$expect(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image not saved.');
foreach ($receipt['places'] as $place) { update_post_meta($place['id'], 'legacy_website', wp_slash($place['after'])); }
iss_register_clear_places_cache();
$receipt['complete'] = true;
update_option($key, $receipt, false);
$verify();
