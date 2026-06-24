# Current Handoff

Updated: 2026-06-24

Current checkpoint only. History belongs in `CHANGELOG.md`; active follow-up belongs in `TODO.md`.

## Current State

- GitHub `origin/main` is the exchange point for local/staging agents; check refs before pull/push. Staging is a working target, not a production release gate.
- Ownership: theme owns public templates/skins; `iss-content` owns CPT/editor contracts and the Veranstaltung JSON shell; `iss-editorial` owns Ausstellung JSON; `iss-archive` owns archive objects/Archivsets; `industriesalon-steuerung` owns central address/opening-hours/contact facts.
- Veranstaltung structured-content editor is active locally in the `Struktur` box and writes optional `_iss_content_json`. Public Veranstaltung pages use the theme-owned structured renderer for every valid `_iss_content_json` document; empty or invalid documents fall back to legacy `post_content`.
- Veranstaltung authoring now avoids Gutenberg like the Ausstellung editorial path: `veranstaltung` no longer opts into the block editor, default editor support is removed, and `Struktur` is the first high-priority normal editor surface. Title, publish/status, taxonomy/meta, place/person/archive relation boxes, and JSON save stay on the normal WordPress post screen.
- Legacy Veranstaltung presentation switches are no longer active: `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme` are not registered, saved, shown in the editor, emitted as body classes, or used for placeholder/template decisions. Local legacy rows for those keys were removed from `wp_postmeta`; replay artifact: `ops/sql/2026-06-24-veranstaltungen-remove-legacy-presentation-meta.sql`. Old Terminblatt/Fest Gutenberg event patterns were removed from theme registration and disk. The current single-event CSS keeps one baseline treatment until explicit skins/template work replaces it.
- All 25 current Veranstaltung posts now have valid, editorially passed `_iss_content_json` documents. The full transfer artifact is `ops/sql/2026-06-24-veranstaltungen-content-json-full.sql`.
- Local Veranstaltung review candidates:
  - `24988` (`New York, Rio - Schöneweide? Neue Nachbarschaften im globalen Vergleich`): 6 sections, media refs, one lean archive-object ref with thumbnail. Artifact: `ops/sql/2026-06-23-veranstaltung-24988-content-json.sql`.
  - `13349` (`Zukunft im Gespräch`): 2 sections and one `wp-media` ref (`11408`, label `fly_m-massarrat_04-1`). Artifact: `ops/sql/2026-06-24-veranstaltung-13349-content-json.sql`.
  - `25808` (`Fête de la Musique Berlin 2026`): 5 sections and one `dynamic_refs` Steuerung field (`address.full`) in `Ort`. Artifact: `ops/sql/2026-06-24-veranstaltung-25808-content-json.sql`.
- Editorial review for those three Veranstaltung structured-content candidates passed; public rendering is no longer hard-coded to their IDs, but only valid documents render.
- A first theme-owned structured Veranstaltung `the_content` renderer is active locally for all valid `_iss_content_json` documents, with invalid/empty documents and posts without structured JSON staying on legacy `post_content`.
- The 22 legacy-fallback imports also passed editorial review after migration; there is no remaining Veranstaltung JSON review queue at this checkpoint.
- Veranstaltung-only related rails on Veranstaltung pages now delegate to `iss_content_model_veranstaltungen_related()`, and that repository method filters returned posts through registry-valid `_iss_entity_key` values.
- Veranstaltung archive/homepage/calendar projections are already occurrence-backed through `industriesalon/timeline-query`; the Veranstaltung occurrence provider gates synced rows to timeline-shaped entities, so no parallel repository bridge was added for those block surfaces.
- Normal Veranstaltung types now allow `galerie`; `galerie` is approved presentation, not raw intake.
- Editorial media bucket contract stub is documented in `docs/architecture/editorial-media-buckets.md`; buckets are private intake/review state and public renderers consume only promoted refs.
- Existing Ausstellung JSON candidates remain DB-backed local review state; use their SQL artifacts from `TODO.md` before expecting target parity.
- Local dirty work also includes Event Drop snapshot files plus untracked `themes/industriesalon/theme2.json` and `iss-exhibition-composition-add.md`; do not stage them unless intentionally checkpointing that work.

## Current Risk

- Do not treat every `_iss_content_json` meta row as render-ready; the theme gate depends on the sanitized `iss-content` document and requires real sections.
- Do not copy Steuerung address/opening-hours/link values into event JSON; keep central facts as dynamic refs.
- Do not use `galerie` as unapproved dump storage; future bucket UI must stay private until items are promoted.
- Do not reintroduce Veranstaltung block-editor panels, Terminblatt block insertion, or legacy `_iss_event_layout` / `_iss_event_format` / `_iss_event_scheme` switches. Future visual variation belongs in explicit skin/template design.
- SQL artifacts are required to move local DB-backed candidates; create a target DB backup before import and replay code before content SQL.

