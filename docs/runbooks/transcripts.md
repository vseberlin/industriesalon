# Transcripts Runbook

Use this for local video transcript work.

## Rules

- Keep local transcription tooling out of Git unless explicitly approved.
- Prefer captions-first import before expensive local speech-to-text fallback.
- Preserve database state before large transcript rewrites.
- Transfer transcript data through explicit SQL artifacts, not hidden runtime migration.
- Stop CPU-only fallback runs when they are clearly impractical and document the remaining path in `TODO.md` or `handoff_CURRENT.md`.
