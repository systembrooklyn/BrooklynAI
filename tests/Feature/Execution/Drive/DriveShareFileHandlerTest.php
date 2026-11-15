<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveShareFileHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveShareFileHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_share_with_user_requires_email(): void
    {
        $handler = new DriveShareFileHandler($this->fakeResolver(), new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('email_address');

        $handler->handle(1, null, [
            'file_id' => 'f1',
            'type' => 'user',
            'role' => 'reader',
        ]);
    }

    public function test_share_with_domain_requires_domain(): void
    {
        $handler = new DriveShareFileHandler($this->fakeResolver(), new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('domain');

        $handler->handle(1, null, [
            'file_id' => 'f1',
            'type' => 'domain',
            'role' => 'reader',
        ]);
    }

    public function test_share_type_anyone_is_rejected(): void
    {
        $handler = new DriveShareFileHandler($this->fakeResolver(), new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('type:anyone is not permitted');

        $handler->handle(1, null, [
            'file_id' => 'f1',
            'type' => 'anyone',
            'role' => 'reader',
        ]);
    }

    public function test_share_with_invalid_role_is_rejected(): void
    {
        $handler = new DriveShareFileHandler($this->fakeResolver(), new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('role');

        $handler->handle(1, null, [
            'file_id' => 'f1',
            'type' => 'user',
            'role' => 'owner',
            'email_address' => 'a@example.com',
        ]);
    }

    public function test_share_with_user_succeeds(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextCreatePermissionReturn = [
            'permission_id' => 'perm-1',
            'type' => 'user',
            'role' => 'reader',
            'email_address' => 'a@example.com',
            'domain' => null,
        ];

        $handler = new DriveShareFileHandler($this->fakeResolver(), $drive);
        $result = $handler->handle(1, null, [
            'file_id' => 'f1',
            'type' => 'user',
            'role' => 'reader',
            'email_address' => 'a@example.com',
        ]);

        $this->assertSame('perm-1', $result['permission_id']);
        $this->assertSame('a@example.com', $result['email_address']);

        $this->assertCount(1, $drive->createPermissionCalls);
        $this->assertSame('f1', $drive->createPermissionCalls[0]['file_id']);
        $this->assertSame('user', $drive->createPermissionCalls[0]['type']);
        $this->assertSame('reader', $drive->createPermissionCalls[0]['role']);
        $this->assertSame('a@example.com', $drive->createPermissionCalls[0]['email_address']);
        $this->assertNull($drive->createPermissionCalls[0]['domain']);
    }

    public function test_share_with_domain_succeeds(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextCreatePermissionReturn = [
            'permission_id' => 'perm-2',
            'type' => 'domain',
            'role' => 'writer',
            'email_address' => null,
            'domain' => 'example.com',
        ];

        $handler = new DriveShareFileHandler($this->fakeResolver(), $drive);
        $result = $handler->handle(1, null, [
            'file_id' => 'f1',
            'type' => 'domain',
            'role' => 'writer',
            'domain' => 'example.com',
        ]);

        $this->assertSame('example.com', $result['domain']);
    }

    private function fakeResolver(): GoogleCredentialsResolver
    {
        return new FakeGoogleCredentialsResolver;
    }
}
