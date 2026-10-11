<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Existing CLI verification reads owner graph tables.

function iss_graph_wpcli_parse_drift_checks(string $value): array
{
    $default_checks = [
        'post-identifiers',
        'register-identifiers',
        'content-identifiers',
        'archive-identifiers',
        'place-taxonomy',
        'place-graph',
        'relation-integrity',
        'search-index',
        'editorial-signals',
        'entity-kind-contract',
        'public-object-contract',
        'entity-relations-contract',
        'entity-occurrences-contract',
        'availability-contract',
        'facade-route-contract',
        'facade-consumer-contract',
        'frontend-view-contract',
    ];
    $available = array_merge($default_checks, [
        'alias-backfill-replay',
        'canonical-organization-seeds',
        'canonical-wf-industriesalon',
    ]);

    $value = trim($value);
    if ($value === '') {
        return $default_checks;
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
        case 'relation-integrity':
            return iss_graph_wpcli_check_relation_integrity($limit);
        case 'search-index':
            return iss_graph_wpcli_check_search_index($limit);
        case 'editorial-signals':
            return iss_graph_wpcli_check_editorial_signals($limit);
        case 'entity-kind-contract':
            return iss_graph_wpcli_check_entity_kind_contract($limit);
        case 'public-object-contract':
            return iss_graph_wpcli_check_public_object_contract($limit);
        case 'entity-relations-contract':
            return iss_graph_wpcli_check_entity_relations_contract($limit);
        case 'entity-occurrences-contract':
            return iss_graph_wpcli_check_entity_occurrences_contract($limit);
        case 'availability-contract':
            return iss_graph_wpcli_check_availability_contract($limit);
        case 'facade-route-contract':
            return iss_graph_wpcli_check_facade_route_contract($limit);
        case 'facade-consumer-contract':
            return iss_graph_wpcli_check_facade_consumer_contract($limit);
        case 'frontend-view-contract':
            return iss_graph_wpcli_check_frontend_view_contract($limit);
        case 'alias-backfill-replay':
            return iss_graph_wpcli_check_alias_backfill_replay($limit);
        case 'canonical-organization-seeds':
            return iss_graph_wpcli_check_canonical_organization_seeds($limit);
        case 'canonical-wf-industriesalon':
            return iss_graph_wpcli_check_canonical_wf_industriesalon($limit);
        default:
            return [
                'checked' => 0,
                'errors' => [sprintf('Unknown drift check: %s', $check)],
            ];
    }
}

