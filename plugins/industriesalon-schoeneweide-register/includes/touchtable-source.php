<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_touchtable_base_url(): string
{
    return 'https://touchtable.industriesalon.de';
}

function iss_register_touchtable_timeout(): int
{
    return 20;
}

function iss_register_touchtable_get(string $url)
{
    $response = wp_remote_get($url, [
        'timeout' => iss_register_touchtable_timeout(),
        'headers' => [
            'Accept' => 'application/json,text/html;q=0.9,*/*;q=0.8',
            'User-Agent' => 'Industriesalon Register Sync/' . ISS_REGISTER_VERSION,
        ],
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $status_code = (int) wp_remote_retrieve_response_code($response);
    if ($status_code < 200 || $status_code >= 300) {
        return new WP_Error('iss_register_touchtable_http_error', 'Touchtable request failed: ' . $url . ' (' . $status_code . ')');
    }

    return $response;
}

function iss_register_touchtable_get_json(string $url)
{
    $response = iss_register_touchtable_get($url);
    if (is_wp_error($response)) {
        return $response;
    }

    $body = (string) wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);

    if (!is_array($decoded)) {
        return new WP_Error('iss_register_touchtable_json_error', 'Touchtable JSON could not be decoded: ' . $url);
    }

    return [
        'body' => $decoded,
        'headers' => wp_remote_retrieve_headers($response),
    ];
}

function iss_register_touchtable_fetch_paginated_collection(string $route, array $query = [])
{
    $base_url = untrailingslashit(iss_register_touchtable_base_url()) . '/wp-json/wp/v2/' . ltrim($route, '/');
    $page = 1;
    $all_items = [];

    do {
        $request_query = array_merge($query, [
            'per_page' => 100,
            'page' => $page,
        ]);

        $url = add_query_arg($request_query, $base_url);
        $result = iss_register_touchtable_get_json($url);
        if (is_wp_error($result)) {
            return $result;
        }

        $items = $result['body'];
        if (!array_is_list($items)) {
            return new WP_Error('iss_register_touchtable_collection_error', 'Unexpected Touchtable collection response for: ' . $route);
        }

        foreach ($items as $item) {
            if (is_array($item)) {
                $all_items[] = $item;
            }
        }

        $headers = $result['headers'];
        $total_pages = (int) ($headers['x-wp-totalpages'] ?? 1);
        $page++;
    } while ($page <= max(1, $total_pages));

    return $all_items;
}

function iss_register_touchtable_fetch_pages()
{
    return iss_register_touchtable_fetch_paginated_collection('pages', [
        '_fields' => 'id,slug,link,parent,title,content,excerpt,featured_media,modified',
    ]);
}

function iss_register_touchtable_fetch_media()
{
    return iss_register_touchtable_fetch_paginated_collection('media', [
        '_fields' => 'id,slug,source_url,alt_text,media_type,mime_type,date,modified',
    ]);
}

function iss_register_touchtable_fetch_elementor_css(int $page_id)
{
    if ($page_id <= 0) {
        return new WP_Error('iss_register_touchtable_invalid_page_id', 'Invalid Touchtable page id.');
    }

    $url = untrailingslashit(iss_register_touchtable_base_url()) . '/wp-content/uploads/elementor/css/post-' . $page_id . '.css';
    $response = iss_register_touchtable_get($url);
    if (is_wp_error($response)) {
        return $response;
    }

    return (string) wp_remote_retrieve_body($response);
}

function iss_register_touchtable_guess_language(string $url): string
{
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    if ($path !== '' && preg_match('#^/en(/|$)#', $path)) {
        return 'en';
    }

    return 'de';
}

function iss_register_touchtable_strip_text($value): string
{
    if (!is_scalar($value)) {
        return '';
    }

    return trim(wp_strip_all_tags((string) $value));
}

function iss_register_touchtable_parse_hotspots_from_content(string $html): array
{
    if ($html === '' || strpos($html, 'wpr-image-hotspots') === false) {
        return [];
    }

    $wrapped_html = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
    $document = new DOMDocument();

    libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped_html);
    libxml_clear_errors();

    if (!$loaded) {
        return [];
    }

    $xpath = new DOMXPath($document);
    $items = [];

    foreach ($xpath->query('//div[contains(@class, "wpr-hotspot-item")]') as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $class_name = (string) $node->getAttribute('class');
        if (!preg_match('/elementor-repeater-item-([a-z0-9]+)/i', $class_name, $matches)) {
            continue;
        }

        $link_node = $xpath->query('.//a[contains(@class, "wpr-hotspot-content")]', $node)->item(0);
        $target_url = $link_node instanceof DOMElement ? trim((string) $link_node->getAttribute('href')) : '';
        $label = $link_node ? iss_register_touchtable_strip_text($link_node->textContent) : '';

        $items[] = [
            'repeater_id' => strtolower($matches[1]),
            'label' => $label,
            'target_url' => $target_url,
            'target_slug' => basename(untrailingslashit((string) wp_parse_url($target_url, PHP_URL_PATH))),
        ];
    }

    return $items;
}

