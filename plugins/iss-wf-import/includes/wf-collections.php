<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_fetch_remote_html(string $url)
{
    $url = esc_url_raw($url);
    if ($url === '') {
        return new WP_Error('iss_wf_import_invalid_url', 'Missing WF collection URL.');
    }

    $response = wp_remote_get($url, iss_wf_import_http_args());

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    if ($code < 200 || $code >= 300) {
        return new WP_Error(
            'iss_wf_import_http_error',
            sprintf('WF collection request failed with status %d for %s', $code, $url)
        );
    }

    return (string) wp_remote_retrieve_body($response);
}

function iss_wf_import_load_html_document(string $html): ?DOMDocument
{
    if ($html === '' || !class_exists('DOMDocument')) {
        return null;
    }

    $dom = new DOMDocument('1.0', 'UTF-8');
    $content = function_exists('mb_convert_encoding')
        ? mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8')
        : $html;

    $previous = libxml_use_internal_errors(true);
    $loaded = $dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return $loaded ? $dom : null;
}

function iss_wf_import_dom_xpath_contains_class(string $class_name): string
{
    return "contains(concat(' ', normalize-space(@class), ' '), ' " . $class_name . " ')";
}

function iss_wf_import_dom_first_node_text(DOMXPath $xpath, string $query): string
{
    $nodes = $xpath->query($query);
    if (!$nodes || $nodes->length < 1) {
        return '';
    }

    $node = $nodes->item(0);
    if (!$node instanceof DOMNode) {
        return '';
    }

    return trim((string) $node->textContent);
}

function iss_wf_import_dom_first_meta_content(DOMXPath $xpath, string $property): string
{
    $nodes = $xpath->query(sprintf('//meta[@property="%s" or @name="%s"]/@content', $property, $property));
    if (!$nodes || $nodes->length < 1) {
        return '';
    }

    return trim((string) $nodes->item(0)->nodeValue);
}

function iss_wf_import_wf_content_root(DOMXPath $xpath): ?DOMNode
{
    $queries = [
        '//*[' . iss_wf_import_dom_xpath_contains_class('entry-content') . ']',
        '//article',
        '//main',
        '//body',
    ];

    foreach ($queries as $query) {
        $nodes = $xpath->query($query);
        if (!$nodes || $nodes->length < 1) {
            continue;
        }

        $node = $nodes->item(0);
        if ($node instanceof DOMNode) {
            return $node;
        }
    }

    return null;
}

function iss_wf_import_dom_closest_element(?DOMNode $node, array $tag_names): ?DOMElement
{
    $tag_names = array_map('strtolower', $tag_names);

    while ($node) {
        if ($node instanceof DOMElement && in_array(strtolower($node->tagName), $tag_names, true)) {
            return $node;
        }

        $node = $node->parentNode;
    }

    return null;
}

function iss_wf_import_dom_image_source(DOMElement $img): string
{
    $candidates = [
        html_entity_decode(trim($img->getAttribute('data-src'))),
        html_entity_decode(trim($img->getAttribute('src'))),
    ];

    foreach ($candidates as $url) {
        if ($url === '' || str_starts_with($url, 'data:image/gif')) {
            continue;
        }

        return esc_url_raw($url);
    }

    return '';
}

function iss_wf_import_dom_text_from_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $html = preg_replace('~<br\s*/?>~i', "\n", $html);
    $html = preg_replace('~</(p|div|li|tr|td|figure|figcaption)>~i', "\n", $html);
    $text = html_entity_decode(wp_strip_all_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/[ \t]+\n/u", "\n", $text);
    $text = preg_replace("/\n{3,}/u", "\n\n", $text);

    return trim((string) $text);
}

function iss_wf_import_is_media_asset_url(string $url): bool
{
    $path = strtolower((string) wp_parse_url($url, PHP_URL_PATH));
    if ($path === '') {
        return false;
    }

    return (bool) preg_match('/\.(avif|gif|jpe?g|png|svg|tiff?|webp)$/', $path);
}

function iss_wf_import_wf_collection_source_id(string $url): string
{
    $path = (string) wp_parse_url($url, PHP_URL_PATH);
    $path = trim($path);

    if ($path === '') {
        return md5($url);
    }

    return untrailingslashit($path);
}

