<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-fuehrung-data',
        __('Führungsdaten', 'iss-fuehrungen'),
        'iss_fuehrungen_render_meta_box',
        ISS_FUEHRUNGEN_POST_TYPE,
        'side',
        'high'
    );
});

function iss_fuehrungen_render_meta_box($post) {
    wp_nonce_field('iss_fuehrung_save_meta', 'iss_fuehrung_meta_nonce');

    $fields = [
        'duration'      => __('Dauer', 'iss-fuehrungen'),
        'meeting_point' => __('Treffpunkt', 'iss-fuehrungen'),
        'target_group'  => __('Zielgruppe', 'iss-fuehrungen'),
        'price_note'    => __('Preishinweis', 'iss-fuehrungen'),
        'booking_note'  => __('Buchungshinweis', 'iss-fuehrungen'),
        'booking_mode'  => __('Buchungsmodus', 'iss-fuehrungen'),
        'allow_on_demand_with_calendar' => __('Auf Anfrage zusätzlich erlauben (bei Modus Auto)', 'iss-fuehrungen'),
        'inquiry_url'   => __('Anfrage-URL', 'iss-fuehrungen'),
        'inquiry_label' => __('Anfrage-Button Label', 'iss-fuehrungen'),
        'inquiry_note'  => __('Anfrage-Hinweis', 'iss-fuehrungen'),
        'tour_badge'    => __('Badge', 'iss-fuehrungen'),
        'tour_icon'     => __('Icon', 'iss-fuehrungen'),
        'hero_gallery_ids' => __('Hero-Galerie (Bilder)', 'iss-fuehrungen'),
        'sort_weight'   => __('Sortierung', 'iss-fuehrungen'),
    ];

    foreach ($fields as $key => $label) {
        $value = get_post_meta($post->ID, $key, true);
        echo '<p>';
        echo '<label for="iss_' . esc_attr($key) . '"><strong>' . esc_html($label) . '</strong></label>';

        if ($key === 'booking_mode') {
            $mode = $value ?: 'auto';
            $options = [
                'auto' => __('Automatisch (nach Verfügbarkeit)', 'iss-fuehrungen'),
                'calendar' => __('Kalender-Termine', 'iss-fuehrungen'),
                'on_demand' => __('Nur auf Anfrage', 'iss-fuehrungen'),
                'hybrid' => __('Kalender + Anfrage', 'iss-fuehrungen'),
            ];
            echo '<select class="widefat" id="iss_' . esc_attr($key) . '" name="iss_fuehrung[' . esc_attr($key) . ']">';
            foreach ($options as $option_value => $option_label) {
                printf('<option value="%s" %s>%s</option>', esc_attr($option_value), selected($mode, $option_value, false), esc_html($option_label));
            }
            echo '</select>';
        } elseif ($key === 'allow_on_demand_with_calendar') {
            echo '<label><input type="checkbox" name="iss_fuehrung[' . esc_attr($key) . ']" value="1" ' . checked(!empty($value), true, false) . '> ' . esc_html__('Ja', 'iss-fuehrungen') . '</label>';
        } elseif ($key === 'booking_note' || $key === 'inquiry_note') {
            echo '<textarea class="widefat" rows="3" id="iss_' . esc_attr($key) . '" name="iss_fuehrung[' . esc_attr($key) . ']">' . esc_textarea((string) $value) . '</textarea>';
        } elseif ($key === 'hero_gallery_ids') {
            $ids = array_filter(array_map('absint', explode(',', (string) $value)));
            echo '<div class="iss-hero-gallery-field" data-input-id="iss_' . esc_attr($key) . '">';
            echo '<input type="hidden" id="iss_' . esc_attr($key) . '" name="iss_fuehrung[' . esc_attr($key) . ']" value="' . esc_attr(implode(',', $ids)) . '">';
            echo '<p style="margin:8px 0;">';
            echo '<button type="button" class="button iss-hero-gallery-select">' . esc_html__('Bilder auswählen', 'iss-fuehrungen') . '</button> ';
            echo '<button type="button" class="button-link iss-hero-gallery-clear">' . esc_html__('Leeren', 'iss-fuehrungen') . '</button>';
            echo '</p>';
            echo '<div class="iss-hero-gallery-preview" style="display:flex;flex-wrap:wrap;gap:6px;"></div>';
            echo '<p class="description">' . esc_html__('Diese Bilder erscheinen unter dem Hero-Bild. Klick auf ein Thumbnail tauscht das große Hero-Bild. Mehrere Bilder: nacheinander auswählen oder im Medienfenster mehrfach markieren.', 'iss-fuehrungen') . '</p>';
            echo '</div>';
        } elseif ($key === 'sort_weight') {
            echo '<input class="widefat" type="number" step="1" min="0" id="iss_' . esc_attr($key) . '" name="iss_fuehrung[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
        } elseif ($key === 'inquiry_url') {
            echo '<input class="widefat" type="url" id="iss_' . esc_attr($key) . '" name="iss_fuehrung[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
        } else {
            echo '<input class="widefat" type="text" id="iss_' . esc_attr($key) . '" name="iss_fuehrung[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
        }
        echo '</p>';
    }

    $tour_color = get_post_meta($post->ID, 'tour_color', true);
    $tour_color = $tour_color ?: 'red';
    $is_featured = (bool) get_post_meta($post->ID, 'is_featured', true);

    echo '<p><label for="iss_tour_color"><strong>' . esc_html__('Farbakzent', 'iss-fuehrungen') . '</strong></label>';
    echo '<select class="widefat" id="iss_tour_color" name="iss_fuehrung[tour_color]">';
    foreach (['red' => 'Rot', 'blue' => 'Blau', 'green' => 'Grün', 'yellow' => 'Gelb', 'brown' => 'Braun'] as $value => $label) {
        printf('<option value="%s" %s>%s</option>', esc_attr($value), selected($tour_color, $value, false), esc_html($label));
    }
    echo '</select></p>';

    echo '<p><label><input type="checkbox" name="iss_fuehrung[is_featured]" value="1" ' . checked($is_featured, true, false) . '> ' . esc_html__('Auf Landingpages hervorheben', 'iss-fuehrungen') . '</label></p>';

    echo '<p style="margin-top:1rem;color:#666;font-size:12px;line-height:1.5;">';
    echo esc_html__('Lange Beschreibung, Absätze und Medien bitte im normalen Editor pflegen. Hier nur strukturierte Fakten eintragen.', 'iss-fuehrungen');
    echo '</p>';
}

