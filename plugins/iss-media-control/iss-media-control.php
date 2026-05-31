<?php
/**
 * Plugin Name: ISS Media Control
 * Description: Clean Media Library ownership filtering and attachment focal-point metadata without changing physical upload folders.
 * Version: 0.2.0
 * Author: Industriesalon
 * Text Domain: iss-media-control
 */

if (!defined('ABSPATH')) {
    exit;
}

final class ISS_Media_Control
{
    public const VERSION = '0.2.0';
    public const TAXONOMY = 'iss_media_owner';
    public const REQUEST_VAR = 'iss_media_owner';
    public const ALL_VALUE = '__all';
    public const FOCUS_X_META = 'iss_media_focus_x';
    public const FOCUS_Y_META = 'iss_media_focus_y';
    private const FOCUS_MIN = 0.0;
    private const FOCUS_MAX = 100.0;
    private const FOCUS_DEFAULT = 50.0;

    public static function init(): void
    {
        add_action('init', [__CLASS__, 'register_taxonomy']);
        add_action('init', [__CLASS__, 'register_attachment_meta']);
        add_action('admin_init', [__CLASS__, 'ensure_default_terms']);

        add_filter('attachment_fields_to_edit', [__CLASS__, 'add_attachment_owner_field'], 10, 2);
        add_filter('attachment_fields_to_save', [__CLASS__, 'save_attachment_owner_field'], 10, 2);
        add_filter('attachment_fields_to_edit', [__CLASS__, 'add_attachment_focus_fields'], 15, 2);
        add_filter('attachment_fields_to_save', [__CLASS__, 'save_attachment_focus_fields'], 15, 2);
        add_filter('wp_get_attachment_image_attributes', [__CLASS__, 'add_focus_style_to_attachment_image'], 10, 3);

        add_filter('manage_upload_columns', [__CLASS__, 'add_media_owner_column']);
        add_action('manage_media_custom_column', [__CLASS__, 'render_media_owner_column'], 10, 2);

        add_action('restrict_manage_posts', [__CLASS__, 'render_media_owner_filter']);
        add_action('pre_get_posts', [__CLASS__, 'filter_media_list_query']);
        add_filter('ajax_query_attachments_args', [__CLASS__, 'filter_media_modal_query']);

        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_media_admin_assets']);
        add_action('enqueue_block_editor_assets', [__CLASS__, 'enqueue_block_editor_assets']);

        add_action('add_attachment', [__CLASS__, 'set_default_owner_on_upload']);
    }

    public static function activate(): void
    {
        self::register_taxonomy();
        self::ensure_default_terms();
    }

    public static function register_taxonomy(): void
    {
        register_taxonomy(
            self::TAXONOMY,
            ['attachment'],
            [
                'labels' => [
                    'name' => __('Media Ownership', 'iss-media-control'),
                    'singular_name' => __('Media Owner', 'iss-media-control'),
                    'search_items' => __('Search Media Ownership', 'iss-media-control'),
                    'all_items' => __('All Media Ownership', 'iss-media-control'),
                    'edit_item' => __('Edit Media Ownership', 'iss-media-control'),
                    'update_item' => __('Update Media Ownership', 'iss-media-control'),
                    'add_new_item' => __('Add Media Ownership', 'iss-media-control'),
                    'new_item_name' => __('New Media Ownership', 'iss-media-control'),
                    'menu_name' => __('Media Ownership', 'iss-media-control'),
                ],
                'public' => false,
                'show_ui' => true,
                'show_admin_column' => false,
                'show_in_rest' => true,
                'hierarchical' => false,
                'rewrite' => false,
                'query_var' => true,
                'capabilities' => [
                    'manage_terms' => 'manage_options',
                    'edit_terms' => 'manage_options',
                    'delete_terms' => 'manage_options',
                    'assign_terms' => 'upload_files',
                ],
            ]
        );
    }

