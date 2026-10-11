<?php
/** Prepared reads for the existing graph search projection. */
if (!defined('ABSPATH')) { exit; }

function iss_graph_normalize_public_search_query(string $search): string
{
    $search = html_entity_decode($search, ENT_QUOTES, 'UTF-8');
    $search = wp_strip_all_tags($search);
    $search = trim(preg_replace('/\s+/u', ' ', $search));

    return is_string($search) ? $search : '';
}

function iss_graph_tokenize_public_search_query(string $search): array
{
    $search = iss_graph_normalize_public_search_query($search);
    if ($search === '') {
        return [];
    }

    $parts = preg_split('/[\s,.;:\/\-_]+/u', $search) ?: [];
    $tokens = [];

    foreach ($parts as $part) {
        $part = trim((string) $part);
        if ($part === '') {
            continue;
        }

        if (function_exists('mb_strlen') && mb_strlen($part) < 2) {
            continue;
        }

        if (!function_exists('mb_strlen') && strlen($part) < 2) {
            continue;
        }

        $tokens[] = $part;
    }

    $tokens = array_values(array_unique($tokens));

    return $tokens ?: [$search];
}

function iss_graph_build_public_search_sql(string $search, array $args = []): array
{
    global $wpdb;

    $search = iss_graph_normalize_public_search_query($search);
    if ($search === '') {
        return [];
    }

    $tokens = iss_graph_tokenize_public_search_query($search);
    if (!$tokens) {
        return [];
    }

    $post_types = isset($args['post_types']) && is_array($args['post_types']) && $args['post_types']
        ? array_values(array_intersect(array_map('sanitize_key', $args['post_types']), iss_graph_get_public_search_post_types()))
        : iss_graph_get_public_search_post_types();

    if (!$post_types) {
        return [];
    }

    $limit = isset($args['limit']) ? max(1, min(1000, (int) $args['limit'])) : 1000;
    $service = iss_graph_get_service();
    $table = $service->get_search_table_name();
    $signal_table = $service->get_editorial_signal_table_name();
    $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));

    $select_values = [$search];
    $phrase_like = '%' . $wpdb->esc_like($search) . '%';
    $select_values[] = $phrase_like;
    $select_values[] = $phrase_like;
    $select_values[] = $phrase_like;

    $score_sql = "i.boost
        + CASE WHEN i.title = %s THEN 5000 ELSE 0 END
        + CASE WHEN i.title LIKE %s THEN 1800 ELSE 0 END
        + CASE WHEN i.excerpt LIKE %s THEN 700 ELSE 0 END
        + CASE WHEN i.search_text LIKE %s THEN 500 ELSE 0 END
        + CASE sig.signal_type
            WHEN 'pin' THEN 50000
            WHEN 'feature' THEN 12000
            WHEN 'boost' THEN 4000
            ELSE 0
        END";

    $token_where = [];
    $where_values = array_merge($post_types, [iss_graph_get_register_post_type()]);

    foreach ($tokens as $token) {
        $like = '%' . $wpdb->esc_like($token) . '%';

        $score_sql .= "
        + CASE WHEN i.title LIKE %s THEN 180 ELSE 0 END
        + CASE WHEN i.excerpt LIKE %s THEN 90 ELSE 0 END
        + CASE WHEN i.search_text LIKE %s THEN 45 ELSE 0 END";
        $select_values[] = $like;
        $select_values[] = $like;
        $select_values[] = $like;

        $token_where[] = '(i.title LIKE %s OR i.excerpt LIKE %s OR i.search_text LIKE %s)';
        $where_values[] = $like;
        $where_values[] = $like;
        $where_values[] = $like;
    }

    $from = "FROM {$table} i
        INNER JOIN {$wpdb->posts} p
            ON p.ID = i.target_post_id
        LEFT JOIN {$signal_table} sig
            ON sig.context_post_id = i.target_post_id
            AND sig.target_post_id = i.target_post_id
            AND sig.surface = 'search'
            AND sig.status = 'active'
            AND (sig.expires_at IS NULL OR sig.expires_at >= %s)
        WHERE i.is_public = 1
          AND p.post_status = 'publish'
          AND p.post_type IN ({$type_placeholders})
          AND p.post_password = ''
          AND (p.post_type <> %s OR NOT EXISTS (
              SELECT 1 FROM {$wpdb->postmeta} visibility
              WHERE visibility.post_id = p.ID AND visibility.meta_key = 'place_visibility' AND visibility.meta_value = 'tour_only'
          ))
          AND (sig.id IS NULL OR sig.signal_type NOT IN ('suppress', 'hide'))
          AND " . implode(' AND ', $token_where);
    $sql = "SELECT
            i.entity_id,
            i.target_post_id AS post_id,
            i.target_post_type,
            i.search_bucket,
            i.title,
            i.excerpt,
            CASE WHEN sig.signal_type = 'hide' THEN 'suppress' ELSE COALESCE(sig.signal_type, '') END AS editorial_signal,
            ({$score_sql}) AS relevance
        {$from}
        ORDER BY CASE sig.signal_type WHEN 'pin' THEN 1 ELSE 0 END DESC, relevance DESC, p.post_date_gmt DESC, i.target_post_id DESC
        LIMIT %d OFFSET %d";

    $where_params = array_merge([current_time('mysql', true)], $where_values);
    $params = array_merge($select_values, $where_params, [$limit, max(0, (int) ($args['offset'] ?? 0))]);
    $count_sql = "SELECT COUNT(*) {$from}";
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Fixed owner SQL with prepared values.
    return ['rows' => $wpdb->prepare($sql, $params), 'count' => $wpdb->prepare($count_sql, $where_params)];
}

/** One predicate set serves legacy bounded results and counted page reads. */
function iss_graph_read_public_search(string $search, array $args, bool $with_total): array
{
    global $wpdb;
    $sql = iss_graph_build_public_search_sql($search, $args);
    if (!$sql) { return ['items' => [], 'total' => 0]; }
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Prepared by the owner query builder; freshness includes current visibility/signals.
    $rows = $wpdb->get_results($sql['rows'], ARRAY_A);
    if (!is_array($rows)) { return ['items' => [], 'total' => 0]; }
    $items = array_values(array_map(static function (array $row): array {
        return [
            'entity_id' => (int) ($row['entity_id'] ?? 0),
            'post_id' => (int) ($row['post_id'] ?? 0),
            'post_type' => (string) ($row['target_post_type'] ?? ''),
            'search_bucket' => (string) ($row['search_bucket'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'excerpt' => (string) ($row['excerpt'] ?? ''),
            'relevance' => isset($row['relevance']) ? (int) $row['relevance'] : 0,
            'editorial_signal' => sanitize_key((string) ($row['editorial_signal'] ?? '')),
        ];
    }, $rows));
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery -- Same prepared visibility and token predicate as the result query.
    $total = $with_total ? (int) $wpdb->get_var($sql['count']) : count($items);
    return ['items' => $items, 'total' => $total];
}

function iss_graph_search_public_posts(string $search, array $args = []): array
{
    return iss_graph_read_public_search($search, $args, false)['items'];
}

function iss_graph_search_public_page(string $search, array $args = []): array
{
    $args['limit'] = max(1, min(100, (int) ($args['limit'] ?? 24)));
    return iss_graph_read_public_search($search, $args, true);
}


function iss_graph_search_public_post_ids(string $search, array $args = []): array
{
    $ids = array_map(static function (array $row): int {
        return (int) ($row['post_id'] ?? 0);
    }, iss_graph_search_public_posts($search, $args));

    return array_values(array_unique(array_filter($ids)));
}
