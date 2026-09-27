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
- Compatible with Laravel 12.31.1 / PHP 8.2.12.

**Structure:**

- Global configuration: `app/Modules/Swagger/OpenApi.php`.
- Per-module path files: Identity, Connections, Automation, Execution, Integrations (Gmail, Calendar, Sheets, Docs, Analytics).

**Configuration:**

- `config/l5-swagger.php` published.
- Scan path: `base_path('app/Modules')`.
- No annotations in runtime controllers, FormRequests, DTOs, repositories, or services.

**Coverage:**

- 57 operations, 57 unique operationIds at Phase 8 close.
- Additional operations added by later batches (Catalog, Login).

**Security:**

- Sanctum bearer scheme documented as `bearerAuth` and applied to protected operations.

**Error responses:**

- 409 capability error structure (`CapabilityError`) documented for activation.
- 422 validation error structure documented.
- 401 / 404 / 409 / 500 documented where actually returned.

**Excluded:**

- `/api/test/login` (dev-only).
- Facebook legacy endpoints.
- Console commands.
- All deferred Phase 7 capabilities.

## Phase 8 Verification Results

```text
php artisan l5-swagger:generate
→ exit 0, no warnings

storage/api-docs/api-docs.json
→ 57 operationIds, 57 unique
→ JSON valid

composer test
→ 607 passed / 1749 assertions / 0 failures

vendor/bin/pint --test app/Modules
→ PASS (307 files)
```

## Phase 8 Gate

- [x] Swagger/OpenAPI tooling installed and compatible.
- [x] Modular Swagger structure under `app/Modules/**/Swagger`.
- [x] Global configuration in `app/Modules/Swagger/OpenApi.php`.
- [x] Per-module path files.
- [x] No annotations in runtime code.
- [x] L5-Swagger scan path configured to `app/Modules`.
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

Provide a stable, read-only, code-defined Catalog / Discovery contract so the mobile application can render the automation UI without hardcoding or guessing available integrations, triggers, actions, capabilities, scopes, and configuration fields.

## Scope

- `GET /api/catalog`, `auth:sanctum`, operationId `catalog.show`, tag `Catalog`.
- Response derived from `IntegrationCatalog` — single source of truth.
- Field metadata added to `ActionDefinition` / `TriggerDefinition` as optional `array $fields = []`.
- New `FieldDefinition` value object.
- No second registry, no DB table, no form-builder framework.

## Non-Goals

- New runtime behavior.
- New providers.
- Changes to `CapabilityScopeMap`, OAuth, Connections, Execution, or Automation runtime.
- Field metadata for definitions without an authoritative runtime handler.

## Delivered

**Core value object:**

- `app/Modules/Integrations/Core/ValueObjects/FieldDefinition.php`

**Application:**

- `app/Modules/Integrations/Application/Services/CatalogProjector.php`

**Http:**

- `app/Modules/Integrations/Http/Controllers/CatalogController.php`
- `app/Modules/Integrations/Http/Routes/api.php`

**Definition extension:**

- `app/Modules/Integrations/Core/Entities/ActionDefinition.php`
- `app/Modules/Integrations/Core/Entities/TriggerDefinition.php`

**Verified field metadata:**

- `GmailIntegration` — `send_email` carries `to` / `subject` / `body`; `new_email_received` carries `label_id`.

**Swagger:**

- `app/Modules/Integrations/Swagger/Catalog.php`.

**Tests:**

- `tests/Feature/Integrations/CatalogEndpointTest.php` — 11 tests.
- `tests/Unit/Integrations/FieldDefinitionTest.php` — 3 tests.

## Verification Results

```text
composer test
→ 621 passed / 1893 assertions / 0 failures

vendor/bin/pint --test app/Modules
→ PASS (311 files)

php artisan l5-swagger:generate
→ exit 0, no warnings
```

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

Add a first-class email/password login endpoint that authenticates an already-provisioned user, issues a Sanctum bearer token, and returns the authenticated user in the standard API envelope.

## Scope

- `POST /api/login` (public, no auth).
- Implemented inside the existing Identity bounded context using a thin-module layout.
- Reads `App\Models\User` directly; no repository abstraction introduced.
- Does not create users.
- Does not modify `/api/register`, Google OAuth, Sanctum config, or the `User` model.
- No schema or migration change.

