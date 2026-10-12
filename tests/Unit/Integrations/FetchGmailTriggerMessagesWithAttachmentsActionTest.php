<?php

namespace Tests\Unit\Integrations;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Application\Actions\FetchGmailTriggerMessagesWithAttachmentsAction;
use App\Modules\Integrations\Application\DTOs\FetchGmailTriggerMessagesInput;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentMaterializer;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessagePayloadBuilder;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessageReader;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use PHPUnit\Framework\TestCase;

final class FetchGmailTriggerMessagesWithAttachmentsActionTest extends TestCase
{
    public function test_skips_messages_without_attachments(): void
    {
        $action = new FetchGmailTriggerMessagesWithAttachmentsAction(
            new PassthroughCredentialsResolver,
            new InMemoryGmailMessageReader(['msg-1' => $this->plainMessage()]),
            new GmailMessagePayloadBuilder,
            new CannedGmailAttachmentMaterializer([]),
        );

        $result = $action->execute($this->input());

        $this->assertSame([], $result);
    }

    public function test_enriches_messages_with_attachments(): void
    {
        $action = new FetchGmailTriggerMessagesWithAttachmentsAction(
            new PassthroughCredentialsResolver,
            new InMemoryGmailMessageReader(['msg-1' => $this->attachmentMessage()]),
            new GmailMessagePayloadBuilder,
            new CannedGmailAttachmentMaterializer([
                [
                    'gmail_attachment_id' => 'att-1',
                    'drive_file_id' => 'drive-1',
                    'filename' => 'invoice.pdf',
                    'mime_type' => 'application/pdf',
                    'size' => 100,
                ],
            ]),
        );

        $result = $action->execute($this->input());

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('attachments', $result[0]);
        $this->assertSame('drive-1', $result[0]['attachments'][0]['drive_file_id']);
        $this->assertTrue($result[0]['has_attachment']);
    }

    private function input(): FetchGmailTriggerMessagesInput
    {
        return new FetchGmailTriggerMessagesInput(
            userId: 1,
            connectionId: 1,
            afterEpochSeconds: 0,
            labelIds: [],
            query: 'has:attachment',
        );
    }

    private function plainMessage(): GoogleGmailMessage
    {
        return $this->buildMessage('msg-1', false);
    }

    private function attachmentMessage(): GoogleGmailMessage
    {
        return $this->buildMessage('msg-1', true);
    }

    private function buildMessage(string $id, bool $withAttachment): GoogleGmailMessage
    {
        $parts = [];

        if ($withAttachment) {
            $body = new GoogleGmailMessagePartBody;
            $body->setAttachmentId('att-1');
            $body->setSize(100);

            $part = new GoogleGmailMessagePart;
            $part->setMimeType('application/pdf');
            $part->setFilename('invoice.pdf');
            $part->setBody($body);

            $parts[] = $part;
        }

        $textBody = new GoogleGmailMessagePartBody;
        $textBody->setData(rtrim(strtr(base64_encode('Hello'), '+/', '-_'), '='));

        $textPart = new GoogleGmailMessagePart;
        $textPart->setMimeType('text/plain');
        $textPart->setBody($textBody);
        $parts[] = $textPart;

        $root = new GoogleGmailMessagePart;
        $root->setMimeType('multipart/mixed');
        $root->setBody(new GoogleGmailMessagePartBody);
        $root->setParts($parts);
        $root->setHeaders([
            $this->header('From', 'sender@example.com'),
            $this->header('Subject', 'Test'),
        ]);

        $message = new GoogleGmailMessage;
        $message->setId($id);
        $message->setThreadId('thread-1');
        $message->setSnippet('snippet');
        $message->setInternalDate('1759500000000');
        $message->setLabelIds(['INBOX']);
        $message->setPayload($root);

        return $message;
    }

    private function header(string $name, string $value): GoogleGmailMessagePartHeader
    {
        $h = new GoogleGmailMessagePartHeader;
        $h->setName($name);
        $h->setValue($value);

        return $h;
    }
}

final class PassthroughCredentialsResolver implements GoogleCredentialsResolver
{
    public function resolve(int $userId, ?int $connectionId = null): ResolvedGoogleCredentials
    {
        return new ResolvedGoogleCredentials(
            accessToken: 'test-token',
            refreshToken: 'test-refresh',
            expiresAt: null,
        );
    }
}

class InMemoryGmailMessageReader extends GmailMessageReader
{
    /** @param array<string, GoogleGmailMessage> $messages */
    public function __construct(private readonly array $messages) {}

    public function listMessageIds(
        ResolvedGoogleCredentials $credentials,
        int $afterEpochSeconds,
        array $labelIds = [],
        ?string $query = null,
    ): array {
        return array_keys($this->messages);
    }

    public function getMessage(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
    ): GoogleGmailMessage {
        return $this->messages[$messageId];
    }
}

class CannedGmailAttachmentMaterializer extends GmailAttachmentMaterializer
{
    /** @param array<int, array<string, mixed>> $result */
    public function __construct(private readonly array $result) {}

    public function materialize(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        array $parts,
    ): array {
        return $this->result;
    }
}
