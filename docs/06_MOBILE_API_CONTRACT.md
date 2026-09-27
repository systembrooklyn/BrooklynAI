# 06 — Mobile API Contract

Status: read-only contract derived from the currently implemented API.

Version: 1.0.4
Last updated: after Catalog Contract Alignment batch.

This document is written for the mobile developer. It describes only behavior that exists in the backend today. Anything not present in the code is explicitly marked NOT IMPLEMENTED or DEFERRED.

---

## 0. Base facts

- Backend: Laravel 12 API-only, Sanctum bearer authentication.
- All endpoints under `/api/*` return JSON.
- Protected endpoints require `Authorization: Bearer <token>` and `Accept: application/json`.
- Envelope convention (most endpoints):
    - Success: `{ "message": "...", "data": <object|array> }`
    - Some endpoints return only `{ "message": "..." }`.
    - Validation errors: `{ "message": "...", "errors": { <field>: [<string>] } }` with HTTP 422.
- The base URL depends on deployment (local: `http://127.0.0.1:8000`, production: the deployed host).

---

## 1. Authentication

### 1.1 Production authentication — Google OAuth (legacy path)

Currently, the only production login in the codebase is the Google OAuth flow implemented in `App\Http\Controllers\Api\GoogleAuthController`.

Flow:

1. Mobile opens `GET /api/auth/google/redirect` in a browser / web view.
    - No auth required.
    - Returns HTTP 302 to Google's OAuth consent screen.
2. The user consents.
3. Google redirects to `GET /api/auth/google/callback?code=...`.
    - No auth required.
    - Backend exchanges the code with Google, **resolves the user by `email` — the callback only updates an existing `users` row and does NOT create new users**, issues a Sanctum token via `createToken(...)`, and redirects the browser to:
        - `https://www.aibrooklyn.net?token=<sanctum-token>` on success
        - `https://www.aibrooklyn.net?token=null` when the user does not exist or has no bot access
        - `/account-deleted.html` when the user is soft-deleted
4. Mobile must intercept the final redirect and extract the token from the `token` query parameter.

> Note: the callback does NOT create users. Creation code is present but commented out. Users whose email is not already in the `users` table receive `token=null`.

There is also a legacy variant `GET /api/auth/google/redirect-google` that adds the full historical Google scope set (Gmail send, Calendar, Sheets, Drive, Analytics, Docs). The callback behavior is identical. Prefer `/api/auth/google/redirect` for new work.

### 1.2 Authenticated user endpoint

```
GET /api/user
Authorization: Bearer <token>
```

Response (HTTP 200):

```json
{
    "message": "User Retrieved successfully",
    "data": {
        "id": 123,
        "name": "Jane",
        "avatar": "https://...",
        "email": "jane@example.com",
        "has_bot_access": true,
        "access_expiry": "2026-12-31"
    }
}
```

Unauthenticated → HTTP 401.

### 1.3 Logout

```
POST /api/logout
Authorization: Bearer <token>
```

Behavior: revokes every Sanctum token owned by the authenticated user via `$request->user()->tokens()->delete()`.

Response (HTTP 200):

```json
{ "message": "Successfully logged out." }
```

### 1.4 Account deactivation

```
POST /api/account/deactivate
Authorization: Bearer <token>
Body (optional): { "reason": "..." }
```

Behavior: soft-deletes the user, revokes all Sanctum tokens. If `reason` is provided, it is stored on the user before soft delete and logged.

Response (HTTP 200):

```json
{ "message": "Your account has been deactivated successfully." }
```

### 1.5 Test / dev-only login — NOT for production

```
POST /api/test/login
Body: { "email": "...", "password": "<master password>" }
```

This endpoint:

- Validates the body.
- Compares `password` against `env('masterpassword')`.
- Looks up the user by `email`.
- Returns a Sanctum token for that user.

Response (HTTP 200):

```json
{
    "token": "<sanctum-token>",
    "user": {
        "id": 123,
        "name": "Jane",
        "email": "jane@example.com",
        "avatar": "https://...",
        "google_id": "..."
    },
    "message": "This is for TESTING ONLY "
}
```

**Important:**

- `token` is at the **top level of the response**, not under `data`. Mobile clients using this endpoint must read `response.token`, not `response.data.token`.
- This envelope does NOT follow the standard `{ message, data }` convention used by the rest of the API.
- This endpoint is **development / integration-testing only**.
- It must **not** be used as the production authentication path.
- It bypasses normal identity verification: it does not check the user's own password.

Ownership note: anyone who knows the master password and the target email can obtain a token for that user. It exists only for local development. Do not build the mobile production authentication flow on it.

### 1.6 How the mobile client obtains a token

Three paths exist today:

**Production path A — Google OAuth** (see §1.1):

1. Mobile opens `/api/auth/google/redirect` in a browser / web view.
2. The user authenticates with Google.
3. The backend redirects to the configured frontend URL with `?token=<sanctum-token>`.
4. Mobile extracts the token from the query parameter.

**Production path B — Email/password login** (see §1.7):

1. Mobile calls `POST /api/login` with `{ email, password }`.
2. The backend returns `{ message, data: { token, user } }`.
3. Mobile stores `data.token` and uses it the same way as a Google OAuth token.

