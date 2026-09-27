# 07 — Authentication Architecture

Status: DESIGN ONLY. No implementation authorized.

Scope: additive design for supporting Google OAuth (existing, production) and Email/Password (new) against the same application-level `User` and the same Sanctum bearer-token model.

Version: 1.0.0
Last updated: pre-implementation design.

Phase 9 (Security Hardening) remains LOCKED.
Phase 10 (Legacy Cleanup) remains LOCKED.

---

## 1. Current architecture (baseline)

Authentication today is Google OAuth via Socialite, followed by Sanctum token issuance.

```
Google OAuth (Socialite)
        ↓
User (resolved by email)
        ↓
Sanctum personal access token
        ↓
Mobile / Web API
        ↓
Connections / Workflows / Triggers / Steps / Executions
```

Registered authentication-related routes (verified):

- `POST /api/register` — creates or updates a user; does NOT issue a token.
- `GET  /api/auth/google/redirect` — begins Google login via Socialite.
- `GET  /api/auth/google/redirect-google` — begins Google login with the legacy scope list.
- `GET  /api/auth/google/callback` — completes Google login; redirects to the frontend with `?token=<sanctum-token>`.
- `POST /api/logout` — revokes every Sanctum token owned by the authenticated user.
- `POST /api/account/deactivate` — soft-deletes the user and revokes all tokens.
- `POST /api/test/login` — dev-only; shared master password; issues a token for any existing email.

Ownership model: every user-owned resource is scoped by `user_id` at the repository layer. No cross-user access exists in the supplied code.

---

## 2. Production compatibility contract

This is the hard constraint. Everything in Phase A (design) must respect it.

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

- Additive routes (e.g. `POST /api/auth/login`, `POST /api/auth/forgot-password`).
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

## 3. Identity model decision

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

### Recommendation for THIS project

**Option A.** Reasons, ranked by the priorities stated in the design brief:

1. Production safety — zero disruption to the live Google login.
2. Backward compatibility — no changes to controllers, routes, tests, or the `User` model contract.
3. Minimal architectural disruption — an additive column is a smaller change than a new table with backfill.
4. Security — email remains the sole identity key today, which is a manageable scope until a linking protocol is defined.
5. Extensibility — if a future batch needs multiple providers beyond Google and password, `auth_identities` can be introduced then, with a proper migration plan and no live user impacted at that point.

Option A does not preclude Option B later. It defers the abstraction until there is a real need for it.

---

## 4. Google preservation strategy

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

### C) Safe future improvements

- Add `throttle:` middleware to the callback and redirect endpoints.
- Encrypt `users.google_access_token` / `users.google_refresh_token` via a model cast. This is additive: a migration reads the plaintext values and writes encrypted ones in place. Existing tokens keep working after the cast is enabled, provided the migration is run under the same `APP_KEY`.
- Add `sub`-first lookup with `email` fallback, but only after backfilling `google_id` for existing rows. Contract at the HTTP boundary unchanged.
- Introduce an additive second callback path (e.g. `/api/auth/google/callback-v2`) that returns the token in a JSON body, and gradually move mobile clients to it while the legacy path remains live.

### D) Breaking change risk

- Removing `?token=<token>` from the redirect would break live mobile/web consumers. Deferred — requires compatibility migration.
- Changing the redirect target `https://www.aibrooklyn.net` would break live consumers. Deferred — requires coordination.
- Switching identity resolution from `email` to `sub` without a fallback would break existing users whose `google_id` is null. Deferred — requires a backfill and a mixed-mode lookup.

Nothing in this section is approved for implementation.

---

## 5. Google account + password account linking protocol

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

## 6. Email/Password authentication design

### 6.1 Registration

Two options exist today: the live `POST /api/register` route and any future additive endpoint.

Current endpoint:

- `POST /api/register` — creates or updates a user. Does NOT issue a token. Validation: `name`, `email`, `password` (min 6), `st_num` (nullable), `access_expiry`.
- Behavior when email exists: updates `access_expiry` and `has_bot_access`. Does not touch the password.

Design decision required:

