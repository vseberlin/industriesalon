-- Add curated canonical ISS graph organization rows for KWO and AEG.
--
-- Apply only after the generated alias replay is complete and
-- `wp iss-graph sync-aliases --dry-run --limit=25` reports no remaining
-- replay changes. This artifact is additive: it creates hidden organization
-- entities and curated names only. It does not merge, reassign, or delete the
-- existing post, publication, video, archive-object, or place entities that
-- mention KWO or AEG.
--
-- After import, run:
--
--   wp iss-graph sync-aliases --dry-run --limit=25
--   wp iss-graph verify
--   wp iss-graph drift-check --limit=25
--   wp iss-graph entity-hygiene-audit --terms=KWO,AEG --limit=25

SELECT
  'preflight_existing_target_orgs' AS check_name,
  id,
  entity_kind,
  source_system,
  source_id,
  canonical_slug,
  display_title
FROM wp_iss_entity_index
WHERE entity_kind = 'organization'
  AND (
    source_id IN ('kabelwerk-oberspree', 'allgemeine-elektricitats-gesellschaft')
    OR canonical_slug IN ('kabelwerk-oberspree', 'allgemeine-elektricitats-gesellschaft')
    OR display_title IN ('Kabelwerk Oberspree', 'Allgemeine Elektricitäts-Gesellschaft')
  )
ORDER BY id ASC;

SELECT
  'preflight_focus_terms_by_kind' AS check_name,
  n.normalized_name,
  e.entity_kind,
  n.source_system,
  COUNT(DISTINCT n.entity_id) AS entity_count,
  COUNT(*) AS name_count
FROM wp_iss_entity_names n
INNER JOIN wp_iss_entity_index e ON e.id = n.entity_id
WHERE n.normalized_name IN ('kwo', 'aeg', 'kabelwerk-oberspree', 'allgemeine-elektricitats-gesellschaft')
GROUP BY n.normalized_name, e.entity_kind, n.source_system
ORDER BY n.normalized_name ASC, e.entity_kind ASC, n.source_system ASC;

START TRANSACTION;

INSERT INTO wp_iss_entity_index (
  entity_kind,
  post_id,
  profile_post_id,
  source_system,
  source_id,
  canonical_slug,
  display_title,
  summary,
  status,
  is_public,
  has_profile,
  search_visibility,
  search_weight,
  sort_start,
  sort_end,
  created_at,
  updated_at
)
SELECT
  seed.entity_kind,
  NULL,
  NULL,
  seed.source_system,
  seed.source_id,
  seed.canonical_slug,
  seed.display_title,
  seed.summary,
  'publish',
  0,
  0,
  'hidden',
  0,
  0,
  0,
  UTC_TIMESTAMP(),
  UTC_TIMESTAMP()
FROM (
  SELECT
    'organization' AS entity_kind,
    'manual_slug' AS source_system,
    'kabelwerk-oberspree' AS source_id,
    'kabelwerk-oberspree' AS canonical_slug,
    'Kabelwerk Oberspree' AS display_title,
    'Curated canonical organization row for the historical Kabelwerk Oberspree identity.' AS summary
  UNION ALL
  SELECT
    'organization',
    'manual_slug',
    'allgemeine-elektricitats-gesellschaft',
    'allgemeine-elektricitats-gesellschaft',
    'Allgemeine Elektricitäts-Gesellschaft',
    'Curated canonical organization row for the historical AEG identity.'
) AS seed
WHERE NOT EXISTS (
    SELECT 1
    FROM wp_iss_entity_index existing_source
    WHERE existing_source.entity_kind = seed.entity_kind
      AND existing_source.source_system = seed.source_system
      AND existing_source.source_id = seed.source_id
  )
  AND NOT EXISTS (
    SELECT 1
    FROM wp_iss_entity_index existing_slug
    WHERE existing_slug.entity_kind = seed.entity_kind
      AND existing_slug.canonical_slug = seed.canonical_slug
  );

