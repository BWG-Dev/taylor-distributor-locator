# Taylor Distributor Locator — Installation and Configuration Guide

**Plugin version:** 0.6.0
**Document version:** 1.0 — 2026-08-17
**Audience:** the person deploying and configuring the plugin on the production site.

This document covers everything needed to install, configure, and verify the
Taylor Distributor Locator on a live WordPress site. Section 15 is a condensed
launch-day checklist; a standalone copy lives in `GO-LIVE-CHECKLIST.md`.

Anything written as `<angle brackets>` is a value the client or hosting team
must supply — it has deliberately not been guessed.

---

## 1. What the plugin does

- Registers a public distributor locator (search + map) via the `[tdl_locator]` shortcode.
- Stores distributor **physical locations** and **service territories** in dedicated database tables.
- Geocodes location addresses to latitude/longitude so pins can be placed on the map.
- Provides CSV import and export tools for bulk distributor data management.
- Routes "Request Quote" form submissions to the correct distributor by email.
- Sends the same submissions to HubSpot as CRM contacts.

Two concepts are deliberately separate throughout the plugin and must stay that way:

| Concept | Meaning | Where it lives |
|---|---|---|
| **Physical location** | Where the distributor's building is. Drives the map pin. | `tdl_locations` table |
| **Service territory** | Where the distributor is allowed to sell. Drives search results. | `tdl_service_zones` table |

A ZIP/postcode search matches **service territory**, not the distributor's own
postal code. This is why a distributor in Illinois can legitimately appear in a
search for a Mexican postcode.

---

## 2. Requirements

### 2.1 Server

| Requirement | Minimum | Verified against |
|---|---|---|
| WordPress | 6.4 | 7.0.3 |
| PHP | 8.1 | 8.3.14 |
| MySQL / MariaDB | 5.7 / 10.3 | MySQL 9.1 |
| HTTPS | Required (Google Maps and HubSpot both require it in production) | — |
| Outbound HTTPS from the web server | Required — to `maps.googleapis.com`, `api.hubapi.com`, and `unpkg.com` | — |
| WP-Cron | Must be functional (geocoding runs as a background job) | — |

### 2.2 Required plugins

These must be installed **and active**. Versions listed are what the plugin was
built and tested against.

| Plugin | Version tested | Why it is required |
|---|---|---|
| **Toolset Types** | 3.6.1 | **Registers the `distributor` post type and all contact fields.** Without it the locator has no data to read. See §3. |
| **Gravity Forms** | 2.10.5 | Provides the Request Quote form used by the locator modal. |
| **Gravity Forms Webhooks Add-On** | 1.7.0 | Transport for the HubSpot integration. |

### 2.3 Recommended / optional plugins

| Plugin | Version tested | Notes |
|---|---|---|
| Gravity Forms reCAPTCHA Add-On | 2.2.2 | Strongly recommended. The quote form is public and unprotected without it. |
| WPML (`sitepress-multilingual-cms`) | 4.9.3 | Only if the site is multilingual. See §13. |
| An SMTP plugin (WP Mail SMTP, Post SMTP, or equivalent) | — | Strongly recommended. See §11.4. |

### 2.4 Theme

Developed against **Kadence 1.5.2** with the `wdw-custom-theme` child theme.
The locator inherits colours from Kadence CSS variables when the plugin's own
colour settings are left blank. It does not depend on Kadence to function — on
a different theme, set the colours explicitly in §7.6.

---

## 3. Critical dependency: Toolset Types

**Read this before deploying.** It is the single most common cause of a broken
install.

The `distributor` post type and its contact fields are **not** created by this
plugin. They are defined in **Toolset Types**, and those definitions are stored
in the WordPress database (`wpcf-custom-types` and `wpcf-fields` options), not
in any file in this repository.

Consequences:

- If Toolset Types is deactivated, the Distributors menu disappears and the
  locator returns no results. The data is not lost — it returns when Types is
  reactivated.
