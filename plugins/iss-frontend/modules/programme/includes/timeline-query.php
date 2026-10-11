<?php
if (!defined('ABSPATH')) exit;

function iss_timeline_get_now_mysql() {
    return current_time('mysql');
}

function iss_timeline_get_future_horizon_months() {
    $months = (int) apply_filters('iss_timeline_future_horizon_months', 6);
    return $months > 0 ? $months : 1;
}

function iss_timeline_get_future_horizon_end_mysql() {
    try {
        $tz = wp_timezone();
        $now = new DateTimeImmutable('now', $tz);
        $end = $now->modify('+' . iss_timeline_get_future_horizon_months() . ' months');
        return $end->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return current_time('mysql');
    }
}

function iss_timeline_resolve_item_type_value($type_filter) {
    $type_filter = sanitize_key((string) $type_filter);
    if ($type_filter === '' || $type_filter === 'all') {
        return '';
    }

    if (in_array($type_filter, ['fuehrungen', 'fuehrung', 'tour'], true)) {
        return 'tour';
    }
    if (in_array($type_filter, ['veranstaltungen', 'veranstaltung', 'event'], true)) {
        return 'event';
    }
    if (in_array($type_filter, ['ausstellungen', 'ausstellung', 'exhibition'], true)) {
        return 'ausstellung';
    }
    if (in_array($type_filter, ['projekte', 'projekt', 'project'], true)) {
        return 'project';
    }

    return $type_filter;
}

function iss_timeline_normalize_term_list($terms) {
    if (!is_array($terms)) {
        $terms = [$terms];
    }

    $normalized = [];
    foreach ($terms as $term) {
        if (is_numeric($term)) {
            $normalized[] = (int) $term;
            continue;
        }

        $term = sanitize_title((string) $term);
        if ($term !== '') {
            $normalized[] = $term;
        }
    }

    return array_values(array_unique(array_filter($normalized, static function ($term) {
        return $term !== '' && $term !== 0;
    })));
}

function iss_timeline_normalize_source_taxonomy_filters($filters) {
    if (!is_array($filters)) {
        return [];
    }

    $normalized = [];
    foreach ($filters as $filter) {
        if (!is_array($filter)) {
            continue;
        }

        $taxonomy = sanitize_key((string) ($filter['taxonomy'] ?? ''));
        if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = iss_timeline_normalize_term_list($filter['terms'] ?? []);
        if (empty($terms)) {
            continue;
        }

        $field = sanitize_key((string) ($filter['field'] ?? 'slug'));
        if (!in_array($field, ['slug', 'term_id'], true)) {
            $field = 'slug';
        }

        $operator = strtoupper(sanitize_text_field((string) ($filter['operator'] ?? 'IN')));
        if (!in_array($operator, ['IN', 'NOT IN', 'AND'], true)) {
            $operator = 'IN';
        }

        $normalized[] = [
            'taxonomy' => $taxonomy,
            'field' => $field,
            'terms' => $terms,
            'operator' => $operator,
        ];
    }

    return $normalized;
}

function iss_timeline_normalize_item_group_filters($args = []) {
    $groups = $args['groups'] ?? [];
    $single_group = isset($args['group']) ? sanitize_title((string) $args['group']) : '';
    if ($single_group !== '') {
        $groups[] = $single_group;
    }

    return iss_timeline_normalize_term_list($groups);
}

function iss_timeline_normalize_post_types($post_types) {
    if (!is_array($post_types)) {
        $post_types = [$post_types];
    }

    $normalized = [];
    foreach ($post_types as $post_type) {
        $post_type = sanitize_key((string) $post_type);
        if ($post_type !== '' && post_type_exists($post_type)) {
            $normalized[] = $post_type;
        }
    }

    return array_values(array_unique($normalized));
}

function iss_timeline_normalize_item_types($item_types) {
    if (!is_array($item_types)) {
        $item_types = [$item_types];
    }

    $normalized = [];
    foreach ($item_types as $item_type) {
        $item_type = iss_timeline_resolve_item_type_value($item_type);
        if ($item_type !== '') {
            $normalized[] = $item_type;
        }
    }

    return array_values(array_unique($normalized));
}

