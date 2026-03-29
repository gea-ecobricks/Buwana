# Buwana Bug List

Covers both the **Buwana SSO / OIDC service** and the **Buwana App Manager (BAM)**.
Organized by urgency: Critical → High → Medium → Low.

---

## CRITICAL

These bugs can be exploited immediately and may result in account takeover, data exfiltration, or full authentication bypass.

---

### ~~BUG-01 — Open Redirect: No `redirect_uri` Whitelist Validation~~ ✓ FIXED
- **Component**: SSO / OIDC
- **Files**: `authorize.php`
- **Fixed**: `authorize.php` now fetches `redirect_uris` from `apps_tb` and validates the supplied URI against the comma-separated registered list (normalizing each entry before comparison). Apps with an empty `redirect_uris` field receive a backward-compat grace period with a warning logged. See commit for details.

---

### ~~BUG-02 — Open Redirect: Token Endpoint Accepts Arbitrary `redirect_uri`~~ ✓ FIXED
- **Component**: SSO / OIDC
- **Files**: `token.php`
- **Fixed**: SECTION 4 client lookup now fetches `redirect_uris` from `apps_tb` and validates the supplied URI against the registered whitelist before reaching the authorization code check. Same backward-compat logic as BUG-01: apps with an empty `redirect_uris` field receive a grace period with a warning logged. The existing SECTION 5 check against `authorization_codes_tb.redirect_uri` remains as a second layer of protection.

---

### BUG-03 — Path Traversal via `lang` GET Parameter
- **Component**: SSO / OIDC
- **Files**: `authorize.php` (line 46, line 124)
- **Description**: The `lang` parameter is read directly from `$_GET` with no allowlist check:
  ```php
  $lang = $_GET['lang'] ?? 'en';
  header("Location: /$lang/login.php");
  ```
  An attacker can supply values like `../../../etc` causing redirection to unintended paths, or serve a spoofed login page at a crafted URL.
- **Impact**: Phishing via open redirect to attacker-controlled login pages; potential information disclosure depending on server configuration.
- **Fix**: Validate `$lang` against an explicit allowlist: `['en', 'fr', 'es', 'id']`. Default to `'en'` for unrecognized values.

---

### ~~BUG-04 — CSRF Token Generated But Never Validated on Login~~ ✓ FIXED
- **Component**: App Manager (BAM)
- **Files**: `en/login_process.php` (entire file), `en/login.php` (token generation)
- **Fixed**: Added CSRF validation at the top of `login_process.php` (before any credential checks). Uses `hash_equals()` to compare `$_SESSION['csrf_token']` against `$_POST['csrf_token']`; returns HTTP 403 and halts on mismatch.

---

### ~~BUG-05 — DOM-Based XSS in App View Page~~ ✓ FIXED
- **Component**: App Manager (BAM)
- **Files**: `en/app-view.php` (lines 434, 455, 488, 559)
- **Fixed**: Replaced all four unsafe `innerHTML` string-concatenation patterns with `createElement`/`textContent`/`appendChild` DOM methods. User-supplied values (`full_name`, object keys/values) are now always assigned via `textContent`, never interpolated into HTML strings.

---

### BUG-06 — Hardcoded `app_id = 5` in User Search API
- **Component**: App Manager (BAM)
- **Files**: `api/search_app_users.php` (line 16)
- **Description**: The SQL query hardcodes `app_id = 5` regardless of the requesting user's app context:
  ```sql
  WHERE a.app_id = 5 AND u.full_name LIKE CONCAT('%', ?, '%')
  ```
  No authorization check validates that the calling user has rights to app 5.
- **Impact**: Any authenticated user can enumerate all users connected to app 5, regardless of which app they actually manage. This is both an authorization failure and a data breach risk.
- **Fix**: Derive the `app_id` from the authenticated session context (or a validated POST parameter), and verify that `$_SESSION['buwana_id']` is an owner of that app before executing the query.

---

## HIGH

These bugs represent significant vulnerabilities or defects that should be resolved promptly.

---

### BUG-07 — `display_errors = 1` Enabled (Production)
- **Component**: SSO / OIDC, App Manager
- **Files**: `earthenAuth_helper.php` (lines 3-4)
- **Description**:
  ```php
  error_reporting(E_ALL);
  ini_set('display_errors', 1);
  ```
  This is set in a file included across the entire application.
