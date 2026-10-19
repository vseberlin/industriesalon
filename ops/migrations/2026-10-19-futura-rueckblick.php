<?php
/**
 * Import the reviewed Futura 2025 retrospective through editorial/graph owners.
 * wp eval-file FILE dry-run|apply|verify|resume INPUT_JSON --use-include
 * INPUT_JSON is private content, transferred separately; no uploads are required.
 * Resolve target posts/entities by stable identities, never cross-site numeric IDs.
 * Before-image/receipt: iss_backup_20261019_futura_rueckblick (non-autoload option).
 * Reapply verifies, never overwrites later edits. Partial failures retain the receipt.
 * Rollback: after checking receipt fingerprints, restore only the project's saved
 * document through iss_editorial_save_document and trash the new report. Retain
 * graph identities/evidence; removing them requires checking later incoming uses.
 */
if (!defined('ABSPATH') || !defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('Run through WP-CLI so the owner permits graph reconciliation.'); }
$mode = $args[0] ?? 'dry-run';
$input_path = $args[1] ?? '';
if (!in_array($mode, ['dry-run', 'apply', 'verify', 'resume'], true) || !is_readable($input_path)) {
    throw new RuntimeException('Use dry-run|apply|verify|resume and a readable private JSON input.');
}
$input = json_decode(file_get_contents($input_path), true, 512, JSON_THROW_ON_ERROR);
$key = 'iss_backup_20261019_futura_rueckblick';
$source = 'futura_2025_review';
$service = iss_graph_get_service();
$receipt = get_option($key);
$check = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$post_for = static function (string $slug, string $type) use ($check): WP_Post {
    $post = get_page_by_path($slug, OBJECT, $type);
    $check($post instanceof WP_Post && $post->post_status === 'publish', 'Missing published source: ' . $slug);
    return $post;
};
$project = $post_for($input['project_slug'], 'projekt');
$kiezkarte = $post_for($input['kiezkarte_slug'], 'projekt');
$hash = static fn($value): string => hash('sha256', wp_json_encode($value));
$core = static function (int $id): array {
    return array_intersect_key(get_post($id)->to_array(), array_flip(['post_title', 'post_excerpt', 'post_content', 'post_status', 'post_name']));
};
$private_state = static function (int $id) use ($hash): string {
    $meta = get_post_meta($id);
    unset($meta['_iss_editorial_projekt'], $meta['_edit_lock']);
    $revisions = wp_get_post_revisions($id, ['posts_per_page' => -1]);
    $autosaves = [];
    foreach ($revisions as $revision) {
        if (wp_is_post_autosave($revision->ID)) { $autosaves[$revision->ID] = [$revision->to_array(), get_post_meta($revision->ID)]; }
    }
    return $hash([$meta, $autosaves]);
};
$verify = static function () use (&$receipt, $check, $hash, $core, $private_state, $project, $service, $source): void {
    $check(is_array($receipt) && !empty($receipt['complete']), 'Missing or incomplete receipt; inspect before retrying.');
    $id = $receipt['report_id'];
    $check($core($id) === $receipt['report_core'], 'Report changed after import.');
    $check($hash(iss_editorial_get_document($id, 'rueckblick', false)) === $receipt['report_hash'], 'Report document differs.');
    $check($hash(iss_editorial_get_document($project->ID, 'projekt', false)) === $receipt['project_after_hash'], 'Project changed after import.');
    $check($private_state($project->ID) === $receipt['project_private_hash'], 'Project private draft or metadata changed.');
    foreach ($receipt['preserved'] as $post_id => $fingerprint) {
        $check($hash([get_post($post_id)->to_array(), get_post_meta($post_id)]) === $fingerprint, 'Protected source changed: ' . $post_id);
    }
    $check(iss_editorial_document_is_enabled($id, 'rueckblick'), 'Report document not enabled.');
    $check(!is_wp_error(iss_editorial_validate_document(iss_editorial_get_document($id, 'rueckblick', false), 'rueckblick')), 'Invalid report.');
    $entity = iss_graph_get_entity_for_post($id);
    $check(!empty($entity['is_public']), 'Report graph entity not public.');
    $sources = array_map(static fn($p) => (int) $p->ID, iss_content_report_connections($id, true));
    sort($sources);
    $expected = $receipt['source_ids']; sort($expected);
    $check($sources === $expected, 'Report source connections differ.');
    foreach ($receipt['actors'] as $kind => $ids) {
        $rows = $service->get_relations_for_entity((int) $entity['id'], $kind, ['source_system' => $source]);
        $actual = array_map(static fn($r) => (int) $r['entity_id'], $rows); sort($actual); sort($ids);
        $check($actual === $ids, 'Actor relationships differ: ' . $kind);
        foreach ($rows as $row) { $check($row['relation_status'] === 'accepted' && (int) $row['valid_from_year'] === 2025 && (int) $row['valid_to_year'] === 2025, 'Invalid relationship scope.'); }
    }
    $places = iss_relations_get_stored_post_relations($id);
    $check(count($places) === 3, 'Expected three existing venue relations.');
    $check(count($service->get_relations_for_entity((int) $entity['id'], 'place')) === 3, 'Place graph projection differs.');
    echo wp_json_encode(['verified' => true, 'report_id' => $id, 'url' => get_permalink($id), 'actors' => $receipt['actors'], 'places' => count($places)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
};
$resume = $mode === 'resume';
if ($mode === 'verify' || (is_array($receipt) && !$resume)) { $verify(); return; }
$prior_attempt = null;
if ($resume) {
    $check(is_array($receipt) && empty($receipt['complete']) && empty($receipt['report_id']) && empty($receipt['new_entities']), 'Resume is limited to a failed creation with no surviving report or new actors.');
    $check($receipt['input_sha256'] === hash_file('sha256', $input_path), 'Resume input changed.');
    $check($hash($receipt['project_before']) === $hash(iss_editorial_get_document($project->ID, 'projekt', false)) && $private_state($project->ID) === $receipt['project_private_hash'], 'Source changed since failed creation.');
    foreach ($receipt['preserved'] as $post_id => $fingerprint) { $check($hash([get_post($post_id)->to_array(), get_post_meta($post_id)]) === $fingerprint, 'Protected source changed.'); }
    $prior_attempt = $receipt;
}
$check(!get_page_by_path($input['slug'], OBJECT, 'rueckblick'), 'Report slug already exists.');
$check(!iss_content_report_connections($project->ID), 'Project already has a report or draft; inspect before importing.');
$check(current_user_can('edit_post', $project->ID) && current_user_can(get_post_type_object('rueckblick')->cap->publish_posts), 'Run as an authorized editor with Rückblick publishing rights.');
$document_json = str_replace(['{{project_url}}', '{{kiezkarte_url}}'], [get_permalink($project), get_permalink($kiezkarte)], wp_json_encode($input['document'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
$document = iss_editorial_validate_document(json_decode($document_json, true), 'rueckblick');
$check(!is_wp_error($document), is_wp_error($document) ? $document->get_error_message() : 'Invalid document.');
$actors = [];
foreach ($input['existing_entities'] as $entry) {
    $entity = $service->find_entity_by_slug($entry['kind'], $entry['slug']);
    $check($entity && $entity['display_title'] === $entry['name'], 'Existing actor identity differs: ' . $entry['name']);
    $actors[] = [$entry, $entity];
}
foreach ($input['new_entities'] as $entry) {
    $resolution = iss_graph_resolve_entity(['entity_kind' => $entry['kind'], 'label' => $entry['name']]);
    $check($resolution['status'] === 'no_match', 'Actor now has candidates; review before creating: ' . $entry['name']);
}
$places = [];
foreach ($input['places'] as $entry) {
    $place = $post_for($entry['slug'], iss_relations_get_place_post_type());
    $places[] = ['place_id' => $place->ID, 'role' => 'venue', 'weight' => 100, 'label' => $entry['label']];
}
$before = iss_editorial_get_document($project->ID, 'projekt', false);
$project_document = $before;
$found = 0;
foreach ($project_document['sections'] as &$section) {
    if (($section['anchor'] ?? '') !== 'rueckblick') { continue; }
    $check(($section['items'][0]['label'] ?? '') === 'Auftakt im Westrich', 'Project retrospective differs; no overwrite.');
    $section['items'][0]['label'] = 'Auftakt im Industriesalon';
    $section['links'] = [['label' => 'Rückblick 2025 lesen', 'url' => home_url('/rueckblick/' . $input['slug'] . '/')]];
    $found++;
}
unset($section);
$check($found === 1, 'Expected one project retrospective section.');
$project_document = iss_editorial_validate_document($project_document, 'projekt');
$check(!is_wp_error($project_document), 'Invalid project patch.');
if ($mode === 'dry-run') {
    echo "Ready: one published Rückblick, two source connections, three new named graph identities, five actor and three existing venue relationships; one project location/link correction. No media or private draft changes.\n";
    return;
}
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $input_path), 'project_id' => $project->ID, 'project_before' => $before, 'project_core' => $core($project->ID), 'project_private_hash' => $private_state($project->ID), 'source_ids' => [$project->ID, $kiezkarte->ID], 'preserved' => [], 'new_entities' => [], 'actors' => []];
foreach (get_posts(['post_type' => 'projekt', 'post_status' => ['publish', 'draft', 'private', 'pending'], 'numberposts' => -1]) as $post) {
    if ($post->ID !== $project->ID) { $receipt['preserved'][$post->ID] = $hash([$post->to_array(), get_post_meta($post->ID)]); }
}
if ($resume) {
    $receipt['prior_attempt'] = $prior_attempt;
    update_option($key, $receipt, false);
    $check(get_option($key) === $receipt, 'Resumed before-image not saved.');
} else {
    $check(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image not saved.');
}
$save_receipt = static function () use (&$receipt, $key, $check): void {
    update_option($key, $receipt, false);
    $check(get_option($key) === $receipt, 'Receipt could not be verified.');
};
$id = iss_content_create_report($project->ID);
$check(!is_wp_error($id), is_wp_error($id) ? $id->get_error_message() : 'Report creation failed.');
$receipt['report_id'] = (int) $id; $save_receipt();
$updated = wp_update_post(['ID' => $id, 'post_title' => $input['title'], 'post_name' => $input['slug'], 'post_excerpt' => $input['excerpt']], true);
$check(!is_wp_error($updated), 'Report metadata save failed.');
$check(iss_editorial_save_document($id, 'rueckblick', $document, false, true), 'Report save failed.');
$check(iss_content_report_set_sources($id, $receipt['source_ids']), 'Source connection failed.');
foreach ($input['new_entities'] as $entry) {
    $entity = iss_graph_resolve_or_create_named_entity($entry['kind'], $entry['name'], ['source_system' => $source, 'source_id' => $entry['key'], 'canonical_slug' => $entry['key'], 'summary' => $entry['summary'], 'status' => 'publish', 'is_public' => true], ['allow_public_create' => true]);
    $check($entity && !empty($entity['id']), 'Named identity creation failed.');
    $receipt['new_entities'][$entry['key']] = (int) $entity['id']; $save_receipt();
    $evidence = [['source_url' => $entry['url'], 'label' => $entry['label'], 'note' => $entry['note'], 'confidence' => 100, 'status' => 'accepted']];
    if (!empty($entry['extra_url'])) { $evidence[] = ['source_url' => $entry['extra_url'], 'label' => 'Identitätsabgleich', 'note' => $entry['note'], 'confidence' => 100, 'status' => 'accepted']; }
    $service->replace_entity_evidence_refs_for_source((int) $entity['id'], $source, $evidence, 'futura-2025');
    $actors[] = [$entry, $entity];
}
$report_entity = iss_graph_get_or_create_entity_for_post($id);
$rows = [];
foreach ($actors as [$entry, $entity]) {
    $rows[$entry['kind']][] = ['to_entity_id' => (int) $entity['id'], 'relation_type' => $entry['type'], 'relation_label' => $entry['label'], 'note' => $entry['note'], 'source_field' => $entry['key'], 'valid_from_year' => 2025, 'valid_to_year' => 2025, 'confidence' => 100, 'relation_status' => 'accepted', 'is_public' => true];
    $receipt['actors'][$entry['kind']][] = (int) $entity['id'];
}
$service->replace_entity_projection((int) $report_entity['id'], $source, ['source_ref' => 'futura-2025', 'relations' => $rows, 'evidence_refs' => array_map(static fn($s) => ['source_url' => $s['url'], 'label' => $s['label'], 'note' => 'Redaktioneller Quellenabgleich für den Rückblick 2025.', 'confidence' => 100, 'status' => 'accepted'], $input['sources'])]);
iss_relations_save_post_relations($id, $places);
$check(!is_wp_error(wp_update_post(['ID' => $id, 'post_status' => 'publish'], true)), 'Report publish failed.');
iss_relations_sync_post_graph($id);
$check(iss_editorial_save_document($project->ID, 'projekt', $project_document, false, true), 'Project patch failed.');
$receipt['report_core'] = $core($id);
$receipt['report_hash'] = $hash(iss_editorial_get_document($id, 'rueckblick', false));
$receipt['project_after_hash'] = $hash(iss_editorial_get_document($project->ID, 'projekt', false));
$receipt['review'] = $input['review'];
$receipt['complete'] = true;
$save_receipt();
$verify();
