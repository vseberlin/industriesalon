<?php

if (!defined('ABSPATH')) { exit; }

function iss_timeline_get_booking_action_attrs($row): array {
    if (!is_array($row)) {
        return [];
    }

    $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
    $source_post_type = sanitize_key((string) ($row['source_post_type'] ?? ''));
    $attrs = [];

    if ($source_post_type === 'veranstaltung' && $source_post_id > 0) {
        $price_cents = max(0, (int) get_post_meta($source_post_id, 'iss_booking_price_cents', true));
        $cta_label = trim((string) get_post_meta($source_post_id, 'iss_booking_cta_label', true));
        $description = trim((string) get_post_meta($source_post_id, 'iss_booking_gateway_description', true));

        if ($price_cents > 0) {
            $attrs['data-amount'] = (string) $price_cents;
        }
        if ($cta_label !== '') {
            $attrs['data-label'] = $cta_label;
        }
        if ($description !== '') {
            $attrs['data-description'] = $description;
        }
    }

    return $attrs;
}

function iss_timeline_get_calendar_trigger_attrs($row): array {
    return is_array($row) ? iss_programm_booking_trigger_attributes($row) : [];
}

function iss_timeline_get_booking_url_for_row($row): string {
    if (!is_array($row)) {
        return '';
    }

    $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
    $source_post_type = sanitize_key((string) ($row['source_post_type'] ?? ''));
    if ($source_post_id <= 0 || !in_array($source_post_type, ['fuehrung', 'veranstaltung'], true)) {
        return '';
    }

    if ($source_post_type === 'veranstaltung' && empty(get_post_meta($source_post_id, 'iss_booking_enabled', true))) {
        return '';
    }

    $permalink = get_permalink($source_post_id);
    if (!is_string($permalink) || $permalink === '') {
        return '#buchung';
    }

    return $permalink . ($source_post_type === 'veranstaltung' ? '#besuch' : '#buchung');
}

