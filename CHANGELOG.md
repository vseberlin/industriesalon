# Changelog

This file records durable project changes. Keep it compact: current state belongs in
`handoff_CURRENT.md`, active follow-up in `TODO.md`, and detailed investigation can
be recovered from Git history.

## 2026-06-24

- Implemented the Veranstaltung Shape + Skin cleanup:
  - added registry-derived `iss-event-shape-*`, `iss-event-skin-*`, and normalized `iss-event-entity-*` body classes for singular Veranstaltungen;
  - added concrete Vortrag, Lesung, Gespraech, Repair, and Festival skins from the shape/skin mockup, with scoped typography, sidebar, color, layout, and programme treatments while leaving undesigned event entities on the baseline treatment;
  - made `galerie` the explicit image-gallery gesture with a framed carousel strip, removed image refs from `material`, and added `ops/sql/2026-06-24-veranstaltung-24988-material-gallery-split.sql` for the one existing material-image row;
  - replaced old `iss-event-format-*` renderer markup/CSS with the structured JSON class surface and removed stale festival-info styling;
  - removed completed legacy body import commands/helpers and the unused skin-override meta shell after all current Veranstaltung JSON documents passed review;
  - moved graph offer subtype sources to `_iss_entity_key` only, removing the remaining layout/format fallback path.
- Added the guarded Veranstaltung single-renderer slice:
  - marked the editorial review for `24988`, `13349`, and `25808` as passed in the active checkpoint;
  - added a theme-owned `_iss_content_json` renderer for those reviewed posts only, with legacy `post_content` fallback for every other Veranstaltung;
  - reused the existing `iss-content` structured document contract and central `industriesalon-steuerung` field API instead of adding a parallel route, template, or data layer.
- Widened the Veranstaltung structured renderer from a hard-coded reviewed-post list to every Veranstaltung with a valid sanitized `_iss_content_json` document, while preserving legacy `post_content` fallback for empty or invalid rows.
- Migrated the remaining Veranstaltung legacy bodies into structured JSON:
  - imported 22 additional `_iss_content_json` documents through the existing `iss-content` importer without overwriting the three reviewed documents;
  - brought the local Veranstaltung structured-content state to `25` stored, `25` valid, `0` invalid documents;
  - added `ops/sql/2026-06-24-veranstaltungen-content-json-full.sql` as the full transfer artifact for all current Veranstaltung structured-content JSON;
  - marked editorial review as passed for all 25 migrated Veranstaltung JSON documents.
- Moved Veranstaltung authoring off Gutenberg:
  - removed `veranstaltung` from the block-editor opt-in list and removed default editor support for the CPT;
  - promoted the existing `Struktur` JSON editor to the first high-priority normal editor surface while keeping title, publish/status, taxonomy/meta, and relation boxes available;
  - removed the obsolete theme-owned Veranstaltung Gutenberg panel for inserting Terminblatt block patterns.
- Removed the legacy Veranstaltung presentation switches:
  - removed active `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme` registration/save/UI/body-class/template decisions;
  - deleted the remaining local `wp_postmeta` rows for those legacy keys and added `ops/sql/2026-06-24-veranstaltungen-remove-legacy-presentation-meta.sql` for transfer;
  - removed migration-only legacy layout/format inference from the registry and dry-run command now that all current Veranstaltungen have curated `_iss_entity_key` values;
  - removed the old Terminblatt/Fest Gutenberg event patterns and kept a single baseline single-event CSS treatment until explicit skins/template work replaces it.
- Routed Veranstaltung related rails through the shape-aware repository:
  - fixed `iss_content_model_veranstaltungen_related()` to use the existing entity-related source contract and filter returned posts through registry-valid `_iss_entity_key` values;
  - delegated Veranstaltung-only related-content rails on Veranstaltung pages to that repository method while preserving manual related blocks;
  - kept the raw Veranstaltung query audit passing with only the approved repository/CLI query paths.
- Audited Veranstaltung archive/homepage/calendar projections:
  - confirmed the public block surfaces use the existing occurrence-backed `industriesalon/timeline-query` route rather than raw Veranstaltung post loops;
  - confirmed the Veranstaltung occurrence provider already gates synced rows to timeline-shaped entity keys;
  - left the existing projection owner in place instead of adding a parallel repository bridge.
- Added the sixth Veranstaltung JSON editor slice:
  - added `dynamic_refs` to the structured Veranstaltung section contract for centralized `industriesalon-steuerung` field references;
  - taught legacy import to preserve `industriesalon/field` blocks as references such as `address.full` instead of flattening address/opening-hours/link values into `_iss_content_json`;
  - added editor preview support that resolves current Steuerung values read-only while saving only the reference metadata;
  - imported post `25808` into `_iss_content_json` as a five-section `event.festival` candidate and added `ops/sql/2026-06-24-veranstaltung-25808-content-json.sql`.
