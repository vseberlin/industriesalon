<?php
/**
 * Plugin Name: ISS Core
 * Description: Shared infrastructure conventions for first-party Industriesalon plugins.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_CORE_VERSION', '0.1.0');
define('ISS_CORE_FILE', __FILE__);
define('ISS_CORE_PATH', plugin_dir_path(__FILE__));

function iss_core_migrate_domain_plugin_basenames(): void
{
    $active = get_option('active_plugins', []);
    if (!is_array($active) || empty($active)) {
        return;
    }

    $current_lookup = array_fill_keys(array_map('strval', $active), true);
    $replacements = [
        'iss-content-model/iss-content-model.php' => 'iss-content/iss-content.php',
        'iss-payments-lite/iss-payments-lite.php' => 'iss-commerce-lite/iss-commerce-lite.php',
        'iss-wf-import/iss-wf-import.php' => 'iss-archive/iss-archive.php',
    ];
    $retired = [
        'iss-fuehrungen/iss-fuehrungen.php' => true,
        'iss-programm/iss-programm.php' => true,
        'saas-api/saas-api.php' => true,
    ];

    $next = [];
    $saw_retired_domain_plugin = false;
    foreach ($active as $plugin) {
        $plugin = (string) $plugin;
        if (isset($retired[$plugin])) {
            $saw_retired_domain_plugin = true;
            continue;
        }
        if (isset($replacements[$plugin])) {
            $plugin = $replacements[$plugin];
            $saw_retired_domain_plugin = true;
        }
        if ($plugin !== '' && !in_array($plugin, $next, true)) {
            $next[] = $plugin;
        }
    }

    if ($saw_retired_domain_plugin) {
        foreach (array_values($replacements) as $plugin) {
            if (!in_array($plugin, $next, true)) {
                $next[] = $plugin;
            }
        }
        if (!in_array('iss-frontend/iss-frontend.php', $next, true)) {
            $next[] = 'iss-frontend/iss-frontend.php';
        }
    }

    if ($next === $active) {
        return;
    }

    update_option('active_plugins', array_values($next));

    $plugin_root = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : trailingslashit(WP_CONTENT_DIR) . 'plugins';
    foreach ($next as $plugin) {
        if (isset($current_lookup[$plugin])) {
            continue;
        }
        $path = trailingslashit($plugin_root) . $plugin;
        if (is_readable($path)) {
            require_once $path;
        }
    }
}
iss_core_migrate_domain_plugin_basenames();

function iss_core_capability(string $area = 'manage'): string
{
    $area = sanitize_key($area);

    $capabilities = [
        'manage' => 'manage_options',
        'edit_content' => 'edit_posts',
        'sync' => 'manage_options',
        'debug' => 'manage_options',
    ];

    return $capabilities[$area] ?? 'manage_options';
}

function iss_core_schema_option_name(string $plugin_slug): string
{
    $plugin_slug = sanitize_key($plugin_slug);
    return $plugin_slug !== '' ? 'iss_' . str_replace('-', '_', $plugin_slug) . '_schema_version' : '';
}

function iss_core_admin_group_label(string $group): string
{
    $group = sanitize_key($group);

    $labels = [
        'content' => __('Inhalte', 'iss-core'),
        'data' => __('Daten', 'iss-core'),
        'sync' => __('Synchronisierung', 'iss-core'),
        'debug' => __('Diagnose', 'iss-core'),
    ];

    return $labels[$group] ?? ucfirst($group);
}

function iss_core_debug_log(string $message, array $context = []): void
{
    if (!defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }

    $line = '[iss-core] ' . $message;
    if (!empty($context)) {
        $line .= ' ' . wp_json_encode($context);
    }

    error_log($line); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
}

function iss_core_drift_result(string $status, string $message, array $data = []): array
{
    $status = sanitize_key($status);
    if (!in_array($status, ['ok', 'warning', 'error'], true)) {
        $status = 'warning';
    }

    return [
        'status' => $status,
        'message' => $message,
        'data' => $data,
    ];
}
