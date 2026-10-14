<?php
/**
 * Reviewed existing Place CPT JSON activation: dry-run|apply|verify|rollback.
 * Requires current Place editor/schema code and the adjacent reviewed JSON input.
 * Before-images: private non-autoload iss_backup_20261014_place_json_<slug hash>;
 * KAOS retains iss_backup_20261014_kaos_place_json from its first reviewed activation.
 * Optional second argument selects one slug. Enabled Kino pilot is outside this manifest.
 * Rollback refuses later edits, restores metadata and phases in the owner transaction.
 * Retains core content, legacy facts, media permissions, milestones and intake choice.
 * No new uploads; the earlier TIFF preview uses its separate paired media artifact.
 */
if (!defined('ABSPATH') || !defined('WP_CLI')) { exit(1); }
$mode = $args[0] ?? 'dry-run';
if (!in_array($mode, ['dry-run', 'apply', 'verify', 'rollback'], true)) { WP_CLI::error('Use dry-run, apply, verify or rollback.'); }
$manifest = json_decode((string) file_get_contents(__DIR__ . '/2026-10-14-place-documents.json'), true);
if (!is_array($manifest) || !$manifest) { WP_CLI::error('Reviewed manifest is missing.'); }
$target = $args[1] ?? '';
if ($target !== '') {
    if (!isset($manifest[$target])) { WP_CLI::error('Slug is outside the reviewed manifest.'); }
    $manifest = [$target => $manifest[$target]];
}
// Preflight all requested legacy sources before any write; never replace an existing document.
foreach ($manifest as $slug => $record) {
    $candidate_post = get_page_by_path($slug, OBJECT, 'register_place');
    if (!$candidate_post || !in_array($candidate_post->post_status, ['publish', 'draft', 'private'], true)) { WP_CLI::error('Reviewed Place is missing: ' . $slug); }
    $candidate = iss_editorial_validate_document($record['document'], 'place');
    if (is_wp_error($candidate)) { WP_CLI::error($candidate->get_error_message()); }
    if (iss_editorial_document_is_enabled($candidate_post->ID, 'place')) { continue; }
    if (iss_content_model_build_place_editorial_candidate($candidate_post) !== $record['document']) { WP_CLI::error('Legacy content differs: ' . $slug); }
    if (get_metadata_raw('post', $candidate_post->ID, iss_editorial_get_document_meta_key('place'), true)) { WP_CLI::error('Stored document already exists: ' . $slug); }
    foreach (wp_get_post_revisions($candidate_post->ID, ['check_enabled' => false]) as $revision) {
        if (get_post_meta($revision->ID, iss_editorial_get_document_meta_key('place'), true)) { WP_CLI::error('Recovery/revision document exists: ' . $slug); }
    }
}
foreach ($manifest as $slug => $record) {
    $post = get_page_by_path($slug, OBJECT, 'register_place');
    if (!$post || !in_array($post->post_status, ['publish', 'draft', 'private'], true) || !function_exists('iss_content_model_build_place_editorial_candidate')) { WP_CLI::error('Reviewed published Place or owner code is missing.'); }
    $service = iss_register_get_epoch_service();
    $backup_key = $slug === 'kaos-93-kunst-und-gewerbehof-genossenschaft-i-gr' ? 'iss_backup_20261014_kaos_place_json' : 'iss_backup_20261014_place_json_' . substr(hash('sha256', $slug), 0, 24);
    $backup = get_option($backup_key);
    $input = $record['document'];
    $validated = iss_editorial_validate_document($input, 'place');
    if (is_wp_error($validated)) { WP_CLI::error($validated->get_error_message()); }
    $keys = array_filter([iss_editorial_get_document_meta_key('place'), iss_editorial_get_enabled_meta_key('place'), iss_editorial_get_skin_meta_key('place')]);
    $core = static fn(): array => array_intersect_key(get_post($post->ID)->to_array(), array_flip(['post_title', 'post_content', 'post_excerpt', 'post_name', 'post_status']));
    $preserved = static function () use ($post, $keys): array {
        $meta = get_post_meta($post->ID);
        foreach (array_merge($keys, [ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY, ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY, '_iss_register_epoch_save_error', '_edit_lock']) as $key) { unset($meta[$key]); }
        return $meta;
    };
    $verify = static function () use ($post, $service, &$backup, $core, $preserved): void {
        if (!is_array($backup) || (int) $backup['post_id'] !== $post->ID
            || !iss_editorial_document_is_enabled($post->ID, 'place')
            || iss_editorial_get_document($post->ID, 'place') !== $backup['installed_document']
            || $core() !== $backup['core'] || $preserved() !== $backup['preserved_meta']
            || $service->get_milestones_for_place($post->ID) !== $backup['milestones']) { WP_CLI::error('Document or preserved baseline differs; no overwrite.'); }
        $rows = $service->get_epochs_for_place($post->ID);
        $projected = iss_register_editorial_place_epoch_rows($backup['installed_document']);
        if (count($rows) !== count($projected)) { WP_CLI::error('Phase count differs.'); }
        foreach ($projected as $index => $row) {
            foreach ($row as $key => $value) {
                if ($key !== 'sort_order' && ($rows[$index][$key] ?? null) !== $value) { WP_CLI::error('Phase projection differs: ' . $key); }
            }
        }
        WP_CLI::success(sprintf('%s: %d JSON sections; original core/meta, milestones, media and intake preserved.', $post->post_name, count($backup['installed_document']['sections'])));
    };
    if ($mode === 'rollback') {
        $verify();
        $result = $service->with_place_transaction($post->ID, static function () use ($post, $service, $backup, $keys) {
            foreach ($keys as $key) {
                delete_post_meta($post->ID, $key);
                foreach ($backup['editorial_meta'][$key] ?? [] as $value) { add_post_meta($post->ID, $key, wp_slash(maybe_unserialize($value))); }
            }
            return $service->save_epochs_for_place($post->ID, $backup['epochs'], ['source' => 'place_json_rollback']);
        });
        if (is_wp_error($result) || $result === false) { WP_CLI::error('Owner rollback failed.'); }
        iss_register_clear_places_cache();
        WP_CLI::success('Restored the legacy editor and phase before-image; backup retained.'); continue;
    }
    if ($mode === 'verify' || iss_editorial_document_is_enabled($post->ID, 'place')) { $verify(); continue; }
    if (is_array($backup)) { WP_CLI::error('Before-image already exists; do not overwrite or reapply after rollback.'); }
    if (get_metadata_raw('post', $post->ID, iss_editorial_get_document_meta_key('place'), true)) { WP_CLI::error('A stored document already exists; no overwrite.'); }
    foreach (wp_get_post_revisions($post->ID, ['check_enabled' => false]) as $revision) {
        if (get_post_meta($revision->ID, iss_editorial_get_document_meta_key('place'), true)) { WP_CLI::error('A recovery/revision document exists; review before migration.'); }
    }
    if (iss_content_model_build_place_editorial_candidate($post) !== $input) { WP_CLI::error('Legacy content differs from the reviewed JSON input.'); }
    if (count($service->get_milestones_for_place($post->ID)) !== $record['milestone_count']) { WP_CLI::error('Reviewed chronology baseline differs.'); }
    if ($mode === 'dry-run') { WP_CLI::success('Ready: ' . $slug . ' (' . count($input['sections']) . ' sections).'); continue; }
    $all_meta = get_post_meta($post->ID);
    $backup = ['post_id' => $post->ID, 'core' => $core(), 'preserved_meta' => $preserved(), 'editorial_meta' => array_intersect_key($all_meta, array_flip($keys)), 'epochs' => $service->get_epochs_for_place($post->ID), 'milestones' => $service->get_milestones_for_place($post->ID), 'installed_document' => $validated];
    if (!add_option($backup_key, $backup, '', false) || get_option($backup_key) !== $backup) { WP_CLI::error('Could not verify the private before-image.'); }
    if (!iss_editorial_save_document($post->ID, 'place', $validated, false, true)) { WP_CLI::error('Atomic document activation failed; before-image retained.'); }
    iss_register_clear_places_cache();
    $verify();
}
