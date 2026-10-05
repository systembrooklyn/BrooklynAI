# 07 — Authentication Architecture

Status: PARTIALLY IMPLEMENTED. Sections marked `[IMPLEMENTED]` describe live behavior. Sections marked `[DESIGN]` are design-only and have not been authorized for implementation.

Scope: authentication surface of the platform — Google OAuth (existing, production), Email/Password login (existing), and Password Reset via email OTP (implemented). Written against the same `User` model and the same Sanctum bearer-token model.

Version: 1.1.0
Last updated: post-password-reset implementation.

Phase 9 (Security Hardening) remains LOCKED.
Phase 10 (Legacy Cleanup) remains LOCKED.

References:

- `docs/03_ARCHITECTURE_DECISIONS.md` — ADR-029 (Localization), ADR-030 (Password Reset)
- `docs/06_MOBILE_API_CONTRACT.md` — the mobile-facing contract

---

## 1. Current architecture (baseline) `[IMPLEMENTED]`

Authentication today is Google OAuth via Socialite, email/password login, and
Sanctum bearer-token issuance. Password reset is available via email OTP.

```
Google OAuth (Socialite)        Email + Password              Password Reset
        │                              │                            │
        ▼                              ▼                            ▼
   Resolve user by email        Hash::check()                OTP to email
        │                              │                            │
        └──────────────┬───────────────┴────────────────────────────┘
                       ▼
             Sanctum personal access token
                       │
                       ▼
                Mobile / Web API
                       │
                       ▼
    Connections / Workflows / Triggers / Steps / Executions
```

Registered authentication-related routes (verified):

- `POST /api/register` — creates or updates a user; does NOT issue a token.
- `POST /api/login` — email/password login; issues a Sanctum token.
- `GET  /api/auth/google/redirect` — begins Google login via Socialite.
- `GET  /api/auth/google/redirect-google` — begins Google login with the legacy scope list.
- `GET  /api/auth/google/callback` — completes Google login; redirects to the frontend with `?token=<sanctum-token>`.
- `POST /api/logout` — revokes every Sanctum token owned by the authenticated user.
- `POST /api/account/deactivate` — soft-deletes the user and revokes all tokens.
- `POST /api/test/login` — dev-only; shared master password; issues a token for any existing email.
- `POST /api/password/forgot` — **new**; requests a password reset OTP.
- `POST /api/password/reset` — **new**; verifies the OTP and sets a new password.

Ownership model: every user-owned resource is scoped by `user_id` at the repository layer. No cross-user access exists in the supplied code.

---

## 2. Production compatibility contract `[IMPLEMENTED]`

This is the hard constraint. Everything must respect it.

### MUST NOT CHANGE

- `GET /api/auth/google/redirect` — method, path, and 302 behavior.
- `GET /api/auth/google/redirect-google` — method, path, and 302 behavior.
- `GET /api/auth/google/callback` — must continue to accept `code`, resolve the `User` by email, issue a Sanctum token, and redirect to `https://www.aibrooklyn.net?token=<token>`. The query parameter name and redirect target are part of the live contract.
- `User` model field names: `name`, `email`, `password`, `google_id`, `avatar`, `has_bot_access`, `access_expiry`, `google_access_token`, `google_refresh_token`, `google_token_expires_at`.
- `users.email` remains the lookup key for Google login.
- Sanctum is the auth mechanism. Bearer token in `Authorization` header. No replacement.
- `POST /api/logout` continues to revoke all tokens for the user.
- `POST /api/account/deactivate` continues to soft-delete and revoke all tokens.

### CAN BE EXTENDED

- Additive routes (already done: `/api/login`, `/api/password/forgot`, `/api/password/reset`).
- Additive `User` columns (nullable, with safe defaults).
- Additive token abilities for future endpoints.
- Additive middleware for rate limiting on new endpoints.
- Additive notifications (email verification, password reset).
- Additive documentation.

### CAN BE IMPROVED LATER (post-migration)

- Sanctum token expiration policy.
- Google token encryption at rest.
- Token-in-URL redirect replaced by a POST-back / deep-link intermediate page — but only if the current mobile/web client can be migrated in a coordinated release.
- Socialite state handling replaced by the same state-table approach already used by Connections — with the same contract preserved at the HTTP boundary.

### REQUIRES MIGRATION / SPECIAL HANDLING

- Any change to the `?token=<token>` query parameter on the Google callback would break live mobile/web clients. Introduce only via a coordinated migration with a deprecation window and possibly a second parallel callback path.
- Any change to `google_id` semantics requires backfilling existing rows before enforcement.
- Any move away from email-based lookup in the Google callback requires a `sub`-first lookup with email as a fallback, plus a migration to backfill `google_id` for existing users.
- Any change to `has_bot_access` / `access_expiry` semantics requires a migration decision for existing users.

