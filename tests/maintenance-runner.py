#!/usr/bin/env python3
"""Exercise host orchestration/retention with disposable files and a fake Docker CLI."""
import datetime
import fcntl
import json
import os
from pathlib import Path
import shutil
import subprocess
import sys
import tempfile
import tarfile

repo = Path(__file__).resolve().parents[1]
base = repo / 'var' / 'maintenance-tests'
base.mkdir(parents=True, exist_ok=True, mode=0o700)
checks = 0

def expect(value, label):
    global checks
    checks += 1
    if not value:
        raise AssertionError(label)

with tempfile.TemporaryDirectory(dir=base) as temporary:
    root = Path(temporary)
    (root / 'shared/uploads').mkdir(parents=True)
    (root / 'shared/uploads/example.txt').write_text('Known upload bytes\n')
    (root / 'compose.yml').write_text('services: {}\n')
    (root / '.env').write_text('FIXTURE_ONLY=1\n')
    (root / 'repo').mkdir()
    subprocess.run(['git', 'init', '-q', root / 'repo'], check=True)
    (root / 'repo/source.txt').write_text('Known code bytes\n')
    subprocess.run(['git', '-C', root / 'repo', 'add', '.'], check=True)
    subprocess.run(['git', '-C', root / 'repo', '-c', 'user.name=Fixture', '-c', 'user.email=fixture@example.test', 'commit', '-qm', 'Fixture'], check=True)
    (root / 'repo/.git/info/exclude').write_text('/var/\n')
    (root / 'repo/var/event-drop-storage').mkdir(parents=True)
    (root / 'repo/var/event-drop-storage/intake.txt').write_text('Known private intake bytes\n')
    (root / 'bin').mkdir()
    docker = root / 'bin/docker'
    docker.write_text('''#!/usr/bin/env python3
import io,json,os,sys,tarfile,time
from pathlib import Path
root=Path(os.environ['ISS_RUNNER_FIXTURE']);args=sys.argv[1:]
with (root/'commands.jsonl').open('a') as log: log.write(json.dumps(args)+'\\n')
if args[0]=='inspect':sys.exit(0 if (root/'orphan').exists() else 1)
if args[0]=='stop':sys.exit(0)
if 'exec' in args:
    if any('du -sb' in arg for arg in args):print('42 .');sys.exit(0)
    if 'mariadb' in args:
        print('CREATE TABLE fixture (id int);' if not (root/'bad-dump').exists() else 'incomplete dump')
    else:
        with tarfile.open(fileobj=sys.stdout.buffer,mode='w|') as tar:
            payload=b'Fixture config, no secrets';member=tarfile.TarInfo('wp-config.php');member.size=len(payload);tar.addfile(member,io.BytesIO(payload))
    sys.exit(0)
phase='cron' if 'cron' in args else 'graph' if 'iss-graph' in args else 'health'
if (root/('fail-'+phase)).exists():sys.exit(1)
if (root/('timeout-'+phase)).exists():sys.exit(124)
print('Fixture '+phase+' succeeded')
''')
    docker.chmod(0o700)
    env = dict(os.environ, PATH=str(root / 'bin') + os.pathsep + os.environ['PATH'], ISS_RUNNER_FIXTURE=str(root))
    def run(script, mode='run'):
        return subprocess.run(['bash', str(repo / 'ops' / script), mode, str(root)], env=env, capture_output=True, text=True)
    expect(run('maintenance-stage.sh', 'check').returncode == 0 and not (root / 'var').exists(), 'Check is read-only')
    expect(run('backup-stage.sh', 'check').returncode == 0 and not (root / 'backups').exists(), 'Backup plan is read-only')
    first = run('maintenance-stage.sh')
    expect(first.returncode == 0, 'Runner completes all fixture phases: ' + first.stderr)
    state = root / 'var/maintenance'
    receipt = json.loads((state / 'last-run.json').read_text())
    expect(receipt['status'] == 'ok' and set(receipt['exit_codes'].values()) == {0}, 'Complete run records success')
    expect(run('maintenance-stage.sh', 'status').returncode == 0, 'Fresh success is observable')
    backup = Path(json.loads((state / 'backup-last-success.json').read_text())['backup'])
    manifest = json.loads((backup / 'manifest.json').read_text())
    expect(manifest['tables'] == 1 and len(manifest['sha256']) == 7 and 'event-drop-storage.tar.gz' in manifest['sha256'], 'All seven backup artifacts including private intake are validated and hashed')
    with tarfile.open(backup / 'event-drop-storage.tar.gz') as archive:
        expect(archive.extractfile('event-drop-storage/intake.txt').read() == b'Known private intake bytes\n', 'Private intake archive restores the expected file bytes')
    expect(backup.stat().st_mode & 0o777 == 0o700 and all(p.stat().st_mode & 0o077 == 0 for p in backup.iterdir()), 'Backup files and credentials stay private')
    commands = [json.loads(line) for line in (root / 'commands.jsonl').read_text().splitlines()]
    wp = [args for args in commands if 'wpcli' in args]
    expect('cron' in wp[0] and 'iss-graph' in wp[1] and 'maintenance-status' in wp[2], 'Cron, graph and health use one sequential path')
    expect(all('--pull=never' in args and '--name' in args for args in wp), 'Runner cannot pull an untested image; container identity is explicit')
    count = len(commands)
    with (state / 'runner.lock').open('w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        expect(run('maintenance-stage.sh').returncode == 0, 'Overlapping scheduler is skipped')
    expect(len((root / 'commands.jsonl').read_text().splitlines()) == count, 'Overlapping scheduler executes no Docker command')
    last_success = receipt['last_success_at']
    (root / 'fail-cron').touch()
    expect(run('maintenance-stage.sh').returncode != 0, 'Cron failure reaches host scheduler')
    receipt = json.loads((state / 'last-run.json').read_text())
    expect(receipt['exit_codes']['wp_cron'] == 1 and receipt['exit_codes']['graph'] == 0 and receipt['last_success_at'] == last_success, 'Independent graph work continues and last success survives cron failure')
    expect(run('maintenance-stage.sh', 'status').returncode != 0, 'Failed runner remains visible')
    (root / 'fail-cron').unlink(); (root / 'timeout-graph').touch()
    expect(run('maintenance-stage.sh').returncode != 0, 'Timed-out worker fails the run')
    expect(any(json.loads(line)[0] == 'stop' for line in (root / 'commands.jsonl').read_text().splitlines()), 'Timeout stops the named worker before releasing host lock')
    (root / 'timeout-graph').unlink(); (root / 'orphan').touch()
    expect(run('maintenance-stage.sh').returncode != 0, 'An orphaned worker is reported, not overlapped')
    (root / 'orphan').unlink()
    # Retention candidates are managed, complete fixture backups only.
    manual = root / 'backups/20260922-132743'; manual.mkdir(); (manual / 'retained.txt').write_text('manual backup')
    incomplete = root / 'backups/iss-backup-20000101T000000Z.partial'; incomplete.mkdir()
    outside = root / 'outside-retention'; outside.mkdir(); (outside / 'keep').write_text('untouched')
    (root / 'backups/iss-backup-19990101T000000Z').symlink_to(outside, target_is_directory=True)
    for age in range(1, 32):
        moment = datetime.datetime.now(datetime.timezone.utc) - datetime.timedelta(days=age)
        shutil.copytree(backup, backup.parent / ('iss-backup-' + moment.strftime('%Y%m%dT%H%M%SZ')))
    # Ensure a new name without delaying the test: move the current complete copy to an earlier second.
    current_stamp = datetime.datetime.strptime(backup.name.removeprefix('iss-backup-'), '%Y%m%dT%H%M%SZ')
    backup.rename(backup.with_name('iss-backup-' + (current_stamp - datetime.timedelta(seconds=2)).strftime('%Y%m%dT%H%M%SZ')))
    expect(run('backup-stage.sh').returncode == 0, 'Fresh verified backup permits managed retention')
    managed = [p for p in backup.parent.glob('iss-backup-*') if p.is_dir() and not p.is_symlink() and not p.name.endswith('.partial')]
    expect(14 <= len(managed) <= 18, 'Retention keeps daily and weekly restore points without accumulating every copy')
    expect((manual / 'retained.txt').is_file() and incomplete.is_dir() and (outside / 'keep').is_file(), 'Manual, incomplete and symlink targets are never pruned')
    (root / 'bad-dump').touch()
    latest = Path(json.loads((state / 'backup-last-success.json').read_text())['backup'])
    latest.rename(latest.with_name('iss-backup-' + (datetime.datetime.strptime(latest.name.removeprefix('iss-backup-'), '%Y%m%dT%H%M%SZ') - datetime.timedelta(seconds=1)).strftime('%Y%m%dT%H%M%SZ')))
    previous = (state / 'backup-last-success.json').read_text()
    expect(run('backup-stage.sh').returncode != 0, 'Malformed dump cannot become a completed backup')
    expect((state / 'backup-last-success.json').read_text() == previous, 'Failed backup cannot advance last success or prune retained copies')
print(f'Maintenance runner: {checks} checks passed; disposable fixture removed; no real Docker jobs or notifications.')