- If the production site is built from a **copy of the development database**,
  the definitions come across automatically. This is the recommended path.
- If the production site is a **fresh WordPress install**, the post type and all
  eight custom fields must be recreated in Toolset Types **before** importing
  any distributor data, using exactly the slugs below.

### 3.1 Required post type

| Setting | Value |
|---|---|
| Post type slug | `distributor` |
| Plural label | Distributors |
| Singular label | Distributor |

### 3.2 Required custom fields

All eight are plain text/email fields on the `distributor` post type. The
**field slug must match exactly** — the plugin, CSV tools, email routing, and
HubSpot sync all read these keys directly.

| Field slug | Stored meta key | Purpose |
|---|---|---|
| `phone` | `wpcf-phone` | Main company phone |
| `website` | `wpcf-website` | Company website URL |
| `email_main` | `wpcf-email_main` | General enquiries — **fallback for quote routing** |
| `email_sales` | `wpcf-email_sales` | **Primary destination for quote requests. Business-critical — never rename.** |
| `email_parts` | `wpcf-email_parts` | Parts enquiries |
| `email_service` | `wpcf-email_service` | Service enquiries |
| `email_installations` | `wpcf-email_installations` | Installation enquiries |
| `service_area_description` | `wpcf-service_area_description` | Free-text service-area notes shown on the frontend |

> **Do not rename `email_sales`.** Quote routing resolves `email_sales` first and
> falls back to `email_main`. Renaming it silently sends every lead to the
> fallback address.

---

## 4. Deployment approach

Choose one.

### Option A — Database + files migration (recommended)

Copy the development/staging database and the `wp-content` directory to
production. Everything — Toolset definitions, 164 distributors, 685 locations,
53,979 service zones, plugin settings, the Gravity Forms form and its webhook
feed — arrives intact. Only environment-specific values need changing
(§5.2 and §15).

### Option B — Fresh install

Only if a clean production build is required. Order matters:

1. Install and activate Toolset Types, then recreate the post type and fields per §3.
2. Install and activate Gravity Forms + Webhooks Add-On.
3. Install and activate this plugin (§5).
4. Recreate the Request Quote form (§10) — note its form ID.
5. Import distributor data from CSV (§12.1).
6. Run geocoding (§9).
7. Recreate the webhook feed (§12.3 / §12).

Option B requires a current CSV export from the development site. Produce it via
**Distributors → Export CSV** before decommissioning that environment.

---

## 5. Installing the plugin

### 5.1 Install

1. Upload the `taylor-distributor-locator` folder to `wp-content/plugins/`
   (or upload the plugin ZIP via **Plugins → Add New → Upload Plugin**).
2. Activate it under **Plugins**.

Activation creates three database tables (prefix will match the site's, shown
here as `wp_`):

| Table | Contents |
|---|---|
| `wp_tdl_locations` | Physical addresses, coordinates, per-location contact details |
| `wp_tdl_service_zones` | ZIP codes, ZIP ranges, states, and countries served |
| `wp_tdl_routing_log` | Quote routing and HubSpot sync events |

Activation also sets default options and flushes rewrite rules. It is safe to
deactivate and reactivate; no data is touched.

> **Warning — deleting the plugin destroys data.** WordPress's *Delete* action
> runs `uninstall.php`, which **drops the `tdl_locations` and `tdl_service_zones`
> tables** and deletes all plugin settings. Deactivation is safe; deletion is
> not. Always take a database backup first.

### 5.2 wp-config.php additions

Add the HubSpot access token as a constant rather than storing it in the
database. Place this above the `/* That's all, stop editing! */` line:

```php
define( 'TDL_HUBSPOT_TOKEN', '<hubspot-private-app-token>' );
```

When this constant is present the plugin uses it and the Settings field becomes
read-only, so the credential never enters the database or a database backup.

---

## 6. Where everything lives in the admin

All plugin screens are under the **Distributors** menu.

