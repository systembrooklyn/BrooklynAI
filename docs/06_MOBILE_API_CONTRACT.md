# 06 ŌĆö Mobile API Contract

Status: read-only contract derived from the currently implemented API.

Version: 1.1.3
Last updated: after adding the `google.gmail.send_attachment_links_email` action and the trigger–action compatibility rule.

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

### 1.1 Production authentication ŌĆö Google OAuth (legacy path)

Currently, the only production login in the codebase is the Google OAuth flow implemented in `App\Http\Controllers\Api\GoogleAuthController`.

Flow:

1. Mobile opens `GET /api/auth/google/redirect` in a browser / web view.
    - No auth required.
    - Returns HTTP 302 to Google's OAuth consent screen.
2. The user consents.
3. Google redirects to `GET /api/auth/google/callback?code=...`.
    - No auth required.
    - Backend exchanges the code with Google, **resolves the user by `email` ŌĆö the callback only updates an existing `users` row and does NOT create new users**, issues a Sanctum token via `createToken(...)`, and redirects the browser to:
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

Unauthenticated ŌĆÆ HTTP 401.

The `message` value localizes when the request carries `Accept-Language: ar` ŌĆö see ┬º12.

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

The `message` value localizes when the request carries `Accept-Language: ar` ŌĆö see ┬º12.

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

The `message` value localizes when the request carries `Accept-Language: ar` ŌĆö see ┬º12.

### 1.5 Test / dev-only login ŌĆö NOT for production

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

**Production safety warning.** This endpoint must be disabled in production.
Its current route registration is not environment-gated. Until it is gated
(planned for Phase 9 Security Hardening), the deployment must ensure the route
is unreachable in production by any means available (server-level blocking,
reverse-proxy deny rule, or network restriction). Leaving it reachable in
production is a full authentication bypass for any user whose email is known
to the caller.

Ownership note: anyone who knows the master password and the target email can obtain a token for that user. It exists only for local development. Do not build the mobile production authentication flow on it.

### 1.6 How the mobile client obtains a token

Three paths exist today:

**Production path A ŌĆö Google OAuth** (see ┬º1.1):

1. Mobile opens `/api/auth/google/redirect` in a browser / web view.
2. The user authenticates with Google.
3. The backend redirects to the configured frontend URL with `?token=<sanctum-token>`.
4. Mobile extracts the token from the query parameter.

**Production path B ŌĆö Email/password login** (see ┬º1.7):

1. Mobile calls `POST /api/login` with `{ email, password }`.
2. The backend returns `{ message, data: { token, user } }`.
3. Mobile stores `data.token` and uses it the same way as a Google OAuth token.

**Development / integration-testing path ŌĆö `/api/test/login`** (see ┬º1.5):

1. Mobile calls `POST /api/test/login` with an existing user's email and the shared master password.
2. The backend returns `token` at the top level of the JSON body.
3. Mobile stores the token and uses it the same way as a production token.

All three paths result in the same kind of Sanctum bearer token, and the mobile client uses it identically afterward:

```
Authorization: Bearer <token>
```

**What does NOT exist today:**

- Email verification flow.
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

Public ŌĆö no auth required.

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
- `data.user` follows the same field shape used by `GET /api/user` (┬º1.2).
- The `message` value localizes when the request carries `Accept-Language: ar` ŌĆö see ┬º12.

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

- `email` ŌĆö required, string, valid email format.
- `password` ŌĆö required, string.

**Notes:**

- Login does not require `has_bot_access` to be true. Authentication and product access are separate concerns.
- Login does not modify `has_bot_access` or `access_expiry`.
- Login does not create users. Users must be provisioned first (via the existing business flow that calls `POST /api/register`, or via the Google flow for existing users).
- Login does not touch Google fields.
- Login does not normalize email casing.
- **Login rate limiting is NOT yet active.** A rate-limited `LoginController` (5 attempts per email+IP per minute; returns HTTP 429 with a `Retry-After` header on exceed; counter cleared on success) was proposed during the pre-production review. It is not applied. Until it is, mobile should not assume throttling behavior ŌĆö the endpoint accepts unlimited attempts today. Planned for Phase 9 Security Hardening.

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
- Response `message` localizes when the request carries `Accept-Language: ar` ŌĆö see ┬º12.

### 1.9 Password reset (forgot password)

Two public endpoints support password reset via email OTP. See ┬º13 for the full contract.

```
POST /api/password/forgot    ŌĆö request a reset code
POST /api/password/reset     ŌĆö verify the code and set a new password
```

Both are public. Both return generic responses to prevent email enumeration. See ┬º13.

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

- `capability` must be a value recognized by the backend's capability registry. Currently `gmail` and `drive` are supported.
- Unknown capability ŌĆÆ HTTP 422 with a validation error on `capability`.
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

For `capability=gmail`, the backend requests the five Gmail scopes declared by `CapabilityScopeMap`:

- `openid`
- `email`
- `profile`
- `https://www.googleapis.com/auth/gmail.readonly`
- `https://www.googleapis.com/auth/gmail.send`
- `https://www.googleapis.com/auth/gmail.compose`
- `https://www.googleapis.com/auth/gmail.modify`
- `https://www.googleapis.com/auth/gmail.labels`

For `capability=drive`, the backend requests:

- `openid`
- `email`
- `profile`
- `https://www.googleapis.com/auth/drive.file`

When the same Google account authorizes an additional capability later, the existing connection row is updated in place. The `scopes` array accumulates the union of all previously granted scopes and the newly granted ones. No second connection row is created. This is verified against real Google OAuth (Batch 1).

**Reconnect required for the attachment trigger.** The new Gmail attachment trigger (`new_email_received_with_attachment`) requires the Drive scope on the same connection. Users who connected Google before this feature must reconnect with `capability: "drive"` so the connection accumulates `https://www.googleapis.com/auth/drive.file`.

Connections that continue to use only `send_email`, `reply_to_email`, and `new_email_received` do not need to reconnect.

The authoritative per-action/per-trigger scope is declared on `ActionDefinition::requiredScopes` / `TriggerDefinition::requiredScopes` and enforced server-side at activation time. Mobile does not send scopes.

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
        },
        "from": {
            "label": "From",
            "type": "string",
            "required": false,
            "description": "Only trigger for emails whose sender matches this filter. Uses Gmail search syntax (for example user@example.com)."
        },
        "subject": {
            "label": "Subject",
            "type": "string",
            "required": false,
            "description": "Only trigger for emails whose subject matches this filter. Multi-word values are matched as a phrase."
        },
        "has_attachment": {
            "label": "Has Attachment",
            "type": "boolean",
            "required": false,
            "description": "Only trigger for emails that carry an attachment."
        },
        "query": {
            "label": "Advanced Query",
            "type": "string",
            "required": false,
            "description": "Optional raw Gmail search query, for example \"is:unread larger:5M\". Advanced users only."
        }
    }
}
```

**Canonical key rule.** `trigger_key` is used verbatim as the `trigger_key` field in `PUT /api/workflows/{id}/trigger`.

**`config` map.** Each key inside `config` is the exact field key Mobile must send inside the `config` object of the Workflow API request. No translation, no renaming.

**`capability` is nullable.** The example above shows a Gmail trigger, which declares `capability: "gmail"`. Integrations that do not declare a capability (e.g. Calendar, Sheets, Docs, Analytics) will return `"capability": null`. Do not type `capability` as non-nullable on the mobile side.

**`required_scopes` is not exposed.** Scope validation happens server-side at activation time. Mobile does not send scopes.

**Gmail trigger filters.** `new_email_received` supports the following optional
filters. Only `label_id` is resolved dynamically via the `gmail.labels`
operation. `from`, `subject`, `has_attachment`, and `query` are plain inputs:

- `label_id` ŌĆö optional Gmail label ID. Sent to Gmail as a separate
  `labelIds` parameter, not as part of the search query.
- `from` ŌĆö optional sender filter. Composed by the backend into Gmail's
  native `q` syntax as `from:<value>`.
- `subject` ŌĆö optional subject filter. Composed as `subject:<value>`; values
  containing whitespace are automatically double-quoted so the phrase is
  matched as a whole.
- `has_attachment` ŌĆö optional boolean. When `true`, the backend appends
  `has:attachment` to the search query.
- `query` ŌĆö optional raw Gmail search query appended last. Advanced users
  only; the mobile UI should not surface this without a clear warning.

These filters only affect which messages the trigger considers. They do not
change the trigger payload shape or the execution model.

**Catalog labels localize.** Every `label` and `description` value inside the
catalog response is resolved server-side based on the request `Accept-Language`
header ŌĆö see ┬º12. The identifiers and the JSON shape do not change.

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

**Important ŌĆö the catalog does not currently expose action output schemas.**

The catalog describes **inputs only**. It does not declare, per action, which keys the action returns. This matters for multi-step workflows where a later step references an earlier step's output (see ┬º5.17). The verified output keys for all Gmail actions are documented in ┬º5.9 of this contract. For Calendar / Sheets / Docs / Analytics actions, output schemas are **not yet formally documented** and must not be assumed ŌĆö those actions currently have no runtime handlers and cannot be executed as workflow steps at all.

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

- `google.gmail` ŌĆö 11 actions, 2 triggers.
    - Actions: `send_email`, `reply_to_email`, `create_draft`, `mark_as_read`,
      `mark_as_unread`, `archive`, `trash`, `add_label`, `remove_label`,
      `create_label`, `send_attachment_links_email`.
    - Triggers: `new_email_received`, `new_email_received_with_attachment`.
- `google.calendar` ŌĆö 5 actions.
- `google.sheets` ŌĆö 9 actions.
- `google.docs` ŌĆö 7 actions.
- `google.analytics` ŌĆö 5 actions.
- `google.drive` ŌĆö no actions, no triggers.

### 4.7 Currently executable at runtime vs. catalog-only

The backend currently has runtime handlers for:

- **Triggers:**
    - `google.gmail.new_email_received` ŌĆö driven by `GmailPollStrategy`.
    - `google.gmail.new_email_received_with_attachment` ŌĆö driven by the same `GmailPollStrategy`, with an attachment-aware code path.
- **All eleven Gmail actions**, each with a dedicated handler in the Execution module:
    - `google.gmail.send_email`
    - `google.gmail.reply_to_email`
    - `google.gmail.create_draft`
    - `google.gmail.mark_as_read`
    - `google.gmail.mark_as_unread`
    - `google.gmail.archive`
    - `google.gmail.trash`
    - `google.gmail.add_label`
    - `google.gmail.remove_label`
    - `google.gmail.create_label`
    - `google.gmail.send_attachment_links_email`

Every other catalog entry (Calendar, Sheets, Docs, Analytics) is
**catalog-defined but not currently executable as a workflow trigger/step** at
runtime. The workflow API will still accept them (validation only checks
catalog membership), but execution will fail because no handler exists for
them.

Field metadata is authoritative for every Gmail action and trigger that
currently has a runtime handler. For Calendar / Sheets / Docs / Analytics
actions, `config` is an empty object `{}` and no field metadata is published.

**Gmail attachment trigger ŌĆö scope requirement.** The
`new_email_received_with_attachment` trigger requires the Google connection
to hold `https://www.googleapis.com/auth/drive.file` in addition to the
Gmail scopes. Workflow activation fails with `409 missing_scopes` and the
missing scope URI in `context.missing_scopes` if the Drive scope is absent.
At runtime, if the Drive scope is missing, the polling strategy returns
`drive_connection_required` and does not advance the cursor ŌĆö the next
tick retries once the scope is restored. See ┬º5.20 for the full contract.

