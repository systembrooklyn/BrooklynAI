# Architecture V4 — Approved Baseline

## Status

**APPROVED / LOCKED**

This document represents the approved Architecture V4 baseline.

It is the architectural source of truth unless a later approved Architecture
Decision explicitly changes it.

The project is a production Laravel API backend evolving into a modular
automation platform.

---

# 1. Architectural Goal

The goal is to evolve the existing production Laravel API into a reliable
automation platform without breaking existing production behavior.

The target architecture is:

```text
Modular Monolith + Domain-Driven Design (DDD)
```

The application remains:

* one Laravel application
* one deployable backend
* one production runtime

while business capabilities are separated into explicit modules with clear
ownership and dependency boundaries.

The architecture must support:

```text
Identity
Integrations
Connections
Automation
Execution
```

without requiring microservices.

---

# 2. Canonical Physical Architecture

The canonical physical module root is:

```text
app/Modules/
```

The five initial modules are:

```text
app/Modules/
├── Identity/
├── Integrations/
├── Connections/
├── Automation/
└── Execution/
```

The physical module structure is **canonical and locked**.

Future implementation phases MUST follow this structure unless a later
approved ADR explicitly changes it.

The project does NOT use a new global:

```text
app/Domain/
app/Application/
app/Infrastructure/
```

architecture for new module-owned business functionality.

Inside each module, the DDD/domain layer is named:

```text
Core/
```

`Core` is the official physical name for the DDD/domain layer.

---

# 3. Canonical Module Structure

Every module uses the same canonical internal structure:

```text
app/
└── Modules/
    └── {Module}/
        ├── Core/
        │   ├── Entities/
        │   ├── ValueObjects/
        │   ├── Events/
        │   ├── Exceptions/
        │   └── Repositories/
        │
        ├── Application/
        │   ├── Actions/
        │   ├── DTOs/
        │   └── Services/
        │
        ├── Console/
        │   └── Commands/
        │
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
        │
        ├── Http/
        │   ├── Controllers/
        │   ├── Requests/
        │   ├── Resources/
        │   ├── Middleware/
        │   └── Routes/
        │
        └── Lang/
            ├── ar/
            └── en/
```

Empty directories may exist when they are part of an intentionally established
module structure.

However, empty business classes, interfaces, DTOs, repositories, events,
services, or other speculative abstractions MUST NOT be created merely to
populate directories.

This rule follows ADR-021.

---

# 4. Module Ownership

The five modules have the following ownership:

| Module       | Owns                                                                    |
| ------------ | ----------------------------------------------------------------------- |
| Identity     | Users, authentication, authorization, identity concerns                 |
| Integrations | Provider/application definitions, actions, triggers, schemas            |
| Connections  | OAuth connections, external accounts, credentials, connection lifecycle |
| Automation   | Workflows, workflow triggers, workflow steps, workflow configuration    |
| Execution    | Workflow executions, execution steps, execution state/history           |

Ownership is mandatory.

A business concept belongs to the module that owns its lifecycle and rules.

---

# 5. Identity Module

Identity owns the platform user's identity.

Responsibilities include:

* User identity
* authentication
* authorization
* platform access
* entitlement-related identity state
* Sanctum authentication concerns
* identity lifecycle

The existing:

```text
App\Models\User
```

conceptually belongs to Identity.

However, the existing production model MUST NOT be duplicated or moved during
the Connections phase.

Legacy identity code may remain in its existing Laravel location until an
explicit migration phase moves it safely.

There MUST NOT be two competing User models representing the same production
user.

---

# 6. Integrations Module

Integrations owns the catalog and definitions of external applications.

Examples include:

```text
Google Gmail
Google Calendar
Google Sheets
Google Drive
Google Docs
Google Analytics
```

Google applications are **Integrations**.

They are NOT top-level modules.

The Integrations module owns concepts such as:

* integration keys
* provider definitions
* action definitions
* trigger definitions
* input field definitions
* output schemas
* required scopes
* provider/application metadata

The initial catalog is code-defined.

No `integrations` database table is required for the initial architecture.

---

# 7. Connections Module

Connections owns authorization to external accounts.

A Connection represents:

```text
one user's authorized external account at a provider
```

The conceptual identity is:

```text
(user_id, provider, external_account_id)
```

A Connection owns:

* external account identity
* credentials
* granted scopes
* status
* token expiration
* connection lifecycle
* connection errors

A Gmail connection is therefore not a separate conceptual account from a
Google connection.

The same Google account may authorize:

```text
Gmail
Sheets
Calendar
Drive
Docs
```

through the same provider connection.

---

# 8. Automation Module

Automation owns workflow definitions.

A workflow contains:

```text
one trigger
+
one or more ordered steps
```

