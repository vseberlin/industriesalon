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
    $format = iss_editorial_get_format($format_slug);
    return in_array($post_type, $format['post_types'] ?? [], true)
        && is_callable($format['route_stations_callback'] ?? null);
}

function iss_editorial_get_route_station_editor_config(int $post_id, string $format_slug, string $post_type): array
{
    if (!iss_editorial_uses_integrated_route_stations($format_slug, $post_type)) {
        return [
            'enabled' => false,
        ];
    }

    return (array) call_user_func(iss_editorial_get_format($format_slug)['route_stations_callback'], $post_id);
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
    // Preserve rich-text entities when the browser decodes HTML attributes.
    $document_attribute = wp_json_encode($document, JSON_HEX_AMP);

    wp_nonce_field('iss_editorial_save_document', 'iss_editorial_nonce');

    echo '<div class="iss-editorial-shell">';
    echo '<div class="iss-editorial-admin iss-editorial-admin--canvas" data-format="' . esc_attr($format_slug) . '" data-post-id="' . esc_attr((string) $post->ID) . '">';
    echo '<p class="iss-editorial-startup notice notice-error" role="alert">' . esc_html__('Der Editor ist noch nicht bereit. Falls diese Meldung stehen bleibt, wurden benötigte Dateien nicht geladen. Bitte nicht speichern, sondern die Seite neu öffnen oder den Fehler melden. Die gespeicherten Inhalte bleiben erhalten.', 'iss-editorial') . '</p>';
    // Existing canvases stay enabled; this also opts a new auto-draft into the engine.
    echo '<input type="hidden" class="iss-editorial-enabled-field" name="iss_editorial[' . esc_attr($format_slug) . '][enabled]" value="1">';
    echo '<input type="hidden" class="iss-editorial-document-field" name="iss_editorial[' . esc_attr($format_slug) . '][document]" value="' . esc_attr($document_attribute) . '">';
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
    echo '<div class="iss-editorial-root" data-sections="' . esc_attr(wp_json_encode((array) $format['sections'], JSON_HEX_AMP)) . '"></div>';
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

    iss_editorial_track_lock_session($post_id, (string) get_post_meta($post_id, '_edit_lock', true));

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

    foreach (['rich-text', 'live-preview', 'help', 'workspace', 'pickers'] as $asset) {
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
        if ($draft) { $draft['document'] = iss_editorial_hydrate_document_previews((array) apply_filters('iss_editorial_editor_document', $draft['document'], $post_id, (string) $format['slug'])); }
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
                'heartbeat',
                'wp-hooks',
                'iss-admin-request',
                'iss-editorial-rich-text',
                'iss-editorial-live-preview',
                'iss-editorial-workspace',
                'iss-editorial-help',
                'iss-editorial-pickers',
                $route_dependency,
            ])),
            (string) filemtime($script_path),
            true
        );
        $settings = (array) apply_filters('iss_editorial_admin_settings', [
                'archiveRestRoot' => function_exists('iss_wf_import_archivset_rest_namespace')
                    ? esc_url_raw(rest_url(iss_wf_import_archivset_rest_namespace()))
                    : '',
                'contentRestRoot' => esc_url_raw(rest_url('iss-content/v1')),
                'nonce' => wp_create_nonce('wp_rest'),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'previewNonce' => wp_create_nonce('iss_editorial_preview_document'),
                'postId' => $post_id,
                'userId' => get_current_user_id(),
                'format' => (string) $format['slug'],
                'publishing' => [
                    'status' => $post->post_status,
                    'canPublish' => current_user_can(get_post_type_object($post->post_type)->cap->publish_posts),
                ],
                'supportedVersions' => $format['supported_versions'],
                'textPalette' => iss_editorial_text_palette(),
                'livePreview' => !empty($format['editor']['preview']),
                'workspace' => !empty($format['editor']['workspace']),
                'orderNotice' => sanitize_text_field($format['editor']['order_notice'] ?? ''),
                'formatLabel' => get_post_type_object($post->post_type)->labels->singular_name ?? $format['label'],
                'starters' => $format['starters'],
                'identityPalettes' => $format['editor']['identity_palettes'] ?? [],
                'railControl' => !empty($format['editor']['rail_control']),
                'railPlacementBySkin' => (array) ($format['editor']['rail_placement_by_skin'] ?? []),
                'openingSkins' => iss_editorial_get_opening_skins($format, $post),
                'document' => $document,
                'enabled' => true,
                'baseToken' => iss_editorial_saved_token($post_id, (string) $format['slug']),
                'draftToken' => (string) ($draft['token'] ?? ''),
                'recovery' => $has_recovery ? $draft : null,
                'validationError' => is_wp_error($validation) ? $validation->get_error_message() : '',
                'validation' => is_wp_error($validation) ? ['code' => $validation->get_error_code(), 'message' => $validation->get_error_message(), 'details' => $validation->get_error_data()] : null,
                'rawDocument' => (string) get_metadata_raw('post', $post_id, iss_editorial_get_document_meta_key($format['slug']), true),
                'previewUrl' => iss_editorial_get_preview_url($post_id, (string) $format['slug']),
                'canPurgeDeletedSections' => current_user_can('edit_post', $post_id) && current_user_can('delete_post', $post_id),
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
            ], $post, $format);
        // Owner filters may inspect the document; the browser reads the hidden field.
        unset($settings['document'], $settings['sections'], $settings['enabled'], $settings['workspace']);
        wp_localize_script('iss-editorial-admin', 'issEditorialAdmin', $settings);
    }
}
add_action('admin_enqueue_scripts', 'iss_editorial_enqueue_admin_assets', 25);

