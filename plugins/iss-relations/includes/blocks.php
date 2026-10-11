<?php

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/block-registration.php';
require_once __DIR__ . '/related-query.php';

function iss_relations_get_map_block_contracts(): array
{
    $contracts = [
        'iss/related-place-map' => [
            'default_source' => 'current',
            'default_preset' => 'default',
            'manual_ids_imply_manual_source' => true,
            'frontend_renderer' => 'iss_frontend_render_related_place_map_body',
        ],
        'iss/atlas-map' => [
            'default_source' => 'current',
            'default_preset' => 'default',
            'manual_ids_imply_manual_source' => true,
            'frontend_renderer' => 'iss_frontend_render_atlas_map_block',
        ],
        'iss/atlas-slice' => [
            'default_source' => 'current',
            'default_preset' => 'default',
            'manual_ids_imply_manual_source' => true,
            'frontend_renderer' => 'iss_frontend_render_atlas_slice_block',
        ],
        'iss/atlas-strip' => [
            'default_source' => 'current',
            'default_preset' => 'default',
            'manual_ids_imply_manual_source' => true,
            'frontend_renderer' => 'iss_frontend_render_atlas_strip_block',
        ],
        'iss/spine-strip' => [
            'default_source' => 'manual',
            'default_preset' => 'default',
            'manual_ids_imply_manual_source' => true,
            'frontend_renderer' => 'iss_frontend_render_spine_strip_block',
        ],
        'iss/asymmetric-split-field' => [
            'default_source' => 'current',
            'default_preset' => 'default',
            'manual_ids_imply_manual_source' => true,
            'frontend_renderer' => 'iss_frontend_render_asymmetric_split_field_block',
        ],
    ];

    return apply_filters('iss_relations_map_block_contracts', $contracts);
}

function iss_relations_get_map_block_contract(string $block_name): array
{
    $contracts = iss_relations_get_map_block_contracts();

    return isset($contracts[$block_name]) && is_array($contracts[$block_name])
        ? $contracts[$block_name]
        : [];
}

function iss_relations_get_map_block_default(string $block_name, string $key, $fallback = '')
{
    $contract = iss_relations_get_map_block_contract($block_name);

    return array_key_exists($key, $contract) ? $contract[$key] : $fallback;
}

function iss_relations_get_map_block_public_contracts(): array
{
    $public = [];

    foreach (iss_relations_get_map_block_contracts() as $block_name => $contract) {
        if (!is_array($contract)) {
            continue;
        }

        $public[$block_name] = [
            'defaultSource' => sanitize_key((string) ($contract['default_source'] ?? 'current')),
            'defaultPreset' => sanitize_key((string) ($contract['default_preset'] ?? 'default')),
            'manualIdsImplyManualSource' => !empty($contract['manual_ids_imply_manual_source']),
        ];
    }

    return $public;
}

function iss_relations_get_rendered_block_name($block): string
{
    if (is_object($block) && isset($block->name)) {
        return (string) $block->name;
    }

    if (is_array($block) && isset($block['blockName'])) {
        return (string) $block['blockName'];
    }

    return '';
}

function iss_relations_resolve_map_block_source(array $attributes, string $block_name = ''): string
{
    $source = sanitize_key((string) ($attributes['source'] ?? ''));
    if ($source !== '') {
        return $source;
    }

    $contract = iss_relations_get_map_block_contract($block_name);
    if (!empty($contract['manual_ids_imply_manual_source']) && trim((string) ($attributes['placeIds'] ?? '')) !== '') {
        return 'manual';
    }

    return sanitize_key((string) ($contract['default_source'] ?? 'current')) ?: 'current';
}

function iss_relations_get_atlas_map_variants(): array
{
    $variants = [
        'place-locator' => [
            'source' => 'current',
            'perPage' => 1,
            'mapPreset' => 'default',
            'skin' => 'locator',
            'treatment' => 'stage',
            'panelMode' => 'hide',
            'lineMode' => 'none',
            'ratioWidth' => 1600,
            'ratioHeight' => 720,
            'showMarkers' => true,
            'shellMode' => 'body',
        ],
        'tour-route' => [
            'source' => 'route',
            'perPage' => 12,
            'mapPreset' => 'default',
            'skin' => 'route',
            'treatment' => 'panelled',
            'panelMode' => 'show',
            'panelPosition' => 'right',
            'lineMode' => 'route',
            'ratioWidth' => 1600,
            'ratioHeight' => 900,
            'showMarkers' => true,
            'shellMode' => 'body',
        ],
        'map-only' => [
            'source' => 'current',
            'perPage' => 6,
            'mapPreset' => 'default',
            'skin' => 'terrain',
            'treatment' => 'band',
            'panelMode' => 'hide',
            'lineMode' => 'none',
            'ratioWidth' => 1600,
            'ratioHeight' => 480,
            'showMarkers' => true,
            'shellMode' => 'body',
        ],
        'editorial-split' => [
            'source' => 'current',
            'perPage' => 6,
            'mapPreset' => 'default',
            'skin' => 'terrain',
            'treatment' => 'band',
            'panelMode' => 'hide',
            'lineMode' => 'none',
            'ratioWidth' => 1600,
            'ratioHeight' => 780,
            'showMarkers' => true,
            'shellMode' => 'body',
        ],
        'map-image-panel' => [
            'source' => 'current',
            'perPage' => 6,
            'mapPreset' => 'default',
            'skin' => 'terrain',
            'treatment' => 'band',
            'panelMode' => 'hide',
            'lineMode' => 'none',
            'ratioWidth' => 800,
            'ratioHeight' => 1200,
            'showMarkers' => true,
            'shellMode' => 'body',
        ],
    ];

    return apply_filters('iss_relations_atlas_map_variants', $variants);
}

