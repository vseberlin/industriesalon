<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-graph verify', 'iss_graph_wpcli_verify_command');
    WP_CLI::add_command('iss-graph sync-register', 'iss_graph_wpcli_sync_register_command');
    WP_CLI::add_command('iss-graph sync-archive', 'iss_graph_wpcli_sync_archive_command');
    WP_CLI::add_command('iss-graph sync-search', 'iss_graph_wpcli_sync_search_command');
    WP_CLI::add_command('iss-graph import-enrichment', 'iss_graph_wpcli_import_enrichment_command');
}

function iss_graph_wpcli_verify_command(array $args, array $assoc_args): void
{
    global $wpdb;

    $service = iss_graph_get_service();
    $tables = [
        'entity_index' => $service->get_entity_table_name(),
        'entity_names' => $service->get_name_table_name(),
        'entity_relations' => $service->get_relation_table_name(),
        'search_index' => $service->get_search_table_name(),
        'person_facts' => $service->get_person_facts_table_name(),
        'organization_facts' => $service->get_organization_facts_table_name(),
    ];

    $failed = false;
    foreach ($tables as $label => $table_name) {
        $exists = $service->table_exists($table_name);
        if ($exists) {
            WP_CLI::log(sprintf('[ok] %s %s', $label, $table_name));
            continue;
        }

        $failed = true;
        WP_CLI::warning(sprintf('%s missing: %s', $label, $table_name));
    }

    $entity_count = $service->table_exists($service->get_entity_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_entity_table_name()}")
        : 0;
    $name_count = $service->table_exists($service->get_name_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_name_table_name()}")
        : 0;
    $relation_count = $service->table_exists($service->get_relation_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_relation_table_name()}")
        : 0;
    $search_count = $service->table_exists($service->get_search_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_search_table_name()}")
        : 0;
    $person_facts_count = $service->table_exists($service->get_person_facts_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_person_facts_table_name()}")
        : 0;
    $organization_facts_count = $service->table_exists($service->get_organization_facts_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_organization_facts_table_name()}")
        : 0;

    WP_CLI::log(sprintf(
        'entities=%d names=%d relations=%d search=%d person_facts=%d organization_facts=%d',
        $entity_count,
        $name_count,
        $relation_count,
        $search_count,
        $person_facts_count,
        $organization_facts_count
    ));

    if ($failed) {
        WP_CLI::error('ISS graph verification failed.');
    }

    WP_CLI::success('ISS graph verification passed.');
}

function iss_graph_wpcli_sync_register_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $entity = iss_graph_sync_register_place_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Register place %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced register place %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    $count = iss_graph_backfill_register_places();
    update_option(ISS_GRAPH_REGISTER_BACKFILL_OPTION, ISS_GRAPH_REGISTER_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d register places into the graph.', $count));
}

function iss_graph_wpcli_sync_archive_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $entity = iss_graph_sync_archive_object_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Archive object %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced archive object %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    $count = iss_graph_backfill_archive_objects();
    update_option(ISS_GRAPH_ARCHIVE_BACKFILL_OPTION, ISS_GRAPH_ARCHIVE_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d archive objects into the graph.', $count));
}

function iss_graph_wpcli_sync_search_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $row = iss_graph_sync_public_search_post($post_id);

        if (!$row) {
            WP_CLI::error(sprintf('Public search row for post %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced public search row for post %d.', $post_id));
        return;
    }

    $count = iss_graph_backfill_public_search_index();
    update_option(ISS_GRAPH_SEARCH_BACKFILL_OPTION, ISS_GRAPH_SEARCH_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d public search rows.', $count));
}

function iss_graph_wpcli_import_enrichment_command(array $args, array $assoc_args): void
{
    $source = sanitize_title((string) ($args[0] ?? ($assoc_args['source'] ?? '')));
    if ($source === '') {
        WP_CLI::error('Provide an enrichment source slug, for example: wp iss-graph import-enrichment wista-report-schoeneweide-2011-17');
    }

    $dataset = iss_graph_get_enrichment_dataset($source);
    if (!$dataset) {
        WP_CLI::error(sprintf('Enrichment dataset not found: %s', $source));
    }

    $result = iss_graph_import_enrichment_dataset($dataset, [
        'status' => $assoc_args['status'] ?? ($dataset['post_status'] ?? 'draft'),
        'profiles' => !isset($assoc_args['no-profiles']),
    ]);

    foreach ($result['profiles'] as $name => $post_id) {
        WP_CLI::log(sprintf('%s -> entity_profile %d', $name, (int) $post_id));
    }

    WP_CLI::success(sprintf(
        'Imported enrichment %s: entities=%d facts=%d profiles=%d',
        $source,
        count($result['entities']),
        count($result['facts']),
        count($result['profiles'])
    ));
}
