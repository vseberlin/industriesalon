<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_register_video_library_block(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    $blocks = [
        'video-library' => 'iss_content_model_render_video_library_block',
        'video-library-filters' => 'iss_content_model_render_video_library_filters_block',
        'video-library-feature' => 'iss_content_model_render_video_library_feature_block',
        'video-library-playlists' => 'iss_content_model_render_video_library_playlists_block',
        'video-library-external' => 'iss_content_model_render_video_library_external_block',
        'video-library-inventory' => 'iss_content_model_render_video_library_inventory_block',
        'video-library-cta' => 'iss_content_model_render_video_library_cta_block',
        'video-player' => 'iss_content_model_render_video_player_block',
        'video-transcript' => 'iss_content_model_render_video_transcript_block',
    ];

    foreach ($blocks as $slug => $render_callback) {
        $block_dir = ISS_CONTENT_MODEL_PATH . 'blocks/' . $slug;
        if (!file_exists($block_dir . '/block.json')) {
            continue;
        }

        register_block_type($block_dir, [
            'render_callback' => $render_callback,
        ]);
    }
}
add_action('init', 'iss_content_model_register_video_library_block', 25);

function iss_content_model_enqueue_video_library_assets(): void
{
    $script_path = plugin_dir_path(__FILE__) . '../assets/video-library.js';
    $script_version = file_exists($script_path) ? (string) filemtime($script_path) : ISS_CONTENT_MODEL_VERSION;

    wp_enqueue_script(
        'iss-content-model-video-library',
        plugins_url('../assets/video-library.js', __FILE__),
        [],
        $script_version,
        true
    );
}

function iss_content_model_normalize_video_watch_url(string $url): string
{
    $url = trim($url);
    if ($url === '') {
        return '';
    }

    return function_exists('iss_content_model_convert_youtube_embed_to_watch_url')
        ? iss_content_model_convert_youtube_embed_to_watch_url($url)
        : esc_url_raw($url);
}

function iss_content_model_get_video_embed_url(string $url): string
{
    $watch_url = iss_content_model_normalize_video_watch_url($url);
    if ($watch_url === '') {
        return '';
    }

    if (preg_match('~youtube\.com/watch\?v=([^&]+)~i', $watch_url, $matches)) {
        return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($matches[1]) . '?enablejsapi=1&rel=0&playsinline=1';
    }

    return $watch_url;
}

function iss_content_model_get_video_transcript_html(int $post_id): string
{
    $content_html = apply_filters('the_content', (string) get_post_field('post_content', $post_id));
    return trim(wp_strip_all_tags($content_html)) === '' ? '' : $content_html;
}

function iss_content_model_get_video_chapter_rows(string $body_content, string $transcript_html): array
{
    if (trim(wp_strip_all_tags($body_content)) === '') {
        return [];
    }

    $chapter_source = $transcript_html !== '' ? $transcript_html : wpautop($body_content);
    $chapter_source = preg_replace('~<(?:/p|/h[2-6]|br\s*/?)>~i', "\n\n", $chapter_source);
    $chapter_text = html_entity_decode(wp_strip_all_tags((string) $chapter_source), ENT_QUOTES, get_bloginfo('charset'));
    $segments = preg_split('/\n\s*\n+/', (string) $chapter_text) ?: [];
    $rows = [];

    foreach ($segments as $index => $segment) {
        $segment = trim(preg_replace('/\s+/', ' ', (string) $segment));
        if ($segment === '') {
            continue;
        }

        $label = '';
        if (preg_match('/^\[?(\d{1,2}:\d{2}(?::\d{2})?)\]?(?:\s+|[-:]\s*)?(.*)$/u', $segment, $matches)) {
            $label = $matches[1];
            $segment = trim((string) ($matches[2] ?? ''));
        }

        if ($label === '') {
            continue;
        }

        $rows[] = [
            'label' => $label,
            'text' => wp_trim_words($segment, 22, ' ...'),
        ];

        if (count($rows) >= 5) {
            break;
        }
    }

    return $rows;
}

function iss_content_model_parse_video_timecode(string $value): int
{
    $value = trim($value);
    if (!preg_match('/^(?:(\d{1,2}):)?(\d{2}):(\d{2})$/', $value, $matches)) {
        return -1;
    }

    $hours = isset($matches[1]) && $matches[1] !== '' ? (int) $matches[1] : 0;
    $minutes = (int) $matches[2];
    $seconds = (int) $matches[3];

    return ($hours * 3600) + ($minutes * 60) + $seconds;
}

function iss_content_model_video_timecode_id(string $timecode): string
{
    return 't-' . str_replace(':', '-', trim($timecode));
}

function iss_content_model_link_video_timecodes(string $content_html): string
{
    return (string) preg_replace_callback('/<strong>\[([0-9]{2}:[0-9]{2}(?::[0-9]{2})?)\]<\/strong>/i', static function (array $matches): string {
        $timecode = (string) $matches[1];
        $seconds = iss_content_model_parse_video_timecode($timecode);
        if ($seconds < 0) {
            return (string) $matches[0];
        }

        return sprintf(
            '<a class="iss-video-timecode" href="#%1$s" data-video-seek="%2$d"><span class="screen-reader-text">%3$s </span>%4$s</a>',
            esc_attr(iss_content_model_video_timecode_id($timecode)),
            esc_attr((string) $seconds),
            esc_html__('Videozeit', 'iss-content-model'),
            esc_html($timecode)
        );
    }, $content_html);
}

function iss_content_model_add_video_timecode_ids(string $content_html): string
{
    return (string) preg_replace_callback('/<p([^>]*)>\s*<a class="iss-video-timecode" href="#([^"]+)"/i', static function (array $matches): string {
        $attrs = (string) $matches[1];
        if (preg_match('/\sid=/i', $attrs)) {
            return (string) $matches[0];
        }

        return '<p' . $attrs . ' id="' . esc_attr((string) $matches[2]) . '"><a class="iss-video-timecode" href="#' . esc_attr((string) $matches[2]) . '"';
    }, $content_html);
}

