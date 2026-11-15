<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveListAccessibleFilesHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveListAccessibleFilesHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_without_folder_uses_trashed_filter_only(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextListFilesReturn = [
            'files' => [
                ['id' => 'f1', 'name' => 'doc.pdf', 'mime_type' => 'application/pdf'],
            ],
            'next_page_token' => null,
        ];

        $handler = new DriveListAccessibleFilesHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, []);

        $this->assertCount(1, $result['files']);
        $this->assertSame('f1', $result['files'][0]['id']);
        $this->assertCount(1, $drive->listFilesCalls);
        $this->assertSame(['trashed=false'], $drive->listFilesCalls[0]['query']);
        $this->assertSame(100, $drive->listFilesCalls[0]['page_size']);
    }

    public function test_list_with_folder_id_adds_parent_filter(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextListFilesReturn = ['files' => [], 'next_page_token' => null];

        $handler = new DriveListAccessibleFilesHandler(new FakeGoogleCredentialsResolver, $drive);
        $handler->handle(1, null, [
            'folder_id' => 'folder-abc',
            'page_size' => 25,
        ]);

        $query = $drive->listFilesCalls[0]['query'];
        $this->assertContains('trashed=false', $query);
        $this->assertContains("'folder-abc' in parents", $query);
        $this->assertSame(25, $drive->listFilesCalls[0]['page_size']);
    }

    public function test_page_size_is_clamped_to_safe_range(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextListFilesReturn = ['files' => [], 'next_page_token' => null];

        $handler = new DriveListAccessibleFilesHandler(new FakeGoogleCredentialsResolver, $drive);

        $handler->handle(1, null, ['page_size' => 5000]);
        $this->assertSame(1000, $drive->listFilesCalls[0]['page_size']);

        $handler->handle(1, null, ['page_size' => 0]);
        $this->assertSame(1, $drive->listFilesCalls[1]['page_size']);
    }

    public function test_invalid_page_size_falls_back_to_default(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextListFilesReturn = ['files' => [], 'next_page_token' => null];

        $handler = new DriveListAccessibleFilesHandler(new FakeGoogleCredentialsResolver, $drive);
        $handler->handle(1, null, ['page_size' => 'not-a-number']);

        $this->assertSame(100, $drive->listFilesCalls[0]['page_size']);
    }
}
