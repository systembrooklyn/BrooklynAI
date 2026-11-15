<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveGetFileDetailsHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveGetFileDetailsHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handler_delegates_and_returns_file(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextGetFileReturn = [
            'id' => 'file-1',
            'name' => 'report.pdf',
            'mime_type' => 'application/pdf',
        ];

        $handler = new DriveGetFileDetailsHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, ['file_id' => 'file-1']);

        $this->assertSame('file-1', $result['id']);
        $this->assertCount(1, $drive->getCalls);
        $this->assertSame('file-1', $drive->getCalls[0]['file_id']);
    }

    public function test_handler_throws_on_missing_file_id(): void
    {
        $handler = new DriveGetFileDetailsHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('file_id');

        $handler->handle(1, null, []);
    }
}
