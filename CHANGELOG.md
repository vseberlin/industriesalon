# Changelog

This file records durable project changes. Keep it compact: current state belongs in
`handoff_CURRENT.md`, active follow-up in `TODO.md`, and detailed investigation can
be recovered from Git history.

## 2026-10-27 — Responsive heading sizing and word wrapping

- Shared heading tokens now limit large type by available copy-column width.
  Existing theme, renderer and skin rules consume the limit at their source;
  no page override stylesheet or JavaScript sizing layer was added. Editor
  styles now load the same token sheet as public pages.
- Removed automatic heading hyphenation and arbitrary anywhere wrapping, and
  replaced restrictive 10–18ch title widths with the shared readable measure.
  This resolves tour titles such as Elektropolis and Licht und Transparenz,
  and landing words constrained despite ample column space. Font families,
  content and ordinary paragraph hyphenation remain unchanged.
- Local browser acceptance spans phone, tablet, laptop and desktop widths;
  lint, runtime/editor token checks and existing editorial CSS tests pass.
  Code-only, with no template/content/DB/uploads artifact. Deployed in `9f045fa`; see the current handoff for staging verification.

## 2026-10-27 — Explicit editor failure and recovery

- Established the fail-loud contract: visible, accessible errors, retained input,
  actionable recovery and no false save confirmations. JSON failures preserve
  codes/field paths; repair, comparison and downloadable recovery use existing
  WordPress documents/autosaves and owner endpoints.
- Bounded request/preview waits, exact-snapshot lost-response reconciliation,
  heartbeat nonce renewal, explicit stale-draft comparison, validated repair and
  native-dialog focus handling replace recurring dead ends. Alerts stay within
  the expanded workspace and remain interactive.
- Routes check canonical/draft versions, retain edits made during requests and
  reject incomplete station saves. Publication verifies history and stored state.
  Shared save/preview actions flush route drafts without publishing them.
- Existing fetch/API paths had no shared timeout/error contract; ISS Core now owns
  the small shared admin request helper and connection-scoped write mutex. Domain
  recovery remains in its current plugins. No new table, role change, content
  migration, uploads artifact or private content transfer is required.
- Extended the existing validation gate to private drafts and editor-hydrated
  copies, and added HTTP/DOM regressions for the failure paths. The gate exposed
  an event adapter deriving an empty skin from legacy entity keys; defaults now
  use the existing owner mapping without rewriting stored documents.
- Local verification: 1,846 PHP/HTTP checks, 122 DOM tests, targeted static checks
  and Chrome recovery/native-save acceptance pass. All 191 canonical documents
  pass the expanded audit; one existing private landing draft has an incomplete
  link and correctly keeps that audit nonzero. Content remains unchanged.
  Deployed in `9f045fa`; staging canonical/private-draft audit and Anja browser checks pass.


## 2026-10-27 — Legacy tour text editing and repair

- Elektropolis's private version-1 draft contained old column-layout wrappers.
  Loss protection correctly rejected them, but the editor used unrestricted
  legacy text controls and offered no explicit repair. The saved preview stayed
  unchanged even after section insertion/removal.
- The editor now follows each section's existing `legacy_body_policy=basic`:
  it offers the existing formatting simplification control for retained invalid
  markup and constrains new paste/edit output. Nested layout wrappers unwrap
  without creating nested paragraphs. Text, safe links and document version stay
  within the existing contract; server validation remains strict.
- Validation: 110 DOM tests including legacy repair/paste/add/trash/undo,
  275 PHP legacy-body checks, targeted ESLint and JavaScript syntax checks.
- Code requires no schema, role or uploads migration. The current staging draft
  has a private mode-0600 before-image; any repair stays private and never updates
  the public tour. No private content artifact belongs on GitHub.
- Deployed and repaired Anja's private draft through the editor. Verified unchanged
  wording, links, remaining draft content and public tour; browser add/edit/save/
  delete and reload pass. All 16 tours and her one tour draft validate and permit
  editing/deletion. The temporary test section was removed.

## 2026-10-27 — Full editorial operator responsibilities

- Expanded the existing role from draft editing to create/edit/publish/delete in
  its established content areas, including other authors and private content.
  Bilder-Sets now includes review, promotion and deletion capabilities.
- JSON section purging follows parent edit/delete permissions in both the UI and
  canonical/autosave paths, using native WordPress object capabilities.
- Newsletter sending, scheduling, testing, pause/resume/cancel and deletion use
  the existing delivery capability. Draft-only accounts and blocked subscriber,
  configuration, archive and technical administration remain bounded.
- Local checks: 517 operator, 43 editor recovery and 74 dashboard assertions;
  PHP lint, PHPCS and PHPStan. Fixtures removed, no mail sent.
- Capability version `2026-10-27-editorial-operator-3` uses existing owner-setup
  check/apply/verify with a private target before-image. No content, SQL export,
  uploads or template artifact is required; target accounts are preserved.
- Deployed on staging; 278 account and 74 dashboard checks plus Anja's Chrome
  session verified the controls. Passwords, sessions, assignments and other roles
  are unchanged; no existing content was deleted and no newsletter sent.
  Historical draft 28 retains a recipient query using a former table prefix;
  the current subscriber table exists. Its count error and untested delivery
  are recorded in TODO.md; no newsletter data was changed.

## 2026-10-25 — Bestandskatalog and AI drafting template

- Added `docs/vorstand/bestand.md`, a German capability catalog derived from the
  registries (CPTs, gestures and treatments, skins per format, functions,
  templates, patterns, theme tokens). The AI templates now require reading it
  first: template A drafts proposals from existing parts, template B maps every
  proposal element to the catalog (VORHANDEN/KONFIGURIERBAR/ERWEITERUNG/NEU) and
  restricts designs to existing skins and tokens. `AGENTS.md` requires updating
  the catalog with registry changes. Documentation only; no artifact needed.

## 2026-10-25 — Vorstand entry and AI review prompt

- Added a short root `README.md`, a German non-developer overview
  (`docs/vorstand/README.md`) and a fixed, neutral AI review prompt
  (`docs/vorstand/ki-pruefauftrag.md`) so board proposals made with AI tools are
  assessed against the plugin ownership map and no-parallel-systems rule before
  decisions. Documentation only; no code, database or uploads artifact needed.

## 2026-10-25 — Editorial operator dashboard

- Restored Dashboard navigation and made it the editorial operator login landing
  page. Native WordPress dashboard widgets now present the current permitted menu
  sections with short German descriptions and links, replacing the standard cards.
  Existing ISS Core role/menu ownership and WordPress widget APIs cover this need;
  the Operations URL resolver is shared instead of adding a parallel navigation
  registry, custom layout or route. Administrators and Project UAT are unaffected.
- PHP lint, targeted PHPCS/PHPStan and 74 read-only menu/widget/role checks passed
  locally and on staging (74 checks each); code release `e9d9f12` is deployed
  through GitHub main. Anja's Chrome session verified 12 cards, all destinations,
  dashboard return navigation and layouts at 1920, 1024 and 390px without overflow.
  Both homepages returned 200; staging services and inspected recent PHP logs passed.
  No database, uploads, capability migration or account change is needed.

## 2026-10-25 — Remove redundant personal Profile navigation

- Removed the personal Profile entry from the editorial operator sidebar through
  its existing menu allowlist. Account-toolbar access and the profile route remain
  available; Personen & Organisationen is retained. No capability/account changes,
  database migration or uploads artifact are needed.
- Local PHP lint, PHPCS, PHPStan and native role/menu/route checks passed.
  Code `678334c` is deployed through GitHub main. Staging Chrome verified the
  missing sidebar item, retained Personen & Organisationen, working profile page
  and account-toolbar links. Both homepages returned 200; services/logs passed.

## 2026-10-25 — Personen & Organisationen admin label

- Renamed the Profile content menu and list heading to `Personen & Organisationen`
  to describe both supported entity kinds. Singular profile actions, public URLs,
  content and capabilities are unchanged. No database or uploads artifact is needed.
- Local PHP syntax, targeted PHPCS/PHPStan and native WordPress menu checks passed.
  Code `9a0e5f2` is deployed through GitHub main to staging; Anja's existing Chrome
  session verified the sidebar and list heading, including readable line wrapping.
  Staging PHP lint, homepage HTTP 200, service and recent PHP-log checks passed.

## 2026-10-25 — Steuerung UAT simplification

- Removed the Preise and Mission Statement tabs and the Kontakt Buchungslink
  field. Existing values, public renderers and JSON backup compatibility remain;
  normal settings saves cannot clear the retired values. Removed-tab bookmarks
  fall back to Standort & Adresse. Updated the editorial guide and asset version.
- PHP lint, PHPCS, PHPStan, targeted ESLint and 18 read-only WordPress runtime
  checks passed locally; staging passed PHP lint and the same 18 runtime checks.
  Released code `98f84aa` via GitHub main. Anja's existing staging session verified
  the six tabs, simplified Kontakt form
  and fallback from both retired bookmarks. The options digest stayed unchanged;
  homepage returned 200, containers stayed healthy and inspected logs showed no
  PHP errors. No database/uploads artifact, account change or restart was needed.

## 2026-10-25 — ISS-Betrieb dashboard

- Replaced the ISS-Betrieb landing page's technical-list emphasis with linked
  cards for the current user's permitted submenus, in sidebar order. ISS Core
  reads WordPress's existing menu registry and native URL hooks; no parallel
  navigation configuration or permission model is introduced. Native admin card
  surfaces gain a small dashboard-only responsive stylesheet. Links and the
  collapsed technical-checks section work without JavaScript or custom CSS.
- Local runtime verification covers six editorial-role and eight administrator
  cards, both native content and plugin routes, and page-scoped asset loading.
  This code-only presentation change needs no database or uploads artifact.

## 2026-10-25 — ISS-Betrieb admin label

- Renamed the shared Operations menu, page title and heading to `ISS-Betrieb`,
  including the editorial operator interface used by Anja Meyer. The existing
  `iss-operations` route, submenu destinations and capability gates are unchanged.
  This code-only label update needs no database migration or uploads artifact.

## 2026-10-23

- Cleaned the editorial Operations menu: Archivbeiträge, Register Werkzeuge and
  Fallback-Modus are revoked for Editorial Operators, including direct access.
  Administrators retain access. Publikationen and Rückblicke move to the main
  menu; labels are Hinweisbanner, SuperSaaS-Termine and Bilder-Sets. The existing
  capability migration removes stale grants with a private target before-image;
  no content or uploads transfer is needed. Local checks passed 379 assertions
  plus PHPCS/PHPStan/syntax; staging passed exact-menu and 30 authenticated route
  checks, with account/other-role preservation verified and no email sent.

- Added the bounded Editorial Operations role in the existing ISS Core capability
  system. Existing broad roles could not provide the requested content-only menu.
  Native CPT/media capabilities and object-specific metadata checks allow draft
  creation and editing without Posts/Videos or administrator privileges. Existing
  roles retain their permissions. Operations submenus and personal/content profiles
  are included; the Newsletter integration limits this role to drafts and blocks
  sending/deletion. No third-party plugin edits or parallel permission store.
  Local validation: 366 isolated role/REST/metadata checks, targeted PHPCS/PHPStan
  and syntax checks; 103 existing Project UAT checks also passed. Staging login,
  37 allowed/denied routes, exact navigation, and Newsletter draft create/edit/reload
  passed; the unsent test draft was removed. No account email was sent and existing
  user rows/role grants were preserved. Role changes use the existing owner-setup migration with a
  private target backup; no content/subscriber/uploads transfer is required.

- Refactored SuperSaaS administration into overview, review queue, separate
  tour/event series and connection views in the existing occurrence owner.
  Removed obsolete dense tables and capacity wording; moved summary SQL into
  the owner service. Manual runs reuse existing maintenance receipts, results
  are user-scoped, row actions preserve filters and stale mappings are rejected.
  Explicit mappings, unlinking and ignore/review decisions now survive later
  title inference and repeated sync. Connection validation preserves the complete
  previous configuration on error and never renders the saved API secret.
  Commerce 0.5.1 separates staff tasks into tabs, makes booking/mail actions
  contextual, adds pagination and keeps tables within labelled scroll regions.
  Occurrences 0.2.0 and commerce retain their data/booking ownership; no new
  scheduler, route, table or migration, and no uploads artifact is required.
  Local verification: 96 isolated admin checks, 218 commerce assertions,
  31 public occurrence assertions, PHP/static/CSS/JS checks and authenticated
  Chrome navigation/layout checks. Released `e668797` through GitHub to staging;
  96 isolated admin checks, 31 public-date checks, commerce verification,
  seven HTTP checks and authenticated desktop/mobile admin review passed there.
  Target credentials, roles, configuration and capacity were preserved; no
  data import, restart or booking/mail action. Production remains untouched.

- Released consent code, bounded settings migration and 27 local preview imports
  to staging through GitHub. All five staging browser scenarios and the current
  WordPress privacy checks pass; real YouTube and CARTO rendering were verified.
  Target accounts, authentication and newsletter records were preserved, with a
  verified full backup before release.

- Removed the consent-era static Atlas fallback at the owner’s request. CARTO
  remains the interactive basemap; consent still gates tile requests and
  withdrawal removes tiles. Existing staging key plumbing is unchanged. The
  same key is configured in ignored local runtime settings with a private backup;
  actual local CARTO tiles and zoom passed browser verification.

- Restored video previews with 27 locally imported YouTube thumbnails, preserving
  the existing editorial featured image. Reused WordPress Media Library and the
  video import owner for explicit, idempotent imports and offline release copies;
  frontend requests never fetch remote previews. Replaced long inline consent
  notices with image/play controls and a compact nonmodal bottom popup, equal
  accept-all/decline-all actions and expandable individual choices. Local preview
  health checks, before-images and paired private media/API artifacts accompany
  the change. Privacy text now describes local previews and the bottom popup.

- Added the missing consent owner for YouTube and CARTO, with theme-owned native
  controls, separate choices and withdrawal. Videos no longer load external
  thumbnails; Atlas retains its CARTO basemap behind consent.
  Reused WordPress Site Health and ISS Operations for update-invalidated,
  30-day browser review receipts instead of adding a scheduler. The existing
  newsletter adapter enforces double opt-in, removes individual tracking and
  persistent identity cookies while preserving signed legacy links. Updated the
  native German privacy notice, including confirmed Hetzner hosting. A bounded
  target-local settings migration pairs plugin activation/newsletter options/
  WordPress privacy-page selection; no subscriber or schema transfer.
  Added browser/PHP privacy regression coverage and the regular update procedure.

## 2026-10-22

- Removed repeated tour names beneath dates in the shared Führungen panel;
  date/time, booking state and actions remain. Removed the unused name styling.
  Renderer-only change with no database or uploads artifact.

- Unified the shared Führungen hero grid and information panel with the
  Elektropolis proportions. Native Group blocks replace the forced Columns
  layout; booking labels wrap without squeezing dates, and slot descriptions
  use the shared compact typography. All 16 published tours passed desktop,
  tablet and mobile layout checks. Theme-owned template; no database or uploads
  artifact is required.

- Implemented the WordPress booking ledger in the existing commerce owner:
  stable departure inventory, atomic 15-minute holds, accepted onsite allocations,
  voucher reservations/redemptions, immutable event history, transactional payment
  transitions and late-payment exceptions. Schedule/public availability now reads
  commerce capacity. Native staff controls and CSV expose the same state. Added
  the private legacy-evidence exporter/importers and staff digest outbox with a
  bounded host minute job. These owner tables provide the missing departure lock,
  balance history and durable mail state; no booking CPT or generic queue.
  Capacity is 30 per departure. Private backup review identified nine future
  legacy reservations (18 seats) and 108 vouchers; real cutover requires current
  reconciliation and one redemption authority. Additive v5 migration preserves
  request/payment identities; no content/uploads transfer. Local concurrency,
  provider, migration, worker and browser checks are recorded in the handoff.
  Staging migration, sandbox checkout/callback/receipt, duplicate callbacks,
  Mailpit capture and minute-cron health passed. Settled booking cancellations
  survive reconciliation; stale paid snapshots cannot erase a partial refund.
  Target accounts and existing requests were preserved; real activation stays gated.

- Added Mollie test checkout to the existing booking request and shared native
  dialog, with verified webhooks and an uncached receipt. Server-owned prices,
  durable payment binding, frozen idempotent creation and per-booking locks
  prevent duplicate charges and forged payment confirmation. Staff workflow and
  SuperSaaS seat handling remain separate. Provider status is visible in the
  existing request admin/CSV; no new table, queue, SDK or frontend skin.
  The additive owner migration preserves existing requests in a private verified
  before-image; no uploads/content artifact. Live keys are refused and new
  checkout requires private target configuration. Validation: 207 isolated PHP
  assertions, 16 migration checks, nine JavaScript store tests, lint and static
  analysis. Staging acceptance proved card checkout, a real provider webhook,
  desktop/mobile receipts and idempotent callback replay. Unsuccessful PayPal
  attempts correctly remain open; preserve the verified checkout link when
  Mollie omits it so the same payment can be resumed. Historical requests remain
  unchanged; notification mail and live payments remain disabled.

- Removed public supplier repository citations from 41 Place documents and
  related fact/timeline wording, retaining the internal import provenance.
  Public milestone reads omit supplier audit fields; reviewed summary revisions
  use exact before-images through the epoch owner. Activated the existing upload
  gesture on all 62 published Places with open questions (61 newly opened).
  Files and text use the existing private intake/moderation workflow. Site-bound
  content manifests and receipts remain private; no media or schema artifact.

- Reviewed all 69 supplier records against 84 local/staging Places. Added a
  bounded owner-API migration for 36 present-day descriptions and four fact
  fields, with dated source links and qualified project targets. Checked
  conflicting KAOS, Treptow-Ateliers and Katapult claims against primary sources;
  corrected the unsupported completed-departure claim for Wilhelminenhofstraße 92.
  Preserved local histories, 17 local-only Places, the ADMOS draft, Reinbeckhallen
  component mapping, media, editorial relationships and target accounts.
  Private site-bound payloads and before-images accompany the migration; no
  uploads or schema change. Verification recognizes the existing graph owner's
  regeneration of derived rows while guarding source relationships and attributes.
  Corrected the existing Atlas contract checker to validate the already-published
  `period_label` string; its omission caused the same false failure before this
  content update on staging.
  Applied and verified on local/staging: 191 valid canonical documents, all 36
  changed Place projections, all 83 public Place pages per host, protected source
  data and idempotent receipts. All 84 Place documents match between targets.

## 2026-10-21

- Retired the leaked local administrator credential, cleared local sessions and
  verified it no longer matches any local, staging or touchtable account. Staging's
  previously rotated password remains unchanged; sessions and admin application
  passwords remain absent. Disabled the unrestricted staging SQL importer and
  replaced the full-refresh account-copy policy with target-owned identities and
  reviewed content migrations. Browser authentication now reads a private,
  target-bound credential file with no hard-coded password or retained trace.
  Incident evidence remains private and intact; historical credential/session
  backups must never be replayed as deployment inputs.

- Contained the unrecognized staging administrator login: revoked all WordPress
  sessions, checked/revoked the affected account's application passwords (none
  existed), and compared accounts, code, uploads and scheduled tasks against
  pre-incident backup, trusted Git and official WordPress checksums. No unexplained
  persistence was identified. With separate user approval, moved staging's existing
  upload-PHP Nginx deny rule ahead of the generic PHP handler; syntax, reload,
  blocked-upload and ordinary-route checks passed. Private evidence retained;
  no content migration, uploads transfer or source-code deployment.

- Patched the staging and touchtable VPS WordPress runtimes to PHP 8.3.36 for
  CVE-2026-109618/109619, using a pinned build of the official WordPress recipe
  while published WordPress images still lacked the fixes. Fresh verified
  backups and recovery/build artifacts are retained privately. Both services,
  nine routes and all PHP extensions passed checks; installed cores, persistent
  mounts and other services were preserved. No database migration or uploads
  transfer. STRATO production was already patched.

- Project UAT can browse existing private Sets through the new read capability
  and select their approved media. Archive selection reads now use the existing
  editable-context permission, so an unlinked selection can be opened without
  granting its editing permission. UAT creation/member/link writes are denied.
  Set management and unrelated administration
  remain restricted. Apply the existing `wp iss-core caps migrate --apply`
  after deploying; back up target roles first. No SQL/content/uploads transfer.
  Local Project UAT passes 103 assertions with fixtures removed; PHP lint,
  PHPCS and PHPStan pass for the affected plugins.

## 2026-10-16

- Proposed `docs/architecture/skin-contract-adr.md` after reviewing the
  gesture/skin model in code: the shared gesture half is complete (13 portable
  gestures, one renderer since `9eab967`), but skins still target per-domain
  markup, set only 2–4 colour values on shared gestures, branch markup in PHP
  and have several authorities per page. The ADR defines skins as role-token
  value sets plus feature defaults, one skin authority and registry, one
  treatment mechanism, and a gated migration starting with a staging visual
  check. Cross-referenced from the layering ADR and the consolidation plan.
  Documentation only; no code, database migration or uploads artifact.

- Recorded a static audit of the public CSS system against the layering ADR
  and the gesture/skin consolidation plan in
  `docs/project/css-system-audit-2026-10-16.md`: gestures forked per renderer,
  skins implemented as selector forks instead of value sets, portable gestures
  ignoring the host skin, bypassed semantic tokens, ungoverned typography and
  spacing, a 449 KB global payload, plus defects (24 undefined variables,
  sub-AA subtle ink) and a fix order. Follow-up is in `TODO.md`.
  Documentation only; no code, database migration or uploads artifact.

