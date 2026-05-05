<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_register_blocks(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    $related_dir = ISS_RELATIONS_PATH . 'blocks/related-content';
    if (file_exists($related_dir . '/block.json')) {
        register_block_type($related_dir, [
            'render_callback' => 'iss_relations_render_related_content_block',
        ]);
    }
}
add_action('init', 'iss_relations_register_blocks', 20);

function iss_relations_get_related_content_defaults(string $post_type): array
{
    if ($post_type === iss_relations_get_place_post_type()) {
        return [
            'kicker' => __('Ort', 'iss-relations'),
            'title' => __('Verknüpfte Orte', 'iss-relations'),
            'link_text' => __('Zum Ort', 'iss-relations'),
        ];
    }

    switch ($post_type) {
        case 'fuehrung':
            return [
                'kicker' => __('Vor Ort', 'iss-relations'),
                'title' => __('Führungen zum Ort', 'iss-relations'),
                'link_text' => __('Mehr', 'iss-relations'),
            ];
        case 'veranstaltung':
            return [
                'kicker' => __('Programm', 'iss-relations'),
                'title' => __('Veranstaltungen mit Ortsbezug', 'iss-relations'),
                'link_text' => __('Mehr', 'iss-relations'),
            ];
        case 'post':
            return [
                'kicker' => __('Archiv', 'iss-relations'),
                'title' => __('Beiträge und Hintergründe', 'iss-relations'),
                'link_text' => __('Mehr', 'iss-relations'),
            ];
        default:
            return [
                'kicker' => __('Weiter entdecken', 'iss-relations'),
                'title' => __('Verwandte Inhalte', 'iss-relations'),
                'link_text' => __('Mehr', 'iss-relations'),
            ];
    }
}

function iss_relations_resolve_block_place_ids(array $attributes, int $current_post_id): array
{
    $source = sanitize_key((string) ($attributes['source'] ?? 'current'));
    if ($source === 'manual') {
        return iss_relations_parse_place_ids($attributes['placeIds'] ?? '');
    }

    return iss_relations_get_context_place_ids($current_post_id);
}

function iss_relations_query_related_posts(array $place_ids, string $post_type, int $per_page, int $exclude_post_id = 0): array
{
    $term_ids = iss_relations_get_place_term_ids($place_ids);
    if (!$term_ids || !post_type_exists($post_type)) {
        return [];
    }

    $args = [
        'post_type' => $post_type,
        'post_status' => 'publish',
        'posts_per_page' => max(1, min(12, $per_page)),
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
        'tax_query' => [
            [
                'taxonomy' => ISS_RELATIONS_TAXONOMY,
                'field' => 'term_id',
                'terms' => $term_ids,
                'operator' => 'IN',
            ],
        ],
    ];

    if ($exclude_post_id > 0) {
        $args['post__not_in'] = [$exclude_post_id];
    }

    return get_posts($args);
}

function iss_relations_get_card_meta_line(WP_Post $post): string
{
    if ($post->post_type === 'veranstaltung' && function_exists('iss_content_model_get_meta_rows_for_post')) {
        $rows = iss_content_model_get_meta_rows_for_post((int) $post->ID);
        $parts = [];

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['value'])) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $value = trim(wp_strip_all_tags((string) ($row['value'] ?? '')));
            if ($value === '') {
                continue;
            }

            $parts[] = $label !== '' ? ($label . ': ' . $value) : $value;
            if (count($parts) >= 2) {
                break;
            }
        }

        return implode(' · ', $parts);
    }

    return '';
}

function iss_relations_get_card_excerpt(WP_Post $post, int $max_words = 22): string
{
    $excerpt = trim((string) get_the_excerpt($post));
    if ($excerpt !== '') {
        return $excerpt;
    }

    return wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post->ID)), $max_words, '…');
}

