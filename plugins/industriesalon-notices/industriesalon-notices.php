<?php
/**
 * Plugin Name: Industriesalon Notices
 * Description: Zentrale Hinweise für Startseiten-Banner, Website-Hinweise und Admin-Hinweise.
 * Version: 0.1.2
 * Author: OpenAI
 * Text Domain: industriesalon-notices
 */

if (! defined('ABSPATH')) {
    exit;
}

final class Industriesalon_Notices {
    private const VERSION = '0.1.2';
    private const POST_TYPE = 'iss_notice';
    private const NONCE = 'iss_notice_meta_nonce';

    public function __construct() {
        add_action('plugins_loaded', [$this, 'load_textdomain']);
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_blocks']);
        add_action('admin_menu', [$this, 'register_fallback_submenu'], 99);
        add_action('add_meta_boxes', [$this, 'register_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta_boxes']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('pre_get_posts', [$this, 'handle_admin_sorting']);
        add_filter('use_block_editor_for_post_type', [$this, 'disable_block_editor'], 10, 2);

        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-' . self::POST_TYPE . '_sortable_columns', [$this, 'sortable_columns']);

        add_action('admin_notices', [$this, 'render_admin_notices']);
        add_shortcode('iss_notice', [$this, 'shortcode']);
    }

    public function register_blocks(): void {
        if (! function_exists('register_block_type')) {
            return;
        }

        register_block_type(__DIR__ . '/blocks/notice-banner', [
            'render_callback' => [$this, 'render_notice_block'],
        ]);
    }

    public function load_textdomain(): void {
        load_plugin_textdomain('industriesalon-notices', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function capability(): string {
        return (string) apply_filters('iss_notices_capability', 'iss_manage_hinweise');
    }

    public function register_fallback_submenu(): void {
        if (defined('ISS_CORE_OPERATIONS_MENU_SLUG')) {
            return;
        }

        $page_title = __('Hinweise', 'industriesalon-notices');
        $menu_title = __('Hinweise', 'industriesalon-notices');
        $capability = $this->capability();
        $slug = 'edit.php?post_type=' . self::POST_TYPE;

        add_submenu_page(
            defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : 'options-general.php',
            $page_title,
            $menu_title,
            $capability,
            $slug
        );
    }

    public function register_post_type(): void {
        $labels = [
            'name'                  => __('Hinweise', 'industriesalon-notices'),
            'singular_name'         => __('Hinweis', 'industriesalon-notices'),
            'menu_name'             => __('Hinweise', 'industriesalon-notices'),
            'name_admin_bar'        => __('Hinweis', 'industriesalon-notices'),
            'add_new'               => __('Neu hinzufügen', 'industriesalon-notices'),
            'add_new_item'          => __('Hinweis hinzufügen', 'industriesalon-notices'),
            'new_item'              => __('Neuer Hinweis', 'industriesalon-notices'),
            'edit_item'             => __('Hinweis bearbeiten', 'industriesalon-notices'),
            'view_item'             => __('Hinweis ansehen', 'industriesalon-notices'),
            'all_items'             => __('Alle Hinweise', 'industriesalon-notices'),
            'search_items'          => __('Hinweise suchen', 'industriesalon-notices'),
            'not_found'             => __('Keine Hinweise gefunden.', 'industriesalon-notices'),
            'not_found_in_trash'    => __('Keine Hinweise im Papierkorb gefunden.', 'industriesalon-notices'),
        ];

        register_post_type(self::POST_TYPE, [
            'labels'             => $labels,
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => defined('ISS_CORE_OPERATIONS_MENU_SLUG') ? ISS_CORE_OPERATIONS_MENU_SLUG : true,
            'show_in_admin_bar'  => true,
            'show_in_nav_menus'  => false,
            'show_in_rest'       => false,
            'has_archive'        => false,
            'rewrite'            => false,
            'query_var'          => false,
            'menu_icon'          => 'dashicons-megaphone',
            'supports'           => ['title', 'editor', 'page-attributes'],
            'capability_type'    => ['iss_notice', 'iss_notices'],
            'capabilities'       => function_exists('iss_core_post_type_capabilities')
                ? iss_core_post_type_capabilities('iss_notice', 'iss_notices', 'create_iss_notices')
                : [],
            'map_meta_cap'       => true,
        ]);
    }

    public function disable_block_editor(bool $use_block_editor, string $post_type): bool {
        if ($post_type === self::POST_TYPE) {
            return false;
        }

        return $use_block_editor;
    }

    public function enqueue_admin_assets(string $hook): void {
        if (! in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }

        $screen = get_current_screen();
        if (! ($screen instanceof WP_Screen) || $screen->post_type !== self::POST_TYPE) {
            return;
        }

        wp_enqueue_script(
            'iss-notices-admin',
            plugins_url('assets/admin.js', __FILE__),
            [],
            self::VERSION,
            true
        );
    }

    public function handle_admin_sorting(\WP_Query $query): void {
        if (! is_admin() || ! $query->is_main_query()) {
            return;
        }

        if ($query->get('post_type') !== self::POST_TYPE) {
            return;
        }

        if ($query->get('orderby') !== 'iss_priority') {
            return;
        }

        $query->set('meta_key', 'iss_priority');
        $query->set('orderby', 'meta_value_num');
    }

    public function register_meta_boxes(): void {
        add_meta_box(
            'iss_notice_content',
            __('Inhalt', 'industriesalon-notices'),
            [$this, 'render_content_box'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'iss_notice_display',
            __('Anzeige', 'industriesalon-notices'),
            [$this, 'render_display_box'],
            self::POST_TYPE,
            'side',
            'high'
        );

        add_meta_box(
            'iss_notice_schedule',
            __('Zeitraum', 'industriesalon-notices'),
            [$this, 'render_schedule_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    public function render_content_box(\WP_Post $post): void {
        wp_nonce_field('iss_notice_save_meta', self::NONCE);

        $kicker           = $this->get_meta($post->ID, 'iss_kicker');
        $kicker_style     = $this->get_meta($post->ID, 'iss_kicker_style', 'default');
        $headline          = $this->get_meta($post->ID, 'iss_headline');
        $badge             = $this->get_meta($post->ID, 'iss_badge', 'none');
        $link_type         = $this->get_meta($post->ID, 'iss_link_type', 'none');
        $link_object_id    = (int) $this->get_meta($post->ID, 'iss_link_object_id', 0);
        $link_url_external = $this->get_meta($post->ID, 'iss_link_url_external');
        $link_label        = $this->get_meta($post->ID, 'iss_link_label');

        $posts = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        ?>
        <p>
            <label for="iss_kicker"><strong><?php esc_html_e('Kicker', 'industriesalon-notices'); ?></strong></label><br>
            <input type="text" class="widefat" id="iss_kicker" name="iss_kicker" value="<?php echo esc_attr($kicker); ?>">
            <span class="description"><?php esc_html_e('Kurze Zeile über der Überschrift. Leer lassen, wenn kein Kicker nötig ist.', 'industriesalon-notices'); ?></span>
        </p>

        <p>
            <label for="iss_kicker_style"><strong><?php esc_html_e('Kicker-Stil', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_kicker_style" name="iss_kicker_style">
                <?php foreach ($this->kicker_style_options() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($kicker_style, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php esc_html_e('Erzeugt Klassen wie iss-kicker oder iss-kicker iss-kicker--light.', 'industriesalon-notices'); ?></span>
        </p>

        <p>
            <label for="iss_headline"><strong><?php esc_html_e('Überschrift', 'industriesalon-notices'); ?></strong></label><br>
            <input type="text" class="widefat" id="iss_headline" name="iss_headline" value="<?php echo esc_attr($headline); ?>">
            <span class="description"><?php esc_html_e('Kurze sichtbare Zeile über dem Hinweistext.', 'industriesalon-notices'); ?></span>
        </p>

        <p>
            <label for="iss_badge"><strong><?php esc_html_e('Markierung', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_badge" name="iss_badge">
                <?php foreach ($this->badge_options() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($badge, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <hr>

        <p>
            <label for="iss_link_label"><strong><?php esc_html_e('Button-Text', 'industriesalon-notices'); ?></strong></label><br>
            <input type="text" class="widefat" id="iss_link_label" name="iss_link_label" value="<?php echo esc_attr($link_label); ?>">
        </p>

        <p>
            <label for="iss_link_type"><strong><?php esc_html_e('Linktyp', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_link_type" name="iss_link_type">
                <?php foreach ($this->link_type_options() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($link_type, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p data-iss-link-internal>
            <label for="iss_link_object_id"><strong><?php esc_html_e('Interne Seite oder Beitrag', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_link_object_id" name="iss_link_object_id">
                <option value="0"><?php esc_html_e('— auswählen —', 'industriesalon-notices'); ?></option>
                <?php foreach ($posts as $item) : ?>
                    <option value="<?php echo esc_attr((string) $item->ID); ?>" <?php selected($link_object_id, $item->ID); ?>>
                        <?php echo esc_html(get_the_title($item) ?: ('#' . $item->ID)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php esc_html_e('Nur relevant bei „Interner Inhalt“.', 'industriesalon-notices'); ?></span>
        </p>

        <p data-iss-link-external>
            <label for="iss_link_url_external"><strong><?php esc_html_e('Externer Link', 'industriesalon-notices'); ?></strong></label><br>
            <input type="url" class="widefat" id="iss_link_url_external" name="iss_link_url_external" value="<?php echo esc_attr($link_url_external); ?>" placeholder="https://...">
            <span class="description"><?php esc_html_e('Nur relevant bei „Externer Link“.', 'industriesalon-notices'); ?></span>
        </p>

        <p data-iss-link-external>
            <label>
                <input type="checkbox" name="iss_link_new_tab" value="1" <?php checked((bool) $this->get_meta($post->ID, 'iss_link_new_tab', 0)); ?>>
                <?php esc_html_e('Externen Link in neuem Tab öffnen', 'industriesalon-notices'); ?>
            </label>
        </p>
        <?php
    }

    public function render_display_box(\WP_Post $post): void {
        $area             = $this->get_meta($post->ID, 'iss_area', 'front_page_banner');
        $skin             = $this->get_meta($post->ID, 'iss_skin', 'front');
        $scope            = $this->get_meta($post->ID, 'iss_scope', 'global');
        $audience         = $this->get_meta($post->ID, 'iss_audience', 'public');
        $is_active        = (bool) $this->get_meta($post->ID, 'iss_is_active', 0);
        $priority         = (int) $this->get_meta($post->ID, 'iss_priority', 10);
        $scope_object_ids = $this->get_meta($post->ID, 'iss_scope_object_ids', []);

        $scope_object_ids = is_array($scope_object_ids) ? array_map('intval', $scope_object_ids) : [];

        $posts = get_posts([
            'post_type'      => ['page', 'post'],
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);
        ?>
        <p>
            <label for="iss_area"><strong><?php esc_html_e('Bereich', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_area" name="iss_area">
                <?php foreach ($this->area_options() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($area, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="iss_scope"><strong><?php esc_html_e('Geltung', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_scope" name="iss_scope">
                <?php foreach ($this->scope_options() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($scope, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="iss_skin"><strong><?php esc_html_e('Skin', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_skin" name="iss_skin">
                <?php foreach ($this->skin_options() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($skin, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php esc_html_e('Steuert die visuelle Banner-Variante (Front Banner, Landing Note oder Atlas Note).', 'industriesalon-notices'); ?></span>
        </p>

        <p data-iss-scope-selected>
            <label for="iss_scope_object_ids"><strong><?php esc_html_e('Inhalte auswählen', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_scope_object_ids" name="iss_scope_object_ids[]" multiple size="6">
                <?php foreach ($posts as $item) : ?>
                    <option value="<?php echo esc_attr((string) $item->ID); ?>" <?php selected(in_array($item->ID, $scope_object_ids, true)); ?>>
                        <?php echo esc_html(get_the_title($item) ?: ('#' . $item->ID)); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="description"><?php esc_html_e('Nur nutzen, wenn Geltung „Ausgewählte Inhalte“ ist.', 'industriesalon-notices'); ?></span>
        </p>

        <p>
            <label for="iss_audience"><strong><?php esc_html_e('Sichtbar für', 'industriesalon-notices'); ?></strong></label><br>
            <select class="widefat" id="iss_audience" name="iss_audience">
                <?php foreach ($this->audience_options() as $value => $label) : ?>
                    <option value="<?php echo esc_attr($value); ?>" <?php selected($audience, $value); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </p>

        <p>
            <label for="iss_priority"><strong><?php esc_html_e('Priorität', 'industriesalon-notices'); ?></strong></label><br>
            <input type="number" class="small-text" min="0" step="1" id="iss_priority" name="iss_priority" value="<?php echo esc_attr((string) $priority); ?>">
        </p>

        <p>
            <label>
                <input type="checkbox" name="iss_is_active" value="1" <?php checked($is_active); ?>>
                <?php esc_html_e('Hinweis anzeigen', 'industriesalon-notices'); ?>
            </label>
        </p>
        <?php
    }

    public function render_schedule_box(\WP_Post $post): void {
        $start_at = $this->get_meta($post->ID, 'iss_start_at');
        $end_at   = $this->get_meta($post->ID, 'iss_end_at');
        ?>
        <p>
            <label for="iss_start_at"><strong><?php esc_html_e('Anzeigen ab', 'industriesalon-notices'); ?></strong></label><br>
            <input type="datetime-local" class="widefat" id="iss_start_at" name="iss_start_at" value="<?php echo esc_attr($start_at); ?>">
        </p>

        <p>
            <label for="iss_end_at"><strong><?php esc_html_e('Anzeigen bis', 'industriesalon-notices'); ?></strong></label><br>
            <input type="datetime-local" class="widefat" id="iss_end_at" name="iss_end_at" value="<?php echo esc_attr($end_at); ?>">
        </p>
        <?php
    }

    public function save_meta_boxes(int $post_id): void {
        if (! isset($_POST[self::NONCE]) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), 'iss_notice_save_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $post_id) || ! current_user_can($this->capability())) {
            return;
        }

        $text_fields = [
            'iss_kicker',
            'iss_headline',
            'iss_link_label',
        ];

        foreach ($text_fields as $field) {
            $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
            $this->update_or_delete_meta($post_id, $field, $value);
        }

        $select_fields = [
            'iss_kicker_style' => array_keys($this->kicker_style_options()),
            'iss_badge'        => array_keys($this->badge_options()),
            'iss_link_type'    => array_keys($this->link_type_options()),
            'iss_area'      => array_keys($this->area_options()),
            'iss_skin'      => array_keys($this->skin_options()),
            'iss_scope'     => array_keys($this->scope_options()),
            'iss_audience'  => array_keys($this->audience_options()),
        ];

        foreach ($select_fields as $field => $allowed) {
            $value = isset($_POST[$field]) ? sanitize_text_field(wp_unslash($_POST[$field])) : '';
            if (! in_array($value, $allowed, true)) {
                $value = '';
            }
            $this->update_or_delete_meta($post_id, $field, $value);
        }

        $link_object_id = isset($_POST['iss_link_object_id']) ? absint($_POST['iss_link_object_id']) : 0;
        $this->update_or_delete_meta($post_id, 'iss_link_object_id', $link_object_id);

        $external_url = isset($_POST['iss_link_url_external']) ? esc_url_raw(wp_unslash($_POST['iss_link_url_external'])) : '';
        $this->update_or_delete_meta($post_id, 'iss_link_url_external', $external_url);
        update_post_meta($post_id, 'iss_link_new_tab', isset($_POST['iss_link_new_tab']) ? 1 : 0);

        $scope_ids = isset($_POST['iss_scope_object_ids']) && is_array($_POST['iss_scope_object_ids'])
            ? array_values(array_filter(array_map('absint', wp_unslash($_POST['iss_scope_object_ids']))))
            : [];
        $this->update_or_delete_meta($post_id, 'iss_scope_object_ids', $scope_ids);

        $priority = isset($_POST['iss_priority']) ? (int) $_POST['iss_priority'] : 10;
        update_post_meta($post_id, 'iss_priority', $priority);

        $is_active = isset($_POST['iss_is_active']) ? 1 : 0;
        update_post_meta($post_id, 'iss_is_active', $is_active);

        $start_at = $this->sanitize_datetime_local($_POST['iss_start_at'] ?? '');
        $end_at   = $this->sanitize_datetime_local($_POST['iss_end_at'] ?? '');
        if ($start_at !== '' && $end_at !== '') {
            $start_ts = $this->datetime_local_to_timestamp($start_at);
            $end_ts = $this->datetime_local_to_timestamp($end_at);
            if ($start_ts !== null && $end_ts !== null && $end_ts < $start_ts) {
                $end_at = '';
            }
        }
        $this->update_or_delete_meta($post_id, 'iss_start_at', $start_at);
        $this->update_or_delete_meta($post_id, 'iss_end_at', $end_at);
    }

    public function admin_columns(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['iss_area'] = __('Bereich', 'industriesalon-notices');
                $new['iss_audience'] = __('Sichtbar für', 'industriesalon-notices');
                $new['iss_active'] = __('Aktiv', 'industriesalon-notices');
                $new['iss_period'] = __('Zeitraum', 'industriesalon-notices');
                $new['iss_priority'] = __('Priorität', 'industriesalon-notices');
            }
        }
        return $new;
    }

    public function render_admin_columns(string $column, int $post_id): void {
        switch ($column) {
            case 'iss_area':
                echo esc_html($this->area_options()[$this->get_meta($post_id, 'iss_area', 'front_page_banner')] ?? '');
                break;
            case 'iss_audience':
                echo esc_html($this->audience_options()[$this->get_meta($post_id, 'iss_audience', 'public')] ?? '');
                break;
            case 'iss_active':
                echo esc_html($this->get_meta($post_id, 'iss_is_active', 0) ? __('Ja', 'industriesalon-notices') : __('Nein', 'industriesalon-notices'));
                break;
            case 'iss_period':
                $start = $this->get_meta($post_id, 'iss_start_at');
                $end   = $this->get_meta($post_id, 'iss_end_at');
                echo esc_html($this->format_period((string) $start, (string) $end));
                break;
            case 'iss_priority':
                echo esc_html((string) ((int) $this->get_meta($post_id, 'iss_priority', 10)));
                break;
        }
    }

    public function sortable_columns(array $columns): array {
        $columns['iss_priority'] = 'iss_priority';
        return $columns;
    }

    public function render_admin_notices(): void {
        if (! is_admin()) {
            return;
        }

        if (! current_user_can($this->capability())) {
            return;
        }

        $audience = current_user_can('manage_options') ? ['administrators', 'editors'] : ['editors'];
        $notice   = $this->find_notice('admin_notice', $audience, get_the_ID());

        if (! $notice) {
            return;
        }

        $headline = get_post_meta($notice->ID, 'iss_headline', true);
        $content  = apply_filters('the_content', $notice->post_content);

        echo '<div class="notice notice-info iss-admin-notice">';
        if ($headline) {
            echo '<p><strong>' . esc_html($headline) . '</strong></p>';
        }
        echo wp_kses_post($content);
        echo '</div>';
    }

    public function shortcode(array $atts): string {
        $atts = shortcode_atts([
            'area' => 'front_page_banner',
        ], $atts, 'iss_notice');

        $notice = $this->find_notice(sanitize_key($atts['area']), ['public'], get_the_ID());
        if (! $notice) {
            return '';
        }

        return $this->render_front_notice($notice);
    }

    public function render_notice_block(array $attributes = [], string $content = '', ?\WP_Block $block = null): string {
        unset($content, $block);

        $area = isset($attributes['area']) ? sanitize_key((string) $attributes['area']) : 'front_page_banner';
        if ($area === '') {
            $area = 'front_page_banner';
        }
        $skin_from_block = isset($attributes['skin']) ? sanitize_key((string) $attributes['skin']) : '';

        $current_object_id = (int) get_the_ID();
        if ($current_object_id <= 0) {
            $current_object_id = (int) get_queried_object_id();
        }

        $notice = $this->find_notice($area, ['public'], $current_object_id);

        $skin = $this->sanitize_skin_value($skin_from_block);
        if ($skin === '' && $notice instanceof \WP_Post) {
            $skin = $this->sanitize_skin_value((string) get_post_meta($notice->ID, 'iss_skin', true));
        }
        if ($skin === '') {
            $skin = 'front';
        }

        $attrs = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes(['class' => 'iss-notice-skin--' . $skin])
            : '';

        if (! $notice) {
            if (is_admin()) {
                return sprintf(
                    '<div %s><p style="padding:1rem; border:1px dashed #ccc; text-align:center;">%s: %s</p></div>',
                    $attrs,
                    esc_html__('Kein aktiver Hinweis für Bereich', 'industriesalon-notices'),
                    esc_html($area)
                );
            }
            return '';
        }

        // We wrap the output of render_front_notice to ensure standard Gutenberg attributes are applied
        return sprintf('<div %s>%s</div>', $attrs, $this->render_front_notice($notice));
    }

    public function find_notice(string $area, array $audiences = ['public'], int $current_object_id = 0): ?\WP_Post {
        $query = new \WP_Query([
            'post_type'      => self::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => [
                'menu_order' => 'ASC',
                'date'       => 'DESC',
            ],
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Notice lookup is intentionally driven by small indexed admin-controlled meta flags.
            'meta_query'     => [
                [
                    'key'   => 'iss_is_active',
                    'value' => '1',
                ],
                [
                    'key'   => 'iss_area',
                    'value' => $area,
                ],
            ],
        ]);

        if (! $query->have_posts()) {
            return null;
        }

        $matches = [];
        $now     = current_time('timestamp');

        foreach ($query->posts as $post) {
            $audience = (string) get_post_meta($post->ID, 'iss_audience', true);
            if (! in_array($audience, $audiences, true)) {
                continue;
            }

            if (! $this->matches_schedule($post->ID, $now)) {
                continue;
            }

            if (! $this->matches_scope($post->ID, $current_object_id)) {
                continue;
            }

            $matches[] = $post;
        }

        if ($matches === []) {
            return null;
        }

        usort($matches, static function (\WP_Post $a, \WP_Post $b): int {
            $prio_a = (int) get_post_meta($a->ID, 'iss_priority', true);
            $prio_b = (int) get_post_meta($b->ID, 'iss_priority', true);
            if ($prio_a === $prio_b) {
                return strcmp($b->post_date, $a->post_date);
            }
            return $prio_b <=> $prio_a;
        });

        return $matches[0];
    }

    public function render_front_notice(\WP_Post $notice): string {
        $kicker     = trim((string) get_post_meta($notice->ID, 'iss_kicker', true));
        $headline   = trim((string) get_post_meta($notice->ID, 'iss_headline', true));
        $link_label = trim((string) get_post_meta($notice->ID, 'iss_link_label', true));
        $link_url   = trim((string) $this->resolve_link_url($notice->ID));

        $eyebrow_text = $kicker !== '' ? $kicker : __('Hinweis', 'industriesalon-notices');
        $title_text   = $headline !== '' ? $headline : __('Heute im Industriesalon', 'industriesalon-notices');
        $cta_text     = $link_label !== '' ? $link_label : __('Mehr erfahren', 'industriesalon-notices');

        ob_start();
        ?>
        <aside class="iss-hero-note" role="note" aria-label="Hinweis">
            <span class="iss-hero-note__marker" aria-hidden="true"></span>
            <p class="iss-hero-note__kicker"><?php echo esc_html($eyebrow_text); ?></p>
            <h3 class="iss-hero-note__title"><?php echo esc_html($title_text); ?></h3>
            <div class="iss-hero-note__text"><?php echo wp_kses_post(wpautop($notice->post_content)); ?></div>
            <?php if ($link_url !== '') : ?>
            <p class="iss-hero-note__link">
                <a href="<?php echo esc_url($link_url); ?>"<?php echo $this->link_target_attributes($notice->ID); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Helper returns a fixed escaped target/rel attribute string or an empty string. ?>>
                    <span class="iss-hero-note__link-icon" aria-hidden="true">→</span>
                    <span class="iss-hero-note__link-text"><?php echo esc_html($cta_text); ?></span>
                </a>
            </p>
            <?php endif; ?>
        </aside>
        <?php
        return (string) ob_get_clean();
    }

    private function render_kicker_markup(string $text, string $style = 'default'): string {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        $classes = ['iss-kicker'];
        if ($style !== '' && $style !== 'default') {
            $classes[] = 'iss-kicker--' . sanitize_html_class($style);
        }

        $class_attr = implode(' ', array_unique($classes));

        return sprintf(
            '<!-- wp:paragraph {"className":"%1$s"} -->' . "\n" . '<p class="%1$s">%2$s</p>' . "\n" . '<!-- /wp:paragraph -->',
            esc_attr($class_attr),
            esc_html($text)
        );
    }

    private function matches_schedule(int $post_id, int $now): bool {
        $start_raw = (string) get_post_meta($post_id, 'iss_start_at', true);
        $end_raw   = (string) get_post_meta($post_id, 'iss_end_at', true);

        $start_ok = true;
        $end_ok   = true;

        if ($start_raw !== '') {
            $start_ts = $this->datetime_local_to_timestamp($start_raw);
            $start_ok = $start_ts === null ? false : $start_ts <= $now;
        }

        if ($end_raw !== '') {
            $end_ts = $this->datetime_local_to_timestamp($end_raw);
            $end_ok = $end_ts === null ? false : $end_ts >= $now;
        }

        return $start_ok && $end_ok;
    }

    private function matches_scope(int $post_id, int $current_object_id): bool {
        $scope = (string) get_post_meta($post_id, 'iss_scope', true);

        if ($scope === 'global') {
            return true;
        }

        if ($scope === 'front_page_only') {
            return is_front_page() || $this->is_front_page_editor_preview();
        }

        if ($scope === 'selected') {
            $selected = get_post_meta($post_id, 'iss_scope_object_ids', true);
            $selected = is_array($selected) ? array_map('intval', $selected) : [];
            return $current_object_id > 0 && in_array($current_object_id, $selected, true);
        }

        return false;
    }

    private function is_front_page_editor_preview(): bool {
        if (! (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only REST route detection for editor preview rendering.
        $rest_route  = isset($_GET['rest_route']) ? (string) wp_unslash($_GET['rest_route']) : '';

        if (strpos($request_uri, '/wp-json/wp/v2/block-renderer/industriesalon/notice-banner') !== false) {
            return true;
        }

        if (strpos($rest_route, '/wp/v2/block-renderer/industriesalon/notice-banner') !== false) {
            return true;
        }

        if (! function_exists('rest_get_server')) {
            return false;
        }

        $server = rest_get_server();
        if (! ($server instanceof \WP_REST_Server)) {
            return false;
        }

        $request = $server->get_current_request();
        if (! ($request instanceof \WP_REST_Request)) {
            return false;
        }

        $route = (string) $request->get_route();
        if (strpos($route, '/wp/v2/block-renderer/') !== 0) {
            return false;
        }

        if (strpos($route, '/wp/v2/block-renderer/industriesalon/notice-banner') === 0) {
            return true;
        }

        $post_id = absint($request->get_param('post_id'));
        if ($post_id > 0) {
            $post = get_post($post_id);
            if (($post instanceof \WP_Post) && $post->post_type === 'wp_template') {
                return strpos((string) $post->post_name, 'front-page') !== false;
            }
        }

        $referer = isset($_SERVER['HTTP_REFERER']) ? (string) wp_unslash($_SERVER['HTTP_REFERER']) : '';
        if ($referer === '') {
            return false;
        }

        $parts = wp_parse_url($referer);
        if (! is_array($parts) || empty($parts['path'])) {
            return false;
        }

        if (strpos((string) $parts['path'], '/wp-admin/site-editor.php') === false) {
            return false;
        }

        if (empty($parts['query'])) {
            return false;
        }

        parse_str((string) $parts['query'], $query_vars);
        $editor_target = isset($query_vars['p']) ? (string) $query_vars['p'] : '';

        return strpos($editor_target, 'front-page') !== false;
    }

    private function resolve_link_url(int $post_id): string {
        $type = (string) get_post_meta($post_id, 'iss_link_type', true);
        if ($type === 'internal') {
            $object_id = (int) get_post_meta($post_id, 'iss_link_object_id', true);
            return $object_id > 0 ? (string) get_permalink($object_id) : '';
        }

        if ($type === 'external') {
            return (string) get_post_meta($post_id, 'iss_link_url_external', true);
        }

        return '';
    }

    private function link_target_attributes(int $post_id): string {
        $type = (string) get_post_meta($post_id, 'iss_link_type', true);
        $new_tab = (bool) get_post_meta($post_id, 'iss_link_new_tab', true);
        if ($type === 'external' && $new_tab) {
            return ' target="_blank" rel="noopener noreferrer"';
        }

        return '';
    }

    private function sanitize_datetime_local($raw): string {
        if (! is_scalar($raw)) {
            return '';
        }

        $value = sanitize_text_field(wp_unslash((string) $raw));
        if ($value === '') {
            return '';
        }

        $dt = date_create_immutable_from_format('Y-m-d\\TH:i', $value, wp_timezone());
        if (! ($dt instanceof DateTimeImmutable)) {
            return '';
        }

        return $dt->format('Y-m-d\\TH:i');
    }

    private function datetime_local_to_timestamp(string $value): ?int {
        $dt = date_create_immutable_from_format('Y-m-d\\TH:i', $value, wp_timezone());
        if (! ($dt instanceof DateTimeImmutable)) {
            return null;
        }

        return $dt->getTimestamp();
    }

    private function format_period(string $start, string $end): string {
        $placeholder = '—';
        $start_label = $this->format_datetime_local($start) ?: $placeholder;
        $end_label = $this->format_datetime_local($end) ?: $placeholder;

        return sprintf(
            /* translators: 1: start date/time, 2: end date/time */
            __('%1$s bis %2$s', 'industriesalon-notices'),
            $start_label,
            $end_label
        );
    }

    private function format_datetime_local(string $value): string {
        $timestamp = $this->datetime_local_to_timestamp($value);
        if ($timestamp === null) {
            return '';
        }

        return wp_date('d.m.Y H:i', $timestamp, wp_timezone());
    }

    private function get_meta(int $post_id, string $key, $default = '') {
        $value = get_post_meta($post_id, $key, true);
        return $value === '' ? $default : $value;
    }

    private function update_or_delete_meta(int $post_id, string $key, $value): void {
        $empty = $value === '' || $value === [] || $value === 0;
        if ($empty) {
            delete_post_meta($post_id, $key);
            return;
        }
        update_post_meta($post_id, $key, $value);
    }

    private function kicker_style_options(): array {
        return [
            'default' => __('Standard', 'industriesalon-notices'),
            'light'   => __('Hell', 'industriesalon-notices'),
            'muted'   => __('Gedämpft', 'industriesalon-notices'),
            'compact' => __('Kompakt', 'industriesalon-notices'),
            'lg'      => __('Groß', 'industriesalon-notices'),
            'green'   => __('Grün', 'industriesalon-notices'),
            'blue'    => __('Blau', 'industriesalon-notices'),
            'yellow'  => __('Gelb', 'industriesalon-notices'),
        ];
    }

    private function badge_options(): array {
        return [
            'none'         => __('— keine —', 'industriesalon-notices'),
            'neu'          => __('Neu', 'industriesalon-notices'),
            'wichtig'      => __('Wichtig', 'industriesalon-notices'),
            'ausverkauft'  => __('Ausverkauft', 'industriesalon-notices'),
            'sondertermin' => __('Sondertermin', 'industriesalon-notices'),
            'hinweis'      => __('Hinweis', 'industriesalon-notices'),
        ];
    }

    private function link_type_options(): array {
        return [
            'none'     => __('Kein Link', 'industriesalon-notices'),
            'internal' => __('Interner Inhalt', 'industriesalon-notices'),
            'external' => __('Externer Link', 'industriesalon-notices'),
        ];
    }

    private function area_options(): array {
        return [
            'front_page_banner'      => __('Startseiten-Banner', 'industriesalon-notices'),
            'front_page_timeline_promo' => __('Startseite Kalender-Hinweis', 'industriesalon-notices'),
            'site_notice'            => __('Website-Hinweis', 'industriesalon-notices'),
            'selected_pages_banner' => __('Ausgewählte Seiten (Banner)', 'industriesalon-notices'),
            'ausstellungen_banner'  => __('Ausstellungen (Banner)', 'industriesalon-notices'),
            'admin_notice'          => __('Admin-Hinweis', 'industriesalon-notices'),
        ];
    }

    private function skin_options(): array {
        return [
            'front'   => __('Front Banner', 'industriesalon-notices'),
            'landing' => __('Landing Note', 'industriesalon-notices'),
            'atlas'   => __('Atlas Note', 'industriesalon-notices'),
            'pinned'  => __('Pinned Note', 'industriesalon-notices'),
        ];
    }

    private function sanitize_skin_value(string $value): string {
        $value = sanitize_key($value);
        return in_array($value, array_keys($this->skin_options()), true) ? $value : '';
    }

    private function scope_options(): array {
        return [
            'global'          => __('Gesamte Website', 'industriesalon-notices'),
            'front_page_only' => __('Nur Startseite', 'industriesalon-notices'),
            'selected'        => __('Ausgewählte Inhalte', 'industriesalon-notices'),
        ];
    }

    private function audience_options(): array {
        return [
            'public'         => __('Öffentlich', 'industriesalon-notices'),
            'editors'        => __('Redakteure', 'industriesalon-notices'),
            'administrators' => __('Administratoren', 'industriesalon-notices'),
        ];
    }
}

$GLOBALS['industriesalon_notices'] = new Industriesalon_Notices();

function iss_get_notice(string $area = 'front_page_banner'): ?WP_Post {
    if (! isset($GLOBALS['industriesalon_notices']) || ! $GLOBALS['industriesalon_notices'] instanceof Industriesalon_Notices) {
        return null;
    }

    return $GLOBALS['industriesalon_notices']->find_notice($area, ['public'], get_the_ID());
}

function iss_render_notice(string $area = 'front_page_banner'): string {
    if (! isset($GLOBALS['industriesalon_notices']) || ! $GLOBALS['industriesalon_notices'] instanceof Industriesalon_Notices) {
        return '';
    }

    $notice = $GLOBALS['industriesalon_notices']->find_notice($area, ['public'], get_the_ID());
    if (! $notice) {
        return '';
    }

    return $GLOBALS['industriesalon_notices']->render_front_notice($notice);
}
