<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_facade_rest_limit($value, int $default = 24, int $max = 100): int
{
    $limit = (int) $value;
    if ($limit <= 0) {
        return $default;
    }

    return min($limit, $max);
}

function iss_facade_rest_offset($value): int
{
    return max(0, (int) $value);
}

function iss_facade_rest_scalar_list($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/[\s,]+/', (string) $value) ?: [];
    }

    $items = array_map(static function ($item): string {
        return sanitize_key((string) $item);
    }, $items);

    return array_values(array_unique(array_filter($items)));
}

function iss_facade_rest_int_list($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/[\s,]+/', (string) $value) ?: [];
    }

    $items = array_map('absint', $items);

    return array_values(array_unique(array_filter($items)));
}

function iss_facade_rest_get_post_url(int $post_id): string
{
    if ($post_id <= 0) {
        return '';
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_status !== 'publish') {
        return '';
    }

    return (string) get_permalink($post);
}

function iss_facade_rest_entity_source_post_ids(int $entity_id): array
{
    $entity_id = absint($entity_id);
    if ($entity_id <= 0 || !function_exists('iss_graph_get_service')) {
        return [];
    }

    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return [];
    }

    $post_ids = [];
    foreach (['post_id', 'profile_post_id'] as $field) {
        $post_id = absint($entity[$field] ?? 0);
        if ($post_id > 0 && iss_facade_rest_get_post_url($post_id) !== '') {
            $post_ids[] = $post_id;
        }
    }

    return array_values(array_unique($post_ids));
}

function iss_facade_rest_public_entity_id_for_post(int $post_id): int
{
    $post_id = absint($post_id);
    if ($post_id <= 0 || !function_exists('iss_graph_get_entity_for_post')) {
        return 0;
    }

    $entity = iss_graph_get_entity_for_post($post_id);
    if (!is_array($entity) || !iss_facade_rest_entity_is_public($entity)) {
        return 0;
    }

    return absint($entity['id'] ?? 0);
}

function iss_facade_rest_merge_id_filter(array $existing_ids, array $incoming_ids): array
{
    $incoming_ids = array_values(array_unique(array_filter(array_map('absint', $incoming_ids))));
    if (empty($incoming_ids)) {
        return array_values(array_unique(array_filter(array_map('absint', $existing_ids))));
    }

    $existing_ids = array_values(array_unique(array_filter(array_map('absint', $existing_ids))));
    if (empty($existing_ids)) {
        return $incoming_ids;
    }

    return array_values(array_intersect($existing_ids, $incoming_ids));
}

function iss_facade_rest_entity_is_public(array $entity): bool
{
    if (empty($entity['is_public'])) {
        return false;
    }

    $post_id = absint($entity['post_id'] ?? 0);
    if ($post_id > 0 && iss_facade_rest_get_post_url($post_id) === '') {
        return false;
    }

    $profile_post_id = absint($entity['profile_post_id'] ?? 0);
    if ($post_id <= 0 && $profile_post_id > 0 && iss_facade_rest_get_post_url($profile_post_id) === '') {
        return false;
    }

    return true;
}

function iss_facade_rest_prepare_entity_kind_definition(array $definition): array
{
    $canonical_kind = (string) ($definition['canonical_kind'] ?? '');
    $storage_kind = (string) ($definition['storage_kind'] ?? $canonical_kind);
    $contract_kind = sanitize_key((string) ($definition['contract_kind'] ?? $canonical_kind));

    return [
        'kind' => $canonical_kind,
        'canonical_kind' => $canonical_kind,
        'storage_kind' => $storage_kind,
        'contract_kind' => $contract_kind !== '' ? $contract_kind : $canonical_kind,
        'default_subtype' => sanitize_key((string) ($definition['default_subtype'] ?? '')),
        'subtypes' => array_values(array_map('sanitize_key', (array) ($definition['subtypes'] ?? []))),
        'label' => isset($definition['label']) && is_scalar($definition['label']) ? (string) $definition['label'] : $canonical_kind,
        'owner' => (string) ($definition['owner'] ?? ''),
        'post_types' => array_values(array_map('sanitize_key', (array) ($definition['post_types'] ?? []))),
        'aliases' => array_values(array_map('sanitize_key', (array) ($definition['aliases'] ?? []))),
        'public' => !empty($definition['public']),
    ];
}

