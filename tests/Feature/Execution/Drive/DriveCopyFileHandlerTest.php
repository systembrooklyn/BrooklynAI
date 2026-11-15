<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCopyFileHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveCopyFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_delegates_with_all_fields(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextCopyFileReturn = ['id' => 'copy-1', 'name' => 'copy.pdf', 'web_view_link' => null];

        $handler = new DriveCopyFileHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, [
            'file_id' => 'f1',
            'new_name' => 'copy.pdf',
            'folder_id' => 'folder-1',
        ]);

        $this->assertSame('copy-1', $result['id']);
        $this->assertCount(1, $drive->copyFileCalls);
        $this->assertSame('f1', $drive->copyFileCalls[0]['file_id']);
        $this->assertSame('copy.pdf', $drive->copyFileCalls[0]['new_name']);
        $this->assertSame('folder-1', $drive->copyFileCalls[0]['folder_id']);
    }

    public function test_copy_works_without_folder_id(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextCopyFileReturn = ['id' => 'copy-2', 'name' => 'x', 'web_view_link' => null];

        $handler = new DriveCopyFileHandler(new FakeGoogleCredentialsResolver, $drive);
        $handler->handle(1, null, [
            'file_id' => 'f1',
            'new_name' => 'x',
        ]);

        $this->assertNull($drive->copyFileCalls[0]['folder_id']);
    }

    public function test_copy_throws_on_missing_file_id(): void
    {
        $handler = new DriveCopyFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('file_id');

        $handler->handle(1, null, ['new_name' => 'x']);
    }

    public function test_copy_throws_on_missing_new_name(): void
    {
        $handler = new DriveCopyFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('new_name');

        $handler->handle(1, null, ['file_id' => 'f1']);
    }
}
