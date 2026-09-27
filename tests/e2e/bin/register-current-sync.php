<?php
/** Transactional runtime coverage: no fixture or source change survives. */
if (!defined('WP_CLI') || !WP_CLI) {
    exit(1);
}
// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL -- Transactional integration checks over service-owned tables.
function register_sync_expect(bool $ok, string $label): void
{
    if (!$ok) {
        throw new RuntimeException(esc_html($label));
    }
    $GLOBALS['register_sync_checks'] = ($GLOBALS['register_sync_checks'] ?? 0) + 1;
}
global $wpdb;
$service = iss_register_get_epoch_service();
$table = $service->get_table_name();
$state_table = iss_register_get_place_state_service()->get_table_name();
$place = get_page_by_path('behrens-ufer-be-u', OBJECT, ISS_REGISTER_POST_TYPE);
if (!$place instanceof WP_Post) {
    WP_CLI::error('Expected the existing Behrens-Ufer Place.');
}
$post_id = (int) $place->ID;
$original_rows = $wpdb->get_results("SELECT * FROM {$table} ORDER BY id", ARRAY_A);
$original_states = $wpdb->get_results("SELECT * FROM {$state_table} ORDER BY id", ARRAY_A);
$original_meta = get_post_meta($post_id);
$graph = iss_graph_get_service();
$relation_table = $graph->get_relation_table_name();
$entity_table = $graph->get_entity_table_name();
$original_relations = $wpdb->get_results("SELECT * FROM {$relation_table} ORDER BY id", ARRAY_A);
$original_entities = $wpdb->get_results("SELECT * FROM {$entity_table} WHERE entity_kind = 'organization' ORDER BY id", ARRAY_A);
$original_epochs = $service->get_epochs_for_place($post_id);
$original_milestone_count = count($service->get_milestones_for_place($post_id));
$counts = $service->get_counts_by_era_and_function();
$event = [
    'key' => 'test:ownership-2025-03-05',
    'event_date' => '2025-03-05',
    'date_precision' => 'day',
    'event_type' => 'ownership',
    'title' => 'Ownership event <script>alert(1)</script>',
    'summary' => 'A source-described change, not a period of land use.',
    'source_url' => 'https://github.com/industriesalon-schoeneweide/schoeneweide-register',
    'source_revision' => str_repeat('a', 40),
    'source_summary' => 'Test evidence',
    'observed_at' => '2026-09-27T09:00:00+00:00',
];
$error = null;
$wpdb->query('START TRANSACTION');
try {
    $namespace = 'industriesalon-schoeneweide/schoeneweide-register';
    $site_identity = iss_register_resolve_supplier_place($namespace, '13');
    $component_identity = iss_register_resolve_supplier_place($namespace, '70');
    register_sync_expect(!is_wp_error($site_identity) && !is_wp_error($component_identity) && $site_identity['post_id'] === $component_identity['post_id'], 'Supplier 13 and 70 resolve to the same existing Place.');
    register_sync_expect($component_identity['canonical_source_id'] === '13' && $component_identity['source_ids'] === ['13', '70'], 'Component preserves canonical identity and both source IDs.');
    register_sync_expect(!in_array('current_status', $component_identity['allowed_fields'], true) && !in_array('owner', $component_identity['allowed_fields'], true), 'Component cannot overwrite whole-site status or owner.');
    register_sync_expect(is_wp_error(iss_register_resolve_supplier_place('unknown-supplier', '70')), 'Unknown namespace cannot reuse a supplier identity.');
    update_post_meta($post_id, 'register_id', '70');
    register_sync_expect(is_wp_error(iss_register_resolve_supplier_place($namespace, '70')), 'A duplicate alias record blocks import rather than selecting a Place silently.');
    update_post_meta($post_id, 'register_id', $original_meta['register_id'][0]);
    register_sync_expect($service->append_milestone($post_id, $event) === true, 'Append a new event.');
    register_sync_expect($service->append_milestone($post_id, $event) === false, 'Same event is a no-op.');
    $changed = $event;
    $changed['summary'] = 'Different claim';
    register_sync_expect(is_wp_error($service->append_milestone($post_id, $changed)), 'Cannot silently overwrite an accepted event.');
    foreach (['2025-02-30', '2025-3-5', 'unknown'] as $bad_date) {
        $bad = $event;
        $bad['event_date'] = $bad_date;
        register_sync_expect(is_wp_error($service->validate_milestone($bad)), 'Reject invalid or invented dates.');
    }
    $unknown = $event;
    $unknown['key'] = 'test:unknown-event';
    $unknown['event_date'] = null;
    $unknown['date_precision'] = 'unknown';
    $unknown['reported_as_of'] = '2026-05';
    register_sync_expect($service->append_milestone($post_id, $unknown) === true, 'Unknown event date is retained.');
    register_sync_expect(iss_register_milestone_date_label($unknown) === 'Zeitpunkt unbekannt', 'Reporting month is not rendered as event date.');
    register_sync_expect($service->get_epochs_for_place($post_id) === $original_epochs, 'Events do not enter phase reads.');
    register_sync_expect($service->get_counts_by_era_and_function() === $counts, 'Events do not change Atlas historical filter counts.');
    $timeline = iss_register_merge_place_milestones($post_id, $original_epochs);
    register_sync_expect(count($timeline) === count($original_epochs) + $original_milestone_count + 2, 'One combined chronology includes the events.');
    register_sync_expect(end($timeline)['milestone']['key'] === $unknown['key'], 'Undated events are not assigned an invented chronology.');
    $html = industriesalon_render_editorial_place_document($post_id, ['sections' => []]);
    register_sync_expect(str_contains($html, '05.03.2025'), 'Exact date is rendered.');
    register_sync_expect(!str_contains($html, '<script>'), 'Source text is escaped in public output.');
    register_sync_expect(str_contains($html, 'Standortregister'), 'Public event source is linked.');
    $saved = $service->save_epochs_for_place($post_id, $original_epochs);
    register_sync_expect(!is_wp_error($saved) && count($service->get_milestones_for_place($post_id)) === $original_milestone_count + 2, 'Ordinary epoch saves preserve milestones.');
    // Roll back the phase-save exercise before testing current-only projection preservation.
    $wpdb->query('ROLLBACK');
    clean_post_cache($post_id);
    $wpdb->query('START TRANSACTION');
    update_post_meta($post_id, 'current_use', 'Temporary current-use test');
    register_sync_expect(iss_register_get_place_state_service()->sync_current_state_for_post($post_id) === true, 'Current-only projection succeeds.');
    $historical = $wpdb->get_results("SELECT * FROM {$state_table} WHERE state_kind = 'historical' ORDER BY id", ARRAY_A);
    register_sync_expect($historical === array_values(array_filter($original_states, static fn(array $row): bool => $row['state_kind'] === 'historical')), 'Historical states remain byte-identical.');
    $current = $wpdb->get_var($wpdb->prepare("SELECT summary FROM {$state_table} WHERE place_post_id=%d AND state_kind='current'", $post_id));
    register_sync_expect($current === 'Temporary current-use test', 'Current projection reflects the new fact.');
    $entity = iss_graph_sync_register_place_entity($post_id);
    $public_roles = $graph->get_relations_for_entity((int) $entity['id'], 'organization', ['public_only' => true]);
    register_sync_expect(!in_array('owner', array_column($public_roles, 'relation_type'), true), 'Graph sync cannot revive an obsolete imported owner claim.');
    register_sync_expect($wpdb->get_results("SELECT * FROM {$entity_table} WHERE entity_kind = 'organization' ORDER BY id", ARRAY_A) === $original_entities, 'Qualified imported claims do not become invented organizations.');
} catch (Throwable $caught) {
    $error = $caught;
} finally {
    $wpdb->query('ROLLBACK');
    clean_post_cache($post_id);
    wp_cache_delete('iss_register_atlas_cache_version', 'options');
}
register_sync_expect($wpdb->get_results("SELECT * FROM {$table} ORDER BY id", ARRAY_A) === $original_rows, 'All chronology rows restored.');
register_sync_expect($wpdb->get_results("SELECT * FROM {$state_table} ORDER BY id", ARRAY_A) === $original_states, 'All states restored.');
register_sync_expect(get_post_meta($post_id) === $original_meta, 'All source metadata restored.');
register_sync_expect($wpdb->get_results("SELECT * FROM {$relation_table} ORDER BY id", ARRAY_A) === $original_relations, 'All graph relations restored.');
if ($error) {
    WP_CLI::error($error->getMessage());
}
WP_CLI::success((string) $GLOBALS['register_sync_checks'] . ' register current-sync checks passed; transactional changes rolled back.');
