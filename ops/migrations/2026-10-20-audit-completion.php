<?php
/**
 * Apply the remaining reviewed website audit corrections through WordPress/editorial APIs.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 *
 * Input and before-images stay private. No uploads or schema changes.
 * Rollback: verify the receipt's after-state against later edits first, then
 * restore its before documents with iss_editorial_save_document() and its
 * before_fields with wp_update_post(), options with update_option(), and template
 * before post_name/status with wp_update_post(). Restore before_meta through
 * update_post_meta(). Check later edits first; retain receipts and revisions.
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
$batch = $input['batch'] ?? 'completion';
$expect(in_array($batch, ['completion', 'place-copy'], true), 'Unknown batch.');
$scope = [
    'page/home-2', 'page/fuehrungen', 'projekt/futura-biennale-2027-2',
    'projekt/schoeneweider-kiezkarte', 'projekt/zukunft-im-gespraech', 'page/about',
    'publication/rohren-fur-die-republik-eine-technikgeschichte', 'projekt/walk-of-fame-schoeneweide',
    'video/mueller-wf', 'video/abriss-der-denkmalgeschutzten-fernmeldekabelfabrik-architekt-ernst-ziesel-im-jahr-2007',
];
if ($batch === 'place-copy') { $scope = ['page/schoneweide', 'register_place/industriesalon-schoneweide-reinbeckstrasse']; }
$expect(array_map(static fn(array $row): string => $row['post_type'] . '/' . $row['slug'], $input['records']) === $scope, 'Unexpected migration scope.');
$receipt_key = $batch === 'completion' ? 'iss_backup_20261020_audit_completion' : 'iss_backup_20261020_audit_place_copy';
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
        foreach ($row['after_fields'] ?? [] as $field => $value) {
            $expect(get_post_field($field, $id, 'raw') === $value, 'Post field differs: ' . $id . '/' . $field);
        }
        foreach ($row['after_meta'] ?? [] as $key => $value) {
            $expect(get_post_meta($id, $key, true) === $value, 'Metadata differs: ' . $id);
        }
        $expect($draft_hash($id) === $row['draft_hash'], 'Private draft changed: ' . $id);
    }
    foreach ($receipt['options'] as $key => $option) {
        $expect(get_option($key) === $option['after'], 'Option differs: ' . $key);
    }
    foreach ($receipt['templates'] as $template) {
        $active = get_block_template('industriesalon//' . $template['slug'], 'wp_template');
        $retired = get_post($template['before']['ID']);
        $expect($active && $active->source === 'theme' && $retired instanceof WP_Post
            && $retired->post_status === 'draft' && $retired->post_name === $template['retired_slug']
            && $retired->post_content === $template['before']['post_content'], 'Template preservation differs.');
    }
    echo 'Verified ' . count($receipt['records']) . ' content records, ' . count($receipt['options']) . ' settings and ' . count($receipt['templates']) . " preserved template overrides; private drafts preserved.\n";
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
        $expect(in_array($field, ['post_excerpt', 'post_content', 'post_title'], true)
            && array_keys($row['before_fields']) === array_keys($row['after_fields'])
            && get_post_field($field, $post->ID, 'raw') === $value, 'Post changed: ' . $row['slug']);
    }
    foreach ($row['before_meta'] ?? [] as $key => $value) {
        $expect($key === 'iss_video_year' && array_keys($row['before_meta']) === array_keys($row['after_meta'])
            && get_post_meta($post->ID, $key, true) === $value, 'Metadata changed: ' . $row['slug']);
    }
    $row['post_before'] = $post->to_array();
    $row['meta_before'] = get_post_meta($post->ID);
    $row['draft_hash'] = $draft_hash($post->ID);
    $receipt['records'][] = $row;
}
$expect(array_keys($input['options']) === ($batch === 'completion' ? ['iss_control_contact', 'newsletter_form'] : []), 'Unexpected options.');
$receipt['options'] = $input['options'];
foreach ($receipt['options'] as $key => $option) {
    $expect(get_option($key) === $option['before'], 'Option changed: ' . $key);
    $changed = array_keys(array_diff_assoc($option['after'], $option['before']));
    $expect(!array_diff($changed, $key === 'newsletter_form' ? ['email_placeholder'] : ['facebook', 'instagram', 'youtube']), 'Unexpected setting change.');
}
$expect(array_column($input['templates'], 'slug') === ($batch === 'completion' ? ['page-publikationen', 'page-projekte'] : []), 'Unexpected template scope.');
$receipt['templates'] = [];
foreach ($input['templates'] as $template) {
    $active = get_block_template('industriesalon//' . $template['slug'], 'wp_template');
    $retired_slug = $template['slug'] . '-pre-audit-20261020';
    $expect($active && $active->source === 'custom' && $active->wp_id > 0
        && hash('sha256', $active->content) === $template['sha256']
        && !get_page_by_path($retired_slug, OBJECT, 'wp_template'), 'Template changed: ' . $template['slug']);
    $receipt['templates'][] = $template + ['before' => get_post($active->wp_id)->to_array(),
        'before_meta' => get_post_meta($active->wp_id), 'retired_slug' => $retired_slug];
}
if ($mode === 'dry-run') { echo 'Ready: ' . count($receipt['records']) . ' records, ' . count($receipt['options']) . ' settings and ' . count($receipt['templates']) . " reviewed template overrides. No SQL or uploads.\n"; return; }
$expect(add_option($receipt_key, $receipt, '', false) && get_option($receipt_key) === $receipt, 'Before-image not saved.');
foreach ($receipt['records'] as $row) {
    if (isset($row['after_document'])) {
        $expect(iss_editorial_save_document($row['id'], $row['format'], $row['after_document'], false), 'Document save failed: ' . $row['slug']);
    }
    foreach ($row['after_meta'] ?? [] as $key => $value) { update_post_meta($row['id'], $key, $value); }
    if (isset($row['after_fields'])) {
        $result = wp_update_post(wp_slash(['ID' => $row['id']] + $row['after_fields']), true);
        $expect(!is_wp_error($result) && $result === $row['id'], 'Post save failed: ' . $row['slug']);
    }
}
foreach ($receipt['options'] as $key => $option) { update_option($key, $option['after']); }
foreach ($receipt['templates'] as $template) {
    $result = wp_update_post(['ID' => $template['before']['ID'], 'post_name' => $template['retired_slug'], 'post_status' => 'draft'], true);
    $expect(!is_wp_error($result) && $result === $template['before']['ID'], 'Template retirement failed.');
}
$receipt['complete'] = true;
update_option($receipt_key, $receipt, false);
$expect(get_option($receipt_key) === $receipt, 'Receipt not saved.');
$verify();
