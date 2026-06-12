<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_graph_get_entity_name_type_options(): array
{
    return [
        'alternative' => __('Alternativer Name', 'iss-graph'),
        'alias' => __('Alias', 'iss-graph'),
        'abbreviation' => __('Abkürzung', 'iss-graph'),
        'transliteration' => __('Schreibvariante', 'iss-graph'),
        'source_label' => __('Quellenname', 'iss-graph'),
        'historical' => __('Historischer Name', 'iss-graph'),
        'official' => __('Offizieller Name', 'iss-graph'),
        'typo' => __('Bekannte Fehlschreibung', 'iss-graph'),
    ];
}

function iss_graph_get_alias_backfill_source_system(): string
{
    return 'entity_alias_backfill';
}

function iss_graph_get_profile_alias_source_system(): string
{
    return 'entity_profile_admin';
}

function iss_graph_alias_normalize_text(string $value): string
{
    $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
    $value = wp_strip_all_tags($value);
    $value = preg_replace('/\s+/u', ' ', trim($value));

    return is_string($value) ? $value : '';
}

function iss_graph_alias_lower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function iss_graph_alias_strlen(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function iss_graph_alias_candidate_is_useful(string $name): bool
{
    $name = iss_graph_alias_normalize_text($name);
    if ($name === '') {
        return false;
    }

    if (iss_graph_alias_strlen($name) < 2 || iss_graph_alias_strlen($name) > 191) {
        return false;
    }

    return !preg_match('/^\d{3,4}(\s*[-\/]\s*\d{0,4})?$/', $name);
}

function iss_graph_alias_candidate_key(string $name): string
{
    return iss_graph_alias_lower(iss_graph_alias_normalize_text($name));
}

function iss_graph_alias_looks_like_abbreviation(string $name): bool
{
    $compact = preg_replace('/[^A-Za-z0-9]/', '', $name);
    if (!is_string($compact) || $compact === '' || strlen($compact) > 14) {
        return false;
    }

    return preg_match('/[A-Z]/', $compact) === 1 && strtoupper($compact) === $compact;
}

function iss_graph_alias_add_candidate(array &$rows, array &$seen, string $name, string $name_type): void
{
    $name = iss_graph_alias_normalize_text($name);
    if (!iss_graph_alias_candidate_is_useful($name)) {
        return;
    }

    $key = iss_graph_alias_candidate_key($name);
    if ($key === '' || isset($seen[$key])) {
        return;
    }

    $seen[$key] = true;
    $rows[] = [
        'name' => $name,
        'name_type' => sanitize_key($name_type) ?: 'alternative',
        'is_primary' => false,
        'position' => count($rows),
    ];
}

function iss_graph_alias_get_spelling_variants(string $name): array
{
    $variants = [];
    $umlaut_variant = strtr($name, [
        'Ä' => 'Ae',
        'Ö' => 'Oe',
        'Ü' => 'Ue',
        'ä' => 'ae',
        'ö' => 'oe',
        'ü' => 'ue',
        'ß' => 'ss',
    ]);

    if ($umlaut_variant !== $name) {
        $variants[] = $umlaut_variant;
    }

    $ascii_variant = function_exists('remove_accents') ? remove_accents($name) : '';
    if (is_string($ascii_variant) && $ascii_variant !== '' && $ascii_variant !== $name) {
        $variants[] = $ascii_variant;
    }

    if (preg_match('/(?:\b[A-Z]\.){2,}/', $name)) {
        $no_dots = preg_replace('/\.(?=\s*[A-Z]\.?)/', '', $name);
        if (is_string($no_dots) && $no_dots !== $name) {
            $variants[] = $no_dots;
        }
    }

    return array_values(array_unique(array_filter(array_map('iss_graph_alias_normalize_text', $variants))));
}

function iss_graph_alias_haystack_variants(string $value): array
{
    $variants = [$value];
    $variants = array_merge($variants, iss_graph_alias_get_spelling_variants($value));

    return array_values(array_unique(array_map('iss_graph_alias_lower', $variants)));
}

function iss_graph_alias_haystack_contains(array $haystack_variants, string $needle): bool
{
    $needles = iss_graph_alias_haystack_variants($needle);

    foreach ($haystack_variants as $haystack) {
        foreach ($needles as $variant) {
            if ($variant === '') {
                continue;
            }

            $compact = preg_replace('/[^a-z0-9]/', '', $variant);
            if (is_string($compact) && $compact !== '' && strlen($compact) <= 4 && $compact === $variant) {
                if (preg_match('/(?<![a-z0-9])' . preg_quote($variant, '/') . '(?![a-z0-9])/', $haystack) === 1) {
                    return true;
                }
                continue;
            }

            if (strpos($haystack, $variant) !== false) {
                return true;
            }
        }
    }

    return false;
}

function iss_graph_alias_add_title_variants(array &$rows, array &$seen, string $name): void
{
    $name = iss_graph_alias_normalize_text($name);
    if ($name === '') {
        return;
    }

    foreach (iss_graph_alias_get_spelling_variants($name) as $variant) {
        iss_graph_alias_add_candidate($rows, $seen, $variant, 'transliteration');
    }

    if (preg_match_all('/\(([^)]+)\)/u', $name, $matches)) {
        foreach ((array) ($matches[1] ?? []) as $inner_name) {
            $type = iss_graph_alias_looks_like_abbreviation((string) $inner_name) ? 'abbreviation' : 'alternative';
            iss_graph_alias_add_candidate($rows, $seen, (string) $inner_name, $type);
        }

        $without_parentheses = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $name);
        if (is_string($without_parentheses) && $without_parentheses !== $name) {
            iss_graph_alias_add_candidate($rows, $seen, $without_parentheses, 'alternative');
        }
    }

    if (strpos($name, '/') !== false) {
        $parts = preg_split('/\s*\/\s*/u', $name);
        if (is_array($parts) && count($parts) > 1 && count($parts) <= 5) {
            foreach ($parts as $part) {
                $type = iss_graph_alias_looks_like_abbreviation((string) $part) ? 'abbreviation' : 'alternative';
                iss_graph_alias_add_candidate($rows, $seen, (string) $part, $type);
            }
        }
    }

    $dash_parts = preg_split('/\s+[-–]\s+/u', $name);
    if (is_array($dash_parts) && count($dash_parts) > 1 && count($dash_parts) <= 4) {
        foreach ($dash_parts as $part) {
            $type = iss_graph_alias_looks_like_abbreviation((string) $part) ? 'abbreviation' : 'alternative';
            iss_graph_alias_add_candidate($rows, $seen, (string) $part, $type);
        }
    }
}

