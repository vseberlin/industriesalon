<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_atlas_compact_text(string $text, int $limit): string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return '';
    }

    $length = function_exists('mb_strlen')
        ? mb_strlen($text)
        : strlen($text);

    if ($length <= $limit) {
        return $text;
    }

    $slice = function_exists('mb_substr')
        ? (string) mb_substr($text, 0, $limit)
        : substr($text, 0, $limit);

    $slice = preg_replace('/\s+\S*$/u', '', $slice);

    return rtrim((string) $slice, " \t\n\r\0\x0B.,;:") . '…';
}

function iss_register_atlas_extract_years(string $text): array
{
    if (!preg_match_all('/\b(18\d{2}|19\d{2}|20\d{2})\b/u', $text, $matches)) {
        return [];
    }

    $years = [];

    foreach ($matches[1] as $match) {
        $year = (int) $match;
        if (!in_array($year, $years, true)) {
            $years[] = $year;
        }
    }

    return $years;
}

function iss_register_get_atlas_eras(): array
{
    $eras = [];

    foreach (iss_register_get_atlas_era_definitions() as $definition) {
        $legacy_id = (string) ($definition['legacy_id'] ?? '');
        if ($legacy_id === '') {
            continue;
        }

        $eras[$legacy_id] = [
            'id' => $legacy_id,
            'label' => (string) ($definition['legacy_label'] ?? $legacy_id),
            'short_label' => (string) ($definition['legacy_short_label'] ?? $legacy_id),
            'caption' => (string) ($definition['legacy_caption'] ?? ''),
        ];
    }

    return $eras;
}

function iss_register_get_atlas_current_status_definitions(): array
{
    return [
        'in_use' => [
            'key' => 'in_use',
            'label' => 'In Nutzung',
            'caption' => 'Aktuell genutzt',
        ],
        'vacant' => [
            'key' => 'vacant',
            'label' => 'Leerstand',
            'caption' => 'Der Ort steht aktuell leer',
        ],
        'temporary_use' => [
            'key' => 'temporary_use',
            'label' => 'Zwischennutzung',
            'caption' => 'Temporär oder provisorisch genutzt',
        ],
        'in_transition' => [
            'key' => 'in_transition',
            'label' => 'Im Umbau',
            'caption' => 'Umbau, Entwicklung oder Abzug',
        ],
        'planned' => [
            'key' => 'planned',
            'label' => 'Projekt / Planung',
            'caption' => 'Geplante oder noch nicht umgesetzte Nutzung',
        ],
        'for_sale' => [
            'key' => 'for_sale',
            'label' => 'Verkauf / offen',
            'caption' => 'Verkauf oder offene Perspektive',
        ],
        'unclear' => [
            'key' => 'unclear',
            'label' => 'Unklar',
            'caption' => 'Aktuelle Situation nicht gesichert',
        ],
    ];
}

function iss_register_get_atlas_current_use_type_definitions(): array
{
    return [
        'culture' => [
            'key' => 'culture',
            'label' => 'Kultur',
        ],
        'education' => [
            'key' => 'education',
            'label' => 'Bildung / Forschung',
        ],
        'commercial' => [
            'key' => 'commercial',
            'label' => 'Gewerbe / Bueros',
        ],
        'industrial' => [
            'key' => 'industrial',
            'label' => 'Produktion',
        ],
        'residential' => [
            'key' => 'residential',
            'label' => 'Wohnen',
        ],
        'administration' => [
            'key' => 'administration',
            'label' => 'Verwaltung',
        ],
        'community' => [
            'key' => 'community',
            'label' => 'Gemeinwohl / Soziales',
        ],
        'mixed' => [
            'key' => 'mixed',
            'label' => 'Mischnutzung',
        ],
    ];
}

function iss_register_get_atlas_current_status_payload(string $key): array
{
    $definitions = iss_register_get_atlas_current_status_definitions();
    $key = sanitize_key($key);

    return $definitions[$key] ?? [];
}

function iss_register_get_atlas_current_use_type_payload(string $key): array
{
    $definitions = iss_register_get_atlas_current_use_type_definitions();
    $key = sanitize_key($key);

    return $definitions[$key] ?? [];
}

