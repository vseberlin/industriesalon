<?php
/** Read-only acceptance of the visitor copy, periodisation and layout contracts. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
$checks = 0;
$expect = static function (bool $ok, string $message) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException(esc_html($message)); }
};
$expect(defined('DISABLE_WP_CRON') && DISABLE_WP_CRON, 'Cron disabled.');
$eras = array_values(iss_register_get_atlas_era_definitions());
$expect(count($eras) === 6 && count(array_unique(array_column($eras, 'legacy_id'))) === 6, 'Six eras retain distinct legacy map identities.');
$collections = get_block_template('industriesalon//page-sammlungen', 'wp_template');
$expect($collections && $collections->source === 'theme', 'Collection template authority.');
$blocks = parse_blocks($collections->content);
$expect(parse_blocks(serialize_blocks($blocks)) === $blocks, 'Collection Gutenberg roundtrip.');
$freeform = [];
$inspect_blocks = static function (array $items) use (&$inspect_blocks, &$freeform): void {
    foreach ($items as $block) {
        if (empty($block['blockName']) && trim($block['innerHTML']) !== '') { $freeform[] = $block['innerHTML']; }
        $inspect_blocks($block['innerBlocks']);
    }
};
$inspect_blocks($blocks);
$expect(!array_filter($freeform, static fn(string $html): bool => str_contains($html, '<!-- /wp:')), 'No orphan closing blocks.');
$place = get_page_by_path('schoneweide', OBJECT, 'page');
$doc = iss_editorial_get_document($place->ID, 'landing', false);
$timeline = array_values(array_filter($doc['sections'], static fn(array $section): bool => ($section['anchor'] ?? '') === 'zeitschichten'))[0];
$expect(count($timeline['items']) === 6, 'Six visitor timeline cards.');
foreach ($eras as $index => $era) {
    $expect($timeline['items'][$index]['label'] === $era['name'] . ' · ' . $era['period_label'], 'Timeline follows the Atlas era registry.');
    $expect(str_contains($collections->content, '>' . $era['name'] . '</h3>') && str_contains($collections->content, '>' . $era['period_label'] . '</p>'), 'Collection period matches.');
}
$home = get_page_by_path('home-2', OBJECT, 'page');
$doc = iss_editorial_get_document($home->ID, 'landing', false);
$archive = array_values(array_filter($doc['sections'], static fn(array $section): bool => ($section['anchor'] ?? '') === 'archiv-wissen'))[0];
foreach ($archive['items'] as $item) { $expect(trim($item['text']) !== '' && str_ends_with($item['text'], '.'), 'Every homepage archive tile has a complete teaser.'); }
$tours = get_page_by_path('fuehrungen', OBJECT, 'page');
$doc = iss_editorial_get_document($tours->ID, 'landing', false);
$links = array_values(array_filter($doc['sections'], static fn(array $section): bool => ($section['anchor'] ?? '') === 'weiterlesen'))[0]['items'];
foreach ($links as $item) {
    $expect($item['url'] !== '/fuehrungen/' && strlen($item['label']) > 3 && $item['text'] !== '', 'Tour follow-up link names a useful destination.');
}
$newsletter = industriesalon_render_front_newsletter_slot();
$expect(str_contains($newsletter, 'tnp-privacy') && str_contains($newsletter, 'required'), 'Newsletter still requires explicit consent.');
$expect(!str_contains($newsletter, 'Mit der Eingabe meiner'), 'No duplicate implied consent.');
$hero = get_block_template('industriesalon//page-salon-vermietung', 'wp_template');
$expect(str_contains($hero->content, 'iss-front-hero__actions-wrap'), 'Hero buttons retain their separate established position.');
$expect(!str_contains($collections->content, '>Öffnen</a>') && !str_contains($collections->content, 'Korpus'), 'Collection links use visitor wording.');
$gallery = array_values(array_filter($doc['sections'], static fn(array $section): bool => ($section['type'] ?? '') === 'galerie'))[0];
$html = industriesalon_render_editorial_landing_gallery($gallery, 0, 'standard');
$expect(str_contains($html, 'tabindex="0" role="region"') && !str_contains($html, 'data-iss-strip-carousel'), 'Populated image sequence is keyboard accessible with one native scrolling mechanism.');
$women = get_page_by_path('frauen-in-werk', OBJECT, 'ausstellung');
$expect($women instanceof WP_Post && $women->post_status === 'publish' && str_contains($collections->content, '/ausstellungen/frauen-in-werk/') && !str_contains($collections->content, '/ausstellungen/frauen-im-wf/'), 'Collection links reach the published women exhibition.');
echo 'Passed ' . (int) $checks . " audit polish checks.\n";
