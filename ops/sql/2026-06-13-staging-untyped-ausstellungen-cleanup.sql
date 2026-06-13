-- Staging cleanup for untyped Ausstellung posts that were published on staging
-- but are non-public in the local checkpoint.
--
-- Backup the target DB before applying.

START TRANSACTION;

-- Local checkpoint state: these imported/exploratory Ausstellung posts are drafts.
UPDATE wp_posts
SET post_status = 'draft'
WHERE post_type = 'ausstellung'
  AND post_status = 'publish'
  AND ID IN (24989, 24820, 24821, 24822, 24823, 21128);

-- Local checkpoint state: this imported/exploratory Ausstellung post is trashed.
UPDATE wp_posts
SET post_status = 'trash'
WHERE post_type = 'ausstellung'
  AND post_status = 'publish'
  AND ID = 24824;

-- Non-public Ausstellung posts should not remain in the WP occurrence projection.
DELETE FROM wp_iss_occurrences
WHERE origin = 'wp'
  AND source_post_type = 'ausstellung'
  AND source_post_id IN (24989, 24820, 24821, 24822, 24823, 24824, 21128);

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
