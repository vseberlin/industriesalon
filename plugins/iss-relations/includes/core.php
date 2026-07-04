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

function iss_relations_get_role_priority_map(): array
{
    return [
        'primary' => 500,
        'venue' => 400,
        'subject' => 300,
        'related' => 200,
        // Station weights describe route order, not generic relevance.
        'stop' => 100,
    ];
}

function iss_relations_get_role_priority(string $role): int
{
    $role = sanitize_key($role);
    $map = iss_relations_get_role_priority_map();

    return (int) ($map[$role] ?? 0);
}

function iss_relations_role_uses_weight_for_relevance(string $role): bool
{
    $role = sanitize_key($role);

    return $role !== '' && $role !== 'stop';
}

function iss_relations_compare_place_items(array $left, array $right): int
{
    $left_role = sanitize_key((string) ($left['role'] ?? 'related'));
    $right_role = sanitize_key((string) ($right['role'] ?? 'related'));
    $left_priority = iss_relations_get_role_priority($left_role);
    $right_priority = iss_relations_get_role_priority($right_role);

    if ($left_priority !== $right_priority) {
        return $right_priority <=> $left_priority;
    }

    $left_weight = (int) ($left['weight'] ?? 0);
    $right_weight = (int) ($right['weight'] ?? 0);

    if ($left_role === 'stop' && $right_role === 'stop') {
        if ($left_weight !== $right_weight) {
            return $left_weight <=> $right_weight;
        }
    } elseif ($left_weight !== $right_weight) {
        return $right_weight <=> $left_weight;
    }

    return ((int) ($left['place_id'] ?? 0)) <=> ((int) ($right['place_id'] ?? 0));
}

function iss_relations_target_from_place_id(int $place_id): array
{
    return [
        'entity_id' => 0,
        'entity_kind' => 'place',
        'post_id' => $place_id,
    ];
}

function iss_relations_normalize_relation_target(array $relation): ?array
{
    $target = isset($relation['target']) && is_array($relation['target']) ? $relation['target'] : [];
    $entity_id = absint($target['entity_id'] ?? ($relation['entity_id'] ?? ($relation['to_entity_id'] ?? 0)));
    $entity_kind = sanitize_key((string) ($target['entity_kind'] ?? ($relation['entity_kind'] ?? ($relation['relation_family'] ?? ''))));
    $post_id = absint($target['post_id'] ?? ($relation['post_id'] ?? 0));

    if ($entity_id > 0 && function_exists('iss_graph_get_service')) {
        $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
        if (!is_array($entity)) {
            return null;
        }

        $entity_post_id = absint($entity['post_id'] ?? ($entity['profile_post_id'] ?? 0));
        $actual_entity_kind = sanitize_key((string) ($entity['entity_kind'] ?? ''));
        if ($entity_kind !== '' && $actual_entity_kind !== '' && $entity_kind !== $actual_entity_kind) {
            return null;
        }

        if ($post_id <= 0) {
            $post_id = $entity_post_id;
        } elseif ($entity_post_id > 0 && $post_id !== $entity_post_id) {
            return null;
        }

        if ($entity_kind === '') {
            $entity_kind = $actual_entity_kind;
        }
    }

    if ($post_id <= 0) {
        $place_id = absint($relation['place_id'] ?? 0);
        if ($place_id > 0) {
            $post_id = $place_id;
            if ($entity_kind === '') {
                $entity_kind = 'place';
            }
        }
    }

    if ($entity_kind === '' && $post_id > 0 && get_post_type($post_id) === iss_relations_get_place_post_type()) {
        $entity_kind = 'place';
    }

    if ($entity_kind === 'register_place') {
        $entity_kind = 'place';
    }

    if ($entity_kind === '' || ($post_id <= 0 && $entity_id <= 0)) {
        return null;
    }

    if ($entity_kind !== 'place' && $entity_id <= 0) {
        return null;
    }

    return [
        'entity_id' => $entity_id,
        'entity_kind' => $entity_kind,
        'post_id' => $post_id,
    ];
}