function iss_register_detect_current_status(array $place): array
{
    $explicit = sanitize_key((string) ($place['current_status'] ?? ''));
    if ($explicit !== '') {
        $payload = iss_register_get_atlas_current_status_payload($explicit);
        if ($payload) {
            return $payload + ['source' => 'meta'];
        }
    }

    $legacy_status = strtolower(trim((string) ($place['status'] ?? '')));
    $current = strtolower(trim((string) ($place['current'] ?? '')));

    $legacy_map = [
        'aktiv' => 'in_use',
        'mieter' => 'in_use',
        'entwicklung' => 'in_transition',
        'abzug' => 'in_transition',
        'geplant' => 'planned',
        'sucht' => 'planned',
        'unklar' => 'unclear',
    ];

    if (isset($legacy_map[$legacy_status])) {
        $payload = iss_register_get_atlas_current_status_payload($legacy_map[$legacy_status]);
        if ($payload) {
            return $payload + ['source' => 'legacy_status'];
        }
    }

    if (preg_match('/leerstand|steht\s+leer|ungenutzt|brach|verlassen|stillgelegt/u', $current)) {
        return iss_register_get_atlas_current_status_payload('vacant') + ['source' => 'inferred'];
    }

    if (preg_match('/zwischennutz|tempor[aä]r|interim/u', $current)) {
        return iss_register_get_atlas_current_status_payload('temporary_use') + ['source' => 'inferred'];
    }

    if (preg_match('/verkauf|zu verkaufen|investorensuche|offene perspektive|ungewiss/u', $current)) {
        return iss_register_get_atlas_current_status_payload('for_sale') + ['source' => 'inferred'];
    }

    if (preg_match('/umbau|sanierung|entwicklung|transformation|revitalisierung/u', $current)) {
        return iss_register_get_atlas_current_status_payload('in_transition') + ['source' => 'inferred'];
    }

    if (preg_match('/planung|projekt|vorgesehen|soll|geplant/u', $current) || strtoupper(trim((string) ($place['role'] ?? ''))) === 'P') {
        return iss_register_get_atlas_current_status_payload('planned') + ['source' => 'inferred'];
    }

    if ($current !== '') {
        return iss_register_get_atlas_current_status_payload('in_use') + ['source' => 'inferred'];
    }

    return iss_register_get_atlas_current_status_payload('unclear') + ['source' => 'fallback'];
}

function iss_register_get_normalized_current_status_payload(array $place): array
{
    $normalized_key = sanitize_key((string) ($place['normalized_current_status'] ?? ''));
    if ($normalized_key === '') {
        return iss_register_detect_current_status($place);
    }

    $payload = iss_register_get_atlas_current_status_payload($normalized_key);
    if (!$payload) {
        return iss_register_detect_current_status($place);
    }

    $label = trim((string) ($place['normalized_current_status_label'] ?? ''));
    $caption = trim((string) ($place['normalized_current_status_caption'] ?? ''));
    $source = trim((string) ($place['normalized_current_status_source'] ?? ''));

    if ($label !== '') {
        $payload['label'] = $label;
    }

    if ($caption !== '') {
        $payload['caption'] = $caption;
    }

    return $payload + ['source' => $source !== '' ? $source : 'meta'];
}

function iss_register_detect_current_use_type(array $place): array
{
    $explicit = sanitize_key((string) ($place['current_use_type'] ?? ''));
    if ($explicit !== '') {
        $payload = iss_register_get_atlas_current_use_type_payload($explicit);
        if ($payload) {
            return $payload + ['source' => 'meta'];
        }
    }

    $current = strtolower(trim(implode(' ', array_filter([
        (string) ($place['current'] ?? ''),
        is_array($place['tags'] ?? null) ? implode(' ', (array) $place['tags']) : '',
    ]))));

    $patterns = [
        'culture' => '/kultur|museum|kunst|atelier|ausstellung|veranstaltung|club|theater|musik/u',
        'education' => '/schule|hochschule|universit[aä]t|bildung|forschung|labor|campus/u',
        'commercial' => '/gewerbe|b[uü]ro|handel|dienstleistung|firma|agentur/u',
        'industrial' => '/produktion|fertigung|werkstatt|industrie|werk\b/u',
        'residential' => '/wohnen|wohnhaus|wohnanlage|wohnungen|apartments?/u',
        'administration' => '/verwaltung|amt|beh[oö]rde/u',
        'community' => '/verein|jugend|nachbarschaft|gemeinwohl|sozial|community/u',
        'mixed' => '/mischnutzung|gemischt/u',
    ];

    foreach ($patterns as $key => $pattern) {
        if (preg_match($pattern, $current)) {
            return iss_register_get_atlas_current_use_type_payload($key) + ['source' => 'inferred'];
        }
    }

    return [];
}