**Trigger–action compatibility.** An action may be restricted to a specific
trigger on the server side. The restriction is declared on the action, not
on the trigger. When a workflow pairs a restricted action with a trigger
that is not in the action's allowed list, the workflow API rejects the
operation with HTTP 409:

```json
{
  "message": "<localized>",
  "error": "incompatible_trigger",
  "context": {
    "location": "step",
    "action_key": "google.gmail.send_attachment_links_email",
    "trigger_key": "google.gmail.new_email_received",
    "allowed_trigger_keys": ["google.gmail.new_email_received_with_attachment"]
  }
}
```

The rejection is enforced at every workflow mutation boundary (step add,
step update, trigger upsert) and at activation. Mobile should surface the
`message` and use the `context` to guide the user. The list of allowed
trigger keys is not exposed in the catalog response.

The only restricted action in the current catalog is
`google.gmail.send_attachment_links_email` (see ┬º5.21). All other actions
are unrestricted and remain usable with any trigger.

**Reconnect requirement reminder.** The newer Gmail actions and the
attachment trigger require scopes that were not requested on older
Google connections. See ┬º3.5. Mobile should proactively detect this by
re-fetching `GET /api/connections` after the Gmail integration was
updated, and prompt the user to reconnect their Google account if the
connection's `scopes` array is missing the required scope for the action
or trigger the user wants to configure.

### 4.8 Dynamic configuration options

When a field declares `options_source`, Mobile resolves it as follows:

1. Read `options_source.operation_id`.
2. Look up the operation in the published Swagger spec (e.g. `gmail.labels` ŌĆÆ `GET /api/gmail/labels`).
3. Substitute any `params` placeholders. The placeholder `{{connection_id}}` is replaced with the currently selected connection ID.
4. Call the endpoint using the standard authentication (`Authorization: Bearer <token>`).
5. Use the returned array as the option list. The mapping is:
    - `value` ŌĆÉ the item's `id` field
    - `label` ŌĆÉ the item's `name` field

No hardcoded endpoint per integration. No mapping table. The `operation_id` is the canonical Swagger identifier already published in the API contract.

---

## 5. Workflow creation flow (mobile)

### 5.0 End-to-end summary