function iss_relations_graph_relation_rank_sql(string $relation_alias = 'r'): string
{
    $relation_alias = preg_replace('/[^A-Za-z0-9_]/', '', $relation_alias);
    if ($relation_alias === '') {
        $relation_alias = 'r';
    }

    $priority_when = [];
    foreach (iss_relations_get_role_priority_map() as $role => $priority) {
        $role_sql = esc_sql($role);
        $priority_when[] = sprintf(
            "WHEN %s.relation_type = '%s' OR %s.relation_role = '%s' THEN %d",
            $relation_alias,
            $role_sql,
            $relation_alias,
            $role_sql,
            (int) $priority
        );
    }

    $weight_when = [];
    foreach (iss_relations_get_role_priority_map() as $role => $priority) {
        if (!iss_relations_role_uses_weight_for_relevance($role)) {
            continue;
        }

        $role_sql = esc_sql($role);
        $weight_when[] = sprintf(
            "WHEN %s.relation_type = '%s' OR %s.relation_role = '%s' THEN LEAST(GREATEST(COALESCE(%s.weight, 0), -999999), 999999)",
            $relation_alias,
            $role_sql,
            $relation_alias,
            $role_sql,
            $relation_alias
        );
    }

    $priority_sql = sprintf(
        "CASE WHEN %s.is_primary = 1 THEN %d %s ELSE 0 END",
        $relation_alias,
        iss_relations_get_role_priority('primary'),
        $priority_when ? ' ' . implode(' ', $priority_when) : ''
    );

    $weight_sql = 'CASE';
    if ($weight_when) {
        $weight_sql .= ' ' . implode(' ', $weight_when) . ' ';
    }
    $weight_sql .= 'ELSE 0 END';

    return '((' . $priority_sql . ') * 1000000) + (' . $weight_sql . ')';
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
    $target = iss_relations_normalize_relation_target($relation);
    if (!$target) {
        return null;
    }

    $is_place_target = (string) $target['entity_kind'] === 'place';
    $place_id = $is_place_target ? absint($target['post_id'] ?? ($relation['place_id'] ?? 0)) : 0;
    if ($is_place_target && ($place_id <= 0 || !iss_relations_is_usable_place($place_id))) {
        return null;
    }

    if (
        $is_place_target
        &&
        $context_post_id > 0
        && $place_id === $context_post_id
        && get_post_type($context_post_id) === iss_relations_get_place_post_type()
    ) {
        return null;
    }

    $role = sanitize_key((string) ($relation['role'] ?? ($relation['relation_role'] ?? ($relation['relation_type'] ?? 'related'))));
    $roles = iss_relations_get_role_options();
    if ($is_place_target && !isset($roles[$role])) {
        $role = 'related';
    }
    if (!$is_place_target && $role === '') {
        $role = 'related';
    }

    $relation_family = sanitize_key((string) ($relation['relation_family'] ?? $target['entity_kind']));
    if ($relation_family === '') {
        $relation_family = $target['entity_kind'];
    }
    $relation_type = sanitize_key((string) ($relation['relation_type'] ?? $role));
    if ($relation_type === '') {
        $relation_type = $role;
    }

    $route_title = sanitize_text_field((string) ($relation['route_title'] ?? ''));
    $route_teaser = sanitize_textarea_field((string) ($relation['route_teaser'] ?? ''));
    $station_object_id = absint($relation['station_object_id'] ?? 0);
    $station_story_id = absint($relation['station_story_id'] ?? 0);

    $clean = [
        'target' => $target,
        'relation_family' => $relation_family,
        'relation_type' => $relation_type,
        'relation_role' => $role,
        'place_id' => $place_id,
        'role' => $role,
        'weight' => (int) ($relation['weight'] ?? 0),
        'label' => sanitize_text_field((string) ($relation['label'] ?? '')),
        'route_title' => $route_title,
        'route_teaser' => $route_teaser,
        'station_object_id' => $station_object_id,
        'station_story_id' => $station_story_id,
    ];

    if (!$is_place_target) {
        $clean['place_id'] = 0;
        $clean['route_title'] = '';
        $clean['route_teaser'] = '';
        $clean['station_object_id'] = 0;
        $clean['station_story_id'] = 0;
    }

    return $clean;
}

function iss_relations_normalize_relations($relations, int $context_post_id = 0): array
{
    if (!is_array($relations)) {
        return [];
    }

    $normalized = [];
    $seen_targets = [];

    foreach ($relations as $relation) {
        if (!is_array($relation)) {
            continue;
        }

        $clean = iss_relations_normalize_relation($relation, $context_post_id);
        if (!$clean) {
            continue;
        }

        $target = isset($clean['target']) && is_array($clean['target']) ? $clean['target'] : [];
        $target_key = sanitize_key((string) ($target['entity_kind'] ?? '')) . ':';
        $target_key .= (int) ($target['entity_id'] ?? 0) > 0
            ? 'entity:' . (int) ($target['entity_id'] ?? 0)
            : 'post:' . (int) ($target['post_id'] ?? 0);
        if (isset($seen_targets[$target_key])) {
            continue;
        }

        $seen_targets[$target_key] = true;
        $normalized[] = $clean;
    }

    return $normalized;
}

