# Dulogix Security Audit

**Date:** 3 November 2026
**Branch:** `feature/security-audit`
**Status:** Local anonymous-access audit and targeted hardening completed. Authenticated and production security testing not completed.
**Environment:** Local Symfony/PHP application at `http://127.0.0.1:8080`; no production probing.

## Executive summary

The local audit found **no unexpected anonymously accessible application endpoint** in the routes tested, and no confirmed backdoor or SQL injection in the source reviewed. This does not prove the entire site is vulnerability-free.

Two hardening changes were implemented and validated:

1. Nginx browser security headers, with Content Security Policy in **report-only** mode.
2. ZIP entry-count and uncompressed-size checks before extracting user-import XLSX XML.

A suspected missing CSRF token in the vehicle import form was a **false positive**: the token already exists and is validated server-side. No change to that controller was needed.

## Scope and tests

- Reviewed Symfony firewall and role configuration, route inventory, selected controllers, CSRF handling, import/export paths, and local Nginx/Docker configuration.
- Tested **25 non-parameterised GET routes** anonymously: **24 redirected to login (302)**; `/login` returned **200**.
- Expanded anonymous testing to **45 GET/POST route-method combinations**, including benign substitutions for supported `{week}` and `{id}` parameters. **33 redirected to login**. No unexpected publicly accessible application endpoint was observed. Other responses reflected routing or HTTP method behaviour; no state-changing payloads were submitted.
- Sensitive-path requests for `/.env`, `/.git/config`, `/composer.json` and `/config/packages/security.yaml` returned **404**.
- Scanned application PHP source for common code-execution primitives (`eval`, `shell_exec`, `passthru`, `proc_open`, `unserialize`, `exec`, `system`); none found in that scan.
- Reviewed driver availability and daily-check ownership, roles and CSRF; Excel imports and spreadsheet formula neutralisation; vehicle and manager access paths.

**Interpretation:** Anonymous endpoint checks do not verify authorisation between authenticated driver, manager and administrator accounts.

## Findings and disposition

| ID | Finding | Severity | Disposition |
|---|---|---|---|
| F-01 | Local responses originally lacked recommended browser security headers | Medium | **Mitigated locally**; headers verified after web container recreation. Production unverified |
| F-02 | Suspected missing vehicle-import CSRF field | None | **False positive**; existing field and server-side validation confirmed |
| F-03 | XLSX resource exhaustion exposure from archive XML extraction | Medium | **Partially hardened** for user import; broader ZIP/XML resource testing not performed |
| F-04 | Symfony development error-preview route registered locally | Low / verification | Anonymous access redirected to login; production environment unverified |
| F-05 | Production configuration, dependencies and authenticated access matrix | Verification required | **Not tested**; not represented as passing |

### F-01: Security headers

`docker/nginx/default.conf` now configures `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, and `Content-Security-Policy-Report-Only`. Report-only CSP avoids blocking existing inline scripts until compatibility is assessed.

**Verification:** The running local web container initially retained its old config. After recreating **only the local web container**, `GET /login` returned all five headers. HTTPS/HSTS and production reverse-proxy behaviour remain unverified.

### F-02: Vehicle import CSRF

`VehicleManagementController.php` already renders an `_token` field for the vehicle import form and checks the matching token on POST. The initial audit incorrectly flagged it. **No fix was required.**

### F-03: XLSX import resource limits

`UserExcelController.php` already limited upload size and worksheet XML size and parsed XML using `LIBXML_NONET`. The audit added a maximum ZIP entry count and pre-extraction uncompressed-size checks for the shared strings and first worksheet entries. These reduce resource exhaustion risk but do not replace comprehensive archive fuzzing or memory-budget testing. The vehicle importer already had entry-count and XML size limits.

### F-04: Development diagnostics

The local route list includes `/_error/{code}.{_format}`; an anonymous request for `/_error/404` redirected to login. Verify production runs with `APP_ENV=prod`, `APP_DEBUG=0` and without accessible diagnostic endpoints.

### F-05: Unverified production and authenticated protections

Local Compose intentionally uses `APP_ENV=dev`. Local session cookies showed `HttpOnly` and `SameSite=Lax`; production HTTPS cookie `Secure`, TLS/HSTS, login throttling and session expiry were not verified. Composer was unavailable in the tested shell, so no dependency vulnerability audit was completed.

## Validation results

| Check | Result |
|---|---|
| PHP lint: `UserExcelController.php` | Pass |
| Symfony `lint:container --env=dev` | Pass |
| Nginx `nginx -t` | Pass |
| HTTP response headers after local web container recreation | Pass ? all five present |
| Git `diff --check` | Pass |
| Local Docker services | Running at final check |
| Anonymous access to tested routes | No unexpected public application endpoint observed |
| Authenticated role and ID ownership penetration tests | Not performed |
| Production domain security verification | Not performed |
| Dependency CVE scan | Not performed |

## Remaining actions (separate scope)

1. Test authenticated driver/manager/admin permissions and ID ownership with authorised test accounts, including CSRF-negative cases.
2. Check production HTTPS, security headers, cookies, login throttling and deployment environment.
3. Run `composer audit --locked` or an equivalent dependency scanner in an environment with Composer.
4. Add regression tests for XLSX archive limits, CSRF and browser-facing output escaping; evaluate CSP reports before enforcing it.

These are **explicit limitations**, not evidence of exploitable vulnerabilities. No open-ended penetration testing was undertaken.

## Files and change control

- Modified: `docker/nginx/default.conf`
- Modified: `src/Controller/UserExcelController.php`
- Added: `SECURITY_AUDIT.md`
- No commits, pushes, merges or production deployments performed.

**Conclusion:** The bounded **local** security audit is complete with two targeted hardening changes verified. The website is **not certified as fully secure**, because authenticated and production tests were outside the completed scope.
