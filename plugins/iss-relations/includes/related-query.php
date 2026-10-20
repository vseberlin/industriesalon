<?php

if (!defined('ABSPATH')) { exit; }

function iss_relations_get_related_content_defaults(string $post_type): array
{
    if ($post_type === iss_relations_get_place_post_type()) {
        return [
            'kicker' => __('Ort', 'iss-relations'),
            'title' => __('Verknüpfte Orte', 'iss-relations'),
            'link_text' => __('Weiter', 'iss-relations'),
        ];
    }

    switch ($post_type) {
        case 'fuehrung':
            return [
                'kicker' => __('Vor Ort', 'iss-relations'),
                'title' => __('Führungen zum Ort', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        case 'veranstaltung':
            return [
                'kicker' => __('Programm', 'iss-relations'),
                'title' => __('Veranstaltungen mit Ortsbezug', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        case 'publication':
            return [
                'kicker' => __('Publikation', 'iss-relations'),
                'title' => __('Publikationen mit Ortsbezug', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        case 'video':
            return [
                'kicker' => __('Video', 'iss-relations'),
                'title' => __('Videos mit Ortsbezug', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        case 'archivobjekt':
            return [
                'kicker' => __('Archivobjekt', 'iss-relations'),
                'title' => __('Material aus dem Archiv', 'iss-relations'),
                'link_text' => __('Ansehen', 'iss-relations'),
            ];
        case 'entity_profile':
            return [
                'kicker' => __('Profil', 'iss-relations'),
                'title' => __('Personen und Organisationen', 'iss-relations'),
                'link_text' => __('Zum Profil', 'iss-relations'),
            ];
        case 'post':
            return [
                'kicker' => __('Archiv', 'iss-relations'),
                'title' => __('Beiträge und Hintergründe', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        default:
            return [
                'kicker' => __('Weiter entdecken', 'iss-relations'),
                'title' => __('Verwandte Inhalte', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
    }
}

function iss_relations_get_related_content_defaults_for_post_types(array $post_types): array
{
    $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));

    if (count($post_types) === 1) {
        return iss_relations_get_related_content_defaults($post_types[0]);
    }

    return [
        'kicker' => __('Weiter entdecken', 'iss-relations'),
        'title' => __('Verwandte Inhalte', 'iss-relations'),
        'link_text' => __('Weiter', 'iss-relations'),
    ];
}

function iss_relations_get_related_query_post_types(): array
{
    $post_types = iss_relations_get_supported_post_types();
    $archive_object_post_type = function_exists('iss_graph_get_archive_object_post_type')
        ? iss_graph_get_archive_object_post_type()
        : (defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? (string) ISS_WF_IMPORT_OBJECT_POST_TYPE : 'archivobjekt');

    if ($archive_object_post_type !== '' && post_type_exists($archive_object_post_type)) {
        $post_types[] = $archive_object_post_type;
    }

    $profile_post_type = function_exists('iss_graph_get_entity_profile_post_type')
        ? iss_graph_get_entity_profile_post_type()
        : 'entity_profile';

    if ($profile_post_type !== '' && post_type_exists($profile_post_type)) {
        $post_types[] = $profile_post_type;
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));

    return (array) apply_filters('iss_relations_related_query_post_types', $post_types);
}

function iss_relations_is_related_query_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_relations_get_related_query_post_types(), true);
}

function iss_relations_get_related_cards_post_types(array $attributes = [], string $fallback = 'post'): array
{
    $requested = [];

    if (isset($attributes['postTypes']) && is_array($attributes['postTypes'])) {
        $requested = $attributes['postTypes'];
    } elseif (!empty($attributes['postType'])) {
        $requested = [$attributes['postType']];
    } elseif ($fallback !== '') {
        $requested = [$fallback];
    }

    $post_types = [];

    foreach ($requested as $post_type) {
        $post_type = sanitize_key((string) $post_type);

        if ($post_type === '' || !iss_relations_is_related_query_post_type($post_type)) {
            continue;
        }

        $post_types[] = $post_type;
    }

    if (!$post_types) {
        $fallback = sanitize_key($fallback);

        if ($fallback !== '' && iss_relations_is_related_query_post_type($fallback)) {
            $post_types[] = $fallback;
        }
    }

    return array_values(array_unique($post_types));
}

function iss_relations_get_related_cards_scope_key(array $post_types): string
{
    $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));

    if (count($post_types) === 1) {
        return $post_types[0];
    }

    return 'mixed';
}

function iss_relations_normalize_related_cards_layout_variant(array $attributes = []): string
{
    $variant = sanitize_key((string) ($attributes['layoutVariant'] ?? 'grid'));
    $allowed = ['grid', 'stack', 'compact', 'strip', 'rail', 'register'];

    return in_array($variant, $allowed, true) ? $variant : 'grid';
}

function iss_relations_normalize_related_cards_skin(array $attributes = []): string
{
    $value = sanitize_key((string) ($attributes['skin'] ?? 'default'));
    $allowed = ['default', 'exhibition'];

    return in_array($value, $allowed, true) ? $value : 'default';
}

function iss_relations_normalize_related_cards_sort_mode(array $attributes = [], $post_types = []): string
{
    $value = sanitize_key((string) ($attributes['sortMode'] ?? 'auto'));
    $allowed = ['auto', 'relevance', 'relevance_title', 'relevance_date'];

    if (!in_array($value, $allowed, true)) {
        $value = 'auto';
    }

    if ($value !== 'auto') {
        return $value;
    }

    if (!is_array($post_types)) {
        $post_types = [$post_types];
    }

    $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));

    if (count($post_types) > 1) {
        return array_intersect($post_types, ['veranstaltung', 'fuehrung']) ? 'relevance_date' : 'relevance_title';
    }

    $post_type = $post_types ? $post_types[0] : '';

    if (in_array($post_type, ['ausstellung', 'publication', 'video', 'projekt', 'page', 'post'], true)) {
        return 'relevance_title';
    }

    if (in_array($post_type, ['veranstaltung', 'fuehrung'], true)) {
        return 'relevance_date';
    }

    return 'relevance';
}

function iss_relations_normalize_related_cards_columns(array $attributes = []): int
{
    $columns = isset($attributes['columns']) ? absint($attributes['columns']) : 0;

    if ($columns >= 1) {
        return max(1, min(6, $columns));
    }

    return 3;
}

function iss_relations_is_entity_related_source(string $source): bool
{
    return in_array(sanitize_key($source), ['entity', 'entity_place', 'entity_person', 'entity_organization'], true);
}

function iss_relations_get_entity_related_source_families(string $source): array
{
    switch (sanitize_key($source)) {
        case 'entity_place':
            return ['place'];
        case 'entity_person':
            return ['person'];
        case 'entity_organization':
            return ['organization'];
        case 'entity':
        default:
            return ['place', 'person', 'organization'];
    }
}

function iss_relations_get_graph_entity_for_related_post(int $post_id): ?array
{
    if ($post_id <= 0 || !iss_relations_graph_is_available()) {
        return null;
    }

    if (function_exists('iss_graph_get_entity_for_post')) {
        $entity = iss_graph_get_entity_for_post($post_id);
        if (is_array($entity) && !empty($entity['id'])) {
            return $entity;
        }
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return null;
    }

    if ($post->post_type === (function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt') && function_exists('iss_graph_sync_archive_object_entity')) {
        $entity = iss_graph_sync_archive_object_entity($post_id);
        return is_array($entity) && !empty($entity['id']) ? $entity : null;
    }

    if (iss_relations_is_supported_post_type((string) $post->post_type)) {
        $entity = iss_relations_sync_post_graph($post_id);
        return is_array($entity) && !empty($entity['id']) ? $entity : null;
    }

    return null;
}

function iss_relations_collect_entity_related_seed_ids(array $entity, array $relation_families): array
{
    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0 || !$relation_families) {
        return [];
    }

    $relation_families = array_values(array_unique(array_filter(array_map('sanitize_key', $relation_families))));
    if (!$relation_families) {
        return [];
    }

    $seed_ids = [];
    $entity_kind = sanitize_key((string) ($entity['entity_kind'] ?? ''));
    if (in_array($entity_kind, $relation_families, true)) {
        $seed_ids[] = $entity_id;
    }

    $service = iss_graph_get_service();
    foreach ($relation_families as $relation_family) {
        foreach ($service->get_relations_for_entity($entity_id, $relation_family, [
            'public_only' => true,
            'limit' => 500,
        ]) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $related_entity_id = (int) ($row['entity_id'] ?? 0);
            if ($related_entity_id > 0) {
                $seed_ids[] = $related_entity_id;
            }
        }
    }

    return array_values(array_unique(array_filter(array_map('intval', $seed_ids))));
}

function iss_relations_query_direct_seed_posts(array $seed_entity_ids, array $post_types, int $per_page, int $exclude_post_id = 0): array
{
    if (!$seed_entity_ids || !$post_types || !iss_relations_graph_is_available()) {
        return [];
    }

    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $entity_placeholders = implode(', ', array_fill(0, count($seed_entity_ids), '%d'));
    $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
    $params = array_merge($seed_entity_ids, $post_types);
    $post_id_sql = 'COALESCE(e.post_id, e.profile_post_id)';

    $sql = "SELECT DISTINCT {$post_id_sql} AS post_id
        FROM {$entity_table} e
        INNER JOIN {$wpdb->posts} p
            ON p.ID = {$post_id_sql}
        WHERE e.id IN ({$entity_placeholders})
          AND {$post_id_sql} IS NOT NULL
          AND p.post_type IN ({$type_placeholders})
          AND p.post_status = 'publish'";

    if ($exclude_post_id > 0) {
        $sql .= " AND {$post_id_sql} <> %d";
        $params[] = $exclude_post_id;
    }

    $sql .= ' ORDER BY p.menu_order ASC, p.post_date_gmt DESC, p.ID DESC LIMIT %d';
    $params[] = max(1, min(24, $per_page));

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Related blocks query plugin-owned graph tables through a prepared dynamic table query and cache the final post list per request.
    $post_ids = $wpdb->get_col($wpdb->prepare($sql, $params));

    return array_values(array_unique(array_filter(array_map('intval', is_array($post_ids) ? $post_ids : []))));
}

function iss_relations_query_entity_related_post_ids(array $seed_entity_ids, array $relation_families, array $post_types, int $per_page, int $exclude_post_id = 0, array $attributes = []): array
{
    if (!$seed_entity_ids || !$relation_families || !$post_types || !iss_relations_graph_is_available()) {
        return [];
    }

    global $wpdb;

    $sort_mode = iss_relations_normalize_related_cards_sort_mode($attributes, $post_types);
    $service = iss_graph_get_service();
    $relation_table = $service->get_relation_table_name();
    $entity_table = $service->get_entity_table_name();
    $family_placeholders = implode(', ', array_fill(0, count($relation_families), '%s'));
    $seed_placeholders = implode(', ', array_fill(0, count($seed_entity_ids), '%d'));
    $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
    $post_id_sql = 'COALESCE(e.post_id, e.profile_post_id)';
    $rank_sql = iss_relations_graph_relation_rank_sql('r');
    $params = array_merge($relation_families, $seed_entity_ids, $post_types);

    $sql = "SELECT
            {$post_id_sql} AS post_id,
            MAX({$rank_sql}) AS relation_rank,
            MAX(p.post_date_gmt) AS latest_post_date,
            MAX(p.post_title) AS post_title
        FROM {$relation_table} r
        INNER JOIN {$entity_table} e
            ON e.id = r.from_entity_id
        INNER JOIN {$wpdb->posts} p
            ON p.ID = {$post_id_sql}
        WHERE r.relation_family IN ({$family_placeholders})
          AND r.to_entity_id IN ({$seed_placeholders})
          AND r.is_public = 1
          AND {$post_id_sql} IS NOT NULL
          AND p.post_type IN ({$type_placeholders})
          AND p.post_status = 'publish'";

    if ($exclude_post_id > 0) {
        $sql .= " AND {$post_id_sql} <> %d";
        $params[] = $exclude_post_id;
    }

    $sql .= ' GROUP BY post_id ORDER BY relation_rank DESC';

    if ($sort_mode === 'relevance_title') {
        $sql .= ' , post_title ASC, post_id DESC';
    } elseif ($sort_mode === 'relevance_date' || $sort_mode === 'auto') {
        $sql .= ' , latest_post_date DESC, post_id DESC';
    } else {
        $sql .= ' , post_id DESC';
    }

    $sql .= ' LIMIT %d';
    $params[] = max(1, min(24, $per_page));

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Related blocks query plugin-owned graph tables through a prepared dynamic table query and cache the final post list per request.
    $post_ids = $wpdb->get_col($wpdb->prepare($sql, $params));

    return array_values(array_unique(array_filter(array_map('intval', is_array($post_ids) ? $post_ids : []))));
}

function iss_relations_query_entity_related_posts(int $current_post_id, array $post_types, int $per_page, array $attributes = []): array
{
    static $cache = [];

    $source = sanitize_key((string) ($attributes['source'] ?? 'entity'));
    if (!iss_relations_is_entity_related_source($source) || $current_post_id <= 0 || !$post_types) {
        return [];
    }

    $relation_families = iss_relations_get_entity_related_source_families($source);
    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'iss_relations_is_related_query_post_type')));
    if (!$relation_families || !$post_types) {
        return [];
    }

    $cache_key = md5(wp_json_encode([
        'current_post_id' => $current_post_id,
        'post_types' => $post_types,
        'per_page' => $per_page,
        'source' => $source,
        'sort_mode' => $attributes['sortMode'] ?? 'auto',
    ]));

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $entity = iss_relations_get_graph_entity_for_related_post($current_post_id);
    if (!$entity || empty($entity['id'])) {
        $cache[$cache_key] = [];
        return [];
    }

    $seed_entity_ids = iss_relations_collect_entity_related_seed_ids($entity, $relation_families);
    if (!$seed_entity_ids) {
        $cache[$cache_key] = [];
        return [];
    }

    $limit = max(1, min(24, $per_page));
    $direct_post_ids = iss_relations_query_direct_seed_posts($seed_entity_ids, $post_types, $limit, $current_post_id);
    $related_post_ids = iss_relations_query_entity_related_post_ids($seed_entity_ids, $relation_families, $post_types, $limit, $current_post_id, $attributes);
    $post_ids = array_values(array_unique(array_merge($direct_post_ids, $related_post_ids)));
    $post_ids = array_slice($post_ids, 0, $limit);

    if (!$post_ids) {
        $cache[$cache_key] = [];
        return [];
    }

    $cache[$cache_key] = get_posts([
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'post__in',
        'post__in' => $post_ids,
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
    ]);

    return $cache[$cache_key];
}

