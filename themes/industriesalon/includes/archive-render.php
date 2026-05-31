<?php
/**
 * Theme-owned archive presentation helpers.
 */

if (!defined('ABSPATH')) {
    exit;
}

function industriesalon_archive_render_featured_object(array $context): string
{
    $post_id = (int) ($context['post_id'] ?? 0);
    if ($post_id <= 0) {
        return '';
    }

    $permalink = (string) ($context['permalink'] ?? '');
    $kicker = trim((string) ($context['kicker'] ?? ''));
    $excerpt = trim((string) ($context['excerpt'] ?? ''));
    $summary_meta = is_array($context['summary_meta'] ?? null) ? $context['summary_meta'] : [];

    $html = '<article class="iss-archive-featured-object">';
    $html .= '<a class="iss-media-card iss-media-card--cover iss-media-card--flat iss-archive-featured-object__media" href="' . esc_url($permalink) . '">';
    if (has_post_thumbnail($post_id)) {
        $html .= (string) get_the_post_thumbnail($post_id, 'large');
    }
    $html .= '</a>';

    $html .= '<div class="iss-archive-featured-object__body">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Ausgewähltes Archivobjekt', 'industriesalon') . '</p>';
    $html .= '<h2 class="iss-archive-featured-object__title">' . esc_html(get_the_title($post_id)) . '</h2>';
    if ($kicker !== '') {
        $html .= '<p class="iss-archive-featured-object__type">' . esc_html($kicker) . '</p>';
    }
    if ($excerpt !== '') {
        $html .= '<p class="iss-archive-featured-object__text">' . esc_html($excerpt) . '</p>';
    }

    if ($summary_meta) {
        $html .= '<ul class="iss-archive-featured-object__meta" aria-label="' . esc_attr__('Rahmendaten', 'industriesalon') . '">';
        foreach ($summary_meta as $label => $value) {
            $html .= '<li>';
            $html .= '<span class="iss-archive-featured-object__meta-label">' . esc_html((string) $label) . '</span>';
            $html .= '<span class="iss-archive-featured-object__meta-value">' . esc_html((string) $value) . '</span>';
            $html .= '</li>';
        }
        $html .= '</ul>';
    }

    $html .= '<p class="iss-archive-featured-object__action"><a class="iss-card__link" href="' . esc_url($permalink) . '">' . esc_html__('Objekt öffnen', 'industriesalon') . '</a></p>';
    $html .= '</div>';
    $html .= '</article>';

    return $html;
}

add_filter('iss_wf_import_render_featured_archive_object', function ($rendered, $post_id, $context) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return $rendered;
    }

    return industriesalon_archive_render_featured_object(is_array($context) ? $context : []);
}, 10, 3);
