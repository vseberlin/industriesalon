<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_editorial_gesture_registry(): array
{
    $gestures = [
        'intro' => [
            'label' => __('Einleitung', 'iss-content-model'),
            'description' => __('Den Inhalt kurz einordnen.', 'iss-content-model'),
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'heading',
        ],
        'kapitel' => [
            'label' => __('Kapitel', 'iss-content-model'),
            'description' => __('Ein Thema mit Überschrift und Text vertiefen.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'links', 'media_refs', 'media_layout', 'section_treatment'],
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'text',
        ],
        'fliesstext' => [
            'label' => __('Fließtext', 'iss-content-model'),
            'description' => __('Ein zusammenhängender Textabschnitt.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'treatment', 'links', 'media_refs', 'object_refs'],
            'treatments' => [
                'text.standard' => __('Standard', 'iss-content-model'),
                'text.story-split' => __('Erzählung links, Titel rechts', 'iss-content-model'),
                'text.story-split-flip' => __('Titel links, Erzählung rechts', 'iss-content-model'),
            ],
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'text',
        ],
        'zitat' => [
            'label' => __('Zitat', 'iss-content-model'),
            'description' => __('Ein Zitat mit Quellen- oder Personenangabe.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'quote', 'object_refs', 'media_refs', 'orientation', 'quote_treatment'],
            'icon' => 'format-quote', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'callout',
        ],
        'schluss' => [
            'label' => __('Abschluss', 'iss-content-model'),
            'description' => __('Einordnung, Kontakt oder weiterführende Links.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'links'],
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'callout',
        ],
        'galerie' => [
            'label' => __('Bildergalerie', 'iss-content-model'),
            'description' => __('Bilder mit ihren Beschreibungen zusammenstellen.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'media_refs', 'object_refs', 'gallery_layout'],
            'icon' => 'format-gallery', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'cards',
            'legacy_types' => ['image_wall' => ['gallery_layout' => 'wall'], 'vollbild' => ['gallery_layout' => 'viewport']],
        ],
        'objektfokus' => [
            'label' => __('Archivobjekte', 'iss-content-model'),
            'description' => __('Ausgewählte Archivobjekte im Zusammenhang zeigen.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'object_refs'],
            'icon' => 'archive', 'tone' => 'media', 'group' => 'Material', 'schematic' => 'cards',
        ],
        'material' => [
            'label' => __('Begleitende Dateien', 'iss-content-model'),
            'description' => __('Dateien zum Herunterladen und weiterführende Links anbieten.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'media_refs', 'links'],
            'icon' => 'media-document', 'tone' => 'media', 'group' => 'Material', 'schematic' => 'list',
        ],
        'facts' => [
            'label' => __('Merkpunkte', 'iss-content-model'),
            'description' => __('Fakten und Zahlen übersichtlich zusammenstellen.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'facts'],
            'icon' => 'list-view', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'list',
            'legacy_types' => ['massstab' => []],
        ],
        'programm' => [
            'label' => __('Programm', 'iss-content-model'),
            'description' => __('Den Ablauf eines Programms oder Fests beschreiben.', 'iss-content-model'),
            'icon' => 'calendar-alt', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'list',
        ],
        'upload_intake' => [
            'label' => __('Material beitragen', 'iss-content-model'),
            'description' => __('Aufruf für Fotos und Dokumente zur Prüfung. Unter „Angaben & Beziehungen“ → „Beiträge von Gästen“ öffnen oder schließen. Ältere Aufrufe ohne eigene Einstellung gelten als geöffnet.', 'iss-content-model'),
            'icon' => 'upload', 'tone' => 'automatic', 'group' => 'Material', 'schematic' => 'callout',
        ],
        'statement' => [
            'label' => __('Überschrift & Intro', 'iss-content-model'),
            'description' => __('Eine Überschrift, Einleitung oder Leitfrage hervorheben.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'treatment', 'links'],
            'treatments' => [
                'statement.lead' => __('Leitstatement', 'iss-content-model'),
                'statement.leitfrage' => __('Leitfrage', 'iss-content-model'),
                'statement.callout' => __('Handlungsaufruf', 'iss-content-model'),
            ],
            'icon' => 'editor-textcolor', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'heading',
        ],
        'gateway' => [
            'label' => __('Linkkarten', 'iss-content-model'),
            'description' => __('Verlinkte Einstiege zu weiteren Inhalten zusammenstellen.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['treatment', 'items', 'links'],
            'treatments' => [
                'gateway.cards' => __('Karten', 'iss-content-model'),
                'gateway.link-list' => __('Linkliste', 'iss-content-model'),
                'gateway.feature-strip' => __('Feature-Leiste', 'iss-content-model'),
                'gateway.pathways' => __('Themenpfade', 'iss-content-model'),
                'gateway.atlas-plates' => __('Atlas-Ortskarten', 'iss-content-model'),
            ],
            'icon' => 'screenoptions', 'tone' => 'navigation', 'group' => 'Orientierung', 'schematic' => 'cards',
        ],
        'text_bild_reihe' => [
            'label' => __('Text-Bild-Reihe', 'iss-content-model'),
            'description' => __('Bilder und Texte als zusammengehörige Reihe zeigen.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['treatment', 'items', 'links'],
            'treatments' => [
                'text-bild-reihe.visual' => __('Großformatige Bildreihe', 'iss-content-model'),
                'text-bild-reihe.compact' => __('Kompakte Bildreihe', 'iss-content-model'),
                'text-bild-reihe.chronology' => __('Chronologische Bildreihe', 'iss-content-model'),
                'text-bild-reihe.editorial' => __('Themenspalten', 'iss-content-model'),
                'text-bild-reihe.agenda' => __('Programm / Chronik', 'iss-content-model'),
            ],
            'icon' => 'format-gallery', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'cards',
        ],
        'map_img' => [
            'label' => __('Karte & Bild', 'iss-content-model'),
            'description' => __('Eine Karte mit Ortsbild und Erläuterung verbinden.', 'iss-content-model'),
            'icon' => 'location-alt', 'tone' => 'navigation', 'group' => 'Orientierung', 'schematic' => 'split',
        ],
        'feature' => [
            'label' => __('Bild & Text', 'iss-content-model'),
            'description' => __('Ein Thema mit Bild, Text und Kernfakten hervorheben.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['treatment', 'lead', 'facts', 'links', 'media_refs', 'media_layout'],
            'treatments' => [
                'feature.media-panel' => __('Bild mit Infokasten', 'iss-content-model'),
                'feature.media-text' => __('Bild neben Text', 'iss-content-model'),
                'feature.image-overlay' => __('Titel auf Bild', 'iss-content-model'),
                'feature.origin-story' => __('Zweiteilige Herkunftserzählung', 'iss-content-model'),
            ],
            'icon' => 'format-image', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'panel',
            'treatment_aliases' => ['feature.microblocks' => 'feature.image-overlay'],
        ],
        'dynamic_slot' => [
            'label' => __('Automatische Inhalte', 'iss-content-model'),
            'description' => __('Inhalte aus den zuständigen Datenquellen anzeigen.', 'iss-content-model'),
            'icon' => 'update', 'tone' => 'automatic', 'group' => 'Automatisch', 'schematic' => 'list',
        ],
        'atlas_map' => [
            'label' => __('Atlas-Karte', 'iss-content-model'),
            'description' => __('Orte und räumliche Zusammenhänge zeigen.', 'iss-content-model'),
            'icon' => 'location-alt', 'tone' => 'navigation', 'group' => 'Orientierung', 'schematic' => 'panel',
        ],
        'bildbuehne' => [
            'label' => __('Bildbühne', 'iss-content-model'),
            'description' => __('Ein großes Bild als Auftakt einer Führung einsetzen.', 'iss-content-model'),
            'icon' => 'format-image', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'opening',
        ],
        'epoche' => [
            'label' => __('Epoche', 'iss-content-model'),
            'description' => __('Einen Zeitabschnitt in der Geschichte des Ortes beschreiben.', 'iss-content-model'),
            'icon' => 'backup', 'tone' => 'text', 'group' => 'Geschichte', 'schematic' => 'split',
        ],
        'gegenwart' => [
            'label' => __('Gegenwart', 'iss-content-model'),
            'description' => __('Die heutige Situation des Ortes beschreiben.', 'iss-content-model'),
            'icon' => 'location', 'tone' => 'text', 'group' => 'Geschichte', 'schematic' => 'text',
        ],
        'source' => [
            'label' => __('Quelle & Rechte', 'iss-content-model'),
            'description' => __('Quellen, Rechte und redaktionelle Hinweise nennen.', 'iss-content-model'),
            'icon' => 'media-document', 'tone' => 'text', 'group' => 'Material', 'schematic' => 'text',
        ],
        'publication_rail' => [
            'label' => __('Lesenavigation', 'iss-content-model'),
            'description' => __('Eine Navigation aus den Abschnitten der Publikation erzeugen.', 'iss-content-model'),
            'icon' => 'menu', 'tone' => 'automatic', 'group' => 'Orientierung', 'schematic' => 'list',
        ],
        'longread_chapter' => [
            'label' => __('Lesekapitel', 'iss-content-model'),
            'description' => __('Ein Publikationskapitel mit Nummerierung und Lesenavigation.', 'iss-content-model'),
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'split',
        ],
        'timeline_item' => [
            'label' => __('Zeitleisten-Station', 'iss-content-model'),
            'description' => __('Einen Zeitpunkt mit Text und Bild erläutern.', 'iss-content-model'),
            'icon' => 'calendar-alt', 'tone' => 'text', 'group' => 'Geschichte', 'schematic' => 'list',
        ],
        'photoalbum' => [
            'label' => __('Fotoalbum', 'iss-content-model'),
            'description' => __('Eine Bildfolge aus einem Archivset oder redaktionellen Set zusammenstellen.', 'iss-content-model'),
            'icon' => 'format-gallery', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'cards',
        ],
    ];
    // Saved identifiers remain editable; new questions use the statement treatment.
    $gestures['leitfrage'] = array_merge($gestures['statement'], [
        'description' => __('Die zentrale Frage oder These hervorheben.', 'iss-content-model'),
        'ui_hidden' => true,
        'family' => 'statement',
        'default_treatment' => 'statement.leitfrage',
    ]);
    // Preserve saved publication identifiers while offering one quote in the chooser.
    $gestures['longread_quote'] = array_merge($gestures['zitat'], ['ui_hidden' => true, 'family' => 'zitat']);
    return $gestures;
}

function iss_content_model_editorial_skin_features(): array
{
    return [
        'typografisch' => [
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'dossier' => [
            'features' => [
                'rail' => [
                    'enabled' => true,
                    'placement' => 'left',
                    'mode' => 'anchor-nav',
                    'treatment' => 'sticky',
                ],
            ],
        ],
        'quellenbuehne' => [
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'objektalbum' => [
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'bildmatrix' => [
            'features' => [
                'rail' => [
                    'enabled' => true,
                    'placement' => 'top',
                    'mode' => 'section-index',
                    'treatment' => 'line',
                ],
            ],
        ],
        'buehne' => [
            'features' => [
                'rail' => ['enabled' => false],
            ],
        ],
        'chronik' => [
            'features' => [
                'rail' => [
                    'enabled' => true,
                    'placement' => 'right',
                    'mode' => 'section-index',
                    'treatment' => 'line',
                ],
            ],
        ],
    ];
}

// Data owners contribute feature defaults to the same theme-owned skin choices.
add_filter('iss_editorial_formats', static function (array $formats): array {
    $features = iss_content_model_editorial_skin_features();
    foreach ($formats as &$format) {
        foreach (($format['skins'] ?? []) as $slug => $choice) {
            $choice = is_array($choice) ? $choice : ['label' => $choice];
            $choice['features'] = array_replace_recursive($features[$slug]['features'] ?? [], $choice['features'] ?? []);
            $format['skins'][$slug] = $choice;
        }
    }
    unset($format);
    return $formats;
}, 110);

function iss_content_model_editorial_resolve_rail_feature(string $skin, array $overrides = []): array
{
    $canonical = sanitize_key($skin);
    $defaults = iss_content_model_editorial_skin_features()[$canonical]['features']['rail'] ?? ['enabled' => false];
    $formats = function_exists('iss_editorial_get_registered_formats') ? iss_editorial_get_registered_formats() : [];
    foreach ($formats as $format) {
        if (isset($format['skins'][$canonical]['features']['rail'])) {
            $defaults = $format['skins'][$canonical]['features']['rail'];
            break;
        }
    }

    $feature = array_merge($defaults, $overrides);
    $feature['enabled'] = !empty($feature['enabled']);

    $placement = sanitize_key((string) ($feature['placement'] ?? ''));
    $feature['placement'] = in_array($placement, ['left', 'right', 'top', 'bottom', 'horizontal'], true) ? $placement : 'right';

    $mode = sanitize_key((string) ($feature['mode'] ?? ''));
    $feature['mode'] = in_array($mode, ['anchor-nav', 'section-index', 'contextual'], true) ? $mode : 'anchor-nav';

    $treatment = sanitize_key((string) ($feature['treatment'] ?? ''));
    $feature['treatment'] = in_array($treatment, ['quiet', 'card', 'line', 'sticky', 'overlay'], true) ? $treatment : 'quiet';

    return $feature;
}
