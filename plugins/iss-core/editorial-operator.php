<?php
/** Native capabilities and bounded navigation for editorial Operations staff. */
if (!defined('ABSPATH')) { exit; }

function iss_core_editorial_capability_types(): array
{
    return [
        'attachment' => ['iss_media', 'iss_media_items'],
        'fuehrung' => ['fuehrung', 'fuehrungen'],
        'veranstaltung' => ['veranstaltung', 'veranstaltungen'],
        'ausstellung' => ['ausstellung', 'ausstellungen'],
        'team_member' => ['team_member', 'team_members'],
        'entity_profile' => ['entity_profile', 'entity_profiles'],
    ];
}

add_filter('register_post_type_args', static function (array $args, string $type): array {
    $types = $type === 'attachment' ? ['iss_media', 'iss_media_items'] : null;
    if ($types) {
        $args['capability_type'] = $types;
        $args['capabilities'] = iss_core_post_type_capabilities(...$types);
        $args['map_meta_cap'] = true;
    }
    return $args;
}, 20, 2);

function iss_core_editorial_operator_grants(): array
{
    $caps = [
        'read', 'upload_files', 'iss_assign_editorial_terms', 'iss_access_operations', 'iss_manage_hinweise',
        'iss_manage_publications', 'iss_manage_steuerung',
        'iss_manage_requests', 'iss_read_sets', 'iss_create_sets', 'iss_edit_sets',
        'iss_sync_external_sources',
        'iss_manage_newsletter_content',
    ];
    $plurals = ['pages', 'projekte', 'iss_notices', 'register_places', 'publications', 'rueckblicke'];
    foreach (iss_core_editorial_capability_types() as $types) { $plurals[] = $types[1]; }
    foreach ($plurals as $plural) {
        foreach (['edit_', 'edit_others_', 'edit_private_', 'edit_published_', 'read_private_'] as $prefix) {
            $caps[] = $prefix . $plural;
        }
        if ($plural !== 'pages') { $caps[] = 'create_' . $plural; }
    }
    return $caps;
}

/** Explicit revocations for the existing role; other roles retain their grants. */
function iss_core_editorial_operator_revoked_capabilities(): array
{
    return array_merge([
        'iss_manage_register', 'iss_manage_fallback_mode', 'iss_run_fallback_projection',
        'iss_manage_archive', 'iss_import_archive',
    ], array_values(iss_core_post_type_capabilities('archive_item', 'archive_items')));
}

function iss_core_is_editorial_operator(?WP_User $user = null): bool
{
    $user = $user ?? wp_get_current_user();
    return in_array('iss_editorial_operator', $user->roles, true) && !user_can($user, 'manage_options');
}

function iss_core_editorial_operator_post_types(): array
{
    return array_merge(array_keys(iss_core_editorial_capability_types()), [
        'page', 'projekt', 'iss_notice', 'register_place', 'publication', 'rueckblick',
    ]);
}

add_filter('login_redirect', static function ($redirect, $requested, $user) {
    return $user instanceof WP_User && iss_core_is_editorial_operator($user)
        ? admin_url('edit.php?post_type=page') : $redirect;
}, 10, 3);

add_action('admin_menu', static function (): void {
    if (!iss_core_is_editorial_operator()) { return; }
    global $menu;
    $allowed = ['upload.php', 'profile.php', ISS_CORE_OPERATIONS_MENU_SLUG, 'newsletter_main_index'];
    foreach (iss_core_editorial_operator_post_types() as $type) { $allowed[] = 'edit.php?post_type=' . $type; }
    foreach ($menu as $item) {
        if (!in_array($item[2], $allowed, true)) { remove_menu_page($item[2]); }
    }
    remove_submenu_page('upload.php', 'mla-menu');
}, PHP_INT_MAX);

add_action('admin_bar_menu', static function (WP_Admin_Bar $bar): void {
    if (!iss_core_is_editorial_operator()) { return; }
    foreach ($bar->get_nodes() as $node) {
        if (!$node->parent && !in_array($node->id, ['top-secondary', 'my-account'], true)) { $bar->remove_node($node->id); }
    }
}, PHP_INT_MAX);

add_action('current_screen', static function (WP_Screen $screen): void {
    if (!iss_core_is_editorial_operator()) { return; }
    if ($screen->base === 'dashboard') {
        wp_safe_redirect(admin_url('edit.php?post_type=page'));
        exit;
    }
    if (in_array($screen->base, ['profile', 'upload', 'media'], true)) { return; }
    if (in_array($screen->post_type, iss_core_editorial_operator_post_types(), true)
        && in_array($screen->base, ['edit', 'post'], true)) { return; }
    if (in_array($screen->base, ['edit-tags', 'term'], true)) {
        $taxonomy = get_taxonomy($screen->taxonomy);
        if ($taxonomy && array_intersect($taxonomy->object_type, iss_core_editorial_operator_post_types())
            && current_user_can($taxonomy->cap->edit_terms)) { return; }
    }
    if ($screen->base === 'revision') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only routing; core authorizes revision actions.
        $revision = get_post(absint($_GET['revision'] ?? 0));
        if ($revision && current_user_can('edit_post', $revision->post_parent)) { return; }
    }
    // Plugin pages still enforce their own capabilities and action nonces.
    global $plugin_page, $submenu;
    foreach ($submenu[ISS_CORE_OPERATIONS_MENU_SLUG] ?? [] as $item) {
        if ($plugin_page === $item[2] && current_user_can($item[1])) { return; }
    }
    if ($plugin_page === ISS_CORE_OPERATIONS_MENU_SLUG) { return; }
    if (function_exists('iss_newsletter_editorial_pages') && in_array($plugin_page, iss_newsletter_editorial_pages(), true)) { return; }
    wp_die(esc_html__('Dieses Konto hat keinen Zugriff auf diesen Bereich.', 'iss-core'), '', ['response' => 403]);
});

// Shared terms remain assignable without granting generic Posts access.
add_filter('register_taxonomy_args', static function (array $args): array {
    if (($args['capabilities']['assign_terms'] ?? 'edit_posts') === 'edit_posts') {
        $args['capabilities']['assign_terms'] = 'iss_assign_editorial_terms';
    }
    return $args;
});
