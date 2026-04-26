<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_get_price_cents($post_id) {
    return (int) iss_publications_get_meta($post_id, '_iss_publication_price_cents', 0);
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

function iss_publications_get_year_label($post_id) {
    return trim((string) iss_publications_get_meta($post_id, '_iss_publication_year', ''));
}

function iss_publications_get_card_kicker($post_id) {
    return implode(' / ', array_filter([
        iss_publications_get_type_label($post_id),
        iss_publications_get_year_label($post_id),
    ]));
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

    return $rows;
}

function iss_publications_get_archive_tax_query() {
    if (!is_tax(['publication_type', 'publication_topic'])) {
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

    echo '<div class="iss-card__footer"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html__('Details / bestellen', 'iss-publications') . '</a></div>';
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
    $args = [
        'posts_per_page' => $limit,
    ];

    if ($exclude_featured) {
        $featured_id = iss_publications_get_featured_publication_id();
        if ($featured_id > 0) {
            $args['post__not_in'] = [$featured_id];
        }
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

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes(['class' => 'wp-block-iss-publication-meta'])
        : 'class="wp-block-iss-publication-meta"';

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
