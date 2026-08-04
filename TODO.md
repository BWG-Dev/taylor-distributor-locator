# TODO — Taylor Distributor Locator

## Immediate — M6 Device QA (before merging to master)

- [ ] iOS Safari — tap a map pin, verify popup stays open and pans into view
- [ ] iOS Safari — swipe left on content area, verify list appears
- [ ] iOS Safari — swipe right on content area, verify map appears and scrolls into view
- [ ] Android Chrome — repeat all of the above
- [ ] Verify all interactive elements meet 44px tap target on both platforms
- [ ] Verify desktop layout is completely unaffected (both panels always visible, no toggle)
- [ ] Verify Google Maps provider (if used) — popup stays open on tap

## Current — M8 Dynamic Email Routing

- [ ] Submit a quote for a distributor with `wpcf-email_sales` populated → email routes to email_sales, log shows INFO
- [ ] Submit a quote for a distributor with only `wpcf-email_main` → email routes to email_main, log shows WARNING
- [ ] Test distributor with neither email → no email sent, log shows ERROR, no silent failure
- [ ] Verify HTML email renders in Gmail, Outlook, Apple Mail
- [ ] Confirm GF admin notification (if any) is suppressed — no double-send
- [ ] Confirm customer confirmation email (if configured) still sends
- [ ] Verify Distributors → Routing Log page loads and shows events
- [ ] Verify severity colour-coding (info=blue, warning=orange, error=red) and GF entry links
- [ ] Set `tdl_cc_email` to a test address in Settings → Email Routing, confirm CC arrives
- [ ] Confirm all M1–M7 features still work
- [ ] No PHP errors in debug log

## Pending — M7 QA (complete before merging to staging)

- [ ] **Manual step**: Configure reCAPTCHA v3 in GF Admin → Settings → reCAPTCHA (enter site key + secret, then enable v3 on Form 1 under Form Settings → Personal Data)
- [ ] Load the distributor locator page and confirm the modal appears on "Request Quote" click
- [ ] Confirm the GF form renders inside the modal with all fields
- [ ] Confirm the distributor ID hidden field is populated (check with browser devtools: `#input_1_7`)
- [ ] Submit the form and confirm AJAX submission works (no page reload)
- [ ] Confirm success message shows, then modal auto-closes after ~2.5 s
- [ ] Confirm modal closes on X button click
- [ ] Confirm modal closes on backdrop click
- [ ] Confirm modal closes on Escape key
- [ ] Confirm Tab key cycles through modal elements only (focus trap)
- [ ] Confirm mobile layout (modal slides up from bottom on ≤600px)
- [ ] Confirm desktop layout and all M1–M6 features still work
- [ ] Confirm no PHP errors in debug log after form submission
- [ ] Check GF entry saved correctly with distributor ID in field 7

## Current — M9 HubSpot Webhook (code complete, pending QA)

Pre-build confirmations — **all resolved 2026-08-04**:

- [x] GF Webhooks Add-On installed and active
- [x] Lead Source property confirmed: `lead_source` (text) — it existed; no creation needed
- [x] Endpoint decided: HubSpot CRM v3 `POST /crm/v3/objects/contacts`
- [x] Distributor ID property — does not exist; dropped from scope
- [x] Token write scope verified (400-not-403 probe, no record created)
- [x] Sandbox — none exists; production testing accepted with disposable emails (see DECISIONS.md)

### Step 1 — Configure WordPress (Distributors → Settings → HubSpot Integration)

- [ ] Paste the access token (or better: define `TDL_HUBSPOT_TOKEN` in `wp-config.php`)
- [ ] Click **Test connection** — expect `Connected to portal 7290009 (STANDARD)` plus the production warning
- [ ] Leave the four property names at their defaults (`company`, `distributor_name`, `taylor_message`, `lead_source`)
- [ ] Tick **Enable HubSpot** only when ready to send

### Step 2 — Create the Gravity Forms Webhook feed (required — nothing fires without it)

Forms → **Request Quote** (Form 1) → Settings → **Webhooks** → Add New:

