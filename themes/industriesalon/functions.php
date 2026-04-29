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
 * Single post layout variants (standard / image / short).
 */
function industriesalon_post_layout_choices(): array
{
    return array('standard', 'image', 'short');
}

function industriesalon_sanitize_post_layout($value): string
{
    $value = sanitize_key((string) $value);
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
    { label: 'Standard (Bild im Container)', value: 'standard' },
    { label: 'Bildfokus (Hero Full Width)', value: 'image' },
    { label: 'Kurze Meldung (kompakt)', value: 'short' }
  ];

  function isValidLayout(value) {
    return value === 'standard' || value === 'image' || value === 'short';
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

    var value = isValidLayout(meta._iss_post_layout) ? meta._iss_post_layout : 'standard';

    return createElement(
      PluginDocumentSettingPanel,
      { name: 'iss-post-layout', title: 'Beitragslayout', className: 'iss-post-layout-panel' },
      createElement(SelectControl, {
        label: 'Layout',
        value: value,
        options: options,
        help: 'Wählt die Darstellung für diesen Beitrag im Frontend.',
        onChange: function (nextValue) {
          if (!isValidLayout(nextValue)) {
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

/**
 * Enqueue theme assets.
 */
function industriesalon_enqueue_assets(): void
{
    $theme_dir = get_stylesheet_directory();
    $theme_uri = get_stylesheet_directory_uri();
    $theme = wp_get_theme();
    $version = $theme->get('Version');

    wp_enqueue_style(
        'industriesalon-base',
        get_stylesheet_uri(),
        array(),
        $version
    );

    $cards_rel = '/assets/css/cards.css';
    $cards_abs = $theme_dir . $cards_rel;
    if (file_exists($cards_abs)) {
        wp_enqueue_style(
            'industriesalon-cards',
            $theme_uri . $cards_rel,
            array('industriesalon-base'),
            (string) filemtime($cards_abs)
        );
    }

    $patterns_rel = '/assets/css/patterns.css';
    $patterns_abs = $theme_dir . $patterns_rel;
    if (file_exists($patterns_abs)) {
        $patterns_dependencies = file_exists($cards_abs)
            ? array('industriesalon-cards')
            : array('industriesalon-base');

        wp_enqueue_style(
            'industriesalon-patterns',
            $theme_uri . $patterns_rel,
            $patterns_dependencies,
            (string) filemtime($patterns_abs)
        );
    }

    $overrides_rel = '/assets/css/overrides.css';
    $overrides_abs = $theme_dir . $overrides_rel;
    if (file_exists($overrides_abs)) {
        $overrides_dependencies = file_exists($patterns_abs)
            ? array('industriesalon-patterns')
            : (file_exists($cards_abs)
                ? array('industriesalon-cards')
                : array('industriesalon-base'));

        wp_enqueue_style(
            'industriesalon-overrides',
            $theme_uri . $overrides_rel,
            $overrides_dependencies,
            (string) filemtime($overrides_abs)
        );
    }

    $ueber_uns_rel = '/assets/css/ueber-uns.css';
    $ueber_uns_abs = $theme_dir . $ueber_uns_rel;
    if (file_exists($ueber_uns_abs)) {
        $ueber_uns_dependencies = file_exists($overrides_abs)
            ? array('industriesalon-overrides')
            : (file_exists($patterns_abs)
                ? array('industriesalon-patterns')
                : (file_exists($cards_abs)
                    ? array('industriesalon-cards')
                    : array('industriesalon-base')));

        wp_enqueue_style(
            'industriesalon-ueber-uns',
            $theme_uri . $ueber_uns_rel,
            $ueber_uns_dependencies,
            (string) filemtime($ueber_uns_abs)
        );
    }

    $flex_split_rel = '/assets/css/iss-flex-split.css';
    $flex_split_abs = $theme_dir . $flex_split_rel;
    if (file_exists($flex_split_abs)) {
        $flex_split_dependencies = file_exists($overrides_abs)
            ? array('industriesalon-overrides')
            : (file_exists($patterns_abs)
                ? array('industriesalon-patterns')
                : (file_exists($cards_abs)
                    ? array('industriesalon-cards')
                    : array('industriesalon-base')));

        wp_enqueue_style(
            'industriesalon-flex-split',
            $theme_uri . $flex_split_rel,
            $flex_split_dependencies,
            (string) filemtime($flex_split_abs)
        );
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
}
add_action('wp_enqueue_scripts', 'industriesalon_enqueue_assets');