- Recorded a static audit of the shared JSON editor (`iss-editorial` plus its
  iss-content/theme contract edges) in
  `docs/project/json-editor-audit-2026-10-16.md`: three high findings (degraded
  mode data loss in the Veranstaltung fallback writer, revisions only on the
  form path, silent lossy normalization), eight medium performance/longevity
  findings and a low cleanup list, with a fix order. Follow-up is in
  `TODO.md`. Documentation only; no code, database migration or uploads
  artifact.

- Corrected logout with multiple logins of the same account. A remaining login
  no longer keeps another browser's project lock alive. Core `_edit_lock`
  identifies only the user, so ISS Editorial now records its owning session
  fingerprint in private post metadata and releases that exact native lock on
  logout. No raw login token is stored or session invalidated; a lock renewed
  by another browser is preserved. Native expiry/takeover remains the fallback
  for older untracked locks. Verified 50 authenticated lock/logout checks,
  511 storage checks and 74 project UAT checks. No content migration, SQL
  transfer or uploads artifact is required; ownership is recorded on edit or
  heartbeat using the existing WordPress Metadata API.

- Fixed recurring JSON-editor locks during account switching. ISS Editorial
  now releases native post locks only when their owner has no valid WordPress
  session, closes stale takeover dialogs after a confirmed native heartbeat,
  and stops old tabs renewing locks or saving under a changed login. Unsaved
  fields stay available with a reopen link; genuine concurrent editors retain
  their locks. This extends core post locks, session tokens and heartbeat
  without adding a lock store or changing draft/revision ownership. Verified
  with authenticated switch/logout requests, browser lock/release cycles,
  35 lock checks, 45 UI checks and 511 storage checks. Code-only deployment;
  no database migration or media artifact is needed.

- Fixed silent native Publish/Update failures when editorial recovery is still
  pending. The submit handler now reports the reason and focuses/scrolls to
  the recovery choice or validation/save error, preserving both versions.
  Reproduced on local Futura 30743 under admin and verified the fix in Chrome;
  all 43 editor UI checks pass, including recovery blocking and submission
  after an explicit choice. This code-only fix needs no DB/media artifact.

- Completed the user's full local-to-staging refresh, replacing the narrower
  project-only transfer: all 118 source DB tables, uploads, private intake and
  WordPress 7.1.3 core were synchronized with verified rollback backups. Kept
  staging URL/noindex, credentials, security audit and host configuration;
  retired the obsolete automatic Event Drop bridge. Normalized content hashes,
  complete file checksum comparisons, media coverage and UAT access/save checks
  pass. Burmeister now uses the same local account/password on staging, without
  email. GitHub now carries code/docs only: removed 112 transfer-data artifacts
  from tracking, retained private copies and left history unchanged.

- Synchronized the queued website release through GitHub to staging, with a
  verified database/uploads/intake/runtime backup. Applied the taxonomy,
  owner/capability, commerce, Schöneweide and tour-category migrations; installed
  the paired Futura/KiezKarte review drafts and media while preserving existing
  published projects. Created Burmeister's project-only staging account without
  email. The user explicitly retained public noindex previews. UAT access/save,
  media, schema and route checks passed; maintenance activation and human/browser
  acceptance remain separate follow-up.

- Reviewed the older staging baselines during the UAT release. The existing
  Schöneweide and tour migrations now accept one exact legacy landing hash and
  four exact tour before-images; all other drift still fails closed. The final
  content and target-local backup/readback checks are unchanged.

- Added a project-only UAT role using the existing `iss-core` capability
  migration and native project CPT permissions. It can create/edit projects
  and use media, without publishing new projects, deleting, or administering
  other content. Existing roles retain their project rights. Restricted
  navigation shows only Projects, redirects login there, and rejects other
  admin screens while preserving personal profile access. No permissions plugin
  or separate editor was introduced. Runtime tests cover native JSON
  autosave/save/reload, REST denials and unchanged existing content.

## 2026-10-15

- Added a native Schöneweider KiezKarte review project from the handover ZIP,
  using existing gestures for the opening, three-step introduction, 20 partner
  cards, sales locations, fund explanation, participation, announcement and FAQs.
  A shared full-image choice for Bild & Text/Linkkarten keeps the supplied card
  and logos intact; existing photo treatments retain their defaults. Guarded
  content/media artifacts preserve all existing projects. Conflicting older
  prices, fund allocation, partner fees and award artwork are recorded for
  review; no checkout, form, merchant subsystem or calendar event was created.
  Fixed HTML entity preservation in the shared editor's initial document data,
  preventing valid partner text from producing a save-validation warning.

- Added the reusable project Bühne presentation with document-owned logo and
  curated palettes, a shared composed opening, bounded section surfaces,
  editorial columns, filterable agenda rows and native expandable chapters.
  Futura's richer review draft follows the supplied mockup inside the ISS shell;
  it preserves both published Futura versions and all other published projects.
  Media and content have paired guarded migration artifacts. The illustration is
  explicitly labelled as AI-generated; programme copy remains provisional and
  the existing quiz is linked externally. No automatic event creation or staging
  changes. Shared registry, storage, preview, revision and renderer checks pass;
  browser review covers desktop, phone layout, filters, keyboard disclosures and
  editor surface autosave.

- Simplified Führungen to explicit Öffentlich/Gruppe selections, including both,
  and retained Sonderführung as an optional existing card label. Removed the
  keyword/date classification fallback and distinguished intentionally excluded
  pages from missing assignments in the drift check. Contextual landing actions
  now lead to public dates or the shared group inquiry dialog; availability and
  the family rallye material action remain visible. A guarded slug-based owner
  migration classifies 13 active offers, aligns booking settings, removes the
  old classification suffix from the Helden title and updates the landing intro.
  The duplicate Licht page and historical Rathenau page retain their content
  and URLs with explicit empty landing selections. No new taxonomy, schema,
  frontend script or media artifact. Verified 47 backend/editor regressions,
  PHP lint/PHPCS/PHPStan, migration check/apply/verify and idempotence, rejection
  of later category/copy changes before writes, 16 HTTP 200 routes, tour drift,
  Chrome desktop/390px layout, keyboard filtering and inquiry opening. Signed-in
  Chrome also verifies native editor save/reload for both, group-only and empty
  selections, retaining the optional Sonderführung label; the disposable draft
  was removed. Local before-image:
  `var/fuehrungen-20261015/before.json` (0600); no staging changes.

## 2026-10-12

- Reworked `/schoneweide/` into five sections including the photographic opening:
  one overview with three selected Place cards, a complete six-phase history,
  a compact exploration list and the existing Atlas. Removed repeated curatorial
  explanations and published planning notes; the historical additions use the
  sources recorded in the migration payload. Editorial body copy falls from
  539 to 258 words; Chrome at 390px measures about 7,900px total height against
  the 14,231px baseline. Rewrote the existing Atlas-card/chronology treatments,
  removed fixed card heights/overlays and chronology layout rules from the skin,
  and consolidated map-stage width in its primitive. Native section links and
  the existing fixed-header scroll-target utility keep the composition editable.
  Passed 31 read-only composition/editor/map checks, targeted PHP/CSS checks,
  migration check/apply/verify and repeat-apply; negative checks reject later
  editorial changes or missing media before writing. Chrome desktop/900px/390px
  review covered hierarchy, images, overflow and Atlas navigation. Authenticated
  editor save/reload and cross-engine UAT remain. Local content was applied through
  `2026-10-12-schoneweide-landing.php` with its JSON payload; the verified private
  before-image is `var/schoneweide-20261012/before.json` (0600). Deployment needs
  this guarded content migration alongside the theme/contract changes; existing
  media are reused, so no uploads artifact is required. No staging change.

- Schlüsselorte cards now resolve their existing dossier destinations to
  published Place records and show the corresponding calibrated map position.
  The shared map renderer/runtime supplies compact passive previews, initialized
  only when visible; the card remains the single link. Removed the repeated CSS
  map crop/fixed dot and consolidated marker styling into its existing owner.
  CSS shrinks overall, with no new `!important`, page overrides, dependencies,
  tiles, or content/schema changes. Passed 75 read-only card/location checks,
  static-map contract/audit commands, PHP lint/PHPCS/PHPStan and CSS/JS lint.
  Chrome desktop, 900px and 390px checks covered centred markers, resize,
  overflow, lazy initialization, strip navigation and existing map zoom.
  Editor document validation/rendering passes; authenticated editor save/reload
  and cross-engine UAT remain. Existing data/media only; no DB or uploads
  migration artifact is required.

## 2026-10-11

- Made the Repair-Café landing destination an occurrence-owner rule shared by
  mapping, SuperSaaS sync and public reads, so stale imported metadata cannot
  replace it for current or new dates. Programme renderers now share one image
  helper; the theme supplies Repair-Café's effective landing-template hero,
  including Site Editor overrides. The former direct-thumbnail paths could not
  use that hero without copying media metadata. Existing images remain the
  fallback. Passed 96 read-only destination/hero/overview/preview/booking checks,
  PHP lint/PHPStan and desktop/390px browser review. PHPCS passes the six other
  changed runtime files; sync admin retains the same 8 errors/21 warnings as
  the `3ef84f7` baseline, outside the changed lines. No live SuperSaaS sync, content/media copy,
  DB migration or uploads artifact was needed or performed.

- Programme titles, images and detail actions now honor the occurrence owner's
  existing content destination through the shared card URL helper. Repair-Café
  therefore links to `/repair-cafe/` across the homepage, calendar and programme
  cards for every occurrence; its series metadata/import already supplies that
  destination. Booking destinations retain their separate owner. Passed 80
  read-only overview/preview/booking checks, PHP lint/PHPCS/PHPStan and a browser
  link check. No content, DB or uploads artifact is needed for this renderer fix.

- Added an automatic feature beside the four homepage programme dates. The
  existing program-cards block reuses programme selection, expiry and optional
  expiring editorial focus; a current exhibition fills quiet periods. The
  featured source is excluded from the adjacent list. Existing feature-card
  markup handles entries with or without images; the layout stacks on phones
  and collapses when no feature is available. Both editorial and fallback
  homepage paths use the same presentation, exposed in the block editor.
  Homepage cache headers now keep date rotation current. Passed 122 read-only
  preview/rotation/overview/booking checks, targeted PHP/JS/CSS checks, editor
  control smoke checks and template roundtrips. Chrome desktop/phone review
  covered the feature, full-calendar link and booking request-form handoff
  without submission. Real editor save/reload and cross-engine rerun remain.
  Existing editorial data/media are unchanged; no DB/uploads artifact is needed.

- Aligned the homepage programme preview with the calendar through native
  Programme / Compact programme styles on the existing timeline-query block.
  The active editorial `front-timeline` slot and Gutenberg fallback both keep
  four entries under Demnächst, shorter summaries, Details links and primary
  date selection; obsolete split-column CSS was removed. Running exhibitions
  show their closing date with Läuft bis, using the occurrence query clock.
  Shared row presentation no longer depends on displaying calendar controls.
  Passed 78 read-only calendar/filter/booking checks, targeted PHP lint,
  PHPCS/PHPStan and CSS lint; template parse roundtrips and native style
  registration pass. Chrome desktop/390px review covered both pages, the
  homepage-to-calendar link, filters/month navigation and booking request-form
  handoff without submission. Real editor save/reload and cross-engine rerun
  remain. Code and existing media only; no DB/uploads artifact is required.

- Applied the supplied calendar mockup locally: a quiet filter panel, month/day
  result heading, large semantic dates, existing featured images, inline details
  and prominent booking actions. Programme days have occurrence dots and only
  the required week rows; direct date fields remain in native expandable controls.
  Replaced page-owned boxed rows and unused intro selectors with the shared
  timeline renderer/skin. Existing occurrence queries, booking intake and editor
  block settings remain the owners. Template stays theme-backed; no DB migration
  or uploads artifact is needed. Verified 70 read-only backend checks, PHP
  lint/PHPCS/PHPStan, CSS/JS checks and parsed-template roundtrip. Live Chrome
  desktop/phone checks cover filters, empty day, list/month navigation and booking
  date/time-to-request transition. Existing browser tests were adapted to expand
  date controls; the cross-engine suite and editor save/reload were not rerun.

- Compared the live staging booking chooser read-only and restored its calendar
  and time-button layout locally, followed by the existing request form. Inline
  and programme grids share the same compact date tiles. Calendar filters now
  precede the grid, use smaller buttons and apply content-type changes through
  the existing core router; native GET submission remains available. Drained
  duplicate page controls and obsolete pattern calendar surfaces into the existing
  renderer/skins. Availability ownership, intake and saved block attributes are
  unchanged; no database migration or uploads artifact is required. Passed 70
  backend checks, 60 Chrome/Firefox/Linux-WebKit scenarios and targeted PHP/JS/CSS
  checks, with desktop/phone review of the original local site and inline fixture.

- Matched programme, inline-tour and booking calendars to the former Flatpickr
  visual treatment using one shared date-tile skin: compact rounded dates,
  circular month arrows, red markers/selection and desktop calendar/details
  panels. `/kalender/` now opens with the picker beside the existing paginated
  programme results; selecting a day filters those results. Removed the separate
  large event-cell grid and its CSS. Shared style enqueue does not load booking
  behavior for a calendar-only block. Native WordPress navigation, booking
  contracts and no-JavaScript fallbacks remain. Passed 70 backend checks, 60
  Chrome/Firefox/Linux-WebKit scenarios and targeted PHP/JS/CSS checks; desktop
  and phone visual review confirmed matching tile styles without overflow.
  Warm local full renders: 116 SQL queries for calendar plus results, 113 for
  the plain list. Code/assets only; no saved-template, DB or uploads artifact.

- Added the requested availability month picker to inline tour calendars and the
  shared booking dialog, plus a Liste/Kalender month view on the programme page.
  The existing occurrence service supplies dates; the existing WordPress
  Interactivity module and core router handle selection/navigation. Available,
  sold-out, cancelled and empty dates, keyboard navigation, leap days and Berlin
  date semantics are covered. Programme month/day links work without scripting;
  inline booking retains its server-rendered date/contact fallback. No new
  calendar dependency, endpoint, schema, saved attributes or uploads artifact.
  Passed 155 backend checks and final 36 Chrome/Firefox/Linux-WebKit booking,
  month-view and commerce scenarios, plus PHP/JS/CSS checks. The broader 60-case
  run had one WebKit page-close failure; that existing router case passed three
  consecutive reruns. Warm local full renders measured 113 SQL queries for the
  list and 79 for the month view; booking date changes add no availability read.
  The five changed frontend assets add 2,728 computed gzip bytes versus the prior
  commit. Staff/staging and macOS Safari acceptance remain separate.

- Completed the local maintenance phase: existing SuperSaaS/fallback/Set jobs
  record checked success/failure receipts; Operations and CLI report stale jobs,
  missing callbacks, dead/stalled graph rows and diagnostic read failures. Set
  decay is bounded to 500 status updates without deleting evidence. Graph CLI
  failures now exit nonzero. Removed unconsumed SuperSaaS tag-cache helpers.
- Prepared one locked host scheduler path and a verified seven-artifact backup
  replacement, including private intake and installed runtime packages. Managed
  retention keeps 14 daily/four weekly points while preserving manual/partial
  backups. Added an allowlisted inactive-owner cron migration with a private
  before-image. Staging inventory, paired FPM/Nginx limits, activation, recovery
  and update cadence are documented; no target scheduler/configuration changed.
  No content/media transfer is required. Tests use isolated rows and fake host
  commands; no real notification, remote sync or production queue was run.

- Replaced the project/team ordering controller's jQuery Sortable/AJAX dependency
  with native drag events, keyboard arrows, touch up/down controls and fetch.
  Existing nonce/action/array payload and server save owner are unchanged.
  Pending saves suppress overlap; server/network failures restore row order;
  focus, zero-valued order labels and Quick Edit replacements are retained.
  Removed obsolete sortable helper/placeholder CSS. Twelve Chrome/Firefox/
  Linux-WebKit checks pass, including actual pointer drag and phone controls;
  JS/CSS lint and PHP/PHPCS/PHPStan pass. No content/schema/uploads artifact.

- Split graph schema/queue/read/identifier/evidence/signal methods and CLI
  diagnostics/migrations within the existing owner. Extracted relation block
  registration/query helpers and programme booking/filter configuration without
  changing public APIs, hooks or saved block attributes. Graph core is 1,233
  lines, CLI entry 81, relations blocks 1,927 and timeline renderer 778.
  Shared signal policy remains public/REST; audited graph/relations admin and
  CLI implementations load only in their contexts. Updated facade consumer
  checks for the native Interactivity booking config and GET timeline.
  Passed 1,821 PHP checks, facade checks/comparisons, PHP/PHPCS/PHPStan and 51
  Chrome/Firefox/Linux-WebKit scenarios. No schema/content/uploads artifact.

- Moved capability migration and newsletter defaults out of public bootstrap;
  privileged setup verifies persisted roles/options before advancing versions.
  Legacy plugin aliases still load their owners early but persist only during
  controlled setup. Relation backfill flags survive failed work and AJAX cannot
  trigger those backfills. The newsletter early-translation notice is resolved.
  Passed 16 isolated gate/failure checks and owner migration check/apply/verify
  in the WordPress 7.0 fixture. Deploy `2026-10-11-owner-setup.php` with a private
  target before-image; no uploads/content transfer or remote notifications.

- Coalesced ordinary content/relation/search save and metadata callbacks through
  the existing graph queue, with a bounded request-scoped shutdown drain and
  durable retry after lock contention or checked write failure. Explicit atomic
  writers retain immediate semantics; occurrence sync stays in its owner.
  Removed duplicate bridge reconciliation. Passed 31 isolated coalescing checks,
  87 queue/race checks and 427 atomic writer regressions; PHP/PHPCS/PHPStan pass.
  No new queue/service, schema migration, content or uploads artifact.

- Occurrence pages now check current source publication/password/type before
  pagination and batch source/location post caches. Removed obsolete timeline
  HTML transients and their version writes; retained REST responses are fresh
  and `no-store`. Passed 16 isolated privacy/cache checks and 99 programme/filter/
  rotation checks, including Berlin DST midnight; PHP/PHPCS/PHPStan pass.
  EXPLAIN uses public_date and the post primary key; no index added. Menu query
  retained after proving occurrence ordering changes its curated selection.
  Code only; no schema/content/uploads artifact or projection rebuild.

- Replaced the public search route's capped candidate list and repeated metadata
  filter with bounded SQL-provider pages and complete totals. Shared match/count
  predicates preserve relevance while excluding current private/password and
  tour-only sources before pagination. Batched result caches and a SQL fallback
  retain native templates and the existing REST facade. Read queries now live in
  `iss-graph/includes/search-query.php`. Passed 35 isolated search checks, 661
  atomic-write regressions, three facade comparisons and six Chrome/Firefox/
  WebKit desktop/no-JavaScript scenarios. Four first-page orders match the
  baseline; local search renders use 79 queries (previously 81), with full totals
  rather than the old 1,000-match cap. EXPLAIN supports retaining existing
  indexes. PHP/PHPCS/PHPStan and JS checks pass. No schema/data/uploads artifact.

- Separated public place-term lookups from relation taxonomy creation/repair.
  Multi-place reads now batch post/term loading; existing save and backfill
  paths retain term maintenance. Nine guarded read checks and 92 content-editor
  atomicity checks pass, plus PHP/PHPCS/PHPStan. All seven sampled public routes
  now render with zero database writes; tour queries fell from 207 to 188 and
  publication queries from 102 to 98. Current rendered IDs/order are retained.
  Code only; no term backfill, data migration or uploads artifact required.

- Enabled core Interactivity router enhancement for scoped programme GET forms,
  presets/reset and pagination. The shared PHP renderer remains the only result
  renderer, including full-page failure fallback. Fixed the WordPress 7.0
  Back-during-navigation race through its existing navigation API. All 45 local
  Chrome/Firefox/Linux-WebKit scenarios pass, including independent instances,
  stale responses, history, booking after replacement and phone keyboard/axe;
  28 filter PHP checks and PHP/PHPCS/PHPStan/JS lint pass. Seven routes return
  HTTP 200 without browser errors. Local calendar initial scripts/styles fell
  from 123,836 to 103,804 gzip bytes; first navigation adds 11,826 script bytes
  and a 91,763-byte HTML response. Publication initial assets rose from 88,804
  to 106,170 bytes because the shared core module replaces its small standalone
  controller. No uniform speed claim; no schema/content/uploads artifact.

- Replaced the booking and publication controllers with one PHP-authored native
  dialog and WordPress Interactivity module. Native date/slot and preferred-date
  controls retain public contact/external navigation fallbacks, deduplicate reads,
  reject stale responses and preserve failed submissions. Removed Flatpickr,
  both retired controllers and their CSS; saved block attributes are unchanged.
  WordPress 7.0: 26 booking renderer checks, 97 filter/programme checks, 114 intake
  checks and all 27 browser scenarios passed across Chrome, Firefox and Linux
  WebKit (including phone/no-JavaScript navigation, retries and focus return).
  PHP/PHPCS/PHPStan and JS/CSS lint pass. Code/assets only; no migration/uploads.

