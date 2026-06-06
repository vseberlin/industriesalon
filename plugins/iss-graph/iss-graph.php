<?php
/**
 * Plugin Name: ISS Graph
 * Description: Shared entity graph tables and register bridge for places, people, and organizations.
 * Version: 0.1.2
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_GRAPH_VERSION', '0.1.2');
define('ISS_GRAPH_PATH', plugin_dir_path(__FILE__));
define('ISS_GRAPH_URL', plugin_dir_url(__FILE__));
define('ISS_GRAPH_SCHEMA_VERSION', '2026-06-06-v4');
define('ISS_GRAPH_SCHEMA_OPTION', 'iss_graph_schema_version');
define('ISS_GRAPH_REGISTER_BACKFILL_VERSION', '2026-06-06-register-v2');
define('ISS_GRAPH_REGISTER_BACKFILL_OPTION', 'iss_graph_register_backfill_version');
define('ISS_GRAPH_CONTENT_BACKFILL_VERSION', '2026-06-06-content-v1');
define('ISS_GRAPH_CONTENT_BACKFILL_OPTION', 'iss_graph_content_backfill_version');
define('ISS_GRAPH_ARCHIVE_BACKFILL_VERSION', '2026-06-06-archive-v2');
define('ISS_GRAPH_ARCHIVE_BACKFILL_OPTION', 'iss_graph_archive_backfill_version');
define('ISS_GRAPH_PROFILE_BACKFILL_VERSION', '2026-06-06-profile-v1');
define('ISS_GRAPH_PROFILE_BACKFILL_OPTION', 'iss_graph_profile_backfill_version');
define('ISS_GRAPH_ALIAS_BACKFILL_VERSION', '2026-06-06-alias-v1');
define('ISS_GRAPH_ALIAS_BACKFILL_OPTION', 'iss_graph_alias_backfill_version');
define('ISS_GRAPH_SEARCH_BACKFILL_VERSION', '2026-06-06-search-v3');
define('ISS_GRAPH_SEARCH_BACKFILL_OPTION', 'iss_graph_search_backfill_version');

require_once ISS_GRAPH_PATH . 'includes/core.php';
require_once ISS_GRAPH_PATH . 'includes/aliases.php';
require_once ISS_GRAPH_PATH . 'includes/register-bridge.php';
require_once ISS_GRAPH_PATH . 'includes/archive-bridge.php';
require_once ISS_GRAPH_PATH . 'includes/content-bridge.php';
require_once ISS_GRAPH_PATH . 'includes/video-transcript-bridge.php';
require_once ISS_GRAPH_PATH . 'includes/profiles.php';
require_once ISS_GRAPH_PATH . 'includes/search-index.php';
require_once ISS_GRAPH_PATH . 'includes/search-service.php';
require_once ISS_GRAPH_PATH . 'includes/search-rest.php';
require_once ISS_GRAPH_PATH . 'includes/enrichments.php';
require_once ISS_GRAPH_PATH . 'includes/cli.php';

register_activation_hook(__FILE__, function () {
    iss_graph_get_service()->install_schema();
});

add_action('init', function (): void {
    iss_graph_get_service()->maybe_install_schema();
}, 5);

add_action('init', function (): void {
    if (function_exists('iss_graph_maybe_backfill_register_places')) {
        iss_graph_maybe_backfill_register_places();
    }
}, 20);

add_action('init', function (): void {
    if (function_exists('iss_graph_maybe_backfill_public_content_entities')) {
        iss_graph_maybe_backfill_public_content_entities();
    }
}, 35);

add_action('init', function (): void {
    if (function_exists('iss_graph_maybe_backfill_archive_objects')) {
        iss_graph_maybe_backfill_archive_objects();
    }
}, 40);

add_action('init', function (): void {
    if (function_exists('iss_graph_maybe_backfill_entity_profile_bindings')) {
        iss_graph_maybe_backfill_entity_profile_bindings();
    }
}, 45);

add_action('init', function (): void {
    if (function_exists('iss_graph_maybe_backfill_entity_aliases')) {
        iss_graph_maybe_backfill_entity_aliases();
    }
}, 48);

add_action('init', function (): void {
    if (function_exists('iss_graph_maybe_backfill_public_search_index')) {
        iss_graph_maybe_backfill_public_search_index();
    }
}, 50);
