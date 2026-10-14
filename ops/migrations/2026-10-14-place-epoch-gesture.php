<?php
/**
 * Add one explicitly chosen KAOS DDR invitation through the existing dynamic gesture.
 * dry-run|apply|verify|rollback; requires the matching Place source/renderer code.
 * Before-image: private non-autoload iss_backup_20261014_place_epoch_gesture.
 * Apply after the Place JSON migration; never automatically applied on deployment.
 * Guarded rollback restores the prior document through its atomic owner API.
 * No new Set, schema, media or uploads artifact. Existing intake choice is unchanged.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$mode = $args[0] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'verify', 'rollback'], true)) { WP_CLI::error('Use dry-run, apply, verify or rollback.'); }
$post = get_page_by_path('kaos-93-kunst-und-gewerbehof-genossenschaft-i-gr', OBJECT, 'register_place');
if (!$post || !iss_editorial_document_is_enabled($post->ID, 'place')) { WP_CLI::error('Reviewed enabled Place is missing.'); }
$document = iss_editorial_get_document($post->ID, 'place');
$service = iss_register_get_epoch_service();
$backup_key = 'iss_backup_20261014_place_epoch_gesture';
$backup = get_option($backup_key);
$preserved = static function () use ($post): array {
    $meta = get_post_meta($post->ID);
    foreach ([iss_editorial_get_document_meta_key('place'), ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY, ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY, '_edit_lock'] as $key) { unset($meta[$key]); }
    return ['meta' => $meta, 'core' => array_intersect_key(get_post($post->ID)->to_array(), array_flip(['post_title', 'post_excerpt', 'post_content', 'post_status', 'post_name']))];
};
$phases = static function () use ($service, $post): array {
    return array_map(static function (array $row): array {
        unset($row['id'], $row['sort_order'], $row['created_at'], $row['updated_at']);
        return $row;
    }, $service->get_epochs_for_place($post->ID));
};
$verify = static function () use ($post, $service, &$backup, $preserved, $phases): void {
    if (!is_array($backup) || (int) $backup['post_id'] !== $post->ID
        || iss_editorial_get_document($post->ID, 'place') !== $backup['installed']
        || $preserved() !== $backup['preserved'] || $phases() !== $backup['phases']
        || $service->get_milestones_for_place($post->ID) !== $backup['milestones']) { WP_CLI::error('Document or preserved facts differ; no overwrite.'); }
    WP_CLI::success('One chosen KAOS DDR gesture verified; other epochs, facts, milestones and Place intake preserved.');
};
if ($mode === 'verify') { $verify(); return; }
if ($mode === 'rollback') {
    $verify();
    if (!iss_editorial_save_document($post->ID, 'place', $backup['document'], false, true)) { WP_CLI::error('Atomic owner rollback failed.'); }
    WP_CLI::success('Previous document restored; backup retained.'); return;
}
if (is_array($backup)) {
    if ($document === $backup['installed']) { $verify(); return; }
    WP_CLI::error('Before-image already exists; do not replace or reapply after rollback.');
}
if (hash('sha256', iss_editorial_encode_document($document)) !== '2b97a779c11747a87f59da59c30c8e0668c9233050569413572aabfa1e22b0f3') { WP_CLI::error('Reviewed seven-section document differs.'); }
$index = 2;
$epoch = $document['sections'][$index];
if ($epoch['type'] !== 'epoche' || $epoch['title'] !== 'Industrieareal in DDR-Zeit' || $epoch['start_year'] !== 1945 || $epoch['end_year'] !== 1990) { WP_CLI::error('Reviewed DDR context differs.'); }
$candidate = $document;
array_splice($candidate['sections'], $index + 1, 0, [['type' => 'dynamic_slot', 'slot_key' => 'place-epoch-contribution', 'treatment' => 'slot.place-epoch-contribution', 'title' => '']]);
$candidate = iss_editorial_validate_document($candidate, 'place');
if (is_wp_error($candidate)) { WP_CLI::error($candidate->get_error_message()); }
if ($mode === 'dry-run') { WP_CLI::success('Ready: add one dynamic gesture after the DDR phase; other phases receive no automatic invitation.'); return; }
$backup = ['post_id' => $post->ID, 'document' => $document, 'installed' => $candidate, 'preserved' => $preserved(), 'phases' => $phases(), 'milestones' => $service->get_milestones_for_place($post->ID)];
if (!add_option($backup_key, $backup, '', false) || get_option($backup_key) !== $backup) { WP_CLI::error('Private before-image could not be verified.'); }
if (!iss_editorial_save_document($post->ID, 'place', $candidate, false, true)) { WP_CLI::error('Atomic owner activation failed; before-image retained.'); }
$verify();
