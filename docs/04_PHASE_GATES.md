# Phase Gates

## Purpose

Defines completion conditions per phase. A phase completes only when its gate is satisfied and the phase receives explicit human approval.

---

# Global Rules

```text
Plan → Inspect → Implement → Test → Review → Update State → Human Approval → Next Phase
```

Never transition automatically.

---

# Architecture Rule

Modular Monolith + DDD.

Canonical module root: `app/Modules/`.

Modules: `Identity`, `Integrations`, `Connections`, `Automation`, `Execution`.

`Core` is the physical DDD/domain layer.

Migrated features own their HTTP boundary inside their module.

---

# Phase 0 — Safety Net

Status: `COMPLETE`. 26 tests / 75 assertions.

---

# Phase 1 — Architecture Skeleton

Status: `MERGED INTO PHASE 2` (ADR-027).

---

# Phase 2 — Connections

Status: `COMPLETE`. 52 tests / 179 assertions.

---

# Phase 3 — Integrations

Status: `COMPLETE`. 84 tests / 495 assertions.

---

# Phase 4 — Compatibility + Google Migration

Status: `COMPLETE`. 99 tests / 519 assertions.

---

# Phase 5 — Existing Feature Refactoring

Status: `COMPLETE`. 293 tests / 986 assertions.

---

# Phase 6 — Workflow Engine

Status: `COMPLETE / CLOSED`. 507 tests / 1486 assertions.

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

## Phase 7 Non-Goals

- AI.
- Non-Gmail providers.
- Gmail Push (`users.watch`).
- Google Pub/Sub.
- Retry / DLQ infrastructure.
- Multiple trigger types per workflow.
- Parallel execution.
- Complex branching / loops.
- New execution retry semantics.
- Connection-level Gmail polling pooling.
- Arbitrary new OAuth scopes supplied by the client.
- Label mutation (create/update/delete).
- Attachment downloading or content processing.
- Gmail `historyId` cursor.

## Batch 7.1 — Connection Scope Escalation

Status: `COMPLETE / CLOSED / VERIFIED`.

Human approval: **received**.

## Batch 7.2 — Gmail Trigger Payload + Cursor

Status: `COMPLETE / CLOSED / VERIFIED`.

Human approval: **received**.

## Batch 7.3 — Gmail Polling Runtime

Status: `COMPLETE / CLOSED / VERIFIED`.

Human approval: **received**.

## Batch 7.4 — Activation Capability Validation

Status: `COMPLETE / CLOSED / VERIFIED`.

Human approval: **received**.

## Batch 7.5 — Phase 7 Scope Classification

Status: `COMPLETE / CLOSED / VERIFIED` (no Category A implementation work).

Human approval: **received**.

## Phase 7 Gate

Status: `COMPLETE / CLOSED / VERIFIED`.

- [x] Batch 7.1 closed.
- [x] Batch 7.2 closed.
- [x] Batch 7.3 closed.
- [x] Batch 7.4 closed.
- [x] Batch 7.5 classified — no required work.
- [x] All Phase 0–6 tests remain green.
- [x] Full suite green at 607 tests / 1749 assertions at Phase 7 close.
- [x] Pint green on Phase 7-owned scope.
- [x] Human approval to close Phase 7 received.

Phase 7 is `COMPLETE / CLOSED / VERIFIED`.

---

# Phase 8 — Swagger / OpenAPI Documentation

Status: `COMPLETE / CLOSED / VERIFIED`.

Human approval: **received**.

## Phase 8 Goal

Provide accurate, maintainable, frontend-consumable Swagger/OpenAPI documentation for the API that actually exists.

## Phase 8 Scope

- Valid OpenAPI 3.x document.
- Documentation reflects the current implemented API contract only.
- No invented endpoints, fields, status codes, or capabilities.
- No runtime code modified.

## Phase 8 Non-Goals

- Changing any runtime behavior.
- Documenting `/api/test/login` or Facebook legacy endpoints.
- Documenting deferred Phase 7 capabilities.
- Introducing a Catalog API (handled as a separate batch after Phase 8).

## Delivered

**Tooling:**

- `darkaonline/l5-swagger` **11.1.0**
- `zircote/swagger-php` **6.10.0** (transitive)
- `swagger-api/swagger-ui` **v5.33.0** (transitive)
- Laravel 12.31.1 / PHP 8.2.12.

**Structure:**

- Global configuration: `app/Modules/Swagger/OpenApi.php`.
- Per-module path files: Identity, Connections, Automation, Execution, Integrations.

**Configuration:**

- `config/l5-swagger.php` published.
- Scan path: `base_path('app/Modules')`.
- No annotations in runtime code.

**Coverage:**

- 57 operations, 57 unique operationIds at Phase 8 close. Catalog + Login batches added one each.

