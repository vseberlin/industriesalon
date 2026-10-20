<?php
/**
 * Reviewed public copy, links, project statuses and archive display names.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 *
 * Inputs and complete before-images stay private. No schema changes.
 * Paired uploads contain 17 existing archive PDFs; verify their hashes before
 * registering attachments. Rollback retains these source copies for review.
 * Rollback only after checking for newer edits: restore receipt documents with
 * iss_editorial_save_document(), post fields with wp_update_post(), taxonomy
 * assignments/names with WordPress term APIs and the contact option through its
 * owner sanitizer. Retain revisions, receipts and private drafts.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(current_user_can('manage_options') && in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Administrator, mode and private input required.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$expect(($input['batch'] ?? '') === 'audit-followup' && $input['home_url'] === home_url(), 'Wrong batch or site.');
$key = 'iss_backup_20261020_audit_followup';
$receipt = get_option($key);
$draft_hash = static function (int $id): string {
    $meta = array_filter(get_post_meta($id), static fn(string $name): bool => str_contains($name, 'draft') || str_contains($name, 'autosave'), ARRAY_FILTER_USE_KEY);
    $autosaves = [];
    foreach (wp_get_post_revisions($id, ['posts_per_page' => -1]) as $revision) {
        if (wp_is_post_autosave($revision->ID)) { $autosaves[$revision->ID] = [$revision->to_array(), get_post_meta($revision->ID)]; }
    }
    return hash('sha256', wp_json_encode([$meta, $autosaves]));
};
$term_slugs = static function (int $id): array {
    $slugs = wp_get_object_terms($id, 'project_status', ['fields' => 'slugs']);
    sort($slugs);
    return $slugs;
};
$verify = static function () use (&$receipt, $path, $expect, $draft_hash, $term_slugs): void {
    $expect(is_array($receipt) && !empty($receipt['complete']) && $receipt['input_sha256'] === hash_file('sha256', $path), 'Missing or incomplete receipt; inspect before retrying.');
    foreach ($receipt['records'] as $row) {
        if (isset($row['after_document'])) {
            $expect(iss_editorial_get_document($row['id'], $row['format'], false) === $row['after_document'], 'Document differs: ' . $row['slug']);
        }
        foreach ($row['after_fields'] ?? [] as $field => $value) {
            $expect(get_post_field($field, $row['id'], 'raw') === $value, 'Post field differs: ' . $row['slug']);
        }
        if (isset($row['after_terms'])) { $expect($term_slugs($row['id']) === $row['after_terms'], 'Project status differs.'); }
        $expect($draft_hash($row['id']) === $row['draft_hash'], 'Private draft changed.');
    }
    foreach ($receipt['terms'] as $term) {
        $saved = get_term_by('slug', $term['slug'], $term['taxonomy']);
        $expect($saved instanceof WP_Term && $saved->name === $term['after'], 'Term name differs.');
    }
    foreach ($receipt['media'] as $asset) {
        $file = get_attached_file($asset['id']);
        $expect($file && hash_file('sha256', $file) === $asset['sha256'], 'PDF differs.');
    }
    $expect(get_option('iss_control_contact') === $receipt['contact']['after'], 'Contact settings differ.');
    echo 'Verified ' . count($receipt['records']) . ' records and ' . count($receipt['terms']) . " term names; private drafts preserved.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'records' => [], 'terms' => $input['terms'], 'contact' => $input['contact'], 'media' => $input['media']];
$uploads = wp_upload_dir();
$expect(count($receipt['media']) === 17, 'Expected 17 reviewed PDFs.');
foreach ($receipt['media'] as &$asset) {
    $expect((bool) preg_match('~^2021/(0[2-8])/[A-Za-z0-9_.-]+\.pdf$~', $asset['path']), 'Unexpected upload path.');
    $file = $uploads['basedir'] . '/' . $asset['path'];
    $expect(is_readable($file) && hash_file('sha256', $file) === $asset['sha256']
        && file_get_contents($file, false, null, 0, 4) === '%PDF', 'Paired PDF missing or changed.');
    $existing = get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'numberposts' => 2, 'fields' => 'ids', 'meta_key' => '_wp_attached_file', 'meta_value' => $asset['path']]);
    $expect(count($existing) <= 1, 'Ambiguous attachment path.');
    $asset['id'] = (int) ($existing[0] ?? 0);
    $asset['existed'] = $asset['id'] > 0;
}
unset($asset);
$expect(count($input['records']) <= 180, 'Unexpectedly large migration.');
foreach ($input['records'] as $row) {
    $post = get_page_by_path($row['slug'], OBJECT, $row['post_type']);
    $expect($post instanceof WP_Post && $post->post_status === 'publish' && $post->post_title === $row['title'], 'Post identity changed: ' . $row['slug']);
    $row['id'] = $post->ID;
    if (isset($row['before_document'])) {
        $expect(iss_editorial_get_document($post->ID, $row['format'], false) === $row['before_document'], 'Document changed: ' . $row['slug']);
        $validated = iss_editorial_validate_document($row['after_document'], $row['format']);
        $expect(!is_wp_error($validated) && $validated === $row['after_document'], 'Invalid document: ' . $row['slug']);
    }
    foreach ($row['before_fields'] ?? [] as $field => $value) {
        $expect(in_array($field, ['post_title', 'post_content', 'post_excerpt'], true) && array_keys($row['before_fields']) === array_keys($row['after_fields'])
            && get_post_field($field, $post->ID, 'raw') === $value, 'Post field changed.');
    }
    if (isset($row['before_terms'])) {
        $expect($post->post_type === 'projekt' && $term_slugs($post->ID) === $row['before_terms'] && !array_diff($row['after_terms'], ['in-vorbereitung', 'laufend', 'abgeschlossen']), 'Unexpected project status.');
    }
    $row['post_before'] = $post->to_array();
    $row['meta_before'] = get_post_meta($post->ID);
    $row['draft_hash'] = $draft_hash($post->ID);
    $receipt['records'][] = $row;
}
foreach ($receipt['terms'] as &$term) {
    $expect(in_array($term['taxonomy'], ['project_status', ISS_WF_IMPORT_FAMILY_TAXONOMY], true), 'Unexpected taxonomy.');
    $saved = get_term_by('slug', $term['slug'], $term['taxonomy']);
    $expect($term['before'] === null ? !$saved : ($saved instanceof WP_Term && $saved->name === $term['before']), 'Term changed: ' . $term['slug']);
    $term['before_record'] = $saved ? $saved->to_array() : null;
}
unset($term);
$expect(get_option('iss_control_contact') === $receipt['contact']['before'], 'Contact settings changed.');
$expected_contact = $receipt['contact']['before'];
$expected_contact['website'] = '';
$expect($receipt['contact']['after'] === $expected_contact, 'Only the redundant old website link may change.');
if ($mode === 'dry-run') { echo 'Ready: ' . count($receipt['records']) . " records and 17 verified PDFs. No schema changes.\n"; return; }
$expect(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image not saved.');
foreach ($receipt['media'] as &$asset) {
    if ($asset['id']) { continue; }
    $id = wp_insert_attachment(wp_slash([
        'post_title' => $asset['title'], 'post_status' => 'inherit', 'post_mime_type' => 'application/pdf',
        'post_content' => 'Quelle: ' . $asset['source'],
    ]), $uploads['basedir'] . '/' . $asset['path'], 0, true);
    $expect(!is_wp_error($id) && $id > 0, 'PDF attachment registration failed.');
    $asset['id'] = $id;
    // PDF originals are downloads; no generated cover images are required.
    update_option($key, $receipt, false);
}
unset($asset);
foreach (array_unique(array_column($receipt['records'], 'post_type')) as $type) {
    add_filter('wp_' . $type . '_revisions_to_keep', static fn(): int => -1, PHP_INT_MAX);
}
foreach ($receipt['terms'] as $term) {
    $result = $term['before_record']
        ? wp_update_term($term['before_record']['term_id'], $term['taxonomy'], ['name' => $term['after']])
        : wp_insert_term($term['after'], $term['taxonomy'], ['slug' => $term['slug']]);
    $expect(!is_wp_error($result), 'Term save failed.');
}
foreach ($receipt['records'] as $row) {
    if (isset($row['after_document'])) {
        $expect(iss_editorial_save_document($row['id'], $row['format'], $row['after_document'], false), 'Document save failed.');
    }
    if (isset($row['after_fields'])) {
        $result = wp_update_post(wp_slash(['ID' => $row['id']] + $row['after_fields']), true);
        $expect(!is_wp_error($result) && $result === $row['id'], 'Post save failed.');
    }
    if (isset($row['after_terms'])) {
        $result = wp_set_object_terms($row['id'], $row['after_terms'], 'project_status');
        $expect(!is_wp_error($result), 'Project status save failed.');
    }
}
update_option('iss_control_contact', Industriesalon_Steuerung::instance()->sanitize_contact($receipt['contact']['after']));
$receipt['complete'] = true;
update_option($key, $receipt, false);
$verify();
