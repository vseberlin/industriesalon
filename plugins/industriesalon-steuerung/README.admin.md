# Industriesalon Steuerung - Developer Guide

This guide is for developers and technical maintainers.

## Purpose

`industriesalon-steuerung` is the authoritative source for persistent visitor-facing institutional data.

It owns:

- address
- contact
- map links
- regular visit hours
- office hours
- typed special-day exceptions
- prices
- accessibility
- FAQ
- mission statement

It is not a notice system.

Temporary campaign banners and temporary editorial alerts belong elsewhere.

## Design goals

The plugin follows these rules:

1. one structured source of truth
2. render by semantic intent, not duplicated page text
3. dynamic blocks first
4. dynamic blocks are the primary editor and integration surface
5. `variant` controls layout purpose
6. `skin` controls visual treatment only

## Data model

Main options:

- `iss_control_general`
- `iss_control_contact`
- `iss_control_maps`
- `iss_control_hours`
- `iss_control_accessibility`
- `iss_control_prices`
- `iss_control_faq`
- `iss_control_mission_statement`

Visit-hours structure:

```php
[
    'public' => [
        'note' => '',
        'days' => [
            'monday' => ['closed' => 0, 'open' => '', 'close' => '', 'note' => ''],
            ...
        ],
    ],
    'office' => [
        'note' => '',
        'days' => [...],
    ],
    'exceptions' => [
        [
            'date'   => '2026-05-12',
            'type'   => 'public|office|both',
            'kind'   => 'closed|special_open|extended|reduced|event_day|by_appointment',
            'closed' => 0,
            'open'   => '16:00',
            'close'  => '20:00',
            'label'  => '',
            'note'   => '',
        ],
    ],
]
```

`closed` is kept for backward compatibility.

`kind` is the authoritative semantic field for new data.

## Sondertage semantics

Special days are typed semantic exceptions.

Supported `kind` values:

- `closed`
- `special_open`
- `extended`
- `reduced`
- `event_day`
- `by_appointment`

The frontend uses these values to produce:

- status text
- list labels
- CSS classes
- future skin hooks

Do not infer semantics from free text if structured data is available.

## Render model

There are three layers:

1. data resolution
2. semantic render variant
3. theme skin

### Variant

Variant describes structure and intended placement.

Examples:

- `compact`
- `full`
- `inline`
- `footer`
- `front-card`
- `info-panel`
- `list`
- `compact-row`

### Skin

Skin is only a styling hook.

Current supported skin values:

- `default`
- `light`
- `dark`
- `muted`
- `front`
- `accent-red`

The plugin only emits classes/data attributes.
The theme is responsible for actual styling.

## Dynamic blocks

Primary block surface:

- `industriesalon/field`
- `industriesalon/hours`
- `industriesalon/visit-info`
- `industriesalon/contact`
- `industriesalon/faq`

All are server-rendered.

### `industriesalon/hours`

Core attributes:

- `type`
- `title`
- `variant`
- `skin`
- `show_status`
- `show_exceptions`

### `industriesalon/contact`

Core attributes:

- `title`
- `variant`

### `industriesalon/visit-info`

Core attributes:

- `variant`
- `shellMode`
- `accent`
- `surface`
- `kicker`
- `title`
- `intro`
- `show_address`
- `show_museum_hours`
- `show_office_hours`
- `show_arrival`
- `show_accessibility`

## PHP helpers

Preferred PHP outputs:

```php
Industriesalon_Steuerung::instance()->render_visit_hours('museum', 'info-panel', 'Öffnungszeiten', true, [
    'skin' => 'dark',
    'show_status' => true,
    'show_exceptions' => true,
]);

Industriesalon_Steuerung::instance()->render_visit_exceptions('museum', [
    'variant' => 'list',
    'skin' => 'default',
]);

Industriesalon_Steuerung::instance()->render_contact('Kontakt', [
    'variant' => 'footer',
    'skin' => 'dark',
]);
```

Do not build new features on wrapper helpers or shortcode-like compatibility APIs.

## Semantic output hooks

The plugin now emits stable semantic hooks for styling:

- `data-status`
- `data-kind`
- `data-variant`
- `data-skin`

Examples:

- `.iss-visit-status--kind-event_day`
- `.iss-visit-exceptions__item[data-kind="closed"]`
- `.iss-contact-card--footer`
- `.iss-visit-hours[data-variant="info-panel"]`

This allows new skins without changing stored data or adding special-case render methods.

## Caching

Visit data is cached via transients.

Important methods:

- `visit_cache_version()`
- `bump_visit_cache_version()`
- `visit_cache_key()`

Whenever hours or exceptions are sanitized and saved, the cache version is bumped.

## Backward compatibility

The plugin keeps:

- legacy `closed` field in exceptions
- existing regular schedule structure

New code should prefer:

- `kind`
- dynamic blocks
- variant/skin-aware renderers

## Recommended extension pattern

When you need a new output:

1. check if it is only a visual change
2. if yes, add a new `skin`
3. if the markup structure genuinely changes, add a new `variant`
4. do not add a new data model unless semantics really changed

When you need a new special-day meaning:

1. add a new `kind`
2. update `exception_kind_options()`
3. update semantic label mapping
4. update any editor docs

## What not to do

Do not:

- duplicate address or opening-hour text in page templates
- use notices for persistent visit/contact facts
- create one-off render methods for styling-only needs
- add new wrapper helper layers when a block or renderer method already exists

## Theme boundary

The plugin owns:

- data
- normalization
- semantic meaning
- render variants

The theme owns:

- final visual styling
- spacing
- color treatment
- variant/skin-specific CSS

## Operational checklist

After code changes:

1. verify admin save still works
2. verify one status output
3. verify one hours output
4. verify one exceptions output
5. verify one contact output
6. check frontend classes/data attributes

## File map

- plugin bootstrap:
  - `industriesalon-steuerung.php`
- block editor controls:
  - `assets/blocks.js`
- admin UI:
  - `assets/admin.js`
  - `assets/admin.css`
- editor-facing docs:
  - `README.de.md`

## Short summary

If you remember only one rule:

- keep semantics in plugin data
- keep layout purpose in `variant`
- keep look and feel in `skin`
