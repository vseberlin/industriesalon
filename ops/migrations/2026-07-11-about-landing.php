<?php
/**
 * Apply the /about/ landing document and establish an editable Team order.
 *
 * Run after the matching code deployment:
 * wp eval-file ops/migrations/2026-07-11-about-landing.php --allow-root
 */

if (!defined('ABSPATH') || !defined('WP_CLI')) {
    exit(1);
}

$post_id = 13123;
$post = get_post($post_id);
if (!$post instanceof WP_Post || $post->post_type !== 'page' || $post->post_name !== 'about') {
    WP_CLI::error('Expected page 13123 with slug about.');
}

$required_functions = [
    'iss_editorial_save_document',
    'iss_editorial_set_document_enabled',
];
foreach ($required_functions as $function_name) {
    if (!function_exists($function_name)) {
        WP_CLI::error('Missing required function: ' . $function_name);
    }
}

$team_ids = [13082, 13130, 13206, 13209, 18827, 18831];
foreach ($team_ids as $team_id) {
    if (get_post_type($team_id) !== 'team_member') {
        WP_CLI::error('Expected team_member ' . $team_id . '.');
    }
}

$media_ids = [26059, 26060, 13270, 13276, 13278, 13282, 11659, 1925, 11665];
foreach ($media_ids as $media_id) {
    if (get_post_type($media_id) !== 'attachment' || !wp_attachment_is_image($media_id)) {
        WP_CLI::error('Expected image attachment ' . $media_id . '.');
    }
}

global $wpdb;
$meta_backup_table = $wpdb->prefix . 'iss_backup_20260711_about_landing_meta';
$team_backup_table = $wpdb->prefix . 'iss_backup_20260711_team_order';
foreach ([$meta_backup_table, $team_backup_table] as $backup_table) {
    if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $backup_table)) === $backup_table) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off migration guard.
        WP_CLI::error('Backup table already exists: ' . $backup_table);
    }
}

$meta_keys = [
    '_iss_editorial_landing',
    '_iss_editorial_enabled_landing',
    '_iss_editorial_landing_skin',
    '_iss_editorial_landing_autosave',
];
$quoted_keys = implode(', ', array_map(static function (string $key): string {
    return "'" . esc_sql($key) . "'";
}, $meta_keys));
$quoted_team_ids = implode(', ', array_map('absint', $team_ids));
// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Prefix-derived identifiers and fixed migration values.
if ($wpdb->query(sprintf(
    'CREATE TABLE `%s` AS SELECT * FROM `%s` WHERE post_id = %d AND meta_key IN (%s)',
    esc_sql($meta_backup_table),
    esc_sql($wpdb->postmeta),
    $post_id,
    $quoted_keys
)) === false) {
    WP_CLI::error('Could not create About meta backup.');
}
if ($wpdb->query(sprintf(
    'CREATE TABLE `%s` AS SELECT ID, post_title, menu_order FROM `%s` WHERE ID IN (%s)',
    esc_sql($team_backup_table),
    esc_sql($wpdb->posts),
    $quoted_team_ids
)) === false) {
    WP_CLI::error('Could not create Team order backup.');
}
// phpcs:enable

