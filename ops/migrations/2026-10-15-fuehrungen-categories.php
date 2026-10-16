<?php
/**
 * Assign reviewed tour categories and update the existing landing introduction.
 * wp eval-file FILE check|apply|verify /PRIVATE/new-before-image.json --use-include
 * Deploy matching code first. Slugs identify target-local posts; no uploads needed.
 * Apply exclusively creates a private, verified before-image before any write.
 * Rollback: compare targeted current values with receipt.tours[].after and
 * receipt.landing.after, then restore before through wp_update_post(),
 * update_post_meta()/delete_post_meta() and iss_editorial_save_document(). A null
 * metadata value means the key was absent. Restore matching category code too;
 * preserve later editorial changes. Never replace a database with this receipt.
 */
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

$mode = $args[0] ?? 'check';
$path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true)) {
    WP_CLI::error('Expected check, apply or verify.');
}
if (!function_exists('iss_fuehrung_get_offer_catalog_group_definitions')
    || array_keys(iss_fuehrung_get_offer_catalog_group_definitions()) !== ['oeffentlich', 'gruppen']
    || !function_exists('iss_editorial_save_document')) {
    WP_CLI::error('Deploy the matching tour and editorial code first.');
}
$payload = json_decode((string) file_get_contents(__DIR__ . '/2026-10-15-fuehrungen-categories.json'), true);
if (!is_array($payload) || empty($payload['tours']) || empty($payload['landing'])) {
    WP_CLI::error('Missing reviewed classification manifest.');
}
$read = static function (int $id, array $keys): array {
    $meta = [];
    foreach ($keys as $key) {
        $meta[$key] = metadata_exists('post', $id, $key) ? get_post_meta($id, $key, true) : null;
    }
    return ['title' => get_post_field('post_title', $id, 'raw'), 'meta' => $meta];
};
$prepared = [];
$pending = [];
foreach ($payload['tours'] as $entry) {
    $post = get_page_by_path($entry['slug'], OBJECT, 'fuehrung');
    if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
        WP_CLI::error('Expected published tour: ' . $entry['slug']);
    }
    $id = (int) $post->ID;
    $keys = array_keys($entry['after']['meta']);
    $current = $read($id, $keys);
    // Earlier staging snapshots are explicit full before-images, never wildcard exceptions.
    $baselines = array_merge([$entry['before'], $entry['after']], $entry['additional_before'] ?? []);
    if (!in_array($current, $baselines, true)) {
        WP_CLI::error('Later editorial changes require review: ' . $entry['slug']);
    }
    if (iss_fuehrung_sanitize_offer_catalog_groups($entry['after']['meta']['offer_catalog_groups']) !== $entry['after']['meta']['offer_catalog_groups']) {
        WP_CLI::error('Invalid reviewed categories: ' . $entry['slug']);
    }
    $prepared[$id] = ['post_id' => $id, 'slug' => $entry['slug'], 'before' => $current, 'after' => $entry['after']];
    if ($current !== $entry['after']) {
        $pending[$id] = $prepared[$id];
    }
}
// Additional target-local tours must already have a valid, deliberate selection.
foreach (iss_fuehrung_get_catalog_posts() as $post) {
    if (isset($prepared[$post->ID])) {
        continue;
    }
    if (!metadata_exists('post', $post->ID, 'offer_catalog_groups')
        || get_post_meta($post->ID, 'offer_catalog_groups', true) !== iss_fuehrung_get_offer_catalog_group_keys($post->ID)) {
        WP_CLI::error('Review additional target tour before applying: ' . $post->post_name);
    }
}

$landing = get_page_by_path($payload['landing']['slug']);
if (!$landing instanceof WP_Post || !iss_editorial_document_is_enabled((int) $landing->ID, 'landing')) {
    WP_CLI::error('Expected the enabled Führungen landing document.');
}
$before_document = iss_editorial_get_document((int) $landing->ID, 'landing');
$after_document = $before_document;
$matches = 0;
foreach ($after_document['sections'] as &$section) {
    if (($section['slot_key'] ?? '') !== $payload['landing']['slot_key']) {
        continue;
    }
    ++$matches;
    if (!in_array($section['body'], [$payload['landing']['before'], $payload['landing']['after']], true)) {
        WP_CLI::error('Later landing introduction requires editorial review.');
    }
    $section['body'] = $payload['landing']['after'];
}
unset($section);
if ($matches !== 1) {
    WP_CLI::error('Expected exactly one existing tour offer slot.');
}
$validated = iss_editorial_validate_document($after_document, 'landing');
if (is_wp_error($validated) || $validated !== $after_document) {
    WP_CLI::error('The landing document requires review against current editorial contracts.');
}
$landing_pending = $after_document !== $before_document;
if ($mode === 'verify') {
    if ($pending || $landing_pending) {
        WP_CLI::error('Reviewed classification and landing introduction are not fully applied.');
    }
    WP_CLI::success('All reviewed tour assignments, booking settings and landing copy verified.');
    return;
}
if (!$pending && !$landing_pending) {
    WP_CLI::success('Already applied; no changes.');
    return;
}
WP_CLI::log(sprintf('%d tour records and %d landing introduction pending.', count($pending), (int) $landing_pending));
if ($mode === 'check') {
    WP_CLI::success('Reviewed baseline and owner contracts ready; no writes.');
    return;
}
if ($path === '' || file_exists($path)) {
    WP_CLI::error('A new private before-image path is required.');
}
$receipt = (string) wp_json_encode([
    'site' => home_url(),
    'tours' => array_values($prepared),
    'landing' => ['post_id' => (int) $landing->ID, 'before' => $before_document, 'after' => $after_document],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$old_mask = umask(0077);
$file = fopen($path, 'x');
umask($old_mask);
if (!$file) {
    WP_CLI::error('Cannot create private before-image.');
}
$written = fwrite($file, $receipt);
fclose($file);
if ($written !== strlen($receipt) || file_get_contents($path) !== $receipt || (fileperms($path) & 0077) !== 0) {
    WP_CLI::error('Before-image verification failed.');
}
foreach ($pending as $id => $entry) {
    if ($read($id, array_keys($entry['after']['meta'])) !== $entry['before']) {
        WP_CLI::error('Tour changed during preparation; retain before-image: ' . $entry['slug']);
    }
    foreach ($entry['after']['meta'] as $key => $value) {
        update_post_meta($id, $key, wp_slash($value));
    }
    if ($entry['before']['title'] !== $entry['after']['title']) {
        $saved = wp_update_post(wp_slash(['ID' => $id, 'post_title' => $entry['after']['title']]), true);
        if (is_wp_error($saved)) {
            WP_CLI::error('Title save failed; retain before-image: ' . $entry['slug']);
        }
    }
    if ($read($id, array_keys($entry['after']['meta'])) !== $entry['after']) {
        WP_CLI::error('Tour readback failed; retain before-image: ' . $entry['slug']);
    }
}
if ($landing_pending && (iss_editorial_get_document((int) $landing->ID, 'landing') !== $before_document
    || !iss_editorial_save_document((int) $landing->ID, 'landing', $after_document)
    || iss_editorial_get_document((int) $landing->ID, 'landing') !== $after_document)) {
    WP_CLI::error('Landing save/readback failed; retain the before-image.');
}
WP_CLI::success('Tour categories and landing introduction saved; retain the private before-image.');
