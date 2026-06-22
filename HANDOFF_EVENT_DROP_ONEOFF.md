# Handoff: Event Drop One-Off Intake/Bridge (Staging)

## Context and Status
- Objective: quick one-off implementation for collecting participant photos/videos for an event and exposing approved media on the event page.
- Scope completed: minimal operational workflow (Uppy intake frontend + moderation endpoint + immediate bridge sync on accept + editable event gallery output).
- Environment assumption: staging system under `/srv/industriesalon/stage/`.

## What is implemented now
- Intake endpoint and storage:
  - Public intake endpoint uses Uppy Dashboard + XHRUpload and stores uploads for moderation under `shared/event-drop/storage/incoming`.
  - Accept/reject workflow moves files into `accepted`/`rejected` and records upload metadata in `shared/event-drop/storage/manifests/upload-manifest.csv`.
  - Friendly short-code URLs are used for submitter/admin/shared actions (current one-off: codes in query params).
- Bridge pipeline:
  - WP MU plugin `app/wp-content/mu-plugins/event-drop-bridge.php` reads manifest, copies accepted originals into WP upload area, creates attachments for approved rows, and links attachments to Veranstaltungen by slug.
  - Duplicate imports are avoided by checking manifest state and existing attachment postmeta.
  - Derivative files (e.g., `-300x200`, `-scaled`) are now filtered so only originals are imported.
  - Admin accept triggers `event_drop_bridge_sync_from_intake()` immediately, so approved media does not wait for WP cron.
  - Event slug matching includes a fallback strategy for short identifiers (example: `fete` -> `fete-de-la-musique-berlin-2026`), but current intake defaults to the full event slug.
- Shortcode rendering:
  - `[event_drop_gallery event_slug="fete-de-la-musique-berlin-2026" limit="24"]` is placed in the editable content of the Fête Veranstaltung, not globally in `single-veranstaltung.html`.
  - Gallery outputs a horizontal scrollable strip with medium cards and respects per-attachment hide/order metadata.
  - Clicking cards opens the media in a new browser tab (`target="_blank" rel="noopener noreferrer"`).

## Notable limitations (by design for quick one-off)
- Intake/bridge are not a durable CMS workflow; WP is currently used as publishing endpoint but intake remains operationally authoritative.
- Admin/state actions and tokens are still URL-based and currently rely on simple secret token checks.
- Security hardening is partial (query-code transport and non-WP intake admin actions remain one-off compromises).

## What should be done for durable migration
1. Centralize metadata model:
   - Keep intake DB as source of truth.
   - Persist only canonical fields in WP attachment (file reference, event reference, attribution, license, uploader).
2. Replace query-string admin actions:
   - Move admin operations behind authenticated WP or hardened API routes.
3. Introduce durable admin role/portal and audit log.
4. Harden token handling:
   - use short aliases + mapped secrets server-side or signed one-time links.
5. Add idempotent reconciliation job with explicit reconciliation state and manual re-sync controls.
6. Move inline Uppy/gallery/admin styling to versioned assets and include pagination/lazy loading for large galleries.
7. Add operational docs for backup/restore of manifest and accepted/originals.

## Known file locations
- Theme template: `themes/industriesalon/templates/single-veranstaltung.html`
- Deployed bridge plugin: `app/wp-content/mu-plugins/event-drop-bridge.php`
- Deployed intake interface: `shared/event-drop/interface/index.php`
- Committed bridge snapshot: `ops/event-drop/mu-plugins/event-drop-bridge.php`
- Committed intake snapshot: `ops/event-drop/interface/index.php`
- Upload storage: `shared/event-drop/storage/accepted`
- Manifest: `shared/event-drop/storage/manifests/upload-manifest.csv`

## Notes for next person
- If intake files or manifest are moved, keep original file path in WP attachment meta for reproducibility.
- Keep one-off fixes isolated; avoid broad plugin replacements unless explicitly requested.