- **Impact**: PHP errors, including stack traces, database query strings, file paths, and variable dumps, are rendered directly in HTTP responses. Attackers can trigger errors deliberately to extract internal information.
- **Fix**: Set `display_errors = 0` in `php.ini` or at the top of the shared include. Use `log_errors = 1` with a secure log path instead.

---

### BUG-08 — World-Writable Auth Log Directories (0777)
- **Component**: SSO / OIDC
- **Files**: `token.php` (line 26), `auth_authorize.php` (line 13), `token-with-checks.php` (line 12)
- **Description**: Log directories for authentication events are created with world-writable permissions:
  ```php
  mkdir(dirname($authLogFile), 0777, true);
  ```
- **Impact**: Any local system user can read auth logs (containing authentication events, tokens, and user IDs) or tamper with them, compromising audit integrity.
- **Fix**: Use `0750` (owner read/write/execute, group read/execute, others none) and ensure the web server user owns the directory.

---

### BUG-09 — Duplicate Code Block in `token.php` Silently Overwrites Variables
- **Component**: SSO / OIDC
- **Files**: `token.php` (lines 119-155)
- **Description**: The "INPUT GATHERING" section appears twice. The second occurrence re-declares and overwrites variables set by the first. This is likely an incomplete refactor that left dead or conflicting code in place.
- **Impact**: Unpredictable behavior depending on which code path executes; potential for subtle auth logic bugs that are hard to diagnose.
- **Fix**: Remove the duplicate section. Audit the surrounding logic to confirm only one code path handles input parsing.

---

### BUG-10 — CSRF Protection Missing on App Management POST Forms
- **Component**: App Manager (BAM)
- **Files**: `en/app-view.php` (lines 122-137)
- **Description**: POST forms that toggle `is_active` and `allow_signup` flags submit without including or validating a CSRF token.
- **Impact**: An attacker can trick an authenticated app manager into disabling their own app or enabling open signups via a forged cross-origin request.
- **Fix**: Add a CSRF token to each POST form and validate it server-side at the handler.

---

### BUG-11 — Minimum Password Length of 6 Characters
- **Component**: App Manager (BAM), Signup
- **Files**: `en/signup-2_process.php` (line 30)
- **Description**: `if (strlen($password) < 6) sendJsonError('invalid_password');` — passwords as short as 6 characters are accepted.
- **Impact**: Weak passwords are trivially brute-forced or guessed. NIST SP 800-63B recommends a minimum of 8 characters; 12 is preferable.
- **Fix**: Raise the minimum to at least 8 characters. Consider enforcing character class requirements or checking against a common-passwords list.

---

### BUG-12 — `javascript:history.back()` Used as Error Redirect
- **Component**: App Manager (BAM)
- **Files**: `en/app-connect_process.php` (line 218)
- **Description**:
  ```php
  echo "<p><a href='javascript:history.back()'>Try again</a></p>";
  ```
  This is output server-side and relies on browser JavaScript behavior as a navigation mechanism.
- **Impact**: Non-standard and unreliable across environments. If JavaScript is disabled or the page was navigated directly, the link is non-functional. In some browser contexts this pattern can be abused.
- **Fix**: Redirect to a proper URL via `header("Location: ...")` or output a safe relative URL in the anchor's `href`.

---

## MEDIUM

These bugs have real security or correctness impact but require additional conditions to exploit, or affect a smaller attack surface.

---

### BUG-13 — Authorization Codes Have No Expiration Check
- **Component**: SSO / OIDC
- **Files**: `token.php` (lines 132-134, 257-260)
- **Description**: Authorization codes are stored with an `issued_at` timestamp but the token endpoint never checks whether the code has expired. OAuth 2.0 (RFC 6749 §4.1.2) requires codes to expire within a short window (typically 10 minutes).
- **Impact**: An intercepted authorization code remains valid indefinitely, giving an attacker unlimited time to exchange it for tokens.
- **Fix**: Add a check: `if (time() - $code_row['issued_at'] > 600) { /* reject */ }`. Delete expired codes in the validation step and via a cleanup cron.

