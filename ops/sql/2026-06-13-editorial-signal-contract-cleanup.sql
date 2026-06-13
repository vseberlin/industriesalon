-- Editorial signal contract cleanup.
--
-- Retires expired/test legacy related-feature rows and adds explicit metadata
-- to the two retained Ausstellung self-promotions so the drift guard no longer
-- needs to grandfather active legacy related-feature rows.
--
-- Backup the target DB before applying.

START TRANSACTION;

-- Expired tour recommendations; they no longer affect related results.
UPDATE wp_iss_graph_editorial_signals
SET status = 'inactive',
    reason = 'Expired recommendation retired during editorial-signal contract cleanup.',
    updated_at = UTC_TIMESTAMP()
WHERE surface = 'related'
  AND signal_type = 'feature'
  AND context_post_id = 12191
  AND target_post_id IN (12033, 12183);

-- Test/self-promotion row for the placeholder Veranstaltung titled "123".
UPDATE wp_iss_graph_editorial_signals
SET status = 'inactive',
    reason = 'Retired test self-promotion during editorial-signal contract cleanup.',
    updated_at = UTC_TIMESTAMP()
WHERE surface = 'related'
  AND signal_type = 'feature'
  AND context_post_id = 26002
  AND target_post_id = 26002;

-- Retained digital Ausstellung launch promotion.
UPDATE wp_iss_graph_editorial_signals
SET status = 'active',
    reason = 'Digital exhibition launch promotion through the 2026 availability checkpoint.',
    expires_at = '2026-12-31 22:59:59',
    author_user_id = COALESCE(author_user_id, 1),
    updated_at = UTC_TIMESTAMP()
WHERE surface = 'related'
  AND signal_type = 'feature'
  AND context_post_id = 26287
  AND target_post_id = 26287;

-- Retained digital Ausstellung launch promotion, keeping the existing August
-- end date but making the intent explicit.
UPDATE wp_iss_graph_editorial_signals
SET status = 'active',
    reason = 'Digital exhibition launch promotion through August 2026.',
    expires_at = '2026-08-31 21:59:59',
    author_user_id = COALESCE(author_user_id, 1),
    updated_at = UTC_TIMESTAMP()
WHERE surface = 'related'
  AND signal_type = 'feature'
  AND context_post_id = 26381
  AND target_post_id = 26381;

COMMIT;
