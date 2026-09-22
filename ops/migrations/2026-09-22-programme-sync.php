<?php
/**
 * Import eight dated programme entries from nine production announcements.
 *
 * Run: wp eval-file FILE check|apply|verify /PRIVATE/RECEIPT.json --use-include
 * Staging only. Paired source JSON and ops/uploads/2026-09-22-programme-sync.manifest
 * are required; copy and verify the media before apply. No existing post is replaced.
 * Recovery: receipt lists newly created IDs and the original SuperSaaS mapping.
 * Full pre-write DB backup: server-actions/programme-20260922/before.sql.gz.
 * Restore that backup only after checking for later edits. Do not replay apply.
 */

$mode = $args[0] ?? 'check';
$receipt_path = $args[1] ?? '';
$payload = json_decode(file_get_contents(__DIR__ . '/2026-09-22-programme-source.json'), true);
if (!in_array($mode, ['check', 'apply', 'verify'], true) || home_url() !== $payload['target']) {
    WP_CLI::error('Staging only; use check, apply or verify.');
}
foreach (['iss_editorial_validate_document', 'iss_editorial_save_document', 'iss_relations_save_post_relations', 'iss_occurrences_sync_source', 'iss_occurrences_remember_series_source', 'iss_supersaas_sync_occurrences'] as $fn) {
    if (!function_exists($fn)) { WP_CLI::error('Missing owning API: ' . $fn); }
}
if (count($payload['items']) !== 8 || count($payload['media']) !== 18 || count($payload['files']) !== 215) {
    WP_CLI::error('Unexpected bounded import scope.');
}

function iss_programme_20260922_require($value, string $message): void {
    if (!$value || is_wp_error($value)) { WP_CLI::error($message . (is_wp_error($value) ? ': ' . $value->get_error_message() : '')); }
}

