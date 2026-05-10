<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_get_price_cents($post_id) {
    return (int) iss_publications_get_meta($post_id, '_iss_publication_price_cents', 0);
}

function iss_publications_get_layout($post_id) {
    $layout = sanitize_key((string) iss_publications_get_meta($post_id, '_iss_publication_layout', 'standard'));
    return in_array($layout, ['standard', 'longread', 'timeline'], true) ? $layout : 'standard';
}

function iss_publications_is_longread($post_id) {
    return in_array(iss_publications_get_layout($post_id), ['longread', 'timeline'], true);
}

function iss_publications_is_timeline($post_id) {
    return iss_publications_get_layout($post_id) === 'timeline';
}

function iss_publications_sale_enabled($post_id) {
    return !empty(get_post_meta($post_id, '_iss_publication_sale_enabled', true)) && iss_publications_get_price_cents($post_id) > 0;
}

function iss_publications_format_price($cents) {
    $cents = (int) $cents;
    if ($cents <= 0) {
        return '';
    }

    return number_format_i18n($cents / 100, 2) . ' €';
}

function iss_publications_get_type_label($post_id) {
    $terms = get_the_terms($post_id, 'publication_type');
    if (!$terms || is_wp_error($terms)) {
        return '';
    }

    $term = array_shift($terms);
    return $term instanceof WP_Term ? $term->name : '';
}

function iss_publications_get_shared_topic_taxonomy(): string
{
    if (defined('ISS_CONTENT_MODEL_TOPIC_TAXONOMY')) {
        return (string) ISS_CONTENT_MODEL_TOPIC_TAXONOMY;
    }

    return 'iss_topic';
}

