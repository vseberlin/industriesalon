# Universal Occurrence Calendar And Intake Plan

Status: existing shared intake contract; native frontend migration proposed on
2026-10-07. Part of the [WordPress refactoring plan](wordpress-refactoring-plan.md).

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

## Native WordPress frontend migration

Replace Flatpickr and its imperative calendar controllers with native HTML
controls enhanced through the WordPress Interactivity API. Keep the shared
occurrence and commerce contracts above. This changes frontend implementation
and date-selection presentation, not source dates, bookings or editorial storage.

Staging runs WordPress 7.0 and already includes `@wordpress/interactivity` and
`@wordpress/interactivity-router`. Target APIs verified in that version. Use
plain JavaScript ES modules registered through `wp_register_script_module()`
and existing block `viewScriptModule` registration with explicit dependencies.
Set interactivity support on the existing dynamic blocks and render escaped
per-instance context with `wp_interactivity_data_wp_context()`. No application
framework, TypeScript, JSX, extra block family or additional build pipeline is
required for this migration.

The Interactivity API supplies state/actions and directive processing, not a
ready-made date picker. Native controls deliberately use the browser's own
presentation. Public frontend modules belong to `iss-frontend`; theme skins
remain in `industriesalon`; request persistence stays in `iss-commerce-lite`.

### Native control choices

| Surface | Planned control and behavior |
| --- | --- |
| Programme day filter | Labelled `input type="date"`, submit/reset buttons |
| Programme month filter | Existing native month `select`; retain month presets |
| Bookable dates | Native available-date `select`, then available times/slot controls |
| Preferred-date inquiry | Native date/time fields, visibly identified as a request |
| Booking dialog | One shared native `dialog`, with accessible title and focus return |
| Results and history | Existing PHP cards/rows, accessible links and pagination |

Native date inputs support range/step constraints, not a sparse list of arbitrary
available dates. Use the available-date selector for booking so unavailable days
are not presented as bookable. Keep date-only values as `YYYY-MM-DD` strings;
apply Europe/Berlin semantics on the server and avoid implicit browser timezone
conversion. Cancelled, sold-out, unmapped and empty states retain explicit text.

The styled inline Flatpickr month grid and the separate timeline calendar-grid
enhancement retire. Native date/slot choices replace those widgets. This visible
change needs staff acceptance, including mobile and keyboard use.

### 1 Migrate programme filters

Extend the existing `industriesalon/timeline-query` block. Preserve its saved
attributes, fixed filters, grouping, presets and shared PHP listing renderer.
Normalize public filter parameters through the existing query owner; public
parameters may narrow allowed filters but must not override fixed restrictions.

Give filters and pagination real GET URLs with a stable instance scope. Initial
HTML contains results; normal form submission and links work without JavaScript.
Use the core router for progressive region updates after the returned region's
interactive controls are compatible. Keep navigation scoped to this component.
The booking controller upgrade is a prerequisite for router activation wherever
booking triggers occur inside the replaced region; a native GET release can
precede that activation without maintaining two AJAX clients.

Use the same PHP renderer for full requests and routed results. Do not create
a second JavaScript card renderer. The router fetches a server-rendered page,
so measure total server queries and response bytes; replacing DOM regions does
not itself reduce database work. Do not use an API introduced after WordPress
7.0 to inject interactive HTML. Avoid site-wide client navigation and speculative
prefetch during this migration.

Default pagination becomes normal bounded next/previous navigation enhanced by
the same router. Retain older grouped rows/year labels correctly. Confirm this
visible change from append-style load-more during staff review. Keep the existing
timeline REST contract while tests or other callers use it; remove its obsolete
browser fetch path on each converted surface, and retire the endpoint only after
a consumer audit. There must be one read transport for each UI action.

### 2 Migrate the shared booking and inquiry controls

