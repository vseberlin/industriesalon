<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_register_fallback_projector(array $projectors): array
{
    $projectors[ISS_PUBLICATIONS_POST_TYPE] = [
        'label' => __('Publikationen', 'iss-publications'),
        'callback' => 'iss_publications_fallback_projector',
    ];

    return $projectors;
}
add_filter('iss_fallback_projectors', 'iss_publications_register_fallback_projector');

function iss_publications_fallback_projector(): array
{
    if (!function_exists('iss_content_fallback_default_projection_for_post')) {
        return [];
    }

    $items = [];
    foreach (iss_content_fallback_source_posts(ISS_PUBLICATIONS_POST_TYPE) as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $facts = [];
        if (function_exists('iss_publications_get_summary_meta')) {
            foreach (iss_publications_get_summary_meta((int) $post->ID) as $label => $value) {
                $facts[] = ['label' => (string) $label, 'value' => (string) $value];
            }
        }

        $items[] = iss_content_fallback_default_projection_for_post($post, 'iss-publikationen', 'publication', $facts);
    }

    return $items;
}