**Security:**

- Sanctum bearer scheme documented as `bearerAuth` and applied to protected operations.

**Excluded:**

- `/api/test/login`.
- Facebook legacy endpoints.
- Console commands.
- All deferred Phase 7 capabilities.

## Phase 8 Gate

- [x] Swagger/OpenAPI tooling installed and compatible.
- [x] Modular Swagger structure under `app/Modules/**/Swagger`.
- [x] Global configuration in `app/Modules/Swagger/OpenApi.php`.
- [x] Per-module path files.
- [x] No annotations in runtime code.
- [x] L5-Swagger scan path configured.
- [x] Only existing endpoints documented.
- [x] Sanctum bearer security scheme documented and applied.
- [x] 409 capability error structure documented.
- [x] Gmail labels documented read-only.
- [x] Deferred Phase 7 capabilities not documented.
- [x] `/api/test/login` not documented.
- [x] Facebook legacy endpoints not documented.
- [x] Generated OpenAPI document valid.
- [x] `$ref` resolution verified.
- [x] OperationIds unique.
- [x] Documented endpoints match registered routes.
- [x] Full test suite green.
- [x] Pint green on `app/Modules`.
- [x] Human approval to close Phase 8.

Phase 8 is `COMPLETE / CLOSED / VERIFIED`.

---

# Catalog / Discovery API

Status: `COMPLETE / CLOSED / VERIFIED`.

Human approval: **received**.

## Goal

Provide a stable, read-only, code-defined Catalog / Discovery contract.

## Scope

- `GET /api/catalog`, `auth:sanctum`, operationId `catalog.show`, tag `Catalog`.
- Response derived from `IntegrationCatalog`.
- Field metadata added to `ActionDefinition` / `TriggerDefinition` as optional `array $fields = []`.
- New `FieldDefinition` value object.

## Non-Goals

- New runtime behavior.
- New providers.
- Changes to `CapabilityScopeMap`, OAuth, Connections, Execution, or Automation runtime.
- Field metadata for definitions without an authoritative runtime handler.

## Delivered

- `app/Modules/Integrations/Core/ValueObjects/FieldDefinition.php`
- `app/Modules/Integrations/Application/Services/CatalogProjector.php`
- `app/Modules/Integrations/Http/Controllers/CatalogController.php`
- `app/Modules/Integrations/Http/Routes/api.php`
- `app/Modules/Integrations/Core/Entities/ActionDefinition.php`
- `app/Modules/Integrations/Core/Entities/TriggerDefinition.php`
- `app/Modules/Integrations/Swagger/Catalog.php`
- Tests: `CatalogEndpointTest` (11 tests), `FieldDefinitionTest` (3 tests).

## Catalog Batch Gate

- [x] Read-only catalog endpoint implemented.
- [x] `auth:sanctum` protection.
- [x] OperationId `catalog.show`, tag `Catalog`.
- [x] Stable machine keys.
- [x] Response derived from `IntegrationCatalog` only.
- [x] No second registry, no DB table, no duplicated definitions.
- [x] Field metadata added only where runtime config is authoritative.
- [x] Full test suite green.
- [x] Pint green on `app/Modules`.
- [x] OpenAPI generation successful.
- [x] Human approval to close the Catalog batch.

Catalog / Discovery API is `COMPLETE / CLOSED / VERIFIED`.

---

# Login API Batch

Status: `COMPLETE / CLOSED / VERIFIED`.

Human approval: **received**.

## Goal

Add a first-class email/password login endpoint.

## Scope

- `POST /api/login` (public).
- Implemented inside the existing Identity bounded context.
- Reads `App\Models\User` directly; no repository abstraction introduced.
- Does not create users.
- Does not modify `/api/register`, Google OAuth, Sanctum config, or the `User` model.
- No schema or migration change.

## Delivered

- `app/Modules/Identity/Application/DTOs/LoginInput.php`
- `app/Modules/Identity/Application/Actions/LoginAction.php`
- `app/Modules/Identity/Http/Requests/LoginRequest.php`
- `app/Modules/Identity/Http/Controllers/LoginController.php`
- `app/Modules/Identity/Http/Routes/api.php`
- `app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php`
- `app/Modules/Identity/Swagger/Identity.php`
- Tests: `LoginTest` (11 tests).

## Behavior

- Validates `email` and `password`.
- `Hash::check()` for verification.
- Sanctum token issued on success.
- Response: `{ message: "Login successful.", data: { token, user } }`.
- Same generic `401 { "message": "Invalid credentials." }` for all credential failures.
- `has_bot_access` not required and not modified.
- `access_expiry` not modified.

## Login Batch Gate

