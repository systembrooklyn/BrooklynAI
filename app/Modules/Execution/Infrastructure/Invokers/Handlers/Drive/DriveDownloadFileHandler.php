<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DriveDownloadFileHandler implements ActionHandler
{
    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $fileId = $resolvedConfig['file_id'] ?? null;

        if (! is_string($fileId) || $fileId === '') {
            throw ActionInvocationFailed::forInvalidConfig('file_id');
        }

        $creds = $this->credentials->resolve($userId, $connectionId);
        $bytes = $this->drive->downloadFile($creds, $fileId);

        $storagePath = 'drive-downloads/'.$userId.'/'.Str::uuid()->toString().'.bin';
        Storage::disk('local')->put($storagePath, $bytes);

        return [
            'file_id' => $fileId,
            'storage_path' => $storagePath,
            'size' => strlen($bytes),
        ];
    }
}
