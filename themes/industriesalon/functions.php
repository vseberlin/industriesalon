<?php
if (!defined('ABSPATH')) {
    exit;
}

$industriesalon_fuehrungen_filters_helper = get_stylesheet_directory() . '/assets/css/staging/industriesalon-fuehrungen-filters.php';
if (file_exists($industriesalon_fuehrungen_filters_helper)) {
    require_once $industriesalon_fuehrungen_filters_helper;
}

function industriesalon_expected_render_helpers(): array
{
    return [
        'publications' => '/includes/publications-render.php',
        'ausstellungen' => '/includes/ausstellungen-render.php',
        'projects' => '/includes/projects-render.php',
        'tours' => '/includes/tours-render.php',
        'veranstaltungen' => '/includes/veranstaltungen-render.php',
        'archive' => '/includes/archive-render.php',
        'landings' => '/includes/landings-render.php',
    ];
}

$GLOBALS['industriesalon_missing_render_helpers'] = [];
foreach (industriesalon_expected_render_helpers() as $industriesalon_helper_key => $industriesalon_helper_path) {
    $industriesalon_helper_file = get_stylesheet_directory() . $industriesalon_helper_path;
    if (file_exists($industriesalon_helper_file)) {
        require_once $industriesalon_helper_file;
        continue;
    }

    $GLOBALS['industriesalon_missing_render_helpers'][$industriesalon_helper_key] = $industriesalon_helper_path;
}

function industriesalon_missing_render_helpers(): array
{
    $missing = $GLOBALS['industriesalon_missing_render_helpers'] ?? [];
    return is_array($missing) ? $missing : [];
}

function industriesalon_render_missing_helper_notice(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $missing = industriesalon_missing_render_helpers();
    if (!$missing) {
        return;
    }

    echo '<div class="notice notice-error"><p>';
    echo esc_html__('Industriesalon theme render helpers are missing:', 'industriesalon');
    foreach (array_values($missing) as $relative_path) {
        echo ' <code>' . esc_html($relative_path) . '</code>';
    }
    echo '</p></div>';
}
add_action('admin_notices', 'industriesalon_render_missing_helper_notice');

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('editor-styles');
    add_post_type_support('page', 'excerpt');
    add_editor_style(
        array(
            'style.css',
            'assets/css/front-page.css',
            'assets/css/cards.css',
            'assets/css/patterns.css',
            'assets/css/gestures.css',
            'assets/css/ueber-uns.css',
            'assets/css/page-archive.css',
            'assets/css/page-kalender.css',
            'assets/css/page-sammlungen.css',
            'assets/css/page-fuehrungen.css',
            'assets/css/page-events.css',
            'assets/css/page-projekte.css',
            'assets/css/page-salon-vermietung.css',
            'assets/css/page-videos.css',
            'assets/css/page-ausstellungen.css',
            'assets/css/page-verein.css',
            'assets/css/publications.css',
            'assets/css/single-tour.css',
            'assets/css/single-ausstellung.css',
            'assets/css/single-event.css',
            'assets/css/single-content.css',
            'assets/css/single-video.css',
        )
    );
});

add_action('init', function (): void {
    foreach (
        [
            'iss-list-plain' => __('Plain list', 'industriesalon'),
            'iss-list-rail' => __('Rail list', 'industriesalon'),
            'iss-list-steps' => __('Steps', 'industriesalon'),
            'iss-list-facts' => __('Facts', 'industriesalon'),
        ] as $name => $label
    ) {
        register_block_style(
            'core/list',
            [
                'name' => $name,
                'label' => $label,
            ]
        );
    }
});

add_action('enqueue_block_editor_assets', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post') {
        return;
    }

    $path = get_stylesheet_directory() . '/assets/js/editor-block-cleanup.js';
    if (!file_exists($path)) {
        return;
    }

    wp_enqueue_script(
        'industriesalon-editor-block-cleanup',
        get_stylesheet_directory_uri() . '/assets/js/editor-block-cleanup.js',
        ['wp-blocks', 'wp-dom-ready'],
        (string) filemtime($path),
        true
    );
});

add_filter('get_site_icon_url', function ($url, $size, $blog_id) {
    $favicon_path = get_stylesheet_directory() . '/assets/img/logo-industriesalon-favicon.svg';
    if (!file_exists($favicon_path)) {
        return $url;
    }

    return get_stylesheet_directory_uri() . '/assets/img/logo-industriesalon-favicon.svg';
}, 10, 3);

add_action('template_redirect', function (): void {
    if (!is_404()) {
        return;
    }

    $request_path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($request_path)) {
        return;
    }

    $normalized_path = untrailingslashit($request_path);
    if ($normalized_path !== '/ueber-uns') {
        return;
    }

    wp_safe_redirect(home_url('/about/'), 301);
    exit;
});

add_action('template_redirect', function (): void {
    if (!is_404()) {
        return;
    }

    $request_path = wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (!is_string($request_path)) {
        return;
    }

    $normalized_path = untrailingslashit($request_path);
    if ($normalized_path !== '/aktuelles') {
        return;
    }

    wp_safe_redirect(home_url('/kalender/'), 301);
    exit;
});

