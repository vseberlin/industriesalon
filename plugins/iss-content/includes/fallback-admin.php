<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_fallback_add_origin_column(array $columns): array
{
    $columns['iss_fallback_origin'] = __('Fallback', 'iss-content-model');
    return $columns;
}
add_filter('manage_post_posts_columns', 'iss_content_fallback_add_origin_column');
add_filter('manage_page_posts_columns', 'iss_content_fallback_add_origin_column');

function iss_content_fallback_render_origin_column(string $column, int $post_id): void
{
    if ($column !== 'iss_fallback_origin') {
        return;
    }

    $origin = iss_content_fallback_origin($post_id);
    if ($origin === 'generated') {
        echo esc_html__('Generiert', 'iss-content-model');
        return;
    }
    if ($origin === 'fallback-native') {
        echo esc_html__('Fallback-nativ', 'iss-content-model');
    }
}
add_action('manage_post_posts_custom_column', 'iss_content_fallback_render_origin_column', 10, 2);
add_action('manage_page_posts_custom_column', 'iss_content_fallback_render_origin_column', 10, 2);

function iss_content_fallback_generated_notice(): void
{
    $screen = get_current_screen();
    if (!$screen || !in_array($screen->base, ['post'], true)) {
        return;
    }

    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context.
    if ($post_id <= 0 || !iss_content_fallback_is_generated($post_id)) {
        return;
    }

    $source_url = esc_url((string) get_post_meta($post_id, '_iss_fallback_canonical_url', true));
    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('Generierte Fallback-Projektion. Änderungen werden überschrieben.', 'iss-content-model');
    if ($source_url !== '') {
        echo ' <a href="' . esc_url($source_url) . '">' . esc_html__('Quelle ansehen', 'iss-content-model') . '</a>';
    }
    echo '</p></div>';
}
add_action('admin_notices', 'iss_content_fallback_generated_notice');

function iss_content_fallback_edit_gate(array $caps, string $cap, int $user_id, array $args): array
{
    if ($cap !== 'edit_post' || empty($args[0])) {
        return $caps;
    }

    $post_id = (int) $args[0];
    if (!iss_content_fallback_is_generated($post_id)) {
        return $caps;
    }
    if (user_can($user_id, 'manage_options') || user_can($user_id, 'iss_manage_fallback_mode')) {
        return $caps;
    }

    return ['do_not_allow'];
}
add_filter('map_meta_cap', 'iss_content_fallback_edit_gate', 10, 4);

function iss_content_fallback_status_page(): void
{
    if (!current_user_can('iss_manage_fallback_mode') && !current_user_can('manage_options')) {
        wp_die(esc_html__('Keine Berechtigung.', 'iss-content-model'), 403);
    }

    $result = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['iss_fallback_action'])) {
        check_admin_referer('iss_fallback_action');
        $action = sanitize_key((string) $_POST['iss_fallback_action']);
        if ($action === 'dry-run') {
            $result = iss_content_fallback_project(['dry_run' => true, 'mark_stale' => true]);
        } elseif ($action === 'project' && current_user_can('iss_run_fallback_projection')) {
            $result = iss_content_fallback_project(['dry_run' => false, 'mark_stale' => true]);
        } elseif ($action === 'enable') {
            $result = iss_content_fallback_enable();
        } elseif ($action === 'disable') {
            $result = iss_content_fallback_disable();
        }
    }

    $status = iss_content_fallback_status_report();
    echo '<div class="wrap">';
    echo '<h1>' . esc_html__('ISS Fallback-Modus', 'iss-content-model') . '</h1>';
    echo '<p><strong>' . esc_html__('Modus:', 'iss-content-model') . '</strong> ' . esc_html(!empty($status['mode_enabled']) ? __('Fallback aktiv', 'iss-content-model') : __('Normal', 'iss-content-model')) . '</p>';
    echo '<p>' . esc_html(sprintf(
        'generated=%d published=%d draft=%d stale=%d native=%d',
        (int) ($status['generated_total'] ?? 0),
        (int) ($status['generated_published'] ?? 0),
        (int) ($status['generated_draft'] ?? 0),
        (int) ($status['generated_stale'] ?? 0),
        (int) ($status['fallback_native_total'] ?? 0)
    )) . '</p>';
    if ($result !== null) {
        echo '<pre>' . esc_html((string) wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '</pre>';
    }
    echo '<form method="post">';
    wp_nonce_field('iss_fallback_action');
    echo '<p>';
    foreach ([
        'dry-run' => __('Dry-run', 'iss-content-model'),
        'project' => __('Projektion ausführen', 'iss-content-model'),
        'enable' => __('Fallback aktivieren', 'iss-content-model'),
        'disable' => __('Fallback deaktivieren', 'iss-content-model'),
    ] as $action => $label) {
        echo '<button class="button" type="submit" name="iss_fallback_action" value="' . esc_attr($action) . '">' . esc_html($label) . '</button> ';
    }
    echo '</p></form></div>';
}

function iss_content_fallback_register_status_page(): void
{
    add_submenu_page(
        defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : 'tools.php',
        __('ISS Fallback', 'iss-content-model'),
        __('ISS Fallback', 'iss-content-model'),
        'iss_manage_fallback_mode',
        'iss-fallback',
        'iss_content_fallback_status_page'
    );
}
add_action('admin_menu', 'iss_content_fallback_register_status_page', 30);

