-- Prepare rollback evidence for replaying generated ISS graph aliases.
--
-- Apply after deploying the alias guard code, before running the non-dry-run
-- WP-CLI replay. This SQL creates a targeted rollback snapshot only; the
-- mutation itself must be performed by:
--
--   wp iss-graph sync-aliases --dry-run --limit=25
--   wp iss-graph sync-aliases
--   wp iss-graph sync-aliases --dry-run --limit=25
--   wp iss-graph verify
--   wp iss-graph drift-check --limit=25
--
-- Local pre-replay dry-run on 2026-06-12 reported:
-- entities=3558 changed_entities=30 current_names=4438 proposed_names=4374
-- removed_names=64 added_names=0
--
-- Keep the backup table until the target checkpoint is accepted. Do not import
-- this with --force; if the backup table already exists, inspect it instead of
-- overwriting the rollback snapshot.

SELECT
  'preflight_generated_aliases' AS check_name,
  COUNT(*) AS row_count
FROM wp_iss_entity_names
WHERE source_system = 'entity_alias_backfill';

SELECT
  'preflight_generated_aliases_by_kind' AS check_name,
  e.entity_kind,
  COUNT(*) AS row_count
FROM wp_iss_entity_names n
INNER JOIN wp_iss_entity_index e ON e.id = n.entity_id
WHERE n.source_system = 'entity_alias_backfill'
GROUP BY e.entity_kind
ORDER BY row_count DESC, e.entity_kind ASC;

SELECT
  'preflight_focus_terms' AS check_name,
  n.normalized_name,
  COUNT(DISTINCT n.entity_id) AS entity_count,
  COUNT(*) AS name_count
FROM wp_iss_entity_names n
WHERE n.source_system = 'entity_alias_backfill'
  AND n.normalized_name IN ('wf', 'kwo', 'aeg', 'tro', 'osw')
GROUP BY n.normalized_name
ORDER BY n.normalized_name ASC;

CREATE TABLE wp_iss_entity_names_alias_replay_backup_20260612 LIKE wp_iss_entity_names;

INSERT INTO wp_iss_entity_names_alias_replay_backup_20260612 (
  id,
  entity_id,
  name,
  normalized_name,
  name_type,
  language,
  is_primary,
  valid_from_year,
  valid_to_year,
  position,
  source_system,
  source_ref,
  created_at,
  updated_at
)
SELECT
  id,
  entity_id,
  name,
  normalized_name,
  name_type,
  language,
  is_primary,
  valid_from_year,
  valid_to_year,
  position,
  source_system,
  source_ref,
  created_at,
  updated_at
FROM wp_iss_entity_names
WHERE source_system = 'entity_alias_backfill';

SELECT
  'rollback_snapshot_rows' AS check_name,
  COUNT(*) AS row_count
FROM wp_iss_entity_names_alias_replay_backup_20260612;

-- After `wp iss-graph sync-aliases`, re-run the dry-run command above. A clean
-- target should report changed_entities=0, removed_names=0, added_names=0.
--
-- Rollback block, only if the replay must be undone after a target DB backup:
--
-- START TRANSACTION;
--
-- DELETE FROM wp_iss_entity_names
-- WHERE source_system = 'entity_alias_backfill';
--
-- INSERT INTO wp_iss_entity_names (
--   id,
--   entity_id,
--   name,
--   normalized_name,
--   name_type,
--   language,
--   is_primary,
--   valid_from_year,
--   valid_to_year,
--   position,
--   source_system,
--   source_ref,
--   created_at,
--   updated_at
-- )
-- SELECT
--   id,
--   entity_id,
--   name,
--   normalized_name,
--   name_type,
--   language,
--   is_primary,
--   valid_from_year,
--   valid_to_year,
--   position,
--   source_system,
--   source_ref,
--   created_at,
--   updated_at
-- FROM wp_iss_entity_names_alias_replay_backup_20260612;
--
-- COMMIT;