- Tightened Veranstaltung archive-object refs:
  - stopped storing long Archivset member captions in `_iss_content_json`;
  - capped archive-object labels and rendered selected objects as thumbnail cards in the `Struktur` tray and preview;
  - refreshed post `24988` and `ops/sql/2026-06-23-veranstaltung-24988-content-json.sql` with the lean object-ref payload.
- Added `galerie` to the normal Veranstaltung gesture set so editors can collect image-heavy event posts in a dedicated gallery section instead of overloading prose or material sections.
- Added the editorial media bucket contract stub:
  - documented one shared private intake/review/promotion model for Veranstaltung, Ausstellung, Projekt, Publication, pages, and archive contexts;
  - kept `galerie` as an approved presentation section, not an editor dump area;
  - deferred UI/storage implementation and explicitly avoided separate `eventset`, `projectset`, or `publicationset` systems.

## 2026-06-23

- Added the first Veranstaltung JSON editor slice:
  - added an `iss-content` admin structure box for `_iss_content_json` on Veranstaltung edit screens without disabling the normal editor or changing public rendering;
  - limited available gestures by the selected `_iss_entity_key` registry contract and saved normalized section cards through the existing nonce-protected post save path;
  - tightened `veranstaltungen-content-audit` so empty meta rows are not counted as stored JSON documents.
- Added the second Veranstaltung JSON editor slice:
  - added `wp iss-content veranstaltungen-content-dry-run` to report legacy-body import candidates for all converted Veranstaltungen;
  - added guarded `wp iss-content veranstaltungen-import-candidate --post=<id-or-slug>` with dry-run default and `--yes` writes to `_iss_content_json`;
  - kept generated documents editor-only and reported media/unsupported blocks for curator review.
- Added the third Veranstaltung JSON editor slice:
  - taught the import candidate builder to recognize the Gutenberg event format sheet, skip its navigation block, and preserve sheet chapters as structured sections with kicker/title/body/material items;
  - imported post `24988` into `_iss_content_json` as a six-section `event.vortrag` document from the saved format sheet;
  - added the transfer artifact `ops/sql/2026-06-23-veranstaltung-24988-content-json.sql` for that reviewed local document while keeping public rendering on legacy `post_content`.
- Added the fourth Veranstaltung JSON editor slice:
  - added a read-only preview column to the existing `Struktur` box so editors can inspect the compacted `_iss_content_json` sections before saving;
  - kept the preview entirely in the `iss-content` admin editor script, with no public renderer switch and no new endpoint;
  - verified post `24988` still stores a valid six-section `event.vortrag` document.
- Added the fifth Veranstaltung JSON editor slice:
  - added media and archive-object reference support to the existing `Struktur` box, reusing WordPress media selection and the existing archive-object picker when available;
  - taught legacy content import to preserve WordPress image/media blocks as `media_refs` without storing local dev-host thumbnail URLs;
  - imported post `13349` into `_iss_content_json` as a one-section `event.vortrag` document with one media ref and added `ops/sql/2026-06-24-veranstaltung-13349-content-json.sql`;
  - left public rendering on legacy `post_content` and left the remaining `25808` dynamic `industriesalon/field` block for editor review.
- Completed the local Veranstaltung entity migration checkpoint:
  - curated all remaining Veranstaltung posts so `wp iss-content veranstaltungen-dry-run` now reports `25 safe`, `0 review`, `0 blocked`, and `25 converted`;
  - normalized missing or partial date facts using explicit curator input where needed and recorded inference notes on fallback dates;
  - moved post `25763` from `veranstaltung` to `fuehrung` as `Sonderführung` with on-demand Führung meta;
  - added the transfer artifact `ops/sql/2026-06-23-veranstaltungen-entity-migration.sql` for the source-state post/postmeta/taxonomy changes, with occurrence and graph/search projections regenerated after import.
- Added the seventeenth Veranstaltung entity-registry slice:
  - registered the `_iss_content_json` structured-content shell and hidden `_iss_skin_override` meta for Veranstaltungen;
  - added `wp iss-content veranstaltungen-content-audit` / `wp iss-content-model veranstaltungen-content-audit`;
  - kept stored documents optional and read-only for now, with no content import or renderer switch.