- Option 1 — Redefine `/api/register` to also issue a token. This changes the current production contract for any client relying on it. Not recommended.
- Option 2 — Keep `/api/register` as-is, and add a new endpoint (working name `POST /api/auth/register`), which:
  - Creates a user only when the email is new.
  - Hashes the password.
  - Issues a Sanctum token.
  - Returns the token and user in a JSON body.
  - Requires email verification before the account can authenticate beyond a limited scope.
  - Does not affect existing accounts.
- **Recommended:** Option 2. Additive.

### 6.2 Login

New endpoint (working name `POST /api/auth/login`):

- Input: `email`, `password`.
- Validates credentials via `Hash::check`.
- Verifies account status: not soft-deleted, and (if email verification is required) `email_verified_at` set.
- Issues a Sanctum token with the standard mechanism.
- Returns a JSON body: `{ "token": "...", "user": { ... } }`.
- Rate-limited (see §14).
- On failure, returns a generic error without revealing whether the email exists.

### 6.3 Password change

Authenticated endpoint (working name `POST /api/auth/password/change`):

- Input: `current_password`, `new_password`.
- Verifies current password before changing.
- Hashes and stores the new password.
- Optionally revokes all other tokens (`tokens()->where('id', '!=', $currentTokenId)->delete()`).

### 6.4 Relationship to `/api/register`

`/api/register` remains live and unchanged. The new auth endpoint does not replace it.

---

## 7. Email verification

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

## 8. Password recovery

New endpoints (working names):

- `POST /api/auth/password/forgot` — accepts `email`. Always returns success. Sends a reset link if the email exists. Rate-limited.
- `POST /api/auth/password/reset` — accepts `token`, `email`, `password`, `password_confirmation`.

Reset token lifecycle:

- Single-use.
- Time-limited (Laravel default of 60 minutes is acceptable).
- Invalidated on successful reset.
- Stored in the standard Laravel `password_reset_tokens` table.
- A successful reset revokes all existing tokens for the user (optional but recommended).

No implementation in this phase.

---

## 9. Sanctum token lifecycle

Current: personal access tokens, no abilities, no expiration (`'expiration' => null`), no per-device tracking.

Design decisions:

- Token creation: unchanged for Google login. The new email/password login uses the same `createToken` mechanism.
- Expiration: currently null. Any change to expiration must NOT apply retroactively to already-issued tokens. When expiry is introduced, it must apply only to newly issued tokens.
- Revocation: logout revokes all tokens (unchanged). A future per-device logout endpoint would revoke a single token and is additive.
- Account deactivation: revokes all tokens (unchanged).
- Multiple devices: supported today implicitly. No change.
- Per-device logout: additive endpoint (working name `POST /api/auth/logout/current`) that deletes only the current token. `POST /api/logout` continues to delete all.
- Password change: revoke all other tokens. The active token may be retained or rotated. Recommended: retain the active token, revoke others.
- Password reset: revoke all tokens. The user must re-authenticate.
- Compromised token handling: no endpoint today. A future "revoke all tokens" endpoint is additive and does not change any existing contract.

Safety rule for the migration: **no change to token policy applies to tokens already issued.** Any expiration policy applies only to tokens created after the change. This is the only way to add expiration without logging out every live user.

---

## 10. Google identity vs Google connection

These are separate concepts and must remain separate.

**Google Identity**
Google OAuth → User authentication → Sanctum token.

Owns: `users.google_id`, `users.google_access_token`, `users.google_refresh_token`, `users.google_token_expires_at`.

**Google Connection**
Connections module OAuth → `Connection` row owned by a `user_id` → Actions (Gmail, Calendar, Sheets, Docs, Analytics).

Owns: `connections` table and its encrypted credentials. Not `users.google_*`.

Email/password authentication does not touch Connections. Ownership semantics are unchanged: a user who owns a Connection while logged in via Google still owns it when logged in via email/password, because the `user_id` is the same.

---

## 11. `has_bot_access` and `access_expiry`

These are product-level access entitlements, not authentication credentials.

- `has_bot_access` — whether the user is entitled to use the bot.
- `access_expiry` — the date until which the entitlement is valid.

