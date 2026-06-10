<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- CLI verification reads counts from plugin-owned graph tables.

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-graph verify', 'iss_graph_wpcli_verify_command');
    WP_CLI::add_command('iss-graph drift-check', 'iss_graph_wpcli_drift_check_command');
    WP_CLI::add_command('iss-graph migrate', 'iss_graph_wpcli_migrate_command');
    WP_CLI::add_command('iss-graph sync-register', 'iss_graph_wpcli_sync_register_command');
    WP_CLI::add_command('iss-graph sync-content', 'iss_graph_wpcli_sync_content_command');
    WP_CLI::add_command('iss-graph sync-profiles', 'iss_graph_wpcli_sync_profiles_command');
    WP_CLI::add_command('iss-graph sync-video-transcripts', 'iss_graph_wpcli_sync_video_transcripts_command');
    WP_CLI::add_command('iss-graph sync-archive', 'iss_graph_wpcli_sync_archive_command');
    WP_CLI::add_command('iss-graph sync-aliases', 'iss_graph_wpcli_sync_aliases_command');
    WP_CLI::add_command('iss-graph sync-search', 'iss_graph_wpcli_sync_search_command');
    WP_CLI::add_command('iss-graph import-enrichment', 'iss_graph_wpcli_import_enrichment_command');
}

function iss_graph_wpcli_verify_command(array $args, array $assoc_args): void
{
    global $wpdb;

    $service = iss_graph_get_service();
    $tables = [
        'entity_index' => $service->get_entity_table_name(),
        'entity_names' => $service->get_name_table_name(),
        'entity_identifiers' => $service->get_identifier_table_name(),
        'entity_relations' => $service->get_relation_table_name(),
        'search_index' => $service->get_search_table_name(),
        'person_facts' => $service->get_person_facts_table_name(),
        'organization_facts' => $service->get_organization_facts_table_name(),
        'entity_evidence_refs' => $service->get_evidence_table_name(),
        'editorial_signals' => $service->get_editorial_signal_table_name(),
    ];

    $failed = false;
    foreach ($tables as $label => $table_name) {
        $exists = $service->table_exists($table_name);
        if ($exists) {
            WP_CLI::log(sprintf('[ok] %s %s', $label, $table_name));
            continue;
        }

        $failed = true;
        WP_CLI::warning(sprintf('%s missing: %s', $label, $table_name));
    }

    $entity_count = $service->table_exists($service->get_entity_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_entity_table_name()}")
        : 0;
    $name_count = $service->table_exists($service->get_name_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_name_table_name()}")
        : 0;
    $relation_count = $service->table_exists($service->get_relation_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_relation_table_name()}")
        : 0;
    $identifier_count = $service->table_exists($service->get_identifier_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_identifier_table_name()}")
        : 0;
    $search_count = $service->table_exists($service->get_search_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_search_table_name()}")
        : 0;
    $person_facts_count = $service->table_exists($service->get_person_facts_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_person_facts_table_name()}")
        : 0;
    $organization_facts_count = $service->table_exists($service->get_organization_facts_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_organization_facts_table_name()}")
        : 0;
    $evidence_count = $service->table_exists($service->get_evidence_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_evidence_table_name()}")
        : 0;
    $editorial_signal_count = $service->table_exists($service->get_editorial_signal_table_name())
        ? (int) $wpdb->get_var("SELECT COUNT(*) FROM {$service->get_editorial_signal_table_name()}")
        : 0;

    WP_CLI::log(sprintf(
        'entities=%d names=%d identifiers=%d relations=%d search=%d person_facts=%d organization_facts=%d evidence_refs=%d editorial_signals=%d',
        $entity_count,
        $name_count,
        $identifier_count,
        $relation_count,
        $search_count,
        $person_facts_count,
        $organization_facts_count,
        $evidence_count,
        $editorial_signal_count
    ));

    if ($failed) {
        WP_CLI::error('ISS graph verification failed.');
    }

    WP_CLI::success('ISS graph verification passed.');
}

