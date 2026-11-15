<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveCreateFileHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleDriveClient;
USE Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\TestCase;

final class DriveCreateFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_handler_delegates_and_returns_drive_file(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextCreateFileReturn = [
            'id' => 'file-abc',
            'name' => 'report.txt',
            'mime_type' => 'text/plain',
            'size' => '15',
            'web_view_link' => 'https://drive.google.com/file/d/file-abc/view',
            'parents' => [],
            'description' => null,
            'properties' => [],
            'created_time' => '2026-01-01T00:00:00Z',
            'modified_time' => '2026-01-01T00:00:00Z',
        ];

        $handler = new DriveCreateFileHandler($this->fakeResolver(), $drive);

        $result = $handler->handle(1, null, [
            'filename' => 'report.txt',
            'content' => 'Hello, Drive!',
            'mime_type' => 'text/plain',
        ]);

        $this->assertSame('file-abc', $result['id']);
        $this->assertSame('report.txt', $result['name']);
        $this->assertCount(1, $drive->createFileCalls);
        $this->assertSame('report.txt', $drive->createFileCalls[0]['filename']);
        $this->assertSame('Hello, Drive!', $drive->createFileCalls[0]['content']);
        $this->assertSame('text/plain', $drive->createFileCalls[0]['mime_type']);
        $this->assertNull($drive->createFileCalls[0]['folder_id']);
    }

    public function test_handler_passes_folder_id_through(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextCreateFileReturn = [
            'id' => 'file-1', 'name' => 'x', 'mime_type' => 'text/plain', 'size' => '1',
            'web_view_link' => null, 'parents' => ['folder-1'], 'description' => null,
            'properties' => [], 'created_time' => null, 'modified_time' => null,
        ];

        $handler = new DriveCreateFileHandler($this->fakeResolver(), $drive);

        $handler->handle(1, null, [
            'filename' => 'x',
            'content' => 'y',
            'mime_type' => 'text/plain',
            'folder_id' => 'folder-1',
        ]);

        $this->assertSame('folder-1', $drive->createFileCalls[0]['folder_id']);
    }

    public function test_handler_throws_on_missing_filename(): void
    {
        $handler = new DriveCreateFileHandler($this->fakeResolver(), new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('filename');

        $handler->handle(1, null, ['content' => 'x', 'mime_type' => 'text/plain']);
    }

    public function test_handler_throws_on_missing_content(): void
    {
        $handler = new DriveCreateFileHandler($this->fakeResolver(), new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('content');

        $handler->handle(1, null, ['filename' => 'x', 'mime_type' => 'text/plain']);
    }

    public function test_handler_throws_on_missing_mime_type(): void
    {
        $handler = new DriveCreateFileHandler($this->fakeResolver(), new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('mime_type');

        $handler->handle(1, null, ['filename' => 'x', 'content' => 'y']);
    }

    public function test_handler_passes_connection_id_through(): void
    {
        $resolver = new FakeGoogleCredentialsResolver;
        $drive = new FakeGoogleDriveClient;
        $drive->nextCreateFileReturn = [
            'id' => 'f', 'name' => 'x', 'mime_type' => 'text/plain', 'size' => '1',
            'web_view_link' => null, 'parents' => [], 'description' => null,
            'properties' => [], 'created_time' => null, 'modified_time' => null,
        ];

        $handler = new DriveCreateFileHandler($resolver, $drive);
        $handler->handle(42, 7, ['filename' => 'x', 'content' => 'y', 'mime_type' => 'text/plain']);

        $this->assertCount(1, $resolver->calls);
        $this->assertSame(42, $resolver->calls[0]['userId']);
        $this->assertSame(7, $resolver->calls[0]['connectionId']);
    }

    private function fakeResolver(): GoogleCredentialsResolver
    {
        return new FakeGoogleCredentialsResolver;
    }
}