```text
Login (Google OAuth redirect, email/password login, or /api/test/login for dev)
ŌåÆ Sanctum token
ŌåÆ GET /api/connections                (list user-owned connections)
ŌåÆ GET /api/catalog                    (discover integrations, triggers, actions, config)
ŌåÆ POST /api/workflows                 (create draft)
ŌåÆ PUT  /api/workflows/{id}/trigger    (attach trigger)
ŌåÆ POST /api/workflows/{id}/steps      (append step)
ŌåÆ POST /api/workflows/{id}/activate   (validate capability and activate)
ŌåÆ POST /api/workflows/{id}/execute    (manual run; optional)
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

- `integration_key` ŌĆö required. Must exist in catalog.
- `trigger_key` ŌĆö required. Must be declared by that integration. For `google.gmail`, the supported values are `new_email_received` and `new_email_received_with_attachment`.
- `connection_id` ŌĆö optional integer. If provided, must be a connection owned by the authenticated user.
- `interval_minutes` ŌĆö optional integer 1..1440.
- `config` ŌĆö optional object. Keys must match the trigger's catalog `config` fields. Template syntax is validated on save; runtime errors are separate.

**`integration_key`, `trigger_key`, and each key inside `config` are used verbatim from the Catalog response.** No translation.

**`interval_minutes` semantics:** `interval_minutes` is relevant only for `schedule`-strategy triggers (consumed by `workflows:run-scheduled`). It does **not** configure the Gmail polling frequency for `new_email_received` or `new_email_received_with_attachment`. For a Gmail poll trigger, `interval_minutes` is stored but not used by the polling runtime ŌĆö polling runs on its own schedule. Under the production scheduler architecture, Gmail polling is driven by the external 5-minute Apps Script clock via `POST /api/internal/scheduler/tick`, not by the queue scheduler.

**Gmail trigger filters (current).** Both Gmail triggers accept the following optional `config` keys. All are optional; omit any that do not apply. `has_attachment` is **not** a configurable field on `new_email_received_with_attachment` ŌĆö it is implicit.

- `label_id` (string, optional) ŌĆö Gmail label ID. Passed to Gmail as a separate `labelIds` parameter, not part of the search `q` string.
- `from` (string, optional) ŌĆö sender filter. Composed into the Gmail `q` as `from:<value>`.
- `subject` (string, optional) ŌĆö subject filter. Composed as `subject:<value>`. Values containing spaces are automatically quoted so the phrase is matched as a whole.
- `has_attachment` (boolean, optional; only on `new_email_received`) ŌĆö when `true`, appends `has:attachment` to the `q` string.
- `query` (string, optional) ŌĆö raw Gmail search syntax, appended last. Advanced only.

For `new_email_received_with_attachment`, the strategy **always** appends `has:attachment` to the composed query. If the user's `query` field already contains `has:attachment`, the strategy does not duplicate it.

**`label_id` handling (IMPORTANT):**

- If the user has selected a label ŌĆÆ send `config.label_id` as a non-empty string, e.g. `{ "config": { "label_id": "INBOX" } }`.
- If the user has NOT selected a label (i.e. "all mail") ŌĆÆ **omit `label_id` from `config` entirely**.
- **Do NOT send `"label_id": null`.**
- **Do NOT send `"label_id": ""`.**

Reason: `GmailPollStrategy::extractLabelIds` distinguishes three cases:

- Key absent ŌĆÆ no filter, polling proceeds.
- Key present with a non-empty string ŌĆÆ filter applied.
- Key present with `null`, empty string, or non-string ŌĆÆ the polling runtime treats it as malformed and **skips the workflow on every poll** (cursor unchanged, warning logged). It does not behave as "no filter".

The same "omit when empty" rule applies to the new string filters: omitting
`from` / `subject` / `query` means "no filter". Sending an empty string is
treated as "no filter" by `GmailTriggerQueryComposer`, but the recommended
pattern for Mobile is to omit the key entirely when the user has not supplied
a value.

Response (HTTP 200): the persisted trigger.

Errors:

- Unknown integration ŌĆÆ 422 with `errors.integration_key`.
- Unknown trigger ŌĆÆ 422 with `errors.trigger_key`.
- Non-owned connection ŌĆÆ 404 `{ "message": "Connection not found" }`.
- Invalid template syntax ŌĆÆ 422 `{ "message": "Validation failed", "errors": { "config": [...] } }`.
- Trigger/action incompatibility with an existing step ŌĆÆ 409 with `error: "incompatible_trigger"` and `context`. See ┬º4.7.

There is no separate create/replace endpoint for triggers ŌĆö `PUT` is upsert.

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

- `integration_key` / `action_key` ŌĆö required, must exist in catalog.
- `connection_id` ŌĆö optional integer, must be user-owned if provided.
- `config` ŌĆö optional object. Keys must match the action's catalog `config` fields.

Response (HTTP 201): the persisted step. Position is assigned automatically as `max(existing position) + 1`.

Errors:

- Same pattern as trigger upsert.
- Trigger/action incompatibility with the workflow's current trigger ŌĆÆ 409 with `error: "incompatible_trigger"` and `context`. See ┬º4.7.

**Gmail action config keys.** The Gmail actions currently expose the following
`config` keys (see ┬º4.6 for the catalog list). Mobile must populate them
verbatim from the catalog response, using `{{ trigger.* }}` or
`{{ steps.N.output.* }}` templates where the value comes from a trigger payload
or a previous step's output:

- `send_email` ŌĆö `to` (email), `subject` (string), `body` (text).
- `reply_to_email` ŌĆö `to` (email), `subject` (string), `body` (text),
  `thread_id` (string). Typically `to = {{ trigger.from }}`,
  `subject = "Re: {{ trigger.subject }}"`, `thread_id = {{ trigger.thread_id }}`.
- `create_draft` ŌĆö `to` (email), `subject` (string), `body` (text).
- `mark_as_read` ŌĆö `message_id` (string). Typically `{{ trigger.message_id }}`.
- `mark_as_unread` ŌĆö `message_id` (string).
- `archive` ŌĆö `message_id` (string).
- `trash` ŌĆö `message_id` (string).
- `add_label` ŌĆö `message_id` (string), `label_id` (select, dynamic via
  `gmail.labels`).
- `remove_label` ŌĆö `message_id` (string), `label_id` (select, dynamic via
  `gmail.labels`).
- `create_label` ŌĆö `name` (string).
- `send_attachment_links_email` ŌĆö `to` (email), `subject` (string),
  `intro_text` (text, optional), `attachments` (string, required, with
  `default: "{{ trigger.attachments }}"`). Typically `to = {{ trigger.from }}`.
  Mobile should submit the field's declared `default` verbatim; the server
  resolves it to the raw attachments array at execution time. This action is
  restricted to `new_email_received_with_attachment` and is rejected with
  HTTP 409 `error: "incompatible_trigger"` if paired with
  `new_email_received`. See ┬º5.21.

**Gmail action output keys (VERIFIED against a real Gmail account).**

Each Gmail action returns a **safe, whitelisted output** ŌĆö never the raw
provider response. The keys below are the ones the backend actually returns
today. They are the only keys that may be referenced from a later step via
`{{ steps.N.output.<key> }}`.

| Action           | Output keys                                                      | Example                                                                                      |
| ---------------- | ---------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| `send_email`     | `sent` (boolean), `message_id` (string)                          | `{ "sent": true, "message_id": "1a101cf1b7ca9c08" }`                                         |
| `reply_to_email` | `sent` (boolean), `message_id` (string)                          | `{ "sent": true, "message_id": "1a1021a26fe357a3" }`                                         |
| `create_draft`   | `created` (boolean), `draft_id` (string), `message_id` (string)  | `{ "created": true, "draft_id": "r-7036261660220453950", "message_id": "1a101d92dd8bdabb" }` |
| `mark_as_read`   | `modified` (boolean), `message_id` (string)                      | `{ "modified": true, "message_id": "1a101e9eaca9df54" }`                                     |
| `mark_as_unread` | `modified` (boolean), `message_id` (string)                      | `{ "modified": true, "message_id": "1a101e9eaca9df54" }`                                     |
| `archive`        | `modified` (boolean), `message_id` (string)                      | `{ "modified": true, "message_id": "1a101e9eaca9df54" }`                                     |
| `trash`          | `modified` (boolean), `message_id` (string)                      | `{ "modified": true, "message_id": "1a101e9eaca9df54" }`                                     |
| `add_label`      | `modified` (boolean), `message_id` (string), `label_id` (string) | `{ "modified": true, "message_id": "1a101e9eaca9df54", "label_id": "Label_1" }`              |
| `remove_label`   | `modified` (boolean), `message_id` (string), `label_id` (string) | `{ "modified": true, "message_id": "1a101e9eaca9df54", "label_id": "Label_1" }`              |
| `create_label`   | `created` (boolean), `label_id` (string), `name` (string)        | `{ "created": true, "label_id": "Label_1", "name": "E2E-MultiLabel" }`                       |
| `send_attachment_links_email` | `sent` (boolean), `message_id` (string)           | `{ "sent": true, "message_id": "1a101e9eaca9df54" }`                                         |

**These output keys are the ONLY stable contract for inter-step references.**
Do not assume additional keys exist. Any key not listed above is not returned
by the backend and any reference to it will fail at runtime.

**Output schemas for Calendar / Sheets / Docs / Analytics are NOT documented.**
Those actions currently have no runtime handlers and cannot be executed as
workflow steps at all. Do not attempt to reference their outputs. Do not
invent output keys for them.

### 5.10 Update step

```
PUT /api/workflows/{id}/steps/{position}
Body: same shape as POST /api/workflows/{id}/steps
```

Replaces the step in place. Positions are not reindexed.

Errors: same pattern as step creation, including trigger/action incompatibility.

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
3. Draft ŌĆÆ Active transition allowed.
4. Trigger's integration + trigger key exist in catalog.
5. Trigger's `capability` / `requiredScopes` are satisfied by the referenced connection.
6. Every step's integration + action key exist in catalog.
7. Every step's `capability` / `requiredScopes` are satisfied by its referenced connection.
8. Every step's action is compatible with the workflow's trigger (see ┬º4.7).

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
- `incompatible_trigger`

`missing_scopes` context includes `missing_scopes: [<scope-uri>, ...]`.

`incompatible_trigger` context includes `location`, `action_key`, `trigger_key`, and `allowed_trigger_keys`.

**Gmail scope errors.** If a workflow uses one of the Gmail actions or triggers
that requires a scope the connection does not have ŌĆö for example, using
`create_draft` on a connection that was authorized before `gmail.compose` was
added, or using `new_email_received_with_attachment` on a connection that
lacks `drive.file` ŌĆö activation returns `409` with `error: "missing_scopes"`
and the missing scope URI in `context.missing_scopes`. Mobile should surface
this as "please reconnect your Google account with the required capability"
and re-run the connection flow with the appropriate `capability` value
(`gmail` or `drive`).

For a simpler trigger failure (no trigger configured) the response is HTTP 409 with `{ "message": "Workflow cannot be activated without a trigger" }`.

### 5.13 Pause workflow

```
POST /api/workflows/{id}/pause
```

Active ŌĆÆ Paused. Invalid transition ŌĆÆ HTTP 409 `{ "message": "Invalid workflow state transition" }`.

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

- Draft and Active workflows can be executed. Paused ŌĆÆ HTTP 409.
- `idempotency_key` ŌĆö optional, max 191 chars. Keys starting with `schedule:` or `gmail:` are rejected with HTTP 422 (reserved namespaces).
- If a running/pending execution already exists for the workflow ŌĆÆ HTTP 409.

Response: HTTP 201 for a new execution, HTTP 200 for an idempotent replay. Body contains the execution plus its steps.

### 5.15 List executions for a workflow

```
GET /api/workflows/{id}/executions?limit=50
```

`limit` ŌĆö optional 1..50.

### 5.16 Show execution

```
GET /api/executions/{id}
```

Only the owning user can read an execution.

### 5.17 Multi-step template resolution

Workflow step `config` values may contain **templates** that are resolved at
execution time against data available in the execution context. Two template
roots are supported:

**Root 1 ŌĆö `{{ trigger.* }}`**

References a key from the trigger payload of the current execution. The
trigger payload shape is defined by the trigger.

For `google.gmail.new_email_received`, the trigger payload exposes the
canonical 14-key whitelist documented in the Gmail integration:

```
message_id
thread_id
from
to
cc
subject
snippet
body
received_at
received_at_epoch_ms
labels
has_attachment
attachment_count
attachment_ids
```

For `google.gmail.new_email_received_with_attachment`, the payload contains
the same 14 keys **plus** a 15th key `attachments`. See ┬º5.20 for the shape
of `attachments`.

Example:

```
{{ trigger.message_id }}
{{ trigger.from }}
{{ trigger.thread_id }}
{{ trigger.has_attachment }}
{{ trigger.attachments }}
{{ trigger.attachments.0.drive_file_id }}
{{ trigger.attachments.0.web_view_link }}
{{ trigger.attachments.0.filename }}
```

Note that `{{ trigger.attachments }}` resolves to the whole array when it is
the entire value of a `config` field. This is used by the
`send_attachment_links_email` action (see ┬º5.21).

**Root 2 ŌĆö `{{ steps.N.output.* }}`**

References an output key from a **previous step's** execution result.

- `N` is the **1-based position** of a previous step in the same workflow.
- `N` must refer to a step that runs **before** the step currently being
  resolved. Referencing the current step or a future step is invalid.
- `output` is a fixed literal segment (it is the word "output").
- `<key>` must be one of the output keys actually returned by that previous
  step's action. See ┬º5.9 for the verified output keys per Gmail action.

Example:

```
{{ steps.1.output.label_id }}
{{ steps.2.output.message_id }}
{{ steps.3.output.draft_id }}
```

**Rules**

1. **Position is 1-based.** The first step in a workflow is position `1`, not
   `0`.
2. **Position refers to workflow position, not step ID.** If a step is
   deleted and positions have gaps (for example `1`, `3`, `4`), references use
   those numeric positions, not renumbered indices. See ┬º5.11 ŌĆö positions are
   not reindexed on delete.
3. **The `<key>` segment must exist in the referenced step's output.** The
   authoritative list of keys per action is in ┬º5.9. Referencing a key the
   action does not return fails the step at runtime.
4. **The referenced step must run before the current step.** Forward
   references or self-references are invalid.
5. **Whole-template resolution preserves the resolved value's type.** When a
   `config` value is a whole-string template like
   `"{{ steps.1.output.label_id }}"` and the referenced value is a string, the
   resolved `config` value is that string ŌĆö not the string wrapped in quotes.
   When the referenced value is a boolean, the resolved value is a boolean.
   When the referenced value is an array, the resolved value is the raw PHP
   array and is not stringified. Mobile does not need to coerce types.
6. **Embedded templates resolve to strings.** If a template is mixed with
   other text, such as `"Re: {{ trigger.subject }}"`, the resolved value is a
   single string. If the referenced value is an array or object and the
   template is embedded rather than whole-string, resolution fails.
7. **Invalid, nonexistent, or future step references fail at runtime.** They
   are not silently ignored. The step will be marked `failed` and the
   execution status will become `failed`. The failing step records a
   diagnostic `error_message`. Two common shapes:
    - `Template path "trigger.<key>" cannot be resolved.` ŌĆö the key is
      missing from the trigger payload.
    - `Template path "steps.<N>.output.<key>" cannot be resolved.` ŌĆö the step
      reference is missing, future, or the key is not part of the referenced
      step's output.

**Template syntax is validated on save.** `POST /api/workflows/{id}/steps` and
`PUT /api/workflows/{id}/steps/{position}` return `422` with an error on
`config` if a template is syntactically malformed (for example
`{{ trigger. }}`). Semantic validation (whether the key exists) happens at
execution time, not at save time.

**A worked example of the multi-step pattern is in ┬º5.18.**

### 5.18 Worked example ŌĆö multi-step workflow with inter-step references

This example is the exact workflow verified end-to-end during the Gmail E2E
Verification Pass. It is a real 7-step workflow that was run against a real
Gmail account.

**Purpose:** create a label, apply it to an incoming email, flip the read
state twice, remove the label, then archive and trash the message. Steps 2 and
5 reference the label ID produced by step 1.

**Step order (position ŌĆÆ action):**

| Position | Action           | Notes                                         |
| -------- | ---------------- | --------------------------------------------- |
| 1        | `create_label`   | Creates the label and returns its `label_id`. |
| 2        | `add_label`      | Uses `{{ steps.1.output.label_id }}`.         |
| 3        | `mark_as_unread` | Uses `{{ trigger.message_id }}`.              |
| 4        | `mark_as_read`   | Uses `{{ trigger.message_id }}`.              |
| 5        | `remove_label`   | Uses `{{ steps.1.output.label_id }}`.         |
| 6        | `archive`        | Uses `{{ trigger.message_id }}`.              |
| 7        | `trash`          | Uses `{{ trigger.message_id }}`.              |

**Step 1 ŌĆö create the label**

```
POST /api/workflows/{id}/steps
Content-Type: application/json

