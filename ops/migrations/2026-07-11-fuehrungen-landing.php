<?php
/**
 * Apply the /fuehrungen/ landing document and page relations through owning APIs.
 *
 * Run after the matching code deployment:
 * wp eval-file ops/migrations/2026-07-11-fuehrungen-landing.php --allow-root
 *
 * Rollback source: wp_iss_backup_20260711_fuehrungen_landing_meta.
 * Restore its four rows to wp_postmeta, then run:
 * wp eval 'iss_relations_sync_post_read_models(13301);' --allow-root
 */

if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit(1);
}

$post_id = 13301;
$post = get_post($post_id);
if (!$post instanceof WP_Post || $post->post_type !== 'page' || $post->post_name !== 'fuehrungen') {
    WP_CLI::error('Expected page 13301 with slug fuehrungen.');
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

global $wpdb;
$backup_table = $wpdb->prefix . 'iss_backup_20260711_fuehrungen_landing_meta';
if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $backup_table)) === $backup_table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration guard must inspect the backup table.
    WP_CLI::error('Backup table already exists: ' . $backup_table);
}

$meta_keys = [
    '_iss_editorial_landing',
    '_iss_editorial_enabled_landing',
    '_iss_editorial_landing_skin',
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
  "skin": "typografisch",
  "variant": "standard",
  "features": [],
  "sections": [
    {
      "type": "statement",
      "kicker": "Führungen",
      "title": "Führungen durch die Industriekultur",
      "body": "<p>Das ermäßigte Entgelt für die Führungen kann von Schüler*innen, Auszubildenden, Studierenden, Freiwilligendienstleistenden, Arbeitssuchenden mit Leistungen nach ALG I und Bürgergeld sowie Schwerbehinderten ab einem Grad der Behinderung von 50 gegen Vorlage der entsprechenden Bescheinigung in Anspruch genommen werden. Kinder bis 6 Jahre sind frei.</p>",
      "anchor": "ueberblick",
      "links": [],
      "treatment": "statement.lead"
    },
    {
      "type": "dynamic_slot",
      "kicker": "Überblick",
      "title": "Alle Führungen im Überblick",
      "body": "<p>Öffentliche Termine bleiben der schnellste Einstieg. Daneben finden Sie buchbare Gruppen-, Schul-, Familien- und Sonderformate, geordnet nach ihrem Angebot.</p>",
      "anchor": "angebote",
      "slot_key": "fuehrungen-offers",
      "treatment": "slot.fuehrungen-offers"
    },
    {
      "type": "atlas_map",
      "kicker": "Formate",
      "title": "Wie man Schöneweide erleben kann",
      "body": "Die Führungen reichen von offenen Rundgängen durch Fabrikensembles, Wohnquartiere und thematischen Sonderformaten. Jede Route eröffnet einen anderen Zugang zu Schöneweide — über Architektur, Industriegeschichte, Alltag, Erinnerung oder gegenwärtige Transformationen.",
      "anchor": "formate",
      "links": [],
      "treatment": "atlas-map.editorial-split"
    },
    {
      "type": "galerie",
      "kicker": "Impressionen",
      "title": "Führungen in Bildern",
      "body": "Sechs Perspektiven auf Wege, Orte und Themen unserer Rundgänge.",
      "anchor": "impressionen",
      "media_refs": [
        {
          "kind": "media",
          "source": "wp-media",
          "id": "24766",
          "label": "Industriearchitektur zwischen Geschichte und Transformation.",
          "thumbnail": ""
        },
        {
          "kind": "media",
          "source": "wp-media",
          "id": "981",
          "label": "Spurensuche für Kinder und Familien.",
          "thumbnail": ""
        },
        {
          "kind": "media",
          "source": "wp-media",
          "id": "10840",
          "label": "Industrie- und Ortsgeschichte auf dem Waldfriedhof Oberschöneweide.",
          "thumbnail": ""
        },
        {
          "kind": "media",
          "source": "wp-media",
          "id": "6976",
          "label": "Industriekultur zwischen Schöneweide und Köpenick per Fahrrad.",
          "thumbnail": ""
        },
        {
          "kind": "media",
          "source": "wp-media",
          "id": "11834",
          "label": "Rundgänge für Gruppen und Betriebsausflüge.",
          "thumbnail": ""
        },
        {
          "kind": "media",
          "source": "wp-media",
          "id": "7477",
          "label": "Fototouren zeigen Schöneweide zu ungewöhnlichen Tageszeiten.",
          "thumbnail": ""
        }
      ],
      "gallery_layout": "sequence"
    },
    {
      "type": "gateway",
      "kicker": "Bleiben Sie dran",
      "title": "Dieselben Orte anders weiterlesen",
      "body": "Was im Rundgang beginnt, setzt sich an anderer Stelle fort: in Filmen, Ausstellungen, Publikationen, Gesprächen und Archivmaterialien des Industriesalon.",
      "anchor": "weiterlesen",
      "items": [
        {
          "label": "Unterwegs",
          "text": "Rundgänge öffnen Werkorte, Stadträume und Industriegeschichte im direkten Gehen.",
          "url": "/fuehrungen/",
          "media_refs": [
            {
              "kind": "media",
              "source": "wp-media",
              "id": "26052",
              "label": "",
              "thumbnail": ""
            }
          ]
        },
        {
          "label": "Im Film",
          "text": "Videos zeigen dieselben Orte, Stimmen und Vermittlungssituationen in Bewegung.",
          "url": "/videos/",
          "media_refs": [
            {
              "kind": "media",
              "source": "wp-media",
              "id": "3827",
              "label": "",
              "thumbnail": ""
            }
          ]
        },
        {
          "label": "Im Haus",
          "text": "Ausstellungen verdichten Zusammenhänge, Objekte und Werkbezüge im Haus.",
          "url": "/ausstellungen/",
          "media_refs": [
            {
              "kind": "media",
              "source": "wp-media",
              "id": "26054",
              "label": "",
              "thumbnail": ""
            }
          ]
        },
        {
          "label": "Im Heft",
          "text": "Publikationen sichern Quellen, Chroniken, Hefte und thematische Serien.",
          "url": "/publikationen/",
          "media_refs": [
            {
              "kind": "media",
              "source": "wp-media",
              "id": "26030",
              "label": "",
              "thumbnail": ""
            }
          ]
        },
        {
          "label": "Im Gespräch",
          "text": "Veranstaltungen öffnen Gespräche über Stadt, Arbeit und Transformation.",
          "url": "/veranstaltungen/",
          "media_refs": [
            {
              "kind": "media",
              "source": "wp-media",
              "id": "26055",
              "label": "",
              "thumbnail": ""
            }
          ]
        },
        {
          "label": "Im Archiv",
          "text": "Archiv und Sammlung vertiefen Orte, Objekte und Quellen jenseits des Rundgangs.",
          "url": "/archiv/",
          "media_refs": [
            {
              "kind": "media",
              "source": "wp-media",
              "id": "26057",
              "label": "",
              "thumbnail": ""
            }
          ]
        }
      ],
      "treatment": "gateway.pathways"
    },
    {
      "type": "fliesstext",
      "kicker": "Auf Anfrage",
      "title": "Führungen für Gruppen und besondere Interessen",
      "body": "<p>Die Rundgänge verbinden Architektur, Arbeitsgeschichte, Technik und Stadtentwicklung direkt im Stadtraum. Für Gruppen, Schulen oder thematische Schwerpunkte stimmen wir Termin, Dauer und Ansprache individuell ab. Die Anfrage beginnt direkt beim passenden Angebot.</p>",
      "anchor": "gruppen",
      "links": []
    },
    {
      "type": "dynamic_slot",
      "kicker": "",
      "title": "",
      "body": "",
      "anchor": "besuch",
      "slot_key": "front-visit-info",
      "treatment": "slot.visit-info"
    }
  ],
  "deleted_sections": []
}
JSON;
$document = json_decode($document_json, true);
if (!is_array($document) || !iss_editorial_save_document($post_id, 'landing', $document)) {
    WP_CLI::error('Could not save landing document.');
}
iss_editorial_set_document_enabled($post_id, 'landing', true);

$relations = [
    ['place_id' => 17960, 'role' => 'related', 'weight' => 30, 'label' => 'Industriesalon Schöneweide'],
    ['place_id' => 12875, 'role' => 'related', 'weight' => 20, 'label' => 'Bärenquell-Brauerei'],
    ['place_id' => 17976, 'role' => 'related', 'weight' => 10, 'label' => 'Behrensbau'],
];
$saved_relations = iss_relations_save_post_relations($post_id, $relations);
iss_relations_sync_post_read_models($post_id);

$saved_document = iss_editorial_get_document($post_id, 'landing');
$saved_place_ids = array_map(static function (array $relation): int {
    return (int) ($relation['place_id'] ?? 0);
}, $saved_relations);
if (
    count($saved_document['sections'] ?? []) !== count($document['sections'] ?? [])
    || $saved_place_ids !== [17960, 12875, 17976]
    || !iss_editorial_document_is_enabled($post_id, 'landing')
) {
    WP_CLI::error('Post-write verification failed. Restore from ' . $backup_table . '.');
}

WP_CLI::success(sprintf(
    'Applied %d landing sections and %d page relations; backup: %s.',
    count($saved_document['sections']),
    count($saved_relations),
    $backup_table
));
