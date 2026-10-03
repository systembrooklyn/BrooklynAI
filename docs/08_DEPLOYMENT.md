# 08 — Production Deployment

Operational reference for running this Laravel API in production.

Target: any Linux server capable of running PHP 8.2+, MySQL 8+, and a web server fronting PHP-FPM. DigitalOcean App Platform is the current deployment target. Requirements below are not vendor-specific.

---

## 1. Required processes

| Process                             | Purpose                                                                    | Required |
| ----------------------------------- | -------------------------------------------------------------------------- | -------- |
| PHP-FPM + web server                | Serves the HTTP API                                                        | Yes      |
| External clock (Google Apps Script) | Calls the internal scheduler tick endpoint every 5 minutes                 | Yes      |
| Laravel Scheduler (`schedule:run`)  | Not required for the new scheduler path. Retained for CLI/manual use.      | Optional |
| Queue Worker                        | Not required for the new scheduler path. Retained for legacy/manual flows. | Optional |

Without the external clock, no Gmail polling happens and no scheduled workflows fire.

---

## 2. Production environment

- Production base domain: `https://sea-turtle-app-vshwt.ondigitalocean.app`
- Production scheduler endpoint: `https://sea-turtle-app-vshwt.ondigitalocean.app/api/internal/scheduler/tick`
- Local Laravel server: `http://localhost:8000`
- Local scheduler endpoint: `http://localhost:8000/api/internal/scheduler/tick`

---

## 3. External clock — Google Apps Script

The production scheduler is driven by Google Apps Script installed as a time-driven trigger.

Apps Script responsibilities:

1. Wake up on schedule (every 5 minutes).
2. POST to the internal scheduler tick endpoint.
3. Authenticate with the bearer token.
4. Exit.

Apps Script MUST NOT contain workflow logic, Gmail logic, or any integration-specific behavior.

### Apps Script Script Properties

Two script properties must be set in Apps Script (**Project Settings → Script properties**):

| Property                      | Value                                                                                                                                                     |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `INTERNAL_SCHEDULER_TICK_URL` | `https://sea-turtle-app-vshwt.ondigitalocean.app/api/internal/scheduler/tick` (production) or `http://localhost:8000/api/internal/scheduler/tick` (local) |
| `INTERNAL_SCHEDULER_TOKEN`    | Same value as the Laravel `INTERNAL_SCHEDULER_TOKEN` env variable                                                                                         |

**Never** put the token in script source, in a Google Sheet, or in logs.

### Apps Script code

```javascript
/**
 * Production scheduler clock.
 *
 * Calls POST /api/internal/scheduler/tick every 5 minutes.
 * Contains NO workflow logic, NO Gmail logic, NO business logic.
 *
 * Required Script Properties (Project Settings → Script properties):
 *   INTERNAL_SCHEDULER_TICK_URL = https://<your-app-url>/api/internal/scheduler/tick
 *   INTERNAL_SCHEDULER_TOKEN    = <same value as Laravel INTERNAL_SCHEDULER_TOKEN>
 */

function tick() {
  const props = PropertiesService.getScriptProperties();
  const url = props.getProperty('INTERNAL_SCHEDULER_TICK_URL');
  const token = props.getProperty('INTERNAL_SCHEDULER_TOKEN');

  if (!url || !token) {
    console.warn('Missing INTERNAL_SCHEDULER_TICK_URL or INTERNAL_SCHEDULER_TOKEN');
    return;
  }

  const options = {
    method: 'post',
    contentType: 'application/json',
    headers: {
      Authorization: 'Bearer ' + token,
    },
    muteHttpExceptions: true,
  };

  let response;
  try {
    response = UrlFetchApp.fetch(url, options);
  } catch (err) {
    // Network / DNS / TLS failure. Do not retry aggressively; the next
    // scheduled tick will fire in 5 minutes. The Laravel global lock
    // already protects against overlapping ticks.
    console.warn('Scheduler tick network error: ' + err.message);
    return;
  }

  const code = response.getResponseCode();
  const body = response.getContentText();

  if (code === 200) {
    // Body contains only aggregate counts; safe to log a truncated copy.
    console.log('Scheduler tick OK: ' + body.substring(0, 200));
    return;
  }

  if (code === 401) {
    console.warn('Scheduler tick unauthorized (401). Token mismatch.');
    return;
  }

  if (code === 429) {
    console.warn('Scheduler tick rate limited (429). Backing off.');
    return;
  }

  if (code === 503) {
    console.warn('Scheduler tick server misconfigured (503). Verify Laravel env.');
    return;
  }

  console.warn('Scheduler tick unexpected response: ' + code + ' ' + body.substring(0, 200));
}

/**
 * Run this once from the editor to install or reinstall the 5-minute trigger.
 * Removes any existing tick triggers before creating a new one to avoid
 * duplicate scheduler triggers.
 */
function installTrigger() {
  ScriptApp.getProjectTriggers().forEach(function (t) {
    if (t.getHandlerFunction() === 'tick') {
      ScriptApp.deleteTrigger(t);
    }
  });

  ScriptApp.newTrigger('tick')
    .timeBased()
    .everyMinutes(5)
    .create();

  console.log('Installed 5-minute trigger for tick()');
}
```