function iss_facade_rest_prepare_offer_subtype_definition(string $subtype, array $definition): array
{
    return [
        'subtype' => sanitize_key($subtype),
        'label' => isset($definition['label']) && is_scalar($definition['label']) ? (string) $definition['label'] : $subtype,
        'public_label' => isset($definition['public_label']) && is_scalar($definition['public_label']) ? (string) $definition['public_label'] : '',
        'source' => isset($definition['source']) && is_scalar($definition['source']) ? (string) $definition['source'] : '',
    ];
}

function iss_facade_rest_normalize_relation_direction($value): string
{
    $direction = sanitize_key((string) $value);

    return in_array($direction, ['outgoing', 'incoming', 'both'], true) ? $direction : 'outgoing';
}

function iss_facade_rest_entity_relation_filters_from_request(WP_REST_Request $request): array
{
    $family = sanitize_key((string) (
        $request->get_param('family')
        ?: $request->get_param('relation_family')
        ?: ''
    ));

    return [
        'direction' => iss_facade_rest_normalize_relation_direction($request->get_param('direction') ?: 'outgoing'),
        'family' => $family,
        'source_system' => sanitize_key((string) $request->get_param('source_system')),
        'limit' => iss_facade_rest_limit($request->get_param('limit'), 24, 100),
    ];
}

function iss_facade_rest_prepare_entity_relation(array $relation, string $direction = 'outgoing'): ?array
{
    $related_post_id = absint($relation['post_id'] ?? 0);
    $related_profile_post_id = absint($relation['profile_post_id'] ?? 0);
    $permalink = '';
    if ($related_post_id > 0) {
        $permalink = iss_facade_rest_get_post_url($related_post_id);
    } elseif ($related_profile_post_id > 0) {
        $permalink = iss_facade_rest_get_post_url($related_profile_post_id);
    }

    if (($related_post_id > 0 || $related_profile_post_id > 0) && $permalink === '') {
        return null;
    }

    $storage_kind = sanitize_key((string) ($relation['entity_kind'] ?? ''));
    $canonical_kind = function_exists('iss_graph_get_canonical_entity_kind')
        ? iss_graph_get_canonical_entity_kind($storage_kind)
        : $storage_kind;
    $source_system = sanitize_key((string) ($relation['source_system'] ?? ''));
    $source_ref = sanitize_text_field((string) ($relation['source_ref'] ?? ''));

    return [
        'relation_id' => (int) ($relation['id'] ?? 0),
        'direction' => iss_facade_rest_normalize_relation_direction($direction),
        'entity_id' => (int) ($relation['entity_id'] ?? 0),
        'kind' => $canonical_kind,
        'canonical_kind' => $canonical_kind,
        'storage_kind' => $storage_kind,
        'name' => (string) ($relation['name'] ?? ''),
        'slug' => (string) ($relation['slug'] ?? ''),
        'url' => $permalink !== '' ? $permalink : esc_url_raw((string) ($relation['permalink'] ?? '')),
        'family' => (string) ($relation['relation_family'] ?? ''),
        'type' => (string) ($relation['relation_type'] ?? ''),
        'role' => (string) ($relation['relation_role'] ?? ''),
        'label' => (string) ($relation['relation_label'] ?? ''),
        'primary' => !empty($relation['is_primary']),
        'valid_from_year' => $relation['valid_from_year'] ?? null,
        'valid_to_year' => $relation['valid_to_year'] ?? null,
        'source' => [
            'system' => $source_system,
            'ref' => $source_ref,
        ],
    ];
}

