<?php

namespace Tests\Unit\Integrations;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentReader;
use Google\Client as GoogleClient;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\MessagePartBody as GoogleGmailMessagePartBody;
use PHPUnit\Framework\TestCase;

final class GmailAttachmentReaderTest extends TestCase
{
    public function test_decodes_base64url_and_returns_raw_bytes(): void
    {
        $raw = 'Hello, attachment!';
        $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $reader = new StubGmailAttachmentReader($this->partWith($encoded));

        $this->assertSame(
            $raw,
            $reader->read($this->credentials(), 'msg-1', 'att-1'),
        );
    }

    public function test_decodes_multibyte_content_round_trip(): void
    {
        $raw = "ملف مرفق — résumé — 日本語";
        $encoded = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        $reader = new StubGmailAttachmentReader($this->partWith($encoded));

        $this->assertSame(
            $raw,
            $reader->read($this->credentials(), 'msg-1', 'att-1'),
        );
    }

    public function test_returns_empty_string_when_data_is_null(): void
    {
        $part = new GoogleGmailMessagePartBody;

        $reader = new StubGmailAttachmentReader($part);

        $this->assertSame(
            '',
            $reader->read($this->credentials(), 'msg-1', 'att-1'),
        );
    }

    public function test_returns_empty_string_when_data_is_empty(): void
    {
        $reader = new StubGmailAttachmentReader($this->partWith(''));

        $this->assertSame(
            '',
            $reader->read($this->credentials(), 'msg-1', 'att-1'),
        );
    }

    public function test_transient_failure_is_retryable(): void
    {
        $reader = new ThrowingGmailAttachmentReader(
            new GoogleServiceException('rate limited', 429),
        );

        try {
            $reader->read($this->credentials(), 'msg-1', 'att-1');
            $this->fail('Expected GmailProviderException.');
        } catch (GmailProviderException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_server_error_is_retryable(): void
    {
        $reader = new ThrowingGmailAttachmentReader(
            new GoogleServiceException('backend unavailable', 503),
        );

        try {
            $reader->read($this->credentials(), 'msg-1', 'att-1');
            $this->fail('Expected GmailProviderException.');
        } catch (GmailProviderException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_not_found_is_not_retryable(): void
    {
        $reader = new ThrowingGmailAttachmentReader(
            new GoogleServiceException('attachment not found', 404),
        );

        try {
            $reader->read($this->credentials(), 'msg-1', 'att-1');
            $this->fail('Expected GmailProviderException.');
        } catch (GmailProviderException $e) {
            $this->assertFalse($e->retryable);
        }
    }

    public function test_permanent_client_error_is_not_retryable(): void
    {
        $reader = new ThrowingGmailAttachmentReader(
            new GoogleServiceException('bad request', 400),
        );

        try {
            $reader->read($this->credentials(), 'msg-1', 'att-1');
            $this->fail('Expected GmailProviderException.');
        } catch (GmailProviderException $e) {
            $this->assertFalse($e->retryable);
        }
    }

    private function credentials(): ResolvedGoogleCredentials
    {
        return new ResolvedGoogleCredentials(
            accessToken: 'test-token',
            refreshToken: 'test-refresh',
            expiresAt: null,
        );
    }

    private function partWith(?string $data): GoogleGmailMessagePartBody
    {
        $part = new GoogleGmailMessagePartBody;

        if ($data !== null) {
            $part->setData($data);
        }

        return $part;
    }
}

final class StubGmailAttachmentReader extends GmailAttachmentReader
{
    public function __construct(private readonly GoogleGmailMessagePartBody $part) {}

    protected function fetchFromApi(
        GoogleGmailService $service,
        string $messageId,
        string $attachmentId,
    ): GoogleGmailMessagePartBody {
        return $this->part;
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
    {
        return new GoogleGmailService(new GoogleClient);
    }
}

final class ThrowingGmailAttachmentReader extends GmailAttachmentReader
{
    public function __construct(private readonly GoogleServiceException $e) {}

    protected function fetchFromApi(
        GoogleGmailService $service,
        string $messageId,
        string $attachmentId,
    ): GoogleGmailMessagePartBody {
        throw $this->e;
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
    {
        return new GoogleGmailService(new GoogleClient);
    }
}