**Development / integration-testing path — `/api/test/login`** (see §1.5):

1. Mobile calls `POST /api/test/login` with an existing user's email and the shared master password.
2. The backend returns `token` at the top level of the JSON body.
3. Mobile stores the token and uses it the same way as a production token.

All three paths result in the same kind of Sanctum bearer token, and the mobile client uses it identically afterward:

```
Authorization: Bearer <token>
```

**What does NOT exist today:**

- Email verification flow.
- Password reset flow.
- Refresh-token mechanism.
- Per-device token revocation.

Do not attempt to implement these in the mobile client; they are not available server-side.

### 1.7 Email/password login (production)

```
POST /api/login
Body: {
  "email": "user@example.com",
  "password": "secret"
}
```

Public — no auth required.

Success (HTTP 200):

```json
{
    "message": "Login successful.",
    "data": {
        "token": "<sanctum-token>",
        "user": {
            "id": 123,
            "name": "Jane",
            "email": "jane@example.com",
            "avatar": "https://...",
            "has_bot_access": true,
            "access_expiry": "2026-12-31"
        }
    }
}
```

- `data.token` is the Sanctum bearer token. Use it as `Authorization: Bearer <token>` on subsequent requests.
- `data.user` follows the same field shape used by `GET /api/user` (§1.2).

Invalid credentials (HTTP 401):

```json
{ "message": "Invalid credentials." }
```

The same generic 401 is returned for every credential failure:

- Email does not exist.
- Password is wrong.
- User is soft-deleted.
- User has no usable password.

The response never reveals which case occurred. Mobile must show a single generic "invalid credentials" message to the user.

