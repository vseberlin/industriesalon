# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Next

- Publication JSON migration: after deploying the code, review/apply
  `ops/sql/2026-06-26-nef-album-publication-json.sql` only on a target that
  already has `nef-album` (`post_id=18973`), Archivset `19`, and the referenced
  NEF album archive-object/media rows/files. Then continue with chroniken /
  timelines and longreads using the explicit JSON gesture plus
  `publication_rail` contract.
- Project skin review: visually compare the three enabled local examples:
  `futura-biennale-2027` uses `dossier` with publication-style horizontal
  primary chapter navigation, centered 75rem chapter/fact spreads, and footer context,
  `walk-of-fame-schoeneweide` uses compact `brief`, and
  `stadtlabor-wilhelminenhofstrasse` uses `field` with the side index/context
  treatment. Also review project `galerie` and `material` on pages with
  multiple images/documents, then decide whether to assign `brief` or `field`
  to additional projects, continue the seven-page project review, and test the
  admin project Set flow: create/open/upload from the project edit screen,
  approve items in the Workbench, then promote mixed image/PDF selections into
  `galerie` plus `material`. Transfer order is
  `ops/sql/2026-06-25-project-editorial-json-candidates.sql` first,
  `ops/sql/2026-06-25-project-editorial-enable-json-review.sql` second, and
  `ops/sql/2026-06-25-project-skin-review-assignments.sql` only when the target
  should get the representative skin assignments.
- Project Set normalization transfer check: after deploying the Set resolver
  fix, inspect target Sets for project-shaped `event-drop-*` duplicates. If
  duplicates already exist there, run
  `wp eval 'print_r(iss_content_editorial_sets_normalize_project_duplicate_sets());'`
  after the code deploy so existing duplicate project Event Drop Sets merge into
  the canonical `project-set-<project-slug>` source Set.
- Replace the vanilla WordPress Dashboard with an ISS editorial/operations
  overview. Start read-only: inspect current Dashboard widgets and dashboard
  hooks, then hide the generic core widgets and add practical boxes for next
  Veranstaltungen, drafts needing review, Sets/uploads awaiting approval,
  missing featured images/excerpts, structured-content warnings, and quick
  links to Veranstaltungen, Sets, Medien, Ausstellungen, and Kalender.
- Veranstaltung JSON single-renderer slice: editorial review for `24988`,
  `13349`, `25808`, and the 22 legacy-fallback imports passed. All 25
  Veranstaltung posts now have valid `_iss_content_json`; the public renderer
  applies to every valid document. Veranstaltung authoring now avoids Gutenberg:
  the default editor is disabled and `Struktur` is the primary editor surface.
  Legacy Veranstaltung presentation switches are removed from active UI/meta
  registration/body classes. Theme-owned skins are active for `vortrag`,
  `lesung`, `gespraech`, and `festival`; `repair` is prepared for future
  `event.repair_cafe`; `workshop` and `praesentation` stay on the baseline
  `typografisch` skin until designed. `galerie` now renders as a public carousel
  strip, and `material` is reserved for documents/downloads/links/object refs.
  For transfer, follow
  `ops/sql/2026-06-24-veranstaltungen-transfer-instructions.md` and replay the
  full JSON artifact plus
  `ops/sql/2026-06-24-veranstaltungen-remove-legacy-presentation-meta.sql` and
  `ops/sql/2026-06-24-veranstaltung-24988-material-gallery-split.sql`.

## Active Work

- After deploying the Operations capability foundation, run
  `wp iss-core caps report` and confirm `Unknown role grants: 0` and
  `Missing role caps: 0` on the target. The role/capability DB state is applied
  by the versioned migration, not by a SQL artifact.
- To remove legacy commerce role state on a target, replay
  `ops/sql/2026-06-24-remove-shop-surecart-roles.sql` after deploying the
  Operations capability foundation. The artifact replaces `wp_user_roles` with
  the verified core plus ISS role set and creates
  `wp_user_roles_backup_20260624_shop_surecart_cleanup` first.
