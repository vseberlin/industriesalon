<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_programm_register_frontend_assets() {
    wp_register_style(
        'is-tour-calendar-flatpickr',
        plugin_dir_url(ISS_PROGRAMM_FILE) . 'assets/vendor/flatpickr/flatpickr.min.css',
        [],
        '4.6.13'
    );

    wp_register_script(
        'is-tour-calendar-flatpickr',
        plugin_dir_url(ISS_PROGRAMM_FILE) . 'assets/vendor/flatpickr/flatpickr.min.js',
        [],
        '4.6.13',
        true
    );

    wp_register_script(
        'is-tour-calendar-flatpickr-l10n-de',
        plugin_dir_url(ISS_PROGRAMM_FILE) . 'assets/vendor/flatpickr/l10n/de.js',
        ['is-tour-calendar-flatpickr'],
        '4.6.13',
        true
    );

    wp_register_script(
        'is-tour-calendar',
        plugin_dir_url(ISS_PROGRAMM_FILE) . 'assets/programm.js',
        ['is-tour-calendar-flatpickr', 'is-tour-calendar-flatpickr-l10n-de'],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/programm.js'),
        true
    );

    wp_register_style(
        'is-tour-calendar',
        plugin_dir_url(ISS_PROGRAMM_FILE) . 'assets/programm.css',
        [],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/programm.css')
    );

    wp_register_style(
        'iss-timeline',
        plugin_dir_url(ISS_PROGRAMM_FILE) . 'assets/timeline.css',
        [],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/timeline.css')
    );

    wp_register_script(
        'iss-timeline-query',
        plugin_dir_url(ISS_PROGRAMM_FILE) . 'assets/timeline-query.js',
        [],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/timeline-query.js'),
        true
    );

    wp_add_inline_script(
        'is-tour-calendar',
        'window.IS_TOUR_CALENDAR = ' . wp_json_encode([
            'restUrl' => rest_url('is-tours/v1/slots'),
        ]) . ';',
        'before'
    );

    wp_add_inline_script(
        'is-tour-calendar',
        'window.IS_TOUR_CALENDAR = Object.assign({}, window.IS_TOUR_CALENDAR, {' .
        '"bookUrl": ' . wp_json_encode(rest_url('is-tours/v1/book')) .
        '});',
        'after'
    );

    wp_add_inline_script(
        'iss-timeline-query',
        'window.ISS_TIMELINE = ' . wp_json_encode([
            'restUrl' => rest_url('iss-programm/v1/timeline'),
        ]) . ';',
        'before'
    );
}
add_action('wp_enqueue_scripts', 'iss_programm_register_frontend_assets');

function iss_programm_enqueue_calendar_assets() {
    if (!wp_style_is('is-tour-calendar-flatpickr', 'registered')) {
        iss_programm_register_frontend_assets();
    }

    wp_enqueue_style('is-tour-calendar-flatpickr');
    wp_enqueue_style('is-tour-calendar');
    do_action('iss_programm_calendar_assets_enqueued');
    wp_enqueue_script('is-tour-calendar-flatpickr');
    wp_enqueue_script('is-tour-calendar-flatpickr-l10n-de');
    wp_enqueue_script('is-tour-calendar');
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

    if (!wp_script_is('iss-timeline-query', 'registered')) {
        iss_programm_register_frontend_assets();
    }

    wp_enqueue_script('iss-timeline-query');
}
