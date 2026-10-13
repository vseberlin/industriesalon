# Editorial consolidation audit — 2026-09-22

Authority: website checkout `/home/vladimir/wp-website`, based on `3a385a8`,
the running local WordPress database and effective templates. Compared against
the supplied `iss-editorial-composer-audit (2).md` dated 2026-09-21, including its
F1–F14 follow-up and V1–V9 visual review. This is a code delivery; existing
published documents and private drafts are preserved.

## Gesture portability checkpoint — 13 October 2026

The mounted website checkout now exposes 28 registered gesture identifiers in nine formats.
The workspace/CPT expansion is committed as `65c55b8`, shared portability as
`9eab967`, publication adapters as `c090540`, and shared gallery/text/quote
contracts as `32d3e3f`; native publication layout compatibility is committed as
`9fd10d0`, shared conclusions as `5fce0d0`, and shared chapters as `54afba5`.
Leitfrage consolidation is committed locally as `0c1b046`. The Merkpunkte slice
is accepted for local commit. Registration, editor fields, save validation and
public rendering must agree before a gesture becomes available in another format.

| Gesture identifiers | Effective boundary / remaining work |
| --- | --- |
| `statement`, `gateway`, `text_bild_reihe`, `feature` | One portable field/treatment contract in the vocabulary. Available in landing, article, event, exhibition, project, report, tour and publication formats. All article CPTs inherit this. The existing composition renderer handles them under the selected skin. |
| `intro` | Event/report/Place/publication introductions differ from the hidden tour introduction feeding its hero. Preserve those meanings before consolidation. |
| `kapitel` | One shared chapter contract across eight formats: text, anchors, links, media/captions, inline/right-side media and standard/aside presentation. Event live references remain an owner-specific extension; project chapter/facts pairing retains its separate editing addresses. |
| `fliesstext` | One portable contract across the same eight formats: three text treatments, links, media and archive references. Shared split geometry now works in every skin. |
| `leitfrage` | Saved event/exhibition/tour identifier retained as a hidden statement-family entry. New questions use Überschrift & Intro → Leitfrage across eight formats; existing sections have the same inspector, fields, treatments and renderer. Default question treatment and stored identifiers survive. |
| `zitat` | One portable contract across eight formats: context, quote, attribution, media, objects and source/image orientation. Reuses the theme quote primitive. |
| `schluss` | One portable contract across eight formats: rich text, anchors and links, including links-only endings. Project contact navigation and tour anchors are retained; events now support conclusion links. |
| `galerie` | One portable contract across eight formats: media and archive objects with captions/source links; grid, sequence, wall and viewport layouts. Place retains its separate existing contract. |
| `objektfokus` | Exhibition/report archive references use the archive owner; future reuse must retain resolution and visibility checks. |
| `material` | Domain downloads differ from event text items and dynamic references. Do not union fields that renderers would drop. |
| `facts` | One portable contract across eight formats: optional rich text, title/kicker, anchors and plain-text value/description rows. Reuses the existing copy and fact-list components; project Dossier retains chapter/facts pairing with separate preview addresses. |
| `upload_intake` | Depends on the context owner's open intake and signed invitation; registration alone cannot open collection. |
| `programm` | Event structure controls availability. Shared composition does not bypass its domain allowlist. |
| `dynamic_slot` | Landing slots bind named owner services; each requires a context/renderer review before reuse. |
| `map_img` | Landing map/panorama composition uses the existing static-map model; retain its data contract. |
| `atlas_map` | Landing locator/map treatments and the tour route have different owners and data requirements. |
| `bildbuehne` | Tour hero integration, booking rail and excerpt fallback remain tour-owned. |
| `epoche`, `gegenwart` | Place history and current-state projection remain a separate case. |
| `longread_quote` | Retained saved publication identifier using the shared quote contract. Existing sections remain editable; hidden from new insertion so the chooser presents one quote. No identifier rewrite. |
| `source`, `publication_rail` | Publication intro/source placement and reading-navigation options retain their existing owner contracts. Layout changes use native Update/Publish. |
| `longread_chapter`, `timeline_item`, `photoalbum` | Publication-only gestures now render in every publication layout using the existing components. Mixed order, native navigation/counts and album sources are retained. Multiple albums render with distinct sheet links. Native title/year requirements remain. The publication chapter is labelled Lesekapitel to distinguish its numbering/navigation from the shared Kapitel. |

