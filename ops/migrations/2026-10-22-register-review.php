<?php
/**
 * Reviewed supplier omissions in enabled Place documents; private JSON inputs.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_MANIFEST --use-include --user=admin
 *
 * Requires a verified target DB backup. No schema or uploads artifact.
 * Before-images live in the non-autoloaded iss_backup_20261022_register_review
 * option. An incomplete receipt blocks replay. Rollback after checking for newer
 * edits: restore only receipt documents through iss_editorial_save_document(),
 * restore its exact metadata keys with WordPress APIs, refresh current states
 * and register caches. Never restore accounts or a complete historical DB.
 */
if (!defined('WP_CLI') || !WP_CLI || !current_user_can('manage_options')) {
    throw new RuntimeException('Administrator CLI required.');
}

require_once __DIR__ . '/place-review-preservation.php';
// phpcs:disable WordPress.Security.EscapeOutput -- CLI JSON and controlled diagnostics only.

$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
iss_register_review_require(in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Mode and private manifest required.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
iss_register_review_require(($input['batch'] ?? '') === 'register-review-20261022' && $input['home_url'] === home_url(), 'Wrong batch or target site.');
iss_register_review_require($input['source']['source_namespace'] === 'industriesalon-schoeneweide/schoeneweide-register'
    && $input['source']['source_revision'] === '8574160c4ae79a6101dad59d21c9817ab29d0814' && count($input['records']) === 36, 'Unreviewed source or batch size.');
$key = 'iss_backup_20261022_register_review';
$receipt = get_option($key);
$verify = static function () use (&$receipt, $path): void {
    iss_register_review_require(is_array($receipt) && !empty($receipt['complete']) && $receipt['input_sha256'] === hash_file('sha256', $path), 'Missing, partial or different receipt; inspect before retrying.');
    foreach ($receipt['records'] as $row) {
        iss_register_review_require(iss_editorial_get_document($row['id'], 'place', false) === $row['after_document'], 'Saved document differs: ' . $row['slug']);
        foreach ($row['after_meta'] as $name => $value) {
            iss_register_review_require(get_post_meta($row['id'], $name, true) === $value, 'Saved metadata differs: ' . $row['slug']);
        }
        $expected = [];
        foreach ($row['after_document']['sections'] as $section) {
            if ($section['type'] === 'gegenwart') { $expected[] = trim(wp_strip_all_tags($section['body'])); }
        }
        $entity = iss_register_get_place_entity_by_post_id($row['id']);
        iss_register_review_require(($entity['current'] ?? '') === implode("\n\n", array_filter($expected)), 'Atlas read model differs: ' . $row['slug']);
    }
    $protected = iss_register_review_protected($receipt['targets']);
    foreach ($receipt['protected'] as $name => $hash) {
        iss_register_review_require($protected[$name] === $hash, 'Protected data differs: ' . $name);
    }
    $revisions = iss_register_review_revisions();
    foreach ($receipt['revisions'] as $id => $hash) {
        iss_register_review_require(($revisions[$id] ?? '') === $hash, 'Existing revision or private autosave changed.');
    }
    echo wp_json_encode(['verified' => count($receipt['records']), 'protected_checks' => array_keys($protected), 'existing_revisions_preserved' => count($receipt['revisions']), 'idempotent' => true]) . "\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }

$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'source' => $input['source'], 'targets' => [], 'records' => []];
foreach ($input['records'] as $row) {
    $identity = iss_register_resolve_supplier_place($input['source']['source_namespace'], $row['source_id']);
    iss_register_review_require(!is_wp_error($identity), 'Supplier identity unresolved.');
    $post = get_post($identity['post_id']);
    iss_register_review_require($post && $post->post_name === $row['slug'] && $post->post_title === $row['title'] && $post->post_status === 'publish'
        && iss_editorial_document_is_enabled($post->ID, 'place') && !isset($receipt['targets'][$post->ID]), 'Place identity, status or mode differs.');
    $row['id'] = $post->ID;
    iss_register_review_require(iss_editorial_get_document($post->ID, 'place', false) === $row['before_document'], 'Document changed since review: ' . $row['slug']);
    $validated = iss_editorial_validate_document($row['after_document'], 'place');
    iss_register_review_require(!is_wp_error($validated) && $validated === $row['after_document'], 'Invalid or noncanonical document: ' . $row['slug']);
    $protected_document = $row['after_document'];
    foreach ($protected_document['sections'] as $index => &$section) {
        $before = $row['before_document']['sections'][$index] ?? [];
        if (($section['type'] ?? '') === 'gegenwart' || ($row['source_id'] === '45' && ($section['type'] ?? '') === 'intro')) {
            $section['body'] = $before['body'];
            if ($section['type'] === 'gegenwart') { $section['links'] = $before['links']; }
        }
    }
    unset($section);
    iss_register_review_require($protected_document === $row['before_document'], 'Historical structure or media changed.');
    iss_register_review_require(array_keys($row['before_meta']) === array_keys($row['after_meta']), 'Metadata key mismatch.');
    foreach ($row['after_meta'] as $name => $value) {
        iss_register_review_require(in_array($name, ['current_status', 'jobs', 'source_summary'], true)
            && get_post_meta($post->ID, $name, true) === $row['before_meta'][$name]
            && iss_register_sanitize_meta_value($name, $value) === $value, 'Metadata precondition differs.');
    }
    $public_text = iss_register_validate_public_import_text($post->ID, [$row['after_document'], $row['after_meta']]);
    iss_register_review_require(!is_wp_error($public_text), 'Contact-bearing public text requires review: ' . $row['slug']);
    $row['meta_before'] = get_post_meta($post->ID);
    $receipt['targets'][$post->ID] = array_merge(array_keys($row['after_meta']), [iss_editorial_get_document_meta_key('place'), ISS_REGISTER_EPOCH_SNAPSHOT_META_KEY, ISS_REGISTER_EPOCH_SNAPSHOTS_META_KEY]);
    $receipt['records'][] = $row;
}
$receipt['protected'] = iss_register_review_protected($receipt['targets']);
$receipt['revisions'] = iss_register_review_revisions();
if ($mode === 'dry-run') {
    echo wp_json_encode(['ready' => count($receipt['records']), 'home_url' => home_url(), 'protected_checks' => array_keys($receipt['protected'])]) . "\n";
    return;
}
iss_register_review_require(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image not saved.');
add_filter('wp_register_place_revisions_to_keep', static fn(): int => -1, PHP_INT_MAX);
foreach ($receipt['records'] as $row) {
    $saved = iss_register_get_epoch_service()->with_place_transaction($row['id'], static function () use ($row): bool {
        iss_register_review_require(iss_editorial_get_document($row['id'], 'place', false) === $row['before_document'], 'Concurrent document edit.');
        foreach ($row['after_meta'] as $name => $value) {
            iss_register_review_require(get_post_meta($row['id'], $name, true) === $row['before_meta'][$name], 'Concurrent metadata edit.');
            update_post_meta($row['id'], $name, wp_slash($value));
            iss_register_review_require(get_post_meta($row['id'], $name, true) === $value, 'Metadata write failed.');
        }
        return iss_editorial_save_document($row['id'], 'place', $row['after_document'], false);
    });
    iss_register_review_require($saved === true, 'Place save failed; partial receipt retained: ' . $row['slug']);
}
iss_register_clear_places_cache();
$receipt['complete'] = true;
update_option($key, $receipt, false);
$verify();
