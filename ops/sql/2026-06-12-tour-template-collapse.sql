-- Collapse legacy on-demand Führung template assignments to the canonical single-tour template.

START TRANSACTION;

UPDATE wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
SET pm.meta_value = 'single-tour'
WHERE pm.meta_key = '_wp_page_template'
  AND pm.meta_value = 'single-tour-on-demand'
  AND p.post_type = 'fuehrung';

COMMIT;
