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

function iss_timeline_is_groupable_tour_row($row) {
    if (!is_array($row)) {
        return false;
    }

    return iss_timeline_resolve_item_type_value($row['type'] ?? '') === 'tour';
}

function iss_timeline_get_row_group_key($row) {
    if (!is_array($row)) {
        return '';
    }

    $series_key = trim((string) ($row['series_key'] ?? ''));
    if ($series_key !== '') {
        return 'series:' . $series_key;
    }

    $source_post_id = (int) ($row['source_post_id'] ?? 0);
    if ($source_post_id > 0) {
        return 'source:' . $source_post_id;
    }

    $item_id = (int) ($row['id'] ?? 0);
    return $item_id > 0 ? 'item:' . $item_id : '';
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

function iss_timeline_group_recurring_tour_rows($items) {
    $rows = iss_timeline_prepare_rows($items);
    if (empty($rows)) {
        return [];
    }

    $grouped_rows = [];
    $group_indexes = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        if (!iss_timeline_is_groupable_tour_row($row)) {
            $grouped_rows[] = $row;
            continue;
        }

        $group_key = iss_timeline_get_row_group_key($row);
        if ($group_key === '' || !isset($group_indexes[$group_key])) {
            $row['occurrences'] = [$row];
            $group_indexes[$group_key] = count($grouped_rows);
            $grouped_rows[] = $row;
            continue;
        }

        $target_index = $group_indexes[$group_key];
        if (empty($grouped_rows[$target_index]['occurrences']) || !is_array($grouped_rows[$target_index]['occurrences'])) {
            $grouped_rows[$target_index]['occurrences'] = [$grouped_rows[$target_index]];
        }
        $grouped_rows[$target_index]['occurrences'][] = $row;
    }

    foreach ($grouped_rows as &$row) {
        if (!is_array($row) || empty($row['occurrences']) || !is_array($row['occurrences'])) {
            continue;
        }

        $occurrences = array_values(array_filter($row['occurrences'], 'is_array'));
        if (count($occurrences) <= 1) {
            unset($row['occurrences']);
            continue;
        }

        $row['grouped'] = true;
        $row['occurrences'] = $occurrences;
        $row['occurrence_count'] = count($occurrences);
    }
    unset($row);

    return $grouped_rows;
}

function iss_timeline_should_render_grouped_occurrences($row, $opts = []) {
    return iss_timeline_has_grouped_occurrences($row);
}

function iss_timeline_get_ticket_action_for_occurrence($row, $opts = []) {
    if (!is_array($row)) {
        return [];
    }

    $opts = is_array($opts) ? $opts : [];
    if (array_key_exists('showTicketsButton', $opts) && !(bool) $opts['showTicketsButton']) {
        return [];
    }

    $booking_url = isset($row['booking_url']) ? trim((string) $row['booking_url']) : '';
    $tickets_override = isset($opts['ticketsButtonUrl']) ? esc_url_raw((string) $opts['ticketsButtonUrl']) : '';
    if ($tickets_override !== '') {
        $booking_url = $tickets_override;
    }
    if ($booking_url === '') {
        return [];
    }

    $tickets_label = isset($opts['ticketsButtonText']) ? trim(sanitize_text_field((string) $opts['ticketsButtonText'])) : '';
    if ($tickets_label === '') {
        $tickets_label = __('Buchen', 'iss-timeline');
    }

    $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
    $source_post_type = trim((string) ($row['source_post_type'] ?? ''));
    $slot_id = trim((string) ($row['slot_id'] ?? ''));
    $slot_start = trim((string) ($row['slot_start'] ?? ''));
    $slot_end = trim((string) ($row['end_raw'] ?? ''));

    $action = [
        'url' => $booking_url,
        'label' => $tickets_label,
        'variant' => 'primary',
    ];

    if ($slot_id !== '' && $slot_start !== '') {
        $action['classes'] = ['js-is-tour-slot-trigger'];
        $action['attrs'] = [
            'data-slot-id' => $slot_id,
            'data-start' => $slot_start,
            'data-title' => trim((string) ($row['title'] ?? '')),
        ];
        if ($slot_end !== '') {
            $action['attrs']['data-end'] = $slot_end;
        }
        if ($source_post_id > 0) {
            $action['attrs']['data-source-post-id'] = (string) $source_post_id;
        }
        if ($source_post_type !== '') {
            $action['attrs']['data-source-post-type'] = $source_post_type;
        }
    }

    return $action;
}