    public static function register_attachment_meta(): void
    {
        $meta_args = [
            'type' => 'number',
            'single' => true,
            'show_in_rest' => [
                'schema' => [
                    'type' => 'number',
                    'minimum' => self::FOCUS_MIN,
                    'maximum' => self::FOCUS_MAX,
                    'context' => ['edit'],
                ],
            ],
            'auth_callback' => static function (): bool {
                return current_user_can('upload_files');
            },
        ];

        register_post_meta('attachment', self::FOCUS_X_META, $meta_args);
        register_post_meta('attachment', self::FOCUS_Y_META, $meta_args);
    }

    public static function ensure_default_terms(): void
    {
        $terms = [
            'site-owned' => __('Site owned', 'iss-media-control'),
            'editorial-upload' => __('Editorial upload', 'iss-media-control'),
        ];

        foreach ($terms as $slug => $name) {
            if (!term_exists($slug, self::TAXONOMY)) {
                wp_insert_term($name, self::TAXONOMY, ['slug' => $slug]);
            }
        }
    }

    public static function set_default_owner_on_upload(int $attachment_id): void
    {
        $existing_terms = wp_get_object_terms($attachment_id, self::TAXONOMY, ['fields' => 'ids']);
        if (is_wp_error($existing_terms) || !empty($existing_terms)) {
            return;
        }

        $default_slug = self::get_default_owner_slug();
        if ($default_slug !== '' && term_exists($default_slug, self::TAXONOMY)) {
            wp_set_object_terms($attachment_id, $default_slug, self::TAXONOMY, false);
        }
    }

    public static function add_attachment_owner_field(array $form_fields, WP_Post $post): array
    {
        $terms = self::get_owner_terms_for_assignment();
        if (empty($terms)) {
            return $form_fields;
        }

        $selected = wp_get_object_terms($post->ID, self::TAXONOMY, ['fields' => 'slugs']);
        $selected_slug = !empty($selected) && !is_wp_error($selected) ? (string) $selected[0] : self::get_default_owner_slug();

        $html = '<select name="attachments[' . esc_attr((string) $post->ID) . '][iss_media_owner]">';
        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $html .= sprintf(
                '<option value="%s" %s>%s</option>',
                esc_attr($term->slug),
                selected($selected_slug, $term->slug, false),
                esc_html($term->name)
            );
        }
        $html .= '</select>';

        $form_fields['iss_media_owner'] = [
            'label' => __('Media ownership', 'iss-media-control'),
            'input' => 'html',
            'html' => $html,
            'helps' => __('Use “Site owned” for logos, layout assets, identity, hero and structural media. Use “Editorial upload” for normal editorial uploads.', 'iss-media-control'),
        ];

