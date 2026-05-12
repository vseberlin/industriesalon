<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_register_blocks(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    $archive_exhibition_dir = ISS_WF_IMPORT_PATH . 'blocks/archive-exhibition';
    if (file_exists($archive_exhibition_dir . '/block.json')) {
        register_block_type($archive_exhibition_dir, [
            'render_callback' => 'iss_wf_import_render_archive_exhibition_block',
        ]);
    }

    register_block_type('iss-wf-import/archive-collection', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_collection_block',
    ]);

    register_block_type('iss-wf-import/archive-album', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_album_block',
    ]);

    register_block_type('iss-wf-import/archive-object-media', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_object_media_block',
    ]);

    register_block_type('iss-wf-import/archive-object-browser', [
        'api_version' => 2,
        'render_callback' => 'iss_wf_import_render_archive_object_browser_block',
    ]);
}
add_action('init', 'iss_wf_import_register_blocks', 20);

function iss_wf_import_get_archive_exhibition_posts(string $term_slug): array
{
    $term_slug = sanitize_title($term_slug);
    if ($term_slug === '') {
        return [];
    }

    return get_posts([
        'post_type' => ISS_WF_IMPORT_POST_TYPE,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'ASC',
        'tax_query' => [
            [
                'taxonomy' => ISS_WF_IMPORT_CATEGORY_TAXONOMY,
                'field' => 'slug',
                'terms' => [$term_slug],
            ],
        ],
        'suppress_filters' => true,
    ]);
}

function iss_wf_import_get_archive_exhibition_anchor(WP_Post $post, int $index): string
{
    $slug = sanitize_title($post->post_name ?: $post->post_title);

    if ($slug === '') {
        $slug = 'kapitel-' . max(1, $index + 1);
    }

    return 'kapitel-' . $slug;
}

