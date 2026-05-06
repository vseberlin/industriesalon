<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_clear_places_cache(): void
{
    delete_transient('iss_register_places_cache');
    delete_transient('iss_register_atlas_places_cache');
}

function iss_register_get_meta_value(int $post_id, string $key, $default = '')
{
    $value = get_post_meta($post_id, $key, true);

    if ($value === '' || $value === null) {
        return $default;
    }

    return $value;
}

function iss_register_normalize_array_value($value): array
{
    if (is_string($value)) {
        $value = preg_split('/\r\n|\r|\n/', $value);
    }

    if (!is_array($value)) {
        return [];
    }

    $normalized = [];
    foreach ($value as $item) {
        if (!is_scalar($item)) {
            continue;
        }
        $clean = trim((string) $item);
        if ($clean !== '') {
            $normalized[] = $clean;
        }
    }

    return array_values(array_unique($normalized));
}

function iss_register_normalize_image_group_value($value): array
{
    if (function_exists('iss_register_sanitize_image_group')) {
        return iss_register_sanitize_image_group($value);
    }

    return [];
}

function iss_register_filter_public_images($value): array
{
    $images = iss_register_normalize_image_group_value($value);
    if (!$images) {
        return [];
    }

    $public_images = array_values(array_filter($images, function ($image): bool {
        return is_array($image) && (($image['visibility'] ?? '') === 'public');
    }));

    return $public_images;
}

function iss_register_map_place_post(WP_Post $post): array
{
    $post_id = (int) $post->ID;
    $source_links = iss_register_normalize_array_value(iss_register_get_meta_value($post_id, 'source_links', []));
    $tags = iss_register_normalize_array_value(iss_register_get_meta_value($post_id, 'tags', []));
    $questions = iss_register_normalize_array_value(iss_register_get_meta_value($post_id, 'legacy_questions', []));

    $website = (string) iss_register_get_meta_value($post_id, 'legacy_website', '');
    if ($website === '' && $source_links) {
        $website = (string) $source_links[0];
    }

    $register_id = (string) iss_register_get_meta_value($post_id, 'register_id', '');
    if ($register_id === '') {
        $register_id = (string) $post_id;
    }

    $status = (string) iss_register_get_meta_value($post_id, 'status', '');
    $is_unclear = (bool) iss_register_get_meta_value($post_id, 'is_unclear', 0) || $status === 'unklar';
    $archive_images = iss_register_filter_public_images(iss_register_get_meta_value($post_id, 'archive_images', []));
    $current_images = iss_register_filter_public_images(iss_register_get_meta_value($post_id, 'current_images', []));
    $document_images = iss_register_filter_public_images(iss_register_get_meta_value($post_id, 'document_images', []));
    $featured_image_url = get_the_post_thumbnail_url($post, 'large');

    return [
        'id' => $register_id,
        'post_id' => $post_id,
        'slug' => (string) $post->post_name,
        'name' => get_the_title($post),
        'excerpt' => (string) get_post_field('post_excerpt', $post_id),
        'permalink' => (string) get_permalink($post),
        'featured_image_url' => is_string($featured_image_url) ? $featured_image_url : '',
        'owner' => (string) iss_register_get_meta_value($post_id, 'owner', ''),
        'operator' => (string) iss_register_get_meta_value($post_id, 'operator', ''),
        'developer' => (string) iss_register_get_meta_value($post_id, 'developer', ''),
        'tenant' => (string) iss_register_get_meta_value($post_id, 'tenant', ''),
        'role' => (string) iss_register_get_meta_value($post_id, 'role', ''),
        'area' => (string) iss_register_get_meta_value($post_id, 'area', ''),
        'address' => (string) iss_register_get_meta_value($post_id, 'address', ''),
        'size' => (string) iss_register_get_meta_value($post_id, 'size', ''),
        'investment' => (string) iss_register_get_meta_value($post_id, 'investment', ''),
        'jobs' => (string) iss_register_get_meta_value($post_id, 'jobs', ''),
        'status' => $status,
        'icon' => (string) iss_register_get_meta_value($post_id, 'legacy_icon', '🏭'),
        'color' => (string) iss_register_get_meta_value($post_id, 'legacy_color', 'linear-gradient(135deg,#374151,#6B7280)'),
        'branche' => (string) iss_register_get_meta_value($post_id, 'industry', ''),
        'kaufpreis' => (string) iss_register_get_meta_value($post_id, 'legacy_kaufpreis', ''),
        'vornutzung' => (string) iss_register_get_meta_value($post_id, 'previous_use', ''),
        'history' => (string) iss_register_get_meta_value($post_id, 'history_long', ''),
        'current' => (string) iss_register_get_meta_value($post_id, 'current_use', ''),
        'sources' => (string) iss_register_get_meta_value($post_id, 'source_summary', ''),
        'source_links' => $source_links,
        'website' => $website,
        'questions' => $questions,
        'tags' => $tags,
        'is_unclear' => $is_unclear,
        'archive_images' => $archive_images,
        'current_images' => $current_images,
        'document_images' => $document_images,
        'lat' => iss_register_get_meta_value($post_id, 'lat', ''),
        'lng' => iss_register_get_meta_value($post_id, 'lng', ''),
        'sort_order' => (int) iss_register_get_meta_value($post_id, 'sort_order', 0),
    ];
}