function iss_wf_import_collect_md_object_ids(DOMNode $root): array
{
    $object_ids = [];
    $seen = [];

    if (!$root instanceof DOMElement && !$root instanceof DOMDocument) {
        return [];
    }

    $links = $root->getElementsByTagName('a');
    foreach ($links as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }

        $href = html_entity_decode(trim($link->getAttribute('href')));
        $object_id = iss_wf_import_extract_md_object_id_from_url($href);

        if ($object_id <= 0 || isset($seen[$object_id])) {
            continue;
        }

        $seen[$object_id] = true;
        $object_ids[] = $object_id;
    }

    return $object_ids;
}

function iss_wf_import_collect_md_collection_ids(DOMNode $root): array
{
    $collection_ids = [];
    $seen = [];

    if (!$root instanceof DOMElement && !$root instanceof DOMDocument) {
        return [];
    }

    $links = $root->getElementsByTagName('a');
    foreach ($links as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }

        $href = html_entity_decode(trim($link->getAttribute('href')));
        $collection_id = iss_wf_import_extract_md_collection_id_from_url($href);

        if ($collection_id <= 0 || isset($seen[$collection_id])) {
            continue;
        }

        $seen[$collection_id] = true;
        $collection_ids[] = $collection_id;
    }

    return $collection_ids;
}

function iss_wf_import_collect_wf_md_items(DOMNode $root): array
{
    $items = [];
    $seen = [];

    if (!$root instanceof DOMElement && !$root instanceof DOMDocument) {
        return [];
    }

    $links = $root->getElementsByTagName('a');
    foreach ($links as $link) {
        if (!$link instanceof DOMElement) {
            continue;
        }

        $href = html_entity_decode(trim($link->getAttribute('href')));
        $object_id = iss_wf_import_extract_md_object_id_from_url($href);
        if ($object_id <= 0 || isset($seen[$object_id])) {
            continue;
        }

        $title = iss_wf_import_dom_text_from_html(iss_wf_import_dom_inner_html($link));
        $caption = '';
        $page_label = '';

        $row = iss_wf_import_dom_closest_element($link, ['tr']);
        if ($row instanceof DOMElement) {
            $cells = [];
            foreach ($row->childNodes as $child) {
                if ($child instanceof DOMElement && strtolower($child->tagName) === 'td') {
                    $cells[] = $child;
                }
            }

            if (!empty($cells[0])) {
                $page_label = iss_wf_import_dom_text_from_html(iss_wf_import_dom_inner_html($cells[0]));
            }

            if (!empty($cells[2])) {
                $caption = iss_wf_import_dom_text_from_html(iss_wf_import_dom_inner_html($cells[2]));
            }
        }

        if ($caption === '') {
            $figure = iss_wf_import_dom_closest_element($link, ['figure']);
            if ($figure instanceof DOMElement) {
                foreach ($figure->getElementsByTagName('figcaption') as $figcaption) {
                    $caption = iss_wf_import_dom_text_from_html(iss_wf_import_dom_inner_html($figcaption));
                    if ($caption !== '') {
                        break;
                    }
                }
            }
        }

        if ($title === '') {
            foreach ($link->getElementsByTagName('img') as $img) {
                if (!$img instanceof DOMElement) {
                    continue;
                }

                $title = sanitize_text_field((string) $img->getAttribute('alt'));
                if ($title !== '') {
                    break;
                }
            }
        }

        $seen[$object_id] = true;
        $items[] = [
            'source_object_id' => (string) $object_id,
            'source_url' => $href,
            'title' => $title,
            'caption_override' => $caption,
            'page_label' => $page_label,
        ];
    }

    return $items;
}

