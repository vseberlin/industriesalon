<?php
/**
 * Apply the /schoneweide/ territorial landing document and canonical place relations.
 *
 * Run after the matching code deployment:
 * wp eval-file ops/migrations/2026-07-11-schoneweide-landing.php --allow-root
 *
 * Rollback source: wp_iss_backup_20260711_schoneweide_landing_meta.
 * Restore its rows to wp_postmeta, then run:
 * wp eval 'iss_relations_sync_post_read_models(13251);' --allow-root
 */

if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit(1);
}

$post_id = 13251;
$post = get_post($post_id);
if (!$post instanceof WP_Post || $post->post_type !== 'page' || $post->post_name !== 'schoneweide') {
    WP_CLI::error('Expected page 13251 with slug schoneweide.');
}

$required_functions = [
    'iss_editorial_save_document',
    'iss_editorial_set_document_enabled',
    'iss_relations_save_post_relations',
    'iss_relations_sync_post_read_models',
];
foreach ($required_functions as $function_name) {
    if (!function_exists($function_name)) {
        WP_CLI::error('Missing required function: ' . $function_name);
    }
}

$media_ids = [13266, 13329, 18849, 25246, 18855, 25254, 24683, 25235, 25243, 25261, 25265, 25269, 25267, 2182, 25271, 25273, 5348, 7569, 13146];
foreach ($media_ids as $media_id) {
    if (get_post_type($media_id) !== 'attachment' || !wp_attachment_is_image($media_id)) {
        WP_CLI::error('Expected image attachment ' . $media_id . '.');
    }
}

$place_ids = [17976, 17960, 12865, 12870];
foreach ($place_ids as $place_id) {
    if (get_post_type($place_id) !== 'register_place') {
        WP_CLI::error('Expected register_place ' . $place_id . '.');
    }
}

global $wpdb;
$backup_table = $wpdb->prefix . 'iss_backup_20260711_schoneweide_landing_meta';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $backup_table)) === $backup_table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration guard.
    WP_CLI::error('Backup table already exists: ' . $backup_table);
}

$meta_keys = [
    '_iss_editorial_landing',
    '_iss_editorial_enabled_landing',
    '_iss_editorial_landing_skin',
    '_iss_editorial_landing_autosave',
    'iss_related_places',
];
$quoted_keys = implode(', ', array_map(static function (string $key): string {
    return "'" . esc_sql($key) . "'";
}, $meta_keys));
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- One-off migration backup with prefix-derived escaped identifiers.
$backup_sql = sprintf(
    'CREATE TABLE `%s` AS SELECT * FROM `%s` WHERE post_id = %d AND meta_key IN (%s)',
    esc_sql($backup_table),
    esc_sql($wpdb->postmeta),
    $post_id,
    $quoted_keys
);
if ($wpdb->query($backup_sql) === false) {
    WP_CLI::error('Could not create targeted postmeta backup.');
}
// phpcs:enable