Validation failure (HTTP 422):

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password field is required."]
    }
}
```

- `email` — required, string, valid email format.
- `password` — required, string.

**Notes:**

- Login does not require `has_bot_access` to be true. Authentication and product access are separate concerns.
- Login does not modify `has_bot_access` or `access_expiry`.
- Login does not create users. Users must be provisioned first (via the existing business flow that calls `POST /api/register`, or via the Google flow for existing users).
- Login does not touch Google fields.
- Login does not normalize email casing.
- Login does not apply rate limiting (not implemented anywhere in the project today).

**Operator master password (migration phase).** During the current migration away
from `/api/test/login`, `POST /api/login` also accepts a master password configured
on the server. When the supplied `password` equals the configured master password,
the user identified by `email` is authenticated without verifying their own password.
The master password is never returned in any response, error, or log. This path
exists only to allow already-provisioned users who do not yet have a usable password
to authenticate, and it is disabled when the server-side value is empty or unset.

This does not change the response envelope, the HTTP status codes, or the failure body:
all credential failures still return `401 { "message": "Invalid credentials." }`.

### 1.8 Registration (existing provisioning flow)

```
POST /api/register
Body: {
  "name": "Jane",
  "email": "jane@example.com",
  "password": "min6chars",
  "st_num": null,
  "access_expiry": "2027-01-01"
}
```

- Public (no auth).
- Creates a user if the email is new (HTTP 201), updates `access_expiry` and `has_bot_access` if the email exists (HTTP 200).
- **Does not return a Sanctum token.**
- `has_bot_access` is set based on `today() < access_expiry`.
- This endpoint is used by an existing external provisioning flow (Google Apps Script). It is not a public signup form.

---

## 2. User ownership and isolation (verified)

Every user-owned resource is scoped to the authenticated user. The table below is the result of tracing the actual repository and action code.

| Resource                                           | Isolation mechanism                                                                                 | Status |
| -------------------------------------------------- | --------------------------------------------------------------------------------------------------- | ------ |
| Connections list                                   | `ConnectionRepository::listForUser($userId)`                                                        | SAFE   |
| Connection fetch/delete                            | `ConnectionRepository::findForUser($userId, $connectionId)`                                         | SAFE   |
| Connection use in trigger                          | `ConnectionRepository::findForUser($userId, $connectionId)` inside `UpsertWorkflowTriggerAction`    | SAFE   |
| Connection use in step                             | Same, inside `AddWorkflowStepAction` / `UpdateWorkflowStepAction`                                   | SAFE   |
| Workflow read/update/delete/restore/activate/pause | `WorkflowRepository::findForUser($userId, $workflowId)`                                             | SAFE   |
| Workflow list                                      | `WorkflowRepository::listForUser($userId)`                                                          | SAFE   |
| Trigger read/write                                 | Reached only after the parent workflow passes `findForUser`                                         | SAFE   |
| Step read/write                                    | Same                                                                                                | SAFE   |
| Execution read                                     | `ExecutionRepository::findForUser($userId, $executionId)`                                           | SAFE   |
| Execution list                                     | `ExecutionRepository::listForWorkflow($workflowId, $limit)` after the workflow passes `findForUser` | SAFE   |
| Manual execution                                   | `RunWorkflowAction` first calls `WorkflowRepository::findForUser`                                   | SAFE   |

Expected HTTP status when the resource exists but belongs to another user: **404** (not 403), consistent with the project's "do not reveal existence" convention.

### 2.1 Cross-user isolation tests (mobile / QA matrix)

| Scenario                                                                | Expected result                                                            | Current status |
| ----------------------------------------------------------------------- | -------------------------------------------------------------------------- | -------------- |
| User A lists connections                                                | Sees only own connections                                                  | SAFE           |
| User A requests User B connection_id via `DELETE /api/connections/{id}` | 404                                                                        | SAFE           |
| User A creates workflow using User B `connection_id` on trigger         | 404 `connection_not_found_or_not_owned` (on activation) or 404 (on upsert) | SAFE           |
| User A creates workflow using User B `connection_id` on step            | Same                                                                       | SAFE           |
| User A lists workflows                                                  | Sees only own workflows                                                    | SAFE           |
| User A requests User B workflow via `GET /api/workflows/{id}`           | 404                                                                        | SAFE           |
| User A updates / deletes / activates / pauses User B workflow           | 404                                                                        | SAFE           |
| User A lists executions of User B workflow                              | 404                                                                        | SAFE           |
| User A requests User B execution via `GET /api/executions/{id}`         | 404                                                                        | SAFE           |

All of the above are exercised by existing tests: `WorkflowOwnershipTest`, `WorkflowTriggerHttpTest::test_upsert_rejects_non_owned_connection`, `WorkflowStepHttpTest::test_*_returns_404_for_other_users_workflow`, `RunWorkflowOwnershipTest`, `ShowExecutionTest::test_show_execution_returns_404_for_other_users_execution`, `DisconnectConnectionTest::test_disconnect_cannot_delete_other_users_connection`.

---

## 3. Connections API

### 3.1 List connections

```
GET /api/connections
Authorization: Bearer <token>
```

Response (HTTP 200):

```json
{
    "message": "Connections retrieved successfully",
    "data": [
        {
            "id": 12,
            "provider": "google",
            "external_account_id": "1021...",
            "email": "user@example.com",
            "display_name": "User Name",
            "scopes": [
                "openid",
                "email",
                "profile",
                "https://www.googleapis.com/auth/gmail.readonly",
                "https://www.googleapis.com/auth/gmail.send"
            ],
            "status": "active",
            "created_at": "2026-09-25T08:00:00+00:00",
            "updated_at": "2026-09-25T08:00:00+00:00"
        }
    ]
}
```

Fields never exposed: `access_token`, `refresh_token`, `last_error_message`, `client_secret`, database-internal values.

`status` enum: `active`, `error`, `needs_reauth`, `revoked`.

### 3.2 Start Google OAuth for a connection

```
POST /api/connections/google/start
Authorization: Bearer <token>
Body (optional): { "capability": "gmail" }
```

- `capability` must be a value recognized by the backend's capability registry. Currently only `gmail` is supported.
- Unknown capability → HTTP 422 with a validation error on `capability`.
- Any `scopes` field in the request body is **ignored**. The client cannot request arbitrary OAuth scopes.

Response (HTTP 200):

```json
{ "redirect_url": "https://accounts.google.com/o/oauth2/v2/auth?..." }
```

Mobile opens `redirect_url` in the browser / web view. Google redirects back to the backend callback.

### 3.3 Google OAuth callback

```
GET /api/connections/google/callback?code=...&state=...
```

- No auth required.
- The backend consumes the state, exchanges the code, persists a `Connection` row (upserted by `user_id` + `provider` + `external_account_id`), then redirects to the configured frontend URL with either `?status=connected` or `?error=<code>`.

Error codes that can appear in the `error` query parameter: `oauth_state_invalid`, `oauth_state_expired`, `oauth_state_replayed`, `oauth_code_exchange_failed`, `oauth_scope_invalid`, `oauth_account_fetch_failed`, `oauth_connection_failed`.

Mobile must watch the redirect and then re-fetch `GET /api/connections` to read the persisted connection.

### 3.4 Delete connection

```
DELETE /api/connections/{connection}
Authorization: Bearer <token>
```

Response (HTTP 200): `{ "message": "Connection disconnected successfully" }`.
Deleting a connection you do not own: HTTP 404 `{ "message": "Connection not found" }`.

### 3.5 Gmail capability scopes requested at consent time

For `capability=gmail` the backend requests:

- `openid`
- `email`
- `profile`
- `https://www.googleapis.com/auth/gmail.readonly`
- `https://www.googleapis.com/auth/gmail.send`

`gmail.compose` and `gmail.labels` are NOT requested at consent time. A `create_draft` action can still require `gmail.compose` at activation time; that scope would not be present on a consent-only Gmail connection.

---

## 4. Catalog / Discovery API

### 4.1 Endpoint

```
GET /api/catalog
Authorization: Bearer <token>
```

Response envelope:

```json
{
    "message": "Catalog retrieved successfully",
    "data": {
        "integrations": [
            /* ... */
        ]
    }
}
```

### 4.2 Integration object

```json
{
    "integration_key": "google.gmail",
    "provider_key": "google",
    "name": "Gmail",
    "description": "Send, receive, and manage emails through Google Gmail.",
    "category": "email",
    "auth": { "type": "oauth2" },
    "triggers": [
        /* ... */
    ],
    "actions": [
        /* ... */
    ]
}
```

