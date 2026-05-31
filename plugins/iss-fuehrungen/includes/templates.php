<?php

if (!defined('ABSPATH')) {
    exit;
}

add_filter('theme_' . ISS_FUEHRUNGEN_POST_TYPE . '_templates', function ($templates) {
    $templates = is_array($templates) ? $templates : [];

    $templates['single-tour'] = __('Tour (buchbar / hybrid)', 'iss-fuehrungen');
    $templates['single-tour-on-demand'] = __('Tour (auf Anfrage)', 'iss-fuehrungen');

    return $templates;
}, 20);

function iss_fuehrungen_resolve_single_template_slug($post_id, $effective_mode) {
    $supported_templates = [
        'single-tour',
        'single-tour-on-demand',
    ];

    $selected_template = '';
    if ($post_id > 0) {
        $selected_template = (string) get_page_template_slug($post_id);
        $selected_template = preg_replace('/\.html$/', '', trim($selected_template));
    }

    if (in_array($selected_template, $supported_templates, true)) {
        return $selected_template;
    }

    if ($effective_mode === 'on_demand') {
        return 'single-tour-on-demand';
    }

    return 'single-tour';
}

function iss_fuehrungen_get_single_template_content($template_slug) {
    if ($template_slug === '') {
        return '';
    }

    $template_id = get_stylesheet() . '//' . $template_slug;
    if (function_exists('get_block_template')) {
        $block_template = get_block_template($template_id, 'wp_template');
        if ($block_template instanceof WP_Block_Template) {
            $content = trim((string) $block_template->content);
            if ($content !== '') {
                return $content;
            }
        }
    }

    $theme_template_path = trailingslashit(get_stylesheet_directory()) . 'templates/' . $template_slug . '.html';
    if (!file_exists($theme_template_path)) {
        return '';
    }

    $template_content = file_get_contents($theme_template_path);
    if (!is_string($template_content)) {
        return '';
    }

    return trim($template_content);
}

add_filter('template_include', function ($template) {
    if (is_singular(ISS_FUEHRUNGEN_POST_TYPE)) {
        $post_id = (int) get_queried_object_id();
        $effective_mode = function_exists('iss_fuehrung_get_effective_booking_mode')
            ? iss_fuehrung_get_effective_booking_mode($post_id)
            : 'calendar';
        $template_slug = iss_fuehrungen_resolve_single_template_slug($post_id, $effective_mode);
        $template_content = iss_fuehrungen_get_single_template_content($template_slug);

        if ($template_content !== '') {
            $GLOBALS['iss_fuehrung_theme_single_template_content'] = $template_content;
            return ISS_FUEHRUNGEN_PATH . 'templates/single-fuehrung-theme-loader.php';
        }

        return ISS_FUEHRUNGEN_PATH . 'templates/single-fuehrung.php';
    }

    if (is_post_type_archive(ISS_FUEHRUNGEN_POST_TYPE) || is_tax('fuehrung_typ')) {
        return ISS_FUEHRUNGEN_PATH . 'templates/archive-fuehrung.php';
    }

    return $template;
}, 99);
