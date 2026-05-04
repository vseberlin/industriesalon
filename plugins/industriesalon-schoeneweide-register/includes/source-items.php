<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_register_source_post_type(): void
{
    register_post_type(ISS_REGISTER_SOURCE_POST_TYPE, [
        'labels' => [
            'name' => 'Register Quellstaende',
            'singular_name' => 'Register Quellstand',
        ],
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'show_in_rest' => false,
        'exclude_from_search' => true,
        'rewrite' => false,
        'query_var' => false,
        'supports' => ['title'],
    ]);
}

add_action('init', 'iss_register_register_source_post_type');

function iss_register_source_snapshot_meta_keys(): array
{
    return [
        'source_system' => '_iss_register_source_system',
        'source_key' => '_iss_register_source_key',
        'source_item_type' => '_iss_register_source_item_type',
        'source_id' => '_iss_register_source_id',
        'source_parent_id' => '_iss_register_source_parent_id',
        'source_url' => '_iss_register_source_url',
        'source_parent_url' => '_iss_register_source_parent_url',
        'source_language' => '_iss_register_source_language',
        'source_modified' => '_iss_register_source_modified',
        'source_hash' => '_iss_register_source_hash',
        'source_kind' => '_iss_register_source_kind',
        'raw_payload' => '_iss_register_source_raw_payload',
        'extracted_payload' => '_iss_register_source_extracted_payload',
        'candidate_register_place_id' => '_iss_register_candidate_register_place_id',
        'matched_register_place_id' => '_iss_register_matched_register_place_id',
        'review_status' => '_iss_register_source_review_status',
    ];
}

function iss_register_source_review_statuses(): array
{
    return [
        'new' => 'Neu',
        'changed' => 'Geaendert',
        'linked' => 'Verknuepft',
        'ignored' => 'Ignoriert',
    ];
}

function iss_register_normalize_source_review_status(string $status): string
{
    $status = sanitize_key($status);
    $statuses = iss_register_source_review_statuses();

    if (!isset($statuses[$status])) {
        return 'new';
    }

    return $status;
}

function iss_register_source_snapshot_stats_seed(): array
{
    return [
        'total' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped_unchanged' => 0,
        'matched' => 0,
        'unmatched' => 0,
        'errors' => 0,
    ];
}

function iss_register_normalize_source_text(string $text): string
{
    $text = wp_strip_all_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text);

    return is_string($text) ? trim($text) : '';
}

function iss_register_source_text_length(string $text): int
{
    $text = iss_register_normalize_source_text($text);

    if ($text === '') {
        return 0;
    }

    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($text);
    }

    return strlen($text);
}

function iss_register_source_text_preview(string $text, int $limit = 220): string
{
    $text = iss_register_normalize_source_text($text);
    if ($text === '') {
        return '';
    }

    $limit = max(40, $limit);
    $length = iss_register_source_text_length($text);

    if ($length <= $limit) {
        return $text;
    }

    if (function_exists('mb_substr')) {
        return rtrim((string) mb_substr($text, 0, $limit)) . ' …';
    }

    return rtrim(substr($text, 0, $limit)) . ' …';
}

function iss_register_find_source_snapshot_post_id(string $source_key): int
{
    if ($source_key === '') {
        return 0;
    }

    $posts = get_posts([
        'post_type' => ISS_REGISTER_SOURCE_POST_TYPE,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => iss_register_source_snapshot_meta_keys()['source_key'],
        'meta_value' => $source_key,
        'suppress_filters' => true,
    ]);

    if (!$posts) {
        return 0;
    }

    return (int) $posts[0];
}

function iss_register_match_register_place_candidate(array $snapshot): int
{
    $source_url = trim((string) ($snapshot['source_url'] ?? ''));
    if ($source_url !== '') {
        $posts = get_posts([
            'post_type' => ISS_REGISTER_POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => 'legacy_website',
            'meta_value' => $source_url,
            'suppress_filters' => true,
        ]);

        if ($posts) {
            return (int) $posts[0];
        }
    }

    $source_slug = sanitize_title((string) ($snapshot['slug'] ?? ''));
    $source_title = sanitize_title((string) ($snapshot['title'] ?? ''));

    $posts = get_posts([
        'post_type' => ISS_REGISTER_POST_TYPE,
        'post_status' => 'any',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            continue;
        }

        if ($source_slug !== '' && get_post_field('post_name', $post_id) === $source_slug) {
            return $post_id;
        }

        if ($source_title !== '' && sanitize_title(get_the_title($post_id)) === $source_title) {
            return $post_id;
        }
    }

    return 0;
}

