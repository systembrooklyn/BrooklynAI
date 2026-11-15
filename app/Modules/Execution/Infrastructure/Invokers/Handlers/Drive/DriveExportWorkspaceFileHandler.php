<?php

namespace App\Modules\Execution\Infrastructure\Invokers\Handlers\Drive;

use App\Modules\Connections\Core\Contracts\GoogleCredentialsResolver;
use App\Modules\Execution\Core\Contracts\ActionHandler;
use App\Modules\Execution\Core\Exceptions\ActionInvocationFailed;
use App\Modules\Integrations\Infrastructure\Google\Drive\GoogleDriveClient;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DriveExportWorkspaceFileHandler implements ActionHandler
{
    private const MAX_BYTES = 10 * 1024 * 1024; // 10 MB Drive export limit

    public function __construct(
        private readonly GoogleCredentialsResolver $credentials,
        private readonly GoogleDriveClient $drive,
    ) {}

    public function handle(int $userId, ?int $connectionId, array $resolvedConfig): array
    {
        $fileId = $resolvedConfig['file_id'] ?? null;
        $mimeType = $resolvedConfig['mime_type'] ?? 'application/pdf';

        if (! is_string($fileId) || $fileId === '') {
            throw ActionInvocationFailed::forInvalidConfig('file_id');
        }
        if (! is_string($mimeType) || $mimeType === '') {
            throw ActionInvocationFailed::forInvalidConfig('mime_type');
        }

        $creds = $this->credentials->resolve($userId, $connectionId);
        $bytes = $this->drive->exportFile($creds, $fileId, $mimeType);

        if (strlen($bytes) > self::MAX_BYTES) {
            throw new ActionInvocationFailed(
                'Drive export exceeds 10 MB limit ('.strlen($bytes).' bytes). This is a Google Drive API limitation.'
            );
        }

        $extension = match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
            default => 'bin',
        };

        $storagePath = 'drive-exports/'.$userId.'/'.Str::uuid()->toString().'.'.$extension;
        Storage::disk('local')->put($storagePath, $bytes);

        return [
            'file_id' => $fileId,
            'storage_path' => $storagePath,
            'mime_type' => $mimeType,
            'size' => strlen($bytes),
        ];
    }
}
