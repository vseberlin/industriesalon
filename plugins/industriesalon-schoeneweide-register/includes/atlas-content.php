<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_source_snapshot_by_slug(string $slug, string $source_kind = 'detail_page'): array
{
    $slug = sanitize_title($slug);
    if ($slug === '') {
        return [];
    }

    $query_args = [
        'post_type' => ISS_REGISTER_SOURCE_POST_TYPE,
        'post_status' => ['private', 'publish'],
        'posts_per_page' => -1,
        'orderby' => 'modified',
        'order' => 'DESC',
        'suppress_filters' => true,
    ];

    if ($source_kind !== '') {
        $query_args['meta_query'] = [
            [
                'key' => iss_register_source_snapshot_meta_keys()['source_kind'],
                'value' => $source_kind,
            ],
        ];
    }

    $posts = get_posts($query_args);
    if (!$posts) {
        return [];
    }

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $snapshot = iss_register_get_source_snapshot((int) $post->ID);
        $snapshot_slug = sanitize_title((string) ($snapshot['extracted_payload']['slug'] ?? ''));
        if ($snapshot_slug === $slug) {
            return $snapshot;
        }
    }

    return [];
}

function iss_register_touchtable_story_cache_key(string $slug, string $modified): string
{
    return 'iss_reg_story_' . md5($slug . '|' . $modified . '|' . ISS_REGISTER_VERSION);
}

function iss_register_compact_story_text(string $text, int $limit = 320): string
{
    $text = iss_register_normalize_source_text($text);
    $text = preg_replace('/\)(?=\p{L})/u', ') ', $text);
    $text = preg_replace('/:(?=\p{L})/u', ': ', (string) $text);
    $text = preg_replace('/([.?!])(?=\p{L})/u', '$1 ', (string) $text);
    $text = preg_replace('/((18|19|20)\d{2}(?:\/\d{2})?)(?=Foto)/u', '$1 ', (string) $text);
    $text = preg_replace('/([[:upper:]]{2,})(?=Foto)/u', '$1 ', (string) $text);
    if ($text === '') {
        return '';
    }

    if (iss_register_source_text_length($text) <= $limit) {
        return $text;
    }

    if (function_exists('mb_substr')) {
        $slice = (string) mb_substr($text, 0, $limit);
    } else {
        $slice = substr($text, 0, $limit);
    }

    $slice = preg_replace('/\s+\S*$/u', '', $slice);

    return rtrim((string) $slice, " \t\n\r\0\x0B.,;:") . '…';
}

function iss_register_touchtable_match_year_label(string $text): string
{
    if (preg_match('/^(?:Ab\s+)?((18|19|20)\d{2})\b/u', trim($text), $matches)) {
        return (string) $matches[1];
    }

    return '';
}

function iss_register_extract_years_from_text(string $text): array
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

function iss_register_build_period_label(string $text): string
{
    $text = trim($text);
    if ($text === '') {
        return '';
    }

    if (preg_match('/(Ab\s+((18|19|20)\d{2})|((18|19|20)\d{2}(?:\/\d{2})?))/u', $text, $matches)) {
        return trim((string) $matches[1]);
    }

    $years = iss_register_extract_years_from_text($text);
    if (!$years) {
        return '';
    }

    return (string) $years[0];
}

function iss_register_touchtable_is_widget_class(DOMElement $element, string $needle): bool
{
    $class_name = ' ' . $element->getAttribute('class') . ' ';
    return strpos($class_name, ' ' . $needle . ' ') !== false;
}

function iss_register_pick_largest_srcset_url(string $srcset): string
{
    $srcset = trim($srcset);
    if ($srcset === '') {
        return '';
    }

    $best_url = '';
    $best_width = 0;
    $parts = array_filter(array_map('trim', explode(',', $srcset)));

    foreach ($parts as $part) {
        $segments = preg_split('/\s+/', $part);
        if (!$segments || !isset($segments[0])) {
            continue;
        }

        $candidate_url = trim((string) $segments[0]);
        $candidate_width = 0;

        if (isset($segments[1]) && preg_match('/(\d+)w$/', (string) $segments[1], $matches)) {
            $candidate_width = (int) $matches[1];
        }

        if ($candidate_width >= $best_width) {
            $best_width = $candidate_width;
            $best_url = $candidate_url;
        }
    }

    return $best_url;
}

function iss_register_normalize_touchtable_image_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    $parts = wp_parse_url($url);
    if (!is_array($parts) || empty($parts['path'])) {
        return $url;
    }

    $path = preg_replace('/-\d+x\d+(?=\.(avif|gif|jpe?g|png|webp)$)/i', '', (string) $parts['path']);
    if (!is_string($path) || $path === '') {
        $path = (string) $parts['path'];
    }

    $normalized = '';

    if (!empty($parts['scheme'])) {
        $normalized .= $parts['scheme'] . '://';
    }

    if (!empty($parts['host'])) {
        $normalized .= $parts['host'];
    }

    if (!empty($parts['port'])) {
        $normalized .= ':' . $parts['port'];
    }

    $normalized .= $path;

    return $normalized;
}

