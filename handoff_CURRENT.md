# Handoff Current

## Status
- `paused at clean checkpoint`

## Date / Window
- Date: `2026-05-08`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD at start of this pass: `9759d82`

## What Was Done This Session
- Promoted WF technical material from `wf-museum.de` into stronger local public surfaces instead of leaving it as scattered imported objects:
  - rebuilt `Elektrotechnik im WF` as a denser editorial gateway in WordPress content
  - created new `ausstellung` `Betriebsfotoalben im WF` (`ID 21128`) as the umbrella for the four existing album publications plus their archive collections
- Confirmed the structural boundary for the technical archive:
  - `publication` remains the readable/interpretive layer
  - `ausstellung` remains the curated entry layer
  - `archivobjekt` remains the source-of-truth object layer
  - `WF-Technik` should grow as a taxonomy-led technical corpus, not as many parallel longreads
- Extended the technical archive/browser system in `iss-wf-import` so the remaining WF-Technik sections can scale:
  - added nested route normalization for:
    - `/geraete-einschuebe-bauteile/`
    - `/telekommunikation-sende-und-fernsehtechnik/`
    - `/diverses-gebaeude-schaltbilder-etc/`
  - extended editor-facing archive taxonomy vocabulary with additional fields/families/contexts for:
    - devices/components
    - telecommunication/television
    - buildings/work environment
    - schematics/reproductions
  - added new classifier profiles in `plugins/iss-wf-import/includes/museum-digital-importer.php`:
    - `geraete-bauteile`
    - `telekommunikation-fernsehtechnik`
    - `diverses-gebaeude-schaltbilder`
  - hardened the CLI importer so large WF-Museum seed pages fall back from `discover_seed_rows()` to raw object-id discovery when row extraction comes back empty
- Added three new page-owned browser landings in the active theme:
  - `themes/industriesalon/templates/page-geraete-einschuebe-bauteile.html`
  - `themes/industriesalon/templates/page-telekommunikation-sende-und-fernsehtechnik.html`
  - `themes/industriesalon/templates/page-diverses-gebaeude-schaltbilder-etc.html`
- Created the missing page owners in WordPress content:
  - `Geräte, Einschübe, Bauteile` (`ID 21133`)
  - `Telekommunikation, Sende- und Fernsehtechnik` (`ID 21131`)
  - `Diverses, Gebäude, Schaltbilder` (`ID 21132`)
- Ran chunked museum-digital imports with the now-validated safe pattern:
  - use `--selection=remaining`
  - use `--skip-possible-duplicates`
  - stop long runs at visible checkpoints instead of one opaque full-seed pass
- Brought the technical taxonomy to these checkpoint counts:
  - `Geräte / Bauteile`: `1165`
  - `Telekommunikation / Fernsehtechnik`: `268`
  - `Diverses`: `333`
  - `Gebäude / Werkumfeld`: `21`
  - `Schaltbild / Repro`: `52`

## Verification
- Active theme rechecked: `industriesalon`
- Relevant plugin verified during this pass:
  - `iss-wf-import`
- PHP syntax verified inside the WordPress container:
  - `plugins/iss-wf-import/includes/post-type.php`
  - `plugins/iss-wf-import/includes/museum-digital-importer.php`
- Live page checks passed:
  - `/ausstellungen/elektrotechnik-im-wf/`
  - `/ausstellungen/betriebsfotoalben-im-wf/`
  - `/geraete-einschuebe-bauteile/`
  - `/telekommunikation-sende-und-fernsehtechnik/`
  - `/diverses-gebaeude-schaltbilder-etc/`
- Archive redirect path verified:
  - `/archivobjekte/geraete-einschuebe-bauteile/` redirects to the page-owned landing
- The new browser pages are not empty shells anymore:
  - `Geräte / Bauteile` shows real local object cards
  - `Telekommunikation / Fernsehtechnik` shows relays, senders, meters, cameras, TV equipment
  - `Diverses` now visibly surfaces `Schaltbild / Repro` objects such as the imported `Dunkelschaltbild ...` series

## Important Notes
- The current import workflow is workable and should be continued, but in chunks:
  - giant one-shot runs are technically possible but operationally poor
  - the practical pattern is chunked `remaining` imports with visible checkpoints
- `wf-museum.de/home-2/wf-technik/geraete-einschuebe-bauteile/` is the largest of the remaining technical source pages:
  - source-side object-id discovery showed `1881` links
- `wf-museum.de/home-2/wf-technik/telekommunikation-sende-und-fernsehtechnik/` showed `793`
- `wf-museum.de/home-2/wf-technik/diverses-gebaeude-schaltbilder-etc/` showed `334`
- Database size is still reasonable for continuing toward the larger museum-digital corpus:
  - current `archivobjekt` count: `2779`
  - `wp_posts`: about `34.77 MB`
  - `wp_postmeta`: about `52.52 MB`
  - current archive objects average about `33.52` meta rows per object
- The real scaling risk is not object count alone, but bad query design or uncontrolled attachments. The current browser/taxonomy direction is the right one.

## Current Worktree
- Source-file changes from this pass:
  - `plugins/iss-wf-import/includes/post-type.php`
  - `themes/industriesalon/templates/page-geraete-einschuebe-bauteile.html`
  - `themes/industriesalon/templates/page-telekommunikation-sende-und-fernsehtechnik.html`
  - `themes/industriesalon/templates/page-diverses-gebaeude-schaltbilder-etc.html`
- There are also older unrelated pending worktree changes from earlier sessions in theme/plugin files; do not revert them casually.
- Large parts of this pass also live in WordPress content/database, not only in repo files:
  - updated `Elektrotechnik im WF`
  - created `Betriebsfotoalben im WF`
  - created the three technical page owners
  - imported many new `archivobjekt` records

## Next Recommended Steps
- Continue the same chunked import pattern, not a redesign:
  - `Telekommunikation / Fernsehtechnik` next `remaining` slice
  - then the remaining `Geräte / Bauteile` tail
  - then any still-missing `Diverses` residue
- After enough import coverage, refine the taxonomy semantics where it still drifts:
  - some objects in `Diverses` are still broad and may need sharper family/context assignment
  - `Geräte / Bauteile` will likely need later splits inside the field once the corpus stabilizes
- Only after import coverage is materially stronger, consider new curated surfaces such as:
  - `Röhren und Halbleiter im WF`
  - `Arbeitsplätze und Prüfstände`
  - more device/television-focused exhibitions or publications

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
- Then continue chunked `remaining` imports for the WF-Technik pages, using the new profiles and stopping at explicit checkpoints instead of one-shot full-seed runs.
