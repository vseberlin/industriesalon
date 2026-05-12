# Schoneweide Overlay TODO

## Colors

Leave overlay colors as stubs for now.

Still to define:

- `kaiserzeit`
- `weimar`
- `nazi`
- `ddr`
- `current`
- `future`

Until that is decided:

- keep `color_token` as `TODO`
- keep `panel_theme` as `TODO`
- do not hardcode a fake era palette into the canonical overlay schema

## Guideline

Overlay data should stay query-first:

- eras
- functions
- current status
- current use
- risk flags
- problem flags
- future flags

UI colors and panel skins should derive from tokens later, not be baked into geometry authoring now.
