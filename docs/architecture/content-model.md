# Content Model

This repo uses WordPress editorial content as the primary authoring surface. Custom plugins define CPTs, fields, dynamic blocks, imports, and data contracts; the theme owns public composition and visual presentation.

## Ownership

- Theme: public templates, skins, layout composition, frontend CSS/JS, and editor-visible patterns.
- `iss-content`: shared CPT/editor/data contracts, including `register_place`,
  its shared Place editorial-format registration, and the former content-model
  and Führung module surfaces.
- `industriesalon-steuerung`: persistent institutional visit, address, contact, and notice facts.
- `industriesalon-schoeneweide-register`: transitional Place facts, legacy
  epoch/state projections, compact interactive-Atlas contracts, register tools,
  and admin workflows. It no longer owns the `register_place` CPT or Place
  narrative editor.
- `iss-archive`: archive ingest, normalization, projection, archive object runtime, assertions, evidence, and collection data.
- `iss-graph`: shared entities, names, relations, graph-backed profiles, and public search projection.
- `iss-relations`: relation queries and relation-aware blocks.

For first-party plugin boundaries, see `plugin-map.md`.

## Rules

- Prefer Gutenberg-editable content and reusable patterns for editorial surfaces.
- Keep CPT/data ownership in plugins and public presentation in the theme unless an existing plugin explicitly owns the renderer.
- Do not add shortcode-like workflows or hidden configuration when an editor-visible block or pattern can express the structure.
- Check DB `wp_template` authority before assuming a file template is live.

## Veranstaltungen overview

The Veranstaltungen template uses the existing `industriesalon/program-cards`
block's `presentation: programme` option. The occurrence query remains owned by
`iss-occurrences`, with selection rules in the existing `iss-frontend` programme
helpers; the theme composes the next appointment, subsequent date rows,
running programme ranges, current exhibitions and future exhibitions. Recurring
series show their next occurrence; the full calendar retains all dates. The
programme card variants extend `cards.css`; obsolete page intro/sidebar/formats
selectors were removed from `page-events.css`.

Editors maintain titles, featured images and native excerpts on the source
records. Excerpts are displayed as complete editorial summaries, without automatic
truncation. Existing material ticket links supply the primary ticket action.
Images retain their full composition, including embedded credits and poster text.
Past appointments use the existing paginated timeline, newest first; illustrated,
published Rückblicke linked to events/exhibitions use the shared related cards.
No report is generated merely because an event ended.

Rotation uses the configured WordPress timezone (Europe/Berlin on local/staging).
Known ends remain visible through that time. WP editorial events without an end
remain visible through their starting calendar day; no end is stored or inferred
for booking availability. Inclusive exhibition closing dates and explicit
open-ended flags remain authoritative. The query and history share these bounds.
Series are sorted before taking their next unexpired occurrence.

The nearest non-cancelled appointment leads. When it is over six weeks away (or
absent), a current exhibition leads as “Jetzt zu erleben”; later appointments
remain listed. The existing graph self-promotion signal can override selection
only with an active, unexpired `expires_at`. Event editors expose that date as
“Hervorheben bis”. A selected later appointment is labelled “Im Fokus”. Expired
or cancelled appointments cannot be promoted; ties use earliest occurrence.
Graph expiry remains UTC storage converted through the site timezone.

`iss-content` owns optional `iss_event_status` metadata (blank, `sold_out`,
`cancelled`), shown in the native event basis form. Occurrence presentation reads
it without a schema change or projection rebuild. Cancelled events stay in the
list and retain their detail URL; sold-out and cancelled overview entries offer
Details instead of Tickets. The detail page also carries the notice. This is
editorial display state, not a replacement for external ticket inventory.

Logged-in users with `edit_others_posts` see a native date-preview disclosure on
Veranstaltungen. A valid nonce and strict Berlin date/time are required. It changes
only the read-only occurrence query clock for that page; sources, booking and sync
clocks remain unchanged. Preview history shows its first page only, avoiding a
live-clock AJAX continuation. Calendar/detail links leave the preview.

Veranstaltungen and Kalender send no-cache headers and set `DONOTCACHEPAGE`.
Time-sensitive upcoming/history REST listings bypass transients so a request
cannot cross a date transition with stale cached membership. Rotation occurs on
page load, not as an animated carousel or a scheduled rewrite of content.
No migration/uploads artifact is required: metadata defaults blank, existing
promotion storage is reused, and no existing content/media/source date changes.
Read-only checks: `tests/e2e/bin/programme-overview.php` and
`tests/e2e/bin/programme-rotation.php` via WP-CLI `eval-file`.
