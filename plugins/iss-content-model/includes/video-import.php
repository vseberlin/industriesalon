<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_fetch_remote_html($url) {
    $url = esc_url_raw((string) $url);
    if ($url === '') {
        return new WP_Error('iss_video_import_empty_url', __('Import-URL fehlt.', 'iss-content-model'));
    }

    $response = wp_remote_get($url, [
        'timeout' => 20,
        'redirection' => 5,
        'user-agent' => 'Industriesalon Video Importer',
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300 || trim($body) === '') {
        return new WP_Error('iss_video_import_fetch_failed', __('Die Videoseite konnte nicht geladen werden.', 'iss-content-model'), [
            'status' => $code,
        ]);
    }

    return $body;
}

function iss_content_model_convert_youtube_embed_to_watch_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return '';
    }

    if (preg_match('~youtube\.com/embed/([^?&"/]+)~i', $url, $matches)) {
        return 'https://www.youtube.com/watch?v=' . rawurlencode($matches[1]);
    }

    if (preg_match('~youtu\.be/([^?&"/]+)~i', $url, $matches)) {
        return 'https://www.youtube.com/watch?v=' . rawurlencode($matches[1]);
    }

    return esc_url_raw($url);
}

function iss_content_model_is_weak_video_title($title) {
    $title = trim((string) $title);
    if ($title === '') {
        return true;
    }

    if (preg_match('/^accordion panel$/i', $title)) {
        return true;
    }

    if (preg_match('/^[a-z0-9_-]{3,32}$/i', $title)) {
        return true;
    }

    if (!preg_match('/\s/u', $title) && preg_match('/^\p{Ll}[\p{Ll}\p{M}0-9_-]{2,32}$/u', $title)) {
        return true;
    }

    return false;
}

