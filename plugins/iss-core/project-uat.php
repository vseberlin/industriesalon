<?php
/** Restricted navigation for the dedicated project acceptance-test role. */
if (!defined('ABSPATH')) {
    exit;
}

function iss_core_is_project_uat(?WP_User $user = null): bool
{
    $user = $user ?? wp_get_current_user();
    return in_array('iss_project_uat', $user->roles, true) && !user_can($user, 'manage_options');
}

add_filter('login_redirect', static function ($redirect, $requested, $user) {
    return $user instanceof WP_User && iss_core_is_project_uat($user)
        ? admin_url('edit.php?post_type=projekt')
        : $redirect;
}, 10, 3);

add_action('admin_menu', static function (): void {
    if (!iss_core_is_project_uat()) { return; }
    global $menu;
    foreach ($menu as $item) {
        if ($item[2] !== 'edit.php?post_type=projekt') {
            remove_menu_page($item[2]);
        }
    }
}, PHP_INT_MAX);

add_action('admin_bar_menu', static function (WP_Admin_Bar $bar): void {
    if (!iss_core_is_project_uat()) { return; }
    foreach ($bar->get_nodes() as $node) {
        if (!$node->parent && !in_array($node->id, ['top-secondary', 'my-account'], true)) {
            $bar->remove_node($node->id);
        }
    }
}, PHP_INT_MAX);

add_action('current_screen', static function (WP_Screen $screen): void {
    if (!iss_core_is_project_uat()) { return; }
    if ($screen->base === 'dashboard') {
        wp_safe_redirect(admin_url('edit.php?post_type=projekt'));
        exit;
    }
    // Personal password/profile remains reachable from the account toolbar.
    if ($screen->base === 'profile') { return; }
    if ($screen->post_type === 'projekt' && in_array($screen->base, ['edit', 'post'], true)) { return; }
    if ($screen->base === 'revision') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen routing; core checks the revision action and nonce.
        $revision = get_post(absint($_GET['revision'] ?? 0));
        if ($revision && get_post_type($revision->post_parent) === 'projekt' && current_user_can('edit_post', $revision->post_parent)) { return; }
    }
    wp_die(esc_html__('Dieses Konto hat nur Zugriff auf Projekte.', 'iss-core'), '', ['response' => 403]);
});
