# Greenfield Refactor Plan

This is the repo-owned plan for the gradual Industriesalon refactor. It records direction and sequence only; it is not a request to rename plugins or move public routes in one pass.

## Architecture Direction

- Model the system as `Entity / Relation / Occurrence / View`.
- Treat the calendar as occurrence-only. It should show dated programme occurrences, not broad availability states.
- Treat Dauer and Digital Ausstellungen as exhibition availability, not calendar events.
- Keep public presentation in the active theme.
- Keep plugins responsible for data contracts, services, integrations, and dynamic block data.

## Phased Path

1. Stabilize the current programme/calendar checkpoint on the occurrence projection.
2. Build a dedicated Ausstellung availability browser that is separate from calendar and timeline occurrence queries.
3. Add `iss-core` for infrastructure conventions only.
4. Add `iss-frontend` for shared frontend runtime helpers only.
5. Defer domain plugin extraction until the boundaries are proven by reused services and stable contracts.

## Current Boundary Notes

- `iss-occurrences` owns occurrence projection and programme/calendar query readiness.
- `iss-programm` renders programme and browser blocks from stable data APIs.
- `iss-content-model` owns CPT/editor meta contracts.
- `saas-api` owns SuperSaaS settings, sync, and `/is-tours/v1/slots`.
- The theme owns templates, public composition, visual skin, and route-level page structure.
