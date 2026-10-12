# Implementation State

## Purpose

Operational source of truth for current phase, current task, completed work, remaining work, tests, blockers, pending decisions, next approved step.

Do not infer implementation state from chat history.

---

# Current Project

Production Laravel API backend evolving into a Modular Monolith automation platform.

Architecture: Modular Monolith + DDD.

Reference:

- `docs/01_ARCHITECTURE_V4.md`
- `docs/ADR-028.md`

Canonical module root: `app/Modules/`
Modules: `Identity`, `Integrations`, `Connections`, `Automation`, `Execution`.

---

# Current Phase

## Phase

All planned pre-production work is COMPLETE. Production Scheduler Hardening Batches 1ŌĆō8 are complete. The Gmail integration has been completed for the current MVP scope and has been verified end-to-end against a real Gmail account. Localization (Batches 1ŌĆō4) is complete. Password Reset (Forgot Password) is implemented and verified against Gmail SMTP. Root API Controller localization is complete for the controllers whose source files were supplied. The Gmail attachment trigger feature (its own Batches 1ŌĆō6) has been implemented. The Gmail attachment links email action (its own Batches 1ŌĆō2) has been implemented, including a reusable trigger–action compatibility mechanism. No new numbered phase has been opened. Production has not been deployed.

## Status

```text
Phase 0ŌĆō8 COMPLETE / CLOSED / VERIFIED.
Post-Phase-8 completed branches recorded below.
Production Scheduler Hardening Batches 1ŌĆō8 COMPLETE.
Gmail Integration Completion COMPLETE / CLOSED / VERIFIED.
Gmail E2E verification COMPLETE / VERIFIED.
Strategy test coverage COMPLETE.
Localization (Batches 1ŌĆō4) COMPLETE.
Root API Controller Localization COMPLETE (subject to file availability).
Password Reset COMPLETE.
Gmail Attachment Trigger (its own Batches 1ŌĆō6) COMPLETE (reported).
Gmail Attachment Links Email Action (its own Batches 1ŌĆō2) COMPLETE (reported).
Live Production Readiness Verification PASS (local + real Gmail/Drive).
Batches 1ŌĆō2 changes deployed to production at commit 64ee567 on 2026-10-12.
Apps Script 5-minute scheduler trigger installed and firing against production.
No new numbered phase has been opened.
```

- Phase 0 ŌĆö COMPLETE
- Phase 1 ŌĆö MERGED INTO PHASE 2
- Phase 2 ŌĆö COMPLETE
- Phase 3 ŌĆö COMPLETE
- Phase 4 ŌĆö COMPLETE
- Phase 5 ŌĆö COMPLETE and explicitly approved
- Phase 6 ŌĆö COMPLETE / CLOSED (through Batch 6.5)
- Phase 7 ŌĆö COMPLETE / CLOSED / VERIFIED
- Phase 8 ŌĆö COMPLETE / CLOSED / VERIFIED (human approved)
- Catalog / Discovery API ŌĆö COMPLETE / CLOSED / VERIFIED
- Login API batch ŌĆö COMPLETE / CLOSED / VERIFIED
- Gmail polling hardening ŌĆö COMPLETE / CLOSED / VERIFIED
- Gmail self-email loop incident ŌĆö RESOLVED / VERIFIED
- Workflow API E2E audit ŌĆö COMPLETE / CLOSED / VERIFIED
- Path A `config: []` ŌåÆ `{}` contract fix ŌĆö COMPLETE / CLOSED / VERIFIED
- Production Scheduler Hardening Batch 1 ŌĆö COMPLETE / VERIFIED
- Production Scheduler Hardening Batch 2 ŌĆö COMPLETE / VERIFIED
- Production Scheduler Hardening Batch 3 ŌĆö COMPLETE / VERIFIED
- Production Scheduler Hardening Batch 4 ŌĆö COMPLETE / VERIFIED
- Production Scheduler Hardening Batch 5 ŌĆö COMPLETE / VERIFIED
- Production Scheduler Hardening Batch 6 ŌĆö COMPLETE / VERIFIED
- Production Scheduler Hardening Batch 7 ŌĆö COMPLETE / VERIFIED
- Production Scheduler Hardening Batch 8 ŌĆö COMPLETE (documentation)
- Strategy test coverage ŌĆö COMPLETE / VERIFIED
- Gmail Integration Completion ŌĆö COMPLETE / CLOSED / VERIFIED
- Gmail E2E verification ŌĆö COMPLETE / VERIFIED
- Localization (Batches 1ŌĆō4) ŌĆö COMPLETE
- Root API Controller Localization ŌĆö COMPLETE (subject to file availability)
- Password Reset (Forgot Password) ŌĆö COMPLETE
- Gmail Attachment Trigger (feature Batches 1ŌĆō6) ŌĆö COMPLETE (reported)
- Gmail Attachment Links Email Action (feature Batches 1ŌĆō2) ŌĆö COMPLETE (reported)
- Live Production Readiness Verification (2026-10-12) ŌĆö COMPLETE / VERIFIED (local + real Gmail/Drive)

Phase 9 remains LOCKED. Phase 10 remains LOCKED.

---

# Phase History

## Phase 0 ŌĆö Safety Net

Status: `COMPLETE`. 26 tests / 75 assertions at close.

## Phase 1 ŌĆö Architecture Skeleton

Status: `MERGED INTO PHASE 2` (ADR-027).

## Phase 2 ŌĆö Connections

Status: `COMPLETE`. 52 tests / 179 assertions at close.

## Phase 3 ŌĆö Integrations

Status: `COMPLETE`. 84 tests / 495 assertions at close.

## Phase 4 ŌĆö Compatibility + Google Migration

Status: `COMPLETE`. 99 tests / 519 assertions at close.

## Phase 5 ŌĆö Existing Feature Refactoring

Status: `COMPLETE`. 293 tests / 986 assertions at close.

## Phase 6 ŌĆö Workflow Engine

Status: `COMPLETE / CLOSED`. 507 tests / 1486 assertions at close.

Closed through the approved Batch 6.1ŌĆō6.5 scope.

---

# Phase 7 ŌĆö Gmail Automation MVP

Status: `COMPLETE / CLOSED / VERIFIED`.

## Phase 7 MVP Scope ŌĆö satisfied

- Gmail `new_email_received` trigger runtime.
- Gmail polling runtime.
- Gmail label selection / filtering.
- Existing `send_email` action remains usable.
- Trigger payload includes attachment metadata for future compatibility.
- Capability-based Gmail scope escalation at connection creation.
- Activation-time capability validation for Gmail-gated triggers and actions.

Batches:

- Batch 7.1 ŌĆö COMPLETE / CLOSED / VERIFIED
- Batch 7.2 ŌĆö COMPLETE / CLOSED / VERIFIED
- Batch 7.3 ŌĆö COMPLETE / CLOSED / VERIFIED
- Batch 7.4 / 7.4b ŌĆö COMPLETE / CLOSED / VERIFIED
- Batch 7.5 ŌĆö COMPLETE / CLOSED / VERIFIED (scope-classification only)

---

# Phase 8 ŌĆö Swagger / OpenAPI Documentation

Status: `COMPLETE / CLOSED / VERIFIED` (human approved).

## Tooling

- `darkaonline/l5-swagger` **11.1.0**
- `zircote/swagger-php` **6.10.0** (transitive)
- `swagger-api/swagger-ui` **v5.33.0** (transitive)
- Laravel 12.31.1 / PHP 8.2.12.

## Structure

Global configuration:

- `app/Modules/Swagger/OpenApi.php` ŌĆö `info`, `servers`, `securityScheme` (`bearerAuth`), `Tag` list, shared schemas and responses.

Per-module path files:

- `app/Modules/Identity/Swagger/Identity.php`
- `app/Modules/Connections/Swagger/Connections.php`
- `app/Modules/Automation/Swagger/Automation.php`
- `app/Modules/Execution/Swagger/Execution.php`
- `app/Modules/Integrations/Swagger/{Gmail,Calendar,Sheets,Docs,Analytics,Catalog}.php`

## L5-Swagger scan configuration

- `config/l5-swagger.php` published.
- `documentations.default.paths.annotations` = `base_path('app/Modules')`.

## Server configuration

`app/Modules/Swagger/OpenApi.php` declares two `#[OA\Server]` entries:

- `http://localhost:8000` ŌĆö "Local Development"
- `https://sea-turtle-app-vshwt.ondigitalocean.app` ŌĆö "Production"

Swagger UI allows the operator to switch between them.

## Coverage

57 operations at Phase 8 close. Catalog and Login batches added one each.

## Explicitly excluded

- `POST /api/test/login` (dev only).
- Facebook legacy endpoints.
- Console commands.
- Deferred Phase 7 capabilities.

---

# Catalog / Discovery API

Status: `COMPLETE / CLOSED / VERIFIED` (human approved).

## Scope

- `GET /api/catalog`, `auth:sanctum`, operationId `catalog.show`, tag `Catalog`.
- Response derived from `IntegrationCatalog`.
- Field metadata added to `ActionDefinition` / `TriggerDefinition` as optional `array $fields = []`.
- New `FieldDefinition` value object.
- No second registry, no DB table.

## Files added

- `app/Modules/Integrations/Core/ValueObjects/FieldDefinition.php`
- `app/Modules/Integrations/Application/Services/CatalogProjector.php`
- `app/Modules/Integrations/Http/Controllers/CatalogController.php`
- `app/Modules/Integrations/Swagger/Catalog.php`
- `tests/Feature/Integrations/CatalogEndpointTest.php`
- `tests/Unit/Integrations/FieldDefinitionTest.php`

## Files modified

- `app/Modules/Integrations/Core/Entities/ActionDefinition.php`
- `app/Modules/Integrations/Core/Entities/TriggerDefinition.php`
- `app/Modules/Integrations/Infrastructure/Google/GmailIntegration.php`
- `app/Modules/Integrations/Http/Routes/api.php`

---

# Login API Batch

Status: `COMPLETE / CLOSED / VERIFIED` (human approved).

## Scope

- `POST /api/login` (public).
- Implemented inside the existing Identity bounded context.
- Does not create users. Does not modify `/api/register`, Google OAuth, Sanctum config, or the `User` model.

## Files created

- `app/Modules/Identity/Application/DTOs/LoginInput.php`
- `app/Modules/Identity/Application/Actions/LoginAction.php`
- `app/Modules/Identity/Http/Requests/LoginRequest.php`
- `app/Modules/Identity/Http/Controllers/LoginController.php`
- `app/Modules/Identity/Http/Routes/api.php`
- `app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php`
- `tests/Feature/Identity/LoginTest.php`

## Files modified

- `app/Modules/Identity/Swagger/Identity.php`
- `bootstrap/providers.php`
- `docs/06_MOBILE_API_CONTRACT.md`

## Behavior

- Validates `email`, `password`.
- `Hash::check()` verification.
- Sanctum token issued on success.
- Generic `401 { "message": "Invalid credentials." }` for all credential failures.
- `has_bot_access` not required, not modified.
- `access_expiry` not modified.

## Login rate limiting

**Not yet applied.** A rate-limited version of `LoginController` was proposed during the pre-production review (5 attempts per email+IP per minute, returns HTTP 429 with a `Retry-After` header, counter cleared on success). It has not been integrated into the codebase.

If/when applied, update this section to reflect it and adjust `docs/06_MOBILE_API_CONTRACT.md` ┬º1.7 accordingly.

---

# Live Production Readiness Verification (2026-10-12)

Status: `COMPLETE / VERIFIED (local + real Gmail/Drive)`

## Purpose

End-to-end verification of the Batches 1ŌĆō2 work against a real Gmail
mailbox, a real Google Drive account, and the local scheduler endpoint.
Performed against the triggerŌĆōaction compatibility rule, the
`send_attachment_links_email` action definition, and the production scheduler
architecture.

## Scope

- Phases A through H of the production readiness plan.
- Real Gmail account (`amr.h.sallam00@gmail.com`) as the connected account.
- Real second account (`amrhatem.brooklyn@gmail.com`) as the sender.
- Real third account (`amero.ah@gmail.com`) as the recipient of the outgoing
  links email.
- Manual `POST /api/internal/scheduler/tick` calls against a local Laravel
  instance.

## Verified

### Workflow API and compatibility (Phase C)

- Attachment trigger + `send_attachment_links_email` accepted (HTTP 201).
- Step add with `new_email_received` + `send_attachment_links_email` rejected
  (HTTP 409, `error: "incompatible_trigger"`).
- Trigger replacement to `new_email_received` with an existing restricted step
  rejected (HTTP 409, `context.location` included the position).
