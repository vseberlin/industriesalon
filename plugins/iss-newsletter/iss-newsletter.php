<?php
/**
 * Plugin Name: ISS Newsletter
 * Description: Industriesalon integration layer for The Newsletter Plugin.
 * Version: 0.1.0
 * Author: Industriesalon
 */

if (!defined('ABSPATH')) {
    exit;
}

define('ISS_NEWSLETTER_VERSION', '0.1.0');

function iss_newsletter_render_form_block(): string
{
    if (!shortcode_exists('newsletter_form')) {
        return '<p><a class="iss-action-link" href="' . esc_url(home_url('/newsletter/')) . '">'
            . esc_html__('Zum Newsletter anmelden', 'iss-newsletter')
            . '</a></p>';
    }

    return do_shortcode('[newsletter_form fields="email,privacy" class="iss-newsletter-form" show_labels="false"]');
}

function iss_newsletter_register_blocks(): void
{
    $block_dir = __DIR__ . '/blocks/newsletter-form';
    $editor_script = 'iss-newsletter-form-editor';
    $editor_asset = $block_dir . '/editor.js';

    wp_register_script(
        $editor_script,
        plugins_url('blocks/newsletter-form/editor.js', __FILE__),
        ['wp-blocks', 'wp-element', 'wp-i18n'],
        file_exists($editor_asset) ? (string) filemtime($editor_asset) : ISS_NEWSLETTER_VERSION,
        true
    );

    register_block_type($block_dir, [
        'editor_script' => $editor_script,
        'render_callback' => 'iss_newsletter_render_form_block',
    ]);
}
add_action('init', 'iss_newsletter_register_blocks');

function iss_newsletter_dependency_active(): bool
{
    return defined('NEWSLETTER_VERSION') && class_exists('Newsletter') && class_exists('TNP_Subscription');
}

function iss_newsletter_configure_defaults(): void
{
    if (!iss_newsletter_dependency_active()) {
        return;
    }

    $antispam = get_option('newsletter_antispam', []);
    $antispam = is_array($antispam) ? $antispam : [];
    $antispam = array_merge($antispam, [
        'antiflood' => 300,
        'captcha' => 0,
        'disabled' => 0,
    ]);

    $blacklist = isset($antispam['address_blacklist']) && is_array($antispam['address_blacklist'])
        ? $antispam['address_blacklist']
        : [];
    $blacklist = array_values(array_unique(array_filter(array_merge($blacklist, [
        'txt.att.net',
        'vtext.com',
        'tmomail.net',
        'messaging.sprintpcs.com',
        'pm.sprint.com',
        'myboostmobile.com',
        'mms.att.net',
        'sms.mycricket.com',
        'msg.fi.google.com',
    ]))));
    $antispam['address_blacklist'] = $blacklist;
    update_option('newsletter_antispam', $antispam, false);

    $form = get_option('newsletter_form', []);
    $form = is_array($form) ? $form : [];
    $form = array_merge($form, [
        'name_status' => '1',
        'name_rules' => '0',
        'surname_status' => '1',
        'surname_rules' => '0',
        'sex_status' => '0',
        'privacy_status' => '1',
        'privacy_use_wp_url' => '0',
        'privacy_url' => home_url('/verein/#datenschutz'),
        'email' => __('E-Mail', 'iss-newsletter'),
        'email_placeholder' => __('name@example.org', 'iss-newsletter'),
        'name' => __('Vorname', 'iss-newsletter'),
        'surname' => __('Nachname', 'iss-newsletter'),
        'privacy' => __('Ich akzeptiere die Datenschutzerklärung.', 'iss-newsletter'),
        'subscribe' => __('Anmelden', 'iss-newsletter'),
        'title_female' => __('Frau', 'iss-newsletter'),
    ]);
    update_option('newsletter_form', $form, true);
}
add_action('newsletter_loaded', 'iss_newsletter_configure_defaults', 20);
register_activation_hook(__FILE__, 'iss_newsletter_configure_defaults');

