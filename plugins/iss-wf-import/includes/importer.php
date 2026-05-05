<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_api_base(): string
{
    return untrailingslashit((string) apply_filters('iss_wf_import_api_base', 'https://wf-museum.de/wp-json/wp/v2'));
}

function iss_wf_import_remote_host(): string
{
    return 'wf-museum.de';
}

function iss_wf_import_allowed_media_hosts(): array
{
    $hosts = [
        iss_wf_import_remote_host(),
        'berlin.museum-digital.de',
        'asset.museum-digital.org',
    ];

    $hosts = apply_filters('iss_wf_import_allowed_media_hosts', $hosts);

    return array_values(array_unique(array_filter(array_map(static function ($host): string {
        return strtolower(trim((string) $host));
    }, is_array($hosts) ? $hosts : []))));
}

function iss_wf_import_http_args(): array
{
    return [
        'timeout' => 45,
        'redirection' => 5,
        'user-agent' => 'ISS-WF-Import/' . ISS_WF_IMPORT_VERSION . '; ' . home_url('/'),
    ];
}

function iss_wf_import_remote_term_meta_key(): string
{
    return '_iss_wf_remote_term_id';
}

function iss_wf_import_fetch_remote_json(string $path, array $query = [])
{
    $url = add_query_arg($query, iss_wf_import_api_base() . '/' . ltrim($path, '/'));
    $response = wp_remote_get($url, iss_wf_import_http_args());

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return new WP_Error(
            'iss_wf_import_http_error',
            sprintf('WF import request failed with status %d for %s', $code, $url)
        );
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data)) {
        return new WP_Error('iss_wf_import_invalid_json', sprintf('WF import returned invalid JSON for %s', $url));
    }

    return [
        'items' => $data,
        'total' => (int) wp_remote_retrieve_header($response, 'x-wp-total'),
        'total_pages' => (int) wp_remote_retrieve_header($response, 'x-wp-totalpages'),
    ];
}

function iss_wf_import_fetch_remote_post(int $remote_post_id)
{
    $path = 'posts/' . $remote_post_id;
    $url = add_query_arg(['_embed' => '1'], iss_wf_import_api_base() . '/' . $path);
    $response = wp_remote_get($url, iss_wf_import_http_args());

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return new WP_Error(
            'iss_wf_import_http_error',
            sprintf('WF import request failed with status %d for %s', $code, $url)
        );
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data) || empty($data['id'])) {
        return new WP_Error('iss_wf_import_invalid_post', sprintf('WF import returned no post payload for %s', $url));
    }

    return $data;
}

function iss_wf_import_fetch_all_remote_terms(string $path)
{
    $page = 1;
    $items = [];

    do {
        $result = iss_wf_import_fetch_remote_json($path, [
            'per_page' => 100,
            'page' => $page,
            'orderby' => 'id',
            'order' => 'asc',
        ]);

        if (is_wp_error($result)) {
            return $result;
        }

        $items = array_merge($items, $result['items']);
        $page++;
        $total_pages = max(1, (int) ($result['total_pages'] ?? 1));
    } while ($page <= $total_pages);

    return $items;
}

function iss_wf_import_sync_remote_terms(string $path, string $taxonomy, bool $hierarchical = false): array
{
    $remote_terms = iss_wf_import_fetch_all_remote_terms($path);
    if (is_wp_error($remote_terms)) {
        return [];
    }

    $map = [];

    foreach ($remote_terms as $remote_term) {
        if (!is_array($remote_term) || empty($remote_term['id']) || empty($remote_term['name'])) {
            continue;
        }

        $slug = sanitize_title((string) ($remote_term['slug'] ?? $remote_term['name']));
        $term = get_term_by('slug', $slug, $taxonomy);

        if (!$term instanceof WP_Term) {
            $created = wp_insert_term((string) $remote_term['name'], $taxonomy, [
                'slug' => $slug,
                'description' => (string) ($remote_term['description'] ?? ''),
            ]);

            if (is_wp_error($created)) {
                continue;
            }

            $term = get_term((int) $created['term_id'], $taxonomy);
        } else {
            wp_update_term($term->term_id, $taxonomy, [
                'name' => (string) $remote_term['name'],
                'slug' => $slug,
                'description' => (string) ($remote_term['description'] ?? ''),
            ]);
        }

        if (!$term instanceof WP_Term) {
            continue;
        }

        update_term_meta($term->term_id, iss_wf_import_remote_term_meta_key(), (int) $remote_term['id']);
        $map[(int) $remote_term['id']] = (int) $term->term_id;
    }

    if ($hierarchical && $map) {
        foreach ($remote_terms as $remote_term) {
            $remote_id = (int) ($remote_term['id'] ?? 0);
            $parent_remote_id = (int) ($remote_term['parent'] ?? 0);

            if ($remote_id <= 0 || $parent_remote_id <= 0 || empty($map[$remote_id]) || empty($map[$parent_remote_id])) {
                continue;
            }

            $local_term = get_term($map[$remote_id], $taxonomy);
            if (!$local_term instanceof WP_Term || (int) $local_term->parent === (int) $map[$parent_remote_id]) {
                continue;
            }

            wp_update_term($local_term->term_id, $taxonomy, [
                'parent' => (int) $map[$parent_remote_id],
            ]);
        }
    }

    return $map;
}

