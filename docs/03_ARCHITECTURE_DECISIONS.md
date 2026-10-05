# Architecture Decisions

This document records architectural decisions that have been explicitly agreed upon.

Decisions should not be silently reversed.

If a decision needs to change, create a new ADR explaining why.

---

# Decision Precedence

When architectural decisions overlap, the most recent accepted ADR governs the affected architectural concern.

`ADR-028` is the current locked decision for:

* Modular Monolith architecture
* physical module ownership
* `app/Modules/`
* module internal structure
* `Core` as the DDD/domain physical layer
* module-owned persistence
* module-owned HTTP
* module localization directory layout
* new-code ownership

`ADR-029` is the current locked decision for:

* the localization mechanism
* supported locales
* locale resolution rules
* translation namespaces
* what is localized and what is not

`ADR-030` is the current locked decision for:

* password reset via email OTP
* OTP lifecycle and storage
* reset endpoint contract
* rate limits and security posture for the reset flow

Earlier ADRs remain historical records.

If an earlier ADR uses terminology or physical structures that are superseded by ADR-028, ADR-029, or ADR-030, the newer ADR governs the current implementation.

---

# ADR-001 — Production Behavior Is the Source of Truth

## Status

Accepted

## Decision

The application is already in production.

Existing production behavior must be characterized and protected before significant refactoring.

## Consequence

We prioritize:

```text
Safety > Architectural Purity
```

---

# ADR-002 — Google Production Login Is Protected

## Status

Accepted

## Decision

The existing production Google:

```text
redirect()
callback()
```

flow is protected.

Its behavior must not be changed during Phase 0 and early migration without explicit approval.

## Consequence

Characterization tests must cover it before refactoring.

---

# ADR-003 — `redirectgoogle()` Is Legacy Pending Audit

## Status

Accepted

## Decision

The existing `redirectgoogle()` flow is treated as legacy/non-live until references are audited.

## Consequence

It must not be deleted or modified merely because it appears unused.

Audit:

* routes
* frontend/mobile
* jobs
* services
* controllers
* schedules
* webhooks
* configuration
* documentation

---

# ADR-004 — Legacy Google Token Columns Remain During Migration

## Status

Accepted

## Decision

The following fields remain during the compatibility period:

```text
google_access_token
google_refresh_token
google_token_expires_at
```

## Consequence

The production Google login may continue writing them until the login flow is deliberately migrated.

New Connection infrastructure should gradually become the preferred credential source.

---

# ADR-005 — Provider, Integration, and Connection Are Separate Concepts

## Status

Accepted

## Decision

These concepts are distinct:

```text
Provider
Integration
Connection
```

Example:

```text
Google = Provider
Gmail = Integration
User's Google account = Connection
```

## Consequence

Do not model every Integration as an independent OAuth account.

---

# ADR-006 — Connections Are First-Class Domain Concepts

## Status

Accepted

## Decision

A Connection represents an authenticated external account belonging to a user.

Expected uniqueness:

```text
(user_id, provider, external_account_id)
```

## Consequence

Connection lifecycle and credential handling are modeled explicitly.

---

# ADR-007 — Connection Capability Is Derived From Scopes

## Status

Accepted

## Decision

A single provider Connection may support multiple Integrations/capabilities depending on its granted scopes.

## Consequence

The system should derive capability state rather than assuming:

```text
Google connected = every Google integration connected
```

---

# ADR-008 — Integration Catalog Is Code-Defined Initially

## Status

Accepted

## Decision

The initial Integration Catalog is defined in code.

No `integrations` database table is required initially.

## Consequence

The architecture must still allow future versioning/evolution.

---

# ADR-009 — Core Must Be Framework Independent

## Status

Accepted

## Decision

In the Modular Monolith architecture, `Core` is the physical DDD/domain layer.

Core code must not depend on:

* Laravel
* Eloquent
* Illuminate
* Laravel Request
* Laravel Carbon
* Google SDK
* provider SDKs
* database implementations
* framework-specific infrastructure

## Consequence

Provider/framework-specific implementation belongs outside Core.

---

# ADR-010 — Provider-Neutral Trigger Input

## Status

Accepted

## Decision

Trigger interfaces must not accept Laravel HTTP Request objects.

Use provider-neutral structures such as:

```text
TriggerInput
WebhookPayload
```

## Consequence

HTTP conversion occurs at the boundary.

---

# ADR-011 — Linear Workflow MVP

## Status

Accepted

## Decision

The first Workflow Engine supports:

```text
Trigger → Action → Action
```

## Consequence

Do not implement branches, loops, parallel execution, AI, Agents, or MCP initially.

---

# ADR-012 — No AI, Agents, or MCP in Initial Core

## Status

Accepted

## Decision

The initial automation platform is deterministic.

AI/Agents/MCP are not part of the initial Workflow Engine.

## Consequence

The first automation core remains easier to test, reason about, and stabilize.

---

# ADR-013 — Safe Template Resolution

## Status

Accepted

## Decision

Workflow templates use controlled paths such as:

```text
{{ trigger.subject }}
{{ steps.1.output.message_id }}
```

## Consequence

No `eval()` or arbitrary code execution.

---

# ADR-014 — Safe Action Outputs

## Status

Accepted

## Decision

Action handlers should return explicitly safe outputs.

Secrets should not intentionally enter execution output.

Secret scrubbing is defense-in-depth.

## Consequence

Execution data can be exposed to future UI/debugging without exposing credentials.

---

# ADR-015 — OAuth State Is Single-Use and Expiring

## Status

Accepted

## Decision

OAuth state must have:

* expiration
* single-use semantics
* replay protection
* user/session correlation where applicable

## Consequence

OAuth callback processing must consume state atomically.

---

# ADR-016 — OAuth Redirects Must Be Safe

## Status

Accepted

## Decision

Arbitrary user-controlled OAuth redirect URLs are prohibited.

Use allowlisted origins, safe redirect identifiers, or fixed destinations.

## Consequence

OAuth callback must validate redirect destinations.

---

# ADR-017 — Gmail Polling Is Stateful

## Status

Accepted

## Decision

The initial Gmail trigger may use polling, but it must use durable state such as:

```text
historyId
cursor
last_checked_at
```

and handle:

* stale history
* duplicates
* idempotency
* rate limits
* concurrency
* locking
* retries
* initial sync

## Consequence

Naive repeated "fetch latest emails" polling is not acceptable.

---

# ADR-018 — Gmail Push Is Future Evolution

## Status

Accepted

## Decision

The architecture should allow future:

```text
Gmail watch()
→ Google Cloud Pub/Sub
→ event ingestion
```

but this is not required for the initial MVP.

---

# ADR-019 — Characterization Before Refactoring

## Status

Accepted

## Decision

Existing behavior must be characterized before significant production refactoring.

## Consequence

Phase 0 exists specifically to establish the safety net.

---

# ADR-020 — Phase Discipline

## Status

Accepted

## Decision

Phases must be completed sequentially.

No phase may be skipped.

Human approval is required before unlocking the next phase.

## Consequence

An implementation agent must never autonomously move the project to the next phase.

---

# ADR-021 — Practical DDD Over Speculative Abstraction

## Status

Accepted

## Decision

DDD should be applied pragmatically.

Interfaces/classes are created when they establish a useful boundary or have an immediate purpose.

## Consequence

Avoid empty scaffolding and unnecessary abstractions.

A single implementation is acceptable when the boundary itself has architectural value.

## Current Interpretation

This decision remains active.

Its physical architecture terminology is superseded by ADR-028.

The practical rule remains:

```text
Do not create speculative business abstractions.
```

The canonical physical structure is now defined by ADR-028.

---

# ADR-022 — Compatibility Layer Before Legacy Removal

