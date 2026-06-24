<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_register_post_type() {
    $labels = [
        'name'                  => __('Publikationen', 'iss-publications'),
        'singular_name'         => __('Publikation', 'iss-publications'),
        'menu_name'             => __('Publikationen', 'iss-publications'),
        'name_admin_bar'        => __('Publikation', 'iss-publications'),
        'add_new'               => __('Neu hinzufügen', 'iss-publications'),
        'add_new_item'          => __('Neue Publikation anlegen', 'iss-publications'),
        'new_item'              => __('Neue Publikation', 'iss-publications'),
        'edit_item'             => __('Publikation bearbeiten', 'iss-publications'),
        'view_item'             => __('Publikation ansehen', 'iss-publications'),
        'all_items'             => __('Alle Publikationen', 'iss-publications'),
        'search_items'          => __('Publikationen suchen', 'iss-publications'),
        'not_found'             => __('Keine Publikationen gefunden.', 'iss-publications'),
        'not_found_in_trash'    => __('Keine Publikationen im Papierkorb gefunden.', 'iss-publications'),
        'archives'              => __('Publikations-Archiv', 'iss-publications'),
        'featured_image'        => __('Coverbild', 'iss-publications'),
        'set_featured_image'    => __('Coverbild festlegen', 'iss-publications'),
        'remove_featured_image' => __('Coverbild entfernen', 'iss-publications'),
        'use_featured_image'    => __('Als Coverbild verwenden', 'iss-publications'),
    ];

    register_post_type(ISS_PUBLICATIONS_POST_TYPE, [
        'labels'             => $labels,
        'public'             => true,
        'show_ui'            => true,
        'show_in_menu'       => defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : true,
        'show_in_rest'       => true,
        'has_archive'        => false,
        'rewrite'            => ['slug' => 'publikationen', 'with_front' => false],
        'menu_position'      => 22,
        'menu_icon'          => 'dashicons-book-alt',
        'supports'           => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'publicly_queryable' => true,
        'query_var'          => true,
        'show_in_nav_menus'  => true,
        'delete_with_user'   => false,
        'hierarchical'       => false,
        'taxonomies'         => ['publication_type', 'publication_topic'],
        'capability_type'    => ['publication', 'publications'],
        'capabilities'       => function_exists('iss_core_post_type_capabilities')
            ? iss_core_post_type_capabilities('publication', 'publications', 'create_publications')
            : [],
        'map_meta_cap'       => true,
    ]);

    register_taxonomy('publication_type', [ISS_PUBLICATIONS_POST_TYPE], [
        'labels' => [
            'name'          => __('Publikationstypen', 'iss-publications'),
            'singular_name' => __('Publikationstyp', 'iss-publications'),
            'menu_name'     => __('Typen', 'iss-publications'),
            'all_items'     => __('Alle Typen', 'iss-publications'),
            'edit_item'     => __('Typ bearbeiten', 'iss-publications'),
            'view_item'     => __('Typ ansehen', 'iss-publications'),
            'update_item'   => __('Typ aktualisieren', 'iss-publications'),
            'add_new_item'  => __('Neuen Typ anlegen', 'iss-publications'),
            'new_item_name' => __('Neuer Typname', 'iss-publications'),
            'search_items'  => __('Typen suchen', 'iss-publications'),
        ],
        'public'            => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'publikationstyp', 'with_front' => false],
        'capabilities'      => [
            'manage_terms' => 'iss_manage_publications',
            'edit_terms' => 'iss_manage_publications',
            'delete_terms' => 'iss_manage_publications',
            'assign_terms' => 'edit_publications',
        ],
    ]);

    register_taxonomy('publication_topic', [ISS_PUBLICATIONS_POST_TYPE], [
        'labels' => [
            'name'          => __('Publikationsthemen', 'iss-publications'),
            'singular_name' => __('Publikationsthema', 'iss-publications'),
            'menu_name'     => __('Themen', 'iss-publications'),
            'all_items'     => __('Alle Themen', 'iss-publications'),
            'edit_item'     => __('Thema bearbeiten', 'iss-publications'),
            'view_item'     => __('Thema ansehen', 'iss-publications'),
            'update_item'   => __('Thema aktualisieren', 'iss-publications'),
            'add_new_item'  => __('Neues Thema anlegen', 'iss-publications'),
            'new_item_name' => __('Neuer Themenname', 'iss-publications'),
            'search_items'  => __('Themen suchen', 'iss-publications'),
        ],
        'public'            => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'publikationsthema', 'with_front' => false],
        'capabilities'      => [
            'manage_terms' => 'iss_manage_publications',
            'edit_terms' => 'iss_manage_publications',
            'delete_terms' => 'iss_manage_publications',
            'assign_terms' => 'edit_publications',
        ],
    ]);
}
add_action('init', 'iss_publications_register_post_type');

add_action('pre_get_posts', function ($query) {
    if (is_admin() || !$query instanceof WP_Query || !$query->is_main_query()) {
        return;
    }

    if (!$query->is_post_type_archive(ISS_PUBLICATIONS_POST_TYPE) && !$query->is_tax(['publication_type', 'publication_topic'])) {
        return;
    }

    $query->set('orderby', [
        'menu_order' => 'ASC',
        'date'       => 'DESC',
    ]);
});

add_filter('use_block_editor_for_post_type', function ($use_block_editor, $post_type) {
    if ($post_type === ISS_PUBLICATIONS_POST_TYPE) {
        return true;
    }

    return $use_block_editor;
}, 1000, 2);

add_filter('use_block_editor_for_post', function ($use_block_editor, $post) {
    if (is_numeric($post)) {
        $post = get_post((int) $post);
    }

    if ($post instanceof WP_Post && $post->post_type === ISS_PUBLICATIONS_POST_TYPE) {
        return true;
    }

    return $use_block_editor;
}, 1000, 2);
