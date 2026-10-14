<?php
/** Read-only Place rendering checks; fixtures live only in process-local caches. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
global $wpdb, $wp_query, $post;
$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$old_query = $wp_query;
$old_post = $post;
$id = (int) $wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->posts}") + 1000000;
$expect(get_post($id) === null && !wp_using_ext_object_cache(), 'Disposable ID and process-local cache');
$fixture = new WP_Post((object) ['ID' => $id, 'post_type' => 'register_place', 'post_status' => 'publish', 'post_title' => 'Optional Place fixture', 'post_name' => 'optional-place-fixture', 'post_content' => '', 'post_excerpt' => '', 'post_author' => 0]);
wp_cache_set($id, $fixture, 'posts');
wp_cache_set($id, [], 'post_meta');
$post = $fixture;
$wp_query = new WP_Query();
$wp_query->is_single = true;
$wp_query->is_singular = true;
$wp_query->queried_object = $fixture;
$wp_query->queried_object_id = $id;
$wp_query->post = $fixture;
$wp_query->posts = [$fixture];
$entity = iss_register_map_place_entity($fixture);
$supply = static function () use (&$entity): array { return [$entity]; };
add_filter('pre_transient_iss_register_places_cache', $supply);
// Isolate the fixture from global promoted posts as well as real relation results.
$no_posts = static fn(): array => [];
add_filter('posts_pre_query', $no_posts);
$guard = static function (string $sql): string {
    if (preg_match('/^\s*(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP)\b/i', $sql)) {
        throw new RuntimeException('Rendering attempted a database write');
    }
    return $sql;
};
add_filter('query', $guard);
$template = get_block_template('industriesalon//single-register_place', 'wp_template');
$main = array_values(array_filter(parse_blocks($template->content), static fn(array $b): bool => ($b['attrs']['tagName'] ?? '') === 'main'))[0];
$render = static fn(): string => render_block($main);
$has = static function (string $html, string $class): bool {
    return (new WP_HTML_Tag_Processor($html))->next_tag(['class_name' => $class]);
};
$set_meta = static function (array $meta) use ($id): void {
    $cache = [];
    foreach ($meta as $key => $value) { $cache[$key] = [maybe_serialize($value)]; }
    wp_cache_set($id, $cache, 'post_meta');
};
$pilot = get_page_by_path('kino-spreehofe', OBJECT, 'register_place');
$media_id = $pilot ? (int) get_post_thumbnail_id($pilot->ID) : 0;
$expect(wp_attachment_is_image($media_id), 'Existing published image available');
$image = ['media_id' => $media_id, 'visibility' => 'public', 'caption' => 'Fixture archive view'];
try {
    $expect($template->source === 'theme', 'Place template stays theme-backed');
    $expect(is_singular('register_place'), 'Real single-Place render context');
    $expect(serialize_blocks(parse_blocks($template->content)) === $template->content, 'Native template roundtrip');
    foreach (['', '<p> </p>', '<p>&nbsp;&#160;</p>', '<h2>Heading only</h2>', '<p class="iss-kicker"><span>Kicker only</span></p>', '<script>decorative()</script>'] as $empty) {
        $expect(!industriesalon_place_has_rendered_content($empty), 'Decorative or empty markup is absent');
    }
    $expect(industriesalon_place_has_rendered_content('<p>Real body text</p>'), 'Body text is content');
    $expect(industriesalon_place_has_rendered_content('<img src="/example.jpg" alt="">'), 'An image needs no prose');
    $empty = $render();
    foreach (['iss-register-place__fact-band', 'iss-register-place-dossier', 'iss-register-place-present', 'iss-register-place-contribution', 'iss-register-place-questions', 'iss-register-place-atlas'] as $class) {
        $expect(!$has($empty, $class), 'Empty template section omitted: ' . $class);
    }
    $expect(str_contains($empty, 'Optional Place fixture'), 'Page title remains');
    $expect(!$has($empty, 'iss-register-place__opening-lead'), 'Missing excerpt leaves no opening spacer');
    $fixture->post_title = 'Place name / Existing descriptor';
    wp_cache_set($id, $fixture, 'posts');
    $split_title = $render();
    $expect($has($split_title, 'iss-register-place__subtitle') && str_contains($split_title, 'Existing descriptor'), 'Existing descriptor appears beneath the title');
    $expect(get_the_title($id) === 'Place name / Existing descriptor', 'Presentation does not rewrite the saved title');
    $fixture->post_title = 'Optional Place fixture';
    $fixture->post_excerpt = 'A unique manual excerpt.';
    wp_cache_set($id, $fixture, 'posts');
    $set_meta(['history_short' => 'A unique manual excerpt.']);
    $expect(substr_count($render(), 'A unique manual excerpt.') === 1, 'Manual excerpt is not repeated by the legacy profile');
    $fixture->post_excerpt = '';
    wp_cache_set($id, $fixture, 'posts');
    $set_meta([]);
    $expect(iss_register_render_place_context(['variant' => 'epoch_rail']) === '', 'Empty legacy chronology returns no chips or rail');
    $expect(iss_register_render_place_context(['variant' => 'today_panel']) === '', 'Empty current status returns no wrapper');
    $entity['address'] = 'Example street 1';
    $set_meta(['history_short' => 'A short history.']);
    $small = $render();
    $expect($has($small, 'iss-register-place__fact-band') && str_contains($small, 'Example street 1'), 'One populated fact survives');
    $expect($has($small, 'iss-register-place__dossier-grid--single'), 'No sidebar leaves a single content column');
    $expect(!$has($small, 'iss-register-place__network-rail'), 'No relationships leave no sidebar');
    $expect(str_contains($small, 'A short history.'), 'Short profile survives');
    $entity['questions'] = ['What happened here?', ' ', ['invalid']];
    $questions = $render();
    $expect($has($questions, 'iss-register-place-contribution') && str_contains($questions, 'What happened here?'), 'Authored questions render even with intake closed');
    $expect(substr_count(iss_register_render_place_context(['variant' => 'questions']), '<li>') === 1, 'Blank and invalid questions omitted');
    $entity['questions'] = [];
    $entity['owner'] = 'Example owner';
    $expect($has($render(), 'iss-register-place-present'), 'Current facts survive without status');
    $entity['owner'] = '';
    $set_meta(['_iss_upload_open' => '1']);
    $open = $render();
    $expect($has($open, 'iss-register-place-contribution') && !$has($open, 'iss-register-place__hero-contribution') && substr_count($open, 'class="iss-register-place__upload-button"') === 1, 'Open intake renders one combined closing action');
    $set_meta([]);
    $expect(!$has($render(), 'iss-register-place-contribution'), 'Closing intake omits the entire contribution section');
    $expect(!$has($render(), 'iss-register-place__people') && !$has($render(), 'iss-register-place__collection'), 'Missing people and objects leave no headings or sections');
    $people = industriesalon_render_place_people('', [
        ['name' => 'Public person', 'is_public' => true, 'relation_label' => 'Architect', 'note' => 'A public connection'],
        ['name' => 'Private person', 'is_public' => false],
    ]);
    $expect($has($people, 'iss-card') && str_contains($people, 'Public person') && !str_contains($people, 'Private person'), 'People cards use only public direct relations');
    $kaos = get_page_by_path('kaos-93-kunst-und-gewerbehof-genossenschaft-i-gr', OBJECT, 'register_place');
    $service = iss_register_get_epoch_service();
    $phase_rows = $service->get_epochs_for_place($kaos->ID);
    $events = $service->get_milestones_for_place($kaos->ID);
    $grouped = iss_register_merge_place_milestones($kaos->ID, $phase_rows);
    $retained = [];
    foreach ($grouped as $row) {
        if (!empty($row['milestone'])) { $retained[] = $row['milestone']['key']; continue; }
        foreach ((array) ($row['milestones'] ?? []) as $event) {
            $year = (int) substr($event['event_date'], 0, 4);
            $expect($year >= $row['start_year'] && ($row['end_year'] === null || $year <= $row['end_year']), 'Dated update stays inside the matching epoch');
            $retained[] = $event['key'];
        }
    }
    $expected_events = array_column($events, 'key'); sort($retained); sort($expected_events);
    $expect($retained === $expected_events && count($grouped) < count($phase_rows) + count($events), 'Grouping retains every source update while reducing independent chronology rows');
    $compact = industriesalon_render_place_updates($events);
    $expect(substr_count($compact, 'class="iss-register-place__update"') === count($events) && !str_contains($compact, '<h3'), 'Updates render as compact source-bearing rows');
    $entity['epochs'] = [['phase_name' => 'Heading only', 'start_year' => 1900]];
    $expect(iss_register_render_place_context(['variant' => 'epoch_rail']) === '', 'Heading-only legacy epochs omitted');
    $entity['epochs'][0]['summary'] = 'An actual historical event.';
    $expect(str_contains(iss_register_render_place_context(['variant' => 'epoch_rail']), 'An actual historical event.'), 'Legacy epoch with narrative survives');
    $entity['epochs'][0]['source_links'] = ['https://example.com/source'];
    $expect(str_contains(iss_register_render_place_context(['variant' => 'epoch_rail']), 'https://example.com/source'), 'Legacy source links remain actionable without a summary');
    $entity['epochs'] = [];
    foreach (['intro', 'gegenwart', 'epoche', 'galerie', 'material'] as $type) {
        $doc = ['sections' => [['type' => $type, 'title' => 'Empty title', 'kicker' => 'Empty kicker', 'body' => '<p>&nbsp;</p>', 'media_refs_resolved' => [['resolved' => []]], 'object_refs_resolved' => [['resolved' => []]]]]];
        $expect(industriesalon_render_editorial_place_document($id, $doc) === '', 'Empty editorial section omitted: ' . $type);
        $doc['sections'][0]['body'] = '<p>Actual content.</p>';
        $expect(str_contains(industriesalon_render_editorial_place_document($id, $doc), 'Actual content.'), 'Populated editorial section survives: ' . $type);
    }
    $doc = ['sections' => [['type' => 'intro', 'media_refs_resolved' => [['reference' => ['label' => 'Real photo'], 'resolved' => ['id' => $media_id]]]]]];
    $expect($has(industriesalon_render_editorial_place_document($id, $doc), 'iss-register-place__epoch-image'), 'Image-only overview survives');
    $doc = ['sections' => [['type' => 'gegenwart', 'links' => [['label' => 'Visit website', 'url' => 'https://example.com/']]]]];
    $expect(str_contains(industriesalon_render_editorial_place_document($id, $doc), 'Visit website'), 'Links-only overview survives');
    $epoch = industriesalon_render_editorial_place_epoch(['title' => 'Actual epoch', 'body' => '<p>Real narrative.</p>', 'source_refs' => [['label' => 'Broken source'], ['url' => 'javascript:bad()']]]);
    $expect(!$has($epoch, 'iss-register-place__epoch-sources'), 'Invalid sources leave no empty disclosure');
    $set_meta(['history_short' => 'Legacy content must stay hidden', iss_editorial_get_enabled_meta_key('place') => '1', iss_editorial_get_document_meta_key('place') => iss_editorial_encode_document(iss_editorial_get_empty_document('place'))]);
    $expect(industriesalon_render_editorial_place_block() === '', 'Enabled empty document never revives legacy text');
    $mixed = ['sections' => [
        ['type' => 'intro', 'body' => '<p>Story marker.</p>'],
        ['type' => 'gegenwart', 'body' => '<p>Present marker.</p>'],
        ['type' => 'material', 'body' => '<p>Material marker.</p>'],
        ['type' => 'upload_intake', 'body' => '<p>Invitation marker.</p>'],
    ]];
    foreach (['story' => 'Story marker.', 'present' => 'Present marker.', 'supplements' => 'Material marker.'] as $slot => $text) {
        $slot_html = industriesalon_render_editorial_place_document($id, $mixed, $slot);
        $expect(str_contains($slot_html, $text) && substr_count($slot_html, 'marker.') === 1, 'Native dossier slot renders only its authored narrative: ' . $slot);
    }
    $set_meta(['_iss_upload_open' => '1']);
    $invitation_copy = industriesalon_render_editorial_place_document($id, $mixed, 'contribution');
    $expect(str_contains($invitation_copy, 'Invitation marker.') && !str_contains($invitation_copy, '<a'), 'Stored invitation prose contributes no second intake button');
    $automatic = ['type' => 'epoche', 'title' => 'Actual phase', 'start_year' => 1912, 'end_year' => 1945, 'body' => '<p>Existing history.</p>'];
    $gesture = ['type' => 'dynamic_slot', 'slot_key' => 'place-epoch-contribution', 'treatment' => 'slot.place-epoch-contribution'];
    $expect(!str_contains(industriesalon_render_editorial_place_epoch($automatic, $id), 'research-button'), 'Populated phases have no implicit invitation');
    $doc = ['sections' => [$automatic, $gesture]];
    $automatic_html = industriesalon_render_editorial_place_document($id, $doc, 'story');
    $expect(str_contains($automatic_html, 'Wissen Sie, was hier zwischen 1912 und 1945 geschah?'), 'Chosen dynamic gesture uses its preceding phase dates');
    $doc['sections'][] = array_replace($automatic, ['title' => 'Later phase', 'start_year' => 1950, 'end_year' => 1990]);
    $one = industriesalon_render_editorial_place_document($id, $doc, 'story');
    $expect(substr_count($one, 'research-button') === 1 && !str_contains($one, 'zwischen 1950 und 1990'), 'Gesture stays with one chosen phase without inviting every epoch');
    $doc['sections'][] = $gesture;
    $expect(substr_count(industriesalon_render_editorial_place_document($id, $doc, 'story'), 'research-button') === 2, 'A second explicit gesture enables another phase');
    $doc['sections'][1]['title'] = 'Who remembers the former workshop?';
    $expect(str_contains(industriesalon_render_editorial_place_document($id, $doc, 'story'), 'Who remembers the former workshop?'), 'Gesture title overrides the generated question');
    $doc['sections'] = [$gesture, $automatic];
    $expect(!str_contains(industriesalon_render_editorial_place_document($id, $doc), 'research-button'), 'An orphan gesture cannot invent its phase context');
    unset($automatic['body']);
    $expect(industriesalon_render_editorial_place_epoch($automatic, $id) === '', 'An empty dated phase has no implicit invitation');
    $entity['epochs'] = [['phase_name' => 'Legacy phase', 'summary' => 'Existing history', 'start_year' => 1912, 'end_year' => 1945]];
    $expect(!str_contains(iss_register_render_place_context(['variant' => 'epoch_rail']), 'research-button'), 'Legacy phase has no implicit invitation');
    $entity['epochs'] = [];
    $gap = ['type' => 'epoche', 'title' => 'Research gap', 'start_year' => 1945, 'contribution_question' => 'Retained old question'];
    $expect(industriesalon_render_editorial_place_epoch($gap, $id) === '', 'Retained epoch field does not activate a canonical gesture');
    $gap_doc = ['sections' => [$gap, array_replace($gesture, ['title' => 'What happened here?'])]];
    $expect($has(industriesalon_render_editorial_place_document($id, $gap_doc), 'iss-register-place__research-button'), 'Explicit gesture makes a named research gap useful while intake is open');
    $set_meta([]);
    $expect(industriesalon_render_editorial_place_document($id, $gap_doc) === '', 'Closed gesture does not leave an empty phase');
    $set_meta([]);
    $entity['archive_images'] = [$image];
    $single = iss_register_render_place_context(['variant' => 'then_now']);
    $expect($has($single, 'iss-register-place__comparison-grid--single') && substr_count($single, '<figure') === 1, 'One archival image renders once');
    $entity['current_images'] = [$image];
    $expect(substr_count(iss_register_render_place_context(['variant' => 'then_now']), '<figure') === 1, 'Same attachment never creates a false comparison');
    $entity['archive_images'] = [['media_id' => $id, 'is_featured' => true], $image];
    $entity['current_images'] = [];
    $expect(substr_count(iss_register_render_place_context(['variant' => 'then_now']), '<figure') === 1, 'Missing preferred attachment does not mask usable imagery');
    $entity['archive_images'] = [];
    $entity['current_images'] = [$image];
    $expect(substr_count(iss_register_render_place_context(['variant' => 'then_now']), '<figure') === 1, 'One current image survives');
    $other_place = get_page_by_path('ostendstrasse-1-5-behrensbau', OBJECT, 'register_place');
    $other_media_id = $other_place ? (int) get_post_thumbnail_id($other_place->ID) : 0;
    $expect($other_media_id !== $media_id && wp_attachment_is_image($other_media_id), 'Second existing photograph available');
    $refs = array_map(static fn(int $image_id): array => ['reference' => ['label' => 'Epoch image'], 'resolved' => ['id' => $image_id]], [$media_id, $other_media_id]);
    $epoch_media = industriesalon_render_editorial_place_epoch(['title' => 'Two photographs', 'media_refs_resolved' => $refs]);
    $expect(substr_count($epoch_media, '<figure') === 2 && $has($epoch_media, 'iss-register-place__epoch-media'), 'JSON epoch shows every selected photograph in one media column');
    $set_meta(['archive_images' => [array_replace($image, ['visibility' => 'private'])]]);
    $epoch_images = iss_register_get_place_context_epoch_images(['media_ids' => [$media_id, $other_media_id, $id]], $id);
    $expect(count($epoch_images) === 1 && (int) $epoch_images[0]['media_id'] === $other_media_id, 'Legacy epoch excludes private and unresolved photographs');
    $set_meta(['archive_images' => [$image]]);
    $epoch_images = iss_register_get_place_context_epoch_images(['media_ids' => [$media_id, $other_media_id, $media_id]], $id);
    $expect(count($epoch_images) === 2 && (int) $epoch_images[0]['media_id'] === $media_id, 'Legacy epoch preserves selected photo order and deduplicates');
    $set_meta([]);
    $entity['archive_images'] = [$image];
    $entity['current_images'] = [array_replace($image, ['media_id' => $other_media_id])];
    $pair = iss_register_render_place_context(['variant' => 'then_now']);
    $expect(substr_count($pair, '<figure') === 2 && !$has($pair, 'iss-register-place__comparison-grid--single'), 'Two distinct usable images keep comparison layout');
    $expect(str_contains($pair, 'Archiv und spätere Ansicht'), 'Paired imagery keeps its dated comparison heading');
    $expect(iss_register_filter_public_images([array_replace($image, ['visibility' => 'private'])]) === [], 'Existing image owner excludes private imagery');
    $entity['archive_images'] = [];
    $entity['current_images'] = [];
    $expect(iss_register_render_place_context(['variant' => 'then_now']) === '', 'No usable imagery leaves no comparison heading');
    $post->post_type = 'post';
    $ordinary = '<section class="iss-register-place-contribution"><h2>Other page</h2></section>';
    $expect(industriesalon_suppress_empty_place_groups($ordinary, ['attrs' => ['className' => 'iss-register-place-contribution']]) === $ordinary, 'Other CPT output unchanged');
} finally {
    remove_filter('query', $guard);
    remove_filter('pre_transient_iss_register_places_cache', $supply);
    remove_filter('posts_pre_query', $no_posts);
    wp_cache_delete($id, 'posts');
    wp_cache_delete($id, 'post_meta');
    $wp_query = $old_query;
    $post = $old_post;
}
WP_CLI::success("Place optional sections: $checks checks passed; no database records changed.");
