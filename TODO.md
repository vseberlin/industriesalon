# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Next

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
- Future media intake slice: implement the shared intake/workbench SOW in
  `docs/architecture/editorial-media-buckets.md` as a private review and
  promotion workflow. Use editor-facing named Sets, allow uncategorized intake
  and context-free preparation Sets, support moving items between Sets and
  attaching/promoting whole Sets to multiple CPT targets, keep raw uploads
  temporary/decaying unless retained or promoted, make Rueckblick a first-class
  promotion target, and hand archive candidates to `iss-archive` for stricter
  curation. Do not make `galerie` an unapproved photo dump; promote only
  approved Set items into `media_refs` / `object_refs`.
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
