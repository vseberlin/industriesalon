# Git Exchange Runbook

## Scope

Use this when starting work, exiting work, pulling staging feedback, checking GitHub state, or coordinating local/staging agents through GitHub `main`.

## Preconditions

- Current clone path is known.
- GitHub `origin/main` is the exchange point.
- No destructive Git operation is approved by default.

## Inspect First

```bash
git status --short
git fetch origin --prune
git rev-parse --short HEAD
git rev-parse --short origin/main
git log --oneline --left-right HEAD...origin/main
```

## Start Procedure

1. Run the inspect commands.
2. If clean and behind `origin/main`, run `git merge --ff-only origin/main`.
3. If clean and equal, continue.
4. If clean and ahead, do not pull; decide whether the local checkpoint should be pushed.
5. If diverged, stop and inspect. Do not auto-merge shared `main`.
6. If dirty, do not pull until changes are classified as yours, user changes, generated output, or machine-local state.

## Exit Procedure

1. Classify changes.
2. Commit durable repo changes when requested or when the checkpoint needs to be shared.
3. Push committed shared work to `origin main`.
4. Leave machine-local runtime state out of Git.
5. Report `HEAD`, `origin/main`, clean/dirty state, pushed/not pushed, and next action for the other agent.

## On-Demand Sync

Use the start procedure when the user says to sync, pull staging feedback, check GitHub, deploy, or inspect remote changes.

## Verification

```bash
git status --short
git rev-parse --short HEAD
git rev-parse --short origin/main
git log --oneline --left-right HEAD...origin/main
```

## Rollback

No automatic rollback. Ask before destructive recovery such as reset, checkout-overwrite, rebase, or force push.

## What To Document

- `handoff_CURRENT.md`: current checkpoint, risk, next action, verification.
- `CHANGELOG.md`: durable repo changes.
- Machine-local notes: volatile runtime state and secrets locations.

## Known Pitfalls

- Pulling into a dirty tree hides ownership of local changes.
- Merge commits on shared `main` make local/staging coordination harder unless intentional.
- Staging can advance `origin/main`; local agents must fetch before assuming their clone is current.
