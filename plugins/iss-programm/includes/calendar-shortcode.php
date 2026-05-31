<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('init', function () {
    if (!function_exists('add_shortcode') || shortcode_exists('is_tour_calendar')) {
        return;
    }

    add_shortcode('is_tour_calendar', function ($atts = []) {
        if (!function_exists('iss_render_tour_calendar')) {
            return '';
        }

        $atts = shortcode_atts([
            'tag' => '',
            'title' => 'Termine wählen',
            'fallback_url' => '',
        ], is_array($atts) ? $atts : [], 'is_tour_calendar');

        return iss_render_tour_calendar([
            'tag' => (string) $atts['tag'],
            'title' => (string) $atts['title'],
            'fallbackUrl' => (string) $atts['fallback_url'],
        ], '');
    });
});

