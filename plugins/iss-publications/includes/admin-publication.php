<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_is_disallowed_page_template(string $template_slug): bool
{
    $template_slug = preg_replace('/\.html$/', '', trim($template_slug));
    return in_array($template_slug, iss_publications_disallowed_page_templates(), true);
}

function iss_publications_disallowed_page_templates(): array
{
    return [
        // Retired Führung template slugs kept here only to clean stale publication meta.
        'single-tour',
        'single-tour-on-demand',
    ];
}

function iss_publications_clear_disallowed_page_template(int $post_id): bool
{
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    $template_slug = (string) get_post_meta($post_id, '_wp_page_template', true);
    if (!iss_publications_is_disallowed_page_template($template_slug)) {
        return false;
    }

    delete_post_meta($post_id, '_wp_page_template');
    clean_post_cache($post_id);
    return true;
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-publication-bibliography',
        __('Bibliografische Angaben', 'iss-publications'),
        'iss_publications_render_bibliography_box',
        ISS_PUBLICATIONS_POST_TYPE,
        'normal',
        'high'
    );

    add_meta_box(
        'iss-publication-sale',
        __('Verkauf', 'iss-publications'),
        'iss_publications_render_sale_box',
        ISS_PUBLICATIONS_POST_TYPE,
        'side',
        'high'
    );

    add_meta_box(
        'iss-publication-display',
        __('Darstellung', 'iss-publications'),
        'iss_publications_render_display_box',
        ISS_PUBLICATIONS_POST_TYPE,
        'side',
        'default'
    );

    add_meta_box(
        'iss-publication-related-publications',
        __('Weiterlesen', 'iss-publications'),
        'iss_publications_render_related_publications_box',
        ISS_PUBLICATIONS_POST_TYPE,
        'normal',
        'default'
    );

});

function iss_publications_render_bibliography_box($post) {
    wp_nonce_field('iss_publication_save_meta', 'iss_publication_meta_nonce');

    $fields = [
        '_iss_publication_subtitle'  => __('Untertitel', 'iss-publications'),
        '_iss_publication_author'    => __('Autor:in', 'iss-publications'),
        '_iss_publication_editor'    => __('Herausgeber:in', 'iss-publications'),
        '_iss_publication_year'      => __('Jahr', 'iss-publications'),
        '_iss_publication_pages'     => __('Seiten', 'iss-publications'),
        '_iss_publication_format'    => __('Format', 'iss-publications'),
        '_iss_publication_language'  => __('Sprache', 'iss-publications'),
        '_iss_publication_isbn'      => __('ISBN', 'iss-publications'),
        '_iss_publication_publisher' => __('Verlag', 'iss-publications'),
    ];

    foreach ($fields as $key => $label) {
        $value = get_post_meta($post->ID, $key, true);
        echo '<p>';
        echo '<label for="' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';
        if ($key === '_iss_publication_pages') {
            echo '<input class="widefat" type="number" min="0" step="1" id="' . esc_attr($key) . '" name="iss_publication[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
        } else {
            echo '<input class="widefat" type="text" id="' . esc_attr($key) . '" name="iss_publication[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
        }
        echo '</p>';
    }
}

function iss_publications_render_sale_box($post) {
    $sale_enabled = !empty(get_post_meta($post->ID, '_iss_publication_sale_enabled', true));
    $price_cents = (int) get_post_meta($post->ID, '_iss_publication_price_cents', true);
    $price_display = $price_cents > 0 ? number_format($price_cents / 100, 2, ',', '') : '';
    $cta_label = (string) get_post_meta($post->ID, '_iss_publication_cta_label', true);
    $gateway_description = (string) get_post_meta($post->ID, '_iss_publication_gateway_description', true);

    echo '<p><label><input type="checkbox" name="iss_publication[_iss_publication_sale_enabled]" value="1" ' . checked($sale_enabled, true, false) . '> ' . esc_html__('Verkauf aktivieren', 'iss-publications') . '</label></p>';
    echo '<p>';
    echo '<label for="iss_publication_price_display"><strong>' . esc_html__('Preis in Euro', 'iss-publications') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_publication_price_display" name="iss_publication_price_display" value="' . esc_attr($price_display) . '" placeholder="12,00">';
    echo '</p>';
    echo '<p>';
    echo '<label for="_iss_publication_cta_label"><strong>' . esc_html__('CTA-Label', 'iss-publications') . '</strong></label>';
    echo '<input class="widefat" type="text" id="_iss_publication_cta_label" name="iss_publication[_iss_publication_cta_label]" value="' . esc_attr($cta_label) . '" placeholder="' . esc_attr__('Publikation bestellen', 'iss-publications') . '">';
    echo '</p>';
    echo '<p>';
    echo '<label for="_iss_publication_gateway_description"><strong>' . esc_html__('Bestellhinweis', 'iss-publications') . '</strong></label>';
    echo '<textarea class="widefat" rows="4" id="_iss_publication_gateway_description" name="iss_publication[_iss_publication_gateway_description]">' . esc_textarea($gateway_description) . '</textarea>';
    echo '</p>';
    echo '<p style="margin-top:1rem;color:#666;font-size:12px;line-height:1.5;">';
    echo esc_html__('Für v1 nur Preis und Hinweis pflegen. Versand, Lagerbestand und weitere Handelslogik bleiben bewusst außen vor.', 'iss-publications');
    echo '</p>';
}