function industriesalon_get_schoneweide_viewport_manifest(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $path = get_stylesheet_directory() . '/assets/maps/schoneweide-viewport-manifest.json';
    if (!is_readable($path)) {
        $cache = [];
        return $cache;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    if (!is_array($decoded)) {
        $cache = [];
        return $cache;
    }

    $cache = $decoded;

    return $cache;
}

function industriesalon_get_schoneweide_viewport_preset_variants(): array
{
    $manifest = industriesalon_get_schoneweide_viewport_manifest();
    $presets = is_array($manifest['presets'] ?? null) ? $manifest['presets'] : [];

    if (!$presets) {
        return [];
    }

    $variants = [];

    foreach ($presets as $preset) {
        if (!is_array($preset)) {
            continue;
        }

        $id = sanitize_key((string) ($preset['id'] ?? ''));
        $image = (string) ($preset['image'] ?? '');
        $markers = (string) ($preset['markers'] ?? '');

        if ($id === '' || $image === '' || $markers === '') {
            continue;
        }

        $variants[$id] = [
            'label' => (string) ($preset['label'] ?? $id),
            'map' => '/assets/maps/' . ltrim($image, '/'),
            'markers' => '/assets/maps/' . ltrim($markers, '/'),
            'width' => max(1, absint($preset['width'] ?? 2200)),
            'height' => max(1, absint($preset['height'] ?? 1000)),
            'crop_mode' => 'fixed',
            'rotation_deg' => is_numeric($preset['rotation_deg'] ?? null) ? (float) $preset['rotation_deg'] : 0.0,
            'viewport' => [
                'scale_x' => 1,
                'scale_y' => 1,
                'offset_x' => 0,
                'offset_y' => 0,
            ],
            'rail' => is_array($preset['rail'] ?? null) ? $preset['rail'] : [],
        ];
    }

    return $variants;
}

add_filter('iss_relations_place_map_presets', function (array $presets): array {
    $variants = [
        'default' => [
            'label' => __('Atlas Übersicht', 'industriesalon'),
            'map' => '/assets/maps/schoneweide-map-canonical-display.webp',
            'markers' => '/assets/maps/schoneweide-static-markers-new.json',
            'width' => 4096,
            'height' => 2389,
            'viewport' => [
                'scale_x' => 1,
                'scale_y' => 1,
                'offset_x' => 0,
                'offset_y' => 0,
            ],
        ],
        'front-page' => [
            'label' => __('Frontpage Fokus', 'industriesalon'),
            'map' => '/assets/maps/schoneweide-map-canonical-display.webp',
            'markers' => '/assets/maps/schoneweide-static-markers-new.json',
            'width' => 4096,
            'height' => 2389,
            'viewport' => [
                'scale_x' => 2.507,
                'scale_y' => 2.676,
                'offset_x' => -68.411,
                'offset_y' => -77.398,
            ],
        ],
        'spree-horizontal-17' => [
            'label' => __('Spree Horizontal 17 Grad', 'industriesalon'),
            'map' => '/assets/maps/schoneweide-map-spree-horizontal-17.webp',
            'markers' => '/assets/maps/schoneweide-map-spree-horizontal-17-markers.json',
            'width' => 4618,
            'height' => 3485,
            'viewport' => [
                'scale_x' => 1,
                'scale_y' => 1,
                'offset_x' => 0,
                'offset_y' => 0,
            ],
        ],
        'atlas-slice' => [
            'label' => __('Atlas Slice Fokus', 'industriesalon'),
            'map' => '/assets/maps/schoneweide-map-canonical-display.webp',
            'markers' => '/assets/maps/schoneweide-static-markers-new.json',
            'width' => 4096,
            'height' => 2389,
            'viewport' => [
                'scale_x' => 1.72,
                'scale_y' => 1.98,
                'offset_x' => -28.250,
                'offset_y' => -39.250,
            ],
        ],
        'atlas-split' => [
            'label' => __('Atlas Split Fokus', 'industriesalon'),
            'map' => '/assets/maps/schoneweide-map-canonical-display.webp',
            'markers' => '/assets/maps/schoneweide-static-markers-new.json',
            'width' => 4096,
            'height' => 2389,
            'viewport' => [
                'scale_x' => 2.48,
                'scale_y' => 2.82,
                'offset_x' => -72.500,
                'offset_y' => -49.750,
            ],
        ],
    ];

    $viewport_variants = industriesalon_get_schoneweide_viewport_preset_variants();
    if ($viewport_variants) {
        $variants = array_merge($variants, $viewport_variants);
    }

    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();
    $configured = [];

    foreach ($variants as $preset => $variant) {
        $map_rel = (string) ($variant['map'] ?? '');
        $markers_rel = (string) ($variant['markers'] ?? '');
        $map_path = $theme_dir . $map_rel;
        $markers_path = $theme_dir . $markers_rel;

        if (!file_exists($map_path) || !file_exists($markers_path)) {
            continue;
        }

        $configured[$preset] = [
            'label' => (string) ($variant['label'] ?? $preset),
            'image_url' => $theme_uri . $map_rel,
            'markers_path' => $markers_path,
            'image_alt' => __('Übersichtskarte des Schöneweide-Atlas.', 'industriesalon'),
            'width' => max(1, absint($variant['width'] ?? 2400)),
            'height' => max(1, absint($variant['height'] ?? 1313)),
            'crop_mode' => sanitize_key((string) ($variant['crop_mode'] ?? 'dynamic')),
            'rotation_deg' => is_numeric($variant['rotation_deg'] ?? null) ? (float) $variant['rotation_deg'] : 0.0,
            'viewport' => is_array($variant['viewport'] ?? null) ? $variant['viewport'] : [],
            'rail' => is_array($variant['rail'] ?? null) ? $variant['rail'] : [],
        ];
    }

    return $configured ?: $presets;
}, 10, 1);

/**
 * Register local block patterns from theme files.
 */
function industriesalon_register_block_patterns(): void
{
    if (!function_exists('register_block_pattern')) {
        return;
    }

    if (function_exists('register_block_pattern_category')) {
        register_block_pattern_category(
            'industriesalon',
            array(
                'label' => 'Industriesalon',
            )
        );
    }

    $theme_dir = get_stylesheet_directory();
    $registry = class_exists('WP_Block_Patterns_Registry')
        ? WP_Block_Patterns_Registry::get_instance()
        : null;

    $patterns = array(
        array(
            'name' => 'industriesalon/info-panel-anmeldung',
            'title' => 'Info Panel – Anmeldung',
            'description' => 'Kontakt- und Anmeldeblock für Führungen',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/pattern-info-panel-anmeldung.html',
        ),
        array(
            'name' => 'industriesalon/info-panel-besuch',
            'title' => 'Info Panel – Besuch planen',
            'description' => 'Öffnungszeiten und Besuchsinformationen',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/pattern-info-panel-besuch.html',
        ),
        array(
            'name' => 'industriesalon/info-panel-vermietung',
            'title' => 'Info Panel – Vermietung',
            'description' => 'Kontakt für Raumvermietung und Anfragen',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/pattern-info-panel-vermietung.html',
        ),
        array(
            'name' => 'industriesalon/feature-split',
            'title' => 'ISS Feature Split',
            'description' => 'Linear feature section with text left and image right. Used to break card grids.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-section-feature-split.html',
        ),
        array(
            'name' => 'industriesalon/50-50-media-text',
            'title' => 'ISS 50/50 Media Text',
            'description' => 'Two-column section with text on one side and image on the other.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-50-50-media-text.html',
        ),
        array(
            'name' => 'industriesalon/asymmetric-feature',
            'title' => 'ISS Asymmetric Feature',
            'description' => 'Asymmetric content and image section with offset visual rhythm.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-asymmetric-feature.html',
        ),
        array(
            'name' => 'industriesalon/4-card-row',
            'title' => 'ISS 4 Card Row',
            'description' => 'Section heading above a row of four compact cards with image and title.',
            'categories' => array('industriesalon', 'media', 'cards'),
            'file' => '/patterns/iss-4-card-row.html',
        ),
        array(
            'name' => 'industriesalon/pathway-strip',
            'title' => 'ISS Pathway Strip',
            'description' => 'Curated six-step media strip for cross-links between tours, videos, exhibitions, publications, events, and archive.',
            'categories' => array('industriesalon', 'media', 'cards'),
            'file' => '/patterns/iss-section-pathway-strip.html',
        ),
        array(
            'name' => 'industriesalon/3-card-row',
            'title' => 'ISS 3 Card Row',
            'description' => 'Section heading above a row of three compact info cards.',
            'categories' => array('industriesalon', 'media', 'cards'),
            'file' => '/patterns/iss-3-card-row.html',
        ),
        array(
            'name' => 'industriesalon/newsletter-funders',
            'title' => 'ISS Newsletter + Förderer',
            'description' => 'Newsletter signup section with sponsor/funder panel.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-newsletter-funders.html',
        ),
        array(
            'name' => 'industriesalon/archive-landing',
            'title' => 'ISS Archive Landing',
            'description' => 'Archive and media landing page layout.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/archive-landing.html',
        ),
        array(
            'name' => 'industriesalon/mission-support-strip',
            'title' => 'ISS Mission Support Strip',
            'description' => 'Mission statement with three supporting fact modules.',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/iss-section-mission-support-strip.html',
        ),
        array(
            'name' => 'industriesalon/landing-hero-with-note',
            'title' => 'ISS Landing Hero + Note',
            'description' => 'Full-width landing hero with right-side note banner (iss-hero-note).',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-landing-hero-with-note.html',
        ),
        array(
            'name' => 'industriesalon/flexible-split',
            'title' => 'ISS Flexible Split',
            'description' => 'Flexible two-column section with ratio modifiers and optional callout/media panels.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-flex-split.html',
        ),
        array(
            'name' => 'industriesalon/publications-intro',
            'title' => 'ISS Publications Intro',
            'description' => 'Editorial opener for publications pages and research-related landings.',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/iss-publications-intro.html',
        ),
        array(
            'name' => 'industriesalon/project-dossier-chapters',
            'title' => 'ISS Projekt Dossier Kapitel',
            'description' => 'Chaptered project text with a compact project-voice rail for larger project concepts.',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/iss-project-dossier-chapters.html',
        ),
        array(
            'name' => 'industriesalon/recognition-split',
            'title' => 'ISS Recognition Split',
            'description' => 'Editorial recognition section with a tall media column, trio intro, and two supporting award cards. Remove iss-flex-split--reverse to move the media left.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-section-recognition-split.html',
        ),
        array(
            'name' => 'industriesalon/team-query-grid',
            'title' => 'ISS Team Query Grid',
            'description' => 'Featured team profile plus portrait card grid for team_member query loops.',
            'categories' => array('industriesalon', 'query', 'cards'),
            'file' => '/patterns/iss-team-query-grid.html',
        ),
        array(
            'name' => 'industriesalon/object-highlight',
            'title' => 'ISS Object Highlight',
            'description' => 'Dark contrast section for a single collection or research object with supporting note image.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-section-object-highlight.html',
        ),
        array(
            'name' => 'industriesalon/ausstellung-workstation',
            'title' => 'ISS Ausstellung Workstation',
            'description' => 'Editorial deep-dive for one permanent exhibition workstation or object ensemble.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-section-ausstellung-workstation.html',
        ),
        array(
            'name' => 'industriesalon/page-fuehrungen-template',
            'title' => 'ISS Fuehrungen Page Template',
            'description' => 'Booking-first tours page content for the dedicated fuehrungen landing page.',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/page-fuehrungen-template.html',
        ),
        array(
            'name' => 'industriesalon/page-salon-vermietung-template',
            'title' => 'ISS Salon Vermietung Page Template',
            'description' => 'Practical-first room rental page content for the dedicated salon-vermietung landing page.',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/page-salon-vermietung-template.html',
        ),
        array(
            'name' => 'industriesalon/page-repair-cafe-template',
            'title' => 'ISS Repair Cafe Page Template',
            'description' => 'Repair Cafe page content for the dedicated repair-cafe page.',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/page-repair-cafe-template.html',
        ),
    );

    foreach ($patterns as $pattern) {
        if ($registry && $registry->is_registered($pattern['name'])) {
            continue;
        }

        $file_path = $theme_dir . $pattern['file'];
        if (!file_exists($file_path)) {
            continue;
        }

        $content = file_get_contents($file_path);
        if ($content === false) {
            continue;
        }

        $content = preg_replace('/^<!--[\s\S]*?-->\s*/', '', $content, 1);

        register_block_pattern(
            $pattern['name'],
            array(
                'title' => $pattern['title'],
                'description' => $pattern['description'],
                'categories' => $pattern['categories'],
                'inserter' => true,
                'content' => $content,
            )
        );
    }
}
add_action('init', 'industriesalon_register_block_patterns');

/**
 * Keep role-based About-page team queries portable across databases.
 */
function industriesalon_portable_team_role_queries(array $parsed_block, array $source_block): array
{
    if (($parsed_block['blockName'] ?? '') !== 'core/query') {
        return $parsed_block;
    }

    $attrs = $parsed_block['attrs'] ?? array();
    $class_name = isset($attrs['className']) ? (string) $attrs['className'] : '';
    $role_map = array(
        'iss-about-team__query--staff' => 'mitarbeiter',
        'iss-about-team__query--guides' => 'guides',
    );

    $target_slug = '';
    foreach ($role_map as $needle => $slug) {
        if ($class_name !== '' && str_contains($class_name, $needle)) {
            $target_slug = $slug;
            break;
        }
    }

    if ($target_slug === '') {
        return $parsed_block;
    }

    $term = get_term_by('slug', $target_slug, 'team_role');
    if (!$term instanceof WP_Term) {
        return $parsed_block;
    }

    if (!isset($parsed_block['attrs']['query']) || !is_array($parsed_block['attrs']['query'])) {
        $parsed_block['attrs']['query'] = array();
    }

    $parsed_block['attrs']['query']['taxQuery'] = array(
        'team_role' => array((int) $term->term_id),
    );

    return $parsed_block;
}
add_filter('render_block_data', 'industriesalon_portable_team_role_queries', 10, 2);

/**
 * Replace static About-page team card kicker text with the team role label meta.
 */
function industriesalon_render_about_team_role_label(string $block_content, array $block, WP_Block $instance): string
{
    if ($block_content === '') {
        return $block_content;
    }

    $class_name = (string) ($block['attrs']['className'] ?? '');
    if ($class_name === '' || !str_contains($class_name, 'iss-about-person-card__kicker')) {
        return $block_content;
    }

    $post_id = (int) ($instance->context['postId'] ?? 0);
    if ($post_id <= 0 || get_post_type($post_id) !== 'team_member') {
        return $block_content;
    }

    $role_label = trim((string) get_post_meta($post_id, 'iss_role_label', true));
    if ($role_label === '') {
        return $block_content;
    }

    return preg_replace('/(<p\b[^>]*>)(.*?)(<\/p>)/is', '$1' . esc_html($role_label) . '$3', $block_content, 1) ?: $block_content;
}
add_filter('render_block_core/paragraph', 'industriesalon_render_about_team_role_label', 10, 3);

/**
 * Add semantic image-focus modifiers for About-page team portraits that need
 * a non-default crop focus.
 */
function industriesalon_render_about_team_featured_image(string $block_content, array $block, WP_Block $instance): string
{
    if ($block_content === '') {
        return $block_content;
    }

    $class_name = (string) ($block['attrs']['className'] ?? '');
    if ($class_name === '' || !str_contains($class_name, 'iss-about-person-card__media')) {
        return $block_content;
    }

    $post_id = (int) ($instance->context['postId'] ?? 0);
    if ($post_id <= 0 || get_post_type($post_id) !== 'team_member') {
        return $block_content;
    }

    $modifier_map = array(
        'Susanne Reumschüssel' => 'iss-about-person-card__media--susanne-reumschuessel',
    );

    $modifier_class = $modifier_map[trim((string) get_the_title($post_id))] ?? '';
    if ($modifier_class === '') {
        return $block_content;
    }

    if (!class_exists('WP_HTML_Tag_Processor')) {
        return $block_content;
    }

    $processor = new WP_HTML_Tag_Processor($block_content);
    if (!$processor->next_tag()) {
        return $block_content;
    }

    $processor->add_class($modifier_class);
    return $processor->get_updated_html();
}
add_filter('render_block_core/post-featured-image', 'industriesalon_render_about_team_featured_image', 10, 3);

/**
 * Force zero margin in editor canvas to match frontend gap-less layout.
 */
add_action('admin_head', function() {
    echo '<style>
        .interface-interface-skeleton__content { background-color: #fff; }
        .is-root-container.block-editor-block-list__block { margin-top: 0 !important; padding-top: 0 !important; }
    </style>';
});

/**
 * Single post layout variants (standard / compact / long).
 */
function industriesalon_post_layout_choices(): array
{
    return array('standard', 'compact', 'long');
}

function industriesalon_sanitize_post_layout($value): string
{
    $value = sanitize_key((string) $value);
    if ($value === 'short') {
        $value = 'compact';
    } elseif ($value === 'image') {
        $value = 'standard';
    }
    return in_array($value, industriesalon_post_layout_choices(), true) ? $value : 'standard';
}

function industriesalon_register_post_layout_meta(): void
{
    register_post_meta('post', '_iss_post_layout', array(
        'single' => true,
        'type' => 'string',
        'default' => 'standard',
        'show_in_rest' => true,
        'sanitize_callback' => 'industriesalon_sanitize_post_layout',
        'auth_callback' => static function ($allowed = null, $meta_key = '', $post_id = 0) {
            $post_id = (int) $post_id;
            if ($post_id > 0) {
                return current_user_can('edit_post', $post_id);
            }
            return current_user_can('edit_posts');
        },
    ));
}
add_action('init', 'industriesalon_register_post_layout_meta');

function industriesalon_add_post_layout_body_class(array $classes): array
{
    if (!is_singular('post')) {
        return $classes;
    }

    $post_id = get_queried_object_id();
    $layout = industriesalon_sanitize_post_layout(get_post_meta($post_id, '_iss_post_layout', true));
    $classes[] = 'iss-post-layout-' . $layout;

    return $classes;
}
add_filter('body_class', 'industriesalon_add_post_layout_body_class');

function industriesalon_enqueue_post_layout_editor_assets(): void
{
    if (!function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'post') {
        return;
    }

    wp_register_script(
        'industriesalon-post-layout-editor',
        false,
        array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'),
        wp_get_theme()->get('Version'),
        true
    );
    wp_enqueue_script('industriesalon-post-layout-editor');

    $script = <<<'JS'
(function (wp) {
  if (!wp || !wp.plugins || !wp.editPost || !wp.element || !wp.components || !wp.data) {
    return;
  }

  var registerPlugin = wp.plugins.registerPlugin;
  var PluginDocumentSettingPanel = wp.editPost.PluginDocumentSettingPanel;
  var SelectControl = wp.components.SelectControl;
  var createElement = wp.element.createElement;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;

  var options = [
    { label: 'Standard', value: 'standard' },
    { label: 'Kurze Meldung', value: 'compact' },
    { label: 'Longread', value: 'long' }
  ];

  function normalizeLayout(value) {
    if (value === 'short') {
      return 'compact';
    }
    if (value === 'image') {
      return 'standard';
    }
    if (value === 'standard' || value === 'compact' || value === 'long') {
      return value;
    }
    return 'standard';
  }

  function PostLayoutPanel() {
    var postType = useSelect(function (select) {
      return select('core/editor').getCurrentPostType();
    }, []);

    var meta = useSelect(function (select) {
      return select('core/editor').getEditedPostAttribute('meta') || {};
    }, []);

    var editPost = useDispatch('core/editor').editPost;

    if (postType !== 'post') {
      return null;
    }

    var value = normalizeLayout(meta._iss_post_layout);

    return createElement(
      PluginDocumentSettingPanel,
      { name: 'iss-post-layout', title: 'Beitragslayout', className: 'iss-post-layout-panel' },
      createElement(SelectControl, {
        label: 'Layout',
        value: value,
        options: options,
        help: 'Wählt die Darstellung für diesen Beitrag im Frontend.',
        onChange: function (nextValue) {
          nextValue = normalizeLayout(nextValue);
          if (!nextValue) {
            nextValue = 'standard';
          }
          editPost({ meta: Object.assign({}, meta, { _iss_post_layout: nextValue }) });
        }
      })
    );
  }

  registerPlugin('iss-post-layout-panel', {
    render: PostLayoutPanel
  });
})(window.wp);
JS;

    wp_add_inline_script('industriesalon-post-layout-editor', $script);
}
add_action('enqueue_block_editor_assets', 'industriesalon_enqueue_post_layout_editor_assets');

function industriesalon_format_short_post_excerpt_from_content(int $post_id): string
{
    $content = (string) get_post_field('post_content', $post_id);
    if ($content === '') {
        return '';
    }

    $content = preg_replace('/<!--\s*wp:paragraph[^>]*-->/', '', $content);
    $content = preg_replace('/<!--\s*\/wp:paragraph\s*-->/', "\n\n", $content);
    $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
    $content = preg_replace('/<\/p>/i', "\n\n", $content);
    $content = preg_replace('/<p[^>]*>/i', '', $content);
    $content = wp_strip_all_tags($content, false);
    $content = preg_replace("/\n{3,}/", "\n\n", $content);

    return trim((string) $content);
}

function industriesalon_filter_short_post_excerpt($excerpt, $post): string
{
    $post = get_post($post);
    if (!$post instanceof WP_Post || $post->post_type !== 'post') {
        return (string) $excerpt;
    }

    $layout = industriesalon_sanitize_post_layout(get_post_meta($post->ID, '_iss_post_layout', true));
    if ($layout !== 'short') {
        return (string) $excerpt;
    }

    if (has_excerpt($post)) {
        return (string) $excerpt;
    }

    $formatted = industriesalon_format_short_post_excerpt_from_content((int) $post->ID);
    return $formatted !== '' ? $formatted : (string) $excerpt;
}
add_filter('get_the_excerpt', 'industriesalon_filter_short_post_excerpt', 20, 2);

function industriesalon_get_placeholder_asset_url(string $filename): string
{
    $filename = sanitize_file_name($filename);
    if ($filename === '') {
        return '';
    }

    $relative_path = 'assets/img/placeholders/' . $filename;
    $absolute_path = get_stylesheet_directory() . '/' . $relative_path;
    if (!is_readable($absolute_path)) {
        return '';
    }

    return get_stylesheet_directory_uri() . '/' . $relative_path;
}

function industriesalon_get_related_card_placeholder_filename(WP_Post $post): string
{
    if ($post->post_type === 'veranstaltung') {
        $raw_entity_key = (string) get_post_meta((int) $post->ID, '_iss_entity_key', true);
        $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
            ? iss_content_model_sanitize_veranstaltung_entity_key($raw_entity_key)
            : '';
        if ($entity_key === 'event.festival') {
            return 'fest.webp';
        }

        $semantic_key = '';
        $semantic_taxonomy = defined('ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY')
            ? (string) constant('ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY')
            : 'veranstaltung_art';
        if (taxonomy_exists($semantic_taxonomy)) {
            $terms = wp_get_post_terms((int) $post->ID, $semantic_taxonomy, ['fields' => 'slugs']);
            if (is_array($terms) && $terms !== []) {
                $semantic_key = function_exists('iss_content_model_sanitize_veranstaltung_semantic_key')
                    ? iss_content_model_sanitize_veranstaltung_semantic_key((string) $terms[0])
                    : sanitize_title((string) $terms[0]);
            }
        }
        if ($semantic_key === '' && function_exists('iss_content_model_veranstaltung_semantic_from_legacy_entity_key')) {
            $semantic_key = iss_content_model_veranstaltung_semantic_from_legacy_entity_key($raw_entity_key);
        }

        $semantic_placeholders = [
            'vortrag' => 'vortrag.webp',
            'gespraech' => 'gespach.webp',
            'lesung' => 'lesung.webp',
            'konzert' => 'fest.webp',
            'film' => 'fest.webp',
        ];

        return $semantic_placeholders[$semantic_key] ?? 'versanstaltung.webp';
    }

    $post_type_placeholders = [
        'ausstellung' => 'ausstellung.webp',
        'projekt' => 'project.webp',
        'register_place' => 'orte.webp',
        'video' => 'video.webp',
    ];

    return $post_type_placeholders[$post->post_type] ?? '';
}

add_filter('iss_relations_card_placeholder_image_url', function (string $url, WP_Post $post): string {
    if ($url !== '') {
        return $url;
    }

    $filename = industriesalon_get_related_card_placeholder_filename($post);
    if ($filename === '') {
        return '';
    }

    return industriesalon_get_placeholder_asset_url($filename);
}, 10, 2);

function industriesalon_render_featured_image_placeholder_block(string $block_content, array $block, WP_Block $instance): string
{
    if (trim($block_content) !== '') {
        return $block_content;
    }

    $post_id = (int) ($instance->context['postId'] ?? 0);
    if ($post_id <= 0) {
        $post_id = (int) get_the_ID();
    }

    $post = $post_id > 0 ? get_post($post_id) : null;
    if (!$post instanceof WP_Post || has_post_thumbnail($post)) {
        return $block_content;
    }

    $filename = industriesalon_get_related_card_placeholder_filename($post);
    if ($filename === '') {
        return $block_content;
    }

    $image_url = industriesalon_get_placeholder_asset_url($filename);
    if ($image_url === '') {
        return $block_content;
    }

    $class_names = ['wp-block-post-featured-image', 'iss-featured-image-placeholder'];
    $extra_class = trim((string) ($block['attrs']['className'] ?? ''));
    if ($extra_class !== '') {
        $class_names[] = $extra_class;
    }

    $image = '<img src="' . esc_url($image_url) . '" alt="" loading="lazy" decoding="async">';
    if (!empty($block['attrs']['isLink'])) {
        $permalink = get_permalink($post);
        if (is_string($permalink) && $permalink !== '') {
            $image = '<a href="' . esc_url($permalink) . '" aria-label="' . esc_attr(get_the_title($post)) . '">' . $image . '</a>';
        }
    }

    return '<figure class="' . esc_attr(implode(' ', array_filter($class_names))) . '">' . $image . '</figure>';
}
add_filter('render_block_core/post-featured-image', 'industriesalon_render_featured_image_placeholder_block', 9, 3);

function industriesalon_add_event_registry_body_classes(array $classes): array
{
    if (!is_singular('veranstaltung')) {
        return $classes;
    }

    $post_id = get_queried_object_id();
    $entity_key = '';
    if (function_exists('iss_content_model_sanitize_veranstaltung_entity_key')) {
        $entity_key = iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true));
    }

    if ($entity_key !== '') {
        $classes[] = 'iss-event-entity-' . sanitize_html_class(str_replace(['.', '_'], '-', $entity_key));

        if (function_exists('iss_content_model_veranstaltung_entity_shape')) {
            $shape = iss_content_model_veranstaltung_entity_shape($entity_key);
            if ($shape !== '') {
                $classes[] = 'iss-event-shape-' . sanitize_html_class(str_replace('_', '-', $shape));
            }
        }

        if (function_exists('iss_content_model_veranstaltung_entity_primary_surface')) {
            $surface = iss_content_model_veranstaltung_entity_primary_surface($entity_key);
            if ($surface !== '') {
                $classes[] = 'iss-event-surface-' . sanitize_html_class($surface);
            }
        }

        if (function_exists('iss_content_model_veranstaltung_entity_default_skin')) {
            $skin = iss_content_model_veranstaltung_entity_default_skin($entity_key);
            if ($skin !== '') {
                $classes[] = 'iss-event-skin-' . sanitize_html_class($skin);
            }
        }
    }

    return $classes;
}
add_filter('body_class', 'industriesalon_add_event_registry_body_classes');

