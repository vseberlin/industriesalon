# Register Review Checklist

Use this checklist for any change that touches `industriesalon-schoeneweide-register` or Schoneweide public pages.

## Boundary

- Does this change add public rendering to the plugin?
- If yes, is there a real interaction reason that prevents theme ownership?
- Does this change move sync or data-model logic into the theme?
- Are plugin outputs returning data contracts rather than finished public layout HTML?

## Content Ownership

- Does reviewed public copy end up in native WP fields where possible?
- Is new public prose being trapped in meta without a strong reason?
- Are structured fields being used only for structured supplemental data?

## Routing

- Does `/schoeneweide/` stay page-owned?
- Do public single place routes remain under `/schoeneweide/orte/{slug}/`?
- Does the change introduce any CPT/page slug collision risk?

## Blocks

- Does the change keep `iss-register/register-app` intact?
- If it adds a new plugin block, is it truly interactive or data-projection focused?
- Does it avoid adding plugin-rendered cards, featured layouts, related-place sections, or full page composition?

## CSS and JS

- Is plugin CSS limited to research app, map, hotspots, or narrow interactive wrappers?
- Does the change avoid introducing a second card/layout design system into the plugin?
- Is new JS isolated to one function area rather than creating a new global public app?

## Sync and Import

- Does Touchtable import stage data for review instead of publishing directly?
- Are source IDs, hashes, and manual overrides preserved?
- Does matching avoid silent fuzzy auto-merge?

## Theme Integration

- If this is a public section, is it implemented in `themes/industriesalon` templates, patterns, or page CSS?
- Does it reuse the existing theme visual language instead of plugin-local presentation?

## Performance

- Are repository queries cached?
- Does the change avoid repeated heavy meta shaping during frontend rendering?
- If a new payload is exposed, is it scoped to what the consumer actually needs?

## Final Gate

- After this change, is the statement still true?

`Plugin = data, sync, research interface`

`Theme = public website`
