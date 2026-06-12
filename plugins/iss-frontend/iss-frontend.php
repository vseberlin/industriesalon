<?php
/**
 * Plugin Name: ISS Frontend
 * Description: Shared frontend runtime helpers for first-party Industriesalon surfaces.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_FRONTEND_VERSION', '0.1.0');
define('ISS_FRONTEND_FILE', __FILE__);
define('ISS_FRONTEND_PATH', plugin_dir_path(__FILE__));
define('ISS_FRONTEND_URL', plugin_dir_url(__FILE__));

function iss_frontend_rest_url(string $path): string
{
    $path = ltrim($path, '/');
    return rest_url($path);
}

function iss_frontend_register_rest_config(string $handle, array $config): void
{
    if ($handle === '' || empty($config)) {
        return;
    }

    wp_add_inline_script(
        $handle,
        'window.ISS_FRONTEND = Object.assign({}, window.ISS_FRONTEND || {}, ' . wp_json_encode($config) . ');',
        'before'
    );
}

function iss_frontend_dialog_attributes(string $label): array
{
    return [
        'role' => 'dialog',
        'aria-modal' => 'true',
        'aria-label' => $label,
    ];
}

function iss_frontend_disclosure_attributes(bool $expanded = false): array
{
    return [
        'aria-expanded' => $expanded ? 'true' : 'false',
    ];
}

function iss_frontend_register_datepicker_assets(
    string $handle,
    string $script_url,
    string $style_url = '',
    array $deps = [],
    string $version = ISS_FRONTEND_VERSION
): void
{
    if ($style_url !== '') {
        wp_register_style($handle, $style_url, [], $version);
    }

    wp_register_script($handle, $script_url, $deps, $version, true);
}
