<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-relations sync', 'iss_relations_wpcli_sync_command');
    WP_CLI::add_command('iss-relations backfill-quality', 'iss_relations_wpcli_backfill_quality_command');
    WP_CLI::add_command('iss-relations map-block-audit', 'iss_relations_wpcli_map_block_audit_command');
}

function iss_relations_map_block_audit_targets(): array
{
    return array_keys(iss_relations_get_map_block_contracts());
}

function iss_relations_map_block_audit_add_missing_marker_findings(array $places, array $config, string $owner, string $block_name, array &$findings): void
{
    $marker_lookup = iss_relations_get_place_map_marker_lookup((string) ($config['markers_path'] ?? ''));
    if (!$marker_lookup) {
        return;
    }

    foreach ($places as $place) {
        if (!is_array($place)) {
            continue;
        }

        if (iss_relations_get_place_map_marker_position($place, $marker_lookup) !== null) {
            continue;
        }

        $place_id = (int) ($place['place_id'] ?? 0);
        $title = trim((string) ($place['title'] ?? ''));

        $findings[] = [
            'severity' => 'error',
            'owner' => $owner,
            'block' => $block_name,
            'code' => 'selected_place_missing_marker',
            'message' => sprintf('Selected place #%d %s has no marker in preset marker JSON.', $place_id, $title),
        ];
    }
}

function iss_relations_map_block_audit_resolve_block_places(array $attributes, string $block_name, int $owner_post_id): array
{
    $source = iss_relations_resolve_map_block_source($attributes, $block_name);

    if ($source === 'manual') {
        return iss_relations_build_place_items_from_ids(
            iss_relations_parse_place_ids($attributes['placeIds'] ?? '')
        );
    }

    if ($owner_post_id <= 0) {
        return [];
    }

    return iss_relations_resolve_block_place_items($attributes, $owner_post_id, $block_name);
}

function iss_relations_map_block_audit_scan_blocks(array $blocks, string $owner, array &$findings, int $owner_post_id = 0): void
{
    $targets = array_fill_keys(iss_relations_map_block_audit_targets(), true);

    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }

        $block_name = (string) ($block['blockName'] ?? '');
        $attributes = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];

        if ($block_name !== '' && isset($targets[$block_name])) {
            $place_ids = trim((string) ($attributes['placeIds'] ?? ''));
            $has_source = array_key_exists('source', $attributes);
            $source = $has_source ? sanitize_key((string) $attributes['source']) : '';
            $resolved_source = iss_relations_resolve_map_block_source($attributes, $block_name);
            $preset = $block_name === 'iss/spine-strip'
                ? iss_relations_resolve_spine_strip_preset($attributes)
                : iss_relations_resolve_place_map_preset($attributes);
            $config = iss_relations_get_place_map_config($preset);
            $markers_path = (string) ($config['markers_path'] ?? '');

            if ($place_ids !== '' && !$has_source) {
                $findings[] = [
                    'severity' => 'warning',
                    'owner' => $owner,
                    'block' => $block_name,
                    'code' => 'missing_source_with_place_ids',
                    'message' => sprintf('placeIds present without explicit source; resolves as %s.', $resolved_source),
                ];
            }

            if ($place_ids !== '' && $has_source && $source !== 'manual') {
                $findings[] = [
                    'severity' => 'error',
                    'owner' => $owner,
                    'block' => $block_name,
                    'code' => 'place_ids_non_manual_source',
                    'message' => sprintf('placeIds present but source is %s.', $source),
                ];
            }

            if (isset($attributes['mapPreset'])) {
                $requested_preset = sanitize_key((string) $attributes['mapPreset']);
                if ($requested_preset !== '' && $requested_preset !== $preset) {
                    $findings[] = [
                        'severity' => 'error',
                        'owner' => $owner,
                        'block' => $block_name,
                        'code' => 'unknown_map_preset',
                        'message' => sprintf('Requested mapPreset %s resolved to %s.', $requested_preset, $preset),
                    ];
                }
            }

            if ($markers_path === '' || !is_readable($markers_path)) {
                $findings[] = [
                    'severity' => 'error',
                    'owner' => $owner,
                    'block' => $block_name,
                    'code' => 'missing_marker_json',
                    'message' => sprintf('Preset %s has unreadable marker JSON.', $preset),
                ];
            } else {
                $places = iss_relations_map_block_audit_resolve_block_places($attributes, $block_name, $owner_post_id);
                if ($places) {
                    iss_relations_map_block_audit_add_missing_marker_findings($places, $config, $owner, $block_name, $findings);
                }
            }
        }

        if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
            iss_relations_map_block_audit_scan_blocks($block['innerBlocks'], $owner, $findings, $owner_post_id);
        }
    }
}

