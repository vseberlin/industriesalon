<?php
/**
 * Plugin Name: ISS Publications
 * Description: Publication content model and editorial rendering for Industriesalon publications.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_PUBLICATIONS_VERSION', '0.1.0');
define('ISS_PUBLICATIONS_PATH', plugin_dir_path(__FILE__));
define('ISS_PUBLICATIONS_URL', plugin_dir_url(__FILE__));
define('ISS_PUBLICATIONS_POST_TYPE', 'publication');

require_once ISS_PUBLICATIONS_PATH . 'includes/cpt-publication.php';
require_once ISS_PUBLICATIONS_PATH . 'includes/meta-publication.php';
require_once ISS_PUBLICATIONS_PATH . 'includes/admin-publication.php';
require_once ISS_PUBLICATIONS_PATH . 'includes/render-publication.php';
require_once ISS_PUBLICATIONS_PATH . 'includes/blocks.php';
require_once ISS_PUBLICATIONS_PATH . 'includes/templates.php';

register_activation_hook(__FILE__, function () {
    iss_publications_register_post_type();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
