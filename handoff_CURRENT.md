# Current handoff — 2026-09-21

Website checkout: `/home/vladimir/wp-website`, branch `main`; read `AGENTS.md`
first. `/home/vladimir/wp` remains the preserved mixed archive checkout on
`archive/local-work-20260911`. Do not edit, merge or push that branch for website
work. This checkpoint commits the tested audit follow-up to pushed **3ac41b7**.
Use fresh Git refs before exchange; no deployment or publication was performed.

## Current local feature

The front-page composer has compact rich text, native link editing/search,
colours/highlighting and live preview with section selection in either pane.
The audit follow-up adds explicit **Seitenauftakt**, illustrated layout choices,
named theme palette colours, custom colours under **Eigene Farbe**, and contrast
guidance. Layout choices retain focus and text. A moved/incomplete opening is
saved as an unfinished draft with a diagnostic; the last valid preview remains.

Landing v3 stores palette slugs and `feature.opening`. Existing hex colours are
preserved exactly. Server-side preparation upgrades v2 only in the editor copy,
including recovery; v1 remains opt-in and old public versions remain readable.
Each embedded request reuses one validated snapshot and resolved references;
ordinary API reads remain fresh after writes. Stale frames report immediately.
Lead and body use separate rendering hooks and the storage prose allowlist.
Details: [Editorial platform](docs/architecture/editorial-platform.md#landing-rich-text-and-live-preview).

The `admin` author's private native autosave **27454** for homepage **12257**
(`home`) now has 12 v3 sections. Its only intentional content-state differences
from the previous pilot are the version and explicit opening treatment. Temporary
browser test text/colours were removed and baseline equality verified. Canonical
homepage post/meta and the effective theme front-page template are unchanged,
apart from the normal browser edit lock. **Update** publishes this existing page;
it was not clicked. Reload the editor and use **Entwurf weiterbearbeiten**:
`http://192.168.2.31:8082/wp-admin/post.php?post=12257&action=edit`.

## Verification and next action

- Storage/HTTP: **227 checks**, including **86 existing documents**, passed;
  existing content/meta unchanged and disposable fixtures removed. Includes v3
  save/revision restore, exact custom/named colours, opening constraints, stale
  responses, single validation across 14 consumers, and concurrent-save isolation.
- **20 editor DOM tests + 2 Set/upload tests** passed: recovery, keyboard/focus, pending edits,
  selective discard, palette allowlisting, visual choices, opening diagnostics,
  section selection and authenticated stale-message handling. Targeted ESLint,
  Stylelint, PHPCS, PHPStan, PHP syntax and whitespace checks passed.
- Chrome: palette → custom hex → Undo → reset, actual rendered custom colour,
  recovery, incomplete opening retaining prior preview, layout choice focus/text,
  800px/390px editor windows and 390px preview without horizontal overflow.
  Browser viewport restored. Public homepage: HTTP 200, one H1, no preview bridge
  or section markers. The performance claim is reduced repeated processing;
  end-to-end latency was not remeasured in this follow-up.
- Next: implement the landing workspace proposal using the existing state,
  form controls and preview renderer, then staff acceptance with the task in the
  [pilot plan](docs/project/editorial-rich-text-preview-plan.md#acceptance-and-regression-checks).
  Firefox and real clipboard/paste remain unverified. Target deployment and
  publication remain pending.

## Runtime and artifacts

- `wp_app` mounts plugins/themes from `wp-website` through the machine-local
  `/home/vladimir/.local/state/iss-editorial-pilot-20260921/website-code.yml`.
  Core, DB, uploads and event-drop mounts retain their original locations.
  Do not blindly restart base Compose; it restores the older source mounts.
- That directory contains the WP-CLI wrapper, verified pre-pilot SQL backup,
  canonical baseline, `followup-before.json`, clean draft export and verification
  scripts. Do not rerun draft-preparation scripts. `followup-verify.php` checks
  the private draft against its baseline plus the intended v3 upgrade and then
  checks the canonical homepage/template and anonymous output.
- **No SQL migration or uploads artifact is needed** for this code delivery.
  Deploy `iss-content`, `iss-editorial` and the theme together before accepting
  v3 saves. Retain v3 reader/sanitizer/renderer support on UI rollback. Moving
  the private composition requires a separate content/media dependency review.
- Preserve user-created report **27388**, event **26813**, shared related-card
  recursion fix and Event Drop integration. Existing Place migration/upload
  artifacts remain in place; no earlier migrations were replayed. See
  [Event Drop delivery](docs/runbooks/event-drop-staging.md#current-editorial-integration-2026-09-11).
