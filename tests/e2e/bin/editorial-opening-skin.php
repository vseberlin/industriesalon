<?php
/** wp eval-file: opening capability is owner-declared and eligibility stays live. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($message); }
    ++$checks;
};
$front_id = 900001;
$front_option = static function () use (&$front_id): int { return $front_id; };
$builds = 0;
$count_builds = static function (array $formats) use (&$builds): array { ++$builds; return $formats; };
$probe = null; $fault = null;
add_filter('pre_option_page_on_front', $front_option);
add_filter('iss_editorial_formats', $count_builds, 200);
iss_editorial_reset_format_cache();
try {
    $front = new WP_Post((object) ['ID' => 900001, 'post_type' => 'page', 'post_name' => 'about', 'post_status' => 'publish']);
    $other = new WP_Post((object) ['ID' => 900002, 'post_type' => 'page', 'post_name' => 'verein', 'post_status' => 'draft']);
    $project = new WP_Post((object) ['ID' => 900003, 'post_type' => 'projekt', 'post_name' => 'opening-probe', 'post_status' => 'draft']);
    $formats = iss_editorial_get_registered_formats();
    $landing_skins = array_column(iss_editorial_get_format_skins('landing'), 'slug');
    foreach ($formats as $slug => $format) {
        $expected = ['landing' => $landing_skins, 'projekt' => ['buehne']][$slug] ?? [];
        $assert($format['opening_skins'] === $expected, "$slug declares its opening skins");
    }
    $assert(iss_editorial_get_opening_skins($formats['landing'], $front) === $landing_skins, 'Homepage supports all landing skins');
    $assert(iss_editorial_get_opening_skins($formats['landing'], $other) === $landing_skins, 'Other landings share the opening contract');
    $assert(iss_editorial_get_opening_skins($formats['projekt'], $project) === ['buehne'], 'Projects retain their stage constraint');
    $assert(iss_editorial_get_opening_skins([], $front) === [], 'Undeclared capability defaults to absent');
    $front_id = 0;
    $cached = iss_editorial_get_registered_formats();
    $assert($builds === 1 && $cached === $formats, 'Registry remains cached');
    $assert(iss_editorial_get_opening_skins($cached['landing'], $other) === $landing_skins, 'Landing capability is independent of homepage selection');

    $eligible = false; $calls = [];
    $probe = static function (array $formats) use (&$eligible, &$calls): array {
        $formats['renamed_opening'] = $formats['projekt'];
        $formats['renamed_opening']['opening_skins'] = ['STANDARD', 'typografisch', 'STANDARD'];
        $formats['renamed_opening']['opening_eligibility_callback'] = static function (WP_Post $post, array $format) use (&$eligible, &$calls): bool {
            $calls[] = [$post->ID, $format['slug']];
            return $eligible;
        };
        // Historical format names must not override the new owner declaration.
        $formats['projekt']['opening_skins'] = [];
        $formats['landing']['opening_skins'] = ['typografisch'];
        unset($formats['landing']['opening_eligibility_callback']);
        return $formats;
    };
    add_filter('iss_editorial_formats', $probe, 150); iss_editorial_reset_format_cache();
    $renamed = iss_editorial_get_format('renamed_opening');
    $assert($renamed['opening_skins'] === ['standard', 'typografisch'], 'Declared skin is normalized');
    $assert(iss_editorial_get_opening_skins($renamed, $project) === [], 'Owner callback can deny a renamed format');
    $eligible = true;
    $assert(iss_editorial_get_opening_skins($renamed, $other) === ['standard', 'typografisch'], 'Same cached definition observes live eligibility');
    $assert($calls === [[900003, 'renamed_opening'], [900002, 'renamed_opening']], 'Callback receives the current post and normalized format on each resolution');
    $assert(iss_editorial_get_opening_skins(iss_editorial_get_format('projekt'), $project) === [], 'Project name cannot force an opening');
    $assert(iss_editorial_get_opening_skins(iss_editorial_get_format('landing'), $other) === ['typografisch'], 'Landing name cannot force skin or eligibility');
    $assert(iss_editorial_registry_errors() === [], 'Renamed owner declarations are valid');
    foreach ([
        [['opening_skins' => ['standard', 'unknown']], 'unknown opening skin'],
        [['opening_eligibility_callback' => 'missing_opening_callback'], 'invalid opening eligibility callback'],
    ] as [$override, $message]) {
        $fault = static function (array $formats) use ($override): array {
            $formats['renamed_opening'] = array_replace($formats['renamed_opening'], $override);
            return $formats;
        };
        add_filter('iss_editorial_formats', $fault, 160); iss_editorial_reset_format_cache();
        $assert(in_array('renamed_opening: ' . $message, iss_editorial_registry_errors(), true), 'Registry diagnoses ' . $message);
        remove_filter('iss_editorial_formats', $fault, 160); $fault = null; iss_editorial_reset_format_cache();
    }
} finally {
    if ($fault) { remove_filter('iss_editorial_formats', $fault, 160); }
    if ($probe) { remove_filter('iss_editorial_formats', $probe, 150); }
    remove_filter('iss_editorial_formats', $count_builds, 200);
    remove_filter('pre_option_page_on_front', $front_option);
    iss_editorial_reset_format_cache();
}
$assert(iss_editorial_registry_errors() === [], 'Original registry restored');
$images = get_posts(['post_type' => 'attachment', 'post_mime_type' => 'image', 'posts_per_page' => 1, 'fields' => 'ids']);
$assert(!empty($images), 'An existing image is available without creating media');
$document = ['schema_version' => 3, 'skin' => 'standard', 'sections' => [
    ['type' => 'feature', 'treatment' => 'feature.opening', 'title' => 'Portable opening', 'media_refs' => [['kind' => 'media', 'source' => 'wp-media', 'id' => (string) $images[0]]]],
    ['type' => 'statement', 'title' => 'Body section'],
]];
foreach (array_column(iss_editorial_get_format_skins('landing'), 'slug') as $skin) {
    $document['skin'] = $skin;
    $assert(!is_wp_error(iss_editorial_validate_document($document, 'landing')), "$skin accepts the opening");
    $html = industriesalon_editorial_landing_render_document($document);
    $assert(substr_count($html, '<h1') === 1 && str_contains($html, 'Portable opening'), "$skin renders one opening H1 outside the homepage");
    $assert(!str_contains(industriesalon_editorial_landing_render_document($document, 1), '<h1'), 'A partition after the opening never repeats its heading');
}
foreach (['title', 'image', 'position', 'duplicate', 'skin'] as $fault) {
    $invalid = $document;
    if ($fault === 'title') { $invalid['sections'][0]['title'] = ''; }
    elseif ($fault === 'image') { $invalid['sections'][0]['media_refs'] = []; }
    elseif ($fault === 'position') { $invalid['sections'] = array_reverse($invalid['sections']); }
    elseif ($fault === 'duplicate') { $invalid['sections'][] = $invalid['sections'][0]; }
    else { $invalid['skin'] = 'unknown'; }
    $assert(is_wp_error(iss_editorial_validate_document($invalid, 'landing')), "Reject invalid opening: $fault");
}

// Render a deterministic notice through the real source block without publishing fixtures.
$notice_visible = true;
$notices_in_hero = static function (string $html): int {
    $dom = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors(); libxml_use_internal_errors($previous);
    return (new DOMXPath($dom))->query('//div[contains(concat(" ", normalize-space(@class), " "), " iss-front-hero ")]//*[@data-banner-probe]')->length;
};
$notice_probe = static function ($html, array $block) use (&$notice_visible) {
    return ($block['blockName'] ?? '') === 'industriesalon/notice-banner'
        ? ($notice_visible ? '<aside data-banner-probe="1">Current notice</aside>' : '') : $html;
};
add_filter('pre_render_block', $notice_probe, 10, 2);
try {
    $with_notice = $document;
    $notice = ['type' => 'dynamic_slot', 'slot_key' => 'notice', 'treatment' => 'slot.notice', 'anchor' => 'notice-anchor', 'title' => 'Notice context', 'body' => '<p><strong>Retained introduction</strong></p>'];
    array_splice($with_notice['sections'], 1, 0, [$notice]);
    $before = wp_json_encode($with_notice);
    $html = industriesalon_editorial_landing_render_document($with_notice);
    $assert(substr_count($html, 'data-banner-probe') === 1, 'Hero notice is rendered once');
    $assert($notices_in_hero($html) === 1, 'Adjacent notice is inside the image hero');
    $assert(str_contains($html, 'iss-front-banner-slot') && str_contains($html, 'id="notice-anchor"') && str_contains($html, 'Notice context'), 'Hero retains existing banner layout, anchor and editorial copy');
    $assert(str_contains($html, '<strong>Retained introduction</strong>'), 'Hero notice keeps the document rich-text contract');
    $assert(wp_json_encode($with_notice) === $before, 'Composition never mutates the saved section order or content');
    $assert(!str_contains(industriesalon_editorial_landing_render_document($with_notice, 0, 1), 'data-banner-probe'), 'A partition containing only the hero does not consume another section');
    $html = industriesalon_editorial_landing_render_document($with_notice, 1, 1);
    $assert(substr_count($html, 'data-banner-probe') === 1 && !str_contains($html, 'iss-front-banner-slot'), 'A separate notice partition retains its content');
    $text_opening = $with_notice;
    $text_opening['sections'][0]['treatment'] = 'feature.opening-text';
    $html = industriesalon_editorial_landing_render_document($text_opening);
    $assert(substr_count($html, 'data-banner-probe') === 1 && !str_contains($html, 'iss-front-banner-slot'), 'Text-only openings keep the notice in the page flow');
    $later_notice = $document; $later_notice['sections'][] = $notice;
    $html = industriesalon_editorial_landing_render_document($later_notice);
    $assert(substr_count($html, 'data-banner-probe') === 1 && $notices_in_hero($html) === 0, 'A notice placed later remains in its chosen position');
    $notice_visible = false;
    $html = industriesalon_editorial_landing_render_document($with_notice);
    $assert(!str_contains($html, 'iss-front-banner-slot') && !str_contains($html, 'Notice context'), 'Inactive notices leave no empty banner or duplicate section');
    $assert(isset(iss_editorial_get_format('landing')['sections']['dynamic_slot']['slots']['notice']['settings']['area']['choices']['front_page_banner']), 'Homepage notice selector uses the notice owner area key');
} finally {
    remove_filter('pre_render_block', $notice_probe, 10);
}
$legacy = $document; $legacy['schema_version'] = 2; $legacy['skin'] = 'dossier';
$legacy['sections'][0]['treatment'] = 'feature.image-overlay';
$assert(!industriesalon_landing_has_opening($legacy), 'Legacy non-home overlay is not promoted to an opening');

// Exercise actual template selection with request-local metadata; no content writes.
$original_query = $GLOBALS['wp_query'];
$original_main_query = $GLOBALS['wp_the_query'];
$page = get_page_by_path('salon-vermietung');
$assert($page instanceof WP_Post, 'Existing rental page is available for read-only route checks');
$enabled = '1';
$meta = static function ($value, $id, $key) use ($page, &$document, &$enabled) {
    if ((int) $id !== (int) $page->ID) { return $value; }
    if ($key === iss_editorial_get_document_meta_key('landing')) { return wp_json_encode($document); }
    if ($key === iss_editorial_get_enabled_meta_key('landing')) { return $enabled; }
    return $value;
};
add_filter('get_post_metadata', $meta, 10, 3);
try {
    $GLOBALS['wp_query'] = new WP_Query(['page_id' => $page->ID]);
    $GLOBALS['wp_the_query'] = $GLOBALS['wp_query'];
    $candidates = ['page-salon-vermietung.php', 'page.php'];
    $assert(apply_filters('page_template_hierarchy', $candidates)[0] === 'page-editorial-landing.php', 'Valid opening selects shared template ahead of legacy template');
    get_page_template();
    $assert(($GLOBALS['_wp_current_template_id'] ?? '') === 'industriesalon//page-editorial-landing', 'WordPress resolves the file-backed shared block template');
    $template = get_block_template('industriesalon//page-editorial-landing', 'wp_template');
    $assert($template && $template->source === 'theme', 'Shared template has file authority');
    $html = do_blocks($template->content);
    $assert(substr_count($html, '<h1') === 1 && str_contains($html, 'Body section'), 'Complete shared template renders opening and body exactly once');
    $enabled = '0';
    $assert(apply_filters('page_template_hierarchy', $candidates) === $candidates, 'Disabled document keeps original template');
    $enabled = '1'; $document['sections'][0]['title'] = '';
    $assert(apply_filters('page_template_hierarchy', $candidates) === $candidates, 'Incomplete opening keeps original template');
} finally {
    remove_filter('get_post_metadata', $meta, 10);
    $GLOBALS['wp_query'] = $original_query;
    $GLOBALS['wp_the_query'] = $original_main_query;
}
WP_CLI::success("$checks opening-skin contract checks; no persistent fixtures.");
