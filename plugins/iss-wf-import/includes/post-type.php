<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_register_post_type_and_taxonomies(): void
{
    $archive_post_types = [
        ISS_WF_IMPORT_POST_TYPE,
        ISS_WF_IMPORT_COLLECTION_POST_TYPE,
        ISS_WF_IMPORT_OBJECT_POST_TYPE,
    ];

    register_taxonomy(ISS_WF_IMPORT_SOURCE_TAXONOMY, $archive_post_types, [
        'labels' => [
            'name' => __('Archivquellen', 'iss-wf-import'),
            'singular_name' => __('Archivquelle', 'iss-wf-import'),
        ],
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
    ]);

    register_taxonomy(ISS_WF_IMPORT_CATEGORY_TAXONOMY, [ISS_WF_IMPORT_POST_TYPE], [
        'labels' => [
            'name' => __('Archivkategorien', 'iss-wf-import'),
            'singular_name' => __('Archivkategorie', 'iss-wf-import'),
        ],
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => false,
    ]);

    register_taxonomy(ISS_WF_IMPORT_TAG_TAXONOMY, [ISS_WF_IMPORT_POST_TYPE], [
        'labels' => [
            'name' => __('Archivschlagwörter', 'iss-wf-import'),
            'singular_name' => __('Archivschlagwort', 'iss-wf-import'),
        ],
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
    ]);

    register_post_type(ISS_WF_IMPORT_POST_TYPE, [
        'labels' => [
            'name' => __('Archivbeiträge', 'iss-wf-import'),
            'singular_name' => __('Archivbeitrag', 'iss-wf-import'),
            'menu_name' => __('Archivbeiträge', 'iss-wf-import'),
            'add_new_item' => __('Archivbeitrag hinzufügen', 'iss-wf-import'),
            'edit_item' => __('Archivbeitrag bearbeiten', 'iss-wf-import'),
            'new_item' => __('Neuer Archivbeitrag', 'iss-wf-import'),
            'view_item' => __('Archivbeitrag ansehen', 'iss-wf-import'),
            'search_items' => __('Archivbeiträge durchsuchen', 'iss-wf-import'),
        ],
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
        'labels' => [
            'name' => __('Archivsammlungen', 'iss-wf-import'),
            'singular_name' => __('Archivsammlung', 'iss-wf-import'),
            'menu_name' => __('Sammlungen', 'iss-wf-import'),
            'add_new_item' => __('Archivsammlung hinzufügen', 'iss-wf-import'),
            'edit_item' => __('Archivsammlung bearbeiten', 'iss-wf-import'),
            'new_item' => __('Neue Archivsammlung', 'iss-wf-import'),
            'view_item' => __('Archivsammlung ansehen', 'iss-wf-import'),
            'search_items' => __('Archivsammlungen durchsuchen', 'iss-wf-import'),
        ],
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
        'labels' => [
            'name' => __('Archivobjekte', 'iss-wf-import'),
            'singular_name' => __('Archivobjekt', 'iss-wf-import'),
            'menu_name' => __('Objekte', 'iss-wf-import'),
            'add_new_item' => __('Archivobjekt hinzufügen', 'iss-wf-import'),
            'edit_item' => __('Archivobjekt bearbeiten', 'iss-wf-import'),
            'new_item' => __('Neues Archivobjekt', 'iss-wf-import'),
            'view_item' => __('Archivobjekt ansehen', 'iss-wf-import'),
            'search_items' => __('Archivobjekte durchsuchen', 'iss-wf-import'),
        ],
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
