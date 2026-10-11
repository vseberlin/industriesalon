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
    iss_programm_enqueue_calendar_styles();
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
    $out = '<div class="iss-programme-calendar iss-date-calendar"><nav class="iss-date-calendar__nav" aria-label="' . esc_attr__('Kalendermonat', 'iss-timeline') . '">';
    $out .= '<a class="iss-date-calendar__move" data-wp-on--click="actions.follow" aria-label="' . esc_attr__('Vorheriger Monat', 'iss-timeline') . '" href="' . $link(['month' => $start->modify('-1 month')->format('Y-m')]) . '">‹</a>';
    $out .= '<h3 class="iss-date-calendar__month" id="' . esc_attr($scope . '-month') . '">' . esc_html(iss_timeline_format_month_label($month)) . '</h3>';
    $out .= '<a class="iss-date-calendar__move" data-wp-on--click="actions.follow" aria-label="' . esc_attr__('Nächster Monat', 'iss-timeline') . '" href="' . $link(['month' => $start->modify('+1 month')->format('Y-m')]) . '">›</a></nav>';
    $out .= '<table class="iss-date-calendar__grid" aria-labelledby="' . esc_attr($scope . '-month') . '"><thead><tr>';
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
        if ($number < 1 || $number > count($days)) { $out .= '<td><span class="iss-date-calendar__day iss-date-calendar__outside" aria-hidden="true">' . esc_html($start->modify(($number - 1) . ' days')->format('j')) . '</span></td>'; continue; }
        $day = sprintf('%s-%02d', $month, $number);
        $rows = $days[$day];
        $selected = $day === $config['selection']['day'];
        $label = iss_programm_format_date_long_de($start->setDate((int) $start->format('Y'), (int) $start->format('m'), $number)->getTimestamp(), wp_timezone());
        /* translators: 1: full date, 2: number of occurrences on this day */
        $label = sprintf(__('%1$s · %2$d Termine', 'iss-timeline'), $label, count($rows));
        $out .= '<td' . ($rows ? ' class="has-events"' : '') . '>';
        $out .= '<a class="iss-date-calendar__day" data-calendar-day="' . esc_attr($day) . '" data-availability="' . ($rows ? 'available' : 'empty') . '" data-selected="' . ($selected ? 'true' : 'false') . '" data-wp-on--click="actions.follow" href="' . $link(['day' => $day]) . '" aria-label="' . esc_attr($label) . '"' . ($day === $today ? ' aria-current="date"' : '') . '><span>' . $number . '</span>';
        $out .= '</a>';
        $out .= '</td>';
    }
    $out .= '</tr></tbody></table>';
    $out .= '<p class="iss-date-calendar__legend"><span class="iss-date-calendar__marker" aria-hidden="true"></span>' . esc_html__('Mit Termin', 'iss-timeline') . '</p>';
    if (!$items) { $out .= '<p>' . esc_html__('In diesem Monat wurden keine Termine gefunden.', 'iss-timeline') . '</p>'; }
    if ($config['selection']['day'] !== '') {
        $out .= '<p><a data-wp-on--click="actions.follow" href="' . $link([]) . '">' . esc_html__('Ganzen Monat anzeigen', 'iss-timeline') . '</a></p>';
    }
    return ['html' => $out . '</div>', 'count' => count($items)];
}
