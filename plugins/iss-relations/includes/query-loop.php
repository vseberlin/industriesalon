<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_relations_extend_core_query_attributes(array $args, string $block_type): array
{
    if ($block_type !== 'core/query') {
        return $args;
    }

    $args['attributes'] = is_array($args['attributes'] ?? null) ? $args['attributes'] : [];
    $args['attributes']['issRelationPlaceIds'] = [
        'type' => 'array',
        'default' => [],
    ];

    return $args;
}
add_filter('register_block_type_args', 'iss_relations_extend_core_query_attributes', 10, 2);

function iss_relations_register_query_loop_editor_script(): void
{
    $script_path = ISS_RELATIONS_PATH . 'assets/query-loop-relations.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_register_script(
        'iss-relations-query-loop',
        ISS_RELATIONS_URL . 'assets/query-loop-relations.js',
        [
            'wp-block-editor',
            'wp-blocks',
            'wp-components',
            'wp-compose',
            'wp-core-data',
            'wp-data',
            'wp-element',
            'wp-hooks',
        ],
        (string) filemtime($script_path),
        true
    );
}
add_action('init', 'iss_relations_register_query_loop_editor_script', 20);

function iss_relations_enqueue_query_loop_editor_script(): void
{
    if (!wp_script_is('iss-relations-query-loop', 'registered')) {
        return;
    }

    wp_enqueue_script('iss-relations-query-loop');
    wp_add_inline_script(
        'iss-relations-query-loop',
        'window.issRelationsSettings = ' . wp_json_encode([
            'placePostType' => iss_relations_get_place_post_type(),
            'taxonomy' => ISS_RELATIONS_TAXONOMY,
            'supportedPostTypes' => iss_relations_get_supported_post_types(),
        ]) . ';',
        'before'
    );
}
add_action('enqueue_block_editor_assets', 'iss_relations_enqueue_query_loop_editor_script');

function iss_relations_replace_query_loop_place_tax_query(array $tax_query, array $term_ids): array
{
    $next = [];

    foreach ($tax_query as $taxonomy => $terms) {
        if ($taxonomy === ISS_RELATIONS_TAXONOMY) {
            continue;
        }

        $next[$taxonomy] = $terms;
    }

    $next[ISS_RELATIONS_TAXONOMY] = array_values(array_unique(array_filter(array_map('absint', $term_ids))));

    return $next;
}

function iss_relations_portable_query_loop_place_filters(array $parsed_block, array $source_block): array
{
    if (($parsed_block['blockName'] ?? '') !== 'core/query') {
        return $parsed_block;
    }

    $attributes = is_array($source_block['attrs'] ?? null) ? $source_block['attrs'] : (is_array($parsed_block['attrs'] ?? null) ? $parsed_block['attrs'] : []);
    $place_ids = iss_relations_parse_place_ids($attributes['issRelationPlaceIds'] ?? []);
    if (!$place_ids) {
        return $parsed_block;
    }

    $term_ids = iss_relations_get_place_term_ids($place_ids);
    if (!$term_ids) {
        return $parsed_block;
    }

    if (!isset($parsed_block['attrs']) || !is_array($parsed_block['attrs'])) {
        $parsed_block['attrs'] = [];
    }

    if (!isset($parsed_block['attrs']['query']) || !is_array($parsed_block['attrs']['query'])) {
        $parsed_block['attrs']['query'] = [];
    }

    $parsed_block['attrs']['query']['taxQuery'] = iss_relations_replace_query_loop_place_tax_query(
        is_array($parsed_block['attrs']['query']['taxQuery'] ?? null) ? $parsed_block['attrs']['query']['taxQuery'] : [],
        $term_ids
    );

    return $parsed_block;
}
add_filter('render_block_data', 'iss_relations_portable_query_loop_place_filters', 10, 2);
