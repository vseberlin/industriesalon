<?php

if (!defined('ABSPATH')) exit;

// Register Gutenberg blocks (dynamic render).
add_action('init', function () {
    if (!function_exists('register_block_type')) return;

    $dates_dir = __DIR__ . '/../blocks/tour-dates';
    if (file_exists($dates_dir . '/block.json')) {
        register_block_type($dates_dir, [
            'render_callback' => 'iss_programm_render_tour_dates',
        ]);
    }

    $calendar_dir = __DIR__ . '/../blocks/tour-calendar';
    if (file_exists($calendar_dir . '/block.json')) {
        register_block_type($calendar_dir, [
            'render_callback' => 'iss_programm_render_tour_calendar',
        ]);
    }

});
