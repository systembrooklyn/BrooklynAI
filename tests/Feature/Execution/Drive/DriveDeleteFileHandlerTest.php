<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveDeleteFileHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveDeleteFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_delegates_and_returns_deleted_flag(): void
    {
        $drive = new FakeGoogleDriveClient;

        $handler = new DriveDeleteFileHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, ['file_id' => 'f1']);

        $this->assertSame(['deleted' => true, 'file_id' => 'f1'], $result);
        $this->assertCount(1, $drive->deleteFileCalls);
        $this->assertSame('f1', $drive->deleteFileCalls[0]['file_id']);
    }

    public function test_delete_throws_on_missing_file_id(): void
    {
        $handler = new DriveDeleteFileHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('file_id');

        $handler->handle(1, null, []);
    }

    public function test_delete_passes_connection_id_through(): void
    {
        $resolver = new FakeGoogleCredentialsResolver;
        $handler = new DriveDeleteFileHandler($resolver, new FakeGoogleDriveClient);

        $handler->handle(42, 7, ['file_id' => 'f1']);

        $this->assertSame(42, $resolver->calls[0]['userId']);
        $this->assertSame(7, $resolver->calls[0]['connectionId']);
    }
}
