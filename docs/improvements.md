# Buwana — Improvement Opportunities

Covers efficiency, security hardening, standards compliance, and maintainability improvements for both the **SSO / OIDC service** and the **Buwana App Manager (BAM)**.

Organized by category. Within each category, items are listed roughly in priority order.

---

## 1. Security Hardening

### IMP-01 — Centralized HTTP Security Headers
- **Files affected**: All entry points (`authorize.php`, `token.php`, `userinfo.php`, `en/*.php`, `api/*.php`)
- **Description**: No security headers are currently set. A single shared include or web server config should add:
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
  - `X-Frame-Options: DENY`
  - `X-Content-Type-Options: nosniff`
  - `Cache-Control: no-store` (auth endpoints)
  - `Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{nonce}'; ...`
- **Expected benefit**: Security — eliminates clickjacking, MIME-sniffing, downgrade attacks, and caching of sensitive tokens as a class of vulnerabilities.

---

### IMP-02 — Unified CORS Middleware
- **Files affected**: `token.php` (lines 65-90), `api/login_process.php` (lines 14-36), `userinfo.php`
- **Description**: CORS origin validation and header setting is duplicated in at least three files, with inconsistent logic and a hardcoded `DEVMODE` flag that allows `localhost:8080`. Centralizing this into `/includes/CorsMiddleware.php` would:
  - Apply a single origin whitelist
  - Read allowed origins from config rather than hardcoding
  - Remove the `DEVMODE` flag; control via environment variable
- **Expected benefit**: Security + Maintainability — single place to audit and update CORS policy; removes `localhost` leaking into production.

---

### IMP-03 — Rate Limiting on All Authentication Endpoints
- **Files affected**: `authorize.php`, `token.php`, `api/login_process.php`, `en/login_process.php`
- **Description**: Failed-login attempt counting exists in `login_process.php` but:
  - The `/authorize` and `/token` endpoints have no rate limiting
  - No distributed/IP-based throttle exists (only per-account attempt counts)
  - No exponential backoff or temporary IP blocks
- **Recommendation**: Add per-IP rate limiting (e.g., using a Redis counter or APCu) to all auth endpoints. Implement exponential backoff for repeated failures. Consider a 10-minute lockout after N consecutive failures per IP.
- **Expected benefit**: Security — significantly raises the cost of brute-force and credential-stuffing attacks.

---

### IMP-04 — Standardize Session Security Configuration
- **Files affected**: `api/login_process.php` (lines 48-55), `earthenAuth_helper.php` (lines 13-25), all pages using `session_start()`
- **Description**: Session cookie parameters are defined in multiple places with inconsistent values (`SameSite=None` in one file, different settings elsewhere). A shared `/includes/SessionManager.php` should:
  - Set `SameSite=Strict` (or `Lax` where OAuth redirects require it)
  - Set `HttpOnly=true`, `Secure=true`
  - Define a single session lifetime constant
  - Handle session regeneration on privilege escalation
- **Expected benefit**: Security — consistent protection against session fixation, CSRF, and cookie theft across the entire application.

---

### IMP-05 — Centralized Input Validation
- **Files affected**: All `*_process.php` files, all `api/*.php` files
- **Description**: Input sanitization using `filter_var()` is repeated inconsistently across 10+ files. Some files use `FILTER_SANITIZE_EMAIL` without `FILTER_VALIDATE_EMAIL`; others skip validation entirely. A shared `/includes/InputValidator.php` class should provide typed validation methods (`validateEmail()`, `validateInt()`, `validateLength()`, `validateAllowlist()`, etc.).
- **Expected benefit**: Security + Maintainability — reduces duplicated code; makes it easier to audit and update validation rules.

---

## 2. Performance

### IMP-06 — Add Database Indexes on High-Traffic Lookup Columns
- **Files affected**: Database schema; queried in `token.php`, `authorize.php`, `api/login_process.php`
- **Description**: The following columns are used as lookup keys in every authentication request but likely lack indexes:
  - `credentials_tb.credential_key`
  - `authorization_codes_tb.code`
  - `authorization_codes_tb.client_id`
  - `user_app_connections_tb.buwana_id` + `app_id`
- **Recommended SQL**:
  ```sql
  CREATE INDEX idx_credentials_key ON credentials_tb(credential_key);
  CREATE INDEX idx_auth_codes ON authorization_codes_tb(code, client_id);
  CREATE INDEX idx_user_app ON user_app_connections_tb(buwana_id, app_id);
  ```
- **Expected benefit**: Performance — 60-80% faster auth lookups at scale; prevents full table scans on every login and token exchange.

---

### IMP-07 — Eliminate N+1 Queries in Login Attempt Tracking
- **Files affected**: `en/login_process.php` (lines 94-141), `api/login_process.php` (lines 93-168)
- **Description**: The failed-login flow issues 2-3 separate SELECT queries (fetch credential, fetch attempt count, update attempt count) that could be combined into a single query with a JOIN and upsert pattern.
- **Expected benefit**: Performance — reduces latency per login attempt by 40-50%; reduces load on the database at high request volumes.

