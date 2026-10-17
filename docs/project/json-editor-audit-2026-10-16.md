# JSON Editor Audit (2026-10-16)

Scope: `plugins/iss-editorial` at `d9c4d79` (about 12.1k LOC) plus the contract
edges it shares with `iss-content` and the theme renderers. Criteria:
inconsistencies, dead code, longevity, security, performance.

Method: original review was static only. No WordPress runtime was available, so
its performance statements came from the call graph, not profiling. Subsequent
local measurements are recorded under the individual findings. Confirm M1–M4 with
Query Monitor on staging (programme page, a large album/Futura edit screen)
before and after each fix.

Status, 17 October 2026: H1–H3, M1–M8 and L1–L10 are resolved locally,
including the picker extraction. L3 retains the intentional existing enablement
boundary. Code `9985528` is committed, pushed and deployed to staging (17 October,
13:04 UTC). Target validation, preservation and release checks passed. Human/
cross-browser acceptance and interactive profiling remain in `TODO.md`.
The findings below describe the audit baseline, followed by their resolutions.

## Summary

| ID | Severity | Area | One line |
| --- | --- | --- | --- |
| H1 | High | Degraded mode | Without iss-editorial, saving a Veranstaltung downgrades or deletes its document, with no revision copy |
| H2 | High | Revisions | Only the edit-form path snapshots a revision; storage API writers (CLI `--force`, contributions, epochs) overwrite irreversibly |
| H3 | High | Validation | Lossiness check covers a hand-kept key list; some fields are still silently truncated or rewritten |
| M1 | Medium | Performance | Format registry is rebuilt on every call, four times per Futura document read locally |
| M2 | Medium | Performance | Programme cards sanitize the full event document once per occurrence row |
| M3 | Medium | Performance | Reference resolution is N+1; no cache priming anywhere |
| M4 | Medium | Performance | 350 ms autosave debounce with an iframe frontend render per pause, plus a save on every editor open |
| M5 | Medium | Longevity | Revision count is unbounded; each revision copies full JSON metas |
| M6 | Medium | Consistency | 54 consumer-specific slugs hardcoded in the generic engine |
| M7 | Medium | Robustness | Edit-version token is invalidated by unrelated background writes |
| M8 | Medium | Longevity | Registry changes freeze posts, including through trashed sections; no stored-document sweep |
| L1–L10 | Low | Mixed | Dead code, duplicated sanitizers, truncated album import, missing plugin headers, hidden coupling |

Security: no exploitable issue found (see the Security section).

## High

### H1. Degraded mode destroys Veranstaltung documents

When `iss-editorial` is inactive, `iss-content` registers the old `Struktur`
meta box and loads `assets/admin-veranstaltung-content.js` (1,238 LOC). Saving
goes through `iss_content_model_save_veranstaltung_content_meta`
(`plugins/iss-content/includes/admin.php:2040`).

- The JS forces `schema_version: 1` (`admin-veranstaltung-content.js:348`,
  `:374`). `compactSection` keeps only kicker/title/body/quote/attribution,
  string items and refs. It drops treatment, links, facts, lead, layout fields,
  skin, `hero_*` and `deleted_sections`.
- Runtime correction: before rendering the hidden field, the legacy reader
  rejects stored v3 and the box substitutes an empty v1 document. Even without
  JS, saving that field overwrites v3 with empty v1. A stale form containing raw
  v3 or invalid JSON instead reaches the fallback writer's deletion path.
- Revision meta registration lives in iss-editorial
  (`iss_editorial_register_revision_meta`), so no revision holds the JSON.

Result: one Aktualisieren in degraded mode loses or downgrades the document.
This contradicts the Vanilla Fallback intent (`vanilla-fallback-mode-sow.md`).

Fix: remove the fallback editor, its v1 sanitizer branch and the write path.
With the engine absent, render a read-only notice and never write the
document key. Removes about 1,238 LOC JS plus the PHP box and sanitizer branch.

Local implementation: removed the legacy JS, its orphan CSS, empty-document
builder and form writer. The existing box now shows a read-only notice. Metadata
registration disables REST exposure, custom-field write permissions and the
write sanitizer when the shared engine is unavailable. The v1 sanitization
branch is retained solely for existing public read compatibility in that mode;
removing it would also remove those frontend reads.

Regression: `tests/e2e/bin/editorial-degraded-mode.php` loads the real owner
without the shared engine and uses connection-local temporary tables. It
reproduced the overwrite before the fix, then verifies exact preservation for
v1/v3, future/malformed documents and absent metadata across ordinary saves,
stale forms and REST writes/deletes. Other event fields remain editable. No
content migration or uploads artifact is needed.

Validation passed: 162 degraded-mode checks, 513 storage checks covering 180
existing documents, 52 UI tests and 49 event/feedback assertions, plus PHP
lint/PHPCS/PHPStan and targeted CSS lint. Existing content remained unchanged.

Acceptance: with iss-editorial deactivated, editing and saving a Veranstaltung
leaves `_iss_content_json` byte-identical; an e2e check asserts this.

### H2. Revision capture only on the edit-form path

`wp_save_post_revision($post_id)` runs inside the `wp_insert_post_data`
filter (`revisions.php:300`). `iss_editorial_save_document` and
`iss_editorial_persist_document` (`storage.php:895`, `:905`) never snapshot.
Writers that bypass the form therefore overwrite without history:

- CLI `ausstellung|projekt|fuehrung|place-import-candidate --force`
  (`cli.php:882`, `:1057`, `:1474`, `:1620`)
- `iss_editorial_set_document_enabled` callers in iss-content contributions
- the `iss_editorial_document_writer` epochs wrapper in the register plugin

Fix: snapshot the current state inside `iss_editorial_persist_document` before
calling the writer, and drop the duplicate call from the form filter.

Local implementation corrects that proposed placement: shared metadata writes
capture through `iss_editorial_capture_revision()` inside the owner writer's
callback, so the Place transaction encloses the revision too. The form retains
its pre-update capture and marks that native update as covered; a second
capture after WordPress has changed the title/excerpt would mix versions.
Native revision restore keeps its existing metadata-copy callback.

API document/skin/enabled writes and enabled-only changes share the checked
metadata writer. CLI imports pass their enabled state with the document;
report creation does the same and handles failure. That report path creates
a new document rather than overwriting an existing one. Failed revision
insertion or incomplete metadata copying stops replacement. Native revision
policy remains authoritative; no new history store, schema migration or media
artifact is needed. No-op writes and private autosaves add no canonical revision.

`tests/e2e/bin/editorial-revisions.php` reproduced the missing API snapshot
before implementation and verifies full native restoration, forced CLI import,
enabled-only changes, unsupported raw JSON preservation on disable, form
identity consistency and injected failures. The Place atomic suite also injects
revision-insert/copy failures inside its existing rollback boundary.

