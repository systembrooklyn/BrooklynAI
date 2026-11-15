<?php

namespace Tests\Feature\Execution\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive\DriveUpdateFileMetadataHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Fakes\FakeGoogleCredentialsResolver;
use Tests\Support\Fakes\FakeGoogleDriveClient;
use Tests\TestCase;

final class DriveUpdateFileMetadataHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_description_only(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextUpdateMetadataReturn = ['id' => 'f1', 'name' => 'doc.pdf', 'description' => 'new desc'];

        $handler = new DriveUpdateFileMetadataHandler(new FakeGoogleCredentialsResolver, $drive);
        $result = $handler->handle(1, null, [
            'file_id' => 'f1',
            'description' => 'new desc',
        ]);

        $this->assertSame('f1', $result['id']);
        $this->assertCount(1, $drive->updateMetadataCalls);
        $this->assertSame('f1', $drive->updateMetadataCalls[0]['file_id']);
        $this->assertSame(['description' => 'new desc'], $drive->updateMetadataCalls[0]['updates']);
    }

    public function test_updates_properties_only(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextUpdateMetadataReturn = ['id' => 'f1', 'properties' => ['k' => 'v']];

        $handler = new DriveUpdateFileMetadataHandler(new FakeGoogleCredentialsResolver, $drive);
        $handler->handle(1, null, [
            'file_id' => 'f1',
            'properties' => '{"k":"v"}',
        ]);

        $this->assertSame(['properties' => ['k' => 'v']], $drive->updateMetadataCalls[0]['updates']);
    }

    public function test_updates_both_description_and_properties(): void
    {
        $drive = new FakeGoogleDriveClient;
        $drive->nextUpdateMetadataReturn = ['id' => 'f1'];

        $handler = new DriveUpdateFileMetadataHandler(new FakeGoogleCredentialsResolver, $drive);
        $handler->handle(1, null, [
            'file_id' => 'f1',
            'description' => 'd',
            'properties' => '{"a":"b"}',
        ]);

        $this->assertSame([
            'description' => 'd',
            'properties' => ['a' => 'b'],
        ], $drive->updateMetadataCalls[0]['updates']);
    }

    public function test_throws_when_nothing_provided(): void
    {
        $handler = new DriveUpdateFileMetadataHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('At least one of description or properties');

        $handler->handle(1, null, ['file_id' => 'f1']);
    }

    public function test_throws_on_missing_file_id(): void
    {
        $handler = new DriveUpdateFileMetadataHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('file_id');

        $handler->handle(1, null, ['description' => 'd']);
    }

    public function test_throws_on_invalid_properties_json(): void
    {
        $handler = new DriveUpdateFileMetadataHandler(new FakeGoogleCredentialsResolver, new FakeGoogleDriveClient);

        $this->expectException(ActionInvocationFailed::class);
        $this->expectExceptionMessage('properties');

        $handler->handle(1, null, [
            'file_id' => 'f1',
            'properties' => 'not-valid-json',
        ]);
    }
}
