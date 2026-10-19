<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_editorial_gallery_section(): array
{
    return [
        'label' => __('Bildergalerie', 'iss-content-model'),
        'description' => __('Freigegebene Gästefotos und Medienbilder. Flexibel dargestellt als Raster, Bilderwand, Reihe oder Fokus-Ansicht.', 'iss-content-model'),
        'supports' => ['media_refs', 'object_refs'],
    ];
}

function iss_content_model_editorial_material_section(): array
{
    return [
        'label' => __('Begleitende Dateien', 'iss-content-model'),
        'editor' => ['media_kind' => 'files', 'media_hide_images' => true, 'media_hint' => __('Material verwendet Dateien. Bilder bitte über Galerie einsetzen.', 'iss-content-model')],
        'description' => __('Zum Herunterladen. Wichtig: Bilder und Archiv-Objekte gehören stattdessen in die Galerie.', 'iss-content-model'),
        'supports' => ['anchor', 'media_refs', 'links'],
    ];
}

function iss_content_model_landing_page_allowed_slugs(): array
{
    return [
        'about',
        'verein',
        'salon-vermietung',
        'sammlungen',
        'fuehrungen',
        'schoneweide',
    ];
}

function iss_content_model_landing_page_is_eligible($post): bool
{
    if (is_numeric($post)) {
        $post = get_post((int) $post);
    }

    if (!$post instanceof WP_Post || $post->post_type !== 'page') {
        return false;
    }

    $front_page_id = absint(get_option('page_on_front'));
    if ($front_page_id > 0 && (int) $post->ID === $front_page_id) {
        return true;
    }

    return in_array((string) $post->post_name, iss_content_model_landing_page_allowed_slugs(), true);
}

function iss_content_model_place_media_reference(int $attachment_id): array
{
    $attachment = $attachment_id > 0 ? get_post($attachment_id) : null;
    if (!$attachment instanceof WP_Post || $attachment->post_type !== 'attachment') {
        return [];
    }

    return [
        'kind' => 'media',
        'source' => 'wp-media',
        'id' => (string) $attachment_id,
        'label' => (string) get_the_title($attachment),
    ];
}

function iss_content_model_build_place_editorial_candidate(WP_Post $post): array
{
    $sections = [];
    // Preserve the published narrative; the manual excerpt remains a separate teaser.
    $intro = trim(wp_kses_post(do_blocks((string) $post->post_content)));
    if (trim(wp_strip_all_tags($intro)) === trim((string) $post->post_excerpt)) {
        $intro = '';
    }
    if (trim(wp_strip_all_tags($intro)) === '') {
        $intro = wpautop(esc_html(trim((string) get_post_meta((int) $post->ID, 'history_long', true))));
    }
    if (trim(wp_strip_all_tags($intro)) === '') {
        $intro = trim((string) get_post_meta((int) $post->ID, 'history_short', true));
    }
    if (trim(wp_strip_all_tags($intro)) === trim((string) $post->post_excerpt)) {
        $intro = '';
    }
    $current_use = trim((string) get_post_meta((int) $post->ID, 'current_use', true));

    if ($intro !== '') {
        $sections[] = [
            'type' => 'intro',
            'title' => __('Warum dieser Ort zählt', 'iss-content-model'),
            'body' => $intro,
        ];
    }

    $epochs = function_exists('iss_register_get_epoch_service')
        ? iss_register_get_epoch_service()->get_epochs_for_place((int) $post->ID)
        : [];

    foreach ($epochs as $epoch) {
        $media_refs = [];
        foreach ((array) ($epoch['media_ids'] ?? []) as $attachment_id) {
            $reference = iss_content_model_place_media_reference(absint($attachment_id));
            if ($reference) {
                $media_refs[] = $reference;
            }
        }

        $source_refs = [];
        foreach ((array) ($epoch['source_links'] ?? []) as $source_url) {
            $source_url = esc_url_raw((string) $source_url);
            if ($source_url !== '') {
                $source_refs[] = [
                    'label' => wp_parse_url($source_url, PHP_URL_HOST) ?: __('Quelle', 'iss-content-model'),
                    'url' => $source_url,
                ];
            }
        }

        $sections[] = [
            'type' => 'epoche',
            'title' => (string) ($epoch['phase_name'] ?? ''),
            'body' => (string) ($epoch['summary'] ?? ''),
            'start_year' => $epoch['start_year'] ?? null,
            'end_year' => $epoch['end_year'] ?? null,
            'era_key' => (string) ($epoch['era_slug'] ?? ''),
            'function_key' => (string) ($epoch['function_key'] ?? ''),
            'is_current' => !empty($epoch['is_current']),
            'source_confidence' => (string) ($epoch['source_confidence'] ?? 'unknown'),
            'source_summary' => (string) ($epoch['source_summary'] ?? ''),
            'contribution_question' => (string) ($epoch['contribution_question'] ?? ''),
            'source_refs' => $source_refs,
            'media_refs' => $media_refs,
        ];
    }

    if ($current_use !== '') {
        $import = get_post_meta((int) $post->ID, '_iss_register_current_import', true);
        $source_url = is_array($import) ? esc_url_raw((string) ($import['source_url'] ?? '')) : '';
        $sections[] = [
            'type' => 'gegenwart',
            'title' => __('Heute', 'iss-content-model'),
            'body' => wpautop(esc_html($current_use)),
            'links' => $source_url !== '' ? [['label' => __('Standortregister: Quelle zur Gegenwart', 'iss-content-model'), 'url' => $source_url]] : [],
        ];
    }

    $sections[] = [
        'type' => 'upload_intake',
        'title' => __('Wissen Sie mehr über diesen Ort?', 'iss-content-model'),
        'body' => __('Eigene Fotos, Erinnerungen oder Korrekturen helfen, die Geschichte dieses Ortes vollständiger zu erzählen.', 'iss-content-model'),
    ];

    return [
        'schema_version' => 1,
        'skin' => 'ortsdossier',
        'variant' => 'standard',
        'features' => [],
        'sections' => $sections,
        'deleted_sections' => [],
    ];
}