function iss_relations_build_place_item(int $place_id, array $overrides = []): ?array
{
    if (!iss_relations_is_usable_place($place_id)) {
        return null;
    }

    $place = get_post($place_id);
    if (!$place instanceof WP_Post) {
        return null;
    }

    return array_merge([
        'place_id' => $place_id,
        'role' => 'related',
        'weight' => 0,
        'label' => '',
        'post' => $place,
        'title' => get_the_title($place),
        'permalink' => (string) get_permalink($place),
        'excerpt' => iss_relations_get_card_excerpt($place, 18),
        'address' => trim((string) get_post_meta($place_id, 'address', true)),
        'area' => trim((string) get_post_meta($place_id, 'area', true)),
        'lat' => is_numeric(get_post_meta($place_id, 'lat', true)) ? (float) get_post_meta($place_id, 'lat', true) : null,
        'lng' => is_numeric(get_post_meta($place_id, 'lng', true)) ? (float) get_post_meta($place_id, 'lng', true) : null,
    ], $overrides);
}

function iss_relations_build_static_map_place_dto(array $item, string $source = ''): ?array
{
    $place_id = absint($item['place_id'] ?? 0);
    if (!iss_relations_is_usable_place($place_id)) {
        return null;
    }

    $place = get_post($place_id);
    if (!$place instanceof WP_Post) {
        return null;
    }

    $role = sanitize_key((string) ($item['role'] ?? 'related'));
    if (!isset(iss_relations_get_role_options()[$role])) {
        $role = 'related';
    }

    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        $title = get_the_title($place);
    }

    $label = trim((string) ($item['label'] ?? ''));
    $address = trim((string) ($item['address'] ?? get_post_meta($place_id, 'address', true)));
    $area = trim((string) ($item['area'] ?? get_post_meta($place_id, 'area', true)));
    $lat_value = $item['lat'] ?? get_post_meta($place_id, 'lat', true);
    $lng_value = $item['lng'] ?? get_post_meta($place_id, 'lng', true);
    $lat = is_numeric($lat_value) ? (float) $lat_value : null;
    $lng = is_numeric($lng_value) ? (float) $lng_value : null;
    $thumbnail_id = get_post_thumbnail_id($place);
    $thumbnail_url = $thumbnail_id ? (string) wp_get_attachment_image_url($thumbnail_id, 'medium') : '';
    $type = trim((string) get_post_meta($place_id, 'place_type', true));
    $state = trim((string) get_post_meta($place_id, 'state', true));
    $excerpt = trim((string) ($item['excerpt'] ?? ''));

    if ($excerpt === '') {
        $excerpt = iss_relations_get_card_excerpt($place, 18);
    }

    return [
        'canonical_id' => 'register_place:' . $place_id,
        'post_id' => $place_id,
        'place_id' => $place_id,
        'slug' => (string) $place->post_name,
        'title' => $title,
        'short_label' => $label !== '' ? $label : $title,
        'label' => $label,
        'permalink' => (string) get_permalink($place),
        'excerpt' => $excerpt,
        'address' => $address,
        'area' => $area,
        'location_label' => implode(' · ', array_values(array_filter([$address, $area]))),
        'type' => $type,
        'state' => $state,
        'coordinates' => ($lat !== null && $lng !== null)
            ? [
                'lat' => $lat,
                'lng' => $lng,
            ]
            : null,
        'lat' => $lat,
        'lng' => $lng,
        'map_marker' => null,
        'thumbnail_id' => $thumbnail_id ? (int) $thumbnail_id : 0,
        'thumbnail_url' => $thumbnail_url,
        'source' => sanitize_key($source) ?: 'related',
        'relation' => [
            'source' => sanitize_key($source) ?: 'related',
            'role' => $role,
            'label' => $label,
            'weight' => (int) ($item['weight'] ?? 0),
            'route_title' => trim((string) ($item['route_title'] ?? '')),
            'route_teaser' => trim((string) ($item['route_teaser'] ?? '')),
            'station_object_id' => absint($item['station_object_id'] ?? 0),
            'station_story_id' => absint($item['station_story_id'] ?? 0),
        ],
        'role' => $role,
        'weight' => (int) ($item['weight'] ?? 0),
        'route_title' => trim((string) ($item['route_title'] ?? '')),
        'route_teaser' => trim((string) ($item['route_teaser'] ?? '')),
        'station_object_id' => absint($item['station_object_id'] ?? 0),
        'station_story_id' => absint($item['station_story_id'] ?? 0),
    ];
}