function iss_relations_map_block_audit_register_place_marker_findings(array &$findings): void
{
    $place_type = iss_relations_get_place_post_type();
    if ($place_type === '') {
        return;
    }

    $config = iss_relations_get_place_map_config('default');
    $marker_lookup = iss_relations_get_place_map_marker_lookup((string) ($config['markers_path'] ?? ''));
    if (!$marker_lookup) {
        return;
    }

    $posts = get_posts([
        'post_type' => $place_type,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
    ]);

    foreach ($posts as $post_id) {
        $post_id = (int) $post_id;
        $lat = get_post_meta($post_id, 'lat', true);
        $lng = get_post_meta($post_id, 'lng', true);

        if (!is_numeric($lat) || !is_numeric($lng)) {
            continue;
        }

        $place = iss_relations_build_place_item($post_id);
        if (!$place || iss_relations_get_place_map_marker_position($place, $marker_lookup) !== null) {
            continue;
        }

        $findings[] = [
            'severity' => 'error',
            'owner' => sprintf('%s:%d', $place_type, $post_id),
            'block' => 'static-marker-json',
            'code' => 'coordinate_place_missing_marker',
            'message' => sprintf('Published coordinate-bearing place #%d %s has no default static marker.', $post_id, get_the_title($post_id)),
        ];
    }
}

function iss_relations_map_block_audit_findings(): array
{
    $findings = [];
    $post_types = ['wp_template', 'wp_template_part', 'page', 'post'];
    $posts = get_posts([
        'post_type' => $post_types,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post || trim((string) $post->post_content) === '') {
            continue;
        }

        iss_relations_map_block_audit_scan_blocks(
            parse_blocks($post->post_content),
            sprintf('%s:%s:%d', (string) $post->post_type, (string) $post->post_name, (int) $post->ID),
            $findings,
            (int) $post->ID
        );
    }

    $template_dir = trailingslashit(get_stylesheet_directory()) . 'templates';
    if (is_dir($template_dir)) {
        foreach (glob($template_dir . '/*.html') ?: [] as $file) {
            $content = file_get_contents($file);
            if (!is_string($content) || trim($content) === '') {
                continue;
            }

            iss_relations_map_block_audit_scan_blocks(
                parse_blocks($content),
                'theme-template:' . basename($file),
                $findings
            );
        }
    }

    iss_relations_map_block_audit_register_place_marker_findings($findings);

    return $findings;
}

function iss_relations_wpcli_map_block_audit_command(array $args, array $assoc_args): void
{
    $format = sanitize_key((string) ($assoc_args['format'] ?? 'table'));
    $findings = iss_relations_map_block_audit_findings();
    $errors = array_values(array_filter($findings, static function (array $finding): bool {
        return ($finding['severity'] ?? '') === 'error';
    }));

    if ($format === 'json') {
        WP_CLI::log(wp_json_encode($findings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    } elseif ($findings) {
        WP_CLI::log('severity	owner	block	code	message');
        foreach ($findings as $finding) {
            WP_CLI::log(implode('	', [
                (string) ($finding['severity'] ?? ''),
                (string) ($finding['owner'] ?? ''),
                (string) ($finding['block'] ?? ''),
                (string) ($finding['code'] ?? ''),
                (string) ($finding['message'] ?? ''),
            ]));
        }
    }

    if ($errors) {
        WP_CLI::error(sprintf('Map block audit found %d blocking issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('Map block audit passed with %d warning(s).', count($findings)));
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