function iss_publications_render_display_box($post) {
    $featured = !empty(get_post_meta($post->ID, '_iss_publication_featured', true));
    $layout = iss_publications_get_layout((int) $post->ID);
    $photoalbum_archivset_id = (int) get_post_meta((int) $post->ID, ISS_PUBLICATIONS_PHOTOALBUM_ARCHIVSET_META_KEY, true);
    if (!in_array($layout, ['longread', 'timeline', 'photoalbum'], true)) {
        $layout = 'standard';
    }

    echo '<p>';
    echo '<label for="_iss_publication_layout"><strong>' . esc_html__('Layout', 'iss-publications') . '</strong></label>';
    echo '<select class="widefat" id="_iss_publication_layout" name="iss_publication[_iss_publication_layout]">';
    echo '<option value="standard"' . selected($layout, 'standard', false) . '>' . esc_html__('Publikation / Broschüre', 'iss-publications') . '</option>';
    echo '<option value="longread"' . selected($layout, 'longread', false) . '>' . esc_html__('Longread', 'iss-publications') . '</option>';
    echo '<option value="timeline"' . selected($layout, 'timeline', false) . '>' . esc_html__('Zeitleiste', 'iss-publications') . '</option>';
    echo '<option value="photoalbum"' . selected($layout, 'photoalbum', false) . '>' . esc_html__('Fotoalbum', 'iss-publications') . '</option>';
    echo '</select>';
    echo '</p>';
    echo '<p>';
    echo '<label for="' . esc_attr(ISS_PUBLICATIONS_PHOTOALBUM_ARCHIVSET_META_KEY) . '"><strong>' . esc_html__('Fotoalbum-Archivset', 'iss-publications') . '</strong></label>';
    echo '<input class="widefat" type="number" min="0" step="1" id="' . esc_attr(ISS_PUBLICATIONS_PHOTOALBUM_ARCHIVSET_META_KEY) . '" name="iss_publication[' . esc_attr(ISS_PUBLICATIONS_PHOTOALBUM_ARCHIVSET_META_KEY) . ']" value="' . esc_attr((string) $photoalbum_archivset_id) . '" placeholder="0">';
    echo '</p>';
    echo '<p><label><input type="checkbox" name="iss_publication[_iss_publication_featured]" value="1" ' . checked($featured, true, false) . '> ' . esc_html__('Als ausgewählte Publikation hervorheben', 'iss-publications') . '</label></p>';
    echo '<p style="margin-top:1rem;color:#666;font-size:12px;line-height:1.5;">';
    echo esc_html__('Longread ist für kapitelbasierte Lesestücke gedacht. Zeitleiste und Fotoalbum nutzen im Block-Editor eigene Publikationsblöcke oder die Starter-Patterns gleichen Namens. Ein Fotoalbum-Archivset testet eine schlanke Albumquelle ohne die Gutenberg-Bilder im Inhalt zu löschen. Verkauf bleibt optional.', 'iss-publications');
    echo '</p>';
}