function iss_wf_import_collect_wf_foogallery_items(DOMNode $root): array
{
    if (!$root instanceof DOMElement && !$root instanceof DOMDocument) {
        return [];
    }

    $document = $root instanceof DOMDocument ? $root : $root->ownerDocument;
    if (!$document instanceof DOMDocument) {
        return [];
    }

    $xpath = new DOMXPath($document);
    $nodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " fg-item ")]//a[contains(concat(" ", normalize-space(@class), " "), " fg-thumb ")]', $root);
    if (!$nodes || $nodes->length < 1) {
        return [];
    }

    $items = [];
    $seen = [];

    foreach ($nodes as $node) {
        if (!$node instanceof DOMElement) {
            continue;
        }

        $source_url = esc_url_raw(html_entity_decode(trim($node->getAttribute('href'))));
        if ($source_url === '' || isset($seen[$source_url]) || !iss_wf_import_should_localize_media_url($source_url)) {
            continue;
        }

        $caption = sanitize_textarea_field((string) $node->getAttribute('data-caption-title'));
        $figure = iss_wf_import_dom_closest_element($node, ['figure']);
        if ($caption === '' && $figure instanceof DOMElement) {
            foreach ($figure->getElementsByTagName('figcaption') as $figcaption) {
                $caption = iss_wf_import_dom_text_from_html(iss_wf_import_dom_inner_html($figcaption));
                if ($caption !== '') {
                    break;
                }
            }
        }

        $title = sanitize_text_field((string) $node->getAttribute('title'));
        if ($title === '' && $caption !== '') {
            $title = $caption;
        }

        foreach ($node->getElementsByTagName('img') as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }

            if ($title === '') {
                $title = sanitize_text_field((string) $img->getAttribute('title'));
            }

            if ($title === '') {
                $title = sanitize_text_field((string) $img->getAttribute('alt'));
            }

            if ($title !== '') {
                break;
            }
        }

        $seen[$source_url] = true;
        $items[] = [
            'source_url' => $source_url,
            'title' => $title,
            'caption' => $caption,
            'description' => $caption,
        ];
    }

    return $items;
}

function iss_wf_import_collect_wf_child_links(DOMNode $root, string $current_url): array
{
    $current_host = strtolower((string) wp_parse_url($current_url, PHP_URL_HOST));
    $current_path = untrailingslashit((string) wp_parse_url($current_url, PHP_URL_PATH));
    $parent_path = trailingslashit((string) dirname($current_path));
    $links = [];
    $seen = [];

    if ($current_host === '' || $current_path === '') {
        return [];
    }

    $anchors = $root->getElementsByTagName('a');
    foreach ($anchors as $anchor) {
        if (!$anchor instanceof DOMElement) {
            continue;
        }

        $href = html_entity_decode(trim($anchor->getAttribute('href')));
        if ($href === '') {
            continue;
        }

        $host = strtolower((string) wp_parse_url($href, PHP_URL_HOST));
        $path = untrailingslashit((string) wp_parse_url($href, PHP_URL_PATH));

        if ($host === '' || $path === '' || $host !== $current_host) {
            continue;
        }

        if ($path === $current_path || !str_starts_with(trailingslashit($path), $parent_path)) {
            continue;
        }

        if (str_contains($href, 'museum-digital.de')) {
            continue;
        }

        if (iss_wf_import_is_media_asset_url($href)) {
            continue;
        }

        $has_image = $anchor->getElementsByTagName('img')->length > 0;
        $figure = iss_wf_import_dom_closest_element($anchor, ['figure']);
        if (!$has_image && !($figure instanceof DOMElement)) {
            continue;
        }

        if (isset($seen[$href])) {
            continue;
        }

        $seen[$href] = true;
        $links[] = $href;
    }

    return $links;
}

function iss_wf_import_collect_wf_images(DOMNode $root): array
{
    $images = [];
    $seen = [];

    if (!$root instanceof DOMElement && !$root instanceof DOMDocument) {
        return [];
    }

    foreach ($root->getElementsByTagName('img') as $img) {
        if (!$img instanceof DOMElement) {
            continue;
        }

        $source_url = iss_wf_import_dom_image_source($img);
        if ($source_url === '' || isset($seen[$source_url])) {
            continue;
        }

        if (!iss_wf_import_should_localize_media_url($source_url)) {
            continue;
        }

        $anchor = iss_wf_import_dom_closest_element($img, ['a']);
        if ($anchor instanceof DOMElement) {
            $href = html_entity_decode(trim($anchor->getAttribute('href')));
            if ($href !== '' && !iss_wf_import_is_media_asset_url($href)) {
                continue;
            }
        }

        $seen[$source_url] = true;

        $alt = sanitize_text_field((string) $img->getAttribute('alt'));
        $title = sanitize_text_field((string) $img->getAttribute('title'));
        $caption = '';
        $description = '';

        $figure = iss_wf_import_dom_closest_element($img, ['figure']);
        if ($figure instanceof DOMElement) {
            foreach ($figure->getElementsByTagName('figcaption') as $figcaption) {
                $caption = trim((string) $figcaption->textContent);
                if ($caption !== '') {
                    break;
                }
            }
        }

        if ($caption === '') {
            $anchor = iss_wf_import_dom_closest_element($img, ['a']);
            if ($anchor instanceof DOMElement) {
                $caption = trim((string) $anchor->textContent);
            }
        }

        $row = iss_wf_import_dom_closest_element($img, ['tr']);
        if ($row instanceof DOMElement) {
            $description = trim((string) $row->textContent);
        }

        if ($description === '') {
            $description = $caption !== '' ? $caption : $alt;
        }

        $images[] = [
            'source_url' => $source_url,
            'title' => $title !== '' ? $title : ($caption !== '' ? $caption : $alt),
            'caption' => $caption,
            'description' => $description,
        ];
    }

    return $images;
}

