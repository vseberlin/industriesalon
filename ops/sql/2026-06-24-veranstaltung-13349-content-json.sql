-- Veranstaltung 13349 structured content JSON
-- Generated: 2026-06-24
-- Applies the local _iss_content_json document imported from the legacy body with one media reference.
-- Public rendering remains on the legacy Veranstaltung body until the renderer switch is explicitly implemented.

START TRANSACTION;

DELETE FROM wp_postmeta
WHERE post_id = 13349
  AND meta_key = '_iss_content_json';

INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
(13349, '_iss_content_json', '{"schema_version":1,"entity_key":"event.vortrag","sections":[{"type":"intro","body":"Massarrats These ist unbequem:US-Hegemonie beruht nicht auf Überlegenheit,sondern auf einem System aus Ölkontrolle, Dollardominanz und Militärpräsenz —das sich in der Krise befindet, aber nicht kampflos aufgibt. Er benennt die Risikendes Übergangs zur multipolaren Welt — und zeigt, welche Ansätze eine neueWeltordnung möglich machen könnten.Am 22. April kommt er in den Industriesalon Schöneweide.Wir erwarten einen spannenden Abend mit Zukunftsforscher Klaus Burmeister,Mohssen Massarrat und …Zukunft im Gespräch (ZiG) ist eine kuratierte Gesprächsreihe&nbsp;von WERK116 und Industriesalon Schöneweide.Bücher, Thesen und künstlerische Positionen zu Gegenwart&nbsp;und Zukunft bilden den Ausgangspunkt für Abende, an denenAutorengespräch und Publikumsdialog ineinandergreifen –&nbsp;und das Gespräch in entspannter Salon-Atmosphäre weiterlebt\\n\\nWir danken für die Unterstützung","media_refs":[{"kind":"media","source":"wp-media","id":"11408","label":"fly_m-massarrat_04-1","thumbnail":"","width":"248","height":"107"}]}]}');

COMMIT;
