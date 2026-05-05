<?php
if (!defined('ABSPATH')) {
    exit;
}

$industriesalon_fuehrungen_filters_helper = get_stylesheet_directory() . '/assets/css/staging/industriesalon-fuehrungen-filters.php';
if (file_exists($industriesalon_fuehrungen_filters_helper)) {
    require_once $industriesalon_fuehrungen_filters_helper;
}

add_action('after_setup_theme', function () {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('editor-styles');
    add_post_type_support('page', 'excerpt');
    add_editor_style(
        array(
            'style.css',
            'assets/css/cards.css',
            'assets/css/patterns.css',
            'assets/css/overrides.css',
            'assets/css/iss-flex-split.css',
            'assets/css/ueber-uns.css',
            'assets/css/page-archive.css',
            'assets/css/page-events.css',
            'assets/css/page-museum.css',
            'assets/css/page-videos.css',
            'assets/css/page-ausstellungen.css',
            'assets/css/page-verein.css',
            'assets/css/publications.css',
            'assets/css/single-tour.css',
            'assets/css/single-ausstellung.css',
            'assets/css/single-event.css',
            'assets/css/single-content.css',
        )
    );
});

add_filter('get_site_icon_url', function ($url, $size, $blog_id) {
    $favicon_path = get_stylesheet_directory() . '/assets/img/logo-industriesalon-favicon.svg';
    if (!file_exists($favicon_path)) {
        return $url;
    }

    return get_stylesheet_directory_uri() . '/assets/img/logo-industriesalon-favicon.svg';
}, 10, 3);

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
            'name' => 'industriesalon/1to4-grid',
            'title' => 'ISS 1to4 Grid',
            'description' => 'Lead card with a compact query grid.',
            'categories' => array('industriesalon', 'text', 'media'),
            'file' => '/patterns/iss-1to4-grid.html',
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
            'name' => 'industriesalon/page-projekte-template',
            'title' => 'ISS Projekte Page Template',
            'description' => 'Projects landing page content for the dedicated projekte page.',
            'categories' => array('industriesalon', 'text'),
            'file' => '/patterns/page-projekte-template.html',
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
    if (!$term || is_wp_error($term)) {
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

/**
 * Veranstaltung layout variants (standard / compact / feature / long).
 */
function industriesalon_event_layout_choices(): array
{
    return array('standard', 'compact', 'feature', 'long');
}

function industriesalon_sanitize_event_layout($value): string
{
    $value = sanitize_key((string) $value);
    return in_array($value, industriesalon_event_layout_choices(), true) ? $value : 'standard';
}

function industriesalon_register_event_layout_meta(): void
{
    register_post_meta('veranstaltung', '_iss_event_layout', array(
        'single' => true,
        'type' => 'string',
        'default' => 'standard',
        'show_in_rest' => true,
        'sanitize_callback' => 'industriesalon_sanitize_event_layout',
        'auth_callback' => static function ($allowed = null, $meta_key = '', $post_id = 0) {
            $post_id = (int) $post_id;
            if ($post_id > 0) {
                return current_user_can('edit_post', $post_id);
            }
            return current_user_can('edit_posts');
        },
    ));
}
add_action('init', 'industriesalon_register_event_layout_meta');

function industriesalon_add_event_layout_body_class(array $classes): array
{
    if (!is_singular('veranstaltung')) {
        return $classes;
    }

    $post_id = get_queried_object_id();
    $layout = industriesalon_sanitize_event_layout(get_post_meta($post_id, '_iss_event_layout', true));
    $classes[] = 'iss-event-layout-' . $layout;

    return $classes;
}
add_filter('body_class', 'industriesalon_add_event_layout_body_class');

function industriesalon_enqueue_event_layout_editor_assets(): void
{
    if (!function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== 'veranstaltung') {
        return;
    }

    wp_register_script(
        'industriesalon-event-layout-editor',
        false,
        array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data'),
        wp_get_theme()->get('Version'),
        true
    );
    wp_enqueue_script('industriesalon-event-layout-editor');

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
    { label: 'Standard (Bild + Meta)', value: 'standard' },
    { label: 'Kompakt (kurze Meldung)', value: 'compact' },
    { label: 'Feature (editorial)', value: 'feature' },
    { label: 'Longread (viel Text)', value: 'long' }
  ];

  function isValidLayout(value) {
    return value === 'standard' || value === 'compact' || value === 'feature' || value === 'long';
  }

  function EventLayoutPanel() {
    var postType = useSelect(function (select) {
      return select('core/editor').getCurrentPostType();
    }, []);

    var meta = useSelect(function (select) {
      return select('core/editor').getEditedPostAttribute('meta') || {};
    }, []);

    var editPost = useDispatch('core/editor').editPost;

    if (postType !== 'veranstaltung') {
      return null;
    }

    var value = isValidLayout(meta._iss_event_layout) ? meta._iss_event_layout : 'standard';

    return createElement(
      PluginDocumentSettingPanel,
      { name: 'iss-event-layout', title: 'Veranstaltungslayout', className: 'iss-event-layout-panel' },
      createElement(SelectControl, {
        label: 'Layout',
        value: value,
        options: options,
        help: 'Steuert Dichte, Bildgewicht und Meta-Anordnung auf der Einzelansicht.',
        onChange: function (nextValue) {
          if (!isValidLayout(nextValue)) {
            nextValue = 'standard';
          }
          editPost({ meta: Object.assign({}, meta, { _iss_event_layout: nextValue }) });
        }
      })
    );
  }

  registerPlugin('iss-event-layout-panel', {
    render: EventLayoutPanel
  });
})(window.wp);
JS;

    wp_add_inline_script('industriesalon-event-layout-editor', $script);
}
add_action('enqueue_block_editor_assets', 'industriesalon_enqueue_event_layout_editor_assets');

