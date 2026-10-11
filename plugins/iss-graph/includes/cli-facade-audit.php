<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Existing CLI verification reads owner graph tables.

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

function iss_graph_wpcli_direct_rest_callback(string $context, callable $callback, string $path, array $params, array &$errors, string $label = 'direct'): array
{
    $request = new WP_REST_Request('GET', $path);
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    $raw_response = call_user_func($callback, $request);
    if (is_wp_error($raw_response)) {
        $errors[] = sprintf('%s returned error: %s', $context, $raw_response->get_error_message());
        WP_CLI::log(sprintf('[%s] %s status=error count=n/a', $label, $context));

        return [];
    }

    $response = rest_ensure_response($raw_response);
    $status = (int) $response->get_status();
    $data = $response->get_data();
    if ($status !== 200) {
        $errors[] = sprintf('%s returned status %d, expected 200.', $context, $status);
    }
    if (!is_array($data)) {
        $errors[] = sprintf('%s returned a non-object response.', $context);
        $data = [];
    }

    WP_CLI::log(sprintf('[%s] %s status=%d count=%s', $label, $context, $status, iss_graph_wpcli_facade_response_count($data)));

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

function iss_graph_wpcli_facade_route_contract(): array
{
    return [
        'required_read' => [
            '/iss/v1/contract',
            '/iss/v1/entities',
            '/iss/v1/entities/(?P<id>\d+)/relations',
            '/iss/v1/entities/(?P<id>\d+)/occurrences',
            '/iss/v1/occurrences',
            '/iss/v1/search',
        ],
        'provider_read' => [
            '/iss/v1/timeline' => 'iss_timeline_rest_render_collection',
            '/iss/v1/availability' => 'iss_programm_rest_list_availability',
            '/iss/v1/booking-slots' => 'iss_occurrences_get_booking_slots_rest',
        ],
        'allowed_write' => [
            '/iss-payments/v1/request' => 'iss_payments_lite_create_request',
        ],
        'retired_read' => [
            '/iss-search/v1/search',
            '/iss-programm/v1/timeline',
            '/is-tours/v1/slots',
        ],
    ];
}

function iss_graph_wpcli_facade_route_scan_roots(): array
{
    $roots = [];
    $theme_root = get_stylesheet_directory();
    if (is_dir($theme_root)) {
        $roots[] = $theme_root;
    }

    $plugin_root = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : trailingslashit(WP_CONTENT_DIR) . 'plugins';
    foreach (glob(trailingslashit($plugin_root) . '*', GLOB_ONLYDIR) ?: [] as $plugin_dir) {
        $plugin_name = basename((string) $plugin_dir);
        if (
            str_starts_with($plugin_name, 'iss-')
            || str_starts_with($plugin_name, 'industriesalon-')
        ) {
            $roots[] = (string) $plugin_dir;
        }
    }

    return array_values(array_unique($roots));
}

function iss_graph_wpcli_facade_route_scan_sources(array $retired_routes, int $limit): array
{
    $errors = [];
    $checked = 0;
    $allowed_extensions = ['php', 'js', 'html', 'json'];
    $skip_directories = ['.git', 'node_modules', 'vendor'];
    $cli_directory = realpath(__DIR__);

    foreach (iss_graph_wpcli_facade_route_scan_roots() as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $current) use ($skip_directories): bool {
                    return !$current->isDir() || !in_array($current->getFilename(), $skip_directories, true);
                }
            )
        );

        foreach ($iterator as $file_info) {
            if (!$file_info instanceof SplFileInfo || !$file_info->isFile()) {
                continue;
            }

            $extension = strtolower($file_info->getExtension());
            if (!in_array($extension, $allowed_extensions, true)) {
                continue;
            }

            $path = $file_info->getPathname();
            if ($cli_directory !== false && realpath(dirname($path)) === $cli_directory && preg_match('/^cli(?:-.*)?\.php$/', basename($path))) {
                continue;
            }

            $checked++;
            $contents = file_get_contents($path);
            if (!is_string($contents)) {
                continue;
            }

            foreach ($retired_routes as $route) {
                if (str_contains($contents, (string) $route)) {
                    $errors[] = sprintf('Retired read route consumer remains in %s: %s.', str_replace(ABSPATH, '', $path), (string) $route);
                    if (count($errors) >= $limit) {
                        break 3;
                    }
                }
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_normalize_route_haystack($value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $parts[] = iss_graph_wpcli_normalize_route_haystack($item);
        }

        return implode("\n", array_filter($parts, static function (string $part): bool {
            return $part !== '';
        }));
    }

    if (!is_scalar($value)) {
        return '';
    }

    $haystack = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $haystack = str_replace('\/', '/', $haystack);

    return $haystack;
}

