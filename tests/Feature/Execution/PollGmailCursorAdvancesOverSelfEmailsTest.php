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

class PollGmailCursorAdvancesOverSelfEmailsTest extends TestCase
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

    public function test_cursor_advances_over_self_authored_messages(): void
    {
        Queue::fake();
        [$user, $workflow, $trigger] = $this->setupWorkflow();
        $oldCursor = $trigger->poll_cursor;
        $selfTime = (time() - 30) * 1000;

        $this->reader->listMessageIdsReturn = ['self-1'];
        $this->reader->messages = [
            'self-1' => $this->makeMessage('self-1', $selfTime, 'Owner <owner@example.com>'),
        ];

        $this->artisan('workflows:poll-gmail')->assertSuccessful();

        $trigger->refresh();
        $this->assertSame(intdiv($selfTime, 1000) + 1, $trigger->poll_cursor);
        $this->assertGreaterThan($oldCursor, $trigger->poll_cursor);
        Queue::assertNothingPushed();
    }

    public function test_cursor_advances_over_already_seen_messages(): void
    {
        Queue::fake();
        [$user, $workflow, $trigger] = $this->setupWorkflow();

        ExecutionModel::create([
            'workflow_id' => $workflow->id, 'user_id' => $user->id,
            'status' => 'completed', 'trigger_source' => 'poll',
            'trigger_payload' => [], 'workflow_snapshot' => [],
            'idempotency_key' => 'gmail:'.$workflow->id.':seen-1',
        ]);

        $seenTime = (time() - 45) * 1000;

        $this->reader->listMessageIdsReturn = ['seen-1'];
        $this->reader->messages = [
            'seen-1' => $this->makeMessage('seen-1', $seenTime, 'external@elsewhere.com'),
        ];

        $this->artisan('workflows:poll-gmail')->assertSuccessful();

        $trigger->refresh();
        $this->assertSame(intdiv($seenTime, 1000) + 1, $trigger->poll_cursor);
        Queue::assertNothingPushed();
    }
}
