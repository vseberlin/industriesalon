<?php
/** wp eval-file: cross-format registry, snapshot and theme integration; disposable fixtures only. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/post.php';
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) { throw new RuntimeException($message); }
    ++$checks;
};
$original_user = get_current_user_id();
$original_get = $_GET;
$original_query = $GLOBALS['wp_query'];
$original_main_query = $GLOBALS['wp_the_query'];
$original_post = $GLOBALS['post'] ?? null;
$fixtures = [];
$sets = [];
$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
wp_set_current_user($admin->ID);
$landing_id = 0;
$landing_fixture = static function (array $formats) use (&$landing_id): array {
    $callback = $formats['landing']['post_eligibility_callback'];
    $formats['landing']['post_eligibility_callback'] = static fn($post) => (int) $post->ID === $landing_id || $callback($post);
    return $formats;
};
add_filter('iss_editorial_formats', $landing_fixture, 99);
try {
    $assert(iss_editorial_registry_errors() === [], 'Effective registry must be internally consistent');
    $event_links = industriesalon_render_structured_veranstaltung_section([
        'type' => 'material',
        'links' => [['label' => 'Tickets & Infos', 'url' => 'https://example.invalid/tickets?a=1&b=2']],
    ]);
    $assert(str_contains($event_links, 'https://example.invalid/tickets?a=1&#038;b=2'), 'Event material links render without requiring a title or body');
    $assert(str_contains($event_links, 'Tickets &amp; Infos'), 'Event material link labels are escaped');
    $colour = 'iss-ink-preset-' . iss_editorial_text_palette()[0]['slug'];
    $types = ['landing' => 'fliesstext', 'article' => 'fliesstext', 'projekt' => 'kapitel', 'ausstellung' => 'kapitel', 'rueckblick' => 'fliesstext', 'veranstaltung' => 'kapitel', 'place' => 'epoche', 'fuehrung' => 'kapitel', 'publication' => 'longread_chapter'];
    foreach ($types as $slug => $type) {
        $format = iss_editorial_get_format($slug);
        $id = wp_insert_post(['post_type' => $format['post_types'][0], 'post_status' => 'draft', 'post_title' => 'Disposable registry fixture', 'post_content' => '<p>Original block content</p>', 'post_author' => $admin->ID]);
        $assert(is_int($id) && $id > 0, "$slug: create fixture");
        $fixtures[] = $id;
        foreach (iss_content_editorial_sets_service()->get_links_for_context(get_post_type($id), $id) as $link) { $sets[] = (int) $link['set_id']; }
        if ($slug === 'landing') { $landing_id = $id; }
        $assert(iss_editorial_get_format_for_post($id)['slug'] === $slug, "$slug: post resolves to one owner");
        $doc = iss_editorial_get_empty_document($slug);
        $assert($doc['schema_version'] === 3, "$slug: new document uses current version");
        $doc['sections'] = [['type' => $type], ['type' => $type, 'title' => 'Visible section', 'body' => '<p><strong>Reliable</strong> <span class="' . $colour . '">text</span></p>']];
        if ($slug === 'veranstaltung') { $doc['entity_key'] = 'event.general'; update_post_meta($id, '_iss_entity_key', 'event.general'); }
        if ($slug === 'publication') { update_post_meta($id, '_iss_publication_layout', 'longread'); }
        $assert(iss_editorial_save_document($id, $slug, $doc), "$slug: save current document");
        iss_editorial_set_document_enabled($id, $slug, true);
        $key = iss_editorial_get_document_meta_key($slug);
        $canonical = get_post_meta($id, $key, true);
        $draft = iss_editorial_save_draft($id, $slug, $doc, true);
        $assert(!is_wp_error($draft), "$slug: native autosave");
        $query = new WP_Query(); $query->queried_object_id = $id; $query->queried_object = get_post($id);
        $query->is_singular = true; $query->is_single = true; $query->post = get_post($id);
        $GLOBALS['wp_query'] = $query; $GLOBALS['post'] = get_post($id);
        $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => $slug, 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($id, $slug))];
        $assert(!empty(iss_editorial_embedded_preview()['canvas']), "$slug: authenticated canvas enabled");
        $model = iss_editorial_get_read_model($id, $slug);
        $section = $model['sections'][1];
        $assert($section['_editorial_index'] === 1, "$slug: transient source address survives hydration");
        if (in_array($slug, ['landing', 'article'], true)) { $html = industriesalon_editorial_landing_render_document($model); }
        elseif ($slug === 'projekt') { $html = industriesalon_render_editorial_project_section($section, false, 0, 'standard'); }
        elseif (in_array($slug, ['ausstellung', 'rueckblick'], true)) { $html = industriesalon_render_editorial_ausstellung_section($section, false, 0, 'standard'); }
        elseif ($slug === 'fuehrung') { $html = industriesalon_editorial_preview_section(industriesalon_render_editorial_tour_section($section, false, 0, 'standard', 'fixture', $id), $section, ['title' => 'iss-tour-section__title', 'body' => 'iss-tour-section__body']); }
        elseif ($slug === 'veranstaltung') { $html = industriesalon_editorial_preview_section(industriesalon_render_structured_veranstaltung_section($section, 'standard'), $section, ['title' => 'iss-event-structured__title', 'body' => 'iss-event-structured__body']); }
        elseif ($slug === 'place') { $html = industriesalon_render_editorial_place_document($id, $model); }
        else { $html = industriesalon_publications_render_longread_content($id, iss_publications_parse_editorial_longread_payload($id)); }
        $assert(str_contains($html, 'data-iss-preview-section="1"'), "$slug: theme selects exact source after empty section");
        $assert(str_contains($html, 'data-iss-field="body"'), "$slug: body field belongs to the selected section");
        $assert(str_contains($html, '<strong>Reliable</strong>') && str_contains($html, $colour), "$slug: formatting survives theme output");
        if ($slug === 'projekt') {
            $facts = ['type' => 'facts', 'title' => 'Facts', 'body' => '<p>Facts body</p>', '_editorial_index' => 0];
            $spread = industriesalon_render_editorial_project_dossier_spread($section, $facts, false, 0, 'chapter', 'facts');
            $assert(str_contains($spread, 'data-iss-preview-section="0"') && str_contains($spread, 'data-iss-preview-section="1"'), 'Paired project chapters/facts keep distinct source addresses');
        }
        $_GET = [];
        $public = iss_editorial_get_read_model($id, $slug);
        $assert(!isset($public['sections'][1]['_editorial_index']), "$slug: public read model has no editing address");
        $assert(!str_contains(industriesalon_editorial_preview_section('<section>Public</section>', $section), 'data-iss-'), "$slug: public output never enables editing");
        $assert(get_post_meta($id, $key, true) === $canonical && get_post($id)->post_content === '<p>Original block content</p>', "$slug: preview preserves canonical JSON and block content");
        if ($slug !== 'place') {
            $query->in_the_loop = true;
            $GLOBALS['wp_the_query'] = $query;
            $excerpt = get_the_excerpt($id);
            $assert(($slug === 'rueckblick' ? $excerpt === '' : str_contains($excerpt, 'Original block content')) && !str_contains($excerpt, 'Visible section') && !str_contains($excerpt, 'Verwandte Inhalte'), "$slug: automatic excerpt cannot render full composition or related cards");
            $GLOBALS['wp_the_query'] = $original_main_query;
        }
    }
    // Video templates have an explicit composition slot rather than post-content.
    $id = wp_insert_post(['post_type' => 'video', 'post_status' => 'draft', 'post_title' => 'Disposable video registry fixture', 'post_content' => '<p>Legacy transcript body</p>', 'post_author' => $admin->ID]);
    $fixtures[] = $id;
    $assert(iss_editorial_get_format_for_post($id)['slug'] === 'article', 'Videos reuse the article owner');
    update_post_meta($id, 'iss_video_url', 'https://example.invalid/editorial-fixture.mp4');
    $assert(iss_content_model_get_single_video_card($id) !== null, 'Editor can preview a draft video header');
    wp_set_current_user(0);
    $assert(iss_content_model_get_single_video_card($id) === null, 'Anonymous video lookup cannot expose drafts');
    wp_set_current_user($admin->ID);
    $assert(str_contains(iss_content_model_get_video_transcript_html($id), 'Legacy transcript body'), 'Disabled article preserves legacy transcript');
    $doc = iss_editorial_get_empty_document('article');
    $doc['sections'] = [['type' => 'fliesstext', 'body' => '<p>Video article body</p>']];
    $assert(iss_editorial_save_document($id, 'article', $doc), 'Save video composition');
    iss_editorial_set_document_enabled($id, 'article', true);
    $query = new WP_Query(); $query->queried_object_id = $id; $query->queried_object = get_post($id);
    $query->is_singular = true; $query->is_single = true; $query->post = get_post($id);
    $GLOBALS['wp_query'] = $query; $GLOBALS['post'] = get_post($id);
    $template = get_block_template(get_stylesheet() . '//single-video', 'wp_template');
    $assert($template && has_block('industriesalon/editorial-landing', $template->content), 'Effective video template contains the shared composition slot');
    $assert(str_contains(do_blocks('<!-- wp:industriesalon/editorial-landing /-->'), 'Video article body'), 'Video template slot renders article JSON');
    $assert(iss_content_model_get_video_transcript_html($id) === '', 'Article cannot duplicate itself as a fallback transcript');
    update_post_meta($id, ISS_CONTENT_MODEL_VIDEO_TRANSCRIPT_META_KEY, wp_slash(wp_json_encode(['schema_version' => 1, 'segments' => [['timecode' => '00:10', 'text' => 'Owned timed transcript']]])));
    $assert(str_contains(iss_content_model_get_video_transcript_html($id), 'Owned timed transcript'), 'Structured timed transcript retains its native owner');
    $a = ['type' => 'feature', 'treatment' => 'feature.image-overlay', 'anchor' => 'industriesalon']; $b = $a; $b['anchor'] = 'renamed-link';
    $assert(industriesalon_editorial_landing_section_classes($a, 'frontpage') === industriesalon_editorial_landing_section_classes($b, 'frontpage'), 'Renaming a section anchor cannot change presentation');
    WP_CLI::log("PASS: $checks cross-format registry/renderer checks.");
} finally {
    $_GET = $original_get; $GLOBALS['wp_query'] = $original_query; $GLOBALS['wp_the_query'] = $original_main_query; $GLOBALS['post'] = $original_post;
    remove_filter('iss_editorial_formats', $landing_fixture, 99);
    foreach ($fixtures as $id) { wp_delete_post($id, true); }
    foreach (array_unique($sets) as $id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe($id), 'Remove own empty Set');
        $GLOBALS['wpdb']->delete($service->get_audit_table_name(), ['set_id' => $id], ['%d']);
    }
    wp_set_current_user($original_user);
    WP_CLI::log('Registry fixtures removed.');
}
