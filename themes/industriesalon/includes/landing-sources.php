<?php
/** Named compositions of existing domain blocks, contributed to the shared registry. */
if (!defined('ABSPATH')) { exit; }

add_filter('iss_editorial_formats', static function (array $formats): array {
    if (!isset($formats['landing'])) { return $formats; }
    $number = static fn(string $label, int $default, int $max = 48): array => ['label' => $label, 'type' => 'integer', 'default' => $default, 'min' => 1, 'max' => $max];
    $text = static fn(string $label, string $default = ''): array => ['label' => $label, 'type' => 'string', 'default' => $default];
    $choice = static fn(string $label, string $default, array $choices): array => ['label' => $label, 'type' => 'string', 'default' => $default, 'choices' => $choices];
    $types = $choice('Inhalte', 'all', ['all' => 'Gesamtes Programm', 'events' => 'Veranstaltungen und Ausstellungen', 'tours' => 'Führungen']);
    $time = $choice('Zeitraum', 'upcoming', ['upcoming' => 'Kommende Termine', 'month' => 'Diesen Monat', 'past' => 'Vergangene Termine']);
    $sources = [
        'programme' => ['label' => 'Programm', 'block' => 'industriesalon/program-cards', 'defaults' => ['presentation' => 'programme', 'includeRunningRanges' => true], 'settings' => ['limit' => $number('Anzahl', 3), 'timeMode' => $time, 'content_types' => $types]],
        'calendar' => ['label' => 'Kalender', 'block' => 'industriesalon/timeline-query', 'defaults' => ['shellMode' => 'body', 'defaultType' => 'all', 'includeRunningRanges' => true, 'showTimeModeFilter' => false, 'showItemTypeFilter' => true, 'showTypeFilter' => true, 'showCalendarBridge' => true, 'yearGrouping' => true, 'groupRecurringTours' => true, 'groupRecurringToursByMonth' => true, 'expandGroupedOccurrences' => false, 'showLoadMore' => true, 'showRecommendButton' => false, 'showBottomButton' => true, 'detailsButtonText' => 'Details', 'loadMoreText' => 'Weitere Termine laden', 'ticketsButtonText' => 'Buchen', 'className' => 'is-style-programme', 'presetButtons' => [['label' => 'Heute', 'rangePreset' => 'today'], ['label' => 'Diese Woche', 'rangePreset' => 'week'], ['label' => 'Diesen Monat', 'rangePreset' => 'month', 'isDefault' => true], ['label' => 'Alle kommenden', 'timeMode' => 'upcoming'], ['label' => 'Archiv', 'timeMode' => 'past']]], 'settings' => ['limit' => $number('Anzahl pro Seite', 12), 'timeMode' => array_replace($time, ['default' => 'month']), 'content_types' => $types]],
        'past-events' => ['label' => 'Vergangene Veranstaltungen', 'block' => 'industriesalon/timeline-query', 'defaults' => ['shellMode' => 'body', 'timeMode' => 'past', 'defaultType' => 'veranstaltungen', 'postTypesList' => ['veranstaltung'], 'allowedTypesList' => ['veranstaltungen'], 'showTimeModeFilter' => false, 'showTypeFilter' => false, 'showItemTypeFilter' => false, 'showMonthFilter' => false, 'showCalendarBridge' => false, 'showRecommendButton' => false, 'showTicketsButton' => false, 'yearGrouping' => true, 'showMeta' => false, 'showLoadMore' => true, 'detailsButtonText' => 'Details'], 'settings' => ['limit' => $number('Anzahl pro Seite', 6)]],
        'exhibitions' => ['label' => 'Ausstellungen', 'block' => 'industriesalon/ausstellungen-browser', 'defaults' => ['showMeta' => true, 'detailsButtonText' => 'Mehr', 'className' => 'iss-card-skin--exhibition'], 'settings' => ['limit' => $number('Anzahl', 24, 24), 'defaultFilter' => $choice('Erste Auswahl', 'aktuell', (function_exists('iss_programm_ausstellungen_filter_options') ? iss_programm_ausstellungen_filter_options() : ['aktuell' => 'Aktuell']))]],
        'publications' => ['label' => 'Publikationsbestand', 'pattern' => 'landing-publications-browser'],
        'featured-publication' => ['label' => 'Hervorgehobene Publikation', 'block' => 'iss/featured-publication'],
        'archive' => ['label' => 'Archivsuche', 'block' => 'iss-wf-import/archive-object-browser', 'defaults' => ['showHero' => false, 'showStats' => false, 'showSourceFilter' => false, 'showContextFilter' => false, 'showSourceCards' => false, 'useCurrentUrl' => true, 'quickKicker' => 'Bereiche', 'quickTitle' => 'Im Bestand beginnen', 'discoveryTitle' => 'Weiter entdecken', 'discoveryLinks' => [['label' => 'Publikationen', 'url' => '/publikationen/'], ['label' => 'Videos', 'url' => '/videos/'], ['label' => 'Führungen', 'url' => '/fuehrungen/'], ['label' => 'Sammlungen', 'url' => '/sammlungen/'], ['label' => 'Ausstellungen', 'url' => '/ausstellungen/'], ['label' => 'Veranstaltungen', 'url' => '/veranstaltungen/']]]],
        'videos' => ['label' => 'Videothek mit Player, Themen und Reihen', 'block' => 'iss/video-library', 'defaults' => ['ariaLabel' => 'Thematische Einstiege', 'externalLabel' => 'Externe Berichte'], 'settings' => ['externalKicker' => $text('Vorspann externe Berichte', 'Externe Blicke'), 'externalTitle' => $text('Überschrift externe Berichte', 'Presse und Berichte über Schöneweide'), 'externalText' => $text('Einleitung externe Berichte', 'Diese Videos gehören nicht zum eigenen Bestand, helfen aber dabei, Außenwahrnehmung, Berichterstattung und Kontext sichtbar zu machen.')]],
        'notice' => ['label' => 'Aktueller Hinweis', 'block' => 'industriesalon/notice-banner', 'settings' => ['area' => $choice('Hinweisbereich', 'selected_pages_banner', ['selected_pages_banner' => 'Ausgewählte Seiten', 'ausstellungen_banner' => 'Ausstellungen', 'front_page_banner' => 'Startseite'])]],
        'institution-field' => ['label' => 'Zentrale Kontaktangabe', 'block' => 'industriesalon/field', 'settings' => ['key' => $choice('Angabe', 'contact.email', ['general.site_name' => 'Name', 'address.full' => 'Adresse', 'contact.email' => 'E-Mail', 'contact.phone' => 'Telefon']), 'linkMode' => $choice('Verlinkung', 'none', ['none' => 'Ohne Link', 'email' => 'E-Mail', 'tel' => 'Telefon']), 'tagName' => $choice('Ausgabe', 'p', ['p' => 'Absatz', 'a' => 'Aktionslink']), 'label' => $text('Beschriftung'), 'text' => $text('Linktext'), 'subject' => $text('E-Mail-Betreff')]],
        'visit-info' => ['label' => 'Besuchsinfo mit Optionen', 'block' => 'industriesalon/visit-info', 'defaults' => ['shellMode' => 'body'], 'settings' => ['kicker' => $text('Vorspann', 'Vor Ort'), 'title' => $text('Überschrift', 'Besuch im Industriesalon'), 'intro' => $text('Einleitung'), 'show_office_hours' => ['label' => 'Bürozeiten zeigen', 'type' => 'boolean', 'default' => true], 'show_admission' => ['label' => 'Eintritt zeigen', 'type' => 'boolean', 'default' => true]]],
        'related-publications' => ['label' => 'Ausgewählte Publikationen', 'block' => 'iss/related-cards', 'defaults' => ['postType' => 'publication', 'postTypes' => ['publication'], 'source' => 'manual', 'layoutVariant' => 'strip', 'sortMode' => 'relevance_date', 'columns' => 4], 'settings' => ['perPage' => $number('Anzahl', 4, 12), 'placeIds' => $text('Publikations-IDs (durch Komma getrennt)')]],
        'project-featured' => ['label' => 'Hervorgehobene Projekte', 'pattern' => 'landing-project-featured'],
        'project-index' => ['label' => 'Weitere Projekte', 'pattern' => 'landing-project-index'],
        'newsletter-form' => ['label' => 'Newsletter-Anmeldung', 'block' => 'iss/newsletter-form'],
        'newsletter-page' => ['label' => 'Newsletter: Anmeldung und Abonnement verwalten', 'renderer' => 'newsletter-page'],
    ];
    $section =& $formats['landing']['sections']['dynamic_slot'];
    $section['supports'][] = 'slot_settings';
    foreach ($sources as $key => $source) {
        $source['treatment'] = 'slot.' . $key;
        $section['slots'][$key] = $source;
        if (!isset($section['treatments'][$source['treatment']])) {
            $section['treatments'][$source['treatment']] = ['label' => $source['label'], 'schematic' => 'panel', 'hint' => 'Inhalte kommen aus dem bestehenden Fachbereich. Auswahl und Umfang können hier angepasst werden.'];
        }
    }
    $section['treatments']['slot.notice']['hint'] = 'Direkt nach einem Bild-Seitenauftakt erscheint der Hinweis im Hero. An anderer Stelle bleibt er ein eigener Abschnitt. Inhalt und Sichtbarkeit werden unter Hinweisbanner gepflegt.';
    $section['slots']['front-projects']['settings'] = ['limit' => $number('Anzahl Projekte', 2, 24)];
    $section['slots']['front-timeline']['settings'] = ['limit' => $number('Anzahl Termine', 4), 'timeMode' => $time, 'content_types' => $types];
    $formats['landing']['starters'] = [
        ['label' => 'Seitenauftakt und Überblick', 'sections' => [['type' => 'feature', 'treatment' => 'feature.opening-text', 'title' => 'Neue Seite'], ['type' => 'gateway']]],
        ['label' => 'Seitenauftakt, Kalender und Besuchsinfo', 'sections' => [['type' => 'feature', 'treatment' => 'feature.opening-text', 'title' => 'Programm und Besuch'], ['type' => 'dynamic_slot', 'slot_key' => 'calendar', 'treatment' => 'slot.calendar'], ['type' => 'dynamic_slot', 'slot_key' => 'front-visit-info', 'treatment' => 'slot.visit-info']]],
    ];
    return $formats;
}, 30);