### Apps Script setup steps

1. Open https://script.google.com → New project.
2. Paste the code above.
3. Project Settings → Script properties → Add:
   - `INTERNAL_SCHEDULER_TICK_URL` = `https://sea-turtle-app-vshwt.ondigitalocean.app/api/internal/scheduler/tick`
   - `INTERNAL_SCHEDULER_TOKEN` = same value as Laravel env.
4. Run `installTrigger` once from the editor and authorize.
5. Confirm the trigger exists in the Triggers sidebar: `tick` — time-driven — every 5 minutes.
6. Watch executions: check `View → Executions` — expect one entry every ~5 minutes.

### Apps Script constraints

- Consumer accounts have a daily trigger runtime quota (~90 minutes/day). A 5-minute cadence that takes more than ~18 seconds per invocation will exhaust this budget.
- Workspace accounts have a higher quota (~6 hours/day).
- `UrlFetchApp.fetch` has a request timeout ceiling (~60 seconds).
- Time-driven triggers may fire late (up to 15 minutes on the first run after install, then 1–3 minutes of drift regularly). `next_poll_at` is computed from the actual execution time, so drift is tolerated.

---

## 4. Internal Scheduler Tick Endpoint

```
POST {APP_URL}/api/internal/scheduler/tick
Authorization: Bearer ${INTERNAL_SCHEDULER_TOKEN}
```

- Method: `POST` only. Other methods return 405.
- Auth: dedicated bearer token, constant-time comparison. Not Sanctum.
- Rate limit: `INTERNAL_SCHEDULER_RATE_LIMIT` per 60-second window.
- Global lock: `scheduler-tick-global` via `Cache::lock`, TTL `INTERNAL_SCHEDULER_LOCK_SECONDS`. If the lock is held, the tick returns `lock_held: true` without processing.
- Stale-running sweep runs before the coordinator on every tick.
- Response: `{ ok, processed, executed, skipped, failed, lock_held }`.
- Errors: 401 (unauthorized), 429 (rate limited), 503 (misconfigured server).

The endpoint executes synchronously. It must complete within the platform HTTP request timeout. Keep `INTERNAL_SCHEDULER_BATCH_SIZE` conservative until load characteristics are measured.

The endpoint does **not** require a queue worker.

---

## 5. Queue Worker (legacy)

A queue worker is **not required** for the new scheduler path. `RunWorkflowJob` is retained in the repository for legacy/manual flows but is not dispatched by the new scheduler.

If a future deployment re-enables async execution:

```
php artisan queue:work --tries=3 --timeout=120 --sleep=3
```

Managed by systemd, supervisor, or a similar process manager. Not required for the current MVP deployment.

---

## 6. Cache and queue store requirements

- `CACHE_STORE` must be a shared, persistent backend (`database` or `redis`). `array` is never acceptable. `file` is acceptable only for single-instance deployments where the scheduler and the web app share the same filesystem.
- `QUEUE_CONNECTION` must be a persistent backend (`database` or `redis`) if the queue is used. `sync` is acceptable only for local development.

The global tick lock and `ShouldBeUnique` behavior depend on the cache store being cross-process safe.

---

## 7. Environment variables

Already required by the existing project:

- `APP_KEY`, `APP_URL`, `APP_ENV=production`, `APP_DEBUG=false`
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`
- `GOOGLE_REDIRECT_URI`, `GOOGLE_CONNECTIONS_REDIRECT_URI`
- `FRONTEND_CONNECTIONS_REDIRECT`
- `LOGIN_MASTER_PASSWORD` (optional)

Required for the scheduler:

- `INTERNAL_SCHEDULER_TOKEN` — high-entropy bearer token (required).
- `INTERNAL_SCHEDULER_BATCH_SIZE` — max due triggers per tick.
- `INTERNAL_SCHEDULER_RATE_LIMIT` — requests per 60-second window.
- `INTERNAL_SCHEDULER_LOCK_SECONDS` — global lock TTL.
- `INTERNAL_SCHEDULER_STALE_RUNNING_MINUTES` — stale-running recovery threshold.

Persistent storage:

- `CACHE_STORE` — must be shared and persistent.
- `QUEUE_CONNECTION` — persistent backend if the queue is used.

---

## 8. Gmail poll cadence

Each Gmail trigger has an `interval_minutes`. The external clock runs every ~5 minutes. The scheduler tick queries only due triggers via `workflow_triggers.next_poll_at`.

After a successful Gmail poll, the strategy sets `next_poll_at = now + interval_minutes`.

Default when `interval_minutes` is null: 5 minutes.

Paused and draft workflows are never polled.

### First-tick cursor initialization

For a newly activated Gmail polling trigger:

- `poll_cursor` is `NULL`.
- The first processing initializes `poll_cursor = now` and does **not** fetch historical Gmail messages.
- This is intentional: prevents processing historical emails on activation.
- Subsequent ticks fetch messages using `after = cursor - OVERLAP_SECONDS`.
- Current `OVERLAP_SECONDS = 360` (6 minutes = 5-minute tick + 1-minute margin).

The 5-minute value is the scheduler polling interval, **not** an exact processing SLA. Delivery is at-least-once.

---

## 9. Gmail cursor overlap

`GmailPollStrategy::OVERLAP_SECONDS = 360` and `PollGmailCommand::OVERLAP_SECONDS = 360`.

The overlap window protects against cursor non-advancement on tick failure. The cursor is only advanced at the end of `process()`, so a crashed tick can leave messages unprocessed. The next tick re-fetches from `cursor - OVERLAP_SECONDS`. Under a 5-minute external tick cadence, an overlap of at least the tick interval is required; 360s provides that plus a 1-minute margin. Duplicate fetches are idempotent via `gmail:{workflowId}:{messageId}`.

---

## 10. Self-email protection

The Gmail strategy inspects the `From:` header of every detected message. If the sender address (case-insensitive, including display-name form) matches the workflow connection's own email, the message is skipped and logged.

Always on. No configuration.

---

## 11. Delivery semantics — at-least-once

The Gmail trigger is at-least-once, not exactly-once.

- Idempotency keys (`gmail:{workflowId}:{messageId}`) reduce duplicate execution.
- The executions unique constraint (`workflow_id, idempotency_key`) prevents duplicate row creation.
- The cursor is advanced only after messages have been processed or idempotently skipped.
- If the tick is interrupted mid-batch, unprocessed messages will be re-fetched on the next tick within the overlap window.

None of these guarantees exactly-once external side effects. If the Gmail API accepts a send and the response is lost, a retry will send the message again. This is an inherent limitation of the Gmail API.

---

## 12. Locking

| Lock                     | Key                                        | Purpose                                                   | TTL                               |
| ------------------------ | ------------------------------------------ | --------------------------------------------------------- | --------------------------------- |
| Global tick lock         | `scheduler-tick-global`                    | Prevents overlapping ticks                                | `INTERNAL_SCHEDULER_LOCK_SECONDS` |
| Per-trigger lock         | `poll-trigger:{id}`                        | Prevents concurrent poller processes for the same trigger | 90 seconds                        |
| Idempotency unique index | `executions(workflow_id, idempotency_key)` | Prevents duplicate executions                             | permanent                         |

Requires `CACHE_STORE` to be shared and persistent.

---

## 13. Stuck-running recovery

`SchedulerTickService::tick()` calls `ExecutionRepository::markStaleRunningAsFailed()` before running the coordinator. Any execution whose `status = 'running'` and `started_at` is older than `INTERNAL_SCHEDULER_STALE_RUNNING_MINUTES` is marked `failed` with a diagnostic `error_message` and `finished_at = now`.

This closes the `hasInProgressForWorkflow()` gap: a tick crash mid-execution would otherwise block the workflow indefinitely.

The sweep is idempotent. The 5-minute threshold is safely longer than the platform HTTP request timeout.

---

## 14. Failed-execution recovery (legacy CLI)

`RecoverFailedPollExecutionsCommand` remains available as a CLI tool for manual use:

- Scans failed executions with `trigger_source='poll'`, `status='failed'`, `retry_attempts < 3`, `updated_at < now - 10 min`.
- Claims via CAS on `retry_attempts`.
- Dispatches a new job with `#r{n}` idempotency key and `retry_of_id` lineage.

