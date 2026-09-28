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

Post-Phase-8 completed branches — see below. Production Scheduler Hardening Batches 1–4 are also complete and recorded here. No new numbered phase has been opened.

## Status

```text
Phase 0–8 COMPLETE / CLOSED / VERIFIED.
Post-Phase-8 completed branches recorded below.
Production Scheduler Hardening Batches 1–4 COMPLETE.
Strategy test coverage COMPLETE.
No new numbered phase has been opened.
Production has NOT been deployed.
```

- Phase 0 — COMPLETE
- Phase 1 — MERGED INTO PHASE 2
- Phase 2 — COMPLETE
- Phase 3 — COMPLETE
- Phase 4 — COMPLETE
- Phase 5 — COMPLETE and explicitly approved
- Phase 6 — COMPLETE / CLOSED (through Batch 6.5)
- Phase 7 — COMPLETE / CLOSED / VERIFIED
- Phase 8 — COMPLETE / CLOSED / VERIFIED (human approved)
- Catalog / Discovery API — COMPLETE / CLOSED / VERIFIED (human approved)
- Login API batch — COMPLETE / CLOSED / VERIFIED (human approved)
- Gmail polling hardening — COMPLETE / CLOSED / VERIFIED
- Gmail self-email loop incident — RESOLVED / VERIFIED
- Workflow API E2E audit — COMPLETE / CLOSED / VERIFIED
- Path A `config: []` → `{}` contract fix — COMPLETE / CLOSED / VERIFIED
- Production Scheduler Hardening Batches 1–4 — COMPLETE / VERIFIED
- Strategy test coverage — COMPLETE / VERIFIED

Phase 9 remains LOCKED. Phase 10 remains LOCKED. No future phase transition is authorized by this state file alone.

---

# Phase History

## Phase 0 — Safety Net

Status: `COMPLETE`. 26 tests / 75 assertions at close.

## Phase 1 — Architecture Skeleton

Status: `MERGED INTO PHASE 2` (ADR-027).

## Phase 2 — Connections

Status: `COMPLETE`. 52 tests / 179 assertions at close.

## Phase 3 — Integrations

Status: `COMPLETE`. 84 tests / 495 assertions at close.

## Phase 4 — Compatibility + Google Migration

Status: `COMPLETE`. 99 tests / 519 assertions at close.

## Phase 5 — Existing Feature Refactoring

Status: `COMPLETE`. 293 tests / 986 assertions at close.

## Phase 6 — Workflow Engine

Status: `COMPLETE / CLOSED`. 507 tests / 1486 assertions at close.

Closed through the approved Batch 6.1–6.5 scope.

---

# Phase 7 — Gmail Automation MVP

Status: `COMPLETE / CLOSED / VERIFIED`.

## Phase 7 MVP Scope — satisfied

- Gmail `new_email_received` trigger runtime.
- Gmail polling runtime.
- Gmail label selection / filtering.
- Existing `send_email` action remains usable.
- Trigger payload includes attachment metadata for future compatibility.
- Capability-based Gmail scope escalation at connection creation.
- Activation-time capability validation for Gmail-gated triggers and actions.

Batches:

- Batch 7.1 — COMPLETE / CLOSED / VERIFIED
- Batch 7.2 — COMPLETE / CLOSED / VERIFIED
- Batch 7.3 — COMPLETE / CLOSED / VERIFIED
- Batch 7.4 / 7.4b — COMPLETE / CLOSED / VERIFIED
- Batch 7.5 — COMPLETE / CLOSED / VERIFIED (scope-classification only)

---

# Phase 8 — Swagger / OpenAPI Documentation

Status: `COMPLETE / CLOSED / VERIFIED` (human approved).

## Tooling

- `darkaonline/l5-swagger` **11.1.0**
- `zircote/swagger-php` **6.10.0** (transitive)
- `swagger-api/swagger-ui` **v5.33.0** (transitive)
- Laravel 12.31.1 / PHP 8.2.12.