$document_json = <<<'JSON'
{
  "schema_version": 1,
  "skin": "territorial",
  "variant": "standard",
  "features": [],
  "sections": [
    {
      "type": "fliesstext",
      "kicker": "Woran wir arbeiten",
      "title": "Schöneweide als lesbare Stadtlandschaft",
      "body": "<p><strong>Wir lesen Oberschöneweide nicht als lose Ansammlung einzelner Gebäude, sondern als zusammenhängende industrielle Stadtlandschaft zwischen Spree, Werkstoren, Bahntrassen und neuen Nutzungen.</strong></p><p>Über Jahrzehnte entstand hier ein Produktionsraum, dessen Strukturen, Übergänge und Spuren bis heute ablesbar geblieben sind.</p><p>Von dort führen die Wege weiter in Rundgänge, Sammlung, Archiv und die einzelnen Dossiers der Orte. Geschichte bleibt an reale Gebäude, Übergänge und Produktionsspuren gebunden.</p><p>Der Atlas bleibt das operative Werkzeug. Diese Landeseite setzt davor einen ruhigen, kuratierten Rahmen für die Öffentlichkeit.</p><p><strong>Arbeitslogik:</strong> Oben steht die Orientierung, darunter der operative Atlas. Dossiers, Rundgänge und Vor-Ort-Angebote vertiefen die einzelnen Ebenen der Industrielandschaft.</p>",
      "anchor": "stadtlandschaft",
      "treatment": "text.story-split",
      "links": []
    },
    {
      "type": "map_img",
      "kicker": "Topografie",
      "title": "Werkorte bilden das räumliche Gerüst von Schöneweide",
      "body": "<p>Behrensbau, Industriesalon, KWO und die Wasserkante sind keine neutralen Kulissen. Sie strukturieren, wie sich Produktionsgeschichte, Umbau und heutige Stadträume in Oberschöneweide lesen lassen.</p>",
      "lead": "<p><strong>Räumlicher Rahmen</strong></p><p>Die wichtigsten Lektüren verlaufen zwischen Behrensbau, Industriesalon, KWO und den Uferzonen der Spree.</p><p>Die Karte dient als Lagebild für Orte, Zeitschichten und spätere Vertiefungen auf derselben Seite.</p>",
      "anchor": "topografie",
      "treatment": "map-img.editorial-atlas",
      "media_refs": [{"kind":"media","source":"wp-media","id":"13266","label":"Werkorte, Spree und Hallen bleiben das räumliche Gerüst von Schöneweide.","thumbnail":""}],
      "items": [
        {"label":"Behrensbau","text":"Ostendstraße 1-5 · AEG / Verwaltung. Architektur, Repräsentation und Werkorganisation bilden hier einen frühen Deutungsanker für den ganzen Ort.","media_refs":[{"kind":"media","source":"wp-media","id":"24683","label":"","thumbnail":""}]},
        {"label":"Industriesalon","text":"Reinbeckstraße · Sammlung / Vermittlung. Arbeitsplätze, Technikreste und Recherchematerial treffen in einer Halle aufeinander, die selbst historisch mitliest.","media_refs":[{"kind":"media","source":"wp-media","id":"25235","label":"","thumbnail":""}]},
        {"label":"Kabelwerk Oberspree","text":"Wilhelminenhofstraße · Kabel / Material. Werkgrenzen, Stoffströme und das materielle Rückgrat des Standorts werden hier besonders konkret.","media_refs":[{"kind":"media","source":"wp-media","id":"25243","label":"","thumbnail":""}]},
        {"label":"Behrens-Ufer","text":"Wasserkante · Umbau / Gegenwart. Gerade an den heutigen Transformationsorten werden historische Tiefenschichten neu lesbar.","media_refs":[{"kind":"media","source":"wp-media","id":"13329","label":"","thumbnail":""}]}
      ]
    },
    {
      "type": "text_bild_reihe",
      "kicker": "Zeitschichten",
      "title": "Der Standort im Wandel",
      "body": "<p>Die industrielle Topografie blieb bestehen, doch Funktionen, Eigentümer und Produktionsformen änderten sich mehrfach. Jede Phase hinterließ eigene Spuren im Stadtraum.</p>",
      "anchor": "zeitschichten",
      "treatment": "text-bild-reihe.chronology",
      "items": [
        {"label":"1880–1918","text":"Kaiserzeit. Elektrifizierung, Großindustrie und neue Werkstädte entstehen an der Spree.","media_refs":[{"kind":"media","source":"wp-media","id":"18849","label":"","thumbnail":""}]},
        {"label":"1945–1989","text":"DDR. Volkseigene Betriebe, Kombinate und Massenproduktion prägen den Standort.","media_refs":[{"kind":"media","source":"wp-media","id":"25246","label":"","thumbnail":""}]},
        {"label":"1990–2005","text":"Umbruch. Stilllegung, Leerstand und die ersten neuen Nutzungen markieren den Bruch.","media_refs":[{"kind":"media","source":"wp-media","id":"18855","label":"","thumbnail":""}]},
        {"label":"Seit 2005","text":"Heute. Transformation zwischen Wissenschaft, Kultur, Wohnen und industrieller Erinnerung.","media_refs":[{"kind":"media","source":"wp-media","id":"25254","label":"","thumbnail":""}]}
      ]
    },
    {
      "type": "gateway",
      "kicker": "Schlüsselorte",
      "title": "Wie Industrie den Stadtraum prägte",
      "body": "<p>Die ausgewählten Standorte markieren zentrale Punkte der industriellen Stadtlandschaft Schöneweides. Ihre Geschichte reicht von der frühen Industrialisierung über die DDR-Zeit bis zu heutigen Umnutzungen.</p>",
      "anchor": "schluesselorte",
      "treatment": "gateway.atlas-plates",
      "items": [
        {"label":"Behrensbau","text":"AEG · Architektur und Verwaltung · West","url":"/schoeneweide/orte/ostendstrasse-1-5-behrensbau/","page_id":"","media_refs":[{"kind":"media","source":"wp-media","id":"24683","label":"","thumbnail":""}]},
        {"label":"Kabelwerk Oberspree","text":"Kabel und Energie · Produktion · Ost","url":"/schoeneweide/orte/wilhelminenhofstrasse-rathenaustrasse-kwo/","page_id":"","media_refs":[{"kind":"media","source":"wp-media","id":"25261","label":"","thumbnail":""}]},
        {"label":"Rathenauhallen","text":"Transformatorenwerk · Elektrotechnik · Mitte","url":"/schoeneweide/orte/rathenau-hallen-komplex-urban-banks-berlin/","page_id":"","media_refs":[{"kind":"media","source":"wp-media","id":"25265","label":"","thumbnail":""}]},
        {"label":"Werk für Fernsehelektronik","text":"Fernsehröhren und Elektronik · Forschung · Ost","url":"/schoeneweide/orte/wilhelminenhofstrasse-66-67-und-68-69/","page_id":"","media_refs":[{"kind":"media","source":"wp-media","id":"25269","label":"","thumbnail":""}]},
        {"label":"Platz am Kaisersteg","text":"Spreequerung · Infrastruktur und Mobilität","url":"/schoeneweide/orte/unter-der-kranbahn-platz-am-kaisersteg/","page_id":"","media_refs":[{"kind":"media","source":"wp-media","id":"25267","label":"","thumbnail":""}]},
        {"label":"Stiftung Reinbeckhallen","text":"Werkhalle · Industriekultur und Produktion · West","url":"/schoeneweide/orte/stiftung-reinbeckhallen/","page_id":"","media_refs":[{"kind":"media","source":"wp-media","id":"2182","label":"","thumbnail":""}]}
      ]
    },
    {
      "type": "gateway",
      "kicker": "Zugänge zu Schöneweide",
      "title": "Wege durch Stadtraum und Sammlung",
      "body": "<p>Die Industriegeschichte Schöneweides lässt sich im Stadtraum, an Objekten der Sammlung und im Archiv nachvollziehen. Rundgänge, Ausstellung und Recherche greifen dabei ineinander.</p>",
      "anchor": "zugaenge",
      "treatment": "gateway.pathways",
      "items": [
        {"label":"Führungen","text":"Gebäude, Achsen, Arbeit und Wandel werden direkt vor Ort lesbar.","url":"/fuehrungen/","page_id":"13301","media_refs":[{"kind":"media","source":"wp-media","id":"25271","label":"","thumbnail":""}]},
        {"label":"Sammlungen","text":"Objekte und Maschinen verdichten Produktion, Wissen und Betriebskultur.","url":"/sammlungen/","page_id":"19873","media_refs":[{"kind":"media","source":"wp-media","id":"25273","label":"","thumbnail":""}]},
        {"label":"Archiv und Wissen","text":"Quellen, Bilder, Stimmen und Register führen tiefer in die Geschichte.","url":"/archiv/","page_id":"12594","media_refs":[{"kind":"media","source":"wp-media","id":"5348","label":"","thumbnail":""}]},
        {"label":"Ortsgeschichte","text":"Die Publikation verbindet Rundgang und vertiefende Lektüre.","url":"/publikationen/schoeneweide-eine-ortsgeschichte/","page_id":"","media_refs":[{"kind":"media","source":"wp-media","id":"7569","label":"","thumbnail":""}]}
      ]
    },
    {
      "type": "feature",
      "kicker": "Schöneweide heute",
      "title": "Transformation bleibt Teil des Orts",
      "body": "<p>Der industrielle Kern ist nicht verschwunden, sondern in neue Nutzungen, neue Konflikte und neue Öffentlichkeiten übersetzt worden. Erinnerung und Gegenwart bleiben hier im selben Stadtraum sichtbar.</p><p>Der Industriesalon versteht Oberschöneweide nicht als Kulisse vergangener Industrie, sondern als lesbare Stadtlandschaft im Wandel.</p>",
      "anchor": "gegenwart",
      "treatment": "feature.media-text",
      "media_layout": "40-60",
      "facts": [
        {"value":"Gegenwart","label":"Produktionsort, Wohnquartier und Kulturstandort."},
        {"value":"Lesart","label":"Industriegeschichte bleibt im Alltag sichtbar."}
      ],
      "links": [{"label":"Transformationen im Atlas","url":"#atlas-buehne","page_id":""}],
      "media_refs": [{"kind":"media","source":"wp-media","id":"13146","label":"Veranstaltung in einer ehemaligen Werkhalle in Oberschöneweide.","thumbnail":""}]
    },
    {
      "type": "dynamic_slot",
      "kicker": "Karte & Atlas",
      "title": "Von der Landeseite in die Dossiers wechseln",
      "body": "<p>Der Atlas bleibt die operative Lesefläche der Industrielandschaft: Orte suchen, Zeitschichten wechseln, Akteure einblenden und anschließend direkt in die einzelnen Ortsdossiers weiterlesen.</p>",
      "anchor": "atlas-buehne",
      "slot_key": "schoneweide-atlas",
      "treatment": "slot.schoneweide-atlas"
    }
  ],
  "deleted_sections": []
}
JSON;

