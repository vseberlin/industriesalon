<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_register_post_types() {
    register_post_type(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, [
        'labels' => [
            'name' => __('Veranstaltungen', 'iss-content-model'),
            'singular_name' => __('Veranstaltung', 'iss-content-model'),
            'menu_name' => __('Veranstaltungen', 'iss-content-model'),
            'add_new_item' => __('Neue Veranstaltung anlegen', 'iss-content-model'),
            'edit_item' => __('Veranstaltung bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'veranstaltungen', 'with_front' => false],
        'menu_position' => 22,
        'menu_icon' => 'dashicons-calendar',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'taxonomies' => ['category', 'post_tag'],
    ]);

    register_post_type(ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE, [
        'labels' => [
            'name' => __('Ausstellungen', 'iss-content-model'),
            'singular_name' => __('Ausstellung', 'iss-content-model'),
            'menu_name' => __('Ausstellungen', 'iss-content-model'),
            'add_new_item' => __('Neue Ausstellung anlegen', 'iss-content-model'),
            'edit_item' => __('Ausstellung bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'ausstellungen', 'with_front' => false],
        'menu_position' => 23,
        'menu_icon' => 'dashicons-art',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'taxonomies' => [
            'category',
            'post_tag',
            ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY,
            ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY,
            ISS_CONTENT_MODEL_INDUSTRY_SITE_TAXONOMY,
        ],
    ]);

    register_post_type(ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, [
        'labels' => [
            'name' => __('Projekte', 'iss-content-model'),
            'singular_name' => __('Projekt', 'iss-content-model'),
            'menu_name' => __('Projekte', 'iss-content-model'),
            'add_new_item' => __('Neues Projekt anlegen', 'iss-content-model'),
            'edit_item' => __('Projekt bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'projekte', 'with_front' => false],
        'menu_position' => 24,
        'menu_icon' => 'dashicons-portfolio',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'taxonomies' => ['category', 'post_tag', ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY],
    ]);

    register_post_type(ISS_CONTENT_MODEL_TEAM_POST_TYPE, [
        'labels' => [
            'name' => __('Team', 'iss-content-model'),
            'singular_name' => __('Teammitglied', 'iss-content-model'),
            'menu_name' => __('Team', 'iss-content-model'),
            'add_new_item' => __('Neues Teammitglied anlegen', 'iss-content-model'),
            'edit_item' => __('Teammitglied bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'team', 'with_front' => false],
        'menu_position' => 25,
        'menu_icon' => 'dashicons-businessperson',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'taxonomies' => [ISS_CONTENT_MODEL_TEAM_ROLE_TAXONOMY],
    ]);

    register_post_type(ISS_CONTENT_MODEL_VIDEO_POST_TYPE, [
        'labels' => [
            'name' => __('Videos', 'iss-content-model'),
            'singular_name' => __('Video', 'iss-content-model'),
            'menu_name' => __('Videos', 'iss-content-model'),
            'add_new_item' => __('Neues Video anlegen', 'iss-content-model'),
            'edit_item' => __('Video bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'video', 'with_front' => false],
        'menu_position' => 26,
        'menu_icon' => 'dashicons-video-alt3',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'taxonomies' => [ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_TEAM_ROLE_TAXONOMY, [ISS_CONTENT_MODEL_TEAM_POST_TYPE], [
        'labels' => [
            'name' => __('Teamrollen', 'iss-content-model'),
            'singular_name' => __('Teamrolle', 'iss-content-model'),
            'menu_name' => __('Teamrollen', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'team-rolle', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY, [ISS_CONTENT_MODEL_PROJEKT_POST_TYPE], [
        'labels' => [
            'name' => __('Projektstatus', 'iss-content-model'),
            'singular_name' => __('Projektstatus', 'iss-content-model'),
            'menu_name' => __('Projektstatus', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'projekt-status', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, [ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE], [
        'labels' => [
            'name' => __('Ausstellungstypen', 'iss-content-model'),
            'singular_name' => __('Ausstellungstyp', 'iss-content-model'),
            'menu_name' => __('Ausstellungstypen', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'ausstellungstyp', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY, [ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE], [
        'labels' => [
            'name' => __('Sammlungsbereiche', 'iss-content-model'),
            'singular_name' => __('Sammlungsbereich', 'iss-content-model'),
            'menu_name' => __('Sammlungsbereiche', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'sammlungsbereich', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_INDUSTRY_SITE_TAXONOMY, [ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE], [
        'labels' => [
            'name' => __('Industrieorte', 'iss-content-model'),
            'singular_name' => __('Industrieort', 'iss-content-model'),
            'menu_name' => __('Industrieorte', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'industrieort', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY, [ISS_CONTENT_MODEL_VIDEO_POST_TYPE], [
        'labels' => [
            'name' => __('Videokategorien', 'iss-content-model'),
            'singular_name' => __('Videokategorie', 'iss-content-model'),
            'menu_name' => __('Videokategorien', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'videokategorie', 'with_front' => false],
    ]);
}
add_action('init', 'iss_content_model_register_post_types');

function iss_content_model_get_default_taxonomy_terms() {
    return [
        ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY => [
            ['name' => __('Dauerausstellung', 'iss-content-model'), 'slug' => 'dauerausstellung'],
            ['name' => __('Sonderausstellung', 'iss-content-model'), 'slug' => 'sonderausstellung'],
        ],
        ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY => [
            ['name' => __('WF / Werk für Fernsehelektronik', 'iss-content-model'), 'slug' => 'wf-werk-fuer-fernsehelektronik'],
            ['name' => __('TRO / Transformatorenwerk Oberschöneweide', 'iss-content-model'), 'slug' => 'tro-transformatorenwerk-oberschoeneweide'],
            ['name' => __('KWO / Kabelwerk Oberspree', 'iss-content-model'), 'slug' => 'kwo-kabelwerk-oberspree'],
            ['name' => __('Vakuumtechnik & Röhren', 'iss-content-model'), 'slug' => 'vakuumtechnik-roehren'],
            ['name' => __('Mess- und Steuertechnik', 'iss-content-model'), 'slug' => 'mess-und-steuertechnik'],
            ['name' => __('Rundfunk, Ton & Bild', 'iss-content-model'), 'slug' => 'rundfunk-ton-bild'],
            ['name' => __('Lampen & Lichttechnik', 'iss-content-model'), 'slug' => 'lampen-lichttechnik'],
            ['name' => __('Archivmaterial / Fotos / Dokumente', 'iss-content-model'), 'slug' => 'archivmaterial-fotos-dokumente'],
        ],
        ISS_CONTENT_MODEL_INDUSTRY_SITE_TAXONOMY => [
            ['name' => __('Industriesalon Schöneweide', 'iss-content-model'), 'slug' => 'industriesalon-schoeneweide'],
            ['name' => __('Wilhelminenhofstraße', 'iss-content-model'), 'slug' => 'wilhelminenhofstrasse'],
            ['name' => __('Peter-Behrens-Bau', 'iss-content-model'), 'slug' => 'peter-behrens-bau'],
            ['name' => __('Kabelwerk Oberspree', 'iss-content-model'), 'slug' => 'kabelwerk-oberspree'],
            ['name' => __('Transformatorenwerk Oberschöneweide', 'iss-content-model'), 'slug' => 'transformatorenwerk-oberschoeneweide'],
            ['name' => __('Werk für Fernsehelektronik', 'iss-content-model'), 'slug' => 'werk-fuer-fernsehelektronik'],
        ],
        ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY => [
            ['name' => __('Zeitzeugen', 'iss-content-model'), 'slug' => 'zeitzeugen'],
            ['name' => __('Werk & Technik', 'iss-content-model'), 'slug' => 'werk-technik'],
            ['name' => __('Führungen', 'iss-content-model'), 'slug' => 'fuehrungen'],
            ['name' => __('Orte & Wandel', 'iss-content-model'), 'slug' => 'orte-wandel'],
            ['name' => __('Gespräche & Debatten', 'iss-content-model'), 'slug' => 'gespraeche-debatten'],
        ],
    ];
}

function iss_content_model_maybe_seed_taxonomy_terms() {
    $seed_version = (string) get_option('iss_content_model_taxonomy_seed_version', '');
    if ($seed_version === ISS_CONTENT_MODEL_VERSION) {
        return;
    }

    foreach (iss_content_model_get_default_taxonomy_terms() as $taxonomy => $terms) {
        if (!taxonomy_exists($taxonomy) || !is_array($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            $slug = isset($term['slug']) ? sanitize_title((string) $term['slug']) : '';
            $name = isset($term['name']) ? trim((string) $term['name']) : '';
            if ($slug === '' || $name === '') {
                continue;
            }

            if (term_exists($slug, $taxonomy)) {
                continue;
            }

            wp_insert_term($name, $taxonomy, ['slug' => $slug]);
        }
    }

    update_option('iss_content_model_taxonomy_seed_version', ISS_CONTENT_MODEL_VERSION, false);
}
add_action('init', 'iss_content_model_maybe_seed_taxonomy_terms', 30);
