<?php
/**
 * Plugin Name: ISS Occurrences
 * Description: Durable occurrence projection, SuperSaaS ingestion, and public programme query service.
 * Version: 0.1.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_OCCURRENCES_VERSION', '0.1.0');
define('ISS_OCCURRENCES_PATH', plugin_dir_path(__FILE__));
define('ISS_OCCURRENCES_SCHEMA_VERSION', '2026-06-13-occurrences-v8');
define('ISS_OCCURRENCES_SCHEMA_OPTION', 'iss_occurrences_schema_version');
define('ISS_OCCURRENCES_RETIRED_SOURCE_MAP_OPTION', 'iss_occurrences_source_map');
define('ISS_OCCURRENCES_RETIRED_SERIES_MAP_OPTION', 'iss_occurrences_series_map');

require_once ISS_OCCURRENCES_PATH . 'includes/service.php';
require_once ISS_OCCURRENCES_PATH . 'includes/mapping.php';
require_once ISS_OCCURRENCES_PATH . 'includes/providers.php';
require_once ISS_OCCURRENCES_PATH . 'includes/hooks.php';
require_once ISS_OCCURRENCES_PATH . 'includes/supersaas/bootstrap.php';
require_once ISS_OCCURRENCES_PATH . 'includes/admin-fuehrung-mapping.php';
require_once ISS_OCCURRENCES_PATH . 'includes/admin-sync-page.php';
require_once ISS_OCCURRENCES_PATH . 'includes/cli.php';

register_activation_hook(__FILE__, function (): void {
    iss_occurrences_get_service()->install_schema();
    if (function_exists('iss_supersaas_activate_sync')) {
        iss_supersaas_activate_sync();
    }
});

register_deactivation_hook(__FILE__, function (): void {
    if (function_exists('iss_supersaas_deactivate_sync')) {
        iss_supersaas_deactivate_sync();
    }
});

add_action('init', function (): void {
    iss_occurrences_get_service()->maybe_install_schema();
}, 4);
