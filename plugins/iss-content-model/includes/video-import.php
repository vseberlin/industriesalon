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

function iss_content_model_fetch_remote_html_browser($url, $cache_prefix = 'iss_video_remote_browser_') {
    $url = esc_url_raw((string) $url);
    if ($url === '') {
        return new WP_Error('iss_video_import_empty_url', __('Import-URL fehlt.', 'iss-content-model'));
    }

    $cache_key = sanitize_key($cache_prefix . md5($url));
    $cached = get_transient($cache_key);
    if (is_string($cached) && $cached !== '') {
        return $cached;
    }

    $response = wp_remote_get($url, [
        'timeout' => 20,
        'redirection' => 5,
        'user-agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/136.0.0.0 Safari/537.36',
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

    set_transient($cache_key, $body, DAY_IN_SECONDS);

    return $body;
}

function iss_content_model_is_youtube_url($url) {
    $url = trim((string) $url);
    if ($url === '') {
        return false;
    }

    return (bool) preg_match('~(?:youtube\.com|youtu\.be|youtube-nocookie\.com)~i', $url);
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

function iss_content_model_extract_youtube_video_id($url) {
    $watch_url = iss_content_model_convert_youtube_embed_to_watch_url($url);
    if (preg_match('~youtube\.com/watch\?v=([^&]+)~i', $watch_url, $matches)) {
        return rawurldecode((string) $matches[1]);
    }

    if (preg_match('~youtu\.be/([^?&"/]+)~i', $watch_url, $matches)) {
        return rawurldecode((string) $matches[1]);
    }

    return '';
}

function iss_content_model_normalize_remote_date($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^\d{8}$/', $value)) {
        $value = substr($value, 0, 4) . '-' . substr($value, 4, 2) . '-' . substr($value, 6, 2);
    }

    try {
        $date = new DateTimeImmutable($value, wp_timezone());
        return $date->format('Y-m-d');
    } catch (Throwable $e) {
        return '';
    }
}

function iss_content_model_format_video_duration_from_seconds($seconds) {
    $seconds = max(0, (int) $seconds);
    if ($seconds <= 0) {
        return '';
    }

    $hours = (int) floor($seconds / 3600);
    $minutes = (int) floor(($seconds % 3600) / 60);
    $remaining_seconds = $seconds % 60;

    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $remaining_seconds);
    }

    return sprintf('%02d:%02d', $minutes, $remaining_seconds);
}

function iss_content_model_get_language_label_from_code($code) {
    $code = strtolower(trim((string) $code));
    if ($code === '') {
        return '';
    }

    $code = preg_split('/[-_]/', $code)[0] ?? $code;
    $map = [
        'de' => __('Deutsch', 'iss-content-model'),
        'en' => __('Englisch', 'iss-content-model'),
        'fr' => __('Französisch', 'iss-content-model'),
        'es' => __('Spanisch', 'iss-content-model'),
        'it' => __('Italienisch', 'iss-content-model'),
        'pl' => __('Polnisch', 'iss-content-model'),
        'ru' => __('Russisch', 'iss-content-model'),
        'uk' => __('Ukrainisch', 'iss-content-model'),
        'tr' => __('Türkisch', 'iss-content-model'),
    ];

    return $map[$code] ?? strtoupper($code);
}

function iss_content_model_parse_youtube_caption_tracks_from_html($html) {
    $html = (string) $html;
    if ($html === '') {
        return [];
    }

    $patterns = [
        '/"captionTracks":(\[.*?\]),"audioTracks"/s',
        '/"captionTracks":(\[.*?\]),"translationLanguages"/s',
    ];

    foreach ($patterns as $pattern) {
        if (!preg_match($pattern, $html, $matches)) {
            continue;
        }

        $tracks = json_decode((string) $matches[1], true);
        if (is_array($tracks)) {
            return $tracks;
        }
    }

    return [];
}

