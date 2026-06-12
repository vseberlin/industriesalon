-- Curate canonical ISS and WF graph identities after the alias replay.
--
-- Apply only after:
--
--   wp iss-graph sync-aliases --dry-run --limit=25
--   wp iss-graph drift-check --checks=alias-backfill-replay --limit=25
--   wp iss-graph drift-check --checks=canonical-organization-seeds --limit=25
--
-- This artifact records the curator decisions:
--
--   * Industriesalon Schöneweide e.V. is the canonical association identity
--     for Industriesalon variants, including ISS.
--   * archive_institution:institution:29 belongs to that canonical identity.
--   * WF means Werk für Fernsehelektronik.
--   * Werk für Fernmeldewesen remains a separate organization, without WF as
--     an abbreviation.
--
-- It reassigns archive institution relations from the duplicate source row to
-- the canonical Industriesalon organization, moves the archive institution
-- identifier, retires the duplicate source row, and normalizes the two WF/Fern-
-- meldewesen labels so future alias replay does not recreate the conflict.
--
-- After import, run:
--
--   wp iss-graph sync-aliases --dry-run --limit=25
--   wp iss-graph sync-aliases
--   wp iss-graph sync-aliases --dry-run --limit=25
--   wp iss-graph verify
--   wp iss-graph drift-check --limit=25
--   wp iss-graph drift-check --checks=canonical-wf-industriesalon --limit=25
--   wp iss-graph entity-hygiene-audit --terms="Industriesalon Schöneweide,ISS,WF" --limit=25

SELECT
  'preflight_target_entities' AS check_name,
  id,
  entity_kind,
  source_system,
  source_id,
  canonical_slug,
  display_title,
  status,
  is_public,
  search_visibility
FROM wp_iss_entity_index
WHERE entity_kind = 'organization'
  AND (
    (source_system = 'manual_slug' AND source_id = 'industriesalon-schoneweide-e-v')
    OR (source_system = 'archive_institution' AND source_id = 'institution:29')
    OR (source_system = 'archive_person_organization_label' AND source_id IN ('person-label:63251', 'person-label:63659'))
  )
ORDER BY id ASC;

SELECT
  'preflight_target_names' AS check_name,
  e.id,
  e.source_system,
  e.source_id,
  n.source_system AS name_source,
  n.name_type,
  n.name,
  n.normalized_name
FROM wp_iss_entity_index e
INNER JOIN wp_iss_entity_names n ON n.entity_id = e.id
WHERE e.entity_kind = 'organization'
  AND (
    (e.source_system = 'manual_slug' AND e.source_id = 'industriesalon-schoneweide-e-v')
    OR (e.source_system = 'archive_institution' AND e.source_id = 'institution:29')
    OR (e.source_system = 'archive_person_organization_label' AND e.source_id IN ('person-label:63251', 'person-label:63659'))
  )
ORDER BY e.id ASC, n.source_system ASC, n.name_type ASC, n.name ASC;

SELECT
  'preflight_target_relations' AS check_name,
  target.source_system,
  target.source_id,
  r.relation_family,
  r.relation_type,
  COUNT(*) AS relation_count
FROM wp_iss_entity_relations r
INNER JOIN wp_iss_entity_index target ON target.id = r.to_entity_id
WHERE target.entity_kind = 'organization'
  AND (
    (target.source_system = 'archive_institution' AND target.source_id = 'institution:29')
    OR (target.source_system = 'archive_person_organization_label' AND target.source_id IN ('person-label:63251', 'person-label:63659'))
  )
GROUP BY target.source_system, target.source_id, r.relation_family, r.relation_type
ORDER BY target.source_system ASC, target.source_id ASC, r.relation_family ASC, r.relation_type ASC;

START TRANSACTION;

SET @graph_curation_ref = 'graph-curation:2026-06-12-wf-industriesalon';
SET @iss_canonical_id = (
  SELECT id
  FROM wp_iss_entity_index
  WHERE entity_kind = 'organization'
    AND source_system = 'manual_slug'
    AND source_id = 'industriesalon-schoneweide-e-v'
  LIMIT 1
);
SET @iss_archive_id = (
  SELECT id
  FROM wp_iss_entity_index
  WHERE entity_kind = 'organization'
    AND source_system = 'archive_institution'
    AND source_id = 'institution:29'
  LIMIT 1
);
SET @wf_canonical_id = (
  SELECT id
  FROM wp_iss_entity_index
  WHERE entity_kind = 'organization'
    AND source_system = 'archive_person_organization_label'
    AND source_id = 'person-label:63251'
  LIMIT 1
);
SET @fernmeldewesen_id = (
  SELECT id
  FROM wp_iss_entity_index
  WHERE entity_kind = 'organization'
    AND source_system = 'archive_person_organization_label'
    AND source_id = 'person-label:63659'
  LIMIT 1
);

