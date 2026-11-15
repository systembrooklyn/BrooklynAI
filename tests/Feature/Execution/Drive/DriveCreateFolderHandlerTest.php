<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCreateFolderHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveCreateFolderHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_folder_without_parent(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextCreateFolderReturn = ['id' => 'folder-1', 'name' => 'Reports'];

        $handler = new DriveCreateFolderHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, ['name' => 'Reports']);

        $this->assertSame('folder-1', $result['id']);
        $this->assertCount(1, $drive->createFolderCalls);
        $this->assertSame('Reports', $drive->createFolderCalls[0]['name']);
        $this->assertNull($drive->createFolderCalls[0]['parent_id']);
    }

    public function test_create_folder_with_parent(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextCreateFolderReturn = ['id' => 'folder-2', 'name' => 'Sub'];

        $handler = new DriveCreateFolderHandler(new FakeGoogleCredentialsResolver, $drive);
        $handler->handle(1, null, [
            'name' => 'Sub',
            'parent_folder_id' => 'parent-1',
        ]);

        $this->assertSame('parent-1', $drive->createFolderCalls[0]['parent_id']);
    }

    public function test_create_folder_throws_on_missing_name(): void
    {
        $handler = new DriveCreateFolderHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('name');

        $handler->handle(1, null, ['parent_folder_id' => 'p']);
    }
}