- [x] `POST /api/login` implemented inside Identity.
- [x] Thin Identity module matching project conventions.
- [x] `Hash::check()` verification.
- [x] Sanctum token issued on success.
- [x] Standard `{ message, data }` envelope.
- [x] Same generic 401 body for every credential failure.
- [x] `has_bot_access` not required and not modified.
- [x] `access_expiry` not modified.
- [x] Soft-deleted users excluded.
- [x] `/api/register` unchanged.
- [x] Google OAuth unchanged.
- [x] `App\Models\User` unchanged.
- [x] No migration, schema, or composer change.
- [x] Swagger updated in Identity module.
- [x] Full test suite green.
- [x] Pint green on the affected scope.
- [x] Human approval to close the Login batch.

Login API batch is `COMPLETE / CLOSED / VERIFIED`.

---

# Post-Phase-8 Completed Branches

The following branches were completed after Phase 8 without being opened as a new numbered phase.

---

## Gmail Polling Hardening

Status: `COMPLETE / CLOSED / VERIFIED`.

## Delivered

**Runtime:**

- `PollGmailCommand` — self-email filter, `hasInProgressForWorkflow` check, per-trigger cache lock, cursor advancement past skipped messages, `next_poll_at` scheduling, rate limiter at dispatch.
- `RunWorkflowJob` — `ShouldQueue`, `ShouldBeUnique`, `$tries = 3`, `$backoff = [30, 120, 600]`, `$uniqueFor = 3600`.
- `RecoverFailedPollExecutionsCommand` — `MAX_RETRIES = 3`, `GRACE_MINUTES = 10`, `BATCH_SIZE = 100`, CAS claim, `retry_of_id` lineage.

**Schema:**

- `workflow_triggers.next_poll_at` + due index.
- `executions.retry_attempts` + `executions.retry_of_id` + recovery scan index.

**Repository:**

- `WorkflowRepository::listActiveWithTriggerDue()`.
- `ExecutionRepository::listFailedPollExecutionRoots()`.
- `ExecutionRepository::claimFailedPollExecution()`.

**Action:**

- `ActivateWorkflowAction` resets `next_poll_at = null` on activation.
- `UpsertWorkflowTriggerAction` preserves `next_poll_at` on update.

## Gmail Polling Hardening Gate

- [x] Unique job per logical Gmail event.
- [x] Overlap detection before dispatch.
- [x] Cursor advances past self-authored and already-seen messages.
- [x] `next_poll_at` respects `interval_minutes`.
- [x] Recovery command with CAS and lineage.
- [x] Rate limiter at dispatch.
- [x] Per-trigger cache lock.
- [x] Focused tests green.

Gmail polling hardening is `COMPLETE / CLOSED / VERIFIED`.

---

## Gmail Self-Email Loop Incident — Resolution

Status: `RESOLVED / VERIFIED`.

## Incident

A Gmail workflow that sent a reply to an incoming email could re-trigger itself, because the outbound reply landed in the same Gmail INBOX that the trigger listened to. Before hardening, this produced unbounded execution growth.

## Root cause

No self-exclusion filter existed in the polling runtime.

## Resolution

`PollGmailCommand::filterOutSelfEmails` compares each fetched message's `From:` header against the connected Gmail account email, case-insensitively, supporting both raw-email and display-name forms. Self-authored messages are skipped before dispatch, logged at INFO level, and the cursor advances past them.

## Verification

Live Gmail verification during a diagnostic session confirmed the filter breaks the loop.

## Incident Gate

- [x] Root cause identified.
- [x] Mitigation implemented.
- [x] Case-insensitive, display-name-aware comparison verified.
- [x] Cursor advancement past skipped messages verified.
- [x] Empirical live-Gmail verification complete.
- [x] Log evidence captured.
- [x] Regression coverage in focused tests.

Gmail self-email loop incident is `RESOLVED / VERIFIED`.

---

## Workflow API E2E Audit

Status: `COMPLETE / CLOSED / VERIFIED`.

## Goal

Verify the public HTTP API surface for the entire workflow lifecycle.

## Delivered

- `tests/Feature/Audit/WorkflowApiAuditTest.php` — 26 tests / 105 assertions.

## Audit Gate

- [x] Full lifecycle verified through HTTP.
- [x] Cross-user ownership verified.
- [x] Validation verified.
- [x] Scheduler semantics verified.
- [x] Catalog contract verified.
- [x] Manual execution contract verified.
- [x] Full suite green at audit close.

Workflow API E2E audit is `COMPLETE / CLOSED / VERIFIED`.

---

## Path A — `config: []` → `{}` Contract Fix

Status: `COMPLETE / CLOSED / VERIFIED`.

## Bug

The Catalog and Trigger responses serialized an empty `config` object as a JSON array.

## Fix — production files changed

- `app/Modules/Integrations/Application/Services/CatalogProjector.php`
- `app/Modules/Automation/Application/DTOs/TriggerData.php`

