-- Production sync for 2026-06-03 front-page launch work.
-- Scope: template authority and project ordering only.
-- Video transcript DB content is handled separately in 2026-06-03-production-video-transcripts-sync.sql.
-- Assumes the standard WordPress table prefix `wp_`.

START TRANSACTION;

-- Return these templates to the theme files by removing Site Editor overrides.
DELETE pm
FROM wp_postmeta pm
INNER JOIN wp_posts p ON p.ID = pm.post_id
WHERE p.post_type IN ('wp_template', 'wp_template_part')
  AND p.post_name IN ('front-page', 'page-videos');

DELETE tr
FROM wp_term_relationships tr
INNER JOIN wp_posts p ON p.ID = tr.object_id
WHERE p.post_type IN ('wp_template', 'wp_template_part')
  AND p.post_name IN ('front-page', 'page-videos');

DELETE FROM wp_posts
WHERE post_type IN ('wp_template', 'wp_template_part')
  AND post_name IN ('front-page', 'page-videos');

-- Match the local ordering used by the front-page and project-page Query Loops.
UPDATE wp_posts SET menu_order = 10 WHERE post_type = 'projekt' AND post_name = 'walk-of-fame-schoeneweide';
UPDATE wp_posts SET menu_order = 15 WHERE post_type = 'projekt' AND post_name = 'futura-biennale-2027';
UPDATE wp_posts SET menu_order = 20 WHERE post_type = 'projekt' AND post_name = 'boulevard-der-industriekultur';
UPDATE wp_posts SET menu_order = 30 WHERE post_type = 'projekt' AND post_name = 'industrieabfaelle';
UPDATE wp_posts SET menu_order = 40 WHERE post_type = 'projekt' AND post_name = 'stadtlabor-wilhelminenhofstrasse';
UPDATE wp_posts SET menu_order = 50 WHERE post_type = 'projekt' AND post_name = 'connected-by-lights';
UPDATE wp_posts SET menu_order = 60 WHERE post_type = 'projekt' AND post_name = 'das-landmark-der-elektropolis';

COMMIT;
