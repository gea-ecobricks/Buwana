# Buwana — CLAUDE.md

## Project Overview

Buwana is an open-source OpenID Connect identity provider (IdP) built in PHP, operated by the Global Ecobrick Alliance (a not-for-profit Earth Enterprise). It provides SSO authentication for a family of regenerative apps (GoBrik, Ecobricks.org, EarthCal, Earthen, etc.) and is designed to be usable by other aligned organizations.

Live host: `https://buwana.ecobricks.org`

The repository contains two distinct systems:
1. **Buwana App Manager (BAM)** — web interface for registering and administering client apps
2. **Buwana SSO / OIDC Provider** — the actual authentication service consumed by client apps

---

## Tech Stack

- **Backend**: PHP (no framework), Composer for dependencies
- **Database**: MySQL (accessed via PDO)
- **Tokens**: RS256-signed JWTs (asymmetric keypair per app, stored in `apps_tb`)
- **Frontend**: Vanilla JS, Chart.js (dashboard stats), HTML/CSS
- **Testing**: PHPUnit (`phpunit.xml` at root, run via `vendor/bin/phpunit`)
- **Dependencies**: Install with `composer install`

---

## Directory Structure

```
/
├── authorize.php              # OIDC authorization endpoint (GET)
├── token.php                  # Token endpoint (POST) — issues RS256 JWTs
├── userinfo.php               # Userinfo endpoint (GET, Bearer token)
├── auth_authorize.php         # Legacy auth entry point
├── fetch_app_info.php         # Loads app metadata from DB
├── earthenAuth_helper.php     # Shared session/auth utility functions
├── .well-known/
│   ├── openid-configuration.php  # OIDC discovery document
│   └── jwks.php                  # Public key set (CORS-enabled)
├── en/                        # English-language UI pages
│   ├── dashboard.php          # BAM entry point — app manager dashboard
│   ├── app-wizard.php         # Create new app (multi-step form)
│   ├── app-view.php           # Manage a specific app
│   ├── edit-app-*.php         # Edit app config (core, graphics, texts, signup)
│   ├── login.php              # Buwana login UI
│   ├── login_process.php      # Login form handler
│   ├── signup-1.php           # 7-step user signup wizard (signup-1 through signup-7)
│   └── app-connect.php        # Connect existing user to an app
├── api/                       # Internal JSON API endpoints
├── scripts/                   # Form-processing backend scripts
│   ├── create_app.php         # Handles app creation (generates client_id, keypair)
│   └── create_user.php        # Handles new user creation in client apps
├── processes/                 # Background/cron processes
│   └── crons/                 # Scheduled cleanup jobs
├── includes/                  # Shared PHP template partials
├── buwana-wiki/               # Developer documentation (Markdown)
└── cs_system/                 # Chat support module (separate)
```

Multi-language support mirrors `en/` for other locales (`fr/`, `es/`, `id/`, etc.).

---

## App Manager Entry Point

**`/en/dashboard.php`** is the main entry point for the Buwana App Manager.

- Requires an active Buwana session (`$_SESSION['jwt']`)
- Displays apps where the user is listed in `app_owners_tb`
- Shows user growth statistics and links to per-app management
- App creation: `app-wizard.php` → `scripts/create_app.php`
  - Generates unique `client_id`, `client_secret`, and an RS256 keypair (`jwt_private_key` / `jwt_public_key`) per app
  - Stores scopes as a comma-separated string, whitelisted to the scope list below (`openid,buwana:basic` always included)
  - Registers the creator as the primary owner in `app_owners_tb`
  - `edit-app-core.php` edits the same scope list; `processes/key_generator.php` (re)generates keys for an existing app

---

## OIDC / SSO Flow

Buwana implements the **OAuth 2.0 Authorization Code flow** with OpenID Connect.

