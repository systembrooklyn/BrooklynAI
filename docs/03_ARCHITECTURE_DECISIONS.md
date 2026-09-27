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
* module localization
* new-code ownership

Earlier ADRs remain historical records.

If an earlier ADR uses terminology or physical structures that are superseded by ADR-028, ADR-028 governs the current implementation.

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

# Future ADRs

New decisions should use:

```text
ADR-029
ADR-030
ADR-031
...
```

Each new decision should include:

* Status
* Context
* Decision
* Consequences
* Migration impact where relevant

Do not rewrite old decisions to hide historical changes.

If a future decision changes ADR-028, it must explicitly identify the affected ADR-028 rule and explain the reason for the change.
