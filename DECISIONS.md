# Decisions — Taylor Distributor Locator

## Keep CLAUDE.md as the source of truth

Decision:
The existing CLAUDE.md remains the primary instruction file.

Reason:
It already contains detailed senior engineering expectations, module boundaries, security rules, business rules, Toolset rules, Gravity Forms rules, HubSpot rules, WPML rules, frontend standards, performance rules, Git workflow, and response style.

Impact:
New memory files should support CLAUDE.md, not replace it.

---

## Work one module at a time

Decision:
Do not mix modules unless explicitly approved.

Reason:
The project is organized by M1–M10, and mixing scope increases QA risk and makes PR review harder.

---

## Do not start blocked modules

Decision:
M5, M6, M7, M8, M9, and M10 remain blocked until their prerequisites are met.

Reason:
Several modules depend on client decisions, plugin availability, or earlier modules.

---

## Do not force Toolset migration

Decision:
Do not automatically replace custom code with Toolset.

Reason:
If the plugin already implements equivalent functionality through custom CPTs, meta boxes, custom tables, and REST endpoints, a Toolset migration could break slugs, field storage, relationships, or map behavior.

Approved rule:
Before any Toolset-related refactor, compare current implementation against requirements and wait for approval.

---

## Preserve service territory logic

Decision:
Service territory must remain separate from physical location.

Reason:
Distributor search correctness depends on service areas, not just physical address.

Critical rule:
ZIP search must match `zip_codes_served`, not physical ZIP/postal code.

---

## Preserve `email_sales`

Decision:
`email_sales` must keep this exact field key.

Reason:
Future M8 lead routing depends on it.