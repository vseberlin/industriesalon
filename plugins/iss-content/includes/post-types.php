<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_register_post_types() {
    register_post_type(ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, [
        'labels' => [
            'name' => __('Veranstaltungen', 'iss-content-model'),
            'singular_name' => __('Veranstaltung', 'iss-content-model'),
            'menu_name' => __('Veranstaltungen', 'iss-content-model'),
            'add_new_item' => __('Neue Veranstaltung anlegen', 'iss-content-model'),
            'edit_item' => __('Veranstaltung bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'veranstaltungen', 'with_front' => false],
        'menu_position' => 22,
        'menu_icon' => 'dashicons-calendar',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'taxonomies' => ['category', 'post_tag'],
    ]);

    register_post_type(ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE, [
        'labels' => [
            'name' => __('Ausstellungen', 'iss-content-model'),
            'singular_name' => __('Ausstellung', 'iss-content-model'),
            'menu_name' => __('Ausstellungen', 'iss-content-model'),
            'add_new_item' => __('Neue Ausstellung anlegen', 'iss-content-model'),
            'edit_item' => __('Ausstellung bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'ausstellungen', 'with_front' => false],
        'menu_position' => 23,
        'menu_icon' => 'dashicons-art',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'taxonomies' => [
            'category',
            'post_tag',
            ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY,
            ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY,
        ],
    ]);

    register_post_type(ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, [
        'labels' => [
            'name' => __('Projekte', 'iss-content-model'),
            'singular_name' => __('Projekt', 'iss-content-model'),
            'menu_name' => __('Projekte', 'iss-content-model'),
            'add_new_item' => __('Neues Projekt anlegen', 'iss-content-model'),
            'edit_item' => __('Projekt bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => true,
        'rewrite' => ['slug' => 'projekte', 'with_front' => false],
        'menu_position' => 24,
        'menu_icon' => 'dashicons-portfolio',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'taxonomies' => ['category', 'post_tag', ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY],
    ]);

    register_post_type(ISS_CONTENT_MODEL_TEAM_POST_TYPE, [
        'labels' => [
            'name' => __('Team', 'iss-content-model'),
            'singular_name' => __('Teammitglied', 'iss-content-model'),
            'menu_name' => __('Team', 'iss-content-model'),
            'add_new_item' => __('Neues Teammitglied anlegen', 'iss-content-model'),
            'edit_item' => __('Teammitglied bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'team', 'with_front' => false],
        'menu_position' => 25,
        'menu_icon' => 'dashicons-businessperson',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes'],
        'taxonomies' => [ISS_CONTENT_MODEL_TEAM_ROLE_TAXONOMY],
    ]);

    register_post_type(ISS_CONTENT_MODEL_VIDEO_POST_TYPE, [
        'labels' => [
            'name' => __('Videos', 'iss-content-model'),
            'singular_name' => __('Video', 'iss-content-model'),
            'menu_name' => __('Videos', 'iss-content-model'),
            'add_new_item' => __('Neues Video anlegen', 'iss-content-model'),
            'edit_item' => __('Video bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'video', 'with_front' => false],
        'menu_position' => 26,
        'menu_icon' => 'dashicons-video-alt3',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'taxonomies' => [ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY],
    ]);

    register_post_type(ISS_CONTENT_MODEL_ENTITY_PROFILE_POST_TYPE, [
        'labels' => [
            'name' => __('Profile', 'iss-content-model'),
            'singular_name' => __('Profil', 'iss-content-model'),
            'menu_name' => __('Profile', 'iss-content-model'),
            'add_new_item' => __('Neues Profil anlegen', 'iss-content-model'),
            'edit_item' => __('Profil bearbeiten', 'iss-content-model'),
        ],
        'public' => true,
        'show_in_rest' => true,
        'has_archive' => false,
        'rewrite' => ['slug' => 'profile', 'with_front' => false],
        'menu_position' => 27,
        'menu_icon' => 'dashicons-id',
        'supports' => ['title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'page-attributes', 'custom-fields'],
        'taxonomies' => ['category', 'post_tag'],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_TEAM_ROLE_TAXONOMY, [ISS_CONTENT_MODEL_TEAM_POST_TYPE], [
        'labels' => [
            'name' => __('Teamrollen', 'iss-content-model'),
            'singular_name' => __('Teamrolle', 'iss-content-model'),
            'menu_name' => __('Teamrollen', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'team-rolle', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_PROJECT_STATUS_TAXONOMY, [ISS_CONTENT_MODEL_PROJEKT_POST_TYPE], [
        'labels' => [
            'name' => __('Projektstatus', 'iss-content-model'),
            'singular_name' => __('Projektstatus', 'iss-content-model'),
            'menu_name' => __('Projektstatus', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'projekt-status', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, [ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE], [
        'labels' => [
            'name' => __('Ausstellungstypen', 'iss-content-model'),
            'singular_name' => __('Ausstellungstyp', 'iss-content-model'),
            'menu_name' => __('Ausstellungstypen', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_ui' => false,
        'show_admin_column' => false,
        'show_in_rest' => true,
        'rest_base' => ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY,
        'rewrite' => ['slug' => 'ausstellungstyp', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY, [ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE], [
        'labels' => [
            'name' => __('Sammlungsbereiche', 'iss-content-model'),
            'singular_name' => __('Sammlungsbereich', 'iss-content-model'),
            'menu_name' => __('Sammlungsbereiche', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'sammlungsbereich', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_TOPIC_TAXONOMY, [
        'post',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
    ], [
        'labels' => [
            'name' => __('Themen', 'iss-content-model'),
            'singular_name' => __('Thema', 'iss-content-model'),
            'menu_name' => __('Themen', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'thema', 'with_front' => false],
    ]);

    register_taxonomy(ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY, [ISS_CONTENT_MODEL_VIDEO_POST_TYPE], [
        'labels' => [
            'name' => __('Videokategorien', 'iss-content-model'),
            'singular_name' => __('Videokategorie', 'iss-content-model'),
            'menu_name' => __('Videokategorien', 'iss-content-model'),
        ],
        'public' => true,
        'hierarchical' => true,
        'show_admin_column' => true,
        'show_in_rest' => true,
        'rewrite' => ['slug' => 'videokategorie', 'with_front' => false],
    ]);
}
add_action('init', 'iss_content_model_register_post_types');

/**
 * These CPTs rely on theme-owned block patterns or document panels in their
 * single-entry editorial workflow, so they opt back into Gutenberg after the
 * global structured-content Classic Editor policy has run.
 */
function iss_content_model_block_editor_post_types(): array
{
    return [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
    ];
}

function iss_content_model_use_block_editor_for_selected_post_types(bool $use_block_editor, string $post_type): bool
{
    if (in_array($post_type, iss_content_model_block_editor_post_types(), true)) {
        return true;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post_type', 'iss_content_model_use_block_editor_for_selected_post_types', 1000, 2);

function iss_content_model_use_block_editor_for_selected_posts(bool $use_block_editor, $post): bool
{
    if (is_numeric($post)) {
        $post = get_post((int) $post);
    }

    if ($post instanceof WP_Post && in_array($post->post_type, iss_content_model_block_editor_post_types(), true)) {
        return true;
    }

    return $use_block_editor;
}
add_filter('use_block_editor_for_post', 'iss_content_model_use_block_editor_for_selected_posts', 1000, 2);

function iss_content_model_attach_shared_topic_taxonomy(): void
{
    if (!taxonomy_exists(ISS_CONTENT_MODEL_TOPIC_TAXONOMY)) {
        return;
    }

    foreach (['publication'] as $post_type) {
        if (!post_type_exists($post_type)) {
            continue;
        }

        register_taxonomy_for_object_type(ISS_CONTENT_MODEL_TOPIC_TAXONOMY, $post_type);
    }
}
add_action('init', 'iss_content_model_attach_shared_topic_taxonomy', 20);

function iss_content_model_get_default_taxonomy_terms() {
    return [
        ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY => [
            ['name' => __('Dauerausstellung', 'iss-content-model'), 'slug' => 'dauerausstellung'],
            ['name' => __('Sonderausstellung', 'iss-content-model'), 'slug' => 'sonderausstellung'],
            ['name' => __('Digitale Ausstellung', 'iss-content-model'), 'slug' => 'digitaleausstellungen'],
        ],
        ISS_CONTENT_MODEL_TOPIC_TAXONOMY => [
            ['name' => __('Elektropolis', 'iss-content-model'), 'slug' => 'elektropolis'],
            ['name' => __('Schöneweide & Stadtraum', 'iss-content-model'), 'slug' => 'schoeneweide-stadtraum'],
            ['name' => __('Transformation', 'iss-content-model'), 'slug' => 'transformation'],
            ['name' => __('Werk für Fernsehelektronik (WF)', 'iss-content-model'), 'slug' => 'wf'],
            ['name' => __('Kabelwerk Oberspree (KWO)', 'iss-content-model'), 'slug' => 'kwo'],
            ['name' => __('Transformatorenwerk Oberschöneweide (TRO)', 'iss-content-model'), 'slug' => 'tro'],
            ['name' => __('Röhren & Fernsehtechnik', 'iss-content-model'), 'slug' => 'roehren-fernsehtechnik'],
            ['name' => __('Arbeits- und Sozialgeschichte', 'iss-content-model'), 'slug' => 'arbeits-und-sozialgeschichte'],
            ['name' => __('Quellen & Fundstücke', 'iss-content-model'), 'slug' => 'quellen-und-fundstuecke'],
            ['name' => __('Licht & Lampentechnik', 'iss-content-model'), 'slug' => 'licht-und-lampentechnik'],
        ],
        ISS_CONTENT_MODEL_COLLECTION_AREA_TAXONOMY => [
            ['name' => __('WF / Werk für Fernsehelektronik', 'iss-content-model'), 'slug' => 'wf-werk-fuer-fernsehelektronik'],
            ['name' => __('TRO / Transformatorenwerk Oberschöneweide', 'iss-content-model'), 'slug' => 'tro-transformatorenwerk-oberschoeneweide'],
            ['name' => __('KWO / Kabelwerk Oberspree', 'iss-content-model'), 'slug' => 'kwo-kabelwerk-oberspree'],
            ['name' => __('Vakuumtechnik & Röhren', 'iss-content-model'), 'slug' => 'vakuumtechnik-roehren'],
            ['name' => __('Mess- und Steuertechnik', 'iss-content-model'), 'slug' => 'mess-und-steuertechnik'],
            ['name' => __('Rundfunk, Ton & Bild', 'iss-content-model'), 'slug' => 'rundfunk-ton-bild'],
            ['name' => __('Lampen & Lichttechnik', 'iss-content-model'), 'slug' => 'lampen-lichttechnik'],
            ['name' => __('Archivmaterial / Fotos / Dokumente', 'iss-content-model'), 'slug' => 'archivmaterial-fotos-dokumente'],
        ],
        ISS_CONTENT_MODEL_VIDEO_CATEGORY_TAXONOMY => [
            ['name' => __('Zeitzeugen', 'iss-content-model'), 'slug' => 'zeitzeugen'],
            ['name' => __('Werk & Technik', 'iss-content-model'), 'slug' => 'werk-technik'],
            ['name' => __('Führungen', 'iss-content-model'), 'slug' => 'fuehrungen'],
            ['name' => __('Orte & Wandel', 'iss-content-model'), 'slug' => 'orte-wandel'],
            ['name' => __('Gespräche & Debatten', 'iss-content-model'), 'slug' => 'gespraeche-debatten'],
        ],
    ];
}

function iss_content_model_maybe_seed_taxonomy_terms() {
    foreach (iss_content_model_get_default_taxonomy_terms() as $taxonomy => $terms) {
        if (!taxonomy_exists($taxonomy) || !is_array($terms)) {
            continue;
        }

        foreach ($terms as $term) {
            $slug = isset($term['slug']) ? sanitize_title((string) $term['slug']) : '';
            $name = isset($term['name']) ? trim((string) $term['name']) : '';
            if ($slug === '' || $name === '') {
                continue;
            }

            if (term_exists($slug, $taxonomy)) {
                continue;
            }

            wp_insert_term($name, $taxonomy, ['slug' => $slug]);
        }
    }

    update_option('iss_content_model_taxonomy_seed_version', ISS_CONTENT_MODEL_VERSION, false);
}
add_action('init', 'iss_content_model_maybe_seed_taxonomy_terms', 30);

function iss_content_model_get_initial_topic_assignments(): array
{
    return [
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE => [
            'betriebsfotoalben-im-wf' => ['wf', 'quellen-und-fundstuecke', 'arbeits-und-sozialgeschichte'],
            'fundstucke-aus-dem-landesarchiv-berlin' => ['quellen-und-fundstuecke', 'schoeneweide-stadtraum'],
            'elektrotechnik-im-wf' => ['wf', 'roehren-fernsehtechnik'],
            'rohren-fur-die-republik' => ['wf', 'roehren-fernsehtechnik'],
            'geschichte-des-wf' => ['wf', 'elektropolis'],
            'brigadebuecher-im-industriesalon' => ['quellen-und-fundstuecke', 'arbeits-und-sozialgeschichte'],
            'produktionspropaganda-im-wf-sender' => ['wf', 'quellen-und-fundstuecke', 'roehren-fernsehtechnik'],
            'frauen-im-wf' => ['wf', 'arbeits-und-sozialgeschichte'],
            'kinder-im-wf' => ['wf', 'arbeits-und-sozialgeschichte'],
            'arbeitsplatze-der-lampen' => ['licht-und-lampentechnik', 'arbeits-und-sozialgeschichte'],
        ],
        'publication' => [
            'schoeneweide-eine-ortsgeschichte' => ['elektropolis', 'schoeneweide-stadtraum', 'transformation'],
            'fundstucke-aus-dem-landesarchiv-berlin-eine-quellengeschichte' => ['quellen-und-fundstuecke', 'schoeneweide-stadtraum'],
            'fundstucke-zur-geschichte-des-nef-im-archiv-des-industriesalons' => ['quellen-und-fundstuecke', 'schoeneweide-stadtraum'],
            'farbfernsehen-in-der-ddr-schon-vor-dem-mauerbau' => ['wf', 'roehren-fernsehtechnik'],
            'rohren-fur-die-republik-eine-technikgeschichte' => ['wf', 'roehren-fernsehtechnik'],
            'geschichte-des-wf-eine-entwicklungsgeschichte' => ['wf', 'elektropolis'],
            'fotoalbum-produktion-im-werk-fuer-fernmeldewesen-hf-1951' => ['quellen-und-fundstuecke', 'arbeits-und-sozialgeschichte'],
            'nef-album' => ['quellen-und-fundstuecke', 'schoeneweide-stadtraum'],
            'fotoalbum-produkte-lkvo-1946' => ['quellen-und-fundstuecke', 'arbeits-und-sozialgeschichte'],
            'fotoalbum-labor-konstruktions-und-versuchswerk-oberspree-1946' => ['quellen-und-fundstuecke', 'kwo'],
            'brigadebuecher-im-industriesalon-eine-quellengeschichte' => ['quellen-und-fundstuecke', 'arbeits-und-sozialgeschichte'],
            'fundstuecke-aus-dem-wf-sender-eine-quellengeschichte' => ['wf', 'quellen-und-fundstuecke', 'roehren-fernsehtechnik'],
            'frauen-im-wf-eine-entwicklungsgeschichte' => ['wf', 'arbeits-und-sozialgeschichte'],
            'kinder-im-wf-eine-entwicklungsgeschichte' => ['wf', 'arbeits-und-sozialgeschichte'],
            'kabelwerk-oberspree-chronik' => ['kwo', 'elektropolis'],
            'transformatorenwerk-oberschoeneweide-chronik' => ['tro', 'elektropolis'],
            'aus-der-vergangenheit-des-werks-fur-fernsehelektronik' => ['wf', 'quellen-und-fundstuecke'],
            'erdfund-eines-agentenfunkgerates' => ['quellen-und-fundstuecke', 'roehren-fernsehtechnik'],
            'das-werk-fur-fernsehelektronik-wf-in-oberschoneweide' => ['wf', 'elektropolis'],
            'das-kabelwerk-oberspree-bruchstucke-eines-industriegiganten' => ['kwo', 'elektropolis'],
        ],
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE => [
            'zukunft-im-gesprach' => ['transformation', 'schoeneweide-stadtraum'],
        ],
        'post' => [
            'futura-zukuenfte-erleben-eroeffnung-am-freitag-10-4-26-19-uhr' => ['transformation', 'schoeneweide-stadtraum'],
            'zukunft-im-gespraech' => ['transformation', 'schoeneweide-stadtraum'],
            'kiez-tour-wilhelminenhofstrasse-2' => ['elektropolis', 'schoeneweide-stadtraum'],
            'fuehrungen-waldfriedhof-oberschoeneweide' => ['schoeneweide-stadtraum'],
            'kiez-tour-wilhelminenhofstrasse-am-3-januar-faellt-aus' => ['elektropolis', 'schoeneweide-stadtraum'],
            'filmpremiere-am-13-januar-im-industriesalon' => ['transformation', 'schoeneweide-stadtraum'],
            'das-archiv-von-fraeulein-krause' => ['quellen-und-fundstuecke'],
            'salongespraech-vielfaeltig-tolerant-zukunftsoffen-perspektiven-fuer-eine-solidarische-zukunft-in-schoeneweide' => ['transformation', 'schoeneweide-stadtraum'],
            'sonderprogramm-zum-tag-des-offenen-denkmals' => ['elektropolis', 'schoeneweide-stadtraum'],
            'brueckenfest-schoeneweide' => ['schoeneweide-stadtraum'],
            'elektropolis-tour-standard' => ['elektropolis', 'schoeneweide-stadtraum'],
            'nachhall-der-elektropolis-2' => ['elektropolis', 'schoeneweide-stadtraum'],
            'boulevard-der-industriekultur-eine-projektidee-in-vorbereitung' => ['transformation', 'schoeneweide-stadtraum'],
            'der-boulevard-der-industriekultur' => ['transformation', 'schoeneweide-stadtraum'],
            'eroeffnung-stadtlabor-wilhelminenhofstrasse' => ['transformation', 'schoeneweide-stadtraum'],
            'update-fuer-stadtlabor-wilhelminenhofstrasse' => ['transformation', 'schoeneweide-stadtraum'],
            'informationsveranstaltung-zum-stadtlabor-wilhelminenhofstrasse-am-30-april' => ['transformation', 'schoeneweide-stadtraum'],
            'die-entstehung-der-elektropolis-schoeneweide-das-werk-fuer-fernsehelektronik' => ['elektropolis', 'wf'],
            'die-entstehung-der-elektropolis-schoeneweide-das-kabelwerk-oberspree' => ['elektropolis', 'kwo'],
            'die-entstehung-der-elektropolis-schoeneweide-das-transformatorenwerk' => ['elektropolis', 'tro'],
            'die-trolli-werbung' => ['quellen-und-fundstuecke'],
            'licht-und-transparenz-3' => ['licht-und-lampentechnik'],
            'licht-und-transparenz-2' => ['licht-und-lampentechnik'],
            'light-magic' => ['licht-und-lampentechnik'],
        ],
    ];
}

function iss_content_model_find_post_id_by_type_and_slug(string $post_type, string $post_name): int
{
    $post_type = sanitize_key($post_type);
    $post_name = sanitize_title($post_name);
    if ($post_type === '' || $post_name === '' || !post_type_exists($post_type)) {
        return 0;
    }

    $posts = get_posts([
        'post_type' => $post_type,
        'name' => $post_name,
        'post_status' => 'any',
        'posts_per_page' => 1,
        'fields' => 'ids',
        'no_found_rows' => true,
    ]);

    return !empty($posts) ? (int) $posts[0] : 0;
}

function iss_content_model_maybe_seed_initial_topic_assignments(): void
{
    $migration_version = '2026-05-10-starter-topics-v1';
    $stored_version = (string) get_option('iss_content_model_topic_assignment_version', '');
    if ($stored_version === $migration_version || !taxonomy_exists(ISS_CONTENT_MODEL_TOPIC_TAXONOMY)) {
        return;
    }

    foreach (iss_content_model_get_initial_topic_assignments() as $post_type => $slug_map) {
        if (!is_array($slug_map)) {
            continue;
        }

        foreach ($slug_map as $post_name => $topic_slugs) {
            $post_id = iss_content_model_find_post_id_by_type_and_slug((string) $post_type, (string) $post_name);
            if ($post_id <= 0) {
                continue;
            }

            $resolved_terms = [];
            foreach ((array) $topic_slugs as $topic_slug) {
                $topic_slug = sanitize_title((string) $topic_slug);
                if ($topic_slug === '' || !term_exists($topic_slug, ISS_CONTENT_MODEL_TOPIC_TAXONOMY)) {
                    continue;
                }
                $resolved_terms[] = $topic_slug;
            }

            if (empty($resolved_terms)) {
                continue;
            }

            $existing_terms = wp_get_post_terms($post_id, ISS_CONTENT_MODEL_TOPIC_TAXONOMY, ['fields' => 'slugs']);
            if (is_wp_error($existing_terms)) {
                $existing_terms = [];
            }

            sort($existing_terms);
            $next_terms = array_values(array_unique($resolved_terms));
            sort($next_terms);
            if ($existing_terms === $next_terms) {
                continue;
            }

            wp_set_object_terms($post_id, array_values(array_unique($resolved_terms)), ISS_CONTENT_MODEL_TOPIC_TAXONOMY, false);
        }
    }

    update_option('iss_content_model_topic_assignment_version', $migration_version, false);
}
add_action('init', 'iss_content_model_maybe_seed_initial_topic_assignments', 40);
