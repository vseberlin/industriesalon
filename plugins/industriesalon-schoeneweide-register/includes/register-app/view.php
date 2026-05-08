<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_register_build_tab_items(string $active_tab): array
{
    $tab_definitions = [
        ['target' => 'discover', 'label' => 'Entdecken'],
        ['target' => 'places', 'label' => 'Orte'],
        ['target' => 'then-now', 'label' => 'Damals & Heute'],
        ['target' => 'research', 'label' => 'Recherche'],
        ['target' => 'detail', 'label' => 'Detail', 'hidden' => true],
    ];

    return array_map(static function (array $tab) use ($active_tab): array {
        $target = (string) ($tab['target'] ?? '');

        return [
            'target' => $target,
            'label' => (string) ($tab['label'] ?? $target),
            'is_active' => $target === $active_tab,
            'is_hidden' => !empty($tab['hidden']),
        ];
    }, $tab_definitions);
}

function iss_register_build_research_links(array $rest_roots): array
{
    $api_root_url = isset($rest_roots['url']) && is_string($rest_roots['url'])
        ? untrailingslashit($rest_roots['url'])
        : untrailingslashit(rest_url(ISS_REGISTER_REST_NAMESPACE));

    return [
        'places_url' => esc_url($api_root_url . '/places'),
        'export_url' => esc_url($api_root_url . '/export'),
        'feedback_url' => esc_url($api_root_url . '/feedback'),
    ];
}

function iss_register_build_app_view(array $attributes, array $rest_roots): array
{
    $active_tab = (string) ($attributes['default_view'] ?? 'discover');

    return [
        'active_tab' => $active_tab,
        'show_intro' => !empty($attributes['show_intro']),
        'show_feedback' => !empty($attributes['show_feedback']),
        'enable_export' => !empty($attributes['enable_export']),
        'tab_items' => iss_register_build_tab_items($active_tab),
        'research_links' => iss_register_build_research_links($rest_roots),
    ];
}