function iss_register_determine_source_review_status(
    int $existing_post_id,
    string $existing_hash,
    string $incoming_hash,
    int $linked_register_place_id,
    string $existing_status
): string {
    if ($existing_post_id <= 0) {
        return 'new';
    }

    $existing_status = iss_register_normalize_source_review_status($existing_status);

    if ($linked_register_place_id > 0 && $existing_status === 'new') {
        $existing_status = 'linked';
    }

    if ($existing_hash !== '' && $incoming_hash !== '' && hash_equals($existing_hash, $incoming_hash)) {
        return $existing_status;
    }

    if ($linked_register_place_id > 0 || $existing_status === 'ignored' || $existing_status === 'changed') {
        return 'changed';
    }

    return 'new';
}

function iss_register_upsert_source_snapshot(array $snapshot, array &$stats, bool $dry_run = false): void
{
    $meta_keys = iss_register_source_snapshot_meta_keys();
    $source_key = trim((string) ($snapshot['source_key'] ?? ''));

    if ($source_key === '') {
        $stats['errors']++;
        return;
    }

    $existing_post_id = iss_register_find_source_snapshot_post_id($source_key);
    $existing_hash = $existing_post_id > 0 ? (string) get_post_meta($existing_post_id, $meta_keys['source_hash'], true) : '';
    $candidate_register_place_id = iss_register_match_register_place_candidate($snapshot);

    if ($candidate_register_place_id > 0) {
        $stats['matched']++;
    } else {
        $stats['unmatched']++;
    }

    $has_candidate_meta = $existing_post_id > 0 && metadata_exists('post', $existing_post_id, $meta_keys['candidate_register_place_id']);
    $has_review_status_meta = $existing_post_id > 0 && metadata_exists('post', $existing_post_id, $meta_keys['review_status']);
    $is_legacy_snapshot = $existing_post_id > 0 && !$has_candidate_meta && !$has_review_status_meta;

    $linked_register_place_id = 0;
    if ($existing_post_id > 0 && !$is_legacy_snapshot) {
        $linked_register_place_id = (int) get_post_meta($existing_post_id, $meta_keys['matched_register_place_id'], true);
    }

    $existing_status = $existing_post_id > 0 && !$is_legacy_snapshot
        ? (string) get_post_meta($existing_post_id, $meta_keys['review_status'], true)
        : 'new';

    $review_status = iss_register_determine_source_review_status(
        $existing_post_id,
        $existing_hash,
        (string) ($snapshot['source_hash'] ?? ''),
        $linked_register_place_id,
        $existing_status
    );

    $needs_backfill = $existing_post_id > 0 && (
        $is_legacy_snapshot
        || !$has_candidate_meta
        || !$has_review_status_meta
    );

    if (
        $existing_post_id > 0
        && $existing_hash !== ''
        && hash_equals($existing_hash, (string) ($snapshot['source_hash'] ?? ''))
        && !$needs_backfill
    ) {
        $stats['skipped_unchanged']++;
        return;
    }

    if ($dry_run) {
        if ($existing_post_id > 0) {
            $stats['updated']++;
        } else {
            $stats['created']++;
        }
        return;
    }

    $post_data = [
        'post_type' => ISS_REGISTER_SOURCE_POST_TYPE,
        'post_title' => trim((string) ($snapshot['title'] ?? $source_key)) ?: $source_key,
        'post_status' => 'private',
    ];

    if ($existing_post_id > 0) {
        $post_data['ID'] = $existing_post_id;
        $post_id = wp_update_post($post_data, true);
    } else {
        $post_id = wp_insert_post($post_data, true);
    }

    if (is_wp_error($post_id)) {
        $stats['errors']++;
        return;
    }

    $meta_values = [
        $meta_keys['source_system'] => (string) ($snapshot['source_system'] ?? ''),
        $meta_keys['source_key'] => $source_key,
        $meta_keys['source_item_type'] => (string) ($snapshot['source_item_type'] ?? ''),
        $meta_keys['source_id'] => (int) ($snapshot['source_id'] ?? 0),
        $meta_keys['source_parent_id'] => (int) ($snapshot['source_parent_id'] ?? 0),
        $meta_keys['source_url'] => (string) ($snapshot['source_url'] ?? ''),
        $meta_keys['source_parent_url'] => (string) ($snapshot['source_parent_url'] ?? ''),
        $meta_keys['source_language'] => (string) ($snapshot['source_language'] ?? ''),
        $meta_keys['source_modified'] => (string) ($snapshot['source_modified'] ?? ''),
        $meta_keys['source_hash'] => (string) ($snapshot['source_hash'] ?? ''),
        $meta_keys['source_kind'] => (string) ($snapshot['source_kind'] ?? ''),
        $meta_keys['raw_payload'] => $snapshot['raw_payload'] ?? [],
        $meta_keys['extracted_payload'] => $snapshot['extracted_payload'] ?? [],
        $meta_keys['candidate_register_place_id'] => $candidate_register_place_id,
        $meta_keys['matched_register_place_id'] => $linked_register_place_id,
        $meta_keys['review_status'] => $review_status,
    ];

    foreach ($meta_values as $meta_key => $meta_value) {
        update_post_meta((int) $post_id, $meta_key, $meta_value);
    }

    if ($existing_post_id > 0) {
        $stats['updated']++;
    } else {
        $stats['created']++;
    }
}

