<?php
/** Read-only series destination and landing-hero integration regressions. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($message); }
};
$event = get_page_by_path('repair-cafe-terminreihe', OBJECT, 'veranstaltung');
$expect($event instanceof WP_Post, 'Mapped Repair-Cafe source exists');
$target = home_url('/repair-cafe/');
$expect(iss_occurrences_event_series_landing_target_url('event:repair-cafe') === $target, 'Sync and public reads share the permanent site-local destination');
$expect(iss_occurrences_event_series_landing_target_url('event:other') === '', 'Other event destinations are unaffected');
$expect(iss_occurrences_sync_slot_landing_target_url(['raw_title' => 'Repair Café']) === $target, 'New slot mapping uses the same destination');
$writes = [];
$observe = static function ($sql) use (&$writes) {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $sql)) { $writes[] = $sql; }
    return $sql;
};
add_filter('query', $observe);
$stale = static function ($value, $post_id, $key) use ($event) {
    return $post_id === $event->ID && $key === 'iss_timeline_target_url' ? 'https://example.org/stale-import/' : $value;
};
add_filter('get_post_metadata', $stale, 10, 3);
try {
    foreach (['2026-10-14', '2026-11-04', '2027-04-07'] as $day) {
        $row = iss_occurrences_get_service()->format_timeline_row([
            'source_post_id' => $event->ID, 'source_post_type' => 'veranstaltung',
            'series_key' => 'event:repair-cafe', 'origin' => 'supersaas', 'kind' => 'event',
            'starts_at' => $day . ' 17:00:00', 'ends_at' => $day . ' 19:00:00',
        ]);
        $expect($row['content_url'] === $target && $row['cta_url'] === $target, 'Current/newly imported dates ignore stale source destinations: ' . $day);
    }
} finally { remove_filter('get_post_metadata', $stale, 10); }
$template = get_block_template(get_stylesheet() . '//page-repair-cafe', 'wp_template');
preg_match('/<!-- wp:cover (\{.*?\}) -->/s', $template->content, $match);
$hero = json_decode($match[1] ?? '{}', true);
$hero_id = (int) ($hero['id'] ?? 0);
$expect(wp_attachment_is_image($hero_id), 'Effective landing cover owns a usable hero attachment');
$hero_url = (string) wp_get_attachment_image_url($hero_id, 'large');
$image = iss_programm_cards_get_image($row, 'large', ['loading' => 'lazy']);
$expect(str_contains($image, 'src="' . esc_url($hero_url) . '"') && str_contains($image, 'loading="lazy"'), 'Programme image reuses the hero and native image attributes');
$renderers = [
    iss_programm_cards_render_item($row, ['showImage' => true]),
    iss_timeline_render_items_cards([$row]),
    iss_timeline_render_items_list([$row], ['programmeLayout' => true]),
    industriesalon_programme_entry($row, 'feature-compact', '2027-04-01 12:00:00'),
];
foreach ($renderers as $html) {
    $expect(str_contains($html, 'src="' . esc_url($hero_url) . '"') && str_contains($html, 'href="' . esc_url($target) . '"'), 'Every card/list presentation shares the landing image and destination');
}
$other_id = (int) get_posts(['post_type' => 'attachment', 'post_status' => 'inherit', 'post_mime_type' => 'image', 'posts_per_page' => 1, 'post__not_in' => [$hero_id], 'fields' => 'ids'])[0];
$override = static function ($value, $id) use ($other_id) {
    if ($id !== get_stylesheet() . '//page-repair-cafe') { return $value; }
    $template = new WP_Block_Template();
    $template->content = '<!-- wp:cover {"id":' . $other_id . '} --><div></div><!-- /wp:cover -->';
    return $template;
};
add_filter('pre_get_block_template', $override, 10, 2);
try {
    $expect(str_contains(iss_programm_cards_get_image($row), 'src="' . esc_url((string) wp_get_attachment_image_url($other_id, 'large')) . '"'), 'Changing the landing hero updates the programme without copying metadata');
} finally { remove_filter('pre_get_block_template', $override, 10); }
$expect(apply_filters('iss_programm_card_image', 'existing-image', ['series_key' => 'event:other'], 'large', []) === 'existing-image', 'Unrelated featured images remain unchanged');
$expect(!$writes, 'Link and hero resolution perform no database writes');
remove_filter('query', $observe);
WP_CLI::success("Repair-Cafe programme: $checks read-only checks passed.");
