<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- Graph owner facade reads custom tables.

function iss_graph_get_service(): ISS_Graph_Service
{
    return ISS_Graph_Service::get_instance();
}

function iss_graph_get_entity_kind_for_post_type(string $post_type): string
{
    return function_exists('iss_graph_get_storage_entity_kind_for_post_type')
        ? iss_graph_get_storage_entity_kind_for_post_type($post_type)
        : sanitize_key($post_type);
}

function iss_graph_build_wp_post_identifier_rows(WP_Post $post): array
{
    $rows = [[
        'namespace' => 'wp_post',
        'value' => (string) $post->ID,
        'label' => 'WordPress post',
        'trust_level' => 'trusted_auto_link',
        'confidence' => 100,
        'is_primary' => true,
        'status' => 'accepted',
    ]];

    $slug = trim((string) $post->post_name);
    if ($slug !== '') {
        $rows[] = [
            'namespace' => 'legacy_slug',
            'value' => $post->post_type . ':' . $slug,
            'label' => 'WordPress slug',
            'trust_level' => 'trusted_review',
            'confidence' => 80,
            'status' => 'accepted',
        ];
    }

    return $rows;
}

function iss_graph_sync_wp_post_identifiers(int $entity_id, WP_Post $post, string $source_system = 'wp_post', array $extra_rows = []): void
{
    $entity_id = absint($entity_id);
    if ($entity_id <= 0) {
        return;
    }

    $rows = array_merge(iss_graph_build_wp_post_identifier_rows($post), $extra_rows);
    iss_graph_get_service()->replace_entity_identifiers_for_source(
        $entity_id,
        $source_system,
        $rows,
        'post:' . (int) $post->ID
    );
}

function iss_graph_get_entity_for_post(int $post_id): ?array
{
    return iss_graph_get_service()->find_entity_by_post_id(absint($post_id));
}

function iss_graph_get_or_create_entity_for_post(int $post_id, string $kind = ''): ?array
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return null;
    }

    $service = iss_graph_get_service();
    $kind = $service->normalize_entity_kind($kind);
    $existing = $kind !== ''
        ? $service->find_entity_by_post($kind, $post_id)
        : $service->find_entity_by_post_id($post_id);

    if ($existing) {
        return $existing;
    }

    if (function_exists('iss_graph_can_reconcile_current_request') && !iss_graph_can_reconcile_current_request()) {
        return null;
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
        return null;
    }

    $post_type = sanitize_key((string) $post->post_type);
    $register_post_type = defined('ISS_REGISTER_POST_TYPE') ? sanitize_key((string) ISS_REGISTER_POST_TYPE) : 'register_place';
    $archive_object_post_type = defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_OBJECT_POST_TYPE) : 'archivobjekt';

    if ($post_type === $register_post_type && function_exists('iss_graph_sync_register_place_entity')) {
        return iss_graph_sync_register_place_entity($post_id);
    }

    if ($post_type === $archive_object_post_type && function_exists('iss_graph_sync_archive_object_entity')) {
        return iss_graph_sync_archive_object_entity($post_id);
    }

    if (function_exists('iss_graph_is_content_relation_post_type') && iss_graph_is_content_relation_post_type($post_type)) {
        return iss_graph_sync_public_content_entity($post_id);
    }

    $entity_kind = $kind !== '' ? $kind : iss_graph_get_entity_kind_for_post_type($post_type);
    if ($entity_kind === '') {
        return null;
    }

    $entity = $service->upsert_entity([
        'entity_kind' => $entity_kind,
        'post_id' => $post_id,
        'source_system' => 'wp_post',
        'source_id' => (string) $post_id,
        'canonical_slug' => $post_type . '-' . $post_id . '-' . (trim((string) $post->post_name) !== '' ? (string) $post->post_name : sanitize_title((string) get_the_title($post))),
        'display_title' => get_the_title($post),
        'summary' => (string) get_post_field('post_excerpt', $post_id),
        'status' => sanitize_key((string) $post->post_status),
        'is_public' => $post->post_status === 'publish',
        'search_visibility' => $post->post_status === 'publish' ? 'public' : 'hidden',
    ]);

    if (!$entity || empty($entity['id'])) {
        return null;
    }

    $entity_id = (int) $entity['id'];
    $service->replace_entity_names_for_source($entity_id, 'wp_post_title', [[
        'name' => get_the_title($post) ?: ($post_type . ' ' . $post_id),
        'name_type' => 'primary',
        'is_primary' => true,
        'position' => 0,
    ]], 'post:' . $post_id);

    iss_graph_sync_wp_post_identifiers($entity_id, $post, 'wp_post');

    if (function_exists('iss_graph_sync_entity_alias_backfill')) {
        iss_graph_sync_entity_alias_backfill($entity_id);
    }

    return $service->get_entity_by_id($entity_id);
}

