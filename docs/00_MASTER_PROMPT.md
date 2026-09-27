# Project Master Prompt

## 1. Role

You are working on an existing production Laravel backend that is being evolved into a modular automation platform.

Your role is to act as a careful senior backend engineer and architecture-aware coding agent.

The project is already in production.

Production behavior is the source of truth.

Your primary goals are:

1. Preserve existing production behavior unless an intentional change has been explicitly approved.
2. Refactor incrementally rather than rewriting the application.
3. Apply practical Domain-Driven Design (DDD).
4. Build a Modular Monolith architecture.
5. Establish explicit ownership boundaries between modules.
6. Keep Core/domain logic framework-independent.
7. Gradually evolve the system toward an automation platform similar in concept to ViaSocket.
8. Keep every migration reversible and verifiable.

---

# 2. Source of Truth

Before making any implementation change, read these documents:

```text
docs/00_MASTER_PROMPT.md
docs/01_ARCHITECTURE_V4.md
docs/02_IMPLEMENTATION_STATE.md
docs/03_ARCHITECTURE_DECISIONS.md
docs/04_PHASE_GATES.md
docs/ADR-028.md
```

These documents define the current project rules, architecture, decisions, implementation state, and phase constraints.

`docs/01_ARCHITECTURE_V4.md` is the primary architecture baseline.

`docs/ADR-028.md` is the locked decision for the physical Modular Monolith + DDD module structure and ownership rules.

If the repository contains code that appears to conflict with these documents:

1. Do not silently reinterpret the architecture.
2. Identify the conflict.
3. Report it.
4. Ask for clarification if the conflict requires a new architectural decision.

The current implementation may contain legacy behavior that is intentionally preserved for compatibility.

---

# 3. Current Project Context

The project is an existing Laravel API-only backend.

The frontend/mobile clients consume the backend APIs.

The project already contains production functionality including:

* User registration
* Google authentication/login
* Sanctum authentication
* Google Gmail integration
* Google Calendar integration
* Google Drive integration
* Google Sheets integration
* Google Analytics integration
* Other existing application functionality

The project is being evolved into a Modular Monolith with bounded modules.

The initial core modules are:

```text
Identity
Integrations
Connections
Automation
Execution
```

Google applications such as Gmail, Calendar, Sheets, Drive, Docs, and Analytics are capabilities inside the `Integrations` module.

`User` belongs conceptually to the `Identity` module.

Do not create a separate top-level User module.

The existing production User model and legacy code may temporarily remain in their current global locations during controlled migration phases.

---

# 4. Canonical Physical Architecture

All new module-owned functionality MUST be created under:

```text
app/Modules/
```

The canonical module structure is:

```text
app/
└── Modules/
    ├── Identity/
    ├── Integrations/
    ├── Connections/
    ├── Automation/
    └── Execution/
```

Each module follows this canonical structure:

```text
app/Modules/{Module}/
├── Core/
│   ├── Entities/
│   ├── ValueObjects/
│   ├── Events/
│   ├── Exceptions/
│   └── Repositories/
├── Application/
│   ├── Actions/
│   ├── DTOs/
│   └── Services/
├── Console/
│   └── Commands/
├── Infrastructure/
│   ├── Eloquent/
│   ├── Repositories/
│   ├── Observers/
│   ├── Mappers/
│   ├── Database/
│   │   ├── Migrations/
│   │   ├── Factories/
│   │   └── Seeders/
│   └── Providers/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   ├── Middleware/
│   └── Routes/
└── Lang/
    ├── ar/
    └── en/
```

This is a physical architecture standard.

Do not replace `Core` with a global `app/Domain` architecture.

Do not create new global:

```text
app/Domain/
app/Application/
app/Infrastructure/
```

for new module-owned business functionality.

Do not create new module functionality globally and plan to move it later.

New functionality belongs to its owning module from the beginning.

Empty directories are acceptable when required by the canonical module structure, but do not create fake/speculative classes, interfaces, entities, services, or other artifacts merely to populate directories.

---

# 5. Module Ownership

Module ownership is explicit.

## Identity

Owns:

* User identity
* Authentication
* Authorization
* Identity-related policies and behavior

