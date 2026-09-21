# Current handoff — 2026-09-21

Website checkout: `/home/vladimir/wp-website`, branch `main`; read `AGENTS.md`
first. `/home/vladimir/wp` remains the preserved mixed archive checkout on
`archive/local-work-20260911`. Do not edit, merge or push that branch for website
work. Its archive history and working files remain intact.

## Current local feature

The landing/front-page pilot now has compact rich text, labelled native link
editing/search, theme/custom text colours, highlighting and visible page preview
inside the section workspace. Landing version 2 preserves this formatting;
version 1 and other formats retain their existing contracts. Draft recovery,
revisions, domain panels, Sets and graph ownership still use the shared engine.
The **Abschnitt** selector and preview clicks now select the same section in
both panes without reloading the preview. Pending text edits remain in the draft;
discard inside the workspace affects only the active section.

The user's `admin` account has a private native autosave **27454** for homepage
**12257** (`home`): 12 sections, an editable opening feature and an image/text
story row, using existing approved copy/media. The published homepage and its
metadata remain unchanged, apart from the normal browser edit lock. The effective
front-page template comes from the theme; no DB template override was changed.
Open `http://192.168.2.31:8082/wp-admin/post.php?post=12257&action=edit`, restore
the private draft with **Entwurf weiterbearbeiten**, then edit the first section.
The draft choice now appears above the canvas; inactive sections stay hidden
until a choice is made. This fixes the reported frozen-looking editor on reopen.
**Update** is the native publication action on this already-published page; it
was not clicked.

Implementation and checks: [Editorial platform](docs/architecture/editorial-platform.md#landing-rich-text-and-live-preview).
Scope and staff task: [Front-page pilot](docs/project/editorial-rich-text-preview-plan.md).

## Verification and next action

- Storage/HTTP: **204 checks**, including **86 existing documents**, passed;
  pre-existing content/meta unchanged and disposable fixtures removed.
- **18 editor DOM tests + 2 Set/upload interaction tests** passed. Targeted
  ESLint, Stylelint, PHPCS, PHPStan, PHP syntax and whitespace checks passed.
- Chrome in the user's admin session: selected/cursor-only links, internal
  search, link edits, custom colours, independent colour/highlight resets, undo,
  short image/text rich editing and nested-dialog Escape. Real previews retain
  the previous valid page on incomplete input or stale saves. Newsletter forms
  are inert and sandboxed in the embedded preview; no message was sent.
- One preview H1; desktop/tablet/390px page viewports and 800px/390px editor windows
  checked without horizontal overflow. Section selection works in both panes;
  narrow preview selection returns to editing. Two final idle title edits reached the
  rendered preview in **1.94s / 1.85s** on this local stack. This is a local
  measurement, not a performance guarantee. Temporary viewport override reset.
- The user reviewed the local workflow and authorized GitHub delivery. Wider
  staff acceptance, Firefox and actual clipboard/paste remain unverified. Next:
  complete those checks and coordinate target deployment if requested. The
  front-page composition remains a private local draft; no publication or
  deployment was performed.

## Runtime and delivery

- Local WordPress now explicitly mounts plugins/themes from `wp-website` through
  `/home/vladimir/.local/state/iss-editorial-pilot-20260921/website-code.yml`.
  Core, database, uploads and event-drop mounts retain their original locations.
  Only the WordPress container was recreated; do not blindly restart the base
  Compose file, which would restore the older source mounts. The same directory
  holds the WP-CLI wrapper, verified SQL backup, baseline, clean pilot JSON and
  machine-local runtime instructions. Do not rerun draft-preparation scripts.
- This checkpoint commits the pilot's code/docs/tests and the two editorial
  project documents on top of **1505267**. GitHub delivery includes this commit
  and the preceding website commits **5c6433a**, **7d97628**, **1505267** since
  the previous `origin/main` **0857845**. The user authorized pushing website
  `main`; use Git refs for the current exchange status. The 22 preserved mixed
  branch commits remain excluded. Continue only in the website checkout.
- Code delivery requires **no SQL migration or uploads artifact**: the proposed
  composition remains private and uses existing media. Deliver `iss-content`,
  `iss-editorial` and the theme together before accepting v2 content. If reverting
  the UI after v2 saves, retain the v2 reader/sanitizer/renderer. Transferring the
  composition itself requires a separate content/media dependency assessment.
- Preserve the existing user-created report **27388** and event **26813**, the
  shared related-card recursion fix, and the earlier Event Drop integration.
  See [Event Drop](docs/runbooks/event-drop-staging.md#current-editorial-integration-2026-09-11)
  for its delivery dependencies. Existing Place migration/upload artifacts from
  the three preceding commits remain in place; the paired Kino upload archive's
  SHA-256 and five manifest members were verified before GitHub delivery. None
  of these migrations were replayed here.
