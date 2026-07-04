<?php

if (!defined('ABSPATH')) {
    exit;
}

add_action('add_meta_boxes', function () {
    add_meta_box(
        'iss-content-model-veranstaltung',
        __('Pflichtangaben', 'iss-content-model'),
        'iss_content_model_render_veranstaltung_basis_box',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'normal',
        'high'
    );

    add_meta_box(
        'iss-content-model-veranstaltung-type',
        __('Struktur & Art', 'iss-content-model'),
        'iss_content_model_render_veranstaltung_type_box',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'normal',
        'high'
    );

    add_meta_box(
        'iss-content-model-veranstaltung-content',
        __('Struktur', 'iss-content-model'),
        'iss_content_model_render_veranstaltung_content_box',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'normal',
        'high'
    );

    add_meta_box(
        'iss-content-model-veranstaltung-booking',
        __('Buchung', 'iss-content-model'),
        'iss_content_model_render_veranstaltung_booking_box',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        'side',
        'high'
    );

    if (!iss_content_model_acf_handles_ausstellung_meta()) {
        add_meta_box(
            'iss-content-model-ausstellung',
            __('Pflichtangaben', 'iss-content-model'),
            'iss_content_model_render_ausstellung_box',
            ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
            'side',
            'high'
        );
    }

    add_meta_box(
        'iss-content-model-projekt',
        __('Pflichtangaben', 'iss-content-model'),
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

    add_meta_box(
        'iss-content-model-video-transcript-json',
        __('Transkriptstruktur', 'iss-content-model'),
        'iss_content_model_render_video_transcript_json_box',
        ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        'normal',
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

function iss_content_model_should_hide_editor_dashboard_technical_boxes(string $post_type): bool
{
    return iss_content_model_should_hide_wholesale_editor_surfaces($post_type);
}

function iss_content_model_should_lock_editor_dashboard(string $post_type): bool
{
    return iss_content_model_use_editor_dashboard($post_type)
        && iss_content_model_should_hide_wholesale_editor_surfaces($post_type);
}

function iss_content_model_get_wholesale_simplification_post_types(): array
{
    $post_types = [
        'post',
        'page',
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE,
        ISS_CONTENT_MODEL_TEAM_POST_TYPE,
        ISS_CONTENT_MODEL_VIDEO_POST_TYPE,
        ISS_CONTENT_MODEL_ENTITY_PROFILE_POST_TYPE,
        'publication',
        'fuehrung',
        'archivbeitrag',
        'archivsammlung',
        'archivobjekt',
        'register_place',
    ];

    foreach ([
        'iss_content_model_get_editor_dashboard_post_types',
        'iss_relations_get_supported_post_types',
        'iss_graph_get_related_promotion_post_types',
        'iss_graph_get_content_relation_post_types',
        'iss_graph_get_search_signal_post_types',
        'iss_graph_get_availability_signal_post_types',
        'iss_graph_get_editorial_signal_context_post_types',
        'iss_wf_import_get_archivset_supported_post_types',
        'iss_wf_import_get_suggestion_post_types',
    ] as $provider) {
        if (function_exists($provider)) {
            $post_types = array_merge($post_types, (array) $provider());
        }
    }

    $post_types = (array) apply_filters('iss_content_model_wholesale_simplification_post_types', $post_types);

    return array_values(array_unique(array_filter(array_map('sanitize_key', $post_types), 'post_type_exists')));
}

function iss_content_model_should_hide_wholesale_editor_surfaces(string $post_type): bool
{
    return !current_user_can('manage_options')
        && in_array(sanitize_key($post_type), iss_content_model_get_wholesale_simplification_post_types(), true);
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
    $post_type = sanitize_key($post_type);
    if (in_array($post_type, [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE,
        'publication',
        'fuehrung',
    ], true)) {
        return true;
    }

    if (use_block_editor_for_post_type($post_type)) {
        return false;
    }

    return !current_user_can('manage_options')
        && in_array($post_type, iss_content_model_get_editor_dashboard_post_types(), true);
}

function iss_content_model_get_editor_dashboard_box_ids(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    $sections = iss_content_model_get_editor_dashboard_sections($post_type);
    if ($sections) {
        $box_ids = [];
        foreach ($sections as $section) {
            $box_ids = array_merge($box_ids, (array) ($section['boxIds'] ?? []));
        }

        return array_values(array_unique(array_filter(array_map('sanitize_key', $box_ids))));
    }

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
        $box_ids = [
            'iss-content-model-veranstaltung',
            'postexcerpt',
            'postimagediv',
            'iss-graph-related-promotion',
            'iss-content-editorial-sets',
        ];
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

function iss_content_model_use_compact_dashboard_relation_actions(string $post_type): bool
{
    return !current_user_can('manage_options') && iss_content_model_use_editor_dashboard($post_type);
}

function iss_content_model_get_dashboard_relation_modal_targets(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    $targets = [
        'iss-relations-places' => [
            'label' => __('Orte', 'iss-content-model'),
            'description' => __('Orte und Stationen, die mit diesem Inhalt verbunden sind.', 'iss-content-model'),
            'buttonLabel' => __('Orte', 'iss-content-model'),
        ],
        'iss-graph-public-content-relations' => [
            'label' => __('Personen und Organisationen', 'iss-content-model'),
            'description' => __('Personen und Organisationen, die mit diesem Inhalt verbunden sind.', 'iss-content-model'),
            'buttonLabel' => __('Akteure', 'iss-content-model'),
        ],
        'iss-wf-import-archive-picker' => [
            'label' => __('Archivmaterial', 'iss-content-model'),
            'description' => __('Archivmaterial, das mit diesem Inhalt verbunden ist.', 'iss-content-model'),
            'buttonLabel' => __('Archive', 'iss-content-model'),
        ],
        'iss-content-editorial-sets' => [
            'label' => __('Media', 'iss-content-model'),
            'description' => __('Sets und Media, die mit diesem Inhalt verbunden sind.', 'iss-content-model'),
            'buttonLabel' => __('Media', 'iss-content-model'),
        ],
    ];

    return (array) apply_filters('iss_content_model_dashboard_relation_modal_targets', $targets, $post_type);
}

function iss_content_model_get_editor_dashboard_relation_box_ids(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    $with_sets = [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE,
        'publication',
    ];
    if (!in_array($post_type, array_merge($with_sets, ['fuehrung']), true)) {
        return [];
    }

    $shared_relation_boxes = [
        'iss-relations-places',
        'iss-graph-public-content-relations',
        'iss-wf-import-archive-picker',
        'iss-content-editorial-sets',
        'iss-graph-related-promotion',
        'iss-graph-editorial-signals',
    ];

    if (!in_array($post_type, $with_sets, true)) {
        $shared_relation_boxes = array_values(array_diff($shared_relation_boxes, ['iss-content-editorial-sets']));
    }

    if ($post_type === 'publication') {
        $shared_relation_boxes[] = 'iss-publication-related-publications';
    }

    return (array) apply_filters(
        'iss_content_model_editor_dashboard_relation_box_ids',
        array_values(array_unique(array_filter(array_map('sanitize_key', $shared_relation_boxes)))),
        $post_type
    );
}

function iss_content_model_get_available_dashboard_relation_modal_targets(string $post_type, array $box_ids): array
{
    $targets = iss_content_model_get_dashboard_relation_modal_targets($post_type);
    $box_ids = array_values(array_filter(array_map('sanitize_key', $box_ids)));
    $available_targets = [];

    foreach ($targets as $target_id => $target) {
        $target_id = sanitize_key((string) $target_id);
        if (
            $target_id === ''
            || !in_array($target_id, $box_ids, true)
            || !is_array($target)
            || !iss_content_model_dashboard_relation_target_is_available($post_type, $target_id)
        ) {
            continue;
        }

        $available_targets[$target_id] = array_merge(['target' => $target_id], $target);
    }

    return $available_targets;
}

function iss_content_model_dashboard_relation_target_is_available(string $post_type, string $target_id): bool
{
    $post_type = sanitize_key($post_type);
    $target_id = sanitize_key($target_id);

    if ($target_id === 'iss-relations-places') {
        if (!function_exists('iss_relations_is_supported_post_type') || !iss_relations_is_supported_post_type($post_type)) {
            return false;
        }

        if (function_exists('iss_editorial_get_format_for_post_type') && function_exists('iss_editorial_uses_integrated_route_stations')) {
            $format = iss_editorial_get_format_for_post_type($post_type);
            if (is_array($format) && iss_editorial_uses_integrated_route_stations((string) ($format['slug'] ?? ''), $post_type)) {
                return false;
            }
        }

        return true;
    }

    if ($target_id === 'iss-graph-public-content-relations') {
        return function_exists('iss_graph_is_content_relation_post_type') && iss_graph_is_content_relation_post_type($post_type);
    }

    if ($target_id === 'iss-wf-import-archive-picker') {
        return function_exists('iss_wf_import_is_archivset_supported_post_type') && iss_wf_import_is_archivset_supported_post_type($post_type);
    }

    if ($target_id === 'iss-content-editorial-sets') {
        return function_exists('iss_content_editorial_sets_supported_post_types')
            && in_array($post_type, array_map('sanitize_key', iss_content_editorial_sets_supported_post_types()), true);
    }

    return true;
}

function iss_content_model_get_dashboard_promotion_target_slug(array $sections): string
{
    $fallback_slug = '';

    foreach ($sections as $section) {
        if (!is_array($section)) {
            continue;
        }

        $slug = sanitize_key((string) ($section['slug'] ?? ''));
        if (in_array($slug, ['facts', 'schedule', 'booking'], true)) {
            return $slug;
        }

        if ($slug === 'identity') {
            $fallback_slug = $slug;
        }
    }

    return $fallback_slug;
}

function iss_content_model_compact_dashboard_relation_sections(array $sections, string $post_type): array
{
    if (!iss_content_model_use_compact_dashboard_relation_actions($post_type)) {
        return $sections;
    }

    $move_promotion_box = false;

    foreach ($sections as &$section) {
        if (!is_array($section) || sanitize_key((string) ($section['slug'] ?? '')) !== 'relations') {
            continue;
        }

        $box_ids = array_values(array_filter(array_map('sanitize_key', (array) ($section['boxIds'] ?? []))));
        $relation_target_box_ids = array_values(array_intersect(
            array_map('sanitize_key', array_keys(iss_content_model_get_dashboard_relation_modal_targets($post_type))),
            $box_ids
        ));
        $remove_box_ids = array_merge($relation_target_box_ids, ['iss-graph-editorial-signals']);

        if (in_array('iss-graph-related-promotion', $box_ids, true)) {
            $remove_box_ids[] = 'iss-graph-related-promotion';
            $move_promotion_box = !in_array($post_type, [ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE], true);
        }

        $section['boxIds'] = array_values(array_diff($box_ids, array_values(array_unique($remove_box_ids))));
        $section['modalTargets'] = [];
    }
    unset($section);

    if ($move_promotion_box) {
        $target_slug = iss_content_model_get_dashboard_promotion_target_slug($sections);
        if ($target_slug !== '') {
            foreach ($sections as &$section) {
                if (!is_array($section) || sanitize_key((string) ($section['slug'] ?? '')) !== $target_slug) {
                    continue;
                }

                $section['boxIds'] = array_values(array_unique(array_merge(
                    (array) ($section['boxIds'] ?? []),
                    ['iss-graph-related-promotion']
                )));
                break;
            }
            unset($section);
        }
    }

    return $sections;
}

function iss_content_model_sanitize_editor_modal_targets(array $targets): array
{
    return array_values(array_filter(array_map(static function ($target): array {
        if (!is_array($target)) {
            return [];
        }

        $target_id = sanitize_key((string) ($target['target'] ?? ''));
        if ($target_id === '') {
            return [];
        }

        return [
            'target' => $target_id,
            'label' => sanitize_text_field((string) ($target['label'] ?? $target_id)),
            'description' => sanitize_text_field((string) ($target['description'] ?? '')),
            'buttonLabel' => sanitize_text_field((string) ($target['buttonLabel'] ?? __('Bearbeiten', 'iss-content-model'))),
        ];
    }, $targets)));
}

function iss_content_model_get_editor_side_rail_sections(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    if (!iss_content_model_use_compact_dashboard_relation_actions($post_type)) {
        return [];
    }

    $targets = [];
    $available_targets = iss_content_model_get_available_dashboard_relation_modal_targets(
        $post_type,
        iss_content_model_get_editor_dashboard_relation_box_ids($post_type)
    );

    foreach ($available_targets as $target) {
        $targets[] = $target;
    }

    if (!$targets) {
        return [];
    }

    return [
        [
            'slug' => 'relations',
            'label' => __('Verknüpfte Inhalte', 'iss-content-model'),
            'description' => __('Alles Wichtige miteinander vernetzen. Verknüpfen Sie Orte, Personen, Organisationen und Archivmaterial mit Ihrer Veranstaltung. So stellen Sie sicher, dass Ihr Event überall leicht gefunden und automatisch im richtigen Zusammenhang angezeigt wird.', 'iss-content-model'),
            'modalTargets' => iss_content_model_sanitize_editor_modal_targets($targets),
        ],
    ];
}

function iss_content_model_get_editor_dashboard_sections(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    $sections = [];

    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $sections = [
            [
                'slug' => 'identity',
                'label' => __('Identität', 'iss-content-model'),
                'description' => __('Kurzbeschreibung, Kartenbild und Pflichtangaben für Listen, Teaser und Vorschauen.', 'iss-content-model'),
                'boxIds' => ['postexcerpt', 'postimagediv', 'iss-content-model-veranstaltung-type', 'iss-content-model-veranstaltung'],
            ],
            [
                'slug' => 'composition',
                'label' => __('Redaktionelle Komposition', 'iss-content-model'),
                'description' => '',
                'boxIds' => ['iss-content-model-veranstaltung-content'],
            ],
            [
                'slug' => 'relations',
                'label' => __('Beziehungen und Verweise', 'iss-content-model'),
                'description' => __('Orte, Personen, Organisationen, Archivmaterial, Sets und Hervorhebungen mit ihren bestehenden Speicherpfaden.', 'iss-content-model'),
                'boxIds' => [
                    'iss-relations-places',
                    'iss-graph-public-content-relations',
                    'iss-wf-import-archive-picker',
                    'iss-content-editorial-sets',
                    'iss-graph-editorial-signals',
                ],
            ],
        ];
    } elseif ($post_type === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        $sections = [
            [
                'slug' => 'identity',
                'label' => __('Identität', 'iss-content-model'),
                'description' => __('Kurzbeschreibung, Kartenbild und Pflichtangaben für Projektlisten, Karten und Vorschauen.', 'iss-content-model'),
                'boxIds' => ['postexcerpt', 'postimagediv', 'iss-content-model-projekt'],
            ],
            [
                'slug' => 'composition',
                'label' => __('Redaktionelle Komposition', 'iss-content-model'),
                'description' => __('Projekt-Erzählung im bestehenden iss-editorial JSON-Canvas.', 'iss-content-model'),
                'selectors' => ['.iss-editorial-shell'],
            ],
            [
                'slug' => 'relations',
                'label' => __('Verknüpfte Inhalte', 'iss-content-model'),
                'description' => __('Hier finden Sie Verknüpfungen zu Orten, Personen, Organisationen, Archivmaterial und Media.', 'iss-content-model'),
                'boxIds' => [
                    'iss-relations-places',
                    'iss-graph-public-content-relations',
                    'iss-wf-import-archive-picker',
                    'iss-content-editorial-sets',
                    'iss-graph-related-promotion',
                    'iss-graph-editorial-signals',
                ],
            ],
        ];
    } elseif ($post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE) {
        $facts_box_id = iss_content_model_acf_handles_ausstellung_meta()
            ? 'acf-group_iss_ausstellung_controls'
            : 'iss-content-model-ausstellung';
        $sections = [
            [
                'slug' => 'identity',
                'label' => __('Identität', 'iss-content-model'),
                'description' => __('Kurzbeschreibung, Kartenbild und Pflichtangaben für Ausstellungslisten, Karten und Vorschauen.', 'iss-content-model'),
                'boxIds' => ['postexcerpt', 'postimagediv', $facts_box_id],
            ],
            [
                'slug' => 'composition',
                'label' => __('Redaktionelle Komposition', 'iss-content-model'),
                'description' => __('Ausstellungserzählung im bestehenden iss-editorial JSON-Canvas.', 'iss-content-model'),
                'selectors' => ['.iss-editorial-shell'],
            ],
            [
                'slug' => 'relations',
                'label' => __('Beziehungen und Verweise', 'iss-content-model'),
                'description' => __('Orte, Personen, Organisationen, Archivmaterial, Sets und Hervorhebungen mit ihren bestehenden Speicherpfaden.', 'iss-content-model'),
                'boxIds' => [
                    'iss-relations-places',
                    'iss-graph-public-content-relations',
                    'iss-wf-import-archive-picker',
                    'iss-content-editorial-sets',
                    'iss-graph-related-promotion',
                    'iss-graph-editorial-signals',
                ],
            ],
        ];
    } elseif ($post_type === 'publication') {
        $sections = [
            [
                'slug' => 'identity',
                'label' => __('Identität', 'iss-content-model'),
                'description' => __('Kurzbeschreibung, Cover/Kartenbild und Pflichtangaben für Publikationslisten, Karten und Vorschauen.', 'iss-content-model'),
                'boxIds' => ['postexcerpt', 'postimagediv', 'iss-publication-bibliography', 'iss-publication-display'],
            ],
            [
                'slug' => 'composition',
                'label' => __('Redaktionelle Komposition', 'iss-content-model'),
                'description' => __('Publikations-Erzählung im bestehenden iss-editorial JSON-Canvas.', 'iss-content-model'),
                'selectors' => ['.iss-editorial-shell'],
            ],
            [
                'slug' => 'commerce',
                'label' => __('Verkauf', 'iss-content-model'),
                'description' => __('Optionale Verkaufs- und Bestellhinweise für sale-fähige Publikationen.', 'iss-content-model'),
                'boxIds' => ['iss-publication-sale'],
            ],
            [
                'slug' => 'relations',
                'label' => __('Beziehungen und Verweise', 'iss-content-model'),
                'description' => __('Orte, Personen, Organisationen, Archivmaterial, Sets und Weiterlesen-Auswahl mit ihren bestehenden Speicherpfaden.', 'iss-content-model'),
                'boxIds' => [
                    'iss-relations-places',
                    'iss-graph-public-content-relations',
                    'iss-wf-import-archive-picker',
                    'iss-content-editorial-sets',
                    'iss-publication-related-publications',
                    'iss-graph-related-promotion',
                    'iss-graph-editorial-signals',
                ],
            ],
        ];
    } elseif ($post_type === 'fuehrung') {
        $sections = [
            [
                'slug' => 'identity',
                'label' => __('Identität', 'iss-content-model'),
                'description' => __('Kurzbeschreibung, Kartenbild und Pflichtangaben für Führungslisten, Karten und Vorschauen.', 'iss-content-model'),
                'boxIds' => ['postexcerpt', 'postimagediv', 'iss-fuehrung-data', 'iss-graph-related-promotion'],
            ],
            [
                'slug' => 'composition',
                'label' => __('Redaktionelle Komposition', 'iss-content-model'),
                'description' => '',
                'selectors' => ['.iss-editorial-shell'],
            ],
            [
                'slug' => 'relations',
                'label' => __('Beziehungen und Verweise', 'iss-content-model'),
                'description' => __('Personen, Organisationen, Archivmaterial und Hervorhebungen mit ihren bestehenden Speicherpfaden.', 'iss-content-model'),
                'boxIds' => [
                    'iss-relations-places',
                    'iss-graph-public-content-relations',
                    'iss-wf-import-archive-picker',
                    'iss-graph-editorial-signals',
                ],
            ],
        ];
    } elseif ($post_type === ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE) {
        $sections = [
            [
                'slug' => 'identity',
                'label' => __('Identität', 'iss-content-model'),
                'description' => __('Kurzbeschreibung und Kartenbild für Rückblicklisten, Karten und Vorschauen.', 'iss-content-model'),
                'boxIds' => ['postexcerpt', 'postimagediv'],
            ],
            [
                'slug' => 'composition',
                'label' => __('Redaktionelle Komposition', 'iss-content-model'),
                'description' => __('Rückblick-Erzählung im bestehenden iss-editorial JSON-Canvas.', 'iss-content-model'),
                'selectors' => ['.iss-editorial-shell'],
            ],
            [
                'slug' => 'relations',
                'label' => __('Beziehungen und Verweise', 'iss-content-model'),
                'description' => __('Sets, Orte, Personen, Organisationen und Archivmaterial mit ihren bestehenden Speicherpfaden.', 'iss-content-model'),
                'boxIds' => [
                    'iss-content-editorial-sets',
                    'iss-relations-places',
                    'iss-graph-public-content-relations',
                    'iss-wf-import-archive-picker',
                    'iss-graph-related-promotion',
                    'iss-graph-editorial-signals',
                ],
            ],
        ];
    }

    $sections = (array) apply_filters('iss_content_model_editor_dashboard_sections', $sections, $post_type);
    $sections = iss_content_model_compact_dashboard_relation_sections($sections, $post_type);

    return array_values(array_filter(array_map(static function ($section): array {
        if (!is_array($section)) {
            return [];
        }

        $slug = sanitize_key((string) ($section['slug'] ?? ''));
        $box_ids = array_values(array_unique(array_filter(array_map('sanitize_key', (array) ($section['boxIds'] ?? [])))));
        $modal_targets = iss_content_model_sanitize_editor_modal_targets((array) ($section['modalTargets'] ?? []));
        $selectors = array_values(array_unique(array_filter(array_map(static function ($selector): string {
            $selector = trim(sanitize_text_field((string) $selector));
            if ($selector === '' || !preg_match('/^[#.][A-Za-z0-9_-]+$/', $selector)) {
                return '';
            }

            return $selector;
        }, (array) ($section['selectors'] ?? [])))));
        if ($slug === '' || (!$box_ids && !$modal_targets && !$selectors)) {
            return [];
        }

        return [
            'slug' => $slug,
            'label' => sanitize_text_field((string) ($section['label'] ?? $slug)),
            'description' => sanitize_text_field((string) ($section['description'] ?? '')),
            'boxIds' => $box_ids,
            'modalTargets' => $modal_targets,
            'selectors' => $selectors,
        ];
    }, $sections)));
}

function iss_content_model_restore_admin_editor_dashboard_metabox_visibility(string $post_type): void
{
    $post_type = sanitize_key($post_type);
    if (!in_array($post_type, [
        ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE,
        ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE,
        ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE,
        'publication',
        'fuehrung',
    ], true)) {
        return;
    }

    $user_id = get_current_user_id();
    if ($user_id <= 0) {
        return;
    }

    if (current_user_can('manage_options')) {
        $managed_boxes = [
            'slugdiv',
            'postcustom',
            'pageparentdiv',
            'categorydiv',
            'tagsdiv-post_tag',
            'iss-graph-search-signal',
        ];
    } elseif (iss_content_model_should_lock_editor_dashboard($post_type)) {
        $managed_boxes = array_merge(
            iss_content_model_get_editor_dashboard_box_ids($post_type),
            iss_content_model_get_editor_dashboard_relation_box_ids($post_type)
        );
    } else {
        return;
    }

    $managed_boxes = array_values(array_unique(array_filter(array_map('sanitize_key', $managed_boxes))));
    if (!$managed_boxes) {
        return;
    }

    foreach (['metaboxhidden_' . $post_type, 'closedpostboxes_' . $post_type] as $option) {
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

add_filter('screen_options_show_screen', function (bool $show): bool {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || !isset($screen->post_type)) {
        return $show;
    }

    return iss_content_model_should_lock_editor_dashboard((string) $screen->post_type) ? false : $show;
});

add_action('admin_menu', function (): void {
    if (current_user_can('manage_options')) {
        return;
    }

    remove_menu_page('edit.php');
}, 99);

add_filter('disable_categories_dropdown', function ($disable, string $post_type) {
    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return true;
    }

    return $disable;
}, 10, 2);

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

function iss_content_model_get_ausstellung_type_options(): array
{
    return [
        'sonderausstellung' => __('Sonderausstellung', 'iss-content-model'),
        'dauerausstellung' => __('Dauerausstellung', 'iss-content-model'),
        'digitaleausstellungen' => __('Digitale Ausstellung', 'iss-content-model'),
    ];
}

function iss_content_model_get_selected_ausstellung_type(int $post_id): string
{
    if ($post_id <= 0 || !taxonomy_exists(ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY)) {
        return '';
    }

    $terms = wp_get_post_terms($post_id, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, ['fields' => 'slugs']);
    if (is_wp_error($terms)) {
        return '';
    }

    $slugs = array_map('sanitize_title', (array) $terms);
    foreach (array_keys(iss_content_model_get_ausstellung_type_options()) as $slug) {
        if (in_array($slug, $slugs, true)) {
            return $slug;
        }
    }

    return '';
}

function iss_content_model_save_ausstellung_type(int $post_id, string $type_slug): void
{
    if ($post_id <= 0 || !taxonomy_exists(ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY)) {
        return;
    }

    $type_slug = sanitize_title($type_slug);
    $options = iss_content_model_get_ausstellung_type_options();
    if (!array_key_exists($type_slug, $options)) {
        return;
    }

    if (!term_exists($type_slug, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY)) {
        wp_insert_term((string) $options[$type_slug], ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, ['slug' => $type_slug]);
    }

    wp_set_object_terms($post_id, $type_slug, ISS_CONTENT_MODEL_AUSSTELLUNG_TYPE_TAXONOMY, false);
}

add_action('media_buttons', function (string $editor_id): void {
    if ($editor_id !== 'content') {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'post' || !isset($screen->post_type) || !iss_content_model_use_editor_modal_controls((string) $screen->post_type)) {
        return;
    }
    if (iss_content_model_use_editor_dashboard((string) $screen->post_type)) {
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
    iss_content_model_restore_admin_editor_dashboard_metabox_visibility((string) $screen->post_type);

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
                'hideDashboardTechnicalBoxes' => iss_content_model_should_hide_editor_dashboard_technical_boxes((string) $screen->post_type),
                'lockEditorDashboard' => iss_content_model_should_lock_editor_dashboard((string) $screen->post_type),
                'moveAusstellungTopGroups' => false,
                'moveEditorTopGroups' => $uses_editor_dashboard,
                'dashboardSections' => $uses_editor_dashboard
                    ? iss_content_model_get_editor_dashboard_sections((string) $screen->post_type)
                    : [],
                'sideRailSections' => $uses_editor_dashboard
                    ? iss_content_model_get_editor_side_rail_sections((string) $screen->post_type)
                    : [],
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

function iss_content_model_get_wholesale_simplification_metabox_ids(string $post_type): array
{
    $post_type = sanitize_key($post_type);
    $box_ids = [
        'slugdiv',
        'postcustom',
        'revisionsdiv',
        'pageparentdiv',
        'categorydiv',
        'tagsdiv-post_tag',
        'iss-graph-search-signal',
        'iss-graph-availability-signal',
        'iss-graph-editorial-signals',
        'iss-graph-video-transcript-review',
        'iss-wf-import-suggestions',
    ];

    foreach (get_object_taxonomies($post_type, 'objects') as $taxonomy) {
        if (!$taxonomy instanceof WP_Taxonomy || !$taxonomy->show_ui || $taxonomy->hierarchical) {
            continue;
        }

        $box_ids[] = 'tagsdiv-' . $taxonomy->name;
    }

    if ($post_type === ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        $box_ids[] = ISS_CONTENT_MODEL_TOPIC_TAXONOMY . 'div';
    }

    $box_ids = (array) apply_filters('iss_content_model_wholesale_simplification_metabox_ids', $box_ids, $post_type);

    return array_values(array_unique(array_filter(array_map('sanitize_key', $box_ids))));
}

function iss_content_model_remove_wholesale_simplification_metaboxes(string $post_type): void
{
    if (!iss_content_model_should_hide_wholesale_editor_surfaces($post_type)) {
        return;
    }

    foreach (iss_content_model_get_wholesale_simplification_metabox_ids($post_type) as $box_id) {
        foreach (['normal', 'side', 'advanced'] as $context) {
            remove_meta_box($box_id, $post_type, $context);
        }
    }
}
add_action('add_meta_boxes', 'iss_content_model_remove_wholesale_simplification_metaboxes', 120);

function iss_content_model_remove_project_editor_promotion_metabox(string $post_type): void
{
    if ($post_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE || current_user_can('manage_options')) {
        return;
    }

    foreach (['normal', 'side', 'advanced'] as $context) {
        remove_meta_box('iss-graph-related-promotion', ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, $context);
    }
}
add_action('add_meta_boxes', 'iss_content_model_remove_project_editor_promotion_metabox', 130);

function iss_content_model_remove_veranstaltung_promotion_metabox(string $post_type): void
{
    if ($post_type !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return;
    }

    foreach (['normal', 'side', 'advanced'] as $context) {
        remove_meta_box('iss-graph-related-promotion', ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE, $context);
    }
}
add_action('add_meta_boxes', 'iss_content_model_remove_veranstaltung_promotion_metabox', 130);

add_action('add_meta_boxes', function (string $post_type): void {
    static $registered = [];

    $post_type = sanitize_key($post_type);
    if ($post_type === '' || isset($registered[$post_type])) {
        return;
    }

    $registered[$post_type] = true;
    add_action(
        'add_meta_boxes_' . $post_type,
        static function () use ($post_type): void {
            iss_content_model_remove_wholesale_simplification_metaboxes($post_type);
        },
        120
    );
}, 1);

add_action('admin_enqueue_scripts', function ($hook) {
    if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return;
    }

    $post_id = 0;
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context for asset loading.
    if (isset($_GET['post'])) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin context for asset loading.
        $post_id = absint(wp_unslash($_GET['post']));
    }

    wp_enqueue_media(['post' => $post_id]);
    if (function_exists('iss_editorial_enqueue_archive_picker_assets')) {
        iss_editorial_enqueue_archive_picker_assets($post_id);
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

    $script_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-veranstaltung-content.js';
    if (file_exists($script_path)) {
        wp_enqueue_script(
            'iss-content-model-veranstaltung-content',
            plugins_url('../assets/admin-veranstaltung-content.js', __FILE__),
            [],
            (string) filemtime($script_path),
            true
        );
    }
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
    $native_place_id = absint(get_post_meta($post_id, 'iss_primary_place_id', true));
    if (
        $native_place_id > 0
        && (!function_exists('iss_relations_is_usable_place') || iss_relations_is_usable_place($native_place_id))
    ) {
        return $native_place_id;
    }

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

function iss_content_model_render_veranstaltung_select(string $id, string $name, array $options, string $selected): void
{
    echo '<select class="widefat" id="' . esc_attr($id) . '" name="iss_content_model[' . esc_attr($name) . ']">';
    foreach ($options as $value => $config) {
        $label = is_array($config) ? (string) ($config['label'] ?? $value) : (string) $config;
        echo '<option value="' . esc_attr((string) $value) . '" ' . selected($selected, (string) $value, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

function iss_content_model_render_veranstaltung_entity_select(array $options, string $selected): void
{
    iss_content_model_render_veranstaltung_select('iss_veranstaltung_entity_key', '_iss_entity_key', $options, $selected);
}

function iss_content_model_get_veranstaltung_semantic_key(int $post_id): string
{
    if ($post_id <= 0 || !taxonomy_exists(ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY)) {
        return '';
    }

    $terms = wp_get_post_terms($post_id, ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY, ['fields' => 'slugs']);
    if (is_array($terms) && $terms !== []) {
        $semantic_key = function_exists('iss_content_model_sanitize_veranstaltung_semantic_key')
            ? iss_content_model_sanitize_veranstaltung_semantic_key((string) $terms[0])
            : sanitize_title((string) $terms[0]);
        if ($semantic_key !== '') {
            return $semantic_key;
        }
    }

    return function_exists('iss_content_model_veranstaltung_semantic_from_legacy_entity_key')
        ? iss_content_model_veranstaltung_semantic_from_legacy_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true))
        : '';
}

function iss_content_model_get_veranstaltung_semantic_label(int $post_id): string
{
    $semantic_key = iss_content_model_get_veranstaltung_semantic_key($post_id);

    return $semantic_key !== '' && function_exists('iss_content_model_veranstaltung_semantic_label')
        ? iss_content_model_veranstaltung_semantic_label($semantic_key)
        : '';
}

function iss_content_model_count_veranstaltung_document_refs(array $document): array
{
    $counts = [
        'sections' => 0,
        'gallery_sections' => 0,
        'media_refs' => 0,
        'object_refs' => 0,
        'dynamic_refs' => 0,
    ];

    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }

        $counts['sections']++;
        if ((string) ($section['type'] ?? '') === 'galerie') {
            $counts['gallery_sections']++;
        }
        $counts['media_refs'] += count((array) ($section['media_refs'] ?? []));
        $counts['object_refs'] += count((array) ($section['object_refs'] ?? []));
        $counts['dynamic_refs'] += count((array) ($section['dynamic_refs'] ?? []));
    }

    return $counts;
}

function iss_content_model_get_veranstaltung_editor_summary(WP_Post $post, ?array $document = null): array
{
    $post_id = (int) $post->ID;
    $selected_place_id = iss_content_model_get_veranstaltung_primary_place_id($post_id);
    $location = trim((string) get_post_meta($post_id, 'iss_location', true));
    $start = trim((string) get_post_meta($post_id, 'iss_start_datetime', true));
    $end = trim((string) get_post_meta($post_id, 'iss_end_datetime', true));
    $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
        ? iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true))
        : '';
    $entity_label = $entity_key !== '' && function_exists('iss_content_model_veranstaltung_entity_label')
        ? iss_content_model_veranstaltung_entity_label($entity_key)
        : '';
    $shape = $entity_key !== '' && function_exists('iss_content_model_veranstaltung_entity_shape')
        ? iss_content_model_veranstaltung_entity_shape($entity_key)
        : '';
    $surface = $entity_key !== '' && function_exists('iss_content_model_veranstaltung_entity_primary_surface')
        ? iss_content_model_veranstaltung_entity_primary_surface($entity_key)
        : '';
    $skin = $entity_key !== '' && function_exists('iss_content_model_veranstaltung_entity_default_skin')
        ? iss_content_model_veranstaltung_entity_default_skin($entity_key)
        : '';
    $semantic_key = iss_content_model_get_veranstaltung_semantic_key($post_id);
    $semantic_label = $semantic_key !== '' && function_exists('iss_content_model_veranstaltung_semantic_label')
        ? iss_content_model_veranstaltung_semantic_label($semantic_key)
        : '';
    $booking_enabled = get_post_meta($post_id, 'iss_booking_enabled', true);
    $booking_enabled = $booking_enabled === '' ? false : (bool) $booking_enabled;
    $booking_price_cents = (int) get_post_meta($post_id, 'iss_booking_price_cents', true);

    if ($document === null && function_exists('iss_content_model_veranstaltung_content_document')) {
        $document = iss_content_model_veranstaltung_content_document($post_id);
    }
    $document = is_array($document) ? $document : [];
    $counts = iss_content_model_count_veranstaltung_document_refs($document);

    return [
        'entity_key' => $entity_key,
        'entity_label' => $entity_label,
        'semantic_key' => $semantic_key,
        'semantic_label' => $semantic_label,
        'shape' => $shape,
        'surface' => $surface,
        'skin' => $skin,
        'start' => $start,
        'end' => $end,
        'has_place' => $selected_place_id > 0 || $location !== '',
        'place_label' => $selected_place_id > 0 ? iss_content_model_get_veranstaltung_place_title($selected_place_id) : $location,
        'has_booking' => $booking_enabled,
        'booking_label' => $booking_enabled
            ? ($booking_price_cents > 0 ? number_format($booking_price_cents / 100, 2, ',', '') . ' EUR' : __('Anfrage aktiv', 'iss-content-model'))
            : __('Nicht aktiv', 'iss-content-model'),
        'has_excerpt' => has_excerpt($post_id),
        'has_thumbnail' => has_post_thumbnail($post_id),
        'counts' => $counts,
        'has_structure' => $counts['sections'] > 0,
    ];
}

function iss_content_model_render_veranstaltung_status_strip(array $summary): void
{
    $chips = [
        [
            'label' => __('Struktur', 'iss-content-model'),
            'value' => (string) ($summary['entity_label'] ?: __('Fehlt', 'iss-content-model')),
            'complete' => (string) ($summary['entity_key'] ?? '') !== '',
        ],
        [
            'label' => __('Art', 'iss-content-model'),
            'value' => (string) ($summary['semantic_label'] ?: __('Optional', 'iss-content-model')),
            'complete' => true,
        ],
        [
            'label' => __('Datum', 'iss-content-model'),
            'value' => (string) ($summary['start'] ?: __('Fehlt', 'iss-content-model')),
            'complete' => (string) ($summary['start'] ?? '') !== '',
        ],
        [
            'label' => __('Ort', 'iss-content-model'),
            'value' => (string) ($summary['place_label'] ?: __('Fehlt', 'iss-content-model')),
            'complete' => !empty($summary['has_place']),
        ],
        [
            'label' => __('Buchung', 'iss-content-model'),
            'value' => (string) ($summary['booking_label'] ?? __('Nicht aktiv', 'iss-content-model')),
            'complete' => !empty($summary['has_booking']),
        ],
        [
            'label' => __('Inhalt', 'iss-content-model'),
            'value' => sprintf(
                /* translators: %d: number of structured sections. */
                _n('%d Abschnitt', '%d Abschnitte', (int) ($summary['counts']['sections'] ?? 0), 'iss-content-model'),
                (int) ($summary['counts']['sections'] ?? 0)
            ),
            'complete' => !empty($summary['has_structure']),
        ],
        [
            'label' => __('Beitragsbild', 'iss-content-model'),
            'value' => !empty($summary['has_thumbnail']) ? __('Gesetzt', 'iss-content-model') : __('Fehlt', 'iss-content-model'),
            'complete' => !empty($summary['has_thumbnail']),
        ],
        [
            'label' => __('Kurzbeschreibung', 'iss-content-model'),
            'value' => !empty($summary['has_excerpt']) ? __('Gesetzt', 'iss-content-model') : __('Fehlt', 'iss-content-model'),
            'complete' => !empty($summary['has_excerpt']),
        ],
    ];

    echo '<div class="iss-veranstaltung-status-strip" aria-label="' . esc_attr__('Veranstaltungsstatus', 'iss-content-model') . '">';
    foreach ($chips as $chip) {
        echo '<div class="iss-veranstaltung-status-strip__chip ' . esc_attr(!empty($chip['complete']) ? 'is-complete' : 'is-missing') . '">';
        echo '<span>' . esc_html((string) $chip['label']) . '</span>';
        echo '<strong>' . esc_html((string) $chip['value']) . '</strong>';
        echo '</div>';
    }
    echo '</div>';
}

function iss_content_model_render_veranstaltung_basis_box($post): void {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $start = (string) get_post_meta($post->ID, 'iss_start_datetime', true);
    $end = (string) get_post_meta($post->ID, 'iss_end_datetime', true);
    $location = (string) get_post_meta($post->ID, 'iss_location', true);
    $place_choices = iss_content_model_get_veranstaltung_place_choices();
    $selected_place_id = iss_content_model_get_veranstaltung_primary_place_id((int) $post->ID);
    $selected_place_title = iss_content_model_get_veranstaltung_place_title($selected_place_id);
    $location_override = $selected_place_title !== '' && $location === $selected_place_title ? '' : $location;
    $programme_enabled = get_post_meta($post->ID, 'iss_programme_enabled', true);
    $programme_enabled = $programme_enabled === '' ? false : (bool) $programme_enabled;

    echo '<div class="iss-veranstaltung-admin">';

    echo '<section class="iss-veranstaltung-admin__section">';
    echo '<div class="iss-veranstaltung-admin__section-head">';
    echo '<h3>' . esc_html__('Pflichtangaben', 'iss-content-model') . '</h3>';
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

    echo '<p class="iss-veranstaltung-admin__programme"><label><input type="checkbox" name="iss_content_model[iss_programme_enabled]" value="1" ' . checked($programme_enabled, true, false) . '> ' . esc_html__('Im Kalender / Programm anzeigen', 'iss-content-model') . '</label></p>';
    echo '</section>';

    echo '</div>';
}

function iss_content_model_render_veranstaltung_booking_box($post): void
{
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $booking_enabled = get_post_meta($post->ID, 'iss_booking_enabled', true);
    $booking_enabled = $booking_enabled === '' ? false : (bool) $booking_enabled;
    $booking_price_cents = (int) get_post_meta($post->ID, 'iss_booking_price_cents', true);
    $booking_price_display = $booking_price_cents > 0 ? number_format($booking_price_cents / 100, 2, ',', '') : '';
    $booking_cta_label = (string) get_post_meta($post->ID, 'iss_booking_cta_label', true);
    $booking_gateway_description = (string) get_post_meta($post->ID, 'iss_booking_gateway_description', true);

    echo '<div class="iss-veranstaltung-admin iss-veranstaltung-admin--booking">';
    echo '<p class="iss-veranstaltung-admin__programme"><label><input type="checkbox" name="iss_content_model[iss_booking_enabled]" value="1" ' . checked($booking_enabled, true, false) . '> ' . esc_html__('Buchung aktivieren', 'iss-content-model') . '</label></p>';
    echo '<p class="iss-veranstaltung-admin__field"><label for="iss_booking_price_display"><strong>' . esc_html__('Preis in Euro', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_booking_price_display" name="iss_booking_price_display" value="' . esc_attr($booking_price_display) . '" placeholder="12,00">';
    echo '<span class="description">' . esc_html__('Leer oder 0 für reine Anfrage ohne Betrag.', 'iss-content-model') . '</span></p>';
    echo '<p class="iss-veranstaltung-admin__field"><label for="iss_booking_cta_label"><strong>' . esc_html__('CTA-Label', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_booking_cta_label" name="iss_content_model[iss_booking_cta_label]" value="' . esc_attr($booking_cta_label) . '" placeholder="' . esc_attr__('Tickets buchen', 'iss-content-model') . '"></p>';
    echo '<p class="iss-veranstaltung-admin__field"><label for="iss_booking_gateway_description"><strong>' . esc_html__('Buchungshinweis', 'iss-content-model') . '</strong></label>';
    echo '<textarea class="widefat" rows="3" id="iss_booking_gateway_description" name="iss_content_model[iss_booking_gateway_description]">' . esc_textarea($booking_gateway_description) . '</textarea>';
    echo '<span class="description">' . esc_html__('Mollie ist vorbereitet, aber bis zur Provider-Anbindung deaktiviert; Anfragen werden lokal gespeichert.', 'iss-content-model') . '</span></p>';
    echo '</div>';
}

function iss_content_model_render_veranstaltung_type_box($post): void
{
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $post_id = (int) $post->ID;
    $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
        ? iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true))
        : '';
    $semantic_key = iss_content_model_get_veranstaltung_semantic_key($post_id);

    echo '<div class="iss-veranstaltung-admin iss-veranstaltung-admin--type">';
    echo '<section class="iss-veranstaltung-admin__section">';
    echo '<div class="iss-veranstaltung-admin__section-head">';
    echo '<h3>' . esc_html__('Struktur & Art', 'iss-content-model') . '</h3>';
    echo '<p>' . esc_html__('Über die Struktur legen Sie fest, wie der Kalender rechnet und Daten ausgibt. Die Art nutzen Sie als gezielten Filter, um genau die Inhalte anzuzeigen, die Sie gerade brauchen.', 'iss-content-model') . '</p>';
    echo '</div>';

    if (function_exists('iss_content_model_veranstaltung_entity_options')) {
        $entity_options = function_exists('iss_content_model_veranstaltung_entity_options_for_editor')
            ? iss_content_model_veranstaltung_entity_options_for_editor($entity_key)
            : iss_content_model_veranstaltung_entity_options();
        echo '<p class="iss-veranstaltung-admin__field">';
        echo '<label for="iss_veranstaltung_entity_key"><strong>' . esc_html__('Struktur', 'iss-content-model') . '</strong></label>';
        iss_content_model_render_veranstaltung_entity_select($entity_options, $entity_key);
        echo '</p>';
    }

    if (function_exists('iss_content_model_veranstaltung_semantic_options')) {
        echo '<p class="iss-veranstaltung-admin__field">';
        echo '<label for="iss_veranstaltung_semantic_key"><strong>' . esc_html__('Veranstaltungsart', 'iss-content-model') . '</strong></label>';
        iss_content_model_render_veranstaltung_select(
            'iss_veranstaltung_semantic_key',
            'iss_veranstaltung_semantic',
            iss_content_model_veranstaltung_semantic_options(),
            $semantic_key
        );
        echo '</p>';
    }

    echo '</section>';

    iss_content_model_render_veranstaltung_promotion_section($post);

    echo '</div>';
}

function iss_content_model_render_veranstaltung_promotion_section(WP_Post $post): void
{
    ob_start();
    iss_content_model_render_project_promotion_control($post);
    $control = trim((string) ob_get_clean());
    if ($control === '') {
        return;
    }

    echo '<section class="iss-veranstaltung-admin__section iss-veranstaltung-admin__section--promotion">';
    echo '<div class="iss-veranstaltung-admin__section-head">';
    echo '<h3>' . esc_html__('Inhalt promoten', 'iss-content-model') . '</h3>';
    echo '<p>' . esc_html__('Heben Sie diese Veranstaltung gezielt hervor, wenn sie gerade besonders sichtbar sein soll.', 'iss-content-model') . '</p>';
    echo '</div>';
    echo $control; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Existing metabox renderer escapes its fields.
    echo '</section>';
}

function iss_content_model_render_veranstaltung_content_box($post): void
{
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $post_id = (int) $post->ID;
    $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
        ? iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true))
        : '';
    $document = function_exists('iss_content_model_veranstaltung_content_document')
        ? iss_content_model_veranstaltung_content_document($post_id)
        : [];
    if (!$document && function_exists('iss_content_model_veranstaltung_empty_content_document')) {
        $document = iss_content_model_veranstaltung_empty_content_document($entity_key);
    }
    if (is_array($document) && $entity_key !== '') {
        $document['entity_key'] = $entity_key;
    }

    $gestures = function_exists('iss_content_model_veranstaltung_content_gestures_for_entity')
        ? iss_content_model_veranstaltung_content_gestures_for_entity($entity_key)
        : [];
    if (!$gestures && function_exists('iss_content_model_veranstaltung_content_gestures')) {
        $gestures = iss_content_model_veranstaltung_content_gestures();
    }

    $entity_label = $entity_key !== '' && function_exists('iss_content_model_veranstaltung_entity_label')
        ? iss_content_model_veranstaltung_entity_label($entity_key)
        : '';
    $semantic_label = iss_content_model_get_veranstaltung_semantic_label($post_id);
    $badge_label = $entity_label !== '' ? $entity_label : __('Struktur nicht gesetzt', 'iss-content-model');
    if ($semantic_label !== '') {
        $badge_label .= ' / ' . $semantic_label;
    }
    $summary = iss_content_model_get_veranstaltung_editor_summary($post, $document);
    $meta_key = function_exists('iss_content_model_veranstaltung_content_meta_key')
        ? iss_content_model_veranstaltung_content_meta_key()
        : '_iss_content_json';
    $encoded_document = (string) wp_json_encode($document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $media_previews = [];
    $dynamic_previews = [];
    $steuerung = null;
    if (class_exists('Industriesalon_Steuerung') && method_exists('Industriesalon_Steuerung', 'instance')) {
        $steuerung = Industriesalon_Steuerung::instance();
    }
    foreach ((array) ($document['sections'] ?? []) as $section) {
        if (!is_array($section)) {
            continue;
        }
        foreach ((array) ($section['media_refs'] ?? []) as $reference) {
            if (!is_array($reference) || (string) ($reference['source'] ?? '') !== 'wp-media') {
                continue;
            }
            $attachment_id = absint($reference['id'] ?? 0);
            if ($attachment_id <= 0) {
                continue;
            }
            $metadata = wp_get_attachment_metadata($attachment_id);
            $media_previews[(string) $attachment_id] = [
                'label' => (string) get_the_title($attachment_id),
                'thumbnail' => (string) wp_get_attachment_image_url($attachment_id, 'medium'),
                'width' => is_array($metadata) ? (string) absint($metadata['width'] ?? 0) : '',
                'height' => is_array($metadata) ? (string) absint($metadata['height'] ?? 0) : '',
            ];
        }
        foreach ((array) ($section['dynamic_refs'] ?? []) as $reference) {
            if (
                !is_array($reference)
                || (string) ($reference['source'] ?? '') !== 'industriesalon-steuerung'
                || (string) ($reference['kind'] ?? '') !== 'control_field'
            ) {
                continue;
            }
            $key = trim(sanitize_text_field((string) ($reference['key'] ?? '')));
            if ($key === '') {
                continue;
            }
            $value = '';
            if (is_object($steuerung) && method_exists($steuerung, 'get_field_value')) {
                $value = (string) $steuerung->get_field_value($key, '');
            }
            $dynamic_previews[$key] = [
                'label' => trim(sanitize_text_field((string) ($reference['label'] ?? ''))),
                'value' => $value,
            ];
        }
    }

    echo '<div class="iss-veranstaltung-content-editor" data-entity-key="' . esc_attr($entity_key) . '" data-document="' . esc_attr($encoded_document) . '" data-gestures="' . esc_attr((string) wp_json_encode($gestures, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '" data-media-previews="' . esc_attr((string) wp_json_encode($media_previews, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '" data-dynamic-previews="' . esc_attr((string) wp_json_encode($dynamic_previews, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) . '">';
    echo '<input type="hidden" class="iss-veranstaltung-content-editor__field" name="iss_content_model[' . esc_attr($meta_key) . ']" value="' . esc_attr($encoded_document) . '">';
    echo '<div class="iss-veranstaltung-content-editor__head">';
    echo '<div>';
    echo '<h3>' . esc_html__('Strukturierter Inhalt', 'iss-content-model') . '</h3>';
    echo '<p>' . esc_html__('Ihr Hauptformat für die Anzeige öffentlicher Veranstaltungen.', 'iss-content-model') . '</p>';
    echo '</div>';
    echo '<span class="iss-veranstaltung-content-editor__badge">' . esc_html($badge_label) . '</span>';
    echo '</div>';
    iss_content_model_render_veranstaltung_status_strip($summary);
    echo '<div class="iss-veranstaltung-content-editor__root"></div>';
    echo '</div>';
}

function iss_content_model_render_veranstaltung_status_box($post): void
{
    $post_id = (int) $post->ID;
    $summary = iss_content_model_get_veranstaltung_editor_summary($post);
    $entity_key = (string) ($summary['entity_key'] ?? '');
    $checks = [
        __('Titel', 'iss-content-model') => trim((string) get_the_title($post_id)) !== '',
        __('Struktur', 'iss-content-model') => $entity_key !== '',
    ];

    if ($entity_key !== '' && function_exists('iss_content_model_veranstaltung_required_facts_for_entity') && function_exists('iss_content_model_veranstaltung_required_fact_labels') && function_exists('iss_content_model_veranstaltung_fact_value')) {
        $fact_labels = iss_content_model_veranstaltung_required_fact_labels();
        foreach (iss_content_model_veranstaltung_required_facts_for_entity($entity_key) as $fact) {
            $checks[(string) ($fact_labels[$fact] ?? $fact)] = iss_content_model_veranstaltung_fact_value($post, $fact) !== '';
        }
    } else {
        $checks[__('Beginn', 'iss-content-model')] = trim((string) get_post_meta($post_id, 'iss_start_datetime', true)) !== '';
    }

    $checks[__('Ort', 'iss-content-model')] = !empty($summary['has_place']);
    $checks[__('Kurzbeschreibung', 'iss-content-model')] = !empty($summary['has_excerpt']);
    $checks[__('Struktur', 'iss-content-model')] = !empty($summary['has_structure']);
    $checks[__('Beitragsbild oder Galerie', 'iss-content-model')] = !empty($summary['has_thumbnail']) || (int) ($summary['counts']['media_refs'] ?? 0) > 0;
    $missing_checks = array_filter($checks, static function ($complete): bool {
        return !$complete;
    });

    echo '<div class="iss-veranstaltung-status">';
    if (!$missing_checks) {
        echo '<p class="iss-veranstaltung-status__ready">' . esc_html__('Alles bereit für die Veröffentlichung.', 'iss-content-model') . '</p>';
        echo '</div>';
        return;
    }

    echo '<p class="iss-veranstaltung-status__summary">' . esc_html__('Noch offen:', 'iss-content-model') . '</p>';
    echo '<ul class="iss-veranstaltung-status__checks iss-veranstaltung-status__checks--compact">';
    foreach (array_keys($missing_checks) as $label) {
        echo '<li class="is-missing">';
        echo '<span aria-hidden="true">' . esc_html('!') . '</span>';
        echo esc_html((string) $label);
        echo '</li>';
    }
    echo '</ul>';
    echo '</div>';
}

function iss_content_model_render_ausstellung_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $start = (string) get_post_meta($post->ID, 'iss_start_date', true);
    $end = (string) get_post_meta($post->ID, 'iss_end_date', true);
    $selected_type = iss_content_model_get_selected_ausstellung_type((int) $post->ID);
    $overview_enabled = get_post_meta($post->ID, 'iss_public_overview_enabled', true);
    $overview_enabled = $overview_enabled === '' ? false : (bool) $overview_enabled;
    $programme_enabled = get_post_meta($post->ID, 'iss_programme_enabled', true);
    $programme_enabled = $programme_enabled === '' ? false : (bool) $programme_enabled;

    echo '<h3>' . esc_html__('Pflichtangaben', 'iss-content-model') . '</h3>';
    echo '<p><label for="iss_start_date"><strong>' . esc_html__('Startdatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_start_date" name="iss_content_model[iss_start_date]" value="' . esc_attr($start) . '"></p>';

    echo '<p><label for="iss_end_date"><strong>' . esc_html__('Enddatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_end_date" name="iss_content_model[iss_end_date]" value="' . esc_attr($end) . '"></p>';

    echo '<p class="description">' . esc_html__('Sammlungsbereich und Themen werden in den Taxonomie-Boxen verwaltet. Orte werden ueber "Ort hinzufuegen" verbunden.', 'iss-content-model') . '</p>';

    echo '<fieldset><legend><strong>' . esc_html__('Ausstellungsart', 'iss-content-model') . '</strong></legend>';
    foreach (iss_content_model_get_ausstellung_type_options() as $slug => $label) {
        echo '<p><label><input type="radio" name="iss_content_model[ausstellung_typ]" value="' . esc_attr($slug) . '" ' . checked($selected_type, (string) $slug, false) . '> ' . esc_html((string) $label) . '</label></p>';
    }
    echo '</fieldset>';

    echo '<p><label><input type="checkbox" name="iss_content_model[iss_public_overview_enabled]" value="1" ' . checked($overview_enabled, true, false) . '> ' . esc_html__('In Ausstellungsübersichten anzeigen', 'iss-content-model') . '</label></p>';
    echo '<p><label><input type="checkbox" name="iss_content_model[iss_programme_enabled]" value="1" ' . checked($programme_enabled, true, false) . '> ' . esc_html__('Im Kalender / Programm anzeigen', 'iss-content-model') . '</label></p>';
    echo '<p class="description">' . esc_html__('Dauer- und digitale Ausstellungen erscheinen nur dann im Kalender / Programm, wenn diese Programmfreigabe aktiv ist.', 'iss-content-model') . '</p>';
}

function iss_content_model_render_project_promotion_control(WP_Post $post): void
{
    if (current_user_can('manage_options')) {
        return;
    }

    if (
        !function_exists('iss_graph_current_user_can_edit_editorial_signals')
        || !function_exists('iss_graph_get_related_promotion_signal')
        || !function_exists('iss_graph_related_promotion_signal_is_active')
        || !function_exists('iss_graph_is_related_promotion_post_type')
        || !iss_graph_is_related_promotion_post_type((string) $post->post_type)
        || !iss_graph_current_user_can_edit_editorial_signals((int) $post->ID)
    ) {
        return;
    }

    $signal = iss_graph_get_related_promotion_signal((int) $post->ID, false);
    $is_active = iss_graph_related_promotion_signal_is_active($signal);
    $reason = $is_active ? (string) ($signal['reason'] ?? '') : '';
    $expires_at = $is_active && isset($signal['expires_at']) && $signal['expires_at'] !== null
        ? substr((string) $signal['expires_at'], 0, 10)
        : '';

    wp_nonce_field('iss_graph_save_related_promotion', 'iss_graph_related_promotion_nonce');

    echo '<hr>';
    echo '<p><label><input type="checkbox" name="iss_graph_related_promotion[enabled]" value="1" ' . checked($is_active, true, false) . '> ' . esc_html__('Inhalt promoten', 'iss-content-model') . '</label></p>';
    echo '<input type="hidden" name="iss_graph_related_promotion[reason]" value="' . esc_attr($reason) . '">';
    echo '<input type="hidden" name="iss_graph_related_promotion[expires_at]" value="' . esc_attr($expires_at) . '">';
    echo '<p class="description">' . esc_html__('Mit dieser Auswahl rückt der Post nach vorne.', 'iss-content-model') . '</p>';
}

function iss_content_model_render_projekt_box($post) {
    wp_nonce_field('iss_content_model_save_meta', 'iss_content_model_meta_nonce');

    $start = (string) get_post_meta($post->ID, 'iss_start_date', true);
    $end = (string) get_post_meta($post->ID, 'iss_end_date', true);
    $period_label = (string) get_post_meta($post->ID, 'iss_period_label', true);
    $programme_enabled = get_post_meta($post->ID, 'iss_programme_enabled', true);
    $programme_enabled = $programme_enabled === '' ? false : (bool) $programme_enabled;

    echo '<p><label for="iss_start_date"><strong>' . esc_html__('Startdatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_start_date" name="iss_content_model[iss_start_date]" value="' . esc_attr($start) . '"></p>';

    echo '<p><label for="iss_end_date"><strong>' . esc_html__('Enddatum', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="date" id="iss_end_date" name="iss_content_model[iss_end_date]" value="' . esc_attr($end) . '"></p>';

    echo '<p><label for="iss_period_label"><strong>' . esc_html__('Zeitraum-Label', 'iss-content-model') . '</strong></label>';
    echo '<input class="widefat" type="text" id="iss_period_label" name="iss_content_model[iss_period_label]" value="' . esc_attr($period_label) . '" placeholder="' . esc_attr__('seit 2023 / laufend / 2024', 'iss-content-model') . '"></p>';
    echo '<p class="description">' . esc_html__('Das Label ist nur Anzeigetext. Fuer Kalender und Timeline zaehlen Start-/Enddatum oder ersatzweise das Erstellungsdatum.', 'iss-content-model') . '</p>';

    echo '<p><label><input type="checkbox" name="iss_content_model[iss_programme_enabled]" value="1" ' . checked($programme_enabled, true, false) . '> ' . esc_html__('Im Kalender / Programm anzeigen', 'iss-content-model') . '</label></p>';

    iss_content_model_render_project_promotion_control($post);

    if (current_user_can('manage_options')) {
        $front_page_order = (int) $post->menu_order;

        echo '<p><label for="iss_project_front_page_order"><strong>' . esc_html__('Startseiten-Reihenfolge', 'iss-content-model') . '</strong></label>';
        echo '<input class="widefat" type="number" step="1" id="iss_project_front_page_order" name="iss_content_model[menu_order]" value="' . esc_attr((string) $front_page_order) . '"></p>';
        echo '<p class="description">' . esc_html__('Technischer Reparaturwert. Redakteur:innen sortieren Projekte per Drag & Drop in der Projektliste.', 'iss-content-model') . '</p>';
    }
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

function iss_content_model_save_veranstaltung_entity_meta(int $post_id, array $raw): void
{
    if (!array_key_exists('_iss_entity_key', $raw) || !function_exists('iss_content_model_sanitize_veranstaltung_entity_key')) {
        return;
    }

    $entity_key = iss_content_model_sanitize_veranstaltung_entity_key((string) $raw['_iss_entity_key']);
    if ($entity_key === '') {
        delete_post_meta($post_id, '_iss_entity_key');
        return;
    }

    update_post_meta($post_id, '_iss_entity_key', $entity_key);
}

function iss_content_model_save_veranstaltung_semantic_terms(int $post_id, array $raw): void
{
    if (
        !array_key_exists('iss_veranstaltung_semantic', $raw)
        || !taxonomy_exists(ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY)
        || !function_exists('iss_content_model_sanitize_veranstaltung_semantic_key')
    ) {
        return;
    }

    $semantic_key = iss_content_model_sanitize_veranstaltung_semantic_key((string) $raw['iss_veranstaltung_semantic']);
    if ($semantic_key === '') {
        wp_set_object_terms($post_id, [], ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY, false);
        return;
    }

    $label = function_exists('iss_content_model_veranstaltung_semantic_label')
        ? iss_content_model_veranstaltung_semantic_label($semantic_key)
        : '';
    if ($label !== '' && !term_exists($semantic_key, ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY)) {
        wp_insert_term($label, ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY, ['slug' => $semantic_key]);
    }

    wp_set_object_terms($post_id, [$semantic_key], ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY, false);
}

function iss_content_model_save_veranstaltung_content_meta(int $post_id, array $raw): void
{
    if (!function_exists('iss_content_model_veranstaltung_content_meta_key') || !function_exists('iss_content_model_sanitize_veranstaltung_content_json')) {
        return;
    }

    $meta_key = iss_content_model_veranstaltung_content_meta_key();
    if (!array_key_exists($meta_key, $raw)) {
        return;
    }

    $raw_document = (string) $raw[$meta_key];
    $decoded = json_decode($raw_document, true);
    if (is_array($decoded) && array_key_exists('_iss_entity_key', $raw) && function_exists('iss_content_model_sanitize_veranstaltung_entity_key')) {
        $decoded['entity_key'] = iss_content_model_sanitize_veranstaltung_entity_key((string) $raw['_iss_entity_key']);
        $raw_document = (string) wp_json_encode($decoded, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    $sanitized = iss_content_model_sanitize_veranstaltung_content_json($raw_document);
    if ($sanitized === '') {
        delete_post_meta($post_id, $meta_key);
        return;
    }

    update_post_meta($post_id, $meta_key, wp_slash($sanitized));
}

function iss_content_model_parse_price_to_cents($value): int
{
    $value = trim((string) $value);
    if ($value === '') {
        return 0;
    }

    $value = str_replace(["\xc2\xa0", ' '], '', $value);
    $value = str_replace(',', '.', $value);
    if (!is_numeric($value)) {
        return 0;
    }

    return max(0, (int) round(((float) $value) * 100));
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
        iss_content_model_save_veranstaltung_entity_meta($post_id, $raw);
        iss_content_model_save_veranstaltung_semantic_terms($post_id, $raw);
        iss_content_model_save_veranstaltung_content_meta($post_id, $raw);

        unset($raw['_iss_entity_key'], $raw['_iss_content_json'], $raw['iss_veranstaltung_semantic']);

        $manual_location = trim((string) ($raw['iss_location'] ?? ''));
        if ($selected_place_id > 0 && $manual_location === '') {
            $raw['iss_location'] = iss_content_model_get_veranstaltung_place_title($selected_place_id);
        }

        $raw['iss_booking_price_cents'] = isset($_POST['iss_booking_price_display'])
            ? iss_content_model_parse_price_to_cents(wp_unslash((string) $_POST['iss_booking_price_display']))
            : 0;
    }

    if ($post_type === ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE && array_key_exists('ausstellung_typ', $raw)) {
        iss_content_model_save_ausstellung_type($post_id, (string) $raw['ausstellung_typ']);
        unset($raw['ausstellung_typ']);
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

    if ($post_type === ISS_CONTENT_MODEL_PROJEKT_POST_TYPE && array_key_exists('menu_order', $raw) && current_user_can('manage_options')) {
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

function iss_content_model_project_order_query_args(): array
{
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin list state for enabling the reorder UI.
    $query_args = wp_unslash($_GET);

    return is_array($query_args) ? $query_args : [];
}

function iss_content_model_get_ordered_project_ids(): array
{
    $project_ids = get_posts([
        'post_type' => ISS_CONTENT_MODEL_PROJEKT_POST_TYPE,
        'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
        'posts_per_page' => -1,
        'fields' => 'ids',
        'orderby' => [
            'menu_order' => 'ASC',
            'title' => 'ASC',
            'ID' => 'ASC',
        ],
        'suppress_filters' => true,
    ]);

    return array_values(array_filter(array_map('absint', $project_ids)));
}

function iss_content_model_user_can_reorder_projects(): bool
{
    static $can_reorder = null;

    if ($can_reorder !== null) {
        return $can_reorder;
    }

    $post_type_object = get_post_type_object(ISS_CONTENT_MODEL_PROJEKT_POST_TYPE);
    $edit_posts_cap = $post_type_object && isset($post_type_object->cap->edit_posts)
        ? (string) $post_type_object->cap->edit_posts
        : 'edit_posts';

    if (!current_user_can($edit_posts_cap)) {
        $can_reorder = false;
        return $can_reorder;
    }

    foreach (iss_content_model_get_ordered_project_ids() as $project_id) {
        if (!current_user_can('edit_post', $project_id)) {
            $can_reorder = false;
            return $can_reorder;
        }
    }

    $can_reorder = true;
    return $can_reorder;
}

function iss_content_model_project_order_request_has_modifiers(): bool
{
    $query_args = iss_content_model_project_order_query_args();

    foreach (['s', 'm', 'cat', 'author', 'author_name'] as $key) {
        if (trim((string) ($query_args[$key] ?? '')) !== '') {
            return true;
        }
    }

    $post_status = sanitize_key((string) ($query_args['post_status'] ?? ''));
    if ($post_status !== '' && $post_status !== 'all') {
        return true;
    }

    $paged = absint($query_args['paged'] ?? 0);
    if ($paged > 1) {
        return true;
    }

    $orderby = sanitize_key((string) ($query_args['orderby'] ?? ''));
    if ($orderby !== '' && !in_array($orderby, ['menu_order', 'iss_project_front_page_order'], true)) {
        return true;
    }

    $order = strtolower(sanitize_key((string) ($query_args['order'] ?? '')));
    if ($order !== '' && $order !== 'asc') {
        return true;
    }

    foreach (get_object_taxonomies(ISS_CONTENT_MODEL_PROJEKT_POST_TYPE, 'objects') as $taxonomy) {
        if (!$taxonomy instanceof WP_Taxonomy) {
            continue;
        }

        $query_var = is_string($taxonomy->query_var) && $taxonomy->query_var !== ''
            ? $taxonomy->query_var
            : $taxonomy->name;
        $value = trim((string) ($query_args[$query_var] ?? ''));
        if ($value !== '' && $value !== '0') {
            return true;
        }
    }

    return false;
}

function iss_content_model_project_order_list_is_reorderable_request(): bool
{
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'edit' || $screen->post_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return false;
    }

    return iss_content_model_user_can_reorder_projects()
        && !iss_content_model_project_order_request_has_modifiers();
}

function iss_content_model_update_project_menu_order(array $ordered_post_ids)
{
    $ordered_post_ids = array_values(array_unique(array_filter(array_map('absint', $ordered_post_ids))));
    $expected_post_ids = iss_content_model_get_ordered_project_ids();

    if (count($ordered_post_ids) !== count($expected_post_ids) || array_diff($ordered_post_ids, $expected_post_ids) || array_diff($expected_post_ids, $ordered_post_ids)) {
        return new WP_Error(
            'iss_content_project_order_partial_set',
            __('Bitte laden Sie die ungefilterte Projektliste neu und sortieren Sie dann erneut.', 'iss-content-model')
        );
    }

    $orders = [];
    foreach ($ordered_post_ids as $index => $post_id) {
        if (get_post_type($post_id) !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE || !current_user_can('edit_post', $post_id)) {
            return new WP_Error(
                'iss_content_project_order_forbidden_post',
                __('Mindestens ein Projekt darf von diesem Konto nicht sortiert werden.', 'iss-content-model')
            );
        }

        $menu_order = ($index + 1) * 10;
        $orders[$post_id] = $menu_order;

        if ((int) get_post_field('menu_order', $post_id) === $menu_order) {
            continue;
        }

        $result = wp_update_post([
            'ID' => $post_id,
            'menu_order' => $menu_order,
        ], true);

        if (is_wp_error($result)) {
            return $result;
        }
    }

    return $orders;
}

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

    $menu_order = (int) get_post_field('menu_order', $post_id);
    if (iss_content_model_project_order_list_is_reorderable_request() && current_user_can('edit_post', $post_id)) {
        printf(
            '<button type="button" class="button-link iss-project-order-handle" data-post-id="%d" aria-label="%s"><span class="dashicons dashicons-menu" aria-hidden="true"></span><span class="screen-reader-text">%s</span></button>',
            absint($post_id),
            esc_attr(sprintf(__('Projekt "%s" in der Liste verschieben', 'iss-content-model'), get_the_title($post_id))),
            esc_html__('Projekt verschieben', 'iss-content-model')
        );

        if (current_user_can('manage_options')) {
            echo ' <span class="iss-project-order-value">' . esc_html((string) $menu_order) . '</span>';
        }
        return;
    }

    if (current_user_can('manage_options')) {
        echo esc_html((string) $menu_order);
        return;
    }

    echo '<span aria-hidden="true">-</span><span class="screen-reader-text">' . esc_html__('Reihenfolge in der ungefilterten Projektliste bearbeiten.', 'iss-content-model') . '</span>';
}
add_action('manage_' . ISS_CONTENT_MODEL_PROJEKT_POST_TYPE . '_posts_custom_column', 'iss_content_model_render_project_order_column', 10, 2);

function iss_content_model_make_project_order_column_sortable(array $columns): array
{
    $columns['iss_project_front_page_order'] = 'menu_order';
    return $columns;
}
add_filter('manage_edit-' . ISS_CONTENT_MODEL_PROJEKT_POST_TYPE . '_sortable_columns', 'iss_content_model_make_project_order_column_sortable');

function iss_content_model_order_project_admin_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    if ($query->get('post_type') !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return;
    }

    $orderby = (string) $query->get('orderby');
    if ($orderby === '' || $orderby === 'menu_order' || $orderby === 'iss_project_front_page_order') {
        $query->set('orderby', [
            'menu_order' => 'ASC',
            'title' => 'ASC',
            'ID' => 'ASC',
        ]);
        $query->set('order', 'ASC');
    }

    if (iss_content_model_user_can_reorder_projects() && !iss_content_model_project_order_request_has_modifiers()) {
        $query->set('posts_per_page', -1);
    }
}
add_action('pre_get_posts', 'iss_content_model_order_project_admin_query');

function iss_content_model_render_project_order_admin_notice(): void
{
    if (iss_content_model_project_order_list_is_reorderable_request()) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->base !== 'edit' || $screen->post_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE || !iss_content_model_user_can_reorder_projects()) {
        return;
    }

    echo '<div class="notice notice-info"><p>' . esc_html__('Projekt-Reihenfolge per Drag & Drop ist in der ungefilterten Reihenfolge-Ansicht aktiv.', 'iss-content-model') . '</p></div>';
}
add_action('admin_notices', 'iss_content_model_render_project_order_admin_notice');

function iss_content_model_enqueue_project_order_assets(string $hook): void
{
    if ($hook !== 'edit.php') {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen || $screen->post_type !== ISS_CONTENT_MODEL_PROJEKT_POST_TYPE) {
        return;
    }

    $style_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-project-order.css';
    if (file_exists($style_path)) {
        wp_enqueue_style(
            'iss-content-model-project-order',
            plugins_url('../assets/admin-project-order.css', __FILE__),
            [],
            (string) filemtime($style_path)
        );
    }

    $script_path = ISS_CONTENT_MODEL_PATH . 'assets/admin-project-order.js';
    if (!file_exists($script_path)) {
        return;
    }

    wp_enqueue_script(
        'iss-content-model-project-order',
        plugins_url('../assets/admin-project-order.js', __FILE__),
        ['jquery', 'jquery-ui-sortable'],
        (string) filemtime($script_path),
        true
    );
    wp_localize_script(
        'iss-content-model-project-order',
        'issContentProjectOrder',
        [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'enabled' => iss_content_model_project_order_list_is_reorderable_request(),
            'nonce' => wp_create_nonce('iss_content_project_order'),
            'strings' => [
                'saving' => __('Reihenfolge wird gespeichert ...', 'iss-content-model'),
                'saved' => __('Projekt-Reihenfolge gespeichert.', 'iss-content-model'),
                'error' => __('Projekt-Reihenfolge konnte nicht gespeichert werden.', 'iss-content-model'),
            ],
        ]
    );
}
add_action('admin_enqueue_scripts', 'iss_content_model_enqueue_project_order_assets');

function iss_content_model_handle_project_order_ajax(): void
{
    check_ajax_referer('iss_content_project_order', 'nonce');

    if (!iss_content_model_user_can_reorder_projects()) {
        wp_send_json_error([
            'message' => __('Dieses Konto darf die Projekt-Reihenfolge nicht ändern.', 'iss-content-model'),
        ], 403);
    }

    $post_ids = isset($_POST['post_ids']) && is_array($_POST['post_ids'])
        ? wp_unslash($_POST['post_ids'])
        : [];
    $orders = iss_content_model_update_project_menu_order((array) $post_ids);

    if (is_wp_error($orders)) {
        wp_send_json_error([
            'message' => $orders->get_error_message(),
        ], 400);
    }

    wp_send_json_success([
        'message' => __('Projekt-Reihenfolge gespeichert.', 'iss-content-model'),
        'orders' => $orders,
    ]);
}
add_action('wp_ajax_iss_content_project_reorder', 'iss_content_model_handle_project_order_ajax');

function iss_content_model_add_veranstaltung_entity_column(array $columns): array
{
    $ordered_columns = [];

    foreach ($columns as $key => $label) {
        $ordered_columns[$key] = $label;

        if ($key === 'title') {
            $ordered_columns['iss_entity_key'] = __('Struktur', 'iss-content-model');
            $ordered_columns['iss_veranstaltung_semantic'] = __('Art', 'iss-content-model');
        }
    }

    return $ordered_columns;
}
add_filter('manage_' . ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE . '_posts_columns', 'iss_content_model_add_veranstaltung_entity_column');

function iss_content_model_render_veranstaltung_entity_column(string $column, int $post_id): void
{
    if ($column === 'iss_entity_key') {
        $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
            ? iss_content_model_sanitize_veranstaltung_entity_key((string) get_post_meta($post_id, '_iss_entity_key', true))
            : '';
        if ($entity_key === '') {
            echo '<span aria-hidden="true">-</span><span class="screen-reader-text">' . esc_html__('Nicht gesetzt', 'iss-content-model') . '</span>';
            return;
        }

        $label = function_exists('iss_content_model_veranstaltung_entity_label')
            ? iss_content_model_veranstaltung_entity_label($entity_key)
            : '';

        echo esc_html($label !== '' ? $label : $entity_key);
        return;
    }

    if ($column === 'iss_veranstaltung_semantic') {
        $label = iss_content_model_get_veranstaltung_semantic_label($post_id);
        if ($label === '') {
            echo '<span aria-hidden="true">-</span><span class="screen-reader-text">' . esc_html__('Nicht gesetzt', 'iss-content-model') . '</span>';
            return;
        }

        echo esc_html($label);
    }
}
add_action('manage_' . ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE . '_posts_custom_column', 'iss_content_model_render_veranstaltung_entity_column', 10, 2);

function iss_content_model_render_veranstaltung_entity_filter(string $post_type): void
{
    if ($post_type !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE || !function_exists('iss_content_model_veranstaltung_entity_options')) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin list filter reads query state only.
    $selected = sanitize_text_field((string) wp_unslash($_GET['iss_entity_key_filter'] ?? ''));
    echo '<label class="screen-reader-text" for="iss_entity_key_filter">' . esc_html__('Nach Struktur filtern', 'iss-content-model') . '</label>';
    echo '<select id="iss_entity_key_filter" name="iss_entity_key_filter">';
    echo '<option value="">' . esc_html__('Alle Strukturen', 'iss-content-model') . '</option>';
    echo '<option value="__unset"' . selected($selected, '__unset', false) . '>' . esc_html__('Struktur nicht gesetzt', 'iss-content-model') . '</option>';

    foreach (iss_content_model_veranstaltung_entity_options() as $entity_key => $option) {
        if ($entity_key === '') {
            continue;
        }

        echo '<option value="' . esc_attr((string) $entity_key) . '"' . selected($selected, (string) $entity_key, false) . '>' . esc_html((string) ($option['label'] ?? $entity_key)) . '</option>';
    }

    echo '</select>';

    if (function_exists('iss_content_model_veranstaltung_semantic_options')) {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin list filter reads query state only.
        $semantic_selected = sanitize_text_field((string) wp_unslash($_GET['iss_veranstaltung_semantic_filter'] ?? ''));
        echo '<label class="screen-reader-text" for="iss_veranstaltung_semantic_filter">' . esc_html__('Nach Art filtern', 'iss-content-model') . '</label>';
        echo '<select id="iss_veranstaltung_semantic_filter" name="iss_veranstaltung_semantic_filter">';
        echo '<option value="">' . esc_html__('Alle Arten', 'iss-content-model') . '</option>';
        echo '<option value="__unset"' . selected($semantic_selected, '__unset', false) . '>' . esc_html__('Art nicht gesetzt', 'iss-content-model') . '</option>';
        foreach (iss_content_model_veranstaltung_semantic_options() as $semantic_key => $option) {
            if ($semantic_key === '') {
                continue;
            }

            echo '<option value="' . esc_attr((string) $semantic_key) . '"' . selected($semantic_selected, (string) $semantic_key, false) . '>' . esc_html((string) ($option['label'] ?? $semantic_key)) . '</option>';
        }
        echo '</select>';
    }
}
add_action('restrict_manage_posts', 'iss_content_model_render_veranstaltung_entity_filter');

function iss_content_model_filter_veranstaltung_admin_query(WP_Query $query): void
{
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }

    $post_type = $query->get('post_type');
    if ($post_type !== ISS_CONTENT_MODEL_VERANSTALTUNG_POST_TYPE) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin list filter reads query state only.
    $selected = sanitize_text_field((string) wp_unslash($_GET['iss_entity_key_filter'] ?? ''));

    $meta_query = $query->get('meta_query');
    $meta_query = is_array($meta_query) ? $meta_query : [];
    if ($selected === '__unset') {
        $meta_query[] = [
            'relation' => 'OR',
            [
                'key' => '_iss_entity_key',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_iss_entity_key',
                'value' => '',
                'compare' => '=',
            ],
        ];
        $query->set('meta_query', $meta_query);
    } elseif ($selected !== '') {
        $entity_key = function_exists('iss_content_model_sanitize_veranstaltung_entity_key')
            ? iss_content_model_sanitize_veranstaltung_entity_key($selected)
            : '';
        if ($entity_key !== '') {
            $storage_keys = function_exists('iss_content_model_veranstaltung_entity_storage_keys_for_query')
                ? iss_content_model_veranstaltung_entity_storage_keys_for_query([$entity_key])
                : [$entity_key];
            $meta_query[] = [
                'key' => '_iss_entity_key',
                'value' => $storage_keys,
                'compare' => 'IN',
            ];
            $query->set('meta_query', $meta_query);
        }
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin list filter reads query state only.
    $semantic_selected = sanitize_text_field((string) wp_unslash($_GET['iss_veranstaltung_semantic_filter'] ?? ''));
    if ($semantic_selected === '') {
        return;
    }

    $tax_query = $query->get('tax_query');
    $tax_query = is_array($tax_query) ? $tax_query : [];
    if ($semantic_selected === '__unset') {
        $tax_query[] = [
            'taxonomy' => ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY,
            'operator' => 'NOT EXISTS',
        ];
        $query->set('tax_query', $tax_query);
        return;
    }

    $semantic_key = function_exists('iss_content_model_sanitize_veranstaltung_semantic_key')
        ? iss_content_model_sanitize_veranstaltung_semantic_key($semantic_selected)
        : '';
    if ($semantic_key === '') {
        return;
    }

    $tax_query[] = [
        'taxonomy' => ISS_CONTENT_MODEL_VERANSTALTUNG_SEMANTIC_TAXONOMY,
        'field' => 'slug',
        'terms' => [$semantic_key],
    ];
    $query->set('tax_query', $tax_query);
}
add_action('pre_get_posts', 'iss_content_model_filter_veranstaltung_admin_query');
