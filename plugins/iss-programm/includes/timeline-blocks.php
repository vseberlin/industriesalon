<?php
if (!defined('ABSPATH')) exit;

add_action('init', function () {
    if (!function_exists('register_block_type')) return;

    $dir_cards = __DIR__ . '/../blocks/program-cards';
    if (file_exists($dir_cards . '/block.json')) {
        register_block_type($dir_cards, [
            'render_callback' => 'iss_programm_render_cards_block',
        ]);
    }

    $dir_query = __DIR__ . '/../blocks/timeline-query';
    if (file_exists($dir_query . '/block.json')) {
        register_block_type($dir_query, [
            'render_callback' => 'iss_timeline_render_query_block',
        ]);
    }

});