**Canonical key rule.** The `integration_key` value is used **verbatim** as the `integration_key` field in Workflow API requests. No translation is required on the mobile side.

**`auth.provider_key` is NOT exposed.** The provider is available at the top level as `provider_key`. Mobile should use `provider_key` to construct the connection URL: `POST /api/connections/{provider_key}/start`.

### 4.3 Trigger object

```json
{
    "trigger_key": "new_email_received",
    "label": "New Email Received",
    "description": "Trigger when a new email is received.",
    "strategy": "poll",
    "capability": "gmail",
    "config": {
        "label_id": {
            "label": "Label",
            "type": "select",
            "required": false,
            "options_source": {
                "operation_id": "gmail.labels",
                "params": { "connection_id": "{{connection_id}}" }
            }
        }
    }
}
```

**Canonical key rule.** `trigger_key` is used verbatim as the `trigger_key` field in `PUT /api/workflows/{id}/trigger`.

**`config` map.** Each key inside `config` is the exact field key Mobile must send inside the `config` object of the Workflow API request. No translation, no renaming.

**`capability` is nullable.** The example above shows a Gmail trigger, which declares `capability: "gmail"`. Integrations that do not declare a capability (e.g. Calendar, Sheets, Docs, Analytics) will return `"capability": null`. Do not type `capability` as non-nullable on the mobile side.

**`required_scopes` is not exposed.** Scope validation happens server-side at activation time. Mobile does not send scopes.

### 4.4 Action object

```json
{
    "action_key": "send_email",
    "label": "Send Email",
    "description": "Send a new email message.",
    "capability": "gmail",
    "config": {
        "to": { "label": "To", "type": "email", "required": true },
        "subject": { "label": "Subject", "type": "string", "required": true },
        "body": { "label": "Body", "type": "text", "required": true }
    }
}
```

**Canonical key rule.** `action_key` is used verbatim as the `action_key` field in `POST /api/workflows/{id}/steps` and `PUT /api/workflows/{id}/steps/{position}`.

**`config` map.** Same rule as triggers: each key inside `config` is the exact field key Mobile must send in the Workflow API request `config` object.

**Actions with no configuration** return `"config": {}` (empty object). Mobile must not assume any fields exist.

### 4.5 Field object (inside `config` map)

```json
{
    "label": "To",
    "type": "email",
    "required": true
}
```

Optional keys (only present when defined): `description`, `default`, `options`, `options_source`.

**Field type values and mobile UI mapping:**

| `type`    | UI control             |
| --------- | ---------------------- |
| `email`   | Email input            |
| `string`  | Single-line text input |
| `text`    | Multi-line textarea    |
| `boolean` | Toggle / checkbox      |
| `integer` | Numeric input          |
| `select`  | Dropdown / picker      |

**Static `options`** shape (mutually exclusive with `options_source`):

```json
"options": [
  { "value": "INBOX", "label": "Inbox" },
  { "value": "SENT", "label": "Sent" }
]
```

**Dynamic `options_source`** shape (mutually exclusive with `options`):

```json
"options_source": {
  "operation_id": "gmail.labels",
  "params": { "connection_id": "{{connection_id}}" }
}
```

### 4.6 Currently catalog-defined integrations

- `google.gmail` — `send_email`, `reply_to_email`, `create_draft`, trigger `new_email_received`
- `google.calendar` — 5 actions
- `google.sheets` — 9 actions
- `google.docs` — 7 actions
- `google.analytics` — 5 actions
- `google.drive` — no actions, no triggers

### 4.7 Currently executable at runtime vs. catalog-only

The backend currently has runtime handlers only for:

- **Trigger** `google.gmail.new_email_received` — driven by `workflows:poll-gmail`.
- **Action** `google.gmail.send_email` — driven by `GmailSendEmailHandler`.

Every other catalog entry is **catalog-defined but not currently executable as a workflow trigger/step** at runtime. The workflow API will still accept them (validation only checks catalog membership), but execution will fail because no handler exists for them.

Field metadata is authoritative only where a runtime handler exists:

- `send_email`: `to` (email, required), `subject` (string, required), `body` (text, required).
- `new_email_received`: `label_id` (select, optional) with dynamic options from `gmail.labels`.

`reply_to_email`, `create_draft`, and all Calendar / Sheets / Docs / Analytics actions expose `config: {}`.

Mobile should treat the catalog as the discovery surface, and additionally gate action availability on the presence of a non-empty `config` object today. This will change when a future batch adds more handlers.

### 4.8 Dynamic configuration options

When a field declares `options_source`, Mobile resolves it as follows:

1. Read `options_source.operation_id`.
2. Look up the operation in the published Swagger spec (e.g. `gmail.labels` → `GET /api/gmail/labels`).
3. Substitute any `params` placeholders. The placeholder `{{connection_id}}` is replaced with the currently selected connection ID.
4. Call the endpoint using the standard authentication (`Authorization: Bearer <token>`).
5. Use the returned array as the option list. The mapping is:
    - `value` ← the item's `id` field
    - `label` ← the item's `name` field

