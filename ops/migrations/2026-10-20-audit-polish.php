<?php
/**
 * Apply the reviewed copy and periodisation polish through WordPress/editorial APIs.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 *
 * Input and before-images stay private. No uploads or schema changes.
 * Rollback: verify the receipt's after-state against later edits first, then
 * restore its before documents with iss_editorial_save_document();
 * keep receipts and revisions. This batch changes only four landing documents.
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
$expect(($input['home_url'] ?? '') === home_url(), 'Input belongs to another site.');
$expect(($input['batch'] ?? '') === 'polish', 'Unexpected batch.');
$scope = ['page/home-2', 'page/fuehrungen', 'page/schoneweide', 'page/about'];
$expect(array_map(static fn(array $row): string => $row['post_type'] . '/' . $row['slug'], $input['records']) === $scope, 'Unexpected migration scope.');
$receipt_key = 'iss_backup_20261020_audit_polish';
$receipt = get_option($receipt_key);
$hash = static fn($value): string => hash('sha256', wp_json_encode($value));
$draft_hash = static function (int $id) use ($hash): string {
    $meta = array_filter(get_post_meta($id), static fn(string $key): bool => str_contains($key, 'draft') || str_contains($key, 'autosave'), ARRAY_FILTER_USE_KEY);
    $autosaves = [];
    foreach (wp_get_post_revisions($id, ['posts_per_page' => -1]) as $revision) {
        if (wp_is_post_autosave($revision->ID)) { $autosaves[$revision->ID] = [$revision->to_array(), get_post_meta($revision->ID)]; }
    }
    return $hash([$meta, $autosaves]);
};
$verify = static function () use (&$receipt, $expect, $draft_hash, $path): void {
    $expect(is_array($receipt) && !empty($receipt['complete']) && $receipt['input_sha256'] === hash_file('sha256', $path), 'Missing or partial receipt; inspect before retrying.');
    foreach ($receipt['records'] as $row) {
        $id = $row['id'];
        if (isset($row['after_document'])) {
            $expect(iss_editorial_get_document($id, $row['format'], false) === $row['after_document'], 'Saved document differs: ' . $id);
        }
        $expect($draft_hash($id) === $row['draft_hash'], 'Private draft changed: ' . $id);
    }
    echo 'Verified ' . count($receipt['records']) . ' content records, ' . count($receipt['options']) . ' settings and ' . count($receipt['templates']) . " preserved template overrides; private drafts preserved.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'records' => []];
foreach ($input['records'] as $row) {
    $expect($row['format'] === 'landing' && array_keys($row) === ['post_type', 'slug', 'title', 'format', 'before_document', 'after_document'], 'Unexpected document payload.');
    $post = get_page_by_path($row['slug'], OBJECT, $row['post_type']);
    $expect($post instanceof WP_Post && $post->post_status === 'publish' && $post->post_title === $row['title'], 'Post identity differs: ' . $row['slug']);
    $row['id'] = $post->ID;
    if (isset($row['before_document'])) {
        $expect(iss_editorial_get_document($post->ID, $row['format'], false) === $row['before_document'], 'Document changed: ' . $row['slug']);
        $after = iss_editorial_validate_document($row['after_document'], $row['format']);
        $expect(!is_wp_error($after) && $after === $row['after_document'], 'Invalid document: ' . $row['slug']);
    }
    $row['post_before'] = $post->to_array();
    $row['meta_before'] = get_post_meta($post->ID);
    $row['draft_hash'] = $draft_hash($post->ID);
    $receipt['records'][] = $row;
}
$expect($input['options'] === [] && $input['templates'] === [], 'Only landing documents are in scope.');
$receipt['options'] = [];
$receipt['templates'] = [];
if ($mode === 'dry-run') { echo 'Ready: ' . count($receipt['records']) . ' records, ' . count($receipt['options']) . ' settings and ' . count($receipt['templates']) . " reviewed template overrides. No SQL or uploads.\n"; return; }
$expect(add_option($receipt_key, $receipt, '', false) && get_option($receipt_key) === $receipt, 'Before-image not saved.');
// Preserve existing normal revisions as well as private autosaves during this batch.
add_filter('wp_page_revisions_to_keep', static fn(): int => -1, PHP_INT_MAX);
foreach ($receipt['records'] as $row) {
    if (isset($row['after_document'])) {
        $expect(iss_editorial_save_document($row['id'], $row['format'], $row['after_document'], false), 'Document save failed: ' . $row['slug']);
    }
}
$receipt['complete'] = true;
update_option($receipt_key, $receipt, false);
$expect(get_option($receipt_key) === $receipt, 'Receipt not saved.');
$verify();
