<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_get_place_post_type(): string
{
    return (string) apply_filters('iss_relations_place_post_type', 'register_place');
}

function iss_relations_get_role_options(): array
{
    return [
        'primary' => __('Hauptort', 'iss-relations'),
        'venue' => __('Veranstaltungsort', 'iss-relations'),
        'stop' => __('Station', 'iss-relations'),
        'subject' => __('Thema', 'iss-relations'),
        'related' => __('Verwandt', 'iss-relations'),
    ];
}

function iss_relations_get_candidate_post_types(): array
{
    return (array) apply_filters('iss_relations_candidate_post_types', [
        iss_relations_get_place_post_type(),
        'archivbeitrag',
        'fuehrung',
        'publication',
        'veranstaltung',
        'ausstellung',
        'projekt',
        'post',
        'page',
    ]);
}

function iss_relations_get_supported_post_types(): array
{
    $post_types = array_filter(array_map('sanitize_key', iss_relations_get_candidate_post_types()));

    return array_values(array_unique(array_filter($post_types, 'post_type_exists')));
}

function iss_relations_is_supported_post_type(string $post_type): bool
{
    return in_array($post_type, iss_relations_get_supported_post_types(), true);
}

function iss_relations_supports_route_fields(string $post_type): bool
{
    return in_array($post_type, ['fuehrung'], true);
}

function iss_relations_register_taxonomy(): void
{
    $object_types = iss_relations_get_supported_post_types();
    if (!$object_types) {
        return;
    }

    register_taxonomy(ISS_RELATIONS_TAXONOMY, $object_types, [
        'labels' => [
            'name' => __('Place Relations', 'iss-relations'),
            'singular_name' => __('Place Relation', 'iss-relations'),
        ],
        'public' => false,
        'publicly_queryable' => true,
        'show_ui' => false,
        'show_in_rest' => true,
        'show_admin_column' => false,
        'hierarchical' => false,
        'query_var' => false,
        'rewrite' => false,
    ]);

    foreach ($object_types as $post_type) {
        register_taxonomy_for_object_type(ISS_RELATIONS_TAXONOMY, $post_type);
    }
}
add_action('init', 'iss_relations_register_taxonomy', 20);

function iss_relations_term_slug_for_place(int $place_id): string
{
    return 'place-' . $place_id;
}

function iss_relations_is_usable_place(int $place_id): bool
{
    if ($place_id <= 0) {
        return false;
    }

    $place = get_post($place_id);
    if (!$place instanceof WP_Post) {
        return false;
    }

    if ($place->post_type !== iss_relations_get_place_post_type()) {
        return false;
    }

    return !in_array($place->post_status, ['auto-draft', 'trash'], true);
}

function iss_relations_get_place_term_id(int $place_id): int
{
    if (!taxonomy_exists(ISS_RELATIONS_TAXONOMY) || !iss_relations_is_usable_place($place_id)) {
        return 0;
    }

    $slug = iss_relations_term_slug_for_place($place_id);
    $term = get_term_by('slug', $slug, ISS_RELATIONS_TAXONOMY);
    $title = trim((string) get_the_title($place_id));
    $name = $title !== '' ? $title : sprintf(__('Ort %d', 'iss-relations'), $place_id);

    if (!$term instanceof WP_Term) {
        $created = wp_insert_term($name, ISS_RELATIONS_TAXONOMY, [
            'slug' => $slug,
        ]);

        if (is_wp_error($created)) {
            $term = get_term_by('slug', $slug, ISS_RELATIONS_TAXONOMY);
            if (!$term instanceof WP_Term) {
                return 0;
            }
        } else {
            $term = get_term((int) $created['term_id'], ISS_RELATIONS_TAXONOMY);
            if (!$term instanceof WP_Term) {
                return 0;
            }
        }
    }

    if ($term->name !== $name) {
        wp_update_term($term->term_id, ISS_RELATIONS_TAXONOMY, ['name' => $name]);
    }

    update_term_meta($term->term_id, 'place_post_id', $place_id);

    return (int) $term->term_id;
}

function iss_relations_normalize_relation(array $relation, int $context_post_id = 0): ?array
{
    $place_id = absint($relation['place_id'] ?? 0);
    if ($place_id <= 0 || !iss_relations_is_usable_place($place_id)) {
        return null;
    }

    if (
        $context_post_id > 0
        && $place_id === $context_post_id
        && get_post_type($context_post_id) === iss_relations_get_place_post_type()
    ) {
        return null;
    }

    $role = sanitize_key((string) ($relation['role'] ?? 'related'));
    $roles = iss_relations_get_role_options();
    if (!isset($roles[$role])) {
        $role = 'related';
    }

    $route_title = sanitize_text_field((string) ($relation['route_title'] ?? ''));
    $route_teaser = sanitize_textarea_field((string) ($relation['route_teaser'] ?? ''));
    $station_object_id = absint($relation['station_object_id'] ?? 0);
    $station_story_id = absint($relation['station_story_id'] ?? 0);

    return [
        'place_id' => $place_id,
        'role' => $role,
        'weight' => (int) ($relation['weight'] ?? 0),
        'label' => sanitize_text_field((string) ($relation['label'] ?? '')),
        'route_title' => $route_title,
        'route_teaser' => $route_teaser,
        'station_object_id' => $station_object_id,
        'station_story_id' => $station_story_id,
    ];
}