function iss_timeline_render_grouped_occurrences($row, $opts = []) {
    if (!iss_timeline_should_render_grouped_occurrences($row, $opts)) {
        return '';
    }

    $occurrences = array_values(array_filter((array) ($row['occurrences'] ?? []), 'is_array'));
    if (count($occurrences) <= 1) {
        return '';
    }

    $summary_label = sprintf(
        /* translators: %d number of visible dates in grouped tour row */
        _n('Termine anzeigen (%d)', 'Termine anzeigen (%d)', count($occurrences), 'iss-timeline'),
        count($occurrences)
    );

    $out = '<details class="iss-timeline__occurrences">';
    $out .= '<summary class="iss-timeline__btn iss-timeline__btn--secondary iss-timeline__occurrences-summary">'
        . esc_html($summary_label) . '</summary>';
    $out .= '<ul class="iss-timeline__occurrence-list">';

    foreach ($occurrences as $occurrence) {
        $label = iss_timeline_get_occurrence_label($occurrence);
        if ($label === '') {
            continue;
        }

        $out .= '<li class="iss-timeline__occurrence">';
        $out .= '<span class="iss-timeline__occurrence-date">' . esc_html($label) . '</span>';
        $ticket_action = iss_timeline_get_ticket_action_for_occurrence($occurrence, $opts);
        if (!empty($ticket_action)) {
            $ticket_action['classes'] = array_merge(
                isset($ticket_action['classes']) && is_array($ticket_action['classes']) ? $ticket_action['classes'] : [],
                ['iss-timeline__occurrence-ticket']
            );
            $out .= iss_timeline_render_action_link($ticket_action);
        }
        $out .= '</li>';
    }

    $out .= '</ul>';
    $out .= '</details>';

    return $out;
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

    $booking_url = isset($row['booking_url']) ? trim((string) $row['booking_url']) : '';

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
        $slot_end = trim((string) ($row['end_raw'] ?? ''));
        $source_post_type = trim((string) ($row['source_post_type'] ?? ''));
        if ($slot_id !== '' && $slot_start !== '') {
            $ticket_action['classes'] = ['js-is-tour-slot-trigger'];
            $ticket_action['attrs'] = [
                'data-slot-id' => $slot_id,
                'data-start' => $slot_start,
                'data-title' => trim((string) ($row['title'] ?? '')),
            ];
            if ($slot_end !== '') {
                $ticket_action['attrs']['data-end'] = $slot_end;
            }
            if ($source_post_id > 0) {
                $ticket_action['attrs']['data-source-post-id'] = (string) $source_post_id;
            }
            if ($source_post_type !== '') {
                $ticket_action['attrs']['data-source-post-type'] = $source_post_type;
            }
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

function iss_timeline_render_booking_host() {
    return '<div class="is-tour-calendar iss-tour-calendar--booking-host" data-booking-host="1" hidden aria-hidden="true"></div>';
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

    if ($group_recurring_tours) {
        $fetch_args = $query_args;
        $fetch_args['offset'] = 0;
        $fetch_args['limit'] = -1;

        $items = function_exists('iss_timeline_get_items_advanced')
            ? iss_timeline_get_items_advanced($fetch_args)
            : [];
        $grouped_rows = iss_timeline_group_recurring_tour_rows($items);
        $total_count = count($grouped_rows);

        if ($limit > 0) {
            $visible_rows = array_slice($grouped_rows, $offset, $limit);
        } elseif ($offset > 0) {
            $visible_rows = array_slice($grouped_rows, $offset);
        } else {
            $visible_rows = $grouped_rows;
        }

        $visible_count = $offset + count($visible_rows);
        $has_more = $visible_count < $total_count;

        return [
            'items' => $visible_rows,
            'count' => $visible_count,
            'batchCount' => count($visible_rows),
            'isEmpty' => empty($visible_rows),
            'offset' => $offset,
            'nextOffset' => $visible_count,
            'hasMore' => $has_more,
            'html' => (isset($render_opts['renderMode']) && $render_opts['renderMode'] === 'cards')
                ? iss_timeline_render_items_cards($visible_rows, $render_opts)
                : iss_timeline_render_items_list($visible_rows, $render_opts),
        ];
    }

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
        'html' => (isset($render_opts['renderMode']) && $render_opts['renderMode'] === 'cards')
            ? iss_timeline_render_items_cards($items, $render_opts)
            : iss_timeline_render_items_list($items, $render_opts),
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
    $allowed_types = iss_timeline_get_attribute_token_list($attributes['allowedTypesList'] ?? [], ['fuehrungen', 'veranstaltungen']);

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

function iss_timeline_render_preset_buttons($attributes = []) {
    $buttons = iss_timeline_get_preset_buttons_from_attributes($attributes);
    if (empty($buttons)) {
        return '';
    }

    $out = '<div class="iss-timeline__presets" data-timeline-presets>';
    foreach ($buttons as $button) {
        $label = $button['label'];
        $time_mode = $button['timeMode'] ?? '';
        $range_preset = $button['rangePreset'] ?? '';
        $taxonomy = $button['taxonomy'] ?? '';
        $terms = !empty($button['terms']) && is_array($button['terms']) ? implode(',', $button['terms']) : '';
        $is_default = !empty($button['isDefault']);

        $out .= '<button type="button" class="iss-timeline__preset' . ($is_default ? ' is-active' : '') . '" data-timeline-preset';
        $out .= ' data-timeline-preset-default="' . ($is_default ? 'true' : 'false') . '"';
        $out .= ' aria-pressed="' . ($is_default ? 'true' : 'false') . '"';
        if ($time_mode !== '') {
            $out .= ' data-preset-time-mode="' . esc_attr($time_mode) . '"';
        }
        if ($range_preset !== '') {
            $out .= ' data-preset-range="' . esc_attr($range_preset) . '"';
        }
        if ($taxonomy !== '') {
            $out .= ' data-preset-taxonomy="' . esc_attr($taxonomy) . '"';
        }
        if ($terms !== '') {
            $out .= ' data-preset-terms="' . esc_attr($terms) . '"';
        }
        $out .= '>' . esc_html($label) . '</button>';
    }
    $out .= '</div>';

    return $out;
}

function iss_timeline_render_choice_filter($args = []) {
    $args = is_array($args) ? $args : [];

    $name = sanitize_key((string) ($args['name'] ?? ''));
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
            'post_types' => iss_timeline_get_attribute_token_list($attributes['postTypesList'] ?? [], []),
            'taxonomy_filters' => iss_timeline_get_fixed_taxonomy_rules_from_attributes($attributes),
        ],
        'render' => [
            'renderMode' => (($attributes['renderMode'] ?? 'timeline') === 'cards') ? 'cards' : 'timeline',
            'showCardImage' => !array_key_exists('showCardImage', $attributes) || (bool) $attributes['showCardImage'],
            'showCardSummary' => !array_key_exists('showCardSummary', $attributes) || (bool) $attributes['showCardSummary'],
            'yearGrouping' => !empty($attributes['yearGrouping']),
            'groupRecurringTours' => !empty($attributes['groupRecurringTours']),
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
    if (function_exists('iss_programm_enqueue_timeline_query_assets')) {
        iss_programm_enqueue_timeline_query_assets();
    }

    $attributes = is_array($attributes) ? $attributes : [];
    $config = iss_timeline_build_query_block_config($attributes);
    $config['baseFilters'] = $config['filters'];
    $default_preset = iss_timeline_get_default_preset_button($attributes);
    if (!empty($default_preset)) {
        $config['filters'] = iss_timeline_merge_preset_filters($config['filters'], $default_preset);
    }
    $listing_config = $config;
    if (!empty($config['render']['showTicketsButton']) && function_exists('iss_programm_enqueue_calendar_assets')) {
        iss_programm_enqueue_calendar_assets();
    }
    $listing = iss_timeline_get_listing_response($listing_config, $listing_config['render']);
    $config['initialNextOffset'] = (int) ($listing['nextOffset'] ?? 0);

    $title = trim((string) ($attributes['title'] ?? ''));
    $intro = trim((string) ($attributes['intro'] ?? ''));
    $shell_mode = sanitize_key((string) ($attributes['shellMode'] ?? 'section'));
    $months = iss_timeline_collect_future_month_options(function_exists('iss_timeline_get_future_horizon_months') ? iss_timeline_get_future_horizon_months() : 6);

    $use_block_wrapper = function_exists('get_block_wrapper_attributes') && ($block instanceof WP_Block);
    $wrapper_class = 'iss-timeline-query';
    if ($shell_mode !== 'body') {
        $wrapper_class .= ' iss-container';
    }
    $attrs = $use_block_wrapper
        ? get_block_wrapper_attributes(['class' => $wrapper_class])
        : 'class="' . esc_attr($wrapper_class) . '"';

    $tag = $shell_mode === 'body' ? 'div' : 'section';
    $out = '<' . $tag . ' ' . $attrs . ' data-timeline-query data-config="' . esc_attr(wp_json_encode($config)) . '">';

    if ($shell_mode !== 'body') {
        if ($title !== '') {
            $out .= '<h2 class="iss-timeline__section-title">' . esc_html($title) . '</h2>';
        }
        if ($intro !== '') {
            $out .= '<p class="iss-timeline__summary">' . esc_html($intro) . '</p>';
        }
    }

    $out .= iss_timeline_render_preset_buttons($attributes);

    $has_filter_ui = !empty($config['ui']['showTimeModeFilter'])
        || !empty($config['ui']['showItemTypeFilter'])
        || !empty($config['ui']['showPostTypeFilter'])
        || !empty($config['ui']['showMonthFilter'])
        || !empty($config['ui']['showCalendarBridge'])
        || !empty($config['ui']['taxonomyUiFilters']);

    $has_non_bridge_filter_ui = !empty($config['ui']['showTimeModeFilter'])
        || !empty($config['ui']['showItemTypeFilter'])
        || !empty($config['ui']['showPostTypeFilter'])
        || !empty($config['ui']['showMonthFilter'])
        || !empty($config['ui']['taxonomyUiFilters']);

    if ($has_filter_ui) {
        $out .= '<form class="iss-timeline__filters" data-timeline-query-form>';

        if (!empty($config['ui']['showCalendarBridge'])) {
            $out .= '<div class="iss-timeline__calendar-bridge" data-timeline-calendar-bridge>';
            $out .= '<label class="iss-timeline__filter iss-timeline__filter--calendar">';
            $out .= '<span class="iss-timeline__filter-label">' . esc_html__('Monat', 'iss-timeline') . '</span>';
            $out .= '<select data-calendar-bridge-month>';
            foreach ($months as $ym) {
                $out .= '<option value="' . esc_attr($ym) . '"' . selected($config['filters']['month'], $ym, false) . '>' . esc_html(iss_timeline_format_month_label($ym)) . '</option>';
            }
            $out .= '</select>';
            $out .= '</label>';
            $out .= '<label class="iss-timeline__filter iss-timeline__filter--calendar">';
            $out .= '<span class="iss-timeline__filter-label">' . esc_html__('Tag', 'iss-timeline') . '</span>';
            $out .= '<input type="text" value="" placeholder="' . esc_attr__('TT.MM.JJJJ', 'iss-timeline') . '" autocomplete="off" data-calendar-bridge-day />';
            $out .= '</label>';
            $out .= '<button type="button" class="iss-timeline__apply iss-timeline__apply--ghost" data-calendar-bridge-reset>'
                . esc_html__('Zurücksetzen', 'iss-timeline') . '</button>';
            $out .= '</div>';
        }

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

        if (!empty($config['ui']['showItemTypeFilter'])) {
            $out .= iss_timeline_render_choice_filter([
                'name' => 'item_type',
                'filter_key' => 'item_type',
                'label' => __('Inhaltstyp', 'iss-timeline'),
                'selected' => (string) ($config['filters']['item_type'] ?? 'all'),
                'options' => is_array($config['ui']['typeOptions']) ? $config['ui']['typeOptions'] : [],
                'links' => is_array($attributes['externalTypeLinks'] ?? null) ? $attributes['externalTypeLinks'] : [],
                'className' => 'iss-timeline__filter--type',
            ]);
        }

        if (!empty($config['ui']['showMonthFilter'])) {
            $month_hidden = ($config['filters']['time_mode'] === 'month') ? '' : ' hidden';
            $out .= '<label class="iss-timeline__filter iss-timeline__filter--month"' . $month_hidden . ' data-timeline-month-filter><span class="iss-timeline__filter-label">' . esc_html__('Monat', 'iss-timeline') . '</span>';
            $out .= '<select name="month" data-filter-key="month"' . $month_hidden . '>';
            foreach ($months as $ym) {
                $out .= '<option value="' . esc_attr($ym) . '"' . selected($config['filters']['month'], $ym, false) . '>' . esc_html(iss_timeline_format_month_label($ym)) . '</option>';
            }
            $out .= '</select></label>';
        }

        if (!empty($config['ui']['showPostTypeFilter']) && !empty($config['ui']['postTypeOptions']) && is_array($config['ui']['postTypeOptions'])) {
            $out .= '<label class="iss-timeline__filter"><span class="iss-timeline__filter-label">' . esc_html__('Post-Typ', 'iss-timeline') . '</span>';
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
                $out .= '<select name="taxonomy_' . esc_attr($taxonomy) . '[]" data-filter-taxonomy="' . esc_attr($taxonomy) . '" multiple size="' . esc_attr((string) min(8, max(4, count($taxonomy_filter['options']) - 1))) . '">';
                foreach ($taxonomy_filter['options'] as $option) {
                    if (!is_array($option)) {
                        continue;
                    }
                    $value = isset($option['value']) ? sanitize_title((string) $option['value']) : '';
                    $option_label = isset($option['label']) ? (string) $option['label'] : $value;
                    if ($value === '') {
                        continue;
                    }
                    $out .= '<option value="' . esc_attr($value) . '">' . esc_html($option_label) . '</option>';
                }
                $out .= '</select></label>';
            }
        }

        if ($has_non_bridge_filter_ui) {
            $out .= '<div class="iss-timeline__filter-actions">';
            $out .= '<button type="button" class="iss-timeline__apply iss-timeline__apply--ghost" data-timeline-query-reset>'
                . esc_html__('Filter zurücksetzen', 'iss-timeline') . '</button>';
            $out .= '</div>';
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
    if (!empty($config['render']['showTicketsButton'])) {
        $out .= iss_timeline_render_booking_host();
    }
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
    $out .= '</' . $tag . '>';

    return $out;
}

function iss_timeline_format_month_label($ym) {
    if (!function_exists('iss_timeline_month_to_range')) return $ym;
    $r = iss_timeline_month_to_range($ym);
    if (!is_array($r)) return $ym;
    try {
        $dt = new DateTimeImmutable($r['start'], wp_timezone());
        return iss_programm_format_month_year_de($dt->getTimestamp(), wp_timezone());
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