function iss_relations_get_stored_post_relations(int $post_id): array
{
    $stored = get_post_meta($post_id, ISS_RELATIONS_META_KEY, true);

    return iss_relations_normalize_relations(is_array($stored) ? $stored : [], $post_id);
}

function iss_relations_supports_route_drafts(int $post_id): bool
{
    $post_type = (string) get_post_type($post_id);

    return $post_id > 0 && iss_relations_supports_route_fields($post_type);
}

function iss_relations_route_station_rows(array $relations): array
{
    return array_values(array_filter($relations, static function ($relation): bool {
        return is_array($relation) && (($relation['role'] ?? '') === 'stop');
    }));
}

function iss_relations_route_relation_hash(array $relations): string
{
    $rows = [];

    foreach (iss_relations_route_station_rows($relations) as $relation) {
        $rows[] = [
            'place_id' => (int) ($relation['place_id'] ?? 0),
            'role' => (string) ($relation['role'] ?? ''),
            'weight' => (int) ($relation['weight'] ?? 0),
            'label' => (string) ($relation['label'] ?? ''),
            'route_title' => (string) ($relation['route_title'] ?? ''),
            'route_teaser' => (string) ($relation['route_teaser'] ?? ''),
            'station_object_id' => (int) ($relation['station_object_id'] ?? 0),
            'station_story_id' => (int) ($relation['station_story_id'] ?? 0),
        ];
    }

    return hash('sha256', wp_json_encode($rows));
}

function iss_relations_get_route_draft(int $post_id): array
{
    if (!iss_relations_supports_route_drafts($post_id)) {
        return [];
    }

    $draft = get_post_meta($post_id, ISS_RELATIONS_ROUTE_DRAFT_META_KEY, true);
    if (!is_array($draft)) {
        return [];
    }

    $relations = iss_relations_normalize_relations(is_array($draft['relations'] ?? null) ? $draft['relations'] : [], $post_id);
    $trash = iss_relations_normalize_relations(is_array($draft['trash'] ?? null) ? $draft['trash'] : [], $post_id);

    return [
        'schema_version' => 1,
        'relations' => $relations,
        'trash' => iss_relations_route_station_rows($trash),
        'base_hash' => sanitize_text_field((string) ($draft['base_hash'] ?? '')),
        'updated_at' => sanitize_text_field((string) ($draft['updated_at'] ?? '')),
        'updated_by' => absint($draft['updated_by'] ?? 0),
    ];
}

function iss_relations_save_route_draft(int $post_id, array $relations, array $trash = [], string $base_hash = ''): array
{
    if (!iss_relations_supports_route_drafts($post_id)) {
        return [];
    }

    $clean_relations = iss_relations_normalize_relations($relations, $post_id);
    $clean_trash = iss_relations_route_station_rows(iss_relations_normalize_relations($trash, $post_id));
    $canonical = iss_relations_get_stored_post_relations($post_id);
    $base_hash = $base_hash !== '' ? sanitize_text_field($base_hash) : iss_relations_route_relation_hash($canonical);

    $draft = [
        'schema_version' => 1,
        'relations' => $clean_relations,
        'trash' => $clean_trash,
        'base_hash' => $base_hash,
        'updated_at' => current_time('mysql'),
        'updated_by' => get_current_user_id(),
    ];

    update_post_meta($post_id, ISS_RELATIONS_ROUTE_DRAFT_META_KEY, $draft);

    return iss_relations_get_route_draft($post_id);
}

function iss_relations_create_route_draft(int $post_id): array
{
    $existing = iss_relations_get_route_draft($post_id);
    if ($existing) {
        return $existing;
    }

    $canonical = iss_relations_get_stored_post_relations($post_id);

    return iss_relations_save_route_draft(
        $post_id,
        $canonical,
        [],
        iss_relations_route_relation_hash($canonical)
    );
}

function iss_relations_discard_route_draft(int $post_id): void
{
    if (iss_relations_supports_route_drafts($post_id)) {
        delete_post_meta($post_id, ISS_RELATIONS_ROUTE_DRAFT_META_KEY);
    }
}