function iss_register_count_source_snapshots_by_type(string $item_type): int
{
    global $wpdb;

    $meta_keys = iss_register_source_snapshot_meta_keys();

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(1)
        FROM {$wpdb->posts} posts
        INNER JOIN {$wpdb->postmeta} meta
            ON posts.ID = meta.post_id
        WHERE posts.post_type = %s
            AND posts.post_status IN ('private', 'publish')
            AND meta.meta_key = %s
            AND meta.meta_value = %s",
        ISS_REGISTER_SOURCE_POST_TYPE,
        $meta_keys['source_item_type'],
        $item_type
    ));

    return (int) $count;
}

function iss_register_get_source_snapshot(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_REGISTER_SOURCE_POST_TYPE) {
        return [];
    }

    $meta_keys = iss_register_source_snapshot_meta_keys();
    $candidate_register_place_id = (int) get_post_meta($post_id, $meta_keys['candidate_register_place_id'], true);
    $matched_register_place_id = (int) get_post_meta($post_id, $meta_keys['matched_register_place_id'], true);
    $review_status = (string) get_post_meta($post_id, $meta_keys['review_status'], true);

    $extracted_payload = get_post_meta($post_id, $meta_keys['extracted_payload'], true);
    $extracted_payload = is_array($extracted_payload) ? $extracted_payload : [];
    $excerpt_text = iss_register_normalize_source_text((string) ($extracted_payload['excerpt_text'] ?? ''));
    $content_text = iss_register_normalize_source_text((string) ($extracted_payload['content_text'] ?? ''));
    $preview_text = $content_text !== '' ? $content_text : $excerpt_text;
    $hotspots = is_array($extracted_payload['hotspots'] ?? null) ? $extracted_payload['hotspots'] : [];

    return [
        'post_id' => $post_id,
        'title' => (string) $post->post_title,
        'item_type' => (string) get_post_meta($post_id, $meta_keys['source_item_type'], true),
        'source_kind' => (string) get_post_meta($post_id, $meta_keys['source_kind'], true),
        'source_url' => (string) get_post_meta($post_id, $meta_keys['source_url'], true),
        'source_parent_url' => (string) get_post_meta($post_id, $meta_keys['source_parent_url'], true),
        'source_language' => (string) get_post_meta($post_id, $meta_keys['source_language'], true),
        'source_modified' => (string) get_post_meta($post_id, $meta_keys['source_modified'], true),
        'source_id' => (int) get_post_meta($post_id, $meta_keys['source_id'], true),
        'source_parent_id' => (int) get_post_meta($post_id, $meta_keys['source_parent_id'], true),
        'source_key' => (string) get_post_meta($post_id, $meta_keys['source_key'], true),
        'candidate_register_place_id' => $candidate_register_place_id,
        'matched_register_place_id' => $matched_register_place_id,
        'review_status' => iss_register_normalize_source_review_status($review_status),
        'raw_payload' => get_post_meta($post_id, $meta_keys['raw_payload'], true),
        'extracted_payload' => $extracted_payload,
        'excerpt_text' => $excerpt_text,
        'content_text' => $content_text,
        'preview_text' => iss_register_source_text_preview($preview_text),
        'excerpt_length' => iss_register_source_text_length($excerpt_text),
        'content_length' => iss_register_source_text_length($content_text),
        'preview_length' => iss_register_source_text_length($preview_text),
        'has_text_content' => $preview_text !== '',
        'hotspot_count' => count($hotspots),
        'modified' => get_the_modified_date('Y-m-d H:i:s', $post),
    ];
}