---

## 3. Identity model decision `[IMPLEMENTED — Option A]`

Two candidate models were considered.

### OPTION A — Keep `users` as the identity table; extend in place

The existing `users` table already carries:

- `email` (unique)
- `password` (nullable in the current schema, hashed)
- `google_id` (nullable)
- `avatar`, `has_bot_access`, `access_expiry`
- `google_access_token`, `google_refresh_token`, `google_token_expires_at`

Google login resolves the user by email; the password column already exists; `HasApiTokens` is wired.

Advantages:

- Zero migration risk for existing Google users.
- No changes to any existing controller or repository.
- Preserves the current email-based lookup contract.
- Matches the existing DDD-agnostic `User` model that lives outside `app/Modules/` and is referenced from `App\Models\User` across modules.
- Sanctum is already integrated.

Disadvantages:

- Extending this table later requires additive columns rather than a clean provider table.
- Future non-Google providers will add columns to `users` unless a provider table is introduced at that time.

### OPTION B — Introduce `auth_identities` alongside `users`

A new table keyed by `(provider, subject)`, with `user_id` foreign key, plus a migration to backfill Google identity rows for all existing users.

Advantages:

- Cleaner long-term multi-provider model.
- Provider-specific metadata isolated.

Disadvantages:

- Migration touches every existing Google user.
- Requires new entity, repository, provider abstraction, and a lookup path that duplicates the current `email` lookup.
- Higher blast radius: current callback, current Connection modules, current tests. Every existing path that resolves the user by email must be revisited.
- Introduces a second identity source of truth at a moment when the production system is live.

### Decision

**Option A.** Reasons, ranked by the priorities stated in the design brief:

1. Production safety — zero disruption to the live Google login.
2. Backward compatibility — no changes to controllers, routes, tests, or the `User` model contract.
3. Minimal architectural disruption — an additive column is a smaller change than a new table with backfill.
4. Security — email remains the sole identity key today, which is a manageable scope until a linking protocol is defined.
5. Extensibility — if a future batch needs multiple providers beyond Google and password, `auth_identities` can be introduced then, with a proper migration plan and no live user impacted at that point.

Option A does not preclude Option B later. It defers the abstraction until there is a real need for it.

---

## 4. Google preservation strategy `[IMPLEMENTED as-is]`

Google login is production. Improvements are separated by risk class.

### A) Current production behavior

- Redirect endpoint initiates Socialite.
- Callback resolves user by `email`.
- Issues a Sanctum token.
- Redirects with `?token=...`.
- Writes `google_id`, `google_access_token`, `google_refresh_token`, `google_token_expires_at`, `avatar`, `name`.
- Trashed users are redirected to `/account-deleted.html`.
- Users without bot access get `token=null`.

### B) Security weaknesses (documented, not fixed here)

- Sanctum token travels in a URL query parameter.
- Google access/refresh tokens stored in plain `text` columns.
- Socialite stateless flow delegates OAuth state handling to the library.
- Callback returns 500 JSON with `file` and `line` on exception.
- No rate limiting on the OAuth initiation or callback.
- Lookup is by email only; `google_id` is stored but not used as primary identifier.

### C) Safe future improvements `[DESIGN]`

- Add `throttle:` middleware to the callback and redirect endpoints.
- Encrypt `users.google_access_token` / `users.google_refresh_token` via a model cast. This is additive: a migration reads the plaintext values and writes encrypted ones in place. Existing tokens keep working after the cast is enabled, provided the migration is run under the same `APP_KEY`.
- Add `sub`-first lookup with `email` fallback, but only after backfilling `google_id` for existing rows. Contract at the HTTP boundary unchanged.
- Introduce an additive second callback path (e.g. `/api/auth/google/callback-v2`) that returns the token in a JSON body, and gradually move mobile clients to it while the legacy path remains live.

### D) Breaking change risk

- Removing `?token=<token>` from the redirect would break live mobile/web consumers. Deferred — requires compatibility migration.
- Changing the redirect target `https://www.aibrooklyn.net` would break live consumers. Deferred — requires coordination.
- Switching identity resolution from `email` to `sub` without a fallback would break existing users whose `google_id` is null. Deferred — requires a backfill and a mixed-mode lookup.

Nothing in §4 C/D is approved for implementation.

---

## 5. Google account + password account linking protocol `[DESIGN]`

Five scenarios are analyzed. All decisions below are recommendations for review; none are final.

### Scenario A — Google user adds a password