## Next Action

- Think through the next Veranstaltung template/skin layer before adding visual variants; keep the current baseline renderer stable until a concrete skin contract is designed.
- Add gallery-specific frontend markup when gallery-heavy Veranstaltung documents appear; current valid documents can render without it.
- Later intake slice: implement private editorial media bucket UI from `docs/architecture/editorial-media-buckets.md`.
- For staging transfer, follow `ops/sql/2026-06-24-veranstaltungen-transfer-instructions.md`: deploy code first, import the full JSON artifact plus `ops/sql/2026-06-24-veranstaltungen-remove-legacy-presentation-meta.sql`, refresh occurrence/graph/search projections, then run the listed checks.
- If Event Drop work resumes, sync `ops/event-drop/interface/index.php` to the target host snapshot intentionally.

## Verified Locally

- Veranstaltung JSON editor/dynamic/gallery/archive cleanup passed JS syntax/ESLint, Stylelint, Docker PHP lint, PHPCS/PHPStan targets, `wp iss-content veranstaltungen-registry-check`, and `wp iss-content veranstaltungen-content-audit` (`stored=3 valid=3 invalid=0`).
- SQL replay and Playwright admin smokes covered `24988`, `13349`, and `25808`: object thumbnails render, `member_caption` is absent, `25808` stores only the `address.full` ref, and `Galerie` is available in the structure palette.
- Guarded Veranstaltung single-renderer slice passed Docker PHP lint, targeted Stylelint, targeted PHPCS/PHPStan, `git diff --check`, HTTP smokes, and Playwright desktop/mobile checks for `24988`, `13349`, `25808`, plus a non-allow-listed legacy event. The three reviewed posts render structured sections with no horizontal overflow; the legacy event remains unstructured.
- Renderer widening passed Docker PHP lint, targeted PHPCS/PHPStan, `wp iss-content veranstaltungen-content-audit` (`stored=3 valid=3 invalid=0`), DB inspection showing five published posts with `_iss_content_json` meta but only three valid section documents, HTTP smokes for the three valid documents plus two empty-meta rows and one legacy event, and Playwright desktop/mobile checks with no overflow or console errors.
- Full Veranstaltung legacy-content migration imported 22 additional `_iss_content_json` documents via `wp iss-content veranstaltungen-import-candidate --yes`, preserved the three reviewed documents, passed `wp iss-content veranstaltungen-content-audit` (`stored=25 valid=25 invalid=0`), confirmed `25 total / 0 missing valid JSON`, created `ops/sql/2026-06-24-veranstaltungen-content-json-full.sql`, and passed Playwright desktop/mobile checks across all 25 Veranstaltung routes with structured output, no overflow, and no console errors.
- Editorial review for all 25 migrated Veranstaltung JSON documents passed.
- Veranstaltung Gutenberg-removal/editor-surface slice passed Docker PHP lint, targeted PHPCS/PHPStan, runtime checks showing `veranstaltung_block_type=no` and `veranstaltung_editor_support=no`, metabox order check showing `Struktur` as the high-priority normal box, admin Playwright smoke confirming no block editor/no classic content editor, initialized Struktur cards, publish/relation/archive boxes present, valid hidden JSON, and no old Gutenberg panel asset loaded.
- Veranstaltung legacy presentation cleanup passed Docker PHP lint, targeted PHPCS/PHPStan, Stylelint, runtime checks showing `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme` are not registered, local DB cleanup showing no remaining grouped rows for those meta keys, admin Playwright smoke confirming no Darstellung wording/legacy inputs and `Struktur` first, `wp iss-content veranstaltungen-dry-run --format=json` (`25 safe`, `25 converted`), reference scans with no active old meta/body-class/pattern registrations, `git diff --check`, and Playwright desktop/mobile checks across all 25 Veranstaltung routes with structured output, no legacy layout/scheme body classes, and no overflow.
- Veranstaltung related-rail repository slice passed Docker PHP lint, targeted PHPCS/PHPStan, `wp iss-content veranstaltungen-query-audit`, `wp iss-content veranstaltungen-repository-check`, `git diff --check`, direct repository/render smokes, and Playwright desktop/mobile checks on one structured and one legacy Veranstaltung; both render three related Veranstaltung items with no horizontal overflow.
- Veranstaltung archive/homepage/calendar projection audit confirmed the public templates use `industriesalon/timeline-query`, the occurrence table contains only timeline-shaped Veranstaltung rows, query smokes for the front page, `/veranstaltungen/`, and `/kalender/` returned no non-timeline Veranstaltung records, and Playwright desktop/mobile checks showed populated timeline blocks with no scoped overflow or console errors.
- `24988` hidden JSON is about 2.5 KB; `13349` media preview resolves from attachment `11408`.
- Editorial media bucket contract stub is documentation-only and passed `git diff --check`.
