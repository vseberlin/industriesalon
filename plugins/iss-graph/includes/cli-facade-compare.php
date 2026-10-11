<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Existing CLI verification reads owner graph tables.

function iss_graph_wpcli_facade_parse_search_queries($value): array
{
    $raw_queries = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['salon', 'schoeneweide', 'ausstellung'];

    $queries = [];
    foreach ((array) $raw_queries as $query) {
        $query = iss_graph_normalize_public_search_query((string) $query);
        if ($query !== '') {
            $queries[] = $query;
        }
    }

    return array_values(array_unique($queries));
}

function iss_graph_wpcli_facade_search_signature(array $data): array
{
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    return [
        'query' => (string) ($data['query'] ?? ''),
        'provider' => (string) ($data['provider'] ?? ''),
        'count' => (int) ($data['count'] ?? 0),
        'items' => array_values(array_map(static function (array $item): array {
            return [
                'id' => (int) ($item['id'] ?? 0),
                'result_kind' => (string) ($item['result_kind'] ?? ''),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'type' => (string) ($item['type'] ?? ''),
                'type_label' => (string) ($item['type_label'] ?? ''),
                'contract_kind' => (string) ($item['contract_kind'] ?? ''),
                'subtype' => (string) ($item['subtype'] ?? ''),
                'post_type' => (string) ($item['post_type'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'relevance' => (int) ($item['relevance'] ?? 0),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_search_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_search_rest_search')) {
        WP_CLI::error('Search REST service is unavailable.');
    }

    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $queries = iss_graph_wpcli_facade_parse_search_queries($assoc_args['queries'] ?? '');
    if (!$queries) {
        WP_CLI::error('Provide at least one search query through --queries or use the defaults.');
    }

    $errors = [];
    foreach ($queries as $query) {
        $params = [
            'q' => $query,
            'limit' => $limit,
        ];
        $legacy = iss_graph_wpcli_direct_rest_callback('search service', 'iss_search_rest_search', '/iss/v1/search', $params, $errors);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/search', $params, $errors, 'facade');
        $legacy_signature = iss_graph_wpcli_facade_search_signature($legacy);
        $facade_signature = iss_graph_wpcli_facade_search_signature($facade);

        if ($legacy_signature !== $facade_signature) {
            $errors[] = sprintf('Search facade mismatch for query "%s".', $query);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] search q="%s" provider=%s count=%d matched',
            $query,
            (string) ($facade_signature['provider'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade search comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade search comparison passed for %d querie(s).', count($queries)));
}

function iss_graph_wpcli_facade_parse_occurrence_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['upcoming', 'all', 'event'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_occurrence_params_for_scenario(string $scenario, int $limit): array
{
    $params = [
        'limit' => $limit,
    ];

    switch ($scenario) {
        case 'all':
            $params['time_mode'] = 'all';
            break;
        case 'event':
        case 'events':
        case 'veranstaltung':
        case 'veranstaltungen':
            $params['time_mode'] = 'all';
            $params['kind'] = 'event';
            break;
        case 'tour':
        case 'tours':
        case 'fuehrung':
        case 'fuehrungen':
            $params['time_mode'] = 'upcoming';
            $params['kind'] = 'tour';
            break;
        case 'exhibition':
        case 'exhibitions':
        case 'ausstellung':
        case 'ausstellungen':
            $params['time_mode'] = 'all';
            $params['kind'] = 'exhibition';
            break;
        case 'project':
        case 'projects':
        case 'projekt':
        case 'projekte':
            $params['time_mode'] = 'all';
            $params['kind'] = 'project';
            break;
        case 'upcoming':
        default:
            $params['time_mode'] = 'upcoming';
            break;
    }

    return $params;
}

function iss_graph_wpcli_facade_occurrence_direct_response(array $params): array
{
    if (!function_exists('iss_occurrences_query') || !function_exists('iss_facade_rest_occurrence_filters_from_request')) {
        return [];
    }

    $request = new WP_REST_Request('GET', '/iss/v1/occurrences');
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    $filters = iss_facade_rest_occurrence_filters_from_request($request);

    return iss_facade_rest_get_occurrences_response($filters);
}

function iss_graph_wpcli_facade_occurrence_signature(array $data): array
{
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];

    return [
        'filters' => [
            'limit' => (int) ($filters['limit'] ?? 0),
            'offset' => (int) ($filters['offset'] ?? 0),
            'order' => (string) ($filters['order'] ?? ''),
            'time_mode' => (string) ($filters['time_mode'] ?? ''),
        ],
        'count' => (int) ($data['count'] ?? 0),
        'items' => array_values(array_map(static function (array $item): array {
            $source = isset($item['source']) && is_array($item['source']) ? $item['source'] : [];
            $location = isset($item['location']) && is_array($item['location']) ? $item['location'] : [];
            $schema = isset($item['schema']) && is_array($item['schema']) ? $item['schema'] : [];

            return [
                'id' => (int) ($item['id'] ?? 0),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
                'contract_kind' => (string) ($item['contract_kind'] ?? ''),
                'subtype' => (string) ($item['subtype'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'starts_at' => (string) ($item['starts_at'] ?? ''),
                'ends_at' => (string) ($item['ends_at'] ?? ''),
                'date_source' => (string) ($item['date_source'] ?? ''),
                'series_key' => (string) ($item['series_key'] ?? ''),
                'booking_url' => (string) ($item['booking_url'] ?? ''),
                'availability_state' => (string) ($item['availability_state'] ?? ''),
                'capacity_available' => $item['capacity_available'] ?? null,
                'capacity_total' => $item['capacity_total'] ?? null,
                'tag' => (string) ($item['tag'] ?? ''),
                'source_post_id' => (int) ($source['post_id'] ?? 0),
                'source_post_type' => (string) ($source['post_type'] ?? ''),
                'source_url' => (string) ($source['url'] ?? ''),
                'location_entity_id' => (int) ($location['entity_id'] ?? 0),
                'location_label' => (string) ($location['label'] ?? ''),
                'schema_kind' => (string) ($schema['kind'] ?? ''),
                'schema_type' => (string) ($schema['type'] ?? ''),
                'emits_event_schema' => (bool) ($schema['emits_event_schema'] ?? false),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_occurrences_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_occurrences_query')) {
        WP_CLI::error('Occurrence query service is unavailable.');
    }

    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $scenarios = iss_graph_wpcli_facade_parse_occurrence_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one occurrence comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_occurrence_params_for_scenario($scenario, $limit);
        $direct = iss_graph_wpcli_facade_occurrence_direct_response($params);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/occurrences', $params, $errors, 'facade');
        $direct_signature = iss_graph_wpcli_facade_occurrence_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_occurrence_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Occurrence facade mismatch for scenario "%s".', $scenario);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] occurrences scenario="%s" time_mode=%s count=%d matched',
            $scenario,
            (string) ($facade_signature['filters']['time_mode'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade occurrence comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade occurrence comparison passed for %d scenario(s).', count($scenarios)));
}

function iss_graph_wpcli_facade_find_occurrence_entity_id(array $params): int
{
    $params['limit'] = 100;
    $direct = iss_graph_wpcli_facade_occurrence_direct_response($params);
    $items = isset($direct['items']) && is_array($direct['items']) ? $direct['items'] : [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $entity_id = absint($item['entity_id'] ?? 0);
        if ($entity_id <= 0) {
            continue;
        }

        $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
        if ($entity && iss_facade_rest_entity_is_public($entity)) {
            return $entity_id;
        }
    }

    return 0;
}

function iss_graph_wpcli_facade_entity_occurrence_direct_response(int $entity_id, array $params): array
{
    if (!function_exists('iss_occurrences_query') || !function_exists('iss_facade_rest_occurrence_filters_from_request')) {
        return [];
    }

    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return [];
    }

    $request = new WP_REST_Request('GET', sprintf('/iss/v1/entities/%d/occurrences', $entity_id));
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    $filters = iss_facade_rest_occurrence_filters_from_request($request);
    $filters['source_post_ids'] = function_exists('iss_facade_rest_entity_source_post_ids')
        ? (iss_facade_rest_entity_source_post_ids($entity_id) ?: [-1])
        : [-1];

    return iss_facade_rest_get_occurrences_response($filters, $entity_id);
}

function iss_graph_wpcli_facade_entity_occurrence_signature(array $data): array
{
    $signature = iss_graph_wpcli_facade_occurrence_signature($data);
    $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];
    $signature['entity_id'] = (int) ($data['entity_id'] ?? 0);
    $signature['filters']['entity_id'] = (int) ($filters['entity_id'] ?? 0);

    return $signature;
}

function iss_graph_wpcli_facade_entity_occurrences_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_occurrences_query')) {
        WP_CLI::error('Occurrence query service is unavailable.');
    }

    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $requested_entity_id = absint($assoc_args['entity_id'] ?? 0);
    $scenarios = iss_graph_wpcli_facade_parse_occurrence_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one entity-occurrence comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    $compared = 0;
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_occurrence_params_for_scenario($scenario, $limit);
        $entity_id = $requested_entity_id > 0
            ? $requested_entity_id
            : iss_graph_wpcli_facade_find_occurrence_entity_id($params);

        if ($entity_id <= 0) {
            WP_CLI::warning(sprintf('Skipping entity-occurrences scenario "%s"; no public occurrence-backed entity found.', $scenario));
            continue;
        }

        $path = sprintf('/iss/v1/entities/%d/occurrences', $entity_id);
        $direct = iss_graph_wpcli_facade_entity_occurrence_direct_response($entity_id, $params);
        $facade = iss_graph_wpcli_facade_request($path, $params, $errors, 'facade');
        $direct_signature = iss_graph_wpcli_facade_entity_occurrence_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_entity_occurrence_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Entity-occurrences facade mismatch for scenario "%s" entity %d.', $scenario, $entity_id);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] entity-occurrences scenario="%s" entity=%d time_mode=%s count=%d matched',
            $scenario,
            $entity_id,
            (string) ($facade_signature['filters']['time_mode'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
        $compared++;
    }

    if ($compared <= 0 && !$errors) {
        WP_CLI::error('No entity-occurrence comparison scenarios were run.');
    }
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade entity-occurrence comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade entity-occurrence comparison passed for %d scenario(s).', $compared));
}

function iss_graph_wpcli_facade_parse_availability_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['aktuell', 'dauer', 'digital', 'archiv', 'aktuell-search'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_availability_params_for_scenario(string $scenario, int $limit): array
{
    $search = '';
    if (substr($scenario, -7) === '-search') {
        $scenario = substr($scenario, 0, -7);
        $search = 'Werk';
    }

    $filter = function_exists('iss_programm_ausstellungen_normalize_filter')
        ? iss_programm_ausstellungen_normalize_filter($scenario)
        : sanitize_key($scenario);

    $params = [
        'kind' => 'exhibition',
        'filter' => $filter,
        'limit' => $limit,
        'show_meta' => '1',
        'show_summary' => '1',
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    return $params;
}

function iss_graph_wpcli_facade_availability_signature(array $data): array
{
    $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    return [
        'provider' => (string) ($data['provider'] ?? ''),
        'kind' => (string) ($data['kind'] ?? ''),
        'storage_kind' => (string) ($data['storage_kind'] ?? ''),
        'filters' => [
            'kind' => (string) ($filters['kind'] ?? ''),
            'filter' => (string) ($filters['filter'] ?? ''),
            'limit' => (int) ($filters['limit'] ?? 0),
            'search' => (string) ($filters['search'] ?? ''),
            'today' => (string) ($filters['today'] ?? ''),
        ],
        'count' => (int) ($data['count'] ?? 0),
        'html_hash' => md5((string) ($data['html'] ?? '')),
        'is_empty' => (bool) ($data['is_empty'] ?? false),
        'items' => array_values(array_map(static function (array $item): array {
            $type = isset($item['type']) && is_array($item['type']) ? $item['type'] : [];
            $availability = isset($item['availability']) && is_array($item['availability']) ? $item['availability'] : [];
            $editorial = isset($item['editorial']) && is_array($item['editorial']) ? $item['editorial'] : [];
            $schema = isset($item['schema']) && is_array($item['schema']) ? $item['schema'] : [];
            $source = isset($item['source']) && is_array($item['source']) ? $item['source'] : [];

            return [
                'id' => (int) ($item['id'] ?? 0),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
                'storage_kind' => (string) ($item['storage_kind'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'type_slugs' => isset($type['slugs']) && is_array($type['slugs']) ? array_values($type['slugs']) : [],
                'type_label' => (string) ($type['label'] ?? ''),
                'state' => (string) ($availability['state'] ?? ''),
                'starts_at' => (string) ($availability['starts_at'] ?? ''),
                'ends_at' => (string) ($availability['ends_at'] ?? ''),
                'period_label' => (string) ($availability['period_label'] ?? ''),
                'editorial_signal' => (string) ($editorial['signal'] ?? ''),
                'schema_kind' => (string) ($schema['kind'] ?? ''),
                'schema_type' => (string) ($schema['type'] ?? ''),
                'emits_event_schema' => (bool) ($schema['emits_event_schema'] ?? true),
                'source_post_id' => (int) ($source['post_id'] ?? 0),
                'source_post_type' => (string) ($source['post_type'] ?? ''),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_availability_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_programm_rest_list_availability')) {
        WP_CLI::error('Programme availability REST service is unavailable.');
    }

    $limit = max(1, min(24, absint($assoc_args['limit'] ?? 5)));
    $scenarios = iss_graph_wpcli_facade_parse_availability_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one availability comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_availability_params_for_scenario($scenario, $limit);
        $direct = iss_graph_wpcli_direct_rest_callback('availability service', 'iss_programm_rest_list_availability', '/iss/v1/availability', $params, $errors);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/availability', $params, $errors, 'facade');
        iss_graph_wpcli_facade_require_keys('availability service', $direct, ['provider', 'kind', 'storage_kind', 'filters', 'count', 'items', 'html', 'is_empty'], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/availability', $facade, ['provider', 'kind', 'storage_kind', 'filters', 'count', 'items', 'html', 'is_empty'], $errors);

        $direct_signature = iss_graph_wpcli_facade_availability_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_availability_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Availability facade mismatch for scenario "%s".', $scenario);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] availability scenario="%s" filter=%s search="%s" count=%d matched',
            $scenario,
            (string) ($facade_signature['filters']['filter'] ?? ''),
            (string) ($facade_signature['filters']['search'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade availability comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade availability comparison passed for %d scenario(s).', count($scenarios)));
}

function iss_graph_wpcli_facade_parse_timeline_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['upcoming', 'month', 'event'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_timeline_params_for_scenario(string $scenario, int $limit): array
{
    $params = [
        'limit' => $limit,
        'order' => 'ASC',
        'render' => [
            'renderMode' => 'timeline',
            'yearGrouping' => true,
            'groupRecurringTours' => true,
        ],
        'filters' => [
            'time_mode' => 'upcoming',
        ],
    ];

    switch ($scenario) {
        case 'all':
            $params['filters']['time_mode'] = 'all';
            break;
        case 'month':
            $params['filters']['time_mode'] = 'month';
            $params['filters']['month'] = current_time('Y-m');
            break;
        case 'event':
        case 'events':
        case 'veranstaltung':
        case 'veranstaltungen':
            $params['filters']['time_mode'] = 'all';
            $params['filters']['item_type'] = 'event';
            break;
        case 'tour':
        case 'tours':
        case 'fuehrung':
        case 'fuehrungen':
            $params['filters']['time_mode'] = 'upcoming';
            $params['filters']['item_type'] = 'tour';
            break;
        case 'cards':
            $params['render']['renderMode'] = 'cards';
            $params['render']['yearGrouping'] = false;
            break;
        case 'upcoming':
        default:
            break;
    }

    return $params;
}

function iss_graph_wpcli_facade_timeline_signature(array $data): array
{
    $html = (string) ($data['html'] ?? '');

    return [
        'html_hash' => md5($html),
        'html_length' => strlen($html),
        'count' => (int) ($data['count'] ?? 0),
        'batchCount' => (int) ($data['batchCount'] ?? 0),
        'isEmpty' => (bool) ($data['isEmpty'] ?? false),
        'offset' => (int) ($data['offset'] ?? 0),
        'nextOffset' => (int) ($data['nextOffset'] ?? 0),
        'hasMore' => (bool) ($data['hasMore'] ?? false),
    ];
}

function iss_graph_wpcli_facade_timeline_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_timeline_rest_render_collection')) {
        WP_CLI::error('Programme timeline REST service is unavailable.');
    }

    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $scenarios = iss_graph_wpcli_facade_parse_timeline_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one timeline comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_timeline_params_for_scenario($scenario, $limit);
        $legacy = iss_graph_wpcli_direct_rest_callback('timeline service', 'iss_timeline_rest_render_collection', '/iss/v1/timeline', $params, $errors);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/timeline', $params, $errors, 'facade');
        iss_graph_wpcli_facade_require_keys('timeline service', $legacy, ['html', 'count', 'batchCount', 'isEmpty', 'offset', 'nextOffset', 'hasMore'], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/timeline', $facade, ['html', 'count', 'batchCount', 'isEmpty', 'offset', 'nextOffset', 'hasMore'], $errors);

        $legacy_signature = iss_graph_wpcli_facade_timeline_signature($legacy);
        $facade_signature = iss_graph_wpcli_facade_timeline_signature($facade);

        if ($legacy_signature !== $facade_signature) {
            $errors[] = sprintf('Timeline facade mismatch for scenario "%s".', $scenario);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] timeline scenario="%s" count=%d html=%s matched',
            $scenario,
            (int) ($facade_signature['count'] ?? 0),
            (string) ($facade_signature['html_hash'] ?? '')
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade timeline comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade timeline comparison passed for %d scenario(s).', count($scenarios)));
}

function iss_graph_wpcli_facade_parse_tour_slot_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['tag', 'nomap'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_tour_slot_params_for_scenario(string $scenario, string $tag, int $post_id): array
{
    switch ($scenario) {
        case 'post':
        case 'post_id':
            return $post_id > 0 ? ['post_id' => $post_id] : [];
        case 'nomap':
        case 'missing':
            return [];
        case 'tag':
        default:
            return $tag !== '' ? ['tag' => $tag] : [];
    }
}

function iss_graph_wpcli_facade_tour_slots_signature(array $data): array
{
    $slots = isset($data['slots']) && is_array($data['slots']) ? $data['slots'] : [];

    return [
        'source' => (string) ($data['source'] ?? ''),
        'slots' => array_values(array_map(static function (array $slot): array {
            return [
                'id' => (string) ($slot['id'] ?? ''),
                'title' => (string) ($slot['title'] ?? ''),
                'start' => (string) ($slot['start'] ?? ''),
                'end' => $slot['end'] ?? null,
                'capacity' => $slot['capacity'] ?? null,
                'available' => $slot['available'] ?? null,
                'booking_url' => $slot['booking_url'] ?? null,
                'content_url' => $slot['content_url'] ?? null,
            ];
        }, array_filter($slots, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_tour_slots_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_occurrences_get_booking_slots_rest')) {
        WP_CLI::error('Booking slots REST service is unavailable.');
    }

    $tag = strtoupper(sanitize_text_field((string) ($assoc_args['tag'] ?? 'ELEKTRO')));
    $post_id = absint($assoc_args['post_id'] ?? 0);
    $scenarios = iss_graph_wpcli_facade_parse_tour_slot_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
            WP_CLI::error('Provide at least one booking-slot comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    $compared = 0;
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_tour_slot_params_for_scenario($scenario, $tag, $post_id);
        if (in_array($scenario, ['post', 'post_id'], true) && $post_id <= 0) {
            WP_CLI::warning(sprintf('Skipping booking-slot scenario "%s"; provide --post_id to compare it.', $scenario));
            continue;
        }
        if ($scenario === 'tag' && $tag === '') {
            $errors[] = 'Booking-slot scenario "tag" requires a non-empty --tag value.';
            continue;
        }

        $legacy = iss_graph_wpcli_direct_rest_callback('booking-slot service', 'iss_occurrences_get_booking_slots_rest', '/iss/v1/booking-slots', $params, $errors);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/booking-slots', $params, $errors, 'facade');
        iss_graph_wpcli_facade_require_keys('booking-slot service', $legacy, ['source', 'slots'], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/booking-slots', $facade, ['source', 'slots'], $errors);

        $legacy_signature = iss_graph_wpcli_facade_tour_slots_signature($legacy);
        $facade_signature = iss_graph_wpcli_facade_tour_slots_signature($facade);

        if ($legacy_signature !== $facade_signature) {
            $errors[] = sprintf('Booking slots facade mismatch for scenario "%s".', $scenario);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] booking-slots scenario="%s" source=%s slots=%d matched',
            $scenario,
            (string) ($facade_signature['source'] ?? ''),
            count($facade_signature['slots'] ?? [])
        ));
        $compared++;
    }

    if ($compared <= 0 && !$errors) {
        WP_CLI::error('No booking-slot comparison scenarios were run.');
    }
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade booking-slot comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade booking-slot comparison passed for %d scenario(s).', $compared));
}

function iss_graph_wpcli_facade_parse_entity_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['list', 'archive_object', 'search'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_entity_params_for_scenario(string $scenario, int $limit, string $search): array
{
    $params = [
        'limit' => $limit,
    ];

    switch ($scenario) {
        case 'place':
        case 'places':
            $params['kind'] = 'place';
            break;
        case 'archive':
        case 'archive_object':
        case 'archive_objects':
            $params['kind'] = 'archive_object';
            break;
        case 'event':
        case 'events':
            $params['kind'] = 'event';
            break;
        case 'tour':
        case 'tours':
            $params['kind'] = 'tour';
            break;
        case 'exhibition':
        case 'exhibitions':
            $params['kind'] = 'exhibition';
            break;
        case 'project':
        case 'projects':
            $params['kind'] = 'project';
            break;
        case 'search':
            $params['q'] = $search;
            break;
        case 'list':
        default:
            break;
    }

    return $params;
}

function iss_graph_wpcli_facade_entity_storage_kinds_from_params(array $params): array
{
    $storage_kinds = [];
    $kind = sanitize_key((string) ($params['kind'] ?? $params['entity_kind'] ?? $params['canonical_kind'] ?? ''));
    if ($kind !== '') {
        $storage_kind = function_exists('iss_graph_get_storage_entity_kind') ? iss_graph_get_storage_entity_kind($kind) : $kind;
        if ($storage_kind !== '') {
            $storage_kinds[] = $storage_kind;
        }
    }

    foreach (iss_facade_rest_scalar_list($params['kinds'] ?? '') as $raw_kind) {
        $storage_kind = function_exists('iss_graph_get_storage_entity_kind') ? iss_graph_get_storage_entity_kind($raw_kind) : $raw_kind;
        if ($storage_kind !== '') {
            $storage_kinds[] = $storage_kind;
        }
    }

    return array_values(array_unique($storage_kinds));
}

function iss_graph_wpcli_facade_entity_direct_list_response(array $params): array
{
    $limit = iss_facade_rest_limit($params['limit'] ?? 24, 24, 100);
    $offset = iss_facade_rest_offset($params['offset'] ?? 0);
    $query = iss_graph_normalize_public_search_query((string) ($params['q'] ?? $params['search'] ?? ''));
    $storage_kinds = iss_graph_wpcli_facade_entity_storage_kinds_from_params($params);

    $args = [
        'limit' => $limit,
        'public_only' => true,
    ];
    if (count($storage_kinds) === 1) {
        $args['entity_kind'] = $storage_kinds[0];
    } elseif ($storage_kinds) {
        $args['entity_kinds'] = $storage_kinds;
    }

    if ($query !== '') {
        $rows = iss_graph_search_entities(array_merge($args, ['query' => $query]));
    } else {
        $args['offset'] = $offset;
        $args['orderby'] = sanitize_key((string) ($params['orderby'] ?? 'display_title'));
        $args['order'] = strtoupper((string) ($params['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $rows = iss_graph_get_service()->get_entities($args);
    }

    $items = array_values(array_filter(array_map(static function (array $entity): ?array {
        return iss_facade_rest_prepare_entity($entity, false);
    }, is_array($rows) ? $rows : [])));

    return [
        'query' => $query,
        'kind' => $storage_kinds[0] ?? '',
        'limit' => $limit,
        'offset' => $offset,
        'count' => count($items),
        'items' => $items,
    ];
}

function iss_graph_wpcli_facade_entity_direct_detail_response(int $entity_id): array
{
    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return [];
    }

    return [
        'item' => iss_facade_rest_prepare_entity($entity, true),
    ];
}

function iss_graph_wpcli_facade_entity_list_signature(array $data): array
{
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    return [
        'query' => (string) ($data['query'] ?? ''),
        'kind' => (string) ($data['kind'] ?? ''),
        'limit' => (int) ($data['limit'] ?? 0),
        'offset' => (int) ($data['offset'] ?? 0),
        'count' => (int) ($data['count'] ?? 0),
        'items' => array_values(array_map(static function (array $item): array {
            return [
                'id' => (int) ($item['id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
                'storage_kind' => (string) ($item['storage_kind'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'post_id' => (int) ($item['post_id'] ?? 0),
                'post_type' => (string) ($item['post_type'] ?? ''),
                'profile_post_id' => (int) ($item['profile_post_id'] ?? 0),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_entity_detail_signature(array $data): array
{
    $item = isset($data['item']) && is_array($data['item']) ? $data['item'] : [];

    return [
        'item' => [
            'id' => (int) ($item['id'] ?? 0),
            'kind' => (string) ($item['kind'] ?? ''),
            'storage_kind' => (string) ($item['storage_kind'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'slug' => (string) ($item['slug'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'post_id' => (int) ($item['post_id'] ?? 0),
            'post_type' => (string) ($item['post_type'] ?? ''),
            'names' => isset($item['names']) && is_array($item['names']) ? $item['names'] : [],
            'identifiers' => isset($item['identifiers']) && is_array($item['identifiers']) ? $item['identifiers'] : [],
            'relations' => isset($item['relations']) && is_array($item['relations']) ? $item['relations'] : [],
        ],
    ];
}

function iss_graph_wpcli_facade_entities_compare_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $search = iss_graph_normalize_public_search_query((string) ($assoc_args['search'] ?? 'salon'));
    if ($search === '') {
        $search = 'salon';
    }

    $scenarios = iss_graph_wpcli_facade_parse_entity_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one entity comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_entity_params_for_scenario($scenario, $limit, $search);
        $direct = iss_graph_wpcli_facade_entity_direct_list_response($params);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/entities', $params, $errors, 'facade');
        $direct_signature = iss_graph_wpcli_facade_entity_list_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_entity_list_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Entity facade list mismatch for scenario "%s".', $scenario);
            continue;
        }

        $entity_id = absint($facade_signature['items'][0]['id'] ?? 0);
        if ($entity_id > 0) {
            $detail_path = sprintf('/iss/v1/entities/%d', $entity_id);
            $direct_detail = iss_graph_wpcli_facade_entity_direct_detail_response($entity_id);
            $facade_detail = iss_graph_wpcli_facade_request($detail_path, [], $errors, 'facade');
            if (iss_graph_wpcli_facade_entity_detail_signature($direct_detail) !== iss_graph_wpcli_facade_entity_detail_signature($facade_detail)) {
                $errors[] = sprintf('Entity facade detail mismatch for scenario "%s" entity %d.', $scenario, $entity_id);
                continue;
            }
        }

        WP_CLI::log(sprintf(
            '[compare] entities scenario="%s" count=%d detail_entity=%d matched',
            $scenario,
            (int) ($facade_signature['count'] ?? 0),
            $entity_id
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade entity comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade entity comparison passed for %d scenario(s).', count($scenarios)));
}

function iss_graph_wpcli_facade_parse_entity_relation_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['outgoing', 'incoming', 'organization'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_entity_relation_params_for_scenario(string $scenario, int $limit): array
{
    $params = [
        'limit' => $limit,
        'direction' => 'outgoing',
    ];

    switch ($scenario) {
        case 'incoming':
            $params['direction'] = 'incoming';
            break;
        case 'both':
            $params['direction'] = 'both';
            break;
        case 'place':
        case 'places':
            $params['family'] = 'place';
            break;
        case 'person':
        case 'people':
        case 'persons':
            $params['family'] = 'person';
            break;
        case 'organization':
        case 'organizations':
        case 'organisation':
        case 'organisations':
            $params['family'] = 'organization';
            break;
        case 'outgoing':
        default:
            break;
    }

    return $params;
}

function iss_graph_wpcli_facade_find_relation_entity_id(string $direction = 'outgoing', string $family = ''): int
{
    global $wpdb;

    $service = iss_graph_get_service();
    $relation_table = $service->get_relation_table_name();
    $entity_table = $service->get_entity_table_name();
    $direction = iss_facade_rest_normalize_relation_direction($direction);
    $family = sanitize_key($family);
    $entity_column = $direction === 'incoming' ? 'r.to_entity_id' : 'r.from_entity_id';
    $where = ['r.is_public = 1', 'e.is_public = 1'];
    $values = [];
    if ($family !== '') {
        $where[] = 'r.relation_family = %s';
        $values[] = $family;
    }

    $values[] = 200;
    $sql = "SELECT {$entity_column} AS entity_id,
            MIN(r.id) AS relation_id
        FROM {$relation_table} r
        INNER JOIN {$entity_table} e
            ON e.id = {$entity_column}
        WHERE " . implode(' AND ', $where) . "
        GROUP BY {$entity_column}
        ORDER BY relation_id ASC
        LIMIT %d";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names and the selected id column are fixed by this command; values are prepared above.
    $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    foreach (is_array($rows) ? $rows : [] as $row) {
        $entity_id = absint($row['entity_id'] ?? 0);
        if ($entity_id <= 0) {
            continue;
        }

        $entity = $service->get_entity_by_id($entity_id);
        if ($entity && iss_facade_rest_entity_is_public($entity)) {
            return $entity_id;
        }
    }

    return 0;
}

function iss_graph_wpcli_facade_entity_relations_direct_response(int $entity_id, array $params): array
{
    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return [];
    }

    $request = new WP_REST_Request('GET', sprintf('/iss/v1/entities/%d/relations', $entity_id));
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    return iss_facade_rest_get_entity_relations_response(
        $entity_id,
        iss_facade_rest_entity_relation_filters_from_request($request)
    );
}

function iss_graph_wpcli_facade_entity_relations_signature(array $data): array
{
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];

    return [
        'entity_id' => (int) ($data['entity_id'] ?? 0),
        'filters' => [
            'direction' => (string) ($filters['direction'] ?? ''),
            'family' => (string) ($filters['family'] ?? ''),
            'source_system' => (string) ($filters['source_system'] ?? ''),
            'limit' => (int) ($filters['limit'] ?? 0),
        ],
        'count' => (int) ($data['count'] ?? 0),
        'items' => array_values(array_map(static function (array $item): array {
            $source = isset($item['source']) && is_array($item['source']) ? $item['source'] : [];

            return [
                'relation_id' => (int) ($item['relation_id'] ?? 0),
                'direction' => (string) ($item['direction'] ?? ''),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
                'canonical_kind' => (string) ($item['canonical_kind'] ?? ''),
                'storage_kind' => (string) ($item['storage_kind'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'family' => (string) ($item['family'] ?? ''),
                'type' => (string) ($item['type'] ?? ''),
                'role' => (string) ($item['role'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'primary' => (bool) ($item['primary'] ?? false),
                'valid_from_year' => $item['valid_from_year'] ?? null,
                'valid_to_year' => $item['valid_to_year'] ?? null,
                'source_system' => (string) ($source['system'] ?? ''),
                'source_ref' => (string) ($source['ref'] ?? ''),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_entity_relations_compare_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $requested_entity_id = absint($assoc_args['entity_id'] ?? 0);
    $scenarios = iss_graph_wpcli_facade_parse_entity_relation_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one entity-relation comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    $compared = 0;
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_entity_relation_params_for_scenario($scenario, $limit);
        $direction = (string) ($params['direction'] ?? 'outgoing');
        $family = (string) ($params['family'] ?? '');
        $lookup_direction = $direction === 'incoming' ? 'incoming' : 'outgoing';
        $entity_id = $requested_entity_id > 0
            ? $requested_entity_id
            : iss_graph_wpcli_facade_find_relation_entity_id($lookup_direction, $family);

        if ($entity_id <= 0) {
            WP_CLI::warning(sprintf('Skipping entity-relations scenario "%s"; no public relation-backed entity found.', $scenario));
            continue;
        }

        $path = sprintf('/iss/v1/entities/%d/relations', $entity_id);
        $direct = iss_graph_wpcli_facade_entity_relations_direct_response($entity_id, $params);
        $facade = iss_graph_wpcli_facade_request($path, $params, $errors, 'facade');
        $direct_signature = iss_graph_wpcli_facade_entity_relations_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_entity_relations_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Entity-relations facade mismatch for scenario "%s" entity %d.', $scenario, $entity_id);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] entity-relations scenario="%s" entity=%d direction=%s family=%s count=%d matched',
            $scenario,
            $entity_id,
            (string) ($facade_signature['filters']['direction'] ?? ''),
            (string) ($facade_signature['filters']['family'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
        $compared++;
    }

    if ($compared <= 0 && !$errors) {
        WP_CLI::error('No entity-relation comparison scenarios were run.');
    }
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade entity-relation comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade entity-relation comparison passed for %d scenario(s).', $compared));
}