add_action('save_post_' . ISS_FUEHRUNGEN_POST_TYPE, function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!isset($_POST['iss_fuehrung_meta_nonce']) || !wp_verify_nonce((string) $_POST['iss_fuehrung_meta_nonce'], 'iss_fuehrung_save_meta')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw = isset($_POST['iss_fuehrung']) && is_array($_POST['iss_fuehrung']) ? wp_unslash($_POST['iss_fuehrung']) : [];
    $fields = iss_fuehrungen_meta_fields();

    foreach ($fields as $key => $config) {
        $value = $raw[$key] ?? ($config['type'] === 'boolean' ? '' : $config['default']);
        $sanitizer = $config['sanitize'];
        $value = is_callable($sanitizer) ? $sanitizer($value) : call_user_func($sanitizer, $value);

        if ($config['type'] === 'boolean') {
            $value = $value ? '1' : '';
        }

        if ($value === '' || $value === false || $value === null) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }
}, 10, 1);

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== ISS_FUEHRUNGEN_POST_TYPE) {
        return;
    }

    wp_enqueue_media();
    wp_enqueue_script(
        'iss-fuehrungen-admin-gallery',
        ISS_FUEHRUNGEN_URL . 'assets/admin-hero-gallery.js',
        ['jquery'],
        ISS_FUEHRUNGEN_VERSION,
        true
    );
});

add_action('admin_notices', function () {
    if (!function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== ISS_FUEHRUNGEN_POST_TYPE) {
        return;
    }

    $post_id = isset($_GET['post']) ? (int) $_GET['post'] : 0;
    if ($post_id <= 0) {
        return;
    }

    if (!iss_fuehrungen_calendar_warning_required($post_id)) {
        return;
    }

    $edit_url = admin_url('post.php?post=' . $post_id . '&action=edit');
    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('Für diese Führung sind keine zukünftigen Kalender-Termine verknüpft, obwohl der Buchungsmodus einen Kalender erwartet.', 'iss-fuehrungen');
    echo ' ';
    echo '<a href="' . esc_url($edit_url) . '#iss-programm-calendar-mapping">' . esc_html__('Kalender-Zuordnung prüfen', 'iss-fuehrungen') . '</a>';
    echo '</p></div>';
});

function iss_fuehrungen_calendar_warning_required($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    if (!function_exists('iss_fuehrung_get_effective_booking_mode')) {
        return false;
    }

    $mode = iss_fuehrung_get_effective_booking_mode($post_id);
    $calendar_expected = in_array($mode, ['calendar', 'hybrid'], true);
    if (!$calendar_expected) {
        return false;
    }

    if (iss_fuehrungen_has_linked_future_calendar_events($post_id)) {
        return false;
    }

    return true;
}

function iss_fuehrungen_has_linked_future_calendar_events($post_id) {
    if (!function_exists('iss_programm_has_linked_future_events')) {
        return false;
    }

    $post_id = (int) $post_id;
    if ($post_id <= 0) {
        return false;
    }

    return iss_programm_has_linked_future_events($post_id);
}