| Screen | Capability | Purpose |
|---|---|---|
| Distributors → All Distributors | `edit_posts` | List, filter by geocode status, edit distributors |
| Distributors → Settings | `manage_options` | All plugin configuration (§7) |
| Distributors → Geocoding Status | `manage_options` | Run/monitor geocoding, review errors (§9) |
| Distributors → Routing Log | `manage_options` | Quote routing + HubSpot sync history (§11.3) |
| Distributors → Import CSV | `manage_options` | Bulk create/update distributors (§12.1) |
| Distributors → Export CSV | `manage_options` | Full data export / backup (§12.2) |
| Distributors → M2 Initial Import | `manage_options` | One-time original data load. Not needed post-launch. |

On a single distributor's edit screen:

- **Toolset field group** — the eight contact fields from §3.2.
- **Physical Locations** meta box — addresses, coordinates, per-location contacts, hours.
- **Service Zones** meta box — ZIPs, ZIP ranges, states, countries served.
- **Parent Distributor** meta box (sidebar) — links a branch to its parent company.

---

## 7. Settings reference

**Distributors → Settings.** Every value below is admin-editable; nothing is
hardcoded.

### 7.1 General

| Setting | Option key | Default | Production guidance |
|---|---|---|---|
| Map Provider | `tdl_map_provider` | `openstreetmap` | Set to **Google Maps** to match the current build. |
| Google Maps API Key | `tdl_google_maps_api_key` | empty | Required when provider is Google. See §8. |
| Google Map ID | `tdl_google_maps_map_id` | empty | Required for Advanced Markers. See §8.3. |
| Default Map Zoom | `tdl_default_map_zoom` | 4 (5 on activation) | 5 is the current setting. |
| Default Map Center Latitude | `tdl_default_map_center_lat` | `39.8283` | Currently `42.4620761` (Rockton, IL). |
| Default Map Center Longitude | `tdl_default_map_center_lng` | `-98.5795` | Currently `-89.0706026`. |
| Results Per Page | `tdl_results_per_page` | 20 | |
| Show City / Region Tab | `tdl_show_city_tab` | on | Currently **off**. Confirm the client's preference before launch. |
| Cache Duration (minutes) | `tdl_cache_duration` | 60 | Leave at 60. See §7.5. |

### 7.2 Email Routing

| Setting | Option key | Default | Production guidance |
|---|---|---|---|
| CC Email | `tdl_cc_email` | empty | **Currently set to the placeholder `routing_example@taylor.com`.** Replace with the real address or clear it before launch. |
| Quote Form ID | `tdl_gf_quote_form_id` | 1 | Must match the Gravity Forms form ID (§10). |

### 7.3 HubSpot Integration

| Setting | Option key | Default | Production guidance |
|---|---|---|---|
| Enable HubSpot | `tdl_hubspot_enabled` | off | Turn on only when the client is ready to receive live leads. |
| Access Token | `tdl_hubspot_token` | empty | Prefer the `wp-config.php` constant (§5.2). |
| API Endpoint | `tdl_hubspot_endpoint` | `https://api.hubapi.com/crm/v3/objects/contacts` | Must match the webhook feed's Request URL exactly. |
| Company Property | `tdl_hubspot_prop_company` | `company` | Leave as-is. |
| Distributor Property | `tdl_hubspot_prop_distributor` | `distributor_name` | Leave as-is. See the warning in §12.2. |
| Message Property | `tdl_hubspot_prop_message` | `taylor_message` | Leave as-is. |
| Lead Source Property | `tdl_hubspot_prop_lead_source` | `lead_source` | Blank disables sending lead source. |
| Lead Source Value | `tdl_hubspot_lead_source_value` | `Distributor Locator` | |

### 7.4 Colours

Nine optional colour overrides (`tdl_color_primary`, `tdl_color_secondary`,
`tdl_color_accent`, `tdl_color_btn_bg`, `tdl_color_btn_text`,
`tdl_color_card_border`, `tdl_color_card_active`, `tdl_color_text`,
`tdl_color_link`). **Leave blank to inherit the theme's colours** — that is the
current configuration. Set a hex value only to override.

