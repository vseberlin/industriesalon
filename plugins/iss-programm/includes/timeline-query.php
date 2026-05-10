<?php
if (!defined('ABSPATH')) exit;

function iss_timeline_get_now_mysql() {
    return current_time('mysql');
}

function iss_timeline_get_future_horizon_months() {
    $months = (int) apply_filters('iss_timeline_future_horizon_months', 6);
    if ($months < 1) {
        $months = 1;
    }
    return $months;
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

function iss_timeline_build_visibility_meta_query() {
    // Visible items only.
    // Back-compat: if `is_visible` is not set, fall back to `is_public`.
    return [
        'relation' => 'OR',
        [
            'key' => 'is_visible',
            'value' => 1,
            'compare' => '=',
            'type' => 'NUMERIC',
        ],
        [
            'relation' => 'AND',
            [
                'key' => 'is_visible',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => 'is_public',
                'value' => 1,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
        ],
    ];
}

function iss_timeline_month_to_range($ym) {
    $ym = trim((string) $ym);
    if (!preg_match('/^\\d{4}-\\d{2}$/', $ym)) return null;

    try {
        $tz = wp_timezone();
        $start_dt = new DateTimeImmutable($ym . '-01 00:00:00', $tz);
        $end_dt = $start_dt->modify('+1 month')->modify('-1 second');
        return [
            'start' => $start_dt->format('Y-m-d H:i:s'),
            'end' => $end_dt->format('Y-m-d H:i:s'),
        ];
    } catch (Throwable $e) {
        return null;
    }
}

function iss_timeline_build_type_meta_query($type_filter) {
    if (is_array($type_filter)) {
        $resolved_types = [];
        foreach ($type_filter as $single_type) {
            $resolved_type = iss_timeline_resolve_item_type_value($single_type);
            if ($resolved_type !== '') {
                $resolved_types[] = $resolved_type;
            }
        }

        $resolved_types = array_values(array_unique($resolved_types));
        if (empty($resolved_types)) {
            return [];
        }

        return [
            'key' => 'item_type',
            'value' => count($resolved_types) === 1 ? $resolved_types[0] : $resolved_types,
            'compare' => count($resolved_types) === 1 ? '=' : 'IN',
        ];
    }

    $type_filter = iss_timeline_resolve_item_type_value($type_filter);
    if ($type_filter === '') return [];

    return [
        'key' => 'item_type',
        'value' => $type_filter,
        'compare' => '=',
    ];
}

function iss_timeline_resolve_item_type_value($type_filter) {
    $type_filter = sanitize_key((string) $type_filter);
    if ($type_filter === '' || $type_filter === 'all') {
        return '';
    }

    // Simple editorial filters used by the site.
    if (in_array($type_filter, ['fuehrungen', 'fuehrung', 'tour'], true)) {
        return 'tour';
    }

    if (in_array($type_filter, ['veranstaltungen', 'veranstaltung', 'event'], true)) {
        return 'event';
    }

    if (in_array($type_filter, ['ausstellungen', 'ausstellung', 'exhibition'], true)) {
        return 'ausstellung';
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
        if ($taxonomy === '' || !taxonomy_exists($taxonomy) || $taxonomy === 'iss_timeline_group') {
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
        $item_type = sanitize_key((string) $item_type);
        if ($item_type !== '' && $item_type !== 'all') {
            $normalized[] = $item_type;
        }
    }

    return array_values(array_unique($normalized));
}

function iss_timeline_get_requested_resolved_item_types($filters) {
    $filters = is_array($filters) ? $filters : [];

    $selected_type = sanitize_key((string) ($filters['item_type'] ?? ''));
    if ($selected_type !== '' && $selected_type !== 'all') {
        $resolved = iss_timeline_resolve_item_type_value($selected_type);
        return $resolved !== '' ? [$resolved] : [];
    }

    $resolved_types = [];
    $item_types = isset($filters['item_types']) && is_array($filters['item_types']) ? $filters['item_types'] : [];
    foreach ($item_types as $item_type) {
        $resolved = iss_timeline_resolve_item_type_value($item_type);
        if ($resolved !== '') {
            $resolved_types[] = $resolved;
        }
    }

    return array_values(array_unique($resolved_types));
}

function iss_timeline_build_requested_type_meta_query($filters) {
    $filters = is_array($filters) ? $filters : [];
    $resolved_types = iss_timeline_get_requested_resolved_item_types($filters);
    if (empty($resolved_types)) {
        return [];
    }

    $clauses = [];
    foreach ($resolved_types as $resolved_type) {
        if ($resolved_type === 'event') {
            $clauses[] = [
                'relation' => 'AND',
                [
                    'key' => 'item_type',
                    'value' => 'event',
                    'compare' => '=',
                ],
                [
                    'key' => 'source_post_type',
                    'value' => 'veranstaltung',
                    'compare' => '=',
                ],
            ];
            continue;
        }

        if ($resolved_type === 'ausstellung') {
            $clauses[] = [
                'relation' => 'AND',
                [
                    'key' => 'item_type',
                    'value' => 'ausstellung',
                    'compare' => '=',
                ],
                [
                    'key' => 'source_post_type',
                    'value' => 'ausstellung',
                    'compare' => '=',
                ],
            ];
            continue;
        }

        $clauses[] = [
            'key' => 'item_type',
            'value' => $resolved_type,
            'compare' => '=',
        ];
    }

    if (count($clauses) === 1) {
        return $clauses[0];
    }

    $query = ['relation' => 'OR'];
    foreach ($clauses as $clause) {
        $query[] = $clause;
    }

    return $query;
}

function iss_timeline_filters_need_running_ranges($filters) {
    $filters = is_array($filters) ? $filters : [];

    if (empty($filters['include_running_ranges'])) {
        return false;
    }

    $resolved_types = iss_timeline_get_requested_resolved_item_types($filters);
    if (!empty($resolved_types)) {
        return in_array('ausstellung', $resolved_types, true);
    }

    $post_types = isset($filters['post_types']) && is_array($filters['post_types']) ? array_values(array_unique(array_filter(array_map('sanitize_key', $filters['post_types'])))) : [];
    if (!empty($post_types)) {
        return in_array('ausstellung', $post_types, true);
    }

    return true;
}

function iss_timeline_filters_are_only_post_type($filters, $post_type) {
    $filters = is_array($filters) ? $filters : [];
    $post_type = sanitize_key((string) $post_type);
    if ($post_type === '') {
        return false;
    }

    $resolved_types = iss_timeline_get_requested_resolved_item_types($filters);
    if (!empty($resolved_types)) {
        return count($resolved_types) === 1 && $resolved_types[0] === $post_type;
    }

    $post_types = isset($filters['post_types']) && is_array($filters['post_types'])
        ? array_values(array_unique(array_filter(array_map('sanitize_key', $filters['post_types']))))
        : [];

    return count($post_types) === 1 && $post_types[0] === $post_type;
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

    $date_start = isset($filters['date_start']) ? sanitize_text_field((string) $filters['date_start']) : '';
    $date_end = isset($filters['date_end']) ? sanitize_text_field((string) $filters['date_end']) : '';

    $type = sanitize_key((string) ($filters['item_type'] ?? ''));
    $item_types = iss_timeline_normalize_item_types($filters['item_types'] ?? []);

    $source_post_ids = isset($filters['source_post_ids']) ? $filters['source_post_ids'] : [];
    $source_post_ids = array_values(array_unique(array_filter(array_map('intval', is_array($source_post_ids) ? $source_post_ids : [$source_post_ids]))));
    $offset = isset($args['offset']) ? max(0, (int) $args['offset']) : 0;
    $include_running_ranges = !array_key_exists('include_running_ranges', $filters) || (bool) $filters['include_running_ranges'];

    return [
        'limit' => $limit,
        'offset' => $offset,
        'order' => $order,
        'time_mode' => $time_mode,
        'month' => $month,
        'date_start' => $date_start,
        'date_end' => $date_end,
        'item_type' => $type,
        'item_types' => $item_types,
        'item_groups' => iss_timeline_normalize_item_group_filters($args),
        'post_types' => iss_timeline_normalize_post_types($filters['post_types'] ?? []),
        'source_post_ids' => $source_post_ids,
        'source_taxonomy_filters' => iss_timeline_normalize_source_taxonomy_filters($filters['taxonomy_filters'] ?? []),
        'include_running_ranges' => $include_running_ranges,
        'meta_filters' => isset($filters['meta_filters']) && is_array($filters['meta_filters']) ? $filters['meta_filters'] : [],
    ];
}

function iss_timeline_resolve_source_post_ids_for_filters($filters) {
    $filters = is_array($filters) ? $filters : [];

    $seed_ids = isset($filters['source_post_ids']) && is_array($filters['source_post_ids'])
        ? array_values(array_unique(array_filter(array_map('intval', $filters['source_post_ids']))))
        : [];
    $post_types = isset($filters['post_types']) && is_array($filters['post_types']) ? $filters['post_types'] : [];
    $tax_filters = isset($filters['source_taxonomy_filters']) && is_array($filters['source_taxonomy_filters']) ? $filters['source_taxonomy_filters'] : [];

    if (empty($post_types) && empty($tax_filters)) {
        return $seed_ids;
    }

    if (empty($post_types)) {
        $post_types = [];
        foreach ($tax_filters as $filter) {
            $taxonomy = isset($filter['taxonomy']) ? sanitize_key((string) $filter['taxonomy']) : '';
            if ($taxonomy === '') {
                continue;
            }

            $taxonomy_obj = get_taxonomy($taxonomy);
            if (!$taxonomy_obj || empty($taxonomy_obj->object_type) || !is_array($taxonomy_obj->object_type)) {
                continue;
            }

            foreach ($taxonomy_obj->object_type as $post_type) {
                $post_type = sanitize_key((string) $post_type);
                if ($post_type !== '' && post_type_exists($post_type)) {
                    $post_types[] = $post_type;
                }
            }
        }
        $post_types = array_values(array_unique($post_types));
    }

    if (empty($post_types)) {
        return $seed_ids;
    }

    $tax_query = [];
    foreach ($tax_filters as $tax_filter) {
        $tax_query[] = [
            'taxonomy' => $tax_filter['taxonomy'],
            'field' => $tax_filter['field'],
            'terms' => $tax_filter['terms'],
            'operator' => $tax_filter['operator'],
        ];
    }

    $query_args = [
        'post_type' => $post_types,
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ];

    if (!empty($tax_query)) {
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        $query_args['tax_query'] = $tax_query;
    }

    $matched_ids = get_posts($query_args);
    $matched_ids = array_values(array_unique(array_filter(array_map('intval', is_array($matched_ids) ? $matched_ids : []))));

    if (!empty($seed_ids)) {
        if (empty($matched_ids)) {
            return [];
        }
        $matched_ids = array_values(array_intersect($matched_ids, $seed_ids));
    }

    return $matched_ids;
}

function iss_timeline_build_time_meta_query($filters) {
    $filters = is_array($filters) ? $filters : [];
    $meta_query = [];
    $include_running_ranges = iss_timeline_filters_need_running_ranges($filters);

    $time_mode = sanitize_key((string) ($filters['time_mode'] ?? 'all'));
    if (!$include_running_ranges) {
        if ($time_mode === 'upcoming') {
            $meta_query[] = [
                'key' => 'sort_date',
                'value' => iss_timeline_get_now_mysql(),
                'compare' => '>=',
                'type' => 'DATETIME',
            ];
            $meta_query[] = [
                'key' => 'sort_date',
                'value' => iss_timeline_get_future_horizon_end_mysql(),
                'compare' => '<=',
                'type' => 'DATETIME',
            ];
        } elseif ($time_mode === 'past') {
            $meta_query[] = [
                'key' => 'sort_date',
                'value' => iss_timeline_get_now_mysql(),
                'compare' => '<',
                'type' => 'DATETIME',
            ];
        } elseif ($time_mode === 'month') {
            $month = isset($filters['month']) ? (string) $filters['month'] : '';
            $range = iss_timeline_month_to_range($month);
            if (is_array($range)) {
                $meta_query[] = [
                    'key' => 'sort_date',
                    'value' => [$range['start'], $range['end']],
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME',
                ];
            }
        } elseif ($time_mode === 'range') {
            $date_start = trim((string) ($filters['date_start'] ?? ''));
            $date_end = trim((string) ($filters['date_end'] ?? ''));

            if ($date_start !== '' && $date_end !== '') {
                $meta_query[] = [
                    'key' => 'sort_date',
                    'value' => [$date_start, $date_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME',
                ];
            } elseif ($date_start !== '') {
                $meta_query[] = [
                    'key' => 'sort_date',
                    'value' => $date_start,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ];
            } elseif ($date_end !== '') {
                $meta_query[] = [
                    'key' => 'sort_date',
                    'value' => $date_end,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ];
            }
        }

        return $meta_query;
    }

    if ($time_mode === 'upcoming') {
        $range_start = iss_timeline_get_now_mysql();
        $range_end = iss_timeline_get_future_horizon_end_mysql();
        $meta_query[] = [
            'relation' => 'OR',
            [
                'relation' => 'AND',
                [
                    'key' => 'sort_date',
                    'value' => $range_start,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
                [
                    'key' => 'sort_date',
                    'value' => $range_end,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ],
            ],
            [
                'relation' => 'AND',
                [
                    'key' => 'event_end',
                    'value' => $range_start,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
                [
                    'key' => 'sort_date',
                    'value' => $range_end,
                    'compare' => '<=',
                    'type' => 'DATETIME',
                ],
            ],
        ];
    } elseif ($time_mode === 'past') {
        $now = iss_timeline_get_now_mysql();

        // Ausstellung items are synced with a concrete `event_end`, so use a single
        // indexed-style comparison here instead of the broader mixed-date fallback.
        if (iss_timeline_filters_are_only_post_type($filters, 'ausstellung')) {
            $meta_query[] = [
                'key' => 'event_end',
                'value' => $now,
                'compare' => '<',
                'type' => 'DATETIME',
            ];
            return $meta_query;
        }

        $meta_query[] = [
            'relation' => 'OR',
            [
                'key' => 'event_end',
                'value' => $now,
                'compare' => '<',
                'type' => 'DATETIME',
            ],
            [
                'relation' => 'AND',
                [
                    'key' => 'sort_date',
                    'value' => $now,
                    'compare' => '<',
                    'type' => 'DATETIME',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'event_end',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => 'event_end',
                        'value' => '',
                        'compare' => '=',
                    ],
                ],
            ],
        ];
    } elseif ($time_mode === 'month') {
        $month = isset($filters['month']) ? (string) $filters['month'] : '';
        $range = iss_timeline_month_to_range($month);
        if (is_array($range)) {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'sort_date',
                    'value' => [$range['start'], $range['end']],
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME',
                ],
                [
                    'relation' => 'AND',
                    [
                        'key' => 'event_end',
                        'value' => $range['start'],
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                    [
                        'key' => 'sort_date',
                        'value' => $range['end'],
                        'compare' => '<=',
                        'type' => 'DATETIME',
                    ],
                ],
            ];
        }
    } elseif ($time_mode === 'range') {
        $date_start = trim((string) ($filters['date_start'] ?? ''));
        $date_end = trim((string) ($filters['date_end'] ?? ''));

        if ($date_start !== '' && $date_end !== '') {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'sort_date',
                    'value' => [$date_start, $date_end],
                    'compare' => 'BETWEEN',
                    'type' => 'DATETIME',
                ],
                [
                    'relation' => 'AND',
                    [
                        'key' => 'event_end',
                        'value' => $date_start,
                        'compare' => '>=',
                        'type' => 'DATETIME',
                    ],
                    [
                        'key' => 'sort_date',
                        'value' => $date_end,
                        'compare' => '<=',
                        'type' => 'DATETIME',
                    ],
                ],
            ];
        } elseif ($date_start !== '') {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'sort_date',
                    'value' => $date_start,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
                [
                    'key' => 'event_end',
                    'value' => $date_start,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
            ];
        } elseif ($date_end !== '') {
            $meta_query[] = [
                'key' => 'sort_date',
                'value' => $date_end,
                'compare' => '<=',
                'type' => 'DATETIME',
            ];
        }
    }

    return $meta_query;
}

function iss_timeline_build_custom_meta_filters($filters) {
    $filters = is_array($filters) ? $filters : [];
    $meta_filters = isset($filters['meta_filters']) && is_array($filters['meta_filters']) ? $filters['meta_filters'] : [];
    $normalized = [];

    foreach ($meta_filters as $meta_filter) {
        if (!is_array($meta_filter)) {
            continue;
        }

        $key = sanitize_key((string) ($meta_filter['key'] ?? ''));
        if ($key === '') {
            continue;
        }

        $compare = strtoupper(sanitize_text_field((string) ($meta_filter['compare'] ?? '=')));
        if (!in_array($compare, ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'IN', 'NOT IN', 'BETWEEN', 'EXISTS', 'NOT EXISTS'], true)) {
            $compare = '=';
        }

        $type = strtoupper(sanitize_text_field((string) ($meta_filter['type'] ?? 'CHAR')));
        if (!in_array($type, ['CHAR', 'NUMERIC', 'BINARY', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'TIME', 'UNSIGNED'], true)) {
            $type = 'CHAR';
        }

        $clause = [
            'key' => $key,
            'compare' => $compare,
            'type' => $type,
        ];

        if (!in_array($compare, ['EXISTS', 'NOT EXISTS'], true)) {
            $value = $meta_filter['value'] ?? '';
            if (in_array($compare, ['IN', 'NOT IN', 'BETWEEN'], true)) {
                $value = is_array($value) ? $value : [$value];
            }
            $clause['value'] = $value;
        }

        $normalized[] = $clause;
    }

    return $normalized;
}

function iss_timeline_build_core_meta_query($filters) {
    $filters = is_array($filters) ? $filters : [];

    $meta_query = [iss_timeline_build_visibility_meta_query()];

    $type_q = iss_timeline_build_requested_type_meta_query($filters);
    if (!empty($type_q)) {
        $meta_query[] = $type_q;
    }

    if (!empty($filters['post_types'])) {
        $meta_query[] = [
            'key' => 'source_post_type',
            'value' => $filters['post_types'],
            'compare' => 'IN',
        ];
    }

    if (!empty($filters['source_post_ids']) || !empty($filters['source_taxonomy_filters'])) {
        $source_post_ids = iss_timeline_resolve_source_post_ids_for_filters($filters);
        if (!empty($source_post_ids)) {
            $meta_query[] = [
                'key' => 'source_post_id',
                'value' => $source_post_ids,
                'compare' => 'IN',
                'type' => 'NUMERIC',
            ];
        } else {
            $meta_query[] = [
                'key' => 'source_post_id',
                'value' => -1,
                'compare' => '=',
                'type' => 'NUMERIC',
            ];
        }
    }

    $meta_query = array_merge($meta_query, iss_timeline_build_custom_meta_filters($filters));

    return $meta_query;
}

function iss_timeline_build_meta_query($filters, $time_meta_query = null) {
    $filters = is_array($filters) ? $filters : [];

    $meta_query = iss_timeline_build_core_meta_query($filters);
    if ($time_meta_query === null) {
        $time_meta_query = iss_timeline_build_time_meta_query($filters);
    }

    if (is_array($time_meta_query) && !empty($time_meta_query)) {
        $meta_query = array_merge($meta_query, $time_meta_query);
    }

    return $meta_query;
}

function iss_timeline_build_tax_query($filters) {
    $filters = is_array($filters) ? $filters : [];
    $groups = isset($filters['item_groups']) && is_array($filters['item_groups']) ? $filters['item_groups'] : [];

    if (empty($groups) || !taxonomy_exists('iss_timeline_group')) {
        return [];
    }

    return [[
        'taxonomy' => 'iss_timeline_group',
        'field' => 'slug',
        'terms' => $groups,
    ]];
}

function iss_timeline_build_query_args_from_filters($filters, $time_meta_query = null) {
    $post_type = function_exists('iss_timeline_get_post_type') ? iss_timeline_get_post_type() : 'iss_calendar_item';
    $filters = is_array($filters) ? $filters : [];

    $limit = (int) ($filters['limit'] ?? 50);
    if ($limit <= 0) {
        $limit = -1;
    }
    $offset = max(0, (int) ($filters['offset'] ?? 0));

    $query_args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'no_found_rows' => true,
        'meta_key' => 'sort_date',
        'orderby' => 'meta_value',
        'meta_type' => 'DATETIME',
        'order' => $filters['order'],
        'meta_query' => iss_timeline_build_meta_query($filters, $time_meta_query),
    ];

    $tax_query = iss_timeline_build_tax_query($filters);
    if (!empty($tax_query)) {
        $query_args['tax_query'] = $tax_query;
    }

    return $query_args;
}