function iss_register_extract_touchtable_image_url(DOMElement $widget): string
{
    $document = $widget->ownerDocument;
    if (!$document instanceof DOMDocument) {
        return '';
    }

    $xpath = new DOMXPath($document);
    $image = $xpath->query('.//img', $widget)->item(0);
    if (!$image instanceof DOMElement) {
        return '';
    }

    $candidate = '';

    $srcset = (string) $image->getAttribute('srcset');
    if ($srcset !== '') {
        $candidate = iss_register_pick_largest_srcset_url($srcset);
    }

    if ($candidate === '') {
        $candidate = (string) $image->getAttribute('data-lazy-src');
    }

    if ($candidate === '') {
        $candidate = (string) $image->getAttribute('src');
    }

    return iss_register_normalize_touchtable_image_url($candidate);
}

function iss_register_should_skip_story_heading(string $text, string $title): bool
{
    $normalized = iss_register_normalize_source_text($text);
    if ($normalized === '') {
        return true;
    }

    $title_normalized = iss_register_normalize_source_text($title);
    if ($title_normalized !== '' && $normalized === $title_normalized) {
        return true;
    }

    return in_array($normalized, ['(tro)', 'tro'], true);
}

function iss_register_finalize_story_timeline_event(array $event): array
{
    $event['year'] = trim((string) ($event['year'] ?? ''));
    $event['year_label'] = trim((string) ($event['year_label'] ?? $event['year']));
    $event['title'] = trim((string) ($event['title'] ?? ''));
    $summary = (string) ($event['summary'] ?? '');
    $summary = preg_replace('/\b((18|19|20)\d{2})(?=\p{Lu})/u', '$1 ', $summary);
    $summary = preg_replace('/\bAb\s+((18|19|20)\d{2})(?=\p{Lu})/u', 'Ab $1 ', (string) $summary);
    $event['summary'] = iss_register_compact_story_text((string) $summary, 340);
    $event['image_source_url'] = iss_register_normalize_touchtable_image_url((string) ($event['image_source_url'] ?? ''));

    return $event;
}

function iss_register_extract_touchtable_timeline_events(DOMElement $widget, string $title = ''): array
{
    $document = $widget->ownerDocument;
    if (!$document instanceof DOMDocument) {
        return [];
    }

    $xpath = new DOMXPath($document);
    $entries = $xpath->query('.//*[self::span[contains(concat(" ", normalize-space(@class), " "), " wpr-year-wrap ")] or self::article[contains(concat(" ", normalize-space(@class), " "), " wpr-timeline-entry ")]]', $widget);
    if (!$entries instanceof DOMNodeList) {
        return [];
    }

    $events = [];
    $current_year_label = '';

    foreach ($entries as $entry) {
        if (!$entry instanceof DOMElement) {
            continue;
        }

        if ($entry->tagName === 'span' && strpos(' ' . $entry->getAttribute('class') . ' ', ' wpr-year-wrap ') !== false) {
            $year_text = iss_register_normalize_source_text($entry->textContent ?? '');
            if ($year_text !== '') {
                $current_year_label = $year_text;
            }
            continue;
        }

        if ($entry->tagName !== 'article' || strpos(' ' . $entry->getAttribute('class') . ' ', ' wpr-timeline-entry ') === false) {
            continue;
        }

        $title_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " wpr-title ")]', $entry)->item(0);
        $summary_node = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " wpr-description ")]', $entry)->item(0);

        $event_title = $title_node instanceof DOMNode
            ? iss_register_normalize_source_text($title_node->textContent ?? '')
            : '';
        $event_summary = $summary_node instanceof DOMNode
            ? iss_register_normalize_source_text($summary_node->textContent ?? '')
            : '';

        if ($event_title === '' && $summary_node instanceof DOMNode) {
            $event_title = '';
        }

        $entry_text = iss_register_normalize_source_text($entry->textContent ?? '');
        $year_label = $current_year_label !== '' ? $current_year_label : iss_register_build_period_label($entry_text);
        $years = $year_label !== '' ? iss_register_extract_years_from_text($year_label) : iss_register_extract_years_from_text($entry_text);

        $event = [
            'year' => $years ? (string) $years[0] : '',
            'year_label' => $year_label,
            'title' => iss_register_should_skip_story_heading($event_title, $title) ? '' : $event_title,
            'summary' => $event_summary,
            'image_source_url' => iss_register_extract_touchtable_image_url($entry),
        ];

        if ($event['summary'] === '' && $entry_text !== '') {
            $event['summary'] = $entry_text;
        }

        $event = iss_register_finalize_story_timeline_event($event);
        if (($event['year_label'] ?? '') === '' || (($event['summary'] ?? '') === '' && ($event['image_source_url'] ?? '') === '' && ($event['title'] ?? '') === '')) {
            continue;
        }

        $events[] = $event;
    }

    return $events;
}