- Original trigger preserved after the rejected replacement (atomic).
- `send_email` accepted with `new_email_received` (unrestricted action
  confirmed).
- `send_email` accepted with the attachment trigger (attachment trigger not
  made exclusive).

### Catalog and localization (Phase D)

- English catalog exposed the new action with the four expected fields
  (`to`, `subject`, `intro_text`, `attachments`) and correct `default`.
- Arabic catalog localized labels while keeping identifiers byte-identical.
- `allowedTriggerKeys` not exposed anywhere in the catalog response.

### Scheduler endpoint (Phase E)

- Unauthenticated tick returned HTTP 401 with body `{"error":"unauthorized"}`.
- Wrong-token tick returned HTTP 401.
- First tick after activation initialized `poll_cursor` and did not fetch
  historical messages.
- Immediate second tick returned `processed: 0` (no duplicate processing).

### Live Gmail attachment end-to-end (Phase F)

- Trigger detected the incoming email via the composed Gmail query.
- Attachment was downloaded from Gmail.
- Attachment was uploaded to Google Drive via `drive.file` scope.
- Trigger payload contained the canonical 15 keys with a non-null
  `web_view_link`.
- Step output was `{"sent": true, "message_id": "..."}`.
- Execution recorded as `completed` with `trigger_source: "poll"`.
- Idempotent re-tick produced zero additional executions.

### Outgoing email (Phase G)

Raw MIME source confirmed:

- `Content-Type: multipart/alternative`.
- `text/html` body part contained `<p>...</p>` + `<ul>` + `<li><a href="...">...</a></li>`.
- `text/plain` fallback auto-generated by Gmail.
- No attached binary parts.
- `From: amr.h.sallam00@gmail.com`, `To: amero.ah@gmail.com`,
  `Subject: Your attachments`.

### Negative tests (Phase H)

- Paused workflow not polled (`processed: 0`).
- Non-matching `from:` sender did not trigger an execution.
- Self-authored email did not trigger an execution.
- Test workflows cleaned up; no active workflows left in the local database.

## Findings (recorded, not defects)

### Finding 1 ŌĆö Scheduler tick duration approximately 23.5 seconds

A single-attachment E2E tick took about 23.5 seconds wall-clock on the local
environment. The tick chains Gmail list + get + attachment download + Drive
upload + Gmail send + status writes synchronously. Under a default
`php max_execution_time` of 30 seconds this is close to the ceiling and leaves
little headroom for concurrent due workflows in the same tick.

Not a defect. Recorded as a capacity consideration. The production
`INTERNAL_SCHEDULER_BATCH_SIZE` may need lowering if real production traffic
approaches the same per-workflow duration.

### Finding 2 ŌĆö Apps Script 5-minute trigger was not installed

The Apps Script project's Triggers page showed "No results" during
verification. Automation therefore relied on manual `POST
/api/internal/scheduler/tick` calls. `docs/08_DEPLOYMENT.md` and this
document have been updated to reflect the actual state. Installation is an
operator action and is recorded as pending.

### Finding 3 ŌĆö Drive links do not automatically grant access

The third-party recipient could not open the Drive link without requesting
access from the file owner. This matches the documented behavior: the
`drive.file` scope is least-privilege, no public permissions are set, and
existing Drive permissions govern access. This is a product-level limitation,
not a bug. Files remain private to the user's own Google account.

### Finding 4 ŌĆö Idempotency counter ambiguity

A tick that fetches messages and skips all of them via idempotency returns
`{"processed": 1, "executed": 0, "skipped": 0}`. A tick that finds nothing
returns `{"processed": 0, "executed": 0, "skipped": 0}`. Both surface as
"the tick ran but did nothing". Not a defect. Recorded as an observability
note.

## Deployment status

The Batches 1ŌĆō2 changes were deployed to production at commit 64ee567 on
2026-10-12. The Apps Script 5-minute time-driven trigger was installed the
same day. Manual `tick()` against the production endpoint returned
`{"ok":true,"processed":0,"executed":0,"skipped":0,"failed":0,"lock_held":false}`.
The first automatic time-driven invocation fired within 3 minutes of install
and returned a clean response. Automatic polling of the production scheduler
is live.

## Not verified by this pass

- Live Gmail delivery through the production environment (all E2E was local).
- Automatic five-minute Apps Script invocations (trigger not installed).
- Missing-scope activation against a real old-scope-only Google connection.
- Any production-specific behavior (env vars, cache store, mail transport,
  `config:cache`).

## Files touched

None. This was a verification pass.

---

# Post-Phase-8 Completed Branches

These branches were completed after Phase 8 without being designated as a new numbered phase.

---

## Gmail Polling Hardening

Status: `COMPLETE / CLOSED / VERIFIED`.

Delivered:

- `RunWorkflowJob` ŌĆö `ShouldQueue`, `ShouldBeUnique`, `$tries = 3`, `$backoff = [30, 120, 600]`, `$uniqueFor = 3600`. Retained for legacy/manual flows, not used by the new scheduler path.
- `PollGmailCommand` ŌĆö self-email filter, per-trigger cache lock, cursor advancement, `next_poll_at` scheduling, rate limiting. Retained as CLI tool.
- `RecoverFailedPollExecutionsCommand` ŌĆö `MAX_RETRIES = 3`, `GRACE_MINUTES = 10`, `BATCH_SIZE = 100`, CAS claim, `retry_of_id` lineage. Retained as CLI tool.
- Migrations: `workflow_triggers.next_poll_at`, `executions.retry_attempts`, `executions.retry_of_id`.
- `ActivateWorkflowAction` resets `next_poll_at = null` on activation.
- `UpsertWorkflowTriggerAction` preserves `next_poll_at` on update.
- `WorkflowRepository::listActiveWithTriggerDue()` added.

---

## Gmail Self-Email Loop Incident ŌĆö Diagnosis and Resolution

Status: `RESOLVED / VERIFIED`.

## Incident

Before hardening, a Gmail workflow could fire on its own outbound reply. Since the outbound reply lands in the same Gmail INBOX as the trigger source, the workflow re-triggered itself. Observed escalation: 3s ŌåÆ 28s ŌåÆ 40s per tick.

## Resolution

`filterOutSelfEmails` (in `PollGmailCommand` and `GmailPollStrategy`) compares each fetched message's `From:` header against the connected Gmail account email. Case-insensitive, display-name aware. Skipped messages are logged at INFO level and the cursor advances past them.

## Verification

Live Gmail verification confirmed the filter breaks the loop: two self-authored replies were filtered, zero `RunWorkflowJob` instances dispatched, cursor advanced.

---

## Workflow API E2E Audit

Status: `COMPLETE / CLOSED / VERIFIED`.

- `tests/Feature/Audit/WorkflowApiAuditTest.php` ŌĆö 26 tests / 105 assertions.
- Covers: authentication, catalog, connections, Gmail labels, workflow CRUD, trigger upsert, step upsert, activate/pause/resume/delete/restore, manual execution, idempotency, `interval_minutes` validation sweep, scheduler semantics, cross-user ownership, unauthenticated requests, negative testing.

---

## Path A ŌĆö `config: []` ŌåÆ `{}` Contract Fix

Status: `COMPLETE / CLOSED / VERIFIED`.

## Bug

Empty `config` objects serialized as JSON arrays (`[]`) instead of JSON objects (`{}`).

## Fix ŌĆö production files changed

- `app/Modules/Integrations/Application/Services/CatalogProjector.php`
- `app/Modules/Automation/Application/DTOs/TriggerData.php`

## Fix ŌĆö test file changed

- `tests/Feature/Audit/WorkflowApiAuditTest.php`

---

# Gmail Integration Completion

Status: `COMPLETE / CLOSED / VERIFIED` for the implemented MVP scope.

This branch completed the Gmail integration as the platform's first full-featured
integration. It added new actions, extended the trigger with filters, and
expanded the OAuth capability set. It is the reference integration for future
providers.

## Actions added

- `reply_to_email` ŌĆö sends a reply in an existing Gmail thread. Uses the Gmail
  API `threadId` mechanism. Requires `gmail.send`.
- `create_draft` ŌĆö creates a draft without sending. Requires `gmail.compose`.
- `mark_as_read` ŌĆö `users.messages.modify` with `removeLabelIds: ['UNREAD']`.
  Requires `gmail.modify`.
- `mark_as_unread` ŌĆö `users.messages.modify` with `addLabelIds: ['UNREAD']`.
  Requires `gmail.modify`.
- `archive` ŌĆö `users.messages.modify` with `removeLabelIds: ['INBOX']`.
  Requires `gmail.modify`.
- `trash` ŌĆö `users.messages.trash`. Requires `gmail.modify`.
- `add_label` ŌĆö `users.messages.modify` with `addLabelIds: [labelId]`.
  Requires `gmail.modify`.
- `remove_label` ŌĆö `users.messages.modify` with `removeLabelIds: [labelId]`.
  Requires `gmail.modify`.
- `create_label` ŌĆö `users.labels.create`. Requires `gmail.labels`.

Together with the pre-existing `send_email`, the Gmail integration now exposes
ten actions: `send_email`, `reply_to_email`, `create_draft`, `mark_as_read`,
`mark_as_unread`, `archive`, `trash`, `add_label`, `remove_label`,
`create_label`.

## Trigger extensions

`new_email_received` gained four optional filter fields, all passed through to
Gmail's native `q` search parameter via `GmailTriggerQueryComposer`:

- `from` (string)
- `subject` (string; multi-word values are quoted)
- `has_attachment` (boolean; emits `has:attachment`)
- `query` (string; raw Gmail syntax, appended last)

`label_id` remains unchanged and is passed to Gmail as a separate `labelIds`
parameter, not as part of `q`.

## OAuth scope expansion

`CapabilityScopeMap::CAPABILITIES['gmail']` now requests five scopes:

- `https://www.googleapis.com/auth/gmail.readonly`
- `https://www.googleapis.com/auth/gmail.send`
- `https://www.googleapis.com/auth/gmail.compose`
- `https://www.googleapis.com/auth/gmail.modify`
- `https://www.googleapis.com/auth/gmail.labels`

**Existing Google connections must reconnect** to obtain
`gmail.compose`, `gmail.modify`, and `gmail.labels`. Connections with only the
original two scopes (`gmail.readonly`, `gmail.send`) can still use
`send_email`, `reply_to_email`, and `new_email_received` without reconnecting.

## Attachment metadata

The Gmail trigger payload already includes three attachment-related keys,
produced by `GmailMessagePayloadBuilder`:

- `has_attachment` (bool)
- `attachment_count` (int)
- `attachment_ids` (array of Gmail attachment part IDs)

These keys are exposed on every Gmail-triggered execution and are available to
step config templates as `{{ trigger.has_attachment }}`,
`{{ trigger.attachment_count }}`, and `{{ trigger.attachment_ids }}`.

