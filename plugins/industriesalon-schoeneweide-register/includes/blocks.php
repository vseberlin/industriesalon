<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_block_type')) {
        return;
    }

    $block_json = ISS_REGISTER_PATH . 'block.json';
    if (file_exists($block_json)) {
        register_block_type(ISS_REGISTER_PATH, [
            'render_callback' => 'iss_register_render_register_app',
        ]);
    }
});
