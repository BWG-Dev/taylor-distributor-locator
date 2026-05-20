# Phase Plan — Taylor Distributor Locator

This file tracks module status. CLAUDE.md remains the source of truth for detailed module rules.

## M1 — Plugin Foundation / Distributor CPT / Fields

Status: Needs verification

Focus:
- Plugin activation
- Distributor CPT/admin structure
- Editable distributor fields
- Contact fields
- Physical location fields
- Service territory fields
- Internal notes
- Parent/child distributor relationship
- Admin columns
- Exact `email_sales` field key

Do not include:
- Frontend search
- Gravity Forms
- WPML
- HubSpot
- Mobile enhancements
- Final QA

Testing checklist:
- Plugin activates.
- Distributor CPT appears.
- Distributor fields save.
- `email_sales` exists exactly.
- Parent/child relationship works if implemented.
- Admin columns work if included.

Suggested commit:
`Implement M1 distributor foundation fields and admin columns`

---

## M2 — One-Time CSV Import

Status: Blocked until M1 verified/approved

Focus:
- Developer-run import
- Mapping approved CSV data
- Parent/child creation
- Missing lat/lng geocoding
- Import error log
- Verification of 164 records

Do not include:
- Self-service CSV uploader. That belongs to M4.

Suggested commit:
`Add one-time distributor CSV import workflow`

---

## M3 — Frontend Locator / Search / Maps

Status: Blocked until M1/M2 foundation verified

Focus:
- Split-view frontend UI
- AJAX search
- Google Maps/Toolset Maps integration decision
- Service-territory ZIP matching
- State/province search
- Country search
- City/region search
- Cross-border distributor logic
- Map pins at physical locations
- Marker clustering
- Info window with Request Quote stub

Suggested commit:
`Add distributor locator frontend search and map`

---

## M4 — Admin CSV Upload Tool

Status: Future

Focus:
- Admin CSV upload UI
- Validation
- Duplicate detection
- Update existing vs create new
- Row-level error reporting
- Rollback safety
- Tutorial link placeholder/admin UI location

Suggested commit:
`Add admin CSV upload tool for distributors`

---

## M5 — WPML Support

Status: Hard-blocked

Blocked until:
- WPML is confirmed purchased.
- WPML is active.
- WPML is compatible.

Suggested commit:
`Add WPML support for distributor locator`

---

## M6 — Mobile Enhancements

Status: **Complete** (2026-05-18) — pending device QA sign-off on iOS Safari and Android Chrome

Branch: `feature/m4-csv-service-tool`

### What was delivered

- **Popup fix:** Leaflet pin bubble no longer disappears on tap. Three-layer fix: `overflow: visible` on map container, `leafletMarkerTapped` flag blocking synthetic click, `autoPan: false` on mobile.
- **Map/List toggle:** Button bar at ≤960px with swipe gesture support (left = list, right = map). `aria-pressed` state. `scrollIntoView` on return to map.
- **Pan-to-bottom:** Tapping a pin pans the map so the pin sits at 88% from top, giving the popup full visible space above it.
- **Touch targets:** All interactive elements ≥44px on mobile. Marker tap zone expanded to 44×44px via `::before` pseudo-element.
- **Info window condensed:** Phone + website inline on one row. Tighter section padding. `max-height: 460px` with scroll.
- **Map heights:** 520px at ≤960px, 420px at ≤600px.
- **Performance:** Google Maps API already uses `loading=async`. Leaflet tiles lazy by default. Plugin JS in footer (non-render-blocking).

### Files changed

- `assets/js/tdl-locator.js`
- `assets/css/tdl-locator.css`
- `templates/locator-main.php`
- `includes/class-tdl-shortcode.php`

### Device QA required before merge

- [ ] iOS Safari — tap pin, popup stays open, pan-to-bottom works
- [ ] Android Chrome — same
- [ ] Swipe left/right switches panels correctly
- [ ] Desktop layout unaffected

### Known edge case

Pin very near the top map edge on mobile may have popup tip slightly clipped. User can drag map. Not a blocking issue.

Suggested commit:
`feat(m6): mobile enhancements — pin popup fix, map/list toggle, swipe, pan-to-bottom, 44px tap targets`

---

## M7 — Gravity Forms Quote Modal

Status: **Complete** (2026-05-19) — pending QA sign-off

Branch: `feature/m7-gravity-form-and-modal`

### What was delivered

- **GF Form**: Form 1 ("Request Quote") with fields: Name (advanced), Email, Phone, Company, Message/Needs. All required except phone.
- **Hidden distributor ID field**: Field 7 added programmatically via `GFAPI::update_form()`. Stored in option `tdl_gf_distributor_field_id`. Populated via JS at modal open time.
- **Server-side validation**: `gform_validation` hook rejects submissions with missing or invalid distributor IDs before GF saves the entry.
- **Modal**: Fixed overlay, backdrop blur, slide-up animation, X button + backdrop click + Escape key close, Tab focus trap, aria attributes, `role="dialog" aria-modal="true"`.
- **AJAX submission**: `gravity_form()` called with `$ajax=true` — form submits without page reload. GF shows confirmation inside the modal; MutationObserver auto-closes the modal 2.5 s after confirmation appears.
- **Mobile**: Bottom-sheet modal layout at ≤600px.
- **GF styles**: CSS overrides for GF fields inside the modal — uses plugin CSS vars (`--tdl-btn-bg`, `--tdl-primary`, etc.) for consistent branding.

