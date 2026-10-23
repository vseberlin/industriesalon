<?php
/** Adapt the upstream role switch to draft-only editorial access. */
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
    if (str_starts_with($action, 'tnp') || str_starts_with($action, 'newsletter')) {
        return in_array($action, ['tnpc_options', 'tnpc_get_all_presets', 'tnpc_get_preset',
            'tnpc_render', 'tnpc_preview', 'tnpc_css', 'tnpc_block_form'], true);
    }
    if ($na !== '' && !in_array($na, ['emails-css', 'emails-composer-css', 'emails-preview',
        'emails-version-preview', 'emails-preview-text', 'emails-create', 'v', 'view'], true)) { return false; }
    if (str_starts_with($page, 'newsletter_') && !in_array($page, iss_newsletter_editorial_pages(), true)) { return false; }
    if (!str_starts_with($page, 'newsletter_') && $na === '') { return true; }
    if (!in_array($act, ['', 'save', 'next', 'preview', 'reset', 'create', 'refresh', 'copy', 'search'], true)) { return false; }
    if ($act !== '' && !empty($request['id'])) {
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
        wp_die(esc_html__('Dieses Konto darf Newsletter-Entwürfe bearbeiten, aber nicht versenden oder löschen.', 'iss-newsletter'), '', ['response' => 403]);
    }
}, 0);

add_action('admin_menu', static function (): void {
    if (!iss_newsletter_is_editorial_operator()) { return; }
    global $submenu;
    // Upstream retains hidden editor routes; only expose the draft workflow.
    $submenu['newsletter_main_index'] = [
        [__('Newsletter', 'iss-newsletter'), 'iss_manage_newsletter_content', 'newsletter_emails_index'],
        [__('Neu erstellen', 'iss-newsletter'), 'iss_manage_newsletter_content', 'admin.php?page=newsletter_emails_composer'],
    ];
}, PHP_INT_MAX);

add_action('load-toplevel_page_newsletter_main_index', static function (): void {
    if (iss_newsletter_is_editorial_operator()) {
        wp_safe_redirect(admin_url('admin.php?page=newsletter_emails_index'));
        exit;
    }
});