function iss_graph_get_entity_by_identifier(string $namespace, $value): ?array
{
    return iss_graph_get_service()->get_entity_by_identifier($namespace, $value);
}

function iss_graph_search_entities(array $args): array
{
    $query = (string) ($args['query'] ?? ($args['search'] ?? ''));

    return iss_graph_get_service()->search_entities($query, $args);
}

function iss_graph_get_entity_names(int $entity_id, array $args = []): array
{
    return iss_graph_get_service()->get_names_for_entity($entity_id, $args);
}

function iss_graph_get_entity_identifiers(int $entity_id, array $args = []): array
{
    return iss_graph_get_service()->get_identifiers_for_entity($entity_id, $args);
}

function iss_graph_get_entity_relations(int $entity_id, array $args = []): array
{
    $relation_family = sanitize_key((string) ($args['relation_family'] ?? ''));

    return iss_graph_get_service()->get_relations_for_entity($entity_id, $relation_family, $args);
}

function iss_graph_get_active_editorial_signals_for_context(int $context_entity_id, string $surface = 'related', array $args = []): array
{
    return iss_graph_get_service()->get_active_editorial_signals($context_entity_id, $surface, $args);
}

function iss_graph_get_active_editorial_signals_for_post(int $context_post_id, string $surface = 'related', array $args = []): array
{
    return iss_graph_get_service()->get_active_editorial_signals_for_post($context_post_id, $surface, $args);
}

function iss_graph_get_active_related_promotion_post_ids(array $post_types, array $args = []): array
{
    return iss_graph_get_service()->get_active_related_promotion_post_ids($post_types, $args);
}

function iss_graph_upsert_editorial_signal_for_post(int $context_post_id, int $target_post_id, string $signal, array $args = []): ?array
{
    $args['signal'] = $signal;

    return iss_graph_get_service()->upsert_editorial_signal_for_post($context_post_id, $target_post_id, $args);
}

function iss_graph_remove_editorial_signal_for_post(int $context_post_id, int $target_post_id, string $surface = 'related'): bool
{
    return iss_graph_get_service()->remove_editorial_signal_for_post($context_post_id, $target_post_id, $surface);
}

function iss_graph_mark_post_dirty(int $post_id, string $reason = '', string $source_system = 'iss_graph'): bool
{
    return iss_graph_get_service()->mark_post_dirty($post_id, $reason, $source_system);
}

function iss_graph_get_dirty_queue_stats(): array
{
    return iss_graph_get_service()->get_dirty_queue_stats();
}

function iss_graph_drain_dirty_queue(array $args = []): array
{
    return iss_graph_get_service()->drain_dirty_queue($args);
}

function iss_graph_backfill_relation_autonomy_columns(array $args = []): array
{
    return iss_graph_get_service()->backfill_relation_autonomy_columns($args);
}

function iss_graph_can_reconcile_current_request(): bool
{
    if (defined('WP_CLI') && WP_CLI) {
        return true;
    }

    if (function_exists('wp_doing_cron') && wp_doing_cron()) {
        return true;
    }

    if (is_admin()) {
        return true;
    }

    if (defined('REST_REQUEST') && REST_REQUEST) {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? ''));
        return $method !== 'GET' && $method !== 'HEAD';
    }

    return false;
}

function iss_graph_reconcile_post(int $post_id): array
{
    $previous = $GLOBALS['iss_graph_reconciling_post'] ?? 0;
    $GLOBALS['iss_graph_reconciling_post'] = $post_id;
    try {
        return iss_graph_reconcile_post_projections($post_id);
    } finally {
        $GLOBALS['iss_graph_reconciling_post'] = $previous;
    }
}

