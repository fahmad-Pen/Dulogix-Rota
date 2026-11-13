# Production diagnostics

Production-only Symfony event subscriber: `src/Controller/ProductionDiagnosticsSubscriber.php`.

- Logs uncaught Symfony exceptions (type and source file/line), HTTP 5xx responses, and slow main HTTP requests (default >= 2000 ms).
- Output: `var/log/production-diagnostics.log` (newline-delimited JSON, UTC timestamps). Rotates at 10 MiB, retains five previous files. Log directory must be writable by the web PHP user.
- Optional environment variable `DULOGIX_SLOW_REQUEST_MS` adjusts the threshold (minimum 250 ms).
- Does not log request bodies, query strings, headers, cookies, user IDs or exception messages. Path segments can still contain sensitive information; avoid putting secrets in URLs.
- On a log write failure, attempts the PHP error log as fallback.
- Read recent entries: `tail -n 100 var/log/production-diagnostics.log`.
- Identify slow pages: `grep '"event":"slow_request"' var/log/production-diagnostics.log | tail -n 50`.

## Coverage limitations

This captures Symfony-dispatched exceptions, completed 5xx responses and slow requests **only after PHP/Symfony has started and the response event fires**. It does not capture PHP startup failures, fatal process crashes, connection failures, requests terminated before response, frontend JavaScript failures, SQL query timings, or outages at IONOS/CDN/DNS. PHP/Apache hosting logs and external uptime checks are still needed. It is not a complete APM solution.

After deploying, confirm web-server write permissions and trigger a controlled slow request in staging, then verify entries. Do not expose `var/log` publicly.
