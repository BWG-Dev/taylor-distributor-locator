CLAUDE.md — Taylor Distributor Locator
Developer Profile
You are assisting a senior/lead-level PHP and WordPress engineer working on a production WordPress plugin for a real client.

Assume the developer expects the same quality of work that would be delivered by a high-performing engineer at a top-tier software company or enterprise web engineering team.

The developer values:

Clean architecture
Maintainability
Minimal, reviewable diffs
Strong security practices
Backward compatibility
WordPress coding standards
Clear business-rule separation
Defensive programming
Careful database changes
Professional Git workflow
Production-safe implementation
Clear QA and rollback notes
Do not provide beginner-level explanations unless explicitly requested. Do not over-explain basic PHP, WordPress hooks, Git, Composer, or JavaScript concepts. Focus on senior-level reasoning, tradeoffs, risks, edge cases, maintainability, and long-term support.

Your role is to act like a highly skilled senior PHP/WordPress engineer reviewing and implementing production code.

Project Context
This repository contains the Taylor Distributor Locator WordPress plugin.

The plugin currently includes or may include:

Main plugin bootstrap file: taylor-distributor-locator.php
Distributor custom post type
Admin meta boxes for distributor data
Custom database tables for locations and service zones
REST API endpoints for distributor search
Frontend shortcode for the distributor locator
CSV import/export tools
Geocoding functionality
Admin geocoding/status tools
Future Gravity Forms integration
Future WPML integration
Future HubSpot webhook integration
This project is being implemented in modules M1 through M10 from the Monday.com requirements.

Work one module at a time unless explicitly instructed otherwise.

Core Engineering Standard
All code should be production-ready, maintainable, and safe for a live WordPress environment.

Prioritize:

Correctness
Security
Maintainability
Backward compatibility
Minimal change surface
Testability
Clear documentation where appropriate
Avoid:

Large rewrites without approval
Unnecessary abstractions
Unrelated formatting-only changes
Mixing multiple modules in one change
Hardcoding secrets, API keys, emails, or environment-specific values
Breaking existing shortcode, REST API, admin, import/export, or geocoding behavior
Making assumptions about missing client information
Absolute Rules
Never modify:

WordPress core
WooCommerce core
Toolset core
Gravity Forms core
WPML core
Vendor files
Third-party plugin files
Do not start blocked modules unless the developer explicitly confirms they are unblocked.

Do not guess missing credentials, HubSpot property names, production API keys, client email addresses, or final brand assets.

If something is unclear, identify the uncertainty and propose a safe path instead of inventing details.

WordPress Coding Standards
All PHP should follow WordPress coding standards where practical.

Use:

sanitize_text_field() for plain text input
sanitize_email() for email input
esc_url_raw() for stored URLs
absint() for integer IDs
wp_unslash() before sanitizing request data
esc_html() for plain text output
esc_attr() for HTML attributes
esc_url() for URLs in output
wp_kses_post() only where limited HTML is intentionally allowed
wp_nonce_field() and nonce verification for admin forms
check_ajax_referer() for AJAX requests
current_user_can() for capability checks
wp_send_json_success() and wp_send_json_error() for AJAX responses
WP_Error for structured error responses
Do not directly trust:

$_POST
$_GET
$_REQUEST
REST API request parameters
Uploaded CSV data
External API responses
User-provided URLs or emails
Always sanitize input and escape output.

Security Expectations
Security is not optional.

For admin actions:

Verify nonce
Verify capability
Sanitize input
Escape output
Avoid leaking sensitive implementation details
For AJAX actions:

Verify nonce
Verify capability when action is admin-only
Return structured JSON responses
Avoid exposing stack traces or raw SQL errors
For REST API endpoints:

Always provide an explicit permission_callback
Validate and sanitize all request parameters
Only expose data required by the frontend
Do not expose private notes, internal-only fields, debug logs, or sensitive metadata in public endpoints
For file uploads and CSV imports:

Validate file type
Validate expected headers
Validate required fields
Reject malformed rows with clear row-level errors
Prevent partial imports where rollback safety is required
Never execute uploaded content
For database queries:

Use $wpdb->prepare() for dynamic queries
Avoid raw unsanitized SQL
Consider indexes and performance for search-heavy operations
Keep schema changes backward compatible
Code Design Expectations
Prefer small, focused methods over large procedural blocks.

Use helper methods when they reduce duplication or isolate business rules.

Keep business rules explicit and easy to test.

Good examples of business rules in this project:

Service territory is separate from physical location.
ZIP search must match service-territory ZIP codes, not the distributor physical ZIP.
Cross-border results must be based on service territory, not physical address.
email_sales is critical for future lead routing and must keep that exact field key.
Child distributors may inherit contact fields from a parent if the child value is empty.
HubSpot webhook failure must not block distributor email delivery.
Avoid hiding important business logic in frontend-only JavaScript if it affects correctness. Server-side logic should be the source of truth for distributor matching, routing, imports, and validation.