function iss_register_sort_places_default(array &$places): void
{
    usort($places, function (array $left, array $right): int {
        $left_sort = (int) ($left['sort_order'] ?? 0);
        $right_sort = (int) ($right['sort_order'] ?? 0);

        if ($left_sort !== 0 || $right_sort !== 0) {
            if ($left_sort === $right_sort) {
                $left_id = (int) preg_replace('/\D+/', '', (string) ($left['id'] ?? '0'));
                $right_id = (int) preg_replace('/\D+/', '', (string) ($right['id'] ?? '0'));
                return $left_id <=> $right_id;
            }
            return $left_sort <=> $right_sort;
        }

        $left_id = (int) preg_replace('/\D+/', '', (string) ($left['id'] ?? '0'));
        $right_id = (int) preg_replace('/\D+/', '', (string) ($right['id'] ?? '0'));
        return $left_id <=> $right_id;
    });
}

function iss_register_get_places_from_cpt(): array
{
    if (!post_type_exists(ISS_REGISTER_POST_TYPE)) {
        return [];
    }

    $posts = get_posts([
        'post_type' => ISS_REGISTER_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    if (!$posts) {
        return [];
    }

    $places = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $places[] = iss_register_map_place_post($post);
    }

    iss_register_sort_places_default($places);

    return $places;
}

function iss_register_get_places_data(): array
{
    $cached = get_transient('iss_register_places_cache');
    if (is_array($cached)) {
        return $cached;
    }

    $places = iss_register_get_places_from_cpt();
    set_transient('iss_register_places_cache', $places, HOUR_IN_SECONDS);

    return $places;
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

/**
 * Atlas may expose that a place participates in tours, but it must not expose
 * route ordering or stop-specific route presentation fields.
 */
function iss_register_get_place_tour_usage(int $place_id): array
{
    if ($place_id <= 0 || !post_type_exists('fuehrung')) {
        return [];
    }

    $tour_posts = get_posts([
        'post_type' => 'fuehrung',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    if (!$tour_posts) {
        return [];
    }

    $related_tours = [];

    foreach ($tour_posts as $tour_post) {
        if (!$tour_post instanceof WP_Post) {
            continue;
        }

        $related_places = get_post_meta((int) $tour_post->ID, 'iss_related_places', true);
        if (!is_array($related_places)) {
            continue;
        }

        foreach ($related_places as $related_place) {
            if (!is_array($related_place)) {
                continue;
            }

            if ((int) ($related_place['place_id'] ?? 0) !== $place_id) {
                continue;
            }

            $related_tours[] = [
                'id' => (int) $tour_post->ID,
                'slug' => (string) $tour_post->post_name,
                'title' => get_the_title($tour_post),
                'permalink' => (string) get_permalink($tour_post),
            ];
            break;
        }
    }

    return $related_tours;
}

function iss_register_map_place_for_atlas(array $place): array
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
        'iss_register_map_place_for_atlas',
        iss_register_get_places_data()
    )));

    set_transient('iss_register_atlas_places_cache', $atlas_places, HOUR_IN_SECONDS);

    return $atlas_places;
}

