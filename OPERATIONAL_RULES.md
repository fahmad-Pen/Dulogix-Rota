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

## Daily LANE PLAN Excel import (local, 2026-11-10)

- Operations Full Access managers may upload an Excel `.xlsx` file (maximum 15 MB) to the selected date on the Operations Report page. The importer reads only the **LANE PLAN** worksheet and requires the column headers `COLOR`, `HOLDING LANE TIME`, `STG. LOCATION`, `ROUTE`, and `ASSIGNED DA` in columns C, D, G, H and I.
- `ASSIGNED DA` is matched to the existing driver `transporter_id` (case-insensitive normalization). The report shows Color, Holding, STG and Route immediately after Start Time. Import data is read-only for all users with Operations page access.
- Records are stored per report date in `operations_lane_plan`; uploading another valid file replaces that date's entire previous import in a transaction. Unmatched IDs are reported to the uploader, not inserted. At least one active driver must match. Other dates and existing Operations values are unaffected.
- Upload is restricted server-side to Operations Full Access, protected by the Operations CSRF token; Performance Full + Operations Read Only cannot upload. No Google account integration is required.
- Local migration `Version20261110140000` was applied to the development database. Excel reader synthetic test `tests/lane_plan_reader.php`, PHP syntax and Symfony container lint passed. Real uploaded workbook import and authenticated browser layout are **not yet end-to-end verified**. Do not deploy before verifying the sample report in the app.

### Blank manager FICO / returns values (2026-11-10)

On Operations, a manager may leave or clear the FICO score and returns fields. Empty values are stored as SQL NULL (not zero), and no validation error is raised. Clearing a manager FICO override falls back to the driver's submitted score if one exists; clearing a manager-only FICO score leaves no effective score for that day. Audit history records FICO clears with nullable `new_score` (`Version20261110234500`, applied locally). FICO remains 0–850 and returns 0–999 when populated. Transactional regression tests cover blank persistence and clearing audit. Browser verification pending.

### Publish Holding Time & Route to drivers (2026-11-11)

Operations managers with full Operations access can use the pastel-red **Publish HT & Route** button to publish the selected day's imported lane-plan Holding Time, Route and STG plus the driver's full assigned van registration. Publication requires a confirmation and a valid Operations CSRF token. The button is disabled if no lane-plan rows are imported. The publication is a date-scoped snapshot stored in `operations_lane_publication` (migration `Version20261111020000`). Republish replaces that date's snapshot, including updated vans. An import or van edit alone does **not** publish. Read-only Operations and Performance-only users cannot publish (server-side authorization).

The authenticated driver's `/driver/daily-check/data?date=...` GET response includes only that driver's published assignment for the requested date. Daily Shift Check displays it in a prominent card immediately above Pre-Shift, including the full van registration; no publication means no card. Dates remain subject to the existing one-month selection limit. The driver submission and locking workflow is unchanged. Local migration and transactional `tests/lane_publication_integration.php` passed; browser confirmation and manager-to-driver end-to-end verification remain pending.

### Change / Swap Driver on imported routes (2026-11-11)

Operations-full managers see a compact ⇄ icon beside drivers who have an imported lane-plan route. It opens a searchable Change / Swap Driver dialog listing all active non-manager drivers. Replacing a driver with no route transfers the current route, colour, holding time and STG; choosing a driver with a route swaps those lane-plan assignments. The preview displays both drivers' resulting routes and vans and warns if a van is missing. **Fixed vans remain with their driver**; non-fixed vans follow the route, with duplicate/fixed-van conflicts rejected server-side. Only the van field changes in `operations_daily`; each driver's start time, comments, FICO, returns and daily check submissions remain untouched.

The POST endpoint `/operations/change-driver` requires full Operations edit access and an Operations CSRF token, validates active driver IDs and the selected date, locks the day's lane plan, and commits all changes atomically. If that day's routes have already been published, both affected drivers' `operations_lane_publication` snapshots update immediately; if unpublished, changing drivers does not publish. Each action records the actor, timestamp, before/after assignments and vans in `operations_driver_change_audit` (`Version20261111160000`). Six van-handling regression cases in `tests/operations_driver_change.php` passed. Browser-based end-to-end testing remains pending. No extra table column is added.

UI refinement: the Operations Driver cell groups the name and ⇄ control in a flex-wrap container aligned after the row number and print checkbox, keeping wrapped names properly indented. The Change / Swap dialog explicitly identifies the **Current driver** with their existing route, holding time, STG and van above the replacement search and preview.

Correction: **Start time belongs to the route assignment**, not the driver. On a swap, both Operations start times exchange; on a change to an unassigned driver, the source start time transfers to the replacement and the source is cleared. This is atomic with van and lane-plan changes; daily comments, FICO, returns and checks remain driver-owned. The confirmation preview includes original and resulting start times. Audit before/after data records start times. Regression cases cover swap, change, missing time and replacement with existing time.