---

### IMP-08 — Cache Connected-Apps List in Session
- **Files affected**: `header-2026.php` (lines 43-62)
- **Description**: Every page load re-queries `apps_tb` and `user_app_connections_tb` to build the navigation menu. This data changes rarely and is a good candidate for short-lived session caching:
  ```php
  if (!isset($_SESSION['connected_apps']) || $_SESSION['connected_apps_ttl'] < time()) {
      $_SESSION['connected_apps'] = fetchConnectedApps($buwana_id);
      $_SESSION['connected_apps_ttl'] = time() + 3600;
  }
  ```
- **Expected benefit**: Performance — eliminates 2+ queries per page load per user; significant improvement at moderate traffic.

---

### IMP-09 — Set Appropriate Cache-Control Headers on Static Resources
- **Files affected**: All entry points
- **Description**: PHP pages that render static UI (dashboard, app wizard, etc.) do not set any `Cache-Control` headers, preventing browsers from caching CSS, JS, or images aggressively. Auth endpoints should explicitly set `no-store`.
- **Expected benefit**: Performance — reduces repeat-visit page load times; correct `no-store` on auth endpoints prevents token caching in shared proxies.

---

## 3. OIDC Standards Compliance

### IMP-10 — Implement Token Revocation Endpoint (`/revoke`)
- **Files affected**: New file `revoke.php` (to be created)
- **Description**: RFC 7009 defines a standard `/revoke` endpoint that allows clients to invalidate tokens. Without it, users cannot explicitly log out of connected apps, and compromised tokens remain valid until natural expiry.
- **Expected benefit**: Security + Standards — enables proper logout flows; required for full OAuth 2.0 compliance.

---

### IMP-11 — Implement Token Introspection Endpoint (`/introspect`)
- **Files affected**: New file `introspect.php` (to be created)
- **Description**: RFC 7662 defines `/introspect`, allowing resource servers to verify a token's validity and inspect its claims without parsing the JWT themselves. Currently client apps must implement full RS256 JWT verification locally.
- **Expected benefit**: Standards + Developer experience — simplifies client integration; enables server-side token validation.

---

### IMP-12 — Implement Refresh Token Grant Type
- **Files affected**: `token.php`, database schema
- **Description**: Access tokens expire after 5400 seconds (90 minutes) with no renewal mechanism. Users must re-authenticate when tokens expire. Implementing `grant_type=refresh_token` allows silent token renewal.
- **Expected benefit**: UX + Standards — improves user experience for long sessions; required for full OAuth 2.0 compliance.

---

### IMP-13 — Sync OIDC Discovery Document with Implemented Scopes
- **Files affected**: `.well-known/openid-configuration.php`, `token.php` (lines 352-356)
- **Description**: The discovery document's `scopes_supported` list does not match the scopes actually implemented in `token.php` (e.g., `buwana:earthlingEmoji`, `buwana:community`, `buwana:location.continent`). Client libraries rely on the discovery document to know what scopes to request.
- **Expected benefit**: Standards — correct discovery enables automated client configuration and scope negotiation.

---

### IMP-14 — Scope-Filter `userinfo.php` Response
- **Files affected**: `userinfo.php`
- **Description**: The userinfo endpoint currently returns all available user fields regardless of the scopes granted in the access token. Per OIDC Core §5.3, only claims corresponding to the granted scopes should be returned.
- **Expected benefit**: Security + Privacy — users are not over-sharing profile data beyond what they consented to.

---

## 4. Maintainability

### IMP-15 — Extract Shared Database Bootstrap into a Single Include
- **Files affected**: 19+ `*_process.php` files that each include `buwanaconn_env.php`, `gobrikconn_env.php`, `fetch_app_info.php`
- **Description**: Database setup and app-info loading is copy-pasted across the codebase. A single `/includes/bootstrap.php` should handle this so changes (e.g., adding connection pooling, changing credentials lookup) are made in one place.
- **Expected benefit**: Maintainability — reduces duplication; eliminates the risk of inconsistent DB setup across files.

---

### IMP-16 — Introduce `/config/constants.php` for Magic Numbers
- **Files affected**: `header-2026.php` (line 22), `token.php` (line 348), `api/login_process.php` (line 106)
- **Description**: Numeric literals for timeouts and other configuration values are scattered through the code:
  - `1800` — session timeout (seconds)
  - `5400` — token expiry (seconds)
  - `600` — failed-login window (seconds)
  - `10` — max failed login attempts
- **Recommendation**: Create `/config/constants.php` with named constants and reference them throughout.
- **Expected benefit**: Maintainability — single place to tune security parameters; self-documenting code.

---