function iss_publications_get_shared_topic_names($post_id): array
{
    $taxonomy = iss_publications_get_shared_topic_taxonomy();
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $terms = get_the_terms((int) $post_id, $taxonomy);
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

function iss_publications_get_year_label($post_id) {
    return trim((string) iss_publications_get_meta($post_id, '_iss_publication_year', ''));
}

function iss_publications_get_card_kicker($post_id) {
    $items = [
        iss_publications_is_timeline($post_id)
            ? __('Zeitleiste', 'iss-publications')
            : (iss_publications_is_longread($post_id) ? __('Longread', 'iss-publications') : ''),
        iss_publications_get_type_label($post_id),
        iss_publications_get_year_label($post_id),
    ];

    return implode(' / ', array_filter($items));
}

function iss_publications_get_card_meta_items($post_id) {
    $items = [];

    $pages = (int) iss_publications_get_meta($post_id, '_iss_publication_pages', 0);
    if ($pages > 0) {
        $items[] = sprintf(__('%d Seiten', 'iss-publications'), $pages);
    }

    return $items;
}

function iss_publications_get_summary_meta($post_id) {
    $items = [
        __('Untertitel', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_subtitle', ''),
        __('Autor:in', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_author', ''),
        __('Herausgeber:in', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_editor', ''),
        __('Jahr', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_year', ''),
        __('Seiten', 'iss-publications') => (int) iss_publications_get_meta($post_id, '_iss_publication_pages', 0),
        __('Format', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_format', ''),
        __('Sprache', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_language', ''),
        __('ISBN', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_isbn', ''),
        __('Verlag', 'iss-publications') => iss_publications_get_meta($post_id, '_iss_publication_publisher', ''),
    ];

    $rows = [];
    foreach ($items as $label => $value) {
        if (is_int($value)) {
            if ($value > 0) {
                $rows[$label] = sprintf(__('%d Seiten', 'iss-publications'), $value);
            }
            continue;
        }

        $value = trim((string) $value);
        if ($value !== '') {
            $rows[$label] = $value;
        }
    }

    if (iss_publications_sale_enabled($post_id)) {
        $price = iss_publications_format_price(iss_publications_get_price_cents($post_id));
        if ($price !== '') {
            $rows[__('Preis', 'iss-publications')] = $price;
        }
    }

    $topic_names = iss_publications_get_shared_topic_names($post_id);
    if (!empty($topic_names)) {
        $rows[__('Thema', 'iss-publications')] = implode(', ', $topic_names);
    }

    return $rows;
}

function iss_publications_get_archive_tax_query() {
    $supported_taxonomies = ['publication_type', 'publication_topic'];
    $shared_topic_taxonomy = iss_publications_get_shared_topic_taxonomy();
    if (taxonomy_exists($shared_topic_taxonomy)) {
        $supported_taxonomies[] = $shared_topic_taxonomy;
    }

    if (!is_tax($supported_taxonomies)) {
        return [];
    }

    $term = get_queried_object();
    if (!$term instanceof WP_Term) {
        return [];
    }

    return [[
        'taxonomy' => $term->taxonomy,
        'field'    => 'term_id',
        'terms'    => [$term->term_id],
    ]];
}

function iss_publications_get_featured_publication_id() {
    $args = [
        'post_type'      => ISS_PUBLICATIONS_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
        'meta_query'     => [[
            'key'     => '_iss_publication_featured',
            'value'   => '1',
            'compare' => '=',
        ]],
        'tax_query'      => iss_publications_get_archive_tax_query(),
        'fields'         => 'ids',
    ];

    $ids = get_posts($args);
    if (!empty($ids)) {
        return (int) $ids[0];
    }

    unset($args['meta_query']);
    $ids = get_posts($args);
    return !empty($ids) ? (int) $ids[0] : 0;
}

function iss_publications_render_archive_card($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $permalink = get_permalink($post_id);
    $kicker = iss_publications_get_card_kicker($post_id);
    $meta_items = iss_publications_get_card_meta_items($post_id);

    ob_start();
    echo '<article class="iss-card iss-card--flat iss-card--info iss-publication-card">';
    if (has_post_thumbnail($post_id)) {
        echo '<a class="iss-card__media iss-publication-card__cover" href="' . esc_url($permalink) . '">';
        echo get_the_post_thumbnail($post_id, 'large');
        echo '</a>';
    }

    echo '<div class="iss-card__body">';
    if ($kicker !== '') {
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
    }
    echo '<h2 class="iss-card__title"><a href="' . esc_url($permalink) . '">' . esc_html(get_the_title($post_id)) . '</a></h2>';

    $excerpt = get_the_excerpt($post_id);
    if ($excerpt !== '') {
        echo '<p class="iss-card__text">' . esc_html($excerpt) . '</p>';
    }

    if (!empty($meta_items)) {
        echo '<div class="iss-card__meta">';
        foreach ($meta_items as $item) {
            echo '<span>' . esc_html($item) . '</span>';
        }
        echo '</div>';
    }

    $card_label = iss_publications_sale_enabled($post_id)
        ? __('Details / bestellen', 'iss-publications')
        : __('Mehr lesen', 'iss-publications');

    echo '<div class="iss-card__footer"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html($card_label) . '</a></div>';
    echo '</div>';
    echo '</article>';
    return (string) ob_get_clean();
}

function iss_publications_render_featured_publication($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return '';
    }

    $permalink = get_permalink($post_id);
    $excerpt = get_the_excerpt($post_id);

    ob_start();
    echo '<article class="iss-publications-feature">';
    echo '<div class="iss-publications-feature__cover">';
    echo '<a class="iss-media-card iss-media-card--contain iss-media-card--soft iss-media-card--framed" href="' . esc_url($permalink) . '">';
    if (has_post_thumbnail($post_id)) {
        echo get_the_post_thumbnail($post_id, 'large');
    }
    echo '</a>';
    echo '</div>';

    echo '<div class="iss-publications-feature__content">';
    echo '<div class="iss-heading iss-heading--uncaged">';
    echo '<p class="iss-kicker">' . esc_html__('Ausgewählte Publikation', 'iss-publications') . '</p>';
    echo '<h2 class="iss-heading__title">' . esc_html(get_the_title($post_id)) . '</h2>';
    if ($excerpt !== '') {
        echo '<p class="iss-heading__text">' . esc_html($excerpt) . '</p>';
    }
    echo '</div>';

    $summary_meta = iss_publications_get_summary_meta($post_id);
    if (!empty($summary_meta)) {
        echo '<ul class="iss-publication-meta">';
        foreach ($summary_meta as $label => $value) {
            echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
        }
        echo '</ul>';
    }

    echo '<p class="iss-publications-feature__action"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html__('Details ansehen', 'iss-publications') . '</a></p>';
    echo '</div>';
    echo '</article>';
    return (string) ob_get_clean();
}

function iss_publications_render_order_panel($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0 || !iss_publications_sale_enabled($post_id)) {
        return '';
    }

    $cta_label = trim((string) iss_publications_get_meta($post_id, '_iss_publication_cta_label', ''));
    if ($cta_label === '') {
        $cta_label = __('Publikation bestellen', 'iss-publications');
    }

    $description = trim((string) iss_publications_get_meta($post_id, '_iss_publication_gateway_description', ''));
    if ($description === '') {
        $description = __('Diese Publikation kann online bezahlt werden. Mit dem Kauf unterstützen Sie die Arbeit des Industriesalon Schöneweide.', 'iss-publications');
    }

    $button_html = apply_filters('iss_publications_order_button_html', '', $post_id, [
        'entity_type' => 'publication',
        'entity_id'   => $post_id,
        'title'       => get_the_title($post_id),
        'amount'      => iss_publications_get_price_cents($post_id),
        'label'       => $cta_label,
    ]);

    ob_start();
    echo '<aside class="iss-publication-order-panel">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Bestellung', 'iss-publications') . '</p>';
    echo '<h2 class="iss-info-panel__title">' . esc_html__('Publikation bestellen', 'iss-publications') . '</h2>';
    echo '<p class="iss-publication-order-panel__price">' . esc_html(iss_publications_format_price(iss_publications_get_price_cents($post_id))) . '</p>';
    echo '<p class="iss-publication-order-panel__text">' . esc_html($description) . '</p>';

    if (is_string($button_html) && trim($button_html) !== '') {
        echo wp_kses_post($button_html);
    } else {
        echo '<p class="iss-publication-order-panel__note">' . esc_html__('Die Bestellfunktion wird im nächsten Schritt angebunden.', 'iss-publications') . '</p>';
    }

    echo '</aside>';
    return (string) ob_get_clean();
}

function iss_publications_get_source_ausstellung_id($post_id): int
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 0;
    }

    $source_id = (int) iss_publications_get_meta($post_id, '_iss_publication_source_ausstellung_id', 0);
    if ($source_id > 0) {
        return $source_id;
    }

    if (!post_type_exists('ausstellung')) {
        return 0;
    }

    $posts = get_posts([
        'post_type' => 'ausstellung',
        'post_status' => ['publish', 'private', 'future', 'draft', 'pending'],
        'posts_per_page' => 1,
        'meta_key' => 'iss_companion_publication_id',
        'meta_value' => $post_id,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);

    return !empty($posts) ? (int) $posts[0] : 0;
}

function iss_publications_render_corpus_stream_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $source_ausstellung_id = iss_publications_get_source_ausstellung_id($post_id);
    if ($source_ausstellung_id <= 0 || !function_exists('iss_content_model_get_ausstellung_corpus_chapters')) {
        return '';
    }

    $chapters = iss_content_model_get_ausstellung_corpus_chapters($source_ausstellung_id);
    if (!$chapters) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publication-corpus'])
        : 'class="wp-block-iss-publication-corpus"';

    ob_start();
    echo '<div ' . $wrapper . '>';
    echo '<section class="iss-publication-corpus">';
    echo '<div class="iss-heading iss-publication-corpus__head">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Kapitelpfad', 'iss-publications') . '</p>';
    echo '<h2 class="iss-heading__title">' . esc_html__('Diese Publikation liest denselben Korpus linear.', 'iss-publications') . '</h2>';
    echo '<p class="iss-heading__text">' . esc_html__('Die Ausstellung bietet Überblick und thematische Einstiege. Hier laufen dieselben Kapitel als fortlaufender Lesepfad untereinander.', 'iss-publications') . '</p>';
    echo '</div>';

    echo '<div class="iss-publication-corpus__topline">';
    echo '<p class="iss-publication-corpus__backlink"><a class="iss-action-link" href="' . esc_url(get_permalink($source_ausstellung_id)) . '">' . esc_html__('Zur Ausstellung', 'iss-publications') . '</a></p>';
    echo '<ol class="iss-publication-corpus__nav">';
    foreach ($chapters as $index => $chapter) {
        $anchor = 'publikation-kapitel-' . ($index + 1);
        echo '<li><a href="#' . esc_attr($anchor) . '">' . esc_html(get_the_title($chapter)) . '</a></li>';
    }
    echo '</ol>';
    echo '</div>';

    echo '<div class="iss-publication-corpus__stream">';
    foreach ($chapters as $index => $chapter) {
        $anchor = 'publikation-kapitel-' . ($index + 1);
        $content_html = apply_filters('the_content', $chapter->post_content);

        echo '<article id="' . esc_attr($anchor) . '" class="iss-publication-corpus__chapter">';
        echo '<p class="iss-kicker iss-kicker--compact">' . esc_html(sprintf(__('Kapitel %02d', 'iss-publications'), $index + 1)) . '</p>';
        echo '<h3 class="iss-publication-corpus__chapter-title">' . esc_html(get_the_title($chapter)) . '</h3>';
        echo '<div class="iss-publication-corpus__chapter-content">' . $content_html . '</div>';
        echo '<p class="iss-publication-corpus__chapter-link"><a class="iss-action-link" href="' . esc_url(get_permalink($chapter)) . '">' . esc_html__('Kapitel einzeln öffnen', 'iss-publications') . '</a></p>';
        echo '</article>';
    }
    echo '</div>';
    echo '</section>';
    echo '</div>';

    return (string) ob_get_clean();
}

add_filter('body_class', function ($classes) {
    if (!is_singular(ISS_PUBLICATIONS_POST_TYPE)) {
        return $classes;
    }

    $post_id = (int) get_queried_object_id();
    if ($post_id <= 0) {
        return $classes;
    }

    $classes[] = 'iss-publication-layout-' . sanitize_html_class(iss_publications_get_layout($post_id));
    return $classes;
});

function iss_publications_get_related_posts($post_id, $limit = 3) {
    $post_id = (int) $post_id;
    $types = wp_get_post_terms($post_id, 'publication_type', ['fields' => 'ids']);

    $args = [
        'post_type'      => ISS_PUBLICATIONS_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => max(1, (int) $limit),
        'post__not_in'   => [$post_id],
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
    ];

    if (!empty($types) && !is_wp_error($types)) {
        $args['tax_query'] = [[
            'taxonomy' => 'publication_type',
            'field'    => 'term_id',
            'terms'    => array_map('absint', $types),
        ]];
    }

    return get_posts($args);
}

function iss_publications_get_collection_kind($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return 'other';
    }

    $type_terms = get_the_terms($post_id, 'publication_type');
    if (!empty($type_terms) && !is_wp_error($type_terms)) {
        foreach ($type_terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }

            $slug = sanitize_title($term->slug);
            if (in_array($slug, ['buch', 'buecher', 'book', 'books'], true)) {
                return 'book';
            }

            if (in_array($slug, ['broschuere', 'broschueren', 'heft', 'hefte', 'booklet', 'booklets'], true)) {
                return 'brochure';
            }
        }
    }

    $format = sanitize_title((string) iss_publications_get_meta($post_id, '_iss_publication_format', ''));
    if ($format !== '') {
        if (str_contains($format, 'buch')) {
            return 'book';
        }

        if (str_contains($format, 'broschure') || str_contains($format, 'broschuere') || str_contains($format, 'magazin') || str_contains($format, 'heft')) {
            return 'brochure';
        }
    }

    return 'other';
}

