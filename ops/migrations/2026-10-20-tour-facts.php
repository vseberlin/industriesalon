<?php
/**
 * Confirmed tour facts and separate public/group Elektropolis offers.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_JSON --use-include --user=admin
 * Apply after 2026-10-20-public-copy-cleanup.php. Inputs stay outside Git.
 * Receipt retains before-images, new post ID and the unchanged legacy source.
 * Rollback: compare against later edits before restoring existing fields/meta
 * and documents through their WordPress/editorial APIs; draft the new offer.
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
$scope = ['fuehrung/elektropolis-tour', 'iss_notice/12846', 'fuehrung/elektropolis-tour-individuell-buchbar'];
$expect(array_map(static fn(array $row): string => $row['post_type'] . '/' . $row['slug'], $input['records']) === $scope, 'Unexpected scope.');
$key = 'iss_backup_20261020_tour_facts';
$receipt = get_option($key);
$hash = static fn($value): string => hash('sha256', wp_json_encode($value));
$snapshot = static fn(int $id): array => ['post' => get_post($id)->to_array(), 'meta' => get_post_meta($id)];
$draft_hash = static function (int $id) use ($hash): string {
    $meta = array_filter(get_post_meta($id), static fn(string $key): bool => str_contains($key, 'draft') || str_contains($key, 'autosave'), ARRAY_FILTER_USE_KEY);
    $autosaves = [];
    foreach (wp_get_post_revisions($id, ['posts_per_page' => -1]) as $revision) {
        if (wp_is_post_autosave($revision->ID)) { $autosaves[$revision->ID] = [$revision->to_array(), get_post_meta($revision->ID)]; }
    }
    return $hash([$meta, $autosaves]);
};
$verify = static function () use (&$receipt, $expect, $snapshot, $hash, $draft_hash): void {
    $expect(is_array($receipt) && !empty($receipt['complete']), 'Missing or partial receipt; inspect before retrying.');
    foreach ($receipt['records'] as $row) {
        $id = $row['id'];
        $expect(get_post_status($id) === 'publish', 'Offer or notice is not published.');
        foreach ($row['after_fields'] ?? [] as $field => $value) {
            $expect(get_post_field($field, $id, 'raw') === $value, 'Post field differs: ' . $row['slug'] . '/' . $field);
        }
        foreach ($row['after_meta'] as $field => $value) {
            $expect(get_post_meta($id, $field, true) === $value, 'Metadata differs: ' . $row['slug'] . '/' . $field);
        }
        if (isset($row['after_document'])) {
            $expect(iss_editorial_get_document($id, 'fuehrung', false) === $row['after_document'], 'Document differs: ' . $row['slug']);
        }
        if (isset($row['draft_hash'])) { $expect($draft_hash($id) === $row['draft_hash'], 'Private draft changed.'); }
        if (!empty($row['create'])) {
            $expect(iss_editorial_document_is_enabled($id, 'fuehrung'), 'New offer editor not enabled.');
            $expect(!iss_fuehrung_get_next_event($id), 'Group offer must not inherit public dates.');
        }
    }
    $expect($hash($snapshot($receipt['legacy_id'])) === $receipt['legacy_hash'], 'Legacy source changed.');
    echo "Verified separate calendar/group offers, confirmed facts, notice, preserved source and drafts.\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$legacy = get_page_by_path($input['legacy_source']['slug'], OBJECT, 'post');
$expect($legacy instanceof WP_Post && $legacy->post_status === 'private'
    && $legacy->post_title === $input['legacy_source']['title'], 'Legacy group source differs.');
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'source_note' => $input['source_note'],
    'legacy_id' => $legacy->ID, 'legacy_hash' => $hash($snapshot($legacy->ID)), 'records' => []];
$allowed_meta = ['duration', 'price_note', 'booking_note', 'booking_mode', 'booking_price_cents', 'allow_on_demand_with_calendar',
    'offer_catalog_groups', 'meeting_point', 'inquiry_label', 'inquiry_note', '_thumbnail_id', 'iss_kicker', 'iss_headline'];
foreach ($input['records'] as $row) {
    $post = get_page_by_path($row['slug'], OBJECT, $row['post_type']);
    if (!empty($row['create'])) {
        $expect(!$post && $row['slug'] === 'elektropolis-tour-individuell-buchbar', 'New offer already exists.');
        $image_id = (int) $row['after_meta']['_thumbnail_id'];
        $expect(wp_attachment_is_image($image_id) && get_post_meta($image_id, '_wp_attached_file', true) === $input['image_file'], 'Group image identity differs.');
    } else {
        $expect($post instanceof WP_Post && $post->post_title === $row['title'], 'Post identity differs: ' . $row['slug']);
        $row['id'] = $post->ID;
        $row['before'] = $snapshot($post->ID);
        $row['draft_hash'] = $draft_hash($post->ID);
        foreach ($row['before_fields'] ?? [] as $field => $value) {
            $expect(get_post_field($field, $post->ID, 'raw') === $value, 'Post changed: ' . $row['slug']);
        }
        foreach ($row['before_meta'] as $field => $value) {
            $expect(get_post_meta($post->ID, $field, true) === $value, 'Metadata changed: ' . $row['slug'] . '/' . $field);
        }
        if (isset($row['before_document'])) {
            $expect(iss_editorial_get_document($post->ID, 'fuehrung', false) === $row['before_document'], 'Document changed.');
        }
    }
    $expect(!array_diff(array_keys($row['after_fields'] ?? []), ['post_content', 'post_excerpt']), 'Unexpected post fields.');
    $expect(!array_diff(array_keys($row['after_meta']), $allowed_meta), 'Unexpected metadata.');
    if (isset($row['after_document'])) {
        $document = iss_editorial_validate_document($row['after_document'], 'fuehrung');
        $expect(!is_wp_error($document), 'Invalid tour document.');
        $row['after_document'] = $document;
    }
    $receipt['records'][] = $row;
}
if ($mode === 'dry-run') { echo "Ready: public tour, access notice and restored on-demand group offer; legacy source preserved.\n"; return; }
$expect(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image not saved.');
foreach ($receipt['records'] as $index => &$row) {
    if (!empty($row['create'])) {
        $id = wp_insert_post(wp_slash(['post_type' => 'fuehrung', 'post_status' => 'draft', 'post_name' => $row['slug'], 'post_title' => $row['title']] + $row['after_fields']), true);
        $expect(!is_wp_error($id) && $id > 0, 'Group creation failed.');
        $row['id'] = $id;
        update_option($key, $receipt, false);
    }
    $id = $row['id'];
    if (isset($row['after_document'])) {
        $expect(iss_editorial_save_document($id, 'fuehrung', $row['after_document'], false, true), 'Document save failed.');
    }
    foreach ($row['after_meta'] as $field => $value) {
        update_post_meta($id, $field, wp_slash($value));
        $expect(get_post_meta($id, $field, true) === $value, 'Metadata save failed: ' . $field);
    }
    $result = wp_update_post(wp_slash(['ID' => $id, 'post_status' => 'publish'] + ($row['after_fields'] ?? [])), true);
    $expect(!is_wp_error($result) && $result === $id, 'Post save failed.');
    update_option($key, $receipt, false);
}
unset($row);
$receipt['complete'] = true;
update_option($key, $receipt, false);
$expect(get_option($key) === $receipt, 'Receipt not saved.');
$verify();
