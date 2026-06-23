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
            'owner' => 'iss-archive',
            'post_types' => [$archive_object_post_type],
            'storage_kind' => 'archive_object',
            'aliases' => [],
            'public' => true,
        ],
        'archive_collection' => [
            'label' => __('Archivsammlung', 'iss-graph'),
            'owner' => 'iss-archive',
            'post_types' => [$archive_collection_post_type],
            'storage_kind' => 'archive_collection',
            'aliases' => ['archivsammlung'],
            'public' => true,
        ],
        'exhibition' => [
            'label' => __('Ausstellung', 'iss-graph'),
            'owner' => 'iss-content',
            'post_types' => ['ausstellung'],
            'storage_kind' => 'ausstellung',
            'aliases' => ['ausstellung'],
            'public' => true,
        ],
        'tour' => [
            'label' => __('Fuehrung', 'iss-graph'),
            'owner' => 'iss-content',
            'post_types' => ['fuehrung'],
            'storage_kind' => 'fuehrung',
            'aliases' => ['fuehrung'],
            'contract_kind' => 'offer',
            'default_subtype' => 'tour',
            'subtypes' => ['tour'],
            'public' => true,
        ],
        'event' => [
            'label' => __('Veranstaltung', 'iss-graph'),
            'owner' => 'iss-content',
            'post_types' => ['veranstaltung'],
            'storage_kind' => 'veranstaltung',
            'aliases' => ['veranstaltung'],
            'contract_kind' => 'offer',
            'default_subtype' => 'event',
            'subtypes' => ['event', 'event_report', 'lecture', 'reading', 'discussion', 'repair_cafe', 'concert', 'festival', 'workshop', 'school_program', 'presentation', 'special_opening'],
            'public' => true,
        ],
        'project' => [
            'label' => __('Projekt', 'iss-graph'),
            'owner' => 'iss-content',
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
            'owner' => 'iss-content',
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
            'owner' => 'iss-archive',
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

        $contract_kind = iss_graph_normalize_entity_kind_key((string) ($definition['contract_kind'] ?? $canonical_kind));
        if ($contract_kind === '') {
            $contract_kind = $canonical_kind;
        }

        $default_subtype = iss_graph_normalize_entity_kind_key((string) ($definition['default_subtype'] ?? ''));
        $subtypes = [];
        foreach ((array) ($definition['subtypes'] ?? []) as $subtype) {
            $subtype = iss_graph_normalize_entity_kind_key((string) $subtype);
            if ($subtype !== '') {
                $subtypes[] = $subtype;
            }
        }
        if ($default_subtype !== '') {
            $subtypes[] = $default_subtype;
        }

        $normalized[$canonical_kind] = array_merge($definition, [
            'canonical_kind' => $canonical_kind,
            'storage_kind' => $storage_kind,
            'post_types' => array_values(array_unique($post_types)),
            'aliases' => array_values(array_unique($aliases)),
            'contract_kind' => $contract_kind,
            'default_subtype' => $default_subtype,
            'subtypes' => array_values(array_unique($subtypes)),
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

function iss_graph_get_entity_contract_kind(string $entity_kind): string
{
    $definition = iss_graph_get_entity_kind_definition($entity_kind);
    if (!$definition) {
        return iss_graph_get_canonical_entity_kind($entity_kind);
    }

    $contract_kind = iss_graph_normalize_entity_kind_key((string) ($definition['contract_kind'] ?? ''));

    return $contract_kind !== '' ? $contract_kind : (string) $definition['canonical_kind'];
}

function iss_graph_get_entity_default_subtype(string $entity_kind): string
{
    $definition = iss_graph_get_entity_kind_definition($entity_kind);
    if (!$definition) {
        return '';
    }

    return iss_graph_normalize_entity_kind_key((string) ($definition['default_subtype'] ?? ''));
}

function iss_graph_get_offer_subtype_registry(): array
{
    return (array) apply_filters('iss_graph_offer_subtype_registry', [
        'tour' => [
            'label' => 'Tour',
            'public_label' => __('Führung', 'iss-graph'),
            'source' => 'post_type:fuehrung',
        ],
        'event' => [
            'label' => 'Event',
            'public_label' => __('Veranstaltung', 'iss-graph'),
            'source' => '_iss_event_format:general',
        ],
        'event_report' => [
            'label' => 'Event report',
            'public_label' => __('Rückblick', 'iss-graph'),
            'source' => '_iss_entity_key:report.rueckblick',
        ],
        'lecture' => [
            'label' => 'Lecture',
            'public_label' => __('Vortrag', 'iss-graph'),
            'source' => '_iss_event_format:vortrag',
        ],
        'discussion' => [
            'label' => 'Discussion',
            'public_label' => __('Gespräch', 'iss-graph'),
            'source' => '_iss_event_format:gespraech',
        ],
        'repair_cafe' => [
            'label' => 'Repair cafe',
            'public_label' => __('Repair Café', 'iss-graph'),
            'source' => '_iss_event_format:repair_cafe',
        ],
        'concert' => [
            'label' => 'Concert',
            'public_label' => __('Konzert', 'iss-graph'),
            'source' => '_iss_event_format:concert',
        ],
        'festival' => [
            'label' => 'Festival',
            'public_label' => __('Festival', 'iss-graph'),
            'source' => '_iss_event_format:festival',
        ],
        'workshop' => [
            'label' => 'Workshop',
            'public_label' => __('Workshop', 'iss-graph'),
            'source' => '_iss_event_format:workshop',
        ],
        'school_program' => [
            'label' => 'School program',
            'public_label' => __('Schulprogramm', 'iss-graph'),
            'source' => '_iss_event_format:school_program',
        ],
        'reading' => [
            'label' => 'Reading',
            'public_label' => __('Lesung', 'iss-graph'),
            'source' => '_iss_event_format:lesung',
        ],
        'presentation' => [
            'label' => 'Presentation',
            'public_label' => __('Präsentation', 'iss-graph'),
            'source' => '_iss_event_format:praesentation',
        ],
        'special_opening' => [
            'label' => 'Special opening',
            'public_label' => __('Sonderöffnung', 'iss-graph'),
            'source' => '_iss_event_layout:fest',
        ],
    ]);
}

function iss_graph_get_offer_subtype_definition(string $subtype): ?array
{
    $subtype = iss_graph_normalize_entity_kind_key($subtype);
    if ($subtype === '') {
        return null;
    }

    $registry = iss_graph_get_offer_subtype_registry();
    $definition = $registry[$subtype] ?? null;

    return is_array($definition) ? array_merge($definition, ['subtype' => $subtype]) : null;
}

function iss_graph_get_offer_subtype_public_label(string $subtype): string
{
    $definition = iss_graph_get_offer_subtype_definition($subtype);
    if (!$definition) {
        return '';
    }

    $public_label = trim((string) ($definition['public_label'] ?? ''));
    if ($public_label !== '') {
        return $public_label;
    }

    return trim((string) ($definition['label'] ?? ''));
}

function iss_graph_get_contract_public_label(array $contract): string
{
    $kind = iss_graph_normalize_entity_kind_key((string) ($contract['kind'] ?? ''));
    $subtype = iss_graph_normalize_entity_kind_key((string) ($contract['subtype'] ?? ''));

    if ($kind === 'offer' && $subtype !== '') {
        return iss_graph_get_offer_subtype_public_label($subtype);
    }

    return '';
}

function iss_graph_normalize_veranstaltung_format_for_offer($value): string
{
    if (function_exists('iss_content_model_normalize_veranstaltung_format')) {
        return iss_content_model_normalize_veranstaltung_format((string) $value);
    }

    if (function_exists('industriesalon_sanitize_event_format')) {
        return industriesalon_sanitize_event_format($value);
    }

    $value = sanitize_key(remove_accents((string) $value));
    if ($value === 'gesprach') {
        $value = 'gespraech';
    } elseif ($value === 'prasentation' || $value === 'presentation') {
        $value = 'praesentation';
    } elseif (in_array($value, ['repair-cafe', 'repaircafe', 'reparaturcafe', 'reparatur-cafe'], true)) {
        $value = 'repair_cafe';
    } elseif (in_array($value, ['school', 'school-program', 'schoolprogram', 'schulprogramm'], true)) {
        $value = 'school_program';
    }

    return in_array($value, ['general', 'vortrag', 'gespraech', 'lesung', 'praesentation', 'repair_cafe', 'concert', 'festival', 'workshop', 'school_program'], true) ? $value : 'general';
}

function iss_graph_normalize_veranstaltung_layout_for_offer($value): string
{
    if (function_exists('iss_content_model_normalize_veranstaltung_layout')) {
        return iss_content_model_normalize_veranstaltung_layout((string) $value);
    }

    if (function_exists('industriesalon_sanitize_event_layout')) {
        return industriesalon_sanitize_event_layout($value);
    }

    $value = sanitize_key((string) $value);
    if ($value === 'feature') {
        $value = 'fest';
    }

    return in_array($value, ['standard', 'compact', 'fest', 'long'], true) ? $value : 'standard';
}

function iss_graph_normalize_veranstaltung_entity_key_for_offer($value): string
{
    if (function_exists('iss_content_model_sanitize_veranstaltung_entity_key')) {
        return iss_content_model_sanitize_veranstaltung_entity_key((string) $value);
    }

    $value = sanitize_key(str_replace('.', '-', (string) $value));
    $value = str_replace('-', '.', $value);

    return in_array($value, array_keys(iss_graph_get_veranstaltung_entity_offer_subtype_map()), true) ? $value : '';
}

function iss_graph_get_veranstaltung_entity_offer_subtype_map(): array
{
    return [
        'event.general' => 'event',
        'event.vortrag' => 'lecture',
        'event.gespraech' => 'discussion',
        'event.lesung' => 'reading',
        'event.praesentation' => 'presentation',
        'event.workshop' => 'workshop',
        'event.konzert' => 'concert',
        'event.school_program' => 'school_program',
        'event.festival' => 'festival',
        'event.repair_cafe' => 'repair_cafe',
        'report.rueckblick' => 'event_report',
    ];
}

function iss_graph_get_offer_subtype_for_veranstaltung_entity_key(string $entity_key): string
{
    $entity_key = iss_graph_normalize_veranstaltung_entity_key_for_offer($entity_key);
    if ($entity_key === '') {
        return '';
    }

    $map = iss_graph_get_veranstaltung_entity_offer_subtype_map();

    return sanitize_key((string) ($map[$entity_key] ?? ''));
}

function iss_graph_get_offer_contract_for_post($post): array
{
    $post = $post instanceof WP_Post ? $post : get_post(absint($post));
    if (!$post instanceof WP_Post) {
        return [];
    }

    $post_type = sanitize_key((string) $post->post_type);
    if ($post_type === 'fuehrung') {
        return [
            'kind' => 'offer',
            'subtype' => 'tour',
            'subtype_source' => 'post_type:fuehrung',
        ];
    }

    if ($post_type !== 'veranstaltung') {
        return [];
    }

    $entity_key = iss_graph_normalize_veranstaltung_entity_key_for_offer(get_post_meta((int) $post->ID, '_iss_entity_key', true));
    $entity_subtype = $entity_key !== '' ? iss_graph_get_offer_subtype_for_veranstaltung_entity_key($entity_key) : '';
    if ($entity_subtype !== '') {
        return [
            'kind' => 'offer',
            'subtype' => $entity_subtype,
            'subtype_source' => '_iss_entity_key:' . $entity_key,
        ];
    }

    $layout = iss_graph_normalize_veranstaltung_layout_for_offer(get_post_meta((int) $post->ID, '_iss_event_layout', true));
    if ($layout === 'fest') {
        return [
            'kind' => 'offer',
            'subtype' => 'special_opening',
            'subtype_source' => '_iss_event_layout:fest',
        ];
    }

    $format = iss_graph_normalize_veranstaltung_format_for_offer(get_post_meta((int) $post->ID, '_iss_event_format', true));
    $format_map = [
        'vortrag' => 'lecture',
        'gespraech' => 'discussion',
        'lesung' => 'reading',
        'praesentation' => 'presentation',
        'repair_cafe' => 'repair_cafe',
        'concert' => 'concert',
        'festival' => 'festival',
        'workshop' => 'workshop',
        'school_program' => 'school_program',
        'general' => 'event',
    ];
    $subtype = (string) ($format_map[$format] ?? 'event');

    return [
        'kind' => 'offer',
        'subtype' => $subtype,
        'subtype_source' => '_iss_event_format:' . $format,
    ];
}

function iss_graph_get_contract_payload_for_post($post): array
{
    $post = $post instanceof WP_Post ? $post : get_post(absint($post));
    if (!$post instanceof WP_Post) {
        return [];
    }

    $storage_kind = function_exists('iss_graph_get_storage_entity_kind_for_post_type')
        ? iss_graph_get_storage_entity_kind_for_post_type((string) $post->post_type)
        : sanitize_key((string) $post->post_type);
    $canonical_kind = iss_graph_get_canonical_entity_kind($storage_kind);
    $contract_kind = iss_graph_get_entity_contract_kind($storage_kind);
    $subtype = iss_graph_get_entity_default_subtype($storage_kind);
    $subtype_source = $subtype !== '' ? 'entity_kind:' . $canonical_kind : '';

    if ($contract_kind === 'offer') {
        $offer_contract = iss_graph_get_offer_contract_for_post($post);
        if ($offer_contract) {
            $subtype = iss_graph_normalize_entity_kind_key((string) ($offer_contract['subtype'] ?? $subtype));
            $subtype_source = sanitize_text_field((string) ($offer_contract['subtype_source'] ?? $subtype_source));
        }
    }

    return [
        'kind' => $contract_kind,
        'subtype' => $subtype,
        'subtype_source' => $subtype_source,
        'canonical_kind' => $canonical_kind,
        'storage_kind' => $storage_kind,
    ];
}

function iss_graph_get_entity_contract_payload(array $entity): array
{
    $storage_kind = iss_graph_normalize_entity_kind_key((string) ($entity['entity_kind'] ?? ''));
    $canonical_kind = iss_graph_get_canonical_entity_kind($storage_kind);
    $contract_kind = iss_graph_get_entity_contract_kind($storage_kind);
    $subtype = iss_graph_get_entity_default_subtype($storage_kind);
    $subtype_source = $subtype !== '' ? 'entity_kind:' . $canonical_kind : '';

    $post_id = absint($entity['post_id'] ?? 0);
    if ($contract_kind === 'offer' && $post_id > 0) {
        $post_contract = iss_graph_get_contract_payload_for_post($post_id);
        if ($post_contract) {
            $subtype = iss_graph_normalize_entity_kind_key((string) ($post_contract['subtype'] ?? $subtype));
            $subtype_source = sanitize_text_field((string) ($post_contract['subtype_source'] ?? $subtype_source));
        }
    }

    return [
        'kind' => $contract_kind,
        'subtype' => $subtype,
        'subtype_source' => $subtype_source,
        'canonical_kind' => $canonical_kind,
        'storage_kind' => $storage_kind,
    ];
}

function iss_graph_get_public_object_post_type_contracts(): array
{
    $register_post_type = defined('ISS_REGISTER_POST_TYPE') ? sanitize_key((string) ISS_REGISTER_POST_TYPE) : 'register_place';
    $archive_object_post_type = defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_OBJECT_POST_TYPE) : 'archivobjekt';
    $contracts = [
        $register_post_type => [
            'canonical_kind' => 'place',
            'requires_subtype' => false,
        ],
        $archive_object_post_type => [
            'canonical_kind' => 'archive_object',
            'requires_subtype' => false,
        ],
        'ausstellung' => [
            'canonical_kind' => 'exhibition',
            'requires_subtype' => false,
        ],
        'publication' => [
            'canonical_kind' => 'publication',
            'requires_subtype' => false,
        ],
        'video' => [
            'canonical_kind' => 'video',
            'requires_subtype' => false,
        ],
        'projekt' => [
            'canonical_kind' => 'project',
            'requires_subtype' => false,
        ],
        'fuehrung' => [
            'canonical_kind' => 'tour',
            'requires_subtype' => true,
        ],
        'veranstaltung' => [
            'canonical_kind' => 'event',
            'requires_subtype' => true,
        ],
    ];

    $normalized = [];
    foreach ($contracts as $post_type => $contract) {
        $post_type = sanitize_key((string) $post_type);
        $canonical_kind = iss_graph_normalize_entity_kind_key((string) ($contract['canonical_kind'] ?? ''));
        $definition = $canonical_kind !== '' ? iss_graph_get_entity_kind_definition($canonical_kind) : null;
        if ($post_type === '' || !$definition) {
            continue;
        }

        $storage_kind = iss_graph_normalize_entity_kind_key((string) ($definition['storage_kind'] ?? $canonical_kind));
        $normalized[$post_type] = [
            'post_type' => $post_type,
            'canonical_kind' => $canonical_kind,
            'storage_kind' => $storage_kind,
            'contract_kind' => iss_graph_get_entity_contract_kind($storage_kind),
            'requires_subtype' => !empty($contract['requires_subtype']),
        ];
    }

    return (array) apply_filters('iss_graph_public_object_post_type_contracts', $normalized);
}
