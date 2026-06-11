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
