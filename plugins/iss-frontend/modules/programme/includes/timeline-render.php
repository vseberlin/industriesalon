<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/timeline-booking.php';
require_once __DIR__ . '/timeline-filters.php';

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

function iss_timeline_prepare_rows($items) {
    $items = is_array($items) ? $items : [];
    $rows = [];

    foreach ($items as $item) {
        if (is_array($item)) {
            $rows[] = $item;
        }
    }

    return $rows;
}

function iss_timeline_get_occurrence_label($row) {
    if (!is_array($row)) {
        return '';
    }

    $datetime_label = trim((string) ($row['datetime_label'] ?? ''));
    if ($datetime_label !== '') {
        return $datetime_label;
    }

    $label = trim((string) ($row['date_label'] ?? ''));
    $time_label = trim((string) ($row['time_label'] ?? ''));

    if ($label === '') {
        return $time_label;
    }

    if ($time_label !== '') {
        $label .= ' · ' . $time_label;
    }

    return $label;
}

function iss_timeline_get_next_occurrence_card_label($row) {
    $occurrence = iss_timeline_get_occurrence_label($row);
    if ($occurrence === '') {
        return '';
    }

    return sprintf(
        /* translators: %s next occurrence date/time */
        __('Nächster Termin: %s', 'iss-timeline'),
        $occurrence
    );
}

function iss_timeline_get_recurring_note($row, $visible_limit = 2) {
    if (!is_array($row) || empty($row['grouped'])) {
        return '';
    }

    $occurrences = !empty($row['occurrences']) && is_array($row['occurrences']) ? array_values($row['occurrences']) : [];
    if (count($occurrences) < 2) {
        return '';
    }

    $remaining = array_slice($occurrences, 1);
    if (empty($remaining)) {
        return '';
    }

    $labels = [];
    foreach (array_slice($remaining, 0, max(1, (int) $visible_limit)) as $occurrence) {
        $label = iss_timeline_get_occurrence_label($occurrence);
        if ($label !== '') {
            $labels[] = $label;
        }
    }

    $remaining_count = count($remaining);
    $more_count = max(0, $remaining_count - count($labels));
    $note = '';

    if (!empty($labels)) {
        $note = sprintf(
            /* translators: %s comma-separated list of further dates */
            __('Weitere Termine: %s', 'iss-timeline'),
            implode(', ', $labels)
        );
    }

    if ($more_count > 0) {
        $more_label = sprintf(
            /* translators: %d number of additional future dates */
            _n('+ %d weiterer Termin', '+ %d weitere Termine', $more_count, 'iss-timeline'),
            $more_count
        );
        $note = $note !== '' ? $note . ' ' . $more_label : $more_label;
    }

    if ($note === '') {
        $note = sprintf(
            /* translators: %d number of further dates */
            _n('%d weiterer Termin', '%d weitere Termine', $remaining_count, 'iss-timeline'),
            $remaining_count
        );
    }

    return $note;
}

function iss_timeline_has_grouped_occurrences($row) {
    if (!is_array($row) || empty($row['grouped']) || empty($row['occurrences']) || !is_array($row['occurrences'])) {
        return false;
    }

    return count(array_filter($row['occurrences'], 'is_array')) > 1;
}

function iss_timeline_should_render_grouped_occurrences($row, $opts = []) {
    return iss_timeline_has_grouped_occurrences($row);
}

function iss_timeline_get_grouped_occurrence_display_mode($opts = []): string {
    $opts = is_array($opts) ? $opts : [];
    $mode = isset($opts['groupedOccurrenceDisplay']) ? sanitize_key((string) $opts['groupedOccurrenceDisplay']) : 'auto';
    return in_array($mode, ['auto', 'picker', 'inline'], true) ? $mode : 'auto';
}

function iss_timeline_get_grouped_occurrence_picker_threshold($opts = []): int {
    $opts = is_array($opts) ? $opts : [];
    $threshold = isset($opts['groupedOccurrencePickerThreshold'])
        ? (int) $opts['groupedOccurrencePickerThreshold']
        : 2;

    return max(2, $threshold);
}

