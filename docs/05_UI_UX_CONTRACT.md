# 05 — UI/UX Contract

Status: ACCEPTED / REFERENCE

Version: 1.1.0
Last updated: after Password Reset + Localization.

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

---

## Authentication Flow

The backend supports three authentication paths today. The mobile app should
surface them consistently — the resulting Sanctum bearer token is
interchangeable in all three cases.

### Path A — Google OAuth

1. User taps "Continue with Google".
2. App opens `/api/auth/google/redirect` in a browser or web view.
3. User consents on Google.
4. Backend redirects to `https://www.aibrooklyn.net?token=<sanctum-token>`.
5. App intercepts the redirect and extracts the token from the `token` query
   parameter.
6. App stores the token and proceeds to the automation landing page.

If the redirect arrives with `token=null`, the user has no bot access and the
app should show the "contact support" state.

If the redirect lands on `/account-deleted.html`, the account is soft-deleted.

### Path B — Email / password login

1. User enters email + password on the login screen.
2. App calls `POST /api/login` with `{ email, password }`.
3. Backend returns `{ message, data: { token, user } }` on success (HTTP 200).
4. On failure, the backend returns HTTP 401 with a generic
   `{ "message": "Invalid credentials." }`. The app must display a single
   generic "invalid credentials" message to the user, not distinguish between
   unknown email and wrong password.
5. App stores `data.token` and proceeds.

### Path C — Password reset (see next section)

The password reset flow does not itself issue a Sanctum token. On success, the
user returns to the login screen and authenticates normally.

### Token storage and logout

- All three paths yield the same `Authorization: Bearer <token>` header for
  subsequent requests.
- Logout calls `POST /api/logout` and revokes all tokens for the user.
- Account deactivation calls `POST /api/account/deactivate` and soft-deletes
  the account.

---

## Password Reset Flow

The backend provides two public endpoints. Mobile must implement the UI flow
below against them. See `docs/06_MOBILE_API_CONTRACT.md` §13 for the full HTTP
contract.

### Entry point

A "Forgot password?" link on the login screen.

### Screen 1 — Request reset code

- Single input: email.
- Button: "Send reset code".
- On tap: `POST /api/password/forgot` with `{ email }`.
- The backend always returns HTTP 200 with a generic message:
  `"If that email exists, we've sent a reset code."`.
- Show that message as-is. Do **not** branch on whether the email exists — the
  backend deliberately returns identical responses for both cases to prevent
  email enumeration.

### Screen 2 — Enter the OTP + new password

- User opens their email inbox, reads the 6-digit code, and returns to the app.
- Inputs: 6-digit numeric code, new password, and (recommended) confirm password.
- Button: "Reset password".
- On tap: `POST /api/password/reset` with `{ email, code, password }`.
- Success (HTTP 200): `{ "message": "Password reset successful. Please log in with your new password." }`.
- Failure (HTTP 422): `{ "message": "The reset code is invalid or has expired." }`.
  The app must show a single generic message — the backend does not distinguish
  between wrong code, expired code, already-used code, or attempts exhausted.
- Failure (HTTP 429): the app is rate-limited. Show a "try again later"
  message. A `Retry-After` header is provided.

### Screen 3 — Success

- Show a confirmation and route the user back to the login screen.
- The new password is required to log in — the reset flow does not issue a
  token.

### Resend

- The user may return to Screen 1 to request a new code.
- The backend rate-limits issuance to 5 requests per email per hour, 20 per IP
  per hour. Exceeding the limit returns HTTP 429. Surface this to the user as
  "try again later".

### Important implementation notes for mobile

- **Do not send `null` or an empty string for the code.** The code field is
  required and 4–10 characters.
- **Do not attempt to distinguish failure modes.** A single generic message is
  required by design.
- **Do not cache the OTP in the app.** It arrives by email; the app only needs
  to accept it as user input.
- **The reset email is sent in the locale resolved from the `Accept-Language`
  header on the `/forgot` request.** Send `Accept-Language` on the `/forgot`
  call so the email is delivered in the user's language (Arabic or English).
- **On success, all existing sessions are revoked.** Any other device using the
  same account must log in again.

### Endpoints summary

| Action                | Method | Path                    |
| --------------------- | ------ | ----------------------- |
| Request reset code    | POST   | `/api/password/forgot`  |
| Verify code + reset   | POST   | `/api/password/reset`   |

No authentication required for either.

---

## Localization Note

All user-facing `message` and `error` values in HTTP responses are localized
based on the `Accept-Language` request header. Supported locales: `en` and
`ar`. Fallback: `en`.

The catalog response's human-readable labels and descriptions
(`integration.name`, `action.label`, `trigger.label`, `field.label`,
and their `description` counterparts) are also localized server-side.
Identifiers and the JSON shape do not change.

The mobile app should:

- Rely on the platform HTTP client's default `Accept-Language` header.
- Detect error classes by HTTP status code and by structured fields — not by
  the localized `message` string.
- Never send `X-Locale`, `?locale=`, or a stored locale preference.

See `docs/06_MOBILE_API_CONTRACT.md` §12 for the full localization contract.