function iss_register_normalize_text($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    return strtolower(trim((string) $value));
}

function iss_register_match_tag(array $place, string $needle): bool
{
    $tags = $place['tags'] ?? [];

    if (is_string($tags)) {
        $tags = array_map('trim', explode(',', $tags));
    }

    if (!is_array($tags)) {
        return false;
    }

    foreach ($tags as $tag) {
        if (iss_register_normalize_text($tag) === $needle) {
            return true;
        }
    }

    return false;
}

function iss_register_is_unclear(array $place): bool
{
    if (($place['status'] ?? '') === 'unklar') {
        return true;
    }

    return !empty($place['is_unclear']);
}

function iss_register_sort_places(array &$places, string $orderby, string $order): void
{
    $status_order = [
        'aktiv' => 0,
        'entwicklung' => 1,
        'geplant' => 2,
        'unklar' => 3,
        'abzug' => 4,
        'sucht' => 5,
    ];

    usort($places, function (array $left, array $right) use ($orderby, $order, $status_order): int {
        if ($orderby === 'name' || $orderby === 'area') {
            $result = strcasecmp((string) ($left[$orderby] ?? ''), (string) ($right[$orderby] ?? ''));
        } elseif ($orderby === 'status') {
            $left_status = $status_order[(string) ($left['status'] ?? '')] ?? 999;
            $right_status = $status_order[(string) ($right['status'] ?? '')] ?? 999;
            $result = $left_status <=> $right_status;
        } else {
            $left_id = (int) preg_replace('/\D+/', '', (string) ($left['id'] ?? '0'));
            $right_id = (int) preg_replace('/\D+/', '', (string) ($right['id'] ?? '0'));
            $result = $left_id <=> $right_id;
        }

        if ($order === 'DESC') {
            return $result * -1;
        }

        return $result;
    });
}

function iss_register_rest_get_places(WP_REST_Request $request): WP_REST_Response
{
    $places = iss_register_get_places_data();

    if (!$places) {
        return rest_ensure_response([]);
    }

    $search = iss_register_normalize_text($request->get_param('search'));
    $area = iss_register_normalize_text($request->get_param('area'));
    $status = iss_register_normalize_text($request->get_param('status'));
    $role = iss_register_normalize_text($request->get_param('role'));
    $tag = iss_register_normalize_text($request->get_param('tag'));

    $unclear_param = $request->get_param('unclear');
    $has_unclear_filter = $unclear_param !== null && $unclear_param !== '';
    $unclear_value = $has_unclear_filter ? (bool) rest_sanitize_boolean($unclear_param) : null;

    $places = array_values(array_filter($places, function (array $place) use ($search, $area, $status, $role, $tag, $has_unclear_filter, $unclear_value): bool {
        if ($area !== '' && iss_register_normalize_text($place['area'] ?? '') !== $area) {
            return false;
        }

        if ($status !== '' && iss_register_normalize_text($place['status'] ?? '') !== $status) {
            return false;
        }

        if ($role !== '' && iss_register_normalize_text($place['role'] ?? '') !== $role) {
            return false;
        }

        if ($tag !== '' && !iss_register_match_tag($place, $tag)) {
            return false;
        }

        if ($has_unclear_filter && iss_register_is_unclear($place) !== $unclear_value) {
            return false;
        }

        if ($search !== '') {
            $haystack = implode(' ', [
                (string) ($place['name'] ?? ''),
                (string) ($place['owner'] ?? ''),
                (string) ($place['branche'] ?? ''),
                (string) ($place['address'] ?? ''),
                (string) ($place['current'] ?? ''),
                (string) ($place['history'] ?? ''),
                (string) ($place['vornutzung'] ?? ''),
                (string) ($place['jobs'] ?? ''),
            ]);

            if (stripos($haystack, $search) === false) {
                return false;
            }
        }

        return true;
    }));

    $orderby = sanitize_key((string) $request->get_param('orderby'));
    $order = strtoupper((string) $request->get_param('order')) === 'DESC' ? 'DESC' : 'ASC';

    if (in_array($orderby, ['id', 'name', 'area', 'status'], true)) {
        iss_register_sort_places($places, $orderby, $order);
    }

    return rest_ensure_response($places);
}

