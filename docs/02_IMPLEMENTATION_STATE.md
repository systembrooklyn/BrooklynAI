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

Post-Phase-8 completed branches — see below.

## Status

```text
Phase 0–8 COMPLETE / CLOSED / VERIFIED.
Post-Phase-8 completed branches are recorded below.
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
- Gmail polling hardening — COMPLETE / CLOSED / VERIFIED (see below)
- Workflow API E2E audit — COMPLETE / CLOSED / VERIFIED (see below)
- Path A `config: []` → `{}` contract fix — COMPLETE / CLOSED / VERIFIED (see below)

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

Closed through the approved Batch 6.1–6.5 scope. Reference `docs/04_PHASE_GATES.md`.

---

# Phase 7 — Gmail Automation MVP

Status: `COMPLETE / CLOSED / VERIFIED`.

## Phase 7 MVP Scope — satisfied

- Gmail `new_email_received` trigger runtime. (7.3)
- Gmail polling runtime. (7.3)
- Gmail label selection / filtering. (7.3)
- Existing `send_email` action remains usable. (7.3 / 7.4)
- Trigger payload includes attachment metadata for future compatibility. (7.2)
- Capability-based Gmail scope escalation at connection creation. (7.1)
- Activation-time capability validation for Gmail-gated triggers and actions. (7.4)

Batches:

- Batch 7.1 — COMPLETE / CLOSED / VERIFIED
- Batch 7.2 — COMPLETE / CLOSED / VERIFIED
- Batch 7.3 — COMPLETE / CLOSED / VERIFIED
- Batch 7.4 / 7.4b — COMPLETE / CLOSED / VERIFIED
- Batch 7.5 — COMPLETE / CLOSED / VERIFIED (scope-classification only)

Reference `docs/04_PHASE_GATES.md` for per-batch gate items and verification results.

---

# Phase 8 — Swagger / OpenAPI Documentation

Status: `COMPLETE / CLOSED / VERIFIED` (human approved).

## Phase 8 Goal

Provide accurate, maintainable, frontend-consumable Swagger/OpenAPI documentation for the API that actually exists.

## Tooling

- `darkaonline/l5-swagger` **11.1.0**
- `zircote/swagger-php` **6.10.0** (transitive)
- `swagger-api/swagger-ui` **v5.33.0** (transitive)
- Compatible with Laravel 12.31.1 / PHP 8.2.12.

## Structure

Global configuration:

- `app/Modules/Swagger/OpenApi.php` — `info`, `server`, `securityScheme` (`bearerAuth`), `Tag` list, shared schemas (`ErrorResponse`, `ValidationError`, `CapabilityError`) and shared responses (`Unauthorized`, `NotFound`, `ValidationErrorResponse`, `GenericError`).

Per-module path files:

- `app/Modules/Identity/Swagger/Identity.php`
- `app/Modules/Connections/Swagger/Connections.php`
- `app/Modules/Automation/Swagger/Automation.php`
- `app/Modules/Execution/Swagger/Execution.php`
- `app/Modules/Integrations/Swagger/Gmail.php`
- `app/Modules/Integrations/Swagger/Calendar.php`
- `app/Modules/Integrations/Swagger/Sheets.php`
- `app/Modules/Integrations/Swagger/Docs.php`
- `app/Modules/Integrations/Swagger/Analytics.php`

Runtime controllers, FormRequests, DTOs, repositories, and services carry **no** Swagger attributes.

## L5-Swagger scan configuration

- `config/l5-swagger.php` published.
- `documentations.default.paths.annotations` set to `base_path('app/Modules')`.

## Coverage

Documented endpoints (57 operations, 57 unique operationIds) in Phase 8. The Catalog batch added 1 more. The Login batch added 1 more (`auth.login`).

## Explicitly excluded

- `POST /api/test/login` (development-only).
- Facebook legacy endpoints.
- Console commands.
- Deferred Phase 7 capabilities.

## Phase 8 Verification Results

```text
php artisan l5-swagger:generate
→ exit 0, no warnings

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
- [x] No annotations in runtime controllers, FormRequests, DTOs, repositories, or services.
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

