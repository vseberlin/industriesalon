<?php
/** Read-only checkout routing regressions; fixtures exist only in the native object cache. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$expect(!wp_using_ext_object_cache(), 'Fixture cache is private to this PHP process');
$fixtures = [];
$no_writes = static function (string $sql): string {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $sql)) {
        throw new RuntimeException('Ticket rendering attempted a database write');
    }
    return $sql;
};
add_filter('query', $no_writes);
try {
    foreach ([1900000101 => 'veranstaltung', 1900000102 => 'ausstellung', 1900000103 => 'post', 1900000104 => 'attachment'] as $id => $type) {
        $expect(get_post($id) === null, 'Fixture ID is unused');
        wp_cache_set($id, new WP_Post((object) [
            'ID' => $id, 'post_type' => $type, 'post_status' => 'publish',
            'post_title' => 'Ticket routing fixture', 'post_name' => 'ticket-routing-fixture',
        ]), 'posts');
        wp_cache_set($id, [], 'post_meta');
        $fixtures[] = $id;
    }
    [$event, $exhibition, $article, $unsupported] = $fixtures;
    $external = ['booking_url' => 'https://www.supersaas.de/schedule/old', 'cta_mode' => 'external'];
    $expect(industriesalon_event_ticket_action($event, $external) === [], 'Disabled booking cannot be enabled by a provider URL');
    $document = ['schema_version' => 1, 'sections' => [['type' => 'material', 'links' => [['label' => 'Tickets', 'url' => 'https://example.invalid/book']]]]];
    wp_cache_set($event, ['iss_booking_enabled' => ['1'], '_iss_editorial_veranstaltung' => [wp_json_encode($document)]], 'post_meta');
    for ($i = 0; $i < 20; ++$i) {
        $action = industriesalon_event_ticket_action($event, $external + ['slot_id' => 'fixture-' . $i]);
        $context = json_decode($action['attrs']['data-wp-context'], true);
        $expect($action['url'] === get_permalink($event) . '#besuch'
            && $action['attrs']['data-wp-on--click'] === 'actions.open'
            && $context['source']['slotId'] === 'fixture-' . $i,
            'Each occurrence opens the same internal checkout with its own slot');
    }
    foreach (['cancelled', 'sold_out'] as $status) {
        $expect(industriesalon_event_ticket_action($event, ['availability_state' => $status] + $external) === [], 'Closed occurrence cannot book');
    }
    foreach ([$exhibition, $article, $unsupported, 0, 1900000199] as $id) {
        $expect(industriesalon_event_ticket_action($id, $external) === [], 'Unsupported content cannot become a bookable offer from an external URL');
    }
    $row = ['source_post_id' => $event, 'source_post_type' => 'veranstaltung', 'start_raw' => '2030-01-01 18:00:00'] + $external;
    foreach (['feature', 'feature-compact', 'row'] as $variant) {
        $html = industriesalon_programme_entry($row, $variant, '2029-12-01 12:00:00');
        $expect(str_contains($html, 'data-wp-on--click="actions.open"') && !str_contains($html, 'supersaas.de') && !str_contains($html, 'example.invalid'), 'Programme ' . $variant . ' exposes only internal checkout');
    }
    wp_cache_set($event, [], 'post_meta');
    $expect(industriesalon_event_ticket_action($event, $external) === [], 'Disabling booking takes effect immediately');
    $expect(!empty(WP_Block_Type_Registry::get_instance()->get_registered('iss/content-meta')->supports['interactivity']), 'Event summary processes checkout directives');
    WP_CLI::success("Ticket routing: $checks checks passed; no records changed.");
} finally {
    foreach ($fixtures as $id) { wp_cache_delete($id, 'posts'); wp_cache_delete($id, 'post_meta'); }
    remove_filter('query', $no_writes);
}
