<?php
/** Read-only automatic homepage feature, deduplication and renderer checks. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($message); }
};
$now = '2026-10-11 12:00:00';
$clock = static function () use (&$now): string { return $now; };
add_filter('iss_occurrences_query_now', $clock, 99);
$tour = ['id' => 1, 'source_post_id' => 910001, 'source_post_type' => 'fuehrung', 'start_raw' => '2026-10-12 10:00:00', 'end_raw' => '2026-10-12 12:00:00'];
$event = ['id' => 2, 'source_post_id' => 910002, 'source_post_type' => 'veranstaltung', 'start_raw' => '2026-10-14 17:00:00', 'end_raw' => '2026-10-14 19:00:00'];
$later = array_merge($event, ['id' => 3, 'source_post_id' => 910003, 'start_raw' => '2026-10-15 19:00:00', 'end_raw' => '2026-10-15 21:00:00']);
$repeat = array_merge($event, ['id' => 4, 'start_raw' => '2026-10-21 17:00:00', 'end_raw' => '2026-10-21 19:00:00']);
$exhibition = ['id' => 5, 'source_post_id' => 910005, 'source_post_type' => 'ausstellung', 'start_raw' => '2026-10-01 00:00:00', 'end_raw' => '2026-11-07 23:59:59'];
$cancelled = array_merge($event, ['id' => 6, 'source_post_id' => 910006, 'start_raw' => '2026-10-13 17:00:00', 'end_raw' => '2026-10-13 19:00:00', 'availability_state' => 'cancelled']);
$rows = [$exhibition, $tour, $cancelled, $event, $later, $repeat];
$preview = iss_programm_preview_items($rows, $now, 4);
$expect($preview['feature']['id'] === 2, 'An upcoming event leads; tours and cancelled events cannot displace it');
$expect(count($preview['items']) === 4, 'Feature removal fills the list with four remaining entries');
$expect(!in_array(910002, array_column($preview['items'], 'source_post_id'), true), 'Later dates of the featured source do not repeat beside it');
$expect(in_array(910001, array_column($preview['items'], 'source_post_id'), true), 'Tours remain available in the adjacent list');
$expect(iss_programm_preview_items([$event, $later], '2026-10-14 19:00:01', 4)['feature']['id'] === 3, 'Feature advances after the existing event end');
$distant = array_merge($later, ['start_raw' => '2027-01-01 19:00:00', 'end_raw' => '2027-01-01 21:00:00']);
$preview = iss_programm_preview_items([$exhibition, $distant], $now, 4);
$expect($preview['feature']['id'] === 5 && count($preview['items']) === 1, 'Quiet periods feature the current exhibition without hiding the distant event');
$expect(iss_programm_preview_items([$exhibition, $distant], '2026-11-08 00:00:00', 4)['feature']['id'] === 3, 'Closed exhibitions cannot remain featured');
$expect(iss_programm_preview_items([$tour], $now, 4)['feature'] === null, 'Tour-only programme does not invent a feature');
$expect(iss_programm_preview_items([], $now, 4) === ['feature' => null, 'items' => []], 'Empty programme is safe');
$attrs = ['presentation' => 'programme-preview', 'limit' => 4, 'postTypesList' => ['fuehrung', 'veranstaltung', 'ausstellung']];
$config = iss_programm_cards_build_block_config(array_merge($attrs, ['timeMode' => 'past']));
$expect($config['query']['filters']['time_mode'] === 'upcoming' && $config['query']['filters']['include_running_ranges'], 'Automatic preview always uses current and upcoming occurrences');
$expect($config['query']['filters']['post_types'] === $attrs['postTypesList'], 'Preview preserves the editor source boundary');
$empty = industriesalon_programme_overview(null, [], $attrs);
$expect(!str_contains($empty, 'iss-programme-preview--featured') && str_contains($empty, 'Keine Einträge gefunden'), 'Empty rendering collapses the feature column');
$html = industriesalon_programme_overview(null, [$tour], $attrs);
$expect(!str_contains($html, 'iss-card--programme-feature-compact') && str_contains($html, 'iss-timeline__item'), 'No eligible feature leaves the ordinary list visible');
$items = iss_timeline_get_items_advanced($config['query']);
$live = iss_programm_preview_items($items, $now, 4);
$expect($live['feature'] !== null && count($live['items']) === 4, 'Local public programme supplies a feature and four rows');
$writes = [];
$observe = static function ($query) use (&$writes) {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $query)) { $writes[] = $query; }
    return $query;
};
add_filter('query', $observe);
try {
    $html = do_blocks('<!-- wp:industriesalon/program-cards ' . wp_json_encode($attrs) . ' /-->');
    $expect(substr_count($html, 'iss-card--programme-feature-compact') === 1 && substr_count($html, '<article class="iss-timeline__item ') === 4, 'Existing programme block renders one feature and four timeline rows');
    $expect(str_contains($html, 'iss-timeline-slot-picker__trigger') && str_contains($html, 'data-wp-on--click='), 'Grouped tours retain their native booking chooser');
    $expect(str_contains($html, '/kalender/') && !str_contains($html, 'mailto:?subject='), 'Preview links to the full calendar and omits recommendation actions');
    $expect(!$writes, 'Public preview rendering performs no database writes');
} finally { remove_filter('query', $observe); }
$single = industriesalon_programme_overview(null, [$live['feature']], $attrs);
$expect(!str_contains($single, 'iss-programme-preview--featured') && str_contains($single, 'Weitere Termine werden angekündigt'), 'A single feature does not leave an empty second column');
$illustrated = null;
foreach ($items as $row) {
    if (has_post_thumbnail($row['source_post_id'])) { $illustrated = $row; break; }
}
$expect($illustrated !== null, 'Local programme includes an illustrated item');
$card = industriesalon_programme_entry($illustrated, 'feature-compact', $now);
$expect(str_contains($card, '<img') && str_contains($card, 'loading="lazy"') && str_contains($card, '<h3'), 'Compact feature uses the existing image and section heading level');
$expect(str_contains($card, 'Unser Tipp') && !str_contains($card, 'Als Nächstes'), 'Homepage feature does not claim to precede earlier tours in the adjacent list');
$original = industriesalon_programme_entry($illustrated, 'feature', $now);
$expect(str_contains($original, 'fetchpriority="high"') && str_contains($original, '<h2'), 'Existing programme opening retains its image loading and heading behavior');
$before = $live['feature'];
$now = (new DateTimeImmutable($before['end_raw'] ?: substr($before['start_raw'], 0, 10) . ' 23:59:59', wp_timezone()))->modify('+1 second')->format('Y-m-d H:i:s');
$after = iss_programm_preview_items(iss_timeline_get_items_advanced($config['query']), $now, 4);
$expect(($after['feature']['id'] ?? null) !== $before['id'], 'Owner query and feature rotate together after the real occurrence ends');
$template = get_block_template('industriesalon//front-page', 'wp_template');
$parsed = parse_blocks($template->content);
$expect($template->source === 'theme' && parse_blocks(serialize_blocks($parsed)) === $parsed, 'Homepage fallback remains theme-owned and round-trips as native blocks');
$expect(str_contains($template->content, 'programme-preview') && str_contains(industriesalon_render_front_timeline_slot(), 'iss-programme-preview'), 'Editorial slot and template fallback share the programme preview');
remove_filter('iss_occurrences_query_now', $clock, 99);
WP_CLI::success("Programme preview: $checks read-only checks passed.");