function iss_register_build_present_label(array $current_status, array $current_use_type): string
{
    $status_label = trim((string) ($current_status['label'] ?? ''));
    $status_key = sanitize_key((string) ($current_status['key'] ?? ''));
    $type_label = trim((string) ($current_use_type['label'] ?? ''));

    if ($type_label !== '' && in_array($status_key, ['in_use'], true)) {
        return $type_label;
    }

    if ($status_label === '') {
        return $type_label;
    }

    if ($type_label === '') {
        return $status_label;
    }

    return $status_label . ' · ' . $type_label;
}

function iss_register_infer_atlas_era_from_place(array $place): array
{
    $eras = iss_register_get_atlas_eras();
    $narrative = trim(implode(' ', array_filter([
        (string) ($place['history'] ?? ''),
        (string) ($place['current'] ?? ''),
        (string) ($place['excerpt'] ?? ''),
        (string) ($place['sources'] ?? ''),
    ])));
    $years = iss_register_atlas_extract_years($narrative);
    $first_year = $years ? min($years) : null;

    if ($first_year !== null) {
        if ($first_year < 1910) {
            return $eras['1890-1910'];
        }
        if ($first_year < 1930) {
            return $eras['1910-1930'];
        }
        if ($first_year < 1945) {
            return $eras['1930-1945'];
        }
        if ($first_year < 1960) {
            return $eras['1945-1960'];
        }
        if ($first_year < 1990) {
            return $eras['1960-1990'];
        }

        return $eras['heute'];
    }

    $status = strtolower(trim((string) ($place['status'] ?? '')));
    $role = strtoupper(trim((string) ($place['role'] ?? '')));

    if ($status === 'entwicklung' || $status === 'geplant' || $role === 'P') {
        return $eras['heute'];
    }

    if (preg_match('/ddr|kombinat|v[eé]b|sozial/i', $narrative)) {
        return $eras['1960-1990'];
    }

    return $eras['heute'];
}

function iss_register_detect_atlas_era(array $place): array
{
    $epochs = isset($place['epochs']) && is_array($place['epochs']) ? array_values($place['epochs']) : [];
    if ($epochs) {
        $primary_slug = sanitize_title((string) ($place['primary_historical_era_slug'] ?? ($epochs[0]['era_slug'] ?? '')));
        $primary = $primary_slug !== '' ? iss_register_get_editorial_era_payload_from_slug($primary_slug) : [];

        if ($primary) {
            return [
                'id' => (string) $primary['legacy_id'],
                'label' => (string) $primary['legacy_label'],
                'short_label' => (string) $primary['legacy_short_label'],
                'caption' => (string) $primary['legacy_caption'],
                'slug' => (string) $primary['slug'],
                'name' => (string) $primary['name'],
                'source' => 'epochs',
                'explicit_eras' => array_values(array_filter(array_map(static function (string $slug): array {
                    return iss_register_get_editorial_era_payload_from_slug($slug);
                }, (array) ($place['available_era_slugs'] ?? [])))),
            ];
        }
    }

    $post_id = isset($place['post_id']) ? (int) $place['post_id'] : 0;
    $explicit = $post_id > 0 ? iss_register_get_primary_explicit_era_payload_for_post($post_id) : [];

    if ($explicit) {
        return [
            'id' => (string) $explicit['legacy_id'],
            'label' => (string) $explicit['legacy_label'],
            'short_label' => (string) $explicit['legacy_short_label'],
            'caption' => (string) $explicit['legacy_caption'],
            'slug' => (string) $explicit['slug'],
            'name' => (string) $explicit['name'],
            'source' => 'taxonomy',
            'explicit_eras' => $post_id > 0 ? iss_register_get_explicit_era_payloads_for_post($post_id) : [],
        ];
    }

    $inferred = iss_register_infer_atlas_era_from_place($place);
    $slug = iss_register_map_legacy_era_to_editorial_slug((string) ($inferred['id'] ?? ''));
    $editorial = $slug !== '' ? iss_register_get_editorial_era_payload_from_slug($slug) : [];

    return [
        'id' => (string) ($inferred['id'] ?? ''),
        'label' => (string) ($inferred['label'] ?? ''),
        'short_label' => (string) ($inferred['short_label'] ?? ''),
        'caption' => (string) ($inferred['caption'] ?? ''),
        'slug' => (string) ($editorial['slug'] ?? $slug),
        'name' => (string) ($editorial['name'] ?? ($inferred['label'] ?? '')),
        'source' => 'inferred',
        'explicit_eras' => [],
    ];
}