function iss_facade_rest_get_entity_relations_response(int $entity_id, array $filters): array
{
    $entity_id = absint($entity_id);
    $direction = iss_facade_rest_normalize_relation_direction($filters['direction'] ?? 'outgoing');
    $family = sanitize_key((string) ($filters['family'] ?? ''));
    $source_system = sanitize_key((string) ($filters['source_system'] ?? ''));
    $limit = iss_facade_rest_limit($filters['limit'] ?? 24, 24, 100);
    $query_args = [
        'public_only' => true,
        'limit' => $limit,
    ];
    if ($source_system !== '') {
        $query_args['source_system'] = $source_system;
    }

    $items = [];
    $service = iss_graph_get_service();

    if ($direction === 'outgoing' || $direction === 'both') {
        $rows = $service->get_relations_for_entity($entity_id, $family, $query_args);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $item = iss_facade_rest_prepare_entity_relation($row, 'outgoing');
            if ($item !== null) {
                $items[] = $item;
            }
        }
    }

    if ($direction === 'incoming' || $direction === 'both') {
        $incoming_args = $query_args;
        if ($family !== '') {
            $incoming_args['relation_family'] = $family;
        }

        $rows = $service->get_incoming_relations_for_entity($entity_id, '', $incoming_args);
        foreach (is_array($rows) ? $rows : [] as $row) {
            $item = iss_facade_rest_prepare_entity_relation($row, 'incoming');
            if ($item !== null) {
                $items[] = $item;
            }
        }
    }

    return [
        'entity_id' => $entity_id,
        'filters' => [
            'direction' => $direction,
            'family' => $family,
            'source_system' => $source_system,
            'limit' => $limit,
        ],
        'count' => count($items),
        'items' => $items,
    ];
}

function iss_facade_rest_prepare_entity(array $entity, bool $include_details = false): ?array
{
    if (!iss_facade_rest_entity_is_public($entity)) {
        return null;
    }

    $entity_id = (int) ($entity['id'] ?? 0);
    $storage_kind = sanitize_key((string) ($entity['entity_kind'] ?? ''));
    $canonical_kind = function_exists('iss_graph_get_canonical_entity_kind')
        ? iss_graph_get_canonical_entity_kind($storage_kind)
        : $storage_kind;
    $contract = function_exists('iss_graph_get_entity_contract_payload')
        ? iss_graph_get_entity_contract_payload($entity)
        : [
            'kind' => $canonical_kind,
            'subtype' => '',
            'subtype_source' => '',
            'canonical_kind' => $canonical_kind,
            'storage_kind' => $storage_kind,
        ];
    $post_id = absint($entity['post_id'] ?? 0);
    $profile_post_id = absint($entity['profile_post_id'] ?? 0);
    $url = iss_facade_rest_get_post_url($post_id);
    $profile_url = iss_facade_rest_get_post_url($profile_post_id);
    $post = $post_id > 0 ? get_post($post_id) : null;

    $item = [
        'id' => $entity_id,
        'kind' => $canonical_kind,
        'canonical_kind' => $canonical_kind,
        'storage_kind' => $storage_kind,
        'contract_kind' => sanitize_key((string) ($contract['kind'] ?? $canonical_kind)),
        'subtype' => sanitize_key((string) ($contract['subtype'] ?? '')),
        'contract' => [
            'kind' => sanitize_key((string) ($contract['kind'] ?? $canonical_kind)),
            'subtype' => sanitize_key((string) ($contract['subtype'] ?? '')),
            'subtype_source' => sanitize_text_field((string) ($contract['subtype_source'] ?? '')),
            'canonical_kind' => sanitize_key((string) ($contract['canonical_kind'] ?? $canonical_kind)),
            'storage_kind' => sanitize_key((string) ($contract['storage_kind'] ?? $storage_kind)),
        ],
        'title' => (string) ($entity['display_title'] ?? ''),
        'summary' => (string) ($entity['summary'] ?? ''),
        'slug' => (string) ($entity['canonical_slug'] ?? ''),
        'url' => $url !== '' ? $url : $profile_url,
        'post_id' => $post_id,
        'post_type' => $post instanceof WP_Post ? (string) $post->post_type : '',
        'profile_post_id' => $profile_post_id,
        'profile_url' => $profile_url,
    ];

    if (!$include_details) {
        return $item;
    }

    $names = array_map(static function (array $name): array {
        return [
            'name' => (string) ($name['name'] ?? ''),
            'type' => (string) ($name['name_type'] ?? ''),
            'language' => (string) ($name['language'] ?? ''),
            'primary' => !empty($name['is_primary']),
            'valid_from_year' => $name['valid_from_year'] ?? null,
            'valid_to_year' => $name['valid_to_year'] ?? null,
        ];
    }, function_exists('iss_graph_get_entity_names') ? iss_graph_get_entity_names($entity_id, ['limit' => 100]) : []);

    $identifiers = array_map(static function (array $identifier): array {
        return [
            'namespace' => (string) ($identifier['namespace'] ?? ''),
            'value' => (string) ($identifier['value'] ?? ''),
            'url' => (string) ($identifier['url'] ?? ''),
            'label' => (string) ($identifier['label'] ?? ''),
            'primary' => !empty($identifier['is_primary']),
        ];
    }, function_exists('iss_graph_get_entity_identifiers') ? iss_graph_get_entity_identifiers($entity_id, [
        'status' => 'accepted',
        'limit' => 100,
    ]) : []);

    $relations = array_values(array_filter(array_map(static function (array $relation): ?array {
        return iss_facade_rest_prepare_entity_relation($relation, 'outgoing');
    }, function_exists('iss_graph_get_entity_relations') ? iss_graph_get_entity_relations($entity_id, [
        'public_only' => true,
        'limit' => 100,
    ]) : [])));

    $item['names'] = array_values(array_filter($names, static function (array $name): bool {
        return trim((string) ($name['name'] ?? '')) !== '';
    }));
    $item['identifiers'] = array_values(array_filter($identifiers, static function (array $identifier): bool {
        return trim((string) ($identifier['namespace'] ?? '')) !== '' && trim((string) ($identifier['value'] ?? '')) !== '';
    }));
    $item['relations'] = $relations;

    return $item;
}

