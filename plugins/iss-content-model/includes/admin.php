<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-content-model-veranstaltung',
        __('Veranstaltungsdaten', 'iss-content-model'),
        'iss_content_model_render_veranstaltung_box',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'side',
        'high'
    );

    add_meta_box(
        'iss-content-model-ausstellung',
        __('Ausstellungsdaten', 'iss-content-model'),
        'iss_content_model_render_ausstellung_box',
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        'side',
        'high'
    );

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

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        return;
    }

    wp_enqueue_script(
        'iss-content-model-ausstellung-corpus',
        plugins_url('../assets/admin-ausstellung-corpus.js', __FILE__),
        [],
        ISS_CONTENT_MODEL_VERSION,
        true
    );
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

function iss_content_model_get_archivbeitrag_choices(): array
{
    if (!post_type_exists('archivbeitrag')) {
        return [];
    }

    $choices = [];
    $posts = get_posts([
        'post_type' => 'archivbeitrag',
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
        'suppress_filters' => true,
    ]);

    foreach ($posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }

        $choices[] = [
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'status' => (string) $post->post_status,
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

function iss_content_model_get_archive_term_choices(string $taxonomy): array
{
    if (!taxonomy_exists($taxonomy)) {
        return [];
    }

    $choices = [];
    $terms = get_terms([
        'taxonomy' => $taxonomy,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ]);

    if (is_wp_error($terms)) {
        return [];
    }

    foreach ($terms as $term) {
        if (!$term instanceof WP_Term) {
            continue;
        }

        $choices[] = [
            'slug' => (string) $term->slug,
            'name' => (string) $term->name,
            'count' => (int) $term->count,
        ];
    }

    return $choices;
}

function iss_content_model_parse_id_list($value): array
{
    if (is_array($value)) {
        $items = $value;
    } else {
        $items = preg_split('/[\s,]+/', (string) $value);
    }

    $ids = [];
    foreach ((array) $items as $item) {
        $id = absint($item);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function iss_content_model_get_ausstellung_corpus_chapter_ids(int $post_id): array
{
    return iss_content_model_parse_id_list((string) get_post_meta($post_id, 'iss_corpus_chapter_ids', true));
}

function iss_content_model_get_ausstellung_surface_mode(int $post_id): string
{
    return iss_content_model_normalize_ausstellung_surface_mode((string) get_post_meta($post_id, 'iss_surface_mode', true));
}

function iss_content_model_get_ausstellung_archive_term_slug(int $post_id): string
{
    return sanitize_title((string) get_post_meta($post_id, 'iss_archive_term_slug', true));
}

function iss_content_model_get_ausstellung_archive_browser_config(int $post_id): array
{
    return [
        'default_source' => sanitize_title((string) get_post_meta($post_id, 'iss_archive_browser_default_source', true)),
        'lock_source' => (bool) get_post_meta($post_id, 'iss_archive_browser_lock_source', true),
        'default_field' => sanitize_title((string) get_post_meta($post_id, 'iss_archive_browser_default_field', true)),
        'lock_field' => (bool) get_post_meta($post_id, 'iss_archive_browser_lock_field', true),
        'quick_kicker' => trim((string) get_post_meta($post_id, 'iss_archive_browser_quick_kicker', true)),
        'quick_title' => trim((string) get_post_meta($post_id, 'iss_archive_browser_quick_title', true)),
        'quick_family_slugs' => iss_content_model_parse_slug_list((string) get_post_meta($post_id, 'iss_archive_browser_quick_family_slugs', true)),
        'show_source_cards' => (bool) get_post_meta($post_id, 'iss_archive_browser_show_source_cards', true),
    ];
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
    $timeline_enabled = $timeline_enabled === '' ? true : (bool) $timeline_enabled;

    echo '<p><label for="iss_start_datetime"><strong>' . esc_html__('Beginn', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="datetime-local" id="iss_start_datetime" name="iss_content_model[iss_start_datetime]" value="' . esc_attr(iss_content_model_mysql_to_local_input($start)) . '"></p>';

    echo '<p><label for="iss_end_datetime"><strong>' . esc_html__('Ende', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="datetime-local" id="iss_end_datetime" name="iss_content_model[iss_end_datetime]" value="' . esc_attr(iss_content_model_mysql_to_local_input($end)) . '"></p>';

    echo '<p><label for="iss_primary_place_id"><strong>' . esc_html__('Atlas-Ort', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_primary_place_id" name="iss_content_model[iss_primary_place_id]">';
    echo '<option value="">' . esc_html__('Keinen Atlas-Ort auswählen', 'iss-content-model') . '</option>';
    foreach ($place_choices as $place) {
        echo '<option value="' . esc_attr((string) $place['id']) . '" ' . selected($selected_place_id, (int) $place['id'], false) . '>' . esc_html((string) $place['title']) . '</option>';
    }
    echo '</select></p>';
    echo '<p class="description">' . esc_html__('Im Normalfall hier genau einen Atlas-Ort wählen. Dadurch wird die gemeinsame Ortsbeziehung automatisch gepflegt.', 'iss-content-model') . '</p>';

    echo '<p><label for="iss_location"><strong>' . esc_html__('Treffpunkt / abweichender Ortstext', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_location" name="iss_content_model[iss_location]" value="' . esc_attr($location_override) . '" placeholder="' . esc_attr__('Leer lassen, dann wird der Name des Atlas-Orts übernommen.', 'iss-content-model') . '"></p>';
    echo '<p class="description">' . esc_html__('Die Metabox „Verknüpfte Orte“ darunter bleibt nur für mehrere Orte oder Sonderfälle nötig.', 'iss-content-model') . '</p>';

    echo '<p><label><input type="checkbox" name="iss_content_model[iss_timeline_enabled]" value="1" ' . checked($timeline_enabled, true, false) . '> ' . esc_html__('In Timeline zeigen', 'iss-content-model') . '</label></p>';
}

function iss_content_model_render_ausstellung_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $start = (string) get_post_meta($post->ID, 'iss_start_date', true);
    $end = (string) get_post_meta($post->ID, 'iss_end_date', true);
    $timeline_enabled = get_post_meta($post->ID, 'iss_timeline_enabled', true);
    $timeline_enabled = $timeline_enabled === '' ? true : (bool) $timeline_enabled;
    $chapter_ids = iss_content_model_get_ausstellung_corpus_chapter_ids((int) $post->ID);
    $surface_mode = iss_content_model_get_ausstellung_surface_mode((int) $post->ID);
    $surface_mode_options = iss_content_model_get_ausstellung_surface_mode_options();
    $archive_term_slug = iss_content_model_get_ausstellung_archive_term_slug((int) $post->ID);
    $archive_browser = iss_content_model_get_ausstellung_archive_browser_config((int) $post->ID);
    $chapter_choices = iss_content_model_get_archivbeitrag_choices();
    $archive_category_choices = defined('ISS_WF_IMPORT_CATEGORY_TAXONOMY')
        ? iss_content_model_get_archive_term_choices(ISS_WF_IMPORT_CATEGORY_TAXONOMY)
        : [];
    $archive_source_choices = defined('ISS_WF_IMPORT_SOURCE_TAXONOMY')
        ? iss_content_model_get_archive_term_choices(ISS_WF_IMPORT_SOURCE_TAXONOMY)
        : [];
    $archive_field_choices = defined('ISS_WF_IMPORT_FIELD_TAXONOMY')
        ? iss_content_model_get_archive_term_choices(ISS_WF_IMPORT_FIELD_TAXONOMY)
        : [];
    $chapter_lookup = [];

    foreach ($chapter_choices as $chapter_choice) {
        $chapter_lookup[(int) $chapter_choice['id']] = $chapter_choice;
    }

    echo '<p><label for="iss_start_date"><strong>' . esc_html__('Startdatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_start_date" name="iss_content_model[iss_start_date]" value="' . esc_attr($start) . '"></p>';

    echo '<p><label for="iss_end_date"><strong>' . esc_html__('Enddatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_end_date" name="iss_content_model[iss_end_date]" value="' . esc_attr($end) . '"></p>';

    echo '<p class="description">' . esc_html__('Typ, Sammlungsbereich und Industrieort werden in den Taxonomie-Boxen verwaltet.', 'iss-content-model') . '</p>';
    echo '<p><label><input type="checkbox" name="iss_content_model[iss_timeline_enabled]" value="1" ' . checked($timeline_enabled, true, false) . '> ' . esc_html__('In Timeline zeigen', 'iss-content-model') . '</label></p>';

    echo '<hr style="margin:1rem 0;">';
    echo '<p><label for="iss_surface_mode"><strong>' . esc_html__('Ausstellungsmodus', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_surface_mode" name="iss_content_model[iss_surface_mode]">';
    foreach ($surface_mode_options as $value => $label) {
        echo '<option value="' . esc_attr($value) . '"' . selected($surface_mode, $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';
    echo '<p class="description">' . esc_html__('Der Modus steuert, ob die Ausstellung normalen Inhalt, eine archivgestützte Kapitelreihe oder einen archivgestützten Objektbrowser über die gemeinsame Einzelansicht rendert.', 'iss-content-model') . '</p>';

    echo '<p><label for="iss_archive_term_slug"><strong>' . esc_html__('Archivkategorie für Kapitelreihe', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_archive_term_slug" name="iss_content_model[iss_archive_term_slug]">';
    echo '<option value="">' . esc_html__('Keine Archivkategorie auswählen', 'iss-content-model') . '</option>';
    foreach ($archive_category_choices as $term) {
        $label = (string) $term['name'];
        if ((int) $term['count'] > 0) {
            $label .= ' (' . (int) $term['count'] . ')';
        }

        echo '<option value="' . esc_attr((string) $term['slug']) . '"' . selected($archive_term_slug, (string) $term['slug'], false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';
    echo '<p class="description">' . esc_html__('Nur für den Modus „Archivreihe / Kapitelpfad“. Die Kapitel werden direkt aus dieser Archivkategorie gelesen; Block-Einbettungen im Inhalt sind nicht nötig.', 'iss-content-model') . '</p>';

    echo '<p><label for="iss_archive_browser_default_source"><strong>' . esc_html__('Archivbrowser: Standardquelle', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_archive_browser_default_source" name="iss_content_model[iss_archive_browser_default_source]">';
    echo '<option value="">' . esc_html__('Keine Quelle vorbelegen', 'iss-content-model') . '</option>';
    foreach ($archive_source_choices as $term) {
        $label = (string) $term['name'];
        if ((int) $term['count'] > 0) {
            $label .= ' (' . (int) $term['count'] . ')';
        }

        echo '<option value="' . esc_attr((string) $term['slug']) . '"' . selected($archive_browser['default_source'], (string) $term['slug'], false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';
    echo '<p><label><input type="checkbox" name="iss_content_model[iss_archive_browser_lock_source]" value="1" ' . checked($archive_browser['lock_source'], true, false) . '> ' . esc_html__('Quelle im Archivbrowser sperren', 'iss-content-model') . '</label></p>';

    echo '<p><label for="iss_archive_browser_default_field"><strong>' . esc_html__('Archivbrowser: Standard-Themenfeld', 'iss-content-model') . '</strong></label>';
    echo '<select class="widefat" id="iss_archive_browser_default_field" name="iss_content_model[iss_archive_browser_default_field]">';
    echo '<option value="">' . esc_html__('Kein Themenfeld vorbelegen', 'iss-content-model') . '</option>';
    foreach ($archive_field_choices as $term) {
        $label = (string) $term['name'];
        if ((int) $term['count'] > 0) {
            $label .= ' (' . (int) $term['count'] . ')';
        }

        echo '<option value="' . esc_attr((string) $term['slug']) . '"' . selected($archive_browser['default_field'], (string) $term['slug'], false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select></p>';
    echo '<p><label><input type="checkbox" name="iss_content_model[iss_archive_browser_lock_field]" value="1" ' . checked($archive_browser['lock_field'], true, false) . '> ' . esc_html__('Themenfeld im Archivbrowser sperren', 'iss-content-model') . '</label></p>';

    echo '<p><label for="iss_archive_browser_quick_kicker"><strong>' . esc_html__('Archivbrowser: Kicker für Einstiege', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_archive_browser_quick_kicker" name="iss_content_model[iss_archive_browser_quick_kicker]" value="' . esc_attr($archive_browser['quick_kicker']) . '" placeholder="' . esc_attr__('Objektfamilien', 'iss-content-model') . '"></p>';

    echo '<p><label for="iss_archive_browser_quick_title"><strong>' . esc_html__('Archivbrowser: Titel für Einstiege', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_archive_browser_quick_title" name="iss_content_model[iss_archive_browser_quick_title]" value="' . esc_attr($archive_browser['quick_title']) . '" placeholder="' . esc_attr__('Einstiege in den Bestand', 'iss-content-model') . '"></p>';

    echo '<p><label for="iss_archive_browser_quick_family_slugs"><strong>' . esc_html__('Archivbrowser: Objektfamilien (Slugs)', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_archive_browser_quick_family_slugs" name="iss_content_model[iss_archive_browser_quick_family_slugs]" value="' . esc_attr(implode(', ', $archive_browser['quick_family_slugs'])) . '" placeholder="' . esc_attr__('geraet, messgeraet, einschub', 'iss-content-model') . '"></p>';
    echo '<p class="description">' . esc_html__('Kommagetrennte Slugs aus den Objektfamilien. Leer lassen, dann werden die ersten verfügbaren Familien verwendet.', 'iss-content-model') . '</p>';
    echo '<p><label><input type="checkbox" name="iss_content_model[iss_archive_browser_show_source_cards]" value="1" ' . checked($archive_browser['show_source_cards'], true, false) . '> ' . esc_html__('Quellenkarten im Archivbrowser zeigen', 'iss-content-model') . '</label></p>';
    echo '<p class="description">' . esc_html__('Nur für den Modus „Archivbrowser / Objektkorpus“. Der eigentliche Archivbrowser wird direkt aus diesen Einstellungen aufgebaut, nicht aus eingebetteten Blöcken im Inhalt.', 'iss-content-model') . '</p>';

    if ($surface_mode === 'archive_browser') {
        echo '<p class="description">' . esc_html__('Für Archivbrowser-Ausstellungen ist kein eigener Kapitelkorpus nötig. Einleitung, Auszug und Beitragsbild bleiben normale Ausstellungsfelder.', 'iss-content-model') . '</p>';
        return;
    }

    if ($surface_mode === 'archive_exhibition') {
        echo '<p class="description">' . esc_html__('Für Archiv-Kapitelreihen wird der Ausstellungspfad aus der ausgewählten Archivkategorie gelesen. Eine separate Kapitel-Liste ist hier nicht nötig.', 'iss-content-model') . '</p>';
        return;
    }

    echo '<p><label for="iss_corpus_chapter_picker"><strong>' . esc_html__('Korpus-Kapitel', 'iss-content-model') . '</strong></label></p>';
    echo '<div class="iss-content-model-corpus" data-iss-corpus-builder>';
    echo '<div class="iss-content-model-corpus__picker">';
    echo '<select class="widefat" id="iss_corpus_chapter_picker" data-iss-corpus-picker>';
    echo '<option value="">' . esc_html__('Kapitel auswählen', 'iss-content-model') . '</option>';
    foreach ($chapter_choices as $chapter) {
        $label = (string) $chapter['title'];
        if ((string) $chapter['status'] !== 'publish') {
            $label .= ' [' . (string) $chapter['status'] . ']';
        }

        echo '<option value="' . esc_attr((string) $chapter['id']) . '">' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '<p><button type="button" class="button" data-iss-corpus-add>' . esc_html__('Kapitel hinzufügen', 'iss-content-model') . '</button></p>';
    echo '</div>';

    echo '<ol class="iss-content-model-corpus__list" data-iss-corpus-list>';
    foreach ($chapter_ids as $chapter_id) {
        if (!isset($chapter_lookup[$chapter_id])) {
            continue;
        }

        $chapter = $chapter_lookup[$chapter_id];
        $label = (string) $chapter['title'];
        if ((string) $chapter['status'] !== 'publish') {
            $label .= ' [' . (string) $chapter['status'] . ']';
        }

        echo '<li class="iss-content-model-corpus__item" data-iss-corpus-item data-id="' . esc_attr((string) $chapter_id) . '">';
        echo '<span class="iss-content-model-corpus__label">' . esc_html($label) . '</span>';
        echo '<span class="iss-content-model-corpus__actions">';
        echo '<button type="button" class="button-link" data-iss-corpus-up>' . esc_html__('Nach oben', 'iss-content-model') . '</button> ';
        echo '<button type="button" class="button-link" data-iss-corpus-down>' . esc_html__('Nach unten', 'iss-content-model') . '</button> ';
        echo '<button type="button" class="button-link-delete" data-iss-corpus-remove>' . esc_html__('Entfernen', 'iss-content-model') . '</button>';
        echo '</span>';
        echo '<input type="hidden" name="iss_content_model[iss_corpus_chapter_ids][]" value="' . esc_attr((string) $chapter_id) . '">';
        echo '</li>';
    }
    echo '</ol>';
    echo '</div>';
    echo '<p class="description">' . esc_html__('Die Liste hier definiert die Reihenfolge der Ausstellung und zugleich den linearen Lesepfad der verknüpften Publikation.', 'iss-content-model') . '</p>';
}

function iss_content_model_render_projekt_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $period_label = (string) get_post_meta($post->ID, 'iss_period_label', true);

    echo '<p><label for="iss_period_label"><strong>' . esc_html__('Zeitraum-Label', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_period_label" name="iss_content_model[iss_period_label]" value="' . esc_attr($period_label) . '" placeholder="' . esc_attr__('seit 2023 / laufend / 2024', 'iss-content-model') . '"></p>';
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
        unset($raw['iss_primary_place_id']);

        $manual_location = trim((string) ($raw['iss_location'] ?? ''));
        if ($selected_place_id > 0 && $manual_location === '') {
            $raw['iss_location'] = iss_content_model_get_veranstaltung_place_title($selected_place_id);
        }
    } elseif ($post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        $raw['iss_corpus_chapter_ids'] = implode(',', iss_content_model_parse_id_list($raw['iss_corpus_chapter_ids'] ?? []));
    }

    foreach ($definitions[$post_type] as $key => $config) {
        if ($key === 'iss_timeline_item_id') {
            continue;
        }

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
}
add_action('save_post', 'iss_content_model_save_meta_box', 20, 1);