function iss_content_model_prepare_video_transcript_html(string $content_html): string
{
    return iss_content_model_add_video_timecode_ids(iss_content_model_link_video_timecodes($content_html));
}

function iss_content_model_get_video_transcript_nav_items(string $content_html): array
{
    $items = [];
    if (!preg_match_all('/<p[^>]*>\s*<a class="iss-video-timecode" href="#([^"]+)" data-video-seek="(\d+)">.*?<\/a>\s*(.*?)<\/p>/is', $content_html, $matches, PREG_SET_ORDER)) {
        return [];
    }

    foreach ($matches as $match) {
        $label = str_replace('-', ':', preg_replace('/^t-/', '', (string) $match[1]));
        $text = trim(preg_replace('/\s+/', ' ', wp_strip_all_tags((string) $match[3])));
        if ($label === '' || $text === '') {
            continue;
        }

        $items[] = [
            'id' => (string) $match[1],
            'seconds' => (int) $match[2],
            'label' => $label,
            'text' => wp_trim_words($text, 12, ' ...'),
        ];

        if (count($items) >= 12) {
            break;
        }
    }

    return $items;
}

function iss_content_model_render_video_transcript_panel_content(array $card): string
{
    $transcript_html = (string) ($card['transcript_html'] ?? '');
    if ($transcript_html !== '') {
        return '<div class="iss-video-library__panel-richtext">' . $transcript_html . '</div>';
    }

    return '<p class="iss-video-library__panel-empty">' . esc_html($card['transcript_status_label'] !== '' ? $card['transcript_status_label'] : __('Kein Transkript hinterlegt.', 'iss-content-model')) . '</p>';
}

function iss_content_model_render_video_chapters_panel_content(array $card): string
{
    $chapters = is_array($card['chapters'] ?? null) ? $card['chapters'] : [];
    if (!$chapters) {
        return '<p class="iss-video-library__panel-empty">' . esc_html__('Noch keine Kapitelmarken hinterlegt.', 'iss-content-model') . '</p>';
    }

    ob_start();
    ?>
    <div class="iss-video-library__chapter-list">
        <?php foreach ($chapters as $chapter) : ?>
            <article class="iss-video-library__chapter-row">
                <p class="iss-video-library__chapter-label"><?php echo esc_html((string) ($chapter['label'] ?? '')); ?></p>
                <p class="iss-video-library__chapter-text"><?php echo esc_html((string) ($chapter['text'] ?? '')); ?></p>
            </article>
        <?php endforeach; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_get_video_source_family_options(): array
{
    return [
        'core' => __('Eigener Bestand', 'iss-content-model'),
        'external_report' => __('Externer Bericht', 'iss-content-model'),
        'place_context' => __('Ort / Kontext', 'iss-content-model'),
    ];
}

function iss_content_model_get_video_source_family_label(string $family): string
{
    $options = iss_content_model_get_video_source_family_options();
    return $options[$family] ?? $options['core'];
}

function iss_content_model_get_video_group_order(): array
{
    return [
        'zeitzeugen',
        'orte-wandel',
        'fuehrungen',
        'gespraeche-debatten',
        'werk-technik',
    ];
}

function iss_content_model_normalize_video_source_family(string $family): string
{
    $family = sanitize_key($family);
    return array_key_exists($family, iss_content_model_get_video_source_family_options()) ? $family : 'core';
}

function iss_content_model_get_video_thumbnail_url(int $post_id, string $video_url): string
{
    $featured_image = get_the_post_thumbnail_url($post_id, 'large');
    if (is_string($featured_image) && $featured_image !== '') {
        return $featured_image;
    }

    $watch_url = iss_content_model_normalize_video_watch_url($video_url);
    if (preg_match('~youtube\.com/watch\?v=([^&]+)~i', $watch_url, $matches)) {
        return 'https://img.youtube.com/vi/' . rawurlencode($matches[1]) . '/hqdefault.jpg';
    }

    return '';
}

function iss_content_model_pick_display_video_category(array $categories): ?array
{
    if (!$categories) {
        return null;
    }

    $preferred_order = array_flip(iss_content_model_get_video_group_order());
    $fallback = $categories[0];
    $best_category = $fallback;
    $best_index = $preferred_order[(string) ($fallback['slug'] ?? '')] ?? 999;

    foreach ($categories as $category) {
        $index = $preferred_order[(string) ($category['slug'] ?? '')] ?? 999;
        if ($index < $best_index) {
            $best_category = $category;
            $best_index = $index;
        }
    }

    return $best_category;
}

function iss_content_model_get_video_cards(): array
{
    $posts = get_posts([
        'post_type' => ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => [
            'menu_order' => 'ASC',
            'date' => 'DESC',
        ],
        'suppress_filters' => true,
    ]);

    if (!$posts) {
        return [];
    }

    $cards = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $video_url = trim((string) get_post_meta($post->ID, 'iss_video_url', true));
        if ($video_url === '') {
            continue;
        }

        $source_url = trim((string) get_post_meta($post->ID, 'iss_video_source_url', true));
        $year_label = trim((string) get_post_meta($post->ID, 'iss_video_year', true));
        $original_date = trim((string) get_post_meta($post->ID, 'iss_video_original_date', true));
        $body_content = trim((string) get_post_field('post_content', $post->ID));
        $has_body_content = $body_content !== '';
        $transcript_html = iss_content_model_get_video_transcript_html((int) $post->ID);
        $transcript_status = iss_content_model_resolve_video_transcript_status(
            (string) get_post_meta($post->ID, 'iss_video_transcript_status', true),
            $has_body_content
        );
        $excerpt = has_excerpt($post) ? get_the_excerpt($post) : wp_trim_words(wp_strip_all_tags((string) $post->post_content), 28);
        $terms = get_the_terms($post, ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY);
        $categories = [];
        if (is_array($terms)) {
            foreach ($terms as $term) {
                if (!$term instanceof WP_Term) {
                    continue;
                }

                $categories[] = [
                    'id' => (int) $term->term_id,
                    'name' => $term->name,
                    'slug' => $term->slug,
                ];
            }
        }

        $display_category = iss_content_model_pick_display_video_category($categories);

        $cards[] = [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'excerpt' => $excerpt,
            'permalink' => get_permalink($post),
            'video_url' => esc_url($video_url),
            'source_url' => esc_url($source_url !== '' ? $source_url : $video_url),
            'source_family' => iss_content_model_normalize_video_source_family((string) get_post_meta($post->ID, 'iss_video_source_family', true)),
            'source_label' => trim((string) get_post_meta($post->ID, 'iss_video_source_label', true)),
            'year_label' => $year_label,
            'year' => iss_content_model_get_video_year_label($year_label, $original_date),
            'original_date' => $original_date,
            'original_date_label' => iss_content_model_format_video_original_date($original_date),
            'duration' => trim((string) get_post_meta($post->ID, 'iss_video_duration', true)),
            'language' => trim((string) get_post_meta($post->ID, 'iss_video_language', true)),
            'rights' => trim((string) get_post_meta($post->ID, 'iss_video_rights', true)),
            'featured' => !empty(get_post_meta($post->ID, 'iss_video_featured', true)),
            'has_transcript' => $has_body_content,
            'transcript_status' => $transcript_status,
            'transcript_status_label' => iss_content_model_get_video_transcript_status_label($transcript_status, $has_body_content),
            'transcript_source' => trim((string) get_post_meta($post->ID, 'iss_video_transcript_source', true)),
            'transcript_link_label' => iss_content_model_get_video_transcript_link_label($transcript_status, $has_body_content),
            'transcript_html' => $transcript_html,
            'chapters' => iss_content_model_get_video_chapter_rows($body_content, $transcript_html),
            'embed_url' => esc_url(iss_content_model_get_video_embed_url($video_url)),
            'thumbnail_url' => esc_url(iss_content_model_get_video_thumbnail_url((int) $post->ID, $video_url)),
            'categories' => $categories,
            'primary_category' => is_array($display_category) ? [
                'name' => (string) ($display_category['name'] ?? ''),
                'slug' => (string) ($display_category['slug'] ?? ''),
            ] : null,
        ];
    }

    return $cards;
}