Current: `User #10` has `email = user@example.com`, `google_id = G123`, `password = null`.

Recommended behavior:

- Allow the user to set a password only via an authenticated endpoint (user must be logged in with their existing Google session).
- Setting a password never changes `google_id`, never removes any Google tokens.
- The password is hashed. Email on the account is assumed verified because it was Google-provided.
- No confirmation email required for setting a password on an already-authenticated account.
- Do not allow adding a password without an active authenticated session.

### Scenario B — Password user signs in with Google

Current: `User #20` has `email = user@example.com`, `password = hashed`, `google_id = null`.

Recommended behavior:

- Do not auto-link on the first Google login.
- Require an explicit linking step after the user has proven ownership of the password account:
  1. User is authenticated (password login).
  2. User initiates "Connect Google account" from an authenticated endpoint.
  3. Google returns an email that matches the same user. Because the user is already authenticated and the Google account was just proven, linking is safe.
  4. Only at that moment write `google_id` (and refresh tokens if desired).
- Do not allow the login-time callback to link the accounts on its own.

### Scenario C — Google email matches, `sub` differs

Current: `User #30` has `google_id = G_old`; Google now returns `sub = G_new` for the same email.

Recommended behavior:

- Reject the login silently, respond with the standard `token=null` redirect, and log the mismatch server-side.
- Do not overwrite `google_id` in place. Overwriting would silently replace a linked identity.
- Provide an authenticated "Reconnect Google account" endpoint for legitimate cases (Google account replaced, email moved between accounts, etc.).

### Scenario D — Same email, different people

Current: two humans happen to use the same email in two different systems.

Recommended behavior:

- The system cannot distinguish "same email, different people" from "same person" using email alone.
- Therefore: do not auto-link. Do not auto-create a second account with the same email.
- Force the user through the explicit linking protocol (Scenario B flow) or reject the login.

### Scenario E — Google email not present in `users`

Current: the callback has no user-creation branch (it is commented out).

Recommended behavior:

- Do not auto-create in the Google callback.
- Either (a) keep the current behavior of returning `token=null` and rely on the existing registration path, or (b) introduce an explicit registration flow that also accepts a Google token as proof of the email.
- Any auto-creation must go through the same registration pipeline used for email/password, so that `has_bot_access` / `access_expiry` and any future rules apply consistently.

### Cross-cutting rules

- Email matching alone is never sufficient for automatic linking when the target account already has a different primary authentication method.
- Linking always requires an authenticated session on the account being linked to.
- Unlinking must not be possible when the account would be left without any primary authentication method.

---

## 6. Email/Password authentication design `[PARTIALLY IMPLEMENTED]`

### 6.1 Registration `[IMPLEMENTED — unchanged]`

The live `POST /api/register` route remains the provisioning path.

Current endpoint:

- `POST /api/register` — creates or updates a user. Does NOT issue a token. Validation: `name`, `email`, `password` (min 6), `st_num` (nullable), `access_expiry`.
- Behavior when email exists: updates `access_expiry` and `has_bot_access`. Does not touch the password.
- Response strings localized via `identity::messages.*` (per ADR-029).

Design decision that remains open `[DESIGN]`:

- Option 1 — Redefine `/api/register` to also issue a token. This changes the current production contract for any client relying on it. Not recommended.
- Option 2 — Keep `/api/register` as-is, and add a new endpoint (working name `POST /api/auth/register`), which:
  - Creates a user only when the email is new.
  - Hashes the password.
  - Issues a Sanctum token.
  - Returns the token and user in a JSON body.
  - Requires email verification before the account can authenticate beyond a limited scope.
  - Does not affect existing accounts.
- **Recommended:** Option 2. Additive. **Not implemented.**

### 6.2 Login `[IMPLEMENTED]`

Live endpoint: `POST /api/login`.

- Input: `email`, `password`.
- Validates credentials via `Hash::check`.
- Verifies account status: not soft-deleted.
- Issues a Sanctum token with the standard mechanism.
- Returns a JSON body: `{ "message": "Login successful.", "data": { "token": "...", "user": { ... } } }`.
- On failure, returns a generic `401 { "message": "Invalid credentials." }` without revealing whether the email exists.
- Response strings localized via `identity::messages.*` (per ADR-029).
- `has_bot_access` / `access_expiry` are NOT checked at login — they are product entitlements, not authentication gates (see §11).

**Rate limiting `[DESIGN]`:** a corrected `LoginController` with a per-email+IP rate limit of 5 attempts per minute was proposed. Not applied. Awaits Phase 9 authorization.

### 6.3 Password change `[DESIGN]`

Authenticated endpoint (working name `POST /api/auth/password/change`):

