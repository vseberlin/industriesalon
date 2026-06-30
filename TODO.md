# Project TODO

Immediate executable work only. Larger future programs live in `docs/project/backlog.md`; UAT-dependent work lives in `docs/project/uat.md`.

## Next

- Universal occurrence calendar/intake staging test: after pulling the pushed
  commit, live-test `/kalender/` grouped recurring `Termin wählen`, month
  navigation, available slot booking form, sold-out/unavailable date empty
  state, and publication order `intent=order` submission through
  `/iss-payments/v1/request`. Before production deploy, verify target mail mode
  and enable `Tools > ISS Anfragen` notification email only for an approved
  recipient.
- Native page JSON landing transfer/review: after deploying the landing code and
  theme assets, apply/review
  `ops/sql/2026-06-30-frontpage-landing-json.sql` only on a target with matching
  front-page content/media IDs. Then browser-check `/`, `/about/`, `/verein/`,
  `/salon-vermietung/`, and `/sammlungen/`; disabled or empty JSON must keep the
  existing template/post-content output. Before client handover, restrict the
  section treatment selector to admins without changing the JSON storage key.
- SuperSaaS/timeline follow-up after this checkpoint: deploy the occurrence and
  timeline code together, replay
  `ops/sql/2026-06-29-repair-cafe-canonical-event-series.sql` only if the local
  Repair-Café canonical content state should move to the target, then run
  `wp iss-occurrences sync`, `verify`, `drift-check`, and `supersaas-audit`.
  Current audit leftovers are non-blocking: unmapped inert series have zero
  occurrence rows, and `Stadtrallye für Erwachsene` is mapped but has no future
  SuperSaaS rows.
- Front-page client experiment decision: original baseline is captured in
  `ops/sql/2026-06-29-frontpage-baseline.sql`; current DB override content is
  synced to `themes/industriesalon/templates/front-page.html` for review. After
  the client finishes, either replay the baseline artifact to restore the old
  page or intentionally accept the current template and remove the DB override.
- Veranstaltung booking public render: the editor meta and
  `iss-commerce-lite` request endpoint exist, but single Veranstaltung output
  still needs a visible booking section/block in the theme template or
  `_iss_content_json` renderer. Add the public CTA/modal trigger so bookable
  Veranstaltungen are visible outside timeline cards.
- Related graph autonomy ops: after deploying the `iss-graph` autonomy slice,
  run `wp iss-graph migrate --skip-sync`, then configure a monitored external
  scheduler entry for `wp iss-graph reconcile --batch-size=50 --max-runtime=30`
  on production. Do not treat page-request WP-Cron as the unattended healing
  mechanism; verify with `wp iss-graph autonomy-health --format=json` and
  `wp iss-graph drift-check --checks=relation-integrity --limit=25`.
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
