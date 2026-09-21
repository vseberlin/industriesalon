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
        'kind' => 'wp_media',
        'source' => 'wordpress',
        'id' => (string) $attachment_id,
        'label' => (string) get_the_title($attachment),
    ];
}

function iss_content_model_build_place_editorial_candidate(WP_Post $post): array
{
    $sections = [];
    $history_short = trim((string) get_post_meta((int) $post->ID, 'history_short', true));
    $current_use = trim((string) get_post_meta((int) $post->ID, 'current_use', true));

    if ($history_short !== '') {
        $sections[] = [
            'type' => 'intro',
            'title' => __('Ort auf einen Blick', 'iss-content-model'),
            'body' => $history_short,
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
            'source_refs' => $source_refs,
            'media_refs' => $media_refs,
        ];
    }

    if ($current_use !== '') {
        $sections[] = [
            'type' => 'gegenwart',
            'title' => __('Heute', 'iss-content-model'),
            'body' => $current_use,
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
    $gallery_section = iss_content_model_editorial_gallery_section();
    $material_section = iss_content_model_editorial_material_section();

    $formats['place'] = [
        'label' => __('Ort', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => ['register_place'],
        'default_skin' => 'ortsdossier',
        'default_variant' => 'standard',
        'sections' => [
            'intro' => [
                'label' => __('Kurzprofil', 'iss-content-model'),
                'description' => __('Ein knapper Einstieg in Bedeutung und heutige Lesart des Ortes.', 'iss-content-model'),
                'supports' => ['media_refs'],
            ],
            'epoche' => [
                'label' => __('Epoche', 'iss-content-model'),
                'description' => __('Eine datierte historische Phase mit Funktion, Quellen und Bildern.', 'iss-content-model'),
                'supports' => [
                    'start_year',
                    'end_year',
                    'era_key',
                    'function_key',
                    'is_current',
                    'source_confidence',
                    'source_summary',
                    'source_refs',
                    'media_refs',
                ],
            ],
            'gegenwart' => [
                'label' => __('Gegenwart', 'iss-content-model'),
                'description' => __('Heutige Nutzung, Zustand und aktuelle Entwicklung.', 'iss-content-model'),
                'supports' => ['media_refs', 'links'],
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['media_refs', 'object_refs', 'gallery_layout']]),
            'material' => $material_section,
            'upload_intake' => [
                'label' => __('Material beitragen', 'iss-content-model'),
                'description' => __('Öffentlicher Mitmach-Aufruf; Uploads werden vor Veröffentlichung geprüft.', 'iss-content-model'),
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
        'default_skin' => 'typografisch',
        'default_variant' => 'standard',
        'skin_meta_key' => '_iss_editorial_landing_skin',
        'sections' => [
            'statement' => [
                'label' => __('Überschrift & Einleitung', 'iss-content-model'),
                'description' => __('Der große Einführungstext oder die Leitfrage ganz oben auf der Seite (optional mit Link).', 'iss-content-model'),
                'supports' => ['treatment', 'links'],
                'treatments' => [
                    'statement.lead' => __('Leitstatement', 'iss-content-model'),
                    'statement.leitfrage' => __('Leitfrage', 'iss-content-model'),
                    'statement.callout' => __('Handlungsaufruf', 'iss-content-model'),
                ],
            ],
            'fliesstext' => [
                'label' => __('Freitext', 'iss-content-model'),
                'description' => __('Ganz normaler Textabschnitt ohne starre Boxen oder Medieninhalte für tiefere Infos.', 'iss-content-model'),
                'supports' => ['treatment', 'links'],
                'treatments' => [
                    'text.standard' => __('Standard', 'iss-content-model'),
                    'text.story-split' => __('Erzählung links, Titel rechts', 'iss-content-model'),
                    'text.story-split-flip' => __('Titel links, Erzählung rechts', 'iss-content-model'),
                ],
            ],
            'gateway' => [
                'label' => __('Einstiegs-Boxen', 'iss-content-model'),
                'description' => __('Kurze Vorschau-Karten zu anderen Seiten (z. B. als Kacheln oder Linkliste).', 'iss-content-model'),
                'supports' => ['treatment', 'items'],
                'treatments' => [
                    'gateway.cards' => __('Karten', 'iss-content-model'),
                    'gateway.link-list' => __('Linkliste', 'iss-content-model'),
                    'gateway.feature-strip' => __('Feature-Leiste', 'iss-content-model'),
                    'gateway.pathways' => __('Themenpfade', 'iss-content-model'),
                    'gateway.atlas-plates' => __('Atlas-Ortskarten', 'iss-content-model'),
                ],
            ],
            'text_bild_reihe' => [
                'label' => __('Text-Bild-Reihe', 'iss-content-model'),
                'description' => __('Eine Reihe nicht verlinkter Bild-Text-Paare, etwa für Perspektiven, Räume oder Auszeichnungen.', 'iss-content-model'),
                'supports' => ['treatment', 'items'],
                'treatments' => [
                    'text-bild-reihe.visual' => __('Großformatige Bildreihe', 'iss-content-model'),
                    'text-bild-reihe.compact' => __('Kompakte Bildreihe', 'iss-content-model'),
                    'text-bild-reihe.chronology' => __('Chronologische Bildreihe', 'iss-content-model'),
                ],
            ],
            'map_img' => [
                'label' => __('Karte & Bild', 'iss-content-model'),
                'description' => __('Eine räumliche Orientierungskarte neben einem großen Ortsbild mit ergänzenden Bild-Text-Karten.', 'iss-content-model'),
                'supports' => ['treatment', 'lead', 'media_refs', 'items'],
                'treatments' => [
                    'map-img.editorial-atlas' => __('Karte, Panorama und Ortskarten', 'iss-content-model'),
                ],
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['anchor', 'media_refs', 'gallery_layout']]),
            'feature' => [
                'label' => __('Hervorgehobener Inhalt', 'iss-content-model'),
                'description' => __('Ein auffälliger Abschnitt mit Bild, Text und Kernfakten, um ein Thema besonders zu betonen.', 'iss-content-model'),
                'supports' => ['treatment', 'lead', 'facts', 'links', 'media_refs', 'media_layout'],
                'treatments' => [
                    'feature.media-panel' => __('Bild mit Infokasten', 'iss-content-model'),
                    'feature.media-text' => __('Bild neben Text', 'iss-content-model'),
                    'feature.image-overlay' => __('Titel auf Bild', 'iss-content-model'),
                    'feature.origin-story' => __('Zweiteilige Herkunftserzählung', 'iss-content-model'),
                    'feature.opening' => ['label' => __('Seitenauftakt', 'iss-content-model'), 'role' => 'opening', 'min_version' => 3],
                ],
            ],
            'dynamic_slot' => [
                'label' => __('Automatische Inhalte', 'iss-content-model'),
                'description' => __('Platzhalter, der selbstständig Termine oder Projekt-Notizen lädt.', 'iss-content-model'),
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
                'description' => __('Eine vorgefertigte Landkarte mit Markierung.', 'iss-content-model'),
                'supports' => ['treatment', 'links'],
                'treatments' => [
                    'atlas-map.place-locator' => __('Ort verorten', 'iss-content-model'),
                    'atlas-map.map-only' => __('Kartenband', 'iss-content-model'),
                    'atlas-map.editorial-split' => __('Text und Karte', 'iss-content-model'),
                ],
            ],
        ],
    ];

    $presentations = iss_content_model_landing_treatment_presentations();
    $workspace_sections = [
        'statement' => ['editor-textcolor', 'text', 'Text'],
        'fliesstext' => ['editor-paragraph', 'text', 'Text'],
        'gateway' => ['screenoptions', 'navigation', 'Orientierung'],
        'text_bild_reihe' => ['format-gallery', 'media', 'Bild'],
        'map_img' => ['location-alt', 'navigation', 'Orientierung'],
        'galerie' => ['format-gallery', 'media', 'Bild'],
        'feature' => ['format-image', 'media', 'Bild'],
        'dynamic_slot' => ['update', 'automatic', 'Automatisch'],
        'atlas_map' => ['location-alt', 'navigation', 'Orientierung'],
    ];
    foreach ($formats['landing']['sections'] as $type => &$section) {
        [$section['icon'], $section['tone'], $section['group']] = $workspace_sections[$type];
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

    $formats['fuehrung'] = [
        'label' => __('Führung', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => ['fuehrung'],
        'default_skin' => 'route-dossier',
        'default_variant' => 'standard',
        'sections' => [
            'bildbuehne' => [
                'label' => __('Bildbühne', 'iss-content-model'),
                'description' => __('Viewport-Bühne mit großem Bild, Overlay-Text und optionaler kleiner Galerie.', 'iss-content-model'),
                'supports' => ['media_refs'],
            ],
            'intro' => [
                'label' => __('Einleitung', 'iss-content-model'),
                'description' => __('Legacy-Einstieg; die Hero-Beschreibung kommt aus Bildbühne oder Excerpt.', 'iss-content-model'),
                'supports' => ['media_refs'],
                'ui_hidden' => true,
            ],
            'kapitel' => [
                'label' => __('Kapitel', 'iss-content-model'),
                'description' => __('Tour-Erzählung, Kontext oder thematischer Abschnitt.', 'iss-content-model'),
                'supports' => ['anchor', 'media_refs', 'media_layout'],
            ],
            'leitfrage' => [
                'label' => __('Die Kernfrage oder These', 'iss-content-model'),
                'description' => __('Gibt den roten Faden vor und führt den Besucher durch das Thema.', 'iss-content-model'),
                'supports' => ['anchor'],
            ],
            'zitat' => [
                'label' => __('Ein prägnantes Zitat zum Inhalt.', 'iss-content-model'),
                'description' => __('Mit Angabe der Person oder der historischen Quelle.', 'iss-content-model'),
                'supports' => ['quote'],
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['anchor', 'media_refs', 'object_refs', 'gallery_layout']]),
            'atlas_map' => [
                'label' => __('Die interaktive Landkarte zur Tour.', 'iss-content-model'),
                'description' => __('Zeigt den genauen Routenverlauf und die einzelnen Stationen der Führung.', 'iss-content-model'),
                'supports' => ['anchor', 'treatment', 'links'],
                'treatments' => [
                    'atlas-map.tour-route' => __('Führungsroute', 'iss-content-model'),
                ],
            ],
            'material' => $material_section,
            'upload_intake' => [
                'label' => __('Öffentlicher Mitmach-Aufruf für Gäste', 'iss-content-model'),
                'description' => __('Schickt hochgeladene Besucherfotos direkt in die Warteschlange zur Freigabe.', 'iss-content-model'),
                'supports' => ['anchor'],
            ],
            'schluss' => [
                'label' => __('Schluss', 'iss-content-model'),
                'description' => __('Abschluss, Einladung oder weiterführende Links.', 'iss-content-model'),
                'supports' => ['anchor', 'links'],
            ],
        ],
    ];

    $formats['ausstellung'] = [
        'label' => __('Ausstellung', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => [ISS_CONTENT_MODEL_AUSSTELLUNG_POST_TYPE],
        'default_skin' => 'typografisch',
        'default_variant' => 'standard',
        'sections' => [
            'kapitel' => [
                'label' => __('Kapitel', 'iss-content-model'),
                'description' => __('Numbered chapter break', 'iss-content-model'),
                'supports' => ['links', 'section_treatment'],
            ],
            'leitfrage' => [
                'label' => __('Die Kernfrage oder These', 'iss-content-model'),
                'description' => __('Gibt den roten Faden vor und führt den Besucher durch das Thema.', 'iss-content-model'),
                'supports' => [],
            ],
            'objektfokus' => [
                'label' => __('Objektfokus', 'iss-content-model'),
                'description' => __('1-3 archive objects inline', 'iss-content-model'),
                'supports' => ['object_refs'],
            ],
            'facts' => [
                'label' => __('Merkpunkte', 'iss-content-model'),
                'description' => __('Facts, stats, or contextual key points with skin-owned presentation.', 'iss-content-model'),
                'supports' => ['facts'],
            ],
            'zitat' => [
                'label' => __('Ein prägnantes Zitat zum Inhalt.', 'iss-content-model'),
                'description' => __('Mit Angabe der Person oder der historischen Quelle.', 'iss-content-model'),
                'supports' => ['quote', 'object_refs', 'media_refs', 'orientation', 'quote_treatment'],
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['object_refs', 'media_refs', 'gallery_layout']]),
            'fliesstext' => [
                'label' => __('Fliesstext', 'iss-content-model'),
                'description' => __('Essay paragraph or connective text', 'iss-content-model'),
                'supports' => [],
            ],
            'material' => $material_section,
            'upload_intake' => [
                'label' => __('Material beitragen', 'iss-content-model'),
                'description' => __('Fotos und Dokumente zur redaktionellen Prüfung einsenden.', 'iss-content-model'),
                'supports' => [],
            ],
            'schluss' => [
                'label' => __('Schluss', 'iss-content-model'),
                'description' => __('Closing statement and onward direction', 'iss-content-model'),
                'supports' => ['links'],
            ],
        ],
    ];

    $formats['projekt'] = [
        'label' => __('Projekt', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => [ISS_CONTENT_MODEL_PROJEKT_POST_TYPE],
        'default_skin' => 'dossier',
        'default_variant' => 'standard',
        'sections' => [
            'kapitel' => [
                'label' => __('Kapitel', 'iss-content-model'),
                'description' => __('Project chapter with title and narrative body', 'iss-content-model'),
                'supports' => ['anchor', 'links'],
            ],
            'fliesstext' => [
                'label' => __('Fliesstext', 'iss-content-model'),
                'description' => __('Essay paragraph or connective text', 'iss-content-model'),
                'supports' => ['anchor', 'links'],
            ],
            'facts' => [
                'label' => __('Merkpunkte', 'iss-content-model'),
                'description' => __('Compact key points, facts, or context cards', 'iss-content-model'),
                'supports' => ['anchor', 'facts'],
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['anchor', 'media_refs', 'object_refs', 'gallery_layout']]),
            'material' => $material_section,
            'upload_intake' => [
                'label' => __('Öffentlicher Mitmach-Aufruf für Gäste', 'iss-content-model'),
                'description' => __('Schickt hochgeladene Besucherfotos direkt in die Warteschlange zur Freigabe.', 'iss-content-model'),
                'supports' => ['anchor', 'links'],
            ],
            'schluss' => [
                'label' => __('Kontakt / Schluss', 'iss-content-model'),
                'description' => __('Closing note, contact, and onward links', 'iss-content-model'),
                'supports' => ['anchor', 'links'],
            ],
        ],
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
                'description' => __('Post-event opening and summary.', 'iss-content-model'),
                'supports' => ['media_refs'],
            ],
            'fliesstext' => [
                'label' => __('Fliesstext', 'iss-content-model'),
                'description' => __('Curated report text.', 'iss-content-model'),
                'supports' => ['media_refs', 'object_refs'],
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['media_refs', 'object_refs', 'gallery_layout']]),
            'objektfokus' => [
                'label' => __('Objektfokus', 'iss-content-model'),
                'description' => __('Archive objects that support the report.', 'iss-content-model'),
                'supports' => ['object_refs'],
            ],
            'material' => array_merge($material_section, ['supports' => ['media_refs', 'links']]),
            'upload_intake' => $formats['ausstellung']['sections']['upload_intake'],
            'schluss' => [
                'label' => __('Schluss', 'iss-content-model'),
                'description' => __('Closing note and onward links.', 'iss-content-model'),
                'supports' => ['links'],
            ],
        ],
    ];

    return $formats;
}
add_filter('iss_editorial_formats', 'iss_content_model_register_editorial_formats');

/** Small diagrams describe existing treatments; public layout remains theme-owned. */
function iss_content_model_landing_treatment_presentations(): array
{
    return [
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
    if (is_wp_error($validated) || $format['slug'] !== 'landing') { return $validated; }
    foreach ($raw['sections'] as $index => $section) {
        if (($section['treatment'] ?? '') !== 'feature.opening') { continue; }
        if ($raw['schema_version'] < 3 || $index !== 0 || ($raw['skin'] ?? '') !== 'frontpage'
            || trim((string) ($section['title'] ?? '')) === '' || !wp_attachment_is_image(absint($section['media_refs'][0]['id'] ?? 0))) {
            return new WP_Error('editorial_invalid_opening', __('Seitenauftakt: Bitte an die erste Stelle setzen, den Startseiten-Stil wählen sowie Titel und Bild ergänzen.', 'iss-content-model'));
        }
    }
    return $validated;
}, 10, 3);