function iss_timeline_should_render_grouped_occurrence_picker($occurrences, $opts = []): bool {
    $opts = is_array($opts) ? $opts : [];
    $occurrences = array_values(array_filter((array) $occurrences, 'is_array'));
    if (count($occurrences) < 2 || !empty($opts['expandGroupedOccurrences'])) {
        return false;
    }

    $mode = iss_timeline_get_grouped_occurrence_display_mode($opts);
    if ($mode === 'inline') {
        return false;
    }
    if ($mode === 'picker') {
        return true;
    }

    return count($occurrences) >= iss_timeline_get_grouped_occurrence_picker_threshold($opts);
}

function iss_timeline_get_programme_item_type_tokens(): array {
    return ['fuehrungen', 'veranstaltungen', 'ausstellungen'];
}

function iss_timeline_get_programme_source_post_types(): array {
    return ['fuehrung', 'veranstaltung', 'ausstellung'];
}

function iss_timeline_attributes_include_projects($attributes = []): bool {
    $attributes = is_array($attributes) ? $attributes : [];
    $tokens = [];
    foreach (['defaultType', 'fixedItemTypesList', 'presetItemTypesList', 'allowedTypesList'] as $key) {
        $value = $attributes[$key] ?? [];
        $values = is_array($value) ? $value : [$value];
        foreach ($values as $item) {
            $token = sanitize_key((string) $item);
            if ($token !== '') {
                $tokens[] = $token;
            }
        }
    }

    return (bool) array_intersect($tokens, ['projekt', 'projekte', 'project']);
}

function iss_timeline_should_group_recurring_by_month($query_args = [], $render_opts = []): bool {
    if (empty($render_opts['groupRecurringToursByMonth'])) {
        return false;
    }

    $filters = isset($query_args['filters']) && is_array($query_args['filters'])
        ? $query_args['filters']
        : [];
    $time_mode = sanitize_key((string) ($filters['time_mode'] ?? ''));

    return $time_mode === 'month';
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
        'groupRecurringTours' => !empty($attributes['groupRecurringTours']) && (bool) $attributes['groupRecurringTours'],
        'groupRecurringToursByMonth' => !empty($attributes['groupRecurringToursByMonth']) && (bool) $attributes['groupRecurringToursByMonth'],
        'expandGroupedOccurrences' => !empty($attributes['expandGroupedOccurrences']) && (bool) $attributes['expandGroupedOccurrences'],
        'groupedOccurrenceDisplay' => isset($attributes['groupedOccurrenceDisplay']) ? (string) $attributes['groupedOccurrenceDisplay'] : 'auto',
        'groupedOccurrencePickerThreshold' => isset($attributes['groupedOccurrencePickerThreshold']) ? (int) $attributes['groupedOccurrencePickerThreshold'] : 2,
        'showRecurringNote' => !array_key_exists('showRecurringNote', $attributes) || (bool) $attributes['showRecurringNote'],
        'showNextOccurrenceLabel' => !empty($attributes['showNextOccurrenceLabel']) && (bool) $attributes['showNextOccurrenceLabel'],
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
    if (iss_timeline_should_render_grouped_occurrences($row, $opts)) {
        $show_tickets = false;
    }

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

    $booking_url = iss_timeline_get_booking_url_for_row($row);

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
        $ticket_action = [
            'url' => $booking_url,
            'label' => $tickets_label,
            'variant' => 'primary',
        ];
        $slot_id = trim((string) ($row['slot_id'] ?? ''));
        $slot_start = trim((string) ($row['slot_start'] ?? ''));
        $source_post_type = trim((string) ($row['source_post_type'] ?? ''));
        if ($tickets_override === '' && ($row['cta_mode'] ?? '') !== 'external' && $source_post_id > 0 && $source_post_type !== '' && $slot_id !== '' && $slot_start !== '') {
            $ticket_action['attrs'] = array_merge(
                iss_timeline_get_calendar_trigger_attrs($row),
                iss_timeline_get_booking_action_attrs($row)
            );
        }
        $actions[] = $ticket_action;
    }

    return $actions;
}

