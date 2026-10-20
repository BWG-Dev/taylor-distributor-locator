# Project Notes — Taylor Distributor Locator

## Current Project

Taylor Distributor Locator is a production WordPress plugin for a real client.

The plugin is being implemented by modules M1 through M10 from the Monday.com requirements.

The current CLAUDE.md file is the primary engineering instruction file and must remain the source of truth for coding standards, security expectations, module boundaries, business rules, and workflow.

## Current Working Mode

Work one module at a time.

Before coding:
- Confirm the active module.
- Compare requirements against current code.
- Identify what already exists.
- Identify what is missing.
- Identify risks and blockers.
- Propose a safe implementation plan.
- Wait for approval.

After coding:
- Summarize changed files.
- Explain the reason for changes.
- Provide QA steps.
- Suggest a commit message.
- Update project memory files.

## Current Module

**M10 — QA / Documentation / Handoff** — started 2026-08-17. Project approved to wrap up.

M1–M9 are functionally complete. M9 is code complete and has been exercised end to end on the
development environment (routing log shows `[HubSpot] Contact synced (HTTP 201)` on 2026-08-04
and 2026-08-11, alongside M8 email routing). Several modules still carry pending QA checklists
in TODO.md.

A 60-day post-launch support period begins at launch. The launch date has not been set; the PM
will give advance notice.

### Handoff documentation (delivered 2026-08-17)

**For the client:**
- `docs/Distributor-Locator-Installation-and-Settings-Guide.pdf` — 22-page guide covering
  installation, every setting explained in plain language, and nine key considerations.
  Deliberately unbranded: no agency credit, no support-term reference, no client wordmark.
- `docs/installation-and-settings-guide.html` — print source; re-render with headless Chrome.

**For the developer / host:**
- `docs/INSTALLATION-AND-CONFIGURATION.md` — full technical install/configure/verify guide.
- `docs/GO-LIVE-CHECKLIST.md` — launch-day tick-box checklist with rollback steps.
- Shared web version: https://claude.ai/code/artifact/ab94c1bf-91b4-433c-9d13-e6d3863256f1

### Branch policy — CORRECTED 2026-08-17

The earlier decision to delete the five feature/fix branches **must not be executed as written.**
`master` is 34 commits behind `feature/m9-hubspot-webhook` and `staging` is 32 behind. All M3–M9
work exists only on feature branches — deleting them would destroy it.

Required before any branch cleanup: merge the feature branches forward into `staging`, then
`master`, in module order, and verify the work is present before deleting anything.

### Open launch blockers

- `tdl_cc_email` is set to the placeholder `routing_example@taylor.com` — must be replaced or cleared.
- 302 of 685 locations are recorded as geocode errors; only 337 (49%) have coordinates. Client
  decision needed on whether to correct source addresses before launch.
- Production Google Maps API key / Map ID not yet supplied.
- HubSpot token must be rotated for production (the development value was shared in a transcript).
- SMTP / SPF / DKIM / DMARC not yet configured — highest-risk item for lead delivery.
- Production locator page not yet confirmed (shortcode currently on page ID 10).

## Known Project Architecture

The repository may include:

- Main plugin bootstrap file: `taylor-distributor-locator.php`
- Distributor custom post type
- Admin meta boxes for distributor data
- Custom database tables for locations and service zones
- REST API endpoints for distributor search
- Frontend shortcode for distributor locator
- CSV import/export tools
- Geocoding functionality
- Admin geocoding/status tools
- Future Gravity Forms integration
- Future WPML integration
- Future HubSpot webhook integration

## Critical Business Rules

- `email_sales` must exist exactly with that field key.
- M8 lead routing depends on `email_sales`.
- If `email_sales` is empty, routing should fall back to `email_main` and log a warning.
- Physical location and service territory are separate concepts.
- ZIP search must match `zip_codes_served`, not physical ZIP/postal code.
- Cross-border distributors must appear based on service territory.
- Latitude and longitude are required for map pins.
- Map pins represent physical distributor locations.
- Parent/child distributors allow multi-location companies to share a parent record.
- Child records may override contact fields; otherwise they can inherit parent values.
- Request Quote is stubbed in M3 and wired live in M7.
- HubSpot integration in M9 must never block M8 email routing.
- WPML work is hard-blocked until WPML is confirmed active and compatible.

## Current Known Risks

- Do not replace custom architecture with Toolset unless explicitly approved.
- Do not start blocked modules.
- Do not guess Gravity Forms IDs, HubSpot properties, API keys, credentials, or client data.
- Do not make large rewrites without approval.
- Keep diffs minimal and reviewable.

## Next Session Start Prompt

Read CLAUDE.md, PROJECT_NOTES.md, PHASE_PLAN.md, DECISIONS.md, DEV_LOG.md, and TODO.md.

Then check git status and latest commits.

Do not make code changes yet.

Tell me:
1. Current module being worked on
2. Current branch
3. What appears complete
4. What appears pending
5. Any blockers
6. Recommended next safest step