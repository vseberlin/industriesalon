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

    $description_dir = ISS_FUEHRUNGEN_PATH . 'blocks/tour-description';
    if (file_exists($description_dir . '/block.json')) {
        register_block_type($description_dir, [
            'render_callback' => 'iss_fuehrung_render_description_block',
        ]);
    }

    $offer_catalog_dir = ISS_FUEHRUNGEN_PATH . 'blocks/tour-offer-catalog';
    if (file_exists($offer_catalog_dir . '/block.json')) {
        register_block_type($offer_catalog_dir, [
            'render_callback' => 'iss_fuehrung_render_offer_catalog_block',
        ]);
    }

    $route_dir = ISS_FUEHRUNGEN_PATH . 'blocks/tour-route';
    if (file_exists($route_dir . '/block.json')) {
        register_block_type($route_dir, [
            'render_callback' => 'iss_fuehrung_render_route_block',
        ]);
    }
});