function iss_relations_build_static_map_place_dtos(array $items, string $source = ''): array
{
    $dtos = [];
    $seen = [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $dto = iss_relations_build_static_map_place_dto($item, $source);
        if (!$dto) {
            continue;
        }

        $place_id = (int) $dto['place_id'];
        if (isset($seen[$place_id])) {
            continue;
        }

        $seen[$place_id] = true;
        $dtos[] = $dto;
    }

    return $dtos;
}

function iss_relations_resolve_static_map_relation_result(array $attributes, int $current_post_id, string $block_name = ''): array
{
    $source = iss_relations_resolve_map_block_source($attributes, $block_name);
    $items = iss_relations_resolve_block_place_items($attributes, $current_post_id, $block_name);
    $places = iss_relations_build_static_map_place_dtos($items, $source);

    return [
        'source' => $source,
        'block_name' => $block_name,
        'context_post_id' => $current_post_id,
        'selected_place_ids' => array_values(array_map(static function (array $place): int {
            return (int) ($place['place_id'] ?? 0);
        }, $places)),
        'places' => $places,
        'count' => count($places),
    ];
}

function iss_relations_build_place_items_from_ids(array $place_ids): array
{
    $items = [];

    foreach ($place_ids as $index => $place_id) {
        $item = iss_relations_build_place_item((int) $place_id, [
            'weight' => (int) $index,
        ]);

        if ($item) {
            $items[] = $item;
        }
    }

    return $items;
}

