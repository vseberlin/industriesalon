<?php
/** Read-only renderer, ownership and saved-block compatibility checks. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void { ++$checks; if (!$ok) { throw new RuntimeException($label); } };
$source = null;
foreach (get_posts(['post_type' => 'fuehrung', 'post_status' => 'publish', 'posts_per_page' => -1]) as $candidate) {
    if (iss_occurrences_get_booking_slots(['source_post_id' => $candidate->ID, 'source_post_type' => 'fuehrung'])) { $source = $candidate; break; }
}
$expect($source instanceof WP_Post, 'Fixture contains a published tour with owner dates');
$context = iss_programm_booking_context(['source_post_id' => $source->ID, 'source_post_type' => 'fuehrung']);
$expect($context['id'] === $source->ID && $context['tag'] === iss_occurrences_resolve_tag_for_source_post_id($source->ID), 'Direct source and inline availability use the same owner tag');
$expect(json_decode(iss_programm_booking_scope($context), true) === [$source->ID, 'fuehrung', $context['tag']], 'Cache identity includes ID, type and tag');
$expect(iss_programm_booking_context(['source_post_id' => 1, 'source_post_type' => 'publication'])['intent'] === 'order', 'Publication shares the order flow');
$expect(iss_programm_booking_context(['source_post_id' => $source->ID, 'source_post_type' => 'fuehrung', 'intent' => 'inquiry'])['intent'] === 'inquiry', 'Explicit inquiry intent retained');
$writes = [];
$observe = static function ($query) use (&$writes) {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $query)) { $writes[] = $query; }
    return $query;
};
add_filter('query', $observe);
try {
    $html = iss_programm_render_tour_calendar_shell('Fixture', '', $source->ID, 'fuehrung', 'class="is-tour-calendar"');
    $expect(str_contains($html, '<details') && str_contains($html, '<li>') && str_contains($html, 'mailto:'), 'Initial HTML contains dates and public contact fallback');
    $expect(str_contains($html, 'data-wp-on--click="actions.open"') && !str_contains($html, '<script'), 'PHP renderer emits declarative booking controls');
    $expect(!$writes, 'Inline rendering does not maintain mappings or write public data');
} finally { remove_filter('query', $observe); }
$catalog = wp_interactivity_state('iss/booking')['catalog'];
$expect(isset($catalog[iss_programm_booking_scope($context)]['slots']), 'Known complete availability is serialized for client reuse');
$row = ['source_post_id' => $source->ID, 'source_post_type' => 'fuehrung', 'slot_id' => 'fixture', 'slot_start' => '2030-10-11 10:00:00', 'booking_url' => 'https://example.invalid/tickets'];
$expect(isset(iss_timeline_get_ticket_action_for_occurrence($row)['attrs']['data-wp-on--click']), 'Direct slot action uses shared dialog');
$row['cta_mode'] = 'external';
$expect(!isset(iss_timeline_get_ticket_action_for_occurrence($row)['attrs']), 'Explicit external ticket destination remains navigation');
unset($row['cta_mode']);
$expect(!isset(iss_timeline_get_ticket_action_for_occurrence($row, ['ticketsButtonUrl' => 'https://example.invalid/override'])['attrs']), 'Editorial URL override remains navigation');
ob_start(); iss_programm_render_calendar_modal_host(); $dialog = ob_get_clean();
$expect(substr_count($dialog, '<dialog ') === 1 && substr_count($dialog, '<form ') === 1 && str_contains($dialog, 'type="date"') && str_contains($dialog, 'type="time"'), 'All request intents share one PHP-authored native dialog');
ob_start(); iss_programm_render_calendar_modal_host(); $repeated = ob_get_clean();
$expect($repeated === '', 'Repeated footer calls cannot duplicate the shared dialog');
foreach (['iss/tour-calendar', 'iss/tour-dates', 'industriesalon/program-cards', 'industriesalon/timeline-query'] as $name) {
    $block = WP_Block_Type_Registry::get_instance()->get_registered($name);
    $expect(!empty($block->supports['interactivity']), $name . ' processes native directives');
    $saved = '<!-- wp:' . $name . ' {"title":"Fixture"} /-->';
    $expect(parse_blocks(serialize_blocks(parse_blocks($saved))) === parse_blocks($saved), $name . ' saved attributes round-trip unchanged');
}
foreach (['is-tour-calendar-flatpickr', 'is-tour-calendar-flatpickr-l10n-de', 'is-tour-calendar', 'iss-payments-lite-publications'] as $handle) {
    $expect(!wp_script_is($handle, 'registered') && !wp_script_is($handle, 'enqueued'), 'Retired script has no registration: ' . $handle);
}
WP_CLI::success("Native booking: $checks read-only checks passed.");