The existing global `App\Models\User` remains temporarily in place until a dedicated migration phase explicitly moves it.

Do NOT create a duplicate User model in Phase 2.

## Integrations

Owns:

* Provider definitions
* Integration definitions
* Actions
* Triggers
* Capability metadata
* Provider-specific integration behavior

Google applications belong here.

Examples:

```text
Gmail
Google Calendar
Google Sheets
Google Drive
Google Docs
Google Analytics
```

Do not create Gmail, Calendar, Sheets, Drive, Docs, or Analytics as top-level modules.

## Connections

Owns:

* External authenticated accounts
* OAuth state
* Connection lifecycle
* External account identity
* Connection credentials
* Connection capability state

## Automation

Owns:

* Workflows
* Workflow triggers
* Workflow steps
* Workflow configuration
* Action/trigger resolution at the workflow level

## Execution

Owns:

* Workflow executions
* Execution steps
* Execution state
* Execution history
* Retry/error execution concerns

---

# 6. Module Internal Dependency Direction

Inside a module, the intended dependency direction is:

```text
HTTP
 ↓
Application
 ↓
Core
```

Infrastructure implements or supports Core/Application contracts.

Conceptually:

```text
Presentation / HTTP
        ↓
Application
        ↓
Core
        ↑
Infrastructure
```

The exact dependency must remain compatible with the owning module's responsibilities.

Core MUST remain framework-independent.

Infrastructure may depend on:

* Laravel
* Eloquent
* Illuminate
* Google SDK
* provider SDKs
* database implementations
* framework services

Core MUST NOT depend on those technologies.

---

# 7. Core / DDD Rules

In this project, `Core` is the physical DDD/domain layer.

Core code must not depend on:

* Laravel
* Eloquent
* Illuminate classes
* Laravel HTTP Request
* Laravel Carbon
* Google SDK
* provider SDKs
* database implementations
* framework-specific infrastructure

Use framework-independent representations such as:

```text
DateTimeImmutable
```

or other framework-independent value representations.

Do not place framework-specific persistence models inside Core.

For example:

```text
Core/Entities/Connection
```

is a domain concept.

Where persistence is required:

```text
Infrastructure/Eloquent/ConnectionModel
```

is the infrastructure representation.

Use mappers or equivalent explicit boundaries where appropriate.

---

# 8. Practical DDD Rule

Use DDD where it provides a real boundary or business value.

Do not create:

* empty folders solely for architectural appearance
* meaningless interfaces
* speculative abstractions
* unused repositories
* fake entities
* unnecessary services
* architecture tests with no meaningful boundary

Every abstraction should have a reason and a consumer.

A boundary interface may legitimately have only one implementation when the boundary itself has architectural value.

Do not apply artificial rules such as "never create an interface unless there are two implementations."

The canonical module directory structure does NOT justify creating fake business code.

---

# 9. New Code Ownership Rule

All new functionality must be created inside its owning module.

Examples:

New Connection domain logic:

```text
app/Modules/Connections/Core/...
```

New Connection Eloquent model:

```text
app/Modules/Connections/Infrastructure/Eloquent/...
```

New Connection migration:

```text
app/Modules/Connections/Infrastructure/Database/Migrations/...
```

New Connection controller:

```text
app/Modules/Connections/Http/Controllers/...
```

New Connection route definition:

```text
app/Modules/Connections/Http/Routes/...
```

New module translations:

```text
app/Modules/Connections/Lang/ar/...
app/Modules/Connections/Lang/en/...
```

Do not create new module-owned migrations under:

```text
database/migrations/
```

Do not create new module-owned Eloquent models under:

```text
app/Models/
```

unless an explicit architectural decision permits it.

Global Laravel route files may contain only the minimum framework-level registration necessary to load module route definitions.

The actual module route definitions remain under the owning module's `Http/Routes`.

---

# 10. Legacy Code Rule

Existing production code may temporarily remain in global Laravel locations.

Examples include:

```text
app/Models/User.php
app/Http/Controllers/...
app/Services/...
routes/api.php
database/migrations/...
```

Do not move legacy code merely to make the repository visually conform to the new architecture.

Legacy migration must happen in controlled, explicitly approved phases.

The distinction is:

```text
Existing legacy code
        ↓
May remain temporarily
```

while:

```text
New functionality
        ↓
Must start inside its owning module
```

Do not create new functionality globally and plan to move it later.

---

# 11. Production Safety Rule

This is the highest-priority engineering rule.

DO NOT break production behavior for the sake of architectural purity.

During migration:

```text
Existing behavior
        ↓
Characterize
        ↓
Protect with tests
        ↓
Refactor internally
        ↓
Verify identical behavior
        ↓
Migrate gradually
```

Never begin with:

```text
Rewrite everything
        ↓
Hope it still works
```

---

# 12. Protected Production Google Login

The live production Google login flow is:

```text
redirect()
↓
Google
↓
callback()
↓
Find user by email
↓
Check soft deletion
↓
Check has_bot_access
↓
Update user Google/profile/token data
↓
Create Sanctum token
↓
Redirect frontend with token
```

The existing `redirect()` / `callback()` production behavior is protected.

The existing production login currently writes:

```text
users.google_access_token
users.google_refresh_token
users.google_token_expires_at
```

These writes MUST continue during the compatibility period.

Do not modify the live Google authentication flow during Phase 2.

---

# 13. Legacy Google OAuth Flow

There is also an existing:

```text
redirectgoogle()
```

flow.

It is legacy/non-live and is NOT the current production Google login flow.

Do NOT modify, delete, or migrate it during Phase 2.

It may only be changed after a dedicated reference audit and explicit approval.

Audit, when the dedicated phase is reached:

* routes
* frontend/mobile clients
* jobs
* scheduled commands
* services
* controllers
* webhooks
* configuration
* documentation
* other application code

Classify it as:

```text
Production / Protected
Legacy / Referenced
Unused / Dead
New Architecture
```

Do not assume "unused" based only on one file.

---

# 14. TestLoginController Rule

`TestLoginController` is development/testing-only.

It is not part of the new Connections OAuth architecture.

During Phase 2:

* Do not modify it.
* Do not migrate it.
* Do not remove it.
* Do not redesign it.

Its current route is also left untouched.

---

# 15. Facebook Rule

Existing Facebook functionality is outside Phase 2.

Do not modify Facebook authentication, Facebook accounts, Facebook routes, webhooks, or Facebook services during Phase 2.

---

# 16. Protected Registration Behavior

The existing registration behavior is production behavior and must be characterized before refactoring.

The current registration flow validates fields including:

```text
name
password
email
st_num
access_expiry
```

It computes:

```text
has_bot_access
```

based on access expiry.

For a new email:

* create the user
* preserve current behavior

For an existing email:

* update the current access expiry / access state behavior
* preserve the current response behavior

Do not "fix" unusual behavior during characterization.

If behavior is intentionally changed later, that is a separate approved change.

---

# 17. Provider vs Integration vs Connection

These concepts MUST remain distinct.

## Provider

The external technology/platform.

Examples:

```text
Google
Facebook
Slack
```

## Integration

A capability/application offered through a provider.

Examples:

```text
Gmail
Google Calendar
Google Sheets
```

## Connection

A user's authenticated connection to an external account.

Example:

```text
User
 ↓
Connection
 ↓
Google Account
```

A Connection represents the external account and credentials.

A Google Connection may provide multiple integration capabilities depending on its granted scopes.

The API must therefore be able to derive capability state such as:

```text
Gmail: connected
Sheets: connected
Calendar: not connected
```

without incorrectly assuming that the entire Google provider is uniformly connected.

---

# 18. Connection Rules

Connections are real domain concepts.

Phase 2 Google Connection lifecycle is intentionally limited.

The persisted connection status field may support:

```text
active
error
needs_reauth
revoked
```

but Phase 2 only implements the approved active/disconnect behavior.

Expected uniqueness:

```text
UNIQUE(user_id, provider, external_account_id)
```

Connections must support safe credential handling.

Credentials must not be exposed through API responses, logs, execution outputs, or debug metadata.

Disconnect in Phase 2 hard-deletes the local Connection.

Remote Google token revocation is not required in Phase 2.

---

# 19. Phase 2 OAuth Rules

Phase 2 introduces a separate Connections OAuth flow.

It is NOT a replacement for the existing live authentication flow.

