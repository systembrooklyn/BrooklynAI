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
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\Support\Fakes\FakeGmailMessageReader;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\TestCase;

class PollGmailDuplicateDispatchTest extends TestCase
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

        return [$user, $workflow];
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

    public function test_same_message_does_not_queue_twice_while_unique_lock_is_held(): void
    {
        Queue::fake();
        [$user, $workflow] = $this->setupWorkflow();

        $this->reader->listMessageIdsReturn = ['dup-1'];
        $this->reader->messages = [
            'dup-1' => $this->makeMessage('dup-1', (time() - 30) * 1000, 'external@elsewhere.com'),
        ];

        // First poll dispatches the job; the ShouldBeUnique lock is held.
        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertPushed(RunWorkflowJob::class, 1);

        // Simulate the cursor having been reset (as if a queue dispatch failure
        // occurred between dispatch and cursor persistence). The unique lock
        // must still be held from the first dispatch.
        WorkflowTriggerModel::query()
            ->where('workflow_id', $workflow->id)
            ->update(['poll_cursor' => time() - 120, 'next_poll_at' => null]);

        $this->artisan('workflows:poll-gmail')->assertSuccessful();

        // Still only one job — the second dispatch was blocked by the unique lock.
        Queue::assertPushed(RunWorkflowJob::class, 1);
    }

    public function test_different_messages_can_be_queued_independently(): void
    {
        Queue::fake();
        $this->setupWorkflow();

        $this->reader->listMessageIdsReturn = ['msg-a', 'msg-b'];
        $this->reader->messages = [
            'msg-a' => $this->makeMessage('msg-a', (time() - 30) * 1000, 'external@elsewhere.com'),
            'msg-b' => $this->makeMessage('msg-b', (time() - 20) * 1000, 'external@elsewhere.com'),
        ];

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertPushed(RunWorkflowJob::class, 2);
    }
}
