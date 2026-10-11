<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- CLI verification reads counts from plugin-owned graph tables.

if (defined('WP_CLI') && WP_CLI) {
    require_once __DIR__ . '/cli-operations.php';
    require_once __DIR__ . '/cli-hygiene.php';
    require_once __DIR__ . '/cli-facade-audit.php';
    require_once __DIR__ . '/cli-facade-compare.php';
    require_once __DIR__ . '/cli-drift.php';
    require_once __DIR__ . '/cli-migrations.php';

    WP_CLI::add_command('iss-graph verify', 'iss_graph_wpcli_verify_command');
    WP_CLI::add_command('iss-graph entity-hygiene-audit', 'iss_graph_wpcli_entity_hygiene_audit_command');
    WP_CLI::add_command('iss-graph autonomy-health', 'iss_graph_wpcli_autonomy_health_command');
    WP_CLI::add_command('iss-graph relation-audit', 'iss_graph_wpcli_relation_audit_command');
    WP_CLI::add_command('iss-graph relation-backfill', 'iss_graph_wpcli_relation_backfill_command');
    WP_CLI::add_command('iss-graph relation-dedupe', 'iss_graph_wpcli_relation_dedupe_command');
    WP_CLI::add_command('iss-graph autonomy-fixtures', 'iss_graph_wpcli_autonomy_fixtures_command');
    WP_CLI::add_command('iss-graph reconcile', 'iss_graph_wpcli_reconcile_command');
    WP_CLI::add_command('iss-graph signals-export', 'iss_graph_wpcli_signals_export_command');
    WP_CLI::add_command('iss-graph signals-import', 'iss_graph_wpcli_signals_import_command');
    WP_CLI::add_command('iss-graph drift-check', 'iss_graph_wpcli_drift_check_command');
    WP_CLI::add_command('iss-graph facade-check', 'iss_graph_wpcli_facade_check_command');
    WP_CLI::add_command('iss-graph facade-consumer-audit', 'iss_graph_wpcli_facade_consumer_audit_command');
    WP_CLI::add_command('iss-graph view-contract-audit', 'iss_graph_wpcli_view_contract_audit_command');
    WP_CLI::add_command('iss-graph facade-search-compare', 'iss_graph_wpcli_facade_search_compare_command');
    WP_CLI::add_command('iss-graph facade-occurrences-compare', 'iss_graph_wpcli_facade_occurrences_compare_command');
    WP_CLI::add_command('iss-graph facade-entity-occurrences-compare', 'iss_graph_wpcli_facade_entity_occurrences_compare_command');
    WP_CLI::add_command('iss-graph facade-entities-compare', 'iss_graph_wpcli_facade_entities_compare_command');
    WP_CLI::add_command('iss-graph facade-entity-relations-compare', 'iss_graph_wpcli_facade_entity_relations_compare_command');
    WP_CLI::add_command('iss-graph facade-availability-compare', 'iss_graph_wpcli_facade_availability_compare_command');
    WP_CLI::add_command('iss-graph facade-timeline-compare', 'iss_graph_wpcli_facade_timeline_compare_command');
    WP_CLI::add_command('iss-graph facade-booking-slots-compare', 'iss_graph_wpcli_facade_tour_slots_compare_command');
    WP_CLI::add_command('iss-graph migrate', 'iss_graph_wpcli_migrate_command');
    WP_CLI::add_command('iss-graph sync-register', 'iss_graph_wpcli_sync_register_command');
    WP_CLI::add_command('iss-graph sync-content', 'iss_graph_wpcli_sync_content_command');
    WP_CLI::add_command('iss-graph sync-profiles', 'iss_graph_wpcli_sync_profiles_command');
    WP_CLI::add_command('iss-graph sync-video-transcripts', 'iss_graph_wpcli_sync_video_transcripts_command');
    WP_CLI::add_command('iss-graph sync-archive', 'iss_graph_wpcli_sync_archive_command');
    WP_CLI::add_command('iss-graph sync-aliases', 'iss_graph_wpcli_sync_aliases_command');
    WP_CLI::add_command('iss-graph sync-search', 'iss_graph_wpcli_sync_search_command');
    WP_CLI::add_command('iss-graph import-enrichment', 'iss_graph_wpcli_import_enrichment_command');
}

function iss_graph_wpcli_drift_check_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(500, absint($assoc_args['limit'] ?? 50)));
    $checks = iss_graph_wpcli_parse_drift_checks((string) ($assoc_args['checks'] ?? ''));
    $errors = [];
    $stats = [];

    foreach ($checks as $check) {
        $result = iss_graph_wpcli_run_drift_check($check, $limit);
        $stats[$check] = (int) ($result['checked'] ?? 0);
        foreach ((array) ($result['errors'] ?? []) as $error) {
            if (is_string($error) && $error !== '') {
                $errors[] = $error;
            }
        }
    }

    foreach ($stats as $check => $checked) {
        WP_CLI::log(sprintf('[drift] %s checked=%d', $check, $checked));
    }

    if ($errors) {
        WP_CLI::error_multi_line(array_slice($errors, 0, $limit));
        $remaining = count($errors) - $limit;
        if ($remaining > 0) {
            WP_CLI::warning(sprintf('%d additional drift issues hidden by --limit=%d.', $remaining, $limit));
        }
        WP_CLI::error(sprintf('ISS graph drift check failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph drift check passed.');
}