function iss_register_get_atlas_place_score(array $place): int
{
    $score = 0;
    $history = (string) ($place['history'] ?? '');
    $excerpt = (string) ($place['excerpt'] ?? '');
    $current = (string) ($place['current'] ?? '');

    $score += min(strlen($history), 600);
    $score += (int) round(min(strlen($excerpt), 220) * 1.2);
    $score += (int) round(min(strlen($current), 220) * 0.8);
    $score += !empty($place['featured_image_url']) ? 120 : 0;
    $score += !empty($place['area']) ? 30 : 0;

    if (strtoupper((string) ($place['role'] ?? '')) === 'E+P') {
        $score += 40;
    }

    return $score;
}

function iss_register_get_related_publications_by_place(array $place_post_ids, int $limit_per_place = 2): array
{
    $place_post_ids = array_values(array_filter(array_map('absint', $place_post_ids)));
    if (!$place_post_ids) {
        return [];
    }

    if (
        !defined('ISS_RELATIONS_TAXONOMY')
        || !taxonomy_exists(ISS_RELATIONS_TAXONOMY)
        || !post_type_exists('publication')
        || !function_exists('iss_relations_get_place_term_id')
    ) {
        return [];
    }

    $limit_per_place = max(1, min(3, $limit_per_place));
    $term_to_place = [];

    foreach ($place_post_ids as $place_post_id) {
        $term_id = (int) iss_relations_get_place_term_id((int) $place_post_id);
        if ($term_id <= 0) {
            continue;
        }

        $term_to_place[$term_id] = (int) $place_post_id;
    }

    if (!$term_to_place) {
        return [];
    }

    $posts = get_posts([
        'post_type' => 'publication',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
        'tax_query' => [[
            'taxonomy' => ISS_RELATIONS_TAXONOMY,
            'field' => 'term_id',
            'terms' => array_keys($term_to_place),
            'operator' => 'IN',
        ]],
    ]);

    if (!$posts) {
        return [];
    }

    $result = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $post_id = (int) $post->ID;
        $term_ids = wp_get_object_terms($post_id, ISS_RELATIONS_TAXONOMY, ['fields' => 'ids']);
        if (is_wp_error($term_ids) || !is_array($term_ids) || !$term_ids) {
            continue;
        }

        foreach ($term_ids as $term_id) {
            $term_id = (int) $term_id;
            if ($term_id <= 0 || !isset($term_to_place[$term_id])) {
                continue;
            }

            $place_post_id = (int) $term_to_place[$term_id];
            if (!isset($result[$place_post_id])) {
                $result[$place_post_id] = [];
            }

            if (count($result[$place_post_id]) >= $limit_per_place) {
                continue;
            }

            $already_present = false;
            foreach ($result[$place_post_id] as $item) {
                if ((int) ($item['id'] ?? 0) === $post_id) {
                    $already_present = true;
                    break;
                }
            }
            if ($already_present) {
                continue;
            }

            $result[$place_post_id][] = [
                'id' => $post_id,
                'title' => get_the_title($post),
                'permalink' => (string) get_permalink($post),
            ];
        }
    }

    return $result;
}

