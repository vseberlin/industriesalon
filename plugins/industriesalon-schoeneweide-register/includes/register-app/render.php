<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_render_register_document(string $bootstrap_tag, string $layout): string
{
    if ($layout === '') {
        $layout = '<p>Das Schöneweide Register konnte nicht geladen werden.</p>';
    }

    return sprintf(
        '<div class="iss-register">%1$s<div class="iss-register__app" data-iss-register-app>%2$s</div><noscript>JavaScript wird benötigt, um das Schöneweide Register zu laden.</noscript></div>',
        $bootstrap_tag,
        $layout
    );
}

function iss_register_render_register_app(array $attributes = []): string
{
    $normalized_attributes = iss_register_normalize_app_attributes($attributes);
    $rest_roots = iss_register_get_app_rest_roots();
    $view = iss_register_build_app_view($normalized_attributes, $rest_roots);
    $bootstrap_config = iss_register_build_app_bootstrap_config($normalized_attributes, $rest_roots);
    $bootstrap_payload = iss_register_build_app_bootstrap_payload($bootstrap_config);
    $bootstrap_tag = iss_register_render_bootstrap_payload_tag($bootstrap_payload);
    $layout = iss_register_render_register_layout($view);

    wp_enqueue_script('iss-register-frontend-app');
    wp_enqueue_style('iss-register-frontend-style');

    return iss_register_render_register_document($bootstrap_tag, $layout);
}