function iss_relations_resolve_block_place_items(array $attributes, int $current_post_id, string $block_name = ''): array
{
    $source = iss_relations_resolve_map_block_source($attributes, $block_name);

    if ($source === 'manual') {
        return iss_relations_build_place_items_from_ids(
            iss_relations_parse_place_ids($attributes['placeIds'] ?? '')
        );
    }

    if ($source === 'route') {
        if ($current_post_id > 0 && function_exists('iss_relations_get_ordered_route_items')) {
            return iss_relations_get_ordered_route_items($current_post_id);
        }

        return [];
    }

    if ($source === 'related') {
        return $current_post_id > 0 ? iss_relations_get_related_place_items($current_post_id) : [];
    }

    if (
        $current_post_id > 0
        && get_post_type($current_post_id) === iss_relations_get_place_post_type()
        && iss_relations_is_usable_place($current_post_id)
    ) {
        return iss_relations_build_place_items_from_ids([$current_post_id]);
    }

    return $current_post_id > 0 ? iss_relations_get_related_place_items($current_post_id) : [];
}

function iss_relations_query_related_posts(array $place_ids, $post_types, int $per_page, int $exclude_post_id = 0, array $attributes = []): array
{
    static $cache = [];
    $post_types = is_array($post_types) ? $post_types : [$post_types];
    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'iss_relations_is_supported_post_type')));
    $sort_mode = iss_relations_normalize_related_cards_sort_mode($attributes, $post_types);

    if (!$post_types) {
        return [];
    }

    $cache_key = md5(wp_json_encode([
        'place_ids' => array_values(array_map('intval', $place_ids)),
        'post_types' => $post_types,
        'per_page' => $per_page,
        'exclude_post_id' => $exclude_post_id,
        'sort_mode' => $sort_mode,
    ]));

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    if (iss_relations_graph_is_available()) {
        global $wpdb;

        $place_entity_ids = [];
        foreach ($place_ids as $place_id) {
            $place_entity = iss_relations_graph_find_place_entity((int) $place_id);
            if (is_array($place_entity) && !empty($place_entity['id'])) {
                $place_entity_ids[] = (int) $place_entity['id'];
            }
        }

        $place_entity_ids = array_values(array_unique(array_filter($place_entity_ids)));

        if ($place_entity_ids) {
            $service = iss_graph_get_service();
            $relation_table = $service->get_relation_table_name();
            $entity_table = $service->get_entity_table_name();
            $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
            $placeholders = implode(', ', array_fill(0, count($place_entity_ids), '%d'));
            $params = array_merge(['place'], $post_types, $place_entity_ids);
            $limit = max(1, min(24, $per_page));
            $rank_sql = iss_relations_graph_relation_rank_sql('r');

            $sql = "SELECT
                    e.post_id,
                    MAX({$rank_sql}) AS relation_rank,
                    MAX(p.post_date_gmt) AS latest_post_date
                FROM {$relation_table} r
                INNER JOIN {$entity_table} e
                    ON e.id = r.from_entity_id
                INNER JOIN {$wpdb->posts} p
                    ON p.ID = e.post_id
                WHERE r.relation_family = %s
                  AND r.is_public = 1
                  AND p.post_type IN ({$type_placeholders})
                  AND p.post_status = 'publish'
                  AND e.post_id > 0
                  AND r.to_entity_id IN ({$placeholders})";

            if ($exclude_post_id > 0) {
                $sql .= ' AND e.post_id <> %d';
                $params[] = $exclude_post_id;
            }

            $sql .= ' GROUP BY e.post_id ORDER BY relation_rank DESC';

            if ($sort_mode === 'relevance_date') {
                $sql .= ' , latest_post_date DESC, e.post_id DESC';
            } elseif ($sort_mode === 'relevance_title') {
                $sql .= ' , p.post_title ASC, e.post_id DESC';
            } else {
                $sql .= ' , e.post_id DESC';
            }

            $sql .= ' LIMIT %d';
            $params[] = $limit;

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existing related blocks query plugin-owned graph tables through a prepared dynamic table query and cache the final post list per request.
            $post_ids = $wpdb->get_col($wpdb->prepare($sql, $params));
            $post_ids = array_values(array_unique(array_filter(array_map('intval', is_array($post_ids) ? $post_ids : []))));

            if ($post_ids) {
                $cache[$cache_key] = get_posts([
                    'post_type' => $post_types,
                    'post_status' => 'publish',
                    'posts_per_page' => $limit,
                    'orderby' => 'post__in',
                    'post__in' => $post_ids,
                    'suppress_filters' => true,
                    'ignore_sticky_posts' => true,
                ]);

                return $cache[$cache_key];
            }
        }
    }

    if (count($post_types) === 1 && $post_types[0] === 'archivobjekt' && function_exists('iss_wf_import_get_relation_service')) {
        $post_ids = [];

        foreach ($place_ids as $place_id) {
            $post_ids = array_merge($post_ids, iss_wf_import_get_relation_service()->get_object_post_ids_for_local_place((int) $place_id));
        }

        $post_ids = array_values(array_unique(array_filter(array_map('intval', $post_ids))));
        if ($exclude_post_id > 0) {
            $post_ids = array_values(array_diff($post_ids, [$exclude_post_id]));
        }

        if ($post_ids) {
                $cache[$cache_key] = get_posts([
                    'post_type' => $post_types[0],
                    'post_status' => 'publish',
                    'posts_per_page' => max(1, min(24, $per_page)),
                    'orderby' => 'date',
                    'order' => 'DESC',
                'suppress_filters' => true,
                'ignore_sticky_posts' => true,
                'post__in' => $post_ids,
            ]);

            return $cache[$cache_key];
        }
    }

    $term_ids = iss_relations_get_place_term_ids($place_ids);
    if (!$term_ids) {
        return [];
    }

    $args = [
        'post_type' => count($post_types) === 1 ? $post_types[0] : $post_types,
        'post_status' => 'publish',
        'posts_per_page' => max(1, min(24, $per_page)),
        'orderby' => $sort_mode === 'relevance_title' ? 'title' : 'date',
        'order' => $sort_mode === 'relevance_title' ? 'ASC' : 'DESC',
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Legacy taxonomy read is the fallback when graph relations are unavailable.
        'tax_query' => [
            [
                'taxonomy' => ISS_RELATIONS_TAXONOMY,
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => 'IN',
            ],
        ],
    ];

    if ($exclude_post_id > 0) {
        $args['post__not_in'] = [$exclude_post_id];
    }

    $cache[$cache_key] = get_posts($args);

    return $cache[$cache_key];
}

