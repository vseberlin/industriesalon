<?php

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_FRONTEND_STATIC_MAPS_PATH', __DIR__ . '/');

require_once ISS_FRONTEND_STATIC_MAPS_PATH . 'includes/render.php';

function iss_frontend_static_maps_register_image_viewport_assets(): void
{
    $relative_path = 'modules/static-maps/assets/image-viewport.js';
    $plugin_path = dirname(__DIR__, 2) . '/';
    $script_path = $plugin_path . $relative_path;

    if (!is_readable($script_path)) {
        return;
    }

    $dependencies = wp_script_is('iss-register-schoneweide-atlas-leaflet', 'registered')
        ? ['iss-register-schoneweide-atlas-leaflet']
        : [];

    wp_register_script(
        'iss-frontend-static-map-image-viewport',
        plugins_url($relative_path, $plugin_path . 'iss-frontend.php'),
        $dependencies,
        (string) filemtime($script_path),
        true
    );
}
add_action('init', 'iss_frontend_static_maps_register_image_viewport_assets', 20);

function iss_frontend_static_maps_enqueue_image_viewport_assets(): void
{
    if (wp_style_is('iss-register-schoneweide-atlas-leaflet', 'registered')) {
        wp_enqueue_style('iss-register-schoneweide-atlas-leaflet');
    }

    if (wp_script_is('iss-frontend-static-map-image-viewport', 'registered')) {
        wp_enqueue_script('iss-frontend-static-map-image-viewport');
    }
}
