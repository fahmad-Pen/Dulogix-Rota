# Dulogix data retention policy

Status: **Cleanup command implemented locally; 12-month automatic scheduling and execution are not active.**

Last documented: 28 October 2026.

## Retention schedule

| Data | Retention | Intended action |
| --- | --- | --- |
| Driver rota allocations | 12 months | Delete from live MariaDB |
| Driver availability | 12 months | Delete from live MariaDB |
| Driver rota statuses | 12 months | Delete from live MariaDB |
| Weekly Operations Planning | 12 months | Delete from live MariaDB |
| Weekly Actions / completion records | 12 months | Delete from live MariaDB |
| Driver/manager availability change history (`rota_audit`, `field_key = availability`) | 21 days from change timestamp | Delete using `app:logs:prune` |
| Other ordinary rota audit / change-history records | 3 months | Delete using `app:logs:prune` |
| Historical week unlock/relock and correction audit (planned) | 12 months | Delete after 12 months; separate from ordinary audit before feature activation |

There is **no archive storage** planned for records beyond 12 months.

## Current implementation

- Rota allocation, availability, status and planning cells are stored in `rota_cell` and identified by `week_start`.
- The 12-month rota cleanup command is implemented as `php bin/console app:rota:prune`. It defaults to **dry run**; `--execute` deletes eligible data. It is **not scheduled** or enabled in production.
- `app:logs:prune` deletes `rota_audit` records with `field_key = availability` when their `created_at` timestamp is older than **21 days**; all other rota audit records retain the **3-month** cutoff. The availability change-history page also hides entries older than 21 days. This does **not** shorten the separate 12-month retention of the current availability values stored in `rota_cell`.
- `.github/workflows/prune-logs.yml` defines a daily GitHub Actions job for production, subject to deployment and workflow activation. Physical deletion depends on the cleanup job running successfully; hiding old entries on the page does not delete them. Database backups may retain copies under a separate backup policy.
- Weekly Actions state and broadcast confirmations are stored in `app_setting` keyed by rota week; the new command includes `rota_action_*_YYYY-MM-DD`, `rota_broadcast_sent_YYYY-MM-DD`, and `rota_followup_sent_YYYY-MM-DD` records.
- This document and the Admin Settings retention page are informational, not deletion controls. The local dry run on 26 October 2026 found zero eligible rota cells and zero eligible weekly settings.

## Requirements before enabling cleanup

1. Confirm applicable legal, contractual and operational retention requirements before irreversible deletion.
2. Verify that recoverable database backups exist, and define the separate backup-retention period.
3. Implement a dry-run mode that reports record counts and exact cutoff dates.
4. Ensure cleanup covers weekly action records as well as rota cells, without touching unrelated settings.
5. Use the rota's Monday week-start consistently; avoid deleting an in-progress week.
6. Test locally, then obtain explicit authorization before scheduling or deploying production cleanup.
7. Log each cleanup run (date, cutoff, counts and errors) without retaining personal data unnecessarily.

**Do not treat the agreed 12-month period as a currently enforced technical guarantee.**

## Rota distribution confirmations

- Latest per-driver, per-week manual rota sending confirmations are stored in `app_setting` under `rota_sent_YYYY-MM-DD_DRIVERID` keys. Each record contains confirmation time, confirming manager and a fingerprint of seven allocations; there is no distribution event history.
- The confirmation endpoint prunes entries older than 12 months when a new confirmation is saved. This is **opportunistic cleanup**, not a scheduled guarantee. Review coverage in the central `app:rota:prune` command before production deployment.
- A changed allocation fingerprint displays **Resend required** without creating a separate change audit.
