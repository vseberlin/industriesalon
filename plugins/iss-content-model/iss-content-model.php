<?php
/**
 * Plugin Name: ISS Content Model
 * Description: Shared CPTs and structured fields for Veranstaltungen, Ausstellungen, Projekte, and Team, with minimal timeline wiring.
 * Version: 0.1.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_CONTENT_MODEL_VERSION', '0.2.0');
define('ISS_CONTENT_MODEL_PATH', plugin_dir_path(__FILE__));

define('ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE', 'veranstaltung');
define('ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE', 'ausstellung');
define('ISS_CONTENT_MODEL_PROJEKT_POST_TYPE', 'projekt');
define('ISS_CONTENT_MODEL_TEAM_POST_TYPE', 'team_member');

define('ISS_CONTENT_MODEL_TEAM_ROLE_TAXONOMY', 'team_role');
define('ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY', 'project_status');
define('ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY', 'ausstellung_typ');
define('ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY', 'sammlungsbereich');
define('ISS_CONTENT_MODEL_INDUSTRY_SITE_TAXONOMY', 'industrieort');

require_once ISS_CONTENT_MODEL_PATH . 'includes/post-types.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/meta.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/admin.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/blocks.php';
require_once ISS_CONTENT_MODEL_PATH . 'includes/timeline-sync.php';

register_activation_hook(__FILE__, function () {
    iss_content_model_register_post_types();
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function () {
    flush_rewrite_rules();
});