function iss_register_build_atlas_place_contract(array $place, array $actor_relations_by_place = [], array $related_publications_by_place = []): array
{
    $lat = isset($place['lat']) ? (float) $place['lat'] : 0.0;
    $lng = isset($place['lng']) ? (float) $place['lng'] : 0.0;

    if ($lat === 0.0 || $lng === 0.0) {
        return [];
    }

    if (empty($place['post_id']) || empty($place['permalink'])) {
        return [];
    }

    $era = iss_register_detect_atlas_era($place);
    $excerpt = (string) ($place['excerpt'] ?? '');
    $history = (string) ($place['history'] ?? '');
    $current = (string) ($place['current'] ?? '');
    $sources = (string) ($place['sources'] ?? '');
    $branche = (string) ($place['branche'] ?? '');
    $archive_images = isset($place['archive_images']) && is_array($place['archive_images']) ? array_values($place['archive_images']) : [];
    $primary_archive_image = $archive_images[0] ?? [];
    $archive_image_url = is_array($primary_archive_image) ? esc_url_raw((string) ($primary_archive_image['url'] ?? '')) : '';
    $related_tours = iss_register_get_place_tour_usage((int) ($place['post_id'] ?? 0));
    $current_status = iss_register_get_normalized_current_status_payload($place);
    $current_use_type = iss_register_detect_current_use_type($place);
    $present_label = iss_register_build_present_label($current_status, $current_use_type);
    $post_id = (int) ($place['post_id'] ?? 0);
    $actor_relations = isset($actor_relations_by_place[$post_id]) && is_array($actor_relations_by_place[$post_id])
        ? array_values($actor_relations_by_place[$post_id])
        : [];
    $industry_actor_keys = array_values(array_unique(array_filter(array_map(static function (array $relation): string {
        return sanitize_key((string) ($relation['actor_key'] ?? ''));
    }, $actor_relations))));
    $industry_actor_labels = array_values(array_unique(array_filter(array_map(static function (array $relation): string {
        return trim((string) ($relation['actor_label'] ?? ''));
    }, $actor_relations))));
    $related_publications = isset($related_publications_by_place[$post_id]) && is_array($related_publications_by_place[$post_id])
        ? array_values($related_publications_by_place[$post_id])
        : [];
    $epoch_summaries = array_values(array_map(static function (array $epoch): array {
        return [
            'id' => (int) ($epoch['id'] ?? 0),
            'era_slug' => (string) ($epoch['era_slug'] ?? ''),
            'function_key' => (string) ($epoch['function_key'] ?? ''),
            'phase_name' => (string) ($epoch['phase_name'] ?? ''),
            'summary' => iss_register_atlas_compact_text((string) ($epoch['summary'] ?? ''), 180),
            'start_year' => isset($epoch['start_year']) ? $epoch['start_year'] : null,
            'end_year' => isset($epoch['end_year']) ? $epoch['end_year'] : null,
            'is_current' => !empty($epoch['is_current']),
        ];
    }, (array) ($place['epochs'] ?? [])));

    return [
        'id' => (string) ($place['id'] ?? ''),
        'post_id' => (int) ($place['post_id'] ?? 0),
        'slug' => (string) ($place['slug'] ?? ''),
        'name' => (string) ($place['name'] ?? ''),
        'excerpt' => $excerpt,
        'permalink' => (string) ($place['permalink'] ?? ''),
        'featured_image_url' => (string) ($place['featured_image_url'] ?? ''),
        'archive_image_url' => $archive_image_url,
        'role' => (string) ($place['role'] ?? ''),
        'area' => (string) ($place['area'] ?? ''),
        'address' => (string) ($place['address'] ?? ''),
        'status' => (string) ($place['status'] ?? ''),
        'current_status' => (string) ($current_status['key'] ?? ''),
        'current_status_label' => (string) ($current_status['label'] ?? ''),
        'current_status_caption' => (string) ($current_status['caption'] ?? ''),
        'current_status_source' => (string) ($current_status['source'] ?? ''),
        'current_use_type' => (string) ($current_use_type['key'] ?? ''),
        'current_use_type_label' => (string) ($current_use_type['label'] ?? ''),
        'current_use_type_source' => (string) ($current_use_type['source'] ?? ''),
        'present_label' => $present_label,
        'industry_actor_keys' => $industry_actor_keys,
        'industry_actor_labels' => $industry_actor_labels,
        'industry_actor_relations' => $actor_relations,
        'related_publications' => $related_publications,
        'color' => (string) ($place['color'] ?? ''),
        'branche' => $branche,
        'lat' => $lat,
        'lng' => $lng,
        'era_id' => (string) $era['id'],
        'era_label' => (string) $era['label'],
        'era_short_label' => (string) $era['short_label'],
        'era_caption' => (string) $era['caption'],
        'era_slug' => (string) ($era['slug'] ?? ''),
        'era_name' => (string) ($era['name'] ?? ''),
        'era_source' => (string) ($era['source'] ?? 'inferred'),
        'explicit_era_slugs' => array_values(array_map(static function (array $item): string {
            return (string) ($item['slug'] ?? '');
        }, (array) ($era['explicit_eras'] ?? []))),
        'explicit_era_names' => array_values(array_map(static function (array $item): string {
            return (string) ($item['name'] ?? '');
        }, (array) ($era['explicit_eras'] ?? []))),
        'has_epochs' => !empty($place['has_epochs']),
        'epoch_summaries' => $epoch_summaries,
        'has_tour_usage' => !empty($related_tours),
        'related_tours' => $related_tours,
        'story_score' => iss_register_get_atlas_place_score($place),
        'summary' => iss_register_atlas_compact_text($excerpt !== '' ? $excerpt : ($current !== '' ? $current : ($history !== '' ? $history : (string) ($place['address'] ?? ''))), 260),
        'secondary' => iss_register_atlas_compact_text($history !== '' ? $history : ($current !== '' ? $current : $sources), 180),
        'archive_summary' => iss_register_atlas_compact_text($history !== '' ? $history : $excerpt, 140),
        'current_summary' => iss_register_atlas_compact_text($current !== '' ? $current : $excerpt, 140),
        'note_text' => iss_register_atlas_compact_text($sources !== '' ? $sources : ($current !== '' ? $current : $history), 120),
        'profile' => iss_register_atlas_compact_text($branche !== '' ? $branche : $current, 80),
    ];
}

