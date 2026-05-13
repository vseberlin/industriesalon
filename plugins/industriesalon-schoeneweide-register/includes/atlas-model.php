<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Phase 1 atlas contract:
 * - `register_place` remains the map anchor and existing source of truth.
 * - `atlas_story` adds an editorial narrative layer without replacing current atlas data.
 * - `atlas_era` adds explicit editorial classification but does not break the
 *   current frontend contract that still expects legacy era buckets like `1960-1990`.
 *
 * The current `/atlas` payload therefore keeps legacy `era_id`/`era_label` values.
 * New semantic era slugs such as `ddr` or `kaiserzeit` are exposed alongside them.
 */

define('ISS_REGISTER_ATLAS_ERA_TAXONOMY', 'atlas_era');
define('ISS_REGISTER_ATLAS_STORY_POST_TYPE', 'atlas_story');
define('ISS_REGISTER_ATLAS_ERA_SEED_VERSION', '2026-05-06-phase-1');

/**
 * Stable semantic eras for editorial use.
 *
 * `legacy_id` and related labels preserve the existing atlas frontend contract.
 * Future atlas UI can switch to `slug`/`name` directly without breaking phase 1.
 */
function iss_register_get_atlas_era_definitions(): array
{
    return [
        'kaiserzeit' => [
            'slug' => 'kaiserzeit',
            'name' => 'Kaiserzeit',
            'caption' => 'Elektropolis im Aufbau',
            'legacy_id' => '1890-1910',
            'legacy_label' => '1890–1910',
            'legacy_short_label' => '1890',
            'legacy_caption' => 'Aufbruch',
        ],
        'weimar' => [
            'slug' => 'weimar',
            'name' => 'Weimarer Republik',
            'caption' => 'Industrie und Moderne',
            'legacy_id' => '1910-1930',
            'legacy_label' => '1910–1930',
            'legacy_short_label' => '1910',
            'legacy_caption' => 'Wachstum',
        ],
        'ns-zeit' => [
            'slug' => 'ns-zeit',
            'name' => 'NS-Zeit',
            'caption' => 'Krieg und Gewalt',
            'legacy_id' => '1930-1945',
            'legacy_label' => '1930–1945',
            'legacy_short_label' => '1930',
            'legacy_caption' => 'Krise und Krieg',
        ],
        'nachkriegszeit' => [
            'slug' => 'nachkriegszeit',
            'name' => 'Nachkriegszeit',
            'caption' => 'Besatzung und Neubeginn',
            'legacy_id' => '1945-1960',
            'legacy_label' => '1945–1960',
            'legacy_short_label' => '1945',
            'legacy_caption' => 'Neubeginn',
        ],
        'ddr' => [
            'slug' => 'ddr',
            'name' => 'DDR',
            'caption' => 'Produktion und Betriebskultur',
            'legacy_id' => '1960-1990',
            'legacy_label' => '1960–1990',
            'legacy_short_label' => '1960',
            'legacy_caption' => 'Sozialistische Industrie',
        ],
        'nach-1990' => [
            'slug' => 'nach-1990',
            'name' => 'Nach 1990',
            'caption' => 'Transformation',
            'legacy_id' => 'heute',
            'legacy_label' => '1990–heute',
            'legacy_short_label' => 'Heute',
            'legacy_caption' => 'Transformation',
        ],
    ];
}

function iss_register_get_atlas_era_term_seed(): array
{
    $definitions = iss_register_get_atlas_era_definitions();
    $terms = [];

    foreach ($definitions as $definition) {
        $terms[] = [
            'slug' => $definition['slug'],
            'name' => $definition['name'],
            'description' => $definition['caption'],
        ];
    }

    return $terms;
}

function iss_register_register_atlas_era_taxonomy(): void
{
    register_taxonomy(ISS_REGISTER_ATLAS_ERA_TAXONOMY, [ISS_REGISTER_POST_TYPE, ISS_REGISTER_ATLAS_STORY_POST_TYPE], [
        'labels' => [
            'name' => __('Atlas-Epochen', 'industriesalon-schoeneweide-register'),
            'singular_name' => __('Atlas-Epoche', 'industriesalon-schoeneweide-register'),
            'menu_name' => __('Atlas-Epochen', 'industriesalon-schoeneweide-register'),
            'all_items' => __('Alle Epochen', 'industriesalon-schoeneweide-register'),
            'edit_item' => __('Epoche bearbeiten', 'industriesalon-schoeneweide-register'),
            'view_item' => __('Epoche ansehen', 'industriesalon-schoeneweide-register'),
            'update_item' => __('Epoche aktualisieren', 'industriesalon-schoeneweide-register'),
            'add_new_item' => __('Epoche hinzufügen', 'industriesalon-schoeneweide-register'),
            'new_item_name' => __('Neue Epoche', 'industriesalon-schoeneweide-register'),
            'search_items' => __('Epochen durchsuchen', 'industriesalon-schoeneweide-register'),
        ],
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
        'query_var' => false,
    ]);
}
add_action('init', 'iss_register_register_atlas_era_taxonomy', 12);

