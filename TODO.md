# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Next

- Correct the archive landing quick-navigation grid at phone widths: its four
  columns overflow (409 px content / 375 px viewport). The shared archive cards
  already fit; fix the quick-navigation owner without changing their layout.
- Complete the remaining [maintenance activation](docs/runbooks/staging-maintenance.md)
  in a separately coordinated operational slice. Website code, taxonomy/owner/
  commerce migrations and the full local content/uploads/intake snapshot were
  deployed on 16 October; project UAT is ready with matching local IDs. Review
  current maintenance receipts and cron ownership after the full refresh.
  Scheduler/retention and scoped FPM/Nginx limits remain separate. Preserve the
  full-sync rollback backups and inspect mail mode before any due-job run.
- Staff-test the [shared workspace](docs/project/editor-workspace-plan.md)
  on the deployed staging release: one landing, project, tour,
  publication, event and each additional article CPT; image/title/text,
  owner controls, recovery and native Save Draft. Verify Firefox and external
  Word/Docs paste before a production release; controlled rich-text clipboard
  paste passed locally in Chrome. The 13 October panel/CPT changes
  are deployed; authenticated staging staff UAT remains. Include event opening
  controls and written-feedback moderation. Repeat the two tour editor choices,
  optional Sonderführung label and native save/reload on staging. Confirm whether
  Waldfriedhof should also be public; it currently has the reviewed group assignment.
  See the [audit](docs/project/editorial-consolidation-audit.md).
- Review the interactive Atlas outlier `Energie-Museum Berlin`: its current
  authoritative coordinate is far west of the Schöneweide core while its stored
  area is `Oberschöneweide`. The expanded navigation boundary deliberately
  exposes the record instead of hiding it; confirm whether the coordinate and
  Atlas membership are editorially intended before staging sign-off.
- Führung `bildbuehne` stage gesture content/UAT follow-up: create or migrate
  at least one real Führung stage section in content, then browser-check
  desktop/mobile that the full-viewport image stage, compact gallery, and right
  booking rail render correctly. Booking, dates, facts, and the required
  relation network must remain outside the gesture body.
- Universal occurrence calendar/intake staging test: after pulling the pushed
  commit, live-test `/kalender/` grouped recurring `Termin wählen`, month
  navigation, available slot booking form, sold-out/unavailable date empty
  state, and publication order `intent=order` submission through
  `/iss-payments/v1/request`. Before production deploy, verify target mail mode
  and enable `Tools > ISS Anfragen` notification email only for an approved
  recipient. Publication orders now share the native request dialog locally; include this
  changed presentation in staff acceptance after deployment.
- Native page JSON landing UAT: code, content and media were included in the
  full 16 October staging refresh. Browser-check `/`, `/about/`, `/verein/`,
  `/salon-vermietung/`, and `/sammlungen/`; disabled or empty JSON must keep the
  existing template/post-content output. UAT gesture reordering, click/gap
  insertion from the compact chooser, keyboard controls and the switching
  inspector before client handover. Restrict landing treatment controls to
  admins before client handover.
- CSS layering follow-up during JSON gesture polish: migrate page-specific
  landing/front-page rules into the new layer order gesture by gesture.
  `statement.lead` / `statement.leitfrage` and the renamed
  `feature.image-overlay` treatment are on renderer-treatment selectors; next
  move `gateway` and remaining `feature` treatment styles toward
  renderer-contract selectors before deleting front-page compatibility
  selectors. Migrate `/salon-vermietung/` “Der Ort” from `iss-rental-story`
  into `feature.media-text` with compact text / larger image ratio instead
  of adding a rental-only 50/50 variant. Each touched legacy/page stylesheet
  should be migration-positive:
  drain reusable rules into the proper layer and remove obsolete selectors when
  safe.
- SuperSaaS/timeline follow-up: local occurrence/timeline code and data are now
  on staging. Verify current drift and `supersaas-audit` before scheduling any
  source refresh; do not replay older SQL over the full 16 October snapshot.
  Current audit leftovers are non-blocking: unmapped inert series have zero
  occurrence rows, and `Stadtrallye für Erwachsene` is mapped but has no future
  SuperSaaS rows.
- Repair-Café staging schedule: recheck intended future dates against the
  full local snapshot delivered on 16 October; the September target observation
  is obsolete. Do not blindly replay the June canonical-event SQL. The Apache
  stack on `8082` is the active local website, not a retirement candidate.
