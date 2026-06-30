# Buwana Color-Mode (Dark / Light) Policy

Status: **Active** · Owner: Buwana SSO · Applies to: Buwana + all client apps
(GoBrik, Ecobricks.org, EarthCal, Earthen, …)

---

## 1. Problem

Buwana and each client app live on **different origins**
(`buwana.ecobricks.org`, `earthcal.app`, `gobrik.com`, …). Browser
`localStorage` and cookies are **partitioned by origin**, so there is no single
cache slot that both Buwana and a client can read and write. Historically each
app invented its own key (`dark-mode-toggle`, `user_dark_mode`, …) and never
synchronized, so a user crossing the login/auth boundary saw a jarring
dark→light→dark flash whenever the two sides disagreed.

This policy defines **one canonical color-mode preference** that follows the
user across every Buwana app, with no flash of the wrong theme.

---

## 2. The Contract

| Concern | Value |
|---|---|
| **localStorage key** | `color_mode` |
| **Allowed values** | `'dark'` \| `'light'` (lowercase strings) |
| **Default** | `'light'` |
| **URL transport param** | `?mode=dark` \| `?mode=light` |
| **Language transport param** | `?lang=` (ISO-639-1, e.g. `en`, `fr`, `es`, `id`) |
| **JWT / userinfo claim** | `color_mode` (top-level, non-namespaced) |
| **Server source of truth** | `users_tb.color_mode` |

Every app — Buwana and clients alike — **must** use exactly these names and
values. No `user_dark_mode`, no `dark-mode-toggle`, no booleans, no `'true'`.

---

## 3. Source of Truth

**Buwana owns the canonical preference.** It is persisted server-side in
`users_tb.color_mode` and is the authority for a logged-in user across devices
and browsers.

- It is emitted as the `color_mode` claim in the **ID token** and in the
  **`/userinfo`** response. The claim is part of the always-present base claims
  (it carries no PII and is useful to every client), so it does **not** require
  a scope.
- A client adopts the claim value on login and writes it to its own
  `localStorage['color_mode']`.

This is consistent with the broader Buwana rule that **Buwana is the source of
truth** for user profile data and clients mirror it locally.

---

## 4. Transport: keeping the two sides in sync

The JWT claim is durable but only arrives *after* token exchange. To prevent a
flash during the unauthenticated / pre-token phase, the current mode is also
carried across **every redirect boundary** as a URL param. Both mechanisms run
together:

### 4.1 Client → Buwana (authorization request)
The client appends its current mode and language to the `/authorize` request:

```
/authorize?...&mode=dark&lang=en
```

Buwana's `authorize.php`:
1. Validates `mode ∈ {light, dark}` and `lang` against supported locales.
2. If the user is **logged in** and `mode` is present, updates
   `users_tb.color_mode` (a fresh client-side toggle is a real preference
   expression — last write wins).
3. Carries `mode`/`lang` into `pending_oauth_request` so they survive the login
   detour, and forwards `?mode=` to `login.php`.

`login.php` applies the mode **before first paint** via a render-blocking inline
script in `<head>` and writes `localStorage['color_mode']`.

### 4.2 Buwana → Client (authorization response / callback)
When Buwana redirects back with the auth code, it **also** appends the canonical
mode (and lang):

```
{redirect_uri}?code=...&state=...&mode=dark&lang=en
```

The client's callback page:
1. Reads `?mode=` from the URL and applies it **before first paint** (kills the
   flash before the token even arrives).
2. After token exchange, reconciles with the `color_mode` claim and writes the
   final value to `localStorage['color_mode']`.

---

## 5. No-Flash Requirement

Applying the theme on `DOMContentLoaded` or later is too late — the browser has
already painted the default theme. Every Buwana and client page that can render
themed content **must** apply the mode from a **render-blocking inline script in
`<head>`**, before any themed CSS paints. Resolution order for that script:

1. `?mode=` URL param, if present and valid.
2. `localStorage['color_mode']`, if present and valid.
3. `prefers-color-scheme` media query.
4. Default `'light'`.

The resolved value is written to `localStorage['color_mode']` and applied (via
`data-theme` attribute on `<html>` and/or toggling the app's light/dark
stylesheet links).

---

## 6. Writing a new value (toggling)

- **On a client:** write `localStorage['color_mode']`, apply immediately, and —
  if the user is logged in — push to Buwana so the source of truth updates. The
  minimal path is to send `?mode=` on the next `/authorize` round-trip
  (§4.1.2); apps with `buwana:profile.write` may also `PATCH` the profile API.
- **On Buwana:** write `localStorage['color_mode']`, apply immediately, and
  persist to `users_tb.color_mode` for the logged-in user.

Conflict rule: **last write wins.** A mode arriving via `?mode=` from an
authenticated client overwrites the stored value, because it represents the
user's most recent explicit choice.

---

## 7. Reference values

```
localStorage key : color_mode
values           : "dark" | "light"
url param        : mode=dark | mode=light
lang param       : lang=en | fr | es | id | ...
jwt claim        : color_mode
db column        : users_tb.color_mode  (VARCHAR(5) NOT NULL DEFAULT 'light')
html attribute   : <html data-theme="dark">
```

---

## 8. Migration notes

- Legacy keys (`user_dark_mode`, `dark-mode-toggle`) should be read **once** as a
  fallback when `color_mode` is absent, then superseded by `color_mode`. New
  writes go only to `color_mode`.
- The `?mode=` param name is retained (Buwana and EarthCal already used it) — only
  the storage key, claim, and DB column are newly standardized.
