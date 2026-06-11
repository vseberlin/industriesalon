<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-content-model-veranstaltung',
        __('Veranstaltung', 'iss-content-model'),
        'iss_content_model_render_veranstaltung_box',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'normal',
        'high'
    );

    add_meta_box(
        'iss-content-model-veranstaltung-status',
        __('Redaktionsstatus', 'iss-content-model'),
        'iss_content_model_render_veranstaltung_status_box',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'side',
        'high'
    );

    if (!iss_content_model_acf_handles_ausstellung_meta()) {
        add_meta_box(
            'iss-content-model-ausstellung',
            __('Ausstellungsdaten', 'iss-content-model'),
            'iss_content_model_render_ausstellung_box',
            ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
            'side',
            'high'
        );
    }

    add_meta_box(
        'iss-content-model-projekt',
        __('Projektdaten', 'iss-content-model'),
        'iss_content_model_render_projekt_box',
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        'side',
        'high'
    );

    add_meta_box(
        'iss-content-model-team',
        __('Teamdaten', 'iss-content-model'),
        'iss_content_model_render_team_box',
        ISS_CONTENT_MODEL_TEAM_POST_TYPE,
        'side',
        'high'
    );

    add_meta_box(
        'iss-content-model-video',
        __('Videodaten', 'iss-content-model'),
        'iss_content_model_render_video_box',
        ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        'side',
        'high'
    );
});

function iss_content_model_acf_handles_ausstellung_meta(): bool
{
    return function_exists('acf_add_local_field_group');
}

function iss_content_model_use_simplified_ausstellung_admin(): bool
{
    return !current_user_can('manage_options');
}

function iss_content_model_get_editor_modal_targets(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    $targets = [];

    if (function_exists('iss_wf_import_is_archivset_supported_post_type') && iss_wf_import_is_archivset_supported_post_type($post_type)) {
        $targets['iss-wf-import-archive-picker'] = __('Archivmaterial hinzufügen', 'iss-content-model');
    }

    if (function_exists('iss_graph_is_content_relation_post_type') && iss_graph_is_content_relation_post_type($post_type)) {
        $targets['iss-graph-public-content-relations'] = __('Person hinzufügen', 'iss-content-model');
    }

    if (function_exists('iss_relations_is_supported_post_type') && iss_relations_is_supported_post_type($post_type)) {
        $targets['iss-relations-places'] = __('Ort hinzufügen', 'iss-content-model');
    }

    return (array) apply_filters('iss_content_model_editor_modal_targets', $targets, $post_type);
}

function iss_content_model_use_editor_modal_controls(string $post_type): bool
{
    return iss_content_model_get_editor_modal_targets($post_type) !== [];
}

function iss_content_model_should_hide_editor_modal_metaboxes(): bool
{
    if (current_user_can('manage_options')) {
        return false;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if ($screen && $screen->base === 'post' && isset($screen->post_type) && use_block_editor_for_post_type((string) $screen->post_type)) {
        return false;
    }

    return true;
}

function iss_content_model_get_editor_dashboard_post_types(): array
{
    $post_types = function_exists('iss_graph_get_related_promotion_post_types')
        ? iss_graph_get_related_promotion_post_types()
        : [
            ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
            'publication',
            'fuehrung',
            ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
            ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
            ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
            'page',
            'post',
        ];

    return array_values(array_unique(array_filter(array_map('sanitize_key', (array) $post_types), 'post_type_exists')));
}

function iss_content_model_use_editor_dashboard(string $post_type): bool
{
    return !current_user_can('manage_options')
        && in_array(sanitize_key($post_type), iss_content_model_get_editor_dashboard_post_types(), true);
}

function iss_content_model_get_editor_dashboard_box_ids(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    $box_ids = [
        'postexcerpt',
        'postimagediv',
        'iss-graph-related-promotion',
    ];

    if ($post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        $box_ids[] = iss_content_model_acf_handles_ausstellung_meta()
            ? 'acf-group_iss_ausstellung_controls'
            : 'iss-content-model-ausstellung';
    } elseif ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $box_ids[] = 'iss-content-model-veranstaltung';
        $box_ids[] = 'iss-content-model-veranstaltung-status';
    } elseif ($post_type === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        $box_ids[] = 'iss-content-model-projekt';
    } elseif ($post_type === ISS_CONTENT_MODEL_VIDEO_POST_TYPE) {
        $box_ids[] = 'iss-content-model-video';
    } elseif ($post_type === 'publication') {
        $box_ids[] = 'iss-publication-bibliography';
        $box_ids[] = 'iss-publication-display';
        $box_ids[] = 'iss-publication-sale';
    } elseif ($post_type === 'fuehrung') {
        $box_ids[] = 'iss-fuehrung-data';
    }

    return (array) apply_filters('iss_content_model_editor_dashboard_box_ids', array_values(array_unique($box_ids)), $post_type);
}