Validation passed: 42 revision-writer checks, 239 Place atomic checks, 513
storage checks covering 180 existing documents, 190 Sets/report integration
assertions, and targeted PHP lint/PHPCS/PHPStan. Fixtures were removed and
existing content, Place tables and Sets were preserved.

Acceptance: a CLI `--force` replace produces a restorable revision.

### H3. Silent lossy normalization

`iss_editorial_validate_document` promises to reject edits before
normalization discards content. It enforces this only for explicit lists
(`storage.php:824` for collections, `:835` for enumerations). Still silent:

- `contribution_question` truncated to 240 characters (`storage.php:599`)
- `text_bild_reihe` / `map_img` item `media_refs` sliced to one (`:222`)
- `anchor` rewritten by `sanitize_title` (`:500`)
- title/kicker tags and newlines stripped by `sanitize_text_field` (`:489`)

Every new field repeats the risk. Fix: one generic comparison of scalar leaves
between input and normalized output, with an explicit opt-out list for
deliberate normalizations (whitespace, numeric strings). Reject with the
field path on difference.

**Completed locally (17 October):** the shared validator recursively compares
submitted values/lists with the final normalized document after owner checks.
Unexpected changes return `editorial_lossy_normalization` with the affected
field path in the message and error data. Contribution-question truncation,
extra nested media, title/kicker markup or embedded newline loss, rewritten
anchors, unknown nested fields and lossy owner extensions are rejected before
canonical writes. Private drafts retain the rejected input for correction.

Explicit representation allowances preserve empty placeholders/defaults,
horizontal spacing/CRLF, integer/string and boolean 0/1 values, HTML void-tag
spacing, registered section aliases, album aliases/order, native page permalink
refresh and active-section trash bookkeeping. The event owner declares only its
registered legacy entity map through `iss_editorial_normalization_input`;
consumer slugs do not enter the shared comparison. Security sanitization and
opaque trash retain their existing contracts. No stored documents were rewritten.

Verification: all 180 existing canonical documents pass the read-only gate;
363 focused lossiness checks, 541 storage/HTTP, 334 validation/trash/CLI,
237 retention and 239 Place atomic checks passed. Native rejection preserves
identity, content, private recovery and all history; correction saves through
the existing owner. Signed-in Chrome showed the field-path diagnostic, retained
the rejected title as editable text and successfully saved its correction while
keeping prose/skin/trash. Browser project 34177, its history and empty Set 657
were removed. PHP lint, PHPCS and PHPStan passed. Verified before-images and
receipts are in ignored `var/editorial-lossiness-20261017/`.

## Medium

### M1. Registry rebuilt on every call

At the audited revision, `iss_editorial_get_registered_formats()` has no memo. Each
call runs six `iss_editorial_formats` callbacks (iss-content x4, publications,
theme), rebuilds the gesture vocabularies (about 190 `__()` calls) and
re-sanitizes every label. `get_format`, `get_document_meta_key`,
`get_skin_meta_key`, `get_empty_document` and `get_format_for_post` all route
through it, so one document read causes repeated builds (four measured locally
for Futura, correcting the original static estimate of at least five). It also runs on
every `_edit_lock` write via `iss_editorial_track_lock_session` →
`iss_editorial_uses_canvas` (heartbeat).

Implemented locally: request-local normalized registry cache, enabled only after
`init` has completed. `did_action('init')` alone is insufficient because it is
already true inside the action; `doing_action('init')` prevents caching partial
registrations. The cache follows blog/locale changes and has an explicit lazy
reset for runtime filter changes. Eligibility callbacks remain live per post.
No database or uploads artifact is needed.

Local CLI measurements on 16 October, 15 samples per probe with warmed object/DB
caches and the registry reset before each sample after the change:

| Probe | Registry builds before → after | Median ms before → after |
| --- | --- | --- |
| Futura (`30743`) document read | 4 → 1 | 20.914 → 10.995 |
| Futura read model | 4 → 1 | 21.339 → 11.005 |
| 20 registry reads | 20 → 1 | 73.052 → 3.786 |

Document, read-model and registry JSON hashes matched before/after. These measure
local function work, not end-to-end editor or staging page timings. The new
`editorial-format-cache.php` harness passed 36 checks covering real pre/during-init
reads, late CPT registration, all nine formats, reset, array isolation, live
eligibility, native locale switching with an in-memory translation fixture, and
blog-context isolation. The full renderer harness passed 14,127 checks; storage
passed 513 checks including 180 existing documents and authenticated admin/AJAX
requests. Existing content/meta were unchanged and fixtures removed. PHP lint,
targeted PHPCS and PHPStan passed. Staging Query Monitor comparisons remain
release work.

### M2. Programme hot path

`industriesalon_event_ticket_action`
(`themes/industriesalon/includes/veranstaltungen-render.php:499`) is called
per programme entry (`:543`), i.e. per occurrence row, and also for
ausstellung rows. Each call runs `iss_editorial_get_document` and therefore a
full `sanitize_document` (kses per body, `get_permalink` per link) only to find
ticket links in `material` sections. It passes `get_post_type($id)` as the
format slug, which works only because the slugs coincide.

Implemented locally: resolve the format through
`iss_editorial_get_format_for_post`, then memoize the first canonical ticket URL
per blog/post/format/locale in the existing theme helper. Empty results are cached
too. The first read retains the shared document sanitizer; no parallel JSON
parser or validation path was added. WordPress's cached metadata read detects
changed/deleted JSON, so API saves and native metadata deletion refresh the result
within the same request. Availability and booking fallback remain per occurrence,
and labels are translated on each call. No database or uploads artifact is needed.

Local diagnostic on 16 October (single CLI runs, with M1 already present):

| Probe | Document sanitizations before → after | Elapsed ms before → after |
| --- | --- | --- |
| 20 repeated lookups for event `24993` | 20 → 1 | 2.706 → 0.805 |
| All 52 programme rows, after the probe above | 35 → 34 | 9.983 → 10.271 |
| Same 52 rows a second time | 35 → 0 | 9.659 → 1.352 |

All action hashes matched across 42 source posts. The initial programme pass shows
no meaningful timing improvement; the gain is avoiding repeated processing.
These are local function timings, not end-to-end page measurements.

Verification: 50 new ticket-cache checks, 55 event/feedback checks (including six
new real save/delete/re-add checks), and 144 existing programme overview, rotation,
preview, native booking and Repair-Cafe checks passed: 249 total. Existing events,
reports and comments were unchanged and fixtures removed. PHP lint, targeted
PHPCS/PHPStan and diff checks passed. Anonymous HTTP responses stayed successful;
all eight programme-page cards and five homepage cards had identical markup.
Staging Query Monitor comparisons remain release work.

