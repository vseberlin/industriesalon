<?php

if (!defined('ABSPATH')) { exit; }

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
                'default' => iss_relations_get_map_block_default('iss/related-place-map', 'default_source', 'current'),
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
                'default' => iss_relations_get_map_block_default('iss/related-place-map', 'default_preset', 'default'),
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

    register_block_type('iss/atlas-map', [
        'api_version' => 3,
        'title' => __('Atlas Map', 'iss-relations'),
        'category' => 'widgets',
        'icon' => 'location-alt',
        'description' => __('Renders a registered Atlas map gesture variant through the shared static-map renderer.', 'iss-relations'),
        'editor_script' => 'iss-relations-related-blocks',
        'attributes' => [
            'variant' => [
                'type' => 'string',
                'default' => 'place-locator',
            ],
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
            'linkUrl' => [
                'type' => 'string',
                'default' => '',
            ],
            'linkLabel' => [
                'type' => 'string',
                'default' => '',
            ],
            'shellMode' => [
                'type' => 'string',
                'default' => 'body',
            ],
        ],
        'supports' => [
            'html' => false,
            'align' => ['wide', 'full'],
        ],
        'render_callback' => 'iss_relations_render_atlas_map_block',
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
                'default' => iss_relations_get_map_block_default('iss/atlas-slice', 'default_source', 'current'),
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
            'mapPreset' => [
                'type' => 'string',
                'default' => iss_relations_get_map_block_default('iss/atlas-slice', 'default_preset', 'default'),
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
                'default' => iss_relations_get_map_block_default('iss/atlas-strip', 'default_source', 'current'),
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
            'mapPreset' => [
                'type' => 'string',
                'default' => iss_relations_get_map_block_default('iss/atlas-strip', 'default_preset', 'default'),
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
            'directionLabelStart' => [
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
            'inserter' => false,
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
                'default' => iss_relations_get_map_block_default('iss/spine-strip', 'default_source', 'manual'),
            ],
            'placeIds' => [
                'type' => 'string',
                'default' => '',
            ],
            'mapPreset' => [
                'type' => 'string',
                'default' => iss_relations_get_map_block_default('iss/spine-strip', 'default_preset', 'default'),
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
            'directionLabelStart' => [
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
                'default' => iss_relations_get_map_block_default('iss/asymmetric-split-field', 'default_source', 'current'),
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
            'inserter' => false,
        ],
        'render_callback' => 'iss_relations_render_asymmetric_split_field_block',
    ]);
}
add_action('init', 'iss_relations_register_blocks', 20);

function iss_relations_register_block_editor_script(): void
{
    $base_path = ISS_RELATIONS_PATH . 'blocks/related-content/';
    $base_url = ISS_RELATIONS_URL . 'blocks/related-content/';
    $scripts = [
        'iss-relations-related-editor-context' => [
            'file' => 'editor-context.js',
            'deps' => [
                'wp-api-fetch',
                'wp-block-editor',
                'wp-blocks',
                'wp-components',
                'wp-core-data',
                'wp-data',
                'wp-element',
            ],
        ],
        'iss-relations-related-source-controls' => [
            'file' => 'source-controls.js',
            'deps' => ['iss-relations-related-editor-context'],
        ],
        'iss-relations-related-editorial-signals' => [
            'file' => 'editorial-signal-controls.js',
            'deps' => ['iss-relations-related-editor-context'],
        ],
        'iss-relations-related-card-controls' => [
            'file' => 'related-cards-controls.js',
            'deps' => ['iss-relations-related-editor-context', 'iss-relations-related-editorial-signals'],
        ],
        'iss-relations-related-static-map-controls' => [
            'file' => 'static-map-controls.js',
            'deps' => ['iss-relations-related-editor-context'],
        ],
        'iss-relations-related-spine-strip-controls' => [
            'file' => 'spine-strip-controls.js',
            'deps' => ['iss-relations-related-editor-context'],
        ],
        'iss-relations-related-blocks' => [
            'file' => 'index.js',
            'deps' => [
                'iss-relations-related-card-controls',
                'iss-relations-related-source-controls',
                'iss-relations-related-static-map-controls',
                'iss-relations-related-spine-strip-controls',
                'iss-relations-related-editorial-signals',
            ],
        ],
    ];

    foreach ($scripts as $handle => $script) {
        $script_path = $base_path . $script['file'];
        if (!file_exists($script_path)) {
            return;
        }

        wp_register_script(
            $handle,
            $base_url . $script['file'],
            $script['deps'],
            (string) filemtime($script_path),
            true
        );
    }

    if (!wp_script_is('iss-relations-related-editor-context', 'registered')) {
        return;
    }
}
add_action('init', 'iss_relations_register_block_editor_script', 19);

function iss_relations_register_frontend_scripts(): void
{
    $scripts = [
        'iss-relations-related-strip' => 'assets/related-strip.js',
        'iss-relations-atlas-map' => 'assets/atlas-map.js',
    ];

    foreach ($scripts as $handle => $relative_path) {
        $script_path = ISS_RELATIONS_PATH . $relative_path;
        if (!file_exists($script_path)) {
            continue;
        }

        wp_register_script(
            $handle,
            ISS_RELATIONS_URL . $relative_path,
            [],
            (string) filemtime($script_path),
            true
        );
    }
}
add_action('init', 'iss_relations_register_frontend_scripts', 19);

function iss_relations_enqueue_block_editor_script(): void
{
    if (!wp_script_is('iss-relations-related-blocks', 'registered')) {
        return;
    }

    wp_add_inline_script(
        'iss-relations-related-editor-context',
        'window.issRelationsSettings = ' . wp_json_encode([
            'placePostType' => iss_relations_get_place_post_type(),
            'taxonomy' => ISS_RELATIONS_TAXONOMY,
            'supportedPostTypes' => iss_relations_get_supported_post_types(),
            'relatedPostTypes' => iss_relations_get_related_query_post_types(),
            'mapPresets' => iss_relations_get_place_map_editor_presets(),
            'mapBlockContracts' => iss_relations_get_map_block_public_contracts(),
            'canManageEditorialSignals' => function_exists('iss_graph_current_user_can_edit_editorial_signals')
                ? iss_graph_current_user_can_edit_editorial_signals()
                : current_user_can('manage_options'),
        ]) . ';',
        'before'
    );
    wp_enqueue_script('iss-relations-related-blocks');
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

function iss_relations_enqueue_atlas_map_script(): void
{
    if (is_admin()) {
        return;
    }

    if (!wp_script_is('iss-relations-atlas-map', 'registered')) {
        return;
    }

    wp_enqueue_script('iss-relations-atlas-map');
}