Status: `COMPLETE / CLOSED / VERIFIED` (human approved).

## Goal

Provide a stable, read-only, code-defined Catalog / Discovery contract so the mobile application can render the automation UI without hardcoding or guessing available integrations, triggers, actions, capabilities, scopes, and configuration fields.

## Scope — satisfied

- `GET /api/catalog` endpoint, `auth:sanctum`.
- Operation ID `catalog.show`, tag `Catalog`.
- Response envelope consistent with existing API convention: `{ message, data: { integrations: [...] } }`.
- Read-only, deterministic, code-defined.
- Derived from `IntegrationCatalog` and the existing definitions — no second source of truth.
- No database table, no duplicated definitions, no form-builder framework.

## Field metadata design

New immutable value object:

- `app/Modules/Integrations/Core/ValueObjects/FieldDefinition.php`

Properties: `key`, `label`, `type`, `required`, `description`, `default`, `options`. No subclasses. No factories. No JSON-Schema infrastructure.

Type set: `string`, `text`, `email`, `boolean`, `integer`, `select`.

## Definition extension

`ActionDefinition` and `TriggerDefinition` gained an optional trailing `array $fields = []`. Existing constructor call sites remain valid. Runtime code does not read `fields` anywhere; only `CatalogProjector` does.

## Verified field metadata (Gmail only)

- `send_email`: `to` (email, required), `subject` (string, required), `body` (text, required).
- `new_email_received`: `label_id` (string, optional). No static options — dynamic values via `GET /api/gmail/labels`.
- `reply_to_email`, `create_draft`, and every Calendar / Sheets / Docs / Analytics action return `fields: []`.

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
- [x] Stable machine keys for integrations, triggers, actions, fields.
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

Status: `COMPLETE / CLOSED / VERIFIED` (human approved).

## Goal

Add a first-class email/password login endpoint that authenticates an already-provisioned user, issues a Sanctum bearer token, and returns the authenticated user in the standard API envelope.

## Scope — satisfied

- Endpoint: `POST /api/login`.
- Implemented inside the existing Identity bounded context using a thin-module pattern (Application + Http + Provider), consistent with the other modules.
- Does not replace, refactor, or duplicate the existing `POST /api/register`.
- Does not modify Google OAuth.
- Does not modify Sanctum config.
- Does not modify the `App\Models\User` model.
- Does not modify the schema.

## Architecture

```text
POST /api/login
    ↓
Identity LoginController (thin, __invoke)
    ↓
LoginRequest (validates email + password)
    ↓
LoginInput DTO
    ↓
LoginAction
    ↓
App\Models\User::where('email')->first()   (SoftDeletes excludes trashed)
    ↓
Hash::check()
    ↓
$user->createToken($user->name)->plainTextToken
    ↓
200 { message, data: { token, user } }
```

## Behavior

- Validates `email` (required, string, valid email) and `password` (required, string).
- Authenticates using `Hash::check()`.
- Creates a Sanctum token on success.
- Returns the authenticated user in the response.
- Does NOT require `has_bot_access` to be true. Authentication and product access are separate concerns.
- Does NOT modify `has_bot_access`.
- Does NOT modify `access_expiry`.
- Does NOT create a user if the email is unknown.
- Does NOT normalize email casing.

Invalid credentials — same generic response for unknown email, wrong password, unusable stored password, or soft-deleted user:

```json
{ "message": "Invalid credentials." }
```

HTTP 401. No token is issued on failure.

Validation failures follow the standard Laravel convention: HTTP 422 with `{ message, errors }`.

## Files created

- `app/Modules/Identity/Application/DTOs/LoginInput.php`
- `app/Modules/Identity/Application/Actions/LoginAction.php`
- `app/Modules/Identity/Http/Requests/LoginRequest.php`
- `app/Modules/Identity/Http/Controllers/LoginController.php`
- `app/Modules/Identity/Http/Routes/api.php`
- `app/Modules/Identity/Infrastructure/Providers/IdentityServiceProvider.php`
- `tests/Feature/Identity/LoginTest.php`

