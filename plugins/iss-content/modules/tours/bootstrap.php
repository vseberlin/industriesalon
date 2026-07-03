<?php
if (!defined('ABSPATH')) {
    exit;
}

define('ISS_FUEHRUNGEN_VERSION', '1.0.0');
define('ISS_FUEHRUNGEN_PATH', plugin_dir_path(__FILE__));
define('ISS_FUEHRUNGEN_URL', plugin_dir_url(__FILE__));
define('ISS_FUEHRUNGEN_POST_TYPE', 'fuehrung');

require_once ISS_FUEHRUNGEN_PATH . 'includes/cpt-fuehrung.php';
require_once ISS_FUEHRUNGEN_PATH . 'includes/meta-fuehrung.php';
require_once ISS_FUEHRUNGEN_PATH . 'includes/admin-fuehrung.php';
require_once ISS_FUEHRUNGEN_PATH . 'includes/query-fuehrung.php';
require_once ISS_FUEHRUNGEN_PATH . 'includes/template-tags.php';
require_once ISS_FUEHRUNGEN_PATH . 'includes/blocks.php';
require_once ISS_FUEHRUNGEN_PATH . 'includes/cli.php';

function iss_fuehrungen_enqueue_assets() {
    if (!is_singular(ISS_FUEHRUNGEN_POST_TYPE)) {
        return;
    }

    wp_enqueue_style(
        'iss-fuehrungen',
        ISS_FUEHRUNGEN_URL . 'assets/fuehrungen.css',
        [],
        ISS_FUEHRUNGEN_VERSION
    );
    do_action('iss_fuehrungen_assets_enqueued');

    if (is_singular(ISS_FUEHRUNGEN_POST_TYPE)) {
        $route_carousel_path = ISS_FUEHRUNGEN_PATH . 'assets/tour-route-carousel.js';

        if (function_exists('iss_frontend_enqueue_image_viewport_gallery_assets')) {
            iss_frontend_enqueue_image_viewport_gallery_assets();
        } else {
            $hero_gallery_path = ISS_FUEHRUNGEN_PATH . 'assets/tour-hero-gallery.js';

            wp_enqueue_script(
                'iss-fuehrungen-hero-gallery',
                ISS_FUEHRUNGEN_URL . 'assets/tour-hero-gallery.js',
                [],
                file_exists($hero_gallery_path) ? (string) filemtime($hero_gallery_path) : ISS_FUEHRUNGEN_VERSION,
                true
            );
        }

        if (file_exists($route_carousel_path)) {
            wp_enqueue_script(
                'iss-fuehrungen-route-carousel',
                ISS_FUEHRUNGEN_URL . 'assets/tour-route-carousel.js',
                [],
                (string) filemtime($route_carousel_path),
                true
            );
        }
    }
}
add_action('wp_enqueue_scripts', 'iss_fuehrungen_enqueue_assets');