UPDATE wp_iss_entity_index
SET canonical_slug = 'industriesalon-schoneweide-e-v',
    display_title = 'Industriesalon Schöneweide e.V.',
    summary = CASE
      WHEN summary = '' THEN 'Curated canonical organization identity for the Industriesalon Schöneweide e.V. association, including ISS and archive institution institution:29.'
      ELSE summary
    END,
    status = 'publish',
    is_public = 0,
    search_visibility = 'hidden',
    updated_at = UTC_TIMESTAMP()
WHERE id = @iss_canonical_id;

DELETE duplicate_rel
FROM wp_iss_entity_relations duplicate_rel
INNER JOIN wp_iss_entity_relations existing_rel
  ON existing_rel.from_entity_id = duplicate_rel.from_entity_id
  AND existing_rel.to_entity_id = @iss_canonical_id
  AND existing_rel.relation_family = duplicate_rel.relation_family
  AND existing_rel.relation_type = duplicate_rel.relation_type
  AND existing_rel.relation_role = duplicate_rel.relation_role
  AND existing_rel.relation_label = duplicate_rel.relation_label
  AND existing_rel.note = duplicate_rel.note
  AND existing_rel.weight = duplicate_rel.weight
  AND existing_rel.position = duplicate_rel.position
  AND existing_rel.is_primary = duplicate_rel.is_primary
  AND existing_rel.valid_from_year <=> duplicate_rel.valid_from_year
  AND existing_rel.valid_to_year <=> duplicate_rel.valid_to_year
  AND existing_rel.source_system = duplicate_rel.source_system
  AND existing_rel.source_ref = duplicate_rel.source_ref
  AND existing_rel.is_public = duplicate_rel.is_public
WHERE duplicate_rel.to_entity_id = @iss_archive_id;

UPDATE wp_iss_entity_relations
SET to_entity_id = @iss_canonical_id,
    updated_at = UTC_TIMESTAMP()
WHERE to_entity_id = @iss_archive_id;

UPDATE wp_iss_entity_identifiers
SET entity_id = @iss_canonical_id,
    trust_level = 'trusted_review',
    confidence = 80,
    source_system = 'archive_institution',
    source_ref = CONCAT('entity:', @iss_canonical_id),
    is_primary = 1,
    status = 'accepted',
    updated_at = UTC_TIMESTAMP()
WHERE namespace = 'archive_institution'
  AND normalized_value = 'institution:29';

INSERT INTO wp_iss_entity_identifiers (
  entity_id,
  namespace,
  value,
  normalized_value,
  url,
  label,
  trust_level,
  confidence,
  source_system,
  source_ref,
  is_primary,
  status,
  created_at,
  updated_at
)
SELECT
  @iss_canonical_id,
  'archive_institution',
  'institution:29',
  'institution:29',
  '',
  'Archive institution ID',
  'trusted_review',
  80,
  'archive_institution',
  CONCAT('entity:', @iss_canonical_id),
  1,
  'accepted',
  UTC_TIMESTAMP(),
  UTC_TIMESTAMP()
WHERE @iss_canonical_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM wp_iss_entity_identifiers existing_identifier
    WHERE existing_identifier.namespace = 'archive_institution'
      AND existing_identifier.normalized_value = 'institution:29'
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
  @iss_canonical_id,
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
    WHEN seed.source_system = 'archive_title' THEN CONCAT('entity:', @iss_canonical_id)
    ELSE @graph_curation_ref
  END,
  UTC_TIMESTAMP(),
  UTC_TIMESTAMP()
