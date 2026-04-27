<?php
if (!defined('ABSPATH')) exit;

function iss_timeline_extract_teaser_text($post_id, $word_limit = 28) {
    $post_id = (int) $post_id;
    $word_limit = (int) $word_limit;
    if ($post_id <= 0) return '';
    if ($word_limit <= 0) $word_limit = 28;

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) return '';

    if (has_excerpt($post_id)) {
        $ex = trim((string) get_the_excerpt($post_id));
        if ($ex !== '') {
            return wp_trim_words(wp_strip_all_tags($ex), $word_limit);
        }
    }

    $content = trim((string) $post->post_content);
    if ($content === '') return '';

    if (function_exists('has_blocks') && has_blocks($content) && function_exists('parse_blocks')) {
        $blocks = parse_blocks($content);

        $ignore = [
            'core/cover',
            'core/gallery',
            'core/image',
            'core/media-text',
            'core/video',
            'core/audio',
            'core/embed',
            'core/buttons',
            'core/button',
            'core/spacer',
            'core/separator',
        ];

        $allow_text = [
            'core/paragraph',
            'core/heading',
            'core/list',
            'core/quote',
            'core/pullquote',
        ];

        $out = [];
        $walk = function ($block) use (&$walk, &$out, $ignore, $allow_text) {
            if (!is_array($block)) return;
            $name = isset($block['blockName']) ? (string) $block['blockName'] : '';

            if ($name !== '' && in_array($name, $ignore, true)) {
                return;
            }

            if ($name === '' || !in_array($name, $allow_text, true)) {
                if (!empty($block['innerBlocks']) && is_array($block['innerBlocks'])) {
                    foreach ($block['innerBlocks'] as $inner) {
                        $walk($inner);
                    }
                }
                return;
            }

            $html = isset($block['innerHTML']) ? (string) $block['innerHTML'] : '';
            $txt = trim(wp_strip_all_tags($html));
            if ($txt !== '') $out[] = $txt;
        };

        foreach ($blocks as $b) {
            $walk($b);
            if (count($out) >= 3) break;
        }

        $text = trim(implode(' ', $out));
        if ($text !== '') return wp_trim_words($text, $word_limit);
    }

    $plain = trim(wp_strip_all_tags($content));
    if ($plain === '') return '';
    return wp_trim_words($plain, $word_limit);
}

function iss_timeline_prepare_item($item_id) {
    $item_id = (int) $item_id;
    if ($item_id <= 0) return [];

    $sort_date = (string) get_post_meta($item_id, 'sort_date', true);
    $event_end = (string) get_post_meta($item_id, 'event_end', true);

    $public_title = (string) get_post_meta($item_id, 'public_title', true);
    $public_summary = (string) get_post_meta($item_id, 'public_summary', true);
    $item_type = (string) get_post_meta($item_id, 'item_type', true);

    $cta_mode = (string) get_post_meta($item_id, 'cta_mode', true);
    $cta_url = (string) get_post_meta($item_id, 'cta_url', true);
    $cta_label = (string) get_post_meta($item_id, 'cta_label', true);
    $booking_url = (string) get_post_meta($item_id, 'booking_url', true);

    $source_post_id = (int) get_post_meta($item_id, 'source_post_id', true);

    $ts = null;
    $end_ts = null;
    try {
        if ($sort_date !== '') {
            $ts = (new DateTimeImmutable($sort_date, wp_timezone()))->getTimestamp();
        }
    } catch (Throwable $e) {
        $ts = null;
    }
    try {
        if ($event_end !== '') {
            $end_ts = (new DateTimeImmutable($event_end, wp_timezone()))->getTimestamp();
        }
    } catch (Throwable $e) {
        $end_ts = null;
    }

    $date_label = $ts ? wp_date('j. F Y', $ts) : $sort_date;
    $day_label = $ts ? wp_date('D. d.m.', $ts) : $date_label;

    $time_label = '';
    if ($ts) {
        $start_date_key = wp_date('Y-m-d', $ts, wp_timezone());
        $start_time_key = wp_date('H:i', $ts, wp_timezone());
        $end_date_key = $end_ts ? wp_date('Y-m-d', $end_ts, wp_timezone()) : '';
        $end_time_key = $end_ts ? wp_date('H:i', $end_ts, wp_timezone()) : '';

        if ($end_ts && $start_date_key !== $end_date_key) {
            $time_label = sprintf(__('bis %s', 'iss-timeline'), wp_date('j. F Y', $end_ts, wp_timezone()));
        } elseif ($start_time_key === '00:00' && ($end_time_key === '' || $end_time_key === '23:59' || $end_time_key === '00:00')) {
            $time_label = '';
        } else {
            $time_label = $start_time_key;
            if ($end_ts) {
                $time_label .= ' – ' . $end_time_key;
            }
            $time_label .= ' Uhr';
        }
    }

    $title = trim($public_title);
    if ($title === '' && $source_post_id > 0) {
        $t = get_the_title($source_post_id);
        if (is_string($t) && trim($t) !== '') $title = $t;
    }
    if ($title === '') $title = get_the_title($item_id);

    $summary = trim($public_summary);
    if ($summary === '' && $source_post_id > 0) {
        $summary = iss_timeline_extract_teaser_text($source_post_id, 30);
    }

    return [
        'id' => $item_id,
        'title' => $title,
        'date_raw' => $sort_date,
        'date_label' => $date_label,
        'day_label' => $day_label,
        'time_label' => $time_label,
        'end_raw' => $event_end,
        'type' => $item_type,
        'summary' => $summary,
        'cta_mode' => $cta_mode,
        'cta_url' => $cta_url,
        'cta_label' => $cta_label !== '' ? $cta_label : __('Mehr erfahren', 'iss-timeline'),
        'booking_url' => $booking_url,
        'source_post_id' => $source_post_id,
        'year' => $ts ? (int) wp_date('Y', $ts) : null,
    ];
}

