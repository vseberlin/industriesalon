# Refactor Plan — `iss-fuehrungen` -> `iss-tour`

## Root problem

Current ownership is mixed:
- `saas-api` owns infrastructure/calendar storage,
- `iss-programm` owns shared calendar UI,
- `iss-payments-lite` now owns the thin booking entry endpoint,
- `iss-fuehrungen` owns tour content/CPT/templates.

Target ownership:
- calendar sync/mapping/cache in infrastructure layer,
- shared calendar UI in programme layer,
- thin booking entry/payment flow outside infrastructure,
- tour UX and booking presentation in tour domain layer.

---

## Phase 1 (current) — Mode-aware booking in tour plugin (no slug rename)

Goal:
- Support three real business cases with minimal risk:
  - calendar-only tours,
  - on-demand tours,
  - hybrid tours (calendar + on-demand).

Scope:
- Keep plugin slug `iss-fuehrungen` unchanged.
- Add booking mode/meta fields to tour CPT.
- Make single tour rendering conditional by effective booking mode.
- Keep existing `iss/tour-calendar` and `iss/tour-dates` blocks for scheduled tours.

Non-goals:
- No major data migration.
- No timeline feature removal.
- No immediate plugin rename.

---

## Phase 2 — Extract explicit tour-facing dynamic blocks

Goal:
- Replace ad-hoc PHP template fragments with reusable dynamic blocks.

Blocks to add in tour plugin:
- `iss/tour-facts`
- `iss/tour-booking-panel`
- optional `iss/tour-related`

Keep:
- `iss/tour-calendar` and `iss/tour-dates` from calendar stack until core split is complete.

---

## Phase 3 — Move to block HTML template for tours

Goal:
- Use editor-visible template composition without shortcodes.

Steps:
- Introduce `single-fuehrung.html` for the active tour post type.
- Keep template locking where needed to avoid overexposing internal structure.
- Remove duplicated legacy templates once parity is verified.

---

## Phase 4 — Infrastructure/domain split + safe rename path

Goal:
- End-state architecture:
  - `iss-calendar-core` (or lean `saas-api`) for sync/services only,
  - `iss-tour` for tour UX/domain behavior,
  - `iss-timeline` remains site-wide.

Rename strategy:
- Do not hard-cut plugin slug in one step.
- First ship `iss-tour` code behavior under current slug.
- Later introduce compatibility loader/wrapper and migration path.

---

## Operational acceptance criteria

- Tours with no slots can be configured and rendered as on-demand only.
- Tours with slots still render calendar/list as before.
- Tours can intentionally expose both booking paths (hybrid).
- Timeline functionality remains unchanged and site-wide.
- No shortcodes required for tour booking/calendar rendering.
