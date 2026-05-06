<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('register_block_type')) {
        return;
    }

    $facts_dir = ISS_FUEHRUNGEN_PATH . 'blocks/tour-facts';
    if (file_exists($facts_dir . '/block.json')) {
        register_block_type($facts_dir, [
            'render_callback' => 'iss_fuehrung_render_facts_block',
        ]);
    }

    $booking_dir = ISS_FUEHRUNGEN_PATH . 'blocks/tour-booking-panel';
    if (file_exists($booking_dir . '/block.json')) {
        register_block_type($booking_dir, [
            'render_callback' => 'iss_fuehrung_render_booking_panel_block',
        ]);
    }

    $hero_gallery_dir = ISS_FUEHRUNGEN_PATH . 'blocks/tour-hero-gallery';
    if (file_exists($hero_gallery_dir . '/block.json')) {
        register_block_type($hero_gallery_dir, [
            'render_callback' => 'iss_fuehrung_render_hero_gallery_block',
        ]);
    }

    $route_dir = ISS_FUEHRUNGEN_PATH . 'blocks/tour-route';
    if (file_exists($route_dir . '/block.json')) {
        register_block_type($route_dir, [
            'render_callback' => 'iss_fuehrung_render_route_block',
        ]);
    }
});