- Added the sixteenth Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-query-audit` / `wp iss-content-model veranstaltungen-query-audit`;
  - moved the theme menu's next-event data lookup behind the `iss-content` Veranstaltung repository;
  - made raw Veranstaltung post queries fail the audit outside the approved repository and curation tooling.
- Added the fifteenth Veranstaltung entity-registry slice:
  - registered normalized query fact meta for `_iss_datetime_start`, `_iss_datetime_end`, and `_iss_published_at`;
  - added save/set-entity synchronization from existing Veranstaltung date facts;
  - made required-fact validation prefer normalized facts while preserving legacy fallbacks.
- Added the fourteenth Veranstaltung entity-registry slice:
  - added a shape-aware Veranstaltung repository facade for upcoming, archive, reports, homepage teasers, related, and menu-event lookups;
  - filtered converted posts by `_iss_entity_key` through registry-derived primary surfaces;
  - kept unconverted legacy posts on the existing menu fallback only, so public behavior remains stable until curation.
- Added the thirteenth Veranstaltung entity-registry slice:
  - expanded the Veranstaltung registry with per-entity domain, post type, icon, and field contracts;
  - validated field names, field types, relation targets, and required shape facts in `veranstaltungen-registry-check`;
  - kept the new contract read-only for future generated authoring, with no renderer or content migration change.
- Added the twelfth Veranstaltung entity-registry slice:
  - taught the graph offer contract to prefer `_iss_entity_key` when set on Veranstaltungen;
  - added an `event_report` offer subtype for `report.rueckblick`;
  - kept unset legacy Veranstaltungen on the existing `_iss_event_layout` / `_iss_event_format` subtype fallback.
- Added the eleventh Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-registry-check` / `wp iss-content-model veranstaltungen-registry-check`;
  - exposed a standalone schema/entity/shape validation command for CI, staging, and pre-renderer checks.
- Added the tenth Veranstaltung entity-registry slice:
  - added `--status`, `--entity`, and `--missing-facts` filters to `veranstaltungen-dry-run`;
  - made review, blocked, converted, and entity-specific curation queues available without exporting the full audit table.
- Added the ninth Veranstaltung entity-registry slice:
  - extended `veranstaltungen-dry-run` table and JSON output with a guarded `set_command` recommendation per candidate;
  - kept generated commands dry-run by default by omitting `--yes`, so copied recommendations still validate before writing.
- Added the eighth Veranstaltung entity-registry slice:
  - exposed selected Veranstaltung `Typ` on singular event pages as stable theme body classes;
  - added `iss-event-entity-*`, `iss-event-shape-*`, and `iss-event-surface-*` only when `_iss_entity_key` is set;
  - left unset legacy posts on the existing layout/scheme/format body-class contract with no CSS or renderer changes.
- Added the seventh Veranstaltung entity-registry slice:
  - added a read-only legacy-derived Typ suggestion inside the Veranstaltung metabox;
  - centralized the suggestion helper so the editor hint and `veranstaltungen-dry-run` share the same confidence and conflict logic;
  - kept suggestions non-authoritative: editors still choose and save `_iss_entity_key` deliberately.
- Added the sixth Veranstaltung entity-registry slice:
  - added a `Typ` column to the Veranstaltung admin list table;
  - added a list-table filter for concrete Veranstaltung entity keys and unset posts;
  - kept the filter read-only over `_iss_entity_key`, giving editors a curation dashboard without changing public rendering.
- Added the fifth Veranstaltung entity-registry slice:
  - centralized Veranstaltung required-fact checks in the registry helpers;
  - made the Veranstaltung status box shape-aware once `Typ` is set, so event types check their date facts while `report.rueckblick` checks publication instead of event start;
  - switched the curation CLI to the same required-fact helper used by the editor status.