/**
 * Enqueue theme assets.
 */
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
        'industriesalon-base',
        get_stylesheet_uri(),
        array(),
        $base_version
    );

    $cards_loaded = $enqueue_theme_style(
        'industriesalon-cards',
        '/assets/css/cards.css',
        array('industriesalon-base')
    );

    $patterns_dependencies = $cards_loaded
        ? array('industriesalon-cards')
        : array('industriesalon-base');
    $patterns_loaded = $enqueue_theme_style(
        'industriesalon-patterns',
        '/assets/css/patterns.css',
        $patterns_dependencies
    );

    $overrides_dependencies = $patterns_loaded
        ? array('industriesalon-patterns')
        : ($cards_loaded
            ? array('industriesalon-cards')
            : array('industriesalon-base'));
    $overrides_loaded = $enqueue_theme_style(
        'industriesalon-overrides',
        '/assets/css/overrides.css',
        $overrides_dependencies
    );

    $page_dependencies = $overrides_loaded
        ? array('industriesalon-overrides')
        : ($patterns_loaded
            ? array('industriesalon-patterns')
            : ($cards_loaded
                ? array('industriesalon-cards')
                : array('industriesalon-base')));

    if (!$is_schoneweide_page) {
        $enqueue_theme_style(
            'industriesalon-flex-split',
            '/assets/css/iss-flex-split.css',
            $page_dependencies
        );
    }

    $conditional_styles = array(
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
            'handle' => 'industriesalon-page-events',
            'path' => '/assets/css/page-events.css',
            'condition' => is_page('veranstaltungen'),
        ),
        array(
            'handle' => 'industriesalon-page-museum',
            'path' => '/assets/css/page-museum.css',
            'condition' => is_page('das-museum'),
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
            'handle' => 'industriesalon-publications',
            'path' => '/assets/css/publications.css',
            'condition' => is_page('publikationen') || is_singular('publication'),
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
            'condition' => is_singular(array('post', 'archivsammlung', 'archivobjekt', 'register_place', 'team_member', 'projekt'))
                || is_post_type_archive(array('archivsammlung', 'archivobjekt')),
        ),
    );

    foreach ($conditional_styles as $style) {
        if (!$style['condition']) {
            continue;
        }

        $enqueue_theme_style($style['handle'], $style['path'], $page_dependencies);
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

    if ($is_schoneweide_page) {
        $enqueue_theme_style(
            'industriesalon-oberschoeneweide-atlas',
            '/assets/css/oberschoeneweide-atlas.css',
            $page_dependencies
        );

        $schoneweide_script_rel = '/assets/js/schoneweide.js';
        $schoneweide_script_abs = $theme_dir . $schoneweide_script_rel;

        if (file_exists($schoneweide_script_abs) && defined('ISS_REGISTER_REST_NAMESPACE')) {
            $atlas_timeline = function_exists('iss_register_get_atlas_story_public_payload')
                ? iss_register_get_atlas_story_public_payload('transformatorenwerk-oberschoeneweide')
                : array();

            wp_enqueue_script(
                'industriesalon-schoneweide',
                industriesalon_make_relative_url($theme_uri . $schoneweide_script_rel),
                array(),
                (string) filemtime($schoneweide_script_abs),
                true
            );

            wp_localize_script(
                'industriesalon-schoneweide',
                'industriesalonSchoneweide',
                array(
                    'placesUrl' => industriesalon_make_relative_url(untrailingslashit(rest_url(ISS_REGISTER_REST_NAMESPACE)) . '/atlas'),
                    'registerUrl' => industriesalon_make_relative_url(home_url('/register-schoneweide/')),
                    'featuredTimeline' => $atlas_timeline,
                )
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'industriesalon_enqueue_assets');

function industriesalon_collect_skin_style_dependencies(string $primary_handle): array
{
    $dependencies = array($primary_handle, 'industriesalon-base');

    if (wp_style_is('industriesalon-cards', 'enqueued')) {
        $dependencies[] = 'industriesalon-cards';
    }

    if (wp_style_is('industriesalon-patterns', 'enqueued')) {
        $dependencies[] = 'industriesalon-patterns';
    }

    if (wp_style_is('industriesalon-overrides', 'enqueued')) {
        $dependencies[] = 'industriesalon-overrides';
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

    echo do_blocks($content);
}
add_action('wp_footer', 'industriesalon_render_menu_shell', 5);

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
