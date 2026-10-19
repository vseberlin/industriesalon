<?php
/**
 * Import five reviewed ZiG essays through existing editorial and graph owners.
 * wp eval-file FILE dry-run|apply|verify PRIVATE_INPUT_JSON --use-include
 * Transfer content.json and its source.docx privately, never through GitHub.
 * Before-image: iss_backup_20261019_zig_rueckblicke, non-autoload option.
 * Apply creates reports only; source posts, dates and private drafts are preserved.
 * Reapply verifies the receipt, never overwrites later edits or partial imports.
 * Rollback: verify report fingerprints, then trash only receipt.report_ids via WP.
 * Keep source posts/identities/Sets; detach new report contexts only if deleting.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('Run through WP-CLI.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(in_array($mode, ['dry-run', 'apply', 'verify'], true) && is_readable($path), 'Provide dry-run|apply|verify and private input JSON.');
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$expect(count($input['reports']) === 5, 'Expected five reviewed reports.');
$expect(is_readable(dirname($path) . '/source.docx') && hash_file('sha256', dirname($path) . '/source.docx') === $input['source_sha256'], 'Source DOCX fingerprint differs.');
$key = 'iss_backup_20261019_zig_rueckblicke';
$source = 'zig_rueckblicke_review';
$receipt = get_option($key);
$service = iss_graph_get_service();
$hash = static fn($value): string => hash('sha256', wp_json_encode($value));
$post_for = static function (string $slug, string $type) use ($expect): WP_Post {
    $post = get_page_by_path($slug, OBJECT, $type);
    $expect($post instanceof WP_Post && $post->post_status === 'publish', 'Missing published source: ' . $slug);
    return $post;
};
$snapshot = static function (int $id): array {
    $meta = get_post_meta($id);
    unset($meta['_edit_lock'], $meta['_edit_last']);
    $revisions = [];
    foreach (wp_get_post_revisions($id, ['posts_per_page' => -1]) as $r) {
        $revisions[$r->ID] = [$r->to_array(), get_post_meta($r->ID)];
    }
    return ['post' => get_post($id)->to_array(), 'meta' => $meta, 'revisions' => $revisions];
};
$project = $post_for($input['project_slug'], 'projekt');
$place = $post_for($input['place_slug'], 'register_place');
$events = [];
foreach ($input['reports'] as $entry) {
    $post = $post_for($entry['event_slug'], 'veranstaltung');
    $expect(get_post_meta($post->ID, 'iss_start_datetime', true) === $entry['event_start'], 'Source event date differs.');
    $expect((int) get_post_meta($post->ID, 'iss_project_id', true) === $project->ID, 'Source event belongs to another project.');
    $events[$entry['slug']] = $post->ID;
}
$verify = static function () use (&$receipt, $expect, $hash, $snapshot, $project, $place, $events, $service, $source): void {
    $expect(is_array($receipt) && !empty($receipt['complete']), 'Missing or partial receipt; inspect before retrying.');
    $ids = array_values($receipt['report_ids']);
    $expect(count($ids) === 5 && count(array_unique($ids)) === 5, 'Expected five distinct reports.');
    foreach ($receipt['before'] as $id => $before) {
        $expect($hash($snapshot((int) $id)) === $hash($before), 'Existing source or private draft changed: ' . $id);
    }
    foreach ($receipt['report_ids'] as $slug => $id) {
        $expect(get_post_status($id) === 'publish' && $hash($snapshot($id)) === $receipt['after_hashes'][$slug], 'Report changed: ' . $slug);
        $doc = iss_editorial_get_document($id, 'rueckblick', false);
        $expect(iss_editorial_document_is_enabled($id, 'rueckblick') && !is_wp_error(iss_editorial_validate_document($doc, 'rueckblick')), 'Report JSON invalid or disabled.');
        $expected_sources = [$events[$slug], $project->ID]; sort($expected_sources);
        $actual = array_map(static fn($p): int => $p->ID, iss_content_report_connections($id, true)); sort($actual);
        $expect($actual === $expected_sources, 'Report must link to exactly its event and the project.');
        $expect(in_array($id, array_map(static fn($p): int => $p->ID, iss_content_report_connections($events[$slug], true)), true), 'Event backlink missing.');
        $entity = iss_graph_get_entity_for_post($id);
        $expect(!empty($entity['is_public']), 'Report entity must be public.');
        foreach ($receipt['actors'][$slug] as $family => $expected) {
            $rows = $service->get_relations_for_entity((int) $entity['id'], $family, ['source_system' => $source]);
            $actual = array_map(static fn($r): int => (int) $r['entity_id'], $rows); sort($actual); sort($expected);
            $expect($actual === $expected, 'Actor projection differs: ' . $slug . '/' . $family);
        }
        $venues = iss_relations_get_stored_post_relations($id);
        $expect(count($venues) === 1 && (int) $venues[0]['place_id'] === $place->ID, 'Venue source differs.');
        $expect(count($service->get_relations_for_entity((int) $entity['id'], 'place')) === 1, 'Venue graph projection differs.');
        $rows = $service->get_relations_for_entity((int) $entity['id'], 'reports_on', ['source_system' => 'iss_content_rueckblick', 'public_only' => true]);
        $expect(count($rows) === 2, 'Both report source relations must be public.');
    }
    $project_reports = array_map(static fn($p): int => $p->ID, iss_content_report_connections($project->ID, true));
    $expect(!array_diff($ids, $project_reports), 'Project is missing report backlinks.');
    echo wp_json_encode(['verified' => true, 'report_ids' => $receipt['report_ids'], 'urls' => array_map('get_permalink', $ids), 'sources_preserved' => count($receipt['before'])], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$expect(current_user_can(get_post_type_object('rueckblick')->cap->publish_posts), 'Run as an editor allowed to publish reports.');
$actors = ['person' => [], 'organization' => []];
foreach (array_merge([$input['moderator']], array_map(static fn($r): array => ['slug' => $r['guest_slug'], 'name' => $r['guest']], $input['reports'])) as $person) {
    $entity = $service->find_entity_by_slug('person', $person['slug']);
    $expect($entity && $entity['display_title'] === $person['name'], 'Existing person identity differs: ' . $person['name']);
    $actors['person'][$person['slug']] = (int) $entity['id'];
}
foreach ($input['organizations'] as $slug) {
    $entity = $service->find_entity_by_slug('organization', $slug);
    $expect($entity !== null, 'Missing existing organisation: ' . $slug);
    $actors['organization'][$slug] = (int) $entity['id'];
}
$documents = [];
foreach ($input['reports'] as $entry) {
    $expect(!get_page_by_path($entry['slug'], OBJECT, 'rueckblick'), 'Report slug already exists.');
    $expect(current_user_can('edit_post', $events[$entry['slug']]) && !iss_content_report_connections($events[$entry['slug']]), 'Existing report/draft or insufficient source rights.');
    $document = iss_editorial_validate_document($entry['document'], 'rueckblick');
    $expect(!is_wp_error($document), is_wp_error($document) ? $document->get_error_message() : 'Invalid document.');
    foreach ($entry['essay_paragraphs'] as $paragraph) {
        $expect(str_contains(html_entity_decode(wp_strip_all_tags($document['sections'][1]['body']), ENT_QUOTES, 'UTF-8'), $paragraph), 'Essay text was changed during normalization.');
    }
    $documents[$entry['slug']] = $document;
}
if ($mode === 'dry-run') { echo "Ready: five reports, ten source links, ten person links, ten organiser links, five venues. No source document/date or media changes.\n"; return; }
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'source_sha256' => $input['source_sha256'], 'before' => [], 'report_ids' => [], 'actors' => [], 'after_hashes' => [], 'review' => $input['review']];
$source_ids = array_merge([$project->ID], iss_content_model_get_project_event_ids($project->ID));
foreach ($source_ids as $id) { $receipt['before'][$id] = $snapshot($id); }
$expect(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Before-image could not be verified.');
$save = static function () use (&$receipt, $key, $expect): void {
    update_option($key, $receipt, false);
    $expect(get_option($key) === $receipt, 'Receipt could not be verified.');
};
foreach ($input['reports'] as $entry) {
    $slug = $entry['slug'];
    $id = iss_content_create_report($events[$slug]);
    $expect(!is_wp_error($id) && $id > 0, is_wp_error($id) ? $id->get_error_message() : 'Report creation failed.');
    $receipt['report_ids'][$slug] = $id; $save();
    $expect(!is_wp_error(wp_update_post(['ID' => $id, 'post_title' => $entry['title'], 'post_name' => $slug, 'post_excerpt' => $entry['excerpt']], true)), 'Report metadata failed.');
    $expect(iss_editorial_save_document($id, 'rueckblick', $documents[$slug], false, true), 'Report JSON save failed.');
    $expect(iss_content_report_set_sources($id, [$events[$slug], $project->ID]), 'Source connections failed.');
    iss_relations_save_post_relations($id, [['place_id' => $place->ID, 'role' => 'venue', 'weight' => 100]]);
    $entity = iss_graph_get_or_create_entity_for_post($id);
    $year = (int) substr($entry['event_start'], 0, 4);
    $rows = ['person' => [], 'organization' => []];
    foreach ([$entry['guest_slug'] => 'Gast', $input['moderator']['slug'] => 'Moderation'] as $actor_slug => $label) {
        $rows['person'][] = ['to_entity_id' => $actors['person'][$actor_slug], 'relation_type' => 'participant', 'relation_label' => $label, 'valid_from_year' => $year, 'valid_to_year' => $year, 'relation_status' => 'accepted', 'is_public' => true];
    }
    foreach ($actors['organization'] as $actor_id) {
        $rows['organization'][] = ['to_entity_id' => $actor_id, 'relation_type' => 'organizer', 'relation_label' => 'Veranstalter', 'valid_from_year' => $year, 'valid_to_year' => $year, 'relation_status' => 'accepted', 'is_public' => true];
    }
    $service->replace_entity_projection((int) $entity['id'], $source, ['source_ref' => $slug, 'relations' => $rows, 'evidence_refs' => [['label' => $input['source_filename'], 'note' => 'Stand ' . $input['source_date'] . '; Ausgabe ' . $entry['title'] . '; SHA-256 ' . $input['source_sha256'], 'status' => 'accepted', 'confidence' => 100]]]);
    $receipt['actors'][$slug] = array_map(static fn($r): array => array_column($r, 'to_entity_id'), $rows);
    $save();
}
foreach ($receipt['report_ids'] as $slug => $id) {
    $expect(!is_wp_error(wp_update_post(['ID' => $id, 'post_status' => 'publish'], true)), 'Report publish failed.');
    iss_relations_sync_post_graph($id);
    iss_graph_reconcile_post($id);
    $receipt['after_hashes'][$slug] = $hash($snapshot($id));
    $save();
}
iss_graph_flush_request_projections();
$receipt['complete'] = true; $save();
$verify();