function iss_relations_add_route_snapshot(int $post_id, array $canonical, array $draft, array $trash): void
{
    $snapshots = get_post_meta($post_id, ISS_RELATIONS_ROUTE_SNAPSHOTS_META_KEY, true);
    $snapshots = is_array($snapshots) ? $snapshots : [];
    array_unshift($snapshots, [
        'created_at' => current_time('mysql'),
        'created_by' => get_current_user_id(),
        'canonical' => $canonical,
        'published' => $draft,
        'deleted' => iss_relations_route_station_rows($trash),
        'canonical_hash' => iss_relations_route_relation_hash($canonical),
        'published_hash' => iss_relations_route_relation_hash($draft),
    ]);

    update_post_meta(
        $post_id,
        ISS_RELATIONS_ROUTE_SNAPSHOTS_META_KEY,
        array_slice($snapshots, 0, 10)
    );
}

function iss_relations_ensure_route_station_source_relations(array $relations): void
{
    foreach (iss_relations_route_station_rows($relations) as $relation) {
        $place_id = (int) ($relation['place_id'] ?? 0);
        if ($place_id <= 0) {
            continue;
        }

        $station_object_id = (int) ($relation['station_object_id'] ?? 0);
        if ($station_object_id > 0) {
            iss_relations_ensure_post_has_place_relation($station_object_id, $place_id);
        }

        $station_story_id = (int) ($relation['station_story_id'] ?? 0);
        if ($station_story_id > 0) {
            iss_relations_ensure_post_has_place_relation($station_story_id, $place_id);
        }
    }
}

function iss_relations_publish_route_draft(int $post_id): array
{
    $draft = iss_relations_get_route_draft($post_id);
    if (!$draft) {
        return [
            'relations' => iss_relations_get_stored_post_relations($post_id),
            'published' => false,
        ];
    }

    $canonical = iss_relations_get_stored_post_relations($post_id);
    $published = iss_relations_normalize_relations((array) ($draft['relations'] ?? []), $post_id);
    iss_relations_add_route_snapshot($post_id, $canonical, $published, (array) ($draft['trash'] ?? []));
    iss_relations_update_post_relations($post_id, $published);
    iss_relations_ensure_route_station_source_relations($published);
    update_post_meta($post_id, ISS_RELATIONS_ROUTE_GRAPH_HASH_META_KEY, iss_relations_route_relation_hash($published));
    delete_post_meta($post_id, ISS_RELATIONS_ROUTE_DRAFT_META_KEY);
    iss_relations_sync_post_read_models($post_id);

    return [
        'relations' => iss_relations_get_stored_post_relations($post_id),
        'published' => true,
    ];
}

function iss_relations_route_draft_preview_nonce_action(int $post_id): string
{
    return 'iss_relations_route_draft_preview_' . (int) $post_id;
}

function iss_relations_get_route_draft_preview_args(int $post_id): array
{
    return [
        'iss_relations_route_draft_preview' => '1',
        'iss_relations_route_draft_nonce' => wp_create_nonce(iss_relations_route_draft_preview_nonce_action($post_id)),
    ];
}