function iss_timeline_build_render_options($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    return [
        'showDetailsButton' => !array_key_exists('showDetailsButton', $attributes) || (bool) $attributes['showDetailsButton'],
        'showRecommendButton' => !array_key_exists('showRecommendButton', $attributes) || (bool) $attributes['showRecommendButton'],
        'showTicketsButton' => !array_key_exists('showTicketsButton', $attributes) || (bool) $attributes['showTicketsButton'],
        'detailsButtonUrl' => isset($attributes['detailsButtonUrl']) ? (string) $attributes['detailsButtonUrl'] : '',
        'recommendButtonUrl' => isset($attributes['recommendButtonUrl']) ? (string) $attributes['recommendButtonUrl'] : '',
        'ticketsButtonUrl' => isset($attributes['ticketsButtonUrl']) ? (string) $attributes['ticketsButtonUrl'] : '',
        'detailsButtonText' => isset($attributes['detailsButtonText']) ? (string) $attributes['detailsButtonText'] : '',
        'recommendButtonText' => isset($attributes['recommendButtonText']) ? (string) $attributes['recommendButtonText'] : '',
        'ticketsButtonText' => isset($attributes['ticketsButtonText']) ? (string) $attributes['ticketsButtonText'] : '',
        'showBottomButton' => !empty($attributes['showBottomButton']) && (bool) $attributes['showBottomButton'],
        'bottomButtonUrl' => isset($attributes['bottomButtonUrl']) ? (string) $attributes['bottomButtonUrl'] : '',
        'bottomButtonText' => isset($attributes['bottomButtonText']) ? (string) $attributes['bottomButtonText'] : '',
    ];
}

function iss_timeline_render_bottom_button($opts = []) {
    $opts = is_array($opts) ? $opts : [];
    $url = isset($opts['bottomButtonUrl']) ? esc_url_raw((string) $opts['bottomButtonUrl']) : '';
    $show_bottom = !empty($opts['showBottomButton']) || $url !== '';
    if (!$show_bottom) return '';
    if ($url === '') return '';

    $label = isset($opts['bottomButtonText']) ? trim(sanitize_text_field((string) $opts['bottomButtonText'])) : '';
    if ($label === '') {
        $label = __('Zum gesamten Kalender', 'iss-timeline');
    }

    return '<div class="iss-timeline__footer"><a class="iss-timeline__btn iss-timeline__btn--primary iss-timeline__btn--bottom" href="'
        . esc_url($url) . '">' . esc_html($label) . '</a></div>';
}