function iss_content_model_restore_admin_ausstellung_metabox_visibility(): void
{
    if (iss_content_model_use_simplified_ausstellung_admin()) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return;
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    $managed_boxes = [
        'iss-graph-public-content-relations',
        'iss-relations-places',
        'iss-wf-import-archive-picker',
    ];

    foreach (['metaboxhidden_ausstellung', 'closedpostboxes_ausstellung'] as $option) {
        $current = get_user_option($option, $user_id);
        if (!is_array($current)) {
            continue;
        }

        $next = array_values(array_diff($current, $managed_boxes));
        if ($next !== $current) {
            update_user_option($user_id, $option, $next, true);
        }
    }
}

add_action('media_buttons', function (string $editor_id): void {
    if ($editor_id !== 'content') {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || !isset($screen->post_type) || !iss_content_model_use_editor_modal_controls((string) $screen->post_type)) {
        return;
    }

    $buttons = iss_content_model_get_editor_modal_targets((string) $screen->post_type);

    foreach ($buttons as $target => $label) {
        printf(
            ' <button type="button" class="button iss-editor-open" data-iss-editor-modal-target="%s">%s</button>',
            esc_attr($target),
            esc_html($label)
        );
    }
});

add_action('admin_enqueue_scripts', function ($hook): void {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || !isset($screen->post_type)) {
        return;
    }

    if ($screen->post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        iss_content_model_restore_admin_ausstellung_metabox_visibility();
    }

    $uses_modal_controls = iss_content_model_use_editor_modal_controls((string) $screen->post_type);
    $uses_editor_dashboard = iss_content_model_use_editor_dashboard((string) $screen->post_type);

    if (!$uses_modal_controls && !$uses_editor_dashboard) {
        return;
    }

    $style_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-editor-modal-controls.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'iss-content-model-editor-modal-controls',
            plugins_url('../assets/admin-editor-modal-controls.css', __FILE__),
            [],
            (string) filemtime($style_path)
        );
    }

    $script_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-editor-modal-controls.js';
    if (file_exists($script_path)) {
        wp_enqueue_script(
            'iss-content-model-editor-modal-controls',
            plugins_url('../assets/admin-editor-modal-controls.js', __FILE__),
            [],
            (string) filemtime($script_path),
            true
        );

        wp_localize_script(
            'iss-content-model-editor-modal-controls',
            'issContentEditorModalControls',
            [
                'hideManagedBoxes' => iss_content_model_should_hide_editor_modal_metaboxes(),
                'moveAusstellungTopGroups' => false,
                'moveEditorTopGroups' => $uses_editor_dashboard,
                'editorTopGroupIds' => $uses_editor_dashboard
                    ? iss_content_model_get_editor_dashboard_box_ids((string) $screen->post_type)
                    : [],
            ]
        );
    }
}, 20);

add_action('enqueue_block_editor_assets', function (): void {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || !isset($screen->post_type) || $screen->post_type !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return;
    }

    $script_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-ausstellung-timeline-panel.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_script(
        'iss-content-model-ausstellung-timeline-panel',
        plugins_url('../assets/admin-ausstellung-timeline-panel.js', __FILE__),
        ['wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data'],
        (string) filemtime($script_path),
        true
    );
});

add_action('add_meta_boxes', function (string $post_type): void {
    if (!iss_content_model_use_editor_dashboard($post_type)) {
        return;
    }

    remove_meta_box('slugdiv', $post_type, 'normal');
    remove_meta_box('revisionsdiv', $post_type, 'normal');
    remove_meta_box('postcustom', $post_type, 'normal');
}, 99);

add_action('add_meta_boxes_' . ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE, function (): void {
    if (!iss_content_model_use_simplified_ausstellung_admin()) {
        return;
    }

    remove_meta_box('categorydiv', ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE, 'side');
    remove_meta_box('tagsdiv-post_tag', ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE, 'side');
    remove_meta_box('pageparentdiv', ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE, 'side');
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return;
    }

    $style_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-veranstaltung.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'iss-content-model-veranstaltung-admin',
            plugins_url('../assets/admin-veranstaltung.css', __FILE__),
            [],
            (string) filemtime($style_path)
        );
    }
});

add_action('add_meta_boxes_' . ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, function (): void {
    if (current_user_can('manage_options')) {
        return;
    }

    remove_meta_box('postcustom', ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, 'normal');
    remove_meta_box('slugdiv', ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, 'normal');
    remove_meta_box('pageparentdiv', ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, 'side');
});

