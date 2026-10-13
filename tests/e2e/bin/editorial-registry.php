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
    $missing_renderer = static function (array $formats): array { unset($formats['projekt']['sections']['feature']['renderer']); return $formats; };
    add_filter('iss_editorial_formats', $missing_renderer, 120);
    $assert(in_array('projekt.feature: portable gesture has no renderer', iss_editorial_registry_errors(), true), 'Registry diagnostics detect missing portable renderer coverage');
    remove_filter('iss_editorial_formats', $missing_renderer, 120);
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
    $media = ['kind' => 'wp_media', 'source' => 'wordpress', 'id' => (string) $image_id];
    $portable_types = ['statement', 'gateway', 'text_bild_reihe', 'feature'];
    $render_portable = static function (array $section, string $slug, string $skin, int $version = 3) use ($media): string {
        $section['_text_version'] = $version;
        if (!empty($section['media_refs'])) { $section['media_refs_resolved'] = [['reference' => $media, 'resolved' => iss_editorial_resolve_reference($media)]]; }
        return match ($slug) {
            'landing', 'article' => industriesalon_render_editorial_landing_section($section, 0, $skin),
            'projekt' => industriesalon_render_editorial_project_section($section, false, 0, $skin),
            'ausstellung', 'rueckblick' => industriesalon_render_editorial_ausstellung_section($section, false, 0, $skin),
            'fuehrung' => industriesalon_render_editorial_tour_section($section, false, 0, $skin, 'portable-anchor', get_the_ID()),
            'veranstaltung' => industriesalon_render_structured_veranstaltung_section($section, $skin),
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
        if (!in_array($slug, ['place', 'publication'], true)) {
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
                    foreach (array_diff(array_keys(iss_content_model_veranstaltung_content_gestures()), $entity['allowed_gestures']) as $restricted_type) {
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
        } else {
            $assert(array_intersect($portable_types, array_keys($format['sections'])) === [], "$slug: specialized adapters are not silently widened");
        }

    }
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
        $query = new WP_Query(); $query->queried_object_id = $id; $query->queried_object = get_post($id);
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
