<?php
/** wp eval-file: request-local ticket lookup regressions; fixtures live only in the native object cache. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$expect(!wp_using_ext_object_cache(), 'Fixture cache must be local to this PHP process');
$fixtures = [];
$sanitizations = 0;
$count = static function ($document) use (&$sanitizations) { ++$sanitizations; return $document; };
$no_writes = static function (string $sql): string {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $sql)) {
        throw new RuntimeException('Ticket lookup attempted a database write');
    }
    return $sql;
};
$store = static function (int $id, $document): void {
    $format = iss_editorial_get_format_for_post($id);
    $meta = $document === null ? [] : [iss_editorial_get_document_meta_key($format['slug']) => [
        is_array($document) ? iss_editorial_encode_document($document) : $document,
    ]];
    wp_cache_set($id, $meta, 'post_meta');
};
$document = static function (string $url): array {
    return ['schema_version' => 3, 'sections' => [
        ['type' => 'fliesstext', 'body' => str_repeat('<p>Programme narrative.</p>', 40)],
        ['type' => 'material', 'links' => [
            ['label' => 'Information', 'url' => 'https://example.invalid/information'],
            ['label' => 'Ticket', 'url' => 'javascript:alert(1)'],
            ['label' => 'Tickets & Infos', 'url' => $url],
            ['label' => 'Tickets', 'url' => 'https://example.invalid/second-ticket'],
        ]],
    ]];
};
add_filter('query', $no_writes);
add_filter('iss_editorial_sanitized_document', $count, PHP_INT_MAX);
try {
    foreach ([1900000101 => 'veranstaltung', 1900000102 => 'ausstellung', 1900000103 => 'post', 1900000104 => 'attachment'] as $id => $type) {
        $expect(get_post($id) === null, 'Fixture ID does not refer to an existing post');
        wp_cache_set($id, new WP_Post((object) [
            'ID' => $id, 'post_type' => $type, 'post_status' => 'publish',
            'post_title' => 'Ticket lookup fixture', 'post_name' => 'ticket-lookup-fixture',
        ]), 'posts');
        wp_cache_set($id, [], 'post_meta');
        $fixtures[] = $id;
    }
    [$event, $exhibition, $article, $unsupported] = $fixtures;
    $url = 'https://example.invalid/tickets?a=1&b=2';
    $store($event, $document($url));
    $before = $sanitizations;
    for ($i = 0; $i < 20; ++$i) {
        $action = industriesalon_event_ticket_action($event, ['booking_url' => 'https://example.invalid/date/' . $i]);
        $expect($action === ['url' => $url, 'label' => __('Tickets', 'industriesalon')], 'Every occurrence uses the first safe editorial ticket link');
    }
    $expect($sanitizations === $before + 1, 'Twenty occurrences sanitize the document only once');
    foreach (['cancelled', 'sold_out'] as $status) {
        $expect(industriesalon_event_ticket_action($event, ['availability_state' => $status, 'booking_url' => $url]) === [], 'Closed occurrence suppresses cached tickets and booking fallback');
    }
    $expect($sanitizations === $before + 1, 'Closed occurrences do not read the document');
    $expect(industriesalon_event_ticket_action($event, [])['url'] === $url, 'An open occurrence after a closed one retains its ticket link');

    $store($exhibition, $document('https://example.invalid/exhibition'));
    $expect(industriesalon_event_ticket_action($exhibition, [])['url'] === 'https://example.invalid/exhibition', 'Exhibition tickets have a separate post cache');
    $store($article, $document('https://example.invalid/article'));
    $expect(industriesalon_event_ticket_action($article, [])['url'] === 'https://example.invalid/article', 'Post type post resolves through the article format owner');
    $fallback = ['booking_url' => 'https://example.invalid/booking'];
    foreach ([$unsupported, 0, 1900000199] as $id) {
        $expect(industriesalon_event_ticket_action($id, $fallback) === ['url' => $fallback['booking_url'], 'label' => __('Buchen', 'industriesalon')], 'Missing or unsupported documents retain the occurrence booking fallback');
    }

    $empty = ['schema_version' => 3, 'sections' => [], 'deleted_sections' => [
        ['type' => 'material', 'links' => [['label' => 'Tickets', 'url' => $url]]],
    ]];
    $store($event, $empty);
    $before = $sanitizations;
    for ($i = 0; $i < 5; ++$i) {
        $booking_url = 'https://example.invalid/date/' . $i;
        $expect(industriesalon_event_ticket_action($event, ['booking_url' => $booking_url])['url'] === $booking_url, 'A cached absence preserves each occurrence booking URL');
    }
    $expect(industriesalon_event_ticket_action($event, []) === [], 'No ticket or booking URL gives no action; deleted sections cannot supply tickets');
    $expect($sanitizations === $before + 1, 'Absence of an editorial ticket is cached too');

    $changed = $document('https://example.invalid/changed');
    $store($event, $changed);
    $expect(industriesalon_event_ticket_action($event, [])['url'] === 'https://example.invalid/changed', 'A changed canonical document refreshes the cached ticket');
    $changed['sections'][1]['links'] = [['label' => 'Tickets', 'url' => 'javascript:alert(1)']];
    $store($event, $changed);
    $expect(industriesalon_event_ticket_action($event, []) === [], 'An unsafe replacement cannot reuse the previously safe ticket');
    foreach (['{"unfinished":', null] as $missing) {
        $store($event, $missing);
        $expect(industriesalon_event_ticket_action($event, $fallback)['url'] === $fallback['booking_url'], 'Malformed or removed JSON falls back without retaining an old ticket');
    }
    $store($event, $document($url));
    $expect(industriesalon_event_ticket_action($event, [])['url'] === $url, 'Restoring a document refreshes a cached absence');
    $row = ['source_post_id' => $event, 'source_post_type' => 'veranstaltung', 'start_raw' => '2030-01-01 18:00:00'];
    foreach (['feature', 'feature-compact', 'row'] as $variant) {
        $html = industriesalon_programme_entry($row, $variant, '2029-12-01 12:00:00');
        $expect(str_contains($html, 'href="' . esc_url($url) . '"') && !str_contains($html, 'javascript:'), 'Programme rendering preserves escaping for ' . $variant);
    }
    WP_CLI::success("Ticket cache: $checks checks passed; no records changed.");
} finally {
    foreach ($fixtures as $id) {
        wp_cache_delete($id, 'posts');
        wp_cache_delete($id, 'post_meta');
    }
    remove_filter('iss_editorial_sanitized_document', $count, PHP_INT_MAX);
    remove_filter('query', $no_writes);
}