function iss_relations_resolve_atlas_map_variant(array $attributes = [], string $variant_override = ''): array
{
    $variants = iss_relations_get_atlas_map_variants();
    $variant = sanitize_key($variant_override !== '' ? $variant_override : (string) ($attributes['variant'] ?? ''));

    if ($variant === '' || !isset($variants[$variant]) || !is_array($variants[$variant])) {
        $variant = isset($variants['place-locator']) ? 'place-locator' : (string) array_key_first($variants);
    }

    $contract = is_array($variants[$variant] ?? null) ? $variants[$variant] : [];
    $public_overrides = [];

    foreach (['source', 'placeIds', 'perPage', 'mapPreset', 'title', 'kicker', 'text', 'linkUrl', 'linkLabel', 'shellMode'] as $key) {
        if (array_key_exists($key, $attributes)) {
            $public_overrides[$key] = $attributes[$key];
        }
    }

    return array_merge($contract, $public_overrides, [
        'variant' => $variant,
    ]);
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
            'image_sources' => [],
            'rail' => [],
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
        $image_sources = [];

        foreach ((array) ($preset_config['image_sources'] ?? []) as $source) {
            if (!is_array($source)) {
                continue;
            }

            $url = trim((string) ($source['url'] ?? ''));
            $source_width = absint($source['width'] ?? 0);
            if ($url !== '' && $source_width > 0) {
                $image_sources[] = [
                    'url' => $url,
                    'width' => $source_width,
                ];
            }
        }

        usort($image_sources, static fn(array $left, array $right): int => $left['width'] <=> $right['width']);
        $normalized[$preset] = [
            'label' => trim((string) ($preset_config['label'] ?? $preset)),
            'image_url' => isset($preset_config['image_url']) ? (string) $preset_config['image_url'] : '',
            'markers_path' => isset($preset_config['markers_path']) ? (string) $preset_config['markers_path'] : '',
            'image_alt' => isset($preset_config['image_alt']) ? (string) $preset_config['image_alt'] : __('Übersichtskarte des Schöneweide-Atlas.', 'iss-relations'),
            'width' => $width,
            'height' => $height,
            'image_sources' => $image_sources,
            'rail' => is_array($preset_config['rail'] ?? null) ? $preset_config['rail'] : [],
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
            'hasRail' => !empty((array) (($config['rail']['stations'] ?? []))),
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
        'image_sources' => [],
        'rail' => [],
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

function iss_relations_resolve_spine_strip_preset(array $attributes = []): string
{
    return iss_relations_resolve_place_map_preset($attributes);
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
    return function_exists('iss_frontend_static_maps_get_marker_lookup')
        ? iss_frontend_static_maps_get_marker_lookup($markers_path)
        : [];
}

function iss_relations_get_place_map_marker_position(array $place, array $marker_lookup): ?array
{
    return function_exists('iss_frontend_static_maps_get_marker_position')
        ? iss_frontend_static_maps_get_marker_position($place, $marker_lookup)
        : null;
}

function iss_relations_collect_map_places(array $attributes = [], $block = null, array $context = []): array
{
    $attributes = is_array($attributes) ? $attributes : [];
    $context_post_id = absint($context['post_id'] ?? 0);
    $current_post_id = $context_post_id > 0
        ? $context_post_id
        : (isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID());
    $per_page = max(1, min(12, absint($attributes['perPage'] ?? 5)));
    $relation_result = iss_relations_resolve_static_map_relation_result(
        $attributes,
        $current_post_id,
        iss_relations_get_rendered_block_name($block)
    );
    $place_items = (array) ($relation_result['places'] ?? []);

    if (!$place_items) {
        return [];
    }

    return array_slice($place_items, 0, $per_page);
}

function iss_relations_render_generic_card(WP_Post $post, array $copy, array $options = []): string
{
    $post_type = (string) $post->post_type;
    $permalink = (string) get_permalink($post);
    $title = get_the_title($post);
    $meta_line = iss_relations_get_card_meta_line($post);
    if ($post_type === 'fuehrung' && function_exists('iss_fuehrung_get_card_meta')) {
        $meta = iss_fuehrung_get_card_meta((int) $post->ID);
        $meta_line = $meta ? implode(' · ', $meta) : '';
    }
    $detail_line = iss_relations_get_card_detail_line($post);
    $excerpt = iss_relations_get_card_excerpt($post);
    $kicker = iss_relations_get_card_kicker($post, $copy);
    $link_text = trim((string) ($copy['link_text'] ?? __('Weiter', 'iss-relations')));
    $card_class = 'iss-card iss-card--flat iss-related-feed__card iss-related-feed__card--' . sanitize_html_class($post_type);
    $show_image = !isset($options['show_image']) || !empty($options['show_image']);
    $show_excerpt = !isset($options['show_excerpt']) || !empty($options['show_excerpt']);
    $layout_variant = iss_relations_normalize_related_cards_layout_variant($options);
    $skin = iss_relations_normalize_related_cards_skin($options);
    $has_media = $show_image && has_post_thumbnail($post);
    $placeholder_image_url = $show_image && !$has_media ? iss_relations_get_card_placeholder_image_url($post, $options) : '';

    $card_class .= ' iss-related-feed__card--layout-' . sanitize_html_class($layout_variant);
    $card_class .= ' iss-related-feed__card--skin-' . sanitize_html_class($skin);

    if (!$has_media) {
        $card_class .= ' iss-related-feed__card--no-media';
    }

    ob_start();
    ?>
    <article class="<?php echo esc_attr($card_class); ?>">
        <?php if ($show_image) : ?>
            <div class="iss-related-feed__media-shell iss-media-card iss-media-card--cover iss-media-card--soft iss-media-card--rail-thin">
                <?php if ($has_media) : ?>
                    <a class="iss-card__media" href="<?php echo esc_url($permalink); ?>">
                        <?php echo get_the_post_thumbnail($post, 'large'); ?>
                    </a>
                <?php else : ?>
                    <a class="iss-card__media iss-related-feed__placeholder<?php echo $placeholder_image_url !== '' ? ' iss-related-feed__placeholder--image' : ''; ?>" href="<?php echo esc_url($permalink); ?>" aria-label="<?php echo esc_attr($title); ?>">
                        <?php if ($placeholder_image_url !== '') : ?>
                            <img src="<?php echo esc_url($placeholder_image_url); ?>" alt="" loading="lazy" decoding="async">
                        <?php endif; ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <div class="iss-card__body">
            <?php if ($kicker !== '') : ?>
                <p class="iss-kicker iss-kicker--compact"><?php echo esc_html($kicker); ?></p>
            <?php endif; ?>
            <h3 class="iss-card__title"><a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($title); ?></a></h3>
            <?php if ($detail_line !== '') : ?>
                <p class="iss-related-feed__detail"><?php echo esc_html($detail_line); ?></p>
            <?php endif; ?>
            <?php if ($meta_line !== '') : ?>
                <p class="iss-related-feed__meta"><?php echo esc_html($meta_line); ?></p>
            <?php endif; ?>
            <?php if ($show_excerpt && $excerpt !== '') : ?>
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

function iss_relations_get_card_placeholder_image_url(WP_Post $post, array $options = []): string
{
    $url = (string) apply_filters('iss_relations_card_placeholder_image_url', '', $post, $options);

    return esc_url_raw($url);
}

function iss_relations_render_related_content_card(WP_Post $post, array $defaults, array $options = []): string
{
    $card_copy = [
        'kicker' => $defaults['kicker'] ?? '',
        'link_text' => $defaults['link_text'] ?? __('Weiter', 'iss-relations'),
    ];

    return iss_relations_render_generic_card($post, $card_copy, $options);
}

function iss_relations_collect_related_cards(array $attributes = [], $block = null): array
{
    $attributes = is_array($attributes) ? $attributes : [];
    $current_post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
    return iss_relations_collect_related_cards_data($attributes, $current_post_id);
}

function iss_relations_collect_related_cards_data(array $attributes = [], int $current_post_id = 0): array
{
    $attributes = is_array($attributes) ? $attributes : [];
    $post_types = iss_relations_get_related_cards_post_types(
        $attributes,
        sanitize_key((string) ($attributes['postType'] ?? 'post'))
    );
    $per_page = max(1, min(24, absint($attributes['perPage'] ?? 3)));

    if (!$post_types) {
        return [];
    }

    $related_posts = iss_relations_resolve_block_posts($attributes, $current_post_id, $post_types, $per_page);
    $related_posts = iss_relations_apply_editorial_signals_to_related_posts($related_posts, $current_post_id, $post_types, $per_page, $attributes);
    if (!$related_posts) {
        return [];
    }

    $defaults = iss_relations_get_related_content_defaults_for_post_types($post_types);
    $card_options = [
        'layoutVariant' => iss_relations_normalize_related_cards_layout_variant($attributes),
        'sortMode' => iss_relations_normalize_related_cards_sort_mode($attributes, $post_types),
        'columns' => iss_relations_normalize_related_cards_columns($attributes),
        'show_image' => !isset($attributes['showImage']) || !empty($attributes['showImage']),
        'show_excerpt' => false,
        'skin' => iss_relations_normalize_related_cards_skin($attributes),
    ];
    $cards = [];

    foreach ($related_posts as $related_post) {
        if (!$related_post instanceof WP_Post) {
            continue;
        }

        $cards[] = iss_relations_render_related_content_card($related_post, $defaults, $card_options);
    }

    if (!$cards) {
        return [];
    }

    return [
        'post_type' => $post_types[0],
        'post_types' => $post_types,
        'scope_key' => iss_relations_get_related_cards_scope_key($post_types),
        'cards' => $cards,
        'defaults' => $defaults,
        'card_options' => $card_options,
        'posts' => $related_posts,
    ];
}

function iss_relations_build_related_cards_preview_payload(array $attributes = [], int $current_post_id = 0): array
{
    $data = iss_relations_collect_related_cards_data($attributes, $current_post_id);
    $editorial_signals = iss_relations_prepare_editorial_signal_preview_items($current_post_id);
    if (!$data) {
        $post_types = iss_relations_get_related_cards_post_types(
            $attributes,
            sanitize_key((string) ($attributes['postType'] ?? 'post'))
        );

        return [
            'items' => [],
            'count' => 0,
            'postType' => $post_types ? $post_types[0] : sanitize_key((string) ($attributes['postType'] ?? 'post')),
            'postTypes' => $post_types,
            'scopeKey' => iss_relations_get_related_cards_scope_key($post_types),
            'layoutVariant' => iss_relations_normalize_related_cards_layout_variant($attributes),
            'sortMode' => iss_relations_normalize_related_cards_sort_mode($attributes, $post_types),
            'columns' => iss_relations_normalize_related_cards_columns($attributes),
            'skin' => iss_relations_normalize_related_cards_skin($attributes),
            'editorialSignals' => $editorial_signals,
        ];
    }

    $items = [];
    $signals_by_target = iss_relations_get_related_editorial_signals_by_target($current_post_id);
    $show_image = !isset($data['card_options']['show_image']) || !empty($data['card_options']['show_image']);
    $show_excerpt = !isset($data['card_options']['show_excerpt']) || !empty($data['card_options']['show_excerpt']);

    foreach ((array) ($data['posts'] ?? []) as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $thumb = '';
        if ($show_image && has_post_thumbnail($post)) {
            $thumb = (string) get_the_post_thumbnail_url($post, 'medium');
        } elseif ($show_image) {
            $thumb = iss_relations_get_card_placeholder_image_url($post, (array) ($data['card_options'] ?? []));
        }

        $items[] = [
            'id' => (int) $post->ID,
            'title' => (string) get_the_title($post),
            'permalink' => (string) get_permalink($post),
            'kicker' => iss_relations_get_card_kicker($post, $data['defaults'] ?? []),
            'meta' => $post->post_type === 'fuehrung' && function_exists('iss_fuehrung_get_card_meta')
                ? implode(' · ', (array) iss_fuehrung_get_card_meta((int) $post->ID))
                : iss_relations_get_card_meta_line($post),
            'detail' => iss_relations_get_card_detail_line($post),
            'excerpt' => $show_excerpt ? iss_relations_get_card_excerpt($post) : '',
            'thumbnail' => $thumb,
            'postType' => (string) $post->post_type,
            'editorialSignal' => isset($signals_by_target[$post->ID]) ? sanitize_key((string) ($signals_by_target[$post->ID]['signal'] ?? '')) : '',
            'editorialReason' => isset($signals_by_target[$post->ID]) ? (string) ($signals_by_target[$post->ID]['reason'] ?? '') : '',
            'editorialExpiresAt' => isset($signals_by_target[$post->ID]['expires_at']) && $signals_by_target[$post->ID]['expires_at'] !== null ? substr((string) $signals_by_target[$post->ID]['expires_at'], 0, 10) : '',
        ];
    }

    return [
        'items' => $items,
        'count' => count($items),
        'postType' => (string) $data['post_type'],
        'postTypes' => (array) ($data['post_types'] ?? []),
        'scopeKey' => (string) ($data['scope_key'] ?? $data['post_type']),
        'layoutVariant' => (string) ($data['card_options']['layoutVariant'] ?? 'grid'),
        'sortMode' => (string) ($data['card_options']['sortMode'] ?? 'auto'),
        'columns' => (int) ($data['card_options']['columns'] ?? 3),
        'showImage' => $show_image,
        'showExcerpt' => $show_excerpt,
        'skin' => (string) ($data['card_options']['skin'] ?? 'default'),
        'editorialSignals' => $editorial_signals,
    ];
}

function iss_relations_rest_preview_related_cards(WP_REST_Request $request): WP_REST_Response
{
    $params = $request->get_json_params();
    $attributes = is_array($params['attributes'] ?? null) ? $params['attributes'] : [];
    $current_post_id = absint($params['postId'] ?? 0);

    return new WP_REST_Response(
        iss_relations_build_related_cards_preview_payload($attributes, $current_post_id)
    );
}

function iss_relations_register_rest_routes(): void
{
    register_rest_route('iss-relations/v1', '/related-preview', [
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'iss_relations_rest_preview_related_cards',
        'permission_callback' => static function (): bool {
            return current_user_can('edit_posts');
        },
    ]);
}
add_action('rest_api_init', 'iss_relations_register_rest_routes');

function iss_relations_render_cards_grid(array $cards, string $post_type, array $options = []): string
{
    $skin = iss_relations_normalize_related_cards_skin($options);
    $layout_variant = iss_relations_normalize_related_cards_layout_variant($options);

    if ($layout_variant === 'strip') {
        iss_relations_enqueue_related_strip_script();
    }

    $classes = [
        'iss-related-feed__grid',
        'iss-related-feed__grid--' . sanitize_html_class($post_type),
        'iss-related-feed__grid--layout-' . sanitize_html_class($layout_variant),
        'iss-related-feed__grid--columns-' . iss_relations_normalize_related_cards_columns($options),
        'iss-related-feed__grid--skin-' . sanitize_html_class($skin),
    ];

    $grid = '<div class="' . esc_attr(implode(' ', $classes)) . '">';
    $grid .= implode('', $cards);
    $grid .= '</div>';

    if ($layout_variant !== 'strip') {
        return $grid;
    }

    $controls_label = esc_attr__('Karussell-Steuerung', 'iss-relations');
    $prev_label = esc_attr__('Vorherige Karten', 'iss-relations');
    $next_label = esc_attr__('Nächste Karten', 'iss-relations');
    $prev_text = esc_html__('Zurück', 'iss-relations');
    $next_text = esc_html__('Weiter', 'iss-relations');

    $out = '<div class="iss-related-feed__carousel" data-related-carousel>';
    $out .= $grid;
    $out .= '<div class="iss-related-feed__carousel-controls" aria-label="' . $controls_label . '">';
    $out .= '<button type="button" class="iss-related-feed__carousel-control iss-related-feed__carousel-control--prev" data-related-carousel-prev aria-label="' . $prev_label . '" disabled>';
    $out .= '<span class="iss-related-feed__carousel-control-icon" aria-hidden="true">&#8592;</span>';
    $out .= '<span class="iss-related-feed__carousel-control-text">' . $prev_text . '</span>';
    $out .= '</button>';
    $out .= '<button type="button" class="iss-related-feed__carousel-control iss-related-feed__carousel-control--next" data-related-carousel-next aria-label="' . $next_label . '" disabled>';
    $out .= '<span class="iss-related-feed__carousel-control-text">' . $next_text . '</span>';
    $out .= '<span class="iss-related-feed__carousel-control-icon" aria-hidden="true">&#8594;</span>';
    $out .= '</button>';
    $out .= '</div>';
    $out .= '</div>';

    return $out;
}

function iss_relations_render_related_place_map_body(array $attributes, $block = null): string
{
    $places = iss_relations_collect_map_places($attributes, $block);
    if (!$places) {
        return '';
    }

    $preset = iss_relations_resolve_place_map_preset($attributes);
    $config = iss_relations_get_place_map_config($preset);

    return function_exists('iss_frontend_render_related_place_map_body')
        ? iss_frontend_render_related_place_map_body($attributes, $places, $config)
        : '';
}

function iss_relations_clamp_float(float $value, float $min, float $max): float
{
    return function_exists('iss_frontend_static_maps_clamp_float')
        ? iss_frontend_static_maps_clamp_float($value, $min, $max)
        : max($min, min($max, $value));
}

function iss_relations_collect_mapped_places(array $places, array $config): array
{
    return function_exists('iss_frontend_static_maps_collect_mapped_places')
        ? iss_frontend_static_maps_collect_mapped_places($places, $config)
        : [];
}

function iss_relations_render_atlas_slice_stage(array $places, array $config, array $options = []): string
{
    return function_exists('iss_frontend_static_maps_render_atlas_slice_stage')
        ? iss_frontend_static_maps_render_atlas_slice_stage($places, $config, $options)
        : '';
}

function iss_relations_normalize_atlas_slice_body_mode(array $attributes): string
{
    $mode = sanitize_key((string) ($attributes['bodyMode'] ?? 'text'));

    return in_array($mode, ['text', 'image'], true) ? $mode : 'text';
}

function iss_relations_normalize_atlas_slice_body_position(array $attributes): string
{
    $position = sanitize_key((string) ($attributes['bodyPosition'] ?? 'end'));

    return $position === 'start' ? 'start' : 'end';
}

function iss_relations_normalize_atlas_slice_layout_mode(array $attributes): string
{
    $mode = sanitize_key((string) ($attributes['layoutMode'] ?? 'band'));

    return $mode === 'split' ? 'split' : 'band';
}

function iss_relations_normalize_atlas_strip_variant(array $attributes): string
{
    $variant = sanitize_key((string) ($attributes['variant'] ?? 'place'));

    return in_array($variant, ['corridor', 'place', 'minimal', 'spine'], true) ? $variant : 'place';
}

function iss_relations_normalize_atlas_strip_theme(array $attributes): string
{
    $theme = sanitize_key((string) ($attributes['theme'] ?? 'black'));

    return in_array($theme, ['black', 'red', 'ochre', 'blue', 'green', 'violet'], true) ? $theme : 'black';
}

function iss_relations_normalize_atlas_strip_line_mode(array $attributes): string
{
    $mode = sanitize_key((string) ($attributes['lineMode'] ?? 'route'));

    return in_array($mode, ['none', 'route', 'corridor'], true) ? $mode : 'route';
}

function iss_relations_normalize_atlas_strip_station_mode(array $attributes): string
{
    $mode = sanitize_key((string) ($attributes['stationMode'] ?? 'selected'));

    return in_array($mode, ['selected', 'preset'], true) ? $mode : 'selected';
}

function iss_relations_normalize_spine_text_mode(array $attributes): string
{
    $mode = sanitize_key((string) ($attributes['textMode'] ?? 'text'));

    return in_array($mode, ['text', 'quote'], true) ? $mode : 'text';
}

function iss_relations_parse_place_id_list(string $value): array
{
    $items = preg_split('/[\s,]+/', trim($value)) ?: [];
    $out = [];

    foreach ($items as $item) {
        $item = trim((string) $item);
        if ($item === '' || isset($out[$item])) {
            continue;
        }

        $out[$item] = $item;
    }

    return array_values($out);
}

function iss_relations_get_atlas_strip_rail(array $config): array
{
    $rail = is_array($config['rail'] ?? null) ? $config['rail'] : [];
    $stations_raw = is_array($rail['stations'] ?? null) ? $rail['stations'] : [];
    $stations = [];
    $station_offset_x = is_numeric($rail['station_offset_x'] ?? null)
        ? iss_relations_clamp_float(round((float) $rail['station_offset_x'], 3), -10.0, 10.0)
        : 0.0;

    foreach ($stations_raw as $station) {
        if (!is_array($station)) {
            continue;
        }

        $id = trim((string) ($station['id'] ?? ''));
        $label = trim((string) ($station['label'] ?? ''));

        if ($id === '' || $label === '') {
            continue;
        }

        $stations[] = [
            'id' => $id,
            'label' => $label,
        ];
    }

    return [
        'direction_label' => trim((string) ($rail['direction_label'] ?? '')),
        'station_offset_x' => $station_offset_x,
        'stations' => $stations,
    ];
}

function iss_relations_get_atlas_strip_rail_label_lookup(array $config): array
{
    $lookup = [];

    foreach (iss_relations_get_atlas_strip_rail($config)['stations'] as $station) {
        $id = trim((string) ($station['id'] ?? ''));
        $label = trim((string) ($station['label'] ?? ''));

        if ($id === '' || $label === '') {
            continue;
        }

        $lookup[$id] = $label;
    }

    return $lookup;
}

function iss_relations_get_atlas_strip_station_items(array $config, array $active_places): array
{
    $rail = iss_relations_get_atlas_strip_rail($config);
    if (!$rail['stations']) {
        return [];
    }

    $marker_lookup = iss_relations_get_place_map_marker_lookup((string) ($config['markers_path'] ?? ''));
    if (!$marker_lookup) {
        return [];
    }

    $active_ids = [];
    foreach ($active_places as $place) {
        $place_id = trim((string) ($place['place_id'] ?? ''));
        if ($place_id !== '') {
            $active_ids[$place_id] = true;
        }
    }

    $items = [];

    foreach ($rail['stations'] as $station) {
        $id = (string) ($station['id'] ?? '');
        if ($id === '' || !isset($marker_lookup['by_id'][$id])) {
            continue;
        }

        $items[] = [
            'id' => $id,
            'label' => (string) ($station['label'] ?? $id),
            'position' => [
                'x' => (float) ($marker_lookup['by_id'][$id]['x'] ?? 0.0),
                'y' => (float) ($marker_lookup['by_id'][$id]['y'] ?? 0.0),
            ],
            'is_active' => isset($active_ids[$id]),
        ];
    }

    return $items;
}

function iss_relations_get_selected_spine_station_items(array $places, array $config): array
{
    $marker_lookup = iss_relations_get_place_map_marker_lookup((string) ($config['markers_path'] ?? ''));
    if (!$marker_lookup) {
        return [];
    }

    $rail_label_lookup = iss_relations_get_atlas_strip_rail_label_lookup($config);
    $items = [];

    foreach ($places as $place) {
        if (!is_array($place)) {
            continue;
        }

        $place_id = trim((string) ($place['place_id'] ?? ''));
        if ($place_id === '') {
            continue;
        }

        $position = iss_relations_get_place_map_marker_position($place, $marker_lookup);
        if ($position === null) {
            continue;
        }

        $items[] = [
            'id' => $place_id,
            'label' => trim((string) ($rail_label_lookup[$place_id] ?? ($place['label'] ?? '') ?: ($place['title'] ?? $place_id))),
            'position' => [
                'x' => (float) ($position['x'] ?? 0.0),
                'y' => (float) ($position['y'] ?? 0.0),
            ],
            'is_active' => true,
        ];
    }

    return $items;
}

function iss_relations_render_atlas_spine_payload(array $attributes, array $places): string
{
    $media_url = trim((string) ($attributes['mediaUrl'] ?? ''));
    $media_alt = trim((string) ($attributes['mediaAlt'] ?? ''));
    $payload_kicker = trim(sanitize_text_field((string) ($attributes['payloadKicker'] ?? '')));
    $payload_title = trim(sanitize_text_field((string) ($attributes['payloadTitle'] ?? '')));
    $payload_text = trim((string) ($attributes['payloadText'] ?? ''));
    $meta_primary_label = trim(sanitize_text_field((string) ($attributes['payloadMetaPrimaryLabel'] ?? '')));
    $meta_primary_value = trim(sanitize_text_field((string) ($attributes['payloadMetaPrimaryValue'] ?? '')));
    $meta_secondary_label = trim(sanitize_text_field((string) ($attributes['payloadMetaSecondaryLabel'] ?? '')));
    $meta_secondary_value = trim(sanitize_text_field((string) ($attributes['payloadMetaSecondaryValue'] ?? '')));
    $link_url = trim((string) ($attributes['linkUrl'] ?? ''));
    $link_label = trim(sanitize_text_field((string) ($attributes['linkLabel'] ?? '')));
    $first_place = $places[0] ?? [];

    if ($payload_title === '') {
        $payload_title = trim((string) ($first_place['title'] ?? ''));
    }

    if ($payload_text === '') {
        $payload_text = trim((string) ($first_place['excerpt'] ?? ''));
    }

    if ($link_url === '') {
        $link_url = trim((string) ($first_place['permalink'] ?? ''));
    }

    if ($link_url === '') {
        $link_url = home_url('/schoneweide/');
    }

    if ($link_label === '') {
        $link_label = __('Zur Karte', 'iss-relations');
    }

    if (
        $media_url === ''
        && $payload_kicker === ''
        && $payload_title === ''
        && $payload_text === ''
        && $meta_primary_value === ''
        && $meta_secondary_value === ''
        && $link_url === ''
    ) {
        return '';
    }

    $out = '<article class="iss-card iss-card--flat iss-program-spine-row__payload">';

    if ($media_url !== '') {
        $out .= '<div class="iss-card__media iss-program-spine-row__payload-media"><img src="' . esc_url($media_url) . '" alt="' . esc_attr($media_alt) . '" loading="lazy" decoding="async"></div>';
    }

    $out .= '<div class="iss-card__body iss-program-spine-row__payload-body">';

    if ($payload_kicker !== '') {
        $out .= '<p class="iss-kicker iss-kicker--compact iss-program-spine-row__payload-kicker">' . esc_html($payload_kicker) . '</p>';
    }

    if ($payload_title !== '') {
        $out .= '<h3 class="iss-card__title iss-program-spine-row__payload-title">' . esc_html($payload_title) . '</h3>';
    }

    if ($payload_text !== '') {
        $out .= '<p class="iss-card__text iss-program-spine-row__payload-text">' . esc_html($payload_text) . '</p>';
    }

    if ($meta_primary_value !== '' || $meta_secondary_value !== '') {
        $out .= '<div class="iss-program-spine-row__payload-meta">';

        if ($meta_primary_value !== '') {
            $out .= '<div class="iss-program-spine-row__payload-meta-item">';
            if ($meta_primary_label !== '') {
                $out .= '<p class="iss-program-spine-row__payload-meta-label">' . esc_html($meta_primary_label) . '</p>';
            }
            $out .= '<p class="iss-program-spine-row__payload-meta-value">' . esc_html($meta_primary_value) . '</p>';
            $out .= '</div>';
        }

        if ($meta_secondary_value !== '') {
            $out .= '<div class="iss-program-spine-row__payload-meta-item">';
            if ($meta_secondary_label !== '') {
                $out .= '<p class="iss-program-spine-row__payload-meta-label">' . esc_html($meta_secondary_label) . '</p>';
            }
            $out .= '<p class="iss-program-spine-row__payload-meta-value">' . esc_html($meta_secondary_value) . '</p>';
            $out .= '</div>';
        }

        $out .= '</div>';
    }

    if ($link_url !== '') {
        $out .= '<div class="iss-card__footer iss-program-spine-row__payload-footer"><a class="iss-card__link" href="' . esc_url($link_url) . '">' . esc_html($link_label) . '</a></div>';
    }

    $out .= '</div></article>';

    return $out;
}

function iss_relations_render_spine_intro_text(string $text, string $attribution = '', string $mode = 'text'): string
{
    $lines = array_values(array_filter(
        array_map('trim', preg_split('/\R+/', trim($text)) ?: []),
        static fn($line) => $line !== ''
    ));

    if (!$lines) {
        return '';
    }

    $text_html = implode('<br>', array_map('esc_html', $lines));

    if ($mode !== 'quote') {
        return '<p class="iss-program-spine-row__text">' . $text_html . '</p>';
    }

    $attribution_lines = array_values(array_filter(
        array_map('trim', preg_split('/\R+/', trim($attribution)) ?: []),
        static fn($line) => $line !== ''
    ));
    $attribution_html = implode('<br>', array_map('esc_html', $attribution_lines));
    $out = '<figure class="iss-quote iss-quote--compact iss-quote--fit iss-program-spine-row__quote">';
    $out .= '<blockquote class="iss-quote__text"><p>' . $text_html . '</p></blockquote>';
    if ($attribution_html !== '') {
        $out .= '<figcaption class="iss-quote__cite">' . $attribution_html . '</figcaption>';
    }
    $out .= '</figure>';

    return $out;
}

function iss_relations_render_spine_strip_core(array $attributes, array $places, array $config): string
{
    $station_mode = iss_relations_normalize_atlas_strip_station_mode($attributes);
    $station_items = $station_mode === 'preset'
        ? iss_relations_get_atlas_strip_station_items($config, $places)
        : iss_relations_get_selected_spine_station_items($places, $config);
    if (!$station_items) {
        return '';
    }

    $stage_places = [];
    $places_by_id = [];

    foreach ($places as $place) {
        if (!is_array($place)) {
            continue;
        }

        $place_id = trim((string) ($place['place_id'] ?? ''));
        if ($place_id !== '') {
            $places_by_id[$place_id] = $place;
        }
    }

    foreach ($station_items as $station) {
        $source_place = $places_by_id[$station['id']] ?? [];
        $stage_places[] = [
            'place_id' => $station['id'],
            'title' => trim((string) ($source_place['title'] ?? $station['label'])),
            'label' => trim((string) ($source_place['label'] ?? '')),
            'permalink' => trim((string) ($source_place['permalink'] ?? '')),
        ];
    }

    $ratio_width = 2200;
    $ratio_height = 360;
    $theme = iss_relations_normalize_atlas_strip_theme($attributes);
    $line_mode = iss_relations_normalize_atlas_strip_line_mode($attributes);
    $show_map_markers = !array_key_exists('showMapMarkers', $attributes) || !empty($attributes['showMapMarkers']);

    if (function_exists('iss_frontend_static_maps_enqueue_image_viewport_assets')) {
        iss_frontend_static_maps_enqueue_image_viewport_assets();
    }
    $stage_html = iss_relations_render_atlas_slice_stage($stage_places, $config, [
        'class_name' => 'iss-program-spine-row__stage',
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
        'show_markers' => $show_map_markers,
        'line_mode' => $line_mode === 'none' ? 'none' : 'route',
    ]);
    $payload_html = !empty($attributes['_disablePayload'])
        ? ''
        : iss_relations_render_atlas_spine_payload($attributes, $places);
    $direction_label = trim(sanitize_text_field((string) ($attributes['directionLabel'] ?? '')));
    $direction_label_start = trim(sanitize_text_field((string) ($attributes['directionLabelStart'] ?? '')));
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? '')));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? '')));
    $text = trim((string) ($attributes['text'] ?? ''));
    $text_mode = iss_relations_normalize_spine_text_mode($attributes);
    $text_attribution = trim((string) ($attributes['textAttribution'] ?? ''));
    $classes = [
        'iss-program-spine-row',
        'iss-program-spine-row--theme-' . $theme,
    ];

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
        ])
        : 'class="' . esc_attr(implode(' ', $classes)) . '"';

    $out = '<article ' . $wrapper . '>';
    $out .= '<header class="iss-program-spine-row__intro">';
    if ($kicker !== '') {
        $out .= '<p class="iss-strip__eyebrow iss-program-spine-row__eyebrow">' . esc_html($kicker) . '</p>';
    }
    if ($title !== '') {
        $out .= '<h3 class="iss-program-spine-row__title">' . esc_html($title) . '</h3>';
    }
    if ($text !== '') {
        $out .= iss_relations_render_spine_intro_text($text, $text_attribution, $text_mode);
    }
    $out .= '</header>';
    $out .= '<div class="iss-program-spine-row__content">';
    $out .= '<div class="iss-program-spine-row__rail">';
    $out .= '<div class="iss-program-spine-row__stage-shell">';
    $out .= $stage_html;
    $out .= '<div class="iss-program-spine-row__overlay">';
    if ($line_mode !== 'none' || $direction_label !== '' || $direction_label_start !== '') {
        $out .= '<span class="iss-program-spine-row__baseline" aria-hidden="true"></span>';
    }
    if ($direction_label_start !== '') {
        $out .= '<span class="iss-program-spine-row__direction iss-program-spine-row__direction--start">' . esc_html($direction_label_start) . '</span>';
    }
    if ($direction_label !== '') {
        $out .= '<span class="iss-program-spine-row__direction">' . esc_html($direction_label) . '</span>';
    }
    $out .= '</div></div></div>';
    if ($payload_html !== '') {
        $out .= $payload_html;
    }
    $out .= '</div></article>';

    return $out;
}