- Tightened booking/order source visibility, current slot status/quantity and
  strict Berlin preferred-date validation. Persisted slot time/title now come
  from owners. Slot reads cover the complete existing 12-month source horizon;
  removed the silent 1000-row cap and REST tag-cache reuse/writes that could
  outlive visibility or mix source scopes. Passed 114 isolated commerce checks,
  including private sources, cancellation, DST and 1001-slot completeness, plus
  PHP lint/PHPCS/PHPStan. No schema/data or uploads artifact is required.
  The WordPress 7.0/PHP 8.3.31 fixture passed the same commerce checks, 26 native
  filter checks and three desktop/phone/no-JS Chrome checks. A scoped isolated
  full SQL restore retained all 118 tables; 117 checksums matched the live source,
  with only volatile options different after testing. Original data was untouched.

- Converted timeline-query filters to scoped native GET forms, date/month fields,
  preset links and bounded previous/next pagination using the existing PHP
  listing renderer. Editor attributes remain unchanged; public selections cannot
  widen fixed types, sources, taxonomy rules or hidden controls. Removed the
  old AJAX/grid controller, its enqueue/global and retired grid/bridge CSS.
  Flatpickr remains only for the booking consumer pending its next conversion.
  Passed 26 read-only filter checks, 71 programme/rotation checks and desktop,
  phone and JavaScript-disabled Chrome navigation/history checks; targeted PHP,
  PHPCS/PHPStan and CSS checks passed. Code/assets only; no data/uploads artifact.

- Made commerce retries idempotent in the existing request table: optional client
  keys with a unique constraint, intent fingerprints and serialized duplicate
  checks preserve existing responses while coalescing concurrent retries. New
  forms retain their key after failures and suppress pending submissions.
  Notifications/hooks run only after a new committed request, never on replay;
  this does not promise exactly-once email across a process crash. Passed 76
  backend, 16 migration and three Chrome browser checks plus lint/PHPCS/PHPStan.
  Applied `ops/migrations/2026-10-11-commerce-idempotency.php` locally after a
  verified scoped SQL backup and private target-local before-image; all three
  historical requests retained. Existing installations require that check/apply/
  verify artifact; public intake returns 503 while pending. No uploads artifact.

- Extended structural verification to occurrence, Set, commerce and all seven
  archive installers, retaining each owner's SQL as the sole declaration. Schema
  versions advance only after verified structure and persisted option writes.
  Checked existing occurrence legacy migrations and collection text-column
  upgrades; consolidated live checks into the existing ISS Core diagnostics.
  Commerce CLI verification is now read-only. Passed 194 isolated schema checks,
  PHP lint, PHPCS/PHPStan; all 11 live schemas passed read-only inspection. No
  definition/version change, real migration replay or uploads/data artifact.

- Removed graph, occurrence, commerce and archive schema work from public `init`;
  restricted automatic schema checks, including Sets, to privileged non-AJAX
  admin requests. Activation and explicit CLI owner paths remain. Added a small
  read-only checker and admin failure notice helper under ISS Core, the existing
  shared infrastructure owner; `dbDelta()` remains the installer and owner SQL
  remains the only declaration. Graph now verifies required columns/indexes and
  version persistence, and exposes drift through existing diagnostics. Passed
  40 disposable-schema checks, PHP lint, PHPCS/PHPStan and Kalender HTTP 200.
  No real data/schema mutation or migration/uploads artifact. Structural checks
  for the remaining installers follow; the helper must ship with its callers.

- Made failed Event Drop moderation recoverable when file movement encounters a
  filename collision. The existing resolver now finds deterministic item paths
  and checks known hashes; unrelated collision files remain untouched. Item
  updates accept the caller's supplemental audit within their existing atomic
  scope, so moderation state and both audits roll back together. File work stays
  outside that scope; retry finds the moved bytes. Passed 33 isolated file/DB
  recovery checks and 369 Set regressions, lint, PHPCS and PHPStan. Test files
  were confined to a private checkout fixture mount and removed. No data/uploads
  migration or real intake operation was required.

- Completed atomic item/context writes in the existing editorial Set owner,
  including moves, status batches, promotions, candidates and required audits/
  timestamps. Reused the owner transaction helper; added shared checked parent/
  item locks and item readback because existing read methods did not coordinate
  writers with deletion. Preserved unassigned intake, rights and normal rejection
  contracts; missing parents cannot gain new orphans. Candidate hooks follow the
  successful owner scope. Passed 890 checks across five suites, including real
  two-connection races, plus lint, PHPCS and PHPStan. No schema/data or uploads
  artifact; real rows retained. External import/file workflows remain separate.

- Made Set field/key changes atomic with their required audit, checking locked
  source rows, key conflicts and saved values. Preserved defaults, empty-title
  behavior, unchanged keys and rejected first-write results. Passed 169 isolated
  rollback/retry/caller-transaction checks, PHP lint, PHPCS and PHPStan. Real rows
  stayed unchanged; no schema/data migration or uploads artifact is needed.

- Committed the accumulated refactor checkpoint after a combined regression pass:
  3,134 checks across 21 suites, PHP lint, PHPCS and PHPStan. The user authorized
  autonomous continuation in verified local commits. The taxonomy seed migration
  is included; other checkpoint changes need no data or uploads artifact.
- Made editorial Set creation atomic with its required audit row. Extracted the
  verified deletion transaction mechanics into one private helper in the same
  service; no new graph/archive dependency or parallel writer. Key-generation
  reads are checked, and the existing audit writer returns insert success.
  Creation requires that result; other callers retain their current policy.
  Preserved positive IDs, duplicate/first-insert rejection as zero, normalization,
  actor attribution and audit payloads. Passed 133 creation checks plus all 181
  deletion regressions, lint, PHPCS and PHPStan. Temporary fixtures retained real
  Set/audit/options/queue rows. Retained a service before-image and reverified the
  four-table backup. No schema/data migration, uploads artifact, real Set changes,
  import/merge run or staging deployment. Broader caller workflows remain separate.
- Made editorial Set deletion atomic across items, context links and the Set,
  preserving audit history and existing promoted/archive-candidate protection.
  The owner method checks table engines/state, locks all relevant rows, verifies
  delete counts and preserves caller transactions with savepoints. Database
  failures now raise errors; missing/protected Sets retain their false result.
  No Set transaction helper existed; the boundary stays in this method without
  adding dependencies on graph/archive owners. Passed 181 isolated checks plus
  lint, PHPCS and PHPStan; real Set/audit/options/queue rows stayed unchanged.
  Retained a source before-image and verified four-table backup. No real deletion
  or merge run, schema/data migration, uploads artifact or staging deployment.
  Earlier merge moves and other Set writes remain separate transaction work.
- Made archive object saves and complete relation replacements atomic, including
  required object creation and paired source preparation before graph sync.
  Added one shared boundary in the existing archive owner: no archive helper
  existed, and the graph helper is private to its owner. Checked stored results,
  locked source posts, savepoints and transaction-aware projection caches retain
  complete old rows after failure and support retry/caller rollback. Failed
  metadata queries now evict WordPress's empty error cache before retry. Passed
  371 projection checks, 195 archive graph and 221 register regressions, plus
  lint, PHPCS and PHPStan. Isolated suites preserved real graph/archive/queue/
  options rows. Retained source before-images and reverified the scoped backup.
  No schema/data migration, uploads artifact, real repair/backfill, preservation
  storage changes or staging deployment. Browser/staging acceptance remains open.
- Made automatic archive-object graph synchronization atomic through the existing
  graph transaction helper, including title/identifiers/aliases, all projection
  relationship families, named targets and nested register-place/owner creation.
  Checked source and identity reads plus required write results prevent empty or
  partial graph replacement. Reused prepared archive snapshots across families;
  retained source labels, identity matching, privacy and no-op behavior. Existing
  archive projection preparation stays before graph writes, with explicit read/
  write failures and cache invalidation in its owners. Its own non-atomic relation
  replacement remains the next bounded step. Passed 195 archive, 221 register and
  114 content checks plus lint, PHPCS and PHPStan; isolated suites preserved real
  graph/archive projections, queue and options. Retained source before-images and
  verified scoped graph/archive projection backup. No schema/data migration,
  uploads artifact, real backfill, preservation-storage work or staging deployment.
- Made manual video transcript review batches atomic across person/organization
  promotions, canonical place metadata, review decisions and automatic evidence
  refresh through the existing transaction helper. Checked prerequisite reads,
  stored decisions and relation metadata; preserved permissions, sources and
  review precedence. The relation owner retains Metadata API writes, now verifies
  stored values and preserves backslashes. Restored deferred metadata hooks/cache
  state on success and failure; derived place taxonomy/graph and search run after
  standalone commit. Shared prepared transcript evidence writing keeps rendering
  outside standalone write locks. Passed 180 review checks, 128 transcript,
  92 content-editor and 114 content regressions, plus lint, PHPCS and PHPStan.
  Isolated fixtures retained real graph, taxonomy, relation/status metadata,
  queue and options. Retained three source before-images and a verified scoped
  graph/postmeta backup. No schema/data migration or uploads artifact is needed;
  browser UAT and staging acceptance remain outstanding.
- Made automatic video transcript evidence synchronization atomic with required
  video graph creation through the existing graph transaction helper. Checked
  source/status, candidate, review and evidence-URL reads prevent failed queries
  from becoming empty evidence. Standalone calls render blocks before opening
  the graph transaction. Preserved matching/order/caps, visibility, status
  normalization, source labels, reviewed-target exclusions and save priority.
  Passed 128 transcript checks, 114 content regressions and 105 evidence
  regressions, plus PHP lint, PHPCS and PHPStan. Isolated suites retained real
  graph/transcript-status/queue/options rows. Kalender and a published video
  returned HTTP 200 with no web PHP errors. Retained a source before-image and
  reverified the scoped graph backup. No schema/data migration or uploads
  artifact is needed; manual review writes and staging remain separate.
- Hardened profile facts saves and the existing shared facts writer. Entity and
  facts reads, insert/update results and saved values are checked inside the
  existing transaction helper; failures roll back instead of returning an old
  row as success. Valid zero-row updates, sanitation, creation timestamps and
  clearing semantics remain supported. Profile saves lock the stored binding;
  stale metadata cannot redirect facts. Search follows successful standalone
  commit. Passed 234 facts checks, 155 binding regressions and 102 alias
  regressions, plus PHP lint, PHPCS and PHPStan. Temporary fixtures preserved real
  graph/facts/binding/queue/options rows. Retained source before-images and a
  verified facts-table backup. No schema/data migration or uploads artifact is
  needed; staging untouched.
- Made manual profile names and generated aliases atomic through the existing
  profile transaction helper. Checked source, binding and entity reads use the
  binding save's lock order; cached metadata cannot redirect the name save.
  Search follows a successful standalone commit and refreshes binding metadata.
  Preserved permissions, nonces, source labels, ordering, empty-clearing and
  privacy. Passed 102 isolated alias checks, 155 binding regressions and 68 name
  regressions, plus PHP lint, PHPCS and PHPStan. Real graph/binding/queue/options
  rows were unchanged and temporary fixtures removed. Retained a source
  before-image and reverified the scoped graph/postmeta backup. No schema/data
  migration or uploads artifact is needed; staging untouched.
- Made profile binding saves atomic across current/displaced binding metadata,
  previous/new entities, profile identifiers and generated aliases. Added a
  profile-specific entry to the existing transaction helper because the graph-only
  boundary cannot include WordPress metadata. Writes still use the Metadata API;
  checked reads, locked rows and stored-result verification prevent partial moves.
  Detachment removes stale profile identifiers and verifies ownership before
  clearing a link. Editor requests enter the transaction before changing metadata.
  Deferred the existing metadata search callback, cleaned metadata caches on
  success/failure and refreshed register cache/search after standalone commit.
  Existing primary/identifier keys bound entity lookup to four candidates instead
  of an estimated 3,649-row scan; no index or schema change. Passed 155 binding,
  221 register and 92 content editor checks, plus PHP lint, PHPCS and PHPStan.
  Isolated suites preserved real graph/binding/queue/options data. Source
  before-images and a verified graph/postmeta dump are retained locally.
  Kalender and a published profile returned HTTP 200 with no web PHP errors.
  No schema/data migration or uploads artifact is needed; staging untouched.
- Made manual public-content person/organization relation saves atomic through
  the existing graph transaction and combined projection APIs. Target/source
  reads and required source/venue graph creation share the boundary; database
  read failures cannot silently discard selected targets. Preserved nonces,
  permissions, profile-only targets, privacy, source labels, empty-clearing and
  save priority. Derived read-model/search updates run afterward and retain their
  own failure boundary. Passed 92 isolated editor-save checks and 114 automatic
  content regressions, plus PHP lint, PHPCS, PHPStan and whitespace checks.
  Real graph, queue, terms, postmeta and options remained unchanged during the
  editor suite; fixtures were removed. Veranstaltungen and Kalender returned
  HTTP 200. Retained a source before-image and reverified the existing scoped
  graph backup. No schema/data migration, uploads or staging deployment.
- Made automatic public-content graph synchronization atomic through the existing
  graph transaction helper. Entity/title, identifiers, aliases and native relation
  families now include nested venue/owner graph writes in one boundary. Failed
  source/venue reads and entity writes raise errors instead of committing partial
  projections or clearing a venue. Retained source labels, visibility, supported
  post types and atomic trash/auto-draft deletion; source posts/meta and subsequent
  search writes remain separate. Passed 114 isolated content checks, 221 register
  regressions and 87 queue regressions, with real data unchanged during the suites.
  PHP lint, PHPCS, PHPStan and whitespace checks passed; Veranstaltungen and
  Kalender returned HTTP 200. Verified source before-image and scoped graph dump
  retained locally. No schema/data migration, uploads or staging deployment.
  Manual content editor relation groups remain the next bounded step.
- Hardened graph queue processing within the existing queue/lease owner. Select
  after locking; use the existing transaction helper for short ownership-checked
  claims, acknowledgements and run-status writes. Reconcile outside transactions;
  stop on lease loss. Atomic dirty upserts preserve same-second edits and reset
  their retry budget. Recover abandoned processing within the batch limit and
  retire exhausted work without another attempt. No schema or scheduler change.
  Passed 87 processing checks and all 57 lock regressions, plus PHP lint,
  PHPCS/PHPStan and whitespace checks; Kalender returned HTTP 200. Both isolated
  suites preserved real queue/options and removed their fixtures. Source
  before-images and a verified scoped queue dump are retained locally. No real
  queue drain, data migration, uploads artifact or staging deployment was needed.
- Made graph queue lock acquisition atomic in its existing option: unique-name
  insertion, exact-value stale takeover and a per-lease ownership token. Release
  checks the drain's saved lease, so an old worker cannot remove a replacement.
  Bypassed cached reads for arbitration, invalidated option caches after writes
  and surfaced SQL failures. Passed 57 isolated checks using two DB connections,
  including forced competing acquisitions, stale takeover, old-owner release,
  legacy/cache behavior, SQL failures and drain cleanup. The suite leaves the real
  queue and lock/run options unchanged; a later real post-save entry was preserved.
  Fixture tables were removed. PHP lint, PHPCS, PHPStan
  and whitespace checks passed; local Kalender returned HTTP 200. Queue claiming,
  processing recovery and retry behavior remain the next bounded step. Local
  only; no schema/data migration or uploads artifact is needed.

## 2026-10-08

- Made register automatic graph sync and editor graph saves atomic together,
  including place/target entities, identifiers, roles and aliases. Added a narrow
  bridge entry to the existing transaction helper because the single-source API
  cannot encompass target creation or multiple source owners. The editor's three
  groups use that API inside the outer transaction. Checked entity writes and
  prerequisite reads; derive aliases after saving manual names. Retained source
  labels, imported-role exclusions, target privacy, permissions and nonces.
  Passed 221 isolated register-graph checks, 683 graph/register/privacy regression
  checks and PHP lint/PHPCS/PHPStan. Seven pages match; projection tables are
  unchanged. Concurrent editor revisions, locks/session and queue activity were
  preserved. No schema/data migration or uploads artifact is needed.
- Made the combined graph projection API atomic across supplied names,
  identifiers, evidence and relation families. Moved its existing dispatch into
  the graph service and reused the transaction helper; component savepoints keep
  one outer commit. Preserved partial payloads, aliases, family order and empty
  clearing. Passed 205 projection checks and 457 component/register checks, plus
  PHP lint/PHPCS/PHPStan. All 117 non-options tables remain unchanged; seven page
  responses match after normalizing rotating REST nonces and email entities.
  Bridge workflows still require separate integration. No migration/uploads needed.
- Made graph evidence replacement atomic through the existing transaction helper.
  Failed upsert reads/writes and foreign entity IDs now abort the batch, restoring
  deleted rows and preceding same-entity ID updates. Preserved generated IDs,
  default/duplicate rows and zero-row updates. Passed 105 isolated evidence checks,
  30 register checks and PHP lint/PHPCS/PHPStan. Real graph data and seven sampled
  pages are unchanged; the existing booking cron refreshed its own tables.
  No schema/data migration or uploads artifact is needed.
- Made graph identifier replacement atomic through the existing transaction
  helper and checked identifier upsert. Invalid rows, cross-entity conflicts and
  failed reads/writes now roll back the batch, including preceding source changes.
  Retained same-entity source reassignment, last-duplicate behavior and successful
  zero-row updates. Passed 105 isolated identifier checks, 30 register checks and
  PHP lint/PHPCS/PHPStan. Graph rows and seven sampled pages are unchanged; the
  existing booking cron refreshed its own tables. No migration/uploads needed.
- Made graph name replacement atomic for one entity/source using the existing
  transaction helper. Prepare names before deleting, check every write, and
  preserve original names on failure. Kept other sources, historical dates,
  normalization, ordering and empty-clearing behavior. Passed 68 isolated name
  checks, 30 register-sync checks and PHP lint/PHPCS/PHPStan. Real graph data and
  seven sampled pages are unchanged. No schema/data migration or uploads needed.
- Made the existing nine graph entity-deletion writes across eight tables atomic.
  Extracted the preceding relationship transaction handling into one private
  graph-service helper, retaining savepoint ownership and checking every table's
  engine before mutation. No second transaction layer or expanded deletion scope.
  Passed 161 isolated deletion checks, all 149 relationship checks, 30 register
  checks and PHP lint/PHPCS/PHPStan. Real graph rows and seven sampled pages remain
  unchanged. No real entity was deleted; no migration/uploads artifact is needed.
- Made graph relationship replacement atomic for one entity/family/source.
  Normalize rows before writing, require MariaDB/InnoDB, check delete/insert and
  transaction results, and roll back on failure. Savepoints preserve caller-owned
  transactions; exceptions reach the existing queue retry handler. All 149 new
  isolated relation checks, 30 register-sync checks and 23 programme checks pass,
  alongside PHP lint, PHPCS and PHPStan. Graph checksums and seven sampled pages
  are unchanged. Local only; no schema, data migration or uploads artifact needed.
- Version-gated the existing content, fallback-category and archive taxonomy
  seed routines. Registration stays on `init`; seeding runs through activation,
  privileged admin upgrades or the scoped check/apply/verify migration. Failed
  setup leaves the version pending and visible in existing diagnostics.
  All 91 transactional seed checks and 23 programme checks passed. Local
  calendar queries fell from 340 to 136, with unchanged sampled page content
  landmarks, links, assets and preserved term IDs. Applied locally only.
- Corrected local WordPress development mounts to serve the website checkout's
  plugins, theme and receiver, retaining existing data mounts and the same web
  image. Kept the override, WP-CLI wrapper, verified database/files backup and
  seven-route server/asset baseline inside the WordPress workspace. All 23
  programme checks passed; table schemas and preserved content were unchanged.
  Recorded normal options/startup-marker changes and remaining browser/staging
  acceptance. Later refactoring slices require separate user confirmations.

## 2026-10-07

- Prepared a staging-informed WordPress refactoring plan and extended the existing
  universal calendar/intake plan for native controls and WordPress Interactivity
  API. Sequenced query reduction, atomic writes, frontend dependency removal,
  code/CSS consolidation and routine maintenance with verification/rollback
  gates. This is planning only; no runtime, database or deployment changes.
- Saved the next-session handoff and first executable follow-up. Corrected the
  stale local mount claim using current Docker evidence; prior detailed release
  receipts remain recoverable from the preceding handoff in Git history.

## 2026-09-27

- Reworked event details around an illustrated opening, compact occurrence-backed
  dates and shared stacked Rückblick cards. Removed obsolete event layout CSS.
  Added optional text-only guest feedback through the existing signed upload
  link and native WordPress moderation; media remains in private Sets. This
  reuses the existing receiver, open control and review UI without another
  storage/editor system. Public voices require approval and consent. Deployed to
  staging through GitHub with the scoped Anne Rabe opening migration and verified
  before/after backups. All 120 event/programme checks, media integrity and
  signed-receiver HTTP checks passed; no new media or schema was needed.
- Adapted the event layout from the approved reference: optional series/headline/
  subtitle fields in the shared document, uncropped posters or landscape photos,
  a real visit action and venue-backed address panel, and distinct upcoming
  programme cards. Extended the existing metadata/programme blocks and shared
  ticket helper; past events lead with reports/voices and suppress expired ticket
  links. No new block or editor. Empty-field autosave/recovery now persists.

