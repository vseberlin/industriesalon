-- Backfill explicit programme visibility before/with strict calendar toggle semantics.
-- Previous code treated missing iss_timeline_enabled meta on published
-- Veranstaltungen and Ausstellungen as public. The strict contract requires
-- explicit opt-in, so this converts the old implicit public state to explicit
-- meta without changing already-set rows.

INSERT INTO wp_postmeta (post_id, meta_key, meta_value)
SELECT p.ID, 'iss_timeline_enabled', '1'
FROM wp_posts p
LEFT JOIN wp_postmeta pm
    ON pm.post_id = p.ID
    AND pm.meta_key = 'iss_timeline_enabled'
WHERE p.post_type IN ('veranstaltung', 'ausstellung')
    AND p.post_status = 'publish'
    AND pm.post_id IS NULL;