function industriesalon_is_compact_header_context(): bool
{
    if (is_admin()) {
        return false;
    }

    if (is_singular(array('archivsammlung', 'archivobjekt', 'ausstellung'))) {
        return true;
    }

    if (is_singular('post')) {
        $post_id = get_queried_object_id();
        return industriesalon_sanitize_post_layout(get_post_meta($post_id, '_iss_post_layout', true)) === 'long';
    }

    return false;
}

function industriesalon_add_compact_header_body_class(array $classes): array
{
    if (industriesalon_is_compact_header_context()) {
        $classes[] = 'iss-header-mode-compact';
    }

    return $classes;
}
add_filter('body_class', 'industriesalon_add_compact_header_body_class');

function industriesalon_is_offset_header_context(): bool
{
    if (is_admin()) {
        return false;
    }

    if (is_404() || is_home() || is_search()) {
        return true;
    }

    if (is_page(array('archiv', 'publikationen', 'kalender', 'verein'))) {
        return true;
    }

    if (is_singular(array('publication', 'video'))) {
        return true;
    }

    return false;
}

function industriesalon_add_offset_header_body_class(array $classes): array
{
    if (industriesalon_is_offset_header_context()) {
        $classes[] = 'iss-header-mode-offset';
    }

    return $classes;
}
add_filter('body_class', 'industriesalon_add_offset_header_body_class');