FROM (
  SELECT 'ISS' AS name, 'iss' AS normalized_name, 'abbreviation' AS name_type, 0 AS is_primary, 0 AS position, 'manual_alias' AS source_system
  UNION ALL
  SELECT 'Industriesalon', 'industriesalon', 'alternative', 0, 1, 'manual_alias'
  UNION ALL
  SELECT 'Industriesalon Schöneweide', 'industriesalon-schoneweide', 'alternative', 0, 2, 'manual_alias'
  UNION ALL
  SELECT 'Industriesalon Schoeneweide', 'industriesalon-schoeneweide', 'transliteration', 0, 3, 'manual_alias'
  UNION ALL
  SELECT 'Industriesalon Schöneweide', 'industriesalon-schoneweide', 'source_label', 0, 0, 'archive_title'
) AS seed
WHERE @iss_canonical_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1
    FROM wp_iss_entity_names existing_name
    WHERE existing_name.entity_id = @iss_canonical_id
      AND existing_name.source_system = seed.source_system
      AND existing_name.normalized_name = seed.normalized_name
      AND existing_name.name_type = seed.name_type
  );

DELETE FROM wp_iss_entity_names
WHERE entity_id = @iss_archive_id;

UPDATE wp_iss_entity_index
SET source_system = 'retired_graph_identity',
    source_id = 'archive-institution-29-industriesalon-schoneweide',
    canonical_slug = 'retired-archive-institution-29-industriesalon-schoneweide',
    display_title = 'Retired duplicate graph identity',
    summary = 'Retired duplicate of canonical Industriesalon Schöneweide e.V.; archive institution institution:29 and archive relations moved to the canonical organization identity.',
    status = 'retired',
    is_public = 0,
    search_visibility = 'hidden',
    updated_at = UTC_TIMESTAMP()
WHERE id = @iss_archive_id;

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
  @iss_archive_id,
  'Retired duplicate graph identity',
  'retired-duplicate-graph-identity',
  'primary',
  '',
  1,
  NULL,
  NULL,
  0,
  'entity_title',
  CONCAT('entity:', @iss_archive_id),
  UTC_TIMESTAMP(),
  UTC_TIMESTAMP()
WHERE @iss_archive_id IS NOT NULL;

UPDATE wp_iss_entity_index
SET display_title = 'Werk für Fernsehelektronik',
    summary = CASE
      WHEN summary = '' THEN 'Curated canonical organization identity for WF: Werk für Fernsehelektronik.'
      ELSE summary
    END,
    status = 'publish',
    is_public = 0,
    search_visibility = 'hidden',
    updated_at = UTC_TIMESTAMP()
WHERE id = @wf_canonical_id;

DELETE FROM wp_iss_entity_names
WHERE entity_id = @wf_canonical_id
  AND source_system IN ('entity_title', 'manual_alias');

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
  @wf_canonical_id,
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
    WHEN seed.source_system = 'entity_title' THEN CONCAT('entity:', @wf_canonical_id)
    ELSE @graph_curation_ref
  END,
  UTC_TIMESTAMP(),
  UTC_TIMESTAMP()
FROM (
  SELECT 'Werk für Fernsehelektronik' AS name, 'werk-fur-fernsehelektronik' AS normalized_name, 'primary' AS name_type, 1 AS is_primary, 0 AS position, 'entity_title' AS source_system
  UNION ALL
  SELECT 'WF', 'wf', 'abbreviation', 0, 0, 'manual_alias'
  UNION ALL
  SELECT 'VEB Werk für Fernsehelektronik', 'veb-werk-fur-fernsehelektronik', 'official', 0, 1, 'manual_alias'
  UNION ALL
  SELECT 'Werk fuer Fernsehelektronik', 'werk-fuer-fernsehelektronik', 'transliteration', 0, 2, 'manual_alias'
) AS seed
WHERE @wf_canonical_id IS NOT NULL;

UPDATE wp_iss_entity_index
SET display_title = 'Werk für Fernmeldewesen',
    summary = CASE
      WHEN summary = '' THEN 'Curated as Werk für Fernmeldewesen. The former source label abbreviation WF is reserved for Werk für Fernsehelektronik.'
      ELSE summary
    END,
    status = 'publish',
    is_public = 0,
    search_visibility = 'hidden',
    updated_at = UTC_TIMESTAMP()
WHERE id = @fernmeldewesen_id;

DELETE FROM wp_iss_entity_names
WHERE entity_id = @fernmeldewesen_id
  AND source_system IN ('entity_title', 'archive_title', 'entity_alias_backfill', 'manual_alias');

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
  @fernmeldewesen_id,
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
    WHEN seed.source_system = 'entity_title' THEN CONCAT('entity:', @fernmeldewesen_id)
    WHEN seed.source_system = 'archive_title' THEN CONCAT('entity:', @fernmeldewesen_id)
    ELSE @graph_curation_ref
  END,
  UTC_TIMESTAMP(),
  UTC_TIMESTAMP()