$document = json_decode($document_json, true);
if (!is_array($document) || !iss_editorial_save_document($post_id, 'landing', $document)) {
    WP_CLI::error('Could not save Schöneweide landing document.');
}
iss_editorial_set_document_enabled($post_id, 'landing', true);

$relations = [
    ['place_id' => 17976, 'role' => 'related', 'weight' => 10, 'label' => 'Behrensbau'],
    ['place_id' => 17960, 'role' => 'related', 'weight' => 20, 'label' => 'Industriesalon Schöneweide'],
    ['place_id' => 12865, 'role' => 'related', 'weight' => 30, 'label' => 'Behrens-Ufer'],
    ['place_id' => 12870, 'role' => 'related', 'weight' => 40, 'label' => 'Rathenauhallen'],
];
$saved_relations = iss_relations_save_post_relations($post_id, $relations);
iss_relations_sync_post_read_models($post_id);

$saved_document = iss_editorial_get_document($post_id, 'landing');
$saved_place_ids = array_map(static function (array $relation): int {
    return (int) ($relation['place_id'] ?? 0);
}, $saved_relations);
if (
    count($saved_document['sections'] ?? []) !== 7
    || (string) ($saved_document['skin'] ?? '') !== 'territorial'
    || $saved_place_ids !== $place_ids
    || !iss_editorial_document_is_enabled($post_id, 'landing')
) {
    WP_CLI::error('Post-write verification failed. Restore from ' . $backup_table . '.');
}

WP_CLI::success(sprintf(
    'Applied %d Schöneweide sections and %d page relations; backup: %s.',
    count($saved_document['sections']),
    count($saved_relations),
    $backup_table
));