function iss_relations_render_generic_card(WP_Post $post, array $copy): string
{
    $post_type = (string) $post->post_type;
    $permalink = (string) get_permalink($post);
    $title = get_the_title($post);
    $meta_line = iss_relations_get_card_meta_line($post);
    $excerpt = iss_relations_get_card_excerpt($post);
    $kicker = trim((string) ($copy['kicker'] ?? ''));
    $link_text = trim((string) ($copy['link_text'] ?? __('Mehr', 'iss-relations')));

    ob_start();
    ?>
    <article class="iss-card iss-card--flat iss-related-feed__card iss-related-feed__card--<?php echo esc_attr(sanitize_html_class($post_type)); ?>">
        <?php if (has_post_thumbnail($post)) : ?>
            <a class="iss-card__media" href="<?php echo esc_url($permalink); ?>">
                <?php echo get_the_post_thumbnail($post, 'large'); ?>
            </a>
        <?php endif; ?>
        <div class="iss-card__body">
            <?php if ($kicker !== '') : ?>
                <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($kicker); ?></p>
            <?php endif; ?>
            <h3 class="iss-card__title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h3>
            <?php if ($meta_line !== '') : ?>
                <p class="iss-related-feed__meta"><?php echo esc_html($meta_line); ?></p>
            <?php endif; ?>
            <?php if ($excerpt !== '') : ?>
                <p class="iss-card__text"><?php echo esc_html($excerpt); ?></p>
            <?php endif; ?>
            <div class="iss-card__footer">
                <a class="iss-card__link" href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($link_text); ?></a>
            </div>
        </div>
    </article>
    <?php

    return trim((string) ob_get_clean());
}

function iss_relations_render_related_content_card(WP_Post $post, array $defaults): string
{
    if ($post->post_type === 'fuehrung' && function_exists('iss_fuehrung_render_archive_card')) {
        return iss_fuehrung_render_archive_card((int) $post->ID);
    }

    $card_copy = [
        'kicker' => $post->post_type === 'veranstaltung'
            ? __('Veranstaltung', 'iss-relations')
            : ($post->post_type === 'post' ? __('Beitrag', 'iss-relations') : ($defaults['kicker'] ?? '')),
        'link_text' => $defaults['link_text'] ?? __('Mehr', 'iss-relations'),
    ];

    return iss_relations_render_generic_card($post, $card_copy);
}

function iss_relations_render_related_content_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $current_post_id = (int) get_the_ID();
    $post_type = sanitize_key((string) ($attributes['postType'] ?? 'post'));
    $per_page = max(1, min(12, absint($attributes['perPage'] ?? 3)));

    if (!post_type_exists($post_type)) {
        return '';
    }

    $place_ids = iss_relations_resolve_block_place_ids($attributes, $current_post_id);
    if (!$place_ids) {
        return '';
    }

    $related_posts = iss_relations_query_related_posts(
        $place_ids,
        $post_type,
        $per_page,
        ($current_post_id > 0 && get_post_type($current_post_id) === $post_type) ? $current_post_id : 0
    );

    if (!$related_posts) {
        return '';
    }

    $defaults = iss_relations_get_related_content_defaults($post_type);
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? $defaults['kicker'])));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? $defaults['title'])));

    $cards = [];
    foreach ($related_posts as $related_post) {
        if (!$related_post instanceof WP_Post) {
            continue;
        }

        $cards[] = iss_relations_render_related_content_card($related_post, $defaults);
    }

    if (!$cards) {
        return '';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-related-feed iss-related-feed--' . sanitize_html_class($post_type),
        ])
        : 'class="section section--plain iss-related-feed iss-related-feed--' . esc_attr(sanitize_html_class($post_type)) . '"';

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';
    $out .= '<div class="iss-heading iss-related-feed__intro">';
    if ($kicker !== '') {
        $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
    }
    if ($title !== '') {
        $out .= '<h2 class="iss-heading__title">' . esc_html($title) . '</h2>';
    }
    $out .= '</div>';
    $out .= '<div class="iss-related-feed__grid iss-related-feed__grid--' . esc_attr(sanitize_html_class($post_type)) . '">';
    $out .= implode('', $cards);
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}
