<?php
/** Read-only lifecycle and query regressions. wp eval-file; no fixtures persisted. */
$GLOBALS['iss_rotation_checks'] = 0;
function rotation_expect(bool $ok, string $message): void {
    ++$GLOBALS['iss_rotation_checks'];
    if (!$ok) { WP_CLI::error($message); }
}
$GLOBALS['iss_rotation_now'] = '2026-09-22 12:00:00';
function iss_rotation_test_clock(): string { return $GLOBALS['iss_rotation_now']; }
add_filter('iss_occurrences_query_now', 'iss_rotation_test_clock', 99);
$event = ['source_post_type' => 'veranstaltung', 'id' => 1, 'start_raw' => '2026-09-24 19:00:00', 'end_raw' => '', 'day_label' => 'Do. 24.09.', 'time_label' => '19:00 Uhr'];
$next = array_merge($event, ['id' => 2, 'start_raw' => '2026-10-15 19:00:00']);
$exhibition = ['source_post_type' => 'ausstellung', 'id' => 3, 'start_raw' => '2026-09-12 00:00:00', 'end_raw' => '2026-10-04 23:59:59'];
$known = array_merge($event, ['end_raw' => '2026-09-24 21:00:00']);
foreach (['2026-09-24 19:00:00', '2026-09-24 23:59:59'] as $now) {
    rotation_expect(count(iss_programm_sections([$event], $now)['dates']) === 1, 'Unknown end remains through local event day.');
}
rotation_expect(!iss_programm_sections([$event], '2026-09-25 00:00:00')['dates'], 'Unknown end expires at next midnight.');
rotation_expect(industriesalon_programme_date($event, '2026-09-24 10:00:00') === 'Heute · 19:00 Uhr', 'Today label retains start time.');
rotation_expect(industriesalon_programme_status($known, '2026-09-24 20:00:00') === 'Findet gerade statt', 'Known end permits a running notice.');
rotation_expect(industriesalon_programme_status($event, '2026-09-24 20:00:00') === '', 'Unknown duration never claims event is still running.');
rotation_expect(count(iss_programm_sections([$known], '2026-09-24 21:00:00')['dates']) === 1, 'Exact known end retained.');
rotation_expect(!iss_programm_sections([$known], '2026-09-24 21:00:01')['dates'], 'Known end expires immediately afterwards.');
$indefinite = array_merge($exhibition, ['end_raw' => '', 'is_open_ended' => true]);
rotation_expect(count(iss_programm_sections([$indefinite], '2027-02-01 00:00:00')['exhibitions']) === 1, 'Indefinite exhibition survives old opening date.');
rotation_expect(industriesalon_programme_date($indefinite, '2027-02-01 00:00:00') === 'Laufend zu erleben', 'Indefinite exhibition has no invented closing date.');
$series = [array_merge($event, ['series_key' => 'repair']), array_merge($next, ['series_key' => 'repair'])];
rotation_expect(iss_programm_sections(array_reverse($series), '2026-09-22 12:00:00')['dates'][0]['id'] === 1, 'Series sorts by date before deduplication.');
rotation_expect(iss_programm_sections($series, '2026-09-25 00:00:00')['dates'][0]['id'] === 2, 'Recurring event advances to next occurrence.');
$now = '2026-09-22 12:00:00';
$sections = iss_programm_sections([$next, $event, $exhibition], $now);
rotation_expect(iss_programm_take_feature($sections, $now)['id'] === 1, 'Nearest date leads.');
rotation_expect(count($sections['dates']) === 1, 'Featured date is not repeated in rows.');
$focus = array_merge($next, ['focus_until' => '2026-09-23 23:59:59']);
$sections = iss_programm_sections([$event, $focus], $now);
$featured = iss_programm_take_feature($sections, $now);
rotation_expect($featured['id'] === 2 && $featured['feature_label'] === 'focus', 'Unexpired editorial choice leads as Im Fokus.');
rotation_expect($sections['dates'][0]['id'] === 1, 'Nearest date remains visible under editorial feature.');
$sections = iss_programm_sections([$event, $focus], '2026-09-24 00:00:00');
rotation_expect(iss_programm_take_feature($sections, '2026-09-24 00:00:00')['id'] === 1, 'Highlight expires at Berlin midnight.');
$sections = iss_programm_sections([array_merge($event, ['focus_until' => '2027-12-31 23:59:59']), $next], '2026-09-25 00:00:00');
rotation_expect(iss_programm_take_feature($sections, '2026-09-25 00:00:00')['id'] === 2, 'Highlight cannot resurrect expired event.');
$cancelled = array_merge($event, ['availability_state' => 'cancelled', 'focus_until' => '2026-12-31 23:59:59']);
$sections = iss_programm_sections([$cancelled, $next], $now);
rotation_expect(iss_programm_take_feature($sections, $now)['id'] === 2 && count($sections['dates']) === 1, 'Cancellation stays listed but cannot be featured.');
rotation_expect(industriesalon_programme_status($cancelled, $now) === 'Abgesagt', 'Cancellation notice available.');
$distant = array_merge($event, ['start_raw' => '2026-12-01 19:00:00']);
$sections = iss_programm_sections([$distant, $exhibition], $now);
rotation_expect(iss_programm_take_feature($sections, $now)['feature_label'] === 'current' && count($sections['dates']) === 1, 'Quiet period features exhibition without hiding distant date.');
$sections = iss_programm_sections([array_merge($distant, ['focus_until' => '2026-12-31 23:59:59']), $exhibition], $now);
rotation_expect(iss_programm_take_feature($sections, $now)['id'] === 1, 'Explicit promotion overrides quiet-period fallback.');
$sections = iss_programm_sections([$distant], $now);
rotation_expect(iss_programm_take_feature($sections, $now)['id'] === 1, 'Distant event still leads when there is no current exhibition.');
$sections = iss_programm_sections([], $now);
rotation_expect(iss_programm_take_feature($sections, $now) === null, 'Empty programme is safe.');
$dst = array_merge($event, ['start_raw' => '2026-10-25 19:00:00']);
rotation_expect(count(iss_programm_sections([$dst], '2026-10-25 23:59:59')['dates']) === 1 && !iss_programm_sections([$dst], '2026-10-26 00:00:00')['dates'], 'DST-change day still expires at calendar midnight.');
$year = array_merge($event, ['start_raw' => '2026-12-31 19:00:00']);
rotation_expect(!iss_programm_sections([$year], '2027-01-01 00:00:00')['dates'], 'Year boundary expires cleanly.');
rotation_expect(iss_content_model_sanitize_event_status('bad') === '' && iss_content_model_sanitize_event_status('sold_out') === 'sold_out', 'Editorial status accepts only supported values.');

