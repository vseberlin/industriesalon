# Event Drop Staging Runbook

## Scope

Use this when enabling or repairing the Event Drop intake pipeline on staging.

The current pipeline is code plus bind mounts, not a tarball deploy:

- `/event-drop/` serves the checked-in intake interface from `ops/event-drop/interface/`.
- `/event-drop-storage` is a writable mounted storage root.
- Incoming uploads land in `/event-drop-storage/incoming`.
- The manifest is `/event-drop-storage/manifests/upload-manifest.csv`.
- `iss-content` syncs incoming files into private Editorial Sets/Intake Workbench state.
- Approved image items are imported into the WordPress Media Library during promotion.

Do not untar the repo or unpack participant ZIP uploads as part of this setup.
ZIP files are accepted as raw intake files for review; image promotion is the
implemented publishing path.

## Preconditions

- Staging is deployed from GitHub `main`.
- The target has a Docker Compose WordPress service equivalent to local
  `wordpress`.
- The target has enough disk space for raw uploads and later Media Library
  copies.
- Upload secrets are stored in the target `.env`, not in Git.

Before changing staging, inspect the host according to
`docs/agent/server-operations.md`:

```bash
uptime
free -h
df -h
docker compose ps
docker compose logs --tail=120 wordpress
```

## Deploy Code From GitHub

From the staging repo root:

```bash
git status --short
git fetch origin --prune
git rev-parse --short HEAD
git rev-parse --short origin/main
git log --oneline --left-right HEAD...origin/main
```

If the tree is clean and behind GitHub:

```bash
git merge --ff-only origin/main
```

If staging is dirty or diverged, stop and inspect. Do not untar a local copy
over the working tree.

## Create Storage Mount Source

Create the host-side storage tree before starting/recreating containers. This
prevents Docker from creating root-owned directories by accident.

```bash
mkdir -p var/event-drop-storage/incoming
mkdir -p var/event-drop-storage/accepted
mkdir -p var/event-drop-storage/rejected
mkdir -p var/event-drop-storage/manifests
chmod -R ug+rwX var/event-drop-storage
```

If the WordPress container cannot write there, inspect the runtime user first:

```bash
docker compose exec wordpress id
docker compose exec wordpress sh -lc 'ls -ld /event-drop-storage /event-drop-storage/incoming || true'
```

On the standard `wordpress:php8.2-apache` image, Apache/PHP normally writes as
`www-data` (`33:33`). Only if needed and permitted:

```bash
sudo chown -R 33:33 var/event-drop-storage
```

## Required Compose Mounts

The WordPress service must include these mounts:

```yaml
    volumes:
      - ./ops/event-drop/interface:/var/www/html/event-drop:ro
      - ./var/event-drop-storage:/event-drop-storage
      - ./docker/php/uploads.ini:/usr/local/etc/php/conf.d/uploads.ini:ro
```

The WP-CLI service should also mount storage and the PHP override:

```yaml
    volumes:
      - ./var/event-drop-storage:/event-drop-storage
      - ./docker/php/uploads.ini:/usr/local/etc/php/conf.d/uploads.ini:ro
```

The current repo already has these mounts in `docker-compose.yml`. On staging,
verify the rendered compose config instead of assuming:

```bash
docker compose config | grep -nE 'event-drop|uploads.ini|event-drop-storage'
```

## Upload Secrets

Set the upload code in the target `.env`:

```bash
EVENT_DROP_UPLOAD_CODE=replace-with-staging-code
```

Do not commit `.env`. The public upload URL then uses:

```text
/event-drop/?event=fete-de-la-musique-berlin-2026&code=replace-with-staging-code
```

Current editorial review/promotion happens in the WordPress Intake Workbench,
so the old standalone `/event-drop/?view=admin` page is not the main staging
workflow.

## Recreate WordPress With PHP Override

After mounts or `.env` changes:

```bash
docker compose up -d --no-deps --force-recreate wordpress
```

Verify the PHP limits loaded from `docker/php/uploads.ini`:

```bash
docker compose exec wordpress php -i | grep -E 'upload_max_filesize|post_max_size|memory_limit|max_execution_time|max_input_time'
docker compose run --rm wpcli eval 'echo size_format(wp_max_upload_size()).PHP_EOL;'
```

Expected local baseline:

```text
upload_max_filesize = 4096M
post_max_size = 4096M
memory_limit = 512M
max_execution_time = 0
max_input_time = 0
```

## Verify Intake Route And Storage

Check that Apache serves the mounted intake interface:

```bash
curl -I 'https://staging.industriesalon.info/event-drop/?event=fete-de-la-musique-berlin-2026'
```

Then verify from inside the container:

```bash
docker compose exec wordpress sh -lc 'test -r /var/www/html/event-drop/index.php && echo event-drop-interface-ok'
docker compose exec wordpress sh -lc 'test -w /event-drop-storage/incoming && test -w /event-drop-storage/manifests && echo event-drop-storage-writable'
docker compose run --rm wpcli eval 'echo function_exists("iss_content_editorial_sets_sync_event_drop_incoming") ? "sets-sync-ok\n" : "sets-sync-missing\n";'
```

After a browser upload, verify the file and manifest:

```bash
find var/event-drop-storage/incoming -maxdepth 1 -type f | wc -l
test -s var/event-drop-storage/manifests/upload-manifest.csv && tail -n 3 var/event-drop-storage/manifests/upload-manifest.csv
```

Open the WordPress Intake Workbench. Its REST read path calls
`iss_content_editorial_sets_sync_event_drop_incoming()` and should create/update
the Event Drop Set for the uploaded event.

## Promotion Check

For a real image test:

1. Upload through `/event-drop/?event=<veranstaltung-slug>&code=<code>`.
2. Open the Intake Workbench as an authorized user.
3. Confirm the uploaded item appears in the `event-drop-<slug>` Set.
4. Approve the item.
5. Promote it to the target Veranstaltung gallery.
6. Verify WordPress created an attachment under
   `wp-content/uploads/event-drop-storage/accepted/`.
7. Verify the Veranstaltung structured JSON gained a promoted `galerie`
   media reference.

Do not promote ZIP or video files into the current public gallery path. The
implemented promotion importer accepts image files.

## Legacy One-Off Bridge

`ops/event-drop/mu-plugins/event-drop-bridge.php` is the older one-off bridge
snapshot. Do not install it for the current Sets-backed staging workflow unless
the task is explicitly to restore the older accept-to-attachment behavior.

If a target still runs the older `/srv/industriesalon/stage/app` plus
`shared/event-drop` layout, prefer updating its compose mounts to the current
repo paths above. If that is not possible, copy with `rsync`, not tar:

```bash
mkdir -p shared/event-drop/interface
rsync -a --delete ops/event-drop/interface/ shared/event-drop/interface/
```

The storage path exposed to PHP must still be `/event-drop-storage`, because
both the intake interface and `iss-content` integration read that path.

## Rollback

Rollback is code plus mount state:

1. Return staging to the previous Git commit.
2. Recreate the WordPress container.
3. Keep `var/event-drop-storage` until its files have been reviewed or backed
   up; deleting it loses raw intake material.

## Closeout

Record the deployed commit, storage path, upload limit verification, route
check, and any uploaded test file cleanup in `handoff_CURRENT.md` or a
machine-local staging note.