## Status

Accepted

## Decision

Legacy behavior is migrated through compatibility layers where practical.

Typical pattern:

```text
New Connection
      ↓
Compatibility Resolver
      ↓
Legacy User Token
```

## Consequence

Migration can be incremental and reversible.

---

# ADR-023 — Two Migration Tracks

## Status

Accepted

## Decision

The project follows:

### Track A

Production-safe refactoring and reusable provider infrastructure.

### Track B

New automation platform functionality.

## Consequence

We do not need to refactor every unrelated legacy feature before creating useful automation infrastructure.

---

# ADR-024 — No Silent Architectural Changes

## Status

Accepted

## Decision

If implementation reveals that the architecture is insufficient, the agent must stop and propose a new decision.

## Consequence

Architectural changes require explicit review and approval.

ADR-028 is now the locked physical-architecture decision for the Modular Monolith.

---

# ADR-025 — Documentation Is Part of the Project State

## Status

Accepted

## Decision

The following documents are maintained inside the repository:

```text
docs/00_MASTER_PROMPT.md
docs/01_ARCHITECTURE_V4.md
docs/02_IMPLEMENTATION_STATE.md
docs/03_ARCHITECTURE_DECISIONS.md
docs/04_PHASE_GATES.md
docs/ADR-028.md
```

## Consequence

The repository itself contains the durable project context required to continue work across separate AI chats.

---

# ADR-026 — Gate Criteria May Be Waived or Narrowed With Explicit Approval

## Status

Accepted

## Decision

Phase gate criteria are the default expectation, not an unconditional requirement.

When a gate criterion conflicts with a higher-priority constraint, the criterion may be:

* Waived entirely for the phase
* Narrowed in scope
* Deferred to a later phase

Such adjustments require:

1. Explicit identification of the conflict.
2. Explicit project-owner approval.
3. Documentation of the waiver or deferral in `docs/02_IMPLEMENTATION_STATE.md`.

## Consequence

Phase completion remains meaningful and honest.

## Phase 0 Precedent

Approved:

* PHPStan — waived
* CI — waived
* Global Pint — narrowed to Phase 0-owned files
* Gmail / Sheets / Calendar happy paths — deferred

Details are recorded in `docs/02_IMPLEMENTATION_STATE.md`.

---

# ADR-027 — Phase 1 (Architecture Skeleton) Is Merged Into Phase 2

## Status

Accepted

## Context

Architecture V4 originally defined Phase 1 as "Architecture Skeleton" and Phase 2 as "Connections."

The project later adopted a Modular Monolith + DDD physical architecture.

The original Phase 1 concept was intended to establish architectural boundaries without speculative implementation.

## Problem

A standalone architecture-skeleton phase would create speculative structure before a real domain concept required it.

## Decision

Phase 1 is merged into Phase 2.

The architectural skeleton will be established when the first real module-owned domain concept — `Connection` — is implemented.

The skeleton is now established according to ADR-028:

```text
app/Modules/{Module}/
├── Core/
├── Application/
├── Console/
├── Infrastructure/
├── Http/
└── Lang/
```

The `Connection` implementation will therefore establish the first concrete use of:

```text
app/Modules/Connections/Core/
app/Modules/Connections/Application/
app/Modules/Connections/Infrastructure/
app/Modules/Connections/Http/
```

as actually required.

## Consequences

Positive:

* No speculative business scaffolding.
* Practical DDD is preserved.
* The architecture is introduced together with its first real domain concept.
* Module ownership is explicit from the first new feature.
* No broad legacy migration is required.

Negative:

* Phase 1 no longer exists as an independent implementation milestone.
* Phase 2 absorbs the architectural skeleton work.

## Migration Impact

* Existing production code remains unchanged.
* Existing Phase 0 tests remain mandatory.
* No global `app/Domain`, `app/Application`, or `app/Infrastructure` architecture is created for the new system.
* Existing legacy global code may remain temporarily.
* New functionality must follow ADR-028 module ownership.