- Added the fourth Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-set-entity` / `wp iss-content-model veranstaltungen-set-entity` as the controlled write path for curated `_iss_entity_key` assignment;
  - kept the command dry-run by default, requiring `--yes` to write and warning/blocking on missing required facts unless `--force` is explicit;
  - wired successful writes to `iss_occurrences_sync_source()` when available, so setting `report.rueckblick` clears event occurrence rows through the normal provider path.
- Added the third Veranstaltung entity-registry slice:
  - taught the Veranstaltung occurrence provider to honor `_iss_entity_key` once set;
  - kept unset legacy Veranstaltungen on the existing `iss_programme_enabled` occurrence path;
  - made `report.rueckblick` produce no event occurrence, so saving a converted Rueckblick clears it from upcoming timeline surfaces without changing the single-page renderer.
- Added the second Veranstaltung entity-registry slice:
  - exposed `_iss_entity_key` as an editor-visible `Typ` radio group in the existing Veranstaltung metabox;
  - saved the semantic type independently from legacy `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme`;
  - added the type to the Veranstaltung status box while leaving current rendering, occurrence sync, and timeline behavior unchanged.
- Added the first Veranstaltung entity-registry slice:
  - registered a read-only Phase 1 entity/shape contract in `iss-content` with `_iss_entity_key` validation but no content migration;
  - added `wp iss-content veranstaltungen-dry-run` / `wp iss-content-model veranstaltungen-dry-run` to map legacy layout/format metadata to candidate event/report entities;
  - verified the local dry-run result: 26 Veranstaltungen, 0 safe auto-conversions, 15 review candidates, 11 blocked by missing `iss_start_datetime`.
- Added the compressed `Röhren für die Republik` Ausstellung JSON candidate:
  - saved and enabled the local `_iss_editorial_ausstellung` document for post `21108` with the `industrieakte` skin and 16 sections;
  - added source-backed fact-scale sections for Leningrad T2, research-file gaps, and the tube-to-semiconductor transition;
  - wrote paired transfer SQL at `ops/sql/2026-06-23-roehren-republik-editorial-json.sql`;
  - widened `industrieakte` `massstab` facts into a responsive grid for desktop/mobile review.
- Converted local `Kinder im Werk` to a curated Ausstellung JSON document:
  - registered the `kinder-im-werk` JSON skin and added its theme-owned stylesheet;
  - saved and enabled the local `_iss_editorial_ausstellung` document for post `26381`;
  - styled the skin's hero suppression, source excerpts, typographic quote, object-focus cards, album copy order, and stat gesture with existing shared/typographic treatments;
  - wrote paired transfer SQL at `ops/sql/2026-06-23-kinder-im-werk-editorial-json.sql`.
- Added a footer-oriented `register` layout variant for graph related-content rails and switched the single-Ausstellung tail to it.
- Replaced the single-Ausstellung tail from Ausstellung-only `iss/related-cards` to graph-backed mixed `iss/related-content`, and synced the local DB-backed template override.
- Moved Ausstellung practical metadata into the single-Ausstellung hero:
  - relocated the existing `iss/content-meta` block from the post-content intro slot into the hero overlay;
  - styled the existing `iss-info-panel--skin-aside` output as a compact dark hero facts rail without duplicating metadata markup;
  - synced the local DB-backed `single-ausstellung` template override to match the file template for immediate local review.
- Exposed Ausstellung editorial skin assignment in the custom composition editor:
  - added an `iss_editorial_format_skins` hook so the theme can provide allowed skins for a format;
  - registered the current Ausstellung skins from the theme owner;
  - added a single `Darstellung` selector that writes the document `skin` while keeping layout, variant, and section-role controls out of the editor.

## 2026-06-22

- Added phone/camera capture to the one-off Event Drop Uppy intake snapshot:
  - wired Uppy `Webcam` into the existing inline Dashboard/XHRUpload flow;
  - enabled native mobile/tablet camera capture for photo/video uploads;
  - kept the same `/event-drop/` upload endpoint, `media` field, moderation storage, CSV manifest, and WordPress bridge contract.
- Fixed the theme off-canvas menu shell after broad CSS changes:
  - capped the fixed shell to the visible viewport with safer viewport-unit fallbacks;
  - reset WordPress block-gap margins inside the shell so the menu no longer renders at roughly 1.5 viewport heights in Firefox;
  - distributed the Institution/Entdecken/Archiv sections evenly through the available menu height;
  - pinned the Heute/next-event status strip to the bottom of the menu viewport.
- Signed off Phase 1 of the Ausstellung editorial-platform SOW for authoring/save/reload after editor roundtrip proof:
  - kept autosave recovery deferred by decision;
  - renamed the explicit structure action from `JSON speichern` to `Speichern`;
  - styled the explicit structure save as a red editor-canvas button;
  - added a post-save reminder that JSON section content is updated by that button, while WordPress `Aktualisieren` is only needed for WordPress-owned fields such as title, slug, status, taxonomies, or other metabox data.
- Finalized Phase 2 for the current Ausstellung pilot boundary:
  - kept archive-object selection on the existing bucket-first archive picker and media selection on WordPress `wp.media`;
  - changed selected archive-object chips to show human labels with explicit remove buttons instead of clickable raw/fallback identifiers;
  - made archive-object selection refresh the canvas and schedule persistence immediately after picker confirmation;
  - recorded the SOW-wide Relation Picker as deferred until entity-editor work needs it.
- Restored Ausstellung decision/research links in the JSON editor path:
  - added a supported `links` field for `schluss` and `aside` gestures;
  - exposed repeatable link editing in the composition modal;
  - rendered section links as a theme-owned button rail;
  - taught the migration helper to preserve legacy Gutenberg navigation links;
  - updated the `Frauen im Werk` local JSON candidate and transfer SQL with the five legacy links.
- Advanced the editorial-platform SOW Phase 3 skin decision for the Ausstellung pilot:
  - kept skin, variant, layout, and section-role choices out of the main editor canvas so editors only add, edit, save, and reorder content sections;
  - changed the editor save path to preserve the internal JSON-rendering rollout flag without exposing it as a normal authoring control;
  - added theme-owned JSON skin resolution for `frauen-im-werk`, with reusable layout classes and the first `gesture x skin` treatments;
  - extracted the first `frauen-im-werk` treatment CSS into a dedicated theme skin stylesheet that loads only when the enabled editorial document resolves to that skin;
  - changed the default JSON renderer to emit universal section slots for media, copy, kicker, body, quote, and refs so skins can express live station-like treatments without one partial per gesture;
  - made `kicker` a first-class JSON section field in storage, editor UI, migration import, and theme rendering;
  - corrected the first `frauen-im-werk` source-station treatment to belong to the `quellenauszug` gesture, while `objektfokus` stays reserved for archive-object grids;
  - styled the `objektfokus` gesture as a dedicated dark archive-object grid for the `frauen-im-werk` skin, using the existing archive-card renderer with responsive two-column/one-column behavior;
  - adapted `bildstrecke` and `massstab` for the `frauen-im-werk` skin from the existing `Kinder im Werk` album/stat patterns, keeping the shared JSON renderer slots and avoiding new editor controls;
  - added two additional theme-owned editorial JSON skins, `typografisch` and `chronik`, as conditional Ausstellung stylesheets over the same universal gesture/layout classes for later real-content testing;
  - added the `industrieakte` editorial JSON skin for technical/industrial Ausstellung series, translating the WF/Röhren dossier mockup into theme-owned gesture treatments without copying its inline dashboard markup;
  - added a scoped `quellenauszug` text-position flip so source stations can render with text left or text right in the `frauen-im-werk` skin;
  - added quiet typographic treatments for existing `kapitel`, `leitfrage`, `zitat`, and `fliesstext` gestures using the generic renderer slots and CSS-derived chapter numbering, without adding parallel JSON fields or per-gesture partials;
  - made `vollbild` a generic one-image full-viewport treatment with a 16:9 editor hint, media-dimension refs, first-image renderer fallback, and dark ink-panel overlay;
  - added `ops/sql/2026-06-22-frauen-im-werk-editorial-json.sql` as the narrow transfer artifact for the local JSON pilot document and enabled flag;
  - added the SOW partial lookup path for future theme overrides before falling back to generic section rendering;
  - promoted the Ausstellung vocabulary with `vollbild`, `fliesstext`, and `schluss`, then migrated the local `Frauen im Werk für Fernmeldewesen` JSON pilot from generic `bildstrecke` sections to concrete poster-essay gestures while keeping its local JSON rendering enabled for review.

## 2026-06-21

- Added a shared theme button primary tier: `style.css` now exposes solid primary button tokens plus `.iss-button` variants and maps Gutenberg `is-style-fill` buttons to the solid tier, while the programme timeline skin uses the same primary tokens for booking actions.
- Reworked the public `/kalender/` wide-viewport skin: the file-backed calendar template now wraps the existing occurrence-backed `industriesalon/timeline-query` block in a theme-owned workbench, moves the block's existing filters into a left rail on wide screens, caps the result column, collapses recurring tour groups by default, hides oversized listing media on the calendar surface, and adds a right rail linking exhibitions back to the separate availability browser.
- Adjusted programme timeline kickers to set the shared kicker accent/color variables so the text, left rule, and dot follow the active timeline scheme together.
- Added the first `iss-editorial` vertical slice for the finalized editorial-platform SOW:
  - introduced an engine-only plugin for versioned editorial JSON documents, autosave, format/section registration, typed references, and normalized read models;
  - wired `iss-content` to opt `ausstellung` into the initial `OrderedFormat` pilot while preserving existing Gutenberg/meta content as the default fallback;
  - added archive-object picker integration for object-reference sections without exposing raw IDs to editors;
  - added theme-owned Ausstellung JSON rendering behind a per-post feature flag, with unresolved references omitted publicly and shown as preview/editor placeholders;
  - replaced the temporary Gutenberg sidebar/meta-box editorial UI with a custom main-canvas composition editor for `ausstellung`, using palette gestures, ordered section cards, and modal section editing;
  - added media selection to the composition modal via the WordPress media library, imported legacy exhibition images as `bildstrecke` media refs, added editable captions/removal controls, and rendered resolved media refs in the theme-owned JSON output;
  - normalized the archive-object picker toward the media-picker workflow: it now opens as a modal, starts from attached/context Archivset buckets, shows bucket members as thumbnail cards, keeps faceted object search as the secondary fallback, and preserves selected bucket/member provenance in editorial references;
  - stabilized the archive picker modal for editor use with inline/modal mode separation, bucket-first search guards, readable global-search result cards, fixed result scrolling, and theme-red selected-object accents;
  - added a REST save route for reviewed editorial JSON documents and their per-post enabled flag;
  - added a read-only `wp iss-editorial ausstellung-dry-run` report for `Kinder im Werk` and `Frauen im Werk` before any permanent JSON-rendering switch;
  - added `wp iss-editorial ausstellung-import-candidate --post=<slug-or-id>` and imported a disabled local JSON candidate for `Frauen im Werk für Fernmeldewesen` with 7 sections and 6 media refs while leaving public Gutenberg rendering active;
  - fixed editorial JSON meta writes to slash encoded JSON before `update_post_meta()`, preserving paragraph breaks and other escaped content through WordPress meta storage;
  - added `iss-editorial` to PHPStan paths/scanFiles so the repo's per-file target runner can see the new plugin symbols without include-file workarounds;
  - documented the boundary in `docs/architecture/editorial-platform.md` and added `iss-editorial` to the plugin map.
- Reworked static map framing for front-page and Führungen landing surfaces:
  - added a full-size baked `-17deg` Spree-horizontal derived map plus matching marker projection JSON;
  - registered `spree-horizontal-17` as a reusable theme map preset while keeping the unrotated canonical map as the source reference;
  - switched front page and `/fuehrungen/` spine strips to the baked preset with page-specific vertical crop and 1.14 cover zoom;
  - simplified spine-strip overlays so map markers carry the spatial meaning and the direction rail stays decorative;
  - hid raw rotation/horizontal-bias controls from the editor, kept zoom up to 200%, and exposed vertical crop as the author-facing framing control.

## 2026-06-15

- Made `register_place` public editor image groups authoritative for frontend featured-image rendering: public featured/fallback image-group selections now win through the WordPress thumbnail filter and save-time sync updates `_thumbnail_id` when the selected public image changes.
- Audited `/fuehrungen/elektropolis-tour/` route media behavior: the `iss/tour-route` block renders place-level public `archive_images`/`current_images` as station figures and keeps `station_object_id` as a separate detail card, so missing “Damals” images on that route are currently caused by private/missing place image-group data rather than a PHP render failure.
- Polished the `/ausstellungen/` browser interaction: added visible result summaries, debounced live search, a no-JS-capable clear-search link, filter URLs that preserve the current search term, and responsive control styling for the exhibition page skin.
- Closed the Atlas/static-map cleanup with a public-surface audit: first-class surfaces remain `iss/related-place-map`, `iss/atlas-slice`, `iss/spine-strip`, and `iss-register/schoneweide-atlas`; experimental static surfaces stay inserter-hidden; broader archive/graph API consolidation is deferred until a concrete consumer exists.
- Added fullscreen and kiosk layout states to the existing Schöneweide Atlas block: the Atlas now exposes embedded/fullscreen/kiosk controls, keeps the same REST/render path, invalidates Leaflet sizing on mode changes, and resets filters/map view after kiosk idle.
- Added focused contract/schema checks for Atlas REST payloads and static-map inputs: `iss-register contract-check` now validates Atlas place/context schema, and new `wp iss-relations static-map-contract-check` validates first-class map-block contracts plus static-map relation result/DTO shape.
- Finished the interactive Atlas runtime module split by extracting marker icon and map-marker orchestration into `atlas/markers.js`, leaving `schoneweide.js` as the bootstrap coordinator for config, payload loading, state creation, and module calls.
- Split the remaining interactive Atlas DOM-heavy renderers into focused modules:
  - added `atlas/detail.js` for popup/detail rendering and place media figures;
  - added `atlas/stories.js` for story intro, story cards, and fallback place cards;
  - added `atlas/relations.js` for relation rail rendering and mini-map projection;
  - wired the new detail/story/relation script handles before the existing public Atlas view handle, leaving the main runtime focused on bootstrap and marker/map orchestration.
- Split the interactive Schöneweide Atlas place/filter UI out of the main runtime:
  - added `themes/industriesalon/assets/js/atlas/places.js` for filter buttons, filter labels/counts, search/reset bindings, root filter attributes, summary/count rendering, and selected-place render context;
  - wired the new `iss-register-schoneweide-atlas-places` script between the store module and the existing public Atlas view handle;
  - left marker rendering, popup/detail UI, story cards, and relation rails in `schoneweide.js` for the next focused split.
- Continued modularizing the interactive Schöneweide Atlas runtime:
  - extracted payload normalization, derived era/actor maps, filter/selection state, selected story resolution, and relation scoring into `themes/industriesalon/assets/js/atlas/store.js`;
  - wired the new `iss-register-schoneweide-atlas-store` script before the existing public Atlas view handle;
  - kept the visible block, REST payloads, map adapter, and DOM rendering behavior unchanged while leaving place/filter UI and detail/story/relation rendering as the next split.
- Began modularizing the interactive Schöneweide Atlas runtime without changing the public block or REST payload:
  - extracted shared Atlas runtime core utilities, provider selection, payload/config loading, resize/layout sync, and the Leaflet map adapter into ordered theme JS modules;
  - kept the existing `iss-register-schoneweide-atlas-view` handle as the public view entrypoint with the new modules as dependencies;
  - left place filtering/list UI, detail/popup rendering, and story/relation panels in the main runtime for the next split.
- Defined the static-map DTO boundary between `iss-relations` and `iss-frontend`: block place selection now normalizes into a relation result with ordered static-map place DTOs, and `docs/architecture/static-map-rendering.md` records the contract keys.
- Split the `iss-relations` related-content block editor script into focused modules for editor context, place-source controls, related-card controls, static-map controls, spine-strip controls, and editorial-signal controls while keeping the existing block names, render callbacks, and `iss-relations-related-blocks` editor handle stable.
- Froze the first-class static map block surface:
  - kept `iss/related-place-map`, `iss/atlas-slice`, and `iss/spine-strip` as normal inserter-visible map blocks;
  - kept `iss/atlas-strip` and `iss/asymmetric-split-field` render-compatible but hidden from the inserter as experimental/non-current public surfaces.
- Documented static marker provenance and the manual marker update verification path in `docs/architecture/static-map-rendering.md`.
- Split static map block responsibilities:
  - added a shared `iss-relations` map-block source contract used by PHP defaults, render resolution, and editor settings;
  - moved static marker lookup, projection math, focus-window calculation, stage rendering, panel rendering, and static map frontend rendering entry points into `iss-frontend/modules/static-maps`;
  - kept thin compatibility wrappers in `iss-relations` while block callbacks delegate to the frontend renderer;
  - fixed map-block source resolution so implicit manual `placeIds` no longer depend on an out-of-scope rendered block object;
  - added `wp iss-relations map-block-audit` to check DB and file-backed static map blocks, selected-place marker resolution, and public coordinate-bearing `register_place` marker coverage against the same contract;
  - added missing derived static markers for published coordinate-bearing places that were absent from `schoneweide-static-markers-new.json`;
  - kept `industriesalon-schoeneweide-register` as the `register_place` and interactive Atlas data/cache owner;
  - documented the cleanup pattern in `docs/architecture/static-map-rendering.md`.
- Added the Atlas/static-map implementation plan in `docs/architecture/atlas-static-map-implementation-plan.md`, merging the local audit and peer review into one durable architecture plan.

## 2026-06-14

- Added conditional project status rendering:
  - introduced `iss/project-status` for project lists;
  - renders date ranges from project start/end meta when present;
  - falls back to completed state after end date, taxonomy status, then period label.
- Reconciled current editor/template drift:
  - copied current front-page text edits into the file template and removed its DB override;
  - flushed the current `page-projekte` DB template body to disk while leaving the DB override in place for later deletion.
- Moved `iss/dense-image-wall` to `iss-frontend`:
  - kept the block name stable for existing content;
  - split editor workflow into composition and text/link modes;
  - moved baseline block CSS out of the theme;
  - hardened render output for class and URL handling.
- Fixed booking CTA modal loading for tour/calendar surfaces by enqueueing the shared programme script where slot triggers render.
- Added transfer artifacts for the Walk of Fame dense wall content:
  - `ops/sql/2026-06-14-walk-of-fame-dense-wall.sql`;
  - `ops/uploads/2026-06-14-walk-of-fame-dense-wall-media.tar.gz`;
  - matching manifest and SHA256 files.
- Added transfer artifacts for the current `projekt` single-page content edits:
  - exports all seven published `projekt` posts plus their postmeta and term relationships;
  - normalizes local dev URLs in project content to root-relative paths;
  - pairs the SQL with a 28-file upload archive for directly referenced project media.

## 2026-06-13

- Compacted repository documentation:
  - reduced `handoff_CURRENT.md` and `TODO.md` to current operational checkpoints;
  - collapsed the content model architecture notes into `docs/architecture/entity-model.md`;
  - removed stale planning/audit documents and local backup archives;
  - kept `docs/project/decisions.md` and `docs/project/backlog.md` as the durable project record.
- Finalized occurrence/programme cleanup:
  - made `iss-occurrences` the owner of occurrence projection, public query readiness, recurrence grouping, and SuperSaaS ingestion;
  - removed graph/entity ID coupling from occurrence rows;
  - removed frontend dependencies from occurrence query helpers;
  - kept `iss-programm`/`iss-frontend` rendering-oriented.
- Reorganized plugin domains:
  - renamed functional plugin folders toward `iss-content`, `iss-frontend`, `iss-commerce-lite`, and `iss-graph`;
  - refreshed active-plugin SQL artifacts and removed obsolete plugin-folder references from docs.
- Hardened public request surfaces:
  - added rate limiting, body-size guards, nonce support, timing checks, honeypot handling, and light spam rejection logging for public commerce endpoints;
  - moved request/order storage from capped options to plugin-owned tables.
- Cleaned editorial calendar visibility:
  - renamed the UI concept from technical timeline wording to programme/calendar wording while preserving migration compatibility;
  - kept projects opt-in for programme projection;
  - made permanent and digital exhibitions opt-in for programme/calendar visibility.
- Continued graph/facade cleanup:
  - added graph hygiene checks for orphan membership edges, bad relation years, and duplicate alias tokens;
  - preserved typed relationship semantics for employment, construction/design, membership, and founding links;
  - kept consumer discovery read-only through facade/query helpers.
- Verification:
  - ran local PHP syntax checks for touched plugin/theme files;
  - ran focused PHPStan/PHPCS checks where configured;
  - ran WP-CLI drift, active-plugin, and facade/offer-consumer smoke checks where relevant;
  - ran `git diff --check`.

## 2026-06-12

- Built graph editorial signals and search influence:
  - added rating/confidence/visibility controls for canonical graph entities and relationships;
  - added materialized influence rows and search-weight support;
  - added admin export and CLI reporting for graph hygiene.
- Prepared and applied graph hygiene artifacts:
  - canonicalized WF/Industriesalon and KWO/AEG organization data;
  - backfilled alias replay artifacts;
  - removed orphan or semantically incorrect graph edges.
- Added facade and consumer guardrails:
  - kept offer consumers on read-only query helpers;
  - added route/audit tooling for old offer/availability consumers;
  - documented facade ownership in architecture docs.
- Moved tour/public rendering ownership:
  - collapsed Führung public templates back to the theme;
  - cleaned old page-template overrides and tour-template SQL artifacts;
  - added guards around SuperSaaS occurrence reactivation and legacy occurrence-origin purge.
- Retired legacy public-read routes and stale runtime surfaces:
  - removed old REST compatibility paths;
  - kept compatibility only where required for migration or local verification.
- Rewrote history to remove obsolete production newsletter SQL from the active branch.
- Verification included PHP syntax checks, WP-CLI smoke checks, PHPCS/PHPStan passes for touched slices, and `git diff --check`.

## 2026-06-11

- Established the greenfield content/facade checkpoint:
  - set `iss-content` as the editorial source;
  - kept public presentation in the theme and data/business contracts in plugins;
  - documented the source-of-truth architecture in `docs/architecture/entity-model.md`.
- Separated exhibitions from programme/calendar projection:
  - introduced strict programme opt-in for exhibitions/projects;
  - excluded availability-only exhibition types from automatic calendar projection;
  - added SQL artifacts for availability cleanup, strict programme toggle backfill, and legacy programme meta purge.
- Introduced `iss-occurrences` as the durable occurrence projection service:
  - replaced hidden calendar runtime behavior;
  - added occurrence admin/CLI tooling and public query helpers;
  - began migration away from legacy `iss_calendar_item` assumptions.
- Added first read-only availability/entity relation/offer facade slices.
- Verification included WP-CLI source checks, occurrence smoke tests, SQL artifact dry-runs, and PHP syntax checks.

## 2026-06-10

- Reworked key exhibition pages and transfer artifacts:
  - redesigned Frauen im Werk and Kinder im Werk content structures;
  - added related-content and care/skin improvements;
  - prepared media and SQL transfer artifacts under `ops/sql/` and `ops/uploads/`.
- Added SuperSaaS admin bootstrap and began tour availability integration work.
- Improved archive/publication page behavior and related-card presentation.
- Verification included local route checks, WP-CLI template/meta checks, and artifact validation.

## 2026-06-09

- Tightened the Ausstellung plugin/theme contract:
  - clarified plugin data ownership and theme presentation ownership;
  - removed hidden shortcode-style rendering expectations;
  - improved exhibition archive/editor behavior.
- Continued related-card and carousel contract cleanup.
- Verification focused on archive rendering, editor compatibility, and PHP syntax checks.

## 2026-06-08

- Improved exhibition editor controls and archive semantics:
  - added reusable editorial metadata controls;
  - refined exhibition visibility and type handling;
  - improved block/editor alignment for exhibition content.
- Continued CSS contract cleanup around archive/card rendering.

## 2026-06-07

- Audited archive, graph, project, publication, and video ownership:
  - kept public UI in the theme;
  - kept content contracts in plugins;
  - preserved Classic Editor policy unless explicitly opted into Gutenberg.
- Salvaged and restructured selected Kinder/Project content.
- Added and refined publication/video landing and timeline authoring work.

## 2026-06-06

- Added Sammlungen media/template transfer artifacts:
  - created SQL and upload delta artifacts for media sync;
  - adjusted public template behavior for collection pages.
- Migrated Ausstellung backend metadata:
  - added SQL cleanup/migration artifacts;
  - aligned backend metadata with frontend requirements.

## 2026-06-05

- Established the repository documentation model:
  - current checkpoint in `handoff_CURRENT.md`;
  - durable history in `CHANGELOG.md`;
  - active follow-up in `TODO.md`;
  - detailed operational docs under `docs/`.
- Prepared Repair Cafe and Sammlungen media/content transfer artifacts.
- Added operational closeout conventions for staging/local exchange.

## 2026-06-04

- Prepared staging content delta artifacts and supporting transfer notes.
- Continued front-page, archive, and template-authority checks.

## 2026-06-03

- Prepared production transfer artifacts for front-page and video transcript sync.
- Fixed front-page/menu-shell/newsletter rendering details.
- Added featured-image fallback behavior and event-template improvements.

## 2026-06-02

- Added local video transcription workflow support for the `video` CPT.
- Improved video block metadata and editor compatibility.

## Older History

- Added `veranstaltung` scheme/meta support and Terminblatt v1 templates.
- Built `register_place` atlas-led dossier templates and supporting patterns.
- Added `projekt` single-shell work and chaptered-prose planning.
- Added brochure/photoalbum publication templates and related-content rails.
- Built publication/video landing and reusable publication timeline authoring.
- Added shared related-content carousel/card contracts.
- Recovered and converted local media assets as needed for theme work.
- Introduced and evolved the ISS content-model, archive, graph, programme, and
  commerce/plugin stack now summarized in the architecture docs.