### 7.5 Search cache

Search results are cached for the duration in §7.1. The cache is keyed on an
internal data version that is bumped automatically whenever a distributor is
saved, a migration runs, or the language is switched — so ordinary edits appear
immediately.

Use **Clear Cache Now** on the Settings page after a bulk CSV import or a direct
database change, which the plugin cannot detect.

---

## 8. Google Maps setup

Required only when Map Provider is **Google Maps** (the current configuration).

### 8.1 APIs to enable

In Google Cloud Console, on the billing-enabled project that owns the key:

| API | Used for |
|---|---|
| **Maps JavaScript API** | Rendering the frontend map |
| **Geocoding API** | Converting addresses to coordinates (§9) |
| **Places API** | Loaded by the map script (`libraries=marker,places`) |

Google Maps requires an active billing account. Without one the map renders
watermarked and degraded, or not at all.

### 8.2 Key restrictions (do this — the key is public)

The API key is visible in the page source of any page containing the locator.
Restrict it:

- **Application restriction:** HTTP referrers → `https://<production-domain>/*`
  (add `https://www.<production-domain>/*` if both hosts resolve).
- **API restriction:** limit to the three APIs in §8.1.

The Geocoding API is called **server-side**, so a referrer-restricted key will
be rejected for geocoding. Two options:

1. Create a **second, IP-restricted key** for geocoding — restricted to the
   production server's outbound IP address, limited to the Geocoding API. This
   is the more secure arrangement but currently requires a code change to use a
   separate key, so raise it with the developer if the client wants it.
2. Leave the single key unrestricted-by-IP for now and rely on API restriction
   plus budget alerts.

Set a **budget alert** on the Google Cloud project either way.

### 8.3 Map ID

Advanced Markers require a Map ID (Google Cloud Console → Google Maps Platform →
Map management → Create Map ID, type: JavaScript, raster or vector). Paste it
into the **Google Map ID** setting. Without it, markers will not render.

### 8.4 OpenStreetMap alternative

Setting Map Provider to **OpenStreetMap (Leaflet)** removes the Google
dependency entirely — no key, no billing. Geocoding then uses OpenStreetMap's
Nominatim service, which is rate-limited and subject to a usage policy that
discourages bulk use. Suitable as a contingency, not as the primary
configuration for a site of this size.

Leaflet, its marker-clustering plugin, and the Google marker clusterer load from
the public **unpkg.com** CDN. If the hosting environment blocks outbound CDN
requests or the client requires all assets to be self-hosted, flag it — that is
a code change.

---

## 9. Geocoding

Map pins require latitude and longitude. Addresses without coordinates are
searchable but invisible on the map.

### 9.1 Running it

**Distributors → Geocoding Status** shows total locations, how many are
geocoded, how many are pending, and how many previously errored.

| Button | Effect |
|---|---|
| **Run Geocoding** | Processes all locations that have no coordinates and no recorded error. |
| **Re-geocode All (incl. errors)** | Clears all error flags and retries everything missing coordinates. Use after fixing bad addresses or a bad API key. |
| **Cancel** | Stops the background run. |
| **Clear All Errors** | Clears error flags without starting a run. |

Geocoding runs as a **WP-Cron background job** in batches — you can navigate
away. Progress updates live on the status page. Individual distributors can also
be geocoded from the **Run Geocode** button in the Geocode Status column of the
distributor list, and saving a distributor with a new address schedules
geocoding automatically.

Results are cached for 90 days, so re-running does not re-bill identical
addresses.

### 9.2 State on the development database

At time of writing: **685 locations, 337 geocoded (49%), 46 pending, 302
recorded as errors.** The errors are predominantly incomplete or non-deliverable
addresses in the source data (PO boxes without a city, missing postal codes,
international addresses lacking a country).