function iss_wf_import_split_archive_exhibition_body(string $html, int $target_chars = 700, int $max_blocks = 2, int $max_single_block_chars = 650): array
{
    $html = trim($html);
    if ($html === '' || !class_exists('DOMDocument')) {
        return [
            'lead' => '',
            'rest' => $html,
        ];
    }

    $document = new DOMDocument('1.0', 'UTF-8');
    $wrapped = '<!DOCTYPE html><html><body><div id="iss-archive-exhibition-body-root">' . $html . '</div></body></html>';
    libxml_use_internal_errors(true);
    $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    if (!$loaded) {
        return [
            'lead' => '',
            'rest' => $html,
        ];
    }

    $root = $document->getElementById('iss-archive-exhibition-body-root');
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

function iss_wf_import_render_archive_exhibition_toc(string $term_slug): string
{
    $posts = iss_wf_import_get_archive_exhibition_posts($term_slug);
    if (!$posts) {
        return '';
    }

    ob_start();
    echo '<nav id="kapitelverzeichnis" class="iss-digital-exhibition__toc" aria-label="' . esc_attr__('Kapitelverzeichnis', 'iss-wf-import') . '">';
    echo '<ol class="iss-digital-exhibition__toc-list">';

    foreach ($posts as $index => $post) {
        $anchor = iss_wf_import_get_archive_exhibition_anchor($post, $index);
        echo '<li class="iss-digital-exhibition__toc-item">';
        echo '<a class="iss-digital-exhibition__toc-link" href="#' . esc_attr($anchor) . '">';
        echo '<span class="iss-digital-exhibition__toc-number">' . esc_html(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) . '</span>';
        echo '<span class="iss-digital-exhibition__toc-copy">';
        echo '<span class="iss-digital-exhibition__toc-title">' . esc_html(get_the_title($post)) . '</span>';
        echo '<span class="iss-digital-exhibition__toc-date">' . esc_html(get_the_date('j. F Y', $post)) . '</span>';
        echo '</span>';
        echo '</a>';
        echo '</li>';
    }

    echo '</ol>';
    echo '</nav>';

    return (string) ob_get_clean();
}

function iss_wf_import_render_archive_exhibition_cards(string $term_slug): string
{
    $posts = iss_wf_import_get_archive_exhibition_posts($term_slug);
    if (!$posts) {
        return '';
    }

    ob_start();
    echo '<div class="iss-digital-exhibition__chapter-query">';
    echo '<div class="wp-block-post-template is-layout-grid columns-3 iss-digital-exhibition__cards-grid">';

    foreach ($posts as $post) {
        $excerpt = trim((string) get_the_excerpt($post));
        echo '<article class="iss-digital-exhibition__chapter-card">';
        echo '<p class="iss-digital-exhibition__chapter-kicker">Kapitel</p>';
        echo '<h3 class="iss-digital-exhibition__chapter-title"><a href="' . esc_url((string) get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h3>';
        echo '<p class="iss-digital-exhibition__chapter-text">' . esc_html(wp_trim_words(wp_strip_all_tags($excerpt), 24, '...')) . '</p>';
        echo '</article>';
    }

    echo '</div>';
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_wf_import_render_archive_exhibition_stream(string $term_slug): string
{
    $posts = iss_wf_import_get_archive_exhibition_posts($term_slug);
    if (!$posts) {
        return '';
    }

    ob_start();
    echo '<div class="iss-digital-exhibition__stream">';
    foreach ($posts as $index => $post) {
        $anchor = iss_wf_import_get_archive_exhibition_anchor($post, $index);
        $content = apply_filters('the_content', (string) $post->post_content);
        $intro_html = '';
        $body_html = $content;
        $image_html = has_post_thumbnail($post) ? (string) get_the_post_thumbnail($post, 'large') : '';
        $image_caption = '';
        $thumbnail_id = (int) get_post_thumbnail_id($post);

        if ($thumbnail_id > 0) {
            $image_caption = trim((string) wp_get_attachment_caption($thumbnail_id));
        }

        if (preg_match('/<p\b[^>]*>.*?<\/p>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $intro_html = trim((string) $matches[0][0]);
            $start = (int) $matches[0][1];
            $length = strlen((string) $matches[0][0]);
            $body_html = trim(substr($content, 0, $start) . substr($content, $start + $length));
        }

        if ($intro_html === '') {
            $intro_text = trim((string) get_the_excerpt($post));
            if ($intro_text !== '') {
                $intro_html = '<p>' . esc_html(wp_strip_all_tags($intro_text)) . '</p>';
            }
        }

        $body_parts = iss_wf_import_split_archive_exhibition_body($body_html);
        $lead_body_html = $body_parts['lead'];
        $rest_body_html = $body_parts['rest'];

        $entry_classes = 'iss-digital-exhibition__chapter-entry';
        if ($index % 2 === 1) {
            $entry_classes .= ' iss-digital-exhibition__chapter-entry--reverse';
        }

        echo '<article id="' . esc_attr($anchor) . '" class="' . esc_attr($entry_classes) . '">';
        echo '<div class="iss-digital-exhibition__chapter-top">';
        echo '<div class="iss-digital-exhibition__chapter-copy">';
        echo '<p class="iss-kicker iss-kicker--compact">Kapitel ' . esc_html((string) ($index + 1)) . '</p>';
        echo '<h2 class="iss-digital-exhibition__entry-title"><a href="' . esc_url((string) get_permalink($post)) . '">' . esc_html(get_the_title($post)) . '</a></h2>';
        echo '<p class="iss-digital-exhibition__entry-date">' . esc_html(get_the_date('j. F Y', $post)) . '</p>';
        if ($intro_html !== '') {
            echo '<div class="iss-digital-exhibition__entry-intro">' . $intro_html . '</div>';
        }
        if ($lead_body_html !== '') {
            echo '<div class="iss-post-body__content iss-digital-exhibition__entry-body-lead">';
            echo $lead_body_html;
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="iss-digital-exhibition__chapter-media">';
        if ($image_html !== '') {
            echo '<figure class="iss-digital-exhibition__chapter-figure">';
            echo '<a href="' . esc_url((string) get_permalink($post)) . '">' . $image_html . '</a>';
            if ($image_caption !== '') {
                echo '<figcaption>' . esc_html($image_caption) . '</figcaption>';
            }
            echo '</figure>';
        }
        echo '</div>';
        echo '</div>';

        if ($rest_body_html !== '') {
            echo '<div class="iss-post-body__content iss-digital-exhibition__entry-body">';
            echo $rest_body_html;
            echo '</div>';
        } elseif ($lead_body_html === '') {
            echo '<div class="iss-post-body__content iss-digital-exhibition__entry-body">';
            echo $body_html;
            echo '</div>';
        }

        echo '<p class="iss-digital-exhibition__chapter-return"><a class="iss-action-link" href="#kapitelverzeichnis">' . esc_html__('Zurück zum Kapitelverzeichnis', 'iss-wf-import') . '</a></p>';
        echo '</article>';
    }
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_wf_import_render_archive_exhibition_block($attributes = [], $content = '', $block = null): string
{
    $term_slug = sanitize_title((string) ($attributes['termSlug'] ?? ''));
    $layout = sanitize_key((string) ($attributes['layout'] ?? 'cards'));

    if ($term_slug === '') {
        return '';
    }

    if ($layout === 'toc') {
        return iss_wf_import_render_archive_exhibition_toc($term_slug);
    }

    if ($layout === 'stream') {
        return iss_wf_import_render_archive_exhibition_stream($term_slug);
    }

    return iss_wf_import_render_archive_exhibition_cards($term_slug);
}

function iss_wf_import_get_archive_image_meta_line(array $image): string
{
    $parts = [];

    foreach (['creator', 'owner', 'rights'] as $key) {
        $value = trim((string) ($image[$key] ?? ''));
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    return implode(' · ', array_slice($parts, 0, 3));
}

function iss_wf_import_get_archive_image_display(array $image, string $size = 'large'): array
{
    $attachment_id = absint($image['preview_attachment_id'] ?? 0);
    if ($attachment_id <= 0) {
        $attachment_id = absint($image['attachment_id'] ?? 0);
    }

    $href = '';
    if ($attachment_id > 0) {
        $href = (string) wp_get_attachment_url($attachment_id);
    }

    if ($href === '') {
        $href = esc_url_raw((string) ($image['source_url'] ?? ($image['preview_url'] ?? '')));
    }

    if ($attachment_id > 0) {
        $html = (string) wp_get_attachment_image($attachment_id, $size);
    } else {
        $src = esc_url((string) ($image['preview_url'] ?? ($image['source_url'] ?? '')));
        $alt = esc_attr((string) ($image['label'] ?? ''));
        $html = $src !== '' ? '<img src="' . $src . '" alt="' . $alt . '" loading="lazy" />' : '';
    }

    return [
        'html' => $html,
        'href' => $href,
    ];
}

function iss_wf_import_render_archive_object_card(array $item): string
{
    $post_id = absint($item['object_id'] ?? 0);
    $post = $post_id > 0 ? get_post($post_id) : null;
    $title = trim((string) ($item['title'] ?? ''));
    $caption = trim((string) ($item['caption_override'] ?? ''));
    $page_label = trim((string) ($item['page_label'] ?? ''));
    $link_url = '';
    $image_html = '';

    if ($post instanceof WP_Post && $post->post_type === ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        if ($title === '') {
            $title = get_the_title($post);
        }

        $link_url = (string) get_permalink($post);
        if (has_post_thumbnail($post)) {
            $image_html = (string) get_the_post_thumbnail($post, 'large');
        }

        if ($caption === '') {
            $projection = iss_wf_import_get_object_service()->get_projection_for_post((int) $post->ID);
            $caption = is_array($projection)
                ? trim((string) ($projection['creator_label'] ?? ''))
                : trim((string) get_post_meta($post->ID, ISS_WF_IMPORT_OBJECT_CREATOR_META, true));
            if ($caption === '') {
                $caption = trim((string) get_the_excerpt($post));
            }
        }
    }

    if ($title === '') {
        $title = __('Archivobjekt', 'iss-wf-import');
    }

    if ($link_url === '') {
        $link_url = esc_url_raw((string) ($item['source_url'] ?? ''));
    }

    $caption = trim((string) preg_replace('/\s+/u', ' ', $caption));

    ob_start();
    ?>
    <article class="iss-archive-card iss-archive-card--mediathek iss-archive-records__card">
        <?php if ($image_html !== '') : ?>
            <a class="iss-archive-card__media" href="<?php echo esc_url($link_url); ?>">
                <?php echo $image_html; ?>
            </a>
        <?php endif; ?>
        <div class="iss-archive-card__body">
            <span class="iss-archive-card__type"><?php esc_html_e('Archivobjekt', 'iss-wf-import'); ?></span>
            <h3 class="iss-archive-card__title">
                <a class="iss-archive-card__link" href="<?php echo esc_url($link_url); ?>"><?php echo esc_html($title); ?></a>
            </h3>
            <?php if ($caption !== '') : ?>
                <p class="iss-archive-card__text"><?php echo esc_html($caption); ?></p>
            <?php endif; ?>
            <?php if ($page_label !== '') : ?>
                <div class="iss-archive-card__meta">
                    <span><?php echo esc_html($page_label); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_get_public_source_label(string $slug, string $name = ''): string
{
    $slug = sanitize_title($slug);
    $name = trim($name);

    $map = [
        'museum-digital' => 'museum-digital',
        'wf-museum' => 'WF-Museum',
        'ddb' => 'Deutsche Digitale Bibliothek',
        'deutsche-digitale-bibliothek' => 'Deutsche Digitale Bibliothek',
        'europeana' => 'Europeana',
    ];

    if ($slug !== '' && isset($map[$slug])) {
        return $map[$slug];
    }

    return $name !== '' ? $name : $slug;
}

function iss_wf_import_get_public_source_names(int $post_id): array
{
    $terms = get_the_terms($post_id, ISS_WF_IMPORT_SOURCE_TAXONOMY);
    if (!is_array($terms)) {
        return [];
    }

    $names = [];
    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $label = iss_wf_import_get_public_source_label($term->slug, $term->name);
        if ($label !== '') {
            $names[] = $label;
        }
    }

    return array_values(array_unique($names));
}

function iss_wf_import_render_archive_collection_card(array $item): string
{
    $post_id = absint($item['collection_id'] ?? 0);
    $post = $post_id > 0 ? get_post($post_id) : null;

    if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return '';
    }

    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '') {
        $title = get_the_title($post);
    }

    $excerpt = trim((string) get_the_excerpt($post));
    $permalink = (string) get_permalink($post);

    ob_start();
    ?>
    <article class="iss-archive-card iss-archive-card--sammlung iss-archive-records__card">
        <?php if (has_post_thumbnail($post)) : ?>
            <a class="iss-archive-card__media" href="<?php echo esc_url($permalink); ?>">
                <?php echo get_the_post_thumbnail($post, 'large'); ?>
            </a>
        <?php endif; ?>
        <div class="iss-archive-card__body">
            <span class="iss-archive-card__type"><?php esc_html_e('Archivsammlung', 'iss-wf-import'); ?></span>
            <h3 class="iss-archive-card__title">
                <a class="iss-archive-card__link" href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a>
            </h3>
            <?php if ($excerpt !== '') : ?>
                <p class="iss-archive-card__text"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_get_collection_items_for_render(int $post_id): array
{
    $projection = iss_wf_import_get_collection_service()->get_projection_for_post($post_id);
    $items = is_array($projection) ? (array) ($projection['items'] ?? []) : null;
    if (!is_array($items)) {
        $items = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_ITEMS_META, true);
        if (!is_array($items)) {
            return [];
        }
    }

    return array_values(array_filter($items, static function ($item): bool {
        return is_array($item) && (absint($item['object_id'] ?? 0) > 0 || !empty($item['source_url']));
    }));
}

function iss_wf_import_get_collection_children_for_render(int $post_id): array
{
    $projection = iss_wf_import_get_collection_service()->get_projection_for_post($post_id);
    $children = is_array($projection) ? (array) ($projection['children'] ?? []) : null;
    if (!is_array($children)) {
        $children = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_CHILDREN_META, true);
        if (!is_array($children)) {
            return [];
        }
    }

    return array_values(array_filter($children, static function ($item): bool {
        return is_array($item) && absint($item['collection_id'] ?? 0) > 0;
    }));
}

function iss_wf_import_get_archive_album_anchor(array $item, int $index): string
{
    $page_label = sanitize_title((string) ($item['page_label'] ?? ''));
    if ($page_label !== '') {
        return 'album-' . $page_label;
    }

    $object_id = absint($item['object_id'] ?? 0);
    if ($object_id > 0) {
        $post = get_post($object_id);
        if ($post instanceof WP_Post) {
            $slug = sanitize_title($post->post_name ?: $post->post_title);
            if ($slug !== '') {
                return 'album-' . $slug;
            }
        }
    }

    return 'album-item-' . max(1, $index + 1);
}

function iss_wf_import_get_archive_album_source_links(int $post_id): array
{
    $links = [];

    $projection = iss_wf_import_get_collection_service()->get_projection_for_post($post_id);
    $source_url = is_array($projection)
        ? trim((string) (($projection['collection']['source_url'] ?? '')))
        : '';
    if ($source_url === '') {
        $source_url = trim((string) get_post_meta($post_id, ISS_WF_IMPORT_SOURCE_URL_META, true));
    }
    if ($source_url !== '') {
        $links[] = [
            'label' => __('Originalquelle', 'iss-wf-import'),
            'url' => $source_url,
        ];
    }

    $source_ids = is_array($projection)
        ? (array) ($projection['source_refs'] ?? [])
        : null;
    if (!is_array($source_ids) || !$source_ids) {
        $source_ids = get_post_meta($post_id, ISS_WF_IMPORT_COLLECTION_SOURCE_IDS_META, true);
    }
    if (is_array($source_ids)) {
        foreach ($source_ids as $item) {
            if (!is_array($item)) {
                continue;
            }

            $url = trim((string) ($item['source_url'] ?? ''));
            if ($url === '') {
                continue;
            }

            $label = trim((string) ($item['label'] ?? ''));
            $links[] = [
                'label' => $label !== '' ? $label : __('Digitaler Nachweis', 'iss-wf-import'),
                'url' => $url,
            ];
        }
    }

    $unique = [];
    foreach ($links as $link) {
        if ($link['url'] === '') {
            continue;
        }

        $unique[$link['url']] = $link;
    }

    return array_values($unique);
}

function iss_wf_import_get_archive_album_display_title(array $item, ?WP_Post $post = null): string
{
    $title = trim((string) ($item['title'] ?? ''));
    if ($title === '' && $post instanceof WP_Post) {
        $title = get_the_title($post);
    }

    $title = trim(wp_strip_all_tags($title));
    if ($title === '') {
        return '';
    }

    $title = preg_replace('/\s+/u', ' ', $title);
    $short = preg_replace('/,\s*S\.\s*\d+\s+des\s+Fotoalbums.*$/ui', '', $title);
    $short = is_string($short) ? trim($short, " \t\n\r\0\x0B,.;:-") : '';

    return $short !== '' ? $short : $title;
}

function iss_wf_import_render_archive_album_item(array $item, int $index): string
{
    $object_id = absint($item['object_id'] ?? 0);
    $post = $object_id > 0 ? get_post($object_id) : null;

    if (!$post instanceof WP_Post || $post->post_type !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return '';
    }

    $anchor = iss_wf_import_get_archive_album_anchor($item, $index);
    $page_label = trim((string) ($item['page_label'] ?? ''));
    $title = iss_wf_import_get_archive_album_display_title($item, $post);

    $caption = trim((string) ($item['caption_override'] ?? ''));
    if ($caption === '') {
        $caption = trim((string) get_the_excerpt($post));
    }
    $caption = trim(wp_strip_all_tags($caption));
    if ($caption !== '' && $title !== '' && iss_wf_import_get_archive_album_display_title(['title' => $caption], null) === $title) {
        $caption = '';
    }

    $permalink = (string) get_permalink($post);
    $thumbnail_id = (int) get_post_thumbnail_id($post);
    $image_html = '';
    if ($thumbnail_id > 0) {
        $image_html = (string) wp_get_attachment_image($thumbnail_id, 'large', false, [
            'loading' => 'eager',
            'decoding' => 'async',
            'fetchpriority' => $index < 2 ? 'high' : 'auto',
        ]);
    } elseif (has_post_thumbnail($post)) {
        $image_html = (string) get_the_post_thumbnail($post, 'large', [
            'loading' => 'eager',
            'decoding' => 'async',
        ]);
    }
    $source_url = trim((string) ($item['source_url'] ?? ''));

    ob_start();
    ?>
    <article id="<?php echo esc_attr($anchor); ?>" class="iss-archive-album__item">
        <?php if ($image_html !== '') : ?>
            <figure class="iss-archive-album__figure">
                <a href="<?php echo esc_url($permalink); ?>">
                    <?php echo $image_html; ?>
                </a>
                <figcaption class="iss-archive-album__item-copy">
                    <?php if ($page_label !== '') : ?>
                        <p class="iss-kicker iss-kicker--compact iss-archive-album__item-page"><?php echo esc_html($page_label); ?></p>
                    <?php endif; ?>
                    <h3 class="iss-archive-album__item-title"><?php echo esc_html($title); ?></h3>
                    <?php if ($caption !== '') : ?>
                        <div class="iss-archive-album__item-text">
                            <p><?php echo esc_html($caption); ?></p>
                        </div>
                    <?php endif; ?>
                    <?php if ($source_url !== '') : ?>
                        <p class="iss-archive-album__item-actions">
                            <a class="iss-action-link" href="<?php echo esc_url($source_url); ?>"><?php esc_html_e('Original ansehen', 'iss-wf-import'); ?></a>
                        </p>
                    <?php endif; ?>
                </figcaption>
            </figure>
        <?php endif; ?>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_render_archive_album_block($attributes = [], $content = '', $block = null): string
{
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return '';
    }

    $items = iss_wf_import_get_collection_items_for_render($post_id);
    if (!$items) {
        return '';
    }

    $source_links = iss_wf_import_get_archive_album_source_links($post_id);
    $source_names = iss_wf_import_get_public_source_names($post_id);

    $album_items = [];
    $album_nav = [];
    foreach ($items as $index => $item) {
        $anchor = iss_wf_import_get_archive_album_anchor($item, $index);
        $page_label = trim((string) ($item['page_label'] ?? ''));
        $object_id = absint($item['object_id'] ?? 0);
        $post = $object_id > 0 ? get_post($object_id) : null;
        $title = iss_wf_import_get_archive_album_display_title($item, $post instanceof WP_Post ? $post : null);

        $rendered_item = iss_wf_import_render_archive_album_item($item, $index);
        if ($rendered_item === '') {
            continue;
        }

        $album_items[] = $rendered_item;
        $album_nav[] = sprintf(
            '<li class="iss-archive-album__nav-item"><a href="#%1$s"><span class="iss-archive-album__nav-page">%2$s</span><span class="iss-archive-album__nav-title">%3$s</span></a></li>',
            esc_attr($anchor),
            esc_html($page_label !== '' ? $page_label : 'S.' . ($index + 1)),
            esc_html($title)
        );
    }

    if (!$album_items) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-archive-album',
        ])
        : 'class="section section--plain iss-archive-album"';

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';

    $out .= '<div class="iss-archive-album__layout">';
    $out .= '<aside class="iss-archive-album__sidebar">';
    $out .= '<div class="iss-archive-album__overview">';
    $out .= '<div class="iss-archive-album__facts iss-info-panel iss-info-panel--red">';
    $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Albumdaten', 'iss-wf-import') . '</p>';
    $out .= '<h2 class="iss-info-panel__title">' . esc_html__('Bestand und Nachweise', 'iss-wf-import') . '</h2>';
    $out .= '<div class="iss-info-panel__rows">';
    $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html__('Umfang', 'iss-wf-import') . '</strong></p><p class="iss-info-row__text">' . esc_html(sprintf(_n('%d Albumblatt', '%d Albumblätter', count($album_items), 'iss-wf-import'), count($album_items))) . '</p></div></div>';
    $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html__('Bestand', 'iss-wf-import') . '</strong></p><p class="iss-info-row__text">' . esc_html__('Industriesalon Schöneweide', 'iss-wf-import') . '</p></div></div>';

    if ($source_names) {
        $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html__('Digitale Nachweise', 'iss-wf-import') . '</strong></p><p class="iss-info-row__text">' . esc_html(implode(', ', $source_names)) . '</p></div></div>';
    }

    if ($source_links) {
        $link_parts = [];
        foreach ($source_links as $link) {
            $link_parts[] = '<a href="' . esc_url($link['url']) . '">' . esc_html($link['label']) . '</a>';
        }
        $out .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html__('Original', 'iss-wf-import') . '</strong></p><p class="iss-info-row__text">' . implode(' · ', $link_parts) . '</p></div></div>';
    }

    $out .= '</div>';
    $out .= '</div>';

    $out .= '<div class="iss-archive-album__navigator">';
    $out .= '<div class="iss-heading iss-archive-album__navigator-head">';
    $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Albumfolge', 'iss-wf-import') . '</p>';
    $out .= '<h2 class="iss-heading__title">' . esc_html__('Seiten und Motive', 'iss-wf-import') . '</h2>';
    $out .= '</div>';
    $out .= '<ol class="iss-archive-album__nav-list">';
    $out .= implode('', $album_nav);
    $out .= '</ol>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</aside>';

    $out .= '<div class="iss-archive-album__main">';
    $out .= '<div class="iss-archive-album__sequence">';
    $out .= '<div class="iss-heading iss-archive-album__sequence-head">';
    $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Album lesen', 'iss-wf-import') . '</p>';
    $out .= '<h2 class="iss-heading__title">' . esc_html__('Lokale Reihenfolge des Albums', 'iss-wf-import') . '</h2>';
    $out .= '</div>';
    $out .= '<div class="iss-archive-album__items">';
    $out .= implode('', $album_items);
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';

    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_wf_import_render_archive_collection_block($attributes = [], $content = '', $block = null): string
{
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_WF_IMPORT_COLLECTION_POST_TYPE) {
        return '';
    }

    $items = iss_wf_import_get_collection_items_for_render($post_id);
    $children = iss_wf_import_get_collection_children_for_render($post_id);

    if (!$items && !$children) {
        $raw_content = trim((string) get_post_field('post_content', $post_id));
        if ($raw_content === '') {
            return '';
        }

        $wrapper = function_exists('get_block_wrapper_attributes')
            ? get_block_wrapper_attributes([
                'class' => 'section section--plain iss-archive-records iss-archive-records--fallback',
            ])
            : 'class="section section--plain iss-archive-records iss-archive-records--fallback"';

        return '<section ' . $wrapper . '><div class="iss-container"><div class="iss-post-body__content iss-post-body__content--wide">' . apply_filters('the_content', $raw_content) . '</div></div></section>';
    }

    $object_cards = [];
    foreach ($items as $item) {
        $card = iss_wf_import_render_archive_object_card($item);
        if ($card !== '') {
            $object_cards[] = $card;
        }
    }

    $child_cards = [];
    foreach ($children as $item) {
        $card = iss_wf_import_render_archive_collection_card($item);
        if ($card !== '') {
            $child_cards[] = $card;
        }
    }

    if (!$object_cards && !$child_cards) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-archive-records iss-archive-records--collection',
        ])
        : 'class="section section--plain iss-archive-records iss-archive-records--collection"';

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';

    if ($object_cards) {
        $out .= '<div class="iss-archive-records__section">';
        $out .= '<div class="iss-heading iss-archive-records__intro">';
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Lokale Sammlung', 'iss-wf-import') . '</p>';
        $out .= '<h2 class="iss-heading__title">' . esc_html__('Sammlungsobjekte', 'iss-wf-import') . '</h2>';
        $out .= '</div>';
        $out .= '<div class="iss-archive-grid iss-archive-records__grid">';
        $out .= implode('', $object_cards);
        $out .= '</div>';
        $out .= '</div>';
    }

    if ($child_cards) {
        $out .= '<div class="iss-archive-records__section">';
        $out .= '<div class="iss-heading iss-archive-records__intro">';
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Weitere Wege', 'iss-wf-import') . '</p>';
        $out .= '<h2 class="iss-heading__title">' . esc_html__('Weitere Alben und Sammlungen', 'iss-wf-import') . '</h2>';
        $out .= '</div>';
        $out .= '<div class="iss-archive-grid iss-archive-records__grid">';
        $out .= implode('', $child_cards);
        $out .= '</div>';
        $out .= '</div>';
    }

    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_wf_import_render_archive_object_media_block($attributes = [], $content = '', $block = null): string
{
    $post_id = (int) get_the_ID();
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return '';
    }

    $projection = iss_wf_import_get_media_service()->get_projection_for_post($post_id);
    $images = is_array($projection)
        ? (array) ($projection['media'] ?? [])
        : get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_IMAGE_SOURCE_META, true);
    $images = is_array($images) ? array_values(array_filter($images, static function ($item): bool {
        return is_array($item) && (!empty($item['source_url']) || absint($item['attachment_id'] ?? 0) > 0 || absint($item['preview_attachment_id'] ?? 0) > 0);
    })) : [];

    if (!$images) {
        return '';
    }

    $cards = [];
    foreach ($images as $image) {
        $display = iss_wf_import_get_archive_image_display($image);
        if ($display['html'] === '') {
            continue;
        }

        $caption = trim((string) ($image['label'] ?? ''));
        $meta_line = iss_wf_import_get_archive_image_meta_line($image);

        ob_start();
        ?>
        <figure class="iss-archive-card iss-archive-card--mediathek iss-archive-records__card iss-archive-media-card">
            <a class="iss-archive-card__media" href="<?php echo esc_url($display['href']); ?>">
                <?php echo $display['html']; ?>
            </a>
            <?php if ($caption !== '' || $meta_line !== '') : ?>
                <figcaption class="iss-archive-card__body">
                    <?php if ($caption !== '') : ?>
                        <p class="iss-archive-card__text"><?php echo esc_html($caption); ?></p>
                    <?php endif; ?>
                    <?php if ($meta_line !== '') : ?>
                        <div class="iss-archive-card__meta">
                            <span><?php echo esc_html($meta_line); ?></span>
                        </div>
                    <?php endif; ?>
                </figcaption>
            <?php endif; ?>
        </figure>
        <?php

        $cards[] = trim((string) ob_get_clean());
    }

    if (!$cards) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-archive-records iss-archive-records--object-media',
        ])
        : 'class="section section--plain iss-archive-records iss-archive-records--object-media"';

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';
    $out .= '<div class="iss-heading iss-archive-records__intro">';
    $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Lokale Medien', 'iss-wf-import') . '</p>';
    $out .= '<h2 class="iss-heading__title">' . esc_html__('Objektbilder', 'iss-wf-import') . '</h2>';
    $out .= '</div>';
    $out .= '<div class="iss-archive-grid iss-archive-records__grid">';
    $out .= implode('', $cards);
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_wf_import_get_archive_object_browser_request(): array
{
    $page = max(1, absint($_GET['seite'] ?? get_query_var('seite') ?? 0));
    if ($page <= 1) {
        $page = max(1, absint(get_query_var('paged')));
    }

    return [
        'source' => sanitize_title(wp_unslash((string) ($_GET['quelle'] ?? ''))),
        'field' => sanitize_title(wp_unslash((string) ($_GET['feld'] ?? ''))),
        'family' => sanitize_title(wp_unslash((string) ($_GET['familie'] ?? ''))),
        'context' => sanitize_title(wp_unslash((string) ($_GET['kontext'] ?? ''))),
        'search' => sanitize_text_field(wp_unslash((string) ($_GET['suche'] ?? ''))),
        'page' => $page,
    ];
}