## Superseded Physical Terminology

Any earlier references in this ADR to a generic global:

```text
Domain
Application
Infrastructure
```

architecture are superseded by ADR-028 for current implementation.

The decision to avoid speculative scaffolding remains active.

---

# ADR-028 — Modular Monolith + DDD Module Ownership

## Status

Accepted / Locked

## Decision

See:

```text
docs/ADR-028.md
```

ADR-028 is the canonical decision for the physical Modular Monolith + DDD architecture.

It defines:

* `app/Modules/` as the module root
* five core modules
* canonical module structure
* `Core` as the DDD/domain physical layer
* module ownership
* module-owned persistence
* module-owned HTTP
* module localization
* dependency rules
* new-code ownership
* legacy-code migration rules
* Connections Phase 2 ownership

## Consequence

All new module-owned functionality must follow ADR-028.

Earlier ADRs remain historical records and must not be interpreted as permission to create a conflicting global architecture.

---

# ADR-029 — Localization Mechanism

## Status

Accepted / Locked

## Context

The platform serves Arabic-speaking and English-speaking users.

Before this decision, user-facing HTTP responses were hardcoded English across every controller, and the Catalog response had no way to render human-readable labels in Arabic.

The project needed a locale mechanism that:

- works for a mobile-first API client,
- does not change existing API contract shapes,
- does not introduce a per-user stored locale (`users.locale`),
- does not change any machine-readable identifier,
- does not require touching every controller in a single batch.

The physical module layout for translations (`Lang/{ar,en}/`) was already established by ADR-028.

The mechanism that drives which locale is used at request time was not yet defined.

## Decision

### Mechanism

The only client-facing locale mechanism is the standard HTTP `Accept-Language` request header.

### Supported locales

```text
en — English (default, fallback)
ar — Arabic
```

Any locale outside this set is ignored.

### Resolution rules

1. Parse the `Accept-Language` header.
2. Split into comma-separated candidates.
3. Each candidate has an optional `q` weight.
4. Sort candidates by descending `q`.
5. Reject any candidate with `q <= 0` (RFC 7231: "not acceptable").
6. Normalize each candidate by taking the base language tag before the first `-`:
   - `ar-EG` → `ar`
   - `en-US` → `en`
   - `en-GB` → `en`
7. Select the first normalized candidate present in `config('app.supported_locales')`.
8. Fallback: if no candidate matches, use `config('app.locale')` if it is in the supported list; otherwise, leave the framework default untouched.

### Middleware

`App\Http\Middleware\SetLocale` implements the resolution and calls `App::setLocale()` with the resolved locale.

The middleware is prepended to the `api` middleware group in `bootstrap/app.php`:

```php
$middleware->api(prepend: [
    \App\Http\Middleware\SetLocale::class,
]);
```

Prepending ensures the locale is set before `auth:sanctum` runs and before `FormRequest` validation produces messages.

### Configuration

`config/app.php` gains:

```php
'supported_locales' => ['en', 'ar'],
```

### Translation namespaces

Each module registers its own translation namespace in its service provider:

```php
$this->loadTranslationsFrom(__DIR__.'/../../Lang', '<module>');
```

Existing namespaces:

```text
automation
connections
execution
identity
integrations
```

### What is localized

Localized (HTTP response body only):

- Top-level `message` fields produced by project controllers in the Identity, Automation, Connections, Execution, and Integrations modules where the string was moved to a translation namespace.
- The Catalog's `name`, `description`, action `label`/`description`, trigger `label`/`description`, field `label`/`description` values. These are resolved server-side via `CatalogProjector` using `*Key` fields on the definition entities (Shape 1: translation keys, not locale arrays).
- Root API controllers where source files were supplied (`routes/api.php` inline closure, `UserController::register`, `GoogleAuthController::logout`/`deactivateAccount`/callback catch block).

