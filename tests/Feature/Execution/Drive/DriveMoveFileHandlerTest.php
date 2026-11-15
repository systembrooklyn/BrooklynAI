<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveMoveFileHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveMoveFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_move_delegates_with_file_and_parent(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextMoveFileReturn = ['id' => 'f1', 'parents' => ['new-parent']];

        $handler = new DriveMoveFileHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, [
            'file_id' => 'f1',
            'new_parent_id' => 'new-parent',
        ]);

        $this->assertSame('f1', $result['id']);
        $this->assertCount(1, $drive->moveFileCalls);
        $this->assertSame('f1', $drive->moveFileCalls[0]['file_id']);
        $this->assertSame('new-parent', $drive->moveFileCalls[0]['new_parent_id']);
    }

    public function test_move_throws_on_missing_file_id(): void
    {
        $handler = new DriveMoveFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('file_id');

        $handler->handle(1, null, ['new_parent_id' => 'p']);
    }

    public function test_move_throws_on_missing_new_parent_id(): void
    {
        $handler = new DriveMoveFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('new_parent_id');

        $handler->handle(1, null, ['file_id' => 'f1']);
    }
}