## Delivered

**Application:**

- `app/Modules/Identity/Application/DTOs/LoginInput.php`
- `app/Modules/Identity/Application/Actions/LoginAction.php`

**Http:**

- `app/Modules/Identity/Http/Requests/LoginRequest.php`
- `app/Modules/Identity/Http/Controllers/LoginController.php`
- `app/Modules/Identity/Http/Routes/api.php`

**Infrastructure:**

- `app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php`
- Registered in `bootstrap/providers.php`.

**Swagger:**

- `app/Modules/Identity/Swagger/Identity.php` — `POST /api/login`, operationId `auth.login`, tag `Authentication`.

**Tests:**

- `tests/Feature/Identity/LoginTest.php` — 11 tests.

**Documentation:**

- `docs/06_MOBILE_API_CONTRACT.md` — v1.0.3, login contract documented.

## Behavior

- Validates `email` and `password`.
- `Hash::check()` for verification.
- Sanctum token issued on success.
- Response: `{ message: "Login successful.", data: { token, user } }`.
- Same generic `401 { "message": "Invalid credentials." }` for unknown email, wrong password, soft-deleted user, and unusable stored password.
- `has_bot_access` is not required and not modified.
- `access_expiry` is not modified.
- Soft-deleted users are excluded via SoftDeletes.

## Verification Results

```text
php artisan test --filter=LoginTest
→ 11 passed / 36 assertions

php artisan test
→ 632 passed / 1929 assertions

composer test
→ 632 passed / 1929 assertions / 26.32s

vendor/bin/pint --test app/Modules/Identity tests/Feature/Identity
→ PASS (8 files)
```

Test delta: 621 → 632 = +11. No regressions.

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

## Login Batch — Closure Criterion

The Login API batch is formally closed. Status: `COMPLETE / CLOSED / VERIFIED`.

---

# Post-Phase-8 Completed Branches

The following branches were completed after Phase 8 without being opened as a new numbered phase. They are recorded here so the phase history remains accurate and no Phase 9 is implied.

---

## Gmail Polling Hardening

Status: `COMPLETE / CLOSED / VERIFIED`.

## Goal

Address operational and safety defects in the Gmail polling runtime:

- duplicate queued jobs for the same logical Gmail event
- event loss when a workflow execution was already in progress
- cursor re-reading self-authored messages
- non-retryable queued executions
- no recovery for failed polling executions
- no rate limit on workflow dispatch
- concurrent pollers processing the same trigger
- no explicit polling cadence

## Delivered

**Runtime:**

- `PollGmailCommand` — self-email filter, `hasInProgressForWorkflow` check, per-trigger `Cache::lock('poll-trigger:{id}', 90)`, cursor advancement past skipped messages, `next_poll_at` scheduling per `interval_minutes`, `RateLimiter` at dispatch.
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

**Tests:**

- `tests/Feature/Execution/PollGmailCommandTest.php`
- `tests/Feature/Execution/PollGmailCursorAdvancesOverSelfEmailsTest.php`
- `tests/Feature/Execution/PollGmailDuplicateDispatchTest.php`
- `tests/Feature/Execution/PollGmailIdempotencyAndCursorTest.php`
- `tests/Feature/Execution/PollGmailRateLimitTest.php`
- `tests/Feature/Execution/PollGmailSelfEmailExclusionTest.php`
- `tests/Feature/Execution/RecoverFailedPollExecutionsTest.php`
- `tests/Feature/Execution/ReservedIdempotencyNamespaceTest.php`
- `tests/Feature/Execution/RunWorkflowJobRetryLinkTest.php`
- `tests/Feature/Automation/ActivateWorkflowResetsNextPollAtTest.php`
- `tests/Feature/Automation/WorkflowTriggerPollCursorTest.php`

## Gmail Polling Hardening Gate

