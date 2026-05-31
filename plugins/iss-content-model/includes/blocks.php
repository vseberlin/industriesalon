<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_register_blocks() {
    if (!function_exists('register_block_type')) {
        return;
    }

    $meta_dir = ISS_CONTENT_MODEL_PATH . 'blocks/content-meta';
    if (file_exists($meta_dir . '/block.json')) {
        register_block_type($meta_dir, [
            'render_callback' => 'iss_content_model_render_meta_block',
        ]);
    }

    $corpus_dir = ISS_CONTENT_MODEL_PATH . 'blocks/ausstellung-corpus';
    if (file_exists($corpus_dir . '/block.json')) {
        register_block_type($corpus_dir, [
            'render_callback' => 'iss_content_model_render_ausstellung_corpus_block',
        ]);
    }

    register_block_type('iss-content-model/ausstellung-surface', [
        'api_version' => 2,
        'render_callback' => 'iss_content_model_render_ausstellung_surface_block',
    ]);
}
add_action('init', 'iss_content_model_register_blocks');

function iss_content_model_get_ausstellung_corpus_chapters(int $post_id): array
{
    $ids = function_exists('iss_content_model_get_ausstellung_corpus_chapter_ids')
        ? iss_content_model_get_ausstellung_corpus_chapter_ids($post_id)
        : [];

    $posts = [];
    if ($ids) {
        $posts = get_posts([
            'post_type' => 'archivbeitrag',
            'post_status' => 'publish',
            'posts_per_page' => count($ids),
            'post__in' => $ids,
            'orderby' => 'post__in',
            'suppress_filters' => true,
        ]);
    } elseif (
        function_exists('iss_content_model_get_ausstellung_surface_mode')
        && function_exists('iss_content_model_get_ausstellung_archive_term_slug')
        && function_exists('iss_wf_import_get_archive_exhibition_posts')
        && iss_content_model_get_ausstellung_surface_mode($post_id) === 'archive_exhibition'
    ) {
        $term_slug = iss_content_model_get_ausstellung_archive_term_slug($post_id);
        if ($term_slug !== '') {
            $posts = iss_wf_import_get_archive_exhibition_posts($term_slug);
        }
    }

    return array_values(array_filter($posts, static function ($post) {
        return $post instanceof WP_Post;
    }));
}

function iss_content_model_get_ausstellung_surface_context(int $post_id): array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return [];
    }

    $mode = function_exists('iss_content_model_get_ausstellung_surface_mode')
        ? iss_content_model_get_ausstellung_surface_mode($post_id)
        : 'standard';
    $body_html = trim((string) apply_filters('the_content', (string) $post->post_content));
    $chapters = iss_content_model_get_ausstellung_corpus_chapters($post_id);
    $archive_term_slug = function_exists('iss_content_model_get_ausstellung_archive_term_slug')
        ? iss_content_model_get_ausstellung_archive_term_slug($post_id)
        : '';
    $archive_browser = function_exists('iss_content_model_get_ausstellung_archive_browser_config')
        ? iss_content_model_get_ausstellung_archive_browser_config($post_id)
        : [];

    return [
        'post_id' => $post_id,
        'post' => $post,
        'mode' => $mode,
        'body_html' => $body_html,
        'chapters' => $chapters,
        'archive_term_slug' => $archive_term_slug,
        'archive_browser' => [
            'defaultSource' => sanitize_title((string) ($archive_browser['default_source'] ?? '')),
            'lockSource' => !empty($archive_browser['lock_source']),
            'defaultField' => sanitize_title((string) ($archive_browser['default_field'] ?? '')),
            'lockField' => !empty($archive_browser['lock_field']),
            'quickKicker' => trim((string) ($archive_browser['quick_kicker'] ?? '')),
            'quickTitle' => trim((string) ($archive_browser['quick_title'] ?? '')),
            'quickFamilySlugs' => array_values(array_map('sanitize_title', (array) ($archive_browser['quick_family_slugs'] ?? []))),
            'showSourceCards' => !empty($archive_browser['show_source_cards']),
            'useCurrentUrl' => true,
        ],
    ];
}

