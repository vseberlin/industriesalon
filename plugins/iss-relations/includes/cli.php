<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-relations sync', 'iss_relations_wpcli_sync_command');
}

function iss_relations_wpcli_sync_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);
    $post_type = sanitize_key((string) ($assoc_args['post_type'] ?? ''));

    if ($post_id > 0) {
        iss_relations_sync_post_terms($post_id);
        WP_CLI::success(sprintf('Synced place relation index for post %d.', $post_id));
        return;
    }

    $post_types = [];
    if ($post_type !== '') {
        $post_types[] = $post_type;
    }

    $result = iss_relations_backfill_index($post_types);
    delete_option('iss_relations_needs_backfill');

    WP_CLI::success(sprintf(
        'Synced %d posts across: %s',
        (int) $result['count'],
        implode(', ', (array) $result['post_types'])
    ));
}