add_action('init', static function (): void {
    foreach (['landing-publications-browser', 'landing-project-featured', 'landing-project-index'] as $name) {
        register_block_pattern('industriesalon/' . $name, ['title' => $name, 'inserter' => false, 'content' => (string) file_get_contents(get_theme_file_path('/patterns/' . $name . '.html'))]);
    }
});

function industriesalon_landing_source_options(array $section): array
{
    $key = $section['slot_key'] ?? '';
    $source = iss_editorial_get_format('landing')['sections']['dynamic_slot']['slots'][$key] ?? [];
    $options = (array) ($source['defaults'] ?? []);
    foreach ($source['settings'] ?? [] as $field => $rule) {
        $options[$field] = $section['slot_settings'][$key][$field] ?? $rule['default'];
    }
    if (isset($options['content_types'])) {
        $types = ['all' => ['fuehrung', 'veranstaltung', 'ausstellung'], 'events' => ['veranstaltung', 'ausstellung'], 'tours' => ['fuehrung']];
        $options['postTypesList'] = $types[$options['content_types']] ?? $types['all'];
        $plurals = ['fuehrung' => 'fuehrungen', 'veranstaltung' => 'veranstaltungen', 'ausstellung' => 'ausstellungen'];
        $options['allowedTypesList'] = array_map(static fn($type) => $plurals[$type], $options['postTypesList']);
        unset($options['content_types']);
    }
    if (isset($options['subject'])) {
        if ($options['subject'] !== '') { $options['hrefSuffix'] = '?subject=' . rawurlencode($options['subject']); }
        unset($options['subject']);
    }
    return $options;
}