function iss_relations_should_prefer_route_draft(int $post_id): bool
{
    if (!iss_relations_supports_route_drafts($post_id) || !current_user_can('edit_post', $post_id)) {
        return false;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce is verified below for read-only preview routing.
    if ((string) ($_GET['iss_relations_route_draft_preview'] ?? '') !== '1') {
        return false;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only preview nonce.
    $nonce = isset($_GET['iss_relations_route_draft_nonce']) ? sanitize_text_field(wp_unslash((string) $_GET['iss_relations_route_draft_nonce'])) : '';

    return $nonce !== '' && wp_verify_nonce($nonce, iss_relations_route_draft_preview_nonce_action($post_id));
}

function iss_relations_get_route_preview_relations(int $post_id): array
{
    if (!iss_relations_should_prefer_route_draft($post_id)) {
        return [];
    }

    $draft = iss_relations_get_route_draft($post_id);

    return $draft ? (array) ($draft['relations'] ?? []) : [];
}

function iss_relations_graph_is_available(): bool
{
    return function_exists('iss_graph_get_service');
}

function iss_relations_graph_entity_kind_for_post(WP_Post $post): string
{
    if ($post->post_type === iss_relations_get_place_post_type()) {
        return 'place';
    }

    if ($post->post_type === 'archivobjekt') {
        return 'archive_object';
    }

    return sanitize_key($post->post_type);
}

function iss_relations_graph_should_read_for_post(WP_Post $post): bool
{
    if (!iss_relations_graph_is_available() || !iss_relations_is_supported_post_type($post->post_type)) {
        return false;
    }

    // Archive objects are graph-synced through the archive bridge, but
    // relation-service still reads local place meta via iss_relations_* helpers.
    // Keep archive-object reads meta-backed here to avoid recursive graph sync.
    if ($post->post_type === 'archivobjekt') {
        return false;
    }

    return true;
}

function iss_relations_graph_find_place_entity(int $place_id): ?array
{
    static $cache = [];

    if ($place_id <= 0 || !iss_relations_graph_is_available()) {
        return null;
    }

    if (array_key_exists($place_id, $cache)) {
        return $cache[$place_id];
    }

    $entity = iss_graph_get_service()->find_entity_by_post('place', $place_id);
    if ($entity) {
        $cache[$place_id] = $entity;
        return $cache[$place_id];
    }

    if (function_exists('iss_graph_sync_register_place_entity')) {
        $cache[$place_id] = iss_graph_sync_register_place_entity($place_id);
        return $cache[$place_id];
    }

    $cache[$place_id] = null;

    return null;
}

function iss_relations_graph_content_bridge_owns_post(WP_Post $post): bool
{
    return function_exists('iss_graph_is_content_relation_post_type')
        && function_exists('iss_graph_sync_public_content_entity')
        && iss_graph_is_content_relation_post_type((string) $post->post_type);
}

function iss_relations_graph_build_place_relation_rows(array $relations): array
{
    $rows = [];

    foreach (array_values($relations) as $index => $relation) {
        if (!is_array($relation)) {
            continue;
        }

        $target = isset($relation['target']) && is_array($relation['target']) ? $relation['target'] : iss_relations_normalize_relation_target($relation);
        if (!is_array($target) || (string) ($target['entity_kind'] ?? '') !== 'place') {
            continue;
        }

        $place_id = absint($target['post_id'] ?? ($relation['place_id'] ?? 0));
        if ($place_id <= 0) {
            continue;
        }

        $target_entity_id = absint($target['entity_id'] ?? 0);
        $place_entity = $target_entity_id > 0 && function_exists('iss_graph_get_service')
            ? iss_graph_get_service()->get_entity_by_id($target_entity_id)
            : null;

        if (!is_array($place_entity) || empty($place_entity['id']) || (string) ($place_entity['entity_kind'] ?? '') !== 'place') {
            $place_entity = iss_relations_graph_find_place_entity($place_id);
        }

        if (!is_array($place_entity) || empty($place_entity['id'])) {
            continue;
        }

        $role = sanitize_key((string) ($relation['relation_role'] ?? ($relation['role'] ?? 'related')));
        if (!isset(iss_relations_get_role_options()[$role])) {
            $role = 'related';
        }
        $relation_type = sanitize_key((string) ($relation['relation_type'] ?? $role));
        if ($relation_type === '') {
            $relation_type = $role;
        }

        $rows[] = [
            'to_entity_id' => (int) $place_entity['id'],
            'relation_type' => $relation_type,
            'relation_role' => $role,
            'relation_label' => sanitize_text_field((string) ($relation['label'] ?? '')),
            'weight' => (int) ($relation['weight'] ?? 0),
            'position' => $index,
            'is_primary' => $role === 'primary',
            'source_field' => 'iss_relations',
            'confidence' => 100,
            'is_public' => true,
        ];
    }

    return $rows;
}

function iss_relations_graph_upsert_generic_post_entity(WP_Post $post, array $stored_relations = []): ?array
{
    if (!iss_relations_graph_is_available()) {
        return null;
    }

    if (iss_relations_graph_content_bridge_owns_post($post)) {
        return null;
    }

    $service = iss_graph_get_service();
    $entity_kind = iss_relations_graph_entity_kind_for_post($post);
    $existing = $service->find_entity_by_post($entity_kind, (int) $post->ID);

    if ($post->post_type !== iss_relations_get_place_post_type() && !$stored_relations && !$existing) {
        return null;
    }

    if (in_array($post->post_status, ['auto-draft', 'trash'], true)) {
        if ($existing) {
            $service->delete_entity_by_post($entity_kind, (int) $post->ID);
        }

        return null;
    }

    $entity = $service->upsert_entity([
        'entity_kind' => $entity_kind,
        'post_id' => (int) $post->ID,
        'source_system' => 'wp_post',
        'source_id' => (string) $post->ID,
        'canonical_slug' => $post->post_type . '-' . (int) $post->ID . '-' . (trim((string) $post->post_name) !== '' ? (string) $post->post_name : sanitize_title((string) get_the_title($post))),
        'display_title' => get_the_title($post),
        'summary' => (string) get_post_field('post_excerpt', $post->ID),
        'status' => sanitize_key((string) $post->post_status),
        'is_public' => $post->post_status === 'publish',
        'search_visibility' => 'hidden',
    ]);

    if (!$entity) {
        return null;
    }

    $service->replace_entity_names_for_source((int) $entity['id'], 'iss_relations_post_title', [[
        'name' => get_the_title($post) ?: ($post->post_type . ' ' . (int) $post->ID),
        'name_type' => 'primary',
        'is_primary' => true,
        'position' => 0,
    ]], 'post:' . (int) $post->ID);

    if (function_exists('iss_graph_sync_wp_post_identifiers')) {
        iss_graph_sync_wp_post_identifiers((int) $entity['id'], $post, 'iss_relations_post');
    }

    if (function_exists('iss_graph_sync_entity_alias_backfill')) {
        iss_graph_sync_entity_alias_backfill((int) $entity['id']);
    }

    return $service->get_entity_by_id((int) $entity['id']);
}

function iss_relations_sync_post_graph(int $post_id): ?array
{
    if (!iss_relations_graph_is_available()) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_relations_is_supported_post_type($post->post_type)) {
        return null;
    }

    if ($post->post_type === 'archivobjekt' && function_exists('iss_graph_sync_archive_object_entity')) {
        return iss_graph_sync_archive_object_entity($post_id);
    }

    $stored_relations = iss_relations_get_stored_post_relations($post_id);

    if ($post->post_type === iss_relations_get_place_post_type() && function_exists('iss_graph_sync_register_place_entity')) {
        $entity = iss_graph_sync_register_place_entity($post_id);
    } elseif (iss_relations_graph_content_bridge_owns_post($post)) {
        $entity = iss_graph_sync_public_content_entity($post_id);
    } else {
        $entity = iss_relations_graph_upsert_generic_post_entity($post, $stored_relations);
    }

    if (!$entity || empty($entity['id'])) {
        return null;
    }

    iss_graph_get_service()->replace_entity_relations_for_source(
        (int) $entity['id'],
        'place',
        'iss_relations_meta',
        iss_relations_graph_build_place_relation_rows($stored_relations),
        'post:' . $post_id
    );

    return iss_graph_get_service()->get_entity_by_id((int) $entity['id']);
}

function iss_relations_graph_read_post_relations(int $post_id, array $stored_relations = []): ?array
{
    if (!iss_relations_graph_is_available()) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_relations_graph_should_read_for_post($post)) {
        return null;
    }

    $service = iss_graph_get_service();
    $entity = $service->find_entity_by_post(iss_relations_graph_entity_kind_for_post($post), $post_id);

    $can_reconcile = !function_exists('iss_graph_can_reconcile_current_request') || iss_graph_can_reconcile_current_request();
    if (!$entity && $can_reconcile && ($stored_relations || $post->post_type === iss_relations_get_place_post_type())) {
        $entity = iss_relations_sync_post_graph($post_id);
    }

    if (!$entity || empty($entity['id'])) {
        return null;
    }

    $rows = $service->get_relations_for_entity((int) $entity['id'], 'place');
    if (!$rows && $stored_relations && $can_reconcile) {
        $entity = iss_relations_sync_post_graph($post_id);
        if ($entity) {
            $rows = $service->get_relations_for_entity((int) $entity['id'], 'place');
        }
    }

    if (!$rows) {
        return [];
    }

    $stored_by_place = [];
    foreach ($stored_relations as $relation) {
        if (!is_array($relation)) {
            continue;
        }

        $stored_by_place[(int) ($relation['place_id'] ?? 0)] = $relation;
    }

    $mapped = [];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $place_id = absint($row['post_id'] ?? 0);
        if ($place_id <= 0 || !iss_relations_is_usable_place($place_id)) {
            continue;
        }

        $stored = $stored_by_place[$place_id] ?? [];
        $role = sanitize_key((string) ($row['relation_type'] ?? ($stored['role'] ?? 'related')));
        if (!isset(iss_relations_get_role_options()[$role])) {
            $role = 'related';
        }

        $mapped[] = [
            'target' => [
                'entity_id' => (int) ($row['entity_id'] ?? 0),
                'entity_kind' => 'place',
                'post_id' => $place_id,
            ],
            'relation_family' => 'place',
            'relation_type' => sanitize_key((string) ($row['relation_type'] ?? $role)),
            'relation_role' => $role,
            'place_id' => $place_id,
            'role' => $role,
            'weight' => isset($row['weight']) ? (int) $row['weight'] : (int) ($stored['weight'] ?? 0),
            'label' => trim((string) ($row['relation_label'] ?? ($stored['label'] ?? ''))),
            'route_title' => (string) ($stored['route_title'] ?? ''),
            'route_teaser' => (string) ($stored['route_teaser'] ?? ''),
            'station_object_id' => (int) ($stored['station_object_id'] ?? 0),
            'station_story_id' => (int) ($stored['station_story_id'] ?? 0),
        ];
    }

    return iss_relations_normalize_relations($mapped, $post_id);
}

