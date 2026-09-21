<?php
/** Authenticated, snapshot-bound embedding of the existing WordPress preview. */
if (!defined('ABSPATH')) {
    exit;
}

function iss_editorial_embedded_preview(): array
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The existing preview nonce is verified below.
    if (empty($_GET['iss_editorial_embed'])) {
        return [];
    }
    $post_id = (int) get_queried_object_id();
    $format = iss_editorial_get_format_for_post($post_id);
    if (!$format || !iss_editorial_should_prefer_preview_autosave($post_id, $format['slug'])) {
        return [];
    }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authenticated preview, token identifies the saved snapshot.
    $token = sanitize_text_field(wp_unslash((string) ($_GET['iss_editorial_snapshot'] ?? '')));
    static $snapshots = [];
    $key = get_current_user_id() . ':' . $post_id . ':' . $format['slug'] . ':' . $token;
    if (array_key_exists($key, $snapshots)) { return $snapshots[$key]; }
    $snapshots[$key] = [];
    $draft = iss_editorial_get_draft($post_id, $format['slug']);
    if (!$draft || !hash_equals($draft['token'], $token)) { return []; }
    $document = iss_editorial_validate_document($draft['document'], $format['slug']);
    if (is_wp_error($document)) { return []; }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authenticated and snapshot-bound above; this flag only enables editor controls.
    $canvas = $format['slug'] === 'landing' && !empty($_GET['iss_editorial_canvas']);
    $snapshots[$key] = ['canvas' => $canvas, 'token' => $token, 'postId' => $post_id, 'format' => $format['slug'], 'document' => $document, 'enabled' => $draft['enabled']];
    return $snapshots[$key];
}

add_action('template_redirect', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authentication and snapshot are checked by iss_editorial_embedded_preview.
    if (empty($_GET['iss_editorial_embed'])) {
        return;
    }
    nocache_headers();
    if (!iss_editorial_embedded_preview()) {
        // The error page sends only the requested token, never private draft data.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Invalid/unauthenticated previews disclose no content.
        $token = sanitize_text_field(wp_unslash((string) ($_GET['iss_editorial_snapshot'] ?? '')));
        $script = wp_get_script_tag(['src' => iss_editorial_admin_url() . 'assets/preview-frame.js', 'data-iss-preview-stale' => $token]);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_script_tag escapes every attribute; the message is escaped separately.
        wp_die(esc_html__('Diese Vorschau ist nicht mehr aktuell. Bitte im Editor erneut laden.', 'iss-editorial') . $script, '', ['response' => 409]);
    }
    show_admin_bar(false);
});

add_action('wp_enqueue_scripts', static function (): void {
    $context = iss_editorial_embedded_preview();
    if (!$context) {
        return;
    }
    $editing = !empty($context['canvas']) && ($context['document']['schema_version'] ?? 1) >= 2;
    if ($editing) {
        wp_enqueue_editor();
        wp_add_inline_script('wplink', 'window.ajaxurl = ' . wp_json_encode(admin_url('admin-ajax.php')) . ';', 'before');
        wp_enqueue_script('iss-editorial-rich-text', iss_editorial_admin_url() . 'assets/rich-text.js', ['editor', 'wplink'], (string) filemtime(iss_editorial_admin_path() . 'assets/rich-text.js'), true);
        wp_localize_script('iss-editorial-rich-text', 'issEditorialAdmin', ['textPalette' => iss_editorial_text_palette()]);
        wp_enqueue_style('iss-editorial-text-controls', iss_editorial_admin_url() . 'assets/text-controls.css', ['editor-buttons'], (string) filemtime(iss_editorial_admin_path() . 'assets/text-controls.css'));
    }
    wp_enqueue_style('iss-editorial-editor-tokens', iss_editorial_admin_url() . 'assets/editor-tokens.css', [], (string) filemtime(iss_editorial_admin_path() . 'assets/editor-tokens.css'));
    wp_enqueue_style('iss-editorial-preview-frame', iss_editorial_admin_url() . 'assets/preview-frame.css', [], (string) filemtime(iss_editorial_admin_path() . 'assets/preview-frame.css'));
    wp_enqueue_script('iss-editorial-preview-frame', iss_editorial_admin_url() . 'assets/preview-frame.js', [], (string) filemtime(iss_editorial_admin_path() . 'assets/preview-frame.js'), true);
    wp_localize_script('iss-editorial-preview-frame', 'issEditorialPreviewFrame', ['token' => $context['token'], 'postId' => $context['postId'], 'editing' => $editing, 'palette' => $editing ? iss_editorial_text_palette() : []]);
});
