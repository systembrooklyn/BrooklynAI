<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use App\Modules\Execution\Infrastructure\Jobs\RunWorkflowJob;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\Support\Fakes\FakeGmailMessageReader;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\TestCase;

class PollGmailIdempotencyAndCursorTest extends TestCase
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

    private function setupWorkflow(): array
    {
        $user = User::factory()->create();
        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-'.uniqid(),
            'email' => 'owner@example.com',
            'scopes' => ['gmail.readonly', 'gmail.send'],
            'status' => 'active',
        ]);
        $workflow = WorkflowModel::create([
            'user_id' => $user->id, 'name' => 'wf', 'status' => WorkflowStatus::Active->value,
        ]);
        $trigger = WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $connection->id,
            'strategy' => 'poll',
            'config' => ['label_id' => 'INBOX'],
            'interval_minutes' => 1,
            'poll_cursor' => time() - 120,
        ]);

        return [$user, $workflow, $trigger];
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

    public function test_idempotency_prevents_duplicate_dispatch(): void
    {
        Queue::fake();
        [$user, $wf, $trigger] = $this->setupWorkflow();

        ExecutionModel::create([
            'workflow_id' => $wf->id,
            'user_id' => $user->id,
            'status' => 'completed',
            'trigger_source' => 'poll',
            'trigger_payload' => ['message_id' => 'm1'],
            'workflow_snapshot' => [],
            'idempotency_key' => 'gmail:'.$wf->id.':m1',
        ]);

        $this->reader->listMessageIdsReturn = ['m1'];
        $this->reader->messages = [
            'm1' => $this->makeMessage('m1', (time() - 30) * 1000, 'someone@elsewhere.com'),
        ];

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }

    public function test_queue_dispatch_failure_does_not_advance_cursor(): void
    {
        [$user, $wf, $trigger] = $this->setupWorkflow();
        $originalCursor = $trigger->poll_cursor;

        $this->reader->listMessageIdsReturn = ['m9'];
        $this->reader->messages = [
            'm9' => $this->makeMessage('m9', (time() - 30) * 1000, 'someone@elsewhere.com'),
        ];

        $this->app->bind(Dispatcher::class, function () {
            return new class implements Dispatcher
            {
                public function dispatch($command)
                {
                    throw new \RuntimeException('Queue unavailable');
                }

                public function dispatchSync($command, $handler = null)
                {
                    throw new \RuntimeException('Queue unavailable');
                }

                public function dispatchNow($command, $handler = null)
                {
                    throw new \RuntimeException('Queue unavailable');
                }

                public function chain($jobs = null)
                {
                    throw new \RuntimeException('Queue unavailable');
                }

                public function hasCommandHandler($command)
                {
                    return false;
                }

                public function getCommandHandler($command)
                {
                    return null;
                }

                public function pipeThrough(array $pipes)
                {
                    return $this;
                }

                public function map(array $map)
                {
                    return $this;
                }
            };
        });

        try {
            $this->artisan('workflows:poll-gmail');
        } catch (\Throwable) {
            // Expected — the exception propagates.
        }

        $trigger->refresh();
        $this->assertSame($originalCursor, $trigger->poll_cursor);
    }

    public function test_next_poll_at_is_updated_after_polling(): void
    {
        Queue::fake();
        [$user, $wf, $trigger] = $this->setupWorkflow();

        $this->reader->listMessageIdsReturn = [];
        $this->reader->messages = [];

        $this->assertNull($trigger->next_poll_at);

        $this->artisan('workflows:poll-gmail')->assertSuccessful();

        $trigger->refresh();
        $this->assertNotNull($trigger->next_poll_at);
    }
}
