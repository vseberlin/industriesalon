-- Ausstellung availability cleanup for the occurrence/availability split.
-- Backup before local application:
-- ops/content-backups/2026-06-11-before-ausstellung-availability-cleanup.sql

START TRANSACTION;

-- Kinder im Werk and Frauen im Werk fuer Fernmeldewesen are Digital exhibitions.
DELETE tr
FROM wp_term_relationships tr
INNER JOIN wp_term_taxonomy tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
WHERE tr.object_id IN (26381, 26287)
  AND tt.taxonomy = 'ausstellung_typ';

INSERT IGNORE INTO wp_term_relationships (object_id, term_taxonomy_id, term_order)
SELECT post_id, term_taxonomy_id, 0
FROM (
    SELECT 26381 AS post_id
    UNION ALL
    SELECT 26287 AS post_id
) AS target_posts
INNER JOIN wp_term_taxonomy tt
    ON tt.taxonomy = 'ausstellung_typ'
   AND tt.term_id = 444;

-- Keep the two Digital exhibitions visible through the end of 2026.
INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT target_posts.post_id, 'iss_end_date', '2026-12-31'
FROM (
    SELECT 26381 AS post_id
    UNION ALL
    SELECT 26287 AS post_id
) AS target_posts
WHERE NOT EXISTS (
    SELECT 1
    FROM wp_postmeta pm
    WHERE pm.post_id = target_posts.post_id
      AND pm.meta_key = 'iss_end_date'
);

UPDATE wp_postmeta
SET meta_value = '2026-12-31'
WHERE post_id IN (26381, 26287)
  AND meta_key = 'iss_end_date';

-- Old open-ended temporary exhibitions are not public until editors review dates/type.
UPDATE wp_posts
SET post_status = 'draft'
WHERE ID IN (25739, 25745)
  AND post_type = 'ausstellung';

-- These affected posts should not remain in the WP occurrence projection.
DELETE FROM wp_iss_occurrences
WHERE origin = 'wp'
  AND source_post_type = 'ausstellung'
  AND source_post_id IN (26381, 26287, 25739, 25745);

-- Recalculate Ausstellung type counts for published posts.
UPDATE wp_term_taxonomy tt
SET count = (
    SELECT COUNT(*)
    FROM wp_term_relationships tr
    INNER JOIN wp_posts p ON p.ID = tr.object_id
    WHERE tr.term_taxonomy_id = tt.term_taxonomy_id
      AND p.post_status = 'publish'
)
WHERE tt.taxonomy = 'ausstellung_typ';

COMMIT;
