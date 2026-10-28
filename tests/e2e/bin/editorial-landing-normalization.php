<?php
/** Read-only acceptance of page eligibility, openings and source settings. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void { if (!$ok) { throw new RuntimeException($message); } ++$checks; };
$format = iss_editorial_get_format('landing');
$assert(iss_editorial_registry_errors() === [], 'Registry is coherent');
foreach (['new-page', 'kalender', 'verein', 'newsletter'] as $slug) {
    $candidate_post = new WP_Post((object) ['ID' => 900001, 'post_type' => 'page', 'post_name' => $slug, 'post_status' => 'draft']);
    $assert(iss_editorial_format_is_post_eligible($format, $candidate_post), 'Every page supports landings: ' . $slug);
    $assert(!iss_editorial_format_is_post_eligible(iss_editorial_get_format('article'), $candidate_post), 'A page has one format owner');
}
$doc = ['schema_version' => 3, 'skin' => 'typografisch', 'sections' => [['type' => 'feature', 'treatment' => 'feature.opening-text', 'title' => 'Text opening', 'body' => '<p>Introduction</p>']]];
$valid = iss_editorial_validate_document($doc, 'landing');
$assert(!is_wp_error($valid) && industriesalon_landing_has_opening($valid), 'Text opening needs no image');
$html = industriesalon_editorial_landing_render_document($valid);
$assert(substr_count($html, '<h1 ') === 1 && str_contains($html, 'Text opening') && str_contains($html, 'Introduction'), 'Text opening renders one editable H1 and body');
$bad = $doc; $bad['sections'][0]['title'] = '';
$assert(is_wp_error(iss_editorial_validate_document($bad, 'landing')), 'Empty opening title rejected');
$bad = $doc; array_unshift($bad['sections'], ['type' => 'statement', 'title' => 'Before opening']);
$assert(is_wp_error(iss_editorial_validate_document($bad, 'landing')), 'Misplaced text opening rejected');
foreach ($format['sections']['dynamic_slot']['slots'] as $key => $source) {
    $section = ['type' => 'dynamic_slot', 'slot_key' => $key, 'treatment' => $source['treatment']];
    foreach ($source['settings'] ?? [] as $field => $rule) { $section['slot_settings'][$key][$field] = $rule['default']; }
    $document = ['schema_version' => 3, 'skin' => 'typografisch', 'sections' => [$section]];
    $result = iss_editorial_validate_document($document, 'landing');
    $assert(!is_wp_error($result), 'Valid defaults for ' . $key);
    if (isset($section['slot_settings'])) { $assert($result['sections'][0]['slot_settings'] === $section['slot_settings'], 'Settings round-trip: ' . $key); }
    if (!empty($source['block'])) { $assert(WP_Block_Type_Registry::get_instance()->is_registered($source['block']), 'Source uses registered domain block: ' . $key); }
    foreach ($source['settings'] ?? [] as $field => $rule) {
        $bad = $document;
        $bad['sections'][0]['slot_settings'][$key][$field] = $rule['type'] === 'integer' ? $rule['max'] + 1 : ($rule['type'] === 'boolean' ? 'false' : (isset($rule['choices']) ? 'unknown-choice' : '<script>bad</script>'));
        $assert(is_wp_error(iss_editorial_validate_document($bad, 'landing')), 'Reject invalid setting without loss: ' . $key . '.' . $field);
    }
}
$section = ['type' => 'dynamic_slot', 'slot_key' => 'calendar', 'treatment' => 'slot.calendar', 'slot_settings' => ['calendar' => ['limit' => 8, 'content_types' => 'tours'], 'front-projects' => ['limit' => 5]]];
$document = ['schema_version' => 3, 'sections' => [$section]];
$assert(!is_wp_error(iss_editorial_validate_document($document, 'landing')), 'Inactive source settings are preserved for switching back');
$options = industriesalon_landing_source_options($section);
$assert($options['limit'] === 8 && $options['postTypesList'] === ['fuehrung'] && $options['allowedTypesList'] === ['fuehrungen'], 'Calendar options map only to owned source parameters');
foreach (['unknown-source' => ['limit' => 1], 'calendar' => ['arbitrary_block' => 'core/html']] as $key => $values) {
    $bad = $document; $bad['sections'][0]['slot_settings'] = [$key => $values];
    $assert(is_wp_error(iss_editorial_validate_document($bad, 'landing')), 'Unknown source or field is rejected');
}
// Legacy transcripts nested in a landing keep their own post context.
$legacy_video = null;
foreach (get_posts(['post_type' => 'video', 'numberposts' => -1]) as $candidate) {
    if (!iss_editorial_document_is_enabled($candidate->ID, 'article')
        && iss_content_model_render_video_transcript_document_html(iss_content_model_get_video_transcript_document($candidate->ID, false)) === '') {
        $legacy_video = $candidate; break;
    }
}
$assert($legacy_video instanceof WP_Post, 'Legacy transcript example is available');
$previous_post = $GLOBALS['post'] ?? null;
$observed_id = 0;
$observe = static function ($content) use (&$observed_id) { $observed_id = get_the_ID(); return $content; };
try {
    $GLOBALS['post'] = get_post((int) get_option('page_on_front'));
    $parent_id = get_the_ID(); setup_postdata($GLOBALS['post']);
    add_filter('the_content', $observe, 1);
    iss_content_model_get_video_transcript_html($legacy_video->ID);
    $assert($observed_id === $legacy_video->ID, 'Nested transcript filters see the video, not the parent landing');
    $assert(get_the_ID() === $parent_id, 'Parent context is restored after the nested transcript');
} finally {
    remove_filter('the_content', $observe, 1); $GLOBALS['post'] = $previous_post;
    if ($previous_post instanceof WP_Post) { setup_postdata($previous_post); }
}
WP_CLI::success($checks . ' landing normalization checks.');