function iss_wf_import_find_post_by_remote_id(int $remote_post_id): int
{
    if ($remote_post_id <= 0) {
        return 0;
    }

    $posts = get_posts([
        'post_type' => ISS_WF_IMPORT_POST_TYPE,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'suppress_filters' => true,
        'meta_query' => [
            [
                'key' => ISS_WF_IMPORT_REMOTE_POST_ID_META,
                'value' => $remote_post_id,
                'compare' => '=',
                'type' => 'NUMERIC',
            ],
        ],
    ]);

    return $posts ? (int) $posts[0] : 0;
}

function iss_wf_import_post_hash(array $remote_post): string
{
    return md5(wp_json_encode([
        'id' => (int) ($remote_post['id'] ?? 0),
        'slug' => (string) ($remote_post['slug'] ?? ''),
        'modified_gmt' => (string) ($remote_post['modified_gmt'] ?? ''),
        'title' => (string) ($remote_post['title']['rendered'] ?? ''),
        'excerpt' => (string) ($remote_post['excerpt']['rendered'] ?? ''),
        'content' => (string) ($remote_post['content']['rendered'] ?? ''),
        'featured_media' => (int) ($remote_post['featured_media'] ?? 0),
    ]));
}

function iss_wf_import_remote_author_name(array $remote_post): string
{
    $embedded_author = $remote_post['_embedded']['author'][0]['name'] ?? '';

    return sanitize_text_field((string) $embedded_author);
}

function iss_wf_import_remote_featured_media_url(array $remote_post): string
{
    $media = $remote_post['_embedded']['wp:featuredmedia'][0] ?? null;
    if (!is_array($media)) {
        return '';
    }

    return esc_url_raw((string) ($media['source_url'] ?? ''));
}

function iss_wf_import_prepare_media_includes(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $loaded = true;
}

function iss_wf_import_should_localize_media_url(string $url): bool
{
    $url = trim($url);
    if ($url === '') {
        return false;
    }

    $parts = wp_parse_url($url);
    $host = strtolower((string) ($parts['host'] ?? ''));

    if ($host === '') {
        return false;
    }

    foreach (iss_wf_import_allowed_media_hosts() as $allowed_host) {
        if ($allowed_host === '') {
            continue;
        }

        if ($host === $allowed_host || str_ends_with($host, '.' . $allowed_host)) {
            return true;
        }
    }

    return false;
}

function iss_wf_import_assign_source_term(int $post_id, string $slug, string $label): void
{
    $term_id = iss_wf_import_ensure_source_term_by_slug($slug, $label);
    if ($term_id > 0) {
        wp_set_object_terms($post_id, [$term_id], ISS_WF_IMPORT_SOURCE_TAXONOMY, false);
    }
}

function iss_wf_import_find_post_by_source_identity(string $post_type, string $source_kind, string $source_external_id): int
{
    $post_type = sanitize_key($post_type);
    $source_kind = sanitize_key($source_kind);
    $source_external_id = sanitize_text_field($source_external_id);

    if ($post_type === '' || $source_kind === '' || $source_external_id === '') {
        return 0;
    }

    $posts = get_posts([
        'post_type' => $post_type,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'suppress_filters' => true,
        'meta_query' => [
            'relation' => 'AND',
            [
                'key' => ISS_WF_IMPORT_SOURCE_KIND_META,
                'value' => $source_kind,
                'compare' => '=',
            ],
            [
                'key' => ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META,
                'value' => $source_external_id,
                'compare' => '=',
            ],
        ],
    ]);

    return $posts ? (int) $posts[0] : 0;
}