{
  "integration_key": "google.gmail",
  "action_key": "create_label",
  "connection_id": 1,
  "config": {
    "name": "E2E-MultiLabel"
  }
}
```

Runtime output:

```json
{
    "created": true,
    "label_id": "Label_1",
    "name": "E2E-MultiLabel"
}
```

The key we will reference later is `label_id`.

**Step 2 ŌĆö apply the label, referencing step 1's output**

```
POST /api/workflows/{id}/steps
Content-Type: application/json

{
  "integration_key": "google.gmail",
  "action_key": "add_label",
  "connection_id": 1,
  "config": {
    "message_id": "{{ trigger.message_id }}",
    "label_id": "{{ steps.1.output.label_id }}"
  }
}
```

At runtime, `{{ steps.1.output.label_id }}` resolves to the string
`"Label_1"`. The step's persisted output is:

```json
{
    "modified": true,
    "message_id": "1a101e9eaca9df54",
    "label_id": "Label_1"
}
```

**Step 3 ŌĆö mark the message as unread**

```
{
  "integration_key": "google.gmail",
  "action_key": "mark_as_unread",
  "connection_id": 1,
  "config": {
    "message_id": "{{ trigger.message_id }}"
  }
}
```

**Step 4 ŌĆö mark the message as read**

```
{
  "integration_key": "google.gmail",
  "action_key": "mark_as_read",
  "connection_id": 1,
  "config": {
    "message_id": "{{ trigger.message_id }}"
  }
}
```

Steps 3 and 4 exercise the read-state flip on the same message. Order matters:
step 3 runs before step 4, so the final state is "read".

**Step 5 ŌĆö remove the label, referencing step 1's output again**

```
{
  "integration_key": "google.gmail",
  "action_key": "remove_label",
  "connection_id": 1,
  "config": {
    "message_id": "{{ trigger.message_id }}",
    "label_id": "{{ steps.1.output.label_id }}"
  }
}
```

Same `{{ steps.1.output.label_id }}` reference. The value is stable across
the execution ŌĆö it is the same `label_id` that step 1 returned.

**Step 6 ŌĆö archive the message**

```
{
  "integration_key": "google.gmail",
  "action_key": "archive",
  "connection_id": 1,
  "config": {
    "message_id": "{{ trigger.message_id }}"
  }
}
```

**Step 7 ŌĆö move the message to trash**

```
{
  "integration_key": "google.gmail",
  "action_key": "trash",
  "connection_id": 1,
  "config": {
    "message_id": "{{ trigger.message_id }}"
  }
}
```

**Full workflow after all 7 steps are added** (response from
`GET /api/workflows/{id}`):

```json
{
    "id": 5,
    "name": "E2E Multi-Action Workflow",
    "status": "draft",
    "trigger": {
        "id": 6,
        "integration_key": "google.gmail",
        "trigger_key": "new_email_received",
        "connection_id": 1,
        "strategy": "poll",
        "config": {
            "subject": "E2E-Multi",
            "label_id": "INBOX"
        },
        "interval_minutes": null
    },
    "steps": [
        {
            "id": 7,
            "position": 1,
            "integration_key": "google.gmail",
            "action_key": "create_label",
            "connection_id": 1,
            "config": { "name": "E2E-MultiLabel" }
        },
        {
            "id": 8,
            "position": 2,
            "integration_key": "google.gmail",
            "action_key": "add_label",
            "connection_id": 1,
            "config": {
                "label_id": "{{ steps.1.output.label_id }}",
                "message_id": "{{ trigger.message_id }}"
            }
        },
        {
            "id": 9,
            "position": 3,
            "integration_key": "google.gmail",
            "action_key": "mark_as_unread",
            "connection_id": 1,
            "config": { "message_id": "{{ trigger.message_id }}" }
        },
        {
            "id": 10,
            "position": 4,
            "integration_key": "google.gmail",
            "action_key": "mark_as_read",
            "connection_id": 1,
            "config": { "message_id": "{{ trigger.message_id }}" }
        },
        {
            "id": 11,
            "position": 5,
            "integration_key": "google.gmail",
            "action_key": "remove_label",
            "connection_id": 1,
            "config": {
                "label_id": "{{ steps.1.output.label_id }}",
                "message_id": "{{ trigger.message_id }}"
            }
        },
        {
            "id": 12,
            "position": 6,
            "integration_key": "google.gmail",
            "action_key": "archive",
            "connection_id": 1,
            "config": { "message_id": "{{ trigger.message_id }}" }
        },
        {
            "id": 13,
            "position": 7,
            "integration_key": "google.gmail",
            "action_key": "trash",
            "connection_id": 1,
            "config": { "message_id": "{{ trigger.message_id }}" }
        }
    ]
}
```

**Execution result** (all 7 steps, from `GET /api/executions/{id}`):

```json
{
    "status": "completed",
    "trigger_source": "poll",
    "steps": [
        {
            "position": 1,
            "action_key": "create_label",
            "status": "completed",
            "output": {
                "created": true,
                "label_id": "Label_1",
                "name": "E2E-MultiLabel"
            }
        },
        {
            "position": 2,
            "action_key": "add_label",
            "status": "completed",
            "output": {
                "modified": true,
                "message_id": "1a101e9eaca9df54",
                "label_id": "Label_1"
            }
        },
        {
            "position": 3,
            "action_key": "mark_as_unread",
            "status": "completed",
            "output": {
                "modified": true,
                "message_id": "1a101e9eaca9df54"
            }
        },
        {
            "position": 4,
            "action_key": "mark_as_read",
            "status": "completed",
            "output": {
                "modified": true,
                "message_id": "1a101e9eaca9df54"
            }
        },
        {
            "position": 5,
            "action_key": "remove_label",
            "status": "completed",
            "output": {
                "modified": true,
                "message_id": "1a101e9eaca9df54",
                "label_id": "Label_1"
            }
        },
        {
            "position": 6,
            "action_key": "archive",
            "status": "completed",
            "output": {
                "modified": true,
                "message_id": "1a101e9eaca9df54"
            }
        },
        {
            "position": 7,
            "action_key": "trash",
            "status": "completed",
            "output": {
                "modified": true,
                "message_id": "1a101e9eaca9df54"
            }
        }
    ]
}
```

**What this example demonstrates for mobile:**

- A workflow can have many steps.
- Later steps can reference earlier steps' outputs by position and key.
- The referenced output key (`label_id`) is stable and its value is
  identical across steps 2 and 5.
- The resolved value type is preserved ŌĆö step 1's `label_id` is a string, and
  step 2's resolved `label_id` is also a string.
- Mobile does not need any special coordination for these references. It
  constructs the `config` object with the literal template string
  `"{{ steps.1.output.label_id }}"`, submits it via the normal step
  endpoint, and the backend handles resolution at execution time.

**How mobile should build this in the UI:**

1. When the user adds step 1 (`create_label`), mobile can look up the action's
   output keys in the table in ┬º5.9. The user does not configure any output ŌĆö
   outputs are produced by the backend at runtime.
2. When the user adds step 2, and selects an action whose `config` has a
   `label_id` field, mobile can offer the user a dropdown of "referenceable
   values" composed from:
    - The trigger's payload keys (from the trigger definition in the catalog).
    - The output keys of every earlier step in the same workflow (from the
      table in ┬º5.9).
3. When the user picks "Label from step 1", mobile writes the literal string
   `"{{ steps.1.output.label_id }}"` into the step's `config.label_id`.

The backend has no per-workflow opt-in or registration for these references ŌĆö
they are simply strings inside `config`, resolved at runtime.

### 5.19 Additional worked example ŌĆö the hero test

The hero test verified end-to-end during the Gmail E2E Verification Pass:

```
Trigger: new_email_received  (filter: subject contains "E2E-Hero", label INBOX)
Step 1:  mark_as_read    with message_id = {{ trigger.message_id }}
Step 2:  reply_to_email  with to         = {{ trigger.from }},
                          subject    = "Re: {{ trigger.subject }}",
                          body       = "<p>Thanks for reaching out...</p>",
                          thread_id  = {{ trigger.thread_id }}