## Structure

Global configuration:

- `app/Modules/Swagger/OpenApi.php` — `info`, `server`, `securityScheme` (`bearerAuth`), `Tag` list, shared schemas and responses.

Per-module path files:

- `app/Modules/Identity/Swagger/Identity.php`
- `app/Modules/Connections/Swagger/Connections.php`
- `app/Modules/Automation/Swagger/Automation.php`
- `app/Modules/Execution/Swagger/Execution.php`
- `app/Modules/Integrations/Swagger/{Gmail,Calendar,Sheets,Docs,Analytics,Catalog}.php`

Runtime code carries no Swagger attributes.

## L5-Swagger scan configuration

- `config/l5-swagger.php` published.
- `documentations.default.paths.annotations` = `base_path('app/Modules')`.

## Coverage

57 operations, 57 unique operationIds at Phase 8 close. Catalog and Login batches added one each.

## Explicitly excluded

- `POST /api/test/login` (dev only).
- Facebook legacy endpoints.
- Console commands.
- Deferred Phase 7 capabilities.

---

# Catalog / Discovery API

Status: `COMPLETE / CLOSED / VERIFIED` (human approved).

## Goal

Provide a stable, read-only, code-defined Catalog / Discovery contract.

## Scope

- `GET /api/catalog`, `auth:sanctum`, operationId `catalog.show`, tag `Catalog`.
- Response derived from `IntegrationCatalog` — single source of truth.
- Field metadata added to `ActionDefinition` / `TriggerDefinition` as optional `array $fields = []`.
- New `FieldDefinition` value object.
- No second registry, no DB table, no form-builder framework.

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

## Goal

First-class email/password login endpoint.

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

---

# Post-Phase-8 Completed Branches

These branches were completed after Phase 8 without being designated as a new numbered phase.

---

## Gmail Polling Hardening

Status: `COMPLETE / CLOSED / VERIFIED`.

## Goal

Address operational and safety defects in the Gmail polling runtime.

## Scope

- `RunWorkflowJob` — `ShouldQueue`, `ShouldBeUnique`, `$tries = 3`, `$backoff = [30, 120, 600]`, `$uniqueFor = 3600`.
- `PollGmailCommand` — self-email filter, `hasInProgressForWorkflow` pre-check, `Cache::lock('poll-trigger:{id}', 90)`, cursor advancement past skipped messages, `next_poll_at` scheduling, `RateLimiter` at dispatch.
- `RecoverFailedPollExecutionsCommand` — `MAX_RETRIES = 3`, `GRACE_MINUTES = 10`, `BATCH_SIZE = 100`, CAS claim, `retry_of_id` lineage.
- Migrations: `workflow_triggers.next_poll_at`, `executions.retry_attempts`, `executions.retry_of_id`.
- `ActivateWorkflowAction` resets `next_poll_at` to `null` on activation.
- `UpsertWorkflowTriggerAction` preserves `next_poll_at` on update.
- `WorkflowRepository::listActiveWithTriggerDue()` added.

---

## Gmail Self-Email Loop Incident — Diagnosis and Resolution

Status: `RESOLVED / VERIFIED`.

## Incident

Before hardening, a Gmail workflow could fire on its own outbound reply. Since the outbound reply lands in the same Gmail INBOX as the trigger source, the workflow re-triggered itself. Observed escalation: 3s → 28s → 40s per tick.

## Resolution

`PollGmailCommand::filterOutSelfEmails` compares each fetched message's `From:` header against the connected Gmail account email. Case-insensitive, display-name aware. Skipped messages are logged and the cursor advances past them.

## Verification

Live Gmail verification confirmed the filter breaks the loop: two self-authored replies were filtered, zero `RunWorkflowJob` instances dispatched, cursor advanced.

---

## Workflow API E2E Audit

Status: `COMPLETE / CLOSED / VERIFIED`.

## Scope

