<?php
/** wp eval-file FILE --use-include; read-only menu/widget checks, no account changes. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/dashboard.php';

$checks = 0;
$assert = static function (bool $ok, string $label) use (&$checks): void {
    if (!$ok) { throw new RuntimeException($label); }
    ++$checks;
};
$original_user = wp_get_current_user();
$roles_before = wp_roles()->roles;
$operator = new WP_User();
$operator->ID = -1;
$operator->roles = ['iss_editorial_operator'];
$operator->allcaps = get_role('iss_editorial_operator')->capabilities;
$GLOBALS['current_user'] = $operator;

global $menu, $submenu, $pagenow, $typenow, $_wp_menu_nopriv, $_wp_submenu_nopriv,
    $_registered_pages, $_parent_pages, $admin_page_hooks, $wp_meta_boxes;
$pagenow = 'index.php';
$typenow = '';
set_current_screen('dashboard');
require ABSPATH . 'wp-admin/menu.php';

try {
    $assert(apply_filters('login_redirect', admin_url(), '', $operator) === admin_url('index.php'), 'Operator lands on dashboard');
    do_action('current_screen', get_current_screen());
    $routes = array_column($menu, 2);
    $assert(in_array('index.php', $routes, true), 'Dashboard is in the sidebar');
    $assert(!in_array('profile.php', $routes, true), 'Personal profile stays out of sidebar');
    $assert(!in_array('update-core.php', array_column($submenu['index.php'] ?? [], 2), true), 'No update submenu');
    $cards = iss_core_editorial_dashboard_cards();
    $expected = array_values(array_filter($menu, static function (array $item): bool {
        return $item[2] !== 'index.php' && current_user_can($item[1]);
    }));
    // Newsletter's admin bootstrap may be absent in CLI; browser checks cover it.
    $assert(count($cards) >= 11, 'Editorial content and Operations cards are available');
    $assert(array_column($cards, 'title') === array_map(static fn(array $item): string => wp_strip_all_tags($item[0]), $expected), 'Cards match sidebar titles and order');
    foreach ($cards as $card) {
        $assert($card['description'] !== '', 'Description for ' . $card['title']);
        $assert(str_starts_with($card['url'], admin_url()), 'Admin URL for ' . $card['title']);
    }
    $urls = array_column($cards, 'url');
    if (in_array('newsletter_main_index', $routes, true)) {
        $assert(in_array(admin_url('admin.php?page=newsletter_emails_index'), $urls, true), 'Newsletter uses the permitted draft submenu');
    }
    $assert(in_array(admin_url('admin.php?page=iss-operations'), $urls, true), 'Operations plugin route');
    $assert(in_array(admin_url('edit.php?post_type=entity_profile'), $urls, true), 'Native content route');

    $menu[] = ['Forbidden', 'manage_options', 'options-general.php', '', 'menu-top'];
    $assert(iss_core_editorial_dashboard_cards() === $cards, 'Inaccessible menu entries cannot become cards');
    array_pop($menu);
    wp_add_dashboard_widget('dashboard_primary', 'WordPress news', static function (): void {});
    wp_add_dashboard_widget('third_party_widget', 'Plugin widget', static function (): void {});
    do_action('wp_dashboard_setup');
    $widgets = [];
    foreach ($wp_meta_boxes['dashboard'] as $priorities) {
        foreach ($priorities as $boxes) {
            foreach ($boxes as $id => $box) { if ($box) { $widgets[$id] = $box; } }
        }
    }
    $assert(count($widgets) === count($cards), 'Only menu widgets remain');
    $assert(!isset($widgets['dashboard_primary']) && !isset($widgets['third_party_widget']), 'Standard and plugin widgets removed');
    foreach ($cards as $card) {
        $assert(isset($widgets[$card['id']]), 'Registered ' . $card['title']);
        ob_start();
        call_user_func($widgets[$card['id']]['callback']);
        $html = ob_get_clean();
        $assert(str_contains($html, esc_url($card['url'])), 'Rendered destination for ' . $card['title']);
        $assert(str_contains($html, esc_html($card['description'])), 'Rendered description for ' . $card['title']);
    }
    $assert(current_user_can('iss_send_newsletter'), 'Newsletter delivery belongs to editorial responsibilities');
    foreach (['manage_options', 'list_users', 'activate_plugins', 'update_core'] as $cap) {
        $assert(!current_user_can($cap), 'Privilege stays denied: ' . $cap);
    }
    foreach (['administrator', 'iss_project_uat'] as $role) {
        $user = new WP_User();
        $user->ID = -2;
        $user->roles = [$role];
        $user->allcaps = get_role($role)->capabilities;
        $GLOBALS['current_user'] = $user;
        $before = $wp_meta_boxes;
        iss_core_setup_editorial_dashboard();
        $assert($wp_meta_boxes === $before, 'Other role dashboard untouched: ' . $role);
    }
    $assert(wp_roles()->roles === $roles_before, 'No role changes');
} finally {
    $GLOBALS['current_user'] = $original_user;
}
WP_CLI::success($checks . ' read-only editorial dashboard checks passed.');