**This needs a decision before launch.** Roughly half of all locations will not
appear as map pins. They still appear in list results, so search remains
correct, but the map will look sparse. Options: accept it, correct the source
addresses and re-run, or have the client supply better data for the worst
offenders. Re-check these counts after migrating to production — the numbers
travel with the database.

### 9.3 If geocoding does nothing

Check, in order: WP-Cron is running; the Google Maps API key is valid and the
Geocoding API is enabled on it; the site can make outbound HTTPS requests; the
error list on the status page for the specific message returned.

---

## 10. The locator page

### 10.1 Placing the shortcode

Add `[tdl_locator]` to any page or post. On the current build it sits on page ID
10. Confirm the correct production page with the client — it is likely a
dedicated "Find a Distributor" page rather than the home page.

### 10.2 Shortcode attributes

All are optional; each falls back to the corresponding setting.

| Attribute | Default | Purpose |
|---|---|---|
| `map_height` | `600px` | Map container height (any CSS length) |
| `show_map` | `true` | Set `false` for a list-only layout |
| `show_list` | `true` | Set `false` for a map-only layout |
| `default_country` | empty | Pre-filter results to a country code, e.g. `US` |
| `results_per_page` | Settings value | Results per page |
| `zoom` | Settings value | Initial zoom level |
| `center_lat` | Settings value | Initial map centre latitude |
| `center_lng` | Settings value | Initial map centre longitude |

Example: `[tdl_locator map_height="700px" default_country="US"]`

### 10.3 What the page exposes publicly

The locator's REST endpoints are public by design (they power an anonymous
search) and return, for each matched distributor: name, phone, website, service
area notes, all five email addresses, and every location's address, coordinates,
phone, and hours.

**All five distributor email addresses are publicly visible and scrapable.**
That is the intended behaviour — the locator exists so customers can contact
distributors — but the client should be aware of it. No internal notes,
unpublished distributors, or admin-only data are exposed.

---

## 11. Quote form and email routing

### 11.1 How routing works

1. A visitor clicks **Request Quote** on a distributor card or map pin.
2. The modal opens and a hidden field is populated with that distributor's ID.
3. On submit, the distributor ID is validated server-side — an unknown or
   unpublished distributor is rejected before an entry is saved.
4. The plugin resolves the recipient: **`email_sales` → `email_main` → error**.
5. An HTML quote email is sent, and the decision is written to the routing log.

Routing runs **before** the HubSpot webhook, deliberately, so that a slow or
failing HubSpot cannot delay or prevent the distributor's email.

### 11.2 Gravity Forms configuration

| Item | Value on the current build |
|---|---|
| Form | "Request Quote", **form ID 1** |
| Name | field 1 (first `1.3`, last `1.6`) |
| Email | field 3 |
| Phone | field 4 |
| Company | field 5 |
| Message / Needs | field 6 |
| Distributor ID (hidden) | field 7 — created automatically |
| Distributor Name (hidden) | field 8 — created automatically |

The two hidden fields are added programmatically on first load and their IDs
stored in options. Do not delete or renumber them. If the form is ever rebuilt
from scratch, update **Quote Form ID** in Settings and let the plugin recreate
the hidden fields.

> **Notification rule:** the plugin suppresses Gravity Forms' own
> *admin* notifications on this form (those sent to a fixed address) to prevent
> duplicate emails. Customer confirmation notifications — those sent to the
> submitter's email field — are untouched and still work. If someone later adds
> a new fixed-address notification to this form, **it will not fire.** Use the
> CC Email setting (§7.2) instead.

### 11.3 Routing log

**Distributors → Routing Log** records every submission:

| Severity | Meaning |
|---|---|
| **Info** (blue) | Routed to `email_sales` as intended, or HubSpot sync succeeded |
| **Warning** (orange) | Fell back to `email_main` because `email_sales` was empty, or HubSpot was skipped |
| **Error** (red) | No valid email, or the send failed, or HubSpot returned an error |

A steady stream of warnings means distributors are missing `email_sales` values —
worth exporting to CSV and asking the client to fill in.