- `tests/Feature/Audit/WorkflowApiAuditTest.php` — 26 tests / 105 assertions.
- Covers: authentication, catalog, connections, Gmail labels, workflow CRUD, trigger upsert, step upsert, activate/pause/resume/delete/restore, manual execution, idempotency, `interval_minutes` validation sweep, scheduler semantics, cross-user ownership, unauthenticated requests, negative testing.

---

## Path A — `config: []` → `{}` Contract Fix

Status: `COMPLETE / CLOSED / VERIFIED`.

## Bug

The Catalog and Trigger responses serialized an empty `config` object as a JSON array (`[]`) instead of a JSON object (`{}`). The mobile contract and the Swagger `CatalogAction` schema declare `config` as `type: object`.

## Fix — production files changed

- `app/Modules/Integrations/Application/Services/CatalogProjector.php`
- `app/Modules/Automation/Application/DTOs/TriggerData.php`

## Fix — test file changed

- `tests/Feature/Audit/WorkflowApiAuditTest.php`

---

# Production Scheduler Hardening

Status: `COMPLETE` for Batches 1–4 and strategy test coverage.

This effort replaces the previous queue-worker-dependent production scheduling path with a synchronous, externally triggered execution architecture. The new scheduler path does **not** require a queue worker.

## Architecture

```
Google Apps Script (external clock, ~5 min cadence)
        │
        ▼
POST /api/internal/scheduler/tick
        │
        ▼
InternalSchedulerMiddleware (bearer token, rate limit)
        │
        ▼
SchedulerTickService (global lock)
        │
        ▼
TriggerCoordinator
        │
        ▼
TriggerStrategyRegistry
        ├── ScheduleStrategy
        ├── GmailPollStrategy
        └── Future Strategies
        │
        ▼
RunWorkflowAction::execute()   (synchronous)
        │
        ▼
Execution
```

## Batches

### Batch 1 — Foundation

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

- `app/Modules/Automation/Core/Repositories/WorkflowRepository.php` — added `listAllActiveWithTriggerDue()`
- `app/Modules/Automation/Infrastructure/Repositories/EloquentWorkflowRepository.php` — implemented it

No schema change.

### Batch 2 — Schedule Strategy

Status: `COMPLETE`.

Files created:

- `app/Modules/Execution/Application/Strategies/ScheduleStrategy.php`

Behavior:

- Self-enforcing due-check (`nextPollAt <= $now`).
- Idempotency key derived from `next_poll_at` timestamp.
- Synchronous `RunWorkflowAction::execute()`.
- Advances `next_poll_at` to `now + interval_minutes`.

### Batch 3 — Gmail Poll Strategy

Status: `COMPLETE`.

Files created:

- `app/Modules/Execution/Application/Strategies/GmailPollStrategy.php`

Behavior:

- Extracted from `PollGmailCommand`.
- Preserves self-email filter, cursor advancement, deterministic ordering, and idempotency key format.
- Replaces `RunWorkflowJob::dispatch` with synchronous `RunWorkflowAction::execute`.
- No queue worker dependency.

### Batch 4 — Internal Scheduler API

Status: `COMPLETE`.

Files created:

- `app/Modules/Execution/Http/Middleware/InternalSchedulerMiddleware.php`
- `app/Modules/Execution/Http/Controllers/InternalSchedulerTickController.php`
- `app/Modules/Execution/Http/Routes/internal.php`

Files modified:

- `app/Modules/Execution/Infrastructure/Providers/ExecutionServiceProvider.php` — registered strategy registry and internal route file
- `.env.example` — added scheduler env variables

Endpoint contract:

- `POST /api/internal/scheduler/tick`
- `Authorization: Bearer ${INTERNAL_SCHEDULER_TOKEN}`
- Response: `{ ok, processed, executed, skipped, failed, lock_held }`
- Errors: 401 (unauthorized), 429 (rate limited), 503 (misconfigured server)

