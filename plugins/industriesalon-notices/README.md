# Industriesalon Notices

Pragmatic notice plugin for central banners/notices in the Industriesalon setup.

## Version

- Current: `0.1.1`

## Covered in this sketch

- CPT `iss_notice`
- native meta boxes
- Classic Editor for notice text
- internal/external button target
- front page banner / front-page timeline promo / website notice / admin notice
- basic scheduling
- basic priority handling
- admin list columns
- sortable priority in admin list
- timezone-safe datetime handling (`wp_timezone`)
- optional external links in new tab (`target="_blank" rel="noopener noreferrer"`)
- conditional admin fields (link type / scope)
- uses standard WordPress editing capabilities (no extra role setup needed)
- frontend helper functions
- shortcode `[iss_notice area="front_page_banner"]`
- shortcode `[iss_notice area="front_page_timeline_promo"]`

## Deliberately left out

- ACF
- Gutenberg layout blocks
- dismissible notices
- recurring rules
- analytics
- per-user state
- complex targeting matrices
- design picker

## Field model

Core:
- Interner Titel
- Hinweistext

Meta:
- Überschrift
- Markierung
- Button-Text
- Linktyp
- Interne Seite oder Beitrag
- Externer Link
- Extern in neuem Tab öffnen
- Bereich
- Geltung
- Inhalte auswählen
- Sichtbar für
- Hinweis anzeigen
- Priorität
- Anzeigen ab
- Anzeigen bis

## Current assumptions

- internal link selector searches `page` and `post`
- selected scope also searches `page` and `post`
- only one matching notice is rendered per area
- highest priority wins

## Integration ideas for the current theme

### Option 1: shortcode in block template

Use a shortcode block or a PHP-rendered slot if the theme later gets PHP parts.

Example:

`[iss_notice area="front_page_banner"]`

### Option 2: render from theme PHP

If the theme gets a PHP hook or hybrid template part later:

```php
<?php echo iss_render_notice('front_page_banner'); ?>
```

## Suggested first use

Create one notice:
- Bereich: Startseiten-Banner
- Geltung: Nur Startseite
- Sichtbar für: Öffentlich
- Hinweis anzeigen: yes

Then place the output above or overlapping the hero.

## Notes

This plugin intentionally stays narrow. It focuses on reliable editorial workflows and simple theme integration points.
