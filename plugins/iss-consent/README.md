# ISS Consent

Consent decisions and external-media gating belong here; the theme owns the
notices, native dialog, footer link and privacy text. There was no existing
consent owner. WordPress Site Health and ISS Core Operations own the maintenance
surfaces; no consent vendor, scheduler, database table or visitor identifier is
added.

YouTube frames are inert in server-rendered native blocks and the custom video
renderer. The video library asks before playback. Previews use local Media Library
attachments via the ordinary video featured-image field. Atlas retains CARTO as its basemap; only explicit CARTO
consent enables tiles. It has no static-map replacement. Leaflet and map data remain local. Withdrawal
removes frames/tiles and works across tabs. Scripts unavailable means external
content stays blocked. This gate covers these integrations, not arbitrary new
third-party code: new embeds/scripts need an explicit review and integration.

`iss-consent` in localStorage contains only service booleans, decision timestamp
and policy version. It expires after 180 days; unavailable storage limits the
choice to the current page. Increment `ISS_CONSENT_POLICY` when providers,
purposes or material consent information change. Do not increment it for every
code release. Consent is voluntary, separate for each service and revocable in
**Datenschutzeinstellungen**. A compact, nonmodal bottom popup appears until a
choice has been stored. Accept all and decline all are equally visible; native
Details reveals individual choices. Closing the popup makes no decision.

The existing `iss-newsletter/privacy.php` adapter enforces double opt-in, disables
individual message tracking (including existing drafts), suppresses the vendor's
two persistent identity cookies, and keeps old signed links usable without click
or open collection. Vendor-specific hooks, cookie names and signed-link formats
are maintenance dependencies. Confirmation, unsubscribe and subscriber delivery
remain owned by The Newsletter Plugin. Ordinary payment initiation remains owned
by commerce/Mollie and does not require optional-media consent.

## Installation and recovery

Ship the plugin, theme, video/Atlas integrations and newsletter adapter together.
Apply `ops/migrations/2026-10-23-consent.php` through WP-CLI (`check`, then `apply`
with a new private before-image path, then `verify`). It activates this plugin,
updates only the listed newsletter option fields and selects Verein as the
WordPress privacy page. Preserve the target's existing options/accounts. The
migration refuses DB overrides of the privacy template/footer; reconcile them
before applying. It needs no SQL dump or subscriber import. Local video previews
need the paired media/API import below. The existing map image and calibration
manifest must be present on the target.

For recovery retain the reviewed code and target-local settings before-image.
Restore only affected option fields after checking for subsequent edits; never
replay `active_plugins` wholesale over later changes. Do not disable the gate
while leaving external embeds available. A privacy regression should first
suspend the affected external integration or newsletter delivery, then be fixed
and retested. Reverting to automatic third-party requests is not a safe rollback.

## Maintenance contract

Follow [the regular maintenance procedure](../../docs/runbooks/staging-maintenance.md#consent-and-privacy-review).
`wp iss consent-check` is read-only. A passing browser report is explicitly
recorded with `wp iss consent-record-browser FILE`; configuration checks cannot
stand in for browser evidence. Reports and screenshots stay private. An update
hook, plugin/core/theme versions and relevant source hashes invalidate previous
acceptance. A matching report expires after 30 days. These checks are technical
evidence, not proof of all legal duties or a per-visitor consent audit log.

The PHP fixture `tests/e2e/bin/consent.php` exercises native embeds, real newsletter
message generation, cookie filtering, signed links, setting
drift and review expiry. It requires disabled WP-Cron and no persistent object
cache, uses connection-local temporary options, and forbids mail/external HTTP.
Run it on the isolated development fixture, not as a production health probe.
`npm run test:consent` checks actual frontend requests and interactions, using
stubbed third-party responses so test identifiers never reach providers. Real
provider playback/tile availability remains a separate manual acceptance check.

## Disclosure facts to maintain

The privacy notice lives in the native `page-verein.html` template at
`/verein/#datenschutz`; ordinary WordPress privacy links resolve there. Hetzner
hosting and the newsletter sender domain `industriesalon.de` were confirmed by
the site owner. A sender domain does not establish the SMTP operator. Before
production release, verify the actual mail delivery supplier, processing
agreements, transfer arrangements and operational retention/deletion practice;
update the notice to match. Existing production activation gates still apply.

Primary references reviewed 23 October 2026:

- [TDDDG §25](https://www.gesetze-im-internet.de/ttdsg/__25.html)
- [German supervisory guidance, November 2024](https://www.lfd.niedersachsen.de/download/214714)
- [Hetzner](https://www.hetzner.com/de/legal/privacy-policy/)
- [Google privacy](https://policies.google.com/privacy?hl=de) and [transfers](https://policies.google.com/privacy/frameworks?hl=de)
- [CARTO Basemap terms](https://www.carto.com/legal/basemap-terms/)
- [Mollie privacy](https://www.mollie.com/de/legal/privacy)

## Local video previews

The `iss-content` video import owner provides `wp iss-content videos thumbnails`.
It lists published videos without featured images. With `--apply` and a new
private `--before=/PRIVATE/before.json` it imports the existing YouTube thumbnail
on the server into the Media Library and assigns the normal featured image.
Existing editorial images are never replaced; repeat runs skip completed posts.
The server fetches only fixed YouTube image URLs with validated video IDs, bounded
responses and image validation. No visitor request triggers an external fetch.
A new video can use an editor-selected image or this explicit import; missing
local files/previews appear in the consent health check.

For release, pair the private originals archive/checksum and manifest with this
owner command. Verify all hashes, unpack to a private directory and run:

```sh
wp iss-content videos thumbnails
wp iss-content videos thumbnails --apply --before=/PRIVATE/new-before.json --source-dir=/PRIVATE/thumbnails-source
```

Files must be named `youtube-VIDEOID.jpg`. The offline import resolves the target's
own published video URLs, assigns only missing featured images, and regenerates
attachment IDs and image sizes through WordPress. It never imports users, video
text or source IDs. Use the manifest's source URLs as provenance; the offline
attachment records its video source. Review mismatched inventories before apply.
Keep the originals, manifest and before-image together outside Git. After import,
verify all published previews, idempotent rerun and before-consent browser tests.
For rollback restore each changed `_thumbnail_id` only if it still points at the
imported image. Delete newly created attachments only after checking that no
later editorial content references them; never remove pre-existing images.