function iss_wf_import_get_archive_object_browser_term_options(string $taxonomy, int $limit = 0): array
{
    return iss_wf_import_get_archive_browser_service()->get_term_options($taxonomy, $limit);
}

function iss_wf_import_get_archive_object_browser_selected_term(string $taxonomy, string $slug): ?WP_Term
{
    if ($slug === '' || !taxonomy_exists($taxonomy)) {
        return null;
    }

    $term = get_term_by('slug', $slug, $taxonomy);
    return $term instanceof WP_Term ? $term : null;
}

function iss_wf_import_get_archive_object_browser_terms_by_slugs(string $taxonomy, array $slugs): array
{
    return iss_wf_import_get_archive_browser_service()->get_terms_by_slugs($taxonomy, $slugs);
}

function iss_wf_import_get_archive_object_browser_stats(array $state = []): array
{
    return iss_wf_import_get_archive_browser_service()->get_stats($state);
}

function iss_wf_import_get_archive_object_browser_query(array $state, int $per_page = 18): object
{
    return iss_wf_import_get_archive_browser_service()->get_query($state, $per_page);
}

function iss_wf_import_get_archive_object_browser_url(array $overrides = [], bool $reset_page = false, ?array $base_state = null, string $base_url = ''): string
{
    $state = array_merge($base_state ?? iss_wf_import_get_archive_object_browser_request(), $overrides);
    $url = $base_url !== '' ? $base_url : get_post_type_archive_link(ISS_WF_IMPORT_OBJECT_POST_TYPE);
    if (!$url) {
        $url = home_url('/archivobjekte/');
    }

    $args = [];
    foreach ([
        'quelle' => $state['source'] ?? '',
        'feld' => $state['field'] ?? '',
        'familie' => $state['family'] ?? '',
        'kontext' => $state['context'] ?? '',
        'suche' => $state['search'] ?? '',
    ] as $key => $value) {
        $value = is_string($value) ? trim($value) : '';
        if ($value !== '') {
            $args[$key] = $value;
        }
    }

    $page = max(1, (int) ($state['page'] ?? 1));
    if (!$reset_page && $page > 1) {
        $args['seite'] = $page;
    }

    return $args ? add_query_arg($args, $url) : $url;
}