FROM (
  SELECT 'Werk für Fernmeldewesen' AS name, 'werk-fur-fernmeldewesen' AS normalized_name, 'primary' AS name_type, 1 AS is_primary, 0 AS position, 'entity_title' AS source_system
  UNION ALL
  SELECT 'Werk für Fernmeldewesen', 'werk-fur-fernmeldewesen', 'source_label', 0, 0, 'archive_title'
) AS seed
WHERE @fernmeldewesen_id IS NOT NULL;

DELETE FROM wp_iss_entity_names
WHERE normalized_name = 'wf'
  AND entity_id <> @wf_canonical_id;

COMMIT;

SELECT
  'post_import_target_entities' AS check_name,
  id,
  entity_kind,
  source_system,
  source_id,
  canonical_slug,
  display_title,
  status,
  is_public,
  search_visibility
FROM wp_iss_entity_index
WHERE entity_kind = 'organization'
  AND (
    (source_system = 'manual_slug' AND source_id = 'industriesalon-schoneweide-e-v')
    OR (source_system = 'retired_graph_identity' AND source_id = 'archive-institution-29-industriesalon-schoneweide')
    OR (source_system = 'archive_person_organization_label' AND source_id IN ('person-label:63251', 'person-label:63659'))
  )
ORDER BY id ASC;

SELECT
  'post_import_focus_terms' AS check_name,
  n.normalized_name,
  e.entity_kind,
  e.source_system,
  e.source_id,
  e.display_title,
  COUNT(*) AS name_count
FROM wp_iss_entity_names n
INNER JOIN wp_iss_entity_index e ON e.id = n.entity_id
WHERE n.normalized_name IN ('iss', 'industriesalon', 'industriesalon-schoneweide', 'industriesalon-schoeneweide', 'wf', 'werk-fur-fernsehelektronik', 'werk-fur-fernmeldewesen')
GROUP BY n.normalized_name, e.entity_kind, e.source_system, e.source_id, e.display_title
ORDER BY n.normalized_name ASC, e.entity_kind ASC, e.source_system ASC, e.source_id ASC;

SELECT
  'post_import_relation_targets' AS check_name,
  target.source_system,
  target.source_id,
  r.relation_family,
  r.relation_type,
  COUNT(*) AS relation_count
FROM wp_iss_entity_relations r
INNER JOIN wp_iss_entity_index target ON target.id = r.to_entity_id
WHERE target.entity_kind = 'organization'
  AND (
    (target.source_system = 'manual_slug' AND target.source_id = 'industriesalon-schoneweide-e-v')
    OR (target.source_system = 'retired_graph_identity' AND target.source_id = 'archive-institution-29-industriesalon-schoneweide')
    OR (target.source_system = 'archive_person_organization_label' AND target.source_id IN ('person-label:63251', 'person-label:63659'))
  )
GROUP BY target.source_system, target.source_id, r.relation_family, r.relation_type
ORDER BY target.source_system ASC, target.source_id ASC, r.relation_family ASC, r.relation_type ASC;

