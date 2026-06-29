-- Repair-Cafe canonical Veranstaltung and SuperSaaS event-series mapping.
-- Captured locally on 2026-06-29 after duplicate cleanup.
-- Keeps one canonical Veranstaltung (#26813), trashes generated duplicates,
-- maps Salonbelegung event:repair-cafe to the canonical event-series post,
-- and recreates timeline occurrences from the staged SuperSaaS slots.
-- Review target table prefix and URL before replaying outside this local stack.

START TRANSACTION;

SET @repair_cafe_canonical_id := 26813;
SET @repair_cafe_url := 'http://192.168.2.31:8082/repair-cafe/';

UPDATE wp_posts
SET post_title = 'Repair-Café',
    post_name = 'repair-cafe-terminreihe',
    post_status = 'publish',
    post_excerpt = 'Regelmäßiges Repair-Café im Industriesalon Schöneweide.',
    post_modified = NOW(),
    post_modified_gmt = UTC_TIMESTAMP()
WHERE ID = @repair_cafe_canonical_id
  AND post_type = 'veranstaltung';

UPDATE wp_posts
SET post_status = 'trash',
    post_name = CASE ID
        WHEN 26805 THEN '__trashed-4'
        WHEN 26808 THEN '__trashed-3'
        WHEN 26810 THEN '__trashed-2'
        WHEN 26812 THEN '__trashed'
        ELSE post_name
    END,
    post_modified = NOW(),
    post_modified_gmt = UTC_TIMESTAMP()
WHERE ID IN (26805, 26808, 26810, 26812)
  AND post_type = 'veranstaltung';

DELETE FROM wp_postmeta
WHERE post_id = @repair_cafe_canonical_id
  AND meta_key IN (
    '_iss_entity_key',
    'iss_start_datetime',
    'iss_programme_enabled',
    'iss_timeline_target_url',
    '_iss_supersaas_slot_row_id',
    '_iss_supersaas_external_id',
    '_iss_supersaas_slot_id',
    '_iss_supersaas_schedule_key',
    '_iss_supersaas_description'
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
(@repair_cafe_canonical_id, '_iss_entity_key', 'event.series'),
(@repair_cafe_canonical_id, 'iss_start_datetime', '2026-07-01 17:00:00'),
(@repair_cafe_canonical_id, 'iss_programme_enabled', ''),
(@repair_cafe_canonical_id, 'iss_timeline_target_url', @repair_cafe_url);

DELETE tr
FROM wp_term_relationships tr
INNER JOIN wp_term_taxonomy tt
        ON tt.term_taxonomy_id = tr.term_taxonomy_id
WHERE tr.object_id = @repair_cafe_canonical_id
  AND tt.taxonomy = 'veranstaltung_art';

INSERT INTO wp_term_relationships (object_id, term_taxonomy_id, term_order)
SELECT @repair_cafe_canonical_id, tt.term_taxonomy_id, 0
FROM wp_terms t
INNER JOIN wp_term_taxonomy tt
        ON tt.term_id = t.term_id
       AND tt.taxonomy = 'veranstaltung_art'
WHERE t.slug = 'repair-cafe'
ON DUPLICATE KEY UPDATE term_order = VALUES(term_order);

DELETE FROM wp_iss_occurrence_series
WHERE series_key = 'event:repair-cafe';

INSERT INTO wp_iss_occurrence_series (
    source_post_id,
    source_post_type,
    origin,
    external_id,
    series_key,
    rule,
    timezone,
    exceptions,
    created_at,
    updated_at,
    supersaas_title,
    tag,
    fallback_url,
    review_state
) VALUES (
    @repair_cafe_canonical_id,
    'veranstaltung',
    'supersaas',
    COALESCE((
        SELECT s.external_id
        FROM wp_iss_supersaas_slots s
        WHERE s.schedule_key = 'salonbelegung'
          AND s.series_key = 'event:repair-cafe'
        ORDER BY s.starts_at DESC, s.id DESC
        LIMIT 1
    ), 'salonbelegung:repair-cafe'),
    'event:repair-cafe',
    '',
    'Europe/Berlin',
    '',
    NOW(),
    NOW(),
    'Repair-Café',
    '',
    @repair_cafe_url,
    'mapped'
);

SELECT @repair_cafe_series_id := id
FROM wp_iss_occurrence_series
WHERE series_key = 'event:repair-cafe'
LIMIT 1;

UPDATE wp_iss_supersaas_slots
SET source_post_id = @repair_cafe_canonical_id,
    source_post_type = 'veranstaltung',
    match_state = 'mapped',
    review_state = 'mapped',
    status = 'projected',
    visibility = 'public',
    updated_at = NOW()
WHERE schedule_key = 'salonbelegung'
  AND series_key = 'event:repair-cafe';

DELETE FROM wp_iss_occurrences
WHERE series_key = 'event:repair-cafe'
   OR (
      source_post_type = 'veranstaltung'
      AND source_post_id IN (26805, 26808, 26810, 26812)
   );

INSERT INTO wp_iss_occurrences (
    source_post_id,
    source_post_type,
    kind,
    starts_at,
    ends_at,
    date_source,
    status,
    visibility,
    origin,
    external_id,
    series_key,
    series_id,
    booking_url,
    location_post_id,
    location_label,
    created_at,
    updated_at,
    title,
    source_calendar,
    tag,
    availability_state,
    capacity_total,
    capacity_available,
    is_open_ended
)
SELECT
    @repair_cafe_canonical_id,
    'veranstaltung',
    'event',
    s.starts_at,
    s.ends_at,
    'explicit',
    CASE WHEN s.is_cancelled = 1 THEN 'cancelled' ELSE 'active' END,
    'public',
    'supersaas',
    s.external_id,
    s.series_key,
    @repair_cafe_series_id,
    '',
    0,
    s.location_label,
    NOW(),
    NOW(),
    'Repair-Café',
    s.source_calendar,
    s.tag,
    s.availability_state,
    s.capacity_total,
    s.capacity_available,
    0
FROM wp_iss_supersaas_slots s
WHERE s.schedule_key = 'salonbelegung'
  AND s.series_key = 'event:repair-cafe'
  AND s.source_post_id = @repair_cafe_canonical_id
  AND s.source_post_type = 'veranstaltung'
ORDER BY s.starts_at ASC, s.id ASC;

COMMIT;