No hardcoded endpoint per integration. No mapping table. The `operation_id` is the canonical Swagger identifier already published in the API contract.

---

## 5. Workflow creation flow (mobile)

### 5.0 End-to-end summary

```
Login (Google OAuth redirect, email/password login, or /api/test/login for dev)
→ Sanctum token
→ GET /api/connections                (list user-owned connections)
→ GET /api/catalog                    (discover integrations, triggers, actions, config)
→ POST /api/workflows                 (create draft)
→ PUT  /api/workflows/{id}/trigger    (attach trigger)
→ POST /api/workflows/{id}/steps      (append step)
→ POST /api/workflows/{id}/activate   (validate capability and activate)
→ POST /api/workflows/{id}/execute    (manual run; optional)
```

### 5.1 Create workflow

```
POST /api/workflows
Authorization: Bearer <token>
Body: {
  "name": "Gmail auto-reply",
  "description": "optional"
}
```

Response (HTTP 201):

```json
{
    "message": "Workflow created successfully",
    "data": {
        "id": 42,
        "name": "Gmail auto-reply",
        "description": "optional",
        "status": "draft",
        "created_at": "...",
        "updated_at": "...",
        "deleted_at": null,
        "trigger": null,
        "steps": []
    }
}
```

Validation: `name` required, max 120 chars. `description` nullable, max 5000.

### 5.2 Show workflow

```
GET /api/workflows/{id}
```

Returns the workflow including its trigger (if any) and steps (ordered by position).

### 5.3 List workflows

```
GET /api/workflows
GET /api/workflows?trashed=true
```

Returns only the authenticated user's workflows.

### 5.4 Update workflow name/description

```
PUT /api/workflows/{id}
Body: { "name": "...", "description": "..." }
```

### 5.5 Delete workflow (soft delete)

```
DELETE /api/workflows/{id}
```

### 5.6 Restore workflow

```
POST /api/workflows/{id}/restore
```

Restores the workflow with its trigger and steps. Does not auto-activate.

### 5.7 Upsert trigger

```
PUT /api/workflows/{id}/trigger
Authorization: Bearer <token>
Body: {
  "integration_key": "google.gmail",
  "trigger_key": "new_email_received",
  "connection_id": 12,
  "interval_minutes": null,
  "config": { "label_id": "INBOX" }
}
```

Fields:

- `integration_key` — required. Must exist in catalog.
- `trigger_key` — required. Must be declared by that integration.
- `connection_id` — optional integer. If provided, must be a connection owned by the authenticated user.
- `interval_minutes` — optional integer 1..1440.
- `config` — optional object. Keys must match the trigger's catalog `config` fields. Template syntax is validated on save; runtime errors are separate.

**`integration_key`, `trigger_key`, and each key inside `config` are used verbatim from the Catalog response.** No translation.

**`interval_minutes` semantics:** `interval_minutes` is relevant only for `schedule`-strategy triggers (consumed by `workflows:run-scheduled`). It does **not** configure the Gmail polling frequency for `new_email_received`. For a Gmail poll trigger, `interval_minutes` is stored but not used by the polling runtime — polling runs on its own schedule (`workflows:poll-gmail`, invoked every minute with `withoutOverlapping(5)`).

**`label_id` handling on `new_email_received` (IMPORTANT):**

- If the user has selected a label → send `config.label_id` as a non-empty string, e.g. `{ "config": { "label_id": "INBOX" } }`.
- If the user has NOT selected a label (i.e. "all mail") → **omit `label_id` from `config` entirely**.
- **Do NOT send `"label_id": null`.**
- **Do NOT send `"label_id": ""`.**

Reason: `PollGmailCommand::extractLabelIds` distinguishes three cases:

- Key absent → no filter, polling proceeds.
- Key present with a non-empty string → filter applied.
- Key present with `null`, empty string, or non-string → the polling runtime treats it as malformed and **skips the workflow on every poll** (cursor unchanged, warning logged). It does not behave as "no filter".

Response (HTTP 200): the persisted trigger.

Errors:

- Unknown integration → 422 with `errors.integration_key`.
- Unknown trigger → 422 with `errors.trigger_key`.
- Non-owned connection → 404 `{ "message": "Connection not found" }`.
- Invalid template syntax → 422 `{ "message": "Validation failed", "errors": { "config": [...] } }`.

There is no separate create/replace endpoint for triggers — `PUT` is upsert.

### 5.8 Delete trigger

```
DELETE /api/workflows/{id}/trigger
```

### 5.9 Add step

```
POST /api/workflows/{id}/steps
Body: {
  "integration_key": "google.gmail",
  "action_key": "send_email",
  "connection_id": 12,
  "config": {
    "to": "recipient@example.com",
    "subject": "Hi",
    "body": "<p>Hello</p>"
  }
}
```

- `integration_key` / `action_key` — required, must exist in catalog.
- `connection_id` — optional integer, must be user-owned if provided.
- `config` — optional object. Keys must match the action's catalog `config` fields.

Response (HTTP 201): the persisted step. Position is assigned automatically as `max(existing position) + 1`.

Errors: same pattern as trigger upsert.

### 5.10 Update step

