# Changelog

## 0.3.1 - 2026-05-03

- Removed obsolete editor blocks:
  - `industriesalon/status`
  - `industriesalon/exceptions`
  - `industriesalon/prices`
  - `industriesalon/mission-statement`
- Kept the remaining editor block surface focused on:
  - `industriesalon/field`
  - `industriesalon/hours`
  - `industriesalon/visit-info`
  - `industriesalon/contact`
- Refactored `industriesalon/visit-info` into a real info-panel renderer:
  - editable `kicker`, `title`, `intro`
  - plugin-driven rows for address, Besuchszeiten, Bürozeiten, ÖPNV and Barrierefreiheit
  - accent/surface controls for panel color and light/dark background
- Changed opening-hour semantics so exceptional open states stay semantically `open` and carry finer meaning in `kind`.
- Removed the old visitor-card-based visit-info render path and related dead helpers.
- Simplified editor controls so contact/FAQ no longer expose unused skin options.
- Removed leftover legacy spec/example files from the plugin root.

## 0.3.0 - 2026-04-17

- Show the next SaaS calendar item even when it is not linked to a content page.
- Added SaaS description and date under the Demnächst label.

## 0.2.9 - 2026-04-17

- Wired the visitor card to the next SaaS calendar item.
- Added title and date below the Demnächst label.

## 0.2.8 - 2026-04-17

- Removed the primary "Vorbeikommen" button from the visitor card.

## 0.2.7 - 2026-04-17

- Split the visitor card into two lighter rows with compact action buttons.
- Kept the opening-hours table layout in the left column.

## 0.2.6 - 2026-04-17

- Added stacked visit-info block output with editable visitor card fields.
- Added two-column theme hooks and social/address rendering for the visitor card.
- Exposed visit-info controls in the block editor for labels, links, and visibility.

## 0.2.5 - 2026-04-17

- Added `industriesalon/visit-info` block and German-only visit info flow.
- Added theme stylesheet hook for visit info layout and tokens.
- Added plain-language staff docs for the new visit info output.

## 0.2.2 - 2026-04-14

- Added admin help panel (Schnellhilfe) directly below section tabs.
- Added contextual WordPress help tabs (Kurzhilfe, Shortcodes & PHP) for the plugin admin screen.
- Added/updated admin help styling for better readability.
- Kept duplicate save action (`Änderung speichern`) at top and bottom of the form.
- Bumped plugin version from `0.2.1` to `0.2.2`.
