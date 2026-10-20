<?php
/** Read-only regressions for the October audit; run after its content migrations. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$today = '2026-10-20';
$date_filter = static function ($date, $format) use (&$today) { return $format === 'Y-m-d' ? $today : $date; };
add_filter('wp_date', $date_filter, 99, 2);
add_filter('iss_occurrences_query_now', static fn(): string => '2026-10-20 10:00:00', 99);
$exhibition = get_page_by_path('ausstellung-judisches-leben-und-arbeiten-in-schoneweide', OBJECT, 'ausstellung');
$expect($exhibition instanceof WP_Post, 'Current exhibition fixture exists.');
$contains = static fn(array $posts): bool => in_array($exhibition->ID, wp_list_pluck($posts, 'ID'), true);
$expect($contains(iss_programm_get_ausstellungen_for_filter('aktuell', 3)), 'Current exhibition cannot be crowded out by permanent/digital entries.');
foreach (iss_programm_get_ausstellungen_for_filter('aktuell', 24) as $post) {
    $expect(!iss_programm_ausstellung_has_type($post->ID, ['dauerausstellung', 'digitaleausstellungen']), 'Current physical exhibitions have their own tab.');
}
$expect((bool) iss_programm_get_ausstellungen_for_filter('dauer', 24), 'Permanent exhibitions remain accessible.');
$expect((bool) iss_programm_get_ausstellungen_for_filter('digital', 24), 'Digital exhibitions remain accessible.');
$today = '2026-10-08';
$expect(!$contains(iss_programm_get_ausstellungen_for_filter('aktuell', 24)), 'Future exhibition is not current.');
$today = '2026-11-07';
$expect($contains(iss_programm_get_ausstellungen_for_filter('aktuell', 24)), 'Final exhibition day is inclusive.');
$today = '2026-11-08';
$expect(!$contains(iss_programm_get_ausstellungen_for_filter('aktuell', 24))
    && $contains(iss_programm_get_ausstellungen_for_filter('archiv', 24)), 'Expired exhibition moves to archive.');
remove_filter('wp_date', $date_filter, 99);
$public = get_page_by_path('elektropolis-tour', OBJECT, 'fuehrung');
$group = get_page_by_path('elektropolis-tour-individuell-buchbar', OBJECT, 'fuehrung');
$expect($public instanceof WP_Post && $group instanceof WP_Post && $public->ID !== $group->ID, 'Public and group tours are separate offers.');
$expect(iss_fuehrung_get_effective_booking_mode($public->ID) === 'calendar'
    && iss_fuehrung_get_effective_booking_mode($group->ID) === 'on_demand', 'Only the public offer uses calendar dates.');
$expect(get_post_meta($public->ID, 'offer_catalog_groups', true) === ['oeffentlich']
    && get_post_meta($group->ID, 'offer_catalog_groups', true) === ['gruppen'], 'Offers appear in their respective catalogues.');
$next = iss_fuehrung_get_next_event($public->ID);
$expect(is_array($next) && $next, 'Fixture uses the current occurrence-array contract.');
$expect(iss_relations_get_card_detail_line($public) === 'Nächster Termin: ' . iss_fuehrung_get_event_start_label($next), 'Related card matches the owner date instead of claiming no dates.');
$expect(!iss_fuehrung_get_next_event($group->ID) && !iss_occurrences_get_booking_slots(['source_post_id' => $group->ID, 'source_post_type' => 'fuehrung']), 'Group offer inherits no public slots.');
$expect(!iss_occurrences_booking_inquiry_allowed($public->ID, 'fuehrung')
    && iss_occurrences_booking_inquiry_allowed($group->ID, 'fuehrung'), 'Request API respects the separate public and on-demand booking modes.');
$slots = iss_occurrences_get_booking_slots(['source_post_id' => $public->ID, 'source_post_type' => 'fuehrung']);
$expect($slots && $slots[0]['amount'] === 1400 && str_contains($slots[0]['description'], 'ermäßigt 10'), 'Public booking data carries confirmed public prices.');
$expect(str_contains(iss_fuehrung_render_booking_box($public->ID), 'Termine und Verfügbarkeit'), 'Date chooser does not promise the nearest sold-out date is bookable.');
$expect(!str_contains(iss_fuehrung_render_booking_box($public->ID), 'Gruppenführung anfragen')
    && str_contains(iss_fuehrung_render_booking_box($group->ID), 'Gruppenführung anfragen'), 'Group enquiry belongs to its own offer.');
$licht = get_page_by_path('licht-und-transparenz', OBJECT, 'fuehrung');
$licht_group = get_page_by_path('licht-und-transparenz-gruppenbuchung', OBJECT, 'fuehrung');
$expect(iss_fuehrung_get_effective_booking_mode($licht->ID) === 'calendar'
    && iss_fuehrung_get_effective_booking_mode($licht_group->ID) === 'on_demand', 'Licht offers have separate calendar and group booking paths.');
$expect(get_post_meta($licht_group->ID, 'offer_catalog_groups', true) === ['gruppen']
    && str_contains($licht_group->post_title, 'Gruppenführung'), 'Licht group offer is identifiable in the group catalogue.');
foreach ([$public, $licht] as $tour) {
    $rates = iss_occurrences_get_booking_slots(['source_post_id' => $tour->ID, 'source_post_type' => 'fuehrung']);
    $expect($rates && $rates[0]['amount'] === 1400 && $rates[0]['reduced_amount'] === 1000, 'Public tour exposes both confirmed fares.');
}
foreach ([$licht, $licht_group] as $tour) {
    $document = wp_json_encode(iss_editorial_get_document($tour->ID, 'fuehrung', false), JSON_UNESCAPED_UNICODE);
    $expect(str_contains($document, 'Behrensbau') && str_contains($document, 'geschlossen'), 'Architecture tour explains the interior closure.');
}
$owner = iss_occurrences_get_service();
foreach (['public:73836755', 'public:72767289', 'public:73058861'] as $external_id) {
    $expect(!$owner->get_occurrence_by_external('supersaas', $external_id)
        && $owner->get_supersaas_slot_by_external($external_id)['review_state'] === 'ignored', 'Individual reservation retained privately and excluded from public calendar.');
}
$special = $owner->get_occurrence_by_external('supersaas', 'public:74269614');
$expect(str_contains($owner->format_timeline_row($special)['title'], 'Spezial mit Berliner Unterwelten')
    && $owner->format_timeline_row($special)['time_label'] === '14:00 – 15:30 Uhr', 'Special appointment retains its label and actual time span.');
foreach (['judische-unternehmen-im-historischen-berlin-bustour', 'kiez-tour-wilhelminenhofstrase-sa-2-5-26-14-uhr'] as $slug) {
    $tour = get_page_by_path($slug, OBJECT, 'fuehrung');
    $document = wp_json_encode(iss_editorial_get_document($tour->ID, 'fuehrung', false), JSON_UNESCAPED_UNICODE);
    $expect(!preg_match('/(?:2\. Mai|30\. Mai|30\.05\.|02\.05\.|Die nächsten Bustouren)/u', $document . $tour->post_excerpt), 'Evergreen tour copy delegates dates to its calendar.');
    $expect((int) get_post_meta($tour->ID, 'booking_price_cents', true) > 0, 'Published tour rate reaches booking configuration.');
}
$album = get_page_by_path('nef-album', OBJECT, 'publication');
$expect($album instanceof WP_Post && substr_count(iss_publications_get_card_kicker($album->ID), 'Fotoalbum') === 1, 'Album format/type label is not repeated.');
foreach (['page-ausstellungen', 'page-sammlungen', 'front-page', 'page-ueber-uns'] as $slug) {
    $template = get_block_template('industriesalon//' . $slug, 'wp_template');
    $expect($template && $template->source === 'theme', 'Template authority stays on disk: ' . $slug);
    $parsed = parse_blocks($template->content);
    $expect(parse_blocks(serialize_blocks($parsed)) === $parsed, 'Template block roundtrip: ' . $slug);
}
$note = get_page_by_path('evtl-txl-projekt-im-salon-nahere-infos-kommen-schon-mal-zeitraum-geblockt', OBJECT, 'veranstaltung');
$expect($note instanceof WP_Post && $note->post_status === 'draft', 'Internal reservation stays unpublished.');
WP_CLI::success("$checks website audit checks passed; no messages, bookings or content writes.");
