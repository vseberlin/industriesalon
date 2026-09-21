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
    $draft = iss_editorial_get_draft($post_id, $format['slug']);
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authenticated preview, token identifies the saved snapshot.
    $token = sanitize_text_field(wp_unslash((string) ($_GET['iss_editorial_snapshot'] ?? '')));
    if (!$draft || !hash_equals($draft['token'], $token) || is_wp_error(iss_editorial_validate_document($draft['document'], $format['slug']))) {
        return [];
    }
    return ['token' => $token, 'postId' => $post_id];
}

add_action('template_redirect', static function (): void {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Authentication and snapshot are checked by iss_editorial_embedded_preview.
    if (empty($_GET['iss_editorial_embed'])) {
        return;
    }
    nocache_headers();
    if (!iss_editorial_embedded_preview()) {
        wp_die(esc_html__('Diese Vorschau ist nicht mehr aktuell. Bitte im Editor erneut laden.', 'iss-editorial'), '', ['response' => 409]);
    }
    show_admin_bar(false);
});

add_action('wp_enqueue_scripts', static function (): void {
    $context = iss_editorial_embedded_preview();
    if (!$context) {
        return;
    }
    wp_enqueue_style('iss-editorial-preview-frame', iss_editorial_admin_url() . 'assets/preview-frame.css', [], (string) filemtime(iss_editorial_admin_path() . 'assets/preview-frame.css'));
    wp_enqueue_script('iss-editorial-preview-frame', iss_editorial_admin_url() . 'assets/preview-frame.js', [], (string) filemtime(iss_editorial_admin_path() . 'assets/preview-frame.js'), true);
    wp_localize_script('iss-editorial-preview-frame', 'issEditorialPreviewFrame', $context);
});