function iss_wf_import_wf_collection_hash(array $payload): string
{
    return md5(wp_json_encode([
        'source_url' => (string) ($payload['source_url'] ?? ''),
        'title' => (string) ($payload['title'] ?? ''),
        'excerpt' => (string) ($payload['excerpt'] ?? ''),
        'content_html' => (string) ($payload['content_html'] ?? ''),
        'featured_image_url' => (string) ($payload['featured_image_url'] ?? ''),
        'child_links' => array_values((array) ($payload['child_links'] ?? [])),
        'md_object_ids' => array_values((array) ($payload['md_object_ids'] ?? [])),
        'md_collection_ids' => array_values((array) ($payload['md_collection_ids'] ?? [])),
        'md_items' => array_values(array_map(static function (array $item): array {
            return [
                'source_object_id' => (string) ($item['source_object_id'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'caption_override' => (string) ($item['caption_override'] ?? ''),
                'page_label' => (string) ($item['page_label'] ?? ''),
            ];
        }, (array) ($payload['md_items'] ?? []))),
        'image_urls' => array_values(array_map(static function (array $image): array {
            return [
                'source_url' => (string) ($image['source_url'] ?? ''),
                'title' => (string) ($image['title'] ?? ''),
                'caption' => (string) ($image['caption'] ?? ''),
            ];
        }, (array) ($payload['images'] ?? []))),
    ]));
}

function iss_wf_import_upsert_wf_gallery_object(array $image, array $options = []): array
{
    $source_page_url = esc_url_raw((string) ($options['source_page_url'] ?? ''));
    $source_url = esc_url_raw((string) ($image['source_url'] ?? ''));

    if ($source_page_url === '' || $source_url === '') {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => 'Missing WF gallery image source.',
            'object_id' => 0,
        ];
    }

    $source_external_id = md5($source_page_url . '|' . $source_url);
    $existing_post_id = iss_wf_import_find_post_by_source_identity(
        ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'wf_gallery_object',
        $source_external_id
    );
    $force = !empty($options['force']);
    $title = trim((string) ($image['title'] ?? ''));
    if ($title === '') {
        $title = wp_basename((string) wp_parse_url($source_url, PHP_URL_PATH));
    }

    $description = trim((string) ($image['description'] ?? ''));
    $hash = md5(wp_json_encode([
        'source_page_url' => $source_page_url,
        'source_url' => $source_url,
        'title' => $title,
        'description' => $description,
        'caption' => (string) ($image['caption'] ?? ''),
    ]));

    if ($existing_post_id > 0 && !$force) {
        $existing_hash = (string) get_post_meta($existing_post_id, ISS_WF_IMPORT_HASH_META, true);
        if ($existing_hash === $hash) {
            update_post_meta($existing_post_id, ISS_WF_IMPORT_LAST_SYNCED_META, current_time('mysql', true));
            iss_wf_import_assign_source_term($existing_post_id, 'wf-museum', 'WF-Museum');

            return [
                'status' => 'skipped',
                'post_id' => $existing_post_id,
                'message' => 'Unchanged.',
                'object_id' => 0,
            ];
        }
    }

    $postarr = [
        'post_type' => ISS_WF_IMPORT_OBJECT_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => sanitize_title($title),
        'post_content' => iss_wf_import_md_text_to_html($description),
        'post_excerpt' => iss_wf_import_md_excerpt_from_text($description),
        'meta_input' => [
            ISS_WF_IMPORT_SOURCE_SITE_META => 'wf-museum',
            ISS_WF_IMPORT_SOURCE_KIND_META => 'wf_gallery_object',
            ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META => $source_external_id,
            ISS_WF_IMPORT_SOURCE_URL_META => $source_url,
            ISS_WF_IMPORT_SOURCE_SLUG_META => sanitize_title($title),
            ISS_WF_IMPORT_SOURCE_DATE_GMT_META => '',
            ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META => '',
            ISS_WF_IMPORT_SOURCE_AUTHOR_META => 'WF-Museum',
            ISS_WF_IMPORT_HASH_META => $hash,
            ISS_WF_IMPORT_LAST_SYNCED_META => current_time('mysql', true),
            ISS_WF_IMPORT_OBJECT_TYPE_META => 'Bild',
            ISS_WF_IMPORT_OBJECT_INVENTORY_META => '',
            ISS_WF_IMPORT_OBJECT_RIGHTS_HOLDER_META => '',
            ISS_WF_IMPORT_OBJECT_RIGHTS_STATUS_META => '',
            ISS_WF_IMPORT_OBJECT_CREATOR_META => '',
            ISS_WF_IMPORT_OBJECT_MATERIAL_META => '',
            ISS_WF_IMPORT_OBJECT_DIMENSIONS_META => '',
            ISS_WF_IMPORT_OBJECT_JSON_URL_META => '',
            ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META => [[
                'source_id' => 0,
                'source_url' => $source_url,
                'preview_url' => $source_url,
                'attachment_id' => 0,
                'preview_attachment_id' => 0,
                'filename' => sanitize_text_field((string) wp_basename((string) wp_parse_url($source_url, PHP_URL_PATH))),
                'label' => $title,
                'owner' => '',
                'creator' => '',
                'rights' => '',
                'type' => 'image',
                'is_main' => true,
            ]],
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
            'object_id' => 0,
        ];
    }

    $post_id = (int) $post_id;
    iss_wf_import_assign_source_term($post_id, 'wf-museum', 'WF-Museum');

    $attachment_id = iss_wf_import_get_or_create_attachment($source_url, $post_id, $title);
    $image_meta = [[
        'source_id' => 0,
        'source_url' => $source_url,
        'preview_url' => $source_url,
        'attachment_id' => $attachment_id,
        'preview_attachment_id' => $attachment_id,
        'filename' => sanitize_text_field((string) wp_basename((string) wp_parse_url($source_url, PHP_URL_PATH))),
        'label' => $title,
        'owner' => '',
        'creator' => '',
        'rights' => '',
        'type' => 'image',
        'is_main' => true,
    ]];
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, $image_meta);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_PRIMARY_ATTACHMENT_META, $attachment_id);
    update_post_meta($post_id, ISS_WF_IMPORT_OBJECT_PREVIEW_ATTACHMENT_META, $attachment_id);

    if ($attachment_id > 0 && wp_attachment_is_image($attachment_id)) {
        set_post_thumbnail($post_id, $attachment_id);
    } else {
        delete_post_thumbnail($post_id);
    }

    return [
        'status' => $status,
        'post_id' => $post_id,
        'message' => '',
        'object_id' => 0,
    ];
}

