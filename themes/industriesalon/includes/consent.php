<?php
/** External media controls; policy and decisions belong to ISS Consent. */
if (!defined('ABSPATH')) { exit; }

add_filter('iss_consent_prompt', static function (string $html, string $service, array $preview): string {
    $info = iss_consent_services()[$service] ?? null;
    if (!$info) { return $html; }
    ob_start();
    ?>
    <div class="iss-consent-notice" data-iss-consent-prompt="<?php echo esc_attr($service); ?>">
        <?php if (!empty($preview['url'])) : ?>
            <button type="button" class="iss-consent-poster" data-iss-consent-request="<?php echo esc_attr($service); ?>" aria-label="Video abspielen">
                <img src="<?php echo esc_url($preview['url']); ?>" alt="<?php echo esc_attr($preview['title'] ?? ''); ?>">
                <span aria-hidden="true">▶ Video abspielen</span>
            </button>
        <?php else : ?>
            <div class="wp-block-buttons"><div class="wp-block-button is-style-outline"><button type="button" class="wp-block-button__link" data-iss-consent-request="<?php echo esc_attr($service); ?>"><?php echo esc_html($service === 'youtube' ? 'Video abspielen' : 'Externe Karte laden'); ?></button></div></div>
        <?php endif; ?>
        <noscript><p>Ohne JavaScript werden keine externen Inhalte geladen.</p></noscript>
    </div>
    <?php
    return (string) ob_get_clean();
}, 10, 3);

add_action('wp_footer', static function (): void {
    if (!function_exists('iss_consent_services')) { return; }
    ?>
    <dialog id="iss-consent-dialog" class="iss-consent-dialog" aria-labelledby="iss-consent-title" aria-describedby="iss-consent-intro">
        <h2 id="iss-consent-title">Datenschutzeinstellungen</h2>
        <p id="iss-consent-intro">YouTube-Videos und CARTO-Karten laden erst mit Ihrer Einwilligung. Dabei werden Daten an die Anbieter übermittelt, auch in die USA. YouTube kann Cookies für Nutzungsanalyse und Profilbildung einsetzen. Lokale Vorschaubilder und die Website bleiben ohne Einwilligung nutzbar.</p>
        <form>
            <div class="wp-block-buttons iss-consent-dialog__actions">
                <div class="wp-block-button is-style-outline"><button type="submit" class="wp-block-button__link" data-iss-consent-accept>Alle akzeptieren</button></div>
                <div class="wp-block-button is-style-outline"><button type="submit" class="wp-block-button__link" data-iss-consent-reject>Alle ablehnen</button></div>
            </div>
            <details data-iss-consent-details>
                <summary>Einzeln auswählen</summary>
                <?php foreach (iss_consent_services() as $service => $info) : ?>
                    <div class="iss-consent-dialog__service">
                        <label><input type="checkbox" value="<?php echo esc_attr($service); ?>" data-iss-consent-choice aria-describedby="iss-consent-description-<?php echo esc_attr($service); ?>"> <strong><?php echo esc_html($info['label']); ?></strong></label>
                        <p id="iss-consent-description-<?php echo esc_attr($service); ?>"><?php echo esc_html($info['description']); ?></p>
                    </div>
                <?php endforeach; ?>
                <p>Ihre Auswahl, der Zeitpunkt und die Informationsversion werden für bis zu 180 Tage in diesem Browser gespeichert. Ein Widerruf beendet weiteres Laden; bereits übermittelte Daten können dadurch nicht zurückgeholt werden.</p>
                <div class="wp-block-buttons"><div class="wp-block-button is-style-outline"><button type="submit" class="wp-block-button__link">Auswahl speichern</button></div></div>
            </details>
            <p class="iss-consent-dialog__links"><a href="<?php echo esc_url(iss_consent_privacy_url()); ?>">Datenschutzerklärung</a><button type="button" data-iss-consent-close>Schließen</button></p>
        </form>
    </dialog>
    <?php
}, 7);

add_action('wp_enqueue_scripts', static function (): void {
    if (!function_exists('iss_consent_services')) { return; }
    $path = '/assets/css/consent.css';
    wp_enqueue_style('industriesalon-consent', get_stylesheet_directory_uri() . $path, ['industriesalon-base'], (string) filemtime(get_stylesheet_directory() . $path));
}, 20);