function iss_publications_render_related_publications_box($post) {
    $selected_ids = get_post_meta((int) $post->ID, ISS_PUBLICATIONS_RELATED_PUBLICATIONS_META_KEY, true);
    $selected_ids = function_exists('iss_publications_sanitize_related_publication_ids')
        ? iss_publications_sanitize_related_publication_ids(is_array($selected_ids) ? $selected_ids : [])
        : [];
    $selected_lookup = array_fill_keys($selected_ids, true);
    $posts = get_posts([
        'post_type' => ISS_PUBLICATIONS_POST_TYPE,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'post__not_in' => [(int) $post->ID],
        'orderby' => [
            'menu_order' => 'ASC',
            'title' => 'ASC',
        ],
        'suppress_filters' => true,
    ]);

    if (!$posts) {
        echo '<p>' . esc_html__('Keine weiteren Publikationen verfügbar.', 'iss-publications') . '</p>';
        return;
    }

    echo '<p style="margin-top:0;color:#666;font-size:12px;line-height:1.5;">';
    echo esc_html__('Explizite Auswahl für den Bereich „Weiterlesen“. Ohne Auswahl kann das Theme weiterhin relationale Treffer verwenden.', 'iss-publications');
    echo '</p>';
    echo '<div style="display:grid;gap:.45rem;max-height:18rem;overflow:auto;padding:.35rem 0;">';
    foreach ($posts as $related_post) {
        if (!$related_post instanceof WP_Post) {
            continue;
        }

        $related_id = (int) $related_post->ID;
        echo '<label style="display:block;line-height:1.35;">';
        echo '<input type="checkbox" name="iss_publication_related_publications[]" value="' . esc_attr((string) $related_id) . '" ' . checked(isset($selected_lookup[$related_id]), true, false) . '> ';
        echo esc_html(get_the_title($related_post));
        echo '</label>';
    }
    echo '</div>';
}

function iss_publications_parse_price_to_cents($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    $normalized = str_replace([' ', '€'], '', $value);
    if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif (strpos($normalized, ',') !== false) {
        $normalized = str_replace(',', '.', $normalized);
    }

    if (!is_numeric($normalized)) {
        return 0;
    }

    return (int) round(((float) $normalized) * 100);
}

add_action('save_post_' . ISS_PUBLICATIONS_POST_TYPE, function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!isset($_POST['iss_publication_meta_nonce']) || !wp_verify_nonce((string) $_POST['iss_publication_meta_nonce'], 'iss_publication_save_meta')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    iss_publications_clear_disallowed_page_template((int) $post_id);

    $raw = isset($_POST['iss_publication']) && is_array($_POST['iss_publication']) ? wp_unslash($_POST['iss_publication']) : [];
    $fields = iss_publications_meta_fields();

    foreach ($fields as $key => $config) {
        if ($key === '_iss_publication_price_cents') {
            $display = isset($_POST['iss_publication_price_display']) ? wp_unslash($_POST['iss_publication_price_display']) : '';
            $value = iss_publications_parse_price_to_cents($display);
        } else {
            $value = $raw[$key] ?? ($config['type'] === 'boolean' ? '' : $config['default']);
            $sanitizer = $config['sanitize'];
            $value = is_callable($sanitizer) ? $sanitizer($value) : call_user_func($sanitizer, $value);
        }

        if ($config['type'] === 'boolean') {
            $value = $value ? '1' : '';
        }

        if ($value === '' || $value === false || $value === null) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }

    $related_publications = isset($_POST['iss_publication_related_publications']) && is_array($_POST['iss_publication_related_publications'])
        ? wp_unslash($_POST['iss_publication_related_publications'])
        : [];
    $related_publications = function_exists('iss_publications_sanitize_related_publication_ids')
        ? iss_publications_sanitize_related_publication_ids($related_publications)
        : array_values(array_filter(array_map('absint', (array) $related_publications)));
    $related_publications = array_values(array_filter($related_publications, static function (int $related_id) use ($post_id): bool {
        return $related_id !== (int) $post_id;
    }));

    if ($related_publications === []) {
        delete_post_meta($post_id, ISS_PUBLICATIONS_RELATED_PUBLICATIONS_META_KEY);
    } else {
        update_post_meta($post_id, ISS_PUBLICATIONS_RELATED_PUBLICATIONS_META_KEY, $related_publications);
    }

    $sale_enabled = !empty(get_post_meta($post_id, '_iss_publication_sale_enabled', true));
    $price_cents = (int) get_post_meta($post_id, '_iss_publication_price_cents', true);
    if ($sale_enabled && $price_cents <= 0) {
        delete_post_meta($post_id, '_iss_publication_sale_enabled');
    }

}, 10, 1);

add_action('init', function () {
    $cleanup_option = 'iss_publications_page_template_cleanup_v1';
    if (get_option($cleanup_option)) {
        return;
    }

    $invalid_templates = iss_publications_disallowed_page_templates();
    $posts = get_posts([
        'post_type' => ISS_PUBLICATIONS_POST_TYPE,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);

    update_meta_cache('post', array_map('absint', $posts));

    foreach ($posts as $post_id) {
        $template = (string) get_post_meta((int) $post_id, '_wp_page_template', true);
        if (in_array($template, $invalid_templates, true)) {
            iss_publications_clear_disallowed_page_template((int) $post_id);
        }
    }

    update_option($cleanup_option, 1, false);
}, 30);
