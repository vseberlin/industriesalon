<?php
if (!defined('ABSPATH')) {
    exit;
}

define('ISS_PROGRAMM_VERSION', '0.1.0');
define('ISS_PROGRAMM_FILE', __FILE__);

require_once __DIR__ . '/includes/assets.php';
require_once __DIR__ . '/includes/calendar-render.php';
require_once __DIR__ . '/includes/calendar-blocks.php';

require_once __DIR__ . '/includes/timeline-query.php';
require_once __DIR__ . '/includes/timeline-render.php';
require_once __DIR__ . '/includes/program-cards.php';
require_once __DIR__ . '/includes/ausstellungen-browser.php';
require_once __DIR__ . '/includes/timeline-rest.php';
require_once __DIR__ . '/includes/timeline-blocks.php';
require_once __DIR__ . '/includes/programme-helpers.php';

add_action('admin_notices', function () {
    if (!function_exists('iss_occurrences_query')) {
        echo '<div class="notice notice-error"><p>'
            . '<strong>ISS Frontend:</strong> '
            . 'Das Plugin <em>ISS Occurrences</em> muss aktiviert sein. '
            . 'Kalender- und Termindarstellung lesen ausschließlich aus der Occurrence-Projektion.'
            . '</p></div>';
    }
});
