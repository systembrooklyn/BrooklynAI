# 08 — Production Deployment

Operational reference for running this Laravel API in production.

Target: any Linux server capable of running PHP 8.2+, MySQL 8+, and a web server fronting PHP-FPM. DigitalOcean App Platform is the current deployment target. The requirements below are not vendor-specific.

---

## 1. Required processes

| Process | Purpose | Required |
| --- | --- | --- |
| PHP-FPM + web server | Serves the HTTP API | Yes |
| External clock (Google Apps Script) | Calls the internal scheduler tick endpoint every ~5 minutes | Yes |
| Laravel Scheduler (`schedule:run`) | **Not required for the new scheduler path.** Retained for CLI/manual use. | Optional |
| Queue Worker | **Not required for the new scheduler path.** Retained for legacy/manual flows. | Optional |

Without the external clock calling the internal tick endpoint, no Gmail polling happens and no scheduled workflows fire.

---

## 2. External clock — Google Apps Script

The production scheduler is driven by an external clock. The intended mechanism is Google Apps Script with a time-driven trigger.

Apps Script responsibilities:

1. Wake up on schedule.
2. POST to the internal scheduler tick endpoint.
3. Authenticate with the bearer token.
4. Exit.

Apps Script must NOT contain workflow logic, Gmail logic, or any integration-specific behavior.

### Apps Script configuration

- Store the tick URL and bearer token in `PropertiesService.getScriptProperties()`.
- Never put the token in a Google Sheet, in the script source, or in logs.
- Install a time-driven trigger at approximately 5-minute cadence.
- Run as the script owner.

### Apps Script constraints

- Consumer accounts have a daily trigger runtime quota (~90 minutes/day). A 5-minute cadence that takes more than ~18 seconds per invocation will exhaust this budget.
- Workspace accounts have a higher quota (~6 hours/day).
- `UrlFetchApp.fetch` has a request timeout ceiling (~60 seconds, community-verified).
- Time-driven triggers may fire late. The system tolerates this because `next_poll_at` is computed from the actual execution time, not the scheduled time.

---

## 3. Internal Scheduler Tick Endpoint

```
POST {APP_URL}/api/internal/scheduler/tick
Authorization: Bearer ${INTERNAL_SCHEDULER_TOKEN}
```

- Method: `POST` only. Other methods return 405.
- Auth: dedicated bearer token, constant-time comparison. Not Sanctum.
- Rate limit: `INTERNAL_SCHEDULER_RATE_LIMIT` per 60-second window (default 20).
- Global lock: `scheduler-tick-global` via `Cache::lock`, TTL `INTERNAL_SCHEDULER_LOCK_SECONDS` (default 60). If the lock is held, the tick returns `lock_held: true` without processing.
- Response: `{ ok, processed, executed, skipped, failed, lock_held }`.
- Errors: 401 (unauthorized), 429 (rate limited), 503 (misconfigured server).

The endpoint executes synchronously. It must complete within the platform HTTP request timeout. Keep `INTERNAL_SCHEDULER_BATCH_SIZE` conservative (default 50) until load characteristics are measured.

The endpoint does **not** require a queue worker.

---

## 4. Queue Worker (legacy)

A queue worker is **not required** for the new scheduler path. The `RunWorkflowJob` class is retained in the repository for legacy and manual flows but is not dispatched by the new scheduler.

If a future deployment re-enables async execution:

```
php artisan queue:work --tries=3 --timeout=120 --sleep=3
```

This can be managed by systemd, supervisor, or a similar process manager. It is not required for the current MVP deployment.

---

## 5. Cache and queue store requirements

- `CACHE_STORE` must be a shared, persistent backend (`database` or `redis`). `array` is never acceptable. `file` is acceptable only for single-instance deployments.
- `QUEUE_CONNECTION` must be a persistent backend (`database` or `redis`) if the queue is used at all. `sync` is acceptable only for local development.

The global tick lock depends on the cache store being cross-process safe.

---

## 6. Environment variables

Already required by the existing project:

