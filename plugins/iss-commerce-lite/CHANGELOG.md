# Changelog

All notable changes for `iss-commerce-lite` are documented here.

## [Unreleased]

### Changed
- Collapsed public intake to `POST /wp-json/iss-payments/v1/request`.
- Replaced domain-specific request kinds with universal `booking`, `inquiry`, and `order` intents for new writes.
- Removed legacy public tour-booking and publication-order route registration.

## [0.3.0] - 2026-06-13

### Changed
- Renamed the owning plugin to `iss-commerce-lite`.
- SuperSaaS adapter ownership moved to `iss-occurrences`; this plugin now owns request/order intake only.
- Added `wp iss-commerce-lite verify` as the production/deploy check command.

## [0.2.0] - 2026-06-13

### Added
- Added `wp_iss_payments_lite_requests` as durable request storage with legacy option migration.
- Added `Tools > ISS Anfragen` for request filters, CSV export, status updates, and notification/security settings.
- Added `wp iss-payments-lite verify` for production/deploy checks.
- Added opt-in request notification mail with stored notification state.

### Changed
- Public write endpoints now require REST nonce by default, enforce payload size and submit-timing checks, keep honeypot/rate-limit checks, and perform persistent duplicate checks against the request table.
- Payment methods default to `onsite` only; online settlement methods require an explicit provider integration before acceptance.

## [0.1.0] - 2026-04-26

### Added
- Added thin booking entry plugin to own public booking submit flow outside the SuperSaaS API adapter.
- Registered `POST /is-tours/v1/book` in `iss-payments-lite`.
- Preserved compatibility hook `is_tours_booking_created`.
- Added new hook `iss_payments_lite_booking_created` for future payment flow work.