function iss_graph_alias_known_patterns(): array
{
    return [
        [
            'patterns' => ['werk für fernsehelektronik', 'werk fuer fernsehelektronik', 'veb wf'],
            'names' => [
                ['WF', 'abbreviation'],
                ['VEB Werk für Fernsehelektronik', 'official'],
                ['Werk fuer Fernsehelektronik', 'transliteration'],
            ],
        ],
        [
            'patterns' => ['kabelwerk oberspree', 'veb kwo'],
            'names' => [
                ['KWO', 'abbreviation'],
                ['VEB Kabelwerk Oberspree', 'official'],
            ],
        ],
        [
            'patterns' => ['transformatorenwerk oberspree', 'veb tro'],
            'names' => [
                ['TRO', 'abbreviation'],
                ['VEB Transformatorenwerk Oberspree', 'official'],
            ],
        ],
        [
            'patterns' => ['allgemeine elektricitäts-gesellschaft', 'allgemeine elektricitaets-gesellschaft', 'allgemeine elektrizitäts-gesellschaft', 'aeg'],
            'names' => [
                ['AEG', 'abbreviation'],
                ['Allgemeine Elektricitäts-Gesellschaft', 'official'],
                ['Allgemeine Elektricitaets-Gesellschaft', 'transliteration'],
            ],
        ],
        [
            'patterns' => ['nationale automobil-gesellschaft', 'nationale automobil gesellschaft', 'nag'],
            'names' => [
                ['NAG', 'abbreviation'],
                ['Nationale Automobil-Gesellschaft', 'official'],
            ],
        ],
        [
            'patterns' => ['oberspreewerk', 'osw'],
            'names' => [
                ['OSW', 'abbreviation'],
                ['Oberspreewerk', 'alternative'],
            ],
        ],
    ];
}

function iss_graph_alias_add_known_variants(array &$rows, array &$seen, array $seed_names, array $entity): void
{
    if ((string) ($entity['entity_kind'] ?? '') !== 'organization') {
        return;
    }

    $source = implode(' ', array_merge($seed_names, [
        (string) ($entity['display_title'] ?? ''),
        (string) ($entity['canonical_slug'] ?? ''),
        (string) ($entity['source_id'] ?? ''),
    ]));
    $haystack_variants = iss_graph_alias_haystack_variants($source);

    foreach (iss_graph_alias_known_patterns() as $rule) {
        $matched = false;
        foreach ((array) ($rule['patterns'] ?? []) as $pattern) {
            if (iss_graph_alias_haystack_contains($haystack_variants, (string) $pattern)) {
                $matched = true;
                break;
            }
        }

        if (!$matched) {
            continue;
        }

        foreach ((array) ($rule['names'] ?? []) as $name_row) {
            if (!is_array($name_row)) {
                continue;
            }

            iss_graph_alias_add_candidate(
                $rows,
                $seen,
                (string) ($name_row[0] ?? ''),
                (string) ($name_row[1] ?? 'alternative')
            );
        }
    }
}

