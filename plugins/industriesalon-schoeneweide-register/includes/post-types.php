<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_register_post_types(): void
{
    $labels = [
        'name' => 'Schöneweide Register',
        'singular_name' => 'Ort / Standort',
        'menu_name' => 'Schöneweide Register',
        'name_admin_bar' => 'Ort / Standort',
        'add_new' => 'Neu hinzufügen',
        'add_new_item' => 'Ort / Standort hinzufügen',
        'edit_item' => 'Ort / Standort bearbeiten',
        'new_item' => 'Neuer Ort / Standort',
        'view_item' => 'Ort / Standort ansehen',
        'search_items' => 'Orte / Standorte durchsuchen',
        'not_found' => 'Keine Orte / Standorte gefunden',
        'not_found_in_trash' => 'Keine Orte / Standorte im Papierkorb gefunden',
        'all_items' => 'Alle Orte / Standorte',
    ];

    register_post_type(ISS_REGISTER_POST_TYPE, [
        'labels' => $labels,
        'public' => false,
        'show_ui' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title', 'editor', 'thumbnail', 'revisions'],
    ]);
}

add_action('init', 'iss_register_register_post_types');
