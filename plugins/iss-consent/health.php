<?php
/** Read-only checks use Site Health and the existing ISS Operations surface. No new scheduler. */
if (!defined('ABSPATH')) { exit; }

function iss_consent_fingerprint(): string
{
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $versions = ['wp' => get_bloginfo('version'), 'theme' => wp_get_theme()->get('Version'), 'policy' => ISS_CONSENT_POLICY];
    $plugins = get_plugins();
    foreach ((array) get_option('active_plugins', []) as $plugin) { $versions[$plugin] = $plugins[$plugin]['Version'] ?? 'missing'; }
    ksort($versions);
    $files = [
        __FILE__, __DIR__ . '/iss-consent.php', __DIR__ . '/assets/consent.js',
        WP_PLUGIN_DIR . '/iss-content/includes/videos.php', WP_PLUGIN_DIR . '/iss-content/includes/video-thumbnails.php', WP_PLUGIN_DIR . '/iss-content/assets/video-library.js',
        WP_PLUGIN_DIR . '/iss-newsletter/iss-newsletter.php', WP_PLUGIN_DIR . '/iss-newsletter/privacy.php',
        WP_PLUGIN_DIR . '/industriesalon-schoeneweide-register/includes/atlas-render.php',
    ];
    foreach (['includes/consent.php', 'assets/css/consent.css', 'assets/js/atlas/map.js', 'assets/js/atlas/provider.js', 'templates/page-verein.html', 'parts/footer.html'] as $file) {
        $files[] = get_stylesheet_directory() . '/' . $file;
    }
    foreach ($files as $file) { $versions[$file] = is_readable($file) ? hash_file('sha256', $file) : 'missing'; }
    $versions['updated'] = get_option('iss_consent_updated_at', '');
    return hash('sha256', (string) wp_json_encode($versions));
}

