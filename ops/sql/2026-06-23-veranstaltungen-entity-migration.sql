-- Veranstaltung entity migration source-state transfer
-- Generated: 2026-06-23
-- Applies curated Veranstaltung entity keys/date facts and moves post 25763 to fuehrung/Sonderfuehrung.
-- Projection rows are intentionally not transferred; regenerate occurrences and graph/search after import:
--   wp iss-content veranstaltungen-dry-run
--   wp iss-content veranstaltungen-repository-check
--   wp iss-graph sync-content
--   wp iss-graph sync-search
--   wp iss-content tours-drift-check

START TRANSACTION;

UPDATE wp_posts SET post_type = 'fuehrung' WHERE ID = 25763;

DELETE FROM wp_postmeta
WHERE post_id IN (25808,25729,25661,24988,24991,24992,24993,24961,24825,24826,24827,24828,24829,24830,24831,13349,11216,25751,25757,25774,25761,25755,25749,25759,25753)
  AND meta_key IN ('_iss_entity_key', 'iss_start_datetime', 'iss_end_datetime', '_iss_datetime_start', '_iss_datetime_end', '_iss_published_at', '_iss_date_inference_note');

DELETE FROM wp_postmeta
WHERE post_id = 25763
  AND meta_key IN ('_iss_entity_key', '_iss_datetime_start', '_iss_datetime_end', '_iss_published_at', 'booking_mode', 'target_group', 'tour_badge', 'meeting_point', 'duration');

INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
(25808, '_iss_entity_key', 'event.festival'),
(25808, 'iss_start_datetime', '2026-06-21 15:00:00'),
(25808, 'iss_end_datetime', '2026-06-21 22:00:00'),
(25808, '_iss_datetime_start', '2026-06-21 15:00:00'),
(25808, '_iss_datetime_end', '2026-06-21 22:00:00'),
(25808, '_iss_published_at', '2026-05-30 15:50:46'),
(25729, '_iss_entity_key', 'event.workshop'),
(25729, 'iss_start_datetime', '2019-04-11 16:00:00'),
(25729, 'iss_end_datetime', '2019-04-11 20:00:00'),
(25729, '_iss_datetime_start', '2019-04-11 16:00:00'),
(25729, '_iss_datetime_end', '2019-04-11 20:00:00'),
(25729, '_iss_published_at', '2026-05-29 15:53:11'),
(25661, '_iss_entity_key', 'event.workshop'),
(25661, 'iss_start_datetime', '2026-05-19 18:00:00'),
(25661, 'iss_end_datetime', '2026-05-19 20:00:00'),
(25661, '_iss_datetime_start', '2026-05-19 18:00:00'),
(25661, '_iss_datetime_end', '2026-05-19 20:00:00'),
(25661, '_iss_published_at', '2026-05-28 22:00:02'),
(24988, '_iss_entity_key', 'event.vortrag'),
(24988, 'iss_start_datetime', '2025-10-14 19:00:00'),
(24988, 'iss_end_datetime', '2025-10-14 21:00:00'),
(24988, '_iss_datetime_start', '2025-10-14 19:00:00'),
(24988, '_iss_datetime_end', '2025-10-14 21:00:00'),
(24988, '_iss_published_at', '2026-05-16 14:53:16'),
(24988, '_iss_date_inference_note', 'Body exact: 14. Oktober 2025 | 19 Uhr; default end +2h.'),
(24991, '_iss_entity_key', 'event.gespraech'),
(24991, 'iss_start_datetime', '2023-06-15 19:00:00'),
(24991, 'iss_end_datetime', '2023-06-15 21:00:00'),
(24991, '_iss_datetime_start', '2023-06-15 19:00:00'),
(24991, '_iss_datetime_end', '2023-06-15 21:00:00'),
(24991, '_iss_published_at', '2026-05-16 14:53:16'),
(24991, '_iss_date_inference_note', 'Body month/year only: Juni 2023; fallback day=15, time=19-21.'),
(24992, '_iss_entity_key', 'event.festival'),
(24992, 'iss_start_datetime', '2024-07-15 19:00:00'),
(24992, 'iss_end_datetime', '2024-07-15 21:00:00'),
(24992, '_iss_datetime_start', '2024-07-15 19:00:00'),
(24992, '_iss_datetime_end', '2024-07-15 21:00:00'),
(24992, '_iss_published_at', '2026-05-16 14:53:16'),
(24992, '_iss_date_inference_note', 'Body month/year only: Juli 2024; fallback day=15, time=19-21.'),
(24993, '_iss_entity_key', 'event.festival'),
(24993, 'iss_start_datetime', '2017-10-01 19:00:00'),
(24993, 'iss_end_datetime', '2017-10-01 21:00:00'),
(24993, '_iss_datetime_start', '2017-10-01 19:00:00'),
(24993, '_iss_datetime_end', '2017-10-01 21:00:00'),
(24993, '_iss_published_at', '2026-05-16 14:53:16'),
(24993, '_iss_date_inference_note', 'Body exact date: 1. Oktober 2017; fallback time=19-21.'),
(24961, '_iss_entity_key', 'event.vortrag'),
(24961, 'iss_start_datetime', '2026-05-21 19:00:00'),
(24961, 'iss_end_datetime', '2026-05-21 21:00:00'),
(24961, '_iss_datetime_start', '2026-05-21 19:00:00'),
(24961, '_iss_datetime_end', '2026-05-21 21:00:00'),
(24961, '_iss_published_at', '2026-05-16 13:12:02'),
(24825, '_iss_entity_key', 'event.vortrag'),
(24825, 'iss_start_datetime', '2022-06-16 19:00:00'),
(24825, 'iss_end_datetime', '2022-06-16 21:00:00'),
(24825, '_iss_datetime_start', '2022-06-16 19:00:00'),
(24825, '_iss_datetime_end', '2022-06-16 21:00:00'),
(24825, '_iss_published_at', '2026-05-13 20:27:48'),
(24825, '_iss_date_inference_note', 'Body exact: 16. Juni, 19 Uhr; year inferred from same Rathenau series body references in 2022; default end +2h.'),
(24826, '_iss_entity_key', 'event.gespraech'),
(24826, 'iss_start_datetime', '2023-09-14 19:00:00'),
(24826, 'iss_end_datetime', '2023-09-14 21:00:00'),
(24826, '_iss_datetime_start', '2023-09-14 19:00:00'),
(24826, '_iss_datetime_end', '2023-09-14 21:00:00'),
(24826, '_iss_published_at', '2026-05-13 20:27:48'),
(24826, '_iss_date_inference_note', 'User supplied 14.09.2023; default time 19:00-21:00.'),
(24827, '_iss_entity_key', 'event.gespraech'),
(24827, 'iss_start_datetime', '2024-10-15 19:00:00'),
(24827, 'iss_end_datetime', '2024-10-15 21:00:00'),
(24827, '_iss_datetime_start', '2024-10-15 19:00:00'),
(24827, '_iss_datetime_end', '2024-10-15 21:00:00'),
(24827, '_iss_published_at', '2026-05-13 20:27:48'),
(24827, '_iss_date_inference_note', 'Body exact: Dienstag, 15. Oktober 2024, 19 Uhr; default end +2h.'),
(24828, '_iss_entity_key', 'event.festival'),
(24828, 'iss_start_datetime', '2024-09-08 11:00:00'),
(24828, 'iss_end_datetime', '2024-09-08 15:00:00'),
(24828, '_iss_datetime_start', '2024-09-08 11:00:00'),
(24828, '_iss_datetime_end', '2024-09-08 15:00:00'),
(24828, '_iss_published_at', '2026-05-13 20:27:48'),
(24828, '_iss_date_inference_note', 'Body exact date and start times: Sonntag, 8.9.2024, starts 11 and 13 Uhr; end inferred as 15 Uhr.'),
(24829, '_iss_entity_key', 'event.vortrag'),
(24829, 'iss_start_datetime', '2025-11-13 19:30:00'),
(24829, 'iss_end_datetime', '2025-11-13 21:30:00'),
(24829, '_iss_datetime_start', '2025-11-13 19:30:00'),
(24829, '_iss_datetime_end', '2025-11-13 21:30:00'),
(24829, '_iss_published_at', '2026-05-13 20:27:48'),
(24829, '_iss_date_inference_note', 'Body exact: 13.11.2025 | 19:30 Uhr; default end +2h.'),
(24830, '_iss_entity_key', 'event.vortrag'),
(24830, 'iss_start_datetime', '2026-04-09 18:00:00'),
(24830, 'iss_end_datetime', '2026-04-09 20:00:00'),
(24830, '_iss_datetime_start', '2026-04-09 18:00:00'),
(24830, '_iss_datetime_end', '2026-04-09 20:00:00'),
(24830, '_iss_published_at', '2026-05-13 20:27:48'),
(24830, '_iss_date_inference_note', 'User supplied 09.04.2026 at 18:00; default end +2 hours.'),
(24831, '_iss_entity_key', 'event.praesentation'),
(24831, 'iss_start_datetime', '2026-03-27 18:00:00'),
(24831, 'iss_end_datetime', '2026-03-27 20:00:00'),
(24831, '_iss_datetime_start', '2026-03-27 18:00:00'),
(24831, '_iss_datetime_end', '2026-03-27 20:00:00'),
(24831, '_iss_published_at', '2026-05-13 20:27:48'),
(24831, '_iss_date_inference_note', 'User supplied 27.03.2026 at 18:00; default end +2 hours.'),
(13349, '_iss_entity_key', 'event.vortrag'),
(13349, 'iss_start_datetime', '2026-05-14 08:29:00'),
(13349, 'iss_end_datetime', '2026-05-15 08:29:00'),
(13349, '_iss_datetime_start', '2026-05-14 08:29:00'),
(13349, '_iss_datetime_end', '2026-05-15 08:29:00'),
(13349, '_iss_published_at', '2026-05-02 08:29:36'),
(11216, '_iss_entity_key', 'event.lesung'),
(11216, 'iss_start_datetime', '2026-03-19 18:00:00'),
(11216, '_iss_datetime_start', '2026-03-19 18:00:00'),
(11216, '_iss_published_at', '2026-02-13 11:24:17'),
(25751, '_iss_entity_key', 'event.vortrag'),
(25751, 'iss_start_datetime', '2023-11-21 17:00:00'),
(25751, '_iss_datetime_start', '2023-11-21 17:00:00'),
(25751, '_iss_published_at', '2023-11-21 12:00:00'),
(25757, '_iss_entity_key', 'event.festival'),
(25757, 'iss_start_datetime', '2023-09-10 11:00:00'),
(25757, 'iss_end_datetime', '2023-09-10 18:00:00'),
(25757, '_iss_datetime_start', '2023-09-10 11:00:00'),
(25757, '_iss_datetime_end', '2023-09-10 18:00:00'),
(25757, '_iss_published_at', '2023-09-10 12:00:00'),
(25774, '_iss_entity_key', 'event.lesung'),
(25774, 'iss_start_datetime', '2022-06-23 19:00:00'),
(25774, '_iss_datetime_start', '2022-06-23 19:00:00'),
(25774, '_iss_published_at', '2022-06-23 12:00:00'),
(25761, '_iss_entity_key', 'event.vortrag'),
(25761, 'iss_start_datetime', '2022-06-16 19:00:00'),
(25761, '_iss_datetime_start', '2022-06-16 19:00:00'),
(25761, '_iss_published_at', '2022-06-16 12:00:00'),
(25755, '_iss_entity_key', 'event.festival'),
(25755, 'iss_start_datetime', '2020-09-13 13:00:00'),
(25755, 'iss_end_datetime', '2020-09-13 17:00:00'),
(25755, '_iss_datetime_start', '2020-09-13 13:00:00'),
(25755, '_iss_datetime_end', '2020-09-13 17:00:00'),
(25755, '_iss_published_at', '2020-09-13 12:00:00'),
(25749, '_iss_entity_key', 'event.gespraech'),
(25749, 'iss_start_datetime', '2019-10-10 18:00:00'),
(25749, '_iss_datetime_start', '2019-10-10 18:00:00'),
(25749, '_iss_published_at', '2019-10-10 12:00:00'),
(25759, '_iss_entity_key', 'event.festival'),
(25759, 'iss_start_datetime', '2019-10-06 15:00:00'),
(25759, 'iss_end_datetime', '2019-10-13 18:00:00'),
(25759, '_iss_datetime_start', '2019-10-06 15:00:00'),
(25759, '_iss_datetime_end', '2019-10-13 18:00:00'),
(25759, '_iss_published_at', '2019-10-06 12:00:00'),
(25753, '_iss_entity_key', 'event.festival'),
(25753, 'iss_start_datetime', '2019-09-07 15:00:00'),
(25753, 'iss_end_datetime', '2019-09-08 17:00:00'),
(25753, '_iss_datetime_start', '2019-09-07 15:00:00'),
(25753, '_iss_datetime_end', '2019-09-08 17:00:00'),
(25753, '_iss_published_at', '2019-09-07 12:00:00'),
(25763, 'booking_mode', 'on_demand'),
(25763, 'target_group', 'Sonderführung'),
(25763, 'tour_badge', 'Sonderführung'),
(25763, 'meeting_point', 'Start: Industriesalon Schöneweide; Ende: Waldfriedhof Oberschöneweide'),
(25763, 'duration', '2 Stunden');