function iss_graph_wpcli_check_alias_backfill_replay(int $limit): array
{
    if (!function_exists('iss_graph_audit_entity_alias_backfill')) {
        return [
            'checked' => 0,
            'errors' => ['Alias backfill audit is unavailable.'],
        ];
    }

    $stats = iss_graph_audit_entity_alias_backfill(['sample_limit' => min(25, $limit)]);
    $changed = (int) ($stats['changed_entities'] ?? 0);
    $removed = (int) ($stats['removed_names'] ?? 0);
    $added = (int) ($stats['added_names'] ?? 0);
    if ($changed <= 0 && $removed <= 0 && $added <= 0) {
        return [
            'checked' => (int) ($stats['entities'] ?? 0),
            'errors' => [],
        ];
    }

    $errors = [
        sprintf(
            'Alias backfill replay pending: changed_entities=%d current_names=%d proposed_names=%d removed_names=%d added_names=%d.',
            $changed,
            (int) ($stats['current_names'] ?? 0),
            (int) ($stats['proposed_names'] ?? 0),
            $removed,
            $added
        ),
    ];

    foreach ((array) ($stats['samples'] ?? []) as $preview) {
        if (!is_array($preview) || count($errors) >= $limit) {
            break;
        }

        $errors[] = sprintf(
            'Alias backfill pending for entity %d [%s] "%s": removed=%d added=%d.',
            (int) ($preview['entity_id'] ?? 0),
            (string) ($preview['entity_kind'] ?? ''),
            iss_graph_wpcli_entity_hygiene_clip((string) ($preview['title'] ?? ''), 80),
            (int) ($preview['removed_count'] ?? 0),
            (int) ($preview['added_count'] ?? 0)
        );
    }

    return [
        'checked' => (int) ($stats['entities'] ?? 0),
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_entity_relations_contract(int $limit): array
{
    $errors = [];
    $checked = 0;
    $request_limit = max(1, min(5, $limit));
    $scenarios = [
        'outgoing' => iss_graph_wpcli_facade_find_relation_entity_id('outgoing', ''),
        'incoming' => iss_graph_wpcli_facade_find_relation_entity_id('incoming', ''),
    ];

    foreach ($scenarios as $direction => $entity_id) {
        $entity_id = absint($entity_id);
        if ($entity_id <= 0) {
            continue;
        }

        $checked++;
        $path = sprintf('/iss/v1/entities/%d/relations', $entity_id);
        $request = new WP_REST_Request('GET', $path);
        $request->set_param('direction', $direction);
        $request->set_param('limit', $request_limit);
        $response = rest_do_request($request);
        if ($response->is_error()) {
            $error = $response->as_error();
            $errors[] = sprintf(
                '%s returned REST error: %s.',
                $path,
                $error ? $error->get_error_message() : 'unknown error'
            );
            continue;
        }

        $status = (int) $response->get_status();
        if ($status !== 200) {
            $errors[] = sprintf('%s returned status %d for direction=%s, expected 200.', $path, $status, $direction);
            continue;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            $errors[] = sprintf('%s returned a non-object response for direction=%s.', $path, $direction);
            continue;
        }

        foreach (['entity_id', 'filters', 'count', 'items'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('%s direction=%s is missing key: %s.', $path, $direction, $key);
            }
        }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        if ((int) ($data['count'] ?? -1) !== count($items)) {
            $errors[] = sprintf('%s direction=%s count does not match item list length.', $path, $direction);
        }

        if (isset($items[0]) && is_array($items[0])) {
            $checked++;
            foreach (['relation_id', 'direction', 'entity_id', 'kind', 'storage_kind', 'name', 'family', 'type', 'label', 'source'] as $key) {
                if (!array_key_exists($key, $items[0])) {
                    $errors[] = sprintf('%s direction=%s first relation item is missing key: %s.', $path, $direction, $key);
                }
            }
            if (($items[0]['direction'] ?? '') !== $direction) {
                $errors[] = sprintf('%s first relation item direction is %s, expected %s.', $path, (string) ($items[0]['direction'] ?? ''), $direction);
            }
            if (isset($items[0]['source']) && !is_array($items[0]['source'])) {
                $errors[] = sprintf('%s first relation item source is not an object.', $path);
            }
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_check_entity_occurrences_contract(int $limit): array
{
    if (!function_exists('iss_occurrences_query')) {
        return [
            'checked' => 0,
            'errors' => [],
        ];
    }

    $errors = [];
    $checked = 0;
    $request_limit = max(1, min(5, $limit));
    $scenarios = [
        'upcoming' => iss_graph_wpcli_facade_occurrence_params_for_scenario('upcoming', $request_limit),
        'event' => iss_graph_wpcli_facade_occurrence_params_for_scenario('event', $request_limit),
        'tour' => iss_graph_wpcli_facade_occurrence_params_for_scenario('tour', $request_limit),
    ];

    foreach ($scenarios as $scenario => $params) {
        $entity_id = iss_graph_wpcli_facade_find_occurrence_entity_id($params);
        if ($entity_id <= 0) {
            continue;
        }

        $checked++;
        $path = sprintf('/iss/v1/entities/%d/occurrences', $entity_id);
        $request = new WP_REST_Request('GET', $path);
        foreach ($params as $key => $value) {
            $request->set_param((string) $key, $value);
        }

        $response = rest_do_request($request);
        if ($response->is_error()) {
            $error = $response->as_error();
            $errors[] = sprintf(
                '%s scenario=%s returned REST error: %s.',
                $path,
                $scenario,
                $error ? $error->get_error_message() : 'unknown error'
            );
            continue;
        }

        $status = (int) $response->get_status();
        if ($status !== 200) {
            $errors[] = sprintf('%s scenario=%s returned status %d, expected 200.', $path, $scenario, $status);
            continue;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            $errors[] = sprintf('%s scenario=%s returned a non-object response.', $path, $scenario);
            continue;
        }

        foreach (['entity_id', 'filters', 'count', 'items'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('%s scenario=%s is missing key: %s.', $path, $scenario, $key);
            }
        }
        if ((int) ($data['entity_id'] ?? 0) !== $entity_id) {
            $errors[] = sprintf('%s scenario=%s entity_id does not match route id.', $path, $scenario);
        }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        if ((int) ($data['count'] ?? -1) !== count($items)) {
            $errors[] = sprintf('%s scenario=%s count does not match item list length.', $path, $scenario);
        }

        if (isset($items[0]) && is_array($items[0])) {
            $checked++;
            foreach (['id', 'entity_id', 'kind', 'contract_kind', 'subtype', 'contract', 'title', 'starts_at', 'source', 'location', 'schema'] as $key) {
                if (!array_key_exists($key, $items[0])) {
                    $errors[] = sprintf('%s scenario=%s first occurrence item is missing key: %s.', $path, $scenario, $key);
                }
            }
            if ((int) ($items[0]['entity_id'] ?? 0) !== $entity_id) {
                $errors[] = sprintf('%s scenario=%s first occurrence entity_id does not match route id.', $path, $scenario);
            }
            if (isset($items[0]['schema']) && is_array($items[0]['schema'])) {
                $schema = $items[0]['schema'];
                if (($schema['type'] ?? '') !== 'Event' || empty($schema['emits_event_schema'])) {
                    $errors[] = sprintf('%s scenario=%s occurrence schema is not Event-emitting.', $path, $scenario);
                }
            }
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_check_availability_contract(int $limit): array
{
    if (!function_exists('iss_programm_rest_list_availability')) {
        return [
            'checked' => 0,
            'errors' => [],
        ];
    }

    $errors = [];
    $checked = 0;
    $request_limit = max(1, min(5, $limit));
    $scenarios = [
        ['filter' => 'aktuell', 'search' => ''],
        ['filter' => 'dauer', 'search' => ''],
        ['filter' => 'digital', 'search' => ''],
        ['filter' => 'archiv', 'search' => ''],
        ['filter' => 'aktuell', 'search' => 'Werk'],
    ];

    foreach ($scenarios as $scenario) {
        $filter = (string) ($scenario['filter'] ?? 'aktuell');
        $search = (string) ($scenario['search'] ?? '');
        $checked++;
        $request = new WP_REST_Request('GET', '/iss/v1/availability');
        $request->set_param('kind', 'exhibition');
        $request->set_param('filter', $filter);
        $request->set_param('limit', $request_limit);
        if ($search !== '') {
            $request->set_param('search', $search);
        }
        $response = rest_do_request($request);
        if ($response->is_error()) {
            $error = $response->as_error();
            $errors[] = sprintf(
                '/iss/v1/availability filter=%s returned REST error: %s.',
                $filter,
                $error ? $error->get_error_message() : 'unknown error'
            );
            continue;
        }

        $status = (int) $response->get_status();
        if ($status !== 200) {
            $errors[] = sprintf('/iss/v1/availability filter=%s returned status %d, expected 200.', $filter, $status);
            continue;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            $errors[] = sprintf('/iss/v1/availability filter=%s returned a non-object response.', $filter);
            continue;
        }

        foreach (['provider', 'kind', 'storage_kind', 'filters', 'count', 'items', 'html', 'is_empty'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('/iss/v1/availability filter=%s is missing key: %s.', $filter, $key);
            }
        }

        if (isset($data['html']) && !is_string($data['html'])) {
            $errors[] = sprintf('/iss/v1/availability filter=%s html is not a string.', $filter);
        }
        if (isset($data['is_empty']) && !is_bool($data['is_empty'])) {
            $errors[] = sprintf('/iss/v1/availability filter=%s is_empty is not a boolean.', $filter);
        }

        if (($data['provider'] ?? '') !== 'iss-frontend') {
            $errors[] = sprintf('/iss/v1/availability filter=%s provider is %s, expected iss-frontend.', $filter, (string) ($data['provider'] ?? ''));
        }
        if (($data['kind'] ?? '') !== 'exhibition' || ($data['storage_kind'] ?? '') !== 'ausstellung') {
            $errors[] = sprintf('/iss/v1/availability filter=%s does not expose exhibition/ausstellung kind fields.', $filter);
        }

        $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];
        foreach (['kind', 'filter', 'limit', 'search', 'today'] as $key) {
            if (!array_key_exists($key, $filters)) {
                $errors[] = sprintf('/iss/v1/availability filter=%s response filters is missing key: %s.', $filter, $key);
            }
        }
        if ($search !== '' && (string) ($filters['search'] ?? '') !== $search) {
            $errors[] = sprintf('/iss/v1/availability filter=%s did not preserve search filter %s.', $filter, $search);
        }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        if ((int) ($data['count'] ?? -1) !== count($items)) {
            $errors[] = sprintf('/iss/v1/availability filter=%s count does not match item list length.', $filter);
        }

        if (isset($items[0]) && is_array($items[0])) {
            $checked++;
            foreach (['id', 'entity_id', 'kind', 'storage_kind', 'title', 'url', 'type', 'availability', 'editorial', 'schema', 'source'] as $key) {
                if (!array_key_exists($key, $items[0])) {
                    $errors[] = sprintf('/iss/v1/availability filter=%s first item is missing key: %s.', $filter, $key);
                }
            }
            if (isset($items[0]['type']) && !is_array($items[0]['type'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item type is not an object.', $filter);
            }
            if (isset($items[0]['availability']) && !is_array($items[0]['availability'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item availability is not an object.', $filter);
            }
            if (isset($items[0]['editorial']) && !is_array($items[0]['editorial'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item editorial is not an object.', $filter);
            }
            if (isset($items[0]['schema']) && !is_array($items[0]['schema'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item schema is not an object.', $filter);
            }
            if (isset($items[0]['schema']) && is_array($items[0]['schema'])) {
                $schema = $items[0]['schema'];
                if (!array_key_exists('emits_event_schema', $schema) || !array_key_exists('type', $schema)) {
                    $errors[] = sprintf('/iss/v1/availability filter=%s first item schema is missing event-split keys.', $filter);
                } elseif (!empty($schema['emits_event_schema']) || (string) ($schema['type'] ?? '') === 'Event') {
                    $errors[] = sprintf('/iss/v1/availability filter=%s first item must not emit Event schema.', $filter);
                }
            }
            if (isset($items[0]['source']) && !is_array($items[0]['source'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item source is not an object.', $filter);
            }
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_canonical_organization_seed_contract(): array
{
    return [
        [
            'label' => 'KWO',
            'source_id' => 'kabelwerk-oberspree',
            'canonical_slug' => 'kabelwerk-oberspree',
            'display_title' => 'Kabelwerk Oberspree',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'kabelwerk-oberspree', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'manual_alias', 'normalized_name' => 'kwo', 'name_type' => 'abbreviation', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'veb-kabelwerk-oberspree', 'name_type' => 'official', 'is_primary' => 0],
            ],
        ],
        [
            'label' => 'AEG',
            'source_id' => 'allgemeine-elektricitats-gesellschaft',
            'canonical_slug' => 'allgemeine-elektricitats-gesellschaft',
            'display_title' => 'Allgemeine Elektricitäts-Gesellschaft',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'allgemeine-elektricitats-gesellschaft', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'manual_alias', 'normalized_name' => 'aeg', 'name_type' => 'abbreviation', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'allgemeine-elektricitaets-gesellschaft', 'name_type' => 'transliteration', 'is_primary' => 0],
            ],
        ],
    ];
}

function iss_graph_wpcli_check_canonical_organization_seeds(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $name_table = $service->get_name_table_name();
    if (!$service->table_exists($entity_table) || !$service->table_exists($name_table)) {
        return [
            'checked' => 0,
            'errors' => ['Graph entity or name table is unavailable.'],
        ];
    }

    $errors = [];
    $checked = 0;
    foreach (iss_graph_wpcli_canonical_organization_seed_contract() as $seed) {
        $checked++;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, canonical_slug, display_title, status, is_public, search_visibility
                FROM {$entity_table}
                WHERE entity_kind = 'organization'
                  AND source_system = 'manual_slug'
                  AND source_id = %s
                ORDER BY id ASC",
                (string) ($seed['source_id'] ?? '')
            ),
            ARRAY_A
        );
        $rows = is_array($rows) ? $rows : [];
        if (count($rows) !== 1) {
            $errors[] = sprintf(
                'Canonical organization seed %s expected exactly 1 manual_slug row for source_id=%s, found %d.',
                (string) ($seed['label'] ?? ''),
                (string) ($seed['source_id'] ?? ''),
                count($rows)
            );
            if (count($errors) >= $limit) {
                break;
            }
            continue;
        }

        $entity = $rows[0];
        $entity_id = (int) ($entity['id'] ?? 0);
        foreach (['canonical_slug', 'display_title', 'status', 'search_visibility'] as $field) {
            $expected = $field === 'status' ? 'publish' : (string) ($seed[$field] ?? '');
            if ($field === 'search_visibility') {
                $expected = 'hidden';
            }
            $actual = (string) ($entity[$field] ?? '');
            if ($actual !== $expected) {
                $errors[] = sprintf('Canonical organization seed %s entity %d has %s=%s, expected %s.', (string) ($seed['label'] ?? ''), $entity_id, $field, $actual, $expected);
            }
        }

        if ((int) ($entity['is_public'] ?? 0) !== 0) {
            $errors[] = sprintf('Canonical organization seed %s entity %d must remain hidden, found is_public=%d.', (string) ($seed['label'] ?? ''), $entity_id, (int) ($entity['is_public'] ?? 0));
        }

        foreach ((array) ($seed['names'] ?? []) as $name) {
            $checked++;
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$name_table}
                    WHERE entity_id = %d
                      AND source_system = %s
                      AND normalized_name = %s
                      AND name_type = %s
                      AND is_primary = %d",
                    $entity_id,
                    (string) ($name['source_system'] ?? ''),
                    (string) ($name['normalized_name'] ?? ''),
                    (string) ($name['name_type'] ?? ''),
                    (int) ($name['is_primary'] ?? 0)
                )
            );
            if ($exists <= 0) {
                $errors[] = sprintf(
                    'Canonical organization seed %s entity %d is missing %s %s:%s.',
                    (string) ($seed['label'] ?? ''),
                    $entity_id,
                    (string) ($name['source_system'] ?? ''),
                    (string) ($name['name_type'] ?? ''),
                    (string) ($name['normalized_name'] ?? '')
                );
            }

            if (count($errors) >= $limit) {
                break 2;
            }
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

function iss_graph_wpcli_canonical_wf_industriesalon_contract(): array
{
    return [
        [
            'label' => 'Industriesalon',
            'source_system' => 'manual_slug',
            'source_id' => 'industriesalon-schoneweide-e-v',
            'canonical_slug' => 'industriesalon-schoneweide-e-v',
            'display_title' => 'Industriesalon Schöneweide e.V.',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'industriesalon-schoneweide-e-v', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'manual_alias', 'normalized_name' => 'iss', 'name_type' => 'abbreviation', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'industriesalon', 'name_type' => 'alternative', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'industriesalon-schoneweide', 'name_type' => 'alternative', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'industriesalon-schoeneweide', 'name_type' => 'transliteration', 'is_primary' => 0],
            ],
            'identifiers' => [
                ['namespace' => 'archive_institution', 'normalized_value' => 'institution:29', 'status' => 'accepted'],
            ],
        ],
        [
            'label' => 'WF',
            'source_system' => 'archive_person_organization_label',
            'source_id' => 'person-label:63251',
            'canonical_slug' => 'archive_person_organization_label-person-label63251',
            'display_title' => 'Werk für Fernsehelektronik',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'werk-fur-fernsehelektronik', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'manual_alias', 'normalized_name' => 'wf', 'name_type' => 'abbreviation', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'veb-werk-fur-fernsehelektronik', 'name_type' => 'official', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'werk-fuer-fernsehelektronik', 'name_type' => 'transliteration', 'is_primary' => 0],
            ],
        ],
        [
            'label' => 'Werk für Fernmeldewesen',
            'source_system' => 'archive_person_organization_label',
            'source_id' => 'person-label:63659',
            'canonical_slug' => 'archive_person_organization_label-person-label63659',
            'display_title' => 'Werk für Fernmeldewesen',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'werk-fur-fernmeldewesen', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'archive_title', 'normalized_name' => 'werk-fur-fernmeldewesen', 'name_type' => 'source_label', 'is_primary' => 0],
            ],
        ],
    ];
}

function iss_graph_wpcli_check_canonical_wf_industriesalon(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $name_table = $service->get_name_table_name();
    $identifier_table = $service->get_identifier_table_name();
    $relation_table = $service->get_relation_table_name();
    foreach ([$entity_table, $name_table, $identifier_table, $relation_table] as $table_name) {
        if (!$service->table_exists($table_name)) {
            return [
                'checked' => 0,
                'errors' => ['Graph entity, name, identifier, or relation table is unavailable.'],
            ];
        }
    }

    $errors = [];
    $checked = 0;
    $entity_ids = [];
    foreach (iss_graph_wpcli_canonical_wf_industriesalon_contract() as $seed) {
        $checked++;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, canonical_slug, display_title, status, is_public, search_visibility
                FROM {$entity_table}
                WHERE entity_kind = 'organization'
                  AND source_system = %s
                  AND source_id = %s
                ORDER BY id ASC",
                (string) ($seed['source_system'] ?? ''),
                (string) ($seed['source_id'] ?? '')
            ),
            ARRAY_A
        );
        $rows = is_array($rows) ? $rows : [];
        if (count($rows) !== 1) {
            $errors[] = sprintf(
                'Canonical identity %s expected exactly 1 row for %s:%s, found %d.',
                (string) ($seed['label'] ?? ''),
                (string) ($seed['source_system'] ?? ''),
                (string) ($seed['source_id'] ?? ''),
                count($rows)
            );
            if (count($errors) >= $limit) {
                break;
            }
            continue;
        }

        $entity = $rows[0];
        $entity_id = (int) ($entity['id'] ?? 0);
        $entity_ids[(string) ($seed['label'] ?? '')] = $entity_id;
        foreach (['canonical_slug', 'display_title', 'status', 'search_visibility'] as $field) {
            $expected = $field === 'status' ? 'publish' : (string) ($seed[$field] ?? '');
            if ($field === 'search_visibility') {
                $expected = 'hidden';
            }
            $actual = (string) ($entity[$field] ?? '');
            if ($actual !== $expected) {
                $errors[] = sprintf('Canonical identity %s entity %d has %s=%s, expected %s.', (string) ($seed['label'] ?? ''), $entity_id, $field, $actual, $expected);
            }
        }

        if ((int) ($entity['is_public'] ?? 0) !== 0) {
            $errors[] = sprintf('Canonical identity %s entity %d must remain hidden, found is_public=%d.', (string) ($seed['label'] ?? ''), $entity_id, (int) ($entity['is_public'] ?? 0));
        }

        foreach ((array) ($seed['names'] ?? []) as $name) {
            $checked++;
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$name_table}
                    WHERE entity_id = %d
                      AND source_system = %s
                      AND normalized_name = %s
                      AND name_type = %s
                      AND is_primary = %d",
                    $entity_id,
                    (string) ($name['source_system'] ?? ''),
                    (string) ($name['normalized_name'] ?? ''),
                    (string) ($name['name_type'] ?? ''),
                    (int) ($name['is_primary'] ?? 0)
                )
            );
            if ($exists <= 0) {
                $errors[] = sprintf(
                    'Canonical identity %s entity %d is missing %s %s:%s.',
                    (string) ($seed['label'] ?? ''),
                    $entity_id,
                    (string) ($name['source_system'] ?? ''),
                    (string) ($name['name_type'] ?? ''),
                    (string) ($name['normalized_name'] ?? '')
                );
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }

        foreach ((array) ($seed['identifiers'] ?? []) as $identifier) {
            $checked++;
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$identifier_table}
                    WHERE entity_id = %d
                      AND namespace = %s
                      AND normalized_value = %s
                      AND status = %s",
                    $entity_id,
                    (string) ($identifier['namespace'] ?? ''),
                    (string) ($identifier['normalized_value'] ?? ''),
                    (string) ($identifier['status'] ?? '')
                )
            );
            if ($exists <= 0) {
                $errors[] = sprintf(
                    'Canonical identity %s entity %d is missing identifier %s:%s.',
                    (string) ($seed['label'] ?? ''),
                    $entity_id,
                    (string) ($identifier['namespace'] ?? ''),
                    (string) ($identifier['normalized_value'] ?? '')
                );
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }
    }

    $checked++;
    $archive_institution_rows = (int) $wpdb->get_var(
        "SELECT COUNT(*)
        FROM {$entity_table}
        WHERE entity_kind = 'organization'
          AND source_system = 'archive_institution'
          AND source_id = 'institution:29'"
    );
    if ($archive_institution_rows > 0) {
        $errors[] = sprintf('Archive institution institution:29 must resolve by identifier only; found %d active source row(s).', $archive_institution_rows);
    }

    if (!empty($entity_ids['Industriesalon'])) {
        $checked++;
        $old_institution_relations = (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$relation_table} r
            INNER JOIN {$entity_table} e ON e.id = r.to_entity_id
            WHERE e.entity_kind = 'organization'
              AND e.source_system = 'archive_institution'
              AND e.source_id = 'institution:29'"
        );
        if ($old_institution_relations > 0) {
            $errors[] = sprintf('Archive institution institution:29 still has %d relation(s) pointing at the retired source row.', $old_institution_relations);
        }
    }

    if (!empty($entity_ids['WF'])) {
        $checked++;
        $noncanonical_wf_names = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$name_table}
                WHERE normalized_name = 'wf'
                  AND entity_id <> %d",
                (int) $entity_ids['WF']
            )
        );
        if ($noncanonical_wf_names > 0) {
            $errors[] = sprintf('WF must be a canonical alias only for Werk für Fernsehelektronik; found %d noncanonical WF name row(s).', $noncanonical_wf_names);
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_check_facade_route_contract(int $limit): array
{
    $contract = iss_graph_wpcli_facade_route_contract();
    $routes = array_keys(rest_get_server()->get_routes());
    $errors = [];
    $checked = 0;

    foreach ((array) $contract['required_read'] as $route) {
        $checked++;
        if (!in_array($route, $routes, true)) {
            $errors[] = sprintf('Required facade read route is not registered: %s.', (string) $route);
        }
    }

    foreach ((array) $contract['provider_read'] as $route => $provider_function) {
        if (!function_exists((string) $provider_function)) {
            continue;
        }

        $checked++;
        if (!in_array((string) $route, $routes, true)) {
            $errors[] = sprintf('Provider facade read route is not registered: %s.', (string) $route);
        }
    }

    foreach ((array) $contract['allowed_write'] as $route => $provider_function) {
        if (!function_exists((string) $provider_function)) {
            continue;
        }

        $checked++;
        if (!in_array((string) $route, $routes, true)) {
            $errors[] = sprintf('Expected booking write route is not registered: %s.', (string) $route);
        }
    }

    foreach ((array) $contract['retired_read'] as $route) {
        $checked++;
        if (in_array($route, $routes, true)) {
            $errors[] = sprintf('Retired read route is still registered: %s.', (string) $route);
        }
    }

    $scan = iss_graph_wpcli_facade_route_scan_sources((array) $contract['retired_read'], $limit);
    $checked += (int) ($scan['checked'] ?? 0);
    foreach ((array) ($scan['errors'] ?? []) as $error) {
        if (is_string($error) && $error !== '') {
            $errors[] = $error;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
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

function iss_graph_wpcli_get_entity_rows_for_post(int $post_id): array
{
    global $wpdb;

    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return [];
    }

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, entity_kind, display_title
            FROM {$entity_table}
            WHERE post_id = %d
            ORDER BY entity_kind ASC, id ASC",
            $post_id
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

function iss_graph_wpcli_format_entity_row_summary(array $rows): string
{
    $items = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $items[] = sprintf(
            '#%d:%s',
            (int) ($row['id'] ?? 0),
            sanitize_key((string) ($row['entity_kind'] ?? ''))
        );
    }

    return implode(',', $items);
}

function iss_graph_wpcli_check_public_object_contract(int $limit): array
{
    if (!function_exists('iss_graph_get_public_object_post_type_contracts')) {
        return [
            'checked' => 0,
            'errors' => ['Public object contract registry is unavailable.'],
        ];
    }

    $contracts = iss_graph_get_public_object_post_type_contracts();
    $contracts = array_filter($contracts, static function ($contract): bool {
        return is_array($contract)
            && !empty($contract['post_type'])
            && post_type_exists((string) $contract['post_type']);
    });

    if (!$contracts) {
        return [
            'checked' => 0,
            'errors' => [],
        ];
    }

    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;
    $post_type_owners = [];

    foreach (iss_graph_get_entity_kind_registry() as $definition) {
        $canonical_kind = sanitize_key((string) ($definition['canonical_kind'] ?? ''));
        foreach ((array) ($definition['post_types'] ?? []) as $post_type) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type !== '') {
                $post_type_owners[$post_type][] = $canonical_kind;
            }
        }
    }

    foreach ($contracts as $contract) {
        $post_type = sanitize_key((string) ($contract['post_type'] ?? ''));
        $storage_kind = sanitize_key((string) ($contract['storage_kind'] ?? ''));
        $canonical_kind = sanitize_key((string) ($contract['canonical_kind'] ?? ''));
        $contract_kind = sanitize_key((string) ($contract['contract_kind'] ?? $canonical_kind));
        $owners = array_values(array_unique(array_filter($post_type_owners[$post_type] ?? [])));

        $checked++;
        if (count($owners) !== 1 || $owners[0] !== $canonical_kind) {
            $errors[] = sprintf(
                'Public object post type %s has ambiguous entity-kind registry owners: expected %s, found [%s].',
                $post_type,
                $canonical_kind,
                implode(',', $owners)
            );
            if (count($errors) >= $limit) {
                break;
            }
        }

        $post_ids = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ]);

        foreach (array_map('absint', is_array($post_ids) ? $post_ids : []) as $post_id) {
            if ($post_id <= 0) {
                continue;
            }

            $checked++;
            $rows = iss_graph_wpcli_get_entity_rows_for_post($post_id);
            if (count($rows) > 1) {
                $errors[] = sprintf(
                    'Public object %d [%s] has multiple post-backed graph entities: %s.',
                    $post_id,
                    $post_type,
                    iss_graph_wpcli_format_entity_row_summary($rows)
                );
                if (count($errors) >= $limit) {
                    break 2;
                }
            }

            $entity = $storage_kind !== '' ? $service->find_entity_by_post($storage_kind, $post_id) : null;
            if (!$entity) {
                $detail = $rows ? ' found ' . iss_graph_wpcli_format_entity_row_summary($rows) . ' instead.' : '';
                $errors[] = sprintf(
                    'Public object %d [%s] has no canonical graph entity for storage kind %s.%s',
                    $post_id,
                    $post_type,
                    $storage_kind,
                    $detail
                );
                if (count($errors) >= $limit) {
                    break 2;
                }
                continue;
            }

            $entity_id = (int) ($entity['id'] ?? 0);
            $actual_storage_kind = sanitize_key((string) ($entity['entity_kind'] ?? ''));
            $actual_canonical_kind = function_exists('iss_graph_get_canonical_entity_kind')
                ? iss_graph_get_canonical_entity_kind($actual_storage_kind)
                : $actual_storage_kind;

            if ($actual_storage_kind !== $storage_kind || $actual_canonical_kind !== $canonical_kind) {
                $errors[] = sprintf(
                    'Public object %d [%s] entity %d maps to %s/%s, expected %s/%s.',
                    $post_id,
                    $post_type,
                    $entity_id,
                    $actual_canonical_kind,
                    $actual_storage_kind,
                    $canonical_kind,
                    $storage_kind
                );
            }

            if ($entity_id > 0 && !iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
                $errors[] = sprintf('Public object %d [%s] entity %d is missing wp_post identifier.', $post_id, $post_type, $entity_id);
            }

            $payload = function_exists('iss_graph_get_entity_contract_payload')
                ? iss_graph_get_entity_contract_payload($entity)
                : [];
            $actual_contract_kind = sanitize_key((string) ($payload['kind'] ?? $actual_canonical_kind));
            if ($actual_contract_kind !== $contract_kind) {
                $errors[] = sprintf(
                    'Public object %d [%s] entity %d has contract kind %s, expected %s.',
                    $post_id,
                    $post_type,
                    $entity_id,
                    $actual_contract_kind,
                    $contract_kind
                );
            }

            if (!empty($contract['requires_subtype'])) {
                $subtype = sanitize_key((string) ($payload['subtype'] ?? ''));
                if ($subtype === '' || !iss_graph_get_offer_subtype_definition($subtype)) {
                    $errors[] = sprintf(
                        'Public object %d [%s] entity %d has missing or unknown offer subtype %s.',
                        $post_id,
                        $post_type,
                        $entity_id,
                        $subtype !== '' ? $subtype : '(empty)'
                    );
                }
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
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
        "SELECT s.id, s.context_post_id, s.target_post_id, s.surface, s.signal_type, s.reason, s.expires_at, s.author_user_id, s.status,
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
        $reason = trim((string) ($row['reason'] ?? ''));
        $expires_at = isset($row['expires_at']) ? (string) $row['expires_at'] : '';
        $author_user_id = absint($row['author_user_id'] ?? 0);

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

        if ($status === 'active') {
            if ($author_user_id <= 0) {
                $errors[] = sprintf('Editorial signal %d has no author user.', $id);
            } elseif (!get_userdata($author_user_id)) {
                $errors[] = sprintf('Editorial signal %d references missing author user %d.', $id, $author_user_id);
            }
        }

        if (
            $status === 'active'
            && $signal !== ''
            && $service->editorial_signal_requires_metadata($surface, $signal)
        ) {
            if ($reason === '') {
                $errors[] = sprintf('Editorial signal %d has no reason.', $id);
            }

            if ($expires_at === '' || $expires_at === '0000-00-00 00:00:00' || $service->normalize_editorial_signal_expiry($expires_at) === null) {
                $errors[] = sprintf('Editorial signal %d has no valid expiry.', $id);
            }
        }

        if (
            $context_post_id > 0
            && $target_post_id > 0
            && function_exists('iss_graph_editorial_signal_target_is_allowed_for_surface')
            && !iss_graph_editorial_signal_target_is_allowed_for_surface($context_post_id, $target_post_id, $surface)
        ) {
            $errors[] = sprintf('Editorial signal %d targets post %d outside the allowed target types for context post %d.', $id, $target_post_id, $context_post_id);
        }

        if ($context_post_id > 0 && $target_post_id > 0 && $context_post_id === $target_post_id) {
            $post_type = get_post_type($target_post_id);
            if (
                $surface === 'related'
                && (!is_string($post_type) || !function_exists('iss_graph_is_related_promotion_post_type') || !iss_graph_is_related_promotion_post_type($post_type))
            ) {
                $errors[] = sprintf('Editorial signal %d is a self-promotion on unsupported post %d.', $id, $target_post_id);
            }

            if (
                $surface === 'search'
                && (!is_string($post_type) || !function_exists('iss_graph_is_search_signal_post_type') || !iss_graph_is_search_signal_post_type($post_type))
            ) {
                $errors[] = sprintf('Editorial signal %d is a search signal on unsupported post %d.', $id, $target_post_id);
            }

            if (
                $surface === 'availability'
                && (!is_string($post_type) || !function_exists('iss_graph_is_availability_signal_post_type') || !iss_graph_is_availability_signal_post_type($post_type))
            ) {
                $errors[] = sprintf('Editorial signal %d is an availability signal on unsupported post %d.', $id, $target_post_id);
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
