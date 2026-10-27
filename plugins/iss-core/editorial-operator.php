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
        'iss_review_sets', 'iss_promote_media', 'iss_delete_sets',
        'iss_sync_external_sources',
        'iss_manage_newsletter_content', 'iss_send_newsletter',
    ];
    $plurals = ['pages', 'projekte', 'iss_notices', 'register_places', 'publications', 'rueckblicke'];
    foreach (iss_core_editorial_capability_types() as $types) { $plurals[] = $types[1]; }
    foreach ($plurals as $plural) {
        foreach (['edit_', 'edit_others_', 'edit_private_', 'edit_published_', 'read_private_',
            'publish_', 'delete_', 'delete_others_', 'delete_private_', 'delete_published_'] as $prefix) {
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
        ? admin_url('index.php') : $redirect;
}, 10, 3);

add_action('admin_menu', static function (): void {
    if (!iss_core_is_editorial_operator()) { return; }
    global $menu;
    // Personal profile remains available through the account toolbar.
    $allowed = ['index.php', 'upload.php', ISS_CORE_OPERATIONS_MENU_SLUG, 'newsletter_main_index'];
    foreach (iss_core_editorial_operator_post_types() as $type) { $allowed[] = 'edit.php?post_type=' . $type; }
    foreach ($menu as $item) {
        if (!in_array($item[2], $allowed, true)) { remove_menu_page($item[2]); }
    }
    remove_submenu_page('upload.php', 'mla-menu');
    remove_submenu_page('index.php', 'update-core.php');
}, PHP_INT_MAX);

add_action('admin_bar_menu', static function (WP_Admin_Bar $bar): void {
    if (!iss_core_is_editorial_operator()) { return; }
    foreach ($bar->get_nodes() as $node) {
        if (!$node->parent && !in_array($node->id, ['top-secondary', 'my-account'], true)) { $bar->remove_node($node->id); }
    }
}, PHP_INT_MAX);

add_action('current_screen', static function (WP_Screen $screen): void {
    if (!iss_core_is_editorial_operator()) { return; }
    if (in_array($screen->base, ['dashboard', 'profile', 'upload', 'media'], true)) { return; }
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

/** Descriptions decorate the current menu; WordPress remains the navigation authority. */
function iss_core_editorial_dashboard_cards(): array
{
    global $menu, $submenu;
    $descriptions = [
        'upload.php' => __('Bilder und Dateien hochladen und verwalten.', 'iss-core'),
        'edit.php?post_type=page' => __('Texte und Inhalte der Website-Seiten bearbeiten.', 'iss-core'),
        'edit.php?post_type=fuehrung' => __('Führungen anlegen und Tourbeschreibungen pflegen.', 'iss-core'),
        'edit.php?post_type=veranstaltung' => __('Veranstaltungen anlegen und Programminformationen pflegen.', 'iss-core'),
        'edit.php?post_type=ausstellung' => __('Ausstellungen und ihre Beschreibungen bearbeiten.', 'iss-core'),
        'edit.php?post_type=projekt' => __('Projekte vorstellen und ihre Inhalte ergänzen.', 'iss-core'),
        'edit.php?post_type=rueckblick' => __('Über vergangene Veranstaltungen und Projekte berichten.', 'iss-core'),
        'edit.php?post_type=team_member' => __('Teammitglieder und ihre Aufgaben vorstellen.', 'iss-core'),
        ISS_CORE_OPERATIONS_MENU_SLUG => __('Hinweise, Orte, Buchungen, Bilder-Sets und weitere Betriebsbereiche öffnen.', 'iss-core'),
        'edit.php?post_type=entity_profile' => __('Personen und Organisationen vorstellen und ihre Profile pflegen.', 'iss-core'),
        'edit.php?post_type=publication' => __('Publikationen anlegen und ihre Beschreibungen bearbeiten.', 'iss-core'),
        'newsletter_main_index' => __('Newsletter erstellen, bearbeiten und versenden.', 'iss-core'),
    ];
    $cards = [];
    foreach ($menu as $item) {
        if (in_array($item[2], ['index.php', 'profile.php'], true)
            || str_contains($item[4] ?? '', 'wp-menu-separator') || !current_user_can($item[1])) {
            continue;
        }
        $target = $item[2];
        foreach ($submenu[$item[2]] ?? [] as $child) {
            if (current_user_can($child[1])) {
                $target = $child[2];
                break;
            }
        }
        $cards[] = [
            'id' => 'iss-editorial-menu-' . sanitize_key($item[2]),
            'title' => wp_strip_all_tags($item[0]),
            'url' => iss_core_admin_menu_url($target, $item[2]),
            'description' => $descriptions[$item[2]] ?? __('Diesen Bereich der Website bearbeiten.', 'iss-core'),
        ];
    }
    return $cards;
}

function iss_core_setup_editorial_dashboard(): void
{
    if (!iss_core_is_editorial_operator()) { return; }
    global $wp_meta_boxes;
    foreach ($wp_meta_boxes['dashboard'] ?? [] as $context => $priorities) {
        foreach ($priorities as $boxes) {
            foreach (array_keys($boxes) as $id) { remove_meta_box($id, 'dashboard', $context); }
        }
    }
    $contexts = ['normal', 'side', 'column3', 'column4'];
    foreach (iss_core_editorial_dashboard_cards() as $index => $card) {
        wp_add_dashboard_widget($card['id'], $card['title'], static function () use ($card): void {
            echo '<p>' . esc_html($card['description']) . '</p>';
            echo '<p><a class="button" href="' . esc_url($card['url']) . '" aria-label="';
            /* translators: %s: Name of the admin menu section. */
            echo esc_attr(sprintf(__('%s öffnen', 'iss-core'), $card['title']));
            echo '">' . esc_html__('Öffnen', 'iss-core') . '</a></p>';
        }, null, null, $contexts[$index % count($contexts)]);
    }
}
add_action('wp_dashboard_setup', 'iss_core_setup_editorial_dashboard', PHP_INT_MAX);

// Shared terms remain assignable without granting generic Posts access.
add_filter('register_taxonomy_args', static function (array $args): array {
    if (($args['capabilities']['assign_terms'] ?? 'edit_posts') === 'edit_posts') {
        $args['capabilities']['assign_terms'] = 'iss_assign_editorial_terms';
    }
    return $args;
});
