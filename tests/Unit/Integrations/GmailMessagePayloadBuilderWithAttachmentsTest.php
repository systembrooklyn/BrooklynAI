<?php

namespace Tests\Unit\Integrations;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveFileUploader;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentMaterializer;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentReader;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessagePayloadBuilder;
use DateTimeImmutable;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use PHPUnit\Framework\TestCase;

final class GmailMessagePayloadBuilderWithAttachmentsTest extends TestCase
{
    public function test_build_remains_unchanged_when_attachments_present(): void
    {
        $message = $this->messageWithAttachments([
            ['id' => 'att-1', 'filename' => 'one.pdf', 'mime' => 'application/pdf', 'size' => 100],
        ]);

        $builder = new GmailMessagePayloadBuilder;

        $payload = $builder->build($message);

        $this->assertArrayNotHasKey('attachments', $payload);
        $this->assertCount(14, $payload);
    }

    public function test_build_with_attachments_preserves_all_fourteen_keys(): void
    {
        $message = $this->messageWithAttachments([
            ['id' => 'att-1', 'filename' => 'one.pdf', 'mime' => 'application/pdf', 'size' => 100],
        ]);

        $materializer = new FixedGmailAttachmentMaterializer([
            [
                'gmail_attachment_id' => 'att-1',
                'drive_file_id' => 'drive-1',
                'filename' => 'one.pdf',
                'mime_type' => 'application/pdf',
                'size' => 100,
            ],
        ]);

        $builder = new GmailMessagePayloadBuilder;

        $payload = $builder->buildWithAttachments(
            $message,
            $this->credentials(),
            $materializer,
        );

        $expectedKeys = [
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
            'attachments',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $payload, "Missing key: {$key}");
        }