function iss_timeline_build_actions($row, $opts = []) {
    if (!is_array($row)) return [];
    $opts = is_array($opts) ? $opts : [];
    $mode = isset($row['cta_mode']) ? sanitize_key((string) $row['cta_mode']) : '';
    $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
    $show_details = !array_key_exists('showDetailsButton', $opts) || (bool) $opts['showDetailsButton'];
    $show_recommend = !array_key_exists('showRecommendButton', $opts) || (bool) $opts['showRecommendButton'];
    $show_tickets = !array_key_exists('showTicketsButton', $opts) || (bool) $opts['showTicketsButton'];

    $details_override = isset($opts['detailsButtonUrl']) ? esc_url_raw((string) $opts['detailsButtonUrl']) : '';
    $recommend_override = isset($opts['recommendButtonUrl']) ? esc_url_raw((string) $opts['recommendButtonUrl']) : '';
    $tickets_override = isset($opts['ticketsButtonUrl']) ? esc_url_raw((string) $opts['ticketsButtonUrl']) : '';
    $details_label = isset($opts['detailsButtonText']) ? trim(sanitize_text_field((string) $opts['detailsButtonText'])) : '';
    $recommend_label = isset($opts['recommendButtonText']) ? trim(sanitize_text_field((string) $opts['recommendButtonText'])) : '';
    $tickets_label = isset($opts['ticketsButtonText']) ? trim(sanitize_text_field((string) $opts['ticketsButtonText'])) : '';
    if ($details_label === '') $details_label = __('Details anschauen', 'iss-timeline');
    if ($recommend_label === '') $recommend_label = __('Empfehlen', 'iss-timeline');
    if ($tickets_label === '') $tickets_label = __('Tickets kaufen', 'iss-timeline');

    $details_url = '';
    if ($source_post_id > 0) {
        $permalink = get_permalink($source_post_id);
        if (is_string($permalink) && $permalink !== '') {
            $details_url = $permalink;
        }
    }

    $booking_url = isset($row['booking_url']) ? trim((string) $row['booking_url']) : '';
    if ($booking_url === '' && $mode === 'booking' && $source_post_id > 0 && function_exists('iss_calendar_get_next_item_for_post')) {
        $next = iss_calendar_get_next_item_for_post($source_post_id);
        if ($next instanceof WP_Post) {
            $booking_url = trim((string) get_post_meta($next->ID, 'booking_url', true));
        }
    }

    $fallback_url = trim((string) ($row['cta_url'] ?? ''));
    if ($mode === 'external' && $fallback_url !== '') {
        $details_url = $fallback_url;
    } elseif ($details_url === '' && $fallback_url !== '') {
        $details_url = $fallback_url;
    }

    $share_target = $details_url !== '' ? $details_url : $booking_url;
    $recommend_url = '';
    if ($share_target !== '') {
        $subject = rawurlencode(sprintf(__('Empfehlung: %s', 'iss-timeline'), (string) ($row['title'] ?? '')));
        $body = rawurlencode($share_target);
        $recommend_url = 'mailto:?subject=' . $subject . '&body=' . $body;
    }

    if ($details_override !== '') {
        $details_url = $details_override;
    }
    if ($recommend_override !== '') {
        $recommend_url = $recommend_override;
    }
    if ($tickets_override !== '') {
        $booking_url = $tickets_override;
    }

    $actions = [];
    if ($show_details && $details_url !== '') {
        $actions[] = [
            'url' => $details_url,
            'label' => $details_label,
            'variant' => 'secondary',
        ];
    }
    if ($show_recommend && $recommend_url !== '') {
        $actions[] = [
            'url' => $recommend_url,
            'label' => $recommend_label,
            'variant' => 'secondary',
        ];
    }
    if ($show_tickets && $booking_url !== '') {
        $actions[] = [
            'url' => $booking_url,
            'label' => $tickets_label,
            'variant' => 'primary',
        ];
    }

    return $actions;
}