NOT localized:

- `execution_steps.error_message` and `executions.error_message` — internal diagnostics; remain English in the database and in every API response.
- Raw provider / business-result `message` strings returned inside action result payloads (Google Sheets row-append, Docs append-text, Sheets add/delete/update/clear, etc.). These are part of the business-result body, not the controller envelope.
- Every `$e->getMessage()` diagnostic payload, Google exception message, `trace`, `file`, `line`.
- The 401 body `{ "message": "Unauthenticated." }` — emitted by Laravel's authentication middleware, not project code.
- The top-level 422 body `"The given data was invalid."` — emitted by Laravel's exception handler.
- Machine-readable identifiers: `integration_key`, `action_key`, `trigger_key`, `provider_key`, field `key`, field `type`, `capability`, `strategy`, `operation_id`, status values, capability error codes, OAuth error query params.
- User-supplied data (workflow names, email subjects, etc.).

### Explicit non-mechanisms

The following are NOT permitted and are NOT implemented:

- `X-Locale` header.
- `?locale=` query parameter.
- Stored per-user locale preference (`users.locale`).
- Cookie-based locale.
- Subdomain-based locale.

### Contract addition

`docs/06_MOBILE_API_CONTRACT.md` §12 documents this mechanism.

## Consequences

Positive:

- Mobile clients get localized responses by setting a standard header. No custom client behavior required.
- Machine-readable identifiers remain stable and locale-independent.
- Diagnostics remain English, so logs are searchable across locales.
- The middleware applies to every request, so new controllers inherit localization automatically when they use `__()`.
- The Shape 1 catalog approach keeps identifiers stable and lets the client receive translated text without any client-side translation layer.

Negative / trade-offs:

- `Accept-Language` parsing is now the single point of locale resolution. Bugs in the parser affect every endpoint.
- For clients that do not send `Accept-Language`, everything falls back to `en` — there is no user preference persistence across sessions.
- Raw business-result strings remain English, so mobile must read them defensively.
- Framework validation overrides for locales other than `en` are not yet shipped (see migration impact below).

## Migration Impact

- No database schema change.
- No existing API contract shape change.
- No machine-readable identifier change.
- No changes to any protected production flow (Google login redirect/callback, `/api/register`, `/api/test/login`, Facebook).
- No modification of Scheduler, Gmail polling, execution, connections, OAuth, attachments, or Swagger code.
- Framework `lang/en/validation.php` and `lang/ar/validation.php` overrides are deferred. The framework default `en` validation messages resolve automatically. Per-field 422 messages for locales other than `en` fall back to English until project overrides are added. This is documented in `docs/06_MOBILE_API_CONTRACT.md` §11 and §12.5.
- Localization of `TestLoginController` and the Facebook controllers is deferred pending source file availability.

## References

- `app/Http/Middleware/SetLocale.php`
- `bootstrap/app.php`
- `config/app.php`
- `docs/06_MOBILE_API_CONTRACT.md` §12
- `tests/Feature/Localization/SetLocaleTest.php`
- `tests/Feature/Localization/CatalogTranslationParityTest.php`

---

# ADR-030 — Password Reset via Email OTP

## Status

Accepted / Locked

## Context

Before this decision, the platform had:

- Google OAuth login (production, protected).
- Email/password login at `POST /api/login` (existing users only).
- User provisioning at `POST /api/register`.
- No way for a user who forgot their password to regain access.

Users provisioned via the external Google Apps Script flow or with a password could not self-recover. The only recovery path was direct DB access, which is not a product.

Two flow types were considered:

- **Email link** — classic Laravel default. Awkward for the mobile client because it requires opening a browser and returning to the app.
- **Email OTP** — a short numeric code the user types back into the mobile app. Mobile-friendly.

## Decision

### Flow type

**Email OTP.**

### Endpoint shape

**Shape B** — two endpoints:

