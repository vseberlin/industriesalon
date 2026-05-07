<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_block_type')) {
        return;
    }

    $featured_dir = ISS_PUBLICATIONS_PATH . 'blocks/featured-publication';
    if (file_exists($featured_dir . '/block.json')) {
        register_block_type($featured_dir, [
            'render_callback' => 'iss_publications_render_featured_block',
        ]);
    }

    $grid_dir = ISS_PUBLICATIONS_PATH . 'blocks/publications-grid';
    if (file_exists($grid_dir . '/block.json')) {
        register_block_type($grid_dir, [
            'render_callback' => 'iss_publications_render_grid_block',
        ]);
    }

    $order_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-order-panel';
    if (file_exists($order_dir . '/block.json')) {
        register_block_type($order_dir, [
            'render_callback' => 'iss_publications_render_order_panel_block',
        ]);
    }

    $meta_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-meta';
    if (file_exists($meta_dir . '/block.json')) {
        register_block_type($meta_dir, [
            'render_callback' => 'iss_publications_render_meta_block',
        ]);
    }

    $corpus_dir = ISS_PUBLICATIONS_PATH . 'blocks/publication-corpus-stream';
    if (file_exists($corpus_dir . '/block.json')) {
        register_block_type($corpus_dir, [
            'render_callback' => 'iss_publications_render_corpus_stream_block',
        ]);
    }
});