function iss_graph_alias_known_organization_alias_keys(): array
{
    static $keys = null;

    if (is_array($keys)) {
        return $keys;
    }

    $keys = [];
    foreach (iss_graph_alias_known_patterns() as $rule) {
        foreach ((array) ($rule['names'] ?? []) as $name_row) {
            if (!is_array($name_row)) {
                continue;
            }

            $name = iss_graph_alias_normalize_text((string) ($name_row[0] ?? ''));
            $key = sanitize_title($name);
            if ($key !== '') {
                $keys[$key] = true;
            }
        }
    }

    return $keys;
}

function iss_graph_alias_row_is_known_organization_alias(array $row): bool
{
    $name = iss_graph_alias_normalize_text((string) ($row['name'] ?? ''));
    $key = sanitize_title($name);
    if ($key === '') {
        return false;
    }

    return isset(iss_graph_alias_known_organization_alias_keys()[$key]);
}

function iss_graph_filter_entity_alias_rows(array $rows, array $entity): array
{
    if ((string) ($entity['entity_kind'] ?? '') === 'organization') {
        return array_values($rows);
    }

    return array_values(array_filter($rows, static function (array $row): bool {
        return !iss_graph_alias_row_is_known_organization_alias($row);
    }));
}

function iss_graph_build_entity_alias_rows(array $entity): array
{
    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0) {
        return [];
    }

    $source_system = iss_graph_get_alias_backfill_source_system();
    $seed_names = [];
    $seen = [];

    $display_title = iss_graph_alias_normalize_text((string) ($entity['display_title'] ?? ''));
    if ($display_title !== '') {
        $seed_names[] = $display_title;
        $seen[iss_graph_alias_candidate_key($display_title)] = true;
    }

    foreach (iss_graph_get_service()->get_names_for_entity($entity_id, ['limit' => 500]) as $row) {
        if (!is_array($row) || (string) ($row['source_system'] ?? '') === $source_system) {
            continue;
        }

        $name = iss_graph_alias_normalize_text((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $seed_names[] = $name;
        $seen[iss_graph_alias_candidate_key($name)] = true;
    }

    $seed_names = array_values(array_unique($seed_names));
    $rows = [];

    foreach ($seed_names as $name) {
        iss_graph_alias_add_title_variants($rows, $seen, $name);
    }

    iss_graph_alias_add_known_variants($rows, $seen, $seed_names, $entity);
    $rows = iss_graph_filter_entity_alias_rows($rows, $entity);

    foreach ($rows as $index => &$row) {
        $row['position'] = $index;
    }
    unset($row);

    return $rows;
}

function iss_graph_sync_entity_alias_backfill(int $entity_id): int
{
    $entity_id = absint($entity_id);
    if ($entity_id <= 0) {
        return 0;
    }

    $service = iss_graph_get_service();
    $entity = $service->get_entity_by_id($entity_id);
    if (!$entity) {
        return 0;
    }

    $rows = iss_graph_build_entity_alias_rows($entity);
    $service->replace_entity_names_for_source(
        $entity_id,
        iss_graph_get_alias_backfill_source_system(),
        $rows,
        'entity:' . $entity_id
    );

    return count($rows);
}

function iss_graph_alias_compare_key(array $row): string
{
    $name = iss_graph_alias_normalize_text((string) ($row['name'] ?? ''));
    $normalized_name = sanitize_title($name);
    $name_type = sanitize_key((string) ($row['name_type'] ?? 'alternative'));

    return $normalized_name . '|' . $name_type;
}

function iss_graph_alias_compare_label(array $row): string
{
    $name = iss_graph_alias_normalize_text((string) ($row['name'] ?? ''));
    $name_type = sanitize_key((string) ($row['name_type'] ?? 'alternative'));

    return $name_type . ':' . $name;
}

function iss_graph_get_current_alias_backfill_rows(int $entity_id): array
{
    if ($entity_id <= 0) {
        return [];
    }

    return iss_graph_get_service()->get_names_for_entity($entity_id, [
        'source_system' => iss_graph_get_alias_backfill_source_system(),
        'limit' => 500,
    ]);
}

function iss_graph_preview_entity_alias_backfill(int $entity_id): array
{
    $entity_id = absint($entity_id);
    if ($entity_id <= 0) {
        return [];
    }

    $service = iss_graph_get_service();
    $entity = $service->get_entity_by_id($entity_id);
    if (!$entity) {
        return [];
    }

    $current_rows = iss_graph_get_current_alias_backfill_rows($entity_id);
    $proposed_rows = iss_graph_build_entity_alias_rows($entity);
    $current = [];
    $proposed = [];

    foreach ($current_rows as $row) {
        $key = iss_graph_alias_compare_key($row);
        if ($key !== '|') {
            $current[$key] = iss_graph_alias_compare_label($row);
        }
    }

    foreach ($proposed_rows as $row) {
        $key = iss_graph_alias_compare_key($row);
        if ($key !== '|') {
            $proposed[$key] = iss_graph_alias_compare_label($row);
        }
    }

    $removed = array_values(array_diff_key($current, $proposed));
    $added = array_values(array_diff_key($proposed, $current));

    return [
        'entity_id' => $entity_id,
        'entity_kind' => (string) ($entity['entity_kind'] ?? ''),
        'title' => (string) ($entity['display_title'] ?? ''),
        'current_count' => count($current),
        'proposed_count' => count($proposed),
        'removed_count' => count($removed),
        'added_count' => count($added),
        'removed' => $removed,
        'added' => $added,
    ];
}

function iss_graph_audit_entity_alias_backfill(array $args = []): array
{
    $sample_limit = isset($args['sample_limit']) ? max(1, min(500, (int) $args['sample_limit'])) : 25;
    $entities = iss_graph_get_service()->get_entities([
        'limit' => 50000,
        'orderby' => 'id',
        'order' => 'ASC',
    ]);

    $stats = [
        'entities' => 0,
        'changed_entities' => 0,
        'current_names' => 0,
        'proposed_names' => 0,
        'removed_names' => 0,
        'added_names' => 0,
        'samples' => [],
    ];

    foreach ($entities as $entity) {
        if (!is_array($entity) || empty($entity['id'])) {
            continue;
        }

        $stats['entities']++;
        $preview = iss_graph_preview_entity_alias_backfill((int) $entity['id']);
        if (!$preview) {
            continue;
        }

        $stats['current_names'] += (int) ($preview['current_count'] ?? 0);
        $stats['proposed_names'] += (int) ($preview['proposed_count'] ?? 0);
        $stats['removed_names'] += (int) ($preview['removed_count'] ?? 0);
        $stats['added_names'] += (int) ($preview['added_count'] ?? 0);

        if ((int) ($preview['removed_count'] ?? 0) <= 0 && (int) ($preview['added_count'] ?? 0) <= 0) {
            continue;
        }

        $stats['changed_entities']++;
        if (count($stats['samples']) < $sample_limit) {
            $stats['samples'][] = $preview;
        }
    }

    return $stats;
}

function iss_graph_backfill_entity_aliases(): array
{
    $entities = iss_graph_get_service()->get_entities([
        'limit' => 50000,
        'orderby' => 'id',
        'order' => 'ASC',
    ]);

    $stats = [
        'entities' => 0,
        'with_aliases' => 0,
        'names' => 0,
    ];

    foreach ($entities as $entity) {
        if (!is_array($entity) || empty($entity['id'])) {
            continue;
        }

        $stats['entities']++;
        $row_count = iss_graph_sync_entity_alias_backfill((int) $entity['id']);
        if ($row_count > 0) {
            $stats['with_aliases']++;
            $stats['names'] += $row_count;
        }
    }

    return $stats;
}

function iss_graph_maybe_backfill_entity_aliases(): void
{
    $stored = (string) get_option(ISS_GRAPH_ALIAS_BACKFILL_OPTION, '');
    if ($stored === ISS_GRAPH_ALIAS_BACKFILL_VERSION) {
        return;
    }

    iss_graph_backfill_entity_aliases();
    update_option(ISS_GRAPH_ALIAS_BACKFILL_OPTION, ISS_GRAPH_ALIAS_BACKFILL_VERSION, false);
}

function iss_graph_sanitize_entity_name_rows(array $rows, array $allowed_types = []): array
{
    $service = iss_graph_get_service();
    if (!$allowed_types) {
        $allowed_types = iss_graph_get_entity_name_type_options();
    }

    $allowed = array_fill_keys(array_keys($allowed_types), true);
    $sanitized = [];

    foreach (array_values($rows) as $index => $row) {
        if (!is_array($row)) {
            continue;
        }

        $name = sanitize_text_field((string) ($row['name'] ?? ''));
        if ($name === '') {
            continue;
        }

        $name_type = sanitize_key((string) ($row['name_type'] ?? 'alternative'));
        if (!isset($allowed[$name_type])) {
            $name_type = 'alternative';
        }

        $sanitized[] = [
            'name' => $name,
            'name_type' => $name_type,
            'valid_from_year' => $service->normalize_year($row['valid_from_year'] ?? null),
            'valid_to_year' => $service->normalize_year($row['valid_to_year'] ?? null),
            'position' => $index,
        ];
    }

    return $sanitized;
}
