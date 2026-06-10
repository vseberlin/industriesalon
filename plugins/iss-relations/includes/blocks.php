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
            'text' => [
                'type' => 'string',
                'default' => '',
            ],
            'postType' => [
                'type' => 'string',
                'default' => 'post',
            ],
            'postTypes' => [
                'type' => 'array',
                'default' => [],
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
            'layoutVariant' => [
                'type' => 'string',
                'default' => 'grid',
            ],
            'sortMode' => [
                'type' => 'string',
                'default' => 'auto',
            ],
            'columns' => [
                'type' => 'number',
            ],
            'showImage' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'showExcerpt' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'skin' => [
                'type' => 'string',
                'default' => 'default',
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
            'postTypes' => [
                'type' => 'array',
                'default' => [],
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
            'layoutVariant' => [
                'type' => 'string',
                'default' => 'grid',
            ],
            'sortMode' => [
                'type' => 'string',
                'default' => 'auto',
            ],
            'columns' => [
                'type' => 'number',
            ],
            'showImage' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'showExcerpt' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'skin' => [
                'type' => 'string',
                'default' => 'default',
            ],
        ],
        'supports' => [
            'html' => false,
        ],
        'render_callback' => 'iss_relations_render_related_cards_block',
    ]);

    register_block_type('iss/related-place-links', [
        'api_version' => 3,
        'title' => __('Related Place Links', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'location',
        'description' => __('Shows related places as a compact text list without cards or media.', 'iss-relations'),
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
            'source' => [
                'type' => 'string',
                'default' => 'current',
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
            'perPage' => [
                'type' => 'number',
                'default' => 6,
            ],
            'showRole' => [
                'type' => 'boolean',
                'default' => true,
            ],
        ],
        'supports' => [
            'html' => false,
        ],
        'render_callback' => 'iss_relations_render_related_place_links_block',
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
            'rotationDeg' => [
                'type' => 'number',
                'default' => 0,
            ],
            'biasX' => [
                'type' => 'number',
                'default' => 0,
            ],
            'biasY' => [
                'type' => 'number',
                'default' => 0,
            ],
            'mapScale' => [
                'type' => 'number',
                'default' => 1,
            ],
            'panelMode' => [
                'type' => 'string',
                'default' => 'show',
            ],
            'panelPosition' => [
                'type' => 'string',
                'default' => 'right',
            ],
            'shellMode' => [
                'type' => 'string',
                'default' => 'section',
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_relations_render_related_place_map_block',
    ]);

    register_block_type('iss/atlas-slice', [
        'api_version' => 3,
        'title' => __('Atlas Slice', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'images-alt2',
        'description' => __('Shows a cropped horizontal atlas slice with automatic place markers and an accompanying text or image panel.', 'iss-relations'),
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
            'mapPreset' => [
                'type' => 'string',
                'default' => 'atlas-slice',
            ],
            'framingMode' => [
                'type' => 'string',
                'default' => 'inherit',
            ],
            'rotationDeg' => [
                'type' => 'number',
                'default' => 0,
            ],
            'biasX' => [
                'type' => 'number',
                'default' => 0,
            ],
            'biasY' => [
                'type' => 'number',
                'default' => 0,
            ],
            'mapScale' => [
                'type' => 'number',
                'default' => 1,
            ],
            'bodyMode' => [
                'type' => 'string',
                'default' => 'text',
            ],
            'bodyPosition' => [
                'type' => 'string',
                'default' => 'end',
            ],
            'layoutMode' => [
                'type' => 'string',
                'default' => 'band',
            ],
            'mediaUrl' => [
                'type' => 'string',
                'default' => '',
            ],
            'mediaAlt' => [
                'type' => 'string',
                'default' => '',
            ],
            'mediaCaption' => [
                'type' => 'string',
                'default' => '',
            ],
            'linkUrl' => [
                'type' => 'string',
                'default' => '',
            ],
            'linkLabel' => [
                'type' => 'string',
                'default' => '',
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_relations_render_atlas_slice_block',
    ]);

    register_block_type('iss/atlas-strip', [
        'api_version' => 3,
        'title' => __('Atlas Strip', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'image-flip-horizontal',
        'description' => __('Shows a static-map editorial strip based on canonical map presets and shared marker JSON.', 'iss-relations'),
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
            'mapPreset' => [
                'type' => 'string',
                'default' => 'default',
            ],
            'framingMode' => [
                'type' => 'string',
                'default' => 'inherit',
            ],
            'rotationDeg' => [
                'type' => 'number',
                'default' => 0,
            ],
            'biasX' => [
                'type' => 'number',
                'default' => 0,
            ],
            'biasY' => [
                'type' => 'number',
                'default' => 0,
            ],
            'mapScale' => [
                'type' => 'number',
                'default' => 1,
            ],
            'variant' => [
                'type' => 'string',
                'default' => 'place',
            ],
            'stationMode' => [
                'type' => 'string',
                'default' => 'selected',
            ],
            'bodyPosition' => [
                'type' => 'string',
                'default' => 'end',
            ],
            'theme' => [
                'type' => 'string',
                'default' => 'black',
            ],
            'lineMode' => [
                'type' => 'string',
                'default' => 'route',
            ],
            'directionLabel' => [
                'type' => 'string',
                'default' => '',
            ],
            'textMode' => [
                'type' => 'string',
                'default' => 'text',
            ],
            'textAttribution' => [
                'type' => 'string',
                'default' => '',
            ],
            'showMapMarkers' => [
                'type' => 'boolean',
                'default' => false,
            ],
            'mediaUrl' => [
                'type' => 'string',
                'default' => '',
            ],
            'mediaAlt' => [
                'type' => 'string',
                'default' => '',
            ],
            'payloadKicker' => [
                'type' => 'string',
                'default' => '',
            ],
            'payloadTitle' => [
                'type' => 'string',
                'default' => '',
            ],
            'payloadText' => [
                'type' => 'string',
                'default' => '',
            ],
            'payloadMetaPrimaryLabel' => [
                'type' => 'string',
                'default' => '',
            ],
            'payloadMetaPrimaryValue' => [
                'type' => 'string',
                'default' => '',
            ],
            'payloadMetaSecondaryLabel' => [
                'type' => 'string',
                'default' => '',
            ],
            'payloadMetaSecondaryValue' => [
                'type' => 'string',
                'default' => '',
            ],
            'linkUrl' => [
                'type' => 'string',
                'default' => '',
            ],
            'linkLabel' => [
                'type' => 'string',
                'default' => '',
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_relations_render_atlas_strip_block',
    ]);

    register_block_type('iss/spine-strip', [
        'api_version' => 3,
        'title' => __('Spine Strip', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'minus',
        'description' => __('Shows a dedicated editorial rail with projected stops over a map strip.', 'iss-relations'),
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
                'default' => 6,
            ],
            'source' => [
                'type' => 'string',
                'default' => 'manual',
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
            'mapPreset' => [
                'type' => 'string',
                'default' => 'default',
            ],
            'rotationDeg' => [
                'type' => 'number',
                'default' => 0,
            ],
            'biasX' => [
                'type' => 'number',
                'default' => 0,
            ],
            'biasY' => [
                'type' => 'number',
                'default' => 0,
            ],
            'mapScale' => [
                'type' => 'number',
                'default' => 1,
            ],
            'stationMode' => [
                'type' => 'string',
                'default' => 'selected',
            ],
            'theme' => [
                'type' => 'string',
                'default' => 'black',
            ],
            'lineMode' => [
                'type' => 'string',
                'default' => 'none',
            ],
            'directionLabel' => [
                'type' => 'string',
                'default' => '',
            ],
            'textMode' => [
                'type' => 'string',
                'default' => 'text',
            ],
            'textAttribution' => [
                'type' => 'string',
                'default' => '',
            ],
            'showMapMarkers' => [
                'type' => 'boolean',
                'default' => false,
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_relations_render_spine_strip_block',
    ]);

    register_block_type('iss/dense-image-wall', [
        'api_version' => 3,
        'title' => __('Dense Image Wall', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'grid-view',
        'description' => __('Shows a structured editorial image wall with simple wide and tall cell variants.', 'iss-relations'),
        'editor_script' => 'iss-relations-related-blocks',
        'attributes' => [
            'columns' => [
                'type' => 'number',
                'default' => 4,
            ],
            'rowHeight' => [
                'type' => 'number',
                'default' => 118,
            ],
            'items' => [
                'type' => 'array',
                'default' => [],
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_relations_render_dense_image_wall_block',
    ]);

    register_block_type('iss/asymmetric-split-field', [
        'api_version' => 3,
        'title' => __('Asymmetric Split Field', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'columns',
        'description' => __('Shows an editorial asymmetric field with optional map, image, and text zones.', 'iss-relations'),
        'editor_script' => 'iss-relations-related-blocks',
        'attributes' => [
            'layoutPreset' => [
                'type' => 'string',
                'default' => 'map-image-text',
            ],
            'heightMode' => [
                'type' => 'string',
                'default' => 'lg',
            ],
            'source' => [
                'type' => 'string',
                'default' => 'current',
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
            'perPage' => [
                'type' => 'number',
                'default' => 3,
            ],
            'mapEnabled' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'imageEnabled' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'textEnabled' => [
                'type' => 'boolean',
                'default' => true,
            ],
            'imageUrl' => [
                'type' => 'string',
                'default' => '',
            ],
            'imageAlt' => [
                'type' => 'string',
                'default' => '',
            ],
            'imageCaption' => [
                'type' => 'string',
                'default' => '',
            ],
            'textKicker' => [
                'type' => 'string',
                'default' => '',
            ],
            'textTitle' => [
                'type' => 'string',
                'default' => '',
            ],
            'textBody' => [
                'type' => 'string',
                'default' => '',
            ],
            'textLinkUrl' => [
                'type' => 'string',
                'default' => '',
            ],
            'textLinkLabel' => [
                'type' => 'string',
                'default' => '',
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_relations_render_asymmetric_split_field_block',
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
            'wp-api-fetch',
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

function iss_relations_register_frontend_scripts(): void
{
    $script_path = ISS_RELATIONS_PATH . 'assets/related-strip.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_register_script(
        'iss-relations-related-strip',
        ISS_RELATIONS_URL . 'assets/related-strip.js',
        [],
        (string) filemtime($script_path),
        true
    );
}
add_action('init', 'iss_relations_register_frontend_scripts', 19);

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
            'relatedPostTypes' => iss_relations_get_related_query_post_types(),
            'mapPresets' => iss_relations_get_place_map_editor_presets(),
            'canManageEditorialSignals' => function_exists('iss_graph_current_user_can_edit_editorial_signals')
                ? iss_graph_current_user_can_edit_editorial_signals()
                : current_user_can('manage_options'),
        ]) . ';',
        'before'
    );
}
add_action('enqueue_block_editor_assets', 'iss_relations_enqueue_block_editor_script');

function iss_relations_enqueue_related_strip_script(): void
{
    if (is_admin()) {
        return;
    }

    if (!wp_script_is('iss-relations-related-strip', 'registered')) {
        return;
    }

    wp_enqueue_script('iss-relations-related-strip');
}

function iss_relations_get_related_content_defaults(string $post_type): array
{
    if ($post_type === iss_relations_get_place_post_type()) {
        return [
            'kicker' => __('Ort', 'iss-relations'),
            'title' => __('Verknüpfte Orte', 'iss-relations'),
            'link_text' => __('Weiter', 'iss-relations'),
        ];
    }

    switch ($post_type) {
        case 'fuehrung':
            return [
                'kicker' => __('Vor Ort', 'iss-relations'),
                'title' => __('Führungen zum Ort', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        case 'veranstaltung':
            return [
                'kicker' => __('Programm', 'iss-relations'),
                'title' => __('Veranstaltungen mit Ortsbezug', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        case 'publication':
            return [
                'kicker' => __('Publikation', 'iss-relations'),
                'title' => __('Publikationen mit Ortsbezug', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        case 'video':
            return [
                'kicker' => __('Video', 'iss-relations'),
                'title' => __('Videos mit Ortsbezug', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        case 'archivobjekt':
            return [
                'kicker' => __('Archivobjekt', 'iss-relations'),
                'title' => __('Material aus dem Archiv', 'iss-relations'),
                'link_text' => __('Ansehen', 'iss-relations'),
            ];
        case 'entity_profile':
            return [
                'kicker' => __('Profil', 'iss-relations'),
                'title' => __('Personen und Organisationen', 'iss-relations'),
                'link_text' => __('Zum Profil', 'iss-relations'),
            ];
        case 'post':
            return [
                'kicker' => __('Archiv', 'iss-relations'),
                'title' => __('Beiträge und Hintergründe', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
        default:
            return [
                'kicker' => __('Weiter entdecken', 'iss-relations'),
                'title' => __('Verwandte Inhalte', 'iss-relations'),
                'link_text' => __('Weiter', 'iss-relations'),
            ];
    }
}

function iss_relations_get_related_content_defaults_for_post_types(array $post_types): array
{
    $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));

    if (count($post_types) === 1) {
        return iss_relations_get_related_content_defaults($post_types[0]);
    }

    return [
        'kicker' => __('Weiter entdecken', 'iss-relations'),
        'title' => __('Verwandte Inhalte', 'iss-relations'),
        'link_text' => __('Weiter', 'iss-relations'),
    ];
}

function iss_relations_get_related_query_post_types(): array
{
    $post_types = iss_relations_get_supported_post_types();
    $archive_object_post_type = function_exists('iss_graph_get_archive_object_post_type')
        ? iss_graph_get_archive_object_post_type()
        : (defined('ISS_WF_IMPORT_OBJECT_POST_TYPE') ? (string) ISS_WF_IMPORT_OBJECT_POST_TYPE : 'archivobjekt');

    if ($archive_object_post_type !== '' && post_type_exists($archive_object_post_type)) {
        $post_types[] = $archive_object_post_type;
    }

    $profile_post_type = function_exists('iss_graph_get_entity_profile_post_type')
        ? iss_graph_get_entity_profile_post_type()
        : 'entity_profile';

    if ($profile_post_type !== '' && post_type_exists($profile_post_type)) {
        $post_types[] = $profile_post_type;
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));

    return (array) apply_filters('iss_relations_related_query_post_types', $post_types);
}

function iss_relations_is_related_query_post_type(string $post_type): bool
{
    return in_array(sanitize_key($post_type), iss_relations_get_related_query_post_types(), true);
}

function iss_relations_get_related_cards_post_types(array $attributes = [], string $fallback = 'post'): array
{
    $requested = [];

    if (isset($attributes['postTypes']) && is_array($attributes['postTypes'])) {
        $requested = $attributes['postTypes'];
    } elseif (!empty($attributes['postType'])) {
        $requested = [$attributes['postType']];
    } elseif ($fallback !== '') {
        $requested = [$fallback];
    }

    $post_types = [];

    foreach ($requested as $post_type) {
        $post_type = sanitize_key((string) $post_type);

        if ($post_type === '' || !iss_relations_is_related_query_post_type($post_type)) {
            continue;
        }

        $post_types[] = $post_type;
    }

    if (!$post_types) {
        $fallback = sanitize_key($fallback);

        if ($fallback !== '' && iss_relations_is_related_query_post_type($fallback)) {
            $post_types[] = $fallback;
        }
    }

    return array_values(array_unique($post_types));
}

function iss_relations_get_related_cards_scope_key(array $post_types): string
{
    $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));

    if (count($post_types) === 1) {
        return $post_types[0];
    }

    return 'mixed';
}

function iss_relations_normalize_related_cards_layout_variant(array $attributes = []): string
{
    $variant = sanitize_key((string) ($attributes['layoutVariant'] ?? 'grid'));
    $allowed = ['grid', 'stack', 'compact', 'strip', 'rail'];

    return in_array($variant, $allowed, true) ? $variant : 'grid';
}

function iss_relations_normalize_related_cards_skin(array $attributes = []): string
{
    $value = sanitize_key((string) ($attributes['skin'] ?? 'default'));
    $allowed = ['default', 'exhibition'];

    return in_array($value, $allowed, true) ? $value : 'default';
}

function iss_relations_normalize_related_cards_sort_mode(array $attributes = [], $post_types = []): string
{
    $value = sanitize_key((string) ($attributes['sortMode'] ?? 'auto'));
    $allowed = ['auto', 'relevance', 'relevance_title', 'relevance_date'];

    if (!in_array($value, $allowed, true)) {
        $value = 'auto';
    }

    if ($value !== 'auto') {
        return $value;
    }

    if (!is_array($post_types)) {
        $post_types = [$post_types];
    }

    $post_types = array_values(array_filter(array_map('sanitize_key', $post_types)));

    if (count($post_types) > 1) {
        return array_intersect($post_types, ['veranstaltung', 'fuehrung']) ? 'relevance_date' : 'relevance_title';
    }

    $post_type = $post_types ? $post_types[0] : '';

    if (in_array($post_type, ['ausstellung', 'publication', 'video', 'projekt', 'page', 'post'], true)) {
        return 'relevance_title';
    }

    if (in_array($post_type, ['veranstaltung', 'fuehrung'], true)) {
        return 'relevance_date';
    }

    return 'relevance';
}

function iss_relations_normalize_related_cards_columns(array $attributes = []): int
{
    $columns = isset($attributes['columns']) ? absint($attributes['columns']) : 0;

    if ($columns >= 1) {
        return max(1, min(6, $columns));
    }

    return 3;
}

function iss_relations_is_entity_related_source(string $source): bool
{
    return in_array(sanitize_key($source), ['entity', 'entity_place', 'entity_person', 'entity_organization'], true);
}

function iss_relations_get_entity_related_source_families(string $source): array
{
    switch (sanitize_key($source)) {
        case 'entity_place':
            return ['place'];
        case 'entity_person':
            return ['person'];
        case 'entity_organization':
            return ['organization'];
        case 'entity':
        default:
            return ['place', 'person', 'organization'];
    }
}

function iss_relations_get_graph_entity_for_related_post(int $post_id): ?array
{
    if ($post_id <= 0 || !iss_relations_graph_is_available()) {
        return null;
    }

    if (function_exists('iss_graph_get_entity_for_post')) {
        $entity = iss_graph_get_entity_for_post($post_id);
        if (is_array($entity) && !empty($entity['id'])) {
            return $entity;
        }
    }

    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return null;
    }

    if ($post->post_type === (function_exists('iss_graph_get_archive_object_post_type') ? iss_graph_get_archive_object_post_type() : 'archivobjekt') && function_exists('iss_graph_sync_archive_object_entity')) {
        $entity = iss_graph_sync_archive_object_entity($post_id);
        return is_array($entity) && !empty($entity['id']) ? $entity : null;
    }

    if (iss_relations_is_supported_post_type((string) $post->post_type)) {
        $entity = iss_relations_sync_post_graph($post_id);
        return is_array($entity) && !empty($entity['id']) ? $entity : null;
    }

    return null;
}

function iss_relations_collect_entity_related_seed_ids(array $entity, array $relation_families): array
{
    $entity_id = (int) ($entity['id'] ?? 0);
    if ($entity_id <= 0 || !$relation_families) {
        return [];
    }

    $relation_families = array_values(array_unique(array_filter(array_map('sanitize_key', $relation_families))));
    if (!$relation_families) {
        return [];
    }

    $seed_ids = [];
    $entity_kind = sanitize_key((string) ($entity['entity_kind'] ?? ''));
    if (in_array($entity_kind, $relation_families, true)) {
        $seed_ids[] = $entity_id;
    }

    $service = iss_graph_get_service();
    foreach ($relation_families as $relation_family) {
        foreach ($service->get_relations_for_entity($entity_id, $relation_family, [
            'public_only' => true,
            'limit' => 500,
        ]) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $related_entity_id = (int) ($row['entity_id'] ?? 0);
            if ($related_entity_id > 0) {
                $seed_ids[] = $related_entity_id;
            }
        }
    }

    return array_values(array_unique(array_filter(array_map('intval', $seed_ids))));
}

function iss_relations_query_direct_seed_posts(array $seed_entity_ids, array $post_types, int $per_page, int $exclude_post_id = 0): array
{
    if (!$seed_entity_ids || !$post_types || !iss_relations_graph_is_available()) {
        return [];
    }

    global $wpdb;

    $service = iss_graph_get_service();
    $entity_table = $service->get_entity_table_name();
    $entity_placeholders = implode(', ', array_fill(0, count($seed_entity_ids), '%d'));
    $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
    $params = array_merge($seed_entity_ids, $post_types);
    $post_id_sql = 'COALESCE(e.post_id, e.profile_post_id)';

    $sql = "SELECT DISTINCT {$post_id_sql} AS post_id
        FROM {$entity_table} e
        INNER JOIN {$wpdb->posts} p
            ON p.ID = {$post_id_sql}
        WHERE e.id IN ({$entity_placeholders})
          AND {$post_id_sql} IS NOT NULL
          AND p.post_type IN ({$type_placeholders})
          AND p.post_status = 'publish'";

    if ($exclude_post_id > 0) {
        $sql .= " AND {$post_id_sql} <> %d";
        $params[] = $exclude_post_id;
    }

    $sql .= ' ORDER BY p.menu_order ASC, p.post_date_gmt DESC, p.ID DESC LIMIT %d';
    $params[] = max(1, min(24, $per_page));

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Related blocks query plugin-owned graph tables through a prepared dynamic table query and cache the final post list per request.
    $post_ids = $wpdb->get_col($wpdb->prepare($sql, $params));

    return array_values(array_unique(array_filter(array_map('intval', is_array($post_ids) ? $post_ids : []))));
}

function iss_relations_query_entity_related_post_ids(array $seed_entity_ids, array $relation_families, array $post_types, int $per_page, int $exclude_post_id = 0, array $attributes = []): array
{
    if (!$seed_entity_ids || !$relation_families || !$post_types || !iss_relations_graph_is_available()) {
        return [];
    }

    global $wpdb;

    $sort_mode = iss_relations_normalize_related_cards_sort_mode($attributes, $post_types);
    $service = iss_graph_get_service();
    $relation_table = $service->get_relation_table_name();
    $entity_table = $service->get_entity_table_name();
    $family_placeholders = implode(', ', array_fill(0, count($relation_families), '%s'));
    $seed_placeholders = implode(', ', array_fill(0, count($seed_entity_ids), '%d'));
    $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
    $post_id_sql = 'COALESCE(e.post_id, e.profile_post_id)';
    $rank_sql = iss_relations_graph_relation_rank_sql('r');
    $params = array_merge($relation_families, $seed_entity_ids, $post_types);

    $sql = "SELECT
            {$post_id_sql} AS post_id,
            MAX({$rank_sql}) AS relation_rank,
            MAX(p.post_date_gmt) AS latest_post_date,
            MAX(p.post_title) AS post_title
        FROM {$relation_table} r
        INNER JOIN {$entity_table} e
            ON e.id = r.from_entity_id
        INNER JOIN {$wpdb->posts} p
            ON p.ID = {$post_id_sql}
        WHERE r.relation_family IN ({$family_placeholders})
          AND r.to_entity_id IN ({$seed_placeholders})
          AND r.is_public = 1
          AND {$post_id_sql} IS NOT NULL
          AND p.post_type IN ({$type_placeholders})
          AND p.post_status = 'publish'";

    if ($exclude_post_id > 0) {
        $sql .= " AND {$post_id_sql} <> %d";
        $params[] = $exclude_post_id;
    }

    $sql .= ' GROUP BY post_id ORDER BY relation_rank DESC';

    if ($sort_mode === 'relevance_title') {
        $sql .= ' , post_title ASC, post_id DESC';
    } elseif ($sort_mode === 'relevance_date' || $sort_mode === 'auto') {
        $sql .= ' , latest_post_date DESC, post_id DESC';
    } else {
        $sql .= ' , post_id DESC';
    }

    $sql .= ' LIMIT %d';
    $params[] = max(1, min(24, $per_page));

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Related blocks query plugin-owned graph tables through a prepared dynamic table query and cache the final post list per request.
    $post_ids = $wpdb->get_col($wpdb->prepare($sql, $params));

    return array_values(array_unique(array_filter(array_map('intval', is_array($post_ids) ? $post_ids : []))));
}

function iss_relations_query_entity_related_posts(int $current_post_id, array $post_types, int $per_page, array $attributes = []): array
{
    static $cache = [];

    $source = sanitize_key((string) ($attributes['source'] ?? 'entity'));
    if (!iss_relations_is_entity_related_source($source) || $current_post_id <= 0 || !$post_types) {
        return [];
    }

    $relation_families = iss_relations_get_entity_related_source_families($source);
    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'iss_relations_is_related_query_post_type')));
    if (!$relation_families || !$post_types) {
        return [];
    }

    $cache_key = md5(wp_json_encode([
        'current_post_id' => $current_post_id,
        'post_types' => $post_types,
        'per_page' => $per_page,
        'source' => $source,
        'sort_mode' => $attributes['sortMode'] ?? 'auto',
    ]));

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $entity = iss_relations_get_graph_entity_for_related_post($current_post_id);
    if (!$entity || empty($entity['id'])) {
        $cache[$cache_key] = [];
        return [];
    }

    $seed_entity_ids = iss_relations_collect_entity_related_seed_ids($entity, $relation_families);
    if (!$seed_entity_ids) {
        $cache[$cache_key] = [];
        return [];
    }

    $limit = max(1, min(24, $per_page));
    $direct_post_ids = iss_relations_query_direct_seed_posts($seed_entity_ids, $post_types, $limit, $current_post_id);
    $related_post_ids = iss_relations_query_entity_related_post_ids($seed_entity_ids, $relation_families, $post_types, $limit, $current_post_id, $attributes);
    $post_ids = array_values(array_unique(array_merge($direct_post_ids, $related_post_ids)));
    $post_ids = array_slice($post_ids, 0, $limit);

    if (!$post_ids) {
        $cache[$cache_key] = [];
        return [];
    }

    $cache[$cache_key] = get_posts([
        'post_type' => $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'post__in',
        'post__in' => $post_ids,
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
    ]);

    return $cache[$cache_key];
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
        'address' => trim((string) get_post_meta($place_id, 'address', true)),
        'area' => trim((string) get_post_meta($place_id, 'area', true)),
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

    if ($source === 'related') {
        return $current_post_id > 0 ? iss_relations_get_related_place_items($current_post_id) : [];
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

function iss_relations_query_related_posts(array $place_ids, $post_types, int $per_page, int $exclude_post_id = 0, array $attributes = []): array
{
    static $cache = [];
    $post_types = is_array($post_types) ? $post_types : [$post_types];
    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'iss_relations_is_supported_post_type')));
    $sort_mode = iss_relations_normalize_related_cards_sort_mode($attributes, $post_types);

    if (!$post_types) {
        return [];
    }

    $cache_key = md5(wp_json_encode([
        'place_ids' => array_values(array_map('intval', $place_ids)),
        'post_types' => $post_types,
        'per_page' => $per_page,
        'exclude_post_id' => $exclude_post_id,
        'sort_mode' => $sort_mode,
    ]));

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    if (iss_relations_graph_is_available()) {
        global $wpdb;

        $place_entity_ids = [];
        foreach ($place_ids as $place_id) {
            $place_entity = iss_relations_graph_find_place_entity((int) $place_id);
            if (is_array($place_entity) && !empty($place_entity['id'])) {
                $place_entity_ids[] = (int) $place_entity['id'];
            }
        }

        $place_entity_ids = array_values(array_unique(array_filter($place_entity_ids)));

        if ($place_entity_ids) {
            $service = iss_graph_get_service();
            $relation_table = $service->get_relation_table_name();
            $entity_table = $service->get_entity_table_name();
            $type_placeholders = implode(', ', array_fill(0, count($post_types), '%s'));
            $placeholders = implode(', ', array_fill(0, count($place_entity_ids), '%d'));
            $params = array_merge(['place'], $post_types, $place_entity_ids);
            $limit = max(1, min(24, $per_page));
            $rank_sql = iss_relations_graph_relation_rank_sql('r');

            $sql = "SELECT
                    e.post_id,
                    MAX({$rank_sql}) AS relation_rank,
                    MAX(p.post_date_gmt) AS latest_post_date
                FROM {$relation_table} r
                INNER JOIN {$entity_table} e
                    ON e.id = r.from_entity_id
                INNER JOIN {$wpdb->posts} p
                    ON p.ID = e.post_id
                WHERE r.relation_family = %s
                  AND r.is_public = 1
                  AND p.post_type IN ({$type_placeholders})
                  AND p.post_status = 'publish'
                  AND e.post_id > 0
                  AND r.to_entity_id IN ({$placeholders})";

            if ($exclude_post_id > 0) {
                $sql .= ' AND e.post_id <> %d';
                $params[] = $exclude_post_id;
            }

            $sql .= ' GROUP BY e.post_id ORDER BY relation_rank DESC';

            if ($sort_mode === 'relevance_date') {
                $sql .= ' , latest_post_date DESC, e.post_id DESC';
            } elseif ($sort_mode === 'relevance_title') {
                $sql .= ' , p.post_title ASC, e.post_id DESC';
            } else {
                $sql .= ' , e.post_id DESC';
            }

            $sql .= ' LIMIT %d';
            $params[] = $limit;

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Existing related blocks query plugin-owned graph tables through a prepared dynamic table query and cache the final post list per request.
            $post_ids = $wpdb->get_col($wpdb->prepare($sql, $params));
            $post_ids = array_values(array_unique(array_filter(array_map('intval', is_array($post_ids) ? $post_ids : []))));

            if ($post_ids) {
                $cache[$cache_key] = get_posts([
                    'post_type' => $post_types,
                    'post_status' => 'publish',
                    'posts_per_page' => $limit,
                    'orderby' => 'post__in',
                    'post__in' => $post_ids,
                    'suppress_filters' => true,
                    'ignore_sticky_posts' => true,
                ]);

                return $cache[$cache_key];
            }
        }
    }

    if (count($post_types) === 1 && $post_types[0] === 'archivobjekt' && function_exists('iss_wf_import_get_relation_service')) {
        $post_ids = [];

        foreach ($place_ids as $place_id) {
            $post_ids = array_merge($post_ids, iss_wf_import_get_relation_service()->get_object_post_ids_for_local_place((int) $place_id));
        }

        $post_ids = array_values(array_unique(array_filter(array_map('intval', $post_ids))));
        if ($exclude_post_id > 0) {
            $post_ids = array_values(array_diff($post_ids, [$exclude_post_id]));
        }

        if ($post_ids) {
                $cache[$cache_key] = get_posts([
                    'post_type' => $post_types[0],
                    'post_status' => 'publish',
                    'posts_per_page' => max(1, min(24, $per_page)),
                    'orderby' => 'date',
                    'order' => 'DESC',
                'suppress_filters' => true,
                'ignore_sticky_posts' => true,
                'post__in' => $post_ids,
            ]);

            return $cache[$cache_key];
        }
    }

    $term_ids = iss_relations_get_place_term_ids($place_ids);
    if (!$term_ids) {
        return [];
    }

    $args = [
        'post_type' => count($post_types) === 1 ? $post_types[0] : $post_types,
        'post_status' => 'publish',
        'posts_per_page' => max(1, min(24, $per_page)),
        'orderby' => $sort_mode === 'relevance_title' ? 'title' : 'date',
        'order' => $sort_mode === 'relevance_title' ? 'ASC' : 'DESC',
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
        // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Legacy taxonomy read is the fallback when graph relations are unavailable.
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

    $cache[$cache_key] = get_posts($args);

    return $cache[$cache_key];
}

function iss_relations_resolve_block_posts(array $attributes, int $current_post_id, array $post_types, int $per_page): array
{
    $source = sanitize_key((string) ($attributes['source'] ?? 'current'));
    if (iss_relations_is_entity_related_source($source)) {
        return iss_relations_query_entity_related_posts($current_post_id, $post_types, $per_page, $attributes);
    }

    $place_items = iss_relations_resolve_block_place_items($attributes, $current_post_id);
    if (!$place_items) {
        return [];
    }

    $place_post_type = iss_relations_get_place_post_type();
    $include_places = in_array($place_post_type, $post_types, true);
    $content_post_types = array_values(array_diff($post_types, [$place_post_type]));
    $posts = [];

    if ($include_places) {
        foreach ($place_items as $item) {
            $place = $item['post'] ?? null;
            if ($place instanceof WP_Post) {
                $posts[] = $place;
            }
        }
    }

    if (!$content_post_types) {
        return array_slice($posts, 0, $per_page);
    }

    $place_ids = array_values(array_filter(array_map(static function (array $item): int {
        return (int) ($item['place_id'] ?? 0);
    }, $place_items)));

    if (!$place_ids) {
        return [];
    }

    $queried_posts = iss_relations_query_related_posts(
        $place_ids,
        $content_post_types,
        $per_page,
        ($current_post_id > 0 && in_array((string) get_post_type($current_post_id), $content_post_types, true)) ? $current_post_id : 0,
        $attributes
    );

    if (!$posts) {
        return $queried_posts;
    }

    $seen_ids = [];
    $merged = [];

    foreach (array_merge($posts, $queried_posts) as $post) {
        if (!$post instanceof WP_Post || isset($seen_ids[$post->ID])) {
            continue;
        }

        $seen_ids[$post->ID] = true;
        $merged[] = $post;
    }

    return array_slice($merged, 0, $per_page);
}

function iss_relations_should_apply_related_editorial_signals(array $attributes, int $current_post_id): bool
{
    if ($current_post_id <= 0 || !function_exists('iss_graph_get_active_editorial_signals_for_post')) {
        return false;
    }

    $source = sanitize_key((string) ($attributes['source'] ?? 'current'));

    return $source !== 'manual';
}

function iss_relations_get_related_editorial_signals(int $current_post_id): array
{
    if ($current_post_id <= 0 || !function_exists('iss_graph_get_active_editorial_signals_for_post')) {
        return [];
    }

    return iss_graph_get_active_editorial_signals_for_post($current_post_id, 'related');
}

function iss_relations_get_related_editorial_signals_by_target(int $current_post_id): array
{
    $by_target = [];

    foreach (iss_relations_get_related_editorial_signals($current_post_id) as $signal) {
        if (!is_array($signal)) {
            continue;
        }

        $target_post_id = absint($signal['target_post_id'] ?? 0);
        if ($target_post_id <= 0 || $target_post_id === $current_post_id) {
            continue;
        }

        $by_target[$target_post_id] = $signal;
    }

    return $by_target;
}

function iss_relations_get_promoted_related_posts(array $post_types, int $per_page, int $exclude_post_id = 0): array
{
    if (!function_exists('iss_graph_get_active_related_promotion_post_ids')) {
        return [];
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'iss_relations_is_related_query_post_type')));
    if (!$post_types) {
        return [];
    }

    $limit = max(1, min(24, $per_page));
    $post_ids = iss_graph_get_active_related_promotion_post_ids($post_types, [
        'exclude_post_id' => $exclude_post_id,
        'post_status' => 'publish',
        'limit' => $limit,
    ]);

    if (!$post_ids) {
        return [];
    }

    return get_posts([
        'post_type' => count($post_types) === 1 ? $post_types[0] : $post_types,
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'orderby' => 'post__in',
        'post__in' => $post_ids,
        'suppress_filters' => true,
        'ignore_sticky_posts' => true,
    ]);
}

function iss_relations_apply_editorial_signals_to_related_posts(array $posts, int $current_post_id, array $post_types, int $per_page, array $attributes = []): array
{
    $limit = max(1, min(24, $per_page));
    if (!iss_relations_should_apply_related_editorial_signals($attributes, $current_post_id)) {
        return array_slice($posts, 0, $limit);
    }

    $post_types = array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'iss_relations_is_related_query_post_type')));
    if (!$post_types) {
        return [];
    }

    $signals = iss_relations_get_related_editorial_signals($current_post_id);
    $promoted_posts = iss_relations_get_promoted_related_posts($post_types, $limit, $current_post_id);
    if (!$signals && !$promoted_posts) {
        return array_slice($posts, 0, $limit);
    }

    $allowed_post_types = array_fill_keys($post_types, true);
    $feature_ids = [];
    $hide_ids = [];

    foreach ($signals as $signal) {
        if (!is_array($signal)) {
            continue;
        }

        $target_post_id = absint($signal['target_post_id'] ?? 0);
        if ($target_post_id <= 0 || $target_post_id === $current_post_id) {
            continue;
        }

        $signal_type = sanitize_key((string) ($signal['signal'] ?? ''));
        if ($signal_type === 'feature') {
            $feature_ids[] = $target_post_id;
        } elseif ($signal_type === 'hide') {
            $hide_ids[$target_post_id] = true;
        }
    }

    foreach ($promoted_posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $feature_ids[] = (int) $post->ID;
    }

    $feature_ids = array_values(array_unique(array_filter($feature_ids)));
    $feature_lookup = array_fill_keys($feature_ids, true);
    $featured_posts = [];
    $seen = [];

    foreach ($feature_ids as $target_post_id) {
        if (isset($hide_ids[$target_post_id])) {
            continue;
        }

        $post = get_post($target_post_id);
        if (
            !$post instanceof WP_Post
            || $post->post_status !== 'publish'
            || empty($allowed_post_types[(string) $post->post_type])
        ) {
            continue;
        }

        $seen[$post->ID] = true;
        $featured_posts[] = $post;
    }

    $automatic_posts = [];
    foreach ($posts as $post) {
        if (!$post instanceof WP_Post || isset($hide_ids[$post->ID]) || isset($feature_lookup[$post->ID]) || isset($seen[$post->ID])) {
            continue;
        }

        $seen[$post->ID] = true;
        $automatic_posts[] = $post;
    }

    return array_slice(array_merge($featured_posts, $automatic_posts), 0, $limit);
}

function iss_relations_prepare_editorial_signal_preview_items(int $current_post_id): array
{
    $items = [];

    foreach (iss_relations_get_related_editorial_signals($current_post_id) as $signal) {
        if (!is_array($signal)) {
            continue;
        }

        $target_post_id = absint($signal['target_post_id'] ?? 0);
        $post = $target_post_id > 0 ? get_post($target_post_id) : null;
        if (!$post instanceof WP_Post || $target_post_id === $current_post_id) {
            continue;
        }

        $items[] = [
            'targetPostId' => $target_post_id,
            'signal' => sanitize_key((string) ($signal['signal'] ?? '')),
            'reason' => (string) ($signal['reason'] ?? ''),
            'expiresAt' => isset($signal['expires_at']) && $signal['expires_at'] !== null ? substr((string) $signal['expires_at'], 0, 10) : '',
            'target' => [
                'id' => $target_post_id,
                'title' => (string) get_the_title($post),
                'postType' => (string) $post->post_type,
                'permalink' => (string) get_permalink($post),
                'kicker' => iss_relations_get_card_kicker($post),
            ],
        ];
    }

    return $items;
}

function iss_relations_get_card_meta_line(WP_Post $post): string
{
    if ($post->post_type === 'publication' && function_exists('iss_publications_get_card_meta_items')) {
        $items = array_values(array_filter((array) iss_publications_get_card_meta_items((int) $post->ID), static function ($item) {
            return is_scalar($item) && trim((string) $item) !== '';
        }));

        if ($items) {
            return implode(' · ', array_slice(array_map(static function ($item) {
                return trim((string) $item);
            }, $items), 0, 2));
        }
    }

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

function iss_relations_get_card_kicker(WP_Post $post, array $defaults = []): string
{
    if ($post->post_type === iss_relations_get_place_post_type()) {
        return __('Ort', 'iss-relations');
    }

    if ($post->post_type === 'fuehrung') {
        $badge = trim((string) get_post_meta((int) $post->ID, 'tour_badge', true));
        if ($badge !== '') {
            return $badge;
        }

        return __('Führung', 'iss-relations');
    }

    if ($post->post_type === 'veranstaltung') {
        return __('Veranstaltung', 'iss-relations');
    }

    if ($post->post_type === 'ausstellung') {
        return __('Ausstellung', 'iss-relations');
    }

    if ($post->post_type === 'publication' && function_exists('iss_publications_get_card_kicker')) {
        $kicker = trim((string) iss_publications_get_card_kicker((int) $post->ID));
        if ($kicker !== '') {
            return $kicker;
        }
    }

    if ($post->post_type === 'publication') {
        return __('Publikation', 'iss-relations');
    }

    if ($post->post_type === 'video') {
        return __('Video', 'iss-relations');
    }

    if ($post->post_type === 'archivobjekt') {
        return __('Archivobjekt', 'iss-relations');
    }

    if ($post->post_type === 'projekt') {
        return __('Projekt', 'iss-relations');
    }

    if ($post->post_type === (function_exists('iss_graph_get_entity_profile_post_type') ? iss_graph_get_entity_profile_post_type() : 'entity_profile')) {
        return __('Profil', 'iss-relations');
    }

    if ($post->post_type === 'post') {
        return __('Beitrag', 'iss-relations');
    }

    if ($post->post_type === 'page') {
        return __('Seite', 'iss-relations');
    }

    if ($post->post_type === 'archivbeitrag') {
        return __('Archiv', 'iss-relations');
    }

    return trim((string) ($defaults['kicker'] ?? ''));
}

function iss_relations_get_card_detail_line(WP_Post $post): string
{
    if ($post->post_type !== 'fuehrung') {
        return '';
    }

    if (function_exists('iss_fuehrung_get_next_event')) {
        $next_event = iss_fuehrung_get_next_event((int) $post->ID);
        if ($next_event instanceof WP_Post && function_exists('iss_fuehrung_get_event_start_label')) {
            return __('Nächster Termin:', 'iss-relations') . ' ' . iss_fuehrung_get_event_start_label((int) $next_event->ID);
        }
    }

    if (!function_exists('iss_fuehrung_get_effective_booking_mode')) {
        return '';
    }

    $mode = iss_fuehrung_get_effective_booking_mode((int) $post->ID);

    if ($mode === 'on_demand') {
        return __('Status: Auf Anfrage buchbar', 'iss-relations');
    }

    if ($mode === 'hybrid') {
        return __('Status: Aktuell keine Termine online · Anfrage möglich', 'iss-relations');
    }

    return __('Status: Aktuell keine Termine online', 'iss-relations');
}

function iss_relations_get_card_excerpt(WP_Post $post, int $max_words = 22): string
{
    if ($post->post_type === iss_relations_get_place_post_type()) {
        foreach (['current_use', 'history_short'] as $meta_key) {
            $value = trim(wp_strip_all_tags((string) get_post_meta((int) $post->ID, $meta_key, true)));
            if ($value !== '') {
                return wp_trim_words($value, $max_words, '…');
            }
        }
    }

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
            'crop_mode' => 'dynamic',
            'rotation_deg' => 0,
            'viewport' => [
                'scale_x' => 1,
                'scale_y' => 1,
                'offset_x' => 0,
                'offset_y' => 0,
            ],
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
        $viewport = is_array($preset_config['viewport'] ?? null) ? $preset_config['viewport'] : [];
        $scale = isset($viewport['scale']) && is_numeric($viewport['scale']) ? (float) $viewport['scale'] : 1.0;
        $scale_x = isset($viewport['scale_x']) && is_numeric($viewport['scale_x']) ? (float) $viewport['scale_x'] : $scale;
        $scale_y = isset($viewport['scale_y']) && is_numeric($viewport['scale_y']) ? (float) $viewport['scale_y'] : $scale;
        $offset_x = isset($viewport['offset_x']) && is_numeric($viewport['offset_x']) ? (float) $viewport['offset_x'] : 0.0;
        $offset_y = isset($viewport['offset_y']) && is_numeric($viewport['offset_y']) ? (float) $viewport['offset_y'] : 0.0;
        $crop_mode = sanitize_key((string) ($preset_config['crop_mode'] ?? 'dynamic'));

        $normalized[$preset] = [
            'label' => trim((string) ($preset_config['label'] ?? $preset)),
            'image_url' => isset($preset_config['image_url']) ? (string) $preset_config['image_url'] : '',
            'markers_path' => isset($preset_config['markers_path']) ? (string) $preset_config['markers_path'] : '',
            'image_alt' => isset($preset_config['image_alt']) ? (string) $preset_config['image_alt'] : __('Übersichtskarte des Schöneweide-Atlas.', 'iss-relations'),
            'width' => $width,
            'height' => $height,
            'crop_mode' => $crop_mode === 'fixed' ? 'fixed' : 'dynamic',
            'rotation_deg' => iss_relations_normalize_map_rotation_degrees($preset_config['rotation_deg'] ?? 0),
            'viewport' => [
                'scale_x' => $scale_x > 0 ? $scale_x : 1.0,
                'scale_y' => $scale_y > 0 ? $scale_y : 1.0,
                'offset_x' => $offset_x,
                'offset_y' => $offset_y,
            ],
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
        'crop_mode' => 'dynamic',
        'rotation_deg' => 0,
        'viewport' => [
            'scale_x' => 1,
            'scale_y' => 1,
            'offset_x' => 0,
            'offset_y' => 0,
        ],
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
    $presets = iss_relations_get_place_map_presets();
    $preferred = 'viewport-industrial-spine-strip';

    if (isset($presets[$preferred]) && !empty((array) (($presets[$preferred]['rail']['stations'] ?? [])))) {
        return $preferred;
    }

    foreach ($presets as $preset => $config) {
        if (!empty((array) (($config['rail']['stations'] ?? [])))) {
            return (string) $preset;
        }
    }

    return iss_relations_resolve_place_map_preset($attributes);
}

function iss_relations_normalize_map_rotation_degrees($value): float
{
    if (!is_numeric($value)) {
        return 0.0;
    }

    $rotation = fmod((float) $value, 360.0);

    if ($rotation > 180.0) {
        $rotation -= 360.0;
    }

    if ($rotation <= -180.0) {
        $rotation += 360.0;
    }

    return round($rotation, 3);
}

function iss_relations_get_map_rotation_degrees(array $attributes = [], array $config = []): float
{
    $attribute_rotation = $attributes['rotationDeg'] ?? null;
    $rotation = $attribute_rotation !== null
        ? $attribute_rotation
        : ($config['rotation_deg'] ?? 0);

    return iss_relations_normalize_map_rotation_degrees($rotation);
}

function iss_relations_normalize_map_framing_mode(array $attributes = []): string
{
    $mode = sanitize_key((string) ($attributes['framingMode'] ?? 'inherit'));

    return in_array($mode, ['inherit', 'preset', 'auto'], true) ? $mode : 'inherit';
}

function iss_relations_get_map_plane_bias(array $attributes = []): array
{
    $bias_x = is_numeric($attributes['biasX'] ?? null) ? (float) $attributes['biasX'] : 0.0;
    $bias_y = is_numeric($attributes['biasY'] ?? null) ? (float) $attributes['biasY'] : 0.0;

    return [
        'x' => max(-25.0, min(25.0, round($bias_x, 3))),
        'y' => max(-25.0, min(25.0, round($bias_y, 3))),
    ];
}

function iss_relations_normalize_map_plane_scale($value): float
{
    $scale = is_numeric($value) ? (float) $value : 1.0;

    return max(0.9, min(1.25, round($scale, 3)));
}

function iss_relations_get_map_plane_scale(array $attributes = []): float
{
    return iss_relations_normalize_map_plane_scale($attributes['mapScale'] ?? 1.0);
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

function iss_relations_get_map_stage_rotation_class(): string
{
    return 'is-map-rotation';
}

function iss_relations_get_map_rotation_fit_scale(float $rotation_deg, int $ratio_width, int $ratio_height): float
{
    if (abs($rotation_deg) < 0.001) {
        return 1.0;
    }

    // For free-angle editorial strips we want the map to fill the stage and be
    // clipped by the frame, not shrink into a floating rotated rectangle.
    if (abs(fmod(abs($rotation_deg), 90.0)) > 0.001) {
        return 1.0;
    }

    $width = max(1.0, (float) $ratio_width);
    $height = max(1.0, (float) $ratio_height);
    $theta = deg2rad($rotation_deg);
    $cos = abs(cos($theta));
    $sin = abs(sin($theta));
    $bbox_width = ($width * $cos) + ($height * $sin);
    $bbox_height = ($width * $sin) + ($height * $cos);

    if ($bbox_width <= 0.0 || $bbox_height <= 0.0) {
        return 1.0;
    }

    return min($width / $bbox_width, $height / $bbox_height);
}

function iss_relations_project_map_plane_point(float $x, float $y, float $rotation_deg, float $rotation_scale, float $bias_x = 0.0, float $bias_y = 0.0): array
{
    $theta = deg2rad($rotation_deg);
    $dx = ($x - 50.0) * $rotation_scale;
    $dy = ($y - 50.0) * $rotation_scale;

    $projected_x = 50.0 + (($dx * cos($theta)) - ($dy * sin($theta))) + $bias_x;
    $projected_y = 50.0 + (($dx * sin($theta)) + ($dy * cos($theta))) + $bias_y;

    return [
        'x' => $projected_x,
        'y' => $projected_y,
    ];
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

function iss_relations_render_place_map_stage(array $places, array $config, array $options = []): string
{
    $image_url = $config['image_url'] ?? '';
    $image_alt = $config['image_alt'] ?? '';
    $marker_lookup = iss_relations_get_place_map_marker_lookup((string) ($config['markers_path'] ?? ''));
    $rotation_deg = iss_relations_normalize_map_rotation_degrees($options['rotation_deg'] ?? ($config['rotation_deg'] ?? 0));

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

    $ratio_width = max(1, absint($options['ratio_width'] ?? ($config['width'] ?? 0)));
    $ratio_height = max(1, absint($options['ratio_height'] ?? ($config['height'] ?? 0)));
    $plane_bias = [
        'x' => is_numeric($options['bias_x'] ?? null) ? (float) $options['bias_x'] : 0.0,
        'y' => is_numeric($options['bias_y'] ?? null) ? (float) $options['bias_y'] : 0.0,
    ];

    if ($ratio_width > 0 && $ratio_height > 0) {
        $stage_styles[] = '--iss-related-place-map-ratio:' . $ratio_width . ' / ' . $ratio_height;
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

    $stage_classes = ['iss-related-place-map__stage'];
    $extra_class = trim((string) ($options['class_name'] ?? ''));
    if ($extra_class !== '') {
        foreach (preg_split('/\s+/', $extra_class) as $class_name) {
            $class_name = sanitize_html_class($class_name);
            if ($class_name !== '') {
                $stage_classes[] = $class_name;
            }
        }
    }

    $plane_scale = iss_relations_normalize_map_plane_scale($options['map_scale'] ?? 1.0);
    $rotation_scale = iss_relations_get_map_rotation_fit_scale($rotation_deg, $ratio_width, $ratio_height) * $plane_scale;
    $plane_classes = [
        'iss-related-place-map__plane',
        iss_relations_get_map_stage_rotation_class(),
    ];
    $plane_attr = ' style="' . esc_attr(implode(';', [
        '--iss-map-rotation-deg:' . number_format($rotation_deg, 3, '.', '') . 'deg',
        '--iss-map-rotation-scale:' . number_format($rotation_scale, 6, '.', ''),
        '--iss-map-bias-x:' . number_format($plane_bias['x'], 3, '.', '') . '%',
        '--iss-map-bias-y:' . number_format($plane_bias['y'], 3, '.', '') . '%',
    ])) . '"';

    return '<div class="' . esc_attr(implode(' ', $stage_classes)) . '"' . $stage_attr . '><div class="' . esc_attr(implode(' ', $plane_classes)) . '"' . $plane_attr . '><div class="iss-related-place-map__viewport"><img class="iss-related-place-map__image" src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" loading="lazy" decoding="async"></div><div class="iss-related-place-map__markers">' . $markers . '</div></div></div>';
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

function iss_relations_render_related_place_map_body(array $attributes, $block = null): string
{
    $places = iss_relations_collect_map_places($attributes, $block);
    if (!$places) {
        return '';
    }

    $preset = iss_relations_resolve_place_map_preset($attributes);
    $config = iss_relations_get_place_map_config($preset);
    $panel_mode = iss_relations_normalize_place_map_panel_mode($attributes);
    $panel_position = iss_relations_normalize_place_map_panel_position($attributes);
    $rotation_deg = iss_relations_get_map_rotation_degrees($attributes, $config);
    $plane_bias = iss_relations_get_map_plane_bias($attributes);
    $plane_scale = iss_relations_get_map_plane_scale($attributes);
    $body_classes = ['iss-related-place-map__body', 'iss-related-place-map__body--panel-' . $panel_mode];

    if ($panel_mode === 'show') {
        $body_classes[] = 'iss-related-place-map__body--panel-' . $panel_position;
    }

    $out = '<div class="' . esc_attr(implode(' ', $body_classes)) . '">';
    $out .= iss_relations_render_place_map_stage($places, $config, [
        'rotation_deg' => $rotation_deg,
        'bias_x' => $plane_bias['x'],
        'bias_y' => $plane_bias['y'],
        'map_scale' => $plane_scale,
    ]);
    if ($panel_mode === 'show') {
        $out .= iss_relations_render_place_map_panel($places);
    }
    $out .= '</div>';

    return $out;
}

function iss_relations_clamp_float(float $value, float $min, float $max): float
{
    if ($value < $min) {
        return $min;
    }

    if ($value > $max) {
        return $max;
    }

    return $value;
}

function iss_relations_collect_mapped_places(array $places, array $config): array
{
    $marker_lookup = iss_relations_get_place_map_marker_lookup((string) ($config['markers_path'] ?? ''));
    if (!$marker_lookup) {
        return [];
    }

    $mapped_places = [];

    foreach ($places as $index => $place) {
        $position = iss_relations_get_place_map_marker_position($place, $marker_lookup);
        if ($position === null) {
            continue;
        }

        $mapped_places[] = [
            'index' => (int) $index,
            'place' => $place,
            'position' => [
                'x' => (float) $position['x'],
                'y' => (float) $position['y'],
            ],
        ];
    }

    return $mapped_places;
}

function iss_relations_get_atlas_slice_focus_window(array $mapped_places, array $config, int $ratio_width, int $ratio_height): array
{
    $source_width = max(1, absint($config['width'] ?? 4096));
    $source_height = max(1, absint($config['height'] ?? 2389));
    $stage_ratio = $ratio_width / max(1, $ratio_height);
    $source_ratio = $source_width / $source_height;
    $source_target_ratio = $stage_ratio / max(0.0001, $source_ratio);

    $xs = [];
    $ys = [];

    foreach ($mapped_places as $item) {
        $xs[] = (float) ($item['position']['x'] ?? 0.0);
        $ys[] = (float) ($item['position']['y'] ?? 0.0);
    }

    $x_min = min($xs);
    $x_max = max($xs);
    $y_min = min($ys);
    $y_max = max($ys);

    $bbox_width = max(2.0, $x_max - $x_min);
    $bbox_height = max(2.0, $y_max - $y_min);
    $margin_x = 7.5;
    $margin_y = 9.5;
    $window_width = $bbox_width + ($margin_x * 2);
    $window_height = $bbox_height + ($margin_y * 2);
    $min_window_height = count($mapped_places) === 1 ? 34.0 : 28.0;
    $min_window_width = $min_window_height * $source_target_ratio;

    if (($window_width / $window_height) < $source_target_ratio) {
        $window_width = $window_height * $source_target_ratio;
    } else {
        $window_height = $window_width / max(0.0001, $source_target_ratio);
    }

    $window_width = max($window_width, $min_window_width);
    $window_height = max($window_height, $min_window_height);

    if ($window_width > 100.0) {
        $window_width = 100.0;
        $window_height = $window_width / max(0.0001, $source_target_ratio);
    }

    if ($window_height > 100.0) {
        $window_height = 100.0;
        $window_width = min(100.0, $window_height * $source_target_ratio);
    }

    $center_x = ($x_min + $x_max) / 2;
    $center_y = ($y_min + $y_max) / 2;
    $start_x = iss_relations_clamp_float($center_x - ($window_width / 2), 0.0, 100.0 - $window_width);
    $start_y = iss_relations_clamp_float($center_y - ($window_height / 2), 0.0, 100.0 - $window_height);

    return [
        'x' => $start_x,
        'y' => $start_y,
        'width' => $window_width,
        'height' => $window_height,
    ];
}

function iss_relations_render_atlas_slice_stage(array $places, array $config, array $options = []): string
{
    $mapped_places = iss_relations_collect_mapped_places($places, $config);
    $rotation_deg = iss_relations_normalize_map_rotation_degrees($options['rotation_deg'] ?? ($config['rotation_deg'] ?? 0));
    if (!$mapped_places) {
        return '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl sind noch keine Koordinaten hinterlegt.', 'iss-relations') . '</div>';
    }

    $image_url = (string) ($config['image_url'] ?? '');
    if ($image_url === '') {
        return '<div class="iss-related-place-map__empty">' . esc_html__('Für diese Auswahl ist noch keine Atlaskarte hinterlegt.', 'iss-relations') . '</div>';
    }

    $image_alt = (string) ($config['image_alt'] ?? '');
    $ratio_width = max(1, absint($options['ratio_width'] ?? 1600));
    $ratio_height = max(1, absint($options['ratio_height'] ?? 720));
    $plane_bias = [
        'x' => is_numeric($options['bias_x'] ?? null) ? (float) $options['bias_x'] : 0.0,
        'y' => is_numeric($options['bias_y'] ?? null) ? (float) $options['bias_y'] : 0.0,
    ];
    $crop_mode = sanitize_key((string) ($config['crop_mode'] ?? 'dynamic'));
    $show_markers = !array_key_exists('show_markers', $options) || !empty($options['show_markers']);

    if ($crop_mode === 'fixed') {
        $window = [
            'x' => 0.0,
            'y' => 0.0,
            'width' => 100.0,
            'height' => 100.0,
        ];
        $image_width = 100.0;
        $image_height = 100.0;
        $image_left = 0.0;
        $image_top = 0.0;
    } else {
        $window = iss_relations_get_atlas_slice_focus_window($mapped_places, $config, $ratio_width, $ratio_height);
        $image_width = 10000 / max(0.001, $window['width']);
        $image_height = 10000 / max(0.001, $window['height']);
        $image_left = -($window['x'] / max(0.001, $window['width'])) * 100;
        $image_top = -($window['y'] / max(0.001, $window['height'])) * 100;
    }
    $stage_classes = ['iss-atlas-slice__stage'];
    $extra_class = trim((string) ($options['class_name'] ?? ''));

    if ($extra_class !== '') {
        foreach (preg_split('/\s+/', $extra_class) as $class_name) {
            $class_name = sanitize_html_class($class_name);
            if ($class_name !== '') {
                $stage_classes[] = $class_name;
            }
        }
    }

    $stage_attr = ' style="' . esc_attr(implode(';', [
        '--iss-atlas-slice-ratio:' . $ratio_width . ' / ' . $ratio_height,
        '--iss-atlas-slice-image-width:' . number_format($image_width, 4, '.', '') . '%',
        '--iss-atlas-slice-image-height:' . number_format($image_height, 4, '.', '') . '%',
        '--iss-atlas-slice-image-left:' . number_format($image_left, 4, '.', '') . '%',
        '--iss-atlas-slice-image-top:' . number_format($image_top, 4, '.', '') . '%',
    ])) . '"';

    $markers = '';

    if ($show_markers) {
        foreach ($mapped_places as $item) {
            $raw_x = (float) ($item['position']['x'] ?? 0.0);
            $raw_y = (float) ($item['position']['y'] ?? 0.0);
            if ($crop_mode === 'fixed') {
                $marker_x = $raw_x;
                $marker_y = $raw_y;
            } else {
                $marker_x = (($raw_x - $window['x']) / max(0.001, $window['width'])) * 100;
                $marker_y = (($raw_y - $window['y']) / max(0.001, $window['height'])) * 100;
            }
            $place = $item['place'];
            $index = (int) $item['index'];
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
    }

    $plane_scale = iss_relations_normalize_map_plane_scale($options['map_scale'] ?? 1.0);
    $rotation_scale = iss_relations_get_map_rotation_fit_scale($rotation_deg, $ratio_width, $ratio_height) * $plane_scale;
    $plane_classes = [
        'iss-atlas-slice__plane',
        iss_relations_get_map_stage_rotation_class(),
    ];
    $plane_attr = ' style="' . esc_attr(implode(';', [
        '--iss-map-rotation-deg:' . number_format($rotation_deg, 3, '.', '') . 'deg',
        '--iss-map-rotation-scale:' . number_format($rotation_scale, 6, '.', ''),
        '--iss-map-bias-x:' . number_format($plane_bias['x'], 3, '.', '') . '%',
        '--iss-map-bias-y:' . number_format($plane_bias['y'], 3, '.', '') . '%',
    ])) . '"';

    return '<div class="' . esc_attr(implode(' ', $stage_classes)) . '"' . $stage_attr . '><div class="' . esc_attr(implode(' ', $plane_classes)) . '"' . $plane_attr . '><div class="iss-atlas-slice__viewport"><img class="iss-atlas-slice__image" src="' . esc_url($image_url) . '" alt="' . esc_attr($image_alt) . '" loading="lazy" decoding="async"><div class="iss-atlas-slice__markers">' . $markers . '</div></div></div></div>';
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

    $stage_config = $config;
    $stage_config['crop_mode'] = 'dynamic';
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
    $mapped_stage_places = iss_relations_collect_mapped_places($stage_places, $stage_config);
    if (!$mapped_stage_places) {
        return '';
    }

    $theme = iss_relations_normalize_atlas_strip_theme($attributes);
    $line_mode = iss_relations_normalize_atlas_strip_line_mode($attributes);
    $rotation_deg = iss_relations_get_map_rotation_degrees($attributes, $config);
    $plane_bias = iss_relations_get_map_plane_bias($attributes);
    $plane_scale = iss_relations_get_map_plane_scale($attributes);
    $rail = iss_relations_get_atlas_strip_rail($config);
    $station_offset_x = (float) ($rail['station_offset_x'] ?? 0.0);
    $rotation_scale = iss_relations_get_map_rotation_fit_scale($rotation_deg, $ratio_width, $ratio_height) * $plane_scale;
    $window = iss_relations_get_atlas_slice_focus_window($mapped_stage_places, $stage_config, $ratio_width, $ratio_height);
    $stations_by_id = [];

    foreach ($station_items as $station) {
        $station_x = (($station['position']['x'] - $window['x']) / max(0.001, $window['width'])) * 100;
        $station_y = (($station['position']['y'] - $window['y']) / max(0.001, $window['height'])) * 100;
        $projected = iss_relations_project_map_plane_point(
            $station_x,
            $station_y,
            $rotation_deg,
            $rotation_scale,
            $plane_bias['x'],
            $plane_bias['y']
        );

        $stations_by_id[$station['id']] = [
            'id' => $station['id'],
            'label' => $station['label'],
            'is_active' => !empty($station['is_active']),
            'x' => iss_relations_clamp_float((float) $projected['x'] + $station_offset_x, 0.0, 100.0),
            'y' => iss_relations_clamp_float((float) $projected['y'], 0.0, 100.0),
            'label_position' => 'below',
        ];
    }

    $station_label_order = array_keys($stations_by_id);
    usort($station_label_order, static function ($left, $right) use ($stations_by_id): int {
        return ($stations_by_id[$left]['x'] ?? 0.0) <=> ($stations_by_id[$right]['x'] ?? 0.0);
    });

    foreach ($station_label_order as $label_index => $station_id) {
        $stations_by_id[$station_id]['label_position'] = ($label_index % 2 === 0) ? 'below' : 'above';
    }

    $route_ids = [];

    foreach ($places as $place) {
        $place_id = trim((string) ($place['place_id'] ?? ''));
        if ($place_id !== '' && isset($stations_by_id[$place_id])) {
            $route_ids[] = $place_id;
        }
    }

    $segment = null;

    if ($line_mode === 'corridor' && count($station_items) >= 2) {
        $first_station = $stations_by_id[$station_items[0]['id']] ?? null;
        $last_station = $stations_by_id[$station_items[count($station_items) - 1]['id']] ?? null;
        if ($first_station && $last_station) {
            $segment = [
                'start' => min($first_station['x'], $last_station['x']),
                'end' => max($first_station['x'], $last_station['x']),
            ];
        }
    } elseif ($line_mode === 'route' && count($route_ids) >= 2) {
        $first_route = $stations_by_id[$route_ids[0]] ?? null;
        $last_route = $stations_by_id[$route_ids[count($route_ids) - 1]] ?? null;
        if ($first_route && $last_route) {
            $segment = [
                'start' => min($first_route['x'], $last_route['x']),
                'end' => max($first_route['x'], $last_route['x']),
            ];
        }
    }

    $stage_html = iss_relations_render_atlas_slice_stage($stage_places, $stage_config, [
        'class_name' => 'iss-program-spine-row__stage',
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
        'show_markers' => !empty($attributes['showMapMarkers']),
        'rotation_deg' => $rotation_deg,
        'bias_x' => $plane_bias['x'],
        'bias_y' => $plane_bias['y'],
        'map_scale' => $plane_scale,
    ]);
    $payload_html = !empty($attributes['_disablePayload'])
        ? ''
        : iss_relations_render_atlas_spine_payload($attributes, $places);
    $direction_label = trim(sanitize_text_field((string) ($attributes['directionLabel'] ?? '')));
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
    $out .= '<span class="iss-program-spine-row__baseline" aria-hidden="true"></span>';
    if ($segment && $segment['end'] > $segment['start']) {
        $out .= '<span class="iss-program-spine-row__segment" style="left:' . esc_attr(number_format($segment['start'], 3, '.', '')) . '%;width:' . esc_attr(number_format($segment['end'] - $segment['start'], 3, '.', '')) . '%" aria-hidden="true"></span>';
    }
    foreach ($station_items as $station) {
        $station_render = $stations_by_id[$station['id']] ?? null;
        if (!$station_render) {
            continue;
        }

        $station_classes = ['iss-program-spine-row__station'];
        if ($station_render['is_active']) {
            $station_classes[] = 'is-active';
        }
        $station_classes[] = 'is-label-' . sanitize_html_class((string) ($station_render['label_position'] ?? 'below'));
        if ((float) ($station_render['x'] ?? 0.0) <= 16.0) {
            $station_classes[] = 'is-label-edge-start';
        } elseif ((float) ($station_render['x'] ?? 0.0) >= 84.0) {
            $station_classes[] = 'is-label-edge-end';
        }

        $station_style = '--x:' . esc_attr(number_format($station_render['x'], 3, '.', '')) . '%';

        $out .= '<span class="' . esc_attr(implode(' ', $station_classes)) . '" style="' . $station_style . '">';
        $out .= '<span class="iss-program-spine-row__station-dot" aria-hidden="true"></span>';
        $out .= '<span class="iss-program-spine-row__station-label">' . esc_html($station_render['label']) . '</span>';
        $out .= '</span>';
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
    $body_mode = iss_relations_normalize_atlas_slice_body_mode($attributes);
    $body_position = iss_relations_normalize_atlas_slice_body_position($attributes);
    $layout_mode = iss_relations_normalize_atlas_slice_layout_mode($attributes);
    $framing_mode = iss_relations_normalize_map_framing_mode($attributes);
    $rotation_deg = iss_relations_get_map_rotation_degrees($attributes, $config);
    $plane_bias = iss_relations_get_map_plane_bias($attributes);
    $plane_scale = iss_relations_get_map_plane_scale($attributes);
    $body_html = $body_mode === 'image'
        ? iss_relations_render_atlas_slice_image_body($attributes)
        : '';

    if ($body_html === '') {
        $body_mode = 'text';
        $body_html = iss_relations_render_atlas_slice_copy_body($attributes, $places);
    }

    $ratio_width = $layout_mode === 'split' ? 760 : 1600;
    $ratio_height = $layout_mode === 'split' ? 1140 : 720;
    if ($framing_mode === 'inherit') {
        $framing_mode = $layout_mode === 'split' ? 'auto' : 'preset';
    }

    $stage_options = [
        'class_name' => 'iss-atlas-slice__stage--' . $layout_mode,
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
        'rotation_deg' => $rotation_deg,
        'bias_x' => $plane_bias['x'],
        'bias_y' => $plane_bias['y'],
        'map_scale' => $plane_scale,
    ];

    if ($framing_mode === 'preset') {
        $stage_options['class_name'] = 'iss-atlas-slice__stage iss-atlas-slice__stage--' . $layout_mode;
        $stage_html = iss_relations_render_place_map_stage($places, $config, $stage_options);
    } else {
        $stage_html = iss_relations_render_atlas_slice_stage($places, $config, $stage_options);
    }

    $classes = [
        'iss-atlas-slice',
        'iss-atlas-slice--layout-' . $layout_mode,
        'iss-atlas-slice--body-' . $body_mode,
        'iss-atlas-slice--body-' . $body_position,
        'iss-atlas-slice--framing-' . $framing_mode,
    ];

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
        ])
        : 'class="' . esc_attr(implode(' ', $classes)) . '"';

    $map_shell = '<div class="iss-atlas-slice__map">' . $stage_html . '</div>';
    $parts = $body_position === 'start'
        ? [$body_html, $map_shell]
        : [$map_shell, $body_html];

    return '<div ' . $wrapper . '>' . implode('', $parts) . '</div>';
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
    $variant = iss_relations_normalize_atlas_strip_variant($attributes);
    $framing_mode = iss_relations_normalize_map_framing_mode($attributes);
    $rotation_deg = iss_relations_get_map_rotation_degrees($attributes, $config);
    $plane_bias = iss_relations_get_map_plane_bias($attributes);
    $plane_scale = iss_relations_get_map_plane_scale($attributes);

    if ($variant === 'spine') {
        return iss_relations_render_atlas_spine_block($attributes, $places, $config);
    }

    if ($framing_mode === 'inherit') {
        $framing_mode = 'auto';
    }

    $body_position = iss_relations_normalize_atlas_slice_body_position($attributes);
    $body_html = $variant === 'minimal'
        ? ''
        : iss_relations_render_atlas_slice_copy_body($attributes, $places);

    switch ($variant) {
        case 'corridor':
            $ratio_width = 1600;
            $ratio_height = 420;
            break;

        case 'minimal':
            $ratio_width = 1600;
            $ratio_height = 220;
            break;

        case 'place':
        default:
            $ratio_width = 1600;
            $ratio_height = 520;
            break;
    }

    $stage_options = [
        'class_name' => 'iss-atlas-strip__stage iss-atlas-strip__stage--' . $variant,
        'ratio_width' => $ratio_width,
        'ratio_height' => $ratio_height,
        'rotation_deg' => $rotation_deg,
        'bias_x' => $plane_bias['x'],
        'bias_y' => $plane_bias['y'],
        'map_scale' => $plane_scale,
    ];

    $stage_html = $framing_mode === 'preset'
        ? iss_relations_render_place_map_stage($places, $config, $stage_options)
        : iss_relations_render_atlas_slice_stage($places, $config, $stage_options);

    $classes = [
        'iss-atlas-strip',
        'iss-atlas-strip--' . $variant,
        'iss-atlas-slice',
        'iss-atlas-slice--layout-band',
        'iss-atlas-strip--framing-' . $framing_mode,
    ];

    if ($body_html !== '') {
        $classes[] = 'iss-atlas-slice--body-text';
        $classes[] = 'iss-atlas-slice--body-' . $body_position;
    } else {
        $classes[] = 'iss-atlas-strip--body-none';
    }

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => implode(' ', $classes),
        ])
        : 'class="' . esc_attr(implode(' ', $classes)) . '"';

    $map_shell = '<div class="iss-atlas-slice__map iss-atlas-strip__map">' . $stage_html . '</div>';

    if ($body_html === '') {
        return '<div ' . $wrapper . '>' . $map_shell . '</div>';
    }

    $parts = $body_position === 'start'
        ? [$body_html, $map_shell]
        : [$map_shell, $body_html];

    return '<div ' . $wrapper . '>' . implode('', $parts) . '</div>';
}

function iss_relations_render_dense_image_wall_block($attributes = []): string
{
    $attributes = is_array($attributes) ? $attributes : [];
    $items = isset($attributes['items']) && is_array($attributes['items']) ? $attributes['items'] : [];
    if (!$items) {
        return '';
    }

    $columns = max(2, min(6, (int) ($attributes['columns'] ?? 4)));
    $row_height = max(80, min(260, (int) ($attributes['rowHeight'] ?? 118)));

    $wrapper = function_exists('get_block_wrapper_attributes')
        ? get_block_wrapper_attributes([
            'class' => 'wp-block-group iss-dense-image-wall',
            'style' => '--iss-dense-image-wall-columns:' . $columns . ';--iss-dense-image-wall-row-size:' . $row_height . 'px;',
        ])
        : 'class="wp-block-group iss-dense-image-wall" style="--iss-dense-image-wall-columns:' . esc_attr((string) $columns) . ';--iss-dense-image-wall-row-size:' . esc_attr((string) $row_height) . 'px;"';

    $out = '<div ' . $wrapper . '>';

    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }

        $url = trim((string) ($item['url'] ?? ''));
        if ($url === '') {
            continue;
        }

        $col_span = max(1, min($columns, (int) ($item['colSpan'] ?? 1)));
        $row_span = max(1, min(6, (int) ($item['rowSpan'] ?? 1)));
        $link_url = trim((string) ($item['linkUrl'] ?? ''));
        $alt = trim((string) ($item['alt'] ?? ''));
        $kicker = trim((string) ($item['kicker'] ?? ''));
        $title = trim((string) ($item['title'] ?? ''));
        $text = trim((string) ($item['text'] ?? ''));
        $caption = trim((string) ($item['caption'] ?? ''));
        $caption_html = trim((string) ($item['captionHtml'] ?? ''));
        $cell_class = trim((string) ($item['className'] ?? ($item['cellClassName'] ?? '')));
        $caption_class = trim((string) ($item['captionClassName'] ?? ''));
        $attachment_id = absint($item['id'] ?? 0);

        $classes = ['wp-block-image', 'iss-dense-image-wall__cell'];
        if ($col_span >= 2) {
            $classes[] = 'iss-dense-image-wall__cell--wide';
        }
        if ($row_span >= 2) {
            $classes[] = 'iss-dense-image-wall__cell--tall';
        }
        if ($caption_html !== '' || $caption !== '' || $kicker !== '' || $title !== '' || $text !== '') {
            $classes[] = 'iss-dense-image-wall__cell--captioned';
        }
        if ($cell_class !== '') {
            $classes[] = $cell_class;
        }

        $style = [];
        if ($col_span > 1) {
            $style[] = 'grid-column:span ' . $col_span;
        }
        if ($row_span > 1) {
            $style[] = 'grid-row:span ' . $row_span;
        }

        $out .= '<figure class="' . esc_attr(implode(' ', $classes)) . '"';
        if ($style) {
            $out .= ' style="' . esc_attr(implode(';', $style)) . '"';
        }
        $out .= '>';

        $image_html = '';
        if ($attachment_id > 0) {
            $image_html = wp_get_attachment_image($attachment_id, 'large', false, [
                'alt' => $alt,
                'loading' => 'lazy',
                'decoding' => 'async',
            ]);
        }

        if ($image_html === '') {
            $image_html = '<img src="' . esc_url($url) . '" alt="' . esc_attr($alt) . '" loading="lazy" decoding="async">';
        }

        if ($link_url !== '') {
            $out .= '<a href="' . esc_url($link_url) . '">' . $image_html . '</a>';
        } else {
            $out .= $image_html;
        }

        if ($caption_html !== '' || $caption !== '' || $kicker !== '' || $title !== '' || $text !== '') {
            $caption_classes = ['iss-dense-image-wall__caption'];
            if ($caption_class !== '') {
                $caption_classes[] = $caption_class;
            }
            $figcaption_class = ' class="' . esc_attr(implode(' ', $caption_classes)) . '"';
            $out .= '<figcaption' . $figcaption_class . '>';
            if ($caption_html !== '') {
                $out .= wp_kses_post($caption_html);
            } elseif ($kicker !== '' || $title !== '' || $text !== '') {
                if ($kicker !== '') {
                    $out .= '<p class="iss-dense-image-wall__kicker">' . esc_html($kicker) . '</p>';
                }
                if ($title !== '') {
                    $out .= '<p class="iss-dense-image-wall__title">' . esc_html($title) . '</p>';
                }
                if ($text !== '') {
                    $out .= '<p class="iss-dense-image-wall__text">' . esc_html($text) . '</p>';
                }
            } else {
                $out .= esc_html($caption);
            }
            $out .= '</figcaption>';
        }

        $out .= '</figure>';
    }

    $out .= '</div>';

    return $out;
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
    $title_id = $title !== '' ? wp_unique_id('iss-related-network-title-') : '';
    $wrapper_classes = [
        'iss-related-network-group',
        'iss-related-network-group--' . sanitize_html_class($scope_key),
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

        $meta = array_values(array_filter([
            iss_relations_get_card_kicker($post, $defaults),
            iss_relations_get_card_meta_line($post),
            iss_relations_get_card_detail_line($post),
        ]));

        $out .= '<li class="iss-related-network-group__item">';
        $out .= '<a class="iss-related-network-group__link" href="' . esc_url($permalink) . '">' . esc_html($title_text) . '</a>';
        if ($meta) {
            $out .= '<span class="iss-related-network-group__meta">' . esc_html(implode(' · ', array_slice($meta, 0, 2))) . '</span>';
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

    if ($layout_variant === 'rail') {
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
