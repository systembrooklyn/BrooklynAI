<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveSearchAccessibleFilesHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveSearchAccessibleFilesHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_composes_query_with_trashed_false_and_user_query(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextListFilesReturn = [
            'files' => [
                ['id' => 'match-1', 'name' => 'match.pdf'],
            ],
            'next_page_token' => null,
        ];

        $handler = new DriveSearchAccessibleFilesHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, [
            'query' => "name contains 'invoice'",
        ]);

        $this->assertCount(1, $result['files']);
        $this->assertSame('match-1', $result['files'][0]['id']);

        $composed = $drive->listFilesCalls[0]['query'];
        $this->assertContains('trashed=false', $composed);
        $this->assertContains("(name contains 'invoice')", $composed);
    }

    public function test_search_throws_on_missing_query(): void
    {
        $handler = new DriveSearchAccessibleFilesHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('query');

        $handler->handle(1, null, []);
    }

    public function test_search_throws_on_empty_query(): void
    {
        $handler = new DriveSearchAccessibleFilesHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('query');

        $handler->handle(1, null, ['query' => '   ']);
    }

    public function test_search_throws_on_whitespace_only_query(): void
    {
        $handler = new DriveSearchAccessibleFilesHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('query');

        $handler->handle(1, null, ['query' => "\t\n "]);
    }

    public function test_search_rejects_control_characters_in_query(): void
    {
        $handler = new DriveSearchAccessibleFilesHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('query');

        $handler->handle(1, null, ['query' => "name contains \x00evil"]);
    }

    public function test_search_page_size_clamped(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextListFilesReturn = ['files' => [], 'next_page_token' => null];

        $handler = new DriveSearchAccessibleFilesHandler(new FakeGoogleCredentialsResolver, $drive);
        $handler->handle(1, null, ['query' => 'x', 'page_size' => 99999]);

        $this->assertSame(1000, $drive->listFilesCalls[0]['page_size']);
    }
}