function iss_relations_get_post_relations(int $post_id): array
{
    $stored = iss_relations_get_stored_post_relations($post_id);
    $preview = iss_relations_get_route_preview_relations($post_id);

    if ($preview) {
        return iss_relations_normalize_relations($preview, $post_id);
    }

    $external = apply_filters('iss_relations_external_post_relations', null, $post_id, $stored);

    if ($external !== null) {
        return iss_relations_normalize_relations(is_array($external) ? $external : [], $post_id);
    }

    $graph = iss_relations_graph_read_post_relations($post_id, $stored);

    if ($graph !== null) {
        if ($graph || !$stored) {
            return $graph;
        }
    }

    return $stored;
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
    static $cache = [];

    if (isset($cache[$post_id])) {
        return $cache[$post_id];
    }

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

    if (count($items) > 1) {
        usort($items, 'iss_relations_compare_place_items');
    }

    $cache[$post_id] = $items;

    return $cache[$post_id];
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

function iss_relations_get_quality_backfill_role_map(): array
{
    return (array) apply_filters('iss_relations_quality_backfill_role_map', [
        'video' => 'subject',
        'publication' => 'subject',
        'post' => 'subject',
        'ausstellung' => 'venue',
        'archivbeitrag' => 'subject',
        'archivobjekt' => 'subject',
        'register_place' => 'related',
    ]);
}

function iss_relations_get_quality_backfill_target_post_types(): array
{
    return array_values(array_unique(array_filter(array_map('sanitize_key', array_keys(iss_relations_get_quality_backfill_role_map())))));
}

function iss_relations_get_quality_backfill_relation_weight(int $index, string $role): int
{
    if ($index <= 0) {
        return 100;
    }

    return max(10, 90 - (($index - 1) * 10));
}

function iss_relations_should_backfill_quality_for_post(WP_Post $post, array $relations): bool
{
    if (!$relations) {
        return false;
    }

    $role_map = iss_relations_get_quality_backfill_role_map();
    if (!isset($role_map[$post->post_type])) {
        return false;
    }

    foreach ($relations as $relation) {
        if (!is_array($relation)) {
            return false;
        }

        if (sanitize_key((string) ($relation['role'] ?? 'related')) !== 'related') {
            return false;
        }
    }

    return true;
}

function iss_relations_build_quality_backfill_relations(WP_Post $post, array $relations): array
{
    $lead_role = sanitize_key((string) (iss_relations_get_quality_backfill_role_map()[$post->post_type] ?? ''));
    if ($lead_role === '' || !isset(iss_relations_get_role_options()[$lead_role])) {
        return $relations;
    }

    $normalized = array_values(iss_relations_normalize_relations($relations, (int) $post->ID));
    if (!$normalized) {
        return [];
    }

    // Preserve the existing editor order, but turn the first linked place into
    // the stronger semantic anchor for this post type.
    foreach ($normalized as $index => &$relation) {
        $relation['role'] = $index === 0 ? $lead_role : 'related';
        $relation['weight'] = iss_relations_get_quality_backfill_relation_weight($index, $relation['role']);
    }
    unset($relation);

    return $normalized;
}

function iss_relations_backfill_relation_quality(array $post_types = [], bool $dry_run = false): array
{
    $candidate_types = $post_types
        ? array_values(array_unique(array_filter(array_map('sanitize_key', $post_types))))
        : iss_relations_get_quality_backfill_target_post_types();

    $candidate_types = array_values(array_intersect($candidate_types, iss_relations_get_quality_backfill_target_post_types()));
    if (!$candidate_types) {
        return [
            'count' => 0,
            'post_types' => [],
            'posts' => [],
        ];
    }

    $posts = get_posts([
        'post_type' => $candidate_types,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    $changed = [];
    $changed_count = 0;

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $stored = iss_relations_get_stored_post_relations((int) $post->ID);
        if (!iss_relations_should_backfill_quality_for_post($post, $stored)) {
            continue;
        }

        $updated = iss_relations_build_quality_backfill_relations($post, $stored);
        if ($updated === $stored) {
            continue;
        }

        $changed[] = [
            'post_id' => (int) $post->ID,
            'post_type' => (string) $post->post_type,
            'title' => get_the_title($post),
            'relation_count' => count($updated),
            'before' => $stored,
            'after' => $updated,
        ];

        if (!$dry_run) {
            iss_relations_update_post_relations((int) $post->ID, $updated);
        }

        $changed_count++;
    }

    return [
        'count' => $changed_count,
        'post_types' => $candidate_types,
        'posts' => $changed,
    ];
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

function iss_relations_save_post_relations(int $post_id, array $relations): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_relations_is_supported_post_type((string) $post->post_type)) {
        return [];
    }

    $clean = iss_relations_normalize_relations($relations, $post_id);
    iss_relations_update_post_relations($post_id, $clean);

    if ($post->post_type === 'fuehrung') {
        iss_relations_ensure_route_station_source_relations($clean);
    }

    return $clean;
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

    $relations = iss_relations_get_stored_post_relations($post_id);

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

    foreach (iss_relations_get_stored_post_relations($post_id) as $relation) {
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
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Relation backfill intentionally discovers posts that have relation source meta.
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
        iss_relations_sync_post_read_models($post_id);
    }

    return [
        'count' => count($post_ids),
        'post_ids' => $post_ids,
        'post_types' => $types,
    ];
}

function iss_relations_sync_post_read_models(int $post_id): void
{
    iss_relations_sync_post_terms($post_id);
    iss_relations_sync_post_graph($post_id);
}

function iss_relations_sync_supported_post_on_save(int $post_id, WP_Post $post): void
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!iss_relations_is_supported_post_type($post->post_type)) {
        return;
    }

    if (iss_relations_supports_route_fields($post->post_type)) {
        return;
    }

    iss_relations_sync_post_read_models($post_id);
}
add_action('save_post', 'iss_relations_sync_supported_post_on_save', 30, 2);

