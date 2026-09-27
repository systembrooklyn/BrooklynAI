# 08 — Production Deployment

Operational reference for running this Laravel API in production.

Target: any Linux server capable of running PHP 8.2+, MySQL 8+, and a web server fronting PHP-FPM. DigitalOcean Ubuntu is a common deployment target but the requirements below are not vendor-specific.

---

## 1. Required processes

| Process              | Purpose                                                                              | Required |
| -------------------- | ------------------------------------------------------------------------------------ | -------- |
| PHP-FPM + web server | Serves the HTTP API                                                                  | Yes      |
| Laravel Scheduler    | Runs `poll-gmail`, `run-scheduled`, and `recover-failed-poll-executions` on schedule | Yes      |
| Queue Worker         | Processes `RunWorkflowJob` dispatched by the poller                                  | Yes      |

Without the scheduler, no Gmail trigger will fire automatically.
Without a queue worker, dispatched jobs will sit in the queue and never execute.

---

## 2. Laravel Scheduler (cron)

Add exactly one cron entry to the deploy user's crontab:

cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1

text

- `/path/to/project` is a placeholder — replace with the absolute path of the deployed Laravel project.
- The entry must run as the user that owns the project files.
- Do not add per-command cron entries.

`routes/console.php` declares:

- `workflows:run-scheduled` — every minute
- `workflows:poll-gmail` — every minute
- `workflows:recover-failed-poll-executions` — every five minutes

All three use `withoutOverlapping()`.

Verify by observing periodic `schedule:run` invocations in the system logs or by tailing `storage/logs/laravel.log`.

---

## 3. Queue Worker

The queue connection is read from `QUEUE_CONNECTION`. The shipped `.env.example` uses `database`, which requires no additional infrastructure.

`RunWorkflowJob` is dispatched by `PollGmailCommand`. A long-running worker is required.

### Option A — long-running worker via a process manager

Use whatever process manager is already present on the server (systemd, supervisor, runit, etc.). The essential command:
php artisan queue:work --tries=3 --timeout=120 --sleep=3

text

Example systemd unit — treat as illustrative, adjust user, paths, and binary location:

````ini
[Unit]
Description=Laravel Queue Worker
After=network.target

[Service]
User=<deploy-user>
Restart=always
RestartSec=5
WorkingDirectory=/path/to/project
ExecStart=/usr/bin/php artisan queue:work --tries=3 --timeout=120 --sleep=3

[Install]
WantedBy=multi-user.target
Option B — per-minute cron worker
For low-volume deployments:

text
* * * * * cd /path/to/project && php artisan queue:work --stop-when-empty --tries=3 --timeout=120 --max-time=55 >> /dev/null 2>&1
Higher process-startup overhead; not recommended for high throughput.

4. Cache and queue store requirements
RunWorkflowJob implements ShouldBeUnique, which relies on Laravel's cache lock. The cache store must be shared and persistent across processes.

Acceptable: database, redis, memcached

Not acceptable: array (per-process; no cross-process locking)

Not acceptable: file on multi-server deployments without shared storage

Confirm CACHE_STORE in .env.

QUEUE_CONNECTION must also be a persistent store (database, redis, sqs). sync is acceptable only for local development.

5. Environment variables
Already required by the existing project:

APP_KEY, APP_URL, APP_ENV=production, APP_DEBUG=false

DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD

GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, GOOGLE_REDIRECT_URI

GOOGLE_CONNECTIONS_REDIRECT_URI

FRONTEND_CONNECTIONS_REDIRECT

LOGIN_MASTER_PASSWORD (optional)

New in this batch:

AUTOMATION_EXECUTION_RATE_LIMIT_PER_MINUTE (optional; default 10)

QUEUE_CONNECTION (must not be sync)

CACHE_STORE (must be shared and persistent)

6. Gmail poll cadence
Each Gmail trigger has an interval_minutes. The scheduler runs every minute, but the poller only processes a trigger when next_poll_at IS NULL OR next_poll_at <= now().

After a successful Gmail query, the poller sets next_poll_at = now + interval_minutes.

Default when interval_minutes is null: 1 minute.

Paused workflows are never polled.

7. Self-email protection
The poller inspects the From: header of every detected message. If the sender address (case-insensitive, including display-name form) matches the workflow connection's own email, the message is skipped and logged.

Always on. No configuration.

8. Delivery semantics — at-least-once
The Gmail trigger is at-least-once, not exactly-once.

Unique jobs (ShouldBeUnique) reduce duplicate queuing of the same logical event.

RunWorkflowAction's idempotency check reduces duplicate execution of the same message.

The executions unique constraint on (workflow_id, idempotency_key) prevents duplicate row creation.