- Input: `current_password`, `new_password`.
- Verifies current password before changing.
- Hashes and stores the new password.
- Optionally revokes all other tokens (`tokens()->where('id', '!=', $currentTokenId)->delete()`).

Not implemented.

### 6.4 Relationship to `/api/register`

`/api/register` remains live and unchanged. No `/api/auth/register` endpoint exists yet.

---

## 7. Email verification `[DESIGN]`

Current state: no email verification exists.

Recommended behavior:

- Required for new email/password registrations.
- Not required for Google-provided emails (Google has already verified them).
- Not retroactively required for existing Google users.
- Not required for existing password-less users (none exist today).

Verification flow:

1. On registration, set `email_verified_at = null` (additive column).
2. Send a signed verification link (working name `GET /api/auth/email/verify/{id}/{hash}`) with a TTL.
3. On success, set `email_verified_at`.
4. Before verification, the user can log in but with a restricted token that only allows profile retrieval and resending verification. Restricted tokens are an additive mechanism and do not affect existing Google tokens.
5. Unverified accounts expire after a documented window if unused.

Edge cases:

- Google users who later add a password: their email is already trusted; set `email_verified_at` at the moment they set a password.
- Password user whose email is later verified via Google linking: keep verification.

No implementation in this phase.

---

## 8. Password recovery `[IMPLEMENTED]`

Implemented as an OTP-based reset flow, live in production.

### 8.1 Endpoints

Two public endpoints (no authentication required):

```
POST /api/password/forgot
POST /api/password/reset
```

### 8.2 `/api/password/forgot`

- Request body: `{ "email": "user@example.com" }`.
- Validation: `email` — required, string, valid email format, max 255.
- If the email matches an existing, non-soft-deleted user, the server:
  1. Generates a 6-digit numeric OTP (uniformly random, no leading zero).
  2. Stores `Hash::make($code)` in the `password_reset_otps` table.
  3. Sends an email containing the plain code.
- If the email does NOT match any user: identical response, no email sent.
- Response (HTTP 200): `{ "message": "If that email exists, we've sent a reset code." }`.
- Email enumeration is prevented by the generic response.
- Response body is localized via `identity::messages.*` (ADR-029).

Rate limits (via `RateLimiter`):

- 5 requests per email address per hour.
- 20 requests per IP address per hour.

Exceeded → HTTP 429 with `Retry-After: 3600`.

### 8.3 `/api/password/reset`

- Request body: `{ "email": "...", "code": "123456", "password": "new-password" }`.
- Validation: `email` (valid), `code` (string, 4–10 chars), `password` (string, min 6, max 255).
- On success:
  1. The user's password is updated (via the model's `password` cast, which hashes).
  2. All active Sanctum tokens for that user are revoked.
  3. Response (HTTP 200): `{ "message": "Password reset successful. Please log in with your new password." }`.
- On every failure mode, the response is identical: HTTP 422 `{ "message": "The reset code is invalid or has expired." }`.

Failure modes:

- No OTP row exists for the email.
- OTP already used.
- OTP expired (15-minute TTL).
- Attempts exhausted (5 wrong attempts on the same code).
- Code mismatch.
- User was deleted between issuance and reset.

Rate limits:

- 20 requests per email address per hour.
- 60 requests per IP address per hour.

Exceeded → HTTP 429 with `Retry-After: 3600`.

### 8.4 OTP lifecycle

- Length: 6 digits.
- Character set: numeric.
- TTL: 15 minutes.
- Attempt cap: 5 wrong attempts per issued code. On the 6th attempt, the code is rejected even if the correct code is later supplied. A new code must be requested.
- Single-use: once consumed successfully, a code cannot be reused.
- Re-issuance: issuing a new code for the same email replaces the prior row entirely. Only one active code per email exists at any time.
- Storage: only a bcrypt hash of the code is persisted. The plain code is never persisted and never logged.

### 8.5 Storage

New table `password_reset_otps`, created by a module-owned migration:

```
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

Index: `expires_at` (for future cleanup).

The existing `password_reset_tokens` table remains unused and unmodified.

### 8.6 Email delivery

- Delivered via a Laravel notification (`PasswordResetOtpNotification`).
- Body rendered from a project-owned Blade template:
  `app/Modules/Identity/Views/emails/password-reset-otp.blade.php`.
- No Laravel default mail template. No branding from the framework.
- Body localized via `identity::messages.*` using the request locale (`Accept-Language`, ADR-029).
- `MAIL_MAILER` must be configured to a real transport (SMTP, SES, Postmark, Resend) in production. The default `log` driver writes the OTP to `storage/logs/laravel.log` and delivers nothing. Documented in `docs/08_DEPLOYMENT.md`.

### 8.7 Config

`app/Modules/Identity/Infrastructure/Config/identity.php` (merged at runtime via `mergeConfigFrom`):

```php
'password_reset' => [
    'code_length'                          => env('PASSWORD_RESET_CODE_LENGTH', 6),
    'code_ttl_minutes'                     => env('PASSWORD_RESET_CODE_TTL_MINUTES', 15),
    'max_attempts'                         => env('PASSWORD_RESET_MAX_ATTEMPTS', 5),
    'forgot_rate_limit_per_email_per_hour' => env('PASSWORD_RESET_FORGOT_RATE_EMAIL', 5),
    'forgot_rate_limit_per_ip_per_hour'    => env('PASSWORD_RESET_FORGOT_RATE_IP', 20),
    'reset_rate_limit_per_email_per_hour'  => env('PASSWORD_RESET_RESET_RATE_EMAIL', 20),
    'reset_rate_limit_per_ip_per_hour'     => env('PASSWORD_RESET_RESET_RATE_IP', 60),
],
```

### 8.8 Explicit non-features

The following were considered and are **NOT** implemented:

- **Master OTP** — a static shared secret bypassing the per-email OTP was proposed and explicitly rejected. It would be a privileged backdoor with no per-person audit trail.
- **CLI reset command** — `php artisan user:reset-password` was proposed as a safer alternative for support use; not implemented.
- **Admin reset endpoint** — not implemented.
- **Email link flow** — not implemented. Only OTP is supported.
- **SMS / WhatsApp delivery** — not implemented.
- **Password reset for soft-deleted users** — not implemented. Soft-deleted users cannot reset.
- **Notification to the account owner when reset is completed** — not implemented. On a normal user-driven reset this is redundant; it becomes meaningful only for an operator-initiated reset, which itself is not implemented.

### 8.9 Related ADR

This section documents the implementation decision recorded in **ADR-030**.

---

## 9. Sanctum token lifecycle `[PARTIALLY IMPLEMENTED]`

Current `[IMPLEMENTED]`: personal access tokens, no abilities, no expiration (`'expiration' => null`), no per-device tracking.

Design decisions that remain open `[DESIGN]`:

- Token creation: unchanged for Google login and email/password login. Both use the same `createToken` mechanism.
- Expiration: currently null. Any change to expiration must NOT apply retroactively to already-issued tokens. When expiry is introduced, it must apply only to newly issued tokens.
- Revocation: logout revokes all tokens (unchanged). A future per-device logout endpoint would revoke a single token and is additive.
- Account deactivation: revokes all tokens (unchanged).
- Multiple devices: supported today implicitly. No change.
- Per-device logout: additive endpoint (working name `POST /api/auth/logout/current`) that deletes only the current token. `POST /api/logout` continues to delete all. Not implemented.
- Password change: revoke all other tokens. The active token may be retained or rotated. Recommended: retain the active token, revoke others. Not implemented (there is no password change endpoint yet).
- **Password reset `[IMPLEMENTED]`**: revokes ALL tokens for the user. The user must re-authenticate. This is live behavior, documented in `docs/06_MOBILE_API_CONTRACT.md` §13.3.
- Compromised token handling: no endpoint today. A future "revoke all tokens" endpoint is additive and does not change any existing contract.

Safety rule for the migration: **no change to token policy applies to tokens already issued.** Any expiration policy applies only to tokens created after the change.

---

## 10. Google identity vs Google connection `[IMPLEMENTED]`

These are separate concepts and must remain separate.

**Google Identity**
Google OAuth → User authentication → Sanctum token.

Owns: `users.google_id`, `users.google_access_token`, `users.google_refresh_token`, `users.google_token_expires_at`.

**Google Connection**
Connections module OAuth → `Connection` row owned by a `user_id` → Actions (Gmail, Calendar, Sheets, Docs, Analytics).

Owns: `connections` table and its encrypted credentials. Not `users.google_*`.

Email/password authentication does not touch Connections. Password reset does not touch Connections. Ownership semantics are unchanged: a user who owns a Connection while logged in via Google still owns it when logged in via email/password, because the `user_id` is the same.

---

## 11. `has_bot_access` and `access_expiry` `[IMPLEMENTED as-is]`

These are product-level access entitlements, not authentication credentials.

- `has_bot_access` — whether the user is entitled to use the bot.
- `access_expiry` — the date until which the entitlement is valid.

Current uses:

- Set during `/api/register` from `today() < access_expiry`.
- Checked in the Google login callback: users with `has_bot_access == false` receive `token=null`.
- NOT checked at `/api/login`. This is intentional — auth ≠ product access.

Recommended treatment `[DESIGN]`:

- Authentication (Google or email/password) issues a token if the user exists and is not soft-deleted.
- Product access is enforced at request time via middleware or policy checks that read `has_bot_access` and `access_expiry`.
- The current Google callback behavior for `has_bot_access == false` is preserved for backward compatibility. It is not a model to extend to email/password login.
- This keeps authentication ≠ product access.

No change in this phase.

---

## 12. Development / test login `[IMPLEMENTED as-is]`

Current: `POST /api/test/login` uses a shared master password to issue a token for any existing email. Marked dev-only in comments. Not gated by environment.

Design decision `[DESIGN]`:

- Keep the endpoint in development and test environments.
- Gate it explicitly by environment (e.g. `APP_ENV !== 'production'`) via middleware or route registration.
- Do not remove it and do not change its current test behavior.
- Treat as a Phase 9 candidate for environment gating.

No changes in this phase.

---

## 13. Rate limiting / abuse protection `[PARTIALLY IMPLEMENTED]`

Rate limiting status today:

| Endpoint | Rate limit | Status |
| --- | --- | --- |
| `POST /api/register` | — | Not implemented |
| `POST /api/login` | — | **Not implemented** — corrected controller proposed; not applied |
| `POST /api/password/forgot` | 5 per email/h, 20 per IP/h | **Implemented** (ADR-030) |
| `POST /api/password/reset` | 20 per email/h, 60 per IP/h | **Implemented** (ADR-030) |
| `GET /api/auth/google/redirect` | — | Not implemented |
| `GET /api/auth/google/callback` | — | Not implemented |
| `POST /api/test/login` | — | Not implemented |

Design `[DESIGN]` — remaining to add in Phase 9:

- `POST /api/register` — rate limit by IP.
- `POST /api/login` — rate limit by IP and by email (proposed 5/min per email+IP).
- `GET /api/auth/google/redirect` and `/api/auth/google/callback` — rate limit by IP.
- `POST /api/test/login` — rate limit by IP; environment-gated.

---

## 14. Account lifecycle `[IMPLEMENTED + DESIGN]`

- **Active** — normal. `[IMPLEMENTED]`
- **Unverified** (future email/password users only) — authenticated with restricted token until verification completes. `[DESIGN]`
- **Suspended** — not currently modelled. Do not add unless required by a future batch. `[DESIGN]`
- **Soft-deleted** — `deleted_at` set. Login rejected. Tokens revoked. Existing behavior preserved. `[IMPLEMENTED]`
- **Restored** — no user-restore endpoint exists. Not in scope. `[DESIGN]`
- **Password changed** — other tokens revoked. `[DESIGN]` (no password change endpoint yet).
- **Password reset `[IMPLEMENTED]`** — all tokens revoked. User must re-authenticate with the new password. See §8.
- **Google linked** — `google_id` written after authentication. `[IMPLEMENTED]` (only written at Google callback login).
- **Google unlinked** — only allowed if the account has another primary authentication method. `[DESIGN]`

---

## 15. Mobile client contract `[IMPLEMENTED]`

The mobile client has three authentication paths today.

### Google OAuth

```
Mobile
  ↓
