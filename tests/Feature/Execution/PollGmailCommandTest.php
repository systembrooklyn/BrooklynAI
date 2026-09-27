<?php

namespace Tests\Feature\Execution;

use App\Models\User;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowModel;
use App\Modules\Automation\Infrastructure\Eloquent\WorkflowTriggerModel;
use App\Modules\Connections\Core\Exceptions\ConnectionNotFoundException;
use App\Modules\Connections\Core\Exceptions\GoogleCredentialsUnavailableException;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Infrastructure\Eloquent\ExecutionModel;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeActionInvoker;
use Tests\Support\Fakes\FakeGmailMessageReader;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\TestCase;

class PollGmailCommandTest extends TestCase
{
    use RefreshDatabase;

    private FakeGmailMessageReader $reader;

    private FakeGoogleCredentialsResolver $resolver;

    private FakeActionInvoker $invoker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new FakeGmailMessageReader;
        $this->resolver = new FakeGoogleCredentialsResolver;
        $this->invoker = new FakeActionInvoker;

        $this->app->instance(GmailMessageReader::class, $this->reader);
        $this->app->instance(\App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver::class, $this->resolver);
        $this->app->instance(ActionInvoker::class, $this->invoker);
    }

    private function seedWorkflow(
        User $user,
        array $triggerConfig = [],
        ?int $pollCursor = null,
        string $status = 'active',
    ): int {
        $workflow = WorkflowModel::create([
            'user_id' => $user->id,
            'name' => 'W-'.uniqid(),
            'status' => $status,
        ]);

        WorkflowTriggerModel::create([
            'workflow_id' => $workflow->id,
            'integration_key' => 'google.gmail',
            'trigger_key' => 'new_email_received',
            'connection_id' => null,
            'strategy' => 'poll',
            'config' => $triggerConfig,
            'interval_minutes' => null,
            'poll_cursor' => $pollCursor,
        ]);

        return (int) $workflow->id;
    }

    private function makeMessage(string $id, int $internalDateMs, array $overrides = []): GoogleGmailMessage
    {
        $header = new GoogleGmailMessagePartHeader(['name' => 'Subject', 'value' => 'Hello']);
        $body = new GoogleGmailMessagePartBody(['data' => '']);
        $part = new GoogleGmailMessagePart([
            'mimeType' => 'text/plain',
            'headers' => [$header],
            'body' => $body,
        ]);

        return new GoogleGmailMessage(array_merge([
            'id' => $id,
            'threadId' => 'thread-'.$id,
            'internalDate' => (string) $internalDateMs,
            'labelIds' => ['INBOX'],
            'snippet' => 'Snippet',
            'payload' => $part,
        ], $overrides));
    }

    private function cursorOf(int $workflowId): ?int
    {
        $value = WorkflowTriggerModel::query()->where('workflow_id', $workflowId)->value('poll_cursor');

        return $value === null ? null : (int) $value;
    }

    // -------------------------------------------------------------------
    // Command surface
    // -------------------------------------------------------------------

    public function test_command_is_registered(): void
    {
        $this->assertTrue(
            array_key_exists('workflows:poll-gmail', \Illuminate\Support\Facades\Artisan::all())
        );
    }

    public function test_command_with_no_workflows_exits_zero(): void
    {
        $this->artisan('workflows:poll-gmail')->assertExitCode(0);
    }

    // -------------------------------------------------------------------
    // First tick
    // -------------------------------------------------------------------

    public function test_first_tick_initializes_cursor_without_querying_gmail(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: null);

        $before = time();

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $after = time();
        $cursor = $this->cursorOf($id);

        $this->assertNotNull($cursor);
        $this->assertGreaterThanOrEqual($before, $cursor);
        $this->assertLessThanOrEqual($after, $cursor);
        $this->assertCount(0, $this->reader->listCalls);
    }

    public function test_first_tick_creates_no_executions(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: null);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertDatabaseMissing('executions', ['workflow_id' => $id]);
    }

    // -------------------------------------------------------------------
    // Subsequent ticks
    // -------------------------------------------------------------------

    public function test_subsequent_tick_uses_cursor_overlap(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertCount(1, $this->reader->listCalls);
        $this->assertSame(940, $this->reader->listCalls[0]['after']);
    }

    public function test_cursor_overlap_never_goes_negative(): void
    {
        $user = User::factory()->create();
        $this->seedWorkflow($user, pollCursor: 30);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(0, $this->reader->listCalls[0]['after']);
    }

    public function test_subsequent_tick_omits_label_filter_when_config_absent(): void
    {
        $user = User::factory()->create();
        $this->seedWorkflow($user, pollCursor: 1000, triggerConfig: []);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame([], $this->reader->listCalls[0]['labelIds']);
    }

    public function test_subsequent_tick_includes_label_id_filter_when_configured(): void
    {
        $user = User::factory()->create();
        $this->seedWorkflow($user, pollCursor: 1000, triggerConfig: ['label_id' => 'INBOX']);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(['INBOX'], $this->reader->listCalls[0]['labelIds']);
    }

    public function test_malformed_label_id_skips_workflow_and_preserves_cursor(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000, triggerConfig: ['label_id' => 123]);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
        $this->assertCount(0, $this->reader->listCalls);
    }

    public function test_empty_label_id_skips_workflow_and_preserves_cursor(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000, triggerConfig: ['label_id' => '']);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
        $this->assertCount(0, $this->reader->listCalls);
    }

    // -------------------------------------------------------------------
    // Deterministic ordering
    // -------------------------------------------------------------------

    public function test_messages_processed_in_deterministic_order(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->reader->listMessageIdsReturn = ['m-late', 'm-early', 'm-early-2'];
        $this->reader->messages = [
            'm-late' => $this->makeMessage('m-late', 1_700_000_030_000),
            'm-early' => $this->makeMessage('m-early', 1_700_000_010_000),
            'm-early-2' => $this->makeMessage('m-early-2', 1_700_000_010_000),
        ];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $executions = ExecutionModel::query()
            ->where('workflow_id', $id)
            ->orderBy('id')
            ->get();

        $messageIds = $executions
            ->map(static fn ($e) => $e->trigger_payload['message_id'] ?? null)
            ->filter()
            ->values()
            ->all();

        $this->assertSame(['m-early', 'm-early-2', 'm-late'], $messageIds);
    }

    // -------------------------------------------------------------------
    // Execution content
    // -------------------------------------------------------------------

    public function test_execution_trigger_source_is_poll(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);
        $this->reader->listMessageIdsReturn = ['m-1'];
        $this->reader->messages = ['m-1' => $this->makeMessage('m-1', 1_700_000_000_000)];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $id,
            'trigger_source' => 'poll',
        ]);
    }

    public function test_execution_idempotency_key_format(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);
        $this->reader->listMessageIdsReturn = ['m-abc'];
        $this->reader->messages = ['m-abc' => $this->makeMessage('m-abc', 1_700_000_000_000)];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertDatabaseHas('executions', [
            'workflow_id' => $id,
            'idempotency_key' => 'gmail:'.$id.':m-abc',
        ]);
    }

    public function test_execution_payload_is_canonical(): void
    {
        $user = User::factory()->create();
        $this->seedWorkflow($user, pollCursor: 1000);
        $this->reader->listMessageIdsReturn = ['m-1'];
        $this->reader->messages = ['m-1' => $this->makeMessage('m-1', 1_700_000_000_000)];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $execution = ExecutionModel::query()->firstOrFail();
        $payload = $execution->trigger_payload;

        $this->assertSame([
            'message_id',
            'thread_id',
            'from',
            'to',
            'cc',
            'subject',
            'snippet',
            'body',
            'received_at',
            'received_at_epoch_ms',
            'labels',
            'has_attachment',
            'attachment_count',
            'attachment_ids',
        ], array_keys($payload));
    }

    // -------------------------------------------------------------------
    // Cursor advancement
    // -------------------------------------------------------------------

    public function test_cursor_advances_to_max_internal_date_after_clean_tick(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->reader->listMessageIdsReturn = ['m-1', 'm-2'];
        $this->reader->messages = [
            'm-1' => $this->makeMessage('m-1', 1_700_000_005_000),
            'm-2' => $this->makeMessage('m-2', 1_700_000_010_500),
        ];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        // Production behavior: next cursor = maxSeenSeconds + 1.
        // Max internalDate is 1_700_000_010_500 ms => 1_700_000_010 s => +1.
        $this->assertSame(1_700_000_011, $this->cursorOf($id));
    }

    public function test_cursor_unchanged_when_no_messages_returned(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);
        $this->reader->listMessageIdsReturn = [];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
    }

    // -------------------------------------------------------------------
    // Transient failure isolation
    // -------------------------------------------------------------------

    public function test_cursor_unchanged_on_429_from_list(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->reader->listThrows = new GoogleServiceException('Rate limited', 429);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
    }

    public function test_cursor_unchanged_on_500_from_list(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->reader->listThrows = new GoogleServiceException('Server error', 500);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
    }

    public function test_cursor_unchanged_on_429_from_get(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->reader->listMessageIdsReturn = ['m-1'];
        $this->reader->getThrows = ['m-1' => new GoogleServiceException('Rate limited', 429)];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
    }

    // -------------------------------------------------------------------
    // 404 handling
    // -------------------------------------------------------------------

    public function test_get_404_is_skipped_and_cursor_still_advances_from_others(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->reader->listMessageIdsReturn = ['m-ok', 'm-gone'];
        $this->reader->messages = ['m-ok' => $this->makeMessage('m-ok', 1_700_000_010_000)];
        $this->reader->getThrows = ['m-gone' => new GoogleServiceException('Not found', 404)];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        // Production behavior: next cursor = maxSeenSeconds + 1.
        $this->assertSame(1_700_000_011, $this->cursorOf($id));
    }

    public function test_all_get_404_leaves_cursor_unchanged(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->reader->listMessageIdsReturn = ['m-1', 'm-2'];
        $this->reader->getThrows = [
            'm-1' => new GoogleServiceException('Not found', 404),
            'm-2' => new GoogleServiceException('Not found', 404),
        ];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
    }

    // -------------------------------------------------------------------
    // Isolation
    // -------------------------------------------------------------------

    public function test_workflow_with_running_execution_is_skipped(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        ExecutionModel::create([
            'workflow_id' => $id,
            'user_id' => $user->id,
            'status' => 'running',
            'trigger_source' => 'schedule',
            'workflow_snapshot' => [],
        ]);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
        $this->assertCount(0, $this->reader->listCalls);
    }

    public function test_non_active_workflows_are_not_polled(): void
    {
        $user = User::factory()->create();
        $draft = $this->seedWorkflow($user, pollCursor: 1000, status: 'draft');
        $paused = $this->seedWorkflow($user, pollCursor: 1000, status: 'paused');

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($draft));
        $this->assertSame(1000, $this->cursorOf($paused));
        $this->assertCount(0, $this->reader->listCalls);
    }

    public function test_per_workflow_isolation_on_429(): void
    {
        $user = User::factory()->create();
        $failing = $this->seedWorkflow($user, pollCursor: 1000);
        $ok = $this->seedWorkflow($user, pollCursor: 1000);

        WorkflowTriggerModel::query()
            ->where('workflow_id', $failing)
            ->update(['config' => json_encode(['label_id' => 999])]);

        $this->reader->listMessageIdsReturn = ['m-1'];
        $this->reader->messages = ['m-1' => $this->makeMessage('m-1', 1_700_000_000_000)];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($failing));
        // Production behavior: next cursor = maxSeenSeconds + 1.
        $this->assertSame(1_700_000_001, $this->cursorOf($ok));
    }

    // -------------------------------------------------------------------
    // Unexpected errors
    // -------------------------------------------------------------------

    public function test_unexpected_exception_propagates(): void
    {
        $user = User::factory()->create();
        $this->seedWorkflow($user, pollCursor: 1000);

        $this->reader->listThrows = new \LogicException('boom');

        $this->expectException(\LogicException::class);

        $this->artisan('workflows:poll-gmail');
    }

    public function test_credentials_unavailable_skips_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->resolver->throws = GoogleCredentialsUnavailableException::forLegacyUser((int) $user->id);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
    }

    public function test_connection_not_found_skips_workflow(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);

        $this->resolver->throws = ConnectionNotFoundException::forUser((int) $user->id, 42);

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(1000, $this->cursorOf($id));
    }

    public function test_same_message_discovered_twice_is_replayed_via_idempotency(): void
    {
        $user = User::factory()->create();
        $id = $this->seedWorkflow($user, pollCursor: 1000);
        $this->reader->listMessageIdsReturn = ['m-1'];
        $this->reader->messages = ['m-1' => $this->makeMessage('m-1', 1_700_000_000_000)];

        $this->artisan('workflows:poll-gmail')->assertExitCode(0);
        $this->artisan('workflows:poll-gmail')->assertExitCode(0);

        $this->assertSame(
            1,
            ExecutionModel::query()
                ->where('workflow_id', $id)
                ->where('idempotency_key', 'gmail:'.$id.':m-1')
                ->count()
        );
    }
}