- Deployed the Atlas update, Reinbeckhallen source mapping and private-contact
  separation to staging through GitHub, with scoped API migrations and verified
  backups. All 51 runtime checks and anonymous privacy/noindex checks passed.

- Added a bounded, source-pinned Atlas current-fact import: 23 field changes
  across ten existing Places and six sourced modern milestones in the existing
  chronology. Historical content and local-only Places remain locally owned.
  Exact/unknown event dates are distinct from reporting/import dates; repeat
  imports cannot duplicate events. Retired four stale automatic owner links
  without changing editorial or historical graph relationships. JSON exchange,
  paired migration artifacts and transactional preservation checks are documented.

- Mapped upstream Gründerzentrum #70 into the existing Reinbeckhallen #13 Place.
  Added its development statement and sourced milestone, preserving the site's
  active cultural status. Future imports share a component-aware identity
  resolver and reject duplicate Places or whole-site changes from component data.

- Separated seven embedded Place contacts into staff-only name/email/phone/role/
  source fields in the existing editor. Removed research notes from public REST,
  redacted contacts from the shareable supplier snapshot, and added a public
  import contact gate. Private migration evidence stays outside Git/web access;
  anonymous route checks cover the public boundary.

## 2026-09-22

- Added a bounded production-to-staging programme import for four events and
  four exhibitions, with paired attachment/file evidence and duplicate checks.
  The discovery game stays with its Walk of Fame installation. Veranstaltungen
  now includes exhibitions through the existing timeline block; the existing
  Waldfriedhof tour receives its missing SuperSaaS series mapping. Event material
  sections now render their existing link field through the shared theme helper,
  preserving imported ticket and film links; renderer regression checks cover it.

- Restored the About hero's pre-July-11 triptych composition: inset heading,
  taller images, legible kicker captions and stacked phone layout. The template
  places the existing JSON image row inside one hero using bounded slots in the
  same renderer. No second editor, duplicated content or DB/media changes.

- Restored missing attachment records behind Führungen card thumbnails and two
  other editor images. Added a read-only media dependency check across all
  canonical documents, including unchanged JSON, plus a guarded staging repair
  and paired file manifest. Existing card CSS and markup are unchanged.

- Synchronized the accepted website code and scoped editor/content data to
  staging: 52 documents, required templates/media and report source connection.
  Verified backups, migration read-back, media hashes, preserved private/domain
  data and public desktop/mobile output. Production is unchanged.

- Preserved staging's CARTO key support in the shared code, keeping the key
  outside Git. Added the guarded editor/content sync migration and required
  media checksum manifest; private drafts and unrelated domain data stay local
  to their environments.

- Restored the homepage hero's original staging appearance while retaining JSON
  ownership. Reused native Cover/Buttons and the existing theme hero pattern,
  removed the duplicate opening design and ordinary-section padding conflict,
  and preserved optional introductory content beneath the image.

- Consolidated JSON authoring into one workspace and effective format registry,
  extended previews to nine formats, and removed the alternate cards/modal
  editor. Added explicit starters and repeatable registry/renderer checks.
- Split landing renderer CSS from skins, restricted legacy front-page styles to
  fallback output, and removed anchor-dependent presentation. Preserved stored
  documents and native domain controls. Video articles use the shared renderer;
  excerpts no longer render full compositions. See the consolidation audit.

## 2026-07-19

- Redesigned the `register_place` dossier around an image-led hero, compact
  public identity band, epoch rail with archival media and source disclosure,
  present-day data panel, contribution intake, related rails, and bottom Atlas.
  Added construction/original-name/monument fields and a replayable Kino
  Spreehöfe migration plus paired historical-media uploads artifact; removed
  the redundant post-content history chapter because the curated epochs now
  carry that narrative.
- Added the accepted repo-owned Place editorial and interactive Atlas
  restructuring plan. It moves ordinary Place ownership toward `iss-content`,
  uses the shared `iss-editorial` JSON engine and theme renderer, keeps
  query-critical facts outside narrative JSON, preserves epoch/state tables as
  derived projections during migration, moves semantic actors toward graph
  organizations, keeps ordered routes in `iss-relations`, and targets a compact
  `iss-frontend` Atlas projection with explicit payload/cache budgets. The plan
  also records complete TouchTable runtime retirement: live TouchTable and SQL
  backup remain the recovery source, with target inventory and backup required
  before obsolete snapshot rows are removed.
- Implemented the complete local Place JSON pilot for Kino Spreehöfe: moved
  `register_place` CPT registration to `iss-content`, added typed Place gestures
  to the shared editor, made enabled JSON authoritative for deterministic
  epoch/state projections, and added the theme-owned renderer with legacy
  fallback. Retired the obsolete TouchTable/feedback/story runtime and backed
  up then removed 184 local TouchTable snapshots. Reduced the 76-Place Atlas
  bootstrap from 245,413 to 58,535 bytes (9,526 bytes gzipped), added versioned
  caches, ETag/304 delivery, lazy Place detail, near-viewport initialization,
  and parity/payload CLI checks. Graph actor migration and final register-plugin
  removal remain later reviewed phases.

## 2026-07-12

- Added a paired migration and checksummed upload artifact for About landing
  image attachment `26059`, which was present locally but absent on staging.
  The migration restores the canonical attachment ID and metadata through
  WordPress APIs and verifies the extracted five-file WebP family.
- Changed the interactive Atlas to start without a selected place. Filtering
  and single-result search keep the map card-free while retaining map panning;
  a place card and active marker now appear only after explicit marker
  selection.
- Added a shared high-contrast treatment to the interactive Atlas tile pane in
  embedded, fullscreen, and kiosk modes. Increased contrast and saturation
  sharpen roads and buildings while leaving markers, overlays, controls,
  labels, and detail cards unfiltered.
- Extended the interactive Atlas from its fixed core pan boundary to a
  data-derived navigation boundary while preserving the existing Schöneweide
  core as the initial/reset composition. The REST Atlas payload is now the
  frontend inclusion authority, so all 76 valid payload places render;
  single-result searches automatically pan distant places into view and reset
  returns to the core map. Updated the scope label and singular result grammar.
- Consolidated static maps onto one calibrated Schöneweide coordinate space and
  canonical source image. Added deterministic WP-CLI marker generation from all
  83 published coordinate-bearing `register_place` records, calibration/source
  checksums, projection quality thresholds, a generated provenance manifest,
  and a visual QA overlay. Public maps now use generated 1024px (~120 KB) and
  2048px (~416 KB) WebP derivatives without duplicate requests; removed the
  superseded rotated, cropped, and duplicate static-map rasters and marker
  files. Desktop keeps the 2048px asset while mobile uses the 1024px derivative.
- Replaced the complete editorial static-map camera engine with progressively
  enhanced Leaflet image viewports across the Führungen landing, Schöneweide
  Topografie, Führung route maps, related-place maps, Atlas slices/strips, and
  spine strips. Existing image assets and marker projections remain
  authoritative; Leaflet now owns responsive marker-bound fitting, resize,
  constrained pan, and zoom interaction while the station-detail contract is
  preserved. The marker-fitted initial view is both minimum zoom and safe pan
  boundary, wheel zoom is deliberately restrained, and the no-JavaScript
  fallback is a plain full-map image. Removed the superseded crop/focus,
  rotation, projection, bias, scale, viewport-transform, editor-camera, and
  publication-specific CSS-variable workaround paths.

## 2026-07-11

- Created `/mnt/volume/ISS_ARCHIVE_READY` as an empty archive-ready filesystem
  skeleton with `00_MANIFESTS`, `01_UNITS`, `90_GAPS`, and
  `99_TRANSIENT_REFERENCE`, using `ISS_ARCHIVE_READY` as the archive container
  and unit folders such as `WF_TFA`, `WF_Kurt_Schwarz`, `WF_Sender`,
  `WF_Paragon`, `WF_Trafo_Bibliocopy`, `WF_Mediathek_Audio_AV`, and
  `ISS_History`.
- Remounted the external USB `Elements` source read-only and started a
  manifest-driven rsync intake from
  `/media/vladimir/Elements/restore/archive` into
  `/mnt/volume/archive-intake/elements-restore-md-candidates/`, selecting
  `64,299` MD-relevant candidate files (`3,681,284,532,528` bytes) that were not
  already present in `/mnt/volume/archive` by filename+size.
- Updated the local archive restoration reconciler under
  `/home/vladimir/Archive-restoration/` to use museum-digital's batch export API
  (`/export/json/ids:...`) and completed the full MD fetch pass: `6,746` records
  cached OK, `17` persistent 404/error IDs, and `1,625` local master-to-MD
  object chains restored.
- Migrated `/about/` from a hardcoded page body to nine registered landing JSON
  sections. Added the reusable non-link `text_bild_reihe` gesture, a data-driven
  Team directory slot, and staff drag-and-drop ordering for `team_member`; the
  cutover removes the legacy Query Loop, person-name crop condition, and more
  than 1,000 lines of page-specific body CSS.
- Migrated `/schoneweide/` to seven sections in the new `territorial` landing
  skin. Added reusable chronology and Atlas-place treatments, delegated the
  interactive Atlas through a constrained dynamic slot, established canonical
  page relations, restored the map/panorama/card composition as the reusable
  `map_img` gesture, and removed the 383-line legacy page stylesheet.
- Migrated `/fuehrungen/` from its static Query Loop and standalone offer
  catalog block to an eligible landing JSON document with a theme-owned
  `fuehrungen-offers` slot. The slot keeps tour query and booking data in
  `iss-content`, reuses the site-wide card/carousel contract, and removes the
  old block, private JavaScript, page pattern, and page-specific CSS layer.
- Reintroduced the Führungen “Formate” map as the JSON `atlas_map` treatment
  `editorial-split`. It uses the standard current-page relation source and
  shared static-map renderer, stores the three highlighted places as ordinary
  page relations, and keeps the 20/80 composition in the landing CSS while
  route-colored marker variables remain in the shared gesture layer.
- Migrated the Führungen “Bleiben Sie dran” pathways into the registered
  `gateway.pathways` landing treatment. The six image-led destinations now use
  the standard gateway JSON and renderer contract instead of the deleted
  pathway block markup and page-specific CSS.
- Added the canonical `galerie` gesture to native landing JSON and migrated the
  six Führungen images into a `sequence` gallery. It reuses the site-wide
  carousel runtime with stable image geometry and captions, without restoring
  dense-wall spans, overlays, or page-specific gallery CSS.
- Added treatment-scoped orientation labels to the Führungen landing map. Its
  shared route-colored markers now expose their existing place names below the
  dots in condensed red type, without changing the static-map renderer or
  adding opaque label panels.
- Replaced the Führungen landing SQL blob with a readable WP-CLI migration.
  Landing JSON now saves through `iss-editorial`, page relations save through
  `iss-relations`, and the migration creates and verifies a targeted backup
  instead of embedding normalized serialized postmeta.

## 2026-07-09

- Added ADR-001 for archive preservation architecture, defining the
  preservation archive as authority, public systems as projections, a
  standalone builder plus archivist workbench boundary, and `iss-archive` as
  the WordPress-side projection consumer rather than the master-file owner.
- Added a combined Schöneweide Atlas bootstrap REST payload so first load no
  longer fires separate cold `/atlas` and `/atlas-context` place-model builds,
  cached the context contract, and switched the relation mini-map bootstrap
  asset from the 6 MB PNG to the existing WebP map image.
- Added display WebP variants for heavy Schöneweide page media and switched the
  file-backed Schöneweide template plus static-map preset to the lighter assets.

## 2026-07-07

- Removed the legacy SuperSaaS-derived `inquiry_url` from the active Führung
  booking contract. Führung on-demand and hybrid inquiry CTAs now open the
  existing local `iss-payments/v1/request` inquiry modal, while saved Führung
  edits clear any old `inquiry_url` post meta.

## 2026-07-06

- Removed the legacy `iss/tour-route` public block from the active single
  Führung template and deleted its route-dossier renderer, carousel script,
  block registration, block files, and dedicated `.iss-tour-route` CSS. Route
  presentation for JSON-enabled Führungen now flows through the `atlas_map`
  gesture backed by `iss-relations`; a later vanilla WordPress fallback should
  be rebuilt explicitly from the stabilized JSON and relation contracts.
- Replaced the single Führung related-card tail with a required shared
  `iss/related-content` relation network rail, aligned Veranstaltung to the same
  wrapper contract, and drained retired Führung/event relation CSS into the
  shared `patterns.css` system.
- Added `upload_intake` to the Führung JSON gesture palette. The theme now
  renders it as a moderated photo-sharing CTA to Event Drop with a
  `fuehrung__{slug}` context, and shared upload-intake styling moved into
  `gestures.css` with project/event CSS reduced to local variables.
- Polished editor-facing gesture labels and descriptions for `galerie`,
  `material`, and `upload_intake` so gallery, downloads, and public guest
  upload intake have clearer boundaries in the JSON editor.
- Replaced heuristic-only Führungen landing filters with controlled
  `offer_catalog_groups` post meta in the Führung side panel. The offer catalog
  now uses explicit categories first and falls back to the old booking/text
  heuristic only for unclassified posts; the free `fuehrung_typ` taxonomy is no
  longer exposed as an editor classification surface.

## 2026-07-04

- Moved the Führung `Route / Stationen` constructor out of the generic
  `iss-editorial` admin bundle into an `iss-relations` route-station editor
  module. The integrated canvas now mounts the relation-owned editor without
  hidden canonical route fields, and route edits flow through the draft route
  endpoint instead of the legacy `/places` write path. Route station payloads
  now let valid `role=stop` rows win over duplicate non-station rows for the
  same place, preventing a station from disappearing when editors change it to
  a place already stored as primary/related.
- Fixed Führung `atlas_map` rendering so JSON Atlas-Karte sections pass the
  owning Führung post context into the shared static-map renderer, removed the
  template fallback map shell during the JSON migration, and let the route-map
  marker-box ratio control the stage height without single-tour CSS overriding it.
  The JSON route-map section now expands to the 1720px content width and keeps
  the station panel beside the map on desktop with a map-derived scroll height.
- Connected Führung `atlas_map` route markers to the side panel so the
  `tour-route` variant progressively enhances from a fallback station list into
  a selected-station detail panel using the existing route title and teaser
  fields. The renderer now builds markers and panel content from one mapped
  station model, with legacy fallback suppression and private context attributes
  removed from the public block path.
- Restored route-station data deleted during route-constructor testing from the
  June 11 postmeta backup for `elektropolis-tour` and `familienrallye`, then
  refreshed the graph relation projection so the public route helper and Atlas
  map source consume the restored station rows. Rollback and restore SQL
  artifacts live under `ops/sql/2026-07-04-*route-station*.sql`.
- Added a locked route-draft workflow for Führung stations. Normal WordPress
  updates no longer carry integrated route fields; stale canonical `/places`
  writes for route-enabled posts are rejected. Editors must unlock a route,
  save sandboxed draft edits, can restore draft-deleted stations from a draft
  trash area, and explicitly publish-and-lock before canonical `iss_related_places`
  and the graph/map projection update. JSON/WP preview can opt into the draft
  route through signed preview args while public rendering stays on the locked
  route. Fixed the extracted editor state update path so place/object/story,
  title, and teaser edits write back into the draft row before save instead of
  being lost on rerender. Route publish now also ensures selected station source
  objects and Beiträge are indexed against the station place, and the constructor
  demotes those source fields into an optional collapsed area because the current
  public route presentation does not consume them yet.
- Removed the legacy Führung template presentation path for hero-gallery data:
  the old `iss/tour-hero-gallery` block, admin gallery picker, fallback gallery
  scripts, and template placeholder are gone. The Führung side meta box now
  keeps only facts, booking, and `tour_badge` as a card/kicker label; unused
  icon, color, featured, sort, and `hero_gallery_ids` meta registration/UI were
  removed so JSON composition is the single source for Hero, Medien, route, and
  gesture presentation.
- Tightened the Führung editor dashboard after the cleanup: identity now stacks
  excerpt and featured image beside a two-column Pflichtangaben box, while
  SuperSaaS linking and related-content promotion live together in a dedicated
  Termine/Sichtbarkeit section with clearer SuperSaaS copy.
- Unified occurrence-backed public booking entry points around the shared
  programme calendar modal: single Führung booking CTAs and timeline ticket
  buttons now open the source-level slot chooser, available slots open the
  shared request/payment form, sold-out compact date rows no longer trigger the
  form, and the temporary single-Führung `Alle Termine` calendar section was
  removed. The occurrence slot adapter now carries Veranstaltung price/copy
  metadata and treats `source_post_type` as the canonical slot-query kind.
- Added a structured Führung `booking_price_cents` field for the booking/payment
  contract while keeping `price_note` as editorial display copy. The Führung
  admin Pflichtangaben grid is more compact, the occurrence slot adapter
  projects the structured amount when present, and `iss-commerce-lite`
  recalculates Führung booking request amounts server-side from post meta.
- Tightened the Führung admin layout again: `Anfrage-URL` is no longer exposed
  or used for booking-mode decisions, `Buchungshinweis` is a single-line field,
  promotion sits with the identity/Pflichtangaben dashboard column, and the
  SuperSaaS mapping box stays in the native WordPress side column below
  `Führungstypen`.
- Simplified the single Führung hero text contract: JSON `bildbuehne` body owns
  the hero lede when present, the manual excerpt is the only fallback, both are
  capped server-side, and JSON-backed tours no longer let the description block
  fall back to Kapitel or generated post content. The route-dossier skin now
  shows that lede instead of hiding it through the old description-panel CSS,
  and the legacy Führung `intro` section stays schema-valid but hidden from the
  add-section palette because it is not part of the public hero contract.
- Tightened CSS boundaries after the Führung stage cleanup: stage-gallery
  thumbnails now use the shared gesture ratio again instead of a renamed legacy
  tour-gallery override, and the shared programme booking form no longer applies
  text-field sizing to radio controls inside the payment selector. The active
  Führung stage overrides now live in `fuehrungen-skin.css`, including the
  flow-layout margin reset that keeps desktop Bildbühne heroes within the
  viewport. The single Führung stage copy/booking rail now sit lower on desktop,
  and the hero kicker drops its own mini rail when it already sits inside the
  larger hero-anchor rail.
- Removed generated fallback labels from Führung JSON sections. Gesture type no
  longer creates visible pseudo-kickers such as `Tourprofil`; section kicker text
  is now purely an editorial field.
- Made JSON gesture palette clicks non-destructive in the editorial admin:
  clicking a gesture now opens an add-confirmation modal, while the section is
  written to the composition only after explicit confirmation or drag/drop into
  the structure. Accidental palette clicks no longer create empty sections or
  fill the draft trash.
- Tightened the shared `material` gesture contract: material now means files and
  links only, while images and archive objects belong to gallery/object gestures.
  The editorial media picker excludes images for material sections, stored media
  references preserve MIME type, Set promotion no longer routes archive objects
  into project material, and Führung/Projekt/Veranstaltung renderers ignore
  legacy image/object refs in material output.
- Collapsed the next JSON gesture vocabulary layer without adding a renderer:
  `image_wall` and `vollbild` are now legacy aliases for `galerie` treatments
  (`gallery_layout=wall` / `viewport`), while `massstab` is a legacy alias for
  `facts`. Führung, Ausstellung, and Projekt renderers map the canonical
  sections to existing layout skins, the project import path emits `facts`, and
  the architecture docs now describe the reduced vocabulary.
- Added an explicit agent/CSS-skill guardrail for future cleanup: touched legacy
  CSS must drain into the owning layer and avoid parallel selector systems.

## 2026-07-03

- Added the shared JSON gesture CSS layer and documented the public CSS
  layering direction for gesture contracts, renderer skins, and scoped
  overrides.
- Reworked single Führung public rendering around the `bildbuehne` gesture:
  hero text now sits over the image with a left-to-right readability gradient,
  the compact image gallery uses shared viewport-gallery JavaScript, gallery
  clicks update the defined hero viewport, and the booking rail is more compact.
- Added the shared `iss/atlas-map` gesture/block contract and registered
  variants for `place-locator`, `map-only`, and `tour-route`. Führung JSON now
  exposes `atlas_map` as the route-map gesture, while the template block remains
  a fallback that suppresses itself when JSON owns the section.
- Extended the static-map renderer with route lines, marker-edge crop fitting,
  and internal marker-box ratio mode so Führung route maps derive their crop and
  stage height from marker bounds plus registry padding instead of one-off
  Elektropolis CSS.
- Added landing `atlas_map` gesture support behind the existing JSON renderer
  path, with follow-up tracked for relation/source behavior before broad
  landing-page use.

## 2026-07-02

- Added the Intentional Vanilla Fallback Mode contract and v1 implementation:
  `iss-content` now coordinates native Category seeding, generated/fallback-native
  origin meta, read-model-to-vanilla serialization, idempotent projection,
  scheduled sweeps, WP-CLI/admin controls, and explicit enable/disable mode
  switching; `iss-publications` contributes its own publication projector; and
  `iss-core` exposes fallback status/backfill/capability integration. The local
  verification projected 89 generated draft objects, confirmed idempotency,
  exercised enable/disable, and restored normal mode with zero generated
  fallback posts published.
- Added repo docs for the fallback architecture, implementation SOW, and
  operator runbook under `docs/architecture/`, `docs/project/`, and
  `docs/runbooks/`. The no-parallel-systems decision is recorded there: native
  WordPress Categories with fixed `iss-...` slugs replace the earlier custom
  shadow-taxonomy idea because Categories survive with zero first-party plugins.