function iss_timeline_render_items_list($items, $opts = []) {
    $opts = is_array($opts) ? $opts : [];
    $yearGrouping = array_key_exists('yearGrouping', $opts) ? (bool) $opts['yearGrouping'] : true;
    $order = isset($opts['order']) ? strtoupper((string) $opts['order']) : 'ASC';
    if (!in_array($order, ['ASC', 'DESC'], true)) $order = 'ASC';

    if (empty($items)) {
        return '<p class="iss-timeline__empty">' . esc_html__('Keine Einträge gefunden.', 'iss-timeline') . '</p>';
    }

    $rows = array_map(function ($post) {
        $id = ($post instanceof WP_Post) ? $post->ID : (int) $post;
        return iss_timeline_prepare_item($id);
    }, $items);

    $groups = [];
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $key = $yearGrouping ? ($row['year'] ?? '—') : 'all';
        if (!isset($groups[$key])) $groups[$key] = [];
        $groups[$key][] = $row;
    }

    if ($yearGrouping) {
        if ($order === 'DESC') {
            krsort($groups, SORT_NUMERIC);
        } else {
            ksort($groups, SORT_NUMERIC);
        }
    }

    $out = '';
    foreach ($groups as $year => $groupRows) {
        if ($yearGrouping) {
            $out .= '<div class="iss-timeline__year">';
            $out .= '<h3 class="iss-timeline__year-label">' . esc_html((string) $year) . '</h3>';
        }

        foreach ($groupRows as $row) {
            $out .= '<article class="iss-timeline__item">';
            $out .= '<div class="iss-timeline__date">';
            $out .= '<div class="iss-timeline__day">' . esc_html((string) ($row['day_label'] ?? '')) . '</div>';
            if (!empty($row['time_label'])) {
                $out .= '<div class="iss-timeline__time">' . esc_html((string) $row['time_label']) . '</div>';
            }
            $out .= '</div>';
            $out .= '<div class="iss-timeline__content">';
            $out .= '<h4 class="iss-timeline__title">' . esc_html((string) ($row['title'] ?? '')) . '</h4>';
            if (!empty($row['summary'])) {
                $out .= '<div class="iss-timeline__summary">' . esc_html((string) $row['summary']) . '</div>';
            } elseif (!empty($row['type'])) {
                $out .= '<div class="iss-timeline__summary">' . esc_html((string) $row['type']) . '</div>';
            }

            $actions = iss_timeline_build_actions($row, $opts);
            if (!empty($actions)) {
                $out .= '<div class="iss-timeline__actions">';
                foreach ($actions as $action) {
                    if (!is_array($action) || empty($action['url'])) continue;
                    $variant = isset($action['variant']) ? sanitize_key((string) $action['variant']) : 'secondary';
                    if (!in_array($variant, ['secondary', 'primary'], true)) {
                        $variant = 'secondary';
                    }
                    $out .= '<a class="iss-timeline__btn iss-timeline__btn--' . esc_attr($variant) . '" href="'
                        . esc_url((string) $action['url']) . '">'
                        . esc_html((string) ($action['label'] ?? '')) . '</a>';
                }
                $out .= '</div>';
            }
            $out .= '</div></article>';
        }

        if ($yearGrouping) {
            $out .= '</div>';
        }
    }

    return $out;
}

function iss_timeline_get_listing_response($query_args = [], $render_opts = []) {
    $query_args = is_array($query_args) ? $query_args : [];
    $render_opts = is_array($render_opts) ? $render_opts : [];
    $offset = isset($query_args['offset']) ? max(0, (int) $query_args['offset']) : 0;
    $limit = isset($query_args['limit']) ? (int) $query_args['limit'] : 0;
    $fetch_args = $query_args;
    $use_overscan = $limit > 0;
    if ($use_overscan) {
        $fetch_args['limit'] = $limit + 1;
    }

    $items = function_exists('iss_timeline_get_items_advanced')
        ? iss_timeline_get_items_advanced($fetch_args)
        : [];
    $has_more = false;
    if ($use_overscan && count($items) > $limit) {
        $has_more = true;
        $items = array_slice($items, 0, $limit);
    }
    $visible_count = $offset + count($items);

    return [
        'items' => $items,
        'count' => $visible_count,
        'batchCount' => count($items),
        'isEmpty' => empty($items),
        'offset' => $offset,
        'nextOffset' => $visible_count,
        'hasMore' => $has_more,
        'html' => iss_timeline_render_items_list($items, $render_opts),
    ];
}

function iss_timeline_get_attribute_token_list($values, $default = []) {
    $default = is_array($default) ? $default : [];

    $values = is_array($values) ? array_values(array_unique(array_filter(array_map(static function ($value) {
            return sanitize_key((string) $value);
        }, $values)))) : [];

    if (empty($values)) {
        $values = array_values(array_unique(array_filter(array_map(static function ($value) {
            return sanitize_key((string) $value);
        }, $default))));
    }

    return $values;
}

