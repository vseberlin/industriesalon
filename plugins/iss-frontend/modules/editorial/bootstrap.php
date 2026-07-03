<?php

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_FRONTEND_EDITORIAL_PATH', __DIR__ . '/');
define('ISS_FRONTEND_EDITORIAL_URL', ISS_FRONTEND_URL . 'modules/editorial/');

require_once ISS_FRONTEND_EDITORIAL_PATH . 'includes/dense-image-wall.php';

function iss_frontend_register_editorial_assets(): void
{
    $script_path = ISS_FRONTEND_EDITORIAL_PATH . 'assets/image-viewport-gallery.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_register_script(
        'iss-frontend-image-viewport-gallery',
        ISS_FRONTEND_EDITORIAL_URL . 'assets/image-viewport-gallery.js',
        [],
        (string) filemtime($script_path),
        true
    );
}
add_action('wp_enqueue_scripts', 'iss_frontend_register_editorial_assets', 9);

function iss_frontend_enqueue_image_viewport_gallery_assets(): void
{
    if (!wp_script_is('iss-frontend-image-viewport-gallery', 'registered')) {
        iss_frontend_register_editorial_assets();
    }

    if (wp_script_is('iss-frontend-image-viewport-gallery', 'registered')) {
        wp_enqueue_script('iss-frontend-image-viewport-gallery');
    }
}
