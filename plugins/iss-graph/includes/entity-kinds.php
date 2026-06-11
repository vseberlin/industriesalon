<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_normalize_entity_kind_key(string $value): string
{
    return sanitize_key($value);
}

function iss_graph_get_entity_kind_registry(): array
{
    $register_post_type = defined('ISS_REGISTER_POST_TYPE') ? sanitize_key((string) ISS_REGISTER_POST_TYPE) : 'register_place';
    $archive_object_post_type = defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_OBJECT_POST_TYPE) : 'archivobjekt';
    $archive_collection_post_type = defined('ISS_WF_IMPORT_COLLECTION_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_COLLECTION_POST_TYPE) : 'archivsammlung';

    $registry = [
        'place' => [
            'label' => __('Ort', 'iss-graph'),
            'owner' => 'industriesalon-schoeneweide-register',
            'post_types' => [$register_post_type],
            'storage_kind' => 'place',
            'aliases' => [],
            'public' => true,
        ],
        'organization' => [
            'label' => __('Organisation', 'iss-graph'),
            'owner' => 'iss-graph',
            'post_types' => [],
            'storage_kind' => 'organization',
            'aliases' => [],
            'public' => true,
        ],
        'person' => [
            'label' => __('Person', 'iss-graph'),
            'owner' => 'iss-graph',
            'post_types' => [],
            'storage_kind' => 'person',
            'aliases' => [],
            'public' => true,
        ],
        'archive_object' => [
            'label' => __('Archivobjekt', 'iss-graph'),
            'owner' => 'iss-wf-import',
            'post_types' => [$archive_object_post_type],
            'storage_kind' => 'archive_object',
            'aliases' => [],
            'public' => true,
        ],
        'archive_collection' => [
            'label' => __('Archivsammlung', 'iss-graph'),
            'owner' => 'iss-wf-import',
            'post_types' => [$archive_collection_post_type],
            'storage_kind' => 'archive_collection',
            'aliases' => ['archivsammlung'],
            'public' => true,
        ],
        'exhibition' => [
            'label' => __('Ausstellung', 'iss-graph'),
            'owner' => 'iss-content-model',
            'post_types' => ['ausstellung'],
            'storage_kind' => 'ausstellung',
            'aliases' => ['ausstellung'],
            'public' => true,
        ],
        'tour' => [
            'label' => __('Fuehrung', 'iss-graph'),
            'owner' => 'iss-fuehrungen',
            'post_types' => ['fuehrung'],
            'storage_kind' => 'fuehrung',
            'aliases' => ['fuehrung'],
            'public' => true,
        ],
        'event' => [
            'label' => __('Veranstaltung', 'iss-graph'),
            'owner' => 'iss-content-model',
            'post_types' => ['veranstaltung'],
            'storage_kind' => 'veranstaltung',
            'aliases' => ['veranstaltung'],
            'public' => true,
        ],
        'project' => [
            'label' => __('Projekt', 'iss-graph'),
            'owner' => 'iss-content-model',
            'post_types' => ['projekt'],
            'storage_kind' => 'projekt',
            'aliases' => ['projekt'],
            'public' => true,
        ],
        'publication' => [
            'label' => __('Publikation', 'iss-graph'),
            'owner' => 'iss-publications',
            'post_types' => ['publication'],
            'storage_kind' => 'publication',
            'aliases' => [],
            'public' => true,
        ],
        'video' => [
            'label' => __('Video', 'iss-graph'),
            'owner' => 'iss-content-model',
            'post_types' => ['video'],
            'storage_kind' => 'video',
            'aliases' => [],
            'public' => true,
        ],
        'content' => [
            'label' => __('Inhalt', 'iss-graph'),
            'owner' => 'wordpress',
            'post_types' => ['post'],
            'storage_kind' => 'post',
            'aliases' => [],
            'public' => true,
        ],
        'page' => [
            'label' => __('Seite', 'iss-graph'),
            'owner' => 'wordpress',
            'post_types' => ['page'],
            'storage_kind' => 'page',
            'aliases' => [],
            'public' => true,
        ],
        'archive_story' => [
            'label' => __('Archivbeitrag', 'iss-graph'),
            'owner' => 'iss-wf-import',
            'post_types' => ['archivbeitrag'],
            'storage_kind' => 'archivbeitrag',
            'aliases' => ['archivbeitrag'],
            'public' => true,
        ],
    ];

    $registry = (array) apply_filters('iss_graph_entity_kind_registry', $registry);
    $normalized = [];

    foreach ($registry as $canonical_kind => $definition) {
        $canonical_kind = iss_graph_normalize_entity_kind_key((string) $canonical_kind);
        if ($canonical_kind === '' || !is_array($definition)) {
            continue;
        }

        $storage_kind = iss_graph_normalize_entity_kind_key((string) ($definition['storage_kind'] ?? $canonical_kind));
        if ($storage_kind === '') {
            $storage_kind = $canonical_kind;
        }

        $post_types = [];
        foreach ((array) ($definition['post_types'] ?? []) as $post_type) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type !== '') {
                $post_types[] = $post_type;
            }
        }

        $aliases = [];
        foreach ((array) ($definition['aliases'] ?? []) as $alias) {
            $alias = iss_graph_normalize_entity_kind_key((string) $alias);
            if ($alias !== '') {
                $aliases[] = $alias;
            }
        }

        $normalized[$canonical_kind] = array_merge($definition, [
            'canonical_kind' => $canonical_kind,
            'storage_kind' => $storage_kind,
            'post_types' => array_values(array_unique($post_types)),
            'aliases' => array_values(array_unique($aliases)),
            'owner' => sanitize_key((string) ($definition['owner'] ?? 'iss-graph')),
            'public' => !array_key_exists('public', $definition) || (bool) $definition['public'],
        ]);
    }

    return $normalized;
}

