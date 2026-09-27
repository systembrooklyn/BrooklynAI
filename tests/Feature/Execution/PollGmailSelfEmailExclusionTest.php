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

class PollGmailSelfEmailExclusionTest extends TestCase
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

    public function test_self_authored_message_is_not_dispatched(): void
    {
        Queue::fake();
        $this->setupWorkflow('sender@example.com');
        $this->reader->listMessageIdsReturn = ['m1'];
        $this->reader->messages = [
            'm1' => $this->makeMessage('m1', (time() - 30) * 1000, 'Sender <sender@example.com>'),
        ];

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }

    public function test_external_sender_is_dispatched(): void
    {
        Queue::fake();
        $this->setupWorkflow('sender@example.com');
        $this->reader->listMessageIdsReturn = ['m2'];
        $this->reader->messages = [
            'm2' => $this->makeMessage('m2', (time() - 30) * 1000, 'someone@elsewhere.com'),
        ];

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertPushed(RunWorkflowJob::class, 1);
    }

    public function test_case_insensitive_self_sender_is_excluded(): void
    {
        Queue::fake();
        $this->setupWorkflow('sender@example.com');
        $this->reader->listMessageIdsReturn = ['m3'];
        $this->reader->messages = [
            'm3' => $this->makeMessage('m3', (time() - 30) * 1000, 'SENDER@EXAMPLE.COM'),
        ];

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }

    public function test_display_name_self_sender_is_excluded(): void
    {
        Queue::fake();
        $this->setupWorkflow('sender@example.com');
        $this->reader->listMessageIdsReturn = ['m4'];
        $this->reader->messages = [
            'm4' => $this->makeMessage('m4', (time() - 30) * 1000, 'Some Name <SENDER@example.com>'),
        ];

        $this->artisan('workflows:poll-gmail')->assertSuccessful();
        Queue::assertNotPushed(RunWorkflowJob::class);
    }
}
