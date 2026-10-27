<?php
/** Bound upstream access to editorial pages and explicit delivery capabilities. */
if (!defined('ABSPATH')) { exit; }

function iss_newsletter_is_editorial_operator(): bool
{
    return function_exists('iss_core_is_editorial_operator') && iss_core_is_editorial_operator();
}

add_filter('option_newsletter_main', static function ($options) {
    if (iss_newsletter_is_editorial_operator()) {
        $options = (array) $options;
        $options['roles'][] = 'iss_manage_newsletter_content';
    }
    return $options;
});

function iss_newsletter_editorial_pages(): array
{
    return ['newsletter_main_index', 'newsletter_emails_index', 'newsletter_emails_new',
        'newsletter_emails_composer', 'newsletter_emails_edit', 'newsletter_emails_editorhtml',
        'newsletter_emails_editortinymce', 'newsletter_emails_logs', 'newsletter_emails_versions'];
}

/** Upstream checks only one role switch for drafts, delivery and deletion. */
function iss_newsletter_editorial_request_allowed(array $request): bool
{
    $page = (string) ($request['page'] ?? '');
    $action = (string) ($request['action'] ?? '');
    $na = (string) ($request['na'] ?? '');
    $act = (string) ($request['act'] ?? '');
    $can_send = current_user_can('iss_send_newsletter');
    if (str_starts_with($action, 'tnp') || str_starts_with($action, 'newsletter')) {
        if ($can_send && in_array($action, ['tnpc_test', 'tnpc_test_raw_html', 'tnpc_regenerate_email'], true)) { return true; }
        return in_array($action, ['tnpc_options', 'tnpc_get_all_presets', 'tnpc_get_preset',
            'tnpc_render', 'tnpc_preview', 'tnpc_css', 'tnpc_block_form'], true);
    }
    if ($na !== '' && !in_array($na, ['emails-css', 'emails-composer-css', 'emails-preview',
        'emails-version-preview', 'emails-preview-text', 'emails-create', 'v', 'view'], true)) { return false; }
    if (str_starts_with($page, 'newsletter_') && !in_array($page, iss_newsletter_editorial_pages(), true)) { return false; }
    if (!str_starts_with($page, 'newsletter_') && $na === '') { return true; }
    $allowed = ['', 'save', 'next', 'preview', 'reset', 'create', 'refresh', 'copy', 'search'];
    if ($can_send) {
        $allowed = array_merge($allowed, ['send', 'schedule', 'test', 'continue', 'pause', 'abort',
            'delete', 'delete_selected', 'template', 'html', 'change-private']);
    }
    if (!in_array($act, $allowed, true)) { return false; }
    if (!$can_send && $act !== '' && !empty($request['id'])) {
        // @phpstan-ignore-next-line -- Optional upstream Newsletter plugin owns this API.
        $email = Newsletter::instance()->get_email(absint($request['id']));
        if (!$email || !in_array($email->status, ['new', 'draft'], true)) { return false; }
    }
    return true;
}

add_action('init', static function (): void {
    if (!iss_newsletter_is_editorial_operator()) { return; }
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Additional denial gate; upstream validates action nonces.
    if (!iss_newsletter_editorial_request_allowed(wp_unslash($_REQUEST))) {
        wp_die(esc_html__('Dieses Konto hat keine Berechtigung für diese Newsletter-Aktion.', 'iss-newsletter'), '', ['response' => 403]);
    }
}, 0);

add_action('admin_menu', static function (): void {
    if (!iss_newsletter_is_editorial_operator()) { return; }
    global $submenu;
    // Upstream retains hidden editor routes; expose only the editorial workflow.
    $submenu['newsletter_main_index'] = [
        [__('Newsletter', 'iss-newsletter'), 'iss_manage_newsletter_content', 'newsletter_emails_index', __('Newsletter', 'iss-newsletter')],
        [__('Neu erstellen', 'iss-newsletter'), 'iss_manage_newsletter_content', 'admin.php?page=newsletter_emails_composer', __('Neuer Newsletter', 'iss-newsletter')],
    ];
}, PHP_INT_MAX);

add_action('load-toplevel_page_newsletter_main_index', static function (): void {
    if (iss_newsletter_is_editorial_operator()) {
        wp_safe_redirect(admin_url('admin.php?page=newsletter_emails_index'));
        exit;
    }
});
