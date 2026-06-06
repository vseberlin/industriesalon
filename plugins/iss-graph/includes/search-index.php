<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_get_public_search_post_type_map(): array
{
    $register_post_type = function_exists('iss_graph_get_register_post_type')
        ? iss_graph_get_register_post_type()
        : 'register_place';
    $profile_post_type = function_exists('iss_graph_get_entity_profile_post_type')
        ? iss_graph_get_entity_profile_post_type()
        : 'entity_profile';
    $archive_post_type = function_exists('iss_graph_get_archive_object_post_type')
        ? iss_graph_get_archive_object_post_type()
        : 'archivobjekt';

    return (array) apply_filters('iss_graph_public_search_post_type_map', [
        $register_post_type => ['bucket' => 'place', 'boost' => 120],
        'fuehrung' => ['bucket' => 'tour', 'boost' => 110],
        'veranstaltung' => ['bucket' => 'event', 'boost' => 100],
        'ausstellung' => ['bucket' => 'exhibition', 'boost' => 95],
        'projekt' => ['bucket' => 'project', 'boost' => 92],
        'publication' => ['bucket' => 'publication', 'boost' => 90],
        'video' => ['bucket' => 'video', 'boost' => 85],
        'post' => ['bucket' => 'story', 'boost' => 80],
        $profile_post_type => ['bucket' => 'profile', 'boost' => 75],
        $archive_post_type => ['bucket' => 'archive_object', 'boost' => 70],
    ]);
}

function iss_graph_get_public_search_post_types(): array
{
    $post_types = [];

    foreach (iss_graph_get_public_search_post_type_map() as $post_type => $config) {
        $post_type = sanitize_key((string) $post_type);
        if ($post_type !== '' && post_type_exists($post_type)) {
            $post_types[] = $post_type;
        }
    }

    return array_values(array_unique($post_types));
}

function iss_graph_get_public_search_post_type_config(string $post_type): ?array
{
    $post_type = sanitize_key($post_type);
    $config = iss_graph_get_public_search_post_type_map();

    return isset($config[$post_type]) && is_array($config[$post_type]) ? $config[$post_type] : null;
}

function iss_graph_maybe_backfill_public_search_index(): void
{
    if (defined('WP_CLI') && WP_CLI) {
        return;
    }

    $post_types = iss_graph_get_public_search_post_types();
    if (!$post_types) {
        return;
    }

    $stored = (string) get_option(ISS_GRAPH_SEARCH_BACKFILL_OPTION, '');
    if ($stored === ISS_GRAPH_SEARCH_BACKFILL_VERSION) {
        return;
    }

    iss_graph_backfill_public_search_index();
    update_option(ISS_GRAPH_SEARCH_BACKFILL_OPTION, ISS_GRAPH_SEARCH_BACKFILL_VERSION, false);
}

