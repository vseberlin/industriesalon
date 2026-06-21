<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_editorial_admin_path(): string
{
    return defined('ISS_EDITORIAL_PATH') ? (string) ISS_EDITORIAL_PATH : trailingslashit(dirname(__DIR__));
}

function iss_editorial_admin_url(): string
{
    return defined('ISS_EDITORIAL_URL') ? (string) ISS_EDITORIAL_URL : plugin_dir_url(dirname(__DIR__) . '/iss-editorial.php');
}

function iss_editorial_add_meta_boxes(): void
{
    // The editorial engine renders its own main canvas; legacy meta boxes are not the editor surface.
}
add_action('add_meta_boxes', 'iss_editorial_add_meta_boxes', 30);

function iss_editorial_use_block_editor_for_post_type(bool $use_block_editor, string $post_type): bool
{
    if (iss_editorial_get_format_for_post_type($post_type)) {
        return false;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'iss_editorial_use_block_editor_for_post_type', 2000, 2);

function iss_editorial_use_block_editor_for_post(bool $use_block_editor, $post): bool
{
    if (is_numeric($post)) {
        $post = get_post((int) $post);
    }

    if ($post instanceof WP_Post && iss_editorial_get_format_for_post_type((string) $post->post_type)) {
        return false;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post', 'iss_editorial_use_block_editor_for_post', 2000, 2);

function iss_editorial_remove_default_editor_support(): void
{
    foreach (iss_editorial_get_registered_formats() as $format) {
        foreach ((array) $format['post_types'] as $post_type) {
            remove_post_type_support((string) $post_type, 'editor');
        }
    }
}
add_action('init', 'iss_editorial_remove_default_editor_support', 100);

function iss_editorial_render_meta_box(WP_Post $post, array $box): void
{
    $format_slug = sanitize_key((string) ($box['args']['format_slug'] ?? ''));
    $format = iss_editorial_get_format($format_slug);
    if (!$format) {
        return;
    }

    $document = iss_editorial_get_document((int) $post->ID, $format_slug, false);
    $enabled = iss_editorial_document_is_enabled((int) $post->ID, $format_slug);

    wp_nonce_field('iss_editorial_save_document', 'iss_editorial_nonce');

    echo '<div class="iss-editorial-admin" data-format="' . esc_attr($format_slug) . '" data-post-id="' . esc_attr((string) $post->ID) . '">';
    echo '<p class="description">' . esc_html__('Pilot-Modus: Die JSON-Komposition wird erst öffentlich gerendert, wenn die Ausgabe unten aktiviert ist. Der bestehende Inhalt bleibt erhalten.', 'iss-editorial') . '</p>';
    echo '<p><label><input type="checkbox" class="iss-editorial-enabled-field" name="iss_editorial[' . esc_attr($format_slug) . '][enabled]" value="1" ' . checked($enabled, true, false) . '> ' . esc_html__('JSON-Komposition für diese Ausstellung verwenden', 'iss-editorial') . '</label></p>';
    echo '<input type="hidden" class="iss-editorial-document-field" name="iss_editorial[' . esc_attr($format_slug) . '][document]" value="' . esc_attr(iss_editorial_encode_document($document)) . '">';
    echo '<div class="iss-editorial-root" data-document="' . esc_attr(iss_editorial_encode_document($document)) . '" data-sections="' . esc_attr(iss_editorial_encode_document((array) $format['sections'])) . '"></div>';
    echo '<p class="description iss-editorial-autosave-status" aria-live="polite"></p>';
    echo '</div>';
}

function iss_editorial_render_main_canvas(WP_Post $post): void
{
    $format = iss_editorial_get_format_for_post_type((string) $post->post_type);
    if (!$format) {
        return;
    }

    $format_slug = (string) $format['slug'];
    $document = iss_editorial_get_document((int) $post->ID, $format_slug, false);
    $enabled = iss_editorial_document_is_enabled((int) $post->ID, $format_slug);

    wp_nonce_field('iss_editorial_save_document', 'iss_editorial_nonce');

    echo '<div class="iss-editorial-shell">';
    echo '<div class="iss-editorial-admin iss-editorial-admin--canvas" data-format="' . esc_attr($format_slug) . '" data-post-id="' . esc_attr((string) $post->ID) . '">';
    echo '<input type="hidden" class="iss-editorial-document-field" name="iss_editorial[' . esc_attr($format_slug) . '][document]" value="' . esc_attr(iss_editorial_encode_document($document)) . '">';
    echo '<div class="iss-editorial-canvas-toolbar">';
    echo '<p class="iss-editorial-mode">' . esc_html__('Redaktionelle Komposition', 'iss-editorial') . '</p>';
    echo '<label class="iss-editorial-enable"><input type="checkbox" class="iss-editorial-enabled-field" name="iss_editorial[' . esc_attr($format_slug) . '][enabled]" value="1" ' . checked($enabled, true, false) . '> ' . esc_html__('JSON-Ausgabe verwenden', 'iss-editorial') . '</label>';
    echo '</div>';
    echo '<div class="iss-editorial-root" data-document="' . esc_attr(iss_editorial_encode_document($document)) . '" data-sections="' . esc_attr(iss_editorial_encode_document((array) $format['sections'])) . '"></div>';
    echo '<p class="description iss-editorial-autosave-status" aria-live="polite"></p>';
    echo '</div>';
    echo '</div>';
}
add_action('edit_form_after_title', 'iss_editorial_render_main_canvas', 20);

function iss_editorial_enqueue_archive_picker_assets(int $post_id): void
{
    if (!defined('ISS_WF_IMPORT_PATH') || !defined('ISS_WF_IMPORT_URL') || !function_exists('iss_wf_import_archivset_rest_namespace')) {
        return;
    }

    $style_path = ISS_WF_IMPORT_PATH . 'assets/css/archivsets-admin.css';
    $set_selector_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-set-selector.js';
    $helper_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-picker-helper.js';
    $object_picker_path = ISS_WF_IMPORT_PATH . 'assets/js/archive-object-picker.js';

    if (file_exists($style_path)) {
        wp_enqueue_style('iss-wf-import-archivsets-admin', ISS_WF_IMPORT_URL . 'assets/css/archivsets-admin.css', [], (string) filemtime($style_path));
    }

    if (file_exists($set_selector_path)) {
        wp_enqueue_script('iss-wf-import-archive-set-selector', ISS_WF_IMPORT_URL . 'assets/js/archive-set-selector.js', ['wp-api-fetch'], (string) filemtime($set_selector_path), true);
    }

    if (file_exists($helper_path)) {
        wp_enqueue_script(
            'iss-wf-import-archive-picker-helper',
            ISS_WF_IMPORT_URL . 'assets/js/archive-picker-helper.js',
            array_values(array_filter([file_exists($set_selector_path) ? 'iss-wf-import-archive-set-selector' : ''])),
            (string) filemtime($helper_path),
            true
        );

        wp_localize_script(
            'iss-wf-import-archive-picker-helper',
            'issArchiveSetsAdmin',
            [
                'restRoot' => esc_url_raw(rest_url(iss_wf_import_archivset_rest_namespace())),
                'nonce' => wp_create_nonce('wp_rest'),
                'postId' => $post_id,
                'strings' => [
                    'error' => __('Die Anfrage konnte nicht abgeschlossen werden.', 'iss-editorial'),
                ],
            ]
        );
    }

    if (file_exists($object_picker_path)) {
        wp_enqueue_script(
            'iss-wf-import-archive-object-picker',
            ISS_WF_IMPORT_URL . 'assets/js/archive-object-picker.js',
            ['iss-wf-import-archive-picker-helper'],
            (string) filemtime($object_picker_path),
            true
        );
    }
}

function iss_editorial_enqueue_admin_assets(string $hook): void
{
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || empty($screen->post_type)) {
        return;
    }

    $format = iss_editorial_get_format_for_post_type((string) $screen->post_type);
    if (!$format) {
        return;
    }

    $post_id = 0;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context for asset config.
    if (isset($_GET['post'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context for asset config.
        $post_id = absint(wp_unslash($_GET['post']));
    }

    iss_editorial_enqueue_archive_picker_assets($post_id);
    wp_enqueue_media(['post' => $post_id]);

    $style_path = iss_editorial_admin_path() . 'assets/admin.css';
    if (file_exists($style_path)) {
        wp_enqueue_style('iss-editorial-admin', iss_editorial_admin_url() . 'assets/admin.css', [], (string) filemtime($style_path));
    }

    $script_path = iss_editorial_admin_path() . 'assets/admin.js';
    if (file_exists($script_path)) {
        wp_enqueue_script('iss-editorial-admin', iss_editorial_admin_url() . 'assets/admin.js', [], (string) filemtime($script_path), true);
        wp_localize_script(
            'iss-editorial-admin',
            'issEditorialAdmin',
            [
                'restRoot' => esc_url_raw(rest_url('iss-editorial/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'postId' => $post_id,
                'format' => (string) $format['slug'],
                'document' => iss_editorial_get_document($post_id, (string) $format['slug'], false),
                'enabled' => iss_editorial_document_is_enabled($post_id, (string) $format['slug']),
                'sections' => (array) $format['sections'],
                'strings' => [
                    'saved' => __('Automatisch gesichert.', 'iss-editorial'),
                    'saving' => __('Automatische Sicherung...', 'iss-editorial'),
                    'error' => __('Automatische Sicherung fehlgeschlagen.', 'iss-editorial'),
                    'savedPermanent' => __('JSON-Komposition gespeichert.', 'iss-editorial'),
                    'savingPermanent' => __('JSON-Komposition wird gespeichert...', 'iss-editorial'),
                    'savePermanent' => __('JSON speichern', 'iss-editorial'),
                ],
            ]
        );
    }
}
add_action('admin_enqueue_scripts', 'iss_editorial_enqueue_admin_assets', 25);

function iss_editorial_save_meta_box(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (!isset($_POST['iss_editorial_nonce']) || !wp_verify_nonce((string) $_POST['iss_editorial_nonce'], 'iss_editorial_save_document')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post_type = (string) get_post_type($post_id);
    $format = iss_editorial_get_format_for_post_type($post_type);
    if (!$format) {
        return;
    }

    $format_slug = (string) $format['slug'];
    $raw = isset($_POST['iss_editorial']) && is_array($_POST['iss_editorial']) ? wp_unslash($_POST['iss_editorial']) : [];
    $format_payload = is_array($raw[$format_slug] ?? null) ? $raw[$format_slug] : [];
    if (!$format_payload) {
        return;
    }

    iss_editorial_set_document_enabled($post_id, $format_slug, !empty($format_payload['enabled']));
    iss_editorial_save_document($post_id, $format_slug, (string) ($format_payload['document'] ?? ''), false);
    delete_post_meta($post_id, iss_editorial_get_autosave_meta_key($format_slug));
}
add_action('save_post', 'iss_editorial_save_meta_box', 35, 1);