function industriesalon_get_compact_header_context(): array
{
    $post_id = get_queried_object_id();
    if ($post_id <= 0) {
        return array();
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return array();
    }

    $crumbs = array();
    $crumbs[] = sprintf(
        '<a href="%s">%s</a>',
        esc_url(home_url('/')),
        esc_html__('Start', 'industriesalon')
    );

    $archive_link = '';
    $archive_label = '';

    if ($post->post_type === 'post') {
        $posts_page_id = (int) get_option('page_for_posts');
        if ($posts_page_id > 0) {
            $archive_link = get_permalink($posts_page_id) ?: '';
            $archive_label = get_the_title($posts_page_id);
        } else {
            $archive_label = __('Beiträge', 'industriesalon');
        }
    } else {
        $post_type_object = get_post_type_object($post->post_type);
        if ($post_type_object) {
            $archive_label = $post_type_object->labels->name ?? '';
            if (!empty($post_type_object->has_archive)) {
                $archive_link = get_post_type_archive_link($post->post_type) ?: '';
            }
        }
    }

    if ($archive_label !== '') {
        if ($archive_link !== '') {
            $crumbs[] = sprintf('<a href="%s">%s</a>', esc_url($archive_link), esc_html($archive_label));
        } else {
            $crumbs[] = '<span>' . esc_html($archive_label) . '</span>';
        }
    }

    return array(
        'crumbs' => $crumbs,
        'title' => get_the_title($post),
    );
}