function iss_content_model_split_exhibition_body(string $html, int $target_chars = 560, int $max_blocks = 2, int $max_single_block_chars = 520): array
{
    $html = trim($html);
    if ($html === '' || !class_exists('DOMDocument')) {
        return [
            'lead' => '',
            'rest' => $html,
        ];
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<!DOCTYPE html><html><body><div id="iss-ausstellung-corpus-body-root">' . $html . '</div></body></html>';
    libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    if (!$loaded) {
        return [
            'lead' => '',
            'rest' => $html,
        ];
    }

    $root = $document->getElementById('iss-ausstellung-corpus-body-root');
    if (!$root instanceof DOMElement) {
        return [
            'lead' => '',
            'rest' => $html,
        ];
    }

    $lead_parts = [];
    $rest_parts = [];
    $lead_chars = 0;
    $lead_blocks = 0;
    $collect_lead = true;

    foreach ($root->childNodes as $node) {
        $node_html = trim($document->saveHTML($node));
        if ($node_html === '') {
            continue;
        }

        if ($collect_lead && $node instanceof DOMElement) {
            $node_chars = mb_strlen(trim(wp_strip_all_tags($node->textContent)));

            if (!$lead_parts && $node_chars > $max_single_block_chars) {
                $collect_lead = false;
                $rest_parts[] = $node_html;
                continue;
            }

            if ($lead_parts && ($lead_blocks >= $max_blocks || ($lead_chars + $node_chars) > $target_chars)) {
                $collect_lead = false;
                $rest_parts[] = $node_html;
                continue;
            }

            $lead_parts[] = $node_html;
            $lead_blocks++;
            $lead_chars += $node_chars;

            if ($lead_blocks >= $max_blocks || $lead_chars >= $target_chars) {
                $collect_lead = false;
            }

            continue;
        }

        $rest_parts[] = $node_html;
    }

    if (!$lead_parts || !$rest_parts) {
        return [
            'lead' => '',
            'rest' => $html,
        ];
    }

    return [
        'lead' => implode('', $lead_parts),
        'rest' => implode('', $rest_parts),
    ];
}

function iss_content_model_clean_station_plain_text(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = str_replace("\xc2\xa0", ' ', $text);
    $text = preg_replace('/\x{00A0}/u', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim((string) $text);
}

function iss_content_model_trim_station_intro_text(string $html, int $word_limit = 42): string
{
    $text = iss_content_model_clean_station_plain_text(wp_strip_all_tags($html));
    if ($text === '') {
        return '';
    }

    return wp_trim_words($text, $word_limit, ' …');
}

function iss_content_model_trim_empty_station_paragraphs(string $html): string
{
    $html = preg_replace('/^(?:\s*<p\b[^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?>)*<\/p>)+/iu', '', $html);
    $html = preg_replace('/(?:<p\b[^>]*>(?:\s|&nbsp;|&#160;|<br\s*\/?>)*<\/p>\s*)+$/iu', '', $html);
    return trim((string) $html);
}

function iss_content_model_get_corpus_station_indexes(int $total, int $limit = 6): array
{
    if ($total <= 0) {
        return [];
    }

    $limit = max(1, min($limit, $total));
    if ($limit >= $total) {
        return range(0, $total - 1);
    }

    $indexes = [];
    for ($i = 0; $i < $limit; $i++) {
        $position = (int) round(($i * ($total - 1)) / max(1, ($limit - 1)));
        $indexes[] = max(0, min($total - 1, $position));
    }

    $indexes = array_values(array_unique($indexes));
    while (count($indexes) < $limit) {
        foreach (range(0, $total - 1) as $position) {
            if (!in_array($position, $indexes, true)) {
                $indexes[] = $position;
            }
            if (count($indexes) >= $limit) {
                break;
            }
        }
    }

    sort($indexes);
    return $indexes;
}

function iss_content_model_get_corpus_station_payload(WP_Post $chapter, int $index): array
{
    $content = apply_filters('the_content', (string) $chapter->post_content);
    $intro_html = '';
    $body_html = $content;
    $image_html = has_post_thumbnail($chapter) ? (string) get_the_post_thumbnail($chapter, 'large') : '';
    $image_caption = '';
    $thumbnail_id = (int) get_post_thumbnail_id($chapter);

    if ($thumbnail_id > 0) {
        $image_caption = trim((string) wp_get_attachment_caption($thumbnail_id));
    }

    if (preg_match_all('/<p\b[^>]*>.*?<\/p>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $match) {
            $candidate_html = trim((string) $match[0]);
            $candidate_text = iss_content_model_clean_station_plain_text(wp_strip_all_tags($candidate_html));
            if ($candidate_text === '') {
                continue;
            }

            $intro_html = $candidate_html;
            $start = (int) $match[1];
            $length = strlen((string) $match[0]);
            $body_html = trim(substr($content, 0, $start) . substr($content, $start + $length));
            break;
        }
    }

    if ($intro_html === '') {
        $intro_text = trim((string) get_the_excerpt($chapter));
        if ($intro_text !== '') {
            $intro_html = '<p>' . esc_html(wp_strip_all_tags($intro_text)) . '</p>';
        }
    }

    $intro_text = iss_content_model_clean_station_plain_text(wp_strip_all_tags($intro_html));
    if ($intro_text === '') {
        $intro_html = '';
    } else {
        $is_long_intro = mb_strlen($intro_text) > 420;
        $has_social_breaks = str_contains($intro_html, '<br') || preg_match_all('/<a\b/i', $intro_html) >= 3;
        if ($is_long_intro || $has_social_breaks) {
            $trimmed_intro = iss_content_model_trim_station_intro_text($intro_html);
            $intro_html = $trimmed_intro !== '' ? '<p>' . esc_html($trimmed_intro) . '</p>' : '';
        }
    }

    $body_parts = iss_content_model_split_exhibition_body($body_html);
    $lead_body_html = iss_content_model_trim_empty_station_paragraphs((string) $body_parts['lead']);

    if (
        $image_html !== ''
        && preg_match('/^\s*(<h[1-6][^>]*>.*?<\/h[1-6]>\s*)?<(figure|div)\b[^>]*(wp-block-image|wp-block-gallery)/is', $lead_body_html)
    ) {
        $lead_body_html = '';
    }

    return [
        'chapter' => $chapter,
        'index' => $index,
        'intro_html' => $intro_html,
        'lead_body_html' => $lead_body_html,
        'image_html' => $image_html,
        'image_caption' => $image_caption,
    ];
}

function iss_content_model_render_ausstellung_corpus_stations(array $chapters): string
{
    if (!$chapters) {
        return '';
    }

    $station_indexes = iss_content_model_get_corpus_station_indexes(count($chapters), 6);
    if (!$station_indexes) {
        return '';
    }

    $stations = [];
    foreach ($station_indexes as $index) {
        if (!isset($chapters[$index]) || !$chapters[$index] instanceof WP_Post) {
            continue;
        }
        $stations[] = iss_content_model_get_corpus_station_payload($chapters[$index], $index);
    }

    if (!$stations) {
        return '';
    }

    $is_sample = count($stations) < count($chapters);

    ob_start();
    echo '<div class="iss-ausstellung-corpus__guide">';
    echo '<div class="iss-heading iss-ausstellung-corpus__guide-head">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Stationen', 'iss-content-model') . '</p>';
    echo '<h3 class="iss-heading__title">' . esc_html__('Ausgewählte Stationen der Ausstellung', 'iss-content-model') . '</h3>';
    if ($is_sample) {
        echo '<p class="iss-heading__text">' . esc_html__('Diese Auswahl fasst einen größeren Korpus zu einem geführten Rundgang zusammen. Der vollständige Kapitelindex folgt darunter.', 'iss-content-model') . '</p>';
    } else {
        echo '<p class="iss-heading__text">' . esc_html__('Die Ausstellung entfaltet ihren Korpus in einzelnen Stationen. Darunter folgt der vollständige Kapitelindex.', 'iss-content-model') . '</p>';
    }
    echo '</div>';
    echo '<div class="iss-ausstellung-corpus__stations">';

    foreach ($stations as $station_position => $station) {
        $chapter = $station['chapter'];
        $entry_classes = 'iss-ausstellung-corpus__station';
        if ($station_position % 2 === 1) {
            $entry_classes .= ' iss-ausstellung-corpus__station--reverse';
        }

        echo '<article class="' . esc_attr($entry_classes) . '">';
        echo '<div class="iss-ausstellung-corpus__station-top">';
        echo '<div class="iss-ausstellung-corpus__station-copy">';
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Station', 'iss-content-model') . ' ' . esc_html(str_pad((string) ($station['index'] + 1), 2, '0', STR_PAD_LEFT)) . '</p>';
        echo '<h3 class="iss-ausstellung-corpus__station-title"><a href="' . esc_url((string) get_permalink($chapter)) . '">' . esc_html(get_the_title($chapter)) . '</a></h3>';
        if ($station['intro_html'] !== '') {
            echo '<div class="iss-ausstellung-corpus__station-intro">' . $station['intro_html'] . '</div>';
        }
        if ($station['lead_body_html'] !== '') {
            echo '<div class="iss-post-body__content iss-ausstellung-corpus__station-body-lead">' . $station['lead_body_html'] . '</div>';
        }
        echo '</div>';

        echo '<div class="iss-ausstellung-corpus__station-media">';
        if ($station['image_html'] !== '') {
            echo '<figure class="iss-ausstellung-corpus__station-figure">';
            echo '<a href="' . esc_url((string) get_permalink($chapter)) . '">' . $station['image_html'] . '</a>';
            if ($station['image_caption'] !== '') {
                echo '<figcaption>' . esc_html($station['image_caption']) . '</figcaption>';
            }
            echo '</figure>';
        }
        echo '</div>';
        echo '</div>';

        echo '<p class="iss-ausstellung-corpus__station-link"><a class="iss-action-link" href="' . esc_url((string) get_permalink($chapter)) . '">' . esc_html__('Kapitel öffnen', 'iss-content-model') . '</a></p>';
        echo '</article>';
    }

    echo '</div>';
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_content_model_render_ausstellung_corpus_toc(array $chapters): string
{
    if (!$chapters) {
        return '';
    }

    ob_start();
    echo '<ol class="iss-ausstellung-corpus__toc">';
    foreach ($chapters as $index => $chapter) {
        $number = str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
        $excerpt = get_the_excerpt($chapter);

        echo '<li class="iss-ausstellung-corpus__toc-item">';
        echo '<a class="iss-ausstellung-corpus__toc-link" href="' . esc_url(get_permalink($chapter)) . '">';
        echo '<span class="iss-ausstellung-corpus__toc-number">' . esc_html($number) . '</span>';
        echo '<span class="iss-ausstellung-corpus__toc-copy">';
        echo '<span class="iss-ausstellung-corpus__toc-title">' . esc_html(get_the_title($chapter)) . '</span>';
        if ($excerpt !== '') {
            echo '<span class="iss-ausstellung-corpus__toc-text">' . esc_html($excerpt) . '</span>';
        }
        echo '</span>';
        echo '</a>';
        echo '</li>';
    }
    echo '</ol>';

    return (string) ob_get_clean();
}

function iss_content_model_render_ausstellung_corpus_body(array $chapters): string
{
    if (!$chapters) {
        return '';
    }

    ob_start();
    if ($chapters) {
        echo iss_content_model_render_ausstellung_corpus_stations($chapters);
    }

    if ($chapters) {
        echo iss_content_model_render_ausstellung_corpus_toc($chapters);
    }

    return (string) ob_get_clean();
}

function iss_content_model_render_ausstellung_corpus_block($attributes = [], $content = '', $block = null) {
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return '';
    }

    $chapters = iss_content_model_get_ausstellung_corpus_chapters($post_id);
    if (!$chapters) {
        return '';
    }

    $shell_mode = sanitize_key((string) ($attributes['shellMode'] ?? 'section'));
    $body_html = iss_content_model_render_ausstellung_corpus_body($chapters);
    if ($body_html === '') {
        return '';
    }

    if ($shell_mode === 'body') {
        return $body_html;
    }

    $wrapper = (function_exists('get_block_wrapper_attributes') && $block instanceof WP_Block)
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-ausstellung-corpus'])
        : 'class="wp-block-iss-ausstellung-corpus"';

    ob_start();
    echo '<div ' . $wrapper . '>';
    echo '<section class="iss-ausstellung-corpus">';
    echo '<div class="iss-heading iss-ausstellung-corpus__head">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Ausstellungspfad', 'iss-content-model') . '</p>';
    echo '<h2 class="iss-heading__title">' . esc_html__('Die Ausstellung ordnet den Korpus als geführten Rundgang.', 'iss-content-model') . '</h2>';
    echo '<p class="iss-heading__text">' . esc_html__('Ausgewählte Stationen führen in das Thema ein. Darunter bleibt der vollständige Korpus als lesbarer Ausstellungspfad zugänglich.', 'iss-content-model') . '</p>';
    echo '</div>';

    echo $body_html;

    echo '</section>';
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_content_model_render_ausstellung_surface_fallback(array $context): string
{
    $mode = (string) ($context['mode'] ?? 'standard');
    $body_html = trim((string) ($context['body_html'] ?? ''));
    if ($mode === 'archive_exhibition') {
        $term_slug = sanitize_title((string) ($context['archive_term_slug'] ?? ''));
        if ($term_slug === '' || !function_exists('iss_wf_import_render_archive_exhibition_toc') || !function_exists('iss_wf_import_render_archive_exhibition_stream')) {
            return '';
        }

        $toc_html = trim((string) iss_wf_import_render_archive_exhibition_toc($term_slug));
        $stream_html = trim((string) iss_wf_import_render_archive_exhibition_stream($term_slug));
        if ($toc_html === '' && $stream_html === '') {
            return '';
        }

        ob_start();
        echo '<div class="iss-ausstellung-surface iss-ausstellung-surface--archive-exhibition">';
        if ($body_html !== '') {
            echo '<div class="iss-ausstellung-story__content">' . $body_html . '</div>';
        }
        if ($toc_html !== '') {
            echo '<div class="iss-digital-exhibition__toc">' . $toc_html . '</div>';
        }
        if ($stream_html !== '') {
            echo '<div class="iss-digital-exhibition__stream">' . $stream_html . '</div>';
        }
        echo '</div>';

        return (string) ob_get_clean();
    }

    if ($mode === 'archive_browser') {
        $browser_attributes = is_array($context['archive_browser'] ?? null) ? $context['archive_browser'] : [];
        if (!function_exists('iss_wf_import_render_archive_object_browser_block')) {
            return '';
        }

        ob_start();
        echo '<div class="iss-ausstellung-surface iss-ausstellung-surface--archive-browser">';
        if ($body_html !== '') {
            echo '<div class="iss-ausstellung-story__content">' . $body_html . '</div>';
        }
        echo iss_wf_import_render_archive_object_browser_block($browser_attributes, '', null);
        echo '</div>';

        return (string) ob_get_clean();
    }

    $chapters = is_array($context['chapters'] ?? null) ? $context['chapters'] : [];
    $corpus_html = iss_content_model_render_ausstellung_corpus_body($chapters);

    ob_start();
    echo '<div class="iss-ausstellung-surface iss-ausstellung-surface--standard">';
    if ($body_html !== '') {
        echo '<div class="iss-heading iss-ausstellung-story__heading">';
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Im Detail', 'iss-content-model') . '</p>';
        echo '<h2 class="iss-heading__title">' . esc_html__('Rundgang durch die Ausstellung', 'iss-content-model') . '</h2>';
        echo '</div>';
        echo '<div class="iss-ausstellung-story__content">' . $body_html . '</div>';
    }
    if ($corpus_html !== '') {
        echo '<div class="iss-ausstellung-corpus">';
        echo '<div class="iss-heading iss-ausstellung-corpus__head">';
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Ausstellungspfad', 'iss-content-model') . '</p>';
        echo '<h2 class="iss-heading__title">' . esc_html__('Die Ausstellung ordnet den Korpus als geführten Rundgang.', 'iss-content-model') . '</h2>';
        echo '<p class="iss-heading__text">' . esc_html__('Ausgewählte Stationen führen in das Thema ein. Darunter bleibt der vollständige Korpus als lesbarer Ausstellungspfad zugänglich.', 'iss-content-model') . '</p>';
        echo '</div>';
        echo $corpus_html;
        echo '</div>';
    }
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_content_model_render_ausstellung_surface_block($attributes = [], $content = '', $block = null): string
{
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return '';
    }

    $context = iss_content_model_get_ausstellung_surface_context($post_id);
    if ($context === []) {
        return '';
    }

    $rendered = apply_filters('iss_content_model_render_ausstellung_surface', '', $post_id, $context, $attributes);
    if (is_string($rendered) && trim($rendered) !== '') {
        return $rendered;
    }

    return iss_content_model_render_ausstellung_surface_fallback($context);
}

function iss_content_model_month_name_de(int $month): string
{
    $names = [
        1 => 'Januar',
        2 => 'Februar',
        3 => 'März',
        4 => 'April',
        5 => 'Mai',
        6 => 'Juni',
        7 => 'Juli',
        8 => 'August',
        9 => 'September',
        10 => 'Oktober',
        11 => 'November',
        12 => 'Dezember',
    ];

    return $names[$month] ?? '';
}

function iss_content_model_format_date_long_de(DateTimeImmutable $dt): string
{
    $timestamp = $dt->getTimestamp();
    $timezone = wp_timezone();
    $month_name = iss_content_model_month_name_de((int) wp_date('n', $timestamp, $timezone));

    if ($month_name === '') {
        return wp_date('d.m.Y', $timestamp, $timezone);
    }

    return wp_date('j.', $timestamp, $timezone) . ' ' . $month_name . ' ' . wp_date('Y', $timestamp, $timezone);
}

function iss_content_model_format_datetime($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value, wp_timezone());
        $time = $dt->format('H:i:s');
        if (in_array($time, ['00:00:00', '23:59:59'], true)) {
            return iss_content_model_format_date_long_de($dt);
        }

        return iss_content_model_format_date_long_de($dt) . ', ' . wp_date('H:i', $dt->getTimestamp(), wp_timezone()) . ' Uhr';
    } catch (Throwable $e) {
        return $value;
    }
}