function iss_timeline_get_ticket_action_for_occurrence($row, $opts = []) {
    if (!is_array($row) || in_array($row['availability_state'] ?? $row['availability'] ?? '', ['cancelled', 'sold_out', 'unavailable'], true)) {
        return [];
    }

    $opts = is_array($opts) ? $opts : [];
    if (array_key_exists('showTicketsButton', $opts) && !(bool) $opts['showTicketsButton']) {
        return [];
    }

    $booking_url = iss_timeline_get_booking_url_for_row($row);
    if ($booking_url === '') {
        return [];
    }

    $tickets_label = isset($opts['ticketsButtonText']) ? trim(sanitize_text_field((string) $opts['ticketsButtonText'])) : '';
    if ($tickets_label === '') {
        $tickets_label = __('Buchen', 'iss-timeline');
    }

    $source_post_id = isset($row['source_post_id']) ? (int) $row['source_post_id'] : 0;
    $source_post_type = trim((string) ($row['source_post_type'] ?? ''));

    $action = [
        'url' => $booking_url,
        'label' => $tickets_label,
        'variant' => 'primary',
    ];

    if ($source_post_id > 0 && $source_post_type !== '') {
        $action['attrs'] = array_merge(
            iss_timeline_get_calendar_trigger_attrs($row),
            iss_timeline_get_booking_action_attrs($row)
        );
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

    if (iss_timeline_should_render_grouped_occurrence_picker($occurrences, $opts)) {
        return iss_timeline_render_grouped_occurrence_picker($row, $occurrences, $opts);
    }

    $summary_label = sprintf(
        /* translators: %d number of visible dates in grouped tour row */
        _n('Termine anzeigen (%d)', 'Termine anzeigen (%d)', count($occurrences), 'iss-timeline'),
        count($occurrences)
    );

    if (!empty($opts['expandGroupedOccurrences'])) {
        $summary_label = sprintf(
            /* translators: %d number of visible dates in grouped tour row */
            _n('%d Termin', '%d Termine', count($occurrences), 'iss-timeline'),
            count($occurrences)
        );
        $out = '<div class="iss-timeline__occurrences iss-timeline__occurrences--expanded">';
        $out .= '<p class="iss-timeline__occurrences-summary">' . esc_html($summary_label) . '</p>';
    } else {
        $out = '<details class="iss-timeline__occurrences">';
        $out .= '<summary class="iss-timeline__btn iss-timeline__btn--secondary iss-timeline__occurrences-summary">'
            . esc_html($summary_label) . '</summary>';
    }
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
    $out .= !empty($opts['expandGroupedOccurrences']) ? '</div>' : '</details>';

    return $out;
}

function iss_timeline_group_occurrences_by_month(array $occurrences): array {
    $groups = [];

    foreach ($occurrences as $occurrence) {
        if (!is_array($occurrence)) {
            continue;
        }

        $start_raw = trim((string) ($occurrence['start_raw'] ?? ''));
        $month_key = preg_match('/^\d{4}-\d{2}/', $start_raw, $matches) ? $matches[0] : '';
        if ($month_key === '') {
            $month_key = 'undated';
        }

        if (!isset($groups[$month_key])) {
            $groups[$month_key] = [
                'label' => $month_key === 'undated'
                    ? __('Ohne Monat', 'iss-timeline')
                    : iss_timeline_format_month_label($month_key),
                'items' => [],
            ];
        }

        $groups[$month_key]['items'][] = $occurrence;
    }

    return $groups;
}

function iss_timeline_occurrence_is_sold_out(array $occurrence): bool {
    $availability = isset($occurrence['availability_state'])
        ? sanitize_key((string) $occurrence['availability_state'])
        : sanitize_key((string) ($occurrence['availability'] ?? ''));
    if (in_array($availability, ['sold_out', 'sold-out', 'full', 'unavailable'], true)) {
        return true;
    }

    if (array_key_exists('available', $occurrence)
        && $occurrence['available'] !== null
        && (int) $occurrence['available'] <= 0
        && array_key_exists('capacity', $occurrence)
        && $occurrence['capacity'] !== null
        && (int) $occurrence['capacity'] >= 0
    ) {
        return true;
    }

    return false;
}

function iss_timeline_get_occurrence_capacity_label(array $occurrence): string {
    if (($occurrence['availability_state'] ?? $occurrence['availability'] ?? '') === 'unavailable') { return __('Buchung noch nicht geöffnet', 'iss-timeline'); }
    if (iss_timeline_occurrence_is_sold_out($occurrence)) {
        return __('Ausgebucht', 'iss-timeline');
    }

    if (!array_key_exists('available', $occurrence) || $occurrence['available'] === null) {
        return '';
    }

    $available = max(0, (int) $occurrence['available']);
    if (array_key_exists('capacity', $occurrence) && $occurrence['capacity'] !== null && (int) $occurrence['capacity'] > 0) {
        return sprintf(
            /* translators: 1: available places, 2: total capacity */
            __('%1$d von %2$d frei', 'iss-timeline'),
            $available,
            (int) $occurrence['capacity']
        );
    }

    return sprintf(
        /* translators: %d available places */
        _n('%d Platz frei', '%d Plätze frei', $available, 'iss-timeline'),
        $available
    );
}

function iss_timeline_render_attr_list(array $attrs): string {
    $out = '';
    foreach ($attrs as $attr_name => $attr_value) {
        $attr_name = trim((string) $attr_name);
        if ($attr_name === '' || $attr_value === null || $attr_value === '') {
            continue;
        }
        $out .= ' ' . esc_attr($attr_name) . '="' . esc_attr((string) $attr_value) . '"';
    }

    return $out;
}

function iss_timeline_render_grouped_occurrence_picker_slot(array $occurrence, array $opts = []): string {
    $label = iss_timeline_get_occurrence_label($occurrence);
    if ($label === '') {
        return '';
    }

    $meta = iss_timeline_get_occurrence_capacity_label($occurrence);
    $sold_out = iss_timeline_occurrence_is_sold_out($occurrence);
    $ticket_action = $sold_out ? [] : iss_timeline_get_ticket_action_for_occurrence($occurrence, $opts);
    $inner = '<span class="iss-timeline-slot-picker__slot-date">' . esc_html($label) . '</span>';
    if ($meta !== '') {
        $inner .= '<span class="iss-timeline-slot-picker__slot-meta">' . esc_html($meta) . '</span>';
    }

    if (empty($ticket_action) || empty($ticket_action['url'])) {
        $classes = ['iss-timeline-slot-picker__slot', 'iss-timeline-slot-picker__slot--disabled'];
        if ($sold_out) {
            $classes[] = 'is-sold-out';
        }

        return '<span class="' . esc_attr(implode(' ', $classes)) . '" aria-disabled="true">' . $inner . '</span>';
    }

    $classes = ['iss-timeline__btn', 'iss-timeline__btn--secondary', 'iss-timeline-slot-picker__slot'];
    if (!empty($ticket_action['classes']) && is_array($ticket_action['classes'])) {
        foreach ($ticket_action['classes'] as $class_name) {
            $class_name = trim((string) $class_name);
            if ($class_name !== '') {
                $classes[] = $class_name;
            }
        }
    }

    $attrs = !empty($ticket_action['attrs']) && is_array($ticket_action['attrs'])
        ? $ticket_action['attrs']
        : [];

    return '<a class="' . esc_attr(implode(' ', array_values(array_unique($classes)))) . '" href="'
        . esc_url((string) $ticket_action['url']) . '"' . iss_timeline_render_attr_list($attrs) . '>'
        . $inner . '</a>';
}

function iss_timeline_render_grouped_occurrence_picker($row, array $occurrences, array $opts = []): string {
    $row = is_array($row) ? $row : [];
    $occurrences = array_values(array_filter($occurrences, 'is_array'));
    if (count($occurrences) < 2) {
        return '';
    }

    $count = count($occurrences);
    $title = trim((string) ($row['title'] ?? ''));
    if ($title === '') {
        $title = __('Termine', 'iss-timeline');
    }

    $trigger_label = __('Termin wählen', 'iss-timeline');
    $trigger_aria = sprintf(
        /* translators: 1: timeline item title, 2: number of available dates */
        __('Termin wählen: %1$s, %2$d Termine', 'iss-timeline'),
        $title,
        $count
    );
    $count_label = sprintf(
        /* translators: %d number of grouped dates */
        _n('%d Termin', '%d Termine', $count, 'iss-timeline'),
        $count
    );

    $row['slot_id'] = '';
    $attrs = iss_timeline_get_calendar_trigger_attrs($row);
    $fallback = $row['content_url'] ?? get_permalink((int) ($row['source_post_id'] ?? 0));

    return '<div class="iss-timeline-slot-picker-wrap">'
        . '<p class="iss-timeline-slot-picker__count">' . esc_html($count_label) . '</p>'
        . '<a href="' . esc_url($fallback ?: home_url('/kalender/')) . '" class="iss-timeline__btn iss-timeline__btn--' . (!empty($opts['programmeLayout']) ? 'primary' : 'secondary') . ' iss-timeline-slot-picker__trigger"'
        . ' aria-label="' . esc_attr($trigger_aria) . '"'
        . iss_timeline_render_attr_list($attrs) . '>'
        . esc_html($trigger_label) . '</a>'
        . '</div>';
}