function iss_register_register_atlas_story_post_type(): void
{
    register_post_type(ISS_REGISTER_ATLAS_STORY_POST_TYPE, [
        'labels' => [
            'name' => __('Atlas-Geschichten', 'industriesalon-schoeneweide-register'),
            'singular_name' => __('Atlas-Geschichte', 'industriesalon-schoeneweide-register'),
            'menu_name' => __('Atlas-Geschichten', 'industriesalon-schoeneweide-register'),
            'add_new_item' => __('Atlas-Geschichte hinzufügen', 'industriesalon-schoeneweide-register'),
            'edit_item' => __('Atlas-Geschichte bearbeiten', 'industriesalon-schoeneweide-register'),
            'new_item' => __('Neue Atlas-Geschichte', 'industriesalon-schoeneweide-register'),
            'view_item' => __('Atlas-Geschichte ansehen', 'industriesalon-schoeneweide-register'),
            'search_items' => __('Atlas-Geschichten durchsuchen', 'industriesalon-schoeneweide-register'),
        ],
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=' . ISS_REGISTER_POST_TYPE,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => [
            'slug' => 'atlas-geschichten',
            'with_front' => false,
        ],
        'menu_icon' => 'dashicons-book-alt',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'taxonomies' => [ISS_REGISTER_ATLAS_ERA_TAXONOMY],
    ]);
}
add_action('init', 'iss_register_register_atlas_story_post_type', 12);

function iss_register_seed_atlas_era_terms(): void
{
    if (!taxonomy_exists(ISS_REGISTER_ATLAS_ERA_TAXONOMY)) {
        return;
    }

    $stored = (string) get_option('iss_register_atlas_era_seed_version', '');
    if ($stored === ISS_REGISTER_ATLAS_ERA_SEED_VERSION) {
        return;
    }

    foreach (iss_register_get_atlas_era_term_seed() as $term) {
        $slug = sanitize_title((string) ($term['slug'] ?? ''));
        $name = trim((string) ($term['name'] ?? ''));

        if ($slug === '' || $name === '') {
            continue;
        }

        if (term_exists($slug, ISS_REGISTER_ATLAS_ERA_TAXONOMY)) {
            continue;
        }

        wp_insert_term($name, ISS_REGISTER_ATLAS_ERA_TAXONOMY, [
            'slug' => $slug,
            'description' => (string) ($term['description'] ?? ''),
        ]);
    }

    update_option('iss_register_atlas_era_seed_version', ISS_REGISTER_ATLAS_ERA_SEED_VERSION, false);
}
add_action('init', 'iss_register_seed_atlas_era_terms', 30);

function iss_register_add_atlas_story_to_place_relations(array $post_types): array
{
    $post_types[] = ISS_REGISTER_ATLAS_STORY_POST_TYPE;
    $post_types[] = 'archivobjekt';

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));
}
add_filter('iss_relations_candidate_post_types', 'iss_register_add_atlas_story_to_place_relations');

function iss_register_clear_atlas_cache_on_term_change($object_id, $terms, $tt_ids, $taxonomy): void
{
    if ($taxonomy !== ISS_REGISTER_ATLAS_ERA_TAXONOMY) {
        return;
    }

    iss_register_clear_places_cache();
}
add_action('set_object_terms', 'iss_register_clear_atlas_cache_on_term_change', 10, 4);

function iss_register_get_ordered_atlas_era_terms(int $post_id): array
{
    $terms = get_the_terms($post_id, ISS_REGISTER_ATLAS_ERA_TAXONOMY);
    if (!is_array($terms) || !$terms) {
        return [];
    }

    $definitions = iss_register_get_atlas_era_definitions();
    $order = array_keys($definitions);

    usort($terms, static function (WP_Term $left, WP_Term $right) use ($order): int {
        $left_index = array_search($left->slug, $order, true);
        $right_index = array_search($right->slug, $order, true);
        $left_index = $left_index === false ? 999 : (int) $left_index;
        $right_index = $right_index === false ? 999 : (int) $right_index;

        if ($left_index === $right_index) {
            return strcasecmp($left->name, $right->name);
        }

        return $left_index <=> $right_index;
    });

    return $terms;
}

function iss_register_get_atlas_era_definition_by_slug(string $slug): array
{
    $definitions = iss_register_get_atlas_era_definitions();
    $slug = sanitize_title($slug);

    return $definitions[$slug] ?? [];
}

function iss_register_get_editorial_era_payload_from_slug(string $slug): array
{
    $definition = iss_register_get_atlas_era_definition_by_slug($slug);
    if (!$definition) {
        return [];
    }

    return [
        'slug' => (string) $definition['slug'],
        'name' => (string) $definition['name'],
        'caption' => (string) $definition['caption'],
        'legacy_id' => (string) $definition['legacy_id'],
        'legacy_label' => (string) $definition['legacy_label'],
        'legacy_short_label' => (string) $definition['legacy_short_label'],
        'legacy_caption' => (string) $definition['legacy_caption'],
    ];
}

