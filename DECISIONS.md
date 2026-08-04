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
Modules remain blocked until their prerequisites are met.

Reason:
Several modules depend on client decisions, plugin availability, or earlier modules.

Current state (2026-08-04):
M1–M8 complete. **M9 is unblocked** (see below). M10 remains blocked until M9 is done.

---

## M9 unblocked — HubSpot via Gravity Forms Webhook Add-On

Decision (2026-08-04):
M9 is unblocked now that M7 and M8 are complete. HubSpot integration will be implemented by **configuring the Gravity Forms Webhook Add-On**, not by writing a custom `wp_remote_post` integration class. Custom glue code only where the add-on cannot do something natively.

Reason:
Client requirement. The add-on is supported, configurable in admin, and keeps the integration out of plugin code — which also keeps it structurally independent of M8 email routing.

Confirmed HubSpot contact properties (do not guess or create others):

| HubSpot label | Internal name |
|---|---|
| Company Name | `company` |
| [Taylor] Distributor Name | `distributor_name` |
| [Taylor] Message | `taylor_message` |

Fixed value: Lead Source = `Distributor Locator`. The *internal name* for the Lead Source property was not provided and must be confirmed before build.

Hard rule:
A HubSpot webhook failure must never block or delay M8 email routing. The two paths stay fully independent — failures are logged, never surfaced to the user.

Blocked on:
Client must provide HubSpot **sandbox** access before build begins. No testing against a production portal.

Secrets:
Access token / client secret live in a `wp-config` constant or WP option. Never hardcoded, never committed, never written to project memory files.

---

## M9 — verified HubSpot schema and property choices

Decision (2026-08-04), based on live inspection of portal **7290009** via `GET /crm/v3/properties/contacts` (2,298 contact properties — a Middleby corporate portal with brand-scoped namespaces `taylor__`, `mmp__`, `turbochef_cooktek__`, `follett__`).

| Purpose | Property | Type | Note |
|---|---|---|---|
| Company | `company` | text | As specified |
| Distributor | `distributor_name` | text | As specified |
| Message | `taylor_message` | textarea | As specified |
| Lead Source | `lead_source` | text | **Found — no creation needed** |

**Lead Source existed all along.** The admin UI search missed it among 2,298 properties. Two properties share the label "Lead Source": `lead_source` (text, group `contactinformation`) and `leadsource` (select, group `salesforceinformation`, 131 options, Salesforce-synced). We use `lead_source` — free text, no enum risk, no RevOps involvement. `Distributor Locator` is **not** among `leadsource`'s options; adding it there would be a Salesforce-sync decision belonging to the client.

**`taylor_distributor` must NOT be used.** It is a dropdown with 35 options, all US/domestic. WordPress holds 164 distributor posts across **142 distinct companies**, most of them international. HubSpot silently discards enumeration values that match no option, so roughly three quarters of leads would arrive with an empty distributor and no error surfaced anywhere.

**Distributor ID dropped from scope.** No HubSpot property exists for it; `distributor_name` satisfies the routing and reporting need. Revisit only if the client asks.

**Open question for the client:** sibling brands have scoped lead-source properties (`mmp__lead_source`, `turbochef_cooktek__lead_source`); there is no `taylor__lead_source`. If the portal convention is brand-scoped, Taylor may want one created for consistency.

---

## M9 — accepted risk: testing against the production portal

Decision (2026-08-04): the developer elected to build and test against the **production** portal (7290009, `accountType: STANDARD`). No sandbox exists.

Risks accepted, having been stated:

- Test submissions create real contacts in Taylor's live CRM
- Contact-creation workflows, notifications, and the live Salesforce sync may fire — **these are not reversible by deleting the contact**
- Test leads tagged `Distributor Locator` pollute the very funnel report M9 exists to produce

Required mitigation, agreed: **every test uses a unique, disposable, plus-addressed email** (`…+tdl01@…`) that has never existed in the portal. This guarantees create-never-update, so no existing contact can have `company` or other fields silently overwritten. Log every test address used so cleanup is provably complete.

Token scope was verified without writing any record: a POST carrying a deliberately invalid property returned **HTTP 400**, not 403, confirming authentication and `crm.objects.contacts.write` both pass.

---

## Branch cleanup

Decision (2026-08-04):
Only `master` and `staging` are kept long-term. Existing feature/fix branches will be deleted once their work is confirmed merged. M9 starts on a fresh branch.

Reason:
Five stale branches with unclear merge state make review and release tracking unreliable.

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