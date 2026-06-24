<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_veranstaltung_entity_keys_for_shapes(array $shapes): array
{
    $shapes = array_values(array_unique(array_filter(array_map('sanitize_key', $shapes))));
    if (!$shapes) {
        return [];
    }

    $entity_keys = [];
    foreach (iss_content_model_veranstaltung_entities() as $entity_key => $entity) {
        $shape = sanitize_key((string) ($entity['shape'] ?? ''));
        if ($shape !== '' && in_array($shape, $shapes, true)) {
            $entity_keys[] = (string) $entity_key;
        }
    }

    return $entity_keys;
}

function iss_content_model_veranstaltung_entity_keys_for_primary_surface(string $surface): array
{
    $surface = sanitize_key($surface);
    if ($surface === '') {
        return [];
    }

    $entity_keys = [];
    foreach (array_keys(iss_content_model_veranstaltung_entities()) as $entity_key) {
        if (iss_content_model_veranstaltung_entity_primary_surface((string) $entity_key) === $surface) {
            $entity_keys[] = (string) $entity_key;
        }
    }

    return $entity_keys;
}

function iss_content_model_veranstaltung_entity_meta_query(array $entity_keys): array
{
    $entity_keys = array_values(array_filter(array_map('iss_content_model_sanitize_veranstaltung_entity_key', $entity_keys)));
    if (!$entity_keys) {
        return [
            [
                'key' => '_iss_entity_key',
                'value' => '__none__',
            ],
        ];
    }

    return [
        [
            'key' => '_iss_entity_key',
            'value' => $entity_keys,
            'compare' => 'IN',
        ],
    ];
}

function iss_content_model_veranstaltungen_query_posts(array $args = []): array
{
    $entity_keys = array_values(array_filter(array_map('iss_content_model_sanitize_veranstaltung_entity_key', (array) ($args['entity_keys'] ?? []))));
    $meta_query = iss_content_model_veranstaltung_entity_meta_query($entity_keys);
    $date_fact = sanitize_key((string) ($args['date_fact'] ?? 'datetime_start'));
    $meta_keys = iss_content_model_veranstaltung_fact_meta_keys();
    $date_meta_key = (string) ($meta_keys[$date_fact] ?? '_iss_datetime_start');
    $compare = (string) ($args['date_compare'] ?? '');
    $date_value = trim((string) ($args['date_value'] ?? ''));

    if ($compare !== '' && $date_value !== '') {
        $meta_query[] = [
            'key' => $date_meta_key,
            'value' => $date_value,
            'compare' => $compare,
            'type' => 'DATETIME',
        ];
    }

    $query_args = [
        'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'post_status' => (array) ($args['post_status'] ?? ['publish']),
        'posts_per_page' => max(1, (int) ($args['limit'] ?? 12)),
        'paged' => max(1, (int) ($args['page'] ?? 1)),
        'meta_key' => $date_meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Repository owns the normalized fact lookup during Phase 1.
        'orderby' => 'meta_value',
        'meta_type' => 'DATETIME',
        'order' => strtoupper((string) ($args['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC',
        'no_found_rows' => empty($args['with_found_rows']),
        'ignore_sticky_posts' => true,
        'suppress_filters' => false,
        'meta_query' => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Repository gates converted Veranstaltungen by registry-derived entity keys and facts.
    ];

    return get_posts($query_args);
}

function iss_content_model_veranstaltungen_upcoming(int $limit = 12): array
{
    return iss_content_model_veranstaltungen_query_posts([
        'entity_keys' => iss_content_model_veranstaltung_entity_keys_for_primary_surface('timeline'),
        'limit' => $limit,
        'date_fact' => 'datetime_start',
        'date_compare' => '>=',
        'date_value' => current_time('mysql'),
        'order' => 'ASC',
    ]);
}

function iss_content_model_veranstaltungen_homepage_teasers(int $limit = 3): array
{
    return iss_content_model_veranstaltungen_upcoming($limit);
}

function iss_content_model_veranstaltungen_next_menu_event(): ?WP_Post
{
    $upcoming = iss_content_model_veranstaltungen_upcoming(1);
    if (isset($upcoming[0]) && $upcoming[0] instanceof WP_Post) {
        return $upcoming[0];
    }

    $events = get_posts([
        'post_type' => ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'meta_key' => 'iss_start_datetime', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Legacy fallback preserves existing menu behavior until posts are curated.
        'orderby' => 'meta_value',
        'order' => 'ASC',
        'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Legacy fallback preserves existing menu behavior until posts are curated.
            [
                'key' => 'iss_start_datetime',
                'value' => current_time('mysql'),
                'compare' => '>=',
                'type' => 'DATETIME',
            ],
        ],
        'no_found_rows' => true,
        'ignore_sticky_posts' => true,
    ]);

    return isset($events[0]) && $events[0] instanceof WP_Post ? $events[0] : null;
}

function iss_content_model_veranstaltungen_archive(int $page = 1, int $per_page = 12): array
{
    return iss_content_model_veranstaltungen_query_posts([
        'entity_keys' => iss_content_model_veranstaltung_entity_keys_for_primary_surface('timeline'),
        'limit' => $per_page,
        'page' => $page,
        'date_fact' => 'datetime_start',
        'date_compare' => '<',
        'date_value' => current_time('mysql'),
        'order' => 'DESC',
        'with_found_rows' => true,
    ]);
}

function iss_content_model_veranstaltungen_reports(int $page = 1, int $per_page = 12): array
{
    return iss_content_model_veranstaltungen_query_posts([
        'entity_keys' => iss_content_model_veranstaltung_entity_keys_for_primary_surface('archive'),
        'limit' => $per_page,
        'page' => $page,
        'date_fact' => 'published_at',
        'order' => 'DESC',
        'with_found_rows' => true,
    ]);
}

function iss_content_model_filter_veranstaltung_repository_posts(array $posts, int $limit = 0): array
{
    $valid_entity_keys = array_keys(iss_content_model_veranstaltung_entities());
    $valid_entity_keys = array_fill_keys(array_filter(array_map('iss_content_model_sanitize_veranstaltung_entity_key', $valid_entity_keys)), true);
    $filtered = [];
    $seen = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post || $post->post_type !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE || isset($seen[$post->ID])) {
            continue;
        }

        $entity_key = iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta((int) $post->ID, '_iss_entity_key', true));
        if ($entity_key === '' || !isset($valid_entity_keys[$entity_key])) {
            continue;
        }

        $seen[$post->ID] = true;
        $filtered[] = $post;

        if ($limit > 0 && count($filtered) >= $limit) {
            break;
        }
    }

    return $filtered;
}

function iss_content_model_veranstaltungen_related(int $post_id, int $limit = 4): array
{
    $post_id = max(0, $post_id);
    $limit = max(1, $limit);
    if ($post_id <= 0) {
        return [];
    }

    if (function_exists('iss_relations_query_entity_related_posts')) {
        return iss_content_model_filter_veranstaltung_repository_posts(iss_relations_query_entity_related_posts(
            $post_id,
            [ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE],
            $limit,
            ['source' => 'entity']
        ), $limit);
    }

    return iss_content_model_filter_veranstaltung_repository_posts((array) apply_filters('iss_content_model_veranstaltungen_related_posts', [], $post_id, $limit), $limit);
}