function iss_content_model_format_date($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value, wp_timezone());
        return iss_content_model_format_date_long_de($dt);
    } catch (Throwable $e) {
        return $value;
    }
}

function iss_content_model_get_default_meta_panel_copy($post_type) {
    switch ((string) $post_type) {
        case ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE:
            return [
                'kicker' => __('Planen', 'iss-content-model'),
                'title' => __('Termin und Ort', 'iss-content-model'),
            ];
        case ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE:
            return [
                'kicker' => __('Besuch', 'iss-content-model'),
                'title' => __('Laufzeit', 'iss-content-model'),
            ];
        case ISS_CONTENT_MODEL_PROJEKT_POST_TYPE:
            return [
                'kicker' => __('Projekt', 'iss-content-model'),
                'title' => __('Projektinfo', 'iss-content-model'),
            ];
        case ISS_CONTENT_MODEL_TEAM_POST_TYPE:
            return [
                'kicker' => __('Kontakt', 'iss-content-model'),
                'title' => __('Ansprechperson', 'iss-content-model'),
            ];
        default:
            return [
                'kicker' => __('Info', 'iss-content-model'),
                'title' => __('Details', 'iss-content-model'),
            ];
    }
}

function iss_content_model_get_meta_rows_for_post($post_id, array $options = []) {
    $post_id = (int) $post_id;
    $post_type = (string) get_post_type($post_id);
    $rows = [];
    $show_related_places = !isset($options['show_places']) || !empty($options['show_places']);
    $topic_terms = taxonomy_exists(ISS_CONTENT_MODEL_TOPIC_TAXONOMY)
        ? iss_content_model_get_term_name_list($post_id, ISS_CONTENT_MODEL_TOPIC_TAXONOMY)
        : [];
    $related_places = function_exists('iss_relations_get_related_place_items')
        ? iss_relations_get_related_place_items($post_id)
        : [];
    $related_links = ($related_places && function_exists('iss_relations_render_related_place_links_html'))
        ? iss_relations_render_related_place_links_html($post_id)
        : '';

    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $start = iss_content_model_format_datetime(get_post_meta($post_id, 'iss_start_datetime', true));
        $end = iss_content_model_format_datetime(get_post_meta($post_id, 'iss_end_datetime', true));
        $location = trim((string) get_post_meta($post_id, 'iss_location', true));

        if ($start !== '') {
            $rows[] = ['label' => __('Beginn', 'iss-content-model'), 'value' => $start];
        }
        if ($end !== '') {
            $rows[] = ['label' => __('Ende', 'iss-content-model'), 'value' => $end];
        }
        if ($location !== '') {
            $single_related_place_title = count($related_places) === 1
                ? trim((string) ($related_places[0]['title'] ?? ''))
                : '';

            if ($single_related_place_title !== '' && $related_links !== '' && $location === $single_related_place_title) {
                $rows[] = ['label' => __('Ort', 'iss-content-model'), 'value' => $related_links, 'html' => true];
            } else {
                $rows[] = ['label' => __('Ort', 'iss-content-model'), 'value' => $location];
            }
        } elseif (count($related_places) === 1 && $related_links !== '') {
            $rows[] = ['label' => __('Ort', 'iss-content-model'), 'value' => $related_links, 'html' => true];
        }
        if (!empty($topic_terms)) {
            $rows[] = ['label' => __('Thema', 'iss-content-model'), 'value' => implode(', ', $topic_terms)];
        }
    } elseif ($post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        $start = iss_content_model_format_date(get_post_meta($post_id, 'iss_start_date', true));
        $end = iss_content_model_format_date(get_post_meta($post_id, 'iss_end_date', true));
        $type_terms = iss_content_model_get_term_name_list($post_id, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY);
        $collection_terms = iss_content_model_get_term_name_list($post_id, ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY);
        $site_terms = iss_content_model_get_term_name_list($post_id, ISS_CONTENT_MODEL_INDUSTRY_SITE_TAXONOMY);
        $is_permanent = !empty(get_post_meta($post_id, 'iss_is_permanent', true));

        if (!empty($type_terms)) {
            $rows[] = ['label' => __('Typ', 'iss-content-model'), 'value' => implode(', ', $type_terms)];
        } elseif ($is_permanent) {
            $rows[] = ['label' => __('Typ', 'iss-content-model'), 'value' => __('Dauerausstellung', 'iss-content-model')];
        }

        if ($start !== '' && $end !== '') {
            $rows[] = ['label' => __('Laufzeit', 'iss-content-model'), 'value' => $start . ' – ' . $end];
        } elseif ($start !== '') {
            $rows[] = ['label' => $is_permanent ? __('Seit', 'iss-content-model') : __('Beginn', 'iss-content-model'), 'value' => $start];
        } elseif ($end !== '') {
            $rows[] = ['label' => __('Bis', 'iss-content-model'), 'value' => $end];
        }

        if (!empty($collection_terms)) {
            $rows[] = ['label' => __('Sammlungsbereich', 'iss-content-model'), 'value' => implode(', ', $collection_terms)];
        }

        if (!empty($site_terms)) {
            $rows[] = ['label' => __('Industrieort', 'iss-content-model'), 'value' => implode(', ', $site_terms)];
        }

        if (!empty($topic_terms)) {
            $rows[] = ['label' => __('Thema', 'iss-content-model'), 'value' => implode(', ', $topic_terms)];
        }
    } elseif ($post_type === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        $period = trim((string) get_post_meta($post_id, 'iss_period_label', true));
        $status_terms = get_the_terms($post_id, ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY);

        if ($period !== '') {
            $rows[] = ['label' => __('Zeitraum', 'iss-content-model'), 'value' => $period];
        }
        if (is_array($status_terms) && !empty($status_terms)) {
            $rows[] = [
                'label' => __('Status', 'iss-content-model'),
                'value' => implode(', ', wp_list_pluck($status_terms, 'name')),
            ];
        }
        if (!empty($topic_terms)) {
            $rows[] = ['label' => __('Thema', 'iss-content-model'), 'value' => implode(', ', $topic_terms)];
        }
    } elseif ($post_type === ISS_CONTENT_MODEL_TEAM_POST_TYPE) {
        $role_label = trim((string) get_post_meta($post_id, 'iss_role_label', true));
        $role_terms = get_the_terms($post_id, ISS_CONTENT_MODEL_TEAM_ROLE_TAXONOMY);
        $email = sanitize_email((string) get_post_meta($post_id, 'iss_email', true));
        $phone = trim((string) get_post_meta($post_id, 'iss_phone', true));

        if ($role_label !== '') {
            $rows[] = ['label' => __('Rolle', 'iss-content-model'), 'value' => $role_label];
        } elseif (is_array($role_terms) && !empty($role_terms)) {
            $rows[] = [
                'label' => __('Rolle', 'iss-content-model'),
                'value' => implode(', ', wp_list_pluck($role_terms, 'name')),
            ];
        }

        if ($email !== '') {
            $rows[] = [
                'label' => __('E-Mail', 'iss-content-model'),
                'value' => '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>',
                'html' => true,
            ];
        }

        if ($phone !== '') {
            $tel = preg_replace('/[^0-9+]/', '', $phone);
            $rows[] = [
                'label' => __('Telefon', 'iss-content-model'),
                'value' => $tel !== '' ? '<a href="tel:' . esc_attr($tel) . '">' . esc_html($phone) . '</a>' : esc_html($phone),
                'html' => true,
            ];
        }
    }

    if ($show_related_places && $related_places && $related_links !== '') {
        $render_related_places_row = true;

        if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE && count($related_places) <= 1) {
            $render_related_places_row = false;
        }

        if ($render_related_places_row) {
            $rows[] = [
                'label' => count($related_places) > 1 ? __('Orte', 'iss-content-model') : __('Ort', 'iss-content-model'),
                'value' => $related_links,
                'html' => true,
            ];
        }
    }

    return $rows;
}