Open /api/auth/google/redirect in browser / web view
  ↓
User consents on Google
  ↓
Google redirects to /api/auth/google/callback
  ↓
Backend redirects to https://www.aibrooklyn.net?token=<sanctum-token>
  ↓
Mobile intercepts redirect and extracts the token
```

### Email/password login

```
Mobile
  ↓
POST /api/login with email + password
  ↓
Backend returns { message, data: { token, user } } in a JSON body
  ↓
Mobile stores token
```

### Password reset

```
Mobile
  ↓
POST /api/password/forgot with email
  ↓
Backend returns generic 200 (no enumeration)
  ↓
User receives email with 6-digit OTP
  ↓
Mobile prompts for OTP + new password
  ↓
POST /api/password/reset with email + code + password
  ↓
Backend returns 200 (on success) or 422 (on generic failure)
  ↓
Mobile returns to /api/login with the new password
```

All three flows yield the same kind of Sanctum bearer token and the same
`Authorization: Bearer <token>` usage afterward.

See `docs/06_MOBILE_API_CONTRACT.md` §13 for the exact request/response contract.

---

## 16. Migration strategy `[PARTIALLY EXECUTED]`

### Phase A — Baseline preserved `[EXECUTED]`

- Google login remains the primary production authentication.
- Existing users, tokens, and connections are unaffected.

### Phase B — Add Email/Password capability `[PARTIALLY EXECUTED]`

Shipped:

- `POST /api/login` (email/password login, Sanctum token).
- `POST /api/password/forgot`, `POST /api/password/reset` (password reset via email OTP).
- `password_reset_otps` table.

Not yet shipped:

- `POST /api/auth/register` (Option 2 in §6.1) — registration still only happens via `/api/register`.
- `email_verified_at` column and the email verification flow.
- Rate limiting on `/api/login`, `/api/register`, and the Google auth endpoints.

### Phase C — Safe account linking `[DESIGN]`

- New endpoints: link Google to an authenticated account, unlink if a second method exists.
- Google callback continues to reject auto-linking.

### Phase D — Security hardening where compatible `[DESIGN]`

- Environment gate on `/api/test/login`.
- Rate limits on remaining auth endpoints.
- Encryption of Google tokens on `users` via a migration.
- Sanctum expiration for **new** tokens only.

### Phase E — Legacy improvements requiring coordination `[DESIGN]`

- Second callback path that returns the token in a JSON body.
- Deprecation window for the `?token=` query parameter.
- `sub`-first Google lookup with email fallback.

Each phase is independently shippable. None requires the next.

---

## 17. Decision table

| # | Decision | Recommended choice | Reason | Production impact | Status |
| --- | --- | --- | --- | --- | --- |
| 1 | User identity model | Option A (extend `users`) | Zero migration risk; matches current schema | None | IMPLEMENTED |
| 2 | Google login preservation | Preserve contract as-is | It is live | None | IMPLEMENTED |
| 3 | Email/password login | Add new endpoints; leave `/api/register` untouched | Additive; no contract change | None on existing users | IMPLEMENTED |
| 4 | Account linking | Explicit, authenticated, no email-only auto-link | Prevent account takeover | None on existing users | DESIGN |
| 5 | Email verification | Required for new password accounts; not retroactive | Prevent fake accounts | None on existing users | DESIGN |
| 6 | Password reset | Two endpoints; standard OTP lifecycle | Needed for usability | None on existing users | IMPLEMENTED (ADR-030) |
| 7 | Sanctum expiration | Apply only to new tokens | Avoid breaking live users | None on existing tokens | DESIGN |
| 8 | Logout behavior | Keep "revoke all tokens"; add per-device logout later | Backward compatible | None | IMPLEMENTED |
| 9 | Per-device sessions | Additive endpoint | Mobile convenience | None | DESIGN |
| 10 | `has_bot_access` / `access_expiry` | Keep as product entitlement, not authentication | Auth ≠ product access | Preserve current Google callback behavior | IMPLEMENTED as-is |
| 11 | Dev test login | Keep, add environment gate later | Safer dev workflow | None if correctly gated | IMPLEMENTED, gate = DESIGN |
| 12 | Google token storage | Encrypt at rest via additive migration | Data-loss protection | Google tokens keep working after migration | DESIGN |
| 13 | Token-in-URL | Introduce a second JSON callback path; deprecate over time | Avoid breaking mobile | Requires coordination | DESIGN |
| 14 | Rate limiting | Add to new endpoints immediately; extend later | Abuse protection | None | PARTIAL — password reset done, others pending |

---

## 18. Final architecture

```
                 ┌──────────────────────────────┐
                 │   Existing Google OAuth      │
                 │   (production, unchanged)    │
                 └───────────────┬──────────────┘
                                 │
                 ┌───────────────┼──────────────┐
                 │               │              │
                 ▼               ▼              ▼
        ┌──────────────┐ ┌──────────────┐ ┌──────────────────┐
        │ Email +      │ │ Password     │ │  Google OAuth    │
        │ Password     │ │ Reset (OTP)  │ │  (new connections│
        │ Login        │ │              │ │   module)        │
        └──────┬───────┘ └──────┬───────┘ └────────┬─────────┘
               │                │                  │
               └────────┬───────┘                  │
                        │                          │
                        ▼                          │
                ┌───────────────┐                  │
                │  User         │                  │
                │  (users table)│                  │
                └───────┬───────┘                  │
                        │                          │
                        ▼                          │
                ┌───────────────┐                  │
                │Sanctum Token  │                  │
                └───────┬───────┘                  │
                        │                          │
         ┌──────────────┼──────────────┐           │
         │              │              │           │
         ▼              ▼              ▼           ▼
    Connections    Workflows     Executions   Connections
    (per user)     (per user)    (per user)   (external account
                                              OAuth — separate
                                              from login)