function iss_content_model_get_youtube_caption_track_label($track) {
    if (!is_array($track)) {
        return '';
    }

    $label = trim((string) (($track['name']['simpleText'] ?? '') ?: ''));
    if ($label !== '') {
        return $label;
    }

    return iss_content_model_get_language_label_from_code((string) ($track['languageCode'] ?? ''));
}

function iss_content_model_fetch_youtube_watch_metadata($video_url) {
    $video_id = iss_content_model_extract_youtube_video_id($video_url);
    if ($video_id === '') {
        return [];
    }

    $cache_key = 'iss_video_watch_meta_' . md5($video_id);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $watch_url = 'https://www.youtube.com/watch?v=' . rawurlencode($video_id);
    $html = iss_content_model_fetch_remote_html_browser($watch_url, 'iss_video_watch_html_');
    if (is_wp_error($html)) {
        return [];
    }

    $metadata = [
        'video_id' => $video_id,
        'publish_date' => '',
        'duration_seconds' => 0,
        'duration' => '',
        'channel_name' => '',
        'language_code' => '',
        'language_label' => '',
        'captions_available' => false,
        'caption_label' => '',
    ];

    if (preg_match('/"approxDurationMs":"(\d+)"/', $html, $matches)) {
        $metadata['duration_seconds'] = (int) round(((int) $matches[1]) / 1000);
        $metadata['duration'] = iss_content_model_format_video_duration_from_seconds($metadata['duration_seconds']);
    }

    foreach (['/"publishDate":"([^"]+)"/', '/"uploadDate":"([^"]+)"/'] as $pattern) {
        if (!preg_match($pattern, $html, $matches)) {
            continue;
        }

        $normalized_date = iss_content_model_normalize_remote_date((string) $matches[1]);
        if ($normalized_date !== '') {
            $metadata['publish_date'] = $normalized_date;
            break;
        }
    }

    if (preg_match('/"ownerChannelName":"([^"]+)"/', $html, $matches)) {
        $metadata['channel_name'] = trim((string) $matches[1]);
    }

    if (preg_match('/"defaultAudioLanguage":"([^"]+)"/', $html, $matches)) {
        $metadata['language_code'] = trim((string) $matches[1]);
    }

    $caption_tracks = iss_content_model_parse_youtube_caption_tracks_from_html($html);
    if ($caption_tracks) {
        $metadata['captions_available'] = true;
        $metadata['caption_label'] = iss_content_model_get_youtube_caption_track_label($caption_tracks[0]);

        if ($metadata['language_code'] === '') {
            $metadata['language_code'] = trim((string) ($caption_tracks[0]['languageCode'] ?? ''));
        }
    }

    if ($metadata['language_code'] !== '') {
        $metadata['language_label'] = iss_content_model_get_language_label_from_code($metadata['language_code']);
    }

    if ($metadata['channel_name'] === '') {
        $oembed = iss_content_model_fetch_youtube_oembed_data($watch_url);
        if (!empty($oembed['author_name'])) {
            $metadata['channel_name'] = trim((string) $oembed['author_name']);
        }
    }

    set_transient($cache_key, $metadata, DAY_IN_SECONDS);

    return $metadata;
}

