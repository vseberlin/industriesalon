<?php
/** Server-rendered month view of the existing occurrence query, with native GET links. */
if (!defined('ABSPATH')) { exit; }

function iss_timeline_calendar_days(array $items, string $month, bool $include_ranges = true): array {
    $start = new DateTimeImmutable($month . '-01', wp_timezone());
    $end = $start->modify('last day of this month');
    $days = [];
    for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) { $days[$date->format('Y-m-d')] = []; }
    foreach ($items as $item) {
        $first = substr((string) ($item['start_raw'] ?? ''), 0, 10);
        if ($first === '') { continue; }
        $last = !empty($item['is_open_ended']) ? $end->format('Y-m-d') : substr((string) ($item['end_raw'] ?? $first), 0, 10);
        $last = max($first, $last);
        if (!$include_ranges) { $last = $first; }
        // Date ranges occupy their visible days, bounded to this month. This is
        // presentation of owner rows; no query, recurrence expansion or write per day.
        foreach ($days as $day => $rows) {
            if ($day >= $first && $day <= $last) { $days[$day][] = $item; }
        }
    }
    return $days;
}

function iss_timeline_render_month_calendar(array $config, string $scope, array $input): array {
    $month = $config['selection']['month'];
    $start = new DateTimeImmutable($month . '-01', wp_timezone());
    $args = $config;
    $args['filters']['time_mode'] = 'month';
    $args['filters']['month'] = $month;
    unset($args['filters']['date_start'], $args['filters']['date_end']);
    $args['limit'] = -1;
    $args['offset'] = 0;
    $items = iss_timeline_get_items_advanced($args);
    $days = iss_timeline_calendar_days($items, $month, !empty($config['filters']['include_running_ranges']));
    $navigation = array_merge($input, ['view' => 'calendar', 'month' => $month]);
    unset($navigation['day'], $navigation['page']);
    $link = static fn(array $values): string => esc_url(iss_timeline_navigation_url($scope, array_merge($navigation, $values)));
    $out = '<div class="iss-programme-calendar"><nav class="iss-programme-calendar__nav" aria-label="' . esc_attr__('Kalendermonat', 'iss-timeline') . '">';
    $out .= '<a class="iss-timeline__btn iss-timeline__btn--secondary" data-wp-on--click="actions.follow" aria-label="' . esc_attr__('Vorheriger Monat', 'iss-timeline') . '" href="' . $link(['month' => $start->modify('-1 month')->format('Y-m')]) . '">←</a>';
    $out .= '<h3 id="' . esc_attr($scope . '-month') . '">' . esc_html(iss_timeline_format_month_label($month)) . '</h3>';
    $out .= '<a class="iss-timeline__btn iss-timeline__btn--secondary" data-wp-on--click="actions.follow" aria-label="' . esc_attr__('Nächster Monat', 'iss-timeline') . '" href="' . $link(['month' => $start->modify('+1 month')->format('Y-m')]) . '">→</a></nav>';
    $out .= '<table class="iss-programme-calendar__grid" aria-labelledby="' . esc_attr($scope . '-month') . '"><thead><tr>';
    foreach (['Montag' => 'Mo', 'Dienstag' => 'Di', 'Mittwoch' => 'Mi', 'Donnerstag' => 'Do', 'Freitag' => 'Fr', 'Samstag' => 'Sa', 'Sonntag' => 'So'] as $long => $short) {
        $out .= '<th scope="col" abbr="' . esc_attr($long) . '">' . esc_html($short) . '</th>';
    }
    $out .= '</tr></thead><tbody><tr>';
    $offset = (int) $start->format('N') - 1;
    $cells = (int) ceil(($offset + count($days)) / 7) * 7;
    $today = wp_date('Y-m-d', null, wp_timezone());
    for ($index = 0; $index < $cells; ++$index) {
        if ($index && $index % 7 === 0) { $out .= '</tr><tr>'; }
        $number = $index - $offset + 1;
        if ($number < 1 || $number > count($days)) { $out .= '<td class="iss-programme-calendar__blank"></td>'; continue; }
        $day = sprintf('%s-%02d', $month, $number);
        $rows = $days[$day];
        $selected = $day === $config['selection']['day'];
        $label = wp_date('j. F Y', $start->setDate((int) $start->format('Y'), (int) $start->format('m'), $number)->getTimestamp(), wp_timezone());
        /* translators: 1: full date, 2: number of occurrences on this day */
        $label = sprintf(__('%1$s · %2$d Termine', 'iss-timeline'), $label, count($rows));
        $out .= '<td class="iss-programme-calendar__cell' . ($rows ? ' has-events' : '') . ($selected ? ' is-selected' : '') . '">';
        $out .= '<a class="iss-programme-calendar__day" data-calendar-day="' . esc_attr($day) . '" data-wp-on--click="actions.follow" href="' . $link(['day' => $day]) . '" aria-label="' . esc_attr($label) . '"' . ($day === $today ? ' aria-current="date"' : '') . '>' . $number;
        if ($rows) { $out .= '<span class="iss-programme-calendar__count" aria-hidden="true">' . count($rows) . '</span>'; }
        $out .= '</a>';
        if ($rows) {
            $out .= '<ul class="iss-programme-calendar__events">';
            foreach (array_slice($rows, 0, 3) as $row) {
                $title = (string) ($row['title'] ?? '');
                if (!empty($row['time_label'])) { $title = $row['time_label'] . ' · ' . $title; }
                if (($row['availability'] ?? '') === 'cancelled') { $title .= ' · ' . __('abgesagt', 'iss-timeline'); }
                elseif (($row['availability'] ?? '') === 'sold_out' || (isset($row['available']) && (int) $row['available'] <= 0)) { $title .= ' · ' . __('ausgebucht', 'iss-timeline'); }
                $url = (string) ($row['content_url'] ?? '');
                $out .= '<li>' . ($url !== '' ? '<a class="iss-programme-calendar__event-link" href="' . esc_url($url) . '">' . esc_html($title) . '</a>' : esc_html($title)) . '</li>';
            }
            if (count($rows) > 3) {
                /* translators: %d: remaining occurrences available through the day link */
                $out .= '<li><a class="iss-programme-calendar__event-link" data-wp-on--click="actions.follow" href="' . $link(['day' => $day]) . '">' . esc_html(sprintf(__('+ %d weitere', 'iss-timeline'), count($rows) - 3)) . '</a></li>';
            }
            $out .= '</ul>';
        }
        $out .= '</td>';
    }
    $out .= '</tr></tbody></table>';
    if (!$items) { $out .= '<p>' . esc_html__('In diesem Monat wurden keine Termine gefunden.', 'iss-timeline') . '</p>'; }
    if ($config['selection']['day'] !== '') {
        $out .= '<p><a data-wp-on--click="actions.follow" href="' . $link([]) . '">' . esc_html__('Ganzen Monat anzeigen', 'iss-timeline') . '</a></p>';
    }
    return ['html' => $out . '</div>', 'count' => count($items)];
}
