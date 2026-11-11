# Driver Performance Hub — Dulogix

Last updated: 2 November 2026. This document describes the **local development implementation**, not a production deployment.

## Location and access

- Route: `/driver-performance` (GET/POST); controller: `src/Controller/DriverPerformanceController.php`.
- Access is controlled by `SectionAccess::performanceLevel()`: `full` permits editing/imports, `read only` permits viewing, and `none` denies access.
- Week selector uses Monday-start weeks in the `Europe/London` time zone. URL query: `?tab=weekly&week=2026-10-26`.
- Current tab order: **Weekly Reports → DSP Daily Summary → CC (Contact Compliance) → POD (Photo on Delivery) → DWC/IADC Report → Rules**.

## Weekly Reports

Displays driver name and Transporter ID, Rank / Status / Total Score, and performance metrics. Driver-name search filters the rows. Alternating row shades, conditional metric colours and editable scoring-rule dialogs are part of the UI. The two-row header has sticky styling; browser behaviour should be checked after layout changes.

### Score components (maximum 100 points)

| Metric | Weight | Source / meaning |
| --- | ---: | --- |
| FICO / eMentor | 20 | Driver daily-check eMentor/FICO score |
| DWC | 10 | Delivery Workflow Compliance |
| PSB | 5 | Collection fails per 100 stops |
| DSC DPMO | 20 | Delivery service concessions |
| POD | 10 | Picture on Delivery |
| CC | 15 | Contact Compliance |
| CE | 15 | Escalations |
| CDF DPMO / DEX | 5 | Negative feedback |
| **Total** | **100** | Sum of category points |

The configured threshold bands and colours are loaded from `driver_performance_scoring_rules.rules_json` when available; otherwise controller defaults apply. **Do not assume defaults are the current configured values.** Netradyne violation deductions are shown separately; verify how they affect the final total before changing score calculations.

DPMO is **lower-is-better**. In particular, a DSC DPMO value of `0` earns the lowest-threshold band (default **20 points**), not zero points. The rank total was corrected to match the displayed DPMO score. Any future changes must keep category display and total-score calculations consistent.

A driver is ranked when all eight required category values are available; otherwise status is **Pending**. Complete drivers are sorted by descending total score, with equal scores sharing a rank.

### Status thresholds and pastel colours

| Total score | Status | Background | Text |
| --- | --- | --- | --- |
| >= 93.5 | Fantastic_Plus | `#BFE8D0` | `#14532D` |
| >= 85 and < 93.5 | Fantastic | `#DDF2E7` | `#246B4B` |
| >= 70 and < 85 | Great | `#DCEBFA` | `#245B91` |
| >= 40 and < 70 | Fair | `#FFF0BF` | `#8A5A12` |
| < 40 | Poor | `#FAD9DF` | `#9F2338` |

These colours are intentionally light to match the other metric cells.

## Other tabs

- **DSP Daily Summary:** imported DSP data, unmatched Transporter ID review, driver matching and ignore controls.
- **CC:** Contact Compliance reporting and import workflow.
- **POD:** Photo on Delivery reporting and import workflow.
- **DWC/IADC:** delivery workflow compliance reporting.
- **Rules:** scoring weights, editable thresholds and metric-specific conditional colours.

## Data and testing

- Driver matching uses **Transporter ID**, not names.
- For the week beginning **2026-10-26**, eMentor scores were imported as **test data**: 103 supplied; 100 matching driver records reported as loaded. Three IDs required reconciliation. All existing driver scores were described as test data at the time.
- In production, eMentor/FICO scores are expected to come from drivers' submitted daily checks. Do not make a permanent dependency on the test import.
- DSP import data and individual metric sources must be checked when troubleshooting missing/Pending scores.

## Development and verification

Project path on Farid-Asus-Duo WSL: `/home/farid/projects/Dulogix`.

Local page: `http://localhost:8080/driver-performance?tab=weekly&week=2026-10-26`.

After controller edits, run:

```bash
cd /home/farid/projects/Dulogix
docker compose exec -T php php -l src/Controller/DriverPerformanceController.php
git diff --check
```

Check the page in a browser, especially the sticky table header and multi-row alignment; syntax checks do not verify rendering.

## Development guardrails

- Preserve existing uncommitted and untracked project work.
- Do not commit, push or deploy without explicit permission.
- Maintain scoring consistency between visible category points and the total out of 100.
- Keep test data separate from future production data and avoid accidental overwrites.
- Update this file when tabs, scoring thresholds, data sources or key UI behaviour change.

## Agreed eMentor/FICO daily-to-weekly design (2026-11-10; implementation pending)

**Authoritative source:** Daily Operations FICO scores, based on drivers' locked daily submissions and authorized manager corrections. Weekly Performance is a read-only consumer of the daily score; it must not maintain a separate editable weekly FICO override. This is a design decision, **not a claim that the current code already behaves this way**.

