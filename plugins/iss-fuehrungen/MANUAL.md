# Industriesalon Führungen — Manual

## 1) Purpose and scope

`iss-fuehrungen` is the content-and-rendering plugin for public tour pages (`fuehrung`) at Industriesalon.

It provides:
- a dedicated post type and taxonomy for tours,
- structured tour meta fields in the editor,
- custom archive/single templates,
- integration points to the local calendar stack for dates plus a separate booking entry layer.

This plugin does **not** sync SuperSaaS by itself. Sync/import/fallback logic lives in `saas-api`.

---

## 2) Runtime dependencies

Required for full behavior:
- Theme: `industriesalon`
- Plugin: `saas-api` (calendar data sync/store/read)
- Plugin: `iss-payments-lite` (tour booking submit endpoint)

Without `saas-api`, tours still render, but dynamic calendar/date behaviors degrade.
Without `iss-payments-lite`, the booking form submit flow degrades.

---

## 3) Data model

## 3.1 Post type and taxonomy

- Post type: `fuehrung`
- Taxonomy: `fuehrung_typ` (hierarchical)

Slug behavior:
- archive slug: `/fuehrungen`
- taxonomy slug: `/fuehrungsart/<term>`

## 3.2 Structured meta fields (registered + REST-exposed)

All fields are registered via `register_post_meta` and sanitized on save.

| Meta key | UI label | Type | Current use |
|---|---|---|---|
| `duration` | Dauer | string | Single facts + archive card meta |
| `meeting_point` | Treffpunkt | string | Single facts + archive card meta |
| `target_group` | Zielgruppe | string | Single facts + archive card meta |
| `price_note` | Preishinweis | string | Single facts |
| `booking_note` | Buchungshinweis | string (textarea) | Booking box message |
| `booking_mode` | Buchungsmodus | enum (`auto`,`calendar`,`on_demand`,`hybrid`) | Controls calendar/on-demand rendering |
| `allow_on_demand_with_calendar` | Auf Anfrage zusätzlich erlauben | boolean | Enables automatic hybrid mode when calendar slots exist |
| `inquiry_url` | Anfrage-URL | string | On-demand CTA target |
| `inquiry_label` | Anfrage-Button Label | string | On-demand CTA text |
| `inquiry_note` | Anfrage-Hinweis | string (textarea) | On-demand explanatory text |
| `tour_badge` | Badge | string | Kicker on single + archive card |
| `tour_color` | Farbakzent | enum (`red`,`blue`,`green`,`yellow`,`brown`) | Accent CSS class |
| `tour_icon` | Icon | string | Stored only (not rendered currently) |
| `is_featured` | Auf Landingpages hervorheben | boolean | Stored only (not rendered currently) |
| `sort_weight` | Sortierung | integer | Stored only (not used in current query/render logic) |

Reason this split exists:
- keep long prose in Gutenberg content,
- keep repetitive, scan-friendly facts in structured fields for consistent cards/sidebars.

---

## 4) Editor UI: what authors see and why

In the `Führung` editor sidebar meta box (`Führungsdaten`), authors enter compact operational metadata:

- **Dauer / Treffpunkt / Zielgruppe / Preishinweis**: shown as standardized “facts” to reduce free-text inconsistency.
- **Buchungshinweis**: supports temporary operational notices without editing long content.
- **Buchungsmodus**: controls whether tour behaves as calendar, on-demand, hybrid, or auto mode.
- **Anfrage-URL / Label / Hinweis**: provide on-demand booking path for tours without regular slots.
- **Badge**: quick category-style label for information scent.
- **Farbakzent**: lightweight visual differentiation per tour.
- **Icon / Hervorheben / Sortierung**: currently visible but not functionally active in frontend templates (technical debt; see TODO).

---

## 5) Frontend rendering behavior

## 5.1 Template ownership

`iss-fuehrungen` overrides templates using `template_include`:
- single (`fuehrung`): routed by effective booking mode to theme HTML templates:
  - bookable/hybrid: `themes/industriesalon/templates/single-tour.html`
  - on-demand: `themes/industriesalon/templates/single-tour-on-demand.html`
  - rendered via plugin loader: `plugins/iss-fuehrungen/templates/single-fuehrung-theme-loader.php`
- archive/tax: `plugins/iss-fuehrungen/templates/archive-fuehrung.php`

## 5.2 Single page structure

The single template renders:
1. optional hero image,
2. heading + excerpt + facts (`iss/tour-facts`),
3. booking sidebar (`iss/tour-booking-panel`),
4. calendar area:
   - `iss/tour-calendar` (interactive JS calendar),
   - `iss/tour-dates` (SEO/stable list),