function iss_timeline_normalize_filter_payload($args = []) {
    $args = is_array($args) ? $args : [];
    $filters = isset($args['filters']) && is_array($args['filters']) ? $args['filters'] : [];

    $limit = isset($args['limit']) ? (int) $args['limit'] : 50;
    if ($limit === 0) {
        $limit = 50;
    }

    $order = strtoupper(sanitize_text_field((string) ($args['order'] ?? 'ASC')));
    if (!in_array($order, ['ASC', 'DESC'], true)) {
        $order = 'ASC';
    }

    $time_mode = sanitize_key((string) ($filters['time_mode'] ?? 'all'));
    if (!in_array($time_mode, ['all', 'upcoming', 'past', 'month', 'range'], true)) {
        $time_mode = 'all';
    }

    $month = isset($filters['month']) ? preg_replace('/[^0-9\-]/', '', (string) $filters['month']) : '';
    $month = preg_match('/^\d{4}-\d{2}$/', $month) ? $month : '';
    $type = iss_timeline_resolve_item_type_value($filters['item_type'] ?? '');

    $source_post_ids = isset($filters['source_post_ids']) ? $filters['source_post_ids'] : [];
    $source_post_ids = array_values(array_unique(array_filter(array_map('intval', is_array($source_post_ids) ? $source_post_ids : [$source_post_ids]))));

    return [
        'limit' => $limit,
        'offset' => isset($args['offset']) ? max(0, (int) $args['offset']) : 0,
        'order' => $order,
        'time_mode' => $time_mode,
        'month' => $month,
        'date_start' => isset($filters['date_start']) ? sanitize_text_field((string) $filters['date_start']) : '',
        'date_end' => isset($filters['date_end']) ? sanitize_text_field((string) $filters['date_end']) : '',
        'item_type' => $type,
        'item_types' => iss_timeline_normalize_item_types($filters['item_types'] ?? []),
        'item_groups' => iss_timeline_normalize_item_group_filters($args),
        'post_types' => iss_timeline_normalize_post_types($filters['post_types'] ?? []),
        'source_post_ids' => $source_post_ids,
        'source_taxonomy_filters' => iss_timeline_normalize_source_taxonomy_filters($filters['taxonomy_filters'] ?? []),
        'include_running_ranges' => !array_key_exists('include_running_ranges', $filters) || (bool) $filters['include_running_ranges'],
        'group_recurring' => !empty($args['group_recurring']),
        'group_recurring_by_month' => !empty($args['group_recurring_by_month']),
        'group_recurring_by_source' => !empty($args['group_recurring_by_source']),
    ];
}

function iss_timeline_get_items_advanced($args = []) {
    if (!function_exists('iss_occurrences_query')
        || (function_exists('iss_occurrences_public_query_ready') && !iss_occurrences_public_query_ready())
    ) {
        return [];
    }

    return iss_occurrences_query(iss_timeline_normalize_filter_payload($args));
}