### IMP-17 — Add Missing Cron Jobs for Data Cleanup
- **Files affected**: `processes/crons/`
- **Description**: Existing cron jobs handle test-account deletion and general cleanup, but the following are missing:
  - **Expired authorization codes**: `authorization_codes_tb` rows past their expiry should be deleted regularly
  - **Session garbage collection**: Explicit cleanup of expired PHP session files
  - **Failed login attempt reset**: `credentials_tb` attempt counters should reset after the lockout window
  - **Inactive user connection cleanup**: `user_app_connections_tb` rows for deleted apps or users
- **Expected benefit**: Performance + Correctness — prevents unbounded table growth; ensures lockout logic resets correctly.

---

### IMP-18 — Document Multi-Language Strategy and Complete Language Directories
- **Files affected**: `en/`, `fr/`, `es/`, `id/` directories; `header-2026.php`
- **Description**: The header references multiple language variants, but only `en/` contains a full set of pages. The fallback behavior when a requested language page is missing is undocumented and potentially broken.
- **Recommendation**: Either complete the `fr/`, `es/`, `id/` directories with translated pages, or implement an explicit fallback to `en/` with a documented strategy. Document the approach in the developer wiki.
- **Expected benefit**: Maintainability + Correctness — prevents broken pages for non-English users; clarifies contribution requirements for translators.

---

## 5. Observability and Audit Trail

### IMP-19 — Unify Logging into a Single Framework
- **Files affected**: `token.php` (custom `auth_log()` function), `api/login_process.php` (`error_log()` calls), `processes/crons/*.php` (`file_put_contents()`)
- **Description**: Three different logging mechanisms are in use. A PSR-3-compatible logger (or a lightweight wrapper around `error_log`) should be used consistently. Structured logs (JSON lines) make correlation and analysis significantly easier.
- **Expected benefit**: Maintainability + Security — single format for all audit events; enables log aggregation and alerting.

---

### IMP-20 — Add Structured Audit Logging for All Write Operations
- **Files affected**: All `*_process.php` files, `en/edit-profile.php`, `en/app-connect_process.php`
- **Description**: Profile changes, app connections, consent modifications, and ownership changes currently generate no audit log. Every state-changing operation should log: timestamp, actor `buwana_id`, action type, affected entity ID, and before/after values where practical.
- **Expected benefit**: Compliance + Security — enables forensic investigation; supports GDPR/privacy compliance obligations.

---

### IMP-21 — Log All Authentication Attempts with IP Address
- **Files affected**: `api/login_process.php`, `en/login_process.php`
- **Description**: Failed login attempts are counted but not fully logged (no IP, no timestamp in a queryable format). Successful logins are not logged at all. A structured log of all authentication attempts (success and failure) with hashed credential, IP address, and timestamp enables detection of credential-stuffing and account-enumeration attacks.
- **Expected benefit**: Security — enables detection of and response to automated attack campaigns.

---

### IMP-22 — Add `/health` Endpoint
- **Files affected**: New file `health.php` (to be created)
- **Description**: A lightweight endpoint that checks database connectivity and returns HTTP 200 with `{"status":"ok"}` or HTTP 500 with an error description. Required for load balancer health checks and uptime monitoring.
- **Expected benefit**: Operational — enables automated failover detection and monitoring alerts.

---

## 6. Testing and Documentation

### IMP-23 — Expand PHPUnit Test Coverage for Critical Auth Flows
- **Files affected**: `tests/` directory (currently <1% coverage)
- **Description**: No unit or integration tests exist for the core OIDC flows. Minimum recommended test coverage:
  - Authorization code flow (happy path + invalid `redirect_uri` + invalid `client_id`)
  - Token exchange (valid code, expired code, already-used code, PKCE validation)
  - Failed login attempt tracking and lockout
  - `userinfo.php` scope filtering
- **Expected benefit**: Stability — prevents regressions when modifying auth logic; enables confident refactoring.

---

### IMP-24 — Create OpenAPI 3.0 Specification for All Endpoints
- **Files affected**: `authorize.php`, `token.php`, `userinfo.php`, `revoke.php` (future), `introspect.php` (future), all `api/*.php` files
- **Description**: No formal API specification exists. An OpenAPI 3.0 spec in `docs/openapi.yaml` would:
  - Document all request/response schemas
  - Enable automated SDK generation for client apps
  - Serve as a contract for integration testing
- **Expected benefit**: Developer experience + Maintainability — significantly reduces integration effort for client app teams.

---

### IMP-25 — Add Schema Documentation for All Database Tables
- **Files affected**: `docs/` directory (currently only `users_tb.sql` documented)
- **Description**: `credentials_tb`, `authorization_codes_tb`, `user_app_connections_tb`, `app_owners_tb`, and `apps_tb` have no schema documentation. Each should have a SQL file or Markdown doc describing columns, constraints, indexes, and the purpose of each field.
- **Expected benefit**: Maintainability — enables safer schema migrations; onboards new developers faster.

---

*Last updated: 2026-03-29*
