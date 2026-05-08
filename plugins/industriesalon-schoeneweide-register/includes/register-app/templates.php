<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_get_partial_template_path(string $partial): string
{
    return ISS_REGISTER_PATH . 'includes/partials/' . $partial . '.php';
}

function iss_register_render_register_partial(string $partial, array $view = []): string
{
    $template_path = iss_register_get_partial_template_path($partial);
    if (!is_readable($template_path)) {
        return '';
    }

    ob_start();
    include $template_path;

    return (string) ob_get_clean();
}

function iss_register_render_register_panels(array $view): string
{
    $panel_partials = [
        'panel-discover',
        'panel-places',
        'panel-then-now',
        'panel-research',
        'panel-detail',
    ];

    $markup = '<div class="iss-register-panels">';

    foreach ($panel_partials as $partial) {
        $markup .= iss_register_render_register_partial($partial, $view);
    }

    $markup .= '</div>';

    return $markup;
}

function iss_register_render_register_layout(array $view): string
{
    $layout = '';
    $layout .= iss_register_render_register_partial('hero', $view);
    $layout .= iss_register_render_register_partial('tabs', $view);
    $layout .= iss_register_render_register_panels($view);
    $layout .= iss_register_render_register_partial('client-templates', $view);
    $layout .= iss_register_render_register_partial('modal-feedback', $view);

    return $layout;
}