No schema change.

## Strategy Test Coverage

Status: `COMPLETE / VERIFIED`.

Files created:

- `tests/Feature/Execution/ScheduleStrategyTest.php` — 3 tests / 14 assertions
- `tests/Feature/Execution/GmailPollStrategyTest.php` — 3 tests / 13 assertions

Also completed:

- `tests/Feature/Internal/SchedulerTickTest.php` — 6 tests / 17 assertions
- `tests/Feature/Execution/TriggerCoordinatorTest.php` — 3 tests / 5 assertions

## Scheduler Architecture (verified)

- The scheduler execution path is **synchronous**. No queue worker is required.
- External clock: Google Apps Script (intended production trigger), calling the internal tick endpoint every ~5 minutes.
- Internal endpoint: `POST /api/internal/scheduler/tick`.
- Authentication: dedicated bearer token (`INTERNAL_SCHEDULER_TOKEN`).
- Global lock: `scheduler-tick-global` via `Cache::lock`.
- Due-trigger selection: generic query over `workflow_triggers.next_poll_at`.
- Dispatch: `TriggerCoordinator` → `TriggerStrategyRegistry` → strategy → `RunWorkflowAction::execute()`.
- `RunWorkflowJob` remains in the project for legacy paths but is **not** used by the new scheduler path.

## Remaining Batches

- **Batch 5** — Recovery generalization and stuck-`running` execution sweep. Not implemented.
- **Batch 6** — `next_poll_at` index optimization for the generic due query. Not implemented.
- **Batch 7** — Google Apps Script production setup and token configuration. Not done.
- **Batch 8** — Final production documentation and deployment checklist. Not done.

## Open Concerns (still open after current code inspection)

1. **`NULL` `next_poll_at` treated as due.** A newly-activated trigger fires on the next tick. This is intended for immediate polling but means activation produces an immediate first poll.
2. **`next_poll_at` index adequacy.** The existing composite index `(integration_key, trigger_key, next_poll_at)` is not usable for the generic due query without an `integration_key` predicate. Batch 6 must address.
3. **Gmail cursor overlap under 5-minute ticks.** `OVERLAP_SECONDS = 60` in `GmailPollStrategy` is sized for a 1-minute cadence. Under a 5-minute external clock this window may be too narrow to recover from a crash mid-tick.
4. **Synchronous tick duration.** The tick runs synchronously and is bounded by the platform HTTP timeout. `INTERNAL_SCHEDULER_BATCH_SIZE` must remain conservative until measured.
5. **Apps Script token storage.** `INTERNAL_SCHEDULER_TOKEN` must be stored in `ScriptProperties` and never logged. Batch 7 concern.
6. **Stuck `running` executions.** No protection today. Batch 5 concern.

---

# Cumulative Test Growth

| Phase / Batch | Total Tests | Total Assertions |
| --- | --- | --- |
| Phase 0 close | 26 | 75 |
| Phase 2 close | 52 | 179 |
| Phase 3 close | 84 | 495 |
| Phase 4 close | 99 | 519 |
| Phase 5 close | 293 | 986 |
| Batch 6.5 close (Phase 6 close) | 507 | 1486 |
| Batch 7.1 close | 519 | 1512 |
| Batch 7.2 close | 540 | 1576 |
| Batch 7.3 close | 577 | 1670 |
| Batch 7.4 close | 607 | 1749 |
| Batch 7.5 close (Phase 7 close) | 607 | 1749 |
| Phase 8 close | 607 | 1749 |
| Catalog batch close | 621 | 1893 |
| Login API batch close | 632 | 1929 |
| Post-Login full-suite (pre-Path-A) | 663 | 1963 |
| Post-Path-A focused (3 files) | 53 focused | 207 focused |
| Post-Path-A full suite | 689 | 2068 |
| Post Batches 1–4 full suite | 698 | 2090 |
| **Post scheduler + strategy coverage (current)** | **704** | **2117** |