/** Keep the machine-readable cause and field path across the transport boundary. */
function iss_editorial_send_error(WP_Error $error, int $status): void
{
    wp_send_json_error(['code' => $error->get_error_code(), 'message' => $error->get_error_message(), 'details' => $error->get_error_data()], $status);
}

function iss_editorial_ajax_save_preview_document(): void
{
    if (!check_ajax_referer('iss_editorial_preview_document', 'nonce', false)) {
        iss_editorial_send_error(new WP_Error('editorial_session_expired', __('Die Sitzung ist abgelaufen. Bitte Ihre Änderungen herunterladen und die Anmeldung in einem neuen Fenster prüfen.', 'iss-editorial')), 403);
    }

    $post_id = absint($_POST['post_id'] ?? 0);
    $format_slug = sanitize_key((string) ($_POST['format'] ?? ''));
    $document = isset($_POST['document']) ? wp_unslash((string) $_POST['document']) : '';

    if ($post_id <= 0 || $format_slug === '' || !current_user_can('edit_post', $post_id)) {
        iss_editorial_send_error(new WP_Error('editorial_permission', __('Keine Berechtigung zum Bearbeiten dieses Inhalts.', 'iss-editorial')), 403);
    }

    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format || (string) ($format['slug'] ?? '') !== $format_slug) {
        iss_editorial_send_error(new WP_Error('editorial_format', __('Das Inhaltsformat ist nicht mehr verfügbar. Bitte Änderungen herunterladen und die Seite neu öffnen.', 'iss-editorial')), 400);
    }

    if (($_POST['intent'] ?? '') === 'inspect') {
        $post = get_post($post_id);
        wp_send_json_success([
            'baseToken' => iss_editorial_saved_token($post_id, $format_slug),
            'canonical' => ['document' => iss_editorial_get_editor_document($post_id, $format_slug), 'title' => $post->post_title, 'excerpt' => $post->post_excerpt],
            'draft' => iss_editorial_get_draft($post_id, $format_slug),
        ]);
    }
    if (!iss_core_acquire_post_write_lock($post_id)) {
        iss_editorial_send_error(new WP_Error('editorial_write_busy', __('Eine Speicherung läuft noch. Bitte kurz warten und erneut sichern.', 'iss-editorial')), 409);
    }

    $version = iss_editorial_check_edit_version(
        $post_id,
        $format_slug,
        sanitize_text_field(wp_unslash((string) ($_POST['base'] ?? ''))),
        sanitize_text_field(wp_unslash((string) ($_POST['draft_token'] ?? '')))
    );
    if (is_wp_error($version)) {
        // A response can be lost after the autosave committed. A retry may only
        // acknowledge that exact content, never adopt a different tab's work.
        $draft = iss_editorial_get_draft($post_id, $format_slug);
        if ($version->get_error_code() === 'editorial_draft_conflict' && ($_POST['intent'] ?? '') === 'discard' && !$draft) {
            wp_send_json_success(['draftToken' => '']);
        }
        $decoded = json_decode($document, true);
        $same = $version->get_error_code() === 'editorial_draft_conflict'
            && !in_array($_POST['intent'] ?? '', ['discard', 'validate'], true)
            && is_array($decoded) && $draft
            && iss_editorial_recovery_document($decoded) === $draft['document']
            && sanitize_text_field(wp_unslash((string) ($_POST['title'] ?? ''))) === $draft['title']
            && wp_kses_post(wp_unslash((string) ($_POST['excerpt'] ?? ''))) === $draft['excerpt']
            && (!empty($_POST['enabled']) || !empty($format['always_enabled'])) === $draft['enabled'];
        if (!$same) { iss_editorial_send_error($version, 409); }
        $validated = iss_editorial_validate_document($document, $format_slug);
        wp_send_json_success(iss_editorial_draft_response($post_id, $format_slug, $draft, $validated));
    }
    if (($_POST['intent'] ?? '') === 'discard') {
        $draft = iss_editorial_get_draft($post_id, $format_slug);
        if ($draft) {
            if (!wp_delete_post_revision($draft['id'])) {
                iss_editorial_send_error(new WP_Error('editorial_discard_failed', __('Der Entwurf konnte nicht verworfen werden. Er bleibt erhalten.', 'iss-editorial')), 500);
            }
        }
        wp_send_json_success(['draftToken' => '']);
    }
    $validated = iss_editorial_validate_document($document, $format_slug);
    if (($_POST['intent'] ?? '') === 'validate') {
        if (is_wp_error($validated)) { iss_editorial_send_error($validated, 422); }
        $stored = iss_editorial_decode_document(get_metadata_raw('post', $post_id, iss_editorial_get_document_meta_key($format_slug), true));
        $draft = iss_editorial_get_draft($post_id, $format_slug);
        // A validated restore may be edited immediately, before the next autosave.
        foreach ([$stored, $draft['document'] ?? []] as $source) {
            foreach ((array) ($source['deleted_sections'] ?? []) as $deleted) {
                $restored = iss_editorial_validate_document(['schema_version' => $validated['schema_version'], 'sections' => [$deleted]], $format_slug);
                if (is_wp_error($restored)) { continue; }
                foreach ($validated['sections'] as &$section) {
                    if ($section === $restored['sections'][0]) { $section['restored_from'] = hash('sha256', iss_editorial_encode_document($deleted)); break; }
                }
                unset($section);
            }
        }
        wp_send_json_success(['valid' => true, 'document' => iss_editorial_hydrate_document_previews($validated)]);
    }
    $decoded = json_decode($document, true);
    if (!is_array($decoded) || !iss_editorial_supports_version($format, $decoded['schema_version'] ?? null) || !isset($decoded['sections']) || !is_array($decoded['sections']) || !array_is_list($decoded['sections'])) {
        iss_editorial_send_error(is_wp_error($validated) ? $validated : new WP_Error('editorial_invalid_schema', __('Der Entwurf konnte nicht gelesen werden.', 'iss-editorial')), 422);
    }
    $post_fields = [];
    foreach (['title', 'excerpt'] as $key) {
        if (isset($_POST[$key])) {
            $post_fields[$key] = wp_unslash((string) $_POST[$key]);
        }
    }
    $draft = iss_editorial_save_draft($post_id, $format_slug, $decoded, !empty($_POST['enabled']), $post_fields);
    if (is_wp_error($draft)) {
        iss_editorial_send_error($draft, 500);
    }
    wp_send_json_success(iss_editorial_draft_response($post_id, $format_slug, $draft, $validated));
}

