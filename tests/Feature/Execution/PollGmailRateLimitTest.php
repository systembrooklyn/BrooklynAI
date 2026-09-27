<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Infrastructure\Jobs\RunWorkflowJob;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\Support\Fakes\FakeGmailMessageReader;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\TestCase;

class PollGmailRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private FakeGmailMessageReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new FakeGmailMessageReader;

        $this->app->instance(GmailMessageReader::class, $this->reader);
        $this->app->instance(GoogleCredentialsResolver::class, new FakeGoogleCredentialsResolver);
        $this->app->instance(ActionInvoker::class, new FakeActionInvoker);
    }

    private function makeMessage(string $id, int $internalDateMs, string $from): GoogleGmailMessage
    {
        $fromHeader = new GoogleGmailMessagePartHeader(['name' => 'From', 'value' => $from]);
        $subjectHeader = new GoogleGmailMessagePartHeader(['name' => 'Subject', 'value' => 'Hello']);
        $body = new GoogleGmailMessagePartBody(['data' => '']);
        $part = new GoogleGmailMessagePart([
            'mimeType' => 'text/plain',
            'headers' => [$fromHeader, $subjectHeader],
            'body' => $body,
        ]);

        return new GoogleGmailMessage([
            'id' => $id,
            'threadId' => 'thread-'.$id,
            'internalDate' => (string) $internalDateMs,
            'labelIds' => ['INBOX'],
            'snippet' => 'Snippet',
            'payload' => $part,
        ]);
    }

    public function test_over_limit_messages_are_still_dispatched_with_delay(): void
    {
        Queue::fake();
        config(['automation.execution_rate_limit_per_minute' => 3]);

        $user = User::factory()->create();
        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub',
            'email' => 'owner@example.com',
            'scopes' => ['gmail.readonly', 'gmail.send'],
            'status' => 'active',
        ]);
        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'wf', 'status' => WorkflowStatus::Active->value,
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

        RateLimiter::clear('workflow-exec:'.$workflow->id);

        $ids = ['m1', 'm2', 'm3', 'm4', 'm5'];
        $messages = [];
        foreach ($ids as $i => $id) {
            $messages[$id] = $this->makeMessage(
                $id,
                (time() - 30 + $i) * 1000,
                'ext@elsewhere.com',
            );
        }

        $this->reader->listMessageIdsReturn = $ids;
        $this->reader->messages = $messages;

        $this->artisan('workflows:poll-gmail')->assertSuccessful();

        Queue::assertPushed(RunWorkflowJob::class, 5);
        $this->assertSame(3, RateLimiter::attempts('workflow-exec:'.$workflow->id));

        /** @var \Illuminate\Support\Collection<int, RunWorkflowJob> $jobs */
        $jobs = Queue::pushed(RunWorkflowJob::class);

        $this->assertCount(5, $jobs, 'All five messages must be dispatched — none silently dropped.');

        $immediate = $jobs
            ->filter(static fn (RunWorkflowJob $job) => $job->delay === null)
            ->values();

        $delayed = $jobs
            ->filter(static fn (RunWorkflowJob $job) => $job->delay !== null)
            ->values();

        $this->assertCount(
            3,
            $immediate,
            'Exactly the configured rate-limit slots must be dispatched immediately.',
        );
        $this->assertCount(
            2,
            $delayed,
            'Excess jobs must be delayed, not dropped.',
        );

        // No message is silently dropped — every message id appears exactly once.
        $messageIds = $jobs
            ->map(static fn (RunWorkflowJob $job) => (string) ($job->triggerPayload['message_id'] ?? ''))
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['m1', 'm2', 'm3', 'm4', 'm5'], $messageIds);
    }
}
