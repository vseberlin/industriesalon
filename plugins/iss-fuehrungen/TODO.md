# TODO — iss-fuehrungen

## P0 (stability / security)

- [ ] Harden booking flow in `iss-payments-lite`: add rate limiting + anti-automation checks for `/is-tours/v1/book`.
- [ ] Define and enforce retention policy for booking request data (`is_tours_booking_requests`) including IP/UA minimization.
- [ ] Add admin-visible health check: mapping present, last sync timestamp, and unlinked future items count.

## P1 (editor clarity / correctness)

- [ ] Resolve field ambiguity: decide and document whether `tour_icon`, `is_featured`, `sort_weight` are active or deprecated.
- [ ] If deprecated, remove those fields from meta box to prevent misleading data entry.
- [ ] If active, implement concrete frontend/query usage and acceptance checks.
- [ ] Clarify fallback URL ownership model (source-map vs block attribute vs tour meta) and remove legacy dead path.

## P2 (UX / consistency)

- [ ] Convert raw availability states (`available`, `sold_out`, `inquiry`) to localized human labels in booking box/card contexts.
- [ ] Add admin guidance text for “Keine Zuordnung vorhanden” resolution path (where to fix mapping).
- [ ] Add a compact “Tour readiness” checklist in editor sidebar (facts complete, taxonomy set, mapping verified).

## P3 (maintainability)

- [ ] Add integration test coverage for:
  - [ ] mapped vs unmapped tour rendering,
  - [ ] SaaS unavailable → CPT fallback,
  - [ ] sold-out slot booking rejection,
  - [ ] mismatched `source_post_id` booking rejection.
- [ ] Add manual smoke-test procedure for release checklist.
- [ ] Keep `MANUAL.md` and `CHANGELOG.md` updated for each release.
