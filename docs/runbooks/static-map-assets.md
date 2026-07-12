# Static Map Asset Generation

Use this runbook when a published `register_place` coordinate, the canonical
Schöneweide artwork, calibration control point, or WebP encoding recipe changes.

## Authority

- Published `register_place` latitude/longitude is place-location authority.
- `themes/industriesalon/assets/maps/schoneweide-map-canonical.png` is the
  source/build image and must not be served by normal editorial maps.
- `schoneweide-map-calibration.json` is the tracked affine calibration contract.
- Marker JSON, the projection manifest, and 1024px/2048px WebPs are generated
  deployment artifacts. Do not edit them by hand.

## Generate

From the repository root:

```bash
tools/build-static-map-assets.sh
tools/generate-static-map-markers.sh
```

The image build requires ImageMagick. The marker wrapper runs WP-CLI as the host
UID/GID so the bind-mounted Git worktree keeps normal file ownership. It writes
an ignored QA overlay to:

```text
themes/industriesalon/assets/maps/qa/schoneweide-canonical-projection.svg
```

Open that SVG and inspect marker distribution before accepting changed
coordinates or calibration. Out-of-frame markers are valid when the underlying
place lies outside the artwork extent.

## Verify

The generation wrapper performs projection verification. Also run the public
map checks:

```bash
docker compose run --rm wpcli iss-relations map-markers verify --allow-root
docker compose run --rm wpcli iss-relations map-block-audit --allow-root
docker compose run --rm wpcli iss-relations static-map-contract-check --allow-root
```

Verification fails when the master checksum/dimensions no longer match the
calibration, calibration quality exceeds its thresholds, published place data
does not match generated markers, derivatives are missing, or the generated
manifest is stale.

## Deployment Pairing

Commit the calibration, marker JSON, projection manifest, responsive WebPs, and
relevant place-data migration together. No uploads artifact is used because map
assets are theme-owned files. On the target, run `map-markers verify`; do not
regenerate into an uncommitted production checkout merely to hide DB/code drift.

Browser-check desktop and mobile on `/fuehrungen/`, a Führung route,
`/schoneweide/`, and one `register_place`. Confirm mobile loads the 1024px WebP,
desktop loads the 2048px WebP, all selected markers remain visible, and the
no-JavaScript fallback still renders.
