<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_get_enrichment_dataset_path(string $slug): string
{
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return '';
    }

    return ISS_GRAPH_PATH . 'data/enrichments/' . $slug . '.php';
}

function iss_graph_get_enrichment_dataset(string $slug): array
{
    $path = iss_graph_get_enrichment_dataset_path($slug);
    if ($path === '' || !is_readable($path)) {
        return [];
    }

    $data = require $path;

    return is_array($data) ? $data : [];
}

function iss_graph_normalize_enrichment_profile_status($value): string
{
    $status = sanitize_key((string) $value);
    if (!in_array($status, ['publish', 'draft', 'private', 'pending'], true)) {
        return 'draft';
    }

    return $status;
}

function iss_graph_ensure_enrichment_profile_post(array $entity, array $entry, string $post_status): int
{
    if (empty($entity['id']) || !in_array((string) ($entity['entity_kind'] ?? ''), iss_graph_get_profile_entity_kinds(), true)) {
        return 0;
    }

    $entity_id = (int) $entity['id'];
    $existing_post_id = absint($entity['profile_post_id'] ?? 0);
    $post_type = iss_graph_get_entity_profile_post_type();
    $post_excerpt = sanitize_textarea_field((string) ($entry['post_excerpt'] ?? ($entry['facts']['summary'] ?? '')));
    $post_content = trim((string) ($entry['post_content'] ?? ($entry['facts']['description'] ?? '')));

    $postarr = [
        'post_type' => $post_type,
        'post_title' => sanitize_text_field((string) ($entry['profile_title'] ?? ($entry['name'] ?? ($entity['display_title'] ?? '')))),
        'post_status' => $post_status,
        'post_excerpt' => $post_excerpt,
        'post_content' => wp_kses_post($post_content),
    ];

    if ($existing_post_id > 0 && get_post($existing_post_id) instanceof WP_Post) {
        $postarr['ID'] = $existing_post_id;
        $post_id = wp_update_post($postarr, true);
    } else {
        $post_id = wp_insert_post($postarr, true);
    }

    if (is_wp_error($post_id) || $post_id <= 0) {
        return 0;
    }

    update_post_meta((int) $post_id, iss_graph_get_entity_profile_meta_key(), $entity_id);
    iss_graph_sync_profile_binding_for_post((int) $post_id);

    if (function_exists('iss_graph_sync_public_search_post')) {
        iss_graph_sync_public_search_post((int) $post_id);
    }

    return (int) $post_id;
}

function iss_graph_import_enrichment_dataset(array $dataset, array $args = []): array
{
    $entries = isset($dataset['entries']) && is_array($dataset['entries']) ? $dataset['entries'] : [];
    if (!$entries) {
        return [
            'source' => sanitize_title((string) ($dataset['source_slug'] ?? '')),
            'entities' => [],
            'profiles' => [],
            'facts' => [],
        ];
    }

    $service = iss_graph_get_service();
    $source_slug = sanitize_title((string) ($dataset['source_slug'] ?? 'dataset'));
    if ($source_slug === '') {
        $source_slug = 'dataset';
    }

    $profile_status = iss_graph_normalize_enrichment_profile_status(
        $args['status'] ?? ($dataset['post_status'] ?? 'draft')
    );
    $create_profiles = !isset($args['profiles']) || rest_sanitize_boolean($args['profiles']);

    $results = [
        'source' => $source_slug,
        'entities' => [],
        'profiles' => [],
        'facts' => [],
    ];

    foreach (array_values($entries) as $entry) {
        if (!is_array($entry)) {
            continue;
        }

        $entity_kind = sanitize_key((string) ($entry['entity_kind'] ?? ''));
        $name = sanitize_text_field((string) ($entry['name'] ?? ''));
        if ($entity_kind === '' || $name === '') {
            continue;
        }

        $entity = $service->find_or_create_named_entity($entity_kind, $name, [
            'source_system' => 'enrichment_' . $source_slug,
            'source_id' => sanitize_title((string) ($entry['source_id'] ?? $name)),
            'display_title' => sanitize_text_field((string) ($entry['display_title'] ?? $name)),
            'status' => $profile_status === 'publish' ? 'publish' : 'draft',
            'is_public' => false,
            'search_visibility' => 'hidden',
        ]);

        if (!$entity || empty($entity['id'])) {
            continue;
        }

        $entity_id = (int) $entity['id'];
        $results['entities'][$name] = $entity_id;

        $names = [];
        if (!empty($entry['names']) && is_array($entry['names'])) {
            foreach (array_values($entry['names']) as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }

                $alias = sanitize_text_field((string) ($row['name'] ?? ''));
                if ($alias === '') {
                    continue;
                }

                $names[] = [
                    'name' => $alias,
                    'name_type' => sanitize_key((string) ($row['name_type'] ?? 'alternative')),
                    'is_primary' => !empty($row['is_primary']),
                    'valid_from_year' => $service->normalize_year($row['valid_from_year'] ?? null),
                    'valid_to_year' => $service->normalize_year($row['valid_to_year'] ?? null),
                    'position' => isset($row['position']) ? (int) $row['position'] : ($index + 1),
                ];
            }
        }

        array_unshift($names, [
            'name' => $name,
            'name_type' => 'primary',
            'is_primary' => true,
            'position' => 0,
        ]);

        $service->replace_entity_names_for_source(
            $entity_id,
            'enrichment_' . $source_slug,
            $names,
            'dataset:' . $source_slug
        );

        if (!empty($entry['facts']) && is_array($entry['facts'])) {
            $facts = $service->upsert_entity_facts($entity_kind, $entity_id, $entry['facts']);
            if ($facts) {
                $results['facts'][$name] = $entity_id;
            }
        }

        if ($create_profiles && !empty($entry['create_profile'])) {
            $post_id = iss_graph_ensure_enrichment_profile_post($service->get_entity_by_id($entity_id) ?: $entity, $entry, $profile_status);
            if ($post_id > 0) {
                $results['profiles'][$name] = $post_id;
            }
        }
    }

    if (function_exists('iss_graph_backfill_public_search_index')) {
        iss_graph_backfill_public_search_index();
    }

    return $results;
}
