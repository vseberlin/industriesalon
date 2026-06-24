<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_get_archivset_supported_post_types(): array
{
    $post_types = [
        'publication',
        'ausstellung',
        'fuehrung',
        'projekt',
        'veranstaltung',
        'video',
        'page',
    ];

    $post_types = (array) apply_filters('iss_wf_import_archivset_supported_post_types', $post_types);

    return array_values(array_filter(array_unique(array_map('sanitize_key', $post_types)), 'post_type_exists'));
}

function iss_wf_import_is_archivset_supported_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_wf_import_get_archivset_supported_post_types(), true);
}

function iss_wf_import_register_archivset_admin_page(): void
{
    add_submenu_page(
        'edit.php?post_type=' . ISS_WF_IMPORT_POST_TYPE,
        __('Archivsets', 'iss-wf-import'),
        __('Archivsets', 'iss-wf-import'),
        ISS_WF_IMPORT_ARCHIVSET_CAPABILITY,
        'iss-archive-sets',
        'iss_wf_import_render_archivset_admin_page'
    );
}
add_action('admin_menu', 'iss_wf_import_register_archivset_admin_page');

function iss_wf_import_render_archivset_admin_page(): void
{
    if (function_exists('iss_require_cap')) {
        iss_require_cap(ISS_WF_IMPORT_ARCHIVSET_CAPABILITY);
    } elseif (!current_user_can(ISS_WF_IMPORT_ARCHIVSET_CAPABILITY)) {
        wp_die(esc_html__('Keine Berechtigung.', 'iss-wf-import'), 403);
    }

    echo '<div class="wrap iss-archivsets-page">';
    echo '<h1>' . esc_html__('Archivsets', 'iss-wf-import') . '</h1>';
    echo '<div id="iss-archivsets-workbench" class="iss-archivsets-workbench" data-mode="workbench"></div>';
    echo '</div>';
}

function iss_wf_import_add_archivset_meta_box(): void
{
    foreach (iss_wf_import_get_archivset_supported_post_types() as $post_type) {
        add_meta_box(
            'iss-wf-import-archive-picker',
            __('Archivauswahl', 'iss-wf-import'),
            'iss_wf_import_render_archivset_meta_box',
            $post_type,
            'normal',
            'default'
        );
    }
}
add_action('add_meta_boxes', 'iss_wf_import_add_archivset_meta_box');

function iss_wf_import_render_archivset_meta_box(WP_Post $post): void
{
    if (!iss_wf_import_is_archivset_supported_post_type($post->post_type)) {
        return;
    }

    echo '<div id="iss-archivsets-metabox" class="iss-archivsets-metabox" data-mode="metabox" data-post-id="' . esc_attr((string) $post->ID) . '">';
    echo '<p class="description">' . esc_html__('Archivsets werden geladen.', 'iss-wf-import') . '</p>';
    echo '</div>';
}