```

Result on real Gmail:

- The incoming message was marked read.
- A reply landed in the **same conversation thread** as the incoming message.
- Both steps returned `completed`.

This example demonstrates the mix of `{{ trigger.* }}` references (four
distinct ones in step 2) and a simple single-action step 1.

### 5.20 Gmail attachment trigger

The `new_email_received_with_attachment` trigger extends the
`new_email_received` trigger to materialize the incoming message's
attachments into the user's Google Drive before the workflow executes.

#### What it does

- Uses the same Gmail polling mechanism as `new_email_received`. There is
  exactly one polling strategy; the trigger key selects the code path inside
  it.
- Always filters the Gmail query by `has:attachment`, so only messages that
  actually carry an attachment reach the workflow. If the user's `query`
  config already contains `has:attachment`, the strategy does not duplicate
  it.
- For every message that matches, downloads each attachment through the
  Gmail API, uploads it to the user's own Google Drive via the `drive.file`
  scope, and adds an `attachments` array to the trigger payload.
- The uploaded file is owned by the user's own Google account. No public
  permissions are set. No service-account ownership is involved.
- Attachment download and upload run synchronously inside the scheduler
  tick, before `RunWorkflowAction::execute()` is invoked.

#### Activation requirement

- The workflow's Google connection must hold both
  `https://www.googleapis.com/auth/gmail.readonly` and
  `https://www.googleapis.com/auth/drive.file`.
- If the Drive scope is missing, `POST /api/workflows/{id}/activate` returns
  HTTP 409:

  ```json
  {
      "message": "<localized>",
      "error": "missing_scopes",
      "context": {
          "location": "trigger",
          "missing_scopes": ["https://www.googleapis.com/auth/drive.file"]
      }
  }
  ```

- To fix this, the user must reconnect Google with `capability: "drive"`.
  See ┬º3.5 for the reconnect semantics.
- Reconnecting Google with the Drive capability **does not** create a second
  connection row. The existing connection's `scopes` array is extended.

#### Runtime behavior when the Drive scope is missing

- If the workflow is active but the connection no longer carries
  `drive.file` (e.g. the user revoked the scope, or the token was
  reissued without it), the polling strategy skips the workflow with
  reason `drive_connection_required` and **does not advance the polling
  cursor**. The next tick will retry once the scope is restored.
- This is a runtime skip, not an execution. No `Execution` row is created
  for the skipped tick.
- Mobile is not notified of the skip via the API. If the user wants to see
  whether a workflow is currently failing due to a missing scope, they
  should inspect the connection's `scopes` array from
  `GET /api/connections`.

#### Trigger payload shape

The payload is identical to `new_email_received`'s 14-key payload, plus a
15th key `attachments`:

```json
{
    "message_id": "...",
    "thread_id": "...",
    "from": "...",
    "to": ["..."],
    "cc": ["..."],
    "subject": "...",
    "snippet": "...",
    "body": "...",
    "received_at": "2026-10-05T12:00:00+00:00",
    "received_at_epoch_ms": 1759500000000,
    "labels": ["INBOX"],
    "has_attachment": true,
    "attachment_count": 1,
    "attachment_ids": ["ANGjdJ..."],
    "attachments": [
        {
            "gmail_attachment_id": "ANGjdJ...",
            "drive_file_id": "1AbC...",
            "web_view_link": "https://drive.google.com/file/d/1AbC.../view",
            "filename": "invoice.pdf",
            "mime_type": "application/pdf",
            "size": 123456
        }
    ]
}
```

**`attachment_ids` vs `attachments[].drive_file_id`:** these are unrelated.
`attachment_ids` is the pre-existing Gmail part ID array ŌĆö internal to the
Gmail API. `attachments[].drive_file_id` is the Drive file identifier
produced by the materialization step. Downstream actions consume
`attachments[].drive_file_id` (or, for read-only display, the additive
`web_view_link`).

**`attachments` is always an array, and it is always non-empty in the payloads this trigger produces.** The enriched fetch action skips any message that does not carry at least one attachment, and the `has:attachment` fragment in the Gmail query prevents non-attachment messages from being fetched in the first place. So this key is present on every payload from `new_email_received_with_attachment` and contains at least one entry.

#### Attachment object fields

| Field                 | Type            | Notes |
| --------------------- | --------------- | ----- |
| `gmail_attachment_id` | string          | Gmail-side part ID. Provider-specific traceability. Not the primary reference for downstream actions. |
| `drive_file_id`       | string \| null  | The Drive file identifier. **This is the canonical downstream reference.** `null` when the attachment failed to materialize. |
| `web_view_link`       | string \| null  | Google Drive web link for the uploaded file. **Additive field.** `null` when the attachment failed to materialize, or when Drive did not return a link. Possessing the link does not grant access; existing Drive permissions still apply. |
| `filename`            | string          | Original filename from Gmail. |
| `mime_type`           | string          | MIME type from Gmail. Defaults to `application/octet-stream` if Gmail did not return one. |
| `size`                | integer \| null | Byte size. On success, this is the actual decoded byte size. On failure, it is the reported size from Gmail when available, or `null`. |
| `error`               | string          | **Present only on failed attachments.** One of `gmail_download_failed` or `drive_upload_failed`. Stable machine-readable code. |

#### Partial-failure policy

Each attachment is processed independently. If one attachment fails to
download or upload, the others still proceed. The failing entry retains
`drive_file_id: null` and `web_view_link: null`, and gains a stable `error`
code:

- `gmail_download_failed` ŌĆö the Gmail API could not return the bytes.
- `drive_upload_failed` ŌĆö the Drive API could not create the file.

The workflow still executes with the partial result set. Downstream actions
can branch on `drive_file_id != null` to handle successes only.

If **all** attachments fail, the workflow still executes with every
attachment entry carrying `drive_file_id: null` and an `error`. The
workflow's own step logic determines whether that is acceptable.

#### Payload size

The materialized `attachments` array contains only metadata (file IDs,
filenames, MIME types, sizes, links). Attachment bytes are never included in
the trigger payload. The Drive file remains the single source of truth for
the binary content. There are no artificial attachment count or size limits ŌĆö
Gmail's own limits remain authoritative.

### 5.21 Gmail send attachment links email action

The `send_attachment_links_email` action sends one HTML email containing a
clickable Google Drive link for each attachment that the
`new_email_received_with_attachment` trigger has already materialized.

#### Restriction

This action is **only** compatible with the
`new_email_received_with_attachment` trigger. Pairing it with
`new_email_received` is rejected server-side at every write path (step add,
step update, trigger upsert) and at activation:

```json
{
  "message": "<localized>",
  "error": "incompatible_trigger",
  "context": {
    "location": "step",
    "action_key": "google.gmail.send_attachment_links_email",
    "trigger_key": "google.gmail.new_email_received",
    "allowed_trigger_keys": ["google.gmail.new_email_received_with_attachment"]
  }
}
```

Mobile should not need to filter the action out of a UI list on its own ŌĆö
the server rejects the pairing with a clear error. Mobile may still hide the
action when the current trigger is `new_email_received` as a UX courtesy,
but this is not required for correctness.

`new_email_received_with_attachment` remains fully usable with every other
action, including `send_email`.

#### Config fields