```text
POST /api/password/forgot    — request a reset code
POST /api/password/reset     — verify the code and set a new password
```

Both are public (no authentication required).

### Endpoint location

Implemented inside the Identity module:

```text
app/Modules/Identity/
```

Not in the root API controllers. Identity owns authentication.

### OTP lifecycle

- **Code length:** 6 digits.
- **Character set:** numeric, no leading zero.
- **TTL:** 15 minutes.
- **Attempt cap:** 5 wrong attempts per issued code. On the 6th attempt, the code is rejected even if the correct code is later supplied. A new code must be requested.
- **Storage:** only a bcrypt hash of the code is persisted in the new `password_reset_otps` table. The plain code is never persisted, never logged.
- **Single-use:** once a code is successfully consumed, it cannot be reused.
- **Re-issuance:** issuing a new code for the same email overwrites the previous row. Only one active code per email exists at any time.

### `password_reset_otps` table

New module-owned migration:

```text
app/Modules/Identity/Infrastructure/Database/Migrations/2026_10_04_000001_create_password_reset_otps_table.php
```

Columns:

- `id`
- `email` (unique)
- `code_hash`
- `attempts` (unsigned tiny int, default 0)
- `expires_at`
- `used_at` (nullable)
- `created_at`, `updated_at`

The existing `password_reset_tokens` table remains unused and unmodified.

### Rate limits

Per email + per IP, tracked via Laravel's `RateLimiter`:

```text
/api/password/forgot:
  5 per email per hour
  20 per IP per hour

/api/password/reset:
  20 per email per hour
  60 per IP per hour
```

Exceeded limits return HTTP 429 with a `Retry-After: 3600` header and a generic body.

### Response semantics

- `/forgot` returns the same generic 200 message whether the email exists or not, to prevent email enumeration.
- `/reset` returns a single generic 422 for every failure mode (no code, wrong code, expired code, used code, attempts exhausted, unknown email).
- HTTP status codes and envelope shape are stable.

### Token revocation

On successful reset:

- The user's password is updated.
- **All active Sanctum tokens for that user are revoked.** The user must log in again with the new password.

### Email delivery

- The reset code is delivered via a Laravel notification (`PasswordResetOtpNotification`).
- The email body is rendered from a project-owned Blade template at `app/Modules/Identity/Views/emails/password-reset-otp.blade.php`.
- The email body is localized via `identity::messages.*` using the request locale (`Accept-Language`).
- The Laravel default mail template is not used.
- `MAIL_MAILER` must be configured to a real transport in production. The default `log` driver writes the OTP to `storage/logs/laravel.log` and delivers nothing. This is documented in `docs/08_DEPLOYMENT.md`.

### Explicit non-features

The following were considered and are **NOT** implemented:

- **Master OTP.** A shared static code that bypasses the per-email OTP was proposed and explicitly rejected. It would be a privileged backdoor with no per-person audit trail.
- **CLI reset command.** `php artisan user:reset-password` was proposed as a safer alternative for support use and is not implemented.
- **Admin reset endpoint.** Not implemented.
- **Email link flow.** Not implemented.
- **SMS / WhatsApp delivery.** Not implemented.
- **Password reset for soft-deleted users.** Not implemented — soft-deleted users cannot reset.

### Config

`app/Modules/Identity/Infrastructure/Config/identity.php` defines:

```php
'password_reset' => [
    'code_length'          => env('PASSWORD_RESET_CODE_LENGTH', 6),
    'code_ttl_minutes'     => env('PASSWORD_RESET_CODE_TTL_MINUTES', 15),
    'max_attempts'         => env('PASSWORD_RESET_MAX_ATTEMPTS', 5),
    'forgot_rate_limit_per_email_per_hour' => env('PASSWORD_RESET_FORGOT_RATE_EMAIL', 5),
    'forgot_rate_limit_per_ip_per_hour'    => env('PASSWORD_RESET_FORGOT_RATE_IP', 20),
    'reset_rate_limit_per_email_per_hour'  => env('PASSWORD_RESET_RESET_RATE_EMAIL', 20),
    'reset_rate_limit_per_ip_per_hour'     => env('PASSWORD_RESET_RESET_RATE_IP', 60),
],
```