function iss_wf_import_find_attachment_by_source_url(string $url): int
{
    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'suppress_filters' => true,
        'meta_query' => [
            [
                'key' => ISS_WF_IMPORT_ATTACHMENT_SOURCE_URL_META,
                'value' => esc_url_raw($url),
                'compare' => '=',
            ],
        ],
    ]);

    return $attachments ? (int) $attachments[0] : 0;
}

function iss_wf_import_get_or_create_attachment(string $url, int $parent_post_id, string $title = ''): int
{
    if (!iss_wf_import_should_localize_media_url($url)) {
        return 0;
    }

    $existing = iss_wf_import_find_attachment_by_source_url($url);
    if ($existing > 0) {
        return $existing;
    }

    iss_wf_import_prepare_media_includes();

    $tmp = download_url($url, 45);
    if (is_wp_error($tmp)) {
        return 0;
    }

    $file_array = [
        'name' => wp_basename((string) wp_parse_url($url, PHP_URL_PATH)),
        'tmp_name' => $tmp,
    ];

    $attachment_id = media_handle_sideload($file_array, $parent_post_id, $title);
    if (is_wp_error($attachment_id)) {
        @unlink($tmp);
        return 0;
    }

    update_post_meta($attachment_id, ISS_WF_IMPORT_ATTACHMENT_SOURCE_URL_META, esc_url_raw($url));

    return (int) $attachment_id;
}

function iss_wf_import_dom_inner_html(DOMNode $node): string
{
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }

    return $html;
}

function iss_wf_import_localize_content_media(string $html, int $parent_post_id): string
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return $html;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $content_html = function_exists('mb_convert_encoding')
        ? mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')
        : $html;
    $wrapped = '<!DOCTYPE html><html><body><div data-iss-wf-root="1">' . $content_html . '</div></body></html>';

    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return $html;
    }

    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//*[@data-iss-wf-root="1"]');
    if (!$nodes || $nodes->length < 1) {
        return $html;
    }

    $root = $nodes->item(0);
    if (!$root instanceof DOMNode) {
        return $html;
    }

    $images = [];
    foreach ($root->getElementsByTagName('img') as $img) {
        $images[] = $img;
    }

    foreach ($images as $img) {
        if (!$img instanceof DOMElement) {
            continue;
        }

        $source_url = html_entity_decode(trim($img->getAttribute('src')));
        if ($source_url === '') {
            continue;
        }

        $attachment_id = iss_wf_import_get_or_create_attachment($source_url, $parent_post_id);
        if ($attachment_id <= 0) {
            continue;
        }

        $local_url = (string) wp_get_attachment_url($attachment_id);
        if ($local_url === '') {
            continue;
        }

        $img->setAttribute('src', $local_url);
        $img->removeAttribute('srcset');
        $img->removeAttribute('sizes');
        $img->removeAttribute('data-large-file');
        $img->removeAttribute('data-medium-file');

        $class_name = trim((string) $img->getAttribute('class'));
        $class_name = trim((string) preg_replace('/\bwp-image-\d+\b/', '', $class_name));
        $class_name = trim($class_name . ' wp-image-' . $attachment_id);
        if ($class_name !== '') {
            $img->setAttribute('class', preg_replace('/\s+/', ' ', $class_name));
        }

        $parent = $img->parentNode;
        if ($parent instanceof DOMElement && strtolower($parent->tagName) === 'a') {
            $href = html_entity_decode(trim($parent->getAttribute('href')));
            if ($href === $source_url) {
                $parent->setAttribute('href', $local_url);
            }
        }
    }

    return iss_wf_import_dom_inner_html($root);
}