Current uses:

- Set during `/api/register` from `today() < access_expiry`.
- Checked in the Google login callback: users with `has_bot_access == false` receive `token=null`.

Recommended treatment:

- Authentication (Google or email/password) issues a token if the user exists and is not soft-deleted.
- Product access is enforced at request time via middleware or policy checks that read `has_bot_access` and `access_expiry`.
- The current Google callback behavior for `has_bot_access == false` is preserved for backward compatibility. It is not a model to extend to email/password login, which should authenticate normally and let authorization gate access.
- This keeps authentication ≠ product access.

No implementation in this phase.

---

## 12. Development / test login

Current: `POST /api/test/login` uses a shared master password to issue a token for any existing email. Marked dev-only in comments. Not gated by environment.

Design decision:

- Keep the endpoint in development and test environments.
- Gate it explicitly by environment (e.g. `APP_ENV !== 'production'`) via middleware or route registration.
- Do not remove it and do not change its current test behavior.
- Treat as a Phase 9 candidate for environment gating.

No changes in this phase.

---

## 13. Rate limiting / abuse protection

Current: none on the routes inspected.

Design:

- `POST /api/register` — rate limit by IP.
- `POST /api/auth/login` — rate limit by IP and by email.
- `POST /api/auth/password/forgot` — rate limit by IP and by email.
- `POST /api/auth/password/reset` — rate limit by IP.
- `GET /api/auth/google/redirect` and `/api/auth/google/callback` — rate limit by IP.
- `POST /api/test/login` — rate limit by IP; environment-gated.

No implementation in this phase.

---

## 14. Account lifecycle

- **Active** — normal.
- **Unverified** (new email/password users only) — authenticated with restricted token until verification completes.
- **Suspended** — not currently modelled. Do not add unless required by a future batch.
- **Soft-deleted** — `deleted_at` set. Login rejected. Tokens revoked. Existing behavior preserved.
- **Restored** — no user-restore endpoint exists. Not in scope.
- **Password changed** — other tokens revoked.
- **Google linked** — `google_id` written after authentication.
- **Google unlinked** — only allowed if the account has another primary authentication method.

---

## 15. Mobile client contract

Current (Google OAuth only):

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

Future (Email/Password added):

```
Mobile
  ↓
POST /api/auth/login with email + password
  ↓
Backend returns { token, user } in a JSON body
  ↓
Mobile stores token
```

Both flows yield the same kind of token and the same `Authorization: Bearer <token>` usage afterward. No existing mobile behavior changes.

---

## 16. Migration strategy

### Phase A — Baseline preserved

- No changes.
- Google login remains the sole production authentication.
- Existing users, tokens, and connections are unaffected.

### Phase B — Add Email/Password capability (additive)

- New endpoints: register, login, password change.
- Additive columns: `email_verified_at` (nullable).
- Additive notifications: verification email.
- Existing Google login untouched.
- Existing `/api/register` untouched.
- Rollback: drop the new endpoints and columns; existing users unaffected.

### Phase C — Safe account linking

- New endpoints: link Google to an authenticated account, unlink if a second method exists.
- Google callback continues to reject auto-linking.
- Rollback: remove the linking endpoints; no existing state depends on them.

### Phase D — Security hardening where compatible

- Environment gate on `/api/test/login`.
- Rate limits on new endpoints and on the Google flow.
- Encryption of Google tokens on `users` via a migration.
- Sanctum expiration for **new** tokens only.
- Rollback: each item is reversible independently.

### Phase E — Legacy improvements requiring coordination

- Second callback path that returns the token in a JSON body.
- Deprecation window for the `?token=` query parameter.
- `sub`-first Google lookup with email fallback.
- Rollback: the legacy path remains live until consumers migrate.

Each phase is independently shippable. None requires the next.

---

## 17. Decision table

