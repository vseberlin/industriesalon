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

function iss_editorial_use_block_editor_for_post(bool $use_block_editor, $post): bool
{
    if (is_numeric($post)) {
        $post = get_post((int) $post);
    }

    if ($post instanceof WP_Post && iss_editorial_uses_canvas($post)) {
        return false;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post', 'iss_editorial_use_block_editor_for_post', 2000, 2);

function iss_editorial_uses_canvas(WP_Post $post): bool
{
    $format = iss_editorial_get_format_for_post($post);
    return $format && ($post->post_status === 'auto-draft' || iss_editorial_document_is_enabled($post->ID, $format['slug']));
}

function iss_editorial_remove_default_editor_support(string $post_type, WP_Post $post): void
{
    if (iss_editorial_uses_canvas($post)) {
        remove_post_type_support($post_type, 'editor');
    }
}
add_action('add_meta_boxes', 'iss_editorial_remove_default_editor_support', 5, 2);

/** Read raw stored sections for editing; never hide unsupported data by normalizing it first. */
function iss_editorial_get_editor_document(int $post_id, string $format_slug): array
{
    $raw = get_metadata_raw('post', $post_id, iss_editorial_get_document_meta_key($format_slug), true);
    $document = $raw ? iss_editorial_decode_document($raw) : iss_editorial_get_empty_document($format_slug);
    return (array) apply_filters('iss_editorial_editor_document', $document, $post_id, $format_slug);
}

function iss_editorial_uses_integrated_route_stations(string $format_slug, string $post_type): bool
{
    return $format_slug === 'fuehrung'
        && $post_type === 'fuehrung'
        && function_exists('iss_relations_get_route_station_editor_config');
}

function iss_editorial_get_route_station_editor_config(int $post_id, string $format_slug, string $post_type): array
{
    if (!iss_editorial_uses_integrated_route_stations($format_slug, $post_type)) {
        return [
            'enabled' => false,
        ];
    }

    return iss_relations_get_route_station_editor_config($post_id);
}

function iss_editorial_render_route_relation_hidden_fields(array $relations): void
{
    if (function_exists('iss_relations_render_route_station_hidden_fields')) {
        iss_relations_render_route_station_hidden_fields($relations);
    }
}

function iss_editorial_render_main_canvas(WP_Post $post): void
{
    $format = iss_editorial_get_format_for_post($post);
    if (!$format) {
        return;
    }
    if (!iss_editorial_uses_canvas($post)) {
        echo '<div class="notice notice-info inline"><p>' . esc_html__('Dieser Inhalt verwendet den bisherigen Editor. Eine gespeicherte Abschnittsfassung ist noch nicht öffentlich aktiviert.', 'iss-editorial') . '</p></div>';
        return;
    }

    $format_slug = (string) $format['slug'];
    $document = iss_editorial_hydrate_document_previews(iss_editorial_get_editor_document((int) $post->ID, $format_slug));
    $enabled = true;
    $route_config = iss_editorial_get_route_station_editor_config((int) $post->ID, $format_slug, (string) $post->post_type);

    wp_nonce_field('iss_editorial_save_document', 'iss_editorial_nonce');

    echo '<div class="iss-editorial-shell">';
    echo '<div class="iss-editorial-admin iss-editorial-admin--canvas" data-format="' . esc_attr($format_slug) . '" data-post-id="' . esc_attr((string) $post->ID) . '">';
    echo '<input type="hidden" class="iss-editorial-enabled-field" name="iss_editorial[' . esc_attr($format_slug) . '][enabled]" value="' . esc_attr($enabled ? '1' : '0') . '">';
    echo '<input type="hidden" class="iss-editorial-document-field" name="iss_editorial[' . esc_attr($format_slug) . '][document]" value="' . esc_attr(iss_editorial_encode_document($document)) . '">';
    echo '<input type="hidden" class="iss-editorial-base-field" name="iss_editorial[' . esc_attr($format_slug) . '][base]" value="' . esc_attr(iss_editorial_saved_token($post->ID, $format_slug)) . '">';
    $draft = iss_editorial_get_draft($post->ID, $format_slug);
    echo '<input type="hidden" class="iss-editorial-draft-token-field" name="iss_editorial[' . esc_attr($format_slug) . '][draft_token]" value="' . esc_attr((string) ($draft['token'] ?? '')) . '">';
    echo '<div class="iss-editorial-recovery" role="region" aria-label="' . esc_attr__('Entwurf fortsetzen', 'iss-editorial') . '"></div>';
    echo '<p class="description iss-editorial-autosave-status" aria-live="polite"></p>';
    echo '<div class="iss-editorial-canvas-toolbar">';
    echo '<div class="iss-editorial-canvas-toolbar__copy">';
    echo '<p class="iss-editorial-mode">' . esc_html__('Inhalt bearbeiten', 'iss-editorial') . '</p>';
    echo '<p class="iss-editorial-mode">' . esc_html__('Diese Abschnitte bilden den Seiteninhalt. Entwürfe werden automatisch gesichert; öffentlich wird die Änderung erst mit Aktualisieren oder Veröffentlichen.', 'iss-editorial') . '</p>';
    echo '</div>';
    echo '<button type="button" class="button button-secondary iss-editorial-preview-button">' . esc_html__('Vorschau öffnen', 'iss-editorial') . '</button>';
    echo '</div>';
    echo '<noscript><p>' . esc_html__('Zum Bearbeiten der Abschnitte bitte JavaScript aktivieren. Ihre gespeicherten Inhalte bleiben erhalten.', 'iss-editorial') . '</p></noscript>';
    echo '<div class="iss-editorial-root" data-document="' . esc_attr(iss_editorial_encode_document($document)) . '" data-sections="' . esc_attr(iss_editorial_encode_document((array) $format['sections'])) . '"></div>';
    $revisions = wp_get_post_revisions($post->ID, ['posts_per_page' => 1, 'check_enabled' => false]);
    if ($revisions) {
        $revision = reset($revisions);
        echo '<p><a href="' . esc_url(get_edit_post_link($revision->ID)) . '">' . esc_html__('Gespeicherte Fassungen ansehen', 'iss-editorial') . '</a></p>';
    }
    echo '</div>';
    echo '</div>';
}
add_action('edit_form_after_title', 'iss_editorial_render_main_canvas', 20);

function iss_editorial_remove_integrated_relation_meta_boxes(string $post_type): void
{
    $format = iss_editorial_get_format_for_post_type($post_type);
    if (!$format || !iss_editorial_uses_integrated_route_stations((string) $format['slug'], $post_type)) {
        return;
    }

    remove_meta_box('iss-relations-places', $post_type, 'normal');
}
add_action('add_meta_boxes', 'iss_editorial_remove_integrated_relation_meta_boxes', 100, 1);

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

function iss_editorial_get_page_link_choices(): array
{
    $pages = get_pages([
        'post_status' => 'publish',
        'sort_column' => 'post_title',
        'sort_order' => 'ASC',
    ]);
    $choices = [];

    foreach ($pages as $page) {
        if (!$page instanceof WP_Post) {
            continue;
        }

        $choices[] = [
            'id' => (int) $page->ID,
            'title' => html_entity_decode((string) get_the_title($page), ENT_QUOTES, get_bloginfo('charset') ?: 'UTF-8'),
            'url' => (string) get_permalink($page),
            'path' => '/' . trim((string) get_page_uri($page), '/') . '/',
        ];
    }

    return $choices;
}

function iss_editorial_get_preview_url(int $post_id, string $format_slug): string
{
    $url = get_preview_post_link($post_id) ?: add_query_arg('preview', 'true', get_permalink($post_id));
    $url = is_string($url) ? $url : '';

    return iss_editorial_add_preview_args($url, $post_id, $format_slug);
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

    $post_id = 0;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context for asset config.
    if (isset($_GET['post'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context for asset config.
        $post_id = absint(wp_unslash($_GET['post']));
    }

    $post = $post_id > 0 ? get_post($post_id) : ($GLOBALS['post'] ?? null);
    $post_id = $post instanceof WP_Post ? (int) $post->ID : 0;
    $format = $post_id > 0 ? iss_editorial_get_format_for_post($post_id) : [];
    if (!$format || !$post instanceof WP_Post || !iss_editorial_uses_canvas($post)) {
        return;
    }

    iss_editorial_enqueue_archive_picker_assets($post_id);
    wp_enqueue_media(['post' => $post_id]);
    wp_enqueue_editor();
    // This canvas saves title, excerpt and document together. Keep heartbeat post locking,
    // but prevent the classic editor's separate autosave from replacing that same revision.
    wp_dequeue_script('autosave');

    $style_path = iss_editorial_admin_path() . 'assets/admin.css';
    if (file_exists($style_path)) {
        wp_enqueue_style('iss-editorial-admin', iss_editorial_admin_url() . 'assets/admin.css', [], (string) filemtime($style_path));
    }

    foreach (['editor-tokens', 'text-controls', 'workspace'] as $asset) {
        wp_enqueue_style('iss-editorial-' . $asset, iss_editorial_admin_url() . 'assets/' . $asset . '.css', ['iss-editorial-admin'], (string) filemtime(iss_editorial_admin_path() . 'assets/' . $asset . '.css'));
    }

    $set_media_picker_path = iss_editorial_admin_path() . 'assets/set-media-picker.js';
    if (file_exists($set_media_picker_path)) {
        wp_enqueue_script(
            'iss-editorial-set-media-picker',
            iss_editorial_admin_url() . 'assets/set-media-picker.js',
            ['wp-api-fetch'],
            (string) filemtime($set_media_picker_path),
            true
        );
        wp_localize_script(
            'iss-editorial-set-media-picker',
            'issEditorialSetMediaPicker',
            [
                'namespace' => 'iss-content/v1',
                'nonce' => wp_create_nonce('wp_rest'),
                'postId' => $post_id,
                'contextType' => (string) $screen->post_type,
                'strings' => [
                    'error' => __('Die Anfrage konnte nicht abgeschlossen werden.', 'iss-editorial'),
                ],
            ]
        );
    }

    $dnd_path = iss_editorial_admin_path() . 'assets/dnd.js';
    if (file_exists($dnd_path)) {
        wp_enqueue_script(
            'iss-editorial-dnd',
            iss_editorial_admin_url() . 'assets/dnd.js',
            [],
            (string) filemtime($dnd_path),
            true
        );
    }

    $ui_path = iss_editorial_admin_path() . 'assets/ui.js';
    if (file_exists($ui_path)) {
        wp_enqueue_script(
            'iss-editorial-ui',
            iss_editorial_admin_url() . 'assets/ui.js',
            [],
            (string) filemtime($ui_path),
            true
        );
    }

    foreach (['rich-text', 'live-preview', 'workspace'] as $asset) {
        wp_enqueue_script('iss-editorial-' . $asset, iss_editorial_admin_url() . 'assets/' . $asset . '.js', ['editor', 'wplink'], (string) filemtime(iss_editorial_admin_path() . 'assets/' . $asset . '.js'), true);
    }

    $script_path = iss_editorial_admin_path() . 'assets/admin.js';
    if (file_exists($script_path)) {
        $route_config = iss_editorial_get_route_station_editor_config($post_id, (string) $format['slug'], (string) $screen->post_type);
        $route_dependency = !empty($route_config['enabled']) && wp_script_is('iss-relations-route-stations', 'registered')
            ? 'iss-relations-route-stations'
            : '';
        $document = iss_editorial_hydrate_document_previews(iss_editorial_get_editor_document($post_id, (string) $format['slug']));
        $validation = iss_editorial_validate_document($document, (string) $format['slug']);
        $draft = iss_editorial_get_draft($post_id, (string) $format['slug']);
        if ($draft) { $draft['document'] = (array) apply_filters('iss_editorial_editor_document', $draft['document'], $post_id, (string) $format['slug']); }
        $draft_validation = $draft ? iss_editorial_validate_document($draft['document'], (string) $format['slug']) : [];
        $has_recovery = $draft && (is_wp_error($draft_validation) || $draft_validation !== iss_editorial_sanitize_document($document, (string) $format['slug']) || $draft['title'] !== $post->post_title || $draft['excerpt'] !== $post->post_excerpt);
        wp_enqueue_script(
            'iss-editorial-admin',
            iss_editorial_admin_url() . 'assets/admin.js',
            array_values(array_filter([
                file_exists($set_media_picker_path) ? 'iss-editorial-set-media-picker' : '',
                file_exists($dnd_path) ? 'iss-editorial-dnd' : '',
                file_exists($ui_path) ? 'iss-editorial-ui' : '',
                'editor',
                'iss-editorial-rich-text',
                'iss-editorial-live-preview',
                'iss-editorial-workspace',
                $route_dependency,
            ])),
            (string) filemtime($script_path),
            true
        );
        wp_localize_script(
            'iss-editorial-admin',
            'issEditorialAdmin',
            (array) apply_filters('iss_editorial_admin_settings', [
                'archiveRestRoot' => function_exists('iss_wf_import_archivset_rest_namespace')
                    ? esc_url_raw(rest_url(iss_wf_import_archivset_rest_namespace()))
                    : '',
                'contentRestRoot' => esc_url_raw(rest_url('iss-content/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'previewNonce' => wp_create_nonce('iss_editorial_preview_document'),
                'postId' => $post_id,
                'format' => (string) $format['slug'],
                'supportedVersions' => $format['supported_versions'],
                'textPalette' => iss_editorial_text_palette(),
                'livePreview' => !empty($format['editor']['preview']),
                'workspace' => !empty($format['editor']['workspace']),
                'formatLabel' => $format['label'],
                'starters' => $format['starters'],
                'isFrontPage' => $post_id === (int) get_option('page_on_front'),
                'document' => $document,
                'enabled' => true,
                'baseToken' => iss_editorial_saved_token($post_id, (string) $format['slug']),
                'draftToken' => (string) ($draft['token'] ?? ''),
                'recovery' => $has_recovery ? $draft : null,
                'validationError' => is_wp_error($validation) ? $validation->get_error_message() : '',
                'previewUrl' => iss_editorial_get_preview_url($post_id, (string) $format['slug']),
                'canPurgeDeletedSections' => current_user_can('manage_options'),
                'pageChoices' => iss_editorial_get_page_link_choices(),
                'sections' => (array) $format['sections'],
                'skins' => iss_editorial_get_format_skins((string) $format['slug']),
                'routeStations' => $route_config,
                'strings' => [
                    'pendingUpdate' => __('Noch nicht gesichert.', 'iss-editorial'),
                    'previewSaving' => __('Vorschau wird vorbereitet ...', 'iss-editorial'),
                    'previewError' => __('Die Vorschau konnte nicht vorbereitet werden.', 'iss-editorial'),
                    'previewReady' => __('Vorschau wurde geöffnet.', 'iss-editorial'),
                ],
            ], $post, $format)
        );
    }
}
add_action('admin_enqueue_scripts', 'iss_editorial_enqueue_admin_assets', 25);

function iss_editorial_ajax_save_preview_document(): void
{
    check_ajax_referer('iss_editorial_preview_document', 'nonce');

    $post_id = absint($_POST['post_id'] ?? 0);
    $format_slug = sanitize_key((string) ($_POST['format'] ?? ''));
    $document = isset($_POST['document']) ? wp_unslash((string) $_POST['document']) : '';

    if ($post_id <= 0 || $format_slug === '' || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error(['message' => __('Keine Berechtigung.', 'iss-editorial')], 403);
    }

    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format || (string) ($format['slug'] ?? '') !== $format_slug) {
        wp_send_json_error(['message' => __('Ungültiges Format.', 'iss-editorial')], 400);
    }

    $version = iss_editorial_check_edit_version(
        $post_id,
        $format_slug,
        sanitize_text_field(wp_unslash((string) ($_POST['base'] ?? ''))),
        sanitize_text_field(wp_unslash((string) ($_POST['draft_token'] ?? '')))
    );
    if (is_wp_error($version)) {
        wp_send_json_error(['message' => $version->get_error_message()], 409);
    }
    if (($_POST['intent'] ?? '') === 'discard') {
        $draft = iss_editorial_get_draft($post_id, $format_slug);
        if ($draft) {
            wp_delete_post_revision($draft['id']);
        }
        wp_send_json_success(['draftToken' => '']);
    }
    $validated = iss_editorial_validate_document($document, $format_slug);
    $decoded = json_decode($document, true);
    if (!is_array($decoded) || !iss_editorial_supports_version($format, $decoded['schema_version'] ?? null) || !isset($decoded['sections']) || !is_array($decoded['sections']) || !array_is_list($decoded['sections'])) {
        wp_send_json_error(['message' => is_wp_error($validated) ? $validated->get_error_message() : __('Der Entwurf konnte nicht gelesen werden.', 'iss-editorial')], 422);
    }
    $post_fields = [];
    foreach (['title', 'excerpt'] as $key) {
        if (isset($_POST[$key])) {
            $post_fields[$key] = wp_unslash((string) $_POST[$key]);
        }
    }
    $draft = iss_editorial_save_draft($post_id, $format_slug, $decoded, !empty($_POST['enabled']), $post_fields);
    if (is_wp_error($draft)) {
        wp_send_json_error(['message' => $draft->get_error_message()], 500);
    }

    wp_send_json_success([
        'previewUrl' => iss_editorial_get_preview_url($post_id, $format_slug),
        'draftToken' => $draft['token'],
        'savedAt' => $draft['modified'],
        'validationMessage' => is_wp_error($validated) ? $validated->get_error_message() : '',
    ]);
}
add_action('wp_ajax_iss_editorial_save_preview_document', 'iss_editorial_ajax_save_preview_document');

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
    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format) {
        return;
    }

    $format_slug = (string) $format['slug'];
    $raw = isset($_POST['iss_editorial']) && is_array($_POST['iss_editorial']) ? wp_unslash($_POST['iss_editorial']) : [];
    $format_payload = is_array($raw[$format_slug] ?? null) ? $raw[$format_slug] : [];
    if (!$format_payload) {
        return;
    }

    if (!iss_editorial_save_document($post_id, $format_slug, (string) ($format_payload['document'] ?? ''), false)) {
        return;
    }
    if (empty($format['always_enabled']) && array_key_exists('enabled', $format_payload)) {
        $was_enabled = iss_editorial_document_is_enabled($post_id, $format_slug);
        iss_editorial_set_document_enabled($post_id, $format_slug, !empty($format_payload['enabled']));
        if (!$was_enabled && !empty($format_payload['enabled'])) {
            do_action('iss_editorial_document_saved', $post_id, $format_slug, iss_editorial_get_document($post_id, $format_slug));
        }
    }
    $draft = iss_editorial_get_draft($post_id, $format_slug);
    if ($draft) {
        wp_delete_post_revision($draft['id']);
    }
    delete_post_meta($post_id, iss_editorial_get_autosave_meta_key($format_slug));
}
add_action('save_post', 'iss_editorial_save_meta_box', 35, 1);