function iss_content_model_register_editorial_formats(array $formats): array
{
    $gestures = iss_content_model_editorial_gesture_registry();
    $gallery_section = iss_content_model_editorial_gallery_section();
    $material_section = iss_content_model_editorial_material_section();

    $formats['place'] = [
        'label' => __('Ort', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => ['register_place'],
        'default_skin' => 'ortsdossier',
        'default_variant' => 'standard',
        'editor' => ['order_notice' => __('Feste Seitenfolge: Einleitung → Epochen in Datumsfolge → Gegenwart → Galerie und Material. Datierte Ereignisse erscheinen als kurze Aktualisierungen in der passenden Epoche. „Automatische Inhalte → Wissen zur Epoche beitragen“ nach der gewünschten Epoche einfügen, um dort zum Beitragen einzuladen.', 'iss-content-model')],
        'sections' => [
            'intro' => [
                'label' => __('Warum dieser Ort zählt', 'iss-content-model'),
                'description' => __('Am Anfang des Dossiers: Titel, Text und Bilder zum Ort. Kann einmal hinzugefügt werden.', 'iss-content-model'),
                'max_instances' => 1,
                'supports' => ['media_refs'],
            ],
            'epoche' => [
                'label' => __('Epoche', 'iss-content-model'),
                'editor' => [
                    'hidden_fields' => ['contribution_question'],
                    'media_hint' => __('Fotos dieser Epoche: Bilder aus einem Set oder der Mediathek auswählen. Alle ausgewählten Bilder erscheinen mit ihrer Bildunterschrift.', 'iss-content-model'),
                ],
                'description' => __('Eine datierte historische Phase mit Funktion, Quellen und Bildern.', 'iss-content-model'),
                'vocabularies' => [
                    'era_key' => [
                        'label' => __('Historische Epoche', 'iss-content-model'),
                        'choices' => [
                            'kaiserzeit' => __('Kaiserzeit', 'iss-content-model'),
                            'weimar' => __('Weimarer Republik', 'iss-content-model'),
                            'ns-zeit' => __('NS-Zeit', 'iss-content-model'),
                            'nachkriegszeit' => __('Nachkriegszeit', 'iss-content-model'),
                            'ddr' => __('DDR', 'iss-content-model'),
                            'nach-1990' => __('Nach 1990', 'iss-content-model'),
                        ],
                    ],
                    'function_key' => [
                        'label' => __('Funktion', 'iss-content-model'),
                        'choices' => [
                            'industrial' => __('Industrie / Produktion', 'iss-content-model'),
                            'commercial' => __('Gewerbe / Handel', 'iss-content-model'),
                            'culture' => __('Kultur', 'iss-content-model'),
                            'education' => __('Bildung / Forschung', 'iss-content-model'),
                            'community' => __('Gemeinwohl / Soziales', 'iss-content-model'),
                            'residential' => __('Wohnen', 'iss-content-model'),
                            'mixed' => __('Mischnutzung', 'iss-content-model'),
                            'vacant' => __('Leerstand', 'iss-content-model'),
                            'infrastructure' => __('Infrastruktur', 'iss-content-model'),
                        ],
                    ],
                ],
                'supports' => [
                    'start_year',
                    'end_year',
                    'era_key',
                    'function_key',
                    'is_current',
                    'source_confidence',
                    'source_summary',
                    'contribution_question',
                    'source_refs',
                    'media_refs',
                ],
            ],
            'dynamic_slot' => [
                'editor' => [
                    'preview_editable' => false,
                    'title_context' => [
                        'preceding_type' => 'epoche',
                        'placeholder' => __('Automatische Frage zur vorherigen Epoche', 'iss-content-model'),
                        'fallback_title' => __('Vorherige Epoche', 'iss-content-model'),
                        'hint' => __('Bezug: {title}. Titel leer lassen für die automatische Frage aus deren Zeitraum. Eigener Titel ersetzt sie.', 'iss-content-model'),
                        'missing_hint' => __('Diese Geste nach der gewünschten Epoche einfügen. Titel leer lassen für die automatische Frage aus deren Zeitraum.', 'iss-content-model'),
                    ],
                ],
                'description' => __('„Wissen zur Epoche beitragen“ nach der gewünschten Epoche einfügen. Die Frage verwendet deren Zeitraum. Alle Beiträge gehen in das gemeinsame Set des Ortes; Beiträge unter Material & Beiträge ausdrücklich öffnen.', 'iss-content-model'),
                'supports' => ['treatment', 'slot_key', 'no_body'],
                'slots' => [
                    'place-epoch-contribution' => [
                        'label' => __('Wissen zur Epoche beitragen', 'iss-content-model'),
                        'treatment' => 'slot.place-epoch-contribution',
                    ],
                ],
                'treatments' => [
                    'slot.place-epoch-contribution' => [
                        'label' => __('Wissen zur Epoche beitragen', 'iss-content-model'),
                        'schematic' => 'callout',
                        'hint' => __('Verwendet die vorherige Epoche. Titel leer lassen für die automatische Frage aus deren Zeitraum. Bei geöffneten Beiträgen führen alle Einladungen in das gemeinsame Set des Ortes.', 'iss-content-model'),
                    ],
                ],
            ],
            'gegenwart' => [
                'label' => __('Gegenwart', 'iss-content-model'),
                'description' => __('Nach der Geschichte: heutige Nutzung, Bilder und weiterführende Links. Kann einmal hinzugefügt werden.', 'iss-content-model'),
                'max_instances' => 1,
                'supports' => ['media_refs', 'links'],
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['media_refs', 'object_refs', 'gallery_layout']]),
            'material' => $material_section,
            'upload_intake' => [
                'label' => __('Material beitragen', 'iss-content-model'),
                'description' => __('Einladung zum Hochladen von Fotos und Dokumenten. Unter Angaben & Beziehungen → Material & Beiträge den Upload ausdrücklich öffnen. Einsendungen bleiben bis zur Freigabe privat.', 'iss-content-model'),
                'supports' => ['links'],
            ],
        ],
    ];

    $formats['landing'] = [
        'supported_versions' => [1, 2, 3],
        'label' => __('Landing Page', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => ['page'],
        'post_eligibility_callback' => 'iss_content_model_landing_page_is_eligible',
        'opening_skin' => 'frontpage',
        'opening_eligibility_callback' => static function (WP_Post $post): bool {
            return (int) $post->ID === (int) get_option('page_on_front');
        },
        'default_skin' => 'typografisch',
        'default_variant' => 'standard',
        'skin_meta_key' => '_iss_editorial_landing_skin',
        'sections' => [
            'statement' => $gestures['statement'],
            'fliesstext' => $gestures['fliesstext'],
            'gateway' => $gestures['gateway'],
            'text_bild_reihe' => $gestures['text_bild_reihe'],
            'map_img' => [
                'label' => __('Karte & Bild', 'iss-content-model'),
                'description' => __('Karte der verknüpften Orte mit Panorama und Bild-Text-Karten. Orte unter „Angaben & Beziehungen“ zuordnen; Bilder hier auswählen.', 'iss-content-model'),
                'supports' => ['treatment', 'lead', 'media_refs', 'items'],
                'treatments' => [
                    'map-img.editorial-atlas' => __('Karte, Panorama und Ortskarten', 'iss-content-model'),
                ],
            ],
            'galerie' => $gestures['galerie'],
            'feature' => $gestures['feature'],
            'dynamic_slot' => [
                'label' => __('Automatische Inhalte', 'iss-content-model'),
                'description' => __('Termine, Projekte oder weitere automatisch geladene Inhalte. Die Quelle unter „Darstellung“ auswählen.', 'iss-content-model'),
                'supports' => ['treatment', 'slot_key'],
                'slots' => [
                    'front-projects' => ['label' => __('Projekt-Notizen', 'iss-content-model'), 'treatment' => 'slot.projects'],
                    'front-timeline' => ['label' => __('Termine', 'iss-content-model'), 'treatment' => 'slot.timeline'],
                    'front-visit-info' => ['label' => __('Besuchsinfo', 'iss-content-model'), 'treatment' => 'slot.visit-info'],
                    'front-newsletter' => ['label' => __('Newsletter', 'iss-content-model'), 'treatment' => 'slot.newsletter'],
                    'fuehrungen-offers' => ['label' => __('Führungsangebote', 'iss-content-model'), 'treatment' => 'slot.fuehrungen-offers'],
                    'team-directory' => ['label' => __('Team-Verzeichnis', 'iss-content-model'), 'treatment' => 'slot.team-directory'],
                    'schoneweide-atlas' => ['label' => __('Schöneweide-Atlas', 'iss-content-model'), 'treatment' => 'slot.schoneweide-atlas'],
                ],
            ],
            'atlas_map' => [
                'label' => __('Atlas Karte', 'iss-content-model'),
                'description' => __('Zeigt die verknüpften Orte der Seite. Orte unter „Angaben & Beziehungen“ zuordnen; die Kartenansicht hier auswählen.', 'iss-content-model'),
                'supports' => ['treatment', 'links'],
                'treatments' => [
                    'atlas-map.place-locator' => __('Ort verorten', 'iss-content-model'),
                    'atlas-map.map-only' => __('Kartenband', 'iss-content-model'),
                    'atlas-map.editorial-split' => __('Text und Karte', 'iss-content-model'),
                ],
            ],
        ],
    ];

    // Openings are template capabilities; the project stage registers its own below.
    $formats['landing']['sections']['feature']['treatments']['feature.opening'] = [
        'label' => __('Seitenauftakt', 'iss-content-model'), 'role' => 'opening', 'min_version' => 3,
    ];
    $presentations = iss_content_model_landing_treatment_presentations();
    foreach ($formats['landing']['sections'] as $type => &$section) {
        foreach (($section['slots'] ?? []) as $slot) {
            $section['treatments'][$slot['treatment']] = ['label' => $slot['label'], 'schematic' => 'cards', 'hint' => 'Inhalte werden aus den verknüpften Daten geladen.'];
        }
        foreach (($section['treatments'] ?? []) as $slug => $treatment) {
            $section['treatments'][$slug] = array_merge(is_array($treatment) ? $treatment : ['label' => $treatment], $presentations[$slug] ?? []);
        }
        $section['rich_text'] = ['body' => 'block', 'lead' => 'block'];
        if (in_array('items', $section['supports'], true)) {
            $section['rich_text']['items'] = in_array($type, ['text_bild_reihe', 'map_img'], true) ? 'inline' : 'inline-card';
        }
    }
    unset($section);

    // General editorial content uses the same section contracts and theme renderer.
    // Existing block content stays authoritative until a reviewed document is enabled.
    $formats['article'] = [
        'label' => __('Seite / Beitrag', 'iss-content-model'),
        // General narrative bodies share one contract. Structured records and
        // their metadata remain with their CPT owners; Places use `place`.
        'post_types' => ['page', 'post', 'video', 'team_member', 'entity_profile', 'archivbeitrag', 'archivsammlung', 'archivobjekt'],
        'post_eligibility_callback' => static function (WP_Post $post): bool {
            return $post->post_type !== 'page' || !iss_content_model_landing_page_is_eligible($post);
        },
        'default_skin' => 'typografisch',
        'sections' => array_intersect_key($formats['landing']['sections'], array_flip(['statement', 'fliesstext', 'gateway', 'text_bild_reihe', 'feature', 'galerie'])),
    ];
    // The opening belongs to the front-page template, not ordinary articles.
    unset($formats['article']['sections']['feature']['treatments']['feature.opening']);

    $formats['fuehrung'] = [
        'label' => __('Führung', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => ['fuehrung'],
        'default_skin' => 'route-dossier',
        'route_stations_callback' => 'iss_relations_get_route_station_editor_config',
        'default_variant' => 'standard',
        'sections' => [
            'bildbuehne' => [
                'label' => __('Bildbühne', 'iss-content-model'),
                'editor' => ['title_from_post' => true, 'title_hint' => __('Ohne eigenen Titel erscheint der Titel der Führung.', 'iss-content-model')],
                'description' => __('Bildauftakt der Führung mit Text und Buchungsangaben. Das erste Bild bildet den Hintergrund; weitere Bilder ergänzen die Galerie.', 'iss-content-model'),
                'legacy_body_policy' => 'basic',
                'supports' => ['media_refs'],
            ],
            'intro' => [
                'label' => __('Einleitung', 'iss-content-model'),
                'description' => __('Vorhandene Einleitung der Führung. Der Bildauftakt nutzt den Text der Bildbühne oder die Kurzbeschreibung.', 'iss-content-model'),
                'legacy_body_policy' => 'basic',
                'supports' => ['media_refs'],
                'ui_hidden' => true,
            ],
            'kapitel' => array_merge($gestures['kapitel'], ['legacy_body_policy' => 'basic']),
            'leitfrage' => array_merge($gestures['leitfrage'], ['legacy_body_policy' => 'basic']),
            'zitat' => $gestures['zitat'],
            'galerie' => $gestures['galerie'],
            'atlas_map' => [
                'editor' => ['route_stations' => true],
                'label' => __('Die interaktive Landkarte zur Tour.', 'iss-content-model'),
                'description' => __('Zeigt die Route dieser Führung. Orte und Reihenfolge über „Route / Stationen bearbeiten“ festlegen und die Route dort veröffentlichen.', 'iss-content-model'),
                'supports' => ['anchor', 'treatment', 'links'],
                'treatments' => [
                    'atlas-map.tour-route' => __('Führungsroute', 'iss-content-model'),
                ],
            ],
            'material' => array_merge($gestures['material'], ['legacy_body_policy' => 'basic']),
            'upload_intake' => [
                'label' => __('Öffentlicher Mitmach-Aufruf für Gäste', 'iss-content-model'),
                'description' => $gestures['upload_intake']['description'],
                'supports' => ['anchor'],
            ],
            'schluss' => array_merge($gestures['schluss'], ['legacy_body_policy' => 'basic']),
        ],
    ];

    $formats['ausstellung'] = [
        'label' => __('Ausstellung', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => [ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE],
        'default_skin' => 'typografisch',
        'default_variant' => 'standard',
        'sections' => [
            'kapitel' => $gestures['kapitel'],
            'leitfrage' => $gestures['leitfrage'],
            'objektfokus' => $gestures['objektfokus'],
            'facts' => $gestures['facts'],
            'zitat' => $gestures['zitat'],
            'galerie' => $gestures['galerie'],
            'fliesstext' => $gestures['fliesstext'],
            'material' => $gestures['material'],
            'upload_intake' => [
                'label' => __('Material beitragen', 'iss-content-model'),
                'description' => $gestures['upload_intake']['description'],
                'supports' => [],
            ],
            'schluss' => $gestures['schluss'],
        ],
    ];

    $formats['projekt'] = [
        'label' => __('Projekt', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => [ISS_CONTENT_MODEL_PROJEKT_POST_TYPE],
        'default_skin' => 'dossier',
        'opening_skin' => 'buehne',
        'editor' => ['rail_control' => true, 'rail_placement_by_skin' => ['dossier' => 'horizontal']],
        'default_variant' => 'standard',
        'sections' => [
            'kapitel' => array_merge($gestures['kapitel'], ['legacy_body_policy' => 'basic']),
            'fliesstext' => array_merge($gestures['fliesstext'], ['legacy_body_policy' => 'basic']),
            'facts' => $gestures['facts'],
            'galerie' => $gestures['galerie'],
            'material' => $gestures['material'],
            'upload_intake' => [
                'label' => __('Öffentlicher Mitmach-Aufruf für Gäste', 'iss-content-model'),
                'description' => $gestures['upload_intake']['description'],
                'supports' => ['anchor', 'links'],
            ],
            'schluss' => array_merge($gestures['schluss'], ['legacy_body_policy' => 'basic']),
        ],
    ];

    $formats['projekt']['sections']['dynamic_slot'] = [
        'label' => __('Automatische Inhalte', 'iss-content-model'),
        'description' => __('Veranstaltungen dieser Reihe. Die Zuordnung wird bei der Veranstaltung unter „Reihe / Projekt“ gepflegt.', 'iss-content-model'),
        'supports' => ['anchor', 'treatment', 'slot_key'],
        'slots' => ['project-events' => ['label' => __('Veranstaltungen dieser Reihe', 'iss-content-model'), 'treatment' => 'slot.project-events']],
        'treatments' => ['slot.project-events' => ['label' => __('Veranstaltungen dieser Reihe', 'iss-content-model'), 'schematic' => 'cards']],
    ];

    $formats['rueckblick'] = [
        'label' => __('Rückblick', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => [ISS_CONTENT_MODEL_RUECKBLICK_POST_TYPE],
        'default_skin' => 'chronik',
        'default_variant' => 'standard',
        'sections' => [
            'intro' => [
                'label' => __('Intro', 'iss-content-model'),
                'description' => __('Den Anlass und die wichtigsten Eindrücke zusammenfassen.', 'iss-content-model'),
                'supports' => ['media_refs'],
            ],
            'fliesstext' => $gestures['fliesstext'],
            'galerie' => $gestures['galerie'],
            'objektfokus' => $gestures['objektfokus'],
            'material' => $gestures['material'],
            'upload_intake' => $formats['ausstellung']['sections']['upload_intake'],
            'schluss' => $gestures['schluss'],
        ],
    ];

    return $formats;
}
add_filter('iss_editorial_formats', 'iss_content_model_register_editorial_formats');

/** Small diagrams describe existing treatments; public layout remains theme-owned. */
function iss_content_model_landing_treatment_presentations(): array
{
    return [
        'slot.projects' => ['schematic' => 'notes', 'hint' => 'Zwei veröffentlichte Projekte nach ihrer Reihenfolge. Titel, Kurztext und Bild werden bei den Projekten gepflegt.'],
        'slot.timeline' => ['schematic' => 'list', 'hint' => 'Das Programm liefert automatisch den hervorgehobenen Beitrag und kommende Termine aus Führungen, Veranstaltungen und Ausstellungen.'],
        'slot.visit-info' => ['schematic' => 'panel', 'hint' => 'Adresse und Öffnungszeiten aus den zentralen Besuchsinformationen.'],
        'slot.newsletter' => ['schematic' => 'callout', 'hint' => 'Anmeldeformular des Newsletters mit Einladungstext und Partnerlogos.'],
        'slot.fuehrungen-offers' => ['schematic' => 'cards', 'hint' => 'Der Führungskatalog liefert die verfügbaren Angebote. Texte, Bilder und Buchungsangaben werden bei den Führungen gepflegt.'],
        'slot.team-directory' => ['schematic' => 'cards', 'hint' => 'Veröffentlichte Teammitglieder in ihrer festgelegten Reihenfolge. Namen, Bilder und Rollen werden bei den Teammitgliedern gepflegt.'],
        'slot.schoneweide-atlas' => ['schematic' => 'panel', 'hint' => 'Der Schöneweide-Atlas liefert Karte und Orte aus dem Register. Ortsdaten werden dort gepflegt.'],
        'statement.lead' => ['schematic' => 'heading', 'hint' => 'Große Überschrift mit Einleitung.'],
        'statement.leitfrage' => ['schematic' => 'heading', 'hint' => 'Eine Frage als Blickfang.'],
        'statement.callout' => ['schematic' => 'callout', 'hint' => 'Kurzer Aufruf mit Link.'],
        'text.standard' => ['schematic' => 'text', 'hint' => 'Titel und Text untereinander.'],
        'text.story-split' => ['schematic' => 'split', 'hint' => 'Erzählung links, Titel rechts.'],
        'text.story-split-flip' => ['schematic' => 'split-flip', 'hint' => 'Titel links, Erzählung rechts.'],
        'gateway.cards' => ['schematic' => 'cards', 'hint' => 'Verlinkte Karten mit Bild und Text.'],
        'gateway.link-list' => ['schematic' => 'list', 'hint' => 'Kompakte Liste mit Links.'],
        'gateway.feature-strip' => ['schematic' => 'strip', 'hint' => 'Breite Reihe hervorgehobener Ziele.'],
        'gateway.pathways' => ['schematic' => 'cards', 'hint' => 'Thematische Einstiege nebeneinander.'],
        'gateway.atlas-plates' => ['schematic' => 'cards', 'hint' => 'Orte als Karten zum Entdecken.'],
        'text-bild-reihe.visual' => ['schematic' => 'cards', 'hint' => 'Große Bilder mit Text darunter.'],
        'text-bild-reihe.compact' => ['schematic' => 'strip', 'hint' => 'Kleine Bilder neben kurzen Texten.'],
        'text-bild-reihe.chronology' => ['schematic' => 'list', 'hint' => 'Bild und Text in zeitlicher Folge.'],
        'text-bild-reihe.editorial' => ['schematic' => 'cards', 'hint' => 'Themen mit Vorspann, Titel und Text nebeneinander.'],
        'text-bild-reihe.agenda' => ['schematic' => 'list', 'hint' => 'Zeiträume neben Text; Details aufklappen, optional nach Themen filtern.'],
        'map-img.editorial-atlas' => ['schematic' => 'split', 'hint' => 'Karte neben Panorama und Ortskarten.'],
        'feature.media-panel' => ['schematic' => 'panel', 'hint' => 'Bild mit hervorgehobenem Infokasten.'],
        'feature.media-text' => ['schematic' => 'split', 'hint' => 'Bild und Text nebeneinander.'],
        'feature.image-overlay' => ['schematic' => 'overlay', 'hint' => 'Text liegt über dem Bild. Hintergrundkontrast prüfen.'],
        'feature.origin-story' => ['schematic' => 'split-flip', 'hint' => 'Einleitung und Bilder erzählen die Herkunft.'],
        'feature.opening' => ['schematic' => 'opening', 'hint' => 'Ersetzt den Vorlagenauftakt: erster Abschnitt, Startseiten-Stil, Titel und Bild erforderlich.'],
        'atlas-map.place-locator' => ['schematic' => 'panel', 'hint' => 'Ein Ort auf der Karte.'],
        'atlas-map.map-only' => ['schematic' => 'overlay', 'hint' => 'Breites Kartenband.'],
        'atlas-map.editorial-split' => ['schematic' => 'split', 'hint' => 'Erklärung neben der Karte.'],
    ];
}

/** Upgrade the editor copy only. Stored/public v1 and v2 documents stay unchanged. */
function iss_content_model_landing_editor_document(array $document, int $post_id, string $format): array
{
    if ($format !== 'landing' || ($document['schema_version'] ?? 1) !== 2) { return $document; }
    $first = $document['sections'][0] ?? [];
    if ($post_id === (int) get_option('page_on_front') && ($document['skin'] ?? '') === 'frontpage'
        && ($first['type'] ?? '') === 'feature' && ($first['treatment'] ?? '') === 'feature.image-overlay'
        && trim((string) ($first['title'] ?? '')) !== '' && wp_attachment_is_image(absint($first['media_refs'][0]['id'] ?? 0))) {
        $document['sections'][0]['treatment'] = 'feature.opening';
    }
    $document['schema_version'] = 3;
    return $document;
}
add_filter('iss_editorial_editor_document', 'iss_content_model_landing_editor_document', 10, 3);

/** Invalid opening edits remain recoverable drafts, but cannot replace a valid preview. */
add_filter('iss_editorial_validated_document', static function ($validated, array $raw, array $format) {
    if (is_wp_error($validated) || !in_array($format['slug'], ['landing', 'projekt'], true)) { return $validated; }
    $opening_skin = $format['slug'] === 'projekt' ? 'buehne' : 'frontpage';
    foreach ($raw['sections'] as $index => $section) {
        if (($section['treatment'] ?? '') !== 'feature.opening') { continue; }
        if ($raw['schema_version'] < 3 || $index !== 0 || ($raw['skin'] ?? '') !== $opening_skin
            || trim((string) ($section['title'] ?? '')) === '' || !wp_attachment_is_image(absint($section['media_refs'][0]['id'] ?? 0))) {
            return new WP_Error('editorial_invalid_opening', __('Seitenauftakt: Bitte an die erste Stelle setzen, den passenden Seitenstil (Startseite oder Bühne) wählen sowie Titel und Bild ergänzen.', 'iss-content-model'));
        }
    }
    return $validated;
}, 10, 3);

/** Shared definitions enrich the existing format registry, without changing stored documents. */
add_filter('iss_editorial_formats', static function (array $formats): array {
    $definitions = iss_content_model_editorial_gesture_registry();
    $starters = [
        'landing' => [['label' => 'Einführung und Überblick', 'sections' => [['type' => 'statement'], ['type' => 'gateway']]]],
        'article' => [['label' => 'Text mit Bildern', 'sections' => [['type' => 'statement'], ['type' => 'fliesstext'], ['type' => 'galerie']]]],
        'projekt' => [['label' => 'Projektdossier', 'sections' => [['type' => 'kapitel', 'title' => 'Das Projekt'], ['type' => 'facts', 'title' => 'Auf einen Blick'], ['type' => 'galerie']]]],
        'rueckblick' => [['label' => 'Bericht mit Bildern', 'sections' => [['type' => 'intro'], ['type' => 'fliesstext'], ['type' => 'galerie']]]],
        'ausstellung' => [['label' => 'Ausstellungsrundgang', 'sections' => [['type' => 'statement', 'treatment' => 'statement.leitfrage'], ['type' => 'kapitel', 'title' => 'Die Ausstellung'], ['type' => 'galerie']]]],
        'fuehrung' => [['label' => 'Führung vorstellen', 'sections' => [['type' => 'kapitel', 'title' => 'Die Führung'], ['type' => 'galerie']]]],
        'place' => [['label' => 'Ortsdossier', 'sections' => [['type' => 'intro'], ['type' => 'epoche', 'title' => 'Geschichte'], ['type' => 'gegenwart']]]],
        'veranstaltung' => [['label' => 'Veranstaltung vorstellen', 'sections' => [['type' => 'intro'], ['type' => 'kapitel', 'title' => 'Programm']]]],
    ];
    $presentations = iss_content_model_landing_treatment_presentations();
    $portable = array_filter($definitions, static fn(array $gesture): bool => !empty($gesture['portable']) && empty($gesture['ui_hidden']));
    foreach ($formats as $slug => &$format) {
        if (!isset($starters[$slug]) && $slug !== 'publication') { continue; }
        // These formats have an ordered composition adapter in the theme.
        // Places retain their separate dossier contract.
        if ($slug !== 'place') { $format['sections'] += $portable; }
        $format['starters'] = $format['starters'] ?? ($starters[$slug] ?? []);
        $format['editor'] = array_merge(['workspace' => true], (array) ($format['editor'] ?? []));
        $format['supported_versions'] = $format['supported_versions'] ?? [1, 2, 3];
        $format['default_version'] = $format['default_version'] ?? 3;
        foreach ($format['sections'] as $type => &$section) {
            if (!isset($definitions[$type])) { continue; }
            $section = array_merge($definitions[$type], $section);
            if ($slug === 'place') {
                unset($section['portable']);
            } elseif (!empty($definitions[$type]['portable'])) {
                $section['supports'] = array_merge($definitions[$type]['supports'], ['surface']);
                if ($type === 'text_bild_reihe') {
                    $section['treatments'] = array_merge((array) ($section['treatments'] ?? []), $definitions[$type]['treatments']);
                }
                if ($slug === 'projekt' && $type === 'feature') {
                    $section['treatments']['feature.opening'] = ['label' => __('Seitenauftakt', 'iss-content-model'), 'role' => 'opening', 'min_version' => 3];
                }
                // Live institution references remain an event-owned extension.
                if ($slug === 'veranstaltung' && $type === 'kapitel') {
                    $section['supports'][] = 'dynamic_refs';
                }
                if ($slug === 'veranstaltung' && $type === 'material') {
                    $section['supports'] = array_merge($section['supports'], ['object_refs', 'dynamic_refs', 'items']);
                }
            }
            // One concise chooser label across formats; domain explanations
            // belong to descriptions, not alternate names for the same gesture.
            $section['label'] = $definitions[$type]['label'];
            $section['family'] = $definitions[$type]['family'] ?? $type;
            if (!empty($section['portable'])) {
                $section['rich_text'] = ['body' => 'block', 'lead' => 'block'];
                if (in_array('items', $section['supports'], true) && ($section['items_kind'] ?? '') !== 'text') {
                    $section['rich_text']['items'] = $type === 'text_bild_reihe' ? 'inline' : 'inline-card';
                }
            }
            if (!in_array('no_body', $section['supports'] ?? [], true) && !in_array($type, ['source', 'bildbuehne'], true)) {
                $section['rich_text'] = array_merge(['body' => 'block'], (array) ($section['rich_text'] ?? []));
                if (in_array('lead', $section['supports'] ?? [], true)) { $section['rich_text']['lead'] = 'block'; }
            }
            if ($type === 'atlas_map' && $slug === 'landing') {
                // Keep the editor/storage default that existing saved documents already use.
                $section['default_treatment'] = 'atlas-map.place-locator';
            }
            if ($slug === 'landing' && $type === 'feature') {
                // Hidden controls retain their saved ratio when switching treatments.
                $section['media_layout'] = [
                    'choices' => [
                        '50-50' => __('Ausgewogen', 'iss-content-model'),
                        '40-60' => __('Text kompakt', 'iss-content-model'),
                        '60-40' => __('Text breit', 'iss-content-model'),
                    ],
                    'default' => '50-50',
                    'treatments' => ['feature.media-text'],
                ];
            }
            $section['treatments'] = (array) ($section['treatments'] ?? []);
            if (!empty($section['legacy_treatment_field']) && in_array($section['legacy_treatment_field'], $section['supports'] ?? [], true)) {
                $section['supports'] = array_values(array_unique(array_merge($section['supports'], ['treatment'])));
            }
            foreach ($section['treatments'] as $treatment => &$choice) {
                $choice = array_merge(['schematic' => $section['schematic']], is_array($choice) ? $choice : ['label' => $choice], $presentations[$treatment] ?? []);
            }
            unset($choice);
        }
        unset($section);
    }
    unset($format);
    return $formats;
}, 90);

/** Project identity travels with the editorial document, including drafts and revisions. */
add_filter('iss_editorial_document_features', static function (array $sanitized, array $features, string $format_slug): array {
    if ($format_slug !== 'projekt' || !is_array($features['identity'] ?? null)) { return $sanitized; }
    $identity = $features['identity'];
    $value = [];
    $palettes = iss_editorial_get_format('projekt')['editor']['identity_palettes'] ?? [];
    if (isset($identity['palette']) && is_string($identity['palette']) && isset($palettes[$identity['palette']])) {
        $value['palette'] = $identity['palette'];
    }
    if (isset($identity['media_refs']) && is_array($identity['media_refs'])) {
        $value['media_refs'] = array_values(array_filter(
            array_slice(iss_editorial_sanitize_reference_list($identity['media_refs']), 0, 1),
            static function (array $reference): bool {
                return ($reference['source'] ?? '') === 'wp-media'
                    && ($reference['kind'] ?? '') === 'media'
                    && wp_attachment_is_image(absint($reference['id'] ?? 0));
            }
        ));
    }
    $sanitized['identity'] = $value;
    return $sanitized;
}, 10, 3);
