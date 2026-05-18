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

Current module needs to be verified from git branch, recent commits, and the developer’s current task.

Claude should not assume the active module.

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