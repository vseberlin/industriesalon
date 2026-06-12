-- Remove retired Führung custom-template meta after moving singles to the block-theme hierarchy.
START TRANSACTION;

DELETE pm
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.post_type = 'fuehrung'
  AND pm.meta_key = '_wp_page_template'
  AND pm.meta_value IN ('single-tour', 'single-tour-on-demand');

COMMIT;