function iss_register_touchtable_parse_map_image_url_from_content(string $html): string
{
    if ($html === '' || strpos($html, 'wpr-hotspot-image') === false) {
        return '';
    }

    if (preg_match('/<div class="wpr-hotspot-image">\s*<img[^>]+src="([^"]+)"/i', $html, $matches)) {
        return esc_url_raw($matches[1]);
    }

    return '';
}

function iss_register_touchtable_parse_hotspot_positions_from_css(string $css): array
{
    if ($css === '') {
        return [];
    }

    $positions = [];
    if (!preg_match_all('/\.elementor-repeater-item-([a-z0-9]+)\.wpr-hotspot-item\{left:([0-9.]+)%\;top:([0-9.]+)%\;\}/i', $css, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $match) {
        $positions[strtolower($match[1])] = [
            'x' => (float) $match[2],
            'y' => (float) $match[3],
        ];
    }

    return $positions;
}

function iss_register_touchtable_pages_by_id(array $pages): array
{
    $indexed = [];

    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }

        $page_id = (int) ($page['id'] ?? 0);
        if ($page_id > 0) {
            $indexed[$page_id] = $page;
        }
    }

    return $indexed;
}

function iss_register_touchtable_pages_by_url(array $pages): array
{
    $indexed = [];

    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }

        $url = trailingslashit((string) ($page['link'] ?? ''));
        if ($url !== '/') {
            $indexed[$url] = (int) ($page['id'] ?? 0);
        }
    }

    return $indexed;
}

function iss_register_touchtable_resolve_root_page(array $pages_by_id, int $page_id): ?array
{
    if ($page_id <= 0 || !isset($pages_by_id[$page_id])) {
        return null;
    }

    $current = $pages_by_id[$page_id];
    while (!empty($current['parent'])) {
        $parent_id = (int) $current['parent'];
        if ($parent_id <= 0 || !isset($pages_by_id[$parent_id])) {
            break;
        }
        $current = $pages_by_id[$parent_id];
    }

    return is_array($current) ? $current : null;
}

function iss_register_touchtable_snapshot_page(array $page, array $pages_by_id, array $pages_by_url): array
{
    $page_id = (int) ($page['id'] ?? 0);
    $content_rendered = (string) ($page['content']['rendered'] ?? '');
    $excerpt_rendered = (string) ($page['excerpt']['rendered'] ?? '');
    $has_hotspots = strpos($content_rendered, 'wpr-image-hotspots') !== false;
    $root_page = iss_register_touchtable_resolve_root_page($pages_by_id, $page_id);

    $hotspots = [];
    $raw_css = '';
    if ($has_hotspots) {
        $hotspots = iss_register_touchtable_parse_hotspots_from_content($content_rendered);
        $css = iss_register_touchtable_fetch_elementor_css($page_id);
        if (!is_wp_error($css)) {
            $raw_css = $css;
            $positions = iss_register_touchtable_parse_hotspot_positions_from_css($css);
            foreach ($hotspots as &$hotspot) {
                $repeater_id = (string) ($hotspot['repeater_id'] ?? '');
                if ($repeater_id !== '' && isset($positions[$repeater_id])) {
                    $hotspot['x'] = $positions[$repeater_id]['x'];
                    $hotspot['y'] = $positions[$repeater_id]['y'];
                }

                $target_url = trailingslashit((string) ($hotspot['target_url'] ?? ''));
                $hotspot['target_source_page_id'] = $pages_by_url[$target_url] ?? 0;
            }
            unset($hotspot);
        }
    }

    $source_url = (string) ($page['link'] ?? '');
    $source_kind = 'page';
    if ($has_hotspots) {
        $source_kind = 'map_page';
    } elseif (!empty($page['parent'])) {
        $source_kind = 'detail_page';
    } elseif ($page_id > 0) {
        $source_kind = 'landing_page';
    }

    $raw_payload = $page;
    if ($raw_css !== '') {
        $raw_payload['_elementor_css'] = $raw_css;
    }

    $title = iss_register_touchtable_strip_text($page['title']['rendered'] ?? '');
    $snapshot = [
        'source_system' => ISS_REGISTER_TOUCHTABLE_SOURCE,
        'source_key' => ISS_REGISTER_TOUCHTABLE_SOURCE . ':page:' . $page_id,
        'source_item_type' => 'page',
        'source_id' => $page_id,
        'source_parent_id' => (int) ($page['parent'] ?? 0),
        'source_url' => $source_url,
        'source_parent_url' => !empty($page['parent']) && isset($pages_by_id[(int) $page['parent']]['link'])
            ? (string) $pages_by_id[(int) $page['parent']]['link']
            : '',
        'source_language' => iss_register_touchtable_guess_language($source_url),
        'source_modified' => (string) ($page['modified'] ?? ''),
        'source_kind' => $source_kind,
        'title' => $title,
        'slug' => (string) ($page['slug'] ?? ''),
        'raw_payload' => $raw_payload,
        'extracted_payload' => [
            'title' => $title,
            'slug' => (string) ($page['slug'] ?? ''),
            'source_url' => $source_url,
            'source_kind' => $source_kind,
            'language' => iss_register_touchtable_guess_language($source_url),
            'modified' => (string) ($page['modified'] ?? ''),
            'root_page_id' => (int) ($root_page['id'] ?? 0),
            'root_page_slug' => (string) ($root_page['slug'] ?? ''),
            'root_page_title' => iss_register_touchtable_strip_text($root_page['title']['rendered'] ?? ''),
            'excerpt_text' => iss_register_touchtable_strip_text($excerpt_rendered),
            'content_text' => iss_register_touchtable_strip_text($content_rendered),
            'featured_media' => (int) ($page['featured_media'] ?? 0),
            'map_image_url' => iss_register_touchtable_parse_map_image_url_from_content($content_rendered),
            'hotspots' => $hotspots,
        ],
    ];

    $snapshot['source_hash'] = md5(wp_json_encode([
        'page' => $snapshot['raw_payload'],
        'extracted' => $snapshot['extracted_payload'],
    ]));

    return $snapshot;
}