### Files changed

- `includes/class-tdl-gf-integration.php` (new)
- `taylor-distributor-locator.php`
- `includes/class-tdl-shortcode.php`
- `templates/locator-main.php`
- `assets/js/tdl-locator.js`
- `assets/css/tdl-locator.css`

### Manual step required before merge

Configure reCAPTCHA v3 in GF Admin → Settings → reCAPTCHA (site key + secret), then enable on Form 1 under Form Settings → Personal Data.

### QA required before merge

- [ ] Modal opens on "Request Quote" click (cards + map info window)
- [ ] `#input_1_7` contains the correct distributor ID (devtools check)
- [ ] GF form renders with all 5 visible fields
- [ ] Form submits via AJAX (no page reload)
- [ ] GF entry saved with correct distributor ID in field 7
- [ ] Confirmation shows, modal auto-closes after ~2.5 s
- [ ] X / backdrop / Escape all close the modal
- [ ] Tab focus trapped inside modal
- [ ] Mobile: modal appears as bottom sheet
- [ ] No PHP errors in debug log
- [ ] All M1–M6 features unaffected

Suggested commit:
`feat(m7): Gravity Forms quote modal — hidden distributor ID field, modal open/close, focus trap, AJAX submission`

---

## M8 — Dynamic Email Routing

Status: **Complete** (2026-05-20) — pending QA sign-off

Branch: `feature/m8-email-routing`

### What was delivered

- **`class-tdl-email-router.php`** (new): Hooks `gform_after_submission_{form_id}`. Resolves routing email via `wpcf-email_sales` → `wpcf-email_main` fallback → error log. Sends HTML email via `wp_mail()`. Suppresses GF's static-address admin notification (`gform_disable_notification_{form_id}`) to prevent double-sending; customer confirmation emails unaffected. CC support via WP option `tdl_cc_email` (empty by default).
- **`class-tdl-routing-log.php`** (new): DB-backed log table (`{prefix}_tdl_routing_log`). `log()`, `get_entries()`, `get_total()`. All DB errors caught and `error_log()`-ed; logging never breaks form submission.
- **`class-tdl-admin-log.php`** (new): Admin submenu "Routing Log" under Distributors. Paginated table of routing events.
- **`templates/email/quote-request.php`** (new): Table-based HTML email, inline styles, email-client compatible. Sections: customer info, message, reply CTA, submission details, footer.
- **`templates/admin/email-routing-log.php`** (new): WP admin table with severity colour-coding, distributor links, GF entry links.
- **`class-tdl-activator.php`** (modified): Adds `tdl_routing_log` table via `dbDelta()` on fresh activation.
- **`class-tdl-admin-settings.php`** (modified): New "Email Routing" settings section — CC Email field (optional, pending client confirmation) + Quote Form ID field.
- **`taylor-distributor-locator.php`** (modified): require + init for new classes; version bumped to 0.5.0.

### DB changes

New table `{prefix}_tdl_routing_log`. Created via `dbDelta()` in activator (new installs) and via `TDL_Routing_Log::maybe_create_table()` on first init after update (existing installs). Version tracked in option `tdl_routing_log_db_version`.

### Client dependency

CC email address — not yet confirmed by client. Set WP option `tdl_cc_email` once confirmed. Field is in Settings page under "Email Routing".

### QA required before merge

- [ ] Submit a quote for a distributor with `wpcf-email_sales` populated → email arrives at email_sales, routing log shows INFO
- [ ] Submit a quote for a distributor without email_sales but with `wpcf-email_main` → email arrives at email_main, routing log shows WARNING
- [ ] Test with a distributor with neither email → no email sent, routing log shows ERROR
- [ ] Verify HTML email renders correctly in Gmail, Outlook, Apple Mail
- [ ] Confirm GF admin notification (if any) is suppressed — no double-send
- [ ] Confirm customer confirmation email (if configured) still sends
- [ ] Verify routing log page loads under Distributors → Routing Log
- [ ] Verify severity colour-coding and GF entry links in log page
- [ ] Verify pagination at 25+ entries
- [ ] Set `tdl_cc_email` option to a test address, confirm CC arrives
- [ ] Confirm all M1–M7 features unaffected
- [ ] No PHP fatal errors or warnings in debug log

Suggested commit:
`feat(m8): dynamic email routing with HTML template, fallback logic, and admin routing log`

---

## M9 — HubSpot Webhook

Status: Blocked until M7 and M8 complete

Focus:
- Confirmed HubSpot properties only
- Non-blocking webhook
- Logging
- End-to-end test flow

Suggested commit:
`Add non-blocking HubSpot webhook integration`

---

## M10 — QA / Documentation / Handoff

Status: Blocked until M1–M9 complete

Focus:
- QA
- Documentation
- Video tutorial
- Handoff support

Suggested commit:
`Add QA documentation and handoff materials`