/** Resolve only editor-exposed GET controls; fixed owner restrictions remain authoritative. */
function iss_timeline_apply_public_filters(array $config, array $attributes, array $input): array {
    $scalar = static fn(string $key): string => isset($input[$key]) && is_scalar($input[$key]) ? sanitize_text_field((string) $input[$key]) : '';
    $selection = ['preset' => '', 'day' => '', 'month' => '', 'taxonomies' => []];
    $buttons = iss_timeline_get_preset_buttons_from_attributes($attributes);
    $preset = $scalar('preset');
    if ($preset !== '' && ctype_digit($preset) && isset($buttons[(int) $preset])) {
        $selection['preset'] = $preset;
    } else {
        foreach ($buttons as $index => $button) {
            if (!empty($button['isDefault'])) { $selection['preset'] = (string) $index; break; }
        }
    }
    if ($selection['preset'] !== '') {
        $config['filters'] = iss_timeline_merge_preset_filters($config['filters'], $buttons[(int) $selection['preset']]);
    }
    $preset_dates = array_intersect_key($config['filters'], array_flip(['time_mode', 'month', 'date_start', 'date_end']));
    $ui = $config['ui'];
    if ($ui['showTimeModeFilter'] && in_array($scalar('time_mode'), array_column($ui['timeModeOptions'], 'value'), true)) {
        $config['filters']['time_mode'] = $scalar('time_mode');
        unset($config['filters']['date_start'], $config['filters']['date_end']);
    }
    $fixed = iss_timeline_normalize_item_types($config['filters']['item_types']);
    if ($fixed) {
        $config['ui']['typeOptions'] = array_values(array_filter($ui['typeOptions'], static fn(array $option): bool => $option['value'] === 'all' || in_array(iss_timeline_resolve_item_type_value($option['value']), $fixed, true)));
    }
    $type = $scalar('item_type');
    if ($ui['showItemTypeFilter'] && in_array($type, array_column($config['ui']['typeOptions'], 'value'), true)) {
        if ($type !== 'all') {
            // The occurrence owner combines item_types and item_type with OR. Narrow
            // the allowed list here instead of adding a kind to that union.
            $config['filters']['item_types'] = [iss_timeline_resolve_item_type_value($type)];
        }
        $config['filters']['item_type'] = $type === 'all' ? '' : $type;
    }
    $post_type = $scalar('post_type');
    if ($ui['showPostTypeFilter'] && $post_type !== '' && in_array($post_type, array_column($ui['postTypeOptions'], 'value'), true) && in_array($post_type, $config['filters']['post_types'], true)) {
        $config['filters']['post_types'] = [$post_type];
    }
    $month = $scalar('month');
    if (($ui['showMonthFilter'] || $ui['showCalendarBridge']) && preg_match('/^\d{4}-\d{2}$/D', $month) && checkdate((int) substr($month, 5), 1, (int) substr($month, 0, 4))) {
        $selection['month'] = $month;
        $config['filters']['month'] = $month;
        if ($ui['showCalendarBridge']) { $config['filters']['time_mode'] = 'month'; }
    }
    $day = $scalar('day');
    if ($ui['showCalendarBridge'] && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day) && checkdate((int) substr($day, 5, 2), (int) substr($day, 8), (int) substr($day, 0, 4))) {
        $selection['day'] = $day;
        $selection['month'] = substr($day, 0, 7);
        $config['filters']['month'] = $selection['month'];
        $config['filters']['time_mode'] = 'range';
        $config['filters']['date_start'] = $day . ' 00:00:00';
        $config['filters']['date_end'] = $day . ' 23:59:59';
    }
    foreach ($ui['taxonomyUiFilters'] as $rule) {
        $taxonomy = $rule['taxonomy'];
        $requested = $input['taxonomy_' . $taxonomy] ?? [];
        $requested = is_array($requested) ? $requested : [$requested];
        $requested = array_map('sanitize_title', array_filter(array_slice($requested, 0, 50), 'is_scalar'));
        $terms = array_values(array_intersect(array_column($rule['options'], 'value'), $requested));
        $terms = array_values(array_filter($terms, static fn(string $term): bool => $term !== ''));
        $selection['taxonomies'][$taxonomy] = $terms;
        if ($terms) {
            $config['filters']['taxonomy_filters'][] = ['taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $terms, 'operator' => 'IN'];
        }
    }
    $config['limit'] = min(100, max(1, (int) $config['limit']));
    $page = !empty($config['render']['showLoadMore']) ? min(1000, max(1, (int) $scalar('page'))) : 1;
    $selection['page'] = $page;
    $selection['presetActive'] = $preset_dates === array_intersect_key($config['filters'], array_flip(['time_mode', 'month', 'date_start', 'date_end']));
    $config['offset'] = ($page - 1) * $config['limit'];
    $config['selection'] = $selection;
    return $config;
}

/** Stable for full requests and router requests, including repeated identical blocks. */
function iss_timeline_query_scope(array $attributes): string {
    static $instances = [];
    $hash = substr(hash('sha256', get_queried_object_id() . '|' . wp_json_encode($attributes)), 0, 12);
    $instances[$hash] = ($instances[$hash] ?? 0) + 1;
    return 'timeline-' . $hash . '-' . $instances[$hash];
}

/** Bound reflected navigation data and retain other block instances and WP routing. */
function iss_timeline_navigation_query(): array {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only navigation, no state mutation.
    $input = wp_unslash($_GET);
    $clean = static function (array $values, int $depth = 0) use (&$clean): array {
        $result = [];
        foreach (array_slice($values, 0, 50, true) as $key => $value) {
            if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', (string) $key)) { continue; }
            if (is_array($value) && $depth < 3) { $result[$key] = $clean($value, $depth + 1); }
            elseif (is_scalar($value)) { $result[$key] = substr(sanitize_text_field((string) $value), 0, 200); }
        }
        return $result;
    };
    return $clean($input);
}

function iss_timeline_navigation_url(string $scope, array $selection = []): string {
    $query = iss_timeline_navigation_query();
    if (!isset($query['iss_timeline']) || !is_array($query['iss_timeline'])) { $query['iss_timeline'] = []; }
    unset($query['iss_timeline'][$scope]);
    if ($selection) { $query['iss_timeline'][$scope] = $selection; }
    if (!$query['iss_timeline']) { unset($query['iss_timeline']); }
    $path = wp_parse_url(isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '/', PHP_URL_PATH);
    // An origin-relative path avoids trusting a request-supplied host.
    $path = '/' . ltrim(is_string($path) ? $path : '/', '/');
    return $path . ($query ? '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986) : '') . '#'. $scope;
}
