<?php

if (!defined('ABSPATH')) {
    exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- CLI verification reads counts from plugin-owned graph tables.

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('iss-graph verify', 'iss_graph_wpcli_verify_command');
    WP_CLI::add_command('iss-graph entity-hygiene-audit', 'iss_graph_wpcli_entity_hygiene_audit_command');
    WP_CLI::add_command('iss-graph drift-check', 'iss_graph_wpcli_drift_check_command');
    WP_CLI::add_command('iss-graph facade-check', 'iss_graph_wpcli_facade_check_command');
    WP_CLI::add_command('iss-graph facade-consumer-audit', 'iss_graph_wpcli_facade_consumer_audit_command');
    WP_CLI::add_command('iss-graph view-contract-audit', 'iss_graph_wpcli_view_contract_audit_command');
    WP_CLI::add_command('iss-graph facade-search-compare', 'iss_graph_wpcli_facade_search_compare_command');
    WP_CLI::add_command('iss-graph facade-occurrences-compare', 'iss_graph_wpcli_facade_occurrences_compare_command');
    WP_CLI::add_command('iss-graph facade-entity-occurrences-compare', 'iss_graph_wpcli_facade_entity_occurrences_compare_command');
    WP_CLI::add_command('iss-graph facade-entities-compare', 'iss_graph_wpcli_facade_entities_compare_command');
    WP_CLI::add_command('iss-graph facade-entity-relations-compare', 'iss_graph_wpcli_facade_entity_relations_compare_command');
    WP_CLI::add_command('iss-graph facade-availability-compare', 'iss_graph_wpcli_facade_availability_compare_command');
    WP_CLI::add_command('iss-graph facade-timeline-compare', 'iss_graph_wpcli_facade_timeline_compare_command');
    WP_CLI::add_command('iss-graph facade-tour-slots-compare', 'iss_graph_wpcli_facade_tour_slots_compare_command');
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

function iss_graph_wpcli_entity_hygiene_audit_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(500, absint($assoc_args['limit'] ?? 50)));
    $format = sanitize_key((string) ($assoc_args['format'] ?? 'table'));
    $terms = iss_graph_wpcli_entity_hygiene_terms((string) ($assoc_args['terms'] ?? ''));
    $duplicates = iss_graph_wpcli_entity_hygiene_duplicate_names($limit);
    $term_matches = iss_graph_wpcli_entity_hygiene_term_matches($terms, $limit);
    $review_flags = iss_graph_wpcli_entity_hygiene_review_flags($terms, $term_matches, $duplicates);

    if ($format === 'json') {
        WP_CLI::log(wp_json_encode([
            'terms' => $terms,
            'duplicate_normalized_names' => $duplicates,
            'term_matches' => $term_matches,
            'review_flags' => $review_flags,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        WP_CLI::success('ISS graph entity hygiene audit completed. No changes made.');

        return;
    }

    WP_CLI::log('Duplicate normalized names');
    if ($duplicates) {
        iss_graph_wpcli_entity_hygiene_print_table(
            $duplicates,
            ['normalized_name', 'entity_count', 'name_count', 'entities', 'names']
        );
    } else {
        WP_CLI::log('No duplicate normalized names found.');
    }

    WP_CLI::log('');
    WP_CLI::log('Focus term matches');
    if ($term_matches) {
        iss_graph_wpcli_entity_hygiene_print_table(
            $term_matches,
            ['term', 'entity_id', 'entity_kind', 'title', 'visibility', 'source', 'identifiers', 'names', 'review']
        );
    } else {
        WP_CLI::log('No focus term matches found.');
    }

    WP_CLI::log('');
    WP_CLI::log('Review flags');
    if ($review_flags) {
        iss_graph_wpcli_entity_hygiene_print_table(
            $review_flags,
            ['issue', 'term', 'expected', 'entity_id', 'actual', 'title', 'detail']
        );
    } else {
        WP_CLI::log('No review flags found.');
    }

    WP_CLI::success(sprintf(
        'ISS graph entity hygiene audit completed: duplicate_names=%d term_matches=%d review_flags=%d. No changes made.',
        count($duplicates),
        count($term_matches),
        count($review_flags)
    ));
}

function iss_graph_wpcli_entity_hygiene_terms(string $value): array
{
    $raw_terms = trim($value) !== ''
        ? preg_split('/\s*,\s*/', $value) ?: []
        : ['Industriesalon Schöneweide', 'WF', 'KWO', 'TRO', 'AEG'];

    $terms = [];
    $seen = [];
    foreach ($raw_terms as $raw_term) {
        $term = sanitize_text_field(trim((string) $raw_term));
        $key = sanitize_title($term);
        if ($term === '' || $key === '' || isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        $terms[] = $term;
    }

    return $terms;
}

function iss_graph_wpcli_entity_hygiene_print_table(array $rows, array $fields): void
{
    WP_CLI::log(implode("\t", $fields));

    foreach ($rows as $row) {
        $values = [];
        foreach ($fields as $field) {
            $value = is_array($row) ? ($row[$field] ?? '') : '';
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            if (!is_scalar($value)) {
                $value = '';
            }

            $values[] = str_replace(["\r", "\n", "\t"], ' ', (string) $value);
        }

        WP_CLI::log(implode("\t", $values));
    }
}

function iss_graph_wpcli_entity_hygiene_expected_kinds(string $term): array
{
    $expected = [
        'industriesalon-schoneweide' => ['organization'],
        'industriesalon-schoeneweide' => ['organization'],
        'wf' => ['organization'],
        'kwo' => ['organization'],
        'tro' => ['organization'],
        'aeg' => ['organization'],
    ];
    $normalized = sanitize_title($term);

    return $expected[$normalized] ?? [];
}

function iss_graph_wpcli_entity_hygiene_duplicate_names(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $name_table = $service->get_name_table_name();
    $entity_table = $service->get_entity_table_name();
    if (!$service->table_exists($name_table) || !$service->table_exists($entity_table)) {
        return [];
    }

    $groups = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT normalized_name,
                COUNT(DISTINCT entity_id) AS entity_count,
                COUNT(*) AS name_count
            FROM {$name_table}
            WHERE normalized_name <> ''
            GROUP BY normalized_name
            HAVING entity_count > 1
            ORDER BY entity_count DESC, normalized_name ASC
            LIMIT %d",
            $limit
        ),
        ARRAY_A
    );
    if (!is_array($groups)) {
        return [];
    }

    $rows = [];
    foreach ($groups as $group) {
        $normalized_name = (string) ($group['normalized_name'] ?? '');
        if ($normalized_name === '') {
            continue;
        }

        $matches = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.id,
                    e.entity_kind,
                    e.display_title,
                    e.source_system,
                    e.source_id,
                    e.post_id,
                    e.profile_post_id,
                    e.is_public,
                    n.id AS name_id,
                    n.name,
                    n.name_type,
                    n.is_primary,
                    n.source_system AS name_source_system,
                    n.source_ref AS name_source_ref
                FROM {$name_table} n
                INNER JOIN {$entity_table} e
                    ON e.id = n.entity_id
                WHERE n.normalized_name = %s
                ORDER BY n.is_primary DESC, e.entity_kind ASC, e.display_title ASC, e.id ASC, n.position ASC, n.id ASC
                LIMIT 200",
                $normalized_name
            ),
            ARRAY_A
        );

        $entities = [];
        $names = [];
        foreach (is_array($matches) ? $matches : [] as $match) {
            $entity_id = (int) ($match['id'] ?? 0);
            if ($entity_id <= 0) {
                continue;
            }

            $entities[$entity_id] = iss_graph_wpcli_entity_hygiene_entity_label($match);
            $names[] = iss_graph_wpcli_entity_hygiene_name_label($match);
        }

        $rows[] = [
            'normalized_name' => $normalized_name,
            'entity_count' => (int) ($group['entity_count'] ?? 0),
            'name_count' => (int) ($group['name_count'] ?? 0),
            'entities' => iss_graph_wpcli_entity_hygiene_clip(implode(' | ', array_values($entities)), 260),
            'names' => iss_graph_wpcli_entity_hygiene_clip(implode(' | ', array_values(array_unique($names))), 260),
        ];
    }

    return $rows;
}

function iss_graph_wpcli_entity_hygiene_term_matches(array $terms, int $limit): array
{
    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $name_table = $service->get_name_table_name();
    $identifier_table = $service->get_identifier_table_name();
    if (!$service->table_exists($entity_table) || !$service->table_exists($name_table) || !$service->table_exists($identifier_table)) {
        return [];
    }

    $rows = [];
    foreach ($terms as $term) {
        $term = sanitize_text_field((string) $term);
        $normalized = sanitize_title($term);
        if ($term === '' || $normalized === '') {
            continue;
        }

        $entities = [];
        foreach (iss_graph_wpcli_entity_hygiene_query_term_entities($term, false, 500) as $entity) {
            $entity['_match_rank'] = 0;
            $entities[(int) ($entity['id'] ?? 0)] = $entity;
        }
        if (iss_graph_wpcli_entity_hygiene_allows_contains_match($normalized)) {
            foreach (iss_graph_wpcli_entity_hygiene_query_term_entities($term, true, 500) as $entity) {
                $entity_id = (int) ($entity['id'] ?? 0);
                if ($entity_id <= 0 || isset($entities[$entity_id])) {
                    continue;
                }

                $entity['_match_rank'] = 1;
                $entities[$entity_id] = $entity;
            }
        }

        $expected_kinds = iss_graph_wpcli_entity_hygiene_expected_kinds($term);
        $entities = iss_graph_wpcli_entity_hygiene_sort_term_entities(array_values($entities), $expected_kinds);
        $entities = array_slice($entities, 0, $limit);

        foreach (is_array($entities) ? $entities : [] as $entity) {
            $review = [];
            $entity_kind = (string) ($entity['entity_kind'] ?? '');
            if ($expected_kinds && !in_array($entity_kind, $expected_kinds, true)) {
                $review[] = 'kind';
            }

            $rows[] = [
                'term' => $term,
                'entity_id' => (int) ($entity['id'] ?? 0),
                'entity_kind' => $entity_kind,
                'title' => iss_graph_wpcli_entity_hygiene_clip((string) ($entity['display_title'] ?? ''), 110),
                'visibility' => !empty($entity['is_public']) ? 'public' : 'hidden',
                'source' => iss_graph_wpcli_entity_hygiene_source_label($entity),
                'identifiers' => iss_graph_wpcli_entity_hygiene_identifier_summary((int) ($entity['id'] ?? 0), 5),
                'names' => iss_graph_wpcli_entity_hygiene_name_summary((int) ($entity['id'] ?? 0), 5),
                'review' => implode(',', $review),
            ];
        }
    }

    return $rows;
}

function iss_graph_wpcli_entity_hygiene_query_term_entities(string $term, bool $contains, int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $name_table = $service->get_name_table_name();
    $identifier_table = $service->get_identifier_table_name();
    $normalized = sanitize_title($term);
    $identifier_normalized = $service->normalize_identifier_lookup_value($term);
    $limit = max(1, min(500, $limit));

    if ($contains) {
        $like = '%' . $wpdb->esc_like($term) . '%';
        $normalized_like = '%' . $wpdb->esc_like($normalized) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT DISTINCT e.*
                FROM {$entity_table} e
                WHERE e.display_title LIKE %s
                   OR e.canonical_slug LIKE %s
                   OR e.source_id LIKE %s
                   OR EXISTS (
                        SELECT 1
                        FROM {$name_table} n
                        WHERE n.entity_id = e.id
                          AND (n.name LIKE %s OR n.normalized_name LIKE %s)
                    )
                   OR EXISTS (
                        SELECT 1
                        FROM {$identifier_table} i
                        WHERE i.entity_id = e.id
                          AND i.status = 'accepted'
                          AND (i.value LIKE %s OR i.normalized_value LIKE %s)
                    )
                ORDER BY e.entity_kind ASC, e.display_title ASC, e.id ASC
                LIMIT %d",
                $like,
                $normalized_like,
                $like,
                $like,
                $normalized_like,
                $like,
                $normalized_like,
                $limit
            ),
            ARRAY_A
        );

        return is_array($rows) ? array_values($rows) : [];
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT e.*
            FROM {$entity_table} e
            WHERE e.display_title = %s
               OR e.canonical_slug = %s
               OR e.source_id = %s
               OR EXISTS (
                    SELECT 1
                    FROM {$name_table} n
                    WHERE n.entity_id = e.id
                      AND (n.name = %s OR n.normalized_name = %s)
                )
               OR EXISTS (
                    SELECT 1
                    FROM {$identifier_table} i
                    WHERE i.entity_id = e.id
                      AND i.status = 'accepted'
                      AND (i.value = %s OR i.normalized_value = %s)
                )
            ORDER BY e.entity_kind ASC, e.display_title ASC, e.id ASC
            LIMIT %d",
            $term,
            $normalized,
            $term,
            $term,
            $normalized,
            $term,
            $identifier_normalized,
            $limit
        ),
        ARRAY_A
    );

    return is_array($rows) ? array_values($rows) : [];
}

function iss_graph_wpcli_entity_hygiene_allows_contains_match(string $normalized): bool
{
    return strlen($normalized) > 3;
}

function iss_graph_wpcli_entity_hygiene_sort_term_entities(array $entities, array $expected_kinds): array
{
    usort($entities, static function (array $a, array $b) use ($expected_kinds): int {
        $a_expected = $expected_kinds && in_array((string) ($a['entity_kind'] ?? ''), $expected_kinds, true) ? 0 : 1;
        $b_expected = $expected_kinds && in_array((string) ($b['entity_kind'] ?? ''), $expected_kinds, true) ? 0 : 1;
        if ($a_expected !== $b_expected) {
            return $a_expected <=> $b_expected;
        }

        $a_rank = (int) ($a['_match_rank'] ?? 1);
        $b_rank = (int) ($b['_match_rank'] ?? 1);
        if ($a_rank !== $b_rank) {
            return $a_rank <=> $b_rank;
        }

        $a_public = !empty($a['is_public']) ? 0 : 1;
        $b_public = !empty($b['is_public']) ? 0 : 1;
        if ($a_public !== $b_public) {
            return $a_public <=> $b_public;
        }

        $a_weight = (int) ($a['search_weight'] ?? 0);
        $b_weight = (int) ($b['search_weight'] ?? 0);
        if ($a_weight !== $b_weight) {
            return $b_weight <=> $a_weight;
        }

        $kind_compare = strnatcasecmp((string) ($a['entity_kind'] ?? ''), (string) ($b['entity_kind'] ?? ''));
        if ($kind_compare !== 0) {
            return $kind_compare;
        }

        $title_compare = strnatcasecmp((string) ($a['display_title'] ?? ''), (string) ($b['display_title'] ?? ''));
        if ($title_compare !== 0) {
            return $title_compare;
        }

        return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
    });

    return $entities;
}

