<?php

if (!defined('ABSPATH')) {
    exit;
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

    return array_values(array_filter($images, static function ($image): bool {
        return is_array($image) && (($image['visibility'] ?? '') === 'public');
    }));
}

function iss_register_map_place_entity(WP_Post $post): array
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

    $place = [
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
        'current_status' => (string) iss_register_get_meta_value($post_id, 'current_status', ''),
        'current_use_type' => (string) iss_register_get_meta_value($post_id, 'current_use_type', ''),
        'size' => (string) iss_register_get_meta_value($post_id, 'size', ''),
        'investment' => (string) iss_register_get_meta_value($post_id, 'investment', ''),
        'jobs' => (string) iss_register_get_meta_value($post_id, 'jobs', ''),
        'status' => $status,
        'icon' => (string) iss_register_get_meta_value($post_id, 'legacy_icon', '🏭'),
        'color' => (string) iss_register_get_meta_value($post_id, 'legacy_color', 'linear-gradient(135deg,#374151,#6B7280)'),
        'branche' => (string) iss_register_get_meta_value($post_id, 'industry', ''),
        'kaufpreis' => (string) iss_register_get_meta_value($post_id, 'legacy_kaufpreis', ''),
        'vornutzung' => (string) iss_register_get_meta_value($post_id, 'previous_use', ''),
        'history_short' => (string) iss_register_get_meta_value($post_id, 'history_short', ''),
        'history' => (string) iss_register_get_meta_value($post_id, 'history_long', ''),
        'current' => (string) iss_register_get_meta_value($post_id, 'current_use', ''),
        'sources' => (string) iss_register_get_meta_value($post_id, 'source_summary', ''),
        'source_summary' => (string) iss_register_get_meta_value($post_id, 'source_summary', ''),
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

    if (function_exists('iss_register_detect_current_status')) {
        $normalized_current_status = iss_register_detect_current_status($place);

        $place['normalized_current_status'] = (string) ($normalized_current_status['key'] ?? '');
        $place['normalized_current_status_label'] = (string) ($normalized_current_status['label'] ?? '');
        $place['normalized_current_status_caption'] = (string) ($normalized_current_status['caption'] ?? '');
        $place['normalized_current_status_source'] = (string) ($normalized_current_status['source'] ?? '');
    } else {
        $place['normalized_current_status'] = (string) ($place['current_status'] ?? '');
        $place['normalized_current_status_label'] = '';
        $place['normalized_current_status_caption'] = '';
        $place['normalized_current_status_source'] = $place['current_status'] !== '' ? 'meta' : '';
    }

    return $place;
}

function iss_register_sort_place_entities_default(array &$places): void
{
    usort($places, static function (array $left, array $right): int {
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

function iss_register_read_place_entities_from_cpt(): array
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

        $places[] = iss_register_map_place_entity($post);
    }

    $epochs_by_place = iss_register_get_epoch_service()->get_epochs_for_places(array_map(static function (array $place): int {
        return (int) ($place['post_id'] ?? 0);
    }, $places));

    foreach ($places as $index => $place) {
        $post_id = (int) ($place['post_id'] ?? 0);
        $places[$index] = iss_register_enrich_place_with_epochs($place, $epochs_by_place[$post_id] ?? []);
    }

    iss_register_sort_place_entities_default($places);

    return $places;
}

function iss_register_get_place_entities(): array
{
    $cached = get_transient('iss_register_places_cache');
    if (is_array($cached)) {
        return $cached;
    }

    $places = iss_register_read_place_entities_from_cpt();
    set_transient('iss_register_places_cache', $places, HOUR_IN_SECONDS);

    return $places;
}

function iss_register_get_place_entity_by_id(string $target_id): ?array
{
    $target_id = trim($target_id);
    if ($target_id === '') {
        return null;
    }

    foreach (iss_register_get_place_entities() as $place) {
        if ((string) ($place['id'] ?? '') === $target_id) {
            return $place;
        }
    }

    return null;
}

function iss_register_get_place_entity_by_post_id(int $target_post_id): ?array
{
    if ($target_post_id <= 0) {
        return null;
    }

    foreach (iss_register_get_place_entities() as $place) {
        if ((int) ($place['post_id'] ?? 0) === $target_post_id) {
            return $place;
        }
    }

    return null;
}