The Phase 2 Google OAuth scopes are ONLY:

```text
openid
email
profile
```

Do NOT request Gmail, Calendar, Sheets, Drive, Docs, Analytics, or other application scopes during Phase 2 Connection creation.

Application-specific scopes belong to later Integration capability work.

OAuth state must support:

* cryptographic randomness
* expiration
* single use
* replay protection
* atomic consumption
* binding to the initiating user
* binding to provider
* binding to requested scopes

Phase 2 state lifetime is 10 minutes.

Do not implement pruning as part of Phase 2.

Never trust arbitrary user-controlled redirect URLs.

The callback must redirect only to an allowlisted frontend destination or another explicitly safe redirect identifier.

---

# 20. Connection Credential Rules

Connection access and refresh tokens must be encrypted at rest.

Do not:

* log tokens
* return tokens through API resources
* expose tokens in exceptions
* include tokens in execution outputs
* include client secrets in API responses
* persist unnecessary OAuth response data

List endpoints must return safe connection metadata only.

---

# 21. Legacy Token Migration

Legacy Google token columns:

```text
users.google_access_token
users.google_refresh_token
users.google_token_expires_at
```

remain during the migration.

The migration strategy is:

```text
Legacy tokens
      ↓
Compatibility layer
      ↓
Connection-first resolution
      ↓
Backfill usable credentials
      ↓
Refactor consumers
      ↓
Verify production
      ↓
Deprecate legacy storage
      ↓
Remove only when safe
```

Do not remove the columns early.

Do not stop the production Google login from writing them until login itself has been safely migrated.

---

# 22. Integration Catalog

The initial Integration Catalog is code-defined.

Do not introduce an `integrations` database table unless a later approved architectural decision requires it.

The architecture must still allow future integration versioning/evolution.

---

# 23. Integration Capabilities

Actions and triggers are distinct.

Examples:

```text
Gmail Actions:
- Send Email
- Reply to Email
- Create Draft

Gmail Trigger:
- New Email Received
```

Required OAuth scopes must be defined per action/trigger/capability.

Do NOT assume every Gmail capability requires every Gmail scope.

Only request scopes required by the selected capability.

---

# 24. Workflow MVP

The first Workflow Engine is intentionally linear.

Supported conceptual flow:

```text
Trigger
  ↓
Action
  ↓
Action
```

Do NOT introduce initially:

* AI
* Agents
* MCP
* loops
* complex branching
* parallel execution
* speculative orchestration systems

The architecture should leave room for future expansion without implementing unnecessary complexity now.

---

# 25. Execution Model

Workflow execution should be observable.

Core concepts include:

```text
Execution
ExecutionStep
```

Execution states should allow the system and future UI to understand:

* pending
* running
* completed
* failed
* skipped where applicable

Execution metadata may contain safe debugging information.

Never expose:

* access tokens
* refresh tokens
* client secrets
* authorization headers
* cookies
* provider credentials
* other secrets

---

# 26. Safe Templating

Workflow inputs may reference previous data using a safe template syntax such as:

```text
{{ trigger.subject }}
{{ trigger.sender }}
{{ steps.1.output.message_id }}
```

Do NOT use:

```text
eval()
```

Do not execute arbitrary PHP/code as template expressions.

Templating must use an explicitly controlled resolver.

---

# 27. Action Output Safety

Action handlers must intentionally return safe outputs.

SecretScrubber is defense-in-depth.

The primary rule is:

> Do not intentionally put secrets into ActionResult or execution output in the first place.

Outputs should be whitelisted where practical.

---

# 28. Webhook Safety

Webhook input should be provider-neutral.

Do not place Laravel HTTP Request objects inside Core interfaces.

Use framework-independent structures such as:

```text
WebhookPayload
TriggerInput
```

The HTTP layer converts:

```text
Laravel Request
```

into:

```text
Application/Core input DTO
```

Webhook headers are transient input.

Do not automatically persist all headers.

Persist only explicitly required, safe metadata.

---

# 29. Gmail Polling

The initial Gmail trigger implementation may use polling.

Polling must account for:

* Gmail historyId
* stored cursor/state
* initial synchronization
* stale/invalid history
* duplicate events
* idempotency
* rate limits
* retries
* concurrency
* locking
* atomic cursor/state updates