---

### BUG-14 — Bot Detection Score Logged But Not Enforced
- **Component**: App Manager (BAM), Signup
- **Files**: `en/signup-2_process.php` (lines 40-52)
- **Description**: A bot score is calculated and written to the error log if it exceeds 70, but signup is never blocked:
  ```php
  if ($bot_score > 70) { error_log(...); }
  // ... continues to create the account
  ```
- **Impact**: Bot signups proceed unimpeded. The detection mechanism provides no actual protection.
- **Fix**: Block the signup request (return an error or present a CAPTCHA challenge) when the bot score exceeds the threshold. Tune the threshold based on observed false-positive rates.

---

### BUG-15 — Session Cookie Set with `SameSite=None`
- **Component**: App Manager (BAM)
- **Files**: `api/login_process.php` (lines 48-54)
- **Description**: Session cookies are configured with `SameSite=None`, which allows them to be sent on cross-origin requests. This weakens CSRF protection.
- **Impact**: Increases the attack surface for CSRF. Combined with missing CSRF validation (BUG-04, BUG-10), this significantly increases risk.
- **Fix**: Set `SameSite=Strict` (or `Lax` if cross-site POSTs are required by a known legitimate use case). `SameSite=None` requires `Secure` and should only be used when cross-origin cookie sharing is explicitly needed.

---

### BUG-16 — Profile Update Does Not Verify Ownership
- **Component**: App Manager (BAM)
- **Files**: `en/profile_update_process.php` (lines 20-60)
- **Description**: The profile to update is identified by a `?id=` GET parameter. The handler confirms that a session exists but does not verify that `$_SESSION['buwana_id']` matches the `id` being updated.
- **Impact**: An authenticated user can potentially modify another user's profile by changing the `id` parameter.
- **Fix**: Enforce `WHERE buwana_id = $_SESSION['buwana_id']` in the UPDATE query, ignoring any user-supplied ID.

---

### BUG-17 — Path Traversal Risk via `app_name` in File Path Construction
- **Component**: App Manager (BAM)
- **Files**: `en/app-connect_process.php` (line 192)
- **Description**: `app_name` is used directly in a file path:
  ```php
  $client_env_path = "../config/{$app_name}_env.php";
  ```
  If `$app_name` contains `../` sequences, this could reference files outside the intended directory.
- **Impact**: Potential local file inclusion if the resulting path resolves to a PHP file the attacker controls or can access.
- **Fix**: Validate `$app_name` against an allowlist of registered app names, or use `basename()` to strip directory components before constructing the path.

---

### BUG-18 — Unvalidated `redirect` Parameter After Login
- **Component**: App Manager (BAM)
- **Files**: `en/login.php` (line 118)
- **Description**: The `redirect` GET parameter is sanitized with `FILTER_SANITIZE_SPECIAL_CHARS` but not validated to be an internal/safe URL. It is then used as a post-login redirect destination.
- **Impact**: Users can be sent to external attacker-controlled sites immediately after logging in (open redirect), facilitating phishing.
- **Fix**: Validate that the redirect URL is a relative path or matches an expected internal hostname before using it.

---

### BUG-19 — `Access-Control-Allow-Credentials: true` on Token Endpoint
- **Component**: SSO / OIDC
- **Files**: `token.php` (lines 83-90)
- **Description**: The token endpoint sets `Access-Control-Allow-Credentials: true` in CORS responses. The OAuth 2.0 token endpoint uses Bearer tokens, not cookies — credential-bearing CORS requests are unnecessary.
- **Impact**: If any allowed origin is compromised or a subdomain takeover occurs, cross-origin requests with the user's session credentials can be made to the token endpoint.
- **Fix**: Remove `Access-Control-Allow-Credentials: true` from the token and userinfo CORS responses.

---

### BUG-20 — PKCE Not Enforced for Public Clients; No Length Validation
- **Component**: SSO / OIDC
- **Files**: `token.php` (lines 234-241)
- **Description**: PKCE (`code_challenge`/`code_verifier`) is implemented but:
  - Public clients are not required to use it
  - `code_verifier` length is not validated (RFC 7636 requires 43–128 characters)
  - Mixing PKCE and `client_secret` (confused deputy) is not prevented