function iss_relations_resolve_block_posts(array $attributes, int $current_post_id, array $post_types, int $per_page, string $block_name = ''): array
{
    $source = sanitize_key((string) ($attributes['source'] ?? 'current'));
    if (
        $current_post_id > 0
        && $source !== 'manual'
        && count($post_types) === 1
        && $post_types[0] === 'veranstaltung'
        && get_post_type($current_post_id) === 'veranstaltung'
        && function_exists('iss_content_model_veranstaltungen_related')
    ) {
        return iss_content_model_veranstaltungen_related($current_post_id, $per_page);
    }

    if (iss_relations_is_entity_related_source($source)) {
        return iss_relations_query_entity_related_posts($current_post_id, $post_types, $per_page, $attributes);
    }

    if ($source === 'current') {
        $graph_posts = iss_relations_query_entity_related_posts($current_post_id, $post_types, $per_page, array_merge($attributes, [
            'source' => 'entity',
        ]));
        if ($graph_posts) {
            return $graph_posts;
        }
    }

    $place_items = iss_relations_resolve_block_place_items($attributes, $current_post_id, $block_name);
    if (!$place_items) {
        return [];
    }

    $place_post_type = iss_relations_get_place_post_type();
    $include_places = in_array($place_post_type, $post_types, true);
    $content_post_types = array_values(array_diff($post_types, [$place_post_type]));
    $posts = [];

    if ($include_places) {
        foreach ($place_items as $item) {
            $place = $item['post'] ?? null;
            if ($place instanceof WP_Post) {
                $posts[] = $place;
            }
        }
    }

    if (!$content_post_types) {
        return array_slice($posts, 0, $per_page);
    }

    $place_ids = array_values(array_filter(array_map(static function (array $item): int {
        return (int) ($item['place_id'] ?? 0);
    }, $place_items)));

    if (!$place_ids) {
        return [];
    }

    $queried_posts = iss_relations_query_related_posts(
        $place_ids,
        $content_post_types,
        $per_page,
        ($current_post_id > 0 && in_array((string) get_post_type($current_post_id), $content_post_types, true)) ? $current_post_id : 0,
        $attributes
    );

    if (!$posts) {
        return $queried_posts;
    }

    $seen_ids = [];
    $merged = [];

    foreach (array_merge($posts, $queried_posts) as $post) {
        if (!$post instanceof WP_Post || isset($seen_ids[$post->ID])) {
            continue;
        }

        $seen_ids[$post->ID] = true;
        $merged[] = $post;
    }

    return array_slice($merged, 0, $per_page);
}

