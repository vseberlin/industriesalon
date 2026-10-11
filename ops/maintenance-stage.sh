#!/usr/bin/env bash
# One host scheduler entry; never run this against staging without deployment approval.
set -u -o pipefail
mode=${1:-check}
stage_root=${2:-}
if [[ ! "$mode" =~ ^(check|run|status)$ || -z "$stage_root" || ! -d "$stage_root" ]]; then
  echo 'Usage: maintenance-stage.sh check|run|status /absolute/stage/root' >&2; exit 2
fi
stage_root=$(realpath "$stage_root")
script_dir=$(cd -- "$(dirname -- "$0")" && pwd)
state_dir="$stage_root/var/maintenance"
if [[ "$mode" == status ]]; then
  python3 - "$state_dir/last-run.json" <<'PY'
import json,sys,time
from pathlib import Path
p=Path(sys.argv[1])
if not p.is_file(): print('No maintenance receipt; inspect scheduler installation.');sys.exit(1)
r=json.loads(p.read_text()); print(json.dumps(r,indent=2))
sys.exit(0 if r.get('status')=='ok' and time.time()-r.get('finished_at',0)<600 else 1)
PY
  exit $?
fi
for command in docker flock timeout python3; do command -v "$command" >/dev/null || exit 2; done
[[ -r "$stage_root/compose.yml" && -f "$script_dir/backup-stage.sh" ]] || { echo 'Runtime Compose and paired backup script required.' >&2; exit 2; }
if [[ "$mode" == check ]]; then
  echo 'Plan: one flock; due WP-Cron (180s); graph queue (50 rows/30s); daily verified backup; owner status.'
  echo 'No jobs, mail, sync, backup or cleanup executed.'; exit 0
fi
umask 077
mkdir -p "$state_dir" || exit 1
exec 9>"$state_dir/runner.lock"
flock -n 9 || { echo 'Maintenance already running.'; exit 0; }
cd "$stage_root" || exit 1
exec >"$state_dir/last-run.log" 2>&1
wp_container=iss-stage-maintenance-wpcli
active_wp=0
stop_active() {
  if [[ "$active_wp" == 1 ]]; then docker stop --time 10 "$wp_container" >/dev/null 2>&1 || true; fi
}
trap 'stop_active; exit 130' INT TERM
run_wp() {
  if docker inspect "$wp_container" >/dev/null 2>&1; then
    echo "A prior maintenance container remains; inspect it before retrying." >&2; return 75
  fi
  active_wp=1
  timeout --signal=INT --kill-after=15s 180s docker compose run --rm -T --no-deps --pull=never --name "$wp_container" wpcli \
    --exec='if (!defined("DISABLE_WP_CRON")) { define("DISABLE_WP_CRON", true); }' "$@"
  local code=$?
  if [[ "$code" == 124 || "$code" == 137 ]]; then stop_active; fi
  active_wp=0
  return "$code"
}
started=$(date +%s)
run_wp cron event run --due-now; cron_code=$?
run_wp iss-graph reconcile --batch-size=50 --max-runtime=30 --max-attempts=3; graph_code=$?
backup_code=0
if ! python3 - "$state_dir/backup-last-success.json" <<'PY'
import json,sys,time
from pathlib import Path
p=Path(sys.argv[1]);r=json.loads(p.read_text()) if p.is_file() else {}
sys.exit(0 if time.time()-r.get('finished_at',0)<86400 else 1)
PY
then
  timeout --signal=INT --kill-after=30s 1800s bash "$script_dir/backup-stage.sh" run "$stage_root"; backup_code=$?
fi
run_wp iss maintenance-status; health_code=$?
python3 - "$state_dir/last-run.json" "$started" "$cron_code" "$graph_code" "$backup_code" "$health_code" <<'PY'
import json,os,sys,time
from pathlib import Path
p=Path(sys.argv[1]);previous=json.loads(p.read_text()) if p.is_file() else {}
codes=dict(zip(['wp_cron','graph','backup','owner_status'],map(int,sys.argv[3:])))
now=int(time.time());ok=not any(codes.values())
r={'started_at':int(sys.argv[2]),'finished_at':now,'status':'ok' if ok else 'failed','last_success_at':now if ok else previous.get('last_success_at',0),'exit_codes':codes,'log':'last-run.log'}
tmp=p.with_suffix('.tmp');tmp.write_text(json.dumps(r,indent=2)+'\n');os.chmod(tmp,0o600);tmp.replace(p)
sys.exit(0 if ok else 1)
PY
