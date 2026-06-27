<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_editorial_image_wall_section(): array
{
    return [
        'label' => __('Bilderwand', 'iss-content-model'),
        'description' => __('Ordered image wall with framed, uncropped media', 'iss-content-model'),
        'supports' => ['media_refs'],
    ];
}

function iss_content_model_editorial_gallery_section(): array
{
    return [
        'label' => __('Galerie', 'iss-content-model'),
        'description' => __('Approved media promoted from a Set or selected from the media library', 'iss-content-model'),
        'supports' => ['media_refs', 'object_refs'],
    ];
}

function iss_content_model_editorial_material_section(): array
{
    return [
        'label' => __('Material', 'iss-content-model'),
        'description' => __('Documents, links, archive references, and supporting project material', 'iss-content-model'),
        'supports' => ['anchor', 'media_refs', 'object_refs', 'links'],
    ];
}

function iss_content_model_register_editorial_formats(array $formats): array
{
    $gallery_section = iss_content_model_editorial_gallery_section();
    $image_wall_section = iss_content_model_editorial_image_wall_section();
    $material_section = iss_content_model_editorial_material_section();

    $formats['fuehrung'] = [
        'label' => __('Führung', 'iss-content-model'),
        'base' => 'ordered',
        'post_types' => ['fuehrung'],
        'default_skin' => 'route-dossier',
        'default_variant' => 'standard',
        'sections' => [
            'intro' => [
                'label' => __('Einleitung', 'iss-content-model'),
                'description' => __('Kurzer Einstieg für die Hero-Beschreibung der Führung.', 'iss-content-model'),
                'supports' => ['media_refs'],
            ],
            'kapitel' => [
                'label' => __('Kapitel', 'iss-content-model'),
                'description' => __('Tour-Erzählung, Kontext oder thematischer Abschnitt.', 'iss-content-model'),
                'supports' => ['anchor', 'media_refs', 'media_layout'],
            ],
            'leitfrage' => [
                'label' => __('Leitfrage', 'iss-content-model'),
                'description' => __('Frage oder These, die die Führung rahmt.', 'iss-content-model'),
                'supports' => ['anchor'],
            ],
            'zitat' => [
                'label' => __('Zitat', 'iss-content-model'),
                'description' => __('Zitat mit Zuordnung oder Quellenhinweis.', 'iss-content-model'),
                'supports' => ['quote'],
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['anchor', 'media_refs', 'object_refs', 'gallery_layout']]),
            'image_wall' => array_merge($image_wall_section, ['supports' => ['anchor', 'media_refs']]),
            'material' => $material_section,
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
        'default_skin' => 'standard',
        'default_variant' => 'standard',
        'sections' => [
            'kapitel' => [
                'label' => __('Kapitel', 'iss-content-model'),
                'description' => __('Numbered chapter break', 'iss-content-model'),
                'supports' => ['links', 'section_treatment'],
            ],
            'leitfrage' => [
                'label' => __('Leitfrage', 'iss-content-model'),
                'description' => __('The guiding question', 'iss-content-model'),
                'supports' => [],
            ],
            'objektfokus' => [
                'label' => __('Objektfokus', 'iss-content-model'),
                'description' => __('1-3 archive objects inline', 'iss-content-model'),
                'supports' => ['object_refs'],
            ],
            'quellenauszug' => [
                'label' => __('Quellenauszug', 'iss-content-model'),
                'description' => __('Source quote and citation', 'iss-content-model'),
                'supports' => ['quote', 'object_refs', 'media_refs', 'orientation'],
                'ui_hidden' => true,
            ],
            'massstab' => [
                'label' => __('Massstab', 'iss-content-model'),
                'description' => __('Stats and context paragraph', 'iss-content-model'),
                'supports' => [],
            ],
            'zitat' => [
                'label' => __('Zitat', 'iss-content-model'),
                'description' => __('Direct quote with attribution', 'iss-content-model'),
                'supports' => ['quote', 'object_refs', 'media_refs', 'orientation', 'quote_treatment'],
            ],
            'bildstrecke' => [
                'label' => __('Dokumentarische Strecke', 'iss-content-model'),
                'description' => __('Photo sequence with captions', 'iss-content-model'),
                'supports' => ['object_refs', 'media_refs', 'gallery_layout'],
                'ui_hidden' => true,
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['object_refs', 'media_refs', 'gallery_layout']]),
            'image_wall' => array_merge($image_wall_section, ['ui_hidden' => true]),
            'vollbild' => [
                'label' => __('Vollbild', 'iss-content-model'),
                'description' => __('One image, full viewport, short panel', 'iss-content-model'),
                'supports' => ['media_refs'],
            ],
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
            'aside' => [
                'label' => __('Ausstellungsentscheidung', 'iss-content-model'),
                'description' => __('Curator speaks directly', 'iss-content-model'),
                'supports' => ['links'],
                'ui_hidden' => true,
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
            'massstab' => [
                'label' => __('Merkpunkte', 'iss-content-model'),
                'description' => __('Compact key points, facts, or context cards', 'iss-content-model'),
                'supports' => ['anchor', 'facts'],
            ],
            'projekt_rail' => [
                'label' => __('Navigation', 'iss-content-model'),
                'description' => __('Optional project rail generated from chapter and contact sections', 'iss-content-model'),
                'supports' => ['no_body'],
                'ui_hidden' => true,
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['anchor', 'media_refs', 'object_refs', 'gallery_layout']]),
            'image_wall' => array_merge($image_wall_section, ['supports' => ['anchor', 'media_refs'], 'ui_hidden' => true]),
            'material' => $material_section,
            'upload_intake' => [
                'label' => __('Upload-Aufruf', 'iss-content-model'),
                'description' => __('Public contribution call that sends uploads into the moderated project Set.', 'iss-content-model'),
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
        'default_skin' => 'standard',
        'default_variant' => 'standard',
        'sections' => [
            'intro' => [
                'label' => __('Intro', 'iss-content-model'),
                'description' => __('Post-event opening and summary.', 'iss-content-model'),
                'supports' => ['media_refs'],
            ],
            'bericht' => [
                'label' => __('Bericht', 'iss-content-model'),
                'description' => __('Curated report text.', 'iss-content-model'),
                'supports' => ['media_refs', 'object_refs'],
                'ui_hidden' => true,
            ],
            'fliesstext' => [
                'label' => __('Fliesstext', 'iss-content-model'),
                'description' => __('Curated report text.', 'iss-content-model'),
                'supports' => ['media_refs', 'object_refs'],
            ],
            'bildstrecke' => [
                'label' => __('Bildstrecke', 'iss-content-model'),
                'description' => __('Approved gallery material promoted from a Set.', 'iss-content-model'),
                'supports' => ['media_refs', 'object_refs', 'gallery_layout'],
                'ui_hidden' => true,
            ],
            'galerie' => array_merge($gallery_section, ['supports' => ['media_refs', 'object_refs', 'gallery_layout']]),
            'objektfokus' => [
                'label' => __('Objektfokus', 'iss-content-model'),
                'description' => __('Archive objects that support the report.', 'iss-content-model'),
                'supports' => ['object_refs'],
            ],
            'material' => array_merge($material_section, ['supports' => ['media_refs', 'object_refs', 'links']]),
            'schluss' => [
                'label' => __('Schluss', 'iss-content-model'),
                'description' => __('Closing note and onward links.', 'iss-content-model'),
                'supports' => ['links'],
            ],
            'quellen' => [
                'label' => __('Quellen', 'iss-content-model'),
                'description' => __('Source material that supports the report.', 'iss-content-model'),
                'supports' => ['object_refs', 'links'],
                'ui_hidden' => true,
            ],
        ],
    ];

    return $formats;
}
add_filter('iss_editorial_formats', 'iss_content_model_register_editorial_formats');
