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
    $archive_taxonomy_capabilities = [
        'manage_terms' => 'iss_manage_archive',
        'edit_terms' => 'iss_manage_archive',
        'delete_terms' => 'iss_manage_archive',
        'assign_terms' => 'edit_archive_items',
    ];

    register_taxonomy(ISS_WF_IMPORT_SOURCE_TAXONOMY, $archive_post_types, [
        'labels' => iss_wf_import_build_taxonomy_labels('Archivquellen', 'Archivquelle'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
        'capabilities' => $archive_taxonomy_capabilities,
    ]);

    register_taxonomy(ISS_WF_IMPORT_CATEGORY_TAXONOMY, [ISS_WF_IMPORT_POST_TYPE], [
        'labels' => iss_wf_import_build_taxonomy_labels('Archivkategorien', 'Archivkategorie'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => false,
        'capabilities' => $archive_taxonomy_capabilities,
    ]);

    register_taxonomy(ISS_WF_IMPORT_TAG_TAXONOMY, [ISS_WF_IMPORT_POST_TYPE], [
        'labels' => iss_wf_import_build_taxonomy_labels('Archivschlagwörter', 'Archivschlagwort'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => false,
        'rewrite' => false,
        'capabilities' => $archive_taxonomy_capabilities,
    ]);

    register_taxonomy(ISS_WF_IMPORT_FIELD_TAXONOMY, [ISS_WF_IMPORT_OBJECT_POST_TYPE], [
        'labels' => iss_wf_import_build_taxonomy_labels('Themenfelder', 'Themenfeld'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => false,
        'capabilities' => $archive_taxonomy_capabilities,
    ]);

    register_taxonomy(ISS_WF_IMPORT_FAMILY_TAXONOMY, [ISS_WF_IMPORT_OBJECT_POST_TYPE], [
        'labels' => iss_wf_import_build_taxonomy_labels('Objektfamilien', 'Objektfamilie'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => false,
        'capabilities' => $archive_taxonomy_capabilities,
    ]);

    register_taxonomy(ISS_WF_IMPORT_CONTEXT_TAXONOMY, [ISS_WF_IMPORT_OBJECT_POST_TYPE], [
        'labels' => iss_wf_import_build_taxonomy_labels('Kontexte', 'Kontext'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => false,
        'capabilities' => $archive_taxonomy_capabilities,
    ]);

    register_taxonomy(ISS_WF_IMPORT_DECADE_TAXONOMY, [ISS_WF_IMPORT_OBJECT_POST_TYPE], [
        'labels' => iss_wf_import_build_taxonomy_labels('Dekaden', 'Dekade'),
        'public' => true,
        'show_ui' => true,
        'show_in_rest' => true,
        'hierarchical' => true,
        'rewrite' => false,
        'capabilities' => $archive_taxonomy_capabilities,
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
        'show_in_menu' => defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => [
            'slug' => 'archivbeitraege',
            'with_front' => false,
        ],
        'menu_icon' => 'dashicons-archive',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'capability_type' => ['archive_item', 'archive_items'],
        'capabilities' => function_exists('iss_core_post_type_capabilities')
            ? iss_core_post_type_capabilities('archive_item', 'archive_items', 'create_archive_items')
            : [],
        'map_meta_cap' => true,
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
        'has_archive' => false,
        'rewrite' => [
            'slug' => 'archivsammlungen',
            'with_front' => false,
        ],
        'menu_icon' => 'dashicons-portfolio',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'capability_type' => ['archive_item', 'archive_items'],
        'capabilities' => function_exists('iss_core_post_type_capabilities')
            ? iss_core_post_type_capabilities('archive_item', 'archive_items', 'create_archive_items')
            : [],
        'map_meta_cap' => true,
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
        'has_archive' => false,
        'rewrite' => [
            'slug' => 'archivobjekte',
            'with_front' => false,
        ],
        'menu_icon' => 'dashicons-format-image',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'capability_type' => ['archive_item', 'archive_items'],
        'capabilities' => function_exists('iss_core_post_type_capabilities')
            ? iss_core_post_type_capabilities('archive_item', 'archive_items', 'create_archive_items')
            : [],
        'map_meta_cap' => true,
        'taxonomies' => [
            ISS_WF_IMPORT_SOURCE_TAXONOMY,
        ],
    ]);
}
add_action('init', 'iss_wf_import_register_post_type_and_taxonomies', 10);

function iss_wf_import_use_block_editor_for_archive_object_post_type(bool $use_block_editor, string $post_type): bool
{
    if ($post_type === ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return true;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'iss_wf_import_use_block_editor_for_archive_object_post_type', 1000, 2);

function iss_wf_import_use_block_editor_for_archive_object_post(bool $use_block_editor, $post): bool
{
    if (is_numeric($post)) {
        $post = get_post((int) $post);
    }

    if ($post instanceof WP_Post && $post->post_type === ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return true;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post', 'iss_wf_import_use_block_editor_for_archive_object_post', 1000, 2);

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

function iss_wf_import_ensure_taxonomy_term(string $taxonomy, string $slug, string $label): int
{
    $taxonomy = sanitize_key($taxonomy);
    $slug = sanitize_title($slug);
    $label = sanitize_text_field($label);

    if ($taxonomy === '' || $slug === '' || $label === '' || !taxonomy_exists($taxonomy)) {
        return 0;
    }

    $term = get_term_by('slug', $slug, $taxonomy);
    if ($term instanceof WP_Term) {
        return (int) $term->term_id;
    }

    $created = wp_insert_term($label, $taxonomy, [
        'slug' => $slug,
    ]);

    if (is_wp_error($created)) {
        $term = get_term_by('slug', $slug, $taxonomy);
        return $term instanceof WP_Term ? (int) $term->term_id : 0;
    }

    return (int) ($created['term_id'] ?? 0);
}

function iss_wf_import_ensure_museum_digital_terms(): void
{
    $taxonomy_terms = [
        ISS_WF_IMPORT_FIELD_TAXONOMY => [
            'roehre' => 'Röhre',
            'halbleiter' => 'Halbleiter',
            'anlage' => 'Anlage',
            'arbeitsplatz' => 'Arbeitsplatz',
            'automat' => 'Automat',
            'geraete-bauteile' => 'Geräte / Bauteile',
            'telekommunikation-fernsehtechnik' => 'Telekommunikation / Fernsehtechnik',
            'diverses' => 'Diverses',
            'schaltbild' => 'Schaltbild / Repro',
            'gebaeude' => 'Gebäude / Werkumfeld',
        ],
        ISS_WF_IMPORT_FAMILY_TAXONOMY => [
            'thyratron' => 'Thyratron',
            'klystron' => 'Klystron',
            'magnetron' => 'Magnetron',
            'bildroehre' => 'Bildröhre',
            'miniaturroehre' => 'Miniaturröhre',
            'gasentladungsroehre' => 'Gasentladungsröhre',
            'roehrensystem' => 'Röhrensystem',
            'diode' => 'Diode',
            'werkstoff' => 'Werkstoff',
            'verpackung' => 'Verpackung',
            'baugruppe' => 'Bauteilgruppe',
            'trockenanlage' => 'Trockenanlage',
            'ofen' => 'Ofen',
            'pruefstand' => 'Prüfstand',
            'fertigungslinie' => 'Fertigungslinie',
            'montageplatz' => 'Montageplatz',
            'arbeitsplatz' => 'Arbeitsplatz',
            'innenraum' => 'Innenraum/Fabrikhalle',
            'laboraufbau' => 'Laboraufbau',
            'transport' => 'Transport/Materialfluss',
            'geraet' => 'Gerät',
            'bauteil' => 'Bauteil',
            'einschub' => 'Einschub',
            'messgeraet' => 'Messgerät',
            'kamera' => 'Kamera',
            'sender' => 'Sender / Sendeeinheit',
            'relais' => 'Relais / Schaltung',
            'schrank' => 'Geräteschrank',
            'schaltbild' => 'Schaltbild / Repro',
            'gebaeudeansicht' => 'Gebäudeansicht',
            'musikinstrument' => 'Elektronisches Instrument',
        ],
        ISS_WF_IMPORT_CONTEXT_TAXONOMY => [
            'produkt' => 'Produkt',
            'komponente' => 'Komponente',
            'werkstoff' => 'Werkstoff',
            'produktion' => 'Produktion',
            'vergleichsaufnahme' => 'Vergleichsaufnahme',
            'verpackung' => 'Verpackung',
            'innenraumansicht' => 'Innenraumansicht',
            'arbeitsprozess' => 'Arbeitsprozess',
            'montage' => 'Montage',
            'sachaufnahme' => 'Sachaufnahme',
            'maschine' => 'Maschine',
            'dokumentation' => 'Dokumentation',
            'reproduktion' => 'Reproduktion',
            'aussenaufnahme' => 'Außenaufnahme',
        ],
        ISS_WF_IMPORT_DECADE_TAXONOMY => [
            '1940er' => '1940er',
            '1950er' => '1950er',
            '1960er' => '1960er',
            '1970er' => '1970er',
            '1980er' => '1980er',
        ],
    ];

    foreach ($taxonomy_terms as $taxonomy => $terms) {
        foreach ($terms as $slug => $label) {
            iss_wf_import_ensure_taxonomy_term($taxonomy, (string) $slug, (string) $label);
        }
    }
}
add_action('init', 'iss_wf_import_ensure_museum_digital_terms', 30);

function iss_wf_import_add_to_relations_post_types(array $post_types): array
{
    $post_types[] = ISS_WF_IMPORT_POST_TYPE;
    $post_types[] = ISS_WF_IMPORT_COLLECTION_POST_TYPE;
    $post_types[] = ISS_WF_IMPORT_OBJECT_POST_TYPE;

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))));
}
add_filter('iss_relations_candidate_post_types', 'iss_wf_import_add_to_relations_post_types');
