<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveDownloadFileHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveDownloadFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_download_writes_bytes_to_storage(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextDownloadFileReturn = 'binary-file-content';

        $handler = new DriveDownloadFileHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(42, null, ['file_id' => 'file-1']);

        $this->assertSame('file-1', $result['file_id']);
        $this->assertSame(19, $result['size']);
        $this->assertStringStartsWith('drive-downloads/42/', $result['storage_path']);

        $this->assertTrue(
            Storage::disk('local')->exists($result['storage_path']),
            'Downloaded file must exist in local storage.',
        );
        $this->assertSame('binary-file-content', Storage::disk('local')->get($result['storage_path']));
    }

    public function test_download_throws_on_missing_file_id(): void
    {
        $handler = new DriveDownloadFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('file_id');

        $handler->handle(1, null, []);
    }

    public function test_download_delegates_with_correct_file_id(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextDownloadFileReturn = 'x';

        $handler = new DriveDownloadFileHandler(new FakeGoogleCredentialsResolver, $drive);
        $handler->handle(1, null, ['file_id' => 'abc123']);

        $this->assertCount(1, $drive->downloadFileCalls);
        $this->assertSame('abc123', $drive->downloadFileCalls[0]['file_id']);
    }

    public function test_download_passes_connection_id_through(): void
    {
        $resolver = new FakeGoogleCredentialsResolver;
        $drive = new FakeGoogleDriveClient;
        $drive->nextDownloadFileReturn = 'x';

        $handler = new DriveDownloadFileHandler($resolver, $drive);
        $handler->handle(42, 7, ['file_id' => 'f']);

        $this->assertSame(42, $resolver->calls[0]['userId']);
        $this->assertSame(7, $resolver->calls[0]['connectionId']);
    }
}