function iss_graph_wpcli_drift_check_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(500, absint($assoc_args['limit'] ?? 50)));
    $checks = iss_graph_wpcli_parse_drift_checks((string) ($assoc_args['checks'] ?? ''));
    $errors = [];
    $stats = [];

    foreach ($checks as $check) {
        $result = iss_graph_wpcli_run_drift_check($check, $limit);
        $stats[$check] = (int) ($result['checked'] ?? 0);
        foreach ((array) ($result['errors'] ?? []) as $error) {
            if (is_string($error) && $error !== '') {
                $errors[] = $error;
            }
        }
    }

    foreach ($stats as $check => $checked) {
        WP_CLI::log(sprintf('[drift] %s checked=%d', $check, $checked));
    }

    if ($errors) {
        WP_CLI::error_multi_line(array_slice($errors, 0, $limit));
        $remaining = count($errors) - $limit;
        if ($remaining > 0) {
            WP_CLI::warning(sprintf('%d additional drift issues hidden by --limit=%d.', $remaining, $limit));
        }
        WP_CLI::error(sprintf('ISS graph drift check failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph drift check passed.');
}

function iss_graph_wpcli_parse_drift_checks(string $value): array
{
    $available = [
        'post-identifiers',
        'register-identifiers',
        'content-identifiers',
        'archive-identifiers',
        'place-taxonomy',
        'place-graph',
        'search-index',
        'editorial-signals',
    ];

    $value = trim($value);
    if ($value === '') {
        return $available;
    }

    $requested = array_values(array_filter(array_map('sanitize_key', preg_split('/[\s,]+/', $value) ?: [])));

    return array_values(array_intersect($available, $requested));
}

function iss_graph_wpcli_run_drift_check(string $check, int $limit): array
{
    switch ($check) {
        case 'post-identifiers':
            return iss_graph_wpcli_check_post_identifiers($limit);
        case 'register-identifiers':
            return iss_graph_wpcli_check_register_identifiers($limit);
        case 'content-identifiers':
            return iss_graph_wpcli_check_content_identifiers($limit);
        case 'archive-identifiers':
            return iss_graph_wpcli_check_archive_identifiers($limit);
        case 'place-taxonomy':
            return iss_graph_wpcli_check_place_taxonomy($limit);
        case 'place-graph':
            return iss_graph_wpcli_check_place_graph($limit);
        case 'search-index':
            return iss_graph_wpcli_check_search_index($limit);
        case 'editorial-signals':
            return iss_graph_wpcli_check_editorial_signals($limit);
        default:
            return [
                'checked' => 0,
                'errors' => [sprintf('Unknown drift check: %s', $check)],
            ];
    }
}

function iss_graph_wpcli_collect_post_ids(array $post_types, bool $require_relation_meta = false): array
{
    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
    if (!$post_types) {
        return [];
    }

    $args = [
        'post_type' => $post_types,
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids',
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => true,
        'update_post_term_cache' => false,
        'update_post_meta_cache' => false,
    ];

    if ($require_relation_meta && defined('ISS_RELATIONS_META_KEY')) {
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Drift verification must discover posts that use the relation source meta key.
        $args['meta_query'] = [
            [
                'key' => ISS_RELATIONS_META_KEY,
                'compare' => 'EXISTS',
            ],
        ];
    }

    return array_values(array_filter(array_map('absint', get_posts($args))));
}

function iss_graph_wpcli_entity_has_identifier(int $entity_id, string $namespace, $value): bool
{
    $identifier = iss_graph_get_service()->get_identifier_by_namespace_value($namespace, $value);

    return is_array($identifier)
        && (int) ($identifier['entity_id'] ?? 0) === $entity_id
        && (string) ($identifier['status'] ?? '') === 'accepted';
}

function iss_graph_wpcli_check_post_identifiers(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $rows = $wpdb->get_results(
        "SELECT id, entity_kind, post_id, profile_post_id, display_title
        FROM {$entity_table}
        WHERE post_id IS NOT NULL
           OR profile_post_id IS NOT NULL
        ORDER BY id ASC",
        ARRAY_A
    );

    $errors = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $entity_id = (int) ($row['id'] ?? 0);
        $post_id = absint($row['post_id'] ?? 0) ?: absint($row['profile_post_id'] ?? 0);
        if ($entity_id <= 0 || $post_id <= 0) {
            continue;
        }

        if (!iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
            $errors[] = sprintf('Entity %d (%s) is missing accepted wp_post identifier %d.', $entity_id, (string) ($row['display_title'] ?? ''), $post_id);
            if (count($errors) >= $limit) {
                break;
            }
        }
    }

    return [
        'checked' => is_array($rows) ? count($rows) : 0,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_register_identifiers(int $limit): array
{
    if (!function_exists('iss_graph_get_register_post_type')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_collect_post_ids([iss_graph_get_register_post_type()]);
    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            continue;
        }

        $checked++;
        $entity = $service->find_entity_by_post('place', $post_id);
        $entity_id = (int) ($entity['id'] ?? 0);
        if ($entity_id <= 0) {
            $errors[] = sprintf('Register place %d has no place entity.', $post_id);
            if (count($errors) >= $limit) {
                break;
            }
            continue;
        }

        if (!iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
            $errors[] = sprintf('Register place %d entity %d is missing wp_post identifier.', $post_id, $entity_id);
        }

        $register_id = trim((string) get_post_meta($post_id, 'register_id', true));
        if ($register_id !== '' && !iss_graph_wpcli_entity_has_identifier($entity_id, 'register_id', $register_id)) {
            $errors[] = sprintf('Register place %d entity %d is missing register_id identifier %s.', $post_id, $entity_id, $register_id);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_content_identifiers(int $limit): array
{
    if (!function_exists('iss_graph_get_content_relation_post_types')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_collect_post_ids(iss_graph_get_content_relation_post_types());
    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            continue;
        }

        $checked++;
        $entity_kind = sanitize_key((string) $post->post_type);
        $entity = $service->find_entity_by_post($entity_kind, $post_id);
        $entity_id = (int) ($entity['id'] ?? 0);
        if ($entity_id <= 0) {
            $errors[] = sprintf('Content post %d [%s] has no graph entity.', $post_id, $entity_kind);
        } elseif (!iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
            $errors[] = sprintf('Content post %d [%s] entity %d is missing wp_post identifier.', $post_id, $entity_kind, $entity_id);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_archive_identifiers(int $limit): array
{
    if (!function_exists('iss_graph_get_archive_object_post_type')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_collect_post_ids([iss_graph_get_archive_object_post_type()]);
    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || in_array($post->post_status, ['auto-draft', 'trash'], true)) {
            continue;
        }

        $checked++;
        $projection = function_exists('iss_graph_get_archive_object_projection')
            ? iss_graph_get_archive_object_projection($post_id)
            : null;
        $entity = $service->find_entity_by_post('archive_object', $post_id);
        $entity_id = (int) ($entity['id'] ?? 0);

        if ($entity_id <= 0) {
            $errors[] = sprintf('Archive object %d has no archive_object entity.', $post_id);
            if (count($errors) >= $limit) {
                break;
            }
            continue;
        }

        if (!iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
            $errors[] = sprintf('Archive object %d entity %d is missing wp_post identifier.', $post_id, $entity_id);
        }

        $object_key = trim((string) ($projection['object_key'] ?? ''));
        if ($object_key !== '' && !iss_graph_wpcli_entity_has_identifier($entity_id, 'archive_object_id', $object_key)) {
            $errors[] = sprintf('Archive object %d entity %d is missing archive_object_id identifier %s.', $post_id, $entity_id, $object_key);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_get_relation_post_ids_for_drift(bool $taxonomy_check): array
{
    if (!function_exists('iss_relations_get_supported_post_types')) {
        return [];
    }

    $types = iss_relations_get_supported_post_types();
    $place_type = function_exists('iss_relations_get_place_post_type') ? iss_relations_get_place_post_type() : 'register_place';
    $post_ids = [];

    if ($taxonomy_check && in_array($place_type, $types, true)) {
        $post_ids = array_merge($post_ids, iss_graph_wpcli_collect_post_ids([$place_type]));
    }

    $relation_types = array_values(array_diff($types, [$place_type]));
    if ($relation_types) {
        $post_ids = array_merge($post_ids, iss_graph_wpcli_collect_post_ids($relation_types, true));
    }

    return array_values(array_unique(array_filter(array_map('absint', $post_ids))));
}

function iss_graph_wpcli_relation_signature(array $relations): array
{
    $items = [];
    foreach (array_values($relations) as $relation) {
        if (!is_array($relation)) {
            continue;
        }

        $place_id = absint($relation['place_id'] ?? 0);
        if ($place_id <= 0) {
            continue;
        }

        $items[] = implode(':', [
            $place_id,
            sanitize_key((string) ($relation['role'] ?? ($relation['relation_type'] ?? 'related'))) ?: 'related',
            (int) ($relation['weight'] ?? 0),
            sanitize_text_field((string) ($relation['label'] ?? ($relation['relation_label'] ?? ''))),
        ]);
    }

    $items = array_values(array_unique($items));
    sort($items, SORT_STRING);

    return $items;
}

function iss_graph_wpcli_get_taxonomy_place_ids(int $post_id): array
{
    if (!defined('ISS_RELATIONS_TAXONOMY') || !taxonomy_exists(ISS_RELATIONS_TAXONOMY)) {
        return [];
    }

    $term_ids = wp_get_object_terms($post_id, ISS_RELATIONS_TAXONOMY, ['fields' => 'ids']);
    if (is_wp_error($term_ids) || !is_array($term_ids)) {
        return [];
    }

    $place_ids = [];
    foreach ($term_ids as $term_id) {
        $place_id = absint(get_term_meta((int) $term_id, 'place_post_id', true));
        if ($place_id > 0) {
            $place_ids[] = $place_id;
        }
    }

    $place_ids = array_values(array_unique(array_filter($place_ids)));
    sort($place_ids, SORT_NUMERIC);

    return $place_ids;
}

function iss_graph_wpcli_check_place_taxonomy(int $limit): array
{
    if (!function_exists('iss_relations_collect_taxonomy_place_ids_for_post')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_get_relation_post_ids_for_drift(true);
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $expected = array_values(array_unique(array_filter(array_map('absint', iss_relations_collect_taxonomy_place_ids_for_post($post_id)))));
        sort($expected, SORT_NUMERIC);
        $actual = iss_graph_wpcli_get_taxonomy_place_ids($post_id);
        $checked++;

        if ($expected !== $actual) {
            $errors[] = sprintf('Post %d iss_place_ref drift: expected [%s], actual [%s].', $post_id, implode(',', $expected), implode(',', $actual));
            if (count($errors) >= $limit) {
                break;
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_get_graph_place_signature(int $post_id): array
{
    if (!function_exists('iss_relations_graph_entity_kind_for_post')) {
        return [];
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return [];
    }

    $entity = iss_graph_get_service()->find_entity_by_post(iss_relations_graph_entity_kind_for_post($post), $post_id);
    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return [];
    }

    $signature_rows = [];
    foreach (iss_graph_get_service()->get_relations_for_entity($entity_id, 'place', [
        'source_system' => 'iss_relations_meta',
        'limit' => 500,
    ]) as $row) {
        if (!is_array($row)) {
            continue;
        }

        $place_id = absint($row['post_id'] ?? 0);
        if ($place_id <= 0) {
            continue;
        }

        $signature_rows[] = [
            'place_id' => $place_id,
            'role' => sanitize_key((string) ($row['relation_type'] ?? 'related')) ?: 'related',
            'weight' => (int) ($row['weight'] ?? 0),
            'label' => sanitize_text_field((string) ($row['relation_label'] ?? '')),
        ];
    }

    return iss_graph_wpcli_relation_signature($signature_rows);
}

function iss_graph_wpcli_check_place_graph(int $limit): array
{
    if (!function_exists('iss_relations_get_stored_post_relations')) {
        return ['checked' => 0, 'errors' => []];
    }

    $post_ids = iss_graph_wpcli_get_relation_post_ids_for_drift(false);
    $archive_post_type = function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt';
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post || $post->post_type === $archive_post_type) {
            continue;
        }

        $expected = iss_graph_wpcli_relation_signature(iss_relations_get_stored_post_relations($post_id));
        $actual = iss_graph_wpcli_get_graph_place_signature($post_id);
        $checked++;

        if ($expected !== $actual) {
            $errors[] = sprintf('Post %d graph place relation drift: expected [%s], actual [%s].', $post_id, implode(',', $expected), implode(',', $actual));
            if (count($errors) >= $limit) {
                break;
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_search_index(int $limit): array
{
    $post_types = function_exists('iss_graph_get_public_search_post_types') ? iss_graph_get_public_search_post_types() : [];
    $post_ids = iss_graph_wpcli_collect_post_ids($post_types);
    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;

    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post instanceof WP_Post) {
            continue;
        }

        $row = $service->get_search_row_by_post($post_id);
        $checked++;

        if ($post->post_status === 'publish') {
            if (!$row) {
                $errors[] = sprintf('Published search post %d [%s] has no search index row.', $post_id, (string) $post->post_type);
            }
        } elseif ($row) {
            $errors[] = sprintf('Non-published search post %d [%s:%s] still has search index row.', $post_id, (string) $post->post_type, (string) $post->post_status);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_editorial_signals(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $signal_table = $service->get_editorial_signal_table_name();
    if (!$service->table_exists($signal_table)) {
        return [
            'checked' => 0,
            'errors' => [sprintf('Editorial signal table missing: %s.', $signal_table)],
        ];
    }

    $rows = $wpdb->get_results(
        "SELECT s.id, s.context_post_id, s.target_post_id, s.surface, s.signal_type, s.status,
            cp.post_status AS context_status,
            tp.post_status AS target_status
        FROM {$signal_table} s
        LEFT JOIN {$wpdb->posts} cp ON cp.ID = s.context_post_id
        LEFT JOIN {$wpdb->posts} tp ON tp.ID = s.target_post_id
        ORDER BY s.id ASC",
        ARRAY_A
    );

    $errors = [];
    foreach (is_array($rows) ? $rows : [] as $row) {
        $id = (int) ($row['id'] ?? 0);
        $context_post_id = absint($row['context_post_id'] ?? 0);
        $target_post_id = absint($row['target_post_id'] ?? 0);
        $context_status = (string) ($row['context_status'] ?? '');
        $target_status = (string) ($row['target_status'] ?? '');

        if ($context_post_id <= 0 || $context_status === '') {
            $errors[] = sprintf('Editorial signal %d has a missing context post %d.', $id, $context_post_id);
        } elseif (in_array($context_status, ['auto-draft', 'trash'], true)) {
            $errors[] = sprintf('Editorial signal %d has invalid context post status %s for post %d.', $id, $context_status, $context_post_id);
        }

        if ($target_post_id <= 0 || $target_status === '') {
            $errors[] = sprintf('Editorial signal %d has a missing target post %d.', $id, $target_post_id);
        } elseif (in_array($target_status, ['auto-draft', 'trash'], true)) {
            $errors[] = sprintf('Editorial signal %d has invalid target post status %s for post %d.', $id, $target_status, $target_post_id);
        }

        $surface = $service->normalize_editorial_signal_surface((string) ($row['surface'] ?? ''));
        if ($surface !== (string) ($row['surface'] ?? '')) {
            $errors[] = sprintf('Editorial signal %d has invalid surface %s.', $id, (string) ($row['surface'] ?? ''));
        }

        $signal = $service->normalize_editorial_signal_type((string) ($row['signal_type'] ?? ''));
        if ($signal === '') {
            $errors[] = sprintf('Editorial signal %d has invalid signal type %s.', $id, (string) ($row['signal_type'] ?? ''));
        }

        $status = $service->normalize_editorial_signal_status((string) ($row['status'] ?? ''));
        if ($status !== (string) ($row['status'] ?? '')) {
            $errors[] = sprintf('Editorial signal %d has invalid status %s.', $id, (string) ($row['status'] ?? ''));
        }

        if (
            $context_post_id > 0
            && $target_post_id > 0
            && $context_post_id !== $target_post_id
            && function_exists('iss_graph_editorial_signal_target_is_allowed')
            && !iss_graph_editorial_signal_target_is_allowed($context_post_id, $target_post_id)
        ) {
            $errors[] = sprintf('Editorial signal %d targets post %d outside the allowed target types for context post %d.', $id, $target_post_id, $context_post_id);
        }

        if ($context_post_id > 0 && $target_post_id > 0 && $context_post_id === $target_post_id) {
            $post_type = get_post_type($target_post_id);
            if (!is_string($post_type) || !function_exists('iss_graph_is_related_promotion_post_type') || !iss_graph_is_related_promotion_post_type($post_type)) {
                $errors[] = sprintf('Editorial signal %d is a self-promotion on unsupported post %d.', $id, $target_post_id);
            }
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => is_array($rows) ? count($rows) : 0,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_log_migration_step(string $label, callable $callback, string $option = '', string $version = ''): void
{
    WP_CLI::log(sprintf('[migrate] %s', $label));
    $result = $callback();
    if ($option !== '' && $version !== '') {
        update_option($option, $version, false);
    }

    if (is_array($result)) {
        WP_CLI::log(sprintf('[migrate] %s result=%s', $label, wp_json_encode($result)));
        return;
    }

    WP_CLI::log(sprintf('[migrate] %s count=%d', $label, (int) $result));
}

function iss_graph_wpcli_migrate_command(array $args, array $assoc_args): void
{
    WP_CLI::log('[migrate] installing graph schema');
    iss_graph_get_service()->install_schema();
    iss_graph_ensure_editorial_signals_capability();

    if (!isset($assoc_args['skip-sync'])) {
        if (function_exists('iss_graph_backfill_register_places')) {
            iss_graph_wpcli_log_migration_step('register places', 'iss_graph_backfill_register_places', ISS_GRAPH_REGISTER_BACKFILL_OPTION, ISS_GRAPH_REGISTER_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_public_content_entities')) {
            iss_graph_wpcli_log_migration_step('public content entities', 'iss_graph_backfill_public_content_entities', ISS_GRAPH_CONTENT_BACKFILL_OPTION, ISS_GRAPH_CONTENT_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_archive_objects')) {
            iss_graph_wpcli_log_migration_step('archive objects', 'iss_graph_backfill_archive_objects', ISS_GRAPH_ARCHIVE_BACKFILL_OPTION, ISS_GRAPH_ARCHIVE_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_entity_profile_bindings')) {
            iss_graph_wpcli_log_migration_step('profile bindings', 'iss_graph_backfill_entity_profile_bindings', ISS_GRAPH_PROFILE_BACKFILL_OPTION, ISS_GRAPH_PROFILE_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_entity_aliases')) {
            iss_graph_wpcli_log_migration_step('entity aliases', 'iss_graph_backfill_entity_aliases', ISS_GRAPH_ALIAS_BACKFILL_OPTION, ISS_GRAPH_ALIAS_BACKFILL_VERSION);
        }

        if (function_exists('iss_graph_backfill_public_search_index')) {
            iss_graph_wpcli_log_migration_step('public search index', 'iss_graph_backfill_public_search_index', ISS_GRAPH_SEARCH_BACKFILL_OPTION, ISS_GRAPH_SEARCH_BACKFILL_VERSION);
        }

        if (isset($assoc_args['with-video-transcripts']) && function_exists('iss_graph_backfill_video_transcript_mentions')) {
            iss_graph_wpcli_log_migration_step('video transcript mentions', 'iss_graph_backfill_video_transcript_mentions');
        }
    }

    if (!isset($assoc_args['skip-drift'])) {
        iss_graph_wpcli_drift_check_command([], [
            'checks' => (string) ($assoc_args['checks'] ?? ''),
            'limit' => (int) ($assoc_args['limit'] ?? 50),
        ]);
    }

    WP_CLI::success('ISS graph migration completed.');
}

function iss_graph_wpcli_sync_register_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $entity = iss_graph_sync_register_place_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Register place %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced register place %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    $count = iss_graph_backfill_register_places();
    update_option(ISS_GRAPH_REGISTER_BACKFILL_OPTION, ISS_GRAPH_REGISTER_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d register places into the graph.', $count));
}

function iss_graph_wpcli_sync_content_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $entity = iss_graph_sync_public_content_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Public content post %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced public content post %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    $count = iss_graph_backfill_public_content_entities();
    update_option(ISS_GRAPH_CONTENT_BACKFILL_OPTION, ISS_GRAPH_CONTENT_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d public content posts into the graph.', $count));
}

function iss_graph_wpcli_sync_profiles_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_graph_backfill_entity_profile_bindings')) {
        WP_CLI::error('Entity profile bindings are not available.');
    }

    $count = iss_graph_backfill_entity_profile_bindings();
    update_option(ISS_GRAPH_PROFILE_BACKFILL_OPTION, ISS_GRAPH_PROFILE_BACKFILL_VERSION, false);

    WP_CLI::success(sprintf('Synced %d entity profile bindings.', $count));
}

function iss_graph_wpcli_sync_video_transcripts_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_graph_sync_video_transcript_mentions')) {
        WP_CLI::error('Video transcript bridge is not loaded.');
    }

    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $result = iss_graph_sync_video_transcript_mentions($post_id);

        if (empty($result['synced'])) {
            WP_CLI::error(sprintf('Video transcript mentions for post %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf(
            'Synced video transcript mentions for post %d: entity=%d status=%s segments=%d mentions=%d.',
            $post_id,
            (int) ($result['entity_id'] ?? 0),
            (string) ($result['status'] ?? ''),
            (int) ($result['segments'] ?? 0),
            (int) ($result['mentions'] ?? 0)
        ));
        return;
    }

    $stats = iss_graph_backfill_video_transcript_mentions();
    WP_CLI::success(sprintf(
        'Synced video transcript mentions: videos=%d synced=%d mentions=%d.',
        (int) ($stats['videos'] ?? 0),
        (int) ($stats['synced'] ?? 0),
        (int) ($stats['mentions'] ?? 0)
    ));
}

function iss_graph_wpcli_sync_archive_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $entity = iss_graph_sync_archive_object_entity($post_id);

        if (!$entity) {
            WP_CLI::error(sprintf('Archive object %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced archive object %d to graph entity %d.', $post_id, (int) ($entity['id'] ?? 0)));
        return;
    }

    $count = iss_graph_backfill_archive_objects();
    update_option(ISS_GRAPH_ARCHIVE_BACKFILL_OPTION, ISS_GRAPH_ARCHIVE_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d archive objects into the graph.', $count));
}

function iss_graph_wpcli_sync_aliases_command(array $args, array $assoc_args): void
{
    $entity_id = absint($assoc_args['entity_id'] ?? 0);

    if ($entity_id > 0) {
        $count = iss_graph_sync_entity_alias_backfill($entity_id);
        WP_CLI::success(sprintf('Synced %d generated alias name(s) for graph entity %d.', $count, $entity_id));
        return;
    }

    $stats = iss_graph_backfill_entity_aliases();
    update_option(ISS_GRAPH_ALIAS_BACKFILL_OPTION, ISS_GRAPH_ALIAS_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf(
        'Synced aliases for %d entities: with_aliases=%d names=%d.',
        (int) ($stats['entities'] ?? 0),
        (int) ($stats['with_aliases'] ?? 0),
        (int) ($stats['names'] ?? 0)
    ));
}

function iss_graph_wpcli_sync_search_command(array $args, array $assoc_args): void
{
    $post_id = absint($assoc_args['post_id'] ?? 0);

    if ($post_id > 0) {
        $row = iss_graph_sync_public_search_post($post_id);

        if (!$row) {
            WP_CLI::error(sprintf('Public search row for post %d could not be synced.', $post_id));
        }

        WP_CLI::success(sprintf('Synced public search row for post %d.', $post_id));
        return;
    }

    $count = iss_graph_backfill_public_search_index();
    update_option(ISS_GRAPH_SEARCH_BACKFILL_OPTION, ISS_GRAPH_SEARCH_BACKFILL_VERSION, false);
    WP_CLI::success(sprintf('Synced %d public search rows.', $count));
}

function iss_graph_wpcli_import_enrichment_command(array $args, array $assoc_args): void
{
    $source = sanitize_title((string) ($args[0] ?? ($assoc_args['source'] ?? '')));
    if ($source === '') {
        WP_CLI::error('Provide an enrichment source slug, for example: wp iss-graph import-enrichment wista-report-schoeneweide-2011-17');
    }

    $dataset = iss_graph_get_enrichment_dataset($source);
    if (!$dataset) {
        WP_CLI::error(sprintf('Enrichment dataset not found: %s', $source));
    }

    $result = iss_graph_import_enrichment_dataset($dataset, [
        'status' => $assoc_args['status'] ?? ($dataset['post_status'] ?? 'draft'),
        'profiles' => !isset($assoc_args['no-profiles']),
    ]);

    foreach ($result['profiles'] as $name => $post_id) {
        WP_CLI::log(sprintf('%s -> entity_profile %d', $name, (int) $post_id));
    }

    WP_CLI::success(sprintf(
        'Imported enrichment %s: entities=%d facts=%d profiles=%d',
        $source,
        count($result['entities']),
        count($result['facts']),
        count($result['profiles'])
    ));
}
