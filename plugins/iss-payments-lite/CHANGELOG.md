# Changelog

All notable changes for `iss-payments-lite` are documented here.

## [0.1.0] - 2026-04-26

### Added
- Added thin booking entry plugin to own public booking submit flow outside `saas-api`.
- Registered `POST /is-tours/v1/book` in `iss-payments-lite`.
- Preserved compatibility hook `is_tours_booking_created`.
- Added new hook `iss_payments_lite_booking_created` for future payment flow work.