function iss_relations_render_atlas_spine_block(array $attributes, array $places, array $config): string
{
    return iss_relations_render_spine_strip_core($attributes, $places, $config);
}

function iss_relations_render_spine_strip_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $attributes['_disablePayload'] = true;
    $places = iss_relations_collect_map_places($attributes, $block);
    if (!$places) {
        return '';
    }

    $preset = iss_relations_resolve_spine_strip_preset($attributes);
    $config = iss_relations_get_place_map_config($preset);

    if (function_exists('iss_frontend_render_spine_strip_block')) {
        return iss_frontend_render_spine_strip_block($attributes, $places, $config);
    }

    return iss_relations_render_spine_strip_core($attributes, $places, $config);
}

function iss_relations_normalize_asymmetric_split_layout_preset(array $attributes): string
{
    $preset = sanitize_key((string) ($attributes['layoutPreset'] ?? 'map-image-text'));

    return in_array($preset, ['map-image-text', 'text-map-image', 'map-text'], true) ? $preset : 'map-image-text';
}

function iss_relations_normalize_asymmetric_split_height_mode(array $attributes): string
{
    $mode = sanitize_key((string) ($attributes['heightMode'] ?? 'lg'));

    return in_array($mode, ['md', 'lg', 'xl'], true) ? $mode : 'lg';
}