None of these guarantee exactly-once external side effects. If the Gmail API accepts a send and the response is lost, a retry will send the message again. This is an inherent limitation of the Gmail API (no idempotency key on messages.send).

9. Rate limiting
Per-workflow dispatch-time protection using Laravel RateLimiter.

Key: workflow-exec:{workflowId}

Default: 10 dispatches per 60 seconds

Under limit: dispatch immediately, consume one slot

Over limit: dispatch with ->delay(availableIn + 1), consume zero slots

This is burst protection, not a strict executions-per-minute guarantee. Delayed jobs all become due when the window resets. The unique-job mechanism still prevents duplicate queuing of the same logical event.

10. Failed-execution recovery
Failed poll executions are retried by a scheduled command:

Grace period: 10 minutes after the failure

Max recovery cycles: 3

Each recovery creates a new execution row with idempotency key {original}#r{N} and retry_of_id = original.id

Total worker attempt budget per logical event:

text
Original job:  3 worker attempts
Recovery #1:   3 worker attempts
Recovery #2:   3 worker attempts
Recovery #3:   3 worker attempts
             ────
             12 worker attempts maximum
retry_attempts counts recovery cycles only. retry_of_id provides lineage for audit — it does NOT guarantee exactly-once external side effects.

CAS limitation: the "claim recovery slot → dispatch job" sequence is not atomic across DB and queue. If dispatch fails after the claim, one recovery slot is consumed without a retry job. This is accepted for MVP; no outbox architecture is used.

11. Deploy checklist
text
[ ] APP_KEY set and stable across deploys
[ ] .env production values populated
[ ] QUEUE_CONNECTION and CACHE_STORE set to persistent stores
[ ] composer install --no-dev --optimize-autoloader
[ ] php artisan migrate --force
[ ] php artisan config:cache
[ ] php artisan route:cache
[ ] storage/ and bootstrap/cache/ writable
[ ] cron entry for schedule:run is active
[ ] queue worker process running
[ ] Google Cloud Console: OAuth redirect URIs include GOOGLE_CONNECTIONS_REDIRECT_URI
[ ] FRONTEND_CONNECTIONS_REDIRECT configured
text

---

## Tests

### `tests/Feature/Execution/PollGmailSelfEmailExclusionTest.php`

```php
<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Execution\Infrastructure\Jobs\RunWorkflowJob;
use App\Modules\Integrations\Application\Actions\FetchGmailTriggerMessagesAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PollGmailSelfEmailExclusionTest extends TestCase
{
    use RefreshDatabase;

    private function setupWorkflow(string $connectionEmail): array
    {
        $user = User::factory()->create();
        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-'.uniqid(),
            'email' => $connectionEmail,
            'scopes' => ['gmail.readonly', 'gmail.send'],
            'status' => 'active',
        ]);
        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'wf',
            'status' => WorkflowStatus::Active->value,
        ]);
        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $connection->id,
            'strategy' => 'poll',
            'config' => ['label_id' => 'INBOX'],
            'interval_minutes' => 1,
            'poll_cursor' => time() - 120,
        ]);

        return [$user, $connection, $workflow];
    }

    private function fakeFetch(array $payloads): void
    {
        $this->mock(FetchGmailTriggerMessagesAction::class, function ($mock) use ($payloads) {
            $mock->shouldReceive('execute')->andReturn($payloads);
        });
    }

    public function test_self_authored_message_is_not_dispatched(): void
    {
        Queue::fake();
        $this->setupWorkflow('sender@example.com');
        $this->fakeFetch([[
            'message_id' => 'm1',
            'from' => 'Sender <sender@example.com>',
            'received_at_epoch_ms' => (time() - 30) * 1000,
        ]]);

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }

    public function test_external_sender_is_dispatched(): void
    {
        Queue::fake();
        $this->setupWorkflow('sender@example.com');
        $this->fakeFetch([[
            'message_id' => 'm2',
            'from' => 'someone@elsewhere.com',
            'received_at_epoch_ms' => (time() - 30) * 1000,
        ]]);

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertPushed(RunWorkflowJob::class, 1);
    }

    public function test_case_insensitive_self_sender_is_excluded(): void
    {
        Queue::fake();
        $this->setupWorkflow('sender@example.com');
        $this->fakeFetch([[
            'message_id' => 'm3',
            'from' => 'SENDER@EXAMPLE.COM',
            'received_at_epoch_ms' => (time() - 30) * 1000,
        ]]);

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }

    public function test_display_name_self_sender_is_excluded(): void
    {
        Queue::fake();
        $this->setupWorkflow('sender@example.com');
        $this->fakeFetch([[
            'message_id' => 'm4',
            'from' => 'Some Name <SENDER@example.com>',
            'received_at_epoch_ms' => (time() - 30) * 1000,
        ]]);

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }
}