Historical numbers through Batch 7.3 are preserved exactly as recorded at their respective closures.

---

# Production-Safe Legacy Confirmation

The following remain unchanged and intact:

- All legacy `app/Http/Controllers/Api/*` Google/Facebook/TestLogin/User controllers.
- `app/Services/*`.
- `app/Models/User.php`, `app/Models/FacebookAccount.php`.
- All existing global migrations under `database/migrations/`.
- `config/*` except the published `config/l5-swagger.php` and the new `config/internal_scheduler.php`.
- `bootstrap/*` except the additive `IdentityServiceProvider` line.
- All Phase 0–7 tests.

Live Google login (`redirect` / `callback`) untouched.
`users.google_access_token`, `users.google_refresh_token`, `users.google_token_expires_at` untouched.
`redirectgoogle()` legacy flow untouched.
`TestLoginController` untouched.
Facebook functionality untouched.
`POST /api/register` behaviorally untouched.
`has_bot_access` untouched.

---

# Pending Cleanup Items (Post-Phase-10)

1. `app/Http/Controllers/Api/EmailController.php` — emptied/commented
2. `app/Http/Controllers/Api/GoogleSheetsController.php` — emptied/commented
3. `app/Http/Controllers/Api/CalendarController.php` — orphaned
4. `app/Http/Controllers/Api/GoogleDocsController.php` — orphaned
5. `app/Http/Controllers/Api/GoogleAnalyticsController.php` — orphaned
6. `app/Services/GoogleSheetsService.php` — orphaned
7. `app/Services/GoogleCalendarService.php` — orphaned
8. `app/Services/GoogleDocsService.php` — orphaned
9. `app/Services/GoogleAnalyticsService.php` — orphaned
10. `tests/Feature/Connections/GoogleCallbackTest.php` — two commented-out method bodies
11. Resolver naming asymmetry (`GoogleCredentialsResolver` interface vs `GoogleCredentialResolver` implementation)
12. `env()` reads for Google client id/secret in infrastructure classes
13. Commented legacy route blocks in `routes/api.php`
14. `GmailService` has no active consumer but is retained
15. `WorkflowSnapshotBuilder::stepSnapshot()` — dead code introduced during Batch 6.4
16. Pint — 8 files flagged with style issues in `app/Modules/Automation`, `app/Modules/Execution`, `tests/Feature/Execution`, `tests/Feature/Automation`. Not yet applied.
17. `RunWorkflowJob` is no longer dispatched by the new scheduler path. Kept for legacy/manual flows.

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
- No `eval` / callables.

## Execution

- Runtime executes captured `workflow_snapshot`.
- Snapshots use explicit whitelist projections.
- Manual execution of `Draft` / `Active` permitted; `Paused` rejected (409).
- Lifecycle: `Pending → Running → Completed | Failed`.
- Idempotency enforced by `UNIQUE(workflow_id, idempotency_key)`.
- Overlap check best-effort.
- Narrow failure classification; programming errors propagate.
- `RunWorkflowAction` is the single execution path.

## Scheduler (post-hardening)

- Synchronous execution path.
- No queue worker required for the new scheduler.
- External clock: Google Apps Script calling the internal tick endpoint.
- Internal endpoint: `POST /api/internal/scheduler/tick`.
- Auth: dedicated bearer token.
- Global lock: `scheduler-tick-global` via `Cache::lock`.
- Due query: generic over `workflow_triggers.next_poll_at`.
- Dispatch: `TriggerCoordinator` → `TriggerStrategyRegistry` → strategy → `RunWorkflowAction::execute()`.
- `RunWorkflowJob` remains for legacy paths but is unused by the new scheduler.

## Post-Phase-8 Gmail Polling

