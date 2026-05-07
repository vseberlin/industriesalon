# Handoff Current

## Status
- `in progress`

## Date / Window
- Date: `2026-05-07`
- Timezone: `Europe/Berlin`

## Branch / Commit
- Branch: `master`
- HEAD at start of this pass: `9759d82`

## What Was Done This Session
- Finished the `Menschen im WF` evening museum-digital reconciliation as an update-first rescue instead of a fresh-import run:
  - fixed fallback identity handling in `plugins/iss-wf-import/includes/museum-digital-importer.php`
  - merged seed-row metadata back into weak museum-digital fallback payloads when title/inventory were missing
  - stopped duplicate scans from running on empty title/inventory records
  - completed the full `721`-row source pass in safe update batches with `0` creates and `0` errors
- Confirmed the `Menschen im WF` remaining tail is no longer a bulk-import blocker:
  - safe `remaining` runs now leave only a tiny duplicate residue instead of a large unresolved corpus
  - the local `/menschen-im-wf/` landing remains the right public entry surface while editorial review continues
- Continued the archive/public wording cleanup so Industriesalon ownership is clearer than external hosting traces:
  - public archive wording now favors `Bestand` and `Digitale Nachweise`
  - highlighted `museum-digital`, `DDB`, and `Europeana` as public portals instead of treating them as ownership/source labels
- Built the new dense `/sammlungen/` discovery landing in the theme as a separate safe page/template with isolated CSS:
  - `themes/industriesalon/templates/page-sammlungen.html`
  - `themes/industriesalon/assets/css/page-sammlungen.css`
  - compact switchboard intro
  - denser second-section three-panel logic
  - epoch cards and archive-discovery pathways
  - synced the final edited template back to disk after Gutenberg DB override creation
- Tightened `/archiv/` into the stricter research surface beside `/sammlungen/`:
  - compact archive intro/orientation band
  - denser sidebar/results layout
  - fixed sidebar width and native control overflow behavior
- Added archive/object UI wording and taxonomy improvements:
  - archive object cards now use family/thematic kicker terms instead of the generic `Archivobjekt`
  - single archive objects and collections surface public portal context more clearly
- Expanded the exhibition/publication twin model with additional WF corpora and technical longreads:
  - `Geschichte des WF` exhibition + publication
  - `Röhren für die Republik` exhibition + publication, explicitly marked as an incomplete first secured run
  - `Fundstücke aus dem Landesarchiv Berlin` exhibition + publication
  - standalone longread publications:
    - `Farbfernsehen in der DDR schon vor dem Mauerbau?`
    - `Fundstücke zur Geschichte des NEF im Archiv des Industriesalons`
  - umbrella exhibition:
    - `Elektrotechnik im WF`
- Fixed source-content defects discovered while promoting corpora:
  - repaired broken slugs in `Aus der Geschichte des WF` chapters
  - normalized obvious `Röhren für die Republik` title/slug defects (`Folge 11`, `Folge 20`, `Folge 24`)

## Verification
- Active theme rechecked: `industriesalon`
- Relevant plugins repeatedly verified live during this pass:
  - `iss-wf-import`
  - `iss-content-model`
  - `iss-publications`
  - `iss-relations`
  - `industriesalon-schoeneweide-register`
- `Menschen im WF` importer pass verified through full WP-CLI batch summaries:
  - `721` discovered source rows covered
  - `0` creates
  - `0` errors
  - only a minimal duplicate residue left in safe `remaining` mode
- New publication/exhibition URLs verified live:
  - `/ausstellungen/geschichte-des-wf/`
  - `/publikationen/geschichte-des-wf-eine-entwicklungsgeschichte/`
  - `/ausstellungen/rohren-fur-die-republik/`
  - `/publikationen/rohren-fur-die-republik-eine-technikgeschichte/`
  - `/ausstellungen/fundstucke-aus-dem-landesarchiv-berlin/`
  - `/publikationen/fundstucke-aus-dem-landesarchiv-berlin-eine-quellengeschichte/`
  - `/publikationen/farbfernsehen-in-der-ddr-schon-vor-dem-mauerbau/`
  - `/publikationen/fundstucke-zur-geschichte-des-nef-im-archiv-des-industriesalons/`
  - `/ausstellungen/elektrotechnik-im-wf/`
- `/sammlungen/` verified live after template sync-back to disk
- `/archiv/` verified live after compact-research-page refinements

## Important Notes
- `Menschen im WF` is now functionally rescued as a reconciled local corpus rather than an importer problem.
- The remaining work there is editorial/data review, not bulk mechanics.
- `Röhren für die Republik` is intentionally published as an incomplete first secured run:
  - present: `11–13`, `18–48`, `50`
  - missing: `1–10`, `14–17`, `49`
- `Geschichten vom Herrn A. (nicht K.)` remains partial locally:
  - current local coverage is only `Folge 1`, `Folge 2`, and `Folge 5`
  - do not promote it to `ausstellung` yet
  - only consider a selection-style publication after missing parts `3` and `4` are verified locally or recovered
- `Elektrotechnik im WF` is now a real editorial umbrella, but it is still a first-stage technical program rather than a finished refined museum section.

## Current Worktree
- Pending commit at handoff time:
  - full theme/plugin worktree from the session, including archive/archive-object, publications, relations, content-model, and Schöneweide register changes

## Next Recommended Steps
- Review and refine the new technical/editorial shells instead of creating more structure blindly:
  - `Elektrotechnik im WF`
  - `Röhren für die Republik`
  - `Geschichte des WF`
  - `Fundstücke aus dem Landesarchiv Berlin`
- Decide whether the next pass should be:
  - editorial cleanup of rough chapter bodies/slugs/notes
  - relation enrichment to places/Atlas/tours
  - or visual refinement of the new archive/publication/exhibition landing surfaces
- Keep `Geschichten vom Herrn A. (nicht K.)` on the editorial review list only after missing parts are recovered

## Todo: Menschen im WF Follow-up
- `Menschen im WF` is no longer blocked on bulk importer mechanics.
- Remaining tasks there are editorial:
  - inspect the tiny duplicate residue
  - refine local taxonomy/family assignments where the corpus is still rough
  - decide whether stronger sub-landings or thematic exhibitions should grow out of that collection

## Continuity Prompt
- Start next session with: `read /home/vladimir/wp/handoff_CURRENT.md`
