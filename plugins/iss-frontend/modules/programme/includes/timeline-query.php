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
