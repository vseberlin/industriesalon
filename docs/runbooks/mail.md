# Mail Runbook

## Scope

Use this when configuring, testing, or debugging mail locally or on staging.

## Preconditions

- Approved mail mode is known: capture, sandbox, restricted delivery, or real outbound.
- Secrets are available only through machine-local configuration.

## Inspect First

- mail-related plugins
- environment settings
- service logs

## Rules

- Staging must not send unintended production mail.
- Mail configuration is infrastructure state; secrets and provider credentials do not belong in Git.
- Newsletter/content email systems must degrade gracefully: public site rendering cannot depend on mail delivery.
- Prefer capture, sandbox, or restricted-recipient behavior on staging.

## Procedure

1. Inspect current mail-related plugins, environment settings, and service logs.
2. Identify whether the task is capture, outbound delivery, newsletter content, or transactional mail.
3. Verify the configured sender path without exposing secrets.
4. Send only an explicit test message to an approved address or capture mailbox.
5. Document current behavior in `handoff_CURRENT.md` when it affects deployment or UAT.

## Verification

- Test message reaches only the intended sink or address.
- Logs show no unintended production delivery.

## Rollback

Disable outbound delivery or return to capture/sandbox configuration.

## What To Document

Record current mail mode, risk, and next verification in `handoff_CURRENT.md` when relevant.

## Known Pitfalls

- Staging real-send settings can leak mail to production subscribers.
- Secrets must never be committed.
