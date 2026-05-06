<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Editor-facing labels can evolve, but stored slugs and constants stay stable.
 * Keep label generation in one place so future WP upgrades only require one
 * review when admin copy needs to change.
 */
function iss_wf_import_build_post_type_labels(array $args): array
{
    $plural = (string) ($args['plural'] ?? '');
    $singular = (string) ($args['singular'] ?? '');
    $menu_name = (string) ($args['menu_name'] ?? $plural);
    $text_domain = (string) ($args['text_domain'] ?? 'iss-wf-import');

    return [
        'name' => __($plural, $text_domain),
        'singular_name' => __($singular, $text_domain),
        'menu_name' => __($menu_name, $text_domain),
        'all_items' => sprintf(__('Alle %s', $text_domain), __($plural, $text_domain)),
        'add_new' => __('Neu hinzufügen', $text_domain),
        'add_new_item' => sprintf(__('%s hinzufügen', $text_domain), __($singular, $text_domain)),
        'edit_item' => sprintf(__('%s bearbeiten', $text_domain), __($singular, $text_domain)),
        'new_item' => sprintf(__('Neuer Eintrag: %s', $text_domain), __($singular, $text_domain)),
        'view_item' => sprintf(__('%s ansehen', $text_domain), __($singular, $text_domain)),
        'view_items' => sprintf(__('%s ansehen', $text_domain), __($plural, $text_domain)),
        'search_items' => sprintf(__('%s durchsuchen', $text_domain), __($plural, $text_domain)),
        'not_found' => sprintf(__('Keine %s gefunden.', $text_domain), mb_strtolower($plural)),
        'not_found_in_trash' => sprintf(__('Keine %s im Papierkorb gefunden.', $text_domain), mb_strtolower($plural)),
        'archives' => sprintf(__('%s-Archiv', $text_domain), __($singular, $text_domain)),
        'featured_image' => __('Beitragsbild', $text_domain),
        'set_featured_image' => __('Beitragsbild festlegen', $text_domain),
        'remove_featured_image' => __('Beitragsbild entfernen', $text_domain),
        'use_featured_image' => __('Als Beitragsbild verwenden', $text_domain),
        'items_list' => sprintf(__('%s-Liste', $text_domain), __($plural, $text_domain)),
        'items_list_navigation' => sprintf(__('Navigation für %s-Liste', $text_domain), __($plural, $text_domain)),
        'filter_items_list' => sprintf(__('%s-Liste filtern', $text_domain), __($plural, $text_domain)),
    ];
}

function iss_wf_import_build_taxonomy_labels(string $plural, string $singular): array
{
    return [
        'name' => __($plural, 'iss-wf-import'),
        'singular_name' => __($singular, 'iss-wf-import'),
        'search_items' => sprintf(__('%s durchsuchen', 'iss-wf-import'), __($plural, 'iss-wf-import')),
        'all_items' => sprintf(__('Alle %s', 'iss-wf-import'), __($plural, 'iss-wf-import')),
        'edit_item' => sprintf(__('%s bearbeiten', 'iss-wf-import'), __($singular, 'iss-wf-import')),
        'update_item' => sprintf(__('%s aktualisieren', 'iss-wf-import'), __($singular, 'iss-wf-import')),
        'add_new_item' => sprintf(__('%s hinzufügen', 'iss-wf-import'), __($singular, 'iss-wf-import')),
        'new_item_name' => sprintf(__('Neuer Name für %s', 'iss-wf-import'), __($singular, 'iss-wf-import')),
        'menu_name' => __($plural, 'iss-wf-import'),
    ];
}

