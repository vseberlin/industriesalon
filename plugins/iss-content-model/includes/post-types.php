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
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
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
        'taxonomies' => ['category', 'post_tag'],
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
}
add_action('init', 'iss_content_model_register_post_types');