function iss_graph_reconcile_post_projections(int $post_id): array
{
    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return ['status' => 'dead', 'message' => 'invalid_post_id'];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return ['status' => 'clean', 'message' => 'post_missing'];
    }

    if (in_array((string) $post->post_status, ['auto-draft', 'trash'], true)) {
        return ['status' => 'clean', 'message' => 'post_not_publicly_reconcilable'];
    }

    $post_type = sanitize_key((string) $post->post_type);
    $register_post_type = function_exists('iss_graph_get_register_post_type')
        ? iss_graph_get_register_post_type()
        : (defined('ISS_REGISTER_POST_TYPE') ? sanitize_key((string) ISS_REGISTER_POST_TYPE) : 'register_place');
    $archive_post_type = defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_OBJECT_POST_TYPE) : 'archivobjekt';
    $synced = false;

    // The relations owner already delegates source-entity work to the correct
    // register/content/archive bridge. Do not repeat that bridge first.
    if (function_exists('iss_relations_is_supported_post_type') && iss_relations_is_supported_post_type($post_type)) {
        iss_relations_sync_post_terms($post_id);
        $synced = (bool) iss_relations_sync_post_graph($post_id);
    } elseif ($post_type === $register_post_type && function_exists('iss_graph_sync_register_place_entity')) {
        $synced = (bool) iss_graph_sync_register_place_entity($post_id);
    } elseif ($post_type === $archive_post_type && function_exists('iss_graph_sync_archive_object_entity')) {
        $synced = (bool) iss_graph_sync_archive_object_entity($post_id);
    } elseif (function_exists('iss_graph_is_content_relation_post_type') && iss_graph_is_content_relation_post_type($post_type)) {
        $synced = (bool) iss_graph_sync_public_content_entity($post_id);
    }

    if (function_exists('iss_graph_sync_public_search_post')) {
        iss_graph_sync_public_search_post($post_id);
    }

    return [
        'status' => 'clean',
        'message' => $synced ? 'reconciled' : 'no_graph_projection_required',
    ];
}

function iss_graph_post_type_affects_relatedness(string $post_type): bool
{
    $post_type = sanitize_key($post_type);
    if ($post_type === '') {
        return false;
    }

    $register_post_type = function_exists('iss_graph_get_register_post_type')
        ? iss_graph_get_register_post_type()
        : (defined('ISS_REGISTER_POST_TYPE') ? sanitize_key((string) ISS_REGISTER_POST_TYPE) : 'register_place');
    $archive_post_type = defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? sanitize_key((string) ISS_WF_IMPORT_OBJECT_POST_TYPE) : 'archivobjekt';

    if ($post_type === $register_post_type || $post_type === $archive_post_type) {
        return true;
    }

    if (function_exists('iss_graph_is_content_relation_post_type') && iss_graph_is_content_relation_post_type($post_type)) {
        return true;
    }

    return function_exists('iss_relations_is_supported_post_type') && iss_relations_is_supported_post_type($post_type);
}

function iss_graph_mark_related_post_dirty_on_save(int $post_id, WP_Post $post): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }

    if (!iss_graph_post_type_affects_relatedness((string) $post->post_type)) {
        return;
    }

    iss_graph_defer_post_projection($post_id, 'post_save', 'wp_post');
}
add_action('save_post', 'iss_graph_mark_related_post_dirty_on_save', 8, 2);

function iss_graph_replace_entity_projection(string $source_system, int $entity_id, array $payload): void
{
    iss_graph_get_service()->replace_entity_projection($entity_id, $source_system, $payload);
}

function iss_graph_map_resolver_candidate(array $entity, string $match_type, int $confidence, string $reason = '', array $extra = []): array
{
    return array_merge([
        'entity' => $entity,
        'entity_id' => (int) ($entity['id'] ?? 0),
        'entity_kind' => (string) ($entity['entity_kind'] ?? ''),
        'display_title' => (string) ($entity['display_title'] ?? ''),
        'match_type' => $match_type,
        'confidence' => max(0, min(100, $confidence)),
        'reason' => $reason,
    ], $extra);
}

function iss_graph_resolver_result(string $status, string $match_type, ?array $entity = null, array $candidates = [], int $confidence = 0, string $reason = ''): array
{
    return [
        'status' => $status,
        'match_type' => $match_type,
        'entity' => $entity,
        'entity_id' => $entity ? (int) ($entity['id'] ?? 0) : 0,
        'candidates' => $candidates,
        'confidence' => max(0, min(100, $confidence)),
        'reason' => $reason,
        'should_create' => $status === 'no_match',
    ];
}

function iss_graph_resolve_or_create_named_entity(string $entity_kind, string $name, array $overrides = [], array $args = []): ?array
{
    return iss_graph_get_service()->resolve_or_create_named_entity($entity_kind, $name, $overrides, $args);
}