```
PUT /api/workflows/{id}/steps/{position}
Body: same shape as POST /api/workflows/{id}/steps
```

Replaces the step in place. Positions are not reindexed.

### 5.11 Delete step

```
DELETE /api/workflows/{id}/steps/{position}
```

Remaining positions are not shifted.

### 5.12 Activate workflow

```
POST /api/workflows/{id}/activate
Authorization: Bearer <token>
```

Validation order (all before the workflow status changes):

1. Workflow exists and is owned by the caller.
2. Trigger exists.
3. Draft → Active transition allowed.
4. Trigger's integration + trigger key exist in catalog.
5. Trigger's `capability` / `requiredScopes` are satisfied by the referenced connection.
6. Every step's integration + action key exist in catalog.
7. Every step's `capability` / `requiredScopes` are satisfied by its referenced connection.

On success (HTTP 200): the workflow status becomes `active`.

On failure (HTTP 409) the workflow remains `draft`. Structured error:

```json
{
  "message": "<localized>",
  "error": "<code>",
  "context": { ... }
}
```

Error codes:

- `missing_connection`
- `connection_not_found_or_not_owned`
- `unsupported_capability`
- `missing_scopes`
- `unknown_integration`
- `unknown_trigger`
- `unknown_action`

`missing_scopes` context includes `missing_scopes: [<scope-uri>, ...]`.

For a simpler trigger failure (no trigger configured) the response is HTTP 409 with `{ "message": "Workflow cannot be activated without a trigger" }`.

### 5.13 Pause workflow

```
POST /api/workflows/{id}/pause
```

Active → Paused. Invalid transition → HTTP 409 `{ "message": "Invalid workflow state transition" }`.

### 5.14 Execute workflow (manual run)

```
POST /api/workflows/{id}/execute
Authorization: Bearer <token>
Body (optional): {
  "trigger_payload": { ... },
  "idempotency_key": "my-custom-key-1"
}
```

Behavior:

- Draft and Active workflows can be executed. Paused → HTTP 409.
- `idempotency_key` — optional, max 191 chars. Keys starting with `schedule:` or `gmail:` are rejected with HTTP 422 (reserved namespaces).
- If a running/pending execution already exists for the workflow → HTTP 409.

Response: HTTP 201 for a new execution, HTTP 200 for an idempotent replay. Body contains the execution plus its steps.

### 5.15 List executions for a workflow

```
GET /api/workflows/{id}/executions?limit=50
```

`limit` — optional 1..50.

### 5.16 Show execution

```
GET /api/executions/{id}
```

Only the owning user can read an execution.

---

## 6. Gmail labels

```
GET /api/gmail/labels?connection_id=12
Authorization: Bearer <token>
```

Returns the whitelisted labels for the referenced Gmail connection.

Response (HTTP 200):

```json
{
    "message": "Labels retrieved successfully.",
    "data": [
        { "id": "INBOX", "name": "INBOX", "type": "system" },
        { "id": "Label_1", "name": "My label", "type": "user" }
    ]
}
```

- `connection_id` is optional. If omitted, the backend resolves credentials via legacy user tokens.
- If `connection_id` is supplied but is not owned by the caller → 404 `{ "message": "Connection not found" }`.
- Errors from Gmail → HTTP 500 with a descriptive message.

Why labels are separate from the catalog:

- Label values depend on the user's Gmail account. They cannot be hardcoded in the catalog.
- The catalog declares `label_id` with `type: "select"` and `options_source: { operation_id: "gmail.labels", params: { connection_id: "{{connection_id}}" } }`.
- Mobile resolves `options_source.operation_id` against the published Swagger spec. `gmail.labels` maps to `GET /api/gmail/labels`. Mobile substitutes `{{connection_id}}` with the currently selected connection ID, calls the endpoint, and maps the returned `data[]` items to `{ value: item.id, label: item.name }`.

**How to build the trigger config in mobile:**

1. Read the trigger's `config.label_id` object from the Catalog.
2. See `type: "select"` → render a dropdown.
3. See `options_source.operation_id: "gmail.labels"` → resolve this operation through the API contract.
4. Substitute `{{connection_id}}` with the user's chosen Gmail connection ID.
5. Call the endpoint. Render `data[]` as options.
6. When the user picks a label → put its `id` into `config.label_id`.
7. When the user picks "all mail" → omit `label_id` from `config` entirely (do NOT send `null` or `""`).

**No Gmail-specific hardcoding in the mobile app.** The mobile app reads `options_source` and resolves it declaratively.

Label mutation (create/update/delete) is **not implemented**. The `gmail.labels` scope is **not** part of consent. Push / Pub/Sub / `historyId` cursors are **not implemented**.

---

## 7. Error shapes

### 7.1 Unauthenticated (401)

Laravel standard:

```json
{ "message": "Unauthenticated." }
```

Returned by any route under `auth:sanctum` when the token is missing or invalid.

**Exception:** `POST /api/login` returns `{ "message": "Invalid credentials." }` (also HTTP 401) for credential failures — see §1.7. That is the login-specific 401 body, not the middleware 401.

### 7.2 Validation (422)

