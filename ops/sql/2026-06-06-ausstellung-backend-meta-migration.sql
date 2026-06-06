-- Ausstellung backend contract migration.
-- Generated from local dev on 2026-06-06.
-- Scope: replace retired iss_surface_mode with iss_exhibition_type + iss_exhibition_source.

START TRANSACTION;

DELETE FROM wp_postmeta
WHERE meta_key IN ('iss_exhibition_type', 'iss_exhibition_source')
  AND post_id IN (
    13257,17980,18877,18880,18885,21104,21108,21112,21113,21128,
    24820,24821,24822,24823,24824,24989,25525,25527,25529,25531,
    25533,25535,25735,25737,25739,25741,25743,25745,25747,25772
  );

INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES
(13257, 'iss_exhibition_type', 'story'),
(13257, 'iss_exhibition_source', 'manual'),
(17980, 'iss_exhibition_type', 'story'),
(17980, 'iss_exhibition_source', 'archive_category'),
(18877, 'iss_exhibition_type', 'story'),
(18877, 'iss_exhibition_source', 'curated_chapters'),
(18880, 'iss_exhibition_type', 'collection'),
(18880, 'iss_exhibition_source', 'archive_browser'),
(18885, 'iss_exhibition_type', 'story'),
(18885, 'iss_exhibition_source', 'curated_chapters'),
(21104, 'iss_exhibition_type', 'story'),
(21104, 'iss_exhibition_source', 'curated_chapters'),
(21108, 'iss_exhibition_type', 'story'),
(21108, 'iss_exhibition_source', 'curated_chapters'),
(21112, 'iss_exhibition_type', 'collection'),
(21112, 'iss_exhibition_source', 'manual'),
(21113, 'iss_exhibition_type', 'story'),
(21113, 'iss_exhibition_source', 'curated_chapters'),
(21128, 'iss_exhibition_type', 'visual_essay'),
(21128, 'iss_exhibition_source', 'manual'),
(24820, 'iss_exhibition_type', 'story'),
(24820, 'iss_exhibition_source', 'manual'),
(24821, 'iss_exhibition_type', 'story'),
(24821, 'iss_exhibition_source', 'manual'),
(24822, 'iss_exhibition_type', 'story'),
(24822, 'iss_exhibition_source', 'manual'),
(24823, 'iss_exhibition_type', 'story'),
(24823, 'iss_exhibition_source', 'manual'),
(24824, 'iss_exhibition_type', 'story'),
(24824, 'iss_exhibition_source', 'manual'),
(24989, 'iss_exhibition_type', 'story'),
(24989, 'iss_exhibition_source', 'manual'),
(25525, 'iss_exhibition_type', 'collection'),
(25525, 'iss_exhibition_source', 'archive_browser'),
(25527, 'iss_exhibition_type', 'collection'),
(25527, 'iss_exhibition_source', 'archive_browser'),
(25529, 'iss_exhibition_type', 'collection'),
(25529, 'iss_exhibition_source', 'archive_browser'),
(25531, 'iss_exhibition_type', 'collection'),
(25531, 'iss_exhibition_source', 'archive_browser'),
(25533, 'iss_exhibition_type', 'collection'),
(25533, 'iss_exhibition_source', 'archive_browser'),
(25535, 'iss_exhibition_type', 'collection'),
(25535, 'iss_exhibition_source', 'archive_browser'),
(25735, 'iss_exhibition_type', 'story'),
(25735, 'iss_exhibition_source', 'manual'),
(25737, 'iss_exhibition_type', 'story'),
(25737, 'iss_exhibition_source', 'manual'),
(25739, 'iss_exhibition_type', 'story'),
(25739, 'iss_exhibition_source', 'manual'),
(25741, 'iss_exhibition_type', 'story'),
(25741, 'iss_exhibition_source', 'manual'),
(25743, 'iss_exhibition_type', 'story'),
(25743, 'iss_exhibition_source', 'manual'),
(25745, 'iss_exhibition_type', 'story'),
(25745, 'iss_exhibition_source', 'manual'),
(25747, 'iss_exhibition_type', 'story'),
(25747, 'iss_exhibition_source', 'manual'),
(25772, 'iss_exhibition_type', 'story'),
(25772, 'iss_exhibition_source', 'manual');

DELETE FROM wp_postmeta
WHERE meta_key = 'iss_surface_mode'
  AND post_id IN (
    13257,17980,18877,18880,18885,21104,21108,21112,21113,21128,
    24820,24821,24822,24823,24824,24989,25525,25527,25529,25531,
    25533,25535,25735,25737,25739,25741,25743,25745,25747,25772
  );

COMMIT;
