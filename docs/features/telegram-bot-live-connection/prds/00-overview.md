# Overview — Telegram Bot Live Connection PRDs

This folder breaks the parent feature
(`../telegram-bot-live-connection.md`) into six independently-grabbable PRDs.
Each is a deep module: small public interface, large internal behavior,
testable in isolation.

| # | PRD                                          | Depends on |
|---|----------------------------------------------|------------|
| 1 | Webhook Security Boundary                    | —          |
| 2 | Family Onboarding via Invite Codes           | 1          |
| 3 | Sender Allowlist & Rate Limit                | 1, 2       |
| 4 | `/done` Command                              | 1, 3       |
| 5 | Message Failure Visibility                   | —          |
| 6 | Telegram Operator Tooling                    | 1          |

Recommended implementation order: 1 → 5 → 2 → 3 → 4 → 6.
(5 stands alone schema-wise; doing it early lets later PRDs assume the new
columns exist.)

All PRDs assume single-user MVP; multi-parent shared family is explicitly
out of scope (see parent doc).
