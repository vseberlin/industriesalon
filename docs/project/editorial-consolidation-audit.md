# Editorial consolidation audit — 2026-09-22

Authority: website checkout `/home/vladimir/wp-website`, based on `3a385a8`,
the running local WordPress database and effective templates. Compared against
the supplied `iss-editorial-composer-audit (2).md` dated 2026-09-21, including its
F1–F14 follow-up and V1–V9 visual review. This is a code delivery; existing
published documents and private drafts are preserved.

## Findings and disposition

| Finding | Result |
| --- | --- |
| Home alone had the current workspace | One workspace for all nine formats, including v1 documents. The general article format reuses existing sections for pages/posts/videos. |
| Parallel section UI | Old section cards/modal and modal-preview layout removed. Inspector, canvas and native owner panels share one document and autosave queue. |
| F4/F5: disconnected vocabularies | Effective format registry combines owner metadata, aliases, defaults, skins, skin features and starters. Renderer skin lists and landing treatment allowlist removed. Read-only CLI consistency check added. |
| F1/F2: blind treatments, undifferentiated palette | Registered labels, icons, groups and schematics available across formats. Native radio treatment controls retained. |
| F3: preview | Existing authenticated WordPress preview extended to all formats. Source indices survive skipped sections, publication payload transforms and paired project sections. |
| F6: language | Registry descriptions and shared format/skin labels use German. Stored identifiers remain stable. |
| F7/V3: control styling | Shared input appearance belongs to admin controls; workspace CSS owns layout. Existing tokens and text toolbar retained. |
| F8: controller size | Removed alternative editor and extracted inputs/schematics into existing `ui.js`. Domain-specific field controls remain in the state controller; further extraction is maintenance, not a second state/store. |
| F9: authoring boundary | Enabled JSON/new auto-drafts use one workspace. Existing block documents retain native WordPress authority until explicitly migrated. No dual editing of the same enabled document. |
| F10: exact text colours | Named theme presets are supported. Previously approved custom hex colours remain supported; no lossy nearest-colour migration. This intentionally differs from the audit recommendation. |
| F11/V1: opening ownership | Explicit opening treatment and section-specific guidance retained, with tests. Article starters cannot introduce a front-page opening. |
| F12: repeated validation | Snapshot cache retained; repeated consumers validate once. |
| F13: rich-text profiles | Registered profiles drive validation/editing. Cross-format checks prove emphasis and named colour retention. Unsupported legacy markup remains explicit review work. |
| F14: frame portability | Device sizing uses standard transforms rather than CSS zoom; fixed internal viewport dimensions are tested. |
| V2/V4–V8 | Existing compact toolbar, inspector tabs, growing title, outline labels, measured selection, fixed workspace and undo retained. DOM checks cover selection, reorder, recovery, failure and native publishing navigation. |
| V9: untitled generated section | Outline uses registered treatment/slot labels; no invented editorial title is saved. |
| CSS mixed renderer/skin/page rules | Renderer and skin files separated. Legacy front-page CSS does not load on the JSON page. Five exact duplicate declaration blocks consolidated. |
| CSS anchored to URL names | Removed anchor-driven styling decisions. Changing a section anchor cannot change its treatment/classes. |
| Place sections silently absent | Registered gallery/material/upload sections now use the existing shared media/archive renderer. |
| Video template bypassed article content | Existing shared composition block now supplies the article slot; timed transcripts retain their owner. Draft headers are available only to editors; anonymous lookups remain published-only. |
| Generated excerpts rendered full compositions | Content renderers skip excerpt generation, preserving native excerpts and preventing duplicated text/related-card content. |
| Preview scaling created outer scrollbars | Existing viewport CSS now lets the iframe own scrolling; project navigation controls belong to the outline owner panel. |

## Current content and template authority

- 86 stored documents validate: events 26, places 1, landings 4, tours 15,
  exhibitions 16, projects 7, reports 1, publications 16. Registration does not
  imply activation: only three exhibition documents are currently enabled.
- Homepage **12257** (`home-2`) has a published v3 document with 10 sections and
  four deleted sections. Administrator autosave **27678** is a separate valid
  10+4 composition. The previous handoff's 27454 / 12+2 private-only description
  was stale. Neither current composition is replaced by this delivery.
- About, Schöneweide and Führungen still store v1 documents. They now use the
  same workspace without an automatic schema/content rewrite.
- Effective `single-ausstellung` uses database override **26309**. Additional
  overrides include `page-publikationen` **26560** and `page-projekte` **26532**.
  They were inspected and preserved; theme files alone are not their authority.
  `single-video` is theme-owned locally and now contains the existing shared
  composition block. Check target overrides before deployment.
- Report **27388**, event **26813**, all source content, revisions, native
  relationships and Set data remain preserved. No historical migration replayed.

## Verification and repeatability

From the website checkout, against the existing local stack:

```sh
node --test tests/e2e/bin/editorial-ui.cjs tests/e2e/bin/editorial-set-ui.cjs
wp iss-editorial registry-check --format=json
wp eval-file tests/e2e/bin/editorial-registry.php
wp eval-file tests/e2e/bin/editorial-storage.php
wp eval-file tests/e2e/bin/editorial-sets.php
bash tools/phpcs-target.sh
bash tools/phpstan-target.sh
```

`wp` denotes the target's normal WP-CLI invocation; in this local Docker stack
use the wrapper and `/tmp/iss-tests/` paths recorded in the handoff. The registry
command is read-only. The three PHP suites create only their own disposable
fixtures and remove them in `finally`; storage/Set suites check preservation.
Run database suites sequentially. Also run changed PHP syntax, JS lint, CSS lint
and `git diff --check`.

Automated evidence: 287 storage checks (including the 86 existing documents),
137 cross-format registry/renderer checks, and 169 Set assertions. The browser
checks public homepage/About/Schöneweide/Führungen at phone and desktop widths;
no horizontal overflow, one H1, and no public editing markers. The Schöneweide
media-text treatment retains its 40:60 ratio. Public HTTP checks cover the
existing formats and protected report/event routes.

Authenticated Chrome checks passed for the home workspace and disposable
project, tour, publication, ordinary page/article and video/article drafts.
Project editing, autosave, reload/recovery and native Save Draft retained the
changed section title without publishing. Native relationship navigation and
route owner controls were checked. Project/publication phone layouts fit at
390px; the 800px project layout also fits. The video fixture used an intentionally
unresolvable media URL: article/header rendering was verified, playback was not.
All browser fixtures and their empty Sets were removed; viewport reset.

34 DOM tests, targeted ESLint/Stylelint, PHP syntax and PHPStan pass. PHPCS passes
for 18 changed PHP files; `iss-content/includes/videos.php` retains the same 24
pre-existing escaping findings as HEAD, with no new findings. They are not
silently suppressed or claimed as a clean lint run.

Full-row comparison uses a baseline of 15,390 posts and 238,520 non-lock metadata
rows. The latest rerun reports private draft differences: autosaves 26790 and
27228 changed, autosave 27941 was added, metadata was added to autosaves
26723/26790/27941, and `_iss_relations_route_draft` was added to tour 12191.
Published content/JSON and home draft content remain unchanged; no rows
were removed. Home autosave 27678 has timestamp/base-token bookkeeping changes;
normal edit locks are excluded. The broad preservation check therefore does not
pass full-row equality. These private drafts remain intact; their provenance
requires review before accepting a new baseline. Firefox, real clipboard/paste
and staff speed/acceptance remain rollout checks.

## Homepage hero restoration

Staging and Git comparison found that commit `3ac41b7` introduced a separate
`iss-landing-opening` design when JSON gained hero ownership. Its serif font,
740px height cap, gradient and filled buttons differed from the existing
`iss-front-hero` pattern. The front-page skin also overrode its zero padding;
ordinary mobile section spacing affected it too. The CSS split retained this
regression rather than resolving the duplicate presentation contract.

The JSON opening now renders native Cover/Buttons through the original hero
pattern. The template fallback uses the same homepage modifier; its former
inline gradient and kicker settings belong to `patterns.css`. Generated group
spacing is expressed as explicit hero rhythm. The duplicate opening CSS is
removed, and ordinary section rules exclude the opening. Optional JSON prose
is retained beneath the image. Cover image sizes account for portrait viewports.

Chrome comparison against `https://staging.industriesalon.info/` at 1440×900 and
390×844 verifies matching hero height, title font/position, gradient and kicker
spacing, no extra section padding and no horizontal overflow. The JSON preview
retains section/field addresses. Theme-owned `front-page` parses/serializes
without changes; one Cover and one editorial slot remain. No content, enabled
flags or DB template overrides are changed by this repair.

## Delivery boundary and next action

The owning plugins and theme are deployed together on staging. The code alone
requires no data rewrite. The subsequently authorized website/editor content sync
applied `ops/migrations/2026-09-22-editorial-staging-sync.php`, followed by
`ops/migrations/2026-09-22-editorial-sync-dependencies.php`, with
`ops/uploads/2026-09-22-editorial-sync.manifest`. It transferred 52 documents,
their content/template/media prerequisites, and the report's required source
connection through owner APIs. Private autosaves and unrelated domain data were
preserved. Use migration `verify --use-include`, not a second `apply`.

Staging validates all 86 stored documents and all nine registry formats. Public
route, desktop/phone hero and keyed map-tile checks pass. Full and targeted
backups, scope and preservation evidence are recorded in `handoff_CURRENT.md`.
The local content database was not rewritten. Production was not touched.

Next action: authenticated staff acceptance and Firefox/clipboard checks on a
landing, project, tour and publication, including owner controls, draft recovery
and native Save Draft. Existing tests and the registry command remain the
repeatable gate for later registry extensions.