-- Rollback block, only after a target DB backup and before later graph changes
-- intentionally depend on these canonical rows:
--
-- START TRANSACTION;
--
-- SET @graph_curation_ref = 'graph-curation:2026-06-12-wf-industriesalon';
-- SET @iss_canonical_id = (SELECT id FROM wp_iss_entity_index WHERE entity_kind = 'organization' AND source_system = 'manual_slug' AND source_id = 'industriesalon-schoneweide-e-v' LIMIT 1);
-- SET @iss_archive_id = (SELECT id FROM wp_iss_entity_index WHERE entity_kind = 'organization' AND source_system = 'retired_graph_identity' AND source_id = 'archive-institution-29-industriesalon-schoneweide' LIMIT 1);
-- SET @wf_canonical_id = (SELECT id FROM wp_iss_entity_index WHERE entity_kind = 'organization' AND source_system = 'archive_person_organization_label' AND source_id = 'person-label:63251' LIMIT 1);
-- SET @fernmeldewesen_id = (SELECT id FROM wp_iss_entity_index WHERE entity_kind = 'organization' AND source_system = 'archive_person_organization_label' AND source_id = 'person-label:63659' LIMIT 1);
--
-- UPDATE wp_iss_entity_relations
-- SET to_entity_id = @iss_archive_id,
--     updated_at = UTC_TIMESTAMP()
-- WHERE to_entity_id = @iss_canonical_id
--   AND source_system = 'archive_projection'
--   AND relation_family = 'organization'
--   AND relation_type = 'institution';
--
-- UPDATE wp_iss_entity_identifiers
-- SET entity_id = @iss_archive_id,
--     source_ref = CONCAT('entity:', @iss_archive_id),
--     updated_at = UTC_TIMESTAMP()
-- WHERE entity_id = @iss_canonical_id
--   AND namespace = 'archive_institution'
--   AND normalized_value = 'institution:29';
--
-- DELETE FROM wp_iss_entity_names
-- WHERE entity_id = @iss_canonical_id
--   AND source_ref = @graph_curation_ref;
--
-- UPDATE wp_iss_entity_index
-- SET source_system = 'archive_institution',
--     source_id = 'institution:29',
--     canonical_slug = 'archive_institution-institution29',
--     display_title = 'Industriesalon Schöneweide',
--     summary = '',
--     status = 'publish',
--     is_public = 0,
--     search_visibility = 'hidden',
--     updated_at = UTC_TIMESTAMP()
-- WHERE id = @iss_archive_id;
--
-- DELETE FROM wp_iss_entity_names WHERE entity_id = @iss_archive_id;
-- INSERT INTO wp_iss_entity_names (entity_id, name, normalized_name, name_type, language, is_primary, valid_from_year, valid_to_year, position, source_system, source_ref, created_at, updated_at)
-- SELECT @iss_archive_id, 'Industriesalon Schöneweide', 'industriesalon-schoneweide', 'primary', '', 1, NULL, NULL, 0, 'entity_title', CONCAT('entity:', @iss_archive_id), UTC_TIMESTAMP(), UTC_TIMESTAMP()
-- UNION ALL
-- SELECT @iss_archive_id, 'Industriesalon Schöneweide', 'industriesalon-schoneweide', 'source_label', '', 0, NULL, NULL, 0, 'archive_title', CONCAT('entity:', @iss_archive_id), UTC_TIMESTAMP(), UTC_TIMESTAMP();
--
-- DELETE FROM wp_iss_entity_names
-- WHERE entity_id IN (@wf_canonical_id, @fernmeldewesen_id)
--   AND source_system IN ('entity_title', 'archive_title', 'manual_alias', 'entity_alias_backfill');
--
-- UPDATE wp_iss_entity_index
-- SET display_title = 'Werk für Fernsehelektronik (WF)',
--     updated_at = UTC_TIMESTAMP()
-- WHERE id = @wf_canonical_id;
--
-- INSERT INTO wp_iss_entity_names (entity_id, name, normalized_name, name_type, language, is_primary, valid_from_year, valid_to_year, position, source_system, source_ref, created_at, updated_at)
-- SELECT @wf_canonical_id, 'Werk für Fernsehelektronik (WF)', 'werk-fur-fernsehelektronik-wf', 'primary', '', 1, NULL, NULL, 0, 'entity_title', CONCAT('entity:', @wf_canonical_id), UTC_TIMESTAMP(), UTC_TIMESTAMP()
-- UNION ALL
-- SELECT @wf_canonical_id, 'Werk für Fernsehelektronik (WF)', 'werk-fur-fernsehelektronik-wf', 'source_label', '', 0, NULL, NULL, 0, 'archive_title', CONCAT('entity:', @wf_canonical_id), UTC_TIMESTAMP(), UTC_TIMESTAMP();
--
-- UPDATE wp_iss_entity_index
-- SET display_title = 'Werk für Fernmeldewesen (WF)',
--     updated_at = UTC_TIMESTAMP()
-- WHERE id = @fernmeldewesen_id;
--
-- INSERT INTO wp_iss_entity_names (entity_id, name, normalized_name, name_type, language, is_primary, valid_from_year, valid_to_year, position, source_system, source_ref, created_at, updated_at)
-- SELECT @fernmeldewesen_id, 'Werk für Fernmeldewesen (WF)', 'werk-fur-fernmeldewesen-wf', 'primary', '', 1, NULL, NULL, 0, 'entity_title', CONCAT('entity:', @fernmeldewesen_id), UTC_TIMESTAMP(), UTC_TIMESTAMP()
-- UNION ALL
-- SELECT @fernmeldewesen_id, 'Werk für Fernmeldewesen (WF)', 'werk-fur-fernmeldewesen-wf', 'source_label', '', 0, NULL, NULL, 0, 'archive_title', CONCAT('entity:', @fernmeldewesen_id), UTC_TIMESTAMP(), UTC_TIMESTAMP();
--
-- COMMIT;