`massstab`, `vollbild`, `image_wall` and `feature.microblocks` remain declared
compatibility aliases. Existing stored documents are not renamed or migrated.
`feature.opening` remains restricted to the front-page opening contract.

Implementation uses the existing `iss_editorial_formats` filter, `supports`,
rich-text profiles and treatment metadata. The vocabulary marks portable
contracts; the theme declares their section renderer in the same effective
registry. `registry-check` reports a portable gesture without a renderer. No
second editor, document store, block or rendering vocabulary is introduced.

The existing media/text pattern now owns its flex layout for native blocks and
JSON alike. Its defaults are component-scoped, the overlay retains image height
on phones. Native Columns compatibility selectors remain because WordPress
assigns its own flex basis; their markup and responsive rules remain supported. The image-overlay treatment now
renders every supplied link, and feature leads render in retained v1/v2 documents
without changing the stored version. Event composition can use the full existing content
column; ordinary event prose keeps its reading measure. Archival skin palettes
flow through existing domain variables, without new gesture selector trees.

Publication adapters use the same shared renderer and existing publication-native
components. All four layouts keep chapters, dated entries, albums and shared
sections in document order. Timeline composition preserves
consecutive epoch groups, generates unique repeated-epoch anchors and counts only
native dates/epochs. Each album keeps its own sheet sequence and source metadata;
album navigation includes visible sheets from every album with distinct anchors.
Intro/source placement retains the existing layout contract; an album layout
without an album section still shows that text before its other content.
Payload caches now distinguish enabled/disabled canonical JSON and exact preview
snapshots. An empty legacy album no longer suppresses original block content.
The existing album stylesheet loses its `100vw` breakout (which caused horizontal
scrolling), and its phone title stacks instead of clipping. Skin variables reuse
publication palettes; no extra gesture selector tree is introduced.
Chapter text and shared headings now consume existing surface/skin variables,
fixing dark-on-dark text in Bildmatrix. Its album palette is component-owned so
the same album can appear in other layouts without losing its colours.

Ten visible composition gestures now share fields and rendering across eight
formats. Gallery/text/quote rendering consumes resolved references through one
existing composition path, with published-object checks delegated to the archive
owner. Existing documents adopt the shared presentation while retaining their
selected palette. Split-text rules move out of Dossier into the renderer layer;
duplicate Territorial positioning is removed so shared phone stacking can apply.
Domain gallery/quote compatibility branches and their styling remain for legacy
content and Place supplements; they are not a second new gesture contract.

Verification covers the ten gestures, their treatments/layouts in every
registered skin of eight formats, save/reload, public content routing, snapshot
addresses, media/archive captions, source visibility, links, rich text,
front-page exclusion and event-structure restrictions: 11,911 assertions plus 35
UI tests. Retained publication quote IDs and Place fields/rendering are checked.
The publication matrix covers four layouts in all three skins,
interleaving, native navigation/source data, all-native mixed documents, multiple
albums, standalone native gestures under mismatched layouts, warm-cache layout
switches, disabled fallback and two successive
snapshots after a warmed public cache. Storage checks pass 299 assertions over all
94 existing documents, with post content/metadata unchanged. Chapter coverage
includes all format/skin combinations, versions 1–3, both media positions and
aside treatment, captions, links, event live-only references, owner boundaries,
project pairing and exact snapshot addresses. Shared chapters render in all
publication layouts without joining native chapter counts/navigation.
Chrome checks event, tour, exhibition, project, article and publication at
desktop/390 px: no document overflow, images stack and captions remain visible.
The project pairing also fits 820 px. Event edit/Fertig/native Update/reload
preserves its live reference, media, caption and link. Paired project facts edit
independently. The chooser distinguishes Kapitel and Lesekapitel; live reference
counts now include dynamic sources. The preceding conclusion checkpoint covered
links-only endings and title/link editing. Leitfrage coverage includes versions
1–3, every native event/tour/exhibition skin and statement treatment, anchors,
rich text, links, exact preview addresses and saved identifiers. New statements
retain anchors across all eight formats; native intro/hero and Place contracts
remain separate. Chrome checks all three existing exhibition questions and
private event/tour/exhibition samples at desktop/390 px with no overflow.
The compact chooser offers one question path; new question insertion and Fertig
return to the outline were exercised. Existing question title/treatment
edits return through Fertig to the outline; native Update/reload retains type,
anchor, formatted text and links while leaving the neighbouring section intact.
Review fixtures are removed.
Earlier tour/event/exhibition checks remain recorded in the preceding commit.
Places remain explicitly excluded. No SQL, content migration, uploads or template
artifact is needed. The reviewed local templates are file-backed except the
existing Ausstellung override (26309), which was rendered successfully and left
unchanged.

