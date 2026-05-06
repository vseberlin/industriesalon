<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_register_blocks(): void
{
    if (!function_exists('register_block_type')) {
        return;
    }

    register_block_type('iss/related-content', [
        'api_version' => 3,
        'title' => __('Related Content', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'share-alt2',
        'description' => __('Shows related cards inside a standalone section with heading and container.', 'iss-relations'),
        'editor_script' => 'iss-relations-related-blocks',
        'attributes' => [
            'title' => [
                'type' => 'string',
                'default' => '',
            ],
            'kicker' => [
                'type' => 'string',
                'default' => '',
            ],
            'postType' => [
                'type' => 'string',
                'default' => 'post',
            ],
            'perPage' => [
                'type' => 'number',
                'default' => 3,
            ],
            'source' => [
                'type' => 'string',
                'default' => 'current',
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
        ],
        'supports' => [
            'html' => false,
        ],
        'render_callback' => 'iss_relations_render_related_content_block',
    ]);

    register_block_type('iss/related-cards', [
        'api_version' => 3,
        'title' => __('Related Cards', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'screenoptions',
        'description' => __('Shows related cards without section or heading wrappers.', 'iss-relations'),
        'editor_script' => 'iss-relations-related-blocks',
        'attributes' => [
            'postType' => [
                'type' => 'string',
                'default' => 'post',
            ],
            'perPage' => [
                'type' => 'number',
                'default' => 3,
            ],
            'source' => [
                'type' => 'string',
                'default' => 'current',
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
        ],
        'supports' => [
            'html' => false,
        ],
        'render_callback' => 'iss_relations_render_related_cards_block',
    ]);
}
add_action('init', 'iss_relations_register_blocks', 20);

function iss_relations_register_block_editor_script(): void
{
    $script_path = ISS_RELATIONS_PATH . 'blocks/related-content/index.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_register_script(
        'iss-relations-related-blocks',
        ISS_RELATIONS_URL . 'blocks/related-content/index.js',
        [
            'wp-block-editor',
            'wp-blocks',
            'wp-components',
            'wp-core-data',
            'wp-data',
            'wp-element',
        ],
        (string) filemtime($script_path),
        true
    );
}
add_action('init', 'iss_relations_register_block_editor_script', 19);

function iss_relations_enqueue_block_editor_script(): void
{
    if (!wp_script_is('iss-relations-related-blocks', 'registered')) {
        return;
    }

    wp_enqueue_script('iss-relations-related-blocks');
    wp_add_inline_script(
        'iss-relations-related-blocks',
        'window.issRelationsSettings = ' . wp_json_encode([
            'placePostType' => iss_relations_get_place_post_type(),
            'taxonomy' => ISS_RELATIONS_TAXONOMY,
            'supportedPostTypes' => iss_relations_get_supported_post_types(),
        ]) . ';',
        'before'
    );
}
add_action('enqueue_block_editor_assets', 'iss_relations_enqueue_block_editor_script');

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

function iss_relations_build_place_item(int $place_id, array $overrides = []): ?array
{
    if (!iss_relations_is_usable_place($place_id)) {
        return null;
    }

    $place = get_post($place_id);
    if (!$place instanceof WP_Post) {
        return null;
    }

    return array_merge([
        'place_id' => $place_id,
        'role' => 'related',
        'weight' => 0,
        'label' => '',
        'post' => $place,
        'title' => get_the_title($place),
        'permalink' => (string) get_permalink($place),
    ], $overrides);
}

function iss_relations_build_place_items_from_ids(array $place_ids): array
{
    $items = [];

    foreach ($place_ids as $index => $place_id) {
        $item = iss_relations_build_place_item((int) $place_id, [
            'weight' => (int) $index,
        ]);

        if ($item) {
            $items[] = $item;
        }
    }

    return $items;
}

function iss_relations_resolve_block_place_items(array $attributes, int $current_post_id): array
{
    $source = sanitize_key((string) ($attributes['source'] ?? 'current'));

    if ($source === 'manual') {
        return iss_relations_build_place_items_from_ids(
            iss_relations_parse_place_ids($attributes['placeIds'] ?? '')
        );
    }

    if ($source === 'route') {
        if ($current_post_id > 0 && function_exists('iss_relations_get_ordered_route_items')) {
            return iss_relations_get_ordered_route_items($current_post_id);
        }

        return [];
    }

    if (
        $current_post_id > 0
        && get_post_type($current_post_id) === iss_relations_get_place_post_type()
        && iss_relations_is_usable_place($current_post_id)
    ) {
        return iss_relations_build_place_items_from_ids([$current_post_id]);
    }

    return $current_post_id > 0 ? iss_relations_get_related_place_items($current_post_id) : [];
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

function iss_relations_resolve_block_posts(array $attributes, int $current_post_id, string $post_type, int $per_page): array
{
    $place_items = iss_relations_resolve_block_place_items($attributes, $current_post_id);
    if (!$place_items) {
        return [];
    }

    if ($post_type === iss_relations_get_place_post_type()) {
        $posts = [];

        foreach ($place_items as $item) {
            $place = $item['post'] ?? null;
            if ($place instanceof WP_Post) {
                $posts[] = $place;
            }
        }

        return array_slice($posts, 0, $per_page);
    }

    $place_ids = array_values(array_filter(array_map(static function (array $item): int {
        return (int) ($item['place_id'] ?? 0);
    }, $place_items)));

    if (!$place_ids) {
        return [];
    }

    return iss_relations_query_related_posts(
        $place_ids,
        $post_type,
        $per_page,
        ($current_post_id > 0 && get_post_type($current_post_id) === $post_type) ? $current_post_id : 0
    );
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
    $card_class = 'iss-card iss-card--flat iss-related-feed__card iss-related-feed__card--' . sanitize_html_class($post_type);

    if (!has_post_thumbnail($post)) {
        $card_class .= ' iss-related-feed__card--no-media';
    }

    ob_start();
    ?>
    <article class="<?php echo esc_attr($card_class); ?>">
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

function iss_relations_collect_related_cards(array $attributes = [], $block = null): array
{
    $attributes = is_array($attributes) ? $attributes : [];
    $current_post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
    $post_type = sanitize_key((string) ($attributes['postType'] ?? 'post'));
    $per_page = max(1, min(12, absint($attributes['perPage'] ?? 3)));

    if (!post_type_exists($post_type)) {
        return [];
    }

    $related_posts = iss_relations_resolve_block_posts($attributes, $current_post_id, $post_type, $per_page);
    if (!$related_posts) {
        return [];
    }

    $defaults = iss_relations_get_related_content_defaults($post_type);
    $cards = [];

    foreach ($related_posts as $related_post) {
        if (!$related_post instanceof WP_Post) {
            continue;
        }

        $cards[] = iss_relations_render_related_content_card($related_post, $defaults);
    }

    if (!$cards) {
        return [];
    }

    return [
        'post_type' => $post_type,
        'cards' => $cards,
        'defaults' => $defaults,
    ];
}

function iss_relations_render_cards_grid(array $cards, string $post_type): string
{
    $out = '<div class="iss-related-feed__grid iss-related-feed__grid--' . esc_attr(sanitize_html_class($post_type)) . '">';
    $out .= implode('', $cards);
    $out .= '</div>';

    return $out;
}

function iss_relations_render_related_cards_block($attributes = [], $content = '', $block = null): string
{
    $data = iss_relations_collect_related_cards($attributes, $block);
    if (!$data) {
        return '';
    }

    $post_type = (string) $data['post_type'];
    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'iss-related-cards iss-related-cards--' . sanitize_html_class($post_type),
        ])
        : 'class="' . esc_attr('iss-related-cards iss-related-cards--' . sanitize_html_class($post_type)) . '"';

    $out = '<div ' . $wrapper . '>';
    $out .= iss_relations_render_cards_grid($data['cards'], $post_type);
    $out .= '</div>';

    return $out;
}

function iss_relations_render_related_content_block($attributes = [], $content = '', $block = null): string
{
    $data = iss_relations_collect_related_cards($attributes, $block);
    if (!$data) {
        return '';
    }

    $attributes = is_array($attributes) ? $attributes : [];
    $post_type = (string) $data['post_type'];
    $defaults = is_array($data['defaults']) ? $data['defaults'] : [];
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? ($defaults['kicker'] ?? ''))));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? ($defaults['title'] ?? ''))));

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-related-feed iss-related-feed--' . sanitize_html_class($post_type),
        ])
        : 'class="' . esc_attr('section section--plain iss-related-feed iss-related-feed--' . sanitize_html_class($post_type)) . '"';

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
    $out .= iss_relations_render_cards_grid($data['cards'], $post_type);
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}