function iss_graph_get_entity_kind_definition(string $entity_kind): ?array
{
    $entity_kind = iss_graph_normalize_entity_kind_key($entity_kind);
    if ($entity_kind === '') {
        return null;
    }

    $registry = iss_graph_get_entity_kind_registry();
    if (isset($registry[$entity_kind])) {
        return $registry[$entity_kind];
    }

    foreach ($registry as $definition) {
        $storage_kind = (string) ($definition['storage_kind'] ?? '');
        $aliases = (array) ($definition['aliases'] ?? []);

        if ($entity_kind === $storage_kind || in_array($entity_kind, $aliases, true)) {
            return $definition;
        }
    }

    return null;
}

function iss_graph_get_canonical_entity_kind(string $entity_kind): string
{
    $definition = iss_graph_get_entity_kind_definition($entity_kind);

    return $definition ? (string) $definition['canonical_kind'] : iss_graph_normalize_entity_kind_key($entity_kind);
}

function iss_graph_get_storage_entity_kind(string $entity_kind): string
{
    $definition = iss_graph_get_entity_kind_definition($entity_kind);

    return $definition ? (string) $definition['storage_kind'] : iss_graph_normalize_entity_kind_key($entity_kind);
}

function iss_graph_get_entity_kind_storage_candidates(string $entity_kind): array
{
    $entity_kind = iss_graph_normalize_entity_kind_key($entity_kind);
    $definition = iss_graph_get_entity_kind_definition($entity_kind);

    if (!$definition) {
        return $entity_kind !== '' ? [$entity_kind] : [];
    }

    $candidates = [
        (string) ($definition['storage_kind'] ?? ''),
        (string) ($definition['canonical_kind'] ?? ''),
    ];
    $candidates = array_merge($candidates, (array) ($definition['aliases'] ?? []));

    return array_values(array_unique(array_filter(array_map('iss_graph_normalize_entity_kind_key', $candidates))));
}

function iss_graph_get_entity_kind_label_from_registry(string $entity_kind): string
{
    $definition = iss_graph_get_entity_kind_definition($entity_kind);
    if ($definition && isset($definition['label']) && is_scalar($definition['label'])) {
        return (string) $definition['label'];
    }

    return ucfirst(str_replace('_', ' ', iss_graph_normalize_entity_kind_key($entity_kind)));
}

function iss_graph_get_entity_kind_owner(string $entity_kind): string
{
    $definition = iss_graph_get_entity_kind_definition($entity_kind);

    return $definition ? (string) ($definition['owner'] ?? '') : '';
}

function iss_graph_get_entity_kind_for_post_type_map(): array
{
    $map = [];

    foreach (iss_graph_get_entity_kind_registry() as $definition) {
        $storage_kind = iss_graph_normalize_entity_kind_key((string) ($definition['storage_kind'] ?? ''));
        if ($storage_kind === '') {
            continue;
        }

        foreach ((array) ($definition['post_types'] ?? []) as $post_type) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type !== '') {
                $map[$post_type] = $storage_kind;
            }
        }
    }

    $map = (array) apply_filters('iss_graph_entity_kind_for_post_type_map', $map);
    $normalized = [];

    foreach ($map as $post_type => $entity_kind) {
        $post_type = sanitize_key((string) $post_type);
        $entity_kind = iss_graph_normalize_entity_kind_key((string) $entity_kind);
        if ($post_type !== '' && $entity_kind !== '') {
            $normalized[$post_type] = $entity_kind;
        }
    }

    return $normalized;
}

function iss_graph_get_storage_entity_kind_for_post_type(string $post_type): string
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return '';
    }

    $map = iss_graph_get_entity_kind_for_post_type_map();

    return sanitize_key((string) ($map[$post_type] ?? $post_type));
}

function iss_graph_get_canonical_entity_kind_for_post_type(string $post_type): string
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return '';
    }

    foreach (iss_graph_get_entity_kind_registry() as $canonical_kind => $definition) {
        if (in_array($post_type, (array) ($definition['post_types'] ?? []), true)) {
            return (string) $canonical_kind;
        }
    }

    return iss_graph_get_canonical_entity_kind($post_type);
}
