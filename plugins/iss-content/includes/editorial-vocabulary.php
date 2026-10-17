<?php

if (!defined('ABSPATH')) {
    exit;
}

function iss_content_model_editorial_gesture_registry(): array
{
    $inline_media_layout = [
        'choices' => ['inline' => __('Im Text', 'iss-content-model'), 'aside-right' => __('Rechts daneben', 'iss-content-model')],
        'default' => 'inline',
    ];
    $gestures = [
        'intro' => [
            'label' => __('Einleitung', 'iss-content-model'),
            'description' => __('Den Inhalt kurz einordnen.', 'iss-content-model'),
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'heading',
        ],
        'kapitel' => [
            'label' => __('Kapitel', 'iss-content-model'),
            'description' => __('Ein Thema mit Überschrift und Text vertiefen.', 'iss-content-model'),
            'help' => [
                __('Für ein abgegrenztes Thema mit eigener Überschrift. Unter „Darstellung“ kannst du es auch als Hintergrund oder aufklappbaren Abschnitt zeigen.', 'iss-content-model'),
                __('Schreibe zuerst Titel und Text. Ergänze Bilder und Links nur, wenn sie das Thema erklären. Prüfe aufgeklappte Inhalte in der Vorschau.', 'iss-content-model'),
            ],
            'portable' => true,
            'supports' => ['anchor', 'links', 'media_refs', 'media_layout', 'section_treatment', 'treatment'],
            'legacy_treatment_field' => 'section_treatment',
            'default_treatment' => 'kapitel.standard',
            'treatments' => [
                'kapitel.standard' => ['label' => __('Kapitel', 'iss-content-model'), 'legacy_value' => 'standard', 'hint' => __('Überschrift, Text und ergänzende Bilder.', 'iss-content-model')],
                'kapitel.aside' => ['label' => __('Hintergrund / Exkurs', 'iss-content-model'), 'legacy_value' => 'aside', 'hint' => __('Eine ergänzende Stimme sichtbar absetzen.', 'iss-content-model')],
                'kapitel.disclosure' => ['label' => __('Aufklappbar', 'iss-content-model'), 'legacy_value' => 'disclosure', 'hint' => __('Details erst auf Nachfrage öffnen.', 'iss-content-model')],
            ],
            'media_layout' => $inline_media_layout,
            'icon' => 'editor-paragraph', 'tone' => 'text', 'group' => 'Text', 'schematic' => 'text',
        ],
        'fliesstext' => [
            'label' => __('Fließtext', 'iss-content-model'),
            'description' => __('Ein zusammenhängender Textabschnitt.', 'iss-content-model'),
            'help' => [
                __('Für zusammenhängende Erzählungen. Gliedere längere Texte in kurze Absätze; Überschrift und Einleitung sollen den Einstieg erleichtern.', 'iss-content-model'),
                __('Unter „Darstellung“ bestimmt die Variante, ob Titel und Erzählung nebeneinander stehen. Prüfe auch die Telefon-Vorschau: dort werden Spalten untereinander angeordnet.', 'iss-content-model'),
            ],
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
            'supports' => ['anchor', 'quote', 'object_refs', 'media_refs', 'orientation', 'quote_treatment', 'treatment'],
            'legacy_treatment_field' => 'quote_treatment',
            'default_treatment' => 'zitat.pull',
            'treatments' => [
                'zitat.pull' => ['label' => __('Zitat', 'iss-content-model'), 'legacy_value' => 'pull', 'hint' => __('Eine Stimme mit Zuordnung hervorheben.', 'iss-content-model')],
                'zitat.source' => ['label' => __('Quellenauszug', 'iss-content-model'), 'legacy_value' => 'source', 'schematic' => 'split', 'hint' => __('Zitat und Quelle nebeneinander lesen.', 'iss-content-model')],
            ],
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
            'supports' => ['anchor', 'media_refs', 'object_refs', 'gallery_layout', 'treatment'],
            'legacy_treatment_field' => 'gallery_layout',
            'default_treatment' => 'galerie.grid',
            'treatments' => [
                'galerie.grid' => ['label' => __('Raster', 'iss-content-model'), 'legacy_value' => 'grid', 'hint' => __('Bilder gleichwertig nebeneinander zeigen.', 'iss-content-model')],
                'galerie.sequence' => ['label' => __('Strecke', 'iss-content-model'), 'legacy_value' => 'sequence', 'hint' => __('Eine geordnete Bildfolge zum Weiterblättern.', 'iss-content-model')],
                'galerie.wall' => ['label' => __('Bilderwand', 'iss-content-model'), 'legacy_value' => 'wall', 'hint' => __('Eine dichte Zusammenstellung von Bildern.', 'iss-content-model')],
                'galerie.viewport' => ['label' => __('Vollbild', 'iss-content-model'), 'legacy_value' => 'viewport', 'hint' => __('Ein Bild groß zeigen; weitere Bilder bleiben gespeichert.', 'iss-content-model')],
            ],
            'icon' => 'format-gallery', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'cards',
            'legacy_types' => ['image_wall' => ['gallery_layout' => 'wall'], 'vollbild' => ['gallery_layout' => 'viewport']],
        ],
        'objektfokus' => [
            'label' => __('Archivobjekte', 'iss-content-model'),
            'editor' => ['reference_captions' => true],
            'description' => __('Ausgewählte Archivobjekte im Zusammenhang zeigen.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'object_refs'],
            'icon' => 'archive', 'tone' => 'media', 'group' => 'Material', 'schematic' => 'cards',
        ],
        'material' => [
            'label' => __('Begleitende Dateien', 'iss-content-model'),
            'editor' => ['media_kind' => 'files', 'media_hint' => __('Dateien werden als Downloads angezeigt. Für eine Bildansicht bitte Galerie verwenden.', 'iss-content-model')],
            'description' => __('Dateien zum Herunterladen und weiterführende Links anbieten.', 'iss-content-model'),
            'portable' => true,
            'supports' => ['anchor', 'media_refs', 'links'],
            'icon' => 'media-document', 'tone' => 'media', 'group' => 'Material', 'schematic' => 'list',
        ],
        'facts' => [
            'label' => __('Merkpunkte', 'iss-content-model'),
            'description' => __('Fakten und Zahlen übersichtlich zusammenstellen.', 'iss-content-model'),
            'help' => [
                __('Jeder Merkpunkt besteht aus „Wert“ und „Beschreibung“: zum Beispiel „2027“ und „Geplantes Festivaljahr“. Halte beide Angaben kurz.', 'iss-content-model'),
                __('Mit „Fakt hinzufügen“ ergänzt du einen Eintrag. „Entfernen“ löscht nur diesen Eintrag und bietet keinen Abschnitt-Papierkorb.', 'iss-content-model'),
            ],
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
            'help' => [
                __('Für eine zentrale Aussage, eine Leitfrage oder einen Handlungsaufruf. Schreibe eine kurze Überschrift und bei Bedarf einen erklärenden Text.', 'iss-content-model'),
                __('Wähle unter „Darstellung“ den passenden Schwerpunkt. Soll der Aufruf zu einer anderen Seite führen, ergänze unter „Links“ ein verständlich benanntes Ziel.', 'iss-content-model'),
            ],
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
            'supports' => ['treatment', 'items', 'links', 'image_fit'],
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
            'help' => [
                __('Unter „Inhalt“ ergänzt du Bild-Text-Paare mit Beschriftung, Text und optional einem Bild. Die Reihenfolge der Einträge bestimmt die Lesereihenfolge.', 'iss-content-model'),
                __('„Themenspalten“ eignet sich für parallele Themen; „Programm / Chronik“ für einen Ablauf. Unter „Vorspann und Details“ ergänzen Zeitraum, Filtergruppe und „Mehr erfahren“ die Programmeinträge.', 'iss-content-model'),
                __('Eine andere Darstellung ordnet die vorhandenen Inhalte neu. Prüfe danach Bilder, Textlängen und die Telefon-Vorschau.', 'iss-content-model'),
            ],
            'portable' => true,
            'supports' => ['treatment', 'items', 'links'],
            'items_kind' => 'text_image',
            'items_label' => __('Bild-Text-Paare', 'iss-content-model'),
            'treatments' => [
                'text-bild-reihe.visual' => __('Großformatige Bildreihe', 'iss-content-model'),
                'text-bild-reihe.compact' => __('Kompakte Bildreihe', 'iss-content-model'),
                'text-bild-reihe.chronology' => __('Chronologische Bildreihe', 'iss-content-model'),
                'text-bild-reihe.editorial' => ['label' => __('Themenspalten', 'iss-content-model'), 'item_fields' => ['eyebrow']],
                'text-bild-reihe.agenda' => ['label' => __('Programm / Chronik', 'iss-content-model'), 'item_fields' => ['eyebrow', 'group', 'detail']],
            ],
            'icon' => 'format-gallery', 'tone' => 'media', 'group' => 'Bild', 'schematic' => 'cards',
        ],
        'map_img' => [
            'label' => __('Karte & Bild', 'iss-content-model'),
            'description' => __('Eine Karte mit Ortsbild und Erläuterung verbinden.', 'iss-content-model'),
            'items_kind' => 'text_image',
            'icon' => 'location-alt', 'tone' => 'navigation', 'group' => 'Orientierung', 'schematic' => 'split',
        ],
        'feature' => [
            'label' => __('Bild & Text', 'iss-content-model'),
            'description' => __('Ein Thema mit Bild, Text und Kernfakten hervorheben.', 'iss-content-model'),
            'help' => [
                __('Für einen hervorgehobenen Einstieg mit Bild, Überschrift und Text. Wähle unter „Medien“ ein Bild und prüfe dessen Ausschnitt.', 'iss-content-model'),
                __('Die Darstellung bestimmt das Zusammenspiel von Bild und Text. „Seitenauftakt“ ist für den ersten Abschnitt gedacht und braucht Titel und Bild; bei Projekten gehört dazu die Seitengestaltung „Bühne“.', 'iss-content-model'),
                __('Prüfe bei Text auf einem Bild die Lesbarkeit und die Telefon-Vorschau. Die Bildunterschrift beschreibt das Motiv oder nennt die Quelle.', 'iss-content-model'),
            ],
            'portable' => true,
            'supports' => ['treatment', 'lead', 'facts', 'links', 'media_refs', 'media_layout', 'image_fit'],
            'media_layout' => $inline_media_layout,
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
            'editor' => ['preview_editable' => false],
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
            'media_layout' => $inline_media_layout,
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