function iss_wf_import_register_post_type_and_taxonomies(): void
{
    $archive_post_types = [
        ISS_WF_IMPORT_POST_TYPE,
        ISS_WF_IMPORT_COLLECTION_POST_TYPE,
        ISS_WF_IMPORT_OBJECT_POST_TYPE,
    ];

    register_taxonomy(ISS_WF_IMPORT_SOURCE_TAXONOMY, $archive_post_types, [
        'labels' => iss_wf_import_build_taxonomy_labels('Archivquellen', 'Archivquelle'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
    ]);

    register_taxonomy(ISS_WF_IMPORT_CATEGORY_TAXONOMY, [ISS_WF_IMPORT_POST_TYPE], [
        'labels' => iss_wf_import_build_taxonomy_labels('Archivkategorien', 'Archivkategorie'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => false,
    ]);

    register_taxonomy(ISS_WF_IMPORT_TAG_TAXONOMY, [ISS_WF_IMPORT_POST_TYPE], [
        'labels' => iss_wf_import_build_taxonomy_labels('Archivschlagwörter', 'Archivschlagwort'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
    ]);

    register_post_type(ISS_WF_IMPORT_POST_TYPE, [
        'labels' => iss_wf_import_build_post_type_labels([
            'plural' => 'Archivbeiträge',
            'singular' => 'Archivbeitrag',
            'menu_name' => 'Archiv',
        ]),
        'description' => __('Kuratorische Archivtexte und Hintergrundbeiträge.', 'iss-wf-import'),
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => [
            'slug' => 'archivbeitraege',
            'with_front' => false,
        ],
        'menu_icon' => 'dashicons-archive',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'taxonomies' => [
            ISS_WF_IMPORT_SOURCE_TAXONOMY,
            ISS_WF_IMPORT_CATEGORY_TAXONOMY,
            ISS_WF_IMPORT_TAG_TAXONOMY,
        ],
    ]);

    register_post_type(ISS_WF_IMPORT_COLLECTION_POST_TYPE, [
        'labels' => iss_wf_import_build_post_type_labels([
            'plural' => 'Archivsammlungen',
            'singular' => 'Archivsammlung',
            'menu_name' => 'Sammlungen',
        ]),
        'description' => __('Archivsammlungen, Alben und geordnete Objektgruppen.', 'iss-wf-import'),
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=' . ISS_WF_IMPORT_POST_TYPE,
        'show_in_rest' => true,
        'has_archive' => 'archivsammlungen',
        'rewrite' => [
            'slug' => 'archivsammlungen',
            'with_front' => false,
        ],
        'menu_icon' => 'dashicons-portfolio',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'taxonomies' => [
            ISS_WF_IMPORT_SOURCE_TAXONOMY,
        ],
        'hierarchical' => true,
    ]);

    register_post_type(ISS_WF_IMPORT_OBJECT_POST_TYPE, [
        'labels' => iss_wf_import_build_post_type_labels([
            'plural' => 'Archivobjekte',
            'singular' => 'Archivobjekt',
            'menu_name' => 'Objekte',
        ]),
        'description' => __('Einzelobjekte des Archivs mit Metadaten und Medien.', 'iss-wf-import'),
        'public' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => 'edit.php?post_type=' . ISS_WF_IMPORT_POST_TYPE,
        'show_in_rest' => true,
        'has_archive' => 'archivobjekte',
        'rewrite' => [
            'slug' => 'archivobjekte',
            'with_front' => false,
        ],
        'menu_icon' => 'dashicons-format-image',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'taxonomies' => [
            ISS_WF_IMPORT_SOURCE_TAXONOMY,
        ],
    ]);
}
add_action('init', 'iss_wf_import_register_post_type_and_taxonomies', 10);

function iss_wf_import_ensure_source_term_by_slug(string $slug, string $label): int
{
    $slug = sanitize_title($slug);
    $label = sanitize_text_field($label);

    if ($slug === '' || $label === '') {
        return 0;
    }

    $term = get_term_by('slug', $slug, ISS_WF_IMPORT_SOURCE_TAXONOMY);
    if ($term instanceof WP_Term) {
        return (int) $term->term_id;
    }

    $created = wp_insert_term($label, ISS_WF_IMPORT_SOURCE_TAXONOMY, [
        'slug' => $slug,
    ]);

    if (is_wp_error($created)) {
        $term = get_term_by('slug', $slug, ISS_WF_IMPORT_SOURCE_TAXONOMY);
        return $term instanceof WP_Term ? (int) $term->term_id : 0;
    }

    return (int) ($created['term_id'] ?? 0);
}

function iss_wf_import_ensure_source_term(): int
{
    return iss_wf_import_ensure_source_term_by_slug('wf-museum', 'WF-Museum');
}

function iss_wf_import_add_to_relations_post_types(array $post_types): array
{
    $post_types[] = ISS_WF_IMPORT_POST_TYPE;
    $post_types[] = ISS_WF_IMPORT_COLLECTION_POST_TYPE;
    $post_types[] = ISS_WF_IMPORT_OBJECT_POST_TYPE;

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));
}
add_filter('iss_relations_candidate_post_types', 'iss_wf_import_add_to_relations_post_types');
