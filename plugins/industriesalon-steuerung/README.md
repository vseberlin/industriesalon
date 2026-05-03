# Industriesalon Steuerung

Central source of truth for repeated institutional content on the Industriesalon site.

## What it manages

- address and location
- contact details
- maps / arrival links
- visit hours
- office hours
- typed special-day exceptions
- prices
- accessibility
- FAQ
- mission statement

## Main principle

Data is entered once and rendered in multiple places.

The plugin owns:

- structured data
- semantic resolution
- render variants

The theme owns:

- visual styling
- spacing
- color treatment

## Documentation

- editor / non-technical guide:
  - [`README.de.md`](./README.de.md)
- developer guide:
  - [`README.admin.md`](./README.admin.md)

## Primary output surface

Dynamic blocks:

- `industriesalon/field`
- `industriesalon/hours`
- `industriesalon/visit-info`
- `industriesalon/contact`
- `industriesalon/faq`

## Key semantic change

Special days are no longer treated as plain date overrides.

They now carry typed semantics through `kind`, for example:

- `closed`
- `special_open`
- `extended`
- `reduced`
- `event_day`
- `by_appointment`

That makes it possible to:

- render better status text
- style exceptions predictably
- add new skins without changing stored data

## Integration rule

Use dynamic blocks or PHP render methods first.

Use dynamic blocks or direct PHP render methods for all new integrations.
