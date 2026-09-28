<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Execution\Application\Strategies\GmailPollStrategy;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use DateTimeImmutable;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\Support\Fakes\FakeGmailMessageReader;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\TestCase;

class GmailPollStrategyTest extends TestCase
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

    private function makeWorkflow(string $connectionEmail, int $pollCursor): array
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
            'name' => 'gmail-wf-'.uniqid(),
            'status' => 'active',
        ]);

        $triggerModel = WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => $connection->id,
            'strategy' => 'poll',
            'config' => [],
            'interval_minutes' => 5,
            'poll_cursor' => $pollCursor,
            'next_poll_at' => null,
        ]);

        return [$user, $workflow, $triggerModel];
    }

    public function test_external_email_executes_workflow(): void
    {
        $now = new DateTimeImmutable;
        $initialCursor = $now->getTimestamp() - 120;

        [$user, $workflow, $triggerModel] = $this->makeWorkflow('owner@example.com', $initialCursor);

        $this->reader->listMessageIdsReturn = ['m1'];
        $this->reader->messages = [
            'm1' => $this->makeMessage(
                'm1',
                ($now->getTimestamp() - 30) * 1000,
                'external@elsewhere.com',
            ),
        ];

        $repo = app(WorkflowRepository::class);
        $workflowEntity = $repo->findForUser((int) $user->id, (int) $workflow->id);
        $triggerEntity = $repo->findTriggerForWorkflow((int) $workflow->id);

        $this->assertNotNull($workflowEntity);
        $this->assertNotNull($triggerEntity);

        $result = app(GmailPollStrategy::class)->process($workflowEntity, $triggerEntity, $now);

        $this->assertTrue($result->executed);
        $this->assertSame(1, $result->executionCount);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $workflow->id,
            'trigger_source' => 'poll',
            'status' => 'completed',
        ]);

        $this->assertSame(
            1,
            ExecutionModel::where('workflow_id', $workflow->id)->count(),
        );

        $triggerModel->refresh();
        $this->assertGreaterThan($initialCursor, (int) $triggerModel->poll_cursor);
        $this->assertNotNull($triggerModel->next_poll_at);
    }

    public function test_self_authored_email_is_ignored(): void
    {
        $now = new DateTimeImmutable;
        $initialCursor = $now->getTimestamp() - 120;

        [$user, $workflow, $triggerModel] = $this->makeWorkflow('owner@example.com', $initialCursor);

        $this->reader->listMessageIdsReturn = ['m1'];
        $this->reader->messages = [
            'm1' => $this->makeMessage(
                'm1',
                ($now->getTimestamp() - 30) * 1000,
                'Owner <owner@example.com>',
            ),
        ];

        $repo = app(WorkflowRepository::class);
        $workflowEntity = $repo->findForUser((int) $user->id, (int) $workflow->id);
        $triggerEntity = $repo->findTriggerForWorkflow((int) $workflow->id);

        $result = app(GmailPollStrategy::class)->process($workflowEntity, $triggerEntity, $now);

        $this->assertFalse($result->executed);
        $this->assertSame(
            0,
            ExecutionModel::where('workflow_id', $workflow->id)->count(),
        );

        $triggerModel->refresh();
        // Cursor must still advance past the skipped self-email.
        $this->assertGreaterThan($initialCursor, (int) $triggerModel->poll_cursor);
    }

    public function test_gmail_message_idempotency_prevents_duplicate_execution(): void
    {
        $now = new DateTimeImmutable;
        $initialCursor = $now->getTimestamp() - 120;

        [$user, $workflow, $triggerModel] = $this->makeWorkflow('owner@example.com', $initialCursor);

        $this->reader->listMessageIdsReturn = ['m1'];
        $this->reader->messages = [
            'm1' => $this->makeMessage(
                'm1',
                ($now->getTimestamp() - 30) * 1000,
                'external@elsewhere.com',
            ),
        ];

        $repo = app(WorkflowRepository::class);
        $workflowEntity = $repo->findForUser((int) $user->id, (int) $workflow->id);

        $strategy = app(GmailPollStrategy::class);

        $triggerEntity = $repo->findTriggerForWorkflow((int) $workflow->id);
        $strategy->process($workflowEntity, $triggerEntity, $now);

        $this->assertSame(
            1,
            ExecutionModel::where('workflow_id', $workflow->id)->count(),
            'First process should create exactly one execution.',
        );

        // Rewind the cursor to simulate the same message being returned again.
        $triggerModel->update(['poll_cursor' => $initialCursor]);

        $triggerEntity2 = $repo->findTriggerForWorkflow((int) $workflow->id);
        $strategy->process($workflowEntity, $triggerEntity2, $now);

        $this->assertSame(
            1,
            ExecutionModel::where('workflow_id', $workflow->id)->count(),
            'Second process with the same message must not create a duplicate execution.',
        );
    }
}
