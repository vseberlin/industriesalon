<?php
/**
 * Plugin Name: Industriesalon Führungen
 * Description: Führung CPT, structured fields, archive/single templates, and SuperSaaS-aware helpers for Industriesalon.
 * Version: 1.0.0
 * Author: Industriesalon
 */

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
require_once ISS_FUEHRUNGEN_PATH . 'includes/templates.php';

function iss_fuehrungen_enqueue_assets() {
    if (!is_singular(ISS_FUEHRUNGEN_POST_TYPE) && !is_post_type_archive(ISS_FUEHRUNGEN_POST_TYPE) && !is_tax('fuehrung_typ')) {
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
        $hero_gallery_path = ISS_FUEHRUNGEN_PATH . 'assets/tour-hero-gallery.js';
        $route_carousel_path = ISS_FUEHRUNGEN_PATH . 'assets/tour-route-carousel.js';

        wp_enqueue_script(
            'iss-fuehrungen-hero-gallery',
            ISS_FUEHRUNGEN_URL . 'assets/tour-hero-gallery.js',
            [],
            file_exists($hero_gallery_path) ? (string) filemtime($hero_gallery_path) : ISS_FUEHRUNGEN_VERSION,
            true
        );

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

register_activation_hook(__FILE__, function () {
    iss_fuehrungen_register_post_type();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