function iss_content_model_sort_video_groups(array $groups): array
{
    $preferred_order = iss_content_model_get_video_group_order();

    uksort($groups, static function ($a, $b) use ($preferred_order) {
        $a_index = array_search($a, $preferred_order, true);
        $b_index = array_search($b, $preferred_order, true);
        $a_index = $a_index === false ? 999 : $a_index;
        $b_index = $b_index === false ? 999 : $b_index;

        if ($a_index === $b_index) {
            return strcmp($a, $b);
        }

        return $a_index <=> $b_index;
    });

    return $groups;
}

function iss_content_model_group_video_cards(array $cards): array
{
    $groups = [];

    foreach ($cards as $card) {
        $primary_category = $card['primary_category'] ?? null;
        if (!is_array($primary_category) || empty($primary_category['slug'])) {
            $groups['ohne-kategorie']['term'] = [
                'name' => __('Weiteres Material', 'iss-content-model'),
                'slug' => 'ohne-kategorie',
            ];
            $groups['ohne-kategorie']['cards'][] = $card;
            continue;
        }

        $slug = (string) $primary_category['slug'];
        if (!isset($groups[$slug])) {
            $groups[$slug] = [
                'term' => [
                    'name' => (string) ($primary_category['name'] ?? ''),
                    'slug' => $slug,
                ],
                'cards' => [],
            ];
        }

        $groups[$slug]['cards'][] = $card;
    }

    return iss_content_model_sort_video_groups($groups);
}

function iss_content_model_pick_featured_video(array $cards): ?array
{
    foreach ($cards as $card) {
        if (!empty($card['featured'])) {
            return $card;
        }
    }

    return $cards[0] ?? null;
}

function iss_content_model_get_playlist_copy(string $slug, string $term_name): array
{
    $map = [
        'zeitzeugen' => [
            'label' => __('3 Stimmen zum Wandel', 'iss-content-model'),
            'text' => __('Gespräche, in denen Werkalltag, Übergänge und Erfahrungswissen zuerst über Menschen und ihre Erinnerungen lesbar werden.', 'iss-content-model'),
            'relation' => __('Weiter in Archiv und Transkript', 'iss-content-model'),
        ],
        'werk-technik' => [
            'label' => __('Werkorte in Bewegung', 'iss-content-model'),
            'text' => __('Filme zu Produktion, Technik und industriellen Räumen. Hier wird die Sammlung als bewegtes Material sichtbar.', 'iss-content-model'),
            'relation' => __('Weiter in Sammlung und Werkgeschichte', 'iss-content-model'),
        ],
        'orte-wandel' => [
            'label' => __('Vom Film zum Ort', 'iss-content-model'),
            'text' => __('Videos, die Fabriken, Straeume und Umbrueche mit Karten, Ortsdossiers und Stadtraum verbinden.', 'iss-content-model'),
            'relation' => __('Weiter im Atlas', 'iss-content-model'),
        ],
        'fuehrungen' => [
            'label' => __('Vom Film zur Fuehrung', 'iss-content-model'),
            'text' => __('Bewegte Bilder, die sich in Rundgaengen, Stationen und oeffentlichen Formaten fortsetzen.', 'iss-content-model'),
            'relation' => __('Weiter zu Fuehrungen', 'iss-content-model'),
        ],
        'gespraeche-debatten' => [
            'label' => __('Oeffentliche Stimmen', 'iss-content-model'),
            'text' => __('Gespräche und Debatten, in denen Industriegeschichte in gegenwärtige Fragen und öffentliche Diskussionen übergeht.', 'iss-content-model'),
            'relation' => __('Weiter ins Programm', 'iss-content-model'),
        ],
    ];

    if (isset($map[$slug])) {
        return $map[$slug];
    }

    return [
        'label' => $term_name,
        'text' => __('Eine kuratierte Folge von Videos aus demselben thematischen Zusammenhang.', 'iss-content-model'),
        'relation' => __('Weiter in der Bibliothek', 'iss-content-model'),
    ];
}

