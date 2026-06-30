# ISS Commerce Lite

Lightweight universal request capture for Industriesalon domain plugins.

## Scope

Owns:

- universal booking, inquiry, and order submit endpoint
- durable lightweight request storage
- request admin review/export/status handling
- opt-in request notification emails
- public write endpoint spam/rate-limit guards

Does not own:

- calendar CPT storage
- slot mapping/cache
- SuperSaaS settings, occurrence sync, or public booking-slot reads
- timeline rendering
- tour, event, exhibition, or publication content models
- payment-provider settlement unless a dedicated provider integration is added

## Current Role

Right now this plugin owns:

```txt
POST /wp-json/iss-payments/v1/request
Tools > ISS Anfragen
wp iss-commerce-lite verify
```

`/iss-payments/v1/request` is the only public intake route for shared booking,
inquiry, and order flows. It accepts `intent` values for `booking`, `inquiry`,
and `order`.

SuperSaaS settings, occurrence ingestion, and `GET /wp-json/iss/v1/booking-slots`
belong to `iss-occurrences`.

Requests are stored in `wp_iss_payments_lite_requests`. The table keeps the
existing `request_kind` column name, but new writes store normalized intent
values there.

By default the supported payment method is `onsite`. Online settlement methods
must be enabled by a provider integration through
`iss_payments_lite_supported_payment_methods`; do not accept a method until the
provider actually creates/settles payment state.

Request notification mail is opt-in from the admin screen. This keeps staging
safe unless outbound mail has been deliberately approved for the environment.
