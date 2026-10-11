<?php

if (!defined('ABSPATH')) { exit; }

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Existing CLI verification reads owner graph tables.

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