$document_json = <<<'JSON'
{
  "schema_version": 1,
  "skin": "dossier",
  "variant": "standard",
  "features": [],
  "sections": [
    {
      "type": "text_bild_reihe",
      "kicker": "",
      "title": "",
      "body": "",
      "treatment": "text-bild-reihe.visual",
      "items": [
        {
          "label": "Führungen",
          "text": "Industriegeschichte wird dort lesbar, wo sie gebaut wurde: im Quartier, auf Wegen, an Fassaden und in Erzählungen vor Ort.",
          "media_refs": [{"kind":"media","source":"wp-media","id":"26059","label":"","thumbnail":""}]
        },
        {
          "label": "Wissen sichern",
          "text": "Objekte, Dokumente und Erinnerungen werden nicht nur bewahrt, sondern in Zusammenhänge gebracht und wieder lesbar gemacht.",
          "media_refs": [{"kind":"media","source":"wp-media","id":"26060","label":"","thumbnail":""}]
        },
        {
          "label": "Gegenwart",
          "text": "Der Ort ist nicht Abschluss einer Geschichte, sondern ein öffentlicher Ausgangspunkt für Fragen nach Wandel, Arbeit und Stadt.",
          "media_refs": [{"kind":"media","source":"wp-media","id":"13270","label":"","thumbnail":""}]
        }
      ]
    },
    {
      "type": "feature",
      "kicker": "Woher wir kommen",
      "title": "Aus Resten wurde ein Ort",
      "lead": "<p><strong>Ein Betriebsmuseum verschwand. Geräte, Dokumente und Teile der Geschichte lagen bereits zur Entsorgung bereit.</strong></p><p>Die Arbeit begann nicht mit Konzepten, sondern mit dem, was noch da war.</p>",
      "body": "<blockquote><p>Aus Ruinen wurde kein Palast. Erst einmal wurde gerettet, sortiert, getragen.</p></blockquote><p>Einige begannen, die Bestände herauszunehmen und zu sichern. Geräte, Unterlagen und Erinnerungen ehemaliger Beschäftigter wurden zusammengeführt.</p><p>Aus dieser ersten Sicherung entstand 2009 der Verein. Die Sammlung wurde zur Grundlage eines Ortes, der Industriegeschichte nicht abschließt, sondern weiter befragt.</p>",
      "anchor": "geschichte",
      "treatment": "feature.origin-story",
      "media_layout": "50-50",
      "facts": [
        {"value":"Winfried Müller","label":"1934–2022. Einer der wichtigen Initiatoren bei der Sicherung der Bestände des ehemaligen WF-Museums."}
      ],
      "links": [],
      "media_refs": [
        {"kind":"media","source":"wp-media","id":"13276","label":"Gesicherte Bestände des ehemaligen WF-Museums in einer frühen Phase des Industriesalons.","thumbnail":""}
      ]
    },
    {
      "type": "galerie",
      "kicker": "Der erste Zustand",
      "title": "Kein Ausstellungssystem. Nur Bestand.",
      "body": "<p><strong>Objektnotiz:</strong> Viele Geräte kamen ohne Kontext an. Beschriftungen fehlten, Zuordnungen waren unklar. Ein großer Teil der Arbeit bestand zunächst im Identifizieren, Ordnen und Wiederverbinden von Wissen.</p>",
      "anchor": "erster-zustand",
      "gallery_layout": "grid",
      "media_refs": [
        {"kind":"media","source":"wp-media","id":"13278","label":"Erste Zusammenstellungen der Bestände. Noch ohne ausgebildetes Ordnungssystem.","thumbnail":""},
        {"kind":"media","source":"wp-media","id":"13282","label":"Frühe Präsentation eines noch unsortierten Bestands.","thumbnail":""}
      ]
    },
    {
      "type": "fliesstext",
      "kicker": "Woran wir arbeiten",
      "title": "Aus Sammlung wird Stadtgespräch",
      "body": "<p><strong>Die Sammlung blieb nicht stehen. Aus Geräten, Dokumenten und Erinnerungen entstehen heute Rundgänge, Publikationen und Projekte im Stadtraum.</strong></p><p>Der Industriesalon arbeitet mit dem geretteten Bestand weiter: als Archiv, Ausstellungsort und Ausgangspunkt für Vermittlung.</p><p>Dabei geht es nicht nur um Technikgeschichte. Die Sammlung führt zu Fragen nach Arbeit, Stadtentwicklung und dem heutigen Schöneweide.</p><p>Kooperationen mit Schulen, Hochschulen, Initiativen und Partnern im Bezirk verbinden historische Objekte mit aktueller Stadtarbeit.</p><p><strong>Arbeitsform:</strong> zwischen Sammlung, Rundgang, Publikation und projektbezogener Förderung.</p>",
      "anchor": "arbeit",
      "treatment": "text.story-split",
      "links": []
    },
    {
      "type": "fliesstext",
      "kicker": "Wohin es geht",
      "title": "Ein Ort, der weiter in die Stadt reicht",
      "body": "<p><strong>Aus Ruinen wird nicht sofort ein Palast. Die Arbeit bleibt näher am Bestand: sichern, zeigen, erklären, weiterbauen.</strong></p><p>Der Industriesalon ist kein abgeschlossenes Museum. Er ist ein Ort, an dem industrielle Erinnerung in die Gegenwart Schöneweides hineinreicht.</p><p>Die Arbeit geht weiter, weil die Geschichte des Standorts nicht nur in Objekten liegt. Sie steckt auch in Gebäuden, Straßen, Arbeitswegen und persönlichen Erinnerungen.</p><p>Aus industrieller Erinnerung entstehen Fragen an die Stadtentwicklung: Welche Orte bleiben sichtbar? Welche Geschichten werden weitergegeben? Wer nutzt den Stadtteil morgen?</p><p>Viele Vorhaben entstehen projektbezogen und unter wechselnden Rahmenbedingungen. Der Salon verbindet dabei Archivarbeit, Vermittlung und Kooperationen im Bezirk.</p><h3>Projektarbeit</h3><p>Förderung, Kooperationen und ehrenamtliche Arbeit tragen viele Vorhaben. Das macht die Arbeit beweglich, aber nie ganz abgeschlossen.</p><p><strong>Ein Ort, der genutzt wird:</strong> für Führungen, Sammlung, Forschung, Nachbarschaft und Projekte, die Schöneweide nicht nur als Vergangenheit betrachten.</p>",
      "anchor": "zukunft",
      "treatment": "text.story-split-flip",
      "links": []
    },
    {
      "type": "text_bild_reihe",
      "kicker": "Anerkennung",
      "title": "Eine lange lokale Arbeit wurde auch über Schöneweide hinaus gesehen",
      "body": "<p>Auszeichnungen markieren hier keinen Höhepunkt, sondern machen sichtbar, wie kontinuierlich am Ort gearbeitet wurde: mit Sammlung, Vermittlung und einem langen Einsatz für die Industriegeschichte Schöneweides.</p>",
      "anchor": "anerkennung",
      "treatment": "text-bild-reihe.compact",
      "items": [
        {
          "label": "Bundesverdienstkreuz",
          "text": "Es steht exemplarisch für eine Arbeit, die nicht spektakulär begann, sondern über Jahre Bestände rettete, Wissen sicherte und daraus einen öffentlichen Ort machte.",
          "media_refs": [{"kind":"media","source":"wp-media","id":"11659","label":"","thumbnail":""}]
        },
        {
          "label": "Ehren-Institut der HTW Berlin",
          "text": "2019 wurde der Industriesalon zum 10. Geburtstag vom Präsidenten der HTW Berlin zum Ehren-Institut der HTW ernannt.",
          "media_refs": [{"kind":"media","source":"wp-media","id":"1925","label":"","thumbnail":""}]
        },
        {
          "label": "Tourismus-Award",
          "text": "2018 erhielt der Industriesalon den Tourismus-Award für herausragende touristische Leistungen in Treptow-Köpenick.",
          "media_refs": [{"kind":"media","source":"wp-media","id":"11665","label":"","thumbnail":""}]
        }
      ]
    },
    {
      "type": "dynamic_slot",
      "kicker": "Team",
      "title": "Die Menschen hinter dem Industriesalon",
      "body": "Ansprechpersonen, Vermittlerinnen und Projektverantwortliche arbeiten hier an Ausstellung, Sammlung, Programm und Besuch vor Ort.",
      "anchor": "team",
      "slot_key": "team-directory",
      "treatment": "slot.team-directory"
    },
    {
      "type": "statement",
      "kicker": "Mitmachen",
      "title": "Der Industriesalon als Verein",
      "body": "<p>Der Industriesalon wird von einem gemeinnützigen Verein getragen. Mitglieder, Ehrenamtliche, Zeitzeugen, Hochschulpartner und Unterstützer aus dem Stadtteil sichern die Arbeit langfristig und ermöglichen neue Projekte.</p><p>Mitgliedschaft, Mitarbeit im Archiv oder bei Veranstaltungen und konkrete Unterstützung vor Ort sind gleichermaßen willkommen.</p>",
      "anchor": "mitmachen",
      "treatment": "statement.callout",
      "links": [
        {"label":"Zum Verein","url":"/verein/","page_id":"13297"},
        {"label":"Führungen ansehen","url":"/fuehrungen/","page_id":"13301"}
      ]
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
    WP_CLI::error('Could not save About landing document.');
}
iss_editorial_set_document_enabled($post_id, 'landing', true);

$orders = [];
foreach ($team_ids as $index => $team_id) {
    $menu_order = ($index + 1) * 10;
    $result = wp_update_post([
        'ID' => $team_id,
        'menu_order' => $menu_order,
    ], true);
    if (is_wp_error($result)) {
        WP_CLI::error($result->get_error_message());
    }
    $orders[$team_id] = $menu_order;
}

$saved_document = iss_editorial_get_document($post_id, 'landing');
$saved_order = get_posts([
    'post_type' => 'team_member',
    'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
    'posts_per_page' => -1,
    'fields' => 'ids',
    'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC', 'ID' => 'ASC'],
    'order' => 'ASC',
]);
if (
    count($saved_document['sections'] ?? []) !== 9
    || array_map('absint', $saved_order) !== $team_ids
    || !iss_editorial_document_is_enabled($post_id, 'landing')
) {
    WP_CLI::error('Post-write verification failed. Restore from the About and Team backup tables.');
}

WP_CLI::success(sprintf(
    'Applied %d About sections and ordered %d Team profiles; backups: %s, %s.',
    count($saved_document['sections']),
    count($orders),
    $meta_backup_table,
    $team_backup_table
));
