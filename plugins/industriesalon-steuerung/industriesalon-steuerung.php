<?php
/**
 * Plugin Name: Industriesalon Steuerung
 * Description: Zentrale Seiteneinstellungen für Industriesalon: Öffnungszeiten, Bürozeiten, Kontakt, Adresse, Mission Statement, Barrierefreiheit, FAQ und Preise mit wiederverwendbarer Ausgabe per PHP und Gutenberg-Blöcke.
 * Version: 0.3.0
 * Author: OpenAI
 * Text Domain: industriesalon-steuerung
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Industriesalon_Steuerung {
    private const VERSION = '0.3.0';
    private const CAPABILITY = 'manage_iss_controls';

    private const OPTION_GENERAL = 'iss_control_general';
    private const OPTION_CONTACT = 'iss_control_contact';
    private const OPTION_MAPS = 'iss_control_maps';
    private const OPTION_HOURS = 'iss_control_hours';
    private const OPTION_ACCESSIBILITY = 'iss_control_accessibility';
    private const OPTION_PRICES = 'iss_control_prices';
    private const OPTION_FAQ = 'iss_control_faq';
    private const OPTION_MISSION_STATEMENT = 'iss_control_mission_statement';
    private const OPTION_VISIT_CACHE_VERSION = 'iss_control_visit_cache_version';

    private static ?Industriesalon_Steuerung $instance = null;

    public static function instance(): Industriesalon_Steuerung {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function activate(): void {
        foreach (['administrator', 'editor'] as $role_name) {
            $role = get_role($role_name);
            if ($role instanceof WP_Role) {
                $role->add_cap(self::CAPABILITY);
            }
        }
    }

    public static function deactivate(): void {
        foreach (['administrator', 'editor'] as $role_name) {
            $role = get_role($role_name);
            if ($role instanceof WP_Role) {
                $role->remove_cap(self::CAPABILITY);
            }
        }
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_post_iss_control_export', [$this, 'handle_export']);
        add_action('admin_post_iss_control_import', [$this, 'handle_import']);

        add_action('init', [$this, 'register_blocks']);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('industriesalon-steuerung', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function capability(): string {
        return (string) apply_filters('iss_control_capability', self::CAPABILITY);
    }

    public function get_option_keys(): array {
        return [
            'general'       => self::OPTION_GENERAL,
            'contact'       => self::OPTION_CONTACT,
            'maps'          => self::OPTION_MAPS,
            'hours'         => self::OPTION_HOURS,
            'accessibility' => self::OPTION_ACCESSIBILITY,
            'prices'        => self::OPTION_PRICES,
            'faq'           => self::OPTION_FAQ,
            'mission_statement' => self::OPTION_MISSION_STATEMENT,
        ];
    }

    public function register_admin_menu(): void {
        $hook = add_menu_page(
            __('Industriesalon Steuerung', 'industriesalon-steuerung'),
            __('Industriesalon Steuerung', 'industriesalon-steuerung'),
            $this->capability(),
            'industriesalon-steuerung',
            [$this, 'render_admin_page'],
            'dashicons-admin-generic',
            58
        );

        if (is_string($hook) && $hook !== '') {
            add_action('load-' . $hook, [$this, 'register_help_tabs']);
        }
    }

    public function register_help_tabs(): void {
        $screen = get_current_screen();
        if (! ($screen instanceof WP_Screen)) {
            return;
        }

        if ($screen->id !== 'toplevel_page_industriesalon-steuerung') {
            return;
        }

        $screen->add_help_tab([
            'id'      => 'iss-help-quick',
            'title'   => __('Kurzhilfe', 'industriesalon-steuerung'),
            'content' =>
                '<p><strong>' . esc_html__('Alles hier wirkt websiteweit.', 'industriesalon-steuerung') . '</strong></p>' .
                '<p>' . esc_html__('Änderungen nach dem Speichern werden automatisch in allen verbundenen Bereichen angezeigt.', 'industriesalon-steuerung') . '</p>' .
                '<ul>' .
                '<li>' . esc_html__('Nach jeder Bearbeitung auf „Änderung speichern“ klicken.', 'industriesalon-steuerung') . '</li>' .
                '<li>' . esc_html__('Vor größeren Änderungen zuerst JSON exportieren.', 'industriesalon-steuerung') . '</li>' .
                '<li>' . esc_html__('Bei Öffnungszeiten Ausnahmen mit Datum prüfen.', 'industriesalon-steuerung') . '</li>' .
                '</ul>',
        ]);

        $screen->add_help_tab([
            'id'      => 'iss-help-blocks',
            'title'   => __('Blöcke & PHP', 'industriesalon-steuerung'),
            'content' =>
                '<p>' . esc_html__('Beispiele für technische Einbindung in Seiten/Theme:', 'industriesalon-steuerung') . '</p>' .
                '<p><code>&lt;!-- wp:industriesalon/hours {"type":"public","variant":"full"} /--&gt;</code></p>' .
                '<p><code>&lt;!-- wp:industriesalon/visit-info {"variant":"info-panel","accent":"green","surface":"light"} /--&gt;</code></p>' .
                '<p><code>&lt;?php echo Industriesalon_Steuerung::instance()->render_contact(); ?&gt;</code></p>',
        ]);

        $screen->set_help_sidebar(
            '<p><strong>' . esc_html__('Hinweis', 'industriesalon-steuerung') . '</strong></p>' .
            '<p>' . esc_html__('Für Redaktion: nur Inhalte pflegen und speichern. Für Entwickler: Blöcke oder direkte Render-Methoden nutzen.', 'industriesalon-steuerung') . '</p>'
        );
    }

    public function enqueue_admin_assets(string $hook): void {
        if ($hook !== 'toplevel_page_industriesalon-steuerung') {
            return;
        }

        wp_enqueue_style(
            'iss-control-admin',
            plugins_url('assets/admin.css', __FILE__),
            [],
            self::VERSION
        );

        wp_enqueue_script(
            'iss-control-admin',
            plugins_url('assets/admin.js', __FILE__),
            [],
            self::VERSION,
            true
        );
    }

    public function register_settings(): void {
        register_setting('iss_control_group', self::OPTION_GENERAL, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_general'],
            'default'           => $this->default_general(),
        ]);

        register_setting('iss_control_group', self::OPTION_CONTACT, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_contact'],
            'default'           => $this->default_contact(),
        ]);

        register_setting('iss_control_group', self::OPTION_MAPS, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_maps'],
            'default'           => $this->default_maps(),
        ]);

        register_setting('iss_control_group', self::OPTION_HOURS, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_hours'],
            'default'           => $this->default_hours(),
        ]);

        register_setting('iss_control_group', self::OPTION_ACCESSIBILITY, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_accessibility'],
            'default'           => $this->default_accessibility(),
        ]);

        register_setting('iss_control_group', self::OPTION_PRICES, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_prices'],
            'default'           => $this->default_prices(),
        ]);

        register_setting('iss_control_group', self::OPTION_FAQ, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_faq'],
            'default'           => $this->default_faq(),
        ]);

        register_setting('iss_control_group', self::OPTION_MISSION_STATEMENT, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_mission_statement'],
            'default'           => $this->default_mission_statement(),
        ]);
    }

    public function render_admin_page(): void {
        if (! current_user_can($this->capability())) {
            return;
        }

        $general = get_option(self::OPTION_GENERAL, $this->default_general());
        $contact = get_option(self::OPTION_CONTACT, $this->default_contact());
        $maps = get_option(self::OPTION_MAPS, $this->default_maps());
        $hours = get_option(self::OPTION_HOURS, $this->default_hours());
        $accessibility = get_option(self::OPTION_ACCESSIBILITY, $this->default_accessibility());
        $prices = get_option(self::OPTION_PRICES, $this->default_prices());
        $faq = get_option(self::OPTION_FAQ, $this->default_faq());
        $mission_statement = get_option(self::OPTION_MISSION_STATEMENT, $this->default_mission_statement());
        $days = $this->days();
        ?>
        <div class="wrap iss-admin">
            <h1><?php esc_html_e('Industriesalon Steuerung', 'industriesalon-steuerung'); ?></h1>
            <p class="description"><?php esc_html_e('Zentrale Angaben für wiederverwendbare Inhalte im Frontend.', 'industriesalon-steuerung'); ?></p>

            <?php $this->render_admin_notice(); ?>

            <nav class="nav-tab-wrapper iss-tabs" aria-label="<?php esc_attr_e('Bereiche', 'industriesalon-steuerung'); ?>">
                <a href="#iss-general" class="nav-tab nav-tab-active"><?php esc_html_e('Standort & Adresse', 'industriesalon-steuerung'); ?></a>
                <a href="#iss-contact" class="nav-tab"><?php esc_html_e('Kontakt', 'industriesalon-steuerung'); ?></a>
                <a href="#iss-maps" class="nav-tab"><?php esc_html_e('Google Maps / Anfahrt', 'industriesalon-steuerung'); ?></a>
                <a href="#iss-hours" class="nav-tab"><?php esc_html_e('Besuchszeiten', 'industriesalon-steuerung'); ?></a>
                <a href="#iss-prices" class="nav-tab"><?php esc_html_e('Preise', 'industriesalon-steuerung'); ?></a>
                <a href="#iss-accessibility" class="nav-tab"><?php esc_html_e('Barrierefreiheit', 'industriesalon-steuerung'); ?></a>
                <a href="#iss-faq" class="nav-tab"><?php esc_html_e('Häufige Fragen', 'industriesalon-steuerung'); ?></a>
                <a href="#iss-mission-statement" class="nav-tab"><?php esc_html_e('Mission Statement', 'industriesalon-steuerung'); ?></a>
                <a href="#iss-tools" class="nav-tab"><?php esc_html_e('Werkzeuge', 'industriesalon-steuerung'); ?></a>
            </nav>

            <section class="iss-help-panel" aria-label="<?php esc_attr_e('Schnellhilfe', 'industriesalon-steuerung'); ?>">
                <h2><?php esc_html_e('Schnellhilfe', 'industriesalon-steuerung'); ?></h2>
                <ul>
                    <li><?php esc_html_e('Alles, was Sie hier speichern, erscheint automatisch auf der gesamten Website.', 'industriesalon-steuerung'); ?></li>
                    <li><?php esc_html_e('Nutzen Sie den Button „Änderung speichern“ oben oder unten im Formular.', 'industriesalon-steuerung'); ?></li>
                    <li><?php esc_html_e('Vor größeren Änderungen bitte zuerst über „Werkzeuge“ eine JSON-Sicherung erstellen.', 'industriesalon-steuerung'); ?></li>
                </ul>
                <p class="description"><?php esc_html_e('Mehr Hilfe finden Sie oben rechts unter „Hilfe einblenden“.', 'industriesalon-steuerung'); ?></p>
            </section>

            <form method="post" action="options.php">
                <?php settings_fields('iss_control_group'); ?>
                <p><?php submit_button(__('Änderung speichern', 'industriesalon-steuerung'), 'primary', 'submit', false); ?></p>

                <section id="iss-general" class="iss-panel">
                    <h2><?php esc_html_e('Standort & Adresse', 'industriesalon-steuerung'); ?></h2>
                    <p class="iss-panel__hint"><?php esc_html_e('Grundangaben zum Ort und zur Anfahrt.', 'industriesalon-steuerung'); ?></p>
                    <div class="iss-grid iss-grid--2">
                        <?php $this->text_field(self::OPTION_GENERAL, 'site_name', __('Name des Ortes', 'industriesalon-steuerung'), $general['site_name'] ?? '', true, 'Industriesalon Schöneweide', __('So erscheint der Name in Kontakt- und Infobereichen.', 'industriesalon-steuerung')); ?>
                        <?php $this->text_field(self::OPTION_GENERAL, 'tagline', __('Kurzbeschreibung', 'industriesalon-steuerung'), $general['tagline'] ?? '', true, 'Museum und Besucherzentrum zur Industriegeschichte in Berlin-Schöneweide.', __('Kurzer Satz für Hinweise oder Infobereiche.', 'industriesalon-steuerung')); ?>
                        <?php $this->text_field(self::OPTION_GENERAL, 'street', __('Straße und Hausnummer', 'industriesalon-steuerung'), $general['street'] ?? '', true, 'Reinbeckstraße 9', __('Bitte so eingeben, wie es öffentlich erscheinen soll.', 'industriesalon-steuerung')); ?>
                        <?php $this->text_field(self::OPTION_GENERAL, 'postal_code', __('PLZ', 'industriesalon-steuerung'), $general['postal_code'] ?? '', true, '12459'); ?>
                        <?php $this->text_field(self::OPTION_GENERAL, 'city', __('Ort', 'industriesalon-steuerung'), $general['city'] ?? '', true, 'Berlin'); ?>
                        <?php $this->text_field(self::OPTION_GENERAL, 'district', __('Stadtteil', 'industriesalon-steuerung'), $general['district'] ?? '', true, 'Schöneweide', __('Optional.', 'industriesalon-steuerung')); ?>
                    </div>
                    <div class="iss-grid iss-grid--1">
                        <?php $this->textarea_field(self::OPTION_GENERAL, 'arrival', __('Anfahrt', 'industriesalon-steuerung'), $general['arrival'] ?? '', 4, true, 'Erreichbar mit S-Bahn, Tram oder Bus …', __('Kurzer Hinweis zur Anreise.', 'industriesalon-steuerung')); ?>
                        <?php $this->textarea_field(self::OPTION_GENERAL, 'visit_note', __('Besuchshinweis', 'industriesalon-steuerung'), $general['visit_note'] ?? '', 4, true, 'Der Eingang befindet sich …', __('Optional. Für wichtige Hinweise vor dem Besuch.', 'industriesalon-steuerung')); ?>
                    </div>
                </section>

                <section id="iss-contact" class="iss-panel" hidden>
                    <h2><?php esc_html_e('Kontakt', 'industriesalon-steuerung'); ?></h2>
                    <p class="iss-panel__hint"><?php esc_html_e('Kontaktangaben für Besucherinnen und Besucher.', 'industriesalon-steuerung'); ?></p>
                    <div class="iss-grid iss-grid--2">
                        <?php $this->text_field(self::OPTION_CONTACT, 'phone', __('Telefon', 'industriesalon-steuerung'), $contact['phone'] ?? '', true, '030 12345678', __('Kann mit Leerzeichen eingegeben werden.', 'industriesalon-steuerung')); ?>
                        <?php $this->email_field(self::OPTION_CONTACT, 'email', __('E-Mail', 'industriesalon-steuerung'), $contact['email'] ?? '', 'info@industriesalon.de'); ?>
                        <?php $this->email_field(self::OPTION_CONTACT, 'booking_email', __('E-Mail für Buchungen', 'industriesalon-steuerung'), $contact['booking_email'] ?? '', 'buchung@industriesalon.de', __('Optional. Nur wenn Buchungen über eine eigene Adresse laufen.', 'industriesalon-steuerung')); ?>
                        <?php $this->text_field(self::OPTION_CONTACT, 'contact_person', __('Ansprechperson', 'industriesalon-steuerung'), $contact['contact_person'] ?? '', true, 'Max Mustermann', __('Optional.', 'industriesalon-steuerung')); ?>
                        <?php $this->url_field(self::OPTION_CONTACT, 'website', __('Website', 'industriesalon-steuerung'), $contact['website'] ?? '', 'https://www.industriesalon.de'); ?>
                        <?php $this->url_field(self::OPTION_CONTACT, 'booking_url', __('Buchungslink', 'industriesalon-steuerung'), $contact['booking_url'] ?? '', 'https://…', __('Zum Beispiel SuperSaaS, Formular oder externe Buchungsseite.', 'industriesalon-steuerung')); ?>
                        <?php $this->url_field(self::OPTION_CONTACT, 'instagram', __('Instagram', 'industriesalon-steuerung'), $contact['instagram'] ?? '', 'https://instagram.com/...', __('Optional.', 'industriesalon-steuerung')); ?>
                        <?php $this->url_field(self::OPTION_CONTACT, 'facebook', __('Facebook', 'industriesalon-steuerung'), $contact['facebook'] ?? '', 'https://facebook.com/...', __('Optional.', 'industriesalon-steuerung')); ?>
                        <?php $this->url_field(self::OPTION_CONTACT, 'youtube', __('YouTube', 'industriesalon-steuerung'), $contact['youtube'] ?? '', 'https://youtube.com/...', __('Optional.', 'industriesalon-steuerung')); ?>
                    </div>
                </section>

                <section id="iss-maps" class="iss-panel" hidden>
                    <h2><?php esc_html_e('Google Maps / Anfahrt', 'industriesalon-steuerung'); ?></h2>
                    <p class="iss-panel__hint"><?php esc_html_e('Links zur Karte und optional zur Einbettung.', 'industriesalon-steuerung'); ?></p>
                    <div class="iss-grid iss-grid--1">
                        <?php $this->url_field(self::OPTION_MAPS, 'google_maps_url', __('Google Maps URL', 'industriesalon-steuerung'), $maps['google_maps_url'] ?? '', 'https://maps.google.com/...'); ?>
                        <?php $this->textarea_field(self::OPTION_MAPS, 'google_maps_embed', __('Embed URL oder iFrame-Code', 'industriesalon-steuerung'), $maps['google_maps_embed'] ?? '', 5, true, '', __('Optional.', 'industriesalon-steuerung')); ?>
                    </div>
                </section>

                <section id="iss-hours" class="iss-panel" hidden>
                    <h2><?php esc_html_e('Besuchszeiten', 'industriesalon-steuerung'); ?></h2>
                    <p class="iss-panel__hint"><?php esc_html_e('Hier werden Besuchszeiten, Bürozeiten und Sondertage gepflegt.', 'industriesalon-steuerung'); ?></p>

                    <div class="iss-hours-group">
                        <h3><?php esc_html_e('Besuchszeiten', 'industriesalon-steuerung'); ?></h3>
                        <?php $this->hours_table('public', $hours['public'] ?? [], $days); ?>
                    </div>

                    <div class="iss-hours-group">
                        <h3><?php esc_html_e('Bürozeiten', 'industriesalon-steuerung'); ?></h3>
                        <?php $this->hours_table('office', $hours['office'] ?? [], $days); ?>
                    </div>

                    <div class="iss-hours-group">
                        <h3><?php esc_html_e('Sondertage', 'industriesalon-steuerung'); ?></h3>
                        <p class="iss-panel__hint"><?php esc_html_e('Diese Einträge gelten nur für das gewählte Datum.', 'industriesalon-steuerung'); ?></p>
                        <div class="iss-special-list" data-iss-special-list>
                            <?php
                            $special = isset($hours['exceptions']) && is_array($hours['exceptions']) ? $hours['exceptions'] : [];
                            if (empty($special)) {
                                $special = [[
                                    'date' => '',
                                    'type' => 'public',
                                    'kind' => 'special_open',
                                    'closed' => 0,
                                    'open' => '',
                                    'close' => '',
                                    'label' => '',
                                    'note' => '',
                                ]];
                            }
                            foreach ($special as $index => $item) :
                                $this->special_date_fields((int) $index, $item);
                            endforeach;
                            ?>
                        </div>
                        <template id="iss-special-template">
                            <?php
                            $this->special_date_fields('__INDEX__', [
                                'date' => '',
                                'type' => 'public',
                                'kind' => 'special_open',
                                'closed' => 0,
                                'open' => '',
                                'close' => '',
                                'label' => '',
                                'note' => '',
                            ]);
                            ?>
                        </template>
                        <p><button type="button" class="button" data-iss-add-special><?php esc_html_e('Sondertag hinzufügen', 'industriesalon-steuerung'); ?></button></p>
                    </div>
                </section>

                <section id="iss-accessibility" class="iss-panel" hidden>
                    <h2><?php esc_html_e('Barrierefreiheit', 'industriesalon-steuerung'); ?></h2>
                    <p class="iss-panel__hint"><?php esc_html_e('Kurze, klare Angaben zur Zugänglichkeit.', 'industriesalon-steuerung'); ?></p>
                    <div class="iss-grid iss-grid--2">
                        <?php $this->checkbox_field(self::OPTION_ACCESSIBILITY, 'wheelchair', __('Stufenloser Zugang', 'industriesalon-steuerung'), ! empty($accessibility['wheelchair'])); ?>
                        <?php $this->checkbox_field(self::OPTION_ACCESSIBILITY, 'accessible_toilet', __('Barrierefreies WC', 'industriesalon-steuerung'), ! empty($accessibility['accessible_toilet'])); ?>
                        <?php $this->checkbox_field(self::OPTION_ACCESSIBILITY, 'elevator', __('Aufzug vorhanden', 'industriesalon-steuerung'), ! empty($accessibility['elevator'])); ?>
                        <?php $this->checkbox_field(self::OPTION_ACCESSIBILITY, 'parking', __('Parken in der Nähe', 'industriesalon-steuerung'), ! empty($accessibility['parking'])); ?>
                        <?php $this->checkbox_field(self::OPTION_ACCESSIBILITY, 'companion', __('Begleitperson möglich', 'industriesalon-steuerung'), ! empty($accessibility['companion'])); ?>
                    </div>
                    <div class="iss-grid iss-grid--1">
                        <?php $this->textarea_field(self::OPTION_ACCESSIBILITY, 'note', __('Zusätzlicher Hinweis', 'industriesalon-steuerung'), $accessibility['note'] ?? '', 5, true, 'Ein Teil der Ausstellung ist nur eingeschränkt zugänglich.', __('Nur das eintragen, was Besucher wirklich wissen müssen.', 'industriesalon-steuerung')); ?>
                    </div>
                </section>

                <section id="iss-prices" class="iss-panel" hidden>
                    <h2><?php esc_html_e('Preise', 'industriesalon-steuerung'); ?></h2>
                    <p class="iss-panel__hint"><?php esc_html_e('Preise und Hinweise für Besucher.', 'industriesalon-steuerung'); ?></p>
                    <div class="iss-grid iss-grid--2">
                        <?php $this->text_field(self::OPTION_PRICES, 'adult', __('Regulär', 'industriesalon-steuerung'), $prices['adult'] ?? '', true, '5 €'); ?>
                        <?php $this->text_field(self::OPTION_PRICES, 'reduced', __('Ermäßigt', 'industriesalon-steuerung'), $prices['reduced'] ?? ''); ?>
                        <?php $this->text_field(self::OPTION_PRICES, 'group', __('Gruppen', 'industriesalon-steuerung'), $prices['group'] ?? '', true, 'auf Anfrage', __('Optional.', 'industriesalon-steuerung')); ?>
                        <?php $this->text_field(self::OPTION_PRICES, 'school', __('Schulklassen', 'industriesalon-steuerung'), $prices['school'] ?? '', true, 'nach Vereinbarung', __('Optional.', 'industriesalon-steuerung')); ?>
                        <?php $this->text_field(self::OPTION_PRICES, 'tour', __('Hinweis zu Führungen oder Buchungen', 'industriesalon-steuerung'), $prices['tour'] ?? '', true, 'Führungen nur nach Voranmeldung.'); ?>
                        <?php $this->text_field(self::OPTION_PRICES, 'rental', __('Freier Eintritt / Hinweise', 'industriesalon-steuerung'), $prices['rental'] ?? '', true, 'Kinder bis … frei', __('Optional.', 'industriesalon-steuerung')); ?>
                    </div>
                    <div class="iss-grid iss-grid--1">
                        <?php $this->textarea_field(self::OPTION_PRICES, 'note', __('Hinweis', 'industriesalon-steuerung'), $prices['note'] ?? '', 4); ?>
                    </div>
                </section>

                <section id="iss-faq" class="iss-panel" hidden>
                    <h2><?php esc_html_e('Häufige Fragen', 'industriesalon-steuerung'); ?></h2>
                    <p class="iss-panel__hint"><?php esc_html_e('Kurze Fragen und kurze Antworten.', 'industriesalon-steuerung'); ?></p>

                    <div class="iss-faq-list" data-iss-faq-list>
                        <?php
                        $items = $faq['items'] ?? [];
                        if (empty($items)) {
                            $items = [['question' => '', 'answer' => '']];
                        }
                        foreach ($items as $index => $item) :
                            $this->faq_item_fields((int) $index, $item);
                        endforeach;
                        ?>
                    </div>

                    <template id="iss-faq-template">
                        <?php $this->faq_item_fields('__INDEX__', ['question' => '', 'answer' => '']); ?>
                    </template>

                    <p>
                        <button type="button" class="button" data-iss-add-faq><?php esc_html_e('FAQ-Eintrag hinzufügen', 'industriesalon-steuerung'); ?></button>
                    </p>
                </section>

                <section id="iss-mission-statement" class="iss-panel" hidden>
                    <h2><?php esc_html_e('Mission Statement', 'industriesalon-steuerung'); ?></h2>
                    <p class="iss-panel__hint"><?php esc_html_e('Kurztext für die redaktionelle Selbstbeschreibung auf Seiten und in Inhaltsblöcken.', 'industriesalon-steuerung'); ?></p>
                    <div class="iss-grid iss-grid--1">
                        <?php $this->textarea_field(self::OPTION_MISSION_STATEMENT, 'content', __('Mission Statement', 'industriesalon-steuerung'), $mission_statement['content'] ?? '', 8, true, '', __('Kurz und prägnant formulieren.', 'industriesalon-steuerung')); ?>
                    </div>
                </section>

                <?php submit_button(__('Änderung speichern', 'industriesalon-steuerung')); ?>
            </form>

                <section id="iss-tools" class="iss-panel" hidden>
                <h2><?php esc_html_e('Werkzeuge', 'industriesalon-steuerung'); ?></h2>
                <p class="iss-panel__hint"><?php esc_html_e('Für Sicherung und Wiederherstellung der Daten.', 'industriesalon-steuerung'); ?></p>

                <div class="iss-tools-grid">
                    <div class="iss-tool-card">
                        <h3><?php esc_html_e('Daten sichern', 'industriesalon-steuerung'); ?></h3>
                        <p><?php esc_html_e('Lädt eine Sicherungsdatei herunter.', 'industriesalon-steuerung'); ?></p>
                        <p>
                            <a class="button button-secondary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=iss_control_export'), 'iss_control_export')); ?>">
                                <?php esc_html_e('JSON exportieren', 'industriesalon-steuerung'); ?>
                            </a>
                        </p>
                    </div>

                    <div class="iss-tool-card">
                        <h3><?php esc_html_e('Daten wiederherstellen', 'industriesalon-steuerung'); ?></h3>
                        <p><?php esc_html_e('Spielt eine zuvor gespeicherte Sicherungsdatei ein. Bestehende Werte werden überschrieben.', 'industriesalon-steuerung'); ?></p>
                        <p class="iss-panel__hint"><?php esc_html_e('Nur benutzen, wenn Sie eine Sicherungsdatei haben. Nicht für den täglichen Gebrauch.', 'industriesalon-steuerung'); ?></p>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                            <?php wp_nonce_field('iss_control_import'); ?>
                            <input type="hidden" name="action" value="iss_control_import">
                            <input type="file" name="iss_import_file" accept="application/json,.json" required>
                            <?php submit_button(__('JSON importieren', 'industriesalon-steuerung'), 'secondary', 'submit', false); ?>
                        </form>
                    </div>
                </div>
            </section>
        </div>
        <?php
    }

    private function render_admin_notice(): void {
        if (! isset($_GET['iss_notice'])) {
            return;
        }

        $notice = sanitize_key((string) wp_unslash($_GET['iss_notice']));
        $messages = [
            'import_success' => ['updated', __('Import erfolgreich.', 'industriesalon-steuerung')],
            'import_error'   => ['error', __('Import fehlgeschlagen. Datei prüfen und erneut versuchen.', 'industriesalon-steuerung')],
        ];

        if (! isset($messages[$notice])) {
            return;
        }

        [$class, $message] = $messages[$notice];

        printf(
            '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
            esc_attr($class),
            esc_html($message)
        );
    }

    public function handle_export(): void {
        if (! current_user_can($this->capability())) {
            wp_die(esc_html__('Keine Berechtigung.', 'industriesalon-steuerung'));
        }

        check_admin_referer('iss_control_export');

        $payload = [
            'plugin'       => 'Industriesalon Steuerung',
            'version'      => self::VERSION,
            'generated_at' => current_time('mysql'),
            'site_url'     => home_url('/'),
            'options'      => $this->get_all_data(),
        ];

        nocache_headers();
        header('Content-Type: application/json; charset=' . get_option('blog_charset'));
        header('Content-Disposition: attachment; filename="industriesalon-steuerung-export-' . gmdate('Y-m-d-His') . '.json"');

        echo wp_json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function handle_import(): void {
        if (! current_user_can($this->capability())) {
            wp_die(esc_html__('Keine Berechtigung.', 'industriesalon-steuerung'));
        }

        check_admin_referer('iss_control_import');

        if (
            empty($_FILES['iss_import_file']['tmp_name']) ||
            ! is_uploaded_file($_FILES['iss_import_file']['tmp_name'])
        ) {
            $this->redirect_with_notice('import_error');
        }

        $raw = file_get_contents($_FILES['iss_import_file']['tmp_name']);
        if (! is_string($raw) || $raw === '') {
            $this->redirect_with_notice('import_error');
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            $this->redirect_with_notice('import_error');
        }

        $options = isset($decoded['options']) && is_array($decoded['options']) ? $decoded['options'] : $decoded;
        $map = $this->get_option_keys();

        foreach ($map as $section => $option_name) {
            $payload = [];

            if (isset($options[$section]) && is_array($options[$section])) {
                $payload = $options[$section];
            } elseif (isset($options[$option_name]) && is_array($options[$option_name])) {
                $payload = $options[$option_name];
            }

            switch ($section) {
                case 'general':
                    update_option($option_name, $this->sanitize_general($payload));
                    break;
                case 'contact':
                    update_option($option_name, $this->sanitize_contact($payload));
                    break;
                case 'maps':
                    update_option($option_name, $this->sanitize_maps($payload));
                    break;
                case 'hours':
                    update_option($option_name, $this->sanitize_hours($payload));
                    $this->bump_visit_cache_version();
                    break;
                case 'accessibility':
                    update_option($option_name, $this->sanitize_accessibility($payload));
                    break;
                case 'prices':
                    update_option($option_name, $this->sanitize_prices($payload));
                    break;
                case 'faq':
                    update_option($option_name, $this->sanitize_faq($payload));
                    break;
                case 'mission_statement':
                    update_option($option_name, $this->sanitize_mission_statement($payload));
                    break;
            }
        }

        $this->redirect_with_notice('import_success');
    }

    private function redirect_with_notice(string $notice): void {
        wp_safe_redirect(add_query_arg(['page' => 'industriesalon-steuerung', 'iss_notice' => $notice], admin_url('admin.php')));
        exit;
    }

    public function register_blocks(): void {
        if (! function_exists('register_block_type')) {
            return;
        }

        // One shared editor script keeps the remaining block controls
        // consistent across field, hours, contact, FAQ and visit-info outputs.
        wp_register_script(
            'iss-control-blocks',
            plugins_url('assets/blocks.js', __FILE__),
            ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render'],
            self::VERSION,
            true
        );

        register_block_type('industriesalon/field', [
            'api_version'     => 2,
            'editor_script'   => 'iss-control-blocks',
            'render_callback' => [$this, 'render_field_block'],
            'attributes'      => [
                'key'      => ['type' => 'string', 'default' => 'contact.phone'],
                'tagName'  => ['type' => 'string', 'default' => 'div'],
                'linkMode' => ['type' => 'string', 'default' => 'auto'],
                'label'    => ['type' => 'string', 'default' => ''],
            ],
        ]);

        register_block_type('industriesalon/hours', [
            'api_version'     => 2,
            'editor_script'   => 'iss-control-blocks',
            'render_callback' => [$this, 'render_hours_block'],
            'attributes'      => [
                'type'            => ['type' => 'string', 'default' => 'public'],
                'title'           => ['type' => 'string', 'default' => ''],
                'variant'         => ['type' => 'string', 'default' => 'compact'],
                'skin'            => ['type' => 'string', 'default' => 'default'],
                'show_status'     => ['type' => 'boolean', 'default' => false],
                'show_exceptions' => ['type' => 'boolean', 'default' => false],
            ],
        ]);

        register_block_type('industriesalon/visit-info', [
            'api_version'     => 2,
            'editor_script'   => 'iss-control-blocks',
            'render_callback' => [$this, 'render_visit_info_block'],
            'supports'        => [
                'align' => ['wide', 'full'],
                'html'  => false,
            ],
            'attributes'      => [
                'align'              => ['type' => 'string', 'default' => ''],
                'variant'            => ['type' => 'string', 'default' => 'info-panel'],
                'accent'             => ['type' => 'string', 'default' => 'green'],
                'surface'            => ['type' => 'string', 'default' => 'light'],
                'kicker'             => ['type' => 'string', 'default' => 'ANREISE & MEHR'],
                'title'              => ['type' => 'string', 'default' => 'Besuch planen'],
                'intro'              => ['type' => 'string', 'default' => 'Die wichtigsten Hinweise für Ihren Besuch im Industriesalon.'],
                'show_address'       => ['type' => 'boolean', 'default' => true],
                'show_museum_hours'  => ['type' => 'boolean', 'default' => true],
                'show_office_hours'  => ['type' => 'boolean', 'default' => true],
                'show_arrival'       => ['type' => 'boolean', 'default' => true],
                'show_accessibility' => ['type' => 'boolean', 'default' => true],
            ],
        ]);

        register_block_type('industriesalon/contact', [
            'api_version'     => 2,
            'editor_script'   => 'iss-control-blocks',
            'render_callback' => [$this, 'render_contact_block'],
            'attributes'      => [
                'title'   => ['type' => 'string', 'default' => ''],
                'variant' => ['type' => 'string', 'default' => 'card'],
                'skin'    => ['type' => 'string', 'default' => 'default'],
            ],
        ]);

        register_block_type('industriesalon/footer-info', [
            'api_version'     => 2,
            'editor_script'   => 'iss-control-blocks',
            'render_callback' => [$this, 'render_footer_info_block'],
            'attributes'      => [
                'title'   => ['type' => 'string', 'default' => ''],
                'section' => ['type' => 'string', 'default' => 'visit'],
            ],
        ]);

        register_block_type('industriesalon/faq', [
            'api_version'     => 2,
            'editor_script'   => 'iss-control-blocks',
            'render_callback' => [$this, 'render_faq_block'],
            'attributes'      => [
                'title' => ['type' => 'string', 'default' => ''],
            ],
        ]);
    }

    public function render_field_block(array $attributes = []): string {
        return $this->render_field([
            'key'   => $attributes['key'] ?? 'contact.phone',
            'tag'   => $attributes['tagName'] ?? 'div',
            'link'  => $attributes['linkMode'] ?? 'auto',
            'label' => $attributes['label'] ?? '',
        ]);
    }

    public function render_hours_block(array $attributes = []): string {
        return $this->render_visit_hours(
            (string) ($attributes['type'] ?? 'public'),
            (string) ($attributes['variant'] ?? 'compact'),
            (string) ($attributes['title'] ?? ''),
            true,
            [
                'skin' => (string) ($attributes['skin'] ?? 'default'),
                'show_status' => ! empty($attributes['show_status']),
                'show_exceptions' => ! empty($attributes['show_exceptions']),
            ]
        );
    }

    public function render_visit_info_block(array $attributes = []): string {
        return $this->render_visit_info($attributes);
    }

    public function render_contact_block(array $attributes = []): string {
        return $this->render_contact(
            (string) ($attributes['title'] ?? ''),
            [
                'variant' => (string) ($attributes['variant'] ?? 'card'),
                'skin' => (string) ($attributes['skin'] ?? 'default'),
            ]
        );
    }

    public function render_footer_info_block(array $attributes = []): string {
        return $this->render_footer_info(
            (string) ($attributes['section'] ?? 'visit'),
            (string) ($attributes['title'] ?? '')
        );
    }

    public function render_faq_block(array $attributes = []): string {
        return $this->render_faq((string) ($attributes['title'] ?? ''));
    }

    public function render_field(array $args): string {
        $key = isset($args['key']) ? (string) $args['key'] : '';
        if ($key === '') {
            return '';
        }

        $tag = isset($args['tag']) ? strtolower((string) $args['tag']) : 'div';
        $allowed_tags = ['div', 'p', 'span', 'strong', 'h1', 'h2', 'h3'];
        if (! in_array($tag, $allowed_tags, true)) {
            $tag = 'div';
        }

        $link_mode = isset($args['link']) ? (string) $args['link'] : 'auto';
        $label = isset($args['label']) ? (string) $args['label'] : '';
        $value = $this->get_field_value($key);

        if ($value === '' || $value === null) {
            return '';
        }

        $label_html = $label !== '' ? '<span class="iss-control-field__label">' . esc_html($label) . '</span> ' : '';
        $content = esc_html((string) $value);
        $href = '';

        if ($link_mode === 'auto') {
            $href = $this->auto_link_for_key($key, (string) $value);
        } elseif ($link_mode === 'tel') {
            $href = 'tel:' . $this->normalize_phone_href((string) $value);
        } elseif ($link_mode === 'email') {
            $href = 'mailto:' . antispambot((string) $value);
        } elseif ($link_mode === 'url') {
            $href = esc_url((string) $value);
        }

        if ($href !== '' && $link_mode !== 'none') {
            $content = '<a href="' . esc_url($href) . '">' . $content . '</a>';
        }

        $class = 'iss-control-field iss-control-field--' . sanitize_html_class(str_replace('.', '-', $key));

        return sprintf('<%1$s class="%2$s">%3$s%4$s</%1$s>', esc_html($tag), esc_attr($class), $label_html, $content);
    }

    public function render_visit_info(array $attributes = []): string {
        $align = isset($attributes['align']) ? (string) $attributes['align'] : '';
        $align_class = in_array($align, ['wide', 'full'], true) ? ' align' . $align : '';
        $variant = $this->normalize_visit_variant((string) ($attributes['variant'] ?? 'info-panel'), 'visit_info');
        $accent = $this->normalize_info_panel_accent((string) ($attributes['accent'] ?? 'green'));
        $surface = $this->normalize_info_panel_surface((string) ($attributes['surface'] ?? 'light'));
        $kicker = trim((string) ($attributes['kicker'] ?? ''));
        $title = trim((string) ($attributes['title'] ?? ''));
        $intro = trim((string) ($attributes['intro'] ?? ''));
        $show_address = array_key_exists('show_address', $attributes) ? (bool) $attributes['show_address'] : true;
        $show_museum = array_key_exists('show_museum_hours', $attributes) ? (bool) $attributes['show_museum_hours'] : true;
        $show_office = array_key_exists('show_office_hours', $attributes) ? (bool) $attributes['show_office_hours'] : true;
        $show_arrival = array_key_exists('show_arrival', $attributes) ? (bool) $attributes['show_arrival'] : true;
        $show_accessibility = array_key_exists('show_accessibility', $attributes) ? (bool) $attributes['show_accessibility'] : true;

        $rows = [];
        if ($show_address) {
            $rows[] = $this->build_visit_info_address_row();
        }
        if ($show_museum) {
            $rows[] = $this->build_visit_info_hours_row('museum');
        }
        if ($show_office) {
            $rows[] = $this->build_visit_info_hours_row('office');
        }
        if ($show_arrival) {
            $rows[] = $this->build_visit_info_arrival_row();
        }
        if ($show_accessibility) {
            $rows[] = $this->build_visit_info_accessibility_row();
        }
        $rows = array_filter($rows, static function ($part) {
            return trim((string) $part) !== '';
        });

        if (empty($rows)) {
            return '<div class="iss-visit-info iss-visit-info--empty">' . esc_html__('Keine Zeiten eingetragen.', 'industriesalon-steuerung') . '</div>';
        }

        ob_start();
        ?>
        <div class="iss-visit-info iss-visit-info--<?php echo esc_attr(sanitize_html_class($variant)); ?><?php echo esc_attr($align_class); ?>" data-variant="<?php echo esc_attr($variant); ?>">
            <section class="iss-info-panel iss-info-panel--<?php echo esc_attr($accent); ?> iss-control-info-panel iss-control-info-panel--surface-<?php echo esc_attr($surface); ?>">
                <?php if ($kicker !== '') : ?>
                    <p class="iss-kicker iss-control-info-panel__kicker"><?php echo esc_html($kicker); ?></p>
                <?php endif; ?>
                <div class="wp-block-columns iss-info-panel__grid">
                    <div class="wp-block-column iss-info-panel__title-col" style="flex-basis:34%">
                        <?php if ($title !== '') : ?>
                            <h2 class="iss-info-panel__title"><?php echo esc_html($title); ?></h2>
                        <?php endif; ?>
                        <?php if ($intro !== '') : ?>
                            <p class="iss-info-panel__intro"><?php echo nl2br(esc_html($intro)); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="wp-block-column iss-info-panel__rows-col" style="flex-basis:66%">
                        <div class="iss-info-panel__rows">
                            <?php echo implode('', $rows); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function render_visit_status(string $type = 'museum', array $options = []): string {
        $resolved = $this->get_visit_day($type, $this->current_visit_moment());
        if ($resolved === []) {
            return '';
        }

        $variant = $this->normalize_visit_variant((string) ($options['variant'] ?? 'default'), 'status');
        $skin = $this->normalize_output_skin((string) ($options['skin'] ?? 'default'));
        $status = (string) ($resolved['status'] ?? 'open');
        $kind = (string) ($resolved['kind'] ?? 'regular');

        return '<p class="iss-visit-status iss-visit-status--' . esc_attr(sanitize_html_class($status)) . ' iss-visit-status--kind-' . esc_attr(sanitize_html_class($kind)) . ' iss-visit-status--variant-' . esc_attr(sanitize_html_class($variant)) . ' iss-visit-status--skin-' . esc_attr($skin) . '" data-status="' . esc_attr($status) . '" data-kind="' . esc_attr($kind) . '" data-source="' . esc_attr((string) ($resolved['source'] ?? 'regular')) . '">' . esc_html((string) ($resolved['label'] ?? '')) . '</p>';
    }

    public function render_visit_hours(string $type = 'museum', string $variant = 'compact', string $title = '', bool $show_heading = true, array $options = []): string {
        $type = $this->normalize_visit_type($type);
        $variant = $this->normalize_visit_variant($variant, 'hours');
        $skin = $this->normalize_output_skin((string) ($options['skin'] ?? 'default'));
        $show_status = ! empty($options['show_status']);
        $show_exceptions = ! empty($options['show_exceptions']) || $variant === 'info-panel';
        $group = $this->visit_group_for_type($this->get_visit_schedule(), $type);
        if ($group === []) {
            return '';
        }

        $heading = $title !== '' ? $title : $this->visit_type_label($type);
        $summary = $this->build_visit_summary($group);
        $exceptions = $show_exceptions ? $this->build_visit_exception_list($type, [
            'variant' => $variant === 'footer' ? 'footer' : 'list',
            'skin' => $skin,
        ]) : '';
        $status_html = $show_status ? $this->render_visit_status($type, ['variant' => $variant === 'footer' ? 'footer' : 'default', 'skin' => $skin]) : '';

        if ($variant === 'inline') {
            $line = $summary !== '' ? $summary : __('Keine Zeiten eingetragen.', 'industriesalon-steuerung');
            $status_text = $show_status && $status_html !== '' ? wp_strip_all_tags($status_html) . '. ' : '';
            return '<p class="iss-visit-hours iss-visit-hours--inline iss-visit-hours--' . esc_attr($type) . ' iss-visit-hours--skin-' . esc_attr($skin) . '" data-variant="' . esc_attr($variant) . '" data-skin="' . esc_attr($skin) . '">' . esc_html($status_text . $heading . ': ' . wp_strip_all_tags($line)) . '</p>';
        }

        ob_start();
        ?>
        <section class="iss-visit-hours iss-visit-hours--<?php echo esc_attr($variant); ?> iss-visit-hours--<?php echo esc_attr($type); ?> iss-visit-hours--skin-<?php echo esc_attr($skin); ?>" data-variant="<?php echo esc_attr($variant); ?>" data-skin="<?php echo esc_attr($skin); ?>" data-type="<?php echo esc_attr($type); ?>">
            <?php if ($show_heading) : ?>
                <h3 class="iss-visit-hours__title"><?php echo esc_html($heading); ?></h3>
            <?php endif; ?>
            <?php if ($status_html !== '') : ?>
                <?php echo $status_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
            <?php if ($variant !== 'full') : ?>
                <p class="iss-visit-hours__summary"><?php echo esc_html($summary !== '' ? $summary : __('Keine Zeiten eingetragen.', 'industriesalon-steuerung')); ?></p>
                <?php if (! empty($group['note']) && in_array($variant, ['info-panel', 'front-card'], true)) : ?>
                    <p class="iss-visit-hours__note"><?php echo nl2br(esc_html((string) $group['note'])); ?></p>
                <?php endif; ?>
                <?php if (! empty($exceptions) && in_array($variant, ['info-panel', 'front-card', 'footer'], true)) : ?>
                    <div class="iss-visit-hours__exceptions"><?php echo $exceptions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
                <?php endif; ?>
            <?php else : ?>
                <div class="iss-visit-hours__table-wrap iss-open-hours__table-wrap">
                    <?php if (! $show_heading) : ?>
                        <h3 class="iss-visit-hours__title iss-open-hours__table-title"><?php echo esc_html($heading); ?></h3>
                    <?php endif; ?>
                    <figure class="wp-block-table iss-visit-hours__table iss-open-hours__table">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Tag', 'industriesalon-steuerung'); ?></th>
                                    <th><?php esc_html_e('Zeit', 'industriesalon-steuerung'); ?></th>
                                    <th><?php esc_html_e('Status', 'industriesalon-steuerung'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($this->render_visit_hours_rows($group['days'] ?? [], $type) as $row) : ?>
                                    <tr class="<?php echo esc_attr(trim(($row['today'] ? 'is-today ' : '') . 'iss-visit-hours__row--' . ($row['badge'] ?? 'closed') . ' iss-visit-hours__row--kind-' . ($row['kind'] ?? 'regular'))); ?>" data-status="<?php echo esc_attr((string) ($row['badge'] ?? 'closed')); ?>" data-kind="<?php echo esc_attr((string) ($row['kind'] ?? 'regular')); ?>">
                                        <td><?php echo esc_html((string) $row['day']); ?></td>
                                        <td><?php echo esc_html((string) $row['time']); ?></td>
                                        <td>
                                            <span class="iss-visit-hours__badge iss-open-hours__badge iss-open-hours__badge--<?php echo esc_attr((string) $row['badge']); ?> iss-visit-hours__badge--kind-<?php echo esc_attr((string) ($row['kind'] ?? 'regular')); ?>" data-kind="<?php echo esc_attr((string) ($row['kind'] ?? 'regular')); ?>">
                                                <?php echo esc_html((string) $row['badge_label']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </figure>
                </div>
                <?php if (! empty($group['note'])) : ?>
                    <p class="iss-visit-hours__note"><?php echo nl2br(esc_html((string) $group['note'])); ?></p>
                <?php endif; ?>
                <?php if (! empty($exceptions)) : ?>
                    <div class="iss-visit-hours__exceptions">
                        <h4 class="iss-visit-hours__exceptions-title"><?php esc_html_e('Sondertage', 'industriesalon-steuerung'); ?></h4>
                        <?php echo $exceptions; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function render_visit_hours_rows(array $days, string $type): array {
        $rows = [];
        $day_labels = $this->days();
        $today_index = (int) $this->current_visit_moment()->format('N') - 1;

        foreach (array_keys($day_labels) as $index => $slug) {
            $row = wp_parse_args($days[$slug] ?? [], ['closed' => 0, 'open' => '', 'close' => '', 'note' => '']);
            $day_date = $this->current_visit_moment();
            $delta = $index - $today_index;
            if ($delta !== 0) {
                $day_date = $day_date->modify(($delta > 0 ? '+' : '') . $delta . ' days');
            }

            $resolved = $this->resolve_visit_day($this->get_visit_schedule(), $type, $day_date);
            $is_today = $index === $today_index;
            $badge = (string) ($resolved['status'] ?? 'open');
            $kind = (string) ($resolved['kind'] ?? 'regular');
            $kind_meta = $this->get_exception_kind_meta($kind);
            $badge_label = $badge === 'closed'
                ? __('geschlossen', 'industriesalon-steuerung')
                : ($kind !== 'regular'
                    ? (string) ($kind_meta['badge'] ?? __('sonder', 'industriesalon-steuerung'))
                    : __('offen', 'industriesalon-steuerung'));
            if ($is_today && $badge === 'open') {
                $badge_label = __('heute', 'industriesalon-steuerung');
            } elseif ($is_today && $badge === 'closed') {
                $badge_label = __('heute', 'industriesalon-steuerung');
            }

            $rows[] = [
                'day'        => $day_labels[$slug] ?? $slug,
                'time'       => $is_today ? $this->format_visit_time_ranges((array) ($resolved['ranges'] ?? [])) : $this->format_hours_row($row),
                'badge'      => $badge,
                'badge_label'=> $badge_label,
                'kind'       => $kind,
                'today'      => $is_today,
            ];
        }

        return $rows;
    }

    public function render_visit_exceptions(string $type = 'museum', array $options = []): string {
        $type = $this->normalize_visit_type($type);
        $list = $this->get_relevant_visit_exceptions($type);
        if (empty($list)) {
            return '';
        }

        $variant = $this->normalize_visit_variant((string) ($options['variant'] ?? 'list'), 'exceptions');
        $skin = $this->normalize_output_skin((string) ($options['skin'] ?? 'default'));
        $title = trim((string) ($options['title'] ?? ''));
        if ($title === '') {
            $title = __('Sondertage', 'industriesalon-steuerung');
        }

        ob_start();
        ?>
        <section class="iss-visit-exceptions iss-visit-exceptions--<?php echo esc_attr($type); ?> iss-visit-exceptions--<?php echo esc_attr($variant); ?> iss-visit-exceptions--skin-<?php echo esc_attr($skin); ?>" data-variant="<?php echo esc_attr($variant); ?>" data-skin="<?php echo esc_attr($skin); ?>">
            <h3 class="iss-visit-exceptions__title"><?php echo esc_html($title); ?></h3>
            <ul class="iss-visit-exceptions__list">
                <?php foreach ($list as $item) : ?>
                    <li class="iss-visit-exceptions__item iss-visit-exceptions__item--<?php echo esc_attr(sanitize_html_class((string) ($item['status'] ?? 'special'))); ?> iss-visit-exceptions__item--kind-<?php echo esc_attr(sanitize_html_class((string) ($item['kind'] ?? 'special_open'))); ?>" data-status="<?php echo esc_attr((string) ($item['status'] ?? 'special')); ?>" data-kind="<?php echo esc_attr((string) ($item['kind'] ?? 'special_open')); ?>">
                        <span class="iss-visit-exceptions__date"><?php echo esc_html((string) $item['date_label']); ?></span>
                        <span class="iss-visit-exceptions__time"><?php echo esc_html((string) $item['label']); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php
        return (string) ob_get_clean();
    }

    private function build_visit_info_address_row(): string {
        $address = trim($this->compose_full_address());
        if ($address === '') {
            return '';
        }

        $maps = get_option(self::OPTION_MAPS, $this->default_maps());
        $action = '';
        if (! empty($maps['google_maps_url'])) {
            $action = $this->visit_info_action_button((string) $maps['google_maps_url'], __('Google Maps', 'industriesalon-steuerung'));
        }

        return $this->render_visit_info_row(
            'address',
            __('Adresse', 'industriesalon-steuerung'),
            $address,
            $action
        );
    }

    private function build_visit_info_hours_row(string $type): string {
        $type = $this->normalize_visit_type($type);
        $group = $this->visit_group_for_type($this->get_visit_schedule(), $type);
        $summary = $this->build_visit_summary($group, true);
        if ($summary === '') {
            $summary = __('Keine Zeiten eingetragen.', 'industriesalon-steuerung');
        }

        $label = $this->visit_type_label($type);
        $text = '<strong>' . esc_html($label) . ':</strong> ' . esc_html($summary);
        $notice = $this->build_visit_info_exception_notice($type);
        if ($notice !== '') {
            $text .= $notice;
        }

        return $this->render_visit_info_row_html(
            $type === 'office' ? 'office-hours' : 'museum-hours',
            $text
        );
    }

    private function build_visit_info_arrival_row(): string {
        $general = get_option(self::OPTION_GENERAL, $this->default_general());
        $arrival = trim((string) ($general['arrival'] ?? ''));
        if ($arrival === '') {
            return '';
        }

        return $this->render_visit_info_row(
            'arrival',
            __('ÖPNV', 'industriesalon-steuerung'),
            $arrival
        );
    }

    private function build_visit_info_accessibility_row(): string {
        $summary = $this->visit_info_accessibility_summary();
        if ($summary === '') {
            return '';
        }

        return $this->render_visit_info_row(
            'accessibility',
            __('Barrierefreiheit', 'industriesalon-steuerung'),
            $summary
        );
    }

    private function build_visit_info_exception_notice(string $type): string {
        $items = array_slice($this->get_relevant_visit_exceptions($type), 0, 2);
        if (empty($items)) {
            return '';
        }

        $parts = [];
        foreach ($items as $item) {
            $line = $this->format_visit_info_exception_notice_item($item);
            if ($line !== '') {
                $parts[] = $line;
            }
        }

        if (empty($parts)) {
            return '';
        }

        return '<span class="iss-control-info-panel__subline iss-control-info-panel__subline--exception"><span class="iss-control-info-panel__subline-label">' . esc_html__('Sondertag:', 'industriesalon-steuerung') . '</span> ' . esc_html(implode(' · ', $parts)) . '</span>';
    }

    private function format_visit_info_exception_notice_item(array $item): string {
        $date = $this->sanitize_date_value((string) ($item['date'] ?? ''));
        if ($date === '') {
            return '';
        }

        $moment = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', wp_timezone());
        if (! ($moment instanceof DateTimeImmutable)) {
            return '';
        }

        $kind = (string) ($item['kind'] ?? 'special_open');
        $status = $this->visit_info_exception_status_label($kind);
        $date_label = $this->format_visit_info_exception_date($moment);
        $time_label = $this->format_visit_info_exception_time_label($item, $kind);
        $note = trim((string) ($item['note'] ?? ''));

        $parts = [$status, $date_label];
        if ($time_label !== '') {
            $parts[] = $time_label;
        }

        $line = implode(' ', $parts);
        if ($note !== '' && $time_label === '') {
            $line .= ' (' . $note . ')';
        }

        return $line;
    }

    private function visit_info_exception_status_label(string $kind): string {
        if ($kind === 'closed') {
            return __('geschlossen', 'industriesalon-steuerung');
        }

        if ($kind === 'by_appointment') {
            return __('nur nach Vereinbarung', 'industriesalon-steuerung');
        }

        return __('geöffnet', 'industriesalon-steuerung');
    }

    private function format_visit_info_exception_time_label(array $item, string $kind): string {
        if ($kind === 'closed' || $kind === 'by_appointment') {
            return '';
        }

        $open = $this->format_visit_clock((string) ($item['open'] ?? ''));
        $close = $this->format_visit_clock((string) ($item['close'] ?? ''));
        if ($open !== '' && $close !== '') {
            return $open . '–' . $close . ' Uhr';
        }
        if ($open !== '') {
            return $open . ' Uhr';
        }
        if ($close !== '') {
            return $close . ' Uhr';
        }

        return '';
    }

    private function format_visit_info_exception_date(DateTimeImmutable $moment): string {
        $weekday_map = [
            1 => __('Mo.', 'industriesalon-steuerung'),
            2 => __('Di.', 'industriesalon-steuerung'),
            3 => __('Mi.', 'industriesalon-steuerung'),
            4 => __('Do.', 'industriesalon-steuerung'),
            5 => __('Fr.', 'industriesalon-steuerung'),
            6 => __('Sa.', 'industriesalon-steuerung'),
            7 => __('So.', 'industriesalon-steuerung'),
        ];

        $weekday = $weekday_map[(int) $moment->format('N')] ?? '';
        return trim($weekday . ' ' . wp_date('d.m.y', $moment->getTimestamp()));
    }

    private function render_visit_info_row(string $icon, string $label, string $text, string $action = ''): string {
        $main = '<div class="iss-info-row__main"><p class="iss-info-row__text"><span class="iss-control-info-panel__icon">' . $this->visit_info_icon($icon) . '</span><span class="iss-control-info-panel__copy"><strong>' . esc_html($label) . ':</strong> ' . nl2br(esc_html($text)) . '</span></p></div>';
        $action_html = $action !== '' ? '<div class="iss-info-row__actions">' . $action . '</div>' : '';

        return '<div class="iss-info-row iss-control-info-panel__row iss-control-info-panel__row--' . esc_attr(sanitize_html_class($icon)) . '">' . $main . $action_html . '</div>';
    }

    private function render_visit_info_row_html(string $icon, string $text_html, string $action = ''): string {
        $main = '<div class="iss-info-row__main"><p class="iss-info-row__text"><span class="iss-control-info-panel__icon">' . $this->visit_info_icon($icon) . '</span><span class="iss-control-info-panel__copy">' . $text_html . '</span></p></div>';
        $action_html = $action !== '' ? '<div class="iss-info-row__actions">' . $action . '</div>' : '';

        return '<div class="iss-info-row iss-control-info-panel__row iss-control-info-panel__row--' . esc_attr(sanitize_html_class($icon)) . '">' . $main . $action_html . '</div>';
    }

    private function visit_info_action_button(string $url, string $label): string {
        return '<p class="iss-control-info-panel__action-link"><a class="iss-action-link" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($label) . '</a></p>';
    }

    private function visit_info_accessibility_summary(): string {
        $accessibility = get_option(self::OPTION_ACCESSIBILITY, $this->default_accessibility());
        $parts = [];
        if (! empty($accessibility['wheelchair'])) {
            $parts[] = __('Stufenloser Zugang', 'industriesalon-steuerung');
        }
        if (! empty($accessibility['accessible_toilet'])) {
            $parts[] = __('Barrierefreies WC', 'industriesalon-steuerung');
        }
        if (! empty($accessibility['elevator'])) {
            $parts[] = __('Aufzug vorhanden', 'industriesalon-steuerung');
        }
        if (! empty($accessibility['parking'])) {
            $parts[] = __('Parken in der Nähe', 'industriesalon-steuerung');
        }
        if (! empty($accessibility['companion'])) {
            $parts[] = __('Begleitperson möglich', 'industriesalon-steuerung');
        }

        $summary = implode(', ', $parts);
        $note = trim((string) ($accessibility['note'] ?? ''));
        if ($note !== '') {
            $summary = $summary !== '' ? $summary . '. ' . $note : $note;
        }

        return $summary;
    }

    private function visit_info_icon(string $icon): string {
        if ($icon === 'accessibility') {
            $icon_url = plugins_url('assets/accessibility-icon.png', __FILE__);

            return '<img src="' . esc_url($icon_url) . '" alt="" loading="lazy" decoding="async">';
        }

        $icons = [
            'address' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 21s-6-5.6-6-10.5A6 6 0 1 1 18 10.5C18 15.4 12 21 12 21Z" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="10" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>',
            'museum-hours' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M12 7.5v5l3.2 1.8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'office-hours' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><rect x="4" y="5" width="16" height="14" rx="1.8" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M9 5V3.8M15 5V3.8M4 9.5h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
            'arrival' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M5 17h14M7 17l1.1-6.2A2 2 0 0 1 10.1 9h3.8a2 2 0 0 1 2 1.8L17 17M8.5 17a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Zm7 0a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3ZM9 9V6.8A1.8 1.8 0 0 1 10.8 5h2.4A1.8 1.8 0 0 1 15 6.8V9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>',
            'accessibility' => '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><circle cx="12" cy="5" r="2" fill="currentColor"/><path d="M8 8.5h8M12 8.5v4m0 0 3 3.5m-3-3.5-2.5 6M12 12.5H8.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="12" cy="12" r="8.2" fill="none" stroke="currentColor" stroke-width="1.2"/></svg>',
        ];

        return $icons[$icon] ?? $icons['address'];
    }

    public function render_contact(string $title = '', array $options = []): string {
        $contact = get_option(self::OPTION_CONTACT, $this->default_contact());
        $general = get_option(self::OPTION_GENERAL, $this->default_general());
        $maps = get_option(self::OPTION_MAPS, $this->default_maps());
        $variant = $this->normalize_visit_variant((string) ($options['variant'] ?? 'card'), 'contact');
        $skin = $this->normalize_output_skin((string) ($options['skin'] ?? 'default'));

        $address_lines = array_filter([
            $general['site_name'] ?? '',
            $this->compose_address_line(),
            trim(($general['postal_code'] ?? '') . ' ' . ($general['city'] ?? '')),
        ]);

        if ($variant === 'compact-row') {
            $parts = [];
            if (! empty($address_lines)) {
                $parts[] = '<span class="iss-contact-card__compact-address">' . esc_html(implode(', ', $address_lines)) . '</span>';
            }
            if (! empty($contact['phone'])) {
                $parts[] = '<a href="' . esc_url('tel:' . $this->normalize_phone_href((string) $contact['phone'])) . '">' . esc_html((string) $contact['phone']) . '</a>';
            }
            if (! empty($contact['email'])) {
                $parts[] = '<a href="' . esc_url('mailto:' . antispambot((string) $contact['email'])) . '">' . esc_html((string) $contact['email']) . '</a>';
            }
            if (! empty($maps['google_maps_url'])) {
                $parts[] = '<a href="' . esc_url((string) $maps['google_maps_url']) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Karte', 'industriesalon-steuerung') . '</a>';
            }

            if (empty($parts)) {
                return '';
            }

            return '<div class="iss-contact-card iss-contact-card--' . esc_attr($variant) . ' iss-contact-card--skin-' . esc_attr($skin) . '" data-variant="' . esc_attr($variant) . '" data-skin="' . esc_attr($skin) . '">' . implode('<span class="iss-contact-card__separator" aria-hidden="true">·</span>', $parts) . '</div>';
        }

        if ($variant === 'footer') {
            return $this->render_footer_contact($title, $contact);
        }

        ob_start();
        ?>
        <div class="iss-contact-card iss-contact-card--<?php echo esc_attr($variant); ?> iss-contact-card--skin-<?php echo esc_attr($skin); ?>" data-variant="<?php echo esc_attr($variant); ?>" data-skin="<?php echo esc_attr($skin); ?>">
            <?php if ($title !== '') : ?>
                <h3 class="iss-contact-card__title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>

            <?php if (! empty($address_lines)) : ?>
                <div class="iss-contact-card__address">
                    <?php foreach ($address_lines as $line) : ?>
                        <div><?php echo esc_html($line); ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <ul class="iss-contact-card__list">
                <?php if (! empty($contact['phone'])) : ?>
                    <li><a href="<?php echo esc_url('tel:' . $this->normalize_phone_href((string) $contact['phone'])); ?>"><?php echo esc_html((string) $contact['phone']); ?></a></li>
                <?php endif; ?>
                <?php if (! empty($contact['email'])) : ?>
                    <li><a href="<?php echo esc_url('mailto:' . antispambot((string) $contact['email'])); ?>"><?php echo esc_html((string) $contact['email']); ?></a></li>
                <?php endif; ?>
                <?php if (! empty($contact['booking_email'])) : ?>
                    <li><a href="<?php echo esc_url('mailto:' . antispambot((string) $contact['booking_email'])); ?>"><?php echo esc_html((string) $contact['booking_email']); ?></a></li>
                <?php endif; ?>
                <?php if (! empty($contact['website'])) : ?>
                    <li><a href="<?php echo esc_url($this->normalize_public_url((string) $contact['website'])); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html((string) $contact['website']); ?></a></li>
                <?php endif; ?>
                <?php if (! empty($maps['google_maps_url'])) : ?>
                    <li><a href="<?php echo esc_url($this->normalize_public_url((string) $maps['google_maps_url'])); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Google Maps', 'industriesalon-steuerung'); ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function render_footer_info(string $section = 'visit', string $title = ''): string {
        $section = sanitize_key($section);

        if ($section === 'brand') {
            return $this->render_footer_brand($title);
        }

        if ($section === 'visit') {
            return $this->render_footer_visit($title);
        }

        return '';
    }

    public function render_prices(string $title = ''): string {
        $prices = get_option(self::OPTION_PRICES, $this->default_prices());
        $items = [
            'adult'   => __('Erwachsene', 'industriesalon-steuerung'),
            'reduced' => __('Ermäßigt', 'industriesalon-steuerung'),
            'group'   => __('Gruppen', 'industriesalon-steuerung'),
            'school'  => __('Schulklassen', 'industriesalon-steuerung'),
            'tour'    => __('Führung', 'industriesalon-steuerung'),
            'rental'  => __('Raumvermietung', 'industriesalon-steuerung'),
        ];

        ob_start();
        ?>
        <div class="iss-prices">
            <?php if ($title !== '') : ?>
                <h3 class="iss-prices__title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            <ul class="iss-prices__list">
                <?php foreach ($items as $key => $label) : ?>
                    <?php if (! empty($prices[$key])) : ?>
                        <li class="iss-prices__item">
                            <span class="iss-prices__label"><?php echo esc_html($label); ?></span>
                            <span class="iss-prices__value"><?php echo esc_html((string) $prices[$key]); ?></span>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
            <?php if (! empty($prices['note'])) : ?>
                <p class="iss-prices__note"><?php echo nl2br(esc_html((string) $prices['note'])); ?></p>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function render_faq(string $title = ''): string {
        $faq = get_option(self::OPTION_FAQ, $this->default_faq());
        $items = $faq['items'] ?? [];
        if (empty($items)) {
            return '';
        }

        ob_start();
        ?>
        <div class="iss-faq-list">
            <?php if ($title !== '') : ?>
                <h3 class="iss-faq-list__title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            <?php foreach ($items as $item) : ?>
                <?php if (empty($item['question']) && empty($item['answer'])) { continue; } ?>
                <details class="iss-faq-item">
                    <summary class="iss-faq-item__question"><?php echo esc_html((string) ($item['question'] ?? '')); ?></summary>
                    <div class="iss-faq-item__answer"><?php echo wpautop(wp_kses_post((string) ($item['answer'] ?? ''))); ?></div>
                </details>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    public function render_mission_statement(string $title = '', string $heading = '', string $layout = 'offset'): string {
        $mission_statement = get_option(self::OPTION_MISSION_STATEMENT, $this->default_mission_statement());
        $content = trim((string) ($mission_statement['content'] ?? ''));
        if ($content === '') {
            return '';
        }

        $wrapper_classes = ['iss-heading', 'iss-mission-statement'];
        if ($layout !== 'natural') {
            $wrapper_classes[] = 'iss-mission-statement--manual-offset';
        }

        ob_start();
        ?>
        <div class="<?php echo esc_attr(implode(' ', $wrapper_classes)); ?>">
            <?php if ($title !== '') : ?>
                <p class="iss-kicker iss-mission-statement__title"><?php echo esc_html($title); ?></p>
            <?php endif; ?>
            <?php if ($heading !== '') : ?>
                <h2 class="iss-heading__title iss-mission-statement__heading"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>
            <p class="iss-heading__text iss-mission-statement__text"><?php echo nl2br(esc_html($content)); ?></p>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function hours_table(string $type, array $group, array $days): void {
        $group = wp_parse_args($group, ['note' => '', 'days' => []]);
        ?>
        <table class="widefat striped iss-hours-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Tag', 'industriesalon-steuerung'); ?></th>
                    <th><?php esc_html_e('Geschlossen', 'industriesalon-steuerung'); ?></th>
                    <th><?php esc_html_e('Von', 'industriesalon-steuerung'); ?></th>
                    <th><?php esc_html_e('Bis', 'industriesalon-steuerung'); ?></th>
                    <th><?php esc_html_e('Hinweis', 'industriesalon-steuerung'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $slug => $label) : ?>
                    <?php $row = wp_parse_args($group['days'][$slug] ?? [], ['closed' => 0, 'open' => '', 'close' => '', 'note' => '']); ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($label); ?></th>
                        <td><input type="checkbox" name="<?php echo esc_attr(self::OPTION_HOURS . '[' . $type . '][days][' . $slug . '][closed]'); ?>" value="1" <?php checked(! empty($row['closed'])); ?>></td>
                        <td><input type="time" class="regular-text iss-time" step="300" name="<?php echo esc_attr(self::OPTION_HOURS . '[' . $type . '][days][' . $slug . '][open]'); ?>" value="<?php echo esc_attr((string) $row['open']); ?>" placeholder="10:00"></td>
                        <td><input type="time" class="regular-text iss-time" step="300" name="<?php echo esc_attr(self::OPTION_HOURS . '[' . $type . '][days][' . $slug . '][close]'); ?>" value="<?php echo esc_attr((string) $row['close']); ?>" placeholder="18:00"></td>
                        <td><input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_HOURS . '[' . $type . '][days][' . $slug . '][note]'); ?>" value="<?php echo esc_attr((string) $row['note']); ?>"></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        $this->textarea_field(self::OPTION_HOURS . '[' . $type . ']', 'note', __('Globaler Hinweis', 'industriesalon-steuerung'), $group['note'] ?? '', 3, false);
    }

    private function special_date_fields($index, array $item): void {
        $item = wp_parse_args($item, [
            'date' => '',
            'type' => 'public',
            'kind' => 'special_open',
            'closed' => 0,
            'open' => '',
            'close' => '',
            'label' => '',
            'note' => '',
        ]);
        ?>
        <div class="iss-special-item" data-iss-special-item>
            <div class="iss-grid iss-grid--2">
                <div class="iss-field">
                    <label><?php esc_html_e('Datum', 'industriesalon-steuerung'); ?></label>
                    <input type="date" class="regular-text" name="<?php echo esc_attr(self::OPTION_HOURS . '[exceptions][' . $index . '][date]'); ?>" value="<?php echo esc_attr((string) $item['date']); ?>">
                </div>
                <div class="iss-field">
                    <label><?php esc_html_e('Bereich', 'industriesalon-steuerung'); ?></label>
                    <select class="regular-text" name="<?php echo esc_attr(self::OPTION_HOURS . '[exceptions][' . $index . '][type]'); ?>">
                        <option value="public" <?php selected((string) $item['type'], 'public'); ?>><?php esc_html_e('Besuchszeiten', 'industriesalon-steuerung'); ?></option>
                        <option value="office" <?php selected((string) $item['type'], 'office'); ?>><?php esc_html_e('Bürozeiten', 'industriesalon-steuerung'); ?></option>
                        <option value="both" <?php selected((string) $item['type'], 'both'); ?>><?php esc_html_e('Beides', 'industriesalon-steuerung'); ?></option>
                    </select>
                </div>
                <div class="iss-field">
                    <label><?php esc_html_e('Art des Sondertags', 'industriesalon-steuerung'); ?></label>
                    <select class="regular-text" name="<?php echo esc_attr(self::OPTION_HOURS . '[exceptions][' . $index . '][kind]'); ?>">
                        <?php foreach ($this->exception_kind_options() as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>" <?php selected($this->normalize_exception_kind((string) $item['kind'], ! empty($item['closed'])), $value); ?>><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description iss-field-help"><?php esc_html_e('Bestimmt Text, Status und spätere Darstellung im Frontend.', 'industriesalon-steuerung'); ?></p>
                </div>
                <div class="iss-field">
                    <label><?php esc_html_e('Von', 'industriesalon-steuerung'); ?></label>
                    <input type="time" class="regular-text iss-time" step="300" name="<?php echo esc_attr(self::OPTION_HOURS . '[exceptions][' . $index . '][open]'); ?>" value="<?php echo esc_attr((string) $item['open']); ?>">
                </div>
                <div class="iss-field">
                    <label><?php esc_html_e('Bis', 'industriesalon-steuerung'); ?></label>
                    <input type="time" class="regular-text iss-time" step="300" name="<?php echo esc_attr(self::OPTION_HOURS . '[exceptions][' . $index . '][close]'); ?>" value="<?php echo esc_attr((string) $item['close']); ?>">
                </div>
                <div class="iss-field">
                    <label><?php esc_html_e('Kurzes Label', 'industriesalon-steuerung'); ?></label>
                    <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_HOURS . '[exceptions][' . $index . '][label]'); ?>" value="<?php echo esc_attr((string) $item['label']); ?>">
                    <p class="description iss-field-help"><?php esc_html_e('Optional. Überschreibt die automatische Kurzbezeichnung für Listen und Statuszeilen.', 'industriesalon-steuerung'); ?></p>
                </div>
                <div class="iss-field">
                    <label><?php esc_html_e('Hinweis', 'industriesalon-steuerung'); ?></label>
                    <input type="text" class="regular-text" name="<?php echo esc_attr(self::OPTION_HOURS . '[exceptions][' . $index . '][note]'); ?>" value="<?php echo esc_attr((string) $item['note']); ?>">
                </div>
            </div>
            <p><button type="button" class="button-link-delete" data-iss-remove-special><?php esc_html_e('Sondertag entfernen', 'industriesalon-steuerung'); ?></button></p>
        </div>
        <?php
    }

    private function faq_item_fields($index, array $item): void {
        $question = isset($item['question']) ? (string) $item['question'] : '';
        $answer = isset($item['answer']) ? (string) $item['answer'] : '';
        ?>
        <div class="iss-faq-item-editor" data-iss-faq-item>
            <div class="iss-grid iss-grid--1">
                <?php $this->text_field(self::OPTION_FAQ . '[items][' . $index . ']', 'question', __('Frage', 'industriesalon-steuerung'), $question, false, 'Ist der Industriesalon barrierefrei?'); ?>
                <?php $this->textarea_field(self::OPTION_FAQ . '[items][' . $index . ']', 'answer', __('Antwort', 'industriesalon-steuerung'), $answer, 4, false, 'Der Eingangsbereich ist stufenlos erreichbar. Weitere Hinweise finden Sie unter Barrierefreiheit.'); ?>
            </div>
            <p><button type="button" class="button-link-delete" data-iss-remove-faq><?php esc_html_e('Eintrag entfernen', 'industriesalon-steuerung'); ?></button></p>
        </div>
        <?php
    }

    private function text_field(string $base, string $key, string $label, string $value, bool $wrap = true, string $placeholder = '', string $help = ''): void {
        $name = $base . '[' . $key . ']';
        $field = '<div class="iss-field"><label>' . esc_html($label) . '</label><input type="text" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
        if ($help !== '') {
            $field .= '<p class="description iss-field-help">' . esc_html($help) . '</p>';
        }
        $field .= '</div>';
        echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function email_field(string $base, string $key, string $label, string $value, string $placeholder = '', string $help = ''): void {
        $name = $base . '[' . $key . ']';
        $field = '<div class="iss-field"><label>' . esc_html($label) . '</label><input type="email" class="regular-text" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
        if ($help !== '') {
            $field .= '<p class="description iss-field-help">' . esc_html($help) . '</p>';
        }
        $field .= '</div>';
        echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function url_field(string $base, string $key, string $label, string $value, string $placeholder = '', string $help = ''): void {
        $name = $base . '[' . $key . ']';
        $field = '<div class="iss-field"><label>' . esc_html($label) . '</label><input type="url" class="regular-text code" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" placeholder="' . esc_attr($placeholder) . '">';
        if ($help !== '') {
            $field .= '<p class="description iss-field-help">' . esc_html($help) . '</p>';
        }
        $field .= '</div>';
        echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function textarea_field(string $base, string $key, string $label, string $value, int $rows = 4, bool $wrap = true, string $placeholder = '', string $help = ''): void {
        $name = $base . '[' . $key . ']';
        $field = '<div class="iss-field"><label>' . esc_html($label) . '</label><textarea class="large-text" rows="' . esc_attr((string) $rows) . '" name="' . esc_attr($name) . '" placeholder="' . esc_attr($placeholder) . '">' . esc_textarea($value) . '</textarea>';
        if ($help !== '') {
            $field .= '<p class="description iss-field-help">' . esc_html($help) . '</p>';
        }
        $field .= '</div>';
        echo $field; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    private function checkbox_field(string $base, string $key, string $label, bool $checked): void {
        $name = $base . '[' . $key . ']';
        echo '<label class="iss-checkbox"><input type="checkbox" name="' . esc_attr($name) . '" value="1" ' . checked($checked, true, false) . '> <span>' . esc_html($label) . '</span></label>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    public function sanitize_general($input): array {
        $input = is_array($input) ? $input : [];
        return [
            'site_name'   => sanitize_text_field($input['site_name'] ?? ''),
            'tagline'     => sanitize_text_field($input['tagline'] ?? ''),
            'street'      => sanitize_text_field($input['street'] ?? ''),
            'postal_code' => sanitize_text_field($input['postal_code'] ?? ''),
            'city'        => sanitize_text_field($input['city'] ?? ''),
            'district'    => sanitize_text_field($input['district'] ?? ''),
            'arrival'     => sanitize_textarea_field($input['arrival'] ?? ''),
            'visit_note'  => sanitize_textarea_field($input['visit_note'] ?? ''),
        ];
    }

    public function sanitize_contact($input): array {
        $input = is_array($input) ? $input : [];
        return [
            'phone'          => sanitize_text_field($input['phone'] ?? ''),
            'email'          => sanitize_email($input['email'] ?? ''),
            'booking_email'  => sanitize_email($input['booking_email'] ?? ''),
            'contact_person' => sanitize_text_field($input['contact_person'] ?? ''),
            'website'        => $this->normalize_public_url((string) ($input['website'] ?? '')),
            'booking_url'    => $this->normalize_public_url((string) ($input['booking_url'] ?? '')),
            'instagram'      => $this->normalize_public_url((string) ($input['instagram'] ?? '')),
            'facebook'       => $this->normalize_public_url((string) ($input['facebook'] ?? '')),
            'youtube'        => $this->normalize_public_url((string) ($input['youtube'] ?? '')),
        ];
    }

    public function sanitize_maps($input): array {
        $input = is_array($input) ? $input : [];
        $embed = $input['google_maps_embed'] ?? '';

        if (is_string($embed) && strpos($embed, '<iframe') !== false) {
            $embed = wp_kses($embed, [
                'iframe' => [
                    'src'             => true,
                    'width'           => true,
                    'height'          => true,
                    'style'           => true,
                    'loading'         => true,
                    'allowfullscreen' => true,
                    'referrerpolicy'  => true,
                ],
            ]);
        } else {
            $embed = esc_url_raw((string) $embed);
        }

        return [
            'google_maps_url'   => esc_url_raw($input['google_maps_url'] ?? ''),
            'google_maps_embed' => $embed,
        ];
    }

    public function sanitize_hours($input): array {
        $input = is_array($input) ? $input : [];
        $days = $this->days();
        $output = $this->default_hours();

        foreach (['public', 'office'] as $type) {
            $group = isset($input[$type]) && is_array($input[$type]) ? $input[$type] : [];
            $output[$type]['note'] = sanitize_textarea_field($group['note'] ?? '');

            foreach ($days as $slug => $label) {
                $row = isset($group['days'][$slug]) && is_array($group['days'][$slug]) ? $group['days'][$slug] : [];
                $open = $this->sanitize_time_value($row['open'] ?? '');
                $close = $this->sanitize_time_value($row['close'] ?? '');
                if ($open !== '' && $close !== '' && strcmp($open, $close) > 0) {
                    $close = '';
                }
                $output[$type]['days'][$slug] = [
                    'closed' => ! empty($row['closed']) ? 1 : 0,
                    'open'   => $open,
                    'close'  => $close,
                    'note'   => sanitize_text_field($row['note'] ?? ''),
                ];
            }
        }

        $exceptions = isset($input['exceptions']) && is_array($input['exceptions']) ? array_values($input['exceptions']) : [];
        $exceptions = array_slice($exceptions, 0, 20);
        $clean_exceptions = [];
        foreach ($exceptions as $item) {
            if (! is_array($item)) {
                continue;
            }

            // Special days are stored as typed semantic exceptions. This keeps
            // editor input simple while giving frontend renderers stable meaning
            // for status text, badges and variant-specific styling.
            $date = $this->sanitize_date_value($item['date'] ?? '');
            $type = isset($item['type']) ? (string) $item['type'] : 'public';
            if (! in_array($type, ['public', 'office', 'both'], true)) {
                $type = 'public';
            }
            $open = $this->sanitize_time_value($item['open'] ?? '');
            $close = $this->sanitize_time_value($item['close'] ?? '');
            if ($open !== '' && $close !== '' && strcmp($open, $close) > 0) {
                $close = '';
            }
            $kind = $this->normalize_exception_kind((string) ($item['kind'] ?? ''), ! empty($item['closed']));
            if ($kind === 'closed') {
                $open = '';
                $close = '';
            }
            if ($kind === 'by_appointment' && $open === '' && $close === '') {
                $open = '';
                $close = '';
            }

            $row = [
                'date'   => $date,
                'type'   => $type,
                'kind'   => $kind,
                'closed' => $kind === 'closed' ? 1 : (! empty($item['closed']) ? 1 : 0),
                'open'   => $open,
                'close'  => $close,
                'label'  => sanitize_text_field($item['label'] ?? ''),
                'note'   => sanitize_text_field($item['note'] ?? ''),
            ];
            if ($row['date'] === '') {
                continue;
            }
            $clean_exceptions[] = $row;
        }
        $output['exceptions'] = $clean_exceptions;
        $this->bump_visit_cache_version();

        return $output;
    }

    public function sanitize_accessibility($input): array {
        $input = is_array($input) ? $input : [];
        return [
            'wheelchair'        => ! empty($input['wheelchair']) ? 1 : 0,
            'accessible_toilet' => ! empty($input['accessible_toilet']) ? 1 : 0,
            'elevator'          => ! empty($input['elevator']) ? 1 : 0,
            'parking'           => ! empty($input['parking']) ? 1 : 0,
            'companion'         => ! empty($input['companion']) ? 1 : 0,
            'note'              => sanitize_textarea_field($input['note'] ?? ''),
        ];
    }

    public function sanitize_prices($input): array {
        $input = is_array($input) ? $input : [];
        return [
            'adult'   => sanitize_text_field($input['adult'] ?? ''),
            'reduced' => sanitize_text_field($input['reduced'] ?? ''),
            'group'   => sanitize_text_field($input['group'] ?? ''),
            'school'  => sanitize_text_field($input['school'] ?? ''),
            'tour'    => sanitize_text_field($input['tour'] ?? ''),
            'rental'  => sanitize_text_field($input['rental'] ?? ''),
            'note'    => sanitize_textarea_field($input['note'] ?? ''),
        ];
    }

    public function sanitize_faq($input): array {
        $input = is_array($input) ? $input : [];
        $items = isset($input['items']) && is_array($input['items']) ? array_values($input['items']) : [];
        $items = array_slice($items, 0, 10);

        $clean = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $question = sanitize_text_field($item['question'] ?? '');
            $answer = wp_kses_post($item['answer'] ?? '');
            if ($question === '' && trim(wp_strip_all_tags($answer)) === '') {
                continue;
            }
            $clean[] = ['question' => $question, 'answer' => $answer];
        }

        return ['items' => $clean];
    }

    public function sanitize_mission_statement($input): array {
        $input = is_array($input) ? $input : [];
        return [
            'content' => sanitize_textarea_field($input['content'] ?? ''),
        ];
    }

    private function sanitize_time_value($value): string {
        $value = sanitize_text_field((string) $value);
        if (preg_match('/^([0-1]?[0-9]|2[0-3]):([0-5][0-9])$/', $value, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }
        return '';
    }

    private function sanitize_date_value($value): string {
        $value = sanitize_text_field((string) $value);
        if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m)) {
            return '';
        }
        $year = (int) $m[1];
        $month = (int) $m[2];
        $day = (int) $m[3];
        if (! checkdate($month, $day, $year)) {
            return '';
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    private function normalize_visit_type(string $type): string {
        return $type === 'office' ? 'office' : 'public';
    }

    /**
     * Rendering variants are intentionally separated from data semantics.
     * The same structured data can therefore be exposed as footer output,
     * a front-page card, an info panel or a dense schedule without changing
     * how editors enter address, hours or special-day rows.
     */
    private function normalize_visit_variant(string $variant, string $domain = 'hours'): string {
        $variant = sanitize_key($variant);
        $allowed = [
            'hours' => ['compact', 'full', 'inline', 'footer', 'front-card', 'info-panel'],
            'status' => ['default', 'footer', 'front-card', 'info-panel'],
            'exceptions' => ['list', 'footer', 'compact'],
            'contact' => ['card', 'footer', 'front-card', 'info-panel', 'compact-row'],
            'visit_info' => ['info-panel'],
        ];
        $fallback = [
            'hours' => 'compact',
            'status' => 'default',
            'exceptions' => 'list',
            'contact' => 'card',
            'visit_info' => 'info-panel',
        ];

        if (!isset($allowed[$domain]) || !in_array($variant, $allowed[$domain], true)) {
            return $fallback[$domain] ?? 'compact';
        }

        return $variant;
    }

    /**
     * Skin is a pure visual hook. Theme CSS may style the same semantic output
     * differently without introducing new data structures or new block types.
     */
    private function normalize_output_skin(string $skin): string {
        $skin = sanitize_key($skin);
        $allowed = ['default', 'light', 'dark', 'muted', 'front', 'accent-red'];
        return in_array($skin, $allowed, true) ? $skin : 'default';
    }

    private function normalize_info_panel_accent(string $accent): string {
        $accent = sanitize_key($accent);
        $allowed = ['red', 'green', 'blue', 'yellow', 'brown'];
        return in_array($accent, $allowed, true) ? $accent : 'green';
    }

    private function normalize_info_panel_surface(string $surface): string {
        $surface = sanitize_key($surface);
        $allowed = ['light', 'dark'];
        return in_array($surface, $allowed, true) ? $surface : 'light';
    }

    /**
     * Editors choose from a small semantic vocabulary. Each value carries
     * stable meaning for frontend text, badges and future styling.
     */
    private function exception_kind_options(): array {
        return [
            'closed' => __('Geschlossen', 'industriesalon-steuerung'),
            'special_open' => __('Sonderöffnung', 'industriesalon-steuerung'),
            'extended' => __('Länger geöffnet', 'industriesalon-steuerung'),
            'reduced' => __('Verkürzte Zeit', 'industriesalon-steuerung'),
            'event_day' => __('Veranstaltungstag', 'industriesalon-steuerung'),
            'by_appointment' => __('Nur nach Vereinbarung', 'industriesalon-steuerung'),
        ];
    }

    private function normalize_exception_kind(string $kind, bool $legacy_closed = false): string {
        $kind = sanitize_key($kind);
        if ($legacy_closed) {
            return 'closed';
        }

        $allowed = array_keys($this->exception_kind_options());
        return in_array($kind, $allowed, true) ? $kind : 'special_open';
    }

    private function resolve_exception_kind(array $exception): string {
        return $this->normalize_exception_kind((string) ($exception['kind'] ?? ''), !empty($exception['closed']));
    }

    private function get_exception_kind_meta(string $kind): array {
        $labels = $this->exception_kind_options();

        $map = [
            'closed' => [
                'status' => 'closed',
                'badge' => __('geschlossen', 'industriesalon-steuerung'),
                'summary' => $labels['closed'],
            ],
            'special_open' => [
                'status' => 'open',
                'badge' => __('sonder', 'industriesalon-steuerung'),
                'summary' => $labels['special_open'],
            ],
            'extended' => [
                'status' => 'open',
                'badge' => __('verlängert', 'industriesalon-steuerung'),
                'summary' => $labels['extended'],
            ],
            'reduced' => [
                'status' => 'open',
                'badge' => __('verkürzt', 'industriesalon-steuerung'),
                'summary' => $labels['reduced'],
            ],
            'event_day' => [
                'status' => 'open',
                'badge' => __('event', 'industriesalon-steuerung'),
                'summary' => $labels['event_day'],
            ],
            'by_appointment' => [
                'status' => 'open',
                'badge' => __('vereinbarung', 'industriesalon-steuerung'),
                'summary' => $labels['by_appointment'],
            ],
        ];

        return $map[$kind] ?? $map['special_open'];
    }

    private function visit_type_label(string $type): string {
        return $this->normalize_visit_type($type) === 'office'
            ? __('Bürozeiten', 'industriesalon-steuerung')
            : __('Besuchszeiten', 'industriesalon-steuerung');
    }

    private function current_visit_moment(): DateTimeImmutable {
        return new DateTimeImmutable('now', wp_timezone());
    }

    private function visit_cache_version(): int {
        return max(1, (int) get_option(self::OPTION_VISIT_CACHE_VERSION, 1));
    }

    private function bump_visit_cache_version(): void {
        update_option(self::OPTION_VISIT_CACHE_VERSION, $this->visit_cache_version() + 1, false);
    }

    private function visit_cache_key(string $scope, array $args = []): string {
        return 'iss_visit_' . $this->visit_cache_version() . '_' . md5(wp_json_encode([$scope, $args]));
    }

    private function get_visit_schedule(): array {
        $cache_key = $this->visit_cache_key('schedule');
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $hours = get_option(self::OPTION_HOURS, $this->default_hours());
        $schedule = is_array($hours) ? $hours : $this->default_hours();
        set_transient($cache_key, $schedule, 10 * MINUTE_IN_SECONDS);
        return $schedule;
    }

    private function visit_group_for_type(array $schedule, string $type): array {
        $type = $this->normalize_visit_type($type);
        $group = isset($schedule[$type]) && is_array($schedule[$type]) ? $schedule[$type] : [];
        $group = wp_parse_args($group, ['note' => '', 'days' => []]);
        $days = $this->days();

        $rows = [];
        foreach ($days as $slug => $label) {
            $rows[$slug] = wp_parse_args($group['days'][$slug] ?? [], ['closed' => 0, 'open' => '', 'close' => '', 'note' => '']);
        }

        return ['note' => (string) $group['note'], 'days' => $rows];
    }

    private function get_visit_day(string $type, DateTimeInterface $date): array {
        $type = $this->normalize_visit_type($type);
        $cache_key = $this->visit_cache_key('day', ['type' => $type, 'date' => $date->format('Y-m-d'), 'time' => $date->format('H:i')]);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = $this->resolve_visit_day($this->get_visit_schedule(), $type, $date);
        set_transient($cache_key, $result, 2 * MINUTE_IN_SECONDS);
        return $result;
    }

    private function resolve_visit_day(array $schedule, string $type, DateTimeInterface $date): array {
        $type = $this->normalize_visit_type($type);
        $date_key = $date->format('Y-m-d');
        $day_slug = strtolower($date->format('l'));
        $days_map = [
            'monday' => 'monday',
            'tuesday' => 'tuesday',
            'wednesday' => 'wednesday',
            'thursday' => 'thursday',
            'friday' => 'friday',
            'saturday' => 'saturday',
            'sunday' => 'sunday',
        ];
        $slug = $days_map[$day_slug] ?? 'monday';

        $group = $this->visit_group_for_type($schedule, $type);
        $weekly = $group['days'][$slug] ?? ['closed' => 0, 'open' => '', 'close' => '', 'note' => ''];
        $exception = $this->find_visit_exception($schedule['exceptions'] ?? [], $type, $date_key);
        $is_today = $date_key === $this->current_visit_moment()->format('Y-m-d');
        $day_label = $is_today ? __('Heute', 'industriesalon-steuerung') : wp_date('d.m.Y', $date->getTimestamp());

        if (is_array($exception)) {
            $kind = $this->resolve_exception_kind($exception);
            $meta = $this->get_exception_kind_meta($kind);
            $closed = $kind === 'closed' || ($kind !== 'by_appointment' && ((string) ($exception['open'] ?? '') === '' && (string) ($exception['close'] ?? '') === ''));
            $ranges = ($closed || $kind === 'by_appointment') ? [] : [[
                'open'  => (string) ($exception['open'] ?? ''),
                'close' => (string) ($exception['close'] ?? ''),
            ]];
            $label = $this->build_exception_status_label($exception, $kind, $day_label, $is_today, $ranges);
            return [
                'status'  => (string) $meta['status'],
                'source'  => 'exception',
                'kind'    => $kind,
                'date'    => $date_key,
                'label'   => $label,
                'ranges'  => $ranges,
                'note'    => (string) ($exception['note'] ?? ''),
                'date_label' => $day_label,
            ];
        }

        $closed = ! empty($weekly['closed']) || ((string) ($weekly['open'] ?? '') === '' && (string) ($weekly['close'] ?? '') === '');
        $ranges = $closed ? [] : [[
            'open'  => (string) ($weekly['open'] ?? ''),
            'close' => (string) ($weekly['close'] ?? ''),
        ]];
        $label = $closed
            ? ($is_today ? __('Heute geschlossen', 'industriesalon-steuerung') : sprintf(__('%s geschlossen', 'industriesalon-steuerung'), $day_label))
            : ($is_today ? __('Heute geöffnet', 'industriesalon-steuerung') : sprintf(__('%s geöffnet', 'industriesalon-steuerung'), $day_label));
        if (! $closed && ! empty($ranges[0]['close'])) {
            $label .= ' ' . sprintf(__('bis %s Uhr', 'industriesalon-steuerung'), $this->format_visit_clock((string) $ranges[0]['close']));
        }

        return [
            'status'  => $closed ? 'closed' : 'open',
            'source'  => 'regular',
            'kind'    => 'regular',
            'date'    => $date_key,
            'label'   => $label,
            'ranges'  => $ranges,
            'note'    => (string) ($weekly['note'] ?? ''),
            'date_label' => $day_label,
        ];
    }

    private function find_visit_exception(array $exceptions, string $type, string $date): ?array {
        foreach ($exceptions as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['date'] ?? '') !== $date) {
                continue;
            }
            $item_type = isset($item['type']) ? (string) $item['type'] : 'public';
            if ($item_type !== 'both' && $this->normalize_visit_type($item_type) !== $type) {
                continue;
            }
            return $item;
        }
        return null;
    }

    private function format_visit_clock(string $time): string {
        if ($time === '') {
            return '';
        }

        $time = trim($time);
        if (! preg_match('/^(?<hour>\d{1,2}):(?<minute>\d{2})/', $time, $matches)) {
            return $time;
        }

        $hour = (int) $matches['hour'];
        $minute = (int) $matches['minute'];

        return $minute === 0
            ? (string) $hour
            : sprintf('%02d:%02d', $hour, $minute);
    }

    private function format_visit_time_ranges(array $ranges): string {
        $ranges = array_values(array_filter($ranges, static function ($range) {
            return is_array($range);
        }));
        if (empty($ranges)) {
            return __('geschlossen', 'industriesalon-steuerung');
        }

        $parts = [];
        foreach ($ranges as $range) {
            $open = $this->format_visit_clock((string) ($range['open'] ?? ''));
            $close = $this->format_visit_clock((string) ($range['close'] ?? ''));
            if ($open === '' && $close === '') {
                continue;
            }
            if ($open !== '' && $close !== '') {
                $parts[] = $open . '–' . $close . ' Uhr';
            } elseif ($open !== '') {
                $parts[] = $open . ' Uhr';
            } else {
                $parts[] = $close . ' Uhr';
            }
        }

        return empty($parts) ? __('geschlossen', 'industriesalon-steuerung') : implode('; ', $parts);
    }

    private function build_exception_status_label(array $exception, string $kind, string $day_label, bool $is_today, array $ranges): string {
        $custom = trim((string) ($exception['label'] ?? ''));
        $kind_meta = $this->get_exception_kind_meta($kind);
        $range_label = $this->format_visit_time_ranges($ranges);

        if ($custom !== '') {
            if ($is_today) {
                return $day_label . ' ' . $custom;
            }
            return $day_label . ' ' . $custom;
        }

        if ($kind === 'closed') {
            return $is_today ? __('Heute geschlossen', 'industriesalon-steuerung') : sprintf(__('%s geschlossen', 'industriesalon-steuerung'), $day_label);
        }

        if ($kind === 'by_appointment') {
            return $is_today ? __('Heute nur nach Vereinbarung', 'industriesalon-steuerung') : sprintf(__('%s nur nach Vereinbarung', 'industriesalon-steuerung'), $day_label);
        }

        $prefix_map = [
            'special_open' => $is_today ? __('Heute Sonderöffnung', 'industriesalon-steuerung') : sprintf(__('%s Sonderöffnung', 'industriesalon-steuerung'), $day_label),
            'extended' => $is_today ? __('Heute länger geöffnet', 'industriesalon-steuerung') : sprintf(__('%s länger geöffnet', 'industriesalon-steuerung'), $day_label),
            'reduced' => $is_today ? __('Heute verkürzte Öffnungszeit', 'industriesalon-steuerung') : sprintf(__('%s verkürzte Öffnungszeit', 'industriesalon-steuerung'), $day_label),
            'event_day' => $is_today ? __('Heute Veranstaltungstag', 'industriesalon-steuerung') : sprintf(__('%s Veranstaltungstag', 'industriesalon-steuerung'), $day_label),
        ];

        $prefix = $prefix_map[$kind] ?? ($is_today ? (string) $kind_meta['summary'] : $day_label . ' ' . (string) $kind_meta['summary']);
        if ($range_label !== '' && $range_label !== __('geschlossen', 'industriesalon-steuerung')) {
            return $prefix . ': ' . $range_label;
        }

        return $prefix;
    }

    private function format_visit_day_range(array $days): string {
        $map = [
            'monday' => __('Mo', 'industriesalon-steuerung'),
            'tuesday' => __('Di', 'industriesalon-steuerung'),
            'wednesday' => __('Mi', 'industriesalon-steuerung'),
            'thursday' => __('Do', 'industriesalon-steuerung'),
            'friday' => __('Fr', 'industriesalon-steuerung'),
            'saturday' => __('Sa', 'industriesalon-steuerung'),
            'sunday' => __('So', 'industriesalon-steuerung'),
        ];
        $order = array_keys($map);
        $indexes = [];
        foreach ($days as $day) {
            $index = array_search($day, $order, true);
            if ($index !== false) {
                $indexes[] = $index;
            }
        }
        sort($indexes);
        $chunks = [];
        $current = [];
        foreach ($indexes as $index) {
            if (empty($current) || $index === end($current) + 1) {
                $current[] = $index;
                continue;
            }
            $chunks[] = $current;
            $current = [$index];
        }
        if (! empty($current)) {
            $chunks[] = $current;
        }

        $labels = [];
        foreach ($chunks as $chunk) {
            $start = $order[(int) $chunk[0]];
            $end = $order[(int) end($chunk)];
            $labels[] = count($chunk) > 1 ? $map[$start] . '–' . $map[$end] : $map[$start];
        }

        return implode(', ', $labels);
    }

    private function build_visit_summary(array $group, bool $omit_closed = false): string {
        $rows = $this->group_visit_rows($group['days'] ?? [], $omit_closed);
        $parts = [];
        foreach ($rows as $row) {
            $parts[] = $row['days'] . ', ' . $row['times'];
        }
        return implode('; ', $parts);
    }

    private function group_visit_rows(array $days, bool $omit_closed = false): array {
        $order = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $groups = [];
        $current = null;

        foreach ($order as $slug) {
            $row = wp_parse_args($days[$slug] ?? [], ['closed' => 0, 'open' => '', 'close' => '', 'note' => '']);
            $signature = implode('|', [
                ! empty($row['closed']) ? '1' : '0',
                (string) $row['open'],
                (string) $row['close'],
                (string) $row['note'],
            ]);

            if ($current !== null && $current['signature'] === $signature) {
                $current['days'][] = $slug;
                $current['times'] = $this->format_visit_time_ranges([[
                    'open'  => (string) $row['open'],
                    'close' => (string) $row['close'],
                ]]);
                $groups[count($groups) - 1] = $current;
                continue;
            }

            $current = [
                'signature' => $signature,
                'days'      => [$slug],
                'times'     => $this->format_visit_time_ranges([[
                    'open'  => (string) $row['open'],
                    'close' => (string) $row['close'],
                ]]),
                'note'      => (string) $row['note'],
            ];
            $groups[] = $current;
        }

        $rows = [];
        foreach ($groups as $group) {
            if ($omit_closed && $group['times'] === __('geschlossen', 'industriesalon-steuerung')) {
                continue;
            }
            $rows[] = [
                'days'  => $this->format_visit_day_range($group['days']),
                'times' => $group['times'],
            ];
        }

        return $rows;
    }

    private function get_relevant_visit_exceptions(string $type): array {
        $type = $this->normalize_visit_type($type);
        $today_key = $this->current_visit_moment()->format('Y-m-d');
        $cache_key = $this->visit_cache_key('exceptions', ['type' => $type, 'from' => $today_key]);
        $cached = get_transient($cache_key);
        if (is_array($cached)) {
            return $cached;
        }

        $schedule = $this->get_visit_schedule();
        $exceptions = isset($schedule['exceptions']) && is_array($schedule['exceptions']) ? $schedule['exceptions'] : [];
        $today = $this->current_visit_moment()->setTime(0, 0);
        $limit = $today->modify('+30 days');
        $list = [];

        foreach ($exceptions as $item) {
            if (! is_array($item)) {
                continue;
            }
            $date = $this->sanitize_date_value((string) ($item['date'] ?? ''));
            if ($date === '') {
                continue;
            }
            $moment = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date . ' 00:00:00', wp_timezone());
            if (! ($moment instanceof DateTimeImmutable)) {
                continue;
            }
            if ($moment < $today || $moment > $limit) {
                continue;
            }

            $item_type = isset($item['type']) ? (string) $item['type'] : 'public';
            if ($item_type !== 'both' && $this->normalize_visit_type($item_type) !== $type) {
                continue;
            }

            $kind = $this->resolve_exception_kind($item);
            $meta = $this->get_exception_kind_meta($kind);
            $closed = $kind === 'closed' || ($kind !== 'by_appointment' && ((string) ($item['open'] ?? '') === '' && (string) ($item['close'] ?? '') === ''));
            $ranges = ($closed || $kind === 'by_appointment') ? [] : [[
                'open'  => (string) ($item['open'] ?? ''),
                'close' => (string) ($item['close'] ?? ''),
            ]];
            $custom_label = trim((string) ($item['label'] ?? ''));
            $label = $custom_label !== '' ? $custom_label : ($kind === 'by_appointment' ? __('nur nach Vereinbarung', 'industriesalon-steuerung') : ($closed ? __('geschlossen', 'industriesalon-steuerung') : $this->format_visit_time_ranges($ranges)));

            $list[] = [
                'date'       => $date,
                'date_label' => wp_date('d.m.Y', $moment->getTimestamp()),
                'label'      => $label,
                'status'     => (string) $meta['status'],
                'kind'       => $kind,
                'open'       => (string) ($item['open'] ?? ''),
                'close'      => (string) ($item['close'] ?? ''),
                'note'       => (string) ($item['note'] ?? ''),
            ];
        }

        usort($list, static function ($a, $b) {
            return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
        });

        set_transient($cache_key, $list, 10 * MINUTE_IN_SECONDS);
        return $list;
    }

    private function build_visit_exception_list(string $type, array $options = []): string {
        $list = $this->get_relevant_visit_exceptions($type);
        if (empty($list)) {
            return '';
        }

        $variant = $this->normalize_visit_variant((string) ($options['variant'] ?? 'list'), 'exceptions');
        $skin = $this->normalize_output_skin((string) ($options['skin'] ?? 'default'));

        ob_start();
        ?>
        <ul class="iss-visit-exceptions__list iss-visit-exceptions__list--<?php echo esc_attr($variant); ?> iss-visit-exceptions__list--skin-<?php echo esc_attr($skin); ?>" data-variant="<?php echo esc_attr($variant); ?>" data-skin="<?php echo esc_attr($skin); ?>">
            <?php foreach ($list as $item) : ?>
                <li class="iss-visit-exceptions__item iss-visit-exceptions__item--<?php echo esc_attr(sanitize_html_class((string) ($item['status'] ?? 'special'))); ?> iss-visit-exceptions__item--kind-<?php echo esc_attr(sanitize_html_class((string) ($item['kind'] ?? 'special_open'))); ?>" data-status="<?php echo esc_attr((string) ($item['status'] ?? 'special')); ?>" data-kind="<?php echo esc_attr((string) ($item['kind'] ?? 'special_open')); ?>">
                    <span class="iss-visit-exceptions__date"><?php echo esc_html((string) $item['date_label']); ?></span>
                    <span class="iss-visit-exceptions__time"><?php echo esc_html((string) $item['label']); ?></span>
                    <?php if (! empty($item['note']) && $variant !== 'footer') : ?>
                        <span class="iss-visit-exceptions__note"><?php echo esc_html((string) $item['note']); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return (string) ob_get_clean();
    }

    public function get_all_data(): array {
        return [
            'general'       => get_option(self::OPTION_GENERAL, $this->default_general()),
            'contact'       => get_option(self::OPTION_CONTACT, $this->default_contact()),
            'maps'          => get_option(self::OPTION_MAPS, $this->default_maps()),
            'hours'         => get_option(self::OPTION_HOURS, $this->default_hours()),
            'accessibility' => get_option(self::OPTION_ACCESSIBILITY, $this->default_accessibility()),
            'prices'        => get_option(self::OPTION_PRICES, $this->default_prices()),
            'faq'           => get_option(self::OPTION_FAQ, $this->default_faq()),
            'mission_statement' => get_option(self::OPTION_MISSION_STATEMENT, $this->default_mission_statement()),
        ];
    }

    public function get_field_value(string $key, $default = '') {
        $key = trim($key);
        if ($key === '') {
            return $default;
        }

        $aliases = [
            'address.street'      => 'general.street',
            'address.postal_code' => 'general.postal_code',
            'address.city'        => 'general.city',
            'address.district'    => 'general.district',
        ];

        if (isset($aliases[$key])) {
            $key = $aliases[$key];
        }

        if ($key === 'address.full' || $key === 'general.address_full') {
            $full = $this->compose_full_address();
            return $full !== '' ? $full : $default;
        }

        if (strpos($key, '.') === false) {
            return $default;
        }

        [$section, $field] = explode('.', $key, 2);
        $data = $this->get_all_data();
        if (! isset($data[$section]) || ! is_array($data[$section])) {
            return $default;
        }

        return $data[$section][$field] ?? $default;
    }

    private function auto_link_for_key(string $key, string $value): string {
        if (str_contains($key, 'email')) {
            return 'mailto:' . antispambot($value);
        }
        if (str_contains($key, 'phone')) {
            return 'tel:' . $this->normalize_phone_href($value);
        }
        if (str_contains($key, 'url') || str_contains($key, 'website') || str_contains($key, 'instagram') || str_contains($key, 'facebook') || str_contains($key, 'youtube') || str_contains($key, 'maps')) {
            return $this->normalize_public_url($value);
        }
        return '';
    }

    private function normalize_public_url(string $value): string {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        if (preg_match('#^(https?):([^/])#i', $value, $matches) === 1) {
            $value = $matches[1] . '://' . substr($value, strlen($matches[1]) + 1);
        } elseif (! preg_match('#^[a-z][a-z0-9+\-.]*://#i', $value)) {
            $value = 'https://' . ltrim($value, '/');
        }

        return esc_url_raw($value);
    }

    private function normalize_phone_href(string $value): string {
        return preg_replace('/[^0-9\+]/', '', $value) ?: '';
    }

    private function compose_address_line(): string {
        $general = get_option(self::OPTION_GENERAL, $this->default_general());
        return trim(implode(', ', array_filter([$general['street'] ?? '', $general['district'] ?? ''])));
    }

    private function compose_full_address(): string {
        $general = get_option(self::OPTION_GENERAL, $this->default_general());
        $parts = array_filter([$general['street'] ?? '', trim(($general['postal_code'] ?? '') . ' ' . ($general['city'] ?? '')), $general['district'] ?? '']);
        return implode(', ', $parts);
    }

    private function format_hours_row(array $row): string {
        $row = wp_parse_args($row, ['closed' => 0, 'open' => '', 'close' => '', 'note' => '']);
        if (! empty($row['closed'])) {
            return ! empty($row['note']) ? (string) $row['note'] : __('geschlossen', 'industriesalon-steuerung');
        }

        $time = trim((string) $row['open']);
        if ($time !== '' && ! empty($row['close'])) {
            $time .= '–' . (string) $row['close'];
        } elseif (! empty($row['close'])) {
            $time = (string) $row['close'];
        }

        if ($time === '' && ! empty($row['note'])) {
            return (string) $row['note'];
        }
        if ($time !== '' && ! empty($row['note'])) {
            return $time . ' · ' . (string) $row['note'];
        }
        return $time !== '' ? $time : '—';
    }

    private function days(): array {
        return [
            'monday'    => __('Montag', 'industriesalon-steuerung'),
            'tuesday'   => __('Dienstag', 'industriesalon-steuerung'),
            'wednesday' => __('Mittwoch', 'industriesalon-steuerung'),
            'thursday'  => __('Donnerstag', 'industriesalon-steuerung'),
            'friday'    => __('Freitag', 'industriesalon-steuerung'),
            'saturday'  => __('Samstag', 'industriesalon-steuerung'),
            'sunday'    => __('Sonntag', 'industriesalon-steuerung'),
        ];
    }

    private function default_general(): array {
        return ['site_name' => '', 'tagline' => '', 'street' => '', 'postal_code' => '', 'city' => '', 'district' => '', 'arrival' => '', 'visit_note' => ''];
    }

    private function default_contact(): array {
        return ['phone' => '', 'email' => '', 'booking_email' => '', 'contact_person' => '', 'website' => '', 'booking_url' => '', 'instagram' => '', 'facebook' => '', 'youtube' => ''];
    }

    private function render_footer_brand(string $title = ''): string {
        $general = get_option(self::OPTION_GENERAL, $this->default_general());
        $contact = get_option(self::OPTION_CONTACT, $this->default_contact());
        $tagline = trim((string) ($general['tagline'] ?? ''));
        $social_links = $this->footer_social_links($contact);

        if ($title === '' && $tagline === '' && $social_links === '') {
            return '';
        }

        ob_start();
        ?>
        <div class="iss-site-footer__plugin iss-site-footer__plugin--brand">
            <?php if ($title !== '') : ?>
                <h3 class="iss-site-footer__title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            <?php if ($tagline !== '') : ?>
                <p class="iss-site-footer__tagline"><?php echo esc_html($tagline); ?></p>
            <?php endif; ?>
            <?php if ($social_links !== '') : ?>
                <?php echo $social_links; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_footer_visit(string $title = ''): string {
        $general = get_option(self::OPTION_GENERAL, $this->default_general());
        $maps = get_option(self::OPTION_MAPS, $this->default_maps());
        $address_lines = array_filter([
            trim((string) ($general['site_name'] ?? '')),
            trim((string) ($general['street'] ?? '')),
            trim((string) (($general['postal_code'] ?? '') . ' ' . ($general['city'] ?? ''))),
            trim((string) ($general['district'] ?? '')),
        ]);
        $arrival = trim((string) ($general['arrival'] ?? ''));
        $accessibility = $this->visit_info_accessibility_summary();
        $maps_url = $this->normalize_public_url((string) ($maps['google_maps_url'] ?? ''));

        if ($title === '' && empty($address_lines) && $arrival === '' && $accessibility === '' && $maps_url === '') {
            return '';
        }

        ob_start();
        ?>
        <div class="iss-site-footer__plugin iss-site-footer__plugin--visit">
            <?php if ($title !== '') : ?>
                <h3 class="iss-site-footer__title"><?php echo esc_html($title); ?></h3>
            <?php endif; ?>
            <?php if (! empty($address_lines)) : ?>
                <div class="iss-site-footer__fact iss-site-footer__fact--address">
                    <p class="iss-site-footer__text"><?php echo nl2br(esc_html(implode("\n", $address_lines))); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($arrival !== '') : ?>
                <div class="iss-site-footer__fact iss-site-footer__fact--transport">
                    <p class="iss-site-footer__fact-label"><?php esc_html_e('ÖPNV', 'industriesalon-steuerung'); ?></p>
                    <p class="iss-site-footer__text iss-site-footer__transport"><?php echo nl2br(esc_html($arrival)); ?></p>
                </div>
            <?php endif; ?>
            <?php if ($accessibility !== '') : ?>
                <div class="iss-site-footer__access"><?php echo $this->render_footer_accessibility_badge(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
            <?php endif; ?>
            <?php if ($maps_url !== '') : ?>
                <p class="iss-site-footer__map-link-wrap"><a class="iss-site-footer__map-link" href="<?php echo esc_url($maps_url); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Google Maps', 'industriesalon-steuerung'); ?></a></p>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    private function render_footer_contact(string $title, array $contact): string {
        $items = [];

        if (! empty($contact['contact_person'])) {
            $items[] = '<p class="iss-site-footer__text"><strong>' . esc_html__('Ansprechperson', 'industriesalon-steuerung') . ':</strong><br>' . esc_html((string) $contact['contact_person']) . '</p>';
        }
        if (! empty($contact['email'])) {
            $items[] = '<p class="iss-site-footer__text"><strong>' . esc_html__('E-Mail', 'industriesalon-steuerung') . ':</strong><br><a href="' . esc_url('mailto:' . antispambot((string) $contact['email'])) . '">' . esc_html((string) $contact['email']) . '</a></p>';
        }
        if (! empty($contact['phone'])) {
            $items[] = '<p class="iss-site-footer__text"><strong>' . esc_html__('Telefon', 'industriesalon-steuerung') . ':</strong><br><a href="' . esc_url('tel:' . $this->normalize_phone_href((string) $contact['phone'])) . '">' . esc_html((string) $contact['phone']) . '</a></p>';
        }
        if (! empty($contact['booking_email'])) {
            $items[] = '<p class="iss-site-footer__text"><strong>' . esc_html__('Buchungen', 'industriesalon-steuerung') . ':</strong><br><a href="' . esc_url('mailto:' . antispambot((string) $contact['booking_email'])) . '">' . esc_html((string) $contact['booking_email']) . '</a></p>';
        }
        if (! empty($contact['website'])) {
            $items[] = '<p class="iss-site-footer__text"><a href="' . esc_url($this->normalize_public_url((string) $contact['website'])) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Website', 'industriesalon-steuerung') . '</a></p>';
        }

        if (empty($items)) {
            return '';
        }

        return '<div class="iss-site-footer__plugin iss-site-footer__plugin--contact">' . ($title !== '' ? '<h3 class="iss-site-footer__title">' . esc_html($title) . '</h3>' : '') . implode('', $items) . '</div>';
    }

    private function footer_social_links(array $contact): string {
        $services = [
            'facebook' => __('Facebook', 'industriesalon-steuerung'),
            'instagram' => __('Instagram', 'industriesalon-steuerung'),
            'youtube' => __('YouTube', 'industriesalon-steuerung'),
        ];
        $items = [];

        foreach ($services as $key => $label) {
            $url = $this->normalize_public_url((string) ($contact[$key] ?? ''));
            if ($url === '') {
                continue;
            }
            $items[] = '<li class="iss-site-footer__social-item"><a class="iss-site-footer__social-link" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr($label) . '">' . $this->footer_social_icon($key) . '<span class="screen-reader-text">' . esc_html($label) . '</span></a></li>';
        }

        if (empty($items)) {
            return '';
        }

        return '<ul class="iss-site-footer__social-list">' . implode('', $items) . '</ul>';
    }

    private function footer_social_icon(string $service): string {
        $symbols = [
            'facebook' => 'iss-icon-facebook',
            'instagram' => 'iss-icon-instagram',
            'youtube' => 'iss-icon-youtube',
        ];

        if (! isset($symbols[$service])) {
            return '';
        }

        $sprite_url = trailingslashit(get_stylesheet_directory_uri()) . 'assets/img/iss-social-sprite.svg';
        $symbol = $sprite_url . '#' . $symbols[$service];

        return '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><use href="' . esc_url($symbol) . '" xlink:href="' . esc_url($symbol) . '"></use></svg>';
    }

    private function render_footer_accessibility_badge(): string {
        $badge_url = plugins_url('assets/barrierefreiheit-geprueft-badge.webp', __FILE__);

        return '<img class="iss-site-footer__access-badge" src="' . esc_url($badge_url) . '" alt="' . esc_attr__('Barrierefreiheit geprüft', 'industriesalon-steuerung') . '" loading="lazy" decoding="async">';
    }

    private function default_maps(): array {
        return ['google_maps_url' => '', 'google_maps_embed' => ''];
    }

    private function default_hours(): array {
        $days = [];
        foreach (array_keys($this->days()) as $slug) {
            $days[$slug] = ['closed' => 0, 'open' => '', 'close' => '', 'note' => ''];
        }
        return ['public' => ['note' => '', 'days' => $days], 'office' => ['note' => '', 'days' => $days], 'exceptions' => []];
    }

    private function default_accessibility(): array {
        return ['wheelchair' => 0, 'accessible_toilet' => 0, 'elevator' => 0, 'parking' => 0, 'companion' => 0, 'note' => ''];
    }

    private function default_prices(): array {
        return ['adult' => '', 'reduced' => '', 'group' => '', 'school' => '', 'tour' => '', 'rental' => '', 'note' => ''];
    }

    private function default_faq(): array {
        return ['items' => []];
    }

    private function default_mission_statement(): array {
        return ['content' => ''];
    }
}

register_activation_hook(__FILE__, ['Industriesalon_Steuerung', 'activate']);
register_deactivation_hook(__FILE__, ['Industriesalon_Steuerung', 'deactivate']);
Industriesalon_Steuerung::instance();