function iss_register_touchtable_snapshot_media(array $item): array
{
    $media_id = (int) ($item['id'] ?? 0);
    $source_url = (string) ($item['source_url'] ?? '');
    $title = trim((string) ($item['slug'] ?? ''));

    $snapshot = [
        'source_system' => ISS_REGISTER_TOUCHTABLE_SOURCE,
        'source_key' => ISS_REGISTER_TOUCHTABLE_SOURCE . ':media:' . $media_id,
        'source_item_type' => 'media',
        'source_id' => $media_id,
        'source_parent_id' => 0,
        'source_url' => $source_url,
        'source_parent_url' => '',
        'source_language' => iss_register_touchtable_guess_language($source_url),
        'source_modified' => (string) ($item['modified'] ?? ($item['date'] ?? '')),
        'source_kind' => 'media',
        'title' => $title !== '' ? $title : ('media-' . $media_id),
        'slug' => (string) ($item['slug'] ?? ''),
        'raw_payload' => $item,
        'extracted_payload' => [
            'slug' => (string) ($item['slug'] ?? ''),
            'source_url' => $source_url,
            'language' => iss_register_touchtable_guess_language($source_url),
            'alt_text' => (string) ($item['alt_text'] ?? ''),
            'media_type' => (string) ($item['media_type'] ?? ''),
            'mime_type' => (string) ($item['mime_type'] ?? ''),
            'modified' => (string) ($item['modified'] ?? ($item['date'] ?? '')),
        ],
    ];

    $snapshot['source_hash'] = md5(wp_json_encode([
        'media' => $snapshot['raw_payload'],
        'extracted' => $snapshot['extracted_payload'],
    ]));

    return $snapshot;
}

function iss_register_touchtable_collect_snapshots()
{
    $pages = iss_register_touchtable_fetch_pages();
    if (is_wp_error($pages)) {
        return $pages;
    }

    $media = iss_register_touchtable_fetch_media();
    if (is_wp_error($media)) {
        return $media;
    }

    $pages_by_id = iss_register_touchtable_pages_by_id($pages);
    $pages_by_url = iss_register_touchtable_pages_by_url($pages);

    $page_snapshots = [];
    foreach ($pages as $page) {
        if (!is_array($page)) {
            continue;
        }
        $page_snapshots[] = iss_register_touchtable_snapshot_page($page, $pages_by_id, $pages_by_url);
    }

    $media_snapshots = [];
    foreach ($media as $item) {
        if (!is_array($item)) {
            continue;
        }
        $media_snapshots[] = iss_register_touchtable_snapshot_media($item);
    }

    return [
        'pages' => $page_snapshots,
        'media' => $media_snapshots,
    ];
}

function iss_register_touchtable_pull_snapshots(array $options = []): array
{
    $dry_run = !empty($options['dry_run']);
    $collected = iss_register_touchtable_collect_snapshots();

    if (is_wp_error($collected)) {
        return [
            'dry_run' => $dry_run,
            'error' => $collected->get_error_message(),
            'stats' => [
                'pages' => iss_register_source_snapshot_stats_seed(),
                'media' => iss_register_source_snapshot_stats_seed(),
            ],
        ];
    }

    $stats = [
        'pages' => iss_register_source_snapshot_stats_seed(),
        'media' => iss_register_source_snapshot_stats_seed(),
    ];

    foreach ($collected['pages'] as $snapshot) {
        $stats['pages']['total']++;
        iss_register_upsert_source_snapshot($snapshot, $stats['pages'], $dry_run);
    }

    foreach ($collected['media'] as $snapshot) {
        $stats['media']['total']++;
        iss_register_upsert_source_snapshot($snapshot, $stats['media'], $dry_run);
    }

    return [
        'dry_run' => $dry_run,
        'error' => '',
        'stats' => $stats,
    ];
}
