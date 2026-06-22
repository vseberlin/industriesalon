# Handoff: Event Drop One-Off Intake/Bridge (Staging)

## Context and Status
- Objective: quick one-off implementation for collecting participant photos/videos for an event and exposing approved media on the event page.
- Scope completed: minimal operational workflow (intake endpoint + bridge + gallery output) with additional ad-hoc gallery styling.
- Environment assumption: staging system under `/srv/industriesalon/stage/`.

## What is implemented now
- Intake endpoint and storage:
  - Public intake endpoint accepts uploads and stores files in `shared/event-drop/storage/accepted`.
  - Accept/reject workflow writes status into `shared/event-drop/storage/manifests/upload-manifest.csv`.
  - Tokenized URLs are used for submitter/admin actions (current one-off: tokens in query params).
- Bridge pipeline:
  - WP MU plugin `app/wp-content/mu-plugins/event-drop-bridge.php` reads manifest, copies accepted originals into WP upload area, creates attachments for approved rows, and links attachments to Veranstaltungen by slug.
  - Duplicate imports are avoided by checking manifest state and existing attachment postmeta.
  - Derivative files (e.g., `-300x200`, `-scaled`) are now filtered so only originals are imported.
  - Event slug matching includes a fallback strategy for short identifiers (example: `fete` → `fete-de-la-musique-berlin-2026`).
- Shortcode rendering:
  - `[event_drop_gallery]` shortcode is present in `themes/industriesalon/templates/single-veranstaltung.html`.
  - Gallery outputs a horizontal scrollable strip with medium cards.
  - Clicking cards opens the media in a new browser tab (`target="_blank" rel="noopener noreferrer"`).

## Notable limitations (by design for quick one-off)
- Intake/bridge are not a durable CMS workflow; WP is currently used as publishing endpoint but intake remains operationally authoritative.
- Admin/state actions and tokens are still URL-based and currently rely on simple secret token checks.
- Token UX is user-unfriendly (complex values in links).
- Security hardening is partial (CSRF and token transport concerns remain).

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
6. Move styling to theme CSS and include pagination/lazy loading for large galleries.
7. Add operational docs for backup/restore of manifest and accepted/originals.

## Known file locations
- Theme template: `themes/industriesalon/templates/single-veranstaltung.html`
- Bridge plugin: `app/wp-content/mu-plugins/event-drop-bridge.php`
- Intake interface: `shared/event-drop/interface/index.php`
- Upload storage: `shared/event-drop/storage/accepted`
- Manifest: `shared/event-drop/storage/manifests/upload-manifest.csv`

## Notes for next person
- If intake files or manifest are moved, keep original file path in WP attachment meta for reproducibility.
- Keep one-off fixes isolated; avoid broad plugin replacements unless explicitly requested.