function iss_content_model_get_term_name_list($post_id, $taxonomy) {
    $terms = get_the_terms((int) $post_id, (string) $taxonomy);
    if (!is_array($terms) || empty($terms)) {
        return [];
    }

    $names = [];
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $name = trim((string) $term->name);
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return array_values(array_unique($names));
}

function iss_content_model_render_meta_block($attributes = [], $content = '', $block = null) {
    $post_id = get_the_ID();
    if ($post_id <= 0) {
        return '';
    }

    $post_type = (string) get_post_type($post_id);
    $rows = iss_content_model_get_meta_rows_for_post($post_id, [
        'show_places' => !isset($attributes['showPlaces']) || !empty($attributes['showPlaces']),
    ]);
    if (empty($rows)) {
        return '';
    }

    $defaults = iss_content_model_get_default_meta_panel_copy($post_type);
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? $defaults['kicker'])));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? $defaults['title'])));
    $intro = trim(sanitize_text_field((string) ($attributes['intro'] ?? '')));
    $variant = sanitize_key((string) ($attributes['variant'] ?? ''));
    $variant_class = in_array($variant, ['red', 'green', 'blue', 'yellow', 'brown'], true) ? ' iss-info-panel--' . $variant : '';

    $wrapper_attrs = (function_exists('get_block_wrapper_attributes') && ($block instanceof WP_Block))
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-content-meta'])
        : 'class="wp-block-iss-content-meta"';

    $out = '<div ' . $wrapper_attrs . '>';
    $out .= '<aside class="iss-info-panel iss-info-panel--skin-aside' . esc_attr($variant_class) . '">';

    if ($kicker !== '' || $title !== '' || $intro !== '') {
        $out .= '<div class="iss-heading iss-heading--uncaged iss-info-panel__heading">';
        if ($kicker !== '') {
            $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h3 class="iss-heading__title iss-info-panel__title">' . esc_html($title) . '</h3>';
        }
        if ($intro !== '') {
            $out .= '<p class="iss-heading__text iss-info-panel__intro">' . esc_html($intro) . '</p>';
        }
        $out .= '</div>';
    }

    $out .= '<div class="iss-info-panel__rows">';
    foreach ($rows as $row) {
        if (!is_array($row) || empty($row['value'])) {
            continue;
        }

        $label = isset($row['label']) ? (string) $row['label'] : '';
        $value = (string) $row['value'];
        $allow_html = !empty($row['html']);

        $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text">';
        if ($label !== '') {
            $out .= '<strong>' . esc_html($label) . '</strong><br>';
        }
        $out .= $allow_html ? wp_kses_post($value) : esc_html($value);
        $out .= '</p></div></div>';
    }
    $out .= '</div></aside></div>';

    return $out;
}