- Front-page client experiment decision: original baseline is captured in
  `ops/sql/2026-06-29-frontpage-baseline.sql`; current DB override content is
  synced to `themes/industriesalon/templates/front-page.html` for review. After
  the client finishes, either replay the baseline artifact to restore the old
  page or intentionally accept the current template and remove the DB override.
- Related graph autonomy ops: include `wp iss-graph migrate --skip-sync` and
  owner health/drift verification in the coordinated release above. Use the
  single maintenance runner for graph reconciliation; do not add a second
  independent cron worker or rely on page-request WP-Cron.
- Graph/native relation coverage: Veranstaltung venue is now harvested from
  native `iss_primary_place_id` into `content_native` graph edges. Add
  CPT-specific native person/organization harvesters only where a real
  intrinsic field exists; keep the content-bridge person/organization box as
  optional curation, not a required relatedness field.
- Editorial admin simplification: the shared classic/editorial dashboard layout
  is implemented for `veranstaltung`, `projekt`, `ausstellung`, `publication`,
  `fuehrung`, and `rueckblick`. Required facts are now first-row
  `Pflichtangaben`, shared linked-content controls live in the right rail where
  their owner metabox exists, and normal editors get only the simple promotion
  toggle. Veranstaltung has the current polish slice: structural
  `Struktur` plus semantic `Art`, compact status, locked Screen Options/postbox
  controls for editors, no default Posts/Pages editor navigation, and no
  WordPress category dropdown on the Veranstaltung list. Remaining work should
  be content-specific: decide Publication related-publications UX, decide
  whether Rueckblick needs additional relation owner controls, and design a
  separate Gutenberg adapter for `page`, `post`, and Video if those screens
  should enter the shared workflow. Before hiding or purging more legacy boxes,
  run edit/save/reload parity with a normal editorial role.
- JSON editor maintainability cleanup after modal/drag-drop UAT: split
  picker-heavy code out of `plugins/iss-editorial/assets/admin.js`.
  `assets/dnd.js` now owns shared drag/drop and `assets/ui.js` owns the modal
  shell/panel primitives. Archive object picking, media tray/picker behavior,
  gateway item media, and album/source sheet import are good first extraction
  candidates; keep the main file focused on document state, section rendering,
  save/preview wiring, and editor orchestration.
- Archive provenance intake/browse pipeline: bring the local WF archive
  reconstruction work into the WP archive line owned by `iss-archive`, starting
  with provenance to museum-digital before DDB/Europeana. Source artifacts live
  under `/home/vladimir/Archive-restoration/`:
  `archive_index.sqlite`, `reconcile_museum_digital.py`, and
  `md_provenance_report.md`. Next slice: model/import a read-only provenance
  chain from local TIFF/master paths and derivatives to archive object,
  museum-digital ID/JSON, source metadata row, and browse/report views for
  complete chains, missing local masters, invalid MD fields, and unfetched/503
  MD records. Keep `/mnt/volume` read-only and keep high-resolution masters
  outside WordPress media-library authority; WP should store/import provenance
  records and preview/browse references, not become the master-file store.
- Führung JSON migration: after deploying the code, review/apply
  `ops/sql/2026-06-27-fuehrung-editorial-json.sql` only on a target that
  already has the 15 published Führung posts listed in the artifact. The SQL
  writes only `_iss_editorial_fuehrung` JSON documents and enabled flags; no
  upload artifact is required. After applying it, spot-check representative
  tour routes because route stations, facts, booking, dates, Atlas map, and
  related content still come from their existing meta/block contracts.
- Publication JSON migration: after deploying the code, review/apply
  `ops/sql/2026-06-26-nef-album-publication-json.sql` and
  `ops/sql/2026-06-26-photoalbum-blueprint-other-albums.sql` only on a target
  that already has the four publication posts (`18973`, `18894`, `18948`,
  `19038`), Archivset `19` for `nef-album`, the referenced archive-object/media
  rows/files and Media Library attachments, plus register place `17976`
  (`Ostendstraße 1-5 / Behrensbau`). The current vocabulary-normalized transfer
  state is captured in
  `ops/sql/2026-06-27-editorial-vocabulary-normalized-json.sql`; use canonical
  `bildmatrix` skin assignments, not the old `blueprint-matrix` slug. For
  existing chroniken / timelines and longreads, review/apply
  `ops/sql/2026-06-27-publication-longread-timeline-json.sql` only after the
  code deploy on a target that already has the 12 publication posts and
  referenced timeline/longread image attachment rows/files. This artifact now
  assigns `longread-poster` to all nine migrated longreads. Curator-review the
  migrated drafts/stub in the JSON editor before publishing, especially `18873`
  because the local source content has no dated timeline stations; for
  longreads, add/reorder `longread_quote` moments and chapter `media_refs`
  where the story needs poster imagery.