function iss_timeline_normalize_taxonomy_rule_list($rules) {
    if (!is_array($rules)) {
        return [];
    }

    $normalized = [];
    foreach ($rules as $rule) {
        if (!is_array($rule)) {
            continue;
        }

        $taxonomy = sanitize_key((string) ($rule['taxonomy'] ?? ''));
        if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
            continue;
        }

        $terms = $rule['terms'] ?? [];
        if (!is_array($terms)) {
            $terms = [$terms];
        }
        $terms = array_values(array_unique(array_filter(array_map('sanitize_title', $terms))));
        if (empty($terms)) {
            continue;
        }

        $normalized[] = [
            'taxonomy' => $taxonomy,
            'field' => 'slug',
            'terms' => $terms,
            'operator' => 'IN',
            'label' => isset($rule['label']) ? sanitize_text_field((string) $rule['label']) : '',
        ];
    }

    return $normalized;
}

function iss_timeline_get_taxonomy_preset_rules_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];
    return !empty($attributes['taxonomyPresetRules']) && is_array($attributes['taxonomyPresetRules'])
        ? iss_timeline_normalize_taxonomy_rule_list($attributes['taxonomyPresetRules'])
        : [];
}

function iss_timeline_get_taxonomy_ui_rules_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    if (!empty($attributes['taxonomyUiRules']) && is_array($attributes['taxonomyUiRules'])) {
        $rules = iss_timeline_normalize_taxonomy_rule_list($attributes['taxonomyUiRules']);
        if (!empty($rules)) {
            $ui_rules = [];
            foreach ($rules as $rule) {
                $taxonomy = $rule['taxonomy'];
                $term_query = [
                    'taxonomy' => $taxonomy,
                    'hide_empty' => false,
                    'slug' => $rule['terms'],
                ];
                $terms = get_terms($term_query);
                if (is_wp_error($terms) || empty($terms)) {
                    continue;
                }

                $taxonomy_obj = get_taxonomy($taxonomy);
                $label = $rule['label'] !== ''
                    ? $rule['label']
                    : (($taxonomy_obj && !empty($taxonomy_obj->labels->singular_name)) ? (string) $taxonomy_obj->labels->singular_name : ucfirst($taxonomy));

                $options = [[
                    'value' => '',
                    'label' => __('Alle', 'iss-timeline'),
                ]];
                foreach ($terms as $term) {
                    if (!$term instanceof WP_Term) {
                        continue;
                    }
                    $options[] = [
                        'value' => (string) $term->slug,
                        'label' => (string) $term->name,
                    ];
                }

                if (count($options) > 1) {
                    $ui_rules[] = [
                        'taxonomy' => $taxonomy,
                        'label' => $label,
                        'options' => $options,
                    ];
                }
            }

            if (!empty($ui_rules)) {
                return $ui_rules;
            }
        }
    }

    return [];
}

function iss_timeline_get_type_options_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];
    $allowed_types = iss_timeline_get_attribute_token_list($attributes['allowedTypesList'] ?? [], ['fuehrungen', 'veranstaltungen']);

    $labels = [
        'all' => __('Alle', 'iss-timeline'),
        'fuehrungen' => __('Führungen', 'iss-timeline'),
        'veranstaltungen' => __('Veranstaltungen', 'iss-timeline'),
        'event' => __('Veranstaltungen', 'iss-timeline'),
        'tour' => __('Führungen', 'iss-timeline'),
        'ausstellungen' => __('Ausstellungen', 'iss-timeline'),
        'ausstellung' => __('Ausstellungen', 'iss-timeline'),
    ];

    $options = [['value' => 'all', 'label' => $labels['all']]];
    foreach ($allowed_types as $type) {
        $options[] = [
            'value' => $type,
            'label' => $labels[$type] ?? ucfirst($type),
        ];
    }

    return $options;
}

function iss_timeline_get_post_type_options_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];
    $post_types = iss_timeline_get_attribute_token_list($attributes['postTypesList'] ?? [], []);

    if (empty($post_types)) {
        return [];
    }

    $options = [[
        'value' => '',
        'label' => __('Alle', 'iss-timeline'),
    ]];

    foreach ($post_types as $post_type) {
        if (!post_type_exists($post_type)) {
            continue;
        }

        $post_type_obj = get_post_type_object($post_type);
        $label = $post_type_obj && !empty($post_type_obj->labels->singular_name)
            ? (string) $post_type_obj->labels->singular_name
            : ucfirst($post_type);

        $options[] = [
            'value' => $post_type,
            'label' => $label,
        ];
    }

    return count($options) > 1 ? $options : [];
}

