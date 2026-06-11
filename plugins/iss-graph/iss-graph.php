<?php
/**
 * Plugin Name: ISS Graph
 * Description: Shared entity graph tables and register bridge for places, people, and organizations.
 * Version: 0.1.3
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_GRAPH_VERSION', '0.1.3');
define('ISS_GRAPH_PATH', plugin_dir_path(__FILE__));
define('ISS_GRAPH_URL', plugin_dir_url(__FILE__));
define('ISS_GRAPH_SCHEMA_VERSION', '2026-06-07-v2');
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
define('ISS_GRAPH_EDITORIAL_SIGNALS_CAPABILITY', 'edit_graph_editorial_signals');

require_once ISS_GRAPH_PATH . 'includes/entity-kinds.php';
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
require_once ISS_GRAPH_PATH . 'includes/facade-rest.php';
require_once ISS_GRAPH_PATH . 'includes/editorial-signals-rest.php';
require_once ISS_GRAPH_PATH . 'includes/editorial-signals-admin.php';
require_once ISS_GRAPH_PATH . 'includes/enrichments.php';
require_once ISS_GRAPH_PATH . 'includes/cli.php';

register_activation_hook(__FILE__, function () {
    iss_graph_get_service()->install_schema();
    iss_graph_ensure_editorial_signals_capability();
});

add_action('init', function (): void {
    iss_graph_get_service()->maybe_install_schema();
}, 5);

add_action('admin_init', 'iss_graph_ensure_editorial_signals_capability', 5);

function iss_graph_ensure_editorial_signals_capability(): void
{
    $role = get_role('administrator');
    if (!$role instanceof WP_Role) {
        return;
    }

    if (!$role->has_cap(ISS_GRAPH_EDITORIAL_SIGNALS_CAPABILITY)) {
        $role->add_cap(ISS_GRAPH_EDITORIAL_SIGNALS_CAPABILITY);
    }
}

function iss_graph_current_user_can_edit_editorial_signals(int $context_post_id = 0): bool
{
    $can_manage_signals = current_user_can(ISS_GRAPH_EDITORIAL_SIGNALS_CAPABILITY) || current_user_can('manage_options');
    if (!$can_manage_signals) {
        return false;
    }

    if ($context_post_id <= 0) {
        return true;
    }

    return current_user_can('edit_post', $context_post_id);
}
