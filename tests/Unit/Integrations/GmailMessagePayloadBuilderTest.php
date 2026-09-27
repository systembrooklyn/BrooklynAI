<?php

namespace Tests\Unit\Integrations;

use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailMessagePayloadBuilder;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Google\Service\Gmail\MessagePart as GoogleGmailMessagePart;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use Google\Service\Gmail\MessagePartHeader as GoogleGmailMessagePartHeader;
use PHPUnit\Framework\TestCase;

class GmailMessagePayloadBuilderTest extends TestCase
{
    private GmailMessagePayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();

        $this->builder = new GmailMessagePayloadBuilder;
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function header(string $name, string $value): GoogleGmailMessagePartHeader
    {
        return new GoogleGmailMessagePartHeader([
            'name' => $name,
            'value' => $value,
        ]);
    }

    private function encodeBody(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * @param  array<int, string>  $attachmentIds
     */
    private function buildMessage(
        array $headers,
        string $body,
        array $attachmentIds = [],
        array $overrides = [],
    ): GoogleGmailMessage {
        $encodedBody = $this->encodeBody($body);

        $textPart = new GoogleGmailMessagePart([
            'mimeType' => 'text/plain',
            'body' => new GoogleGmailMessagePartBody([
                'data' => $encodedBody,
                'size' => strlen($body),
            ]),
        ]);

        if (empty($attachmentIds)) {
            $payload = new GoogleGmailMessagePart([
                'mimeType' => 'text/plain',
                'headers' => array_values($headers),
                'body' => new GoogleGmailMessagePartBody([
                    'data' => $encodedBody,
                    'size' => strlen($body),
                ]),
            ]);
        } else {
            $parts = [$textPart];
            foreach ($attachmentIds as $i => $attachmentId) {
                $parts[] = new GoogleGmailMessagePart([
                    'partId' => (string) ($i + 2),
                    'mimeType' => 'application/pdf',
                    'filename' => 'file-'.($i + 1).'.pdf',
                    'body' => new GoogleGmailMessagePartBody([
                        'attachmentId' => $attachmentId,
                        'size' => 1024,
                    ]),
                ]);
            }

            $payload = new GoogleGmailMessagePart([
                'mimeType' => 'multipart/mixed',
                'headers' => array_values($headers),
                'parts' => $parts,
            ]);
        }

        return new GoogleGmailMessage(array_merge([
            'id' => 'msg-1',
            'threadId' => 'thread-1',
            'labelIds' => ['INBOX', 'UNREAD'],
            'snippet' => 'This is a snippet',
            'internalDate' => '1700000000000',
            'payload' => $payload,
        ], $overrides));
    }

    /**
     * @return array<string, GoogleGmailMessagePartHeader>
     */
    private function defaultHeaders(): array
    {
        return [
            'From' => $this->header('From', 'sender@example.com'),
            'To' => $this->header('To', 'recipient@example.com, other@example.com'),
            'Cc' => $this->header('Cc', 'cc1@example.com'),
            'Subject' => $this->header('Subject', 'Hello World'),
        ];
    }

    public function test_payload_contains_exactly_the_whitelisted_keys(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'Body text');

        $payload = $this->builder->build($message);

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

    public function test_message_and_thread_ids_are_mapped(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'Body', [], [
            'id' => 'msg-abc',
            'threadId' => 'thread-xyz',
        ]);

        $payload = $this->builder->build($message);

        $this->assertSame('msg-abc', $payload['message_id']);
        $this->assertSame('thread-xyz', $payload['thread_id']);
    }

    public function test_address_headers_are_mapped(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'Body');

        $payload = $this->builder->build($message);