function iss_facade_rest_entity_kind_from_request(WP_REST_Request $request): string
{
    $kind = (string) (
        $request->get_param('kind')
        ?: $request->get_param('entity_kind')
        ?: $request->get_param('canonical_kind')
        ?: ''
    );

    return function_exists('iss_graph_get_storage_entity_kind')
        ? iss_graph_get_storage_entity_kind($kind)
        : sanitize_key($kind);
}

function iss_facade_rest_list_entities(WP_REST_Request $request)
{
    $query = iss_graph_normalize_public_search_query((string) ($request->get_param('q') ?: $request->get_param('search') ?: ''));
    $limit = iss_facade_rest_limit($request->get_param('limit'), 24, 100);
    $offset = iss_facade_rest_offset($request->get_param('offset'));
    $entity_kind = iss_facade_rest_entity_kind_from_request($request);
    $kinds = iss_facade_rest_scalar_list($request->get_param('kinds') ?: '');
    $storage_kinds = [];

    foreach ($kinds as $kind) {
        $storage_kind = function_exists('iss_graph_get_storage_entity_kind') ? iss_graph_get_storage_entity_kind($kind) : sanitize_key($kind);
        if ($storage_kind !== '') {
            $storage_kinds[] = $storage_kind;
        }
    }

    $args = [
        'limit' => $limit,
        'public_only' => true,
    ];

    if ($entity_kind !== '') {
        $args['entity_kind'] = $entity_kind;
    }
    if ($storage_kinds) {
        $args['entity_kinds'] = array_values(array_unique($storage_kinds));
    }

    if ($query !== '') {
        $rows = iss_graph_search_entities(array_merge($args, ['query' => $query]));
    } else {
        $args['offset'] = $offset;
        $args['orderby'] = sanitize_key((string) ($request->get_param('orderby') ?: 'display_title'));
        $args['order'] = strtoupper((string) ($request->get_param('order') ?: 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $rows = iss_graph_get_service()->get_entities($args);
    }

    $items = array_values(array_filter(array_map(static function (array $entity): ?array {
        return iss_facade_rest_prepare_entity($entity, false);
    }, is_array($rows) ? $rows : [])));

    return rest_ensure_response([
        'query' => $query,
        'kind' => $entity_kind,
        'limit' => $limit,
        'offset' => $offset,
        'count' => count($items),
        'items' => $items,
    ]);
}

function iss_facade_rest_get_entity(WP_REST_Request $request)
{
    $entity_id = absint($request->get_param('id'));
    if ($entity_id <= 0) {
        return new WP_Error(
            'iss_facade_invalid_entity_id',
            __('Provide a valid entity id.', 'iss-graph'),
            ['status' => 400]
        );
    }

    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return new WP_Error(
            'iss_facade_entity_not_found',
            __('Entity not found.', 'iss-graph'),
            ['status' => 404]
        );
    }

    return rest_ensure_response([
        'item' => iss_facade_rest_prepare_entity($entity, true),
    ]);
}

function iss_facade_rest_list_entity_relations(WP_REST_Request $request)
{
    $entity_id = absint($request->get_param('id'));
    if ($entity_id <= 0) {
        return new WP_Error(
            'iss_facade_invalid_entity_id',
            __('Provide a valid entity id.', 'iss-graph'),
            ['status' => 400]
        );
    }

    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return new WP_Error(
            'iss_facade_entity_not_found',
            __('Entity not found.', 'iss-graph'),
            ['status' => 404]
        );
    }

    return rest_ensure_response(iss_facade_rest_get_entity_relations_response(
        $entity_id,
        iss_facade_rest_entity_relation_filters_from_request($request)
    ));
}