function iss_graph_resolve_entity(array $args): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_kind = $service->normalize_entity_kind((string) ($args['entity_kind'] ?? ($args['kind'] ?? '')));
    $entity_id = absint($args['entity_id'] ?? 0);
    if ($entity_id > 0) {
        $entity = $service->get_entity_by_id($entity_id);
        if ($entity && ($entity_kind === '' || (string) ($entity['entity_kind'] ?? '') === $entity_kind)) {
            return iss_graph_resolver_result('matched', 'exact_entity_id_match', $entity, [], 100, 'Exact graph entity ID match.');
        }
    }

    $namespace = $service->normalize_identifier_namespace((string) ($args['namespace'] ?? ''));
    $identifier_value = $args['identifier'] ?? ($args['value'] ?? '');
    if ($namespace !== '' && is_scalar($identifier_value) && trim((string) $identifier_value) !== '') {
        $identifier = $service->get_identifier_by_namespace_value($namespace, $identifier_value);
        if ($identifier && (string) ($identifier['status'] ?? '') === 'accepted') {
            $entity = $service->get_entity_by_id((int) ($identifier['entity_id'] ?? 0));
            if ($entity && ($entity_kind === '' || (string) ($entity['entity_kind'] ?? '') === $entity_kind)) {
                $trust_level = (string) ($identifier['trust_level'] ?? 'suggest_only');
                if ($trust_level === 'trusted_auto_link') {
                    return iss_graph_resolver_result('matched', 'exact_trusted_identifier_match', $entity, [], (int) ($identifier['confidence'] ?? 100), 'Exact trusted identifier match.');
                }

                $match_type = $trust_level === 'trusted_review' ? 'exact_trusted_review_identifier_match' : 'exact_suggested_identifier_match';
                return iss_graph_resolver_result('candidates', $match_type, null, [
                    iss_graph_map_resolver_candidate($entity, $match_type, (int) ($identifier['confidence'] ?? 80), 'Identifier requires review.', [
                        'identifier' => $identifier,
                    ]),
                ], (int) ($identifier['confidence'] ?? 80), 'Identifier match requires review.');
            }
        }
    }

    $post_id = absint($args['post_id'] ?? 0);
    if ($post_id > 0) {
        $entity = iss_graph_get_entity_for_post($post_id);
        if ($entity && ($entity_kind === '' || (string) ($entity['entity_kind'] ?? '') === $entity_kind)) {
            return iss_graph_resolver_result('matched', 'exact_post_match', $entity, [], 100, 'Exact WordPress post match.');
        }
    }

    $label = sanitize_text_field((string) ($args['label'] ?? ($args['raw_label'] ?? '')));
    if ($label !== '') {
        $queries = $wpdb->num_queries;
        $exact_rows = $service->find_entities_by_normalized_name($label, [
            'entity_kind' => $entity_kind,
            'limit' => 10,
        ]);
        if ($wpdb->num_queries > $queries && $wpdb->last_error !== '') {
            throw new RuntimeException('Graph entity resolution could not read matching names.');
        }

        if ($exact_rows) {
            $candidates = array_map(static function (array $row): array {
                $name_type = sanitize_key((string) ($row['matched_name_type'] ?? ''));
                $match_type = in_array($name_type, ['primary', 'canonical', 'official'], true)
                    ? 'exact_accepted_alias_match'
                    : 'historical_or_source_label_match';

                return iss_graph_map_resolver_candidate($row, $match_type, $match_type === 'exact_accepted_alias_match' ? 90 : 75, 'Exact normalized name match.', [
                    'matched_name' => (string) ($row['matched_name'] ?? ''),
                    'matched_name_type' => $name_type,
                    'matched_name_id' => (int) ($row['matched_name_id'] ?? 0),
                ]);
            }, $exact_rows);

            return iss_graph_resolver_result('candidates', $candidates[0]['match_type'] ?? 'exact_accepted_alias_match', null, $candidates, (int) ($candidates[0]['confidence'] ?? 85), 'Alias/name matches require review.');
        }

        $search_rows = $service->search_entities($label, [
            'entity_kind' => $entity_kind,
            'limit' => isset($args['limit']) ? (int) $args['limit'] : 10,
        ]);
        if ($wpdb->last_error !== '') {
            throw new RuntimeException('Graph entity resolution could not search candidates.');
        }

        if ($search_rows) {
            $candidates = array_map(static function (array $row): array {
                return iss_graph_map_resolver_candidate($row, 'fuzzy_candidate', 50, 'Search candidate.');
            }, $search_rows);

            return iss_graph_resolver_result('candidates', 'fuzzy_candidate', null, $candidates, 50, 'Search candidates require review.');
        }
    }

    return iss_graph_resolver_result('no_match', 'no_match', null, [], 0, 'No safe entity match found.');
}