## Files modified

- `app/Modules/Identity/Swagger/Identity.php` — added `POST /api/login` path (operationId `auth.login`).
- `bootstrap/providers.php` — added `IdentityServiceProvider::class`.
- `docs/06_MOBILE_API_CONTRACT.md` — documented the Login endpoint (v1.0.3).

No other existing file was modified for the Login batch.

## Register / Google — unchanged

- `POST /api/register` remains the existing business/provisioning API. It is unchanged in validation, status codes, response shape, password handling, existing-user behavior, `has_bot_access`, `access_expiry`, and route.
- The Register flow (Google Sheets → Apps Script → `POST /api/register` → user provisioning → AI Modules access) is untouched.
- Google OAuth (`GoogleAuthController`, redirect, callback, scopes, token storage) is unchanged.
- The only Login behavior that touches Google-created users is: if a User has both a Google identity and a valid password, `/api/login` authenticates that same User.

## Database

- `users_email_unique` was verified present before implementation.
- No migration was created.
- No schema change was made.

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

Test delta: 621 → 632 = +11 (Login tests only). No regressions.

## Login Batch Gate

- [x] `POST /api/login` implemented inside Identity.
- [x] Thin Identity module (Application + Http + Provider) matching project conventions.
- [x] `Hash::check()` verification.
- [x] Sanctum token issued on success.
- [x] Standard `{ message, data: { token, user } }` envelope.
- [x] Same generic 401 body for every credential failure.
- [x] `has_bot_access` not required and not modified.
- [x] `access_expiry` not modified.
- [x] Soft-deleted users excluded via SoftDeletes.
- [x] `/api/register` unchanged.
- [x] Google OAuth unchanged.
- [x] `App\Models\User` unchanged.
- [x] No migration, schema, or composer change.
- [x] Swagger documented under `app/Modules/Identity/Swagger/Identity.php`.
- [x] Full test suite green.
- [x] Pint green on the affected scope.
- [x] Human approval to close the Login batch.

Login API batch is `COMPLETE / CLOSED / VERIFIED`.

---

# Post-Phase-8 Completed Branches

These branches were completed after Phase 8 without being designated as a new numbered phase. They are recorded here to keep the project state accurate. No Phase 9 has been opened.

---

## Gmail Polling Hardening

Status: `COMPLETE / CLOSED / VERIFIED`.

## Goal

Address operational and safety defects in the Gmail polling runtime that were identified after Phase 7 close:

- duplicate queued jobs for the same logical Gmail event
- permanent event loss when a workflow execution was already in progress
- Gmail cursor re-reading self-authored messages indefinitely
- non-retryable queued execution
- no recovery for failed polling executions
- no rate limit on workflow execution dispatches
- concurrent pollers processing the same trigger
- no explicit polling cadence

## Scope — satisfied

- `RunWorkflowJob` — `ShouldQueue`, `ShouldBeUnique`, `$tries = 3`, `$backoff = [30, 120, 600]`, `$uniqueFor = 3600`. Unique on workflow ID + idempotency key. Does not swallow `ExecutionAlreadyRunningException` so queue retry semantics apply.
- `PollGmailCommand` — self-email filter, `hasInProgressForWorkflow` pre-check, `Cache::lock('poll-trigger:{id}', 90)`, cursor advancement past skipped messages, `next_poll_at` scheduling per `interval_minutes`, `RateLimiter` at dispatch time.
- `RecoverFailedPollExecutionsCommand` — `MAX_RETRIES = 3`, `GRACE_MINUTES = 10`, `BATCH_SIZE = 100`, CAS claim, `retry_of_id` lineage.
- Migrations — `workflow_triggers.next_poll_at` and `executions.retry_attempts` + `executions.retry_of_id`.
- `ActivateWorkflowAction` resets `next_poll_at` to `null` on activation.
- `UpsertWorkflowTriggerAction` preserves `next_poll_at` on update.
- `WorkflowRepository::listActiveWithTriggerDue()` added.

