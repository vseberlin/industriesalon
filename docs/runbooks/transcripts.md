# Transcripts Runbook

## Scope

Use this for local video transcript work.

## Preconditions

- Target Video CPT posts are identified.
- Backup/export expectations are known.

## Inspect First

- existing transcript content/meta
- source availability
- caption availability

## Rules

- Keep local transcription tooling out of Git unless explicitly approved.
- Prefer captions-first import before expensive local speech-to-text fallback.
- Preserve database state before large transcript rewrites.
- Transfer transcript data through explicit SQL artifacts, not hidden runtime migration.
- Stop CPU-only fallback runs when they are clearly impractical and document the remaining path in `TODO.md` or `handoff_CURRENT.md`.

## Procedure

Prefer captions-first import. Use fallback transcription only when captions are unavailable and runtime is practical.

## Verification

Check post content, transcript meta, representative frontend output, and SQL artifact rows when transfer is involved.

## Rollback

Restore from the pre-run DB backup or per-post export.

## What To Document

Record current unresolved transcript path in `TODO.md`; use handoff only for current checkpoint risk.

## Known Pitfalls

- Long CPU-only Whisper fallback can run for a long time without useful output.
