<?php
/** Read-only acceptance for the second website audit package. No forms or emails. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'Cron disabled.');
foreach (['page-projekte', 'page-publikationen', 'page-sammlungen', 'page-archiv', 'page-ausstellungen', 'page-kalender', 'page-salon-vermietung', 'page-videos', 'single-archivobjekt'] as $slug) {
    $template = get_block_template('industriesalon//' . $slug, 'wp_template');
    $expect($template && $template->source === 'theme', 'Template authority: ' . $slug);
    $parsed = parse_blocks($template->content);
    $expect(parse_blocks(serialize_blocks($parsed)) === $parsed, 'Block roundtrip: ' . $slug);
}
$template = get_block_template('industriesalon//page-ausstellungen', 'wp_template');
$expect(substr_count($template->content, '<!-- wp:industriesalon/ausstellungen-browser ') === 1, 'One exhibition filter interface.');
$expect(str_contains($template->content, '"postTypes":["publication"]'), 'Publication query remains restricted.');
$project_query = apply_filters('query_loop_block_query_vars', ['post_type' => 'projekt', 'orderby' => 'menu_order', 'order' => 'ASC']);
$expect($project_query['orderby'] === ['menu_order' => 'ASC', 'ID' => 'ASC'], 'Stable project ordering for offset queries.');
$first = new WP_Query($project_query + ['posts_per_page' => 2, 'post_status' => 'publish', 'fields' => 'ids']);
$rest = new WP_Query($project_query + ['posts_per_page' => 24, 'offset' => 2, 'post_status' => 'publish', 'fields' => 'ids']);
$expect(!array_intersect($first->posts, $rest->posts), 'Featured and remaining projects do not overlap.');
$expected_titles = ['home-2' => 'Schöneweide entdecken', 'fuehrungen' => 'Mit uns durch Schöneweide'];
foreach ($expected_titles as $slug => $title) {
    $post = get_page_by_path($slug, OBJECT, 'page');
    $document = iss_editorial_get_document($post->ID, 'landing', false);
    $expect($document['sections'][0]['title'] === $title, 'Saved visitor introduction: ' . $slug);
}
foreach (['futura-biennale-2027-2', 'schoeneweider-kiezkarte'] as $slug) {
    $post = get_page_by_path($slug, OBJECT, 'projekt');
    $document = iss_editorial_get_document($post->ID, 'projekt', false);
    $expect(!str_contains(wp_json_encode($document['sections']), 'Übergabekonzept') && !str_contains(wp_json_encode($document['sections']), 'Projektübergabe'), 'No handover instructions: ' . $slug);
}
$contact = Industriesalon_Steuerung::instance()->get_all_data()['contact'];
foreach (['facebook', 'instagram', 'youtube'] as $key) { $expect($contact[$key] === '', 'Unconfirmed social placeholder removed: ' . $key); }
$form = iss_newsletter_render_form_block();
$expect(str_contains($form, 'Ihre E-Mail-Adresse') && str_contains($form, '<label'), 'Newsletter has a visible label and useful placeholder.');
$expect(str_contains($form, 'tnp-privacy') && str_contains($form, 'required'), 'Newsletter consent remains required.');
$object = get_page_by_path('kinder-mit-erzieherin-am-sandkasten-vor-dem-wf-gebaude', OBJECT, 'archivobjekt');
$projection = iss_wf_import_get_object_service()->get_projection_for_post($object->ID);
$html = apply_filters('iss_content_meta_presentation', null, $object->ID, [], ['presentation' => 'archive-object']);
$expect(str_contains($html, esc_url($projection['source_url'])) && str_contains($html, 'Originalnachweis'), 'Known photograph source is visible.');
$expect(!str_contains($html, 'Inventarnummer') && !str_contains($html, 'Urheber'), 'Missing object facts are not invented.');
$sampled = 0;
foreach (get_posts(['post_type' => 'archivobjekt', 'post_status' => 'publish', 'numberposts' => 100]) as $post) {
    $projection = iss_wf_import_get_object_service()->get_projection_for_post($post->ID);
    if (empty($projection['inventory_number']) || empty($projection['rights_status'])) { continue; }
    $html = apply_filters('iss_content_meta_presentation', null, $post->ID, [], ['presentation' => 'archive-object']);
    $expect(str_contains($html, esc_html($projection['inventory_number'])) && str_contains($html, esc_html($projection['rights_status'])), 'Existing inventory and rights are rendered.');
    ++$sampled;
    break;
}
$expect($sampled > 0, 'A catalogued object with rights was checked.');
$post = get_page_by_path('abriss-der-denkmalgeschutzten-fernmeldekabelfabrik-architekt-ernst-ziesel-im-jahr-2007', OBJECT, 'video');
$expect(get_post_meta($post->ID, 'iss_video_year', true) === '2007 (Abriss); 1927–1928 (Bau)', 'Video event and construction years distinguished.');
$expect(iss_content_model_format_video_original_date('2015-08-01') === '01.08.2015', 'Unambiguous German source date.');
$without_report = 0;
$with_report = 0;
foreach (get_posts(['post_type' => 'veranstaltung', 'post_status' => 'publish', 'numberposts' => -1]) as $post) {
    $state = iss_content_model_event_appointment($post->ID);
    if (empty($state['past'])) { continue; }
    $has_report = (bool) iss_content_report_connections($post->ID, true);
    if (($has_report && $with_report) || (!$has_report && $without_report)) { continue; }
    $html = apply_filters('iss_content_meta_presentation', null, $post->ID, [], ['presentation' => 'event-summary']);
    $expect(str_contains($html, $has_report ? 'href="#rueckblicke"' : 'href="#ankuendigung"'), 'Past event action follows existing material.');
    if ($has_report) { ++$with_report; } else { ++$without_report; }
}
$expect($without_report > 0 && $with_report > 0, 'Checked past events both with and without a report.');
echo 'Passed ' . (int) $checks . " audit completion checks.\n";