- `APP_KEY`, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`, `GOOGLE_CONNECTIONS_REDIRECT_URI`
- `FRONTEND_CONNECTIONS_REDIRECT`
- `LOGIN_MASTER_PASSWORD` (optional)

Required for the scheduler:

- `INTERNAL_SCHEDULER_TOKEN` — high-entropy bearer token (required).
- `INTERNAL_SCHEDULER_RATE_LIMIT` — default 20.
- `INTERNAL_SCHEDULER_LOCK_SECONDS` — default 60.
- `INTERNAL_SCHEDULER_BATCH_SIZE` — default 50.

Persistent storage:

- `CACHE_STORE` — must be shared and persistent.
- `QUEUE_CONNECTION` — persistent backend if the queue is used.

---

## 7. Gmail poll cadence

Each Gmail trigger has an `interval_minutes`. The external clock runs every ~5 minutes. The scheduler tick queries only due triggers via `workflow_triggers.next_poll_at`.

After a successful Gmail poll, the strategy sets `next_poll_at = now + interval_minutes`.

Default when `interval_minutes` is null: 5 minutes.

Paused and draft workflows are never polled.

---

## 8. Self-email protection

The Gmail strategy inspects the `From:` header of every detected message. If the sender address (case-insensitive, including display-name form) matches the workflow connection's own email, the message is skipped and logged.

Always on. No configuration.

---

## 9. Delivery semantics — at-least-once

The Gmail trigger is at-least-once, not exactly-once.

- Idempotency keys (`gmail:{workflowId}:{messageId}`) reduce duplicate execution.
- The executions unique constraint (`workflow_id, idempotency_key`) prevents duplicate row creation.
- The cursor is advanced only after messages have been processed or idempotently skipped.
- If the tick is interrupted mid-batch, unprocessed messages will be re-fetched on the next tick within the overlap window.

None of these guarantees exactly-once external side effects. If the Gmail API accepts a send and the response is lost, a retry will send the message again. This is an inherent limitation of the Gmail API (no idempotency key on `messages.send`).

---

## 10. Locking

| Lock | Key | Purpose | TTL |
| --- | --- | --- | --- |
| Global tick lock | `scheduler-tick-global` | Prevents overlapping ticks | `INTERNAL_SCHEDULER_LOCK_SECONDS` (default 60) |
| Per-trigger lock | `poll-trigger:{id}` | Prevents concurrent poller processes for the same trigger | 90 seconds |
| Idempotency unique index | `executions(workflow_id, idempotency_key)` | Prevents duplicate executions | permanent |

Requires `CACHE_STORE` to be shared and persistent.

---

## 11. Failed-execution recovery

Recovery for failed poll executions is provided by `RecoverFailedPollExecutionsCommand`. This is a Batch 5 concern for generalization and stuck-`running` sweep.

Current state:

- Scans failed executions with `trigger_source='poll'`, `status='failed'`, `retry_attempts < 3`, `updated_at < now - 10 min`.
- Claims via CAS on `retry_attempts`.
- Dispatches a new job with `#r{n}` idempotency key and `retry_of_id` lineage.

Under the new scheduler path, this command is not required for the primary flow but remains available for compatibility.

---

## 12. Deploy checklist

```text
[ ] APP_KEY set and stable across deploys
[ ] .env production values populated
[ ] CACHE_STORE set to persistent shared store
[ ] QUEUE_CONNECTION set to persistent store (if queue is used)
[ ] composer install --no-dev --optimize-autoloader
[ ] php artisan migrate --force
[ ] php artisan config:cache
[ ] php artisan route:cache
[ ] storage/ and bootstrap/cache/ writable
[ ] INTERNAL_SCHEDULER_TOKEN set in production env
[ ] Google Apps Script configured to call the tick endpoint with the token
[ ] Google Cloud Console: OAuth redirect URIs include GOOGLE_CONNECTIONS_REDIRECT_URI
[ ] FRONTEND_CONNECTIONS_REDIRECT configured
```

---

## 13. Post-deployment smoke test

1. `curl -X POST {APP_URL}/api/internal/scheduler/tick` with no token → 401.
2. Same with wrong token → 401.
3. Same with correct token → 200 with `{ ok: true, ... }`.
4. `GET /api/catalog` with a valid user token → 200.
5. `GET /api/connections` with a valid user token → 200.
6. Complete a real workflow: create → trigger → step → activate.
7. Send an external email to a connected Gmail account.
8. Within 1–2 ticks, verify a new execution appears in `GET /api/workflows/{id}/executions`.

---

## 14. Known limitations

- DigitalOcean App Platform does not allow multiple long-lived processes in a single Web Service container. The external clock approach (Apps Script) sidesteps this.
- Apps Script is a single point of failure for the scheduler. If the trigger is disabled or the script is deleted, automation stops.
- Consumer Apps Script accounts cannot sustain more than ~18 seconds per tick. Long ticks exhaust the daily quota.
- The new scheduler path is synchronous. Very large batches will exceed the platform HTTP request timeout.
- Stuck `running` executions are not recovered (Batch 5).
- `next_poll_at` index optimization is not yet applied (Batch 6).

---

## 15. Contact points for operational rotation

- `INTERNAL_SCHEDULER_TOKEN` — rotate in both the Laravel `.env` and the Apps Script `ScriptProperties` simultaneously.
- Google OAuth credentials — rotate via Google Cloud Console.
- `APP_KEY` — must remain stable across deploys. Rotating it invalidates encrypted credentials in `connections`.
