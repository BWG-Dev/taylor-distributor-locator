# Dev Log — Taylor Distributor Locator

## 2026-08-04 — M9 HubSpot integration implemented (pending QA)

### Discovery (live portal inspection)

Read-only API calls against portal **7290009** settled every open gap. See DECISIONS.md for the property table and rationale.

- `accountType: STANDARD` — production, no sandbox
- Lead Source **does exist** (`lead_source`, text) — the admin UI search had missed it among 2,298 properties
- `taylor_distributor` (35-option dropdown) rejected in favour of `distributor_name` (text): WP holds 142 distinct companies, mostly international
- Write scope confirmed via a 400-not-403 probe that created no record
- WP post titles carry HTML entities (`ABS &#038; Taylor…`) — must be decoded before sending

### Implementation

#### `includes/class-tdl-hubspot.php` (new)

Gravity Forms Webhooks Add-On is the transport, per client requirement. The add-on exposes only two filters — `gform_webhooks_request_url` and `gform_webhooks_request_args` (verified against the installed source; there is no `request_data` filter). This class supplies what the add-on cannot do natively:

- **Payload reshaping.** HubSpot CRM v3 needs a nested `{"properties": {...}}` body; the add-on emits a flat map. Rebuilt in `filter_request_args()`.
- **Credential injection.** The token is attached as an `Authorization` header at request time, so it never has to be typed into or stored in the GF feed UI.
- **Server-side distributor resolution.** The company name is looked up from the distributor post ID (validated as a published `distributor` post), not read from the posted hidden field. Location suffix stripped, HTML entities decoded.
- **Scoping.** Filters no-op unless the feed's Request URL resolves to a `hubapi.com` host, so other webhook feeds on any form are untouched.
- **Abort path.** When disabled, tokenless, or the submission has no email, `filter_request_url()` returns `''`, which makes the add-on log a feed error and stop before any HTTP call.
- **Logging.** The add-on has no post-request hook, so `http_api_debug` observes the response; entries go to the existing M8 routing log table prefixed `[HubSpot]`, using `routing_source: none` so M8's schema and allowed values are unchanged.
- **Connection test.** `test_connection()` calls `account-info/v3/details` — read-only, reports portal ID and warns when the portal is production.

Every entry point is wrapped in `try/catch (\Throwable)`; a failure returns the original args rather than propagating.

#### `includes/class-tdl-admin-settings.php` (modified)

New "HubSpot Integration" section. All integration config is admin-editable — nothing hardcoded: enable toggle, access token, API endpoint, the four property names, the lead-source value, and a Test Connection button (AJAX, nonce + `manage_options`).

Token handling: `TDL_HUBSPOT_TOKEN` in `wp-config.php` wins over the stored option and renders the field disabled. The stored token is never echoed back — only a masked hint (`pat-na1-••••••••e1ae`) — and submitting the field empty preserves the existing value rather than wiping it.

#### `includes/class-tdl-email-router.php` (modified — one line)

`gform_after_submission_{id}` priority changed from 10 to **5**. GF feed add-ons process at priority 10 and `gravityformswebhooks` loads before this plugin, so at equal priority the webhook would run first and a hung HubSpot could consume the request's remaining `max_execution_time` before the distributor email was dispatched. Sending the email first is what makes "HubSpot failure must not block M8" true in the hang case, not just the error case.

#### `taylor-distributor-locator.php` (modified)

`require_once` + `TDL_HubSpot::init()`.

### Files changed

| File | Changes |
|---|---|
| `includes/class-tdl-hubspot.php` | **New** — payload builder, auth injection, abort path, logging, connection test |
| `includes/class-tdl-admin-settings.php` | HubSpot section, 8 fields, masked token handling, AJAX test |
| `includes/class-tdl-email-router.php` | Hook priority 10 → 5 (M9 independence) |
| `taylor-distributor-locator.php` | require + init |

### DB impact

None. No schema change, no new table, no migration. Reuses the M8 routing log.

### Manual step still required

A Webhook feed must be created on Form 1 — see TODO.md. Without it nothing fires.

---

## 2026-08-04 — M9 unblocked (memory files updated, no code changes)

