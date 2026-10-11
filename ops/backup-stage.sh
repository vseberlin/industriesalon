#!/usr/bin/env bash
# Source-controlled successor to the existing target ops/backup-stage.sh.
set -euo pipefail
mode=${1:-check}
stage_root=${2:-}
if [[ ! "$mode" =~ ^(check|run)$ || -z "$stage_root" || ! -d "$stage_root" ]]; then
  echo 'Usage: backup-stage.sh check|run /absolute/stage/root' >&2; exit 2
fi
stage_root=$(realpath "$stage_root")
[[ -r "$stage_root/compose.yml" && -r "$stage_root/.env" && -d "$stage_root/shared/uploads" && -d "$stage_root/repo/var/event-drop-storage" && -d "$stage_root/repo/.git" ]] || { echo 'Runtime, uploads, Event Drop storage and repository required.' >&2; exit 2; }
if [[ "$mode" == check ]]; then
  echo 'Plan: private DB/uploads/intake/code/config archives, integrity manifests, 14 daily and 4 weekly verified copies.'
  echo 'Existing manual backups are retained. No backup or pruning executed.'; exit 0
fi
umask 077
mkdir -p "$stage_root/var/maintenance"
exec 8>"$stage_root/var/maintenance/backup.lock"
flock -n 8 || { echo 'Backup already running.' >&2; exit 1; }
cd "$stage_root"
[[ -z "$(git -C repo status --porcelain)" ]] || { echo 'Commit/review target code before a reproducible backup; no checkout reset performed.' >&2; exit 1; }
python3 - "$stage_root" <<'PY'
import shutil,subprocess,sys
root=sys.argv[1]
size=sum(int(subprocess.check_output(['du','-sb',root+'/'+path]).split()[0]) for path in ['shared/uploads','repo/var/event-drop-storage'])
size+=int(subprocess.check_output(['docker','compose','exec','-T','wordpress','sh','-c','cd /var/www/html && du -sb --exclude=./wp-content/uploads --exclude=./event-drop .'],stdin=subprocess.DEVNULL).split()[0])
if shutil.disk_usage(root).free<size+3*1024**3: raise SystemExit('Insufficient free space for files plus database/code and reserve.')
PY
stamp=$(date -u +%Y%m%dT%H%M%SZ)
backup_root="$stage_root/backups"
mkdir -p "$backup_root"
partial="$backup_root/iss-backup-$stamp.partial"
complete="$backup_root/iss-backup-$stamp"
[[ ! -e "$complete" ]] || { echo 'Backup timestamp already exists.' >&2; exit 1; }
mkdir -m 700 "$partial"
# Read credentials inside the existing DB service; never put passwords in argv.
docker compose exec -T mariadb sh -c 'MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb-dump --single-transaction --quick --lock-tables=false -u"$MARIADB_USER" "$MARIADB_DATABASE"' </dev/null | gzip > "$partial/database.sql.gz"
tar -C "$stage_root/shared" -czf "$partial/uploads.tar.gz" uploads
tar -C "$stage_root/repo/var" -czf "$partial/event-drop-storage.tar.gz" event-drop-storage
git -C "$stage_root/repo" archive HEAD | gzip > "$partial/code.tar.gz"
tar -C "$stage_root" -czf "$partial/runtime-config.tar.gz" compose.yml .env
# Capture installed core/third-party plugins as well as config: not all runtime
# packages belong to Git. Large uploads/intake already have dedicated archives.
docker compose exec -T wordpress tar -C /var/www/html --exclude=./wp-content/uploads --exclude=./event-drop -cf - . </dev/null | gzip > "$partial/wordpress-runtime.tar.gz"
git -C "$stage_root/repo" rev-parse HEAD > "$partial/code-commit.txt"
python3 - "$partial" "$complete" "$stage_root" <<'PY'
import datetime,gzip,hashlib,json,os,re,shutil,sys,tarfile,time
from pathlib import Path
partial,complete,root=map(Path,sys.argv[1:]);hashes={}
for path in sorted(partial.iterdir()):
    if path.name.endswith('.tar.gz'):
        # Read through the gzip trailer too; tar iteration can stop before CRC.
        with gzip.open(path,'rb') as compressed:
            for chunk in iter(lambda:compressed.read(1024*1024),b''): pass
        with tarfile.open(path,'r|gz') as archive:
            count=sum(1 for member in archive)
        if not count: raise SystemExit('Empty file archive: '+path.name)
    elif path.name=='database.sql.gz':
        with gzip.open(path,'rt',encoding='utf8',errors='replace') as dump:
            tables=sum(1 for line in dump if line.startswith('CREATE TABLE'))
        if tables<1: raise SystemExit('Database dump has no schema; incomplete backup retained for inspection.')
    digest=hashlib.sha256()
    with path.open('rb') as stream:
        for chunk in iter(lambda:stream.read(1024*1024),b''): digest.update(chunk)
    hashes[path.name]=digest.hexdigest()
manifest={'owner':'iss-stage-backup-v1','root':str(root),'finished_at':int(time.time()),'tables':tables,'sha256':hashes}
(partial/'manifest.json').write_text(json.dumps(manifest,indent=2)+'\n')
# Only a fully read and verified archive set may become a retention candidate.
partial.rename(complete)
owned=[]
for candidate in complete.parent.iterdir():
    if candidate.is_symlink() or not candidate.is_dir() or not re.fullmatch(r'iss-backup-\d{8}T\d{6}Z',candidate.name): continue
    marker=candidate/'manifest.json'
    if not marker.is_file(): continue
    try: data=json.loads(marker.read_text())
    except (ValueError,OSError): continue
    if data.get('owner')!='iss-stage-backup-v1' or data.get('root')!=str(root): continue
    if not all((candidate/name).is_file() for name in hashes): continue
    try: datetime.datetime.strptime(candidate.name.removeprefix('iss-backup-'),'%Y%m%dT%H%M%SZ')
    except ValueError: continue
    owned.append(candidate)
# Keep newest complete backup for each of the last 14 days and 4 ISO weeks.
keep={complete};days=set();weeks=set()
for candidate in sorted(owned,reverse=True):
    moment=datetime.datetime.strptime(candidate.name.removeprefix('iss-backup-'),'%Y%m%dT%H%M%SZ')
    day=moment.date();week=moment.isocalendar()[:2]
    if day not in days and len(days)<14: keep.add(candidate);days.add(day)
    if week not in weeks and len(weeks)<4: keep.add(candidate);weeks.add(week)
for candidate in owned:
    if candidate not in keep: shutil.rmtree(candidate)
p=root/'var/maintenance/backup-last-success.json';tmp=p.with_suffix('.tmp')
tmp.write_text(json.dumps({'finished_at':manifest['finished_at'],'backup':str(complete)},indent=2)+'\n');os.chmod(tmp,0o600);tmp.replace(p)
print('Verified backup:',complete.name,'tables:',tables,'retained managed copies:',len(keep))
PY
