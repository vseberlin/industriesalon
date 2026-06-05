# Continuity Rules

Use this for handoff, changelog, TODO, memory, skill, commit, and closeout work.

## File Roles

- `AGENTS.md`: compact default rules loaded by agents.
- `docs/agent/`: shareable detailed rules for local and staging agents.
- `handoff_CURRENT.md`: current checkpoint only. Keep it short and operational.
- `CHANGELOG.md`: durable historical record of changes and reasons.
- `TODO.md`: active follow-up items.
- Local Codex memories: compact retrieval hints and prior-run facts, not copied repo policy.
- Local Codex skills: compact procedures for repeated workflows, not broad project history.

## Handoff Discipline

`handoff_CURRENT.md` should answer:

- What is the current repo/deploy state?
- What is the active source-of-truth risk?
- What must be preserved?
- What are the next actions?
- What was actually verified?

Do not turn it into a chronological session log. Move history to `CHANGELOG.md` and future work to `TODO.md`.

## Changelog Discipline

Every substantive change needs a concise entry explaining the reason and affected surface. Keep entries factual and grouped by checkpoint. Do not duplicate the full handoff.

## Memory And Skill Discipline

- Keep memory entries small, searchable, and scoped to recurring facts that are hard to rediscover.
- Keep skills procedural and trigger-specific.
- Do not put repo-wide policy only in local memory or local skills; staging cannot share it.
- If a procedure must work on staging too, document the policy in `docs/agent/` and let local skills point to it.
- Avoid copying large docs into memories. Use pointers to repo files and exact commands.

## Closeout Pattern

When the user asks to write handoff/changelog, commit, exit, or stop after implementation:

1. Read `handoff_CURRENT.md`, `CHANGELOG.md`, and `TODO.md` as needed.
2. Check `git status --short`.
3. Reconstruct the actual checkpoint from diffs and verification, not memory.
4. Update `CHANGELOG.md` for historical trace.
5. Update `handoff_CURRENT.md` only with current state, caveats, and next actions.
6. Update `TODO.md` only for active follow-up.
7. If committing, stage only the intended files and re-check status after commit.

Do not silently leave important next steps only in chat.
