<?php

namespace Tests\Unit\Execution;

use App\Modules\Automation\Core\Contracts\TemplateResolver;
use App\Modules\Automation\Core\Entities\Workflow;
use App\Modules\Automation\Core\Entities\WorkflowTrigger;
use App\Modules\Automation\Core\Repositories\WorkflowRepository;
use App\Modules\Automation\Core\ValueObjects\WorkflowStatus;
use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\Entities\Connection;
use App\Modules\Connections\Core\Repositories\ConnectionRepository;
use App\Modules\Connections\Core\ValueObjects\ConnectionCredentials;
use App\Modules\Connections\Core\ValueObjects\ConnectionStatus;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Execution\Application\Actions\RunWorkflowAction;
use App\Modules\Execution\Application\Services\WorkflowSnapshotBuilder;
use App\Modules\Execution\Application\Strategies\GmailPollStrategy;
use App\Modules\Execution\Core\Contracts\ActionInvoker;
use App\Modules\Execution\Core\Repositories\ExecutionRepository;
use App\Modules\Integrations\Application\Actions\FetchGmailTriggerMessagesAction;
use App\Modules\Integrations\Application\Actions\FetchGmailTriggerMessagesWithAttachmentsAction;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveFileUploader;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentMaterializer;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentReader;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessagePayloadBuilder;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailTriggerQueryComposer;
use DateTimeImmutable;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use PHPUnit\Framework\TestCase;

/**
 * Exercises the real, final GmailPollStrategy::process() through its real
 * constructor.
 *
 * The strategy is constructed with:
 *   - PHPUnit mocks for the three repository interfaces and the credentials
 *     resolver interface.
 *   - Real instances of the concrete action / composer / builder classes
 *     whose own dependencies are themselves mocked or provided as real
 *     non-final infrastructure.
 *
 * The GmailMessageReader, GmailAttachmentReader and GoogleDriveFileUploader
 * are all non-final and can be mocked directly by PHPUnit, so no production
 * class needs to be altered to allow this test to construct the strategy.
 *
 * The test is DB-free, network-free, Gmail API-free, Drive API-free and
 * OAuth-free.
 */
final class GmailPollStrategyBranchTest extends TestCase
{
    /** @var array<int, array{after: int, labelIds: array<int, string>, query: ?string}> */
    private array $capturedFetchCalls = [];

    // ---------------------------------------------------------------------
    // Normal trigger
    // ---------------------------------------------------------------------

    public function test_normal_trigger_uses_lightweight_fetch_path(): void
    {
        $strategy = $this->makeStrategy(connectionHasDriveScope: true);

        $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received', 1000),
            new DateTimeImmutable,
        );