## Chapter contract boundary

The read-only inventory found 72 existing `kapitel` sections. They retain their
stored identifiers and fields. The vocabulary supplies the common contract;
only event chapters add `dynamic_refs`. The existing event sanitizer and
institution-field renderer own those references, including the stored
`address.full` reference. Other formats reject the event extension.

The theme's chapter body reuses shared prose, links and resolved media. The
project dossier uses that same body alongside its facts component, retaining
both anchors and separate preview selection. A small chapter layout contract
owns right-side media, phone stacking and aside presentation; no second field
editor or storage path is added. Old domain renderer branches/CSS remain for
legacy compatibility; this slice removes duplicate chapter definitions and the
paired project's duplicate copy markup. Native publication chapters keep their
numbering/navigation contract. Places remain separate.

## Question contract boundary

The read-only inventory found three saved `leitfrage` sections, all in
exhibitions. They keep their identifiers and text. The hidden registry entry
inherits the statement contract with `statement.leitfrage` as its default;
saved selections can use the other statement treatments. No stored identifier
is rewritten. The exhibition starter now creates the canonical statement
treatment. The existing portable adapter resolves declared families for rendering
only, including the retained publication quote identifier. The old question
layout mappings and obsolete domain/skin CSS are removed; shared treatment/skin
CSS owns the presentation. No new renderer, stylesheet or storage path is added.

## Facts contract boundary

The read-only inventory found 12 stored `massstab` sections: five prose-only
sections in two exhibitions and seven structured lists in six projects. The
existing normalizer already resolves these to `facts`; no stored documents or
versions are rewritten. The shared registry now exposes Merkpunkte across eight
formats. Optional formatted prose remains intact, including legacy numbers and
emphasis. Fact values/descriptions remain escaped plain text. Empty, value-only,
description-only and zero-value cases are covered.

Standalone facts use the existing shared copy and fact-list components, with a
smaller heading, responsive columns and top-aligned card contents. Removed
exhibition stat-layout CSS and standalone project key-points CSS are obsolete
because those paths now delegate to the shared renderer. Project Dossier keeps
its existing paired fact column and component styles; its anchor now targets the
column, and kicker/body preview fields and document text version are propagated.
Domain fact styling outside these replaced layouts remains compatibility code.

Facts verification includes versions 1–3, every format/skin, all publication
layouts, save/reload, unsupported-field rejection, escaping and distinct project
chapter/fact snapshot addresses. Chrome checks the two existing exhibitions,
Futura's paired facts, and private article/publication samples at desktop/390 px
without horizontal overflow. Article chooser insertion, row edit/add/remove,
Fertig, native Update and reload retain zero values, anchors and formatted text;
the neighbouring chapter stays intact. Private fixtures/revisions/autosaves are
removed. PHP lint, targeted PHPCS/PHPStan and changed CSS lint pass. Staff UAT
and Firefox remain separate release checks.

Next: inspect the `material` contract before sharing it. Domain downloads,
event text items and owner-resolved dynamic references must retain their fields
and renderers. Native intro/hero contracts and Places remain separate.

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

## Content and template snapshot — 22 September 2026

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
