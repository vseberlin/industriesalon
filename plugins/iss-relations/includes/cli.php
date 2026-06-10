<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-relations sync', 'iss_relations_wpcli_sync_command');
    WP_CLI::add_command('iss-relations backfill-quality', 'iss_relations_wpcli_backfill_quality_command');
}

function iss_relations_wpcli_sync_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);
    $post_type = sanitize_key((string) ($assoc_args['post_type'] ?? ''));

    if ($post_id > 0) {
        iss_relations_sync_post_read_models($post_id);
        WP_CLI::success(sprintf('Synced place relation read models for post %d.', $post_id));
        return;
    }

    $post_types = [];
    if ($post_type !== '') {
        $post_types[] = $post_type;
    }

    $result = iss_relations_backfill_index($post_types);
    delete_option('iss_relations_needs_backfill');
    update_option(ISS_RELATIONS_GRAPH_IDENTIFIER_BACKFILL_OPTION, ISS_RELATIONS_GRAPH_IDENTIFIER_BACKFILL_VERSION, false);

    WP_CLI::success(sprintf(
        'Synced %d posts across: %s',
        (int) $result['count'],
        implode(', ', (array) $result['post_types'])
    ));
}

function iss_relations_wpcli_backfill_quality_command(array $args, array $assoc_args): void
{
    $post_type_arg = (string) ($assoc_args['post_type'] ?? '');
    $dry_run = !empty($assoc_args['dry-run']);

    $post_types = [];
    if ($post_type_arg !== '') {
        $post_types = array_values(array_filter(array_map('sanitize_key', preg_split('/[\s,]+/', $post_type_arg) ?: [])));
    }

    $result = iss_relations_backfill_relation_quality($post_types, $dry_run);
    $posts = (array) ($result['posts'] ?? []);

    foreach ($posts as $row) {
        if (!is_array($row)) {
            continue;
        }

        $before_summary = array_map(static function (array $relation): string {
            return sprintf(
                '%d:%s@%d',
                (int) ($relation['place_id'] ?? 0),
                (string) ($relation['role'] ?? ''),
                (int) ($relation['weight'] ?? 0)
            );
        }, (array) ($row['before'] ?? []));

        $after_summary = array_map(static function (array $relation): string {
            return sprintf(
                '%d:%s@%d',
                (int) ($relation['place_id'] ?? 0),
                (string) ($relation['role'] ?? ''),
                (int) ($relation['weight'] ?? 0)
            );
        }, (array) ($row['after'] ?? []));

        WP_CLI::log(sprintf(
            '#%d [%s] %s :: %s -> %s',
            (int) ($row['post_id'] ?? 0),
            (string) ($row['post_type'] ?? ''),
            (string) ($row['title'] ?? ''),
            implode(', ', $before_summary),
            implode(', ', $after_summary)
        ));
    }

    $message = sprintf(
        '%s relation quality for %d posts across: %s',
        $dry_run ? 'Dry-run checked' : 'Backfilled',
        (int) ($result['count'] ?? 0),
        implode(', ', (array) ($result['post_types'] ?? []))
    );

    WP_CLI::success($message);
}
