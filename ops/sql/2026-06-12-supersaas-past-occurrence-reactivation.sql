-- Restore already-past public SuperSaaS occurrence rows as historical calendar records.
-- SuperSaaS free-slot sync starts at "now", so it cannot re-fetch dates that already passed.

START TRANSACTION;

UPDATE wp_iss_occurrences o
INNER JOIN wp_posts p ON p.ID = o.source_post_id
SET o.status = 'active',
    o.updated_at = NOW()
WHERE o.origin = 'supersaas'
  AND o.visibility = 'public'
  AND o.source_post_type = 'fuehrung'
  AND o.starts_at < NOW()
  AND o.status <> 'active'
  AND p.post_type = 'fuehrung'
  AND p.post_status = 'publish';

COMMIT;