function iss_register_map_legacy_era_to_editorial_slug(string $legacy_id): string
{
    $legacy_id = trim($legacy_id);
    if ($legacy_id === '') {
        return '';
    }

    foreach (iss_register_get_atlas_era_definitions() as $definition) {
        if (($definition['legacy_id'] ?? '') === $legacy_id) {
            return (string) $definition['slug'];
        }
    }

    return '';
}

function iss_register_get_explicit_era_payloads_for_post(int $post_id): array
{
    $payloads = [];

    foreach (iss_register_get_ordered_atlas_era_terms($post_id) as $term) {
        $payload = iss_register_get_editorial_era_payload_from_slug((string) $term->slug);
        if ($payload) {
            $payloads[] = $payload;
        }
    }

    return $payloads;
}

function iss_register_get_primary_explicit_era_payload_for_post(int $post_id): array
{
    $payloads = iss_register_get_explicit_era_payloads_for_post($post_id);

    return $payloads[0] ?? [];
}

function iss_register_map_story_post_for_rest(WP_Post $post): array
{
    $post_id = (int) $post->ID;
    $eras = iss_register_get_explicit_era_payloads_for_post($post_id);
    $featured_image_url = get_the_post_thumbnail_url($post, 'large');
    $place_ids = function_exists('iss_relations_get_context_place_ids')
        ? iss_relations_get_context_place_ids($post_id)
        : [];

    return [
        'id' => $post_id,
        'slug' => (string) $post->post_name,
        'title' => get_the_title($post),
        'excerpt' => (string) get_post_field('post_excerpt', $post_id),
        'permalink' => (string) get_permalink($post),
        'featured_image_url' => is_string($featured_image_url) ? $featured_image_url : '',
        'era_slugs' => array_values(array_map(static function (array $era): string {
            return (string) ($era['slug'] ?? '');
        }, $eras)),
        'era_legacy_ids' => array_values(array_map(static function (array $era): string {
            return (string) ($era['legacy_id'] ?? '');
        }, $eras)),
        'place_ids' => array_values(array_map('absint', is_array($place_ids) ? $place_ids : [])),
    ];
}

function iss_register_get_editorial_atlas_story_items(string $era_slug = ''): array
{
    if (!post_type_exists(ISS_REGISTER_ATLAS_STORY_POST_TYPE)) {
        return [];
    }

    $query_args = [
        'post_type' => ISS_REGISTER_ATLAS_STORY_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => [
            'menu_order' => 'ASC',
            'title' => 'ASC',
        ],
        'suppress_filters' => true,
    ];

    $era_slug = sanitize_title($era_slug);
    if ($era_slug !== '') {
        $query_args['tax_query'] = [
            [
                'taxonomy' => ISS_REGISTER_ATLAS_ERA_TAXONOMY,
                'field' => 'slug',
                'terms' => [$era_slug],
            ],
        ];
    }

    $posts = get_posts($query_args);
    if (!$posts) {
        return [];
    }

    return array_values(array_map('iss_register_map_story_post_for_rest', $posts));
}

/**
 * Additive REST context for future atlas UI.
 *
 * This route is intentionally separate from `/atlas` so current frontend code
 * keeps working unchanged while phase 1 editorial data is introduced.
 */
function iss_register_get_atlas_context_data(): array
{
    $places = iss_register_get_atlas_places_data();
    $stories = iss_register_get_editorial_atlas_story_items();
    $actors = function_exists('iss_register_get_industry_actor_service')
        ? iss_register_get_industry_actor_service()->build_actor_context_from_places($places)
        : [];
    $place_counts = [];
    $story_counts = [];

    foreach ($places as $place) {
        $legacy_id = (string) ($place['era_id'] ?? '');
        $slug = (string) ($place['era_slug'] ?? iss_register_map_legacy_era_to_editorial_slug($legacy_id));
        if ($slug === '') {
            continue;
        }
        $place_counts[$slug] = ($place_counts[$slug] ?? 0) + 1;
    }

    foreach ($stories as $story) {
        foreach ((array) ($story['era_slugs'] ?? []) as $slug) {
            $slug = sanitize_title((string) $slug);
            if ($slug === '') {
                continue;
            }
            $story_counts[$slug] = ($story_counts[$slug] ?? 0) + 1;
        }
    }

    $eras = [];
    foreach (iss_register_get_atlas_era_definitions() as $definition) {
        $slug = (string) $definition['slug'];
        $eras[] = [
            'slug' => $slug,
            'name' => (string) $definition['name'],
            'caption' => (string) $definition['caption'],
            'legacy_id' => (string) $definition['legacy_id'],
            'legacy_label' => (string) $definition['legacy_label'],
            'legacy_short_label' => (string) $definition['legacy_short_label'],
            'legacy_caption' => (string) $definition['legacy_caption'],
            'place_count' => (int) ($place_counts[$slug] ?? 0),
            'story_count' => (int) ($story_counts[$slug] ?? 0),
        ];
    }

    return [
        'eras' => $eras,
        'actors' => $actors,
        'stories' => $stories,
    ];
}
