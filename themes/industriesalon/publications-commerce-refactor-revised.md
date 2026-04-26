# Industriesalon Publications Payment Refactor (Revised)

## Purpose

Industriesalon has a small catalogue of books, brochures, catalogues, and printed materials. The website should present these as an editorial and institutional publishing programme, not as a webshop.

The payment flow should be intentionally small:

```txt
publication page -> action button -> modal -> gateway -> webhook -> confirmation
```

This is not a commerce suite.

Not in scope:

- no cart
- no inventory management
- no shipping rules engine
- no product variation system
- no discount logic
- no generalized commerce abstraction unless truly required by implementation

---

# 1. Architectural Correction

## saas-api Boundary

`saas-api` is infrastructure only.

Its role in the plugin stack is limited to:

- pull calendar data from SuperSaaS
- normalize and store calendar data in local CPT records
- expose read/query helpers for local consumers
- support frontend read access for calendars and dates

Reserved future capability:

- push local calendar changes back to SuperSaaS

No other extra logic belongs in `saas-api`.

## Strip Unintended Functions From saas-api

The following responsibilities must move out of `saas-api`:

- booking request capture
- booking form submission handling
- payment method handling
- Mollie or any gateway integration
- webhook processing
- confirmation flow
- customer/order persistence
- publication commerce logic
- tour domain workflow beyond calendar data access

`saas-api` should end as:

```txt
SuperSaaS adapter + local calendar store + read API
```

Not:

```txt
booking, payments, or domain commerce
```

---

# 2. Plugin Ownership

## saas-api

Owns:

- SuperSaaS pull
- local calendar CPT sync
- mapping/linking needed to connect slots to local content
- slot query helpers
- read-only REST or helper access for frontend calendars

Does not own:

- booking modal
- booking endpoint
- payment gateway
- webhook logic
- confirmation emails
- publication ordering
- tour ordering

## iss-fuehrungen

Owns:

- `fuehrung` content model
- tour rendering
- tour-specific booking UI and CTA placement
- use of local calendar data from `saas-api`

Does not own:

- low-level SuperSaaS sync
- publication domain

## iss-publications

New plugin.

Owns:

- `publication` CPT
- publication taxonomies and meta
- publication archive and single rendering
- publication action button and modal payload

Does not own:

- calendar sync
- generalized commerce platform concerns

## iss-payments-lite

New thin payment layer.

Owns:

- modal submission target
- gateway request creation
- webhook handling
- minimal payment record storage
- confirmation emails

This layer should stay small and flow-oriented, not evolve into a store framework.

---

# 3. Publication Scope

## Content Model

Register CPT:

```txt
publication
```

Taxonomies:

```txt
publication_type
publication_topic
```

Use native post fields first:

- title
- editor
- excerpt
- featured image
- revisions
- page attributes

## Minimal Publication Meta

Only keep fields required for editorial presentation and thin payment:

```txt
_iss_publication_subtitle
_iss_publication_author
_iss_publication_editor
_iss_publication_year
_iss_publication_pages
_iss_publication_format
_iss_publication_language
_iss_publication_isbn
_iss_publication_publisher
_iss_publication_sale_enabled
_iss_publication_price_cents
_iss_publication_cta_label
_iss_publication_gateway_description
_iss_publication_featured
_iss_publication_related_tours
_iss_publication_related_posts
```

Remove from v1:

- stock mode
- stock quantity
- shipping enabled
- shipping price
- pickup/shipping branching
- tax mode complexity
- SKU management
- card variant system unless the theme actually needs it

---

# 4. Frontend Direction

## Editorial Principle

Publications are content first and purchasable objects second.

Use the existing theme system:

- `.iss-page-hero`
- `.section`
- `.iss-container`
- `.iss-heading`
- `.iss-indent`
- `.iss-card-grid`
- `.iss-card`
- `.iss-media-card`

## Archive

URL:

```txt
/publikationen/
```

Structure:

```txt
hero
editorial intro
featured publication
publication grid
ordering note
archive/contact note
```

