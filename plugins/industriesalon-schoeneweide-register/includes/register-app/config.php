<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_app_default_attributes(): array
{
    return [
        'defaultView' => 'discover',
        'showIntro' => true,
        'showFeedback' => true,
        'enableExport' => true,
        'limitArea' => '',
        'limitStatus' => '',
    ];
}

function iss_register_normalize_app_attributes(array $attributes): array
{
    $attributes = wp_parse_args($attributes, iss_register_get_app_default_attributes());
    $allowed_views = ['discover', 'places', 'then-now', 'research'];

    return [
        'default_view' => in_array($attributes['defaultView'], $allowed_views, true)
            ? $attributes['defaultView']
            : 'discover',
        'show_intro' => !empty($attributes['showIntro']),
        'show_feedback' => !empty($attributes['showFeedback']),
        'enable_export' => !empty($attributes['enableExport']),
        'limit_area' => sanitize_text_field((string) ($attributes['limitArea'] ?? '')),
        'limit_status' => sanitize_text_field((string) ($attributes['limitStatus'] ?? '')),
    ];
}

function iss_register_get_app_rest_roots(): array
{
    $api_root_url = untrailingslashit(rest_url(ISS_REGISTER_REST_NAMESPACE));
    $api_root_path = wp_parse_url($api_root_url, PHP_URL_PATH);

    if (!is_string($api_root_path) || $api_root_path === '') {
        $api_root_path = '/wp-json/' . ISS_REGISTER_REST_NAMESPACE;
    }

    return [
        'url' => $api_root_url,
        'path' => $api_root_path,
    ];
}
