<?php
/**
 * Plugin Name: ISS Relations
 * Description: Shared place relations with meta-backed source data and hidden taxonomy indexing for reusable queries.
 * Version: 0.1.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_RELATIONS_VERSION', '0.1.0');
define('ISS_RELATIONS_PATH', plugin_dir_path(__FILE__));
define('ISS_RELATIONS_URL', plugin_dir_url(__FILE__));
define('ISS_RELATIONS_META_KEY', 'iss_related_places');
define('ISS_RELATIONS_TAXONOMY', 'iss_place_ref');

require_once ISS_RELATIONS_PATH . 'includes/core.php';
require_once ISS_RELATIONS_PATH . 'includes/meta.php';
require_once ISS_RELATIONS_PATH . 'includes/admin.php';
require_once ISS_RELATIONS_PATH . 'includes/blocks.php';
require_once ISS_RELATIONS_PATH . 'includes/query-loop.php';
require_once ISS_RELATIONS_PATH . 'includes/cli.php';

register_activation_hook(__FILE__, function () {
    update_option('iss_relations_needs_backfill', '1', false);
});