function iss_relations_render_atlas_slice_copy_body(array $attributes, array $places): string
{
    $defaults = iss_relations_get_place_map_defaults();
    $first_place = $places[0] ?? [];
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? '')));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? '')));
    $text = trim((string) ($attributes['text'] ?? ''));
    $permalink = trim((string) ($first_place['permalink'] ?? ''));
    $meta = array_values(array_filter([
        trim((string) ($first_place['address'] ?? '')),
        trim((string) ($first_place['area'] ?? '')),
    ]));

    if ($kicker === '') {
        $kicker = trim((string) ($first_place['label'] ?? $defaults['kicker']));
    }

    if ($title === '') {
        $title = trim((string) ($first_place['title'] ?? $defaults['title']));
    }

    if ($text === '') {
        $text = trim((string) ($first_place['excerpt'] ?? ''));
    }

    if ($text === '') {
        $text = __('Gerahmter Atlasausschnitt mit automatisch gesetzten Ortsmarkern aus dem bestehenden JSON-Lookup.', 'iss-relations');
    }

    $link_url = trim((string) ($attributes['linkUrl'] ?? ''));
    $link_label = trim((string) ($attributes['linkLabel'] ?? ''));
    if ($link_url === '') {
        $link_url = $permalink !== '' ? $permalink : home_url('/schoneweide/');
    }
    $link_text = $link_label !== ''
        ? $link_label
        : ($permalink !== '' ? __('Zum Ort', 'iss-relations') : __('Zum Atlas', 'iss-relations'));

    $out = '<article class="iss-atlas-slice__body iss-atlas-slice__body--text">';
    if ($kicker !== '') {
        $out .= '<p class="iss-atlas-slice__eyebrow">' . esc_html($kicker) . '</p>';
    }
    if ($title !== '') {
        $out .= '<h3 class="iss-atlas-slice__title">' . esc_html($title) . '</h3>';
    }
    if ($meta) {
        $out .= '<p class="iss-atlas-slice__meta">' . esc_html(implode(' · ', $meta)) . '</p>';
    }
    if ($text !== '') {
        $out .= '<p class="iss-atlas-slice__text">' . esc_html($text) . '</p>';
    }
    $out .= '<p class="iss-atlas-slice__link"><a class="iss-action-link" href="' . esc_url($link_url) . '">' . esc_html($link_text) . '</a></p>';
    $out .= '</article>';

    return $out;
}

