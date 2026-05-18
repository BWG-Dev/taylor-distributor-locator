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

Status: Blocked until M3 complete

Focus:
- Mobile UX improvements
- Preserve desktop behavior

Suggested commit:
`Add mobile enhancements for distributor locator`

---

## M7 — Gravity Forms Quote Modal

Status: Blocked until M3 complete

Focus:
- Gravity Forms quote form
- Modal behavior
- Hidden distributor ID capture
- Server-side distributor validation

Suggested commit:
`Add Gravity Forms quote request modal`

---

## M8 — Dynamic Email Routing

Status: Blocked until M7 complete

Focus:
- Route Gravity Forms submissions to `email_sales`
- Fallback to `email_main`
- Warning logging
- Email delivery independent from HubSpot

Suggested commit:
`Add dynamic distributor email routing`

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