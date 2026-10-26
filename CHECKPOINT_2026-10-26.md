# Development checkpoint — 2026-10-26

## Branch and release boundary
- Development branch: `develop`. Production branch: `main`.
- Last known merged production commit: `5700a69` — `feat(rota): improve manager access, driver security and rota planning (#2)`.
- Previous local checkpoint: `6bf8b90` — `feat(rota): checkpoint weekly operations and WhatsApp distribution`.
- This checkpoint is for review only. **Do not merge the PR into main or deploy to IONOS until explicitly approved.**
- GitHub Actions production deployment is intended to occur only after merging into `main`.

## Changes since production main (includes earlier develop commits)
- Weekly rota improvements, driver availability reviews, status controls, week locking, deadlines, and WhatsApp rota distribution.
- Manager section access and permissions, driver account controls, training requirements, and vehicle management.
- Operations report: fixed vans, daily start times, comments, driver checks, print and WhatsApp sharing.
- Driver daily shift check: pre-shift Ementor and Clear Quote; post-shift Fisco Score and returns; dedicated landing page.
- Mobile navigation for drivers and operations users.
- Retention controls, pruning commands and scheduled workflows for audit, rota and daily operations data.
- Administrator-only Excel user export template/import; all-or-nothing validation, existing usernames skipped, normalized lowercase usernames, password generated as username + suffix, no password export.
- Password policy: Driver/Jumper minimum 8 characters including a digit and special character; Manager/Admin minimum 12 characters. Applied to manual account creation, Excel import, account password change and admin/manager reset.
- Administrator may reset any user's password; managers may reset Driver/Jumper passwords only. Password rules displayed in forms.

## Verification and release checklist
- PHP syntax checks and git whitespace checks have been run during implementation. Browser end-to-end and production migration tests are still required.
- Verify user import with duplicate users, bad data, and multiple roles; password resets and self-service changes for all account types.
- Verify daily driver check and operations report autosave, navigation, mobile layout, print/share, and retention dry-run.
- Review database migrations, production secrets, and GitHub Actions deployment workflow before merging.
- Review diff and test the PR. Keep production unchanged until the owner explicitly merges.

## Operational notes
- New Excel import does not modify existing users. Imported initial password is lowercase username concatenated with suffix; store completed templates securely.
- One-calendar-month retention is intended for daily shift checks and operations reports.
- Never commit local scratch scripts (`*_tmp.py`).