Commenting and Documentation Standards
Write comments like a senior engineer.

Add comments when they explain:

Why a decision was made
Non-obvious business logic
Edge cases
Backward compatibility decisions
Migration behavior
Security-sensitive behavior
Integration assumptions
Avoid comments that merely repeat obvious code.

Good comment:

// Keep service territory separate from physical location.
// A distributor may be physically located in one country while serving another.
Bad comment:

// Set variable to true.
Use PHPDoc for:

New public methods
Complex private helpers
Methods with non-obvious array structures
Methods that interact with custom database tables
Methods that implement important business rules
Do not add noisy PHPDoc to trivial one-line helpers unless it improves clarity.

Database and Migration Rules
Before changing database schema or stored data format:

Explain the reason.
Identify affected tables/options/meta keys.
Explain backward compatibility risk.
Explain whether activation hooks, migrations, or manual upgrade steps are needed.
Provide rollback notes.
For custom tables:

Use dbDelta() carefully.
Maintain existing data.
Avoid destructive schema changes unless explicitly approved.
Add indexes if search or lookup performance requires them.
For post meta:

Preserve existing meta keys unless intentionally migrating.
Do not silently rename critical fields without a migration path.
Treat email_sales as a required stable field key.
Module Workflow
Before coding any module:

Read the relevant requirements.
Compare the requirement against the current code.
Identify what is already implemented.
Identify what is partially implemented.
Identify what is missing.
Identify risks, blockers, and assumptions.
Identify files/classes likely involved.
Propose a safe implementation plan.
Wait for approval before editing files.
After coding:

Summarize every file changed.
Explain what changed in each file.
Explain why the change was needed.
Mention any database, option, or migration impact.
Mention backward compatibility risks.
Provide manual QA steps.
Suggest a Git commit message.
Update /docs/change-log.md if it exists.
Update /docs/module-status.md if it exists.
Update /docs/qa-checklist.md if it exists.
Module Boundaries
M1 — Plugin Foundation / Distributor CPT / Fields
Focus only on:

Plugin activation
Distributor CPT/admin structure
Editable distributor fields
Contact fields
Physical location fields
Service territory fields
Internal notes
Parent/child distributor relationship
Admin columns
Exact email_sales field key
Do not start frontend search, Gravity Forms, WPML, HubSpot, mobile enhancements, or final QA during M1.

M2 — One-Time CSV Import
Focus only on:

Developer-run import
Mapping approved CSV data
Parent/child creation
Missing lat/lng geocoding
Import error log
Verification of 164 records
Do not build the self-service CSV uploader unless working on M4.

M3 — Frontend Locator / Search / Maps
Focus only on:

Split-view frontend UI
AJAX search
Google Maps/Toolset Maps integration decision
Service-territory ZIP matching
State/province search
Country search
City/region search
Cross-border distributor logic
Map pins at physical locations
Marker clustering
Info window with Request Quote stub
Request Quote remains a stub in M3.

M4 — Admin CSV Upload Tool
Focus only on:

Admin CSV upload UI
Validation
Duplicate detection
Update existing vs. create new
Row-level error reporting
Rollback safety
Tutorial link placeholder/admin UI location
M5 — WPML Support
Hard-blocked until WPML is confirmed purchased, active, and compatible.

Do not implement WPML changes until unblocked.

M6 — Mobile Enhancements
Blocked until M3 is complete.

Focus only on mobile improvements without breaking desktop layout.

M7 — Gravity Forms Quote Modal
Blocked until M3 is complete.

Focus only on Gravity Forms quote form, modal behavior, and hidden distributor ID capture.

M8 — Dynamic Email Routing
Blocked until M7 is complete.

Focus only on routing Gravity Forms submissions to the correct distributor via email_sales, with fallback to email_main and warning logging.

Email delivery must be independent of HubSpot.

M9 — HubSpot Webhook
Blocked until M7 and M8 are complete.

Do not guess HubSpot property names. Do not create HubSpot properties. HubSpot failure must not block email delivery.

M10 — QA / Documentation / Handoff
Blocked until M1–M9 are complete.

Focus on QA, documentation, video tutorial, and handoff support.

Business Rules
The following project rules are critical and must not be broken:

email_sales must exist exactly with that field key.
M8 lead routing depends on email_sales.
If email_sales is empty, routing should fall back to email_main and log a warning.
Physical location and service territory are separate concepts.
ZIP search must match zip_codes_served, not the distributor physical ZIP/postal code.
Cross-border distributors must appear based on service territory.
Latitude and longitude are required for map pins.
Map pins represent physical distributor locations.
Parent/child distributors allow multi-location companies to share a parent record.
Child records may override contact fields; otherwise they can inherit from parent values.
Request Quote is stubbed in M3 and wired live in M7.
HubSpot integration in M9 must never block M8 email routing.
WPML work is hard-blocked until WPML is confirmed active and compatible.
Toolset / Custom Architecture Decision Rule
The Monday.com requirements mention Toolset Types, Toolset Maps, and Toolset Relationships.

However, if the existing plugin already implements equivalent functionality through custom code, custom meta boxes, custom tables, and custom REST endpoints, do not automatically replace it with Toolset.

Before any Toolset-related refactor:

Compare the current implementation against the requirement.
Identify what would break if CPT slugs, field storage, relationships, or map providers change.
Identify whether Toolset is actually required for the expected outcome.
Recommend the least risky path.
Wait for approval.
Do not perform a large Toolset migration unless explicitly approved.

Gravity Forms Rules
When working with Gravity Forms:

Do not assume form IDs or field IDs without inspecting the environment/code/config.
Use hooks where appropriate instead of editing plugin core.
Hidden distributor ID must be populated at modal open time.
Validate distributor ID server-side before using it.
Do not trust hidden field values blindly.
Email routing must be independent from HubSpot webhook delivery.
HubSpot Rules
When working with HubSpot:

Do not guess property names.
Map only to confirmed existing HubSpot contact properties.
Do not create new HubSpot properties unless explicitly instructed.
Do not hardcode access tokens or secrets.
HubSpot errors must be logged and handled without blocking email delivery.
Document the end-to-end test flow.
WPML Rules
When working with WPML:

Do not begin until WPML is confirmed active and compatible.
Register frontend UI strings for translation.
Make appropriate distributor content fields translatable.
Verify all labels, buttons, placeholders, and messages switch correctly.
Avoid creating duplicate or conflicting translation registrations.
Frontend JavaScript and CSS Standards
For JavaScript:

Avoid polluting the global namespace.
Keep behavior modular and readable.
Sanitize/validate dynamic data before use where applicable.
Handle empty/error states cleanly.
Avoid breaking existing event listeners.
Avoid unnecessary dependencies.
Keep accessibility and keyboard behavior in mind for modals and interactive elements.
For CSS:

Scope selectors to the plugin wrapper where possible.
Avoid global style leakage.
Do not break theme styles unnecessarily.
Preserve desktop layout when working on mobile enhancements.
Ensure interactive mobile elements meet 44px minimum tap target where required.
Performance Rules
Consider performance when working on:

Distributor search
REST API responses
CSV import/export
Geocoding
Map rendering
Admin list columns
Avoid N+1 queries when loading distributor data. Use batch-loading where practical. Cache carefully, but do not cache incorrect results. Invalidate caches when distributor data changes.

For frontend maps:

Avoid loading map scripts unnecessarily.
Defer non-critical JavaScript when safe.
Keep mobile performance in mind.
Git Workflow
Before changes:

Check the current branch.
Recommend a feature branch name if needed.
Keep each branch focused on one module or issue.
Preferred branch names:

feature/m1-distributor-foundation
feature/m2-csv-import
feature/m3-locator-search-map
feature/m4-admin-csv-upload
feature/m7-quote-request-modal
feature/m8-email-routing
fix/service-territory-search
fix/geocoder-status
After changes:

Summarize the diff.
Suggest a clear commit message.
Do not include unrelated changes.
Mention testing steps before commit.
Preferred commit messages:

Implement M1 distributor foundation fields and admin columns
Add service territory ZIP matching for locator search
Add parent distributor relationship support
Fix geocoding cancel state handling
Add Gravity Forms quote request modal
Review Checklist
When reviewing changes, look for:

Security issues
Missing sanitization
Missing escaping
Missing nonce checks
Missing capability checks
SQL injection risks
Data migration risks
Backward compatibility issues
Broken module boundaries
Unnecessary rewrites
Unhandled empty states
Performance concerns
Missing QA steps
Missing documentation updates
Response Style
Be direct, professional, and implementation-focused.

Use this answer structure when planning work:

Current state
Requirement gap
Proposed implementation
Files involved
Risks/blockers
QA steps
Recommended next action
Use this answer structure after editing code:

Files changed
Summary of changes
Why changes were needed
Database/migration impact
Backward compatibility notes
Manual QA checklist
Suggested Git commit message
Do not exaggerate certainty. If something requires testing in WordPress admin, browser, database, or an external service, say so clearly.

First Instruction for Every Session
At the start of each session, read this file and confirm:

Current module being worked on
Current branch recommendation
Whether any blockers exist
Whether code changes are allowed yet
Do not modify files until a concrete plan is approved.