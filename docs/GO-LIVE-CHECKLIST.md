# Go-Live Checklist — Taylor Distributor Locator

**Plugin version:** 0.6.0 · **Checklist version:** 1.0 — 2026-08-17

One page for launch day. Full detail in `INSTALLATION-AND-CONFIGURATION.md`
(section references in brackets).

---

## A. Before touching production

- [ ] Full **database backup** taken and restore tested
- [ ] Full **file backup** of `wp-content` taken
- [ ] Rollback plan agreed with the hosting team
- [ ] Launch window agreed — avoid launching into a weekend

---

## B. Dependencies active

- [ ] **Toolset Types** active — Distributors menu visible [§3]
- [ ] Post type `distributor` present with all eight `wpcf-*` fields, including **`email_sales`** [§3.2]
- [ ] **Gravity Forms** active
- [ ] **Gravity Forms Webhooks Add-On** active
- [ ] **reCAPTCHA** configured and enabled on the quote form
- [ ] PHP 8.1+ and HTTPS confirmed on production [§2.1]

---

## C. Plugin installed

- [ ] Taylor Distributor Locator activated
- [ ] Tables exist: `tdl_locations`, `tdl_service_zones`, `tdl_routing_log` [§5.1]
- [ ] Distributor count reconciled with the client's source of truth (164 on the development database)
- [ ] Locations and service zones present and non-zero

---

## D. Credentials and keys

- [ ] `TDL_HUBSPOT_TOKEN` defined in `wp-config.php` [§5.2]
- [ ] HubSpot token **rotated** — any token shared during development is burned
- [ ] Google Maps API key is a **production** key
- [ ] Key restricted to the production domain, limited to Maps JavaScript / Geocoding / Places [§8.2]
- [ ] Google Cloud **billing enabled** and a **budget alert** set
- [ ] **Google Map ID** set — without it, no markers render [§8.3]

---

## E. Settings reviewed — Distributors → Settings

- [ ] Map Provider set (currently **Google Maps**)
- [ ] Default zoom and map centre confirmed with the client
- [ ] Results per page confirmed
- [ ] **Show City / Region tab** set to the client's preference — currently **off**
- [ ] **CC Email replaced or cleared** — currently the placeholder `routing_example@taylor.com` ⚠️
- [ ] **Quote Form ID** matches the live Gravity Forms form
- [ ] HubSpot property names left at defaults: `company`, `distributor_name`, `taylor_message`, `lead_source`
- [ ] Lead Source Value reads `Distributor Locator`
- [ ] **Test connection** returns `Connected to portal <id>` [§12.2]
- [ ] **Enable HubSpot** ticked only when the client is ready for live leads
- [ ] Colours left blank to inherit the theme, or set deliberately

---

## F. Gravity Forms

- [ ] Quote form exists and matches the configured form ID
- [ ] Hidden **Distributor ID** and **Distributor Name** fields present (auto-created — do not delete) [§11.2]
- [ ] Webhook feed exists: name `HubSpot Contact`, URL `https://api.hubapi.com/crm/v3/objects/contacts`, method `POST`, format `JSON`, **no field mapping, no token header** [§12.3]
- [ ] No fixed-address admin notification relied upon — it is suppressed by design; use CC Email instead [§11.2]

---

## G. Email deliverability

- [ ] **SMTP plugin configured** with an authenticated sender [§11.4]
- [ ] Sender address is on the site's own domain
- [ ] **SPF**, **DKIM**, and **DMARC** records in place
- [ ] Test email confirmed to land in the inbox, not spam

---

## H. Content and data

- [ ] Locator page published containing `[tdl_locator]` [§10.1]
- [ ] Page linked from site navigation
- [ ] Geocoding coverage reviewed — decision made on locations without coordinates [§9.2]
- [ ] **Clear Cache Now** clicked after any bulk import or direct database change
- [ ] Client aware that all five distributor email addresses are publicly visible [§10.3]

---

## I. Smoke test on production

- [ ] Locator page loads over HTTPS with no browser console errors
- [ ] Map renders, pins appear, clustering works
- [ ] **ZIP / postcode** search returns correct distributors
- [ ] **State / province** search works
- [ ] **Country** search works
- [ ] **City / region** search works, if that tab is enabled
- [ ] A known **cross-border** distributor appears for a foreign postcode
- [ ] Clicking a pin opens the info window with correct contact details
- [ ] Pagination works past the first page

---

## J. Lead flow end-to-end

Use a unique plus-addressed email per attempt (`you+tdl01@example.com`) — every
test creates a **real** HubSpot contact [§12.4].

- [ ] **Request Quote** modal opens from a card and from a map pin
- [ ] Form submits by AJAX with no page reload; confirmation shows; modal auto-closes
- [ ] Email arrives at the **correct distributor** address
- [ ] Routing Log shows **Info** (or **Warning** if that distributor has no `email_sales`)
- [ ] CC address receives a copy, if one is configured
- [ ] HubSpot contact created with email, name, phone, company, message, distributor, and `lead_source = Distributor Locator`
- [ ] `distributor_name` has **no HTML entities** and **no ` — City` suffix**
- [ ] Routing Log shows `[HubSpot] Contact synced (HTTP 201)`
- [ ] Every test email address recorded for cleanup

### Independence proof (the acceptance criterion) [§12.5]

- [ ] Invalid token → submit → **distributor email still arrives**; log shows `[HubSpot] HTTP 401`
- [ ] HubSpot disabled → submit → **email still arrives**; log shows the skip; no HTTP request
- [ ] Valid token and enabled state **restored**

---

## K. Mobile

- [ ] iOS Safari — pin tap keeps the popup open and pans it into view
- [ ] iOS Safari — map/list toggle and swipe both work
- [ ] Android Chrome — same
- [ ] Quote modal usable as a bottom sheet on a phone
- [ ] Desktop layout unaffected

---

## L. Cleanup and handoff

- [ ] All **test HubSpot contacts deleted**; none remain
- [ ] Test Gravity Forms entries removed if the client wants a clean entry list
- [ ] `WP_DEBUG` off
- [ ] Routing Log reviewed after the first day of real traffic
- [ ] Client shown: Settings, Routing Log, Geocoding Status, Import/Export CSV
- [ ] Client warned: **deactivate, never delete** — deleting the plugin drops the data tables [§5.1]
- [ ] Launch date recorded — starts the 60-day support period

---

## Rollback

If the locator misbehaves on launch day:

1. **Deactivate the plugin** — the page falls back to bare shortcode text; no data is lost.
2. To stop HubSpot alone, untick **Enable HubSpot**. Email routing continues.
3. To stop lead emails alone, deactivate the plugin — Gravity Forms then handles the form with its own notifications (re-enable one first if you need them).
4. Restore the database backup only if data has been corrupted. **Never delete the plugin as a troubleshooting step.**
