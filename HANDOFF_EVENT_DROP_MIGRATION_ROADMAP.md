# Handoff: Event Drop Durable Migration Roadmap

## Business goal
Move from the current one-off event media collection flow to a durable, low-friction, admin-manageable ingestion pipeline while preserving staging behaviour and avoiding disruption.

## Current state snapshot (to preserve)
- Intake runs via shared PHP endpoint in `/srv/industriesalon/stage/shared/event-drop/interface/` with manifest-driven status.
- Sync is handled by MU plugin `app/wp-content/mu-plugins/event-drop-bridge.php`.
- Event rendering uses `[event_drop_gallery]` in `themes/industriesalon/templates/single-veranstaltung.html`.
- Bridge currently imports approved rows into WP media and filters derivative files.

## Target architecture
- Intake remains source of truth (upload metadata, moderation status, consent/license/uploader IDs).
- WP remains publish endpoint only.
- Introduce durable “sync state” as explicit contract between systems.
- Keep originals outside WP; only derived/serving copies in WP uploads.

## Recommended phased migration

1. Phase 0 — Stabilization (1 week)
- Lock current behaviour (no user-visible changes).
- Add operational docs:
  - backup/restore of manifest + accepted files
  - recovery for failed sync rows
- Add minimal monitoring:
  - accepted count / processed count / errors per run
  - unresolved events

2. Phase 1 — Durable intake data model (1–2 weeks)
- Replace CSV manifest as source-of-record with SQLite/MySQL table in the intake service.
- Normalize tables:
  - `events` (event external id, slug, mapping)
  - `assets` (id, filename, hash, uploader, consent/license, status)
  - `sync_jobs` (asset_id, destination status, last_attempt, last_error)
- Add idempotency by content hash + asset id.

3. Phase 2 — Moderation and token UX (1 week)
- Replace URL-secret pattern with short, revocable “invite codes” mapped server-side.
- Add token abstraction + admin-only long-lived endpoint.
- Admin UI should support:
  - one-click approve/reject
  - bulk actions
  - search/filter by event/uploader/status
  - consent/license validation state

4. Phase 3 — Secure bridge sync service (2 weeks)
- Harden bridge with strict allowlist and MIME validation.
- Add explicit reconciliation job with per-row checksum and retry policy.
- Add queue/debounce to avoid duplicate imports on repeated runs.
- Keep metadata mapped minimally to WP:
  - event id/slug
  - uploader attribution
  - attribution/license/consent flags
  - source path/reference

5. Phase 4 — Gallery and delivery hardening (1 week)
- Move inline styling to stylesheet.
- Add pagination/infinite lazy loading for large events.
- Cache-aware rendering and alt text fallback.
- Add optional ZIP download tokenized endpoint for admins.

6. Phase 5 — Production cutover (hardening 1 week)
- Dry-run rehearsal on staging with real event.
- Diff reconciliation report before go-live.
- Rollout window with rollback:
  - disable bridge cron
  - restore from git + previous manifest snapshot if needed
- Post-release tuning of permissions/logging.

## Security hardening priorities (must-do during migration)
- CSRF protection on state-changing admin actions.
- Authenticated admin controls (no GET mutate links).
- Token scoping: per-event + expiry + revocation.
- Signed links for any public share/download action.
- Strict file validation: extension + MIME + server-side ffprobe/imagemagick probing for media.
- Rate limiting + abuse monitoring on upload/intake endpoints.
- File quarantine checks before publish (malware/invalid header checks where practical).

## Acceptance criteria by release gate
- Intake accepts and stores files for at least one event.
- Moderation flow approves/rejects records with immutable audit log.
- Bridge sync imports only approved originals once.
- Gallery renders approved items only, no placeholder or derivative leakage.
- Failed rows produce actionable, timestamped logs and are retryable without duplicates.
- 10k+ files can be processed in batches without timeout or duplicate side effects.

## Operational runbooks
- Recovery:
  - Re-run sync on specific event
  - Mark asset failed/successful manually
  - Rebuild missing attachments from intake originals
- Monitoring checks:
  - daily manifest/sync parity
  - missing attachment + broken-link scan for public event pages

## Suggested ownership model
- Product owner: approves scope and workflow rules.
- Backend engineer: intake API + sync service + db model.
- WordPress engineer: shortcode/template surface and attachment mapping.
- Security/ops: authz, abuse controls, backups, incident playbook.

## Open dependencies
- Confirm final legal text requirements for consent/license fields.
- Confirm who can approve (WP editors only / event staff).
- Confirm required retention window for accepted originals.
- Confirm whether public downloads of full-res are allowed or only preview-first.