Automation owns:

* Workflow
* WorkflowTrigger
* WorkflowStep
* workflow configuration
* workflow validation
* activation/deactivation rules
* mapping configuration

Automation references integrations using stable keys.

Example:

```text
google.gmail
google.sheets
google.calendar
```

Automation MUST NOT directly depend on Google SDK classes.

---

# 9. Execution Module

Execution owns the runtime execution of workflows.

It owns:

* Execution
* ExecutionStep
* execution state
* execution history
* execution errors
* retry state
* execution timing
* trigger ingestion coordination

Execution depends on contracts rather than provider-specific implementations.

Execution MUST NOT contain Google-specific business logic.

---

# 10. Provider / Integration / Connection Terminology

These concepts are intentionally separate.

```text
Provider
    Google

Integration
    Gmail
    Google Sheets
    Google Calendar

Connection
    User's authorized Google account
```

Example:

```text
Google = Provider

Gmail = Integration

user's Google account = Connection
```

An Integration describes what the platform can do.

A Connection describes which external account the user authorized.

---

# 11. Core Layer Rules

Inside each module:

```text
Core/
```

is the framework-independent DDD layer.

Core code MUST NOT depend on:

```text
Illuminate\
Laravel\
Eloquent
Google SDK
Facebook SDK
GuzzleHttp
Carbon
Symfony HTTP framework classes
```

Core should use:

```text
native PHP
DateTimeImmutable
module-owned interfaces
module-owned entities
module-owned value objects
module-owned domain events
```

Framework conversion belongs at Infrastructure boundaries.

---

# 12. Application Layer Rules

The Application layer coordinates use cases.

```text
Http
  ↓
Application
  ↓
Core
```

Application code may coordinate:

* repositories
* domain objects
* infrastructure-provided contracts
* transactions
* application-level policies

Application code MUST NOT contain provider-specific SDK execution.

Application code MUST NOT become a second domain layer.

Actions should represent real use cases.

---

# 13. Infrastructure Layer Rules

Infrastructure contains framework and external-provider implementations.

Examples:

```text
Eloquent models
repository implementations
database migrations
Google OAuth client
Google SDK integration code
encryption implementations
Laravel service providers
framework adapters
```

Infrastructure implements contracts defined by Core/Application where such
contracts represent real architectural boundaries.

Infrastructure may depend on:

```text
Laravel
Eloquent
Google SDK
Guzzle
Carbon
```

Core must not depend on them.

---

# 14. HTTP Layer Rules

New HTTP functionality belongs inside the owning module.

For example:

```text
app/Modules/Connections/Http/Controllers/
app/Modules/Connections/Http/Requests/
app/Modules/Connections/Http/Resources/
app/Modules/Connections/Http/Routes/
```

New module-owned routes MUST NOT be created directly in global
`routes/api.php` as the business route definition.

Laravel may require a minimal global bootstrap/registration mechanism, but the
actual route definitions remain module-owned.

Existing legacy routes remain where they are until their explicit migration
phase.

---

# 15. Persistence Ownership

New module-owned migrations MUST live inside the owning module:

```text
app/Modules/{Module}/Infrastructure/Database/Migrations/
```

New module-owned Eloquent models MUST live inside:

```text
app/Modules/{Module}/Infrastructure/Eloquent/
```

New module-owned repositories MUST live inside:

```text
app/Modules/{Module}/Infrastructure/Repositories/
```

This rule applies to all new functionality.

New functionality MUST NOT be created globally and planned for movement into a
module later.

---

# 16. Localization

The project supports:

```text
Arabic
English
```

Every module therefore contains:

```text
Lang/ar/
Lang/en/
```

User-facing messages should be localizable.

Do not hardcode user-facing messages to a single language when the message
belongs to module/application behavior.

Do not create fake translation keys merely to populate directories.

---

# 17. DDD Dependency Direction

The canonical dependency direction is:

```text
HTTP
  ↓
Application
  ↓
Core
```

Infrastructure sits outside the Core boundary and implements required
contracts.

Conceptually:

```text
                 ┌──────────────┐
                 │    HTTP      │
                 └──────┬───────┘
                        ↓
                 ┌──────────────┐
                 │ Application  │
                 └──────┬───────┘
                        ↓
                 ┌──────────────┐
                 │     Core     │
                 └──────────────┘
                        ↑
                        │
                 ┌──────┴───────┐
                 │Infrastructure│
                 └──────────────┘
```

Infrastructure implements interfaces where required.

Core does not import Infrastructure.

---

# 18. No Speculative Abstraction

The project follows ADR-021 and the refined architecture rule:

> Do not create abstractions speculatively. Create abstractions when they
> represent a real architectural boundary or have a real consumer.