### 11.4 Deliverability

Emails are sent with `wp_mail()` from the site's admin email address, using the
site name as the sender. On most hosts, PHP mail lands in spam or is dropped.

**Configure an SMTP plugin with an authenticated sender before launch**, and
ensure SPF, DKIM, and DMARC are set for `<sending-domain>`. This is the single
most likely cause of "distributors say they never got the lead" after go-live.
Sending from an address on the site's own domain (not a distributor's domain, and
not a free mailbox provider) is important for the same reason.

---

## 12. HubSpot integration

Quote submissions are also created as **contacts** in HubSpot. The transport is
the Gravity Forms Webhooks Add-On; the plugin reshapes the payload, attaches the
access token, and resolves the distributor name server-side.

### 12.1 Property mapping

| Form data | HubSpot contact property |
|---|---|
| Email | `email` |
| First name | `firstname` |
| Last name | `lastname` |
| Phone | `phone` |
| Company | `company` |
| Message / Needs | `taylor_message` |
| Distributor (resolved from the post) | `distributor_name` |
| Fixed value `Distributor Locator` | `lead_source` |

All four custom targets are **free-text** properties, verified against the
client's portal. Do not point the Distributor Property at `taylor_distributor`:
that is a 35-option dropdown covering US distributors only, and HubSpot silently
discards values that match no option — roughly three quarters of leads would
arrive blank with no error anywhere.

### 12.2 Setup

1. **Token.** Define `TDL_HUBSPOT_TOKEN` in `wp-config.php` (§5.2), or paste it
   into Settings → HubSpot Integration → Access Token.
2. **Test connection.** Click **Test connection** on the Settings page. Expect
   `Connected to portal <id> (STANDARD)` plus a production-portal warning. The
   check is read-only and touches no CRM records.
3. **Property names.** Leave at defaults unless HubSpot's schema changes.
4. **Enable HubSpot.** Tick only when the client is ready for live leads.

### 12.3 The webhook feed (required — nothing sends without it)

**Forms → Request Quote → Settings → Webhooks → Add New:**

| Field | Value |
|---|---|
| Name | `HubSpot Contact` |
| Request URL | `https://api.hubapi.com/crm/v3/objects/contacts` |
| Request Method | `POST` |
| Request Format | `JSON` |
| Field mapping | **Leave empty** — the plugin replaces the body |
| Headers | **Do not add the token here** — it is injected at request time |

The feed must point at a `hubapi.com` host; that is how the plugin recognises it
as a HubSpot feed. Any other webhook feed, on this or any other form, passes
through untouched.

This feed already exists on the development database and migrates with it under
Option A.

### 12.4 Verifying

Submit a test quote, then confirm:

- The contact appears in HubSpot with all mapped properties populated.
- `lead_source` reads `Distributor Locator`.
- `distributor_name` contains the company name with **no HTML entities**
  (`&` not `&#038;`) and **no location suffix** (no ` — City`).
- Distributors → Routing Log shows `[HubSpot] Contact synced (HTTP 201)`.

Because there is no sandbox portal, **every test creates a real contact in the
live CRM.** Use a unique plus-addressed email per attempt
(`you+tdl01@example.com`), never one that already exists in the portal, record
each address used, and delete the test contacts afterwards. Note that HubSpot
workflows and any Salesforce sync may fire on creation and are **not** undone by
deleting the contact.

### 12.5 Independence from email routing

By design, HubSpot cannot break lead delivery. Verify this after launch:

| Test | Expected result |
|---|---|
| Set an invalid token, submit | Distributor email still arrives; log shows `[HubSpot] HTTP 401` |
| Untick Enable HubSpot, submit | Distributor email still arrives; log shows the skip; no HTTP request made |

Restore the valid token and the enabled state afterwards.

---

## 13. WPML (only if the site is multilingual)

- `wpml-config.xml` ships with the plugin and is detected automatically. Contact
  and email fields are set to **copy** across translations; the service-area
  description is set to **translate**.
