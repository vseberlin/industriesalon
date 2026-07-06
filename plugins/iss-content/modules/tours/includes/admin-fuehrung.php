<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-fuehrung-data',
        __('Pflichtangaben', 'iss-fuehrungen'),
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
        'booking_price_cents' => __('Buchungspreis in Euro', 'iss-fuehrungen'),
        'booking_note'  => __('Buchungshinweis', 'iss-fuehrungen'),
        'booking_mode'  => __('Buchungsmodus', 'iss-fuehrungen'),
        'allow_on_demand_with_calendar' => __('Anfrage zusätzlich erlauben', 'iss-fuehrungen'),
        'inquiry_label' => __('Anfrage-Button Label', 'iss-fuehrungen'),
        'inquiry_note'  => __('Anfrage-Hinweis', 'iss-fuehrungen'),
        'tour_badge'    => __('Kicker / Kartenlabel', 'iss-fuehrungen'),
        'offer_catalog_groups' => __('Art der Führung', 'iss-fuehrungen'),
    ];

    echo '<div class="iss-fuehrung-meta-grid">';

    foreach ($fields as $key => $label) {
        $value = get_post_meta($post->ID, $key, true);
        echo '<p class="iss-fuehrung-meta-field iss-fuehrung-meta-field--' . esc_attr($key) . '">';
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
        } elseif ($key === 'booking_price_cents') {
            $price_cents = max(0, (int) $value);
            $price_display = $price_cents > 0 ? number_format($price_cents / 100, 2, ',', '') : '';
            echo '<input class="widefat" type="text" inputmode="decimal" id="iss_' . esc_attr($key) . '" name="iss_fuehrung_booking_price_display" value="' . esc_attr($price_display) . '" placeholder="12,00">';
        } elseif ($key === 'allow_on_demand_with_calendar') {
            echo '<label><input type="checkbox" name="iss_fuehrung[' . esc_attr($key) . ']" value="1" ' . checked(!empty($value), true, false) . '> ' . esc_html__('Ja', 'iss-fuehrungen') . '</label>';
        } elseif ($key === 'offer_catalog_groups') {
            $selected_groups = iss_fuehrung_sanitize_offer_catalog_groups($value);
            $selected_lookup = array_fill_keys($selected_groups, true);
            echo '<span class="description">' . esc_html__('Leer lassen, um es automatisch zuzuordnen.', 'iss-fuehrungen') . '</span>';
            echo '<span class="iss-fuehrung-meta-checklist">';
            foreach (iss_fuehrung_get_offer_catalog_group_definitions() as $group_key => $group) {
                echo '<label>';
                echo '<input type="checkbox" name="iss_fuehrung[' . esc_attr($key) . '][]" value="' . esc_attr($group_key) . '" ' . checked(isset($selected_lookup[$group_key]), true, false) . '> ';
                echo esc_html((string) ($group['label'] ?? $group_key));
                echo '</label>';
            }
            echo '</span>';
        } elseif ($key === 'inquiry_note') {
            echo '<textarea class="widefat" rows="2" id="iss_' . esc_attr($key) . '" name="iss_fuehrung[' . esc_attr($key) . ']">' . esc_textarea((string) $value) . '</textarea>';
        } else {
            echo '<input class="widefat" type="text" id="iss_' . esc_attr($key) . '" name="iss_fuehrung[' . esc_attr($key) . ']" value="' . esc_attr((string) $value) . '">';
        }
        echo '</p>';
    }

    echo '</div>';

}

function iss_fuehrungen_parse_price_to_cents($value): int {
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    $normalized = str_replace(["\xc2\xa0", ' ', '€'], '', $value);
    if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } elseif (strpos($normalized, ',') !== false) {
        $normalized = str_replace(',', '.', $normalized);
    }

    if (!is_numeric($normalized)) {
        return 0;
    }

    return max(0, (int) round(((float) $normalized) * 100));
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
    $raw['booking_price_cents'] = isset($_POST['iss_fuehrung_booking_price_display'])
        ? iss_fuehrungen_parse_price_to_cents(wp_unslash((string) $_POST['iss_fuehrung_booking_price_display']))
        : 0;

    foreach ($fields as $key => $config) {
        $value = $raw[$key] ?? ($config['type'] === 'boolean' ? '' : $config['default']);
        $sanitizer = $config['sanitize'];
        $value = is_callable($sanitizer) ? $sanitizer($value) : call_user_func($sanitizer, $value);

        if ($config['type'] === 'boolean') {
            $value = $value ? '1' : '';
        }

        if (is_array($value) && empty($value)) {
            delete_post_meta($post_id, $key);
        } elseif ($value === '' || $value === false || $value === null) {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $value);
        }
    }
}, 10, 1);

add_action('admin_notices', function () {
    if (!function_exists('get_current_screen')) {
        return;
    }

    $screen = get_current_screen();
    if (!$screen || $screen->base !== 'post' || $screen->post_type !== ISS_FUEHRUNGEN_POST_TYPE) {
        return;
    }

    $current_post = get_post();
    $post_id = $current_post instanceof WP_Post ? (int) $current_post->ID : 0;
    if ($post_id <= 0) {
        return;
    }

    if (!iss_fuehrungen_calendar_warning_required($post_id)) {
        return;
    }

    $edit_url = admin_url('post.php?post=' . $post_id . '&action=edit');
    echo '<div class="notice notice-warning"><p>';
    echo esc_html__('Für diese Führung sind keine zukünftigen öffentlichen Termine verknüpft, obwohl der Buchungsmodus den Termin-Kalender erwartet.', 'iss-fuehrungen');
    echo ' ';
    echo '<a href="' . esc_url($edit_url) . '#iss-occurrences-calendar-mapping">' . esc_html__('SuperSaaS-Verknüpfung prüfen', 'iss-fuehrungen') . '</a>';
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
