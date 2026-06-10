<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_wf_import_render_archive_object_shortcode($atts = []): string
{
    $atts = shortcode_atts([
        'id' => 0,
    ], is_array($atts) ? $atts : [], 'iss_archive_object');

    $post_id = absint($atts['id'] ?? 0);
    if ($post_id <= 0 || get_post_type($post_id) !== ISS_WF_IMPORT_OBJECT_POST_TYPE) {
        return '';
    }

    if (!function_exists('iss_wf_import_render_featured_archive_object')) {
        return '';
    }

    $html = iss_wf_import_render_featured_archive_object($post_id);
    if ($html === '') {
        return '';
    }

    return '<div class="iss-inline-archive-object" data-archive-object-id="' . esc_attr((string) $post_id) . '">' . $html . '</div>';
}
add_shortcode('iss_archive_object', 'iss_wf_import_render_archive_object_shortcode');
