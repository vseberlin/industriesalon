# Native Page JSON Landings SOW

Date: 2026-06-30

## Purpose

Move selected landing pages onto the existing editorial JSON engine without
creating a new `landing_page` CPT or replacing normal WordPress page ownership.
The goal is a constrained native-page authoring surface for curated landing
sections while preserving existing URLs, menus, templates, front-page settings,
and fallback behavior.

## Locked Decisions

- V1 does not add a `landing_page` CPT.
- Landing documents stay native WordPress `page` posts.
- Existing URLs, menu assignments, page hierarchy, and the WordPress static
  front-page setting remain source of truth.
- The first allowlist is the front page plus pages with these slugs:
  `about`, `verein`, `salon-vermietung`, `sammlungen`, and `fuehrungen`.
- The front page keeps `front-page.html` as the wrapper/template authority.
- JSON rendering replaces only eligible editable landing sections.
- Disabled JSON, missing JSON, invalid JSON, or an empty section list falls
  back to the current template and `post_content` output.
- `/fuehrungen/` is an explicit completed-cutover exception: its file template
  retains the hero and one inert landing slot, while the paired API migration
  enables the JSON-owned body. Deploy them together; disabling that document is
  a rollback to the hero shell, not to the deleted Query Loop body.
- `/about/` is an explicit completed-cutover exception: its file template keeps
  the masthead and landing slot, while the paired API migration enables the
  JSON-owned body and initial Team order. Disabling the document returns to the
  masthead shell, not to the deleted hardcoded body.
- This docs pass creates no SQL, content migration, or upload artifact.

## Ownership

- WordPress owns native `page` identity, slugs, menus, hierarchy, page status,
  featured image, and front-page assignment.
- `iss-content` owns the landing format registration and page eligibility
  contract.
- `iss-editorial` owns JSON document storage, section registry behavior,
  validation, autosave/read models, and editor UI primitives.
- The theme owns public landing HTML, CSS, wrappers, skins, and gesture
  treatments.
- Existing page templates remain source of truth until a page is explicitly
  eligible and JSON-enabled.

## Registry And Storage

Landing pages use a hybrid registry:

- the registry owns allowed gestures, skins, default skin, allowed treatments,
  labels, and field contracts;
- page meta stores the selected skin plus ordered section content.

Storage keys:

- `_iss_editorial_landing`
- `_iss_editorial_enabled_landing`
- `_iss_editorial_landing_skin`

Each stored section may include an optional `treatment`. V1 allows editors to
choose section treatment while internal review is active. Before client
handover, that treatment control becomes administrator-only without changing
storage or migration shape.

Whole-page `skin` and per-section `treatment` are separate concepts:

- `skin` is the page reading posture.
- `treatment` is how one gesture renders inside that posture.

The front page uses a dedicated `frontpage` skin. It is a page-level parity
skin for reconstructing the existing hardcoded Gutenberg homepage body while
`front-page.html` continues to own the hero wrapper. Gesture treatments remain
durable choices such as `gateway.cards` or `feature.media-panel`; they should
not multiply just to preserve one homepage row's spacing during migration.

The Schöneweide landing uses the `territorial` skin for a map-led reading
posture with compact technical labels, indexed rules, place plates, and an
operational Atlas destination. It remains semantic and reusable; it does not
expose Schöneweide page classes as an editor contract.

## Landing Gestures

Add `gateway` as a shared landing gesture. `gateway` is curated next-path
cards/links; it is not a layout primitive.

`gateway` fields:

- `kicker`
- `title`
- `body`
- optional `treatment`
- `items[]`

Each `items[]` entry stores:

- `label`
- `text`
- `url`
- optional `media_refs`

Do not add separate gestures such as `card_row`, `teaser_grid`, `portal_grid`,
or `gateway_3_cards`. Item count is handled by CSS inside the selected
treatment.

Front-page reconstruction adds constrained landing gestures:

- `statement`: a text-forward editorial thesis, intro, or Leitfrage with
  optional links.
- `fliesstext`: free landing prose with optional page links, no facts or media
  fields. Use it when editors need longer text without expanding `feature`.
- `feature`: a highlighted section with body copy, optional media, links, and
  facts. Its treatments decide whether those facts appear as an infobox,
  supporting rows, or image-overlay support.
- `dynamic_slot`: a mapped theme-owned dynamic module. Editors choose from
  approved slot keys; they do not paste raw block markup or shortcode-like
  content.
- `text_bild_reihe`: repeated, non-navigational image/title/text items. It is
  used when media and explanatory copy form one editorial row; unlike
  `gateway`, its items do not require or store destination links.
- `map_img`: a spatial editorial composition with a relation-driven map panel,
  one lead image, an optional contextual note, and non-linked supporting cards.
  The treatment owns the composition; JSON stores the image and card content,
  while map source and crop remain registered renderer details.

The first dynamic slot keys are front-page scoped:

- `front-projects`
- `front-timeline`
- `front-visit-info`
- `front-newsletter`

The Führungen landing adds `fuehrungen-offers`. `iss-content` owns its tour
query, controlled offer-group classification, and booking state. The theme owns
the slot markup and uses the existing `iss-card` plus shared
`data-iss-strip-carousel*` contract from `iss-relations`; the JSON document
stores only the ordered slot assignment and editorial copy. It does not revive
the removed `iss/tour-offer-catalog` block or add a second carousel runtime.