function iss_wf_import_upsert_wf_collection(string $url, array $options = []): array
{
    $source_url = esc_url_raw($url);
    if ($source_url === '') {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => 'Missing WF collection URL.',
            'collection_id' => 0,
            'object_stats' => [],
            'child_stats' => [],
        ];
    }

    $html = iss_wf_import_fetch_remote_html($source_url);
    if (is_wp_error($html)) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => $html->get_error_message(),
            'collection_id' => 0,
            'object_stats' => [],
            'child_stats' => [],
        ];
    }

    $dom = iss_wf_import_load_html_document($html);
    if (!$dom) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => 'WF collection HTML could not be parsed.',
            'collection_id' => 0,
            'object_stats' => [],
            'child_stats' => [],
        ];
    }

    $xpath = new DOMXPath($dom);
    $content_root = iss_wf_import_wf_content_root($xpath);
    if (!$content_root) {
        return [
            'status' => 'failed',
            'post_id' => 0,
            'message' => 'WF collection content root not found.',
            'collection_id' => 0,
            'object_stats' => [],
            'child_stats' => [],
        ];
    }

    $title = iss_wf_import_dom_first_meta_content($xpath, 'og:title');
    if ($title === '') {
        $title = iss_wf_import_dom_first_node_text($xpath, '//*[' . iss_wf_import_dom_xpath_contains_class('entry-title') . ']');
    }
    if ($title === '') {
        $title = 'Archivsammlung';
    }

    $excerpt = iss_wf_import_dom_first_meta_content($xpath, 'og:description');
    if ($excerpt === '') {
        foreach ($content_root->getElementsByTagName('p') as $paragraph) {
            if (!$paragraph instanceof DOMElement) {
                continue;
            }

            $excerpt = trim((string) $paragraph->textContent);
            if ($excerpt !== '') {
                break;
            }
        }
    }

    $featured_image_url = iss_wf_import_dom_first_meta_content($xpath, 'og:image');
    if ($featured_image_url === '') {
        foreach ($content_root->getElementsByTagName('img') as $img) {
            if (!$img instanceof DOMElement) {
                continue;
            }

            $featured_image_url = iss_wf_import_dom_image_source($img);
            if ($featured_image_url !== '') {
                break;
            }
        }
    }

    $content_html = iss_wf_import_dom_inner_html($content_root);
    $md_items = iss_wf_import_collect_wf_md_items($content_root);
    $md_object_ids = $md_items
        ? array_values(array_filter(array_map(static function (array $item): int {
            return absint($item['source_object_id'] ?? 0);
        }, $md_items)))
        : iss_wf_import_collect_md_object_ids($content_root);
    $md_collection_ids = iss_wf_import_collect_md_collection_ids($content_root);
    $images = iss_wf_import_collect_wf_foogallery_items($content_root);
    if (!$images) {
        $images = iss_wf_import_collect_wf_images($content_root);
    }
    $child_links = !empty($options['follow_children'])
        ? iss_wf_import_collect_wf_child_links($content_root, $source_url)
        : [];

    $payload = [
        'source_url' => $source_url,
        'title' => $title,
        'excerpt' => $excerpt,
        'content_html' => $content_html,
        'featured_image_url' => $featured_image_url,
        'md_object_ids' => $md_object_ids,
        'md_collection_ids' => $md_collection_ids,
        'md_items' => $md_items,
        'images' => $images,
        'child_links' => $child_links,
    ];

    $source_external_id = iss_wf_import_wf_collection_source_id($source_url);
    $existing_post_id = iss_wf_import_find_post_by_source_identity(
        ISS_WF_IMPORT_COLLECTION_POST_TYPE,
        'wf_collection_page',
        $source_external_id
    );
    $force = !empty($options['force']);
    $parent_id = absint($options['parent_id'] ?? 0);
    $media = in_array((string) ($options['media'] ?? 'all'), ['all', 'featured', 'none'], true)
        ? (string) $options['media']
        : 'all';
    $limit = max(0, absint($options['limit'] ?? 0));
    $hash = iss_wf_import_wf_collection_hash($payload);

    $post_id = $existing_post_id;
    $status = 'skipped';

    if ($existing_post_id > 0 && !$force) {
        $existing_hash = (string) get_post_meta($existing_post_id, ISS_WF_IMPORT_HASH_META, true);
        if ($existing_hash === $hash) {
            update_post_meta($existing_post_id, ISS_WF_IMPORT_LAST_SYNCED_META, current_time('mysql', true));
        } else {
            $post_id = 0;
        }
    }

    if ($post_id <= 0) {
        $postarr = [
            'post_type' => ISS_WF_IMPORT_COLLECTION_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_name' => sanitize_title($title),
            'post_content' => $content_html,
            'post_excerpt' => iss_wf_import_md_excerpt_from_text($excerpt),
            'post_parent' => $parent_id,
            'meta_input' => [
                ISS_WF_IMPORT_SOURCE_SITE_META => 'wf-museum',
                ISS_WF_IMPORT_SOURCE_KIND_META => 'wf_collection_page',
                ISS_WF_IMPORT_SOURCE_EXTERNAL_ID_META => $source_external_id,
                ISS_WF_IMPORT_SOURCE_URL_META => $source_url,
                ISS_WF_IMPORT_SOURCE_SLUG_META => sanitize_title($title),
                ISS_WF_IMPORT_SOURCE_DATE_GMT_META => '',
                ISS_WF_IMPORT_SOURCE_MODIFIED_GMT_META => '',
                ISS_WF_IMPORT_SOURCE_AUTHOR_META => 'WF-Museum',
                ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META => [[
                    'source_kind' => 'wf_collection_page',
                    'source_id' => $source_external_id,
                    'source_url' => $source_url,
                    'label' => $title,
                ]],
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
                'collection_id' => 0,
                'object_stats' => [],
                'child_stats' => [],
            ];
        }

        $post_id = (int) $post_id;
    } elseif ($parent_id > 0 && (int) get_post_field('post_parent', $post_id) !== $parent_id) {
        wp_update_post([
            'ID' => $post_id,
            'post_parent' => $parent_id,
        ]);
    }

    iss_wf_import_assign_source_term($post_id, 'wf-museum', 'WF-Museum');

    $has_structured_items = !empty($md_items) || !empty($md_collection_ids) || !empty($images);
    if ($media !== 'none') {
        if (!$has_structured_items) {
            $localized_content = iss_wf_import_localize_content_media($content_html, $post_id);
            if ($localized_content !== $content_html) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_content' => $localized_content,
                ]);
            }
        }

        if ($featured_image_url !== '') {
            $featured_attachment_id = iss_wf_import_get_or_create_attachment($featured_image_url, $post_id, $title);
            if ($featured_attachment_id > 0 && wp_attachment_is_image($featured_attachment_id)) {
                set_post_thumbnail($post_id, $featured_attachment_id);
            }
        }
    }

    $object_stats = [
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'failed' => 0,
        'processed' => 0,
        'object_ids' => [],
        'errors' => [],
    ];

    $collection_source_ids = [[
        'source_kind' => 'wf_collection_page',
        'source_id' => $source_external_id,
        'source_url' => $source_url,
        'label' => $title,
    ]];
    $collection_items = [];

    if ($md_items) {
        foreach ($md_items as $index => $item) {
            $object_id = absint($item['source_object_id'] ?? 0);
            if ($object_id <= 0) {
                continue;
            }

            $result = iss_wf_import_upsert_md_object($object_id, [
                'force' => $force,
                'media' => $media,
            ]);

            if (isset($object_stats[$result['status']])) {
                $object_stats[$result['status']]++;
            }
            $object_stats['processed']++;

            if (!empty($result['post_id'])) {
                $object_stats['object_ids'][] = (int) $result['post_id'];
            }

            if (!empty($result['message']) && $result['status'] === 'failed') {
                $object_stats['errors'][] = sprintf('Object %d: %s', $object_id, $result['message']);
            }

            $collection_items[] = [
                'object_id' => (int) ($result['post_id'] ?? 0),
                'position' => $index + 1,
                'caption_override' => sanitize_textarea_field((string) ($item['caption_override'] ?? '')),
                'page_label' => sanitize_text_field((string) ($item['page_label'] ?? '')),
                'title' => sanitize_text_field((string) ($item['title'] ?? '')),
                'source_object_id' => (string) $object_id,
                'source_url' => esc_url_raw((string) ($item['source_url'] ?? iss_wf_import_md_object_url($object_id))),
            ];

            if ($limit > 0 && $object_stats['processed'] >= $limit) {
                break;
            }
        }
    } elseif ($md_object_ids) {
        foreach ($md_object_ids as $index => $object_id) {
            $result = iss_wf_import_upsert_md_object($object_id, [
                'force' => $force,
                'media' => $media,
            ]);

            if (isset($object_stats[$result['status']])) {
                $object_stats[$result['status']]++;
            }
            $object_stats['processed']++;

            if (!empty($result['post_id'])) {
                $object_stats['object_ids'][] = (int) $result['post_id'];
            }

            if (!empty($result['message']) && $result['status'] === 'failed') {
                $object_stats['errors'][] = sprintf('Object %d: %s', $object_id, $result['message']);
            }

            $collection_items[] = [
                'object_id' => (int) ($result['post_id'] ?? 0),
                'position' => $index + 1,
                'caption_override' => '',
                'page_label' => '',
                'title' => '',
                'source_object_id' => (string) $object_id,
                'source_url' => iss_wf_import_md_object_url($object_id),
            ];

            if ($limit > 0 && $object_stats['processed'] >= $limit) {
                break;
            }
        }
    } elseif ($md_collection_ids) {
        $position = 1;

        foreach ($md_collection_ids as $collection_id) {
            $collection_source_ids[] = [
                'source_kind' => 'museum_digital_collection',
                'source_id' => (string) $collection_id,
                'source_url' => iss_wf_import_md_collection_url($collection_id),
                'label' => '',
            ];

            $objects_payload = iss_wf_import_fetch_md_collection_objects($collection_id);
            if (is_wp_error($objects_payload)) {
                $object_stats['errors'][] = sprintf('Collection %d: %s', $collection_id, $objects_payload->get_error_message());
                continue;
            }

            foreach ((array) ($objects_payload['objekte'] ?? []) as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $remote_object_id = absint($item['objekt_id'] ?? 0);
                if ($remote_object_id <= 0) {
                    continue;
                }

                $result = iss_wf_import_upsert_md_object($remote_object_id, [
                    'force' => $force,
                    'media' => $media,
                ]);

                if (isset($object_stats[$result['status']])) {
                    $object_stats[$result['status']]++;
                }
                $object_stats['processed']++;

                if (!empty($result['post_id'])) {
                    $object_stats['object_ids'][] = (int) $result['post_id'];
                }

                if (!empty($result['message']) && $result['status'] === 'failed') {
                    $object_stats['errors'][] = sprintf('Object %d: %s', $remote_object_id, $result['message']);
                }

                $collection_items[] = [
                    'object_id' => (int) ($result['post_id'] ?? 0),
                    'position' => $position,
                    'caption_override' => '',
                    'page_label' => '',
                    'title' => sanitize_text_field((string) ($item['objekt_name'] ?? '')),
                    'source_object_id' => (string) $remote_object_id,
                    'source_url' => iss_wf_import_md_object_url($remote_object_id),
                ];

                $position++;

                if ($limit > 0 && $object_stats['processed'] >= $limit) {
                    break 2;
                }
            }
        }
    } else {
        foreach ($images as $index => $image) {
            $result = iss_wf_import_upsert_wf_gallery_object($image, [
                'source_page_url' => $source_url,
                'force' => $force,
            ]);

            if (isset($object_stats[$result['status']])) {
                $object_stats[$result['status']]++;
            }
            $object_stats['processed']++;

            if (!empty($result['post_id'])) {
                $object_stats['object_ids'][] = (int) $result['post_id'];
            }

            if (!empty($result['message']) && $result['status'] === 'failed') {
                $object_stats['errors'][] = sprintf('WF image %d: %s', $index + 1, $result['message']);
            }

            $collection_items[] = [
                'object_id' => (int) ($result['post_id'] ?? 0),
                'position' => $index + 1,
                'caption_override' => sanitize_textarea_field((string) ($image['caption'] ?? '')),
                'page_label' => '',
                'title' => sanitize_text_field((string) ($image['title'] ?? '')),
                'source_object_id' => '',
                'source_url' => esc_url_raw((string) ($image['source_url'] ?? '')),
            ];

            if ($limit > 0 && $object_stats['processed'] >= $limit) {
                break;
            }
        }
    }

    $child_stats = [];
    $child_meta = [];

    if (!empty($options['follow_children']) && $child_links) {
        foreach ($child_links as $index => $child_url) {
            $child_result = iss_wf_import_upsert_wf_collection($child_url, [
                'force' => $force,
                'media' => $media,
                'parent_id' => $post_id,
                'follow_children' => false,
                'limit' => $limit,
            ]);

            $child_stats[] = $child_result;

            if (!empty($child_result['post_id'])) {
                $child_meta[] = [
                    'collection_id' => (int) $child_result['post_id'],
                    'position' => $index + 1,
                    'title' => sanitize_text_field((string) get_the_title((int) $child_result['post_id'])),
                    'slug' => sanitize_title((string) get_post_field('post_name', (int) $child_result['post_id'])),
                    'source_external_id' => iss_wf_import_wf_collection_source_id($child_url),
                    'source_url' => $child_url,
                ];
            }
        }
    }

    update_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META, $collection_source_ids);
    update_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_ITEMS_META, $collection_items);
    update_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_CHILDREN_META, $child_meta);

    iss_wf_import_sync_collection_sequence_relations(
        array_values(array_filter(array_map(static function (array $item): int {
            return absint($item['object_id'] ?? 0);
        }, $collection_items))),
        'collection:wf:' . md5($source_external_id),
        $title
    );

    return [
        'status' => $status,
        'post_id' => $post_id,
        'message' => '',
        'collection_id' => $post_id,
        'object_stats' => $object_stats,
        'child_stats' => $child_stats,
    ];
}
