<?php
/** Read-only month geometry, source constraints and renderer compatibility. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($message); } };
$rows = [
    ['id' => 1, 'start_raw' => '2028-02-29 12:00:00', 'end_raw' => '2028-02-29 13:00:00'],
    ['id' => 2, 'start_raw' => '2028-01-15 12:00:00', 'end_raw' => '2028-02-03 18:00:00'],
    ['id' => 3, 'start_raw' => '2028-02-28 12:00:00', 'is_open_ended' => true],
];
$days = iss_timeline_calendar_days($rows, '2028-02');
$expect(count($days) === 29 && count($days['2028-02-29']) === 2, 'Leap day and open-ended range share the final cell');
$expect(count($days['2028-02-01']) === 1 && !$days['2028-02-04'], 'Cross-month ranges are clipped to their visible days');
$expect(count(iss_timeline_calendar_days($rows, '2028-02', false)['2028-02-29']) === 1, 'Disabling running ranges keeps cell counts aligned with the owner day query');
$expect(count(iss_timeline_calendar_days([], '2026-03')) === 31 && count(iss_timeline_calendar_days([], '2026-10')) === 31, 'Berlin DST months retain every calendar day');
$attrs = ['limit' => 2, 'showLoadMore' => true, 'showCalendarBridge' => true, 'fixedItemTypesList' => ['event'], 'postTypesList' => ['veranstaltung'], 'group' => 'fixture-fixed'];
$base = iss_timeline_build_query_block_config($attrs);
$config = iss_timeline_apply_public_filters($base, $attrs, ['view' => 'calendar', 'month' => '2028-02', 'page' => 999, 'item_type' => 'fuehrungen']);
$expect($config['selection']['view'] === 'calendar' && $config['selection']['page'] === 1 && $config['offset'] === 0, 'A complete month has no list pagination offset');
$expect($config['filters']['time_mode'] === 'month' && $config['filters']['item_types'] === ['event'] && $config['filters']['post_types'] === ['veranstaltung'] && $config['groups'] === ['fixture-fixed'], 'Month view preserves editor-owned source restrictions');
$day = iss_timeline_apply_public_filters($base, $attrs, ['view' => 'calendar', 'month' => '2028-02', 'day' => '2028-03-02']);
$expect($day['selection']['month'] === '2028-03' && $day['filters']['date_start'] === '2028-03-02 00:00:00', 'Selected day owns both visible month and list range');
$hidden = $attrs; $hidden['showCalendarBridge'] = false;
$expect(iss_timeline_apply_public_filters(iss_timeline_build_query_block_config($hidden), $hidden, ['view' => 'calendar'])['selection']['view'] === 'list', 'A hidden calendar control cannot widen editor scope');
$expect(iss_timeline_apply_public_filters($base, $attrs, ['view' => ['calendar'], 'month' => '2028-13'])['selection']['view'] === 'list', 'Malformed input cannot activate a calendar');
$calendar = iss_timeline_render_month_calendar($config, 'fixture', []);
$expect(substr_count($calendar['html'], 'data-calendar-day=') === 29, 'Server grid emits each leap-month date once');
$expect(str_contains($calendar['html'], '2028-01') && str_contains($calendar['html'], '2028-03'), 'Server navigation uses adjacent months');
$expect(str_contains($calendar['html'], 'data-wp-on--click="actions.follow"') && str_contains($calendar['html'], 'href='), 'Calendar navigation supports core router and ordinary links');
$picker = iss_programm_render_availability_calendar(['id' => 1, 'type' => 'fuehrung', 'tag' => '']);
$expect(str_contains($picker, 'role="grid"') && str_contains($picker, 'actions.calendarKey') && str_contains($picker, 'aria-disabled'), 'One accessible picker exposes keyboard and availability contracts');
$expect(!str_contains($picker, '<script') && !str_contains($picker, 'flatpickr'), 'Picker uses existing script module, with no library or inline script');
WP_CLI::success("Month calendar: $checks read-only checks passed.");
