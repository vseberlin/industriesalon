<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Existing CLI verification reads owner graph tables.

function iss_graph_wpcli_log_migration_step(string $label, callable $callback, string $option = '', string $version = ''): void
{
    WP_CLI::log(sprintf('[migrate] %s', $label));
    $result = $callback();
    if ($option !== '' && $version !== '') {
        update_option($option, $version, false);
    }

    if (is_array($result)) {
        WP_CLI::log(sprintf('[migrate] %s result=%s', $label, wp_json_encode($result)));
        return;
    }

    WP_CLI::log(sprintf('[migrate] %s count=%d', $label, (int) $result));
}

function iss_graph_wpcli_migrate_command(array $args, array $assoc_args): void
{
    if (isset($assoc_args['dry-run'])) {
        $steps = ['install graph schema', 'ensure editorial signal capability'];
        $steps[] = 'backfill relation autonomy columns';
        $steps[] = 'deprecate duplicate relation edges';
        if (!isset($assoc_args['skip-sync'])) {
            if (function_exists('iss_graph_backfill_register_places')) {
                $steps[] = 'sync register places';
            }
            if (function_exists('iss_graph_backfill_public_content_entities')) {
                $steps[] = 'sync public content entities';
            }
            if (function_exists('iss_graph_backfill_archive_objects')) {
                $steps[] = 'sync archive objects';
            }
            if (function_exists('iss_graph_backfill_entity_profile_bindings')) {
                $steps[] = 'sync profile bindings';
            }
            if (function_exists('iss_graph_backfill_entity_aliases')) {
                $steps[] = 'sync entity aliases';
            }
            if (function_exists('iss_graph_backfill_public_search_index')) {
                $steps[] = 'sync public search index';
            }
            if (isset($assoc_args['with-video-transcripts']) && function_exists('iss_graph_backfill_video_transcript_mentions')) {
                $steps[] = 'sync video transcript mentions';
            }
        }
        if (!isset($assoc_args['skip-drift'])) {
            $steps[] = 'run drift checks';
        }

        WP_CLI::log(wp_json_encode([
            'dry_run' => true,
            'installed_schema_version' => (string) get_option(ISS_GRAPH_SCHEMA_OPTION, ''),
            'target_schema_version' => ISS_GRAPH_SCHEMA_VERSION,
            'would_run' => $steps,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        WP_CLI::success('ISS graph migration dry-run completed. No changes made.');
        return;
    }

    WP_CLI::log('[migrate] installing graph schema');
    iss_graph_get_service()->install_schema();
    iss_graph_ensure_editorial_signals_capability();
    WP_CLI::log('[migrate] relation autonomy columns');
    $relation_backfill_stats = iss_graph_backfill_relation_autonomy_columns(['batch_size' => 5000]);
    WP_CLI::log(sprintf('[migrate] relation autonomy selected=%d updated=%d', (int) ($relation_backfill_stats['selected'] ?? 0), (int) ($relation_backfill_stats['updated'] ?? 0)));
    WP_CLI::log('[migrate] relation duplicate audit');
    iss_graph_wpcli_relation_dedupe_command([], ['limit' => 500]);

    if (!isset($assoc_args['skip-sync'])) {
        if (function_exists('iss_graph_backfill_register_places')) {
            iss_graph_wpcli_log_migration_step('register places', 'iss_graph_backfill_register_places', ISS_GRAPH_REGISTER_BACKFILL_OPTION, ISS_GRAPH_REGISTER_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_public_content_entities')) {
            iss_graph_wpcli_log_migration_step('public content entities', 'iss_graph_backfill_public_content_entities', ISS_GRAPH_CONTENT_BACKFILL_OPTION, ISS_GRAPH_CONTENT_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_archive_objects')) {
            iss_graph_wpcli_log_migration_step('archive objects', 'iss_graph_backfill_archive_objects', ISS_GRAPH_ARCHIVE_BACKFILL_OPTION, ISS_GRAPH_ARCHIVE_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_entity_profile_bindings')) {
            iss_graph_wpcli_log_migration_step('profile bindings', 'iss_graph_backfill_entity_profile_bindings', ISS_GRAPH_PROFILE_BACKFILL_OPTION, ISS_GRAPH_PROFILE_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_entity_aliases')) {
            iss_graph_wpcli_log_migration_step('entity aliases', 'iss_graph_backfill_entity_aliases', ISS_GRAPH_ALIAS_BACKFILL_OPTION, ISS_GRAPH_ALIAS_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_public_search_index')) {
            iss_graph_wpcli_log_migration_step('public search index', 'iss_graph_backfill_public_search_index', ISS_GRAPH_SEARCH_BACKFILL_OPTION, ISS_GRAPH_SEARCH_BACKFILL_VERSION);
        }

        if (isset($assoc_args['with-video-transcripts']) && function_exists('iss_graph_backfill_video_transcript_mentions')) {
            iss_graph_wpcli_log_migration_step('video transcript mentions', 'iss_graph_backfill_video_transcript_mentions');
        }
    }

    if (!isset($assoc_args['skip-drift'])) {
        iss_graph_wpcli_drift_check_command([], [
            'checks' => (string) ($assoc_args['checks'] ?? ''),
            'limit' => (int) ($assoc_args['limit'] ?? 50),
        ]);
    }

    WP_CLI::success('ISS graph migration completed.');
}

function iss_graph_wpcli_sync_register_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);
    $dry_run = isset($assoc_args['dry-run']);

    if ($post_id > 0) {
        if ($dry_run) {
            WP_CLI::success(sprintf('Register place sync dry-run completed for post %d. No changes made.', $post_id));
            return;
        }

        $entity = iss_graph_sync_register_place_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Register place %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced register place %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    if ($dry_run) {
        $post_type = function_exists('iss_graph_get_register_post_type') ? iss_graph_get_register_post_type() : 'register_place';
        $count = count(get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
            'suppress_filters' => true,
        ]));
        WP_CLI::success(sprintf('Register place sync dry-run completed: would_sync=%d. No changes made.', $count));
        return;
    }

    $count = iss_graph_backfill_register_places();
    update_option(ISS_GRAPH_REGISTER_BACKFILL_OPTION, ISS_GRAPH_REGISTER_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d register places into the graph.', $count));
}

function iss_graph_wpcli_sync_content_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);
    $dry_run = isset($assoc_args['dry-run']);

    if ($post_id > 0) {
        if ($dry_run) {
            WP_CLI::success(sprintf('Public content sync dry-run completed for post %d. No changes made.', $post_id));
            return;
        }

        $entity = iss_graph_sync_public_content_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Public content post %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced public content post %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    if ($dry_run) {
        $post_types = function_exists('iss_graph_get_content_relation_post_types') ? iss_graph_get_content_relation_post_types() : [];
        $count = count(get_posts([
            'post_type' => $post_types ?: ['post'],
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'numberposts' => -1,
            'fields' => 'ids',
            'suppress_filters' => true,
        ]));
        WP_CLI::success(sprintf('Public content sync dry-run completed: would_sync=%d. No changes made.', $count));
        return;
    }

    $count = iss_graph_backfill_public_content_entities();
    update_option(ISS_GRAPH_CONTENT_BACKFILL_OPTION, ISS_GRAPH_CONTENT_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d public content posts into the graph.', $count));
}

function iss_graph_wpcli_sync_profiles_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_graph_backfill_entity_profile_bindings')) {
        WP_CLI::error('Entity profile bindings are not available.');
    }

    $count = iss_graph_backfill_entity_profile_bindings();
    update_option(ISS_GRAPH_PROFILE_BACKFILL_OPTION, ISS_GRAPH_PROFILE_BACKFILL_VERSION, false);

    WP_CLI::success(sprintf('Synced %d entity profile bindings.', $count));
}

function iss_graph_wpcli_sync_video_transcripts_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_graph_sync_video_transcript_mentions')) {
        WP_CLI::error('Video transcript bridge is not loaded.');
    }

    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $result = iss_graph_sync_video_transcript_mentions($post_id);

        if (empty($result['synced'])) {
            WP_CLI::error(sprintf('Video transcript mentions for post %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf(
            'Synced video transcript mentions for post %d: entity=%d status=%s segments=%d mentions=%d.',
            $post_id,
            (int) ($result['entity_id'] ?? 0),
            (string) ($result['status'] ?? ''),
            (int) ($result['segments'] ?? 0),
            (int) ($result['mentions'] ?? 0)
        ));
        return;
    }

    $stats = iss_graph_backfill_video_transcript_mentions();
    WP_CLI::success(sprintf(
        'Synced video transcript mentions: videos=%d synced=%d mentions=%d.',
        (int) ($stats['videos'] ?? 0),
        (int) ($stats['synced'] ?? 0),
        (int) ($stats['mentions'] ?? 0)
    ));
}

function iss_graph_wpcli_sync_archive_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);
    $dry_run = isset($assoc_args['dry-run']);

    if ($post_id > 0) {
        if ($dry_run) {
            WP_CLI::success(sprintf('Archive object sync dry-run completed for post %d. No changes made.', $post_id));
            return;
        }

        $entity = iss_graph_sync_archive_object_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Archive object %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced archive object %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    if ($dry_run) {
        $post_type = function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt';
        $count = count(get_posts([
            'post_type' => $post_type,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'suppress_filters' => true,
        ]));
        WP_CLI::success(sprintf('Archive object sync dry-run completed: would_sync=%d. No changes made.', $count));
        return;
    }

    $count = iss_graph_backfill_archive_objects();
    update_option(ISS_GRAPH_ARCHIVE_BACKFILL_OPTION, ISS_GRAPH_ARCHIVE_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d archive objects into the graph.', $count));
}

function iss_graph_wpcli_sync_aliases_command(array $args, array $assoc_args): void
{
    $entity_id = absint($assoc_args['entity_id'] ?? 0);
    $dry_run = !empty($assoc_args['dry-run']);
    $sample_limit = max(1, min(500, absint($assoc_args['limit'] ?? 25)));

    if ($entity_id > 0) {
        if ($dry_run) {
            $preview = iss_graph_preview_entity_alias_backfill($entity_id);
            if (!$preview) {
                WP_CLI::error(sprintf('Graph entity %d could not be previewed.', $entity_id));
            }

            iss_graph_wpcli_log_alias_backfill_preview($preview);
            WP_CLI::success(sprintf('Alias backfill dry-run completed for graph entity %d. No changes made.', $entity_id));
            return;
        }

        $count = iss_graph_sync_entity_alias_backfill($entity_id);
        WP_CLI::success(sprintf('Synced %d generated alias name(s) for graph entity %d.', $count, $entity_id));
        return;
    }

    if ($dry_run) {
        $stats = iss_graph_audit_entity_alias_backfill(['sample_limit' => $sample_limit]);
        WP_CLI::log(sprintf(
            'Alias backfill dry-run: entities=%d changed_entities=%d current_names=%d proposed_names=%d removed_names=%d added_names=%d.',
            (int) ($stats['entities'] ?? 0),
            (int) ($stats['changed_entities'] ?? 0),
            (int) ($stats['current_names'] ?? 0),
            (int) ($stats['proposed_names'] ?? 0),
            (int) ($stats['removed_names'] ?? 0),
            (int) ($stats['added_names'] ?? 0)
        ));

        foreach ((array) ($stats['samples'] ?? []) as $preview) {
            if (is_array($preview)) {
                iss_graph_wpcli_log_alias_backfill_preview($preview);
            }
        }

        WP_CLI::success('Alias backfill dry-run completed. No changes made.');
        return;
    }

    $stats = iss_graph_backfill_entity_aliases();
    update_option(ISS_GRAPH_ALIAS_BACKFILL_OPTION, ISS_GRAPH_ALIAS_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf(
        'Synced aliases for %d entities: with_aliases=%d names=%d.',
        (int) ($stats['entities'] ?? 0),
        (int) ($stats['with_aliases'] ?? 0),
        (int) ($stats['names'] ?? 0)
    ));
}

function iss_graph_wpcli_log_alias_backfill_preview(array $preview): void
{
    WP_CLI::log(sprintf(
        '[alias-preview] entity=%d kind=%s current=%d proposed=%d removed=%d added=%d title="%s"',
        (int) ($preview['entity_id'] ?? 0),
        (string) ($preview['entity_kind'] ?? ''),
        (int) ($preview['current_count'] ?? 0),
        (int) ($preview['proposed_count'] ?? 0),
        (int) ($preview['removed_count'] ?? 0),
        (int) ($preview['added_count'] ?? 0),
        iss_graph_wpcli_entity_hygiene_clip((string) ($preview['title'] ?? ''), 120)
    ));

    $removed = array_slice((array) ($preview['removed'] ?? []), 0, 8);
    if ($removed) {
        WP_CLI::log('  removed: ' . iss_graph_wpcli_entity_hygiene_clip(implode(' | ', array_map('strval', $removed)), 220));
    }

    $added = array_slice((array) ($preview['added'] ?? []), 0, 8);
    if ($added) {
        WP_CLI::log('  added: ' . iss_graph_wpcli_entity_hygiene_clip(implode(' | ', array_map('strval', $added)), 220));
    }
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
