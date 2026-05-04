# Register Boundaries

## Purpose

This document defines the implementation boundary for `industriesalon-schoeneweide-register`.

The goal is to keep the plugin focused on:

- structured place data
- import and sync
- research interface
- interactive utilities

and to keep the public Schoneweide website in the theme.

## Core Rule

Plugin = data, sync, research interface  
Theme = public website

If a change makes the plugin responsible for public cards, page sections, or dossier layouts, the boundary is being broken.

## Plugin Responsibilities

The plugin owns:

- `register_place` content model
- taxonomies and structured meta for place data
- import from local JSON or future Touchtable source
- sync metadata, review status, and provenance
- repository and query helpers
- REST endpoints
- full research interface block: `iss-register/register-app`
- interactive map and hotspot behavior where the theme alone is not enough

## Theme Responsibilities

The theme owns:

- `/schoeneweide/` landing page
- single place template for `register_place`
- public cards
- featured sections
- narrative page sections
- archive, exhibition, project, and tour integrations
- public CSS and visual system

Theme-owned public rendering should live in:

- `themes/industriesalon/templates/`
- `themes/industriesalon/patterns/`
- `themes/industriesalon/assets/css/`

## Native Content Rule

Reviewed public content should end up in native WordPress fields whenever possible:

- title -> post title
- short public summary -> excerpt
- long reviewed story -> content
- lead image -> featured image

Use post meta for structured supplemental data, not as the default home for public prose.

## Allowed Plugin Blocks

Keep:

- `iss-register/register-app`

Add only if clearly justified:

- `iss-register/map`
- `iss-register/hotspots`
- optional `iss-register/place-facts`
- optional `iss-register/place-timeline`

Do not add plugin-rendered blocks for:

- place cards
- featured layouts
- related places
- full page sections
- public landing page composition

## Data Contracts, Not HTML Contracts

The theme should consume place data through plugin helpers or repository functions.

Preferred contract:

- plugin returns structured place payloads
- theme decides markup and styling

Avoid:

- plugin functions that return finished public card HTML
- plugin render helpers that become the only way to output a public section

## CSS Rule

Plugin CSS may style:

- research app
- plugin map behavior
- plugin hotspot behavior
- small interactive wrappers

Plugin CSS must not become a second site-wide design system.

Do not expand plugin CSS into theme-level systems such as:

- shared cards
- page sections
- headings
- longread layouts

## Routing Rule

Use:

- page-owned landing route: `/schoeneweide/`
- public single-place route: `/schoeneweide/orte/{slug}/`

Do not let plugin archive routing take over the main editorial landing.

## Sync Rule

Touchtable is an enrichment source, not a public frontend.

Import flow:

1. import
2. match
3. stage
4. review
5. promote approved fields

Never auto-publish imported long text into public output.

## Legacy Surfaces

The plugin currently contains some presentation-oriented helpers and CSS, especially around the research interface and older register cards.

Treat these as legacy/internal implementation, not as the future public rendering model:

- `includes/render-register-list.php`
- `includes/render-register-featured.php`
- `assets/css/register-frontend.css`

New public sections should not copy this pattern.

## Decision Test

Before merging a change, ask:

1. Is this data/sync/research-app logic?
2. Or is this public website composition?

If it is public composition, it belongs in the theme unless there is a strong interaction reason to keep it in the plugin.