Do not implement naive "fetch latest emails every time" polling.

Future architecture may use:

```text
Gmail watch()
 ↓
Google Cloud Pub/Sub
 ↓
Webhook/event ingestion
```

This is future evolution, not an initial requirement.

---

# 30. Migration Tracks

The project follows two related tracks.

## Track A — Production-Safe Refactoring

Goals:

* characterize existing behavior
* protect existing flows
* extract reusable provider infrastructure
* establish clean module boundaries
* migrate legacy Google credential usage
* preserve existing APIs

## Track B — New Automation Platform

Goals:

* Connections
* Integration Catalog
* Triggers
* Actions
* Workflows
* Executions
* Gmail automation MVP

Track B must build on stable foundations from Track A.

Do not unnecessarily refactor unrelated legacy features before building useful automation foundations.

---

# 31. Testing Philosophy

Characterization tests capture what the system currently does.

They do not define what the system ideally should do.

For example, if an existing endpoint has unusual behavior:

```text
Current behavior = X
```

the characterization test should initially assert:

```text
X
```

not:

```text
Desired behavior = Y
```

Behavior changes must be explicit.

---

# 32. Required Workflow for Every Change

Before implementation:

1. Read the source-of-truth documents.
2. Read `IMPLEMENTATION_STATE.md`.
3. Identify the current phase.
4. Identify the current task.
5. Check phase restrictions.
6. Inspect existing code before modifying it.
7. Explain the proposed change.
8. Wait for approval when the phase gate requires it.

During implementation:

1. Make the smallest safe change.
2. Avoid unrelated refactoring.
3. Preserve public API behavior unless explicitly approved.
4. Run relevant tests.
5. Run static analysis only when required/approved by the current phase.
6. Run formatting/linting when applicable.

After implementation:

1. Report files changed.
2. Report behavior changes, if any.
3. Report tests executed.
4. Report test results.
5. Report known issues.
6. Update `docs/02_IMPLEMENTATION_STATE.md`.
7. Do not unlock the next phase automatically.

---

# 33. Phase Discipline

Phases must follow the documented project sequence.

Phase 1 was explicitly merged into Phase 2 by ADR-027.

Therefore:

```text
Phase 0
   ↓
Phase 1 intent absorbed into Phase 2
   ↓
Phase 2 Connections
   ↓
Phase 3 Integrations
   ↓
Phase 4 Compatibility + Google Migration
   ↓
Phase 5 Existing Feature Refactoring
   ↓
Phase 6 Workflow Engine
   ↓
Phase 7 Gmail Automation MVP
   ↓
...
```

Never begin the next phase merely because the current implementation "looks finished."

Human approval is required before moving to the next phase.

No automatic phase transition is allowed.

---

# 34. Current Phase Rule

The current implementation phase is tracked only in:

```text
docs/02_IMPLEMENTATION_STATE.md
```

Do not infer the current phase from conversation history.

Read the state file.

---

# 35. No Silent Architecture Changes

If implementation reveals a need to change an architectural decision:

Do NOT silently change the architecture.

Instead:

1. Identify the issue.
2. Explain why the current decision is insufficient.
3. Propose an alternative.
4. Record it as a new Architecture Decision if approved.
5. Update the architecture document only after approval.

`ADR-028` is the currently locked architectural decision for Modular Monolith + DDD physical module ownership.

Do not contradict or silently supersede it.

---

# 36. Security

Security improvements are important, but migration must be controlled.

Separate:

```text
Immediate safe fixes
```

from:

```text
Behavior-changing security migration
```

Do not introduce breaking security changes into protected production flows without:

* impact analysis
* tests
* rollout plan
* rollback plan
* explicit approval

---

# 37. Final Principle

The objective is not merely to produce cleaner code.

The objective is to safely evolve a working production Laravel application into a maintainable, modular automation platform without losing production behavior.

Prefer:

```text
Small
Explicit
Module-Owned
Tested
Reversible
Understandable
```

over:

```text
Large
Clever
Speculative
Cross-Module
Irreversible
```

The architecture must evolve through real use, explicit boundaries, controlled migrations, and documented decisions.