// Exercise actual occurrence SQL and REST cache behaviour using the imported source identity.
$ids = get_posts(['post_type' => 'veranstaltung', 'posts_per_page' => -1, 'fields' => 'ids']);
$phone_id = 0;
foreach ($ids as $id) {
    if ((string) get_post_meta($id, 'source_post_id', true) === '12060') { $phone_id = $id; break; }
}
rotation_expect($phone_id > 0, 'Telephone source resolved without environment-specific numeric IDs.');
$query = ['limit' => -1, 'source_post_ids' => [$phone_id], 'include_running_ranges' => true, 'time_mode' => 'upcoming'];
foreach (['2026-09-24 19:00:00', '2026-09-24 23:59:59', '2026-09-25 00:00:00'] as $now) {
    $GLOBALS['iss_rotation_now'] = $now;
    $upcoming = iss_occurrences_query($query);
    $past = iss_occurrences_query(array_merge($query, ['time_mode' => 'past']));
    $before_midnight = $now < '2026-09-25 00:00:00';
    rotation_expect(count($upcoming) === ($before_midnight ? 1 : 0), 'SQL upcoming respects unknown-end boundary: ' . $now);
    rotation_expect(count($past) === ($before_midnight ? 0 : 1), 'SQL history complements upcoming: ' . $now);
}
// Repeated identical REST requests must observe a clock transition, not a stale transient.
$request = new WP_REST_Request('GET', '/iss/v1/timeline');
$request->set_param('filters', ['time_mode' => 'upcoming', 'source_post_ids' => [$phone_id], 'include_running_ranges' => true]);
$GLOBALS['iss_rotation_now'] = '2026-09-24 23:59:59';
$before = iss_timeline_rest_render_collection($request)->get_data();
$GLOBALS['iss_rotation_now'] = '2026-09-25 00:00:00';
$after = iss_timeline_rest_render_collection($request)->get_data();
rotation_expect($before['count'] === 1 && $after['count'] === 0, 'Identical REST request sees midnight rotation immediately.');
$GLOBALS['iss_rotation_now'] = '2026-09-22 12:00:00';
$service = iss_occurrences_get_service();
$formatted = $service->format_timeline_row(['source_post_type' => 'ausstellung', 'starts_at' => '2020-01-01 00:00:00', 'is_open_ended' => 1]);
rotation_expect($formatted['is_open_ended'] === true, 'Service passes indefinite flag to presentation.');
$ticket_row = array_merge($event, ['source_post_id' => $phone_id, 'availability_state' => 'sold_out']);
$html = industriesalon_programme_entry($ticket_row, 'row', $GLOBALS['iss_rotation_now']);
rotation_expect(strpos($html, 'Ausgebucht') !== false && strpos($html, '>Tickets<') === false && strpos($html, '>Details<') !== false, 'Sold-out entry has notice and details instead of purchase CTA.');
$original_user = get_current_user_id();
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
wp_set_current_user((int) $admins[0]);
ob_start(); iss_content_model_render_project_promotion_control(get_post($phone_id)); $control = ob_get_clean();
rotation_expect(strpos($control, 'type="date" name="iss_graph_related_promotion[expires_at]"') !== false, 'Event expiry is visible to administrator in existing promotion control.');
ob_start(); iss_content_model_render_veranstaltung_basis_box(get_post($phone_id)); $control = ob_get_clean();
rotation_expect(strpos($control, 'iss_content_model[iss_event_status]') !== false, 'Status is exposed in native event form.');
$old_query = $GLOBALS['wp_query'];
$GLOBALS['wp_query'] = new WP_Query(['pagename' => 'veranstaltungen']);
$_GET['programme_at'] = '2026-10-05T12:00';
$_GET['_wpnonce'] = wp_create_nonce('iss_programme_preview');
rotation_expect(iss_programm_preview_datetime() === '2026-10-05 12:00:00', 'Authenticated nonce-protected date preview accepted.');
remove_filter('iss_occurrences_query_now', 'iss_rotation_test_clock', 99);
rotation_expect(iss_occurrences_query_now() === '2026-10-05 12:00:00', 'Authenticated preview controls the occurrence query clock.');
rotation_expect(!iss_occurrences_query($query), 'Authenticated preview excludes the completed telephone event.');
$preview_html = iss_programm_render_cards_block(['presentation' => 'programme', 'timeMode' => 'upcoming', 'includeRunningRanges' => true, 'postTypesList' => ['veranstaltung', 'ausstellung']]);
rotation_expect(strpos($preview_html, 'Vorschau: 2026-10-05') !== false && strpos($preview_html, 'Noch bis 4. Oktober') === false, 'Future overview includes preview banner and removes closed exhibitions.');
$expiry = iss_graph_get_service()->normalize_editorial_signal_expiry('2026-10-25');
rotation_expect(get_date_from_gmt($expiry) === '2026-10-25 23:59:59', 'Existing graph expiry preserves Berlin day end on DST boundary.');
$_GET['programme_at'] = '2026-02-30T12:00';
rotation_expect(iss_programm_preview_datetime() === '', 'Invalid calendar dates rejected.');
$_GET['programme_at'] = '2026-10-05T12:00';
$_GET['_wpnonce'] = 'invalid';
rotation_expect(iss_programm_preview_datetime() === '', 'Invalid nonce cannot override date.');
wp_set_current_user(0);
rotation_expect(iss_programm_preview_datetime() === '', 'Public visitors cannot alter display date.');
rotation_expect(industriesalon_programme_preview_form($GLOBALS['iss_rotation_now']) === '', 'Preview controls hidden from public visitors.');
wp_set_current_user($original_user);
$GLOBALS['wp_query'] = $old_query;
unset($_GET['programme_at'], $_GET['_wpnonce']);
WP_CLI::success($GLOBALS['iss_rotation_checks'] . ' rotation checks passed; no records changed.');
