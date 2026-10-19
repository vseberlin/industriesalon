<?php
/** Shared event membership, graph replacement and automatic project JSON section. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($message); }
};
$created = [];
$old_post = $GLOBALS['post'] ?? null;
$now = '2050-06-15 12:00:00';
$clock = static function () use (&$now): string { return $now; };
add_filter('iss_occurrences_query_now', $clock);
try {
    $create = static function (string $type, string $title, array $meta = []) use (&$created): int {
        $id = wp_insert_post(['post_type' => $type, 'post_status' => 'publish', 'post_title' => 'ZiG fixture ' . $title, 'meta_input' => $meta], true);
        if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
        $created[] = $id;
        return $id;
    };
    $project = $create('projekt', 'A');
    $other = $create('projekt', 'B');
    $event = static function (string $title, string $day) use ($create, $project): int {
        return $create('veranstaltung', $title, ['_iss_entity_key' => 'event.general', 'iss_project_id' => $project, 'iss_start_datetime' => $day . ' 18:00:00', 'iss_programme_enabled' => 1]);
    };
    $past = $event('Past', '2050-06-14');
    $first = $event('First', '2050-06-16');
    $second = $event('Second', '2050-06-17');
    $hidden = $event('Hidden', '2050-06-18');
    update_post_meta($hidden, 'iss_programme_enabled', 0);
    iss_occurrences_sync_source($hidden);
    $expect(iss_content_model_sanitize_event_project_id($first) === 0, 'Non-project targets rejected');
    $expect(iss_content_model_sanitize_event_project_id(987654321) === 0, 'Missing targets rejected');
    $expect(iss_content_model_get_event_project($first)->ID === $project, 'Canonical reference resolves');
    $future = iss_programm_get_project_events($project, 'upcoming');
    $expect(array_column($future, 'source_post_id') === [$first, $second], 'Separate evenings stay visible; opt-out respected');
    $expect(array_column(iss_programm_get_project_events($project, 'past'), 'source_post_id') === [$past], 'Date rotation separates past editions');
    $expect($future[0]['series_key'] === '' && $future[1]['series_key'] === '', 'No recurrence keys created');
    $expect($future[0]['content_url'] === get_permalink($first), 'Calendar links to the one canonical record');
    $document = iss_editorial_get_empty_document('projekt');
    $document['skin'] = 'buehne';
    $document['sections'] = [['type' => 'dynamic_slot', 'title' => 'Die Abende', 'slot_key' => 'project-events', 'treatment' => 'slot.project-events']];
    $expect(!is_wp_error(iss_editorial_validate_document($document, 'projekt')), 'Automatic section is valid project JSON');
    $expect(iss_editorial_save_document($project, 'projekt', $document, false, true), 'Native editor persists section');
    $expect(iss_editorial_document_is_enabled($project, 'projekt'), 'JSON editor enabled');
    $read = iss_editorial_get_read_model($project, 'projekt');
    $expect(($read['sections'][0]['slot_key'] ?? '') === 'project-events', 'Editor read model preserves live source');
    $GLOBALS['post'] = get_post($project);
    $html = industriesalon_render_editorial_project_section($read['sections'][0], false, 0, 'buehne');
    $expect(str_contains($html, get_permalink($first)) && str_contains($html, get_permalink($second)), 'Project renders both future events');
    $expect(str_contains($html, 'Vergangene Ausgaben') && str_contains($html, get_permalink($past)), 'Past events remain accessible');
    $expect(!str_contains($html, get_permalink($hidden)), 'Project never shows programme opt-out');
    $dom = new DOMDocument();
    @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    $xpath = new DOMXPath($dom);
    $feature = $xpath->query('//article[contains(@class, "iss-card--programme-feature-compact")]');
    $expect($feature->length === 1 && str_contains($dom->saveHTML($feature->item(0)), get_permalink($first)), 'Next evening uses the homepage featured card');
    $expect($xpath->query('//article[contains(@class, "iss-timeline__item")]')->length === 2, 'Remaining upcoming and past evenings use compact timeline rows');
    $expect($xpath->query('//article[.//a[@href="' . get_permalink($first) . '"]]')->length === 1, 'Featured evening is not repeated in the list');
    $expect(str_contains($html, 'iss-timeline__year-label">2050'), 'Past editions retain visible year headings');
    $now = '2050-06-18 12:00:00';
    $expired = industriesalon_render_project_events_slot($project);
    $expect(!str_contains($expired, 'iss-card--programme-feature-compact') && str_contains($expired, 'Weitere Termine werden angekündigt.'), 'Expired evenings never remain featured');
    $expect(substr_count($expired, '<article class="iss-timeline__item ') === 3, 'Expired evenings automatically move into the archive');
    $now = '2050-06-15 12:00:00';
    $service = iss_graph_get_service();
    iss_graph_reconcile_post($first);
    $entity = iss_graph_get_entity_for_post($first);
    $project_entity = iss_graph_get_entity_for_post($project);
    foreach ([$entity, $project_entity] as $named_entity) {
        $service->replace_entity_names_for_source((int) $named_entity['id'], 'project_event_fixture', [['name' => 'Older imported title', 'name_type' => 'primary', 'is_primary' => true]]);
    }
    $edges = $service->get_relations_for_entity((int) $entity['id'], 'project', ['source_system' => 'content_native']);
    $expect(count($edges) === 1 && $edges[0]['relation_type'] === 'part_of', 'Graph receives one membership edge');
    $expect($edges[0]['name'] === get_the_title($project), 'Outgoing graph selects current canonical primary name');
    $incoming = $service->get_incoming_relations_for_entity((int) $project_entity['id'], 'veranstaltung', ['relation_family' => 'project']);
    $expect(count($incoming) === count(array_unique(array_column($incoming, 'id'))), 'Multiple imported primary names do not multiply incoming relations');
    foreach ($incoming as $edge) {
        if ((int) $edge['entity_id'] === (int) $entity['id']) { $expect($edge['name'] === get_the_title($first), 'Incoming graph selects current canonical event name'); }
    }
    $request = new WP_REST_Request('POST', '/wp/v2/veranstaltung/' . $first);
    $request->set_param('meta', ['iss_project_id' => $other]);
    $response = rest_do_request($request);
    $expect($response->get_status() === 200, 'REST editor saves the reference');
    iss_graph_flush_request_projections();
    $expect(iss_content_model_get_event_project($first)->ID === $other, 'Reference reassigned');
    $edges = $service->get_relations_for_entity((int) $entity['id'], 'project', ['source_system' => 'content_native']);
    $other_entity = iss_graph_get_entity_for_post($other);
    $expect(count($edges) === 1 && (int) $edges[0]['entity_id'] === (int) $other_entity['id'], 'Reassignment replaces old graph edge');
    $expect(array_column(iss_programm_get_project_events($project, 'upcoming'), 'source_post_id') === [$second], 'Old project immediately drops reassigned event');
    wp_update_post(['ID' => $other, 'post_status' => 'draft']);
    $expect(iss_content_model_get_event_project($first) === null, 'Private/draft parent has no public link');
    iss_graph_flush_request_projections();
    $expect(!$service->get_relations_for_entity((int) $entity['id'], 'project', ['public_only' => true]), 'Parent visibility transition hides public graph membership');
    wp_update_post(['ID' => $other, 'post_status' => 'publish']);
    iss_graph_flush_request_projections();
    $expect(count($service->get_relations_for_entity((int) $entity['id'], 'project', ['public_only' => true])) === 1, 'Publishing parent restores membership without resaving event');
    delete_post_meta($first, 'iss_project_id');
    iss_graph_flush_request_projections();
    $expect(!$service->get_relations_for_entity((int) $entity['id'], 'project', ['source_system' => 'content_native']), 'Removing the field removes only the owned edge');
    $expect(count(iss_occurrences_query(['source_post_ids' => [$first], 'time_mode' => 'all'])) === 1, 'Removing membership preserves the event in the calendar');
    $columns = iss_content_model_add_veranstaltung_entity_column(['title' => 'Title']);
    $expect(isset($columns['iss_project_id']), 'Admin event table exposes the project column');
    $report = $create('rueckblick', 'Report');
    $expect(iss_content_report_set_sources($report, [$past, $project]), 'Report links to its event and project through the owner');
    $old_query = $GLOBALS['wp_query']; $old_main = $GLOBALS['wp_the_query'];
    $old_server_name = $_SERVER['SERVER_NAME'] ?? null;
    try {
        $_SERVER['SERVER_NAME'] = wp_parse_url(home_url(), PHP_URL_HOST);
        $GLOBALS['wp_query'] = new WP_Query(['p' => $project, 'post_type' => 'projekt']);
        $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
        $page = (static function (): string {
            ob_start();
            include get_stylesheet_directory() . '/single-projekt-stage.php';
            return (string) ob_get_clean();
        })();
        $expect(str_contains($page, get_permalink($report)), 'Actual Bühne template renders the reciprocal report link');
        $expect(substr_count($page, '<h2>Rückblicke</h2>') === 1 && str_contains($page, 'Die Abende'), 'Shared report feed appears once alongside the project document');
        $expect(str_contains($page, 'Rückblick lesen') && !str_contains($page, 'iss-related-feed__media-shell'), 'Text-only retrospective cards offer a clear link without an empty image');
        $expect(get_the_excerpt($report) === '', 'Empty report excerpt cannot recurse into report relationships');
    } finally {
        $GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main;
        if ($old_server_name === null) { unset($_SERVER['SERVER_NAME']); } else { $_SERVER['SERVER_NAME'] = $old_server_name; }
    }
} finally {
    remove_filter('iss_occurrences_query_now', $clock);
    $GLOBALS['post'] = $old_post;
    $fixture_sets = [];
    foreach (array_reverse($created) as $id) {
        $sets = iss_content_editorial_sets_service()->get_links_for_context(get_post_type($id), $id);
        foreach ($sets as $link) { $fixture_sets[(int) $link['set_id']] = true; }
        wp_delete_post($id, true);
    }
    foreach (array_keys($fixture_sets) as $set_id) {
        $expect(iss_content_editorial_sets_service()->delete_set_if_safe($set_id), 'Remove empty fixture source Set');
    }
    iss_graph_flush_request_projections();
}
WP_CLI::success("Project events: $checks checks passed; fixtures removed.");