function industriesalon_render_compact_header_context(): string
{
    if (!industriesalon_is_compact_header_context()) {
        return '';
    }

    $context = industriesalon_get_compact_header_context();
    if (!$context) {
        return '';
    }

    $crumbs = $context['crumbs'] ?? array();
    $title = trim((string) ($context['title'] ?? ''));

    ob_start();
    ?>
    <div class="iss-header-context__inner">
        <?php if ($crumbs) : ?>
            <nav class="iss-header-context__breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'industriesalon'); ?>">
                <?php echo wp_kses_post(implode('<span class="iss-header-context__sep">/</span>', $crumbs)); ?>
            </nav>
        <?php endif; ?>
        <?php if ($title !== '') : ?>
            <p class="iss-header-context__title"><?php echo esc_html($title); ?></p>
        <?php endif; ?>
    </div>
    <?php

    return trim((string) ob_get_clean());
}
add_shortcode('iss_compact_header_context', 'industriesalon_render_compact_header_context');

function industriesalon_enqueue_assets(): void
{
    $is_schoneweide_page = is_page('schoneweide');
    $current_page_template = is_page()
        ? (string) get_post_meta(get_queried_object_id(), '_wp_page_template', true)
        : '';
    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();
    $theme = wp_get_theme();
    $version = $theme->get('Version');
    $base_stylesheet = get_stylesheet_directory() . '/style.css';
    $base_version = file_exists($base_stylesheet)
        ? (string) filemtime($base_stylesheet)
        : $version;
    $enqueue_theme_style = static function (string $handle, string $relative_path, array $dependencies) use ($theme_dir, $theme_uri): bool {
        $absolute_path = $theme_dir . $relative_path;

        if (!file_exists($absolute_path)) {
            return false;
        }

        wp_enqueue_style(
            $handle,
            $theme_uri . $relative_path,
            $dependencies,
            (string) filemtime($absolute_path)
        );

        return true;
    };

    wp_enqueue_style(
        'industriesalon-tokens',
        $theme_uri . '/assets/css/tokens.css',
        array(),
        file_exists($theme_dir . '/assets/css/tokens.css')
            ? (string) filemtime($theme_dir . '/assets/css/tokens.css')
            : $version
    );

    wp_enqueue_style(
        'industriesalon-base',
        get_stylesheet_uri(),
        array('industriesalon-tokens'),
        $base_version
    );

    $cards_loaded = $enqueue_theme_style(
        'industriesalon-cards',
        '/assets/css/cards.css',
        array('industriesalon-base')
    );

    $primitives_dependencies = $cards_loaded
        ? array('industriesalon-cards')
        : array('industriesalon-base');
    $primitives_loaded = $enqueue_theme_style(
        'industriesalon-primitives',
        '/assets/css/primitives.css',
        $primitives_dependencies
    );

    $patterns_dependencies = $primitives_loaded
        ? array('industriesalon-primitives')
        : ($cards_loaded
            ? array('industriesalon-cards')
            : array('industriesalon-base'));
    $patterns_loaded = $enqueue_theme_style(
        'industriesalon-patterns',
        '/assets/css/patterns.css',
        $patterns_dependencies
    );

    $gestures_dependencies = $patterns_loaded
        ? array('industriesalon-patterns')
        : $patterns_dependencies;
    $gestures_loaded = $enqueue_theme_style(
        'industriesalon-gestures',
        '/assets/css/gestures.css',
        $gestures_dependencies
    );

    $page_dependencies = $patterns_loaded
        ? ($gestures_loaded ? array('industriesalon-gestures') : array('industriesalon-patterns'))
        : ($primitives_loaded
            ? array('industriesalon-primitives')
            : ($cards_loaded
                ? array('industriesalon-cards')
                : array('industriesalon-base')));

    $conditional_styles = array(
        array(
            'handle' => 'industriesalon-front-page',
            'path' => '/assets/css/front-page.css',
            'condition' => is_front_page(),
        ),
        array(
            'handle' => 'industriesalon-ueber-uns',
            'path' => '/assets/css/ueber-uns.css',
            'condition' => $current_page_template === 'page-ueber-uns'
                || is_page(array('ueber-uns', 'about')),
        ),
        array(
            'handle' => 'industriesalon-page-archive',
            'path' => '/assets/css/page-archive.css',
            'condition' => is_page('archiv'),
        ),
        array(
            'handle' => 'industriesalon-page-kalender',
            'path' => '/assets/css/page-kalender.css',
            'condition' => $current_page_template === 'page-kalender'
                || is_page('kalender'),
        ),
        array(
            'handle' => 'industriesalon-page-projekte',
            'path' => '/assets/css/page-projekte.css',
            'condition' => is_page('projekte'),
        ),
        array(
            'handle' => 'industriesalon-page-schoneweide',
            'path' => '/assets/css/page-schoneweide.css',
            'condition' => $is_schoneweide_page,
        ),
        array(
            'handle' => 'industriesalon-page-salon-vermietung',
            'path' => '/assets/css/page-salon-vermietung.css',
            'condition' => $current_page_template === 'page-salon-vermietung'
                || is_page('salon-vermietung'),
        ),
        array(
            'handle' => 'industriesalon-page-sammlungen',
            'path' => '/assets/css/page-sammlungen.css',
            'condition' => is_page('sammlungen'),
        ),
        array(
            'handle' => 'industriesalon-page-fuehrungen',
            'path' => '/assets/css/page-fuehrungen.css',
            'condition' => $current_page_template === 'page-fuehrungen'
                || is_page('fuehrungen'),
        ),
        array(
            'handle' => 'industriesalon-page-events',
            'path' => '/assets/css/page-events.css',
            'condition' => is_page('veranstaltungen'),
        ),
        array(
            'handle' => 'industriesalon-page-videos',
            'path' => '/assets/css/page-videos.css',
            'condition' => is_page('videos'),
        ),
        array(
            'handle' => 'industriesalon-page-ausstellungen',
            'path' => '/assets/css/page-ausstellungen.css',
            'condition' => is_page('ausstellungen'),
        ),
        array(
            'handle' => 'industriesalon-page-verein',
            'path' => '/assets/css/page-verein.css',
            'condition' => is_page('verein'),
        ),
        array(
            'handle' => 'industriesalon-page-landing-editorial',
            'path' => '/assets/css/page-landing-editorial.css',
            'condition' => is_singular('page')
                && function_exists('industriesalon_editorial_landing_is_enabled')
                && industriesalon_editorial_landing_is_enabled((int) get_queried_object_id()),
        ),
        array(
            'handle' => 'industriesalon-publications',
            'path' => '/assets/css/publications.css',
            'condition' => is_page('publikationen') || is_singular('publication'),
        ),
        array(
            'handle' => 'industriesalon-single-video',
            'path' => '/assets/css/single-video.css',
            'condition' => is_singular('video'),
        ),
        array(
            'handle' => 'industriesalon-single-tour',
            'path' => '/assets/css/single-tour.css',
            'condition' => is_singular('fuehrung'),
        ),
        array(
            'handle' => 'industriesalon-single-ausstellung',
            'path' => '/assets/css/single-ausstellung.css',
            'condition' => is_singular('ausstellung'),
        ),
        array(
            'handle' => 'industriesalon-single-event',
            'path' => '/assets/css/single-event.css',
            'condition' => is_singular('veranstaltung'),
        ),
        array(
            'handle' => 'industriesalon-single-content',
            'path' => '/assets/css/single-content.css',
            'condition' => is_singular(array('post', 'archivsammlung', 'archivobjekt', 'register_place', 'team_member', 'projekt', 'entity_profile')),
        ),
        array(
            'handle' => 'industriesalon-search',
            'path' => '/assets/css/search.css',
            'condition' => is_search(),
        ),
    );

    foreach ($conditional_styles as $style) {
        if (!$style['condition']) {
            continue;
        }

        $enqueue_theme_style($style['handle'], $style['path'], $page_dependencies);
    }

    if (is_singular('ausstellung') && function_exists('industriesalon_get_editorial_ausstellung_post_skin')) {
        $ausstellung_skin = industriesalon_get_editorial_ausstellung_post_skin((int) get_queried_object_id());
        $ausstellung_skin_styles = array(
            'quellenbuehne' => array(
                'handle' => 'industriesalon-ausstellung-skin-quellenbuehne',
                'path' => '/assets/css/skins/ausstellung-quellenbuehne.css',
            ),
            'objektalbum' => array(
                'handle' => 'industriesalon-ausstellung-skin-objektalbum',
                'path' => '/assets/css/skins/ausstellung-objektalbum.css',
            ),
            'typografisch' => array(
                'handle' => 'industriesalon-ausstellung-skin-typografisch',
                'path' => '/assets/css/skins/ausstellung-typografisch.css',
            ),
            'chronik' => array(
                'handle' => 'industriesalon-ausstellung-skin-chronik',
                'path' => '/assets/css/skins/ausstellung-chronik.css',
            ),
        );

        if (isset($ausstellung_skin_styles[$ausstellung_skin])) {
            $skin_style = $ausstellung_skin_styles[$ausstellung_skin];
            $enqueue_theme_style(
                $skin_style['handle'],
                $skin_style['path'],
                array('industriesalon-single-ausstellung')
            );
        }
    }

    // Header JS
    $script_rel_path = '/assets/js/header.js';
    $script_abs_path = get_stylesheet_directory() . $script_rel_path;
    if (file_exists($script_abs_path)) {
        wp_enqueue_script(
            'industriesalon-header',
            get_stylesheet_directory_uri() . $script_rel_path,
            array(),
            $version,
            true
        );
    }

    $reading_nav_script_rel = '/assets/js/reading-nav.js';
    $reading_nav_script_abs = $theme_dir . $reading_nav_script_rel;
    if (file_exists($reading_nav_script_abs) && ($is_schoneweide_page || is_singular(array('publication', 'projekt')))) {
        wp_enqueue_script(
            'industriesalon-reading-nav',
            industriesalon_make_relative_url($theme_uri . $reading_nav_script_rel),
            array(),
            (string) filemtime($reading_nav_script_abs),
            true
        );
    }

    $single_video_script_rel = '/assets/js/single-video.js';
    $single_video_script_abs = $theme_dir . $single_video_script_rel;
    if (file_exists($single_video_script_abs) && is_singular('video')) {
        wp_enqueue_script(
            'industriesalon-single-video',
            industriesalon_make_relative_url($theme_uri . $single_video_script_rel),
            array(),
            (string) filemtime($single_video_script_abs),
            true
        );
    }

    if (is_page('sammlungen') && function_exists('iss_relations_enqueue_related_strip_script')) {
        iss_relations_enqueue_related_strip_script();
    }

}
add_action('wp_enqueue_scripts', 'industriesalon_enqueue_assets');