Replace `programm.js` initialization, selection state, DOM-built forms and modal
handling with a focused Interactivity API module and PHP-authored form markup.
Reuse it for inline calendars, grouped programme rows and direct slot actions.
Use per-instance context; keep shared dialog state limited to the active source
and selection so multiple widgets cannot overwrite one another.

Reuse `GET /iss/v1/booking-slots` for lazy slot reads, keyed by the complete source
scope. Render any already-known slot data into the initial context instead of
fetching it again. The existing complete future-slot response lets date changes
filter the loaded set without another request. Measure its existing horizon and
worst-case size; any later pagination must be explicit in the owner contract,
never a silent truncation of availability.

Deduplicate in-flight reads for the same scope, ignore stale responses and keep
failure/retry states visible. Treat cached availability as a display aid; server
submission validates the current source, slot, status and quantity. Use the
existing universal request endpoint, nonce/spam checks and idempotency work.
Do not imply that a stored inquiry or request is a confirmed reservation/payment.

Display server-rendered dates and the valid existing booking/contact fallback
when enhancement is unavailable. Keep preferred-date inquiry behavior for sources
with no slots where the owner permits it. Preserve external ticket links. A
public list must never become an empty loading shell after a script failure.

### 3 Remove obsolete dependencies and styling

After both consumer families migrate, remove:

- Flatpickr vendor JS/CSS and German locale assets under
  `modules/programme/assets/vendor/flatpickr/`.
- Flatpickr handles/dependencies in `includes/assets.php` and the shared
  datepicker registration helper if it has no remaining callers.
- `_flatpickr`, `is-has-flatpickr`, widget initialization and bridge-grid code
  from the retired `programm.js` and `timeline-query.js` paths.
- `.flatpickr-*` theme rules from `tour-calendar-skin.css` and obsolete plugin
  control rules. Keep the surviving field/dialog/slot styles in their existing
  owning layers.
- Tests, localized globals and compatibility branches that exclusively serve
  the removed controllers. Add an ESLint module override for the new ES modules;
  the current project default is `sourceType: script`.

Each migrated surface ships with its old controller disabled/removed in the same
change. Retain only the still-used legacy consumer during the staged conversion;
remove the vendor once that consumer is gone. Rollback uses the preceding code
release, not simultaneous old/new initializers.

First-party jQuery auditing is a separate cleanup item in the overall plan.
Calendar modules currently use browser APIs, not jQuery. WordPress admin/editor
dependencies must not be globally dequeued as a calendar optimization.

### Acceptance criteria

- Homepage, Kalender, Veranstaltungen and tour/detail booking entry points keep
  correct published results, recurring grouping, slot identity and external links.
- No Flatpickr files, initialization, dependency handles or live selector rules
  remain after the final consumer cutover.
- Initial server-rendered listings make zero duplicate hydration fetches. An
  explicit filter/page action has one read path. Changing a selected booking date
  makes no new request when its complete slot set is already loaded.
- Back/forward, reset, multiple blocks, double clicks, out-of-order responses,
  expired nonces, denied submissions and retry after a network error behave
  predictably. Retried requests persist only once.
- German labels, Berlin date boundaries, DST, empty/sold-out/cancelled states,
  keyboard operation, dialog Escape/focus return and announced status are tested.
- Native controls are checked on desktop/phone Chrome, Firefox and real
  WebKit/Safari. Browser variation in native picker appearance is accepted;
  missing availability or controls is not.
- Existing `programme-rotation.php`, `programme-overview.php` and affected
  event/commerce/editor tests pass. Fixtures are isolated and notifications are
  intercepted. No editor block validation errors or changed stored attributes.
- Record compressed JS/CSS bytes, request counts and database query time before
  and after, including WordPress core module dependencies. Library removal alone
  is not evidence of a faster page.

Sources for the proposed native API integration:
[Interactivity API](https://developer.wordpress.org/block-editor/reference-guides/interactivity-api/),
[script module registration](https://developer.wordpress.org/reference/functions/wp_register_script_module/),
[region navigation](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-interactivity-router/),
and [native date input constraints](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/input/date).

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