function iss_register_extract_touchtable_story_payload(array $snapshot): array
{
    $raw_payload = $snapshot['raw_payload'] ?? [];
    if (!is_array($raw_payload)) {
        $raw_payload = maybe_unserialize($raw_payload);
    }

    $html = is_array($raw_payload) ? (string) (($raw_payload['content']['rendered'] ?? '') ?: '') : '';
    if ($html === '') {
        return [];
    }

    $title = trim((string) ($snapshot['extracted_payload']['title'] ?? $snapshot['title'] ?? ''));
    $intro_parts = [];
    $events = [];
    $current_event = null;
    $timeline_started = false;

    libxml_use_internal_errors(true);

    $document = new DOMDocument();
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $html);
    if (!$loaded) {
        libxml_clear_errors();
        return [];
    }

    $xpath = new DOMXPath($document);
    $widgets = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " elementor-widget ")]');

    if (!$widgets instanceof DOMNodeList) {
        libxml_clear_errors();
        return [];
    }

    foreach ($widgets as $widget) {
        if (!$widget instanceof DOMElement) {
            continue;
        }

        $text = iss_register_normalize_source_text($widget->textContent ?? '');

        if (iss_register_touchtable_is_widget_class($widget, 'elementor-widget-heading')) {
            if (!$timeline_started) {
                continue;
            }

            if (is_array($current_event) && $current_event['title'] === '' && !iss_register_should_skip_story_heading($text, $title)) {
                $current_event['title'] = $text;
                continue;
            }

            if (is_array($current_event) && $text !== '') {
                $current_event['summary'] = trim(($current_event['summary'] !== '' ? $current_event['summary'] . ' ' : '') . $text);
            }

            continue;
        }

        if (iss_register_touchtable_is_widget_class($widget, 'elementor-widget-wpr-posts-timeline')) {
            $timeline_events = iss_register_extract_touchtable_timeline_events($widget, $title);
            if ($timeline_events) {
                if (is_array($current_event) && (($current_event['summary'] ?? '') !== '' || ($current_event['image_source_url'] ?? '') !== '' || ($current_event['title'] ?? '') !== '')) {
                    $events[] = iss_register_finalize_story_timeline_event($current_event);
                }

                $timeline_started = true;
                $current_event = null;
                $events = array_merge($events, $timeline_events);
                continue;
            }

            $timeline_image_url = iss_register_extract_touchtable_image_url($widget);

            if (is_array($current_event) && (($current_event['summary'] ?? '') !== '' || ($current_event['image_source_url'] ?? '') !== '' || ($current_event['title'] ?? '') !== '')) {
                $events[] = iss_register_finalize_story_timeline_event($current_event);
            }

            $timeline_started = true;
            $current_event = [
                'year' => '',
                'year_label' => iss_register_build_period_label($text),
                'title' => '',
                'summary' => $text,
                'image_source_url' => $timeline_image_url,
            ];

            $years = iss_register_extract_years_from_text($text);
            if ($years) {
                $current_event['year'] = (string) $years[0];
                if ($current_event['year_label'] === '') {
                    $current_event['year_label'] = count($years) === 1
                        ? (string) $years[0]
                        : (string) $years[0] . '–' . (string) $years[count($years) - 1];
                }
            }

            continue;
        }

        if (iss_register_touchtable_is_widget_class($widget, 'elementor-widget-image')) {
            $image_url = iss_register_extract_touchtable_image_url($widget);

            if ($timeline_started && is_array($current_event) && $image_url !== '' && ($current_event['image_source_url'] ?? '') === '') {
                $current_event['image_source_url'] = $image_url;
            }
            continue;
        }

        if (!$timeline_started) {
            if ($text !== '' && iss_register_touchtable_is_widget_class($widget, 'elementor-widget-text-editor')) {
                $intro_parts[] = $text;
            }
            continue;
        }

        if (is_array($current_event) && $text !== '') {
            $current_event['summary'] = trim(($current_event['summary'] !== '' ? $current_event['summary'] . ' ' : '') . $text);
        }
    }

    if (is_array($current_event) && (($current_event['summary'] ?? '') !== '' || ($current_event['image_source_url'] ?? '') !== '' || ($current_event['title'] ?? '') !== '')) {
        $events[] = iss_register_finalize_story_timeline_event($current_event);
    }

    libxml_clear_errors();

    $events = array_values(array_filter($events, function (array $event): bool {
        return ($event['year_label'] ?? '') !== '' && (($event['summary'] ?? '') !== '' || ($event['image_source_url'] ?? '') !== '');
    }));

    return [
        'title' => $title,
        'intro' => iss_register_compact_story_text(implode(' ', array_values(array_unique(array_filter($intro_parts)))), 360),
        'events' => $events,
    ];
}