function industriesalon_collect_skin_style_dependencies(string $primary_handle): array
{
    $dependencies = array($primary_handle, 'industriesalon-base');

    if (wp_style_is('industriesalon-cards', 'enqueued')) {
        $dependencies[] = 'industriesalon-cards';
    }

    if (wp_style_is('industriesalon-primitives', 'enqueued')) {
        $dependencies[] = 'industriesalon-primitives';
    }

    if (wp_style_is('industriesalon-patterns', 'enqueued')) {
        $dependencies[] = 'industriesalon-patterns';
    }

    if (wp_style_is('industriesalon-gestures', 'enqueued')) {
        $dependencies[] = 'industriesalon-gestures';
    }

    return array_values(array_unique($dependencies));
}

function industriesalon_enqueue_timeline_skin(): void
{
    $relative_path = '/assets/css/timeline-skin.css';
    $absolute_path = get_stylesheet_directory() . $relative_path;

    if (!file_exists($absolute_path)) {
        return;
    }

    wp_enqueue_style(
        'industriesalon-timeline-skin',
        get_stylesheet_directory_uri() . $relative_path,
        industriesalon_collect_skin_style_dependencies('iss-timeline'),
        (string) filemtime($absolute_path)
    );
}
add_action('iss_programm_timeline_assets_enqueued', 'industriesalon_enqueue_timeline_skin');

