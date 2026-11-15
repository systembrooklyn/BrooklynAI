<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveRenameFileHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveRenameFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_rename_delegates_via_update_metadata(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextUpdateMetadataReturn = ['id' => 'f1', 'name' => 'new-name.pdf'];

        $handler = new DriveRenameFileHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, [
            'file_id' => 'f1',
            'new_name' => 'new-name.pdf',
        ]);

        $this->assertSame('f1', $result['id']);
        $this->assertCount(1, $drive->updateMetadataCalls);
        $this->assertSame('f1', $drive->updateMetadataCalls[0]['file_id']);
        $this->assertSame(['name' => 'new-name.pdf'], $drive->updateMetadataCalls[0]['updates']);
    }

    public function test_rename_throws_on_missing_file_id(): void
    {
        $handler = new DriveRenameFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('file_id');

        $handler->handle(1, null, ['new_name' => 'x']);
    }

    public function test_rename_throws_on_missing_new_name(): void
    {
        $handler = new DriveRenameFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('new_name');

        $handler->handle(1, null, ['file_id' => 'f1']);
    }
}
