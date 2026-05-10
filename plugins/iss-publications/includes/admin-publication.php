<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_publications_is_disallowed_page_template(string $template_slug): bool
{
    $template_slug = preg_replace('/\.html$/', '', trim($template_slug));
    return in_array($template_slug, ['single-tour', 'single-tour-on-demand'], true);
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
        'iss-publication-corpus',
        __('Korpus', 'iss-publications'),
        'iss_publications_render_corpus_box',
        ISS_PUBLICATIONS_POST_TYPE,
        'side',
        'default'
    );
});

function iss_publications_get_ausstellung_choices(): array
{
    if (!post_type_exists('ausstellung')) {
        return [];
    }

    $choices = [];
    $posts = get_posts([
        'post_type' => 'ausstellung',
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $choices[] = [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
        ];
    }

    return $choices;
}

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
    $layout = (string) get_post_meta($post->ID, '_iss_publication_layout', true);
    if (!in_array($layout, ['longread', 'timeline'], true)) {
        $layout = 'standard';
    }

    echo '<p>';
    echo '<label for="_iss_publication_layout"><strong>' . esc_html__('Layout', 'iss-publications') . '</strong></label>';
    echo '<select class="widefat" id="_iss_publication_layout" name="iss_publication[_iss_publication_layout]">';
    echo '<option value="standard"' . selected($layout, 'standard', false) . '>' . esc_html__('Publikation / Broschüre', 'iss-publications') . '</option>';
    echo '<option value="longread"' . selected($layout, 'longread', false) . '>' . esc_html__('Longread', 'iss-publications') . '</option>';
    echo '<option value="timeline"' . selected($layout, 'timeline', false) . '>' . esc_html__('Longread / Zeitleiste', 'iss-publications') . '</option>';
    echo '</select>';
    echo '</p>';
    echo '<p><label><input type="checkbox" name="iss_publication[_iss_publication_featured]" value="1" ' . checked($featured, true, false) . '> ' . esc_html__('Als ausgewählte Publikation hervorheben', 'iss-publications') . '</label></p>';
    echo '<p style="margin-top:1rem;color:#666;font-size:12px;line-height:1.5;">';
    echo esc_html__('Longread ist für fortlaufende Lesestücke gedacht. Longread / Zeitleiste öffnet die Inhaltsfläche für chronologische Entwicklungsstücke mit Jahr-zu-Jahr-Abschnitten. Verkauf bleibt optional.', 'iss-publications');
    echo '</p>';
}

function iss_publications_render_corpus_box($post) {
    $source_ausstellung_id = (int) get_post_meta($post->ID, '_iss_publication_source_ausstellung_id', true);
    $choices = iss_publications_get_ausstellung_choices();

    echo '<p>';
    echo '<label for="_iss_publication_source_ausstellung_id"><strong>' . esc_html__('Quell-Ausstellung', 'iss-publications') . '</strong></label>';
    echo '<select class="widefat" id="_iss_publication_source_ausstellung_id" name="iss_publication[_iss_publication_source_ausstellung_id]">';
    echo '<option value="">' . esc_html__('Keine Ausstellung auswählen', 'iss-publications') . '</option>';
    foreach ($choices as $choice) {
        echo '<option value="' . esc_attr((string) $choice['id']) . '"' . selected($source_ausstellung_id, (int) $choice['id'], false) . '>' . esc_html((string) $choice['title']) . '</option>';
    }
    echo '</select>';
    echo '</p>';
    echo '<p style="margin-top:0.75rem;color:#666;font-size:12px;line-height:1.5;">';
    echo esc_html__('Wenn diese Publikation die lineare Lesefassung einer Ausstellung ist, hier die Ausstellung wählen. Die Kapitelzuordnung bleibt dort an einem Ort gepflegt.', 'iss-publications');
    echo '</p>';
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

    $previous_source_ausstellung_id = (int) get_post_meta($post_id, '_iss_publication_source_ausstellung_id', true);

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

    $sale_enabled = !empty(get_post_meta($post_id, '_iss_publication_sale_enabled', true));
    $price_cents = (int) get_post_meta($post_id, '_iss_publication_price_cents', true);
    if ($sale_enabled && $price_cents <= 0) {
        delete_post_meta($post_id, '_iss_publication_sale_enabled');
    }

    $source_ausstellung_id = (int) get_post_meta($post_id, '_iss_publication_source_ausstellung_id', true);
    if ($previous_source_ausstellung_id > 0 && $previous_source_ausstellung_id !== $source_ausstellung_id) {
        $previous_companion = (int) get_post_meta($previous_source_ausstellung_id, 'iss_companion_publication_id', true);
        if ($previous_companion === (int) $post_id) {
            delete_post_meta($previous_source_ausstellung_id, 'iss_companion_publication_id');
        }
    }

    if ($source_ausstellung_id > 0) {
        update_post_meta($source_ausstellung_id, 'iss_companion_publication_id', (int) $post_id);
    }
}, 10, 1);

add_action('init', function () {
    $cleanup_option = 'iss_publications_page_template_cleanup_v1';
    if (get_option($cleanup_option)) {
        return;
    }

    $posts = get_posts([
        'post_type' => ISS_PUBLICATIONS_POST_TYPE,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'meta_key' => '_wp_page_template',
        'meta_value' => 'single-tour',
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post_id) {
        iss_publications_clear_disallowed_page_template((int) $post_id);
    }

    $posts = get_posts([
        'post_type' => ISS_PUBLICATIONS_POST_TYPE,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'meta_key' => '_wp_page_template',
        'meta_value' => 'single-tour-on-demand',
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post_id) {
        iss_publications_clear_disallowed_page_template((int) $post_id);
    }

    update_option($cleanup_option, 1, false);
}, 30);
