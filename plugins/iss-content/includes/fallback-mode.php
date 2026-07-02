<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_fallback_route_pages(): array
{
    $routes = function_exists('iss_content_model_landing_route_map') ? iss_content_model_landing_route_map() : [];
    $labels = [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE => __('Veranstaltungen', 'iss-content-model'),
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE => __('Ausstellungen', 'iss-content-model'),
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE => __('Projekte', 'iss-content-model'),
        ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE => __('Rückblicke', 'iss-content-model'),
        'fuehrung' => __('Führungen', 'iss-content-model'),
        'publication' => __('Publikationen', 'iss-content-model'),
    ];

    $pages = [];
    foreach ($routes as $source_type => $slug) {
        $source_type = sanitize_key((string) $source_type);
        $pages[$source_type] = [
            'title' => (string) ($labels[$source_type] ?? ucfirst($source_type)),
            'slug' => sanitize_title((string) $slug),
            'category_slug' => iss_content_fallback_category_slug_for_source($source_type),
        ];
    }

    $pages['aktuelles'] = [
        'title' => __('Aktuelles', 'iss-content-model'),
        'slug' => 'aktuelles',
        'category_slug' => 'iss-aktuelles',
    ];

    return $pages;
}

function iss_content_fallback_category_archive_url(string $slug): string
{
    $term = get_term_by('slug', sanitize_title($slug), 'category');
    if (!$term instanceof WP_Term) {
        return home_url('/');
    }

    $url = get_term_link($term);
    return is_wp_error($url) ? home_url('/') : (string) $url;
}

function iss_content_fallback_page_content(string $title, string $category_slug): string
{
    $category_url = iss_content_fallback_category_archive_url($category_slug);
    return '<!-- wp:paragraph --><p>' . esc_html__('Diese Seite ist Teil des vereinfachten WordPress-Fallbacks.', 'iss-content-model') . '</p><!-- /wp:paragraph -->'
        . '<!-- wp:paragraph --><p><a href="' . esc_url($category_url) . '">' . esc_html(sprintf(__('Alle Beiträge in %s ansehen', 'iss-content-model'), $title)) . '</a></p><!-- /wp:paragraph -->';
}

