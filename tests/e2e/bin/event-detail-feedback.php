<?php
/** Local runtime regressions. Creates and removes its own fixtures; sends no mail. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
global $wpdb;
$checks = 0;
$expect = static function ($ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$before = $wpdb->get_results("SELECT ID, post_title, post_content, post_excerpt, post_status FROM {$wpdb->posts} WHERE post_type IN ('veranstaltung','rueckblick') ORDER BY ID", ARRAY_A);
$before_comments = $wpdb->get_results("SELECT * FROM {$wpdb->comments} ORDER BY comment_ID", ARRAY_A);
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
add_filter('notify_moderator', '__return_false');
add_filter('notify_post_author', '__return_false');
$ids = [];
try {
    $id = wp_insert_post(['post_type' => 'veranstaltung', 'post_status' => 'publish', 'post_title' => 'Event feedback regression ' . wp_generate_uuid4()]);
    $ids[] = $id;
    $expect($id > 0, 'Fixture created');
    $input = ['feedback_nonce' => wp_create_nonce('iss_feedback_' . $id), 'feedback_name' => "Gast O'Neil", 'feedback_text' => "Eine schöne Erinnerung.\nDanke für den Abend!", 'feedback_email' => 'fixture@example.invalid', 'feedback_consent' => '1'];
    $expect(is_wp_error(iss_content_submit_event_feedback($id, '', $input)), 'Closed intake rejects text');
    iss_content_set_upload_open($id, true);
    $key = iss_content_upload_key($id);
    $expect(is_wp_error(iss_content_submit_event_feedback($id, 'wrong', $input)), 'Invalid signed link rejected');
    foreach (['feedback_nonce' => '', 'feedback_name' => '', 'feedback_text' => '', 'feedback_consent' => '', 'feedback_email' => 'invalid', 'feedback_website' => 'spam', 'feedback_text-long' => str_repeat('x', 4001)] as $field => $value) {
        $case = $input; $case[str_replace('-long', '', $field)] = $value;
        $expect(is_wp_error(iss_content_submit_event_feedback($id, $key, $case)), 'Invalid field rejected: ' . $field);
    }
    $expect(is_wp_error(iss_content_submit_event_feedback($id, $key, array_merge($input, ['feedback_name' => ['bad']]))), 'Array input rejected');
    $comment = iss_content_submit_event_feedback($id, $key, $input);
    $expect(is_int($comment) && $comment > 0, 'Text without attachment accepted');
    $expect(get_comment($comment)->comment_approved === '0', 'Even administrator submissions require moderation');
    $expect(get_comment($comment)->comment_author === $input['feedback_name'], 'Apostrophe survives sanitization');
    $expect(get_comment_meta($comment, '_iss_feedback_consent', true) === '1', 'Consent retained');
    $expect(!str_contains(industriesalon_render_event_feedback($id), $input['feedback_text']), 'Pending feedback never rendered');
    $expect(is_wp_error(iss_content_submit_event_feedback($id, $key, $input)), 'Duplicate submission rejected');
    wp_set_comment_status($comment, 'approve');
    $html = industriesalon_render_event_feedback($id);
    $expect(str_contains($html, 'Eine schöne Erinnerung.'), 'Approval makes feedback visible');
    $expect(!str_contains($html, 'fixture@example.invalid'), 'Email stays private');
    $pagination_ids = [];
    for ($i = 0; $i < 13; ++$i) {
        $pagination_ids[] = wp_insert_comment(['comment_post_ID' => $id, 'comment_type' => 'iss_feedback', 'comment_approved' => 1, 'comment_author' => 'Pagination fixture', 'comment_content' => 'Voice ' . $i, 'comment_meta' => ['_iss_feedback_consent' => '1']]);
    }
    $expect(substr_count(industriesalon_render_event_feedback($id), '<blockquote') === 12, 'Public feedback is paginated');
    $_GET['feedback_page'] = 2;
    $expect(substr_count(industriesalon_render_event_feedback($id), '<blockquote') === 2, 'Older feedback remains reachable');
    unset($_GET['feedback_page']);
    foreach ($pagination_ids as $pagination_id) { wp_delete_comment($pagination_id, true); }
    $injection = wp_insert_comment(['comment_post_ID' => $id, 'comment_type' => 'iss_feedback', 'comment_approved' => 1, 'comment_author' => '<script>name</script>', 'comment_content' => '<script>unsafe()</script>', 'comment_meta' => ['_iss_feedback_consent' => '1']]);
    $expect(!str_contains(industriesalon_render_event_feedback($id), '<script>'), 'Stored markup cannot execute in public feedback');
    wp_delete_comment($injection, true);
    wp_set_comment_status($comment, 'hold');
    $expect(!str_contains(industriesalon_render_event_feedback($id), 'Eine schöne Erinnerung.'), 'Withdrawal immediately removes public text');
    wp_set_comment_status($comment, 'approve');
    iss_content_set_upload_open($id, false);
    $expect(!str_contains(industriesalon_render_event_feedback($id), 'Erinnerung oder Fotos beitragen'), 'Closing intake hides invitation');
    $expect(str_contains(industriesalon_render_event_feedback($id), 'Eine schöne Erinnerung.'), 'Closing intake retains approved contributions');
    iss_content_set_upload_open($id, true);
    $expect(is_wp_error(iss_content_submit_event_feedback($id, $key, $input)), 'Reopening invalidates old link');
    wp_update_post(['ID' => $id, 'post_status' => 'draft']);
    $expect(industriesalon_render_event_feedback($id) === '', 'Draft event never exposes feedback');
    $expect(is_wp_error(iss_content_submit_event_feedback($id, iss_content_upload_key($id), $input)), 'Draft event refuses public feedback');
    wp_update_post(['ID' => $id, 'post_status' => 'publish', 'post_password' => 'test-only']);
    $expect(industriesalon_render_event_feedback($id) === '', 'Password protection covers feedback');
    $expect(is_wp_error(iss_content_submit_event_feedback($id, iss_content_upload_key($id), $input)), 'Protected event refuses anonymous feedback');

    // Shared date query: recurring future, running, completed, missing projection.
    $clock = static fn() => '2026-09-27 12:00:00';
    add_filter('iss_occurrences_query_now', $clock, 100);
    wp_update_post(['ID' => $id, 'post_password' => '']);
    update_post_meta($id, 'iss_start_datetime', '2026-07-01 17:00:00');
    update_post_meta($id, 'iss_end_datetime', '2026-09-09 19:00:00');
    $occurrences = iss_occurrences_get_service();
    foreach (['2026-09-09 17:00:00', '2026-10-07 17:00:00'] as $date) {
        $occurrences->upsert_occurrence(['source_post_id' => $id, 'source_post_type' => 'veranstaltung', 'kind' => 'event', 'origin' => 'wp', 'external_id' => 'feedback-fixture:' . $id . ':' . $date, 'title' => 'Temporary recurring event', 'starts_at' => $date, 'ends_at' => substr($date, 0, 10) . ' 19:00:00']);
    }
    $next = iss_occurrences_get_next_dates($id, 1);
    $rows = iss_content_model_get_meta_rows_for_post($id);
    $expect(count($next) === 1 && $rows[0]['value'] === $next[0]['datetime_label'], 'Recurring detail date equals overview occurrence');
    $expect(!str_contains(wp_json_encode($rows), 'Juli'), 'Old source date range not displayed as next appointment');
    $occurrences->delete_source_occurrences($id, 'veranstaltung');
    delete_post_meta($id, 'iss_end_datetime');
    remove_filter('iss_occurrences_query_now', $clock, 100);
    wp_update_post(['ID' => $id, 'post_password' => '']);
    update_post_meta($id, 'iss_start_datetime', '2026-10-10 17:00:00');
    $expect(str_contains(iss_content_model_get_meta_rows_for_post($id)[0]['value'], '10. Oktober'), 'Unprojected editorial date remains available');
    // The shared projection preserves a running event and advances after its end.
    update_post_meta($id, 'iss_end_datetime', '2026-10-10 19:00:00');
    update_post_meta($id, 'iss_programme_enabled', '1');
    wp_update_post(['ID' => $id]);
    $running_clock = static fn() => '2026-10-10 18:00:00';
    add_filter('iss_occurrences_query_now', $running_clock, 100);
    $expect(iss_content_model_get_meta_rows_for_post($id)[0]['label'] === 'Termin', 'Running event remains current');
    remove_filter('iss_occurrences_query_now', $running_clock, 100);
    $past_clock = static fn() => '2026-10-11 00:00:00';
    add_filter('iss_occurrences_query_now', $past_clock, 100);
    $expect(iss_content_model_get_meta_rows_for_post($id)[0]['label'] === 'Vergangener Termin', 'Completed event is clearly historical');
    remove_filter('iss_occurrences_query_now', $past_clock, 100);
    $document = ['schema_version' => 1, 'entity_key' => 'event.general', 'sections' => [['type' => 'material', 'kicker' => 'Tickets', 'links' => [['label' => 'Tickets buchen', 'url' => 'https://example.org/book']]]], 'hero_title' => 'A short headline', 'hero_subtitle' => 'A second line', 'hero_kicker' => 'Series', 'hero_image_fit' => 'cover'];
    $expect(iss_editorial_save_document($id, 'veranstaltung', $document), 'Opening fields save through the shared document API');
    $saved = iss_content_model_veranstaltung_content_document($id);
    $expect($saved['hero_title'] === 'A short headline' && $saved['hero_image_fit'] === 'cover', 'Opening fields survive canonical readback');
    $document['hero_subtitle'] = '';
    $expect(iss_editorial_save_document($id, 'veranstaltung', $document) && iss_content_model_veranstaltung_content_document($id)['hero_subtitle'] === '', 'Editors can clear optional title fields');
    $rows = iss_content_model_get_meta_rows_for_post($id);
    $summary = apply_filters('iss_content_meta_presentation', null, $id, $rows, ['presentation' => 'event-summary']);
    $expect(str_contains($summary, 'https://example.org/book') && str_contains($summary, 'Tickets'), 'Upcoming event uses its real external ticket action');
    $visit = apply_filters('iss_content_meta_presentation', null, $id, $rows, ['presentation' => 'event-visit']);
    $expect(str_contains($visit, 'id="besuch"') && !str_contains($visit, 'maps/search'), 'Unknown venue has no invented directions');
    add_filter('iss_occurrences_query_now', $past_clock, 100);
    $summary = apply_filters('iss_content_meta_presentation', null, $id, $rows, ['presentation' => 'event-summary']);
    $expect(str_contains($summary, 'Erinnerung beitragen') && !str_contains($summary, 'Besuch planen'), 'Past opening invites memories instead of a visit');
    $old_query = $GLOBALS['wp_query']; $old_main = $GLOBALS['wp_the_query']; $old_post = $GLOBALS['post'] ?? null;
    $GLOBALS['wp_query'] = new WP_Query(['p' => $id, 'post_type' => 'veranstaltung']);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $GLOBALS['wp_query']->the_post();
    $rendered = do_blocks('<!-- wp:post-content /-->');
    $expect(!str_contains($rendered, 'https://example.org/book'), 'Past announcement has no active ticket links');
    $expect(str_contains($rendered, 'Stimmen zur Veranstaltung') && str_contains($rendered, 'Die Ankündigung') && strpos($rendered, 'Stimmen zur Veranstaltung') < strpos($rendered, 'Die Ankündigung'), 'Past event places visitor voices before original announcement');
    $title_html = do_blocks('<!-- wp:post-title {"level":1,"className":"iss-event-hero__title"} /-->');
    $expect(str_contains($title_html, 'A short headline') && substr_count($title_html, '<h1') === 1, 'Native title uses explicit opening with one h1');
    $GLOBALS['wp_query'] = $old_query; $GLOBALS['wp_the_query'] = $old_main; $GLOBALS['post'] = $old_post;
    remove_filter('iss_occurrences_query_now', $past_clock, 100);
    $row = iss_content_model_event_appointment($id)['row'];
    $cards = apply_filters('iss_programm_cards_presentation', null, [$row, $row, $row], ['presentation' => 'event-next']);
    $expect(substr_count($cards, '<article') === 1, 'Upcoming cards never repeat the same recurring event');
    $expect(industriesalon_event_ticket_action($id, ['availability_state' => 'cancelled', 'booking_url' => 'https://example.org/book']) === [], 'Cancelled events suppress external booking actions');
    update_post_meta($id, 'iss_programme_enabled', '0');
    wp_update_post(['ID' => $id]);
    add_filter('iss_occurrences_query_now', $past_clock, 100);
    $expect(!empty(iss_content_model_event_appointment($id)['past']), 'Unprojected dated event still becomes historical');
    remove_filter('iss_occurrences_query_now', $past_clock, 100);
    $template = get_block_template('industriesalon//single-veranstaltung', 'wp_template');
    $expect($template->source === 'theme', 'Theme template is effective');
    $blocks = parse_blocks($template->content);
    $expect(serialize_blocks($blocks) === $template->content, 'Template block serialization round trip');
    WP_CLI::log("PASS: $checks event/feedback assertions.");
} finally {
    foreach ($ids as $id) { wp_delete_post($id, true); }
    $expect($before === $wpdb->get_results("SELECT ID, post_title, post_content, post_excerpt, post_status FROM {$wpdb->posts} WHERE post_type IN ('veranstaltung','rueckblick') ORDER BY ID", ARRAY_A), 'Existing events and reports unchanged');
    $expect($before_comments === $wpdb->get_results("SELECT * FROM {$wpdb->comments} ORDER BY comment_ID", ARRAY_A), 'Existing comments unchanged');
    WP_CLI::log('PASS: own fixtures removed; existing events, reports and comments unchanged.');
}