function iss_facade_rest_get_public_contract_payload_for_post(int $post_id): array
{
    if ($post_id <= 0 || !function_exists('iss_graph_get_contract_payload_for_post')) {
        return [];
    }

    $contract = iss_graph_get_contract_payload_for_post($post_id);
    if (!is_array($contract) || empty($contract['kind'])) {
        return [];
    }

    return [
        'kind' => sanitize_key((string) ($contract['kind'] ?? '')),
        'subtype' => sanitize_key((string) ($contract['subtype'] ?? '')),
        'subtype_source' => sanitize_text_field((string) ($contract['subtype_source'] ?? '')),
        'canonical_kind' => sanitize_key((string) ($contract['canonical_kind'] ?? '')),
        'storage_kind' => sanitize_key((string) ($contract['storage_kind'] ?? '')),
    ];
}

function iss_facade_rest_prepare_occurrence(array $occurrence): array
{
    $source_post_id = absint($occurrence['source_post_id'] ?? 0);
    $source_post_type = sanitize_key((string) ($occurrence['source_post_type'] ?? ''));
    $location_post_id = absint($occurrence['location_post_id'] ?? 0);
    $contract = iss_facade_rest_get_public_contract_payload_for_post($source_post_id);

    return [
        'id' => (int) ($occurrence['id'] ?? 0),
        'entity_id' => iss_facade_rest_public_entity_id_for_post($source_post_id),
        'kind' => sanitize_key((string) ($occurrence['type'] ?? $occurrence['kind'] ?? '')),
        'contract_kind' => sanitize_key((string) ($contract['kind'] ?? 'occurrence')),
        'subtype' => sanitize_key((string) ($contract['subtype'] ?? '')),
        'contract' => $contract,
        'title' => (string) ($occurrence['title'] ?? ''),
        'summary' => (string) ($occurrence['summary'] ?? ''),
        'starts_at' => (string) ($occurrence['start_raw'] ?? $occurrence['date_raw'] ?? ''),
        'ends_at' => (string) ($occurrence['end_raw'] ?? ''),
        'date_label' => (string) ($occurrence['date_label'] ?? ''),
        'day_label' => (string) ($occurrence['day_label'] ?? ''),
        'time_label' => (string) ($occurrence['time_label'] ?? ''),
        'datetime_label' => (string) ($occurrence['datetime_label'] ?? ''),
        'date_source' => sanitize_key((string) ($occurrence['date_source'] ?? '')),
        'series_key' => (string) ($occurrence['series_key'] ?? ''),
        'booking_url' => esc_url_raw((string) ($occurrence['booking_url'] ?? '')),
        'availability_state' => sanitize_key((string) ($occurrence['availability_state'] ?? $occurrence['availability'] ?? '')),
        'capacity_available' => $occurrence['available'] ?? null,
        'capacity_total' => $occurrence['capacity'] ?? null,
        'tag' => (string) ($occurrence['tag'] ?? ''),
        'source' => [
            'post_id' => $source_post_id,
            'post_type' => $source_post_type,
            'url' => iss_facade_rest_get_post_url($source_post_id),
        ],
        'location' => [
            'post_id' => $location_post_id,
            'entity_id' => iss_facade_rest_public_entity_id_for_post($location_post_id),
            'label' => (string) ($occurrence['location_label'] ?? ''),
        ],
        'schema' => [
            'kind' => 'occurrence',
            'type' => 'Event',
            'emits_event_schema' => true,
        ],
    ];
}