The About landing adds `team-directory`. `iss-content` owns the `team_member`
records and staff-editable `menu_order`; the theme queries every published
profile and renders the existing card contract. The slot stores no roster,
role filter, item limit, or person-specific crop rule.

The Schöneweide landing adds `schoneweide-atlas`. The slot delegates to the
existing register plugin renderer and stores no Atlas configuration, place
payload, or map markup in landing JSON.

These slots preserve existing dynamic render ownership for project notes,
timeline queries, visit information, and the newsletter form while letting the
front-page body be ordered from landing JSON.

`feature.media-text` renders the reusable image-beside-text anatomy used across
pages and CPT detail views. It carries ratio variants for balanced, compact
text / larger image, and wider text layouts; `/salon-vermietung/` “Der Ort”
should migrate here rather than keep `iss-rental-story` as a separate layout.
`feature.image-overlay` renders the image-dominant variant where `kicker`,
`title`, and `body` sit on the image. Facts remain facts; treatments only change
how they are presented. `feature.media-panel` remains the simpler copy/facts/media
panel used by sections such as the rental block.

`feature.origin-story` is the two-stage dossier treatment used by About: a
separate editable `lead` sits opposite the heading, followed by media opposite
the narrative and facts. `text.story-split` and `text.story-split-flip` retain
directional long-form composition without introducing page-specific gestures.

Do not add fallback text fields to `feature` just to survive deleted facts.
Use `fliesstext` for text-led sections and keep `feature` focused on highlighted
media/fact presentations.

## Treatments

The first landing treatment registry entries are:

- `gateway.cards`
- `gateway.link-list`
- `gateway.feature-strip`
- `gateway.pathways`
- `gateway.atlas-plates`
- `statement.lead`
- `statement.leitfrage`
- `statement.callout`
- `text.story-split`
- `text.story-split-flip`
- `text-bild-reihe.visual`
- `text-bild-reihe.compact`
- `text-bild-reihe.chronology`
- `map-img.editorial-atlas`
- `feature.media-panel`
- `feature.media-text`
- `feature.image-overlay`
- `feature.origin-story`
- `atlas-map.place-locator`
- `atlas-map.map-only`
- `atlas-map.editorial-split`
- `slot.projects`
- `slot.timeline`
- `slot.visit-info`
- `slot.newsletter`
- `slot.fuehrungen-offers`
- `slot.team-directory`
- `slot.schoneweide-atlas`
- `slot.team-directory`

The landing registry also exposes the canonical `galerie` gesture with
`gallery_layout`; this is a layout option rather than another treatment. The
Führungen migration uses `sequence` and the shared carousel runtime instead of
the retired dense-image-wall composition.

`gateway.cards` must support two, three, or four items with CSS only and no
markup change. `gateway.pathways` uses the same markup for a compact horizontal
sequence of image-led destinations. Treatment names describe durable visual
behavior, not every card count or one-off layout.

## Implementation Plan

1. Add the landing-page registry in the content/editorial vocabulary layer.
2. Add eligibility-gated `landing` format support so non-allowlisted pages keep
   Gutenberg as their normal authoring surface.
3. Extend the editor UI and sanitizer for section `treatment`, `gateway.items`,
   and mapped `dynamic_slot.slot_key` selection.
4. Add the theme landing renderer:
   - emit stable classes for skin, gesture, and treatment;
   - render `gateway` with theme-owned markup;
   - render `statement`, `feature`, and mapped dynamic front-page slots;
   - suppress hardcoded front-page fallback sections only when JSON is enabled
     and the landing document has renderable sections;
   - leave current output untouched when JSON is disabled or empty;
   - expose an inert theme-owned `industriesalon/editorial-landing` slot in the
     allowlisted static page templates.
5. Add CSS for the landing renderer and `gateway` treatments using existing
   tokens and layout primitives where possible.

## Test Plan

- Verify allowlisted pages get the JSON canvas and non-allowlisted pages still
  use Gutenberg.
- Verify editor-visible treatment selection saves and reloads.
- Verify `gateway.cards` renders two, three, and four items without markup
  changes.
- Verify disabled or empty JSON preserves current output.
- Verify enabled front-page JSON suppresses only the hardcoded body fallback,
  keeps the `front-page.html` hero wrapper, and renders project/timeline/visit
  info/newsletter dynamic slots.
- Browser-check `/`, `/about/`, `/verein/`, `/salon-vermietung/`,
  `/sammlungen/`, and `/fuehrungen/` on desktop and mobile.
- Run PHP lint for changed PHP files, JavaScript syntax checks for changed JS,
  targeted CSS lint for changed CSS, and `git diff --check`.

## Assumptions

- SQL is required when front-page landing JSON is backfilled or enabled.
- No upload artifact is needed for the front-page backfill because it references
  media IDs already used by the current front-page template/uploads set.
- Existing page templates remain source of truth until a page is explicitly
  JSON-enabled.
- The Führungen landing migration is paired with
  `ops/migrations/2026-07-11-fuehrungen-landing.php`. It requires no upload
  artifact because the document contains no media references.
- The About landing migration is paired with
  `ops/migrations/2026-07-11-about-landing.php`. Its media references already
  exist in the current Media Library, so no uploads artifact is required.
- Treatment choice is editor-visible during internal buildout and then
  capability-gated to administrators before client handover.
