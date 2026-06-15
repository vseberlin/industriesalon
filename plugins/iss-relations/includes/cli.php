<?php

if (!defined('ABSPATH')) {
    exit;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-relations sync', 'iss_relations_wpcli_sync_command');
    WP_CLI::add_command('iss-relations backfill-quality', 'iss_relations_wpcli_backfill_quality_command');
    WP_CLI::add_command('iss-relations map-block-audit', 'iss_relations_wpcli_map_block_audit_command');
    WP_CLI::add_command('iss-relations static-map-contract-check', 'iss_relations_wpcli_static_map_contract_check_command');
}

function iss_relations_contract_check_result(string $label, bool $passed, string $details = ''): array
{
    return [
        'label' => $label,
        'passed' => $passed,
        'details' => $details,
    ];
}

function iss_relations_contract_is_list(array $payload): bool
{
    return array_values($payload) === $payload;
}

function iss_relations_contract_collect_exact_key_errors(array $payload, array $expected_keys, string $path): array
{
    $actual_keys = array_values(array_keys($payload));
    if ($actual_keys === array_values($expected_keys)) {
        return [];
    }

    return [
        sprintf('%s keys expected [%s], got [%s]', $path, implode(',', $expected_keys), implode(',', $actual_keys)),
    ];
}

function iss_relations_contract_collect_string_error(array $payload, string $key, string $path, bool $allow_empty = true): array
{
    if (!array_key_exists($key, $payload) || !is_string($payload[$key])) {
        return [sprintf('%s.%s must be a string.', $path, $key)];
    }

    if (!$allow_empty && trim($payload[$key]) === '') {
        return [sprintf('%s.%s must not be empty.', $path, $key)];
    }

    return [];
}

function iss_relations_contract_collect_int_error(array $payload, string $key, string $path, int $min = 0): array
{
    if (!array_key_exists($key, $payload) || !is_int($payload[$key]) || $payload[$key] < $min) {
        return [sprintf('%s.%s must be an integer >= %d.', $path, $key, $min)];
    }

    return [];
}

function iss_relations_contract_collect_numeric_or_null_error(array $payload, string $key, string $path): array
{
    if (!array_key_exists($key, $payload) || ($payload[$key] !== null && !is_numeric($payload[$key]))) {
        return [sprintf('%s.%s must be numeric or null.', $path, $key)];
    }

    return [];
}

function iss_relations_contract_collect_array_error(array $payload, string $key, string $path): array
{
    if (!array_key_exists($key, $payload) || !is_array($payload[$key])) {
        return [sprintf('%s.%s must be an array.', $path, $key)];
    }

    return [];
}

function iss_relations_static_map_relation_result_keys(): array
{
    return [
        'source',
        'block_name',
        'context_post_id',
        'selected_place_ids',
        'places',
        'count',
    ];
}

function iss_relations_static_map_place_dto_keys(): array
{
    return [
        'canonical_id',
        'post_id',
        'place_id',
        'slug',
        'title',
        'short_label',
        'label',
        'permalink',
        'excerpt',
        'address',
        'area',
        'location_label',
        'type',
        'state',
        'coordinates',
        'lat',
        'lng',
        'map_marker',
        'thumbnail_id',
        'thumbnail_url',
        'source',
        'relation',
        'role',
        'weight',
        'route_title',
        'route_teaser',
        'station_object_id',
        'station_story_id',
    ];
}

function iss_relations_static_map_relation_keys(): array
{
    return [
        'source',
        'role',
        'label',
        'weight',
        'route_title',
        'route_teaser',
        'station_object_id',
        'station_story_id',
    ];
}

function iss_relations_static_map_find_sample_place_id(): int
{
    $place_type = iss_relations_get_place_post_type();
    if ($place_type === '') {
        return 0;
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
        if (
            iss_relations_is_usable_place($post_id)
            && is_numeric(get_post_meta($post_id, 'lat', true))
            && is_numeric(get_post_meta($post_id, 'lng', true))
        ) {
            return $post_id;
        }
    }

    return 0;
}

