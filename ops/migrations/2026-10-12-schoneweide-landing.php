<?php
/**
 * Apply the compact Schöneweide landing through the existing editorial owner.
 * wp eval-file FILE check|apply|verify /PRIVATE/new-before-image.json --use-include
 * Deploy matching theme/iss-content code first. Uses existing attachment files.
 * Apply requires the reviewed baseline and exclusively creates a private receipt.
 * Rollback: compare the current document to receipt.after, then save receipt.before
 * through iss_editorial_save_document(receipt.post_id, 'landing', ...). Restore the
 * previous disk template/CSS alongside it; never overwrite later editorial changes.
 */
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}

$mode = $args[0] ?? 'check';
$path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true)) {
    WP_CLI::error('Unknown mode.');
}
$post = get_page_by_path('schoneweide');
if (!$post instanceof WP_Post || $post->post_status !== 'publish' || !iss_editorial_document_is_enabled((int) $post->ID, 'landing')) {
    WP_CLI::error('Expected the published, editorial-enabled Schöneweide page.');
}
$id = (int) $post->ID;
$template = get_block_template('industriesalon//page-schoneweide', 'wp_template');
if (!$template || $template->source !== 'theme') {
    WP_CLI::error('Preserve and review the DB template override before this migration.');
}
$payload = json_decode((string) file_get_contents(__DIR__ . '/2026-10-12-schoneweide-landing.json'), true);
if (!is_array($payload)) {
    WP_CLI::error('Unreadable landing payload.');
}
foreach ($payload['media_dependencies'] as $media_id) {
    if (get_post_type($media_id) !== 'attachment' || !wp_attachment_is_image($media_id) || !is_readable((string) get_attached_file($media_id))) {
        WP_CLI::error('Missing existing image attachment/file: ' . $media_id);
    }
}
$after = iss_editorial_validate_document($payload['document'], 'landing');
if (is_wp_error($after) || count($after['sections'] ?? []) !== 4 || count($after['sections'][0]['items'] ?? []) !== 3 || count($after['sections'][1]['items'] ?? []) !== 6 || count($after['sections'][0]['links'] ?? []) !== 1 || count($after['sections'][1]['links'] ?? []) !== 2) {
    WP_CLI::error('Deploy the matching editorial section contracts first.');
}
foreach ($after['sections'][0]['items'] as $item) {
    $place_id = url_to_postid(home_url($item['url']));
    $places = iss_relations_collect_map_places(['source' => 'manual', 'placeIds' => [$place_id], 'perPage' => 1]);
    $model = iss_frontend_static_maps_get_atlas_slice_model($places, iss_relations_get_place_map_config());
    if (count($model['stations']) !== 1) {
        WP_CLI::error('Missing published, mapped Place destination: ' . $item['url']);
    }
}

// The owning editor hydrates page-link URLs per site; the page ID is their identity.
$canonicalize = static function (array $value) use (&$canonicalize): array {
    if (!empty($value['page_id'])) {
        unset($value['url']);
    }
    foreach ($value as $key => $item) {
        if (is_array($item)) {
            $value[$key] = $canonicalize($item);
        }
    }
    return $value;
};
$fingerprint = static fn(array $document): string => hash('sha256', (string) wp_json_encode($canonicalize($document)));
$before = iss_editorial_get_document($id, 'landing');
$applied = $fingerprint($before) === $fingerprint($after);
if ($mode === 'verify') {
    if (!$applied) {
        WP_CLI::error('Saved landing differs from the reviewed composition.');
    }
    WP_CLI::success('Four editorial sections, three Place cards and six historical phases verified.');
    return;
}
if ($applied) {
    WP_CLI::success('Already applied; no changes.');
    return;
}
if ($fingerprint($before) !== $payload['expected_document_hash']) {
    WP_CLI::error('The existing editorial document has changed; review it before applying.');
}
if ($mode === 'check') {
    WP_CLI::success('Reviewed baseline, section contracts, Place destinations and existing media ready.');
    return;
}
if ($path === '' || file_exists($path)) {
    WP_CLI::error('A new private before-image path is required.');
}
$receipt = (string) wp_json_encode(['site' => home_url(), 'post_id' => $id, 'before' => $before, 'after' => $after], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
if (!iss_editorial_save_document($id, 'landing', $after) || $fingerprint(iss_editorial_get_document($id, 'landing')) !== $fingerprint($after)) {
    WP_CLI::error('Save/readback failed; retain the before-image.');
}
WP_CLI::success('Compact Schöneweide landing saved and verified; retain the private before-image.');