function iss_relations_render_atlas_slice_image_body(array $attributes): string
{
    $media_url = trim((string) ($attributes['mediaUrl'] ?? ''));
    if ($media_url === '') {
        return '';
    }

    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? '')));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? '')));
    $caption = trim((string) ($attributes['mediaCaption'] ?? ($attributes['text'] ?? '')));
    $alt = trim((string) ($attributes['mediaAlt'] ?? ''));

    $out = '<figure class="iss-atlas-slice__body iss-atlas-slice__body--image">';
    $out .= '<div class="iss-atlas-slice__media"><img src="' . esc_url($media_url) . '" alt="' . esc_attr($alt) . '" loading="lazy" decoding="async"></div>';

    if ($kicker !== '' || $title !== '' || $caption !== '') {
        $out .= '<figcaption class="iss-atlas-slice__caption">';
        if ($kicker !== '') {
            $out .= '<p class="iss-atlas-slice__eyebrow">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h3 class="iss-atlas-slice__title">' . esc_html($title) . '</h3>';
        }
        if ($caption !== '') {
            $out .= '<p class="iss-atlas-slice__text">' . esc_html($caption) . '</p>';
        }
        $out .= '</figcaption>';
    }

    $out .= '</figure>';

    return $out;
}

function iss_relations_render_atlas_slice_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $places = iss_relations_collect_map_places($attributes, $block);
    if (!$places) {
        return '';
    }

    $preset = iss_relations_resolve_place_map_preset($attributes);
    $config = iss_relations_get_place_map_config($preset);

    return function_exists('iss_frontend_render_atlas_slice_block')
        ? iss_frontend_render_atlas_slice_block($attributes, $places, $config)
        : '';
}

