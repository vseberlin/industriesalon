<?php
/**
 * Bring the eight published staging programme records into local, with new IDs.
 * wp eval-file FILE check|apply|verify /PRIVATE/receipt.json --use-include
 * Requires programme-local-source.json and original programme-sync media manifest.
 * No SuperSaaS refresh, no existing content updates. Run apply once on local only.
 * Rollback: restore the verified local before.sql.gz after checking for later edits;
 * receipt lists added posts/media. Added upload paths are in the paired manifest.
 */
$mode = $args[0] ?? 'check';
$path = $args[1] ?? '';
if (!in_array($mode, ['check', 'apply', 'verify'], true) || wp_parse_url(home_url(), PHP_URL_HOST) !== '192.168.2.31') {
    WP_CLI::error('This import is for the local site only.');
}
$data = json_decode(file_get_contents(__DIR__ . '/2026-09-22-programme-local-source.json'), true);
$files = json_decode(file_get_contents(__DIR__ . '/2026-09-22-programme-source.json'), true)['files'];
$uploads = wp_upload_dir();
function iss_local_programme_require($ok, string $message): void {
    if (!$ok || is_wp_error($ok)) { WP_CLI::error($message); }
}
function iss_local_programme_write(string $path, array $receipt): void {
    iss_local_programme_require(file_put_contents($path, wp_json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false, 'Cannot save receipt.');
}
function iss_local_programme_hashes(int $max): array {
    global $wpdb;
    return [
        hash('sha256', wp_json_encode($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID<=%d ORDER BY ID", $max), ARRAY_A))),
        hash('sha256', wp_json_encode($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id<=%d ORDER BY meta_id", $max), ARRAY_A))),
    ];
}
foreach ($files as $file) {
    $name = $uploads['basedir'] . '/' . $file['path'];
    iss_local_programme_require(is_file($name) && hash_file('sha256', $name) === $file['sha256'], 'Media mismatch: ' . $file['path']);
}
iss_local_programme_require(count($data['items']) === 8 && count($data['media']) === 18, 'Unexpected import scope.');
if ($mode === 'verify') {
    $receipt = json_decode(file_get_contents($path), true);
    iss_local_programme_require($receipt['state'] === 'applied' && iss_local_programme_hashes($receipt['max']) === $receipt['before'], 'Pre-existing content changed.');
    foreach ($data['items'] as $item) {
        $id = $receipt['posts'][$item['source_id']];
        iss_local_programme_require(get_post_status($id) === 'publish' && get_post_meta($id, '_iss_source_url', true) === $item['meta']['_iss_source_url'], 'Source mismatch.');
        iss_local_programme_require((int) get_post_thumbnail_id($id) === $receipt['media'][$item['thumbnail']], 'Thumbnail mismatch.');
        iss_local_programme_require(iss_editorial_get_document($id, $item['post_type']) === $receipt['documents'][$id], 'Document mismatch.');
    }
    WP_CLI::success('8 local records, 18 attachments, 215 media hashes; original posts/meta preserved.');
    return;
}
iss_local_programme_require($path !== '' && !file_exists($path), 'New private receipt required; do not replay.');
foreach ($data['items'] as $item) {
    $existing = get_posts(['post_type' => $item['post_type'], 'post_status' => 'any', 'meta_key' => '_iss_source_url', 'meta_value' => $item['meta']['_iss_source_url'], 'fields' => 'ids']);
    iss_local_programme_require(!$existing && !get_page_by_path($item['slug'], OBJECT, $item['post_type']), 'Source or slug exists.');
    if ($item['venue_slug']) { iss_local_programme_require(get_page_by_path($item['venue_slug'], OBJECT, 'register_place'), 'Venue missing.'); }
}
foreach ($data['media'] as $media) {
    iss_local_programme_require(!get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_wp_attached_file', 'meta_value' => $media['file'], 'fields' => 'ids']), 'Attachment already exists.');
}
if ($mode === 'check') { WP_CLI::success('Local preflight passed.'); return; }
global $wpdb;
$max = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
$r = ['state' => 'applying', 'max' => $max, 'before' => iss_local_programme_hashes($max), 'posts' => [], 'media' => [], 'documents' => []];
$f = fopen($path, 'x'); iss_local_programme_require($f, 'Receipt exists.'); fclose($f);
iss_local_programme_write($path, $r);
foreach ($data['media'] as $media) {
    $id = wp_insert_attachment(wp_slash(['post_title' => $media['title'], 'post_content' => $media['description'], 'post_excerpt' => $media['caption'], 'post_mime_type' => $media['mime'], 'post_status' => 'inherit']), $uploads['basedir'] . '/' . $media['file'], 0, true);
    iss_local_programme_require($id, 'Attachment failed.');
    $r['media'][$media['id']] = (int) $id; iss_local_programme_write($path, $r);
    wp_update_attachment_metadata($id, $media['metadata']);
    update_post_meta($id, '_wp_attachment_image_alt', $media['alt']);
}
foreach ($data['items'] as $item) {
    $id = wp_insert_post(wp_slash(['post_type' => $item['post_type'], 'post_status' => 'draft', 'post_title' => $item['title'], 'post_name' => $item['slug'], 'post_excerpt' => $item['excerpt'], 'post_date' => $item['date'], 'meta_input' => $item['meta'], 'comment_status' => 'closed', 'ping_status' => 'closed']), true);
    iss_local_programme_require($id, 'Post failed.');
    $r['posts'][$item['source_id']] = $id; iss_local_programme_write($path, $r);
    set_post_thumbnail($id, $r['media'][$item['thumbnail']]);
    $doc = json_decode(str_replace($data['source'] . '/wp-content/uploads/', $uploads['baseurl'] . '/', wp_json_encode($item['document'], JSON_UNESCAPED_SLASHES)), true);
    foreach ($doc['sections'] as &$section) {
        foreach (($section['media_refs'] ?? []) as $key => $ref) {
            if (($ref['source'] ?? '') === 'wp-media') { $section['media_refs'][$key]['id'] = (string) $r['media'][(int) $ref['id']]; }
        }
    }
    unset($section);
    iss_local_programme_require(iss_editorial_save_document($id, $item['post_type'], $doc), 'Document save failed.');
    iss_editorial_set_document_enabled($id, $item['post_type'], true);
    if ($item['post_type'] === 'ausstellung') { wp_set_object_terms($id, ['sonderausstellung'], 'ausstellung_typ'); }
    if ($item['venue_slug']) {
        $venue = get_page_by_path($item['venue_slug'], OBJECT, 'register_place');
        iss_relations_save_post_relations($id, [['place_id' => $venue->ID, 'role' => 'venue', 'weight' => 100]]);
    }
    iss_local_programme_require(wp_update_post(['ID' => $id, 'post_status' => 'publish'], true), 'Publish failed.');
    if ($item['post_type'] === 'veranstaltung') { iss_content_model_sync_veranstaltung_normalized_facts($id); }
    iss_relations_sync_post_read_models($id);
    iss_local_programme_require(iss_occurrences_sync_source($id) === 1, 'Occurrence failed.');
    $r['documents'][$id] = iss_editorial_get_document($id, $item['post_type']);
    iss_local_programme_write($path, $r);
}
iss_local_programme_require(iss_local_programme_hashes($max) === $r['before'], 'Original content changed.');
$r['state'] = 'applied'; iss_local_programme_write($path, $r);
iss_timeline_rest_bump_cache_version();
WP_CLI::success('Local programme imported. Run verify.');