function iss_relations_normalize_relations($relations, int $context_post_id = 0): array
{
    if (!is_array($relations)) {
        return [];
    }

    $normalized = [];
    $seen_place_ids = [];

    foreach ($relations as $relation) {
        if (!is_array($relation)) {
            continue;
        }

        $clean = iss_relations_normalize_relation($relation, $context_post_id);
        if (!$clean) {
            continue;
        }

        $place_id = (int) $clean['place_id'];
        if (isset($seen_place_ids[$place_id])) {
            continue;
        }

        $seen_place_ids[$place_id] = true;
        $normalized[] = $clean;
    }

    return $normalized;
}

function iss_relations_get_post_relations(int $post_id): array
{
    $stored = get_post_meta($post_id, ISS_RELATIONS_META_KEY, true);
    return iss_relations_normalize_relations(is_array($stored) ? $stored : [], $post_id);
}

function iss_relations_get_ordered_route_items(int $post_id): array
{
    $items = array_values(array_filter(
        iss_relations_get_related_place_items($post_id),
        static function (array $item): bool {
            return (($item['role'] ?? '') === 'stop');
        }
    ));

    usort($items, static function (array $left, array $right): int {
        $left_weight = (int) ($left['weight'] ?? 0);
        $right_weight = (int) ($right['weight'] ?? 0);

        if ($left_weight === $right_weight) {
            return ((int) ($left['place_id'] ?? 0)) <=> ((int) ($right['place_id'] ?? 0));
        }

        return $left_weight <=> $right_weight;
    });

    return $items;
}

function iss_relations_get_related_place_items(int $post_id): array
{
    $items = [];

    foreach (iss_relations_get_post_relations($post_id) as $relation) {
        $place_id = (int) ($relation['place_id'] ?? 0);
        if (!iss_relations_is_usable_place($place_id)) {
            continue;
        }

        $place = get_post($place_id);
        if (!$place instanceof WP_Post) {
            continue;
        }

        $items[] = array_merge($relation, [
            'post' => $place,
            'title' => get_the_title($place),
            'permalink' => (string) get_permalink($place),
        ]);
    }

    return $items;
}

function iss_relations_get_related_place_ids(int $post_id): array
{
    return array_values(array_filter(array_map(static function (array $item): int {
        return (int) ($item['place_id'] ?? 0);
    }, iss_relations_get_related_place_items($post_id))));
}

function iss_relations_get_context_place_ids(int $post_id): array
{
    if ($post_id <= 0) {
        return [];
    }

    if (get_post_type($post_id) === iss_relations_get_place_post_type() && iss_relations_is_usable_place($post_id)) {
        return [$post_id];
    }

    return iss_relations_get_related_place_ids($post_id);
}

function iss_relations_parse_place_ids_string(string $value): array
{
    return iss_relations_parse_place_ids($value);
}

function iss_relations_parse_place_ids($value): array
{
    $raw_ids = [];

    if (is_string($value)) {
        $parts = preg_split('/[\s,]+/', $value);
        if (is_array($parts)) {
            $raw_ids = $parts;
        }
    } elseif (is_array($value)) {
        $raw_ids = $value;
    }

    $ids = array_values(array_unique(array_filter(array_map('absint', $raw_ids))));

    return array_values(array_filter($ids, 'iss_relations_is_usable_place'));
}

function iss_relations_get_place_term_ids(array $place_ids): array
{
    $term_ids = [];

    foreach ($place_ids as $place_id) {
        $term_id = iss_relations_get_place_term_id((int) $place_id);
        if ($term_id > 0) {
            $term_ids[] = $term_id;
        }
    }

    return array_values(array_unique(array_filter(array_map('absint', $term_ids))));
}

function iss_relations_render_related_place_links_html(int $post_id): string
{
    $items = iss_relations_get_related_place_items($post_id);
    if (!$items) {
        return '';
    }

    $links = [];
    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));
        $permalink = trim((string) ($item['permalink'] ?? ''));

        if ($permalink === '' || $title === '') {
            continue;
        }

        $links[] = '<a href="' . esc_url($permalink) . '">' . esc_html($label !== '' ? $label : $title) . '</a>';
    }

    if (!$links) {
        return '';
    }

    return '<span class="iss-related-links">' . implode(', ', $links) . '</span>';
}