- Synced the current DB-saved `page-fuehrungen` block template back to
  `themes/industriesalon/templates/page-fuehrungen.html` and flushed the local
  `wp_template` override so the Führung landing page is file-backed again. The
  template copy removes the embedded upcoming-dates list section and keeps the
  Führung overview/card surface plus existing follow-on sections.

## 2026-07-01

- Refined the public single Führung renderer around a stable gesture contract:
  `intro` feeds the hero description, `kapitel` renders as readable tour
  narrative, `leitfrage` / `zitat` / media / material / `schluss` have explicit
  theme treatments, the first screen now uses a full-viewport route stage, and
  route, booking, facts, Atlas map, and related cards remain outside the JSON
  gesture layer.
- Added the `bildbuehne` Führung gesture as the explicit hero-stage contract:
  the template consumes its title, body, and media into the first viewport while
  keeping booking, dates, facts, route, Atlas, and related content outside the
  gesture body.
- Reworked the public `/kalender/` selection surface toward a crisp
  booking-style calendar: the existing `timeline-query` month/day bridge now
  progressively renders month buttons and a visible day grid while keeping the
  original select/input controls as the source of truth for REST refreshes.
  The theme calendar page now uses a two-panel booking layout, red accent
  scheme, compact result cards, and unchanged occurrence reads plus
  `/iss-payments/v1/request` booking writes.
- Reworked the grouped `Termin wählen` booking popup into the same booking
  selector language: opening the popup now preselects the first bookable day,
  shows the available slot panel immediately, and uses a wider two-column modal
  on desktop with a stacked mobile fallback. Slot buttons still hand off to the
  existing booking form and request endpoint.
- Added the CSS layering skeleton for the JSON migration: `tokens.css` now owns
  the layer-0 `--iss-*` token contract, `style.css` depends on it as the base
  layer, and `docs/architecture/css-layering-adr.md` records the token ->
  primitives/patterns -> renderer contract -> skin -> page-exception order.
  The WordPress CSS skill and engineering docs now direct future CSS work
  through that layer contract.
- Extended the CSS layering contract to first-party plugin styles: public
  plugin CSS consumes `--iss-*` tokens with fallbacks and plugin-prefixed local
  variables, while admin plugin CSS remains scoped to wp-admin and outside the
  public renderer stack. Updated the programme/timeline/dense-image-wall/tour
  public CSS headers and obvious base values to follow that contract.
- Made CSS changes migration-positive in the repo rules: touched legacy or
  page-specific stylesheets should drain reusable rules into tokens,
  primitives/patterns, renderer contracts, or skins, and delete obsolete
  selectors when safe.
- Added the inverse token rule to the CSS contract: layer-0 tokens are only for
  shared semantic values with likely cross-context consumers; one-off geometry,
  temporary migration values, implementation glue, and component-only internals
  stay local.
- Migrated the native landing `statement` gesture to the new renderer contract:
  the former generic/front-page statement styling now lives on
  `statement.lead`, the old `statement.callout` vocabulary was replaced by
  `statement.leitfrage` with a typografisch-style guiding-question treatment,
  and JSON-rendered statement sections no longer emit the legacy
  `iss-front-schoneweide-statement` class. Stored `statement.callout` values are
  mapped to `statement.leitfrage` for compatibility.
- Renamed the confusing landing `feature.microblocks` treatment to
  `feature.image-overlay` and updated editor labels to user-facing outcomes:
  `Bild mit Infokasten`, `Bild neben Text`, and `Titel auf Bild`. Existing
  stored `feature.microblocks` values are mapped to `feature.image-overlay`;
  facts remain facts and only the treatment changes their presentation.
- Added compact/balanced/wide ratio options to landing `feature.media-text`, so
  image-beside-text sections such as `/salon-vermietung/` “Der Ort” can migrate
  into the shared feature treatment instead of keeping rental-only 50/50 CSS.
- Added the JSON editor UX redesign contract in
  `docs/project/json-editor-ux-redesign-sow.md`, introduced
  `plugins/iss-editorial/assets/ui.js` for modal/panel primitives, and rebuilt
  section editing as a structured panel modal (`Inhalt`, `Darstellung`,
  `Archiv`, `Bilder`, `Album`, `Fakten`, `Ziele`, `Links`) with a footer trash
  action that reuses the existing deleted-section workflow. Existing JSON
  storage and public render ownership remain unchanged.
- Polished the redesigned JSON editor modal so tall section forms scroll inside
  the modal instead of the underlying WordPress page, while text and rich-text
  fields keep stable minimum editing heights inside panels.
- Added a separate `iss-editorial` drag/drop helper for the shared JSON canvas.
  Gesture cards can now be reordered by dragging the card body or compact
  handle, or by focusing the handle and using ArrowUp/ArrowDown, while palette
  gestures can be dragged into the composition to create a section at the drop
  position. The visible `Hoch` / `Runter` buttons were removed from section
  cards; JSON storage remains unchanged and `admin.js` still owns document
  mutation.
- Routed the native WordPress preview button through the same JSON preview
  autosave path as the custom canvas preview button, so unsaved canvas changes
  such as drag/drop reordering are saved to preview meta before the preview tab
  opens.
- Added signed `iss-editorial` preview URL parameters and taught the native
  landing renderer to prefer editorial autosave data when that preview signature
  is valid. This keeps JSON canvas preview behavior reliable even on native page
  templates where WordPress does not consistently expose the request as
  `is_preview()`.

## 2026-06-30

- Added JSON-editor safety guardrails for the shared `iss-editorial` canvas:
  section deletion now moves gestures into a persisted `deleted_sections`
  trash that can be restored after `Aktualisieren`, admins can purge deleted
  sections, and the canvas has a preview action that saves current JSON to the
  existing autosave meta before opening the WordPress preview URL. This reuses
  the existing editorial document storage instead of adding a parallel recovery
  system.
- Constrained landing JSON navigation links to native page selection in the
  editor. Landing section links and gateway-card targets now use a published
  Pages dropdown, store an optional `page_id`, and the theme resolves current
  permalinks at render time while preserving legacy URL values for existing
  documents and non-landing formats.
- Added the canonical `fliesstext` gesture to native landing JSON as the
  text-first alternative to `feature`: editors can add longer prose with
  optional page links without carrying Fakten/media fields or expanding the
  Feature modal.
- Added `feature.media-text` as the reusable 50/50 image-text landing treatment
  so native page JSON can use the common page/CPT media-text pattern without
  adding another gesture.
- Added a universal occurrence booking calendar/intake plan and began the clean
  refactor: public slot reads now use `/iss/v1/booking-slots`, timeline grouped
  `Termin wählen` opens the shared occurrence calendar, and commerce-lite public
  writes are collapsed to `/iss-payments/v1/request` with `booking`, `inquiry`,
  and `order` intents. The old public tour-booking/publication-order route
  registrations and migration wrappers were removed; request storage keeps the
  existing table/column names until a schema cleanup is justified.
- Reused the occurrence-backed timeline calendar path for the public calendar:
  `/kalender/` now enables the existing timeline month/day calendar bridge
  instead of embedding the Führung booking calendar. Booking-slot validation
  now uses a shared occurrence adapter so booking requests validate against
  Veranstaltung occurrence rows. Timeline booking actions infer the
  Veranstaltung booking URL from `iss_booking_enabled` when SuperSaaS-projected
  event rows do not store one directly.
- Restored the WordPress Pages menu for non-admin editors so eligible native
  landing pages are reachable from admin, while keeping generic Posts hidden.
  The shared editorial JSON admin now hydrates missing attachment preview data
  from stored media IDs, so existing media refs render thumbnails in the
  composition cards and media trays without requiring a JSON migration.
- Added local IBM Plex Serif font files to the theme, registered the family in
  `theme.json`, and switched the front-page landing serif headings/facts from
  the Georgia fallback to IBM Plex Serif. The frontpage landing skin now keeps
  semantic `h2` section headings but renders them at a smaller visual scale.
- Switched native landing JSON CTA links to the shared `.iss-button` primary
  tier so they inherit the active page color scheme, and extended landing dark
  surfaces with the shared button variables. The front-page rental panel now
  sizes by content while preserving the media/text visual match.
- Backfilled native page JSON landing data for the static front page and added
  `ops/sql/2026-06-30-frontpage-landing-json.sql` as the transfer artifact.
  Landing support now includes `statement`, `feature`, and mapped
  `dynamic_slot` gestures so the front-page body can be reconstructed while
  `front-page.html` keeps the hero/template wrapper. The theme suppresses the
  hardcoded front-page body fallback only when landing JSON is enabled and
  populated.
- Added a dedicated `frontpage` landing skin for homepage parity and switched
  the front-page JSON seed from `typografisch` to `frontpage`, keeping
  one-off homepage spacing in skin CSS rather than exposing extra gesture
  treatments.
- Adjusted `feature.microblocks` to render the homepage 50/50 media-text
  structure: microblocks/actions in the text column, with kicker/title/body as
  the media-card overlay heading.
- Documented the native page JSON landing SOW: V1 keeps landing surfaces as
  WordPress `page` posts with existing URLs, menus, front-page settings, and
  template wrappers, gates the `landing` JSON format to the front page plus
  `about`, `verein`, `salon-vermietung`, and `sammlungen`, and adds the shared
  `gateway` gesture with landing treatments while preserving current output
  whenever JSON is disabled or empty. No SQL, content migration, or upload
  artifact is required for this docs-only checkpoint.
- Implemented the first native page JSON landing slice. `iss-editorial` now
  supports post-aware format eligibility, section treatment registries, and
  gateway item sanitization without disabling Gutenberg for normal pages.
  `iss-content` registers the allowlisted `landing` format for native pages,
  and the theme owns the inert `industriesalon/editorial-landing` template slot,
  landing renderer, and gateway treatment CSS. Disabled or empty landing JSON
  renders no new markup and keeps the current page output; no SQL or upload
  artifact is required.

## 2026-06-29

- Added and deployed the missing front-page hero upload artifact:
  `ops/uploads/2026-06-29-frontpage-hero-media.tar.gz` packages the original
  and generated WebP sizes for Media Library attachment `26778`. The artifact
  was pushed to `origin/main`, staging was fast-forwarded to `befc31e`, the
  archive checksum was verified on staging, and the files were extracted into
  staging uploads so the DB-backed front page no longer references a missing
  hero image.
- Added the SuperSaaS pipeline staging layer for timeline sync: the occurrence
  plugin now creates `wp_iss_supersaas_slots`, normalizes one or more enabled
  SuperSaaS schedules into schedule-keyed slot imports, keeps mapped slots in
  the public occurrence projection with schedule-prefixed internal external IDs,
  handles schedules that reuse one raw SuperSaaS slot ID across many dated
  instances, and preserves raw slot IDs for booking APIs. The sync admin screen
  now opens with a sortable/filterable slot workbench that shows dates,
  schedule, cleaned series title, mapping state, source post, availability, and
  actions to map or ignore a series before the legacy series table. The
  workbench also stores and displays SuperSaaS descriptions where the API
  provides them, and falls back to the SuperSaaS `name` field when a schedule
  row has no title. Schedule configuration now distinguishes free-slot feeds
  from range/booking feeds so the `Salonbelegung` workbench can read actual
  booking descriptions instead of blank availability gaps. Salonbelegung rows
  now use event-series keys, stay out of the Führung series mapping queue, and
  can create draft Veranstaltung shells from the workbench; Repair-Cafe shells
  receive a timeline target URL that points timeline clicks at the existing
  `/repair-cafe/` landing page. The event workbench can also map/remap
  Salonbelegung rows to existing Veranstaltungen or mark them ignored; ignored
  slot decisions survive the next sync and are hidden from the default active
  workbench filter. Recurring Salonbelegung events now have a series-level
  workbench as well: mapped `event:repair-cafe` rows project as dated event
  occurrences from one canonical Veranstaltung source instead of creating one
  Veranstaltung post per booking, while raw Repair-Cafe slots no longer offer
  the per-instance create action. The local duplicate Repair-Cafe event shells
  were collapsed to canonical Veranstaltung `26813`; generated duplicates were
  moved to trash, `event:repair-cafe` was mapped to the canonical source, and
  the replay artifact is stored in
  `ops/sql/2026-06-29-repair-cafe-canonical-event-series.sql`.
- Centralized the first programme timeline policy in the existing
  `industriesalon/timeline-query` renderer instead of adding a parallel block:
  public programme timelines now default to Führungen, Veranstaltungen, and
  programme-enabled Ausstellungen, constrain source post types to the same
  programme set unless a project timeline is explicitly requested, and group
  recurring Führung rows by month only for month views. The front-page and
  `/kalender/` templates now both include programme Ausstellungen; grouped
  Führung rows with two or more dates now open a centralized month-grouped slot
  picker instead of rendering long inline occurrence lists. The active local
  DB-owned `front-page` template was resynced from the theme file.
- Added the next timeline cleanup slice: upcoming/teaser timelines now collapse
  SuperSaaS Führung occurrences by their linked Führung post while month views
  keep month-scoped series grouping, the unfinished Veranstaltung
  `Serientermin` structure is hidden from normal new editor selections, and
  `wp iss-occurrences supersaas-audit` reports unmapped series, wrong-source
  links, mapped Führungen without future rows, and future inactive SuperSaaS
  rows before any importer semantics change. SuperSaaS sync also now self-heals
  untagged series when the series title has one exact published Führung match;
  the local sync reconciled `Stadtrallye für Erwachsene` away from the stale
  Familienrallye source.
- Finished the first timeline data-fidelity cleanup pass: Veranstaltung
  migration-only post scans now route through repository-owned maintenance
  helpers so `wp iss-content veranstaltungen-query-audit` stays green, SuperSaaS
  sync requests full slots with `full=true`, clears stale non-Führung source
  mappings, can purge missing future inactive rows, and reports cleanup counts
  in CLI/admin sync output. The SuperSaaS audit now separates inert unmapped
  scheduler labels from unmapped labels that actually have occurrence rows.
- Fixed the SuperSaaS empty-title slot path exposed by the Bustour 05.07 row:
  title-less slots can now match only against existing mapped Führung series,
  public output keeps the mapped Führung/series title instead of SuperSaaS
  description notes, cancellation rows such as `AUSFALL` are removed from the
  public projection, and the SuperSaaS sync admin table now shows occurrence
  counts plus the next date per series.
- Added the client review checkpoint for front-page and Führungen presentation
  changes. The homepage DB-owned `front-page` template now matches the theme
  file and carries the one-off hero image/text experiment, removed spine/project
  rail/banner surfaces, note-style project cards with logos, and a primary
  projects CTA. The file-backed `/fuehrungen/` template now shows dated
  upcoming Führung cards first, followed by a non-tabbed `Gruppen & Co.` card
  grid from Führung posts. Local browser checks covered desktop/mobile ordering,
  card counts, absence of the removed catalog/banner surfaces, and horizontal
  overflow.
- Captured a one-off front-page baseline before client hero image/text
  experiments. The active homepage is a DB-owned `wp_template` override
  (`front-page`, ID `26534`) with static front page ID `12257`; rollback state
  is recorded in `ops/sql/2026-06-29-frontpage-baseline.sql`. No code, uploads,
  or media rows were changed for this checkpoint.
- Refined the JSON editorial rich-text authoring controls for Veranstaltung and
  shared editorial composition: body fields now use a local contenteditable
  helper with paragraph, bold, italic, link, unordered-list, and ordered-list
  controls, links are visible and removable, and Veranstaltung body sanitization
  permits only the matching narrow HTML contract. The editor keeps the existing
  lightweight local implementation rather than adopting Trix, but uses a calmer
  toolbar/editor skin consistent with the admin cleanup direction.
- Tightened Veranstaltung material/download authoring. The `material` gesture
  now offers description plus media references only, material media selection is
  no longer image-only, non-image material attachments render as theme-owned
  `Herunterladen` download cards, and obsolete item/object/dynamic controls are
  removed from the material payload. `programm`, `upload_intake`, and `schluss`
  also dropped the obsolete "Punkte" item field now that rich-text lists are
  available. Legacy saved item output remains renderable until edited/saved.
- Fixed Veranstaltung composition media picker feedback so selecting or removing
  media updates the modal tray and card preview immediately without requiring a
  save/reopen cycle.

## 2026-06-28

- Unified lightweight booking/order intake around `iss-commerce-lite`:
  `/iss-payments/v1/request` is now the canonical public request endpoint for
  `tour_booking`, `event_booking`, and `publication_order`, while the old tour
  and publication endpoints remain compatibility wrappers. Veranstaltung
  booking now mirrors the publication sale gesture in the native WordPress right
  rail with a toggle, Euro price, CTA label, and booking hint; occurrence/timeline
  rendering projects that state into the existing booking modal with the Mollie
  option still disabled until a real provider integration registers support.
  This extends the existing request table/admin/export layer instead of
  introducing a parallel booking store; no SQL or upload artifact is required.
- Tightened shared JSON editor wording: editors now choose/add visible
  `Abschnitt` entries rather than seeing the internal architecture term
  `Geste`, the redundant Veranstaltung per-card type selector is removed, and
  the stored `kicker` field is labelled as `Vorspann` in the editorial UI
  without changing the JSON storage contract.
- Consolidated the first shared composition UX slice across JSON-driven content:
  the shared `iss-editorial` card list now exposes `Löschen` beside
  `Bearbeiten`, Veranstaltung composition now uses compact ordered cards plus an
  edit modal instead of always-open section forms and a separate preview column,
  the Veranstaltung add-section rail now uses the same colored-dot structure as
  other JSON editors, and the useful Veranstaltung health/status strip stays
  inside the composition box with explicit Beitragsbild/Kurzbeschreibung checks
  while the separate Redaktionsstatus metabox is no longer registered.
- Polished the Veranstaltung editorial admin workflow: the old type list is now
  a structural `Struktur` choice (`Veranstaltung`, `Programm / Fest`,
  `Serientermin`) plus a hidden semantic `veranstaltung_art` taxonomy for
  `Art` filters such as Vortrag, Gespräch, Lesung, Präsentation, Workshop,
  Konzert, Film, and Repair Café. Legacy stored event type keys are mapped to
  the new structure/semantic split for repository queries, graph offer
  contracts, related-card placeholders, and list-table filtering. The
  Veranstaltung editor copy now avoids storage/rendering terms, recurring events
  use `Serientermin`, promotion sits inside `Struktur & Art`, the status panel
  is compact, linked-content actions explain their purpose, non-admin editors
  cannot reopen hidden Screen Options or postbox action controls, default Posts
  and Pages are hidden from editor navigation, and the Veranstaltung list drops
  the unused WordPress category dropdown while keeping Struktur, Art, and
  visibility filters. No SQL or upload artifact is required; the semantic
  taxonomy terms and legacy-term backfill are code-driven.
- Added a read-only `wp iss-content editor-ui-audit` first slice for the
  editorial admin simplification work. It inventories metaboxes, dashboard
  anchors, registered meta, taxonomies, list-table columns, save hooks, sampled
  blocks, and user-specific Screen Options by role/post type, classifying
  surfaces as `must_show`, `integrated`, `hide_for_editors`, or `review` before
  any editor UI is hidden or moved. The SOW now records raw custom fields, slug,
  revisions, page attributes, raw taxonomy boxes, and diagnostic graph/search
  boxes as shared wholesale simplification candidates rather than CPT-specific
  decisions, and `iss-content` now hides those wholesale metaboxes for
  non-admin editors across supported ISS content, relation, archive, register,
  and graph-backed edit screens while preserving administrator visibility.
  Projekt now acts as the reference integrated-relations slice: promotion stays
  directly visible, while places, people/organizations, archive material, and
  Sets/media open from compact dashboard actions into their existing owner
  controls without changing storage or save paths. The same Projekt slice now
  puts excerpt, featured image, and required project facts in the top dashboard
  row, renames project facts to "Pflichtangaben", relabels the section as
  "Verknüpfte Inhalte", and simplifies promotion wording to "Inhalt promoten".
  Projekt ordering now keeps native WordPress `menu_order` storage but removes
  the raw "Startseiten-Reihenfolge" weight from normal editors; editors reorder
  projects by drag/drop in the unfiltered Projekt list table, while
  administrators keep the raw field as a repair hatch. This extends the
  existing `iss-content` admin/list-table layer instead of adding a parallel
  ordering store. Projekt linked-content controls now collapse into a right-rail
  "Verknüpfte Inhalte" launcher with Orte, Akteure, Archive, and Media modal
  buttons, while related-content promotion moves into "Pflichtangaben" as a
  simple "Inhalt promoten" toggle for normal editors. The graph save path remains
  the authority; related self-promotion no longer requires fake reason/expiry
  metadata. The same compact linked-content pattern now covers Veranstaltung,
  Ausstellung, Publication, Fuehrung, and Rueckblick where the owner controls
  are available: normal editors get right-rail launchers, promotion moves into
  facts/identity as a simple toggle, Fuehrung keeps route/place relations inside
  the route editor, and Publication keeps its publication-specific related
  publications box in the main dashboard for a later content-specific decision.
  Veranstaltung, Ausstellung, Publication, and Fuehrung now match the Projekt
  top-row pattern: excerpt, featured image, and required facts appear before the
  composition canvas, and generic basis/data panels use the editor-facing
  "Pflichtangaben" label.
