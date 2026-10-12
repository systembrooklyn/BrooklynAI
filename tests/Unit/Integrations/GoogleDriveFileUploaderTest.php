<?php

namespace Tests\Unit\Integrations;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Core\Exceptions\DriveProviderException;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveFileUploader;
use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile as GoogleDriveFile;
use Google\Service\Exception as GoogleServiceException;
use PHPUnit\Framework\TestCase;

final class GoogleDriveFileUploaderTest extends TestCase
{
    public function test_returns_created_drive_file_id(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(returnId: 'drive-file-abc');

        $id = $uploader->upload(
            $this->credentials(),
            'binary-bytes',
            'invoice.pdf',
            'application/pdf',
        );

        $this->assertSame('drive-file-abc', $id);
    }

    public function test_passes_filename_to_metadata(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(returnId: 'id');

        $uploader->upload(
            $this->credentials(),
            'bytes',
            'unique-name-xyz.doc',
            'application/msword',
        );

        $this->assertNotNull($uploader->lastMetadata);
        $this->assertSame('unique-name-xyz.doc', $uploader->lastMetadata->getName());
    }

    public function test_passes_mime_type_to_metadata_and_media(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(returnId: 'id');

        $uploader->upload(
            $this->credentials(),
            'bytes',
            'image.png',
            'image/png',
        );

        $this->assertNotNull($uploader->lastMetadata);
        $this->assertSame('image/png', $uploader->lastMetadata->getMimeType());
        $this->assertSame('image/png', $uploader->lastMediaMimeType);
    }

    public function test_passes_binary_bytes_to_upload(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(returnId: 'id');

        $uploader->upload(
            $this->credentials(),
            'raw-binary-payload-ñ-日本語',
            'file.bin',
            'application/octet-stream',
        );

        $this->assertSame('raw-binary-payload-ñ-日本語', $uploader->lastBytes);
    }

    public function test_public_api_surface_is_minimal(): void
    {
        $reflection = new \ReflectionClass(GoogleDriveFileUploader::class);

        $publicMethods = array_filter(
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
            fn (\ReflectionMethod $m) => ! $m->isConstructor(),
        );

        $names = array_map(fn (\ReflectionMethod $m) => $m->getName(), $publicMethods);
        sort($names);

        $this->assertSame(['upload', 'uploadWithMetadata'], $names);
    }

    public function test_uploader_has_no_share_or_permission_methods(): void
    {
        $reflection = new \ReflectionClass(GoogleDriveFileUploader::class);

        $allMethodNames = array_map(
            fn (\ReflectionMethod $m) => strtolower($m->getName()),
            $reflection->getMethods(),
        );

        foreach (['share', 'makepublic', 'createpermission', 'addpermission'] as $forbidden) {
            $this->assertNotContains(
                $forbidden,
                $allMethodNames,
                "GoogleDriveFileUploader must not expose a '$forbidden' method.",
            );
        }
    }

