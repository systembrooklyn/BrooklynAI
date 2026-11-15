<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveExportWorkspaceFileHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveExportWorkspaceFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_export_succeeds_and_writes_to_storage(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextExportFileReturn = str_repeat('x', 1024);

        $handler = new DriveExportWorkspaceFileHandler($this->fakeResolver(), $drive);
        $result = $handler->handle(1, null, [
            'file_id' => 'doc-1',
            'mime_type' => 'application/pdf',
        ]);

        $this->assertSame('doc-1', $result['file_id']);
        $this->assertSame(1024, $result['size']);
        $this->assertSame('application/pdf', $result['mime_type']);
        $this->assertStringEndsWith('.pdf', $result['storage_path']);

        // Safe universal assertion (works in every Laravel version).
        $this->assertTrue(
            Storage::disk('local')->exists($result['storage_path']),
            'Exported file must exist in local storage.',
        );
    }

    public function test_export_exceeding_10mb_is_rejected(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextExportFileReturn = str_repeat('x', 10 * 1024 * 1024 + 1);

        $handler = new DriveExportWorkspaceFileHandler($this->fakeResolver(), $drive);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('10 MB');

        $handler->handle(1, null, [
            'file_id' => 'doc-1',
            'mime_type' => 'application/pdf',
        ]);
    }

    public function test_export_rejects_missing_file_id(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextExportFileReturn = '';

        $handler = new DriveExportWorkspaceFileHandler($this->fakeResolver(), $drive);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('file_id');

        $handler->handle(1, null, ['mime_type' => 'application/pdf']);
    }

    private function fakeResolver(): GoogleCredentialsResolver
    {
        return new FakeGoogleCredentialsResolver;
    }
}