- Implemented the first `iss-graph` related-graph autonomy slice: relation
  provenance/status columns, relation backfill/dedupe/audit CLI commands, dirty
  queue and bounded reconcile CLI, autonomy health and fixture checks, soft
  editorial-signal removal plus export/import, no-frontend-repair guards,
  harvest-critical Veranstaltung venue warnings, and promotion list-table
  status/filter/disable actions. Local Docker verification backfilled 4,796
  relation rows and deprecated four duplicate manual edges so relation
  integrity health reports zero missing provenance, orphan edges, and active
  duplicate edge keys.
- Expanded `docs/project/related-graph-autonomy-sow.md` with the editor-facing
  contract, list-table promotion workflow, audit/reversibility expectations,
  dirty-queue/cron autonomy gate, observability/health requirements, scheduler
  independence, harvest-critical validation, relation provenance/status
  guardrails, dry-run/rollback expectations, fixture coverage, performance
  limits, acceptance drills, and rebuild/recovery criteria for derived relation
  state.
- Added `docs/project/related-graph-autonomy-sow.md` to record the relatedness
  direction: public related places/content must be autonomously derivable
  without admin presence, manual relations are correction/curation rather than
  backbone data, and visible content promotion remains a separate
  non-graph-specific editorial signal.
- Made related-content resolution graph-first for the default current-post path
  and loosened the `iss-relations` relation payload contract from place-only
  `place_id` rows to generic entity targets while preserving legacy place rows.
  `iss-content` now persists Veranstaltung `iss_primary_place_id` as native
  meta, and `iss-graph` harvests it as a `content_native` venue edge so the
  related-places editor box is no longer the only source of relatedness.
- Added a Video CPT transcript JSON contract in `iss-content`: editors can clean
  timecoded segments in a dedicated metabox, save through the normal WordPress
  Update action, and public video blocks render `_iss_video_transcript_json`
  when present with legacy `post_content` fallback. The Video Gutenberg body
  canvas is hidden after migration so transcript edits happen in the JSON
  segment editor, and existing video transcript transfer state is captured as an
  explicit SQL artifact instead of a hidden runtime migration.

## 2026-06-27

- Unified editorial composition save behavior so JSON-first composition changes
  are committed through the normal WordPress Update action instead of a second
  in-canvas JSON save button, and aligned the Veranstaltung structure editor with
  the shared left-rail gesture layout.
- Implemented the shared editorial admin dashboard shell for compatible
  classic/editorial edit screens:
  - added reusable dashboard section registration and DOM assembly in
    `iss-content` without moving storage or save ownership;
  - converted `veranstaltung`, `projekt`, `ausstellung`, `publication`,
    `fuehrung`, and `rueckblick` to the shared section order for identity,
    composition, facts, relations/references, sale/publish where applicable;
  - kept `iss-editorial`, `iss-publications`, Führung meta, Sets,
    `iss-relations`, `iss-graph`, and `iss-archive` as the actual control/save
    owners;
  - explicitly left Gutenberg screens `video`, `page`, and `post` out of the
    classic dashboard mover pending a separate block-editor adapter.
- Added `docs/project/editorial-admin-simplification-sow.md` to define the
  shared ISS editorial admin direction: no global admin reskin, classify each
  CPT field/block/metabox as must-show, integrated, hidden, migrated, or purged,
  and converge JSON composition, facts, relations, media/Sets, archive refs, and
  list-table status into one dashboard model with one editing authority per
  concept.
- Completed the hard editorial vocabulary cleanup after the compatibility pass:
  - migrated local stored editorial JSON to canonical gestures, skins, and
    `features.rail`;
  - added rollback and deploy SQL artifacts at
    `ops/sql/2026-06-27-editorial-vocabulary-pre-migration.sql` and
    `ops/sql/2026-06-27-editorial-vocabulary-normalized-json.sql`;
  - removed hidden legacy section definitions, legacy skin aliases, legacy
    renderer branches, and the one-off `normalize-vocabulary` CLI command;
  - renamed Ausstellung skin CSS assets to `quellenbuehne` and `objektalbum`,
    removed the `industrieakte` stylesheet, and collapsed Veranstaltung skin
    defaults to canonical `typografisch`, `buehne`, and `chronik`;
  - left Führungen out of this cleanup pass per the architecture boundary.
- Started the shared gesture/skin/feature implementation from the audit answer:
  - added an `iss-content` editorial vocabulary helper for canonical gestures,
    canonical skins, skin aliases, and rail feature defaults;
  - kept public presentation in the theme by resolving canonical skin classes
    and legacy CSS aliases there instead of moving render logic into the
    plugin;
  - made `bildmatrix`, `quellenbuehne`, and `objektalbum` valid canonical skin
    slugs while preserving existing `blueprint-matrix`, `frauen-im-werk`, and
    `kinder-im-werk` render paths;
  - added project rail feature classes and resolver defaults while preserving
    existing `projekt_rail` documents as the current on/off source;
  - added `leitfrage` and `schluss` to Veranstaltung gesture palettes where
    the existing event structure can already render them.
- Added the first document-level feature slice for project rails:
  - extended `iss-editorial` documents with sanitized `features.rail` metadata;
  - added a compact project editor control for rail on/off, placement, and
    visual treatment;
  - made project rendering resolve rail from `features.rail` while preserving
    existing `projekt_rail` sections as the legacy default-on signal;
  - supported top/horizontal, side, and bottom rail placements without treating
    the rail as authored content.
- Hid legacy feature-only sections from editorial composition:
  - added a `ui_hidden` format-section flag to `iss-editorial`;
  - marked `projekt_rail` hidden in the project format so editors use the rail
    feature control instead of adding a fake navigation gesture;
  - kept hidden sections valid in stored documents for backwards-compatible
    rail fallback.
- Consolidated gallery/image gestures:
  - added a `gallery_layout` section field with `grid`, `sequence`, and `wall`
    values;
  - added canonical `galerie` support to exhibitions and marked legacy
    `bildstrecke` / `image_wall` sections hidden where replacement authoring is
    ready;
  - made project and exhibition renderers treat `galerie` with `wall` layout as
    the existing image-wall treatment while keeping old stored section types
    valid.
- Consolidated exhibition quote/source gestures:
  - marked legacy `quellenauszug` hidden while keeping stored sections valid;
  - added `quote_treatment` to canonical `zitat` sections so editors can choose
    normal quote or source-focused excerpt treatment;
  - made source-treated `zitat` sections reuse the existing source-focus
    exhibition layouts, including media orientation.
- Consolidated exhibition aside gestures:
  - marked legacy `aside` hidden while keeping stored sections valid;
  - added `section_treatment` to canonical `kapitel` sections so editors can
    choose the existing Ausstellungsentscheidung layout without changing
    gesture type;
  - kept the old `layout-aside` theme treatment as the rendering target.
- Consolidated Rückblick registry gestures:
  - added canonical `fliesstext`, `galerie`, `objektfokus`, `material`, and
    `schluss` sections;
  - marked legacy `bericht`, `bildstrecke`, and `quellen` hidden while keeping
    them valid for imported or old documents;
  - made editorial Set promotion prefer canonical `galerie` before legacy
    `bildstrecke`.
- Consolidated skin picker vocabularies:
  - kept legacy skins renderable for stored documents;
  - removed alias-only skin choices from Ausstellung, Projekt, and publication
    editor pickers so new authoring uses `quellenbuehne`, `objektalbum`,
    `dossier`, `typografisch`, and `bildmatrix`.
- Added `wp iss-editorial normalize-vocabulary` as a dry-run-first maintenance
  command for stored editorial JSON, with `--write` required to persist legacy
  gesture and skin rewrites.
- Added a registry diagnostics and backfill maintenance slice:
  - added `wp iss status` in `iss-core` to report first-party plugin loading,
    key CPT registration, schema/backfill option versions, graph/occurrence/Set
    tables, Event Drop storage isolation, and expected theme render helpers;
  - added `wp iss backfill-all` as an explicit operator command for graph,
    relations, WP occurrence-source, and editorial Set schema backfills, with
    `--dry-run` by default-friendly reporting and external sync excluded unless
    `--include-external` is passed;
  - made `iss-relations` consume its activation and graph-identifier backfill
    flags on admin requests instead of leaving callable backfill functions
    unhooked;
  - replaced silent theme render-helper includes with a tracked expected-helper
    list and an administrator notice when a required render helper is missing.
- Added the Führung JSON gesture/skin slice:
  - registered a `fuehrung` editorial format in the shared `iss-editorial`
    engine with tour gestures for intro, chapters, thesis/quote moments,
    galleries, image walls, material, and conclusion links;
  - kept booking, dates, route stations, facts, hero-gallery meta, Atlas map,
    and related discovery on the existing tour module/template contracts;
  - added a theme-owned tour renderer and template slot so enabled
    `_iss_editorial_fuehrung` documents replace only the narrative layer while
    unmigrated tours keep the legacy `post_content` description fallback.
- Migrated the existing local Führung narrative layer into JSON:
  - added `wp iss-editorial fuehrung-dry-run` and
    `wp iss-editorial fuehrung-import-candidate` for published Führung
    candidates;
  - imported and enabled `_iss_editorial_fuehrung` for all 15 published local
    Führung posts, skipping only template-owned infrastructure blocks such as
    tour calendar and related-content output;
  - created `ops/sql/2026-06-27-fuehrung-editorial-json.sql` as the narrow DB
    transfer artifact for the JSON documents and enabled flags; no uploads
    artifact is required because this migration does not introduce media refs.
- Integrated Führung route-station editing into the JSON composition editor:
  - added a `Route / Stationen` panel for Führungen that edits the existing
    `iss_related_places` station rows while leaving station storage in
    `iss-relations`, not in `_iss_editorial_fuehrung`;
  - hid the older `Verknüpfte Orte` metabox on Führung edit screens to avoid
    two competing station editors while keeping the shared metabox for other
    post types;
  - added a narrow `iss-relations` REST save/read route for post place
    relations because the existing related-preview endpoint is read-only for
    card previews and raw post-meta REST saves would skip the established
    station object/story back-link side effects; the route degrades to normal
    WordPress form submission through synced hidden `iss_relations` fields.
- Extended the publication JSON migration beyond photoalbums:
  - added `longread_chapter` and `timeline_item` gestures to the existing
    `publication` editorial format instead of adding new Gutenberg blocks or a
    second authoring path;
  - added a generic `year` field to the shared editorial editor/storage layer
    for dated timeline stations, with timeline images continuing to use the
    existing promoted `media_refs` contract;
  - made JSON-backed longreads and timelines produce the same payload shapes as
    the legacy publication blocks, so the existing theme-owned longread and
    timeline renderers remain the public UI owner;
  - kept legacy Gutenberg/block parsing as the fallback for unmigrated
    publications.
- Migrated the existing local publication longreads/timelines into the JSON
  gesture contract:
  - enabled `_iss_editorial_publication` for 12 existing posts, covering
    timelines `18864`, `18865`, `18873` and longreads `18878`, `18881`,
    `18886`, `21105`, `21109`, `21110`, `21111`, `21114`, `21125`;
  - converted dated chronik entries into `timeline_item` sections with `year`
    and promoted `media_refs`, and converted longread body sections into
    `longread_chapter` sections with anchors;
  - created `ops/sql/2026-06-27-publication-longread-timeline-json.sql` as the
    DB transfer artifact for the migrated JSON documents, enabled flags, and
    layout meta;
  - recorded that `18873` is currently an intro/rail timeline stub with no
    dated timeline stations in the local source content, and that no upload
    artifact was created because timeline images rely on existing Media Library
    attachment rows/files.
- Added the first theme-owned longread poster skin:
  - exposed `longread-poster` as a publication skin and assigned it locally to
    all nine migrated JSON longreads;
  - added a reusable `longread_quote` gesture and moved normal longread imagery
    onto `longread_chapter` through the shared `media_refs` contract, with a
    constrained inline or right-aside chapter image placement;
  - kept longread navigation generated from `longread_chapter` sections only,
    while quote moments render in document order between chapters;
  - styled the skin in the theme with a restrained typographic longread rhythm,
    pull quotes, chapter images, mobile fallbacks, and an empty-rail collapse
    path;
  - regenerated `ops/sql/2026-06-27-publication-longread-timeline-json.sql` so
    the transfer artifact now includes the `longread-poster` assignments for
    migrated longreads.

## 2026-06-26

- Added the first publication JSON migration slice for photoalbums:
  - registered a `publication` editorial JSON format with `intro`, `source`,
    optional `publication_rail`, and editable `photoalbum` gestures;
  - extended the shared editorial editor/storage layer with constrained
    photoalbum source/sheet controls and publication rail options instead of
    relying on Gutenberg image blocks or server-only album magic;
  - made photoalbums render from explicit JSON sheets first, while preserving
    legacy Gutenberg/Archivset fallback behavior for unmigrated publications;
  - allowed album sheets to be imported from an Archivset or editorial Set,
    then reordered, hidden, captioned, and given nav titles in the JSON editor;
  - made publication reading rails optional/skinnable through a
    `publication_rail` gesture while keeping legacy non-JSON rails automatic;
  - added a theme-owned `blueprint-matrix` publication skin for photoalbums,
    with a viewport-wide technical matrix, compact sheet cards, and per-sheet
    description drawers plus footer-style place and related-content context;
  - rendered the blueprint footer context through theme-owned relation markup
    and cached raw skin lookup, avoiding dynamic block-callback recursion and
    full editorial-document reads during frontend rendering;
  - seeded local `nef-album` with a 63-sheet JSON document and added
    `ops/sql/2026-06-26-nef-album-publication-json.sql` as the narrow transfer
    artifact for that DB-backed publication state and its Behrensbau place
    relation;
  - extended the same `blueprint-matrix` structure to the remaining local WF
    photoalbums: Labor/LKVO (`18894`, 52 sheets), Produkte LKVO (`18948`, 23
    sheets), and Produktion HF (`19038`, 34 sheets), using manual `WF-Museum`
    source metadata, resolved Media Library sheet refs, Behrensbau place
    relations, and
    `ops/sql/2026-06-26-photoalbum-blueprint-other-albums.sql` as the paired
    transfer artifact.
- Reduced `blueprint-matrix` photoalbum frontend render load after staging
  feedback: the theme now regenerates blueprint grid cell images from
  attachment/archive-object IDs as `medium` thumbnails while preserving
  full-size detail links, and removed the per-cell image filter that made
  scroll/paint work expensive on large albums.
- Normalized project media Set ownership:
  - project source Sets now use the canonical `project-set-<project-slug>` key
    when created or reopened;
  - the Event Drop attachment watcher now uses the same target resolver as raw
    incoming uploads, so project attachments with `projekt__<slug>` or a
    project parent land in the project source-material Set instead of creating
    a separate `event-drop-*` Set;
  - added an idempotent normalization helper for existing project-shaped
    duplicate Sets and used it locally to merge the Walk of Fame duplicate
    `event-drop-*` Set into `project-set-walk-of-fame-schoeneweide`.
- Added the first shared surface/color contract slice:
  - introduced readable surface tokens in `style.css` for foreground, muted
    text, subtle text, rules, heading text, and kicker text/accent;
  - kept existing `section--dark`, `iss-heading--light`, and
    `iss-kicker--light` as compatibility hooks while aligning them with the new
    contract;
  - moved project editorial section kicker/title/body text away from raw accent
    colors and onto surface tokens, including nested project media, download,
    facts, and archive-reference cards;
  - switched the obvious page-family heading overrides, archive featured-object
    cards, and timeline kickers so accent remains the rail/dot color while
    text uses the readable surface color;
  - aligned Veranstaltung skins with the same surface contract, including the
    dark festival skin, event meta panels, generic info panels, and related
    network/plugin bridge output;
  - extended the sweep to shared card families, project context sidebars,
    archive/publication rails, Kalender timeline labels, primitive meta labels,
    and Ausstellung editorial skins so small kicker/meta text uses readable
    surface text while accents remain markers, rails, and hover states;
  - fixed publication single surfaces across standard, longread, photoalbum,
    and timeline layouts, including dark photoalbum reading panels, longread
    chapter indexes, timeline year/range labels, related-card links, and file
    download buttons;
  - audited public plugin renderers: archive/content/graph/relations/video
    blocks mostly emit shared `.iss-kicker` markup for the theme to style; the
    remaining inline atlas-slice caption in `iss-relations` is a later cleanup.

## 2026-06-25

- Added the first `projekt` registry/gesture migration slice:
  - registered the `projekt` editorial format in `iss-content` with `kapitel`,
    `fliesstext`, `massstab`, `projekt_rail`, `galerie`, `image_wall`,
    `material`, and `schluss` gestures;
  - made `projekt_rail` an optional navigation gesture: when present, the theme
    appends it below the project meta panel and derives links from `kapitel`
    and `schluss` section anchors; when absent, short projects stay in a
    single-column flow;
  - moved JSON-backed project context out of manual Gutenberg rail markup: the
    theme now appends generated related places plus a compact related-content
    group below the meta/rail stack from the existing relations layer;
  - kept project media intake Set-first: Sets remain private growing
    collections, while promotion writes approved `media_refs` / `object_refs`
    into the project document only;
  - added a theme-owned project editorial renderer with legacy `post_content`
    fallback unless `_iss_editorial_enabled_projekt` is enabled per post;
  - added `wp iss-editorial projekt-dry-run` and
    `wp iss-editorial projekt-import-candidate` for disabled migration
    candidates;
  - created `ops/sql/2026-06-25-project-editorial-json-candidates.sql` for the
    seven current published project candidates, all with frontend rendering
    disabled.
- Added structured facts to the project `massstab` gesture:
  - extended the existing project editorial format with repeatable `value` /
    `label` fact rows so editors do not need inline `<strong>` markup in body
    text;
  - made the project renderer prefer structured facts while preserving optional
    body fallback text;
  - added a scoped `layout-key-points` project treatment in `single-content.css`.
- Added the first theme-owned project skin review pass:
  - exposed `brief`, `dossier`, `field`, and `standard` as allowed `projekt`
    skins through the existing editor-visible skin path;
  - kept the JSON gesture contract unchanged while allowing skin-specific theme
    markup for project navigation and `massstab` fact sections;
  - made `dossier` render project navigation as a horizontal chapter strip in
    the content flow, pair `kapitel` plus following `massstab` sections into a
    chapter/fact spread, center the dossier story flow in a 75rem section
    container, promote the horizontal rail into a dark primary CTA band, and
    move generated places/context into a footer grid;
  - made `brief` a compact project-sheet treatment without the heavy chapter
    rail, and `field` a darker spatial/index treatment that reuses generated
    context and related-place output instead of adding a map schema;
  - added `ops/sql/2026-06-25-project-skin-review-assignments.sql` for the
    three local review assignments: Walk of Fame as `brief`, Stadtlabor as
    `field`, and Futura as `dossier`.
- Strengthened project media/material handling:
  - made project `galerie` render through project-owned carousel markup while
    reusing the shared strip-carousel JavaScript hooks already used by
    Veranstaltungen;
  - gave project galleries a stronger dark section treatment and kept mobile as
    a horizontal strip rather than a long image stack;
  - allowed `material` sections in the editorial JSON UI to pick files and
    documents, not only images, and added a project file-card fallback for
    non-image media refs.
- Reconciled project media Sets with the admin workflow:
  - made the Sets Workbench context-aware so project-scoped Workbench views show
    only Sets attached to that project;
  - kept Sets reachable under Operations when the user has Operations access,
    with a Tools fallback when that parent menu is unavailable;
  - added project edit-screen actions to create the attached project Set, open
    it, or open it directly for upload;
  - added a Set-scoped raw upload endpoint and Workbench file picker so project
    files enter the private `external_upload` intake/review path before Media
    Library import;
  - changed default project Set promotion to split approved items by type:
    images/videos append to `galerie`, while PDFs/documents/archive objects
    append to `material`.
- Added project Set lifecycle rules:
  - new real `projekt` saves now ensure a source-material project Set unless
    the post is an autosave, revision, auto-draft, or trash;
  - deleting or trashing a project may quarantine disposable raw
    `external_upload` intake with decay, but it does not delete promoted,
    retained, archive-candidate, Media Library, or shared Set material;
  - project-only Sets are marked `stale` after disposable raw intake is
    quarantined, while Sets attached to other live contexts are left active.
- Extended public contribution intake to projects:
  - added a project `upload_intake` gesture that renders a theme-owned CTA to
    the existing `/event-drop/` interface instead of adding a parallel uploader;
  - routes project Event Drop targets with the preserved `projekt__<slug>`
    marker into the attached source-material project Set;
  - records project target provenance on incoming `external_upload` items and
    keeps public pages rendering only promoted editorial references.
- Added constrained rich-text editing for project chapter prose:
  - replaced the project `kapitel`, `fliesstext`, and `schluss` body textarea
    with a small custom editor for paragraphs, emphasis, links, and lists;
  - limited stored project body HTML to `p`, `br`, `strong`, `em`, `a[href]`,
    `ul`, `ol`, and `li` with server-side sanitization.