function iss_relations_static_map_collect_place_dto_errors(array $place, string $path, int $expected_place_id, string $expected_source): array
{
    $errors = iss_relations_contract_collect_exact_key_errors($place, iss_relations_static_map_place_dto_keys(), $path);

    foreach (['canonical_id', 'slug', 'title', 'short_label', 'label', 'permalink', 'excerpt', 'address', 'area', 'location_label', 'type', 'state', 'thumbnail_url', 'source', 'role', 'route_title', 'route_teaser'] as $key) {
        $errors = array_merge($errors, iss_relations_contract_collect_string_error($place, $key, $path, !in_array($key, ['canonical_id', 'slug', 'title', 'short_label', 'permalink', 'source', 'role'], true)));
    }

    foreach (['post_id', 'place_id'] as $key) {
        $errors = array_merge($errors, iss_relations_contract_collect_int_error($place, $key, $path, 1));
    }
    foreach (['thumbnail_id', 'weight', 'station_object_id', 'station_story_id'] as $key) {
        $errors = array_merge($errors, iss_relations_contract_collect_int_error($place, $key, $path, 0));
    }
    foreach (['lat', 'lng'] as $key) {
        $errors = array_merge($errors, iss_relations_contract_collect_numeric_or_null_error($place, $key, $path));
    }

    if (($place['post_id'] ?? null) !== $expected_place_id || ($place['place_id'] ?? null) !== $expected_place_id) {
        $errors[] = sprintf('%s post_id/place_id must both equal sample place %d.', $path, $expected_place_id);
    }

    if (($place['source'] ?? '') !== $expected_source || ($place['role'] ?? '') === '') {
        $errors[] = sprintf('%s source/role are not normalized.', $path);
    }

    if (!array_key_exists('map_marker', $place) || $place['map_marker'] !== null) {
        $errors[] = sprintf('%s.map_marker must be null before frontend marker projection.', $path);
    }

    if (!isset($place['coordinates']) || !is_array($place['coordinates'])) {
        $errors[] = sprintf('%s.coordinates must be an array for the coordinate-bearing sample place.', $path);
    } else {
        $errors = array_merge($errors, iss_relations_contract_collect_exact_key_errors($place['coordinates'], ['lat', 'lng'], $path . '.coordinates'));
        foreach (['lat', 'lng'] as $key) {
            $errors = array_merge($errors, iss_relations_contract_collect_numeric_or_null_error($place['coordinates'], $key, $path . '.coordinates'));
        }
    }

    if (!isset($place['relation']) || !is_array($place['relation'])) {
        $errors[] = sprintf('%s.relation must be an array.', $path);
    } else {
        $relation_path = $path . '.relation';
        $errors = array_merge($errors, iss_relations_contract_collect_exact_key_errors($place['relation'], iss_relations_static_map_relation_keys(), $relation_path));
        foreach (['source', 'role', 'label', 'route_title', 'route_teaser'] as $key) {
            $errors = array_merge($errors, iss_relations_contract_collect_string_error($place['relation'], $key, $relation_path, !in_array($key, ['source', 'role'], true)));
        }
        foreach (['weight', 'station_object_id', 'station_story_id'] as $key) {
            $errors = array_merge($errors, iss_relations_contract_collect_int_error($place['relation'], $key, $relation_path, 0));
        }
        if (($place['relation']['source'] ?? '') !== $expected_source) {
            $errors[] = sprintf('%s.source must equal %s.', $relation_path, $expected_source);
        }
    }

    return $errors;
}

function iss_relations_static_map_collect_relation_result_errors(array $result, string $block_name, int $place_id): array
{
    $path = 'static-map-result:' . $block_name;
    $errors = iss_relations_contract_collect_exact_key_errors($result, iss_relations_static_map_relation_result_keys(), $path);

    $errors = array_merge($errors, iss_relations_contract_collect_string_error($result, 'source', $path, false));
    $errors = array_merge($errors, iss_relations_contract_collect_string_error($result, 'block_name', $path, false));
    $errors = array_merge($errors, iss_relations_contract_collect_int_error($result, 'context_post_id', $path, 0));
    $errors = array_merge($errors, iss_relations_contract_collect_int_error($result, 'count', $path, 0));
    $errors = array_merge($errors, iss_relations_contract_collect_array_error($result, 'selected_place_ids', $path));
    $errors = array_merge($errors, iss_relations_contract_collect_array_error($result, 'places', $path));

    if (($result['source'] ?? '') !== 'manual') {
        $errors[] = sprintf('%s.source must resolve manual for manual placeIds.', $path);
    }
    if (($result['block_name'] ?? '') !== $block_name) {
        $errors[] = sprintf('%s.block_name must equal %s.', $path, $block_name);
    }
    if (($result['count'] ?? null) !== 1) {
        $errors[] = sprintf('%s.count must equal 1 for the sample place.', $path);
    }
    if (!isset($result['selected_place_ids']) || !is_array($result['selected_place_ids']) || !iss_relations_contract_is_list($result['selected_place_ids']) || $result['selected_place_ids'] !== [$place_id]) {
        $errors[] = sprintf('%s.selected_place_ids must equal [%d].', $path, $place_id);
    }
    if (!isset($result['places']) || !is_array($result['places']) || !iss_relations_contract_is_list($result['places'])) {
        $errors[] = sprintf('%s.places must be a list array.', $path);
    }

    $place = is_array($result['places'][0] ?? null) ? $result['places'][0] : [];
    if (!$place) {
        $errors[] = sprintf('%s.places[0] is missing.', $path);
        return $errors;
    }

    return array_merge(
        $errors,
        iss_relations_static_map_collect_place_dto_errors($place, $path . '.places[0]', $place_id, 'manual')
    );
}