### M3. N+1 in reference resolution

At the audited revision, `iss_editorial_get_read_model` resolves each object/media
reference separately: post, metadata and archive thumbnail reads. Local measurement
found two queries per distinct media ID, with two more for each archive thumbnail.

Implemented locally on 17 October: the existing read-model and editor-hydration
boundaries collect distinct supported IDs and call WordPress `_prime_post_caches`
with metadata enabled and taxonomy caching disabled. Archive thumbnails use a
second native batch. Both paths include nested item and identity media; only the
editor hydrates deleted-section media, and it continues to leave `object_refs`
alone. Existing resolvers, ordering, missing-reference behavior and snapshot-bound
preview selection remain unchanged. No new result cache or storage layer was added.

Nine local CLI samples per probe, evicting referenced post/meta caches (including
archive thumbnails) before every sample, with unrelated options/registry caches warm:

| Probe | Queries before → after | Median ms before → after |
| --- | --- | --- |
| Futura `30743`, read model | 4 → 4 | 9.645 → 8.986 |
| Futura, editor hydration | 4 → 2 | 1.948 → 2.071 |
| Kinder im Werk `26381`, read model | 30 → 4 | 12.948 → 5.414 |
| Kinder im Werk, editor hydration | 18 → 2 | 9.432 → 2.779 |
| Transformatorenwerk `18864` (25 distinct media), read model | 50 → 2 | 28.868 → 9.176 |
| Transformatorenwerk, editor hydration | 50 → 2 | 17.851 → 7.751 |

Futura's identity validator already reads its logo before the reference boundary,
so its two-reference public model gains no query reduction. The small hydration
timing difference is noise; these are local function measurements, not page or
staging timings. Every before/after model and hydrated-document hash matched;
rendered `core/post-content` HTML also matched for all three real pages.

Verification: 148 focused checks in `editorial-reference-cache.php` (temporary
connection-local fixture tables), 513 storage checks including 180 existing
documents and authenticated preview requests, and 14,127 registry/renderer checks
passed. The focused regression fails against isolated pre-M3 code (68 fixture
queries); batching needs six including the existing identity validation. It covers
duplicates, nested/identity/deleted media, PDFs, missing/trashed/private/unsupported
references, preserved editor captions, draft media and native metadata/thumbnail
invalidation. Existing posts, metadata and private autosaves were unchanged after
fixture cleanup (volatile edit-lock metadata excluded). PHP lint, targeted
PHPCS/PHPStan and diff checks passed; all three
public routes returned HTTP 200. No migration or uploads artifact is required.
Local receipts: ignored `var/editorial-reference-cache-20261017/`.
Staging profiling remains release work.

### M4. Autosave cadence and preview reloads

The original live-preview debounce was 350 ms (`assets/admin.js:595`). Each pause
causes an AJAX draft save (version check, full validation,
`create_post_autosave`, meta writes, two `saved_token` hashes) and a full
frontend render in the preview iframe. The editor also saves and renders once
on every open (`admin.js:316`), even without edits.

**Completed locally, 17 October 2026.** The existing serialized autosave queue
now waits 1,200 ms after input, skips already acknowledged values at execution,
and flushes on blur. The iframe waits for 2,000 ms of idle time or field completion;
slow responses cannot replace newer edits and active canvas editing keeps its
frame. Explicit preview/retry/Finish and native saves still check the server
immediately, even when fields are unchanged.

An untouched editor opens a `saved:<baseToken>` snapshot through the existing
authenticated preview route without creating an autosave. It verifies the current
canonical version and shares editor hydration. Saved title/summary and event
structure remain canonical even with a different private draft. The event meta
preview filter required the same saved-snapshot distinction as the title filter;
authenticated event HTTP coverage caught that boundary. Recovery remains explicit,
invalid work stays recoverable, and publication still requires valid input.

Controlled DOM measurements use production JavaScript with simulated timers and
responses, ten title inputs at each interval, then 2.5 seconds of idle time:

| Input interval | Autosave requests before → after | Iframe loads before → after |
| --- | --- | --- |
| Open without edits | 1 → 0 | 1 → 1 |
| 600 ms | 11 → 1 | 11 → 2 |
| 1,500 ms | 11 → 10 | 11 → 2 |

Totals include the initial open. These are scheduling counts, not staging latency
or a throughput benchmark. `editorial-ui.cjs` passes 60 tests, including slow
responses, blur, undo, direct canvas edits, recovery, stale versions and immediate
native saves; the Set UI suite passes two. `editorial-storage.php` passes 528
checks with 181 documents (including the disposable browser fixture), covering
saved/draft preview isolation, stale-token rejection and read-only preview GETs.
The 14,127 registry/renderer checks and 55 event/feedback assertions also pass,
as do targeted PHP lint/PHPCS/PHPStan, JavaScript lint and diff checks.

Signed-in local Chrome checks passed on disposable project 32444: untouched
reopen preserved its exact autosave/revision state; heading and TinyMCE edits
autosaved, survived reload/recovery, and reached canonical storage through native
Save Draft. Direct canvas editing autosaved without replacing the active frame,
then refreshed on Enter; desktop/phone preview controls worked and console errors
were empty. The fixture, its revisions/autosave and empty Set 625 were removed.
No real editorial content was edited. Browser receipts and before/after counters
remain in ignored `var/editorial-cadence-20261017/`. No migration or uploads
artifact is needed. Staging profiling and cross-browser/human UAT remain separate.
After cleanup, all 15,499 existing posts (including private autosaves/revisions)
and 239,197 metadata rows match their pre-check hashes; only volatile edit-lock
metadata is excluded. Prior dirty files outside M4 retain their recorded hashes;
the overlapping H1/H2/storage-test edits were preserved.

### M5. Unbounded revisions