function iss_graph_wpcli_route_haystack_contains($value, string $route_fragment): bool
{
    $route_fragment = trim($route_fragment, '/');
    if ($route_fragment === '') {
        return false;
    }

    return str_contains(iss_graph_wpcli_normalize_route_haystack($value), $route_fragment);
}

function iss_graph_wpcli_get_inline_script_haystack(string $handle)
{
    $scripts = wp_scripts();
    if (!$scripts instanceof WP_Scripts || empty($scripts->registered[$handle])) {
        return '';
    }

    return [
        $scripts->get_data($handle, 'data'),
        $scripts->get_data($handle, 'before'),
        $scripts->get_data($handle, 'after'),
    ];
}

function iss_graph_wpcli_facade_consumer_contract(): array
{
    return [
        'header-search' => [
            'kind' => 'rendered_search_modal',
            'function' => 'industriesalon_render_search_modal',
            'route' => 'iss/v1/search',
        ],
        // Timeline uses native GET/core region navigation; it has no REST consumer.
        'booking-slot-read' => [
            'kind' => 'interactivity_config',
            'setup_function' => 'iss_programm_register_frontend_assets',
            'namespace' => 'iss/booking',
            'route' => 'iss/v1/booking-slots',
        ],
        'request-write' => [
            'kind' => 'interactivity_config',
            'setup_function' => 'iss_programm_register_frontend_assets',
            'namespace' => 'iss/booking',
            'route' => 'iss-payments/v1/request',
            'write' => true,
        ],
        'availability-browser-script' => [
            'kind' => 'inline_script',
            'setup_function' => 'iss_programm_register_frontend_assets',
            'handle' => 'iss-ausstellungen-browser',
            'route' => 'iss/v1/availability',
        ],
        'availability-browser-block' => [
            'kind' => 'source_literal',
            'file' => trailingslashit(defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : trailingslashit(WP_CONTENT_DIR) . 'plugins') . 'iss-frontend/modules/programme/includes/ausstellungen-browser.php',
            'route' => 'iss/v1/availability',
        ],
    ];
}