function iss_relations_sync_post_terms_on_meta_change($meta_id, int $post_id, string $meta_key): void
{
    if ($meta_key !== ISS_RELATIONS_META_KEY) {
        return;
    }

    iss_relations_sync_post_read_models($post_id);
}
add_action('added_post_meta', 'iss_relations_sync_post_terms_on_meta_change', 10, 3);
add_action('updated_post_meta', 'iss_relations_sync_post_terms_on_meta_change', 10, 3);
add_action('deleted_post_meta', 'iss_relations_sync_post_terms_on_meta_change', 10, 3);

function iss_relations_delete_supported_post_graph_on_before_delete(int $post_id): void
{
    if (!iss_relations_graph_is_available()) {
        return;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || !iss_relations_is_supported_post_type($post->post_type)) {
        return;
    }

    if (
        (function_exists('iss_graph_get_register_post_type') && $post->post_type === iss_graph_get_register_post_type())
        || (function_exists('iss_graph_get_archive_object_post_type') && $post->post_type === iss_graph_get_archive_object_post_type())
        || iss_relations_graph_content_bridge_owns_post($post)
    ) {
        return;
    }

    iss_graph_get_service()->delete_entity_by_post(iss_relations_graph_entity_kind_for_post($post), $post_id);
}
add_action('before_delete_post', 'iss_relations_delete_supported_post_graph_on_before_delete', 7);

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

function iss_relations_maybe_backfill_graph_identifiers(): void
{
    if (!is_admin() || !current_user_can('manage_options')) {
        return;
    }

    if (!function_exists('iss_graph_sync_wp_post_identifiers')) {
        return;
    }

    $stored = (string) get_option(ISS_RELATIONS_GRAPH_IDENTIFIER_BACKFILL_OPTION, '');
    if ($stored === ISS_RELATIONS_GRAPH_IDENTIFIER_BACKFILL_VERSION) {
        return;
    }

    iss_relations_backfill_index();
    update_option(ISS_RELATIONS_GRAPH_IDENTIFIER_BACKFILL_OPTION, ISS_RELATIONS_GRAPH_IDENTIFIER_BACKFILL_VERSION, false);
}
add_action('admin_init', 'iss_relations_maybe_run_backfill', 20);
add_action('admin_init', 'iss_relations_maybe_backfill_graph_identifiers', 21);