- Added the reusable `image_wall` / `Bilderwand` editorial gesture for
  Ausstellung JSON documents:
  - registered the gesture in the existing `iss-content` editorial format
    registry with `media_refs` only, instead of adding a parallel Gutenberg or
    shortcode path;
  - mapped it through the theme-owned Ausstellung renderer as
    `layout-image-wall`;
  - added a framed, uncropped image-wall treatment in
    `single-ausstellung.css`, with horizontal scrolling on narrow screens so
    dense media does not turn into a long vertical stack;
  - kept `iss/dense-image-wall` as the heavier asymmetric Gutenberg composer
    because its per-item layout controls are useful but outside the custom
    editor gesture model.

## 2026-06-24

- Added `docs/runbooks/event-drop-staging.md` for the current Sets-backed
  Event Drop staging setup: deploy from GitHub instead of untarring, create the
  writable `/event-drop-storage` mount tree, verify the mounted intake
  interface and `docker/php/uploads.ini`, keep upload secrets out of Git, and
  treat ZIP files as raw intake rather than a staging extraction step.
- Added `docs/project/operations-admin-permissions-sow.md` to define the
  code-owned Operations admin capability model before more custom admin screens
  are built; documented cross-plugin capability assembly, fail-closed
  screen/action authorization, version-gated role migrations, audit logging,
  rollout slices, and that third-party role plugins may only be assignment UI,
  not permission authority.
- Implemented the Operations admin capability foundation:
  - added the `iss-core` capability registry, mapped capability helper,
    Operations root menu, `iss_require_cap()` / `iss_cap_check()` helpers,
    version-gated `iss_caps_version` role migration, WP-CLI diagnostics, and
    lightweight operations audit logging;
  - created project roles for operations manager, curator/editor, reviewer,
    intake helper, and technical maintainer while preserving administrator and
    editor compatibility grants;
  - moved Steuerung, Sets, Register tools, SuperSaaS sync/settings, ISS
    requests, and operational CPT menus under Operations where available;
  - mapped Rueckblick, Publications, Register places, Hinweise, and Archive
    CPTs to explicit primitive/meta caps and tightened Set/Archivset REST
    permissions for create, edit, review, promote, and delete paths.
- Added `ops/sql/2026-06-24-remove-shop-surecart-roles.sql` as the transfer
  artifact for removing legacy shop/SureCart roles and residual Woo/SureCart
  capabilities from `wp_user_roles`; the artifact backs up the prior option
  value before replacing it with the verified core plus ISS role set.
- Implemented the editorial media Sets workflow foundation from `docs/architecture/editorial-media-buckets.md`:
  - added `iss-content` owned Set, item, context-link, and audit tables because the full SOW requires indexed queues, cross-context filters, batch review, decay sweeps, and promotion history that post meta would make fragile;
  - added the private Intake Workbench admin surface with Set creation, media attachment, thumbnail grid, modal facts, filters, status/batch actions, context attachment, promotion, and archive-candidate marking;
  - added explicit REST/service APIs for Set CRUD, item movement/review, context links, promotion, decay, and Event Drop attachment intake without making public renderers read raw Set state;
  - added Event Drop incoming-file sync so successful public uploads appear as pending private Workbench items before WordPress attachment promotion, with authenticated admin previews, promotion-time Media Library import, reject quarantine/restore handling, German Workbench labels, and filename/file-metadata recovery when manifest rows are missing;
  - added `rueckblick` as a first-class CPT with an `iss-editorial` document format, while keeping promotion into public pages limited to approved `media_refs` / `object_refs`.
- Added a test `upload_intake` Veranstaltung gesture:
  - exposes an editor-visible Upload-Aufruf section that renders a public CTA to `/event-drop/?event=<veranstaltung-slug>`;
  - keeps uploaded material in the moderated intake/Set path and does not render raw uploads on public pages.
- Normalized the local Event Drop upload frontend:
  - mounted the committed intake interface at `/event-drop/` in Docker and added local ignored `.env` upload-code support;
  - made the intake form honor the `event` query parameter so Veranstaltung upload CTAs prefill the target event.
- Raised local Event Drop upload runtime limits:
  - mounted `docker/php/uploads.ini` into the WordPress and WP-CLI containers;
  - increased PHP upload/post limits for large moderated intake files and verified an upload above the old 2 MB default succeeds.
- Implemented the Veranstaltung Shape + Skin cleanup:
  - added registry-derived `iss-event-shape-*`, `iss-event-skin-*`, and normalized `iss-event-entity-*` body classes for singular Veranstaltungen;
  - added concrete Vortrag, Lesung, Gespraech, Repair, and Festival skins from the shape/skin mockup, with scoped typography, sidebar, color, layout, and programme treatments while leaving undesigned event entities on the baseline treatment;
  - made `galerie` the explicit image-gallery gesture with a framed carousel strip, removed image refs from `material`, and added `ops/sql/2026-06-24-veranstaltung-24988-material-gallery-split.sql` for the one existing material-image row;
  - replaced old `iss-event-format-*` renderer markup/CSS with the structured JSON class surface and removed stale festival-info styling;
  - removed completed legacy body import commands/helpers and the unused skin-override meta shell after all current Veranstaltung JSON documents passed review;
  - moved graph offer subtype sources to `_iss_entity_key` only, removing the remaining layout/format fallback path.
- Added the guarded Veranstaltung single-renderer slice:
  - marked the editorial review for `24988`, `13349`, and `25808` as passed in the active checkpoint;
  - added a theme-owned `_iss_content_json` renderer for those reviewed posts only, with legacy `post_content` fallback for every other Veranstaltung;
  - reused the existing `iss-content` structured document contract and central `industriesalon-steuerung` field API instead of adding a parallel route, template, or data layer.
- Widened the Veranstaltung structured renderer from a hard-coded reviewed-post list to every Veranstaltung with a valid sanitized `_iss_content_json` document, while preserving legacy `post_content` fallback for empty or invalid rows.
- Migrated the remaining Veranstaltung legacy bodies into structured JSON:
  - imported 22 additional `_iss_content_json` documents through the existing `iss-content` importer without overwriting the three reviewed documents;
  - brought the local Veranstaltung structured-content state to `25` stored, `25` valid, `0` invalid documents;
  - added `ops/sql/2026-06-24-veranstaltungen-content-json-full.sql` as the full transfer artifact for all current Veranstaltung structured-content JSON;
  - marked editorial review as passed for all 25 migrated Veranstaltung JSON documents.
- Moved Veranstaltung authoring off Gutenberg:
  - removed `veranstaltung` from the block-editor opt-in list and removed default editor support for the CPT;
  - promoted the existing `Struktur` JSON editor to the first high-priority normal editor surface while keeping title, publish/status, taxonomy/meta, and relation boxes available;
  - removed the obsolete theme-owned Veranstaltung Gutenberg panel for inserting Terminblatt block patterns.
- Removed the legacy Veranstaltung presentation switches:
  - removed active `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme` registration/save/UI/body-class/template decisions;
  - deleted the remaining local `wp_postmeta` rows for those legacy keys and added `ops/sql/2026-06-24-veranstaltungen-remove-legacy-presentation-meta.sql` for transfer;
  - removed migration-only legacy layout/format inference from the registry and dry-run command now that all current Veranstaltungen have curated `_iss_entity_key` values;
  - removed the old Terminblatt/Fest Gutenberg event patterns and kept a single baseline single-event CSS treatment until explicit skins/template work replaces it.
- Routed Veranstaltung related rails through the shape-aware repository:
  - fixed `iss_content_model_veranstaltungen_related()` to use the existing entity-related source contract and filter returned posts through registry-valid `_iss_entity_key` values;
  - delegated Veranstaltung-only related-content rails on Veranstaltung pages to that repository method while preserving manual related blocks;
  - kept the raw Veranstaltung query audit passing with only the approved repository/CLI query paths.
- Audited Veranstaltung archive/homepage/calendar projections:
  - confirmed the public block surfaces use the existing occurrence-backed `industriesalon/timeline-query` route rather than raw Veranstaltung post loops;
  - confirmed the Veranstaltung occurrence provider already gates synced rows to timeline-shaped entity keys;
  - left the existing projection owner in place instead of adding a parallel repository bridge.
- Added the sixth Veranstaltung JSON editor slice:
  - added `dynamic_refs` to the structured Veranstaltung section contract for centralized `industriesalon-steuerung` field references;
  - taught legacy import to preserve `industriesalon/field` blocks as references such as `address.full` instead of flattening address/opening-hours/link values into `_iss_content_json`;
  - added editor preview support that resolves current Steuerung values read-only while saving only the reference metadata;
  - imported post `25808` into `_iss_content_json` as a five-section `event.festival` candidate and added `ops/sql/2026-06-24-veranstaltung-25808-content-json.sql`.
- Tightened Veranstaltung archive-object refs:
  - stopped storing long Archivset member captions in `_iss_content_json`;
  - capped archive-object labels and rendered selected objects as thumbnail cards in the `Struktur` tray and preview;
  - refreshed post `24988` and `ops/sql/2026-06-23-veranstaltung-24988-content-json.sql` with the lean object-ref payload.
- Added `galerie` to the normal Veranstaltung gesture set so editors can collect image-heavy event posts in a dedicated gallery section instead of overloading prose or material sections.
- Added the editorial media bucket contract stub:
  - documented one shared private intake/review/promotion model for Veranstaltung, Ausstellung, Projekt, Publication, pages, and archive contexts;
  - kept `galerie` as an approved presentation section, not an editor dump area;
  - deferred UI/storage implementation and explicitly avoided separate `eventset`, `projectset`, or `publicationset` systems.
- Expanded the media intake/bucket contract into an implementation SOW:
  - defined one shared thumbnail-grid intake workbench instead of separate editor-facing buckets per event/content type;
  - made named Sets the editor-facing model, including uncategorized intake, context-free preparation Sets, item movement between Sets, and whole-Set attachment/promotion to multiple CPT targets;
  - added temporary decay/retention rules for raw uploads;
  - made Rueckblick a first-class promotion target and archive promotion a stricter `iss-archive` curation handoff.

## 2026-06-23

- Added the first Veranstaltung JSON editor slice:
  - added an `iss-content` admin structure box for `_iss_content_json` on Veranstaltung edit screens without disabling the normal editor or changing public rendering;
  - limited available gestures by the selected `_iss_entity_key` registry contract and saved normalized section cards through the existing nonce-protected post save path;
  - tightened `veranstaltungen-content-audit` so empty meta rows are not counted as stored JSON documents.
- Added the second Veranstaltung JSON editor slice:
  - added `wp iss-content veranstaltungen-content-dry-run` to report legacy-body import candidates for all converted Veranstaltungen;
  - added guarded `wp iss-content veranstaltungen-import-candidate --post=<id-or-slug>` with dry-run default and `--yes` writes to `_iss_content_json`;
  - kept generated documents editor-only and reported media/unsupported blocks for curator review.
- Added the third Veranstaltung JSON editor slice:
  - taught the import candidate builder to recognize the Gutenberg event format sheet, skip its navigation block, and preserve sheet chapters as structured sections with kicker/title/body/material items;
  - imported post `24988` into `_iss_content_json` as a six-section `event.vortrag` document from the saved format sheet;
  - added the transfer artifact `ops/sql/2026-06-23-veranstaltung-24988-content-json.sql` for that reviewed local document while keeping public rendering on legacy `post_content`.
- Added the fourth Veranstaltung JSON editor slice:
  - added a read-only preview column to the existing `Struktur` box so editors can inspect the compacted `_iss_content_json` sections before saving;
  - kept the preview entirely in the `iss-content` admin editor script, with no public renderer switch and no new endpoint;
  - verified post `24988` still stores a valid six-section `event.vortrag` document.
- Added the fifth Veranstaltung JSON editor slice:
  - added media and archive-object reference support to the existing `Struktur` box, reusing WordPress media selection and the existing archive-object picker when available;
  - taught legacy content import to preserve WordPress image/media blocks as `media_refs` without storing local dev-host thumbnail URLs;
  - imported post `13349` into `_iss_content_json` as a one-section `event.vortrag` document with one media ref and added `ops/sql/2026-06-24-veranstaltung-13349-content-json.sql`;
  - left public rendering on legacy `post_content` and left the remaining `25808` dynamic `industriesalon/field` block for editor review.
- Completed the local Veranstaltung entity migration checkpoint:
  - curated all remaining Veranstaltung posts so `wp iss-content veranstaltungen-dry-run` now reports `25 safe`, `0 review`, `0 blocked`, and `25 converted`;
  - normalized missing or partial date facts using explicit curator input where needed and recorded inference notes on fallback dates;
  - moved post `25763` from `veranstaltung` to `fuehrung` as `Sonderführung` with on-demand Führung meta;
  - added the transfer artifact `ops/sql/2026-06-23-veranstaltungen-entity-migration.sql` for the source-state post/postmeta/taxonomy changes, with occurrence and graph/search projections regenerated after import.
- Added the seventeenth Veranstaltung entity-registry slice:
  - registered the `_iss_content_json` structured-content shell and hidden `_iss_skin_override` meta for Veranstaltungen;
  - added `wp iss-content veranstaltungen-content-audit` / `wp iss-content-model veranstaltungen-content-audit`;
  - kept stored documents optional and read-only for now, with no content import or renderer switch.
- Added the sixteenth Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-query-audit` / `wp iss-content-model veranstaltungen-query-audit`;
  - moved the theme menu's next-event data lookup behind the `iss-content` Veranstaltung repository;
  - made raw Veranstaltung post queries fail the audit outside the approved repository and curation tooling.
- Added the fifteenth Veranstaltung entity-registry slice:
  - registered normalized query fact meta for `_iss_datetime_start`, `_iss_datetime_end`, and `_iss_published_at`;
  - added save/set-entity synchronization from existing Veranstaltung date facts;
  - made required-fact validation prefer normalized facts while preserving legacy fallbacks.
- Added the fourteenth Veranstaltung entity-registry slice:
  - added a shape-aware Veranstaltung repository facade for upcoming, archive, reports, homepage teasers, related, and menu-event lookups;
  - filtered converted posts by `_iss_entity_key` through registry-derived primary surfaces;
  - kept unconverted legacy posts on the existing menu fallback only, so public behavior remains stable until curation.
- Added the thirteenth Veranstaltung entity-registry slice:
  - expanded the Veranstaltung registry with per-entity domain, post type, icon, and field contracts;
  - validated field names, field types, relation targets, and required shape facts in `veranstaltungen-registry-check`;
  - kept the new contract read-only for future generated authoring, with no renderer or content migration change.
- Added the twelfth Veranstaltung entity-registry slice:
  - taught the graph offer contract to prefer `_iss_entity_key` when set on Veranstaltungen;
  - added an `event_report` offer subtype for `report.rueckblick`;
  - kept unset legacy Veranstaltungen on the existing `_iss_event_layout` / `_iss_event_format` subtype fallback.
- Added the eleventh Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-registry-check` / `wp iss-content-model veranstaltungen-registry-check`;
  - exposed a standalone schema/entity/shape validation command for CI, staging, and pre-renderer checks.
- Added the tenth Veranstaltung entity-registry slice:
  - added `--status`, `--entity`, and `--missing-facts` filters to `veranstaltungen-dry-run`;
  - made review, blocked, converted, and entity-specific curation queues available without exporting the full audit table.
- Added the ninth Veranstaltung entity-registry slice:
  - extended `veranstaltungen-dry-run` table and JSON output with a guarded `set_command` recommendation per candidate;
  - kept generated commands dry-run by default by omitting `--yes`, so copied recommendations still validate before writing.
- Added the eighth Veranstaltung entity-registry slice:
  - exposed selected Veranstaltung `Typ` on singular event pages as stable theme body classes;
  - added `iss-event-entity-*`, `iss-event-shape-*`, and `iss-event-surface-*` only when `_iss_entity_key` is set;
  - left unset legacy posts on the existing layout/scheme/format body-class contract with no CSS or renderer changes.
- Added the seventh Veranstaltung entity-registry slice:
  - added a read-only legacy-derived Typ suggestion inside the Veranstaltung metabox;
  - centralized the suggestion helper so the editor hint and `veranstaltungen-dry-run` share the same confidence and conflict logic;
  - kept suggestions non-authoritative: editors still choose and save `_iss_entity_key` deliberately.
- Added the sixth Veranstaltung entity-registry slice:
  - added a `Typ` column to the Veranstaltung admin list table;
  - added a list-table filter for concrete Veranstaltung entity keys and unset posts;
  - kept the filter read-only over `_iss_entity_key`, giving editors a curation dashboard without changing public rendering.
- Added the fifth Veranstaltung entity-registry slice:
  - centralized Veranstaltung required-fact checks in the registry helpers;
  - made the Veranstaltung status box shape-aware once `Typ` is set, so event types check their date facts while `report.rueckblick` checks publication instead of event start;
  - switched the curation CLI to the same required-fact helper used by the editor status.
- Added the fourth Veranstaltung entity-registry slice:
  - added `wp iss-content veranstaltungen-set-entity` / `wp iss-content-model veranstaltungen-set-entity` as the controlled write path for curated `_iss_entity_key` assignment;
  - kept the command dry-run by default, requiring `--yes` to write and warning/blocking on missing required facts unless `--force` is explicit;
  - wired successful writes to `iss_occurrences_sync_source()` when available, so setting `report.rueckblick` clears event occurrence rows through the normal provider path.
- Added the third Veranstaltung entity-registry slice:
  - taught the Veranstaltung occurrence provider to honor `_iss_entity_key` once set;
  - kept unset legacy Veranstaltungen on the existing `iss_programme_enabled` occurrence path;
  - made `report.rueckblick` produce no event occurrence, so saving a converted Rueckblick clears it from upcoming timeline surfaces without changing the single-page renderer.
- Added the second Veranstaltung entity-registry slice:
  - exposed `_iss_entity_key` as an editor-visible `Typ` radio group in the existing Veranstaltung metabox;
  - saved the semantic type independently from legacy `_iss_event_layout`, `_iss_event_format`, and `_iss_event_scheme`;
  - added the type to the Veranstaltung status box while leaving current rendering, occurrence sync, and timeline behavior unchanged.
- Added the first Veranstaltung entity-registry slice:
  - registered a read-only Phase 1 entity/shape contract in `iss-content` with `_iss_entity_key` validation but no content migration;
  - added `wp iss-content veranstaltungen-dry-run` / `wp iss-content-model veranstaltungen-dry-run` to map legacy layout/format metadata to candidate event/report entities;
  - verified the local dry-run result: 26 Veranstaltungen, 0 safe auto-conversions, 15 review candidates, 11 blocked by missing `iss_start_datetime`.
- Added the compressed `Röhren für die Republik` Ausstellung JSON candidate:
  - saved and enabled the local `_iss_editorial_ausstellung` document for post `21108` with the `industrieakte` skin and 16 sections;
  - added source-backed fact-scale sections for Leningrad T2, research-file gaps, and the tube-to-semiconductor transition;
  - wrote paired transfer SQL at `ops/sql/2026-06-23-roehren-republik-editorial-json.sql`;
  - widened `industrieakte` `massstab` facts into a responsive grid for desktop/mobile review.
- Converted local `Kinder im Werk` to a curated Ausstellung JSON document:
  - registered the `kinder-im-werk` JSON skin and added its theme-owned stylesheet;
  - saved and enabled the local `_iss_editorial_ausstellung` document for post `26381`;
  - styled the skin's hero suppression, source excerpts, typographic quote, object-focus cards, album copy order, and stat gesture with existing shared/typographic treatments;
  - wrote paired transfer SQL at `ops/sql/2026-06-23-kinder-im-werk-editorial-json.sql`.
- Added a footer-oriented `register` layout variant for graph related-content rails and switched the single-Ausstellung tail to it.
- Replaced the single-Ausstellung tail from Ausstellung-only `iss/related-cards` to graph-backed mixed `iss/related-content`, and synced the local DB-backed template override.
- Moved Ausstellung practical metadata into the single-Ausstellung hero:
  - relocated the existing `iss/content-meta` block from the post-content intro slot into the hero overlay;
  - styled the existing `iss-info-panel--skin-aside` output as a compact dark hero facts rail without duplicating metadata markup;
  - synced the local DB-backed `single-ausstellung` template override to match the file template for immediate local review.
- Exposed Ausstellung editorial skin assignment in the custom composition editor:
  - added an `iss_editorial_format_skins` hook so the theme can provide allowed skins for a format;
  - registered the current Ausstellung skins from the theme owner;
  - added a single `Darstellung` selector that writes the document `skin` while keeping layout, variant, and section-role controls out of the editor.

## 2026-06-22

- Added phone/camera capture to the one-off Event Drop Uppy intake snapshot:
  - wired Uppy `Webcam` into the existing inline Dashboard/XHRUpload flow;
  - enabled native mobile/tablet camera capture for photo/video uploads;
  - kept the same `/event-drop/` upload endpoint, `media` field, moderation storage, CSV manifest, and WordPress bridge contract.
- Fixed the theme off-canvas menu shell after broad CSS changes:
  - capped the fixed shell to the visible viewport with safer viewport-unit fallbacks;
  - reset WordPress block-gap margins inside the shell so the menu no longer renders at roughly 1.5 viewport heights in Firefox;
  - distributed the Institution/Entdecken/Archiv sections evenly through the available menu height;
  - pinned the Heute/next-event status strip to the bottom of the menu viewport.
- Signed off Phase 1 of the Ausstellung editorial-platform SOW for authoring/save/reload after editor roundtrip proof:
  - kept autosave recovery deferred by decision;
  - renamed the explicit structure action from `JSON speichern` to `Speichern`;
  - styled the explicit structure save as a red editor-canvas button;
  - added a post-save reminder that JSON section content is updated by that button, while WordPress `Aktualisieren` is only needed for WordPress-owned fields such as title, slug, status, taxonomies, or other metabox data.