function iss_relations_should_apply_related_editorial_signals(array $attributes, int $current_post_id): bool
{
    if ($current_post_id <= 0 || !function_exists('iss_graph_get_active_editorial_signals_for_post')) {
        return false;
    }

    $source = sanitize_key((string) ($attributes['source'] ?? 'current'));

    return $source !== 'manual';
}

function iss_relations_get_related_editorial_signals(int $current_post_id): array
{
    if ($current_post_id <= 0 || !function_exists('iss_graph_get_active_editorial_signals_for_post')) {
        return [];
    }

    return iss_graph_get_active_editorial_signals_for_post($current_post_id, 'related');
}

function iss_relations_get_related_editorial_signals_by_target(int $current_post_id): array
{
    $by_target = [];

    foreach (iss_relations_get_related_editorial_signals($current_post_id) as $signal) {
        if (!is_array($signal)) {
            continue;
        }

        $target_post_id = absint($signal['target_post_id'] ?? 0);
        if ($target_post_id <= 0 || $target_post_id === $current_post_id) {
            continue;
        }

        $by_target[$target_post_id] = $signal;
    }

    return $by_target;
}

function iss_relations_get_promoted_related_posts(array $post_types, int $per_page, int $exclude_post_id = 0): array
{
    if (!function_exists('iss_graph_get_active_related_promotion_post_ids')) {
        return [];
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'iss_relations_is_related_query_post_type')));
    if (!$post_types) {
        return [];
    }

    $limit = max(1, min(24, $per_page));
    $post_ids = iss_graph_get_active_related_promotion_post_ids($post_types, [
        'exclude_post_id' => $exclude_post_id,
        'post_status' => 'publish',
        'limit' => $limit,
    ]);

    if (!$post_ids) {
        return [];
    }

    return get_posts([
        'post_type' => count($post_types) === 1 ? $post_types[0] : $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'post__in',
        'post__in' => $post_ids,
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
    ]);
}