Under the new scheduler path, this command is not required for the primary flow.

---

## 15. Gmail integration — reconnect requirement

The Gmail integration was completed after the initial scheduler hardening work.
The Gmail capability now requests five OAuth scopes:

- `https://www.googleapis.com/auth/gmail.readonly`
- `https://www.googleapis.com/auth/gmail.send`
- `https://www.googleapis.com/auth/gmail.compose`
- `https://www.googleapis.com/auth/gmail.modify`
- `https://www.googleapis.com/auth/gmail.labels`

**Operational impact:** existing Google connections created before this change
hold only the original `gmail.readonly` and `gmail.send` scopes. Users who
want to use any of the newer Gmail actions — `create_draft`, `mark_as_read`,
`mark_as_unread`, `archive`, `trash`, `add_label`, `remove_label`,
`create_label` — must reconnect their Google account so the new scopes are
granted.

Connections that continue to use only `send_email`, `reply_to_email`, and
`new_email_received` do not need to reconnect.

Workflow activation already surfaces this as `409 missing_scopes` with the
missing scope URI in `context.missing_scopes`, so the client can prompt the
user to reconnect.

**Attachment binary retrieval is deferred.** The Gmail trigger payload exposes
`has_attachment`, `attachment_count`, and `attachment_ids`, but there is no
action that downloads attachment bytes. This is intentional and will remain
until the Drive/storage architecture exists. Do not put large base64 attachment
payloads into execution records.

---

## 16. Deploy checklist

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
[ ] INTERNAL_SCHEDULER_BATCH_SIZE set
[ ] INTERNAL_SCHEDULER_RATE_LIMIT set
[ ] INTERNAL_SCHEDULER_LOCK_SECONDS set
[ ] INTERNAL_SCHEDULER_STALE_RUNNING_MINUTES set
[ ] Google Apps Script configured with Script Properties
[ ] Apps Script 5-minute trigger installed
[ ] Google Cloud Console: OAuth redirect URIs include GOOGLE_CONNECTIONS_REDIRECT_URI
[ ] FRONTEND_CONNECTIONS_REDIRECT configured
[ ] Full test suite green (last verified: 752 passed / 2267 assertions)
```

---

## 17. Post-deployment smoke test

1. `curl -X POST https://sea-turtle-app-vshwt.ondigitalocean.app/api/internal/scheduler/tick` with no token → 401.
2. Same with wrong token → 401.
3. Same with correct token → 200 with `{ ok: true, ... }`.
4. `GET /api/catalog` with a valid user token → 200.
5. `GET /api/connections` with a valid user token → 200.
6. Complete a real workflow: create → trigger → step → activate.
7. Send an external email to a connected Gmail account.
8. Within two ticks (≈10 minutes), verify a new execution appears in `GET /api/workflows/{id}/executions`.
9. Verify the execution has `status: "completed"` and `trigger_source: "poll"`.
10. Verify the response email arrives at the configured `to` address.
11. Verify the Apps Script Executions log shows `Scheduler tick OK: {"ok":true,"processed":1,"executed":1,...}`.
12. (If the workflow uses `create_draft`, `mark_as_read`, `mark_as_unread`, `archive`, `trash`, `add_label`, `remove_label`, or `create_label`) confirm the connection has been reconnected with the new Gmail scopes.

---

## 18. Known limitations

- DigitalOcean App Platform does not allow multiple long-lived processes in a single Web Service container. The external clock approach (Apps Script) sidesteps this.
- Apps Script is a single point of failure for the scheduler. If the trigger is disabled or the script is deleted, automation stops.
- Consumer Apps Script accounts cannot sustain more than ~18 seconds per tick. Long ticks exhaust the daily quota.
- The new scheduler path is synchronous. Very large batches will exceed the platform HTTP request timeout.
- Gmail delivery is at-least-once. No exactly-once guarantee.
- Real Gmail E2E verification of the new Gmail actions and trigger filters is still pending at the time of this document update.

---

## 19. Contact points for operational rotation

- `INTERNAL_SCHEDULER_TOKEN` — rotate in both the Laravel `.env` and the Apps Script `ScriptProperties` simultaneously.
- Google OAuth credentials — rotate via Google Cloud Console.
- `APP_KEY` — must remain stable across deploys. Rotating it invalidates encrypted credentials in `connections`.