| Field          | Type    | Required | Notes |
| -------------- | ------- | -------- | ----- |
| `to`           | email   | yes      | Recipient. Typically `{{ trigger.from }}`. |
| `subject`      | string  | yes      | Subject of the outgoing email. |
| `intro_text`   | text    | no       | Optional sentence shown above the list. |
| `attachments`  | string  | yes      | Defaults to `{{ trigger.attachments }}`. Leave the default untouched. |

The `attachments` field is typed `string` in the catalog even though the
resolved value is an array. This is intentional: the mobile client submits
the literal template string `"{{ trigger.attachments }}"`, and the existing
template resolver's whole-string path returns the raw PHP array at execution
time. No new field type is introduced, and the catalog's field-type set is
unchanged.

**Mobile should submit the field's declared `default` verbatim.** Do not
parse, stringify, or alter the value. Do not attempt to pre-compute the
links on the client.

#### Body composition

The backend iterates the resolved attachments and:
- Skips any entry that is not an array.
- Skips any entry whose `web_view_link` is not a string, is empty, or is
  whitespace-only.
- Uses the entry's `filename` as the anchor text; falls back to the literal
  `Attachment` when `filename` is missing or empty.
- HTML-escapes both the URL and the filename.

If no eligible attachments remain, the body is:

```html
<p>Attachments could not be linked.</p>
```

If the user supplied `intro_text`, it is HTML-escaped and rendered as a
leading `<p>...</p>` paragraph.

#### Output keys

`sent` (boolean), `message_id` (string | null). Identical shape to
`send_email`. A later step can reference the output via
`{{ steps.N.output.message_id }}`.

#### What it does not do

- Does not read attachment bytes from Drive. It only sends links.
- Does not attach binary content to the outgoing email.
- Does not create or modify Drive permissions.
- Does not add OAuth scopes. Only `gmail.send` is required, the same scope
  already used by `send_email`.

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
- If `connection_id` is supplied but is not owned by the caller ŌĆÆ 404 `{ "message": "Connection not found" }`.
- Errors from Gmail ŌĆÆ HTTP 500 with a descriptive message.

Why labels are separate from the catalog:

- Label values depend on the user's Gmail account. They cannot be hardcoded in the catalog.
- The catalog declares `label_id` with `type: "select"` and `options_source: { operation_id: "gmail.labels", params: { connection_id: "{{connection_id}}" } }`.
- Mobile resolves `options_source.operation_id` against the published Swagger spec. `gmail.labels` maps to `GET /api/gmail/labels`. Mobile substitutes `{{connection_id}}` with the currently selected connection ID, calls the endpoint, and maps the returned `data[]` items to `{ value: item.id, label: item.name }`.

**How to build the trigger config in mobile:**

1. Read the trigger's `config.label_id` object from the Catalog.
2. See `type: "select"` ŌĆÆ render a dropdown.
3. See `options_source.operation_id: "gmail.labels"` ŌĆÆ resolve this operation through the API contract.
4. Substitute `{{connection_id}}` with the user's chosen Gmail connection ID.
5. Call the endpoint. Render `data[]` as options.
6. When the user picks a label ŌĆÆ put its `id` into `config.label_id`.
7. When the user picks "all mail" ŌĆÆ omit `label_id` from `config` entirely (do NOT send `null` or `""`).

**No Gmail-specific hardcoding in the mobile app.** The mobile app reads `options_source` and resolves it declaratively.

Label mutation (create/update/delete) is **not implemented as an HTTP endpoint**. The `gmail.labels` scope is part of Gmail capability consent. Push / Pub/Sub / `historyId` cursors are **not implemented**.

---

## 7. Error shapes

### 7.1 Unauthenticated (401)

Laravel standard:

```json
{ "message": "Unauthenticated." }
```

Returned by any route under `auth:sanctum` when the token is missing or invalid.

**Exception:** `POST /api/login` returns `{ "message": "Invalid credentials." }` (also HTTP 401) for credential failures ŌĆö see ┬º1.7. That is the login-specific 401 body, not the middleware 401.

The 401 body itself is not localized. Treat HTTP 401 as a state code and do not depend on the message text ŌĆö see ┬º12.3.

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

- Activation without a trigger ŌĆÆ `{ "message": "Workflow cannot be activated without a trigger" }`
- Invalid lifecycle transition ŌĆÆ `{ "message": "Invalid workflow state transition" }`
- Manual execution of a paused workflow ŌĆÆ `{ "message": "Workflow is not active" }`
- Overlapping execution ŌĆÆ `{ "message": "An execution is already running for this workflow" }`
- Capability validation ŌĆÆ structured error with `message`, `error`, `context` (see ┬º5.12)
- Trigger–action incompatibility ŌĆÆ structured error with `error: "incompatible_trigger"` (see ┬º4.7 and ┬º5.21)

### 7.5 OAuth / provider errors

`GET /api/connections/google/callback` returns 302 redirects with an `error=<code>` query parameter (see ┬º3.3). Not a JSON response.

### 7.6 Server errors (500)

Google provider failures from the integration endpoints (Gmail send, Calendar, Sheets, Docs, Analytics, labels) surface as HTTP 500 with a JSON body containing at least a `message` field. These are not meant to be interpreted field-by-field by the mobile client; treat them as retriable transient errors at the workflow-step level.

### 7.7 Step template resolution failures

When a `{{ trigger.* }}` or `{{ steps.N.output.* }}` reference cannot be resolved at execution time, the step is marked `failed` and the execution status becomes `failed`. The step's `error_message` contains a descriptive string, for example:

```
Template path "trigger.message_id" cannot be resolved.
```

or

```
Template path "steps.2.output.label_id" cannot be resolved.
```

These are not HTTP errors ŌĆö the workflow execution itself returns `200`/`201` with the execution object, and the failure is recorded on the execution and step records. Mobile must inspect `data.status` and `data.steps[].status`, not just the HTTP status code, to detect this class of failure.

---

## 8. Mobile end-to-end example (Gmail auto-reply)

Preconditions:

- User has authenticated and mobile holds a Sanctum token (via production Google OAuth, `POST /api/login`, or `/api/test/login` for dev/testing).
- User has already connected a Gmail account with the `gmail` capability so a `connection_id` exists.

Step 1 ŌĆö List connections

```
GET /api/connections
Authorization: Bearer <token>
```

Pick `connection_id = 12` from `data[]`. Verify its `scopes` array contains
`https://www.googleapis.com/auth/gmail.send` (for `send_email` /
`reply_to_email`) or the relevant `gmail.*` scope for the action the user
intends to configure. If not, prompt the user to reconnect.

Step 2 ŌĆö Fetch catalog

```
GET /api/catalog
Authorization: Bearer <token>
```

Confirm `google.gmail` exists; read its `triggers` and `actions`.

Step 3 ŌĆö Create workflow

```
POST /api/workflows
Body: { "name": "Gmail auto-reply" }
```

Capture `id = 42`, `status = "draft"`.

Step 4 ŌĆö Resolve dynamic label options

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

Resolve `gmail.labels` through the API contract ŌĆÆ `GET /api/gmail/labels`. Substitute `{{connection_id}} = 12`. Call:

```
GET /api/gmail/labels?connection_id=12
```

Render the returned `data[]` as a dropdown. Two possible user choices:

**Case A ŌĆö user picks a label (e.g. `INBOX`):** include `config.label_id` in the trigger body (Step 5).

**Case B ŌĆö user picks "all mail":** omit `config.label_id` entirely. Do not send `null` or `""`.

Step 5 ŌĆö Configure Gmail trigger

```
PUT /api/workflows/42/trigger
Body (Case A ŌĆö with label):
{
  "integration_key": "google.gmail",
  "trigger_key": "new_email_received",
  "connection_id": 12,
  "config": { "label_id": "INBOX" }
}
```

```
PUT /api/workflows/42/trigger
Body (Case B ŌĆö no label):
{
  "integration_key": "google.gmail",
  "trigger_key": "new_email_received",
  "connection_id": 12,
  "config": {}
}
```

Optional trigger filters can be added alongside `label_id` (or alone):

```
PUT /api/workflows/42/trigger
Body (with extra filters):
{
  "integration_key": "google.gmail",
  "trigger_key": "new_email_received",
  "connection_id": 12,
  "config": {
    "label_id": "INBOX",
    "from": "vip@example.com",
    "subject": "Invoice paid",
    "has_attachment": true,
    "query": "is:unread"
  }
}
```

Step 6 ŌĆö Add Gmail step

Read `catalog.integrations[google.gmail].actions[<action_key>].config` for the
field list. Two common examples:

Simple reply step:

```
POST /api/workflows/42/steps
Body: {
  "integration_key": "google.gmail",
  "action_key": "reply_to_email",
  "connection_id": 12,
  "config": {
    "to": "{{ trigger.from }}",
    "subject": "Re: {{ trigger.subject }}",
    "body": "<p>Auto-reply</p>",
    "thread_id": "{{ trigger.thread_id }}"
  }
}
```

Simple archive step:

```
POST /api/workflows/42/steps
Body: {
  "integration_key": "google.gmail",
  "action_key": "archive",
  "connection_id": 12,
  "config": {
    "message_id": "{{ trigger.message_id }}"
  }
}
```

For a multi-step workflow with inter-step references, see ┬º5.18.

For a workflow that emails Drive links for the incoming email's attachments,
see ┬º5.21.

Step 7 ŌĆö Activate workflow

```
POST /api/workflows/42/activate
```