- **Impact**: Public clients without PKCE are vulnerable to authorization code interception attacks.
- **Fix**: Require PKCE for all public clients. Validate `code_verifier` length (43–128 chars). Reject requests that supply both `client_secret` and `code_verifier`.

---

## LOW

These issues have limited immediate exploitability but represent poor practices, information leaks, or hygiene problems.

---

### BUG-21 — `test.php` Exposed on Server
- **Component**: SSO / OIDC
- **Files**: `test.php`
- **Description**: A test file exists that outputs all GET parameters via `print_r($_GET)`.
- **Impact**: Minor information disclosure; signals to attackers that the codebase may have other development artifacts exposed.
- **Fix**: Delete `test.php` from production. Add it to `.gitignore` if used locally.

---

### BUG-22 — Client ID Hardcoded in `token.php`
- **Component**: SSO / OIDC
- **Files**: `token.php` (line 307)
- **Description**: `$is_learning_app = ($client_id === 'lear_a30d677a7b08');` — a specific app is identified by a hardcoded string to apply special token behavior.
- **Impact**: Brittle; breaks silently if the client ID ever changes. Special-case logic should be driven by a database flag, not hardcoded strings.
- **Fix**: Add a boolean column (e.g., `special_token_behavior`) to `apps_tb` and query it instead.

---

### BUG-23 — Email Sanitized But Not Validated at Signup
- **Component**: App Manager (BAM), Signup
- **Files**: `en/signup-2_process.php` (line 26)
- **Description**: `FILTER_SANITIZE_EMAIL` strips illegal characters but does not confirm the result is a valid email address. `FILTER_VALIDATE_EMAIL` is not used.
- **Impact**: Invalid email addresses (e.g., `foo@` or `@bar.com`) may be stored in the database, breaking downstream email flows.
- **Fix**: Replace or supplement with `filter_var($email, FILTER_VALIDATE_EMAIL)` and reject invalid inputs.

---

### BUG-24 — Raw Database Errors Returned in API Responses
- **Component**: App Manager (BAM)
- **Files**: `en/edit-app-core.php` (lines 86, 93)
- **Description**: `$stmt->error` is included directly in JSON error responses returned to authenticated users.
- **Impact**: Leaks database schema, table names, and query structure to any authenticated user who triggers an error.
- **Fix**: Log the raw error server-side and return a generic message: `"An error occurred. Please try again."`.

---

### BUG-25 — `$redirect_url` Interpolated Directly into Inline `<script>`
- **Component**: Signup
- **Files**: `en/signup-1.php` (lines 45-48)
- **Description**:
  ```php
  echo "<script>
    window.location.href = '$redirect_url';
  </script>";
  ```
  If `$redirect_url` is not properly escaped for a JavaScript string context, injected quotes or script content will execute.
- **Impact**: XSS if `$redirect_url` originates from user input or an OAuth parameter and is not sanitized.
- **Fix**: Use `json_encode()` to safely embed PHP values in JavaScript: `window.location.href = <?php echo json_encode($redirect_url); ?>;`.

---

### BUG-26 — `DEVMODE = true` Hardcoded in API Login Handler
- **Component**: App Manager (BAM)
- **Files**: `api/login_process.php` (line 7)
- **Description**: `define('DEVMODE', true);` is hardcoded and enables verbose debug logging including session contents.
- **Impact**: Sensitive authentication data (session values, user IDs) may be written to logs accessible by lower-privileged processes.
- **Fix**: Control `DEVMODE` via an environment variable or a config file excluded from production deployments.

---

### BUG-27 — Missing HTTP Security Headers Across All Endpoints
- **Component**: SSO / OIDC, App Manager
- **Files**: All entry points
- **Description**: The following headers are absent from all responses:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
  - `Cache-Control: no-store` (on auth endpoints)
  - `Content-Security-Policy`
- **Impact**: Increases exposure to MIME-sniffing, clickjacking, downgrade attacks, and caching of sensitive auth responses.
- **Fix**: Add a centralized header-setting function called from all entry points, or configure these headers at the web server (nginx/Apache) level.

---

*Last updated: 2026-03-29*