```

Separation that must remain:

```
Google Identity  ≠  Google Connection
     │                   │
     │                   │
  users.email        connections.user_id
  users.google_id    connections.access_token (encrypted)
```

Google identity authenticates the user. Google Connection authorizes the user's ability to call Gmail, Calendar, Sheets, Docs, Analytics on their behalf.

Email/password login and password reset do not interact with Google Connection.

---

## 19. Unresolved questions

Remaining open questions (design only, no implementation authorized):

- Should a Google-linked user be able to add a password without re-authenticating to Google?
- Should password registration be allowed for emails already present in `users` via Google, or should it always route through the linking protocol?
- What is the exact pre-verification token scope (which endpoints remain callable before email verification)?
- Should the Sanctum expiration be a fixed number of days, or per-client (mobile vs web)?
- Should `?token=` be deprecated via a v2 callback with a hard cutover date, or a long-tail parallel window?
- Should `google_id` mismatches on login be treated as an error or as a silent skip?
- Should `email` case-folding be normalized on write to prevent duplicate-account races?
- Should the master-password test login be disabled in staging as well, or only in production?
- Should rate limits on `/api/login` count the master-password path the same as normal credential failures?

Resolved by implementation:

- ~~What is the password reset flow?~~ — Email OTP, two endpoints. See §8 and ADR-030.
- ~~Does password reset revoke existing sessions?~~ — Yes, all Sanctum tokens. See §8.3.
- ~~Should the reset email use the Laravel default template?~~ — No. A project-owned Blade template is used. See §8.6.

---

## 20. Implementation-deferred items

Nothing below is authorized for implementation by this document without explicit
approval. Items marked **[Phase 9]** are planned for the locked Security Hardening
phase. Items marked **[Phase 10]** are cleanup. Items marked **[Unphased]** require
a future decision.

- New auth endpoints for the register/change-password flows. **[Unphased]**
- Linking and unlinking endpoints. **[Unphased]**
- Additive `email_verified_at` column and verification flow. **[Unphased]**
- Sanctum expiration policy for new tokens. **[Phase 9]**
- Per-device logout endpoint. **[Unphased]**
- Environment gating for `/api/test/login`. **[Phase 9]**
- Rate limiting on `/api/login`, `/api/register`, and Google auth endpoints. **[Phase 9]**
- Encryption of `users.google_access_token` / `users.google_refresh_token`. **[Phase 9]**
- Second Google callback path that returns JSON. **[Unphased]**
- `sub`-first Google lookup with email fallback. **[Unphased]**
- Any change to the current Google callback contract. **[Unphased]**

**Removed from the deferred list** (now implemented):

- Password reset endpoints. Implemented. See §8 and ADR-030.
- Custom password reset email template. Implemented. See §8.6.

**Explicitly rejected:**

- Master OTP for password reset. See ADR-030 §"Explicit non-features".
- Admin-initiated password reset endpoint. See ADR-030 §"Explicit non-features".

---

## 21. Recommended next design batch

The next design batch, subject to explicit authorization, should cover:

1. Email verification flow (columns, endpoint contracts, restricted-token scopes).
2. Account linking and unlinking endpoints (contracts, threat model, rollback).
3. Rate limits on `/api/login`, `/api/register`, and Google auth endpoints (values, scope, storage backend).
4. Encryption migration for `users.google_access_token` / `users.google_refresh_token` (rollout plan, `APP_KEY` stability requirements).
5. Sanctum expiration policy (values, migration strategy for new tokens only).
6. Second Google callback path (parallel operation window, deprecation timeline).

No implementation follows from this document until you explicitly authorize a
specific batch.

---

STATUS:

- §1 Baseline: IMPLEMENTED
- §2 Production compatibility: ACTIVE
- §3 Identity model: IMPLEMENTED (Option A)
- §4 Google preservation: IMPLEMENTED as-is
- §5 Account linking: DESIGN ONLY
- §6 Email/Password: LOGIN IMPLEMENTED, REGISTER/CHANGE DESIGN
- §7 Email verification: DESIGN ONLY
- §8 Password recovery: IMPLEMENTED (ADR-030)
- §9 Token lifecycle: PARTIAL (reset revokes tokens; expiry design)
- §10 Google identity vs connection: IMPLEMENTED
- §11 Entitlements: IMPLEMENTED as-is
- §12 Dev test login: IMPLEMENTED, gate DESIGN
- §13 Rate limiting: PARTIAL (password reset done)
- §14 Account lifecycle: MIXED
- §15 Mobile client: IMPLEMENTED
- §16 Migration strategy: PARTIAL
- §17 Decision table: MIXED
- §18 Architecture diagram: CURRENT
- §19-21: DESIGN

PHASE 9 REMAINS LOCKED
PHASE 10 REMAINS LOCKED

NO IMPLEMENTATION AUTHORIZED BY THIS DOCUMENT