## Single

URL:

```txt
/publikationen/{slug}/
```

Structure:

```txt
title area
cover + publication metadata
editorial description
ordering panel
related publications or tours
```

The order panel should sit after the editorial content, not in the hero.

---

# 5. Thin Payment Flow

## User Flow

```txt
publication page -> order button -> modal -> gateway -> return -> webhook -> confirmation
```

## Modal Data

Required:

- publication id
- publication title
- amount cents
- customer name
- customer email

Optional:

- notes

No slot logic.

No ticket quantity logic unless explicitly needed.

No pickup/shipping branching in v1.

## Payment Record

Use one minimal local record type:

```txt
iss_payment
```

Possible implementation:

- custom post type for admin visibility
- or custom table if later needed

CPT is acceptable for v1 simplicity.

Minimal fields:

```txt
_iss_payment_entity_type
_iss_payment_entity_id
_iss_payment_status
_iss_payment_amount_cents
_iss_payment_currency
_iss_payment_customer_name
_iss_payment_customer_email
_iss_payment_gateway
_iss_payment_gateway_payment_id
_iss_payment_notes
```

Statuses:

```txt
pending
paid
failed
expired
canceled
refunded
```

## Gateway

Gateway integration belongs in `iss-payments-lite`, not in `saas-api`.

For v1 the gateway flow should do only this:

1. create local payment record
2. create gateway payment
3. redirect visitor
4. receive webhook
5. mark payment state
6. send confirmation email

That is enough.

---

# 6. Tour Relationship

Tours and publications may share a future payment layer, but they should not be forcibly unified first.

Correct order:

1. restore clean boundaries
2. keep `saas-api` infra-only
3. build publications as its own domain
4. extract shared payment code only where duplication is real

Do not begin with a big generic `industriesalon-commerce` plugin.

If shared code becomes necessary, keep it narrow:

```txt
gateway client
payment record creation
webhook verification
confirmation mail helper
```

Nothing more.

---

# 7. Implementation Plan

## Phase 0: Boundary Cleanup

Before publication payment work:

1. remove booking/payment ownership from `saas-api`
2. keep `saas-api` limited to calendar sync/store/read functions
3. document reserved future capability only:
   local calendar changes -> push back to SuperSaaS

Deliverable:

```txt
saas-api is infrastructure only
```

## Phase 1: Publications Data Layer

1. register `publication`
2. register `publication_type`
3. register `publication_topic`
4. register minimal publication meta
5. add simple admin metaboxes

Deliverable:

```txt
publications editable in admin without payment flow
```

## Phase 2: Publications Render Layer

1. add publication archive renderer
2. add featured publication renderer
3. add single publication metadata/order panel
4. keep markup compatible with existing theme classes

Deliverable:

```txt
/publikationen/ and single publication pages work as editorial content
```

## Phase 3: Thin Payment Layer

1. create minimal payment record model
2. add publication modal submit flow
3. add gateway redirect creation
4. add webhook endpoint
5. add confirmation email

Deliverable:

```txt
publication payment works end to end without cart or inventory logic
```

## Phase 4: Optional Tour Reuse

Only after publications payment is stable:

1. review tour payment needs
2. extract only truly shared payment code
3. leave calendar sync inside `saas-api`

Deliverable:

```txt
shared payment helper if justified by real duplication
```

---

# 8. Acceptance Criteria

## Architecture

- `saas-api` contains no booking or payment domain logic
- `saas-api` remains pull/store/read infrastructure
- only reserved future extension in `saas-api` is push-back calendar sync

## Editorial

- publications do not read as webshop products
- archive and single pages use Industriesalon theme language
- price is visible but not dominant

## Functional

- publication can be listed
- publication detail page can show metadata
- action button opens modal
- modal can create gateway payment
- webhook updates local payment state
- confirmation email is sent after successful payment

## Non-Goals Confirmed

- no cart
- no inventory
- no shipping engine
- no generalized commerce platform
- no expansion of `saas-api` beyond calendar infrastructure