function industriesalon_enqueue_tour_calendar_skin(): void
{
    $relative_path = '/assets/css/tour-calendar-skin.css';
    $absolute_path = get_stylesheet_directory() . $relative_path;

    if (!file_exists($absolute_path)) {
        return;
    }

    wp_enqueue_style(
        'industriesalon-tour-calendar-skin',
        get_stylesheet_directory_uri() . $relative_path,
        industriesalon_collect_skin_style_dependencies('is-tour-calendar'),
        (string) filemtime($absolute_path)
    );
}
add_action('iss_programm_calendar_assets_enqueued', 'industriesalon_enqueue_tour_calendar_skin');

function industriesalon_enqueue_fuehrungen_skin(): void
{
    $relative_path = '/assets/css/fuehrungen-skin.css';
    $absolute_path = get_stylesheet_directory() . $relative_path;

    if (!file_exists($absolute_path)) {
        return;
    }

    wp_enqueue_style(
        'industriesalon-fuehrungen-skin',
        get_stylesheet_directory_uri() . $relative_path,
        industriesalon_collect_skin_style_dependencies('iss-fuehrungen'),
        (string) filemtime($absolute_path)
    );
}
add_action('iss_fuehrungen_assets_enqueued', 'industriesalon_enqueue_fuehrungen_skin');