function iss_register_get_attachment_id_by_source_url(string $url): int
{
    $url = iss_register_normalize_touchtable_image_url($url);
    if ($url === '') {
        return 0;
    }

    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_key' => '_iss_register_source_url',
        'meta_value' => $url,
        'suppress_filters' => true,
    ]);

    if (!$attachments) {
        return 0;
    }

    return (int) $attachments[0];
}

function iss_register_import_attachment_from_source_url(string $url, string $title = ''): int
{
    $url = iss_register_normalize_touchtable_image_url($url);
    if ($url === '') {
        return 0;
    }

    $existing_attachment_id = iss_register_get_attachment_id_by_source_url($url);
    if ($existing_attachment_id > 0) {
        return $existing_attachment_id;
    }

    if (!function_exists('download_url')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }
    if (!function_exists('media_handle_sideload')) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
    }
    if (!function_exists('wp_generate_attachment_metadata')) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
    }

    $temporary_file = download_url($url, 45);
    if (is_wp_error($temporary_file)) {
        return 0;
    }

    $filename = wp_basename((string) wp_parse_url($url, PHP_URL_PATH));
    $file_array = [
        'name' => $filename !== '' ? $filename : 'atlas-story-image',
        'tmp_name' => $temporary_file,
    ];

    $attachment_id = media_handle_sideload($file_array, 0, $title !== '' ? $title : null);

    if (is_wp_error($attachment_id)) {
        @unlink($temporary_file);
        return 0;
    }

    update_post_meta((int) $attachment_id, '_iss_register_source_url', $url);

    return (int) $attachment_id;
}

function iss_register_enrich_atlas_story_payload_media(array $payload): array
{
    $events = is_array($payload['events'] ?? null) ? $payload['events'] : [];

    $payload['events'] = array_map(function (array $event): array {
        $source_url = (string) ($event['image_source_url'] ?? '');
        $attachment_id = $source_url !== '' ? iss_register_get_attachment_id_by_source_url($source_url) : 0;
        $image_url = $attachment_id > 0 ? (string) (wp_get_attachment_image_url($attachment_id, 'large') ?: '') : '';

        $event['attachment_id'] = $attachment_id;
        $event['image_url'] = $image_url;

        return $event;
    }, $events);

    return $payload;
}

function iss_register_get_atlas_story_payload(string $slug): array
{
    $snapshot = iss_register_get_source_snapshot_by_slug($slug, 'detail_page');
    if (!$snapshot) {
        return [];
    }

    $cache_key = iss_register_touchtable_story_cache_key($slug, (string) ($snapshot['source_modified'] ?? ''));
    $cached = get_transient($cache_key);

    if (is_array($cached)) {
        $enriched_cached = iss_register_enrich_atlas_story_payload_media($cached);
        if ($enriched_cached !== $cached) {
            set_transient($cache_key, $enriched_cached, 6 * HOUR_IN_SECONDS);
        }

        return $enriched_cached;
    }

    $payload = iss_register_extract_touchtable_story_payload($snapshot);
    if (!$payload) {
        return [];
    }

    $payload['slug'] = $slug;
    $payload['source_post_id'] = (int) ($snapshot['post_id'] ?? 0);
    $payload['modified'] = (string) ($snapshot['source_modified'] ?? '');
    $payload = iss_register_enrich_atlas_story_payload_media($payload);

    set_transient($cache_key, $payload, 6 * HOUR_IN_SECONDS);

    return $payload;
}

function iss_register_get_atlas_story_public_payload(string $slug): array
{
    $payload = iss_register_get_atlas_story_payload($slug);
    if (!$payload) {
        return [];
    }

    $public_events = array_map(function (array $event): array {
        return [
            'year' => (string) ($event['year'] ?? ''),
            'year_label' => (string) ($event['year_label'] ?? ''),
            'title' => (string) ($event['title'] ?? ''),
            'summary' => (string) ($event['summary'] ?? ''),
            'image_url' => (string) ($event['image_url'] ?? ''),
        ];
    }, (array) ($payload['events'] ?? []));

    return [
        'title' => (string) ($payload['title'] ?? ''),
        'intro' => (string) ($payload['intro'] ?? ''),
        'slug' => (string) ($payload['slug'] ?? $slug),
        'events' => $public_events,
    ];
}