function iss_timeline_render_action_link($action = []) {
    if (!is_array($action) || empty($action['url'])) {
        return '';
    }

    $variant = isset($action['variant']) ? sanitize_key((string) $action['variant']) : 'secondary';
    if (!in_array($variant, ['secondary', 'primary'], true)) {
        $variant = 'secondary';
    }

    $classes = ['iss-timeline__btn', 'iss-timeline__btn--' . $variant];
    if (!empty($action['classes']) && is_array($action['classes'])) {
        foreach ($action['classes'] as $class_name) {
            $class_name = trim((string) $class_name);
            if ($class_name !== '') {
                $classes[] = $class_name;
            }
        }
    }

    $attrs = '';
    if (!empty($action['attrs']) && is_array($action['attrs'])) {
        foreach ($action['attrs'] as $attr_name => $attr_value) {
            $attr_name = trim((string) $attr_name);
            if ($attr_name === '' || $attr_value === null || $attr_value === '') {
                continue;
            }
            $attrs .= ' ' . esc_attr($attr_name) . '="' . esc_attr((string) $attr_value) . '"';
        }
    }

    return '<a class="' . esc_attr(implode(' ', array_values(array_unique($classes)))) . '" href="'
        . esc_url((string) $action['url']) . '"' . $attrs . '>'
        . esc_html((string) ($action['label'] ?? '')) . '</a>';
}

function iss_timeline_get_type_label($item_type) {
    $item_type = sanitize_key((string) $item_type);

    if (in_array($item_type, ['tour', 'fuehrung', 'fuehrungen'], true)) {
        return __('Führung', 'iss-timeline');
    }

    if (in_array($item_type, ['event', 'veranstaltung', 'veranstaltungen'], true)) {
        return __('Veranstaltung', 'iss-timeline');
    }

    if (in_array($item_type, ['ausstellung', 'ausstellungen', 'exhibition'], true)) {
        return __('Ausstellung', 'iss-timeline');
    }

    if (in_array($item_type, ['project', 'projekt', 'projekte'], true)) {
        return __('Projekt', 'iss-timeline');
    }

    return $item_type !== '' ? ucfirst($item_type) : '';
}

function iss_timeline_get_card_badge_label($row) {
    $row = is_array($row) ? $row : [];
    $item_type = sanitize_key((string) ($row['type'] ?? ''));
    $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;

    if ($source_post_id > 0 && in_array($item_type, ['tour', 'fuehrung', 'fuehrungen'], true) && taxonomy_exists('fuehrung_typ')) {
        $terms = wp_get_post_terms($source_post_id, 'fuehrung_typ', ['fields' => 'names']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $first_term = trim((string) reset($terms));
            if ($first_term !== '') {
                return $first_term;
            }
        }
    }

    if ($source_post_id > 0 && function_exists('iss_graph_get_contract_payload_for_post') && function_exists('iss_graph_get_contract_public_label')) {
        $contract = iss_graph_get_contract_payload_for_post($source_post_id);
        if (is_array($contract) && sanitize_key((string) ($contract['kind'] ?? '')) === 'offer') {
            $contract_label = trim((string) iss_graph_get_contract_public_label($contract));
            if ($contract_label !== '') {
                return $contract_label;
            }
        }
    }

    return iss_timeline_get_type_label($item_type);
}