function iss_editorial_draft_response(int $post_id, string $format_slug, array $draft, $validated): array
{
    return [
        'previewUrl' => iss_editorial_get_preview_url($post_id, $format_slug),
        'draftToken' => $draft['token'],
        'savedAt' => $draft['modified'],
        'validationMessage' => is_wp_error($validated) ? $validated->get_error_message() : '',
        'validation' => is_wp_error($validated) ? ['code' => $validated->get_error_code(), 'message' => $validated->get_error_message(), 'details' => $validated->get_error_data()] : null,
    ];
}
add_action('wp_ajax_iss_editorial_save_preview_document', 'iss_editorial_ajax_save_preview_document');

function iss_editorial_save_meta_box(int $post_id): void
{
    // A nested owner update must never consume another post's submitted canvas.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Matching identity only; nonce and capability checks follow.
    if ($post_id !== absint($_POST['post_ID'] ?? 0)) { return; }
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

    $enabled = array_key_exists('enabled', $format_payload) ? !empty($format_payload['enabled']) : null;
    if (!iss_editorial_save_document($post_id, $format_slug, (string) ($format_payload['document'] ?? ''), false, $enabled)) {
        wp_die(esc_html__('Die Abschnitte konnten nicht gespeichert werden. Bitte die Seite neu öffnen und den gesicherten Entwurf prüfen.', 'iss-editorial'), esc_html__('Inhalt nicht gespeichert', 'iss-editorial'), ['response' => 500, 'back_link' => true]);
    }
    $draft = iss_editorial_get_draft($post_id, $format_slug);
    if ($draft) {
        if (!wp_delete_post_revision($draft['id'])) {
            wp_die(esc_html__('Der Inhalt wurde gespeichert, aber der private Entwurf konnte nicht aufgeräumt werden. Bitte die Fassungen beim nächsten Öffnen vergleichen.', 'iss-editorial'), '', ['response' => 500, 'back_link' => true]);
        }
    }
}
add_action('save_post', 'iss_editorial_save_meta_box', 35, 1);