- The search cache is invalidated automatically on language switch.
- Frontend UI strings are registered for translation, but **WPML String
  Translation is not installed** on the current build — string registration is
  an inert no-op until it is added. Install String Translation if the client
  wants the locator's buttons and labels translated.
- **Known limitation:** if translated *distributor posts* are created, search
  results may come back empty in non-default languages, because location and
  service-zone data is keyed to original-language post IDs. The client is
  expected to use WPML for interface language only. If they intend to translate
  distributor records themselves, raise it before launch — it needs development.

---

## 14. Ongoing data management

### 14.1 Importing (Distributors → Import CSV)

Bulk create or update distributors. Recognised columns:

`company_name` (required), `phone`, `website`, `email_main`, `email_sales`,
`email_parts`, `email_service`, `email_installations`, `service_area_notes`,
`location_name`, `street_address`, `address_2`, `address_3`, `city`,
`state_province`, `zip_postal`, `country_code`, `latitude`, `longitude`,
`location_phone`, `hours_operation`, `is_primary`, `zip_codes_served`,
`states_served`, `countries_served`

A **sample CSV template** is downloadable from the import page. The importer
supports a **dry run** — always use it first: it reports row-level errors and
what would be created or updated, without writing anything. Rows missing
coordinates are queued for geocoding automatically.

Take a database backup before any live import.

### 14.2 Exporting (Distributors → Export CSV)

Exports all distributors, contact fields, locations, and service zones in the
same column format the importer accepts. Useful as a data backup, for handing
data to the client, and as the source file for an Option B rebuild. Run an export
before any bulk change.

### 14.3 Adding a distributor manually

1. **Distributors → Add New**, enter the company name as the title.
2. Fill the Toolset contact fields — **`email_sales` matters most**; it is where
   that distributor's leads will go.
3. Add at least one **Physical Location** (a primary one) for the map pin.
4. Add **Service Zones** — the ZIPs, states, or countries they may sell into.
   Without service zones the distributor will not appear in any search.
5. Set a **Parent Distributor** if this is a branch of a company already listed.
6. Publish, then geocode from the distributor list if the location has no
   coordinates.

---

## 15. Go-live checklist

A standalone tick-box copy is in `GO-LIVE-CHECKLIST.md`.

**Before launch**

- [ ] Full database and file backup taken.
- [ ] Toolset Types active; `distributor` post type and all eight fields present (§3).
- [ ] Gravity Forms + Webhooks Add-On active; reCAPTCHA configured on the quote form.
- [ ] Plugin activated; the three `tdl_*` tables exist.
- [ ] `TDL_HUBSPOT_TOKEN` defined in `wp-config.php`; token rotated from any value shared during development.
- [ ] Google Maps API key: production key, referrer-restricted, billing enabled, budget alert set (§8).
- [ ] Google Map ID set.
- [ ] Map Provider, zoom, and centre confirmed with the client.
- [ ] **CC Email replaced or cleared** — currently the placeholder `routing_example@taylor.com`.
- [ ] Show City / Region tab set to the client's preference — currently **off**.
- [ ] Quote Form ID matches the live form.
- [ ] Webhook feed present on the quote form, pointing at the HubSpot endpoint, no token in its headers.
- [ ] SMTP configured; SPF/DKIM/DMARC in place (§11.4).
- [ ] Geocoding coverage reviewed and the shortfall accepted or addressed (§9.2).
- [ ] Locator page published with `[tdl_locator]` and linked in navigation.
- [ ] Distributor count reconciled against the client's source of truth (164 on the development database).

**After launch**