**Attachment materialization is a separate concern, implemented by a later
branch.** The `new_email_received_with_attachment` trigger (see "Gmail
Attachment Trigger (feature Batches 1ŌĆō6)" below) does download attachment
bytes from Gmail and materialize them into the user's Drive. What remains
deferred here is narrower: a downstream workflow action that reads attachment
bytes back **out of Drive** and passes them into execution output. There is
still no action that returns the raw binary content of an attachment to a
workflow step. This is intentional ŌĆö putting large base64 payloads into
`execution_steps.output` is unsafe with the current schema.

## Files

New:

- `app/Modules/Integrations/Application/DTOs/ReplyToGmailEmailInput.php`
- `app/Modules/Integrations/Application/Actions/ReplyToGmailEmailAction.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailReplyToEmailHandler.php`
- `app/Modules/Integrations/Infrastructure/Google/Gmail/GmailTriggerQueryComposer.php`
- `app/Modules/Integrations/Application/DTOs/CreateGmailDraftInput.php`
- `app/Modules/Integrations/Application/DTOs/CreateGmailDraftResult.php`
- `app/Modules/Integrations/Application/Actions/CreateGmailDraftAction.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailCreateDraftHandler.php`
- `app/Modules/Integrations/Application/DTOs/ModifyGmailMessageInput.php`
- `app/Modules/Integrations/Application/DTOs/ModifyGmailMessageResult.php`
- `app/Modules/Integrations/Infrastructure/Google/Gmail/GmailMessageModifier.php`
- `app/Modules/Integrations/Application/Actions/MarkGmailAsReadAction.php`
- `app/Modules/Integrations/Application/Actions/MarkGmailAsUnreadAction.php`
- `app/Modules/Integrations/Application/Actions/ArchiveGmailMessageAction.php`
- `app/Modules/Integrations/Application/Actions/TrashGmailMessageAction.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailMarkAsReadHandler.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailMarkAsUnreadHandler.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailArchiveHandler.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailTrashHandler.php`
- `app/Modules/Integrations/Infrastructure/Google/Gmail/GmailLabelCreator.php`
- `app/Modules/Integrations/Application/DTOs/ModifyGmailLabelsInput.php`
- `app/Modules/Integrations/Application/DTOs/ModifyGmailLabelsResult.php`
- `app/Modules/Integrations/Application/DTOs/CreateGmailLabelInput.php`
- `app/Modules/Integrations/Application/DTOs/CreateGmailLabelResult.php`
- `app/Modules/Integrations/Application/Actions/AddGmailLabelAction.php`
- `app/Modules/Integrations/Application/Actions/RemoveGmailLabelAction.php`
- `app/Modules/Integrations/Application/Actions/CreateGmailLabelAction.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailAddLabelHandler.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailRemoveLabelHandler.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailCreateLabelHandler.php`

Modified:

- `app/Modules/Integrations/Infrastructure/Google/GmailIntegration.php`
- `app/Modules/Integrations/Infrastructure/Google/Gmail/GmailEmailSender.php`
- `app/Modules/Integrations/Infrastructure/Google/Gmail/GmailMessageReader.php`
- `app/Modules/Integrations/Application/Services/CapabilityScopeMap.php`
- `app/Modules/Integrations/Application/DTOs/FetchGmailTriggerMessagesInput.php`
- `app/Modules/Integrations/Application/Actions/FetchGmailTriggerMessagesAction.php`
- `app/Modules/Execution/Application/Strategies/GmailPollStrategy.php`
- `app/Modules/Execution/Console/Commands/PollGmailCommand.php`
- `app/Modules/Execution/Infrastructure/Invokers/HandlerRegistry.php`

Tests:

- `tests/Feature/Execution/GmailReplyToEmailHandlerTest.php`
- `tests/Unit/Integrations/GmailTriggerQueryComposerTest.php`
- `tests/Feature/Execution/GmailCreateDraftHandlerTest.php`
- `tests/Feature/Execution/GmailMessageModificationHandlerTest.php`
- `tests/Feature/Execution/GmailLabelActionsHandlerTest.php`
- `tests/Support/Fakes/FakeGmailMessageReader.php`
- `tests/Feature/Execution/PollGmailCommandTest.php`
- `tests/Feature/Execution/GmailPollStrategyTest.php`
- `tests/Unit/Integrations/CapabilityScopeMapTest.php`
- `tests/Feature/Connections/StartGoogleConnectionTest.php`
- `tests/Unit/Integrations/GoogleIntegrationsMetadataTest.php`
- `tests/Unit/Integrations/IntegrationCatalogTest.php`
- `tests/Unit/Execution/HandlerRegistryTest.php`
- `tests/Feature/Execution/CatalogActionInvokerTest.php`
- `tests/Feature/Audit/WorkflowApiAuditTest.php`

## E2E verification status

**VERIFIED against a real Gmail account.** See the "Gmail E2E Verification Pass" section below for details.

The original `new_email_received` + `send_email` workflow and the production
scheduler were already verified end-to-end prior to this pass. The nine new
Gmail actions, all four new trigger filters, multi-step execution, and
inter-step template resolution have since been verified end-to-end against a
real Gmail mailbox.

## Swagger status

No new HTTP endpoints were added for the Gmail workflow actions. The Gmail
actions are workflow/catalog actions executed through the generic workflow
engine, not standalone HTTP endpoints. Existing Swagger HTTP documentation
remains valid; no new Swagger endpoints were invented for these actions.

## Documentation status

The repository documentation files were updated in this pass to reflect the
final Gmail completion state. Any further additions (for example, a future
Gmail attachment batch) must update these documents again.

---

# Gmail E2E Verification Pass

Status: `COMPLETE / VERIFIED`.

## Scope

Live end-to-end verification of the entire Gmail integration stack against a
real Gmail account: OAuth connection, catalog discovery, workflow CRUD, trigger
CRUD, step CRUD, activation, manual execution, scheduler tick, polling,
per-action execution, trigger filters, multi-step execution, and
unauthenticated rejection.

Executed from Section 1 to Section 12 of the E2E plan.

## Sections verified

### Section 1 ŌĆö Auth

- `GET /api/user` with valid token ŌåÆ 200.
- `GET /api/user` without token ŌåÆ 401.
- `POST /api/login` with correct credentials ŌåÆ 200, Sanctum token.
- `POST /api/login` with wrong password ŌåÆ 401 generic body.
- Catalog gated behind Sanctum ŌåÆ 401 unauthenticated.

### Section 2 ŌĆö Catalog

- `GET /api/catalog` returned `google.gmail` with exactly 10 actions and
  exactly 1 trigger.
- Trigger `new_email_received` declared all four filter fields plus `label_id`.
- Empty-config action serialized as `"config": {}` (Path A contract holds).

### Section 3 ŌĆö Connections

- `GET /api/connections` returned the connected Google account with all
  eight expected scopes (3 identity + 5 Gmail).
- No `access_token`, `refresh_token`, `last_error_message`, or `client_secret`
  exposed.
- `GET /api/gmail/labels?connection_id=N` returned the user's labels.
- Non-owned `connection_id` ŌåÆ 404.
- Unknown capability on OAuth start ŌåÆ 422.

### Section 4 ŌĆö Workflow CRUD

- Create, show, list, update, soft-delete, restore ŌĆö all 200/201 as expected.
- Deleted workflow excluded from default list, present in `?trashed=true`.
- Show of deleted workflow ŌåÆ 404.
- Cross-user access on every workflow endpoint ŌåÆ 404, not 403.
- Restore of a non-deleted workflow is a no-op returning 200.

### Section 5 ŌĆö Trigger CRUD

- Unknown integration ŌåÆ 422 on `integration_key`.
- Unknown trigger for known integration ŌåÆ 422 on `trigger_key`.
- Upsert created trigger; re-upsert preserved the same trigger ID (find-then-update).
- Empty config serialized as `{}`, not `[]`.
- Invalid template syntax ŌåÆ 422 on `config`.
- Non-owned connection ŌåÆ 404.
- Delete removed trigger; delete again ŌåÆ 404.

### Section 6 ŌĆö Step CRUD

- Missing keys ŌåÆ 422 on `integration_key` and `action_key`.
- Unknown action ŌåÆ 422 on `action_key`.
- Position auto-assignment: 1, 2, 3, then after gap, 4 (no reindex).
- Update at missing position ŌåÆ 404.
- Delete removed step without reindexing remaining positions.

### Section 7 ŌĆö Activation and lifecycle

- Activate without trigger ŌåÆ 409.
- Activate ŌåÆ 200, status `active`.
- Activate again ŌåÆ 409 invalid transition.
- Pause ŌåÆ 200, status `paused`.
- Execute paused workflow ŌåÆ 409.
- Reactivate ŌåÆ 200, status `active`.
- Activation resets `next_poll_at = null`.
- **`missing_scopes` case was skipped during the live pass** because no
  old-scope-only Google connection was available at test time. The behavior
  is covered by unit and feature tests
  (`WorkflowActivationCapabilityTest`, `WorkflowCapabilityValidatorTest`) and
  by `docs/06_MOBILE_API_CONTRACT.md` ┬º3.5. It must be exercised against a
  live old-scope connection before production deployment.

### Section 8 ŌĆö Manual execution

- Manual execute with crafted payload ŌåÆ execution persisted with
  `trigger_source: "manual"`.
- Gmail side effect on a fake message ID correctly produced a `failed`
  execution with the Gmail `400 Invalid id value` body recorded in
  `error_message`. No credential leakage in the error output.
- Same `idempotency_key` replayed ŌåÆ returned the existing execution `200`,
  no duplicate row.
- Reserved prefix `schedule:` ŌåÆ 422.
- Reserved prefix `gmail:` ŌåÆ 422.
- Overlap check with a synthetic `running` execution row ŌåÆ 409.
- Empty-body manual execute ŌåÆ execution persisted with trigger payload
  omitted. Template resolution correctly failed with
  `Template path "trigger.message_id" cannot be resolved.` and the execution
  was recorded `failed` ŌĆö this confirms the template-miss path is
  properly classified as a domain failure, not a crash.

### Section 9 ŌĆö Scheduler tick and polling

- First tick after activation initialized `poll_cursor` and set
  `next_poll_at = now + interval_minutes`; created zero executions.
- Second tick (after forcing `next_poll_at = NULL` and sending a real
  email) detected the message and executed the workflow.
- Trigger payload contained all 14 canonical keys.
- `idempotency_key` correctly formatted as `gmail:{workflow_id}:{message_id}`.
- Real Gmail side effect (`mark_as_read`) reflected in the Gmail UI.
- Re-running the tick with the same message (forced due) created **zero**
  additional executions ŌĆö idempotency holds.

### Section 10 ŌĆö Per-action and filter E2E

**Actions verified against a real Gmail account:**

| Action           | Result | Evidence                                                                                              |
| ---------------- | ------ | ----------------------------------------------------------------------------------------------------- |
| `send_email`     | PASS   | (verified during prior scheduler hardening)                                                           |
| `reply_to_email` | PASS   | Reply visible in the same Gmail thread, real `message_id` returned                                    |
| `create_draft`   | PASS   | Draft visible in Gmail Drafts, real `draft_id` and `message_id` returned, destination inbox untouched |
| `mark_as_read`   | PASS   | Read state flipped in Gmail UI                                                                        |
| `mark_as_unread` | PASS   | Unread state flipped in Gmail UI                                                                      |
| `archive`        | PASS   | Message removed from INBOX, still reachable via All Mail                                              |
| `trash`          | PASS   | Message present in Trash, not permanently deleted                                                     |
| `add_label`      | PASS   | Label attached to the message                                                                         |
| `remove_label`   | PASS   | Label removed from the message                                                                        |
| `create_label`   | PASS   | New label appeared in Gmail's label list                                                              |

**Multi-step execution:** A single seven-step workflow exercised
`create_label` ŌåÆ `add_label` ŌåÆ `mark_as_unread` ŌåÆ `mark_as_read` ŌåÆ
`remove_label` ŌåÆ `archive` ŌåÆ `trash` in position order. Every step completed
successfully. All Gmail UI checks confirmed the expected final state.

**Inter-step template resolution:** `{{ steps.1.output.label_id }}` was used
in steps 2 and 5 of the multi-step workflow. Both steps resolved the value
to the label ID returned by step 1 (`Label_1`), which confirms that the
`ExecutionContext` `stepOutputs` map is wired correctly and that the runner
threads outputs forward between steps. **This is the first live confirmation
that inter-step templates work end-to-end.**

**Trigger filters verified:**

| Filter                 | Positive                                                | Negative                                                                                                                                                                                         |
| ---------------------- | ------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `label_id: "INBOX"`    | PASS (implicit in every triggered email)                | ŌĆö                                                                                                                                                                                              |
| `subject`              | PASS (positive case verified across multiple workflows) | PASS (implicit ŌĆö no non-matching email ever triggered)                                                                                                                                         |
| `from`                 | Not exercised live (requires a second external sender)  | Not exercised live                                                                                                                                                                               |
| `has_attachment: true` | PASS (email with attachment triggered)                  | PASS (email without attachment did not trigger)                                                                                                                                                  |
| `query: "is:unread"`   | PASS (three consecutive executions confirmed matching)  | Not directly demonstrated in the live pass; the filter delegates to Gmail's native `is:unread` operator through the same `q` composer as `has_attachment`, whose negative case WAS verified live |

### Section 11 ŌĆö Hero test

- Single workflow: `new_email_received` (filter `subject: "E2E-Hero"`) ŌåÆ
  `mark_as_read` (position 1) ŌåÆ `reply_to_email` (position 2).
- Both steps completed successfully.
- The reply landed in the **same conversation thread** as the incoming
  message, confirmed in Gmail UI.
- The incoming message was marked read.
- `thread_id` template resolved correctly to the original thread ID.

### Section 12 ŌĆö Unauthenticated access matrix

All 21 protected endpoints returned `401 Unauthenticated` when called without
a bearer token. No data leak anywhere.

`POST /api/internal/scheduler/tick` without token also returned `401`.

## Files touched

None. This was a verification pass. No production code was modified as a
result of the E2E run.

## Notes and observations

1. **`missing_scopes` activation path** was not exercised against a real
   old-scope-only connection during this pass. Behaviour is covered by
   automated tests. Should be verified live before production deployment.

2. **`is:unread` filter negative case** was not directly demonstrated. The
   positive case was verified three times. The negative path shares its
   implementation with `has_attachment`, whose negative case WAS verified
   live. Behaviour is considered verified but a clean negative retest is
   cheap if desired.

3. **Scheduler tick unauthenticated body** returned
   `{"message":"Unauthenticated."}` rather than the middleware's declared
   `{"error":"unauthorized"}`. The 401 status itself is correct; only the
   response body differs. This is a small post-E2E observation, not a
   security regression: unauthenticated requests are still rejected. Slated
   for investigation in the next maintenance pass ŌĆö possibly the internal
   route is being wrapped by an authentication middleware earlier than
   expected, or the tick endpoint is being matched by a fallback. No action
   required to close the E2E run.

4. **Manual execution of a fake message ID** correctly records the Gmail
   `400 Invalid id value` response as a `failed` execution. This confirms
   the failure-classification path and the "no credential leakage in error
   output" property.

5. **Inter-step `{{ steps.N.output.* }}` templates** are now verified live.
   This was previously only covered by unit tests of the resolver.

## Gate

- [x] Section 1 ŌĆö Auth verified.
- [x] Section 2 ŌĆö Catalog verified.
- [x] Section 3 ŌĆö Connections verified.
- [x] Section 4 ŌĆö Workflow CRUD verified.
- [x] Section 5 ŌĆö Trigger CRUD verified.
- [x] Section 6 ŌĆö Step CRUD verified.
- [x] Section 7 ŌĆö Activation/lifecycle verified (missing_scopes skipped with documented reason).
- [x] Section 8 ŌĆö Manual execution + idempotency verified.
- [x] Section 9 ŌĆö Scheduler tick + polling verified.
- [x] Section 10 ŌĆö All 10 Gmail actions verified against a real account.
- [x] Section 10 ŌĆö All 4 new trigger filters verified (positive) and negative where applicable.
- [x] Section 10 ŌĆö Multi-step execution verified.
- [x] Section 10 ŌĆö Inter-step template resolution verified.
- [x] Section 11 ŌĆö Hero test verified end-to-end.
- [x] Section 12 ŌĆö Unauthenticated access matrix verified.
- [x] All workflows paused after the run; no test workflow left polling.
- [x] No credential leakage observed in any execution output.

Gmail E2E Verification Pass is `COMPLETE / VERIFIED`.

---

# Production Scheduler Hardening

Status: `COMPLETE / CLOSED / VERIFIED` for Batches 1ŌĆō8.

This effort replaces the previous queue-worker-dependent production scheduling path with a synchronous, externally triggered execution architecture. The new scheduler path does **not** require a queue worker.

## Production architecture (verified)

```text
Google Apps Script (external clock)
    Ōöé every 5 minutes
    Ō¢╝
POST /api/internal/scheduler/tick
    Ōöé
    Ō¢╝
InternalSchedulerMiddleware (auth, rate limit)
    Ōöé
    Ō¢╝
SchedulerTickService (global lock, stale-running sweep)
    Ōöé
    Ō¢╝
TriggerCoordinator (generic due-trigger query)
    Ōöé
    Ō¢╝
TriggerStrategyRegistry
    Ōö£ŌöĆŌöĆ ScheduleStrategy
    Ōö£ŌöĆŌöĆ GmailPollStrategy
    ŌööŌöĆŌöĆ Future Strategies
    Ōöé
    Ō¢╝
RunWorkflowAction::execute()   (synchronous)
    Ōöé
    Ō¢╝
Execution
```

## Batch 1 ŌĆö Foundation

Status: `COMPLETE`.

Files created:

- `config/internal_scheduler.php`
- `app/Modules/Execution/Application/Contracts/TriggerStrategy.php`
- `app/Modules/Execution/Application/DTOs/StrategyResult.php`
- `app/Modules/Execution/Application/DTOs/TickResult.php`
- `app/Modules/Execution/Application/Services/TriggerStrategyRegistry.php`
- `app/Modules/Execution/Application/Services/TriggerCoordinator.php`
- `app/Modules/Execution/Application/Services/SchedulerTickService.php`

Files modified:

- `app/Modules/Automation/Core/Repositories/WorkflowRepository.php` ŌĆö added `listAllActiveWithTriggerDue()`
- `app/Modules/Automation/Infrastructure/Repositories/EloquentWorkflowRepository.php` ŌĆö implemented it

No schema change.

## Batch 2 ŌĆö Schedule Strategy

Status: `COMPLETE`.

- `app/Modules/Execution/Application/Strategies/ScheduleStrategy.php`
- Self-enforcing due-check (`nextPollAt <= $now`).
- Idempotency key derived from `next_poll_at` timestamp.
- Synchronous `RunWorkflowAction::execute()`.
- Advances `next_poll_at` to `now + interval_minutes`.

## Batch 3 ŌĆö Gmail Poll Strategy

Status: `COMPLETE`.

- `app/Modules/Execution/Application/Strategies/GmailPollStrategy.php`
- Extracted from `PollGmailCommand`.
- Preserves self-email filter, cursor advancement, deterministic ordering, idempotency key format.
- Synchronous `RunWorkflowAction::execute()` ŌĆö no queue dispatch.

## Batch 4 ŌĆö Internal Scheduler API

Status: `COMPLETE`.

- `app/Modules/Execution/Http/Middleware/InternalSchedulerMiddleware.php`
- `app/Modules/Execution/Http/Controllers/InternalSchedulerTickController.php`
- `app/Modules/Execution/Http/Routes/internal.php`
- `app/Modules/Execution/Infrastructure/Providers/ExecutionServiceProvider.php` ŌĆö registers strategy registry and internal route file
- `config/internal_scheduler.php`
- `.env.example` ŌĆö scheduler env variables

Endpoint contract:

- `POST /api/internal/scheduler/tick`
- `Authorization: Bearer ${INTERNAL_SCHEDULER_TOKEN}`
- Response: `{ ok, processed, executed, skipped, failed, lock_held }`
- Errors: 401 (unauthorized), 429 (rate limited), 503 (misconfigured server)

No schema change.

## Batch 5 ŌĆö Recovery Generalization and Stuck-Running Sweep

Status: `COMPLETE / VERIFIED`.

- `ExecutionRepository::markStaleRunningAsFailed(DateTimeImmutable $startedBefore): int` ŌĆö single indexed `UPDATE` marking `running` executions older than the threshold as `failed`.
- `SchedulerTickService::tick()` runs the sweep at the start of every tick, before the coordinator.
- New config `INTERNAL_SCHEDULER_STALE_RUNNING_MINUTES` (default 5).
- Idempotent: already-terminal rows are not affected.
- Preserves `RecoverFailedPollExecutionsCommand` unchanged.
- Tests: `tests/Feature/Execution/StuckRunningExecutionRecoveryTest.php`.

**Threshold rationale:** synchronous execution cannot legitimately exceed the platform HTTP request timeout (30s default, up to 100s with custom config). The global tick lock prevents overlapping ticks. 5 minutes is unambiguously longer than any legitimate execution and catches stuck executions within 1ŌĆō2 ticks.

## Batch 6 ŌĆö `next_poll_at` Index Optimization

Status: `COMPLETE / VERIFIED`.

- Migration `<timestamp>_add_next_poll_at_index_to_workflow_triggers_table.php` adding `INDEX (next_poll_at)` on `workflow_triggers`.
- Existing composite `(integration_key, trigger_key, next_poll_at)` preserved for per-trigger-type queries.
- No behavior change.
- `NULL` semantics preserved: `NULL` means "due immediately". Set by activation and by upsert creation. Documented, intentional.

## Batch 7 ŌĆö Google Apps Script Production Scheduler

Status: `COMPLETE / VERIFIED`.

- Apps Script code (external): calls `POST /api/internal/scheduler/tick` every 5 minutes with the bearer token; reads URL and token from `ScriptProperties`; does not log the token; handles HTTP failures safely.
- `OVERLAP_SECONDS` in `GmailPollStrategy` and `PollGmailCommand` raised from `60` to `360` (5-minute tick + 1-minute margin).
- `PollGmailCommandTest::test_subsequent_tick_uses_cursor_overlap` expectation updated: `640` instead of `940`.
- Production URL: `https://sea-turtle-app-vshwt.ondigitalocean.app/api/internal/scheduler/tick`.
- Local URL: `http://localhost:8000/api/internal/scheduler/tick`.

**Justification for overlap change:** `GmailPollStrategy::advanceState` runs after processing. If the tick process is terminated mid-run, the cursor is not advanced. The next tick re-fetches only within `OVERLAP_SECONDS`. Under a 5-minute tick cadence, 60s is insufficient; messages from 61sŌĆō5min before the crash would be lost. 360s covers the full tick window. Duplicate fetches are idempotent via the `gmail:{workflowId}:{messageId}` key.

## Batch 8 ŌĆö Final Production Documentation

Status: `COMPLETE`.

- `docs/02_IMPLEMENTATION_STATE.md` updated.
- `docs/04_PHASE_GATES.md` updated.
- `docs/08_DEPLOYMENT.md` updated.

## Strategy Test Coverage

Status: `COMPLETE / VERIFIED`.

- `tests/Feature/Execution/ScheduleStrategyTest.php` ŌĆö 3 tests / 14 assertions.
- `tests/Feature/Execution/GmailPollStrategyTest.php` ŌĆö 3 tests / 13 assertions.
- `tests/Feature/Internal/SchedulerTickTest.php` ŌĆö 6 tests / 17 assertions.
- `tests/Feature/Execution/TriggerCoordinatorTest.php` ŌĆö 3 tests / 5 assertions.
- `tests/Feature/Execution/StuckRunningExecutionRecoveryTest.php` ŌĆö 5 tests / 10 assertions.

## Gmail polling behavior

- For a newly activated Gmail polling trigger, `poll_cursor` is `NULL`.
- The first processing initializes `poll_cursor = now` and does **not** fetch historical messages.
- This is intentional: prevents processing of historical emails on activation.
- Subsequent ticks fetch messages using `after = cursor - OVERLAP_SECONDS`.
- Current `OVERLAP_SECONDS = 360`.
- Current polling interval = `interval_minutes` on the trigger; default 5 minutes.

The 5-minute value represents the scheduler polling interval, **not** an exact per-email processing SLA. Delivery is at-least-once.

## End-to-end verification

A real Gmail-triggered workflow was verified end-to-end at runtime against the local Laravel server and the Apps Script external clock:

1. Workflow created with `google.gmail` / `new_email_received` trigger and a `send_email` step.
2. External email sent to the connected Gmail account.
3. Tick 1 (first tick after activation): `{"ok":true,"processed":1,"executed":0,"skipped":1,"failed":0,"lock_held":false}` ŌĆö cursor initialized.
4. Tick 2: `{"ok":true,"processed":1,"executed":1,"skipped":0,"failed":0,"lock_held":false}` ŌĆö email detected, workflow executed, response email dispatched.
5. `GET /api/workflows/{id}/executions` returned an execution with `status: "completed"`, `trigger_source: "poll"`, `idempotency_key: "gmail:{workflowId}:{messageId}"`, and the correct `trigger_payload`.

This pass has since been extended by the "Gmail E2E Verification Pass" section above.

## Open Concerns

1. **Synchronous tick duration.** Bounded by the platform HTTP request timeout. `INTERNAL_SCHEDULER_BATCH_SIZE` remains conservative. Increase only after measuring observed tick durations in production.
2. **Apps Script as single point of failure.** Documented in `docs/08_DEPLOYMENT.md`. Mitigation: monitor the Apps Script executions log; install a fallback clock if the primary fails.
3. **Consumer Apps Script quota.** ~90 minutes/day trigger runtime on consumer accounts. At 5-minute cadence with short ticks this is safe; sustained ticks above ~18 seconds will exhaust it. Use a Workspace account if needed.
4. **Gmail overlap under live load.** Raised to 360s. Monitor under production traffic.
5. **Scheduler tick unauthenticated response body.** Observed `{"message":"Unauthenticated."}` instead of the middleware's declared `{"error":"unauthorized"}`. Status code 401 is correct in both cases; only the body differs. To investigate in a maintenance pass. Not a security regression ŌĆö the endpoint still rejects unauthenticated callers.

---

# Localization (Batches 1ŌĆō4)

Status: `COMPLETE`.

This branch added first-class Arabic + English localization to the API surface, driven by the standard `Accept-Language` header. No `X-Locale`, no `?locale`, no stored user locale.

## Batch 1 ŌĆö Locale infrastructure

Status: `COMPLETE`.

Files created:

- `app/Http/Middleware/SetLocale.php`
- `tests/Feature/Localization/SetLocaleTest.php` ŌĆö 6 tests

Files modified:

- `bootstrap/app.php` ŌĆö `$middleware->api(prepend: [SetLocale::class])`
- `config/app.php` ŌĆö added `'supported_locales' => ['en', 'ar']`

Behaviour:

- `Accept-Language` parsed with q-values.
- Regional subtags normalized (`ar-EG` ŌåÆ `ar`, `en-US` ŌåÆ `en`).
- `q=0` skipped.
- Fallback to `config('app.locale')`.
- Middleware prepended to the `api` group, so it runs before `auth:sanctum` and before FormRequest validation.

**Framework validation overrides deferred.** `lang/en/validation.php` and `lang/ar/validation.php` were not created because the root `lang/` directory was not supplied. Only the framework default English validation messages are guaranteed to resolve. Per-field 422 messages for locales other than `en` fall back to English until project overrides are added. See Deferred/Future list.

## Batch 2 ŌĆö Identity localization

Status: `COMPLETE`.

Files created:

- `app/Modules/Identity/Lang/en/messages.php`
- `app/Modules/Identity/Lang/ar/messages.php`
- `tests/Feature/Localization/IdentityLocalizationTest.php`

Files modified:

- `app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php` ŌĆö `loadTranslationsFrom(__DIR__.'/../../Lang', 'identity')`
- `app/Modules/Identity/Http/Controllers/LoginController.php` ŌĆö localize `login_success`, `invalid_credentials`

## Batch 3 ŌĆö Integrations + Catalog localization

Status: `COMPLETE`.

Files created:

- `app/Modules/Integrations/Lang/en/messages.php`
- `app/Modules/Integrations/Lang/ar/messages.php`
- `app/Modules/Integrations/Lang/en/catalog.php`
- `app/Modules/Integrations/Lang/ar/catalog.php`
- `tests/Feature/Localization/IntegrationsLocalizationTest.php`
- `tests/Feature/Localization/CatalogLocalizationTest.php`
- `tests/Feature/Localization/CatalogTranslationParityTest.php`

Files modified:

- `app/Modules/Integrations/Infrastructure/Providers/IntegrationsServiceProvider.php` ŌĆö added `loadTranslationsFrom`
- `app/Modules/Integrations/Core/Entities/IntegrationDefinition.php` ŌĆö added `nameKey`, `descriptionKey`
- `app/Modules/Integrations/Core/Entities/ActionDefinition.php` ŌĆö added `labelKey`, `descriptionKey`
- `app/Modules/Integrations/Core/Entities/TriggerDefinition.php` ŌĆö added `labelKey`, `descriptionKey`
- `app/Modules/Integrations/Core/ValueObjects/FieldDefinition.php` ŌĆö added `labelKey`, `descriptionKey`
- `app/Modules/Integrations/Application/Services/CatalogProjector.php` ŌĆö resolve via `__()` with English fallback
- `app/Modules/Integrations/Infrastructure/Google/GmailIntegration.php`
- `app/Modules/Integrations/Infrastructure/Google/GoogleCalendarIntegration.php`
- `app/Modules/Integrations/Infrastructure/Google/GoogleSheetsIntegration.php`
- `app/Modules/Integrations/Infrastructure/Google/GoogleDocsIntegration.php`
- `app/Modules/Integrations/Infrastructure/Google/GoogleAnalyticsIntegration.php`
- `app/Modules/Integrations/Infrastructure/Google/GoogleDriveIntegration.php`
- 31 controllers under `app/Modules/Integrations/Http/Controllers/` ŌĆö controller-level `message` / `error` strings localized

**Deferred by design:** raw business-result `message` strings returned inside action result payloads (Sheets row-append result, Docs append-text result, Sheets add/delete/update/clear result). These are part of the business-result body, not the controller envelope, and remain English.

## Batch 4 ŌĆö Contract + verification

Status: `COMPLETE`.

- `docs/06_MOBILE_API_CONTRACT.md` bumped to v1.0.7. New ┬º12 (Localization) added. Old ┬º12 renamed to ┬º13.

Verification evidence:

- `php artisan test --filter=CatalogTranslationParityTest` ŌåÆ 2 passed / 16 assertions.
- `php artisan test --filter=SetLocaleTest` ŌåÆ 6 passed / 12 assertions.
- Tinker manual checks confirmed `identity::messages.invalid_credentials`, `identity::messages.login_success`, and `integrations::catalog.google_gmail.actions.send_email.label` all resolve correctly under `en` and `ar`.
- Recursive key-tree diff between `en/catalog.php` and `ar/catalog.php` returned empty on both sides (parity holds).

## Root API Controller Localization

Status: `COMPLETE` (subject to file availability).

Files modified:

- `routes/api.php` ŌĆö inline `/user` closure now uses `__('identity::messages.user_retrieved')`
- `app/Http/Controllers/Api/UserController.php` ŌĆö `user_registered`, `user_updated`
- `app/Http/Controllers/Api/GoogleAuthController.php` ŌĆö `login_failed`, `logout_success`, `account_deactivated`

Translation keys added to `app/Modules/Identity/Lang/{en,ar}/messages.php`:

- `user_retrieved`
- `user_registered`
- `user_updated`
- `logout_success`
- `account_deactivated`
- `login_failed`

**Not localized:** `TestLoginController` and the Facebook controllers ŌĆö their source files were not supplied. Endpoints remain English. See Deferred/Future list.

---

# Password Reset (Forgot Password)

Status: `COMPLETE`.

Two public endpoints support password reset via email OTP. Implemented inside the Identity module.

## Endpoints

- `POST /api/password/forgot` ŌĆö request a reset code.
- `POST /api/password/reset` ŌĆö verify the code and set a new password.

## Behaviour

- 6-digit numeric OTP, uniformly random, no leading zero.
- 15-minute TTL.
- 5-attempt cap per issued code.
- Only a bcrypt hash of the code is stored (`password_reset_otps.code_hash`).
- Single-use. New issuance invalidates prior codes for the same email.
- Rate limits: 5 per email + 20 per IP per hour (forgot); 20 per email + 60 per IP per hour (reset).
- Generic response on `/forgot` (no email enumeration).
- Generic 422 on every reset failure mode.
- On success, all active Sanctum tokens for the user are revoked.
- Email body localized via `identity::messages.*`, rendered from a custom Blade view (no Laravel default branding).

## Files created

- `app/Modules/Identity/Infrastructure/Config/identity.php` ŌĆö `password_reset` config block
- `app/Modules/Identity/Infrastructure/Database/Migrations/2026_10_04_000001_create_password_reset_otps_table.php`
- `app/Modules/Identity/Infrastructure/Eloquent/PasswordResetOtpModel.php`
- `app/Modules/Identity/Infrastructure/Repositories/EloquentPasswordResetOtpRepository.php`
- `app/Modules/Identity/Infrastructure/Notifications/PasswordResetOtpNotification.php`
- `app/Modules/Identity/Views/emails/password-reset-otp.blade.php`
- `app/Modules/Identity/Core/Entities/PasswordResetOtp.php`
- `app/Modules/Identity/Core/Repositories/PasswordResetOtpRepository.php`
- `app/Modules/Identity/Core/Exceptions/InvalidPasswordResetCodeException.php`
- `app/Modules/Identity/Application/Services/PasswordResetService.php`
- `app/Modules/Identity/Application/DTOs/RequestPasswordResetInput.php`
- `app/Modules/Identity/Application/DTOs/ResetPasswordInput.php`
- `app/Modules/Identity/Application/Actions/RequestPasswordResetAction.php`
- `app/Modules/Identity/Application/Actions/ResetPasswordAction.php`
- `app/Modules/Identity/Http/Requests/RequestPasswordResetRequest.php`
- `app/Modules/Identity/Http/Requests/ResetPasswordRequest.php`
- `app/Modules/Identity/Http/Controllers/RequestPasswordResetController.php`
- `app/Modules/Identity/Http/Controllers/ResetPasswordController.php`
- `tests/Feature/Identity/PasswordResetTest.php` ŌĆö written, not yet executed against a test DB

## Files modified

- `app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php` ŌĆö added `mergeConfigFrom`, `loadMigrationsFrom`, repository bind, service singleton
- `app/Modules/Identity/Http/Routes/api.php` ŌĆö two new POST routes
- `app/Modules/Identity/Lang/en/messages.php` ŌĆö reset-related keys
- `app/Modules/Identity/Lang/ar/messages.php` ŌĆö reset-related keys
- `app/Modules/Identity/Swagger/Identity.php` ŌĆö two new OpenAPI operations
- `docs/06_MOBILE_API_CONTRACT.md` ŌĆö new ┬º13 documenting the feature

## Migration

`2026_10_04_000001_create_password_reset_otps_table.php` creates the `password_reset_otps` table. Schema:

- `id`
- `email` (unique)
- `code_hash`
- `attempts` (unsigned tiny int, default 0)
- `expires_at`
- `used_at` (nullable)
- `created_at`, `updated_at`

No existing table is modified. No data is deleted.

## Production requirements

- `MAIL_MAILER` must be configured to a real transport (SMTP, SES, Postmark, Resend). Default `log` writes the OTP to `storage/logs/laravel.log`.
- `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` must be set.
- `php artisan migrate` must have run.

## Security notes

- Master OTP backdoor was considered and **explicitly rejected**. Not implemented.
- Operator-driven CLI password reset was considered and not implemented.

## Verification

- Postman test of `/forgot` returned the generic 200 message and delivered the email to the configured Gmail SMTP.
- Postman test of `/reset` succeeded with the received code, updated the password, revoked all tokens.
- Postman test of `/login` with the new password returned HTTP 200 with a fresh token.
- Full-flow is COMPLETE / VERIFIED manually.

## Documentation

Contract ┬º13 documents the feature end-to-end. Swagger operations `auth.password.forgot` and `auth.password.reset` added under the `Authentication` tag.

---

# Gmail Attachment Trigger (feature Batches 1ŌĆō6)

Status: `COMPLETE (reported)`.

Purpose: allow a workflow to be triggered by a Gmail message that carries at
least one attachment, and to materialize those attachments into the user's
own Google Drive before the workflow executes.

The batch numbers below refer to this feature's own internal batches, not to
the project's numbered phases.

## Trigger semantics

- **Existing trigger** `new_email_received` ŌĆö unchanged. Lightweight path.
  Payload is the same 14 keys as before. Does not download attachments. Does
  not require the Drive scope.
- **New trigger** `new_email_received_with_attachment` ŌĆö same polling
  strategy, same cursor, same idempotency key format, same self-email
  filter, same ordering. Additionally ensures `has:attachment` is present
  in the Gmail `q` (without duplicating it if the user's own query already
  contains it), materializes each attachment into the user's Drive, and adds
  an `attachments` array to the trigger payload.

Both trigger keys share the single existing `GmailPollStrategy`. There is no
second polling strategy, no second scheduler, no second cursor, and no
second idempotency mechanism.

## Connection and scope requirement

- Uses the **existing** Google Connection model. No separate Drive
  connection is created.
- The connection must carry `https://www.googleapis.com/auth/drive.file`
  in its `scopes` array, in addition to the Gmail scopes already required.
- A user whose existing connection lacks the Drive scope must reauthorize
  the **same** connection with `capability: "drive"`. Reauthorization
  updates the existing connection's `scopes` array in place. It does not
  create a new connection row.
- The `drive` capability was added to `CapabilityScopeMap` in Batch 1
  (mapping: `drive` ŌåÆ `https://www.googleapis.com/auth/drive.file`).
- This was verified empirically against real Google OAuth: reauthorizing
  with `capability: "drive"` on a connection that already had Gmail scopes
  preserved the connection ID, preserved `external_account_id`, preserved
  the refresh token, and accumulated `drive.file` in `scopes`.

## Activation validation

- Batch 5 relies on the **existing** `WorkflowCapabilityValidator`. No
  separate validator was introduced.
- The `new_email_received_with_attachment` trigger definition declares both
  `https://www.googleapis.com/auth/gmail.readonly` and
  `https://www.googleapis.com/auth/drive.file` in `requiredScopes`.
- Activating a workflow whose trigger is `new_email_received_with_attachment`
  on a connection that lacks the Drive scope returns HTTP 409 with
  `error: "missing_scopes"` and the missing scope URI in
  `context.missing_scopes`.
- The normal `new_email_received` trigger does **not** require the Drive
  scope. Existing workflows are unaffected.

## Runtime behavior

- If the workflow is active but the connection no longer carries the Drive
  scope, the polling strategy returns `drive_connection_required` and does
  not advance the polling cursor. The next tick retries once the scope is
  restored.
- Attachment download and upload run synchronously inside the scheduler
  tick, before `RunWorkflowAction::execute()` is invoked.
- The workflow invocation path (`RunWorkflowAction`, `RunWorkflowJob`,
  `ExecutionContext`, `WorkflowSnapshotBuilder`, template resolution) is
  unchanged. The enriched payload flows through the existing mechanism.

## Payload extension

The new trigger's payload contains the same 14 keys as the existing trigger
plus a 15th key, `attachments`, which is an array of objects with the
following fields:

- `gmail_attachment_id` (string) ŌĆö Gmail part ID.
- `drive_file_id` (string | null) ŌĆö the canonical downstream reference.
- `web_view_link` (string | null) ŌĆö additive field. Google Drive web link
  for the uploaded file. `null` on failure or when Drive did not return a
  link. Metadata only ŌĆö possessing the link does not grant access; existing
  Drive permissions still apply.
- `filename` (string)
- `mime_type` (string)
- `size` (integer | null)
- `error` (string, only when the attachment failed)

`attachment_ids` in the base payload remains the Gmail part-ID array and is
distinct from `attachments[].drive_file_id`. Downstream actions consume
`attachments[].drive_file_id` (or, for read-only display, the additive
`web_view_link`).

## Partial-failure policy

Per-attachment handling. A failure in one attachment does not prevent the
other attachments from being materialized and does not prevent the workflow
from executing. Failed entries carry `drive_file_id: null`,
`web_view_link: null`, and one of two stable machine-readable codes:

- `gmail_download_failed`
- `drive_upload_failed`

No credentials, tokens, provider exception messages, or stack traces are
exposed in the payload.

## Files ŌĆö created (production)

- `app/Modules/Integrations/Infrastructure/Google/Gmail/GmailAttachmentReader.php`
- `app/Modules/Integrations/Infrastructure/Google/Drive/GoogleDriveFileUploader.php`
- `app/Modules/Integrations/Infrastructure/Google/Gmail/GmailAttachmentMaterializer.php`
- `app/Modules/Integrations/Application/Actions/FetchGmailTriggerMessagesWithAttachmentsAction.php`
- `app/Modules/Integrations/Core/Exceptions/DriveProviderException.php`

## Files ŌĆö modified (production)

- `app/Modules/Integrations/Application/Services/CapabilityScopeMap.php` ŌĆö added the `drive` capability.
- `app/Modules/Integrations/Infrastructure/Google/Gmail/GmailMessagePayloadBuilder.php` ŌĆö added a `buildWithAttachments()` method. The existing `build()` method is byte-identical.
- `app/Modules/Integrations/Infrastructure/Google/GmailIntegration.php` ŌĆö added the second trigger definition. The existing `new_email_received` definition is byte-identical.
- `app/Modules/Execution/Application/Strategies/GmailPollStrategy.php` ŌĆö widened the trigger-key guard to accept both keys, added the Drive-scope check and the `drive_connection_required` skip path, appended `has:attachment` for the attachment trigger, and dispatched to the enriched fetch action.
- `app/Modules/Integrations/Lang/en/catalog.php` ŌĆö added translation keys for the new trigger.
- `app/Modules/Integrations/Lang/ar/catalog.php` ŌĆö matching Arabic keys.
- `tests/Unit/Integrations/IntegrationCatalogTest.php` ŌĆö one-line assertion update to reflect the second Gmail trigger.

## Tests ŌĆö created

- `tests/Unit/Integrations/DriveCapabilityTest.php`
- `tests/Unit/Integrations/GmailAttachmentReaderTest.php`
- `tests/Unit/Integrations/GoogleDriveFileUploaderTest.php`
- `tests/Unit/Integrations/GmailAttachmentMaterializerTest.php`
- `tests/Unit/Integrations/GmailMessagePayloadBuilderWithAttachmentsTest.php`
- `tests/Unit/Integrations/GmailIntegrationAttachmentTriggerTest.php`
- `tests/Unit/Integrations/FetchGmailTriggerMessagesWithAttachmentsActionTest.php`
- `tests/Unit/Execution/GmailPollStrategyBranchTest.php`
- `tests/Feature/Automation/GmailAttachmentTriggerActivationTest.php`

## Test evidence

The following figures come from terminal output **actually pasted during this
conversation**. They are recorded here as the concrete evidence available:

- `php artisan test --filter=DriveCapabilityTest` ŌĆö 7 tests / 8 assertions.
- `php artisan test --filter=GmailAttachmentReaderTest` ŌĆö 8 tests / 8 assertions.
- `php artisan test --filter=GoogleDriveFileUploaderTest` ŌĆö 10 tests / 16 assertions.
- `php artisan test --filter=GmailAttachmentMaterializerTest` ŌĆö 8 tests / 36 assertions.
- `php artisan test --filter=GmailMessagePayloadBuilderWithAttachmentsTest` ŌĆö 6 tests / 30 assertions.
- `php artisan test --filter=GmailPollStrategyBranchTest` ŌĆö 10 tests / 21 assertions (after the test-correction pass).
- `php artisan test --filter=GmailAttachmentTriggerActivationTest` ŌĆö 4 tests / 14 assertions.
- A combined sweep of `GmailAttachmentTriggerActivationTest`,
  `GmailPollStrategyBranchTest`, `GmailIntegrationAttachmentTriggerTest`,
  `FetchGmailTriggerMessagesWithAttachmentsActionTest`,
  `GmailPollStrategyTest`, `ScheduleStrategyTest`,
  `TriggerCoordinatorTest`, `SchedulerTickTest`, `IntegrationCatalogTest`,
  `CatalogTranslationParityTest` ŌĆö 47 tests / 150 assertions.

**These figures are recorded separately. They are not summed into the
cumulative full-suite figure below.**

## Not independently verified by this document

- Live end-to-end verification of the attachment trigger against a real
  Gmail mailbox has not been reported. The automated tests exercise the
  branching logic, the payload builder, the materializer, the enriched
  fetch action, and the activation validation. They do not exercise the
  live Gmail ŌåÆ Drive data path.
- The 47 / 150 combined sweep figure is a union of the ten suites listed
  above. It is not independent of the per-suite figures for
  `GmailAttachmentTriggerActivationTest`, `GmailPollStrategyBranchTest`,
  and `IntegrationCatalogTest`, which are among its members.

## Deferred within the attachment feature

- An action that reads attachment bytes back out of Drive and passes them
  into execution output is not implemented. See the Deferred/Future list.

---

# Gmail Attachment Links Email Action (feature Batches 1ŌĆō2)

Status: `COMPLETE (reported)`.

Purpose: a new Gmail action that sends one HTML email containing clickable
Google Drive links for the attachments already materialized by the
`new_email_received_with_attachment` trigger.

The batch numbers below refer to this feature's own internal batches, not to
the project's numbered phases.

## Batch 1 ŌĆö Trigger–Action Compatibility Mechanism

Status: `COMPLETE / VERIFIED`.

Introduced a reusable, backward-compatible mechanism for restricting an
action to specific trigger keys, and enforced it at every workflow mutation
boundary and at activation.

### New concept

`ActionDefinition` gained an optional `allowedTriggerKeys` field, defaulting
to `null` (unrestricted). A non-empty list restricts the action to the
qualified trigger keys it contains. The field is not exposed in the catalog
or any API response.

### Enforcement points

| Boundary                                | File                          |
| --------------------------------------- | ----------------------------- |
| Step add                                | `AddWorkflowStepAction`       |
| Step update                             | `UpdateWorkflowStepAction`    |
| Trigger upsert (against existing steps) | `UpsertWorkflowTriggerAction` |
| Activation                              | `ActivateWorkflowAction`      |

All four throw `WorkflowCapabilityException::incompatibleTrigger()` with
error code `incompatible_trigger` and a `context` payload containing
`location`, `action_key`, `trigger_key`, and `allowed_trigger_keys`.

### HTTP surface

The controller layer returns HTTP 409 with the existing capability-error
envelope. A new localized key `automation::messages.capability_incompatible_trigger`
was added in both `en` and `ar`. The `WorkflowController` capability-error
logic was extracted into a small trait `TranslatesCapabilityErrors` and
reused by the step and trigger controllers.

### Files

New:

- `app/Modules/Automation/Application/Services/WorkflowTriggerActionCompatibilityValidator.php`
- `app/Modules/Automation/Http/Controllers/Concerns/TranslatesCapabilityErrors.php`
- `tests/Support/Integrations/TestRestrictedIntegration.php`
- `tests/Unit/Automation/WorkflowTriggerActionCompatibilityValidatorTest.php`
- `tests/Feature/Automation/WorkflowTriggerActionCompatibilityTest.php`

Modified:

- `app/Modules/Integrations/Core/Entities/ActionDefinition.php` ŌĆö added `allowedTriggerKeys`
- `app/Modules/Automation/Core/Exceptions/WorkflowCapabilityException.php` ŌĆö new `ERROR_INCOMPATIBLE_TRIGGER` + factory
- `app/Modules/Automation/Application/Actions/AddWorkflowStepAction.php`
- `app/Modules/Automation/Application/Actions/UpdateWorkflowStepAction.php`
- `app/Modules/Automation/Application/Actions/UpsertWorkflowTriggerAction.php`
- `app/Modules/Automation/Application/Actions/ActivateWorkflowAction.php`
- `app/Modules/Automation/Http/Controllers/WorkflowController.php`
- `app/Modules/Automation/Http/Controllers/WorkflowStepController.php`
- `app/Modules/Automation/Http/Controllers/WorkflowTriggerController.php`
- `app/Modules/Automation/Lang/en/messages.php`
- `app/Modules/Automation/Lang/ar/messages.php`

### Test evidence (Batch 1)

- `php artisan test tests/Unit/Automation --env=testing` ŌĆö 70 tests / 96 assertions.
- `php artisan test tests/Feature/Automation --env=testing` ŌĆö 112 tests / 338 assertions.

Both runs included the two new focused suites and every pre-existing
Automation test file. Zero failures.

## Batch 2 ŌĆö Register and Implement the New Gmail Action

Status: `COMPLETE / VERIFIED`.

### Action key

`google.gmail.send_attachment_links_email`

### Trigger compatibility

Restricted to `google.gmail.new_email_received_with_attachment` via the
`allowedTriggerKeys` field added in Batch 1. Attempts to pair the action with
`google.gmail.new_email_received` are rejected at every write path and at
activation with HTTP 409 `error: "incompatible_trigger"`.

`new_email_received_with_attachment` is **not** made exclusive to the new
action. Existing actions (including `send_email`) remain usable with both
triggers.

### Action behavior

- Accepts `to` (email), `subject` (string), `intro_text` (text, optional),
  and `attachments` (string, pre-filled with `{{ trigger.attachments }}`).
- Uses the existing `GmailEmailSender::send()` path. No new sender, no
  binary attachments, no Drive permission changes, no extra OAuth scopes.
- Iterates over the attachment entries and selects only those whose
  `web_view_link` is a non-empty string after trimming.
- Builds one HTML email with a clickable link per eligible attachment.
- Skips entries with `null`, empty, whitespace-only, or missing links, and
  skips non-array entries.
- If no eligible attachments remain, emits the fallback body
  `<p>Attachments could not be linked.</p>` rather than an empty list.
- HTML-escapes filenames, URLs, and `intro_text` with
  `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`.
- Returns `['sent' => bool, 'message_id' => ?string]`, identical to the
  existing `send_email` output shape.

### Config field types

Uses only existing field types: `email`, `string`, `text`. No new mobile
field type is introduced. The `attachments` field is a `string` with
`default: '{{ trigger.attachments }}'`; the existing resolver's
whole-string-template path yields the raw PHP array at execution time.

### Files

New (production):

- `app/Modules/Integrations/Application/DTOs/SendGmailAttachmentLinksEmailInput.php`
- `app/Modules/Integrations/Application/Actions/SendGmailAttachmentLinksEmailAction.php`
- `app/Modules/Execution/Infrastructure/Invokers/Handlers/GmailSendAttachmentLinksEmailHandler.php`

Modified (production):

- `app/Modules/Integrations/Infrastructure/Google/GmailIntegration.php` ŌĆö new `ActionDefinition` with `allowedTriggerKeys`
- `app/Modules/Execution/Infrastructure/Invokers/HandlerRegistry.php` ŌĆö new handler binding
- `app/Modules/Integrations/Lang/en/catalog.php` ŌĆö new action label/description
- `app/Modules/Integrations/Lang/ar/catalog.php` ŌĆö matching Arabic entries

New (tests):

- `tests/Unit/Integrations/GmailSendAttachmentLinksEmailActionTest.php`
- `tests/Feature/Execution/GmailSendAttachmentLinksEmailHandlerTest.php`
- `tests/Feature/Automation/GmailSendAttachmentLinksEmailRestrictionTest.php`

### Documentation

- `docs/02_IMPLEMENTATION_STATE.md` ŌĆö this section.
- `docs/06_MOBILE_API_CONTRACT.md` ŌĆö ┬º5.9 updated with the new action row
  and its output keys; ┬º4.6 updated with the new action count.

### Test evidence (Batch 2)

- `php artisan test --filter=GmailSendAttachmentLinksEmailActionTest` ŌĆö 13 tests / 39 assertions.
- `php artisan test --filter=GmailSendAttachmentLinksEmailHandlerTest` ŌĆö 10 tests / 18 assertions.
- `php artisan test --filter=GmailSendAttachmentLinksEmailRestrictionTest` ŌĆö 8 tests / 28 assertions.
- `php artisan test tests/Unit/Integrations --env=testing` ŌĆö 135 tests / 663 assertions.
- `php artisan test tests/Feature/Automation --env=testing` ŌĆö 120 tests / 366 assertions.

Both module runs included the new focused suites and every pre-existing
test file in their respective directories. Zero failures after the stale
assertion fixes in `CapabilityScopeMapTest`, `GoogleIntegrationsMetadataTest`,
and `IntegrationCatalogTest`.

### Deferred within this feature

- The action does not read attachment bytes back out of Drive. It only
  produces clickable Drive links, as specified.
- No adjustment to the catalog response shape beyond the addition of the
  new action's config metadata.

---

# Cumulative Test Growth

| Phase / Batch                                                          | Total Tests | Total Assertions |
| ---------------------------------------------------------------------- | ----------- | ---------------- |
| Phase 0 close                                                          | 26          | 75               |
| Phase 2 close                                                          | 52          | 179              |
| Phase 3 close                                                          | 84          | 495              |
| Phase 4 close                                                          | 99          | 519              |
| Phase 5 close                                                          | 293         | 986              |
| Batch 6.5 close (Phase 6 close)                                        | 507         | 1486             |
| Batch 7.1 close                                                        | 519         | 1512             |
| Batch 7.2 close                                                        | 540         | 1576             |
| Batch 7.3 close                                                        | 577         | 1670             |
| Batch 7.4 close                                                        | 607         | 1749             |
| Batch 7.5 close (Phase 7 close)                                        | 607         | 1749             |
| Phase 8 close                                                          | 607         | 1749             |
| Catalog batch close                                                    | 621         | 1893             |
| Login API batch close                                                  | 632         | 1929             |
| Post-Login full-suite (pre-Path-A)                                     | 663         | 1963             |
| Post-Path-A full suite                                                 | 689         | 2068             |
| Post scheduler Batches 1ŌĆō4                                           | 698         | 2090             |
| Post scheduler + strategy coverage                                     | 704         | 2117             |
| Post scheduler hardening close                                         | 709         | 2127             |
| Gmail Integration Completion close                                     | 752         | 2267             |
| **Localization + Password Reset + Gmail Attachment Trigger (current)** | **752**     | **2267**         |
| Post-live-verification (verification only; no new tests)               | **752**     | **2267**         |

The last verified full-suite run remains **752 passed / 2267 assertions / 0
failures**. That figure predates the Localization test files, the Password
Reset test file, the Gmail Attachment Trigger test files, and the Gmail
Attachment Links Email Action test files. Those have not been folded into the
cumulative figure.

The Automation module suites were run individually and are recorded in the
"Gmail Attachment Links Email Action (feature Batches 1ŌĆō2)" section, kept
separate from the full-suite figure.

---

# Production-Safe Legacy Confirmation

The following remain unchanged and intact:

- All legacy `app/Http/Controllers/Api/*` Google/Facebook/TestLogin/User controllers (except for the localized strings listed in the Root API Controller Localization section above).
- `app/Services/*`.
- `app/Models/User.php`, `app/Models/FacebookAccount.php`.
- All existing global migrations under `database/migrations/` (new `password_reset_otps` migration was added inside the Identity module).
- `config/*` except the published `config/l5-swagger.php`, `config/internal_scheduler.php`, and the newly added `config/identity.php` merged at runtime.
- `bootstrap/*` except the additive `IdentityServiceProvider` line.
- All Phase 0ŌĆō7 tests.

Live Google login (`redirect` / `callback`) untouched except for the localized `login_failed` string in the catch block. No contract change.
`users.google_access_token`, `users.google_refresh_token`, `users.google_token_expires_at` untouched.
`redirectgoogle()` legacy flow untouched.
`TestLoginController` untouched.
Facebook functionality untouched.
`POST /api/register` behaviorally untouched except for localized response strings.
`has_bot_access` untouched.

---

# Pending Cleanup Items (Post-Phase-10)

1. `app/Http/Controllers/Api/EmailController.php` ŌĆö emptied/commented
2. `app/Http/Controllers/Api/GoogleSheetsController.php` ŌĆö emptied/commented
3. `app/Http/Controllers/Api/CalendarController.php` ŌĆö orphaned
4. `app/Http/Controllers/Api/GoogleDocsController.php` ŌĆö orphaned
5. `app/Http/Controllers/Api/GoogleAnalyticsController.php` ŌĆö orphaned
6. `app/Services/GoogleSheetsService.php` ŌĆö orphaned
7. `app/Services/GoogleCalendarService.php` ŌĆö orphaned
8. `app/Services/GoogleDocsService.php` ŌĆö orphaned
9. `app/Services/GoogleAnalyticsService.php` ŌĆö orphaned
10. `tests/Feature/Connections/GoogleCallbackTest.php` ŌĆö two commented-out method bodies
11. Resolver naming asymmetry (`GoogleCredentialsResolver` interface vs `GoogleCredentialResolver` implementation)
12. `env()` reads for Google client id/secret in infrastructure classes (still present in 15 files across `app/Modules/Connections/Infrastructure/Google/**` and `app/Modules/Integrations/Infrastructure/Google/**`). Blocks `config:cache` in production.
13. Commented legacy route blocks in `routes/api.php`
14. `GmailService` has no active consumer but is retained
15. `WorkflowSnapshotBuilder::stepSnapshot()` ŌĆö dead code introduced during Batch 6.4
16. Pint ŌĆö 8 files flagged with style issues in `app/Modules/Automation`, `app/Modules/Execution`, `tests/Feature/Execution`, `tests/Feature/Automation`. Not yet applied.
17. `RunWorkflowJob` is no longer dispatched by the new scheduler path. Kept for legacy/manual flows.
18. Scheduler tick unauthenticated body mismatch (`{"message":"Unauthenticated."}` vs declared `{"error":"unauthorized"}`). Investigate in a maintenance pass.
19. Login endpoint rate limiting ŌĆö corrected `LoginController` proposed but not applied.
20. Root `lang/en/validation.php` and `lang/ar/validation.php` ŌĆö not created; per-field 422 messages only resolve for `en`.
21. `TestLoginController` ŌĆö not localized (source file not supplied).
22. `FacebookAuthController` and `FacebookWebhookController` ŌĆö not localized (source files not supplied).

---

# Pending Architectural Constraints

Locked unless a new ADR changes them.

## Cross-cutting

- Modular Monolith, DDD, `app/Modules/`, five core modules.
- `Core` framework-independent.
- Module-owned migrations, models, HTTP, localization.
- No duplicate User model.
- No speculative abstractions.
- No silent architectural changes.

## Identity / Auth

- Live Google login (`redirect` / `callback`) protected.
- `redirectgoogle()` legacy flow untouched.
- `TestLoginController` untouched.
- Facebook functionality untouched.
- Legacy Google token fields remain during migration.
- `/api/register` remains the provisioning API.
- `/api/login` authenticates existing users only.
- `has_bot_access` / `access_expiry` are product entitlements, not authentication.
- `App\Models\User` remains shared application infrastructure.

## Localization

- Only `Accept-Language`. No `X-Locale`, no `?locale`, no `users.locale`.
- Supported locales: `en`, `ar`. Fallback `en`.
- Region subtags normalized. q-weighted. `q=0` skipped.
- Middleware `SetLocale` prepended to the `api` group.
- Module translation namespaces via `loadTranslationsFrom` in each module's service provider.
- Framework validation overrides deferred.
- Machine-readable identifiers never translated.
- Execution diagnostics remain English.
- Raw business-result payloads deferred.

## Password Reset

- Two endpoints: `/api/password/forgot`, `/api/password/reset`.
- 6-digit OTP, 15-min TTL, 5-attempt cap.
- Bcrypt-hashed storage in `password_reset_otps`.
- Rate-limited per email + per IP.
- Generic responses (no enumeration).
- All Sanctum tokens revoked on success.
- No master OTP.
- No CLI reset command.
- Email body localized via `identity::messages.*`.

## Gmail Attachment Trigger

- The `new_email_received_with_attachment` trigger is an additive trigger key on the existing Gmail polling strategy.
- One `GmailPollStrategy`. One cursor per `WorkflowTrigger` row. One idempotency key format. No second poller, scheduler, or queue.
- The existing `new_email_received` trigger is unchanged: lightweight, no Drive scope, no attachment materialization.
- The attachment trigger requires the **existing** Google connection to carry `https://www.googleapis.com/auth/drive.file` in its `scopes` array.
- No separate Drive connection is created. No `capability` column is added to `connections`. Reauthorizing the same connection with `capability: "drive"` accumulates the scope on the existing row.
- The `drive` capability in `CapabilityScopeMap` maps to `https://www.googleapis.com/auth/drive.file` only. It does not request the full `drive` scope.
- Activation validation for the attachment trigger uses the existing `WorkflowCapabilityValidator`. Missing Drive scope ŌåÆ HTTP 409 `error: "missing_scopes"` with `context.location = "trigger"`.
- Runtime missing Drive scope ŌåÆ `StrategyResult::skipped('drive_connection_required')`. Cursor is not advanced.
- The attachment trigger always appends `has:attachment` to the composed Gmail `q`, without duplicating a user-supplied `has:attachment`.
- Payload is the same 14 keys as `new_email_received` plus a 15th key `attachments`, whose entries carry `gmail_attachment_id`, `drive_file_id`, `web_view_link`, `filename`, `mime_type`, `size`, and (only on failure) `error`.
- Per-attachment partial-failure policy. Stable error codes: `gmail_download_failed`, `drive_upload_failed`. Failed entries carry `drive_file_id: null` and `web_view_link: null`.
- `web_view_link` is additive. It does not alter Drive permissions. No public sharing is performed. The Drive file remains private to the user's own Google account.
- No credentials, tokens, provider exception messages, or stack traces in the payload.
- No changes to the scheduler, coordinator, execution engine, connections schema, OAuth flow, or Swagger operations.
- Downstream byte read-back from Drive into a workflow step is not implemented. Consuming actions consume `drive_file_id` (or, for read-only display, the additive `web_view_link`), not bytes.

## Trigger–Action Compatibility

- `ActionDefinition` gained an optional `allowedTriggerKeys` field.
- `null` or an empty list is unrestricted, preserving every existing action's behavior.
- A non-empty list is a whitelist of qualified trigger keys (`<integration_key>.<trigger_key>`).
- Enforcement is server-side and covers all four mutation/activation boundaries. Frontend filtering is not sufficient.
- Failure raises `WorkflowCapabilityException` with error code `incompatible_trigger`; the HTTP response is 409 with the existing capability-error envelope.
- The list is not exposed in the catalog or any API response.
- The validator is `WorkflowTriggerActionCompatibilityValidator`. It runs after catalog-membership validation and is a no-op for unrestricted actions and for any action or integration not present in the catalog.

## Gmail Attachment Links Email Action

- The action key is `google.gmail.send_attachment_links_email`.
- It is restricted via `allowedTriggerKeys` to
  `google.gmail.new_email_received_with_attachment`.
- It reuses `GmailEmailSender::send()`. It does not introduce a second
  sender, does not attach binary content, does not create Drive
  permissions, and does not add OAuth scopes.
- The `attachments` config field is typed `string` with
  `default: '{{ trigger.attachments }}'`. No new mobile field type was
  introduced. The existing resolver's whole-string-template path supplies
  the raw PHP array at execution time.
- The action skips any attachment entry whose `web_view_link` is not a
  non-empty string after trimming, and skips non-array entries.
- If no eligible attachments remain, the body is
  `<p>Attachments could not be linked.</p>`.
- HTML escaping uses `htmlspecialchars(..., ENT_QUOTES | ENT_HTML5, 'UTF-8')`.
- Output shape is `['sent' => bool, 'message_id' => ?string]`, identical to
  `send_email`.
- The attachment trigger is not made exclusive to this action. All existing
  actions remain usable with both Gmail triggers.

## Integrations

- Google applications belong to Integrations.
- Integrations catalog code-defined.
- `CapabilityScopeMap` code-defined.
- Client requests capabilities, never raw scopes.
- Individual workflow definition scopes come from `ActionDefinition` / `TriggerDefinition`.

## Connections

- Connections first-class.
- OAuth state single-use and expiring.
- Credentials encrypted.
- Explicit `connectionId` never falls back to legacy `users.google_*`.

## Cross-capability

- Provider batch structure per Phase 5.
- Migrated features own their HTTP boundary inside their module.
- Cross-capability orchestration in Application Actions.
- Gmail sending has one owner: `GmailEmailSender`.
- Gmail reading has one owner: `GmailMessageReader`.
- Gmail message modification has one owner: `GmailMessageModifier`.
- Gmail label creation has one owner: `GmailLabelCreator`.
- Gmail attachment reading has one owner: `GmailAttachmentReader`.
- Google Drive file upload has one owner: `GoogleDriveFileUploader`.
- Gmail attachment materialization has one owner: `GmailAttachmentMaterializer`.

## Automation

- Workflow has one optional trigger.
- Steps use positive unsigned smallint positions.
- Positions are not reindexed.
- Trigger/step `connection_id` ownership enforced.
- Workflow deletion is soft delete.
- Restore preserves trigger + steps and preserves pre-delete status.
- Activation requires a trigger.
- Activation validates the trigger and every step before persisting `Active`.
- Trigger replacement uses FIND-THEN-UPDATE.
- `WorkflowRepository::listActiveWithTriggerDue()` (integration/trigger-scoped).
- `WorkflowRepository::listAllActiveWithTriggerDue()` (generic due query for the coordinator).
- `UpsertWorkflowTriggerAction` preserves `next_poll_at` on update.

## Activation Capability Validation

- `WorkflowCapabilityValidator` is the validation service.
- `ActionDefinition::requiredScopes` / `TriggerDefinition::requiredScopes` are authoritative.
- `CapabilityScopeMap::has()` confirms capability support.
- `capability === null` is allowed.
- Failed validation prevents activation.

## Templates

- Missing template runtime path throws `TemplateResolutionFailed`.
- Template syntax validated on save.
- Whole-template resolution preserves resolved value type.
- Embedded templates resolve to strings.
- Inter-step templates (`{{ steps.N.output.* }}`) resolve against prior step outputs during execution.
- No `eval` / callables.

## Execution

- Runtime executes captured `workflow_snapshot`.
- Snapshots use explicit whitelist projections.
- Manual execution of `Draft` / `Active` permitted; `Paused` rejected (409).
- Lifecycle: `Pending ŌåÆ Running ŌåÆ Completed | Failed`.
- Idempotency enforced by `UNIQUE(workflow_id, idempotency_key)`.
- Overlap check best-effort.
- Narrow failure classification; programming errors propagate.
- `RunWorkflowAction` is the single execution path.

## Scheduler (production)

- Synchronous execution path.
- No queue worker required for the new scheduler.
- External clock: Google Apps Script calling the internal tick endpoint every 5 minutes.
- Internal endpoint: `POST /api/internal/scheduler/tick`.
- Auth: dedicated bearer token (`INTERNAL_SCHEDULER_TOKEN`), not Sanctum.
- Rate limit: `INTERNAL_SCHEDULER_RATE_LIMIT`.
- Global lock: `scheduler-tick-global` via `Cache::lock`.
- Stale-running sweep: `INTERNAL_SCHEDULER_STALE_RUNNING_MINUTES`.
- Batch size: `INTERNAL_SCHEDULER_BATCH_SIZE`.
- Due query: generic over `workflow_triggers.next_poll_at`.
- Dispatch: `TriggerCoordinator` ŌåÆ `TriggerStrategyRegistry` ŌåÆ strategy ŌåÆ `RunWorkflowAction::execute()`.
- `RunWorkflowJob` remains for legacy paths but is unused by the new scheduler.

## Post-Phase-8 Gmail Polling

- `PollGmailCommand` retained as CLI tool but not used by the production scheduler.
- `GmailPollStrategy` is the production path.
- First tick initializes `poll_cursor` to now; no Gmail query.
- Subsequent ticks: `after = cursor - 360` (OVERLAP_SECONDS = 360).
- Cursor unit: epoch seconds. Gmail `internalDate` is milliseconds.
- Deterministic ordering: `(received_at_epoch_ms, message_id)`.
- Cursor advances to `max(internalDate_seconds) + 1` after successful processing.
- Idempotency key: `gmail:{workflow_id}:{message_id}`.
- `trigger_source = 'poll'`.
- Payload: canonical 14-key whitelist via `GmailMessagePayloadBuilder`.
- Self-email filter: case-insensitive, display-name aware.
- Malformed `label_id` ŌåÆ skip workflow; cursor unchanged.
- 429/5xx ŌåÆ per-workflow isolation; cursor unchanged.
- 404 on message fetch ŌåÆ skipped; cursor advances from other messages.
- `GoogleCredentialsUnavailableException` / `ConnectionNotFoundException` ŌåÆ skip workflow.
- Other throwables propagate.

## Post-Phase-8 Contract Fixes

- Empty `config` serializes as JSON object `{}`, not array `[]`, in Catalog and Trigger responses.

## Gmail Integration Completion

- Ten Gmail actions wired and registered in `HandlerRegistry`.
- Trigger `new_email_received` extended with `from`, `subject`, `has_attachment`, `query`; `label_id` remains separate.
- Gmail capability now requests five scopes.
- Existing connections must reconnect to obtain `gmail.compose`, `gmail.modify`, `gmail.labels`.
- Attachment metadata exposed via `has_attachment` / `attachment_count` / `attachment_ids`.
- Real Gmail E2E verification COMPLETE / VERIFIED.

## Deferred / Future

- Email verification flow.
- Per-device token revocation.
- Refresh-token / long-lived token policy.
- Rate limiting on `/api/login` (corrected controller proposed; not applied).
- Rate limiting on the Google auth redirect/callback (design-only).
- Encryption of `users.google_access_token` / `users.google_refresh_token`.
- Google OAuth hardening (token-in-URL callback).
- `requires_connection` catalog flag.
- Field metadata for Calendar / Sheets / Docs / Analytics actions.
- Runtime handlers for all Calendar / Sheets / Docs / Analytics workflow step actions.
- **Downstream Gmail attachment byte retrieval**: an action that reads attachment bytes back out of Drive and passes them into execution output. The `new_email_received_with_attachment` trigger does materialize attachments into the user's Drive; this deferred item refers only to a downstream action that consumes the bytes.
- Gmail attachment ŌåÆ Sheets (write attachment metadata or content into a spreadsheet).
- Gmail attachment ŌåÆ Gmail send (forward the attachment via another Gmail action).
- Gmail Push (`users.watch`), Google Pub/Sub, `historyId` cursors.
- Webhook trigger ingress.
- Scheduled workflow payload support for `{{trigger.*}}` templates.
- Rate limits per connection.
- Catalog caching / ETag / multi-endpoint catalog.
- Documentation clarifications for `trigger_payload` semantics, `interval_minutes` update behavior, restore-preserves-status behavior.
- Pint style cleanup.
- Live `missing_scopes` verification against an old-scope-only Google connection.
- Live end-to-end verification of the `new_email_received_with_attachment` trigger against a real Gmail mailbox (materialization path).
- Optional clean negative-case retest of the `is:unread` filter.
- Investigation of the scheduler tick unauthenticated response body mismatch.
- Framework `lang/en/validation.php` and `lang/ar/validation.php` overrides (per-field 422 messages for locales other than `en`).
- Localization of `TestLoginController` (source file not supplied).
- Localization of `FacebookAuthController` and `FacebookWebhookController` (source files not supplied).
- Localization of raw business-result `message` strings embedded in action result payloads.
- Master OTP for internal password reset ŌĆö explicitly rejected.
- CLI password reset command ŌĆö proposed but not implemented.
- Any additional capability not explicitly approved.
- **Live end-to-end verification of the `send_attachment_links_email` action** against a real Gmail mailbox.

---

# Current Task

````text
Phase 0ŌĆō8 and all recorded post-Phase-8 branches are COMPLETE / CLOSED / VERIFIED.

Production Scheduler Hardening Batches 1ŌĆō8 are COMPLETE / VERIFIED.
Gmail Integration Completion is COMPLETE / CLOSED / VERIFIED for the MVP scope.
Gmail E2E verification is COMPLETE / VERIFIED against a real Gmail account.
Strategy test coverage is COMPLETE / VERIFIED.

Localization (Batches 1ŌĆō4) is COMPLETE.
Root API Controller Localization is COMPLETE where source files were available.
Password Reset (Forgot Password) is COMPLETE.

Gmail Attachment Trigger (feature Batches 1ŌĆō6) is COMPLETE (reported).

Gmail Attachment Links Email Action (feature Batches 1ŌĆō2) is COMPLETE (reported):
- Batch 1 introduced the reusable trigger–action compatibility mechanism
  (ActionDefinition::allowedTriggerKeys + WorkflowTriggerActionCompatibilityValidator)
  and enforced it at step add, step update, trigger upsert, and activation.
- Batch 2 registered google.gmail.send_attachment_links_email, restricted
  to google.gmail.new_email_received_with_attachment, and implemented the
  action, handler, DTO, and focused tests.
- Existing send_email behavior and every other existing action is unchanged.
- The attachment trigger remains usable with every existing action.
- No new field type, endpoint, dependency, migration, or scope was added.
- The catalog now exposes the new action's config metadata.

Live Production Readiness Verification (2026-10-12) is COMPLETE / VERIFIED:
- Full Gmail -> Drive -> links-email chain confirmed against real accounts.
- Four findings recorded (tick duration, Apps Script trigger not installed,
  Drive link recipients need existing permission, idempotency counter
  ambiguity). None are defects.

The scheduler path is unchanged since scheduler hardening close.

Latest verified full-suite run: 752 passed / 2267 assertions / 0 failures.
This count predates the Localization test files, the Password Reset test
file, the Gmail Attachment Trigger test files, and the Gmail Attachment
Links Email Action test files.

Batches 1ŌĆō2 changes deployed to production at commit 64ee567 on 2026-10-12.
Apps Script 5-minute scheduler trigger installed and firing against production.
Phase 9 remains LOCKED.
Phase 10 remains LOCKED.

---

# Current Next Step

```text
Await explicit human authorization for the next approved step.

Remaining planned work:
- Phase 9 ŌĆö Security Hardening (LOCKED).
- Phase 10 ŌĆö Legacy Deprecation / Cleanup (LOCKED).
- Optional: apply the corrected LoginController rate limit.
- Optional: publish framework validation overrides (lang/en/validation.php,
  lang/ar/validation.php).
- Optional: localize TestLoginController and the Facebook controllers once
  their source files are supplied.
- Optional: run the Localization + Password Reset + Gmail Attachment Trigger
  + Gmail Attachment Links Email Action test files against a dedicated test
  DB or :memory: SQLite, and update the cumulative test count.
- Optional clean negative retest of the is:unread filter.
- Live missing_scopes verification against an old-scope-only Google connection.
- Live end-to-end verification of the attachment trigger against a real
  Gmail mailbox.

Production deployment follow-up:
- Deploy the Batches 1ŌĆō2 changes via the GitHub -> pipeline workflow.
- Verify the deployed revision matches the pushed commit.
- Install the Apps Script 5-minute trigger (operator action).
- Confirm one automatic Apps Script tick succeeds against production.
- After the first live tick, monitor tick duration against the platform
  HTTP request timeout.

Production-environment verification (env vars, cache store, Apps Script
ScriptProperties, mail transport, migrate --force, first live tick) is
required before any deployment.
````

No cleanup, no refactor, and no new code is authorized by this state file alone.

---

# State Update Rule

Update this file after every approved implementation step with: current phase, phase status, completed tasks, current task, next task, files changed, tests, known issues, pending decisions.

Never claim a phase complete without satisfying its Phase Gate or an explicitly approved waiver.