function iss_timeline_get_time_mode_options_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];
    $allowed_modes = iss_timeline_get_attribute_token_list($attributes['allowedTimeModesList'] ?? [], ['upcoming', 'month', 'past', 'all']);

    $labels = [
        'upcoming' => __('Kommend', 'iss-timeline'),
        'month' => __('Monat', 'iss-timeline'),
        'past' => __('Archiv', 'iss-timeline'),
        'all' => __('Alle', 'iss-timeline'),
    ];

    $options = [];
    foreach ($allowed_modes as $mode) {
        if (!isset($labels[$mode])) {
            continue;
        }

        $options[] = [
            'value' => $mode,
            'label' => $labels[$mode],
        ];
    }

    return $options;
}

function iss_timeline_build_query_block_config($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    $group = sanitize_text_field((string) ($attributes['group'] ?? ''));
    $default_month = preg_replace('/[^0-9\-]/', '', (string) ($attributes['defaultMonth'] ?? ''));
    if (!preg_match('/^\d{4}-\d{2}$/', $default_month)) {
        $default_month = wp_date('Y-m', null, wp_timezone());
    }

    $default_type = sanitize_key((string) ($attributes['defaultType'] ?? 'all'));
    if ($default_type === '') {
        $default_type = 'all';
    }

    return [
        'limit' => max(1, (int) ($attributes['limit'] ?? 12)),
        'order' => 'ASC',
        'groups' => $group !== '' ? [$group] : [],
        'filters' => [
            'time_mode' => sanitize_key((string) ($attributes['timeMode'] ?? 'upcoming')) ?: 'upcoming',
            'month' => $default_month,
            'item_type' => $default_type,
            'item_types' => iss_timeline_get_attribute_token_list($attributes['presetItemTypesList'] ?? [], []),
            'include_running_ranges' => !empty($attributes['includeRunningRanges']),
            'post_types' => iss_timeline_get_attribute_token_list($attributes['postTypesList'] ?? [], []),
            'taxonomy_filters' => iss_timeline_get_taxonomy_preset_rules_from_attributes($attributes),
        ],
        'render' => [
            'yearGrouping' => !empty($attributes['yearGrouping']),
            'showMeta' => !array_key_exists('showMeta', $attributes) || (bool) $attributes['showMeta'],
            'showDetailsButton' => !array_key_exists('showDetailsButton', $attributes) || (bool) $attributes['showDetailsButton'],
            'showRecommendButton' => !array_key_exists('showRecommendButton', $attributes) || (bool) $attributes['showRecommendButton'],
            'showTicketsButton' => !array_key_exists('showTicketsButton', $attributes) || (bool) $attributes['showTicketsButton'],
            'showLoadMore' => !empty($attributes['showLoadMore']),
            'showBottomButton' => !empty($attributes['showBottomButton']),
            'loadMoreText' => isset($attributes['loadMoreText']) ? (string) $attributes['loadMoreText'] : '',
            'bottomButtonText' => isset($attributes['bottomButtonText']) ? (string) $attributes['bottomButtonText'] : '',
            'bottomButtonUrl' => isset($attributes['bottomButtonUrl']) ? (string) $attributes['bottomButtonUrl'] : '',
            'detailsButtonText' => isset($attributes['detailsButtonText']) ? (string) $attributes['detailsButtonText'] : '',
            'recommendButtonText' => isset($attributes['recommendButtonText']) ? (string) $attributes['recommendButtonText'] : '',
            'ticketsButtonText' => isset($attributes['ticketsButtonText']) ? (string) $attributes['ticketsButtonText'] : '',
            'detailsButtonUrl' => isset($attributes['detailsButtonUrl']) ? (string) $attributes['detailsButtonUrl'] : '',
            'recommendButtonUrl' => isset($attributes['recommendButtonUrl']) ? (string) $attributes['recommendButtonUrl'] : '',
            'ticketsButtonUrl' => isset($attributes['ticketsButtonUrl']) ? (string) $attributes['ticketsButtonUrl'] : '',
        ],
        'ui' => [
            'showTimeModeFilter' => !empty($attributes['showTimeModeFilter']),
            'showTypeFilter' => !empty($attributes['showTypeFilter']),
            'showPostTypeFilter' => !empty($attributes['showPostTypeFilter']),
            'showMonthFilter' => !empty($attributes['showMonthFilter']),
            'timeModeOptions' => iss_timeline_get_time_mode_options_from_attributes($attributes),
            'typeOptions' => iss_timeline_get_type_options_from_attributes($attributes),
            'postTypeOptions' => iss_timeline_get_post_type_options_from_attributes($attributes),
            'taxonomyUiFilters' => iss_timeline_get_taxonomy_ui_rules_from_attributes($attributes),
        ],
    ];
}

