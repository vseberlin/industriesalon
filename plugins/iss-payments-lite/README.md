# ISS Payments Lite

Thin booking and payment entry layer for Industriesalon domain plugins.

## Scope

Owns:

- booking submit endpoints
- durable lightweight request storage
- request admin review/export/status handling
- opt-in request notification emails
- public write endpoint spam/rate-limit guards
- compatibility hooks for downstream handling

Does not own:

- SuperSaaS sync
- calendar CPT storage
- slot mapping/cache
- timeline rendering
- tour or publication content models
- payment-provider settlement unless a dedicated provider integration is added

## Current Role

Right now this plugin owns:

```txt
POST /wp-json/is-tours/v1/book
POST /wp-json/iss-payments/v1/publication-order
Tools > ISS Anfragen
wp iss-payments-lite verify
```

It exists to keep write-side booking/payment logic out of `saas-api`.

Requests are stored in `wp_iss_payments_lite_requests`. The old capped
`is_tours_booking_requests` and `iss_publication_order_requests` options are
migration inputs only and are deleted after schema install.

By default the supported payment method is `onsite`. Online settlement methods
must be enabled by a provider integration through
`iss_payments_lite_supported_payment_methods`; do not accept a method until the
provider actually creates/settles payment state.

Request notification mail is opt-in from the admin screen. This keeps staging
safe unless outbound mail has been deliberately approved for the environment.