function iss_content_model_fetch_youtube_oembed_data($video_url) {
    $video_url = esc_url_raw((string) $video_url);
    if ($video_url === '') {
        return [];
    }

    $cache_key = 'iss_video_oembed_' . md5($video_url);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $oembed_url = add_query_arg([
        'url' => $video_url,
        'format' => 'json',
    ], 'https://www.youtube.com/oembed');

    $response = wp_remote_get($oembed_url, [
        'timeout' => 15,
        'redirection' => 3,
        'user-agent' => 'Industriesalon Video Importer',
    ]);

    if (is_wp_error($response)) {
        return [];
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    $body = (string) wp_remote_retrieve_body($response);
    if ($code < 200 || $code >= 300 || trim($body) === '') {
        return [];
    }

    $data = json_decode($body, true);
    if (!is_array($data)) {
        return [];
    }

    set_transient($cache_key, $data, DAY_IN_SECONDS);

    return $data;
}

function iss_content_model_guess_video_title_from_excerpt($excerpt) {
    $excerpt = trim((string) $excerpt);
    if ($excerpt === '') {
        return '';
    }

    $excerpt = wp_strip_all_tags($excerpt);
    $parts = preg_split('/\s*(?:,|-|–)\s*/u', $excerpt);
    if (!is_array($parts) || empty($parts)) {
        return '';
    }

    $candidate = trim((string) $parts[0]);
    if ($candidate === '' || mb_strlen($candidate) < 3) {
        return '';
    }

    return sanitize_text_field($candidate);
}

function iss_content_model_guess_video_terms($title, $excerpt) {
    $haystack = mb_strtolower(trim((string) $title . ' ' . $excerpt));
    $terms = [];

    $rules = [
        'Community' => ['repair café', 'repair cafe', 'community'],
        'Führungen' => ['führung', 'führerstand', 'rundgang', 'spaziergang'],
        'Veranstaltungen' => ['veranstaltung', 'vortrag', 'gespräch', 'lesung', 'konzert'],
        'Zeitzeugen' => ['zeitzeuge', 'erinnert sich', 'interview', 'bürokraft', 'ingenieur', 'leiter', 'küchenchefin', 'punkerin'],
        'Werk & Technik' => ['werk', 'kabelwerk', 'transformatorenwerk', 'montagehalle', 'fabrik', 'lok', 'technik', 'produktion', 'bildröhrenwerk', 'kwo', 'wf', 'tro'],
    ];

    foreach ($rules as $term_name => $needles) {
        foreach ($needles as $needle) {
            if (mb_strpos($haystack, $needle) !== false) {
                $terms[] = $term_name;
                break;
            }
        }
    }

    return array_values(array_unique($terms));
}

function iss_content_model_find_video_import_container(DOMNode $node) {
    $current = $node;
    while ($current instanceof DOMNode) {
        if ($current instanceof DOMElement) {
            $class_name = (string) $current->getAttribute('class');
            if ($class_name !== '' && preg_match('/\bcol-inner\b/', $class_name)) {
                return $current;
            }
        }
        $current = $current->parentNode;
    }

    return null;
}

function iss_content_model_parse_video_entries_from_html($html) {
    $html = (string) $html;
    if (trim($html) === '') {
        return [];
    }

    $previous = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $loaded = $doc->loadHTML($html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        return [];
    }

    $xpath = new DOMXPath($doc);
    $iframes = $xpath->query('//iframe[contains(@data-src-cmplz, "youtube.com") or contains(@src, "youtube.com") or contains(@src, "youtu.be")]');
    if (!$iframes instanceof DOMNodeList || $iframes->length === 0) {
        return [];
    }

    $entries = [];
    foreach ($iframes as $iframe) {
        if (!($iframe instanceof DOMElement)) {
            continue;
        }

        $embed_url = (string) $iframe->getAttribute('data-src-cmplz');
        if ($embed_url === '') {
            $embed_url = (string) $iframe->getAttribute('src');
        }
        $video_url = iss_content_model_convert_youtube_embed_to_watch_url($embed_url);
        if ($video_url === '') {
            continue;
        }

        $container = iss_content_model_find_video_import_container($iframe);
        $title = trim((string) $iframe->getAttribute('title'));
        $description = '';
        $thumbnail = trim((string) $iframe->getAttribute('data-placeholder-image'));

        if ($container instanceof DOMElement) {
            $title_nodes = $xpath->query('.//*[contains(@class, "accordion-title")]//span', $container);
            if ($title_nodes instanceof DOMNodeList && $title_nodes->length > 0) {
                $candidate = trim((string) $title_nodes->item(0)->textContent);
                if ($candidate !== '') {
                    $title = $candidate;
                }
            }

            $desc_nodes = $xpath->query('.//*[contains(@class, "accordion-inner")]//p | .//*[contains(@class, "text")]//p', $container);
            if ($desc_nodes instanceof DOMNodeList && $desc_nodes->length > 0) {
                foreach ($desc_nodes as $desc_node) {
                    $candidate = trim(wp_strip_all_tags((string) $desc_node->textContent));
                    if ($candidate !== '') {
                        $description = $candidate;
                        break;
                    }
                }
            }
        }

        if ($title === '') {
            $title = $video_url;
        }

        if (iss_content_model_is_weak_video_title($title) || $thumbnail === '') {
            $oembed = iss_content_model_fetch_youtube_oembed_data($video_url);
            if (iss_content_model_is_weak_video_title($title) && !empty($oembed['title'])) {
                $title = trim((string) $oembed['title']);
                $title = preg_replace('/\s+-\s+Industriesalon$/u', '', $title);
            }
            if ($thumbnail === '' && !empty($oembed['thumbnail_url'])) {
                $thumbnail = trim((string) $oembed['thumbnail_url']);
            }
        }

        $entries[] = [
            'video_url' => esc_url_raw($video_url),
            'title' => $title,
            'excerpt' => $description,
            'thumbnail' => esc_url_raw($thumbnail),
        ];
    }

    $unique = [];
    foreach ($entries as $entry) {
        $video_url = (string) ($entry['video_url'] ?? '');
        if ($video_url === '' || isset($unique[$video_url])) {
            continue;
        }
        $unique[$video_url] = $entry;
    }

    return array_values($unique);
}

function iss_content_model_find_video_post_by_url($video_url) {
    $video_url = esc_url_raw((string) $video_url);
    if ($video_url === '') {
        return 0;
    }

    $query = new WP_Query([
        'post_type' => ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => 'iss_video_url',
        'meta_value' => $video_url,
        'no_found_rows' => true,
    ]);

    if (!$query->have_posts()) {
        return 0;
    }

    return (int) $query->posts[0];
}

function iss_content_model_upsert_video_post($entry, $args = []) {
    $entry = is_array($entry) ? $entry : [];
    $args = is_array($args) ? $args : [];

    $video_url = esc_url_raw((string) ($entry['video_url'] ?? ''));
    if ($video_url === '') {
        return new WP_Error('iss_video_import_missing_video_url', __('Video-URL fehlt.', 'iss-content-model'));
    }

    $status = sanitize_key((string) ($args['status'] ?? 'draft'));
    if (!in_array($status, ['draft', 'publish', 'pending', 'private'], true)) {
        $status = 'draft';
    }

    $existing_id = iss_content_model_find_video_post_by_url($video_url);
    $title = trim(sanitize_text_field((string) ($entry['title'] ?? '')));
    $excerpt = trim(sanitize_textarea_field((string) ($entry['excerpt'] ?? '')));

    if (iss_content_model_is_weak_video_title($title)) {
        $guessed_title = iss_content_model_guess_video_title_from_excerpt($excerpt);
        if ($guessed_title !== '') {
            $title = $guessed_title;
        }
    }

    $postarr = [
        'post_type' => ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        'post_status' => $status,
        'post_title' => $title !== '' ? $title : $video_url,
        'post_excerpt' => $excerpt,
        'post_content' => $excerpt,
    ];

    if ($existing_id > 0) {
        $postarr['ID'] = $existing_id;
        $postarr['post_status'] = get_post_status($existing_id) ?: $status;
    }

    $post_id = wp_insert_post(wp_slash($postarr), true);
    if (is_wp_error($post_id)) {
        return $post_id;
    }

    $source_family = sanitize_key((string) ($args['source_family'] ?? ($entry['source_family'] ?? 'core')));
    if (!in_array($source_family, ['core', 'external_report', 'place_context'], true)) {
        $source_family = 'core';
    }

    $source_label = trim(sanitize_text_field((string) ($args['source_label'] ?? ($entry['source_label'] ?? 'Industriesalon Schöneweide'))));
    $source_page = trim((string) ($args['source_url'] ?? ($entry['source_url'] ?? $video_url)));

    update_post_meta($post_id, 'iss_video_url', $video_url);
    update_post_meta($post_id, 'iss_video_source_family', $source_family);
    update_post_meta($post_id, 'iss_video_source_label', $source_label !== '' ? $source_label : 'Industriesalon Schöneweide');
    update_post_meta($post_id, 'iss_video_source_url', $source_page !== '' ? esc_url_raw($source_page) : $video_url);

    $year_match = [];
    if (preg_match('/\b(19|20)\d{2}\b/', $excerpt, $year_match)) {
        update_post_meta($post_id, 'iss_video_year', $year_match[0]);
    }

    $category_terms = iss_content_model_guess_video_terms($title, $excerpt);
    if (!empty($category_terms)) {
        wp_set_object_terms($post_id, $category_terms, ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY, false);
    }

    return (int) $post_id;
}

function iss_content_model_import_videos_from_remote_page($url, $args = []) {
    $html = iss_content_model_fetch_remote_html($url);
    if (is_wp_error($html)) {
        return $html;
    }

    $entries = iss_content_model_parse_video_entries_from_html($html);
    if (empty($entries)) {
        return new WP_Error('iss_video_import_no_entries', __('Keine YouTube-Videos auf der angegebenen Seite gefunden.', 'iss-content-model'));
    }

    $limit = isset($args['limit']) ? max(0, (int) $args['limit']) : 0;
    if ($limit > 0) {
        $entries = array_slice($entries, 0, $limit);
    }

    $imported = [];
    foreach ($entries as $entry) {
        $post_id = iss_content_model_upsert_video_post($entry, $args);
        if (is_wp_error($post_id)) {
            continue;
        }
        $imported[] = [
            'post_id' => (int) $post_id,
            'title' => (string) ($entry['title'] ?? ''),
            'video_url' => (string) ($entry['video_url'] ?? ''),
        ];
    }

    return [
        'count' => count($imported),
        'items' => $imported,
    ];
}

if (defined('WP_CLI') && WP_CLI) {
    class ISS_Content_Model_Video_CLI_Command {
        /**
         * Import videos from a remote page with YouTube embeds.
         *
         * ## OPTIONS
         *
         * <url>
         * : Source page URL.
         *
         * [--status=<status>]
         * : Post status for new videos.
         * ---
         * default: draft
         * options:
         *   - draft
         *   - publish
         *   - pending
         *   - private
         * ---
         *
         * [--limit=<limit>]
         * : Import only the first N videos.
         *
         * ## EXAMPLES
         *
         *     wp iss-content-model videos import-page https://www.industriesalon.de/videos/ --status=draft
         */
        public function import_page($args, $assoc_args) {
            $url = isset($args[0]) ? (string) $args[0] : '';
            $result = iss_content_model_import_videos_from_remote_page($url, [
                'status' => isset($assoc_args['status']) ? (string) $assoc_args['status'] : 'draft',
                'limit' => isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0,
            ]);

            if (is_wp_error($result)) {
                WP_CLI::error($result->get_error_message());
            }

            foreach ($result['items'] as $item) {
                WP_CLI::log(sprintf('#%d %s', (int) $item['post_id'], (string) $item['title']));
            }

            WP_CLI::success(sprintf(__('Imported %d videos.', 'iss-content-model'), (int) $result['count']));
        }
    }

    WP_CLI::add_command('iss-content-model videos', 'ISS_Content_Model_Video_CLI_Command');
}
