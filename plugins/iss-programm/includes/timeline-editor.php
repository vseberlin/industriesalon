<?php
if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', function () {
    $post_type = function_exists('iss_timeline_get_post_type') ? iss_timeline_get_post_type() : '';
    if (!$post_type) return;

    add_meta_box(
        'iss_timeline_public_fields',
        'Timeline (Public)',
        'iss_timeline_render_public_metabox',
        $post_type,
        'normal',
        'default'
    );
}, 20);

function iss_timeline_get_mapping_post_type_choices() {
    $defaults = ['fuehrung', 'veranstaltung', 'post', 'page'];
    $choices = apply_filters('iss_timeline_mapping_post_types', $defaults);
    if (!is_array($choices)) {
        $choices = $defaults;
    }

    $choices = array_values(array_unique(array_filter(array_map(static function ($value) {
        return sanitize_key((string) $value);
    }, $choices))));

    return $choices;
}

function iss_timeline_get_mapping_post_options() {
    $post_types = iss_timeline_get_mapping_post_type_choices();
    if (empty($post_types)) {
        return [];
    }

    $posts = get_posts([
        'post_type' => $post_types,
        'post_status' => ['publish', 'draft', 'private', 'pending'],
        'posts_per_page' => 300,
        'orderby' => 'title',
        'order' => 'ASC',
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);

    $options = [];
    foreach ($posts as $content_id) {
        $content_id = (int) $content_id;
        if ($content_id <= 0) {
            continue;
        }

        $title = trim((string) get_the_title($content_id));
        if ($title === '') {
            $title = '(ohne Titel)';
        }
        $post_type = sanitize_key((string) get_post_type($content_id));
        $options[$content_id] = sprintf('#%d %s (%s)', $content_id, $title, $post_type);
    }

    return $options;
}

function iss_timeline_render_public_metabox($post) {
    if (!$post instanceof WP_Post) return;

    $source_post_id = (int) get_post_meta($post->ID, 'source_post_id', true);
    $source_post_type = sanitize_key((string) get_post_meta($post->ID, 'source_post_type', true));
    $mapping_options = iss_timeline_get_mapping_post_options();

    $public_title = (string) get_post_meta($post->ID, 'public_title', true);
    $public_summary = (string) get_post_meta($post->ID, 'public_summary', true);
    $is_visible = get_post_meta($post->ID, 'is_visible', true);
    $is_visible = ($is_visible === '' || $is_visible === null) ? 1 : (int) $is_visible;

    $item_type = (string) get_post_meta($post->ID, 'item_type', true);
    $cta_mode = (string) get_post_meta($post->ID, 'cta_mode', true);
    $cta_url = (string) get_post_meta($post->ID, 'cta_url', true);
    $cta_label = (string) get_post_meta($post->ID, 'cta_label', true);

    wp_nonce_field('iss_timeline_save', 'iss_timeline_nonce');

    echo '<p><label for="iss_timeline_source_post_id"><strong>Inhalt zuordnen</strong></label></p>';
    echo '<p><select id="iss_timeline_source_post_id" name="iss_timeline_source_post_id" class="widefat">';
    echo '<option value="0">— Keine Zuordnung —</option>';
    foreach ($mapping_options as $content_id => $label) {
        printf(
            '<option value="%d" %s>%s</option>',
            (int) $content_id,
            selected($source_post_id, (int) $content_id, false),
            esc_html((string) $label)
        );
    }
    echo '</select></p>';

    if ($source_post_id > 0) {
        $edit_link = get_edit_post_link($source_post_id);
        $mapped_label = '#' . $source_post_id . ' ' . get_the_title($source_post_id);
        echo '<p class="description"><strong>Aktuell verknüpft:</strong> ';
        if ($edit_link) {
            echo '<a href="' . esc_url($edit_link) . '">' . esc_html($mapped_label) . '</a>';
        } else {
            echo esc_html($mapped_label);
        }
        if ($source_post_type !== '') {
            echo ' <code>' . esc_html($source_post_type) . '</code>';
        }
        echo '</p>';
        echo '<p class="description">Diese Kalender-Entry ist bereits mit Inhalt verknüpft. Public-Felder sind ausgeblendet.</p>';
        return;
    }

    echo '<p><label><input type="checkbox" name="iss_timeline_is_visible" value="1" ' . checked($is_visible, 1, false) . '> Sichtbar in Timeline</label></p>';

    echo '<p><label for="iss_timeline_public_title"><strong>Öffentlicher Titel (optional)</strong></label></p>';
    echo '<p><input id="iss_timeline_public_title" name="iss_timeline_public_title" type="text" class="widefat" value="' . esc_attr($public_title) . '" placeholder="Titel für die Timeline" /></p>';

    echo '<p><label for="iss_timeline_public_summary"><strong>Öffentliche Kurzbeschreibung (optional)</strong></label></p>';
    echo '<p><textarea id="iss_timeline_public_summary" name="iss_timeline_public_summary" class="widefat" rows="4" placeholder="1–2 Sätze als Teaser">' . esc_textarea($public_summary) . '</textarea></p>';

    echo '<hr />';

    echo '<p><label for="iss_timeline_item_type"><strong>Typ</strong></label></p>';
    echo '<p><select id="iss_timeline_item_type" name="iss_timeline_item_type" class="widefat">';
    $types = [
        '' => '—',
        'tour' => 'Führung',
        'event' => 'Veranstaltung',
    ];
    foreach ($types as $k => $label) {
        echo '<option value="' . esc_attr($k) . '"' . selected($item_type, $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="iss_timeline_cta_mode"><strong>Link/CTA</strong></label></p>';
    echo '<p><select id="iss_timeline_cta_mode" name="iss_timeline_cta_mode" class="widefat">';
    $modes = [
        'auto' => 'Auto (Details →, sonst extern)',
        'details' => 'Details (verknüpfter Beitrag)',
        'booking' => 'Buchung (nächster Termin)',
        'external' => 'Extern (URL)',
    ];
    foreach ($modes as $k => $label) {
        echo '<option value="' . esc_attr($k) . '"' . selected($cta_mode ?: 'auto', $k, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="iss_timeline_cta_url"><strong>Externe URL (optional)</strong></label></p>';
    echo '<p><input id="iss_timeline_cta_url" name="iss_timeline_cta_url" type="url" class="widefat" value="' . esc_attr($cta_url) . '" placeholder="https://…" /></p>';

    echo '<p><label for="iss_timeline_cta_label"><strong>Link-Label (optional)</strong></label></p>';
    echo '<p><input id="iss_timeline_cta_label" name="iss_timeline_cta_label" type="text" class="widefat" value="' . esc_attr($cta_label) . '" placeholder="Mehr erfahren" /></p>';

    echo '<p class="description">Hinweis: Wenn ein verknüpfter Beitrag existiert (<code>source_post_id</code>), verwendet die Timeline standardmäßig dessen Titel/Teaser als Fallback.</p>';
}

add_action('save_post', function ($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    if (!isset($_POST['iss_timeline_nonce']) || !wp_verify_nonce((string) $_POST['iss_timeline_nonce'], 'iss_timeline_save')) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) return;

    $post_type = get_post_type($post_id);
    $expected = function_exists('iss_timeline_get_post_type') ? iss_timeline_get_post_type() : '';
    if (!$expected || $post_type !== $expected) return;

    $selected_source_post_id = isset($_POST['iss_timeline_source_post_id']) ? (int) $_POST['iss_timeline_source_post_id'] : 0;
    if ($selected_source_post_id > 0) {
        $selected_post = get_post($selected_source_post_id);
        if (!($selected_post instanceof WP_Post)) {
            $selected_source_post_id = 0;
        }
    }

    $selected_source_post_type = $selected_source_post_id > 0
        ? sanitize_key((string) get_post_type($selected_source_post_id))
        : '';

    update_post_meta($post_id, 'source_post_id', $selected_source_post_id);
    update_post_meta($post_id, 'source_post_type', $selected_source_post_type);

    $series_key = trim((string) get_post_meta($post_id, 'series_key', true));
    if ($series_key !== '') {
        if ($selected_source_post_id > 0 && function_exists('iss_programm_remember_series_mapping')) {
            $supersaas_title = trim((string) get_post_meta($post_id, 'supersaas_title', true));
            $fallback_url = trim((string) get_post_meta($post_id, 'booking_url', true));
            $tag = '';
            if (function_exists('iss_programm_get_series_map_entry')) {
                $entry = iss_programm_get_series_map_entry($series_key);
                if (is_array($entry) && !empty($entry['tag'])) {
                    $tag = strtoupper(sanitize_text_field((string) $entry['tag']));
                }
            }

            iss_programm_remember_series_mapping(
                $series_key,
                $selected_source_post_id,
                $selected_source_post_type,
                $supersaas_title,
                $tag,
                $fallback_url
            );

            if ($tag !== '' && function_exists('iss_programm_remember_source_mapping')) {
                iss_programm_remember_source_mapping(
                    $tag,
                    $fallback_url,
                    $selected_source_post_id,
                    $selected_source_post_type
                );
            }

            if (function_exists('iss_programm_relink_series_to_post')) {
                iss_programm_relink_series_to_post(
                    $selected_source_post_id,
                    [$series_key],
                    $selected_source_post_type
                );
            }
        } elseif ($selected_source_post_id <= 0 && function_exists('iss_programm_clear_series_mapping_for_key')) {
            iss_programm_clear_series_mapping_for_key($series_key);
        }
    }

    if ($selected_source_post_id > 0) {
        // When content mapping exists, hide/ignore manual timeline fields.
        return;
    }

    $visible = isset($_POST['iss_timeline_is_visible']) ? 1 : 0;
    update_post_meta($post_id, 'is_visible', $visible);

    $public_title = isset($_POST['iss_timeline_public_title']) ? sanitize_text_field((string) $_POST['iss_timeline_public_title']) : '';
    $public_summary = isset($_POST['iss_timeline_public_summary']) ? sanitize_textarea_field((string) $_POST['iss_timeline_public_summary']) : '';
    update_post_meta($post_id, 'public_title', $public_title);
    update_post_meta($post_id, 'public_summary', $public_summary);

    $item_type = isset($_POST['iss_timeline_item_type']) ? sanitize_key((string) $_POST['iss_timeline_item_type']) : '';
    if ($item_type !== '') {
        update_post_meta($post_id, 'item_type', $item_type);
    }

    $cta_mode = isset($_POST['iss_timeline_cta_mode']) ? sanitize_key((string) $_POST['iss_timeline_cta_mode']) : 'auto';
    update_post_meta($post_id, 'cta_mode', $cta_mode);

    $cta_url = isset($_POST['iss_timeline_cta_url']) ? esc_url_raw((string) $_POST['iss_timeline_cta_url']) : '';
    update_post_meta($post_id, 'cta_url', $cta_url);

    $cta_label = isset($_POST['iss_timeline_cta_label']) ? sanitize_text_field((string) $_POST['iss_timeline_cta_label']) : '';
    update_post_meta($post_id, 'cta_label', $cta_label);
});

$iss_timeline_post_type = function_exists('iss_timeline_get_post_type')
    ? iss_timeline_get_post_type()
    : 'iss_calendar_item';

add_filter('manage_' . $iss_timeline_post_type . '_posts_columns', function ($cols) {
    // Insert a few public-facing columns for easier editorial work.
    $out = [];
    foreach ($cols as $key => $label) {
        $out[$key] = $label;
        if ($key === 'title') {
            $out['public_title'] = 'Public Title';
            $out['is_visible'] = 'Visible';
        }
    }
    return $out;
}, 20);

add_action('manage_' . $iss_timeline_post_type . '_posts_custom_column', function ($col, $post_id) {
    if ($col === 'public_title') {
        $t = (string) get_post_meta($post_id, 'public_title', true);
        echo $t !== '' ? esc_html($t) : '—';
        return;
    }
    if ($col === 'is_visible') {
        $v = get_post_meta($post_id, 'is_visible', true);
        if ($v === '' || $v === null) {
            $v = (int) get_post_meta($post_id, 'is_public', true);
        }
        echo ((int) $v === 1) ? '✓' : '—';
        return;
    }
}, 20, 2);