5. main content body,
6. related tours.

## 5.3 Archive cards

Each card shows:
- badge (if set),
- title, excerpt,
- compact meta line (`duration · meeting_point · target_group`),
- next date from linked calendar item, or “Aktuell keine Termine online”.

---

## 6) Mapping to SaaS API and calendar plugins

## 6.1 Integration contract

`iss-fuehrungen` itself only queries local calendar CPT entries (`iss_calendar_item`) by:
- `source_post_id = current fuehrung ID`,
- `event_start >= now`,
- ordered ascending.

Therefore the critical contract is: **calendar items must be linked to the correct `fuehrung` post ID**.

## 6.2 How mapping is established

Primary mapping path in current stack:
1. Tour page renders `iss/tour-calendar`.
2. Calendar renderer resolves a tag (from block attribute or source-map lookup).
3. Renderer stores/refreshes source mapping (`tag => source_post_id/source_post_type/fallback_url`) in option `iss_calendar_source_map`.
4. SuperSaaS sync imports slots into `iss_calendar_item`, sets `source_post_id` based on mapped tag.

Fallback / repair path:
- Use Calendar Items bulk edit (`Linked content ID`) with optional “same series” to assign `source_post_id` for recurring entries.

## 6.3 Booking URL resolution path

For each event shown in `iss-fuehrungen`:
1. use event meta `booking_url`,
2. else use tour meta `fallback_url` (legacy path in code; not exposed by current UI),
3. else no direct booking button.

In `saas-api` sync, `booking_url` is typically set to mapped `fallback_url` or SuperSaaS schedule URL.

---

## 7) How to use (editor + operations)

## 7.1 Authoring a new tour

1. Create `Führung` post.
2. Fill title, excerpt, content, featured image.
3. Set `fuehrung_typ`.
4. Fill structured fields (at least Dauer/Treffpunkt/Zielgruppe when available).
5. Publish.

## 7.2 Ensure calendar linkage works

1. Confirm SuperSaaS slot tagging strategy is consistent (`TAG=...` marker in description preferred; legacy `[TAG] title` also supported).
2. Open the public tour page once (writes source-map entry).
3. Run SaaS sync (Tools → SaaS Calendar Sync), or wait for hourly cron.
4. Verify `iss_calendar_item` rows for that tour have `source_post_id = <fuehrung ID>`.

## 7.3 Validate frontend

Check on single page:
- booking box shows next date/state,
- interactive calendar loads slots,
- date list (`tour-dates`) shows upcoming entries,
- archive card shows correct next date.

---

## 8) Behavior in hostile environments (degradation model)

Hostile environment = API outages, bad data, partial mappings, abusive traffic.

Current resilience:
- Slots endpoint falls back from live SaaS data to locally synced CPT data.
- Calendar UI shows explicit status/fallback links when mapping is missing or data unavailable.
- Booking endpoint validates required fields, mapping coherence, and sold-out state.

Current weak points:
- booking endpoint is public (`permission_callback` open) and lacks nonce/captcha/rate limiting,
- booking requests store PII (name/email/IP/UA) in options without explicit retention policy,
- editor UI shows fields that are not active in rendering, creating operational ambiguity,
- legacy `fallback_url` read path remains in `iss-fuehrungen` but field is not exposed to editors.

---

## 9) Deficiencies and ambiguity summary

1. **Inactive fields in editor UI** (`tour_icon`, `is_featured`, `sort_weight`) cause expectation mismatch.
2. **Legacy fallback coupling** (`fallback_url`) still referenced in code but no direct UI field in this plugin.
3. **Mixed status semantics** (`available/sold_out/inquiry`) can surface raw labels without human-friendly mapping in booking box.
4. **Mapping visibility gap**: frontend says “Keine Zuordnung vorhanden” but editors lack direct guided fix flow from that screen.
5. **Security hardening gap** in the thin booking entry layer for `/is-tours/v1/book`.

---

## 10) Improvement direction (high value first)

1. Decide for each currently exposed-but-unused field: wire it properly or remove from UI.
2. Make fallback URL ownership explicit (block attribute vs source-map vs tour meta) and remove dead fallback path.
3. Add booking abuse controls (rate-limit + anti-bot + logging hygiene).
4. Normalize human-facing availability labels and i18n for all states.
5. Add an editor troubleshooting checklist directly in admin (mapping/sync/link diagnostics).
