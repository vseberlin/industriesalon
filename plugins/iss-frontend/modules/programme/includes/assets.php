<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_programm_register_frontend_assets() {
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    $programm_url = plugin_dir_url(ISS_PROGRAMM_FILE);

    wp_register_script_module(
        'iss/booking',
        $programm_url . 'assets/booking.js',
        ['@wordpress/interactivity'],
        (string) filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/booking.js')
    );
    wp_interactivity_config('iss/booking', [
        'slotsUrl' => rest_url('iss/v1/booking-slots'),
        'requestUrl' => rest_url('iss-payments/v1/request'),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
    wp_interactivity_state('iss/booking', ['catalog' => []]);

    wp_register_script_module(
        'iss/timeline-navigation',
        $programm_url . 'assets/timeline-navigation.js',
        ['@wordpress/interactivity', ['id' => '@wordpress/interactivity-router', 'import' => 'dynamic']],
        (string) filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/timeline-navigation.js')
    );

    wp_register_style(
        'is-tour-calendar',
        $programm_url . 'assets/programm.css',
        [],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/programm.css')
    );

    wp_register_style(
        'iss-timeline',
        $programm_url . 'assets/timeline.css',
        [],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/timeline.css')
    );

    wp_register_script(
        'iss-ausstellungen-browser',
        $programm_url . 'assets/ausstellungen-browser.js',
        [],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/ausstellungen-browser.js'),
        true
    );

    wp_add_inline_script(
        'iss-ausstellungen-browser',
        'window.ISS_AUSSTELLUNGEN = ' . wp_json_encode([
            'restUrl' => function_exists('iss_frontend_rest_url') ? iss_frontend_rest_url('iss/v1/availability') : rest_url('iss/v1/availability'),
        ]) . ';',
        'before'
    );
}
add_action('wp_enqueue_scripts', 'iss_programm_register_frontend_assets');

function iss_programm_enqueue_calendar_assets() {
    iss_programm_register_frontend_assets();
    wp_enqueue_style('is-tour-calendar');
    do_action('iss_programm_calendar_assets_enqueued');
    wp_enqueue_script_module('iss/booking');
}

function iss_programm_enqueue_timeline_assets() {
    if (!wp_style_is('iss-timeline', 'registered')) {
        iss_programm_register_frontend_assets();
    }

    wp_enqueue_style('iss-timeline');
    do_action('iss_programm_timeline_assets_enqueued');
}

function iss_programm_enqueue_timeline_query_assets() {
    iss_programm_enqueue_timeline_assets();
    wp_enqueue_script_module('iss/timeline-navigation');
}

function iss_programm_enqueue_ausstellungen_browser_assets() {
    iss_programm_enqueue_timeline_assets();

    if (!wp_script_is('iss-ausstellungen-browser', 'registered')) {
        iss_programm_register_frontend_assets();
    }

    wp_enqueue_script('iss-ausstellungen-browser');
}