function iss_register_get_recent_source_snapshots(int $limit = 10): array
{
    $posts = get_posts([
        'post_type' => ISS_REGISTER_SOURCE_POST_TYPE,
        'post_status' => ['private', 'publish'],
        'posts_per_page' => $limit,
        'orderby' => 'modified',
        'order' => 'DESC',
        'suppress_filters' => true,
    ]);

    if (!$posts) {
        return [];
    }

    $items = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $items[] = iss_register_get_source_snapshot((int) $post->ID);
    }

    return array_values(array_filter($items));
}

function iss_register_get_source_snapshots_for_review(array $args = []): array
{
    $meta_keys = iss_register_source_snapshot_meta_keys();
    $source_kind = sanitize_key((string) ($args['source_kind'] ?? 'detail_page'));
    $review_status = sanitize_key((string) ($args['review_status'] ?? 'needs_review'));
    $paged = max(1, (int) ($args['paged'] ?? 1));
    $per_page = max(1, min(100, (int) ($args['per_page'] ?? 25)));
    $linked_register_place_id = max(0, (int) ($args['linked_register_place_id'] ?? 0));

    $needs_php_sort = in_array($source_kind, ['detail_page', 'landing_page', 'all'], true);

    $query_args = [
        'post_type' => ISS_REGISTER_SOURCE_POST_TYPE,
        'post_status' => ['private', 'publish'],
        'posts_per_page' => $needs_php_sort ? -1 : $per_page,
        'paged' => $needs_php_sort ? 1 : $paged,
        'orderby' => 'modified',
        'order' => 'DESC',
        'suppress_filters' => true,
    ];

    $meta_query = [];

    if ($source_kind !== '' && $source_kind !== 'all') {
        $meta_query[] = [
            'key' => $meta_keys['source_kind'],
            'value' => $source_kind,
        ];
    }

    if ($linked_register_place_id > 0) {
        $meta_query[] = [
            'key' => $meta_keys['matched_register_place_id'],
            'value' => $linked_register_place_id,
        ];
    }

    if ($review_status === 'needs_review') {
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key' => $meta_keys['review_status'],
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => $meta_keys['review_status'],
                'value' => ['new', 'changed'],
                'compare' => 'IN',
            ],
        ];
    } elseif ($review_status !== '' && $review_status !== 'all') {
        $meta_query[] = [
            'key' => $meta_keys['review_status'],
            'value' => iss_register_normalize_source_review_status($review_status),
        ];
    }

    if ($meta_query) {
        if (count($meta_query) > 1) {
            $meta_query['relation'] = 'AND';
        }
        $query_args['meta_query'] = $meta_query;
    }

    $query = new WP_Query($query_args);
    $items = [];

    foreach ($query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $items[] = iss_register_get_source_snapshot((int) $post->ID);
    }

    $items = array_values(array_filter($items));

    if ($needs_php_sort) {
        usort($items, function (array $a, array $b): int {
            $a_has_text = !empty($a['has_text_content']);
            $b_has_text = !empty($b['has_text_content']);

            if ($a_has_text !== $b_has_text) {
                return $a_has_text ? -1 : 1;
            }

            $a_preview_length = (int) ($a['preview_length'] ?? 0);
            $b_preview_length = (int) ($b['preview_length'] ?? 0);
            if ($a_preview_length !== $b_preview_length) {
                return $b_preview_length <=> $a_preview_length;
            }

            $a_modified = strtotime((string) ($a['modified'] ?? '')) ?: 0;
            $b_modified = strtotime((string) ($b['modified'] ?? '')) ?: 0;

            return $b_modified <=> $a_modified;
        });
    }

    $total = $needs_php_sort ? count($items) : (int) $query->found_posts;
    $pages = max(1, (int) ceil($total / $per_page));

    if ($needs_php_sort) {
        $offset = ($paged - 1) * $per_page;
        $items = array_slice($items, max(0, $offset), $per_page);
    }

    return [
        'items' => $items,
        'total' => $total,
        'pages' => $pages,
        'page' => $paged,
        'per_page' => $per_page,
    ];
}

