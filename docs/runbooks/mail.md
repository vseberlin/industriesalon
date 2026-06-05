# Mail Runbook

Use this when configuring, testing, or debugging mail locally or on staging.

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