function iss_relations_apply_editorial_signals_to_related_posts(array $posts, int $current_post_id, array $post_types, int $per_page, array $attributes = []): array
{
    $limit = max(1, min(24, $per_page));
    if (!iss_relations_should_apply_related_editorial_signals($attributes, $current_post_id)) {
        return array_slice($posts, 0, $limit);
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'iss_relations_is_related_query_post_type')));
    if (!$post_types) {
        return [];
    }

    $signals = iss_relations_get_related_editorial_signals($current_post_id);
    $promoted_posts = iss_relations_get_promoted_related_posts($post_types, $limit, $current_post_id);
    if (!$signals && !$promoted_posts) {
        return array_slice($posts, 0, $limit);
    }

    $allowed_post_types = array_fill_keys($post_types, true);
    $pin_ids = [];
    $feature_ids = [];
    $boost_ids = [];
    $suppress_ids = [];

    foreach ($signals as $signal) {
        if (!is_array($signal)) {
            continue;
        }

        $target_post_id = absint($signal['target_post_id'] ?? 0);
        if ($target_post_id <= 0 || $target_post_id === $current_post_id) {
            continue;
        }

        $signal_type = sanitize_key((string) ($signal['signal'] ?? ''));
        if ($signal_type === 'pin') {
            $pin_ids[] = $target_post_id;
        } elseif ($signal_type === 'feature') {
            $feature_ids[] = $target_post_id;
        } elseif ($signal_type === 'boost') {
            $boost_ids[] = $target_post_id;
        } elseif ($signal_type === 'suppress' || $signal_type === 'hide') {
            $suppress_ids[$target_post_id] = true;
        }
    }

    foreach ($promoted_posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $feature_ids[] = (int) $post->ID;
    }

    $priority_ids = array_values(array_unique(array_filter(array_merge($pin_ids, $feature_ids, $boost_ids))));
    $priority_lookup = array_fill_keys($priority_ids, true);
    $featured_posts = [];
    $seen = [];

    foreach ($priority_ids as $target_post_id) {
        if (isset($suppress_ids[$target_post_id])) {
            continue;
        }

        $post = get_post($target_post_id);
        if (
            !$post instanceof WP_Post
            || $post->post_status !== 'publish'
            || empty($allowed_post_types[(string) $post->post_type])
        ) {
            continue;
        }

        $seen[$post->ID] = true;
        $featured_posts[] = $post;
    }

    $automatic_posts = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post || isset($suppress_ids[$post->ID]) || isset($priority_lookup[$post->ID]) || isset($seen[$post->ID])) {
            continue;
        }

        $seen[$post->ID] = true;
        $automatic_posts[] = $post;
    }

    return array_slice(array_merge($featured_posts, $automatic_posts), 0, $limit);
}

function iss_relations_prepare_editorial_signal_preview_items(int $current_post_id): array
{
    $items = [];

    foreach (iss_relations_get_related_editorial_signals($current_post_id) as $signal) {
        if (!is_array($signal)) {
            continue;
        }

        $target_post_id = absint($signal['target_post_id'] ?? 0);
        $post = $target_post_id > 0 ? get_post($target_post_id) : null;
        if (!$post instanceof WP_Post || $target_post_id === $current_post_id) {
            continue;
        }

        $items[] = [
            'targetPostId' => $target_post_id,
            'signal' => sanitize_key((string) ($signal['signal'] ?? '')),
            'reason' => (string) ($signal['reason'] ?? ''),
            'expiresAt' => isset($signal['expires_at']) && $signal['expires_at'] !== null ? substr((string) $signal['expires_at'], 0, 10) : '',
            'target' => [
                'id' => $target_post_id,
                'title' => (string) get_the_title($post),
                'postType' => (string) $post->post_type,
                'permalink' => (string) get_permalink($post),
                'kicker' => iss_relations_get_card_kicker($post),
            ],
        ];
    }

    return $items;
}

