<?php
if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Keep data when explicitly requested.
if (defined('ISS_CONTROL_PRESERVE_DATA') && ISS_CONTROL_PRESERVE_DATA) {
    return;
}

foreach ([
    'iss_control_general',
    'iss_control_contact',
    'iss_control_maps',
    'iss_control_hours',
    'iss_control_accessibility',
    'iss_control_prices',
    'iss_control_faq',
] as $option_name) {
    delete_option($option_name);
}

foreach (['administrator', 'editor'] as $role_name) {
    $role = get_role($role_name);
    if ($role instanceof WP_Role) {
        $role->remove_cap('manage_iss_controls');
    }
}