function iss_relations_render_atlas_strip_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $places = iss_relations_collect_map_places($attributes, $block);
    if (!$places) {
        return '';
    }

    $preset = iss_relations_resolve_place_map_preset($attributes);
    $config = iss_relations_get_place_map_config($preset);

    return function_exists('iss_frontend_render_atlas_strip_block')
        ? iss_frontend_render_atlas_strip_block($attributes, $places, $config)
        : '';
}

function iss_relations_render_asymmetric_split_field_text_zone(array $attributes, array $places): string
{
    $defaults = iss_relations_get_place_map_defaults();
    $first_place = $places[0] ?? [];
    $kicker = trim(sanitize_text_field((string) ($attributes['textKicker'] ?? '')));
    $title = trim(sanitize_text_field((string) ($attributes['textTitle'] ?? '')));
    $text = trim((string) ($attributes['textBody'] ?? ''));
    $link_url = trim((string) ($attributes['textLinkUrl'] ?? ''));
    $link_label = trim(sanitize_text_field((string) ($attributes['textLinkLabel'] ?? '')));
    $permalink = trim((string) ($first_place['permalink'] ?? ''));

    if ($kicker === '') {
        $kicker = trim((string) ($first_place['label'] ?? $defaults['kicker']));
    }

    if ($title === '') {
        $title = trim((string) ($first_place['title'] ?? $defaults['title']));
    }

    if ($text === '') {
        $text = trim((string) ($first_place['excerpt'] ?? ''));
    }

    if ($text === '') {
        $text = __('Flexible asymmetrische Felder mit Karte, Bild und Text als editoriale Komposition.', 'iss-relations');
    }

    if ($link_url === '') {
        $link_url = $permalink !== '' ? $permalink : home_url('/schoneweide/');
    }

    if ($link_label === '') {
        $link_label = $permalink !== '' ? __('Mehr erfahren', 'iss-relations') : __('Zum Atlas', 'iss-relations');
    }

    $out = '<article class="iss-atlas-slice__body iss-atlas-slice__body--text iss-asymmetric-split-field__zone iss-asymmetric-split-field__zone--text">';
    if ($kicker !== '') {
        $out .= '<p class="iss-atlas-slice__eyebrow">' . esc_html($kicker) . '</p>';
    }
    if ($title !== '') {
        $out .= '<h3 class="iss-atlas-slice__title">' . esc_html($title) . '</h3>';
    }
    if ($text !== '') {
        $out .= '<p class="iss-atlas-slice__text">' . esc_html($text) . '</p>';
    }
    $out .= '<p class="iss-atlas-slice__link"><a class="iss-action-link" href="' . esc_url($link_url) . '">' . esc_html($link_label) . '</a></p>';
    $out .= '</article>';

    return $out;
}

function iss_relations_render_asymmetric_split_field_image_zone(array $attributes): string
{
    $image_url = trim((string) ($attributes['imageUrl'] ?? ''));
    if ($image_url === '') {
        return '';
    }

    $alt = trim((string) ($attributes['imageAlt'] ?? ''));
    $caption = trim((string) ($attributes['imageCaption'] ?? ''));

    $out = '<figure class="iss-atlas-slice__body iss-atlas-slice__body--image iss-asymmetric-split-field__zone iss-asymmetric-split-field__zone--image" style="position:relative;display:block;overflow:hidden;padding:0;border-left-width:1px;">';
    $out .= '<div class="iss-atlas-slice__media" style="height:100%;aspect-ratio:auto;border-radius:0;"><img src="' . esc_url($image_url) . '" alt="' . esc_attr($alt) . '" loading="lazy" decoding="async"></div>';

    if ($caption !== '') {
        $out .= '<figcaption class="iss-atlas-slice__caption" style="position:absolute;left:1rem;bottom:1rem;z-index:2;max-width:18rem;padding:0.85rem 0.95rem 0.95rem;border:1px solid rgba(var(--iss-accent-rgb),0.14);border-radius:var(--iss-radius-sm);background:rgba(255,255,255,0.95);">';
        $out .= '<p class="iss-atlas-slice__text">' . esc_html($caption) . '</p>';
        $out .= '</figcaption>';
    }

    $out .= '</figure>';

    return $out;
}