function iss_timeline_render_query_block($attributes = [], $content = '', $block = null) {
    if (function_exists('iss_programm_enqueue_timeline_query_assets')) {
        iss_programm_enqueue_timeline_query_assets();
    }

    $attributes = is_array($attributes) ? $attributes : [];
    $config = iss_timeline_build_query_block_config($attributes);
    $listing = iss_timeline_get_listing_response($config, $config['render']);

    $title = trim((string) ($attributes['title'] ?? ''));
    $intro = trim((string) ($attributes['intro'] ?? ''));
    $months = iss_timeline_collect_future_month_options(function_exists('iss_timeline_get_future_horizon_months') ? iss_timeline_get_future_horizon_months() : 6);

    $use_block_wrapper = function_exists('get_block_wrapper_attributes') && ($block instanceof WP_Block);
    $attrs = $use_block_wrapper
        ? get_block_wrapper_attributes(['class' => 'iss-timeline-query iss-container'])
        : 'class="iss-timeline-query iss-container"';

    $out = '<section ' . $attrs . ' data-timeline-query data-config="' . esc_attr(wp_json_encode($config)) . '">';

    if ($title !== '') {
        $out .= '<h2 class="iss-timeline__section-title">' . esc_html($title) . '</h2>';
    }
    if ($intro !== '') {
        $out .= '<p class="iss-timeline__summary">' . esc_html($intro) . '</p>';
    }

    if (!empty($config['ui']['showTimeModeFilter']) || !empty($config['ui']['showTypeFilter']) || !empty($config['ui']['showPostTypeFilter']) || !empty($config['ui']['showMonthFilter']) || !empty($config['ui']['taxonomyUiFilters'])) {
        $out .= '<form class="iss-timeline__filters" data-timeline-query-form>';

        if (!empty($config['ui']['showTimeModeFilter']) && !empty($config['ui']['timeModeOptions']) && is_array($config['ui']['timeModeOptions'])) {
            $out .= '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html__('Zeitraum', 'iss-timeline') . '</span>';
            $out .= '<select name="time_mode" data-filter-key="time_mode">';
            foreach ($config['ui']['timeModeOptions'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $value = isset($option['value']) ? sanitize_key((string) $option['value']) : '';
                $label = isset($option['label']) ? (string) $option['label'] : $value;
                if ($value === '') {
                    continue;
                }
                $out .= '<option value="' . esc_attr($value) . '"' . selected($config['filters']['time_mode'], $value, false) . '>' . esc_html($label) . '</option>';
            }
            $out .= '</select></label>';
        }

        if (!empty($config['ui']['showTypeFilter'])) {
            $out .= '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html__('Typ', 'iss-timeline') . '</span>';
            $out .= '<select name="item_type" data-filter-key="item_type">';
            foreach ($config['ui']['typeOptions'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $value = isset($option['value']) ? sanitize_key((string) $option['value']) : '';
                $label = isset($option['label']) ? (string) $option['label'] : $value;
                if ($value === '') {
                    continue;
                }
                $out .= '<option value="' . esc_attr($value) . '"' . selected($config['filters']['item_type'], $value, false) . '>' . esc_html($label) . '</option>';
            }
            $out .= '</select></label>';
        }

        if (!empty($config['ui']['showMonthFilter'])) {
            $out .= '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html__('Monat', 'iss-timeline') . '</span>';
            $month_hidden = ($config['filters']['time_mode'] === 'month') ? '' : ' hidden';
            $out .= '<select name="month" data-filter-key="month"' . $month_hidden . '>';
            foreach ($months as $ym) {
                $out .= '<option value="' . esc_attr($ym) . '"' . selected($config['filters']['month'], $ym, false) . '>' . esc_html(iss_timeline_format_month_label($ym)) . '</option>';
            }
            $out .= '</select></label>';
        }

        if (!empty($config['ui']['showPostTypeFilter']) && !empty($config['ui']['postTypeOptions']) && is_array($config['ui']['postTypeOptions'])) {
            $out .= '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html__('Inhaltstyp', 'iss-timeline') . '</span>';
            $out .= '<select name="post_type" data-filter-key="post_type">';
            foreach ($config['ui']['postTypeOptions'] as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $value = isset($option['value']) ? sanitize_key((string) $option['value']) : '';
                $label = isset($option['label']) ? (string) $option['label'] : $value;
                $selected = count($config['filters']['post_types']) === 1 ? (string) $config['filters']['post_types'][0] : '';
                $out .= '<option value="' . esc_attr($value) . '"' . selected($selected, $value, false) . '>' . esc_html($label) . '</option>';
            }
            $out .= '</select></label>';
        }

        if (!empty($config['ui']['taxonomyUiFilters']) && is_array($config['ui']['taxonomyUiFilters'])) {
            foreach ($config['ui']['taxonomyUiFilters'] as $taxonomy_filter) {
                if (!is_array($taxonomy_filter)) {
                    continue;
                }
                $taxonomy = isset($taxonomy_filter['taxonomy']) ? sanitize_key((string) $taxonomy_filter['taxonomy']) : '';
                if ($taxonomy === '' || empty($taxonomy_filter['options']) || !is_array($taxonomy_filter['options'])) {
                    continue;
                }
                $label = isset($taxonomy_filter['label']) ? (string) $taxonomy_filter['label'] : $taxonomy;
                $out .= '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html($label) . '</span>';
                $out .= '<select name="taxonomy_' . esc_attr($taxonomy) . '" data-filter-taxonomy="' . esc_attr($taxonomy) . '">';
                foreach ($taxonomy_filter['options'] as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    $value = isset($option['value']) ? sanitize_title((string) $option['value']) : '';
                    $option_label = isset($option['label']) ? (string) $option['label'] : $value;
                    $out .= '<option value="' . esc_attr($value) . '">' . esc_html($option_label) . '</option>';
                }
                $out .= '</select></label>';
            }
        }

        $out .= '</form>';
    }

    if (!empty($config['render']['showMeta'])) {
        $count_label = sprintf(
            /* translators: %d number of timeline entries */
            _n('%d Eintrag', '%d Einträge', (int) $listing['count'], 'iss-timeline'),
            (int) $listing['count']
        );
        $meta_class = 'iss-timeline-query__meta';
        if (!empty($listing['isEmpty'])) {
            $meta_class .= ' is-empty';
        }
        $out .= '<p class="' . esc_attr($meta_class) . '" data-timeline-query-meta>';
        $out .= '<span class="iss-timeline-query__count" data-timeline-query-count>' . esc_html($count_label) . '</span>';
        $out .= '<span class="iss-timeline-query__empty-note" data-timeline-query-empty-note>';
        if (!empty($listing['isEmpty'])) {
            $out .= esc_html__('Keine Einträge für die aktuelle Auswahl.', 'iss-timeline');
        }
        $out .= '</span>';
        $out .= '</p>';
    }

    $out .= '<div class="iss-timeline" data-timeline-query-results>';
    $out .= $listing['html'];
    $out .= '</div>';
    if (!empty($config['render']['showLoadMore'])) {
        $load_more_text = trim((string) ($config['render']['loadMoreText'] ?? ''));
        if ($load_more_text === '') {
            $load_more_text = __('Mehr laden', 'iss-timeline');
        }
        $button_hidden = !empty($listing['hasMore']) ? '' : ' hidden';
        $out .= '<div class="iss-timeline-query__load-more"' . $button_hidden . ' data-timeline-query-load-more-wrap>';
        $out .= '<button type="button" class="iss-timeline__btn iss-timeline__btn--secondary" data-timeline-query-load-more>';
        $out .= esc_html($load_more_text);
        $out .= '</button></div>';
    }
    $out .= iss_timeline_render_bottom_button($config['render']);
    $out .= '</section>';

    return $out;
}

function iss_timeline_format_month_label($ym) {
    if (!function_exists('iss_timeline_month_to_range')) return $ym;
    $r = iss_timeline_month_to_range($ym);
    if (!is_array($r)) return $ym;
    try {
        $dt = new DateTimeImmutable($r['start'], wp_timezone());
        return wp_date('F Y', $dt->getTimestamp());
    } catch (Throwable $e) {
        return $ym;
    }
}

function iss_timeline_collect_future_month_options($horizon_months = 12) {
    $horizon_months = (int) $horizon_months;
    if ($horizon_months <= 0) $horizon_months = 12;
    if ($horizon_months > 36) $horizon_months = 36;

    $now = new DateTimeImmutable('now', wp_timezone());
    $months = [];
    for ($i = 0; $i <= $horizon_months; $i++) {
        $ym = $now->modify('first day of this month')->modify('+' . $i . ' months')->format('Y-m');
        $months[] = $ym;
    }
    return $months;
}