A boundary interface may have one implementation.

For example:

```text
ConnectionRepository
```

may legitimately have one Eloquent implementation because the interface
defines the boundary between Core/Application and Infrastructure.

However, a helper with one caller and no architectural boundary should normally
remain a private method or concrete class.

No speculative classes are created for future phases.

---

# 19. Integration Contract

Integrations expose definitions through code.

Conceptually:

```text
IntegrationProvider
    key()
    providerKey()
    name()
    category()
    authDefinition()
    actions()
    triggers()
```

The registry is code-defined.

Example:

```text
google.gmail
google.sheets
google.calendar
google.docs
google.analytics
```

No database catalog is required for the initial release.

---

# 20. Action Contract

An Action represents something a workflow can do.

Conceptually:

```text
ActionDefinition
    key
    label
    description
    fields
    outputSchema
    requiredScopes
```

The execution contract is provider-independent.

Conceptually:

```text
ActionHandler
    execute(connection, input)
```

Action handlers return explicit, whitelisted output.

Provider responses MUST NOT be passed through wholesale.

Example:

```text
SendEmailAction
    → message_id
    → thread_id
```

rather than the entire Gmail response.

---

# 21. Trigger Contract

A Trigger represents something that starts a workflow.

A trigger definition contains:

```text
key
label
description
strategy
config fields
output schema
required scopes
```

Supported strategies may include:

```text
poll
webhook
provider_push
schedule
```

The first Gmail MVP uses polling.

Webhook input is transient and must remain separate from persisted execution
data.

---

# 22. OAuth Architecture

There are two completely separate OAuth concepts.

### Existing platform login

```text
GET /api/auth/google/redirect
GET /api/auth/google/callback
```

This is the existing production login flow.

It is protected and MUST NOT be changed during Phase 2.

### New integration connection OAuth

```text
/api/connections/google/*
```

This authorizes an external account for automation.

The two flows MUST NOT be mixed.

---

# 23. Existing Google Login Protection

The following existing production flow is frozen:

```text
register()
redirect()
callback()
```

Specifically:

```text
GoogleAuthController::redirect()
GoogleAuthController::callback()
```

remain unchanged during Phase 2.

The callback continues to write:

```text
users.google_access_token
users.google_refresh_token
users.google_token_expires_at
```

exactly as the existing production implementation does.

The new Connections flow does NOT write those columns.

---

# 24. `redirectgoogle()` Legacy Rule

`redirectgoogle()` is legacy/non-live.

It MUST NOT be treated as the current production Google login flow.

It MUST remain untouched unless a later explicit phase performs the required
reference audit and receives approval for a change.

The live production flow is:

```text
redirect()
callback()
```

not:

```text
redirectgoogle()
```

---

# 25. TestLoginController Rule

`TestLoginController` is development/testing-only.

Its current behavior and route remain untouched during Phase 2.

Current route:

```text
POST /api/test/login
```

The project does not remove or modify it as part of Connections.

Any future change requires a separate explicit decision.

---

# 26. Facebook Rule

Facebook functionality is outside the Connections Phase 2 scope.

Existing Facebook:

* controllers
* services
* routes
* webhook behavior
* `facebook_accounts`

remain untouched.

Facebook security hardening and migration are separate future work.

---

# 27. Legacy Compatibility Strategy

Existing production code is allowed to remain outside the module structure during
the migration.

This is intentional.

The distinction is:

```text
Existing legacy code
    → may remain temporarily outside modules

New functionality
    → MUST be created inside its owning module
```

Legacy migration must happen incrementally.

Do not perform a large-scale move merely for visual consistency.

Every legacy migration must preserve behavior and be independently verified.

---

# 28. Connections Credential Strategy

The Connections module stores new credentials encrypted.

The conceptual model is:

```text
Connection
    provider
    external_account_id
    email
    display_name
    encrypted credentials
    scopes
    status
    token expiration
```

Tokens must never be returned by the Connections API.

Credentials must never appear in:

```text
API responses
logs
exceptions
execution history
Swagger examples
```

The existing legacy `users.google_*` fields remain untouched during Phase 2.

---

# 29. Connection Uniqueness

The database invariant is:

```text
UNIQUE(user_id, provider, external_account_id)
```

This means:

```text
same user
+
same provider
+
same external account
=
one Connection
```

If the same Google account is used for:

```text
Gmail
Sheets
Calendar
```

the system maintains one Connection whose scopes represent the granted
authorization.

If the user authorizes a second Google account, the different Google `sub`
creates a separate Connection.

---

# 30. OAuth State Security

OAuth state must be:

* cryptographically random
* user-bound
* provider-bound
* scope-bound
* short-lived
* single-use
* replay-protected