        return $form_fields;
    }

    public static function add_attachment_focus_fields(array $form_fields, WP_Post $post): array
    {
        if (!wp_attachment_is_image($post->ID)) {
            return $form_fields;
        }

        $focus = self::get_attachment_focus_point($post->ID);
        $focus_x = self::format_focus_value($focus['x'] ?? null);
        $focus_y = self::format_focus_value($focus['y'] ?? null);
        $attachment_id = esc_attr((string) $post->ID);

        $html = '<div style="display:flex;gap:12px;flex-wrap:wrap">';
        $html .= '<label style="display:flex;flex-direction:column;gap:4px">';
        $html .= '<span>' . esc_html__('Horizontal %', 'iss-media-control') . '</span>';
        $html .= '<input type="number" min="0" max="100" step="0.1" class="small-text" name="attachments[' . $attachment_id . '][' . esc_attr(self::FOCUS_X_META) . ']" value="' . esc_attr($focus_x) . '" placeholder="50" />';
        $html .= '</label>';
        $html .= '<label style="display:flex;flex-direction:column;gap:4px">';
        $html .= '<span>' . esc_html__('Vertical %', 'iss-media-control') . '</span>';
        $html .= '<input type="number" min="0" max="100" step="0.1" class="small-text" name="attachments[' . $attachment_id . '][' . esc_attr(self::FOCUS_Y_META) . ']" value="' . esc_attr($focus_y) . '" placeholder="50" />';
        $html .= '</label>';
        $html .= '</div>';

        $form_fields['iss_media_focus'] = [
            'label' => __('Image focus', 'iss-media-control'),
            'input' => 'html',
            'html' => $html,
            'helps' => __('Optional focal point for cropped cards and hero images. Use percentages from 0 to 100. Leave both fields empty to keep centered cropping.', 'iss-media-control'),
        ];

        return $form_fields;
    }

    public static function save_attachment_owner_field(array $post, array $attachment): array
    {
        if (!current_user_can('upload_files')) {
            return $post;
        }

        if (empty($attachment['iss_media_owner'])) {
            return $post;
        }

        $slug = sanitize_key((string) $attachment['iss_media_owner']);
        if ($slug === '' || !self::is_allowed_owner_slug_for_assignment($slug)) {
            return $post;
        }

        if (term_exists($slug, self::TAXONOMY)) {
            wp_set_object_terms((int) $post['ID'], $slug, self::TAXONOMY, false);
        }

        return $post;
    }

    public static function save_attachment_focus_fields(array $post, array $attachment): array
    {
        if (!current_user_can('upload_files')) {
            return $post;
        }

        $attachment_id = (int) ($post['ID'] ?? 0);
        if ($attachment_id <= 0) {
            return $post;
        }

        self::save_focus_meta_value($attachment_id, self::FOCUS_X_META, $attachment[self::FOCUS_X_META] ?? null);
        self::save_focus_meta_value($attachment_id, self::FOCUS_Y_META, $attachment[self::FOCUS_Y_META] ?? null);

        return $post;
    }

    public static function add_focus_style_to_attachment_image(array $attr, WP_Post $attachment, $size): array
    {
        unset($size);

        if (!wp_attachment_is_image($attachment->ID)) {
            return $attr;
        }

        $focus = self::get_attachment_focus_point($attachment->ID);
        if ($focus === []) {
            return $attr;
        }

        $style = self::build_focus_style_declaration($focus);
        $attr['style'] = self::merge_style_attribute((string) ($attr['style'] ?? ''), $style);

        return $attr;
    }

    public static function get_attachment_focus_point(int $attachment_id): array
    {
        $focus_x = self::get_focus_meta_value($attachment_id, self::FOCUS_X_META);
        $focus_y = self::get_focus_meta_value($attachment_id, self::FOCUS_Y_META);

        if ($focus_x === null && $focus_y === null) {
            return [];
        }

        return [
            'x' => $focus_x ?? self::FOCUS_DEFAULT,
            'y' => $focus_y ?? self::FOCUS_DEFAULT,
        ];
    }

    public static function add_media_owner_column(array $columns): array
    {
        $columns['iss_media_owner'] = __('Owner', 'iss-media-control');
        return $columns;
    }

    public static function render_media_owner_column(string $column_name, int $attachment_id): void
    {
        if ($column_name !== 'iss_media_owner') {
            return;
        }

        $terms = wp_get_object_terms($attachment_id, self::TAXONOMY);
        if (is_wp_error($terms) || empty($terms)) {
            echo '—';
            return;
        }

        echo esc_html(implode(', ', wp_list_pluck($terms, 'name')));
    }

    public static function render_media_owner_filter(): void
    {
        global $pagenow;

        if ($pagenow !== 'upload.php') {
            return;
        }

        $terms = self::get_owner_terms_for_view();
        if (empty($terms)) {
            return;
        }

        $selected = self::get_requested_owner_slug();

        echo '<label class="screen-reader-text" for="filter-by-' . esc_attr(self::REQUEST_VAR) . '">' . esc_html__('Filter by media ownership', 'iss-media-control') . '</label>';
        echo '<select id="filter-by-' . esc_attr(self::REQUEST_VAR) . '" name="' . esc_attr(self::REQUEST_VAR) . '">';
        echo '<option value="">' . esc_html__('All media ownership', 'iss-media-control') . '</option>';

        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            echo '<option value="' . esc_attr($term->slug) . '"' . selected($selected, $term->slug, false) . '>' . esc_html($term->name) . '</option>';
        }

        echo '</select>';
    }

    public static function filter_media_list_query(WP_Query $query): void
    {
        global $pagenow;

        if (!is_admin() || $pagenow !== 'upload.php' || !$query->is_main_query()) {
            return;
        }

        $post_type = $query->get('post_type');
        if ($post_type !== 'attachment' && $post_type !== '') {
            return;
        }

        self::apply_owner_filter_to_query($query);
    }

    public static function filter_media_modal_query(array $query): array
    {
        $requested_slug = isset($query[self::REQUEST_VAR]) ? sanitize_key((string) $query[self::REQUEST_VAR]) : '';
        $allowed_slugs = self::get_allowed_owner_slugs('view');

        return self::apply_owner_constraint_to_query_args($query, $requested_slug, $allowed_slugs);
    }

    public static function enqueue_media_admin_assets(string $hook): void
    {
        if ($hook !== 'upload.php') {
            return;
        }

        self::enqueue_filter_script();
    }

    public static function enqueue_block_editor_assets(): void
    {
        self::enqueue_filter_script();
    }

    private static function enqueue_filter_script(): void
    {
        $terms = self::get_owner_terms_for_view();
        if (empty($terms)) {
            return;
        }

        wp_register_script(
            'iss-media-control-admin',
            plugin_dir_url(__FILE__) . 'assets/admin-media-filter.js',
            ['media-views'],
            self::VERSION,
            true
        );

        $term_payload = [];
        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $term_payload[] = [
                'slug' => $term->slug,
                'name' => $term->name,
            ];
        }

        wp_localize_script('iss-media-control-admin', 'issMediaControl', [
            'requestVar' => self::REQUEST_VAR,
            'allLabel' => __('All media ownership', 'iss-media-control'),
            'allValue' => self::ALL_VALUE,
            'defaultModalOwner' => self::get_default_owner_slug(),
            'terms' => $term_payload,
        ]);

        wp_enqueue_script('iss-media-control-admin');
    }

    private static function apply_owner_filter_to_query(WP_Query $query): void
    {
        $requested_slug = self::get_requested_owner_slug();
        $allowed_slugs = self::get_allowed_owner_slugs('view');
        $query_args = self::apply_owner_constraint_to_query_args(
            [
                'tax_query' => $query->get('tax_query'),
                'post__in' => $query->get('post__in'),
            ],
            $requested_slug,
            $allowed_slugs
        );

        if (array_key_exists('post__in', $query_args)) {
            $query->set('post__in', $query_args['post__in']);
        }
        if (array_key_exists('tax_query', $query_args)) {
            $query->set('tax_query', $query_args['tax_query']);
        }

        if (
            (array_key_exists('tax_query', $query_args) || array_key_exists('post__in', $query_args))
            && $query->get('post_status') === ''
        ) {
            $query->set('post_status', 'inherit');
        }
    }

    private static function apply_owner_constraint_to_query_args(array $query_args, string $requested_slug, array $allowed_slugs): array
    {
        $requested_slug = sanitize_key($requested_slug);
        $allowed_slugs = self::normalize_slug_list($allowed_slugs);

        if ($requested_slug === self::ALL_VALUE) {
            $requested_slug = '';
        }

        if ($requested_slug === '' && wp_doing_ajax()) {
            $requested_slug = self::get_default_owner_slug();
        }

        if (!empty($allowed_slugs) && $requested_slug !== '' && !in_array($requested_slug, $allowed_slugs, true)) {
            $query_args['post__in'] = [0];
            return $query_args;
        }

        $terms = [];
        if ($requested_slug !== '') {
            $terms = [$requested_slug];
        } elseif (!empty($allowed_slugs)) {
            $terms = $allowed_slugs;
        }

        if (empty($terms)) {
            return $query_args;
        }

        $tax_query = isset($query_args['tax_query']) && is_array($query_args['tax_query']) ? $query_args['tax_query'] : [];
        $tax_query[] = [
            'taxonomy' => self::TAXONOMY,
            'field' => 'slug',
            'terms' => $terms,
        ];

        if (count($tax_query) > 1 && !isset($tax_query['relation'])) {
            $tax_query['relation'] = 'AND';
        }

        $query_args['tax_query'] = $tax_query;

        if (empty($query_args['post_status'])) {
            $query_args['post_status'] = 'inherit';
        }

        return $query_args;
    }

    private static function get_requested_owner_slug(): string
    {
        return isset($_REQUEST[self::REQUEST_VAR]) ? sanitize_key((string) wp_unslash($_REQUEST[self::REQUEST_VAR])) : '';
    }

    private static function get_default_owner_slug(): string
    {
        $default_slug = apply_filters('iss_media_control_default_owner_slug', 'editorial-upload');
        $default_slug = sanitize_key((string) $default_slug);

        return $default_slug !== '' ? $default_slug : 'editorial-upload';
    }

    private static function get_allowed_owner_slugs(string $context): array
    {
        $allowed = apply_filters('iss_media_control_allowed_owner_slugs', [], get_current_user_id(), $context);
        return self::normalize_slug_list($allowed);
    }

    private static function is_allowed_owner_slug_for_assignment(string $slug): bool
    {
        $allowed_slugs = self::get_allowed_owner_slugs('assign');
        if (empty($allowed_slugs)) {
            return true;
        }

        return in_array(sanitize_key($slug), $allowed_slugs, true);
    }

    private static function get_owner_terms_for_assignment(): array
    {
        $allowed_slugs = self::get_allowed_owner_slugs('assign');
        return self::get_owner_terms($allowed_slugs);
    }

    private static function get_owner_terms_for_view(): array
    {
        $allowed_slugs = self::get_allowed_owner_slugs('view');
        return self::get_owner_terms($allowed_slugs);
    }

    private static function get_owner_terms(array $allowed_slugs = []): array
    {
        $term_query = [
            'taxonomy' => self::TAXONOMY,
            'hide_empty' => false,
        ];

        $allowed_slugs = self::normalize_slug_list($allowed_slugs);
        if (!empty($allowed_slugs)) {
            $term_query['slug'] = $allowed_slugs;
        }

        $terms = get_terms($term_query);
        if (is_wp_error($terms) || empty($terms)) {
            return [];
        }

        return $terms;
    }

    private static function get_focus_meta_value(int $attachment_id, string $meta_key): ?float
    {
        return self::normalize_focus_value(get_post_meta($attachment_id, $meta_key, true));
    }

    private static function save_focus_meta_value(int $attachment_id, string $meta_key, $value): void
    {
        $normalized = self::normalize_focus_value($value);
        if ($normalized === null) {
            delete_post_meta($attachment_id, $meta_key);
            return;
        }

        update_post_meta($attachment_id, $meta_key, self::format_focus_value($normalized));
    }

    private static function normalize_focus_value($value): ?float
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return max(self::FOCUS_MIN, min(self::FOCUS_MAX, (float) $value));
    }

    private static function format_focus_value(?float $value): string
    {
        if ($value === null) {
            return '';
        }

        $formatted = number_format(round($value, 2), 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }

    private static function build_focus_style_declaration(array $focus): string
    {
        $focus_x = self::format_focus_value(isset($focus['x']) ? (float) $focus['x'] : self::FOCUS_DEFAULT);
        $focus_y = self::format_focus_value(isset($focus['y']) ? (float) $focus['y'] : self::FOCUS_DEFAULT);

        return '--iss-media-focus-x:' . $focus_x . '%;--iss-media-focus-y:' . $focus_y . '%;';
    }

    private static function merge_style_attribute(string $existing, string $append): string
    {
        $existing = trim($existing);
        if ($existing !== '' && substr($existing, -1) !== ';') {
            $existing .= ';';
        }

        return $existing . $append;
    }

    private static function normalize_slug_list($slugs): array
    {
        if (!is_array($slugs)) {
            $slugs = [$slugs];
        }

        $normalized = [];
        foreach ($slugs as $slug) {
            $slug = sanitize_key((string) $slug);
            if ($slug !== '') {
                $normalized[] = $slug;
            }
        }

        return array_values(array_unique($normalized));
    }
}

register_activation_hook(__FILE__, ['ISS_Media_Control', 'activate']);
ISS_Media_Control::init();

if (!function_exists('iss_media_control_get_attachment_focus_point')) {
    function iss_media_control_get_attachment_focus_point(int $attachment_id): array
    {
        return ISS_Media_Control::get_attachment_focus_point($attachment_id);
    }
}