- [ ] Name: `HubSpot Contact`
- [ ] Request URL: `https://api.hubapi.com/crm/v3/objects/contacts`
- [ ] Request Method: `POST`
- [ ] Request Format: `JSON`
- [ ] Leave field mapping empty and **do not add the token as a header** — the plugin replaces the body and injects `Authorization` at request time
- [ ] Save

### Step 3 — End-to-end test (production portal — disposable addresses only)

- [ ] Use a unique plus-addressed email per attempt (`you+tdl01@…`) — never one already in the portal, never a real customer
- [ ] Record every test address used, for cleanup
- [ ] Submit a quote → contact appears in HubSpot with `email`, `firstname`, `lastname`, `phone`, `company`, `taylor_message`, `distributor_name`, `lead_source = Distributor Locator`
- [ ] Confirm `distributor_name` has **no HTML entities** (`&` not `&#038;`) — test a distributor whose name contains an ampersand, e.g. *ABS & Taylor Enterprises, Inc.*
- [ ] Confirm the location suffix is stripped (company name only, no `— City`)
- [ ] Test an **international** distributor (e.g. Middleby China, Dayton OÜ) — proves the free-text property accepts names outside the 35-option dropdown
- [ ] Distributors → Routing Log shows an `[HubSpot] Contact synced (HTTP 201)` row
- [ ] Delete the test contacts from HubSpot afterwards

### Step 4 — Prove M8 independence (the acceptance criterion)

- [ ] Set a deliberately invalid token → submit → **distributor email still arrives**, log shows `[HubSpot] HTTP 401`
- [ ] Untick Enable HubSpot → submit → email still arrives, log shows the skip, no HTTP request made
- [ ] Restore the valid token

### Step 5 — Cleanup and handoff

- [ ] Rotate the access token (it was pasted into a chat transcript — treat as burned)
- [ ] Delete all test contacts; verify none remain
- [ ] Ask the client whether Taylor wants a brand-scoped `taylor__lead_source` for consistency with sibling brands
- [ ] Ask whether the locator should also populate `taylor__form_type`
- [ ] Create the M9 branch and commit

## Git

- [ ] Delete stale branches after confirming their work is merged — keep only `master` and `staging`:
      `feature/m5-translation-support`, `feature/m7-gravity-form-and-modal`, `feature/m8-email-routing`, `fix/m3-dropmenu-filters`, `fix/taylor-update-call` (local + `origin`)
- [ ] Verify M5/M7/M8 work is actually present in `staging` before deleting those branches

## Current — M5 WPML Support

- [ ] Navigate to WPML → Languages in WP admin — confirm `distributor` CPT appears under Translation Management
- [ ] Confirm `wpml-config.xml` is auto-detected (WPML usually picks it up on next admin load)
- [ ] Add a second active language in WPML settings, then open a distributor edit screen — verify WPML translation panel appears
- [ ] Confirm `wpcf-email_sales` and other contact fields are marked "Copy" (not "Translate") in translation panel
- [ ] Confirm `wpcf-service_area_description` is marked "Translate" in translation panel
- [ ] Switch active language on frontend, load distributor locator page — confirm UI labels (Search button, placeholder, toggle buttons) render in correct language if a `.po` file is present
- [ ] If WPML String Translation module is installed: confirm all registered strings appear under WPML → String Translation with context "Taylor Distributor Locator"
- [ ] Search for distributors in non-default language — confirm results still populate (no empty list)
- [ ] Switch language and confirm REST API transient cache is invalidated (first request after language switch is a cache miss)
- [ ] No PHP errors in debug log

## Module Status Summary

| Module | Status |
|---|---|
| M1 | Complete |
| M2 | Complete |
| M3 | Complete |
| M4 | Complete |
| M5 | Complete — pending QA |
| M6 | Complete — pending device QA |
| M7 | Complete — pending QA |
| M8 | Complete — pending QA |
| M9 | **Code complete** (2026-08-04) — pending GF feed setup + production QA |
| M10 | Blocked until M1–M9 |

## Ongoing

- [ ] Update PROJECT_NOTES.md after each completed task.
- [ ] Update DEV_LOG.md after each session.
- [ ] Update TODO.md when tasks change.
- [ ] Update PHASE_PLAN.md when module status changes.
- [ ] Update DECISIONS.md when important decisions are made.