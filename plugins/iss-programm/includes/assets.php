<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_programm_render_calendar_modal_host() {
    if (!wp_script_is('is-tour-calendar', 'enqueued')) {
        return;
    }

    static $rendered = false;
    if ($rendered) {
        return;
    }

    $rendered = true;

    echo '<div class="is-tour-calendar-modal" data-shared-tour-calendar-modal="1" hidden>';
    echo '<div class="is-tour-calendar-modal__overlay" data-close="1" tabindex="-1"></div>';
    $dialog_attrs = function_exists('iss_frontend_dialog_attributes')
        ? iss_frontend_dialog_attributes(__('Buchung', 'iss-programm'))
        : [
            'role' => 'dialog',
            'aria-modal' => 'true',
            'aria-label' => __('Buchung', 'iss-programm'),
        ];

    $dialog_attr_html = '';
    foreach ($dialog_attrs as $name => $value) {
        $dialog_attr_html .= ' ' . sanitize_key($name) . '="' . esc_attr((string) $value) . '"';
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attribute names and values are escaped above.
    echo '<div class="is-tour-calendar-modal__panel"' . $dialog_attr_html . '>';
    echo '<button type="button" class="is-tour-calendar-modal__close" data-close="1" aria-label="' . esc_attr__('Schließen', 'iss-programm') . '">×</button>';
    echo '<div class="is-tour-calendar-modal__content"></div>';
    echo '</div>';
    echo '</div>';
}
add_action('wp_footer', 'iss_programm_render_calendar_modal_host', 20);

function iss_programm_register_frontend_assets() {
    static $registered = false;
    if ($registered) {
        return;
    }
    $registered = true;

    $programm_url = plugin_dir_url(ISS_PROGRAMM_FILE);

    if (function_exists('iss_frontend_register_datepicker_assets')) {
        iss_frontend_register_datepicker_assets(
            'is-tour-calendar-flatpickr',
            $programm_url . 'assets/vendor/flatpickr/flatpickr.min.js',
            $programm_url . 'assets/vendor/flatpickr/flatpickr.min.css',
            [],
            '4.6.13'
        );
    } else {
        wp_register_style(
            'is-tour-calendar-flatpickr',
            $programm_url . 'assets/vendor/flatpickr/flatpickr.min.css',
            [],
            '4.6.13'
        );

        wp_register_script(
            'is-tour-calendar-flatpickr',
            $programm_url . 'assets/vendor/flatpickr/flatpickr.min.js',
            [],
            '4.6.13',
            true
        );
    }

    wp_register_script(
        'is-tour-calendar-flatpickr-l10n-de',
        $programm_url . 'assets/vendor/flatpickr/l10n/de.js',
        ['is-tour-calendar-flatpickr'],
        '4.6.13',
        true
    );

    wp_register_script(
        'is-tour-calendar',
        $programm_url . 'assets/programm.js',
        ['is-tour-calendar-flatpickr', 'is-tour-calendar-flatpickr-l10n-de'],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/programm.js'),
        true
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
        'iss-timeline-query',
        $programm_url . 'assets/timeline-query.js',
        ['is-tour-calendar-flatpickr', 'is-tour-calendar-flatpickr-l10n-de'],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/timeline-query.js'),
        true
    );

    wp_register_script(
        'iss-ausstellungen-browser',
        $programm_url . 'assets/ausstellungen-browser.js',
        [],
        filemtime(plugin_dir_path(ISS_PROGRAMM_FILE) . 'assets/ausstellungen-browser.js'),
        true
    );

    wp_add_inline_script(
        'is-tour-calendar',
        'window.IS_TOUR_CALENDAR = ' . wp_json_encode([
            'restUrl' => function_exists('iss_frontend_rest_url') ? iss_frontend_rest_url('iss/v1/tour-slots') : rest_url('iss/v1/tour-slots'),
        ]) . ';',
        'before'
    );

    wp_add_inline_script(
        'is-tour-calendar',
        'window.IS_TOUR_CALENDAR = Object.assign({}, window.IS_TOUR_CALENDAR, {' .
        '"bookUrl": ' . wp_json_encode(function_exists('iss_frontend_rest_url') ? iss_frontend_rest_url('is-tours/v1/book') : rest_url('is-tours/v1/book')) .
        '});',
        'after'
    );

    wp_add_inline_script(
        'iss-timeline-query',
        'window.ISS_TIMELINE = ' . wp_json_encode([
            'restUrl' => function_exists('iss_frontend_rest_url') ? iss_frontend_rest_url('iss/v1/timeline') : rest_url('iss/v1/timeline'),
        ]) . ';',
        'before'
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

    wp_enqueue_style('is-tour-calendar-flatpickr');
    wp_enqueue_script('is-tour-calendar-flatpickr');
    wp_enqueue_script('is-tour-calendar-flatpickr-l10n-de');
    wp_enqueue_script('iss-timeline-query');
}

function iss_programm_enqueue_ausstellungen_browser_assets() {
    iss_programm_enqueue_timeline_assets();

    if (!wp_script_is('iss-ausstellungen-browser', 'registered')) {
        iss_programm_register_frontend_assets();
    }

    wp_enqueue_script('iss-ausstellungen-browser');
}
