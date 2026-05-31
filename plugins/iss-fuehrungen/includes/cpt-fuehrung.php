<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_fuehrungen_register_post_type() {
    $labels = [
        'name'                  => __('Führungen', 'iss-fuehrungen'),
        'singular_name'         => __('Führung', 'iss-fuehrungen'),
        'menu_name'             => __('Führungen', 'iss-fuehrungen'),
        'name_admin_bar'        => __('Führung', 'iss-fuehrungen'),
        'add_new'               => __('Neu hinzufügen', 'iss-fuehrungen'),
        'add_new_item'          => __('Neue Führung anlegen', 'iss-fuehrungen'),
        'new_item'              => __('Neue Führung', 'iss-fuehrungen'),
        'edit_item'             => __('Führung bearbeiten', 'iss-fuehrungen'),
        'view_item'             => __('Führung ansehen', 'iss-fuehrungen'),
        'all_items'             => __('Alle Führungen', 'iss-fuehrungen'),
        'search_items'          => __('Führungen suchen', 'iss-fuehrungen'),
        'parent_item_colon'     => __('Übergeordnete Führung:', 'iss-fuehrungen'),
        'not_found'             => __('Keine Führungen gefunden.', 'iss-fuehrungen'),
        'not_found_in_trash'    => __('Keine Führungen im Papierkorb gefunden.', 'iss-fuehrungen'),
        'archives'              => __('Führungs-Archiv', 'iss-fuehrungen'),
        'featured_image'        => __('Titelbild', 'iss-fuehrungen'),
        'set_featured_image'    => __('Titelbild festlegen', 'iss-fuehrungen'),
        'remove_featured_image' => __('Titelbild entfernen', 'iss-fuehrungen'),
        'use_featured_image'    => __('Als Titelbild verwenden', 'iss-fuehrungen'),
    ];

    register_post_type(ISS_FUEHRUNGEN_POST_TYPE, [
        'labels'              => $labels,
        'public'              => true,
        'show_in_rest'        => true,
        'has_archive'         => true,
        'rewrite'             => ['slug' => 'fuehrungen', 'with_front' => false],
        'menu_position'       => 21,
        'menu_icon'           => 'dashicons-groups',
        'supports'            => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'publicly_queryable'  => true,
        'query_var'           => true,
        'show_in_nav_menus'   => true,
        'delete_with_user'    => false,
        'hierarchical'        => false,
        'taxonomies'          => ['fuehrung_typ'],
    ]);

    register_taxonomy('fuehrung_typ', [ISS_FUEHRUNGEN_POST_TYPE], [
        'labels' => [
            'name'          => __('Führungstypen', 'iss-fuehrungen'),
            'singular_name' => __('Führungstyp', 'iss-fuehrungen'),
            'menu_name'     => __('Führungstypen', 'iss-fuehrungen'),
            'all_items'     => __('Alle Typen', 'iss-fuehrungen'),
            'edit_item'     => __('Typ bearbeiten', 'iss-fuehrungen'),
            'view_item'     => __('Typ ansehen', 'iss-fuehrungen'),
            'update_item'   => __('Typ aktualisieren', 'iss-fuehrungen'),
            'add_new_item'  => __('Neuen Typ anlegen', 'iss-fuehrungen'),
            'new_item_name' => __('Neuer Typname', 'iss-fuehrungen'),
            'search_items'  => __('Typen suchen', 'iss-fuehrungen'),
        ],
        'public'            => true,
        'hierarchical'      => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'rewrite'           => ['slug' => 'fuehrungsart', 'with_front' => false],
    ]);
}
add_action('init', 'iss_fuehrungen_register_post_type');