function iss_register_get_source_snapshots_for_place(int $place_id, int $limit = 20): array
{
    if ($place_id <= 0) {
        return [];
    }

    $meta_keys = iss_register_source_snapshot_meta_keys();
    $posts = get_posts([
        'post_type' => ISS_REGISTER_SOURCE_POST_TYPE,
        'post_status' => ['private', 'publish'],
        'posts_per_page' => max(1, $limit),
        'orderby' => 'modified',
        'order' => 'DESC',
        'suppress_filters' => true,
        'meta_key' => $meta_keys['matched_register_place_id'],
        'meta_value' => $place_id,
    ]);

    if (!$posts) {
        return [];
    }

    $items = [];

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $items[] = iss_register_get_source_snapshot((int) $post->ID);
    }

    return array_values(array_filter($items));
}

function iss_register_link_source_snapshot_to_place(int $snapshot_post_id, int $register_place_id): bool
{
    if ($snapshot_post_id <= 0 || $register_place_id <= 0) {
        return false;
    }

    $snapshot = get_post($snapshot_post_id);
    $place = get_post($register_place_id);

    if (
        !$snapshot instanceof WP_Post
        || $snapshot->post_type !== ISS_REGISTER_SOURCE_POST_TYPE
        || !$place instanceof WP_Post
        || $place->post_type !== ISS_REGISTER_POST_TYPE
    ) {
        return false;
    }

    $meta_keys = iss_register_source_snapshot_meta_keys();
    update_post_meta($snapshot_post_id, $meta_keys['matched_register_place_id'], $register_place_id);
    update_post_meta($snapshot_post_id, $meta_keys['review_status'], 'linked');

    return true;
}

function iss_register_ignore_source_snapshot(int $snapshot_post_id): bool
{
    if ($snapshot_post_id <= 0) {
        return false;
    }

    $snapshot = get_post($snapshot_post_id);
    if (!$snapshot instanceof WP_Post || $snapshot->post_type !== ISS_REGISTER_SOURCE_POST_TYPE) {
        return false;
    }

    $meta_keys = iss_register_source_snapshot_meta_keys();
    update_post_meta($snapshot_post_id, $meta_keys['matched_register_place_id'], 0);
    update_post_meta($snapshot_post_id, $meta_keys['review_status'], 'ignored');

    return true;
}

function iss_register_reset_source_snapshot_review(int $snapshot_post_id): bool
{
    if ($snapshot_post_id <= 0) {
        return false;
    }

    $snapshot = get_post($snapshot_post_id);
    if (!$snapshot instanceof WP_Post || $snapshot->post_type !== ISS_REGISTER_SOURCE_POST_TYPE) {
        return false;
    }

    $meta_keys = iss_register_source_snapshot_meta_keys();
    update_post_meta($snapshot_post_id, $meta_keys['matched_register_place_id'], 0);
    update_post_meta($snapshot_post_id, $meta_keys['review_status'], 'new');

    return true;
}

function iss_register_create_register_place_from_source_snapshot(int $snapshot_post_id)
{
    $snapshot = iss_register_get_source_snapshot($snapshot_post_id);
    if (!$snapshot) {
        return new WP_Error('iss_register_missing_snapshot', 'Quellstand nicht gefunden.');
    }

    $title = trim((string) ($snapshot['title'] ?? ''));
    if ($title === '') {
        return new WP_Error('iss_register_missing_title', 'Quellstand ohne Titel kann nicht angelegt werden.');
    }

    $extracted = is_array($snapshot['extracted_payload'] ?? null) ? $snapshot['extracted_payload'] : [];
    $excerpt = trim((string) ($extracted['excerpt_text'] ?? ''));
    $content = trim((string) ($extracted['content_text'] ?? ''));

    $post_id = wp_insert_post([
        'post_type' => ISS_REGISTER_POST_TYPE,
        'post_status' => 'draft',
        'post_title' => $title,
        'post_excerpt' => $excerpt,
        'post_content' => $content,
    ], true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $source_url = trim((string) ($snapshot['source_url'] ?? ''));
    if ($source_url !== '') {
        update_post_meta((int) $post_id, 'legacy_website', $source_url);
        update_post_meta((int) $post_id, 'source_links', [$source_url]);
    }

    if (function_exists('iss_register_sync_public_fields_for_post')) {
        iss_register_sync_public_fields_for_post((int) $post_id);
    }

    return (int) $post_id;
}