function iss_programme_20260922_receipt(string $path, array $data): void {
    iss_programme_20260922_require(file_put_contents($path, wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n") !== false, 'Cannot save receipt.');
}

function iss_programme_20260922_old_content(int $max_id): array {
    global $wpdb;
    // The hashes expose no private content and protect every pre-existing post/meta row.
    return [
        'posts' => hash('sha256', wp_json_encode($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->posts} WHERE ID <= %d ORDER BY ID", $max_id), ARRAY_A))),
        'postmeta' => hash('sha256', wp_json_encode($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->postmeta} WHERE post_id <= %d ORDER BY meta_id", $max_id), ARRAY_A))),
    ];
}

function iss_programme_20260922_body(string $html): string {
    $html = preg_replace('/\[button\b[^\]]*\]|\[\/?video\b[^\]]*\]/i', '', $html);
    $html = preg_replace('/<img\b[^>]*>/i', '', $html);
    $html = preg_replace('/<(\/?)(?:h[1-6]|td|tr)\b[^>]*>/i', '<$1p>', $html);
    $html = preg_replace('/<(\/?)(?:b)\b[^>]*>/i', '<$1strong>', $html);
    $html = wp_kses($html, ['p' => [], 'br' => [], 'strong' => [], 'em' => [], 'ul' => [], 'ol' => [], 'li' => [], 'a' => ['href' => true]]);
    $html = wpautop(trim($html));
    $html = preg_replace('/<p>\s*(?:&nbsp;|<br\s*\/?\s*>|\s)*<\/p>/i', '', $html);
    return trim($html);
}

function iss_programme_20260922_document(array $item, array $media_map, array $media_by_id, string $upload_url): array {
    $sections = [];
    $body = iss_programme_20260922_body($item['body_source_html']);
    $body = str_replace('https://www.industriesalon.de/wp-content/uploads/', trailingslashit($upload_url), $body);
    if ($body !== '') { $sections[] = ['type' => $item['post_type'] === 'veranstaltung' ? 'intro' : 'kapitel', 'body' => $body]; }
    if (!empty($item['extra_section'])) { $sections[] = $item['extra_section']; }
    $refs = [];
    foreach ($item['inline_media_source_ids'] as $source_id) {
        $media = $media_by_id[$source_id];
        $refs[] = ['kind' => 'media', 'source' => 'wp-media', 'id' => (string) $media_map[$source_id],
                   'label' => (string) $media['post']['post_title']];
    }
    if ($refs) { $sections[] = ['type' => 'galerie', 'title' => 'Bilder', 'media_refs' => $refs]; }
    $links = [];
    foreach ($item['links'] as $link) {
        $links[] = ['label' => $link['label'], 'url' => isset($link['media_source_id'])
            ? trailingslashit($upload_url) . $media_by_id[$link['media_source_id']]['file'] : $link['url']];
    }
    if ($links) { $sections[] = ['type' => 'material', 'title' => $item['source_id'] === 12060 ? 'Tickets' : 'Material', 'links' => $links]; }
    $document = ['schema_version' => 1, 'skin' => 'typografisch', 'sections' => $sections];
    if ($item['entity_key']) { $document['entity_key'] = $item['entity_key']; }
    return $document;
}

global $wpdb;
$uploads = wp_upload_dir();
foreach ($payload['files'] as $file) {
    iss_programme_20260922_require(str_starts_with($file['path'], '2026/') && !str_contains($file['path'], '..'), 'Unsafe upload path.');
    $path = $uploads['basedir'] . '/' . $file['path'];
    iss_programme_20260922_require(is_file($path) && filesize($path) === $file['bytes'] && hash_file('sha256', $path) === $file['sha256'], 'Media mismatch: ' . $file['path']);
}
$media_by_id = [];
foreach ($payload['media'] as $media) { $media_by_id[(int) $media['post']['ID']] = $media; }
$dummy_map = array_combine(array_keys($media_by_id), array_keys($media_by_id));
foreach ($payload['items'] as $item) {
    iss_programme_20260922_require(iss_editorial_validate_document(iss_programme_20260922_document($item, $dummy_map, $media_by_id, $uploads['baseurl']), $item['post_type']), 'Invalid editorial document: ' . $item['title']);
    if ($item['venue_id']) {
        iss_programme_20260922_require(get_post_type($item['venue_id']) === 'register_place', 'Missing venue.');
    }
}

if ($mode === 'verify') {
    iss_programme_20260922_require(is_file($receipt_path), 'Receipt required.');
    $receipt = json_decode(file_get_contents($receipt_path), true);
    iss_programme_20260922_require($receipt['state'] === 'applied', 'Import is not complete.');
    iss_programme_20260922_require(iss_programme_20260922_old_content($receipt['max_existing_post_id']) === $receipt['existing_content_hashes'], 'Pre-existing post or meta changed.');
    foreach ($payload['items'] as $item) {
        $id = (int) $receipt['post_map'][$item['source_id']];
        iss_programme_20260922_require(get_post_status($id) === 'publish' && get_post_type($id) === $item['post_type'] && get_the_title($id) === $item['title'], 'Post mismatch.');
        $saved = iss_editorial_get_document($id, $item['post_type']);
        $expected = iss_editorial_validate_document(iss_programme_20260922_document($item, $receipt['media_map'], $media_by_id, $uploads['baseurl']), $item['post_type']);
        iss_programme_20260922_require($saved === $expected, 'Saved document differs: ' . $id);
        $date_keys = $item['post_type'] === 'veranstaltung' ? ['iss_start_datetime', 'iss_end_datetime'] : ['iss_start_date', 'iss_end_date'];
        iss_programme_20260922_require(get_post_meta($id, $date_keys[0], true) === $item['start'] && get_post_meta($id, $date_keys[1], true) === $item['end'], 'Date mismatch.');
        iss_programme_20260922_require((int) get_post_thumbnail_id($id) === (int) $receipt['media_map'][$item['thumbnail_source_id']], 'Thumbnail mismatch.');
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}iss_occurrences WHERE source_post_id=%d AND origin='wp' AND status='active' AND visibility='public'", $id));
        iss_programme_20260922_require($count === 1, 'Expected exactly one calendar occurrence: ' . $id);
    }
    $mapping = iss_occurrences_get_series_source($payload['series_mapping']['series_key']);
    iss_programme_20260922_require((int) $mapping['source_post_id'] === 11940, 'Waldfriedhof mapping missing.');
    $row = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}iss_occurrences WHERE external_id='public:71051648'", ARRAY_A);
    iss_programme_20260922_require($row && (int) $row['source_post_id'] === 11940 && $row['starts_at'] === '2026-11-14 11:00:00' && $row['status'] === 'active', 'Waldfriedhof occurrence missing.');
    WP_CLI::success('Verified 8 published programme entries, 18 attachments, 215 media files, exact dates, unique occurrences, saved documents, preserved existing posts/meta and Waldfriedhof mapping.');
    return;
}

iss_programme_20260922_require($receipt_path !== '' && !file_exists($receipt_path), 'Use a new private receipt path; replay is prohibited.');
foreach ($payload['items'] as $item) {
    $exists = get_page_by_path($item['slug'], OBJECT, [$item['post_type'], 'post']);
    iss_programme_20260922_require(!$exists, 'Existing slug requires review: ' . $item['slug']);
    foreach ($item['source_ids'] as $source_id) {
        $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE (meta_key='source_post_id' AND meta_value=%s) OR (meta_key='_iss_source_url' AND meta_value=%s)", (string) $source_id, $item['source_url']));
        iss_programme_20260922_require($count === 0, 'Source already imported: ' . $source_id);
    }
}
foreach ($payload['media'] as $media) {
    $count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key='_wp_attached_file' AND meta_value=%s", $media['file']));
    iss_programme_20260922_require($count === 0, 'Existing media record requires review: ' . $media['file']);
}
$mapping = $payload['series_mapping'];
$old_mapping = iss_occurrences_get_series_source($mapping['series_key']);
iss_programme_20260922_require(is_array($old_mapping) && (int) $old_mapping['source_post_id'] === 0 && get_post_type(11940) === 'fuehrung', 'Mapping precondition changed.');
if ($mode === 'check') {
    WP_CLI::success('Preflight passed: 8 new programme entries, 18 new attachment records, 215 media hashes; no content/source duplicates.');
    return;
}

$max_id = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}");
$receipt = ['state' => 'applying', 'started_at' => gmdate('c'), 'source' => $payload['source'], 'target' => home_url(),
            'payload_sha256' => hash_file('sha256', __DIR__ . '/2026-09-22-programme-source.json'),
            'max_existing_post_id' => $max_id, 'existing_content_hashes' => iss_programme_20260922_old_content($max_id),
            'original_mapping' => $old_mapping, 'post_map' => [], 'media_map' => [], 'created_post_ids' => []];
$stream = fopen($receipt_path, 'x');
iss_programme_20260922_require($stream !== false, 'Cannot create private before-image receipt.');
fclose($stream);
iss_programme_20260922_receipt($receipt_path, $receipt);

foreach ($payload['media'] as $media) {
    $p = $media['post'];
    $id = wp_insert_attachment(wp_slash(['post_title' => $p['post_title'], 'post_content' => $p['post_content'],
        'post_excerpt' => $p['post_excerpt'], 'post_mime_type' => $p['post_mime_type'], 'post_status' => 'inherit',
        'post_date' => $p['post_date'], 'post_date_gmt' => $p['post_date_gmt'], 'post_author' => 1]),
        $uploads['basedir'] . '/' . $media['file'], 0, true);
    iss_programme_20260922_require($id, 'Attachment creation failed.');
    $receipt['media_map'][(int) $p['ID']] = (int) $id;
    $receipt['created_post_ids'][] = (int) $id;
    iss_programme_20260922_receipt($receipt_path, $receipt);
    if (is_array($media['metadata'])) { wp_update_attachment_metadata($id, $media['metadata']); }
    update_post_meta($id, '_wp_attachment_image_alt', $media['alt']);
    update_post_meta($id, '_iss_source_url', $media['url']);
}

foreach ($payload['items'] as $item) {
    $meta = ['iss_programme_enabled' => 1, 'iss_location' => $item['location'], '_iss_source_url' => $item['source_url'],
             '_iss_source_context' => $item['source_context'], 'source_post_id' => (string) $item['source_id'],
             'source_post_ids' => implode(',', $item['source_ids'])];
    if ($item['post_type'] === 'veranstaltung') {
        $meta += ['_iss_entity_key' => $item['entity_key'], 'iss_start_datetime' => $item['start'], 'iss_end_datetime' => $item['end']];
    } else {
        $meta += ['iss_start_date' => $item['start'], 'iss_end_date' => $item['end'], 'iss_public_overview_enabled' => 1];
    }
    $id = wp_insert_post(wp_slash(['post_type' => $item['post_type'], 'post_status' => 'draft', 'post_title' => $item['title'],
        'post_name' => $item['slug'], 'post_author' => 1, 'post_date' => $item['post_date'], 'post_date_gmt' => $item['post_date_gmt'],
        'post_excerpt' => wp_trim_words(wp_strip_all_tags(iss_programme_20260922_body($item['body_source_html'])), 45),
        'comment_status' => 'closed', 'ping_status' => 'closed', 'meta_input' => $meta]), true);
    iss_programme_20260922_require($id, 'Post creation failed.');
    $receipt['post_map'][$item['source_id']] = (int) $id;
    $receipt['created_post_ids'][] = (int) $id;
    iss_programme_20260922_receipt($receipt_path, $receipt);
    set_post_thumbnail($id, $receipt['media_map'][$item['thumbnail_source_id']]);
    if ($item['post_type'] === 'ausstellung') { iss_programme_20260922_require(wp_set_object_terms($id, ['sonderausstellung'], 'ausstellung_typ'), 'Exhibition type failed.'); }
    if ($item['venue_id']) { iss_relations_save_post_relations($id, [['place_id' => $item['venue_id'], 'role' => 'venue', 'weight' => 100]]); }
    $document = iss_programme_20260922_document($item, $receipt['media_map'], $media_by_id, $uploads['baseurl']);
    iss_programme_20260922_require(iss_editorial_save_document($id, $item['post_type'], $document), 'Document save failed.');
    iss_editorial_set_document_enabled($id, $item['post_type'], true);
    iss_programme_20260922_require(wp_update_post(['ID' => $id, 'post_status' => 'publish'], true), 'Publishing staging entry failed.');
    if ($item['post_type'] === 'veranstaltung') { iss_content_model_sync_veranstaltung_normalized_facts($id); }
    iss_relations_sync_post_read_models($id);
    iss_programme_20260922_require(iss_occurrences_sync_source($id) === 1, 'Occurrence projection failed.');
}
iss_programme_20260922_require(iss_occurrences_remember_series_source($mapping['series_key'], 11940, 'fuehrung', $mapping['supersaas_title']), 'SuperSaaS mapping save failed.');
$receipt['supersaas_sync'] = iss_supersaas_sync_occurrences();
iss_programme_20260922_receipt($receipt_path, $receipt);
iss_programme_20260922_require(empty($receipt['supersaas_sync']['errors']), 'SuperSaaS refresh failed; inspect receipt.');
iss_timeline_rest_bump_cache_version();
iss_programme_20260922_require(iss_programme_20260922_old_content($max_id) === $receipt['existing_content_hashes'], 'Existing content changed during import.');
$receipt['state'] = 'applied';
$receipt['finished_at'] = gmdate('c');
iss_programme_20260922_receipt($receipt_path, $receipt);
WP_CLI::success('Imported 8 programme entries and 18 attachment records; existing content preserved and SuperSaaS refreshed. Run verify.');