function iss_consent_checks(): array
{
    $rows = [];
    $add = static function (string $name, bool $ok, string $details) use (&$rows): void {
        $rows[] = ['group' => 'privacy', 'name' => $name, 'status' => $ok ? 'ok' : 'warning', 'details' => $details];
    };
    $add('Einwilligungsoberfläche', has_filter('iss_consent_prompt') !== false, 'Theme stellt Hinweise und Einstellungen bereit; Browserprüfung kontrolliert die tatsächlichen Anfragen.');
    $provider = defined('INDUSTRIESALON_MAP_BASEMAP_PROVIDER') ? INDUSTRIESALON_MAP_BASEMAP_PROVIDER : 'carto';
    $add('Kartenanbieter', $provider === 'carto', 'Nur CARTO ist freigegeben. Andere Anbieter bleiben gesperrt, bis Information und Integration geprüft sind.');
    $add('CARTO-Konfiguration', defined('INDUSTRIESALON_CARTO_KEY') && trim((string) INDUSTRIESALON_CARTO_KEY) !== '', 'Für externe Karten ist ein gültiger Schlüssel in der privaten Laufzeitkonfiguration erforderlich. Die Atlas-Karte nutzt CARTO; Anbieter-Verfügbarkeit separat im Browser prüfen.');
    foreach (['page-verein' => 'wp_template', 'footer' => 'wp_template_part'] as $slug => $type) {
        $template = get_block_template(get_stylesheet() . '//' . $slug, $type);
        $add('Vorlage ' . $slug, $template && $template->source === 'theme', 'Abweichende Datenbankvorlagen müssen auf Datenschutzerklärung und Einstellungslink geprüft werden.');
    }
    $missing_previews = 0;
    foreach (get_posts(['post_type' => 'video', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids']) as $video_id) {
        $image = get_post_thumbnail_id($video_id);
        $file = $image ? get_attached_file($image) : false;
        if (!$file || !is_readable($file)) { ++$missing_previews; }
    }
    $add('Lokale Videovorschaubilder', $missing_previews === 0, $missing_previews . ' veröffentlichte Videos ohne lokale Bilddatei. Beitragsbild im Editor wählen oder den dokumentierten Thumbnail-Import ausführen.');
    $newsletter = class_exists('NewsletterSubscription') && function_exists('iss_newsletter_privacy_checks');
    $add('Newsletter-Integration', $newsletter, 'ISS Newsletter und The Newsletter Plugin müssen gemeinsam aktiv sein.');
    if ($newsletter) { $rows = array_merge($rows, iss_newsletter_privacy_checks()); }
    $receipt = get_option('iss_consent_browser_check', []);
    $current = ($receipt['fingerprint'] ?? '') === iss_consent_fingerprint();
    $recent = (int) ($receipt['checked_at'] ?? 0) >= time() - 30 * DAY_IN_SECONDS;
    $add('Browserprüfung', $current && $recent, ($current && $recent ? 'Bestanden: ' : 'Erneut prüfen: ') . 'vor Einwilligung, nach Auswahl und Widerruf; spätestens alle 30 Tage und nach Updates. Letzter Nachweis: ' . (!empty($receipt['checked_at']) ? gmdate('Y-m-d H:i', (int) $receipt['checked_at']) . ' UTC' : 'keiner'));
    return $rows;
}

add_action('upgrader_process_complete', static function (): void {
    update_option('iss_consent_updated_at', microtime(true), false);
}, 10, 0);

add_filter('iss_core_status_rows', static function (array $rows): array { return array_merge($rows, iss_consent_checks()); });

add_filter('site_status_tests', static function (array $tests): array {
    $tests['direct']['iss_consent'] = ['label' => 'Datenschutz und externe Inhalte', 'test' => static function (): array {
        $rows = iss_consent_checks();
        $pending = array_filter($rows, static function (array $row): bool { return $row['status'] !== 'ok'; });
        $description = '<ul>';
        foreach ($rows as $row) { $description .= '<li>' . esc_html($row['name'] . ': ' . $row['details']) . '</li>'; }
        return [
            'label' => $pending ? 'Datenschutzprüfung benötigt Aufmerksamkeit' : 'Technische Datenschutzprüfungen sind aktuell',
            'status' => $pending ? 'recommended' : 'good', 'badge' => ['label' => 'Datenschutz', 'color' => 'blue'],
            'description' => $description . '</ul><p>Technische Prüfung, keine rechtliche Zertifizierung. Anbieterangaben und Verarbeitungspraxis gehören zusätzlich zur monatlichen Wartung.</p>',
            'actions' => '<p><a href="' . esc_url(admin_url('admin.php?page=iss-operations')) . '">ISS Operations</a></p>', 'test' => 'iss_consent',
        ];
    }];
    return $tests;
});

add_action('iss_core_operations_page', static function (): void {
    echo '<h2>Datenschutz und externe Inhalte</h2><p>Nach Updates und spätestens alle 30 Tage Browserprüfung ausführen. Verfahren: <code>docs/runbooks/staging-maintenance.md</code>. Einstellungen werden hier geprüft, nicht automatisch umgeschrieben.</p><table class="widefat striped"><thead><tr><th>Prüfung</th><th>Status</th><th>Hinweis</th></tr></thead><tbody>';
    foreach (iss_consent_checks() as $row) {
        echo '<tr><td>' . esc_html($row['name']) . '</td><td>' . esc_html($row['status']) . '</td><td>' . esc_html($row['details']) . '</td></tr>';
    }
    echo '</tbody></table>';
});

/** Only a complete, matching browser report may advance the verification receipt. */
function iss_consent_record_browser(array $report): void
{
    $metadata = $report['config']['metadata'] ?? [];
    $stats = $report['stats'] ?? [];
    $started = strtotime((string) ($stats['startTime'] ?? ''));
    if (($metadata['issConsentFingerprint'] ?? '') !== iss_consent_fingerprint()
        || untrailingslashit((string) ($metadata['baseURL'] ?? '')) !== untrailingslashit(home_url())
        || !$started || $started > time() || $started < time() - HOUR_IN_SECONDS
        || !empty($stats['unexpected']) || !empty($stats['skipped']) || !empty($stats['flaky']) || !empty($report['errors'])) {
        throw new RuntimeException('Browser report is stale, failed, skipped or belongs to another runtime.');
    }
    $titles = [];
    $walk = static function (array $suites) use (&$walk, &$titles): void {
        foreach ($suites as $suite) {
            foreach ($suite['specs'] ?? [] as $spec) {
                if (empty($spec['ok'])) { throw new RuntimeException('Browser report contains an unsuccessful test.'); }
                $titles[] = $spec['title'];
            }
            $walk($suite['suites'] ?? []);
        }
    };
    $walk($report['suites'] ?? []);
    $required = ['initial blocking and granular withdrawal', 'atlas CARTO basemap and consent', 'stored consent expiry and cross-tab withdrawal', 'keyboard mobile and no JavaScript', 'video library and newsletter links'];
    if (array_diff($required, $titles) || count($titles) !== count($required) || (int) ($stats['expected'] ?? 0) !== count($required)) {
        throw new RuntimeException('Required privacy browser scenarios are missing.');
    }
    $receipt = ['fingerprint' => iss_consent_fingerprint(), 'checked_at' => time(), 'scenarios' => $titles];
    update_option('iss_consent_browser_check', $receipt, false);
    if (get_option('iss_consent_browser_check') !== $receipt) { throw new RuntimeException('Could not record browser verification.'); }
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss consent-check', static function (): void {
        $rows = iss_consent_checks();
        WP_CLI::log((string) wp_json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        if (in_array('warning', array_column($rows, 'status'), true)) { WP_CLI::error('Privacy checks need attention.'); }
        WP_CLI::success('Technical privacy checks current.');
    });
    WP_CLI::add_command('iss consent-fingerprint', static function (): void { WP_CLI::log(iss_consent_fingerprint()); });
    WP_CLI::add_command('iss consent-record-browser', static function (array $args): void {
        try {
            $report = isset($args[0]) && is_readable($args[0]) ? json_decode((string) file_get_contents($args[0]), true) : null;
            if (!is_array($report)) { throw new RuntimeException('Provide the private Playwright JSON report path.'); }
            iss_consent_record_browser($report);
        } catch (Throwable $error) { WP_CLI::error($error->getMessage()); }
        WP_CLI::success('Browser verification recorded for this runtime.');
    });
}