function iss_relations_render_asymmetric_split_field_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $layout_preset = iss_relations_normalize_asymmetric_split_layout_preset($attributes);
    $height_mode = iss_relations_normalize_asymmetric_split_height_mode($attributes);
    $map_enabled = !isset($attributes['mapEnabled']) || !empty($attributes['mapEnabled']);
    $image_enabled = !empty($attributes['imageEnabled']);
    $text_enabled = !isset($attributes['textEnabled']) || !empty($attributes['textEnabled']);
    $places = iss_relations_collect_map_places($attributes, $block);

    if (!$places) {
        $map_enabled = false;
    }

    if (function_exists('iss_frontend_render_asymmetric_split_field_block')) {
        return iss_frontend_render_asymmetric_split_field_block($attributes, $places);
    }

    $map_zone = '';
    if ($map_enabled && $places) {
        $config = iss_relations_get_place_map_config('default');
        $ratio_by_height = [
            'md' => [760, 980],
            'lg' => [760, 1140],
            'xl' => [760, 1320],
        ];
        $ratio = $ratio_by_height[$height_mode] ?? $ratio_by_height['lg'];
        $map_zone = '<div class="iss-atlas-slice__map iss-asymmetric-split-field__zone iss-asymmetric-split-field__zone--map">'
            . iss_relations_render_atlas_slice_stage($places, $config, [
                'class_name' => 'iss-asymmetric-split-field__stage',
                'ratio_width' => $ratio[0],
                'ratio_height' => $ratio[1],
            ])
            . '</div>';
    }

    $image_zone = $image_enabled ? iss_relations_render_asymmetric_split_field_image_zone($attributes) : '';
    if ($image_zone === '') {
        $image_enabled = false;
    }

    $text_zone = $text_enabled ? iss_relations_render_asymmetric_split_field_text_zone($attributes, $places) : '';
    if ($text_zone === '') {
        $text_enabled = false;
    }

    $zones = [];
    $grid_template = 'minmax(7.4rem, 0.42fr) minmax(0, 1.05fr) minmax(15rem, 0.7fr)';

    switch ($layout_preset) {
        case 'text-map-image':
            $grid_template = 'minmax(15rem, 0.78fr) minmax(7.4rem, 0.42fr) minmax(0, 1.08fr)';
            if ($text_zone !== '') {
                $zones[] = $text_zone;
            }
            if ($map_zone !== '') {
                $zones[] = $map_zone;
            }
            if ($image_zone !== '') {
                $zones[] = $image_zone;
            }
            break;

        case 'map-text':
            $grid_template = 'minmax(7.4rem, 0.42fr) minmax(0, 1.58fr)';
            if ($map_zone !== '') {
                $zones[] = $map_zone;
            }
            if ($text_zone !== '') {
                $zones[] = $text_zone;
            }
            break;

        case 'map-image-text':
        default:
            if ($map_zone !== '') {
                $zones[] = $map_zone;
            }
            if ($image_zone !== '') {
                $zones[] = $image_zone;
            }
            if ($text_zone !== '') {
                $zones[] = $text_zone;
            }
            break;
    }

    $zones = array_values(array_filter($zones));
    if (!$zones) {
        return '';
    }

    $classes = [
        'iss-asymmetric-split-field',
        'iss-asymmetric-split-field--' . $layout_preset,
        'iss-asymmetric-split-field--' . $height_mode,
    ];

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
        ])
        : 'class="' . esc_attr(implode(' ', $classes)) . '"';

    $layout_style = 'display:grid;grid-template-columns:' . $grid_template . ';gap:clamp(0.9rem,1.8vw,1.35rem);align-items:stretch;';

    return '<div ' . $wrapper . '><div class="iss-asymmetric-split-field__layout" style="' . esc_attr($layout_style) . '">' . implode('', $zones) . '</div></div>';
}

function iss_relations_render_related_cards_block($attributes = [], $content = '', $block = null): string
{
    $data = iss_relations_collect_related_cards($attributes, $block);
    if (!$data) {
        return '';
    }

    $post_type = (string) $data['post_type'];
    $scope_key = (string) ($data['scope_key'] ?? $post_type);
    $skin = iss_relations_normalize_related_cards_skin((array) ($data['card_options'] ?? []));
    $wrapper_classes = [
        'iss-related-cards',
        'iss-related-cards--' . sanitize_html_class($scope_key),
        'iss-related-feed--skin-' . sanitize_html_class($skin),
    ];

    if ($skin !== 'default') {
        $wrapper_classes[] = 'iss-card-skin--' . sanitize_html_class($skin);
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $wrapper_classes),
        ])
        : 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '"';

    $out = '<div ' . $wrapper . '>';
    $out .= iss_relations_render_cards_grid($data['cards'], $scope_key, (array) ($data['card_options'] ?? []));
    $out .= '</div>';

    return $out;
}