function iss_publications_get_archive_posts($args = []) {
    $defaults = [
        'post_type'      => ISS_PUBLICATIONS_POST_TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => [
            'menu_order' => 'ASC',
            'date'       => 'DESC',
        ],
        'tax_query'      => iss_publications_get_archive_tax_query(),
    ];

    return get_posts(wp_parse_args($args, $defaults));
}

function iss_publications_append_layout_filter(array $args, string $layout): array
{
    $layout = sanitize_key($layout);
    if (!in_array($layout, ['standard', 'longread', 'timeline'], true)) {
        return $args;
    }

    $meta_query = isset($args['meta_query']) && is_array($args['meta_query']) ? $args['meta_query'] : [];

    if ($layout === 'standard') {
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key' => '_iss_publication_layout',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_iss_publication_layout',
                'value' => 'standard',
                'compare' => '=',
            ],
        ];
    } else {
        $meta_query[] = [
            'key' => '_iss_publication_layout',
            'value' => $layout,
            'compare' => '=',
        ];
    }

    $args['meta_query'] = $meta_query;

    return $args;
}

function iss_publications_partition_archive_posts($posts) {
    $groups = [
        'brochure' => [],
        'book'     => [],
        'other'    => [],
    ];

    foreach ((array) $posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $kind = iss_publications_get_collection_kind($post->ID);
        if (!isset($groups[$kind])) {
            $kind = 'other';
        }

        $groups[$kind][] = $post;
    }

    return $groups;
}

