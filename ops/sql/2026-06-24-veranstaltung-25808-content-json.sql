-- Veranstaltung 25808 structured content JSON
-- Generated: 2026-06-24
-- Applies the local _iss_content_json document imported from the legacy body with one industriesalon-steuerung dynamic reference.
-- Public rendering remains on the legacy Veranstaltung body until the renderer switch is explicitly implemented.

START TRANSACTION;

DELETE FROM wp_postmeta
WHERE post_id = 25808
  AND meta_key = '_iss_content_json';

INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
(25808, '_iss_content_json', '{"schema_version":1,"entity_key":"event.festival","sections":[{"type":"intro","body":"Programm"},{"type":"kapitel","title":"Live-Musik zwischen 15 und 22 Uhr","body":"Zum Sommeranfang spielen sieben Acts im Industriesalon. Die Reihenfolge steht im Flyer; einzelne Set-Zeiten werden separat vor Ort bekanntgegeben.\\n\\nLiveKWO-Klangwerk OberspreeBigband mit PowerLiveThe Velvet EchoesWarmer HarmoniegesangLiveJakabLieder mit GeschichtenLiveCrimson SundayUltimativer Retro-RockLiveIzzy DiazTanzbarer Folk aus SpanienLiveFranziskas ErbenPunk-Pop mit TiefgangLiveDances with DogsRock voller Überraschungen"},{"type":"kapitel","title":"Vor Ort","body":"Live-Musik, kühle Getränke und offene Hofatmosphäre im Industriesalon. Kommen und Gehen ist jederzeit möglich."},{"type":"kapitel","title":"Ort","dynamic_refs":[{"kind":"control_field","source":"industriesalon-steuerung","key":"address.full","label":"Volle Adresse","tagName":"p","linkMode":"none"}]},{"type":"kapitel","title":"Eintritt","body":"Der Eintritt ist frei. Eine Anmeldung ist nicht erforderlich."}]}');

COMMIT;