- [x] Unique job per logical Gmail event.
- [x] Overlap detection before dispatch.
- [x] Cursor advances past self-authored and already-seen messages.
- [x] `next_poll_at` respects `interval_minutes`.
- [x] Recovery command with CAS and lineage.
- [x] Rate limiter at dispatch.
- [x] Per-trigger cache lock.
- [x] Focused tests green.
- [x] Gmail loop incident closed (next section).

Gmail polling hardening is `COMPLETE / CLOSED / VERIFIED`.

---

## Gmail Self-Email Loop Incident — Resolution

Status: `RESOLVED / VERIFIED`.

## Incident

A Gmail workflow that sent a reply to an incoming email could re-trigger itself, because the outbound reply landed in the same Gmail INBOX that the trigger listened to. Before hardening, this produced unbounded execution growth.

## Root cause

No self-exclusion filter existed in the polling runtime.

## Resolution

`PollGmailCommand::filterOutSelfEmails` compares each fetched message's `From:` header against the connected Gmail account email, case-insensitively, supporting both raw-email and display-name forms. Self-authored messages are skipped before dispatch, logged at INFO level, and the cursor advances past them so they are not re-fetched.

## Verification

Live Gmail verification during a diagnostic session:

- Two self-authored replies placed in INBOX by a controlled test workflow.
- Next poll tick observed the two self-authored messages, filtered both, dispatched zero `RunWorkflowJob` instances.
- Log evidence: `Gmail poll: skipped self-authored email` for both message IDs at the exact tick timestamp.
- Cursor advanced past the self-authored messages.
- No new executions created.

## Incident Gate

- [x] Root cause identified.
- [x] Mitigation implemented in `PollGmailCommand`.
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

Verify the public HTTP API surface for the entire workflow lifecycle — the surface the mobile / frontend client depends on — without relying only on internal action- or command-level tests.

## Delivered

- `tests/Feature/Audit/WorkflowApiAuditTest.php` — 26 tests / 105 assertions.

Coverage:

- Authentication (`POST /api/login`, `GET /api/user`)
- Catalog (`GET /api/catalog`)
- Connections (`GET /api/connections`)
- Gmail labels (`GET /api/gmail/labels`)
- Workflow CRUD, trigger upsert, step upsert, activate / pause / resume / delete / restore
- Manual execution — empty body, valid payload, idempotency replay, reserved idempotency prefixes
- `interval_minutes` full validation sweep (1, 5, 10, 1440, 0, -1, 1441, 5.5, "5", null, omitted)
- Scheduler semantics — paused / draft / future-`next_poll_at` exclusion
- Cross-user ownership matrix
- Unauthenticated requests (401)
- Negative testing with no partial DB mutation

## Findings

- Audit was read-only with respect to production code.
- One confirmed bug was identified and closed in the same batch (Path A, below).
- Documentation-only ambiguities were recorded for a future documentation batch.

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

The Catalog and Trigger responses serialized an empty `config` object as a JSON array (`"config": []`) instead of a JSON object (`"config": {}`), contradicting the mobile contract and the Swagger `CatalogAction` schema (both declare `config` as `type: object`).

## Fix — production files changed (only)

- `app/Modules/Integrations/Application/Services/CatalogProjector.php`
- `app/Modules/Automation/Application/DTOs/TriggerData.php`

Non-empty configs are byte-for-byte identical to before.

## Fix — test file changed (only)

- `tests/Feature/Audit/WorkflowApiAuditTest.php`

## Verification Results

```text
php artisan test --filter=WorkflowApiAudit
→ 26 passed / 105 assertions

php artisan test --filter=CatalogEndpointTest
→ 13 passed / 67 assertions

php artisan test --filter=WorkflowTriggerHttpTest
→ 14 passed / 35 assertions

composer test (full suite)
→ 689 passed / 2068 assertions / 41.08s
```

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
| **Post-Path-A full suite (current)** | **689** | **2068** |

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

Post-Phase-8 branches recorded in this document do **not** open a new numbered phase. They are completed branches that ran under explicit authorization without being designated as a phase.

---

# If a Gate Fails

Phase remains IN PROGRESS.

Document the failure, identify the cause, fix within the current phase, re-run verification, and request approval.

---

# Emergency Production Issue

Stop. Preserve state. Identify rollback. Restore production behavior. Document. Do not continue until stability is restored.