        $this->assertCount(15, $payload);
    }

    public function test_build_with_attachments_yields_empty_array_when_no_attachments(): void
    {
        $message = $this->messageWithoutAttachments();

        $materializer = new FixedGmailAttachmentMaterializer([]);

        $builder = new GmailMessagePayloadBuilder;

        $payload = $builder->buildWithAttachments(
            $message,
            $this->credentials(),
            $materializer,
        );

        $this->assertSame([], $payload['attachments']);
        $this->assertSame(0, $payload['attachment_count']);
        $this->assertSame([], $payload['attachment_ids']);
        $this->assertFalse($payload['has_attachment']);
    }

    public function test_build_with_attachments_preserves_order_of_materialized_attachments(): void
    {
        $message = $this->messageWithAttachments([
            ['id' => 'att-1', 'filename' => 'one.pdf', 'mime' => 'application/pdf', 'size' => 100],
            ['id' => 'att-2', 'filename' => 'two.pdf', 'mime' => 'application/pdf', 'size' => 200],
            ['id' => 'att-3', 'filename' => 'three.pdf', 'mime' => 'application/pdf', 'size' => 300],
        ]);

        $materializer = new FixedGmailAttachmentMaterializer([
            ['gmail_attachment_id' => 'att-1', 'drive_file_id' => 'drive-1', 'filename' => 'one.pdf', 'mime_type' => 'application/pdf', 'size' => 100],
            ['gmail_attachment_id' => 'att-2', 'drive_file_id' => 'drive-2', 'filename' => 'two.pdf', 'mime_type' => 'application/pdf', 'size' => 200],
            ['gmail_attachment_id' => 'att-3', 'drive_file_id' => 'drive-3', 'filename' => 'three.pdf', 'mime_type' => 'application/pdf', 'size' => 300],
        ]);

        $builder = new GmailMessagePayloadBuilder;

        $payload = $builder->buildWithAttachments(
            $message,
            $this->credentials(),
            $materializer,
        );

        $this->assertCount(3, $payload['attachments']);
        $this->assertSame('att-1', $payload['attachments'][0]['gmail_attachment_id']);
        $this->assertSame('att-2', $payload['attachments'][1]['gmail_attachment_id']);
        $this->assertSame('att-3', $payload['attachments'][2]['gmail_attachment_id']);
    }

    public function test_existing_attachment_metadata_keys_are_unchanged(): void
    {
        $message = $this->messageWithAttachments([
            ['id' => 'att-1', 'filename' => 'one.pdf', 'mime' => 'application/pdf', 'size' => 100],
            ['id' => 'att-2', 'filename' => 'two.pdf', 'mime' => 'application/pdf', 'size' => 200],
        ]);

        $materializer = new FixedGmailAttachmentMaterializer([]);

        $builder = new GmailMessagePayloadBuilder;

        $payload = $builder->buildWithAttachments(
            $message,
            $this->credentials(),
            $materializer,
        );

        $this->assertTrue($payload['has_attachment']);
        $this->assertSame(2, $payload['attachment_count']);
        $this->assertSame(['att-1', 'att-2'], $payload['attachment_ids']);
    }

    public function test_build_does_not_invoke_the_materializer(): void
    {
        $message = $this->messageWithAttachments([
            ['id' => 'att-1', 'filename' => 'one.pdf', 'mime' => 'application/pdf', 'size' => 100],
        ]);

        $builder = new GmailMessagePayloadBuilder;

        $payload = $builder->build($message);

        $this->assertArrayNotHasKey('attachments', $payload);
    }

    private function credentials(): ResolvedGoogleCredentials
    {
        return new ResolvedGoogleCredentials(
            accessToken: 'test-access-token',
            refreshToken: 'test-refresh-token',
            expiresAt: null,
        );
    }

    /**
     * @param  array<int, array{id: string, filename: string, mime: string, size: int}>  $attachments
     */
    private function messageWithAttachments(array $attachments): GoogleGmailMessage
    {
        $parts = [];

        foreach ($attachments as $a) {
            $body = new GoogleGmailMessagePartBody;
            $body->setAttachmentId($a['id']);
            $body->setSize($a['size']);

            $part = new GoogleGmailMessagePart;
            $part->setMimeType($a['mime']);
            $part->setFilename($a['filename']);
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
            $this->header('To', 'recipient@example.com'),
            $this->header('Subject', 'Test Subject'),
        ]);

        $message = new GoogleGmailMessage;
        $message->setId('msg-1');
        $message->setThreadId('thread-1');
        $message->setSnippet('snippet');
        $message->setInternalDate((string) ((new DateTimeImmutable('2026-10-05 12:00:00'))->getTimestamp() * 1000));
        $message->setLabelIds(['INBOX']);
        $message->setPayload($root);

        return $message;
    }

    private function messageWithoutAttachments(): GoogleGmailMessage
    {
        $textBody = new GoogleGmailMessagePartBody;
        $textBody->setData(rtrim(strtr(base64_encode('Hello'), '+/', '-_'), '='));

        $textPart = new GoogleGmailMessagePart;
        $textPart->setMimeType('text/plain');
        $textPart->setBody($textBody);
        $textPart->setHeaders([
            $this->header('From', 'sender@example.com'),
            $this->header('To', 'recipient@example.com'),
            $this->header('Subject', 'Test Subject'),
        ]);

        $message = new GoogleGmailMessage;
        $message->setId('msg-2');
        $message->setThreadId('thread-2');
        $message->setSnippet('snippet');
        $message->setInternalDate((string) ((new DateTimeImmutable('2026-10-05 12:00:00'))->getTimestamp() * 1000));
        $message->setLabelIds(['INBOX']);
        $message->setPayload($textPart);

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

final class FixedGmailAttachmentMaterializer extends GmailAttachmentMaterializer
{
    /** @param array<int, array<string, mixed>> $result */
    public function __construct(private readonly array $result)
    {
        parent::__construct(
            new GmailAttachmentReader,
            new GoogleDriveFileUploader,
        );
    }

    public function materialize(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        array $parts,
    ): array {
        return $this->result;
    }
}
