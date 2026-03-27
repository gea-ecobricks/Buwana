# Buwana Scope System

Buwana has just introduced an upgrade to its JWT system.  It now uses a namespaced, tiered scope system to control which user data is shared with client apps (like GoBrik, Earthcal and others) via the JWT ID token. Scopes are registered per-app in `apps_tb.scopes` and validated at the `/authorize` endpoint before a login flow begins. Only fields with non-empty values are included in the final token — empty or null fields are silently omitted.

---

## How Scopes Work

When a client app initiates an OAuth 2.0 / OpenID Connect login, it includes a `scope` parameter in the authorization request:

```
https://buwana.ecobricks.org/authorize?
  client_id={id}
  &response_type=code
  &redirect_uri={uri}
  &scope=openid buwana:basic buwana:profile
  &state={state}
  &nonce={nonce}
```

`/authorize.php` validates that every `buwana:*` scope in the request is listed in the app's registered scopes (`apps_tb.scopes`). Requesting an unregistered scope returns `400 invalid_scope` immediately — before the user sees any login page.

After a successful login, `/token.php` assembles the ID token by checking which scopes were granted and including only the corresponding claims. The scope string is echoed back in the token itself as the `scope` claim.

---

## Scope Tiers

### `openid` — Always required

The mandatory base scope required by the OpenID Connect specification. Triggers issuance of an ID token. Always permitted for every app — not gated by registration.

**Claims always present in every ID token:**

| Claim | Description |
|-------|-------------|
| `iss` | Issuer — always `https://buwana.ecobricks.org` |
| `sub` | Subject — the user's `open_id` UUID (falls back to `buwana_{id}`) |
| `aud` | Audience — the requesting `client_id` |
| `exp` | Expiry timestamp (issued at + 5400 seconds) |
| `iat` | Issued-at timestamp |
| `nonce` | Replay-protection nonce from the authorization request |
| `scope` | The full scope string granted to this token |

---

### `buwana:basic` — Identity fingerprint

Always granted alongside `openid`. Provides the minimal Buwana-specific identity data that every app needs to greet and identify a user. Registered as always-on in `apps_tb.scopes` — not user-toggleable.

**Additional claims provided:**

| Claim | Source field | Description |
|-------|-------------|-------------|
| `buwana_id` | `users_tb.buwana_id` | Numeric internal user ID |
| `email` | `users_tb.email` | User's email address |
| `given_name` | `users_tb.first_name` | User's first name |
| `buwana:earthlingEmoji` | `users_tb.earthling_emoji` | User's emoji avatar (defaults to 🌏 if unset) |

---

### `buwana:profile` — Extended personal data

Opt-in. Provides a fuller picture of the user's account and personal details. Request this scope when your app needs to personalise the experience beyond a first name, or when it integrates with Buwana account features such as brikcoin balance or language preferences.

**Additional claims provided:**

| Claim | Source field | Description |
|-------|-------------|-------------|
| `family_name` | `users_tb.last_name` | User's last name |
| `last_name` | `users_tb.last_name` | Non-standard alias for `family_name` (compatibility) |
| `created_at` | `users_tb.created_at` | Datetime the Buwana account was created |
| `role` | `users_tb.role` | Account role (e.g. `user`, `admin`) |
| `gea_status` | `users_tb.gea_status` | Global Ecobrick Alliance membership status |
| `profile_pic` | `users_tb.profile_pic` | URL to the user's profile picture |
| `language` | `languages_tb.language_name_en` | User's preferred language (resolved to English name) |
| `country` | `countries_tb.country_name` | User's country (resolved to full name) |
| `birth_date` | `users_tb.birth_date` | Date of birth (YYYY-MM-DD) |
| `zoneinfo` | `users_tb.time_zone` | User's time zone identifier |
| `community_id` | `users_tb.community_id` | Numeric ID of the user's primary community |
| `brikcoin_balance` | `users_tb.brikcoin_balance` | Current brikcoin balance (decimal) |
| `connected_app_ids` | `users_tb.connected_app_ids` | IDs of other Buwana apps the user is connected to |

> **Note:** `gea_status` and `profile_pic` default to the string `'null'` in the database when unset. These are treated as empty and omitted from the token.

---

### `buwana:community` — Community membership

Opt-in. Provides the user's community affiliation as a resolved human-readable name, looked up from `communities_tb`. Request this scope when your app displays or acts on a user's community context.

**Additional claims provided:**

| Claim | Source field | Description |
|-------|-------------|-------------|
| `buwana:community` | `communities_tb.com_name` | Full name of the user's primary community |

> The community name is resolved server-side via a JOIN on `users_tb.community_id → communities_tb.community_id`. If the user has no community set, this claim is omitted.

---

### `buwana:bioregion` — Geographic and watershed data

Opt-in. Provides the user's geographic location at the bioregional scale — continent, full location, and watershed details including coordinates. Request this scope when your app works with ecological, regional, or place-based data.

**Additional claims provided:**

| Claim | Source field | Description |
|-------|-------------|-------------|
| `continent` | `continents_tb.continent_name_en` | Continent name in English (resolved from `continent_code`) |
| `location_full` | `users_tb.location_full` | Full human-readable location string |
| `watershed_id` | `users_tb.watershed_id` | Numeric watershed ID |
| `watershed_name` | `watersheds_tb.watershed_name_en` | Watershed name in English |
| `location_watershed` | `users_tb.location_watershed` | User-entered watershed location label |
| `location_lat` | `users_tb.location_lat` | Latitude (decimal, up to 8 decimal places) |
| `location_long` | `users_tb.location_long` | Longitude (decimal, up to 8 decimal places) |

> All values are resolved server-side. Continent and watershed names are looked up via JOINs so client apps receive human-readable strings directly without needing their own lookups.

---

## Scope Registration

App owners configure scopes in the Buwana App Manager at **Edit Core → Scopes**. `openid` and `buwana:basic` are always registered and cannot be removed. `buwana:profile`, `buwana:community`, and `buwana:bioregion` are opt-in toggles.

Registered scopes are stored as a comma-separated string in `apps_tb.scopes`.

---

## Claim Omission Policy

No null or empty claim is ever included in the token. The token endpoint applies the following rule before adding any field:

- PHP `null` → omitted
- Empty string `""` → omitted
- String literal `"null"` (database default for some fields) → omitted

This means client apps should treat the absence of a claim as equivalent to "not set" and must not assume a claim will be present even when the corresponding scope is granted.

---

## Full Scope Reference

| Scope | Opt-in | Key claims |
|-------|--------|-----------|
| `openid` | No | `iss`, `sub`, `aud`, `exp`, `iat`, `nonce`, `scope` |
| `buwana:basic` | No | `buwana_id`, `email`, `given_name`, `buwana:earthlingEmoji` |
| `buwana:profile` | Yes | `family_name`, `created_at`, `role`, `gea_status`, `profile_pic`, `language`, `country`, `birth_date`, `zoneinfo`, `community_id`, `brikcoin_balance`, `connected_app_ids` |
| `buwana:community` | Yes | `buwana:community` |
| `buwana:bioregion` | Yes | `continent`, `location_full`, `watershed_id`, `watershed_name`, `location_watershed`, `location_lat`, `location_long` |