function iss_graph_wpcli_entity_hygiene_review_flags(array $terms, array $term_matches, array $duplicates): array
{
    $duplicate_names = [];
    foreach ($duplicates as $duplicate) {
        $normalized_name = (string) ($duplicate['normalized_name'] ?? '');
        if ($normalized_name !== '') {
            $duplicate_names[$normalized_name] = $duplicate;
        }
    }
    $focus_duplicates = iss_graph_wpcli_entity_hygiene_duplicate_lookup($terms);

    $matches_by_term = [];
    foreach ($term_matches as $match) {
        $term = (string) ($match['term'] ?? '');
        if ($term === '') {
            continue;
        }

        $matches_by_term[$term][] = $match;
    }

    $flags = [];
    foreach ($terms as $term) {
        $expected_kinds = iss_graph_wpcli_entity_hygiene_expected_kinds($term);
        $expected_label = $expected_kinds ? implode(',', $expected_kinds) : '';
        $matches = $matches_by_term[$term] ?? [];
        if (!$matches) {
            $flags[] = [
                'issue' => 'no_match',
                'term' => $term,
                'expected' => $expected_label,
                'entity_id' => '',
                'actual' => '',
                'title' => '',
                'detail' => 'No graph entity matched this focus term.',
            ];
            continue;
        }

        $entity_ids = array_values(array_unique(array_map(static function (array $match): int {
            return (int) ($match['entity_id'] ?? 0);
        }, $matches)));
        if (count($entity_ids) > 1) {
            $flags[] = [
                'issue' => 'ambiguous_alias',
                'term' => $term,
                'expected' => $expected_label,
                'entity_id' => '',
                'actual' => '',
                'title' => '',
                'detail' => sprintf('%d entities matched this focus term.', count($entity_ids)),
            ];
        }

        $normalized = sanitize_title($term);
        $duplicate = $duplicate_names[$normalized] ?? $focus_duplicates[$normalized] ?? null;
        if (is_array($duplicate)) {
            $flags[] = [
                'issue' => 'duplicate_normalized_name',
                'term' => $term,
                'expected' => $expected_label,
                'entity_id' => '',
                'actual' => '',
                'title' => '',
                'detail' => sprintf(
                    '%d entities share normalized_name=%s.',
                    (int) ($duplicate['entity_count'] ?? 0),
                    $normalized
                ),
            ];
        }

        foreach ($matches as $match) {
            $actual = (string) ($match['entity_kind'] ?? '');
            if ($expected_kinds && !in_array($actual, $expected_kinds, true)) {
                $flags[] = [
                    'issue' => 'wrong_kind_candidate',
                    'term' => $term,
                    'expected' => $expected_label,
                    'entity_id' => (int) ($match['entity_id'] ?? 0),
                    'actual' => $actual,
                    'title' => (string) ($match['title'] ?? ''),
                    'detail' => 'Review whether the term should be an alias/evidence label instead of this entity name.',
                ];
            }
        }
    }

    return $flags;
}

function iss_graph_wpcli_entity_hygiene_duplicate_lookup(array $terms): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $name_table = $service->get_name_table_name();
    if (!$service->table_exists($name_table)) {
        return [];
    }

    $normalized_names = [];
    foreach ($terms as $term) {
        $normalized = sanitize_title((string) $term);
        if ($normalized !== '') {
            $normalized_names[$normalized] = $normalized;
        }
    }
    if (!$normalized_names) {
        return [];
    }

    $lookup = [];
    foreach (array_values($normalized_names) as $normalized_name) {
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT normalized_name,
                    COUNT(DISTINCT entity_id) AS entity_count,
                    COUNT(*) AS name_count
                FROM {$name_table}
                WHERE normalized_name = %s
                GROUP BY normalized_name
                HAVING entity_count > 1",
                $normalized_name
            ),
            ARRAY_A
        );
        if (!is_array($row)) {
            continue;
        }

        $normalized_name = (string) ($row['normalized_name'] ?? '');
        if ($normalized_name === '') {
            continue;
        }

        $lookup[$normalized_name] = [
            'normalized_name' => $normalized_name,
            'entity_count' => (int) ($row['entity_count'] ?? 0),
            'name_count' => (int) ($row['name_count'] ?? 0),
        ];
    }

    return $lookup;
}

function iss_graph_wpcli_entity_hygiene_entity_label(array $row): string
{
    return sprintf(
        '#%d %s "%s" [%s]',
        (int) ($row['id'] ?? 0),
        (string) ($row['entity_kind'] ?? ''),
        (string) ($row['display_title'] ?? ''),
        !empty($row['is_public']) ? 'public' : 'hidden'
    );
}

function iss_graph_wpcli_entity_hygiene_name_label(array $row): string
{
    $source = iss_graph_wpcli_entity_hygiene_source_label([
        'source_system' => (string) ($row['name_source_system'] ?? ''),
        'source_id' => (string) ($row['name_source_ref'] ?? ''),
    ]);
    $primary = !empty($row['is_primary']) ? '*' : '';

    return sprintf(
        '#%d %s%s "%s" [%s]',
        (int) ($row['name_id'] ?? 0),
        (string) ($row['name_type'] ?? 'name'),
        $primary,
        (string) ($row['name'] ?? ''),
        $source
    );
}

function iss_graph_wpcli_entity_hygiene_source_label(array $row): string
{
    $source_system = sanitize_key((string) ($row['source_system'] ?? ''));
    $source_id = sanitize_text_field((string) ($row['source_id'] ?? ''));

    if ($source_system === '' && $source_id === '') {
        return '';
    }

    if ($source_system === '') {
        return $source_id;
    }

    if ($source_id === '') {
        return $source_system;
    }

    return $source_system . ':' . $source_id;
}

function iss_graph_wpcli_entity_hygiene_name_summary(int $entity_id, int $limit): string
{
    $names = iss_graph_get_service()->get_names_for_entity($entity_id, ['limit' => $limit]);
    $labels = [];
    foreach ($names as $name) {
        $labels[] = iss_graph_wpcli_entity_hygiene_name_label([
            'name_id' => (int) ($name['id'] ?? 0),
            'name_type' => (string) ($name['name_type'] ?? ''),
            'is_primary' => !empty($name['is_primary']),
            'name' => (string) ($name['name'] ?? ''),
            'name_source_system' => (string) ($name['source_system'] ?? ''),
            'name_source_ref' => (string) ($name['source_ref'] ?? ''),
        ]);
    }

    return iss_graph_wpcli_entity_hygiene_clip(implode(' | ', $labels), 220);
}

function iss_graph_wpcli_entity_hygiene_identifier_summary(int $entity_id, int $limit): string
{
    $identifiers = iss_graph_get_service()->get_identifiers_for_entity($entity_id, [
        'status' => 'accepted',
        'limit' => $limit,
    ]);
    $labels = [];
    foreach ($identifiers as $identifier) {
        $source = iss_graph_wpcli_entity_hygiene_source_label([
            'source_system' => (string) ($identifier['source_system'] ?? ''),
            'source_id' => (string) ($identifier['source_ref'] ?? ''),
        ]);
        $labels[] = sprintf(
            '%s:%s [%s%s]',
            (string) ($identifier['namespace'] ?? ''),
            (string) ($identifier['value'] ?? ''),
            (string) ($identifier['trust_level'] ?? 'suggest_only'),
            $source !== '' ? '; ' . $source : ''
        );
    }

    return iss_graph_wpcli_entity_hygiene_clip(implode(' | ', $labels), 220);
}

function iss_graph_wpcli_entity_hygiene_clip(string $value, int $limit): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?: '');
    if ($limit <= 0 || strlen($value) <= $limit) {
        return $value;
    }

    $suffix = '...';
    $length = max(0, $limit - strlen($suffix));

    return (function_exists('mb_substr') ? mb_substr($value, 0, $length, 'UTF-8') : substr($value, 0, $length)) . $suffix;
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

function iss_graph_wpcli_facade_response_count(array $data): string
{
    if (isset($data['count'])) {
        return (string) (int) $data['count'];
    }

    if (isset($data['entity_kinds']) && is_array($data['entity_kinds'])) {
        return (string) count($data['entity_kinds']);
    }

    if (isset($data['item']) && is_array($data['item'])) {
        return '1';
    }

    return 'n/a';
}

function iss_graph_wpcli_facade_request(string $path, array $params, array &$errors, string $label = 'facade'): array
{
    $request = new WP_REST_Request('GET', $path);
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    $response = rest_do_request($request);
    if ($response->is_error()) {
        $error = $response->as_error();
        $errors[] = sprintf('%s returned REST error: %s', $path, $error ? $error->get_error_message() : 'unknown error');
        WP_CLI::log(sprintf('[%s] %s status=error count=n/a', $label, $path));

        return [];
    }

    $status = (int) $response->get_status();
    $data = $response->get_data();
    if ($status !== 200) {
        $errors[] = sprintf('%s returned status %d, expected 200.', $path, $status);
    }
    if (!is_array($data)) {
        $errors[] = sprintf('%s returned a non-object response.', $path);
        $data = [];
    }

    WP_CLI::log(sprintf('[%s] %s status=%d count=%s', $label, $path, $status, iss_graph_wpcli_facade_response_count($data)));

    return $data;
}

function iss_graph_wpcli_direct_rest_callback(string $context, callable $callback, string $path, array $params, array &$errors, string $label = 'direct'): array
{
    $request = new WP_REST_Request('GET', $path);
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    $raw_response = call_user_func($callback, $request);
    if (is_wp_error($raw_response)) {
        $errors[] = sprintf('%s returned error: %s', $context, $raw_response->get_error_message());
        WP_CLI::log(sprintf('[%s] %s status=error count=n/a', $label, $context));

        return [];
    }

    $response = rest_ensure_response($raw_response);
    $status = (int) $response->get_status();
    $data = $response->get_data();
    if ($status !== 200) {
        $errors[] = sprintf('%s returned status %d, expected 200.', $context, $status);
    }
    if (!is_array($data)) {
        $errors[] = sprintf('%s returned a non-object response.', $context);
        $data = [];
    }

    WP_CLI::log(sprintf('[%s] %s status=%d count=%s', $label, $context, $status, iss_graph_wpcli_facade_response_count($data)));

    return $data;
}

function iss_graph_wpcli_facade_require_keys(string $context, array $data, array $keys, array &$errors): void
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $data)) {
            $errors[] = sprintf('%s is missing key: %s.', $context, (string) $key);
        }
    }
}

function iss_graph_wpcli_facade_route_contract(): array
{
    return [
        'required_read' => [
            '/iss/v1/contract',
            '/iss/v1/entities',
            '/iss/v1/entities/(?P<id>\d+)/relations',
            '/iss/v1/entities/(?P<id>\d+)/occurrences',
            '/iss/v1/occurrences',
            '/iss/v1/search',
        ],
        'provider_read' => [
            '/iss/v1/timeline' => 'iss_timeline_rest_render_collection',
            '/iss/v1/availability' => 'iss_programm_rest_list_availability',
            '/iss/v1/tour-slots' => 'is_tours_get_slots',
        ],
        'allowed_write' => [
            '/is-tours/v1/book' => 'iss_payments_lite_create_tour_booking',
        ],
        'retired_read' => [
            '/iss-search/v1/search',
            '/iss-programm/v1/timeline',
            '/is-tours/v1/slots',
        ],
    ];
}

function iss_graph_wpcli_facade_route_scan_roots(): array
{
    $roots = [];
    $theme_root = get_stylesheet_directory();
    if (is_dir($theme_root)) {
        $roots[] = $theme_root;
    }

    $plugin_root = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : trailingslashit(WP_CONTENT_DIR) . 'plugins';
    foreach (glob(trailingslashit($plugin_root) . '*', GLOB_ONLYDIR) ?: [] as $plugin_dir) {
        $plugin_name = basename((string) $plugin_dir);
        if (
            str_starts_with($plugin_name, 'iss-')
            || str_starts_with($plugin_name, 'industriesalon-')
        ) {
            $roots[] = (string) $plugin_dir;
        }
    }

    return array_values(array_unique($roots));
}

function iss_graph_wpcli_facade_route_scan_sources(array $retired_routes, int $limit): array
{
    $errors = [];
    $checked = 0;
    $allowed_extensions = ['php', 'js', 'html', 'json'];
    $skip_directories = ['.git', 'node_modules', 'vendor'];
    $self = realpath(__FILE__);

    foreach (iss_graph_wpcli_facade_route_scan_roots() as $root) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
                static function (SplFileInfo $current) use ($skip_directories): bool {
                    return !$current->isDir() || !in_array($current->getFilename(), $skip_directories, true);
                }
            )
        );

        foreach ($iterator as $file_info) {
            if (!$file_info instanceof SplFileInfo || !$file_info->isFile()) {
                continue;
            }

            $extension = strtolower($file_info->getExtension());
            if (!in_array($extension, $allowed_extensions, true)) {
                continue;
            }

            $path = $file_info->getPathname();
            if ($self !== false && realpath($path) === $self) {
                continue;
            }

            $checked++;
            $contents = file_get_contents($path);
            if (!is_string($contents)) {
                continue;
            }

            foreach ($retired_routes as $route) {
                if (str_contains($contents, (string) $route)) {
                    $errors[] = sprintf('Retired read route consumer remains in %s: %s.', str_replace(ABSPATH, '', $path), (string) $route);
                    if (count($errors) >= $limit) {
                        break 3;
                    }
                }
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_normalize_route_haystack($value): string
{
    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            $parts[] = iss_graph_wpcli_normalize_route_haystack($item);
        }

        return implode("\n", array_filter($parts, static function (string $part): bool {
            return $part !== '';
        }));
    }

    if (!is_scalar($value)) {
        return '';
    }

    $haystack = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $haystack = str_replace('\/', '/', $haystack);

    return $haystack;
}

function iss_graph_wpcli_route_haystack_contains($value, string $route_fragment): bool
{
    $route_fragment = trim($route_fragment, '/');
    if ($route_fragment === '') {
        return false;
    }

    return str_contains(iss_graph_wpcli_normalize_route_haystack($value), $route_fragment);
}

function iss_graph_wpcli_get_inline_script_haystack(string $handle)
{
    $scripts = wp_scripts();
    if (!$scripts instanceof WP_Scripts || empty($scripts->registered[$handle])) {
        return '';
    }

    return [
        $scripts->get_data($handle, 'data'),
        $scripts->get_data($handle, 'before'),
        $scripts->get_data($handle, 'after'),
    ];
}

function iss_graph_wpcli_facade_consumer_contract(): array
{
    return [
        'header-search' => [
            'kind' => 'rendered_search_modal',
            'function' => 'industriesalon_render_search_modal',
            'route' => 'iss/v1/search',
        ],
        'timeline-query' => [
            'kind' => 'inline_script',
            'setup_function' => 'iss_programm_register_frontend_assets',
            'handle' => 'iss-timeline-query',
            'route' => 'iss/v1/timeline',
        ],
        'tour-slot-read' => [
            'kind' => 'inline_script',
            'setup_function' => 'iss_programm_register_frontend_assets',
            'handle' => 'is-tour-calendar',
            'route' => 'iss/v1/tour-slots',
        ],
        'tour-booking-write' => [
            'kind' => 'inline_script',
            'setup_function' => 'iss_programm_register_frontend_assets',
            'handle' => 'is-tour-calendar',
            'route' => 'is-tours/v1/book',
            'write' => true,
        ],
        'availability-browser-script' => [
            'kind' => 'inline_script',
            'setup_function' => 'iss_programm_register_frontend_assets',
            'handle' => 'iss-ausstellungen-browser',
            'route' => 'iss/v1/availability',
        ],
        'availability-browser-block' => [
            'kind' => 'source_literal',
            'file' => trailingslashit(defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : trailingslashit(WP_CONTENT_DIR) . 'plugins') . 'iss-frontend/modules/programme/includes/ausstellungen-browser.php',
            'route' => 'iss/v1/availability',
        ],
    ];
}

