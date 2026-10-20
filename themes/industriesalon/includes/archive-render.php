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
    $title = trim((string) ($context['title'] ?? ''));
    $kicker = trim((string) ($context['kicker'] ?? ''));
    $excerpt = trim((string) ($context['excerpt'] ?? ''));
    $summary_meta = is_array($context['summary_meta'] ?? null) ? $context['summary_meta'] : [];
    if ($title === '') {
        $title = get_the_title($post_id);
    }

    $html = '<article class="iss-archive-featured-object">';
    $html .= '<a class="iss-media-card iss-media-card--cover iss-media-card--flat iss-archive-featured-object__media" href="' . esc_url($permalink) . '" aria-label="' . esc_attr($title) . '">';
    if (has_post_thumbnail($post_id)) {
        $html .= (string) get_the_post_thumbnail($post_id, 'large');
    }
    $html .= '</a>';

    $html .= '<div class="iss-archive-featured-object__body">';
    $html .= '<p class="iss-kicker iss-kicker--compact">' . esc_html__('Ausgewähltes Archivobjekt', 'industriesalon') . '</p>';
    $html .= '<h2 class="iss-archive-featured-object__title">' . esc_html($title) . '</h2>';
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

/** Show recorded object facts through the existing metadata block and archive owner. */
add_filter('iss_content_meta_presentation', static function ($html, int $post_id, array $rows, array $attributes) {
    if (($attributes['presentation'] ?? '') !== 'archive-object' || get_post_type($post_id) !== 'archivobjekt') {
        return $html;
    }
    if (post_password_required($post_id) || !function_exists('iss_wf_import_get_object_service')) {
        return '';
    }
    $object = iss_wf_import_get_object_service()->get_projection_for_post($post_id);
    if (!$object) {
        return '';
    }
    $object['object_type_label'] = iss_wf_import_get_archive_object_browser_kicker($post_id);
    $subjects = wp_get_post_terms($post_id, ISS_WF_IMPORT_FAMILY_TAXONOMY, ['fields' => 'names']);
    $object['subject_label'] = is_array($subjects) ? implode(', ', $subjects) : '';
    $facts = [
        'object_type_label' => __('Art des Archivstücks', 'industriesalon'),
        'subject_label' => __('Motiv / Bereich', 'industriesalon'),
        'year_label' => __('Datierung', 'industriesalon'),
        'creator_label' => __('Urheber', 'industriesalon'),
        'inventory_number' => __('Inventarnummer', 'industriesalon'),
        'material' => __('Material', 'industriesalon'),
        'dimensions' => __('Maße', 'industriesalon'),
        'institution_name' => __('Sammlung', 'industriesalon'),
        'rights_status' => __('Rechte', 'industriesalon'),
        'rights_holder' => __('Rechteinhaber', 'industriesalon'),
        'md_image_rights' => __('Bildrechte', 'industriesalon'),
        'md_image_owner' => __('Bildnachweis', 'industriesalon'),
        'md_metadata_rights_status' => __('Rechte an den Metadaten', 'industriesalon'),
        'md_metadata_rights_holder' => __('Nachweis der Metadaten', 'industriesalon'),
    ];
    $items = '';
    foreach ($facts as $key => $label) {
        $value = trim((string) ($object[$key] ?? ''));
        if ($value !== '') {
            $items .= '<div class="iss-info-row"><div class="iss-info-row__main"><p class="iss-info-row__text"><strong>' . esc_html($label) . '</strong><br>' . esc_html($value) . '</p></div></div>';
        }
    }
    $original_source = (string) ($object['source_url'] ?? '');
    $source = iss_wf_import_get_archive_source_link_url($original_source, (int) get_post_thumbnail_id($post_id));
    if ($source !== '') {
        $items .= '<p><a class="iss-action-link" href="' . esc_url($source) . '">' . esc_html($source === $original_source ? __('Zum Originalnachweis', 'industriesalon') : __('Bild vergrößern', 'industriesalon')) . '</a></p>';
    }
    if ($original_source !== '' && $source !== $original_source) {
        $items .= '<p><strong>' . esc_html__('Originalnachweis', 'industriesalon') . ':</strong> ' . esc_html((string) wp_parse_url($original_source, PHP_URL_HOST)) . '</p>';
    }
    $relations = iss_wf_import_get_relation_service()->get_projection_for_post($post_id);
    foreach (['series' => __('Serie', 'industriesalon'), 'collections' => __('Bestand', 'industriesalon')] as $family => $label) {
        foreach ($relations[$family] ?? [] as $relation) {
            $name = trim((string) ($relation['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $url = iss_wf_import_get_archive_source_link_url((string) ($relation['source_url'] ?? ''));
            $items .= '<p><strong>' . esc_html($label) . ':</strong> ' . ($url !== '' ? '<a href="' . esc_url($url) . '">' . esc_html($name) . '</a>' : esc_html($name)) . '</p>';
        }
    }
    return $items !== '' ? '<aside class="iss-info-panel"><h2 class="iss-info-panel__title">' . esc_html__('Zum Objekt', 'industriesalon') . '</h2><div class="iss-info-panel__rows">' . $items . '</div></aside>' : '';
}, 10, 4);

/** Enlarge the existing public preview; never expose private archival master paths. */
add_filter('render_block_core/post-featured-image', static function (string $html, array $block, WP_Block $instance): string {
    $id = (int) ($instance->context['postId'] ?? 0);
    if (!is_singular('archivobjekt') || $id !== (int) get_queried_object_id() || post_password_required($id)) {
        return $html;
    }
    $url = wp_get_attachment_image_url(get_post_thumbnail_id($id), 'full');
    return $url && $html !== '' ? $html . '<p><a class="iss-action-link" href="' . esc_url($url) . '">' . esc_html__('Bild vergrößern', 'industriesalon') . '</a></p>' : $html;
}, 20, 3);
