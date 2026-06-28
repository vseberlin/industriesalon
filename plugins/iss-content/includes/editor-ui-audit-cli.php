<?php

if (!defined('ABSPATH')) {
    exit;
}

final class ISS_Content_Model_Editor_UI_Audit_CLI_Command
{
    private const STATE_MUST_SHOW = 'must_show';
    private const STATE_INTEGRATED = 'integrated';
    private const STATE_HIDE_FOR_EDITORS = 'hide_for_editors';
    private const STATE_REVIEW = 'review';

    /**
     * Inventory and classify editor-facing surfaces by post type.
     *
     * ## OPTIONS
     *
     * [--role=<role>]
     * : Audit as the first user with this role. Default: editor.
     *
     * [--user=<id|login|email>]
     * : Audit as a specific user. Overrides --role.
     *
     * [--post-types=<list>]
     * : Comma-separated post types. Default: ISS editorial/public graph set.
     *
     * [--states=<list>]
     * : Comma-separated states to display. Use all for every state. Default: integrated,hide_for_editors,review.
     *
     * [--kinds=<list>]
     * : Comma-separated surface kinds to display. Use all to include save hooks. Default: editor/list/block surfaces.
     *
     * [--limit=<number>]
     * : Sample posts per post type for block inventory. Default: 3.
     *
     * [--format=<format>]
     * : Output format: table, json, or markdown. Default: table.
     *
     * ## EXAMPLES
     *
     *     wp iss-content editor-ui-audit
     *     wp iss-content editor-ui-audit --role=administrator --states=all
     *     wp iss-content editor-ui-audit --post-types=projekt,publication --format=json
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $format = sanitize_key((string) ($assoc_args['format'] ?? 'table'));
        if (!in_array($format, ['table', 'json', 'markdown'], true)) {
            $format = 'table';
        }

        $post_types = $this->resolve_post_types($assoc_args);
        if (!$post_types) {
            \WP_CLI::error('No matching post types found.');
        }

        $user = $this->resolve_user($assoc_args);
        if (!$user instanceof \WP_User) {
            \WP_CLI::error('No matching audit user found. Pass --user=<id|login|email> or a valid --role.');
        }

        $previous_user_id = get_current_user_id();
        wp_set_current_user((int) $user->ID);

        $reports = [];
        foreach ($post_types as $post_type) {
            $reports[$post_type] = $this->audit_post_type($post_type, $assoc_args);
        }

        wp_set_current_user($previous_user_id);

        $surfaces = $this->flatten_surfaces($reports);
        $summary = $this->summarize_surfaces($surfaces);
        $filtered_surfaces = $this->filter_surfaces($surfaces, $assoc_args);
        $payload = [
            'schema_version' => 1,
            'audit_user' => [
                'id' => (int) $user->ID,
                'login' => (string) $user->user_login,
                'roles' => array_values(array_map('sanitize_key', (array) $user->roles)),
            ],
            'filters' => [
                'post_types' => $post_types,
                'states' => $this->resolve_state_filter($assoc_args),
                'kinds' => $this->resolve_kind_filter($assoc_args),
            ],
            'summary' => $summary,
            'displayed_count' => count($filtered_surfaces),
            'post_types' => $reports,
            'items' => $filtered_surfaces,
        ];

        if ($format === 'json') {
            \WP_CLI::log((string) wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }

        if ($format === 'markdown') {
            $this->render_markdown($payload);
            return;
        }

        \WP_CLI::log(sprintf(
            'Editor UI audit: user=%s roles=%s post_types=%d surfaces=%d displayed=%d',
            (string) $user->user_login,
            implode(',', (array) $user->roles),
            count($post_types),
            count($surfaces),
            count($filtered_surfaces)
        ));
        \WP_CLI::log($this->format_summary($summary));

        if (!$filtered_surfaces) {
            \WP_CLI::success('No surfaces matched the selected filters.');
            return;
        }

        $this->render_table($this->to_table_rows($filtered_surfaces), [
            'post_type',
            'kind',
            'id',
            'state',
            'section',
            'owner',
            'reason',
        ]);
    }

    private function resolve_user(array $assoc_args): ?\WP_User
    {
        $user_token = trim((string) ($assoc_args['user'] ?? ''));
        if ($user_token !== '') {
            if (is_numeric($user_token)) {
                $user = get_user_by('id', absint($user_token));
            } elseif (is_email($user_token)) {
                $user = get_user_by('email', $user_token);
            } else {
                $user = get_user_by('login', sanitize_user($user_token));
            }

            return $user instanceof \WP_User ? $user : null;
        }

        $role = sanitize_key((string) ($assoc_args['role'] ?? 'editor'));
        if ($role === 'current') {
            $current = wp_get_current_user();
            return $current instanceof \WP_User && (int) $current->ID > 0 ? $current : null;
        }

        $users = get_users([
            'role' => $role,
            'number' => 1,
            'orderby' => 'ID',
            'order' => 'ASC',
        ]);

        return isset($users[0]) && $users[0] instanceof \WP_User ? $users[0] : null;
    }

    private function resolve_post_types(array $assoc_args): array
    {
        $raw = trim((string) ($assoc_args['post-types'] ?? ''));
        if ($raw !== '') {
            $post_types = array_map('sanitize_key', array_map('trim', explode(',', $raw)));
            return array_values(array_filter(array_unique($post_types), 'post_type_exists'));
        }

        $defaults = [
            ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
            ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
            ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
            'publication',
            'fuehrung',
            ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE,
            ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
            'page',
            'post',
            'archivobjekt',
            'archivsammlung',
            'register_place',
            ISS_CONTENT_MODEL_ENTITY_PROFILE_POST_TYPE,
        ];

        if (function_exists('iss_content_model_get_editor_dashboard_post_types')) {
            $defaults = array_merge($defaults, iss_content_model_get_editor_dashboard_post_types());
        }

        return array_values(array_filter(array_unique(array_map('sanitize_key', $defaults)), 'post_type_exists'));
    }

    private function audit_post_type(string $post_type, array $assoc_args): array
    {
        $post_type_object = get_post_type_object($post_type);
        $sample_post = $this->get_sample_post($post_type);
        $dashboard_sections = function_exists('iss_content_model_get_editor_dashboard_sections')
            ? iss_content_model_get_editor_dashboard_sections($post_type)
            : [];
        $dashboard_index = $this->build_dashboard_index($dashboard_sections);

        $metaboxes = $this->collect_metabox_surfaces($post_type, $sample_post, $dashboard_index);
        $selectors = $this->collect_dashboard_selector_surfaces($dashboard_sections);
        $taxonomies = $this->collect_taxonomy_surfaces($post_type, $dashboard_index);
        $meta = $this->collect_registered_meta_surfaces($post_type, $dashboard_index);
        $columns = $this->collect_list_column_surfaces($post_type);
        $save_hooks = $this->collect_save_hook_surfaces($post_type);
        $blocks = $this->collect_block_surfaces($post_type, $assoc_args);
        $screen_options = $this->collect_user_screen_options($post_type);

        $surfaces = array_merge($selectors, $metaboxes, $taxonomies, $meta, $columns, $save_hooks, $blocks, $screen_options);

        return [
            'label' => $post_type_object ? (string) $post_type_object->label : $post_type,
            'sample_post_id' => (int) $sample_post->ID,
            'block_editor' => use_block_editor_for_post_type($post_type),
            'uses_editor_dashboard' => function_exists('iss_content_model_use_editor_dashboard') && iss_content_model_use_editor_dashboard($post_type),
            'dashboard_sections' => $dashboard_sections,
            'summary' => $this->summarize_surfaces($surfaces),
            'surfaces' => $surfaces,
        ];
    }

    private function get_sample_post(string $post_type): \WP_Post
    {
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => 1,
            'orderby' => 'modified',
            'order' => 'DESC',
            'suppress_filters' => true,
        ]);

        if (isset($posts[0]) && $posts[0] instanceof \WP_Post) {
            return $posts[0];
        }

        return new \WP_Post((object) [
            'ID' => 0,
            'post_author' => get_current_user_id(),
            'post_date' => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', true),
            'post_content' => '',
            'post_title' => '',
            'post_excerpt' => '',
            'post_status' => 'draft',
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_password' => '',
            'post_name' => '',
            'to_ping' => '',
            'pinged' => '',
            'post_modified' => current_time('mysql'),
            'post_modified_gmt' => current_time('mysql', true),
            'post_content_filtered' => '',
            'post_parent' => 0,
            'guid' => '',
            'menu_order' => 0,
            'post_type' => $post_type,
            'post_mime_type' => '',
            'comment_count' => 0,
            'filter' => 'raw',
        ]);
    }

    private function build_dashboard_index(array $sections): array
    {
        $index = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $section_slug = sanitize_key((string) ($section['slug'] ?? ''));
            $section_label = sanitize_text_field((string) ($section['label'] ?? $section_slug));

            foreach ((array) ($section['boxIds'] ?? []) as $box_id) {
                $box_id = sanitize_key((string) $box_id);
                if ($box_id === '') {
                    continue;
                }
                $index[$box_id] = [
                    'section' => $section_slug,
                    'section_label' => $section_label,
                    'source' => 'box',
                ];
            }

            foreach ((array) ($section['modalTargets'] ?? []) as $target) {
                if (!is_array($target)) {
                    continue;
                }
                $target_id = sanitize_key((string) ($target['target'] ?? ''));
                if ($target_id === '') {
                    continue;
                }
                $index[$target_id] = [
                    'section' => $section_slug,
                    'section_label' => $section_label,
                    'source' => 'modal_target',
                ];
            }

            foreach ((array) ($section['selectors'] ?? []) as $selector) {
                $selector = trim((string) $selector);
                if ($selector === '') {
                    continue;
                }
                $index[$selector] = [
                    'section' => $section_slug,
                    'section_label' => $section_label,
                    'source' => 'selector',
                ];
            }
        }

        return $index;
    }

    private function collect_metabox_surfaces(string $post_type, \WP_Post $post, array $dashboard_index): array
    {
        global $wp_meta_boxes, $current_screen;

        $this->load_admin_metabox_runtime();

        $previous_meta_boxes = is_array($wp_meta_boxes ?? null) ? $wp_meta_boxes : [];
        $previous_screen = $current_screen ?? null;
        $wp_meta_boxes = [];

        if (function_exists('set_current_screen')) {
            set_current_screen($post_type);
        }

        $this->add_core_meta_boxes($post_type);
        do_action('add_meta_boxes', $post_type, $post);
        do_action("add_meta_boxes_{$post_type}", $post);

        $boxes = $this->flatten_metaboxes((array) ($wp_meta_boxes[$post_type] ?? []), $post_type, $dashboard_index);

        $wp_meta_boxes = $previous_meta_boxes;
        $current_screen = $previous_screen;

        return $boxes;
    }

    private function load_admin_metabox_runtime(): void
    {
        $files = [
            ABSPATH . 'wp-admin/includes/template.php',
            ABSPATH . 'wp-admin/includes/meta-boxes.php',
            ABSPATH . 'wp-admin/includes/screen.php',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                require_once $file;
            }
        }
    }

    private function add_core_meta_boxes(string $post_type): void
    {
        if (!function_exists('add_meta_box')) {
            return;
        }

        add_meta_box('submitdiv', __('Publish'), 'post_submit_meta_box', $post_type, 'side', 'core');

        if (post_type_supports($post_type, 'thumbnail')) {
            add_meta_box('postimagediv', esc_html($this->post_type_support_label($post_type, 'featured_image', __('Featured image'))), 'post_thumbnail_meta_box', $post_type, 'side', 'low');
        }

        if (post_type_supports($post_type, 'excerpt')) {
            add_meta_box('postexcerpt', __('Excerpt'), 'post_excerpt_meta_box', $post_type, 'normal', 'core');
        }

        if (post_type_supports($post_type, 'custom-fields')) {
            add_meta_box('postcustom', __('Custom Fields'), 'post_custom_meta_box', $post_type, 'normal', 'core');
        }

        if (post_type_supports($post_type, 'revisions')) {
            add_meta_box('revisionsdiv', __('Revisions'), 'post_revisions_meta_box', $post_type, 'normal', 'core');
        }

        if (post_type_supports($post_type, 'page-attributes')) {
            add_meta_box('pageparentdiv', __('Page Attributes'), 'page_attributes_meta_box', $post_type, 'side', 'core');
        }

        add_meta_box('slugdiv', __('Slug'), 'post_slug_meta_box', $post_type, 'normal', 'core');

        foreach (get_object_taxonomies($post_type, 'objects') as $taxonomy) {
            if (!$taxonomy instanceof \WP_Taxonomy || !$taxonomy->show_ui || $taxonomy->meta_box_cb === false) {
                continue;
            }

            $box_id = $taxonomy->hierarchical ? $taxonomy->name . 'div' : 'tagsdiv-' . $taxonomy->name;
            if ($taxonomy->name === 'category') {
                $box_id = 'categorydiv';
            }

            $callback = $taxonomy->meta_box_cb;
            if (!is_callable($callback)) {
                $callback = $taxonomy->hierarchical ? 'post_categories_meta_box' : 'post_tags_meta_box';
            }

            add_meta_box(
                $box_id,
                $taxonomy->labels->name,
                $callback,
                $post_type,
                'side',
                'core',
                ['taxonomy' => $taxonomy->name]
            );
        }
    }

    private function post_type_support_label(string $post_type, string $label_key, string $fallback): string
    {
        $labels = get_post_type_labels(get_post_type_object($post_type));
        return isset($labels->{$label_key}) ? (string) $labels->{$label_key} : $fallback;
    }

    private function flatten_metaboxes(array $contexts, string $post_type, array $dashboard_index): array
    {
        $surfaces = [];

        foreach ($contexts as $context => $priorities) {
            if (!is_array($priorities)) {
                continue;
            }

            foreach ($priorities as $priority => $boxes) {
                if (!is_array($boxes)) {
                    continue;
                }

                foreach ($boxes as $box_id => $box) {
                    if (!is_array($box) || empty($box['id'])) {
                        continue;
                    }

                    $id = sanitize_key((string) $box_id);
                    $classification = $this->classify_metabox($id, $post_type, $dashboard_index);
                    $surfaces[] = [
                        'kind' => 'metabox',
                        'id' => $id,
                        'label' => wp_strip_all_tags((string) ($box['title'] ?? $id)),
                        'state' => $classification['state'],
                        'reason' => $classification['reason'],
                        'owner' => $this->infer_owner($id),
                        'section' => (string) ($dashboard_index[$id]['section'] ?? ''),
                        'context' => sanitize_key((string) $context),
                        'priority' => sanitize_key((string) $priority),
                        'callback' => $this->callback_name($box['callback'] ?? null),
                    ];
                }
            }
        }

        usort($surfaces, static function (array $a, array $b): int {
            return [$a['context'], $a['priority'], $a['id']] <=> [$b['context'], $b['priority'], $b['id']];
        });

        return $surfaces;
    }

    private function collect_dashboard_selector_surfaces(array $sections): array
    {
        $surfaces = [];

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $section_slug = sanitize_key((string) ($section['slug'] ?? ''));
            foreach ((array) ($section['selectors'] ?? []) as $selector) {
                $selector = trim((string) $selector);
                if ($selector === '') {
                    continue;
                }

                $surfaces[] = [
                    'kind' => 'dashboard_selector',
                    'id' => $selector,
                    'label' => $selector,
                    'state' => $section_slug === 'composition' ? self::STATE_MUST_SHOW : self::STATE_REVIEW,
                    'reason' => $section_slug === 'composition'
                        ? 'Shared composition canvas anchor in the editor dashboard.'
                        : 'Dashboard selector contribution needs a named owner/action.',
                    'owner' => $this->infer_owner($selector),
                    'section' => $section_slug,
                ];
            }
        }

        return $surfaces;
    }

    private function collect_taxonomy_surfaces(string $post_type, array $dashboard_index): array
    {
        $surfaces = [];

        foreach (get_object_taxonomies($post_type, 'objects') as $taxonomy) {
            if (!$taxonomy instanceof \WP_Taxonomy) {
                continue;
            }

            $id = sanitize_key($taxonomy->name);
            $classification = $this->classify_taxonomy($taxonomy, $post_type, $dashboard_index);
            $surfaces[] = [
                'kind' => 'taxonomy',
                'id' => $id,
                'label' => (string) $taxonomy->labels->name,
                'state' => $classification['state'],
                'reason' => $classification['reason'],
                'owner' => $this->infer_owner($id),
                'section' => $classification['section'],
                'hierarchical' => (bool) $taxonomy->hierarchical,
                'show_ui' => (bool) $taxonomy->show_ui,
                'show_admin_column' => (bool) $taxonomy->show_admin_column,
                'show_in_rest' => (bool) $taxonomy->show_in_rest,
            ];
        }

        return $surfaces;
    }

    private function collect_registered_meta_surfaces(string $post_type, array $dashboard_index): array
    {
        $surfaces = [];
        $registered = function_exists('get_registered_meta_keys') ? get_registered_meta_keys('post', $post_type) : [];

        foreach ($registered as $key => $args) {
            $key = (string) $key;
            $classification = $this->classify_meta_key($key, $post_type, $dashboard_index);
            $surfaces[] = [
                'kind' => 'registered_meta',
                'id' => $key,
                'label' => $key,
                'state' => $classification['state'],
                'reason' => $classification['reason'],
                'owner' => $this->infer_owner($key),
                'section' => $classification['section'],
                'type' => sanitize_key((string) ($args['type'] ?? '')),
                'single' => (bool) ($args['single'] ?? false),
                'show_in_rest' => !empty($args['show_in_rest']),
            ];
        }

        return $surfaces;
    }

    private function collect_list_column_surfaces(string $post_type): array
    {
        $columns = [
            'cb' => '<input type="checkbox" />',
            'title' => __('Title'),
        ];

        if (post_type_supports($post_type, 'author')) {
            $columns['author'] = __('Author');
        }

        foreach (get_object_taxonomies($post_type, 'objects') as $taxonomy) {
            if ($taxonomy instanceof \WP_Taxonomy && $taxonomy->show_admin_column) {
                $columns['taxonomy-' . $taxonomy->name] = (string) $taxonomy->labels->name;
            }
        }

        if (post_type_supports($post_type, 'comments')) {
            $columns['comments'] = __('Comments');
        }

        $columns['date'] = __('Date');

        if ($post_type === 'page') {
            $columns = apply_filters('manage_pages_columns', $columns);
        } else {
            $columns = apply_filters('manage_posts_columns', $columns, $post_type);
        }
        $columns = apply_filters("manage_{$post_type}_posts_columns", $columns);

        $surfaces = [];
        foreach ($columns as $column_id => $label) {
            $column_id = sanitize_key((string) $column_id);
            $classification = $this->classify_list_column($column_id);
            $surfaces[] = [
                'kind' => 'list_column',
                'id' => $column_id,
                'label' => wp_strip_all_tags((string) $label),
                'state' => $classification['state'],
                'reason' => $classification['reason'],
                'owner' => $this->infer_owner($column_id),
                'section' => 'list_table',
            ];
        }

        return $surfaces;
    }

    private function collect_save_hook_surfaces(string $post_type): array
    {
        $surfaces = [];

        foreach (['save_post', 'save_post_' . $post_type] as $hook_name) {
            foreach ($this->hook_callbacks($hook_name) as $callback) {
                $id = $hook_name . ':' . $callback['callback'];
                $surfaces[] = [
                    'kind' => 'save_hook',
                    'id' => $id,
                    'label' => $callback['callback'],
                    'state' => self::STATE_REVIEW,
                    'reason' => 'Save path owner must be checked before hiding or merging UI.',
                    'owner' => $this->infer_owner($callback['callback']),
                    'section' => 'save_path',
                    'priority' => $callback['priority'],
                    'accepted_args' => $callback['accepted_args'],
                ];
            }
        }

        return $surfaces;
    }

    private function collect_block_surfaces(string $post_type, array $assoc_args): array
    {
        $limit = isset($assoc_args['limit']) ? max(1, min(25, (int) $assoc_args['limit'])) : 3;
        $posts = get_posts([
            'post_type' => $post_type,
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => $limit,
            'orderby' => 'modified',
            'order' => 'DESC',
            'suppress_filters' => true,
        ]);

        $counts = [];
        foreach ($posts as $post) {
            if (!$post instanceof \WP_Post || trim((string) $post->post_content) === '') {
                continue;
            }
            $this->count_blocks(parse_blocks((string) $post->post_content), $counts);
        }

        $surfaces = [];
        foreach ($counts as $block_name => $count) {
            $classification = $this->classify_block_name($block_name, $post_type);
            $surfaces[] = [
                'kind' => 'content_block',
                'id' => $block_name,
                'label' => $block_name,
                'state' => $classification['state'],
                'reason' => $classification['reason'],
                'owner' => $this->infer_owner($block_name),
                'section' => 'content',
                'count' => (int) $count,
                'sample_size' => count($posts),
            ];
        }

        usort($surfaces, static function (array $a, array $b): int {
            return (string) ($a['id'] ?? '') <=> (string) ($b['id'] ?? '');
        });
        return array_values($surfaces);
    }

    private function collect_user_screen_options(string $post_type): array
    {
        $surfaces = [];
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return $surfaces;
        }

        foreach (['metaboxhidden_' . $post_type, 'closedpostboxes_' . $post_type] as $option) {
            $value = get_user_option($option, $user_id);
            if (!is_array($value)) {
                continue;
            }

            foreach ($value as $box_id) {
                $box_id = sanitize_key((string) $box_id);
                if ($box_id === '') {
                    continue;
                }

                $surfaces[] = [
                    'kind' => 'screen_option',
                    'id' => $option . ':' . $box_id,
                    'label' => $box_id,
                    'state' => self::STATE_REVIEW,
                    'reason' => 'User-specific Screen Options can mask the real registration state.',
                    'owner' => $this->infer_owner($box_id),
                    'section' => 'screen_options',
                ];
            }
        }

        return $surfaces;
    }

    private function classify_metabox(string $id, string $post_type, array $dashboard_index): array
    {
        $technical = [
            'postcustom',
            'slugdiv',
            'revisionsdiv',
            'pageparentdiv',
            'categorydiv',
            'tagsdiv-post_tag',
            'iss-graph-search-signal',
            'iss-graph-availability-signal',
            'iss-graph-editorial-signals',
            'iss-wf-import-suggestions',
            'iss-graph-video-transcript-review',
        ];

        if (in_array($id, $technical, true) || str_starts_with($id, 'tagsdiv-')) {
            return [
                'state' => self::STATE_HIDE_FOR_EDITORS,
                'reason' => 'Shared wholesale simplification candidate: technical, raw taxonomy, diagnostic, revision, or migration/review surface.',
            ];
        }

        if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE && $id === ISS_CONTENT_MODEL_TOPIC_TAXONOMY . 'div') {
            return [
                'state' => self::STATE_HIDE_FOR_EDITORS,
                'reason' => 'Raw event topic taxonomy stays list/data state until a curated Veranstaltung topic control exists.',
            ];
        }

        $must_show = [
            'submitdiv',
            'postexcerpt',
            'postimagediv',
            'iss-content-model-veranstaltung',
            'iss-content-model-veranstaltung-type',
            'iss-content-model-veranstaltung-content',
            'iss-content-model-veranstaltung-status',
            'iss-content-model-ausstellung',
            'acf-group_iss_ausstellung_controls',
            'iss-content-model-projekt',
            'iss-content-model-video',
            'iss-content-model-video-transcript-json',
            'iss-publication-bibliography',
            'iss-publication-display',
            'iss-publication-sale',
            'iss-fuehrung-data',
            'iss-occurrences-calendar-mapping',
            'iss-graph-register-place',
            'iss-graph-entity-profile-link',
            'iss-graph-entity-profile-facts',
            'iss-graph-entity-profile-aliases',
        ];

        if (in_array($id, $must_show, true)) {
            return [
                'state' => self::STATE_MUST_SHOW,
                'reason' => 'Required identity, fact, composition, publication, or graph-backbone editor control.',
            ];
        }

        $integrated = [
            'iss-relations-places',
            'iss-graph-public-content-relations',
            'iss-wf-import-archive-picker',
            'iss-content-editorial-sets',
            'iss-publication-related-publications',
            'iss-graph-related-promotion',
        ];

        if (in_array($id, $integrated, true)) {
            return [
                'state' => self::STATE_INTEGRATED,
                'reason' => $id === 'iss-graph-related-promotion'
                    ? 'Editorial promotion control should remain easy to switch off and be paired with list-table filters.'
                    : 'Useful editorial action, but target direction is an integrated dashboard/reference panel.',
            ];
        }

        $section = (string) ($dashboard_index[$id]['section'] ?? '');
        if ($section !== '') {
            if (in_array($section, ['identity', 'composition', 'facts', 'publish', 'commerce'], true)) {
                return [
                    'state' => self::STATE_MUST_SHOW,
                    'reason' => 'Listed in the shared dashboard as a primary editor section.',
                ];
            }

            return [
                'state' => self::STATE_INTEGRATED,
                'reason' => 'Listed in the shared dashboard but should be presented as one coherent panel.',
            ];
        }

        if ($post_type === 'page' || $post_type === 'post') {
            return [
                'state' => self::STATE_REVIEW,
                'reason' => 'Block-editor screen; needs a separate Gutenberg/document-panel adapter decision.',
            ];
        }

        return [
            'state' => self::STATE_REVIEW,
            'reason' => 'Unclassified metabox; inspect owner, render usage, and save path before hiding.',
        ];
    }

    private function classify_taxonomy(\WP_Taxonomy $taxonomy, string $post_type, array $dashboard_index): array
    {
        $box_id = $taxonomy->hierarchical ? $taxonomy->name . 'div' : 'tagsdiv-' . $taxonomy->name;
        if ($taxonomy->name === 'category') {
            $box_id = 'categorydiv';
        }

        if (!$taxonomy->show_ui) {
            return [
                'state' => self::STATE_HIDE_FOR_EDITORS,
                'reason' => 'Taxonomy has no native UI; treat as storage/projection state.',
                'section' => '',
            ];
        }

        if (in_array($taxonomy->name, ['category', 'post_tag', ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY], true)) {
            return [
                'state' => self::STATE_HIDE_FOR_EDITORS,
                'reason' => 'Raw taxonomy UI should be hidden where curated facts/editor controls own the decision.',
                'section' => (string) ($dashboard_index[$box_id]['section'] ?? ''),
            ];
        }

        if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE && $taxonomy->name === ISS_CONTENT_MODEL_TOPIC_TAXONOMY) {
            return [
                'state' => self::STATE_HIDE_FOR_EDITORS,
                'reason' => 'Raw event topic taxonomy stays list/data state until a curated Veranstaltung topic control exists.',
                'section' => '',
            ];
        }

        if (isset($dashboard_index[$box_id])) {
            return [
                'state' => self::STATE_INTEGRATED,
                'reason' => 'Taxonomy box participates in dashboard grouping; verify one normal editing authority.',
                'section' => (string) $dashboard_index[$box_id]['section'],
            ];
        }

        return [
            'state' => self::STATE_REVIEW,
            'reason' => $post_type === 'page' || $post_type === 'post'
                ? 'Native taxonomy surface on a block-editor screen; decide Gutenberg panel policy.'
                : 'Native taxonomy surface needs classification: editorial field, list filter, or technical state.',
            'section' => '',
        ];
    }

    private function classify_meta_key(string $key, string $post_type, array $dashboard_index): array
    {
        $must_show_prefixes = [
            'iss_start',
            'iss_end',
            'iss_period',
            'iss_programme',
            'iss_public_overview',
            'iss_primary_place_id',
            'iss_video_',
            'iss_duration',
            'iss_booking',
            'iss_price',
        ];

        foreach ($must_show_prefixes as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return [
                    'state' => self::STATE_MUST_SHOW,
                    'reason' => 'Registered meta backs required public facts or operational rendering.',
                    'section' => 'facts',
                ];
            }
        }

        if (str_starts_with($key, '_iss_editorial_') || $key === '_iss_content_json' || $key === '_iss_entity_key') {
            return [
                'state' => self::STATE_MUST_SHOW,
                'reason' => 'Registered meta backs the primary structured document or content entity contract.',
                'section' => 'composition',
            ];
        }

        if ($key === 'iss_related_places' || str_contains($key, 'related') || str_contains($key, 'relation')) {
            return [
                'state' => self::STATE_INTEGRATED,
                'reason' => 'Relation/reference storage should be edited through an integrated relation panel.',
                'section' => (string) ($dashboard_index['iss-relations-places']['section'] ?? 'relations'),
            ];
        }

        if (str_contains($key, 'graph') || str_contains($key, 'signal') || str_starts_with($key, '_')) {
            return [
                'state' => self::STATE_HIDE_FOR_EDITORS,
                'reason' => 'Technical/private registered meta; expose only through named editor controls if needed.',
                'section' => '',
            ];
        }

        if ($post_type === 'publication' && str_starts_with($key, 'iss_publication_')) {
            return [
                'state' => self::STATE_MUST_SHOW,
                'reason' => 'Publication meta backs public bibliography, display, sale, or source behavior.',
                'section' => 'facts',
            ];
        }

        return [
            'state' => self::STATE_REVIEW,
            'reason' => 'Registered meta needs owner and editor-surface classification.',
            'section' => '',
        ];
    }

    private function classify_list_column(string $column_id): array
    {
        $core = ['cb', 'title', 'author', 'date', 'comments'];
        if (in_array($column_id, $core, true)) {
            return [
                'state' => self::STATE_MUST_SHOW,
                'reason' => 'Core list-table column.',
            ];
        }

        $useful = [
            'iss_project_front_page_order',
            'iss_entity_key',
            'iss_veranstaltung_semantic',
            'iss_graph_related_promotion',
            'taxonomy-ausstellung_typ',
        ];
        if (in_array($column_id, $useful, true)) {
            return [
                'state' => self::STATE_INTEGRATED,
                'reason' => 'Useful management/status column; keep list-table behavior coherent with editor controls.',
            ];
        }

        if (str_starts_with($column_id, 'taxonomy-')) {
            return [
                'state' => self::STATE_REVIEW,
                'reason' => 'Taxonomy column may be useful for filtering but should not imply raw editor UI.',
            ];
        }

        return [
            'state' => self::STATE_REVIEW,
            'reason' => 'Custom list-table column needs owner and editorial value classification.',
        ];
    }

    private function classify_block_name(string $block_name, string $post_type): array
    {
        if (str_starts_with($block_name, 'iss/')) {
            return [
                'state' => self::STATE_REVIEW,
                'reason' => 'ISS block found in sampled content; decide whether it remains editor-facing or migrates into JSON/dashboard controls.',
            ];
        }

        if (in_array($post_type, ['page', 'post', ISS_CONTENT_MODEL_VIDEO_POST_TYPE], true)) {
            return [
                'state' => self::STATE_MUST_SHOW,
                'reason' => 'Block content belongs to a current block-editor screen.',
            ];
        }

        return [
            'state' => self::STATE_REVIEW,
            'reason' => 'Legacy block content on an editorial-dashboard screen; inspect before hiding editor surfaces.',
        ];
    }

    private function hook_callbacks(string $hook_name): array
    {
        global $wp_filter;

        $hook = $wp_filter[$hook_name] ?? null;
        if (!$hook instanceof \WP_Hook) {
            return [];
        }

        $callbacks = [];
        foreach ($hook->callbacks as $priority => $items) {
            foreach ($items as $item) {
                $callbacks[] = [
                    'priority' => (int) $priority,
                    'callback' => $this->callback_name($item['function'] ?? null),
                    'accepted_args' => (int) ($item['accepted_args'] ?? 0),
                ];
            }
        }

        usort($callbacks, static function (array $a, array $b): int {
            return [$a['priority'], $a['callback']] <=> [$b['priority'], $b['callback']];
        });

        return $callbacks;
    }

    private function count_blocks(array $blocks, array &$counts): void
    {
        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $name = trim((string) ($block['blockName'] ?? ''));
            if ($name !== '') {
                $counts[$name] = (int) ($counts[$name] ?? 0) + 1;
            }

            $inner_blocks = (array) ($block['innerBlocks'] ?? []);
            if ($inner_blocks) {
                $this->count_blocks($inner_blocks, $counts);
            }
        }
    }

    private function callback_name($callback): string
    {
        if (is_string($callback)) {
            return $callback;
        }

        if (is_array($callback) && count($callback) === 2) {
            $owner = is_object($callback[0]) ? get_class($callback[0]) : (string) $callback[0];
            return $owner . '::' . (string) $callback[1];
        }

        if ($callback instanceof \Closure) {
            return 'Closure';
        }

        if (is_object($callback)) {
            return get_class($callback);
        }

        return '';
    }

    private function infer_owner(string $id): string
    {
        $id = strtolower($id);
        if (str_contains($id, 'iss_content') || str_starts_with($id, 'iss-content')) {
            return 'iss-content';
        }
        if (str_contains($id, 'iss_editorial') || str_starts_with($id, 'iss-editorial') || str_contains($id, 'iss-editorial')) {
            return 'iss-editorial';
        }
        if (str_contains($id, 'iss_graph') || str_starts_with($id, 'iss-graph') || str_contains($id, 'iss-graph')) {
            return 'iss-graph';
        }
        if (str_contains($id, 'iss_relations') || str_starts_with($id, 'iss-relations') || str_contains($id, 'iss-relations')) {
            return 'iss-relations';
        }
        if (str_contains($id, 'iss_wf') || str_starts_with($id, 'iss-wf') || str_contains($id, 'archive')) {
            return 'iss-archive';
        }
        if (str_contains($id, 'iss_publication') || str_starts_with($id, 'iss-publication')) {
            return 'iss-publications';
        }
        if (str_starts_with($id, 'acf-')) {
            return 'acf/iss-content';
        }
        if (str_starts_with($id, 'wp_') || in_array($id, ['submitdiv', 'postexcerpt', 'postimagediv', 'slugdiv', 'postcustom', 'revisionsdiv', 'pageparentdiv', 'categorydiv', 'tagsdiv-post_tag'], true)) {
            return 'wordpress';
        }

        return '';
    }

    private function flatten_surfaces(array $reports): array
    {
        $surfaces = [];
        foreach ($reports as $post_type => $report) {
            foreach ((array) ($report['surfaces'] ?? []) as $surface) {
                if (!is_array($surface)) {
                    continue;
                }
                $surface['post_type'] = (string) $post_type;
                $surfaces[] = $surface;
            }
        }

        return $surfaces;
    }

    private function summarize_surfaces(array $surfaces): array
    {
        $summary = [
            'total' => 0,
            self::STATE_MUST_SHOW => 0,
            self::STATE_INTEGRATED => 0,
            self::STATE_HIDE_FOR_EDITORS => 0,
            self::STATE_REVIEW => 0,
            'by_kind' => [],
        ];

        foreach ($surfaces as $surface) {
            ++$summary['total'];
            $state = (string) ($surface['state'] ?? self::STATE_REVIEW);
            if (!isset($summary[$state])) {
                $summary[$state] = 0;
            }
            ++$summary[$state];

            $kind = (string) ($surface['kind'] ?? 'unknown');
            if (!isset($summary['by_kind'][$kind])) {
                $summary['by_kind'][$kind] = 0;
            }
            ++$summary['by_kind'][$kind];
        }

        ksort($summary['by_kind']);
        return $summary;
    }

    private function filter_surfaces(array $surfaces, array $assoc_args): array
    {
        $state_filter = $this->resolve_state_filter($assoc_args);
        $kind_filter = $this->resolve_kind_filter($assoc_args);
        $filtered = [];

        foreach ($surfaces as $surface) {
            $state = (string) ($surface['state'] ?? '');
            $kind = (string) ($surface['kind'] ?? '');

            if ($state_filter && !in_array($state, $state_filter, true)) {
                continue;
            }
            if ($kind_filter && !in_array($kind, $kind_filter, true)) {
                continue;
            }

            $filtered[] = $surface;
        }

        return $filtered;
    }

    private function resolve_state_filter(array $assoc_args): array
    {
        $raw = trim((string) ($assoc_args['states'] ?? 'integrated,hide_for_editors,review'));
        if ($raw === '' || strtolower($raw) === 'all') {
            return [];
        }

        return array_values(array_filter(array_unique(array_map('sanitize_key', array_map('trim', explode(',', $raw))))));
    }

    private function resolve_kind_filter(array $assoc_args): array
    {
        $raw = trim((string) ($assoc_args['kinds'] ?? 'metabox,dashboard_selector,taxonomy,registered_meta,list_column,content_block,screen_option'));
        if ($raw === '' || strtolower($raw) === 'all') {
            return [];
        }

        return array_values(array_filter(array_unique(array_map('sanitize_key', array_map('trim', explode(',', $raw))))));
    }

    private function to_table_rows(array $surfaces): array
    {
        return array_map(static function (array $surface): array {
            return [
                'post_type' => (string) ($surface['post_type'] ?? ''),
                'kind' => (string) ($surface['kind'] ?? ''),
                'id' => (string) ($surface['id'] ?? ''),
                'state' => (string) ($surface['state'] ?? ''),
                'section' => (string) ($surface['section'] ?? ''),
                'owner' => (string) ($surface['owner'] ?? ''),
                'reason' => (string) ($surface['reason'] ?? ''),
            ];
        }, $surfaces);
    }

    private function render_table(array $rows, array $fields): void
    {
        $formatter = '\\WP_CLI\\Utils\\format_items';
        if (is_callable($formatter)) {
            call_user_func($formatter, 'table', $rows, $fields);
            return;
        }

        \WP_CLI::log(implode("\t", $fields));
        foreach ($rows as $row) {
            $values = [];
            foreach ($fields as $field) {
                $values[] = str_replace(["\t", "\n"], ' ', (string) ($row[$field] ?? ''));
            }
            \WP_CLI::log(implode("\t", $values));
        }
    }

    private function format_summary(array $summary): string
    {
        return sprintf(
            'Summary: total=%d must_show=%d integrated=%d hide_for_editors=%d review=%d',
            (int) ($summary['total'] ?? 0),
            (int) ($summary[self::STATE_MUST_SHOW] ?? 0),
            (int) ($summary[self::STATE_INTEGRATED] ?? 0),
            (int) ($summary[self::STATE_HIDE_FOR_EDITORS] ?? 0),
            (int) ($summary[self::STATE_REVIEW] ?? 0)
        );
    }

    private function render_markdown(array $payload): void
    {
        $summary = (array) ($payload['summary'] ?? []);
        \WP_CLI::log('# Editor UI Audit');
        \WP_CLI::log('');
        \WP_CLI::log('- Audit user: ' . (string) ($payload['audit_user']['login'] ?? ''));
        \WP_CLI::log('- ' . $this->format_summary($summary));
        \WP_CLI::log('- Displayed: ' . (string) ($payload['displayed_count'] ?? 0));
        \WP_CLI::log('');
        \WP_CLI::log('| Post type | Kind | ID | State | Section | Owner | Reason |');
        \WP_CLI::log('| --- | --- | --- | --- | --- | --- | --- |');

        foreach ((array) ($payload['items'] ?? []) as $surface) {
            if (!is_array($surface)) {
                continue;
            }

            \WP_CLI::log(sprintf(
                '| %s | %s | `%s` | %s | %s | %s | %s |',
                $this->markdown_cell((string) ($surface['post_type'] ?? '')),
                $this->markdown_cell((string) ($surface['kind'] ?? '')),
                str_replace('`', '\`', (string) ($surface['id'] ?? '')),
                $this->markdown_cell((string) ($surface['state'] ?? '')),
                $this->markdown_cell((string) ($surface['section'] ?? '')),
                $this->markdown_cell((string) ($surface['owner'] ?? '')),
                $this->markdown_cell((string) ($surface['reason'] ?? ''))
            ));
        }
    }

    private function markdown_cell(string $value): string
    {
        return str_replace(["\n", '|'], [' ', '\|'], $value);
    }
}

\WP_CLI::add_command('iss-content editor-ui-audit', 'ISS_Content_Model_Editor_UI_Audit_CLI_Command');
\WP_CLI::add_command('iss-content-model editor-ui-audit', 'ISS_Content_Model_Editor_UI_Audit_CLI_Command');
