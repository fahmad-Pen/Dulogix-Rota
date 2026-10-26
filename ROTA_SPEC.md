# Dulogix rota requirements

Preserve the Excel-style weekly grid, including top admin fields Route Target, Current Demand Signal, ECP, DA Capacity, Standby. Display daily required, available, allocated, shortfall/excess and available-but-unallocated counts. Confirm operational formulas before coding.

Two rows per driver: availability automatically from mobile submission and allocation manually from admin. Show conflict alerts, name search, filters and ISO Monday-Sunday week navigation. Past weeks locked until explicit authorised unlock with reason and edit history.

Driver availability changes trigger pending notification and approval; record before/after, reason, timestamp, actor, decision and week. Pending changes do not overwrite approved availability. Keep immutable weekly logs.

WhatsApp Send Rota at end of allocation row only, with personalised coming-week allocation. Track send/resend state. Driver interface mobile-first and one screen. Authentication and permissions mandatory.

Open: DA Capacity/ECP meanings, allocation codes and weights, cutoff, WhatsApp delivery approach.

## Local checkpoint — 26 October 2026

- Weekly rota has driver availability, allocation and status rows; training completion indicators, filtering, weekly planning and actions, PDF export, historical week locking and manager unlock controls.
- WhatsApp supports individual messages, selected recipients and a select-all checkbox in the driver table. General messages open sequential drafts with explicit manager confirmation; no automatic WhatsApp delivery or verification.
- Personalised weekly rota image cards can be prepared for selected drivers one at a time. The manager copies/shares each card in WhatsApp, then selects **Mark as sent & continue**.
- Per-driver, per-week rota distribution shows **Not sent**, **Sent**, or **Resend required** when allocations differ from the confirmed snapshot; a distribution summary appears above the driver table.
- Distribution stores only the latest manager confirmation (week, driver, time, manager and allocation fingerprint). **No rota change audit timeline** is created for this feature. Historical unlock audit is separate.
- WhatsApp desktop opening no longer creates a temporary blank browser tab. A prepared image must still be pasted and sent manually.
- These changes are local on `develop`, not deployed; end-to-end browser and server checks should be repeated before release.
