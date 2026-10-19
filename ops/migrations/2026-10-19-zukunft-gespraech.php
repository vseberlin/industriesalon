<?php
/**
 * Local/native ZiG import. WP-CLI eval-file: dry-run|apply|verify PRIVATE_JSON.
 * Content travels privately; resolve source posts/entities by stable slugs.
 * Before-image/receipt: iss_backup_20261019_zukunft_gespraech, non-autoload.
 * Reapply verifies rather than overwriting edits. An incomplete receipt stops.
 * Rollback: review later edits, restore existing posts/meta from the receipt via
 * WP/editorial/relation APIs, reconcile those IDs, trash created posts, retain
 * newly named graph identities until later references have been reviewed.
 */
if (!defined('WP_CLI') || !WP_CLI) { throw new RuntimeException('WP-CLI required.'); }
$mode = $args[0] ?? 'dry-run';
$path = $args[1] ?? '';
if (!in_array($mode, ['dry-run', 'apply', 'verify'], true) || !is_readable($path)) { throw new RuntimeException('Use dry-run|apply|verify PRIVATE_JSON.'); }
$input = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$key = 'iss_backup_20261019_zukunft_gespraech';
$source = 'zukunft_gespraech_import';
$receipt = get_option($key);
$service = iss_graph_get_service();
$expect = static function (bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$hash = static fn($value): string => hash('sha256', wp_json_encode($value));
$snapshot = static fn(int $id): array => ['post' => get_post($id)->to_array(), 'meta' => get_post_meta($id)];
$verify = static function () use (&$receipt, $input, $expect, $hash, $service): void {
    $expect(is_array($receipt) && !empty($receipt['complete']), 'Missing/incomplete receipt; inspect before retrying.');
    $project_id = $receipt['project_id'];
    $expect(iss_editorial_document_is_enabled($project_id, 'projekt'), 'Project JSON editor is not enabled.');
    $expect($hash(iss_editorial_get_document($project_id, 'projekt', false)) === $receipt['document_hash'], 'Project document changed after import.');
    $expect(count(iss_content_model_get_project_event_ids($project_id)) === 6, 'Expected six shared event records.');
    $rows = iss_occurrences_query(['source_post_ids' => array_values($receipt['events']), 'time_mode' => 'all', 'limit' => -1]);
    $expect(count($rows) === 6, 'Expected six calendar occurrences, without duplicates.');
    $entity = iss_graph_get_entity_for_post($project_id);
    $expect(!empty($entity['id']), 'Project graph identity missing.');
    foreach ($input['events'] as $entry) {
        $id = $receipt['events'][$entry['key']];
        $expect((int) get_post_meta($id, 'iss_project_id', true) === $project_id, 'Project membership changed.');
        $expect(get_post_meta($id, 'iss_start_datetime', true) === $entry['start'], 'Event start mismatch.');
        $expect(get_post_meta($id, 'iss_end_datetime', true) === $entry['end'], 'Event end mismatch.');
        $event_entity = iss_graph_get_entity_for_post($id);
        $edges = $service->get_relations_for_entity((int) $event_entity['id'], 'project', ['source_system' => 'content_native']);
        $expect(count($edges) === 1 && (int) $edges[0]['entity_id'] === (int) $entity['id'] && $edges[0]['relation_type'] === 'part_of', 'Graph membership mismatch.');
        foreach ($rows as $row) {
            if ((int) $row['source_post_id'] === $id) { $expect($row['content_url'] === get_permalink($id) && $row['series_key'] === '', 'Event destination or recurrence semantics changed.'); }
        }
        if (!empty($entry['existing'])) {
            $before = $receipt['before'][$id];
            foreach (['_iss_content_json', '_iss_content_json_draft', '_iss_editorial_enabled_veranstaltung'] as $meta_key) {
                $expect(get_post_meta($id, $meta_key) === ($before['meta'][$meta_key] ?? []), 'Existing event document/draft changed.');
            }
            $expect(get_post_field('post_content', $id, 'raw') === $before['post']['post_content'], 'Existing event body changed.');
        }
    }
    foreach ($receipt['preserved'] as $id => $fingerprint) {
        $expect($hash([get_post($id)->to_array(), get_post_meta($id)]) === $fingerprint, 'Unrelated project changed.');
    }
    echo wp_json_encode(['verified' => true, 'project_id' => $project_id, 'url' => get_permalink($project_id), 'events' => $receipt['events'], 'new_entities' => $receipt['new_entities']], JSON_UNESCAPED_SLASHES) . "\n";
};
if ($mode === 'verify' || is_array($receipt)) { $verify(); return; }
$expect(current_user_can('manage_options'), 'Run as an authorized local administrator.');
$expect(!get_page_by_path($input['slug'], OBJECT, 'projekt'), 'Project already exists; no overwrite.');
$document = iss_editorial_validate_document($input['document'], 'projekt');
$expect(!is_wp_error($document), 'Invalid project document.');
$place = get_page_by_path($input['place_slug'], OBJECT, 'register_place');
$expect($place instanceof WP_Post && $place->post_status === 'publish', 'Missing existing venue.');
$events = [];
foreach ($input['events'] as $entry) {
    $post = get_page_by_path($entry['slug'], OBJECT, 'veranstaltung');
    if (!empty($entry['existing'])) {
        $expect($post instanceof WP_Post && $post->post_status === 'publish', 'Missing existing event: ' . $entry['slug']);
        $expect(!(int) get_post_meta($post->ID, 'iss_project_id', true), 'Event is already assigned to a project.');
        $expected = !empty($entry['correct_facts']) ? '2026-05-14 08:29:00' : $entry['start'];
        $expect(get_post_meta($post->ID, 'iss_start_datetime', true) === $expected, 'Source date changed; review before import.');
        $events[$entry['key']] = $post->ID;
    } else {
        $expect(!$post, 'New event slug already exists.');
        $expect(!is_wp_error(iss_editorial_validate_document($entry['document'], 'veranstaltung')), 'Invalid event document.');
    }
}
$people = [];
foreach ($input['existing_people'] as $slug) {
    $entity = $service->find_entity_by_slug('person', $slug);
    $expect(!empty($entity['id']), 'Missing reviewed person: ' . $slug);
    $people[$slug] = (int) $entity['id'];
}
$organizations = [];
foreach ($input['organizations'] as $slug) {
    $entity = $service->find_entity_by_slug('organization', $slug);
    $expect(!empty($entity['id']), 'Missing reviewed organization: ' . $slug);
    $organizations[] = (int) $entity['id'];
}
foreach ($input['events'] as $entry) {
    if (isset($people[$entry['guest_slug']])) { continue; }
    $resolution = iss_graph_resolve_entity(['entity_kind' => 'person', 'label' => $entry['guest']]);
    $expect($resolution['status'] === 'no_match', 'Guest now has candidates; review identity before import.');
}
if ($mode === 'dry-run') { echo "Ready: one JSON project, three reused and three new events, four new guest identities. Existing event documents/drafts stay intact.\n"; return; }
$receipt = ['complete' => false, 'input_sha256' => hash_file('sha256', $path), 'source_note' => $input['source_note'], 'before' => [], 'preserved' => [], 'events' => $events, 'created_posts' => [], 'new_entities' => []];
foreach ($events as $id) { $receipt['before'][$id] = $snapshot($id); }
foreach (get_posts(['post_type' => 'projekt', 'post_status' => ['publish', 'draft', 'private', 'pending'], 'numberposts' => -1]) as $p) {
    $receipt['preserved'][$p->ID] = $hash([$p->to_array(), get_post_meta($p->ID)]);
}
$expect(add_option($key, $receipt, '', false) && get_option($key) === $receipt, 'Could not save before-image.');
$save_receipt = static function () use (&$receipt, $key, $expect): void {
    update_option($key, $receipt, false);
    $expect(get_option($key) === $receipt, 'Could not verify receipt.');
};
$project_id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => $input['title'], 'post_name' => $input['slug'], 'post_excerpt' => $input['excerpt']], true);
$expect(!is_wp_error($project_id) && $project_id > 0, 'Project creation failed.');
$receipt['project_id'] = $project_id; $receipt['created_posts'][] = $project_id; $save_receipt();
$expect(iss_editorial_save_document($project_id, 'projekt', $document, false, true), 'Project document save failed.');
update_post_meta($project_id, 'iss_programme_enabled', 0);
update_post_meta($project_id, '_iss_source_url', $input['source_url']);
iss_relations_save_post_relations($project_id, [['place_id' => $place->ID, 'role' => 'venue', 'weight' => 100]]);
$expect(!is_wp_error(wp_update_post(['ID' => $project_id, 'post_status' => 'publish'], true)), 'Project publish failed.');
foreach ($input['events'] as $entry) {
    $slug = $entry['guest_slug'];
    if (!isset($people[$slug])) {
        $entity = iss_graph_resolve_or_create_named_entity('person', $entry['guest'], ['source_system' => $source, 'source_id' => $slug, 'canonical_slug' => $slug, 'status' => 'publish', 'is_public' => true], ['allow_public_create' => true]);
        $expect(!empty($entity['id']), 'Guest identity creation failed.');
        $people[$slug] = (int) $entity['id']; $receipt['new_entities'][$slug] = $people[$slug]; $save_receipt();
        $service->replace_entity_evidence_refs_for_source($people[$slug], $source, [['source_url' => $input['source_url'], 'label' => $entry['guest'], 'note' => 'Guest listed for ' . $entry['start'], 'status' => 'accepted', 'confidence' => 100]], $entry['key']);
    }
    $id = $events[$entry['key']] ?? 0;
    if (!$id) {
        $id = wp_insert_post(['post_type' => 'veranstaltung', 'post_status' => 'draft', 'post_title' => $entry['title'], 'post_name' => $entry['slug'], 'post_excerpt' => $entry['excerpt']], true);
        $expect(!is_wp_error($id) && $id > 0, 'Event creation failed.');
        $receipt['created_posts'][] = $id; $receipt['events'][$entry['key']] = $id; $save_receipt();
        update_post_meta($id, '_iss_entity_key', 'event.general');
        $expect(iss_editorial_save_document($id, 'veranstaltung', $entry['document'], false, true), 'Event document save failed.');
        wp_set_object_terms($id, 'gespraech', 'veranstaltung_art');
        update_post_meta($id, '_iss_source_url', $input['source_url']);
    }
    update_post_meta($id, 'iss_project_id', $project_id);
    if (empty($entry['existing']) || !empty($entry['correct_facts'])) {
        update_post_meta($id, 'iss_start_datetime', $entry['start']);
        update_post_meta($id, 'iss_end_datetime', $entry['end']);
        update_post_meta($id, 'iss_programme_enabled', 1);
        update_post_meta($id, 'iss_location', 'Industriesalon Schöneweide');
        // Reviewed source names Industriesalon, not the old industrial-place matches.
        iss_relations_save_post_relations($id, [['place_id' => $place->ID, 'role' => 'venue', 'weight' => 100]]);
    }
    update_post_meta($id, 'iss_primary_place_id', $place->ID);
    $update = ['ID' => $id, 'post_status' => 'publish'];
    if (!empty($entry['correct_facts'])) { $update['post_title'] = $entry['title']; }
    $expect(!is_wp_error(wp_update_post($update, true)), 'Event publish/update failed.');
    iss_content_model_sync_veranstaltung_normalized_facts($id);
    iss_occurrences_sync_source($id);
    iss_graph_reconcile_post($id);
    $entity = iss_graph_get_entity_for_post($id);
    $year = (int) substr($entry['start'], 0, 4);
    $relations = ['person' => []];
    foreach ([$people[$slug] => 'Gast', $people['klaus-burmeister'] => 'Moderation'] as $person_id => $label) {
        $relations['person'][] = ['to_entity_id' => $person_id, 'relation_type' => 'participant', 'relation_label' => $label, 'valid_from_year' => $year, 'valid_to_year' => $year, 'relation_status' => 'accepted', 'is_public' => true];
    }
    $service->replace_entity_projection((int) $entity['id'], $source, ['source_ref' => $entry['key'], 'relations' => $relations, 'evidence_refs' => [['source_url' => $input['source_url'], 'label' => $entry['subject'], 'note' => $input['source_note'], 'status' => 'accepted', 'confidence' => 100]]]);
}
iss_graph_reconcile_post($project_id);
$entity = iss_graph_get_entity_for_post($project_id);
$relations = ['person' => [], 'organization' => []];
foreach ($people as $slug => $id) {
    $relations['person'][] = ['to_entity_id' => $id, 'relation_type' => 'participant', 'relation_label' => in_array($slug, ['klaus-burmeister', 'dirk-sarnoch'], true) ? 'Idee / Organisation' : 'Gast der Reihe', 'relation_status' => 'accepted', 'is_public' => true];
}
foreach ($organizations as $id) { $relations['organization'][] = ['to_entity_id' => $id, 'relation_type' => 'organizer', 'relation_label' => 'Veranstalter', 'relation_status' => 'accepted', 'is_public' => true]; }
$service->replace_entity_projection((int) $entity['id'], $source, ['source_ref' => $input['slug'], 'relations' => $relations, 'evidence_refs' => [['source_url' => $input['source_url'], 'label' => $input['title'], 'note' => $input['source_note'], 'status' => 'accepted', 'confidence' => 100]]]);
$receipt['document_hash'] = $hash(iss_editorial_get_document($project_id, 'projekt', false));
$receipt['complete'] = true; $save_receipt();
$verify();