function iss_timeline_build_query_args($args = []) {
    $filters = iss_timeline_normalize_filter_payload($args);
    return iss_timeline_build_query_args_from_filters($filters);
}

function iss_timeline_build_split_time_meta_queries($filters) {
    $filters = is_array($filters) ? $filters : [];
    if (!iss_timeline_filters_need_running_ranges($filters)) {
        return [];
    }

    $time_mode = sanitize_key((string) ($filters['time_mode'] ?? 'all'));
    $now = iss_timeline_get_now_mysql();

    if ($time_mode === 'upcoming') {
        $primary_filters = $filters;
        $primary_filters['include_running_ranges'] = false;

        return [
            iss_timeline_build_time_meta_query($primary_filters),
            [
                [
                    'key' => 'event_end',
                    'value' => $now,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
                [
                    'key' => 'sort_date',
                    'value' => $now,
                    'compare' => '<',
                    'type' => 'DATETIME',
                ],
            ],
        ];
    }

    if ($time_mode === 'past') {
        if (iss_timeline_filters_are_only_post_type($filters, 'ausstellung')) {
            return [];
        }

        return [
            [
                [
                    'key' => 'event_end',
                    'value' => $now,
                    'compare' => '<',
                    'type' => 'DATETIME',
                ],
            ],
            [
                [
                    'key' => 'sort_date',
                    'value' => $now,
                    'compare' => '<',
                    'type' => 'DATETIME',
                ],
                [
                    'relation' => 'OR',
                    [
                        'key' => 'event_end',
                        'compare' => 'NOT EXISTS',
                    ],
                    [
                        'key' => 'event_end',
                        'value' => '',
                        'compare' => '=',
                    ],
                ],
            ],
        ];
    }

    if ($time_mode === 'month') {
        $range = iss_timeline_month_to_range((string) ($filters['month'] ?? ''));
        if (!is_array($range)) {
            return [];
        }

        $primary_filters = $filters;
        $primary_filters['include_running_ranges'] = false;

        return [
            iss_timeline_build_time_meta_query($primary_filters),
            [
                [
                    'key' => 'event_end',
                    'value' => $range['start'],
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
                [
                    'key' => 'sort_date',
                    'value' => $range['start'],
                    'compare' => '<',
                    'type' => 'DATETIME',
                ],
            ],
        ];
    }

    if ($time_mode === 'range') {
        $date_start = trim((string) ($filters['date_start'] ?? ''));

        if ($date_start === '') {
            return [];
        }

        $primary_filters = $filters;
        $primary_filters['include_running_ranges'] = false;

        return [
            iss_timeline_build_time_meta_query($primary_filters),
            [
                [
                    'key' => 'event_end',
                    'value' => $date_start,
                    'compare' => '>=',
                    'type' => 'DATETIME',
                ],
                [
                    'key' => 'sort_date',
                    'value' => $date_start,
                    'compare' => '<',
                    'type' => 'DATETIME',
                ],
            ],
        ];
    }

    return [];
}

function iss_timeline_build_split_item_type_filters($filters) {
    $filters = is_array($filters) ? $filters : [];
    $resolved_types = iss_timeline_get_requested_resolved_item_types($filters);
    if (count($resolved_types) <= 1) {
        return [];
    }

    $split_filters = [];
    foreach ($resolved_types as $resolved_type) {
        $single_filters = $filters;
        $single_filters['item_type'] = $resolved_type;
        $single_filters['item_types'] = [];
        $single_filters['limit'] = -1;
        $single_filters['offset'] = 0;
        $split_filters[] = $single_filters;
    }

    return $split_filters;
}

function iss_timeline_query_ids_by_time_meta($filters, $time_meta_query) {
    $query_args = iss_timeline_build_query_args_from_filters($filters, $time_meta_query);
    $query_args['posts_per_page'] = -1;
    $query_args['offset'] = 0;
    $query_args['fields'] = 'ids';
    $query_args['update_post_meta_cache'] = false;
    $query_args['update_post_term_cache'] = false;

    $query = new WP_Query($query_args);

    return array_values(array_unique(array_filter(array_map('intval', is_array($query->posts) ? $query->posts : []))));
}

function iss_timeline_query_ids_for_filters($filters) {
    $filters = is_array($filters) ? $filters : [];
    $split_time_meta_queries = iss_timeline_build_split_time_meta_queries($filters);

    if (empty($split_time_meta_queries)) {
        $query_args = iss_timeline_build_query_args_from_filters($filters);
        $query_args['fields'] = 'ids';
        $query_args['update_post_meta_cache'] = false;
        $query_args['update_post_term_cache'] = false;

        $query = new WP_Query($query_args);
        return array_values(array_unique(array_filter(array_map('intval', is_array($query->posts) ? $query->posts : []))));
    }

    $merged_ids = [];
    foreach ($split_time_meta_queries as $time_meta_query) {
        $merged_ids = array_merge($merged_ids, iss_timeline_query_ids_by_time_meta($filters, $time_meta_query));
    }

    $merged_ids = iss_timeline_sort_ids_by_sort_date($merged_ids, $filters['order'] ?? 'ASC');
    if (empty($merged_ids)) {
        return [];
    }

    $offset = max(0, (int) ($filters['offset'] ?? 0));
    $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;
    if ($limit > 0) {
        $merged_ids = array_slice($merged_ids, $offset, $limit);
    } elseif ($offset > 0) {
        $merged_ids = array_slice($merged_ids, $offset);
    }

    return $merged_ids;
}

function iss_timeline_sort_ids_by_sort_date($ids, $order = 'ASC') {
    $ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
    if (count($ids) < 2) {
        return $ids;
    }

    $order = strtoupper(sanitize_text_field((string) $order));
    if (!in_array($order, ['ASC', 'DESC'], true)) {
        $order = 'ASC';
    }

    update_meta_cache('post', $ids);

    $sort_map = [];
    foreach ($ids as $id) {
        $sort_map[$id] = (string) get_post_meta($id, 'sort_date', true);
    }

    usort($ids, static function ($left, $right) use ($sort_map, $order) {
        $left_value = $sort_map[$left] ?? '';
        $right_value = $sort_map[$right] ?? '';

        if ($left_value === $right_value) {
            return $order === 'DESC' ? ($right <=> $left) : ($left <=> $right);
        }

        $comparison = strcmp($left_value, $right_value);
        return $order === 'DESC' ? -$comparison : $comparison;
    });

    return $ids;
}

function iss_timeline_load_posts_by_ids($ids) {
    $ids = array_values(array_unique(array_filter(array_map('intval', is_array($ids) ? $ids : []))));
    if (empty($ids)) {
        return [];
    }

    $post_type = function_exists('iss_timeline_get_post_type') ? iss_timeline_get_post_type() : 'iss_calendar_item';
    $posts = get_posts([
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => count($ids),
        'post__in' => $ids,
        'orderby' => 'post__in',
        'no_found_rows' => true,
    ]);

    if (empty($posts)) {
        return [];
    }

    $posts_by_id = [];
    foreach ($posts as $post) {
        if ($post instanceof WP_Post) {
            $posts_by_id[$post->ID] = $post;
        }
    }

    $ordered_posts = [];
    foreach ($ids as $id) {
        if (isset($posts_by_id[$id])) {
            $ordered_posts[] = $posts_by_id[$id];
        }
    }

    return $ordered_posts;
}

function iss_timeline_get_items_advanced($args = []) {
    $filters = iss_timeline_normalize_filter_payload($args);
    $split_type_filters = iss_timeline_build_split_item_type_filters($filters);

    if (!empty($split_type_filters)) {
        $merged_ids = [];
        foreach ($split_type_filters as $single_filters) {
            $merged_ids = array_merge($merged_ids, iss_timeline_query_ids_for_filters($single_filters));
        }

        $merged_ids = iss_timeline_sort_ids_by_sort_date($merged_ids, $filters['order'] ?? 'ASC');
        if (empty($merged_ids)) {
            return [];
        }

        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        if ($limit > 0) {
            $merged_ids = array_slice($merged_ids, $offset, $limit);
        } elseif ($offset > 0) {
            $merged_ids = array_slice($merged_ids, $offset);
        }

        return iss_timeline_load_posts_by_ids($merged_ids);
    }

    return iss_timeline_load_posts_by_ids(iss_timeline_query_ids_for_filters($filters));
}