function iss_content_fallback_find_page_by_key(string $key): int
{
    $ids = get_posts([
        'post_type' => 'page',
        'post_status' => ['publish', 'draft', 'private', 'pending'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
        'meta_key' => '_iss_fallback_page_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Fallback page lookup is low-volume operational setup.
        'meta_value' => sanitize_key($key), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Fallback page lookup is low-volume operational setup.
    ]);

    return $ids ? (int) $ids[0] : 0;
}

function iss_content_fallback_route_page_snapshots(): array
{
    $snapshots = [];
    foreach (iss_content_fallback_route_pages() as $key => $config) {
        $slug = sanitize_title((string) ($config['slug'] ?? ''));
        if ($slug === '') {
            continue;
        }
        $page = get_page_by_path($slug, OBJECT, 'page');
        if (!$page instanceof WP_Post) {
            continue;
        }
        $snapshots[sanitize_key((string) $key)] = [
            'ID' => (int) $page->ID,
            'post_title' => (string) $page->post_title,
            'post_name' => (string) $page->post_name,
            'post_content' => (string) $page->post_content,
            'post_excerpt' => (string) $page->post_excerpt,
            'post_status' => (string) $page->post_status,
            'page_template' => (string) get_page_template_slug((int) $page->ID),
        ];
    }

    return $snapshots;
}

function iss_content_fallback_ensure_page(string $key, array $config, bool $publish = false): int
{
    $key = sanitize_key($key);
    $slug = sanitize_title((string) ($config['slug'] ?? $key));
    $route_page = $publish && $slug !== '' ? get_page_by_path($slug, OBJECT, 'page') : null;
    $post_id = $route_page instanceof WP_Post ? (int) $route_page->ID : iss_content_fallback_find_page_by_key($key);
    $status = $publish ? 'publish' : 'draft';
    $data = [
        'post_type' => 'page',
        'post_title' => sanitize_text_field((string) ($config['title'] ?? $key)),
        'post_name' => $slug,
        'post_content' => iss_content_fallback_page_content((string) ($config['title'] ?? $key), (string) ($config['category_slug'] ?? '')),
        'post_status' => $status,
    ];

    if ($post_id > 0) {
        $data['ID'] = $post_id;
        wp_update_post(wp_slash($data));
    } else {
        $post_id = (int) wp_insert_post(wp_slash($data));
    }

    if ($post_id > 0) {
        if (!$route_page instanceof WP_Post) {
            update_post_meta($post_id, '_iss_fallback_page_key', $key);
            update_post_meta($post_id, '_iss_fallback_origin', 'fallback-native');
            if (!empty($config['category_slug'])) {
                wp_set_object_terms($post_id, [sanitize_title((string) $config['category_slug'])], 'category', false);
            }
        }
    }

    return $post_id;
}

function iss_content_fallback_front_page_content(): string
{
    $items = [];
    foreach (iss_content_fallback_route_pages() as $config) {
        $url = home_url('/' . trim((string) ($config['slug'] ?? ''), '/') . '/');
        $items[] = '<li><a href="' . esc_url($url) . '">' . esc_html((string) ($config['title'] ?? '')) . '</a></li>';
    }

    return '<!-- wp:paragraph --><p>' . esc_html__('Vereinfachte WordPress-Startseite fuer den Fallback-Betrieb.', 'iss-content-model') . '</p><!-- /wp:paragraph -->'
        . '<!-- wp:list --><ul>' . implode('', $items) . '</ul><!-- /wp:list -->';
}

function iss_content_fallback_ensure_front_page(bool $publish = false): int
{
    $post_id = iss_content_fallback_find_page_by_key('front');
    $data = [
        'post_type' => 'page',
        'post_title' => __('Startseite', 'iss-content-model'),
        'post_name' => 'fallback-startseite',
        'post_content' => iss_content_fallback_front_page_content(),
        'post_status' => $publish ? 'publish' : 'draft',
    ];

    if ($post_id > 0) {
        $data['ID'] = $post_id;
        wp_update_post(wp_slash($data));
    } else {
        $post_id = (int) wp_insert_post(wp_slash($data));
    }

    if ($post_id > 0) {
        update_post_meta($post_id, '_iss_fallback_origin', 'fallback-native');
        update_post_meta($post_id, '_iss_fallback_page_key', 'front');
        wp_set_object_terms($post_id, ['iss-seiten'], 'category', false);
    }

    return $post_id;
}

function iss_content_fallback_ensure_menu(array $page_ids): int
{
    $menu = wp_get_nav_menu_object('Fallback');
    if (!$menu instanceof WP_Term) {
        $menu_id = (int) wp_create_nav_menu('Fallback');
    } else {
        $menu_id = (int) $menu->term_id;
    }
    if ($menu_id <= 0) {
        return 0;
    }

    $existing_items = wp_get_nav_menu_items($menu_id);
    foreach ((array) $existing_items as $item) {
        if ($item instanceof WP_Post) {
            wp_delete_post((int) $item->ID, true);
        }
    }

    foreach ($page_ids as $page_id) {
        $page_id = (int) $page_id;
        if ($page_id <= 0) {
            continue;
        }
        wp_update_nav_menu_item($menu_id, 0, [
            'menu-item-title' => get_the_title($page_id),
            'menu-item-object' => 'page',
            'menu-item-object-id' => $page_id,
            'menu-item-type' => 'post_type',
            'menu-item-status' => 'publish',
        ]);
    }

    return $menu_id;
}

function iss_content_fallback_prepare_structures(bool $publish_pages = false): array
{
    $page_ids = [];
    foreach (iss_content_fallback_route_pages() as $key => $config) {
        $page_ids[$key] = iss_content_fallback_ensure_page((string) $key, $config, $publish_pages);
    }
    $front_page_id = iss_content_fallback_ensure_front_page($publish_pages);
    $menu_id = iss_content_fallback_ensure_menu(array_values($page_ids));

    return [
        'pages' => $page_ids,
        'front_page_id' => $front_page_id,
        'menu_id' => $menu_id,
    ];
}

function iss_content_fallback_generated_ids(): array
{
    return get_posts([
        'post_type' => ['post', 'page'],
        'post_status' => ['publish', 'draft', 'private', 'pending', 'future'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
        'meta_key' => '_iss_fallback_origin', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Mode switch reads low-volume generated fallback posts.
        'meta_value' => 'generated', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Mode switch reads low-volume generated fallback posts.
    ]);
}

function iss_content_fallback_set_generated_statuses(bool $enabled): int
{
    $count = 0;
    foreach (iss_content_fallback_generated_ids() as $post_id) {
        $post_id = (int) $post_id;
        $status = $enabled ? sanitize_key((string) get_post_meta($post_id, '_iss_fallback_public_status', true)) : 'draft';
        if (!in_array($status, ['publish', 'private'], true)) {
            $status = 'publish';
        }
        if (!$enabled) {
            $status = 'draft';
        }
        wp_update_post([
            'ID' => $post_id,
            'post_status' => $status,
        ]);
        ++$count;
    }

    return $count;
}

function iss_content_fallback_primary_menu_location(): string
{
    $locations = get_registered_nav_menus();
    foreach (['primary', 'menu-1', 'main', 'header'] as $candidate) {
        if (isset($locations[$candidate])) {
            return $candidate;
        }
    }

    $keys = array_keys($locations);
    return (string) ($keys[0] ?? 'primary');
}

function iss_content_fallback_enable(): array
{
    $route_snapshots = iss_content_fallback_route_page_snapshots();
    $structures = iss_content_fallback_prepare_structures(true);
    $previous = [
        'nav_menu_locations' => get_theme_mod('nav_menu_locations', []),
        'show_on_front' => get_option('show_on_front'),
        'page_on_front' => get_option('page_on_front'),
        'page_for_posts' => get_option('page_for_posts'),
    ];

    $locations = is_array($previous['nav_menu_locations']) ? $previous['nav_menu_locations'] : [];
    $primary = iss_content_fallback_primary_menu_location();
    if (!empty($structures['menu_id'])) {
        $locations[$primary] = (int) $structures['menu_id'];
        set_theme_mod('nav_menu_locations', $locations);
    }
    if (!empty($structures['front_page_id'])) {
        update_option('show_on_front', 'page', false);
        update_option('page_on_front', (int) $structures['front_page_id'], false);
    }

    update_option(ISS_CONTENT_FALLBACK_STATE_OPTION, [
        'previous' => $previous,
        'enabled_at' => current_time('mysql'),
        'enabled_by' => get_current_user_id(),
        'structures' => $structures,
        'route_page_snapshots' => $route_snapshots,
    ], false);
    update_option(ISS_CONTENT_FALLBACK_MODE_OPTION, '1', false);
    $changed = iss_content_fallback_set_generated_statuses(true);
    flush_rewrite_rules(false);

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('fallback_mode_enabled', [
            'result' => 'completed',
            'object_ids' => array_values(array_filter(array_map('intval', (array) ($structures['pages'] ?? [])))),
        ]);
    }

    return ['enabled' => true, 'generated_changed' => $changed, 'structures' => $structures];
}

function iss_content_fallback_disable(): array
{
    $state = get_option(ISS_CONTENT_FALLBACK_STATE_OPTION, []);
    $previous = is_array($state) && is_array($state['previous'] ?? null) ? $state['previous'] : [];
    $structures = is_array($state) && is_array($state['structures'] ?? null) ? $state['structures'] : iss_content_fallback_prepare_structures(false);
    $route_snapshots = is_array($state) && is_array($state['route_page_snapshots'] ?? null) ? $state['route_page_snapshots'] : [];

    $changed = iss_content_fallback_set_generated_statuses(false);
    foreach ((array) ($structures['pages'] ?? []) as $key => $page_id) {
        $key = sanitize_key((string) $key);
        if (isset($route_snapshots[$key]) && is_array($route_snapshots[$key])) {
            $snapshot = $route_snapshots[$key];
            $restore_id = (int) ($snapshot['ID'] ?? 0);
            if ($restore_id > 0) {
                wp_update_post(wp_slash([
                    'ID' => $restore_id,
                    'post_title' => (string) ($snapshot['post_title'] ?? ''),
                    'post_name' => (string) ($snapshot['post_name'] ?? ''),
                    'post_content' => (string) ($snapshot['post_content'] ?? ''),
                    'post_excerpt' => (string) ($snapshot['post_excerpt'] ?? ''),
                    'post_status' => (string) ($snapshot['post_status'] ?? 'publish'),
                ]));
                update_post_meta($restore_id, '_wp_page_template', (string) ($snapshot['page_template'] ?? ''));
            }
            continue;
        }

        if ((int) $page_id > 0) {
            wp_update_post([
                'ID' => (int) $page_id,
                'post_status' => 'draft',
            ]);
        }
    }
    if (!empty($structures['front_page_id'])) {
        wp_update_post([
            'ID' => (int) $structures['front_page_id'],
            'post_status' => 'draft',
        ]);
    }

    if (isset($previous['nav_menu_locations']) && is_array($previous['nav_menu_locations'])) {
        set_theme_mod('nav_menu_locations', $previous['nav_menu_locations']);
    }
    foreach (['show_on_front', 'page_on_front', 'page_for_posts'] as $option) {
        if (array_key_exists($option, $previous)) {
            update_option($option, $previous[$option], false);
        }
    }

    update_option(ISS_CONTENT_FALLBACK_MODE_OPTION, '0', false);
    flush_rewrite_rules(false);

    if (function_exists('iss_core_audit_log')) {
        iss_core_audit_log('fallback_mode_disabled', [
            'result' => 'completed',
            'object_ids' => array_values(array_filter(array_map('intval', (array) ($structures['pages'] ?? [])))),
        ]);
    }

    return ['enabled' => false, 'generated_changed' => $changed, 'structures' => $structures];
}