function iss_relations_static_map_contract_checks(): array
{
    $checks = [];
    $contracts = iss_relations_get_map_block_contracts();
    $public_contracts = iss_relations_get_map_block_public_contracts();
    $target_blocks = ['iss/related-place-map', 'iss/atlas-slice', 'iss/spine-strip'];
    $place_id = iss_relations_static_map_find_sample_place_id();

    $checks[] = iss_relations_contract_check_result(
        'map-block-public-contract-shape',
        !array_diff($target_blocks, array_keys($public_contracts)),
        'Missing first-class public map block contract(s).'
    );

    foreach ($target_blocks as $block_name) {
        $contract = is_array($contracts[$block_name] ?? null) ? $contracts[$block_name] : [];
        $public = is_array($public_contracts[$block_name] ?? null) ? $public_contracts[$block_name] : [];
        $contract_errors = [];

        $contract_errors = array_merge($contract_errors, iss_relations_contract_collect_exact_key_errors($public, ['defaultSource', 'defaultPreset', 'manualIdsImplyManualSource'], 'public-contract:' . $block_name));
        foreach (['default_source', 'default_preset', 'frontend_renderer'] as $key) {
            $contract_errors = array_merge($contract_errors, iss_relations_contract_collect_string_error($contract, $key, 'map-contract:' . $block_name, false));
        }
        if (!array_key_exists('manual_ids_imply_manual_source', $contract) || !is_bool($contract['manual_ids_imply_manual_source'])) {
            $contract_errors[] = sprintf('map-contract:%s.manual_ids_imply_manual_source must be boolean.', $block_name);
        }

        $checks[] = iss_relations_contract_check_result(
            'map-block-contract:' . $block_name,
            empty($contract_errors),
            implode(' ', array_slice($contract_errors, 0, 4))
        );
    }

    if ($place_id <= 0) {
        $checks[] = iss_relations_contract_check_result(
            'static-map-sample-place',
            false,
            'No published coordinate-bearing register place was available.'
        );
        return $checks;
    }

    foreach ($target_blocks as $block_name) {
        $result = iss_relations_resolve_static_map_relation_result(
            ['placeIds' => (string) $place_id],
            0,
            $block_name
        );
        $errors = iss_relations_static_map_collect_relation_result_errors($result, $block_name, $place_id);

        $checks[] = iss_relations_contract_check_result(
            'static-map-relation-result:' . $block_name,
            empty($errors),
            implode(' ', array_slice($errors, 0, 4))
        );
    }

    return $checks;
}

function iss_relations_wpcli_static_map_contract_check_command(array $args, array $assoc_args): void
{
    $checks = iss_relations_static_map_contract_checks();

    foreach ($checks as $check) {
        $label = (string) ($check['label'] ?? 'unknown');
        $details = trim((string) ($check['details'] ?? ''));

        if (!empty($check['passed'])) {
            WP_CLI::log(sprintf('[ok] %s', $label));
            continue;
        }

        WP_CLI::warning($details !== '' ? sprintf('%s: %s', $label, $details) : $label);
    }

    $failed = array_filter($checks, static function (array $check): bool {
        return empty($check['passed']);
    });

    if ($failed) {
        WP_CLI::error('Static map contract check failed.');
    }

    WP_CLI::success('Static map contract check passed.');
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
