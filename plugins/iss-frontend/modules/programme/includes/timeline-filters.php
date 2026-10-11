<?php

if (!defined('ABSPATH')) { exit; }

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

function iss_timeline_get_fixed_taxonomy_rules_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];
    if (!empty($attributes['fixedTaxonomyRules']) && is_array($attributes['fixedTaxonomyRules'])) {
        return iss_timeline_normalize_taxonomy_rule_list($attributes['fixedTaxonomyRules']);
    }

    return !empty($attributes['taxonomyPresetRules']) && is_array($attributes['taxonomyPresetRules'])
        ? iss_timeline_normalize_taxonomy_rule_list($attributes['taxonomyPresetRules'])
        : [];
}

function iss_timeline_get_taxonomy_preset_rules_from_attributes($attributes = []) {
    return iss_timeline_get_fixed_taxonomy_rules_from_attributes($attributes);
}

function iss_timeline_get_taxonomy_ui_rules_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    if (!empty($attributes['taxonomyUiRules']) && is_array($attributes['taxonomyUiRules'])) {
        $ui_rules = [];
        foreach ($attributes['taxonomyUiRules'] as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $taxonomy = sanitize_key((string) ($rule['taxonomy'] ?? ''));
            if ($taxonomy === '' || !taxonomy_exists($taxonomy)) {
                continue;
            }

            $terms = $rule['terms'] ?? [];
            if (!is_array($terms)) {
                $terms = preg_split('/[\r\n,]+/', (string) $terms);
            }
            $terms = array_values(array_unique(array_filter(array_map('sanitize_title', $terms))));

            $term_query = [
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
            ];
            if (!empty($terms)) {
                $term_query['slug'] = $terms;
            }

            $term_records = get_terms($term_query);
            if (is_wp_error($term_records) || empty($term_records)) {
                continue;
            }

            $taxonomy_obj = get_taxonomy($taxonomy);
            $label = isset($rule['label']) ? sanitize_text_field((string) $rule['label']) : '';
            if ($label === '') {
                $label = ($taxonomy_obj && !empty($taxonomy_obj->labels->singular_name))
                    ? (string) $taxonomy_obj->labels->singular_name
                    : ucfirst($taxonomy);
            }

            $options = [[
                'value' => '',
                'label' => __('Alle', 'iss-timeline'),
            ]];
            foreach ($term_records as $term) {
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

    return [];
}

function iss_timeline_get_type_options_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];
    $allowed_types = iss_timeline_get_attribute_token_list($attributes['allowedTypesList'] ?? [], iss_timeline_get_programme_item_type_tokens());

    $labels = [
        'all' => __('Alle', 'iss-timeline'),
        'fuehrungen' => __('Führungen', 'iss-timeline'),
        'veranstaltungen' => __('Veranstaltungen', 'iss-timeline'),
        'event' => __('Veranstaltungen', 'iss-timeline'),
        'tour' => __('Führungen', 'iss-timeline'),
        'ausstellungen' => __('Ausstellungen', 'iss-timeline'),
        'ausstellung' => __('Ausstellungen', 'iss-timeline'),
        'projekte' => __('Projekte', 'iss-timeline'),
        'projekt' => __('Projekte', 'iss-timeline'),
        'project' => __('Projekte', 'iss-timeline'),
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

function iss_timeline_normalize_preset_button_list($buttons = []) {
    $buttons = is_array($buttons) ? $buttons : [];
    $normalized = [];
    $default_index = -1;
    $allowed_time_modes = ['upcoming', 'month', 'past', 'all'];
    $allowed_range_presets = ['today', 'week', 'month', 'upcoming', 'past'];

    foreach ($buttons as $button) {
        if (!is_array($button)) {
            continue;
        }

        $label = trim(sanitize_text_field((string) ($button['label'] ?? '')));
        if ($label === '') {
            continue;
        }

        $time_mode = sanitize_key((string) ($button['timeMode'] ?? ''));
        if (!in_array($time_mode, $allowed_time_modes, true)) {
            $time_mode = '';
        }

        $range_preset = sanitize_key((string) ($button['rangePreset'] ?? ''));
        if (!in_array($range_preset, $allowed_range_presets, true)) {
            $range_preset = '';
        }

        $taxonomy = sanitize_key((string) ($button['taxonomy'] ?? ''));
        if ($taxonomy !== '' && !taxonomy_exists($taxonomy)) {
            $taxonomy = '';
        }

        $terms = $button['terms'] ?? [];
        if (!is_array($terms)) {
            $terms = preg_split('/[\r\n,]+/', (string) $terms);
        }
        $terms = array_values(array_unique(array_filter(array_map('sanitize_title', $terms))));
        if ($taxonomy === '') {
            $terms = [];
        }

        $is_default = !empty($button['isDefault']);
        if ($is_default && $default_index < 0) {
            $default_index = count($normalized);
        }

        $normalized[] = [
            'label' => $label,
            'timeMode' => $time_mode,
            'rangePreset' => $range_preset,
            'taxonomy' => $taxonomy,
            'terms' => $terms,
            'isDefault' => false,
        ];
    }

    if (!empty($normalized)) {
        if ($default_index < 0) {
            $default_index = 0;
        }

        foreach ($normalized as $index => &$button) {
            $button['isDefault'] = ($index === $default_index);
        }
        unset($button);
    }

    return $normalized;
}

function iss_timeline_get_preset_buttons_from_attributes($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    return !empty($attributes['presetButtons']) && is_array($attributes['presetButtons'])
        ? iss_timeline_normalize_preset_button_list($attributes['presetButtons'])
        : [];
}

function iss_timeline_get_default_preset_button($attributes = []) {
    $buttons = iss_timeline_get_preset_buttons_from_attributes($attributes);
    foreach ($buttons as $button) {
        if (!empty($button['isDefault'])) {
            return $button;
        }
    }

    return [];
}

function iss_timeline_get_range_preset_filters($range_preset = '') {
    $range_preset = sanitize_key((string) $range_preset);
    $timezone = wp_timezone();
    $today = new DateTimeImmutable('today', $timezone);

    if ($range_preset === 'today') {
        return [
            'time_mode' => 'range',
            'date_start' => $today->format('Y-m-d') . ' 00:00:00',
            'date_end' => $today->format('Y-m-d') . ' 23:59:59',
        ];
    }

    if ($range_preset === 'week') {
        $weekday = (int) $today->format('N');
        $start = $today->modify('-' . max(0, $weekday - 1) . ' days');
        $end = $start->modify('+6 days');

        return [
            'time_mode' => 'range',
            'date_start' => $start->format('Y-m-d') . ' 00:00:00',
            'date_end' => $end->format('Y-m-d') . ' 23:59:59',
        ];
    }

    if ($range_preset === 'month') {
        return [
            'time_mode' => 'month',
            'month' => wp_date('Y-m', null, $timezone),
        ];
    }

    if ($range_preset === 'upcoming') {
        return [
            'time_mode' => 'upcoming',
        ];
    }

    if ($range_preset === 'past') {
        return [
            'time_mode' => 'past',
        ];
    }

    return [];
}

function iss_timeline_merge_preset_filters($filters = [], $preset = []) {
    $filters = is_array($filters) ? $filters : [];
    $preset = is_array($preset) ? $preset : [];

    if (!empty($preset['timeMode'])) {
        $filters['time_mode'] = sanitize_key((string) $preset['timeMode']);
    }

    if (!empty($preset['rangePreset'])) {
        $range_filters = iss_timeline_get_range_preset_filters((string) $preset['rangePreset']);
        if (!empty($range_filters)) {
            $filters = array_merge($filters, $range_filters);
            if (($range_filters['time_mode'] ?? '') !== 'range') {
                unset($filters['date_start'], $filters['date_end']);
            }
            if (($range_filters['time_mode'] ?? '') !== 'month') {
                unset($filters['month']);
            }
        }
    }

    if (!isset($filters['taxonomy_filters']) || !is_array($filters['taxonomy_filters'])) {
        $filters['taxonomy_filters'] = [];
    }

    if (!empty($preset['taxonomy']) && !empty($preset['terms']) && is_array($preset['terms'])) {
        $filters['taxonomy_filters'][] = [
            'taxonomy' => sanitize_key((string) $preset['taxonomy']),
            'field' => 'slug',
            'terms' => array_values(array_unique(array_filter(array_map('sanitize_title', $preset['terms'])))),
            'operator' => 'IN',
        ];
    }

    return $filters;
}

function iss_timeline_render_choice_filter($args = []) {
    $args = is_array($args) ? $args : [];

    $name = (string) ($args['name'] ?? '');
    $filter_key = sanitize_key((string) ($args['filter_key'] ?? $name));
    $label = (string) ($args['label'] ?? '');
    $selected = sanitize_key((string) ($args['selected'] ?? ''));
    $options = isset($args['options']) && is_array($args['options']) ? $args['options'] : [];
    $links = isset($args['links']) && is_array($args['links']) ? $args['links'] : [];
    $class_name = trim((string) ($args['className'] ?? ''));

    if ($name === '' || $filter_key === '' || (empty($options) && empty($links))) {
        return '';
    }

    $field_class = 'iss-timeline__filter iss-timeline__filter--choices';
    if ($class_name !== '') {
        $field_class .= ' ' . sanitize_html_class($class_name);
    }

    $out = '<fieldset class="' . esc_attr($field_class) . '">';
    if ($label !== '') {
        $out .= '<legend class="iss-timeline__filter-label">' . esc_html($label) . '</legend>';
    }

    $out .= '<div class="iss-timeline__choice-list">';
    foreach ($options as $index => $option) {
        if (!is_array($option)) {
            continue;
        }

        $value = isset($option['value']) ? sanitize_key((string) $option['value']) : '';
        $option_label = isset($option['label']) ? (string) $option['label'] : $value;
        if ($value === '') {
            continue;
        }

        $id = sprintf('iss-timeline-%s-%d-%s', $filter_key, (int) $index, wp_unique_id());
        $out .= '<label class="iss-timeline__choice" for="' . esc_attr($id) . '">';
        $out .= '<input class="iss-timeline__choice-input" type="radio" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" data-filter-key="' . esc_attr($filter_key) . '"' . checked($selected, $value, false) . ' />';
        $out .= '<span class="iss-timeline__choice-label">' . esc_html($option_label) . '</span>';
        $out .= '</label>';
    }

    foreach ($links as $link) {
        if (!is_array($link)) {
            continue;
        }

        $link_label = trim(sanitize_text_field((string) ($link['label'] ?? '')));
        $link_url = trim((string) ($link['url'] ?? ''));
        if ($link_label === '' || $link_url === '') {
            continue;
        }

        $out .= '<a class="iss-timeline__choice iss-timeline__choice--link" href="' . esc_url($link_url) . '">';
        $out .= '<span class="iss-timeline__choice-label">' . esc_html($link_label) . '</span>';
        $out .= '</a>';
    }
    $out .= '</div>';
    $out .= '</fieldset>';

    return $out;
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

    $fixed_item_types = !empty($attributes['fixedItemTypesList']) && is_array($attributes['fixedItemTypesList'])
        ? iss_timeline_get_attribute_token_list($attributes['fixedItemTypesList'], [])
        : iss_timeline_get_attribute_token_list($attributes['presetItemTypesList'] ?? [], []);
    $post_types = iss_timeline_get_attribute_token_list($attributes['postTypesList'] ?? [], []);
    if (empty($post_types) && !iss_timeline_attributes_include_projects($attributes)) {
        $post_types = iss_timeline_get_programme_source_post_types();
    }

    return [
        'limit' => max(1, (int) ($attributes['limit'] ?? 12)),
        'order' => 'ASC',
        'groups' => $group !== '' ? [$group] : [],
        'filters' => [
            'time_mode' => sanitize_key((string) ($attributes['timeMode'] ?? 'upcoming')) ?: 'upcoming',
            'month' => $default_month,
            'item_type' => $default_type,
            'item_types' => $fixed_item_types,
            'include_running_ranges' => !empty($attributes['includeRunningRanges']),
            'post_types' => $post_types,
            'taxonomy_filters' => iss_timeline_get_fixed_taxonomy_rules_from_attributes($attributes),
        ],
        'render' => [
            'renderMode' => (($attributes['renderMode'] ?? 'timeline') === 'cards') ? 'cards' : 'timeline',
            'showCardImage' => !array_key_exists('showCardImage', $attributes) || (bool) $attributes['showCardImage'],
            'showCardSummary' => !array_key_exists('showCardSummary', $attributes) || (bool) $attributes['showCardSummary'],
            'yearGrouping' => !empty($attributes['yearGrouping']),
            'groupRecurringTours' => !empty($attributes['groupRecurringTours']),
            'groupRecurringToursByMonth' => !empty($attributes['groupRecurringToursByMonth']),
            'expandGroupedOccurrences' => !empty($attributes['expandGroupedOccurrences']),
            'groupedOccurrenceDisplay' => isset($attributes['groupedOccurrenceDisplay']) ? (string) $attributes['groupedOccurrenceDisplay'] : 'auto',
            'groupedOccurrencePickerThreshold' => isset($attributes['groupedOccurrencePickerThreshold']) ? (int) $attributes['groupedOccurrencePickerThreshold'] : 2,
            'showRecurringNote' => !array_key_exists('showRecurringNote', $attributes) || (bool) $attributes['showRecurringNote'],
            'showNextOccurrenceLabel' => !empty($attributes['showNextOccurrenceLabel']),
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
            'showItemTypeFilter' => array_key_exists('showItemTypeFilter', $attributes)
                ? !empty($attributes['showItemTypeFilter'])
                : !empty($attributes['showTypeFilter']),
            'showPostTypeFilter' => !empty($attributes['showPostTypeFilter']),
            'showMonthFilter' => !empty($attributes['showMonthFilter']),
            'showCalendarBridge' => !empty($attributes['showCalendarBridge']),
            'timeModeOptions' => iss_timeline_get_time_mode_options_from_attributes($attributes),
            'typeOptions' => iss_timeline_get_type_options_from_attributes($attributes),
            'postTypeOptions' => iss_timeline_get_post_type_options_from_attributes($attributes),
            'taxonomyUiFilters' => iss_timeline_get_taxonomy_ui_rules_from_attributes($attributes),
        ],
    ];
}

function iss_timeline_render_query_block($attributes = [], $content = '', $block = null) {
    iss_programm_enqueue_timeline_query_assets();
    $attributes = is_array($attributes) ? $attributes : [];
    $scope = iss_timeline_query_scope($attributes);
    $query = iss_timeline_navigation_query();
    $input = $query['iss_timeline'][$scope] ?? [];
    $input = is_array($input) ? $input : [];
    $config = iss_timeline_apply_public_filters(iss_timeline_build_query_block_config($attributes), $attributes, $input);
    if (!empty($config['render']['showTicketsButton'])) { iss_programm_enqueue_calendar_assets(); }
    $selection = $config['selection'];
    $calendar = $selection['view'] === 'calendar' ? iss_timeline_render_month_calendar($config, $scope, $input) : null;
    $listing = iss_timeline_get_listing_response($config, $config['render']);
    $ui = $config['ui'];
    $name = 'iss_timeline[' . $scope . ']';
    $title = trim((string) ($attributes['title'] ?? ''));
    $intro = trim((string) ($attributes['intro'] ?? ''));
    $shell_mode = sanitize_key((string) ($attributes['shellMode'] ?? 'section'));
    $wrapper = [
        'class' => 'iss-timeline-query' . ($shell_mode === 'body' ? '' : ' iss-container'), 'id' => $scope,
        'data-wp-interactive' => 'iss/timeline', 'data-wp-router-region' => $scope,
        'data-wp-bind--aria-busy' => 'context.busy',
    ];
    $attrs = ($block instanceof WP_Block) ? get_block_wrapper_attributes($wrapper) : iss_timeline_render_attr_list($wrapper);
    $tag = $shell_mode === 'body' ? 'div' : 'section';
    $out = '<' . $tag . ' ' . $attrs . ' ' . wp_interactivity_data_wp_context(['scope' => $scope, 'busy' => false]) . ' data-timeline-query>';
    if ($shell_mode !== 'body') {
        if ($title !== '') { $out .= '<h2 class="iss-timeline__section-title">' . esc_html($title) . '</h2>'; }
        if ($intro !== '') { $out .= '<p class="iss-timeline__summary">' . esc_html($intro) . '</p>'; }
    }
    $out .= '<div class="iss-timeline-query__controls">';
    if ($ui['showCalendarBridge']) {
        $out .= '<nav class="iss-timeline__views" aria-label="' . esc_attr__('Ansicht wählen', 'iss-timeline') . '">';
        foreach (['list' => __('Liste', 'iss-timeline'), 'calendar' => __('Kalender', 'iss-timeline')] as $view => $label) {
            $view_input = array_merge($input, ['view' => $view]); unset($view_input['page']);
            $out .= '<a class="iss-timeline__btn iss-timeline__btn--secondary" data-wp-on--click="actions.follow" href="' . esc_url(iss_timeline_navigation_url($scope, $view_input)) . '"' . ($selection['view'] === $view ? ' aria-current="true"' : '') . '>' . esc_html($label) . '</a>';
        }
        $out .= '</nav>';
    }
    $out .= $calendar['html'] ?? '';
    $buttons = iss_timeline_get_preset_buttons_from_attributes($attributes);
    if ($buttons) {
        $out .= '<nav class="iss-timeline__presets" aria-label="' . esc_attr__('Zeitraum wählen', 'iss-timeline') . '">';
        foreach ($buttons as $index => $button) {
            $active = $selection['presetActive'] && $selection['preset'] === (string) $index;
            $out .= '<a class="iss-timeline__preset' . ($active ? ' is-active' : '') . '"' . ($active ? ' aria-current="true"' : '')
                . ' data-wp-on--click="actions.follow" href="' . esc_url(iss_timeline_navigation_url($scope, ['preset' => (string) $index])) . '">' . esc_html($button['label']) . '</a>';
        }
        $out .= '</nav>';
    }
    $has_filters = $ui['showTimeModeFilter'] || $ui['showItemTypeFilter'] || $ui['showPostTypeFilter'] || $ui['showMonthFilter'] || $ui['showCalendarBridge'] || $ui['taxonomyUiFilters'];
    if ($has_filters) {
        $action = iss_timeline_navigation_url($scope);
        $out .= '<form method="get" action="' . esc_url($action) . '" class="iss-timeline__filters" data-timeline-query-form data-wp-on--submit="actions.submit">';
        // GET submission replaces the action query; carry other instances explicitly.
        if (isset($query['iss_timeline']) && is_array($query['iss_timeline'])) { unset($query['iss_timeline'][$scope]); }
        $hidden = static function (array $values, string $parent = '') use (&$hidden): string {
            $html = '';
            foreach ($values as $key => $value) {
                $field = $parent === '' ? (string) $key : $parent . '[' . $key . ']';
                if (is_array($value)) { $html .= $hidden($value, $field); }
                else { $html .= '<input type="hidden" name="' . esc_attr($field) . '" value="' . esc_attr((string) $value) . '">'; }
            }
            return $html;
        };
        $out .= $hidden($query);
        if ($selection['preset'] !== '') { $out .= $hidden(['iss_timeline' => [$scope => ['preset' => $selection['preset']]]]); }
        if ($ui['showCalendarBridge']) { $out .= $hidden(['iss_timeline' => [$scope => ['view' => $selection['view']]]]); }
        $select = static function (string $key, string $label, array $options, string $value) use ($name): string {
            $html = '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html($label) . '</span><select name="' . esc_attr($name . '[' . $key . ']') . '">';
            foreach ($options as $option) { $html .= '<option value="' . esc_attr($option['value']) . '"' . selected($value, $option['value'], false) . '>' . esc_html($option['label']) . '</option>'; }
            return $html . '</select></label>';
        };
        if ($ui['showTimeModeFilter']) { $out .= $select('time_mode', __('Zeitraum', 'iss-timeline'), $ui['timeModeOptions'], $config['filters']['time_mode']); }
        if ($ui['showItemTypeFilter']) {
            $out .= iss_timeline_render_choice_filter([
                'name' => $name . '[item_type]', 'filter_key' => 'item_type', 'label' => __('Inhaltstyp', 'iss-timeline'),
                'selected' => $config['filters']['item_type'] ?: 'all', 'options' => $ui['typeOptions'],
                'links' => is_array($attributes['externalTypeLinks'] ?? null) ? $attributes['externalTypeLinks'] : [], 'className' => 'iss-timeline__filter--type',
            ]);
        }
        if ($ui['showMonthFilter'] || $ui['showCalendarBridge']) {
            $months = iss_timeline_collect_future_month_options(iss_timeline_get_future_horizon_months());
            $month = $selection['month'] ?: ($config['filters']['time_mode'] === 'month' ? $config['filters']['month'] : '');
            if ($month !== '' && !in_array($month, $months, true)) { $months[] = $month; sort($months); }
            $options = [['value' => '', 'label' => __('Alle Monate', 'iss-timeline')]];
            foreach ($months as $ym) { $options[] = ['value' => $ym, 'label' => iss_timeline_format_month_label($ym)]; }
            $out .= $select('month', __('Monat', 'iss-timeline'), $options, $month);
        }
        if ($ui['showCalendarBridge']) {
            $out .= '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html__('Tag', 'iss-timeline') . '</span>'
                . '<input type="date" name="' . esc_attr($name . '[day]') . '" value="' . esc_attr($selection['day']) . '"></label>';
        }
        if ($ui['showPostTypeFilter']) {
            $post_type = count($config['filters']['post_types']) === 1 ? $config['filters']['post_types'][0] : '';
            $out .= $select('post_type', __('Post-Typ', 'iss-timeline'), $ui['postTypeOptions'], $post_type);
        }
        foreach ($ui['taxonomyUiFilters'] as $rule) {
            $taxonomy = $rule['taxonomy'];
            $out .= '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html($rule['label']) . '</span>'
                . '<select multiple size="4" name="' . esc_attr($name . '[taxonomy_' . $taxonomy . '][]') . '">';
            foreach ($rule['options'] as $option) {
                if ($option['value'] === '') { continue; }
                $out .= '<option value="' . esc_attr($option['value']) . '"' . selected(in_array($option['value'], $selection['taxonomies'][$taxonomy] ?? [], true), true, false) . '>' . esc_html($option['label']) . '</option>';
            }
            $out .= '</select></label>';
        }
        $out .= '<div class="iss-timeline__filter-actions"><button type="submit" class="iss-timeline__apply">' . esc_html__('Anwenden', 'iss-timeline') . '</button>'
            . '<a class="iss-timeline__apply iss-timeline__apply--ghost" data-wp-on--click="actions.follow" href="' . esc_url(iss_timeline_navigation_url($scope)) . '">' . esc_html__('Filter zurücksetzen', 'iss-timeline') . '</a></div></form>';
    }
    $out .= '</div>';
    if (!empty($config['render']['showMeta'])) {
        $count_label = sprintf(
            /* translators: %d number of entries on this page */
            _n('%d Eintrag auf dieser Seite', '%d Einträge auf dieser Seite', (int) $listing['batchCount'], 'iss-timeline'),
            (int) $listing['batchCount']
        );
        $out .= '<p class="iss-timeline-query__meta" data-timeline-query-meta>' . esc_html($count_label) . '</p>';
    }
    $out .= '<div class="iss-timeline" data-timeline-query-results tabindex="-1">' . $listing['html'] . '</div>';
    if (!empty($config['render']['showLoadMore']) && iss_programm_preview_datetime() === '' && ($selection['page'] > 1 || $listing['hasMore'])) {
        $out .= '<nav class="iss-timeline-query__pagination" aria-label="' . esc_attr__('Ergebnisseiten', 'iss-timeline') . '">';
        if ($selection['page'] > 1) {
            $out .= '<a class="iss-timeline__btn iss-timeline__btn--secondary" rel="prev" data-wp-on--click="actions.follow" href="' . esc_url(iss_timeline_navigation_url($scope, array_merge($input, ['page' => $selection['page'] - 1]))) . '">' . esc_html__('Zurück', 'iss-timeline') . '</a>';
        }
        if ($listing['hasMore'] && $selection['page'] < 1000) {
            $out .= '<a class="iss-timeline__btn iss-timeline__btn--secondary" rel="next" data-wp-on--click="actions.follow" href="' . esc_url(iss_timeline_navigation_url($scope, array_merge($input, ['page' => $selection['page'] + 1]))) . '">' . esc_html__('Weiter', 'iss-timeline') . '</a>';
        }
        $out .= '</nav>';
    }
    return $out . iss_timeline_render_bottom_button($config['render']) . '</' . $tag . '>';
}

function iss_timeline_format_month_label($ym) {
    $ym = preg_replace('/[^0-9\-]/', '', (string) $ym);
    if (!preg_match('/^\d{4}-\d{2}$/', $ym)) {
        return $ym;
    }

    try {
        $dt = new DateTimeImmutable($ym . '-01 00:00:00', wp_timezone());
        if (function_exists('iss_programm_format_month_year_de')) {
            return iss_programm_format_month_year_de($dt->getTimestamp(), wp_timezone());
        }

        return wp_date('F Y', $dt->getTimestamp(), wp_timezone());
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
