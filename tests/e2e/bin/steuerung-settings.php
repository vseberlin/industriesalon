<?php
/** Read-only checks for the simplified settings form and legacy data preservation. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }

$checks = 0;
$expect = static function (bool $ok, string $label) use (&$checks): void {
    ++$checks;
    if (!$ok) { throw new RuntimeException($label); }
};
$control = Industriesalon_Steuerung::instance();
$before = $control->get_all_data();
$control->register_settings();
$settings = get_registered_settings();
foreach (['iss_control_prices', 'iss_control_mission_statement'] as $retired) {
    $expect(!isset($settings[$retired]), $retired . ' cannot be reset by options.php');
}
foreach (['general', 'contact', 'maps', 'hours', 'accessibility'] as $section) {
    $expect(($settings['iss_control_' . $section]['group'] ?? '') === 'iss_control_group', $section . ' remains editable');
}

$original_user = get_current_user_id();
$admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
$expect(!empty($admins), 'Local administrator available for read-only rendering');
wp_set_current_user((int) $admins[0]);
try {
    ob_start();
    $control->render_admin_page();
    $html = (string) ob_get_clean();
} finally {
    wp_set_current_user($original_user);
}
foreach (['iss-prices', 'iss-mission-statement', 'iss_control_contact[booking_url]'] as $removed) {
    $expect(!str_contains($html, $removed), $removed . ' absent from form and navigation');
}
$expect(str_contains($html, 'iss_control_contact[booking_email]'), 'Booking email remains editable');
$expect(substr_count($html, 'class="nav-tab') === 7, 'Six section links and their navigation wrapper remain');

$stored_contact = static fn() => ['booking_url' => 'https://example.org/existing-booking'];
add_filter('pre_option_iss_control_contact', $stored_contact);
try {
    $saved = sanitize_option('iss_control_contact', ['email' => 'updated@example.org']);
    $expect($saved['email'] === 'updated@example.org', 'Visible contact field can change');
    $expect($saved['booking_url'] === 'https://example.org/existing-booking', 'Omitted booking link survives normal contact save');
    $imported = $control->sanitize_contact(['booking_url' => 'https://example.org/restored']);
    $expect($imported['booking_url'] === 'https://example.org/restored', 'Explicit backup value can still be restored');
    $cleared = $control->sanitize_contact(['booking_url' => '']);
    $expect($cleared['booking_url'] === '', 'Explicit empty backup value is respected');
} finally {
    remove_filter('pre_option_iss_control_contact', $stored_contact);
}
$expect($control->get_all_data() === $before, 'Existing export data and all stored options remain unchanged');
WP_CLI::success($checks . ' Steuerung settings checks passed; no settings written.');
