<?php
/** Read-only GET contract and renderer checks. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$attributes = ['showItemTypeFilter' => true, 'allowedTypesList' => ['fuehrungen', 'veranstaltungen'], 'fixedItemTypesList' => ['event'], 'postTypesList' => ['veranstaltung'], 'showPostTypeFilter' => true, 'showTimeModeFilter' => true, 'allowedTimeModesList' => ['upcoming', 'month'], 'showCalendarBridge' => true, 'showLoadMore' => true, 'limit' => 2, 'showTicketsButton' => false, 'group' => 'fixture-group'];
$base = iss_timeline_build_query_block_config($attributes);
$apply = static fn(array $input): array => iss_timeline_apply_public_filters($base, $attributes, $input);
$result = $apply(['item_type' => 'fuehrungen', 'post_type' => 'post', 'time_mode' => 'all', 'limit' => '-1', 'group' => 'other', 'item_types' => ['tour']]);
$expect($result['filters'] === $base['filters'] && $result['groups'] === ['fixture-group'] && $result['limit'] === 2, 'GET cannot widen fixed types, sources, time choices, group or limit');
$expect(array_column($result['ui']['typeOptions'], 'value') === ['all', 'veranstaltungen'], 'Fixed restrictions remove unusable public type choices');
$both = $attributes; $both['fixedItemTypesList'] = ['event','tour']; $both['postTypesList'] = ['veranstaltung','fuehrung'];
$result = iss_timeline_apply_public_filters(iss_timeline_build_query_block_config($both), $both, ['item_type' => 'fuehrungen', 'post_type' => 'fuehrung']);
$expect($result['filters']['item_types'] === ['tour'] && $result['filters']['post_types'] === ['fuehrung'], 'Visible selections narrow rather than union fixed choices');
foreach (['2026-03-29','2026-10-25','2028-02-29'] as $day) {
    $result = $apply(['day' => $day]);
    $expect($result['filters']['time_mode'] === 'range' && $result['filters']['date_start'] === "$day 00:00:00" && $result['filters']['date_end'] === "$day 23:59:59", 'Date-only values preserve inclusive Berlin DST and leap dates');
}
foreach (['2026-02-29','2026-13-01','2026-10-25T12:00:00', ['nested']] as $day) {
    $expect($apply(['day' => $day])['filters'] === $base['filters'], 'Malformed date cannot alter query');
}
$expect($apply(['day' => '2030-10-11'])['selection']['month'] === '2030-10', 'A selected day displays its own month');
$expect(!$apply(['day' => '2030-10-11'])['selection']['presetActive'], 'A manual date does not claim the default range preset is active');
$expect($apply(['month' => '2026-13'])['filters'] === $base['filters'], 'Invalid month ignored');
$expect($apply(['month' => '2026-12'])['filters']['time_mode'] === 'month', 'Month selects server month filter');
$expect($apply(['page' => 3])['offset'] === 4 && $apply(['page' => -8])['offset'] === 0, 'Pagination is bounded and offset uses editor limit');
$expect($apply(['page' => 999999999])['offset'] === 1998, 'Extreme public page is bounded');
$hidden = $attributes; $hidden['showCalendarBridge'] = false; $hidden['showItemTypeFilter'] = false; $hidden['showTimeModeFilter'] = false; $hidden['showPostTypeFilter'] = false; $hidden['showLoadMore'] = false;
$hidden_base = iss_timeline_build_query_block_config($hidden);
$result = iss_timeline_apply_public_filters($hidden_base, $hidden, ['day' => '2026-10-25', 'month' => '2026-12', 'time_mode' => 'month', 'item_type' => 'fuehrungen', 'post_type' => 'fuehrung', 'page' => 9]);
$expect($result['filters'] === $hidden_base['filters'] && $result['offset'] === 0, 'Hidden controls cannot be supplied through GET');
$tax_base = $base; $tax_base['filters']['taxonomy_filters'] = [['taxonomy'=>'category','field'=>'slug','terms'=>['fixed'],'operator'=>'IN']];
$tax_base['ui']['taxonomyUiFilters'] = [['taxonomy'=>'category','options'=>[['value'=>'','label'=>'All'],['value'=>'allowed','label'=>'Allowed']]]];
$result = iss_timeline_apply_public_filters($tax_base, $attributes, ['taxonomy_category'=>['allowed','forged',['nested']]]);
$expect(count($result['filters']['taxonomy_filters']) === 2 && $result['filters']['taxonomy_filters'][0] === $tax_base['filters']['taxonomy_filters'][0] && $result['filters']['taxonomy_filters'][1]['terms'] === ['allowed'], 'Taxonomy UI appends allowed terms without replacing fixed rules');
$expect(iss_timeline_query_scope($attributes) !== iss_timeline_query_scope($attributes), 'Identical blocks have separate instance scopes');
$old_get = $_GET; $old_uri = $_SERVER['REQUEST_URI'] ?? null;
try {
    $_SERVER['REQUEST_URI'] = '/kalender/?ignored=1';
    $_GET = ['s'=>'public & search', 'iss_timeline'=>['fixture'=>['day'=>'2026-10-25'],'other'=>['item_type'=>'event']]];
    $url = iss_timeline_navigation_url('fixture', ['page'=>2]); parse_str((string) wp_parse_url($url, PHP_URL_QUERY), $args);
    $expect($args['s'] === 'public & search' && $args['iss_timeline']['other']['item_type'] === 'event' && $args['iss_timeline']['fixture'] === ['page'=>'2'], 'Navigation preserves routing and other instance state');
    parse_str((string) wp_parse_url(iss_timeline_navigation_url('fixture'), PHP_URL_QUERY), $args);
    $expect(!isset($args['iss_timeline']['fixture']) && isset($args['iss_timeline']['other']), 'Reset removes only its own instance');
    $_GET = [];
    $render_attrs = array_merge($attributes, ['group'=>'','fixedItemTypesList'=>[], 'allowedTypesList'=>['fuehrungen','veranstaltungen','ausstellungen'], 'postTypesList'=>[], 'timeMode'=>'all']);
    $html = iss_timeline_render_query_block($render_attrs);
    $expect(str_contains($html, 'method="get"') && str_contains($html, 'type="date"') && str_contains($html, 'type="submit"') && !str_contains($html, 'data-config=') && !str_contains($html, 'calendar-bridge'), 'Initial render uses native GET controls and no retired controller data');
    preg_match('/id="(timeline-[^"]+)"/', $html, $match); $scope = $match[1];
    $expect(str_contains($html, 'name="iss_timeline['.$scope.'][item_type]"'), 'Form names use the block scope');
    $expect(!wp_script_is('iss-timeline-query','registered') && !wp_script_is('iss-timeline-query','enqueued'), 'Old AJAX client has no active enqueue');
    $block = WP_Block_Type_Registry::get_instance()->get_registered('industriesalon/timeline-query');
    $expect(isset($block->attributes['showCalendarBridge'], $block->attributes['showLoadMore']), 'Existing saved attributes remain registered');
    $saved = '<!-- wp:industriesalon/timeline-query {"showCalendarBridge":true,"showLoadMore":true} /-->';
    $expect(parse_blocks(serialize_blocks(parse_blocks($saved))) === parse_blocks($saved), 'Saved block attributes round-trip unchanged');
} finally { $_GET = $old_get; if ($old_uri === null) { unset($_SERVER['REQUEST_URI']); } else { $_SERVER['REQUEST_URI'] = $old_uri; } }
WP_CLI::success("Native programme filters: $checks read-only checks passed.");
