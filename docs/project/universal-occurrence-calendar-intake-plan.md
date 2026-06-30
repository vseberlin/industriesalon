# Universal Occurrence Calendar And Intake Plan

Status: active implementation plan.

## Decision

Use one shared occurrence calendar and one shared commerce intake contract for
bookable public actions.

- Occurrence availability is owned by `iss-occurrences`.
- Public calendar UI is owned by `iss-frontend`.
- Request persistence, spam checks, duplicate checks, and payment-gateway handoff
  are owned by `iss-commerce-lite`.
- New public writes use `POST /wp-json/iss-payments/v1/request`.
- New public slot reads use `GET /wp-json/iss/v1/booking-slots`.
- Do not add separate booking paths for Führungen, Veranstaltungen,
  Ausstellungen, or Publikationen.
- Do not keep tour/event/publication compatibility routes in new callers.

The commerce plugin must not know which feature called it beyond normalized
source data. It receives an intent, a source object, a selection, items,
customer data, and payment data, then validates and persists the request.

## Intake Contract

Canonical payload shape:

```json
{
  "intent": "booking",
  "source_post_id": 123,
  "source_post_type": "veranstaltung",
  "selection_mode": "slot",
  "slot_id": "public:12345",
  "start": "2026-07-15T18:00:00+02:00",
  "title": "Sommerabend im Industriesalon",
  "name": "Ada Lovelace",
  "email": "ada@example.test",
  "tickets": 2,
  "payment": "onsite",
  "loaded_at": 1784120000
}
```

Allowed intents:

- `booking`: a concrete occurrence slot is selected and validated against the
  occurrence service.
- `inquiry`: no slot exists; the visitor submits a preferred date/time request.
- `order`: a non-calendar order, currently used by publication sales.

Allowed selection modes:

- `slot`: validated against `iss_occurrences_get_booking_slots()`.
- `preferred_date`: stores a requested date when no available slots exist.
- `order`: stores a quantity-based order.

The request table may keep its existing `request_kind` column until the next
schema migration. For new writes it stores the normalized intent value.

## Calendar Contract

`GET /wp-json/iss/v1/booking-slots` returns a complete future slot set for the
requested source scope, not only the visible month.

Supported filters:

- `source_post_id`
- `source_post_type`
- `item_type`
- `tag`
- `post_id` as an alias for `source_post_id` only while internal callers are
  being renamed

The response shape is:

```json
{
  "source": "occurrences",
  "mode": "slots",
  "slots": [],
  "inquiry": {
    "allowed": true,
    "mode": "preferred_date"
  }
}
```

If no slots are available and inquiry is allowed, the frontend still opens the
calendar modal and renders an `Anfrage senden` preferred-date form. That keeps
the public flow consistent without inventing feature-specific fallback modals.

## Timeline Behavior

Timeline rows with grouped recurring occurrences must not render long lists of
60 or more dates in their own modal. `Termin wählen` opens the shared occurrence
calendar scoped to the row source. Selecting a slot invokes the same booking
form and the same intake contract as a Führung calendar.

Direct single-slot buttons may still open the booking form directly, but they
must submit the universal payload.

## Implementation Steps

1. Add the generic booking-slots REST endpoint in `iss-occurrences`.
2. Rename frontend config from tour-specific URLs to occurrence-calendar URLs.
3. Refactor `programm.js` so inline calendars and modal calendars share the
   same widget initializer.
4. Change grouped timeline `Termin wählen` controls to launch the shared
   calendar instead of the slot-list picker.
5. Collapse commerce public writes to `POST /iss-payments/v1/request`.
6. Normalize request processing around `intent` and `selection_mode`.
7. Update publication ordering to use `intent=order`.
8. Remove obsolete public route registrations and tour/event request-kind
   branching after callers are migrated.

## Verification

- `GET /wp-json/iss/v1/booking-slots` returns slots for a bookable Führung.
- The same endpoint returns slots for a bookable Veranstaltung.
- A grouped timeline row opens the shared calendar modal.
- Slot selection posts `intent=booking` and persists a request.
- Empty slots render a preferred-date inquiry path.
- Publication order posts `intent=order` and persists a request.
- PHP lint changed PHP files.
- JS syntax-check changed JS files.
- Run `tools/phpcs-target.sh` for changed PHP files.
- Run `git diff --check`.