function iss_wf_import_get_archive_object_browser_result_meta(int $post_id): array
{
    $meta = [];

    $source_names = iss_wf_import_get_public_source_names($post_id);
    if ($source_names) {
        $meta[] = implode(', ', array_slice($source_names, 0, 2));
    }

    $projection = iss_wf_import_get_object_service()->get_projection_for_post($post_id);
    $year = is_array($projection)
        ? trim((string) ($projection['year_label'] ?? ''))
        : trim((string) get_post_meta($post_id, ISS_WF_IMPORT_OBJECT_YEAR_META, true));
    if ($year !== '') {
        $meta[] = $year;
    }

    $family_names = wp_get_post_terms($post_id, ISS_WF_IMPORT_FAMILY_TAXONOMY, ['fields' => 'names']);
    if (is_array($family_names) && $family_names) {
        $meta[] = implode(', ', array_slice(array_map('strval', $family_names), 0, 2));
    }

    return $meta;
}

function iss_wf_import_get_archive_object_browser_kicker(int $post_id): string
{
    $taxonomy_order = [
        ISS_WF_IMPORT_FAMILY_TAXONOMY,
        ISS_WF_IMPORT_FIELD_TAXONOMY,
        ISS_WF_IMPORT_CONTEXT_TAXONOMY,
    ];

    foreach ($taxonomy_order as $taxonomy) {
        $names = wp_get_post_terms($post_id, $taxonomy, ['fields' => 'names']);
        if (is_array($names) && !empty($names[0]) && is_string($names[0])) {
            return $names[0];
        }
    }

    return __('Archivobjekt', 'iss-wf-import');
}

