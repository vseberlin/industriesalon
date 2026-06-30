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
  `about`, `verein`, `salon-vermietung`, and `sammlungen`.
- The front page keeps `front-page.html` as the wrapper/template authority.
- JSON rendering replaces only eligible editable landing sections.
- Disabled JSON, missing JSON, invalid JSON, or an empty section list falls
  back to the current template and `post_content` output.
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

- `statement`: a text-forward editorial thesis, intro, or callout with
  optional links.
- `fliesstext`: free landing prose with optional page links, no facts or media
  fields. Use it when editors need longer text without expanding `feature`.
- `feature`: a highlighted section with body copy, optional media, links, and
  fact/microblock rows. Its treatments include the reusable 50/50 image-text
  pattern used across pages and CPT detail views.
- `dynamic_slot`: a mapped theme-owned dynamic module. Editors choose from
  approved slot keys; they do not paste raw block markup or shortcode-like
  content.

The first dynamic slot keys are front-page scoped:

- `front-projects`
- `front-timeline`
- `front-visit-info`
- `front-newsletter`

These slots preserve existing dynamic render ownership for project notes,
timeline queries, visit information, and the newsletter form while letting the
front-page body be ordered from landing JSON.

`feature.media-text` renders the reusable 50/50 image-text anatomy used across
pages and CPT detail views. For front-page parity, `feature.microblocks` renders
the older overlay variant: microblocks and the action link live in the text
column, while `kicker`, `title`, and `body` render as the overlay heading inside
the media card. `feature.media-panel` remains the simpler copy/facts/media panel
used by sections such as the rental block.

Do not add fallback text fields to `feature` just to survive deleted facts.
Use `fliesstext` for text-led sections and keep `feature` focused on highlighted
media/fact presentations.

## Treatments

The first landing treatment registry entries are:

- `gateway.cards`
- `gateway.link-list`
- `gateway.feature-strip`
- `statement.lead`
- `statement.callout`
- `feature.media-panel`
- `feature.media-text`
- `feature.microblocks`
- `slot.projects`
- `slot.timeline`
- `slot.visit-info`
- `slot.newsletter`

`gateway.cards` must support two, three, or four items with CSS only and no
markup change. Treatment names describe durable visual behavior, not every card
count or one-off layout.

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
- Browser-check `/`, `/about/`, `/verein/`, `/salon-vermietung/`, and
  `/sammlungen/` on desktop and mobile.
- Run PHP lint for changed PHP files, JavaScript syntax checks for changed JS,
  targeted CSS lint for changed CSS, and `git diff --check`.

## Assumptions

- SQL is required when front-page landing JSON is backfilled or enabled.
- No upload artifact is needed for the front-page backfill because it references
  media IDs already used by the current front-page template/uploads set.
- Existing page templates remain source of truth until a page is explicitly
  JSON-enabled.
- Treatment choice is editor-visible during internal buildout and then
  capability-gated to administrators before client handover.
