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

function iss_content_model_register_editorial_formats(array $formats): array
{
    $gallery_section = iss_content_model_editorial_gallery_section();
    $material_section = iss_content_model_editorial_material_section();

    $formats['landing'] = [
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
                ],
            ],
            'text_bild_reihe' => [
                'label' => __('Text-Bild-Reihe', 'iss-content-model'),
                'description' => __('Eine Reihe nicht verlinkter Bild-Text-Paare, etwa für Perspektiven, Räume oder Auszeichnungen.', 'iss-content-model'),
                'supports' => ['treatment', 'items'],
                'treatments' => [
                    'text-bild-reihe.visual' => __('Großformatige Bildreihe', 'iss-content-model'),
                    'text-bild-reihe.compact' => __('Kompakte Bildreihe', 'iss-content-model'),
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
                ],
            ],
            'dynamic_slot' => [
                'label' => __('Automatische Inhalte', 'iss-content-model'),
                'description' => __('Platzhalter, der selbstständig Termine oder Projekt-Notizen lädt.', 'iss-content-model'),
                'supports' => ['treatment', 'slot_key'],
                'treatments' => [
                    'slot.projects' => __('Projekt-Notizen', 'iss-content-model'),
                    'slot.timeline' => __('Termine', 'iss-content-model'),
                    'slot.visit-info' => __('Besuchsinfo', 'iss-content-model'),
                    'slot.newsletter' => __('Newsletter', 'iss-content-model'),
                    'slot.fuehrungen-offers' => __('Führungsangebote', 'iss-content-model'),
                    'slot.team-directory' => __('Team-Verzeichnis', 'iss-content-model'),
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
        'label' => __('Rueckblick', 'iss-content-model'),
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
