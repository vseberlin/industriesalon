<?php
/**
 * Apply reviewed page documents through the editorial owner API.
 * wp eval-file FILE dry-run|apply|verify|rollback PRIVATE_JSON --user=ADMIN
 * wp eval-file FILE revise NEW_PRIVATE_JSON PREVIOUS_PRIVATE_JSON --user=ADMIN
 * The private, target-specific artifact includes exact before-images and media hashes.
 * It must never be committed. No post fields, private drafts or accounts are changed.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
$assert = static function (bool $ok, string $message): void { if (!$ok) { throw new RuntimeException(esc_html($message)); } };
$assert(in_array($mode, ['dry-run', 'apply', 'verify', 'rollback', 'revise'], true) && is_readable($path), 'Mode and private artifact required.');
$assert(current_user_can('manage_options'), 'Administrator required.');
$input = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$assert(($input['site'] ?? '') === home_url() && empty($input['errors']) && !empty($input['pages']), 'Artifact must match this site and have no unresolved errors.');
$hash = static fn($value): string => hash('sha256', wp_json_encode($value));
$keys = [iss_editorial_get_document_meta_key('landing'), iss_editorial_get_enabled_meta_key('landing'), iss_editorial_get_skin_meta_key('landing')];
$private = static function (int $id) use ($hash, $keys): string {
    $meta = get_post_meta($id);
    foreach ($keys as $key) { unset($meta[$key]); }
    ksort($meta);
    $drafts = [];
    foreach (wp_get_post_revisions($id, ['posts_per_page' => -1]) as $revision) {
        if (wp_is_post_autosave($revision->ID)) { $drafts[$revision->ID] = [$revision->to_array(), get_post_meta($revision->ID)]; }
    }
    return $hash([get_post($id)->to_array(), $meta, $drafts]);
};
$key = 'iss_backup_20261028_landing_normalization';
$receipt = get_option($key);
$verify = static function () use (&$receipt, $input, $assert, $hash, $private): void {
    $assert(is_array($receipt) && $receipt['complete'], 'Missing or incomplete receipt; inspect before retrying.');
    foreach ($input['pages'] as $record) {
        $id = (int) $record['id'];
        $assert($hash(iss_editorial_get_document($id, 'landing', false)) === $hash($record['after']) && iss_editorial_document_is_enabled($id, 'landing'), 'Saved content differs: ' . $record['slug']);
        $assert($private($id) === $receipt['private'][$id], 'Private draft or unrelated state changed: ' . $record['slug']);
    }
    WP_CLI::success(count($input['pages']) . ' documents verified; post fields, unrelated metadata and private drafts preserved.');
};
if ($mode === 'verify' || ($receipt && $mode === 'apply')) { $assert(($receipt['input_hash'] ?? '') === hash_file('sha256', $path), 'Receipt belongs to another artifact.'); $verify(); return; }
if ($mode === 'revise') {
    $previous_path = $args[2] ?? '';
    $assert(is_readable($previous_path) && !empty($receipt['complete']) && hash_file('sha256', $previous_path) === $receipt['input_hash'], 'Verified previous artifact required.');
    $previous = json_decode((string) file_get_contents($previous_path), true, 512, JSON_THROW_ON_ERROR);
    $assert(array_column($previous['pages'], 'id') === array_column($input['pages'], 'id') && $previous['media'] === $input['media'], 'Revision must retain the reviewed page scope and media.');
    foreach ($input['pages'] as $index => $record) {
        $id = (int) $record['id']; $old = $previous['pages'][$index];
        $assert($record['before'] === $old['before'] && $record['post_before'] === $old['post_before'], 'Original before-image must be retained.');
        $current = json_decode(get_post_meta($id, $keys[0], true), true, 512, JSON_THROW_ON_ERROR);
        $assert($current === $old['after'] && $private($id) === $receipt['private'][$id], 'Intervening edit prevents revision: ' . $record['slug']);
        $valid = iss_editorial_validate_document($record['after'], 'landing');
        $assert(!is_wp_error($valid) && $valid === $record['after'] && industriesalon_landing_has_opening($valid), 'Invalid revised document.');
    }
    $receipt['input_history'][] = $receipt['input_hash']; $receipt['complete'] = false;
    update_option($key, $receipt, false); $assert(get_option($key) === $receipt, 'Revision receipt could not be verified.');
    foreach ($input['pages'] as $index => $record) {
        if ($record['after'] !== $previous['pages'][$index]['after']) {
            $assert(iss_editorial_save_document((int) $record['id'], 'landing', $record['after'], false, true), 'Revision save failed.');
        }
    }
    $receipt['input_hash'] = hash_file('sha256', $path); $receipt['complete'] = true;
    update_option($key, $receipt, false); $verify(); return;
}
if ($mode === 'rollback') {
    $verify();
    foreach ($input['pages'] as $record) {
        $id = (int) $record['id'];
        $raw = $record['before'][$keys[0]][0] ?? null;
        if ($raw !== null) { $assert(iss_editorial_save_document($id, 'landing', json_decode($raw, true, 512, JSON_THROW_ON_ERROR), false, !empty($record['before'][$keys[1]][0])), 'Rollback failed: ' . $record['slug']); }
        foreach ($keys as $meta_key) {
            if (!array_key_exists($meta_key, $record['before'])) { delete_post_meta($id, $meta_key); }
            else { update_post_meta($id, $meta_key, wp_slash($record['before'][$meta_key][0])); }
        }
        $assert(get_post_meta($id) === $record['before'], 'Rollback metadata differs: ' . $record['slug']);
    }
    $receipt['rolled_back'] = gmdate('c'); $receipt['complete'] = false; update_option($key, $receipt, false);
    WP_CLI::success('Restored exact pre-migration metadata; receipt retained.'); return;
}
$assert(!$receipt, 'A receipt already exists; inspect it before another migration.');
foreach ($input['media'] ?? [] as $id => $media) {
    $file = get_attached_file((int) $id);
    $assert(get_post_type((int) $id) === 'attachment' && get_post_meta((int) $id, '_wp_attached_file', true) === $media['file'] && is_file($file) && hash_file('sha256', $file) === $media['sha256'], 'Media identity or file differs: ' . $id);
}
$receipt = ['created' => gmdate('c'), 'complete' => false, 'input_hash' => hash_file('sha256', $path), 'before' => [], 'private' => []];
foreach ($input['pages'] as $record) {
    $id = (int) $record['id']; $post = get_post($id);
    $assert($post instanceof WP_Post && $post->post_type === 'page' && $post->post_name === $record['slug'] && $post->post_status === 'publish', 'Page identity differs.');
    $assert(get_post_meta($id) === $record['before'] && array_intersect_key($post->to_array(), $record['post_before']) === $record['post_before'], 'Intervening edit: ' . $record['slug']);
    $valid = iss_editorial_validate_document($record['after'], 'landing');
    $assert(!is_wp_error($valid) && $valid === $record['after'] && industriesalon_landing_has_opening($valid), 'Invalid complete document: ' . $record['slug']);
    $receipt['before'][$id] = array_intersect_key($record['before'], array_flip($keys));
    $receipt['private'][$id] = $private($id);
}
if ($mode === 'dry-run') { WP_CLI::success(count($input['pages']) . ' pages ready; exact before-images and all media hashes verified.'); return; }
$assert(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image receipt could not be verified.');
foreach ($input['pages'] as $record) {
    $assert(iss_editorial_save_document((int) $record['id'], 'landing', $record['after'], false, true), 'Save failed; receipt contains before-image: ' . $record['slug']);
}
$receipt['complete'] = true; update_option($key, $receipt, false);
$assert(get_option($key) === $receipt, 'Receipt write failed.');
$verify();
