#!/usr/bin/env bash
# Bounded commerce minute job, independent of long backup/maintenance locks.
set -u -o pipefail
mode=${1:-check}
stage_root=${2:-}
if [[ ! "$mode" =~ ^(check|run|status)$ || -z "$stage_root" || ! -d "$stage_root" ]]; then
  echo 'Usage: commerce-stage.sh check|run|status /absolute/stage/root' >&2; exit 2
fi
stage_root=$(realpath "$stage_root")
state_dir="$stage_root/var/commerce"
if [[ "$mode" == status ]]; then
  python3 - "$state_dir/last-run.json" <<'PY'
import json,sys,time
from pathlib import Path
p=Path(sys.argv[1]);r=json.loads(p.read_text()) if p.is_file() else {}
print(json.dumps(r,indent=2));sys.exit(0 if r.get('exit_code')==0 and time.time()-r.get('finished_at',0)<180 else 1)
PY
  exit $?
fi
for command in docker flock timeout python3; do command -v "$command" >/dev/null || exit 2; done
[[ -r "$stage_root/compose.yml" ]] || { echo 'Runtime Compose required.' >&2; exit 2; }
if [[ "$mode" == check ]]; then echo 'Commerce only: independent flock, 55s limit, 20-item batches, private status receipt. No jobs executed.'; exit 0; fi
umask 077
mkdir -p "$state_dir" || exit 1
exec 9>"$state_dir/runner.lock"
flock -n 9 || exit 0
cd "$stage_root" || exit 1
exec >"$state_dir/last-run.log" 2>&1
wp_container=iss-stage-commerce-wpcli
if docker inspect "$wp_container" >/dev/null 2>&1; then echo 'Prior commerce container remains; inspect before retry.' >&2; exit 75; fi
stop_active() { docker stop --time 5 "$wp_container" >/dev/null 2>&1 || true; }
trap 'stop_active; exit 130' INT TERM
started=$(date +%s)
timeout --signal=INT --kill-after=10s 55s docker compose run --rm -T --no-deps --pull=never --name "$wp_container" wpcli \
  --exec='if (!defined("DISABLE_WP_CRON")) { define("DISABLE_WP_CRON", true); }' iss-commerce-lite run --batch=20 --seconds=40
code=$?
if [[ "$code" == 124 || "$code" == 137 ]]; then stop_active; fi
python3 - "$state_dir/last-run.json" "$started" "$code" <<'PY'
import json,os,sys,time
from pathlib import Path
p=Path(sys.argv[1]);r={'started_at':int(sys.argv[2]),'finished_at':int(time.time()),'exit_code':int(sys.argv[3]),'log':'last-run.log'}
tmp=p.with_suffix('.tmp');tmp.write_text(json.dumps(r,indent=2)+'\n');os.chmod(tmp,0o600);tmp.replace(p)
PY
exit "$code"