/** Conditional assets follow the selected sources, including private previews. */
function industriesalon_landing_uses_source(array $keys): bool
{
    $id = (int) get_queried_object_id();
    if (!$id || !industriesalon_editorial_landing_is_enabled($id)) { return false; }
    $document = iss_editorial_get_read_model($id, 'landing', industriesalon_editorial_landing_prefer_autosave($id));
    return (bool) array_intersect($keys, array_column($document['sections'] ?? [], 'slot_key'));
}

function industriesalon_render_landing_source(array $section): string
{
    $source = iss_editorial_get_format('landing')['sections']['dynamic_slot']['slots'][$section['slot_key'] ?? ''] ?? [];
    // The newsletter plugin owns confirmation, profile and unsubscribe routes too.
    if (($source['renderer'] ?? '') === 'newsletter-page') { return do_shortcode('[newsletter]'); }
    if (!empty($source['pattern'])) {
        $file = get_theme_file_path('/patterns/' . $source['pattern'] . '.html');
        $html = is_readable($file) ? do_blocks((string) file_get_contents($file)) : '';
    } else {
        if (empty($source['block']) || !WP_Block_Type_Registry::get_instance()->is_registered($source['block'])) { return ''; }
        $block = ['blockName' => $source['block'], 'attrs' => industriesalon_landing_source_options($section), 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
        $html = render_block($block);
    }
    $wrappers = ['videos' => 'iss-videos-page', 'project-featured' => 'iss-projects-page', 'project-index' => 'iss-projects-page', 'publications' => 'iss-publications-page', 'featured-publication' => 'iss-publications-page'];
    if (isset($wrappers[$section['slot_key']])) { $html = '<div class="' . esc_attr($wrappers[$section['slot_key']]) . '">' . $html . '</div>'; }
    return $section['slot_key'] === 'visit-info' ? '<div class="iss-visit-info iss-visit-info--info-panel">' . $html . '</div>' : $html;
}
