<?php
/** Read-only programme composition regressions. Run with wp eval-file. */
$GLOBALS['iss_programme_checks'] = 0;
function programme_expect(bool $ok, string $message): void {
    ++$GLOBALS['iss_programme_checks'];
    if (!$ok) { WP_CLI::error($message); }
}
$now = '2026-09-22 12:00:00';
$rows = [
    ['source_post_type' => 'veranstaltung', 'start_raw' => '2026-09-12 00:00:00', 'end_raw' => '2026-09-13 23:59:59'],
    ['source_post_type' => 'veranstaltung', 'start_raw' => '2026-09-12 00:00:00', 'end_raw' => '2026-10-04 23:59:59'],
    ['source_post_type' => 'ausstellung', 'start_raw' => '2026-09-12 00:00:00', 'end_raw' => '2026-10-04 23:59:59'],
    ['source_post_type' => 'veranstaltung', 'start_raw' => '2026-09-24 19:00:00', 'end_raw' => ''],
    ['source_post_type' => 'ausstellung', 'start_raw' => '2026-10-09 00:00:00', 'end_raw' => '2026-11-07 23:59:59'],
    ['source_post_type' => 'veranstaltung', 'start_raw' => '2026-10-15 19:00:00', 'end_raw' => ''],
    ['source_post_type' => 'veranstaltung', 'start_raw' => '2026-11-01 17:00:00', 'end_raw' => '2026-11-01 19:00:00', 'series_key' => 'event:repair'],
    ['source_post_type' => 'veranstaltung', 'start_raw' => '2026-12-01 17:00:00', 'end_raw' => '2026-12-01 19:00:00', 'series_key' => 'event:repair'],
];
$sections = industriesalon_programme_sections($rows, $now);
programme_expect(count($sections['dates']) === 3, 'Future dates cross months; series appears once.');
programme_expect(count($sections['running']) === 1, 'Ongoing festival is not an appointment.');
programme_expect(count($sections['exhibitions']) === 1, 'Open exhibition separated.');
programme_expect(count($sections['later_exhibitions']) === 1, 'Future exhibition is not open yet.');
programme_expect(industriesalon_programme_date($rows[2], $now) === 'Noch bis 4. Oktober 2026', 'German end date independent of admin locale.');
programme_expect(count(industriesalon_programme_sections([$rows[2]], '2026-10-04 23:59:59')['exhibitions']) === 1, 'Inclusive last day retained.');
programme_expect(!industriesalon_programme_sections([$rows[2]], '2026-10-05 00:00:00')['exhibitions'], 'Expired exhibition removed.');
programme_expect(!industriesalon_programme_sections([$rows[3]], '2026-09-25 00:00:00')['dates'], 'Finished appointment removed.');
programme_expect(industriesalon_programme_sections([], $now)['dates'] === [], 'Empty programme supported.');
programme_expect(strpos(industriesalon_programme_date(['start_raw' => '2027-01-02 19:00:00', 'day_label' => 'Sa. 02.01.', 'date_label' => '2. Januar 2027'], $now), '2027') !== false, 'Next-year appointments retain their year.');
programme_expect(industriesalon_programme_overview(null, [], []) === null, 'Default programme cards unchanged.');
$config = iss_programm_cards_build_block_config(['limit' => 3]);
programme_expect($config['query']['limit'] === 3, 'Ordinary card limit unchanged.');
$block = WP_Block_Type_Registry::get_instance()->get_registered('industriesalon/program-cards');
programme_expect(isset($block->attributes['presentation']), 'Editor and server share presentation attribute.');
$template = get_block_template('industriesalon//page-veranstaltungen', 'wp_template');
programme_expect($template->source === 'theme', 'Effective template is theme-owned.');
$parsed = parse_blocks($template->content);
programme_expect(parse_blocks(serialize_blocks($parsed)) === $parsed, 'Template survives block parse/serialization.');
$html = do_blocks($template->content);
programme_expect(substr_count($html, '<h1') === 1 && strpos($html, '>Veranstaltungen</h1>') !== false, 'One recognizable page title.');
programme_expect(strpos($html, 'Nicht jede Veranstaltung') === false, 'Obsolete introduction removed.');
programme_expect(strpos($html, 'iss-events-program__aside') === false, 'Instruction sidebar removed.');
programme_expect(strpos($html, 'iss-card--programme-feature') !== false, 'Next event has a feature.');
programme_expect(strpos($html, '15.10.') !== false, 'October event is visible in September.');
programme_expect(strpos($html, 'Noch bis 4. Oktober 2026') !== false, 'Live end-date label rendered.');
$past_args = ['limit' => 2, 'filters' => ['time_mode' => 'past', 'post_types' => ['veranstaltung']]];
$first = iss_timeline_get_listing_response($past_args);
$past_args['offset'] = 2;
$second = iss_timeline_get_listing_response($past_args);
$dates = array_column(array_merge($first['items'], $second['items']), 'start_raw');
$sorted = $dates; rsort($sorted);
programme_expect($dates === $sorted, 'Past results newest first across pagination.');
programme_expect(count(array_unique(array_column(array_merge($first['items'], $second['items']), 'id'))) === count($dates), 'History pages do not repeat occurrences.');
WP_CLI::success($GLOBALS['iss_programme_checks'] . ' programme checks passed; no records changed.');