## Fix — test file changed

- `tests/Feature/Audit/WorkflowApiAuditTest.php`

## Fix Gate

- [x] Catalog empty config serializes as `{}`.
- [x] Trigger empty config serializes as `{}`.
- [x] Non-empty configs unchanged.
- [x] No other production file touched.
- [x] No other test file touched.
- [x] `has_bot_access` untouched.
- [x] Full suite green after fix.

Path A is `COMPLETE / CLOSED / VERIFIED`.

---

# Production Scheduler Hardening

Post-Phase-8 hardening effort. Not a numbered phase.

## Batch 1 — Foundation

Status: `COMPLETE`.

Deliverables:

- `TriggerCoordinator`
- `TriggerStrategy` contract
- `TriggerStrategyRegistry`
- `SchedulerTickService` (global lock, tick orchestration)
- `TickResult`, `StrategyResult` DTOs
- `WorkflowRepository::listAllActiveWithTriggerDue()`
- `config/internal_scheduler.php`

No schema change.

## Batch 2 — Schedule Strategy

Status: `COMPLETE`.

Deliverables:

- `ScheduleStrategy` with self-enforcing due-check (`nextPollAt <= $now`)
- Idempotency key derived from `next_poll_at`
- Synchronous `RunWorkflowAction::execute()`

## Batch 3 — Gmail Poll Strategy

Status: `COMPLETE`.

Deliverables:

- `GmailPollStrategy` extracted from `PollGmailCommand`
- Self-email filter preserved
- Cursor and ordering semantics preserved
- Synchronous `RunWorkflowAction::execute()` — no queue dispatch

## Batch 4 — Internal Scheduler API

Status: `COMPLETE`.

Deliverables:

- `POST /api/internal/scheduler/tick`
- `InternalSchedulerMiddleware` — bearer token, rate limit, misconfig detection
- `InternalSchedulerTickController`
- Global tick lock (`scheduler-tick-global`)
- New env vars documented

## Strategy Test Coverage

Status: `COMPLETE`.

Deliverables:

- `tests/Feature/Execution/ScheduleStrategyTest.php` — 3 tests / 14 assertions
- `tests/Feature/Execution/GmailPollStrategyTest.php` — 3 tests / 13 assertions
- `tests/Feature/Internal/SchedulerTickTest.php` — 6 tests / 17 assertions
- `tests/Feature/Execution/TriggerCoordinatorTest.php` — 3 tests / 5 assertions

## Scheduler Gate

- [x] Trigger Coordinator exists.
- [x] TriggerStrategy contract exists.
- [x] `ScheduleStrategy` and `GmailPollStrategy` implemented.
- [x] Internal tick endpoint implemented and authenticated.
- [x] Global lock implemented.
- [x] Due query is generic (no Gmail hardcoding).
- [x] Execution path reaches `RunWorkflowAction`.
- [x] No queue worker required for the new scheduler path.
- [x] Strategy test coverage green.
- [x] Full suite green at 704 tests / 2117 assertions.

## Batches 5–8

Status: `NOT STARTED`.

- Batch 5 — Recovery generalization and stuck-`running` sweep.
- Batch 6 — `next_poll_at` index optimization.
- Batch 7 — Apps Script production setup and token configuration.
- Batch 8 — Final production documentation and deployment checklist.

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
| Post-Path-A full suite | 689 | 2068 |
| Post scheduler Batches 1–4 | 698 | 2090 |
| **Post scheduler + strategy coverage (current)** | **704** | **2117** |

---

# Phase 9 — Security Hardening

Status: `LOCKED`.

---

# Phase 10 — Legacy Deprecation / Cleanup

Status: `LOCKED`.

Includes:

- Emptied `EmailController.php`.
- Emptied `GoogleSheetsController.php`.
- Orphaned `CalendarController.php`.
- Orphaned `GoogleDocsController.php`.
- Orphaned `GoogleAnalyticsController.php`.
- Orphaned Google service classes.
- Leftover comments in `routes/api.php`.
- Dead `WorkflowSnapshotBuilder::stepSnapshot()` method introduced during Batch 6.4.
- Pint style cleanup on the previously flagged files.

---

# Phase Transition Rule

```text
Current Phase → Implementation Complete → Tests Pass → Review →
State Updated → Human Approval → Next Phase Unlocked
```

No automatic transition.

Post-Phase-8 branches recorded in this document do **not** open a new numbered phase.

---

# If a Gate Fails

Phase remains IN PROGRESS.

Document the failure, identify the cause, fix within the current phase, re-run verification, and request approval.

---

# Emergency Production Issue

Stop. Preserve state. Identify rollback. Restore production behavior. Document. Do not continue until stability is restored.
