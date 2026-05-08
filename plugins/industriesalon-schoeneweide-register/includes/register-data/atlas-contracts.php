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

function iss_register_build_atlas_place_contract(array $place): array
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
    $related_tours = iss_register_get_place_tour_usage((int) ($place['post_id'] ?? 0));

    return [
        'id' => (string) ($place['id'] ?? ''),
        'post_id' => (int) ($place['post_id'] ?? 0),
        'slug' => (string) ($place['slug'] ?? ''),
        'name' => (string) ($place['name'] ?? ''),
        'excerpt' => $excerpt,
        'permalink' => (string) ($place['permalink'] ?? ''),
        'featured_image_url' => (string) ($place['featured_image_url'] ?? ''),
        'role' => (string) ($place['role'] ?? ''),
        'area' => (string) ($place['area'] ?? ''),
        'address' => (string) ($place['address'] ?? ''),
        'status' => (string) ($place['status'] ?? ''),
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

function iss_register_get_atlas_places_data(): array
{
    $cached = get_transient('iss_register_atlas_places_cache');
    if (is_array($cached)) {
        return $cached;
    }

    $atlas_places = array_values(array_filter(array_map(
        'iss_register_build_atlas_place_contract',
        iss_register_get_place_entities()
    )));

    set_transient('iss_register_atlas_places_cache', $atlas_places, HOUR_IN_SECONDS);

    return $atlas_places;
}
