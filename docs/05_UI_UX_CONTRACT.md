# UI/UX Contract

Status: ACCEPTED / REFERENCE

## Purpose

The mobile UI has already been designed.
Backend implementation must support the existing product flow
without redesigning the mobile experience.

## Current Automation Flow

1. Automation landing page
2. Flows list
3. Create new flow
4. Select or connect an account
5. Configure trigger
6. Configure action
7. Configure schedule where applicable
8. Test flow
9. Save and run flow

## Account Selection

A user may have multiple connected accounts for the same provider.

The UI must be able to:
- display connected accounts
- select a specific account
- connect another account

The backend must expose connection metadata safely.

## Provider Agnostic Rule

The current UI prototype uses Gmail as an example.

The backend must NOT model the entire Automation domain as Gmail-specific.

Provider/application-specific behavior belongs to Integrations.

Connections represent external accounts independently of workflows.

## Terminology

UI:
Flow

Backend:
Workflow

UI:
Connected account

Backend:
Connection

UI:
Trigger

Backend:
Workflow Trigger

UI:
Action

Backend:
Workflow Action/Step

## UI Preservation

Backend implementation must not invent alternative product flows
when the existing UI already establishes the intended user journey.

Any API contract required by the UI must be designed to support
the existing screens and future provider expansion.

## Current UI Scope

The provided APK is a Gmail-oriented prototype/reference.
It does not represent the complete final multi-provider UI.

The backend must not assume Gmail is the only provider.