- [ ] Locator loads over HTTPS with no console errors; map renders; pins cluster.
- [ ] ZIP, state, country, and city searches each return sensible results.
- [ ] A known cross-border distributor appears for a foreign postcode.
- [ ] Request Quote modal opens, submits by AJAX, and auto-closes on confirmation.
- [ ] Test lead arrives at the correct distributor address; routing log shows Info.
- [ ] Test lead appears in HubSpot with `lead_source = Distributor Locator`.
- [ ] Invalid-token test proves email still delivers (§12.5); valid token restored.
- [ ] Test HubSpot contacts deleted; addresses used recorded.
- [ ] Mobile: map/list toggle, pin tap, and modal all behave on iOS Safari and Android Chrome.
- [ ] Routing log reviewed after the first day of real traffic.

---

## 16. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| Distributors menu missing; locator empty | Toolset Types deactivated | Reactivate Types (§3) |
| Map area blank or grey | Missing/invalid Google API key, billing off, or referrer restriction blocking the domain | §8.1–8.2 |
| Map loads but no pins | Map ID missing, or locations not geocoded | §8.3, §9 |
| Search returns nothing for a valid area | Distributor has no service zones, or stale cache | Add zones; Clear Cache Now |
| Edits don't show on the frontend | Cached results after a direct DB change | Clear Cache Now (§7.5) |
| Geocoding never progresses | WP-Cron disabled, Geocoding API not enabled, or no outbound HTTPS | §9.3 |
| Distributor never receives leads | `email_sales` empty, or SMTP not configured | Check Routing Log; §11.4 |
| Duplicate lead emails | A fixed-address GF notification was re-enabled | Use CC Email instead (§11.2) |
| Nothing reaches HubSpot | Webhook feed missing, HubSpot not enabled, or token wrong | §12.2–12.3; check Routing Log |
| HubSpot contact missing the distributor | Distributor Property pointed at a dropdown property | Must be free-text `distributor_name` (§12.1) |
| Quote modal doesn't open | Gravity Forms inactive, or Quote Form ID mismatch | §11.2 |

**Where to look first:** Distributors → Routing Log for anything lead-related,
Distributors → Geocoding Status for anything map-related, then the PHP error log
(entries are prefixed `TDL` or `[TDL][M9]`).

To capture PHP errors, temporarily set in `wp-config.php`:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Errors land in `wp-content/debug.log`. **Turn this off again afterwards.**

---

## 17. Known limitations

- Roughly half the locations lack coordinates and will not show as map pins
  until the underlying addresses are corrected (§9.2).
- Deleting the plugin drops the location and service-zone tables (§5.1). The
  uninstall routine also targets an outdated post type name, so distributor
  posts would survive while their location data is destroyed — an inconsistent
  state. Treat plugin deletion as unsupported; deactivate instead.
- Map libraries load from the unpkg.com CDN (§8.4).
- Server-side geocoding cannot use a referrer-restricted key (§8.2).
- Translated distributor posts are not fully supported (§13).
- On mobile, a pin tapped very near the top edge of the map may have its popup
  tip slightly clipped; dragging the map resolves it.
- All distributor email addresses are publicly exposed by the search API (§10.3).

---

## 18. Information still needed from the client

| Item | Needed for |
|---|---|
| Production Google Maps API key + Map ID (or approval to reuse the current ones) | §8 |
| Confirmed CC address for quote emails, or confirmation that none is wanted | §7.2 |
| Rotated HubSpot private app token for production | §5.2 |
| Which page the locator belongs on | §10.1 |
| Whether the City / Region search tab should be visible | §7.1 |
| Decision on the 302 un-geocodable addresses | §9.2 |
| Sending domain and SMTP credentials | §11.4 |
| Whether HubSpot lead source should use a brand-scoped property (`taylor__lead_source`) for consistency with sibling Middleby brands | §12.1 |
| Whether the locator should also populate `taylor__form_type` | §12.1 |

---

## 19. Support

Post-launch support period: **60 days from launch date** `<launch-date>`.

When reporting an issue, include: the page URL, what was searched or submitted,
the expected versus actual result, any Routing Log entry (with timestamp), and a
screenshot. For lead-delivery issues, the Gravity Forms entry ID makes tracing
much faster.

Developer: Well Dressed Walrus — `<support-contact>`
