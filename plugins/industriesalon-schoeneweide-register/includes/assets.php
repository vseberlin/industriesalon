<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_theme_asset_url(string $relative_path): string
{
    $url = get_stylesheet_directory_uri() . $relative_path;

    if (function_exists('industriesalon_make_relative_url')) {
        return industriesalon_make_relative_url($url);
    }

    return $url;
}

add_action('init', function () {
    $theme_dir = get_stylesheet_directory();
    $leaflet_style_rel = '/assets/vendor/leaflet/leaflet.css';
    $leaflet_style_abs = $theme_dir . $leaflet_style_rel;
    if (file_exists($leaflet_style_abs)) {
        wp_register_style(
            'iss-register-schoneweide-atlas-leaflet',
            iss_register_get_theme_asset_url($leaflet_style_rel),
            [],
            (string) filemtime($leaflet_style_abs)
        );
    }

    $atlas_style_rel = '/assets/css/atlas-app.css';
    $atlas_style_abs = $theme_dir . $atlas_style_rel;
    if (file_exists($atlas_style_abs)) {
        $atlas_style_deps = wp_style_is('iss-register-schoneweide-atlas-leaflet', 'registered')
            ? ['iss-register-schoneweide-atlas-leaflet']
            : [];

        if (wp_style_is('industriesalon-overrides', 'registered')) {
            $atlas_style_deps[] = 'industriesalon-overrides';
        }

        wp_register_style(
            'iss-register-schoneweide-atlas-style',
            iss_register_get_theme_asset_url($atlas_style_rel),
            $atlas_style_deps,
            (string) filemtime($atlas_style_abs)
        );
    }

    $leaflet_script_rel = '/assets/vendor/leaflet/leaflet.js';
    $leaflet_script_abs = $theme_dir . $leaflet_script_rel;
    if (file_exists($leaflet_script_abs)) {
        wp_register_script(
            'iss-register-schoneweide-atlas-leaflet',
            iss_register_get_theme_asset_url($leaflet_script_rel),
            [],
            (string) filemtime($leaflet_script_abs),
            true
        );
    }

    $atlas_runtime_scripts = [
        'iss-register-schoneweide-atlas-core' => [
            'path' => '/assets/js/atlas/core.js',
            'deps' => [],
        ],
        'iss-register-schoneweide-atlas-provider' => [
            'path' => '/assets/js/atlas/provider.js',
            'deps' => ['iss-register-schoneweide-atlas-core'],
        ],
        'iss-register-schoneweide-atlas-layout' => [
            'path' => '/assets/js/atlas/layout.js',
            'deps' => ['iss-register-schoneweide-atlas-core'],
        ],
        'iss-register-schoneweide-atlas-config' => [
            'path' => '/assets/js/atlas/config.js',
            'deps' => ['iss-register-schoneweide-atlas-core'],
        ],
        'iss-register-schoneweide-atlas-store' => [
            'path' => '/assets/js/atlas/store.js',
            'deps' => ['iss-register-schoneweide-atlas-core'],
        ],
        'iss-register-schoneweide-atlas-places' => [
            'path' => '/assets/js/atlas/places.js',
            'deps' => ['iss-register-schoneweide-atlas-core', 'iss-register-schoneweide-atlas-store'],
        ],
        'iss-register-schoneweide-atlas-detail' => [
            'path' => '/assets/js/atlas/detail.js',
            'deps' => ['iss-register-schoneweide-atlas-core', 'iss-register-schoneweide-atlas-store'],
        ],
        'iss-register-schoneweide-atlas-stories' => [
            'path' => '/assets/js/atlas/stories.js',
            'deps' => ['iss-register-schoneweide-atlas-core', 'iss-register-schoneweide-atlas-store', 'iss-register-schoneweide-atlas-detail'],
        ],
        'iss-register-schoneweide-atlas-relations' => [
            'path' => '/assets/js/atlas/relations.js',
            'deps' => ['iss-register-schoneweide-atlas-core', 'iss-register-schoneweide-atlas-store'],
        ],
        'iss-register-schoneweide-atlas-map' => [
            'path' => '/assets/js/atlas/map.js',
            'deps' => ['iss-register-schoneweide-atlas-core', 'iss-register-schoneweide-atlas-provider'],
        ],
        'iss-register-schoneweide-atlas-markers' => [
            'path' => '/assets/js/atlas/markers.js',
            'deps' => ['iss-register-schoneweide-atlas-core', 'iss-register-schoneweide-atlas-store', 'iss-register-schoneweide-atlas-map'],
        ],
    ];

    foreach ($atlas_runtime_scripts as $handle => $script) {
        $script_abs = $theme_dir . $script['path'];
        if (!file_exists($script_abs)) {
            continue;
        }

        wp_register_script(
            $handle,
            iss_register_get_theme_asset_url($script['path']),
            $script['deps'],
            (string) filemtime($script_abs),
            true
        );
    }

    $atlas_script_rel = '/assets/js/schoneweide.js';
    $atlas_script_abs = $theme_dir . $atlas_script_rel;
    if (file_exists($atlas_script_abs)) {
        $atlas_script_deps = wp_script_is('iss-register-schoneweide-atlas-leaflet', 'registered')
            ? ['iss-register-schoneweide-atlas-leaflet']
            : [];
        foreach (array_keys($atlas_runtime_scripts) as $runtime_handle) {
            if (wp_script_is($runtime_handle, 'registered')) {
                $atlas_script_deps[] = $runtime_handle;
            }
        }

        wp_register_script(
            'iss-register-schoneweide-atlas-view',
            iss_register_get_theme_asset_url($atlas_script_rel),
            $atlas_script_deps,
            (string) filemtime($atlas_script_abs),
            true
        );
    }

    wp_register_script(
        'iss-register-schoneweide-atlas-block-editor',
        ISS_REGISTER_URL . 'assets/js/schoneweide-atlas-block.js',
        ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n'],
        ISS_REGISTER_VERSION,
        true
    );

});