## Verification Results

Focused polling suite (files listed below) green:

- `tests/Feature/Execution/PollGmailCommandTest.php` (28 tests)
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
- [x] Full suite green at time of hardening.
- [x] Loop incident closed (see next section).

Gmail polling hardening is `COMPLETE / CLOSED / VERIFIED`.

---

## Gmail Self-Email Loop Incident — Diagnosis and Resolution

Status: `RESOLVED / VERIFIED`.

## Incident

Before the hardening above was applied, a Gmail workflow configured to reply to incoming mail could fire on its own outbound reply. Since the outbound reply lands in the same Gmail INBOX as the trigger source, the workflow re-triggered itself. Observed escalation: 3s → 28s → 40s per tick with each tick processing progressively more self-authored messages. If left unbounded this would exhaust Gmail quota and risk account suspension.

## Root cause

No self-exclusion filter existed in the polling runtime. Any INBOX message was treated as a new event, including messages authored by the same Gmail account the trigger was configured against.

## Resolution

`PollGmailCommand::filterOutSelfEmails` compares each fetched message's `From:` header against the connected Gmail account email. Comparison is case-insensitive and supports both raw email and display-name form. Self-authored messages are skipped before dispatch, logged at INFO level, and the Gmail cursor advances past them so they are not re-fetched.

## Verification

Diagnostic session used the actual live Gmail API. Results:

- Two self-authored replies placed in INBOX by a controlled test workflow.
- Next poll tick observed the two self-authored messages, filtered both, dispatched zero `RunWorkflowJob` instances.
- Log entries at the exact tick timestamp: `Gmail poll: skipped self-authored email` for both message IDs.
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

Verify the public HTTP API surface that the mobile / frontend client depends on for the entire workflow lifecycle, without relying only on internal action- or command-level tests.

## Scope

- `tests/Feature/Audit/WorkflowApiAuditTest.php` — 26 tests / 105 assertions.

Coverage:

- `POST /api/login`, `GET /api/user`, `GET /api/catalog`, `GET /api/connections`, `GET /api/gmail/labels`
- Workflow CRUD, trigger upsert, step upsert, activate / pause / resume / delete / restore
- Manual execution — empty body, valid payload, idempotency replay, reserved idempotency prefixes
- `interval_minutes` full validation sweep (1, 5, 10, 1440, 0, -1, 1441, 5.5, "5", null, omitted)
- Scheduler semantics — paused / draft / future-`next_poll_at` exclusion
- Cross-user ownership matrix across every workflow-owned endpoint (all return 404)
- Unauthenticated requests (401)
- Negative testing with no partial DB mutation

## Findings

- The audit was read-only with respect to production code.
- One confirmed bug was identified and closed in the same batch as Path A (below).
- Documentation-only ambiguities were recorded (see "Open / Deferred" section).

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

The Catalog and Trigger responses serialized an empty `config` object as a JSON array (`"config": []`) instead of a JSON object (`"config": {}`). The mobile contract `docs/06_MOBILE_API_CONTRACT.md` §4.4 documents `config` as an object, and `app/Modules/Integrations/Swagger/Catalog.php` declares `config` as `type: object`. Mobile clients that type-strict on objects would fail to decode the payload.

## Fix — production files changed (only)

- `app/Modules/Integrations/Application/Services/CatalogProjector.php` — `projectConfig()` return type widened from `array` to `array|object`; the empty case returns `(object) []`.
- `app/Modules/Automation/Application/DTOs/TriggerData.php` — `toArray()` casts the `config` value to `(object) []` when the underlying array is empty.

Non-empty configs are byte-for-byte identical to before.

## Fix — test file changed (only)

- `tests/Feature/Audit/WorkflowApiAuditTest.php` — one method renamed and flipped from asserting the previous (buggy) behavior to asserting `"config":{}`, plus one new method verifying the trigger upsert response's config shape for both empty and populated inputs.

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
| Login API batch | 632 | 1929 |
| Post-Login full-suite run (pre-Path-A) | 663 | 1963 |
| Post-Path-A focused (3 files) | 53 focused | 207 focused |
| **Post-Path-A full suite (current)** | **689** | **2068** |

