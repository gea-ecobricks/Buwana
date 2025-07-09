

*A meta-platform to empower developers to create, manage, and monitor user accounts and registration flow in their regenerative apps.*

---

## 🧩 COMPONENTS OVERVIEW

---

### 1. 🔐 Buwana App Manager Login

**Purpose:**  
Allow developers to log into the Buwana App Manager (BAM) using their existing **Buwana account credentials**.

**Features:**
- Buwana-based authentication (session-based, but one day using JWT)
- Redirect after login
- Session persistence
- Secure `signup-login_process.php` integration

---

### 2. 🧭 App Manager Dashboard

**Purpose:**  
Display a summary of all apps managed by the current user.

**Features:**
- List apps created by the user (`apps_tb`)
- Display key stats per app
- Show total registered users and growth comparison
- Show growth graph of net Buwana registration 
- Link to detailed app view

**Backend Logic:**
- Query `apps_tb` where `creator_buwana_id = $_SESSION['buwana_id']`
- Join with `user_app_connections_tb` for usage stats

** 📈 Graph of App growth **

**Features:**
- Line chart for user registrations over time
- Date range and app filters
- Compare app-specific vs global registrations

**Tech:**
- Chart.js 
- JSON API endpoint: `/analytics/get-growth-data.php?app_id=x&range=monthly`

**Data Source:**
- `user_app_connections_tb`
- Optional: timestamps from `users_tb`

---

### 4. 🧱 App Creation Wizard

**Purpose:**  
Allow developers to register and configure new apps within Buwana. Will break down app creation into 5 sections on one page.  Later we'll break this into a 5 step, 5 page process:

**Section 1:  Basic**


** FIXED:

* app_id

* client_id

* client_secret

* is_active

* allow_signup

* require_verification

* last_used_dt

* updated_dt

* owner relationships (via `app_owners_tb`)

** BASIC

* app_name

* app_registration_dt

* redirect_uris

* app_login_url

* scopes

* app_domain

* app_url

* app_dashboard_url

* app_description

* app_version

* app_display_name

* contact_email



** CORE TEXTS

* app_slogan

* app_terms_txt

* app_privacy_txt


* app_emojis_array



** BASIC GRAPHICS

* app_logo_url

* app_logo_dark_url

* app_square_icon_url

* app_wordmark_url

* app_wordmark_dark_url



** REGISTRATION GRAPHICS

* signup_1_top_img_light

* signup_1_top_img_dark

* signup_2_top_img_light

* signup_2_top_img_dark

* signup_3_top_img_light

* signup_3_top_img_dark

* signup_4_top_img_light

* signup_4_top_img_dark

* signup_5_top_img_light

* signup_5_top_img_dark

* signup_6_top_img_light

* signup_6_top_img_dark

* signup_7_top_img_light

* signup_7_top_img_dark

* login_top_img_light

* login_top_img_dark



**Post-Submission Actions:**
- Create entry in `apps_tb`
- Generate `client_id`
- Link app to developer via `creator_buwana_id`
- Redirect to app overview

---

### 5. 🧬 Single App Overview Page

**Purpose:**  
Detailed management view for a selected app.
Provide a page to edit, manage and delete app as well as to Visualize app's user growth trends.


**Features:**
- View branding assets
- Regenerate `client_id`
- Show total connections
- Show historical growth chart
- Edit and update app data

**Route:**
`/en/app-view.php?app_id=xxx`

---

### 6. 🌱 Admins Only: Buwana App Explorer

**Purpose:**  
Enable authenticated admins to view trends on all app.

**Features:**
- List Buwana Apps
- Show global Buwana Stats, number of apps, users, weekly growth
- Show line chart of all apps growth over time
- Show line chart of Buwana growth



---

## 📐 DATABASE TABLES

- `apps_tb` — Metadata and configuration for registered apps
- `users_tb` — Global user database for Buwana
- `credentials_tb` — Email/phone login credentials
- `user_app_connections_tb` — Links users to apps

---

## 📊 DEVELOPMENT PHASES

---

### 🚀 Phase 1: MVP
- [ ] App Manager login via Buwana
- [ ] App creation form
- [ ] Dashboard listing user-created apps

---

### 🔄 Phase 2: Analytics & Management
- [ ] Single app overview page
- [ ] Growth line chart
- [ ] Data API endpoint

---

### 🌍 Phase 3: Global Tools
- [ ] Public Buwana User Explorer
- [ ] Admin search and export tools
- [ ] TOS editor for developers
- [ ] Multi-app connection support

---

### ✅ Phase 4: Final Polish
- [ ] Dark/light logo previews
- [ ] Markdown support for TOS field
- [ ] App deletion and revocation flow
- [ ] Theming and accessibility testing

---

## 📝 LICENSE

All code and platform components are released under the **GNU AGPL v3** license.  
Built and maintained by the Global Ecobrick Alliance for the regenerative app developer community.

---
