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

function iss_content_model_landing_page_allowed_slugs(): array
{
    return [
        'about',
        'verein',
        'salon-vermietung',
        'sammlungen',
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
    $image_wall_section = iss_content_model_editorial_image_wall_section();
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
                'label' => __('Statement', 'iss-content-model'),
                'description' => __('Redaktionelle These, Intro oder Callout mit optionalem Link.', 'iss-content-model'),
                'supports' => ['treatment', 'links'],
                'treatments' => [
                    'statement.lead' => __('Leitstatement', 'iss-content-model'),
                    'statement.callout' => __('Callout', 'iss-content-model'),
                ],
            ],
            'gateway' => [
                'label' => __('Gateway', 'iss-content-model'),
                'description' => __('Kuratiert nächste Wege als Karten oder Linkliste.', 'iss-content-model'),
                'supports' => ['treatment', 'items'],
                'treatments' => [
                    'gateway.cards' => __('Karten', 'iss-content-model'),
                    'gateway.link-list' => __('Linkliste', 'iss-content-model'),
                    'gateway.feature-strip' => __('Feature-Leiste', 'iss-content-model'),
                ],
            ],
            'feature' => [
                'label' => __('Feature', 'iss-content-model'),
                'description' => __('Bild, Fakten oder Mikroblöcke als hervorgehobener Landing-Abschnitt.', 'iss-content-model'),
                'supports' => ['treatment', 'facts', 'links', 'media_refs'],
                'treatments' => [
                    'feature.media-panel' => __('Medienpanel', 'iss-content-model'),
                    'feature.microblocks' => __('Mikroblöcke', 'iss-content-model'),
                ],
            ],
            'dynamic_slot' => [
                'label' => __('Dynamischer Slot', 'iss-content-model'),
                'description' => __('Theme-eigener Slot für bestehende dynamische Frontpage-Module.', 'iss-content-model'),
                'supports' => ['treatment', 'slot_key', 'no_body'],
                'treatments' => [
                    'slot.projects' => __('Projekt-Notizen', 'iss-content-model'),
                    'slot.timeline' => __('Termine', 'iss-content-model'),
                    'slot.visit-info' => __('Besuchsinfo', 'iss-content-model'),
                    'slot.newsletter' => __('Newsletter', 'iss-content-model'),
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
        'default_skin' => 'typografisch',
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
            'galerie' => array_merge($gallery_section, ['supports' => ['object_refs', 'media_refs', 'gallery_layout']]),
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
            'galerie' => array_merge($gallery_section, ['supports' => ['anchor', 'media_refs', 'object_refs', 'gallery_layout']]),
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
            'material' => array_merge($material_section, ['supports' => ['media_refs', 'object_refs', 'links']]),
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
