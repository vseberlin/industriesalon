<?php
/**
 * Plugin Name: Industriesalon Schöneweide Register
 * Description: Dynamic block and REST-backed register app for Schöneweide.
 * Version: 0.1.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_REGISTER_VERSION', '0.1.0');
define('ISS_REGISTER_PATH', plugin_dir_path(__FILE__));
define('ISS_REGISTER_URL', plugin_dir_url(__FILE__));
define('ISS_REGISTER_REST_NAMESPACE', 'iss-register/v1');
define('ISS_REGISTER_POST_TYPE', 'register_place');
define('ISS_REGISTER_SOURCE_POST_TYPE', 'register_source_item');
define('ISS_REGISTER_IMPORT_PAGE_SLUG', 'iss-register-import');
define('ISS_REGISTER_TOUCHTABLE_PAGE_SLUG', 'iss-register-touchtable');
define('ISS_REGISTER_TOUCHTABLE_REVIEW_PAGE_SLUG', 'iss-register-touchtable-review');
define('ISS_REGISTER_TOUCHTABLE_SOURCE', 'touchtable');

require_once ISS_REGISTER_PATH . 'includes/assets.php';
require_once ISS_REGISTER_PATH . 'includes/rest-controller.php';
require_once ISS_REGISTER_PATH . 'includes/blocks.php';
require_once ISS_REGISTER_PATH . 'includes/render-register-app.php';
require_once ISS_REGISTER_PATH . 'includes/post-types.php';
require_once ISS_REGISTER_PATH . 'includes/source-items.php';
require_once ISS_REGISTER_PATH . 'includes/taxonomies.php';
require_once ISS_REGISTER_PATH . 'includes/meta-fields.php';
require_once ISS_REGISTER_PATH . 'includes/public-fields.php';
require_once ISS_REGISTER_PATH . 'includes/importer.php';
require_once ISS_REGISTER_PATH . 'includes/touchtable-source.php';
require_once ISS_REGISTER_PATH . 'includes/touchtable-admin.php';
require_once ISS_REGISTER_PATH . 'includes/touchtable-review.php';
require_once ISS_REGISTER_PATH . 'includes/feedback.php';
require_once ISS_REGISTER_PATH . 'includes/render-register-list.php';
require_once ISS_REGISTER_PATH . 'includes/render-register-map.php';
require_once ISS_REGISTER_PATH . 'includes/render-register-featured.php';

register_activation_hook(__FILE__, function () {
    if (function_exists('iss_register_register_post_types')) {
        iss_register_register_post_types();
    }
    if (function_exists('iss_register_register_source_post_type')) {
        iss_register_register_source_post_type();
    }
    if (function_exists('iss_register_register_taxonomies')) {
        iss_register_register_taxonomies();
    }
    flush_rewrite_rules();
    iss_register_clear_places_cache();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
