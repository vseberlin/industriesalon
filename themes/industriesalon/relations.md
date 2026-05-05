# Relations Proposal

## Status

Proposal only. No implementation in this note.

## Goal

Establish a reusable relation layer that:

- uses WordPress-native data structures as much as possible
- avoids a second editor-managed parallel system
- works with Query Loop whenever WordPress can support it cleanly
- allows one place or a set of places to drive content reuse on any page
- remains viable when the larger WF archive is integrated later

Primary current anchor entity:

- `register_place`

## Core Decision

Use a hybrid model:

- post meta is the source of truth
- a hidden taxonomy is the query index

This is the cleanest WordPress compromise.

Why not meta only:

- ordered relations are easy in meta
- reverse queries across many posts are poor in meta
- Query Loop works better with taxonomy than with serialized relation arrays

Why not taxonomy only:

- editors need ordered relations with roles
- one taxonomy term cannot carry relation order and per-link semantics cleanly
- forcing editors to maintain terms directly would create a second manual system

## V1 Relation Model

Register one universal relation meta field on supported post types:

- `iss_related_places`

Supported post types in phase 1:

- `register_place`
- `fuehrung`
- `veranstaltung`
- `ausstellung`
- `projekt`
- `post`
- `page`

Suggested stored shape:

```json
[
  {
    "place_id": 123,
    "role": "primary",
    "weight": 100,
    "label": ""
  },
  {
    "place_id": 456,
    "role": "venue",
    "weight": 80,
    "label": "Treffpunkt"
  }
]
```

Field meanings:

- `place_id`: linked `register_place` post ID
- `role`: relation meaning in the current content item
- `weight`: optional ranking for sorting or prioritization
- `label`: optional editor override for display context

Keep the role list small in v1:

- `primary`
- `venue`
- `stop`
- `subject`
- `related`

This covers the immediate use cases without creating a taxonomy of relation semantics too early.

## Source Of Truth

Editors manage only:

- `iss_related_places`

Everything else is derived from that field:

- taxonomy term assignments
- reverse lookup indexes
- optional display helpers

This avoids a real parallel system in the editorial workflow.

## Query Index

Add one hidden structural taxonomy:

- `iss_place_ref`

Design:

- one term per `register_place`
- stable term slug, for example `place-123`
- term meta stores the linked `register_place` post ID
- term name mirrors the current place title

Term assignment rules:

- every `register_place` post gets its own `iss_place_ref` term
- every post linked via `iss_related_places` gets the same term assigned

This creates a reusable index for:

- Query Loop filtering
- reverse lookups
- archive integration
- light block queries

Recommended registration flags:

- `public => false`
- `show_ui => false`
- `show_in_rest => true`
- `show_admin_column => false`

The taxonomy should stay structural, not editorial.

## Why This Is Not A Parallel System

Editorially, the user edits one thing:

- `iss_related_places`

The hidden taxonomy is generated and synced by code. It exists to support:

- performant queries
- Query Loop integration
- future scale

That is an index, not a second content model.

## Query Loop Fit

This model is intentionally designed to help Query Loop where Query Loop is strong.

Good use cases:

- show related `veranstaltung` items for one place
- show related `fuehrung` items for one place
- show archive posts linked to one place
- show items related to a curated set of places

Important limit:

- core Query Loop is fine for one post type at a time
- core Query Loop is not ideal for mixed-type result streams

Practical implication:

- use taxonomy-backed Query Loops for single post types
- use multiple Query Loops or a thin custom block for mixed-type assemblies

Do not distort the relation layer just to make one block fake a mixed search engine.

## Existing Fields

Keep existing human-readable fields during transition.

Examples:

- `veranstaltung` can keep `iss_location`
- `fuehrung` can keep `meeting_point`

But structural reuse should come from:

- `iss_related_places`

Templates and blocks can later prefer linked place data and fall back to legacy free-text fields.

This is safer for editors and avoids breaking current workflows.

## Reuse Modes

The relation layer should support two reuse modes.

### 1. Single place reuse

Examples:

- a `register_place` page shows related archive posts
- a `fuehrung` page shows its primary place and related places
- a homepage section highlights one place and related events

### 2. Place set reuse

Examples:

- a corridor selection
- a WF-related selection
- a set of places tied to one tour or one editorial landing

V1 can support place sets without a second dataset model by:

- assigning several places in `iss_related_places`
- querying by several `iss_place_ref` terms
- manually choosing place IDs in blocks when needed

## Named Collections

Do not start with editor-facing named collections unless repetition proves necessary.

Only add them in phase 2 if the same multi-place sets are reused across many pages.

If needed later, add a separate editor-facing taxonomy or small CPT such as:

- `iss_collection`

Possible future examples:

- `wf-museum`
- `elektropolis-route`
- `home-featured`
- `schoneweide-corridor`

That should remain optional. It should not be the foundation of the relation layer.

## Archive Readiness

This model is appropriate for the larger archive integration.

If archive content arrives as normal posts:

- the model already works

If archive content arrives as a new CPT:

- register `iss_related_places` on that CPT
- register `iss_place_ref` on that CPT
- no redesign is needed

Archive content can then link to one or more `register_place` entries and immediately become reusable on:

- place pages
- atlas fragments
- fuehrungen
- veranstaltungen
- home
- editorial landings

## Editing UX

The editor UI should stay simple:

- search `register_place`
- add one or more linked places
- choose role
- drag to reorder
- optional display label override

Do not expose:

- raw IDs
- structural taxonomy controls
- technical sync settings

## Ownership

This relation layer should not live in the theme.

It is cross-domain infrastructure affecting:

- `register_place`
- `fuehrung`
- `veranstaltung`
- `ausstellung`
- `projekt`
- posts
- future archive content

Preferred ownership:

- a small dedicated logic plugin such as `iss-relations`

Second-best option:

- extend `iss-content-model`

The Schoneweide register plugin should remain focused on place data and Atlas-specific domain logic, not generic cross-site relations.

## Phase Plan

### Phase 1

- add `iss_related_places`
- add hidden taxonomy `iss_place_ref`
- support `register_place`, `fuehrung`, `veranstaltung`, `post`
- auto-sync taxonomy terms from meta
- leave current templates and free-text fields intact

### Phase 2

- extend support to `ausstellung`, `projekt`, `page`
- add reverse-related sections on selected templates
- use taxonomy-backed Query Loops in block templates and patterns

### Phase 3

- integrate archive content
- decide whether repeated multi-place sets justify named collections
- add light helper blocks for mixed-type related content if needed

## Non-Goals For V1

Do not do these in the first step:

- no graph database
- no generalized entity graph
- no editor-facing duplicate taxonomy UI
- no attempt to solve all mixed-type rendering in core Query Loop
- no scene or panorama coordinate model yet

Those concerns can build on top of the relation layer later.

## Recommendation

Implement the relation layer first as:

- meta-owned relations
- hidden taxonomy index
- minimal role semantics
- Query Loop friendly where WordPress is naturally strong

This is the most durable base for later Atlas decomposition, place-driven landings, and archive integration without creating a second heavy content system.