### Summary

No code was written. Project memory files were updated to reflect that **M9 — HubSpot Webhook is unblocked**, since M7 and M8 are complete.

### M9 spec recorded

Configure the **Gravity Forms Webhook Add-On** to POST distributor lead submissions from the quote form to Taylor Company's HubSpot account, capturing lead source and distributor data for the lead-gen funnel.

Confirmed HubSpot contact properties (do not guess or create others):

| HubSpot label | Internal name |
|---|---|
| Company Name | `company` |
| [Taylor] Distributor Name | `distributor_name` |
| [Taylor] Message | `taylor_message` |

Expected outcome:
- GF Webhook Add-On configured and POSTing to HubSpot
- All form fields mapped to confirmed HubSpot contact properties
- Lead Source = `Distributor Locator` set correctly
- Distributor ID/name captured and sent
- HubSpot failure does **not** block M8 email routing (fully independent paths)
- End-to-end test submission documented

### Open before build

- Client must provide **HubSpot sandbox** access for end-to-end testing
- Lead Source property **internal name** not provided (only the value)
- Endpoint approach undecided: CRM v3 API (Bearer token) vs. HubSpot form-submit endpoint
- GF Webhook Add-On install/license status unverified
- No confirmed distributor **ID** property in HubSpot — only `distributor_name`

### Branch policy change

Only `master` and `staging` are kept. The five existing feature/fix branches will be deleted once their work is confirmed merged. M9 starts on a new branch.

### Files changed

| File | Changes |
|---|---|
| `PROJECT_NOTES.md` | Current module → M9 (unblocked); branch policy; M9 blockers |
| `PHASE_PLAN.md` | M9 section rewritten: unblocked, full scope, confirmed properties, gaps, rules |
| `DECISIONS.md` | New decisions: M9 unblocked via GF Webhook Add-On; branch cleanup |
| `TODO.md` | New M9 pre-build + build checklists; status table; Git branch cleanup tasks |
| `DEV_LOG.md` | This entry |

### Note

`includes/class-tdl-csv-importer.php` has an uncommitted stray-token syntax error near line 910 (`claude}` instead of `}`). Flagged to the developer; not modified.

---

## 2026-05-20 — M5 WPML Support

### Completed

M5 is functionally complete on branch `feature/m5-translation-support`. WPML 4.9.3 (core) confirmed installed.

#### `wpml-config.xml` (new — plugin root)

Declarative WPML config. Registers `distributor` CPT as translatable (`translate="1"`). Defines copy/translate actions for all custom fields:
- Contact/email fields (`wpcf-phone`, `wpcf-website`, `wpcf-email_main`, `wpcf-email_sales`, `wpcf-email_parts`, `wpcf-email_service`, `wpcf-email_installations`) → `copy` (language-independent data)
- `_tdl_parent_id`, `_tdl_has_geocode_errors` → `copy` (structural/internal)
- `wpcf-service_area_description` → `translate` (user-facing text)

Registers `tdl_locator` shortcode so WPML's page/string scanner recognises it and its attributes.

#### `includes/class-tdl-wpml.php` (new)

Guards all hooks behind `ICL_SITEPRESS_VERSION`. Two responsibilities:
- `bust_search_cache()`: bumps `tdl_data_version` on `wpml_language_has_switched`, busting all REST API transient cache keys
- `register_strings()`: fires `wpml_register_single_string` for every frontend UI string (safe no-op if WPML String Translation module not installed)

#### `taylor-distributor-locator.php` (modified)

`require_once` for new class. `TDL_WPML::init()` added as first call in `tdl_init()`.

### Client Note

WPML String Translation (separate add-on) not installed. `register_strings()` will be a no-op until ST is added. All strings already use `__()` and are translatable via standard `.po`/`.mo` files.

### Files Changed

| File | Changes |
|---|---|
| `wpml-config.xml` | **New file** — CPT + field copy/translate rules, shortcode registration |
| `includes/class-tdl-wpml.php` | **New file** — cache bust on language switch, WPML ST string registration |
| `taylor-distributor-locator.php` | require + init TDL_WPML |

### QA Steps

See PHASE_PLAN.md M5 QA checklist.