INSERT INTO wp_iss_entity_names (
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
  e.id,
  seed.name,
  seed.normalized_name,
  seed.name_type,
  '',
  seed.is_primary,
  NULL,
  NULL,
  seed.position,
  seed.source_system,
  CASE
    WHEN seed.source_system = 'entity_title' THEN CONCAT('entity:', e.id)
    ELSE 'graph-curation:2026-06-12-kwo-aeg'
  END,
  UTC_TIMESTAMP(),
  UTC_TIMESTAMP()
FROM wp_iss_entity_index e
INNER JOIN (
  SELECT
    'kabelwerk-oberspree' AS entity_slug,
    'Kabelwerk Oberspree' AS name,
    'kabelwerk-oberspree' AS normalized_name,
    'primary' AS name_type,
    1 AS is_primary,
    0 AS position,
    'entity_title' AS source_system
  UNION ALL
  SELECT
    'kabelwerk-oberspree',
    'KWO',
    'kwo',
    'abbreviation',
    0,
    0,
    'manual_alias'
  UNION ALL
  SELECT
    'kabelwerk-oberspree',
    'VEB Kabelwerk Oberspree',
    'veb-kabelwerk-oberspree',
    'official',
    0,
    1,
    'manual_alias'
  UNION ALL
  SELECT
    'allgemeine-elektricitats-gesellschaft',
    'Allgemeine Elektricitäts-Gesellschaft',
    'allgemeine-elektricitats-gesellschaft',
    'primary',
    1,
    0,
    'entity_title'
  UNION ALL
  SELECT
    'allgemeine-elektricitats-gesellschaft',
    'AEG',
    'aeg',
    'abbreviation',
    0,
    0,
    'manual_alias'
  UNION ALL
  SELECT
    'allgemeine-elektricitats-gesellschaft',
    'Allgemeine Elektricitaets-Gesellschaft',
    'allgemeine-elektricitaets-gesellschaft',
    'transliteration',
    0,
    1,
    'manual_alias'
) AS seed ON seed.entity_slug = e.canonical_slug
WHERE e.entity_kind = 'organization'
  AND e.source_system = 'manual_slug'
  AND e.source_id IN ('kabelwerk-oberspree', 'allgemeine-elektricitats-gesellschaft')
  AND NOT EXISTS (
    SELECT 1
    FROM wp_iss_entity_names existing_name
    WHERE existing_name.entity_id = e.id
      AND existing_name.source_system = seed.source_system
      AND existing_name.normalized_name = seed.normalized_name
      AND existing_name.name_type = seed.name_type
  );

COMMIT;

SELECT
  'post_import_target_orgs' AS check_name,
  e.id,
  e.entity_kind,
  e.source_system,
  e.source_id,
  e.canonical_slug,
  e.display_title,
  GROUP_CONCAT(CONCAT(n.source_system, ':', n.name_type, ':', n.name) ORDER BY n.source_system, n.position SEPARATOR ' | ') AS names
FROM wp_iss_entity_index e
LEFT JOIN wp_iss_entity_names n ON n.entity_id = e.id
WHERE e.entity_kind = 'organization'
  AND e.source_system = 'manual_slug'
  AND e.source_id IN ('kabelwerk-oberspree', 'allgemeine-elektricitats-gesellschaft')
GROUP BY
  e.id,
  e.entity_kind,
  e.source_system,
  e.source_id,
  e.canonical_slug,
  e.display_title
ORDER BY e.display_title ASC;

-- Rollback, only before other graph rows are intentionally related to these
-- entities:
--
-- START TRANSACTION;
--
-- DELETE n
-- FROM wp_iss_entity_names n
-- INNER JOIN wp_iss_entity_index e ON e.id = n.entity_id
-- WHERE e.entity_kind = 'organization'
--   AND e.source_system = 'manual_slug'
--   AND e.source_id IN ('kabelwerk-oberspree', 'allgemeine-elektricitats-gesellschaft')
--   AND n.source_system IN ('entity_title', 'manual_alias', 'entity_alias_backfill');
--
-- DELETE FROM wp_iss_entity_index
-- WHERE entity_kind = 'organization'
--   AND source_system = 'manual_slug'
--   AND source_id IN ('kabelwerk-oberspree', 'allgemeine-elektricitats-gesellschaft');
--
-- COMMIT;
