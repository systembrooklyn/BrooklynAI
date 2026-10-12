<?php

namespace Tests\Unit\Integrations;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Core\Exceptions\DriveProviderException;
use App\Modules\Integrations\Core\Exceptions\GmailProviderException;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveFileUploader;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentMaterializer;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailAttachmentReader;
use PHPUnit\Framework\TestCase;

final class GmailAttachmentMaterializerTest extends TestCase
{
    private const DRIVE_LINK_1 = 'https://drive.google.com/file/d/drive-1/view';

    public function test_empty_parts_returns_empty_array(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader([]),
            new MapGoogleDriveFileUploader([]),
        );

        $this->assertSame(
            [],
            $materializer->materialize($this->credentials(), 'msg-1', []),
        );
    }

    public function test_single_attachment_succeeds(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader(['att-1' => 'pdf-bytes']),
            new MapGoogleDriveFileUploader([
                'invoice.pdf' => ['id' => 'drive-file-1', 'web_view_link' => self::DRIVE_LINK_1],
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 1234)],
        );

        $this->assertCount(1, $result);
        $this->assertSame('att-1', $result[0]['gmail_attachment_id']);
        $this->assertSame('drive-file-1', $result[0]['drive_file_id']);
        $this->assertSame('invoice.pdf', $result[0]['filename']);
        $this->assertSame('application/pdf', $result[0]['mime_type']);
        $this->assertSame(strlen('pdf-bytes'), $result[0]['size']);
        $this->assertArrayNotHasKey('error', $result[0]);
    }

    public function test_multiple_attachments_succeed_in_order(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader([
                'att-1' => 'bytes-1',
                'att-2' => 'bytes-2',
                'att-3' => 'bytes-3',
            ]),
            new MapGoogleDriveFileUploader([
                'one.pdf' => ['id' => 'drive-1', 'web_view_link' => 'https://drive.google.com/file/d/drive-1/view'],
                'two.pdf' => ['id' => 'drive-2', 'web_view_link' => 'https://drive.google.com/file/d/drive-2/view'],
                'three.pdf' => ['id' => 'drive-3', 'web_view_link' => 'https://drive.google.com/file/d/drive-3/view'],
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [
                $this->part('att-1', 'one.pdf', 'application/pdf', 100),
                $this->part('att-2', 'two.pdf', 'application/pdf', 200),
                $this->part('att-3', 'three.pdf', 'application/pdf', 300),
            ],
        );

        $this->assertCount(3, $result);
        $this->assertSame('att-1', $result[0]['gmail_attachment_id']);
        $this->assertSame('drive-1', $result[0]['drive_file_id']);
        $this->assertSame('att-2', $result[1]['gmail_attachment_id']);
        $this->assertSame('drive-2', $result[1]['drive_file_id']);
        $this->assertSame('att-3', $result[2]['gmail_attachment_id']);
        $this->assertSame('drive-3', $result[2]['drive_file_id']);
    }

    public function test_gmail_download_failure_returns_null_drive_id_and_error(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader([
                'att-1' => GmailProviderException::permanent(404, 'not found'),
            ]),
            new MapGoogleDriveFileUploader([]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 1234)],
        );

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['drive_file_id']);
        $this->assertSame('gmail_download_failed', $result[0]['error']);
        $this->assertSame('att-1', $result[0]['gmail_attachment_id']);
        $this->assertSame('invoice.pdf', $result[0]['filename']);
        $this->assertSame('application/pdf', $result[0]['mime_type']);
    }

    public function test_drive_upload_failure_returns_null_drive_id_and_error(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader(['att-1' => 'bytes']),
            new MapGoogleDriveFileUploader([
                'invoice.pdf' => DriveProviderException::permanent(403, 'forbidden'),
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 1234)],
        );

        $this->assertCount(1, $result);
        $this->assertNull($result[0]['drive_file_id']);
        $this->assertSame('drive_upload_failed', $result[0]['error']);
    }

    public function test_one_failure_does_not_block_later_attachments(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader([
                'att-1' => 'bytes-1',
                'att-2' => GmailProviderException::permanent(404, 'not found'),
                'att-3' => 'bytes-3',
            ]),
            new MapGoogleDriveFileUploader([
                'one.pdf' => ['id' => 'drive-1', 'web_view_link' => 'https://drive.google.com/file/d/drive-1/view'],
                'three.pdf' => ['id' => 'drive-3', 'web_view_link' => 'https://drive.google.com/file/d/drive-3/view'],
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [
                $this->part('att-1', 'one.pdf', 'application/pdf', 100),
                $this->part('att-2', 'two.pdf', 'application/pdf', 200),
                $this->part('att-3', 'three.pdf', 'application/pdf', 300),
            ],
        );

        $this->assertCount(3, $result);

        $this->assertSame('drive-1', $result[0]['drive_file_id']);
        $this->assertArrayNotHasKey('error', $result[0]);

        $this->assertNull($result[1]['drive_file_id']);
        $this->assertSame('gmail_download_failed', $result[1]['error']);

        $this->assertSame('drive-3', $result[2]['drive_file_id']);
        $this->assertArrayNotHasKey('error', $result[2]);
    }

    public function test_no_credentials_or_provider_messages_leak_into_result(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader([
                'att-1' => GmailProviderException::permanent(404, 'SECRET-TOKEN-DO-NOT-LEAK'),
            ]),
            new MapGoogleDriveFileUploader([]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 1234)],
        );

        $serialized = (string) json_encode($result);

        $this->assertStringNotContainsString('SECRET-TOKEN-DO-NOT-LEAK', $serialized);
        $this->assertStringNotContainsString('test-access-token', $serialized);
        $this->assertStringNotContainsString('test-refresh-token', $serialized);
        $this->assertSame('gmail_download_failed', $result[0]['error']);
    }

    public function test_actual_byte_size_replaces_reported_size_on_success(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader(['att-1' => str_repeat('x', 999)]),
            new MapGoogleDriveFileUploader([
                'invoice.pdf' => ['id' => 'drive-1', 'web_view_link' => self::DRIVE_LINK_1],
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 12345)],
        );

        $this->assertSame(999, $result[0]['size']);
    }

    // ---------------------------------------------------------------------
    // web_view_link behavior
    // ---------------------------------------------------------------------

    public function test_successful_materialization_includes_web_view_link(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader(['att-1' => 'bytes']),
            new MapGoogleDriveFileUploader([
                'invoice.pdf' => ['id' => 'drive-1', 'web_view_link' => self::DRIVE_LINK_1],
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 1234)],
        );

        $this->assertArrayHasKey('web_view_link', $result[0]);
        $this->assertSame(self::DRIVE_LINK_1, $result[0]['web_view_link']);
    }

    public function test_gmail_failure_entry_includes_null_web_view_link(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader([
                'att-1' => GmailProviderException::permanent(404, 'not found'),
            ]),
            new MapGoogleDriveFileUploader([]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 1234)],
        );

        $this->assertArrayHasKey('web_view_link', $result[0]);
        $this->assertNull($result[0]['web_view_link']);
    }

    public function test_drive_failure_entry_includes_null_web_view_link(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader(['att-1' => 'bytes']),
            new MapGoogleDriveFileUploader([
                'invoice.pdf' => DriveProviderException::permanent(403, 'forbidden'),
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 1234)],
        );

        $this->assertArrayHasKey('web_view_link', $result[0]);
        $this->assertNull($result[0]['web_view_link']);
    }

    public function test_success_with_null_link_is_preserved(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader(['att-1' => 'bytes']),
            new MapGoogleDriveFileUploader([
                'invoice.pdf' => ['id' => 'drive-1', 'web_view_link' => null],
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [$this->part('att-1', 'invoice.pdf', 'application/pdf', 1234)],
        );

        $this->assertSame('drive-1', $result[0]['drive_file_id']);
        $this->assertArrayHasKey('web_view_link', $result[0]);
        $this->assertNull($result[0]['web_view_link']);
        $this->assertArrayNotHasKey('error', $result[0]);
    }

    public function test_partial_failure_mixes_populated_and_null_links(): void
    {
        $materializer = new GmailAttachmentMaterializer(
            new MapGmailAttachmentReader([
                'att-1' => 'bytes-1',
                'att-2' => GmailProviderException::permanent(404, 'not found'),
            ]),
            new MapGoogleDriveFileUploader([
                'one.pdf' => ['id' => 'drive-1', 'web_view_link' => self::DRIVE_LINK_1],
            ]),
        );

        $result = $materializer->materialize(
            $this->credentials(),
            'msg-1',
            [
                $this->part('att-1', 'one.pdf', 'application/pdf', 100),
                $this->part('att-2', 'two.pdf', 'application/pdf', 200),
            ],
        );

        $this->assertCount(2, $result);
        $this->assertSame(self::DRIVE_LINK_1, $result[0]['web_view_link']);
        $this->assertNull($result[1]['web_view_link']);
    }

    private function credentials(): ResolvedGoogleCredentials
    {
        return new ResolvedGoogleCredentials(
            accessToken: 'test-access-token',
            refreshToken: 'test-refresh-token',
            expiresAt: null,
        );
    }

    private function part(
        string $attachmentId,
        string $filename,
        string $mimeType,
        ?int $size = null,
    ): array {
        return [
            'attachment_id' => $attachmentId,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
        ];
    }
}

final class MapGmailAttachmentReader extends GmailAttachmentReader
{
    /** @param array<string, string|\Throwable> $responses */
    public function __construct(private readonly array $responses) {}

    public function read(
        ResolvedGoogleCredentials $credentials,
        string $messageId,
        string $attachmentId,
    ): string {
        $response = $this->responses[$attachmentId] ?? null;

        if ($response === null) {
            throw new \RuntimeException("No canned response for attachment {$attachmentId}");
        }

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return (string) $response;
    }
}

final class MapGoogleDriveFileUploader extends GoogleDriveFileUploader
{
    /**
     * @param  array<string, array{id: string, web_view_link: string|null}|\Throwable>  $responses
     */
    public function __construct(private readonly array $responses) {}

    public function uploadWithMetadata(
        ResolvedGoogleCredentials $credentials,
        string $bytes,
        string $filename,
        string $mimeType,
    ): array {
        $response = $this->responses[$filename] ?? null;

        if ($response === null) {
            throw new \RuntimeException("No canned response for file {$filename}");
        }

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }
}