- Project skin review: visually compare enabled local `dossier` examples,
  including `futura-biennale-2027`, `walk-of-fame-schoeneweide`, and
  `stadtlabor-wilhelminenhofstrasse`, with their rail feature settings instead
  of the retired `brief` / `field` skin slugs. Also review project `galerie`
  and `material` on pages with multiple images/documents, continue the
  seven-page project review, and test the
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
  registration/body classes. Theme-owned Veranstaltung defaults now collapse to
  canonical `typografisch`, `buehne`, and `chronik`; event taxonomy remains the
  semantic type, not the skin. `galerie` now renders as a public carousel strip,
  and `material` is reserved for documents/downloads/links/object refs.
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
- Editorial platform next slice: curator-review `Frauen im Werk für Fernmeldewesen` in the custom editor with the canonical `objektalbum` JSON skin enabled locally. Clean up gesture choices, kickers, section text, captions, archive-object choices, and source/source-link details, then verify preview/frontend output.
- Apply/review `ops/sql/2026-06-23-roehren-republik-editorial-json.sql` on staging and curator-check the `Röhren für die Republik` facts, especially the derived Leningrad T2 tube totals, before production use.
- Continue live-testing `typografisch` and `chronik` Ausstellung JSON skins against real archival-source exhibition candidates before treating them as production-ready.
- Keep Ausstellung layout/gesture decisions out of editor controls while exposing only document skin assignment through `Darstellung`: editors add, edit, save, and reorder gesture sections; the theme renders each `gesture x skin` treatment through the universal section slots and dedicated skin CSS such as `themes/industriesalon/assets/css/skins/ausstellung-quellenbuehne.css` and `themes/industriesalon/assets/css/skins/ausstellung-objektalbum.css`.
- Before transferring the `Frauen im Werk` / `Kinder im Werk` JSON state elsewhere, prefer the normalized artifact `ops/sql/2026-06-27-editorial-vocabulary-normalized-json.sql`; it contains the canonical `objektalbum` / `quellenbuehne` skin assignments and canonical gesture names.
- After an authorized release, staff-review Place editing with Kino Spreehöfe,
  KAOS 93 and a sparse Place: facts/media permissions, optional epochs, preview,
  save/reload, on-demand **Automatische Inhalte → Wissen zur Epoche beitragen**,
  and explicit intake opening/closure plus private/text moderation. All 84 local
  Places now use JSON; target conversion uses the guarded 14 October artifacts
  in `handoff_CURRENT.md`, with the paired TIFF/JPEG preview artifact. Preserve
  target editorial changes and the single Place contribution Set. Keep the
  legacy register plugin until graph actors/identity move to `iss-graph`,
  interactive Atlas runtime moves to `iss-frontend`, and observation proves
  compatibility tables removable. Investigate Atlas warm TTFB separately.
- Before production deploy, verify target mail mode and enable `Tools > ISS Anfragen` notification email only for an approved recipient if request emails should leave the server.
- Before production deploy, reduce first-party dynamic block clutter: reconcile DB template overrides, move theme render-filter dependencies into plugin defaults where needed, hide unused legacy blocks from the inserter, migrate `industriesalon/program-cards` to `industriesalon/timeline-query`, and collapse related-content wrappers around one shared card renderer before deleting registrations.
- Delete the `page-projekte` DB template override after the flushed file template is verified on the target.
- Treat staging as the current live working target, not a production-grade release gate. If staging breaks, rebuild/reapply from Git and known data artifacts.

## Other Active Work

- Resolve remaining `register_place` coordinate gaps:
  - `ITZ 4.0`
  - `Rahmenplan Oberschöneweide`
  - `IBA 2034 Berlin - Standort Oberschöneweide`
  - `Standortgemeinschaft Oberschöneweide`
  - `Treptow-Ateliers e.V.`
