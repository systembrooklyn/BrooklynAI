<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveUploadFileHandler;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveFileUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
USE Tests\Support\Fakes\FakeGoogleDriveFileUploader;
use Tests\TestCase;

final class DriveUploadFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_upload_from_storage_succeeds(): void
    {
        Storage::disk('local')->put('drive-downloads/1/abc.bin', 'binary-content');

        $uploader = new FakeGoogleDriveFileUploader;
        $uploader->nextUploadReturn = [
            'id' => 'drive-1',
            'web_view_link' => 'https://drive.google.com/file/d/drive-1/view',
        ];

        $handler = new DriveUploadFileHandler(new FakeGoogleCredentialsResolver, $uploader);
        $result = $handler->handle(1, null, [
            'storage_path' => 'drive-downloads/1/abc.bin',
            'filename' => 'output.pdf',
            'mime_type' => 'application/pdf',
        ]);

        $this->assertSame('drive-1', $result['file_id']);
        $this->assertSame('output.pdf', $result['filename']);
        $this->assertSame(14, $result['size']);

        $this->assertCount(1, $uploader->uploadCalls);
        $this->assertSame('binary-content', $uploader->uploadCalls[0]['bytes']);
        $this->assertSame('output.pdf', $uploader->uploadCalls[0]['filename']);
        $this->assertSame('application/pdf', $uploader->uploadCalls[0]['mime_type']);
    }

    public function test_upload_rejects_path_traversal(): void
    {
        $handler = new DriveUploadFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveFileUploader);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('storage_path');

        $handler->handle(1, null, [
            'storage_path' => '../../../etc/passwd',
            'filename' => 'x.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_upload_rejects_absolute_path(): void
    {
        $handler = new DriveUploadFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveFileUploader);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('storage_path');

        $handler->handle(1, null, [
            'storage_path' => '/etc/passwd',
            'filename' => 'x.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_upload_rejects_missing_file_in_storage(): void
    {
        $handler = new DriveUploadFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveFileUploader);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('File not found in storage');

        $handler->handle(1, null, [
            'storage_path' => 'drive-downloads/missing.bin',
            'filename' => 'x.pdf',
            'mime_type' => 'application/pdf',
        ]);
    }
}