function iss_wf_import_enqueue_archivset_admin_assets(string $hook_suffix): void
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $is_workbench = is_string($hook_suffix) && strpos($hook_suffix, 'iss-archive-sets') !== false;
    $is_metabox = $screen
        && $screen->base === 'post'
        && isset($screen->post_type)
        && iss_wf_import_is_archivset_supported_post_type((string) $screen->post_type);

    if (!$is_workbench && !$is_metabox) {
        return;
    }

    $style_path = ISS_WF_IMPORT_PATH . 'assets/css/archivsets-admin.css';
    $set_selector_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-set-selector.js';
    $helper_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-picker-helper.js';
    $object_picker_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-object-picker.js';
    $adapter_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-editor-adapter.js';
    $modal_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-editor-modal.js';
    $script_path = ISS_WF_IMPORT_PATH . 'assets/js/archivsets-admin.js';

    if (file_exists($style_path)) {
        wp_enqueue_style(
            'iss-wf-import-archivsets-admin',
            ISS_WF_IMPORT_URL . 'assets/css/archivsets-admin.css',
            [],
            (string) filemtime($style_path)
        );
    }

    if (file_exists($set_selector_path)) {
        wp_enqueue_script(
            'iss-wf-import-archive-set-selector',
            ISS_WF_IMPORT_URL . 'assets/js/archive-set-selector.js',
            ['wp-api-fetch'],
            (string) filemtime($set_selector_path),
            true
        );
    }

    if (file_exists($helper_path)) {
        wp_enqueue_script(
            'iss-wf-import-archive-picker-helper',
            ISS_WF_IMPORT_URL . 'assets/js/archive-picker-helper.js',
            array_values(array_filter([
                file_exists($set_selector_path) ? 'iss-wf-import-archive-set-selector' : '',
            ])),
            (string) filemtime($helper_path),
            true
        );

        $post_id = 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen context for asset config.
        if ($is_metabox && isset($_GET['post'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin screen context for asset config.
            $post_id = absint(wp_unslash($_GET['post']));
        }

        wp_localize_script(
            'iss-wf-import-archive-picker-helper',
            'issArchiveSetsAdmin',
            [
                'restRoot' => esc_url_raw(rest_url(iss_wf_import_archivset_rest_namespace())),
                'nonce' => wp_create_nonce('wp_rest'),
                'postId' => $post_id,
                'postTitle' => $post_id > 0 ? (string) get_the_title($post_id) : '',
                'workbenchUrl' => admin_url('edit.php?post_type=' . ISS_WF_IMPORT_POST_TYPE . '&page=iss-archive-sets'),
                'statusLabels' => iss_wf_import_get_archivset_service()->get_status_labels(),
                'strings' => [
                    'loading' => __('Laedt...', 'iss-wf-import'),
                    'save' => __('Speichern', 'iss-wf-import'),
                    'saving' => __('Speichert...', 'iss-wf-import'),
                    'create' => __('Neu anlegen', 'iss-wf-import'),
                    'attach' => __('Anhaengen', 'iss-wf-import'),
                    'detach' => __('Entfernen', 'iss-wf-import'),
                    'deleteSet' => __('Archivset loeschen', 'iss-wf-import'),
                    'search' => __('Suchen', 'iss-wf-import'),
                    'addObject' => __('Objekt hinzufuegen', 'iss-wf-import'),
                    'remove' => __('Entfernen', 'iss-wf-import'),
                    'open' => __('Oeffnen', 'iss-wf-import'),
                    'noSets' => __('Keine Archivsets verbunden.', 'iss-wf-import'),
                    'noResults' => __('Keine Treffer.', 'iss-wf-import'),
                    'error' => __('Die Anfrage konnte nicht abgeschlossen werden.', 'iss-wf-import'),
                    'archiveObject' => __('Archivobjekt', 'iss-wf-import'),
                    'archiveMaterial' => __('Archivmaterial', 'iss-wf-import'),
                    'archiveObjectPreview' => __('Wird auf der Website als Archivkarte angezeigt.', 'iss-wf-import'),
                ],
            ]
        );
    }

    if (file_exists($object_picker_path)) {
        wp_enqueue_script(
            'iss-wf-import-archive-object-picker',
            ISS_WF_IMPORT_URL . 'assets/js/archive-object-picker.js',
            file_exists($helper_path) ? ['iss-wf-import-archive-picker-helper'] : [],
            (string) filemtime($object_picker_path),
            true
        );
    }

    if ($is_metabox && file_exists($adapter_path)) {
        $adapter_dependencies = file_exists($helper_path) ? ['iss-wf-import-archive-picker-helper'] : [];
        if (wp_script_is('mce-view', 'registered')) {
            $adapter_dependencies[] = 'mce-view';
        }

        wp_enqueue_script(
            'iss-wf-import-archive-editor-adapter',
            ISS_WF_IMPORT_URL . 'assets/js/archive-editor-adapter.js',
            array_values(array_unique($adapter_dependencies)),
            (string) filemtime($adapter_path),
            true
        );
    }

    if ($is_metabox && file_exists($modal_path)) {
        $modal_dependencies = [];
        foreach (['iss-wf-import-archive-picker-helper', 'iss-wf-import-archive-object-picker', 'iss-wf-import-archive-editor-adapter'] as $handle) {
            if (wp_script_is($handle, 'enqueued')) {
                $modal_dependencies[] = $handle;
            }
        }

        wp_enqueue_script(
            'iss-wf-import-archive-editor-modal',
            ISS_WF_IMPORT_URL . 'assets/js/archive-editor-modal.js',
            array_values(array_unique($modal_dependencies)),
            (string) filemtime($modal_path),
            true
        );
    }

    if (file_exists($script_path)) {
        wp_enqueue_script(
            'iss-wf-import-archivsets-admin',
            ISS_WF_IMPORT_URL . 'assets/js/archivsets-admin.js',
            array_values(array_filter([
                file_exists($helper_path) ? 'iss-wf-import-archive-picker-helper' : '',
                file_exists($object_picker_path) ? 'iss-wf-import-archive-object-picker' : '',
            ])),
            (string) filemtime($script_path),
            true
        );
    }
}
add_action('admin_enqueue_scripts', 'iss_wf_import_enqueue_archivset_admin_assets');