---

## 2026-05-20 — M8 Dynamic Email Routing

### Completed

M8 is functionally complete on branch `feature/m8-email-routing`.

#### Email Router (`includes/class-tdl-email-router.php`)

New class. Hooks `gform_after_submission_{form_id}` to run after GF saves the entry. Reads distributor ID from the hidden GF field (option `tdl_gf_distributor_field_id`). Resolves routing email: `wpcf-email_sales` → `wpcf-email_main` → error. Sends HTML email via `wp_mail()` with inline template. Logs every routing decision via `TDL_Routing_Log`.

Also hooks `gform_disable_notification_{form_id}` to suppress GF's static-address (`toType=email`) notifications, preventing double-send. Customer confirmation notifications (`toType=field`) are not suppressed.

CC support via WP option `tdl_cc_email` (empty by default, configurable in Settings page).

#### Routing Log (`includes/class-tdl-routing-log.php`)

New class. Manages `{prefix}_tdl_routing_log` DB table. `log()`, `get_entries()`, `get_total()`. Version-guarded via `tdl_routing_log_db_version` option so `dbDelta()` only runs once per activation/update. All DB errors caught silently — logging never throws or breaks form submission.

#### Admin Log (`includes/class-tdl-admin-log.php`)

New class. Adds "Routing Log" submenu under Distributors. Renders paginated log table (25 per page).

#### Email Template (`templates/email/quote-request.php`)

New HTML email. Table-based, inline styles, MSO conditional comments for Outlook. Sections: header, intro, customer info table, message block with left-border, reply CTA button, submission details, footer.

#### Admin Log Template (`templates/admin/email-routing-log.php`)

New admin template. WP `widefat striped` table. Colour-coded severity pills. Distributor edit links. GF entry links. Pagination.

#### Settings page (`includes/class-tdl-admin-settings.php`)

Added "Email Routing" section with: CC Email field (optional, pending client confirmation) and Quote Form ID field.

#### Activator (`includes/class-tdl-activator.php`)

Added `tdl_routing_log` table to `dbDelta()` batch. Sets `tdl_routing_log_db_version` on activation so `maybe_create_table()` skips on subsequent page loads.

#### Bootstrap (`taylor-distributor-locator.php`)

Added `require_once` for three new classes. Added `TDL_Routing_Log::maybe_create_table()`, `TDL_Email_Router::init()`, `TDL_Admin_Log::init()` to `tdl_init()`. Version bumped to 0.5.0.

### Files Changed

| File | Changes |
|---|---|
| `includes/class-tdl-email-router.php` | **New file** — routing logic, GF hook, wp_mail(), notification suppression |
| `includes/class-tdl-routing-log.php` | **New file** — DB log class |
| `includes/class-tdl-admin-log.php` | **New file** — admin log page |
| `templates/email/quote-request.php` | **New file** — HTML email template |
| `templates/admin/email-routing-log.php` | **New file** — admin log table template |
| `includes/class-tdl-activator.php` | Add routing_log table to dbDelta() + version option |
| `includes/class-tdl-admin-settings.php` | Add Email Routing section + CC Email + Quote Form ID fields |
| `taylor-distributor-locator.php` | require + init 3 new classes; version 0.5.0 |

### Client Dependency

CC email address pending client confirmation. Once confirmed, add to Settings → Email Routing → CC Email. Not hardcoded.

### QA Steps

See PHASE_PLAN.md M8 QA checklist.

---

## 2026-05-19 — M7 Gravity Forms Quote Modal

### Completed

M7 is functionally complete on branch `feature/m7-gravity-form-and-modal`.

#### GF Integration class (`includes/class-tdl-gf-integration.php`)

New class added. Responsibilities:
- `ensure_distributor_field()`: Adds a hidden "Distributor ID" field (adminLabel: `distributor_id`) to Form 1 on first page load via `GFAPI::update_form()`. Idempotent — skips if `tdl_gf_distributor_field_id` option is already set. Field becomes field ID 7 (nextFieldId was 7 in the form JSON).
- `validate_distributor_id()`: Hooks `gform_validation`. Rejects the submission (sets `is_valid = false`) if the posted distributor ID is zero, missing, or doesn't map to a published `distributor` post. Logs a warning. M8 downstream routing relies on this field being valid.

