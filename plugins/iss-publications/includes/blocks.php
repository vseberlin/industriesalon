<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_block_type')) {
        return;
    }

    $featured_dir = ISS_PUBLICATIONS_PATH . 'blocks/featured-publication';
    if (file_exists($featured_dir . '/block.json')) {
        register_block_type($featured_dir, [
            'render_callback' => 'iss_publications_render_featured_block',
        ]);
    }

    $grid_dir = ISS_PUBLICATIONS_PATH . 'blocks/publications-grid';
    if (file_exists($grid_dir . '/block.json')) {
        register_block_type($grid_dir, [
            'render_callback' => 'iss_publications_render_grid_block',
        ]);
    }

    $browser_dir = ISS_PUBLICATIONS_PATH . 'blocks/publications-browser';
    if (file_exists($browser_dir . '/block.json')) {
        register_block_type($browser_dir, [
            'render_callback' => 'iss_publications_render_browser_block',
        ]);
    }

    $browser_sidebar_dir = ISS_PUBLICATIONS_PATH . 'blocks/publications-browser-sidebar';
    if (file_exists($browser_sidebar_dir . '/block.json')) {
        register_block_type($browser_sidebar_dir);
    }

    $browser_results_dir = ISS_PUBLICATIONS_PATH . 'blocks/publications-browser-results';
    if (file_exists($browser_results_dir . '/block.json')) {
        register_block_type($browser_results_dir);
    }

    $browser_format_dir = ISS_PUBLICATIONS_PATH . 'blocks/publications-browser-format';
    if (file_exists($browser_format_dir . '/block.json')) {
        register_block_type($browser_format_dir);
    }

    $order_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-order-panel';
    if (file_exists($order_dir . '/block.json')) {
        register_block_type($order_dir, [
            'render_callback' => 'iss_publications_render_order_panel_block',
        ]);
    }

    $meta_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-meta';
    if (file_exists($meta_dir . '/block.json')) {
        register_block_type($meta_dir, [
            'render_callback' => 'iss_publications_render_meta_block',
        ]);
    }

    $summary_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-essay-summary';
    if (file_exists($summary_dir . '/block.json')) {
        register_block_type($summary_dir, [
            'render_callback' => 'iss_publications_render_essay_summary_block',
        ]);
    }

    $nav_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-essay-nav';
    if (file_exists($nav_dir . '/block.json')) {
        register_block_type($nav_dir, [
            'render_callback' => 'iss_publications_render_essay_nav_block',
        ]);
    }

    $bridge_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-essay-bridge';
    if (file_exists($bridge_dir . '/block.json')) {
        register_block_type($bridge_dir, [
            'render_callback' => 'iss_publications_render_essay_bridge_block',
        ]);
    }

    $longread_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-longread';
    if (file_exists($longread_dir . '/block.json')) {
        register_block_type($longread_dir, [
            'render_callback' => 'iss_publications_render_longread_block',
        ]);
    }

    $longread_chapter_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-longread-chapter';
    if (file_exists($longread_chapter_dir . '/block.json')) {
        register_block_type($longread_chapter_dir);
    }

    $longread_related_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-longread-related';
    if (file_exists($longread_related_dir . '/block.json')) {
        register_block_type($longread_related_dir, [
            'render_callback' => 'iss_publications_render_longread_related_block',
        ]);
    }

    $photoalbum_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-photoalbum';
    if (file_exists($photoalbum_dir . '/block.json')) {
        register_block_type($photoalbum_dir, [
            'render_callback' => 'iss_publications_render_photoalbum_block',
        ]);
    }

    $photoalbum_sheet_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-photoalbum-sheet';
    if (file_exists($photoalbum_sheet_dir . '/block.json')) {
        register_block_type($photoalbum_sheet_dir);
    }

    $timeline_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-timeline';
    if (file_exists($timeline_dir . '/block.json')) {
        register_block_type($timeline_dir, [
            'render_callback' => 'iss_publications_render_timeline_block',
        ]);
    }

    $timeline_item_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-timeline-item';
    if (file_exists($timeline_item_dir . '/block.json')) {
        register_block_type($timeline_item_dir);
    }

    if (function_exists('register_block_pattern_category')) {
        register_block_pattern_category('iss-publications', [
            'label' => __('Publikationen', 'iss-publications'),
        ]);
    }

    if (function_exists('register_block_pattern')) {
        register_block_pattern('iss-publications/timeline-starter', [
            'title' => __('Publikations-Zeitleiste (Starter)', 'iss-publications'),
            'description' => __('Starter pattern for timeline publications with dated stations.', 'iss-publications'),
            'categories' => ['iss-publications', 'text'],
            'content' => implode("\n", [
                '<!-- wp:iss/publication-timeline {"sourceNote":"Optionale Quellen- oder Redaktionsnotiz."} -->',
                '<p class="iss-publication-source-note">Optionale Quellen- oder Redaktionsnotiz.</p>',
                '<div class="wp-block-group iss-publication-chronicle">',
                '<!-- wp:iss/publication-timeline-item {"year":"1900","title":"Erste Station"} -->',
                '<div class="wp-block-group iss-publication-chronicle__item">',
                '<p class="iss-publication-chronicle__year">1900</p>',
                '<div class="wp-block-group iss-publication-chronicle__card">',
                '<h3 class="wp-block-heading iss-publication-chronicle__title">Erste Station</h3>',
                '<div class="wp-block-group iss-publication-chronicle__text">',
                '<!-- wp:paragraph -->',
                '<p>Kurze Einordnung, Quelle oder Entwicklungsbeschreibung.</p>',
                '<!-- /wp:paragraph -->',
                '</div>',
                '</div>',
                '</div>',
                '<!-- /wp:iss/publication-timeline-item -->',
                '<!-- wp:iss/publication-timeline-item {"year":"1915","title":"Zweite Station"} -->',
                '<div class="wp-block-group iss-publication-chronicle__item">',
                '<p class="iss-publication-chronicle__year">1915</p>',
                '<div class="wp-block-group iss-publication-chronicle__card">',
                '<h3 class="wp-block-heading iss-publication-chronicle__title">Zweite Station</h3>',
                '<div class="wp-block-group iss-publication-chronicle__text">',
                '<!-- wp:paragraph -->',
                '<p>Weiteren Kontext, Bruch oder technische Entwicklung ergänzen.</p>',
                '<!-- /wp:paragraph -->',
                '</div>',
                '</div>',
                '</div>',
                '<!-- /wp:iss/publication-timeline-item -->',
                '</div>',
                '<!-- /wp:iss/publication-timeline -->',
            ]),
        ]);

        register_block_pattern('iss-publications/longread-starter', [
            'title' => __('Publikations-Longread (Starter)', 'iss-publications'),
            'description' => __('Starter pattern for chaptered longread publications with generated rail and chapter navigation.', 'iss-publications'),
            'categories' => ['iss-publications', 'text'],
            'content' => implode("\n", [
                '<!-- wp:iss/publication-longread -->',
                '<div class="iss-publication-longread-source">',
                '<div class="iss-publication-longread-source__intro"><p>Kurze Einleitung des Longreads. Sie setzt Thema, Zeitraum und Leseperspektive.</p></div>',
                '<div class="iss-publication-longread-source__chapters">',
                '<!-- wp:iss/publication-longread-chapter {"title":"Erstes Kapitel","anchor":"erstes-kapitel"} -->',
                '<section class="iss-publication-longread-source__chapter">',
                '<h2 id="erstes-kapitel" class="wp-block-heading iss-publication-longread-source__chapter-title">Erstes Kapitel</h2>',
                '<div class="iss-publication-longread-source__chapter-body">',
                '<!-- wp:paragraph -->',
                '<p>Kapiteltext schreiben und bei Bedarf Zwischenueberschriften, Bilder, Listen oder Karten einfuegen.</p>',
                '<!-- /wp:paragraph -->',
                '</div>',
                '</section>',
                '<!-- /wp:iss/publication-longread-chapter -->',
                '<!-- wp:iss/publication-longread-chapter {"title":"Zweites Kapitel","anchor":"zweites-kapitel"} -->',
                '<section class="iss-publication-longread-source__chapter">',
                '<h2 id="zweites-kapitel" class="wp-block-heading iss-publication-longread-source__chapter-title">Zweites Kapitel</h2>',
                '<div class="iss-publication-longread-source__chapter-body">',
                '<!-- wp:paragraph -->',
                '<p>Weiteres Kapitel ergaenzen. Die Navigation entsteht automatisch aus den Kapiteltiteln.</p>',
                '<!-- /wp:paragraph -->',
                '</div>',
                '</section>',
                '<!-- /wp:iss/publication-longread-chapter -->',
                '</div>',
                '</div>',
                '<!-- /wp:iss/publication-longread -->',
            ]),
        ]);

        register_block_pattern('iss-publications/photoalbum-starter', [
            'title' => __('Publikations-Fotoalbum (Starter)', 'iss-publications'),
            'description' => __('Starter pattern for photoalbum publications. Each sheet needs an image before the generated album rail appears.', 'iss-publications'),
            'categories' => ['iss-publications', 'media'],
            'content' => implode("\n", [
                '<!-- wp:iss/publication-photoalbum {"sourceNote":"Optionale Quellen- oder Redaktionsnotiz.","introHtml":"<p>Kurze Einleitung des Fotoalbums. Sie setzt Herkunft, Zeitraum und Leseperspektive.</p>"} -->',
                '<div class="iss-publication-photoalbum-source">',
                '<p class="iss-publication-source-note">Optionale Quellen- oder Redaktionsnotiz.</p>',
                '<div class="iss-publication-photoalbum-source__intro"><p>Kurze Einleitung des Fotoalbums. Sie setzt Herkunft, Zeitraum und Leseperspektive.</p></div>',
                '<div class="iss-publication-photoalbum-source__sheets">',
                '<!-- wp:iss/publication-photoalbum-sheet {"sheetLabel":"Blatt 01","title":"Erstes Blatt","caption":"Kurzbeschreibung, Transkription oder Quellenhinweis zum ersten Blatt."} /-->',
                '<!-- wp:iss/publication-photoalbum-sheet {"sheetLabel":"Blatt 02","title":"Zweites Blatt","caption":"Kurzbeschreibung, Transkription oder Quellenhinweis zum zweiten Blatt."} /-->',
                '<!-- wp:iss/publication-photoalbum-sheet {"sheetLabel":"Blatt 03","title":"Drittes Blatt","caption":"Kurzbeschreibung, Transkription oder Quellenhinweis zum dritten Blatt."} /-->',
                '</div>',
                '</div>',
                '<!-- /wp:iss/publication-photoalbum -->',
            ]),
        ]);
    }
});
