<?php

namespace Tests\Support\Fakes;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveFileUploader;

final class FakeGoogleDriveFileUploader extends GoogleDriveFileUploader
{
    /** @var array<int, array{bytes: string, filename: string, mime_type: string}> */
    public array $uploadCalls = [];

    public array $nextUploadReturn = ['id' => 'fake-id', 'web_view_link' => null];

    public function uploadWithMetadata(ResolvedGoogleCredentials $credentials, string $bytes, string $filename, string $mimeType): array
    {
        $this->uploadCalls[] = [
            'bytes' => $bytes,
            'filename' => $filename,
            'mime_type' => $mimeType,
        ];

        return $this->nextUploadReturn;
    }
}