Historical numbers through Batch 7.3 are preserved exactly as recorded at their respective closures.

---

# Production-Safe Legacy Confirmation

The following remain unchanged and intact:

- All legacy `app/Http/Controllers/Api/*` Google/Facebook/TestLogin/User controllers.
- `app/Services/*`.
- `app/Models/User.php`, `app/Models/FacebookAccount.php`.
- All existing global migrations under `database/migrations/`.
- `config/*` except the newly published `config/l5-swagger.php`.
- `bootstrap/*` except the additive `IdentityServiceProvider` line.
- All Phase 0–7 tests.

Live Google login (`redirect` / `callback`) untouched.
`users.google_access_token`, `users.google_refresh_token`, `users.google_token_expires_at` untouched.
`redirectgoogle()` legacy flow untouched.
`TestLoginController` untouched.
Facebook functionality untouched.
`POST /api/register` behaviorally untouched.

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

---

# Pending Architectural Constraints

Locked unless a new ADR changes them.

## Cross-cutting

- Modular Monolith, DDD, `app/Modules/`, five core modules
- `Core` framework-independent
- Module-owned migrations, models, HTTP, localization
- No duplicate User model
- No speculative abstractions
- No silent architectural changes

## Identity / Auth

- Live Google login (`redirect` / `callback`) protected
- `redirectgoogle()` legacy flow untouched
- `TestLoginController` untouched
- Facebook functionality untouched
- Legacy Google token fields remain during migration
- `/api/register` remains the provisioning API and is not a public signup
- `/api/login` authenticates existing users only; it does not create users
- `has_bot_access` / `access_expiry` are product entitlements, not authentication
- `App\Models\User` remains shared application infrastructure, not moved into Identity

## Integrations

- Google applications belong to Integrations
- Integrations catalog code-defined (no DB)
- `CapabilityScopeMap` code-defined; no DB table
- Client requests capabilities, never raw scopes
- `CapabilityScopeMap` is the consent-time capability-to-scope mapping
- Individual workflow definition scopes come from `ActionDefinition` / `TriggerDefinition`

## Connections

- Connections first-class
- OAuth state single-use and expiring
- Credentials encrypted
- Explicit `connectionId` never falls back to legacy `users.google_*`

## Cross-capability

- Provider batch structure per Phase 5
- Migrated features own their HTTP boundary inside their module
- Cross-capability orchestration in Application Actions
- Gmail sending has one owner: `GmailEmailSender`
- Gmail reading has one owner: `GmailMessageReader`

## Automation

- Workflow has one optional trigger
- Steps use positive unsigned smallint positions
- Positions are not reindexed
- Trigger/step `connection_id` ownership enforced
- Workflow deletion is soft delete
- Restore preserves trigger + steps
- Restore does not auto-activate
- Activation requires a trigger
- Activation validates the trigger and every step before persisting `Active`
- Trigger replacement uses FIND-THEN-UPDATE
- `WorkflowRepository::listActiveWithTriggerDue()` added during Gmail polling hardening
- `UpsertWorkflowTriggerAction` preserves `next_poll_at` on update

## Activation Capability Validation

- `WorkflowCapabilityValidator` is the validation service for capability / connection / scope checks.
- `ActionDefinition::requiredScopes` and `TriggerDefinition::requiredScopes` are authoritative for individual workflow definitions.
- `CapabilityScopeMap::resolve()` is not used for per-definition activation scope comparison.
- `CapabilityScopeMap::has()` confirms that a capability is supported.
- `capability === null` is allowed.
- Unknown integration / trigger / action are handled explicitly.
- Failed validation prevents activation and leaves the workflow `Draft`.

## Templates

- Missing template runtime path throws `TemplateResolutionFailed`
- Template syntax validated on save
- Whole-template resolution preserves resolved value type
- Embedded templates resolve to strings
- No `eval` / callables