    public function test_transient_failure_is_retryable(): void
    {
        $uploader = new ThrowingGoogleDriveFileUploader(
            new GoogleServiceException('drive overloaded', 503),
        );

        try {
            $uploader->upload($this->credentials(), 'bytes', 'f.bin', 'application/octet-stream');
            $this->fail('Expected DriveProviderException.');
        } catch (DriveProviderException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_rate_limited_is_retryable(): void
    {
        $uploader = new ThrowingGoogleDriveFileUploader(
            new GoogleServiceException('rate limited', 429),
        );

        try {
            $uploader->upload($this->credentials(), 'bytes', 'f.bin', 'application/octet-stream');
            $this->fail('Expected DriveProviderException.');
        } catch (DriveProviderException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_forbidden_is_not_retryable(): void
    {
        $uploader = new ThrowingGoogleDriveFileUploader(
            new GoogleServiceException('forbidden', 403),
        );

        try {
            $uploader->upload($this->credentials(), 'bytes', 'f.bin', 'application/octet-stream');
            $this->fail('Expected DriveProviderException.');
        } catch (DriveProviderException $e) {
            $this->assertFalse($e->retryable);
        }
    }

    public function test_empty_drive_id_is_rejected(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(returnId: '');

        $this->expectException(DriveProviderException::class);

        $uploader->upload(
            $this->credentials(),
            'bytes',
            'f.bin',
            'application/octet-stream',
        );
    }

    // ---------------------------------------------------------------------
    // uploadWithMetadata
    // ---------------------------------------------------------------------

    public function test_upload_with_metadata_returns_id_and_link(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(
            returnId: 'drive-file-abc',
            returnLink: 'https://drive.google.com/file/d/drive-file-abc/view',
        );

        $result = $uploader->uploadWithMetadata(
            $this->credentials(),
            'bytes',
            'invoice.pdf',
            'application/pdf',
        );

        $this->assertSame('drive-file-abc', $result['id']);
        $this->assertSame(
            'https://drive.google.com/file/d/drive-file-abc/view',
            $result['web_view_link'],
        );
    }

    public function test_upload_with_metadata_returns_null_link_when_missing(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(
            returnId: 'drive-file-abc',
            returnLink: null,
        );

        $result = $uploader->uploadWithMetadata(
            $this->credentials(),
            'bytes',
            'invoice.pdf',
            'application/pdf',
        );

        $this->assertSame('drive-file-abc', $result['id']);
        $this->assertNull($result['web_view_link']);
    }

    public function test_upload_with_metadata_returns_null_link_when_empty_string(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(
            returnId: 'drive-file-abc',
            returnLink: '',
        );

        $result = $uploader->uploadWithMetadata(
            $this->credentials(),
            'bytes',
            'invoice.pdf',
            'application/pdf',
        );

        $this->assertSame('drive-file-abc', $result['id']);
        $this->assertNull($result['web_view_link']);
    }

    public function test_upload_returns_only_id_string_even_when_link_present(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(
            returnId: 'drive-file-abc',
            returnLink: 'https://drive.google.com/file/d/drive-file-abc/view',
        );

        $id = $uploader->upload(
            $this->credentials(),
            'bytes',
            'invoice.pdf',
            'application/pdf',
        );

        $this->assertIsString($id);
        $this->assertSame('drive-file-abc', $id);
    }

    public function test_upload_with_metadata_rejects_empty_id(): void
    {
        $uploader = new CapturingGoogleDriveFileUploader(returnId: '');

        $this->expectException(DriveProviderException::class);

        $uploader->uploadWithMetadata(
            $this->credentials(),
            'bytes',
            'f.bin',
            'application/octet-stream',
        );
    }

    private function credentials(): ResolvedGoogleCredentials
    {
        return new ResolvedGoogleCredentials(
            accessToken: 'test-token',
            refreshToken: 'test-refresh',
            expiresAt: null,
        );
    }
}

final class CapturingGoogleDriveFileUploader extends GoogleDriveFileUploader
{
    public ?GoogleDriveFile $lastMetadata = null;
    public ?string $lastBytes = null;
    public ?string $lastMediaMimeType = null;

    public function __construct(
        private readonly string $returnId,
        private readonly ?string $returnLink = null,
    ) {}

    protected function performUpload(
        GoogleDriveService $service,
        GoogleDriveFile $metadata,
        string $bytes,
        string $mimeType,
    ): GoogleDriveFile {
        $this->lastMetadata = $metadata;
        $this->lastBytes = $bytes;
        $this->lastMediaMimeType = $mimeType;

        $result = new GoogleDriveFile;
        $result->setId($this->returnId);

        if ($this->returnLink !== null) {
            $result->setWebViewLink($this->returnLink);
        }

        return $result;
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleDriveService
    {
        return new GoogleDriveService(new GoogleClient);
    }
}

final class ThrowingGoogleDriveFileUploader extends GoogleDriveFileUploader
{
    public function __construct(private readonly GoogleServiceException $e) {}

    protected function performUpload(
        GoogleDriveService $service,
        GoogleDriveFile $metadata,
        string $bytes,
        string $mimeType,
    ): GoogleDriveFile {
        throw $this->e;
    }

    protected function createService(ResolvedGoogleCredentials $credentials): GoogleDriveService
    {
        return new GoogleDriveService(new GoogleClient);
    }
}