function iss_content_model_fetch_source_page_metadata($url) {
    $url = esc_url_raw((string) $url);
    if ($url === '' || iss_content_model_is_youtube_url($url)) {
        return [];
    }

    $cache_key = 'iss_video_source_meta_' . md5($url);
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return $cached;
    }

    $html = iss_content_model_fetch_remote_html_browser($url, 'iss_video_source_html_');
    if (is_wp_error($html)) {
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
    $metadata = [
        'published_date' => '',
        'site_name' => '',
        'author' => '',
        'title' => '',
    ];

    if ($doc->getElementsByTagName('title')->length > 0) {
        $metadata['title'] = trim((string) $doc->getElementsByTagName('title')->item(0)->textContent);
    }

    $meta_nodes = $xpath->query('//meta[@content]');
    if ($meta_nodes instanceof DOMNodeList) {
        foreach ($meta_nodes as $meta_node) {
            if (!($meta_node instanceof DOMElement)) {
                continue;
            }

            $property = trim((string) $meta_node->getAttribute('property'));
            $name = trim((string) $meta_node->getAttribute('name'));
            $content = trim((string) $meta_node->getAttribute('content'));
            if ($content === '') {
                continue;
            }

            if ($metadata['published_date'] === '' && in_array($property ?: $name, ['article:published_time', 'og:published_time', 'date', 'publish_date'], true)) {
                $metadata['published_date'] = iss_content_model_normalize_remote_date($content);
            }

            if ($metadata['site_name'] === '' && $property === 'og:site_name') {
                $metadata['site_name'] = $content;
            }

            if ($metadata['author'] === '' && in_array($property ?: $name, ['author', 'article:author'], true)) {
                $metadata['author'] = $content;
            }
        }
    }

    set_transient($cache_key, $metadata, DAY_IN_SECONDS);

    return $metadata;
}

function iss_content_model_backfill_video_post_metadata($post_id, $args = []) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_VIDEO_POST_TYPE) {
        return [
            'post_id' => $post_id,
            'updated' => [],
            'skipped' => true,
        ];
    }

    $overwrite = !empty($args['overwrite']);
    $video_url = (string) get_post_meta($post_id, 'iss_video_url', true);
    $source_url = (string) get_post_meta($post_id, 'iss_video_source_url', true);
    $source_label = (string) get_post_meta($post_id, 'iss_video_source_label', true);
    $year = (string) get_post_meta($post_id, 'iss_video_year', true);
    $original_date = (string) get_post_meta($post_id, 'iss_video_original_date', true);
    $duration = (string) get_post_meta($post_id, 'iss_video_duration', true);
    $language = (string) get_post_meta($post_id, 'iss_video_language', true);
    $transcript_status = (string) get_post_meta($post_id, 'iss_video_transcript_status', true);
    $transcript_source = (string) get_post_meta($post_id, 'iss_video_transcript_source', true);
    $has_transcript_status_meta = metadata_exists('post', $post_id, 'iss_video_transcript_status');
    $content = trim((string) get_post_field('post_content', $post_id));
    $excerpt = trim((string) get_post_field('post_excerpt', $post_id));

    $youtube_meta = iss_content_model_fetch_youtube_watch_metadata($video_url);
    $source_meta = iss_content_model_fetch_source_page_metadata($source_url);
    $updates = [];

    if (($duration === '' || $overwrite) && !empty($youtube_meta['duration'])) {
        $updates['iss_video_duration'] = (string) $youtube_meta['duration'];
    }

    $detected_date = (string) ($source_meta['published_date'] ?? '');
    if ($detected_date === '') {
        $detected_date = (string) ($youtube_meta['publish_date'] ?? '');
    }

    if (($original_date === '' || $overwrite) && $detected_date !== '') {
        $updates['iss_video_original_date'] = $detected_date;
    }

    if (($year === '' || $overwrite) && $detected_date !== '') {
        $updates['iss_video_year'] = substr($detected_date, 0, 4);
    }

    if (($language === '' || $overwrite) && !empty($youtube_meta['language_label'])) {
        $updates['iss_video_language'] = (string) $youtube_meta['language_label'];
    }

    if (($source_label === '' || $overwrite) && !empty($source_meta['site_name'])) {
        $updates['iss_video_source_label'] = (string) $source_meta['site_name'];
    } elseif (($source_label === '' || $overwrite) && !empty($youtube_meta['channel_name'])) {
        $updates['iss_video_source_label'] = (string) $youtube_meta['channel_name'];
    }

    if (!$has_transcript_status_meta || $overwrite) {
        if ($content !== '') {
            $updates['iss_video_transcript_status'] = 'excerpt';
        } elseif (!empty($youtube_meta['captions_available'])) {
            $updates['iss_video_transcript_status'] = 'planned';
        } else {
            $updates['iss_video_transcript_status'] = 'none';
        }
    }

    if (($transcript_source === '' || $overwrite) && $content === '' && !empty($youtube_meta['caption_label'])) {
        $updates['iss_video_transcript_source'] = sprintf(
            /* translators: %s: caption label such as "Deutsch (automatisch erzeugt)" */
            __('YouTube-Untertitel: %s', 'iss-content-model'),
            (string) $youtube_meta['caption_label']
        );
    }

    $applied = [];
    foreach ($updates as $meta_key => $value) {
        if (!$overwrite) {
            $has_meta = metadata_exists('post', $post_id, $meta_key);
            $existing_value = (string) get_post_meta($post_id, $meta_key, true);
            if ($has_meta && $existing_value !== '') {
                continue;
            }
        }

        if ($value === '') {
            continue;
        }

        update_post_meta($post_id, $meta_key, $value);
        $applied[$meta_key] = $value;
    }

    return [
        'post_id' => $post_id,
        'post_title' => get_the_title($post_id),
        'updated' => $applied,
        'youtube_meta' => $youtube_meta,
        'source_meta' => $source_meta,
        'placeholder_body' => ($content !== '' && $content === $excerpt),
    ];
}

