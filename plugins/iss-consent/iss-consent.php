<?php
/**
 * Plugin Name: ISS Consent
 * Description: Consent for external media, with native WordPress privacy checks.
 * Version: 0.1.0
 * Author: Industriesalon
 */
if (!defined('ABSPATH')) { exit; }

define('ISS_CONSENT_VERSION', '0.1.0');
// Change this when purposes/providers/consent information materially change, not on every release.
define('ISS_CONSENT_POLICY', '2026-10-23');

function iss_consent_services(): array
{
    return [
        'youtube' => [
            'label' => 'YouTube-Videos',
            'button' => 'YouTube erlauben und Video laden',
            'description' => 'Mit Ihrer Einwilligung laden wir Videos von YouTube (Google Ireland Limited). Dabei erhält Google unter anderem Ihre IP-Adresse und Browserinformationen und kann Cookies oder ähnliche Speichertechniken für Wiedergabe, Nutzungsanalyse und Profilbildung einsetzen. Eine Verarbeitung in den USA ist möglich.',
        ],
        'carto' => [
            'label' => 'Externe Karte von CARTO',
            'button' => 'CARTO erlauben und Karte laden',
            'description' => 'Mit Ihrer Einwilligung laden wir Kartenkacheln von CARTO (CartoDB Inc., USA). Dabei werden Ihre IP-Adresse, Browserinformationen und die Herkunftsseite zur Bereitstellung und Absicherung der Karte übermittelt. CARTO speichert gekürzte Anfrageprotokolle für 30 Tage in den USA. Ortsinformationen bleiben ohne Einwilligung verfügbar.',
        ],
    ];
}

function iss_consent_privacy_url(): string
{
    return home_url('/verein/#datenschutz');
}

add_filter('privacy_policy_url', static function (): string { return iss_consent_privacy_url(); });

/** Theme owns presentation; failure to render controls never enables external content. */
function iss_consent_prompt(string $service, array $preview = []): string
{
    if (!isset(iss_consent_services()[$service])) { return ''; }
    return (string) apply_filters('iss_consent_prompt', '', $service, $preview);
}

function iss_consent_youtube_url(string $url): bool
{
    $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
    return in_array($host, ['youtube.com', 'www.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'], true);
}

/** Server-side blocking covers native Gutenberg embeds and the existing video renderers. */
function iss_consent_block_embeds(string $html): string
{
    if (stripos($html, '<iframe') === false) { return $html; }
    return (string) preg_replace_callback('~<iframe\b[^>]*>.*?</iframe\s*>~is', static function (array $match): string {
        $tag = new WP_HTML_Tag_Processor($match[0]);
        if (!$tag->next_tag('iframe') || $tag->get_attribute('data-iss-consent-frame') !== null) { return $match[0]; }
        $src = (string) ($tag->get_attribute('src') ?: $tag->get_attribute('data-iss-consent-src'));
        if (!iss_consent_youtube_url($src)) { return $match[0]; }
        $tag->remove_attribute('src');
        $tag->remove_attribute('srcdoc');
        $tag->set_attribute('data-iss-consent-src', $src);
        $tag->set_attribute('data-iss-consent-frame', 'youtube');
        $tag->set_attribute('hidden', true);
        return '<div class="iss-consent-embed">' . iss_consent_prompt('youtube') . $tag->get_updated_html() . '</div>';
    }, $html);
}
add_filter('render_block', 'iss_consent_block_embeds', 20);
add_filter('embed_oembed_html', 'iss_consent_block_embeds', 20);
add_filter('the_content', 'iss_consent_block_embeds', 20);

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_script('iss-consent', plugins_url('assets/consent.js', __FILE__), [], (string) filemtime(__DIR__ . '/assets/consent.js'), false);
    wp_add_inline_script('iss-consent', 'window.issConsentConfig = ' . wp_json_encode([
        'version' => ISS_CONSENT_POLICY,
        'maxAge' => 180 * DAY_IN_SECONDS,
        'services' => array_keys(iss_consent_services()),
    ]) . ';', 'before');
});

require_once __DIR__ . '/health.php';
