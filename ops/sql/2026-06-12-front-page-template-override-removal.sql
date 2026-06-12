-- Remove the DB-backed front-page template override after syncing useful
-- edits into themes/industriesalon/templates/front-page.html.

START TRANSACTION;

DELETE pm
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.post_type = 'wp_template'
  AND p.post_name = 'front-page';

DELETE tr
FROM wp_term_relationships tr
INNER JOIN wp_posts p ON p.ID = tr.object_id
WHERE p.post_type = 'wp_template'
  AND p.post_name = 'front-page';

DELETE FROM wp_posts
WHERE post_type = 'wp_template'
  AND post_name = 'front-page';

COMMIT;