Expect HTTP 200 with `data.status = "active"`. On HTTP 409, inspect the structured error and adjust. `error: "missing_scopes"` means the connection must be reconnected. `error: "incompatible_trigger"` means the action/trigger pairing is not allowed (see ┬º4.7).

Step 8 ŌĆö Manual execution (optional, for testing)

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

Step 9 ŌĆö Poll execution status

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

- `POST /api/register` ŌĆö provisioning (creates/updates users; no token).
- `POST /api/login` ŌĆö email/password login returning a Sanctum token.
- `POST /api/password/forgot` ŌĆö request a password reset code.
- `POST /api/password/reset` ŌĆö reset the password with a valid code.
- `GET /api/auth/google/redirect` ŌĆö Google login redirect.
- `GET /api/auth/google/callback` ŌĆö Google login callback.
- `POST /api/logout` ŌĆö revoke tokens.
- `POST /api/account/deactivate` ŌĆö soft-delete account.
- `GET /api/user` ŌĆö current user.
- `GET /api/connections` ŌĆö list user connections.
- `POST /api/connections/google/start` ŌĆö start Google connection OAuth.
- `GET /api/connections/google/callback` ŌĆö connection OAuth callback.
- `DELETE /api/connections/{connection}` ŌĆö disconnect.
- `GET /api/catalog` ŌĆö catalog discovery.
- `GET /api/gmail/labels` ŌĆö list Gmail labels for a connection.
- `GET /api/workflows` ŌĆö list workflows.
- `POST /api/workflows` ŌĆö create workflow.
- `GET /api/workflows/{id}` ŌĆö show workflow.
- `PUT /api/workflows/{id}` ŌĆö update workflow.
- `DELETE /api/workflows/{id}` ŌĆö soft-delete workflow.
- `POST /api/workflows/{id}/restore` ŌĆö restore workflow.
- `PUT /api/workflows/{id}/trigger` ŌĆö upsert trigger.
- `DELETE /api/workflows/{id}/trigger` ŌĆö delete trigger.
- `POST /api/workflows/{id}/steps` ŌĆö append step.
- `PUT /api/workflows/{id}/steps/{position}` ŌĆö update step.
- `DELETE /api/workflows/{id}/steps/{position}` ŌĆö delete step.
- `POST /api/workflows/{id}/activate` ŌĆö activate.
- `POST /api/workflows/{id}/pause` ŌĆö pause.
- `POST /api/workflows/{id}/execute` ŌĆö manual execution.
- `GET /api/workflows/{id}/executions` ŌĆö list executions.
- `GET /api/executions/{id}` ŌĆö show execution.

Non-production:

- `POST /api/test/login` ŌĆö dev-only. Must not be reachable in production. See ┬º1.5.

The eleven Gmail actions (`send_email`, `reply_to_email`, `create_draft`,
`mark_as_read`, `mark_as_unread`, `archive`, `trash`, `add_label`,
`remove_label`, `create_label`, `send_attachment_links_email`) are not
standalone HTTP endpoints. They are executed through the generic workflow
step API (`POST /api/workflows/{id}/steps` with
`integration_key = "google.gmail"` and the corresponding `action_key`).

The two Gmail triggers (`new_email_received`, `new_email_received_with_attachment`)
are configured through the generic workflow trigger API
(`PUT /api/workflows/{id}/trigger` with `integration_key = "google.gmail"`
and the corresponding `trigger_key`).

---

## 11. Deferred functionality Mobile must not depend on

- Email verification flow. Not implemented.
- Refresh-token / long-lived token policy. Not implemented.
- Per-device token revocation. Not implemented.
- Login endpoint rate limiting. Proposed, not yet applied. See ┬º1.7.
- Environment gating of `/api/test/login`. The endpoint is dev-only by convention but is not environment-gated at the route level. Planned for Phase 9 Security Hardening.
- `requires_connection` catalog flag. Not implemented.
- **Catalog-level output schema declaration.** The catalog exposes action inputs only. Output keys are documented in ┬º5.9 of this contract. There is currently no `outputSchema` field in the catalog response ŌĆö mobile must hardcode the output keys from ┬º5.9 for Gmail actions, and must not assume any output keys for Calendar / Sheets / Docs / Analytics.
- **Catalog-level exposure of `allowedTriggerKeys`.** The action-level trigger restriction is enforced server-side, but the restriction list is not published in the catalog. Mobile must handle the HTTP 409 `incompatible_trigger` response. Proactively hiding incompatible actions in the UI is a UX courtesy, not a correctness requirement.
- Field metadata for Calendar / Sheets / Docs / Analytics actions. Not implemented.
- Runtime handlers for Calendar / Sheets / Docs / Analytics workflow step actions. Not implemented.
- **Gmail attachment byte retrieval for downstream workflow consumption as binary content.** The `new_email_received_with_attachment` trigger materializes attachments into the user's Drive and exposes them via `attachments[].drive_file_id` and `attachments[].web_view_link`. There is **no action** that reads attachment bytes back out of Drive. `send_attachment_links_email` sends links, not bytes.
- Gmail Push (`users.watch`), Google Pub/Sub, `historyId` cursors. Not implemented.
- Retry / DLQ infrastructure. Not implemented.
- Catalog caching / ETag / multi-endpoint catalog. Not implemented.
- Project-supplied `lang/{locale}/validation.php` overrides. As of this contract version, only the framework default `en` translations are guaranteed. Per-field 422 messages for locales other than `en` fall back to the framework default until project overrides are added.
- Localization of `TestLoginController` and the Facebook controllers. Their source files were not supplied during the localization pass. They remain English-only. `/api/test/login` is dev-only in any case.
- Localization of raw business-result `message` strings embedded inside action result payloads.
- Live end-to-end verification of `send_attachment_links_email` against a real Gmail mailbox.

---

## 12. Localization

### 12.1 Supported locales

- `en` ŌĆö English (default, fallback).
- `ar` ŌĆö Arabic.

Any other value is ignored by the server.

### 12.2 Mechanism

The only client-facing locale mechanism is the standard `Accept-Language`
request header.

- Regional variants are normalized: `en-US`, `en-GB` ŌĆÆ `en`; `ar-EG`, `ar-SA` ŌĆÆ `ar`.
- Candidate languages are ordered by their `q` value.
- Candidates with `q=0` are treated as explicitly unacceptable and skipped.
- Candidates whose normalized form is not in the supported list are skipped,
  and the server continues to the next candidate.
- If no acceptable candidate is present, the server falls back to `en`.

There is **no** `X-Locale` header, **no** `?locale=` query parameter, and
**no** per-user stored locale (`users.locale` is not read or written). Do not
send any of these; if sent, they are ignored.

### 12.3 What is localized

Localized (HTTP response body only):

- Top-level `message` fields produced by project controllers in the Identity,
  Automation, Connections, Execution, and Integrations modules where the
  string was moved to a translation namespace.
- Top-level `message` / `error` fields on the root API endpoints whose source
  controller was supplied during the localization pass:
    - `GET /api/user` (inline closure in `routes/api.php`)
    - `POST /api/register` (`UserController::register`)
    - `POST /api/logout` (`GoogleAuthController::logout`)
    - `POST /api/account/deactivate` (`GoogleAuthController::deactivateAccount`)
    - `GET /api/auth/google/callback` catch-block `error` string
  These reuse the `identity::messages.*` namespace.
- All `label` and `description` strings inside the `GET /api/catalog`
  response, for every integration, action, trigger, and config field.
- The `message` field on the two password reset endpoints (`/api/password/forgot`,
  `/api/password/reset`) and the body of the OTP email.
- The `message` field on `409 incompatible_trigger` responses.

NOT localized (remains English, by design):

- `execution_steps.error_message` and `executions.error_message` ŌĆö internal
  diagnostics; remain English in the database and in every API response.
- Raw provider / business-result `message` strings returned inside action
  result payloads.
- Every `$e->getMessage()` diagnostic payload, Google exception message,
  `trace`, `file`, and `line` field.
- The 401 body `{ "message": "Unauthenticated." }` emitted by Laravel's
  authentication middleware. Treat HTTP 401 as a state code; do not depend
  on the message text.
- The top-level `message` on a 422 response (`"The given data was invalid."`),
  which is emitted by Laravel's exception handler. Read per-field messages
  from `errors`, not from `message`.
- Machine-readable identifiers, always: `integration_key`, `action_key`,
  `trigger_key`, `provider_key`, field `key`, field `type`, `capability`,
  `strategy`, `operation_id`, connection `status`, workflow `status`,
  execution `status`, step `status`, capability error codes on `error`
  (including `incompatible_trigger`), the `allowed_trigger_keys` array on the
  `incompatible_trigger` context, the `error` query parameter on the OAuth
  callback, and the `error` field on each attachment entry inside
  `trigger.attachments`.
- User-supplied data (workflow names, email subjects, etc.).

### 12.4 Catalog

The catalog's human-readable strings (`name`, `description`, action `label`
and `description`, trigger `label` and `description`, field `label` and
`description`) are resolved server-side using the request locale. The client
receives translated text, not translation keys. The catalog **JSON shape**,
identifier values, `capability` values, `strategy` values, field types,
`required` flags, `default` values, `options`, and `options_source` are
byte-identical across locales. Only the human-readable string values change.

### 12.5 Validation errors