function iss_graph_wpcli_check_facade_consumer_contract(int $limit): array
{
    $errors = [];
    $checked = 0;
    $setup_functions = [];

    foreach (iss_graph_wpcli_facade_consumer_contract() as $consumer => $contract) {
        $consumer = (string) $consumer;
        $kind = sanitize_key((string) ($contract['kind'] ?? ''));
        $route = trim((string) ($contract['route'] ?? ''));
        if ($kind === '' || $route === '') {
            continue;
        }

        $setup_function = (string) ($contract['setup_function'] ?? '');
        if ($setup_function !== '' && function_exists($setup_function) && !isset($setup_functions[$setup_function])) {
            call_user_func($setup_function);
            $setup_functions[$setup_function] = true;
        }

        $haystack = '';
        if ($kind === 'rendered_search_modal') {
            $function = (string) ($contract['function'] ?? '');
            if ($function === '' || !function_exists($function)) {
                $errors[] = sprintf('Facade consumer %s render function is unavailable.', $consumer);
                continue;
            }

            ob_start();
            call_user_func($function);
            $haystack = ob_get_clean();
        } elseif ($kind === 'inline_script') {
            $handle = sanitize_key((string) ($contract['handle'] ?? ''));
            if ($handle === '') {
                $errors[] = sprintf('Facade consumer %s has no script handle.', $consumer);
                continue;
            }

            $haystack = iss_graph_wpcli_get_inline_script_haystack($handle);
        } elseif ($kind === 'source_literal') {
            $file = (string) ($contract['file'] ?? '');
            if ($file === '' || !is_readable($file)) {
                $errors[] = sprintf('Facade consumer %s source file is unavailable.', $consumer);
                continue;
            }

            $haystack = file_get_contents($file);
        } else {
            $errors[] = sprintf('Facade consumer %s has unknown check kind %s.', $consumer, $kind);
            continue;
        }

        $checked++;
        if (!iss_graph_wpcli_route_haystack_contains($haystack, $route)) {
            $errors[] = sprintf('Facade consumer %s does not expose expected route fragment %s.', $consumer, $route);
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_facade_consumer_audit_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(100, absint($assoc_args['limit'] ?? 25)));
    $result = iss_graph_wpcli_check_facade_consumer_contract($limit);
    $errors = (array) ($result['errors'] ?? []);

    WP_CLI::log(sprintf('[facade-consumer] checked=%d', (int) ($result['checked'] ?? 0)));
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade consumer audit failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph facade consumer audit passed.');
}

function iss_graph_wpcli_frontend_view_contract(): array
{
    $theme_root = get_stylesheet_directory();

    return [
        'calendar' => [
            'file' => trailingslashit($theme_root) . 'templates/page-kalender.html',
            'requires' => [
                'wp:industriesalon/timeline-query',
                '"allowedTypesList":["fuehrungen","veranstaltungen"]',
                '"externalTypeLinks":[{"label":"Ausstellungen","url":"/ausstellungen/"}]',
            ],
            'forbids' => [
                'wp:industriesalon/ausstellungen-browser',
            ],
        ],
        'front-page-programme' => [
            'file' => trailingslashit($theme_root) . 'templates/front-page.html',
            'requires' => [
                'wp:industriesalon/timeline-query',
                '"fixedItemTypesList":["fuehrungen","veranstaltungen"]',
            ],
            'forbids' => [
                'wp:industriesalon/ausstellungen-browser',
            ],
        ],
        'exhibitions' => [
            'file' => trailingslashit($theme_root) . 'templates/page-ausstellungen.html',
            'requires' => [
                'wp:industriesalon/ausstellungen-browser',
                '"defaultFilter":"aktuell"',
                '"defaultFilter":"archiv"',
            ],
            'forbids' => [
                'wp:industriesalon/timeline-query',
            ],
        ],
        'tours' => [
            'file' => trailingslashit($theme_root) . 'templates/page-fuehrungen.html',
            'requires' => [
                'wp:industriesalon/timeline-query',
                '"fixedItemTypesList":["fuehrungen"]',
                'wp:iss/tour-offer-catalog',
            ],
            'forbids' => [
                'wp:industriesalon/ausstellungen-browser',
            ],
        ],
        'events' => [
            'file' => trailingslashit($theme_root) . 'templates/page-veranstaltungen.html',
            'requires' => [
                'wp:industriesalon/timeline-query',
                '"defaultType":"veranstaltungen"',
                '"postTypesList":["veranstaltung"]',
            ],
            'forbids' => [
                'wp:industriesalon/ausstellungen-browser',
                '"postTypesList":["ausstellung"]',
            ],
        ],
    ];
}

function iss_graph_wpcli_check_frontend_view_contract(int $limit): array
{
    $errors = [];
    $checked = 0;

    foreach (iss_graph_wpcli_frontend_view_contract() as $view => $contract) {
        $view = (string) $view;
        $file = (string) ($contract['file'] ?? '');
        if ($file === '' || !is_readable($file)) {
            $errors[] = sprintf('Frontend view %s source file is unavailable.', $view);
            continue;
        }

        $contents = file_get_contents($file);
        if (!is_string($contents)) {
            $errors[] = sprintf('Frontend view %s source file could not be read.', $view);
            continue;
        }

        $contents = iss_graph_wpcli_normalize_route_haystack($contents);
        foreach ((array) ($contract['requires'] ?? []) as $needle) {
            $needle = (string) $needle;
            if ($needle === '') {
                continue;
            }

            $checked++;
            if (!str_contains($contents, $needle)) {
                $errors[] = sprintf('Frontend view %s is missing required source fragment: %s.', $view, $needle);
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }

        foreach ((array) ($contract['forbids'] ?? []) as $needle) {
            $needle = (string) $needle;
            if ($needle === '') {
                continue;
            }

            $checked++;
            if (str_contains($contents, $needle)) {
                $errors[] = sprintf('Frontend view %s contains forbidden source fragment: %s.', $view, $needle);
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_view_contract_audit_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(100, absint($assoc_args['limit'] ?? 25)));
    $result = iss_graph_wpcli_check_frontend_view_contract($limit);
    $errors = (array) ($result['errors'] ?? []);

    WP_CLI::log(sprintf('[view-contract] checked=%d', (int) ($result['checked'] ?? 0)));
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph view contract audit failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph view contract audit passed.');
}

function iss_graph_wpcli_facade_check_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(10, absint($assoc_args['limit'] ?? 2)));
    $search = iss_graph_normalize_public_search_query((string) ($assoc_args['search'] ?? 'salon'));
    if ($search === '') {
        $search = 'salon';
    }

    $errors = [];
    $contract = iss_graph_wpcli_facade_request('/iss/v1/contract', [], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/contract', $contract, ['namespace', 'version', 'read_only', 'providers', 'routes', 'entity_kinds', 'offer_subtypes'], $errors);
    if (($contract['namespace'] ?? '') !== 'iss/v1') {
        $errors[] = '/iss/v1/contract namespace is not iss/v1.';
    }
    if (empty($contract['read_only'])) {
        $errors[] = '/iss/v1/contract does not report read_only=true.';
    }
    if (empty($contract['entity_kinds']) || !is_array($contract['entity_kinds'])) {
        $errors[] = '/iss/v1/contract has no entity_kinds list.';
    }
    if (empty($contract['offer_subtypes']) || !is_array($contract['offer_subtypes'])) {
        $errors[] = '/iss/v1/contract has no offer_subtypes list.';
    }
    if (function_exists('iss_timeline_rest_render_collection')) {
        $routes = isset($contract['routes']) && is_array($contract['routes']) ? $contract['routes'] : [];
        if (!in_array('/iss/v1/timeline', $routes, true)) {
            $errors[] = '/iss/v1/contract does not advertise the timeline facade route.';
        }
    }
    if (function_exists('iss_programm_rest_list_availability')) {
        $routes = isset($contract['routes']) && is_array($contract['routes']) ? $contract['routes'] : [];
        if (!in_array('/iss/v1/availability', $routes, true)) {
            $errors[] = '/iss/v1/contract does not advertise the availability facade route.';
        }
    }
    if (function_exists('is_tours_get_slots')) {
        $routes = isset($contract['routes']) && is_array($contract['routes']) ? $contract['routes'] : [];
        if (!in_array('/iss/v1/tour-slots', $routes, true)) {
            $errors[] = '/iss/v1/contract does not advertise the tour slots facade route.';
        }
    }

    $entities = iss_graph_wpcli_facade_request('/iss/v1/entities', ['limit' => $limit], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/entities', $entities, ['count', 'items'], $errors);
    $entity_items = isset($entities['items']) && is_array($entities['items']) ? $entities['items'] : [];
    if (!$entity_items) {
        $errors[] = '/iss/v1/entities returned no public entity items to detail-check.';
    }

    $entity_id = 0;
    $first_entity = $entity_items[0] ?? null;
    if (is_array($first_entity)) {
        iss_graph_wpcli_facade_require_keys('/iss/v1/entities item', $first_entity, ['id', 'kind', 'storage_kind', 'contract_kind', 'subtype', 'contract', 'title', 'url'], $errors);
        if (isset($first_entity['contract']) && !is_array($first_entity['contract'])) {
            $errors[] = '/iss/v1/entities item contract is not an object.';
        }
        $entity_id = absint($first_entity['id'] ?? 0);
    }

    if ($entity_id > 0) {
        $detail_path = sprintf('/iss/v1/entities/%d', $entity_id);
        $detail = iss_graph_wpcli_facade_request($detail_path, [], $errors);
        iss_graph_wpcli_facade_require_keys($detail_path, $detail, ['item'], $errors);
        $item = isset($detail['item']) && is_array($detail['item']) ? $detail['item'] : [];
        iss_graph_wpcli_facade_require_keys($detail_path . ' item', $item, ['id', 'kind', 'contract_kind', 'subtype', 'contract', 'names', 'identifiers', 'relations'], $errors);
        if (isset($item['contract']) && !is_array($item['contract'])) {
            $errors[] = sprintf('%s item contract is not an object.', $detail_path);
        }
        foreach (['names', 'identifiers', 'relations'] as $list_key) {
            if (isset($item[$list_key]) && !is_array($item[$list_key])) {
                $errors[] = sprintf('%s item key %s is not a list.', $detail_path, $list_key);
            }
        }

        $relations_path = sprintf('/iss/v1/entities/%d/relations', $entity_id);
        $relations = iss_graph_wpcli_facade_request($relations_path, ['limit' => $limit], $errors);
        iss_graph_wpcli_facade_require_keys($relations_path, $relations, ['entity_id', 'filters', 'count', 'items'], $errors);
        if (isset($relations['filters']) && !is_array($relations['filters'])) {
            $errors[] = sprintf('%s filters is not an object.', $relations_path);
        }
        if (isset($relations['items']) && !is_array($relations['items'])) {
            $errors[] = sprintf('%s items is not a list.', $relations_path);
        }
        $relation_items = isset($relations['items']) && is_array($relations['items']) ? $relations['items'] : [];
        if (isset($relation_items[0]) && is_array($relation_items[0])) {
            iss_graph_wpcli_facade_require_keys(
                $relations_path . ' item',
                $relation_items[0],
                ['relation_id', 'direction', 'entity_id', 'kind', 'storage_kind', 'name', 'family', 'type', 'label', 'source'],
                $errors
            );
        }

        $entity_occurrences_path = sprintf('/iss/v1/entities/%d/occurrences', $entity_id);
        $entity_occurrences = iss_graph_wpcli_facade_request($entity_occurrences_path, ['limit' => $limit], $errors);
        iss_graph_wpcli_facade_require_keys($entity_occurrences_path, $entity_occurrences, ['entity_id', 'filters', 'count', 'items'], $errors);
        if ((int) ($entity_occurrences['entity_id'] ?? 0) !== $entity_id) {
            $errors[] = sprintf('%s entity_id does not match the route id.', $entity_occurrences_path);
        }
        if (isset($entity_occurrences['filters']) && !is_array($entity_occurrences['filters'])) {
            $errors[] = sprintf('%s filters is not an object.', $entity_occurrences_path);
        }
        if (isset($entity_occurrences['items']) && !is_array($entity_occurrences['items'])) {
            $errors[] = sprintf('%s items is not a list.', $entity_occurrences_path);
        }
    }

    $occurrences = iss_graph_wpcli_facade_request('/iss/v1/occurrences', ['limit' => $limit], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/occurrences', $occurrences, ['count', 'items'], $errors);
    $occurrence_items = isset($occurrences['items']) && is_array($occurrences['items']) ? $occurrences['items'] : [];
    if (isset($occurrence_items[0]) && is_array($occurrence_items[0])) {
        iss_graph_wpcli_facade_require_keys('/iss/v1/occurrences item', $occurrence_items[0], ['id', 'entity_id', 'kind', 'contract_kind', 'subtype', 'contract', 'title', 'starts_at', 'source', 'location', 'schema'], $errors);
        if (isset($occurrence_items[0]['contract']) && !is_array($occurrence_items[0]['contract'])) {
            $errors[] = '/iss/v1/occurrences item contract is not an object.';
        }
        if (isset($occurrence_items[0]['schema']) && !is_array($occurrence_items[0]['schema'])) {
            $errors[] = '/iss/v1/occurrences item schema is not an object.';
        }
    }

    $search_results = iss_graph_wpcli_facade_request('/iss/v1/search', ['q' => $search, 'limit' => $limit], $errors);
    iss_graph_wpcli_facade_require_keys('/iss/v1/search', $search_results, ['query', 'provider', 'count', 'items'], $errors);
    $search_items = isset($search_results['items']) && is_array($search_results['items']) ? $search_results['items'] : [];
    if (isset($search_items[0]) && is_array($search_items[0])) {
        iss_graph_wpcli_facade_require_keys('/iss/v1/search item', $search_items[0], ['id', 'result_kind', 'entity_id', 'type', 'contract_kind', 'subtype', 'contract', 'title', 'url'], $errors);
        if (isset($search_items[0]['contract']) && !is_array($search_items[0]['contract'])) {
            $errors[] = '/iss/v1/search item contract is not an object.';
        }
    }

    if (function_exists('iss_timeline_rest_render_collection')) {
        $timeline = iss_graph_wpcli_facade_request('/iss/v1/timeline', [
            'limit' => $limit,
            'filters' => [
                'time_mode' => 'upcoming',
            ],
        ], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/timeline', $timeline, ['html', 'count', 'batchCount', 'isEmpty', 'offset', 'nextOffset', 'hasMore'], $errors);
    }

    if (function_exists('iss_programm_rest_list_availability')) {
        $availability = iss_graph_wpcli_facade_request('/iss/v1/availability', [
            'filter' => 'aktuell',
            'limit' => $limit,
        ], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/availability', $availability, ['provider', 'kind', 'storage_kind', 'filters', 'count', 'items', 'html', 'is_empty'], $errors);
        if (isset($availability['filters']) && !is_array($availability['filters'])) {
            $errors[] = '/iss/v1/availability filters is not an object.';
        }
        if (isset($availability['items']) && !is_array($availability['items'])) {
            $errors[] = '/iss/v1/availability items is not a list.';
        }
        if (isset($availability['html']) && !is_string($availability['html'])) {
            $errors[] = '/iss/v1/availability html is not a string.';
        }
        $availability_items = isset($availability['items']) && is_array($availability['items']) ? $availability['items'] : [];
        if (isset($availability_items[0]) && is_array($availability_items[0])) {
            iss_graph_wpcli_facade_require_keys(
                '/iss/v1/availability item',
                $availability_items[0],
                ['id', 'entity_id', 'kind', 'storage_kind', 'title', 'url', 'type', 'availability', 'editorial', 'schema', 'source'],
                $errors
            );
            if (isset($availability_items[0]['schema']) && !is_array($availability_items[0]['schema'])) {
                $errors[] = '/iss/v1/availability item schema is not an object.';
            }
        }
    }

    if (function_exists('is_tours_get_slots')) {
        $tour_slots = iss_graph_wpcli_facade_request('/iss/v1/tour-slots', [], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/tour-slots', $tour_slots, ['source', 'slots'], $errors);
        if (isset($tour_slots['slots']) && !is_array($tour_slots['slots'])) {
            $errors[] = '/iss/v1/tour-slots slots is not a list.';
        }
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade check failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success('ISS graph facade check passed.');
}

function iss_graph_wpcli_facade_parse_search_queries($value): array
{
    $raw_queries = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['salon', 'schoeneweide', 'ausstellung'];

    $queries = [];
    foreach ((array) $raw_queries as $query) {
        $query = iss_graph_normalize_public_search_query((string) $query);
        if ($query !== '') {
            $queries[] = $query;
        }
    }

    return array_values(array_unique($queries));
}

function iss_graph_wpcli_facade_search_signature(array $data): array
{
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    return [
        'query' => (string) ($data['query'] ?? ''),
        'provider' => (string) ($data['provider'] ?? ''),
        'count' => (int) ($data['count'] ?? 0),
        'items' => array_values(array_map(static function (array $item): array {
            return [
                'id' => (int) ($item['id'] ?? 0),
                'result_kind' => (string) ($item['result_kind'] ?? ''),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'type' => (string) ($item['type'] ?? ''),
                'type_label' => (string) ($item['type_label'] ?? ''),
                'contract_kind' => (string) ($item['contract_kind'] ?? ''),
                'subtype' => (string) ($item['subtype'] ?? ''),
                'post_type' => (string) ($item['post_type'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'relevance' => (int) ($item['relevance'] ?? 0),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_search_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_search_rest_search')) {
        WP_CLI::error('Search REST service is unavailable.');
    }

    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $queries = iss_graph_wpcli_facade_parse_search_queries($assoc_args['queries'] ?? '');
    if (!$queries) {
        WP_CLI::error('Provide at least one search query through --queries or use the defaults.');
    }

    $errors = [];
    foreach ($queries as $query) {
        $params = [
            'q' => $query,
            'limit' => $limit,
        ];
        $legacy = iss_graph_wpcli_direct_rest_callback('search service', 'iss_search_rest_search', '/iss/v1/search', $params, $errors);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/search', $params, $errors, 'facade');
        $legacy_signature = iss_graph_wpcli_facade_search_signature($legacy);
        $facade_signature = iss_graph_wpcli_facade_search_signature($facade);

        if ($legacy_signature !== $facade_signature) {
            $errors[] = sprintf('Search facade mismatch for query "%s".', $query);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] search q="%s" provider=%s count=%d matched',
            $query,
            (string) ($facade_signature['provider'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade search comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade search comparison passed for %d querie(s).', count($queries)));
}

function iss_graph_wpcli_facade_parse_occurrence_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['upcoming', 'all', 'event'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_occurrence_params_for_scenario(string $scenario, int $limit): array
{
    $params = [
        'limit' => $limit,
    ];

    switch ($scenario) {
        case 'all':
            $params['time_mode'] = 'all';
            break;
        case 'event':
        case 'events':
        case 'veranstaltung':
        case 'veranstaltungen':
            $params['time_mode'] = 'all';
            $params['kind'] = 'event';
            break;
        case 'tour':
        case 'tours':
        case 'fuehrung':
        case 'fuehrungen':
            $params['time_mode'] = 'upcoming';
            $params['kind'] = 'tour';
            break;
        case 'exhibition':
        case 'exhibitions':
        case 'ausstellung':
        case 'ausstellungen':
            $params['time_mode'] = 'all';
            $params['kind'] = 'exhibition';
            break;
        case 'project':
        case 'projects':
        case 'projekt':
        case 'projekte':
            $params['time_mode'] = 'all';
            $params['kind'] = 'project';
            break;
        case 'upcoming':
        default:
            $params['time_mode'] = 'upcoming';
            break;
    }

    return $params;
}

function iss_graph_wpcli_facade_occurrence_direct_response(array $params): array
{
    if (!function_exists('iss_occurrences_query') || !function_exists('iss_facade_rest_occurrence_filters_from_request')) {
        return [];
    }

    $request = new WP_REST_Request('GET', '/iss/v1/occurrences');
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    $filters = iss_facade_rest_occurrence_filters_from_request($request);

    return iss_facade_rest_get_occurrences_response($filters);
}

function iss_graph_wpcli_facade_occurrence_signature(array $data): array
{
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];

    return [
        'filters' => [
            'limit' => (int) ($filters['limit'] ?? 0),
            'offset' => (int) ($filters['offset'] ?? 0),
            'order' => (string) ($filters['order'] ?? ''),
            'time_mode' => (string) ($filters['time_mode'] ?? ''),
        ],
        'count' => (int) ($data['count'] ?? 0),
        'items' => array_values(array_map(static function (array $item): array {
            $source = isset($item['source']) && is_array($item['source']) ? $item['source'] : [];
            $location = isset($item['location']) && is_array($item['location']) ? $item['location'] : [];
            $schema = isset($item['schema']) && is_array($item['schema']) ? $item['schema'] : [];

            return [
                'id' => (int) ($item['id'] ?? 0),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
                'contract_kind' => (string) ($item['contract_kind'] ?? ''),
                'subtype' => (string) ($item['subtype'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'starts_at' => (string) ($item['starts_at'] ?? ''),
                'ends_at' => (string) ($item['ends_at'] ?? ''),
                'date_source' => (string) ($item['date_source'] ?? ''),
                'series_key' => (string) ($item['series_key'] ?? ''),
                'booking_url' => (string) ($item['booking_url'] ?? ''),
                'availability_state' => (string) ($item['availability_state'] ?? ''),
                'capacity_available' => $item['capacity_available'] ?? null,
                'capacity_total' => $item['capacity_total'] ?? null,
                'tag' => (string) ($item['tag'] ?? ''),
                'source_post_id' => (int) ($source['post_id'] ?? 0),
                'source_post_type' => (string) ($source['post_type'] ?? ''),
                'source_url' => (string) ($source['url'] ?? ''),
                'location_entity_id' => (int) ($location['entity_id'] ?? 0),
                'location_label' => (string) ($location['label'] ?? ''),
                'schema_kind' => (string) ($schema['kind'] ?? ''),
                'schema_type' => (string) ($schema['type'] ?? ''),
                'emits_event_schema' => (bool) ($schema['emits_event_schema'] ?? false),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_occurrences_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_occurrences_query')) {
        WP_CLI::error('Occurrence query service is unavailable.');
    }

    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $scenarios = iss_graph_wpcli_facade_parse_occurrence_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one occurrence comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_occurrence_params_for_scenario($scenario, $limit);
        $direct = iss_graph_wpcli_facade_occurrence_direct_response($params);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/occurrences', $params, $errors, 'facade');
        $direct_signature = iss_graph_wpcli_facade_occurrence_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_occurrence_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Occurrence facade mismatch for scenario "%s".', $scenario);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] occurrences scenario="%s" time_mode=%s count=%d matched',
            $scenario,
            (string) ($facade_signature['filters']['time_mode'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade occurrence comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade occurrence comparison passed for %d scenario(s).', count($scenarios)));
}

function iss_graph_wpcli_facade_find_occurrence_entity_id(array $params): int
{
    $params['limit'] = 100;
    $direct = iss_graph_wpcli_facade_occurrence_direct_response($params);
    $items = isset($direct['items']) && is_array($direct['items']) ? $direct['items'] : [];

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $entity_id = absint($item['entity_id'] ?? 0);
        if ($entity_id <= 0) {
            continue;
        }

        $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
        if ($entity && iss_facade_rest_entity_is_public($entity)) {
            return $entity_id;
        }
    }

    return 0;
}

function iss_graph_wpcli_facade_entity_occurrence_direct_response(int $entity_id, array $params): array
{
    if (!function_exists('iss_occurrences_query') || !function_exists('iss_facade_rest_occurrence_filters_from_request')) {
        return [];
    }

    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return [];
    }

    $request = new WP_REST_Request('GET', sprintf('/iss/v1/entities/%d/occurrences', $entity_id));
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    $filters = iss_facade_rest_occurrence_filters_from_request($request);
    $filters['source_post_ids'] = function_exists('iss_facade_rest_entity_source_post_ids')
        ? (iss_facade_rest_entity_source_post_ids($entity_id) ?: [-1])
        : [-1];

    return iss_facade_rest_get_occurrences_response($filters, $entity_id);
}

function iss_graph_wpcli_facade_entity_occurrence_signature(array $data): array
{
    $signature = iss_graph_wpcli_facade_occurrence_signature($data);
    $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];
    $signature['entity_id'] = (int) ($data['entity_id'] ?? 0);
    $signature['filters']['entity_id'] = (int) ($filters['entity_id'] ?? 0);

    return $signature;
}

function iss_graph_wpcli_facade_entity_occurrences_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_occurrences_query')) {
        WP_CLI::error('Occurrence query service is unavailable.');
    }

    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $requested_entity_id = absint($assoc_args['entity_id'] ?? 0);
    $scenarios = iss_graph_wpcli_facade_parse_occurrence_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one entity-occurrence comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    $compared = 0;
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_occurrence_params_for_scenario($scenario, $limit);
        $entity_id = $requested_entity_id > 0
            ? $requested_entity_id
            : iss_graph_wpcli_facade_find_occurrence_entity_id($params);

        if ($entity_id <= 0) {
            WP_CLI::warning(sprintf('Skipping entity-occurrences scenario "%s"; no public occurrence-backed entity found.', $scenario));
            continue;
        }

        $path = sprintf('/iss/v1/entities/%d/occurrences', $entity_id);
        $direct = iss_graph_wpcli_facade_entity_occurrence_direct_response($entity_id, $params);
        $facade = iss_graph_wpcli_facade_request($path, $params, $errors, 'facade');
        $direct_signature = iss_graph_wpcli_facade_entity_occurrence_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_entity_occurrence_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Entity-occurrences facade mismatch for scenario "%s" entity %d.', $scenario, $entity_id);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] entity-occurrences scenario="%s" entity=%d time_mode=%s count=%d matched',
            $scenario,
            $entity_id,
            (string) ($facade_signature['filters']['time_mode'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
        $compared++;
    }

    if ($compared <= 0 && !$errors) {
        WP_CLI::error('No entity-occurrence comparison scenarios were run.');
    }
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade entity-occurrence comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade entity-occurrence comparison passed for %d scenario(s).', $compared));
}

function iss_graph_wpcli_facade_parse_availability_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['aktuell', 'dauer', 'digital', 'archiv', 'aktuell-search'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_availability_params_for_scenario(string $scenario, int $limit): array
{
    $search = '';
    if (substr($scenario, -7) === '-search') {
        $scenario = substr($scenario, 0, -7);
        $search = 'Werk';
    }

    $filter = function_exists('iss_programm_ausstellungen_normalize_filter')
        ? iss_programm_ausstellungen_normalize_filter($scenario)
        : sanitize_key($scenario);

    $params = [
        'kind' => 'exhibition',
        'filter' => $filter,
        'limit' => $limit,
        'show_meta' => '1',
        'show_summary' => '1',
    ];

    if ($search !== '') {
        $params['search'] = $search;
    }

    return $params;
}

function iss_graph_wpcli_facade_availability_signature(array $data): array
{
    $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    return [
        'provider' => (string) ($data['provider'] ?? ''),
        'kind' => (string) ($data['kind'] ?? ''),
        'storage_kind' => (string) ($data['storage_kind'] ?? ''),
        'filters' => [
            'kind' => (string) ($filters['kind'] ?? ''),
            'filter' => (string) ($filters['filter'] ?? ''),
            'limit' => (int) ($filters['limit'] ?? 0),
            'search' => (string) ($filters['search'] ?? ''),
            'today' => (string) ($filters['today'] ?? ''),
        ],
        'count' => (int) ($data['count'] ?? 0),
        'html_hash' => md5((string) ($data['html'] ?? '')),
        'is_empty' => (bool) ($data['is_empty'] ?? false),
        'items' => array_values(array_map(static function (array $item): array {
            $type = isset($item['type']) && is_array($item['type']) ? $item['type'] : [];
            $availability = isset($item['availability']) && is_array($item['availability']) ? $item['availability'] : [];
            $editorial = isset($item['editorial']) && is_array($item['editorial']) ? $item['editorial'] : [];
            $schema = isset($item['schema']) && is_array($item['schema']) ? $item['schema'] : [];
            $source = isset($item['source']) && is_array($item['source']) ? $item['source'] : [];

            return [
                'id' => (int) ($item['id'] ?? 0),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
                'storage_kind' => (string) ($item['storage_kind'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'type_slugs' => isset($type['slugs']) && is_array($type['slugs']) ? array_values($type['slugs']) : [],
                'type_label' => (string) ($type['label'] ?? ''),
                'state' => (string) ($availability['state'] ?? ''),
                'starts_at' => (string) ($availability['starts_at'] ?? ''),
                'ends_at' => (string) ($availability['ends_at'] ?? ''),
                'period_label' => (string) ($availability['period_label'] ?? ''),
                'editorial_signal' => (string) ($editorial['signal'] ?? ''),
                'schema_kind' => (string) ($schema['kind'] ?? ''),
                'schema_type' => (string) ($schema['type'] ?? ''),
                'emits_event_schema' => (bool) ($schema['emits_event_schema'] ?? true),
                'source_post_id' => (int) ($source['post_id'] ?? 0),
                'source_post_type' => (string) ($source['post_type'] ?? ''),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_availability_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_programm_rest_list_availability')) {
        WP_CLI::error('Programme availability REST service is unavailable.');
    }

    $limit = max(1, min(24, absint($assoc_args['limit'] ?? 5)));
    $scenarios = iss_graph_wpcli_facade_parse_availability_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one availability comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_availability_params_for_scenario($scenario, $limit);
        $direct = iss_graph_wpcli_direct_rest_callback('availability service', 'iss_programm_rest_list_availability', '/iss/v1/availability', $params, $errors);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/availability', $params, $errors, 'facade');
        iss_graph_wpcli_facade_require_keys('availability service', $direct, ['provider', 'kind', 'storage_kind', 'filters', 'count', 'items', 'html', 'is_empty'], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/availability', $facade, ['provider', 'kind', 'storage_kind', 'filters', 'count', 'items', 'html', 'is_empty'], $errors);

        $direct_signature = iss_graph_wpcli_facade_availability_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_availability_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Availability facade mismatch for scenario "%s".', $scenario);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] availability scenario="%s" filter=%s search="%s" count=%d matched',
            $scenario,
            (string) ($facade_signature['filters']['filter'] ?? ''),
            (string) ($facade_signature['filters']['search'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade availability comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade availability comparison passed for %d scenario(s).', count($scenarios)));
}

function iss_graph_wpcli_facade_parse_timeline_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['upcoming', 'month', 'event'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_timeline_params_for_scenario(string $scenario, int $limit): array
{
    $params = [
        'limit' => $limit,
        'order' => 'ASC',
        'render' => [
            'renderMode' => 'timeline',
            'yearGrouping' => true,
            'groupRecurringTours' => true,
        ],
        'filters' => [
            'time_mode' => 'upcoming',
        ],
    ];

    switch ($scenario) {
        case 'all':
            $params['filters']['time_mode'] = 'all';
            break;
        case 'month':
            $params['filters']['time_mode'] = 'month';
            $params['filters']['month'] = current_time('Y-m');
            break;
        case 'event':
        case 'events':
        case 'veranstaltung':
        case 'veranstaltungen':
            $params['filters']['time_mode'] = 'all';
            $params['filters']['item_type'] = 'event';
            break;
        case 'tour':
        case 'tours':
        case 'fuehrung':
        case 'fuehrungen':
            $params['filters']['time_mode'] = 'upcoming';
            $params['filters']['item_type'] = 'tour';
            break;
        case 'cards':
            $params['render']['renderMode'] = 'cards';
            $params['render']['yearGrouping'] = false;
            break;
        case 'upcoming':
        default:
            break;
    }

    return $params;
}

function iss_graph_wpcli_facade_timeline_signature(array $data): array
{
    $html = (string) ($data['html'] ?? '');

    return [
        'html_hash' => md5($html),
        'html_length' => strlen($html),
        'count' => (int) ($data['count'] ?? 0),
        'batchCount' => (int) ($data['batchCount'] ?? 0),
        'isEmpty' => (bool) ($data['isEmpty'] ?? false),
        'offset' => (int) ($data['offset'] ?? 0),
        'nextOffset' => (int) ($data['nextOffset'] ?? 0),
        'hasMore' => (bool) ($data['hasMore'] ?? false),
    ];
}

function iss_graph_wpcli_facade_timeline_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('iss_timeline_rest_render_collection')) {
        WP_CLI::error('Programme timeline REST service is unavailable.');
    }

    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $scenarios = iss_graph_wpcli_facade_parse_timeline_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one timeline comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_timeline_params_for_scenario($scenario, $limit);
        $legacy = iss_graph_wpcli_direct_rest_callback('timeline service', 'iss_timeline_rest_render_collection', '/iss/v1/timeline', $params, $errors);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/timeline', $params, $errors, 'facade');
        iss_graph_wpcli_facade_require_keys('timeline service', $legacy, ['html', 'count', 'batchCount', 'isEmpty', 'offset', 'nextOffset', 'hasMore'], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/timeline', $facade, ['html', 'count', 'batchCount', 'isEmpty', 'offset', 'nextOffset', 'hasMore'], $errors);

        $legacy_signature = iss_graph_wpcli_facade_timeline_signature($legacy);
        $facade_signature = iss_graph_wpcli_facade_timeline_signature($facade);

        if ($legacy_signature !== $facade_signature) {
            $errors[] = sprintf('Timeline facade mismatch for scenario "%s".', $scenario);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] timeline scenario="%s" count=%d html=%s matched',
            $scenario,
            (int) ($facade_signature['count'] ?? 0),
            (string) ($facade_signature['html_hash'] ?? '')
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade timeline comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade timeline comparison passed for %d scenario(s).', count($scenarios)));
}

function iss_graph_wpcli_facade_parse_tour_slot_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['tag', 'nomap'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_tour_slot_params_for_scenario(string $scenario, string $tag, int $post_id): array
{
    switch ($scenario) {
        case 'post':
        case 'post_id':
            return $post_id > 0 ? ['post_id' => $post_id] : [];
        case 'nomap':
        case 'missing':
            return [];
        case 'tag':
        default:
            return $tag !== '' ? ['tag' => $tag] : [];
    }
}

function iss_graph_wpcli_facade_tour_slots_signature(array $data): array
{
    $slots = isset($data['slots']) && is_array($data['slots']) ? $data['slots'] : [];

    return [
        'source' => (string) ($data['source'] ?? ''),
        'slots' => array_values(array_map(static function (array $slot): array {
            return [
                'id' => (string) ($slot['id'] ?? ''),
                'title' => (string) ($slot['title'] ?? ''),
                'start' => (string) ($slot['start'] ?? ''),
                'end' => $slot['end'] ?? null,
                'capacity' => $slot['capacity'] ?? null,
                'available' => $slot['available'] ?? null,
                'booking_url' => $slot['booking_url'] ?? null,
                'content_url' => $slot['content_url'] ?? null,
            ];
        }, array_filter($slots, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_tour_slots_compare_command(array $args, array $assoc_args): void
{
    if (!function_exists('is_tours_get_slots')) {
        WP_CLI::error('Tour slots REST service is unavailable.');
    }

    $tag = strtoupper(sanitize_text_field((string) ($assoc_args['tag'] ?? 'ELEKTRO')));
    $post_id = absint($assoc_args['post_id'] ?? 0);
    $scenarios = iss_graph_wpcli_facade_parse_tour_slot_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one tour-slot comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    $compared = 0;
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_tour_slot_params_for_scenario($scenario, $tag, $post_id);
        if (in_array($scenario, ['post', 'post_id'], true) && $post_id <= 0) {
            WP_CLI::warning(sprintf('Skipping tour-slot scenario "%s"; provide --post_id to compare it.', $scenario));
            continue;
        }
        if ($scenario === 'tag' && $tag === '') {
            $errors[] = 'Tour-slot scenario "tag" requires a non-empty --tag value.';
            continue;
        }

        $legacy = iss_graph_wpcli_direct_rest_callback('tour-slot service', 'is_tours_get_slots', '/iss/v1/tour-slots', $params, $errors);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/tour-slots', $params, $errors, 'facade');
        iss_graph_wpcli_facade_require_keys('tour-slot service', $legacy, ['source', 'slots'], $errors);
        iss_graph_wpcli_facade_require_keys('/iss/v1/tour-slots', $facade, ['source', 'slots'], $errors);

        $legacy_signature = iss_graph_wpcli_facade_tour_slots_signature($legacy);
        $facade_signature = iss_graph_wpcli_facade_tour_slots_signature($facade);

        if ($legacy_signature !== $facade_signature) {
            $errors[] = sprintf('Tour slots facade mismatch for scenario "%s".', $scenario);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] tour-slots scenario="%s" source=%s slots=%d matched',
            $scenario,
            (string) ($facade_signature['source'] ?? ''),
            count($facade_signature['slots'] ?? [])
        ));
        $compared++;
    }

    if ($compared <= 0 && !$errors) {
        WP_CLI::error('No tour-slot comparison scenarios were run.');
    }
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade tour-slot comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade tour-slot comparison passed for %d scenario(s).', $compared));
}

function iss_graph_wpcli_facade_parse_entity_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['list', 'archive_object', 'search'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_entity_params_for_scenario(string $scenario, int $limit, string $search): array
{
    $params = [
        'limit' => $limit,
    ];

    switch ($scenario) {
        case 'place':
        case 'places':
            $params['kind'] = 'place';
            break;
        case 'archive':
        case 'archive_object':
        case 'archive_objects':
            $params['kind'] = 'archive_object';
            break;
        case 'event':
        case 'events':
            $params['kind'] = 'event';
            break;
        case 'tour':
        case 'tours':
            $params['kind'] = 'tour';
            break;
        case 'exhibition':
        case 'exhibitions':
            $params['kind'] = 'exhibition';
            break;
        case 'project':
        case 'projects':
            $params['kind'] = 'project';
            break;
        case 'search':
            $params['q'] = $search;
            break;
        case 'list':
        default:
            break;
    }

    return $params;
}

function iss_graph_wpcli_facade_entity_storage_kinds_from_params(array $params): array
{
    $storage_kinds = [];
    $kind = sanitize_key((string) ($params['kind'] ?? $params['entity_kind'] ?? $params['canonical_kind'] ?? ''));
    if ($kind !== '') {
        $storage_kind = function_exists('iss_graph_get_storage_entity_kind') ? iss_graph_get_storage_entity_kind($kind) : $kind;
        if ($storage_kind !== '') {
            $storage_kinds[] = $storage_kind;
        }
    }

    foreach (iss_facade_rest_scalar_list($params['kinds'] ?? '') as $raw_kind) {
        $storage_kind = function_exists('iss_graph_get_storage_entity_kind') ? iss_graph_get_storage_entity_kind($raw_kind) : $raw_kind;
        if ($storage_kind !== '') {
            $storage_kinds[] = $storage_kind;
        }
    }

    return array_values(array_unique($storage_kinds));
}

function iss_graph_wpcli_facade_entity_direct_list_response(array $params): array
{
    $limit = iss_facade_rest_limit($params['limit'] ?? 24, 24, 100);
    $offset = iss_facade_rest_offset($params['offset'] ?? 0);
    $query = iss_graph_normalize_public_search_query((string) ($params['q'] ?? $params['search'] ?? ''));
    $storage_kinds = iss_graph_wpcli_facade_entity_storage_kinds_from_params($params);

    $args = [
        'limit' => $limit,
        'public_only' => true,
    ];
    if (count($storage_kinds) === 1) {
        $args['entity_kind'] = $storage_kinds[0];
    } elseif ($storage_kinds) {
        $args['entity_kinds'] = $storage_kinds;
    }

    if ($query !== '') {
        $rows = iss_graph_search_entities(array_merge($args, ['query' => $query]));
    } else {
        $args['offset'] = $offset;
        $args['orderby'] = sanitize_key((string) ($params['orderby'] ?? 'display_title'));
        $args['order'] = strtoupper((string) ($params['order'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $rows = iss_graph_get_service()->get_entities($args);
    }

    $items = array_values(array_filter(array_map(static function (array $entity): ?array {
        return iss_facade_rest_prepare_entity($entity, false);
    }, is_array($rows) ? $rows : [])));

    return [
        'query' => $query,
        'kind' => $storage_kinds[0] ?? '',
        'limit' => $limit,
        'offset' => $offset,
        'count' => count($items),
        'items' => $items,
    ];
}

function iss_graph_wpcli_facade_entity_direct_detail_response(int $entity_id): array
{
    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return [];
    }

    return [
        'item' => iss_facade_rest_prepare_entity($entity, true),
    ];
}

function iss_graph_wpcli_facade_entity_list_signature(array $data): array
{
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];

    return [
        'query' => (string) ($data['query'] ?? ''),
        'kind' => (string) ($data['kind'] ?? ''),
        'limit' => (int) ($data['limit'] ?? 0),
        'offset' => (int) ($data['offset'] ?? 0),
        'count' => (int) ($data['count'] ?? 0),
        'items' => array_values(array_map(static function (array $item): array {
            return [
                'id' => (int) ($item['id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
                'storage_kind' => (string) ($item['storage_kind'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'post_id' => (int) ($item['post_id'] ?? 0),
                'post_type' => (string) ($item['post_type'] ?? ''),
                'profile_post_id' => (int) ($item['profile_post_id'] ?? 0),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_entity_detail_signature(array $data): array
{
    $item = isset($data['item']) && is_array($data['item']) ? $data['item'] : [];

    return [
        'item' => [
            'id' => (int) ($item['id'] ?? 0),
            'kind' => (string) ($item['kind'] ?? ''),
            'storage_kind' => (string) ($item['storage_kind'] ?? ''),
            'title' => (string) ($item['title'] ?? ''),
            'slug' => (string) ($item['slug'] ?? ''),
            'url' => (string) ($item['url'] ?? ''),
            'post_id' => (int) ($item['post_id'] ?? 0),
            'post_type' => (string) ($item['post_type'] ?? ''),
            'names' => isset($item['names']) && is_array($item['names']) ? $item['names'] : [],
            'identifiers' => isset($item['identifiers']) && is_array($item['identifiers']) ? $item['identifiers'] : [],
            'relations' => isset($item['relations']) && is_array($item['relations']) ? $item['relations'] : [],
        ],
    ];
}

function iss_graph_wpcli_facade_entities_compare_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $search = iss_graph_normalize_public_search_query((string) ($assoc_args['search'] ?? 'salon'));
    if ($search === '') {
        $search = 'salon';
    }

    $scenarios = iss_graph_wpcli_facade_parse_entity_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one entity comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_entity_params_for_scenario($scenario, $limit, $search);
        $direct = iss_graph_wpcli_facade_entity_direct_list_response($params);
        $facade = iss_graph_wpcli_facade_request('/iss/v1/entities', $params, $errors, 'facade');
        $direct_signature = iss_graph_wpcli_facade_entity_list_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_entity_list_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Entity facade list mismatch for scenario "%s".', $scenario);
            continue;
        }

        $entity_id = absint($facade_signature['items'][0]['id'] ?? 0);
        if ($entity_id > 0) {
            $detail_path = sprintf('/iss/v1/entities/%d', $entity_id);
            $direct_detail = iss_graph_wpcli_facade_entity_direct_detail_response($entity_id);
            $facade_detail = iss_graph_wpcli_facade_request($detail_path, [], $errors, 'facade');
            if (iss_graph_wpcli_facade_entity_detail_signature($direct_detail) !== iss_graph_wpcli_facade_entity_detail_signature($facade_detail)) {
                $errors[] = sprintf('Entity facade detail mismatch for scenario "%s" entity %d.', $scenario, $entity_id);
                continue;
            }
        }

        WP_CLI::log(sprintf(
            '[compare] entities scenario="%s" count=%d detail_entity=%d matched',
            $scenario,
            (int) ($facade_signature['count'] ?? 0),
            $entity_id
        ));
    }

    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade entity comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade entity comparison passed for %d scenario(s).', count($scenarios)));
}

function iss_graph_wpcli_facade_parse_entity_relation_scenarios($value): array
{
    $raw_scenarios = is_string($value) && trim($value) !== ''
        ? preg_split('/\s*,\s*/', trim($value))
        : ['outgoing', 'incoming', 'organization'];

    $scenarios = [];
    foreach ((array) $raw_scenarios as $scenario) {
        $scenario = sanitize_key((string) $scenario);
        if ($scenario !== '') {
            $scenarios[] = $scenario;
        }
    }

    return array_values(array_unique($scenarios));
}

function iss_graph_wpcli_facade_entity_relation_params_for_scenario(string $scenario, int $limit): array
{
    $params = [
        'limit' => $limit,
        'direction' => 'outgoing',
    ];

    switch ($scenario) {
        case 'incoming':
            $params['direction'] = 'incoming';
            break;
        case 'both':
            $params['direction'] = 'both';
            break;
        case 'place':
        case 'places':
            $params['family'] = 'place';
            break;
        case 'person':
        case 'people':
        case 'persons':
            $params['family'] = 'person';
            break;
        case 'organization':
        case 'organizations':
        case 'organisation':
        case 'organisations':
            $params['family'] = 'organization';
            break;
        case 'outgoing':
        default:
            break;
    }

    return $params;
}

function iss_graph_wpcli_facade_find_relation_entity_id(string $direction = 'outgoing', string $family = ''): int
{
    global $wpdb;

    $service = iss_graph_get_service();
    $relation_table = $service->get_relation_table_name();
    $entity_table = $service->get_entity_table_name();
    $direction = iss_facade_rest_normalize_relation_direction($direction);
    $family = sanitize_key($family);
    $entity_column = $direction === 'incoming' ? 'r.to_entity_id' : 'r.from_entity_id';
    $where = ['r.is_public = 1', 'e.is_public = 1'];
    $values = [];
    if ($family !== '') {
        $where[] = 'r.relation_family = %s';
        $values[] = $family;
    }

    $values[] = 200;
    $sql = "SELECT {$entity_column} AS entity_id,
            MIN(r.id) AS relation_id
        FROM {$relation_table} r
        INNER JOIN {$entity_table} e
            ON e.id = {$entity_column}
        WHERE " . implode(' AND ', $where) . "
        GROUP BY {$entity_column}
        ORDER BY relation_id ASC
        LIMIT %d";

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Table names and the selected id column are fixed by this command; values are prepared above.
    $rows = $wpdb->get_results($wpdb->prepare($sql, $values), ARRAY_A);
    foreach (is_array($rows) ? $rows : [] as $row) {
        $entity_id = absint($row['entity_id'] ?? 0);
        if ($entity_id <= 0) {
            continue;
        }

        $entity = $service->get_entity_by_id($entity_id);
        if ($entity && iss_facade_rest_entity_is_public($entity)) {
            return $entity_id;
        }
    }

    return 0;
}

function iss_graph_wpcli_facade_entity_relations_direct_response(int $entity_id, array $params): array
{
    $entity = iss_graph_get_service()->get_entity_by_id($entity_id);
    if (!$entity || !iss_facade_rest_entity_is_public($entity)) {
        return [];
    }

    $request = new WP_REST_Request('GET', sprintf('/iss/v1/entities/%d/relations', $entity_id));
    foreach ($params as $key => $value) {
        $request->set_param((string) $key, $value);
    }

    return iss_facade_rest_get_entity_relations_response(
        $entity_id,
        iss_facade_rest_entity_relation_filters_from_request($request)
    );
}

function iss_graph_wpcli_facade_entity_relations_signature(array $data): array
{
    $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
    $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];

    return [
        'entity_id' => (int) ($data['entity_id'] ?? 0),
        'filters' => [
            'direction' => (string) ($filters['direction'] ?? ''),
            'family' => (string) ($filters['family'] ?? ''),
            'source_system' => (string) ($filters['source_system'] ?? ''),
            'limit' => (int) ($filters['limit'] ?? 0),
        ],
        'count' => (int) ($data['count'] ?? 0),
        'items' => array_values(array_map(static function (array $item): array {
            $source = isset($item['source']) && is_array($item['source']) ? $item['source'] : [];

            return [
                'relation_id' => (int) ($item['relation_id'] ?? 0),
                'direction' => (string) ($item['direction'] ?? ''),
                'entity_id' => (int) ($item['entity_id'] ?? 0),
                'kind' => (string) ($item['kind'] ?? ''),
                'canonical_kind' => (string) ($item['canonical_kind'] ?? ''),
                'storage_kind' => (string) ($item['storage_kind'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'slug' => (string) ($item['slug'] ?? ''),
                'url' => (string) ($item['url'] ?? ''),
                'family' => (string) ($item['family'] ?? ''),
                'type' => (string) ($item['type'] ?? ''),
                'role' => (string) ($item['role'] ?? ''),
                'label' => (string) ($item['label'] ?? ''),
                'primary' => (bool) ($item['primary'] ?? false),
                'valid_from_year' => $item['valid_from_year'] ?? null,
                'valid_to_year' => $item['valid_to_year'] ?? null,
                'source_system' => (string) ($source['system'] ?? ''),
                'source_ref' => (string) ($source['ref'] ?? ''),
            ];
        }, array_filter($items, 'is_array'))),
    ];
}

function iss_graph_wpcli_facade_entity_relations_compare_command(array $args, array $assoc_args): void
{
    $limit = max(1, min(25, absint($assoc_args['limit'] ?? 5)));
    $requested_entity_id = absint($assoc_args['entity_id'] ?? 0);
    $scenarios = iss_graph_wpcli_facade_parse_entity_relation_scenarios($assoc_args['scenarios'] ?? '');
    if (!$scenarios) {
        WP_CLI::error('Provide at least one entity-relation comparison scenario through --scenarios or use the defaults.');
    }

    $errors = [];
    $compared = 0;
    foreach ($scenarios as $scenario) {
        $params = iss_graph_wpcli_facade_entity_relation_params_for_scenario($scenario, $limit);
        $direction = (string) ($params['direction'] ?? 'outgoing');
        $family = (string) ($params['family'] ?? '');
        $lookup_direction = $direction === 'incoming' ? 'incoming' : 'outgoing';
        $entity_id = $requested_entity_id > 0
            ? $requested_entity_id
            : iss_graph_wpcli_facade_find_relation_entity_id($lookup_direction, $family);

        if ($entity_id <= 0) {
            WP_CLI::warning(sprintf('Skipping entity-relations scenario "%s"; no public relation-backed entity found.', $scenario));
            continue;
        }

        $path = sprintf('/iss/v1/entities/%d/relations', $entity_id);
        $direct = iss_graph_wpcli_facade_entity_relations_direct_response($entity_id, $params);
        $facade = iss_graph_wpcli_facade_request($path, $params, $errors, 'facade');
        $direct_signature = iss_graph_wpcli_facade_entity_relations_signature($direct);
        $facade_signature = iss_graph_wpcli_facade_entity_relations_signature($facade);

        if ($direct_signature !== $facade_signature) {
            $errors[] = sprintf('Entity-relations facade mismatch for scenario "%s" entity %d.', $scenario, $entity_id);
            continue;
        }

        WP_CLI::log(sprintf(
            '[compare] entity-relations scenario="%s" entity=%d direction=%s family=%s count=%d matched',
            $scenario,
            $entity_id,
            (string) ($facade_signature['filters']['direction'] ?? ''),
            (string) ($facade_signature['filters']['family'] ?? ''),
            (int) ($facade_signature['count'] ?? 0)
        ));
        $compared++;
    }

    if ($compared <= 0 && !$errors) {
        WP_CLI::error('No entity-relation comparison scenarios were run.');
    }
    if ($errors) {
        WP_CLI::error_multi_line($errors);
        WP_CLI::error(sprintf('ISS graph facade entity-relation comparison failed with %d issue(s).', count($errors)));
    }

    WP_CLI::success(sprintf('ISS graph facade entity-relation comparison passed for %d scenario(s).', $compared));
}

function iss_graph_wpcli_parse_drift_checks(string $value): array
{
    $default_checks = [
        'post-identifiers',
        'register-identifiers',
        'content-identifiers',
        'archive-identifiers',
        'place-taxonomy',
        'place-graph',
        'search-index',
        'editorial-signals',
        'entity-kind-contract',
        'public-object-contract',
        'entity-relations-contract',
        'entity-occurrences-contract',
        'availability-contract',
        'facade-route-contract',
        'facade-consumer-contract',
        'frontend-view-contract',
    ];
    $available = array_merge($default_checks, [
        'alias-backfill-replay',
        'canonical-organization-seeds',
        'canonical-wf-industriesalon',
    ]);

    $value = trim($value);
    if ($value === '') {
        return $default_checks;
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
        case 'entity-kind-contract':
            return iss_graph_wpcli_check_entity_kind_contract($limit);
        case 'public-object-contract':
            return iss_graph_wpcli_check_public_object_contract($limit);
        case 'entity-relations-contract':
            return iss_graph_wpcli_check_entity_relations_contract($limit);
        case 'entity-occurrences-contract':
            return iss_graph_wpcli_check_entity_occurrences_contract($limit);
        case 'availability-contract':
            return iss_graph_wpcli_check_availability_contract($limit);
        case 'facade-route-contract':
            return iss_graph_wpcli_check_facade_route_contract($limit);
        case 'facade-consumer-contract':
            return iss_graph_wpcli_check_facade_consumer_contract($limit);
        case 'frontend-view-contract':
            return iss_graph_wpcli_check_frontend_view_contract($limit);
        case 'alias-backfill-replay':
            return iss_graph_wpcli_check_alias_backfill_replay($limit);
        case 'canonical-organization-seeds':
            return iss_graph_wpcli_check_canonical_organization_seeds($limit);
        case 'canonical-wf-industriesalon':
            return iss_graph_wpcli_check_canonical_wf_industriesalon($limit);
        default:
            return [
                'checked' => 0,
                'errors' => [sprintf('Unknown drift check: %s', $check)],
            ];
    }
}

function iss_graph_wpcli_check_alias_backfill_replay(int $limit): array
{
    if (!function_exists('iss_graph_audit_entity_alias_backfill')) {
        return [
            'checked' => 0,
            'errors' => ['Alias backfill audit is unavailable.'],
        ];
    }

    $stats = iss_graph_audit_entity_alias_backfill(['sample_limit' => min(25, $limit)]);
    $changed = (int) ($stats['changed_entities'] ?? 0);
    $removed = (int) ($stats['removed_names'] ?? 0);
    $added = (int) ($stats['added_names'] ?? 0);
    if ($changed <= 0 && $removed <= 0 && $added <= 0) {
        return [
            'checked' => (int) ($stats['entities'] ?? 0),
            'errors' => [],
        ];
    }

    $errors = [
        sprintf(
            'Alias backfill replay pending: changed_entities=%d current_names=%d proposed_names=%d removed_names=%d added_names=%d.',
            $changed,
            (int) ($stats['current_names'] ?? 0),
            (int) ($stats['proposed_names'] ?? 0),
            $removed,
            $added
        ),
    ];

    foreach ((array) ($stats['samples'] ?? []) as $preview) {
        if (!is_array($preview) || count($errors) >= $limit) {
            break;
        }

        $errors[] = sprintf(
            'Alias backfill pending for entity %d [%s] "%s": removed=%d added=%d.',
            (int) ($preview['entity_id'] ?? 0),
            (string) ($preview['entity_kind'] ?? ''),
            iss_graph_wpcli_entity_hygiene_clip((string) ($preview['title'] ?? ''), 80),
            (int) ($preview['removed_count'] ?? 0),
            (int) ($preview['added_count'] ?? 0)
        );
    }

    return [
        'checked' => (int) ($stats['entities'] ?? 0),
        'errors' => $errors,
    ];
}

function iss_graph_wpcli_check_entity_relations_contract(int $limit): array
{
    $errors = [];
    $checked = 0;
    $request_limit = max(1, min(5, $limit));
    $scenarios = [
        'outgoing' => iss_graph_wpcli_facade_find_relation_entity_id('outgoing', ''),
        'incoming' => iss_graph_wpcli_facade_find_relation_entity_id('incoming', ''),
    ];

    foreach ($scenarios as $direction => $entity_id) {
        $entity_id = absint($entity_id);
        if ($entity_id <= 0) {
            continue;
        }

        $checked++;
        $path = sprintf('/iss/v1/entities/%d/relations', $entity_id);
        $request = new WP_REST_Request('GET', $path);
        $request->set_param('direction', $direction);
        $request->set_param('limit', $request_limit);
        $response = rest_do_request($request);
        if ($response->is_error()) {
            $error = $response->as_error();
            $errors[] = sprintf(
                '%s returned REST error: %s.',
                $path,
                $error ? $error->get_error_message() : 'unknown error'
            );
            continue;
        }

        $status = (int) $response->get_status();
        if ($status !== 200) {
            $errors[] = sprintf('%s returned status %d for direction=%s, expected 200.', $path, $status, $direction);
            continue;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            $errors[] = sprintf('%s returned a non-object response for direction=%s.', $path, $direction);
            continue;
        }

        foreach (['entity_id', 'filters', 'count', 'items'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('%s direction=%s is missing key: %s.', $path, $direction, $key);
            }
        }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        if ((int) ($data['count'] ?? -1) !== count($items)) {
            $errors[] = sprintf('%s direction=%s count does not match item list length.', $path, $direction);
        }

        if (isset($items[0]) && is_array($items[0])) {
            $checked++;
            foreach (['relation_id', 'direction', 'entity_id', 'kind', 'storage_kind', 'name', 'family', 'type', 'label', 'source'] as $key) {
                if (!array_key_exists($key, $items[0])) {
                    $errors[] = sprintf('%s direction=%s first relation item is missing key: %s.', $path, $direction, $key);
                }
            }
            if (($items[0]['direction'] ?? '') !== $direction) {
                $errors[] = sprintf('%s first relation item direction is %s, expected %s.', $path, (string) ($items[0]['direction'] ?? ''), $direction);
            }
            if (isset($items[0]['source']) && !is_array($items[0]['source'])) {
                $errors[] = sprintf('%s first relation item source is not an object.', $path);
            }
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_check_entity_occurrences_contract(int $limit): array
{
    if (!function_exists('iss_occurrences_query')) {
        return [
            'checked' => 0,
            'errors' => [],
        ];
    }

    $errors = [];
    $checked = 0;
    $request_limit = max(1, min(5, $limit));
    $scenarios = [
        'upcoming' => iss_graph_wpcli_facade_occurrence_params_for_scenario('upcoming', $request_limit),
        'event' => iss_graph_wpcli_facade_occurrence_params_for_scenario('event', $request_limit),
        'tour' => iss_graph_wpcli_facade_occurrence_params_for_scenario('tour', $request_limit),
    ];

    foreach ($scenarios as $scenario => $params) {
        $entity_id = iss_graph_wpcli_facade_find_occurrence_entity_id($params);
        if ($entity_id <= 0) {
            continue;
        }

        $checked++;
        $path = sprintf('/iss/v1/entities/%d/occurrences', $entity_id);
        $request = new WP_REST_Request('GET', $path);
        foreach ($params as $key => $value) {
            $request->set_param((string) $key, $value);
        }

        $response = rest_do_request($request);
        if ($response->is_error()) {
            $error = $response->as_error();
            $errors[] = sprintf(
                '%s scenario=%s returned REST error: %s.',
                $path,
                $scenario,
                $error ? $error->get_error_message() : 'unknown error'
            );
            continue;
        }

        $status = (int) $response->get_status();
        if ($status !== 200) {
            $errors[] = sprintf('%s scenario=%s returned status %d, expected 200.', $path, $scenario, $status);
            continue;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            $errors[] = sprintf('%s scenario=%s returned a non-object response.', $path, $scenario);
            continue;
        }

        foreach (['entity_id', 'filters', 'count', 'items'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('%s scenario=%s is missing key: %s.', $path, $scenario, $key);
            }
        }
        if ((int) ($data['entity_id'] ?? 0) !== $entity_id) {
            $errors[] = sprintf('%s scenario=%s entity_id does not match route id.', $path, $scenario);
        }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        if ((int) ($data['count'] ?? -1) !== count($items)) {
            $errors[] = sprintf('%s scenario=%s count does not match item list length.', $path, $scenario);
        }

        if (isset($items[0]) && is_array($items[0])) {
            $checked++;
            foreach (['id', 'entity_id', 'kind', 'contract_kind', 'subtype', 'contract', 'title', 'starts_at', 'source', 'location', 'schema'] as $key) {
                if (!array_key_exists($key, $items[0])) {
                    $errors[] = sprintf('%s scenario=%s first occurrence item is missing key: %s.', $path, $scenario, $key);
                }
            }
            if ((int) ($items[0]['entity_id'] ?? 0) !== $entity_id) {
                $errors[] = sprintf('%s scenario=%s first occurrence entity_id does not match route id.', $path, $scenario);
            }
            if (isset($items[0]['schema']) && is_array($items[0]['schema'])) {
                $schema = $items[0]['schema'];
                if (($schema['type'] ?? '') !== 'Event' || empty($schema['emits_event_schema'])) {
                    $errors[] = sprintf('%s scenario=%s occurrence schema is not Event-emitting.', $path, $scenario);
                }
            }
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_check_availability_contract(int $limit): array
{
    if (!function_exists('iss_programm_rest_list_availability')) {
        return [
            'checked' => 0,
            'errors' => [],
        ];
    }

    $errors = [];
    $checked = 0;
    $request_limit = max(1, min(5, $limit));
    $scenarios = [
        ['filter' => 'aktuell', 'search' => ''],
        ['filter' => 'dauer', 'search' => ''],
        ['filter' => 'digital', 'search' => ''],
        ['filter' => 'archiv', 'search' => ''],
        ['filter' => 'aktuell', 'search' => 'Werk'],
    ];

    foreach ($scenarios as $scenario) {
        $filter = (string) ($scenario['filter'] ?? 'aktuell');
        $search = (string) ($scenario['search'] ?? '');
        $checked++;
        $request = new WP_REST_Request('GET', '/iss/v1/availability');
        $request->set_param('kind', 'exhibition');
        $request->set_param('filter', $filter);
        $request->set_param('limit', $request_limit);
        if ($search !== '') {
            $request->set_param('search', $search);
        }
        $response = rest_do_request($request);
        if ($response->is_error()) {
            $error = $response->as_error();
            $errors[] = sprintf(
                '/iss/v1/availability filter=%s returned REST error: %s.',
                $filter,
                $error ? $error->get_error_message() : 'unknown error'
            );
            continue;
        }

        $status = (int) $response->get_status();
        if ($status !== 200) {
            $errors[] = sprintf('/iss/v1/availability filter=%s returned status %d, expected 200.', $filter, $status);
            continue;
        }

        $data = $response->get_data();
        if (!is_array($data)) {
            $errors[] = sprintf('/iss/v1/availability filter=%s returned a non-object response.', $filter);
            continue;
        }

        foreach (['provider', 'kind', 'storage_kind', 'filters', 'count', 'items', 'html', 'is_empty'] as $key) {
            if (!array_key_exists($key, $data)) {
                $errors[] = sprintf('/iss/v1/availability filter=%s is missing key: %s.', $filter, $key);
            }
        }

        if (isset($data['html']) && !is_string($data['html'])) {
            $errors[] = sprintf('/iss/v1/availability filter=%s html is not a string.', $filter);
        }
        if (isset($data['is_empty']) && !is_bool($data['is_empty'])) {
            $errors[] = sprintf('/iss/v1/availability filter=%s is_empty is not a boolean.', $filter);
        }

        if (($data['provider'] ?? '') !== 'iss-frontend') {
            $errors[] = sprintf('/iss/v1/availability filter=%s provider is %s, expected iss-frontend.', $filter, (string) ($data['provider'] ?? ''));
        }
        if (($data['kind'] ?? '') !== 'exhibition' || ($data['storage_kind'] ?? '') !== 'ausstellung') {
            $errors[] = sprintf('/iss/v1/availability filter=%s does not expose exhibition/ausstellung kind fields.', $filter);
        }

        $filters = isset($data['filters']) && is_array($data['filters']) ? $data['filters'] : [];
        foreach (['kind', 'filter', 'limit', 'search', 'today'] as $key) {
            if (!array_key_exists($key, $filters)) {
                $errors[] = sprintf('/iss/v1/availability filter=%s response filters is missing key: %s.', $filter, $key);
            }
        }
        if ($search !== '' && (string) ($filters['search'] ?? '') !== $search) {
            $errors[] = sprintf('/iss/v1/availability filter=%s did not preserve search filter %s.', $filter, $search);
        }

        $items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
        if ((int) ($data['count'] ?? -1) !== count($items)) {
            $errors[] = sprintf('/iss/v1/availability filter=%s count does not match item list length.', $filter);
        }

        if (isset($items[0]) && is_array($items[0])) {
            $checked++;
            foreach (['id', 'entity_id', 'kind', 'storage_kind', 'title', 'url', 'type', 'availability', 'editorial', 'schema', 'source'] as $key) {
                if (!array_key_exists($key, $items[0])) {
                    $errors[] = sprintf('/iss/v1/availability filter=%s first item is missing key: %s.', $filter, $key);
                }
            }
            if (isset($items[0]['type']) && !is_array($items[0]['type'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item type is not an object.', $filter);
            }
            if (isset($items[0]['availability']) && !is_array($items[0]['availability'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item availability is not an object.', $filter);
            }
            if (isset($items[0]['editorial']) && !is_array($items[0]['editorial'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item editorial is not an object.', $filter);
            }
            if (isset($items[0]['schema']) && !is_array($items[0]['schema'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item schema is not an object.', $filter);
            }
            if (isset($items[0]['schema']) && is_array($items[0]['schema'])) {
                $schema = $items[0]['schema'];
                if (!array_key_exists('emits_event_schema', $schema) || !array_key_exists('type', $schema)) {
                    $errors[] = sprintf('/iss/v1/availability filter=%s first item schema is missing event-split keys.', $filter);
                } elseif (!empty($schema['emits_event_schema']) || (string) ($schema['type'] ?? '') === 'Event') {
                    $errors[] = sprintf('/iss/v1/availability filter=%s first item must not emit Event schema.', $filter);
                }
            }
            if (isset($items[0]['source']) && !is_array($items[0]['source'])) {
                $errors[] = sprintf('/iss/v1/availability filter=%s first item source is not an object.', $filter);
            }
        }

        if (count($errors) >= $limit) {
            break;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_canonical_organization_seed_contract(): array
{
    return [
        [
            'label' => 'KWO',
            'source_id' => 'kabelwerk-oberspree',
            'canonical_slug' => 'kabelwerk-oberspree',
            'display_title' => 'Kabelwerk Oberspree',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'kabelwerk-oberspree', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'manual_alias', 'normalized_name' => 'kwo', 'name_type' => 'abbreviation', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'veb-kabelwerk-oberspree', 'name_type' => 'official', 'is_primary' => 0],
            ],
        ],
        [
            'label' => 'AEG',
            'source_id' => 'allgemeine-elektricitats-gesellschaft',
            'canonical_slug' => 'allgemeine-elektricitats-gesellschaft',
            'display_title' => 'Allgemeine Elektricitäts-Gesellschaft',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'allgemeine-elektricitats-gesellschaft', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'manual_alias', 'normalized_name' => 'aeg', 'name_type' => 'abbreviation', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'allgemeine-elektricitaets-gesellschaft', 'name_type' => 'transliteration', 'is_primary' => 0],
            ],
        ],
    ];
}

function iss_graph_wpcli_check_canonical_organization_seeds(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $name_table = $service->get_name_table_name();
    if (!$service->table_exists($entity_table) || !$service->table_exists($name_table)) {
        return [
            'checked' => 0,
            'errors' => ['Graph entity or name table is unavailable.'],
        ];
    }

    $errors = [];
    $checked = 0;
    foreach (iss_graph_wpcli_canonical_organization_seed_contract() as $seed) {
        $checked++;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, canonical_slug, display_title, status, is_public, search_visibility
                FROM {$entity_table}
                WHERE entity_kind = 'organization'
                  AND source_system = 'manual_slug'
                  AND source_id = %s
                ORDER BY id ASC",
                (string) ($seed['source_id'] ?? '')
            ),
            ARRAY_A
        );
        $rows = is_array($rows) ? $rows : [];
        if (count($rows) !== 1) {
            $errors[] = sprintf(
                'Canonical organization seed %s expected exactly 1 manual_slug row for source_id=%s, found %d.',
                (string) ($seed['label'] ?? ''),
                (string) ($seed['source_id'] ?? ''),
                count($rows)
            );
            if (count($errors) >= $limit) {
                break;
            }
            continue;
        }

        $entity = $rows[0];
        $entity_id = (int) ($entity['id'] ?? 0);
        foreach (['canonical_slug', 'display_title', 'status', 'search_visibility'] as $field) {
            $expected = $field === 'status' ? 'publish' : (string) ($seed[$field] ?? '');
            if ($field === 'search_visibility') {
                $expected = 'hidden';
            }
            $actual = (string) ($entity[$field] ?? '');
            if ($actual !== $expected) {
                $errors[] = sprintf('Canonical organization seed %s entity %d has %s=%s, expected %s.', (string) ($seed['label'] ?? ''), $entity_id, $field, $actual, $expected);
            }
        }

        if ((int) ($entity['is_public'] ?? 0) !== 0) {
            $errors[] = sprintf('Canonical organization seed %s entity %d must remain hidden, found is_public=%d.', (string) ($seed['label'] ?? ''), $entity_id, (int) ($entity['is_public'] ?? 0));
        }

        foreach ((array) ($seed['names'] ?? []) as $name) {
            $checked++;
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$name_table}
                    WHERE entity_id = %d
                      AND source_system = %s
                      AND normalized_name = %s
                      AND name_type = %s
                      AND is_primary = %d",
                    $entity_id,
                    (string) ($name['source_system'] ?? ''),
                    (string) ($name['normalized_name'] ?? ''),
                    (string) ($name['name_type'] ?? ''),
                    (int) ($name['is_primary'] ?? 0)
                )
            );
            if ($exists <= 0) {
                $errors[] = sprintf(
                    'Canonical organization seed %s entity %d is missing %s %s:%s.',
                    (string) ($seed['label'] ?? ''),
                    $entity_id,
                    (string) ($name['source_system'] ?? ''),
                    (string) ($name['name_type'] ?? ''),
                    (string) ($name['normalized_name'] ?? '')
                );
            }

            if (count($errors) >= $limit) {
                break 2;
            }
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

function iss_graph_wpcli_canonical_wf_industriesalon_contract(): array
{
    return [
        [
            'label' => 'Industriesalon',
            'source_system' => 'manual_slug',
            'source_id' => 'industriesalon-schoneweide-e-v',
            'canonical_slug' => 'industriesalon-schoneweide-e-v',
            'display_title' => 'Industriesalon Schöneweide e.V.',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'industriesalon-schoneweide-e-v', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'manual_alias', 'normalized_name' => 'iss', 'name_type' => 'abbreviation', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'industriesalon', 'name_type' => 'alternative', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'industriesalon-schoneweide', 'name_type' => 'alternative', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'industriesalon-schoeneweide', 'name_type' => 'transliteration', 'is_primary' => 0],
            ],
            'identifiers' => [
                ['namespace' => 'archive_institution', 'normalized_value' => 'institution:29', 'status' => 'accepted'],
            ],
        ],
        [
            'label' => 'WF',
            'source_system' => 'archive_person_organization_label',
            'source_id' => 'person-label:63251',
            'canonical_slug' => 'archive_person_organization_label-person-label63251',
            'display_title' => 'Werk für Fernsehelektronik',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'werk-fur-fernsehelektronik', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'manual_alias', 'normalized_name' => 'wf', 'name_type' => 'abbreviation', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'veb-werk-fur-fernsehelektronik', 'name_type' => 'official', 'is_primary' => 0],
                ['source_system' => 'manual_alias', 'normalized_name' => 'werk-fuer-fernsehelektronik', 'name_type' => 'transliteration', 'is_primary' => 0],
            ],
        ],
        [
            'label' => 'Werk für Fernmeldewesen',
            'source_system' => 'archive_person_organization_label',
            'source_id' => 'person-label:63659',
            'canonical_slug' => 'archive_person_organization_label-person-label63659',
            'display_title' => 'Werk für Fernmeldewesen',
            'names' => [
                ['source_system' => 'entity_title', 'normalized_name' => 'werk-fur-fernmeldewesen', 'name_type' => 'primary', 'is_primary' => 1],
                ['source_system' => 'archive_title', 'normalized_name' => 'werk-fur-fernmeldewesen', 'name_type' => 'source_label', 'is_primary' => 0],
            ],
        ],
    ];
}

function iss_graph_wpcli_check_canonical_wf_industriesalon(int $limit): array
{
    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $name_table = $service->get_name_table_name();
    $identifier_table = $service->get_identifier_table_name();
    $relation_table = $service->get_relation_table_name();
    foreach ([$entity_table, $name_table, $identifier_table, $relation_table] as $table_name) {
        if (!$service->table_exists($table_name)) {
            return [
                'checked' => 0,
                'errors' => ['Graph entity, name, identifier, or relation table is unavailable.'],
            ];
        }
    }

    $errors = [];
    $checked = 0;
    $entity_ids = [];
    foreach (iss_graph_wpcli_canonical_wf_industriesalon_contract() as $seed) {
        $checked++;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, canonical_slug, display_title, status, is_public, search_visibility
                FROM {$entity_table}
                WHERE entity_kind = 'organization'
                  AND source_system = %s
                  AND source_id = %s
                ORDER BY id ASC",
                (string) ($seed['source_system'] ?? ''),
                (string) ($seed['source_id'] ?? '')
            ),
            ARRAY_A
        );
        $rows = is_array($rows) ? $rows : [];
        if (count($rows) !== 1) {
            $errors[] = sprintf(
                'Canonical identity %s expected exactly 1 row for %s:%s, found %d.',
                (string) ($seed['label'] ?? ''),
                (string) ($seed['source_system'] ?? ''),
                (string) ($seed['source_id'] ?? ''),
                count($rows)
            );
            if (count($errors) >= $limit) {
                break;
            }
            continue;
        }

        $entity = $rows[0];
        $entity_id = (int) ($entity['id'] ?? 0);
        $entity_ids[(string) ($seed['label'] ?? '')] = $entity_id;
        foreach (['canonical_slug', 'display_title', 'status', 'search_visibility'] as $field) {
            $expected = $field === 'status' ? 'publish' : (string) ($seed[$field] ?? '');
            if ($field === 'search_visibility') {
                $expected = 'hidden';
            }
            $actual = (string) ($entity[$field] ?? '');
            if ($actual !== $expected) {
                $errors[] = sprintf('Canonical identity %s entity %d has %s=%s, expected %s.', (string) ($seed['label'] ?? ''), $entity_id, $field, $actual, $expected);
            }
        }

        if ((int) ($entity['is_public'] ?? 0) !== 0) {
            $errors[] = sprintf('Canonical identity %s entity %d must remain hidden, found is_public=%d.', (string) ($seed['label'] ?? ''), $entity_id, (int) ($entity['is_public'] ?? 0));
        }

        foreach ((array) ($seed['names'] ?? []) as $name) {
            $checked++;
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$name_table}
                    WHERE entity_id = %d
                      AND source_system = %s
                      AND normalized_name = %s
                      AND name_type = %s
                      AND is_primary = %d",
                    $entity_id,
                    (string) ($name['source_system'] ?? ''),
                    (string) ($name['normalized_name'] ?? ''),
                    (string) ($name['name_type'] ?? ''),
                    (int) ($name['is_primary'] ?? 0)
                )
            );
            if ($exists <= 0) {
                $errors[] = sprintf(
                    'Canonical identity %s entity %d is missing %s %s:%s.',
                    (string) ($seed['label'] ?? ''),
                    $entity_id,
                    (string) ($name['source_system'] ?? ''),
                    (string) ($name['name_type'] ?? ''),
                    (string) ($name['normalized_name'] ?? '')
                );
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }

        foreach ((array) ($seed['identifiers'] ?? []) as $identifier) {
            $checked++;
            $exists = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*)
                    FROM {$identifier_table}
                    WHERE entity_id = %d
                      AND namespace = %s
                      AND normalized_value = %s
                      AND status = %s",
                    $entity_id,
                    (string) ($identifier['namespace'] ?? ''),
                    (string) ($identifier['normalized_value'] ?? ''),
                    (string) ($identifier['status'] ?? '')
                )
            );
            if ($exists <= 0) {
                $errors[] = sprintf(
                    'Canonical identity %s entity %d is missing identifier %s:%s.',
                    (string) ($seed['label'] ?? ''),
                    $entity_id,
                    (string) ($identifier['namespace'] ?? ''),
                    (string) ($identifier['normalized_value'] ?? '')
                );
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }
    }

    $checked++;
    $archive_institution_rows = (int) $wpdb->get_var(
        "SELECT COUNT(*)
        FROM {$entity_table}
        WHERE entity_kind = 'organization'
          AND source_system = 'archive_institution'
          AND source_id = 'institution:29'"
    );
    if ($archive_institution_rows > 0) {
        $errors[] = sprintf('Archive institution institution:29 must resolve by identifier only; found %d active source row(s).', $archive_institution_rows);
    }

    if (!empty($entity_ids['Industriesalon'])) {
        $checked++;
        $old_institution_relations = (int) $wpdb->get_var(
            "SELECT COUNT(*)
            FROM {$relation_table} r
            INNER JOIN {$entity_table} e ON e.id = r.to_entity_id
            WHERE e.entity_kind = 'organization'
              AND e.source_system = 'archive_institution'
              AND e.source_id = 'institution:29'"
        );
        if ($old_institution_relations > 0) {
            $errors[] = sprintf('Archive institution institution:29 still has %d relation(s) pointing at the retired source row.', $old_institution_relations);
        }
    }

    if (!empty($entity_ids['WF'])) {
        $checked++;
        $noncanonical_wf_names = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*)
                FROM {$name_table}
                WHERE normalized_name = 'wf'
                  AND entity_id <> %d",
                (int) $entity_ids['WF']
            )
        );
        if ($noncanonical_wf_names > 0) {
            $errors[] = sprintf('WF must be a canonical alias only for Werk für Fernsehelektronik; found %d noncanonical WF name row(s).', $noncanonical_wf_names);
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
}

function iss_graph_wpcli_check_facade_route_contract(int $limit): array
{
    $contract = iss_graph_wpcli_facade_route_contract();
    $routes = array_keys(rest_get_server()->get_routes());
    $errors = [];
    $checked = 0;

    foreach ((array) $contract['required_read'] as $route) {
        $checked++;
        if (!in_array($route, $routes, true)) {
            $errors[] = sprintf('Required facade read route is not registered: %s.', (string) $route);
        }
    }

    foreach ((array) $contract['provider_read'] as $route => $provider_function) {
        if (!function_exists((string) $provider_function)) {
            continue;
        }

        $checked++;
        if (!in_array((string) $route, $routes, true)) {
            $errors[] = sprintf('Provider facade read route is not registered: %s.', (string) $route);
        }
    }

    foreach ((array) $contract['allowed_write'] as $route => $provider_function) {
        if (!function_exists((string) $provider_function)) {
            continue;
        }

        $checked++;
        if (!in_array((string) $route, $routes, true)) {
            $errors[] = sprintf('Expected booking write route is not registered: %s.', (string) $route);
        }
    }

    foreach ((array) $contract['retired_read'] as $route) {
        $checked++;
        if (in_array($route, $routes, true)) {
            $errors[] = sprintf('Retired read route is still registered: %s.', (string) $route);
        }
    }

    $scan = iss_graph_wpcli_facade_route_scan_sources((array) $contract['retired_read'], $limit);
    $checked += (int) ($scan['checked'] ?? 0);
    foreach ((array) ($scan['errors'] ?? []) as $error) {
        if (is_string($error) && $error !== '') {
            $errors[] = $error;
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
    ];
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
        $entity_kind = function_exists('iss_graph_get_entity_kind_for_post_type')
            ? iss_graph_get_entity_kind_for_post_type((string) $post->post_type)
            : sanitize_key((string) $post->post_type);
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

function iss_graph_wpcli_check_entity_kind_contract(int $limit): array
{
    global $wpdb;

    if (!function_exists('iss_graph_get_entity_kind_definition')) {
        return ['checked' => 0, 'errors' => ['Entity-kind registry is unavailable.']];
    }

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $errors = [];
    $checked = 0;

    $kind_rows = $wpdb->get_results(
        "SELECT entity_kind, COUNT(*) AS row_count
        FROM {$entity_table}
        GROUP BY entity_kind
        ORDER BY entity_kind ASC",
        ARRAY_A
    );

    foreach (is_array($kind_rows) ? $kind_rows : [] as $row) {
        $checked++;
        $entity_kind = sanitize_key((string) ($row['entity_kind'] ?? ''));
        if ($entity_kind === '' || !iss_graph_get_entity_kind_definition($entity_kind)) {
            $errors[] = sprintf(
                'Entity kind %s is not registered in the iss-graph entity-kind contract.',
                $entity_kind !== '' ? $entity_kind : '(empty)'
            );
        }

        if (count($errors) >= $limit) {
            return ['checked' => $checked, 'errors' => $errors];
        }
    }

    $rows = $wpdb->get_results(
        "SELECT id, entity_kind, post_id, display_title
        FROM {$entity_table}
        WHERE post_id IS NOT NULL
        ORDER BY id ASC",
        ARRAY_A
    );

    foreach (is_array($rows) ? $rows : [] as $row) {
        $entity_id = (int) ($row['id'] ?? 0);
        $post_id = (int) ($row['post_id'] ?? 0);
        $stored_kind = sanitize_key((string) ($row['entity_kind'] ?? ''));
        $post = $post_id > 0 ? get_post($post_id) : null;
        if (!$post instanceof WP_Post) {
            continue;
        }

        $checked++;
        $expected_kind = function_exists('iss_graph_get_entity_kind_for_post_type')
            ? iss_graph_get_entity_kind_for_post_type((string) $post->post_type)
            : sanitize_key((string) $post->post_type);
        $candidates = function_exists('iss_graph_get_entity_kind_storage_candidates')
            ? iss_graph_get_entity_kind_storage_candidates($expected_kind)
            : [$expected_kind];

        if (!in_array($stored_kind, $candidates, true)) {
            $errors[] = sprintf(
                'Entity %d post %d [%s] stores kind=%s but expected one of: %s.',
                $entity_id,
                $post_id,
                (string) $post->post_type,
                $stored_kind,
                implode(', ', $candidates)
            );
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

function iss_graph_wpcli_get_entity_rows_for_post(int $post_id): array
{
    global $wpdb;

    $post_id = absint($post_id);
    if ($post_id <= 0) {
        return [];
    }

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, entity_kind, display_title
            FROM {$entity_table}
            WHERE post_id = %d
            ORDER BY entity_kind ASC, id ASC",
            $post_id
        ),
        ARRAY_A
    );

    return is_array($rows) ? $rows : [];
}

function iss_graph_wpcli_format_entity_row_summary(array $rows): string
{
    $items = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }

        $items[] = sprintf(
            '#%d:%s',
            (int) ($row['id'] ?? 0),
            sanitize_key((string) ($row['entity_kind'] ?? ''))
        );
    }

    return implode(',', $items);
}

function iss_graph_wpcli_check_public_object_contract(int $limit): array
{
    if (!function_exists('iss_graph_get_public_object_post_type_contracts')) {
        return [
            'checked' => 0,
            'errors' => ['Public object contract registry is unavailable.'],
        ];
    }

    $contracts = iss_graph_get_public_object_post_type_contracts();
    $contracts = array_filter($contracts, static function ($contract): bool {
        return is_array($contract)
            && !empty($contract['post_type'])
            && post_type_exists((string) $contract['post_type']);
    });

    if (!$contracts) {
        return [
            'checked' => 0,
            'errors' => [],
        ];
    }

    $service = iss_graph_get_service();
    $errors = [];
    $checked = 0;
    $post_type_owners = [];

    foreach (iss_graph_get_entity_kind_registry() as $definition) {
        $canonical_kind = sanitize_key((string) ($definition['canonical_kind'] ?? ''));
        foreach ((array) ($definition['post_types'] ?? []) as $post_type) {
            $post_type = sanitize_key((string) $post_type);
            if ($post_type !== '') {
                $post_type_owners[$post_type][] = $canonical_kind;
            }
        }
    }

    foreach ($contracts as $contract) {
        $post_type = sanitize_key((string) ($contract['post_type'] ?? ''));
        $storage_kind = sanitize_key((string) ($contract['storage_kind'] ?? ''));
        $canonical_kind = sanitize_key((string) ($contract['canonical_kind'] ?? ''));
        $contract_kind = sanitize_key((string) ($contract['contract_kind'] ?? $canonical_kind));
        $owners = array_values(array_unique(array_filter($post_type_owners[$post_type] ?? [])));

        $checked++;
        if (count($owners) !== 1 || $owners[0] !== $canonical_kind) {
            $errors[] = sprintf(
                'Public object post type %s has ambiguous entity-kind registry owners: expected %s, found [%s].',
                $post_type,
                $canonical_kind,
                implode(',', $owners)
            );
            if (count($errors) >= $limit) {
                break;
            }
        }

        $post_ids = get_posts([
            'post_type' => $post_type,
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
            'suppress_filters' => true,
            'update_post_term_cache' => false,
            'update_post_meta_cache' => false,
        ]);

        foreach (array_map('absint', is_array($post_ids) ? $post_ids : []) as $post_id) {
            if ($post_id <= 0) {
                continue;
            }

            $checked++;
            $rows = iss_graph_wpcli_get_entity_rows_for_post($post_id);
            if (count($rows) > 1) {
                $errors[] = sprintf(
                    'Public object %d [%s] has multiple post-backed graph entities: %s.',
                    $post_id,
                    $post_type,
                    iss_graph_wpcli_format_entity_row_summary($rows)
                );
                if (count($errors) >= $limit) {
                    break 2;
                }
            }

            $entity = $storage_kind !== '' ? $service->find_entity_by_post($storage_kind, $post_id) : null;
            if (!$entity) {
                $detail = $rows ? ' found ' . iss_graph_wpcli_format_entity_row_summary($rows) . ' instead.' : '';
                $errors[] = sprintf(
                    'Public object %d [%s] has no canonical graph entity for storage kind %s.%s',
                    $post_id,
                    $post_type,
                    $storage_kind,
                    $detail
                );
                if (count($errors) >= $limit) {
                    break 2;
                }
                continue;
            }

            $entity_id = (int) ($entity['id'] ?? 0);
            $actual_storage_kind = sanitize_key((string) ($entity['entity_kind'] ?? ''));
            $actual_canonical_kind = function_exists('iss_graph_get_canonical_entity_kind')
                ? iss_graph_get_canonical_entity_kind($actual_storage_kind)
                : $actual_storage_kind;

            if ($actual_storage_kind !== $storage_kind || $actual_canonical_kind !== $canonical_kind) {
                $errors[] = sprintf(
                    'Public object %d [%s] entity %d maps to %s/%s, expected %s/%s.',
                    $post_id,
                    $post_type,
                    $entity_id,
                    $actual_canonical_kind,
                    $actual_storage_kind,
                    $canonical_kind,
                    $storage_kind
                );
            }

            if ($entity_id > 0 && !iss_graph_wpcli_entity_has_identifier($entity_id, 'wp_post', (string) $post_id)) {
                $errors[] = sprintf('Public object %d [%s] entity %d is missing wp_post identifier.', $post_id, $post_type, $entity_id);
            }

            $payload = function_exists('iss_graph_get_entity_contract_payload')
                ? iss_graph_get_entity_contract_payload($entity)
                : [];
            $actual_contract_kind = sanitize_key((string) ($payload['kind'] ?? $actual_canonical_kind));
            if ($actual_contract_kind !== $contract_kind) {
                $errors[] = sprintf(
                    'Public object %d [%s] entity %d has contract kind %s, expected %s.',
                    $post_id,
                    $post_type,
                    $entity_id,
                    $actual_contract_kind,
                    $contract_kind
                );
            }

            if (!empty($contract['requires_subtype'])) {
                $subtype = sanitize_key((string) ($payload['subtype'] ?? ''));
                if ($subtype === '' || !iss_graph_get_offer_subtype_definition($subtype)) {
                    $errors[] = sprintf(
                        'Public object %d [%s] entity %d has missing or unknown offer subtype %s.',
                        $post_id,
                        $post_type,
                        $entity_id,
                        $subtype !== '' ? $subtype : '(empty)'
                    );
                }
            }

            if (count($errors) >= $limit) {
                break 2;
            }
        }
    }

    return [
        'checked' => $checked,
        'errors' => array_slice($errors, 0, $limit),
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
        "SELECT s.id, s.context_post_id, s.target_post_id, s.surface, s.signal_type, s.reason, s.expires_at, s.author_user_id, s.status,
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
        $reason = trim((string) ($row['reason'] ?? ''));
        $expires_at = isset($row['expires_at']) ? (string) $row['expires_at'] : '';
        $author_user_id = absint($row['author_user_id'] ?? 0);

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

        if ($status === 'active') {
            if ($author_user_id <= 0) {
                $errors[] = sprintf('Editorial signal %d has no author user.', $id);
            } elseif (!get_userdata($author_user_id)) {
                $errors[] = sprintf('Editorial signal %d references missing author user %d.', $id, $author_user_id);
            }
        }

        if (
            $status === 'active'
            && $signal !== ''
            && $service->editorial_signal_requires_metadata($surface, $signal)
        ) {
            if ($reason === '') {
                $errors[] = sprintf('Editorial signal %d has no reason.', $id);
            }

            if ($expires_at === '' || $expires_at === '0000-00-00 00:00:00' || $service->normalize_editorial_signal_expiry($expires_at) === null) {
                $errors[] = sprintf('Editorial signal %d has no valid expiry.', $id);
            }
        }

        if (
            $context_post_id > 0
            && $target_post_id > 0
            && function_exists('iss_graph_editorial_signal_target_is_allowed_for_surface')
            && !iss_graph_editorial_signal_target_is_allowed_for_surface($context_post_id, $target_post_id, $surface)
        ) {
            $errors[] = sprintf('Editorial signal %d targets post %d outside the allowed target types for context post %d.', $id, $target_post_id, $context_post_id);
        }

        if ($context_post_id > 0 && $target_post_id > 0 && $context_post_id === $target_post_id) {
            $post_type = get_post_type($target_post_id);
            if (
                $surface === 'related'
                && (!is_string($post_type) || !function_exists('iss_graph_is_related_promotion_post_type') || !iss_graph_is_related_promotion_post_type($post_type))
            ) {
                $errors[] = sprintf('Editorial signal %d is a self-promotion on unsupported post %d.', $id, $target_post_id);
            }

            if (
                $surface === 'search'
                && (!is_string($post_type) || !function_exists('iss_graph_is_search_signal_post_type') || !iss_graph_is_search_signal_post_type($post_type))
            ) {
                $errors[] = sprintf('Editorial signal %d is a search signal on unsupported post %d.', $id, $target_post_id);
            }

            if (
                $surface === 'availability'
                && (!is_string($post_type) || !function_exists('iss_graph_is_availability_signal_post_type') || !iss_graph_is_availability_signal_post_type($post_type))
            ) {
                $errors[] = sprintf('Editorial signal %d is an availability signal on unsupported post %d.', $id, $target_post_id);
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
    $dry_run = !empty($assoc_args['dry-run']);
    $sample_limit = max(1, min(500, absint($assoc_args['limit'] ?? 25)));

    if ($entity_id > 0) {
        if ($dry_run) {
            $preview = iss_graph_preview_entity_alias_backfill($entity_id);
            if (!$preview) {
                WP_CLI::error(sprintf('Graph entity %d could not be previewed.', $entity_id));
            }

            iss_graph_wpcli_log_alias_backfill_preview($preview);
            WP_CLI::success(sprintf('Alias backfill dry-run completed for graph entity %d. No changes made.', $entity_id));
            return;
        }

        $count = iss_graph_sync_entity_alias_backfill($entity_id);
        WP_CLI::success(sprintf('Synced %d generated alias name(s) for graph entity %d.', $count, $entity_id));
        return;
    }

    if ($dry_run) {
        $stats = iss_graph_audit_entity_alias_backfill(['sample_limit' => $sample_limit]);
        WP_CLI::log(sprintf(
            'Alias backfill dry-run: entities=%d changed_entities=%d current_names=%d proposed_names=%d removed_names=%d added_names=%d.',
            (int) ($stats['entities'] ?? 0),
            (int) ($stats['changed_entities'] ?? 0),
            (int) ($stats['current_names'] ?? 0),
            (int) ($stats['proposed_names'] ?? 0),
            (int) ($stats['removed_names'] ?? 0),
            (int) ($stats['added_names'] ?? 0)
        ));

        foreach ((array) ($stats['samples'] ?? []) as $preview) {
            if (is_array($preview)) {
                iss_graph_wpcli_log_alias_backfill_preview($preview);
            }
        }

        WP_CLI::success('Alias backfill dry-run completed. No changes made.');
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

function iss_graph_wpcli_log_alias_backfill_preview(array $preview): void
{
    WP_CLI::log(sprintf(
        '[alias-preview] entity=%d kind=%s current=%d proposed=%d removed=%d added=%d title="%s"',
        (int) ($preview['entity_id'] ?? 0),
        (string) ($preview['entity_kind'] ?? ''),
        (int) ($preview['current_count'] ?? 0),
        (int) ($preview['proposed_count'] ?? 0),
        (int) ($preview['removed_count'] ?? 0),
        (int) ($preview['added_count'] ?? 0),
        iss_graph_wpcli_entity_hygiene_clip((string) ($preview['title'] ?? ''), 120)
    ));

    $removed = array_slice((array) ($preview['removed'] ?? []), 0, 8);
    if ($removed) {
        WP_CLI::log('  removed: ' . iss_graph_wpcli_entity_hygiene_clip(implode(' | ', array_map('strval', $removed)), 220));
    }

    $added = array_slice((array) ($preview['added'] ?? []), 0, 8);
    if ($added) {
        WP_CLI::log('  added: ' . iss_graph_wpcli_entity_hygiene_clip(implode(' | ', array_map('strval', $added)), 220));
    }
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