Post-publication van sync: saving a manual van selection on Operations updates `operations_lane_publication.van_registration` for that same driver/date inside the existing transaction, only if a publication row already exists; it does not publish an unpublished date. Fixed-van correction: if either driver in a route swap has a fixed van, each driver retains their current van (fixed or non-fixed); when neither is fixed, vans exchange with routes. For a change to an unassigned driver, a fixed source keeps their van and the replacement retains any existing non-fixed van. Preview uses the same rules. This prevents the non-fixed driver from unexpectedly losing their van when swapping with a fixed-van driver.

Driver Daily Shift Check manager comments: GET `/driver/daily-check/data` includes `manager_comments` from the current driver's `operations_daily.comments` for the selected date only when a published assignment row exists. The read-only Manager Comments block appears beneath the published route/van card only when nonempty; text is rendered with `textContent` and preserves line breaks. Operations comment edits are read live on next shift-check reload; comments remain with the driver during route swaps. No schema migration needed.

Published instruction acknowledgement: Driver Daily Shift Check displays a warning and an `Understood — Instructions Read` button for unacknowledged route, holding time, STG, van or manager comments. Acknowledgement records a SHA-256 hash of the current canonical instruction snapshot and timestamp in `operations_instruction_ack` keyed by driver/date. GET compares the live published assignment plus driver/date Operations comments with the last acknowledged snapshot, lists changed field names, and turns the route card green only when the hash matches. POST `section=ack` requires driver authentication, CSRF and the exact current hash, preventing acknowledgement of stale instructions. New changes invalidate the acknowledgement automatically; pre/post shift locks are unaffected. Migration `Version20261111170000` applied locally.

Operations acknowledgement monitoring: The Operations report reads published route fields and current driver/date comments, computes the same canonical SHA-256 snapshot as Driver Daily Shift Check, and compares with `operations_instruction_ack`. Beneath the driver name: grey Not published, red Sent · Not read, or green Instructions read · HH:mm. The report shows X of Y published instructions read, outstanding count, and a `Show only instructions not read` checkbox integrated with existing table allocation/fuel/column filters and clear-filters action. Statuses update when the report is reloaded; they are not push/live streamed. No native phone notifications implemented.

Operations acknowledgement live synchronization: `/operations/instruction-status?date=YYYY-MM-DD` (Operations-authorized GET) computes statuses and counts from published assignments, live driver/date comments and acknowledgement snapshots. `operations-report.js` refreshes the badges, unread filter and summary after successful inline saves, on tab focus/visibility return, and every 15 seconds while visible. A newly changed instruction immediately invalidates the read badge after successful save; a driver's subsequent acknowledgement turns it green on the next poll. The endpoint does not change acknowledgements. No manual refresh required.

Correction to Operations status polling: background acknowledgement checks must never reorder the report or reset Holding/STG/Route or other user-selected sorts. The 30-second status poll updates only changed badges and summary counts; if `Show only instructions not read` is enabled, it recomputes row visibility without sorting/reinserting DOM rows. Status refresh also occurs after a successful save and when the tab regains focus. User's editing position and sorting remain stable.

Changed-instruction field highlighting: Operations compares current published instruction snapshot with the last acknowledged `instruction_snapshot` (not the previous edit) and marks only changed holding_time, stg_location, route, van_registration, manager_comments values red. It clears highlighting after acknowledgement; initial unacknowledged publication has no prior snapshot and shows no individual red fields. The status polling endpoint is one read-only joined query per check, not a database write. `operations_instruction_ack` has a composite (report_date,driver_id) primary key and acknowledgment POST upserts that same day's row.

## Route publication, driver changes and acknowledgements (2026-11-11)
- Publish Holding Time & Route is a manager action scoped to a report date; publication stores the driver's holding time, route, staging location and van. Operations comments are read live by driver/date and follow the driver rather than the route.
- The Operations driver-change control swaps routes and start times between two assigned drivers, or moves a route to an unassigned replacement. Fixed vans remain with their owners; non-fixed vans move according to the swap rules. The change is audited and existing publication is synchronized within the change transaction.
- Driver Daily Shift Check displays published instructions, manager comments, changed-field warnings and an acknowledgement button. Acknowledgement is stored per date/driver with an instruction hash and snapshot; subsequent changes invalidate the previous acknowledgement. Changed values are highlighted red until acknowledged, on both driver and Operations screens.
- If a route is withdrawn through a change to an unassigned replacement, the original driver sees a withdrawal notice rather than an empty instructions area. The pre/post-shift submissions remain independent.
- Operations displays grey unpublished, red unread and green acknowledged statuses beneath driver names, plus an unread-only filter and counts. A read-only endpoint updates indicators after saves, when the tab regains focus, and on a 30-second interval without rearranging the user's selected table order. Changed values are compared against the last acknowledged snapshot.
- Related migrations: Version20261111020000 (publication), Version20261111160000 (driver change audit), Version20261111170000 (acknowledgement). Tests: `tests/lane_publication_integration.php` and `tests/operations_driver_change.php`.
- Web push/phone notifications are not implemented; status polling is not a phone notification service. Verify changes in a real browser before production deployment.