function iss_content_model_render_video_card(array $card, array $options = []): string
{
    $show_excerpt = !array_key_exists('show_excerpt', $options) || (bool) $options['show_excerpt'];
    $extra_classes = array_values(array_filter(array_map('sanitize_html_class', (array) ($options['classes'] ?? []))));
    $category_names = implode(', ', array_filter(array_map(static function ($category) {
        return (string) ($category['name'] ?? '');
    }, $card['categories'])));
    $source_label = $card['source_label'] !== '' ? $card['source_label'] : __('Video', 'iss-content-model');

    $classes = array_merge(['iss-video-card'], $extra_classes);
    if (!$show_excerpt) {
        $classes[] = 'iss-video-card--strip';
    }

    ob_start();
    ?>
    <article class="<?php echo esc_attr(implode(' ', array_unique($classes))); ?>" data-video-id="<?php echo esc_attr((string) $card['id']); ?>">
        <button
            type="button"
            class="iss-video-card__trigger"
            data-video-id="<?php echo esc_attr((string) $card['id']); ?>"
            data-video-embed="<?php echo esc_url($card['embed_url']); ?>"
            data-video-title="<?php echo esc_attr($card['title']); ?>"
            data-video-text="<?php echo esc_attr($card['excerpt']); ?>"
            data-video-year="<?php echo esc_attr($card['year']); ?>"
            data-video-original-date="<?php echo esc_attr($card['original_date_label']); ?>"
            data-video-duration="<?php echo esc_attr($card['duration']); ?>"
            data-video-source-label="<?php echo esc_attr($source_label); ?>"
            data-video-language="<?php echo esc_attr($card['language']); ?>"
            data-video-categories="<?php echo esc_attr($category_names); ?>"
            data-video-thumbnail="<?php echo esc_url($card['thumbnail_url']); ?>"
            data-video-url="<?php echo esc_url($card['source_url']); ?>"
            data-video-permalink="<?php echo esc_url($card['permalink']); ?>"
            data-video-has-transcript="<?php echo $card['has_transcript'] ? '1' : '0'; ?>"
            data-video-transcript-link-label="<?php echo esc_attr($card['transcript_link_label']); ?>"
            data-video-transcript-status="<?php echo esc_attr($card['transcript_status_label']); ?>"
        >
            <span class="iss-video-card__media">
                <?php if ($card['thumbnail_url'] !== '') : ?>
                    <img src="<?php echo esc_url($card['thumbnail_url']); ?>" alt="<?php echo esc_attr($card['title']); ?>">
                <?php else : ?>
                    <span class="iss-video-card__fallback" aria-hidden="true"></span>
                <?php endif; ?>
                <?php if ($card['duration'] !== '') : ?>
                    <span class="iss-video-card__duration"><?php echo esc_html($card['duration']); ?></span>
                <?php endif; ?>
            </span>
            <span class="iss-video-card__body">
                <span class="iss-video-card__title"><span><?php echo esc_html($card['title']); ?></span></span>
                <?php if ($show_excerpt && $card['excerpt'] !== '') : ?>
                    <span class="iss-video-card__text"><?php echo esc_html($card['excerpt']); ?></span>
                <?php endif; ?>
                <span class="iss-video-card__meta">
                    <?php
                    $meta_bits = array_filter([
                        $card['year'],
                        $card['source_label'],
                    ]);
                    echo esc_html(implode(' · ', $meta_bits));
                    ?>
                </span>
            </span>
        </button>
        <p class="iss-video-card__actions">
            <a class="iss-video-card__permalink" href="<?php echo esc_url($card['permalink']); ?>"><?php esc_html_e('Zur Videoseite', 'iss-content-model'); ?></a>
        </p>
        <template class="iss-video-card__panel-template">
            <div data-video-panel-source="transcript"><?php echo iss_content_model_render_video_transcript_panel_content($card); ?></div>
            <div data-video-panel-source="chapters"><?php echo iss_content_model_render_video_chapters_panel_content($card); ?></div>
        </template>
    </article>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_strip(array $cards): string
{
    if (!$cards) {
        return '';
    }

    if (function_exists('iss_relations_enqueue_related_strip_script')) {
        iss_relations_enqueue_related_strip_script();
    }

    $controls_label = esc_attr__('Karussell-Steuerung', 'iss-content-model');
    $prev_label = esc_attr__('Vorherige Videos', 'iss-content-model');
    $next_label = esc_attr__('Nächste Videos', 'iss-content-model');
    $prev_text = esc_html__('Zurück', 'iss-content-model');
    $next_text = esc_html__('Weiter', 'iss-content-model');

    ob_start();
    ?>
    <div class="iss-video-library__carousel iss-related-feed__carousel" data-related-carousel>
        <div class="iss-video-library__rail iss-related-feed__grid iss-related-feed__grid--video iss-related-feed__grid--layout-strip iss-related-feed__grid--columns-6">
            <?php
            foreach ($cards as $card) {
                echo iss_content_model_render_video_card($card, ['show_excerpt' => false]);
            }
            ?>
        </div>
        <div class="iss-related-feed__carousel-controls" aria-label="<?php echo $controls_label; ?>">
            <button type="button" class="iss-related-feed__carousel-control iss-related-feed__carousel-control--prev" data-related-carousel-prev aria-label="<?php echo $prev_label; ?>" disabled>
                <span class="iss-related-feed__carousel-control-icon" aria-hidden="true">&#8592;</span>
                <span class="iss-related-feed__carousel-control-text"><?php echo $prev_text; ?></span>
            </button>
            <button type="button" class="iss-related-feed__carousel-control iss-related-feed__carousel-control--next" data-related-carousel-next aria-label="<?php echo $next_label; ?>" disabled>
                <span class="iss-related-feed__carousel-control-text"><?php echo $next_text; ?></span>
                <span class="iss-related-feed__carousel-control-icon" aria-hidden="true">&#8594;</span>
            </button>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_playlist_section(array $group, bool $with_head = true): string
{
    $slug = (string) ($group['term']['slug'] ?? '');
    $term_name = (string) ($group['term']['name'] ?? '');
    $cards = array_values($group['cards'] ?? []);
    if (!$cards) {
        return '';
    }

    $copy = iss_content_model_get_playlist_copy($slug, $term_name);
    $count = count($cards);

    ob_start();
    ?>
    <section id="<?php echo esc_attr($slug); ?>" class="iss-video-library__section iss-video-library__section--playlist">
        <?php if ($with_head) : ?>
            <div class="iss-video-library__section-head">
                <p class="iss-video-library__playlist-label"><?php echo esc_html((string) $copy['label']); ?></p>
                <h3 class="iss-video-library__section-title"><?php echo esc_html($term_name); ?></h3>
                <p class="iss-video-library__playlist-text"><?php echo esc_html((string) $copy['text']); ?></p>
                <p class="iss-video-library__series-count"><?php echo esc_html(sprintf(_n('%d Video', '%d Videos', $count, 'iss-content-model'), $count)); ?></p>
            </div>
        <?php endif; ?>
        <?php echo iss_content_model_render_video_strip($cards); ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_filter_video_cards_by_source_family(array $cards, string $family): array
{
    $family = iss_content_model_normalize_video_source_family($family);

    return array_values(array_filter($cards, static function (array $card) use ($family): bool {
        return (($card['source_family'] ?? 'core') === $family);
    }));
}

function iss_content_model_get_video_library_payload(): array
{
    $cards = iss_content_model_get_video_cards();
    if (!$cards) {
        return [];
    }

    $featured = iss_content_model_pick_featured_video($cards);
    if (!$featured) {
        return [];
    }

    $external_cards = iss_content_model_filter_video_cards_by_source_family($cards, 'external_report');
    $core_cards = array_values(array_filter($cards, static function (array $card): bool {
        return (($card['source_family'] ?? 'core') !== 'external_report');
    }));

    return [
        'cards' => $cards,
        'featured' => $featured,
        'external_cards' => $external_cards,
        'groups' => iss_content_model_group_video_cards($core_cards),
    ];
}

function iss_content_model_render_video_library_filters(array $groups, array $external_cards): string
{
    ob_start();
    ?>
    <nav class="iss-video-library__filters" aria-label="<?php echo esc_attr__('Thematische Einstiege', 'iss-content-model'); ?>">
        <?php foreach ($groups as $group) : ?>
            <a class="iss-video-library__filter" href="#<?php echo esc_attr((string) $group['term']['slug']); ?>">
                <?php echo esc_html((string) $group['term']['name']); ?>
            </a>
        <?php endforeach; ?>
        <?php if ($external_cards) : ?>
            <a class="iss-video-library__filter" href="#externe-berichte">
                <?php esc_html_e('Externe Berichte', 'iss-content-model'); ?>
            </a>
        <?php endif; ?>
    </nav>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_feature(array $featured): string
{
    $category_names = implode(', ', array_filter(array_map(static function ($category) {
        return (string) ($category['name'] ?? '');
    }, $featured['categories'] ?? [])));
    $source_label = $featured['source_label'] !== '' ? $featured['source_label'] : __('Video', 'iss-content-model');
    $play_label = sprintf(
        /* translators: %s video title */
        __('Video abspielen: %s', 'iss-content-model'),
        $featured['title']
    );

    ob_start();
    ?>
    <section
        class="iss-video-library__feature"
        data-video-player
        data-default-video-id="<?php echo esc_attr((string) $featured['id']); ?>"
        data-active-video-embed="<?php echo esc_url($featured['embed_url']); ?>"
        data-active-video-title="<?php echo esc_attr($featured['title']); ?>"
    >
        <div class="iss-video-library__feature-media">
            <button type="button" class="iss-video-library__feature-poster" data-video-player-poster data-video-player-play aria-label="<?php echo esc_attr($play_label); ?>">
                <?php if ($featured['thumbnail_url'] !== '') : ?>
                    <img data-video-player-poster-image src="<?php echo esc_url($featured['thumbnail_url']); ?>" alt="<?php echo esc_attr($featured['title']); ?>">
                <?php else : ?>
                    <span class="iss-video-library__feature-poster-fallback" data-video-player-poster-fallback aria-hidden="true"></span>
                    <img data-video-player-poster-image src="" alt="" hidden>
                <?php endif; ?>
                <span class="iss-video-library__feature-poster-shade" aria-hidden="true"></span>
                <span class="iss-video-library__feature-poster-copy">
                    <span class="iss-video-library__feature-poster-label"><?php esc_html_e('Film abspielen', 'iss-content-model'); ?></span>
                    <span class="iss-video-library__feature-poster-title" data-video-player-poster-title><?php echo esc_html($featured['title']); ?></span>
                </span>
                <span class="iss-video-library__feature-poster-button" aria-hidden="true">&#9654;</span>
            </button>
            <div class="iss-video-library__feature-frame-wrap" data-video-player-frame-wrap hidden>
                <iframe
                    data-video-player-frame
                    src=""
                    title="<?php echo esc_attr($featured['title']); ?>"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                    hidden
                ></iframe>
            </div>
        </div>

        <div class="iss-video-library__feature-copy">
            <nav class="iss-video-library__feature-tabs" aria-label="<?php echo esc_attr__('Videobereiche', 'iss-content-model'); ?>">
                <button type="button" class="iss-video-library__feature-tab is-active" data-video-player-tab="overview" aria-pressed="true"><?php esc_html_e('Übersicht', 'iss-content-model'); ?></button>
                <button type="button" class="iss-video-library__feature-tab" data-video-player-tab="transcript" aria-pressed="false"><?php esc_html_e('Transkript', 'iss-content-model'); ?></button>
                <button type="button" class="iss-video-library__feature-tab" data-video-player-tab="chapters" aria-pressed="false"><?php esc_html_e('Kapitel', 'iss-content-model'); ?></button>
            </nav>
            <div class="iss-video-library__feature-panels">
                <section class="iss-video-library__feature-panel iss-video-library__feature-panel--overview is-active" data-video-player-panel="overview">
                    <div class="iss-video-library__feature-main">
                        <div class="iss-video-library__feature-head">
                            <p class="iss-video-library__feature-kicker" data-video-player-kicker><?php echo esc_html($category_names); ?></p>
                            <h2 class="iss-video-library__feature-title" data-video-player-title><?php echo esc_html($featured['title']); ?></h2>
                            <p class="iss-video-library__feature-text" data-video-player-text><?php echo esc_html($featured['excerpt']); ?></p>
                        </div>
                    </div>
                    <aside class="iss-video-library__feature-side">
                        <dl class="iss-video-library__feature-facts">
                            <div class="iss-video-library__feature-fact" data-video-player-original-date-wrap <?php echo $featured['original_date_label'] === '' ? 'hidden' : ''; ?>>
                                <dt><?php esc_html_e('Datum', 'iss-content-model'); ?></dt>
                                <dd data-video-player-original-date><?php echo esc_html($featured['original_date_label']); ?></dd>
                            </div>
                            <div class="iss-video-library__feature-fact" data-video-player-year-wrap <?php echo $featured['year'] === '' ? 'hidden' : ''; ?>>
                                <dt><?php esc_html_e('Jahr', 'iss-content-model'); ?></dt>
                                <dd data-video-player-year><?php echo esc_html($featured['year']); ?></dd>
                            </div>
                            <div class="iss-video-library__feature-fact" data-video-player-duration-wrap <?php echo $featured['duration'] === '' ? 'hidden' : ''; ?>>
                                <dt><?php esc_html_e('Dauer', 'iss-content-model'); ?></dt>
                                <dd data-video-player-duration><?php echo esc_html($featured['duration']); ?></dd>
                            </div>
                            <div class="iss-video-library__feature-fact">
                                <dt><?php esc_html_e('Quelle', 'iss-content-model'); ?></dt>
                                <dd data-video-player-source><?php echo esc_html($source_label); ?></dd>
                            </div>
                            <div class="iss-video-library__feature-fact" data-video-player-language-wrap <?php echo $featured['language'] === '' ? 'hidden' : ''; ?>>
                                <dt><?php esc_html_e('Sprache', 'iss-content-model'); ?></dt>
                                <dd data-video-player-language><?php echo esc_html($featured['language']); ?></dd>
                            </div>
                        </dl>
                        <div class="iss-video-library__feature-links">
                            <p class="iss-video-library__feature-link"><a class="iss-action-link" data-video-player-permalink href="<?php echo esc_url($featured['permalink']); ?>"><?php esc_html_e('Zur Videoseite', 'iss-content-model'); ?></a></p>
                            <p class="iss-video-library__feature-link"><a class="iss-action-link" data-video-player-link href="<?php echo esc_url($featured['source_url']); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e('Zum Original', 'iss-content-model'); ?></a></p>
                            <p class="iss-video-library__feature-link" data-video-player-transcript-wrap <?php echo empty($featured['has_transcript']) ? 'hidden' : ''; ?>><a class="iss-action-link" data-video-player-open-tab="transcript" href="#videotranskript"><span data-video-player-transcript-text><?php echo esc_html($featured['transcript_link_label']); ?></span></a></p>
                            <p class="iss-video-library__feature-link" data-video-player-return-wrap hidden><button type="button" class="iss-video-library__return-link" data-video-player-return><?php esc_html_e('Zur Auswahl zurück', 'iss-content-model'); ?></button></p>
                        </div>
                    </aside>
                </section>
                <section class="iss-video-library__feature-panel" id="videotranskript" data-video-player-panel="transcript" hidden>
                    <div data-video-player-panel-target="transcript"><?php echo iss_content_model_render_video_transcript_panel_content($featured); ?></div>
                </section>
                <section class="iss-video-library__feature-panel" data-video-player-panel="chapters" hidden>
                    <div data-video-player-panel-target="chapters"><?php echo iss_content_model_render_video_chapters_panel_content($featured); ?></div>
                </section>
            </div>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_cta(): string
{
    ob_start();
    ?>
    <section class="iss-video-library__cta">
        <div class="iss-video-library__cta-copy">
            <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Weiter lesen', 'iss-content-model'); ?></p>
            <h3 class="iss-video-library__cta-title"><?php esc_html_e('Diese Videos sind Teil derselben Sammlung wie Archiv, Atlas, Führungen und Ausstellungen.', 'iss-content-model'); ?></h3>
            <p class="iss-video-library__cta-text"><?php esc_html_e('Nicht als Nebenmaterial, sondern als eigene Lesart des Ortsgedächtnisses.', 'iss-content-model'); ?></p>
        </div>
        <p><a class="iss-action-link" href="/sammlungen/"><?php esc_html_e('Zur Sammlungsseite', 'iss-content-model'); ?></a></p>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_inventory_section(array $cards): string
{
    $count = count($cards);
    ob_start();
    ?>
    <section class="iss-video-library__section iss-video-library__section--inventory">
        <div class="iss-video-library__section-head">
            <p class="iss-video-library__playlist-label"><?php esc_html_e('Vollstaendiger Bestand', 'iss-content-model'); ?></p>
            <h3 class="iss-video-library__section-title"><?php esc_html_e('Gesamte Videobibliothek', 'iss-content-model'); ?></h3>
            <p class="iss-video-library__playlist-text"><?php esc_html_e('Alle veroeffentlichten Videos bleiben als vollstaendige Rechercheoberflaeche verfuegbar.', 'iss-content-model'); ?></p>
            <p class="iss-video-library__series-count"><?php echo esc_html(sprintf(_n('%d Video', '%d Videos', $count, 'iss-content-model'), $count)); ?></p>
        </div>
        <?php echo iss_content_model_render_video_strip($cards); ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_external_reports_section(array $cards): string
{
    if (!$cards) {
        return '';
    }

    $group = [
        'term' => [
            'slug' => 'externe-berichte',
            'name' => __('Presse und externe Berichte', 'iss-content-model'),
        ],
        'cards' => $cards,
    ];

    ob_start();
    ?>
    <section id="externe-berichte" class="iss-video-library__section iss-video-library__section--external">
        <div class="iss-video-library__section-head">
            <p class="iss-video-library__playlist-label"><?php esc_html_e('Externe Blicke', 'iss-content-model'); ?></p>
            <h3 class="iss-video-library__section-title"><?php esc_html_e('Presse und Berichte ueber Schoneweide', 'iss-content-model'); ?></h3>
            <p class="iss-video-library__playlist-text"><?php esc_html_e('Diese Videos gehoeren nicht zum eigenen Bestand, helfen aber dabei, Aussenwahrnehmung, Berichterstattung und Kontext sichtbar zu machen.', 'iss-content-model'); ?></p>
            <p class="iss-video-library__series-count"><?php echo esc_html(sprintf(_n('%d Video', '%d Videos', count($cards), 'iss-content-model'), count($cards))); ?></p>
        </div>
        <?php echo iss_content_model_render_video_strip($group['cards']); ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '<div class="iss-video-library"><p class="iss-video-library__empty">' . esc_html__('Zurzeit sind noch keine Videos veröffentlicht.', 'iss-content-model') . '</p></div>';
    }
    iss_content_model_enqueue_video_library_assets();

    $wrapper = (function_exists('get_block_wrapper_attributes') && $block instanceof WP_Block)
        ? get_block_wrapper_attributes([
            'class' => 'iss-video-library iss-video-library--interactive',
            'data-video-library-root' => '1',
        ])
        : 'class="iss-video-library iss-video-library--interactive" data-video-library-root="1"';

    ob_start();
    ?>
    <div <?php echo $wrapper; ?>>
        <div class="iss-video-library__feature-column">
            <?php echo iss_content_model_render_video_library_feature($payload['featured']); ?>
        </div>

        <div class="iss-video-library__main-column">
            <?php echo iss_content_model_render_video_library_filters($payload['groups'], $payload['external_cards']); ?>

            <div class="iss-video-library__playlists">
                <?php
                foreach ($payload['groups'] as $group) {
                    echo iss_content_model_render_video_playlist_section($group);
                }
                ?>
            </div>

            <?php echo iss_content_model_render_video_external_reports_section($payload['external_cards']); ?>

            <?php echo iss_content_model_render_video_library_cta(); ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_filters_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    return iss_content_model_render_video_library_filters($payload['groups'], $payload['external_cards']);
}

function iss_content_model_render_video_library_feature_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    iss_content_model_enqueue_video_library_assets();
    return iss_content_model_render_video_library_feature($payload['featured']);
}

function iss_content_model_render_video_library_playlists_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    iss_content_model_enqueue_video_library_assets();
    ob_start();
    echo '<div class="iss-video-library__playlists">';
    foreach ($payload['groups'] as $group) {
        echo iss_content_model_render_video_playlist_section($group);
    }
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_content_model_render_video_library_external_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    iss_content_model_enqueue_video_library_assets();
    return iss_content_model_render_video_external_reports_section($payload['external_cards']);
}

function iss_content_model_render_video_library_inventory_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    iss_content_model_enqueue_video_library_assets();
    return iss_content_model_render_video_inventory_section($payload['cards']);
}

function iss_content_model_render_video_library_cta_block($attributes = [], $content = '', $block = null): string
{
    $payload = iss_content_model_get_video_library_payload();
    if ($payload === []) {
        return '';
    }

    return iss_content_model_render_video_library_cta();
}

function iss_content_model_get_single_video_card(int $post_id): ?array
{
    $post = get_post($post_id);
    if (!$post instanceof WP_Post || $post->post_type !== ISS_CONTENT_MODEL_VIDEO_POST_TYPE) {
        return null;
    }

    $cards = iss_content_model_get_video_cards();
    foreach ($cards as $card) {
        if ((int) ($card['id'] ?? 0) === $post_id) {
            return $card;
        }
    }

    return null;
}

function iss_content_model_render_video_player_block($attributes = [], $content = '', $block = null): string
{
    $post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
    $card = iss_content_model_get_single_video_card($post_id);
    if (!$card) {
        return '';
    }

    $wrapper = (function_exists('get_block_wrapper_attributes') && $block instanceof WP_Block)
        ? get_block_wrapper_attributes(['class' => 'iss-video-single-player'])
        : 'class="iss-video-single-player"';

    $excerpt = trim((string) $card['excerpt']);
    $category_names = implode(', ', array_filter(array_map(static function ($category) {
        return (string) ($category['name'] ?? '');
    }, $card['categories'])));
    $meta_rows = [];
    if ($card['original_date_label'] !== '') {
        $meta_rows[] = [
            'label' => __('Datum', 'iss-content-model'),
            'value' => $card['original_date_label'],
        ];
    }
    if (($card['year_label'] !== '') || ($card['original_date_label'] === '' && $card['year'] !== '')) {
        $meta_rows[] = [
            'label' => __('Jahr', 'iss-content-model'),
            'value' => $card['year'],
        ];
    }
    if ($card['duration'] !== '') {
        $meta_rows[] = [
            'label' => __('Dauer', 'iss-content-model'),
            'value' => $card['duration'],
        ];
    }
    if ($card['language'] !== '') {
        $meta_rows[] = [
            'label' => __('Sprache', 'iss-content-model'),
            'value' => $card['language'],
        ];
    }
    if ($card['source_label'] !== '') {
        $meta_rows[] = [
            'label' => __('Quelle', 'iss-content-model'),
            'value' => $card['source_label'],
        ];
    }
    if ($card['rights'] !== '') {
        $meta_rows[] = [
            'label' => __('Rechte', 'iss-content-model'),
            'value' => $card['rights'],
        ];
    }
    $meta_rows[] = [
        'label' => __('Transkript', 'iss-content-model'),
        'value' => $card['transcript_status_label'],
    ];

    ob_start();
    ?>
    <section <?php echo $wrapper; ?>>
        <div class="iss-video-single-player__layout">
            <div class="iss-video-single-player__media">
                <iframe
                    data-video-single-frame
                    src="<?php echo esc_url($card['embed_url']); ?>"
                    title="<?php echo esc_attr($card['title']); ?>"
                    loading="lazy"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                ></iframe>
            </div>
            <div class="iss-video-single-player__copy">
                <?php if ($category_names !== '') : ?>
                    <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($category_names); ?></p>
                <?php endif; ?>
                <h1 class="iss-video-single-player__title"><?php echo esc_html($card['title']); ?></h1>
                <?php if ($excerpt !== '') : ?>
                    <p class="iss-video-single-player__standfirst"><?php echo esc_html($excerpt); ?></p>
                <?php endif; ?>
                <dl class="iss-video-single-player__meta">
                    <?php foreach ($meta_rows as $row) : ?>
                        <div class="iss-video-single-player__meta-row">
                            <dt><?php echo esc_html($row['label']); ?></dt>
                            <dd><?php echo esc_html((string) $row['value']); ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <p class="iss-video-single-player__links">
                    <a class="iss-video-single-player__source-link" href="<?php echo esc_url($card['source_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e('YouTube', 'iss-content-model'); ?>">
                        <svg class="iss-video-single-player__youtube-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M21.6 7.2c-.2-.8-.8-1.4-1.6-1.6C18.6 5.2 12 5.2 12 5.2s-6.6 0-8 .4c-.8.2-1.4.8-1.6 1.6C2 8.6 2 12 2 12s0 3.4.4 4.8c.2.8.8 1.4 1.6 1.6 1.4.4 8 .4 8 .4s6.6 0 8-.4c.8-.2 1.4-.8 1.6-1.6.4-1.4.4-4.8.4-4.8s0-3.4-.4-4.8Z" />
                            <path class="iss-video-single-player__youtube-play" d="m10 15.2 5.2-3.2L10 8.8v6.4Z" />
                        </svg>
                    </a>
                    <?php if (!empty($card['has_transcript'])) : ?>
                        <a class="iss-action-link" href="#transkript"><?php echo esc_html($card['transcript_link_label']); ?></a>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}

function iss_content_model_render_video_transcript_block($attributes = [], $content = '', $block = null): string
{
    $post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_CONTENT_MODEL_VIDEO_POST_TYPE) {
        return '';
    }

    $content_html = apply_filters('the_content', (string) get_post_field('post_content', $post_id));
    if (trim(wp_strip_all_tags($content_html)) === '') {
        return '';
    }
    $content_html = iss_content_model_prepare_video_transcript_html((string) $content_html);
    $nav_items = iss_content_model_get_video_transcript_nav_items($content_html);

    $transcript_status = iss_content_model_resolve_video_transcript_status(
        (string) get_post_meta($post_id, 'iss_video_transcript_status', true),
        true
    );

    $wrapper = (function_exists('get_block_wrapper_attributes') && $block instanceof WP_Block)
        ? get_block_wrapper_attributes(['class' => 'iss-video-transcript'])
        : 'class="iss-video-transcript"';

    ob_start();
    ?>
    <section id="transkript" <?php echo $wrapper; ?>>
        <div class="iss-video-transcript__layout">
            <aside class="iss-video-transcript__rail">
                <div class="iss-video-transcript__rail-inner">
                    <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Navigation', 'iss-content-model'); ?></p>
                    <?php if ($nav_items) : ?>
                        <nav class="iss-video-transcript__nav" aria-label="<?php echo esc_attr__('Transkript-Zeitmarken', 'iss-content-model'); ?>">
                            <?php foreach ($nav_items as $item) : ?>
                                <a href="#<?php echo esc_attr($item['id']); ?>" data-video-seek="<?php echo esc_attr((string) $item['seconds']); ?>">
                                    <span><?php echo esc_html($item['label']); ?></span>
                                    <strong><?php echo esc_html($item['text']); ?></strong>
                                </a>
                            <?php endforeach; ?>
                        </nav>
                    <?php else : ?>
                        <p class="iss-video-transcript__rail-empty"><?php esc_html_e('Noch keine Zeitmarken im Text.', 'iss-content-model'); ?></p>
                    <?php endif; ?>
                </div>
            </aside>
            <div class="iss-video-transcript__main">
                <div class="iss-heading">
                    <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($transcript_status === 'full' ? __('Transkript', 'iss-content-model') : __('Textmaterial', 'iss-content-model')); ?></p>
                    <h2 class="iss-heading__title"><?php echo esc_html($transcript_status === 'full' ? __('Gesprächsdokumentation', 'iss-content-model') : __('Begleittext und Kontext', 'iss-content-model')); ?></h2>
                </div>
                <div class="iss-video-transcript__body">
                    <?php echo $content_html; ?>
                </div>
            </div>
            <?php if (trim(wp_strip_all_tags((string) $content)) !== '') : ?>
                <aside class="iss-video-transcript__related" aria-label="<?php echo esc_attr__('Weiterfuehrende Inhalte', 'iss-content-model'); ?>">
                    <div class="iss-video-transcript__related-inner">
                        <?php echo $content; ?>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
    </section>
    <?php

    return (string) ob_get_clean();
}
