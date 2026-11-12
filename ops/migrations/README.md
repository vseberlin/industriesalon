# Migration programs and private inputs

> **Status 12 November 2026:** the staging VPS (`staging.industriesalon.info`) is being retired and is no longer a deployment, sync or recovery target. Staging references below are historical. See [hosting status](../../docs/infrastructure/hosting-status.md).

PHP migration programs are versioned here. SQL exports, content JSON, media
archives, checksums and manifests are transferred privately, outside GitHub.
Historical programs may expect JSON alongside the PHP file or media under
`ops/uploads/`; restore the matching release inputs before running them.
A fresh checkout intentionally contains no deployment data.

See [data artifacts](../../docs/infrastructure/data-artifacts.md) and
[sync](../../docs/infrastructure/sync.md) for backup and verification rules.
The 16 October 2026 removal preserved the prior inputs in the private
`prior-tracked-data.tar.gz` release archive on local and staging.