function iss_timeline_render_items_cards($items, $opts = []) {
    $rows = iss_timeline_prepare_rows($items);
    $opts = is_array($opts) ? $opts : [];
    $show_meta = !array_key_exists('showMeta', $opts) || (bool) $opts['showMeta'];
    $show_card_image = !array_key_exists('showCardImage', $opts) || (bool) $opts['showCardImage'];
    $show_card_summary = !array_key_exists('showCardSummary', $opts) || (bool) $opts['showCardSummary'];
    $cards_class = 'iss-card-grid iss-timeline-cards';
    if (!$show_card_image) {
        $cards_class .= ' iss-timeline-cards--compact';
    }
    if (!$show_card_summary) {
        $cards_class .= ' iss-timeline-cards--summary-hidden';
    }
    $out = '<div class="' . esc_attr($cards_class) . '">';

    foreach ($rows as $row) {
        if (empty($row) || !is_array($row)) {
            continue;
        }

        $type_label = iss_timeline_get_card_badge_label($row);
        $summary = trim((string) ($row['summary'] ?? ''));
        $actions = iss_timeline_build_actions($row, $opts);
        $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
        $permalink = $source_post_id > 0 ? get_permalink($source_post_id) : '';
        $permalink = is_string($permalink) ? trim($permalink) : '';
        $card_footer = '';

        if (count($actions) === 1 && $permalink !== '') {
            $action = $actions[0];
            $variant = isset($action['variant']) ? sanitize_key((string) $action['variant']) : 'secondary';
            $action_url = isset($action['url']) ? untrailingslashit((string) $action['url']) : '';
            $details_url = untrailingslashit($permalink);

            if ($variant === 'secondary'
                && $action_url === $details_url
                && empty($action['attrs'])
                && empty($action['classes'])
            ) {
                $card_footer = '<div class="iss-card__footer"><a class="iss-card__link" href="'
                    . esc_url($permalink) . '">' . esc_html((string) ($action['label'] ?? '')) . '</a></div>';
                $actions = [];
            }
        }

        $card_class = 'iss-card iss-card--flat iss-card--media-wide iss-timeline-card';
        if (!$show_card_image) {
            $card_class .= ' iss-timeline-card--text-only';
        }
        $out .= '<article class="' . esc_attr($card_class) . '">';

        if ($show_card_image && $source_post_id > 0 && has_post_thumbnail($source_post_id)) {
            $out .= '<figure class="iss-card__media iss-timeline-card__media">';
            if ($permalink !== '') {
                $out .= '<a href="' . esc_url($permalink) . '">';
            }
            $out .= get_the_post_thumbnail($source_post_id, 'large');
            if ($permalink !== '') {
                $out .= '</a>';
            }
            $out .= '</figure>';
        }

        $out .= '<div class="iss-card__body">';

        if ($type_label !== '') {
            $out .= '<p class="iss-kicker iss-kicker--compact iss-timeline-card__kicker">' . esc_html($type_label) . '</p>';
        }

        $out .= '<h3 class="iss-card__title iss-timeline-card__title">';
        if ($permalink !== '') {
            $out .= '<a href="' . esc_url($permalink) . '">' . esc_html((string) ($row['title'] ?? '')) . '</a>';
        } else {
            $out .= esc_html((string) ($row['title'] ?? ''));
        }
        $out .= '</h3>';

        $meta_lines = [];
        if (!empty($opts['showNextOccurrenceLabel']) && !empty($row['date_label'])) {
            $meta_lines[] = iss_timeline_get_next_occurrence_card_label($row);
        }
        if (($show_meta || !empty($row['grouped'])) && !empty($row['date_label'])) {
            $meta_lines[] = iss_timeline_get_occurrence_label($row);
        }
        $grouped_occurrences = iss_timeline_render_grouped_occurrences($row, $opts);
        $recurring_note = $grouped_occurrences === '' && (!array_key_exists('showRecurringNote', $opts) || !empty($opts['showRecurringNote']))
            ? iss_timeline_get_recurring_note($row)
            : '';
        if ($recurring_note !== '') {
            $meta_lines[] = $recurring_note;
        }
        foreach ($meta_lines as $meta_line) {
            $out .= '<p class="iss-card__meta iss-timeline-card__meta">' . esc_html($meta_line) . '</p>';
        }

        if ($show_card_summary && $summary !== '') {
            $out .= '<p class="iss-card__text iss-timeline-card__text">' . esc_html($summary) . '</p>';
        }

        if ($grouped_occurrences !== '') {
            $out .= $grouped_occurrences;
        }

        if ($card_footer !== '') {
            $out .= $card_footer;
        }

        if (!empty($actions)) {
            $out .= '<div class="iss-timeline-card__actions">';
            foreach ($actions as $action) {
                if (!is_array($action) || empty($action['url'])) {
                    continue;
                }
                $out .= iss_timeline_render_action_link($action);
            }
            $out .= '</div>';
        }

        $out .= '</div></article>';
    }

    $out .= '</div>';
    return $out;
}