## Execution

- Runtime executes captured `workflow_snapshot`
- Snapshots use explicit whitelist projections
- Manual execution of `Draft` / `Active` permitted; `Paused` rejected (409)
- Lifecycle: `Pending → Running → Completed | Failed`
- Idempotency enforced by `UNIQUE(workflow_id, idempotency_key)`
- Overlap check best-effort
- Narrow failure classification; programming errors propagate
- `RunWorkflowAction` is the single execution path

## Scheduler

- `workflows:run-scheduled` (Batch 6.5) — `everyMinute()` + `withoutOverlapping(5)`
- No `onOneServer()`
- Reserved `schedule:` prefix in manual idempotency keys
- Scheduler executes through `RunWorkflowAction`

## Post-Phase-8 Gmail Polling

- `workflows:poll-gmail` — separate from `workflows:run-scheduled`
- Registered in `routes/console.php` with `everyMinute()` + `withoutOverlapping(5)`
- Discovery via `WorkflowRepository::listActiveWithTriggerDue('google.gmail', 'new_email_received', $now)`
- First tick initializes `poll_cursor` to now; no Gmail query
- Subsequent ticks: `after:max(cursor - 60, 0)`
- Cursor unit: epoch seconds. Gmail `internalDate` is milliseconds.
- Deterministic ordering: `(received_at_epoch_ms, message_id)`
- Cursor advances to `max(internalDate_seconds) + 1` only after clean tick
- Idempotency key: `gmail:{workflow_id}:{message_id}`; `gmail:` reserved for manual keys
- `trigger_source='poll'`
- Payload: canonical 14-key whitelist via `GmailMessagePayloadBuilder`
- Self-email filter: case-insensitive, display-name aware, compares against connection email
- Malformed `label_id` → skip workflow; cursor unchanged
- 429/5xx → per-workflow isolation; cursor unchanged
- 404 on message fetch → skipped; cursor advances from other messages
- All-404 → cursor unchanged; continue
- `GoogleCredentialsUnavailableException` / `ConnectionNotFoundException` → skip workflow
- All other throwables propagate
- `Cache::lock('poll-trigger:{id}', 90)` prevents concurrent poller processes from processing the same trigger
- `RunWorkflowJob` implements `ShouldQueue`, `ShouldBeUnique` with `$tries = 3`, `$backoff = [30, 120, 600]`, `$uniqueFor = 3600`
- `RecoverFailedPollExecutionsCommand` scans failed roots older than 10 minutes, max 3 recovery cycles, CAS claim, `retry_of_id` lineage
- `ActivateWorkflowAction` resets `next_poll_at = null` on activation

## Post-Phase-8 Contract Fixes

- Empty `config` serializes as JSON object `{}`, not array `[]`, in Catalog and Trigger responses. Fixed in `CatalogProjector.php` and `TriggerData.php`.

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
- Runtime handlers for `reply_to_email` and `create_draft`.
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
- Any additional capability not explicitly approved.

---

# Current Task

```text
Phase 0–8 and the post-Phase-8 branches (Gmail polling hardening, Gmail loop incident
resolution, Workflow API E2E audit, Path A config fix) are COMPLETE / CLOSED / VERIFIED.

Latest verified full-suite run: 689 passed / 2068 assertions.

Production has NOT been deployed.
Phase 9 remains LOCKED.
Phase 10 remains LOCKED.
```

No new implementation batch has been authorized.

---

# Current Next Step

```text
Await explicit human authorization for the next approved step.
Production-environment verification (cron, queue worker, cache, queue driver,
OAuth redirect URIs) is required before any deployment.
Do not start Phase 9 or any new batch without explicit authorization.
```

No cleanup, no refactor, and no new code is authorized by this state file alone.

---

# State Update Rule

Update this file after every approved implementation step with: current phase, phase status, completed tasks, current task, next task, files changed, tests, known issues, pending decisions.

Never claim a phase complete without satisfying its Phase Gate or an explicitly approved waiver.
