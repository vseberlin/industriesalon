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

    register_block_type('iss/related-place-map', [
        'api_version' => 3,
        'title' => __('Related Place Map', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'location-alt',
        'description' => __('Shows related places as a compact map stage with linked place summaries.', 'iss-relations'),
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
            'text' => [
                'type' => 'string',
                'default' => '',
            ],
            'perPage' => [
                'type' => 'number',
                'default' => 5,
            ],
            'source' => [
                'type' => 'string',
                'default' => 'current',
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
            'mapPreset' => [
                'type' => 'string',
                'default' => 'default',
            ],
            'panelMode' => [
                'type' => 'string',
                'default' => 'show',
            ],
            'panelPosition' => [
                'type' => 'string',
                'default' => 'right',
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_relations_render_related_place_map_block',
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
            'mapPresets' => iss_relations_get_place_map_editor_presets(),
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
        'excerpt' => iss_relations_get_card_excerpt($place, 18),
        'lat' => is_numeric(get_post_meta($place_id, 'lat', true)) ? (float) get_post_meta($place_id, 'lat', true) : null,
        'lng' => is_numeric(get_post_meta($place_id, 'lng', true)) ? (float) get_post_meta($place_id, 'lng', true) : null,
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

function iss_relations_get_place_map_defaults(): array
{
    return [
        'kicker' => __('Atlas', 'iss-relations'),
        'title' => __('Orte im Zusammenhang', 'iss-relations'),
        'link_text' => __('Zum Atlas', 'iss-relations'),
    ];
}

function iss_relations_get_place_map_presets(): array
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $fallback = [
        'default' => [
            'label' => __('Atlas Übersicht', 'iss-relations'),
            'image_url' => '',
            'markers_path' => '',
            'image_alt' => __('Übersichtskarte des Schöneweide-Atlas.', 'iss-relations'),
            'width' => 4096,
            'height' => 2389,
            'viewport' => [
                'scale_x' => 1,
                'scale_y' => 1,
                'offset_x' => 0,
                'offset_y' => 0,
            ],
        ],
    ];
    $presets = apply_filters('iss_relations_place_map_presets', $fallback);

    if (!is_array($presets)) {
        $cache = $fallback;
        return $cache;
    }

    $normalized = [];

    foreach ($presets as $preset_key => $preset_config) {
        if (!is_array($preset_config)) {
            continue;
        }

        $preset = sanitize_key(is_string($preset_key) ? $preset_key : (string) ($preset_config['id'] ?? ''));
        if ($preset === '') {
            continue;
        }

        $width = max(1, absint($preset_config['width'] ?? 2400));
        $height = max(1, absint($preset_config['height'] ?? 1313));
        $viewport = is_array($preset_config['viewport'] ?? null) ? $preset_config['viewport'] : [];
        $scale = isset($viewport['scale']) && is_numeric($viewport['scale']) ? (float) $viewport['scale'] : 1.0;
        $scale_x = isset($viewport['scale_x']) && is_numeric($viewport['scale_x']) ? (float) $viewport['scale_x'] : $scale;
        $scale_y = isset($viewport['scale_y']) && is_numeric($viewport['scale_y']) ? (float) $viewport['scale_y'] : $scale;
        $offset_x = isset($viewport['offset_x']) && is_numeric($viewport['offset_x']) ? (float) $viewport['offset_x'] : 0.0;
        $offset_y = isset($viewport['offset_y']) && is_numeric($viewport['offset_y']) ? (float) $viewport['offset_y'] : 0.0;

        $normalized[$preset] = [
            'label' => trim((string) ($preset_config['label'] ?? $preset)),
            'image_url' => isset($preset_config['image_url']) ? (string) $preset_config['image_url'] : '',
            'markers_path' => isset($preset_config['markers_path']) ? (string) $preset_config['markers_path'] : '',
            'image_alt' => isset($preset_config['image_alt']) ? (string) $preset_config['image_alt'] : __('Übersichtskarte des Schöneweide-Atlas.', 'iss-relations'),
            'width' => $width,
            'height' => $height,
            'viewport' => [
                'scale_x' => $scale_x > 0 ? $scale_x : 1.0,
                'scale_y' => $scale_y > 0 ? $scale_y : 1.0,
                'offset_x' => $offset_x,
                'offset_y' => $offset_y,
            ],
        ];
    }

    if (!$normalized) {
        $normalized = $fallback;
    }

    $cache = $normalized;

    return $cache;
}

function iss_relations_get_place_map_editor_presets(): array
{
    $options = [];

    foreach (iss_relations_get_place_map_presets() as $preset => $config) {
        $options[] = [
            'label' => (string) ($config['label'] ?? $preset),
            'value' => (string) $preset,
        ];
    }

    return $options;
}

function iss_relations_get_place_map_config(string $preset = 'default'): array
{
    $presets = iss_relations_get_place_map_presets();

    if (isset($presets[$preset])) {
        return $presets[$preset];
    }

    $first_key = array_key_first($presets);
    if ($first_key !== null && isset($presets[$first_key])) {
        return $presets[$first_key];
    }

    return [
        'label' => __('Atlas Übersicht', 'iss-relations'),
        'image_url' => '',
        'markers_path' => '',
        'image_alt' => __('Übersichtskarte des Schöneweide-Atlas.', 'iss-relations'),
        'width' => 4096,
        'height' => 2389,
        'viewport' => [
            'scale_x' => 1,
            'scale_y' => 1,
            'offset_x' => 0,
            'offset_y' => 0,
        ],
    ];
}

function iss_relations_get_place_map_coord_key(float $lat, float $lng): string
{
    return number_format($lat, 6, '.', '') . ':' . number_format($lng, 6, '.', '');
}

function iss_relations_resolve_place_map_preset(array $attributes = []): string
{
    $preset = sanitize_key((string) ($attributes['mapPreset'] ?? ''));
    $presets = iss_relations_get_place_map_presets();

    if ($preset !== '' && isset($presets[$preset])) {
        return $preset;
    }

    $first_key = array_key_first($presets);

    if ($first_key === null) {
        return 'default';
    }

    return (string) $first_key;
}

function iss_relations_normalize_place_map_panel_mode(array $attributes = []): string
{
    $value = sanitize_key((string) ($attributes['panelMode'] ?? 'show'));

    return in_array($value, ['show', 'hide'], true) ? $value : 'show';
}

function iss_relations_normalize_place_map_panel_position(array $attributes = []): string
{
    $value = sanitize_key((string) ($attributes['panelPosition'] ?? 'right'));

    return in_array($value, ['right', 'below'], true) ? $value : 'right';
}

function iss_relations_get_place_map_marker_lookup(string $markers_path): array
{
    static $cache = [];

    $markers_path = wp_normalize_path($markers_path);

    if ($markers_path === '') {
        return [];
    }

    if (array_key_exists($markers_path, $cache)) {
        return $cache[$markers_path];
    }

    if (!is_readable($markers_path)) {
        $cache[$markers_path] = [];
        return $cache[$markers_path];
    }

    $contents = file_get_contents($markers_path);
    if (!is_string($contents) || $contents === '') {
        $cache[$markers_path] = [];
        return $cache[$markers_path];
    }

    $decoded = json_decode($contents, true);
    if (!is_array($decoded)) {
        $cache[$markers_path] = [];
        return $cache[$markers_path];
    }

    $lookup = [
        'by_id' => [],
        'by_name' => [],
        'by_coords' => [],
    ];

    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }

        $place_id = isset($item['id']) ? (string) $item['id'] : '';
        $post_id = isset($item['post_id']) ? (string) $item['post_id'] : '';
        $name = sanitize_title((string) ($item['name'] ?? ''));
        $lat = isset($item['lat']) && is_numeric($item['lat']) ? (float) $item['lat'] : null;
        $lng = isset($item['lng']) && is_numeric($item['lng']) ? (float) $item['lng'] : null;
        $x = isset($item['xNorm']) && is_numeric($item['xNorm']) ? (float) $item['xNorm'] : null;
        $y = isset($item['yNorm']) && is_numeric($item['yNorm']) ? (float) $item['yNorm'] : null;

        if ($x === null || $y === null) {
            continue;
        }

        $position = [
            'x' => max(0, min(100, $x * 100)),
            'y' => max(0, min(100, $y * 100)),
        ];

        if ($place_id !== '') {
            $lookup['by_id'][$place_id] = $position;
        }

        if ($post_id !== '') {
            $lookup['by_id'][$post_id] = $position;
        }

        if ($name !== '') {
            $lookup['by_name'][$name] = $position;
        }

        if ($lat !== null && $lng !== null) {
            $lookup['by_coords'][iss_relations_get_place_map_coord_key($lat, $lng)] = $position;
        }
    }

    if (!$lookup['by_id'] && !$lookup['by_name'] && !$lookup['by_coords']) {
        $cache[$markers_path] = [];
        return $cache[$markers_path];
    }

    $cache[$markers_path] = $lookup;

    return $cache[$markers_path];
}

function iss_relations_get_place_map_marker_position(array $place, array $marker_lookup): ?array
{
    $place_id = isset($place['place_id']) ? (string) $place['place_id'] : '';
    if ($place_id !== '' && isset($marker_lookup['by_id'][$place_id])) {
        return $marker_lookup['by_id'][$place_id];
    }

    $lat = isset($place['lat']) && is_numeric($place['lat']) ? (float) $place['lat'] : null;
    $lng = isset($place['lng']) && is_numeric($place['lng']) ? (float) $place['lng'] : null;
    if ($lat !== null && $lng !== null) {
        $coord_key = iss_relations_get_place_map_coord_key($lat, $lng);
        if (isset($marker_lookup['by_coords'][$coord_key])) {
            return $marker_lookup['by_coords'][$coord_key];
        }
    }

    $title = sanitize_title((string) ($place['title'] ?? ''));
    if ($title !== '' && isset($marker_lookup['by_name'][$title])) {
        return $marker_lookup['by_name'][$title];
    }

    return null;
}

function iss_relations_collect_map_places(array $attributes = [], $block = null): array
{
    $attributes = is_array($attributes) ? $attributes : [];
    $current_post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
    $per_page = max(1, min(12, absint($attributes['perPage'] ?? 5)));
    $place_items = iss_relations_resolve_block_place_items($attributes, $current_post_id);

    if (!$place_items) {
        return [];
    }

    return array_slice($place_items, 0, $per_page);
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

function iss_relations_render_place_map_stage(array $places, array $config): string
{
    $image_url = $config['image_url'] ?? '';
    $image_alt = $config['image_alt'] ?? '';
    $marker_lookup = iss_relations_get_place_map_marker_lookup((string) ($config['markers_path'] ?? ''));

    if ($image_url === '' || !$marker_lookup) {
        return '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl ist noch keine Atlaskarte hinterlegt.', 'iss-relations') . '</div>';
    }

    $mapped_places = [];

    foreach ($places as $index => $place) {
        $position = iss_relations_get_place_map_marker_position($place, $marker_lookup);
        if ($position === null) {
            continue;
        }

        $mapped_places[] = [
            'index' => $index,
            'place' => $place,
            'position' => $position,
        ];
    }

    if (!$mapped_places) {
        return '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl sind noch keine Koordinaten hinterlegt.', 'iss-relations') . '</div>';
    }

    $markers = '';
    $stage_styles = [];

    if (!empty($config['width']) && !empty($config['height'])) {
        $stage_styles[] = '--iss-related-place-map-ratio:' . (int) $config['width'] . ' / ' . (int) $config['height'];
    }

    $viewport = is_array($config['viewport'] ?? null) ? $config['viewport'] : [];
    $scale = isset($viewport['scale']) && is_numeric($viewport['scale']) ? (float) $viewport['scale'] : 1.0;
    $scale_x = isset($viewport['scale_x']) && is_numeric($viewport['scale_x']) ? (float) $viewport['scale_x'] : $scale;
    $scale_y = isset($viewport['scale_y']) && is_numeric($viewport['scale_y']) ? (float) $viewport['scale_y'] : $scale;
    $offset_x = isset($viewport['offset_x']) && is_numeric($viewport['offset_x']) ? (float) $viewport['offset_x'] : 0.0;
    $offset_y = isset($viewport['offset_y']) && is_numeric($viewport['offset_y']) ? (float) $viewport['offset_y'] : 0.0;

    $stage_styles[] = '--iss-related-place-map-scale-x:' . number_format($scale_x > 0 ? $scale_x : 1.0, 4, '.', '');
    $stage_styles[] = '--iss-related-place-map-scale-y:' . number_format($scale_y > 0 ? $scale_y : 1.0, 4, '.', '');
    $stage_styles[] = '--iss-related-place-map-offset-x:' . number_format($offset_x, 3, '.', '') . '%';
    $stage_styles[] = '--iss-related-place-map-offset-y:' . number_format($offset_y, 3, '.', '') . '%';

    $stage_attr = '';
    if ($stage_styles) {
        $stage_attr = ' style="' . esc_attr(implode(';', $stage_styles)) . '"';
    }

    foreach ($mapped_places as $item) {
        $index = (int) $item['index'];
        $place = $item['place'];
        $position = $item['position'];
        $marker_x = ((float) $position['x'] * ($scale_x > 0 ? $scale_x : 1.0)) + $offset_x;
        $marker_y = ((float) $position['y'] * ($scale_y > 0 ? $scale_y : 1.0)) + $offset_y;
        $label = trim((string) ($place['label'] ?? ''));
        $marker_label = $label !== '' ? ($label . ': ' . $place['title']) : $place['title'];

        $markers .= sprintf(
            '<a class="iss-related-place-map__marker" href="%1$s" style="--x:%2$s%%;--y:%3$s%%" aria-label="%4$s"><span class="iss-related-place-map__marker-dot" aria-hidden="true"></span><span class="iss-related-place-map__marker-label">%5$s</span></a>',
            esc_url((string) ($place['permalink'] ?? '')),
            esc_attr(number_format($marker_x, 3, '.', '')),
            esc_attr(number_format($marker_y, 3, '.', '')),
            esc_attr($marker_label),
            esc_html((string) ($index + 1))
        );
    }

    return '<div class="iss-related-place-map__stage"' . $stage_attr . '><div class="iss-related-place-map__viewport"><img class="iss-related-place-map__image" src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" loading="lazy" decoding="async"></div><div class="iss-related-place-map__markers">' . $markers . '</div></div>';
}

function iss_relations_render_place_map_panel(array $places): string
{
    $out = '<div class="iss-related-place-map__panel">';

    foreach ($places as $index => $place) {
        $permalink = (string) ($place['permalink'] ?? '');
        $title = trim((string) ($place['title'] ?? ''));
        $label = trim((string) ($place['label'] ?? ''));
        $excerpt = trim((string) ($place['excerpt'] ?? ''));

        $out .= '<article class="iss-related-place-map__entry">';
        $out .= '<div class="iss-related-place-map__entry-index">' . esc_html((string) ($index + 1)) . '</div>';
        $out .= '<div class="iss-related-place-map__entry-body">';
        if ($label !== '') {
            $out .= '<p class="iss-related-place-map__entry-kicker">' . esc_html($label) . '</p>';
        }
        $out .= '<h3 class="iss-related-place-map__entry-title"><a href="' . esc_url($permalink) . '">' . esc_html($title) . '</a></h3>';
        if ($excerpt !== '') {
            $out .= '<p class="iss-related-place-map__entry-text">' . esc_html($excerpt) . '</p>';
        }
        $out .= '<p class="iss-related-place-map__entry-link"><a class="iss-action-link" href="' . esc_url($permalink) . '">' . esc_html__('Zum Ort', 'iss-relations') . '</a></p>';
        $out .= '</div>';
        $out .= '</article>';
    }

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

function iss_relations_render_related_place_map_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $places = iss_relations_collect_map_places($attributes, $block);
    if (!$places) {
        return '';
    }

    $defaults = iss_relations_get_place_map_defaults();
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? $defaults['kicker'])));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? $defaults['title'])));
    $text = trim((string) ($attributes['text'] ?? ''));
    $link_text = (string) $defaults['link_text'];
    $preset = iss_relations_resolve_place_map_preset($attributes);
    $config = iss_relations_get_place_map_config($preset);
    $panel_mode = iss_relations_normalize_place_map_panel_mode($attributes);
    $panel_position = iss_relations_normalize_place_map_panel_position($attributes);
    $body_classes = ['iss-related-place-map__body', 'iss-related-place-map__body--panel-' . $panel_mode];

    if ($panel_mode === 'show') {
        $body_classes[] = 'iss-related-place-map__body--panel-' . $panel_position;
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'section section--plain iss-related-place-map',
        ])
        : 'class="' . esc_attr('section section--plain iss-related-place-map') . '"';

    $has_intro = ($kicker !== '' || $title !== '' || $text !== '');

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';
    $out .= '<div class="iss-related-place-map__shell">';
    if ($has_intro) {
        $out .= '<div class="iss-heading iss-related-place-map__intro">';
        if ($kicker !== '') {
            $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h2 class="iss-heading__title">' . esc_html($title) . '</h2>';
        }
        if ($text !== '') {
            $out .= '<p class="iss-heading__text">' . esc_html($text) . '</p>';
        }
        $out .= '<p class="iss-related-place-map__cta"><a class="iss-action-link" href="' . esc_url(home_url('/schoneweide/')) . '">' . esc_html($link_text) . '</a></p>';
        $out .= '</div>';
    }
    $out .= '<div class="' . esc_attr(implode(' ', $body_classes)) . '">';
    $out .= iss_relations_render_place_map_stage($places, $config);
    if ($panel_mode === 'show') {
        $out .= iss_relations_render_place_map_panel($places);
    }
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}