```json
{
    "message": "The given data was invalid.",
    "errors": { "<field>": ["<message>"] }
}
```

### 7.3 Not found / not owned (404)

```json
{ "message": "Workflow not found" }
{ "message": "Workflow step not found" }
{ "message": "Workflow trigger not found" }
{ "message": "Connection not found" }
{ "message": "Execution not found" }
```

Used for both "does not exist" and "belongs to another user". Mobile must not attempt to distinguish.

### 7.4 Conflict (409)

- Activation without a trigger → `{ "message": "Workflow cannot be activated without a trigger" }`
- Invalid lifecycle transition → `{ "message": "Invalid workflow state transition" }`
- Manual execution of a paused workflow → `{ "message": "Workflow is not active" }`
- Overlapping execution → `{ "message": "An execution is already running for this workflow" }`
- Capability validation → structured error with `message`, `error`, `context` (see §5.12)

### 7.5 OAuth / provider errors

`GET /api/connections/google/callback` returns 302 redirects with an `error=<code>` query parameter (see §3.3). Not a JSON response.

### 7.6 Server errors (500)

Google provider failures from the integration endpoints (Gmail send, Calendar, Sheets, Docs, Analytics, labels) surface as HTTP 500 with a JSON body containing at least a `message` field. These are not meant to be interpreted field-by-field by the mobile client; treat them as retriable transient errors at the workflow-step level.

---

## 8. Mobile end-to-end example (Gmail auto-reply)

Preconditions:

- User has authenticated and mobile holds a Sanctum token (via production Google OAuth, `POST /api/login`, or `/api/test/login` for dev/testing).
- User has already connected a Gmail account with the `gmail` capability so a `connection_id` exists.

Step 1 — List connections

```
GET /api/connections
Authorization: Bearer <token>
```

Pick `connection_id = 12` from `data[]`.

Step 2 — Fetch catalog

```
GET /api/catalog
Authorization: Bearer <token>
```

Confirm `google.gmail` exists; read its `triggers` and `actions`.

Step 3 — Create workflow

```
POST /api/workflows
Body: { "name": "Gmail auto-reply" }
```

Capture `id = 42`, `status = "draft"`.

Step 4 — Resolve dynamic label options

Read `catalog.integrations[google.gmail].triggers[new_email_received].config.label_id`:

```json
{
    "label": "Label",
    "type": "select",
    "required": false,
    "options_source": {
        "operation_id": "gmail.labels",
        "params": { "connection_id": "{{connection_id}}" }
    }
}
```

Resolve `gmail.labels` through the API contract → `GET /api/gmail/labels`. Substitute `{{connection_id}} = 12`. Call:

```
GET /api/gmail/labels?connection_id=12
```

Render the returned `data[]` as a dropdown. Two possible user choices:

**Case A — user picks a label (e.g. `INBOX`):** include `config.label_id` in the trigger body (Step 5).

**Case B — user picks "all mail":** omit `config.label_id` entirely. Do not send `null` or `""`.

Step 5 — Configure Gmail trigger

```
PUT /api/workflows/42/trigger
Body (Case A — with label):
{
  "integration_key": "google.gmail",
  "trigger_key": "new_email_received",
  "connection_id": 12,
  "config": { "label_id": "INBOX" }
}
```

```
PUT /api/workflows/42/trigger
Body (Case B — no label):
{
  "integration_key": "google.gmail",
  "trigger_key": "new_email_received",
  "connection_id": 12,
  "config": {}
}
```

Step 6 — Add Gmail send_email step

Read `catalog.integrations[google.gmail].actions[send_email].config` for the field list. Then:

```
POST /api/workflows/42/steps
Body: {
  "integration_key": "google.gmail",
  "action_key": "send_email",
  "connection_id": 12,
  "config": {
    "to": "{{ trigger.from }}",
    "subject": "Re: {{ trigger.subject }}",
    "body": "<p>Auto-reply</p>"
  }
}
```

Step 7 — Activate workflow

```
POST /api/workflows/42/activate
```

Expect HTTP 200 with `data.status = "active"`. On HTTP 409, inspect the structured error and adjust.

Step 8 — Manual execution (optional, for testing)

```
POST /api/workflows/42/execute
Body: {
  "trigger_payload": {
    "from": "someone@example.com",
    "subject": "Hello",
    "message_id": "abc123",
    "thread_id": "t1",
    "received_at": "2026-09-25T08:00:00+00:00",
    "received_at_epoch_ms": 1758787200000,
    "to": [], "cc": [], "labels": [], "attachment_ids": [],
    "snippet": "",
    "body": "",
    "has_attachment": false,
    "attachment_count": 0
  }
}
```

Step 9 — Poll execution status

```
GET /api/executions/{execution_id}
```

Inspect `status` and `steps[].status`.

---

## 9. Ownership security test matrix

