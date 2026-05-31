<?php
/**
 * Plugin Name: ISS Calendar
 * Description: Internal calendar items (CPT) and sync helpers.
 * Version: 0.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$shared_calendar_bootstrap = dirname(__DIR__) . '/saas-api/iss-calendar/iss-calendar.php';

if (!is_readable($shared_calendar_bootstrap)) {
    add_action('admin_notices', static function () {
        echo '<div class="notice notice-error"><p>'
            . '<strong>ISS Calendar:</strong> '
            . 'Die gemeinsame Kalender-Implementierung unter <code>plugins/saas-api/iss-calendar/</code> fehlt.'
            . '</p></div>';
    });

    return;
}

require_once $shared_calendar_bootstrap;