function iss_register_rest_get_atlas_places(): WP_REST_Response
{
    return rest_ensure_response(iss_register_get_atlas_places_data());
}

function iss_register_rest_get_atlas_context(WP_REST_Request $request): WP_REST_Response
{
    $era_slug = sanitize_title((string) $request->get_param('era'));
    $context = iss_register_get_atlas_context_data();

    if ($era_slug !== '') {
        $context['stories'] = array_values(array_filter((array) ($context['stories'] ?? []), static function (array $story) use ($era_slug): bool {
            return in_array($era_slug, (array) ($story['era_slugs'] ?? []), true);
        }));
    }

    return rest_ensure_response($context);
}

function iss_register_rest_get_place(WP_REST_Request $request)
{
    $target_id = trim((string) $request['id']);
    if ($target_id === '') {
        return new WP_Error('iss_register_missing_id', 'Missing place id.', ['status' => 400]);
    }

    $places = iss_register_get_places_data();
    foreach ($places as $place) {
        if ((string) ($place['id'] ?? '') === $target_id) {
            return rest_ensure_response($place);
        }
    }

    return new WP_Error('iss_register_not_found', 'Place not found.', ['status' => 404]);
}

function iss_register_rest_get_meta(): WP_REST_Response
{
    $places = iss_register_get_places_data();

    $areas = [];
    $statuses = [];
    $roles = [];
    $tags = [];

    foreach ($places as $place) {
        if (isset($place['area']) && $place['area'] !== '') {
            $areas[$place['area']] = ($areas[$place['area']] ?? 0) + 1;
        }
        if (isset($place['status']) && $place['status'] !== '') {
            $statuses[$place['status']] = ($statuses[$place['status']] ?? 0) + 1;
        }
        if (isset($place['role']) && $place['role'] !== '') {
            $roles[$place['role']] = ($roles[$place['role']] ?? 0) + 1;
        }

        $place_tags = $place['tags'] ?? [];
        if (is_string($place_tags)) {
            $place_tags = array_map('trim', explode(',', $place_tags));
        }

        if (is_array($place_tags)) {
            foreach ($place_tags as $tag) {
                if (!is_string($tag) || $tag === '') {
                    continue;
                }
                $tags[$tag] = ($tags[$tag] ?? 0) + 1;
            }
        }
    }

    return rest_ensure_response([
        'total' => count($places),
        'areas' => $areas,
        'statuses' => $statuses,
        'roles' => $roles,
        'tags' => $tags,
    ]);
}

add_action('rest_api_init', function () {
    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/places', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_places',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/atlas', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_atlas_places',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/atlas-context', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_atlas_context',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/places/(?P<id>[^/]+)', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_place',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route(ISS_REGISTER_REST_NAMESPACE, '/meta', [
        'methods' => WP_REST_Server::READABLE,
        'callback' => 'iss_register_rest_get_meta',
        'permission_callback' => '__return_true',
    ]);
});

add_action('save_post_' . ISS_REGISTER_POST_TYPE, 'iss_register_clear_places_cache');
add_action('deleted_post', 'iss_register_clear_places_cache');
add_action('updated_post_meta', 'iss_register_clear_places_cache');