function iss_content_model_backfill_all_video_metadata($args = []) {
    $limit = isset($args['limit']) ? max(0, (int) $args['limit']) : 0;
    $overwrite = !empty($args['overwrite']);

    $post_ids = get_posts([
        'post_type' => ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        'post_status' => ['publish', 'draft', 'pending', 'future', 'private'],
        'fields' => 'ids',
        'posts_per_page' => $limit > 0 ? $limit : -1,
        'orderby' => [
            'menu_order' => 'ASC',
            'date' => 'DESC',
        ],
        'suppress_filters' => true,
    ]);

    $results = [];
    foreach ($post_ids as $post_id) {
        $results[] = iss_content_model_backfill_video_post_metadata((int) $post_id, [
            'overwrite' => $overwrite,
        ]);
    }

    return $results;
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

    if ($excerpt !== '' && trim((string) get_post_meta($post_id, 'iss_video_transcript_status', true)) === '') {
        update_post_meta($post_id, 'iss_video_transcript_status', 'excerpt');
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

        /**
         * Backfill metadata for existing video posts from YouTube and source pages.
         *
         * ## OPTIONS
         *
         * [--limit=<limit>]
         * : Process only the first N video posts.
         *
         * [--overwrite]
         * : Overwrite existing metadata fields when fresh data is available.
         *
         * ## EXAMPLES
         *
         *     wp iss-content-model videos backfill
         *     wp iss-content-model videos backfill --limit=5 --overwrite
         */
        public function backfill($args, $assoc_args) {
            $results = iss_content_model_backfill_all_video_metadata([
                'limit' => isset($assoc_args['limit']) ? (int) $assoc_args['limit'] : 0,
                'overwrite' => isset($assoc_args['overwrite']),
            ]);

            $updated_posts = 0;
            $updated_fields = 0;

            foreach ($results as $result) {
                $updated = (array) ($result['updated'] ?? []);
                if (!$updated) {
                    continue;
                }

                $updated_posts++;
                $updated_fields += count($updated);
                WP_CLI::log(sprintf(
                    '#%d %s: %s',
                    (int) ($result['post_id'] ?? 0),
                    (string) ($result['post_title'] ?? ''),
                    implode(', ', array_keys($updated))
                ));
            }

            WP_CLI::success(sprintf(
                __('Backfilled %1$d fields across %2$d videos.', 'iss-content-model'),
                $updated_fields,
                $updated_posts
            ));
        }
    }

    WP_CLI::add_command('iss-content-model videos', 'ISS_Content_Model_Video_CLI_Command');
}
