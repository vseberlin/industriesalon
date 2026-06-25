-- Enable project editorial JSON rendering for local/staging visual review.
-- Generated: 2026-06-25
-- Requires ops/sql/2026-06-25-project-editorial-json-candidates.sql first.

START TRANSACTION;

UPDATE wp_postmeta
SET meta_value = '1'
WHERE post_id IN (25720, 25657, 24819, 24818, 24817, 24816, 24815)
  AND meta_key = '_iss_editorial_enabled_projekt';

COMMIT;