function iss_wf_import_assign_terms(int $post_id, array $remote_post, array $category_map, array $tag_map): void
{
    iss_wf_import_assign_source_term($post_id, 'wf-museum', 'WF-Museum');

    $category_term_ids = [];
    foreach ((array) ($remote_post['categories'] ?? []) as $remote_category_id) {
        $remote_category_id = (int) $remote_category_id;
        if (!empty($category_map[$remote_category_id])) {
            $category_term_ids[] = (int) $category_map[$remote_category_id];
        }
    }
    wp_set_object_terms($post_id, array_values(array_unique(array_filter($category_term_ids))), ISS_WF_IMPORT_CATEGORY_TAXONOMY, false);

    $tag_term_ids = [];
    foreach ((array) ($remote_post['tags'] ?? []) as $remote_tag_id) {
        $remote_tag_id = (int) $remote_tag_id;
        if (!empty($tag_map[$remote_tag_id])) {
            $tag_term_ids[] = (int) $tag_map[$remote_tag_id];
        }
    }
    wp_set_object_terms($post_id, array_values(array_unique(array_filter($tag_term_ids))), ISS_WF_IMPORT_TAG_TAXONOMY, false);
}

function iss_wf_import_upsert_post(array $remote_post, array $context = []): array
{
    $remote_post_id = (int) ($remote_post['id'] ?? 0);
    if ($remote_post_id <= 0) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => 'Missing remote post id.',
        ];
    }

    $existing_post_id = iss_wf_import_find_post_by_remote_id($remote_post_id);
    $force = !empty($context['force']);
    $media_mode = in_array(($context['media'] ?? 'all'), ['all', 'featured', 'none'], true) ? (string) $context['media'] : 'all';

    $hash = iss_wf_import_post_hash($remote_post);
    if ($existing_post_id > 0 && !$force) {
        $existing_hash = (string) get_post_meta($existing_post_id, ISS_WF_IMPORT_HASH_META, true);
        if ($existing_hash === $hash) {
            iss_wf_import_assign_terms(
                $existing_post_id,
                $remote_post,
                (array) ($context['category_map'] ?? []),
                (array) ($context['tag_map'] ?? [])
            );
            update_post_meta($existing_post_id, ISS_WF_IMPORT_LAST_SYNCED_META, current_time('mysql', true));

            return [
                'status' => 'skipped',
                'post_id' => $existing_post_id,
                'message' => 'Unchanged.',
            ];
        }
    }

    $raw_content = (string) ($remote_post['content']['rendered'] ?? '');
    $title = wp_strip_all_tags(html_entity_decode((string) ($remote_post['title']['rendered'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    $excerpt = wp_strip_all_tags((string) ($remote_post['excerpt']['rendered'] ?? ''));

    $postarr = [
        'post_type' => ISS_WF_IMPORT_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => sanitize_title((string) ($remote_post['slug'] ?? '')),
        'post_content' => $raw_content,
        'post_excerpt' => $excerpt,
        'post_date' => (string) ($remote_post['date'] ?? ''),
        'post_date_gmt' => (string) ($remote_post['date_gmt'] ?? ''),
        'post_modified' => (string) ($remote_post['modified'] ?? ''),
        'post_modified_gmt' => (string) ($remote_post['modified_gmt'] ?? ''),
        'meta_input' => [
            ISS_WF_IMPORT_REMOTE_POST_ID_META => $remote_post_id,
            ISS_WF_IMPORT_SOURCE_SITE_META => 'wf-museum',
            ISS_WF_IMPORT_SOURCE_URL_META => esc_url_raw((string) ($remote_post['link'] ?? '')),
            ISS_WF_IMPORT_SOURCE_SLUG_META => sanitize_title((string) ($remote_post['slug'] ?? '')),
            ISS_WF_IMPORT_SOURCE_DATE_GMT_META => sanitize_text_field((string) ($remote_post['date_gmt'] ?? '')),
            ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META => sanitize_text_field((string) ($remote_post['modified_gmt'] ?? '')),
            ISS_WF_IMPORT_SOURCE_AUTHOR_META => iss_wf_import_remote_author_name($remote_post),
            ISS_WF_IMPORT_HASH_META => $hash,
            ISS_WF_IMPORT_LAST_SYNCED_META => current_time('mysql', true),
        ],
    ];

    if ($existing_post_id > 0) {
        $postarr['ID'] = $existing_post_id;
        $post_id = wp_update_post($postarr, true);
        $status = 'updated';
    } else {
        $post_id = wp_insert_post($postarr, true);
        $status = 'imported';
    }

    if (is_wp_error($post_id)) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => $post_id->get_error_message(),
        ];
    }

    $post_id = (int) $post_id;

    iss_wf_import_assign_terms(
        $post_id,
        $remote_post,
        (array) ($context['category_map'] ?? []),
        (array) ($context['tag_map'] ?? [])
    );

    if ($media_mode === 'all') {
        $localized_content = iss_wf_import_localize_content_media($raw_content, $post_id);
        if ($localized_content !== $raw_content) {
            wp_update_post([
                'ID' => $post_id,
                'post_content' => $localized_content,
            ]);
        }
    }

    if ($media_mode === 'all' || $media_mode === 'featured') {
        $featured_url = iss_wf_import_remote_featured_media_url($remote_post);
        if ($featured_url !== '') {
            $featured_attachment_id = iss_wf_import_get_or_create_attachment($featured_url, $post_id, $title);
            if ($featured_attachment_id > 0) {
                set_post_thumbnail($post_id, $featured_attachment_id);
            }
        } else {
            delete_post_thumbnail($post_id);
        }
    }

    if (function_exists('iss_wf_import_refresh_suggestions_for_post')) {
        iss_wf_import_refresh_suggestions_for_post($post_id);
    }

    return [
        'status' => $status,
        'post_id' => $post_id,
        'message' => '',
    ];
}

function iss_wf_import_sync(array $options = []): array
{
    $per_page = max(1, min(100, absint($options['per_page'] ?? 100)));
    $page = max(1, absint($options['page'] ?? 1));
    $limit = max(0, absint($options['limit'] ?? 0));
    $remote_post_id = max(0, absint($options['remote_id'] ?? 0));

    $category_map = iss_wf_import_sync_remote_terms('categories', ISS_WF_IMPORT_CATEGORY_TAXONOMY, true);
    $tag_map = iss_wf_import_sync_remote_terms('tags', ISS_WF_IMPORT_TAG_TAXONOMY, false);

    $stats = [
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'processed' => 0,
        'post_ids' => [],
        'errors' => [],
        'total_remote' => 0,
    ];

    if ($remote_post_id > 0) {
        $remote_post = iss_wf_import_fetch_remote_post($remote_post_id);
        if (is_wp_error($remote_post)) {
            $stats['failed'] = 1;
            $stats['errors'][] = $remote_post->get_error_message();
            return $stats;
        }

        $result = iss_wf_import_upsert_post($remote_post, array_merge($options, [
            'category_map' => $category_map,
            'tag_map' => $tag_map,
        ]));

        $stats[$result['status']] = isset($stats[$result['status']]) ? $stats[$result['status']] + 1 : 1;
        $stats['processed'] = 1;
        if (!empty($result['post_id'])) {
            $stats['post_ids'][] = (int) $result['post_id'];
        }
        if (!empty($result['message']) && $result['status'] === 'failed') {
            $stats['errors'][] = $result['message'];
        }

        return $stats;
    }

    $current_page = $page;
    $total_pages = $page;

    do {
        $response = iss_wf_import_fetch_remote_json('posts', [
            '_embed' => '1',
            'per_page' => $per_page,
            'page' => $current_page,
            'orderby' => 'date',
            'order' => 'desc',
        ]);

        if (is_wp_error($response)) {
            $stats['errors'][] = $response->get_error_message();
            break;
        }

        $stats['total_remote'] = max($stats['total_remote'], (int) ($response['total'] ?? 0));
        $total_pages = max($current_page, (int) ($response['total_pages'] ?? $current_page));

        foreach ((array) ($response['items'] ?? []) as $remote_post) {
            if (!is_array($remote_post) || empty($remote_post['id'])) {
                continue;
            }

            $result = iss_wf_import_upsert_post($remote_post, array_merge($options, [
                'category_map' => $category_map,
                'tag_map' => $tag_map,
            ]));

            if (isset($stats[$result['status']])) {
                $stats[$result['status']]++;
            }

            $stats['processed']++;
            if (!empty($result['post_id'])) {
                $stats['post_ids'][] = (int) $result['post_id'];
            }
            if (!empty($result['message']) && $result['status'] === 'failed') {
                $stats['errors'][] = sprintf('Remote %d: %s', (int) $remote_post['id'], $result['message']);
            }

            if ($limit > 0 && $stats['processed'] >= $limit) {
                break 2;
            }
        }

        $current_page++;
    } while ($current_page <= $total_pages);

    $stats['post_ids'] = array_values(array_unique(array_filter(array_map('absint', $stats['post_ids']))));

    return $stats;
}
