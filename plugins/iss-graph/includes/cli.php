<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- CLI verification reads counts from plugin-owned graph tables.

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-graph verify', 'iss_graph_wpcli_verify_command');
    WP_CLI::add_command('iss-graph drift-check', 'iss_graph_wpcli_drift_check_command');
    WP_CLI::add_command('iss-graph facade-check', 'iss_graph_wpcli_facade_check_command');
    WP_CLI::add_command('iss-graph facade-search-compare', 'iss_graph_wpcli_facade_search_compare_command');
    WP_CLI::add_command('iss-graph facade-occurrences-compare', 'iss_graph_wpcli_facade_occurrences_compare_command');
    WP_CLI::add_command('iss-graph facade-entities-compare', 'iss_graph_wpcli_facade_entities_compare_command');
    WP_CLI::add_command('iss-graph facade-timeline-compare', 'iss_graph_wpcli_facade_timeline_compare_command');
    WP_CLI::add_command('iss-graph migrate', 'iss_graph_wpcli_migrate_command');
    WP_CLI::add_command('iss-graph sync-register', 'iss_graph_wpcli_sync_register_command');
    WP_CLI::add_command('iss-graph sync-content', 'iss_graph_wpcli_sync_content_command');
    WP_CLI::add_command('iss-graph sync-profiles', 'iss_graph_wpcli_sync_profiles_command');
    WP_CLI::add_command('iss-graph sync-video-transcripts', 'iss_graph_wpcli_sync_video_transcripts_command');
    WP_CLI::add_command('iss-graph sync-archive', 'iss_graph_wpcli_sync_archive_command');
    WP_CLI::add_command('iss-graph sync-aliases', 'iss_graph_wpcli_sync_aliases_command');
    WP_CLI::add_command('iss-graph sync-search', 'iss_graph_wpcli_sync_search_command');
    WP_CLI::add_command('iss-graph import-enrichment', 'iss_graph_wpcli_import_enrichment_command');
}

function iss_graph_wpcli_verify_command(array $args, array $assoc_args): void
{
    global $wpdb;

    $service = iss_graph_get_service();
    $tables = [
        'entity_index' => $service->get_entity_table_name(),
        'entity_names' => $service->get_name_table_name(),
        'entity_identifiers' => $service->get_identifier_table_name(),
        'entity_relations' => $service->get_relation_table_name(),
        'search_index' => $service->get_search_table_name(),
        'person_facts' => $service->get_person_facts_table_name(),
        'organization_facts' => $service->get_organization_facts_table_name(),
        'entity_evidence_refs' => $service->get_evidence_table_name(),
        'editorial_signals' => $service->get_editorial_signal_table_name(),
    ];

    $failed = false;
    foreach ($tables as $label => $table_name) {
        $exists = $service->table_exists($table_name);
        if ($exists) {
            WP_CLI::log(sprintf('[ok] %s %s', $label, $table_name));
            continue;
        }

        $failed = true;
        WP_CLI::warning(sprintf('%s missing: %s', $label, $table_name));
    }

    $entity_count = $service->table_exists($service->get_entity_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_entity_table_name()}")
        : 0;
    $name_count = $service->table_exists($service->get_name_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_name_table_name()}")
        : 0;
    $relation_count = $service->table_exists($service->get_relation_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_relation_table_name()}")
        : 0;
    $identifier_count = $service->table_exists($service->get_identifier_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_identifier_table_name()}")
        : 0;
    $search_count = $service->table_exists($service->get_search_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_search_table_name()}")
        : 0;
    $person_facts_count = $service->table_exists($service->get_person_facts_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_person_facts_table_name()}")
        : 0;
    $organization_facts_count = $service->table_exists($service->get_organization_facts_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_organization_facts_table_name()}")
        : 0;
    $evidence_count = $service->table_exists($service->get_evidence_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_evidence_table_name()}")
        : 0;
    $editorial_signal_count = $service->table_exists($service->get_editorial_signal_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_editorial_signal_table_name()}")
        : 0;

    WP_CLI::log(sprintf(
        'entities=%d names=%d identifiers=%d relations=%d search=%d person_facts=%d organization_facts=%d evidence_refs=%d editorial_signals=%d',
        $entity_count,
        $name_count,
        $identifier_count,
        $relation_count,
        $search_count,
        $person_facts_count,
        $organization_facts_count,
        $evidence_count,
        $editorial_signal_count
    ));

    if ($failed) {
        WP_CLI::error('ISS graph verification failed.');
    }

    WP_CLI::success('ISS graph verification passed.');
}

