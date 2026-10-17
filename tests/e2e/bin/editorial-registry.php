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
    $formats['landing']['post_eligibility_callback'] = static function ($post) use (&$landing_id, $callback): bool {
        return (int) $post->ID === $landing_id || $callback($post);
    };
    return $formats;
};
add_filter('iss_editorial_formats', $landing_fixture, 99);
iss_editorial_reset_format_cache();
try {
    $assert(iss_editorial_registry_errors() === [], 'Effective registry must be internally consistent');
    $special_choices = [
        'landing' => ['map_img', 'dynamic_slot', 'atlas_map'], 'article' => [],
        'fuehrung' => ['bildbuehne', 'atlas_map', 'upload_intake'],
        'veranstaltung' => ['intro', 'programm', 'upload_intake'],
        'ausstellung' => ['upload_intake'], 'projekt' => ['upload_intake'], 'rueckblick' => ['intro', 'upload_intake'],
        'publication' => ['intro', 'source', 'publication_rail', 'longread_chapter', 'timeline_item', 'photoalbum'],
        'place' => ['intro', 'epoche', 'gegenwart', 'galerie', 'material', 'upload_intake', 'dynamic_slot'],
    ];
    foreach ($special_choices as $slug => $expected) {
        $actual = array_keys(array_filter(iss_editorial_get_format($slug)['sections'], static fn(array $section): bool => !$section['portable'] && !$section['ui_hidden']));
        sort($actual); sort($expected);
        $assert($actual === $expected, "$slug: special chooser entries retain their owning format");
    }
    $missing_renderer = static function (array $formats): array { unset($formats['projekt']['sections']['feature']['renderer']); return $formats; };
    add_filter('iss_editorial_formats', $missing_renderer, 120);
    iss_editorial_reset_format_cache();
    $assert(in_array('projekt.feature: portable gesture has no renderer', iss_editorial_registry_errors(), true), 'Registry diagnostics detect missing portable renderer coverage');
    remove_filter('iss_editorial_formats', $missing_renderer, 120);
    iss_editorial_reset_format_cache();
    foreach (iss_editorial_get_registered_formats() as $format) {
        foreach ($format['sections'] as $type => $section) {
            $assert($section['label'] === iss_content_model_editorial_gesture_registry()[$type]['label'], 'Shared gesture label: ' . $type);
        }
    }
    $event_links = industriesalon_render_structured_veranstaltung_section([
        'type' => 'material',
        'links' => [['label' => 'Tickets & Infos', 'url' => 'https://example.invalid/tickets?a=1&b=2']],
    ]);
    $assert(str_contains($event_links, 'https://example.invalid/tickets?a=1&#038;b=2'), 'Event material links render without requiring a title or body');
    $assert(str_contains($event_links, 'Tickets &amp; Infos'), 'Event material link labels are escaped');
    $colour = 'iss-ink-preset-' . iss_editorial_text_palette()[0]['slug'];
    $image_id = (int) (get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'numberposts' => 1, 'fields' => 'ids'])[0] ?? 0);
    $assert($image_id > 0, 'An existing image is available for read-only media coverage');
    $media = ['kind' => 'media', 'source' => 'wp-media', 'id' => (string) $image_id];
    $identity_id = wp_insert_post(['post_type' => 'projekt', 'post_status' => 'draft', 'post_title' => 'Disposable project identity', 'post_author' => $admin->ID]);
    $fixtures[] = $identity_id;
    $identity_doc = iss_editorial_sanitize_document(['schema_version' => 3, 'skin' => 'buehne', 'features' => ['identity' => ['palette' => 'neon', 'media_refs' => [$media]]], 'sections' => [
        ['type' => 'kapitel', 'title' => 'Details <script>bad</script>', 'body' => '<p>Background</p>', 'section_treatment' => 'disclosure'],
    ]], 'projekt');
    $assert(!is_wp_error(iss_editorial_validate_document($identity_doc, 'projekt')), 'Project identity and disclosure validate together');
    foreach ([['palette' => ['neon']], ['palette' => 'raw-css'], ['media_refs' => [$media, $media]], ['arbitrary' => 'value']] as $invalid_identity) {
        $invalid = $identity_doc; $invalid['features']['identity'] = $invalid_identity;
        $assert(is_wp_error(iss_editorial_validate_document($invalid, 'projekt')), 'Malformed project identity fails closed');
    }
    $foreign_identity = iss_editorial_sanitize_document($identity_doc, 'article');
    $assert(!isset($foreign_identity['features']['identity']), 'Project identity remains with its owner');
    $assert(iss_editorial_save_document($identity_id, 'projekt', $identity_doc, false, true), 'Identity saves through the document API');
    $saved_identity = iss_editorial_get_document($identity_id, 'projekt');
    $assert($saved_identity['features']['identity'] === $identity_doc['features']['identity'], 'Logo and palette survive storage');
    $revision_id = _wp_put_post_revision($identity_id);
    $assert(!is_wp_error($revision_id) && $revision_id > 0, 'Native revision created for identity');
    $draft_identity = $identity_doc;
    $draft_identity['features']['identity']['palette'] = 'ochre';
    $draft_identity['features']['identity']['media_refs'] = [];
    $identity_draft = iss_editorial_save_draft($identity_id, 'projekt', $draft_identity, true);
    $assert(!is_wp_error($identity_draft), 'Identity recovery snapshot saves');
    $assert(iss_editorial_get_read_model($identity_id, 'projekt', true)['features']['identity'] === $draft_identity['features']['identity'], 'Preview reads draft palette and logo removal');
    $assert(iss_editorial_get_document($identity_id, 'projekt') === $saved_identity, 'Preview preserves saved identity');
    $assert(iss_editorial_save_document($identity_id, 'projekt', $draft_identity, false, true), 'Changed identity saves');
    wp_restore_post_revision($revision_id);
    $assert(iss_editorial_get_document($identity_id, 'projekt')['features']['identity'] === $saved_identity['features']['identity'], 'Native revision restores palette and logo');
    $chapter = $identity_doc['sections'][0]; $chapter['_text_version'] = 3;
    $disclosure_html = industriesalon_render_editorial_landing_chapter_content($chapter);
    $assert(str_contains($disclosure_html, '<details class="iss-landing-disclosure"><summary><h2>') && !str_contains($disclosure_html, '<script>'), 'Public disclosure is closed, native and escaped');
    $chapter['_canvas_edit'] = true;
    $disclosure_html = industriesalon_render_editorial_landing_chapter_content($chapter);
    $assert(str_contains($disclosure_html, ' open><summary>') && substr_count($disclosure_html, 'data-iss-field="title"') === 1, 'Editable disclosure opens with one exact title field');
    $archive_id = (int) (get_posts(['post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE, 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids'])[0] ?? 0);
    $assert($archive_id > 0, 'Published archive object available for read-only reference coverage');
    $archive_ref = ['kind' => 'archive_object', 'source' => 'iss-archive', 'id' => (string) $archive_id, 'label' => 'Owned archive caption'];
    $composition_doc = iss_editorial_sanitize_document(['schema_version' => 3, 'skin' => 'buehne', 'sections' => [
        ['type' => 'feature', 'treatment' => 'feature.opening', 'title' => 'A composed opening', 'media_refs' => [$media]],
        ['type' => 'text_bild_reihe', 'treatment' => 'text-bild-reihe.agenda', 'title' => 'Agenda', 'surface' => 'dark', 'items' => [
            ['label' => 'First entry', 'text' => '<em>Visible without JavaScript</em>', 'eyebrow' => '10. September', 'group' => 'Art & craft', 'detail' => "Additional detail\nSecond line"],
            ['label' => 'Second entry', 'text' => 'Culture &amp; craft &lt;3', 'group' => 'City'],
        ]],
    ]], 'projekt');
    $assert(!is_wp_error(iss_editorial_validate_document($composition_doc, 'projekt')), 'Project stage accepts a composed opening and agenda');
    $assert(industriesalon_project_has_opening($composition_doc), 'Valid composed opening replaces template introduction');
    $opening_html = industriesalon_render_project_opening($composition_doc, $identity_id);
    $assert(substr_count($opening_html, '<h1') === 1 && str_contains($opening_html, 'A composed opening'), 'Opening has exactly one semantic main heading');
    foreach (['skin', 'position', 'title', 'image'] as $fault) {
        $invalid = $composition_doc;
        if ($fault === 'skin') { $invalid['skin'] = 'dossier'; }
        elseif ($fault === 'position') { $invalid['sections'] = array_reverse($invalid['sections']); }
        elseif ($fault === 'title') { $invalid['sections'][0]['title'] = ''; }
        else { $invalid['sections'][0]['media_refs'] = []; }
        $assert(is_wp_error(iss_editorial_validate_document($invalid, 'projekt')), "Invalid project opening rejected: $fault");
        $assert(!industriesalon_project_has_opening($invalid), "Invalid project opening preserves template fallback: $fault");
    }
    $assert(iss_editorial_save_document($identity_id, 'projekt', $composition_doc, false, true), 'Composition saves through shared API');
    $assert(iss_editorial_get_document($identity_id, 'projekt') === $composition_doc, 'Surface and optional agenda fields survive canonical roundtrip');
    ob_start(); iss_editorial_render_main_canvas(get_post($identity_id)); $canvas_html = ob_get_clean();
    $canvas_tags = new WP_HTML_Tag_Processor($canvas_html);
    $canvas_documents = 0;
    while ($canvas_tags->next_tag()) {
        if (!$canvas_tags->has_class('iss-editorial-document-field')) { continue; }
        $attribute = 'value';
        $canvas_document = json_decode($canvas_tags->get_attribute($attribute), true);
        $assert($canvas_document['sections'][1]['items'][1]['text'] === $composition_doc['sections'][1]['items'][1]['text'], 'Editor HTML transport preserves rich-text entities');
        $assert(!is_wp_error(iss_editorial_validate_document($canvas_document, 'projekt')), 'Browser-decoded editor document validates without a recovery reload');
        ++$canvas_documents;
    }
    $assert($canvas_documents === 1 && !str_contains($canvas_html, 'data-document='), 'One editor document transport roundtrips without a duplicate canvas payload');
    $agenda = $composition_doc['sections'][1]; $agenda['_text_version'] = 3;
    $agenda_html = industriesalon_render_editorial_landing_text_image_row($agenda, 1, 'buehne');
    $assert(substr_count($agenda_html, '<article ') === 2 && str_contains($agenda_html, '<em>Visible without JavaScript</em>'), 'Unfiltered HTML contains every agenda entry');
    $assert(str_contains($agenda_html, 'data-agenda-group="Art &amp; craft"') && str_contains($agenda_html, 'data-agenda-filter="City"'), 'Agenda group values are escaped and filterable');
    $assert(str_contains($agenda_html, '<details class="iss-landing-agenda__details"><summary>') && str_contains($agenda_html, 'Additional detail'), 'Agenda details use native disclosure and retain copy');
    $assert(str_contains($agenda_html, 'iss-landing-section--surface-dark') && str_contains($agenda_html, 'iss-landing-agenda__filters" hidden'), 'Surface class and progressive-enhancement filter contract render');
    foreach ([['surface', 'free-css'], ['eyebrow', ['bad']], ['group', '<b>bad</b>'], ['detail', ['bad']]] as [$key, $value]) {
        $invalid = $composition_doc;
        if ($key === 'surface') { $invalid['sections'][1][$key] = $value; }
        else { $invalid['sections'][1]['items'][0][$key] = $value; }
        $assert(is_wp_error(iss_editorial_validate_document($invalid, 'projekt')), "Malformed composition field rejected: $key");
    }
    $old_item = iss_editorial_sanitize_text_image_item(['label' => 'Legacy', 'text' => 'Preserved', 'media_refs' => []]);
    $assert(array_keys($old_item) === ['label', 'text', 'media_refs'], 'Legacy rows do not acquire optional fields');
    $assert(!isset($identity_doc['sections'][0]['surface']), 'Legacy sections do not acquire a surface field');
    $composition_nav = industriesalon_editorial_project_nav_items($composition_doc['sections'], [], true);
    $assert(count($composition_nav) === 1 && $composition_nav[0]['label'] === 'Agenda', 'Project navigation omits the opening');
    $composition_query = new WP_Query(['post_type' => 'projekt', 'p' => $identity_id, 'post_status' => 'draft']);
    $GLOBALS['wp_query'] = $composition_query; $GLOBALS['wp_the_query'] = $composition_query;
    $composition_query->the_post();
    $composition_html = industriesalon_render_editorial_project_content('Legacy content');
    $assert(!str_contains($composition_html, 'A composed opening') && str_contains($composition_html, 'First entry'), 'Project body does not duplicate its opening');
    wp_reset_postdata(); $GLOBALS['wp_query'] = $original_query; $GLOBALS['wp_the_query'] = $original_main_query; $GLOBALS['post'] = $original_post;
    foreach (['gateway', 'feature'] as $fit_type) {
        foreach (['landing', 'article', 'projekt', 'veranstaltung', 'fuehrung', 'publication', 'ausstellung', 'rueckblick'] as $fit_format) {
            $fit_section = ['type' => $fit_type, 'title' => 'Image fit', 'image_fit' => 'contain'];
            if ($fit_type === 'feature') { $fit_section['media_refs'] = [$media]; }
            else { $fit_section['items'] = [['label' => 'Partner', 'url' => 'https://example.invalid', 'media_refs' => [$media]]]; }
            $fit_doc = iss_editorial_sanitize_document(['schema_version' => 3, 'sections' => [$fit_section]], $fit_format);
            $assert(!is_wp_error(iss_editorial_validate_document($fit_doc, $fit_format)) && $fit_doc['sections'][0]['image_fit'] === 'contain', "$fit_format.$fit_type: full-image choice is portable");
            $invalid_fit = $fit_doc; $invalid_fit['sections'][0]['image_fit'] = 'arbitrary-css';
            $assert(is_wp_error(iss_editorial_validate_document($invalid_fit, $fit_format)), "$fit_format.$fit_type: invalid fit is rejected");
            unset($fit_section['image_fit']);
            $legacy_fit = iss_editorial_sanitize_document(['schema_version' => 3, 'sections' => [$fit_section]], $fit_format);
            $assert(!isset($legacy_fit['sections'][0]['image_fit']), "$fit_format.$fit_type: old document shape is preserved");
        }
    }
    $whole_opening = $composition_doc;
    $whole_opening['sections'][0]['image_fit'] = 'contain';
    $assert(str_contains(industriesalon_render_project_opening($whole_opening, $identity_id), 'iss-editorial-image-fit--contain'), 'Project opening exposes full-image rendering choice');
    $assert(!str_contains(industriesalon_render_project_opening($composition_doc, $identity_id), 'iss-editorial-image-fit--contain'), 'Existing project opening keeps its photo crop');
    $whole_feature = $whole_opening['sections'][0]; $whole_feature['_text_version'] = 3;
    foreach (['feature.media-panel', 'feature.media-text', 'feature.image-overlay', 'feature.origin-story'] as $feature_treatment) {
        $whole_feature['treatment'] = $feature_treatment;
        $whole_html = industriesalon_render_editorial_landing_section($whole_feature, 0, 'buehne');
        $assert(str_contains($whole_html, 'iss-editorial-image-fit--contain'), "$feature_treatment: image-fit marker survives rendering");
        if (in_array($feature_treatment, ['feature.media-text', 'feature.image-overlay'], true)) {
            $assert(str_contains($whole_html, 'iss-media-card--contain') && !str_contains($whole_html, 'iss-media-card--cover'), "$feature_treatment: reuses the existing contain media primitive");
        }
    }
    $assert(iss_editorial_save_document($identity_id, 'projekt', $identity_doc, false, true), 'Restore disposable identity fixture');
    $private_id = wp_insert_post(['post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE, 'post_status' => 'draft', 'post_title' => 'Private reference must stay private', 'post_author' => $admin->ID]);
    $fixtures[] = $private_id;
    $private_ref = ['kind' => 'archive_object', 'source' => 'iss-archive', 'id' => (string) $private_id, 'label' => 'Private archive caption'];
    $portable_types = ['statement', 'gateway', 'text_bild_reihe', 'feature'];
    $render_portable = static function (array $section, string $slug, string $skin, int $version = 3) use ($media): string {
        $section['_text_version'] = $version;
        foreach (['media_refs', 'object_refs'] as $field) {
            if (!empty($section[$field])) { $section[$field . '_resolved'] = array_map(static fn(array $ref): array => ['reference' => $ref, 'resolved' => iss_editorial_resolve_reference($ref)], $section[$field]); }
        }
        return match ($slug) {
            'landing', 'article' => industriesalon_render_editorial_landing_section($section, 0, $skin),
            'projekt' => industriesalon_render_editorial_project_section($section, false, 0, $skin),
            'ausstellung', 'rueckblick' => industriesalon_render_editorial_ausstellung_section($section, false, 0, $skin),
            'fuehrung' => industriesalon_render_editorial_tour_section($section, false, 0, $skin, 'portable-anchor', get_the_ID()),
            'veranstaltung' => industriesalon_render_structured_veranstaltung_section($section, $skin),
            'publication' => industriesalon_render_editorial_portable_section($section, 0, $skin, 'publication') ?? '',
        };
    };

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
        if ($slug === 'place') {
            $doc['sections'][0] = ['type' => 'intro'];
            $doc['sections'][1] += ['era_key' => 'nach-1990', 'function_key' => 'culture'];
        }
        if ($slug === 'veranstaltung') { $doc['entity_key'] = 'event.general'; update_post_meta($id, '_iss_entity_key', 'event.general'); }
        if ($slug === 'publication') { update_post_meta($id, '_iss_publication_layout', 'longread'); }
        $assert(iss_editorial_save_document($id, $slug, $doc), "$slug: save current document");
        iss_editorial_set_document_enabled($id, $slug, true);
        $key = iss_editorial_get_document_meta_key($slug);
        $canonical = get_post_meta($id, $key, true);
        $draft = iss_editorial_save_draft($id, $slug, $doc, true);
        $assert(!is_wp_error($draft), "$slug: native autosave");
        $query = new WP_Query(); $query->posts = [get_post($id)]; $query->queried_object_id = $id; $query->queried_object = get_post($id);
        $query->is_singular = true; $query->is_single = true; $query->post = get_post($id);
        $GLOBALS['wp_query'] = $query; $GLOBALS['post'] = get_post($id);
        $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => $slug, 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($id, $slug))];
        $assert(!empty(iss_editorial_embedded_preview()['canvas']), "$slug: authenticated canvas enabled");
        $model = iss_editorial_get_read_model($id, $slug);
        $section = $model['sections'][1];
        $section['_text_version'] = $model['schema_version'];
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
        if ($slug !== 'place') {
            $portable_doc = iss_editorial_get_empty_document($slug);
            if ($slug === 'veranstaltung') { $portable_doc['entity_key'] = 'event.general'; }
            foreach ($portable_types as $portable_type) {
                $definition = $format['sections'][$portable_type];
                $contract = iss_editorial_get_format('article')['sections'][$portable_type];
                $assert($definition['portable'] && $definition['renderer'] === 'landing', "$slug.$portable_type: shared rendering capability");
                foreach (['supports', 'rich_text', 'default_treatment', 'treatment_aliases'] as $field) {
                    $assert($definition[$field] === $contract[$field], "$slug.$portable_type: identical $field contract");
                }
                $sample = ['type' => $portable_type, 'kicker' => 'Portable kicker', 'title' => 'Portable ' . $portable_type,
                    'body' => '<p><strong>Portable body</strong> <span class="' . $colour . '">colour survives</span></p>',
                    'links' => [['label' => 'Portable first link', 'url' => 'https://example.invalid/first'], ['label' => 'Portable second link', 'url' => 'https://example.invalid/second']]];
                if ($portable_type === 'statement') { $sample['anchor'] = 'guiding-question'; }
                if (in_array($portable_type, ['gateway', 'text_bild_reihe'], true)) {
                    $sample['items'] = [['label' => 'Portable item', 'text' => '<em>Portable item text</em>', 'media_refs' => [$media]]];
                    if ($portable_type === 'gateway') { $sample['items'][0]['url'] = 'https://example.invalid/item'; }
                }
                if ($portable_type === 'feature') {
                    $sample['lead'] = '<p>Portable lead</p>';
                    $sample['facts'] = [['value' => 'Portable fact', 'label' => 'Portable fact label']];
                    $sample['media_refs'] = [$media];
                }
                foreach ($format['skins'] as $skin => $skin_definition) {
                    foreach ($contract['treatments'] as $treatment) {
                        $sample['treatment'] = $treatment['slug'];
                        $candidate = $portable_doc; $candidate['skin'] = $skin; $candidate['sections'] = [$sample];
                        $validated = iss_editorial_validate_document($candidate, $slug);
                        $label = "$slug.$portable_type/$skin/{$treatment['slug']}";
                        $assert(!is_wp_error($validated), "$label: accepts portable contract");
                        $html = $render_portable($validated['sections'][0], $slug, $skin);
                        if ($portable_type === 'statement') {
                            $assert(str_contains($html, 'id="' . ($slug === 'fuehrung' ? 'portable-anchor' : 'guiding-question') . '"'), "$label: statement retains navigation anchor");
                        }
                        foreach (['Portable ' . $portable_type, '<strong>Portable body</strong>', $colour, 'Portable first link', 'Portable second link'] as $value) {
                            $assert(str_contains($html, $value), "$label: renders $value");
                        }
                        if (isset($sample['items'])) {
                            $assert(str_contains($html, '<em>Portable item text</em>') && str_contains($html, 'Portable item'), "$label: item text and formatting survive");
                        }
                        if ($portable_type === 'feature') {
                            $assert(str_contains($html, 'Portable lead') && str_contains($html, 'Portable fact label'), "$label: lead and facts survive");
                        }
                        if ($portable_type !== 'statement') { $assert(str_contains($html, '<img '), "$label: image survives"); }
                        $assert(str_contains($html, '--skin-' . $skin), "$label: requested skin applied");
                        $assert(!str_contains($html, 'data-iss-preview-section') && !str_contains($html, 'data-iss-field='), "$label: public rendering cannot enable editing");
                    }
                }
                if ($portable_type === 'feature') {
                    foreach ([1, 2] as $version) {
                        foreach ($contract['treatments'] as $treatment) {
                            $candidate = $portable_doc; $candidate['schema_version'] = $version;
                            $sample['treatment'] = $treatment['slug']; $candidate['sections'] = [$sample];
                            // Named palette classes belong to v3; older documents retain their own text contract.
                            $candidate['sections'][0]['body'] = '<p><strong>Portable body</strong></p>';
                            $validated = iss_editorial_validate_document($candidate, $slug);
                            $assert(!is_wp_error($validated), "$slug/{$treatment['slug']}: portable feature accepts retained v$version documents: " . (is_wp_error($validated) ? $validated->get_error_message() : ''));
                            $html = $render_portable($validated['sections'][0], $slug, $format['default_skin'], $version);
                            $assert(str_contains($html, 'Portable lead') && str_contains($html, 'Portable body'), "$slug/v$version/{$treatment['slug']}: retained document shows lead and body");
                        }
                    }
                }
                $sample['treatment'] = $definition['default_treatment'];
                $portable_doc['sections'][] = $sample;
            }
            $assert(iss_editorial_save_document($id, $slug, $portable_doc), "$slug: save shared composition");
            if ($slug === 'veranstaltung') {
                foreach (iss_content_model_veranstaltung_entities() as $entity_key => $entity) {
                    $candidate = $portable_doc; $candidate['entity_key'] = $entity_key;
                    $assert(!is_wp_error(iss_editorial_validate_document($candidate, $slug)), "$entity_key: shared composition is independent of event structure");
                    $allowed = array_keys(iss_content_model_veranstaltung_content_gestures_for_entity($entity_key));
                    $assert(array_diff($portable_types, $allowed) === [], "$entity_key: editor chooser exposes the same shared gestures");
                    $programme = $portable_doc; $programme['entity_key'] = $entity_key;
                    $programme['sections'] = [['type' => 'programm', 'body' => 'Festival schedule']];
                    $assert(in_array('programm', $allowed, true) === ($entity_key === 'event.festival'), "$entity_key: programme insertion belongs only to festivals");
                    $assert(!is_wp_error(iss_editorial_validate_document($programme, $slug)) === ($entity_key === 'event.festival'), "$entity_key: programme save agrees with chooser availability");
                    $candidate['sections'] = [['type' => 'leitfrage', 'title' => 'Retained question']];
                    $assert(!is_wp_error(iss_editorial_validate_document($candidate, $slug)), "$entity_key: retained questions share statement availability");
                    foreach (array_diff(array_keys(iss_content_model_veranstaltung_content_gestures()), $entity['allowed_gestures'], ['galerie', 'zitat', 'leitfrage']) as $restricted_type) {
                        $candidate['sections'] = [['type' => $restricted_type, 'body' => 'Restricted domain section']];
                        $assert(is_wp_error(iss_editorial_validate_document($candidate, $slug)), "$entity_key: native domain restrictions retained");
                    }
                }
            }
            $reloaded = iss_editorial_get_document($id, $slug);
            $assert($reloaded === iss_editorial_validate_document($portable_doc, $slug), "$slug: save and reload preserve all shared fields");
            $query->in_the_loop = true; $GLOBALS['wp_the_query'] = $query;
            $html = apply_filters('the_content', '<p>Original block content</p>');
            foreach ($portable_types as $portable_type) {
                $assert(substr_count($html, 'Portable ' . $portable_type) === 1, "$slug.$portable_type: native content route renders section exactly once");
            }
            $assert(str_contains($html, 'Portable lead'), "$slug: document version reaches shared renderer");
            if ($slug === 'veranstaltung') {
                $assert(str_contains(do_blocks('<!-- wp:post-content /-->'), 'iss-event-description--composition'), 'Event native content block uses the full composition column');
            }
            wp_dequeue_style('industriesalon-editorial-landing'); wp_dequeue_style('industriesalon-editorial-skins');
            industriesalon_enqueue_assets();
            $assert(wp_style_is('industriesalon-editorial-landing', 'enqueued') && wp_style_is('industriesalon-editorial-skins', 'enqueued'), "$slug: shared render and skin styles enqueued");
            $GLOBALS['wp_the_query'] = $original_main_query;
            $draft = iss_editorial_save_draft($id, $slug, $portable_doc, true);
            $assert(!is_wp_error($draft), "$slug: shared composition autosave");
            $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => $slug, 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($id, $slug))];
            $model = iss_editorial_get_read_model($id, $slug);
            foreach ($model['sections'] as $index => $shared_section) {
                $html = in_array($slug, ['landing', 'article'], true) ? industriesalon_editorial_landing_render_document($model, $index, 1) : $render_portable($shared_section, $slug, $model['skin']);
                $assert(substr_count($html, 'data-iss-preview-section="' . $index . '"') === 1, "$slug: shared preview has one exact section address");
                $assert(str_contains($html, 'data-iss-field="body"'), "$slug: shared body retains canvas editing");
            }
            $_GET = [];
            $bad = $portable_doc; $bad['sections'][3]['treatment'] = 'feature.opening';
            if ($slug !== 'landing') { $assert(is_wp_error(iss_editorial_validate_document($bad, $slug)), "$slug: front-page opening cannot leak into a domain format"); }
            $common_doc = iss_editorial_get_empty_document($slug);
            if ($slug === 'veranstaltung') { $common_doc['entity_key'] = 'event.general'; }
            foreach (['galerie', 'fliesstext', 'zitat', 'schluss', 'kapitel', 'facts', 'material', 'objektfokus'] as $common_type) {
                $definition = $format['sections'][$common_type];
                $contract = iss_editorial_get_format('article')['sections'][$common_type];
                foreach (['supports', 'rich_text', 'treatments', 'default_treatment', 'legacy_types'] as $field) {
                    $actual = $definition[$field];
                    if ($field === 'supports' && $slug === 'veranstaltung' && $common_type === 'kapitel') { $actual = array_values(array_diff($actual, ['dynamic_refs'])); }
                    if ($field === 'supports' && $slug === 'veranstaltung' && $common_type === 'material') { $actual = array_values(array_diff($actual, ['object_refs', 'dynamic_refs', 'items'])); }
                    $assert($actual === $contract[$field], "$slug.$common_type: identical $field contract");
                }
                $sample = ['type' => $common_type, 'anchor' => 'shared-' . $common_type, 'kicker' => 'Common kicker', 'title' => 'Common ' . $common_type,
                    'body' => '<p><em>Common formatted body</em></p>', 'media_refs' => [array_merge($media, ['label' => 'Owned image caption'])], 'object_refs' => [$archive_ref, $private_ref]];
                if (in_array($common_type, ['schluss', 'facts'], true)) { unset($sample['media_refs'], $sample['object_refs']); }
                if (in_array($common_type, ['kapitel', 'material'], true)) { unset($sample['object_refs']); }
                if ($common_type === 'objektfokus') { unset($sample['media_refs']); }
                $choices = match ($common_type) {
                    'galerie' => array_map(static fn(string $layout): array => ['gallery_layout' => $layout], ['grid', 'sequence', 'wall', 'viewport']),
                    'fliesstext' => array_map(static fn(array $treatment): array => ['treatment' => $treatment['slug']], $contract['treatments']),
                    'schluss', 'facts', 'material', 'objektfokus' => [[]],
                    'kapitel' => [['media_layout' => 'inline', 'section_treatment' => 'standard'], ['media_layout' => 'aside-right', 'section_treatment' => 'standard'], ['media_layout' => 'inline', 'section_treatment' => 'aside'], ['media_layout' => 'aside-right', 'section_treatment' => 'aside']],
                    default => [['quote_treatment' => 'pull'], ['quote_treatment' => 'source', 'orientation' => 'media-left'], ['quote_treatment' => 'source', 'orientation' => 'media-right']],
                };
                if (in_array($common_type, ['fliesstext', 'schluss', 'kapitel', 'material'], true)) { $sample['links'] = [['label' => 'Common link', 'url' => 'https://example.invalid/common']]; }
                if ($common_type === 'facts') { $sample['facts'] = [['value' => '0', 'label' => 'Common zero value'], ['value' => '12 Tage', 'label' => 'Common fact description']]; }
                if ($common_type === 'zitat') { $sample['quote'] = '<p><strong>Quoted words</strong></p>'; $sample['attribution'] = 'Author & Archive'; }
                foreach ($format['skins'] as $skin => $skin_definition) {
                    foreach ($choices as $choice) {
                        $candidate = $common_doc; $candidate['skin'] = $skin; $candidate['sections'] = [array_merge($sample, $choice)];
                        $validated = iss_editorial_validate_document($candidate, $slug);
                        $label = "$slug.$common_type/$skin/" . implode('/', $choice);
                        $assert(!is_wp_error($validated), "$label: shared fields validate");
                        $html = $render_portable($validated['sections'][0], $slug, $skin);
                        $expected = ['Common ' . $common_type, '<em>Common formatted body</em>'];
                        if (!in_array($common_type, ['schluss', 'facts', 'material', 'objektfokus'], true)) { $expected = array_merge($expected, ['Owned image caption', '<img ']); }
                        if (!in_array($common_type, ['schluss', 'kapitel', 'facts', 'material'], true)) { $expected = array_merge($expected, ['Owned archive caption', esc_url(get_permalink($archive_id))]); }
                        if ($common_type === 'material') { $expected = array_merge($expected, ['Owned image caption', 'download=']); }
                        if ($common_type === 'facts') { $expected = array_merge($expected, ['>0<', 'Common zero value', '12 Tage', 'Common fact description']); }
                        foreach ($expected as $value) {
                            $assert(str_contains($html, $value), "$label: preserves $value");
                        }
                        $assert(!str_contains($html, 'Private archive caption') && !str_contains($html, 'Private reference must stay private'), "$label: archive owner rejects unpublished object");
                        if (in_array($common_type, ['fliesstext', 'schluss', 'kapitel', 'material'], true)) { $assert(str_contains($html, 'Common link'), "$label: links survive every text treatment"); }
                        if ($common_type === 'kapitel') {
                            $assert(str_contains($html, 'iss-landing-chapter--media-aside') === ($choice['media_layout'] === 'aside-right'), "$label: chapter media placement retained");
                            $assert(str_contains($html, 'iss-landing-chapter--aside') === ($choice['section_treatment'] === 'aside'), "$label: chapter aside retained");
                        }
                        if ($common_type === 'galerie') { $assert(str_contains($html, 'data-gallery-layout="' . $choice['gallery_layout'] . '"'), "$label: renderer follows chosen gallery layout"); }
                        if ($common_type === 'zitat') {
                            $assert(str_contains($html, '<strong>Quoted words</strong>') && str_contains($html, 'Author &amp; Archive'), "$label: quote formatting and attribution survive");
                            $assert(str_contains($html, 'iss-landing-quote--source') === ($choice['quote_treatment'] === 'source'), "$label: source layout is explicit");
                            $assert(str_contains($html, 'iss-landing-quote--media-right') === (($choice['orientation'] ?? '') === 'media-right'), "$label: orientation is explicit");
                        }
                    }
                }
                $common_doc['sections'][] = $sample;
            }
            $assert(iss_editorial_save_document($id, $slug, $common_doc), "$slug: save common composition");
            $assert(iss_editorial_get_document($id, $slug) === iss_editorial_validate_document($common_doc, $slug), "$slug: common fields round-trip without loss");
            $GLOBALS['wp_the_query'] = $query;
            $html = apply_filters('the_content', '<p>Original block content</p>');
            foreach (['galerie', 'fliesstext', 'zitat', 'schluss', 'kapitel', 'facts', 'material', 'objektfokus'] as $common_type) { $assert(substr_count($html, 'Common ' . $common_type) === 1, "$slug.$common_type: native content route renders once"); }
            $draft = iss_editorial_save_draft($id, $slug, $common_doc, true);
            $assert(!is_wp_error($draft), "$slug: common snapshot saves");
            $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => $slug, 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($id, $slug))];
            $html = apply_filters('the_content', '<p>Original block content</p>');
            foreach (array_keys($common_doc['sections']) as $index) { $assert(substr_count($html, 'data-iss-preview-section="' . $index . '"') === 1, "$slug: common snapshot source address $index"); }
            $assert(substr_count($html, 'data-iss-field="body"') === count($common_doc['sections']), "$slug: every common body is editable in the preview");
            $_GET = []; $GLOBALS['wp_the_query'] = $original_main_query;
            foreach ([1, 2, 3] as $version) {
                $object_doc = iss_editorial_get_empty_document($slug); $object_doc['schema_version'] = $version;
                if ($slug === 'veranstaltung') { $object_doc['entity_key'] = 'event.general'; }
                $saved_ref = array_merge($archive_ref, ['label' => 'Saved label & source', 'set_id' => '13', 'set_title' => 'Saved Set', 'member_id' => '42', 'member_caption' => 'Saved caption & context']);
                $object_doc['sections'] = [['type' => 'objektfokus', 'anchor' => 'sources', 'object_refs' => [$saved_ref, $private_ref]]];
                $valid = iss_editorial_validate_document($object_doc, $slug);
                $assert(!is_wp_error($valid) && iss_editorial_save_document($id, $slug, $object_doc) && iss_editorial_get_document($id, $slug) === $valid, "$slug/v$version: title-free archive references round-trip");
                $html = $render_portable($valid['sections'][0], $slug, $format['default_skin'], $version);
                $expected_card = iss_wf_import_render_featured_archive_object($archive_id, ['title' => $saved_ref['label'], 'excerpt' => $saved_ref['member_caption']]);
                $assert($expected_card !== '' && str_contains($html, $expected_card), "$slug/v$version: archive owner card keeps source metadata and saved label/caption");
                $assert(!str_contains($html, 'Private archive caption'), "$slug/v$version: private source remains hidden");
                $assert(str_contains($html, 'id="' . ($slug === 'fuehrung' ? 'portable-anchor' : 'sources') . '"'), "$slug/v$version: object anchor survives");
                $assert($render_portable(['type' => 'objektfokus'], $slug, $format['default_skin'], $version) === '', "$slug/v$version: empty archive section omitted");
                $html = $render_portable(['type' => 'objektfokus', 'body' => '<p><strong>Source introduction</strong></p>'], $slug, $format['default_skin'], $version);
                $assert(str_contains($html, 'Source introduction'), "$slug/v$version: source introduction works without cards");
            }
            $bare_ref = array_diff_key($archive_ref, ['label' => true]);
            $html = $render_portable(['type' => 'objektfokus', 'object_refs' => [$bare_ref]], $slug, $format['default_skin']);
            $assert(str_contains($html, iss_wf_import_render_featured_archive_object($archive_id)), "$slug: missing overrides retain live archive title and excerpt");
            foreach ([$private_ref, array_merge($archive_ref, ['id' => (string) PHP_INT_MAX]), array_merge($archive_ref, ['id' => (string) $image_id]), $media] as $hidden_ref) {
                $assert($render_portable(['type' => 'objektfokus', 'object_refs' => [$hidden_ref]], $slug, $format['default_skin']) === '', "$slug: unpublished, missing and non-archive objects cannot leak");
            }
            $file_id = (int) (get_posts(['post_type' => 'attachment', 'post_mime_type' => 'application/pdf', 'numberposts' => 1, 'fields' => 'ids'])[0] ?? 0);
            $assert($file_id > 0, 'Existing document available for read-only download coverage');
            $file_ref = ['kind' => 'media', 'source' => 'wp-media', 'id' => (string) $file_id, 'label' => 'File & source label'];
            foreach ([1, 2, 3] as $version) {
                $material_doc = iss_editorial_get_empty_document($slug); $material_doc['schema_version'] = $version;
                if ($slug === 'veranstaltung') { $material_doc['entity_key'] = 'event.general'; }
                foreach ([['media_refs' => [$file_ref, $media]], ['links' => [['label' => 'Reference & context', 'url' => 'https://example.invalid/material?a=1&b=2']]], ['body' => '<p><strong>Material prose</strong></p>']] as $fields) {
                    $material_doc['sections'] = [array_merge(['type' => 'material', 'anchor' => 'materials'], $fields)];
                    $valid = iss_editorial_validate_document($material_doc, $slug);
                    $assert(!is_wp_error($valid) && iss_editorial_save_document($id, $slug, $material_doc) && iss_editorial_get_document($id, $slug) === $valid, "$slug/v$version: standalone material fields round-trip");
                    $html = $render_portable($valid['sections'][0], $slug, $format['default_skin'], $version);
                    $expected = isset($fields['media_refs']) ? ['File &amp; source label', esc_url(wp_get_attachment_url($file_id)), 'application/pdf', 'download=', esc_url(wp_get_attachment_url($image_id))] : (isset($fields['links']) ? ['Reference &amp; context', 'https://example.invalid/material?a=1&#038;b=2'] : ['<strong>Material prose</strong>']);
                    foreach ($expected as $value) { $assert(str_contains($html, $value), "$slug/v$version: title-free material retains $value"); }
                    $assert(str_contains($html, 'id="' . ($slug === 'fuehrung' ? 'portable-anchor' : 'materials') . '"'), "$slug: material anchor survives");
                }
                $assert($render_portable(['type' => 'material'], $slug, $format['default_skin'], $version) === '', "$slug: empty material omits wrapper");
            }
            $missing = ['kind' => 'media', 'source' => 'wp-media', 'id' => (string) PHP_INT_MAX, 'label' => 'Missing file'];
            $assert($render_portable(['type' => 'material', 'media_refs' => [$missing]], $slug, $format['default_skin']) === '', "$slug: unresolved download cannot render a broken link");
            $live_ref = ['kind' => 'control_field', 'source' => 'industriesalon-steuerung', 'key' => 'address.full', 'label' => 'Live source address', 'tagName' => 'p', 'linkMode' => 'none'];
            $material_doc['sections'] = [['type' => 'material', 'items' => ['Saved <em>literal</em> note'], 'dynamic_refs' => [$live_ref], 'object_refs' => [$archive_ref, $private_ref]]];
            if ($slug === 'veranstaltung') {
                $invalid_note = iss_editorial_validate_document($material_doc, $slug);
                $assert(is_wp_error($invalid_note) && $invalid_note->get_error_code() === 'editorial_lossy_normalization' && $invalid_note->get_error_data()['path'] === 'sections[0].items[0]', 'Event text items reject formatting loss before saving');
                $material_doc['sections'][0]['items'] = ['Saved literal note'];
                $assert(iss_editorial_save_document($id, $slug, $material_doc), 'Event material extensions save');
                $valid = iss_editorial_get_document($id, $slug);
                $assert($valid['sections'][0]['dynamic_refs'] === [$live_ref] && count($valid['sections'][0]['object_refs']) === 2, 'Event source data remains owner-managed');
                $html = $render_portable($valid['sections'][0], $slug, $format['default_skin']);
                foreach (['Saved', 'literal', 'Live source address', 'Owned archive caption', esc_url(get_permalink($archive_id)), esc_html(Industriesalon_Steuerung::instance()->get_field_value('address.full', ''))] as $value) { $assert(str_contains($html, $value), 'Event material retains ' . $value); }
                $assert(!str_contains($html, 'Private archive caption') && !str_contains($html, '<em>literal</em>'), 'Event notes remain plain text and private objects stay hidden');
                $material_doc['sections'] = [['type' => 'material', 'links' => [['label' => 'Tickets buchen', 'url' => 'https://example.invalid/ticket']], 'items' => ['Keep this note when booking closes']]];
                $assert(iss_editorial_save_document($id, $slug, $material_doc), 'Event ticket/note fixture saves');
                update_post_meta($id, 'iss_event_status', 'cancelled');
                $GLOBALS['wp_the_query'] = $query;
                $html = apply_filters('the_content', 'Original content');
                $assert(!str_contains($html, 'https://example.invalid/ticket') && str_contains($html, 'Keep this note when booking closes'), 'Closed events suppress booking links while retaining material notes');
                delete_post_meta($id, 'iss_event_status'); $GLOBALS['wp_the_query'] = $original_main_query;
            } else {
                foreach (['items', 'dynamic_refs', 'object_refs'] as $field) {
                    $candidate = $material_doc; $candidate['sections'] = [['type' => 'material', $field => $material_doc['sections'][0][$field]]];
                    $assert(is_wp_error(iss_editorial_validate_document($candidate, $slug)), "$slug: $field stays an event material extension");
                }
            }
            // Saved questions remain editable aliases; new questions use statement treatments.
            if (in_array($slug, ['veranstaltung', 'ausstellung', 'fuehrung'], true)) {
                $definition = $format['sections']['leitfrage'];
                $assert($definition['ui_hidden'] && $definition['family'] === 'statement' && $definition['renderer'] === 'landing', "$slug: one question chooser path and shared renderer");
                foreach (['label', 'supports', 'rich_text', 'treatments'] as $field) {
                    $assert($definition[$field] === $format['sections']['statement'][$field], "$slug: retained question shares statement $field");
                }
                foreach ([1, 2, 3] as $version) {
                    $question = iss_editorial_get_empty_document($slug); $question['schema_version'] = $version;
                    if ($slug === 'veranstaltung') { $question['entity_key'] = 'event.general'; }
                    $question['sections'] = [['type' => 'leitfrage', 'anchor' => 'saved-question', 'kicker' => 'Question context', 'title' => 'Saved question?', 'body' => '<p><strong>Question body</strong></p>', 'links' => [['label' => 'Question link', 'url' => 'https://example.invalid/question']]]];
                    $validated = iss_editorial_validate_document($question, $slug);
                    $assert(!is_wp_error($validated) && $validated['sections'][0]['type'] === 'leitfrage' && $validated['sections'][0]['treatment'] === 'statement.leitfrage', "$slug/v$version: retain identifier and resolve question default");
                    foreach ($format['skins'] as $skin => $choice) {
                        foreach (['', 'statement.lead', 'statement.leitfrage', 'statement.callout'] as $treatment) {
                            $sample = $validated['sections'][0]; $sample['treatment'] = $treatment;
                            $html = $render_portable($sample, $slug, $skin, $version);
                            foreach (['Question context', 'Saved question?', '<strong>Question body</strong>', 'Question link', '--treatment-' . str_replace('.', '-', $treatment ?: 'statement.leitfrage')] as $value) {
                                $assert(str_contains($html, $value), "$slug/$skin/v$version: question renders $value");
                            }
                            $assert(str_contains($html, 'id="' . ($slug === 'fuehrung' ? 'portable-anchor' : 'saved-question') . '"'), "$slug: retained question anchor survives");
                        }
                    }
                    $assert(iss_editorial_save_document($id, $slug, $question) && iss_editorial_get_document($id, $slug) === $validated, "$slug/v$version: question save/reload retains complete contract");
                }
                $draft = iss_editorial_save_draft($id, $slug, $question, true);
                $assert(!is_wp_error($draft), "$slug: retained question autosave");
                $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => $slug, 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($id, $slug))];
                $model = iss_editorial_get_read_model($id, $slug);
                $html = $render_portable($model['sections'][0], $slug, $model['skin']);
                $assert(substr_count($html, 'data-iss-preview-section="0"') === 1 && str_contains($html, 'data-iss-field="body"'), "$slug: alias rendering keeps exact preview edit address");
                $assert($model['sections'][0]['type'] === 'leitfrage', "$slug: rendering cannot rewrite snapshot identifier");
                $_GET = [];
            }
            // Facts preserve legacy prose and structured rows through the shared contract.
            foreach ([1, 2, 3] as $version) {
                $fact_doc = iss_editorial_get_empty_document($slug); $fact_doc['schema_version'] = $version;
                if ($slug === 'veranstaltung') { $fact_doc['entity_key'] = 'event.general'; }
                foreach ([['body' => '<p><strong>8615</strong> Legacy facts prose</p>'], ['facts' => [['value' => '0', 'label' => 'Zero is a fact'], ['value' => '', 'label' => 'Description only'], ['value' => 'Value only', 'label' => '']]]] as $content) {
                    $fact_doc['sections'] = [array_merge(['type' => 'massstab', 'anchor' => 'legacy-facts'], $content)];
                    $validated = iss_editorial_validate_document($fact_doc, $slug);
                    $assert(!is_wp_error($validated) && $validated['sections'][0]['type'] === 'facts', "$slug/v$version: existing massstab normalization remains valid");
                    $assert(iss_editorial_save_document($id, $slug, $fact_doc) && iss_editorial_get_document($id, $slug) === $validated, "$slug/v$version: facts save/reload preserves all supported fields");
                    $html = $render_portable($validated['sections'][0], $slug, $format['default_skin'], $version);
                    foreach (isset($content['facts']) ? ['>0<', 'Zero is a fact', 'Description only', 'Value only'] : ['<strong>8615</strong>', 'Legacy facts prose'] as $value) {
                        $assert(str_contains($html, $value), "$slug/v$version: facts without a heading retain $value");
                    }
                    $assert(str_contains($html, 'id="' . ($slug === 'fuehrung' ? 'portable-anchor' : 'legacy-facts') . '"'), "$slug/v$version: fact anchor retained");
                }
                $assert($render_portable(['type' => 'facts'], $slug, $format['default_skin'], $version) === '', "$slug/v$version: empty facts omit public wrapper");
            }
            $fact_doc['sections'] = [['type' => 'facts', 'body' => 'Facts context', 'links' => [['label' => 'Unsupported', 'url' => 'https://example.invalid']]]];
            $assert(is_wp_error(iss_editorial_validate_document($fact_doc, $slug)), "$slug: unsupported fact links cannot be silently discarded");
            $escaped = $render_portable(['type' => 'facts', 'facts' => [['value' => '<img src=x onerror=alert(1)>', 'label' => '<script>alert(1)</script>']]], $slug, $format['default_skin']);
            $assert(!str_contains($escaped, '<img ') && !str_contains($escaped, '<script>'), "$slug: fact values and descriptions render as escaped text");
            // Conclusions have one link-capable contract, including links-only endings.
            $assert($format['sections']['schluss']['portable'] && $format['sections']['schluss']['renderer'] === 'landing', "$slug: conclusion uses shared composition");
            foreach ([1, 2, 3] as $version) {
                $ending = iss_editorial_get_empty_document($slug); $ending['schema_version'] = $version;
                if ($slug === 'veranstaltung') { $ending['entity_key'] = 'event.general'; }
                $ending['sections'] = [['type' => 'schluss', 'anchor' => 'contact-end', 'links' => [
                    ['label' => 'Contact & visit', 'url' => 'https://example.invalid/contact?a=1&b=2'],
                    ['label' => 'Second destination', 'url' => 'https://example.invalid/visit'],
                ]]];
                $validated = iss_editorial_validate_document($ending, $slug);
                $assert(!is_wp_error($validated), "$slug/v$version: links-only conclusion validates");
                $html = $render_portable($validated['sections'][0], $slug, $format['default_skin'], $version);
                $assert(str_contains($html, 'Contact &amp; visit') && str_contains($html, 'Second destination') && str_contains($html, 'https://example.invalid/contact?a=1&#038;b=2'), "$slug/v$version: both links render and escape correctly without prose");
                $assert(str_contains($html, 'id="' . ($slug === 'fuehrung' ? 'portable-anchor' : 'contact-end') . '"'), "$slug/v$version: conclusion preserves the owner anchor");
                if ($slug === 'veranstaltung') {
                    foreach (iss_content_model_veranstaltung_entities() as $entity_key => $entity) {
                        $ending['entity_key'] = $entity_key;
                        $assert(!is_wp_error(iss_editorial_validate_document($ending, $slug)) && isset(iss_content_model_veranstaltung_content_gestures_for_entity($entity_key)['schluss']), "$entity_key/v$version: conclusion links accepted and available in chooser");
                    }
                }
                if ($slug === 'projekt') {
                    $nav = industriesalon_editorial_project_nav_items($validated['sections']);
                    $assert(count($nav) === 1 && $nav[0]['anchor'] === 'contact-end' && $nav[0]['type'] === 'schluss', 'Project contact navigation retains the conclusion');
                }
            }
            $chapter_doc = iss_editorial_get_empty_document($slug);
            if ($slug === 'veranstaltung') { $chapter_doc['entity_key'] = 'event.general'; }
            $chapter_doc['sections'] = [['type' => 'kapitel', 'anchor' => 'shared-chapter', 'title' => 'Portable chapter', 'body' => '<p><strong>Chapter prose</strong></p>',
                'links' => [['label' => 'Chapter link', 'url' => 'https://example.invalid/chapter']],
                'media_refs' => [array_merge($media, ['label' => 'Chapter image caption'])], 'media_layout' => 'aside-right', 'section_treatment' => 'aside']];
            foreach ([1, 2, 3] as $version) {
                $chapter_doc['schema_version'] = $version;
                $validated = iss_editorial_validate_document($chapter_doc, $slug);
                $assert(!is_wp_error($validated), "$slug/v$version: retained chapter fields validate");
                $html = $render_portable($validated['sections'][0], $slug, $format['default_skin'], $version);
                foreach (['Chapter link', 'Chapter image caption', '<strong>Chapter prose</strong>', 'iss-landing-chapter--media-aside', 'iss-landing-chapter--aside'] as $value) {
                    $assert(str_contains($html, $value), "$slug/v$version: chapter retains $value");
                }
            }
            $dynamic_ref = ['kind' => 'control_field', 'source' => 'industriesalon-steuerung', 'key' => 'address.full', 'label' => 'Live institution address', 'tagName' => 'p', 'linkMode' => 'none'];
            $dynamic_doc = $chapter_doc; $dynamic_doc['sections'] = [['type' => 'kapitel', 'dynamic_refs' => [$dynamic_ref]]];
            if ($slug === 'veranstaltung') {
                $assert(iss_editorial_save_document($id, $slug, $dynamic_doc), 'Event chapter retains live-only references without prose');
                $assert(iss_editorial_get_document($id, $slug)['sections'][0]['dynamic_refs'] === [$dynamic_ref], 'Event live reference round-trips without converting it to text');
                $model = iss_editorial_get_read_model($id, $slug);
                $html = $render_portable($model['sections'][0], $slug, $format['default_skin']);
                $value = trim((string) Industriesalon_Steuerung::instance()->get_field_value('address.full', ''));
                $assert($value !== '' && str_contains($html, esc_html($value)) && str_contains($html, 'Live institution address'), 'Event chapter reads the current value from its owner');
                foreach (iss_content_model_veranstaltung_entities() as $entity_key => $entity) {
                    $dynamic_doc['entity_key'] = $entity_key;
                    $assert(!is_wp_error(iss_editorial_validate_document($dynamic_doc, $slug)), "$entity_key: live references remain an event chapter extension");
                }
            } else {
                $assert(is_wp_error(iss_editorial_validate_document($dynamic_doc, $slug)), "$slug: event live fields cannot leak into other formats");
            }
            if ($slug === 'projekt') {
                $chapter_doc['skin'] = 'dossier';
                $chapter_doc['sections'][] = ['type' => 'facts', 'anchor' => 'chapter-facts', 'kicker' => 'Fact context', 'title' => 'Paired facts', 'body' => '<p><strong>Facts prose</strong> <span class="' . $colour . '">Facts colour</span></p>', 'facts' => [['label' => 'Owned fact', 'value' => '12']]];
                $assert(iss_editorial_save_document($id, $slug, $chapter_doc), 'Project chapter and facts save together');
                $GLOBALS['wp_the_query'] = $query;
                $html = apply_filters('the_content', '<p>Original block content</p>');
                foreach (['iss-project-dossier-spread', 'Paired facts', 'Owned fact', 'Chapter image caption', 'Chapter link', 'iss-landing-chapter--media-aside', 'iss-landing-chapter--aside', 'id="shared-chapter"', 'id="chapter-facts"', '<strong>Facts prose</strong>', $colour] as $value) {
                    $assert(str_contains($html, $value), "Paired project layout retains $value");
                }
                $draft = iss_editorial_save_draft($id, $slug, $chapter_doc, true);
                $assert(!is_wp_error($draft), 'Paired project chapter snapshot saves');
                $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => $slug, 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($id, $slug))];
                $html = apply_filters('the_content', '<p>Original block content</p>');
                $assert(substr_count($html, 'data-iss-preview-section="0"') === 1 && substr_count($html, 'data-iss-preview-section="1"') === 1 && substr_count($html, 'data-iss-field="body"') === 2, 'Chapter and facts retain separate editable snapshot addresses');
                $fact_tags = new WP_HTML_Tag_Processor($html);
                $assert($fact_tags->next_tag(['class_name' => 'iss-project-section__kicker']) && $fact_tags->get_attribute('data-iss-field') === 'kicker', 'Paired fact context remains editable');
                $_GET = []; $GLOBALS['wp_the_query'] = $original_main_query;
            }
        } else {
            $assert(array_intersect($portable_types, array_keys($format['sections'])) === [], "$slug: specialized adapters are not silently widened");
            $assert(!isset($format['sections']['schluss']) && !isset($format['sections']['kapitel']), 'Places do not acquire shared conclusions or chapters');
        }
        if (in_array($slug, ['fuehrung', 'veranstaltung', 'projekt', 'ausstellung', 'rueckblick'], true)) {
            $upload = iss_editorial_get_empty_document($slug);
            if ($slug === 'veranstaltung') { $upload['entity_key'] = 'event.general'; }
            $upload['sections'] = [['type' => 'upload_intake', 'title' => 'Contribute material']];
            $assert(iss_editorial_save_document($id, $slug, $upload), "$slug: save existing upload-call contract");
            $assert(!metadata_exists('post', $id, '_iss_upload_open') && iss_content_upload_is_open($id), "$slug: retained call without an explicit setting uses the legacy open default");
            iss_content_set_upload_open($id, false);
            $assert(iss_editorial_save_document($id, $slug, $upload), "$slug: closed call remains editable");
            $assert(!iss_content_upload_is_open($id) && iss_content_upload_url($id) === '', "$slug: saving a call cannot override explicit closure");
            $assert(industriesalon_render_editorial_project_upload_intake($upload['sections'][0]) === '', "$slug: closed calls render no upload invitation");
        }
    }
    // Publication layout adapters preserve native data and the shared gesture order.
    foreach (['standard', 'longread', 'timeline', 'photoalbum'] as $layout) {
        foreach (['standard', 'longread-poster', 'bildmatrix'] as $skin) {
            $_GET = [];
            $id = wp_insert_post(['post_type' => 'publication', 'post_status' => 'draft', 'post_title' => 'Disposable publication composition', 'post_content' => '<p>Legacy fallback only</p>', 'post_author' => $admin->ID]);
            $fixtures[] = $id;
            update_post_meta($id, '_iss_publication_layout', $layout);
            $query = new WP_Query(); $query->posts = [get_post($id)]; $query->queried_object_id = $id; $query->queried_object = get_post($id);
            $query->is_singular = true; $query->is_single = true; $query->in_the_loop = true; $query->post = get_post($id);
            $GLOBALS['wp_query'] = $query; $GLOBALS['wp_the_query'] = $query; $GLOBALS['post'] = get_post($id);
            $doc = iss_editorial_get_empty_document('publication'); $doc['skin'] = $skin;
            $native = match ($layout) {
                'timeline' => ['type' => 'timeline_item', 'year' => '1900', 'title' => 'Native first', 'body' => '<p>Native body</p>', 'media_refs' => [$media]],
                'photoalbum' => ['type' => 'photoalbum', 'album_source' => ['kind' => 'manual', 'set_title' => 'Owned album source'], 'sheets' => [['source_kind' => 'wp_media', 'source_id' => $image_id, 'label' => 'Native first', 'nav_title' => 'Owned sheet', 'caption' => 'Owned caption']]],
                default => ['type' => 'longread_chapter', 'title' => 'Native first', 'anchor' => 'native-first', 'body' => '<p>Native body</p>', 'media_refs' => [$media]],
            };
            $doc['sections'] = [
                ['type' => 'intro', 'body' => '<p>Owned introduction</p>'],
                ['type' => 'source', 'body' => 'Owned source note'],
                ['type' => 'publication_rail', 'rail_options' => ['show_nav' => true, 'show_summary' => true, 'show_related' => false]],
                ['type' => 'statement', 'title' => 'Shared before', 'body' => '<p>Shared before body</p>'],
                $native,
                ['type' => 'feature', 'title' => 'Shared middle', 'lead' => '<p>Shared lead</p>', 'body' => '<p>Shared middle body</p>', 'media_refs' => [$media]],
            ];
            if ($layout !== 'photoalbum') {
                $second = $native; $second['title'] = 'Native second'; $second['anchor'] = 'native-second';
                $doc['sections'][] = $second;
            }
            $doc['sections'][] = ['type' => 'gateway', 'title' => 'Shared after', 'body' => '<p>Shared after body</p>', 'items' => [['label' => 'Shared item', 'text' => '<em>Shared card copy</em>', 'url' => 'https://example.invalid/shared']]];
            foreach (['galerie', 'fliesstext', 'zitat', 'schluss', 'kapitel', 'facts', 'material', 'objektfokus'] as $common_type) {
                $extra = ['type' => $common_type, 'title' => 'Layout ' . $common_type, 'body' => '<p>Layout common body</p>', 'media_refs' => [$media], 'object_refs' => [$archive_ref]];
                if ($common_type === 'schluss') { unset($extra['media_refs'], $extra['object_refs']); $extra['links'] = [['label' => 'Layout contact', 'url' => 'https://example.invalid/contact']]; }
                if ($common_type === 'facts') { unset($extra['media_refs'], $extra['object_refs']); $extra['facts'] = [['value' => 'Layout fact value', 'label' => 'Layout fact label']]; }
                if ($common_type === 'material') { unset($extra['object_refs']); $extra['media_refs'] = [array_merge($media, ['label' => 'Layout download'])]; }
                if ($common_type === 'objektfokus') { unset($extra['media_refs']); }
                if ($common_type === 'kapitel') { unset($extra['object_refs']); $extra['media_layout'] = 'aside-right'; $extra['section_treatment'] = 'aside'; }
                if ($common_type === 'zitat') { $extra['quote'] = 'Layout quote'; $extra['attribution'] = 'Layout attribution'; }
                $doc['sections'][] = $extra;
            }
            $label = "publication/$layout/$skin";
            $assert(iss_editorial_save_document($id, 'publication', $doc), "$label: save mixed composition");
            iss_editorial_set_document_enabled($id, 'publication', true);
            $canonical = get_post_meta($id, iss_editorial_get_document_meta_key('publication'), true);
            $html = apply_filters('the_content', '<p>Legacy fallback only</p>');
            $markers = ['Shared before', 'Native first', 'Shared middle'];
            if ($layout !== 'photoalbum') { $markers[] = 'Native second'; }
            $markers[] = 'Shared after';
            $position = -1;
            foreach ($markers as $marker) {
                $next = strpos($html, $marker);
                $assert($next !== false && $next > $position, "$label: document order for $marker");
                $position = $next;
            }
            foreach (['Shared before' => 2, 'Shared middle' => 2, 'Shared after' => 2, 'Shared lead' => 1, 'Owned source note' => 1] as $marker => $count) {
                $assert(substr_count($html, $marker) === $count, "$label: content appears once per field: $marker");
            }
            foreach (['galerie', 'fliesstext', 'zitat', 'schluss', 'kapitel', 'facts', 'material', 'objektfokus'] as $common_type) { $assert(substr_count($html, 'Layout ' . $common_type) === 1, "$label: shared $common_type renders once"); }
            $assert(str_contains($html, 'Layout download') && str_contains($html, 'download='), "$label: files survive publication layout selection");
            $assert(substr_count($html, 'Layout fact value') === 1 && substr_count($html, 'Layout fact label') === 1, "$label: structured facts survive publication layout selection");
            $assert(substr_count($html, 'Layout contact') === 1, "$label: conclusion links survive layout selection");
            $assert(!str_contains($html, 'Legacy fallback only'), "$label: enabled composition owns content");
            $assert(iss_publications_get_publication_rail_settings($id)['show_related'] === false, "$label: rail options retained");
            if ($layout === 'timeline') {
                $tags = new WP_HTML_Tag_Processor($html); $moments = 0;
                while ($tags->next_tag(['class_name' => 'iss-publication-moment'])) { ++$moments; }
                $assert($moments === 2, "$label: both native moments survive");
                $assert(str_contains($html, 'id="iss-publication-epoch-industrialisierung-1"'), "$label: repeated epoch has unique anchor");
                $assert(substr_count($html, 'href="#iss-publication-epoch-industrialisierung"') === 1, "$label: epoch navigation stays unique");
                $assert(str_contains($html, '<strong>2</strong><span>Stationen</span>') && str_contains($html, '<strong>1</strong><span>Epochen</span>'), "$label: summary counts only native data");
            } elseif ($layout === 'photoalbum') {
                $payload = iss_publications_get_photoalbum_payload($id);
                $assert($payload['sheet_count'] === 1 && $payload['sheets'][0]['attachment_id'] === $image_id && $payload['source_manual_title'] === 'Owned album source', "$label: album source, attachment and count retained");
                $assert(str_contains($html, 'Owned caption'), "$label: album caption survives");
            } else {
                $payload = iss_publications_get_longread_payload($id);
                $assert(array_column($payload['nav_items'], 'href') === ['#native-first', '#native-second'], "$label: chapter navigation retains original anchors");
                $assert($payload['summary'][0]['value'] === '2', "$label: summary counts chapters only");
            }
            // Read through the warmed public cache, then two distinct authenticated snapshots.
            foreach (['Snapshot one', 'Snapshot two'] as $title) {
                $draft_doc = $doc; $draft_doc['sections'][3]['title'] = $title;
                $draft = iss_editorial_save_draft($id, 'publication', $draft_doc, true);
                $assert(!is_wp_error($draft), "$label: save snapshot");
                $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => 'publication', 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($id, 'publication'))];
                $preview = apply_filters('the_content', '<p>Legacy fallback only</p>');
                $assert(str_contains($preview, $title), "$label: payload cache follows exact snapshot");
                foreach ([3, 4, 5] as $index) { $assert(substr_count($preview, 'data-iss-preview-section="' . $index . '"') === 1, "$label: exact editable address $index"); }
                $assert(str_contains($preview, 'data-iss-field="body"'), "$label: shared body remains editable");
            }
            $_GET = [];
            $assert(get_post_meta($id, iss_editorial_get_document_meta_key('publication'), true) === $canonical, "$label: preview preserves canonical JSON");
            iss_editorial_set_document_enabled($id, 'publication', false);
            $assert(trim(apply_filters('the_content', '<p>Legacy fallback only</p>')) === '<p>Legacy fallback only</p>', "$label: disabling JSON restores original block content after cached rendering");
            iss_editorial_set_document_enabled($id, 'publication', true);
            $only = $doc; $only['sections'] = [['type' => 'statement', 'title' => 'Standalone shared section', 'body' => '<p>Standalone body</p>']];
            $assert(iss_editorial_save_document($id, 'publication', $only), "$label: save without domain sections");
            $assert(substr_count(apply_filters('the_content', '<p>Legacy fallback only</p>'), 'Standalone shared section') === 1, "$label: shared-only composition works without a chapter, date or album");
        }
    }
    // The same native composition must survive every layout, including warm-cache switches.
    foreach (['standard', 'longread-poster', 'bildmatrix'] as $skin) {
        $doc = iss_editorial_get_empty_document('publication'); $doc['skin'] = $skin;
        $doc['sections'] = [
            ['type' => 'source', 'body' => 'Mixed publication source'],
            ['type' => 'longread_chapter', 'title' => 'Chapter Alpha', 'anchor' => 'chapter-alpha', 'body' => '<p>Chapter Alpha body</p>', 'media_layout' => 'aside-right', 'media_refs' => [$media]],
            ['type' => 'timeline_item', 'year' => '1900', 'title' => 'Moment Alpha', 'body' => '<p>Moment Alpha body</p>', 'media_refs' => [$media]],
            ['type' => 'photoalbum', 'album_source' => ['kind' => 'manual', 'set_title' => 'Album Alpha source'], 'sheets' => [['source_kind' => 'wp_media', 'source_id' => $image_id, 'label' => 'Blatt 1', 'nav_title' => 'Sheet Alpha', 'caption' => 'Caption Alpha']]],
            ['type' => 'statement', 'title' => 'Mixed shared section'],
            ['type' => 'longread_chapter', 'title' => 'Chapter Beta', 'anchor' => 'chapter-beta', 'body' => '<p>Chapter Beta body</p>'],
            ['type' => 'photoalbum', 'album_source' => ['kind' => 'manual', 'set_title' => 'Album Beta source'], 'sheets' => [['source_kind' => 'wp_media', 'source_id' => $image_id, 'label' => 'Blatt 1', 'nav_title' => 'Sheet Beta', 'caption' => 'Caption Beta'], ['source_kind' => 'wp_media', 'source_id' => $image_id, 'label' => 'Hidden sheet', 'visible' => false]]],
            ['type' => 'timeline_item', 'year' => '1920', 'title' => 'Moment Beta', 'body' => '<p>Moment Beta body</p>'],
        ];
        $assert(iss_editorial_save_document($id, 'publication', $doc), "$skin: save all native types together");
        $saved = get_post_meta($id, iss_editorial_get_document_meta_key('publication'), true);
        foreach (['standard', 'longread', 'timeline', 'photoalbum', 'standard'] as $layout) {
            $_GET = [];
            update_post_meta($id, '_iss_publication_layout', $layout);
            $html = apply_filters('the_content', '<p>Legacy fallback only</p>');
            $label = "$layout/$skin mixed native sections";
            $position = -1;
            foreach (['Chapter Alpha', 'Moment Alpha', 'Sheet Alpha', 'Mixed shared section', 'Chapter Beta', 'Sheet Beta', 'Moment Beta'] as $marker) {
                $next = strpos($html, $marker);
                $assert($next !== false && $next > $position, "$label: retains order for $marker");
                $position = $next;
            }
            foreach (['Chapter Alpha body', 'Moment Alpha body', 'Caption Alpha', 'Chapter Beta body', 'Caption Beta', 'Moment Beta body', 'Mixed publication source'] as $marker) {
                $assert(substr_count($html, $marker) === 1, "$label: renders $marker once");
            }
            $assert(!str_contains($html, 'Hidden sheet'), "$label: hidden sheet remains hidden");
            $assert(str_contains($html, 'iss-publication-longread__media--aside-right'), "$label: preserves chapter media layout");
            $assert(str_contains($html, 'Kapitel 02'), "$label: chapter numbering ignores other gestures");
            $tags = new WP_HTML_Tag_Processor($html); $anchors = [];
            while ($tags->next_tag()) { $anchor = $tags->get_attribute('id'); if ($anchor) { $anchors[] = $anchor; } }
            $assert(count($anchors) === count(array_unique($anchors)), "$label: unique anchors across albums and chapters");
            if ($layout === 'photoalbum') {
                $payload = iss_publications_get_photoalbum_payload($id);
                $assert($payload['sheet_count'] === 2 && count($payload['sheets']) === 2, "$label: album count includes both albums only");
                $assert(count(array_unique(array_column($payload['sheets'], 'anchor'))) === 2, "$label: sheet navigation targets are distinct");
                foreach ($payload['sheets'] as $sheet) { $assert(in_array($sheet['anchor'], $anchors, true), "$label: sheet navigation resolves"); }
            } elseif (in_array($layout, ['standard', 'longread'], true)) {
                $payload = iss_publications_get_longread_payload($id);
                $assert(array_column($payload['nav_items'], 'href') === ['#chapter-alpha', '#chapter-beta'], "$label: chapter navigation remains owned");
                $assert($payload['summary'][0]['value'] === '2', "$label: only chapters counted");
            } else {
                $assert(str_contains($html, '<strong>2</strong><span>Stationen</span>'), "$label: only moments counted");
            }
            $draft = iss_editorial_save_draft($id, 'publication', $doc, true);
            $assert(!is_wp_error($draft), "$label: save exact mixed draft");
            $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => 'publication', 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($id, 'publication'))];
            $preview = apply_filters('the_content', '<p>Legacy fallback only</p>');
            foreach (range(1, 7) as $index) { $assert(substr_count($preview, 'data-iss-preview-section="' . $index . '"') === 1, "$label: exact section address $index"); }
            $_GET = [];
            $assert(get_post_meta($id, iss_editorial_get_document_meta_key('publication'), true) === $saved, "$label: layout switching preserves canonical JSON");
        }
    }
    // A native gesture must work even without any section belonging to the chosen layout.
    foreach (['standard', 'longread', 'timeline', 'photoalbum'] as $layout) {
        update_post_meta($id, '_iss_publication_layout', $layout);
        foreach ([1 => 'Chapter Alpha body', 2 => 'Moment Alpha body', 3 => 'Caption Alpha'] as $index => $marker) {
            $single = iss_editorial_get_empty_document('publication');
            $single['sections'] = [['type' => 'intro', 'body' => 'Single introduction'], ['type' => 'source', 'body' => 'Single source'], $doc['sections'][$index]];
            $assert(iss_editorial_save_document($id, 'publication', $single), "$layout: save standalone native gesture");
            $html = apply_filters('the_content', '<p>Legacy fallback only</p>');
            $assert(substr_count($html, $marker) === 1, "$layout: standalone $marker survives");
            $assert(substr_count($html, 'Single source') === 1, "$layout: standalone source survives");
            if ($layout !== 'timeline') { $assert(substr_count($html, 'Single introduction') === 1, "$layout: standalone introduction survives"); }
        }
    }
    $_GET = []; $GLOBALS['wp_the_query'] = $original_main_query;
    $place = iss_editorial_get_format('place');
    $assert(!isset($place['sections']['facts']), 'Facts do not widen the Place contract');
    $assert(array_keys($place['sections']['dynamic_slot']['slots']) === ['place-epoch-contribution'] && !$place['sections']['dynamic_slot']['portable'], 'Place reuses the dynamic gesture with only its owned invitation source');
    $assert(!isset($place['sections']['statement']) && !isset($place['sections']['leitfrage']), 'Place retains its separate narrative contract');
    foreach (['veranstaltung', 'fuehrung'] as $slug) {
        $assert(!iss_editorial_get_format($slug)['sections']['intro']['portable'], "$slug: native introduction keeps its owner");
    }
    $assert(!iss_editorial_get_format('fuehrung')['sections']['bildbuehne']['portable'], 'Tour image stage retains its hero contract');
    $starter = iss_editorial_get_format('ausstellung')['starters'][0]['sections'][0];
    $assert($starter === ['type' => 'statement', 'treatment' => 'statement.leitfrage'], 'Exhibition starter creates the canonical question treatment');
    $assert(!$place['sections']['material']['portable'] && $place['sections']['material']['supports'] === ['anchor', 'media_refs', 'links'], 'Place material retains its separate contract');
    $assert(!isset($place['sections']['objektfokus']), 'Place retains its separate archive supplement contract');
    $assert(!$place['sections']['galerie']['portable'] && $place['sections']['galerie']['supports'] === ['media_refs', 'object_refs', 'gallery_layout'], 'Place gallery retains its separate contract');
    $place_html = industriesalon_render_editorial_ausstellung_section(['type' => 'galerie', 'title' => 'Place gallery', 'body' => 'Place body'], false, 0, 'standard', 'place');
    $assert(str_contains($place_html, 'iss-ausstellung-section') && !str_contains($place_html, 'iss-landing-section'), 'Place supplement retains native renderer');
    $assert($place['sections']['intro']['max_instances'] === 1 && $place['sections']['gegenwart']['max_instances'] === 1 && $place['sections']['epoche']['max_instances'] === 0, 'Place insertion limits only its fixed overview slots');
    $assert(str_contains($place['editor']['order_notice'], 'Datumsfolge') && str_contains($place['editor']['order_notice'], 'Einleitung'), 'Place explains fixed grouping and dated chronology');
    $place_id = wp_insert_post(['post_type' => 'register_place', 'post_status' => 'draft', 'post_title' => 'Place layout fixture', 'post_author' => $admin->ID]);
    $fixtures[] = $place_id;
    $place_doc = iss_editorial_get_empty_document('place');
    $place_doc['sections'] = [
        ['type' => 'gegenwart', 'title' => 'Present & future', 'kicker' => 'Present context', 'body' => '<p><strong>Present prose</strong></p>', 'media_refs' => [array_merge($media, ['label' => 'Present image'])], 'links' => [['label' => 'Visit & learn', 'url' => 'https://example.invalid/place?a=1&b=2']]],
        ['type' => 'intro', 'title' => 'Opening & context', 'kicker' => 'Opening context', 'body' => '<p><em>Opening prose</em></p>', 'media_refs' => [array_merge($media, ['label' => 'First opening image']), array_merge($media, ['label' => 'Second opening image'])]],
        ['type' => 'epoche', 'title' => 'Historic phase', 'body' => '<p>Historic prose</p>', 'era_key' => 'nach-1990', 'function_key' => 'culture'],
        ['type' => 'intro', 'title' => 'Retained second opening', 'body' => '<p>Retained opening prose</p>'],
        ['type' => 'gegenwart', 'body' => '<p>Retained second present</p>'],
        ['type' => 'material', 'title' => 'Dossier supplement', 'links' => [['label' => 'Material source', 'url' => 'https://example.invalid/material']]],
    ];
    $assert(iss_editorial_save_document($place_id, 'place', $place_doc, false, true), 'Existing repeated Place overviews remain valid and saveable');
    $place_doc = iss_editorial_get_document($place_id, 'place');
    $place_doc['sections'][] = ['type' => 'dynamic_slot', 'slot_key' => 'place-epoch-contribution', 'treatment' => 'slot.place-epoch-contribution'];
    iss_content_set_upload_open($place_id, true);
    $assert(iss_editorial_save_document($place_id, 'place', $place_doc, false, true), 'Place-owned dynamic gesture remains part of the canonical document');
    $place_doc = iss_editorial_get_document($place_id, 'place');
    $canonical_place = get_post_meta($place_id, iss_editorial_get_document_meta_key('place'), true);
    $public_place = industriesalon_render_editorial_place_document($place_id, iss_editorial_get_read_model($place_id, 'place'));
    foreach (['Opening &amp; context', 'Opening context', '<em>Opening prose</em>', 'First opening image', 'Second opening image', 'Historic prose', 'Present &amp; future', 'Present context', '<strong>Present prose</strong>', 'Present image', 'Visit &amp; learn', 'https://example.invalid/place?a=1&#038;b=2', 'Retained second opening', 'Retained second present', 'Dossier supplement'] as $value) {
        $assert(substr_count($public_place, $value) === 1, 'Place renders offered field or retained content once: ' . $value);
    }
    $positions = array_map(static fn(string $value): int => strpos($public_place, $value), ['Opening &amp; context', 'Retained second opening', 'Historic phase', 'Present &amp; future', 'Retained second present', 'Dossier supplement']);
    $sorted = $positions; sort($sorted);
    $assert($positions === $sorted, 'Out-of-order Place JSON keeps fixed dossier groups and order within them');
    $assert(substr_count($public_place, '<figure') === 3 && !str_contains($public_place, 'data-iss-'), 'All overview images render without public editing attributes');
    foreach (['intro' => 'Warum dieser Ort zählt', 'gegenwart' => 'Der Ort heute'] as $type => $fallback_title) {
        $assert(industriesalon_render_editorial_place_overview(['type' => $type]) === '', $type . ': empty slots produce no phantom heading');
        $assert(str_contains(industriesalon_render_editorial_place_overview(['type' => $type, 'body' => 'Body without title']), $fallback_title), $type . ': body-only content retains its default heading');
        $assert(industriesalon_render_editorial_place_overview(['type' => $type, 'title' => 'Title without body']) === '', $type . ': title-only content does not create an empty section');
        $image_only = ['type' => $type, 'media_refs_resolved' => [['reference' => $media, 'resolved' => iss_editorial_resolve_reference($media)]]];
        $assert(str_contains(industriesalon_render_editorial_place_overview($image_only), '<img'), $type . ': image-only content is visible');
    }
    $assert(str_contains(industriesalon_render_editorial_place_overview(['type' => 'gegenwart', 'links' => [['label' => 'Link-only present', 'url' => 'https://example.invalid/']]]), 'Link-only present'), 'Link-only present content is visible');
    $draft = iss_editorial_save_draft($place_id, 'place', $place_doc, true);
    $assert(!is_wp_error($draft), 'Place preview fixture saves its recovery document');
    $query = new WP_Query(); $query->posts = [get_post($place_id)]; $query->queried_object_id = $place_id; $query->queried_object = get_post($place_id);
    $query->is_singular = true; $query->is_single = true; $query->post = get_post($place_id);
    $GLOBALS['wp_query'] = $query; $GLOBALS['post'] = get_post($place_id);
    $_GET = ['iss_editorial_embed' => '1', 'iss_editorial_canvas' => '1', 'iss_editorial_preview' => '1', 'iss_editorial_format' => 'place', 'iss_editorial_snapshot' => $draft['token'], 'iss_editorial_preview_nonce' => wp_create_nonce(iss_editorial_get_preview_nonce_action($place_id, 'place'))];
    $preview_place = industriesalon_render_editorial_place_document($place_id, iss_editorial_get_read_model($place_id, 'place'));
    $tags = new WP_HTML_Tag_Processor($preview_place); $addresses = []; $fields = []; $address = null;
    while ($tags->next_tag()) {
        if ($tags->get_attribute('data-iss-preview-section') !== null) { $address = (int) $tags->get_attribute('data-iss-preview-section'); $addresses[] = $address; }
        if ($tags->get_attribute('data-iss-field') !== null) { $fields[$address][] = $tags->get_attribute('data-iss-field'); }
    }
    $assert($addresses === [1, 3, 2, 6, 0, 4, 5], 'Preview addresses retain exact JSON indexes across fixed grouping and duplicates');
    foreach ([0, 1, 4] as $index) { $assert($fields[$index] === ['kicker', 'title', 'body'], 'Place preview edits its own title, kicker and body at index ' . $index); }
    $assert($fields[3] === ['kicker', 'title', 'body'], 'A populated retained introduction has the correct editing address');
    $assert(!isset($fields[6]), 'Automatic gesture retains its own preview address without exposing generated copy as editable text');
    $assert(get_post_meta($place_id, iss_editorial_get_document_meta_key('place'), true) === $canonical_place, 'Rendering and preview do not rewrite saved Place JSON or remove duplicates');
    $_GET = []; $GLOBALS['wp_query'] = $original_query; $GLOBALS['post'] = $original_post;
    $publication = iss_editorial_get_format('publication');
    $assert($publication['sections']['longread_quote']['ui_hidden'] && !$publication['sections']['zitat']['ui_hidden'], 'One visible quote choice; old publication quote remains registered');
    $legacy_quote = iss_editorial_get_empty_document('publication');
    $legacy_quote['sections'] = [['type' => 'longread_quote', 'title' => 'Legacy quote title', 'body' => '<p>Legacy accompanying body</p>', 'quote' => 'Legacy quote words', 'attribution' => 'Legacy attribution']];
    $validated = iss_editorial_validate_document($legacy_quote, 'publication');
    $assert(!is_wp_error($validated) && $validated['sections'][0]['type'] === 'longread_quote', 'Legacy publication quote identifier is preserved');
    $html = $render_portable($validated['sections'][0], 'publication', 'standard');
    foreach (['Legacy quote title', 'Legacy accompanying body', 'Legacy quote words', 'Legacy attribution'] as $value) { $assert(str_contains($html, $value), 'Legacy quote preserves ' . $value); }
    // Narrative bodies reuse article; facts and archive projections keep their owners.
    foreach (['team_member', 'entity_profile', 'archivbeitrag', 'archivsammlung', 'archivobjekt'] as $post_type) {
        $id = wp_insert_post(['post_type' => $post_type, 'post_status' => 'auto-draft', 'post_title' => 'Disposable editorial coverage fixture', 'post_author' => $admin->ID]);
        $fixtures[] = $id;
        $assert(iss_editorial_get_format_for_post($id)['slug'] === 'article', "$post_type: shared article contract");
        $assert(iss_editorial_uses_canvas(get_post($id)), "$post_type: new content opens shared editor");
        wp_update_post(['ID' => $id, 'post_status' => 'draft', 'post_content' => '<p>Preserved native body</p>']);
        $assert(!iss_editorial_uses_canvas(get_post($id)), "$post_type: legacy content requires deliberate activation");
        $doc = iss_editorial_get_empty_document('article');
        $doc['sections'] = [['type' => 'fliesstext', 'body' => '<p>Shared narrative fixture</p>']];
        $assert(iss_editorial_save_document($id, 'article', $doc), "$post_type: save article document");
        iss_editorial_set_document_enabled($id, 'article', true);
        $assert(iss_editorial_uses_canvas(get_post($id)), "$post_type: enabled content uses shared editor");
        $query = new WP_Query(); $query->posts = [get_post($id)]; $query->queried_object_id = $id; $query->queried_object = get_post($id);
        $query->is_singular = true; $query->is_single = true; $query->post = get_post($id); $query->in_the_loop = true;
        $GLOBALS['wp_query'] = $query; $GLOBALS['wp_the_query'] = $query; $GLOBALS['post'] = get_post($id);
        $template = get_block_template(get_stylesheet() . '//single-' . $post_type, 'wp_template') ?: get_block_template(get_stylesheet() . '//single', 'wp_template');
        $assert($template && (has_block('core/post-content', $template->content) || has_block('industriesalon/editorial-landing', $template->content)), "$post_type: effective template has a narrative slot");
        $html = has_block('industriesalon/editorial-landing', $template->content) ? do_blocks('<!-- wp:industriesalon/editorial-landing /-->') : industriesalon_render_editorial_landing_content(get_post_field('post_content', $id));
        $assert(str_contains($html, 'Shared narrative fixture'), "$post_type: theme renders article content");
        $assert(!str_contains(get_the_excerpt($id), 'Shared narrative fixture'), "$post_type: excerpts cannot render the composition");
        $assert(get_post_field('post_content', $id) === '<p>Preserved native body</p>', "$post_type: source body preserved");
    }
    $assert(iss_editorial_get_format_for_post_type('register_place')['slug'] === 'place', 'Places retain their specialized format');
    $GLOBALS['wp_the_query'] = $original_main_query;
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
    $query = new WP_Query(); $query->posts = [get_post($id)]; $query->queried_object_id = $id; $query->queried_object = get_post($id);
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
    iss_editorial_reset_format_cache();
    foreach ($fixtures as $id) { wp_delete_post($id, true); }
    foreach (array_unique($sets) as $id) {
        $service = iss_content_editorial_sets_service();
        $assert($service->delete_set_if_safe($id), 'Remove own empty Set');
        $GLOBALS['wpdb']->delete($service->get_audit_table_name(), ['set_id' => $id], ['%d']);
    }
    wp_set_current_user($original_user);
    WP_CLI::log('Registry fixtures removed.');
}