- A driver submits a FICO score for a date once; their own submission is then locked. Manager corrections must not unlock driver editing.
- Operations Managers may correct daily FICO scores on the Operations Daily page.
- **Operations Read Only + Performance Full Access** permits FICO-only corrections on the Operations Daily page. Other Operations fields (including returns, van, start time and comments) remain read-only. Operations Full Access retains normal Operations editing. Operations Read Only + Performance Read Only cannot edit FICO. Enforce these combinations on the server, not only in the UI.
- For each selected reporting week (Monday through Sunday), the Weekly Performance page shows the score associated with the **latest calendar date in that week with a submitted daily FICO score**, including its authorized correction. Do not choose the most recently *edited* date; a correction to an earlier day must not displace a later day's score.
- The report updates as the week progresses: Monday's score is displayed until a later day has a score; if Tuesday has one, Tuesday replaces Monday; if Wednesday has none, Tuesday persists, and so on through Sunday. Weekend submissions count normally.
- If an authorized manager corrects the selected last day's FICO score, the weekly report reflects the corrected value on refresh, including corrections made after the week ends. **No Thursday freeze or finalization lock** applies to FICO. Thursday is the usual review time for the preceding week, not a score cutoff.
- Remove/disable the Performance-page FICO override editor and its server-side save route/action when implementing. Maintain an audit trail of manager changes (old/new score, actor, timestamp, driver and date).
- Preserve existing historical weekly eMentor imports during migration. Decide and test a safe fallback for weeks with no submitted daily FICO; imported weekly data must not silently override a present daily score. Existing `driver_ementor_weekly`, `driver_ementor_overrides`, and `operations_daily_score_override` behaviour needs reconciliation before removing any data or columns.
- The report should identify the daily date/source used, where practical, so managers can verify the selected score.

**Current implementation differs:** `DriverPerformanceController.php` currently prioritizes `driver_ementor_overrides`, then `driver_ementor_weekly`, then the latest `driver_daily_check` score; `OperationsReportController.php` separately uses `operations_daily_score_override`. Implement and test the agreed selection and permission rules before describing the two pages as synchronized.

### Manager FICO correction audit (local implementation)

Migration `Version20261110120000` creates `operations_fico_audit` with driver ID, report date, previous/effective score, corrected score, manager actor ID and timestamp. Operations FICO corrections write audit entries transactionally when the effective score actually changes. The migration was applied to the local development database on 2026-11-10; production migration/deployment is pending. PHP syntax and migration execution were checked locally. End-to-end manager role/browser tests and audit-row verification through a real authenticated correction are still pending.

### Local integration regression tests (2026-11-10)

Run `docker compose exec -T php php tests/fico_integration.php`. The test checks the four Operations/Performance access combinations, denial without Operations access, latest submitted date, older-date correction precedence, corrected latest-day score, preservation of returns on FICO-only updates, audit row columns, and fallback to an earlier submitted day. Test fixtures are rolled back in a database transaction; no persistent driver scores are changed. All 13 assertions passed locally. PHP syntax, Symfony container lint and `git diff --check` also passed. **These are database/authorization-rule integration tests, not authenticated HTTP/browser tests**; actual controller POST authorization, rendered controls, and end-to-end audit persistence still require authenticated browser/HTTP verification before deployment.

### Manager-entered FICO before driver submission (2026-11-10 fix)

On the Operations Daily page, authorised managers can save a valid FICO score (0–850) even when the driver has not yet submitted a daily score. The value is stored in `operations_daily_score_override` for that driver/date and persists on refresh. `operations_fico_audit.old_score` is nullable for the first manager entry without a prior score (`Version20261110160000`, applied locally); the audit records the new score and manager identity. Later changes record the previous effective score. The existing rule for weekly Performance selection remains based on days with driver-submitted FICO scores; a manager-only Operations entry does not independently become the weekly latest-submitted score. Local regression test `tests/fico_integration.php` passed, including first-entry persistence and nullable audit. Authenticated browser save/refresh remains to be checked.

### FICO precedence by submission timestamp (2026-11-10)

If the manager enters a daily FICO score first and the driver subsequently submits their own score, the later driver submission becomes the effective daily score on Operations and on weekly Performance reports. A manager correction made after driver submission can still take priority. The comparison uses `driver_daily_check.post_updated_at` against `operations_daily_score_override.updated_at`; the original manager override and its audit history are preserved. Driver post-shift submission remains one-time locked.

### Manager-only daily FICO shown in Performance (2026-11-10)

Weekly Performance FICO now includes valid manager-entered Operations FICO scores even if the driver has not submitted a daily check. For each driver, select the latest calendar date in the week with either a driver-submitted or manager-entered FICO score. On the same date, the later timestamp determines the effective value. Historical imported weekly Ementor remains fallback when there is no daily score. Apply the same logic to prior-week rankings. The manager-only entry does not count as a driver submission and does not lock the driver's daily check.

### Weekly FICO ignores zero and blank scores (2026-11-10)

For weekly Performance FICO and prior-week ranking, select the latest calendar day whose **effective** FICO score is strictly greater than zero. An effective 0 or NULL (whether driver-submitted or manager-entered) is ignored so a previous day's positive score remains the weekly score. On each day, the latest driver/manager timestamp still determines the effective score; a manager-only positive score counts. The daily Operations value and audit are not altered. Historical imported weekly scores remain a fallback if no positive daily score exists.

### Performance FICO edit regression cleanup (2026-11-10)

Removed obsolete `public/ementor-inline-edit.js`, which transformed weekly Ementor badges into editable score inputs. The Performance page must remain read-only; no independent weekly FICO POST endpoint or editable inline UI is permitted. Edit FICO only on Operations, with its permissions and audit. If an existing browser tab still displays an editor, hard-refresh to replace stale client-side assets.