function iss_relations_get_card_meta_line(WP_Post $post): string
{
    if ($post->post_type === 'publication' && function_exists('iss_publications_get_card_meta_items')) {
        $items = array_values(array_filter((array) iss_publications_get_card_meta_items((int) $post->ID), static function ($item) {
            return is_scalar($item) && trim((string) $item) !== '';
        }));

        if ($items) {
            return implode(' · ', array_slice(array_map(static function ($item) {
                return trim((string) $item);
            }, $items), 0, 2));
        }
    }

    if ($post->post_type === 'veranstaltung' && function_exists('iss_content_model_get_meta_rows_for_post')) {
        $rows = iss_content_model_get_meta_rows_for_post((int) $post->ID);
        $parts = [];

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['value'])) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = trim(wp_strip_all_tags((string) ($row['value'] ?? '')));
            if ($value === '') {
                continue;
            }

            $parts[] = $label !== '' ? ($label . ': ' . $value) : $value;
            if (count($parts) >= 2) {
                break;
            }
        }

        return implode(' · ', $parts);
    }

    return '';
}

function iss_relations_get_card_kicker(WP_Post $post, array $defaults = []): string
{
    if ($post->post_type === iss_relations_get_place_post_type()) {
        return __('Ort', 'iss-relations');
    }

    if ($post->post_type === 'fuehrung') {
        $badge = trim((string) get_post_meta((int) $post->ID, 'tour_badge', true));
        if ($badge !== '') {
            return $badge;
        }

        $contract_label = iss_relations_get_offer_contract_card_kicker($post);
        return $contract_label !== '' ? $contract_label : __('Führung', 'iss-relations');
    }

    if ($post->post_type === 'veranstaltung') {
        $contract_label = iss_relations_get_offer_contract_card_kicker($post);
        return $contract_label !== '' ? $contract_label : __('Veranstaltung', 'iss-relations');
    }

    if ($post->post_type === 'ausstellung') {
        return __('Ausstellung', 'iss-relations');
    }

    if ($post->post_type === 'publication' && function_exists('iss_publications_get_card_kicker')) {
        $kicker = trim((string) iss_publications_get_card_kicker((int) $post->ID));
        if ($kicker !== '') {
            return $kicker;
        }
    }

    if ($post->post_type === 'publication') {
        return __('Publikation', 'iss-relations');
    }

    if ($post->post_type === 'video') {
        return __('Video', 'iss-relations');
    }

    if ($post->post_type === 'archivobjekt') {
        return __('Archivobjekt', 'iss-relations');
    }

    if ($post->post_type === 'projekt') {
        return __('Projekt', 'iss-relations');
    }

    if ($post->post_type === (function_exists('iss_graph_get_entity_profile_post_type') ? iss_graph_get_entity_profile_post_type() : 'entity_profile')) {
        return __('Profil', 'iss-relations');
    }

    if ($post->post_type === 'post') {
        return __('Beitrag', 'iss-relations');
    }

    if ($post->post_type === 'page') {
        return __('Seite', 'iss-relations');
    }

    if ($post->post_type === 'archivbeitrag') {
        return __('Archiv', 'iss-relations');
    }

    return trim((string) ($defaults['kicker'] ?? ''));
}

function iss_relations_get_offer_contract_card_kicker(WP_Post $post): string
{
    if (!function_exists('iss_graph_get_contract_payload_for_post') || !function_exists('iss_graph_get_contract_public_label')) {
        return '';
    }

    $contract = iss_graph_get_contract_payload_for_post($post);
    if (!is_array($contract) || sanitize_key((string) ($contract['kind'] ?? '')) !== 'offer') {
        return '';
    }

    return trim((string) iss_graph_get_contract_public_label($contract));
}

function iss_relations_get_card_detail_line(WP_Post $post): string
{
    if ($post->post_type !== 'fuehrung') {
        return '';
    }

    if (function_exists('iss_fuehrung_get_next_event')) {
        $next_event = iss_fuehrung_get_next_event((int) $post->ID);
        if (function_exists('iss_fuehrung_is_calendar_event') && iss_fuehrung_is_calendar_event($next_event)
            && function_exists('iss_fuehrung_get_event_start_label')) {
            return __('Nächster Termin:', 'iss-relations') . ' ' . iss_fuehrung_get_event_start_label($next_event);
        }
    }

    if (!function_exists('iss_fuehrung_get_effective_booking_mode')) {
        return '';
    }

    $mode = iss_fuehrung_get_effective_booking_mode((int) $post->ID);

    if ($mode === 'on_demand') {
        return __('Status: Auf Anfrage buchbar', 'iss-relations');
    }

    if ($mode === 'hybrid') {
        return __('Status: Aktuell keine Termine online · Anfrage möglich', 'iss-relations');
    }

    return __('Status: Aktuell keine Termine online', 'iss-relations');
}

function iss_relations_get_card_excerpt(WP_Post $post, int $max_words = 22): string
{
    if ($post->post_type === iss_relations_get_place_post_type()) {
        foreach (['current_use', 'history_short'] as $meta_key) {
            $value = trim(wp_strip_all_tags((string) get_post_meta((int) $post->ID, $meta_key, true)));
            if ($value !== '') {
                return wp_trim_words($value, $max_words, '…');
            }
        }
    }

    $excerpt = trim((string) get_the_excerpt($post));
    if ($excerpt !== '') {
        return $excerpt;
    }

    return wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post->ID)), $max_words, '…');
}
