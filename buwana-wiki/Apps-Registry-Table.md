## apps_tb — Application Registry Table

The `apps_tb` table stores metadata for all applications that use the Buwana authentication system. Each app that integrates with Buwana for login, credential management, or user services must register a record in this table.

This allows the registered app to use Buwana to:
- Provide an awesome registration process for their app
- Provide a versatile non-corporate login method for their app
- Track login and signup activity per app
- Display custom branding and messaging per app
- Manage app-specific user connections via `user_app_connections_tb`
- Provide app-specific terms of use, privacy policies, and emoji animation themes

---

### 🛠️ Table Overview

## 📦 `apps_tb` Table – Buwana Authentication System

The `apps_tb` table stores configuration and metadata for third-party or internal apps that use the Buwana authentication system. Each app entry defines how users from the Buwana system can register, authenticate, and interact with that app.

---

### 🔧 Table Structure

| Field                  | Type            | Description |
|------------------------|-----------------|-------------|
| `app_id`               | `int(11)`       | Primary key (auto-incremented). Unique identifier for the app. |
| `app_name`             | `varchar(100)`  | Internal name of the app. |
| `app_registration_dt`  | `datetime`      | Date and time when the app was registered with Buwana. |
| `client_id`            | `varchar(100)`  | Unique public identifier for OAuth or secure identification. |
| `client_secret`        | `varchar(255)`  | Private shared secret used for validating token exchanges. |
| `redirect_uris`        | `text`          | JSON or comma-separated list of valid redirect URIs for OAuth callbacks. |
| `logout_uri`           | `varchar(255)`  | Optional URI to redirect users after logout. |
| `scopes`               | `text`          | JSON or comma-separated list of scopes requested by this app (e.g., `profile`, `email`). |
| `app_domain`           | `varchar(255)`  | Primary domain (e.g. `gobrik.com`). |
| `app_url`              | `varchar(255)`  | Public landing/homepage URL of the app. |
| `app_favicon`          | `varchar(255)`  | URL to the app's favicon image. |
| `app_login_url`        | `varchar(255)`  | URL to redirect for login. |
| `app_description`      | `text`          | Short description of what this app does. |
| `app_logo_url`         | `varchar(255)`  | URL to the logo shown in Buwana login screens. |
| `app_version`          | `varchar(20)`   | Current version of the app (e.g., `1.0.2`). |
| `app_display_name`     | `varchar(100)`  | Display name shown to users (e.g., `EarthCal`). |
| `app_slogan`           | `varchar(255)`  | Short one-line marketing slogan. |
| `app_terms_txt`        | `text (utf8mb4)`| HTML-formatted Terms of Service text for modal display. |
| `app_privacy_txt`      | `text (utf8mb4)`| HTML-formatted Privacy Policy text for modal display. |
| `is_active`            | `tinyint(1)`    | `1` = Active (default), `0` = Disabled (blocks login access). |
| `allow_signup`         | `tinyint(1)`    | Allow new users to register using this app. |
| `require_verification` | `tinyint(1)`    | `1` = Require credential verification (e.g., email), `0` = Allow instant access. |
| `last_used_dt`         | `datetime`      | Timestamp of the last time a user authenticated via this app. |
| `updated_dt`           | `datetime`      | Last modified date (auto-updated). |
| _Removed:_ `owner_buwana_id` |   | Owner relationships are now managed in `app_owners_tb`. |
| `contact_email`        | `varchar(255)`  | Admin or developer contact address. |
| `app_logo_dark_url`    | `varchar(255)`  | Logo variant for dark backgrounds. |
| `app_wordmark_url`     | `varchar(255)`  | Horizontal logo (light mode). |
| `app_wordmark_dark_url`| `varchar(255)`  | Horizontal logo (dark mode). |
| `signup_1_top_img_light` | `text` | Top image for signup step 1 (light mode). |
| `signup_1_top_img_dark`  | `text` | Top image for signup step 1 (dark mode). |
| `signup_2_top_img_light` | `text` | Top image for signup step 2 (light mode). |
| `signup_2_top_img_dark`  | `text` | Top image for signup step 2 (dark mode). |
| `signup_3_top_img_light` | `text` | Top image for signup step 3 (light mode). |
| `signup_3_top_img_dark`  | `text` | Top image for signup step 3 (dark mode). |
| `signup_4_top_img_light` | `text` | Top image for signup step 4 (light mode). |
| `signup_4_top_img_dark`  | `text` | Top image for signup step 4 (dark mode). |
| `signup_5_top_img_light` | `text` | Top image for signup step 5 (light mode). |
| `signup_5_top_img_dark`  | `text` | Top image for signup step 5 (dark mode). |
| `signup_6_top_img_light` | `text` | Top image for signup step 6 (light mode). |
| `signup_6_top_img_dark`  | `text` | Top image for signup step 6 (dark mode). |
| `signup_7_top_img_light` | `text` | Top image for signup step 7 (light mode). |
| `signup_7_top_img_dark`  | `text` | Top image for signup step 7 (dark mode). |
| `login_top_img_light`    | `text` | Image above login form (light mode). |
| `login_top_img_dark`     | `text` | Image above login form (dark mode). |
| `app_emojis_array`     | `text (utf8mb4)`| JSON-encoded array of emojis to use in animations and UI during auth flow. |

---

### 💡 Usage Notes

- `client_id` and `client_secret` are used for OAuth flows. You may omit `client_secret` if using public clients like mobile apps.
- The `app_emojis_array` enables apps to personalize login animations and UI flair using themed emojis (e.g., 🐵🐢🌍).
- `app_terms_txt` and `app_privacy_txt` should contain **HTML-formatted** text to be injected directly into modals without escaping.
- Ownership is tracked via the `app_owners_tb` join table which links apps to their owners.

---

## 🧩 App Registration

Currently, we are manually entering new apps into the database (i.e. EarthCal).  However, in the future, once the process is solid, we'll provide an app admin interface (using buwana login of course) for just this purpose.  New apps will:

- Need to be registered in `apps_tb` (manual or admin-driven)
- Include its `app_id` in the signup/login URL (e.g. `signup.php?app=gbrk_123abc`)
- Load app-specific terms, privacy policy, and emoji spinner content from `apps_tb`
- Use the `user_app_connections_tb` table to log user connections (this will be taken care of by the provide BUwana signup flow)

---

### 🌈 Example: app_emojis_array

The `app_emojis_array` column should contain a JSON-encoded array of emoji characters. These are used in the animated submit button on signup/login pages.