        $this->assertSame('sender@example.com', $payload['from']);
        $this->assertSame(['recipient@example.com', 'other@example.com'], $payload['to']);
        $this->assertSame(['cc1@example.com'], $payload['cc']);
        $this->assertSame('Hello World', $payload['subject']);
    }

    public function test_snippet_and_body_are_mapped(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'The full body content.');

        $payload = $this->builder->build($message);

        $this->assertSame('This is a snippet', $payload['snippet']);
        $this->assertSame('The full body content.', $payload['body']);
    }

    public function test_received_at_epoch_ms_preserves_milliseconds(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'Body', [], [
            'internalDate' => '1700000000123',
        ]);

        $payload = $this->builder->build($message);

        $this->assertSame(1700000000123, $payload['received_at_epoch_ms']);
        $this->assertIsInt($payload['received_at_epoch_ms']);
    }

    public function test_received_at_is_iso8601_from_epoch_ms(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'Body', [], [
            'internalDate' => '1700000000000',
        ]);

        $payload = $this->builder->build($message);

        $parsed = new \DateTimeImmutable($payload['received_at']);
        $this->assertSame(1700000000, $parsed->getTimestamp());
    }

    public function test_labels_are_returned_as_array(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'Body', [], [
            'labelIds' => ['INBOX', 'UNREAD', 'IMPORTANT'],
        ]);

        $payload = $this->builder->build($message);

        $this->assertSame(['INBOX', 'UNREAD', 'IMPORTANT'], $payload['labels']);
    }

    public function test_labels_default_to_empty_array_when_missing(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'Body', [], [
            'labelIds' => null,
        ]);

        $payload = $this->builder->build($message);

        $this->assertSame([], $payload['labels']);
    }

    public function test_message_without_attachments_reports_no_attachments(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'No attachments here.');

        $payload = $this->builder->build($message);

        $this->assertFalse($payload['has_attachment']);
        $this->assertSame(0, $payload['attachment_count']);
        $this->assertSame([], $payload['attachment_ids']);
    }

    public function test_message_with_attachments_reports_correct_metadata(): void
    {
        $message = $this->buildMessage(
            $this->defaultHeaders(),
            'See attachments.',
            ['attach-1', 'attach-2', 'attach-3'],
        );

        $payload = $this->builder->build($message);

        $this->assertTrue($payload['has_attachment']);
        $this->assertSame(3, $payload['attachment_count']);
        $this->assertSame(['attach-1', 'attach-2', 'attach-3'], $payload['attachment_ids']);
    }

    public function test_output_contains_only_whitelisted_keys_and_no_raw_provider_fields(): void
    {
        $message = $this->buildMessage($this->defaultHeaders(), 'Body', ['attach-1']);

        $payload = $this->builder->build($message);

        // No token-like or raw-provider keys present.
        $forbidden = [
            'raw',
            'access_token',
            'refresh_token',
            'authorization',
            'historyId',
            'sizeEstimate',
            'payload',
            'parts',
            'mimeType',
            'attachment_data',
            'attachmentContent',
            'body_data',
        ];

        foreach ($forbidden as $key) {
            $this->assertArrayNotHasKey($key, $payload);
        }
    }

    public function test_output_is_deterministic_for_identical_input(): void
    {
        $headers = $this->defaultHeaders();

        $messageA = $this->buildMessage($headers, 'Body', ['a1']);
        $messageB = $this->buildMessage($headers, 'Body', ['a1']);

        // Same timestamp for both to compare deterministically.
        $messageA->setInternalDate('1700000000000');
        $messageB->setInternalDate('1700000000000');

        $payloadA = $this->builder->build($messageA);
        $payloadB = $this->builder->build($messageB);

        // received_at is derived from the timestamp; it must be identical
        // for identical epoch input.
        $this->assertSame($payloadA['received_at'], $payloadB['received_at']);
        $this->assertSame($payloadA['received_at_epoch_ms'], $payloadB['received_at_epoch_ms']);

        // All other fields are also deterministic.
        unset($payloadA['received_at'], $payloadB['received_at']);

        $this->assertSame($payloadA, $payloadB);
    }

    public function test_missing_headers_yield_empty_strings_and_arrays(): void
    {
        $message = new GoogleGmailMessage([
            'id' => 'msg-no-headers',
            'threadId' => 'thread-no-headers',
            'internalDate' => '1700000000000',
            'payload' => new GoogleGmailMessagePart([
                'mimeType' => 'text/plain',
                'body' => new GoogleGmailMessagePartBody([
                    'data' => $this->encodeBody(''),
                ]),
            ]),
        ]);

        $payload = $this->builder->build($message);

        $this->assertSame('', $payload['from']);
        $this->assertSame([], $payload['to']);
        $this->assertSame([], $payload['cc']);
        $this->assertSame('', $payload['subject']);
    }

    public function test_html_body_is_used_when_no_plain_text_part(): void
    {
        $html = '<p>Hello</p>';
        $encoded = $this->encodeBody($html);

        $message = new GoogleGmailMessage([
            'id' => 'msg-html',
            'threadId' => 'thread-html',
            'internalDate' => '1700000000000',
            'payload' => new GoogleGmailMessagePart([
                'mimeType' => 'text/html',
                'body' => new GoogleGmailMessagePartBody([
                    'data' => $encoded,
                    'size' => strlen($html),
                ]),
            ]),
        ]);

        $payload = $this->builder->build($message);

        $this->assertSame('<p>Hello</p>', $payload['body']);
    }
}