function iss_graph_wpcli_drift_check_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(500, absint($assoc_args['limit'] ?? 50)));
    $checks = iss_graph_wpcli_parse_drift_checks((string) ($assoc_args['checks'] ?? ''));
    $errors = [];
    $stats = [];

    foreach ($checks as $check) {
        $result = iss_graph_wpcli_run_drift_check($check, $limit);
        $stats[$check] = (int) ($result['checked'] ?? 0);
        foreach ((array) ($result['errors'] ?? []) as $error) {
            if (is_string($error) && $error !== '') {
                $errors[] = $error;
            }
        }
    }

    foreach ($stats as $check => $checked) {
        WP_CLI::log(sprintf('[drift] %s checked=%d', $check, $checked));
    }

    if ($errors) {
        WP_CLI::error_multi_line(array_slice($errors, 0, $limit));
        $remaining = count($errors) - $limit;
        if ($remaining > 0) {
            WP_CLI::warning(sprintf('%d additional drift issues hidden by --limit=%d.', $remaining, $limit));
        }
        WP_CLI::error(sprintf('ISS graph drift check failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph drift check passed.');
}

function iss_graph_wpcli_facade_response_count(array $data): string
{
    if (isset($data['count'])) {
        return (string) (int) $data['count'];
    }

    if (isset($data['entity_kinds']) && is_array($data['entity_kinds'])) {
        return (string) count($data['entity_kinds']);
    }

    if (isset($data['item']) && is_array($data['item'])) {
        return '1';
    }

    return 'n/a';
}

function iss_graph_wpcli_facade_request(string $path, array $params, array &$errors, string $label = 'facade'): array
{
    $request = new WP_REST_Request('GET', $path);
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    $response = rest_do_request($request);
    if ($response->is_error()) {
        $error = $response->as_error();
        $errors[] = sprintf('%s returned REST error: %s', $path, $error ? $error->get_error_message() : 'unknown error');
        WP_CLI::log(sprintf('[%s] %s status=error count=n/a', $label, $path));

        return [];
    }

    $status = (int) $response->get_status();
    $data = $response->get_data();
    if ($status !== 200) {
        $errors[] = sprintf('%s returned status %d, expected 200.', $path, $status);
    }
    if (!is_array($data)) {
        $errors[] = sprintf('%s returned a non-object response.', $path);
        $data = [];
    }

    WP_CLI::log(sprintf('[%s] %s status=%d count=%s', $label, $path, $status, iss_graph_wpcli_facade_response_count($data)));

    return $data;
}

function iss_graph_wpcli_facade_require_keys(string $context, array $data, array $keys, array &$errors): void
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            $errors[] = sprintf('%s is missing key: %s.', $context, (string) $key);
        }
    }
}