Each Update can create two revisions (the pre-update snapshot in
`revisions.php:300` plus core's post-update revision). Each copies every
revisioned meta key, including the full document JSON. There is no
`wp_revisions_to_keep` filter or `WP_POST_REVISIONS` setting in the repo.
Large album/Futura documents bloat `wp_postmeta`, and the revision diff walks
the whole document.

Fix: cap revisions per format post type (for example 30) and record the
policy in the runbook. Re-check after H2 moves the snapshot.

**Completed locally (17 October):** the existing revisions owner uses native
retention hooks to keep 30 normal revisions for eligible posts with a stored
document key, including disabled/legacy JSON. Posts without that key keep their
native policy; lower global limits and native per-post-type overrides still
apply. Private autosaves are excluded from the allowance. Core prunes on later
revision creation; there is no load-time or unchanged-save history purge.

The cap exposed two native ordering hazards. H2 now verifies the new snapshot
before core can prune, so failed insertion/metadata copying preserves older
history even when already over the cap. Native restore can prune its own source
while updating the parent, before copying metadata. A request-only source copy
supplies core's existing restore routine for that exact parent/revision; a failed
source read prevents deletion. Existing owner transactions/readback remain in
control. The oldest revision comparison also accepts core's empty left side.

Verification: 237 focused retention checks, 42 revision-writer, 239 Place atomic,
537 storage/HTTP and 334 validation/trash/CLI checks passed. These cover all nine
formats, native/API writes, two authors' autosaves, full-history insertion/copy
failures, oldest restore and source-read failure. PHP lint, PHPCS and PHPStan
passed. Signed-in Chrome compared/restored the oldest of 30 normal revisions;
canonical JSON, skin and opaque trash matched exactly and the private autosave
JSON/token survived unchanged. Disposable project 33801, its history and empty
Set 650 were removed.

The user confirmed concurrent saves to Schöneweide 13251 and Führungen 13301;
those edits were kept. Full post/postmeta comparison proves every other existing
record/history unchanged, excluding volatile edit locks. Evidence and verified
pre-slice worktree/history backups are in ignored
`var/editorial-retention-20261017/`. No SQL migration/uploads artifact is needed.
Before release, verify a current DB backup: code rollback cannot recover history
already pruned on later saves. See the
[retention runbook](../runbooks/deployment.md#editorial-revision-retention).

### M6. Engine knows its consumers

The audit baseline counted 54 consumer slugs in the generic engine (`assets/admin.js` 37,
`includes/storage.php` 12, `includes/admin.php` 5). Examples:

- fuehrung/projekt rich-text allowlist (`storage.php:454`)
- `text_bild_reihe` / `map_img` item shape (`storage.php:530`, `:814`)
- `landing.feature` media layouts (`storage.php:608`)
- `era_key` / `function_key` vocabularies (`storage.php:574`, `:580`), which
  are place-domain data
- `openingSkin` mapping projekt → buehne (`admin.php:380`)
- `format === 'projekt'` rail control in `admin.js:293`

Fix: express these as registry fields owned by iss-content (item shape,
allowed values per field, opening skin, owner controls). Mechanical, can be
done gesture family by gesture family.

**M6a completed locally, 17 October 2026: item-shape contract.** The existing
section registry now accepts `items_kind=text|cards|text_image` and an optional
plain-text `items_label`. `iss-content` declares image/text rows for
`text_bild_reihe` and `map_img`; cards remain the default, and event material
retains text notes. Four engine switches for item sanitation, row-field
validation, row controls and the panel label now use that contract. The current
18 item-bearing definitions resolve to nine row, eight card and one text-list
definitions. Existing panel labels, rich-text profiles, row details, card URLs,
image limits and loss rejection remain unchanged. Unknown declared item kinds
are reported by `registry-check`.

Verification: 102 focused checks include renamed gestures and kinds overriding
the historical names; 69 editor DOM tests, 541 storage/HTTP checks, 363 lossiness
checks and 14,128 cross-format registry/renderer checks pass. The registry fixture
that previously expected HTML to be silently stripped from event notes now checks
H3's loss rejection, then saves valid plain text. All 180 existing documents have
identical raw hashes, normalization and validation results; 54 item samples across
supported versions retain identical normalized values and rendered HTML hashes.
Targeted PHP lint/PHPCS/PHPStan and JavaScript lint pass. Signed-in Chrome on a
disposable project verified an edited row and a new row without a URL through
native Save Draft and reload, with exact retained rich text, row details, card
destination and opaque trash. The card destination control and phone preview
also remain available. Evidence: ignored `var/editorial-item-contract-20261017/`.
No schema, database migration, uploads or template artifact is required.
After removing the disposable project, its history and empty Set, all 15,502
pre-slice posts and 239,205 metadata rows match their baseline hashes, excluding
volatile edit locks. This preserves the user's earlier Schöneweide and Führungen
edits. Both read-only release gates pass: 180 canonical documents valid and no
registry errors. Twenty-six unrelated prior worktree paths retain their hashes.

**M6b completed locally, 17 October 2026: media-layout contract.** Sections
supporting `media_layout` declare ordered choices, a default and an optional
treatment visibility list in the existing registry. The shared vocabulary owns
inline/right-side placement for chapters, ordinary features and publication
reading chapters; the landing owner declares its feature ratios. Shared PHP/JS
no longer select media layouts by format/section name. Hidden choices remain
valid stored values, and showing a default does not write it into the document.
The existing inspector-rebuild visibility boundary is retained. Registry
diagnostics detect missing choices, invalid defaults, empty labels and unknown
treatment restrictions. No renderer or CSS change is involved.

Verification: 492 focused checks cover all 17 declarations, renamed consumers,
defaults independent of choice order, hidden values and invalid declarations.
The 72 editor DOM, 541 storage/HTTP, 363 lossiness and 14,128 registry/renderer
checks pass, as do targeted PHP lint/PHPCS/PHPStan and JavaScript lint.
All 180 stored documents retain raw hashes, normalization and validation;
1,593 layout/treatment/version cases also retain rendered-output hashes.
Signed-in Chrome verified
ratio selection, hiding/reopening without value loss, chapter placement, native
Save Draft/reload and phone preview on disposable page 35190. Media preview
metadata continues to hydrate through its unchanged owner. Evidence and the
verified worktree backup: ignored `var/editorial-media-layout-20261017/`.
No schema, database migration, uploads or template artifact is required.
The disposable page and its history were removed; all 15,502 existing posts and
239,205 metadata rows match their pre-slice hashes, excluding volatile locks.
This includes the user's retained Schöneweide/Führungen edits. Twenty-eight
unrelated prior worktree paths are unchanged. `validate-all` reports 180 valid
canonical documents and `registry-check` reports no errors across nine formats.

**M6c completed locally, 17 October 2026: opening-skin capability.** Formats
declare `opening_skin` and an optional live `opening_eligibility_callback` in
the existing registry. The shared admin resolver replaces its consumer-name
mapping with this declaration. `iss-content` owns project Bühne and the
homepage-only landing capability; cached definitions do not freeze homepage
eligibility. Registry diagnostics flag unknown skins and invalid callbacks.
The editor already consumed `openingSkin`, so production JavaScript, server
opening validation, renderers and CSS remain unchanged. This preserves the
existing distinction between editor availability and valid stored documents.

Verification: 37 focused checks cover declarations, renamed formats,
overrides of historical names, callback arguments and live eligibility without
cache invalidation. All 78 editor DOM, 541 storage/HTTP and 14,128
registry/renderer checks pass, with targeted PHP lint/PHPCS/PHPStan and test
JavaScript lint. The actual localized admin settings confirm project,
ordinary landing, homepage and restored non-homepage states without changing
the saved homepage option. All 180 stored documents retain raw hashes,
normalization, validation and opening capability; 135 opening/version/skin/
placement/title/image cases retain validation and rendered-output hashes.
Signed-in Chrome verified project treatment switching, title edit, native
Save Draft/reload, phone preview and the absence of the opening choice on an
ordinary landing. Disposable project 35615, page 35617, their history and
empty Set 672 were removed. All 15,502 existing posts and 239,205 metadata
rows retain baseline hashes, excluding volatile locks; 31 unrelated dirty
paths retain their hashes. Evidence and verified backup:
`var/editorial-opening-skin-20261017/` (ignored). `validate-all` reports 180
valid documents; `registry-check` reports nine formats with no errors.
No schema, database migration, uploads or template artifact is required.

**M6d completed locally, 17 October 2026: Place vocabularies.** The Place epoch
declaration now supplies `vocabularies.era_key` and `vocabularies.function_key`,
each with a field label and ordered choices. Shared storage and editor controls
consume that metadata; their duplicate domain lists are removed. Existing
sanitizers, blank selection, rejection of changed nonempty values and private
recovery are retained. Missing vocabulary metadata or option labels are registry
errors. The register projection validator/writer and public theme labels remain
unchanged; the editor gains no dependency on the transitional register plugin.

Verification: 360 focused checks compare register values/labels/order, all 54
pairs across versions 1–3, renamed consumers, custom vocabularies, rejected
values and registry diagnostics. All 80 editor DOM, 243 Place atomicity, 541
storage/HTTP, 363 lossiness and 14,128 registry/renderer checks pass, with
targeted PHP lint/PHPCS/PHPStan and JavaScript lint. All 180 stored documents
and 252 vocabulary/version/input cases retain normalization, validation and
label results. Chrome verified selector labels/order, changed choices, private
recovery, native Save Draft/reload with an explicit excerpt, phone preview and
the resulting epoch projection. It also exposed the separate empty-excerpt
save conflict documented under M7 below; that path is not fixed by M6d.
Disposable Place 36047, its history and projections were removed. All 15,502
existing posts, 239,205 metadata rows, 193 epoch rows and 269 state rows retain
baseline hashes (volatile locks excluded); 30 unrelated dirty paths are
unchanged. Evidence and verified backup: `var/editorial-place-vocabulary-20261017/`
(ignored). `validate-all` reports 180 valid documents; nine formats have no
registry errors. No schema, DB migration, uploads or template artifact is needed.

**M6e completed locally, 17 October 2026: project rail capability.** The project
owner declares `editor.rail_control` in its existing registry map. Shared admin
settings expose `railControl`; the workspace uses that capability instead of
the format name. Omission/false hides the control without altering stored
`features.rail`. Defaults, choices, validation and public rendering remain
unchanged. An interaction regression also reproduced an existing bug: enabling
the rail left its position/treatment selectors disabled until reload. The
checkbox handler now updates their disabled state immediately, retaining the
selected values and unexposed rail mode. Production scope is four changed lines
across the owner declaration, admin payload and existing control handler.

Verification: 33 focused contract checks, 86 editor DOM tests, 545 storage/HTTP
and 14,128 registry/renderer checks pass, with targeted PHP lint/PHPCS/PHPStan
and JavaScript lint. Renamed owners use the capability; the historical project
name cannot override a disabled flag. Actual localized project/article settings
match their declarations and preserve the documents. All 180 stored documents
retain raw hashes, normalization and validation; 96 rail/version/skin cases
retain normalization, validation, resolved rail and rendered-output hashes.
Signed-in Chrome verified immediate enable/disable behavior, retained choices,
private recovery, native Save Draft/reload, paired revisions, cleared recovery,
phone preview and absence of the rail control on an article. Mode, identity,
prose, trash and excerpt remain exact.

Disposable project 37656, article 37658, their history and empty Set 696 were
removed. All 15,502 existing posts, 239,205 metadata rows, 193 epoch rows and
269 state rows retain baseline hashes, excluding volatile locks. This includes
the user's retained Schöneweide/Führungen edits. Thirty-six unrelated prior
worktree paths retain their hashes. Evidence and verified backup:
`var/editorial-rail-capability-20261017/` (ignored). `validate-all` reports 180
valid documents; nine formats have no registry errors. No schema, database
migration, uploads or template artifact is required. Changes remain local,
uncommitted and undeployed.

**M6f completed locally, 17 October 2026: row treatment-specific controls.**
The vocabulary declares treatment `item_fields`: Themenspalten exposes
`eyebrow`, while Programm / Chronik exposes `eyebrow`, `group` and `detail`.
The shared registry normalizes the field list and diagnoses unknown fields or
declarations without text-image items. The existing row editor reads the chosen
treatment's declaration, removing both treatment-name checks. Fields can be
declared independently; empty/omitted declarations hide them without removing
stored values. Labels, item shape, inspector rebuild timing, validation,
renderers and CSS are unchanged.

Verification: 197 focused checks cover 16 owner declarations across eight
formats, renamed gestures/treatments, explicit overrides, invalid declarations
and retained row values across versions 1–3. All 93 editor DOM, 545 storage/HTTP
and 14,128 registry/renderer checks pass, with targeted PHP lint/PHPCS/PHPStan
and JavaScript lint. All 180 stored documents retain raw hashes, normalization
and validation; 450 row/version/input cases retain validation, normalization
and rendered-output hashes, and all 41 treatment control combinations retain
availability. The actual admin payload matches the declarations and existing
media-preview hydration.

Signed-in Chrome edited all three fields, verified Themenspalten/compact/agenda
visibility after reopening the inspector, retained hidden values, private
recovery, native Save Draft/reload and phone preview. Media IDs, owner-hydrated
preview metadata, prose, untouched second row, trash and excerpt remain exact;
before/after revisions exist and recovery is clear. Disposable project 38085,
its history and empty Set 700 were removed. Post/meta counts remain 15,502 and
239,205; all 193 epoch and 269 state rows retain baseline hashes. Full post/meta
hashes differ: private autosave 26723 for Familienrallye 12191 was updated at
13:42:49, after the 13:42:14 baseline and before the 13:43:02 implementation.
It was preserved. No other post has a modified timestamp after that baseline;
full row equality is not claimed without a per-row baseline. A final read proves
all 180 canonical documents and 450 sample cases still match. The storage suite's
own preservation check passed. All 37 unrelated prior worktree paths are unchanged.
Evidence and verified backup: `var/editorial-row-fields-20261017/` (ignored).
`validate-all` reports 180 valid documents and nine formats have no registry
errors. No schema, DB migration, uploads or template artifact is needed.
Changes remain local, uncommitted and undeployed.

**M6g completed locally, 17 October 2026: legacy body HTML policy.**
The nine existing basic-HTML sections declare `legacy_body_policy: basic` in
the content owner's registry. The shared sanitizer reads that declaration;
other sections retain the default WordPress post-HTML policy. Active v2/v3
rich-text profiles still take precedence. The basic tag set, link handling,
validation, private recovery, version behavior and public renderers are unchanged.
Unknown policies produce an owner/section registry diagnostic.

Verification: 274 focused checks cover all 127 section declarations, renamed
owners/sections, explicit overrides, safe/rejected links, versions 1–3, profile
precedence, opaque trash and invalid declarations. All 93 editor DOM, 545
storage/HTTP, 363 lossiness and 14,128 registry/renderer checks pass, with
targeted PHP lint/PHPCS/PHPStan. All 180 stored documents retain raw hashes,
normalization and validation. All 3,810 sanitizer cases and 300 validation/
normalization/render cases match the baseline; the 127 policy choices match
the former allowlist. The actual admin payload contains the owner declaration.

Signed-in Chrome resumed owner-prepared private recovery containing a rejected
FTP link and displayed the `sections[0].body` lossiness diagnostic. The private
input remained exact and canonical content was unchanged. Corrected recovery
passed native Save Draft/reload and phone preview with bold, italic, a safe
link and a list. Version 1, trash and excerpt remain exact; before/after
revisions exist and recovery is clear. Disposable project 38517, its history
and empty Set 705 were removed.

Post/meta counts remain 15,502 and 239,205. Per-record comparison isolates the
only existing post/meta change to concurrent private autosave 26723 for
Familienrallye 12191, updated at 14:00:13 local time; it was preserved. Every
other existing post and non-lock metadata group retains its hash, including
Schöneweide 13251 and Führungen 13301. All 193 epoch and 269 state rows are
unchanged. Thirty-nine unrelated prior worktree paths retain their hashes.
Evidence and verified backup: `var/editorial-legacy-body-20261017/` (ignored).
`validate-all` reports 180 valid documents and nine formats have no registry
errors. No schema, DB migration, uploads or template artifact is needed.
Changes remain local, uncommitted and undeployed.

**M6 completed locally, 17 October 2026.** Remaining route/map, title/context,
media/caption, hidden-field, preview-editing and rail-default gates now consume
owner registry declarations. The generic editor has no format-name branches.
`iss-relations` retains payload, permissions and persistence; the closed Führungen
content task stays closed. Stored section schema and public renderers are unchanged.
Evidence: `var/editorial-audit-completion-20261017/`.

### M7. Version token invalidated by unrelated writes

The original `iss_editorial_saved_token` hashed `post_modified_gmt` plus every
revisioned meta of the post type. An ordering update that changed the timestamp,
or an unrelated revisioned metadata write, made the editor's next autosave fail
with "Inzwischen wurde eine neue Fassung gespeichert" despite unchanged editor
content. This does not require or imply running a queue/cron/graph sweep.

Fix: hash only editorial-owned keys plus title and excerpt.

**Concrete Place reproduction, 17 October 2026.** On disposable Place 36047,
native Save Draft with an empty excerpt enters
`iss_register_sync_public_fields_for_post()` through
`save_post_register_place` (priority 30). Its excerpt-fill `wp_update_post()`
re-enters `iss_editorial_validate_post_update()` after the outer update has
changed the post. The submitted edit token is rejected with “Inzwischen wurde
eine neue Fassung gespeichert”. Canonical JSON/projection retain the old
choices; the private draft retains both edits. These save/preflight functions
were unchanged in M6d. Reopening, continuing recovery, entering an explicit
excerpt and saving succeeds, clears recovery and projects both new choices.
Evidence: ignored `var/editorial-place-vocabulary-20261017/native-save-conflict.json`
and `after-conflict.json`. The nested-save case is fixed by M7a below.

**M7a completed locally, 17 October 2026: Place empty-excerpt native save.**
The register public-field owner now prepares its existing excerpt candidate
in the original `wp_insert_post_data` update for enabled Place JSON. This
removes the nested parent update that invalidated the submitted token. The
stored intro/present source, trimming and escaping are unchanged; manual
excerpts remain authoritative and a missing source leaves the excerpt blank.
Legacy/disabled JSON, new records and autosaves retain their existing paths.
Shared editorial preflight, saved tokens, revision capture and projection
writers are unchanged.

Verification: the pre-fix harness reproduced the conflict with two parent
preflight calls and retained private recovery (11 checks). The fixed path
passes 101 checks: one parent write and document save, paired before/after
revisions, exact JSON/trash retention, projected edits, manual/blank excerpt
cases and legacy behavior. Genuine saved-version changes, competing recovery,
invalid documents, invalid nonces and unauthorized submissions are rejected
without changing parent/history/recovery/projections. All 541 storage/HTTP,
42 revision-writer, 237 retention and 243 Place atomicity checks pass, with
targeted PHP lint/PHPCS/PHPStan. Signed-in Chrome saved disposable Place 36263
with the excerpt left empty, then verified automatic excerpt fill, both edited
vocabulary choices after reload, phone preview, paired revisions, cleared
recovery and projected choices. The fixture, its history and projections were
removed through their owners.

All 15,502 existing posts, 239,205 metadata rows, 193 epoch rows and 269 state
rows retain baseline hashes, excluding volatile edit locks. This includes the
user's retained Schöneweide/Führungen edits. Thirty-seven unrelated prior
paths are unchanged. Both release checks pass: 180 canonical documents valid
and no registry errors across nine formats. Evidence and verified worktree
backup: ignored `var/editorial-place-native-save-20261017/`; durable regression:
`tests/e2e/bin/place-native-save.php`. No schema, database migration, uploads or
template artifact is needed. This remains local, uncommitted and undeployed.

**M7b completed locally, 17 October 2026: content-scoped version tokens.**
The token now hashes title, excerpt, raw document, optional enablement and skin
metadata, plus explicit `version_meta_keys` from the existing format registry.
The Veranstaltung owner declares `_iss_entity_key`: that separate stored value
hydrates the editor's structure and must remain protected. Modification time
and unrelated revisioned metadata no longer invalidate the document token;
normal revision capture still copies every registered revision key. The shared
engine owns no new format-name branch or persistence system.

Full-form verification exposed a related overwrite: unchanged project order
controls could undo a newer list reorder once the false conflict was removed.
The existing project owner now submits the displayed order as a baseline for
both the native and administrator repair fields. Untouched fields retain the
current stored order. Intentional concurrent changes reject before parent or
revision writes, unless both changes already agree. Existing nonce/capability
checks, deliberate order editing and older request handling remain intact.

Verification: 13 baseline checks reproduced both false-conflict causes; a
separate failing regression demonstrated the stale order overwrite. The final
319 focused checks cover all nine formats, exact recovery, true title/excerpt/
JSON/enablement/skin/structure conflicts, native saves, unrelated revision-meta
retention, admin and restricted-editor order handling, competing drafts and
old-deployment token rejection/recovery. All 545 storage/HTTP, 42 revision-writer,
237 retention, 243 Place atomicity, 101 Place native-save, 50 account-switch/lock
and 14,128 registry/renderer checks pass. HTTP storage checks keep the original
editor tokens through a later order/timestamp write, then load saved previews,
validate and autosave successfully. Targeted PHP lint/PHPCS/PHPStan pass.

Signed-in Chrome opened disposable project 37146 at order 0, then a separate
owner update set order 73 and changed its modification time. Without reloading
or changing either stale order control, editing a chapter autosaved and native
Save Draft succeeded. Reload and phone preview retained the edited chapter,
exact prose/trash/excerpt and order 73; before/after revisions exist and recovery
was cleared. The project, its history and empty Set 691 were removed through
owners. All 15,502 existing posts, 239,205 metadata rows, 193 epoch rows and 269
state rows retain baseline hashes, excluding volatile locks. Thirty-four
unrelated prior paths are unchanged. Release checks report 180 valid canonical
documents and no registry errors across nine formats.

Evidence and verified backup: ignored `var/editorial-version-token-20261017/`;
durable regression: `tests/e2e/bin/editorial-version-token.php`. No schema, DB
migration, uploads or template artifact is needed. Already-open editors must
reload once after release because their old token inputs differ; existing
private recovery remains readable without rewriting it. M7 is complete locally,
uncommitted and undeployed. Queue, cron and graph changes remain separate.

### M8. Registry evolution freezes posts

An unknown section type, slot or treatment anywhere in `sections` or
`deleted_sections` makes validation fail. The editor then becomes inert and
every save, including recovery discard, is rejected (`config.validationError`
in `admin.js`). Freezing is the correct failsafe, but:

- trashed sections are validated against the current registry, so a section
  deleted long ago can lock a page;
- `deleted_sections` never expires (only a `manage_options` purge);
- `wp iss-editorial registry-check` validates the registry only, not stored
  documents.

Fix: validate trash leniently (keep unknown entries as opaque data) and add
`wp iss-editorial validate-all` as a pre-deploy gate after registry changes.

**Completed locally, 17 October 2026.** Trash now requires only a list of
objects with string types. Existing types, fields and values pass through storage,
draft sanitization, editor hydration and native revisions without current-section
normalization. Removed the
obsolete trash sanitizer and media/summary interpretation that assumed current
section schemas. M3 cache priming now excludes deleted media. Active sections
retain the existing strict validator. The explicit legacy **Textfarben
aktivieren** action still converts recognized deleted item descriptions by
design; unknown types and fields remain untouched.

Restoration uses the existing serialized queue and authenticated AJAX endpoint
for a validation-only request before moving a section into active controls.
This prevents controls silently replacing retired slot/treatment values. A
failed check leaves trash and draft tokens unchanged; concurrent local edits
cancel restoration, and a queued native save waits for a successful restore.
Successful restoration uses the returned validated section, including registered
alias normalization and media hydration, before current controls are rendered.
Chrome caught a diagnostic hidden inside the inactive inspector; restore errors
now use the visible save-status area.

`wp iss-editorial validate-all [--format=json]` audits raw stored canonical JSON
in keyset batches of 100, across registered formats and post types. Disabled
documents, all post statuses and malformed/empty values are checked; revisions
and private autosaves are excluded. Valid documents exit 0; invalid documents
exit 1 with IDs and validation errors. It performs no repairs or writes. The
local canonical sweep passed **180 documents, zero invalid**. Temporary tables
prove pagination beyond 100, invalid-data exit status in JSON/default output,
history exclusion and a no-write SQL guard.

Focused regression: **334 validation/trash/CLI checks**, **64 editor DOM tests**,
537 storage/HTTP checks, 149 cache checks, 42 revision-writer checks and 14,127
registry/renderer checks pass. PHP lint/PHPCS/PHPStan, JavaScript lint and diff
checks pass. Signed-in Chrome on disposable project 32921 verified rejection
with a visible explanation, successful valid restoration, an unrelated active
edit, native Save Draft and reload. Canonical JSON retained retired fields and
legacy markup exactly; browser console errors were empty. The fixture, its
revisions/autosave and empty Set 632 were removed through the existing owners.
The final Chrome check on project 33463 restored `image_wall` as `galerie` with
`gallery_layout=wall`, exposed the current gallery controls, and saved/reloaded
successfully; its revisions/autosave and empty Set 641 were also removed.
All 15,499 existing posts (including private autosaves/revisions) and 239,197
metadata rows match their pre-slice hashes, excluding volatile edit locks.
Seventeen prior dirty paths outside this slice retain their recorded hashes;
overlapping H2/M3/M4 changes are preserved in the slice diff and verified backup.
Evidence and the pre-slice worktree backup are in ignored
`var/editorial-validation-20261017/`. These are code-only changes; no database
migration or uploads artifact is needed. Human/cross-browser UAT and staging
release checks remain separate.

## Low

- **L1 Dead code.**
  - `iss_editorial_add_meta_boxes`: no-op hook (`admin.php:17`).
  - `iss_editorial_post_type_supports_format`: unused (`formats.php:304`).
  - `iss_editorial_render_route_relation_hidden_fields`: unused (`admin.php:77`).
  - Unused `$route_config` and `$enabled` in `render_main_canvas`
    (`admin.php:99`–`100`); unused `$post_type` in `save_meta_box` (`:478`).
  - `_autosave` meta deletion (`admin.php:499`, CLI): nothing writes that key.
  - Orphan CSS from the pre-workspace modal in `assets/admin.css`:
    `.iss-editorial-modal-open`, `.iss-editorial-insert-preview*`,
    `.iss-editorial-gesture__*`, `.iss-editorial-opening-badge`.
- **L2 Payload.** The edit page ships the document three times (hidden input,
  `data-document`, localized `document`) and the format sections twice.
  Localized `document`, `sections`, `enabled` and `workspace` are never read by
  JS. Keep `document` until after `iss_editorial_admin_settings` (the
  Veranstaltung filter reads it), then drop it.
- **L3 `enabled` flag.** The canvas always posts `1`; disabling is reachable
  only via CLI or tests.
- **L4 CLI importers.** About 1,500 LOC of one-shot Gutenberg importers in the
  generic engine. Once those migrations are closed, archive them under
  `tools/` or move them to iss-content.
- **L5 Three divergent href checks.** PHP `iss_editorial_body_href_is_safe`
  and `admin.js` `isSafeRichTextHref` do not strip control characters;
  `rich-text.js` `safeHref` does. Server-side kses (`wp_kses_bad_protocol`) is
  the real gate, so this is not exploitable, but it is drift. Drop the PHP
  stripper and route v1 through `rich-text.js`.
- **L6 Album import truncation.** Editorial-set import requests `perPage=120`
  (`admin.js:2342`); the service clamps at 120
  (`editorial-sets-service.php:631`); the client ignores `total`. Sets over 120
  are silently cut while the status reports success. Page through or warn.
- **L7 Missing plugin headers.** No `Requires PHP` / `Requires at least`. The
  code needs PHP 8.1+ (`array_is_list`) and WP 6.x APIs (HTML Tag Processor,
  autosave meta). Version constant still `0.1.0`.
- **L8 Hidden coupling.** Text profiles depend on a caller-mutated
  `$format['document_version']` (`storage.php:702`, `:749`; `rich-text.php:14`).
  A new caller of `iss_editorial_sanitize_section` silently gets v1 rules. Pass
  the version explicitly.
- **L9 Persisted preview caches.** Hydration writes thumbnail/width/height/mime
  into stored JSON (fill only when empty), so editor previews go stale after
  WebP conversion or thumbnail regeneration, and the values are revisioned. The
  frontend correctly uses resolved data. Hydrate for transport only and strip
  on save.
- **L10 Minor robustness.**
  - `canPurgeDeletedSections` is enforced client-side only (revisions mitigate).
  - `iss_editorial_validate_post_update` matches by format slug, not by
    `post_ID === $_POST['post_ID']`; a cascaded save of another same-format post
    during the request would `wp_die` 409. No current trigger found; one-line
    guard.
  - Core hooks registered as closures (`revisions.php`, `preview.php`) cannot
    be removed by tests or other plugins.

### Low findings resolved locally — 17 October 2026

| Item | Resolution |
| --- | --- |
| L1 | Removed the no-op hook, unused helpers/locals, unused autosave-meta cleanup and nine orphan admin CSS blocks. Active gesture description styling remains. |
| L2 | One hidden document and one section registry; owner filters receive the full settings before unused localized fields are dropped. Actual Project/tour admin payloads verified. |
| L3 | Intentional: the canvas is for enabled documents and eligible new auto-drafts; disabled documents keep their legacy authority. Saving that canvas enables it. No extra disable UI was introduced. |
| L4 | Legacy migration commands moved to `iss-content/includes/editorial-migrations-cli.php`; command names and revision guarantees remain. Shared diagnostics stay in the engine. |
| L5 | Shared browser rich-text sanitation/configuration; PHP uses WordPress tag parsing and protocol checks, preserving safe single/double/unquoted links and legacy HTML policy. |
| L6 | Set album import pages through all items, checks response consistency and merges only after success. A 241-item import, later-page failure and changed total are covered. |
| L7 | Plugin 0.2.0 declares PHP 8.1 and WordPress 6.4 minimums. |
| L8 | Body/profile/section sanitation requires schema version explicitly; no caller-mutated format state. |
| L9 | Known active media/archive references and album thumbnails omit derived preview caches on canonical/private save; editor transport refreshes through the resolver. Captions and opaque trash remain intact; no bulk migration. |
| L10 | Server-side purge permissions, restoration identity for immediate edits, submitted-post guards and named removable core hooks. Transient restoration identity never becomes canonical content. |

Picker/media/archive/album orchestration now lives in `assets/pickers.js`;
`admin.js` retains document state and editor orchestration. The module delegates
to existing media, archive and Set owners.

Final local verification: 99 editor DOM checks; 43 focused completion checks;
545 storage, 363 lossiness, 334 validation, 237 retention, 42 revision,
319 version-token, 243 Place atomicity, 101 Place native-save, 74 Projects-only
access and 14,126 registry/renderer checks passed. Degraded mode (162), format
cache (36) and all M6 contract suites passed. PHP syntax, PHPCS, PHPStan, ESLint
and scoped admin CSS lint passed.

All 180 canonical JSON values retain their raw hashes and validity. Thirty-two
normalized documents change only by dropping known derived preview caches;
authored fields are identical. All 180 anonymous public renders match baseline
hashes, as do 3,810 sanitizer and 300 sample validation/render cases. Detailed
logs and comparisons are in `var/editorial-audit-completion-20261017/` (ignored).
Signed-in Chrome passed Project restore/immediate edit, private recovery, native
Save Draft/reload, Set/native media pickers, phone previews and the tour route
panel/map shortcut. Both disposable documents retained captions, excerpts and
opaque trash; the route owner remained unchanged. Actual validation supplied the
restoration fingerprint and canonical save removed it. Fixtures/history/Set were
removed. Final post/meta hashes are identical across all 15,502 posts and 239,205
non-lock metadata rows; 193 epoch and 269 state rows are also unchanged.
No database migration or uploads artifact was needed; the verified staging
release is recorded below.

## Security

No exploitable issue found:

- Nonces and capability checks on the AJAX draft save, the canonical save and
  the preview routes.
- The embedded preview is bound to the authenticated snapshot token.
- DOM is built with `createElement`/`textContent`; `innerHTML` only behind the
  template sanitizers or on static markup.
- `postMessage` handlers check origin, source and token.
- kses runs on all rich fields server-side.
- `sandbox="allow-scripts allow-same-origin"` on the preview iframe is not a
  security boundary; the code comment already says so.

## What is solid

Read-lenient / write-strict split, drafts on core autosave revisions, the
lock/session handling, and the volume of storage/lock/UI checks.

## Staging release — 17 October 2026

Code `9985528` deployed through GitHub with a clean fast-forward from `d9c4d79`.
A fresh verified 119-table DB/uploads/intake/code/runtime rollback bundle is at
`/srv/industriesalon/stage/backups/iss-backup-20261017T130015Z/`. No database,
content, uploads or configuration transfer was needed; no historical purge ran.

Target validation passed all 180 documents and nine format registries. All 180
anonymous public document render hashes are identical before/after release.
Eleven public routes remain HTTP 200/noindex. Restricted Projects login and
editor access, unrelated-screen denial and five exact editor assets passed.
Every staging post and non-lock metadata row retains its hash (15,504 posts,
239,197 metadata rows), including private drafts; 193 epochs and 269 states are
also unchanged. Service health and post-release logs passed.

Read-only staging probes confirm registry builds 4 → 1 for a Futura read,
twenty ticket lookups sanitizing 20 → 1 with identical output, and larger
reference reads dropping 30 → 4 / 50 → 2 queries. Function timings and three-
request public HTTP samples are in `var/editorial-release-20261017/` (ignored);
these are not interactive Query Monitor measurements or a latency guarantee.

Human/cross-browser UAT and interactive Query Monitor/editor-cadence review remain
separate acceptance work. Already-open editors must reload after token changes;
private recovery remains available. Later saves enforce revision retention, so
keep the matching backup. No adjacent queue/cron/graph work is implied.