### Endpoints

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/authorize` | Start auth flow — validates params, issues auth code |
| POST | `/token` | Exchange code for ID token + access token |
| GET | `/userinfo` | Return user profile from Bearer token |
| GET | `/.well-known/openid-configuration` | OIDC discovery document |
| GET | `/.well-known/jwks.php` | Public RSA keys for token verification |

### Issuer
```
https://buwana.ecobricks.org
```

### Supported Scopes
Keep these in sync across `.well-known/openid-configuration.php`, `authorize.php`, `token.php`, `en/edit-app-core.php`, `en/app-wizard.php` and `scripts/create_app.php`.

| Scope | Grants |
|-------|--------|
| `openid` + `buwana:basic` | Always on. `email`, `given_name`, `buwana:earthlingEmoji` |
| `buwana:profile` | `family_name`, `created_at`, `role`, `gea_status`, `profile_pic`, `language`, `country`, `birth_date`, `zoneinfo`, `community_id`, `brikcoin_balance`, `connected_app_ids` |
| `buwana:community` | `buwana:community` (resolved community name) |
| `buwana:bioregion` | `continent`, `location_full`, `location_watershed`, `watershed_name`, `watershed_id`, `location_lat`, `location_long` |
| `buwana:profile.read` | API permission: read the profile via the Buwana profile API (no token claims) |
| `buwana:profile.write` | API permission: update the profile via the Buwana profile API (no token claims) |

`authorize.php` rejects any `buwana:*` scope that is not in the app's `apps_tb.scopes` allow-list.

### Auth Flow Summary
1. Client redirects user to `/authorize` with `client_id`, `scope`, `state`, `nonce`, `redirect_uri`
2. If not logged in, Buwana stores the pending request in `$_SESSION['pending_oauth_request']` and shows its login page
3. After login, an authorization code is written to `authorization_codes_tb` and the user is redirected to `redirect_uri`
4. Client POSTs the code to `/token` — supports both:
   - **Confidential clients**: `client_secret` validation
   - **Public clients**: PKCE (`code_verifier` / `code_challenge`, S256 or plain)
5. `/token` issues two RS256-signed JWTs (ID token + access token), signed with the app's private key from `apps_tb`
6. Clients verify tokens via the public key from `/.well-known/jwks.php` (keyed by `kid` = `client_id`)

### Custom JWT Claims
Beyond standard OIDC, tokens include the scope-gated claims in the table above, plus:
- `color_mode` — `'light'` | `'dark'`, always present (no scope required). See Color Mode below.

---

## Color Mode (dark / light)

Shared contract between Buwana and every client app, specified in `docs/color-mode-policy.md`:
- localStorage key `color_mode` (`'light'` | `'dark'`); legacy keys `dark-mode-toggle` / `user_dark_mode` are fallback-only
- Carried across redirects as `?mode=` (and `?lang=`) in both directions — `authorize.php` reads it, stores it in `pending_oauth_request`, and appends it to the client callback
- Source of truth: `users_tb.color_mode`, emitted as the `color_mode` claim in the ID token and `/userinfo`
- Buwana-side toggles persist via `POST /api/set_color_mode.php` (session + CSRF)
- Pages apply the mode pre-paint from an inline `<head>` script (`header-2026.php`, `header-2026b.php`, `en/login.php`)

---

## Key Database Tables

| Table | Purpose |
|-------|---------|
| `apps_tb` | App registry — `client_id`, `client_secret`, RSA keypair, `redirect_uris`, scopes, branding |
| `users_tb` | Buwana user accounts — `buwana_id`, `open_id` (UUID), email, name, emoji, location, `color_mode` |
| `credentials_tb` | Login credentials — tracks failed attempts, brute-force lockout |
| `authorization_codes_tb` | Single-use auth codes with PKCE fields |
| `user_app_connections_tb` | Links users to apps — `status` (registered/pending), `connected_at` |
| `app_owners_tb` | Maps app owners — `buwana_id`, `is_primary` |

---

## User Signup Flow

7-step wizard at `/en/signup-1.php` through `/en/signup-7.php`:

1. Name entry
2. Credential (email/phone)
3. Credential verification (code)
4. Bioregional localization (river/watershed)
5. Newsletter preferences
6. Country, emoji avatar, community → user created via `scripts/create_user.php`
7. Confirmation and redirect to client app

---

## Security Notes

- Passwords: bcrypt hashed, failed-login tracking with 10-minute lockout reset
- Sessions: regenerated every 30 minutes; CSRF tokens on forms
- Auth codes: single-use (deleted immediately after token exchange)
- Redirect URIs: normalized before comparison to handle parameter-order variations
- CORS: token/userinfo endpoints whitelist known client app origins
- Tokens: 5400-second expiry, nonce in ID token for replay protection

---

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

---

## Key Helper

`check_user_app_connection()` in `earthenAuth_helper.php` — verifies the logged-in user has an active connection to the current app. Redirects to `/$lang/app-connect.php` (absolute path) if not connected.