function iss_publications_block_resolve_post_id($attributes = []) {
    $attributes = is_array($attributes) ? $attributes : [];

    if (isset($attributes['postId'])) {
        $post_id = (int) $attributes['postId'];
        if ($post_id > 0) {
            return $post_id;
        }
    }

    $post_id = (int) get_the_ID();
    return $post_id > 0 ? $post_id : 0;
}

function iss_publications_render_featured_block($attributes = [], $content = '') {
    $post_id = isset($attributes['postId']) ? (int) $attributes['postId'] : 0;
    if ($post_id <= 0) {
        $post_id = iss_publications_get_featured_publication_id();
    }

    $html = iss_publications_render_featured_publication($post_id);
    if ($html === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-featured-publication'])
        : 'class="wp-block-iss-featured-publication"';

    return '<div ' . $wrapper . '>' . $html . '</div>';
}

function iss_publications_render_grid_posts($posts) {
    $posts = array_values(array_filter((array) $posts, static function ($post) {
        return $post instanceof WP_Post;
    }));

    if (empty($posts)) {
        return '';
    }

    ob_start();
    echo '<div class="iss-card-grid iss-publications-grid">';
    foreach ($posts as $post) {
        echo iss_publications_render_archive_card($post->ID);
    }
    echo '</div>';

    return (string) ob_get_clean();
}

function iss_publications_render_grid_block($attributes = [], $content = '') {
    $limit = isset($attributes['limit']) ? max(1, (int) $attributes['limit']) : 6;
    $exclude_featured = !empty($attributes['excludeFeatured']);
    $layout = isset($attributes['layout']) ? sanitize_key((string) $attributes['layout']) : '';
    $include_ids = isset($attributes['includeIds']) && is_array($attributes['includeIds']) ? array_values(array_filter(array_map('absint', $attributes['includeIds']))) : [];
    $exclude_ids = isset($attributes['excludeIds']) && is_array($attributes['excludeIds']) ? array_values(array_filter(array_map('absint', $attributes['excludeIds']))) : [];
    $args = [
        'posts_per_page' => $limit,
    ];

    if (!empty($include_ids)) {
        $args['post__in'] = $include_ids;
        $args['orderby'] = 'post__in';
        $args['posts_per_page'] = count($include_ids);
    }

    if (!empty($exclude_ids)) {
        $args['post__not_in'] = $exclude_ids;
    }

    if ($exclude_featured) {
        $featured_id = iss_publications_get_featured_publication_id();
        if ($featured_id > 0) {
            $args['post__not_in'] = array_values(array_unique(array_merge($args['post__not_in'] ?? [], [$featured_id])));
        }
    }

    if ($layout !== '') {
        $args = iss_publications_append_layout_filter($args, $layout);
    }

    $html = iss_publications_render_grid_posts(iss_publications_get_archive_posts($args));
    if ($html === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publications-grid'])
        : 'class="wp-block-iss-publications-grid"';

    return '<div ' . $wrapper . '>' . $html . '</div>';
}

function iss_publications_render_order_panel_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $panel = iss_publications_render_order_panel($post_id);
    if ($panel === '') {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publication-order-panel'])
        : 'class="wp-block-iss-publication-order-panel"';

    return '<div ' . $wrapper . '>' . $panel . '</div>';
}

function iss_publications_render_meta_block($attributes = [], $content = '') {
    $post_id = iss_publications_block_resolve_post_id($attributes);
    if ($post_id <= 0) {
        return '';
    }

    $summary_meta = iss_publications_get_summary_meta($post_id);
    if (empty($summary_meta)) {
        return '';
    }

    ob_start();
    echo '<div class="iss-publication-single__panel">';
    echo '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Bibliografie', 'iss-publications') . '</p>';
    echo '<ul class="iss-publication-meta">';
    foreach ($summary_meta as $label => $value) {
        echo '<li><strong>' . esc_html($label) . ':</strong> ' . esc_html($value) . '</li>';
    }
    echo '</ul>';
    echo '</div>';

    $classes = 'wp-block-iss-publication-meta';
    if (iss_publications_is_timeline($post_id)) {
        $classes .= ' wp-block-iss-publication-meta--timeline';
    } elseif (iss_publications_is_longread($post_id)) {
        $classes .= ' wp-block-iss-publication-meta--longread';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => $classes])
        : 'class="' . esc_attr($classes) . '"';

    return '<div ' . $wrapper . '>' . (string) ob_get_clean() . '</div>';
}

add_shortcode('iss_featured_publication', function ($atts = []) {
    $atts = shortcode_atts(['id' => 0], $atts);
    return iss_publications_render_featured_block(['postId' => (int) $atts['id']], '');
});

add_shortcode('iss_publications_grid', function ($atts = []) {
    $atts = shortcode_atts([
        'limit' => 6,
        'exclude_featured' => 'false',
    ], $atts);

    return iss_publications_render_grid_block([
        'limit' => (int) $atts['limit'],
        'excludeFeatured' => filter_var($atts['exclude_featured'], FILTER_VALIDATE_BOOLEAN),
    ], '');
});

add_shortcode('iss_publication_order_panel', function ($atts = []) {
    $atts = shortcode_atts(['id' => 0], $atts);
    return iss_publications_render_order_panel_block(['postId' => (int) $atts['id']], '');
});
