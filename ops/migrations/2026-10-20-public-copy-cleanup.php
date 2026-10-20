<?php
/**
 * Apply the first website audit corrections through WordPress/editorial APIs.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 *
 * Input and before-images stay private. No uploads or schema changes.
 * Rollback: verify the receipt's after-state against later edits first, then
 * restore its before documents with iss_editorial_save_document() and its
 * before_fields with wp_update_post(). Keep the receipt and native revisions.
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
$scope = [
    'page/home-2', 'fuehrung/elektropolis-tour',
    'veranstaltung/evtl-txl-projekt-im-salon-nahere-infos-kommen-schon-mal-zeitraum-geblockt',
    'team_member/13082', 'team_member/klaus-burmeister', 'team_member/albert-markert',
    'team_member/anja-meyer', 'team_member/alexa-steindorf', 'team_member/guido-schlootz',
];
$expect(array_map(static fn(array $row): string => $row['post_type'] . '/' . $row['slug'], $input['records']) === $scope, 'Unexpected migration scope.');
$key = 'iss_backup_20261020_public_copy';
$receipt = get_option($key);
$hash = static fn($value): string => hash('sha256', wp_json_encode($value));
$draft_hash = static function (int $id) use ($hash): string {
    $meta = array_filter(get_post_meta($id), static fn(string $key): bool => str_contains($key, 'draft') || str_contains($key, 'autosave'), ARRAY_FILTER_USE_KEY);
    $autosaves = [];
    foreach (wp_get_post_revisions($id, ['posts_per_page' => -1]) as $revision) {
        if (wp_is_post_autosave($revision->ID)) { $autosaves[$revision->ID] = [$revision->to_array(), get_post_meta($revision->ID)]; }
    }
    return $hash([$meta, $autosaves]);
};
$verify = static function () use (&$receipt, $expect, $draft_hash): void {
    $expect(is_array($receipt) && !empty($receipt['complete']), 'Missing or partial receipt; inspect before retrying.');
    foreach ($receipt['records'] as $row) {
        $id = $row['id'];
        if (isset($row['after_document'])) {
            $expect(iss_editorial_get_document($id, $row['format'], false) === $row['after_document'], 'Saved document differs: ' . $id);
        }
        foreach ($row['after_fields'] ?? [] as $field => $value) {
            $expect(get_post_field($field, $id, 'raw') === $value, 'Post field differs: ' . $id . '/' . $field);
        }
        $expect($draft_hash($id) === $row['draft_hash'], 'Private draft changed: ' . $id);
    }
    echo "Verified two saved documents, six complete team excerpts and one unpublished working note; private drafts preserved.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'records' => []];
foreach ($input['records'] as $row) {
    $post = get_page_by_path($row['slug'], OBJECT, $row['post_type']);
    $expect($post instanceof WP_Post && $post->post_title === $row['title'], 'Post identity differs: ' . $row['slug']);
    $row['id'] = $post->ID;
    if (isset($row['before_document'])) {
        $expect(iss_editorial_get_document($post->ID, $row['format'], false) === $row['before_document'], 'Document changed: ' . $row['slug']);
        $after = iss_editorial_validate_document($row['after_document'], $row['format']);
        $expect(!is_wp_error($after) && $after === $row['after_document'], 'Invalid document: ' . $row['slug']);
    }
    foreach ($row['before_fields'] ?? [] as $field => $value) {
        $expect(in_array($field, ['post_excerpt', 'post_status'], true)
            && array_keys($row['before_fields']) === array_keys($row['after_fields'])
            && get_post_field($field, $post->ID, 'raw') === $value, 'Post changed: ' . $row['slug']);
    }
    $row['post_before'] = $post->to_array();
    $row['meta_before'] = get_post_meta($post->ID);
    $row['draft_hash'] = $draft_hash($post->ID);
    $receipt['records'][] = $row;
}
if ($mode === 'dry-run') { echo "Ready: nine records; canonical input and before-state verified.\n"; return; }
$expect(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image not saved.');
foreach ($receipt['records'] as $row) {
    if (isset($row['after_document'])) {
        $expect(iss_editorial_save_document($row['id'], $row['format'], $row['after_document'], false), 'Document save failed: ' . $row['slug']);
    }
    if (isset($row['after_fields'])) {
        $result = wp_update_post(wp_slash(['ID' => $row['id']] + $row['after_fields']), true);
        $expect(!is_wp_error($result) && $result === $row['id'], 'Post save failed: ' . $row['slug']);
    }
}
$receipt['complete'] = true;
update_option($key, $receipt, false);
$expect(get_option($key) === $receipt, 'Receipt not saved.');
$verify();