function industriesalon_get_next_menu_event(): ?WP_Post
{
    if (!function_exists('iss_content_model_veranstaltungen_next_menu_event')) {
        return null;
    }

    return iss_content_model_veranstaltungen_next_menu_event();
}

function industriesalon_format_menu_event_datetime(WP_Post $event): string
{
    $start = (string) get_post_meta((int) $event->ID, 'iss_start_datetime', true);
    if ($start === '') {
        return '';
    }

    $datetime = date_create_immutable_from_format('Y-m-d H:i:s', $start, wp_timezone());
    if (!$datetime instanceof DateTimeImmutable) {
        return '';
    }

    if (function_exists('iss_programm_format_date_long_de')) {
        $date = iss_programm_format_date_long_de($datetime->getTimestamp(), wp_timezone());
    } else {
        $date = wp_date('j. F Y', $datetime->getTimestamp(), wp_timezone());
    }

    $time = wp_date('H:i', $datetime->getTimestamp(), wp_timezone());

    return $date . ', ' . $time . ' Uhr';
}

function industriesalon_render_menu_status_block(): string
{
    $visit_status = '';
    if (class_exists('Industriesalon_Steuerung')) {
        $control = Industriesalon_Steuerung::instance();
        if (method_exists($control, 'render_visit_status')) {
            $visit_status = trim(wp_strip_all_tags($control->render_visit_status('public', [
                'variant' => 'footer',
                'skin' => 'default',
            ])));
            $visit_status = (string) preg_replace('/^Heute\s+/u', '', $visit_status);
        }
    }

    $event = industriesalon_get_next_menu_event();

    if ($visit_status === '' && !$event instanceof WP_Post) {
        return '';
    }

    ob_start();
    ?>
    <div class="iss-menu-shell__status">
        <?php if ($visit_status !== '') : ?>
            <a class="iss-menu-shell__status-item" href="/#besuch">
                <span><?php echo esc_html__('Heute', 'industriesalon'); ?></span>
                <strong><?php echo esc_html($visit_status); ?></strong>
            </a>
        <?php endif; ?>
        <?php if ($event instanceof WP_Post) : ?>
            <a class="iss-menu-shell__status-item" href="<?php echo esc_url(get_permalink($event)); ?>">
                <span><?php echo esc_html__('Nächste Veranstaltung', 'industriesalon'); ?></span>
                <strong><?php echo esc_html(get_the_title($event)); ?></strong>
                <?php $event_datetime = industriesalon_format_menu_event_datetime($event); ?>
                <?php if ($event_datetime !== '') : ?>
                    <em><?php echo esc_html($event_datetime); ?></em>
                <?php endif; ?>
            </a>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function industriesalon_register_menu_status_block(): void
{
    register_block_type('industriesalon/menu-status', [
        'api_version' => 2,
        'render_callback' => 'industriesalon_render_menu_status_block',
    ]);
}
add_action('init', 'industriesalon_register_menu_status_block');

function industriesalon_render_menu_shell(): void
{
    if (is_admin()) {
        return;
    }

    $menu_shell_path = get_stylesheet_directory() . '/assets/menu-shell.html';
    if (!file_exists($menu_shell_path)) {
        return;
    }

    $content = file_get_contents($menu_shell_path);
    if ($content === false || trim($content) === '') {
        return;
    }

    echo wp_kses_post(do_blocks($content));
}
add_action('wp_footer', 'industriesalon_render_menu_shell', 5);

function industriesalon_render_search_modal(): void
{
    if (is_admin()) {
        return;
    }

    ?>
    <div class="iss-search-modal" data-iss-search-modal data-endpoint="<?php echo esc_url(rest_url('iss/v1/search')); ?>" data-search-url="<?php echo esc_url(home_url('/')); ?>" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="iss-search-modal-title">
        <div class="iss-search-modal__overlay" data-iss-search-close></div>
        <div class="iss-search-modal__panel">
            <div class="iss-search-modal__header">
                <p class="iss-kicker iss-kicker--compact" id="iss-search-modal-title"><?php echo esc_html__('Suche', 'industriesalon'); ?></p>
                <button class="iss-search-modal__close" type="button" data-iss-search-close aria-label="<?php echo esc_attr__('Suche schließen', 'industriesalon'); ?>">×</button>
            </div>
            <form class="iss-search-modal__form" role="search" action="<?php echo esc_url(home_url('/')); ?>" method="get" data-iss-search-form>
                <label class="screen-reader-text" for="iss-search-modal-input"><?php echo esc_html__('Suchbegriff', 'industriesalon'); ?></label>
                <input id="iss-search-modal-input" class="iss-search-modal__input" type="search" name="s" autocomplete="off" placeholder="<?php echo esc_attr__('Suche nach Orten, Veranstaltungen, Objekten, Publikationen...', 'industriesalon'); ?>" data-iss-search-input>
            </form>
            <div class="iss-search-modal__status" data-iss-search-status><?php echo esc_html__('Suchbegriff eingeben.', 'industriesalon'); ?></div>
            <div class="iss-search-modal__results" data-iss-search-results></div>
            <div class="iss-search-modal__footer">
                <button class="iss-search-modal__all" type="button" data-iss-search-all><?php echo esc_html__('Alle Ergebnisse anzeigen', 'industriesalon'); ?></button>
                <span><?php echo esc_html__('Esc schließt', 'industriesalon'); ?></span>
            </div>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'industriesalon_render_search_modal', 6);

function industriesalon_make_relative_url(string $url): string
{
    if ($url === '') {
        return '';
    }

    $parts = wp_parse_url($url);
    if (!is_array($parts)) {
        return $url;
    }

    $relative = $parts['path'] ?? '';

    if (isset($parts['query']) && $parts['query'] !== '') {
        $relative .= '?' . $parts['query'];
    }

    if (isset($parts['fragment']) && $parts['fragment'] !== '') {
        $relative .= '#' . $parts['fragment'];
    }

    return $relative !== '' ? $relative : '/';
}

add_filter('script_loader_src', function ($src, $handle) {
    if ($handle !== 'industriesalon-schoneweide' || !is_string($src) || $src === '') {
        return $src;
    }

    return industriesalon_make_relative_url($src);
}, 10, 2);