### Swagger

Operations `auth.password.forgot` and `auth.password.reset` added to the `Authentication` tag in `app/Modules/Identity/Swagger/Identity.php`.

## Consequences

Positive:

- Users can self-recover a forgotten password without human intervention.
- The flow is mobile-friendly (no browser round-trip required after the initial reset-code request).
- Email enumeration is prevented by design.
- The password reset revokes all existing sessions, mitigating compromised sessions.
- The OTP is stored hashed; a database dump does not reveal active codes.
- The feature lives entirely in the Identity module, following ADR-028.
- No changes to protected production flows (Google login, `/api/register`, `/api/test/login`, Facebook).

Negative / trade-offs:

- Email delivery must be configured. The default `log` driver writes the OTP to disk — unacceptable in production and documented as a deployment prerequisite.
- The synchronous email notification blocks the HTTP request. With slow SMTP, `/forgot` may take several seconds. Queueing is a future enhancement.
- `MAIL_MAILER` misconfiguration silently hides the OTP in log files. The deployment checklist explicitly requires a real transport.
- Gmail SMTP (the current production setup) caps at ~500 emails/day (regular) or ~2,000/day (Workspace). The project should migrate to a transactional provider (Postmark / SES / Resend) for production volume.
- No user preference for locale is stored, so the OTP email is sent in whatever locale the client sent via `Accept-Language` on the `/forgot` request. If the client does not send the header, the email is English.
- No anti-enumeration timing mitigation was added; `/forgot` takes slightly longer when the user exists (SMTP time). Low risk.

## Migration Impact

- **New table:** `password_reset_otps`. Created by a module-owned migration. No existing table is modified.
- **New config file:** `app/Modules/Identity/Infrastructure/Config/identity.php` merged at runtime.
- **New mail requirement:** `MAIL_MAILER` must be set to a real transport in production. Documented in `docs/08_DEPLOYMENT.md`.
- **No change** to:
  - `App\Models\User`
  - `LoginAction`, `LoginController`, `LoginRequest`
  - Google login
  - `/api/register`
  - `/api/test/login`
  - Facebook
  - Scheduler, Gmail polling, execution, connections, OAuth, attachments, Swagger HTTP contracts for unrelated endpoints
- **Contract update:** `docs/06_MOBILE_API_CONTRACT.md` §13.
- **Rollback:** revert the migration and remove the two routes. Existing users are unaffected. No data outside `password_reset_otps` is touched.

## References

- `app/Modules/Identity/Application/Services/PasswordResetService.php`
- `app/Modules/Identity/Http/Controllers/RequestPasswordResetController.php`
- `app/Modules/Identity/Http/Controllers/ResetPasswordController.php`
- `app/Modules/Identity/Infrastructure/Database/Migrations/2026_10_04_000001_create_password_reset_otps_table.php`
- `app/Modules/Identity/Infrastructure/Notifications/PasswordResetOtpNotification.php`
- `app/Modules/Identity/Views/emails/password-reset-otp.blade.php`
- `docs/06_MOBILE_API_CONTRACT.md` §13
- `docs/08_DEPLOYMENT.md` (§ mail configuration)
- `tests/Feature/Identity/PasswordResetTest.php`

---

# Future ADRs

New decisions should use:

```text
ADR-031
ADR-032
ADR-033
...
```

Each new decision should include:

* Status
* Context
* Decision
* Consequences
* Migration impact where relevant

Do not rewrite old decisions to hide historical changes.

If a future decision changes ADR-028, ADR-029, or ADR-030, it must explicitly identify the affected rule and explain the reason for the change.
