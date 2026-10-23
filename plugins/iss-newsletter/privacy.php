<?php
/** Privacy policy adapter. Keep subscriber management and delivery with The Newsletter Plugin. */
if (!defined('ABSPATH')) { exit; }

function iss_newsletter_privacy_defaults(): array
{
    return [
        'newsletter_main' => ['track' => 0],
        'newsletter_subscription' => ['noconfirmation' => 0, 'optin_override' => 0, 'confirmed_tracking' => '', 'confirmation_tracking' => ''],
        'newsletter_form' => [
            'privacy_status' => '1', 'privacy_use_wp_url' => '0', 'privacy_url' => home_url('/verein/#datenschutz'),
            'privacy' => 'Ich möchte den Newsletter des Industriesalons erhalten. Ich kann meine Einwilligung jederzeit über den Abmeldelink widerrufen. Hinweise in der Datenschutzerklärung.',
        ],
    ];
}

/** Explicit migration/activation only; never change options during public reads. */
function iss_newsletter_apply_privacy_defaults(): void
{
    foreach (iss_newsletter_privacy_defaults() as $name => $values) {
        $next = array_merge((array) get_option($name, []), $values);
        update_option($name, $next, false);
        if (get_option($name) !== $next) { throw new RuntimeException('Could not persist newsletter privacy settings.'); }
    }
}

function iss_newsletter_privacy_checks(): array
{
    $rows = [];
    foreach (iss_newsletter_privacy_defaults() as $name => $values) {
        $stored = (array) get_option($name, []);
        $ok = true;
        foreach ($values as $key => $value) { if (!isset($stored[$key]) || (string) $stored[$key] !== (string) $value) { $ok = false; } }
        $rows[] = ['group' => 'privacy', 'name' => $name, 'status' => $ok ? 'ok' : 'warning', 'details' => $ok ? 'Double-Opt-in, Einwilligungstext und Tracking-Einstellungen entsprechen der Website-Richtlinie.' : 'Gespeicherte Einstellungen weichen von der Datenschutz-Richtlinie ab. Vor weiterem Versand prüfen.'];
    }
    return $rows;
}

// Enforce confirmed ownership even if a shortcode supplies a single-opt-in override.
add_filter('newsletter_subscription', static function ($subscription) {
    if ($subscription instanceof TNP_Subscription) {
        $subscription->optin = 'double';
        $subscription->if_exists = TNP_Subscription::EXISTING_DOUBLE_OPTIN;
    }
    return $subscription;
}, 20);

// This supported filter runs before the engine inserts tracking URLs/pixels.
// It also covers existing drafts/campaigns; no historical campaign rows are rewritten.
add_filter('newsletter_message_headers', static function (array $headers, $email): array {
    $email->track = 0;
    return $headers;
}, 10, 2);

/** Existing signed links keep working without recording opens, clicks or subscriber identity. */
function iss_newsletter_untracked_url(string $encoded, string $signing_key): string
{
    $decoded = base64_decode($encoded, true);
    if ($decoded === false) { return ''; }
    $parts = explode(';', $decoded);
    if (count($parts) < 5) { return ''; }
    $email_id = (int) array_shift($parts);
    $user_id = (int) array_shift($parts);
    $signature = (string) array_pop($parts);
    $anchor = (string) array_pop($parts);
    $url = implode(';', $parts);
    $scheme = strtolower((string) wp_parse_url($url, PHP_URL_SCHEME));
    if (!in_array($scheme, ['http', 'https'], true) || !wp_parse_url($url, PHP_URL_HOST)
        || !hash_equals(md5($email_id . ';' . $user_id . ';' . $url . ';' . $anchor . $signing_key), $signature)) { return ''; }
    return $url;
}

function iss_newsletter_handle_legacy_tracking(): void
{
    // Public signed newsletter links have no WordPress nonce. No user-controlled URL is trusted unsigned.
    // phpcs:disable WordPress.Security.NonceVerification.Recommended
    if (isset($_GET['nltr'])) {
        $encoded = is_string($_GET['nltr']) ? wp_unslash($_GET['nltr']) : '';
        $url = iss_newsletter_untracked_url($encoded, (string) NewsletterStatistics::instance()->get_main_option('key'));
        nocache_headers();
        if ($url === '') { status_header(400); exit; }
        wp_redirect($url, 302, 'ISS Newsletter'); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- External newsletter destination authenticated using the owner's existing signature.
        exit;
    }
    if (isset($_GET['noti'])) { nocache_headers(); status_header(204); exit; }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended
}

add_action('newsletter_loaded', static function (): void {
    if (!class_exists('NewsletterStatistics')) { return; }
    // Upstream exposes no switch to disable collection on previously sent links.
    // Replace only its tracking entry point, retaining its signed-link format.
    remove_action('wp_loaded', [NewsletterStatistics::instance(), 'hook_wp_loaded']);
    add_action('wp_loaded', 'iss_newsletter_handle_legacy_tracking', 0);
    add_action('wp_ajax_tnptr', 'iss_newsletter_handle_legacy_tracking', 0);
    add_action('wp_ajax_nopriv_tnptr', 'iss_newsletter_handle_legacy_tracking', 0);
});

/** Upstream calls setcookie directly, without a filter. Remove only its two persistent identity cookies. */
function iss_newsletter_private_cookie_headers(array $headers): array
{
    return array_values(array_filter($headers, static function (string $header): bool {
        return !preg_match('/^Set-Cookie:\s*(newsletter|tnpe)=/i', $header);
    }));
}

if (PHP_SAPI !== 'cli') {
    // Tokenized confirmation/profile/unsubscribe links remain the owner's authentication mechanism.
    $iss_newsletter_old_cookies = array_intersect(['newsletter', 'tnpe'], array_keys($_COOKIE));
    unset($_COOKIE['newsletter'], $_COOKIE['tnpe']);
    header_register_callback(static function () use ($iss_newsletter_old_cookies): void {
        $headers = iss_newsletter_private_cookie_headers(headers_list());
        header_remove('Set-Cookie');
        foreach ($headers as $header) {
            if (stripos($header, 'Set-Cookie:') === 0) { header($header, false); }
        }
        foreach ($iss_newsletter_old_cookies as $name) {
            foreach (array_unique(['/', COOKIEPATH]) as $path) {
                setcookie($name, '', ['expires' => time() - YEAR_IN_SECONDS, 'path' => $path, 'domain' => COOKIE_DOMAIN, 'secure' => is_ssl(), 'httponly' => true, 'samesite' => 'Lax']);
            }
        }
    });
}