#### Shortcode (`includes/class-tdl-shortcode.php`)

- Added `gfFormId` and `gfDistributorFieldId` to the `$config` JS object.
- Added `gravity_form($id, false, false, false, null, true, 0, false)` call before `ob_start()` to render the GF form with AJAX mode and return HTML for embedding. GF also enqueues its scripts here.
- Removed unused `quoteStub` i18n string (stub was removed in M7).

#### Template (`templates/locator-main.php`)

Added modal markup after `.tdl-content`, inside `.tdl-locator`. Conditionally rendered only when GF form HTML is available. Structure: `.tdl-modal` (fixed overlay, role=dialog, aria-modal, hidden) → `.tdl-modal-backdrop` → `.tdl-modal-dialog` → `.tdl-modal-header` (title + close button) → `.tdl-modal-body` (GF form output).

#### JS (`assets/js/tdl-locator.js`)

- Replaced `showQuoteStub()` call in click handler with `openQuoteModal(distributorId)`. Removed `showQuoteStub` function entirely.
- Added `initQuoteModal()`: wires backdrop click, X button click, Escape key, Tab focus trap, and a MutationObserver that auto-closes the modal 2.5 s after GF inserts `#gform_confirmation_wrapper_1` (its AJAX success state).
- Added `openQuoteModal(distributorId)`: removes `hidden` attr, adds `tdl-modal-open` class to body, sets `#input_1_7` value, focuses close button.
- Added `closeQuoteModal()`: restores `hidden` attr, removes body class.
- Exposed `window.tdlCloseQuoteModal` as a global for any external trigger.

#### CSS (`assets/css/tdl-locator.css`)

Added modal styles: fade + slide-up animations, backdrop blur, dialog shadow, close button hover, GF form field overrides (borders, focus rings, submit button using `--tdl-btn-bg`/`--tdl-btn-text` CSS vars), mobile bottom-sheet layout at ≤600px.

### Files Changed

| File | Changes |
|---|---|
| `includes/class-tdl-gf-integration.php` | **New file** — GF integration class |
| `taylor-distributor-locator.php` | require + init TDL_GF_Integration |
| `includes/class-tdl-shortcode.php` | Add GF config to JS, render form, remove quoteStub i18n |
| `templates/locator-main.php` | Add quote modal markup |
| `assets/js/tdl-locator.js` | Replace stub with modal open/close/focus-trap |
| `assets/css/tdl-locator.css` | Modal styles |

### Manual Steps Required

reCAPTCHA v3 must be configured manually:
1. GF Admin → Settings → reCAPTCHA — enter v3 Site Key and Secret Key
2. Form 1 → Settings → Personal Data — enable reCAPTCHA v3

### Known Notes

- After a successful GF AJAX submission the modal auto-closes after 2.5 s. If the user re-opens the modal before the page is reloaded, the GF confirmation message may still be shown (form submission replaced the form element). This is acceptable for a quote form; repeat same-session submissions without reload are not expected.
- The MutationObserver on `.tdl-modal-body` fires once, then disconnects.

---

## 2026-05-18 — M6 Mobile Enhancements

### Completed

M6 is functionally complete on branch `feature/m4-csv-service-tool`.

#### Popup fix — Leaflet pin bubble disappearing on tap

Root cause: Three compounding issues were identified and resolved.

1. **`overflow: hidden` on `.tdl-map-container`** clipped the Leaflet popup when it was taller than the map container. Changed to `overflow: visible` at both mobile breakpoints.
2. **Browser synthetic click** (~300–500ms after `touchend`) bubbled to the map background handler, triggering our `map.on('click', map.closePopup)` call immediately after the popup opened. Fixed with a `flagLeafletMarkerTap()` / `leafletMarkerTapped` flag that blocks the map click handler for 600ms after a marker tap.
3. **Leaflet autopan** was animating the map to try to fit the popup in view on a short map, causing the popup to appear to jump. Disabled with `autoPan: false` on mobile (`window.innerWidth <= 960`).

#### Map/List toggle

