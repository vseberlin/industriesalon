<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_render_partial(string $partial, array $context = []): string
{
    $template_path = ISS_REGISTER_PATH . 'includes/partials/' . $partial . '.php';
    if (!is_readable($template_path)) {
        return '';
    }

    if (!empty($context)) {
        extract($context, EXTR_SKIP);
    }

    ob_start();
    include $template_path;
    return (string) ob_get_clean();
}

function iss_register_render_app_layout(array $context): string
{
    $layout = '';
    $layout .= iss_register_render_partial('hero', $context);
    $layout .= iss_register_render_partial('tabs', $context);
    $layout .= '<div class="iss-register-panels">';
    $layout .= iss_register_render_partial('panel-discover', $context);
    $layout .= iss_register_render_partial('panel-places', $context);
    $layout .= iss_register_render_partial('panel-then-now', $context);
    $layout .= iss_register_render_partial('panel-research', $context);
    $layout .= iss_register_render_partial('panel-detail', $context);
    $layout .= '</div>';
    $layout .= iss_register_render_partial('modal-feedback', $context);

    return $layout;
}

function iss_register_prepare_local_source_payload(): array
{
    $places = function_exists('iss_register_get_places_data')
        ? iss_register_get_places_data()
        : [];

    if (!is_array($places)) {
        $places = [];
    }

    $places = array_values(array_filter($places, 'is_array'));

    return [
        'places' => $places,
        'generatedAt' => gmdate('c'),
    ];
}

function iss_register_render_local_source_tag(array $payload): string
{
    $encoded = wp_json_encode(
        $payload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if (!is_string($encoded) || $encoded === '') {
        $encoded = '{"places":[]}';
    }

    return sprintf(
        '<script type="application/json" data-register-source>%s</script>',
        $encoded
    );
}

function iss_register_render_register_app(array $attributes = []): string
{
    $defaults = [
        'defaultView' => 'discover',
        'showIntro' => true,
        'showFeedback' => true,
        'enableExport' => true,
        'limitArea' => '',
        'limitStatus' => '',
    ];

    $attributes = wp_parse_args($attributes, $defaults);

    $allowed_views = ['discover', 'places', 'then-now', 'research'];
    $default_view = in_array($attributes['defaultView'], $allowed_views, true)
        ? $attributes['defaultView']
        : 'discover';

    $show_intro = !empty($attributes['showIntro']) ? '1' : '0';
    $show_feedback = !empty($attributes['showFeedback']) ? '1' : '0';
    $enable_export = !empty($attributes['enableExport']) ? '1' : '0';
    $limit_area = sanitize_text_field((string) ($attributes['limitArea'] ?? ''));
    $limit_status = sanitize_text_field((string) ($attributes['limitStatus'] ?? ''));
    $rest_nonce = wp_create_nonce('wp_rest');
    $local_source_payload = iss_register_prepare_local_source_payload();
    $local_source_tag = iss_register_render_local_source_tag($local_source_payload);

    $api_root_url = untrailingslashit(rest_url(ISS_REGISTER_REST_NAMESPACE));
    $api_root_path = wp_parse_url($api_root_url, PHP_URL_PATH);
    if (!is_string($api_root_path) || $api_root_path === '') {
        $api_root_path = '/wp-json/' . ISS_REGISTER_REST_NAMESPACE;
    }

    $layout = iss_register_render_app_layout([
        'show_intro' => $show_intro === '1',
        'show_feedback' => $show_feedback === '1',
        'enable_export' => $enable_export === '1',
        'api_root_url' => $api_root_url,
        'places' => $local_source_payload['places'] ?? [],
    ]);

    wp_enqueue_script('iss-register-frontend-app');
    wp_enqueue_style('iss-register-frontend-style');

    if ($layout === '') {
        $layout = '<p>Das Schöneweide Register konnte nicht geladen werden.</p>';
    }

    return sprintf(
        '<div class="iss-register" data-view="%1$s" data-api-root="%2$s" data-show-intro="%3$s" data-show-feedback="%4$s" data-enable-export="%5$s" data-limit-area="%6$s" data-limit-status="%7$s" data-rest-nonce="%8$s" data-source-mode="local">%9$s<div class="iss-register__app" data-iss-register-app>%10$s</div><noscript>JavaScript wird benötigt, um das Schöneweide Register zu laden.</noscript></div>',
        esc_attr($default_view),
        esc_attr($api_root_path),
        esc_attr($show_intro),
        esc_attr($show_feedback),
        esc_attr($enable_export),
        esc_attr($limit_area),
        esc_attr($limit_status),
        esc_attr($rest_nonce),
        $local_source_tag,
        $layout
    );
}
