# 📘 `apps_tb` — Buwana Application Registry Table

The `apps_tb` table is where all the juicy metadata about third-party or internal applications that use the Buwana authentication platform is stored. If an app wants to play nice with the Buwana login system, it better register here first!

---

## 🎯 Purpose

This table enables Buwana to:
- Store app-specific login and branding configurations
- Handle OAuth details like client ID and secret
- Display terms, privacy policies, and emoji-based UI themes per app
- Track app-specific user registration and authentication
- Integrate app-specific URLs for login, logout, and redirects

It's basically the app passport control of Buwana: no app gets in without it.

---

## 📊 Table Structure

| Field                      | Type              | Description |
|---------------------------|-------------------|-------------|
| `app_id`                  | `int(11)`         | Primary key. Unique identifier for each app. |
| `app_name`                | `varchar(100)`    | Internal name of the app. |
| `app_registration_dt`     | `datetime`        | When the app was registered. |
| `client_id`               | `varchar(100)`    | Public ID used in OAuth exchanges. |
| `client_secret`           | `varchar(255)`    | Secret key for secure app verification. |
| `redirect_uris`           | `text`            | List of allowed OAuth redirect URIs. |
| `logout_uri`              | `varchar(255)`    | Where to go after a logout. Optional. |
| `scopes`                  | `text`            | App permissions (like `email`, `profile`). |
| `app_domain`              | `varchar(255)`    | Primary domain (like `earthcal.com`). |
| `app_url`                 | `varchar(255)`    | App's public homepage URL. |
| `app_favicon`             | `varchar(255)`    | URL of the app's favicon. |
| `app_login_url`           | `varchar(255)`    | Where Buwana sends users to log in. |
| `app_description`         | `text`            | Description of what this app does. |
| `app_logo_url`            | `varchar(255)`    | Logo for light mode. |
| `app_version`             | `varchar(20)`     | Current version (e.g., `2.0`). |
| `app_display_name`        | `varchar(100)`    | Human-friendly name for the app. |
| `app_slogan`              | `varchar(255)`    | Catchy app tagline. |
| `app_terms_txt`           | `text (utf8mb4)`  | HTML-formatted Terms of Use. |
| `app_privacy_txt`         | `text (utf8mb4)`  | HTML-formatted Privacy Policy. |
| `is_active`               | `tinyint(1)`      | Whether the app is active (`1`) or not (`0`). |
| `allow_signup`            | `tinyint(1)`      | Whether new signups are allowed. |
| `require_verification`    | `tinyint(1)`      | Require email/phone verification? |
| `last_used_dt`            | `datetime`        | Last time someone logged in via this app. |
| `updated_dt`              | `datetime`        | Last updated timestamp. |
| _Removed:_ `owner_buwana_id` |                   | Owner linkage moved to `app_owners_tb`. |
| `contact_email`           | `varchar(255)`    | Developer or admin email. |
| `app_logo_dark_url`       | `varchar(255)`    | Logo for dark mode. |
| `app_wordmark_url`        | `varchar(255)`    | Horizontal logo for light mode. |
| `app_wordmark_dark_url`   | `varchar(255)`    | Horizontal logo for dark mode. |
| `signup_top_img_url`      | `varchar(255)`    | Top banner image during signup. |
| `signup_top_img_dark_url` | `varchar(255)`    | Dark mode version of the banner. |
| `app_emojis_array`        | `text (utf8mb4)`  | JSON array of themed emojis for UI animations. |

---

## 🚀 Quick Tips

- `client_id` and `client_secret` are essential for OAuth—don’t leave ‘em blank unless you want your app leaking like a sieve.
- Want your login screen to sparkle? Use `app_emojis_array` for animated flair.
- Store Terms and Privacy in HTML format. Inject directly into modals—no escapes needed.
- Use the `app_owners_tb` join table to keep track of which Earthling birthed each app.

---

## 🔗 Related Tables

- [`users_tb`](Buwana-Users-Table.md) — Who owns what?
- [`user_app_connections_tb`](user_app_connections_tb.md) — Tracks which users are connected to each app.

---

## 📅 Status

This table is **Pending Dev** — which means it’s locked and loaded but the admin UI for app creation and management is still baking. Manual insertions only for now.

---

*Source: Internal Buwana schema documentation and database SQL export*:contentReference[oaicite:0]{index=0}