- Finalized Phase 2 for the current Ausstellung pilot boundary:
  - kept archive-object selection on the existing bucket-first archive picker and media selection on WordPress `wp.media`;
  - changed selected archive-object chips to show human labels with explicit remove buttons instead of clickable raw/fallback identifiers;
  - made archive-object selection refresh the canvas and schedule persistence immediately after picker confirmation;
  - recorded the SOW-wide Relation Picker as deferred until entity-editor work needs it.
- Restored Ausstellung decision/research links in the JSON editor path:
  - added a supported `links` field for `schluss` and `aside` gestures;
  - exposed repeatable link editing in the composition modal;
  - rendered section links as a theme-owned button rail;
  - taught the migration helper to preserve legacy Gutenberg navigation links;
  - updated the `Frauen im Werk` local JSON candidate and transfer SQL with the five legacy links.
- Advanced the editorial-platform SOW Phase 3 skin decision for the Ausstellung pilot:
  - kept skin, variant, layout, and section-role choices out of the main editor canvas so editors only add, edit, save, and reorder content sections;
  - changed the editor save path to preserve the internal JSON-rendering rollout flag without exposing it as a normal authoring control;
  - added theme-owned JSON skin resolution for `frauen-im-werk`, with reusable layout classes and the first `gesture x skin` treatments;
  - extracted the first `frauen-im-werk` treatment CSS into a dedicated theme skin stylesheet that loads only when the enabled editorial document resolves to that skin;
  - changed the default JSON renderer to emit universal section slots for media, copy, kicker, body, quote, and refs so skins can express live station-like treatments without one partial per gesture;
  - made `kicker` a first-class JSON section field in storage, editor UI, migration import, and theme rendering;
  - corrected the first `frauen-im-werk` source-station treatment to belong to the `quellenauszug` gesture, while `objektfokus` stays reserved for archive-object grids;
  - styled the `objektfokus` gesture as a dedicated dark archive-object grid for the `frauen-im-werk` skin, using the existing archive-card renderer with responsive two-column/one-column behavior;
  - adapted `bildstrecke` and `massstab` for the `frauen-im-werk` skin from the existing `Kinder im Werk` album/stat patterns, keeping the shared JSON renderer slots and avoiding new editor controls;
  - added two additional theme-owned editorial JSON skins, `typografisch` and `chronik`, as conditional Ausstellung stylesheets over the same universal gesture/layout classes for later real-content testing;
  - added the `industrieakte` editorial JSON skin for technical/industrial Ausstellung series, translating the WF/Röhren dossier mockup into theme-owned gesture treatments without copying its inline dashboard markup;
  - added a scoped `quellenauszug` text-position flip so source stations can render with text left or text right in the `frauen-im-werk` skin;
  - added quiet typographic treatments for existing `kapitel`, `leitfrage`, `zitat`, and `fliesstext` gestures using the generic renderer slots and CSS-derived chapter numbering, without adding parallel JSON fields or per-gesture partials;
  - made `vollbild` a generic one-image full-viewport treatment with a 16:9 editor hint, media-dimension refs, first-image renderer fallback, and dark ink-panel overlay;
  - added `ops/sql/2026-06-22-frauen-im-werk-editorial-json.sql` as the narrow transfer artifact for the local JSON pilot document and enabled flag;
  - added the SOW partial lookup path for future theme overrides before falling back to generic section rendering;
  - promoted the Ausstellung vocabulary with `vollbild`, `fliesstext`, and `schluss`, then migrated the local `Frauen im Werk für Fernmeldewesen` JSON pilot from generic `bildstrecke` sections to concrete poster-essay gestures while keeping its local JSON rendering enabled for review.

## 2026-06-21

- Added a shared theme button primary tier: `style.css` now exposes solid primary button tokens plus `.iss-button` variants and maps Gutenberg `is-style-fill` buttons to the solid tier, while the programme timeline skin uses the same primary tokens for booking actions.
- Reworked the public `/kalender/` wide-viewport skin: the file-backed calendar template now wraps the existing occurrence-backed `industriesalon/timeline-query` block in a theme-owned workbench, moves the block's existing filters into a left rail on wide screens, caps the result column, collapses recurring tour groups by default, hides oversized listing media on the calendar surface, and adds a right rail linking exhibitions back to the separate availability browser.
- Adjusted programme timeline kickers to set the shared kicker accent/color variables so the text, left rule, and dot follow the active timeline scheme together.
- Added the first `iss-editorial` vertical slice for the finalized editorial-platform SOW:
  - introduced an engine-only plugin for versioned editorial JSON documents, autosave, format/section registration, typed references, and normalized read models;
  - wired `iss-content` to opt `ausstellung` into the initial `OrderedFormat` pilot while preserving existing Gutenberg/meta content as the default fallback;
  - added archive-object picker integration for object-reference sections without exposing raw IDs to editors;
  - added theme-owned Ausstellung JSON rendering behind a per-post feature flag, with unresolved references omitted publicly and shown as preview/editor placeholders;
  - replaced the temporary Gutenberg sidebar/meta-box editorial UI with a custom main-canvas composition editor for `ausstellung`, using palette gestures, ordered section cards, and modal section editing;
  - added media selection to the composition modal via the WordPress media library, imported legacy exhibition images as `bildstrecke` media refs, added editable captions/removal controls, and rendered resolved media refs in the theme-owned JSON output;
  - normalized the archive-object picker toward the media-picker workflow: it now opens as a modal, starts from attached/context Archivset buckets, shows bucket members as thumbnail cards, keeps faceted object search as the secondary fallback, and preserves selected bucket/member provenance in editorial references;
  - stabilized the archive picker modal for editor use with inline/modal mode separation, bucket-first search guards, readable global-search result cards, fixed result scrolling, and theme-red selected-object accents;
  - added a REST save route for reviewed editorial JSON documents and their per-post enabled flag;
  - added a read-only `wp iss-editorial ausstellung-dry-run` report for `Kinder im Werk` and `Frauen im Werk` before any permanent JSON-rendering switch;
  - added `wp iss-editorial ausstellung-import-candidate --post=<slug-or-id>` and imported a disabled local JSON candidate for `Frauen im Werk für Fernmeldewesen` with 7 sections and 6 media refs while leaving public Gutenberg rendering active;
  - fixed editorial JSON meta writes to slash encoded JSON before `update_post_meta()`, preserving paragraph breaks and other escaped content through WordPress meta storage;
  - added `iss-editorial` to PHPStan paths/scanFiles so the repo's per-file target runner can see the new plugin symbols without include-file workarounds;
  - documented the boundary in `docs/architecture/editorial-platform.md` and added `iss-editorial` to the plugin map.
- Reworked static map framing for front-page and Führungen landing surfaces:
  - added a full-size baked `-17deg` Spree-horizontal derived map plus matching marker projection JSON;
  - registered `spree-horizontal-17` as a reusable theme map preset while keeping the unrotated canonical map as the source reference;
  - switched front page and `/fuehrungen/` spine strips to the baked preset with page-specific vertical crop and 1.14 cover zoom;
  - simplified spine-strip overlays so map markers carry the spatial meaning and the direction rail stays decorative;
  - hid raw rotation/horizontal-bias controls from the editor, kept zoom up to 200%, and exposed vertical crop as the author-facing framing control.

## 2026-06-15

- Made `register_place` public editor image groups authoritative for frontend featured-image rendering: public featured/fallback image-group selections now win through the WordPress thumbnail filter and save-time sync updates `_thumbnail_id` when the selected public image changes.
- Audited `/fuehrungen/elektropolis-tour/` route media behavior: the `iss/tour-route` block renders place-level public `archive_images`/`current_images` as station figures and keeps `station_object_id` as a separate detail card, so missing “Damals” images on that route are currently caused by private/missing place image-group data rather than a PHP render failure.
- Polished the `/ausstellungen/` browser interaction: added visible result summaries, debounced live search, a no-JS-capable clear-search link, filter URLs that preserve the current search term, and responsive control styling for the exhibition page skin.
- Closed the Atlas/static-map cleanup with a public-surface audit: first-class surfaces remain `iss/related-place-map`, `iss/atlas-slice`, `iss/spine-strip`, and `iss-register/schoneweide-atlas`; experimental static surfaces stay inserter-hidden; broader archive/graph API consolidation is deferred until a concrete consumer exists.
- Added fullscreen and kiosk layout states to the existing Schöneweide Atlas block: the Atlas now exposes embedded/fullscreen/kiosk controls, keeps the same REST/render path, invalidates Leaflet sizing on mode changes, and resets filters/map view after kiosk idle.
- Added focused contract/schema checks for Atlas REST payloads and static-map inputs: `iss-register contract-check` now validates Atlas place/context schema, and new `wp iss-relations static-map-contract-check` validates first-class map-block contracts plus static-map relation result/DTO shape.
- Finished the interactive Atlas runtime module split by extracting marker icon and map-marker orchestration into `atlas/markers.js`, leaving `schoneweide.js` as the bootstrap coordinator for config, payload loading, state creation, and module calls.
- Split the remaining interactive Atlas DOM-heavy renderers into focused modules:
  - added `atlas/detail.js` for popup/detail rendering and place media figures;
  - added `atlas/stories.js` for story intro, story cards, and fallback place cards;
  - added `atlas/relations.js` for relation rail rendering and mini-map projection;
  - wired the new detail/story/relation script handles before the existing public Atlas view handle, leaving the main runtime focused on bootstrap and marker/map orchestration.
- Split the interactive Schöneweide Atlas place/filter UI out of the main runtime:
  - added `themes/industriesalon/assets/js/atlas/places.js` for filter buttons, filter labels/counts, search/reset bindings, root filter attributes, summary/count rendering, and selected-place render context;
  - wired the new `iss-register-schoneweide-atlas-places` script between the store module and the existing public Atlas view handle;
  - left marker rendering, popup/detail UI, story cards, and relation rails in `schoneweide.js` for the next focused split.
- Continued modularizing the interactive Schöneweide Atlas runtime:
  - extracted payload normalization, derived era/actor maps, filter/selection state, selected story resolution, and relation scoring into `themes/industriesalon/assets/js/atlas/store.js`;
  - wired the new `iss-register-schoneweide-atlas-store` script before the existing public Atlas view handle;
  - kept the visible block, REST payloads, map adapter, and DOM rendering behavior unchanged while leaving place/filter UI and detail/story/relation rendering as the next split.
- Began modularizing the interactive Schöneweide Atlas runtime without changing the public block or REST payload:
  - extracted shared Atlas runtime core utilities, provider selection, payload/config loading, resize/layout sync, and the Leaflet map adapter into ordered theme JS modules;
  - kept the existing `iss-register-schoneweide-atlas-view` handle as the public view entrypoint with the new modules as dependencies;
  - left place filtering/list UI, detail/popup rendering, and story/relation panels in the main runtime for the next split.
- Defined the static-map DTO boundary between `iss-relations` and `iss-frontend`: block place selection now normalizes into a relation result with ordered static-map place DTOs, and `docs/architecture/static-map-rendering.md` records the contract keys.
- Split the `iss-relations` related-content block editor script into focused modules for editor context, place-source controls, related-card controls, static-map controls, spine-strip controls, and editorial-signal controls while keeping the existing block names, render callbacks, and `iss-relations-related-blocks` editor handle stable.
- Froze the first-class static map block surface:
  - kept `iss/related-place-map`, `iss/atlas-slice`, and `iss/spine-strip` as normal inserter-visible map blocks;
  - kept `iss/atlas-strip` and `iss/asymmetric-split-field` render-compatible but hidden from the inserter as experimental/non-current public surfaces.
- Documented static marker provenance and the manual marker update verification path in `docs/architecture/static-map-rendering.md`.
- Split static map block responsibilities:
  - added a shared `iss-relations` map-block source contract used by PHP defaults, render resolution, and editor settings;
  - moved static marker lookup, projection math, focus-window calculation, stage rendering, panel rendering, and static map frontend rendering entry points into `iss-frontend/modules/static-maps`;
  - kept thin compatibility wrappers in `iss-relations` while block callbacks delegate to the frontend renderer;
  - fixed map-block source resolution so implicit manual `placeIds` no longer depend on an out-of-scope rendered block object;
  - added `wp iss-relations map-block-audit` to check DB and file-backed static map blocks, selected-place marker resolution, and public coordinate-bearing `register_place` marker coverage against the same contract;
  - added missing derived static markers for published coordinate-bearing places that were absent from `schoneweide-static-markers-new.json`;
  - kept `industriesalon-schoeneweide-register` as the `register_place` and interactive Atlas data/cache owner;
  - documented the cleanup pattern in `docs/architecture/static-map-rendering.md`.
- Added the Atlas/static-map implementation plan in `docs/architecture/atlas-static-map-implementation-plan.md`, merging the local audit and peer review into one durable architecture plan.

## 2026-06-14

- Added conditional project status rendering:
  - introduced `iss/project-status` for project lists;
  - renders date ranges from project start/end meta when present;
  - falls back to completed state after end date, taxonomy status, then period label.
- Reconciled current editor/template drift:
  - copied current front-page text edits into the file template and removed its DB override;
  - flushed the current `page-projekte` DB template body to disk while leaving the DB override in place for later deletion.
- Moved `iss/dense-image-wall` to `iss-frontend`:
  - kept the block name stable for existing content;
  - split editor workflow into composition and text/link modes;
  - moved baseline block CSS out of the theme;
  - hardened render output for class and URL handling.
- Fixed booking CTA modal loading for tour/calendar surfaces by enqueueing the shared programme script where slot triggers render.
- Added transfer artifacts for the Walk of Fame dense wall content:
  - `ops/sql/2026-06-14-walk-of-fame-dense-wall.sql`;
  - `ops/uploads/2026-06-14-walk-of-fame-dense-wall-media.tar.gz`;
  - matching manifest and SHA256 files.
- Added transfer artifacts for the current `projekt` single-page content edits:
  - exports all seven published `projekt` posts plus their postmeta and term relationships;
  - normalizes local dev URLs in project content to root-relative paths;
  - pairs the SQL with a 28-file upload archive for directly referenced project media.

## 2026-06-13

- Compacted repository documentation:
  - reduced `handoff_CURRENT.md` and `TODO.md` to current operational checkpoints;
  - collapsed the content model architecture notes into `docs/architecture/entity-model.md`;
  - removed stale planning/audit documents and local backup archives;
  - kept `docs/project/decisions.md` and `docs/project/backlog.md` as the durable project record.
- Finalized occurrence/programme cleanup:
  - made `iss-occurrences` the owner of occurrence projection, public query readiness, recurrence grouping, and SuperSaaS ingestion;
  - removed graph/entity ID coupling from occurrence rows;
  - removed frontend dependencies from occurrence query helpers;
  - kept `iss-programm`/`iss-frontend` rendering-oriented.
- Reorganized plugin domains:
  - renamed functional plugin folders toward `iss-content`, `iss-frontend`, `iss-commerce-lite`, and `iss-graph`;
  - refreshed active-plugin SQL artifacts and removed obsolete plugin-folder references from docs.
- Hardened public request surfaces:
  - added rate limiting, body-size guards, nonce support, timing checks, honeypot handling, and light spam rejection logging for public commerce endpoints;
  - moved request/order storage from capped options to plugin-owned tables.
- Cleaned editorial calendar visibility:
  - renamed the UI concept from technical timeline wording to programme/calendar wording while preserving migration compatibility;
  - kept projects opt-in for programme projection;
  - made permanent and digital exhibitions opt-in for programme/calendar visibility.
- Continued graph/facade cleanup:
  - added graph hygiene checks for orphan membership edges, bad relation years, and duplicate alias tokens;
  - preserved typed relationship semantics for employment, construction/design, membership, and founding links;
  - kept consumer discovery read-only through facade/query helpers.
- Verification:
  - ran local PHP syntax checks for touched plugin/theme files;
  - ran focused PHPStan/PHPCS checks where configured;
  - ran WP-CLI drift, active-plugin, and facade/offer-consumer smoke checks where relevant;
  - ran `git diff --check`.

## 2026-06-12

- Built graph editorial signals and search influence:
  - added rating/confidence/visibility controls for canonical graph entities and relationships;
  - added materialized influence rows and search-weight support;
  - added admin export and CLI reporting for graph hygiene.
- Prepared and applied graph hygiene artifacts:
  - canonicalized WF/Industriesalon and KWO/AEG organization data;
  - backfilled alias replay artifacts;
  - removed orphan or semantically incorrect graph edges.
- Added facade and consumer guardrails:
  - kept offer consumers on read-only query helpers;
  - added route/audit tooling for old offer/availability consumers;
  - documented facade ownership in architecture docs.
- Moved tour/public rendering ownership:
  - collapsed Führung public templates back to the theme;
  - cleaned old page-template overrides and tour-template SQL artifacts;
  - added guards around SuperSaaS occurrence reactivation and legacy occurrence-origin purge.
- Retired legacy public-read routes and stale runtime surfaces:
  - removed old REST compatibility paths;
  - kept compatibility only where required for migration or local verification.
- Rewrote history to remove obsolete production newsletter SQL from the active branch.
- Verification included PHP syntax checks, WP-CLI smoke checks, PHPCS/PHPStan passes for touched slices, and `git diff --check`.

## 2026-06-11

- Established the greenfield content/facade checkpoint:
  - set `iss-content` as the editorial source;
  - kept public presentation in the theme and data/business contracts in plugins;
  - documented the source-of-truth architecture in `docs/architecture/entity-model.md`.
- Separated exhibitions from programme/calendar projection:
  - introduced strict programme opt-in for exhibitions/projects;
  - excluded availability-only exhibition types from automatic calendar projection;
  - added SQL artifacts for availability cleanup, strict programme toggle backfill, and legacy programme meta purge.
- Introduced `iss-occurrences` as the durable occurrence projection service:
  - replaced hidden calendar runtime behavior;
  - added occurrence admin/CLI tooling and public query helpers;
  - began migration away from legacy `iss_calendar_item` assumptions.
- Added first read-only availability/entity relation/offer facade slices.
- Verification included WP-CLI source checks, occurrence smoke tests, SQL artifact dry-runs, and PHP syntax checks.

## 2026-06-10

- Reworked key exhibition pages and transfer artifacts:
  - redesigned Frauen im Werk and Kinder im Werk content structures;
  - added related-content and care/skin improvements;
  - prepared media and SQL transfer artifacts under `ops/sql/` and `ops/uploads/`.
- Added SuperSaaS admin bootstrap and began tour availability integration work.
- Improved archive/publication page behavior and related-card presentation.
- Verification included local route checks, WP-CLI template/meta checks, and artifact validation.

## 2026-06-09

- Tightened the Ausstellung plugin/theme contract:
  - clarified plugin data ownership and theme presentation ownership;
  - removed hidden shortcode-style rendering expectations;
  - improved exhibition archive/editor behavior.
- Continued related-card and carousel contract cleanup.
- Verification focused on archive rendering, editor compatibility, and PHP syntax checks.

## 2026-06-08

- Improved exhibition editor controls and archive semantics:
  - added reusable editorial metadata controls;
  - refined exhibition visibility and type handling;
  - improved block/editor alignment for exhibition content.
- Continued CSS contract cleanup around archive/card rendering.

## 2026-06-07

- Audited archive, graph, project, publication, and video ownership:
  - kept public UI in the theme;
  - kept content contracts in plugins;
  - preserved Classic Editor policy unless explicitly opted into Gutenberg.
- Salvaged and restructured selected Kinder/Project content.
- Added and refined publication/video landing and timeline authoring work.

## 2026-06-06

- Added Sammlungen media/template transfer artifacts:
  - created SQL and upload delta artifacts for media sync;
  - adjusted public template behavior for collection pages.
- Migrated Ausstellung backend metadata:
  - added SQL cleanup/migration artifacts;
  - aligned backend metadata with frontend requirements.

## 2026-06-05

- Established the repository documentation model:
  - current checkpoint in `handoff_CURRENT.md`;
  - durable history in `CHANGELOG.md`;
  - active follow-up in `TODO.md`;
  - detailed operational docs under `docs/`.
- Prepared Repair Cafe and Sammlungen media/content transfer artifacts.
- Added operational closeout conventions for staging/local exchange.

## 2026-06-04

- Prepared staging content delta artifacts and supporting transfer notes.
- Continued front-page, archive, and template-authority checks.

## 2026-06-03

- Prepared production transfer artifacts for front-page and video transcript sync.
- Fixed front-page/menu-shell/newsletter rendering details.
- Added featured-image fallback behavior and event-template improvements.

## 2026-06-02

- Added local video transcription workflow support for the `video` CPT.
- Improved video block metadata and editor compatibility.

## Older History

- Added `veranstaltung` scheme/meta support and Terminblatt v1 templates.
- Built `register_place` atlas-led dossier templates and supporting patterns.
- Added `projekt` single-shell work and chaptered-prose planning.
- Added brochure/photoalbum publication templates and related-content rails.
- Built publication/video landing and reusable publication timeline authoring.
- Added shared related-content carousel/card contracts.
- Recovered and converted local media assets as needed for theme work.
- Introduced and evolved the ISS content-model, archive, graph, programme, and
  commerce/plugin stack now summarized in the architecture docs.
