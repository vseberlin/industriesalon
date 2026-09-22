<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_editorial_gesture_registry(): array
{
    return [
        'intro' => [
            'label' => __('Einleitung', 'iss-content-model'),
            'description' => __('Den Inhalt kurz einordnen.', 'iss-content-model'),
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'heading',
        ],
        'kapitel' => [
            'label' => __('Kapitel', 'iss-content-model'),
            'description' => __('Ein Thema mit Überschrift und Text vertiefen.', 'iss-content-model'),
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'text',
        ],
        'fliesstext' => [
            'label' => __('Fließtext', 'iss-content-model'),
            'description' => __('Ein zusammenhängender Textabschnitt.', 'iss-content-model'),
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'text',
        ],
        'leitfrage' => [
            'label' => __('Leitfrage', 'iss-content-model'),
            'description' => __('Die zentrale Frage oder These hervorheben.', 'iss-content-model'),
            'icon' => 'editor-help', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'heading',
        ],
        'zitat' => [
            'label' => __('Zitat', 'iss-content-model'),
            'description' => __('Ein Zitat mit Quellen- oder Personenangabe.', 'iss-content-model'),
            'icon' => 'format-quote', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'callout',
        ],
        'schluss' => [
            'label' => __('Abschluss', 'iss-content-model'),
            'description' => __('Einordnung, Kontakt oder weiterführende Links.', 'iss-content-model'),
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'callout',
        ],
        'galerie' => [
            'label' => __('Bildergalerie', 'iss-content-model'),
            'description' => __('Bilder mit ihren Beschreibungen zusammenstellen.', 'iss-content-model'),
            'icon' => 'format-gallery', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'cards',
            'legacy_types' => ['image_wall' => ['gallery_layout' => 'wall'], 'vollbild' => ['gallery_layout' => 'viewport']],
        ],
        'objektfokus' => [
            'label' => __('Archivobjekte', 'iss-content-model'),
            'description' => __('Ausgewählte Archivobjekte im Zusammenhang zeigen.', 'iss-content-model'),
            'icon' => 'archive', 'tone' => 'media', 'group' => 'Material', 'schematic' => 'cards',
        ],
        'material' => [
            'label' => __('Begleitende Dateien', 'iss-content-model'),
            'description' => __('Dokumente und weiterführendes Material anbieten.', 'iss-content-model'),
            'icon' => 'media-document', 'tone' => 'media', 'group' => 'Material', 'schematic' => 'list',
        ],
        'facts' => [
            'label' => __('Merkpunkte', 'iss-content-model'),
            'description' => __('Fakten und Zahlen übersichtlich zusammenstellen.', 'iss-content-model'),
            'icon' => 'list-view', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'list',
            'legacy_types' => ['massstab' => []],
        ],
        'programm' => [
            'label' => __('Programm', 'iss-content-model'),
            'description' => __('Programmpunkte und verknüpfte Inhalte zeigen.', 'iss-content-model'),
            'icon' => 'calendar-alt', 'tone' => 'automatic', 'group' => 'Automatisch', 'schematic' => 'list',
        ],
        'upload_intake' => [
            'label' => __('Material beitragen', 'iss-content-model'),
            'description' => __('Gäste zur Einreichung von Material für die redaktionelle Prüfung einladen.', 'iss-content-model'),
            'icon' => 'upload', 'tone' => 'automatic', 'group' => 'Material', 'schematic' => 'callout',
        ],
        'statement' => [
            'label' => __('Überschrift & Einleitung', 'iss-content-model'),
            'description' => __('Eine Überschrift, Einleitung oder Leitfrage hervorheben.', 'iss-content-model'),
            'icon' => 'editor-textcolor', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'heading',
        ],
        'gateway' => [
            'label' => __('Einstiegs-Boxen', 'iss-content-model'),
            'description' => __('Verlinkte Einstiege zu weiteren Inhalten zusammenstellen.', 'iss-content-model'),
            'icon' => 'screenoptions', 'tone' => 'navigation', 'group' => 'Orientierung', 'schematic' => 'cards',
        ],
        'text_bild_reihe' => [
            'label' => __('Text-Bild-Reihe', 'iss-content-model'),
            'description' => __('Bilder und Texte als zusammengehörige Reihe zeigen.', 'iss-content-model'),
            'icon' => 'format-gallery', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'cards',
        ],
        'map_img' => [
            'label' => __('Karte & Bild', 'iss-content-model'),
            'description' => __('Eine Karte mit Ortsbild und Erläuterung verbinden.', 'iss-content-model'),
            'icon' => 'location-alt', 'tone' => 'navigation', 'group' => 'Orientierung', 'schematic' => 'split',
        ],
        'feature' => [
            'label' => __('Hervorgehobener Inhalt', 'iss-content-model'),
            'description' => __('Ein Thema mit Bild, Text und Kernfakten hervorheben.', 'iss-content-model'),
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
            'label' => __('Kapitel', 'iss-content-model'),
            'description' => __('Ein Kapitel mit Text und begleitenden Bildern.', 'iss-content-model'),
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'split',
        ],
        'longread_quote' => [
            'label' => __('Zitat', 'iss-content-model'),
            'description' => __('Ein eigenständiges Zitat mit Quellenangabe.', 'iss-content-model'),
            'icon' => 'format-quote', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'callout',
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
