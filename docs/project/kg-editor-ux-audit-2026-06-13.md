# Knowledge Graph Editor UX Audit - 2026-06-13

Scope: local refactor checkpoint through the public object, occurrence,
availability, search, and editor-facing Offer contract slices.

## Findings

- No occurrence/calendar/programme CPT is registered or editor-visible.
  Runtime checks for `iss_calendar_item`, `occurrence`, `occurrence_series`,
  `programm`, and `calendar` all return no post type object.
- Editors still work in parent objects: `fuehrung`, `veranstaltung`,
  `ausstellung`, `projekt`, `video`, `publication`, archive/register CPTs, and
  `entity_profile`.
- `entity_profile` is a graph/profile editorial object, not an occurrence or
  calendar workflow, so it stays visible.
- The SuperSaaS sync tool remains an operational Tools page
  (`tools.php?page=iss-occurrences-sync`) guarded by the shared sync capability.
- Ausstellung availability is edited on the Ausstellung parent object through
  type, date, overview visibility, and availability-signal controls. Programme
  occurrence projection uses a separate `iss_programme_enabled` opt-in, so it is
  not a calendar occurrence editor.
- Source search found no first-party Schema.org JSON-LD Event renderer. Event
  schema intent is now explicit in the facade payload contract instead:
  occurrence payloads emit Event schema intent, while availability payloads are
  non-Event CreativeWork availability records.

## Decision

- No editor menu removal is needed in this slice.
- Keep WordPress CPTs as the editor shell and keep occurrence projection as an
  internal service/table contract.
- Future JSON-LD work must consume the facade schema intent and must not emit
  Event schema for overview-only Ausstellung records without concrete dated
  occurrences.
