<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_build_summary_places_payload(array $query_args = []): array
{
    $places = iss_register_get_summary_places_contracts($query_args);

    return [
        'places' => $places,
        'generatedAt' => gmdate('c'),
    ];
}

function iss_register_build_bootstrap_summary_query_args(array $bootstrap_config): array
{
    $query_args = [];

    $limit_area = trim((string) ($bootstrap_config['limitArea'] ?? ''));
    if ($limit_area !== '') {
        $query_args['area'] = $limit_area;
    }

    $limit_status = trim((string) ($bootstrap_config['limitStatus'] ?? ''));
    if ($limit_status !== '') {
        $query_args['status'] = $limit_status;
    }

    return $query_args;
}

function iss_register_build_app_bootstrap_config(array $attributes, array $rest_roots): array
{
    return [
        'apiRoot' => (string) ($rest_roots['path'] ?? '/wp-json/' . ISS_REGISTER_REST_NAMESPACE),
        'defaultView' => (string) ($attributes['default_view'] ?? 'discover'),
        'limitArea' => (string) ($attributes['limit_area'] ?? ''),
        'limitStatus' => (string) ($attributes['limit_status'] ?? ''),
        'restNonce' => wp_create_nonce('wp_rest'),
        'showFeedback' => !empty($attributes['show_feedback']),
        'enableExport' => !empty($attributes['enable_export']),
    ];
}

function iss_register_build_app_bootstrap_payload(array $bootstrap_config): array
{
    return [
        'config' => $bootstrap_config,
        'data' => iss_register_build_summary_places_payload(
            iss_register_build_bootstrap_summary_query_args($bootstrap_config)
        ),
    ];
}

function iss_register_render_bootstrap_payload_tag(array $payload): string
{
    $encoded = wp_json_encode(
        $payload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if (!is_string($encoded) || $encoded === '') {
        $encoded = '{"config":{},"data":{"places":[]}}';
    }

    return sprintf(
        '<script type="application/json" data-register-bootstrap>%s</script>',
        $encoded
    );
}