- Before transferring the local Veranstaltung entity migration elsewhere, apply
  `ops/sql/2026-06-23-veranstaltungen-entity-migration.sql`, then run
  `wp iss-occurrences sync`, `wp iss-graph sync-content`,
  `wp iss-graph sync-search`, `wp iss-content veranstaltungen-dry-run`,
  `wp iss-content veranstaltungen-repository-check`, and
  `wp iss-content tours-drift-check`.
- Before transferring the full local Veranstaltung structured-content migration
  elsewhere, apply/review `ops/sql/2026-06-24-veranstaltungen-content-json-full.sql`;
  it contains all 25 valid `_iss_content_json` documents and supersedes the
  smaller per-post content JSON artifacts for full-state transfer.
- Before transferring the `Zukunft im Gespräch` structured-content candidate
  elsewhere, apply/review
  `ops/sql/2026-06-24-veranstaltung-13349-content-json.sql`; the current
  `_iss_content_json` candidate is DB-backed local review state.
- Before transferring the `Fête de la Musique Berlin 2026` structured-content
  candidate elsewhere, apply/review
  `ops/sql/2026-06-24-veranstaltung-25808-content-json.sql`; its `Ort` section
  stores only `dynamic_refs` for the centralized Steuerung address field.
- Future media intake cleanup slice: add a cautious cleanup job for rejected or
  stale Event Drop raw files whose `decay_at` has passed, skipping retained
  items. First run should log/report candidates before deleting files. The
  current Fete test Set intentionally has all 24 raw uploads quarantined in
  `var/event-drop-storage/rejected`.
- Editorial platform next slice: curator-review `Frauen im Werk für Fernmeldewesen` in the custom editor with the `frauen-im-werk` JSON skin enabled locally. Clean up gesture choices, kickers, section text, captions, archive-object choices, and source/source-link details, then verify preview/frontend output.
- Apply/review `ops/sql/2026-06-23-roehren-republik-editorial-json.sql` on staging and curator-check the `Röhren für die Republik` facts, especially the derived Leningrad T2 tube totals, before production use.
- Continue live-testing `typografisch` and `chronik` Ausstellung JSON skins against real archival-source exhibition candidates before treating them as production-ready.
- Keep Ausstellung layout/gesture decisions out of editor controls while exposing only document skin assignment through `Darstellung`: editors add, edit, save, and reorder gesture sections; the theme renders each `gesture x skin` treatment through the universal section slots and dedicated skin CSS such as `themes/industriesalon/assets/css/skins/ausstellung-frauen-im-werk.css`.
- Before transferring the `Frauen im Werk` JSON pilot elsewhere, apply/review `ops/sql/2026-06-22-frauen-im-werk-editorial-json.sql`; the current skin assignment and section document are DB-backed local state.
- Review the `Kinder im Werk` JSON conversion before transfer; use `ops/sql/2026-06-23-kinder-im-werk-editorial-json.sql` for the DB-backed local document and enabled flag.
- Preserve the current ownership split: `iss-relations` resolves place/source contracts, `iss-frontend` owns frontend map rendering, `industriesalon-schoeneweide-register` owns register/interactive Atlas data, and the theme owns map assets/presets/skins.
- Before production deploy, verify target mail mode and enable `Tools > ISS Anfragen` notification email only for an approved recipient if request emails should leave the server.
- Before production deploy, reduce first-party dynamic block clutter: reconcile DB template overrides, move theme render-filter dependencies into plugin defaults where needed, hide unused legacy blocks from the inserter, migrate `industriesalon/program-cards` to `industriesalon/timeline-query`, and collapse related-content wrappers around one shared card renderer before deleting registrations.
- Delete the `page-projekte` DB template override after the flushed file template is verified on the target.
- Decide the long-term Führung route media contract: keep station archive objects as separate detail cards, or let selected `station_object_id` images fill the station “Damals” slot when the related place has no public `archive_images`.
- Treat staging as the current live working target, not a production-grade release gate. If staging breaks, rebuild/reapply from Git and known data artifacts.

## Other Active Work

- Resolve remaining `register_place` coordinate gaps:
  - `ITZ 4.0`
  - `Rahmenplan Oberschöneweide`
  - `IBA 2034 Berlin - Standort Oberschöneweide`
  - `Standortgemeinschaft Oberschöneweide`
  - `Treptow-Ateliers e.V.`
