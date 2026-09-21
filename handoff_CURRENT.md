# Current handoff — 2026-09-21

Website checkout: `/home/vladimir/wp-website`, branch `main`; read `AGENTS.md`
first. `/home/vladimir/wp` remains the preserved mixed archive checkout on
`archive/local-work-20260911`. Do not edit, merge or push that branch for website
work. Audit follow-up committed as **acee74f**; this checkpoint adds the landing
workspace. Use fresh Git refs before exchange. No deployment or publication.

## Current feature and next action

Rich-text landings now have a persistent outline, real theme preview and tabbed
inspector. Title/kicker and body/lead can be edited in place; input autosaves before
blur, frame replacement waits until editing finishes, and Escape restores the
active field. Native cursor-only links, named/custom colours and the existing
section/media/item/archive controls remain available. Generated content is
read-only in the canvas; individual item fields remain in the inspector.

Searchable insertion, preview gaps, keyboard/pointer reordering, recoverable
trash, device widths, responsive pane switching and workspace expansion share
existing state and save mechanics. **Weitere Werkzeuge** opens the previous
section view and revision history. Native WordPress Update/Publish remains the
publication action. Other formats keep the existing editor until staff UAT.
Details: [workspace plan](docs/project/editor-workspace-plan.md) and
[editorial platform](docs/architecture/editorial-platform.md#landing-rich-text-and-live-preview).

Next: staff acceptance using a section with image, title and text; either canvas
or inspector is valid. Firefox and real clipboard/paste remain unverified. No
new end-to-end latency claim. Target deployment/publication remain separate.

## Local pilot and verification

The `admin` author's private native autosave **27454** for homepage **12257**
(`home`) retains 12 v3 sections and two deleted sections. Canonical homepage
post/meta and the effective theme front-page template are unchanged, apart from
the normal browser edit lock. **Update** was not clicked. Reload and choose
**Entwurf weiterbearbeiten**:
`http://192.168.2.31:8082/wp-admin/post.php?post=12257&action=edit`.

- Storage/HTTP: **269 checks**, including **86 existing documents**, passed;
  existing content/meta unchanged and disposable fixtures removed. Includes
  registered workspace metadata/schematics, authenticated field markers, older
  versions, revision restore, invalid drafts and preview isolation.
- **25 editor DOM tests + 2 Set/upload tests** passed. New checks cover mounted
  field focus, old-frame object mapping after reorder, gap insertion, streamed
  edits, Escape, invalid/failed saves, message/session/sequence validation,
  finishing before reorder, and switching back through the legacy view.
- Targeted ESLint, Stylelint, PHPCS, PHPStan and PHP syntax checks passed.
- Chrome: native link insertion at the cursor, theme and exact custom colours
  in the canvas, draft persistence during active editing, title and rich-text
  cancellation, recovery, 800px/390px pane layout and 390px preview without
  horizontal overflow. Browser viewport restored. Public homepage: HTTP 200,
  one H1, no editing bridge or markers. Temporary test text/link/colours removed.

## Runtime and artifacts

- `wp_app` mounts plugins/themes from `wp-website` through the machine-local
  `/home/vladimir/.local/state/iss-editorial-pilot-20260921/website-code.yml`.
  Core, DB, uploads and event-drop mounts retain their original locations.
  Do not blindly restart base Compose; it restores the older source mounts.
- That directory contains the WP-CLI wrapper, verified pre-pilot SQL backup,
  canonical baseline, clean draft export and verification scripts. Do not rerun
  draft-preparation or cleanup scripts. `workspace-verify.php` compares the
  private draft to `workspace-baseline.json`, then checks canonical content,
  template and anonymous output. The workspace baseline preserves section 7's
  `text-bild-reihe.visual`, already observed in the first workspace read; the
  older follow-up baseline expected `compact`. Do not replay the older baseline.
- **No SQL migration or uploads artifact is needed** for this code delivery.
  Deploy `iss-content`, `iss-editorial` and the theme together. Retain v3 reader,
  sanitizer and renderer support on UI rollback. Moving the private composition
  needs a separate content/media dependency review.
- Preserve report **27388**, event **26813**, shared related-card recursion fix,
  Event Drop integration and existing Place migration/upload artifacts. No
  earlier migrations were replayed. See
  [Event Drop delivery](docs/runbooks/event-drop-staging.md#current-editorial-integration-2026-09-11).
