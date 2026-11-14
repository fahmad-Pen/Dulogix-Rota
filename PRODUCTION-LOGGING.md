# Production diagnostics

Production-only Symfony event subscriber: `src/Controller/ProductionDiagnosticsSubscriber.php`.

- Logs uncaught Symfony exceptions (type and source file/line), HTTP 5xx responses, and slow main HTTP requests (default >= 2000 ms). Every completed GET /operations/report also records its server duration as `operations_timing` (or `slow_request` if above threshold).
- Output: `var/log/production-diagnostics.log` (newline-delimited JSON, UTC timestamps). Rotates daily (UTC) or at 10 MiB; archives are retained for 30 days and older archives are deleted on the next logged event. This is write-triggered maintenance, not a scheduled job; without traffic, cleanup waits until the next event. Log directory must be writable by the web PHP user.
- Optional environment variable `DULOGIX_SLOW_REQUEST_MS` adjusts the threshold (minimum 250 ms).
- Does not log request bodies, query strings, headers, cookies, user IDs or exception messages. Path segments can still contain sensitive information; avoid putting secrets in URLs.
- On a log write failure, attempts the PHP error log as fallback.
- Read recent entries: `tail -n 100 var/log/production-diagnostics.log`.
- Identify slow pages: `grep '"event":"slow_request"' var/log/production-diagnostics.log | tail -n 50`.

## Coverage limitations

This captures Symfony-dispatched exceptions, completed 5xx responses and slow requests **only after PHP/Symfony has started and the response event fires**. It does not capture PHP startup failures, fatal process crashes, connection failures, requests terminated before response, frontend JavaScript failures, SQL query timings, or outages at IONOS/CDN/DNS. PHP/Apache hosting logs and external uptime checks are still needed. It is not a complete APM solution.

The deployment workflow now prepares `var/log` as group-writable (2775) and the diagnostics file as group-writable (664). This assumes the web PHP process belongs to the hosting `www-data` group; verify after deployment. An empty log before this fix did not prove a broken subscriber: normal fast requests were intentionally not logged.

Missing browser icon requests are addressed with actual `/favicon.ico`, `/apple-touch-icon.png`, and `/apple-touch-icon-precomposed.png` assets generated from the existing Dulogix favicon. The touch icons currently reuse the 32×32 source, so a higher-resolution artwork would improve home-screen appearance.

After deploying, open `/operations/report` as an authorised user, then verify a new `operations_timing` or `slow_request` entry. If none appears, check PHP process ownership, filesystem permissions and hosting error logs. Do not expose `var/log` publicly.