function iss_relations_update_post_relations(int $post_id, array $relations): void
{
    $clean = iss_relations_normalize_relations($relations, $post_id);

    if (!$clean) {
        delete_post_meta($post_id, ISS_RELATIONS_META_KEY);
        return;
    }

    update_post_meta($post_id, ISS_RELATIONS_META_KEY, $clean);
}

function iss_relations_ensure_post_has_place_relation(int $post_id, int $place_id, array $overrides = []): bool
{
    if (
        $post_id <= 0
        || $place_id <= 0
        || !iss_relations_is_usable_place($place_id)
    ) {
        return false;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_relations_is_supported_post_type($post->post_type)) {
        return false;
    }

    $relations = iss_relations_get_post_relations($post_id);

    foreach ($relations as $relation) {
        if ((int) ($relation['place_id'] ?? 0) === $place_id) {
            return false;
        }
    }

    $defaults = [
        'place_id' => $place_id,
        'role' => 'related',
        'weight' => 0,
        'label' => '',
        'route_title' => '',
        'route_teaser' => '',
        'station_object_id' => 0,
        'station_story_id' => 0,
    ];

    $relations[] = array_merge($defaults, $overrides);
    iss_relations_update_post_relations($post_id, $relations);

    return true;
}

function iss_relations_collect_taxonomy_place_ids_for_post(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_relations_is_supported_post_type($post->post_type)) {
        return [];
    }

    if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
        return [];
    }

    $place_ids = [];
    if ($post->post_type === iss_relations_get_place_post_type()) {
        $place_ids[] = $post_id;
    }

    foreach (iss_relations_get_post_relations($post_id) as $relation) {
        $place_ids[] = (int) $relation['place_id'];
    }

    $place_ids = array_values(array_unique(array_filter(array_map('absint', $place_ids))));

    return array_values(array_filter($place_ids, 'iss_relations_is_usable_place'));
}

function iss_relations_sync_post_terms(int $post_id): void
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_relations_is_supported_post_type($post->post_type)) {
        return;
    }

    if (!taxonomy_exists(ISS_RELATIONS_TAXONOMY)) {
        return;
    }

    $place_ids = iss_relations_collect_taxonomy_place_ids_for_post($post_id);
    $term_ids = [];

    foreach ($place_ids as $place_id) {
        $term_id = iss_relations_get_place_term_id($place_id);
        if ($term_id > 0) {
            $term_ids[] = $term_id;
        }
    }

    wp_set_object_terms($post_id, array_values(array_unique($term_ids)), ISS_RELATIONS_TAXONOMY, false);
}

function iss_relations_backfill_index(array $post_types = []): array
{
    $types = $post_types ? array_values(array_intersect(iss_relations_get_supported_post_types(), $post_types)) : iss_relations_get_supported_post_types();
    if (!$types) {
        return [
            'count' => 0,
            'post_ids' => [],
            'post_types' => [],
        ];
    }

    $post_ids = [];
    $place_type = iss_relations_get_place_post_type();

    if (in_array($place_type, $types, true)) {
        $post_ids = array_merge($post_ids, get_posts([
            'post_type' => $place_type,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ]));
    }

    $relation_types = array_values(array_diff($types, [$place_type]));
    if ($relation_types) {
        $post_ids = array_merge($post_ids, get_posts([
            'post_type' => $relation_types,
            'post_status' => 'any',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
            'meta_query' => [
                [
                    'key' => ISS_RELATIONS_META_KEY,
                    'compare' => 'EXISTS',
                ],
            ],
        ]));
    }

    $post_ids = array_values(array_unique(array_filter(array_map('absint', $post_ids))));

    foreach ($post_ids as $post_id) {
        iss_relations_sync_post_terms($post_id);
    }

    return [
        'count' => count($post_ids),
        'post_ids' => $post_ids,
        'post_types' => $types,
    ];
}

function iss_relations_sync_supported_post_on_save(int $post_id, WP_Post $post): void
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!iss_relations_is_supported_post_type($post->post_type)) {
        return;
    }

    iss_relations_sync_post_terms($post_id);
}
add_action('save_post', 'iss_relations_sync_supported_post_on_save', 30, 2);

function iss_relations_sync_post_terms_on_meta_change($meta_id, int $post_id, string $meta_key): void
{
    if ($meta_key !== ISS_RELATIONS_META_KEY) {
        return;
    }

    iss_relations_sync_post_terms($post_id);
}
add_action('added_post_meta', 'iss_relations_sync_post_terms_on_meta_change', 10, 3);
add_action('updated_post_meta', 'iss_relations_sync_post_terms_on_meta_change', 10, 3);
add_action('deleted_post_meta', 'iss_relations_sync_post_terms_on_meta_change', 10, 3);

function iss_relations_maybe_run_backfill(): void
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (get_option('iss_relations_needs_backfill') !== '1') {
        return;
    }

    delete_option('iss_relations_needs_backfill');
    iss_relations_backfill_index();
}
add_action('admin_init', 'iss_relations_maybe_run_backfill');