function iss_graph_get_entity_kind_for_search_post(WP_Post $post): string
{
    if ($post->post_type === (function_exists('iss_graph_get_register_post_type') ? iss_graph_get_register_post_type() : 'register_place')) {
        return 'place';
    }

    if ($post->post_type === (function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt')) {
        return 'archive_object';
    }

    return sanitize_key((string) $post->post_type);
}

function iss_graph_normalize_search_text_part($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    $value = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
    $value = wp_strip_all_tags($value);
    $value = sanitize_text_field($value);
    $value = preg_replace('/\s+/u', ' ', trim($value));

    return is_string($value) ? $value : '';
}

function iss_graph_join_search_text_parts(array $parts): string
{
    $clean = [];
    $seen = [];

    foreach ($parts as $part) {
        $part = iss_graph_normalize_search_text_part($part);
        if ($part === '' || isset($seen[$part])) {
            continue;
        }

        $seen[$part] = true;
        $clean[] = $part;
    }

    return implode("\n", $clean);
}

function iss_graph_get_defined_constant_value(string $constant_name, string $fallback): string
{
    return defined($constant_name) ? (string) constant($constant_name) : $fallback;
}

function iss_graph_get_search_index_meta_keys_for_post_type(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    $profile_post_type = function_exists('iss_graph_get_entity_profile_post_type')
        ? iss_graph_get_entity_profile_post_type()
        : 'entity_profile';
    $archive_post_type = function_exists('iss_graph_get_archive_object_post_type')
        ? iss_graph_get_archive_object_post_type()
        : 'archivobjekt';

    if ($post_type === (function_exists('iss_graph_get_register_post_type') ? iss_graph_get_register_post_type() : 'register_place')) {
        return [
            'owner',
            'operator',
            'developer',
            'tenant',
            'current_status',
            'current_use_type',
            'previous_use',
            'history_long',
            'place_visibility',
            'iss_related_places',
        ];
    }

    if ($post_type === $profile_post_type) {
        return [function_exists('iss_graph_get_entity_profile_meta_key') ? iss_graph_get_entity_profile_meta_key() : '_iss_graph_profile_entity_id'];
    }

    if ($post_type === $archive_post_type) {
        return [
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_SOURCE_KIND_META', 'iss_archive_source_kind'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META', 'iss_archive_source_external_id'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_INVENTORY_META', 'iss_archive_inventory_number'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_TYPE_META', 'iss_archive_object_type'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_CREATOR_META', 'iss_archive_creator'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_MATERIAL_META', 'iss_archive_material'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_DIMENSIONS_META', 'iss_archive_dimensions'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_RIGHTS_HOLDER_META', 'iss_archive_rights_holder'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_TAGS_META', 'iss_archive_object_tags'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_COLLECTIONS_META', 'iss_archive_object_collections'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_SERIES_META', 'iss_archive_object_series'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_EVENTS_META', 'iss_archive_object_events'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_PLACE_RELATIONS_META', 'iss_archive_object_places'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_PEOPLE_RELATIONS_META', 'iss_archive_object_people'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_RELATIONS_META', 'iss_related_archive_objects'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_MD_INSTITUTION_NAME_META', 'iss_md_institution_name'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_YEAR_META', 'iss_archive_year'),
            iss_graph_get_defined_constant_value('ISS_WF_IMPORT_OBJECT_DECADE_META', 'iss_archive_decade'),
            'iss_related_places',
        ];
    }

    return ['iss_related_places'];
}

function iss_graph_collect_values_for_search_keys($value, array $keys): array
{
    if (!is_array($value)) {
        return [];
    }

    $parts = [];
    foreach ($value as $key => $item) {
        if (is_string($key) && in_array($key, $keys, true) && is_scalar($item)) {
            $parts[] = (string) $item;
            continue;
        }

        if (is_array($item)) {
            $parts = array_merge($parts, iss_graph_collect_values_for_search_keys($item, $keys));
        }
    }

    return $parts;
}

function iss_graph_collect_public_search_text_parts(WP_Post $post): array
{
    $profile_post_type = function_exists('iss_graph_get_entity_profile_post_type')
        ? iss_graph_get_entity_profile_post_type()
        : 'entity_profile';
    $parts = [
        get_the_title($post),
        get_post_field('post_excerpt', $post->ID),
        get_post_field('post_content', $post->ID),
    ];

    if ($post->post_type === (function_exists('iss_graph_get_register_post_type') ? iss_graph_get_register_post_type() : 'register_place')) {
        foreach (['owner', 'operator', 'developer', 'tenant', 'current_status', 'current_use_type', 'previous_use', 'history_long'] as $meta_key) {
            $parts[] = get_post_meta($post->ID, $meta_key, true);
        }
    }

    if ($post->post_type === (function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt')) {
        $projection = function_exists('iss_graph_get_archive_object_projection')
            ? iss_graph_get_archive_object_projection((int) $post->ID)
            : null;

        if (is_array($projection)) {
            foreach ([
                'object_key',
                'title',
                'summary',
                'description',
                'source_system',
                'source_id',
                'inventory_number',
                'object_type_label',
                'creator_label',
                'material',
                'dimensions',
                'rights_holder',
                'institution_name',
                'year_label',
                'decade_label',
            ] as $field) {
                $parts[] = (string) ($projection[$field] ?? '');
            }
        }

        $relation_projection = function_exists('iss_graph_get_archive_object_relation_projection')
            ? iss_graph_get_archive_object_relation_projection((int) $post->ID)
            : [];

        $parts = array_merge($parts, iss_graph_collect_values_for_search_keys($relation_projection, [
            'name',
            'title',
            'label',
            'target_name',
            'relation_label',
            'event_type_name',
            'time_label',
            'place_name',
            'people_name',
            'note',
        ]));
    }

    $service = iss_graph_get_service();
    $entity = $service->find_entity_by_post(iss_graph_get_entity_kind_for_search_post($post), (int) $post->ID);

    if (!$entity && $post->post_type === (function_exists('iss_graph_get_register_post_type') ? iss_graph_get_register_post_type() : 'register_place') && function_exists('iss_graph_sync_register_place_entity')) {
        $entity = iss_graph_sync_register_place_entity((int) $post->ID);
    }

    if (!$entity && $post->post_type === (function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt') && function_exists('iss_graph_sync_archive_object_entity')) {
        $entity = iss_graph_sync_archive_object_entity((int) $post->ID);
    }

    if ($entity && !empty($entity['id'])) {
        $entity_id = (int) $entity['id'];

        foreach ($service->get_names_for_entity($entity_id) as $row) {
            if (is_array($row)) {
                $parts[] = (string) ($row['name'] ?? '');
            }
        }

        foreach ($service->get_identifiers_for_entity($entity_id, ['status' => 'accepted']) as $row) {
            if (is_array($row)) {
                $parts[] = (string) ($row['value'] ?? '');
                $parts[] = (string) ($row['label'] ?? '');
            }
        }

        foreach (['organization', 'person'] as $relation_family) {
            foreach ($service->get_relations_for_entity($entity_id, $relation_family, ['public_only' => true]) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $parts[] = (string) ($row['name'] ?? '');
                $parts[] = (string) ($row['relation_label'] ?? '');
            }
        }
    }

    if ($post->post_type === $profile_post_type && function_exists('iss_graph_get_profile_linked_entity')) {
        $linked_entity = iss_graph_get_profile_linked_entity((int) $post->ID);
        if ($linked_entity && !empty($linked_entity['id'])) {
            $parts[] = (string) ($linked_entity['display_title'] ?? '');
            $parts[] = function_exists('iss_graph_get_entity_kind_label')
                ? iss_graph_get_entity_kind_label((string) ($linked_entity['entity_kind'] ?? ''))
                : (string) ($linked_entity['entity_kind'] ?? '');

            foreach ($service->get_names_for_entity((int) $linked_entity['id']) as $row) {
                if (is_array($row)) {
                    $parts[] = (string) ($row['name'] ?? '');
                }
            }

            foreach ($service->get_identifiers_for_entity((int) $linked_entity['id'], ['status' => 'accepted']) as $row) {
                if (is_array($row)) {
                    $parts[] = (string) ($row['value'] ?? '');
                    $parts[] = (string) ($row['label'] ?? '');
                }
            }

            $facts = $service->get_entity_facts((string) ($linked_entity['entity_kind'] ?? ''), (int) $linked_entity['id']);
            if ($facts) {
                foreach (['summary', 'description', 'website', 'source_summary', 'person_kind', 'organization_kind', 'organization_status'] as $field) {
                    if (!empty($facts[$field]) && is_scalar($facts[$field])) {
                        $parts[] = (string) $facts[$field];
                    }
                }

                if (function_exists('iss_graph_format_fact_year_range')) {
                    $person_years = iss_graph_format_fact_year_range($facts['birth_year'] ?? null, $facts['death_year'] ?? null);
                    if ($person_years !== '') {
                        $parts[] = $person_years;
                    }

                    $organization_years = iss_graph_format_fact_year_range($facts['founded_year'] ?? null, $facts['dissolved_year'] ?? null, __('seit', 'iss-graph'), __('bis', 'iss-graph'));
                    if ($organization_years !== '') {
                        $parts[] = $organization_years;
                    }
                }
            }
        }
    }

    if (function_exists('iss_relations_get_related_place_items')) {
        foreach ((array) iss_relations_get_related_place_items((int) $post->ID) as $item) {
            if (!is_array($item)) {
                continue;
            }

            $parts[] = (string) ($item['title'] ?? '');
            $parts[] = (string) ($item['label'] ?? '');
        }
    }

    return $parts;
}

function iss_graph_sync_public_search_post(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        iss_graph_get_service()->delete_search_row_by_post($post_id);
        return null;
    }

    $config = iss_graph_get_public_search_post_type_config((string) $post->post_type);
    if (!$config || $post->post_status !== 'publish') {
        iss_graph_get_service()->delete_search_row_by_post($post_id);
        return null;
    }

    $service = iss_graph_get_service();
    $entity = $service->find_entity_by_post(iss_graph_get_entity_kind_for_search_post($post), (int) $post->ID);
    if (!$entity && $post->post_type === (function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt') && function_exists('iss_graph_sync_archive_object_entity')) {
        $entity = iss_graph_sync_archive_object_entity((int) $post->ID);
    }
    if (!$entity && $post->post_type === (function_exists('iss_graph_get_entity_profile_post_type') ? iss_graph_get_entity_profile_post_type() : 'entity_profile') && function_exists('iss_graph_get_profile_linked_entity')) {
        $entity = iss_graph_get_profile_linked_entity((int) $post->ID);
    }
    $search_text = iss_graph_join_search_text_parts(iss_graph_collect_public_search_text_parts($post));
    $title = iss_graph_normalize_search_text_part(get_the_title($post));
    $excerpt = iss_graph_normalize_search_text_part(get_post_field('post_excerpt', $post->ID));
    if ($excerpt === '' && $post->post_type === (function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt') && function_exists('iss_graph_get_archive_object_projection')) {
        $projection = iss_graph_get_archive_object_projection((int) $post->ID);
        if (is_array($projection)) {
            $excerpt = iss_graph_normalize_search_text_part((string) ($projection['summary'] ?? ''));
        }
    }

    return $service->upsert_search_row([
        'entity_id' => (int) ($entity['id'] ?? 0),
        'target_post_id' => (int) $post->ID,
        'target_post_type' => (string) $post->post_type,
        'search_bucket' => sanitize_key((string) ($config['bucket'] ?? '')),
        'title' => $title,
        'excerpt' => $excerpt,
        'search_text' => $search_text !== '' ? $search_text : $title,
        'boost' => (int) ($config['boost'] ?? 0),
        'is_public' => true,
    ]);
}

function iss_graph_cleanup_public_search_index(): void
{
    global $wpdb;

    $post_types = iss_graph_get_public_search_post_types();
    $table = iss_graph_get_service()->get_search_table_name();
    $placeholders = $post_types ? implode(', ', array_fill(0, count($post_types), '%s')) : '';

    if ($placeholders === '') {
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Projection table cleanup is owned by the graph service.
        $wpdb->query("TRUNCATE TABLE {$table}");
        return;
    }

    $sql = "DELETE i
        FROM {$table} i
        LEFT JOIN {$wpdb->posts} p
            ON p.ID = i.target_post_id
        WHERE p.ID IS NULL
           OR p.post_status <> 'publish'
           OR p.post_type NOT IN ({$placeholders})";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Projection table cleanup uses a dynamic owned table name.
    $wpdb->query($wpdb->prepare($sql, $post_types));
}

function iss_graph_backfill_public_search_index(): int
{
    $post_types = iss_graph_get_public_search_post_types();
    if (!$post_types) {
        return 0;
    }

    $post_ids = get_posts([
        'post_type' => $post_types,
        'post_status' => 'publish',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    $count = 0;
    foreach ($post_ids as $post_id) {
        if (iss_graph_sync_public_search_post((int) $post_id)) {
            $count++;
        }
    }

    iss_graph_cleanup_public_search_index();

    return $count;
}

function iss_graph_normalize_public_search_query(string $search): string
{
    $search = html_entity_decode($search, ENT_QUOTES, 'UTF-8');
    $search = wp_strip_all_tags($search);
    $search = trim(preg_replace('/\s+/u', ' ', $search));

    return is_string($search) ? $search : '';
}

function iss_graph_tokenize_public_search_query(string $search): array
{
    $search = iss_graph_normalize_public_search_query($search);
    if ($search === '') {
        return [];
    }

    $parts = preg_split('/[\s,.;:\/\-_]+/u', $search) ?: [];
    $tokens = [];

    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        if (function_exists('mb_strlen') && mb_strlen($part) < 2) {
            continue;
        }

        if (!function_exists('mb_strlen') && strlen($part) < 2) {
            continue;
        }

        $tokens[] = $part;
    }

    $tokens = array_values(array_unique($tokens));

    return $tokens ?: [$search];
}

function iss_graph_search_public_posts(string $search, array $args = []): array
{
    global $wpdb;

    $search = iss_graph_normalize_public_search_query($search);
    if ($search === '') {
        return [];
    }

    $tokens = iss_graph_tokenize_public_search_query($search);
    if (!$tokens) {
        return [];
    }

    $post_types = isset($args['post_types']) && is_array($args['post_types']) && $args['post_types']
        ? array_values(array_intersect(array_map('sanitize_key', $args['post_types']), iss_graph_get_public_search_post_types()))
        : iss_graph_get_public_search_post_types();

    if (!$post_types) {
        return [];
    }

    $limit = isset($args['limit']) ? max(1, min(1000, (int) $args['limit'])) : 1000;
    $table = iss_graph_get_service()->get_search_table_name();
    $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));

    $select_values = [$search];
    $phrase_like = '%' . $wpdb->esc_like($search) . '%';
    $select_values[] = $phrase_like;
    $select_values[] = $phrase_like;
    $select_values[] = $phrase_like;

    $score_sql = "i.boost
        + CASE WHEN i.title = %s THEN 5000 ELSE 0 END
        + CASE WHEN i.title LIKE %s THEN 1800 ELSE 0 END
        + CASE WHEN i.excerpt LIKE %s THEN 700 ELSE 0 END
        + CASE WHEN i.search_text LIKE %s THEN 500 ELSE 0 END";

    $token_where = [];
    $where_values = $post_types;

    foreach ($tokens as $token) {
        $like = '%' . $wpdb->esc_like($token) . '%';

        $score_sql .= "
        + CASE WHEN i.title LIKE %s THEN 180 ELSE 0 END
        + CASE WHEN i.excerpt LIKE %s THEN 90 ELSE 0 END
        + CASE WHEN i.search_text LIKE %s THEN 45 ELSE 0 END";
        $select_values[] = $like;
        $select_values[] = $like;
        $select_values[] = $like;

        $token_where[] = '(i.title LIKE %s OR i.excerpt LIKE %s OR i.search_text LIKE %s)';
        $where_values[] = $like;
        $where_values[] = $like;
        $where_values[] = $like;
    }

    $sql = "SELECT
            i.target_post_id AS post_id,
            i.target_post_type,
            i.search_bucket,
            i.title,
            i.excerpt,
            ({$score_sql}) AS relevance
        FROM {$table} i
        INNER JOIN {$wpdb->posts} p
            ON p.ID = i.target_post_id
        WHERE i.is_public = 1
          AND p.post_status = 'publish'
          AND p.post_type IN ({$type_placeholders})
          AND " . implode(' AND ', $token_where) . "
        ORDER BY relevance DESC, p.post_date_gmt DESC, i.target_post_id DESC
        LIMIT %d";

    $params = array_merge($select_values, $where_values, [$limit]);
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Central search reads the denormalized graph search projection.
    $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

    if (!is_array($rows)) {
        return [];
    }

    return array_values(array_map(static function (array $row): array {
        return [
            'post_id' => (int) ($row['post_id'] ?? 0),
            'post_type' => (string) ($row['target_post_type'] ?? ''),
            'search_bucket' => (string) ($row['search_bucket'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'excerpt' => (string) ($row['excerpt'] ?? ''),
            'relevance' => isset($row['relevance']) ? (int) $row['relevance'] : 0,
        ];
    }, $rows));
}

function iss_graph_search_public_post_ids(string $search, array $args = []): array
{
    $ids = array_map(static function (array $row): int {
        return (int) ($row['post_id'] ?? 0);
    }, iss_graph_search_public_posts($search, $args));

    return array_values(array_unique(array_filter($ids)));
}

function iss_graph_sync_public_search_on_save(int $post_id, WP_Post $post): void
{
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!iss_graph_get_public_search_post_type_config((string) $post->post_type)) {
        return;
    }

    iss_graph_sync_public_search_post($post_id);
}

function iss_graph_delete_public_search_on_before_delete(int $post_id): void
{
    iss_graph_get_service()->delete_search_row_by_post($post_id);
}

function iss_graph_sync_public_search_on_meta_change($meta_id, int $post_id, string $meta_key): void
{
    $post_type = get_post_type($post_id);
    if (!is_string($post_type) || !iss_graph_get_public_search_post_type_config($post_type)) {
        return;
    }

    if (!in_array($meta_key, iss_graph_get_search_index_meta_keys_for_post_type($post_type), true)) {
        return;
    }

    iss_graph_sync_public_search_post($post_id);
}

function iss_graph_intercept_public_search_query(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query() || !$query->is_search()) {
        return;
    }

    $search = iss_graph_normalize_public_search_query((string) $query->get('s'));
    $post_ids = function_exists('iss_search_public_post_ids')
        ? iss_search_public_post_ids($search, ['limit' => 1000])
        : iss_graph_search_public_post_ids($search, ['limit' => 1000]);

    $query->set('post_type', iss_graph_get_public_search_post_types());
    $query->set('post_status', 'publish');
    $query->set('ignore_sticky_posts', true);
    $query->set('orderby', 'post__in');
    $query->set('post__in', $post_ids ?: [0]);
    $query->set('iss_graph_public_search', true);
}

function iss_graph_disable_default_search_sql(string $search_sql, WP_Query $query): string
{
    if (!is_admin() && $query->get('iss_graph_public_search')) {
        return '';
    }

    return $search_sql;
}

add_action('save_post', 'iss_graph_sync_public_search_on_save', 60, 2);
add_action('before_delete_post', 'iss_graph_delete_public_search_on_before_delete', 20);
add_action('deleted_post', 'iss_graph_delete_public_search_on_before_delete', 20);
add_action('added_post_meta', 'iss_graph_sync_public_search_on_meta_change', 30, 3);
add_action('updated_post_meta', 'iss_graph_sync_public_search_on_meta_change', 30, 3);
add_action('deleted_post_meta', 'iss_graph_sync_public_search_on_meta_change', 30, 3);
add_action('pre_get_posts', 'iss_graph_intercept_public_search_query', 20);
add_filter('posts_search', 'iss_graph_disable_default_search_sql', 20, 2);