function iss_register_get_atlas_places_data(array $filters = []): array
{
    $cache_key = 'iss_register_atlas_places_cache';
    if (!empty($filters['era_slug']) || !empty($filters['function_key']) || !empty($filters['actor_key'])) {
        $cache_key .= ':' . md5(wp_json_encode($filters));
    }

    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $places = iss_register_get_public_place_entities();
    $era_slug = sanitize_title((string) ($filters['era_slug'] ?? ''));
    $function_key = sanitize_key((string) ($filters['function_key'] ?? ''));
    $actor_key = sanitize_key((string) ($filters['actor_key'] ?? ''));

    if ($era_slug !== '' || $function_key !== '') {
        $allowed_post_ids = iss_register_get_epoch_service()->get_place_ids_for_filters([
            'era_slug' => $era_slug,
            'function_key' => $function_key,
        ]);

        $places = array_values(array_filter($places, static function (array $place) use ($allowed_post_ids, $era_slug, $function_key): bool {
            if (!$allowed_post_ids) {
                return false;
            }

            $post_id = (int) ($place['post_id'] ?? 0);
            if (!in_array($post_id, $allowed_post_ids, true)) {
                return false;
            }

            if ($era_slug !== '' && !in_array($era_slug, (array) ($place['available_era_slugs'] ?? []), true)) {
                return false;
            }

            if ($function_key !== '' && !in_array($function_key, (array) ($place['available_function_keys'] ?? []), true)) {
                return false;
            }

            return true;
        }));
    }

    $place_post_ids = array_values(array_filter(array_map(static function (array $place): int {
        return (int) ($place['post_id'] ?? 0);
    }, $places)));
    $actor_relations_by_place = function_exists('iss_register_get_industry_actor_service')
        ? iss_register_get_industry_actor_service()->get_relations_for_places($place_post_ids)
        : [];
    $related_publications_by_place = iss_register_get_related_publications_by_place($place_post_ids, 2);

    if ($actor_key !== '') {
        $places = array_values(array_filter($places, static function (array $place) use ($actor_key, $actor_relations_by_place, $era_slug): bool {
            $post_id = (int) ($place['post_id'] ?? 0);
            $relations = isset($actor_relations_by_place[$post_id]) && is_array($actor_relations_by_place[$post_id])
                ? $actor_relations_by_place[$post_id]
                : [];
            foreach ($relations as $relation) {
                if (sanitize_key((string) ($relation['actor_key'] ?? '')) !== $actor_key) {
                    continue;
                }

                if ($era_slug !== '') {
                    $relation_era_slug = sanitize_title((string) ($relation['era_slug'] ?? ''));
                    if ($relation_era_slug !== $era_slug) {
                        continue;
                    }
                }

                return true;
            }

            return false;
        }));
    }

    $atlas_places = array_values(array_filter(array_map(
        static function (array $place) use ($actor_relations_by_place, $related_publications_by_place): array {
            return iss_register_build_atlas_place_contract($place, $actor_relations_by_place, $related_publications_by_place);
        },
        $places
    )));

    set_transient($cache_key, $atlas_places, HOUR_IN_SECONDS);

    return $atlas_places;
}
