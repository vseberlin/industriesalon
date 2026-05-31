# ISS Payments Lite

Thin booking and payment entry layer for Industriesalon domain plugins.

## Scope

Owns:

- booking submit endpoints
- thin payment entry flow
- webhook entry points
- compatibility hooks for downstream handling

Does not own:

- SuperSaaS sync
- calendar CPT storage
- slot mapping/cache
- timeline rendering
- tour or publication content models

## Current Role

Right now this plugin owns:

```txt
POST /wp-json/is-tours/v1/book
```

It exists to keep write-side booking/payment logic out of `saas-api`.
