<?php
/**
 * Plugin Name: ISS Editorial
 * Description: Versioned editorial document engine for structured Industriesalon authoring.
 * Version: 0.1.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_EDITORIAL_VERSION', '0.1.0');
define('ISS_EDITORIAL_PATH', plugin_dir_path(__FILE__));
define('ISS_EDITORIAL_URL', plugin_dir_url(__FILE__));

require_once ISS_EDITORIAL_PATH . 'includes/formats.php';
require_once ISS_EDITORIAL_PATH . 'includes/storage.php';
require_once ISS_EDITORIAL_PATH . 'includes/references.php';
require_once ISS_EDITORIAL_PATH . 'includes/admin.php';
require_once ISS_EDITORIAL_PATH . 'includes/rest.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once ISS_EDITORIAL_PATH . 'includes/cli.php';
}