function iss_graph_wpcli_facade_check_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(10, absint($assoc_args['limit'] ?? 2)));
    $search = iss_graph_normalize_public_search_query((string) ($assoc_args['search'] ?? 'salon'));
    if ($search === '') {
        $search = 'salon';
    }

    $errors = [];
    $contract = iss_graph_wpcli_facade_request('/iss/v1/contract', [], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/contract', $contract, ['namespace', 'version', 'read_only', 'providers', 'routes', 'entity_kinds'], $errors);
    if (($contract['namespace'] ?? '') !== 'iss/v1') {
        $errors[] = '/iss/v1/contract namespace is not iss/v1.';
    }
    if (empty($contract['read_only'])) {
        $errors[] = '/iss/v1/contract does not report read_only=true.';
    }
    if (empty($contract['entity_kinds']) || !is_array($contract['entity_kinds'])) {
        $errors[] = '/iss/v1/contract has no entity_kinds list.';
    }
    if (function_exists('iss_timeline_rest_render_collection')) {
        $routes = isset($contract['routes']) && is_array($contract['routes']) ? $contract['routes'] : [];
        if (!in_array('/iss/v1/timeline', $routes, true)) {
            $errors[] = '/iss/v1/contract does not advertise the timeline facade route.';
        }
    }

    $entities = iss_graph_wpcli_facade_request('/iss/v1/entities', ['limit' => $limit], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/entities', $entities, ['count', 'items'], $errors);
    $entity_items = isset($entities['items']) && is_array($entities['items']) ? $entities['items'] : [];
    if (!$entity_items) {
        $errors[] = '/iss/v1/entities returned no public entity items to detail-check.';
    }

    $entity_id = 0;
    $first_entity = $entity_items[0] ?? null;
    if (is_array($first_entity)) {
        iss_graph_wpcli_facade_require_keys('/iss/v1/entities item', $first_entity, ['id', 'kind', 'storage_kind', 'title', 'url'], $errors);
        $entity_id = absint($first_entity['id'] ?? 0);
    }

    if ($entity_id > 0) {
        $detail_path = sprintf('/iss/v1/entities/%d', $entity_id);
        $detail = iss_graph_wpcli_facade_request($detail_path, [], $errors);
        iss_graph_wpcli_facade_require_keys($detail_path, $detail, ['item'], $errors);
        $item = isset($detail['item']) && is_array($detail['item']) ? $detail['item'] : [];
        iss_graph_wpcli_facade_require_keys($detail_path . ' item', $item, ['id', 'kind', 'names', 'identifiers', 'relations'], $errors);
        foreach (['names', 'identifiers', 'relations'] as $list_key) {
            if (isset($item[$list_key]) && !is_array($item[$list_key])) {
                $errors[] = sprintf('%s item key %s is not a list.', $detail_path, $list_key);
            }
        }
    }

    $occurrences = iss_graph_wpcli_facade_request('/iss/v1/occurrences', ['limit' => $limit], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/occurrences', $occurrences, ['count', 'items'], $errors);
    $occurrence_items = isset($occurrences['items']) && is_array($occurrences['items']) ? $occurrences['items'] : [];
    if (isset($occurrence_items[0]) && is_array($occurrence_items[0])) {
        iss_graph_wpcli_facade_require_keys('/iss/v1/occurrences item', $occurrence_items[0], ['id', 'entity_id', 'kind', 'title', 'starts_at', 'source', 'location'], $errors);
    }

    $search_results = iss_graph_wpcli_facade_request('/iss/v1/search', ['q' => $search, 'limit' => $limit], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/search', $search_results, ['query', 'provider', 'count', 'items'], $errors);
    $search_items = isset($search_results['items']) && is_array($search_results['items']) ? $search_results['items'] : [];
    if (isset($search_items[0]) && is_array($search_items[0])) {
        iss_graph_wpcli_facade_require_keys('/iss/v1/search item', $search_items[0], ['id', 'type', 'title', 'url'], $errors);
    }

    if (function_exists('iss_timeline_rest_render_collection')) {
        $timeline = iss_graph_wpcli_facade_request('/iss/v1/timeline', [
            'limit' => $limit,
            'filters' => [
                'time_mode' => 'upcoming',
            ],
        ], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/timeline', $timeline, ['html', 'count', 'batchCount', 'isEmpty', 'offset', 'nextOffset', 'hasMore'], $errors);
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade check failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph facade check passed.');
}

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
                'type' => (string) ($item['type'] ?? ''),
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
        $legacy = iss_graph_wpcli_facade_request('/iss-search/v1/search', $params, $errors, 'legacy');
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
    $rows = iss_occurrences_query($filters);
    $items = array_values(array_map('iss_facade_rest_prepare_occurrence', is_array($rows) ? $rows : []));

    return [
        'filters' => [
            'limit' => (int) $filters['limit'],
            'offset' => (int) $filters['offset'],
            'order' => (string) $filters['order'],
            'time_mode' => (string) $filters['time_mode'],
        ],
        'count' => count($items),
        'items' => $items,
    ];
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

            return [
                'id' => (int) ($item['id'] ?? 0),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
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
        $legacy = iss_graph_wpcli_facade_request('/iss-programm/v1/timeline', $params, $errors, 'legacy');
        $facade = iss_graph_wpcli_facade_request('/iss/v1/timeline', $params, $errors, 'facade');
        iss_graph_wpcli_facade_require_keys('/iss-programm/v1/timeline', $legacy, ['html', 'count', 'batchCount', 'isEmpty', 'offset', 'nextOffset', 'hasMore'], $errors);
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

function iss_graph_wpcli_parse_drift_checks(string $value): array
{
    $available = [
        'post-identifiers',
        'register-identifiers',
        'content-identifiers',
        'archive-identifiers',
        'place-taxonomy',
        'place-graph',
        'search-index',
        'editorial-signals',
        'entity-kind-contract',
    ];

    $value = trim($value);
    if ($value === '') {
        return $available;
    }

    $requested = array_values(array_filter(array_map('sanitize_key', preg_split('/[\s,]+/', $value) ?: [])));

    return array_values(array_intersect($available, $requested));
}

function iss_graph_wpcli_run_drift_check(string $check, int $limit): array
{
    switch ($check) {
        case 'post-identifiers':
            return iss_graph_wpcli_check_post_identifiers($limit);
        case 'register-identifiers':
            return iss_graph_wpcli_check_register_identifiers($limit);
        case 'content-identifiers':
            return iss_graph_wpcli_check_content_identifiers($limit);
        case 'archive-identifiers':
            return iss_graph_wpcli_check_archive_identifiers($limit);
        case 'place-taxonomy':
            return iss_graph_wpcli_check_place_taxonomy($limit);
        case 'place-graph':
            return iss_graph_wpcli_check_place_graph($limit);
        case 'search-index':
            return iss_graph_wpcli_check_search_index($limit);
        case 'editorial-signals':
            return iss_graph_wpcli_check_editorial_signals($limit);
        case 'entity-kind-contract':
            return iss_graph_wpcli_check_entity_kind_contract($limit);
        default:
            return [
                'checked' => 0,
                'errors' => [sprintf('Unknown drift check: %s', $check)],
            ];
    }
}

function iss_graph_wpcli_collect_post_ids(array $post_types, bool $require_relation_meta = false): array
{
    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
    if (!$post_types) {
        return [];
    }

    $args = [
        'post_type' => $post_types,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
    ];

    if ($require_relation_meta && defined('ISS_RELATIONS_META_KEY')) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Drift verification must discover posts that use the relation source meta key.
        $args['meta_query'] = [
            [
                'key' => ISS_RELATIONS_META_KEY,
                'compare' => 'EXISTS',
            ],
        ];
    }

    return array_values(array_filter(array_map('absint', get_posts($args))));
}

function iss_graph_wpcli_entity_has_identifier(int $entity_id, string $namespace, $value): bool
{
    $identifier = iss_graph_get_service()->get_identifier_by_namespace_value($namespace, $value);

    return is_array($identifier)
        && (int) ($identifier['entity_id'] ?? 0) === $entity_id
        && (string) ($identifier['status'] ?? '') === 'accepted';
}

function iss_graph_wpcli_check_post_identifiers(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $rows = $wpdb->get_results(
        "SELECT id, entity_kind, post_id, profile_post_id, display_title
        FROM {$entity_table}
        WHERE post_id IS NOT NULL
           OR profile_post_id IS NOT NULL
        ORDER BY id ASC",
        ARRAY_A
    );

    $errors = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $entity_id = (int) ($row['id'] ?? 0);
        $post_id = absint($row['post_id'] ?? 0) ?: absint($row['profile_post_id'] ?? 0);
        if ($entity_id <= 0 || $post_id <= 0) {
            continue;
        }

        if (!iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
            $errors[] = sprintf('Entity %d (%s) is missing accepted wp_post identifier %d.', $entity_id, (string) ($row['display_title'] ?? ''), $post_id);
            if (count($errors) >= $limit) {
                break;
            }
        }
    }

    return [
        'checked' => is_array($rows) ? count($rows) : 0,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_register_identifiers(int $limit): array
{
    if (!function_exists('iss_graph_get_register_post_type')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_collect_post_ids([iss_graph_get_register_post_type()]);
    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            continue;
        }

        $checked++;
        $entity = $service->find_entity_by_post('place', $post_id);
        $entity_id = (int) ($entity['id'] ?? 0);
        if ($entity_id <= 0) {
            $errors[] = sprintf('Register place %d has no place entity.', $post_id);
            if (count($errors) >= $limit) {
                break;
            }
            continue;
        }

        if (!iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
            $errors[] = sprintf('Register place %d entity %d is missing wp_post identifier.', $post_id, $entity_id);
        }

        $register_id = trim((string) get_post_meta($post_id, 'register_id', true));
        if ($register_id !== '' && !iss_graph_wpcli_entity_has_identifier($entity_id, 'register_id', $register_id)) {
            $errors[] = sprintf('Register place %d entity %d is missing register_id identifier %s.', $post_id, $entity_id, $register_id);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_content_identifiers(int $limit): array
{
    if (!function_exists('iss_graph_get_content_relation_post_types')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_collect_post_ids(iss_graph_get_content_relation_post_types());
    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            continue;
        }

        $checked++;
        $entity_kind = function_exists('iss_graph_get_entity_kind_for_post_type')
            ? iss_graph_get_entity_kind_for_post_type((string) $post->post_type)
            : sanitize_key((string) $post->post_type);
        $entity = $service->find_entity_by_post($entity_kind, $post_id);
        $entity_id = (int) ($entity['id'] ?? 0);
        if ($entity_id <= 0) {
            $errors[] = sprintf('Content post %d [%s] has no graph entity.', $post_id, $entity_kind);
        } elseif (!iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
            $errors[] = sprintf('Content post %d [%s] entity %d is missing wp_post identifier.', $post_id, $entity_kind, $entity_id);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_entity_kind_contract(int $limit): array
{
    global $wpdb;

    if (!function_exists('iss_graph_get_entity_kind_definition')) {
        return ['checked' => 0, 'errors' => ['Entity-kind registry is unavailable.']];
    }

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $errors = [];
    $checked = 0;

    $kind_rows = $wpdb->get_results(
        "SELECT entity_kind, COUNT(*) AS row_count
        FROM {$entity_table}
        GROUP BY entity_kind
        ORDER BY entity_kind ASC",
        ARRAY_A
    );

    foreach (is_array($kind_rows) ? $kind_rows : [] as $row) {
        $checked++;
        $entity_kind = sanitize_key((string) ($row['entity_kind'] ?? ''));
        if ($entity_kind === '' || !iss_graph_get_entity_kind_definition($entity_kind)) {
            $errors[] = sprintf(
                'Entity kind %s is not registered in the iss-graph entity-kind contract.',
                $entity_kind !== '' ? $entity_kind : '(empty)'
            );
        }

        if (count($errors) >= $limit) {
            return ['checked' => $checked, 'errors' => $errors];
        }
    }

    $rows = $wpdb->get_results(
        "SELECT id, entity_kind, post_id, display_title
        FROM {$entity_table}
        WHERE post_id IS NOT NULL
        ORDER BY id ASC",
        ARRAY_A
    );

    foreach (is_array($rows) ? $rows : [] as $row) {
        $entity_id = (int) ($row['id'] ?? 0);
        $post_id = (int) ($row['post_id'] ?? 0);
        $stored_kind = sanitize_key((string) ($row['entity_kind'] ?? ''));
        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post instanceof WP_Post) {
            continue;
        }

        $checked++;
        $expected_kind = function_exists('iss_graph_get_entity_kind_for_post_type')
            ? iss_graph_get_entity_kind_for_post_type((string) $post->post_type)
            : sanitize_key((string) $post->post_type);
        $candidates = function_exists('iss_graph_get_entity_kind_storage_candidates')
            ? iss_graph_get_entity_kind_storage_candidates($expected_kind)
            : [$expected_kind];

        if (!in_array($stored_kind, $candidates, true)) {
            $errors[] = sprintf(
                'Entity %d post %d [%s] stores kind=%s but expected one of: %s.',
                $entity_id,
                $post_id,
                (string) $post->post_type,
                $stored_kind,
                implode(', ', $candidates)
            );
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_archive_identifiers(int $limit): array
{
    if (!function_exists('iss_graph_get_archive_object_post_type')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_collect_post_ids([iss_graph_get_archive_object_post_type()]);
    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            continue;
        }

        $checked++;
        $projection = function_exists('iss_graph_get_archive_object_projection')
            ? iss_graph_get_archive_object_projection($post_id)
            : null;
        $entity = $service->find_entity_by_post('archive_object', $post_id);
        $entity_id = (int) ($entity['id'] ?? 0);

        if ($entity_id <= 0) {
            $errors[] = sprintf('Archive object %d has no archive_object entity.', $post_id);
            if (count($errors) >= $limit) {
                break;
            }
            continue;
        }

        if (!iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
            $errors[] = sprintf('Archive object %d entity %d is missing wp_post identifier.', $post_id, $entity_id);
        }

        $object_key = trim((string) ($projection['object_key'] ?? ''));
        if ($object_key !== '' && !iss_graph_wpcli_entity_has_identifier($entity_id, 'archive_object_id', $object_key)) {
            $errors[] = sprintf('Archive object %d entity %d is missing archive_object_id identifier %s.', $post_id, $entity_id, $object_key);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_get_relation_post_ids_for_drift(bool $taxonomy_check): array
{
    if (!function_exists('iss_relations_get_supported_post_types')) {
        return [];
    }

    $types = iss_relations_get_supported_post_types();
    $place_type = function_exists('iss_relations_get_place_post_type') ? iss_relations_get_place_post_type() : 'register_place';
    $post_ids = [];

    if ($taxonomy_check && in_array($place_type, $types, true)) {
        $post_ids = array_merge($post_ids, iss_graph_wpcli_collect_post_ids([$place_type]));
    }

    $relation_types = array_values(array_diff($types, [$place_type]));
    if ($relation_types) {
        $post_ids = array_merge($post_ids, iss_graph_wpcli_collect_post_ids($relation_types, true));
    }

    return array_values(array_unique(array_filter(array_map('absint', $post_ids))));
}

function iss_graph_wpcli_relation_signature(array $relations): array
{
    $items = [];
    foreach (array_values($relations) as $relation) {
        if (!is_array($relation)) {
            continue;
        }

        $place_id = absint($relation['place_id'] ?? 0);
        if ($place_id <= 0) {
            continue;
        }

        $items[] = implode(':', [
            $place_id,
            sanitize_key((string) ($relation['role'] ?? ($relation['relation_type'] ?? 'related'))) ?: 'related',
            (int) ($relation['weight'] ?? 0),
            sanitize_text_field((string) ($relation['label'] ?? ($relation['relation_label'] ?? ''))),
        ]);
    }

    $items = array_values(array_unique($items));
    sort($items, SORT_STRING);

    return $items;
}

function iss_graph_wpcli_get_taxonomy_place_ids(int $post_id): array
{
    if (!defined('ISS_RELATIONS_TAXONOMY') || !taxonomy_exists(ISS_RELATIONS_TAXONOMY)) {
        return [];
    }

    $term_ids = wp_get_object_terms($post_id, ISS_RELATIONS_TAXONOMY, ['fields' => 'ids']);
    if (is_wp_error($term_ids) || !is_array($term_ids)) {
        return [];
    }

    $place_ids = [];
    foreach ($term_ids as $term_id) {
        $place_id = absint(get_term_meta((int) $term_id, 'place_post_id', true));
        if ($place_id > 0) {
            $place_ids[] = $place_id;
        }
    }

    $place_ids = array_values(array_unique(array_filter($place_ids)));
    sort($place_ids, SORT_NUMERIC);

    return $place_ids;
}

function iss_graph_wpcli_check_place_taxonomy(int $limit): array
{
    if (!function_exists('iss_relations_collect_taxonomy_place_ids_for_post')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_get_relation_post_ids_for_drift(true);
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $expected = array_values(array_unique(array_filter(array_map('absint', iss_relations_collect_taxonomy_place_ids_for_post($post_id)))));
        sort($expected, SORT_NUMERIC);
        $actual = iss_graph_wpcli_get_taxonomy_place_ids($post_id);
        $checked++;

        if ($expected !== $actual) {
            $errors[] = sprintf('Post %d iss_place_ref drift: expected [%s], actual [%s].', $post_id, implode(',', $expected), implode(',', $actual));
            if (count($errors) >= $limit) {
                break;
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_get_graph_place_signature(int $post_id): array
{
    if (!function_exists('iss_relations_graph_entity_kind_for_post')) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [];
    }

    $entity = iss_graph_get_service()->find_entity_by_post(iss_relations_graph_entity_kind_for_post($post), $post_id);
    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return [];
    }

    $signature_rows = [];
    foreach (iss_graph_get_service()->get_relations_for_entity($entity_id, 'place', [
        'source_system' => 'iss_relations_meta',
        'limit' => 500,
    ]) as $row) {
        if (!is_array($row)) {
            continue;
        }

        $place_id = absint($row['post_id'] ?? 0);
        if ($place_id <= 0) {
            continue;
        }

        $signature_rows[] = [
            'place_id' => $place_id,
            'role' => sanitize_key((string) ($row['relation_type'] ?? 'related')) ?: 'related',
            'weight' => (int) ($row['weight'] ?? 0),
            'label' => sanitize_text_field((string) ($row['relation_label'] ?? '')),
        ];
    }

    return iss_graph_wpcli_relation_signature($signature_rows);
}

function iss_graph_wpcli_check_place_graph(int $limit): array
{
    if (!function_exists('iss_relations_get_stored_post_relations')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_get_relation_post_ids_for_drift(false);
    $archive_post_type = function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt';
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type === $archive_post_type) {
            continue;
        }

        $expected = iss_graph_wpcli_relation_signature(iss_relations_get_stored_post_relations($post_id));
        $actual = iss_graph_wpcli_get_graph_place_signature($post_id);
        $checked++;

        if ($expected !== $actual) {
            $errors[] = sprintf('Post %d graph place relation drift: expected [%s], actual [%s].', $post_id, implode(',', $expected), implode(',', $actual));
            if (count($errors) >= $limit) {
                break;
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_search_index(int $limit): array
{
    $post_types = function_exists('iss_graph_get_public_search_post_types') ? iss_graph_get_public_search_post_types() : [];
    $post_ids = iss_graph_wpcli_collect_post_ids($post_types);
    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            continue;
        }

        $row = $service->get_search_row_by_post($post_id);
        $checked++;

        if ($post->post_status === 'publish') {
            if (!$row) {
                $errors[] = sprintf('Published search post %d [%s] has no search index row.', $post_id, (string) $post->post_type);
            }
        } elseif ($row) {
            $errors[] = sprintf('Non-published search post %d [%s:%s] still has search index row.', $post_id, (string) $post->post_type, (string) $post->post_status);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_editorial_signals(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $signal_table = $service->get_editorial_signal_table_name();
    if (!$service->table_exists($signal_table)) {
        return [
            'checked' => 0,
            'errors' => [sprintf('Editorial signal table missing: %s.', $signal_table)],
        ];
    }

    $rows = $wpdb->get_results(
        "SELECT s.id, s.context_post_id, s.target_post_id, s.surface, s.signal_type, s.status,
            cp.post_status AS context_status,
            tp.post_status AS target_status
        FROM {$signal_table} s
        LEFT JOIN {$wpdb->posts} cp ON cp.ID = s.context_post_id
        LEFT JOIN {$wpdb->posts} tp ON tp.ID = s.target_post_id
        ORDER BY s.id ASC",
        ARRAY_A
    );

    $errors = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $id = (int) ($row['id'] ?? 0);
        $context_post_id = absint($row['context_post_id'] ?? 0);
        $target_post_id = absint($row['target_post_id'] ?? 0);
        $context_status = (string) ($row['context_status'] ?? '');
        $target_status = (string) ($row['target_status'] ?? '');

        if ($context_post_id <= 0 || $context_status === '') {
            $errors[] = sprintf('Editorial signal %d has a missing context post %d.', $id, $context_post_id);
        } elseif (in_array($context_status, ['auto-draft', 'trash'], true)) {
            $errors[] = sprintf('Editorial signal %d has invalid context post status %s for post %d.', $id, $context_status, $context_post_id);
        }

        if ($target_post_id <= 0 || $target_status === '') {
            $errors[] = sprintf('Editorial signal %d has a missing target post %d.', $id, $target_post_id);
        } elseif (in_array($target_status, ['auto-draft', 'trash'], true)) {
            $errors[] = sprintf('Editorial signal %d has invalid target post status %s for post %d.', $id, $target_status, $target_post_id);
        }

        $surface = $service->normalize_editorial_signal_surface((string) ($row['surface'] ?? ''));
        if ($surface !== (string) ($row['surface'] ?? '')) {
            $errors[] = sprintf('Editorial signal %d has invalid surface %s.', $id, (string) ($row['surface'] ?? ''));
        }

        $signal = $service->normalize_editorial_signal_type((string) ($row['signal_type'] ?? ''));
        if ($signal === '') {
            $errors[] = sprintf('Editorial signal %d has invalid signal type %s.', $id, (string) ($row['signal_type'] ?? ''));
        }

        $status = $service->normalize_editorial_signal_status((string) ($row['status'] ?? ''));
        if ($status !== (string) ($row['status'] ?? '')) {
            $errors[] = sprintf('Editorial signal %d has invalid status %s.', $id, (string) ($row['status'] ?? ''));
        }

        if (
            $context_post_id > 0
            && $target_post_id > 0
            && $context_post_id !== $target_post_id
            && function_exists('iss_graph_editorial_signal_target_is_allowed')
            && !iss_graph_editorial_signal_target_is_allowed($context_post_id, $target_post_id)
        ) {
            $errors[] = sprintf('Editorial signal %d targets post %d outside the allowed target types for context post %d.', $id, $target_post_id, $context_post_id);
        }

        if ($context_post_id > 0 && $target_post_id > 0 && $context_post_id === $target_post_id) {
            $post_type = get_post_type($target_post_id);
            if (!is_string($post_type) || !function_exists('iss_graph_is_related_promotion_post_type') || !iss_graph_is_related_promotion_post_type($post_type)) {
                $errors[] = sprintf('Editorial signal %d is a self-promotion on unsupported post %d.', $id, $target_post_id);
            }
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => is_array($rows) ? count($rows) : 0,
        'errors' => $errors,
    ];
}

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
    WP_CLI::log('[migrate] installing graph schema');
    iss_graph_get_service()->install_schema();
    iss_graph_ensure_editorial_signals_capability();

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

    if ($post_id > 0) {
        $entity = iss_graph_sync_register_place_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Register place %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced register place %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    $count = iss_graph_backfill_register_places();
    update_option(ISS_GRAPH_REGISTER_BACKFILL_OPTION, ISS_GRAPH_REGISTER_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d register places into the graph.', $count));
}

function iss_graph_wpcli_sync_content_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $entity = iss_graph_sync_public_content_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Public content post %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced public content post %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
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

    if ($post_id > 0) {
        $entity = iss_graph_sync_archive_object_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Archive object %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced archive object %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    $count = iss_graph_backfill_archive_objects();
    update_option(ISS_GRAPH_ARCHIVE_BACKFILL_OPTION, ISS_GRAPH_ARCHIVE_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d archive objects into the graph.', $count));
}

function iss_graph_wpcli_sync_aliases_command(array $args, array $assoc_args): void
{
    $entity_id = absint($assoc_args['entity_id'] ?? 0);

    if ($entity_id > 0) {
        $count = iss_graph_sync_entity_alias_backfill($entity_id);
        WP_CLI::success(sprintf('Synced %d generated alias name(s) for graph entity %d.', $count, $entity_id));
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