function iss_wf_import_render_archive_object_browser_card(WP_Post $post): string
{
    $meta = iss_wf_import_get_archive_object_browser_result_meta($post->ID);
    $kicker = iss_wf_import_get_archive_object_browser_kicker($post->ID);
    $excerpt = trim((string) get_the_excerpt($post));
    $permalink = (string) get_permalink($post);
    if ($excerpt === '') {
        $excerpt = wp_trim_words(wp_strip_all_tags((string) $post->post_content), 26, '…');
    }

    ob_start();
    ?>
    <article class="iss-card iss-card--flat iss-archive-browser__result-card">
        <?php if (has_post_thumbnail($post)) : ?>
            <a class="iss-card__media" href="<?php echo esc_url($permalink); ?>">
                <?php echo get_the_post_thumbnail($post, 'large'); ?>
            </a>
        <?php endif; ?>
        <div class="iss-card__body">
            <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($kicker); ?></p>
            <h3 class="iss-card__title">
                <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html(get_the_title($post)); ?></a>
            </h3>
            <?php if ($excerpt !== '') : ?>
                <p class="iss-card__text"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
            <?php if ($meta) : ?>
                <div class="iss-card__meta">
                    <?php foreach ($meta as $item) : ?>
                        <span><?php echo esc_html($item); ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a class="iss-card__link" href="<?php echo esc_url($permalink); ?>"><?php esc_html_e('Objekt öffnen', 'iss-wf-import'); ?></a>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_wf_import_render_archive_object_browser_block($attributes = [], $content = '', $block = null): string
{
    $state = iss_wf_import_get_archive_object_browser_request();
    $default_source = sanitize_title((string) ($attributes['defaultSource'] ?? ''));
    $lock_source = !empty($attributes['lockSource']);
    $default_field = sanitize_title((string) ($attributes['defaultField'] ?? ''));
    $lock_field = !empty($attributes['lockField']);
    $browser_kicker = sanitize_text_field((string) ($attributes['kicker'] ?? __('Archiv', 'iss-wf-import')));
    $browser_title = sanitize_text_field((string) ($attributes['title'] ?? __('Archivobjekte', 'iss-wf-import')));
    $browser_intro = sanitize_text_field((string) ($attributes['introText'] ?? __('Lokale Objektkopien, Findbuchfotos und technische Bestände mit Quellenbezug. Das größte Segment stammt aktuell aus museum-digital; Themenfelder und Objektfamilien werden schrittweise für editorische Zugänge ergänzt.', 'iss-wf-import')));
    $quick_kicker = sanitize_text_field((string) ($attributes['quickKicker'] ?? __('Einstiege', 'iss-wf-import')));
    $quick_title = sanitize_text_field((string) ($attributes['quickTitle'] ?? __('Nachweise und strukturierte Zugänge', 'iss-wf-import')));
    $show_source_cards = array_key_exists('showSourceCards', $attributes) ? !empty($attributes['showSourceCards']) : true;
    $quick_family_slugs = is_array($attributes['quickFamilySlugs'] ?? null) ? array_values(array_map('sanitize_title', (array) $attributes['quickFamilySlugs'])) : [];
    $use_current_url = !empty($attributes['useCurrentUrl']);

    if ($default_source !== '' && ($lock_source || $state['source'] === '')) {
        $state['source'] = $default_source;
    }

    if ($default_field !== '' && ($lock_field || $state['field'] === '')) {
        $state['field'] = $default_field;
    }

    $base_url = '';
    if ($use_current_url) {
        $queried_id = get_queried_object_id();
        if ($queried_id > 0) {
            $base_url = (string) get_permalink($queried_id);
        }
    }

    if ($base_url === '') {
        $base_url = (string) (get_post_type_archive_link(ISS_WF_IMPORT_OBJECT_POST_TYPE) ?: home_url('/archivobjekte/'));
    }

    $selected_terms = [
        'source' => iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_SOURCE_TAXONOMY, $state['source']),
        'field' => iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_FIELD_TAXONOMY, $state['field']),
        'family' => iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_FAMILY_TAXONOMY, $state['family']),
        'context' => iss_wf_import_get_archive_object_browser_selected_term(ISS_WF_IMPORT_CONTEXT_TAXONOMY, $state['context']),
    ];

    $query = iss_wf_import_get_archive_object_browser_query($state, 18);
    $stats = iss_wf_import_get_archive_object_browser_stats($state);
    $source_options = iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_SOURCE_TAXONOMY);
    $field_options = iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_FIELD_TAXONOMY);
    $family_options = iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_FAMILY_TAXONOMY, 8);
    $context_options = iss_wf_import_get_archive_object_browser_term_options(ISS_WF_IMPORT_CONTEXT_TAXONOMY);
    $quick_families = $quick_family_slugs
        ? iss_wf_import_get_archive_object_browser_terms_by_slugs(ISS_WF_IMPORT_FAMILY_TAXONOMY, $quick_family_slugs)
        : array_slice($family_options, 0, 4);
    $active_filters = array_values(array_filter($selected_terms));
    $cards = [];

    foreach ($query->posts as $post) {
        if ($post instanceof WP_Post) {
            $cards[] = iss_wf_import_render_archive_object_browser_card($post);
        }
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section iss-archive-browser',
        ])
        : 'class="section iss-archive-browser"';

    $results_label = sprintf(
        _n('%d Archivobjekt', '%d Archivobjekte', (int) $query->found_posts, 'iss-wf-import'),
        (int) $query->found_posts
    );

    $pagination = paginate_links([
        'base' => add_query_arg('seite', '%#%', iss_wf_import_get_archive_object_browser_url([], true, $state, $base_url)),
        'format' => '',
        'current' => max(1, (int) $state['page']),
        'total' => max(1, (int) $query->max_num_pages),
        'type' => 'list',
        'prev_text' => __('Zurück', 'iss-wf-import'),
        'next_text' => __('Weiter', 'iss-wf-import'),
    ]);

    ob_start();
    ?>
    <section <?php echo $wrapper; ?>>
        <div class="iss-container">
            <div class="iss-archive-browser__hero">
                <div class="iss-heading iss-archive-browser__heading">
                    <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($browser_kicker); ?></p>
                    <h1 class="iss-heading__title"><?php echo esc_html($browser_title); ?></h1>
                    <p class="iss-heading__text"><?php echo esc_html($browser_intro); ?></p>
                </div>

                <div class="iss-archive-browser__stats">
                    <article class="iss-archive-browser__stat">
                        <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Gesamt', 'iss-wf-import'); ?></p>
                        <h2><?php echo esc_html(number_format_i18n($stats['total_objects'])); ?></h2>
                        <p><?php esc_html_e('veröffentlichte Archivobjekte', 'iss-wf-import'); ?></p>
                    </article>
                    <article class="iss-archive-browser__stat">
                        <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Digital nachgewiesen', 'iss-wf-import'); ?></p>
                        <h2><?php echo esc_html(number_format_i18n((int) ($stats['source_counts']['museum-digital'] ?? 0))); ?></h2>
                        <p><?php esc_html_e('Objekte mit externem Plattformnachweis', 'iss-wf-import'); ?></p>
                    </article>
                    <article class="iss-archive-browser__stat">
                        <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Typisiert', 'iss-wf-import'); ?></p>
                        <h2><?php echo esc_html(number_format_i18n((int) $stats['classified_objects'])); ?></h2>
                        <p><?php esc_html_e('Objekte mit neuer Familienzuordnung', 'iss-wf-import'); ?></p>
                    </article>
                </div>
            </div>

            <div class="iss-archive-browser__quick">
                <div class="iss-heading iss-archive-browser__section-head">
                    <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($quick_kicker); ?></p>
                    <h2 class="iss-heading__title"><?php echo esc_html($quick_title); ?></h2>
                </div>
                <div class="iss-archive-entry-grid iss-archive-browser__quick-grid">
                    <?php if ($show_source_cards) : ?>
                        <?php foreach (array_slice($source_options, 0, 2) as $term) : ?>
                            <a class="iss-archive-entry iss-archive-entry--sammlung" href="<?php echo esc_url(iss_wf_import_get_archive_object_browser_url([
                                'source' => $term->slug,
                            'field' => '',
                            'family' => '',
                            'context' => '',
                            'search' => '',
                            'page' => 1,
                            ], true, $state, $base_url)); ?>">
                                <p class="iss-archive-entry__eyebrow"><?php esc_html_e('Nachweis', 'iss-wf-import'); ?></p>
                                <h3 class="iss-archive-entry__title"><?php echo esc_html(iss_wf_import_get_public_source_label($term->slug, $term->name)); ?></h3>
                                <p class="iss-archive-entry__text"><?php esc_html_e('Archivobjekte mit diesem digitalen Nachweis direkt im lokalen Bestand durchsuchen.', 'iss-wf-import'); ?></p>
                                <div class="iss-archive-entry__meta"><span><?php echo esc_html(sprintf(_n('%d Objekt', '%d Objekte', (int) $term->count, 'iss-wf-import'), (int) $term->count)); ?></span></div>
                            </a>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php foreach ($quick_families as $term) : ?>
                        <a class="iss-archive-entry iss-archive-entry--mediathek" href="<?php echo esc_url(iss_wf_import_get_archive_object_browser_url([
                            'source' => $lock_source ? $state['source'] : '',
                            'field' => '',
                            'family' => $term->slug,
                            'context' => '',
                            'search' => '',
                            'page' => 1,
                        ], true, $state, $base_url)); ?>">
                            <p class="iss-archive-entry__eyebrow"><?php esc_html_e('Objektfamilie', 'iss-wf-import'); ?></p>
                            <h3 class="iss-archive-entry__title"><?php echo esc_html($term->name); ?></h3>
                            <p class="iss-archive-entry__text"><?php esc_html_e('Bereits editorisch typisierte Objekte dieser Familie direkt öffnen.', 'iss-wf-import'); ?></p>
                            <div class="iss-archive-entry__meta"><span><?php echo esc_html(sprintf(_n('%d Objekt', '%d Objekte', (int) $term->count, 'iss-wf-import'), (int) $term->count)); ?></span></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="iss-archive-browser__layout">
                <aside class="iss-archive-browser__sidebar">
                    <div class="iss-archive-browser__filters">
                        <div class="iss-heading iss-archive-browser__section-head">
                            <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Filter', 'iss-wf-import'); ?></p>
                            <h2 class="iss-heading__title"><?php esc_html_e('Archiv durchsuchen', 'iss-wf-import'); ?></h2>
                        </div>
                        <form class="iss-archive-browser__form" method="get" action="<?php echo esc_url($base_url); ?>">
                            <label class="iss-archive-browser__field">
                                <span><?php esc_html_e('Suche', 'iss-wf-import'); ?></span>
                                <input class="iss-archive-browser__input" type="search" name="suche" value="<?php echo esc_attr($state['search']); ?>" />
                            </label>

                            <?php if ($lock_source) : ?>
                                <input type="hidden" name="quelle" value="<?php echo esc_attr($state['source']); ?>" />
                            <?php else : ?>
                                <label class="iss-archive-browser__field">
                                    <span><?php esc_html_e('Digitaler Nachweis', 'iss-wf-import'); ?></span>
                                    <select class="iss-archive-browser__select" name="quelle">
                                        <option value=""><?php esc_html_e('Alle Nachweise', 'iss-wf-import'); ?></option>
                                        <?php foreach ($source_options as $term) : ?>
                                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($state['source'], $term->slug); ?>><?php echo esc_html(iss_wf_import_get_public_source_label($term->slug, $term->name) . ' (' . $term->count . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <?php if ($lock_field) : ?>
                                <input type="hidden" name="feld" value="<?php echo esc_attr($state['field']); ?>" />
                            <?php else : ?>
                                <label class="iss-archive-browser__field">
                                    <span><?php esc_html_e('Themenfeld', 'iss-wf-import'); ?></span>
                                    <select class="iss-archive-browser__select" name="feld">
                                        <option value=""><?php esc_html_e('Alle Themenfelder', 'iss-wf-import'); ?></option>
                                        <?php foreach ($field_options as $term) : ?>
                                            <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($state['field'], $term->slug); ?>><?php echo esc_html($term->name . ' (' . $term->count . ')'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            <?php endif; ?>

                            <label class="iss-archive-browser__field">
                                <span><?php esc_html_e('Objektfamilie', 'iss-wf-import'); ?></span>
                                <select class="iss-archive-browser__select" name="familie">
                                    <option value=""><?php esc_html_e('Alle Objektfamilien', 'iss-wf-import'); ?></option>
                                    <?php foreach ($family_options as $term) : ?>
                                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($state['family'], $term->slug); ?>><?php echo esc_html($term->name . ' (' . $term->count . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <label class="iss-archive-browser__field">
                                <span><?php esc_html_e('Kontext', 'iss-wf-import'); ?></span>
                                <select class="iss-archive-browser__select" name="kontext">
                                    <option value=""><?php esc_html_e('Alle Kontexte', 'iss-wf-import'); ?></option>
                                    <?php foreach ($context_options as $term) : ?>
                                        <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($state['context'], $term->slug); ?>><?php echo esc_html($term->name . ' (' . $term->count . ')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>

                            <div class="iss-archive-browser__actions">
                                <button class="wp-element-button" type="submit"><?php esc_html_e('Filtern', 'iss-wf-import'); ?></button>
                                <a class="iss-action-link" href="<?php echo esc_url(iss_wf_import_get_archive_object_browser_url([
                                    'source' => $lock_source ? $state['source'] : '',
                                    'field' => $lock_field ? $state['field'] : '',
                                    'family' => '',
                                    'context' => '',
                                    'search' => '',
                                    'page' => 1,
                                ], true, $state, $base_url)); ?>"><?php esc_html_e('Filter zurücksetzen', 'iss-wf-import'); ?></a>
                            </div>
                        </form>

                        <p class="iss-archive-browser__note"><?php esc_html_e('Hinweis: Die editorische Typisierung läuft schrittweise. Noch nicht alle Bestände haben bereits Themenfeld- oder Familienzuordnungen.', 'iss-wf-import'); ?></p>
                    </div>
                </aside>

                <div class="iss-archive-browser__results">
                    <div class="iss-archive-browser__results-head">
                        <div>
                            <p class="iss-kicker iss-kicker--compact"><?php esc_html_e('Ergebnisse', 'iss-wf-import'); ?></p>
                            <h2 class="iss-heading__title"><?php echo esc_html($results_label); ?></h2>
                        </div>
                        <?php if ($active_filters) : ?>
                            <div class="iss-archive-browser__active">
                                <?php foreach ($active_filters as $term) : ?>
                                    <span><?php echo esc_html($term->name); ?></span>
                                <?php endforeach; ?>
                                <?php if ($state['search'] !== '') : ?>
                                    <span><?php echo esc_html($state['search']); ?></span>
                                <?php endif; ?>
                            </div>
                        <?php elseif ($state['search'] !== '') : ?>
                            <div class="iss-archive-browser__active"><span><?php echo esc_html($state['search']); ?></span></div>
                        <?php endif; ?>
                    </div>

                    <?php if ($cards) : ?>
                        <div class="iss-card-grid iss-archive-browser__grid">
                            <?php echo implode('', $cards); ?>
                        </div>
                        <?php if ($pagination) : ?>
                            <nav class="iss-archive-browser__pagination" aria-label="<?php esc_attr_e('Archivobjekt Seiten', 'iss-wf-import'); ?>">
                                <?php echo $pagination; ?>
                            </nav>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="iss-archive-browser__empty">
                            <p><?php esc_html_e('Für diese Kombination wurden noch keine veröffentlichten Archivobjekte gefunden.', 'iss-wf-import'); ?></p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
    <?php

    wp_reset_postdata();

    return trim((string) ob_get_clean());
}