function iss_facade_rest_occurrence_filters_from_request(WP_REST_Request $request): array
{
    $time_mode = sanitize_key((string) ($request->get_param('time_mode') ?: 'upcoming'));
    $filters = [
        'limit' => iss_facade_rest_limit($request->get_param('limit'), 24, 100),
        'offset' => iss_facade_rest_offset($request->get_param('offset')),
        'order' => strtoupper((string) ($request->get_param('order') ?: 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
        'time_mode' => in_array($time_mode, ['all', 'upcoming', 'past', 'month', 'range'], true) ? $time_mode : 'upcoming',
        'include_running_ranges' => rest_sanitize_boolean($request->get_param('include_running_ranges')),
    ];

    $item_types = iss_facade_rest_scalar_list($request->get_param('kind') ?: $request->get_param('type') ?: $request->get_param('item_type') ?: '');
    $item_types = array_merge($item_types, iss_facade_rest_scalar_list($request->get_param('kinds') ?: $request->get_param('types') ?: $request->get_param('item_types') ?: ''));
    if ($item_types) {
        $filters['item_types'] = array_values(array_unique($item_types));
    }

    $post_types = iss_facade_rest_scalar_list($request->get_param('post_type') ?: $request->get_param('post_types') ?: '');
    if ($post_types) {
        $filters['post_types'] = $post_types;
    }

    $source_post_ids = iss_facade_rest_int_list($request->get_param('source_post_id') ?: $request->get_param('source_post_ids') ?: '');
    if ($source_post_ids) {
        $filters['source_post_ids'] = $source_post_ids;
    }

    $entity_ids = iss_facade_rest_int_list($request->get_param('entity_id') ?: $request->get_param('entity_ids') ?: '');
    if ($entity_ids) {
        $entity_source_post_ids = [];
        foreach ($entity_ids as $entity_id) {
            $entity_source_post_ids = array_merge($entity_source_post_ids, iss_facade_rest_entity_source_post_ids($entity_id));
        }
        $filters['source_post_ids'] = iss_facade_rest_merge_id_filter(
            isset($filters['source_post_ids']) && is_array($filters['source_post_ids']) ? $filters['source_post_ids'] : [],
            $entity_source_post_ids ?: [-1]
        );
    }

    $location_post_ids = iss_facade_rest_int_list($request->get_param('location_post_id') ?: $request->get_param('location_post_ids') ?: '');
    if ($location_post_ids) {
        $filters['location_post_ids'] = $location_post_ids;
    }

    $location_entity_ids = iss_facade_rest_int_list($request->get_param('location_entity_id') ?: $request->get_param('location_entity_ids') ?: '');
    if ($location_entity_ids) {
        $location_source_post_ids = [];
        foreach ($location_entity_ids as $location_entity_id) {
            $location_source_post_ids = array_merge($location_source_post_ids, iss_facade_rest_entity_source_post_ids($location_entity_id));
        }
        $filters['location_post_ids'] = iss_facade_rest_merge_id_filter(
            isset($filters['location_post_ids']) && is_array($filters['location_post_ids']) ? $filters['location_post_ids'] : [],
            $location_source_post_ids ?: [-1]
        );
    }

    $month = preg_replace('/[^0-9\-]/', '', (string) $request->get_param('month'));
    if (is_string($month) && $month !== '') {
        $filters['month'] = $month;
        $filters['time_mode'] = 'month';
    }

    $date_start = sanitize_text_field((string) ($request->get_param('date_start') ?: $request->get_param('from') ?: ''));
    $date_end = sanitize_text_field((string) ($request->get_param('date_end') ?: $request->get_param('to') ?: ''));
    if ($date_start !== '' || $date_end !== '') {
        $filters['date_start'] = $date_start;
        $filters['date_end'] = $date_end;
        $filters['time_mode'] = 'range';
    }

    $origin = sanitize_key((string) $request->get_param('origin'));
    if ($origin !== '') {
        $filters['origin'] = $origin;
    }

    $tag = strtoupper(sanitize_text_field((string) $request->get_param('tag')));
    if ($tag !== '') {
        $filters['tag'] = $tag;
    }

    return $filters;
}

function iss_facade_rest_get_occurrences_response(array $filters, int $entity_id = 0): array
{
    $rows = iss_occurrences_query($filters);
    $items = array_values(array_map('iss_facade_rest_prepare_occurrence', is_array($rows) ? $rows : []));
    $payload = [
        'filters' => [
            'limit' => (int) $filters['limit'],
            'offset' => (int) $filters['offset'],
            'order' => (string) $filters['order'],
            'time_mode' => (string) $filters['time_mode'],
        ],
        'count' => count($items),
        'items' => $items,
    ];

    if ($entity_id > 0) {
        $payload = array_merge([
            'entity_id' => $entity_id,
        ], $payload);
        $payload['filters']['entity_id'] = $entity_id;
    }

    return $payload;
}

function iss_facade_rest_list_occurrences(WP_REST_Request $request)
{
    if (!function_exists('iss_occurrences_query')) {
        return new WP_Error(
            'iss_facade_occurrences_unavailable',
            __('The occurrence query service is unavailable.', 'iss-graph'),
            ['status' => 501]
        );
    }

    $filters = iss_facade_rest_occurrence_filters_from_request($request);

    return rest_ensure_response(iss_facade_rest_get_occurrences_response($filters));
}

function iss_facade_rest_list_entity_occurrences(WP_REST_Request $request)
{
    if (!function_exists('iss_occurrences_query')) {
        return new WP_Error(
            'iss_facade_occurrences_unavailable',
            __('The occurrence query service is unavailable.', 'iss-graph'),
            ['status' => 501]
        );
    }

    $entity_id = absint($request->get_param('id'));
    if ($entity_id <= 0) {
        return new WP_Error(
            'iss_facade_invalid_entity_id',
            __('Provide a valid entity id.', 'iss-graph'),
            ['status' => 400]
        );
    }

    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return new WP_Error(
            'iss_facade_entity_not_found',
            __('Entity not found.', 'iss-graph'),
            ['status' => 404]
        );
    }

    $filters = iss_facade_rest_occurrence_filters_from_request($request);
    $filters['source_post_ids'] = iss_facade_rest_entity_source_post_ids($entity_id) ?: [-1];

    return rest_ensure_response(iss_facade_rest_get_occurrences_response($filters, $entity_id));
}

function iss_facade_rest_search(WP_REST_Request $request)
{
    if (!function_exists('iss_search_rest_search')) {
        return new WP_Error(
            'iss_facade_search_unavailable',
            __('The search service is unavailable.', 'iss-graph'),
            ['status' => 501]
        );
    }

    return iss_search_rest_search($request);
}

function iss_facade_rest_contract(WP_REST_Request $request): WP_REST_Response
{
    $registry = function_exists('iss_graph_get_entity_kind_registry') ? iss_graph_get_entity_kind_registry() : [];
    $entity_kinds = array_values(array_map('iss_facade_rest_prepare_entity_kind_definition', $registry));
    $offer_subtypes = [];
    $offer_registry = function_exists('iss_graph_get_offer_subtype_registry') ? iss_graph_get_offer_subtype_registry() : [];
    foreach ($offer_registry as $subtype => $definition) {
        if (!is_array($definition)) {
            continue;
        }

        $offer_subtypes[] = iss_facade_rest_prepare_offer_subtype_definition((string) $subtype, $definition);
    }

    $routes = [
        '/iss/v1/contract',
        '/iss/v1/entities',
        '/iss/v1/entities/{id}',
        '/iss/v1/entities/{id}/relations',
        '/iss/v1/entities/{id}/occurrences',
        '/iss/v1/occurrences',
        '/iss/v1/search',
    ];

    if (function_exists('iss_timeline_rest_render_collection')) {
        $routes[] = '/iss/v1/timeline';
    }
    if (function_exists('iss_programm_rest_list_availability')) {
        $routes[] = '/iss/v1/availability';
    }
    if (function_exists('iss_occurrences_get_booking_slots_rest')) {
        $routes[] = '/iss/v1/booking-slots';
    }

    return new WP_REST_Response([
        'namespace' => 'iss/v1',
        'version' => 1,
        'read_only' => true,
        'providers' => [
            'graph' => function_exists('iss_graph_get_service'),
            'search' => function_exists('iss_search_public_posts'),
            'occurrences' => function_exists('iss_occurrences_query'),
            'timeline' => function_exists('iss_timeline_rest_render_collection'),
            'availability' => function_exists('iss_programm_rest_list_availability'),
            'booking_slots' => function_exists('iss_occurrences_get_booking_slots_rest'),
        ],
        'routes' => $routes,
        'entity_kinds' => $entity_kinds,
        'offer_subtypes' => $offer_subtypes,
    ]);
}

function iss_facade_rest_get_public_route_args(): array
{
    return [
        'limit' => [
            'type' => 'integer',
            'required' => false,
            'validate_callback' => static function ($value): bool {
                return $value === null || ((int) $value >= 1 && (int) $value <= 100);
            },
        ],
        'offset' => [
            'type' => 'integer',
            'required' => false,
            'validate_callback' => static function ($value): bool {
                return $value === null || (int) $value >= 0;
            },
        ],
    ];
}

function iss_facade_rest_get_entity_relations_route_args(): array
{
    return array_merge(iss_facade_rest_get_public_route_args(), [
        'id' => [
            'type' => 'integer',
            'required' => true,
        ],
        'direction' => [
            'type' => 'string',
            'required' => false,
            'enum' => ['outgoing', 'incoming', 'both'],
        ],
        'family' => [
            'type' => 'string',
            'required' => false,
            'sanitize_callback' => 'sanitize_key',
        ],
        'relation_family' => [
            'type' => 'string',
            'required' => false,
            'sanitize_callback' => 'sanitize_key',
        ],
        'source_system' => [
            'type' => 'string',
            'required' => false,
            'sanitize_callback' => 'sanitize_key',
        ],
    ]);
}

function iss_facade_rest_get_entity_occurrences_route_args(): array
{
    return array_merge(iss_facade_rest_get_public_route_args(), [
        'id' => [
            'type' => 'integer',
            'required' => true,
        ],
    ]);
}

function iss_facade_rest_register_routes(): void
{
    register_rest_route('iss/v1', '/contract', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_facade_rest_contract',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('iss/v1', '/entities', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_facade_rest_list_entities',
        'permission_callback' => '__return_true',
        'args' => iss_facade_rest_get_public_route_args(),
    ]);

    register_rest_route('iss/v1', '/entities/(?P<id>\d+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_facade_rest_get_entity',
        'permission_callback' => '__return_true',
        'args' => [
            'id' => [
                'type' => 'integer',
                'required' => true,
            ],
        ],
    ]);

    register_rest_route('iss/v1', '/entities/(?P<id>\d+)/relations', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_facade_rest_list_entity_relations',
        'permission_callback' => '__return_true',
        'args' => iss_facade_rest_get_entity_relations_route_args(),
    ]);

    register_rest_route('iss/v1', '/entities/(?P<id>\d+)/occurrences', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_facade_rest_list_entity_occurrences',
        'permission_callback' => '__return_true',
        'args' => iss_facade_rest_get_entity_occurrences_route_args(),
    ]);

    register_rest_route('iss/v1', '/occurrences', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_facade_rest_list_occurrences',
        'permission_callback' => '__return_true',
        'args' => iss_facade_rest_get_public_route_args(),
    ]);

    register_rest_route('iss/v1', '/search', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_facade_rest_search',
        'permission_callback' => '__return_true',
        'args' => function_exists('iss_search_rest_get_route_args') ? iss_search_rest_get_route_args() : iss_facade_rest_get_public_route_args(),
    ]);
}

add_action('rest_api_init', 'iss_facade_rest_register_routes');