function iss_newsletter_is_random_name_token(string $value): bool
{
    $value = trim($value);
    if ($value === '') {
        return false;
    }

    $letters = preg_replace('/[^A-Za-zÄÖÜäöüß]/u', '', $value);
    if (!is_string($letters) || strlen($letters) < 10) {
        return false;
    }

    if (str_contains($value, ' ') || str_contains($value, '-') || str_contains($value, "'")) {
        return false;
    }

    return (bool) preg_match('/[a-z]/', $letters) && (bool) preg_match('/[A-Z]/', $letters);
}

function iss_newsletter_email_domain(string $email): string
{
    $email = strtolower(trim($email));
    if (!str_contains($email, '@')) {
        return '';
    }

    return substr(strrchr($email, '@'), 1) ?: '';
}

function iss_newsletter_looks_like_gmail_alias_bot(string $email): bool
{
    $email = strtolower(trim($email));
    if (!str_ends_with($email, '@gmail.com') || !str_contains($email, '@')) {
        return false;
    }

    $local = strstr($email, '@', true);
    if (!is_string($local)) {
        return false;
    }

    return str_contains($local, '+')
        || substr_count($local, '.') >= 3
        || (bool) preg_match('/\.[a-z]\./', $local);
}

function iss_newsletter_is_tor_like_ip(string $ip): bool
{
    $ip = trim($ip);
    foreach ([
        '185.220.100.',
        '185.220.101.',
        '185.220.102.',
        '185.220.103.',
        '192.42.116.',
        '23.129.64.',
    ] as $prefix) {
        if (str_starts_with($ip, $prefix)) {
            return true;
        }
    }

    return false;
}

function iss_newsletter_subscription_has_bot_shape(TNP_Subscription $subscription): bool
{
    $data = $subscription->data;
    $email = is_string($data->email) ? $data->email : '';
    $name = is_string($data->name) ? $data->name : '';
    $surname = is_string($data->surname) ? $data->surname : '';
    $ip = is_string($data->ip) ? $data->ip : '';

    $score = 0;
    $sms_domains = [
        'txt.att.net',
        'vtext.com',
        'tmomail.net',
        'messaging.sprintpcs.com',
        'pm.sprint.com',
        'myboostmobile.com',
        'mms.att.net',
        'sms.mycricket.com',
        'msg.fi.google.com',
    ];

    if (in_array(iss_newsletter_email_domain($email), $sms_domains, true)) {
        $score += 3;
    }

    if (iss_newsletter_is_random_name_token($name) || iss_newsletter_is_random_name_token($surname)) {
        $score += 2;
    }

    if (iss_newsletter_looks_like_gmail_alias_bot($email)) {
        $score += 1;
    }

    if (iss_newsletter_is_tor_like_ip($ip)) {
        $score += 2;
    }

    if (strlen(trim($name)) >= 14 && strlen(trim($surname)) >= 18) {
        $score += 1;
    }

    return $score >= 3;
}

function iss_newsletter_filter_subscription($subscription, $existing_user)
{
    if (!$subscription instanceof TNP_Subscription) {
        return $subscription;
    }

    if ($existing_user) {
        return $subscription;
    }

    if (!iss_newsletter_subscription_has_bot_shape($subscription)) {
        return $subscription;
    }

    if (class_exists('Newsletter\\Logs')) {
        Newsletter\Logs::add('iss-newsletter-antispam', 'Blocked bot-shaped newsletter subscription.', 0);
    }

    return false;
}
add_filter('newsletter_subscription', 'iss_newsletter_filter_subscription', 10, 2);

function iss_newsletter_admin_dependency_notice(): void
{
    if (!current_user_can('activate_plugins') || iss_newsletter_dependency_active()) {
        return;
    }

    echo '<div class="notice notice-warning"><p>'
        . esc_html__('ISS Newsletter is active, but The Newsletter Plugin is not active. Frontend signup falls back to the Newsletter public page only after that dependency is enabled.', 'iss-newsletter')
        . '</p></div>';
}
add_action('admin_notices', 'iss_newsletter_admin_dependency_notice');