Added a "Map / List" toggle bar visible only on mobile (≤960px). Includes:
- Button click handlers
- `aria-pressed` state management
- Swipe gesture (horizontal dx > 50px, with scroll protection via dy comparison)
- `map.invalidateSize()` / Google Maps resize event on map reveal
- `scrollIntoView` when switching from list back to map (prevents map being rendered off-screen after list scroll)

#### Pan-to-bottom on marker tap

On mobile, tapping a pin now calls `panMarkerToBottom()` which pans the map so the marker lands centered horizontally and at 88% of the map height from the top. This gives the popup maximum visible space above it without manual dragging.

#### Touch targets

Applied `min-height: 44px` to `.tdl-request-quote`, `.tdl-page-btn`, `.tdl-accordion-toggle`, `.tdl-search-input`, and `.tdl-search-btn` at mobile breakpoints. Leaflet marker tap area expanded via `.tdl-marker-pin::before` pseudo-element (44×44px transparent hit zone).

#### Info window — condensed layout

Phone and website moved to a single inline row (`tdl-iw-contact-row`) separated by a `·` character. Section padding and font sizes reduced on mobile. `max-height: 460px` with `overflow-y: auto` on the popup wrapper.

#### Map heights (final values)

| Breakpoint | Height |
|---|---|
| ≤960px | 520px |
| ≤600px | 420px |

### Files Changed

| File | Changes |
|---|---|
| `assets/js/tdl-locator.js` | Popup fix (flag + stopPropagation + closePopupOnClick:false), mobile toggle + swipe, panMarkerToBottom, autoPan:false, condensed info window |
| `assets/css/tdl-locator.css` | overflow:visible, mobile map heights, toggle bar styles, 44px touch targets, marker hit zone, popup max-height, condensed popup spacing |
| `templates/locator-main.php` | Mobile toggle markup (conditional on split view) |
| `includes/class-tdl-shortcode.php` | Comment clarifying footer enqueue + async API loading |

### Testing Performed

Verified via code review and logic tracing. Device testing required on iOS Safari and Android Chrome before final sign-off (see TODO.md).

### Known Issues

- `autoPan: false` on mobile means if a pin is tapped near the top edge of the map, the popup tip may be clipped by the map top boundary. The `panMarkerToBottom` call handles most cases but an extreme-top-edge pin could still clip. Workaround: user drags map slightly.
- Desktop retains `overflow: hidden` on the map container for rounded tile corners. If a desktop user clicks a pin very near the top of the map and the popup is tall, it may be partially clipped. Leaflet's autopan (enabled on desktop) mitigates this in most cases.
- `window.innerWidth` is evaluated at marker creation time (not on resize). If the browser is resized between mobile and desktop widths after page load, `autoPan` and `panMarkerToBottom` may behave incorrectly. Not a real-world concern for production use.

### Suggested Commit Message

```
feat(m6): mobile enhancements — pin popup fix, map/list toggle, swipe, pan-to-bottom, 44px tap targets
```

---

## 2026-05-18 — Project Memory Setup

### Project Memory Setup

Added project-memory structure to support multi-day Claude Code work without replacing the current CLAUDE.md instructions.

Recommended files:
- PROJECT_NOTES.md
- PHASE_PLAN.md
- DECISIONS.md
- DEV_LOG.md
- TODO.md

### Current Notes

The existing CLAUDE.md is strong and should not be replaced.

It should only be extended with a small “Project Memory Files” section so Claude knows to read and update the companion files.

### Next Recommended Step

1. Add the small Project Memory Files section to the bottom of CLAUDE.md.
2. Create PROJECT_NOTES.md.
3. Create PHASE_PLAN.md.
4. Create DECISIONS.md.
5. Create DEV_LOG.md.
6. Create TODO.md.
7. Ask Claude to inspect current git status and project files before coding.

### Suggested Next Prompt

Read CLAUDE.md, PROJECT_NOTES.md, PHASE_PLAN.md, DECISIONS.md, DEV_LOG.md, and TODO.md.

Then check git status and latest commits.

Do not make code changes yet.

Summarize the current module, branch, completed work, pending work, blockers, and recommended next safest step.