Phase 2 uses a ten-minute expiration.

Callback processing must consume the state atomically.

A consumed or expired state MUST NOT be accepted.

The state MUST NOT contain credentials or sensitive information.

---

# 31. OAuth Redirect Security

OAuth callback redirects MUST be server-controlled.

Arbitrary user-provided full URLs are prohibited.

The redirect mechanism must use an allowlisted destination.

Only safe path information may be accepted from the OAuth flow.

No callback may redirect to an arbitrary attacker-controlled host.

---

# 32. Scope Model

Scopes belong to a Connection.

Integration capability is derived from the scopes granted to the Connection.

The architecture does not assume:

```text
Google connected
=
all Google integrations connected
```

Instead:

```text
Connection scopes
        ↓
Integration required scopes
        ↓
capability available or unavailable
```

For a specific workflow action or trigger, the authoritative check is:

```text
requiredScopes ⊆ connection.scopes
```

The system must validate the scopes actually granted by the provider rather
than trusting only the requested scope list.

---

# 33. Automation and Execution Safety

Workflow execution must be deterministic and idempotent.

The architecture supports:

```text
Workflow
    ↓
Trigger
    ↓
Execution
    ↓
ExecutionStep
    ↓
Action
```

Execution state must be persisted.

Duplicate trigger events must not create duplicate executions.

The Gmail polling architecture uses durable state and deduplication.

Sensitive provider headers and credentials must never become persisted workflow
data.

---

# 34. Phase Sequence

The approved high-level migration sequence is:

```text
Phase 0
Safety Net
    ↓
Phase 1
Merged into Phase 2
    ↓
Phase 2
Connections
    ↓
Phase 3
Integrations
    ↓
Phase 4
Credential Compatibility
    ↓
Phase 5+
Automation / Execution / Gmail MVP
```

### Phase 0

Completed.

Purpose:

```text
characterization + regression protection
```

Status:

```text
COMPLETE
```

Known waivers and deferrals are documented in the project state and ADRs.

### Phase 1

Phase 1 was merged into Phase 2 through ADR-027.

No speculative architecture skeleton is created independently.

The required module structure is introduced when Phase 2 creates its first real
module-owned domain concepts.

### Phase 2

Connections.

Scope:

```text
Connections module
connections table
oauth_states table
Google OAuth connection flow
encrypted credentials
list connections
disconnect connection
tests
```

No Google API actions are implemented.

No Gmail actions are implemented.

No workflow engine is implemented.

No execution engine is implemented.

### Phase 3

Integrations.

The existing Google capabilities become registered Integrations.

Examples:

```text
Gmail
Sheets
Calendar
Docs
Analytics
```

### Phase 4

Credential compatibility.

The platform begins resolving credentials from Connections while preserving
legacy compatibility.

The existing production login callback remains unchanged.

### Phase 5+

Automation and Execution.

This includes:

```text
Workflows
Triggers
Actions
Execution
Mapping
Gmail MVP
Polling
```

The exact phase boundary may evolve only through an approved ADR.

---

# 35. Architecture Change Policy

This architecture is locked.

An implementation phase MUST NOT silently:

* change module ownership
* introduce a different global architecture
* create new global business layers
* move new functionality outside its owning module
* change the Core naming convention
* move module-owned migrations to global `database/migrations`
* move module-owned Eloquent models to global `app/Models`
* place module-owned routes permanently in global route files
* introduce Google applications as top-level modules
* duplicate the User domain/model
* change protected production authentication behavior

If implementation reveals a genuine architectural problem, the implementation
must stop at the architectural boundary and propose a new ADR.

The required process is:

```text
Current Architecture
        ↓
Identified Problem
        ↓
Proposed Change
        ↓
ADR
        ↓
Human Approval
        ↓
Implementation
```

No silent architectural changes are permitted.

---

# Final Architecture Principle

The project follows this rule:

```text
One Laravel application
        +
Five explicit business modules
        +
DDD boundaries inside each module
        +
Module-owned persistence
        +
Module-owned HTTP
        +
Framework-independent Core
        +
Explicit Application use cases
        +
Infrastructure adapters
        +
Incremental legacy migration
        =
Production-safe Modular Monolith
```

The canonical ownership is:

```text
Identity
    → User / authentication / identity

Integrations
    → Google apps / provider definitions / actions / triggers

Connections
    → external accounts / OAuth / credentials

Automation
    → workflows / triggers / steps

Execution
    → runs / step execution / history
```

The canonical physical root is:

```text
app/Modules/
```

The canonical DDD layer inside every module is:

```text
Core/
```

New code goes into its owning module from day one.

Legacy code moves only through controlled migration phases.

Architecture changes require explicit ADR approval.