| Scenario                                    | Expected result                 | Current implementation status |
| ------------------------------------------- | ------------------------------- | ----------------------------- |
| User A lists connections                    | Only User A connections         | SAFE                          |
| User A requests User B connection           | 404                             | SAFE                          |
| User A deletes User B connection            | 404                             | SAFE                          |
| User A uses User B connection on trigger    | 404 (upsert) / 409 (activation) | SAFE                          |
| User A uses User B connection on step       | 404 (create) / 409 (activation) | SAFE                          |
| User A lists workflows                      | Only User A workflows           | SAFE                          |
| User A requests User B workflow             | 404                             | SAFE                          |
| User A updates User B workflow              | 404                             | SAFE                          |
| User A deletes User B workflow              | 404                             | SAFE                          |
| User A activates User B workflow            | 404                             | SAFE                          |
| User A pauses User B workflow               | 404                             | SAFE                          |
| User A accesses User B execution            | 404                             | SAFE                          |
| User A runs User B workflow                 | 404                             | SAFE                          |
| User A upserts User B trigger               | 404                             | SAFE                          |
| User A upserts User B step                  | 404                             | SAFE                          |
| User A fetches labels for User B connection | 404                             | SAFE                          |

All of the above are backed by tests already present in the repository. No `UNSAFE` or `UNCLEAR` cells remain.

---

## 10. Mobile-ready endpoints

Production-ready today:

- `POST /api/register` — provisioning (creates/updates users; no token).
- `POST /api/login` — email/password login returning a Sanctum token.
- `GET /api/auth/google/redirect` — Google login redirect.
- `GET /api/auth/google/callback` — Google login callback.
- `POST /api/logout` — revoke tokens.
- `POST /api/account/deactivate` — soft-delete account.
- `GET /api/user` — current user.
- `GET /api/connections` — list user connections.
- `POST /api/connections/google/start` — start Google connection OAuth.
- `GET /api/connections/google/callback` — connection OAuth callback.
- `DELETE /api/connections/{connection}` — disconnect.
- `GET /api/catalog` — catalog discovery.
- `GET /api/gmail/labels` — list Gmail labels for a connection.
- `GET /api/workflows` — list workflows.
- `POST /api/workflows` — create workflow.
- `GET /api/workflows/{id}` — show workflow.
- `PUT /api/workflows/{id}` — update workflow.
- `DELETE /api/workflows/{id}` — soft-delete workflow.
- `POST /api/workflows/{id}/restore` — restore workflow.
- `PUT /api/workflows/{id}/trigger` — upsert trigger.
- `DELETE /api/workflows/{id}/trigger` — delete trigger.
- `POST /api/workflows/{id}/steps` — append step.
- `PUT /api/workflows/{id}/steps/{position}` — update step.
- `DELETE /api/workflows/{id}/steps/{position}` — delete step.
- `POST /api/workflows/{id}/activate` — activate.
- `POST /api/workflows/{id}/pause` — pause.
- `POST /api/workflows/{id}/execute` — manual execution.
- `GET /api/workflows/{id}/executions` — list executions.
- `GET /api/executions/{id}` — show execution.

Non-production:

- `POST /api/test/login` — dev-only.

---

## 11. Deferred functionality Mobile must not depend on

- Email verification flow. Not implemented.
- Password reset flow. Not implemented.
- Refresh-token / long-lived token policy. Not implemented.
- Per-device token revocation. Not implemented.
- `requires_connection` catalog flag. Not implemented.
- Field metadata for Calendar / Sheets / Docs / Analytics actions. Not implemented.
- Runtime handlers for `reply_to_email` and `create_draft` (and all non-Gmail actions). Not implemented.
- Gmail label mutation / `gmail.labels` capability. Not implemented.
- Gmail attachment byte retrieval. Not implemented.
- Gmail Push (`users.watch`), Google Pub/Sub, `historyId` cursors. Not implemented.
- Retry / DLQ infrastructure. Not implemented.
- Catalog caching / ETag / multi-endpoint catalog. Not implemented.

---

## 12. Final answers

**Q: Are Connections, Workflows, Triggers, Steps, and Executions isolated per authenticated user, or is there any cross-user mixing?**

A: Fully isolated. Every read/write goes through a user-scoped repository method. Cross-user access returns HTTP 404 consistently. No cross-user mixing was found in the code or in any test.

**Q: Does the current login/token flow correctly identify different users, or does the master-password test login map users to the same identity?**

A: Both `POST /api/login` (email/password) and the production Google OAuth flow correctly identify different users, each issuing a distinct Sanctum token per user. The master-password test login also issues a per-user token but does not verify the user's own password and must be treated as dev-only.

**Q: Which login methods are available to the mobile client today?**

A: Three:

- Production Google OAuth (`/api/auth/google/redirect` + callback).
- Production email/password login (`POST /api/login`) — for users who have a password set via the provisioning flow (`POST /api/register`) or another path.
- Dev-only `/api/test/login` for local development.

All three return a Sanctum bearer token, which the mobile client uses identically.

**Q: Can the Mobile Developer start integrating tomorrow?**

A: Yes. Use `/api/login` for users who have email/password credentials, or the Google OAuth flow for users who sign in with Google. Every endpoint needed to build the automation UI is present, authenticated, and owner-scoped. The Catalog now uses the same canonical identifiers as the Workflow API, so no client-side mapping layer is required. See §5.7 for the one config-handling pitfall (`label_id`) that must be handled correctly on the mobile side.