function iss_graph_wpcli_check_facade_consumer_contract(int $limit): array
{
    $errors = [];
    $checked = 0;
    $setup_functions = [];

    foreach (iss_graph_wpcli_facade_consumer_contract() as $consumer => $contract) {
        $consumer = (string) $consumer;
        $kind = sanitize_key((string) ($contract['kind'] ?? ''));
        $route = trim((string) ($contract['route'] ?? ''));
        if ($kind === '' || $route === '') {
            continue;
        }

        $setup_function = (string) ($contract['setup_function'] ?? '');
        if ($setup_function !== '' && function_exists($setup_function) && !isset($setup_functions[$setup_function])) {
            call_user_func($setup_function);
            $setup_functions[$setup_function] = true;
        }

        $haystack = '';
        if ($kind === 'rendered_search_modal') {
            $function = (string) ($contract['function'] ?? '');
            if ($function === '' || !function_exists($function)) {
                $errors[] = sprintf('Facade consumer %s render function is unavailable.', $consumer);
                continue;
            }

            ob_start();
            call_user_func($function);
            $haystack = ob_get_clean();
        } elseif ($kind === 'interactivity_config') {
            $haystack = wp_interactivity_config((string) ($contract['namespace'] ?? ''));
        } elseif ($kind === 'inline_script') {
            $handle = sanitize_key((string) ($contract['handle'] ?? ''));
            if ($handle === '') {
                $errors[] = sprintf('Facade consumer %s has no script handle.', $consumer);
                continue;
            }

            $haystack = iss_graph_wpcli_get_inline_script_haystack($handle);
        } elseif ($kind === 'source_literal') {
            $file = (string) ($contract['file'] ?? '');
            if ($file === '' || !is_readable($file)) {
                $errors[] = sprintf('Facade consumer %s source file is unavailable.', $consumer);
                continue;
            }

            $haystack = file_get_contents($file);
        } else {
            $errors[] = sprintf('Facade consumer %s has unknown check kind %s.', $consumer, $kind);
            continue;
        }

        $checked++;
        if (!iss_graph_wpcli_route_haystack_contains($haystack, $route)) {
            $errors[] = sprintf('Facade consumer %s does not expose expected route fragment %s.', $consumer, $route);
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

function iss_graph_wpcli_facade_consumer_audit_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(100, absint($assoc_args['limit'] ?? 25)));
    $result = iss_graph_wpcli_check_facade_consumer_contract($limit);
    $errors = (array) ($result['errors'] ?? []);

    WP_CLI::log(sprintf('[facade-consumer] checked=%d', (int) ($result['checked'] ?? 0)));
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade consumer audit failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph facade consumer audit passed.');
}

function iss_graph_wpcli_frontend_view_contract(): array
{
    $theme_root = get_stylesheet_directory();

    return [
        'calendar' => [
            'file' => trailingslashit($theme_root) . 'templates/page-kalender.html',
            'requires' => [
                'wp:industriesalon/timeline-query',
                '"allowedTypesList":["fuehrungen","veranstaltungen"]',
                '"externalTypeLinks":[{"label":"Ausstellungen","url":"/ausstellungen/"}]',
            ],
            'forbids' => [
                'wp:industriesalon/ausstellungen-browser',
            ],
        ],
        'front-page-programme' => [
            'file' => trailingslashit($theme_root) . 'templates/front-page.html',
            'requires' => [
                'wp:industriesalon/timeline-query',
                '"fixedItemTypesList":["fuehrungen","veranstaltungen"]',
            ],
            'forbids' => [
                'wp:industriesalon/ausstellungen-browser',
            ],
        ],
        'exhibitions' => [
            'file' => trailingslashit($theme_root) . 'templates/page-ausstellungen.html',
            'requires' => [
                'wp:industriesalon/ausstellungen-browser',
                '"defaultFilter":"aktuell"',
                '"defaultFilter":"archiv"',
            ],
            'forbids' => [
                'wp:industriesalon/timeline-query',
            ],
        ],
        'tours' => [
            'file' => trailingslashit($theme_root) . 'templates/page-fuehrungen.html',
            'requires' => [
                'wp:industriesalon/editorial-landing',
            ],
            'forbids' => [
                'wp:industriesalon/ausstellungen-browser',
                'wp:core/query',
                'wp:iss/tour-offer-catalog',
            ],
        ],
        'events' => [
            'file' => trailingslashit($theme_root) . 'templates/page-veranstaltungen.html',
            'requires' => [
                'wp:industriesalon/timeline-query',
                '"defaultType":"veranstaltungen"',
                '"postTypesList":["veranstaltung"]',
            ],
            'forbids' => [
                'wp:industriesalon/ausstellungen-browser',
                '"postTypesList":["ausstellung"]',
            ],
        ],
    ];
}

function iss_graph_wpcli_check_frontend_view_contract(int $limit): array
{
    $errors = [];
    $checked = 0;

    foreach (iss_graph_wpcli_frontend_view_contract() as $view => $contract) {
        $view = (string) $view;
        $file = (string) ($contract['file'] ?? '');
        if ($file === '' || !is_readable($file)) {
            $errors[] = sprintf('Frontend view %s source file is unavailable.', $view);
            continue;
        }

        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            $errors[] = sprintf('Frontend view %s source file could not be read.', $view);
            continue;
        }

        $contents = iss_graph_wpcli_normalize_route_haystack($contents);
        foreach ((array) ($contract['requires'] ?? []) as $needle) {
            $needle = (string) $needle;
            if ($needle === '') {
                continue;
            }

            $checked++;
            if (!str_contains($contents, $needle)) {
                $errors[] = sprintf('Frontend view %s is missing required source fragment: %s.', $view, $needle);
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }

        foreach ((array) ($contract['forbids'] ?? []) as $needle) {
            $needle = (string) $needle;
            if ($needle === '') {
                continue;
            }

            $checked++;
            if (str_contains($contents, $needle)) {
                $errors[] = sprintf('Frontend view %s contains forbidden source fragment: %s.', $view, $needle);
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

function iss_graph_wpcli_view_contract_audit_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(100, absint($assoc_args['limit'] ?? 25)));
    $result = iss_graph_wpcli_check_frontend_view_contract($limit);
    $errors = (array) ($result['errors'] ?? []);

    WP_CLI::log(sprintf('[view-contract] checked=%d', (int) ($result['checked'] ?? 0)));
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph view contract audit failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph view contract audit passed.');
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
    iss_graph_wpcli_facade_require_keys('/iss/v1/contract', $contract, ['namespace', 'version', 'read_only', 'providers', 'routes', 'entity_kinds', 'offer_subtypes'], $errors);
    if (($contract['namespace'] ?? '') !== 'iss/v1') {
        $errors[] = '/iss/v1/contract namespace is not iss/v1.';
    }
    if (empty($contract['read_only'])) {
        $errors[] = '/iss/v1/contract does not report read_only=true.';
    }
    if (empty($contract['entity_kinds']) || !is_array($contract['entity_kinds'])) {
        $errors[] = '/iss/v1/contract has no entity_kinds list.';
    }
    if (empty($contract['offer_subtypes']) || !is_array($contract['offer_subtypes'])) {
        $errors[] = '/iss/v1/contract has no offer_subtypes list.';
    }
    if (function_exists('iss_timeline_rest_render_collection')) {
        $routes = isset($contract['routes']) && is_array($contract['routes']) ? $contract['routes'] : [];
        if (!in_array('/iss/v1/timeline', $routes, true)) {
            $errors[] = '/iss/v1/contract does not advertise the timeline facade route.';
        }
    }
    if (function_exists('iss_programm_rest_list_availability')) {
        $routes = isset($contract['routes']) && is_array($contract['routes']) ? $contract['routes'] : [];
        if (!in_array('/iss/v1/availability', $routes, true)) {
            $errors[] = '/iss/v1/contract does not advertise the availability facade route.';
        }
    }
    if (function_exists('iss_occurrences_get_booking_slots_rest')) {
        $routes = isset($contract['routes']) && is_array($contract['routes']) ? $contract['routes'] : [];
        if (!in_array('/iss/v1/booking-slots', $routes, true)) {
            $errors[] = '/iss/v1/contract does not advertise the booking slots facade route.';
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
        iss_graph_wpcli_facade_require_keys('/iss/v1/entities item', $first_entity, ['id', 'kind', 'storage_kind', 'contract_kind', 'subtype', 'contract', 'title', 'url'], $errors);
        if (isset($first_entity['contract']) && !is_array($first_entity['contract'])) {
            $errors[] = '/iss/v1/entities item contract is not an object.';
        }
        $entity_id = absint($first_entity['id'] ?? 0);
    }

    if ($entity_id > 0) {
        $detail_path = sprintf('/iss/v1/entities/%d', $entity_id);
        $detail = iss_graph_wpcli_facade_request($detail_path, [], $errors);
        iss_graph_wpcli_facade_require_keys($detail_path, $detail, ['item'], $errors);
        $item = isset($detail['item']) && is_array($detail['item']) ? $detail['item'] : [];
        iss_graph_wpcli_facade_require_keys($detail_path . ' item', $item, ['id', 'kind', 'contract_kind', 'subtype', 'contract', 'names', 'identifiers', 'relations'], $errors);
        if (isset($item['contract']) && !is_array($item['contract'])) {
            $errors[] = sprintf('%s item contract is not an object.', $detail_path);
        }
        foreach (['names', 'identifiers', 'relations'] as $list_key) {
            if (isset($item[$list_key]) && !is_array($item[$list_key])) {
                $errors[] = sprintf('%s item key %s is not a list.', $detail_path, $list_key);
            }
        }

        $relations_path = sprintf('/iss/v1/entities/%d/relations', $entity_id);
        $relations = iss_graph_wpcli_facade_request($relations_path, ['limit' => $limit], $errors);
        iss_graph_wpcli_facade_require_keys($relations_path, $relations, ['entity_id', 'filters', 'count', 'items'], $errors);
        if (isset($relations['filters']) && !is_array($relations['filters'])) {
            $errors[] = sprintf('%s filters is not an object.', $relations_path);
        }
        if (isset($relations['items']) && !is_array($relations['items'])) {
            $errors[] = sprintf('%s items is not a list.', $relations_path);
        }
        $relation_items = isset($relations['items']) && is_array($relations['items']) ? $relations['items'] : [];
        if (isset($relation_items[0]) && is_array($relation_items[0])) {
            iss_graph_wpcli_facade_require_keys(
                $relations_path . ' item',
                $relation_items[0],
                ['relation_id', 'direction', 'entity_id', 'kind', 'storage_kind', 'name', 'family', 'type', 'label', 'source'],
                $errors
            );
        }

        $entity_occurrences_path = sprintf('/iss/v1/entities/%d/occurrences', $entity_id);
        $entity_occurrences = iss_graph_wpcli_facade_request($entity_occurrences_path, ['limit' => $limit], $errors);
        iss_graph_wpcli_facade_require_keys($entity_occurrences_path, $entity_occurrences, ['entity_id', 'filters', 'count', 'items'], $errors);
        if ((int) ($entity_occurrences['entity_id'] ?? 0) !== $entity_id) {
            $errors[] = sprintf('%s entity_id does not match the route id.', $entity_occurrences_path);
        }
        if (isset($entity_occurrences['filters']) && !is_array($entity_occurrences['filters'])) {
            $errors[] = sprintf('%s filters is not an object.', $entity_occurrences_path);
        }
        if (isset($entity_occurrences['items']) && !is_array($entity_occurrences['items'])) {
            $errors[] = sprintf('%s items is not a list.', $entity_occurrences_path);
        }
    }

    $occurrences = iss_graph_wpcli_facade_request('/iss/v1/occurrences', ['limit' => $limit], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/occurrences', $occurrences, ['count', 'items'], $errors);
    $occurrence_items = isset($occurrences['items']) && is_array($occurrences['items']) ? $occurrences['items'] : [];
    if (isset($occurrence_items[0]) && is_array($occurrence_items[0])) {
        iss_graph_wpcli_facade_require_keys('/iss/v1/occurrences item', $occurrence_items[0], ['id', 'entity_id', 'kind', 'contract_kind', 'subtype', 'contract', 'title', 'starts_at', 'source', 'location', 'schema'], $errors);
        if (isset($occurrence_items[0]['contract']) && !is_array($occurrence_items[0]['contract'])) {
            $errors[] = '/iss/v1/occurrences item contract is not an object.';
        }
        if (isset($occurrence_items[0]['schema']) && !is_array($occurrence_items[0]['schema'])) {
            $errors[] = '/iss/v1/occurrences item schema is not an object.';
        }
    }

    $search_results = iss_graph_wpcli_facade_request('/iss/v1/search', ['q' => $search, 'limit' => $limit], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/search', $search_results, ['query', 'provider', 'count', 'items'], $errors);
    $search_items = isset($search_results['items']) && is_array($search_results['items']) ? $search_results['items'] : [];
    if (isset($search_items[0]) && is_array($search_items[0])) {
        iss_graph_wpcli_facade_require_keys('/iss/v1/search item', $search_items[0], ['id', 'result_kind', 'entity_id', 'type', 'contract_kind', 'subtype', 'contract', 'title', 'url'], $errors);
        if (isset($search_items[0]['contract']) && !is_array($search_items[0]['contract'])) {
            $errors[] = '/iss/v1/search item contract is not an object.';
        }
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

    if (function_exists('iss_programm_rest_list_availability')) {
        $availability = iss_graph_wpcli_facade_request('/iss/v1/availability', [
            'filter' => 'aktuell',
            'limit' => $limit,
        ], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/availability', $availability, ['provider', 'kind', 'storage_kind', 'filters', 'count', 'items', 'html', 'is_empty'], $errors);
        if (isset($availability['filters']) && !is_array($availability['filters'])) {
            $errors[] = '/iss/v1/availability filters is not an object.';
        }
        if (isset($availability['items']) && !is_array($availability['items'])) {
            $errors[] = '/iss/v1/availability items is not a list.';
        }
        if (isset($availability['html']) && !is_string($availability['html'])) {
            $errors[] = '/iss/v1/availability html is not a string.';
        }
        $availability_items = isset($availability['items']) && is_array($availability['items']) ? $availability['items'] : [];
        if (isset($availability_items[0]) && is_array($availability_items[0])) {
            iss_graph_wpcli_facade_require_keys(
                '/iss/v1/availability item',
                $availability_items[0],
                ['id', 'entity_id', 'kind', 'storage_kind', 'title', 'url', 'type', 'availability', 'editorial', 'schema', 'source'],
                $errors
            );
            if (isset($availability_items[0]['schema']) && !is_array($availability_items[0]['schema'])) {
                $errors[] = '/iss/v1/availability item schema is not an object.';
            }
        }
    }

    if (function_exists('iss_occurrences_get_booking_slots_rest')) {
        $booking_slots = iss_graph_wpcli_facade_request('/iss/v1/booking-slots', [], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/booking-slots', $booking_slots, ['source', 'slots'], $errors);
        if (isset($booking_slots['slots']) && !is_array($booking_slots['slots'])) {
            $errors[] = '/iss/v1/booking-slots slots is not a list.';
        }
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade check failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph facade check passed.');
}