function iss_timeline_render_items_list($items, $opts = []) {
    $opts = is_array($opts) ? $opts : [];
    $yearGrouping = array_key_exists('yearGrouping', $opts) ? (bool) $opts['yearGrouping'] : true;
    $order = isset($opts['order']) ? strtoupper((string) $opts['order']) : 'ASC';
    if (!in_array($order, ['ASC', 'DESC'], true)) $order = 'ASC';
    $rows = iss_timeline_prepare_rows($items);

    if (empty($rows)) {
        return '<p class="iss-timeline__empty">' . esc_html__('Keine Einträge gefunden.', 'iss-timeline') . '</p>';
    }

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
            $type_label = iss_timeline_get_card_badge_label($row);
            $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
            $permalink = $source_post_id > 0 ? get_permalink($source_post_id) : '';
            $permalink = is_string($permalink) ? trim($permalink) : '';
            $has_media = $source_post_id > 0 && has_post_thumbnail($source_post_id);
            $item_classes = 'iss-timeline__item';
            $item_classes .= $has_media ? ' iss-timeline__item--has-media' : ' iss-timeline__item--no-media';

            $out .= '<article class="' . esc_attr($item_classes) . '">';
            $out .= '<div class="iss-timeline__date">';
            $out .= '<div class="iss-timeline__day">' . esc_html((string) ($row['day_label'] ?? '')) . '</div>';
            if (!empty($row['time_label'])) {
                $out .= '<div class="iss-timeline__time">' . esc_html((string) $row['time_label']) . '</div>';
            }
            $out .= '</div>';
            $out .= '<div class="iss-timeline__content">';
            if ($type_label !== '') {
                $out .= '<p class="iss-kicker iss-kicker--compact iss-timeline__kicker">' . esc_html($type_label) . '</p>';
            }
            $out .= '<h4 class="iss-timeline__title">';
            if ($permalink !== '') {
                $out .= '<a href="' . esc_url($permalink) . '">' . esc_html((string) ($row['title'] ?? '')) . '</a>';
            } else {
                $out .= esc_html((string) ($row['title'] ?? ''));
            }
            $out .= '</h4>';
            if (!empty($row['summary'])) {
                $out .= '<div class="iss-timeline__summary">' . esc_html((string) $row['summary']) . '</div>';
            } elseif (!empty($row['type'])) {
                $out .= '<div class="iss-timeline__summary">' . esc_html((string) $row['type']) . '</div>';
            }
            $grouped_occurrences = iss_timeline_render_grouped_occurrences($row, $opts);
            $recurring_note = $grouped_occurrences === '' && (!array_key_exists('showRecurringNote', $opts) || !empty($opts['showRecurringNote']))
                ? iss_timeline_get_recurring_note($row)
                : '';
            if ($recurring_note !== '') {
                $out .= '<div class="iss-timeline__summary">' . esc_html($recurring_note) . '</div>';
            }
            if ($grouped_occurrences !== '') {
                $out .= $grouped_occurrences;
            }

            $actions = iss_timeline_build_actions($row, $opts);
            if (!empty($actions)) {
                $out .= '<div class="iss-timeline__actions">';
                foreach ($actions as $action) {
                    if (!is_array($action) || empty($action['url'])) continue;
                    $out .= iss_timeline_render_action_link($action);
                }
                $out .= '</div>';
            }
            $out .= '</div>';
            if ($has_media) {
                $out .= '<figure class="iss-timeline__media">';
                if ($permalink !== '') {
                    $out .= '<a href="' . esc_url($permalink) . '">';
                }
                $out .= get_the_post_thumbnail($source_post_id, 'large');
                if ($permalink !== '') {
                    $out .= '</a>';
                }
                $out .= '</figure>';
            }
            $out .= '</article>';
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
    $group_recurring_tours = !empty($render_opts['groupRecurringTours']);

    // Keep initial render and REST pagination in the same chronological order.
    if (($query_args['filters']['time_mode'] ?? '') === 'past') {
        $query_args['order'] = 'DESC';
        $render_opts['order'] = 'DESC';
    }
    $fetch_args = $query_args;
    if ($group_recurring_tours) {
        $group_recurring_by_month = iss_timeline_should_group_recurring_by_month($query_args, $render_opts);
        $fetch_args['group_recurring'] = true;
        $fetch_args['group_recurring_by_month'] = $group_recurring_by_month;
        $fetch_args['group_recurring_by_source'] = !$group_recurring_by_month;
    }

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
        'html' => (isset($render_opts['renderMode']) && $render_opts['renderMode'] === 'cards')
            ? iss_timeline_render_items_cards($items, $render_opts)
            : iss_timeline_render_items_list($items, $render_opts),
    ];
}
