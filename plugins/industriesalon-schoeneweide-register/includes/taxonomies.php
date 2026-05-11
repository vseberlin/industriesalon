<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_public_taxonomy_map(): array
{
    return [
        'register_area' => [
            'meta_key' => 'area',
            'single_label' => 'Gebiet',
            'plural_label' => 'Gebiete',
        ],
        'register_status' => [
            'meta_key' => 'status',
            'single_label' => 'Status',
            'plural_label' => 'Status',
        ],
        'register_role' => [
            'meta_key' => 'role',
            'single_label' => 'Rolle',
            'plural_label' => 'Rollen',
        ],
        'register_current_status' => [
            'meta_key' => 'current_status',
            'single_label' => 'Heutige Situation',
            'plural_label' => 'Heutige Situationen',
        ],
        'register_current_use_type' => [
            'meta_key' => 'current_use_type',
            'single_label' => 'Nutzungstyp',
            'plural_label' => 'Nutzungstypen',
        ],
    ];
}

function iss_register_register_taxonomies(): void
{
    foreach (iss_register_get_public_taxonomy_map() as $taxonomy => $config) {
        register_taxonomy($taxonomy, ISS_REGISTER_POST_TYPE, [
            'labels' => [
                'name' => $config['plural_label'],
                'singular_name' => $config['single_label'],
                'menu_name' => $config['plural_label'],
                'all_items' => 'Alle ' . $config['plural_label'],
                'edit_item' => $config['single_label'] . ' bearbeiten',
                'view_item' => $config['single_label'] . ' ansehen',
                'update_item' => $config['single_label'] . ' aktualisieren',
                'add_new_item' => $config['single_label'] . ' hinzufügen',
                'new_item_name' => 'Neuer ' . $config['single_label'],
                'search_items' => $config['plural_label'] . ' durchsuchen',
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
}

add_action('init', 'iss_register_register_taxonomies');

function iss_register_sync_public_terms_for_post(int $post_id): void
{
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_REGISTER_POST_TYPE) {
        return;
    }

    foreach (iss_register_get_public_taxonomy_map() as $taxonomy => $config) {
        $value = trim((string) get_post_meta($post_id, $config['meta_key'], true));

        if ($value === '') {
            wp_set_object_terms($post_id, [], $taxonomy, false);
            continue;
        }

        wp_set_object_terms($post_id, [$value], $taxonomy, false);
    }
}

function iss_register_sync_public_terms_on_save(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    iss_register_sync_public_terms_for_post($post_id);
}

add_action('save_post_' . ISS_REGISTER_POST_TYPE, 'iss_register_sync_public_terms_on_save', 20);