- `PollGmailCommand` retained as CLI tool but not used by the production scheduler.
- `GmailPollStrategy` is the production path.
- First tick initializes `poll_cursor` to now; no Gmail query.
- Subsequent ticks: `after:max(cursor - 60, 0)`.
- Cursor unit: epoch seconds. Gmail `internalDate` is milliseconds.
- Deterministic ordering: `(received_at_epoch_ms, message_id)`.
- Cursor advances to `max(internalDate_seconds) + 1` after successful processing.
- Idempotency key: `gmail:{workflow_id}:{message_id}`.
- `trigger_source = 'poll'`.
- Payload: canonical 14-key whitelist via `GmailMessagePayloadBuilder`.
- Self-email filter: case-insensitive, display-name aware.
- Malformed `label_id` → skip workflow; cursor unchanged.
- 429/5xx → per-workflow isolation; cursor unchanged.
- 404 on message fetch → skipped; cursor advances from other messages.
- `GoogleCredentialsUnavailableException` / `ConnectionNotFoundException` → skip workflow.
- Other throwables propagate.

## Post-Phase-8 Contract Fixes

- Empty `config` serializes as JSON object `{}`, not array `[]`, in Catalog and Trigger responses.

## Deferred (post-Login backlog)

- Email verification flow.
- Password reset flow.
- Per-device token revocation.
- Refresh-token / long-lived token policy.
- Rate limiting on auth endpoints.
- Encryption of `users.google_access_token` / `users.google_refresh_token`.
- Google OAuth hardening (token-in-URL callback).
- `requires_connection` catalog flag.
- Field metadata for Calendar / Sheets / Docs / Analytics actions.
- Runtime handlers for `reply_to_email`, `create_draft`.
- Runtime handlers for all Calendar / Sheets / Docs / Analytics workflow step actions.
- Gmail label mutation / `gmail.labels` capability.
- Gmail attachment byte retrieval.
- Gmail Push (`users.watch`), Google Pub/Sub, `historyId` cursors.
- Webhook trigger ingress.
- Scheduled workflow payload support for `{{trigger.*}}` templates.
- Rate limits per connection.
- Catalog caching / ETag / multi-endpoint catalog.
- Documentation clarifications for `trigger_payload` semantics, `interval_minutes` update behavior, restore-preserves-status behavior.
- Pint style cleanup.
- Batch 5 — Recovery generalization and stuck-`running` sweep.
- Batch 6 — `next_poll_at` index optimization.
- Batch 7 — Apps Script production setup.
- Batch 8 — Final production documentation.
- Any additional capability not explicitly approved.

---

# Current Task

```text
Phase 0–8 and the post-Phase-8 branches are COMPLETE / CLOSED / VERIFIED.

Production Scheduler Hardening Batches 1–4 and strategy test coverage are
COMPLETE / VERIFIED. The scheduler path is synchronous, uses a dedicated
internal tick endpoint authenticated by a bearer token, holds a global lock,
queries due triggers generically, and dispatches through a coordinator to
strategies that call RunWorkflowAction synchronously. No queue worker is
required for the new scheduler path.

Latest verified full-suite run: 704 passed / 2117 assertions / 0 failures.

Production has NOT been deployed.
Phase 9 remains LOCKED.
Phase 10 remains LOCKED.
```

No new implementation batch has been authorized.

---

# Current Next Step

```text
Await explicit human authorization for the next approved step.

Remaining planned work:
- Batch 5 (recovery generalization, stuck-running sweep)
- Batch 6 (next_poll_at index optimization)
- Batch 7 (Apps Script production setup)
- Batch 8 (final production documentation)

Production-environment verification (cron, queue worker, cache, queue driver,
OAuth redirect URIs) is required before any deployment.

Do not start Phase 9 or any new batch without explicit authorization.
```

No cleanup, no refactor, and no new code is authorized by this state file alone.

---

# State Update Rule

Update this file after every approved implementation step with: current phase, phase status, completed tasks, current task, next task, files changed, tests, known issues, pending decisions.

Never claim a phase complete without satisfying its Phase Gate or an explicitly approved waiver.
