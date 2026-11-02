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