The per-field messages inside a 422 response's `errors` object are localized
when the corresponding `lang/{locale}/validation.php` file exists. At the
time of this contract version, only the framework's built-in `en`
translations are guaranteed to resolve; project-supplied overrides for
individual locales may be absent. Mobile must treat HTTP 422 as a
validation-state code and read the per-field messages from `errors`.
Do not depend on the top-level `message` text.

Example envelope under `Accept-Language: ar` with project Arabic validation
files installed:

```json
{
    "message": "The given data was invalid.",
    "errors": { "email": ["žŁ┘é┘ä ž¦┘äž©ž▒┘Ŗž» ž¦┘äžź┘ä┘āž¬ž▒┘ł┘å┘Ŗ ┘ģžĘ┘ä┘łž©."] }
}
```

### 12.6 What does not change across locales

The following are identical regardless of `Accept-Language`:

- All endpoint paths.
- All HTTP status codes.
- All response envelope shapes (`{ message }`, `{ message, data }`,
  `{ message, errors }`, `{ error }`, `{ error, context }`).
- All identifier values listed in ┬º12.3.
- All capability error codes and their `context` payload keys, including
  `incompatible_trigger` and its `allowed_trigger_keys` list.
- All OAuth query-parameter names and error codes.
- All `Accept-Language`-insensitive data payloads (`data.*`).

### 12.7 Recommended mobile behavior

- Rely on the platform HTTP client's default `Accept-Language` header.
  No additional client work is required to receive a localized response.
- Detect error classes by HTTP status code and by structured fields
  (`errors.<field>`, `error`, `context.error`) ŌĆö not by the top-level
  `message` string.
- Do not send `X-Locale`, `?locale=`, or any stored locale preference.

---

## 13. Password Reset (Forgot Password)

### 13.1 Overview

Two public endpoints support password reset via email OTP:

```
POST /api/password/forgot    ŌĆö request a reset code
POST /api/password/reset     ŌĆö verify the code and set a new password
```

Both endpoints are public (no authentication required). Both return generic
responses to prevent email enumeration.

### 13.2 Request reset

```
POST /api/password/forgot
Body: { "email": "user@example.com" }
```

Response (HTTP 200):

```json
{ "message": "If that email exists, we've sent a reset code." }
```

Behavior:

- If the email matches an existing, non-soft-deleted user, the server
  generates a 6-digit code and emails it to that address. The code expires
  in 15 minutes.
- If the email does not match any user, the response is identical, but no
  email is sent.
- The same response is returned in both cases. Mobile must not attempt to
  distinguish.

Rate limits:

- 5 requests per email address per hour.
- 20 requests per IP address per hour.

Exceeded limits ŌĆÆ HTTP 429 `{ "message": "Too many requests. Please try again later." }`
with a `Retry-After` header.

The `message` value localizes when the request carries `Accept-Language: ar` ŌĆö see ┬º12.

### 13.3 Reset password

```
POST /api/password/reset
Body: {
  "email": "user@example.com",
  "code": "123456",
  "password": "new-password"
}
```

- `email` ŌĆö required, valid email.
- `code` ŌĆö required, string, 4ŌĆō10 characters (default 6).
- `password` ŌĆö required, string, minimum 6 characters.

Success (HTTP 200):

```json
{
    "message": "Password reset successful. Please log in with your new password."
}
```

Failure (HTTP 422):

```json
{ "message": "The reset code is invalid or has expired." }
```

Returned for every failure mode:

- No OTP issued for this email.
- OTP already used.
- OTP expired (15-minute TTL).
- Attempts exhausted (5 wrong codes).
- Code mismatch.

Mobile must show a single generic message to the user.

Rate limits:

- 20 requests per email address per hour.
- 60 requests per IP address per hour.

Exceeded limits ŌĆÆ HTTP 429 with `Retry-After`.

Behavior on success:

- The user's password is updated.
- All active Sanctum tokens for that user are revoked. The user must log in
  again with the new password.

The `message` value localizes when the request carries `Accept-Language: ar` ŌĆö see ┬º12.

### 13.4 Security notes

- The reset code is a 6-digit numeric OTP, uniformly random, no leading zero.
- Only a bcrypt hash of the code is stored. The plain code is never persisted.
- The code is single-use. Once consumed successfully, it cannot be reused.
- Issuing a new code invalidates any previously issued code for the same email.
- Wrong-code attempts increment an attempts counter on the code. After 5
  failed attempts, the code is rejected even if the correct code is later
  supplied. A new code must be requested.
- On password reset, all existing sessions (Sanctum tokens) for the user are
  revoked. This is a safety measure against compromised sessions.
- Email enumeration is mitigated by returning an identical response to both
  known and unknown email addresses.

### 13.5 What the OTP email contains

The reset email is a project-owned Blade template rendered by Laravel. It contains:

- A greeting with the user's name.
- The 6-digit code.
- A note that the code expires in 15 minutes.
- A note that the user can ignore the email if they did not request it.

The email is sent in the request locale, resolved from the `Accept-Language`
header (see ┬º12). The email body text is localized via `identity::messages.*`.

### 13.6 Production requirements

- `MAIL_MAILER` must be configured to a real mailer (SMTP, SES, Postmark, etc.).
  The default `log` driver writes emails to `storage/logs/laravel.log` and
  delivers nothing. Anyone with log access can read OTP codes.
- `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` must be configured.
- The `password_reset_otps` table must be migrated.

---

## 14. Final answers

**Q: Are Connections, Workflows, Triggers, Steps, and Executions isolated per authenticated user, or is there any cross-user mixing?**

A: Fully isolated. Every read/write goes through a user-scoped repository method. Cross-user access returns HTTP 404 consistently. No cross-user mixing was found in the code or in any test.

**Q: Does the current login/token flow correctly identify different users, or does the master-password test login map users to the same identity?**

A: Both `POST /api/login` (email/password) and the production Google OAuth flow correctly identify different users, each issuing a distinct Sanctum token per user. The master-password test login also issues a per-user token but does not verify the user's own password and must be treated as dev-only.

**Q: Which login methods are available to the mobile client today?**

A: Three:

- Production Google OAuth (`/api/auth/google/redirect` + callback).
- Production email/password login (`POST /api/login`) ŌĆö for users who have a password set via the provisioning flow (`POST /api/register`) or another path.
- Dev-only `/api/test/login` for local development.

All three return a Sanctum bearer token, which the mobile client uses identically. Password reset (`/api/password/forgot` + `/api/password/reset`) is a separate recovery flow ŌĆö it does not issue a token; the user logs in again with the new password.

**Q: Can a workflow have multiple steps that reference each other?**

A: Yes. Steps can reference an earlier step's output via `{{ steps.N.output.<key> }}`, where `N` is the 1-based position of an earlier step in the same workflow. The referenced `<key>` must be one of the keys that the earlier step's action actually returns. The verified output keys for all Gmail actions are listed in ┬º5.9. A full worked example is in ┬º5.18. Whole-template resolution preserves the resolved value's type (including arrays); invalid or future-step references fail the step at runtime with a descriptive `error_message`.

**Q: Does the API support Arabic and English responses?**

A: Yes. Every user-facing `message`/`error` field produced by project controllers localizes based on the standard `Accept-Language` request header, as do all catalog `label`/`description` values. Machine-readable identifiers, HTTP status codes, and response envelope shapes are identical across locales. Execution diagnostics, raw provider messages, and Laravel-emitted framework strings (401 body, 422 top-level message) remain English. Full details in ┬º12.

**Q: Does the Gmail attachment trigger require Drive access on the Google connection?**

A: Yes. `new_email_received_with_attachment` requires the Google connection to hold `https://www.googleapis.com/auth/drive.file` in addition to the Gmail scopes. Activation fails with HTTP 409 `{ "error": "missing_scopes", "context": { "missing_scopes": [".../drive.file"] } }` if the Drive scope is absent. At runtime, if the scope is missing, the polling strategy skips the workflow with reason `drive_connection_required` without advancing the cursor. See ┬º5.20.

**Q: Can an action be restricted to a specific trigger?**

A: Yes. The server declares a `allowedTriggerKeys` list on some actions. When a workflow pairs a restricted action with a trigger outside that list, the workflow API rejects the operation with HTTP 409 `error: "incompatible_trigger"` at every write path and at activation. The list is not exposed in the catalog; mobile must handle the 409. The only restricted action today is `send_attachment_links_email` (see ┬º5.21). All other actions are unrestricted. See ┬º4.7.

**Q: Can the Mobile Developer start integrating tomorrow?**

A: Yes. Use `/api/login` for users who have email/password credentials, or the Google OAuth flow for users who sign in with Google. Every endpoint needed to build the automation UI is present, authenticated, and owner-scoped. The Catalog uses the same canonical identifiers as the Workflow API, so no client-side mapping layer is required. See ┬º5.7 for the one config-handling pitfall (`label_id`) that must be handled correctly on the mobile side. See ┬º3.5 for the Gmail/Drive reconnect requirement. See ┬º4.7 for the trigger–action compatibility rule. See ┬º5.9 for the verified output keys per Gmail action, ┬º5.17 and ┬º5.18 for how to build multi-step workflows with `{{ steps.N.output.* }}` references, ┬º5.20 for the attachment trigger, ┬º5.21 for the attachment links email action, ┬º12 for localization, and ┬º13 for the password reset contract.