| # | Decision | Recommended choice | Reason | Production impact |
| --- | --- | --- | --- | --- |
| 1 | User identity model | Option A (extend `users`) | Zero migration risk; matches current schema | None |
| 2 | Google login preservation | Preserve contract as-is | It is live | None |
| 3 | Email/password login | Add new endpoints; leave `/api/register` untouched | Additive; no contract change | None on existing users |
| 4 | Account linking | Explicit, authenticated, no email-only auto-link | Prevent account takeover | None on existing users |
| 5 | Email verification | Required for new password accounts; not retroactive | Prevent fake accounts | None on existing users |
| 6 | Password reset | New endpoints; token lifecycle standard | Needed for usability | None |
| 7 | Sanctum expiration | Apply only to new tokens | Avoid breaking live users | None on existing tokens |
| 8 | Logout behavior | Keep "revoke all tokens"; add per-device logout later | Backward compatible | None |
| 9 | Per-device sessions | Additive endpoint | Mobile convenience | None |
| 10 | `has_bot_access` / `access_expiry` | Keep as product entitlement, not authentication | Auth ≠ product access | Preserve current Google callback behavior |
| 11 | Dev test login | Keep, add environment gate later | Safer dev workflow | None if correctly gated |
| 12 | Google token storage | Encrypt at rest via additive migration | Data-loss protection | Google tokens keep working after migration |
| 13 | Token-in-URL | Introduce a second JSON callback path; deprecate over time | Avoid breaking mobile | Requires coordination |
| 14 | Rate limiting | Add to new endpoints immediately; extend later | Abuse protection | None |

---

## 18. Final architecture

```
                 ┌──────────────────────────────┐
                 │   Existing Google OAuth      │
                 │   (production, unchanged)    │
                 └───────────────┬──────────────┘
                                 │
                                 │
                 ┌───────────────▼──────────────┐
                 │   NEW Email + Password       │
                 │   (additive endpoints)       │
                 └───────────────┬──────────────┘
                                 │
                                 │
                        ┌────────▼────────┐
                        │      User       │
                        │  (users table)  │
                        └────────┬────────┘
                                 │
                        ┌────────▼────────┐
                        │  Sanctum Token  │
                        └────────┬────────┘
                                 │
              ┌──────────────────┼──────────────────┐
              │                  │                  │
              ▼                  ▼                  ▼
        Connections         Workflows          Executions
        (per user)          (per user)         (per user)
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

---

## 19. Unresolved questions

- Should a Google-linked user be able to add a password without re-authenticating to Google?
- Should password registration be allowed for emails already present in `users` via Google, or should it always route through the linking protocol?
- What is the exact pre-verification token scope (which endpoints remain callable before email verification)?
- Should the Sanctum expiration be a fixed number of days, or per-client (mobile vs web)?
- Should `?token=` be deprecated via a v2 callback with a hard cutover date, or a long-tail parallel window?
- Should `google_id` mismatches on login be treated as an error or as a silent skip?
- Should `email` case-folding be normalized on write to prevent duplicate-account races?
- Should the master-password test login be disabled in staging as well, or only in production?

---

## 20. Implementation-deferred items

Nothing below is authorized for implementation by this document.

- New auth endpoints (register, login, password change, forgot, reset, verify).
- Linking and unlinking endpoints.
- Additive `email_verified_at` column.
- Sanctum expiration policy for new tokens.
- Per-device logout endpoint.
- Environment gating for `/api/test/login`.
- Rate limiting on existing and new auth endpoints.
- Encryption of `users.google_access_token` / `users.google_refresh_token`.
- Second Google callback path that returns JSON.
- `sub`-first Google lookup with email fallback.
- Any change to the current Google callback contract.

---

## 21. Recommended next design batch

The next design batch, subject to explicit authorization, should cover:

1. Exact HTTP contract for the new email/password endpoints (path, method, request, response, error envelope).
2. Exact HTTP contract for the linking endpoints.
3. Email template and verification-link strategy.
4. Rate-limit values and scopes.
5. Migration plan for `email_verified_at` and any additive columns.
6. Rollback plan for each additive change.
7. Threat model for the linking protocol.

No implementation follows from this document until you explicitly authorize a specific batch.

---

STATUS:

DESIGN ONLY
NO IMPLEMENTATION AUTHORIZED
PHASE 9 REMAINS LOCKED
PHASE 10 REMAINS LOCKED
