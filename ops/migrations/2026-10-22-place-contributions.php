<?php
/**
 * Remove public supplier citations and open existing invitations at reviewed questions.
 * eval-file FILE dry-run|apply|verify PRIVATE_MANIFEST --use-include --user=admin
 * Requires a verified target backup. Payloads and receipt remain private.
 * Rollback after reviewing newer edits: restore receipt documents/meta through
 * owner APIs, reverse reviewed milestone summaries with revise_milestone_summary(),
 * and close only intake opened by this batch with iss_content_set_upload_open().
 * Never replay old upload generations, credentials or a complete database.
 */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options')) {
    throw new RuntimeException('Administrator CLI required.');
}
require_once __DIR__ . '/place-review-preservation.php';
// phpcs:disable WordPress.Security.EscapeOutput -- CLI JSON only.
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
iss_register_review_require(in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Mode and private manifest required.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
iss_register_review_require($input['batch'] === 'place-contributions-20261022' && $input['home_url'] === home_url() && count($input['records']) === 62, 'Wrong reviewed batch or target.');
$service = iss_register_get_epoch_service();
$key = 'iss_backup_20261022_place_contributions';
$receipt = get_option($key);
$verify = static function () use (&$receipt, $path, $service): void {
    iss_register_review_require(is_array($receipt) && !empty($receipt['complete']) && $receipt['input_sha256'] === hash_file('sha256', $path), 'Missing, partial or different receipt.');
    foreach ($receipt['records'] as $row) {
        $id = $row['id'];
        iss_register_review_require(iss_editorial_get_document($id, 'place', false) === $row['after_document'], 'Document differs: ' . $row['slug']);
        foreach ($row['after_meta'] as $name => $value) {
            iss_register_review_require(get_post_meta($id, $name, true) === $value, 'Metadata differs: ' . $name);
        }
        foreach ($row['milestones'] as $edit) {
            $expected = $edit['before'];
            $expected['summary'] = $edit['summary'];
            iss_register_review_require(in_array($expected, $service->get_milestones_for_place($id), true), 'Milestone or provenance differs.');
        }
        iss_register_review_require(iss_content_upload_is_open($id) && iss_content_upload_url($id) !== '', 'Question invitation is not open.');
    }
    iss_register_review_require(iss_register_review_protected($receipt['targets'], $receipt['milestone_summaries']) === $receipt['protected'], 'Protected data differs.');
    $revisions = iss_register_review_revisions();
    foreach ($receipt['revisions'] as $id => $hash) {
        iss_register_review_require(($revisions[$id] ?? '') === $hash, 'Existing revision changed.');
    }
    echo wp_json_encode(['verified_places' => 62, 'stats' => $receipt['stats'], 'preserved' => array_keys($receipt['protected']), 'existing_revisions_preserved' => count($receipt['revisions']), 'idempotent' => true]) . "\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'stats' => $input['stats'], 'records' => [], 'targets' => [], 'milestone_summaries' => []];
foreach ($input['records'] as $row) {
    $id = (int) $row['id'];
    $post = get_post($id);
    iss_register_review_require($post && $post->post_type === 'register_place' && $post->post_status === 'publish' && $post->post_name === $row['slug'] && !isset($receipt['targets'][$id]), 'Place identity changed.');
    iss_register_review_require(!empty(get_post_meta($id, 'legacy_questions', true)) && iss_editorial_document_is_enabled($id, 'place'), 'Reviewed questions or enabled document absent.');
    iss_register_review_require(iss_editorial_get_document($id, 'place', false) === $row['before_document'] && iss_content_upload_is_open($id) === $row['before_intake'], 'Content or intake changed since review.');
    iss_register_review_require(iss_editorial_validate_document($row['after_document'], 'place') === $row['after_document'], 'Invalid document.');
    $protected = $row['after_document'];
    $public_edits = [];
    foreach ($protected['sections'] as $index => &$section) {
        if ($section['type'] === 'gegenwart') {
            if ($section !== $row['before_document']['sections'][$index]) { $public_edits[] = $section; }
            $section['body'] = $row['before_document']['sections'][$index]['body'];
            $section['links'] = $row['before_document']['sections'][$index]['links'];
        }
    }
    unset($section);
    iss_register_review_require($protected === $row['before_document'] && in_array('upload_intake', array_column($protected['sections'], 'type'), true), 'History, structure or existing gesture changed.');
    iss_register_review_require(array_keys($row['before_meta']) === array_keys($row['after_meta']), 'Metadata key mismatch.');
    foreach ($row['after_meta'] as $name => $value) {
        iss_register_review_require(in_array($name, ['owner', 'developer', 'tenant', 'size', 'investment', 'legacy_kaufpreis', 'current_use'], true) && get_post_meta($id, $name, true) === $row['before_meta'][$name] && iss_register_sanitize_meta_value($name, $value) === $value, 'Unreviewed metadata edit.');
    }
    iss_register_review_require(!is_wp_error(iss_register_validate_public_import_text($id, [$public_edits, $row['after_meta'], array_column($row['milestones'], 'summary')])), 'Changed public text privacy validation failed.');
    $receipt['milestone_summaries'][$id] = [];
    foreach ($row['milestones'] as $edit) {
        iss_register_review_require(in_array($edit['before'], $service->get_milestones_for_place($id), true) && sanitize_textarea_field($edit['summary']) === $edit['summary'], 'Milestone changed since review.');
        $receipt['milestone_summaries'][$id][] = $edit['before']['key'];
    }
    $row['intake_meta_before'] = ['_iss_upload_open' => get_post_meta($id, '_iss_upload_open'), '_iss_upload_generation' => get_post_meta($id, '_iss_upload_generation')];
    $receipt['targets'][$id] = array_keys($row['after_meta']);
    if ($row['before_document'] !== $row['after_document']) {
        $receipt['targets'][$id] = array_merge($receipt['targets'][$id], [iss_editorial_get_document_meta_key('place'), ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY, ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY]);
    }
    if ($row['open_intake']) { $receipt['targets'][$id] = array_merge($receipt['targets'][$id], ['_iss_upload_open', '_iss_upload_generation']); }
    $receipt['records'][] = $row;
}
$receipt['protected'] = iss_register_review_protected($receipt['targets'], $receipt['milestone_summaries']);
$receipt['revisions'] = iss_register_review_revisions();
if ($mode === 'dry-run') {
    echo wp_json_encode(['ready' => count($receipt['records']), 'stats' => $receipt['stats']]) . "\n";
    return;
}
iss_register_review_require(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image not saved.');
add_filter('wp_register_place_revisions_to_keep', static fn(): int => -1, PHP_INT_MAX);
foreach ($receipt['records'] as $row) {
    $saved = $service->with_place_transaction($row['id'], static function () use ($row, $service): bool {
        $id = $row['id'];
        iss_register_review_require(iss_editorial_get_document($id, 'place', false) === $row['before_document'] && iss_content_upload_is_open($id) === $row['before_intake'], 'Concurrent edit.');
        foreach ($row['after_meta'] as $name => $value) {
            iss_register_review_require(get_post_meta($id, $name, true) === $row['before_meta'][$name], 'Concurrent metadata edit.');
            update_post_meta($id, $name, wp_slash($value));
        }
        foreach ($row['milestones'] as $edit) {
            iss_register_review_require($service->revise_milestone_summary($id, $edit['before'], $edit['summary']) === true, 'Milestone revision failed.');
        }
        if ($row['before_document'] !== $row['after_document']) {
            iss_register_review_require(iss_editorial_save_document($id, 'place', $row['after_document'], false), 'Document save failed.');
        }
        if ($row['open_intake']) { iss_content_set_upload_open($id, true); }
        return true;
    });
    iss_register_review_require($saved === true, 'Partial receipt retained; inspect failed Place.');
}
iss_register_clear_places_cache();
$receipt['complete'] = true;
update_option($key, $receipt, false);
$verify();
