# Dulogix operational rules

Last updated: 26 October 2026.

## Understanding the rota stages

Each Monday–Sunday rota moves through four clear stages:

| Stage | Meaning | Editing |
| --- | --- | --- |
| 🟡 **Planning** | Next week's rota is being prepared before drivers start working. | Allowed |
| 🟢 **Live Rota** | Drivers are working this week; managers may need to respond to sickness, unavailability and other changes. | Allowed |
| 🟠 **Review Period** | The completed rota remains open for the entire following Monday–Sunday week for reconciliation and corrections. | Allowed |
| 🔒 **Locked** | From 00:00 UK time on the Monday after the review week, the rota is read-only. | Audited 30-minute manager unlock required |

**Example:** The rota for 26 October–1 November 2026 is Live Rota that week, in Review Period from 2–8 November, and Locked from Monday 9 November at 00:00 UK time. The rota for 2–8 November is Planning during the preceding week.

All stages apply to driver availability, allocations, statuses, Weekly Operations Planning and Weekly Actions. Viewing and PDF export remain available after locking.

## Driver training and requirements audit

- Drivers have a configurable training checklist (initially WISE, Cognition, Ementor, Clear Quote and Badge). Administrators may add or rename requirements but cannot delete them.
- Managers and administrators must confirm each check or uncheck; the system records the actor and timestamp in driver-linked history.
- **Retention: at least 12 months.** Training completion events currently have no automatic deletion configured; the minimum is documented, not an active purge deadline.
- The rota Week Change & Unlock History is labelled **12 months**; ordinary rota audit logs are labelled **3 months**. The Settings → Data retention policy page documents the other retention rules and implementation status.

## Permanent account roles

- Each account is created as **Administrator**, **Manager**, or **Driver**. The role is permanent and cannot be changed on Edit User or via a forged form submission.
- A Driver becoming a Manager, or the reverse, requires a **new account with a unique username**. Deactivate the original Driver account instead of deleting it so historical rota and availability records remain intact.
- Driver operational type may change between **Driver** and **Jumper**.
- Manager section assignments (**Rota Management** and **Vehicle Management**) remain editable by an administrator.
- Multiple accounts may share an **email address**, but usernames must remain unique. No email workflow is required.

## Historical rota locking and corrections

**Status: Implemented locally for rota API edits, weekly planning, weekly actions and manager availability. Not deployed or end-to-end tested.**

1. **Scope:** Historical week locking covers driver availability, driver allocations, driver statuses, Weekly Operations Planning and Weekly Actions. Read-only viewing and PDF export remain available.
2. **Lock timing:** A Monday–Sunday rota remains editable throughout its live week and the **entire following Monday–Sunday review week**. It locks at **00:00 on the second Monday after the rota's Sunday end date (Europe/London timezone)**.
3. **Unlock permission:** An authorised manager can unlock a selected historical week directly, without administrator approval or email notifications.
4. **Warning and reason:** Before unlocking, display a clear warning that the action and subsequent edits will be audited; require a nonempty reason.
5. **Session duration:** A manager's unlock expires after **30 minutes**. The manager can manually lock earlier. Enforce expiry server-side.
6. **Isolation:** Only the manager who unlocked that particular week may edit it during the unlock window. Other managers remain read-only.
7. **Audit accordion:** The rota page shows a **Historical Week Audit** accordion for the selected week, with who unlocked, when, why, when it was relocked, and before/after values for historical edits across all covered sections.
8. **Retention:** Unlock, relock and historical correction records are retained for **12 months**; ordinary rota audit entries for **3 months**. Update the pruning logic to distinguish these categories before activating the rule.
9. **Validation:** Historical corrections are subject to normal validation, including the six-consecutive-driving-days rule.
10. **Security:** All restrictions apply in the server-side APIs and persistence layer, not merely in browser controls.

No email service or admin approval workflow is part of this rule.

See [RETENTION_POLICY.md](RETENTION_POLICY.md) for the broader data retention schedule.

## Implementation checklist

- Server-enforced second-Monday lock implemented for rota API edits, planning targets, weekly action endpoints and manager availability edits.
- Authenticated manager unlock/relock API implemented with mandatory reason and 30-minute expiry.
- Historical audit store and accordion viewer implemented; review full end-to-end coverage before production.
- Cover all weekly planning and action edits, not only driver rota cells.
- Local 12-month retention command includes historical audit and unlock sessions. Production scheduling remains inactive.

## Rota distribution and WhatsApp sharing (local)

- Managers can select one, several or all drivers and prepare personalised rota cards sequentially.
- **Mark as sent & continue** is a manager's manual confirmation only; WhatsApp delivery is not verified automatically.
- The rota displays per-driver **Not sent / Sent / Resend required** and a weekly distribution summary. Resend required is determined by comparing the current seven allocation values with the values at last confirmation.
- Only the latest confirmation snapshot per driver/week is stored (manager, timestamp and allocation fingerprint). **Do not introduce a detailed rota-change audit** for distribution.
- General WhatsApp drafts and rota image cards are separate workflows. The card is copied/pasted or shared by the manager, never automatically sent.
- Local implementation only; not deployed to production.