function iss_content_model_get_veranstaltung_place_choices(): array
{
    if (function_exists('iss_relations_get_place_choices')) {
        return iss_relations_get_place_choices();
    }

    if (!post_type_exists('register_place')) {
        return [];
    }

    $choices = [];
    $posts = get_posts([
        'post_type' => 'register_place',
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'title',
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

function iss_content_model_get_publication_choices(): array
{
    if (!post_type_exists('publication')) {
        return [];
    }

    $choices = [];
    $posts = get_posts([
        'post_type' => 'publication',
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

function iss_content_model_get_veranstaltung_primary_place_id(int $post_id): int
{
    if (!function_exists('iss_relations_get_post_relations')) {
        return 0;
    }

    $relations = iss_relations_get_post_relations($post_id);
    if (!$relations) {
        return 0;
    }

    foreach (['venue', 'primary', 'related', 'stop', 'subject'] as $preferred_role) {
        foreach ($relations as $relation) {
            if ((string) ($relation['role'] ?? '') !== $preferred_role) {
                continue;
            }

            return (int) ($relation['place_id'] ?? 0);
        }
    }

    return (int) ($relations[0]['place_id'] ?? 0);
}

function iss_content_model_get_veranstaltung_place_title(int $place_id): string
{
    if ($place_id <= 0) {
        return '';
    }

    return trim((string) get_the_title($place_id));
}

function iss_content_model_get_veranstaltung_layout_options(): array
{
    return [
        'standard' => [
            'label' => __('Terminblatt', 'iss-content-model'),
            'description' => __('Klassischer Termin mit Datum, Ort, Teaser und Inhalt.', 'iss-content-model'),
        ],
        'compact' => [
            'label' => __('Kurzmeldung', 'iss-content-model'),
            'description' => __('Kurze Ankündigung mit reduzierter Einzelansicht.', 'iss-content-model'),
        ],
        'fest' => [
            'label' => __('Fest / Programm', 'iss-content-model'),
            'description' => __('Programmorientierte Seite. Beitragsbild ist hier besonders wichtig.', 'iss-content-model'),
        ],
        'long' => [
            'label' => __('Bericht / Rückblick', 'iss-content-model'),
            'description' => __('Längere Dokumentation oder Nachbericht.', 'iss-content-model'),
        ],
    ];
}

function iss_content_model_get_veranstaltung_format_options(): array
{
    return [
        'general' => [
            'label' => __('Allgemein', 'iss-content-model'),
            'description' => __('Keine besondere Terminblatt-Struktur.', 'iss-content-model'),
        ],
        'vortrag' => [
            'label' => __('Vortrag', 'iss-content-model'),
            'description' => __('Thema, Referent:in und Kontext.', 'iss-content-model'),
        ],
        'gespraech' => [
            'label' => __('Gespräch', 'iss-content-model'),
            'description' => __('Teilnehmende, Leitfragen und Gesprächsanlass.', 'iss-content-model'),
        ],
        'lesung' => [
            'label' => __('Lesung', 'iss-content-model'),
            'description' => __('Publikation, Autor:in oder Textgrundlage.', 'iss-content-model'),
        ],
        'praesentation' => [
            'label' => __('Präsentation', 'iss-content-model'),
            'description' => __('Projekt, Material oder Ergebnisvorstellung.', 'iss-content-model'),
        ],
    ];
}

function iss_content_model_get_veranstaltung_scheme_options(): array
{
    return [
        'blue' => __('Blau', 'iss-content-model'),
        'red' => __('Rot', 'iss-content-model'),
        'green' => __('Grün', 'iss-content-model'),
        'yellow' => __('Gelb', 'iss-content-model'),
        'brown' => __('Braun', 'iss-content-model'),
    ];
}

function iss_content_model_normalize_veranstaltung_layout(string $value): string
{
    if (function_exists('industriesalon_sanitize_event_layout')) {
        return industriesalon_sanitize_event_layout($value);
    }

    $value = sanitize_key($value);
    if ($value === 'feature') {
        $value = 'fest';
    }

    return array_key_exists($value, iss_content_model_get_veranstaltung_layout_options()) ? $value : 'standard';
}

function iss_content_model_normalize_veranstaltung_format(string $value): string
{
    if (function_exists('industriesalon_sanitize_event_format')) {
        return industriesalon_sanitize_event_format($value);
    }

    $value = sanitize_key(remove_accents($value));
    if ($value === 'gesprach') {
        $value = 'gespraech';
    } elseif ($value === 'prasentation' || $value === 'presentation') {
        $value = 'praesentation';
    }

    return array_key_exists($value, iss_content_model_get_veranstaltung_format_options()) ? $value : 'general';
}

function iss_content_model_normalize_veranstaltung_scheme(string $value): string
{
    if (function_exists('industriesalon_sanitize_event_scheme')) {
        return industriesalon_sanitize_event_scheme($value);
    }

    $value = sanitize_key($value);

    return array_key_exists($value, iss_content_model_get_veranstaltung_scheme_options()) ? $value : 'blue';
}

function iss_content_model_render_veranstaltung_option_cards(string $name, array $options, string $selected): void
{
    echo '<div class="iss-veranstaltung-admin__options">';
    foreach ($options as $value => $config) {
        $label = is_array($config) ? (string) ($config['label'] ?? $value) : (string) $config;
        $description = is_array($config) ? (string) ($config['description'] ?? '') : '';
        $id = sanitize_html_class($name . '-' . $value);

        echo '<label class="iss-veranstaltung-admin__option" for="' . esc_attr($id) . '">';
        echo '<input type="radio" id="' . esc_attr($id) . '" name="iss_content_model[' . esc_attr($name) . ']" value="' . esc_attr($value) . '" ' . checked($selected, (string) $value, false) . '>';
        echo '<span class="iss-veranstaltung-admin__option-body">';
        echo '<strong>' . esc_html($label) . '</strong>';
        if ($description !== '') {
            echo '<span>' . esc_html($description) . '</span>';
        }
        echo '</span>';
        echo '</label>';
    }
    echo '</div>';
}

function iss_content_model_render_veranstaltung_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $start = (string) get_post_meta($post->ID, 'iss_start_datetime', true);
    $end = (string) get_post_meta($post->ID, 'iss_end_datetime', true);
    $location = (string) get_post_meta($post->ID, 'iss_location', true);
    $place_choices = iss_content_model_get_veranstaltung_place_choices();
    $selected_place_id = iss_content_model_get_veranstaltung_primary_place_id((int) $post->ID);
    $selected_place_title = iss_content_model_get_veranstaltung_place_title($selected_place_id);
    $location_override = $selected_place_title !== '' && $location === $selected_place_title ? '' : $location;
    $timeline_enabled = get_post_meta($post->ID, 'iss_timeline_enabled', true);
    $timeline_enabled = $timeline_enabled === '' ? false : (bool) $timeline_enabled;
    $layout = iss_content_model_normalize_veranstaltung_layout((string) get_post_meta($post->ID, '_iss_event_layout', true));
    $format = iss_content_model_normalize_veranstaltung_format((string) get_post_meta($post->ID, '_iss_event_format', true));
    $scheme = iss_content_model_normalize_veranstaltung_scheme((string) get_post_meta($post->ID, '_iss_event_scheme', true));

    echo '<div class="iss-veranstaltung-admin">';

    echo '<section class="iss-veranstaltung-admin__section">';
    echo '<div class="iss-veranstaltung-admin__section-head">';
    echo '<h3>' . esc_html__('Basis', 'iss-content-model') . '</h3>';
    echo '<p>' . esc_html__('Pflichtnahe Angaben für Kalender, Karten und die Einzelansicht.', 'iss-content-model') . '</p>';
    echo '</div>';
    echo '<div class="iss-veranstaltung-admin__grid">';

    echo '<p class="iss-veranstaltung-admin__field"><label for="iss_start_datetime"><strong>' . esc_html__('Beginn', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="datetime-local" id="iss_start_datetime" name="iss_content_model[iss_start_datetime]" value="' . esc_attr(iss_content_model_mysql_to_local_input($start)) . '"></p>';

    echo '<p class="iss-veranstaltung-admin__field"><label for="iss_end_datetime"><strong>' . esc_html__('Ende', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="datetime-local" id="iss_end_datetime" name="iss_content_model[iss_end_datetime]" value="' . esc_attr(iss_content_model_mysql_to_local_input($end)) . '"></p>';

    echo '<p class="iss-veranstaltung-admin__field"><label for="iss_primary_place_id"><strong>' . esc_html__('Atlas-Ort', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_primary_place_id" name="iss_content_model[iss_primary_place_id]">';
    echo '<option value="">' . esc_html__('Keinen Atlas-Ort auswählen', 'iss-content-model') . '</option>';
    foreach ($place_choices as $place) {
        echo '<option value="' . esc_attr((string) $place['id']) . '" ' . selected($selected_place_id, (int) $place['id'], false) . '>' . esc_html((string) $place['title']) . '</option>';
    }
    echo '</select>';
    echo '<span class="description">' . esc_html__('Pflegt automatisch die primäre Ortsbeziehung.', 'iss-content-model') . '</span></p>';

    echo '<p class="iss-veranstaltung-admin__field"><label for="iss_location"><strong>' . esc_html__('Treffpunkt / Ortstext', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_location" name="iss_content_model[iss_location]" value="' . esc_attr($location_override) . '" placeholder="' . esc_attr__('Leer lassen, dann wird der Atlas-Ort übernommen.', 'iss-content-model') . '">';
    echo '<span class="description">' . esc_html__('Nur ausfüllen, wenn der öffentliche Ortstext vom Atlas-Ort abweicht.', 'iss-content-model') . '</span></p>';

    echo '</div>';
    echo '</section>';

    echo '<section class="iss-veranstaltung-admin__section">';
    echo '<div class="iss-veranstaltung-admin__section-head">';
    echo '<h3>' . esc_html__('Darstellung', 'iss-content-model') . '</h3>';
    echo '<p>' . esc_html__('Steuert die Theme-Ausgabe. Bestehende Blockinhalte bleiben unverändert.', 'iss-content-model') . '</p>';
    echo '</div>';

    echo '<div class="iss-veranstaltung-admin__field">';
    echo '<span class="iss-veranstaltung-admin__label">' . esc_html__('Seitentyp', 'iss-content-model') . '</span>';
    iss_content_model_render_veranstaltung_option_cards('_iss_event_layout', iss_content_model_get_veranstaltung_layout_options(), $layout);
    echo '</div>';

    echo '<div class="iss-veranstaltung-admin__field">';
    echo '<span class="iss-veranstaltung-admin__label">' . esc_html__('Format', 'iss-content-model') . '</span>';
    iss_content_model_render_veranstaltung_option_cards('_iss_event_format', iss_content_model_get_veranstaltung_format_options(), $format);
    echo '</div>';

    echo '<p class="iss-veranstaltung-admin__field iss-veranstaltung-admin__field--narrow"><label for="iss_event_scheme"><strong>' . esc_html__('Farbschema', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_event_scheme" name="iss_content_model[_iss_event_scheme]">';
    foreach (iss_content_model_get_veranstaltung_scheme_options() as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($scheme, (string) $value, false) . '>' . esc_html((string) $label) . '</option>';
    }
    echo '</select></p>';
    echo '</section>';

    echo '<section class="iss-veranstaltung-admin__section iss-veranstaltung-admin__section--editorial">';
    echo '<div class="iss-veranstaltung-admin__section-head">';
    echo '<h3>' . esc_html__('Redaktion', 'iss-content-model') . '</h3>';
    echo '<p>' . esc_html__('Kurzbeschreibung im Auszug pflegen, Beitragsbild setzen und Beziehungen unten ergänzen.', 'iss-content-model') . '</p>';
    echo '</div>';
    echo '<p><label><input type="checkbox" name="iss_content_model[iss_timeline_enabled]" value="1" ' . checked($timeline_enabled, true, false) . '> ' . esc_html__('Öffentlich in Ausstellungsübersichten zeigen', 'iss-content-model') . '</label></p>';
    echo '<p class="description">' . esc_html__('Weitere Ortsbezüge bleiben in „Verknüpfte Orte“. Personenbezüge bleiben in „Personen“. Diese V1 bündelt nur die Kernfelder.', 'iss-content-model') . '</p>';
    echo '</section>';

    echo '</div>';
}

function iss_content_model_render_veranstaltung_status_box($post): void
{
    $post_id = (int) $post->ID;
    $selected_place_id = iss_content_model_get_veranstaltung_primary_place_id($post_id);
    $location = trim((string) get_post_meta($post_id, 'iss_location', true));
    $layout = iss_content_model_normalize_veranstaltung_layout((string) get_post_meta($post_id, '_iss_event_layout', true));
    $format = iss_content_model_normalize_veranstaltung_format((string) get_post_meta($post_id, '_iss_event_format', true));
    $layout_options = iss_content_model_get_veranstaltung_layout_options();
    $format_options = iss_content_model_get_veranstaltung_format_options();
    $checks = [
        __('Titel', 'iss-content-model') => trim((string) get_the_title($post_id)) !== '',
        __('Beginn', 'iss-content-model') => trim((string) get_post_meta($post_id, 'iss_start_datetime', true)) !== '',
        __('Ort', 'iss-content-model') => $selected_place_id > 0 || $location !== '',
        __('Kurzbeschreibung', 'iss-content-model') => has_excerpt($post_id),
        __('Beitragsbild', 'iss-content-model') => has_post_thumbnail($post_id),
    ];

    echo '<div class="iss-veranstaltung-status">';
    echo '<dl class="iss-veranstaltung-status__facts">';
    echo '<div><dt>' . esc_html__('Seitentyp', 'iss-content-model') . '</dt><dd>' . esc_html((string) ($layout_options[$layout]['label'] ?? $layout)) . '</dd></div>';
    echo '<div><dt>' . esc_html__('Format', 'iss-content-model') . '</dt><dd>' . esc_html((string) ($format_options[$format]['label'] ?? $format)) . '</dd></div>';
    echo '</dl>';

    echo '<ul class="iss-veranstaltung-status__checks">';
    foreach ($checks as $label => $complete) {
        echo '<li class="' . esc_attr($complete ? 'is-complete' : 'is-missing') . '">';
        echo '<span aria-hidden="true">' . esc_html($complete ? 'OK' : '!') . '</span>';
        echo esc_html((string) $label);
        echo '</li>';
    }
    echo '</ul>';

    echo '<p class="description">' . esc_html__('Das Beitragsbild wird als Kartenbild genutzt. Wenn es fehlt, greift die globale Platzhaltergrafik.', 'iss-content-model') . '</p>';
    echo '</div>';
}

function iss_content_model_render_ausstellung_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $start = (string) get_post_meta($post->ID, 'iss_start_date', true);
    $end = (string) get_post_meta($post->ID, 'iss_end_date', true);
    $is_permanent = (bool) get_post_meta($post->ID, 'iss_is_permanent', true);
    $timeline_enabled = get_post_meta($post->ID, 'iss_timeline_enabled', true);
    $timeline_enabled = $timeline_enabled === '' ? false : (bool) $timeline_enabled;

    echo '<h3>' . esc_html__('Basis', 'iss-content-model') . '</h3>';
    echo '<p><label for="iss_start_date"><strong>' . esc_html__('Startdatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_start_date" name="iss_content_model[iss_start_date]" value="' . esc_attr($start) . '"></p>';

    echo '<p><label for="iss_end_date"><strong>' . esc_html__('Enddatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_end_date" name="iss_content_model[iss_end_date]" value="' . esc_attr($end) . '"></p>';

    echo '<p class="description">' . esc_html__('Sammlungsbereich und Themen werden in den Taxonomie-Boxen verwaltet. Orte werden ueber "Ort hinzufuegen" verbunden.', 'iss-content-model') . '</p>';
    echo '<p><label><input type="checkbox" name="iss_content_model[iss_is_permanent]" value="1" ' . checked($is_permanent, true, false) . '> ' . esc_html__('Dauerausstellung', 'iss-content-model') . '</label></p>';
    echo '<p><label><input type="checkbox" name="iss_content_model[iss_timeline_enabled]" value="1" ' . checked($timeline_enabled, true, false) . '> ' . esc_html__('In Kalender und Timeline zeigen', 'iss-content-model') . '</label></p>';
}

function iss_content_model_render_projekt_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $start = (string) get_post_meta($post->ID, 'iss_start_date', true);
    $end = (string) get_post_meta($post->ID, 'iss_end_date', true);
    $period_label = (string) get_post_meta($post->ID, 'iss_period_label', true);
    $timeline_enabled = get_post_meta($post->ID, 'iss_timeline_enabled', true);
    $timeline_enabled = $timeline_enabled === '' ? false : (bool) $timeline_enabled;
    $front_page_order = (int) $post->menu_order;

    echo '<p><label for="iss_start_date"><strong>' . esc_html__('Startdatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_start_date" name="iss_content_model[iss_start_date]" value="' . esc_attr($start) . '"></p>';

    echo '<p><label for="iss_end_date"><strong>' . esc_html__('Enddatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_end_date" name="iss_content_model[iss_end_date]" value="' . esc_attr($end) . '"></p>';

    echo '<p><label for="iss_period_label"><strong>' . esc_html__('Zeitraum-Label', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_period_label" name="iss_content_model[iss_period_label]" value="' . esc_attr($period_label) . '" placeholder="' . esc_attr__('seit 2023 / laufend / 2024', 'iss-content-model') . '"></p>';
    echo '<p class="description">' . esc_html__('Das Label ist nur Anzeigetext. Fuer Kalender und Timeline zaehlen Start-/Enddatum oder ersatzweise das Erstellungsdatum.', 'iss-content-model') . '</p>';

    echo '<p><label><input type="checkbox" name="iss_content_model[iss_timeline_enabled]" value="1" ' . checked($timeline_enabled, true, false) . '> ' . esc_html__('In Kalender und Timeline zeigen', 'iss-content-model') . '</label></p>';

    echo '<p><label for="iss_project_front_page_order"><strong>' . esc_html__('Startseiten-Reihenfolge', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="number" step="1" id="iss_project_front_page_order" name="iss_content_model[menu_order]" value="' . esc_attr((string) $front_page_order) . '"></p>';
    echo '<p class="description">' . esc_html__('Steuert die Reihenfolge im Projekte-Query-Loop der Startseite. Niedrige Werte erscheinen zuerst; Abstände wie 10, 20, 30 lassen Platz für spätere Einfügungen.', 'iss-content-model') . '</p>';
}

function iss_content_model_render_team_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $role_label = (string) get_post_meta($post->ID, 'iss_role_label', true);
    $email = (string) get_post_meta($post->ID, 'iss_email', true);
    $phone = (string) get_post_meta($post->ID, 'iss_phone', true);

    echo '<p><label for="iss_role_label"><strong>' . esc_html__('Rollenzeile', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_role_label" name="iss_content_model[iss_role_label]" value="' . esc_attr($role_label) . '"></p>';

    echo '<p><label for="iss_email"><strong>' . esc_html__('E-Mail', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="email" id="iss_email" name="iss_content_model[iss_email]" value="' . esc_attr($email) . '"></p>';

    echo '<p><label for="iss_phone"><strong>' . esc_html__('Telefon', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_phone" name="iss_content_model[iss_phone]" value="' . esc_attr($phone) . '"></p>';
}

function iss_content_model_render_video_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $video_url = (string) get_post_meta($post->ID, 'iss_video_url', true);
    $source_family = (string) get_post_meta($post->ID, 'iss_video_source_family', true);
    $source_label = (string) get_post_meta($post->ID, 'iss_video_source_label', true);
    $source_url = (string) get_post_meta($post->ID, 'iss_video_source_url', true);
    $year = (string) get_post_meta($post->ID, 'iss_video_year', true);
    $original_date = (string) get_post_meta($post->ID, 'iss_video_original_date', true);
    $duration = (string) get_post_meta($post->ID, 'iss_video_duration', true);
    $language = (string) get_post_meta($post->ID, 'iss_video_language', true);
    $rights = (string) get_post_meta($post->ID, 'iss_video_rights', true);
    $transcript_status = (string) get_post_meta($post->ID, 'iss_video_transcript_status', true);
    $transcript_source = (string) get_post_meta($post->ID, 'iss_video_transcript_source', true);
    $featured = (bool) get_post_meta($post->ID, 'iss_video_featured', true);
    $source_family = $source_family !== '' ? $source_family : 'core';
    $transcript_status = $transcript_status !== '' ? iss_content_model_normalize_video_transcript_status($transcript_status) : 'none';
    $source_options = function_exists('iss_content_model_get_video_source_family_options')
        ? iss_content_model_get_video_source_family_options()
        : [
            'core' => __('Eigener Bestand', 'iss-content-model'),
            'external_report' => __('Externer Bericht', 'iss-content-model'),
            'place_context' => __('Ort / Kontext', 'iss-content-model'),
        ];
    $transcript_options = iss_content_model_get_video_transcript_status_options();

    echo '<p><label for="iss_video_url"><strong>' . esc_html__('Video-URL', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="url" id="iss_video_url" name="iss_content_model[iss_video_url]" value="' . esc_attr($video_url) . '" placeholder="https://www.youtube.com/watch?v=..."></p>';

    echo '<p><label for="iss_video_source_family"><strong>' . esc_html__('Quellentyp', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_video_source_family" name="iss_content_model[iss_video_source_family]">';
    foreach ($source_options as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($source_family, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="iss_video_source_label"><strong>' . esc_html__('Herausgeber / Herkunft', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_video_source_label" name="iss_content_model[iss_video_source_label]" value="' . esc_attr($source_label) . '" placeholder="' . esc_attr__('Industriesalon Schöneweide / tv.berlin / rbb / DDR Museum', 'iss-content-model') . '"></p>';

    echo '<p><label for="iss_video_source_url"><strong>' . esc_html__('Originalseite', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="url" id="iss_video_source_url" name="iss_content_model[iss_video_source_url]" value="' . esc_attr($source_url) . '"></p>';

    echo '<p><label for="iss_video_year"><strong>' . esc_html__('Jahr / Zeitraum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_video_year" name="iss_content_model[iss_video_year]" value="' . esc_attr($year) . '" placeholder="' . esc_attr__('1987 / ca. 1990 / 1965–2005', 'iss-content-model') . '"></p>';

    echo '<p><label for="iss_video_original_date"><strong>' . esc_html__('Originaldatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_video_original_date" name="iss_content_model[iss_video_original_date]" value="' . esc_attr($original_date) . '"></p>';

    echo '<p><label for="iss_video_duration"><strong>' . esc_html__('Dauer', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_video_duration" name="iss_content_model[iss_video_duration]" value="' . esc_attr($duration) . '" placeholder="' . esc_attr__('28:14 / 1:02:33', 'iss-content-model') . '"></p>';

    echo '<p><label for="iss_video_language"><strong>' . esc_html__('Sprache', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_video_language" name="iss_content_model[iss_video_language]" value="' . esc_attr($language) . '" placeholder="' . esc_attr__('Deutsch / Englisch / mehrsprachig', 'iss-content-model') . '"></p>';

    echo '<p><label for="iss_video_rights"><strong>' . esc_html__('Rechte / Lizenz', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_video_rights" name="iss_content_model[iss_video_rights]" value="' . esc_attr($rights) . '" placeholder="' . esc_attr__('Industriesalon Schöneweide / Rechte vorbehalten / Lizenzhinweis', 'iss-content-model') . '"></p>';

    echo '<p><label for="iss_video_transcript_status"><strong>' . esc_html__('Transkriptstatus', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_video_transcript_status" name="iss_content_model[iss_video_transcript_status]">';
    foreach ($transcript_options as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($transcript_status, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';

    echo '<p><label for="iss_video_transcript_source"><strong>' . esc_html__('Transkript-Herkunft', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_video_transcript_source" name="iss_content_model[iss_video_transcript_source]" value="' . esc_attr($transcript_source) . '" placeholder="' . esc_attr__('manuell transkribiert / automatische Erstfassung / Redaktion', 'iss-content-model') . '"></p>';

    echo '<p><label><input type="checkbox" name="iss_content_model[iss_video_featured]" value="1" ' . checked($featured, true, false) . '> ' . esc_html__('Als Leitvideo hervorheben', 'iss-content-model') . '</label></p>';
    echo '<p class="description">' . esc_html__('Kategorien steuern die thematischen Einstiege. Quellentyp und Herausgeber trennen eigenen Bestand von Presse, Berichten und Ortskontext.', 'iss-content-model') . '</p>';
    echo '<p class="description">' . esc_html__('Jahr / Zeitraum bleibt das öffentliche Kurzlabel. Originaldatum dient für exakte Datierung, wenn sie bekannt ist.', 'iss-content-model') . '</p>';
    echo '<p class="description">' . esc_html__('Transkriptstatus steuert Hinweise und Linktexte im Player. Der eigentliche Text bleibt im normalen Inhaltsbereich des Video-Beitrags.', 'iss-content-model') . '</p>';
}

function iss_content_model_mysql_to_local_input($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value, wp_timezone());
        return $dt->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

function iss_content_model_local_input_to_mysql($value) {
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    try {
        $dt = new DateTimeImmutable($value, wp_timezone());
        return $dt->format('Y-m-d H:i:s');
    } catch (Throwable $e) {
        return '';
    }
}

function iss_content_model_sync_veranstaltung_primary_place(int $post_id, int $place_id): void
{
    if ($place_id <= 0) {
        return;
    }

    if (!function_exists('iss_relations_get_post_relations') || !function_exists('iss_relations_update_post_relations') || !function_exists('iss_relations_sync_post_terms')) {
        return;
    }

    $existing_relations = iss_relations_get_post_relations($post_id);
    $selected_relation = [
        'place_id' => $place_id,
        'role' => 'venue',
        'weight' => 100,
        'label' => '',
    ];
    $remaining_relations = [];

    foreach ($existing_relations as $relation) {
        $relation_place_id = (int) ($relation['place_id'] ?? 0);
        if ($relation_place_id === $place_id) {
            $selected_relation['weight'] = max(100, (int) ($relation['weight'] ?? 0));
            $selected_relation['label'] = (string) ($relation['label'] ?? '');
            continue;
        }

        $remaining_relations[] = $relation;
    }

    array_unshift($remaining_relations, $selected_relation);
    iss_relations_update_post_relations($post_id, $remaining_relations);
    iss_relations_sync_post_terms($post_id);
}

function iss_content_model_save_veranstaltung_presentation_meta(int $post_id, array $raw): void
{
    $fields = [
        '_iss_event_layout' => 'iss_content_model_normalize_veranstaltung_layout',
        '_iss_event_format' => 'iss_content_model_normalize_veranstaltung_format',
        '_iss_event_scheme' => 'iss_content_model_normalize_veranstaltung_scheme',
    ];

    foreach ($fields as $key => $normalizer) {
        if (!array_key_exists($key, $raw)) {
            continue;
        }

        update_post_meta($post_id, $key, $normalizer((string) $raw[$key]));
    }
}

function iss_content_model_save_meta_box(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (!isset($_POST['iss_content_model_meta_nonce']) || !wp_verify_nonce((string) $_POST['iss_content_model_meta_nonce'], 'iss_content_model_save_meta')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $post_type = get_post_type($post_id);
    $definitions = iss_content_model_meta_definitions();
    if (!isset($definitions[$post_type])) {
        return;
    }

    $raw = isset($_POST['iss_content_model']) && is_array($_POST['iss_content_model']) ? wp_unslash($_POST['iss_content_model']) : [];
    $selected_place_id = 0;

    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $selected_place_id = absint($raw['iss_primary_place_id'] ?? 0);
        iss_content_model_save_veranstaltung_presentation_meta($post_id, $raw);

        unset($raw['iss_primary_place_id']);
        unset($raw['_iss_event_layout'], $raw['_iss_event_format'], $raw['_iss_event_scheme']);

        $manual_location = trim((string) ($raw['iss_location'] ?? ''));
        if ($selected_place_id > 0 && $manual_location === '') {
            $raw['iss_location'] = iss_content_model_get_veranstaltung_place_title($selected_place_id);
        }
    }

    foreach ($definitions[$post_type] as $key => $config) {
        $value = $raw[$key] ?? ($config['type'] === 'boolean' ? '' : $config['default']);

        if (in_array($key, ['iss_start_datetime', 'iss_end_datetime'], true)) {
            $value = iss_content_model_local_input_to_mysql($value);
        }

        $sanitized = iss_content_model_sanitize_meta_value($value, $key, null);

        if ($config['type'] === 'boolean') {
            update_post_meta($post_id, $key, $sanitized ? '1' : '0');
            continue;
        }

        if ($config['type'] === 'integer') {
            $sanitized = (int) $sanitized;
            if ($sanitized > 0) {
                update_post_meta($post_id, $key, $sanitized);
            } else {
                delete_post_meta($post_id, $key);
            }
            continue;
        }

        $sanitized = trim((string) $sanitized);
        if ($sanitized === '') {
            delete_post_meta($post_id, $key);
        } else {
            update_post_meta($post_id, $key, $sanitized);
        }
    }

    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        iss_content_model_sync_veranstaltung_primary_place($post_id, $selected_place_id);
    }

    if ($post_type === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE && array_key_exists('menu_order', $raw)) {
        $front_page_order = (int) $raw['menu_order'];

        remove_action('save_post', 'iss_content_model_save_meta_box', 20);
        wp_update_post([
            'ID' => $post_id,
            'menu_order' => $front_page_order,
        ]);
        add_action('save_post', 'iss_content_model_save_meta_box', 20, 1);
    }
}
add_action('save_post', 'iss_content_model_save_meta_box', 20, 1);

function iss_content_model_add_project_order_column(array $columns): array
{
    $ordered_columns = [];

    foreach ($columns as $key => $label) {
        $ordered_columns[$key] = $label;

        if ($key === 'title') {
            $ordered_columns['iss_project_front_page_order'] = __('Reihenfolge', 'iss-content-model');
        }
    }

    return $ordered_columns;
}
add_filter('manage_' . ISS_CONTENT_MODEL_PROJEKT_POST_TYPE . '_posts_columns', 'iss_content_model_add_project_order_column');

function iss_content_model_render_project_order_column(string $column, int $post_id): void
{
    if ($column !== 'iss_project_front_page_order') {
        return;
    }

    echo esc_html((string) get_post_field('menu_order', $post_id));
}
add_action('manage_' . ISS_CONTENT_MODEL_PROJEKT_POST_TYPE . '_posts_custom_column', 'iss_content_model_render_project_order_column', 10, 2);

function iss_content_model_make_project_order_column_sortable(array $columns): array
{
    $columns['iss_project_front_page_order'] = 'menu_order';
    return $columns;
}
add_filter('manage_edit-' . ISS_CONTENT_MODEL_PROJEKT_POST_TYPE . '_sortable_columns', 'iss_content_model_make_project_order_column_sortable');
