<?php
/**
 * Plugin Name: Industriesalon Schöneweide Register
 * Description: Dynamic block and REST-backed register app for Schöneweide.
 * Version: 0.1.1
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_REGISTER_VERSION', '0.1.1');
define('ISS_REGISTER_PATH', plugin_dir_path(__FILE__));
define('ISS_REGISTER_URL', plugin_dir_url(__FILE__));
define('ISS_REGISTER_REST_NAMESPACE', 'iss-register/v1');
define('ISS_REGISTER_POST_TYPE', 'register_place');
define('ISS_REGISTER_SOURCE_POST_TYPE', 'register_source_item');
define('ISS_REGISTER_TOOLS_PAGE_SLUG', 'iss-register-tools');

require_once ISS_REGISTER_PATH . 'includes/assets.php';
require_once ISS_REGISTER_PATH . 'includes/register-data.php';
require_once ISS_REGISTER_PATH . 'includes/atlas-model.php';
require_once ISS_REGISTER_PATH . 'includes/atlas-render.php';
require_once ISS_REGISTER_PATH . 'includes/register-data/atlas-contracts.php';
require_once ISS_REGISTER_PATH . 'includes/rest-controller.php';
require_once ISS_REGISTER_PATH . 'includes/blocks.php';
require_once ISS_REGISTER_PATH . 'includes/post-types.php';
require_once ISS_REGISTER_PATH . 'includes/source-items.php';
require_once ISS_REGISTER_PATH . 'includes/atlas-content.php';
require_once ISS_REGISTER_PATH . 'includes/taxonomies.php';
require_once ISS_REGISTER_PATH . 'includes/meta-fields.php';
require_once ISS_REGISTER_PATH . 'includes/image-suggestions.php';
require_once ISS_REGISTER_PATH . 'includes/public-fields.php';
require_once ISS_REGISTER_PATH . 'includes/geocoding.php';
require_once ISS_REGISTER_PATH . 'includes/admin-tools.php';
require_once ISS_REGISTER_PATH . 'includes/feedback.php';
require_once ISS_REGISTER_PATH . 'includes/cli.php';

register_activation_hook(__FILE__, function () {
    if (function_exists('iss_register_register_post_types')) {
        iss_register_register_post_types();
    }
    if (function_exists('iss_register_register_source_post_type')) {
        iss_register_register_source_post_type();
    }
    if (function_exists('iss_register_register_atlas_story_post_type')) {
        iss_register_register_atlas_story_post_type();
    }
    if (function_exists('iss_register_register_taxonomies')) {
        iss_register_register_taxonomies();
    }
    if (function_exists('iss_register_register_atlas_era_taxonomy')) {
        iss_register_register_atlas_era_taxonomy();
    }
    if (function_exists('iss_register_seed_atlas_era_terms')) {
        iss_register_seed_atlas_era_terms();
    }
    if (function_exists('iss_register_get_epoch_service')) {
        iss_register_get_epoch_service()->install_schema();
    }
    if (function_exists('iss_register_get_industry_actor_service')) {
        iss_register_get_industry_actor_service()->install_schema();
    }
    if (function_exists('iss_register_get_place_state_service')) {
        iss_register_get_place_state_service()->install_schema();
        iss_register_get_place_state_service()->backfill_all_places();
        update_option(ISS_REGISTER_PLACE_STATE_BACKFILL_OPTION, ISS_REGISTER_PLACE_STATE_BACKFILL_VERSION, false);
    }
    flush_rewrite_rules();
    iss_register_clear_places_cache();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