INSERT INTO wp_terms (name, slug, term_group)
SELECT 'Sonderführung', 'sonderfuehrung', 0
WHERE NOT EXISTS (SELECT 1 FROM wp_terms WHERE slug = 'sonderfuehrung');

SET @sonderfuehrung_term_id := (SELECT term_id FROM wp_terms WHERE slug = 'sonderfuehrung' ORDER BY term_id LIMIT 1);

INSERT INTO wp_term_taxonomy (term_id, taxonomy, description, parent, count)
SELECT @sonderfuehrung_term_id, 'fuehrung_typ', '', 0, 0
WHERE NOT EXISTS (SELECT 1 FROM wp_term_taxonomy WHERE term_id = @sonderfuehrung_term_id AND taxonomy = 'fuehrung_typ');

SET @sonderfuehrung_tt_id := (
    SELECT term_taxonomy_id
    FROM wp_term_taxonomy
    WHERE term_id = @sonderfuehrung_term_id AND taxonomy = 'fuehrung_typ'
    ORDER BY term_taxonomy_id
    LIMIT 1
);

DELETE FROM wp_term_relationships
WHERE object_id = 25763
  AND term_taxonomy_id IN (SELECT term_taxonomy_id FROM wp_term_taxonomy WHERE taxonomy = 'fuehrung_typ');

INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order)
VALUES (25763, @sonderfuehrung_tt_id, 0);

UPDATE wp_term_taxonomy
SET count = (SELECT COUNT(*) FROM wp_term_relationships WHERE term_taxonomy_id = @sonderfuehrung_tt_id)
WHERE term_taxonomy_id = @sonderfuehrung_tt_id;

COMMIT;