function iss_relations_render_related_content_rail(array $data, array $attributes = []): string
{
    $posts = is_array($data['posts'] ?? null) ? $data['posts'] : [];
    if (!$posts) {
        return '';
    }

    $defaults = is_array($data['defaults'] ?? null) ? $data['defaults'] : [];
    $scope_key = (string) ($data['scope_key'] ?? $data['post_type'] ?? 'mixed');
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? ($defaults['kicker'] ?? ''))));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? ($defaults['title'] ?? ''))));
    $text = trim(sanitize_textarea_field((string) ($attributes['text'] ?? '')));
    $layout_variant = iss_relations_normalize_related_cards_layout_variant($attributes);
    $title_id = $title !== '' ? wp_unique_id('iss-related-network-title-') : '';
    $wrapper_classes = [
        'iss-related-network-group',
        'iss-related-network-group--' . sanitize_html_class($scope_key),
        'iss-related-network-group--layout-' . sanitize_html_class($layout_variant),
    ];
    $wrapper_attrs = [
        'class' => implode(' ', $wrapper_classes),
    ];

    if ($title_id !== '') {
        $wrapper_attrs['aria-labelledby'] = $title_id;
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes($wrapper_attrs)
        : 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '"' . ($title_id !== '' ? ' aria-labelledby="' . esc_attr($title_id) . '"' : '');

    $out = '<aside ' . $wrapper . '>';
    if ($kicker !== '' || $title !== '' || $text !== '') {
        $out .= '<div class="iss-related-network-group__head">';
        if ($kicker !== '') {
            $out .= '<p class="iss-related-network-group__kicker">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h3 class="iss-related-network-group__title" id="' . esc_attr($title_id) . '">' . esc_html($title) . '</h3>';
        }
        if ($text !== '') {
            $out .= '<p class="iss-related-network-group__text">' . esc_html($text) . '</p>';
        }
        $out .= '</div>';
    }

    $out .= '<ul class="iss-related-network-group__list">';
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $permalink = (string) get_permalink($post);
        $title_text = trim((string) get_the_title($post));
        if ($permalink === '' || $title_text === '') {
            continue;
        }

        $type_label = iss_relations_get_card_kicker($post, $defaults);
        $meta = array_values(array_filter([
            iss_relations_get_card_meta_line($post),
            iss_relations_get_card_detail_line($post),
        ]));

        $out .= '<li class="iss-related-network-group__item">';
        if ($layout_variant === 'register') {
            if ($type_label !== '') {
                $out .= '<span class="iss-related-network-group__type">' . esc_html($type_label) . '</span>';
            }
            $out .= '<a class="iss-related-network-group__link" href="' . esc_url($permalink) . '">' . esc_html($title_text) . '</a>';
            if ($meta) {
                $out .= '<span class="iss-related-network-group__meta">' . esc_html(implode(' · ', array_slice($meta, 0, 2))) . '</span>';
            }
        } else {
            $compact_meta = array_values(array_filter(array_merge([$type_label], $meta)));
            $out .= '<a class="iss-related-network-group__link" href="' . esc_url($permalink) . '">' . esc_html($title_text) . '</a>';
            if ($compact_meta) {
                $out .= '<span class="iss-related-network-group__meta">' . esc_html(implode(' · ', array_slice($compact_meta, 0, 2))) . '</span>';
            }
        }
        $out .= '</li>';
    }
    $out .= '</ul>';
    $out .= '</aside>';

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
    $scope_key = (string) ($data['scope_key'] ?? $post_type);
    $skin = iss_relations_normalize_related_cards_skin((array) ($data['card_options'] ?? []));
    $defaults = is_array($data['defaults']) ? $data['defaults'] : [];
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? ($defaults['kicker'] ?? ''))));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? ($defaults['title'] ?? ''))));
    $text = trim(sanitize_textarea_field((string) ($attributes['text'] ?? '')));
    $layout_variant = iss_relations_normalize_related_cards_layout_variant($attributes);

    if (in_array($layout_variant, ['rail', 'register'], true)) {
        return iss_relations_render_related_content_rail($data, $attributes);
    }

    $wrapper_classes = [
        'section',
        'section--plain',
        'iss-related-feed',
        'iss-related-feed--' . sanitize_html_class($scope_key),
        'iss-related-feed--skin-' . sanitize_html_class($skin),
    ];

    if ($skin !== 'default') {
        $wrapper_classes[] = 'iss-card-skin--' . sanitize_html_class($skin);
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $wrapper_classes),
        ])
        : 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '"';

    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';
    if ($kicker !== '' || $title !== '' || $text !== '') {
        $out .= '<div class="iss-heading iss-related-feed__intro">';
        if ($kicker !== '') {
            $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h2 class="iss-heading__title">' . esc_html($title) . '</h2>';
        }
        if ($text !== '') {
            $out .= '<p class="iss-related-feed__text">' . esc_html($text) . '</p>';
        }
        $out .= '</div>';
    }
    $out .= iss_relations_render_cards_grid($data['cards'], $scope_key, (array) ($data['card_options'] ?? []));
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_relations_render_related_place_links_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $current_post_id = isset($block->context['postId']) ? (int) $block->context['postId'] : (int) get_the_ID();
    $items = iss_relations_resolve_block_place_items($attributes, $current_post_id);
    if (!$items) {
        return '';
    }

    $per_page = max(1, min(12, absint($attributes['perPage'] ?? 6)));
    $items = array_slice($items, 0, $per_page);
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? '')));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? '')));
    $show_role = !isset($attributes['showRole']) || !empty($attributes['showRole']);
    $role_options = iss_relations_get_role_options();
    $title_id = $title !== '' ? wp_unique_id('iss-related-place-links-title-') : '';
    $wrapper_classes = ['iss-related-place-links'];

    $wrapper_attrs = [
        'class' => implode(' ', $wrapper_classes),
    ];

    if ($title_id !== '') {
        $wrapper_attrs['aria-labelledby'] = $title_id;
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes($wrapper_attrs)
        : 'class="' . esc_attr(implode(' ', $wrapper_classes)) . '"' . ($title_id !== '' ? ' aria-labelledby="' . esc_attr($title_id) . '"' : '');

    $out = '<aside ' . $wrapper . '>';
    if ($kicker !== '' || $title !== '') {
        $out .= '<div class="iss-related-place-links__head">';
        if ($kicker !== '') {
            $out .= '<p class="iss-related-place-links__kicker">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h3 class="iss-related-place-links__title" id="' . esc_attr($title_id) . '">' . esc_html($title) . '</h3>';
        }
        $out .= '</div>';
    }

    $out .= '<ul class="iss-related-place-links__list">';
    foreach ($items as $item) {
        $label = trim((string) ($item['label'] ?? ''));
        $title_text = trim((string) ($item['title'] ?? ''));
        $permalink = trim((string) ($item['permalink'] ?? ''));

        if ($permalink === '' || $title_text === '') {
            continue;
        }

        $role = sanitize_key((string) ($item['role'] ?? 'related'));
        $role_label = $show_role ? (string) ($role_options[$role] ?? '') : '';
        $out .= '<li class="iss-related-place-links__item">';
        $out .= '<a class="iss-related-place-links__link" href="' . esc_url($permalink) . '">' . esc_html($label !== '' ? $label : $title_text) . '</a>';
        if ($role_label !== '') {
            $out .= '<span class="iss-related-place-links__meta">' . esc_html($role_label) . '</span>';
        }
        $out .= '</li>';
    }
    $out .= '</ul>';
    $out .= '</aside>';

    return $out;
}

function iss_relations_render_related_place_map_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $shell_mode = sanitize_key((string) ($attributes['shellMode'] ?? 'section'));
    if (iss_relations_resolve_map_block_source($attributes, 'iss/related-place-map') === 'route') {
        return iss_relations_render_atlas_map_variant('tour-route', array_merge($attributes, [
            'shellMode' => $shell_mode,
        ]), $block);
    }

    $body_html = iss_relations_render_related_place_map_body($attributes, $block);
    if ($body_html === '') {
        return '';
    }

    if ($shell_mode === 'body') {
        return $body_html;
    }

    $defaults = iss_relations_get_place_map_defaults();
    $kicker = trim(sanitize_text_field((string) ($attributes['kicker'] ?? $defaults['kicker'])));
    $title = trim(sanitize_text_field((string) ($attributes['title'] ?? $defaults['title'])));
    $text = trim((string) ($attributes['text'] ?? ''));
    $link_text = (string) $defaults['link_text'];

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
    $out .= $body_html;
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_relations_render_atlas_map_variant(string $variant, array $attributes = [], $block = null, array $context = []): string
{
    $resolved_attributes = iss_relations_resolve_atlas_map_variant($attributes, $variant);
    $resolved_variant = sanitize_key((string) ($resolved_attributes['variant'] ?? $variant));
    if ($resolved_variant === 'tour-route') {
        iss_relations_enqueue_atlas_map_script();
    }

    $places = iss_relations_collect_map_places($resolved_attributes, $block, $context);
    if (!$places) {
        return '';
    }

    $preset = iss_relations_resolve_place_map_preset($resolved_attributes);
    $config = iss_relations_get_place_map_config($preset);
    $body_html = function_exists('iss_frontend_render_atlas_map_block')
        ? iss_frontend_render_atlas_map_block($resolved_attributes, $places, $config)
        : '';

    if ($body_html === '') {
        return '';
    }

    $shell_mode = sanitize_key((string) ($resolved_attributes['shellMode'] ?? 'body'));
    if ($shell_mode === 'body') {
        return $body_html;
    }

    $kicker = trim(sanitize_text_field((string) ($resolved_attributes['kicker'] ?? '')));
    $title = trim(sanitize_text_field((string) ($resolved_attributes['title'] ?? '')));
    $text = trim((string) ($resolved_attributes['text'] ?? ''));
    $link_url = trim((string) ($resolved_attributes['linkUrl'] ?? ''));
    $link_label = trim(sanitize_text_field((string) ($resolved_attributes['linkLabel'] ?? '')));
    $variant = sanitize_key((string) ($resolved_attributes['variant'] ?? 'place-locator'));
    $classes = [
        'section',
        'section--plain',
        'iss-gesture-atlas-map-section',
        'iss-gesture-atlas-map-section--variant-' . sanitize_html_class($variant),
    ];
    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
        ])
        : 'class="' . esc_attr(implode(' ', $classes)) . '"';
    $out = '<section ' . $wrapper . '>';
    $out .= '<div class="iss-container">';
    $out .= '<div class="iss-related-place-map__shell iss-gesture-atlas-map-section__shell">';

    if ($kicker !== '' || $title !== '' || $text !== '' || $link_url !== '') {
        $out .= '<div class="iss-heading iss-related-place-map__intro iss-gesture-atlas-map-section__intro">';
        if ($kicker !== '') {
            $out .= '<p class="iss-kicker iss-kicker--compact">' . esc_html($kicker) . '</p>';
        }
        if ($title !== '') {
            $out .= '<h2 class="iss-heading__title">' . esc_html($title) . '</h2>';
        }
        if ($text !== '') {
            $out .= '<p class="iss-heading__text">' . esc_html($text) . '</p>';
        }
        if ($link_url !== '') {
            $out .= '<p class="iss-related-place-map__cta iss-gesture-atlas-map-section__cta"><a class="iss-action-link" href="' . esc_url($link_url) . '">' . esc_html($link_label !== '' ? $link_label : __('Zum Atlas', 'iss-relations')) . '</a></p>';
        }
        $out .= '</div>';
    }

    $out .= $body_html;
    $out .= '</div>';
    $out .= '</div>';
    $out .= '</section>';

    return $out;
}

function iss_relations_render_atlas_map_block($attributes = [], $content = '', $block = null): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $variant = sanitize_key((string) ($attributes['variant'] ?? 'place-locator'));

    return iss_relations_render_atlas_map_variant($variant, $attributes, $block);
}