        $this->assertCount(1, $this->capturedFetchCalls);
        $this->assertNull($this->capturedFetchCalls[0]['query']);
    }

    public function test_normal_trigger_does_not_require_drive_scope(): void
    {
        $strategy = $this->makeStrategy(connectionHasDriveScope: false);

        $result = $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received', 1000),
            new DateTimeImmutable,
        );

        $this->assertNotSame('drive_connection_required', $result->reason);
        $this->assertCount(1, $this->capturedFetchCalls);
    }

    public function test_normal_trigger_query_does_not_contain_has_attachment(): void
    {
        $strategy = $this->makeStrategy();

        $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received', 1000, config: ['subject' => 'invoice']),
            new DateTimeImmutable,
        );

        $this->assertCount(1, $this->capturedFetchCalls);
        $query = (string) $this->capturedFetchCalls[0]['query'];
        $this->assertStringContainsString('subject:invoice', $query);
        $this->assertStringNotContainsString('has:attachment', $query);
    }

    // ---------------------------------------------------------------------
    // Attachment trigger
    // ---------------------------------------------------------------------

    public function test_attachment_trigger_is_recognized(): void
    {
        $strategy = $this->makeStrategy();

        $result = $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received_with_attachment', 1000),
            new DateTimeImmutable,
        );

        $this->assertNotSame('unsupported_trigger', $result->reason);
        $this->assertCount(1, $this->capturedFetchCalls);
    }

    public function test_attachment_trigger_requires_drive_scope(): void
    {
        $strategy = $this->makeStrategy(connectionHasDriveScope: false);

        $result = $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received_with_attachment', 1000),
            new DateTimeImmutable,
        );

        $this->assertFalse($result->executed);
        $this->assertSame('drive_connection_required', $result->reason);
        $this->assertCount(0, $this->capturedFetchCalls);
    }

    public function test_cursor_not_advanced_when_drive_scope_missing(): void
    {
        $workflows = $this->createMock(WorkflowRepository::class);
        $workflows->expects($this->never())->method('saveTrigger');

        $strategy = $this->makeStrategy(
            connectionHasDriveScope: false,
            workflowsOverride: $workflows,
        );

        $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received_with_attachment', 1000),
            new DateTimeImmutable,
        );
    }

    public function test_attachment_trigger_appends_has_attachment_to_null_query(): void
    {
        $strategy = $this->makeStrategy();

        $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received_with_attachment', 1000),
            new DateTimeImmutable,
        );

        $this->assertCount(1, $this->capturedFetchCalls);
        $this->assertSame('has:attachment', $this->capturedFetchCalls[0]['query']);
    }

    public function test_attachment_trigger_preserves_existing_query_and_appends_fragment(): void
    {
        $strategy = $this->makeStrategy();

        $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received_with_attachment', 1000, config: ['subject' => 'invoice']),
            new DateTimeImmutable,
        );

        $query = (string) $this->capturedFetchCalls[0]['query'];
        $this->assertStringContainsString('subject:invoice', $query);
        $this->assertStringContainsString('has:attachment', $query);
    }

    public function test_attachment_trigger_does_not_duplicate_has_attachment_fragment(): void
    {
        $strategy = $this->makeStrategy();

        $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received_with_attachment', 1000, config: ['query' => 'is:unread has:attachment']),
            new DateTimeImmutable,
        );

        $query = (string) $this->capturedFetchCalls[0]['query'];
        $this->assertSame(1, substr_count($query, 'has:attachment'));
    }

    public function test_attachment_trigger_dispatches_enriched_payload_to_invocation_path(): void
    {
        $executions = $this->createMock(ExecutionRepository::class);
        $executions->method('hasInProgressForWorkflow')->willReturn(false);
        $executions->expects($this->once())
            ->method('findByIdempotencyKey')
            ->with(42, 'gmail:42:msg-1')
            ->willReturn(null);

        $strategy = $this->makeStrategy(
            executionsOverride: $executions,
            returnedMessageIds: ['msg-1'],
            message: $this->messageWithAttachment(),
        );

        $strategy->process(
            $this->workflow(),
            $this->trigger('new_email_received_with_attachment', 1000),
            new DateTimeImmutable,
        );
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @param  array<int, string>  $returnedMessageIds
     */
    private function makeStrategy(
        bool $connectionHasDriveScope = true,
        ?WorkflowRepository $workflowsOverride = null,
        ?ExecutionRepository $executionsOverride = null,
        array $returnedMessageIds = [],
        ?GoogleGmailMessage $message = null,
    ): GmailPollStrategy {
        $this->capturedFetchCalls = [];
        $captured = &$this->capturedFetchCalls;

        $workflows = $workflowsOverride ?? $this->createMock(WorkflowRepository::class);
        // WorkflowTrigger is final, so PHPUnit's auto-return-value generation
        // for saveTrigger() cannot construct one. Stub the return explicitly.
        if ($workflowsOverride === null) {
            $workflows->method('saveTrigger')->willReturnArgument(0);
        }

        $connections = $this->createMock(ConnectionRepository::class);
        $connections->method('findForUser')->willReturn($this->connection($connectionHasDriveScope));

        $executions = $executionsOverride ?? $this->createMock(ExecutionRepository::class);
        if ($executionsOverride === null) {
            $executions->method('hasInProgressForWorkflow')->willReturn(false);
            $executions->method('findByIdempotencyKey')->willReturn(null);
        }

        $credentialsResolver = $this->createMock(GoogleCredentialsResolver::class);
        $credentialsResolver->method('resolve')->willReturn(
            new ResolvedGoogleCredentials('test-token', 'test-refresh', null),
        );

        $message ??= new GoogleGmailMessage;
        $messageIdList = $returnedMessageIds;

        $reader = $this->createMock(GmailMessageReader::class);
        $reader->method('listMessageIds')->willReturnCallback(
            function ($creds, $after, $labelIds = [], $query = null) use (&$captured, $messageIdList) {
                $captured[] = ['after' => $after, 'labelIds' => $labelIds, 'query' => $query];

                return $messageIdList;
            },
        );
        $reader->method('getMessage')->willReturn($message);

        $payloadBuilder = new GmailMessagePayloadBuilder;

        $attachmentReader = $this->createMock(GmailAttachmentReader::class);
        $attachmentReader->method('read')->willReturn('fake-bytes');

        $uploader = $this->createMock(GoogleDriveFileUploader::class);
        $uploader->method('uploadWithMetadata')->willReturn([
            'id' => 'fake-drive-id',
            'web_view_link' => 'https://drive.google.com/file/d/fake-drive-id/view',
        ]);

        $materializer = new GmailAttachmentMaterializer($attachmentReader, $uploader);

        $fetch = new FetchGmailTriggerMessagesAction(
            $credentialsResolver,
            $reader,
            $payloadBuilder,
        );

        $fetchWithAttachments = new FetchGmailTriggerMessagesWithAttachmentsAction(
            $credentialsResolver,
            $reader,
            $payloadBuilder,
            $materializer,
        );

        $runWorkflow = new RunWorkflowAction(
            $this->createMock(WorkflowRepository::class),
            $this->createMock(ExecutionRepository::class),
            $this->createMock(TemplateResolver::class),
            $this->createMock(ActionInvoker::class),
            new WorkflowSnapshotBuilder,
        );

        return new GmailPollStrategy(
            $workflows,
            $connections,
            $executions,
            $fetch,
            $fetchWithAttachments,
            $runWorkflow,
            new GmailTriggerQueryComposer,
        );
    }

    private function connection(bool $hasDriveScope): Connection
    {
        $scopes = [
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/gmail.readonly',
        ];

        if ($hasDriveScope) {
            $scopes[] = 'https://www.googleapis.com/auth/drive.file';
        }

        return new Connection(
            id: 1,
            userId: 1,
            provider: 'google',
            externalAccountId: 'google-sub',
            email: 'user@example.com',
            displayName: 'User',
            credentials: new ConnectionCredentials(null, null, null),
            scopes: $scopes,
            status: ConnectionStatus::Active,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
    }

    private function workflow(): Workflow
    {
        return new Workflow(
            id: 42,
            userId: 1,
            name: 'Test',
            description: null,
            status: WorkflowStatus::Active,
            deletedAt: null,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function trigger(
        string $triggerKey,
        ?int $pollCursor,
        array $config = [],
    ): WorkflowTrigger {
        return new WorkflowTrigger(
            id: 1,
            workflowId: 42,
            integrationKey: 'google.gmail',
            triggerKey: $triggerKey,
            connectionId: 1,
            strategy: 'poll',
            config: $config,
            intervalMinutes: 5,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
            pollCursor: $pollCursor,
            nextPollAt: null,
        );
    }

    private function messageWithAttachment(): GoogleGmailMessage
    {
        $body = new GoogleGmailMessagePartBody;
        $body->setAttachmentId('att-1');
        $body->setSize(100);

        $part = new GoogleGmailMessagePart;
        $part->setMimeType('application/pdf');
        $part->setFilename('invoice.pdf');
        $part->setBody($body);

        $root = new GoogleGmailMessagePart;
        $root->setMimeType('multipart/mixed');
        $root->setBody(new GoogleGmailMessagePartBody);
        $root->setParts([$part]);
        $root->setHeaders([
            $this->header('From', 'sender@example.com'),
            $this->header('Subject', 'Test'),
        ]);

        $message = new GoogleGmailMessage;
        $message->setId('msg-1');
        $message->setThreadId('thread-1');
        $message->setSnippet('snippet');
        $message->setInternalDate('1759500000000');
        $message->setLabelIds(['INBOX']);
        $message->setPayload($root);

        return $message;
    }

    private function header(string $name, string $value): GoogleGmailMessagePartHeader
    {
        $header = new GoogleGmailMessagePartHeader;
        $header->setName($name);
        $header->setValue($value);

        return $header;
    }
}
